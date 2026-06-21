<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../lib/admin-layout.php';
require_once __DIR__ . '/../lib/certificates.php';
require_once __DIR__ . '/../lib/identity-validation.php';

$pdo = db();
admin_ensure_schema($pdo);
app_ensure_certificate_schema($pdo);
identity_ensure_schema($pdo);

admin_require($pdo);

$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid security token.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        $notes = trim((string) ($_POST['notes'] ?? ''));

        if ($action === 'revoke_certificate') {
            $certificateId = (int) ($_POST['certificate_id'] ?? 0);
            if ($certificateId <= 0 || $notes === '') {
                $error = 'Select a certificate and provide a revocation reason.';
            } else {
                $pdo->prepare("
                    UPDATE certificates
                    SET status = 'revoked', revoked_at = NOW(), revoked_reason = ?
                    WHERE id = ? AND status = 'issued'
                ")->execute([$notes, $certificateId]);
                $message = 'Grower certificate revoked.';
            }
        } else {
        $docId = (int) ($_POST['doc_id'] ?? 0);

        if ($action === 'verify') {
            $docStmt = $pdo->prepare("SELECT document_type, api_validation_status FROM document_requirements WHERE id = ? LIMIT 1");
            $docStmt->execute([$docId]);
            $doc = $docStmt->fetch();
            if (in_array((string) ($doc['document_type'] ?? ''), ['nin', 'bvn'], true) && (string) ($doc['api_validation_status'] ?? '') !== 'valid') {
                $error = 'NIN/BVN must be valid through Monnify before manual verification.';
            } else {
            $pdo->prepare("
                UPDATE document_requirements
                SET verification_status = 'verified', verified = 1, verified_at = NOW(), verified_by = ?
                WHERE id = ?
            ")->execute([$_SESSION['user_id'] ?? null, $docId]);
            }
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
}

$pendingDocs = [];
$filesByRequirement = [];
$issuedCertificates = [];
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

    $issuedCertificates = $pdo->query("
        SELECT c.*, COALESCE(c.certificate_ref, c.qr_code_hash, a.app_ref) display_ref,
               a.app_ref, a.name, a.location, u.email
        FROM certificates c
        JOIN applications a ON a.id = c.application_id
        LEFT JOIN users u ON u.id = c.user_id
        ORDER BY c.issued_at DESC
        LIMIT 120
    ")->fetchAll();
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
      <tr><th>User</th><th>Document Type</th><th>Document Number</th><th>Monnify</th><th>Uploaded</th><th>Actions</th></tr>
    </thead>
    <tbody>
      <?php foreach ($pendingDocs as $doc): ?>
        <tr>
          <td><?= e($doc['name']) ?><br><small><?= e($doc['email']) ?></small></td>
          <td><?= e(ucfirst(str_replace('_', ' ', (string) $doc['document_type']))) ?></td>
          <td><?= e($doc['document_number']) ?></td>
          <td><?= e(status_label((string) ($doc['api_validation_status'] ?? 'not checked'))) ?></td>
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
      <?php if (!$pendingDocs): ?><tr><td colspan="6">No pending documents.</td></tr><?php endif; ?>
    </tbody>
  </table>

  <section class="panel" style="margin-top:18px;">
    <h2>Issued Grower Certificates</h2>
    <p class="muted">Grower participation certificates are time-bound credentials. They can expire or be revoked for compliance, identity, farm-status, seller-accreditation, or participation issues.</p>
    <table>
      <thead><tr><th>Grower</th><th>Certificate</th><th>Status</th><th>Validity</th><th>Action</th></tr></thead>
      <tbody>
      <?php foreach ($issuedCertificates as $cert): ?>
        <?php $isExpired = !empty($cert['expires_at']) && strtotime((string) $cert['expires_at']) < time(); ?>
        <tr>
          <td><?= e((string) $cert['name']) ?><br><small><?= e((string) ($cert['email'] ?? '')) ?></small></td>
          <td><?= e((string) $cert['display_ref']) ?><br><small><?= e((string) $cert['location']) ?></small></td>
          <td><?= e((string) $cert['status']) ?><?= $isExpired ? ' / expired' : '' ?></td>
          <td>
            Issued <?= e(date('M j, Y', strtotime((string) $cert['issued_at']))) ?><br>
            <small>Valid until <?= !empty($cert['expires_at']) ? e(date('M j, Y', strtotime((string) $cert['expires_at']))) : 'not set' ?></small>
          </td>
          <td>
            <?php if ((string) $cert['status'] === 'issued'): ?>
              <form method="post" class="toolbar">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="revoke_certificate">
                <input type="hidden" name="certificate_id" value="<?= (int) $cert['id'] ?>">
                <input type="text" name="notes" placeholder="Revocation reason" required>
                <button type="submit">Revoke</button>
              </form>
            <?php else: ?>
              <small><?= e((string) ($cert['revoked_reason'] ?? '')) ?></small>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$issuedCertificates): ?><tr><td colspan="5">No grower certificates issued yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </section>
<?php admin_page_end(); ?>
