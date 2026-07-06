<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/admin-layout.php';
require_once __DIR__ . '/../lib/disaster-recovery.php';
require_once __DIR__ . '/../lib/otp-delivery.php';
require_once __DIR__ . '/../lib/workspace-account.php';

const SUPER_ADMIN_SCHEMA_VERSION = '20260513-3';

session_start();

$message = '';
$error = '';
$roles = super_admin_roles();
$statuses = super_admin_statuses();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'logout') {
    if (verify_csrf($_POST['_csrf'] ?? null)) {
        super_admin_logout();
    }
    $error = 'Invalid security token.';
}

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
    } elseif (!app_check_rate_limit('super_admin_login', 5, 900)) {
        $error = 'Too many Super Admin login attempts. Please try again in 15 minutes.';
    } elseif (super_admin_password_is_valid((string) ($_POST['password'] ?? ''))) {
        unset($_SESSION['super_admin_authenticated'], $_SESSION['super_admin_user_id'], $_SESSION['super_admin_login_audited']);
        $stmt = $pdo->query("SELECT id, name, email, password, role, platform_role, account_status, email_verified_at FROM users WHERE is_super_admin = 1 AND account_status = 'active' AND email <> '' AND email_verified_at IS NOT NULL ORDER BY id LIMIT 1");
        $superUser = $stmt ? $stmt->fetch() : null;
        if (is_array($superUser)) {
            $otpStart = otp_begin_email_login_challenge($pdo, $superUser, 'admin/admin.php');
            if ($otpStart['ok']) {
                redirect_to('../verify-otp.php');
            }
            $error = (string) $otpStart['message'];
        } else {
            $error = 'Create an active, email-verified user-backed super admin account before entering the Super Admin console.';
        }
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'export_users') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        http_response_code(403);
        exit('Invalid security token.');
    }
    super_admin_export_users($pdo);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !in_array((string) ($_POST['action'] ?? ''), ['login', 'logout'], true)) {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid security token.';
    } else {
        try {
            $action = (string) ($_POST['action'] ?? '');
            if ($action === 'super_profile') {
                super_admin_save_self_profile($pdo);
                $message = 'Super admin profile updated.';
            } elseif ($action === 'super_password') {
                super_admin_change_self_password($pdo);
                $message = 'Super admin password changed.';
            } elseif ($action === 'create_user') {
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
            } elseif ($action === 'review_certificate_revocation') {
                $message = super_admin_review_certificate_revocation($pdo);
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
$stats = super_admin_stats($pdo);

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

<?php
define('NATCODEV_SUPER_ADMIN', true);
if (in_array($view, ['disaster', 'profile', 'overview', 'users', 'controls'], true)) {
    require __DIR__ . '/modules/' . $view . '.php';
}
?>

<?php super_admin_page_end(); ?>

<?php
function super_admin_logout(): void
{
    unset(
        $_SESSION['super_admin_authenticated'],
        $_SESSION['super_admin_user_id'],
        $_SESSION['super_admin_login_audited'],
        $_SESSION['super_admin_schema_version'],
        $_SESSION['login_otp_pending'],
        $_SESSION['login_otp_user_id'],
        $_SESSION['login_otp_email'],
        $_SESSION['otp_next_destination']
    );
    redirect_to('index.php');
}

function super_admin_current_user(PDO $pdo): ?array
{
    $userId = (int) ($_SESSION['super_admin_user_id'] ?? $_SESSION['user_id'] ?? 0);
    if ($userId <= 0) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? AND is_super_admin = 1 LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    return is_array($user) ? $user : null;
}

function super_admin_save_self_profile(PDO $pdo): void
{
    $user = super_admin_current_user($pdo);
    if (!$user) {
        throw new RuntimeException('A user-backed super admin account is required to update this profile.');
    }
    workspace_account_update_profile($pdo, (int) $user['id'], $_POST);
    super_admin_audit($pdo, 'super_admin_profile_updated', 'Updated own super admin profile.');
}

function super_admin_change_self_password(PDO $pdo): void
{
    $user = super_admin_current_user($pdo);
    if (!$user) {
        throw new RuntimeException('A user-backed super admin account is required to change this password.');
    }
    workspace_account_change_password(
        $pdo,
        (int) $user['id'],
        (string) ($_POST['current_password'] ?? ''),
        (string) ($_POST['new_password'] ?? ''),
        (string) ($_POST['confirm_password'] ?? '')
    );
    super_admin_audit($pdo, 'super_admin_password_changed', 'Changed own super admin password.');
}
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
        'profile' => [
            'label' => 'My Profile',
            'hint' => 'Own profile, password, and secure exit',
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
            [
                'label' => 'My Profile',
                'hint' => 'Profile, password, and secure logout',
                'href' => 'index.php?view=profile',
                'view' => 'profile',
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
        'profile' => [
            'title' => 'My Profile',
            'description' => 'Manage your own super admin profile, password, and secure exit.',
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
        'applications' => ['entry' => '../admin/registry/index.php', 'surface' => 'Admin console', 'owner' => 'Registry Operations', 'purpose' => 'Application review, confirmation, exports, and grower onboarding.', 'setup' => 'Define review statuses, confirmation workflow, and export policy.'],
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

    if (app_is_production()) {
        error_log('SUPER_ADMIN_PASSWORD_HASH is required for production super admin login.');
        return false;
    }

    $plain = app_env('SUPER_ADMIN_PASSWORD', app_env('ADMIN_PASSWORD', ''));
    return $plain !== null && $plain !== '' && hash_equals($plain, $password);
}

function super_admin_is_authorized(PDO $pdo): bool
{
    if (empty($_SESSION['super_admin_authenticated']) || !app_column_exists($pdo, 'users', 'is_super_admin')) {
        unset($_SESSION['super_admin_authenticated'], $_SESSION['super_admin_user_id']);
        return false;
    }

    $userId = (int) ($_SESSION['super_admin_user_id'] ?? $_SESSION['user_id'] ?? 0);
    if ($userId <= 0) {
        unset($_SESSION['super_admin_authenticated'], $_SESSION['super_admin_user_id']);
        return false;
    }

    $stmt = $pdo->prepare("SELECT id, is_super_admin, account_status FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if ($user && (int) $user['is_super_admin'] === 1 && strtolower((string) ($user['account_status'] ?? 'active')) === 'active') {
        $_SESSION['user_id'] = $userId;
        $_SESSION['super_admin_user_id'] = $userId;
        return true;
    }

    unset($_SESSION['super_admin_authenticated'], $_SESSION['super_admin_user_id']);
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

function super_admin_review_certificate_revocation(PDO $pdo): string
{
    admin_ensure_action_request_schema($pdo);
    $requestId = (int) ($_POST['request_id'] ?? 0);
    $decision = (string) ($_POST['decision'] ?? '');
    $note = trim((string) ($_POST['review_note'] ?? ''));
    if ($requestId <= 0 || !in_array($decision, ['approve', 'reject'], true)) {
        throw new RuntimeException('Select a valid revocation request and decision.');
    }

    $stmt = $pdo->prepare("SELECT ar.*, c.certificate_ref, c.status certificate_status FROM admin_action_requests ar LEFT JOIN certificates c ON c.id = ar.target_id WHERE ar.id = ? AND ar.request_type = 'revoke_certificate' AND ar.target_table = 'certificates' AND ar.status = 'pending' LIMIT 1");
    $stmt->execute([$requestId]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$request) {
        throw new RuntimeException('Revocation request was not found or has already been reviewed.');
    }

    $reviewedBy = $_SESSION['super_admin_user_id'] ?? admin_current_user_id($pdo);
    if ($decision === 'reject') {
        $pdo->prepare("UPDATE admin_action_requests SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW(), review_note = ? WHERE id = ?")
            ->execute([$reviewedBy, $note !== '' ? $note : 'Rejected by Super Admin.', $requestId]);
        super_admin_audit($pdo, 'certificate_revocation_rejected', 'Rejected certificate revocation request for ' . (string) ($request['certificate_ref'] ?? ('#' . $request['target_id'])) . '.');
        return 'Certificate revocation request rejected.';
    }

    $payload = json_decode((string) ($request['payload_json'] ?? ''), true);
    $reason = trim((string) ($payload['reason'] ?? $request['reason'] ?? 'Super Admin approved certificate revocation.'));

    $pdo->beginTransaction();
    try {
        $update = $pdo->prepare("UPDATE certificates SET status = 'revoked', revoked_at = NOW(), revoked_reason = ? WHERE id = ? AND status = 'issued'");
        $update->execute([$reason, (int) $request['target_id']]);
        if ($update->rowCount() !== 1) {
            throw new RuntimeException('Certificate is no longer issued or could not be revoked.');
        }
        $pdo->prepare("UPDATE admin_action_requests SET status = 'approved', reviewed_by = ?, reviewed_at = NOW(), review_note = ? WHERE id = ?")
            ->execute([$reviewedBy, $note !== '' ? $note : 'Approved by Super Admin.', $requestId]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    super_admin_audit($pdo, 'certificate_revoked', 'Approved and revoked certificate ' . (string) ($request['certificate_ref'] ?? ('#' . $request['target_id'])) . '.');
    return 'Certificate revoked after Super Admin approval.';
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
    if (function_exists('admin_audit')) {
        admin_audit($pdo, $action, $description);
        return;
    }
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
    fputcsv($out, app_csv_row(['id', 'name', 'email', 'phone', 'role', 'platform_role', 'account_status', 'is_super_admin', 'profile_verified', 'two_factor_required', 'created_at']));
    $rows = $pdo->query("SELECT id, name, email, phone, role, platform_role, account_status, is_super_admin, profile_verified, two_factor_required, created_at FROM users ORDER BY id");
    foreach ($rows ?: [] as $row) {
        fputcsv($out, app_csv_row(array_values($row)));
    }
    super_admin_audit($pdo, 'users_exported', 'Exported users CSV from Super Admin console.');
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
    <img src="<?= e(app_admin_logo_url()) ?>" alt="NATCODEV">
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
      <a class="brand" href="index.php"><img src="<?= e(app_admin_logo_url()) ?>" alt="NATCODEV"><span>NATCODEV Super Admin</span></a>
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
      <nav class="header-actions"><a href="../admin/admin.php">Admin Console</a><a href="index.php?view=profile">My Profile</a><form method="post"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="logout"><button class="secondary" type="submit">Logout</button></form></nav>
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
