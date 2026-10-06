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
                admin_audit($pdo, 'certificate_revoked', 'Revoked grower certificate #' . $certificateId . ': ' . $notes);
                $message = 'Grower certificate revoked.';
            }
        } else {
        $docId = (int) ($_POST['doc_id'] ?? 0);

        if ($action === 'revalidate') {
            $valResult = identity_validate_requirement($pdo, $docId);
            if (($valResult['status'] ?? '') === 'valid') {
                $message = 'Identity matched successfully via ' . htmlspecialchars((string) ($valResult['provider'] ?? 'gateway')) . ' (Ref: ' . htmlspecialchars((string) ($valResult['reference'] ?? 'N/A')) . ').';
            } elseif (($valResult['status'] ?? '') === 'invalid') {
                $error = 'Identity verification failed: ' . htmlspecialchars((string) ($valResult['message'] ?? 'Record mismatch or invalid number'));
            } else {
                $error = 'Identity verification error: ' . htmlspecialchars((string) ($valResult['message'] ?? 'Provider unavailable'));
            }
        } elseif ($action === 'verify') {
            $docStmt = $pdo->prepare("SELECT document_type, api_validation_status FROM document_requirements WHERE id = ? AND deleted_at IS NULL LIMIT 1");
            $docStmt->execute([$docId]);
            $doc = $docStmt->fetch();
            if (in_array((string) ($doc['document_type'] ?? ''), ['nin', 'bvn'], true) && (string) ($doc['api_validation_status'] ?? '') !== 'valid') {
                $error = 'NIN/BVN must be verified valid through an Identity Gateway before manual approval. You can select "Re-validate via Gateways" to verify live against Monnify, Dojah, NetApps, or QoreID.';
            } else {
            $pdo->prepare("
                UPDATE document_requirements
                SET verification_status = 'verified', verified = 1, verified_at = NOW(), verified_by = ?
                WHERE id = ?
            ")->execute([$_SESSION['user_id'] ?? null, $docId]);
                admin_audit($pdo, 'document_verified', 'Verified document #' . $docId . '.');
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
                admin_audit($pdo, 'document_rejected', 'Rejected document #' . $docId . ': ' . $notes);
            }
        }

        if ($error === '') {
            $stmt = $pdo->prepare("SELECT user_id FROM document_requirements WHERE id = ? AND deleted_at IS NULL");
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
        WHERE dr.verification_status = 'pending' AND dr.deleted_at IS NULL
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
        WHERE c.deleted_at IS NULL
        ORDER BY c.issued_at DESC
        LIMIT 120
    ")->fetchAll();
} catch (Throwable $e) {
    $error = 'Document requirements table is not available yet.';
}
$dvPending = count($pendingDocs);
$dvCertificates = count($issuedCertificates);
$dvExpired = count(array_filter($issuedCertificates, static fn($c): bool => !empty($c['expires_at']) && strtotime((string) $c['expires_at']) < time()));
$dvApiValid = count(array_filter($pendingDocs, static fn($d): bool => in_array(strtolower((string) ($d['api_validation_status'] ?? '')), ['valid', 'verified'], true)));

admin_page_start('Document Verification', [
    'active' => 'document-verification.php',
    'description' => 'Review pending identity and farm documents, reject incomplete uploads, and issue certificates when requirements are complete.',
    'wide' => true,
]);
?>
  <?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>
  <?php if ($message): ?><div class="notice ok"><?= e($message) ?></div><?php endif; ?>

  <?= admin_kpi_grid([
      ['Pending Documents', number_format($dvPending), 'Awaiting review', 'fa-file-circle-exclamation', ''],
      ['API Validated', number_format($dvApiValid), 'Gateway verified', 'fa-shield-halved', 'blue'],
      ['Certificates Issued', number_format($dvCertificates), 'Grower credentials', 'fa-certificate', 'purple'],
      ['Expired', number_format($dvExpired), 'Past validity', 'fa-clock-rotate-left', 'red'],
  ]) ?>

  <section class="panel">
    <div class="user-toolbar">
      <h2 style="margin:0">Pending Document Review</h2>
      <span class="meta"><?= number_format($dvPending) ?> document(s)</span>
    </div>
    <div class="record-list">
      <?php foreach ($pendingDocs as $doc): ?>
        <?php
          $apiStatus = strtolower((string) ($doc['api_validation_status'] ?? 'not checked'));
          $apiTone = in_array($apiStatus, ['valid', 'verified'], true) ? 'ok' : (in_array($apiStatus, ['invalid', 'failed', 'rejected'], true) ? 'bad' : 'warn');
          $files = $filesByRequirement[(int) $doc['id']] ?? [];
        ?>
        <article class="record-row stack">
          <span class="record-avatar file"><i class="fas fa-file-circle-check"></i></span>
          <div class="record-main">
            <div class="record-title">
              <?= e($doc['name']) ?>
              <span class="tag info"><?= e(ucfirst(str_replace('_', ' ', (string) $doc['document_type']))) ?></span>
              <span class="tag <?= e($apiTone) ?>"><i class="fas fa-shield-halved"></i> API: <?= e(status_label($apiStatus)) ?></span>
            </div>
            <div class="record-contact">
              <span><i class="far fa-envelope"></i><?= e($doc['email']) ?></span>
              <?php if (!empty($doc['document_number'])): ?><span><i class="fas fa-hashtag"></i><?= e($doc['document_number']) ?></span><?php endif; ?>
              <span><i class="far fa-calendar"></i><?= e(date('M j, Y', strtotime((string) $doc['uploaded_at']))) ?></span>
              <?php if (!empty($doc['api_validation_provider'])): ?>
                <span><i class="fas fa-server"></i><?= e(ucfirst((string) $doc['api_validation_provider'])) ?><?= !empty($doc['api_validation_reference']) ? ' • ' . e((string) $doc['api_validation_reference']) : '' ?></span>
              <?php endif; ?>
            </div>
            <div class="actions" style="margin-top:10px">
              <?php if ($files): ?>
                <?php foreach ($files as $index => $file): ?>
                  <a class="button secondary sm" href="<?= e(admin_document_public_url((string) $file['file_path'])) ?>" target="_blank" rel="noopener"><i class="fas fa-paperclip"></i> View <?= $index + 1 ?></a>
                <?php endforeach; ?>
              <?php elseif (!empty($doc['file_path'])): ?>
                <a class="button secondary sm" href="<?= e(admin_document_public_url((string) $doc['file_path'])) ?>" target="_blank" rel="noopener"><i class="fas fa-paperclip"></i> View</a>
              <?php else: ?>
                <span class="muted">No file uploaded</span>
              <?php endif; ?>
            </div>
            <form method="post" class="toolbar" style="margin-top:10px">
              <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="doc_id" value="<?= (int) $doc['id'] ?>">
              <select name="action">
                <option value="verify">Verify &amp; Approve</option>
                <?php if (in_array((string) ($doc['document_type'] ?? ''), ['nin', 'bvn'], true)): ?>
                  <option value="revalidate">Re-validate via Gateways</option>
                <?php endif; ?>
                <option value="reject">Reject</option>
              </select>
              <input type="text" name="notes" placeholder="Reason if rejecting">
              <button type="submit"><i class="fas fa-check"></i> Submit</button>
            </form>
          </div>
        </article>
      <?php endforeach; ?>
      <?php if (!$pendingDocs): ?><div class="record-empty">No pending documents.</div><?php endif; ?>
    </div>
  </section>

  <section class="panel" style="margin-top:18px">
    <div class="user-toolbar">
      <h2 style="margin:0">Issued Grower Certificates</h2>
      <span class="meta"><?= number_format($dvCertificates) ?> certificate(s)</span>
    </div>
    <p class="muted">Grower participation certificates are time-bound credentials. They can expire or be revoked for compliance, identity, farm-status, seller-accreditation, or participation issues.</p>
    <div class="record-list">
      <?php foreach ($issuedCertificates as $cert): ?>
        <?php
          $isExpired = !empty($cert['expires_at']) && strtotime((string) $cert['expires_at']) < time();
          $certTone = ((string) $cert['status'] === 'issued' && !$isExpired) ? 'ok' : ($isExpired ? 'bad' : 'warn');
        ?>
        <article class="record-row stack">
          <span class="record-avatar doc"><i class="fas fa-certificate"></i></span>
          <div class="record-main">
            <div class="record-title">
              <?= e((string) $cert['name']) ?>
              <span class="tag <?= e($certTone) ?>"><?= e(ucwords((string) $cert['status'])) ?><?= $isExpired ? ' / expired' : '' ?></span>
            </div>
            <div class="record-contact">
              <span><i class="far fa-envelope"></i><?= e((string) ($cert['email'] ?? '')) ?></span>
              <span><i class="fas fa-location-dot"></i><?= e((string) $cert['location']) ?></span>
              <span><i class="fas fa-hashtag"></i><?= e((string) $cert['display_ref']) ?></span>
              <span><i class="far fa-calendar"></i>Issued <?= e(date('M j, Y', strtotime((string) $cert['issued_at']))) ?> · valid until <?= !empty($cert['expires_at']) ? e(date('M j, Y', strtotime((string) $cert['expires_at']))) : 'not set' ?></span>
            </div>
            <div class="actions" style="margin-top:10px">
              <?php $certVerifyUrl = trim((string) ($cert['verification_url'] ?? '')) !== '' ? (string) $cert['verification_url'] : '../verify-certificate.php?ref=' . urlencode((string) $cert['display_ref']); ?>
              <a class="button secondary sm" href="<?= e($certVerifyUrl) ?>" target="_blank" rel="noopener"><i class="fas fa-up-right-from-square"></i> Verify</a>
              <?php if (!empty($cert['certificate_path'])): ?><a class="button secondary sm" href="<?= e(admin_document_public_url((string) $cert['certificate_path'])) ?>" target="_blank" rel="noopener">HTML</a><?php endif; ?>
              <?php if (!empty($cert['certificate_pdf_path'])): ?><a class="button secondary sm" href="<?= e(admin_document_public_url((string) $cert['certificate_pdf_path'])) ?>" target="_blank" rel="noopener">PDF</a><?php endif; ?>
              <?php if ((string) $cert['status'] === 'issued'): ?>
                <details class="user-manage">
                  <summary class="button secondary sm"><i class="fas fa-ban"></i> Revoke</summary>
                  <form class="user-manage-form" method="post">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="revoke_certificate">
                    <input type="hidden" name="certificate_id" value="<?= (int) $cert['id'] ?>">
                    <label class="field"><span>Revocation reason</span><input type="text" name="notes" required></label>
                    <button type="submit" class="sm"><i class="fas fa-ban"></i> Revoke certificate</button>
                  </form>
                </details>
              <?php elseif (!empty($cert['revoked_reason'])): ?>
                <span class="muted"><?= e((string) $cert['revoked_reason']) ?></span>
              <?php endif; ?>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
      <?php if (!$issuedCertificates): ?><div class="record-empty">No grower certificates issued yet.</div><?php endif; ?>
    </div>
  </section>
<?php admin_page_end(); ?>
