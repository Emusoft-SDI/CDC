<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/common.php';

$pageTitle = 'Growers Directory - NATCODEV Registry';
$activeNav = 'growers';

$page = max(1, (int) ($_GET['p'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;
$search = trim((string) ($_GET['search'] ?? ''));

$where = "role = 'grower'";
$params = [];
if ($search !== '') {
    $where .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR u.location LIKE ?)";
    $term = "%$search%";
    array_push($params, $term, $term, $term, $term);
}

$total = rx_scalar($pdo, "SELECT COUNT(*) FROM users u WHERE $where", $params);
$growers = rx_rows($pdo, "
    SELECT u.id, u.name, u.email, u.phone, u.location, u.created_at, u.application_id,
           COALESCE(ns.state_name, u.location) state_name,
           IF(a.confirmed = 1, 'verified', 'pending') reg_status
    FROM users u
    LEFT JOIN applications a ON a.id = u.application_id
    LEFT JOIN nigeria_states ns ON ns.id = a.state_id
    WHERE $where
    ORDER BY u.created_at DESC
    LIMIT $limit OFFSET $offset
", $params);

$allStates = rx_rows($pdo, "SELECT id, state_name FROM nigeria_states ORDER BY state_name");

require __DIR__ . '/layout/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Grower Directory</h1>
    <p class="page-subtitle"><?= number_format($total) ?> registered coconut growers found.</p>
  </div>
  <div class="header-actions">
    <button class="btn btn-primary" onclick="openModal('growerModal')">+ Register Grower</button>
  </div>
</div>

<?php if (isset($_GET['error_code'])): ?>
  <?php if ($_GET['error_code'] === 'duplicate_unconfirmed' && isset($_GET['app_id'])): ?>
    <div class="card" style="background:#fffbeb; border:1px solid #fef3c7; color:#92400e; padding:16px 20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:20px;">
      <div>
        <strong style="font-size:14px; display:block;">Duplicate Registration Found</strong>
        <span style="font-size:12px; margin-top:2px; display:block;">This email or phone number is already registered, but the registration has not yet been confirmed.</span>
      </div>
      <form action="inc/actions.php" method="post" style="margin:0;">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="resend_confirmation">
        <input type="hidden" name="id" value="<?= (int)$_GET['app_id'] ?>">
        <input type="hidden" name="page" value="../growers.php">
        <button type="submit" class="btn btn-primary btn-sm">Resend Confirmation Email</button>
      </form>
    </div>
  <?php elseif ($_GET['error_code'] === 'duplicate_confirmed' && isset($_GET['app_id'])): ?>
    <div class="card" style="background:#fee2e2; border:1px solid #fecdd3; color:#991b1b; padding:16px 20px; margin-bottom:20px;">
        <strong style="font-size:14px; display:block;">Duplicate Registration Found</strong>
        <span style="font-size:12px; margin-top:2px; display:block;">An active, confirmed grower account already exists with this email or phone number.</span>
        <div style="margin-top:10px;">
            <form action="inc/actions.php" method="post" style="display:inline;">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="edit_grower">
                <input type="hidden" name="application_id" value="<?= (int)$_GET['app_id'] ?>">
                <input type="hidden" name="page" value="../growers.php">
                <button type="submit" class="btn btn-primary btn-sm">Edit Grower</button>
            </form>
            <form action="inc/actions.php" method="post" style="display:inline; margin-left:8px;">
                <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="request_delete">
                <input type="hidden" name="application_id" value="<?= (int)$_GET['app_id'] ?>">
                <input type="hidden" name="page" value="../growers.php">
                <button type="submit" class="btn btn-danger btn-sm">Revoke Grower</button>
            </form>
        </div>
    </div>
<?php endif; ?>
<?php endif; ?>

<div class="card">
  <div class="card-header">
    <form method="get" style="display:flex; gap:10px; flex:1;">
      <input type="text" name="search" class="form-input" placeholder="Search by name, email, phone or state..." value="<?= rx_e($search) ?>" style="max-width:400px">
      <button type="submit" class="btn btn-secondary">Search</button>
      <?php if($search): ?><a href="growers.php" class="btn btn-secondary">Clear</a><?php endif; ?>
    </form>
  </div>
  <div class="card-body p0">
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Grower</th>
          <th>Location</th>
          <th>Registered</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($growers as $row): ?>
          <tr>
            <td><strong>#<?= $row['id'] ?></strong></td>
            <td>
              <div class="avatar-row">
                <div class="avatar-sm"><?= rx_user_initials($row['name']) ?></div>
                <div>
                  <strong><?= rx_e($row['name']) ?></strong><br>
                  <small><?= rx_e($row['email'] ?: $row['phone']) ?></small>
                </div>
              </div>
            </td>
            <td><?= rx_e($row['state_name'] ?: 'Unassigned') ?></td>
            <td><?= date('M j, Y', strtotime($row['created_at'])) ?></td>
            <td><span class="status-badge <?= rx_status_class($row['reg_status']) ?>"><?= ucfirst($row['reg_status']) ?></span></td>
            <td>
                <?php if ($row['reg_status'] === 'verified'): ?>
                    <form action="inc/actions.php" method="post" style="display:inline">
                        <input type="hidden" name="action" value="issue_certificate">
                        <input type="hidden" name="user_id" value="<?= $row['id'] ?>">
                        <input type="hidden" name="page" value="../growers.php">
                        <button type="submit" class="btn btn-sm btn-secondary">Issue Cert</button>
                    </form>
                <?php else: ?>
                    <form action="inc/actions.php" method="post" style="display:inline; margin-right:8px;">
                        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                        <input type="hidden" name="action" value="resend_confirmation">
                        <input type="hidden" name="id" value="<?= (int)$row['application_id'] ?>">
                        <input type="hidden" name="page" value="../growers.php">
                        <button type="submit" class="btn btn-sm btn-warning">Resend Confirmation</button>
                    </form>
                    <form action="inc/actions.php" method="post" style="display:inline;">
                        <input type="hidden" name="action" value="admin_confirm_grower">
                        <input type="hidden" name="application_id" value="<?= (int)$row['application_id'] ?>">
                        <input type="hidden" name="page" value="../growers.php">
                        <button type="submit" class="btn btn-sm btn-success">Mark Confirmed</button>
                    </form>
                <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$growers): ?><tr><td colspan="6" style="text-align:center; padding:40px">No growers found matching your search.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?= rx_pagination_links($total, $limit, $page, 'growers.php') ?>

<!-- REGISTER MODAL -->
<div class="modal-overlay" id="growerModal">
  <div class="modal">
    <div class="modal-header">
      <h3 class="card-title" style="margin:20px">Register New Grower</h3>
      <button class="btn-icon" onclick="closeModal('growerModal')" style="margin:15px">✕</button>
    </div>
    <form action="inc/actions.php" method="post">
      <input type="hidden" name="action" value="create_grower">
      <input type="hidden" name="page" value="../growers.php">
      <div class="card-body">
        <div class="form-group">
          <label class="form-label">Full Name / Business Name</label>
          <input type="text" name="name" class="form-input" required>
        </div>
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Email Address</label>
            <input type="email" name="email" class="form-input" required>
          </div>
          <div class="form-group">
            <label class="form-label">Phone Number</label>
            <input type="text" name="phone" class="form-input">
          </div>
        </div>
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Type</label>
            <select name="type" class="form-select">
              <option>Individual</option>
              <option>Group</option>
              <option>Cooperative</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">State</label>
            <select name="state" class="form-select">
              <option value="">Select State</option>
              <?php foreach($allStates as $s): ?><option value="<?= rx_e($s['state_name']) ?>"><?= rx_e($s['state_name']) ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Farm Size (ha) *</label>
          <input type="number" step="0.01" min="0.00" name="farm_size" class="form-input" required placeholder="e.g., 2.5">
        </div>
      </div>
      <div class="card-header" style="justify-content:flex-end">
        <button type="button" class="btn btn-secondary" onclick="closeModal('growerModal')">Cancel</button>
        <button type="submit" class="btn btn-primary" style="margin-left:10px">Register Grower</button>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/layout/footer.php'; ?>
