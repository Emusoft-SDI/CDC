<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/init.php';

$pageTitle = 'Registry Users';
$activeNav = 'users';
$isSuperAdmin = admin_current_user_is_super_admin($pdo);
$search = trim((string) ($_GET['search'] ?? ''));
$role = trim((string) ($_GET['role'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$limit = rx_per_page(50);
$page = max(1, (int) ($_GET['p'] ?? 1));
$offset = ($page - 1) * $limit;

$where = ['1=1'];
$params = [];
if ($search !== '') {
    $where[] = '(u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR u.role LIKE ? OR u.platform_role LIKE ?)';
    $term = '%' . $search . '%';
    array_push($params, $term, $term, $term, $term, $term);
}
if ($role !== '') {
    $where[] = '(u.role = ? OR u.platform_role = ? OR ura.role_key = ?)';
    array_push($params, $role, $role, $role);
}
if ($status !== '') {
    if ($status === 'verified') {
        $where[] = "u.email_verified_at IS NOT NULL AND COALESCE(u.account_status,'active')='active'";
    } elseif ($status === 'pending') {
        $where[] = "(u.email_verified_at IS NULL OR COALESCE(u.account_status,'active') IN ('needs_confirmation','pending','unconfirmed'))";
    } else {
        $where[] = "COALESCE(u.account_status,'active') = ?";
        $params[] = $status;
    }
}
$whereSql = implode(' AND ', $where);

$total = rx_scalar($pdo, "SELECT COUNT(DISTINCT u.id) FROM users u LEFT JOIN user_role_assignments ura ON ura.user_id=u.id AND ura.status='active' WHERE {$whereSql}", $params);
$users = rx_rows($pdo, "SELECT u.id, u.name, u.email, u.phone, u.role, u.platform_role, u.account_status, u.email_verified_at, u.created_at, GROUP_CONCAT(DISTINCT ura.role_key ORDER BY ura.role_key SEPARATOR ', ') assigned_roles FROM users u LEFT JOIN user_role_assignments ura ON ura.user_id=u.id AND ura.status='active' WHERE {$whereSql} GROUP BY u.id ORDER BY u.created_at DESC, u.id DESC LIMIT {$limit} OFFSET {$offset}", $params);

$roleOptions = rx_rows($pdo, "SELECT role_key FROM admin_role_definitions WHERE status='active' ORDER BY role_key");
if (!$roleOptions) {
    $roleOptions = [['role_key' => 'grower'], ['role_key' => 'field_agent'], ['role_key' => 'admin'], ['role_key' => 'provider'], ['role_key' => 'seller']];
}

require __DIR__ . '/layout/header.php';
?>

<div class="page-header">
  <div>
    <h1>Registry Users</h1>
    <p class="page-subtitle">Search and review platform users connected to registry operations. Root role changes remain restricted to Super Admin.</p>
  </div>
  <?php if ($isSuperAdmin): ?><a class="btn btn-primary" href="../users.php">Open Super Admin Users Console</a><?php endif; ?>
</div>

<form class="card mb-3" method="get">
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-5">
        <label class="form-label">Search</label>
        <input class="form-control" name="search" value="<?= rx_e($search) ?>" placeholder="Name, email, phone, role">
      </div>
      <div class="col-md-3">
        <label class="form-label">Role</label>
        <select class="form-select" name="role">
          <option value="">All roles</option>
          <?php foreach ($roleOptions as $option): $key = (string) ($option['role_key'] ?? ''); ?>
            <option value="<?= rx_e($key) ?>" <?= $role === $key ? 'selected' : '' ?>><?= rx_e(ucwords(str_replace('_', ' ', $key))) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">Status</label>
        <select class="form-select" name="status">
          <?php foreach (['' => 'All', 'verified' => 'Verified', 'pending' => 'Pending', 'active' => 'Active', 'suspended' => 'Suspended'] as $key => $label): ?>
            <option value="<?= rx_e($key) ?>" <?= $status === $key ? 'selected' : '' ?>><?= rx_e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2 d-flex align-items-end gap-2">
        <button class="btn btn-primary w-100">Filter</button>
      </div>
    </div>
  </div>
</form>

<div class="card">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2 class="h5 mb-0"><?= number_format($total) ?> user account(s)</h2>
      <a class="btn btn-secondary btn-sm" href="export.php?type=users">Export Users</a>
    </div>
    <div class="table-responsive">
      <table class="table table-striped align-middle">
        <thead><tr><th>User</th><th>Role</th><th>Status</th><th>Verified</th><th>Created</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach ($users as $user): ?>
            <?php
              $accountStatus = (string) ($user['account_status'] ?? 'active');
              $primaryRole = (string) (($user['platform_role'] ?? '') ?: ($user['role'] ?? 'user'));
              $roles = trim((string) ($user['assigned_roles'] ?? ''));
            ?>
            <tr>
              <td><strong><?= rx_e((string) ($user['name'] ?? 'Unnamed')) ?></strong><br><small><?= rx_e((string) ($user['email'] ?? '')) ?><?= !empty($user['phone']) ? ' / ' . rx_e((string) $user['phone']) : '' ?></small></td>
              <td><?= rx_e(ucwords(str_replace('_', ' ', $primaryRole))) ?><?= $roles !== '' ? '<br><small>' . rx_e($roles) . '</small>' : '' ?></td>
              <td><span class="status-badge <?= rx_e(rx_status_class($accountStatus)) ?>"><?= rx_e(ucwords(str_replace('_', ' ', $accountStatus))) ?></span></td>
              <td><?= !empty($user['email_verified_at']) ? rx_e((string) $user['email_verified_at']) : '<span class="muted">Pending</span>' ?></td>
              <td><?= rx_e((string) ($user['created_at'] ?? '')) ?></td>
              <td>
                <?php if ($isSuperAdmin): ?>
                  <a class="btn btn-sm btn-secondary" href="../users.php?search=<?= rx_e(urlencode((string) ($user['email'] ?? ''))) ?>">Manage</a>
                <?php else: ?>
                  <span class="muted">View only</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$users): ?><tr><td colspan="6" class="text-center muted py-4">No users match this filter.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <?= rx_pagination_links($total, $limit, $page, 'users.php') ?>
  </div>
</div>

<?php require __DIR__ . '/layout/footer.php'; ?>