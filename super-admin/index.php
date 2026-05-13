<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/admin-layout.php';
require_once __DIR__ . '/../lib/disaster-recovery.php';

const SUPER_ADMIN_SCHEMA_VERSION = '20260513-3';

session_start();

$message = '';
$error = '';
$roles = super_admin_roles();
$statuses = super_admin_statuses();

if (isset($_GET['logout'])) {
    unset(
        $_SESSION['super_admin_authenticated'],
        $_SESSION['super_admin_user_id'],
        $_SESSION['super_admin_login_audited'],
        $_SESSION['super_admin_schema_version']
    );
    redirect_to('index.php');
}

if (empty($_SESSION['super_admin_authenticated']) && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid security token.';
    } elseif (super_admin_password_is_valid((string) ($_POST['password'] ?? ''))) {
        session_regenerate_id(true);
        $_SESSION['super_admin_authenticated'] = true;
        redirect_to('index.php');
    } else {
        $error = 'Invalid super administrator password.';
    }
}

$needsUserAuthorization = empty($_SESSION['super_admin_authenticated']) && !empty($_SESSION['user_id']);
if (empty($_SESSION['super_admin_authenticated']) && !$needsUserAuthorization) {
    super_admin_login_screen($error);
    exit;
}

$pdo = db();
if (($_SESSION['super_admin_schema_version'] ?? '') !== SUPER_ADMIN_SCHEMA_VERSION) {
    super_admin_ensure_schema($pdo);
    dr_ensure_schema($pdo);
    $_SESSION['super_admin_schema_version'] = SUPER_ADMIN_SCHEMA_VERSION;
}

if (!super_admin_is_authorized($pdo)) {
    super_admin_login_screen($error);
    exit;
}

if (empty($_SESSION['super_admin_login_audited'])) {
    super_admin_audit($pdo, 'super_admin_login', 'Super admin console login.');
    $_SESSION['super_admin_login_audited'] = true;
}

if (isset($_GET['export']) && $_GET['export'] === 'users') {
    super_admin_export_users($pdo);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') !== 'login') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid security token.';
    } else {
        try {
            $action = (string) ($_POST['action'] ?? '');
            if ($action === 'create_user') {
                super_admin_create_user($pdo, $roles);
                $message = 'Privileged user account created and onboarding email recorded/sent.';
            } elseif ($action === 'update_user') {
                super_admin_update_user($pdo, $roles, $statuses);
                $message = 'User profile, role, and access status updated.';
            } elseif ($action === 'reset_password') {
                super_admin_reset_password($pdo);
                $message = 'Temporary password generated and sent/recorded for the selected user.';
            } elseif ($action === 'delete_user') {
                super_admin_delete_user($pdo);
                $message = 'User profile archived. It can be restored from the archived status list.';
            } elseif ($action === 'restore_user') {
                super_admin_restore_user($pdo);
                $message = 'Archived user profile restored to active status.';
            } elseif ($action === 'save_controls') {
                super_admin_save_controls($pdo);
                $message = 'System announcement and security controls saved.';
            } elseif ($action === 'save_access_controls') {
                super_admin_save_access_controls($pdo, $roles);
                $message = 'Role access control matrix saved.';
            } elseif ($action === 'save_module_settings') {
                super_admin_save_module_settings($pdo);
                $message = 'Module setup and entry points saved.';
            } elseif ($action === 'save_training_onboarding') {
                super_admin_save_training_onboarding($pdo);
                $message = 'Training and onboarding policy saved.';
            } elseif ($action === 'create_announcement') {
                super_admin_create_announcement($pdo, $roles);
                $message = 'System announcement created.';
            } elseif ($action === 'toggle_announcement') {
                super_admin_toggle_announcement($pdo);
                $message = 'Announcement status updated.';
            } elseif ($action === 'save_dr_settings') {
                super_admin_save_dr_settings($pdo);
                $message = 'Disaster recovery and multisite policy saved.';
            } elseif ($action === 'add_site_node') {
                $secret = super_admin_add_site_node($pdo);
                $message = 'Site node added. Copy this sync token now: ' . $secret;
            } elseif ($action === 'update_site_node') {
                super_admin_update_site_node($pdo);
                $message = 'Site node updated.';
            } elseif ($action === 'create_backup_manifest') {
                $backup = dr_create_backup_manifest($pdo, $_SESSION['super_admin_user_id'] ?? null);
                $message = 'Backup manifest created: ' . $backup['backup_ref'] . ' at ' . $backup['path'];
            } elseif ($action === 'queue_sync_ping') {
                dr_queue_sync_event($pdo, 'health_ping', ['queued_by' => 'super_admin', 'queued_at' => date('c')], trim((string) ($_POST['target_node'] ?? '')) ?: null);
                $message = 'Health ping queued for multisite sync.';
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$view = (string) ($_GET['view'] ?? 'overview');
$allowedViews = array_keys(super_admin_views());
$view = in_array($view, $allowedViews, true) ? $view : 'overview';
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

$stats = super_admin_stats($pdo);
$roleSummary = super_admin_role_summary($pdo, $roles);
$settings = super_admin_control_settings($pdo);
$accessMatrix = super_admin_access_matrix($pdo, $roles);
$moduleSettings = super_admin_module_settings($pdo);
$trainingSettings = super_admin_training_settings($pdo);
$announcements = $pdo->query("SELECT id, title, body, audience_role, is_active, created_at FROM system_announcements ORDER BY created_at DESC LIMIT 20")->fetchAll();
$drSettings = dr_settings($pdo);
$siteNodes = $pdo->query("SELECT id, node_key, name, base_url, node_role, status, sync_enabled, last_seen_at, last_error, created_at FROM site_nodes ORDER BY created_at DESC")->fetchAll();
$backups = $pdo->query("SELECT backup_ref, backup_type, status, storage_path, file_size, checksum, started_at, completed_at FROM dr_backups ORDER BY created_at DESC LIMIT 10")->fetchAll();
$syncEvents = $pdo->query("SELECT event_uuid, direction, event_type, source_node, target_node, status, attempts, error_message, created_at, processed_at FROM sync_events ORDER BY created_at DESC LIMIT 20")->fetchAll();
$auditRows = app_table_exists($pdo, 'audit_log')
    ? $pdo->query("SELECT action, description, ip_address, created_at FROM audit_log ORDER BY created_at DESC LIMIT 60")->fetchAll()
    : [];

$pageMeta = super_admin_page_meta($view);
super_admin_page_start($pageMeta['title'], $pageMeta['description'], $view);
?>
<?php if ($message): ?><div class="notice ok"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>

<section class="stats">
  <div class="stat"><span>Total Users</span><strong><?= (int) $stats['total_users'] ?></strong></div>
  <div class="stat"><span>Privileged Profiles</span><strong><?= (int) $stats['privileged'] ?></strong></div>
  <div class="stat"><span>Super Admins</span><strong><?= (int) $stats['super_admins'] ?></strong></div>
  <div class="stat"><span>Suspended</span><strong><?= (int) $stats['suspended'] ?></strong></div>
  <div class="stat"><span>Archived</span><strong><?= (int) $stats['archived'] ?></strong></div>
</section>

<?php if ($view === 'disaster'): ?>
<section class="panel">
  <div class="section-head">
    <div>
      <h2>Disaster Recovery and Multisite</h2>
      <p>Define backup policy, register secondary sites, monitor sync events, and keep restore evidence in one Super Admin control plane.</p>
    </div>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="create_backup_manifest">
      <button type="submit" data-busy-text="Creating backup manifest...">Create Backup Manifest</button>
    </form>
  </div>
  <div class="dr-grid">
    <form method="post" class="dr-card">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="save_dr_settings">
      <h3>Recovery Policy</h3>
      <label>Site ID<input name="dr_site_id" value="<?= e($drSettings['dr_site_id']) ?>" required></label>
      <label>Site Role
        <select name="dr_site_role">
          <?php foreach (['primary' => 'Primary', 'replica' => 'Replica', 'standby' => 'Standby'] as $key => $label): ?>
            <option value="<?= e($key) ?>" <?= $drSettings['dr_site_role'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Sync Enabled
        <select name="dr_sync_enabled">
          <option value="1" <?= $drSettings['dr_sync_enabled'] === '1' ? 'selected' : '' ?>>Enabled</option>
          <option value="0" <?= $drSettings['dr_sync_enabled'] === '0' ? 'selected' : '' ?>>Disabled</option>
        </select>
      </label>
      <label>Sync Mode
        <select name="dr_sync_mode">
          <?php foreach (['manual_review' => 'Manual review', 'near_realtime' => 'Near realtime', 'scheduled_batch' => 'Scheduled batch'] as $key => $label): ?>
            <option value="<?= e($key) ?>" <?= $drSettings['dr_sync_mode'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Backup Frequency
        <select name="dr_backup_frequency">
          <?php foreach (['hourly' => 'Hourly', 'daily' => 'Daily', 'weekly' => 'Weekly'] as $key => $label): ?>
            <option value="<?= e($key) ?>" <?= $drSettings['dr_backup_frequency'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Retention Days<input type="number" name="dr_backup_retention_days" min="1" max="3650" value="<?= e($drSettings['dr_backup_retention_days']) ?>"></label>
      <label>Private Backup Path<input name="dr_backup_storage_path" value="<?= e($drSettings['dr_backup_storage_path']) ?>"></label>
      <label>Recovery Contact<input name="dr_recovery_contact" value="<?= e($drSettings['dr_recovery_contact']) ?>"></label>
      <label>Last Restore Test<input name="dr_last_restore_test_at" value="<?= e($drSettings['dr_last_restore_test_at']) ?>" placeholder="YYYY-MM-DD"></label>
      <button type="submit" data-busy-text="Saving recovery policy...">Save Recovery Policy</button>
    </form>

    <form method="post" class="dr-card">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="add_site_node">
      <h3>Add or Rotate Site Node</h3>
      <label>Node Key<input name="node_key" placeholder="lagos-replica" required></label>
      <label>Display Name<input name="name" placeholder="Lagos Standby Site" required></label>
      <label>Base URL<input name="base_url" placeholder="https://replica.example.com/CDC" required></label>
      <label>Node Role
        <select name="node_role">
          <option value="replica">Replica</option>
          <option value="standby">Standby</option>
          <option value="reporting">Reporting</option>
          <option value="primary">Primary</option>
        </select>
      </label>
      <button type="submit" data-busy-text="Saving node...">Save Node and Generate Token</button>
      <p class="meta">The sync token is shown once after save. Store it in the other site's secure environment/config.</p>
    </form>
  </div>

  <div class="dr-grid">
    <section class="dr-card">
      <h3>Registered Sites</h3>
      <div class="compact-list">
        <?php foreach ($siteNodes as $node): ?>
          <article>
            <strong><?= e($node['name']) ?></strong>
            <span><?= e($node['node_key']) ?> | <?= e($node['node_role']) ?> | <?= e($node['status']) ?></span>
            <small><?= e($node['base_url']) ?><?= $node['last_seen_at'] ? ' | Last seen ' . e(date('M j, g:i A', strtotime((string) $node['last_seen_at']))) : '' ?></small>
            <?php if ($node['last_error']): ?><small class="danger-text"><?= e($node['last_error']) ?></small><?php endif; ?>
            <form method="post" class="node-actions">
              <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="update_site_node">
              <input type="hidden" name="node_id" value="<?= (int) $node['id'] ?>">
              <select name="status"><option value="active" <?= $node['status'] === 'active' ? 'selected' : '' ?>>Active</option><option value="paused" <?= $node['status'] === 'paused' ? 'selected' : '' ?>>Paused</option><option value="disabled" <?= $node['status'] === 'disabled' ? 'selected' : '' ?>>Disabled</option></select>
              <label><input type="checkbox" name="sync_enabled" value="1" <?= (int) $node['sync_enabled'] === 1 ? 'checked' : '' ?>> Sync</label>
              <button type="submit" class="secondary" data-busy-text="Updating node...">Update</button>
            </form>
            <form method="post" class="node-actions">
              <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="queue_sync_ping">
              <input type="hidden" name="target_node" value="<?= e($node['node_key']) ?>">
              <button type="submit" class="secondary" data-busy-text="Queueing ping...">Queue Ping</button>
            </form>
          </article>
        <?php endforeach; ?>
        <?php if (!$siteNodes): ?><p class="empty">No replica or standby sites registered yet.</p><?php endif; ?>
      </div>
    </section>

    <section class="dr-card">
      <h3>Backup and Sync Evidence</h3>
      <div class="compact-list">
        <?php foreach ($backups as $backup): ?>
          <article>
            <strong><?= e($backup['backup_ref']) ?></strong>
            <span><?= e($backup['status']) ?> | <?= number_format((int) $backup['file_size']) ?> bytes</span>
            <small><?= e((string) $backup['storage_path']) ?></small>
          </article>
        <?php endforeach; ?>
        <?php if (!$backups): ?><p class="empty">No backup manifests recorded yet.</p><?php endif; ?>
      </div>
      <h3>Recent Sync Events</h3>
      <div class="compact-list">
        <?php foreach ($syncEvents as $event): ?>
          <article>
            <strong><?= e($event['event_type']) ?></strong>
            <span><?= e($event['direction']) ?> | <?= e($event['status']) ?> | <?= e($event['event_uuid']) ?></span>
            <small><?= e((string) ($event['source_node'] ?: 'local')) ?> to <?= e((string) ($event['target_node'] ?: 'all')) ?> | <?= e(date('M j, g:i A', strtotime((string) $event['created_at']))) ?></small>
            <?php if ($event['error_message']): ?><small class="danger-text"><?= e($event['error_message']) ?></small><?php endif; ?>
          </article>
        <?php endforeach; ?>
        <?php if (!$syncEvents): ?><p class="empty">No multisite sync events yet.</p><?php endif; ?>
      </div>
    </section>
  </div>
</section>
<?php endif; ?>

<?php if ($view === 'overview'): ?>
<section class="super-dashboard">
  <a class="command-card" href="index.php?view=users">
    <span>User Governance</span>
    <strong><?= (int) $stats['privileged'] ?> privileged profiles</strong>
    <small>Promote, suspend, restore, reset passwords, and control root access.</small>
  </a>
  <a class="command-card" href="index.php?view=controls">
    <span>Access & Policy</span>
    <strong><?= count(super_admin_feature_catalog()) ?> controlled features</strong>
    <small>Define what each platform role can see and do inside operations.</small>
  </a>
  <a class="command-card" href="index.php?view=disaster">
    <span>Recovery</span>
    <strong><?= count($siteNodes) ?> site nodes</strong>
    <small>Backups, standby sites, sync health, and restore evidence.</small>
  </a>
  <a class="command-card operations" href="../admin/admin.php">
    <span>Operational Handoff</span>
    <strong>Open Admin Console</strong>
    <small>Applications, verification, support, field network, and daily registry work live there.</small>
  </a>
</section>

<section class="readiness-grid">
  <article>
    <span>Permission Boundary</span>
    <strong>Enforced</strong>
    <small>Admin pages now check the Super Admin access matrix before showing menus or allowing direct URL access.</small>
  </article>
  <article>
    <span>Audit Trail</span>
    <strong><?= count($auditRows) ?> recent events</strong>
    <small>Privileged actions are recorded for review from Access & Policy.</small>
  </article>
  <article>
    <span>Account Recovery</span>
    <strong><?= (int) $stats['archived'] ?> archived</strong>
    <small>Deleted users are archived and can be restored through User Governance.</small>
  </article>
</section>

<section class="panel">
  <div class="section-head">
    <div>
      <h2>User Governance Snapshot</h2>
      <p>Quick role and account-health summary. Open the full review only when you need to edit, reset passwords, suspend, archive, or delete users.</p>
    </div>
    <div class="actions">
      <a class="button" href="index.php?view=users">Open User Governance</a>
      <a class="button secondary" href="index.php?export=users">Export CSV</a>
    </div>
  </div>
  <div class="role-summary">
    <?php foreach ($roleSummary as $roleKey => $summary): ?>
      <a href="index.php?view=users&role=<?= e($roleKey) ?>">
        <span><?= e($summary['label']) ?></span>
        <strong><?= (int) $summary['total'] ?></strong>
      </a>
    <?php endforeach; ?>
  </div>
</section>
<?php elseif ($view === 'users'): ?>
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
<?php endif; ?>

<?php if ($view === 'controls'): ?>
<section class="panel">
  <div class="section-head">
    <div>
      <h2>Module Setup and Entry Points</h2>
      <p>Define where each module lives, who owns it operationally, and how it should behave. Access Control below still decides which roles can use each module.</p>
    </div>
    <a class="button secondary" href="../admin/admin.php">Open Admin Console</a>
  </div>
  <form method="post">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="save_module_settings">
    <div class="module-grid">
      <?php foreach (super_admin_module_catalog() as $feature => $module): ?>
        <?php $moduleState = $moduleSettings[$feature] ?? []; ?>
        <article class="module-card">
          <div class="section-head compact">
            <div>
              <h3><?= e($module['label']) ?></h3>
              <p><?= e($module['purpose']) ?></p>
            </div>
            <a class="button secondary" href="<?= e($module['entry']) ?>">Open</a>
          </div>
          <input type="hidden" name="modules[<?= e($feature) ?>][feature]" value="<?= e($feature) ?>">
          <div class="settings-grid">
            <label>Operating Mode
              <select name="modules[<?= e($feature) ?>][mode]">
                <?php foreach (['active' => 'Active', 'pilot' => 'Pilot', 'setup' => 'Setup Required', 'paused' => 'Paused'] as $key => $label): ?>
                  <option value="<?= e($key) ?>" <?= ($moduleState['mode'] ?? $module['mode']) === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label>Owner
              <input name="modules[<?= e($feature) ?>][owner]" value="<?= e($moduleState['owner'] ?? $module['owner']) ?>">
            </label>
          </div>
          <label>Setup Notes
            <textarea name="modules[<?= e($feature) ?>][notes]" placeholder="<?= e($module['setup']) ?>"><?= e($moduleState['notes'] ?? $module['setup']) ?></textarea>
          </label>
          <small class="meta">Entry: <?= e($module['entry']) ?> / Applies to: <?= e($module['surface']) ?></small>
        </article>
      <?php endforeach; ?>
    </div>
    <button type="submit" data-busy-text="Saving module setup...">Save Module Setup</button>
  </form>
</section>

<section class="console-grid">
  <form class="panel" method="post">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="save_access_controls">
    <h2>Access Control Management</h2>
    <p>Universal role permissions. Admins operate inside these boundaries; Super Admin defines them.</p>
    <div class="access-matrix">
      <?php foreach ($roles as $role => $label): ?>
        <fieldset>
          <legend><?= e($label) ?></legend>
          <input type="hidden" name="access_roles[]" value="<?= e($role) ?>">
          <?php foreach (super_admin_feature_catalog() as $feature => $featureLabel): ?>
            <label><input type="checkbox" name="access[<?= e($role) ?>][]" value="<?= e($feature) ?>" <?= in_array($feature, $accessMatrix[$role] ?? [], true) ? 'checked' : '' ?>> <?= e($featureLabel) ?></label>
          <?php endforeach; ?>
        </fieldset>
      <?php endforeach; ?>
    </div>
    <button type="submit" data-busy-text="Saving access controls...">Save Access Controls</button>
  </form>

  <form class="panel" method="post">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="save_training_onboarding">
    <h2>User Training and Onboarding</h2>
    <label>Default Onboarding Message</label>
    <textarea name="onboarding_default_message"><?= e($trainingSettings['onboarding_default_message']) ?></textarea>
    <label>Training Curriculum</label>
    <textarea name="training_curriculum"><?= e($trainingSettings['training_curriculum']) ?></textarea>
    <div class="settings-grid">
      <label><span>Certification Required</span><select name="training_certification_required"><option value="1" <?= $trainingSettings['training_certification_required'] === '1' ? 'selected' : '' ?>>Required</option><option value="0" <?= $trainingSettings['training_certification_required'] === '0' ? 'selected' : '' ?>>Optional</option></select></label>
      <label><span>Paid Certification Service</span><select name="training_paid_certification_enabled"><option value="1" <?= $trainingSettings['training_paid_certification_enabled'] === '1' ? 'selected' : '' ?>>Enabled</option><option value="0" <?= $trainingSettings['training_paid_certification_enabled'] === '0' ? 'selected' : '' ?>>Disabled</option></select></label>
    </div>
    <button type="submit" data-busy-text="Saving onboarding...">Save Onboarding Policy</button>
  </form>
</section>

<section class="console-grid">
  <form class="panel" method="post">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="create_announcement">
    <h2>System Announcement Management</h2>
    <label>Title</label>
    <input name="title" maxlength="180" required>
    <label>Audience</label>
    <select name="audience_role">
      <option value="all">All users</option>
      <?php foreach ($roles as $role => $label): ?>
        <option value="<?= e($role) ?>"><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <label>Message</label>
    <textarea name="body" required></textarea>
    <label><input type="checkbox" name="is_active" value="1" checked> Active announcement</label>
    <button type="submit" data-busy-text="Publishing announcement...">Create Announcement</button>
  </form>

  <section class="panel">
    <h2>Active and Recent Announcements</h2>
    <div class="announcement-list">
      <?php foreach ($announcements as $announcement): ?>
        <article>
          <div class="section-head compact">
            <div>
              <strong><?= e($announcement['title']) ?></strong>
              <small><?= e($announcement['audience_role'] === 'all' ? 'All users' : ($roles[$announcement['audience_role']] ?? $announcement['audience_role'])) ?> | <?= e(date('M j, Y g:i A', strtotime((string) $announcement['created_at']))) ?></small>
            </div>
            <form method="post">
              <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="toggle_announcement">
              <input type="hidden" name="announcement_id" value="<?= (int) $announcement['id'] ?>">
              <button type="submit" class="<?= (int) $announcement['is_active'] === 1 ? 'secondary' : '' ?>" data-busy-text="Updating..."><?= (int) $announcement['is_active'] === 1 ? 'Deactivate' : 'Activate' ?></button>
            </form>
          </div>
          <p><?= e($announcement['body']) ?></p>
        </article>
      <?php endforeach; ?>
      <?php if (!$announcements): ?><p class="empty">No announcements created yet.</p><?php endif; ?>
    </div>
  </section>
</section>

<section class="console-grid">
  <section class="panel">
    <h2>Recent Audit Trail</h2>
    <div class="audit-list">
      <?php foreach ($auditRows as $row): ?>
        <div>
          <strong><?= e($row['action']) ?></strong>
          <span><?= e((string) $row['description']) ?></span>
          <small><?= e(date('M j, Y g:i A', strtotime((string) $row['created_at']))) ?> <?= $row['ip_address'] ? ' | ' . e((string) $row['ip_address']) : '' ?></small>
        </div>
      <?php endforeach; ?>
      <?php if (!$auditRows): ?><p class="empty">No audit activity recorded yet.</p><?php endif; ?>
    </div>
  </section>
</section>

<?php endif; ?>

<?php super_admin_page_end(); ?>

<?php
function super_admin_roles(): array
{
    return [
        'super_admin' => 'Super Administrator',
        'national_coordinator' => 'National Coordinator',
        'state_coordinator' => 'State Coordinator',
        'investor' => 'Investor',
        'admin' => 'Administrator',
        'field_agent' => 'Field Agent',
        'agronomist' => 'Agronomist',
        'agric_extensionist' => 'Agric Extensionist',
        'grower' => 'Grower',
    ];
}

function super_admin_views(): array
{
    return [
        'overview' => [
            'label' => 'Overview',
            'hint' => 'Role counts and governance snapshot',
        ],
        'users' => [
            'label' => 'User Governance',
            'hint' => 'Create, review, reset, and recover privileged access',
        ],
        'disaster' => [
            'label' => 'Disaster Recovery',
            'hint' => 'Backups, site nodes, and sync evidence',
        ],
        'controls' => [
            'label' => 'Access & Policy',
            'hint' => 'Permissions, onboarding, announcements, and audit',
        ],
    ];
}

function super_admin_nav_groups(): array
{
    return [
        'Command' => [
            [
                'label' => 'Overview',
                'hint' => 'Snapshot, role counts, and privileged access signals',
                'href' => 'index.php?view=overview',
                'view' => 'overview',
            ],
        ],
        'People & Access' => [
            [
                'label' => 'User Governance',
                'hint' => 'Accounts, roles, password resets, and recovery',
                'href' => 'index.php?view=users',
                'view' => 'users',
            ],
        ],
        'Governance' => [
            [
                'label' => 'Access & Policy',
                'hint' => 'Permissions, onboarding, announcements, and audit',
                'href' => 'index.php?view=controls',
                'view' => 'controls',
            ],
        ],
        'Recovery & Insights' => [
            [
                'label' => 'Recovery',
                'hint' => 'Backups, site nodes, sync events, and evidence',
                'href' => 'index.php?view=disaster',
                'view' => 'disaster',
            ],
        ],
    ];
}

function super_admin_nav_group_is_active(array $items, string $activeView): bool
{
    foreach ($items as $item) {
        if (($item['view'] ?? '') === $activeView) {
            return true;
        }
    }

    return false;
}

function super_admin_page_meta(string $view): array
{
    return match ($view) {
        'users' => [
            'title' => 'User Governance',
            'description' => 'Manage privileged accounts, role assignments, access status, password resets, and recovery actions.',
        ],
        'disaster' => [
            'title' => 'Recovery',
            'description' => 'Manage backups, site nodes, sync events, and restore evidence.',
        ],
        'controls' => [
            'title' => 'Access & Policy',
            'description' => 'Define feature permissions, onboarding policy, announcements, and audit visibility.',
        ],
        default => [
            'title' => 'Overview',
            'description' => 'Monitor account governance, privileged access, and system health at a glance.',
        ],
    };
}

function super_admin_per_page_options(): array
{
    return [10, 25, 50, 100];
}

function super_admin_per_page(int $default = 25): int
{
    $perPage = (int) ($_GET['per_page'] ?? $default);
    return in_array($perPage, super_admin_per_page_options(), true) ? $perPage : $default;
}

function super_admin_pagination_controls(int $total, int $page, int $perPage, array $extra = []): string
{
    $pages = max(1, (int) ceil($total / max(1, $perPage)));
    $page = min(max(1, $page), $pages);
    $base = array_merge($_GET, $extra);
    unset($base['page'], $base['per_page']);
    $from = $total === 0 ? 0 : (($page - 1) * $perPage) + 1;
    $to = min($total, $page * $perPage);

    $url = static function (int $targetPage, int $targetPerPage) use ($base): string {
        return '?' . http_build_query($base + ['page' => $targetPage, 'per_page' => $targetPerPage]);
    };

    ob_start();
    ?>
    <form class="pagination" method="get">
      <?php foreach ($base as $key => $value): ?>
        <?php if (is_scalar($value)): ?><input type="hidden" name="<?= e((string) $key) ?>" value="<?= e((string) $value) ?>"><?php endif; ?>
      <?php endforeach; ?>
      <div class="meta">Showing <?= (int) $from ?>-<?= (int) $to ?> of <?= (int) $total ?></div>
      <div class="pagination-links">
        <a class="button secondary" href="<?= e($url(max(1, $page - 1), $perPage)) ?>" aria-disabled="<?= $page <= 1 ? 'true' : 'false' ?>">Previous</a>
        <span class="meta">Page <?= (int) $page ?> of <?= (int) $pages ?></span>
        <a class="button secondary" href="<?= e($url(min($pages, $page + 1), $perPage)) ?>" aria-disabled="<?= $page >= $pages ? 'true' : 'false' ?>">Next</a>
      </div>
      <label class="pagination-size">Rows
        <select name="per_page" onchange="this.form.page.value='1'; this.form.submit()">
          <?php foreach (super_admin_per_page_options() as $size): ?>
            <option value="<?= $size ?>" <?= $perPage === $size ? 'selected' : '' ?>><?= $size ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <input type="hidden" name="page" value="<?= (int) $page ?>">
    </form>
    <?php
    return (string) ob_get_clean();
}

function super_admin_statuses(): array
{
    return [
        'active' => 'Active',
        'inactive' => 'Inactive',
        'suspended' => 'Suspended',
        'deactivated' => 'Deactivated',
        'archived' => 'Archived',
    ];
}

function super_admin_ensure_schema(PDO $pdo): void
{
    app_ensure_core_schema($pdo);
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            key_name VARCHAR(120) NOT NULL UNIQUE,
            value TEXT NULL,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'settings');
    admin_ensure_settings_unique($pdo);

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS audit_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            action VARCHAR(120) NOT NULL,
            description TEXT NULL,
            ip_address VARCHAR(45) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'audit_log');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS system_announcements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(180) NOT NULL,
            body TEXT NOT NULL,
            audience_role VARCHAR(60) NOT NULL DEFAULT 'all',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_by INT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_system_announcements_active (is_active, audience_role, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'system_announcements');

    app_add_column_if_missing($pdo, 'users', 'platform_role', "VARCHAR(60) NULL");
    app_add_column_if_missing($pdo, 'users', 'account_status', "VARCHAR(30) NOT NULL DEFAULT 'active'");
    app_add_column_if_missing($pdo, 'users', 'is_super_admin', "TINYINT(1) NOT NULL DEFAULT 0");
    app_add_column_if_missing($pdo, 'users', 'two_factor_required', "TINYINT(1) NOT NULL DEFAULT 0");
    app_add_column_if_missing($pdo, 'users', 'profile_verified', "TINYINT(1) NOT NULL DEFAULT 0");
    app_add_column_if_missing($pdo, 'users', 'suspended_until', "DATETIME NULL");
    app_add_column_if_missing($pdo, 'users', 'deactivated_at', "DATETIME NULL");
    app_add_column_if_missing($pdo, 'users', 'archived_at', "DATETIME NULL");
    app_add_column_if_missing($pdo, 'users', 'last_login_at', "DATETIME NULL");
    app_add_column_if_missing($pdo, 'users', 'admin_notes', "TEXT NULL");
    app_add_column_if_missing($pdo, 'users', 'location', "VARCHAR(255) NULL");

    foreach (super_admin_control_settings() as $key => $default) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO settings (key_name, value) VALUES (?, ?)");
        $stmt->execute([$key, $default]);
    }
    foreach (super_admin_training_settings() as $key => $default) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO settings (key_name, value) VALUES (?, ?)");
        $stmt->execute([$key, $default]);
    }
    $accessCatalogVersion = ADMIN_ACCESS_CATALOG_VERSION;
    $currentCatalogVersion = admin_setting($pdo, 'access_matrix_catalog_version', '');
    foreach (super_admin_roles() as $role => $_label) {
        $key = 'access_matrix_' . $role;
        $defaultAccess = super_admin_default_access($role);
        if ($currentCatalogVersion !== $accessCatalogVersion) {
            $existing = admin_setting($pdo, $key, '');
            $mergedAccess = $existing === ''
                ? $defaultAccess
                : array_values(array_unique(array_merge(array_filter(array_map('trim', explode(',', $existing))), $defaultAccess)));
            $stmt = $pdo->prepare("
                INSERT INTO settings (key_name, value) VALUES (?, ?)
                ON DUPLICATE KEY UPDATE value = VALUES(value)
            ");
            $stmt->execute([$key, implode(',', $mergedAccess)]);
        } else {
            $stmt = $pdo->prepare("INSERT IGNORE INTO settings (key_name, value) VALUES (?, ?)");
            $stmt->execute([$key, implode(',', $defaultAccess)]);
        }
    }
    $stmt = $pdo->prepare("
        INSERT INTO settings (key_name, value) VALUES ('access_matrix_catalog_version', ?)
        ON DUPLICATE KEY UPDATE value = VALUES(value)
    ");
    $stmt->execute([$accessCatalogVersion]);
}

function super_admin_control_settings(?PDO $pdo = null): array
{
    $defaults = [
        'dashboard_system_notice' => '',
        'security_require_profile_otp' => '1',
        'security_require_2fa_admins' => '0',
        'access_investor_dashboard_enabled' => '0',
        'access_user_export_enabled' => '1',
    ];

    if (!$pdo instanceof PDO) {
        return $defaults;
    }

    foreach ($defaults as $key => $default) {
        $defaults[$key] = admin_setting($pdo, $key, $default);
    }
    return $defaults;
}

function super_admin_feature_catalog(): array
{
    return function_exists('admin_feature_catalog') ? admin_feature_catalog() : [];
}

function super_admin_module_catalog(): array
{
    $labels = super_admin_feature_catalog();
    $modules = [
        'dashboard' => ['entry' => '../dashboard/index.php', 'surface' => 'Grower dashboard', 'owner' => 'Operations', 'purpose' => 'Main user home for account, farm, support, and service summaries.', 'setup' => 'Keep dashboard cards aligned with enabled modules and active user journeys.'],
        'state_dashboard' => ['entry' => '../admin/state-dashboard.php', 'surface' => 'State Coordinator', 'owner' => 'State Operations', 'purpose' => 'State-level farmers, accreditation, resources, field network, communication, finance, and reporting.', 'setup' => 'Assign states to coordinators and confirm state-scope data quality.'],
        'national_dashboard' => ['entry' => '../admin/national-dashboard.php', 'surface' => 'National Coordinator', 'owner' => 'National Operations', 'purpose' => 'National comparison, state performance, investor engagement, compliance, and strategic decision support.', 'setup' => 'Define national KPIs, state ranking rules, and reporting cadence.'],
        'governance' => ['entry' => '../admin/governance.php', 'surface' => 'Super Admin/Admin', 'owner' => 'Governance', 'purpose' => 'Policies for access, password, DR, retention, notifications, compliance, and production readiness.', 'setup' => 'Approve policies and maintain review cadence.'],
        'production_readiness' => ['entry' => '../admin/production-readiness.php', 'surface' => 'Admin/Super Admin', 'owner' => 'Technical Operations', 'purpose' => 'Authenticated production checks for live mail, SMS, WhatsApp, payment, backup, roles, and environment readiness.', 'setup' => 'Run before launch and after any infrastructure or credential change.'],
        'profile' => ['entry' => '../dashboard/profile.php', 'surface' => 'Grower dashboard', 'owner' => 'User Operations', 'purpose' => 'Account settings, security, password, notifications, and farm profile data.', 'setup' => 'Review OTP policy, required profile fields, and notification channels.'],
        'applications' => ['entry' => '../admin/admin.php', 'surface' => 'Admin console', 'owner' => 'Registry Operations', 'purpose' => 'Application review, confirmation, exports, and grower onboarding.', 'setup' => 'Define review statuses, confirmation workflow, and export policy.'],
        'documents' => ['entry' => '../admin/document-verification.php', 'surface' => 'Admin console', 'owner' => 'Verification Team', 'purpose' => 'Identity and farm document verification.', 'setup' => 'Maintain document requirements and review SLA.'],
        'certificates' => ['entry' => '../dashboard/documents.php', 'surface' => 'Dashboard/Admin', 'owner' => 'Certification Team', 'purpose' => 'Certificate readiness, verification, and downloads.', 'setup' => 'Define certificate eligibility and verification evidence.'],
        'field_network' => ['entry' => '../admin/agent-map.php', 'surface' => 'Admin console', 'owner' => 'Field Operations', 'purpose' => 'Field agent tracking, assignments, and field network operations.', 'setup' => 'Confirm agent onboarding, GPS tracking policy, and assignment coverage.'],
        'field_management' => ['entry' => '../admin/fields-management.php', 'surface' => 'Admin/Field/Grower', 'owner' => 'Field Operations', 'purpose' => 'Farm maps, farm verification, field tasks, GPS evidence, and weather snapshots.', 'setup' => 'Set verification tolerance, task routing, and farm approval workflow.'],
        'agronomy_advisory' => ['entry' => '../admin/agronomy.php', 'surface' => 'Admin/Agronomist/Grower', 'owner' => 'Agronomy Team', 'purpose' => 'Agronomy cases, soil and crop records, recommendations, and advisory templates.', 'setup' => 'Define case categories, recommendation templates, follow-up policy, and escalation rules.'],
        'support' => ['entry' => '../admin/support.php', 'surface' => 'Admin/Grower', 'owner' => 'Support Team', 'purpose' => 'Support tickets, grower issues, and operational replies.', 'setup' => 'Define categories, priorities, response expectations, and notification routing.'],
        'farm_health' => ['entry' => '../dashboard/farm-health.php', 'surface' => 'Grower dashboard', 'owner' => 'Agronomy Team', 'purpose' => 'Farm health requests, weather, imagery, and assessment entry point.', 'setup' => 'Define when farm health requests become support, field, or agronomy cases.'],
        'marketplace' => ['entry' => '../admin/marketplace.php', 'surface' => 'Admin/Grower', 'owner' => 'Marketplace Team', 'purpose' => 'Inputs, services, and marketplace offers.', 'setup' => 'Define seller policy, active listings, and purchase workflow.'],
        'providers' => ['entry' => '../admin/providers.php', 'surface' => 'Admin/National/State', 'owner' => 'Marketplace Team', 'purpose' => 'Agricultural input and service provider registration, verification, products, and services.', 'setup' => 'Define provider accreditation, certifications, coverage, and listing rules.'],
        'resource_allocation' => ['entry' => '../admin/resource-allocation.php', 'surface' => 'National/State', 'owner' => 'Program Operations', 'purpose' => 'Input inventory, farmer allocation, distribution status, and effectiveness tracking.', 'setup' => 'Define inventory units, resource categories, and beneficiary reporting.'],
        'communications' => ['entry' => '../admin/communications.php', 'surface' => 'National/State/Admin', 'owner' => 'Communications', 'purpose' => 'Statewide and national broadcasts, weather alerts, training announcements, and stakeholder messaging.', 'setup' => 'Define channel routing, approval rules, and priority alert policy.'],
        'wallet' => ['entry' => '../dashboard/wallet.php', 'surface' => 'Grower dashboard', 'owner' => 'Finance', 'purpose' => 'Wallet balance and transaction history.', 'setup' => 'Review payment provider settings and transaction audit.'],
        'training' => ['entry' => '../dashboard/webinars.php', 'surface' => 'Dashboard/Admin', 'owner' => 'Training Team', 'purpose' => 'Training sessions, webinars, onboarding, and certification learning.', 'setup' => 'Maintain curriculum, certification requirements, and paid/free training policy.'],
        'healthcare' => ['entry' => '../dashboard/healthcare.php', 'surface' => 'Grower dashboard', 'owner' => 'Services Team', 'purpose' => 'Health-related grower services when enabled.', 'setup' => 'Define partner/service availability before public rollout.'],
        'pricing' => ['entry' => '../dashboard/pricing.php', 'surface' => 'Grower dashboard', 'owner' => 'Commercial Team', 'purpose' => 'Plans, pricing, upgrades, and premium service visibility.', 'setup' => 'Confirm available plans, payment rules, and messaging.'],
        'resources' => ['entry' => '../admin/resources.php', 'surface' => 'Admin/Field', 'owner' => 'Content Team', 'purpose' => 'Offline resources, guides, and extension content.', 'setup' => 'Keep resources categorized and offline-ready for field agents.'],
        'templates' => ['entry' => '../admin/templates.php', 'surface' => 'Admin console', 'owner' => 'Communications', 'purpose' => 'Notification and communication templates.', 'setup' => 'Review template content, channels, and approved tone.'],
        'notifications' => ['entry' => '../admin/notifications.php', 'surface' => 'Admin console', 'owner' => 'Communications', 'purpose' => 'Notification logs and delivery visibility.', 'setup' => 'Confirm mail/SMS/WhatsApp transports are production-ready.'],
        'reports' => ['entry' => '../admin/reports.php', 'surface' => 'Admin console', 'owner' => 'Operations', 'purpose' => 'Agent and operational reports.', 'setup' => 'Define reporting cadence and required indicators.'],
        'analytics' => ['entry' => '../admin/analytics.php', 'surface' => 'Admin console', 'owner' => 'Data Team', 'purpose' => 'Registry, application, and operational analytics.', 'setup' => 'Review metric definitions and data quality assumptions.'],
        'monitoring' => ['entry' => '../admin/monitoring.php', 'surface' => 'Admin console', 'owner' => 'Technical Operations', 'purpose' => 'System health and integration readiness checks.', 'setup' => 'Track payment, mail, SMS, and system dependencies.'],
        'user_management' => ['entry' => '../admin/users.php', 'surface' => 'Admin console', 'owner' => 'User Operations', 'purpose' => 'Standard user and staff account management.', 'setup' => 'Use Super Admin User Governance for privileged root access.'],
        'imports' => ['entry' => '../admin/import-users.php', 'surface' => 'Admin console', 'owner' => 'Data Operations', 'purpose' => 'Bulk import, engagement, and legacy grower onboarding.', 'setup' => 'Validate CSV structure, confirmation messaging, and duplicate handling.'],
        'settings' => ['entry' => '../admin/settings.php', 'surface' => 'Admin console', 'owner' => 'Technical Operations', 'purpose' => 'Operational settings for integrations and platform behavior.', 'setup' => 'Keep environment-sensitive secrets out of browser-visible pages.'],
        'audit' => ['entry' => 'index.php?view=controls', 'surface' => 'Super Admin', 'owner' => 'Governance', 'purpose' => 'Privileged activity trail and governance evidence.', 'setup' => 'Review audit records regularly and investigate privileged changes.'],
        'integrations' => ['entry' => '../admin/monitoring.php', 'surface' => 'Admin console', 'owner' => 'Technical Operations', 'purpose' => 'External systems such as mail, SMS, WhatsApp, payment, maps, and weather.', 'setup' => 'Confirm provider credentials, transport modes, and failure logs.'],
    ];

    $ordered = [];
    foreach ($labels as $feature => $label) {
        $ordered[$feature] = array_merge([
            'label' => $label,
            'entry' => 'index.php?view=controls',
            'surface' => 'Platform',
            'owner' => 'Super Admin',
            'purpose' => 'Platform module.',
            'setup' => 'Define module owner, entry point, and operating rules.',
            'mode' => 'active',
        ], $modules[$feature] ?? [], ['label' => $label]);
    }

    return $ordered;
}

function super_admin_module_settings(PDO $pdo): array
{
    $settings = [];
    foreach (super_admin_module_catalog() as $feature => $module) {
        $settings[$feature] = [
            'mode' => admin_setting($pdo, 'module_' . $feature . '_mode', (string) $module['mode']),
            'owner' => admin_setting($pdo, 'module_' . $feature . '_owner', (string) $module['owner']),
            'notes' => admin_setting($pdo, 'module_' . $feature . '_notes', (string) $module['setup']),
        ];
    }
    return $settings;
}

function super_admin_default_access(string $role): array
{
    return function_exists('admin_default_access') ? admin_default_access($role) : [];
}

function super_admin_access_matrix(PDO $pdo, array $roles): array
{
    $matrix = [];
    foreach ($roles as $role => $_label) {
        $value = admin_setting($pdo, 'access_matrix_' . $role, implode(',', super_admin_default_access($role)));
        $matrix[$role] = array_values(array_filter(array_map('trim', explode(',', $value))));
    }
    return $matrix;
}

function super_admin_training_settings(?PDO $pdo = null): array
{
    $defaults = [
        'onboarding_default_message' => 'Welcome to NATCODEV. Please complete your profile, verify your contact details, and review your assigned onboarding materials.',
        'training_curriculum' => 'Platform orientation, data integrity, grower engagement, field reporting, privacy, support workflow, certificate verification.',
        'training_certification_required' => '1',
        'training_paid_certification_enabled' => '1',
    ];

    if (!$pdo instanceof PDO) {
        return $defaults;
    }

    foreach ($defaults as $key => $default) {
        $defaults[$key] = admin_setting($pdo, $key, $default);
    }
    return $defaults;
}

function super_admin_password_is_valid(string $password): bool
{
    $hash = app_env('SUPER_ADMIN_PASSWORD_HASH');
    if ($hash) {
        return password_verify($password, $hash);
    }

    $plain = app_env('SUPER_ADMIN_PASSWORD', app_is_production() ? '' : app_env('ADMIN_PASSWORD', ''));
    return $plain !== null && $plain !== '' && hash_equals($plain, $password);
}

function super_admin_is_authorized(PDO $pdo): bool
{
    if (!empty($_SESSION['super_admin_authenticated'])) {
        return true;
    }

    $userId = (int) ($_SESSION['user_id'] ?? 0);
    if ($userId <= 0 || !app_column_exists($pdo, 'users', 'is_super_admin')) {
        return false;
    }

    $stmt = $pdo->prepare("SELECT is_super_admin, account_status FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if ($user && (int) $user['is_super_admin'] === 1 && (string) ($user['account_status'] ?? 'active') === 'active') {
        $_SESSION['super_admin_user_id'] = $userId;
        return true;
    }

    return false;
}

function super_admin_auth_role(string $platformRole): string
{
    if ($platformRole === 'investor') {
        return 'investor';
    }
    if ($platformRole === 'grower') {
        return 'grower';
    }
    if (in_array($platformRole, ['field_agent', 'agronomist', 'agric_extensionist'], true)) {
        return 'field_agent';
    }

    return 'admin';
}

function super_admin_platform_role_from_user(array $user): string
{
    if ((int) ($user['is_super_admin'] ?? 0) === 1) {
        return 'super_admin';
    }
    if (!empty($user['platform_role'])) {
        return (string) $user['platform_role'];
    }
    if (($user['role'] ?? '') === 'field_agent' && (int) ($user['is_agronomist'] ?? 0) === 1) {
        return 'agronomist';
    }
    if (($user['role'] ?? '') === 'field_agent' && (int) ($user['is_extensionist'] ?? 0) === 1) {
        return 'agric_extensionist';
    }

    return (string) ($user['role'] ?? 'grower');
}

function super_admin_temp_password(): string
{
    return 'NAT-' . strtoupper(bin2hex(random_bytes(3))) . '-' . random_int(100, 999);
}

function super_admin_create_user(PDO $pdo, array $roles): void
{
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $platformRole = (string) ($_POST['platform_role'] ?? 'admin');
    $password = trim((string) ($_POST['password'] ?? ''));
    $location = trim((string) ($_POST['location'] ?? ''));

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        throw new RuntimeException('Name, valid email, and temporary password are required.');
    }
    if (!isset($roles[$platformRole])) {
        throw new RuntimeException('Invalid platform role.');
    }

    $stmt = $pdo->prepare("
        INSERT INTO users
            (email, password, name, phone, location, role, platform_role, account_status, is_super_admin, is_agronomist, is_extensionist, two_factor_required, profile_verified)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'active', ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $email,
        password_hash($password, PASSWORD_DEFAULT),
        $name,
        $phone !== '' ? $phone : null,
        $location !== '' ? $location : null,
        super_admin_auth_role($platformRole),
        $platformRole,
        $platformRole === 'super_admin' ? 1 : 0,
        $platformRole === 'agronomist' ? 1 : 0,
        $platformRole === 'agric_extensionist' ? 1 : 0,
        isset($_POST['two_factor_required']) ? 1 : 0,
        isset($_POST['profile_verified']) ? 1 : 0,
    ]);

    $loginUrl = app_base_url() . '/dashboard/login.php';
    app_send_mail($email, 'NATCODEV account created', "Hello {$name},\n\nYour NATCODEV {$roles[$platformRole]} account is ready.\n\nLogin: {$loginUrl}\nUsername: {$email}\nTemporary password: {$password}\n\nPlease sign in and update your password/profile immediately.");
    super_admin_audit($pdo, 'user_created', "Created {$roles[$platformRole]} account for {$email}.");
}

function super_admin_update_user(PDO $pdo, array $roles, array $statuses): void
{
    $userId = (int) ($_POST['user_id'] ?? 0);
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $platformRole = (string) ($_POST['platform_role'] ?? 'admin');
    $status = (string) ($_POST['account_status'] ?? 'active');

    if ($userId <= 0 || $name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('A valid user, name, and email are required.');
    }
    if (!isset($roles[$platformRole]) || !isset($statuses[$status])) {
        throw new RuntimeException('Invalid role or status.');
    }

    $suspendedUntil = $status === 'suspended' ? date('Y-m-d H:i:s', strtotime('+30 days')) : null;
    $deactivatedAt = $status === 'deactivated' ? date('Y-m-d H:i:s') : null;
    $archivedAt = $status === 'archived' ? date('Y-m-d H:i:s') : null;

    $stmt = $pdo->prepare("
        UPDATE users
        SET name = ?, email = ?, phone = ?, role = ?, platform_role = ?, account_status = ?,
            is_super_admin = ?, is_agronomist = ?, is_extensionist = ?, two_factor_required = ?, profile_verified = ?,
            suspended_until = ?, deactivated_at = ?, archived_at = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $name,
        $email,
        $phone !== '' ? $phone : null,
        super_admin_auth_role($platformRole),
        $platformRole,
        $status,
        $platformRole === 'super_admin' ? 1 : 0,
        $platformRole === 'agronomist' ? 1 : 0,
        $platformRole === 'agric_extensionist' ? 1 : 0,
        isset($_POST['two_factor_required']) ? 1 : 0,
        isset($_POST['profile_verified']) ? 1 : 0,
        $suspendedUntil,
        $deactivatedAt,
        $archivedAt,
        $userId,
    ]);
    super_admin_audit($pdo, 'user_updated', "Updated profile/access for {$email}.");
}

function super_admin_reset_password(PDO $pdo): void
{
    $userId = (int) ($_POST['user_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) {
        throw new RuntimeException('User not found.');
    }

    $password = super_admin_temp_password();
    $update = $pdo->prepare("UPDATE users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $update->execute([password_hash($password, PASSWORD_DEFAULT), $userId]);
    app_send_mail((string) $user['email'], 'NATCODEV password reset', "Hello {$user['name']},\n\nA temporary NATCODEV password has been issued by Super Administration.\n\nTemporary password: {$password}\nLogin: " . app_base_url() . "/dashboard/login.php\n\nPlease sign in and change it immediately.");
    super_admin_audit($pdo, 'password_reset', 'Password reset initiated for ' . $user['email'] . '.');
}

function super_admin_delete_user(PDO $pdo): void
{
    $userId = (int) ($_POST['user_id'] ?? 0);
    if ((string) ($_POST['confirm_delete'] ?? '') !== 'DELETE') {
        throw new RuntimeException('Type DELETE before archiving a user profile.');
    }
    if ($userId <= 0 || $userId === (int) ($_SESSION['user_id'] ?? 0)) {
        throw new RuntimeException('This user profile cannot be archived from the active session.');
    }

    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $email = (string) $stmt->fetchColumn();
    if ($email === '') {
        throw new RuntimeException('User not found.');
    }

    $pdo->prepare("
        UPDATE users
        SET account_status = 'archived',
            archived_at = COALESCE(archived_at, CURRENT_TIMESTAMP),
            deactivated_at = COALESCE(deactivated_at, CURRENT_TIMESTAMP),
            suspended_until = NULL,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ")->execute([$userId]);
    super_admin_audit($pdo, 'user_archived', "Archived user profile {$email}.");
}

function super_admin_restore_user(PDO $pdo): void
{
    $userId = (int) ($_POST['user_id'] ?? 0);
    if ($userId <= 0) {
        throw new RuntimeException('User not found.');
    }

    $stmt = $pdo->prepare("SELECT email, account_status FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) {
        throw new RuntimeException('User not found.');
    }
    if ((string) ($user['account_status'] ?? '') !== 'archived') {
        throw new RuntimeException('Only archived users can be restored.');
    }

    $pdo->prepare("
        UPDATE users
        SET account_status = 'active',
            archived_at = NULL,
            deactivated_at = NULL,
            suspended_until = NULL,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ")->execute([$userId]);
    super_admin_audit($pdo, 'user_restored', 'Restored archived user profile ' . $user['email'] . '.');
}

function super_admin_save_controls(PDO $pdo): void
{
    $allowed = array_keys(super_admin_control_settings());
    $stmt = $pdo->prepare("
        INSERT INTO settings (key_name, value) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE value = VALUES(value)
    ");
    foreach ($allowed as $key) {
        $stmt->execute([$key, trim((string) ($_POST[$key] ?? ''))]);
    }
    super_admin_audit($pdo, 'system_controls_updated', 'Updated system announcement/security/access controls.');
}

function super_admin_save_access_controls(PDO $pdo, array $roles): void
{
    $features = array_keys(super_admin_feature_catalog());
    $submitted = $_POST['access'] ?? [];
    $submittedRoles = $_POST['access_roles'] ?? [];
    if (!is_array($submitted)) {
        $submitted = [];
    }
    if (!is_array($submittedRoles)) {
        $submittedRoles = [];
    }
    $submittedRoles = array_values(array_intersect(array_keys($roles), array_map('strval', $submittedRoles)));
    if (!$submittedRoles) {
        $submittedRoles = array_keys($roles);
    }

    $stmt = $pdo->prepare("
        INSERT INTO settings (key_name, value) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE value = VALUES(value)
    ");

    foreach ($submittedRoles as $role) {
        $selected = $submitted[$role] ?? [];
        if (!is_array($selected)) {
            $selected = [];
        }
        $selected = array_values(array_intersect($features, array_map('strval', $selected)));
        $stmt->execute(['access_matrix_' . $role, implode(',', $selected)]);
    }

    $stmt->execute(['access_matrix_catalog_version', ADMIN_ACCESS_CATALOG_VERSION]);
    super_admin_audit($pdo, 'access_controls_updated', 'Updated role feature access matrix.');
}

function super_admin_save_module_settings(PDO $pdo): void
{
    $catalog = super_admin_module_catalog();
    $submitted = $_POST['modules'] ?? [];
    if (!is_array($submitted)) {
        $submitted = [];
    }

    $stmt = $pdo->prepare("
        INSERT INTO settings (key_name, value) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE value = VALUES(value)
    ");

    foreach ($catalog as $feature => $module) {
        $data = is_array($submitted[$feature] ?? null) ? $submitted[$feature] : [];
        $mode = in_array((string) ($data['mode'] ?? $module['mode']), ['active', 'pilot', 'setup', 'paused'], true)
            ? (string) ($data['mode'] ?? $module['mode'])
            : (string) $module['mode'];
        $owner = trim((string) ($data['owner'] ?? $module['owner']));
        $notes = trim((string) ($data['notes'] ?? $module['setup']));

        $stmt->execute(['module_' . $feature . '_mode', $mode]);
        $stmt->execute(['module_' . $feature . '_owner', $owner !== '' ? $owner : (string) $module['owner']]);
        $stmt->execute(['module_' . $feature . '_notes', $notes !== '' ? $notes : (string) $module['setup']]);
    }

    super_admin_audit($pdo, 'module_settings_updated', 'Updated module setup, owners, modes, and operating notes.');
}

function super_admin_save_training_onboarding(PDO $pdo): void
{
    $allowed = array_keys(super_admin_training_settings());
    $stmt = $pdo->prepare("
        INSERT INTO settings (key_name, value) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE value = VALUES(value)
    ");
    foreach ($allowed as $key) {
        $stmt->execute([$key, trim((string) ($_POST[$key] ?? ''))]);
    }
    super_admin_audit($pdo, 'training_onboarding_updated', 'Updated training and onboarding governance settings.');
}

function super_admin_create_announcement(PDO $pdo, array $roles): void
{
    $title = trim((string) ($_POST['title'] ?? ''));
    $body = trim((string) ($_POST['body'] ?? ''));
    $audience = trim((string) ($_POST['audience_role'] ?? 'all'));
    $validAudiences = array_merge(['all'], array_keys($roles));

    if ($title === '' || $body === '') {
        throw new RuntimeException('Announcement title and message are required.');
    }
    if (!in_array($audience, $validAudiences, true)) {
        throw new RuntimeException('Invalid announcement audience.');
    }

    $stmt = $pdo->prepare("
        INSERT INTO system_announcements (title, body, audience_role, is_active, created_by)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$title, $body, $audience, isset($_POST['is_active']) ? 1 : 0, $_SESSION['super_admin_user_id'] ?? null]);
    super_admin_audit($pdo, 'announcement_created', "Created announcement: {$title}.");
}

function super_admin_toggle_announcement(PDO $pdo): void
{
    $id = (int) ($_POST['announcement_id'] ?? 0);
    if ($id <= 0) {
        throw new RuntimeException('Announcement not found.');
    }
    $pdo->prepare("UPDATE system_announcements SET is_active = 1 - is_active WHERE id = ?")->execute([$id]);
    super_admin_audit($pdo, 'announcement_toggled', 'Toggled announcement #' . $id . '.');
}

function super_admin_save_dr_settings(PDO $pdo): void
{
    $allowed = array_keys(dr_default_settings());
    foreach ($allowed as $key) {
        if ($key === 'dr_site_id') {
            $value = preg_replace('/[^a-zA-Z0-9_.-]/', '', (string) ($_POST[$key] ?? ''));
        } elseif ($key === 'dr_backup_retention_days') {
            $value = (string) max(1, min(3650, (int) ($_POST[$key] ?? 30)));
        } else {
            $value = trim((string) ($_POST[$key] ?? ''));
        }
        dr_save_setting($pdo, $key, $value);
    }
    super_admin_audit($pdo, 'dr_settings_updated', 'Updated disaster recovery and multisite settings.');
}

function super_admin_add_site_node(PDO $pdo): string
{
    $nodeKey = preg_replace('/[^a-zA-Z0-9_.-]/', '', (string) ($_POST['node_key'] ?? ''));
    $name = trim((string) ($_POST['name'] ?? ''));
    $baseUrl = rtrim(trim((string) ($_POST['base_url'] ?? '')), '/');
    $role = in_array((string) ($_POST['node_role'] ?? 'replica'), ['primary', 'replica', 'standby', 'reporting'], true)
        ? (string) $_POST['node_role']
        : 'replica';

    if ($nodeKey === '' || $name === '' || !filter_var($baseUrl, FILTER_VALIDATE_URL)) {
        throw new RuntimeException('Node key, name, and valid base URL are required.');
    }

    $secret = dr_generate_shared_secret();
    $stmt = $pdo->prepare("
        INSERT INTO site_nodes (node_key, name, base_url, node_role, status, sync_enabled, shared_secret_hash)
        VALUES (?, ?, ?, ?, 'active', 1, ?)
        ON DUPLICATE KEY UPDATE
            name = VALUES(name),
            base_url = VALUES(base_url),
            node_role = VALUES(node_role),
            status = 'active',
            sync_enabled = 1,
            shared_secret_hash = VALUES(shared_secret_hash)
    ");
    $stmt->execute([$nodeKey, $name, $baseUrl, $role, password_hash($secret, PASSWORD_DEFAULT)]);
    super_admin_audit($pdo, 'site_node_saved', "Added or rotated sync token for site node {$nodeKey}.");
    return $secret;
}

function super_admin_update_site_node(PDO $pdo): void
{
    $id = (int) ($_POST['node_id'] ?? 0);
    $status = in_array((string) ($_POST['status'] ?? 'active'), ['active', 'paused', 'disabled'], true)
        ? (string) $_POST['status']
        : 'active';
    $syncEnabled = isset($_POST['sync_enabled']) ? 1 : 0;
    if ($id <= 0) {
        throw new RuntimeException('Site node not found.');
    }
    $pdo->prepare("UPDATE site_nodes SET status = ?, sync_enabled = ? WHERE id = ?")->execute([$status, $syncEnabled, $id]);
    super_admin_audit($pdo, 'site_node_updated', 'Updated site node #' . $id . '.');
}

function super_admin_user_filters(string $search, string $roleFilter, string $statusFilter): array
{
    $where = [];
    $params = [];
    if ($search !== '') {
        $where[] = '(name LIKE ? OR email LIKE ? OR phone LIKE ?)';
        $needle = '%' . $search . '%';
        array_push($params, $needle, $needle, $needle);
    }
    if ($roleFilter !== '') {
        if ($roleFilter === 'super_admin') {
            $where[] = 'is_super_admin = 1';
        } elseif (in_array($roleFilter, ['grower', 'field_agent', 'admin', 'investor'], true)) {
            $where[] = "(platform_role = ? OR ((platform_role IS NULL OR platform_role = '') AND role = ?))";
            array_push($params, $roleFilter, $roleFilter);
        } else {
            $where[] = 'platform_role = ?';
            $params[] = $roleFilter;
        }
    }
    if ($statusFilter !== '') {
        $where[] = 'account_status = ?';
        $params[] = $statusFilter;
    }

    return [$where ? 'WHERE ' . implode(' AND ', $where) : '', $params];
}

function super_admin_stats(PDO $pdo): array
{
    $total = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $privileged = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' OR is_super_admin = 1 OR platform_role IN ('super_admin','national_coordinator','state_coordinator','investor','admin')")->fetchColumn();
    $super = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE is_super_admin = 1")->fetchColumn();
    $suspended = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE account_status = 'suspended'")->fetchColumn();
    $archived = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE account_status = 'archived'")->fetchColumn();

    return ['total_users' => $total, 'privileged' => $privileged, 'super_admins' => $super, 'suspended' => $suspended, 'archived' => $archived];
}

function super_admin_role_summary(PDO $pdo, array $roles): array
{
    $summary = [];
    foreach ($roles as $role => $label) {
        $summary[$role] = ['label' => $label, 'total' => 0];
    }

    $rows = $pdo->query("
        SELECT role, platform_role, is_super_admin, is_agronomist, is_extensionist, COUNT(*) AS total
        FROM users
        GROUP BY role, platform_role, is_super_admin, is_agronomist, is_extensionist
    ")->fetchAll();

    foreach ($rows as $row) {
        $key = super_admin_platform_role_from_user($row);
        if (!isset($summary[$key])) {
            $summary[$key] = ['label' => ucwords(str_replace('_', ' ', $key)), 'total' => 0];
        }
        $summary[$key]['total'] += (int) $row['total'];
    }

    uasort($summary, static fn (array $a, array $b): int => $b['total'] <=> $a['total']);
    return $summary;
}

function super_admin_audit(PDO $pdo, string $action, string $description): void
{
    if (!app_table_exists($pdo, 'audit_log')) {
        return;
    }
    $stmt = $pdo->prepare("INSERT INTO audit_log (action, description, ip_address) VALUES (?, ?, ?)");
    $stmt->execute([$action, $description, $_SERVER['REMOTE_ADDR'] ?? null]);
}

function super_admin_export_users(PDO $pdo): void
{
    if (admin_setting($pdo, 'access_user_export_enabled', '1') !== '1') {
        http_response_code(403);
        echo 'User export is disabled.';
        exit;
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="natcodev-users-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['id', 'name', 'email', 'phone', 'role', 'platform_role', 'account_status', 'is_super_admin', 'profile_verified', 'two_factor_required', 'created_at']);
    $rows = $pdo->query("SELECT id, name, email, phone, role, platform_role, account_status, is_super_admin, profile_verified, two_factor_required, created_at FROM users ORDER BY id");
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

function super_admin_login_screen(string $error): void
{
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Super Administrator - NATCODEV</title>
  <style>
    body{margin:0;min-height:100vh;display:grid;place-items:center;background:#eef5f1;color:#1f2937;font-family:"Segoe UI",Tahoma,sans-serif}
    .box{width:min(460px,calc(100vw - 32px));background:#fff;border:1px solid #d8e2dc;border-radius:8px;box-shadow:0 18px 38px rgba(16,24,40,.14);padding:28px}
    img{width:64px;height:64px;border-radius:50%;object-fit:contain;border:1px solid #d8e2dc}
    h1{margin:14px 0 6px;color:#1a5276} p{color:#667085;line-height:1.55}
    label{display:block;font-weight:800;margin:14px 0 6px} input{width:100%;box-sizing:border-box;padding:12px;border:1px solid #d8e2dc;border-radius:6px}
    button{margin-top:16px;width:100%;border:0;border-radius:6px;background:#1f8a55;color:#fff;padding:12px 14px;font-weight:850;cursor:pointer}
    .notice{padding:12px;border-radius:6px;background:#fff3f3;color:#a32020;border:1px solid #ffd2d2}
    a{color:#166b41;font-weight:800;text-decoration:none}
  </style>
</head>
<body>
  <form class="box" method="post">
    <img src="<?= e(app_primary_logo_url()) ?>" alt="NATCODEV">
    <h1>Super Administrator</h1>
    <p>Privileged access for account governance, security controls, audit review, and system-wide configuration.</p>
    <?php if ($error): ?><div class="notice"><?= e($error) ?></div><?php endif; ?>
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="login">
    <label>Super Admin Password</label>
    <input type="password" name="password" required autofocus>
    <button type="submit">Enter Secure Console</button>
    <p><a href="../admin/admin.php">Return to admin</a></p>
  </form>
</body>
</html>
    <?php
}

function super_admin_page_start(string $title, string $description = '', string $activeView = 'overview'): void
{
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($title) ?> - NATCODEV</title>
  <style>
    :root{--primary:#1a5276;--green:#1f8a55;--green-dark:#166b41;--ink:#1f2937;--muted:#667085;--line:#d8e2dc;--bg:#f5f8f6;--panel:#fff;--danger:#a32020;--shadow:0 14px 34px rgba(16,24,40,.08)}
    *{box-sizing:border-box} body{margin:0;background:var(--bg);color:var(--ink);font-family:"Segoe UI",Tahoma,sans-serif}
    a{color:var(--green-dark);font-weight:800;text-decoration:none}.super-header{position:sticky;top:0;z-index:10;background:#fff;border-bottom:1px solid rgba(16,24,40,.08);box-shadow:0 8px 24px rgba(16,24,40,.06)}
    .bar{max-width:1320px;margin:0 auto;padding:12px 22px;display:flex;align-items:center;justify-content:space-between;gap:18px}.brand{display:flex;align-items:center;gap:11px;color:var(--primary);font-weight:900;white-space:nowrap}.brand img{width:46px;height:46px;border-radius:50%;object-fit:contain;border:1px solid var(--line)}
    .super-nav{display:flex;align-items:center;justify-content:center;gap:8px;flex:1}.super-nav details{position:relative}.super-nav summary{list-style:none;cursor:pointer;border:1px solid transparent;border-radius:7px;padding:9px 12px;color:var(--ink);font-weight:850}.super-nav summary::-webkit-details-marker{display:none}.super-nav details[open] summary,.super-nav details.active summary,.super-nav summary:hover{background:#eef7f1;border-color:#cfe6d8;color:var(--green-dark)}.super-menu{position:absolute;top:calc(100% + 10px);left:0;z-index:40;display:grid;gap:6px;width:300px;padding:9px;background:#fff;border:1px solid rgba(16,24,40,.12);border-radius:8px;box-shadow:0 20px 42px rgba(16,24,40,.16)}.super-menu a{display:block;padding:10px 11px;border-radius:7px;color:var(--ink);font-weight:850}.super-menu a:hover,.super-menu a.active{background:#f1faf5;color:var(--green-dark)}.super-menu small{display:block;margin-top:3px;color:var(--muted);font-weight:650;line-height:1.35}.header-actions{display:flex;align-items:center;gap:10px;white-space:nowrap}
    main{max-width:1320px;margin:0 auto;padding:22px 22px 42px}.hero{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;margin-bottom:14px;padding-bottom:12px;border-bottom:1px solid rgba(16,24,40,.08)}.hero h1{margin:0;color:var(--primary);font-size:clamp(1.55rem,2.4vw,2.15rem);line-height:1.08}.hero p{margin:6px 0 0;color:var(--muted);line-height:1.5;max-width:780px}.hero-kicker{color:var(--green-dark);font-size:.75rem;font-weight:900;letter-spacing:.12em;text-transform:uppercase;margin-bottom:5px}
    .panel,.stat,table{background:var(--panel);border:1px solid rgba(16,24,40,.08);border-radius:8px;box-shadow:var(--shadow)}.panel,.stat{padding:18px}.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:14px;margin:18px 0}.stat span{color:var(--muted)}.stat strong{display:block;margin-top:8px;color:var(--primary);font-size:2rem;line-height:1}.super-dashboard{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin:18px 0}.command-card{display:block;min-height:162px;padding:17px;border:1px solid rgba(16,24,40,.1);border-radius:8px;background:#fff;color:var(--ink);box-shadow:var(--shadow)}.command-card:hover{text-decoration:none;border-color:#b7dac5;background:#f8fcfa}.command-card span,.readiness-grid span{display:block;color:var(--green-dark);font-size:.78rem;font-weight:900;text-transform:uppercase;letter-spacing:.08em}.command-card strong{display:block;margin:12px 0 8px;color:var(--primary);font-size:1.15rem;line-height:1.22}.command-card small,.readiness-grid small{display:block;color:var(--muted);line-height:1.45}.command-card.operations{background:#f6fafc;border-color:#cfe0ea}.readiness-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin:18px 0}.readiness-grid article{padding:15px;background:#fff;border:1px solid rgba(16,24,40,.08);border-left:4px solid var(--green);border-radius:8px;box-shadow:var(--shadow)}.readiness-grid strong{display:block;margin:8px 0;color:var(--primary)}
    .console-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px;margin:18px 0}.section-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px}.section-head.compact{align-items:center}.actions,.filters,.check-row{display:flex;align-items:center;gap:10px;flex-wrap:wrap}.filters{margin:14px 0}.filters input{min-width:240px;flex:1}.settings-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.module-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(310px,1fr));gap:14px;margin:16px 0}.module-card{border:1px solid var(--line);border-radius:8px;background:#fbfdfb;padding:14px}.module-card h3{margin:0 0 6px;color:var(--primary)}.module-card p{margin:0 0 10px;color:var(--muted);line-height:1.45}.module-card textarea{min-height:84px}
    label{display:block;font-weight:800;margin:10px 0 6px} input,select,textarea{padding:11px 12px;border:1px solid var(--line);border-radius:6px;font:inherit;max-width:100%} input:not([type=checkbox]),select,textarea{width:100%} textarea{min-height:110px}.check-row label{font-weight:700}
    button,.button{display:inline-flex;align-items:center;justify-content:center;gap:8px;background:var(--green);color:#fff;border:0;border-radius:6px;padding:11px 14px;font-weight:850;cursor:pointer;text-decoration:none;box-shadow:0 10px 24px rgba(31,138,85,.18)}button:hover,.button:hover{background:var(--green-dark);color:#fff}.secondary{background:#eef7f1!important;color:var(--green-dark)!important;border:1px solid var(--line)!important;box-shadow:none!important}.danger{background:var(--danger)!important;color:#fff!important}.create-user-panel{position:relative}.create-user-panel summary{list-style:none}.create-user-panel summary::-webkit-details-marker{display:none}.create-user-panel form{position:absolute;right:0;top:calc(100% + 10px);z-index:30;width:min(430px,calc(100vw - 44px));padding:16px;background:#fff;border:1px solid rgba(16,24,40,.12);border-radius:8px;box-shadow:0 18px 38px rgba(16,24,40,.16)}.create-user-panel h3{margin:0 0 8px;color:var(--primary)}.compact-checks{align-items:flex-start}.compact-checks label{margin:4px 0}
    .notice{padding:13px 15px;border-radius:8px;margin:16px 0;border:1px solid transparent}.notice.ok{background:#eaf8f0;color:#0f6b3c;border-color:#bfe8cf}.notice.error{background:#fff3f3;color:var(--danger);border-color:#ffd2d2}.badge{display:inline-flex;margin-top:8px;border-radius:999px;padding:5px 9px;font-size:.78rem;font-weight:850}.warning{background:#fff7df;color:#8a5a00}.muted-badge{background:#eef2f6;color:#475467}.ok-badge{background:#eaf8f0;color:#0f6b3c}.root-badge{background:#eef4ff;color:#174ea6}.role-pill{display:inline-flex;align-items:center;border:1px solid #cfe6d8;background:#f1faf5;color:var(--green-dark);border-radius:999px;padding:6px 10px;font-weight:900;font-size:.82rem}
    .table-wrap{overflow:auto} table{width:100%;border-collapse:collapse} th,td{padding:11px;border-bottom:1px solid #edf1ea;text-align:left;vertical-align:top} th{background:#eef6e9;color:#243b1d}td small{display:block;margin-top:4px}.inline-edit{display:grid;gap:8px;min-width:260px}.mini-form{margin-top:8px}.danger-zone{display:grid;gap:7px}.row-review summary{cursor:pointer;color:var(--green-dark);font-weight:900}.row-review[open]{min-width:300px}.row-actions{border-top:1px solid #edf1ea;margin-top:12px;padding-top:10px}.pagination{margin:14px 0;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:12px;background:#fff;border:1px solid rgba(16,24,40,.08);border-radius:8px}.pagination-links{display:flex;gap:10px;align-items:center}.meta,small{color:var(--muted)}
    .dr-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin-top:14px}.dr-card{border:1px solid #edf1ea;border-radius:8px;padding:14px;background:#fbfdfb}.dr-card h3{margin:0 0 10px;color:var(--primary)}.compact-list{display:grid;gap:10px;max-height:360px;overflow:auto}.compact-list article{border:1px solid var(--line);border-radius:7px;background:#fff;padding:10px}.compact-list span,.compact-list small{display:block;margin-top:4px;color:var(--muted)}.danger-text{color:var(--danger)!important}.node-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:8px}.node-actions select{width:auto;min-width:110px}.node-actions label{margin:0;font-weight:700}
    .role-summary{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;margin-top:16px}.role-summary a{display:block;padding:14px;border:1px solid var(--line);border-radius:8px;background:#f8fbf9;color:var(--ink)}.role-summary a:hover{text-decoration:none;border-color:#b7dac5;background:#f1faf5}.role-summary span{display:block;color:var(--muted);font-weight:800}.role-summary strong{display:block;margin-top:8px;color:var(--primary);font-size:1.65rem;line-height:1}
    fieldset{border:1px solid var(--line);border-radius:8px;padding:12px;margin:0}legend{font-weight:900;color:var(--primary);padding:0 6px}.access-matrix{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;max-height:560px;overflow:auto;padding-right:4px}.access-matrix label{font-weight:650;margin:7px 0}.announcement-list,.audit-list{display:grid;gap:10px;max-height:390px;overflow:auto}.announcement-list article,.audit-list div{padding:11px;border:1px solid #edf1ea;border-radius:7px}.announcement-list p{margin:8px 0 0;color:var(--muted);line-height:1.5}.audit-list span,.audit-list small,.announcement-list small{display:block;margin-top:4px}
    @media(max-width:1100px){.super-dashboard{grid-template-columns:repeat(2,minmax(0,1fr))}.readiness-grid{grid-template-columns:1fr}}
    @media(max-width:900px){.console-grid,.dr-grid{grid-template-columns:1fr}.hero,.section-head,.bar{flex-direction:column;align-items:stretch}.super-nav{justify-content:flex-start;overflow-x:auto;padding-bottom:4px}.super-nav details{position:static}.super-menu{left:22px;right:22px;width:auto}.settings-grid,.access-matrix{grid-template-columns:1fr}}
    @media(max-width:620px){.super-dashboard{grid-template-columns:1fr}}
  </style>
</head>
<body>
  <header class="super-header">
    <div class="bar">
      <a class="brand" href="index.php"><img src="<?= e(app_primary_logo_url()) ?>" alt="NATCODEV"><span>NATCODEV Super Admin</span></a>
      <nav class="super-nav" aria-label="Super Admin menus">
        <?php foreach (super_admin_nav_groups() as $groupLabel => $items): ?>
          <details class="<?= super_admin_nav_group_is_active($items, $activeView) ? 'active' : '' ?>">
            <summary><?= e($groupLabel) ?></summary>
            <div class="super-menu">
              <?php foreach ($items as $item): ?>
                <a class="<?= (($item['view'] ?? '') === $activeView) ? 'active' : '' ?>" href="<?= e($item['href']) ?>">
                  <?= e($item['label']) ?>
                  <small><?= e($item['hint']) ?></small>
                </a>
              <?php endforeach; ?>
            </div>
          </details>
        <?php endforeach; ?>
      </nav>
      <nav class="header-actions"><a href="../admin/admin.php">Admin Console</a><a href="index.php?logout=1">Logout</a></nav>
    </div>
  </header>
  <main>
    <section class="hero">
      <div>
        <div class="hero-kicker">Super Admin</div>
        <h1><?= e($title) ?></h1>
        <p><?= e($description !== '' ? $description : 'Privileged governance for user roles, security access, onboarding, announcements, audit trails, exports, and system level controls.') ?></p>
      </div>
    </section>
    <?php
}

function super_admin_page_end(): void
{
    ?>
  </main>
  <script>
    document.querySelectorAll('.super-nav details').forEach((menu) => {
      menu.addEventListener('toggle', () => {
        if (!menu.open) return;
        document.querySelectorAll('.super-nav details[open]').forEach((other) => {
          if (other !== menu) other.open = false;
        });
      });
    });

    document.addEventListener('click', (event) => {
      if (event.target.closest('.super-nav')) return;
      document.querySelectorAll('.super-nav details[open]').forEach((menu) => {
        menu.open = false;
      });
    });
  </script>
</body>
</html>
    <?php
}
