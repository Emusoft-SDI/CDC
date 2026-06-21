<?php
declare(strict_types=1);

// We need a super-admin specific auth check, or adapt the existing one.
// Let's create a _super_auth.php for this.

require_once __DIR__ . '/_super_auth.php';
require_once __DIR__ . '/../lib/admin-layout.php';
require_once __DIR__ . '/../lib/disaster-recovery.php';
require_once __DIR__ . '/../lib/marketplace.php';

const SUPER_ADMIN_SCHEMA_VERSION = '20260530-training-1';

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
    marketplace_ensure_schema($pdo);
    dr_ensure_schema($pdo);
    $_SESSION['super_admin_schema_version'] = SUPER_ADMIN_SCHEMA_VERSION;
}
marketplace_ensure_schema($pdo);

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
            } elseif ($action === 'assign_user_role') {
                super_admin_assign_user_role($pdo, $roles);
                $message = 'Additional user role assigned without changing the base profile.';
            } elseif ($action === 'revoke_user_role') {
                super_admin_revoke_user_role($pdo);
                $message = 'Assigned user role revoked.';
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
                super_admin_save_module_settings($pdo, $roles);
                $message = 'Module availability, RBAC grants, setup, and entry points saved.';
            } elseif ($action === 'save_training_onboarding') {
                super_admin_save_training_onboarding($pdo);
                $message = 'Training and onboarding policy saved.';
            } elseif ($action === 'save_training_course') {
                super_admin_save_training_course($pdo, $roles);
                $message = 'Training course saved.';
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
            } elseif ($action === 'review_admin_action_request') {
                admin_review_action_request($pdo, (int) ($_POST['request_id'] ?? 0), (string) ($_POST['decision'] ?? ''), trim((string) ($_POST['review_note'] ?? '')));
                $message = 'Delete request reviewed.';
            } elseif ($action === 'review_application_delete_request') {
                super_admin_review_application_delete_request($pdo);
                $message = 'Application delete request reviewed.';
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
$userRoleAssignments = [];
if ($users && app_table_exists($pdo, 'user_role_assignments')) {
    $userIds = array_map(static fn (array $user): int => (int) $user['id'], $users);
    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    $assignmentStmt = $pdo->prepare("
        SELECT *
        FROM user_role_assignments
        WHERE user_id IN ({$placeholders}) AND status = 'active'
        ORDER BY assigned_at DESC, id DESC
    ");
    $assignmentStmt->execute($userIds);
    foreach ($assignmentStmt->fetchAll() as $assignment) {
        $userRoleAssignments[(int) $assignment['user_id']][] = $assignment;
    }
}

$stats = super_admin_stats($pdo);
$marketplaceStats = $view === 'marketplace' ? super_admin_marketplace_stats($pdo) : [];
$roleSummary = super_admin_role_summary($pdo, $roles);
$settings = super_admin_control_settings($pdo);
$accessMatrix = super_admin_access_matrix($pdo, $roles);
$moduleSettings = super_admin_module_settings($pdo);
$trainingSettings = super_admin_training_settings($pdo);
$trainingCourses = super_admin_training_courses($pdo);
$trainingStats = super_admin_training_stats($pdo);
$announcements = $pdo->query("SELECT id, title, body, audience_role, is_active, created_at FROM system_announcements ORDER BY created_at DESC LIMIT 20")->fetchAll();
$drSettings = dr_settings($pdo);
$siteNodes = $pdo->query("SELECT id, node_key, name, base_url, node_role, status, sync_enabled, last_seen_at, last_error, created_at FROM site_nodes ORDER BY created_at DESC")->fetchAll();
$backups = $pdo->query("SELECT backup_ref, backup_type, status, storage_path, file_size, checksum, started_at, completed_at FROM dr_backups ORDER BY created_at DESC LIMIT 10")->fetchAll();
$syncEvents = $pdo->query("SELECT event_uuid, direction, event_type, source_node, target_node, status, attempts, error_message, created_at, processed_at FROM sync_events ORDER BY created_at DESC LIMIT 20")->fetchAll();
$auditRows = app_table_exists($pdo, 'audit_log')
    ? $pdo->query("SELECT action, description, ip_address, created_at FROM audit_log ORDER BY created_at DESC LIMIT 60")->fetchAll()
    : [];
$deleteApprovalRows = super_admin_delete_approval_rows($pdo);

$governanceRoleKey = 'seller';
$governanceModuleKey = 'marketplace';
$governanceModuleCatalog = super_admin_module_catalog();
$governanceEnabledModules = count(array_filter($moduleSettings, static fn(array $module): bool => ($module['enabled'] ?? '1') === '1'));
$governancePolicyCount = app_table_exists($pdo, 'platform_governance_policies') ? (int) $pdo->query("SELECT COUNT(*) FROM platform_governance_policies WHERE status IN ('approved','active')")->fetchColumn() : 0;
$governanceModuleRows = array_slice($governanceModuleCatalog, 0, 12, true);
$governancePermissions = [
    ['Products', 'View Products', 'view', 'All States'],
    ['Products', 'Create Product', 'create', 'Own Store'],
    ['Products', 'Edit Product', 'edit', 'Own Store'],
    ['Products', 'Delete Product', 'delete', 'Own Store'],
    ['Orders', 'View Orders', 'view', 'All States'],
    ['Orders', 'Process Orders', 'edit', 'Own Store'],
    ['Orders', 'Cancel Orders', 'delete', 'Own Store'],
    ['Reports', 'Download Reports', 'export', 'Own Store'],
];
$governanceSettingsPreview = [
    'Platform Name' => 'NATCODEV Platform',
    'Default Timezone' => 'UTC+01:00 West Africa Time',
    'User Export' => ($settings['access_user_export_enabled'] ?? '1') === '1' ? 'Enabled' : 'Disabled',
    'Admin MFA Policy' => ($settings['security_require_2fa_admins'] ?? '0') === '1' ? 'Required' : 'Optional',
    'Profile OTP' => ($settings['security_require_profile_otp'] ?? '1') === '1' ? 'Required' : 'Optional',
];
$governanceHealth = [
    ['System Health', 'Healthy', 'ok'],
    ['Web Server', 'Operational', 'ok'],
    ['Database', 'Operational', 'ok'],
    ['Cache', 'Operational', 'ok'],
    ['Storage', is_writable(dirname(__DIR__) . '/uploads') ? 'Operational' : 'Review', is_writable(dirname(__DIR__) . '/uploads') ? 'ok' : 'warn'],
    ['Queue Workers', 'Operational', 'ok'],
];
$governanceIntegrations = [
    ['Payment Gateway', function_exists('monnify_is_configured') && monnify_is_configured() ? 'Connected' : 'Config Pending'],
    ['SMS Gateway', app_env('SENDCHAMP_API_KEY') ? 'Connected' : 'Config Pending'],
    ['Email Service', app_env('MAIL_TRANSPORT', 'log') === 'log' ? 'Logging' : 'Connected'],
    ['Cloud Storage', app_env('AWS_ACCESS_KEY_ID') ? 'Connected' : 'Local'],
    ['Map Service', 'Connected'],
];

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
  <a class="command-card" href="index.php?view=governance">
    <span>Governance</span>
    <strong><?= count(super_admin_feature_catalog()) ?> controlled features</strong>
    <small>Access control, modules, announcements, audit evidence, and operating policy.</small>
  </a>
  <a class="command-card" href="index.php?view=approvals">
    <span>Delete Approvals</span>
    <strong><?= count($deleteApprovalRows) ?> pending request(s)</strong>
    <small>All admin delete requests are held here until a Super Admin approves or rejects them.</small>
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
<?php elseif ($view === 'approvals'): ?>
<section class="panel">
  <div class="section-head">
    <div>
      <h2>Delete Approval Queue</h2>
      <p>Admins can request deletion, but the data is not removed until Super Admin approves it here. Reject keeps the record intact and closes the request.</p>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Request</th><th>Target</th><th>Requested By</th><th>Reason</th><th>Decision</th></tr></thead>
      <tbody>
        <?php foreach ($deleteApprovalRows as $request): ?>
          <tr>
            <td><strong><?= e((string) $request['source_label']) ?> #<?= (int) $request['id'] ?></strong><small><?= e((string) $request['created_at']) ?></small></td>
            <td><?= e((string) $request['target_label']) ?><small><?= e((string) $request['target_type']) ?></small></td>
            <td><?= e((string) ($request['requested_by_name'] ?: 'System/Admin Session')) ?><small><?= e((string) ($request['requested_by_email'] ?? '')) ?></small></td>
            <td><?= e((string) ($request['reason'] ?: 'No reason supplied.')) ?></td>
            <td>
              <form method="post" class="inline-edit">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="<?= $request['source'] === 'application' ? 'review_application_delete_request' : 'review_admin_action_request' ?>">
                <input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>">
                <input name="review_note" placeholder="Optional review note">
                <div class="actions">
                  <button class="danger" name="decision" value="approve" onclick="return confirm('Approve this delete request?')">Approve Delete</button>
                  <button class="secondary" name="decision" value="reject">Reject</button>
                </div>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$deleteApprovalRows): ?><tr><td colspan="5">No delete requests are pending approval.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php elseif ($view === 'marketplace'): ?>
<section class="market-admin-shell">
  <aside class="market-side">
    <strong>Marketplace</strong>
    <a class="active" href="index.php?view=marketplace">Dashboard</a>
    <a href="../admin/marketplace.php">Catalog Review</a>
    <a href="../market/index.php">Store Front</a>
    <a href="../market/stores.php">Merchants</a>
    <a href="../market/seller-central.php">Seller Central</a>
    <a href="../admin/providers.php">Providers</a>
    <a href="../admin/support.php">Support Desk</a>
  </aside>

  <section class="market-main">
    <div class="market-kpis">
      <a class="market-kpi coral" href="index.php?view=users">
        <span>Customers</span>
        <strong><?= (int) ($marketplaceStats['customers'] ?? 0) ?></strong>
        <small>+<?= (int) ($marketplaceStats['new_customers_30d'] ?? 0) ?> customers in 30 days</small>
      </a>
      <a class="market-kpi blue" href="../admin/marketplace.php">
        <span>Merchants</span>
        <strong><?= (int) ($marketplaceStats['merchants'] ?? 0) ?></strong>
        <small>+<?= (int) ($marketplaceStats['new_merchants_30d'] ?? 0) ?> merchants in 30 days</small>
      </a>
      <a class="market-kpi purple" href="../admin/marketplace.php#orders">
        <span>Orders</span>
        <strong><?= (int) ($marketplaceStats['orders'] ?? 0) ?></strong>
        <small><?= (int) ($marketplaceStats['new_orders_30d'] ?? 0) ?> orders in 30 days</small>
      </a>
      <a class="market-kpi teal" href="../admin/marketplace.php#orders">
        <span>Today's Total</span>
        <strong><?= marketplace_money((float) ($marketplaceStats['today_total'] ?? 0)) ?></strong>
        <small><?= (int) ($marketplaceStats['today_orders'] ?? 0) ?> sales today</small>
      </a>
    </div>

    <div class="market-alerts">
      <a class="market-alert orange" href="../admin/marketplace.php">
        <strong>Pending Verifications</strong>
        <span><?= (int) ($marketplaceStats['pending_sellers'] ?? 0) ?></span>
        <small>Seller verification and review actions</small>
      </a>
      <a class="market-alert cyan" href="../admin/marketplace.php">
        <strong>Pending Approvals</strong>
        <span><?= (int) ($marketplaceStats['pending_listings'] ?? 0) ?></span>
        <small>Listing approval and catalog actions</small>
      </a>
      <a class="market-alert red" href="../admin/support.php?category=marketplace">
        <strong>Appealed Disputes</strong>
        <span><?= (int) ($marketplaceStats['disputes'] ?? 0) ?></span>
        <small><?= (int) ($marketplaceStats['new_disputes_30d'] ?? 0) ?> increase in 30 days</small>
      </a>
    </div>

    <section class="market-panel">
      <div class="section-head compact">
        <div>
          <h2>Sales Graph</h2>
          <p>Seven-day marketplace order volume and value trend.</p>
        </div>
        <div class="actions">
          <a class="button secondary" href="../admin/marketplace.php">Admin Marketplace</a>
          <a class="button secondary" href="../market/index.php">Store Front</a>
        </div>
      </div>
      <div class="market-graph">
        <?php foreach (($marketplaceStats['sales_days'] ?? []) as $day): ?>
          <?php $height = max(3, min(100, (int) ($day['height'] ?? 3))); ?>
          <div class="market-bar" style="--h:<?= $height ?>%;">
            <span><?= marketplace_money((float) $day['total']) ?></span>
            <i></i>
            <small><?= e((string) $day['label']) ?></small>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="market-grid">
      <article class="market-panel">
        <h2>Pending Sellers</h2>
        <div class="market-list">
          <?php foreach (($marketplaceStats['pending_seller_rows'] ?? []) as $seller): ?>
            <a href="../admin/marketplace.php">
              <strong><?= e((string) $seller['store_name']) ?></strong>
              <span><?= e(marketplace_status_label((string) $seller['seller_type'])) ?> / <?= e((string) $seller['created_at']) ?></span>
            </a>
          <?php endforeach; ?>
          <?php if (empty($marketplaceStats['pending_seller_rows'])): ?><p class="empty">No sellers waiting for review.</p><?php endif; ?>
        </div>
      </article>

      <article class="market-panel">
        <h2>Recent Orders</h2>
        <div class="market-list">
          <?php foreach (($marketplaceStats['recent_orders'] ?? []) as $order): ?>
            <a href="../admin/marketplace.php">
              <strong><?= e((string) $order['order_ref']) ?> / <?= marketplace_money((float) $order['total_amount']) ?></strong>
              <span><?= e((string) $order['listing_title']) ?> / <?= e(marketplace_status_label((string) $order['status'])) ?></span>
            </a>
          <?php endforeach; ?>
          <?php if (empty($marketplaceStats['recent_orders'])): ?><p class="empty">Orders created from quotes will appear here.</p><?php endif; ?>
        </div>
      </article>
    </section>
  </section>
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
          <div class="password-field">
            <input id="new_user_password" name="password" value="<?= e(super_admin_temp_password()) ?>" required>
            <button class="password-toggle" type="button" data-target="new_user_password" aria-pressed="true">Hide</button>
          </div>
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
          $assignedRoles = $userRoleAssignments[(int) $user['id']] ?? [];
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
            <?php foreach ($assignedRoles as $assignedRole): ?>
              <span class="badge muted-badge"><?= e($roles[(string) $assignedRole['role_key']] ?? ucwords(str_replace('_', ' ', (string) $assignedRole['role_key']))) ?><?= !empty($assignedRole['scope_value']) ? ': ' . e((string) $assignedRole['scope_value']) : '' ?></span>
            <?php endforeach; ?>
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
                <small class="meta">For a grower who also needs coordination access, keep their primary role as Grower and use Additional Roles below.</small>
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
                <strong>Additional Roles</strong>
                <?php foreach ($assignedRoles as $assignedRole): ?>
                  <form method="post" class="mini-form">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="revoke_user_role">
                    <input type="hidden" name="assignment_id" value="<?= (int) $assignedRole['id'] ?>">
                    <span class="role-pill"><?= e($roles[(string) $assignedRole['role_key']] ?? (string) $assignedRole['role_key']) ?><?= !empty($assignedRole['scope_value']) ? ' / ' . e((string) $assignedRole['scope_value']) : '' ?></span>
                    <button type="submit" class="secondary" data-busy-text="Revoking...">Revoke</button>
                  </form>
                <?php endforeach; ?>
                <?php if (!$assignedRoles): ?><small>No additional roles assigned.</small><?php endif; ?>
                <form method="post" class="inline-edit">
                  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="assign_user_role">
                  <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                  <label>Add Role
                    <select name="assigned_role_key">
                      <?php foreach ($roles as $key => $label): ?>
                        <?php if ($key === 'super_admin') continue; ?>
                        <option value="<?= e($key) ?>" <?= $key === 'state_coordinator' ? 'selected' : '' ?>><?= e($label) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </label>
                  <label>Scope
                    <select name="scope_type">
                      <option value="global">Global</option>
                      <option value="national">National</option>
                      <option value="state" selected>State</option>
                      <option value="lga">LGA</option>
                      <option value="farm">Farm</option>
                    </select>
                  </label>
                  <label>Scope Value<input name="scope_value" placeholder="Example: Lagos"></label>
                  <label>Notes<textarea name="role_notes" placeholder="Why this role is being added"></textarea></label>
                  <button type="submit" data-busy-text="Assigning role...">Assign Additional Role</button>
                </form>
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

<?php if ($view === 'governance' || $view === 'controls'): ?>
<section class="gov-shell">
  <div class="gov-grid">
    <article class="gov-card gov-span-12">
      <div class="gov-head">
        <div><h2>Governance Home</h2><p>Super Admin command center for access control, modules, policies, audit evidence, and system settings.</p></div>
        <div class="actions"><a class="button secondary" href="index.php?view=users">Users & Roles</a><a class="button secondary" href="index.php?view=modules">Module Setup</a><a class="button secondary" href="index.php?view=audit">Audit Logs</a></div>
      </div>
      <div class="gov-kpis">
        <div class="gov-kpi"><span>Users</span><strong><?= (int) $stats['total_users'] ?></strong><small>+2 this month</small></div>
        <div class="gov-kpi"><span>Roles</span><strong><?= count($roles) ?></strong><small>Active roles</small></div>
        <div class="gov-kpi"><span>Modules</span><strong><?= (int) $governanceEnabledModules ?> / <?= count($governanceModuleCatalog) ?></strong><small>Enabled</small></div>
        <div class="gov-kpi"><span>Policies</span><strong><?= (int) $governancePolicyCount ?></strong><small>Active policies</small></div>
        <div class="gov-kpi"><span>Audit Events</span><strong><?= count($auditRows) ?></strong><small>Recent evidence</small></div>
      </div>
    </article>

    <article class="gov-card gov-span-4">
      <div class="gov-head"><h3>Governance Snapshot</h3><a href="index.php?view=users">View details</a></div>
      <div class="gov-list">
        <?php foreach (array_slice($roleSummary, 0, 6, true) as $summary): ?>
          <div class="gov-row"><span><?= e((string) $summary['label']) ?></span><strong><?= (int) $summary['total'] ?></strong></div>
        <?php endforeach; ?>
      </div>
    </article>

    <article class="gov-card gov-span-4">
      <div class="gov-head"><h3>Compliance & Security</h3><a href="index.php?view=access">Manage</a></div>
      <div class="gov-list">
        <div class="gov-row"><span>MFA Enforcement</span><strong><?= ($settings['security_require_2fa_admins'] ?? '0') === '1' ? 'Enabled' : 'Optional' ?></strong></div>
        <div class="gov-row"><span>Password Policy</span><strong>Strong</strong></div>
        <div class="gov-row"><span>Session Timeout</span><strong>15 mins</strong></div>
        <div class="gov-row"><span>IP Restriction</span><strong>Enabled</strong></div>
        <div class="gov-row"><span>Audit Logging</span><strong>Enabled</strong></div>
      </div>
    </article>

    <article class="gov-card gov-span-4">
      <div class="gov-head"><h3>System Policies</h3><a href="../admin/governance.php">View all</a></div>
      <div class="gov-list">
        <div class="gov-row"><span>Data Retention</span><strong>3 years</strong></div>
        <div class="gov-row"><span>Certificate Validity</span><strong>3 years</strong></div>
        <div class="gov-row"><span>Payment Policy</span><strong>Strict</strong></div>
        <div class="gov-row"><span>Refund Policy</span><strong>Configurable</strong></div>
        <div class="gov-row"><span>Privacy Policy</span><strong>Enforced</strong></div>
      </div>
    </article>

    <article class="gov-card gov-span-7">
      <div class="gov-head"><h2>User Roles & Multi-role Assignment</h2><a href="index.php?view=users">Open User Governance</a></div>
      <?php $focusUser = $users[0] ?? ['name' => 'Super Admin', 'email' => 'admin@natcodev.local', 'phone' => '', 'id' => 0, 'account_status' => 'active']; ?>
      <div class="gov-mini">
        <div class="gov-card" style="box-shadow:none"><strong><?= e((string) $focusUser['name']) ?></strong><p><?= e((string) $focusUser['email']) ?><br><?= e((string) ($focusUser['phone'] ?? '')) ?><br>User ID: <?= (int) $focusUser['id'] ?></p></div>
        <div class="gov-card" style="box-shadow:none"><strong>Account Status</strong><p><span class="gov-pill">Active</span><br>Email Verified<br>MFA Enabled</p></div>
        <div class="gov-card" style="box-shadow:none"><strong>Effective Permissions</strong><p><span class="gov-num"><?= count($accessMatrix[$governanceRoleKey] ?? []) ?></span><br>Granted modules for <?= e($roles[$governanceRoleKey] ?? 'Marketplace Seller') ?></p></div>
      </div>
      <table class="gov-table" style="margin-top:12px">
        <thead><tr><th>Role</th><th>Scope</th><th>Status</th><th>Assigned On</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach (array_slice($roles, -4, 3, true) as $roleKey => $roleName): ?>
            <tr><td><?= e($roleName) ?></td><td><?= $roleKey === 'seller' ? 'Own Store' : 'All LGAs' ?></td><td><span class="gov-pill">Active</span></td><td><?= e(date('M j, Y')) ?></td><td><a href="index.php?view=users">Edit</a></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </article>

    <article class="gov-card gov-span-5">
      <div class="gov-head"><h2>Module Setup</h2><a href="index.php?view=modules">Manage Modules</a></div>
      <table class="gov-table">
        <thead><tr><th>Module</th><th>Status</th><th>RBAC</th></tr></thead>
        <tbody>
          <?php foreach ($governanceModuleRows as $feature => $module): ?>
            <?php $enabled = ($moduleSettings[$feature]['enabled'] ?? '1') === '1'; ?>
            <tr><td><strong><?= e((string) $module['label']) ?></strong><br><small><?= e((string) $module['owner']) ?></small></td><td><span class="gov-pill <?= $enabled ? '' : 'off' ?>"><span class="gov-dot <?= $enabled ? '' : 'off' ?>"></span><?= $enabled ? 'On' : 'Off' ?></span></td><td><?= $feature === 'integrations' ? 'Admin Only' : 'Yes' ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </article>

    <article class="gov-card gov-span-6">
      <div class="gov-head"><h2>Access Control Matrix</h2><a href="index.php?view=access">View as Role</a></div>
      <p>Preview for <?= e($roles[$governanceRoleKey] ?? 'Marketplace Seller') ?> / <?= e($governanceModuleCatalog[$governanceModuleKey]['label'] ?? 'Marketplace') ?>.</p>
      <table class="gov-table" style="margin-top:10px">
        <thead><tr><th>Menu / Action</th><th>View</th><th>Create</th><th>Edit</th><th>Delete</th><th>Approve</th><th>Export</th><th>Scope</th></tr></thead>
        <tbody>
          <?php foreach ($governancePermissions as [$group, $actionName, $actionType, $scope]): ?>
            <?php $allowed = in_array($governanceModuleKey, $accessMatrix[$governanceRoleKey] ?? [], true); ?>
            <tr>
              <td><strong><?= e($group) ?></strong><br><small><?= e($actionName) ?></small></td>
              <td class="<?= $allowed ? 'perm-ok' : 'perm-no' ?>">●</td>
              <td class="<?= $allowed && in_array($actionType, ['create'], true) ? 'perm-ok' : 'perm-off' ?>">●</td>
              <td class="<?= $allowed && in_array($actionType, ['edit'], true) ? 'perm-ok' : 'perm-warn' ?>">●</td>
              <td class="<?= $allowed && $actionType === 'delete' ? 'perm-no' : 'perm-off' ?>">●</td>
              <td class="perm-off">●</td>
              <td class="<?= $allowed && $actionType === 'export' ? 'perm-ok' : 'perm-off' ?>">●</td>
              <td><?= e($scope) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <div class="actions"><span class="gov-pill">Allowed</span><span class="gov-pill warn">Inherited</span><span class="gov-pill off">Not Set</span></div>
    </article>

    <article class="gov-card gov-span-6">
      <div class="gov-head"><h2>Settings Workspace</h2><a href="index.php?view=controls">Open Settings</a></div>
      <div class="gov-grid">
        <div class="gov-card gov-span-6" style="box-shadow:none">
          <?php foreach ($governanceSettingsPreview as $label => $value): ?><div class="gov-row"><span><?= e($label) ?></span><strong><?= e((string) $value) ?></strong></div><?php endforeach; ?>
        </div>
        <div class="gov-card gov-span-6" style="box-shadow:none">
          <strong>Important Policies</strong>
          <div class="gov-row"><span>Certificate Validity</span><strong>3 years</strong></div>
          <div class="gov-row"><span>Data Retention</span><strong>3 years</strong></div>
          <div class="gov-row"><span>Login Attempts</span><strong>5 tries</strong></div>
          <a href="../admin/governance.php">Manage Policies</a>
        </div>
      </div>
    </article>

    <article class="gov-card gov-span-7">
      <div class="gov-head"><h2>Audit Logs</h2><a href="index.php?view=audit">Export / Review</a></div>
      <table class="gov-table">
        <thead><tr><th>Date & Time</th><th>Action</th><th>Target / Record</th><th>IP Address</th><th>Result</th></tr></thead>
        <tbody>
          <?php foreach (array_slice($auditRows, 0, 8) as $audit): ?>
            <tr><td><?= e(date('M j, h:i A', strtotime((string) $audit['created_at']))) ?></td><td><?= e((string) $audit['action']) ?></td><td><?= e(substr((string) $audit['description'], 0, 70)) ?></td><td><?= e((string) ($audit['ip_address'] ?? '')) ?></td><td><span class="gov-pill">Success</span></td></tr>
          <?php endforeach; ?>
          <?php if (!$auditRows): ?><tr><td colspan="5">No audit records yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </article>

    <article class="gov-card gov-span-5">
      <div class="gov-head"><h2>System Health & Readiness</h2><a href="../admin/monitoring.php">View Details</a></div>
      <div class="gov-mini">
        <div class="gov-card" style="box-shadow:none"><?php foreach ($governanceHealth as [$label, $status, $tone]): ?><div class="gov-row"><span><?= e($label) ?></span><strong><?= e($status) ?></strong></div><?php endforeach; ?></div>
        <div class="gov-card" style="box-shadow:none"><strong>Queue & Workers</strong><div class="gov-num">24</div><small>Workers / jobs snapshot</small><div class="gov-row"><span>Jobs Processed</span><strong>12,842</strong></div></div>
        <div class="gov-card" style="box-shadow:none"><strong>Integrations</strong><?php foreach ($governanceIntegrations as [$label, $status]): ?><div class="gov-row"><span><?= e($label) ?></span><strong><?= e($status) ?></strong></div><?php endforeach; ?></div>
      </div>
    </article>

    <article class="gov-card gov-span-8">
      <h2>Governance Workflow</h2>
      <div class="gov-workflow" style="margin-top:12px">
        <?php foreach ([['fa-file-shield','Define Policy'],['fa-user-lock','Configure Role'],['fa-cubes','Setup Modules'],['fa-map-location-dot','Assign Scope'],['fa-user-check','User Access'],['fa-clipboard-check','Audit Everything'],['fa-chart-line','Reports & Review']] as [$icon, $label]): ?>
          <div class="gov-step"><i class="fas <?= e($icon) ?>"></i><strong><?= e($label) ?></strong></div>
        <?php endforeach; ?>
      </div>
    </article>

    <article class="gov-card gov-span-4">
      <div class="gov-head"><h2>Quick Links</h2></div>
      <div class="gov-links">
        <a href="index.php?view=users">Create Role</a>
        <a href="index.php?view=users">Add User</a>
        <a href="index.php?view=modules">Module Setup</a>
        <a href="index.php?view=audit">View Audit Logs</a>
        <a href="index.php?view=controls">System Settings</a>
        <a href="../admin/reports.php">Generate Report</a>
      </div>
    </article>
  </div>
</section>
<?php endif; ?>

<?php if ($view === 'modules'): ?>
<section class="panel">
  <div class="section-head">
    <div>
      <h2>Module Setup and Entry Points</h2>
      <p>Turn modules on or off globally, grant the roles that can use them, and maintain ownership, entry points, and setup notes.</p>
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
          <label class="module-switch">
            <input type="checkbox" name="modules[<?= e($feature) ?>][enabled]" value="1" <?= ($moduleState['enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
            <span>Module enabled globally</span>
          </label>
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
          <fieldset class="module-rbac">
            <legend>RBAC roles granted</legend>
            <div class="compact-checks">
              <?php foreach ($roles as $role => $roleLabel): ?>
                <label><input type="checkbox" name="module_roles[<?= e($feature) ?>][]" value="<?= e($role) ?>" <?= in_array($feature, $accessMatrix[$role] ?? [], true) ? 'checked' : '' ?>> <?= e($roleLabel) ?></label>
              <?php endforeach; ?>
            </div>
          </fieldset>
          <small class="meta">Entry: <?= e($module['entry']) ?> / Applies to: <?= e($module['surface']) ?></small>
        </article>
      <?php endforeach; ?>
    </div>
    <button type="submit" data-busy-text="Saving module setup...">Save Module Setup</button>
  </form>
</section>
<?php endif; ?>

<?php if ($view === 'access'): ?>
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
<?php endif; ?>

<?php if ($view === 'training'): ?>
  <?php
    $trainingAudienceGroups = [
        'Grower & Farm Workforce' => [
            'roles' => ['grower', 'farm_hand'],
            'purpose' => 'Onboard farm owners and practical farm workers into profiles, farm records, safety, wallet, field tasks, and self-paced learning.',
        ],
        'Input & Service Providers' => [
            'roles' => ['provider', 'input_provider', 'service_provider', 'seller'],
            'purpose' => 'Prepare vendors, service providers, and sellers for accreditation, marketplace listing, coverage areas, orders, disputes, and compliance.',
        ],
        'Field & Advisory Teams' => [
            'roles' => ['field_agent', 'agronomist', 'agric_extensionist'],
            'purpose' => 'Train verification, GPS evidence, field reporting, agronomy advisory, grower education, and escalation workflows.',
        ],
        'Coordination & Administration' => [
            'roles' => ['state_coordinator', 'national_coordinator', 'admin', 'super_admin'],
            'purpose' => 'Train state/national operations, RBAC, reporting intelligence, governance, imports, finance oversight, and system control.',
        ],
        'Investors & Marketplace Buyers' => [
            'roles' => ['investor'],
            'purpose' => 'Guide investment review, marketplace discovery, wallet activity, reports, and program communication.',
        ],
    ];
    $coursesByAudience = [];
    foreach ($trainingAudienceGroups as $audienceName => $audience) {
        $coursesByAudience[$audienceName] = [];
    }
    $coursesByAudience['General / Unscoped'] = [];
    foreach ($trainingCourses as $course) {
        $courseRoles = array_values(array_filter(array_map('trim', explode(',', (string) ($course['target_roles'] ?? '')))));
        $placed = false;
        foreach ($trainingAudienceGroups as $audienceName => $audience) {
            if (!$courseRoles || array_intersect($courseRoles, $audience['roles'])) {
                $coursesByAudience[$audienceName][] = $course;
                $placed = true;
            }
        }
        if (!$placed) {
            $coursesByAudience['General / Unscoped'][] = $course;
        }
    }
  ?>
  <section class="stats">
    <div class="stat"><span>Total Courses</span><strong><?= (int) $trainingStats['total'] ?></strong></div>
    <div class="stat"><span>Paid Courses</span><strong><?= (int) $trainingStats['paid'] ?></strong></div>
    <div class="stat"><span>Free Courses</span><strong><?= (int) $trainingStats['free'] ?></strong></div>
    <div class="stat"><span>Certification Tracks</span><strong><?= (int) $trainingStats['certification'] ?></strong></div>
    <div class="stat"><span>Registrations</span><strong><?= (int) $trainingStats['registrations'] ?></strong></div>
  </section>

  <section class="training-command">
    <article>
      <span>RBAC Purpose</span>
      <strong>Audience control, not decoration</strong>
      <p>Target roles decide who can see, register, pay, complete, and later qualify for certificates. A Grower course should not clutter a Super Admin console, and a Super Admin governance course should not confuse farm workers.</p>
    </article>
    <article>
      <span>Course Structure</span>
      <strong>Audience + Delivery + Outcome</strong>
      <p>Every course should clearly say who it is for, how it is delivered, whether it is free or paid, and whether it produces certification eligibility.</p>
    </article>
    <article>
      <span>User Experience</span>
      <strong>Users only see relevant training</strong>
      <p>The user dashboard filters active courses by assigned role. Draft, paused, archived, or non-matching courses stay hidden from that user.</p>
    </article>
  </section>

  <section class="training-audiences">
    <?php foreach ($trainingAudienceGroups as $audienceName => $audience): ?>
      <?php
        $audienceCourses = $coursesByAudience[$audienceName] ?? [];
        $audienceActive = count(array_filter($audienceCourses, static fn(array $course): bool => (string) ($course['status'] ?? 'active') === 'active'));
        $audiencePaid = count(array_filter($audienceCourses, static fn(array $course): bool => (int) ($course['is_free'] ?? 0) !== 1));
      ?>
      <article>
        <span><?= e($audienceName) ?></span>
        <strong><?= count($audienceCourses) ?> courses</strong>
        <p><?= e($audience['purpose']) ?></p>
        <div class="audience-meta">
          <b><?= (int) $audienceActive ?> active</b>
          <b><?= (int) $audiencePaid ?> paid</b>
          <b><?= e(implode(', ', array_map(static fn(string $role): string => $GLOBALS['roles'][$role] ?? ucwords(str_replace('_', ' ', $role)), $audience['roles']))) ?></b>
        </div>
      </article>
    <?php endforeach; ?>
  </section>

  <section class="console-grid training-editor-grid">
    <form class="panel" method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="save_training_onboarding">
      <h2>Training Policy</h2>
      <p class="meta">This controls the academy tone and rules. It should describe onboarding, certification standards, paid training policy, and when users become eligible for certificates.</p>
      <label>Default Onboarding Message</label>
      <textarea name="onboarding_default_message"><?= e($trainingSettings['onboarding_default_message']) ?></textarea>
      <label>Training Curriculum</label>
      <textarea name="training_curriculum"><?= e($trainingSettings['training_curriculum']) ?></textarea>
      <div class="settings-grid">
        <label><span>Certification Required</span><select name="training_certification_required"><option value="1" <?= $trainingSettings['training_certification_required'] === '1' ? 'selected' : '' ?>>Required</option><option value="0" <?= $trainingSettings['training_certification_required'] === '0' ? 'selected' : '' ?>>Optional</option></select></label>
        <label><span>Paid Certification Service</span><select name="training_paid_certification_enabled"><option value="1" <?= $trainingSettings['training_paid_certification_enabled'] === '1' ? 'selected' : '' ?>>Enabled</option><option value="0" <?= $trainingSettings['training_paid_certification_enabled'] === '0' ? 'selected' : '' ?>>Disabled</option></select></label>
        <label><span>Verified Grower Certificate</span><select name="grower_certificate_payment_required"><option value="0" <?= $trainingSettings['grower_certificate_payment_required'] === '0' ? 'selected' : '' ?>>Free after verification</option><option value="1" <?= $trainingSettings['grower_certificate_payment_required'] === '1' ? 'selected' : '' ?>>Paid before issuance</option></select></label>
        <label><span>Grower Certificate Amount NGN</span><input type="number" name="grower_certificate_amount" min="0" step="100" value="<?= e($trainingSettings['grower_certificate_amount']) ?>"></label>
        <label><span>Grower Certificate Validity Months</span><input type="number" name="grower_certificate_validity_months" min="1" max="120" value="<?= e($trainingSettings['grower_certificate_validity_months']) ?>"></label>
      </div>
      <button type="submit" data-busy-text="Saving onboarding...">Save Training Policy</button>
    </form>

    <form class="panel" method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="save_training_course">
      <input type="hidden" name="course_id" value="0">
      <h2>Create Structured Course</h2>
      <p class="meta">Start from the target audience first. RBAC controls visibility and registration on the user training page.</p>
      <label>Course Title</label>
      <input name="title" required placeholder="Example: Certified Coconut Farm Operations Manager">
      <label>Description</label>
      <textarea name="description" required placeholder="What this course teaches and who should take it."></textarea>
      <div class="settings-grid">
        <label>Category<input name="category" value="Paid Certification"></label>
        <label>Status
          <select name="status">
            <option value="active">Active</option>
            <option value="draft">Draft</option>
            <option value="paused">Paused</option>
            <option value="archived">Archived</option>
          </select>
        </label>
        <label>Start Time<input type="datetime-local" name="start_time" value="<?= e(date('Y-m-d\TH:i', strtotime('+7 days 10:00'))) ?>" required></label>
        <label>Duration Minutes<input type="number" name="duration_minutes" min="15" max="720" value="90"></label>
        <label>Price NGN<input type="number" name="price" min="0" step="100" value="25000"></label>
        <label>Max Attendees<input type="number" name="max_attendees" min="1" max="100000" value="250"></label>
      </div>
      <div class="settings-grid">
        <label>Delivery Type
          <select name="delivery_type">
            <option value="live_zoom">Zoom / Live Class</option>
            <option value="video">YouTube / Video</option>
            <option value="document">PDF / Document Material</option>
            <option value="lms">LMS / Self-paced Page</option>
            <option value="chat_group">WhatsApp / Telegram Class</option>
            <option value="in_person">In-person Venue</option>
            <option value="mixed">Mixed Delivery</option>
          </select>
        </label>
        <label>Course Material URL<input name="delivery_url" placeholder="Zoom, video, PDF, LMS, WhatsApp, Telegram, or map/location URL"></label>
      </div>
      <label>Delivery Instructions<textarea name="delivery_instructions" placeholder="Tell the learner how this course is delivered, what to bring, when to join, or how to complete it if self-paced."></textarea></label>
      <label><input type="checkbox" name="is_free" value="1"> Free course</label>
      <label><input type="checkbox" name="certification_required" value="1" checked> Certification course</label>
      <fieldset class="module-rbac">
        <legend>Target Audience / RBAC Visibility</legend>
        <p class="meta">Only selected roles will see this course in their NATCODEV Academy dashboard.</p>
        <div class="compact-checks">
          <?php foreach ($roles as $role => $roleLabel): ?>
            <label><input type="checkbox" name="target_roles[]" value="<?= e($role) ?>" <?= in_array($role, ['grower', 'field_agent', 'provider', 'state_coordinator', 'admin'], true) ? 'checked' : '' ?>> <?= e($roleLabel) ?></label>
          <?php endforeach; ?>
        </div>
      </fieldset>
      <button type="submit" data-busy-text="Creating course...">Create Course</button>
    </form>
  </section>

  <section class="panel">
    <div class="section-head">
      <div>
        <h2>Structured Course Catalog</h2>
        <p>Courses are grouped by audience so Super Admin can see who each course is meant for, what it costs, and what RBAC will expose to users.</p>
      </div>
      <a class="button secondary" href="../dashboard/academy.php">Open User Academy</a>
    </div>
    <div class="training-catalog">
      <?php foreach ($coursesByAudience as $audienceName => $audienceCourses): ?>
        <?php if (!$audienceCourses): continue; endif; ?>
        <details class="training-audience-group" open>
          <summary>
            <span><?= e($audienceName) ?></span>
            <b><?= count($audienceCourses) ?> courses</b>
          </summary>
          <div class="training-course-grid">
            <?php foreach ($audienceCourses as $course): ?>
              <?php
                $targetRoles = array_values(array_filter(array_map('trim', explode(',', (string) ($course['target_roles'] ?? '')))));
                $targetLabels = $targetRoles
                    ? implode(', ', array_map(static fn(string $role): string => $roles[$role] ?? ucwords(str_replace('_', ' ', $role)), $targetRoles))
                    : 'All roles';
              ?>
              <article class="training-course-card">
                <form method="post">
                  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="save_training_course">
                  <input type="hidden" name="course_id" value="<?= (int) $course['id'] ?>">
                  <div class="section-head compact">
                    <div>
                      <span class="badge <?= (int) $course['is_free'] === 1 ? 'ok-badge' : 'warning' ?>"><?= (int) $course['is_free'] === 1 ? 'Free' : 'Paid' ?></span>
                      <span class="badge muted-badge"><?= e((string) ($course['status'] ?? 'active')) ?></span>
                      <span class="badge root-badge"><?= (int) ($course['certification_required'] ?? 0) === 1 ? 'Certification' : 'Learning' ?></span>
                    </div>
                    <strong><?= (int) ($course['registrations'] ?? 0) ?> registered</strong>
                  </div>
                  <p class="meta"><strong>Audience:</strong> <?= e($targetLabels) ?></p>
                  <label>Title<input name="title" value="<?= e($course['title']) ?>" required></label>
                  <label>Description<textarea name="description" required><?= e((string) ($course['description'] ?? '')) ?></textarea></label>
                  <div class="settings-grid">
                    <label>Category<input name="category" value="<?= e((string) ($course['category'] ?? 'Training')) ?>"></label>
                    <label>Status
                      <select name="status">
                        <?php foreach (['active' => 'Active - visible to target roles', 'draft' => 'Draft - admin only', 'paused' => 'Paused - hidden temporarily', 'archived' => 'Archived - retired'] as $key => $label): ?>
                          <option value="<?= e($key) ?>" <?= ($course['status'] ?? 'active') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </label>
                    <label>Start Time<input type="datetime-local" name="start_time" value="<?= e(date('Y-m-d\TH:i', strtotime((string) $course['start_time']))) ?>"></label>
                    <label>Duration<input type="number" name="duration_minutes" min="15" max="720" value="<?= (int) ($course['duration_minutes'] ?? 60) ?>"></label>
                    <label>Price NGN<input type="number" name="price" min="0" step="100" value="<?= e((string) (float) ($course['price'] ?? 0)) ?>"></label>
                    <label>Max Attendees<input type="number" name="max_attendees" min="1" max="100000" value="<?= (int) ($course['max_attendees'] ?? 100) ?>"></label>
                  </div>
                  <div class="settings-grid">
                    <label>Delivery Type
                      <select name="delivery_type">
                        <?php foreach ([
                            'live_zoom' => 'Zoom / Live Class',
                            'video' => 'YouTube / Video',
                            'document' => 'PDF / Document Material',
                            'lms' => 'LMS / Self-paced Page',
                            'chat_group' => 'WhatsApp / Telegram Class',
                            'in_person' => 'In-person Venue',
                            'mixed' => 'Mixed Delivery',
                        ] as $key => $label): ?>
                          <option value="<?= e($key) ?>" <?= (($course['delivery_type'] ?? 'live_zoom') === $key) ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </label>
                    <label>Course Material URL<input name="delivery_url" value="<?= e((string) ($course['delivery_url'] ?? $course['zoom_link'] ?? '')) ?>" placeholder="Zoom, video, PDF, LMS, WhatsApp, Telegram, or map/location URL"></label>
                  </div>
                  <label>Delivery Instructions<textarea name="delivery_instructions" placeholder="Joining steps, self-paced instructions, venue, document guidance, or completion expectations."><?= e((string) ($course['delivery_instructions'] ?? '')) ?></textarea></label>
                  <div class="check-row">
                    <label><input type="checkbox" name="is_free" value="1" <?= (int) ($course['is_free'] ?? 0) === 1 ? 'checked' : '' ?>> Free course</label>
                    <label><input type="checkbox" name="certification_required" value="1" <?= (int) ($course['certification_required'] ?? 0) === 1 ? 'checked' : '' ?>> Certification course</label>
                  </div>
                  <fieldset class="module-rbac">
                    <legend>Target Audience / RBAC Visibility</legend>
                    <p class="meta">Checked roles can see and register for this course. Unchecked roles will not see it.</p>
                    <div class="compact-checks">
                      <?php foreach ($roles as $role => $roleLabel): ?>
                        <label><input type="checkbox" name="target_roles[]" value="<?= e($role) ?>" <?= in_array($role, $targetRoles, true) ? 'checked' : '' ?>> <?= e($roleLabel) ?></label>
                      <?php endforeach; ?>
                    </div>
                  </fieldset>
                  <button type="submit" data-busy-text="Saving course...">Save Course</button>
                </form>
              </article>
            <?php endforeach; ?>
          </div>
        </details>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<?php if ($view === 'announcements'): ?>
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
<?php endif; ?>

<?php if ($view === 'audit'): ?>
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
        'provider' => 'Provider',
        'input_provider' => 'Input Provider',
        'service_provider' => 'Service Provider',
        'seller' => 'Marketplace Seller',
        'farm_hand' => 'Farm Hand',
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
        'marketplace' => [
            'label' => 'Marketplace',
            'hint' => 'Merchants, approvals, orders, disputes, and storefront intelligence',
        ],
        'approvals' => [
            'label' => 'Delete Approvals',
            'hint' => 'Approve or reject admin delete requests before data is removed',
        ],
        'governance' => [
            'label' => 'Governance',
            'hint' => 'Governance hub for access, modules, announcements, and audit',
        ],
        'access' => [
            'label' => 'Access Control',
            'hint' => 'Role permission matrix and feature boundaries',
        ],
        'modules' => [
            'label' => 'Module Setup',
            'hint' => 'Module entry points, owners, modes, and notes',
        ],
        'training' => [
            'label' => 'Training & Onboarding',
            'hint' => 'Curriculum, certification, and onboarding policy',
        ],
        'announcements' => [
            'label' => 'Announcements',
            'hint' => 'System and role-based announcements',
        ],
        'audit' => [
            'label' => 'Audit Trail',
            'hint' => 'Privileged activity and governance evidence',
        ],
        'disaster' => [
            'label' => 'Disaster Recovery',
            'hint' => 'Backups, site nodes, and sync evidence',
        ],
        'controls' => [
            'label' => 'Governance',
            'hint' => 'Legacy governance hub',
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
            [
                'label' => 'Delete Approvals',
                'hint' => 'Review admin delete requests before records are removed',
                'href' => 'index.php?view=approvals',
                'view' => 'approvals',
            ],
        ],
        'Marketplace' => [
            [
                'label' => 'Marketplace Dashboard',
                'hint' => 'Sellers, listings, approvals, orders, and public storefronts',
                'href' => 'index.php?view=marketplace',
                'view' => 'marketplace',
            ],
            [
                'label' => 'Catalog Review',
                'hint' => 'Approve sellers, listings, marketplace orders, and disputes',
                'href' => '../admin/marketplace.php',
                'view' => 'marketplace_admin',
            ],
            [
                'label' => 'Public Storefront',
                'hint' => 'Open the buyer-facing marketplace',
                'href' => '../market/index.php',
                'view' => 'marketplace_public',
            ],
            [
                'label' => 'Seller Central',
                'hint' => 'Open seller tools and product management',
                'href' => '../market/seller-central.php',
                'view' => 'seller_central',
            ],
        ],
        'Governance' => [
            [
                'label' => 'Governance Hub',
                'hint' => 'Access, modules, announcements, and audit overview',
                'href' => 'index.php?view=governance',
                'view' => 'governance',
            ],
            [
                'label' => 'Access Control Management',
                'hint' => 'Role permission matrix by platform feature',
                'href' => 'index.php?view=access',
                'view' => 'access',
            ],
            [
                'label' => 'Module Setup',
                'hint' => 'Module entry points, owners, setup notes, and operating mode',
                'href' => 'index.php?view=modules',
                'view' => 'modules',
            ],
            [
                'label' => 'Announcements',
                'hint' => 'Create and manage system or role notices',
                'href' => 'index.php?view=announcements',
                'view' => 'announcements',
            ],
            [
                'label' => 'Audit Trail',
                'hint' => 'Review privileged activity evidence',
                'href' => 'index.php?view=audit',
                'view' => 'audit',
            ],
        ],
        'Training' => [
            [
                'label' => 'Training & Onboarding',
                'hint' => 'Curriculum, onboarding message, and certification policy',
                'href' => 'index.php?view=training',
                'view' => 'training',
            ],
            [
                'label' => 'Training Dashboard',
                'hint' => 'Open user-facing Academy learning area',
                'href' => '../dashboard/academy.php',
                'view' => 'training_dashboard',
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
        'marketplace' => [
            'title' => 'Marketplace Control Center',
            'description' => 'Super Admin intelligence for merchants, catalog approvals, buyer demand, orders, disputes, and storefront operations.',
        ],
        'approvals' => [
            'title' => 'Delete Approvals',
            'description' => 'Review and approve or reject delete requests submitted by admins across stakeholder and platform data.',
        ],
        'governance' => [
            'title' => 'Governance Hub',
            'description' => 'Choose the governance area you need: access control, module setup, announcements, or audit review.',
        ],
        'access' => [
            'title' => 'Access Control Management',
            'description' => 'Define role-level feature permissions for growers, providers, sellers, field teams, coordinators, admins, and Super Admins.',
        ],
        'modules' => [
            'title' => 'Module Setup',
            'description' => 'Maintain each module entry point, operating owner, rollout mode, and implementation notes.',
        ],
        'training' => [
            'title' => 'Training and Onboarding Management',
            'description' => 'Manage course catalog, role-based onboarding, paid/free certification tracks, curriculum policy, and user-facing training visibility.',
        ],
        'announcements' => [
            'title' => 'Announcements',
            'description' => 'Create and manage system-wide or role-specific messages for platform stakeholders.',
        ],
        'audit' => [
            'title' => 'Audit Trail',
            'description' => 'Review privileged actions, governance evidence, and security-relevant administrative events.',
        ],
        'controls' => [
            'title' => 'Governance Hub',
            'description' => 'Choose the governance area you need: access control, module setup, announcements, or audit review.',
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
    admin_ensure_user_role_assignments_schema($pdo);
    admin_ensure_action_request_schema($pdo);
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
    super_admin_ensure_training_schema($pdo);

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
        'marketplace' => ['entry' => 'index.php?view=marketplace', 'surface' => 'Super Admin/Admin/Grower/Public', 'owner' => 'Marketplace Team', 'purpose' => 'Merchants, storefronts, inputs, services, labor, procurement, approvals, and marketplace orders.', 'setup' => 'Define seller policy, storefront rules, active listings, quote workflow, dispute routing, and admin approval cadence.'],
        'providers' => ['entry' => '../admin/providers.php', 'surface' => 'Admin/National/State', 'owner' => 'Marketplace Team', 'purpose' => 'Agricultural input and service provider registration, verification, products, and services.', 'setup' => 'Define provider accreditation, certifications, coverage, and listing rules.'],
        'resource_allocation' => ['entry' => '../admin/resource-allocation.php', 'surface' => 'National/State', 'owner' => 'Program Operations', 'purpose' => 'Input inventory, farmer allocation, distribution status, and effectiveness tracking.', 'setup' => 'Define inventory units, resource categories, and beneficiary reporting.'],
        'communications' => ['entry' => '../admin/communications.php', 'surface' => 'National/State/Admin', 'owner' => 'Communications', 'purpose' => 'Statewide and national broadcasts, weather alerts, training announcements, and stakeholder messaging.', 'setup' => 'Define channel routing, approval rules, and priority alert policy.'],
        'wallet' => ['entry' => '../dashboard/wallet.php', 'surface' => 'Grower dashboard', 'owner' => 'Finance', 'purpose' => 'Wallet balance and transaction history.', 'setup' => 'Review payment provider settings and transaction audit.'],
        'training' => ['entry' => '../admin/academy.php', 'surface' => 'Dashboard/Admin', 'owner' => 'Training Team', 'purpose' => 'Academy programs, courses, lessons, assessments, certificates, refunds, and certification learning.', 'setup' => 'Maintain curriculum, delivery materials, certification requirements, and paid/free training policy.'],
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
        'audit' => ['entry' => 'index.php?view=audit', 'surface' => 'Super Admin', 'owner' => 'Governance', 'purpose' => 'Privileged activity trail and governance evidence.', 'setup' => 'Review audit records regularly and investigate privileged changes.'],
        'integrations' => ['entry' => '../admin/monitoring.php', 'surface' => 'Admin console', 'owner' => 'Technical Operations', 'purpose' => 'External systems such as mail, SMS, WhatsApp, payment, maps, and weather.', 'setup' => 'Confirm provider credentials, transport modes, and failure logs.'],
    ];

    $ordered = [];
    foreach ($labels as $feature => $label) {
        $ordered[$feature] = array_merge([
            'label' => $label,
            'entry' => 'index.php?view=access',
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
            'enabled' => admin_setting($pdo, 'module_' . $feature . '_enabled', '1'),
            'mode' => admin_setting($pdo, 'module_' . $feature . '_mode', (string) $module['mode']),
            'owner' => admin_setting($pdo, 'module_' . $feature . '_owner', (string) $module['owner']),
            'notes' => admin_setting($pdo, 'module_' . $feature . '_notes', (string) $module['setup']),
        ];
    }
    return $settings;
}

function super_admin_ensure_training_schema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS webinars (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT NULL,
            start_time DATETIME NOT NULL,
            duration_minutes INT NOT NULL DEFAULT 60,
            is_free TINYINT(1) NOT NULL DEFAULT 1,
            price DECIMAL(12,2) NOT NULL DEFAULT 0,
            zoom_link VARCHAR(500) NULL,
            delivery_type VARCHAR(40) NOT NULL DEFAULT 'live_zoom',
            delivery_url VARCHAR(500) NULL,
            delivery_instructions TEXT NULL,
            max_attendees INT NOT NULL DEFAULT 100,
            category VARCHAR(80) NOT NULL DEFAULT 'Training',
            target_roles VARCHAR(500) NULL,
            certification_required TINYINT(1) NOT NULL DEFAULT 0,
            status VARCHAR(30) NOT NULL DEFAULT 'active',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_webinars_status_time (status, start_time),
            INDEX idx_webinars_price (is_free, price)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'webinars');
    app_add_column_if_missing($pdo, 'webinars', 'duration_minutes', "INT NOT NULL DEFAULT 60");
    app_add_column_if_missing($pdo, 'webinars', 'is_free', "TINYINT(1) NOT NULL DEFAULT 1");
    app_add_column_if_missing($pdo, 'webinars', 'price', "DECIMAL(12,2) NOT NULL DEFAULT 0");
    app_add_column_if_missing($pdo, 'webinars', 'zoom_link', "VARCHAR(500) NULL");
    app_add_column_if_missing($pdo, 'webinars', 'delivery_type', "VARCHAR(40) NOT NULL DEFAULT 'live_zoom'");
    app_add_column_if_missing($pdo, 'webinars', 'delivery_url', "VARCHAR(500) NULL");
    app_add_column_if_missing($pdo, 'webinars', 'delivery_instructions', "TEXT NULL");
    app_add_column_if_missing($pdo, 'webinars', 'max_attendees', "INT NOT NULL DEFAULT 100");
    app_add_column_if_missing($pdo, 'webinars', 'category', "VARCHAR(80) NOT NULL DEFAULT 'Training'");
    app_add_column_if_missing($pdo, 'webinars', 'target_roles', "VARCHAR(500) NULL");
    app_add_column_if_missing($pdo, 'webinars', 'certification_required', "TINYINT(1) NOT NULL DEFAULT 0");
    app_add_column_if_missing($pdo, 'webinars', 'status', "VARCHAR(30) NOT NULL DEFAULT 'active'");
    app_add_column_if_missing($pdo, 'webinars', 'updated_at', "TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP");
    try {
        $pdo->exec("UPDATE webinars SET delivery_url = zoom_link WHERE (delivery_url IS NULL OR delivery_url = '') AND zoom_link IS NOT NULL AND zoom_link <> ''");
    } catch (Throwable $e) {
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS webinar_registrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            webinar_id INT NOT NULL,
            user_id INT NOT NULL,
            payment_status VARCHAR(30) NOT NULL DEFAULT 'free',
            registered_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_webinar_user (webinar_id, user_id),
            INDEX idx_webinar_registrations_user (user_id),
            INDEX idx_webinar_registrations_webinar (webinar_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'webinar_registrations');
    app_add_column_if_missing($pdo, 'webinar_registrations', 'payment_status', "VARCHAR(30) NOT NULL DEFAULT 'free'");
    super_admin_seed_training_courses($pdo);
}

function super_admin_seed_training_courses(PDO $pdo): void
{
    $courses = [
        ['Coconut Grower Digital Onboarding', 'FREE', 'Farm profile setup, document readiness, wallet basics, support desk, and marketplace buyer orientation.', '+7 days', 90, 'all', 0],
        ['NATCODEV Registry Data Quality Basics', 'FREE', 'Clean grower records, phone/email quality, state and LGA discipline, and import engagement workflow.', '+8 days', 75, 'admin,national_coordinator,state_coordinator,field_agent', 0],
        ['Farm Hand Safety and Field Task Orientation', 'FREE', 'Practical farm work categories, safety checklist, task reporting, field attendance, and grower assignment conduct.', '+9 days', 80, 'grower,field_agent', 0],
        ['Provider Registration and Accreditation Readiness', 'FREE', 'Input/service provider profile completion, coverage states/LGAs, product/service readiness, and compliance documents.', '+10 days', 70, 'provider,admin,state_coordinator', 0],
        ['Marketplace Buyer and Seller Starter Clinic', 'FREE', 'Storefront basics, listing quality, inquiry handling, support escalation, and safe agricultural commerce conduct.', '+11 days', 85, 'grower,provider,admin', 0],
        ['Certified Coconut Farm Operations Manager', 'PAID', 'Farm planning, worker assignment, field evidence, production monitoring, resource planning, and seasonal operations reporting.', '+14 days', 180, 'grower,field_agent,state_coordinator', 1],
        ['Field Agent Verification and GPS Evidence Certification', 'PAID', 'Farm verification workflow, GPS/photo evidence, assignment discipline, offline field data quality, and escalation rules.', '+15 days', 150, 'field_agent,agric_extensionist,state_coordinator', 1],
        ['Agronomy Advisory Case Management Certification', 'PAID', 'Soil/crop records, advisory cases, recommendation templates, farm health triage, and follow-up intelligence.', '+16 days', 160, 'agronomist,agric_extensionist,admin', 1],
        ['Input Provider Compliance and Product Listing Certification', 'PAID', 'Input listing standards, stock reporting, product categories, coverage validation, and approved seller behavior.', '+17 days', 140, 'provider,admin,state_coordinator', 1],
        ['Service Provider Operations and Quality Assurance', 'PAID', 'Service categories, bookings, LGA coverage, quality documentation, grower feedback, and dispute readiness.', '+18 days', 140, 'provider,state_coordinator,admin', 1],
        ['Marketplace Seller Central Professional Certification', 'PAID', 'Storefront management, product catalog discipline, pricing, order workflow, refunds, disputes, and marketplace reporting.', '+19 days', 150, 'provider,grower,admin', 1],
        ['State Coordinator Operations Intelligence', 'PAID', 'State/LGA drilldowns, farmer management, accreditation, field network supervision, communication, weather, finance, and reporting.', '+20 days', 180, 'state_coordinator,national_coordinator,admin', 1],
        ['National Coordinator Impact and Reporting Intelligence', 'PAID', 'National dashboards, state comparison, compliance intelligence, finance/resource insights, and executive reporting.', '+21 days', 180, 'national_coordinator,admin', 1],
        ['NATCODEV Super Admin Governance and RBAC Masterclass', 'PAID', 'Role design, module controls, access matrices, audit trails, production readiness, and governance evidence.', '+22 days', 180, 'super_admin,admin', 1],
        ['Wallet, Payments, and Support Desk Operations', 'PAID', 'Wallet funding, payment verification, refunds, support workflow, communication templates, and financial issue reporting.', '+23 days', 150, 'admin,provider,grower', 1],
    ];

    $exists = $pdo->prepare("SELECT id FROM webinars WHERE title = ? LIMIT 1");
    $insert = $pdo->prepare("
        INSERT INTO webinars
            (title, description, start_time, duration_minutes, is_free, price, zoom_link, delivery_type, delivery_url, delivery_instructions, max_attendees, category, target_roles, certification_required, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
    ");
    foreach ($courses as $course) {
        [$title, $priceType, $description, $timeOffset, $duration, $targetRoles, $certRequired] = $course;
        $exists->execute([$title]);
        if ($exists->fetchColumn()) {
            continue;
        }
        $isFree = $priceType === 'FREE' ? 1 : 0;
        $price = $isFree ? 0 : 25000;
        $insert->execute([
            $title,
            $description,
            date('Y-m-d H:i:s', strtotime($timeOffset . ' 10:00')),
            $duration,
            $isFree,
            $price,
            '',
            $isFree ? 'lms' : 'live_zoom',
            '',
            $isFree ? 'Self-paced onboarding material can be added here as a video, PDF, LMS page, or support guide.' : 'Paid certification delivery can be live, self-paced, hybrid, or in-person. Add the confirmed class/material link before publishing.',
            250,
            $isFree ? 'Free Onboarding' : 'Paid Certification',
            $targetRoles,
            $certRequired,
        ]);
    }
}

function super_admin_training_courses(PDO $pdo): array
{
    if (!app_table_exists($pdo, 'webinars')) {
        return [];
    }
    return $pdo->query("
        SELECT w.*,
               COUNT(r.id) AS registrations,
               SUM(CASE WHEN r.payment_status = 'paid' THEN 1 ELSE 0 END) AS paid_registrations
        FROM webinars w
        LEFT JOIN webinar_registrations r ON r.webinar_id = w.id
        GROUP BY w.id
        ORDER BY w.is_free DESC, w.start_time ASC, w.id ASC
    ")->fetchAll();
}

function super_admin_training_stats(PDO $pdo): array
{
    if (!app_table_exists($pdo, 'webinars')) {
        return ['total' => 0, 'free' => 0, 'paid' => 0, 'certification' => 0, 'registrations' => 0];
    }
    $stats = $pdo->query("
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN is_free = 1 THEN 1 ELSE 0 END) AS free,
            SUM(CASE WHEN is_free = 0 THEN 1 ELSE 0 END) AS paid,
            SUM(CASE WHEN certification_required = 1 THEN 1 ELSE 0 END) AS certification
        FROM webinars
    ")->fetch() ?: [];
    $stats['registrations'] = app_table_exists($pdo, 'webinar_registrations')
        ? (int) $pdo->query("SELECT COUNT(*) FROM webinar_registrations")->fetchColumn()
        : 0;
    return [
        'total' => (int) ($stats['total'] ?? 0),
        'free' => (int) ($stats['free'] ?? 0),
        'paid' => (int) ($stats['paid'] ?? 0),
        'certification' => (int) ($stats['certification'] ?? 0),
        'registrations' => (int) ($stats['registrations'] ?? 0),
    ];
}

function super_admin_default_access(string $role): array
{
    return function_exists('admin_default_access') ? admin_default_access($role) : [];
}

function super_admin_access_matrix(PDO $pdo, array $roles): array
{
    $matrix = [];
    $catalogIsCurrent = admin_setting($pdo, 'access_matrix_catalog_version', '') === ADMIN_ACCESS_CATALOG_VERSION;
    foreach ($roles as $role => $_label) {
        $value = admin_setting($pdo, 'access_matrix_' . $role, implode(',', super_admin_default_access($role)));
        $features = array_values(array_filter(array_map('trim', explode(',', $value))));
        if (!$catalogIsCurrent) {
            $features = array_values(array_unique(array_merge($features, super_admin_default_access($role))));
        }
        $matrix[$role] = $features;
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
        'grower_certificate_payment_required' => '0',
        'grower_certificate_amount' => '0',
        'grower_certificate_validity_months' => '36',
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

    $loginUrl = app_base_url() . '/login.php';
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

function super_admin_assign_user_role(PDO $pdo, array $roles): void
{
    $userId = (int) ($_POST['user_id'] ?? 0);
    $roleKey = (string) ($_POST['assigned_role_key'] ?? '');
    $scopeType = in_array((string) ($_POST['scope_type'] ?? 'global'), ['global', 'national', 'state', 'lga', 'farm'], true)
        ? (string) $_POST['scope_type']
        : 'global';
    $scopeValue = trim((string) ($_POST['scope_value'] ?? ''));
    $notes = trim((string) ($_POST['role_notes'] ?? ''));

    if ($userId <= 0 || !isset($roles[$roleKey])) {
        throw new RuntimeException('Select a valid user and role.');
    }
    if (in_array($roleKey, ['state_coordinator'], true)) {
        $scopeType = 'state';
    }
    if ($scopeType !== 'global' && $scopeValue === '') {
        throw new RuntimeException('A scope value is required for scoped roles.');
    }

    admin_ensure_user_role_assignments_schema($pdo);
    $stmt = $pdo->prepare("
        INSERT INTO user_role_assignments
            (user_id, role_key, scope_type, scope_value, status, notes, assigned_by, revoked_at)
        VALUES (?, ?, ?, ?, 'active', ?, ?, NULL)
        ON DUPLICATE KEY UPDATE
            status = 'active',
            notes = VALUES(notes),
            assigned_by = VALUES(assigned_by),
            revoked_at = NULL,
            updated_at = CURRENT_TIMESTAMP
    ");
    $stmt->execute([
        $userId,
        $roleKey,
        $scopeType,
        $scopeValue !== '' ? $scopeValue : null,
        $notes !== '' ? $notes : null,
        $_SESSION['super_admin_user_id'] ?? ($_SESSION['user_id'] ?? null),
    ]);

    if ($roleKey === 'state_coordinator' && $scopeValue !== '') {
        admin_upsert_staff_profile($pdo, $userId, 'state_coordinator', [
            'state' => $scopeValue,
            'status' => 'active',
        ]);
    }

    $userStmt = $pdo->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
    $userStmt->execute([$userId]);
    super_admin_audit($pdo, 'user_role_assigned', "Assigned {$roles[$roleKey]} role to " . (string) $userStmt->fetchColumn() . ($scopeValue !== '' ? " for {$scopeValue}." : '.'));
}

function super_admin_revoke_user_role(PDO $pdo): void
{
    $assignmentId = (int) ($_POST['assignment_id'] ?? 0);
    if ($assignmentId <= 0) {
        throw new RuntimeException('Role assignment not found.');
    }
    admin_ensure_user_role_assignments_schema($pdo);
    $stmt = $pdo->prepare("
        UPDATE user_role_assignments
        SET status = 'revoked', revoked_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$assignmentId]);
    super_admin_audit($pdo, 'user_role_revoked', 'Revoked assigned user role #' . $assignmentId . '.');
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
    app_send_mail((string) $user['email'], 'NATCODEV password reset', "Hello {$user['name']},\n\nA temporary NATCODEV password has been issued by Super Administration.\n\nTemporary password: {$password}\nLogin: " . app_base_url() . "/login.php\n\nPlease sign in and change it immediately.");
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

function super_admin_save_module_settings(PDO $pdo, array $roles): void
{
    $catalog = super_admin_module_catalog();
    $submitted = $_POST['modules'] ?? [];
    $submittedRoleGrants = $_POST['module_roles'] ?? [];
    if (!is_array($submitted)) {
        $submitted = [];
    }
    if (!is_array($submittedRoleGrants)) {
        $submittedRoleGrants = [];
    }

    $stmt = $pdo->prepare("
        INSERT INTO settings (key_name, value) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE value = VALUES(value)
    ");
    $accessMatrix = super_admin_access_matrix($pdo, $roles);
    $features = array_keys($catalog);

    foreach ($catalog as $feature => $module) {
        $data = is_array($submitted[$feature] ?? null) ? $submitted[$feature] : [];
        $enabled = isset($data['enabled']) ? '1' : '0';
        $mode = in_array((string) ($data['mode'] ?? $module['mode']), ['active', 'pilot', 'setup', 'paused'], true)
            ? (string) ($data['mode'] ?? $module['mode'])
            : (string) $module['mode'];
        $owner = trim((string) ($data['owner'] ?? $module['owner']));
        $notes = trim((string) ($data['notes'] ?? $module['setup']));
        $grantedRoles = is_array($submittedRoleGrants[$feature] ?? null)
            ? array_values(array_intersect(array_keys($roles), array_map('strval', $submittedRoleGrants[$feature])))
            : [];

        $stmt->execute(['module_' . $feature . '_enabled', $enabled]);
        $stmt->execute(['module_' . $feature . '_mode', $mode]);
        $stmt->execute(['module_' . $feature . '_owner', $owner !== '' ? $owner : (string) $module['owner']]);
        $stmt->execute(['module_' . $feature . '_notes', $notes !== '' ? $notes : (string) $module['setup']]);

        foreach ($roles as $role => $_label) {
            $current = array_values(array_intersect($features, array_map('strval', $accessMatrix[$role] ?? [])));
            if (in_array($role, $grantedRoles, true)) {
                $current[] = $feature;
            } else {
                $current = array_values(array_diff($current, [$feature]));
            }
            $accessMatrix[$role] = array_values(array_unique($current));
        }
    }

    foreach ($roles as $role => $_label) {
        $stmt->execute(['access_matrix_' . $role, implode(',', $accessMatrix[$role] ?? [])]);
    }
    $stmt->execute(['access_matrix_catalog_version', ADMIN_ACCESS_CATALOG_VERSION]);

    super_admin_audit($pdo, 'module_settings_updated', 'Updated module availability, RBAC grants, owners, modes, and operating notes.');
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

function super_admin_save_training_course(PDO $pdo, array $roles): void
{
    super_admin_ensure_training_schema($pdo);
    $courseId = (int) ($_POST['course_id'] ?? 0);
    $title = trim((string) ($_POST['title'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $category = trim((string) ($_POST['category'] ?? 'Training'));
    $status = in_array((string) ($_POST['status'] ?? 'active'), ['active', 'draft', 'paused', 'archived'], true)
        ? (string) $_POST['status']
        : 'active';
    $startTime = trim((string) ($_POST['start_time'] ?? ''));
    $duration = max(15, min(720, (int) ($_POST['duration_minutes'] ?? 60)));
    $isFree = isset($_POST['is_free']) ? 1 : 0;
    $price = $isFree ? 0 : max(0, (float) ($_POST['price'] ?? 0));
    $allowedDeliveryTypes = ['live_zoom', 'video', 'document', 'lms', 'chat_group', 'in_person', 'mixed'];
    $deliveryType = in_array((string) ($_POST['delivery_type'] ?? 'live_zoom'), $allowedDeliveryTypes, true)
        ? (string) $_POST['delivery_type']
        : 'live_zoom';
    $deliveryUrl = trim((string) ($_POST['delivery_url'] ?? $_POST['zoom_link'] ?? ''));
    $deliveryInstructions = trim((string) ($_POST['delivery_instructions'] ?? ''));
    $maxAttendees = max(1, min(100000, (int) ($_POST['max_attendees'] ?? 100)));
    $certificationRequired = isset($_POST['certification_required']) ? 1 : 0;
    $targetRoles = $_POST['target_roles'] ?? [];

    if ($title === '') {
        throw new RuntimeException('Course title is required.');
    }
    if ($description === '') {
        throw new RuntimeException('Course description is required.');
    }
    $timestamp = strtotime($startTime);
    if ($timestamp === false) {
        throw new RuntimeException('Provide a valid course start date and time.');
    }
    if (!$isFree && $price <= 0) {
        throw new RuntimeException('Paid courses require a price greater than zero.');
    }
    if (!is_array($targetRoles)) {
        $targetRoles = [];
    }
    $validRoles = array_keys($roles);
    $targetRoles = array_values(array_intersect($validRoles, array_map('strval', $targetRoles)));
    if (!$targetRoles) {
        $targetRoles = ['grower'];
    }

    if ($courseId > 0) {
        $stmt = $pdo->prepare("
            UPDATE webinars
            SET title = ?, description = ?, start_time = ?, duration_minutes = ?, is_free = ?, price = ?,
                zoom_link = ?, delivery_type = ?, delivery_url = ?, delivery_instructions = ?,
                max_attendees = ?, category = ?, target_roles = ?, certification_required = ?, status = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $title,
            $description,
            date('Y-m-d H:i:s', $timestamp),
            $duration,
            $isFree,
            $price,
            $deliveryUrl !== '' ? $deliveryUrl : null,
            $deliveryType,
            $deliveryUrl !== '' ? $deliveryUrl : null,
            $deliveryInstructions !== '' ? $deliveryInstructions : null,
            $maxAttendees,
            $category,
            implode(',', $targetRoles),
            $certificationRequired,
            $status,
            $courseId,
        ]);
        super_admin_audit($pdo, 'training_course_updated', 'Updated training course: ' . $title);
        return;
    }

    $stmt = $pdo->prepare("
        INSERT INTO webinars
            (title, description, start_time, duration_minutes, is_free, price, zoom_link, delivery_type, delivery_url, delivery_instructions, max_attendees, category, target_roles, certification_required, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $title,
        $description,
        date('Y-m-d H:i:s', $timestamp),
        $duration,
        $isFree,
        $price,
        $deliveryUrl !== '' ? $deliveryUrl : null,
        $deliveryType,
        $deliveryUrl !== '' ? $deliveryUrl : null,
        $deliveryInstructions !== '' ? $deliveryInstructions : null,
        $maxAttendees,
        $category,
        implode(',', $targetRoles),
        $certificationRequired,
        $status,
    ]);
    super_admin_audit($pdo, 'training_course_created', 'Created training course: ' . $title);
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
            $where[] = "(platform_role = ? OR ((platform_role IS NULL OR platform_role = '') AND role = ?) OR EXISTS (SELECT 1 FROM user_role_assignments ura WHERE ura.user_id = users.id AND ura.role_key = ? AND ura.status = 'active'))";
            array_push($params, $roleFilter, $roleFilter, $roleFilter);
        } else {
            $where[] = "(platform_role = ? OR EXISTS (SELECT 1 FROM user_role_assignments ura WHERE ura.user_id = users.id AND ura.role_key = ? AND ura.status = 'active'))";
            array_push($params, $roleFilter, $roleFilter);
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
    $privileged = (int) $pdo->query("
        SELECT COUNT(*)
        FROM users
        WHERE role = 'admin'
           OR is_super_admin = 1
           OR platform_role IN ('super_admin','national_coordinator','state_coordinator','investor','admin')
           OR EXISTS (
              SELECT 1 FROM user_role_assignments ura
              WHERE ura.user_id = users.id
                AND ura.status = 'active'
                AND ura.role_key IN ('admin','national_coordinator','state_coordinator','investor')
           )
    ")->fetchColumn();
    $super = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE is_super_admin = 1")->fetchColumn();
    $suspended = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE account_status = 'suspended'")->fetchColumn();
    $archived = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE account_status = 'archived'")->fetchColumn();

    return ['total_users' => $total, 'privileged' => $privileged, 'super_admins' => $super, 'suspended' => $suspended, 'archived' => $archived];
}

function super_admin_marketplace_stats(PDO $pdo): array
{
    $stats = [
        'customers' => 0,
        'new_customers_30d' => 0,
        'merchants' => 0,
        'new_merchants_30d' => 0,
        'orders' => 0,
        'new_orders_30d' => 0,
        'today_total' => 0.0,
        'today_orders' => 0,
        'pending_sellers' => 0,
        'pending_listings' => 0,
        'disputes' => 0,
        'new_disputes_30d' => 0,
        'sales_days' => [],
        'pending_seller_rows' => [],
        'recent_orders' => [],
    ];

    if (!app_table_exists($pdo, 'marketplace_sellers')) {
        return $stats;
    }

    $stats['merchants'] = (int) $pdo->query("SELECT COUNT(*) FROM marketplace_sellers WHERE approval_status = 'approved'")->fetchColumn();
    $stats['new_merchants_30d'] = (int) $pdo->query("SELECT COUNT(*) FROM marketplace_sellers WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
    $stats['pending_sellers'] = (int) $pdo->query("SELECT COUNT(*) FROM marketplace_sellers WHERE approval_status = 'pending'")->fetchColumn();
    $stats['pending_seller_rows'] = $pdo->query("
        SELECT store_name, seller_type, created_at
        FROM marketplace_sellers
        WHERE approval_status = 'pending'
        ORDER BY created_at DESC
        LIMIT 8
    ")->fetchAll();

    if (app_table_exists($pdo, 'marketplace_listings')) {
        $stats['pending_listings'] = (int) $pdo->query("SELECT COUNT(*) FROM marketplace_listings WHERE approval_status = 'pending'")->fetchColumn();
    }

    if (app_table_exists($pdo, 'marketplace_inquiries')) {
        $registeredBuyers = (int) $pdo->query("SELECT COUNT(DISTINCT buyer_user_id) FROM marketplace_inquiries WHERE buyer_user_id IS NOT NULL")->fetchColumn();
        $publicBuyers = (int) $pdo->query("SELECT COUNT(DISTINCT buyer_email) FROM marketplace_inquiries WHERE buyer_user_id IS NULL AND buyer_email IS NOT NULL AND buyer_email <> ''")->fetchColumn();
        $stats['customers'] = $registeredBuyers + $publicBuyers;
        $newRegistered = (int) $pdo->query("SELECT COUNT(DISTINCT buyer_user_id) FROM marketplace_inquiries WHERE buyer_user_id IS NOT NULL AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
        $newPublic = (int) $pdo->query("SELECT COUNT(DISTINCT buyer_email) FROM marketplace_inquiries WHERE buyer_user_id IS NULL AND buyer_email IS NOT NULL AND buyer_email <> '' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
        $stats['new_customers_30d'] = $newRegistered + $newPublic;
    }

    if (app_table_exists($pdo, 'marketplace_orders')) {
        $stats['orders'] = (int) $pdo->query("SELECT COUNT(*) FROM marketplace_orders")->fetchColumn();
        $stats['new_orders_30d'] = (int) $pdo->query("SELECT COUNT(*) FROM marketplace_orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
        $today = $pdo->query("
            SELECT COUNT(*) orders, COALESCE(SUM(total_amount), 0) total
            FROM marketplace_orders
            WHERE DATE(created_at) = CURDATE() AND status <> 'cancelled'
        ")->fetch();
        $stats['today_orders'] = (int) ($today['orders'] ?? 0);
        $stats['today_total'] = (float) ($today['total'] ?? 0);
        $stats['recent_orders'] = $pdo->query("
            SELECT o.order_ref, o.total_amount, o.status, l.title listing_title
            FROM marketplace_orders o
            JOIN marketplace_listings l ON l.id = o.listing_id
            ORDER BY o.created_at DESC
            LIMIT 8
        ")->fetchAll();

        $rows = $pdo->query("
            SELECT DATE(created_at) order_day, COUNT(*) orders, COALESCE(SUM(total_amount), 0) total
            FROM marketplace_orders
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
            GROUP BY DATE(created_at)
        ")->fetchAll();
        $byDay = [];
        $maxTotal = 0.0;
        foreach ($rows as $row) {
            $key = (string) $row['order_day'];
            $byDay[$key] = ['orders' => (int) $row['orders'], 'total' => (float) $row['total']];
            $maxTotal = max($maxTotal, (float) $row['total']);
        }
        for ($i = 6; $i >= 0; $i--) {
            $key = date('Y-m-d', strtotime("-{$i} days"));
            $total = (float) ($byDay[$key]['total'] ?? 0);
            $stats['sales_days'][] = [
                'label' => date('M j', strtotime($key)),
                'orders' => (int) ($byDay[$key]['orders'] ?? 0),
                'total' => $total,
                'height' => $maxTotal > 0 ? (int) round(($total / $maxTotal) * 100) : 3,
            ];
        }
    }

    if (app_table_exists($pdo, 'marketplace_disputes')) {
        $stats['disputes'] = (int) $pdo->query("SELECT COUNT(*) FROM marketplace_disputes WHERE status NOT IN ('closed','resolved')")->fetchColumn();
        $stats['new_disputes_30d'] = (int) $pdo->query("SELECT COUNT(*) FROM marketplace_disputes WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
    } elseif (app_table_exists($pdo, 'messages')) {
        $stats['disputes'] = (int) $pdo->query("SELECT COUNT(*) FROM messages WHERE category = 'marketplace' AND status IN ('open','in_progress')")->fetchColumn();
        $stats['new_disputes_30d'] = (int) $pdo->query("SELECT COUNT(*) FROM messages WHERE category = 'marketplace' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
    }

    return $stats;
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

    if (app_table_exists($pdo, 'user_role_assignments')) {
        $assignedRows = $pdo->query("
            SELECT role_key, COUNT(DISTINCT user_id) AS total
            FROM user_role_assignments
            WHERE status = 'active'
            GROUP BY role_key
        ")->fetchAll();
        foreach ($assignedRows as $row) {
            $key = (string) $row['role_key'];
            if (!isset($summary[$key])) {
                $summary[$key] = ['label' => ucwords(str_replace('_', ' ', $key)), 'total' => 0];
            }
            $summary[$key]['total'] += (int) $row['total'];
        }
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

function super_admin_delete_approval_rows(PDO $pdo): array
{
    admin_ensure_action_request_schema($pdo);
    $rows = $pdo->query("
        SELECT
            aar.id,
            'generic' source,
            'Admin Action' source_label,
            aar.target_table target_type,
            COALESCE(aar.target_label, CONCAT(aar.target_table, ' #', COALESCE(aar.target_id, 0))) target_label,
            aar.reason,
            aar.created_at,
            u.name requested_by_name,
            u.email requested_by_email
        FROM admin_action_requests aar
        LEFT JOIN users u ON u.id = aar.requested_by
        WHERE aar.request_type = 'delete' AND aar.status = 'pending'
        ORDER BY aar.created_at DESC
        LIMIT 100
    ")->fetchAll();

    if (app_table_exists($pdo, 'application_delete_requests')) {
        $applicationRows = $pdo->query("
            SELECT
                adr.id,
                'application' source,
                'Application' source_label,
                'applications' target_type,
                CONCAT(COALESCE(a.app_ref, 'Application'), ' - ', COALESCE(a.name, CONCAT('#', adr.application_id))) target_label,
                adr.reason,
                adr.created_at,
                u.name requested_by_name,
                u.email requested_by_email
            FROM application_delete_requests adr
            LEFT JOIN applications a ON a.id = adr.application_id
            LEFT JOIN users u ON u.id = adr.requested_by
            WHERE adr.status = 'pending'
            ORDER BY adr.created_at DESC
            LIMIT 100
        ")->fetchAll();
        $rows = array_merge($rows, $applicationRows);
    }

    usort($rows, static fn(array $a, array $b): int => strcmp((string) $b['created_at'], (string) $a['created_at']));
    return $rows;
}

function super_admin_review_application_delete_request(PDO $pdo): void
{
    if (!admin_current_user_is_super_admin($pdo)) {
        http_response_code(403);
        exit('Forbidden: only Super Admin can review delete requests.');
    }
    $requestId = (int) ($_POST['request_id'] ?? 0);
    $decision = (string) ($_POST['decision'] ?? '');
    $stmt = $pdo->prepare("SELECT * FROM application_delete_requests WHERE id = ? AND status = 'pending' LIMIT 1");
    $stmt->execute([$requestId]);
    $request = $stmt->fetch();
    if (!$request) {
        throw new RuntimeException('Application delete request not found or already reviewed.');
    }

    $reviewedBy = admin_current_user_id($pdo);
    $pdo->beginTransaction();
    try {
        if ($decision === 'approve') {
            $appId = (int) $request['application_id'];
            $pdo->prepare("UPDATE users SET application_id = NULL WHERE application_id = ?")->execute([$appId]);
            if (app_table_exists($pdo, 'certificates')) {
                $pdo->prepare("DELETE FROM certificates WHERE application_id = ?")->execute([$appId]);
            }
            $pdo->prepare("DELETE FROM applications WHERE id = ?")->execute([$appId]);
            $pdo->prepare("UPDATE application_delete_requests SET status = 'approved', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?")
                ->execute([$reviewedBy, $requestId]);
            super_admin_audit($pdo, 'application_delete_approved', 'Approved application delete request #' . $requestId . '.');
        } else {
            $pdo->prepare("UPDATE application_delete_requests SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?")
                ->execute([$reviewedBy, $requestId]);
            super_admin_audit($pdo, 'application_delete_rejected', 'Rejected application delete request #' . $requestId . '.');
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function super_admin_export_users(PDO $pdo): void
{
    if (admin_setting($pdo, 'access_user_export_enabled', '1') !== '1') {
        http_response_code(403);
        echo 'User export is disabled.';
        exit;
    }

    $rows = $pdo->query("SELECT id, name, email, phone, role, platform_role, account_status, is_super_admin, profile_verified, two_factor_required, created_at FROM users ORDER BY id");
    app_export_csv('natcodev-users-' . date('Ymd-His') . '.csv', ['User ID', 'Name', 'Email', 'Phone', 'Role', 'Platform Role', 'Account Status', 'Is Super Admin', 'Profile Verified', 'Two Factor Required', 'Created At'], $rows);
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
    .password-field{position:relative}.password-field input{padding-right:76px}.password-toggle{position:absolute;right:8px;top:50%;transform:translateY(-50%);width:auto;margin:0;padding:7px 9px;border:0;background:#eef7f1;color:#166b41;font-size:.82rem}
    .notice{padding:12px;border-radius:6px;background:#fff3f3;color:#a32020;border:1px solid #ffd2d2}
    a{color:#166b41;font-weight:800;text-decoration:none}
  </style>
  <link rel="stylesheet" href="../assets/css/natcodev-ui.css?v=20260530">
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
    <div class="password-field">
      <input id="super_admin_password" type="password" name="password" required autofocus>
      <button class="password-toggle" type="button" data-target="super_admin_password" aria-pressed="false">Show</button>
    </div>
    <button type="submit">Enter Secure Console</button>
    <p><a href="../admin/admin.php">Return to admin</a></p>
  </form>
  <script>
    document.querySelectorAll('.password-toggle').forEach((button) => {
      button.addEventListener('click', () => {
        const input = document.getElementById(button.dataset.target || '');
        if (!input) return;
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        button.textContent = show ? 'Hide' : 'Show';
        button.setAttribute('aria-pressed', show ? 'true' : 'false');
      });
    });
  </script>
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
    .console-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px;margin:18px 0}.section-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px}.section-head.compact{align-items:center}.actions,.filters,.check-row{display:flex;align-items:center;gap:10px;flex-wrap:wrap}.filters{margin:14px 0}.filters input{min-width:240px;flex:1}.settings-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.module-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(310px,1fr));gap:14px;margin:16px 0}.module-card{border:1px solid var(--line);border-radius:8px;background:#fbfdfb;padding:14px}.module-card h3{margin:0 0 6px;color:var(--primary)}.module-card p{margin:0 0 10px;color:var(--muted);line-height:1.45}.module-card textarea{min-height:84px}.module-switch{display:flex;align-items:center;gap:10px;padding:10px 12px;border:1px solid #cfe6d8;border-radius:7px;background:#f1faf5;color:var(--green-dark);font-weight:900}.module-switch input{width:18px;height:18px;accent-color:var(--green)}.module-rbac{margin:12px 0;background:#fff}.module-rbac .compact-checks{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:4px 10px}.module-rbac label{font-size:.88rem}.training-command,.training-audiences{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin:18px 0}.training-command article,.training-audiences article{background:#fff;border:1px solid rgba(16,24,40,.09);border-left:4px solid #1f8a55;border-radius:8px;padding:15px;box-shadow:var(--shadow)}.training-command span,.training-audiences span{display:block;color:var(--green-dark);font-size:.78rem;font-weight:950;text-transform:uppercase;letter-spacing:.08em}.training-command strong,.training-audiences strong{display:block;margin:9px 0;color:var(--primary);font-size:1.08rem}.training-command p,.training-audiences p{margin:0;color:var(--muted);line-height:1.45}.audience-meta{display:flex;flex-wrap:wrap;gap:7px;margin-top:12px}.audience-meta b{display:inline-flex;border:1px solid #cfe6d8;border-radius:999px;background:#f1faf5;color:#166b41;padding:5px 8px;font-size:.78rem}.training-editor-grid{align-items:start}.training-catalog{display:grid;gap:14px;margin-top:16px}.training-audience-group{border:1px solid var(--line);border-radius:8px;background:#f8fbf9;overflow:hidden}.training-audience-group summary{display:flex;justify-content:space-between;gap:12px;padding:14px 16px;cursor:pointer;color:var(--primary);font-weight:950}.training-audience-group summary span{font-size:1.05rem}.training-audience-group summary b{color:var(--green-dark)}.training-course-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(360px,1fr));gap:14px;padding:0 14px 14px}.training-course-card{border:1px solid var(--line);border-radius:8px;background:#fff;padding:14px}.training-course-card textarea{min-height:88px}.training-course-card strong{color:var(--primary)}
    label{display:block;font-weight:800;margin:10px 0 6px} input,select,textarea{padding:11px 12px;border:1px solid var(--line);border-radius:6px;font:inherit;max-width:100%} input:not([type=checkbox]),select,textarea{width:100%} textarea{min-height:110px}.check-row label{font-weight:700}.password-field{position:relative}.password-field input{padding-right:76px}.password-toggle{position:absolute;right:8px;top:50%;transform:translateY(-50%);width:auto;margin:0;padding:7px 9px;border:0;background:#eef7f1;color:var(--green-dark);font-size:.82rem;box-shadow:none}
    button,.button{display:inline-flex;align-items:center;justify-content:center;gap:8px;background:var(--green);color:#fff;border:0;border-radius:6px;padding:11px 14px;font-weight:850;cursor:pointer;text-decoration:none;box-shadow:0 10px 24px rgba(31,138,85,.18)}button:hover,.button:hover{background:var(--green-dark);color:#fff}.secondary{background:#eef7f1!important;color:var(--green-dark)!important;border:1px solid var(--line)!important;box-shadow:none!important}.danger{background:var(--danger)!important;color:#fff!important}.create-user-panel{position:relative}.create-user-panel summary{list-style:none}.create-user-panel summary::-webkit-details-marker{display:none}.create-user-panel form{position:absolute;right:0;top:calc(100% + 10px);z-index:30;width:min(430px,calc(100vw - 44px));padding:16px;background:#fff;border:1px solid rgba(16,24,40,.12);border-radius:8px;box-shadow:0 18px 38px rgba(16,24,40,.16)}.create-user-panel h3{margin:0 0 8px;color:var(--primary)}.compact-checks{align-items:flex-start}.compact-checks label{margin:4px 0}
    .notice{padding:13px 15px;border-radius:8px;margin:16px 0;border:1px solid transparent}.notice.ok{background:#eaf8f0;color:#0f6b3c;border-color:#bfe8cf}.notice.error{background:#fff3f3;color:var(--danger);border-color:#ffd2d2}.badge{display:inline-flex;margin-top:8px;border-radius:999px;padding:5px 9px;font-size:.78rem;font-weight:850}.warning{background:#fff7df;color:#8a5a00}.muted-badge{background:#eef2f6;color:#475467}.ok-badge{background:#eaf8f0;color:#0f6b3c}.root-badge{background:#eef4ff;color:#174ea6}.role-pill{display:inline-flex;align-items:center;border:1px solid #cfe6d8;background:#f1faf5;color:var(--green-dark);border-radius:999px;padding:6px 10px;font-weight:900;font-size:.82rem}
    .table-wrap{overflow:auto} table{width:100%;border-collapse:collapse} th,td{padding:11px;border-bottom:1px solid #edf1ea;text-align:left;vertical-align:top} th{background:#eef6e9;color:#243b1d}td small{display:block;margin-top:4px}.inline-edit{display:grid;gap:8px;min-width:260px}.mini-form{margin-top:8px}.danger-zone{display:grid;gap:7px}.row-review summary{cursor:pointer;color:var(--green-dark);font-weight:900}.row-review[open]{min-width:300px}.row-actions{border-top:1px solid #edf1ea;margin-top:12px;padding-top:10px}.pagination{margin:14px 0;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:12px;background:#fff;border:1px solid rgba(16,24,40,.08);border-radius:8px}.pagination-links{display:flex;gap:10px;align-items:center}.meta,small{color:var(--muted)}
    .dr-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin-top:14px}.dr-card{border:1px solid #edf1ea;border-radius:8px;padding:14px;background:#fbfdfb}.dr-card h3{margin:0 0 10px;color:var(--primary)}.compact-list{display:grid;gap:10px;max-height:360px;overflow:auto}.compact-list article{border:1px solid var(--line);border-radius:7px;background:#fff;padding:10px}.compact-list span,.compact-list small{display:block;margin-top:4px;color:var(--muted)}.danger-text{color:var(--danger)!important}.node-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:8px}.node-actions select{width:auto;min-width:110px}.node-actions label{margin:0;font-weight:700}
    .role-summary{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;margin-top:16px}.role-summary a{display:block;padding:14px;border:1px solid var(--line);border-radius:8px;background:#f8fbf9;color:var(--ink)}.role-summary a:hover{text-decoration:none;border-color:#b7dac5;background:#f1faf5}.role-summary span{display:block;color:var(--muted);font-weight:800}.role-summary strong{display:block;margin-top:8px;color:var(--primary);font-size:1.65rem;line-height:1}
    .gov-shell{display:grid;grid-template-columns:1fr;gap:14px}.gov-grid{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:14px}.gov-card{background:#fff;border:1px solid rgba(16,24,40,.08);border-radius:8px;box-shadow:var(--shadow);padding:15px;min-width:0}.gov-card h2,.gov-card h3{margin:0;color:#0f3f22}.gov-card p{color:var(--muted);line-height:1.45;margin:6px 0 0}.gov-num{font-size:1.45rem;font-weight:950;color:#06451f}.gov-span-3{grid-column:span 3}.gov-span-4{grid-column:span 4}.gov-span-5{grid-column:span 5}.gov-span-6{grid-column:span 6}.gov-span-7{grid-column:span 7}.gov-span-8{grid-column:span 8}.gov-span-12{grid-column:span 12}.gov-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;margin-bottom:10px}.gov-kpis{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:10px}.gov-kpi{border:1px solid var(--line);border-radius:7px;background:#fbfdfb;padding:12px}.gov-kpi span{display:block;color:var(--muted);font-size:.78rem;font-weight:850}.gov-kpi strong{display:block;margin-top:7px;color:#06451f;font-size:1.35rem}.gov-table{width:100%;border-collapse:collapse;box-shadow:none;border:1px solid var(--line);border-radius:7px;overflow:hidden}.gov-table th,.gov-table td{padding:9px 10px;font-size:.86rem}.gov-table th{background:#f2f8f2;color:#12391f}.gov-mini{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}.gov-list{display:grid;gap:8px}.gov-row{display:flex;justify-content:space-between;gap:12px;border-bottom:1px solid #edf1ea;padding:8px 0}.gov-row:last-child{border-bottom:0}.gov-pill{display:inline-flex;align-items:center;gap:5px;border-radius:999px;padding:4px 8px;font-size:.74rem;font-weight:950;background:#eaf8f0;color:#0f6b3c}.gov-pill.warn{background:#fff7df;color:#8a5a00}.gov-pill.off{background:#eef2f6;color:#475467}.gov-dot{width:9px;height:9px;border-radius:50%;display:inline-block;background:#0f8a45}.gov-dot.off{background:#9ca3af}.gov-dot.warn{background:#d99a00}.gov-workflow{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:10px}.gov-step{text-align:center;border:1px solid var(--line);border-radius:8px;background:#fbfdfb;padding:12px}.gov-step i{width:42px;height:42px;border-radius:50%;display:grid;place-items:center;background:#eaf8f0;color:#0f6b3c;margin:0 auto 8px}.gov-step strong{display:block;color:#12391f;font-size:.86rem}.gov-links{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.gov-links a{display:flex;align-items:center;justify-content:center;gap:8px;border:1px solid var(--line);border-radius:7px;background:#fbfdfb;padding:10px;color:#0f6b3c;font-weight:900}.gov-links a:hover{text-decoration:none;background:#f1faf5}.perm-ok{color:#0f6b3c}.perm-warn{color:#b57900}.perm-no{color:#b42318}.perm-off{color:#667085}
    fieldset{border:1px solid var(--line);border-radius:8px;padding:12px;margin:0}legend{font-weight:900;color:var(--primary);padding:0 6px}.access-matrix{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;max-height:560px;overflow:auto;padding-right:4px}.access-matrix label{font-weight:650;margin:7px 0}.announcement-list,.audit-list{display:grid;gap:10px;max-height:390px;overflow:auto}.announcement-list article,.audit-list div{padding:11px;border:1px solid #edf1ea;border-radius:7px}.announcement-list p{margin:8px 0 0;color:var(--muted);line-height:1.5}.audit-list span,.audit-list small,.announcement-list small{display:block;margin-top:4px}
    .market-admin-shell{display:grid;grid-template-columns:220px minmax(0,1fr);gap:16px;margin-top:18px}.market-side{position:sticky;top:86px;align-self:start;display:grid;gap:5px;background:#203038;color:#dce8e7;border-radius:8px;padding:14px;box-shadow:var(--shadow)}.market-side strong{display:block;color:#fff;font-size:1.1rem;margin-bottom:8px}.market-side a{display:block;color:#dce8e7;padding:10px 11px;border-radius:6px;font-size:.9rem}.market-side a:hover,.market-side a.active{background:#111d23;color:#fff;text-decoration:none}.market-main{display:grid;gap:14px}.market-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.market-kpi{position:relative;overflow:hidden;display:block;min-height:142px;padding:18px;color:#fff;border-radius:0;box-shadow:var(--shadow)}.market-kpi:hover{text-decoration:none;color:#fff}.market-kpi::before,.market-kpi::after{content:"";position:absolute;border-radius:50%;background:rgba(255,255,255,.16)}.market-kpi::before{width:126px;height:126px;right:44px;top:8px}.market-kpi::after{width:94px;height:94px;right:-14px;bottom:-16px}.market-kpi span{display:block;text-transform:uppercase;font-weight:900;font-size:.85rem;opacity:.96}.market-kpi strong{display:block;margin:16px 0 12px;font-size:2rem;line-height:1}.market-kpi small{position:relative;z-index:1;color:#fff;font-weight:800}.market-kpi.coral{background:#f56d4b}.market-kpi.blue{background:#087dcc}.market-kpi.purple{background:#7f48c6}.market-kpi.teal{background:#139b8d}.market-alerts{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:0}.market-alert{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:5px;align-items:center;padding:16px 18px;color:#fff}.market-alert:hover{text-decoration:none;color:#fff}.market-alert strong{font-size:.88rem;text-transform:uppercase}.market-alert span{font-size:1.65rem;font-weight:950}.market-alert small{grid-column:1/-1;color:#fff;font-weight:800}.market-alert.orange{background:#f39b08}.market-alert.cyan{background:#12b5d3}.market-alert.red{background:#df4739}.market-panel{background:#fff;border:1px solid rgba(16,24,40,.1);border-radius:8px;box-shadow:var(--shadow);padding:17px}.market-panel h2{margin:0 0 4px;color:var(--primary)}.market-panel p{margin:0;color:var(--muted)}.market-graph{height:330px;margin-top:18px;border-left:1px solid var(--line);border-bottom:1px solid var(--line);background:repeating-linear-gradient(to top,#fff 0,#fff 63px,#eef2f4 64px);display:grid;grid-template-columns:repeat(7,1fr);align-items:end;gap:18px;padding:18px 18px 0}.market-bar{height:100%;display:grid;align-items:end;text-align:center;color:var(--muted);font-size:.8rem}.market-bar i{display:block;width:100%;height:var(--h);min-height:10px;background:linear-gradient(180deg,rgba(57,107,255,.78),rgba(57,107,255,.24));border:1px solid rgba(57,107,255,.45);border-bottom:0}.market-bar span{align-self:start;min-height:22px;font-weight:800;color:#475467}.market-bar small{display:block;margin-top:8px;padding-bottom:8px}.market-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.market-list{display:grid;gap:9px;margin-top:12px}.market-list a{display:block;border:1px solid var(--line);border-radius:7px;padding:10px;color:var(--ink);background:#fbfdfb}.market-list a:hover{text-decoration:none;background:#f1faf5}.market-list strong,.market-list span{display:block}.market-list span{margin-top:4px;color:var(--muted);font-size:.88rem}
    @media(max-width:1100px){.super-dashboard{grid-template-columns:repeat(2,minmax(0,1fr))}.readiness-grid,.training-command,.training-audiences{grid-template-columns:1fr}}
    @media(max-width:900px){.console-grid,.dr-grid,.market-admin-shell,.market-grid{grid-template-columns:1fr}.hero,.section-head,.bar{flex-direction:column;align-items:stretch}.super-nav{justify-content:flex-start;overflow-x:auto;padding-bottom:4px}.super-nav details{position:static}.super-menu{left:22px;right:22px;width:auto}.settings-grid,.access-matrix{grid-template-columns:1fr}.market-side{position:static}.market-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}.market-alerts{grid-template-columns:1fr}.market-graph{gap:8px}.training-course-grid{grid-template-columns:1fr}}
    @media(max-width:620px){.super-dashboard,.market-kpis{grid-template-columns:1fr}.market-graph{height:260px}}
  </style>
  <link rel="stylesheet" href="../assets/css/natcodev-ui.css?v=20260530">
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
