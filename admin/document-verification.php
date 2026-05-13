<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/admin-layout.php';
require_once __DIR__ . '/../lib/certificates.php';

session_start();
$pdo = db();
admin_ensure_schema($pdo);
app_ensure_certificate_schema($pdo);

admin_require($pdo);

$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid security token.';
    } else {
        $docId = (int) ($_POST['doc_id'] ?? 0);
        $action = (string) ($_POST['action'] ?? '');
        $notes = trim((string) ($_POST['notes'] ?? ''));

        if ($action === 'verify') {
            $pdo->prepare("
                UPDATE document_requirements
                SET verification_status = 'verified', verified = 1, verified_at = NOW(), verified_by = ?
                WHERE id = ?
            ")->execute([$_SESSION['user_id'] ?? null, $docId]);
        } elseif ($action === 'reject') {
            if ($notes === '') {
                $error = 'Please provide a rejection reason.';
            } else {
                $pdo->prepare("
                    UPDATE document_requirements
                    SET verification_status = 'rejected', verified = 0, verification_notes = ?, verified_by = ?
                    WHERE id = ?
                ")->execute([$notes, $_SESSION['user_id'] ?? null, $docId]);
            }
        }

        if ($error === '') {
            $stmt = $pdo->prepare("SELECT user_id FROM document_requirements WHERE id = ?");
            $stmt->execute([$docId]);
            $userId = (int) $stmt->fetchColumn();

            if ($userId > 0 && canIssueCertificate($userId, $pdo)) {
                $appStmt = $pdo->prepare("SELECT application_id FROM users WHERE id = ?");
                $appStmt->execute([$userId]);
                $appId = (int) $appStmt->fetchColumn();
                if ($appId > 0) {
                    generateCertificate($appId, $userId, $pdo);
                    $message = 'Document updated and certificate issued.';
                }
            } else {
                $message = 'Document updated.';
            }
        }
    }
}

$pendingDocs = [];
$filesByRequirement = [];
try {
    $stmt = $pdo->prepare("
        SELECT dr.*, u.name, u.email, u.role
        FROM document_requirements dr
        JOIN users u ON dr.user_id = u.id
        WHERE dr.verification_status = 'pending'
        ORDER BY dr.uploaded_at DESC
    ");
    $stmt->execute();
    $pendingDocs = $stmt->fetchAll();

    $docIds = array_map(static fn(array $doc): int => (int) $doc['id'], $pendingDocs);
    if ($docIds) {
        $placeholders = implode(',', array_fill(0, count($docIds), '?'));
        $fileStmt = $pdo->prepare("
            SELECT *
            FROM document_files
            WHERE requirement_id IN ({$placeholders})
            ORDER BY uploaded_at DESC
        ");
        $fileStmt->execute($docIds);
        foreach ($fileStmt->fetchAll() as $file) {
            $filesByRequirement[(int) $file['requirement_id']][] = $file;
        }
    }
} catch (Throwable $e) {
    $error = 'Document requirements table is not available yet.';
}
admin_page_start('Document Verification', [
    'active' => 'document-verification.php',
    'description' => 'Review pending identity and farm documents, reject incomplete uploads, and issue certificates when requirements are complete.',
    'wide' => true,
]);
?>
  <?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>
  <?php if ($message): ?><div class="notice ok"><?= e($message) ?></div><?php endif; ?>

  <table>
    <thead>
      <tr><th>User</th><th>Document Type</th><th>Document Number</th><th>Uploaded</th><th>Actions</th></tr>
    </thead>
    <tbody>
      <?php foreach ($pendingDocs as $doc): ?>
        <tr>
          <td><?= e($doc['name']) ?><br><small><?= e($doc['email']) ?></small></td>
          <td><?= e(ucfirst(str_replace('_', ' ', (string) $doc['document_type']))) ?></td>
          <td><?= e($doc['document_number']) ?></td>
          <td><?= e(date('M j, Y', strtotime((string) $doc['uploaded_at']))) ?></td>
          <td>
            <?php $files = $filesByRequirement[(int) $doc['id']] ?? []; ?>
            <?php if ($files): ?>
              <?php foreach ($files as $index => $file): ?>
                <a href="<?= e(app_base_url() . '/' . ltrim((string) $file['file_path'], '/')) ?>" target="_blank">View <?= $index + 1 ?></a><?= $index < count($files) - 1 ? '<br>' : '' ?>
              <?php endforeach; ?>
            <?php elseif (!empty($doc['file_path'])): ?>
              <a href="<?= e(app_base_url() . '/' . ltrim((string) $doc['file_path'], '/')) ?>" target="_blank">View</a>
            <?php endif; ?>
            <form method="post" class="toolbar" style="margin-top:10px;">
              <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="doc_id" value="<?= (int) $doc['id'] ?>">
              <select name="action">
                <option value="verify">Verify</option>
                <option value="reject">Reject</option>
              </select>
              <input type="text" name="notes" placeholder="Reason if rejecting">
              <button type="submit">Submit</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$pendingDocs): ?><tr><td colspan="5">No pending documents.</td></tr><?php endif; ?>
    </tbody>
  </table>
<?php admin_page_end(); ?>
