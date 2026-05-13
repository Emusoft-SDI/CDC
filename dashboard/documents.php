<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/certificates.php';
require_once __DIR__ . '/../lib/dashboard-layout.php';

session_start();
$pdo = db();
app_ensure_farmer_engagement_schema($pdo);
app_ensure_certificate_schema($pdo);

if (empty($_SESSION['user_id'])) {
    redirect_to('login.php');
}

$userId = (int) $_SESSION['user_id'];
$docTypes = [
    'nin' => [
        'label' => 'National ID Number (NIN)',
        'required' => true,
        'multiple' => false,
        'accept' => 'image/jpeg,image/png,application/pdf',
        'hint' => 'Upload a clear image or PDF of your NIN slip/card. Accepted: JPG, PNG, PDF. Max 5MB.',
    ],
    'bvn' => [
        'label' => 'Bank Verification Number (BVN)',
        'required' => true,
        'multiple' => false,
        'accept' => 'image/jpeg,image/png,application/pdf',
        'hint' => 'Upload BVN evidence or bank-issued verification document. Accepted: JPG, PNG, PDF. Max 5MB.',
    ],
    'land_title' => [
        'label' => 'Farm Ownership or Access Proof',
        'required' => true,
        'multiple' => false,
        'accept' => 'image/jpeg,image/png,application/pdf',
        'hint' => 'Upload land title, lease, consent letter, cooperative attestation, or access proof. Accepted: JPG, PNG, PDF. Max 5MB.',
    ],
    'id_card' => [
        'label' => 'Photo ID',
        'required' => true,
        'multiple' => false,
        'accept' => 'image/jpeg,image/png,application/pdf',
        'hint' => 'Upload a government, workplace, cooperative, or association photo ID. Accepted: JPG, PNG, PDF. Max 5MB.',
    ],
    'farm_photo' => [
        'label' => 'Farm Photo',
        'required' => false,
        'multiple' => true,
        'accept' => 'image/jpeg,image/png,image/webp',
        'hint' => 'Optional. Add one or more clear farm photos. Accepted: JPG, PNG, WEBP. Max 8MB per photo.',
    ],
];

function document_reference(int $userId, string $docType): string
{
    return 'DOC-' . str_pad((string) $userId, 5, '0', STR_PAD_LEFT) . '-' . strtoupper(str_replace('_', '-', $docType));
}

function uploaded_files_for(array $files, string $docType): array
{
    if (!isset($files['name'][$docType])) {
        return [];
    }

    $names = is_array($files['name'][$docType]) ? $files['name'][$docType] : [$files['name'][$docType]];
    $tmpNames = is_array($files['tmp_name'][$docType]) ? $files['tmp_name'][$docType] : [$files['tmp_name'][$docType]];
    $errors = is_array($files['error'][$docType]) ? $files['error'][$docType] : [$files['error'][$docType]];
    $sizes = is_array($files['size'][$docType]) ? $files['size'][$docType] : [$files['size'][$docType]];
    $types = is_array($files['type'][$docType]) ? $files['type'][$docType] : [$files['type'][$docType]];

    $uploads = [];
    foreach ($names as $index => $name) {
        $error = (int) ($errors[$index] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $uploads[] = [
            'name' => (string) $name,
            'tmp_name' => (string) ($tmpNames[$index] ?? ''),
            'error' => $error,
            'size' => (int) ($sizes[$index] ?? 0),
            'type' => (string) ($types[$index] ?? ''),
        ];
    }
    return $uploads;
}

function status_label(string $status): string
{
    return ucwords(str_replace('_', ' ', $status));
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Please refresh and try again.';
    } else {
        $files = (array) ($_FILES['doc_file'] ?? []);
        $uploadDir = dirname(__DIR__) . '/documents';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        foreach ($docTypes as $docType => $meta) {
            $existing = $pdo->prepare("SELECT * FROM document_requirements WHERE user_id = ? AND document_type = ? LIMIT 1");
            $existing->execute([$userId, $docType]);
            $current = $existing->fetch();

            if ($current && ($current['verification_status'] ?? '') === 'verified') {
                continue;
            }

            $uploads = uploaded_files_for($files, $docType);
            if ($uploads === [] && !$current) {
                continue;
            }

            if ($docType === 'farm_photo' && count($uploads) > 6) {
                $error = 'Please upload no more than 6 farm photos at once.';
                break;
            }

            $allowedExtensions = $docType === 'farm_photo' ? ['jpg', 'jpeg', 'png', 'webp'] : ['jpg', 'jpeg', 'png', 'pdf'];
            $maxSize = $docType === 'farm_photo' ? 8 * 1024 * 1024 : 5 * 1024 * 1024;
            $savedFiles = [];

            foreach ($uploads as $upload) {
                if ($upload['error'] !== UPLOAD_ERR_OK) {
                    $error = $meta['label'] . ' upload failed. Please choose the file again.';
                    break 2;
                }

                $extension = strtolower(pathinfo($upload['name'], PATHINFO_EXTENSION));
                if (!in_array($extension, $allowedExtensions, true)) {
                    $error = $meta['label'] . ' has an unsupported file type.';
                    break 2;
                }

                if ($upload['size'] > $maxSize) {
                    $error = $meta['label'] . ' exceeds the allowed file size.';
                    break 2;
                }

                $safeName = 'user_' . $userId . '_' . $docType . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
                if (!move_uploaded_file($upload['tmp_name'], $uploadDir . '/' . $safeName)) {
                    $error = 'Failed to upload ' . $meta['label'] . '.';
                    break 2;
                }

                $savedFiles[] = [
                    'path' => 'documents/' . $safeName,
                    'original_name' => $upload['name'],
                    'mime_type' => $upload['type'],
                    'file_size' => $upload['size'],
                ];
            }

            $documentNumber = (string) ($current['document_number'] ?? document_reference($userId, $docType));
            $primaryPath = $savedFiles[0]['path'] ?? ($current['file_path'] ?? null);

            if ($current) {
                $stmt = $pdo->prepare("
                    UPDATE document_requirements
                    SET document_number = ?, file_path = ?, verification_status = 'pending', verified = 0, verification_notes = NULL
                    WHERE user_id = ? AND document_type = ?
                ");
                $stmt->execute([$documentNumber, $primaryPath, $userId, $docType]);
                $requirementId = (int) $current['id'];
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO document_requirements (user_id, document_type, document_number, file_path, verification_status, verified)
                    VALUES (?, ?, ?, ?, 'pending', 0)
                ");
                $stmt->execute([$userId, $docType, $documentNumber, $primaryPath]);
                $requirementId = (int) $pdo->lastInsertId();
            }

            foreach ($savedFiles as $savedFile) {
                $fileStmt = $pdo->prepare("
                    INSERT INTO document_files (requirement_id, user_id, document_type, file_path, original_name, mime_type, file_size)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $fileStmt->execute([
                    $requirementId,
                    $userId,
                    $docType,
                    $savedFile['path'],
                    $savedFile['original_name'],
                    $savedFile['mime_type'],
                    $savedFile['file_size'],
                ]);
            }
        }

        if ($error === '') {
            $message = 'Documents saved. The NATCODEV team will review submitted files.';
            $appStmt = $pdo->prepare("SELECT application_id FROM users WHERE id = ?");
            $appStmt->execute([$userId]);
            $appId = (int) $appStmt->fetchColumn();
            if ($appId > 0 && canIssueCertificate($userId, $pdo)) {
                generateCertificate($appId, $userId, $pdo);
                $message = 'Documents saved and your certificate has been issued.';
            }
        }
    }
}

$stmt = $pdo->prepare("SELECT * FROM document_requirements WHERE user_id = ?");
$stmt->execute([$userId]);
$docs = [];
foreach ($stmt->fetchAll() as $doc) {
    $docs[$doc['document_type']] = $doc;
}

$fileStmt = $pdo->prepare("SELECT * FROM document_files WHERE user_id = ? ORDER BY uploaded_at DESC");
$fileStmt->execute([$userId]);
$filesByType = [];
foreach ($fileStmt->fetchAll() as $file) {
    $filesByType[$file['document_type']][] = $file;
}

$submittedRequired = 0;
$verifiedRequired = 0;
$requiredCount = 0;
foreach ($docTypes as $docType => $meta) {
    if (!$meta['required']) {
        continue;
    }
    $requiredCount++;
    if (!empty($docs[$docType]['file_path'])) {
        $submittedRequired++;
    }
    if (($docs[$docType]['verification_status'] ?? '') === 'verified') {
        $verifiedRequired++;
    }
}
$progress = $requiredCount > 0 ? (int) round(($submittedRequired / $requiredCount) * 100) : 0;
?>
<?php dashboard_page_start('Identity & Farm Verification', ['active' => 'documents.php', 'description' => 'Upload and monitor the documents required for review and certificate issuance.', 'wide' => true]); ?>

    <h1>Identity & Farm Verification</h1>
    <p class="lead">Upload the documents the admin team needs to validate your participation and issue your certificate. Document references are generated automatically.</p>

    <section class="summary">
      <div class="panel">
        <strong>Submission progress</strong>
        <div class="progress"><span style="width:<?= $progress ?>%;"></span></div>
        <p class="note"><?= $submittedRequired ?> of <?= $requiredCount ?> required document types submitted.</p>
      </div>
      <div class="panel">
        <div class="metric"><?= $verifiedRequired ?></div>
        <div class="muted">Verified</div>
      </div>
      <div class="panel">
        <div class="metric"><?= $requiredCount - $verifiedRequired ?></div>
        <div class="muted">Remaining</div>
      </div>
    </section>

    <?php if ($message): ?><div class="notice success"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>

    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <div class="doc-grid">
        <?php foreach ($docTypes as $docType => $meta):
            $doc = $docs[$docType] ?? null;
            $status = (string) ($doc['verification_status'] ?? 'not submitted');
            $statusClass = str_replace(' ', '-', $status);
            $reference = (string) ($doc['document_number'] ?? document_reference($userId, $docType));
            $locked = $status === 'verified';
            $uploadedFiles = $filesByType[$docType] ?? [];
            if ($uploadedFiles === [] && !empty($doc['file_path'])) {
                $uploadedFiles[] = ['file_path' => $doc['file_path'], 'original_name' => basename((string) $doc['file_path'])];
            }
        ?>
          <section class="doc-card">
            <div class="doc-head">
              <div>
                <h2><?= e($meta['label']) ?></h2>
                <p class="note"><?= $meta['required'] ? 'Required for certificate review.' : 'Optional supporting evidence.' ?></p>
              </div>
              <span class="badge <?= e($statusClass) ?>"><?= e(status_label($status)) ?></span>
            </div>

            <div class="reference"><?= e($reference) ?></div>
            <p class="note"><?= e($meta['hint']) ?></p>
            <?php if (!empty($doc['verification_notes'])): ?><div class="notice error"><?= e($doc['verification_notes']) ?></div><?php endif; ?>

            <label><?= $meta['multiple'] ? 'Add farm photos' : 'Upload document' ?></label>
            <input
              type="file"
              name="doc_file[<?= e($docType) ?>][]"
              accept="<?= e($meta['accept']) ?>"
              <?= $meta['multiple'] ? 'multiple' : '' ?>
              <?= $locked ? 'disabled' : '' ?>
            >

            <?php if ($uploadedFiles): ?>
              <ul class="file-list">
                <?php foreach ($uploadedFiles as $file): ?>
                  <li>
                    <span><?= e((string) ($file['original_name'] ?? basename((string) $file['file_path']))) ?></span>
                    <a href="<?= e(app_base_url() . '/' . ltrim((string) $file['file_path'], '/')) ?>" target="_blank">View</a>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </section>
        <?php endforeach; ?>
      </div>

      <button type="submit">Save Documents</button>
      <p class="note">You can save partial uploads and return later. Verified documents are locked to protect review integrity.</p>
    </form>
  <?php dashboard_page_end(); ?>
