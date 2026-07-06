<?php defined('NATCODEV_SUPER_ADMIN') || exit; 
$search = trim((string) ($_GET['q'] ?? ''));
$roleFilter = trim((string) ($_GET['role'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$perPage = super_admin_per_page(25);
$page = admin_current_page();
$offset = admin_pagination_offset($page, $perPage);

[$whereSql, $params] = super_admin_user_filters($search, $roleFilter, $statusFilter);
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM users {$whereSql}");
$countStmt->execute($params);
$totalUsers = (int) $countStmt->fetchColumn();

$usersStmt = $pdo->prepare("
    SELECT id, name, email, phone, role, platform_role, account_status, is_super_admin, is_agronomist, is_extensionist,
           two_factor_required, profile_verified, suspended_until, archived_at, created_at, last_login_at
    FROM users
    {$whereSql}
    ORDER BY created_at DESC, id DESC
    LIMIT {$perPage} OFFSET {$offset}
");
$usersStmt->execute($params);
$users = $usersStmt->fetchAll();
?>
<section class="panel">
  <div class="section-head">
    <div>
      <h2>User Governance</h2>
      <p>Manage users in small pages with compact rows. Open a row only when you need to edit profile, role, security, reset password, or delete access.</p>
    </div>
    <div class="actions">
      <details class="create-user-panel">
        <summary class="button">New User</summary>
        <form method="post">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="create_user">
          <h3>Create Privileged Account</h3>
          <label>Name</label>
          <input name="name" required>
          <label>Email</label>
          <input type="email" name="email" required>
          <label>Phone</label>
          <input name="phone">
          <label>Platform Role</label>
          <select name="platform_role">
            <?php foreach ($roles as $key => $label): ?>
              <option value="<?= e($key) ?>"><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
          <label>Temporary Password</label>
          <input name="password" value="<?= e(super_admin_temp_password()) ?>" required>
          <label>State or Role Scope</label>
          <input name="location" placeholder="National, State name, investor group, etc.">
          <div class="check-row compact-checks">
            <label><input type="checkbox" name="profile_verified" value="1"> Verified profile</label>
            <label><input type="checkbox" name="two_factor_required" value="1"> Require 2FA/OTP</label>
          </div>
          <button type="submit" data-busy-text="Creating account...">Create Account</button>
        </form>
      </details>
      <a class="button secondary" href="index.php">Back to Snapshot</a>
      <a class="button secondary" href="index.php?export=users">Export CSV</a>
    </div>
  </div>

  <form class="filters" method="get">
    <input type="hidden" name="view" value="users">
    <input name="q" value="<?= e($search) ?>" placeholder="Search name, email, phone">
    <select name="role">
      <option value="">All roles</option>
      <?php foreach ($roles as $key => $label): ?>
        <option value="<?= e($key) ?>" <?= $roleFilter === $key ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="status">
      <option value="">All statuses</option>
      <?php foreach ($statuses as $key => $label): ?>
        <option value="<?= e($key) ?>" <?= $statusFilter === $key ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="per_page">
      <?php foreach (super_admin_per_page_options() as $size): ?>
        <option value="<?= $size ?>" <?= $perPage === $size ? 'selected' : '' ?>><?= $size ?> rows</option>
      <?php endforeach; ?>
    </select>
    <button type="submit" data-busy-text="Filtering...">Filter</button>
  </form>

  <?= super_admin_pagination_controls($totalUsers, $page, $perPage, ['view' => 'users', 'q' => $search, 'role' => $roleFilter, 'status' => $statusFilter]) ?>

  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>User</th><th>Role</th><th>Status</th><th>Security</th><th>Review</th></tr>
      </thead>
      <tbody>
      <?php foreach ($users as $user): ?>
        <?php
          $editFormId = 'user-edit-' . (int) $user['id'];
          $platformRole = (string) ($user['platform_role'] ?: super_admin_platform_role_from_user($user));
          $roleLabel = $roles[$platformRole] ?? ucwords(str_replace('_', ' ', $platformRole));
          $status = (string) ($user['account_status'] ?: 'active');
        ?>
        <tr>
          <td>
            <form id="<?= e($editFormId) ?>" method="post"></form>
            <strong><?= e($user['name']) ?></strong>
            <small><?= e($user['email']) ?></small>
            <?php if (!empty($user['phone'])): ?><small><?= e((string) $user['phone']) ?></small><?php endif; ?>
          </td>
          <td>
            <span class="role-pill"><?= e($roleLabel) ?></span>
            <small>Auth: <?= e((string) $user['role']) ?></small>
          </td>
          <td>
            <span class="badge <?= $status === 'active' ? 'ok-badge' : 'warning' ?>"><?= e($statuses[$status] ?? $status) ?></span>
            <small>Created <?= e(date('M j, Y', strtotime((string) $user['created_at']))) ?></small>
            <?php if (!empty($user['suspended_until'])): ?><small>Suspended until <?= e(date('M j, Y', strtotime((string) $user['suspended_until']))) ?></small><?php endif; ?>
            <?php if (!empty($user['archived_at'])): ?><small>Archived <?= e(date('M j, Y', strtotime((string) $user['archived_at']))) ?></small><?php endif; ?>
          </td>
          <td>
            <?php if ((int) $user['is_super_admin'] === 1): ?><span class="badge root-badge">Super Admin</span><?php endif; ?>
            <?php if ((int) $user['profile_verified'] === 1): ?><span class="badge ok-badge">Verified</span><?php endif; ?>
            <?php if ((int) $user['two_factor_required'] === 1): ?><span class="badge muted-badge">2FA</span><?php endif; ?>
            <?php if ((int) $user['is_super_admin'] !== 1 && (int) $user['profile_verified'] !== 1 && (int) $user['two_factor_required'] !== 1): ?><small>No elevated flags</small><?php endif; ?>
          </td>
          <td>
            <details class="row-review">
              <summary>Edit</summary>
              <div class="inline-edit">
                <input form="<?= e($editFormId) ?>" type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input form="<?= e($editFormId) ?>" type="hidden" name="action" value="update_user">
                <input form="<?= e($editFormId) ?>" type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                <label>Name<input form="<?= e($editFormId) ?>" name="name" value="<?= e($user['name']) ?>" required></label>
                <label>Email<input form="<?= e($editFormId) ?>" type="email" name="email" value="<?= e($user['email']) ?>" required></label>
                <label>Phone<input form="<?= e($editFormId) ?>" name="phone" value="<?= e((string) $user['phone']) ?>"></label>
                <label>Platform Role
                  <select form="<?= e($editFormId) ?>" name="platform_role">
                    <?php foreach ($roles as $key => $label): ?>
                      <option value="<?= e($key) ?>" <?= $platformRole === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                  </select>
                </label>
                <small class="meta">Choosing Super Administrator here grants root console access. Other roles keep their normal scoped access.</small>
                <label>Status
                  <select form="<?= e($editFormId) ?>" name="account_status">
                    <?php foreach ($statuses as $key => $label): ?>
                      <option value="<?= e($key) ?>" <?= $status === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                  </select>
                </label>
                <label><input form="<?= e($editFormId) ?>" type="checkbox" name="profile_verified" value="1" <?= (int) $user['profile_verified'] === 1 ? 'checked' : '' ?>> Profile verified</label>
                <label><input form="<?= e($editFormId) ?>" type="checkbox" name="two_factor_required" value="1" <?= (int) $user['two_factor_required'] === 1 ? 'checked' : '' ?>> Require 2FA/OTP</label>
                <button form="<?= e($editFormId) ?>" type="submit" data-busy-text="Saving user...">Save Changes</button>
              </div>
              <div class="row-actions">
                <form method="post" class="mini-form">
                  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="reset_password">
                  <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                  <button type="submit" class="secondary" data-busy-text="Resetting...">Reset Password</button>
                </form>
                <form method="post" class="mini-form danger-zone">
                  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="delete_user">
                  <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                  <input name="confirm_delete" placeholder="Type DELETE">
                  <button type="submit" class="danger" data-busy-text="Archiving...">Archive User</button>
                </form>
                <?php if ($status === 'archived'): ?>
                <form method="post" class="mini-form">
                  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="restore_user">
                  <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                  <button type="submit" class="secondary" data-busy-text="Restoring...">Restore User</button>
                </form>
                <?php endif; ?>
              </div>
            </details>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$users): ?><tr><td colspan="5" class="empty">No users match this review.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

  <?= super_admin_pagination_controls($totalUsers, $page, $perPage, ['view' => 'users', 'q' => $search, 'role' => $roleFilter, 'status' => $statusFilter]) ?>
</section>
