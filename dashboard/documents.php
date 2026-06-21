<?php
declare(strict_types=1);

require_once __DIR__ . '/_user_auth.php';
require_once __DIR__ . '/../lib/certificates.php';
require_once __DIR__ . '/../lib/dashboard-layout.php';
require_once __DIR__ . '/../lib/identity-validation.php';

$pdo = db();
app_ensure_farmer_engagement_schema($pdo);
app_ensure_certificate_schema($pdo);
identity_ensure_schema($pdo);

if (!function_exists('status_label')) {
    function status_label(string $status): string
    {
        return ucwords(str_replace('_', ' ', $status));
    }
}

$userId = (int) $_SESSION['user_id'];
$currentUser = current_user($pdo);
if (!$currentUser) {
    session_destroy();
    redirect_to('login.php');
}
dashboard_redirect_learner_only($pdo, $currentUser);
$docTypes = [
    'nin' => [
        'label' => 'National ID Number (NIN)',
        'required' => true,
        'multiple' => false,
        'accept' => 'image/jpeg,image/png,application/pdf',
        'hint' => 'Enter your 11-digit NIN and upload a clear image or PDF of your NIN slip/card. Accepted: JPG, PNG, PDF. Max 5MB.',
        'number_label' => 'NIN Number',
        'number_required' => true,
    ],
    'bvn' => [
        'label' => 'Bank Verification Number (BVN)',
        'required' => true,
        'multiple' => false,
        'accept' => '',
        'hint' => 'Enter your 11-digit BVN. BVN has no physical card, so upload is not required.',
        'number_label' => 'BVN Number',
        'number_required' => true,
        'upload_optional' => true,
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


// status_label is now provided by lib/admin-layout.php

function document_status_text(?array $doc, bool $numberOnly = false): string
{
    if (!$doc) {
        return 'Not submitted';
    }
    $status = (string) ($doc['verification_status'] ?? 'pending');
    if ($status === 'verified') {
        return 'Approved';
    }
    if ($status === 'rejected') {
        return 'Needs correction';
    }
    if ($numberOnly && !empty($doc['document_number'])) {
        return 'Number submitted';
    }
    return $status === 'pending' ? 'Pending review' : status_label($status);
}

function dashboard_short_date(?string $date, string $fallback = 'Pending'): string
{
    if (!$date) {
        return $fallback;
    }
    $time = strtotime($date);
    return $time ? date('M j, Y', $time) : $fallback;
}

function dashboard_verification_badge_class(string $status): string
{
    $status = strtolower($status);
    if (str_contains($status, 'approved') || str_contains($status, 'verified')) {
        return 'verified';
    }
    if (str_contains($status, 'correction') || str_contains($status, 'rejected')) {
        return 'rejected';
    }
    if (str_contains($status, 'pending') || str_contains($status, 'review') || str_contains($status, 'submitted')) {
        return 'pending';
    }
    return 'not-submitted';
}

function document_file_view_url(array $file): string
{
    if (!empty($file['id'])) {
        return 'view-document.php?id=' . urlencode((string) $file['id']);
    }
    if (!empty($file['document_type'])) {
        return 'view-document.php?type=' . urlencode((string) $file['document_type']);
    }
    return '#';
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
            $postedNumber = preg_replace('/\D+/', '', (string) ($_POST['document_number'][$docType] ?? ''));
            $numberRequired = (bool) ($meta['number_required'] ?? false);
            $uploadOptional = (bool) ($meta['upload_optional'] ?? false);
            if ($numberRequired && $postedNumber !== '' && strlen($postedNumber) !== 11) {
                $error = $meta['label'] . ' requires an 11-digit number.';
                break;
            }
            if ($uploads === [] && !$current && $postedNumber === '') {
                continue;
            }

            if ($docType === 'farm_photo' && count($uploads) > 6) {
                $error = 'Please upload no more than 6 farm photos at once.';
                break;
            }

            $allowedExtensions = $docType === 'farm_photo' ? ['jpg', 'jpeg', 'png', 'webp'] : ['jpg', 'jpeg', 'png', 'pdf'];
            $allowedMimes = $docType === 'farm_photo'
                ? ['image/jpeg', 'image/png', 'image/webp']
                : ['image/jpeg', 'image/png', 'application/pdf'];
            $maxSize = $docType === 'farm_photo' ? 8 * 1024 * 1024 : 5 * 1024 * 1024;
            $savedFiles = [];

            foreach ($uploads as $upload) {
                try {
                    $validatedUpload = app_uploaded_file_info($upload, $allowedExtensions, $maxSize, $meta['label'], $allowedMimes);
                } catch (Throwable $e) {
                    $error = $e->getMessage();
                    break 2;
                }

                $safeName = app_safe_upload_name('user_' . $userId . '_' . $docType, $validatedUpload['name'], $validatedUpload['extension']);
                if (!move_uploaded_file($validatedUpload['tmp_name'], $uploadDir . '/' . $safeName)) {
                    $error = 'Failed to upload ' . $meta['label'] . '.';
                    break 2;
                }

                $savedFiles[] = [
                    'path' => 'documents/' . $safeName,
                    'original_name' => $validatedUpload['name'],
                    'mime_type' => $validatedUpload['type'],
                    'file_size' => $validatedUpload['size'],
                ];
            }

            $documentNumber = $postedNumber !== '' ? $postedNumber : (string) ($current['document_number'] ?? document_reference($userId, $docType));
            $primaryPath = $savedFiles[0]['path'] ?? ($current['file_path'] ?? null);
            if ($numberRequired && strlen($documentNumber) !== 11) {
                $error = $meta['label'] . ' requires an 11-digit number.';
                break;
            }
            if (!$uploadOptional && !$primaryPath && !$current && !in_array($docType, ['bvn'], true)) {
                continue;
            }

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

            if (in_array($docType, ['nin', 'bvn'], true)) {
                $identity = identity_validate_requirement($pdo, $requirementId);
                if (($identity['status'] ?? '') === 'valid') {
                    $message = trim($message . ' ' . strtoupper($docType) . ' validation evidence captured; admin review is still required.');
                } elseif (($identity['status'] ?? '') === 'invalid') {
                    $message = trim($message . ' ' . strtoupper($docType) . ' could not be matched automatically and is pending manual review.');
                } elseif (($identity['status'] ?? '') === 'error') {
                    $message = trim($message . ' ' . strtoupper($docType) . ' validation could not be completed and is pending manual review.');
                }
            }
        }

        if ($error === '') {
            $message = $message !== '' ? $message : 'Documents saved. The NATCODEV team will review submitted files.';
            $appStmt = $pdo->prepare("SELECT application_id FROM users WHERE id = ?");
            $appStmt->execute([$userId]);
            $appId = (int) $appStmt->fetchColumn();
            if ($appId > 0 && canIssueCertificate($userId, $pdo)) {
                if (grower_certificate_payment_required($pdo) && !grower_certificate_is_paid($pdo, $userId, $appId)) {
                    $message = 'Documents saved and verified. Open Certificates to pay the certificate fee and issue your grower certificate.';
                } else {
                    generateCertificate($appId, $userId, $pdo);
                    $message = 'Documents saved and your certificate has been issued.';
                }
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
    $hasDocumentSubmission = !empty($docs[$docType]['file_path'])
        || (!empty($meta['number_required']) && !empty($docs[$docType]['document_number']));
    if ($hasDocumentSubmission) {
        $submittedRequired++;
    }
    if (($docs[$docType]['verification_status'] ?? '') === 'verified') {
        $verifiedRequired++;
    }
}
$progress = $requiredCount > 0 ? (int) round(($submittedRequired / $requiredCount) * 100) : 0;

$profileStmt = $pdo->prepare("
    SELECT u.application_id, a.app_ref, a.created_at application_created_at
    FROM users u
    LEFT JOIN applications a ON a.id = u.application_id
    WHERE u.id = ?
    LIMIT 1
");
$profileStmt->execute([$userId]);
$verificationProfile = $profileStmt->fetch() ?: [];
$verificationRef = (string) ($verificationProfile['app_ref'] ?? '');
if ($verificationRef === '') {
    $verificationRef = 'VER-' . str_pad((string) $userId, 5, '0', STR_PAD_LEFT);
}

$latestDocumentUpdate = null;
foreach ($filesByType as $files) {
    foreach ($files as $file) {
        $uploadedAt = (string) ($file['uploaded_at'] ?? '');
        if ($uploadedAt !== '' && ($latestDocumentUpdate === null || strtotime($uploadedAt) > strtotime($latestDocumentUpdate))) {
            $latestDocumentUpdate = $uploadedAt;
        }
    }
}

$primaryFarm = null;
if (app_table_exists($pdo, 'grower_farms')) {
    $farmStmt = $pdo->prepare("
        SELECT gf.*, ns.state_name, nl.lga_name
        FROM grower_farms gf
        LEFT JOIN nigeria_states ns ON ns.id = gf.state_id
        LEFT JOIN nigeria_lgas nl ON nl.id = gf.lga_id
        WHERE gf.user_id = ?
        ORDER BY gf.is_primary DESC, gf.created_at ASC
        LIMIT 1
    ");
    $farmStmt->execute([$userId]);
    $primaryFarm = $farmStmt->fetch() ?: null;
}

$fieldTask = null;
if ($primaryFarm && app_table_exists($pdo, 'field_tasks')) {
    $taskStmt = $pdo->prepare("
        SELECT ft.*, agent.name agent_name
        FROM field_tasks ft
        LEFT JOIN users agent ON agent.id = ft.assigned_to
        WHERE ft.farm_id = ?
        ORDER BY FIELD(ft.status, 'in_progress', 'assigned', 'pending', 'completed', 'cancelled'), ft.due_date IS NULL, ft.due_date ASC, ft.created_at DESC
        LIMIT 1
    ");
    $taskStmt->execute([(int) $primaryFarm['id']]);
    $fieldTask = $taskStmt->fetch() ?: null;
}

$fieldVisit = null;
if ($primaryFarm && app_table_exists($pdo, 'farm_visits')) {
    $visitStmt = $pdo->prepare("
        SELECT fv.*, agent.name agent_name
        FROM farm_visits fv
        LEFT JOIN users agent ON agent.id = fv.agent_id
        WHERE fv.farm_id = ?
        ORDER BY fv.visited_at DESC, fv.created_at DESC
        LIMIT 1
    ");
    $visitStmt->execute([(int) $primaryFarm['id']]);
    $fieldVisit = $visitStmt->fetch() ?: null;
}

$documentChecklist = [
    ['label' => 'National ID / NIN', 'type' => 'nin', 'number_only' => false],
    ['label' => 'BVN Identity Check', 'type' => 'bvn', 'number_only' => true],
    ['label' => 'Farm Proof / Land or Lease Document', 'type' => 'land_title', 'number_only' => false],
    ['label' => 'Photo ID / Passport Photo', 'type' => 'id_card', 'number_only' => false],
    ['label' => 'Farm Evidence Photos', 'type' => 'farm_photo', 'number_only' => false],
];

$fieldAgent = (string) ($fieldVisit['agent_name'] ?? $fieldTask['agent_name'] ?? '');
$fieldVisitStatus = $fieldVisit
    ? status_label((string) ($fieldVisit['result'] ?? 'submitted'))
    : ($fieldTask ? status_label((string) ($fieldTask['status'] ?? 'assigned')) : 'Not assigned');
$fieldVisitDate = $fieldVisit
    ? dashboard_short_date((string) ($fieldVisit['visited_at'] ?? null))
    : ($fieldTask ? dashboard_short_date((string) ($fieldTask['due_date'] ?? null), 'Awaiting schedule') : 'Awaiting assignment');

$overallStatus = 'Pending';
if ($verifiedRequired >= $requiredCount && $requiredCount > 0) {
    $overallStatus = 'Approved';
} elseif ($submittedRequired > 0 || $fieldTask || $fieldVisit) {
    $overallStatus = 'In progress';
}

$verificationSteps = [
    ['label' => 'Documents Submitted', 'done' => $submittedRequired > 0, 'detail' => $submittedRequired . ' of ' . $requiredCount . ' required'],
    ['label' => 'Field Visit In Progress', 'done' => (bool) ($fieldTask || $fieldVisit), 'detail' => $fieldAgent !== '' ? $fieldAgent : 'Awaiting field team'],
    ['label' => 'Review Pending', 'done' => $verifiedRequired > 0 || $fieldVisit, 'detail' => $verifiedRequired . ' verified'],
    ['label' => 'Decision Pending', 'done' => $verifiedRequired >= $requiredCount && $requiredCount > 0, 'detail' => $overallStatus],
];

$evidenceTiles = [];
foreach (($filesByType['farm_photo'] ?? []) as $file) {
    if (count($evidenceTiles) >= 4) {
        break;
    }
    $evidenceTiles[] = [
        'label' => count($evidenceTiles) === 0 ? 'Farm Entrance' : (count($evidenceTiles) === 1 ? 'Coconut Block' : (count($evidenceTiles) === 2 ? 'Intercrops' : 'Livestock')),
        'file' => $file,
    ];
}
$gpsText = 'Pending GPS evidence';
if ($fieldVisit && $fieldVisit['visit_latitude'] !== null && $fieldVisit['visit_longitude'] !== null) {
    $gpsText = number_format((float) $fieldVisit['visit_latitude'], 5) . ', ' . number_format((float) $fieldVisit['visit_longitude'], 5);
} elseif ($primaryFarm && $primaryFarm['latitude'] !== null && $primaryFarm['longitude'] !== null) {
    $gpsText = number_format((float) $primaryFarm['latitude'], 5) . ', ' . number_format((float) $primaryFarm['longitude'], 5);
}
?>
<?php dashboard_page_start('Identity & Farm Verification', [
    'active' => 'documents.php',
    'description' => 'Upload identity and farm evidence, track review status, and unlock certificate eligibility without guessing the next step.',
    'wide' => true,
]); ?>

    <style>
      .verification-workspace { display:grid; gap:18px; margin-bottom:18px; }
      .verification-hero { display:grid; grid-template-columns:minmax(0,1.15fr) minmax(300px,.85fr); gap:18px; align-items:stretch; }
      .verification-panel { background:#fff; border:1px solid rgba(24,43,18,.1); border-radius:8px; box-shadow:var(--shadow); padding:18px; }
      .verification-head { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:16px; }
      .verification-head h2, .verification-panel h2 { margin:0; color:var(--green); font-size:1.15rem; }
      .verification-head p { margin:6px 0 0; color:var(--muted); line-height:1.45; }
      .verification-status { display:flex; align-items:center; gap:12px; padding:14px; border:1px solid var(--line); border-radius:8px; background:linear-gradient(135deg,#f8fbf5,#fff); }
      .verification-status strong { display:block; color:var(--green); font-size:1.35rem; line-height:1.1; }
      .verification-mark { width:52px; height:52px; border-radius:50%; display:grid; place-items:center; color:#fff; background:var(--leaf); font-weight:900; flex:0 0 auto; }
      .verification-steps { display:grid; gap:10px; margin-top:16px; }
      .verification-step { display:grid; grid-template-columns:34px minmax(0,1fr) auto; gap:10px; align-items:center; padding:10px; border:1px solid var(--line); border-radius:7px; background:#fbfcfa; }
      .step-dot { width:28px; height:28px; border-radius:50%; display:grid; place-items:center; background:#eef2e9; color:#66715f; font-weight:900; }
      .verification-step.is-done .step-dot { background:#eaf8f0; color:#0f6b3c; }
      .verification-step strong { color:#26351f; }
      .verification-step span { color:var(--muted); font-size:.9rem; display:block; margin-top:2px; }
      .verification-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:14px; }
      .status-list { display:grid; gap:10px; }
      .status-row { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:10px 0; border-bottom:1px solid #edf1ea; }
      .status-row:last-child { border-bottom:0; }
      .status-row strong { color:#26351f; }
      .status-row small { color:var(--muted); display:block; margin-top:2px; }
      .field-meta { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; margin-top:12px; }
      .field-meta div { border:1px solid var(--line); border-radius:7px; padding:10px; background:#fbfcfa; }
      .field-meta span { display:block; color:var(--muted); font-size:.82rem; margin-bottom:4px; }
      .field-meta strong { color:var(--green); }
      .evidence-strip { display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:12px; }
      .evidence-tile { min-height:118px; border:1px solid var(--line); border-radius:8px; overflow:hidden; background:#f8faf6; display:flex; flex-direction:column; justify-content:space-between; }
      .evidence-tile img { width:100%; height:82px; object-fit:cover; display:block; }
      .evidence-empty { height:82px; display:grid; place-items:center; color:var(--muted); font-weight:800; background:#eef4ea; }
      .evidence-tile strong { display:block; padding:8px 9px; color:#26351f; font-size:.88rem; }
      .verification-actions { display:flex; flex-wrap:wrap; gap:10px; margin-top:14px; }
      @media (max-width: 980px) {
        .verification-hero, .verification-grid, .evidence-strip { grid-template-columns:1fr; }
        .verification-step { grid-template-columns:34px minmax(0,1fr); }
        .verification-step > .badge { grid-column:2; width:max-content; }
      }
      @media (max-width: 560px) {
        .field-meta { grid-template-columns:1fr; }
      }
    </style>

    <section class="verification-workspace" aria-label="Identity and farm verification workspace">
      <div class="verification-hero">
        <section class="verification-panel">
          <div class="verification-head">
            <div>
              <h2>Verification Status</h2>
              <p>Your identity, farm evidence, field visit, and final decision are tracked here before certificate eligibility.</p>
            </div>
            <?= ntv_badge(strtolower(str_replace(' ', '_', $overallStatus)), $overallStatus) ?>
          </div>
          <div class="verification-status">
            <div class="verification-mark"><?= $overallStatus === 'Approved' ? 'OK' : $progress . '%' ?></div>
            <div>
              <strong><?= e($overallStatus) ?></strong>
              <span class="muted"><?= $submittedRequired ?> of <?= $requiredCount ?> required documents submitted, <?= $verifiedRequired ?> approved.</span>
            </div>
          </div>
          <div class="verification-steps">
            <?php foreach ($verificationSteps as $step): ?>
              <div class="verification-step <?= $step['done'] ? 'is-done' : '' ?>">
                <div class="step-dot"><?= $step['done'] ? '✓' : '•' ?></div>
                <div>
                  <strong><?= e($step['label']) ?></strong>
                  <span><?= e($step['detail']) ?></span>
                </div>
                <?= ntv_badge($step['done'] ? 'verified' : 'pending', $step['done'] ? 'Done' : 'Pending') ?>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="verification-actions">
            <a class="button" href="#verification-documents-form">Upload / Correct Documents</a>
            <a class="button secondary" href="certificates.php">View Certificates</a>
          </div>
        </section>

        <section class="verification-panel">
          <div class="verification-head">
            <div>
              <h2>Overall Record</h2>
              <p>Use this reference when contacting support or when a field team asks for your grower record.</p>
            </div>
          </div>
          <div class="status-list">
            <div class="status-row"><strong>Reference No.</strong><span><?= e($verificationRef) ?></span></div>
            <div class="status-row"><strong>Submitted On</strong><span><?= e(dashboard_short_date((string) ($verificationProfile['application_created_at'] ?? null))) ?></span></div>
            <div class="status-row"><strong>Last Updated</strong><span><?= e(dashboard_short_date($latestDocumentUpdate, 'No upload yet')) ?></span></div>
            <div class="status-row"><strong>Next Step</strong><span><?= $overallStatus === 'Approved' ? 'Certificate review' : 'Complete missing evidence' ?></span></div>
          </div>
          <div class="progress" aria-label="Verification progress"><span style="width:<?= $progress ?>%;"></span></div>
          <p class="note">Verified documents are locked. Rejected or pending records remain editable from the submission area below.</p>
        </section>
      </div>

      <div class="verification-grid">
        <section class="verification-panel">
          <div class="verification-head">
            <div>
              <h2>Documents Submitted</h2>
              <p>Required records are checked individually before approval.</p>
            </div>
          </div>
          <div class="status-list">
            <?php foreach ($documentChecklist as $item):
                $doc = $docs[$item['type']] ?? null;
                $docStatus = document_status_text($doc, (bool) $item['number_only']);
            ?>
              <div class="status-row">
                <div>
                  <strong><?= e($item['label']) ?></strong>
                  <small><?= !empty($filesByType[$item['type']]) ? count($filesByType[$item['type']]) . ' file(s) attached' : 'No file attached' ?></small>
                </div>
                <span class="badge <?= e(dashboard_verification_badge_class($docStatus)) ?>"><?= e($docStatus) ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        </section>

        <section class="verification-panel">
          <div class="verification-head">
            <div>
              <h2>Field Visit</h2>
              <p>Field checks confirm the farm location and practical evidence.</p>
            </div>
          </div>
          <div class="field-meta">
            <div><span>Field Agent</span><strong><?= e($fieldAgent !== '' ? $fieldAgent : 'Not assigned') ?></strong></div>
            <div><span>Visit Date</span><strong><?= e($fieldVisitDate) ?></strong></div>
            <div><span>Report Status</span><strong><?= e($fieldVisitStatus) ?></strong></div>
            <div><span>Farm</span><strong><?= e((string) ($primaryFarm['farm_name'] ?? 'Farm profile pending')) ?></strong></div>
          </div>
          <p class="note">If no visit is assigned yet, NATCODEV back office must schedule one from field operations.</p>
        </section>

        <section class="verification-panel">
          <div class="verification-head">
            <div>
              <h2>Farm Location</h2>
              <p>Location evidence helps confirm eligibility and field routing.</p>
            </div>
          </div>
          <div class="status-list">
            <div class="status-row"><strong>State</strong><span><?= e((string) ($primaryFarm['state_name'] ?? 'Pending')) ?></span></div>
            <div class="status-row"><strong>LGA</strong><span><?= e((string) ($primaryFarm['lga_name'] ?? 'Pending')) ?></span></div>
            <div class="status-row"><strong>GPS</strong><span><?= e($gpsText) ?></span></div>
          </div>
          <div class="verification-actions">
            <a class="button secondary" href="profile.php#farms">Update Farm Profile</a>
          </div>
        </section>
      </div>

      <section class="verification-panel">
        <div class="verification-head">
          <div>
            <h2>Field Evidence</h2>
            <p>Photos and GPS evidence are shown here after upload or field visit capture.</p>
          </div>
        </div>
        <div class="evidence-strip">
          <?php foreach ($evidenceTiles as $tile):
              $path = (string) ($tile['file']['file_path'] ?? '');
              $url = $path !== '' ? document_file_view_url($tile['file']) : '';
          ?>
            <a class="evidence-tile" href="<?= e($url) ?>" target="_blank">
              <?php if ($url !== ''): ?><img src="<?= e($url) ?>" alt="<?= e($tile['label']) ?>"><?php else: ?><div class="evidence-empty">Pending</div><?php endif; ?>
              <strong><?= e($tile['label']) ?></strong>
            </a>
          <?php endforeach; ?>
          <?php for ($i = count($evidenceTiles); $i < 4; $i++): ?>
            <div class="evidence-tile"><div class="evidence-empty">Pending</div><strong><?= e(['Farm Entrance', 'Coconut Block', 'Intercrops', 'Livestock'][$i]) ?></strong></div>
          <?php endfor; ?>
          <div class="evidence-tile"><div class="evidence-empty"><?= e($gpsText === 'Pending GPS evidence' ? 'Pending' : 'Captured') ?></div><strong>GPS Location</strong></div>
        </div>
      </section>
    </section>

    <?php if ($message): ?><div class="notice success"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>

    <form id="verification-documents-form" method="post" enctype="multipart/form-data">
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
                $uploadedFiles[] = ['file_path' => $doc['file_path'], 'document_type' => $docType, 'original_name' => basename((string) $doc['file_path'])];
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
            <?php if (!empty($meta['number_required'])): ?>
              <label><?= e($meta['number_label'] ?? 'Document Number') ?></label>
              <input type="text" name="document_number[<?= e($docType) ?>]" inputmode="numeric" maxlength="11" pattern="[0-9]{11}" value="<?= e(preg_match('/^\d{11}$/', $reference) ? $reference : '') ?>" <?= $locked ? 'disabled' : 'required' ?>>
            <?php endif; ?>

            <?php if (($meta['accept'] ?? '') !== ''): ?>
              <label><?= $meta['multiple'] ? 'Add farm photos' : 'Upload document' ?></label>
              <input
                type="file"
                name="doc_file[<?= e($docType) ?>][]"
                accept="<?= e($meta['accept']) ?>"
                <?= $meta['multiple'] ? 'multiple' : '' ?>
                <?= $locked ? 'disabled' : '' ?>
              >
            <?php endif; ?>

            <?php if (!empty($doc['api_validation_status'])): ?>
              <p class="note">Monnify: <?= e(status_label((string) $doc['api_validation_status'])) ?><?= !empty($doc['api_validation_timestamp']) ? ' / ' . e(date('M j, Y g:i A', strtotime((string) $doc['api_validation_timestamp']))) : '' ?></p>
            <?php endif; ?>

            <?php if ($uploadedFiles): ?>
              <ul class="file-list">
                <?php foreach ($uploadedFiles as $file): ?>
                  <li>
                    <span><?= e((string) ($file['original_name'] ?? basename((string) $file['file_path']))) ?></span>
                    <a href="<?= e(document_file_view_url($file)) ?>" target="_blank">View</a>
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
