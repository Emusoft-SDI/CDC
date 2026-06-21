<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/common.php';

$pageTitle = 'Application Queue - NATCODEV Registry';
$activeNav = 'applications';

$page = max(1, (int) ($_GET['p'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;

$total = rx_scalar($pdo, "SELECT COUNT(*) FROM applications WHERE confirmed = 0");
$apps = rx_rows($pdo, "
    SELECT a.id, a.app_ref, a.name, a.email, a.phone, a.commitments, a.created_at,
           COALESCE(ns.state_name, '') state_name,
           COALESCE(nl.lga_name, '') lga_name
    FROM applications a
    LEFT JOIN nigeria_states ns ON ns.id = a.state_id
    LEFT JOIN nigeria_lgas nl ON nl.id = a.lga_id
    WHERE a.confirmed = 0
    ORDER BY a.created_at DESC
    LIMIT $limit OFFSET $offset
");

$isSuperAdmin = admin_current_platform_role($pdo) === 'super_admin';
$deleteRequests = [];
if ($isSuperAdmin) {
    $deleteRequests = rx_rows($pdo, "
        SELECT adr.*, a.app_ref, a.name, a.email, u.name requested_by_name
        FROM application_delete_requests adr
        LEFT JOIN applications a ON a.id = adr.application_id
        LEFT JOIN users u ON u.id = adr.requested_by
        WHERE adr.status = 'pending'
        ORDER BY adr.created_at DESC
        LIMIT 20
    ");
}

require __DIR__ . '/layout/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Application Queue</h1>
    <p class="page-subtitle"><?= number_format($total) ?> applications awaiting review.</p>
  </div>
</div>

<?php if ($isSuperAdmin && $deleteRequests): ?>
  <div class="card" style="border-color:var(--danger)">
    <div class="card-header" style="background:#fff5f5"><h3 class="card-title" style="color:var(--danger)">Pending Delete Requests</h3></div>
    <div class="card-body p0">
      <table>
        <thead><tr><th>Application</th><th>Requested By</th><th>Reason</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach ($deleteRequests as $req): ?>
            <tr>
              <td><strong><?= rx_e($req['app_ref']) ?></strong><br><small><?= rx_e($req['name']) ?></small></td>
              <td><?= rx_e($req['requested_by_name']) ?></td>
              <td><?= rx_e($req['reason']) ?></td>
              <td>
                <form action="inc/actions.php" method="post" style="display:flex; gap:5px">
                  <input type="hidden" name="action" value="approve_delete">
                  <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                  <input type="hidden" name="page" value="../applications.php">
                  <button type="submit" class="btn btn-sm btn-danger">Approve</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<div class="card">
  <div class="card-body p0">
    <table>
      <thead>
        <tr>
          <th>App Ref</th>
          <th>Applicant</th>
          <th>Type</th>
          <th>Location</th>
          <th>Submitted</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($apps as $row): ?>
          <tr>
            <td><strong><?= rx_e($row['app_ref']) ?></strong></td>
            <td>
              <strong><?= rx_e($row['name']) ?></strong><br>
              <small><?= rx_e($row['email'] ?: $row['phone']) ?></small>
            </td>
            <td><?= rx_e($row['commitments'] ?: 'Individual') ?></td>
            <td><?= rx_e($row['state_name']) ?> / <?= rx_e($row['lga_name']) ?></td>
            <td><?= date('M j, Y', strtotime($row['created_at'])) ?></td>
            <td>
              <button class="btn btn-sm btn-primary" onclick="openReviewModal(<?= $row['id'] ?>, '<?= rx_e($row['name']) ?>')">Review</button>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$apps): ?><tr><td colspan="6" style="text-align:center; padding:40px">No pending applications found.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?= rx_pagination_links($total, $limit, $page, 'applications.php') ?>

<!-- REVIEW MODAL -->
<div class="modal-overlay" id="reviewModal">
  <div class="modal">
    <div class="modal-header">
      <h3 class="card-title" style="margin:20px">Review Application: <span id="reviewAppName"></span></h3>
      <button class="btn-icon" onclick="closeModal('reviewModal')" style="margin:15px">✕</button>
    </div>
    <form action="inc/actions.php" method="post">
      <input type="hidden" name="action" value="review_application">
      <input type="hidden" name="application_id" id="reviewAppId">
      <input type="hidden" name="page" value="../applications.php">
      <div class="card-body">
        <div class="form-group">
          <label class="form-label">Decision</label>
          <select name="status" class="form-select">
            <option value="under_review">Keep Under Review</option>
            <option value="approved">Approve & Confirm</option>
            <option value="rejected">Reject Application</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Internal Notes</label>
          <textarea name="notes" class="form-textarea" placeholder="Optional notes for the registry log..."></textarea>
        </div>
      </div>
      <div class="card-header" style="justify-content:flex-end">
        <button type="button" class="btn btn-secondary" onclick="closeModal('reviewModal')">Cancel</button>
        <button type="submit" class="btn btn-primary" style="margin-left:10px">Save Decision</button>
      </div>
    </form>
  </div>
</div>

<script>
function openReviewModal(id, name) {
    document.getElementById('reviewAppId').value = id;
    document.getElementById('reviewAppName').textContent = name;
    openModal('reviewModal');
}
</script>

<?php require __DIR__ . '/layout/footer.php'; ?>
