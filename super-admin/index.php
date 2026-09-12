<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/admin-layout.php';
require_once __DIR__ . '/../lib/disaster-recovery.php';
require_once __DIR__ . '/../lib/otp-delivery.php';
require_once __DIR__ . '/../lib/workspace-account.php';
require_once __DIR__ . '/../lib/super-admin-console.php';

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
            $otpStart = otp_begin_email_login_challenge($pdo, $superUser, 'super-admin/index.php');
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
