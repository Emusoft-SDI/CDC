<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/common.php';

$pageTitle = 'Certificate Management - NATCODEV Registry';
$activeNav = 'certificates';

$search = trim((string) ($_GET['search'] ?? ''));
$where = "1=1";
$params = [];
if ($search !== '') {
    $where = "(c.certificate_ref LIKE ? OR a.name LIKE ? OR a.email LIKE ?)";
    $term = "%$search%";
    array_push($params, $term, $term, $term);
}

$certs = rx_rows($pdo, "
    SELECT c.*, a.name, a.email, a.app_ref
    FROM certificates c
    JOIN applications a ON a.id = c.application_id
    WHERE $where
    ORDER BY c.issued_at DESC
    LIMIT 100
", $params);

require __DIR__ . '/layout/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Certificate Management</h1>
    <p class="page-subtitle">History and validation of all issued participation credentials.</p>
  </div>
  <div class="header-actions">
    <button class="btn btn-secondary" onclick="openModal('batchModal')">Batch Verify Tool</button>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <form method="get" style="display:flex; gap:10px; flex:1">
      <input type="text" name="search" class="form-input" placeholder="Search by Ref, Name, or Email..." value="<?= rx_e($search) ?>" style="max-width:400px">
      <button type="submit" class="btn btn-secondary">Search</button>
    </form>
  </div>
  <div class="card-body p0">
    <table>
      <thead>
        <tr>
          <th>Certificate Ref</th>
          <th>Holder</th>
          <th>Issued</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($certs as $row): ?>
          <tr>
            <td><strong><?= rx_e($row['certificate_ref'] ?: $row['qr_code_hash']) ?></strong></td>
            <td>
              <strong><?= rx_e($row['name']) ?></strong><br>
              <small><?= rx_e($row['email']) ?></small>
            </td>
            <td><?= date('M j, Y', strtotime($row['issued_at'])) ?></td>
            <td><span class="status-badge <?= rx_status_class($row['status']) ?>"><?= ucfirst($row['status']) ?></span></td>
            <td>
              <div style="display:flex; gap:5px">
                <a href="../api/certificate.php?ref=<?= rx_e($row['certificate_ref']) ?>&download=1" class="btn btn-sm btn-secondary">Download</a>
                <?php if($row['status'] === 'issued'): ?>
                  <button type="button" class="danger btn btn-sm btn-danger" onclick="openRevokeModal(<?= $row['id'] ?>, '<?= rx_e($row['certificate_ref']) ?>')">Revoke</button>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$certs): ?><tr><td colspan="5" style="text-align:center; padding:40px">No certificates found.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- BATCH VERIFY MODAL -->
<div class="modal-overlay" id="batchModal">
  <div class="modal">
    <div class="modal-header">
      <h3 class="card-title" style="margin:20px">Batch Certificate Verification</h3>
      <button class="btn-icon" onclick="closeModal('batchModal')" style="margin:15px">✕</button>
    </div>
    <form action="inc/actions.php" method="post">
      <input type="hidden" name="action" value="batch_verify_certificates">
      <input type="hidden" name="page" value="../certificates.php">
      <div class="card-body">
        <label class="form-label">Paste Certificate References (one per line)</label>
        <textarea name="refs" class="form-textarea" rows="10" placeholder="CERT-NAT-001..."></textarea>
      </div>
      <div class="card-header" style="justify-content:flex-end">
        <button type="button" class="btn btn-secondary" onclick="closeModal('batchModal')">Cancel</button>
        <button type="submit" class="btn btn-primary" style="margin-left:10px">Verify Batch</button>
      </div>
    </form>
  </div>
</div>

<!-- REVOKE MODAL -->
<div class="modal-overlay" id="revokeModal">
  <div class="modal">
    <div class="modal-header">
      <h3 class="card-title" style="margin:20px">Revoke Certificate: <span id="revokeCertRef"></span></h3>
      <button class="btn-icon" onclick="closeModal('revokeModal')" style="margin:15px">✕</button>
    </div>
    <form action="inc/actions.php" method="post">
      <input type="hidden" name="action" value="revoke_certificate">
      <input type="hidden" name="certificate_id" id="revokeCertId">
      <input type="hidden" name="page" value="../certificates.php">
      <div class="card-body">
        <div class="form-group">
          <label class="form-label">Revocation Reason</label>
          <textarea name="reason" class="form-textarea" required placeholder="Reason for revocation (e.g. Identity fraud, Farm verification failure)..."></textarea>
        </div>
      </div>
      <div class="card-header" style="justify-content:flex-end">
        <button type="button" class="btn btn-secondary" onclick="closeModal('revokeModal')">Cancel</button>
        <button type="submit" class="btn btn-danger" style="margin-left:10px">Revoke Certificate</button>
      </div>
    </form>
  </div>
</div>

<script>
function openRevokeModal(id, ref) {
    document.getElementById('revokeCertId').value = id;
    document.getElementById('revokeCertRef').textContent = ref;
    openModal('revokeModal');
}
</script>

<?php require __DIR__ . '/layout/footer.php'; ?>
