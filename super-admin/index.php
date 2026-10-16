<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/admin-layout.php';
require_once __DIR__ . '/../lib/disaster-recovery.php';
require_once __DIR__ . '/../lib/otp-delivery.php';
require_once __DIR__ . '/../lib/workspace-account.php';
require_once __DIR__ . '/../lib/super-admin-console.php';
require_once __DIR__ . '/../lib/wallet-dual-control.php';

const SUPER_ADMIN_SCHEMA_VERSION = '20261005-1';

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

if (isset($_GET['logout']) && verify_csrf((string) ($_GET['_csrf'] ?? ''))) {
    super_admin_logout();
}

if (empty($_SESSION['super_admin_authenticated']) && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid security token.';
    } elseif (!app_check_rate_limit('super_admin_login', 5, 900)) {
        $error = 'Too many Super Admin login attempts. Please try again in 15 minutes.';
    } elseif (super_admin_password_is_valid((string) ($_POST['password'] ?? ''))) {
        unset($_SESSION['super_admin_authenticated'], $_SESSION['super_admin_user_id'], $_SESSION['super_admin_login_audited']);
        $accountEmail = filter_var(trim((string) ($_POST['account_email'] ?? '')), FILTER_VALIDATE_EMAIL);
        if ($accountEmail === false || $accountEmail === '') {
            $error = 'Enter the email address of the super admin account you are signing into.';
        } else {
            $stmt = $pdo->prepare("SELECT id, name, email, password, role, platform_role, account_status, email_verified_at FROM users WHERE email = ? AND is_super_admin = 1 AND account_status = 'active' AND email <> '' AND email_verified_at IS NOT NULL LIMIT 1");
            $stmt->execute([$accountEmail]);
            $superUser = $stmt->fetch();
            if (is_array($superUser)) {
                $otpStart = otp_begin_email_login_challenge($pdo, $superUser, 'super-admin/index.php');
                if ($otpStart['ok']) {
                    redirect_to('../verify-otp.php');
                }
                $error = (string) $otpStart['message'];
            } else {
                $error = 'No active, email-verified super admin account exists for that email address.';
            }
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
            } elseif ($action === 'force_logout_user') {
                super_admin_force_logout_user($pdo);
                $message = 'All active sessions for the selected user were signed out.';
            } elseif ($action === 'unlock_user') {
                super_admin_unlock_user($pdo);
                $message = 'User profile unlocked and set to active.';
            } elseif ($action === 'save_controls') {
                super_admin_save_controls($pdo);
                $message = 'System announcement and security controls saved.';
            } elseif ($action === 'save_secrets') {
                super_admin_save_secrets($pdo);
                $message = 'Integration secrets updated.';
            } elseif ($action === 'restore_deleted_record') {
                super_admin_restore_deleted_record($pdo);
                $message = 'Deleted record restored from the recycle bin.';
            } elseif ($action === 'review_certificate_revocation') {
                $message = super_admin_review_certificate_revocation($pdo);
            } elseif ($action === 'review_certificate_request') {
                $message = super_admin_review_certificate_request($pdo);
            } elseif ($action === 'save_wallet_controls') {
                super_admin_save_wallet_controls($pdo);
                $message = 'Wallet dual-control policy saved.';
            } elseif ($action === 'save_wallet_automation_policy') {
                require_once __DIR__ . '/../lib/wallet-withdrawals.php';
                $currentUser = current_user($pdo) ?: ['id' => (int) ($_SESSION['super_admin_user_id'] ?? 0)];
                $currentUser['is_super_admin'] = 1;
                wallet_save_withdrawal_policy($pdo, $_POST, $currentUser);
                super_admin_audit($pdo, 'wallet_automation_policy_saved', 'Super Admin updated withdrawal automation rules, pre-flight buffer, global daily cap, charges, and circuit breaker settings.');
                $message = 'Withdrawal automation, pre-flight buffer, global daily cap, fee schedule, and circuit breaker settings saved.';
            } elseif ($action === 'reset_circuit_breaker') {
                require_once __DIR__ . '/../lib/circuit-breaker.php';
                $provider = trim((string) ($_POST['provider'] ?? ''));
                $adminId = (int) ($_SESSION['super_admin_user_id'] ?? 0);
                if ($provider === '__all__') {
                    circuit_breaker_reset_all($pdo, $adminId);
                    $message = 'All payment gateway circuit breakers have been reset to CLOSED.';
                } elseif ($provider !== '') {
                    circuit_breaker_reset($pdo, $provider, $adminId);
                    $message = 'Circuit breaker for [' . htmlspecialchars(ucwords($provider)) . '] reset to CLOSED.';
                } else {
                    throw new RuntimeException('Invalid gateway provider specified.');
                }
            } elseif ($action === 'review_wallet_credit') {
                $message = wallet_review_credit_approval($pdo);
            } elseif ($action === 'review_withdrawal_approval') {
                $message = wallet_review_withdrawal_approval($pdo);
            } elseif ($action === 'review_payout_approval') {
                $message = wallet_review_payout_approval($pdo);
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
            } elseif ($action === 'update_announcement') {
                super_admin_update_announcement($pdo, $roles);
                $message = 'Announcement updated.';
            } elseif ($action === 'delete_announcement') {
                super_admin_delete_announcement($pdo);
                $message = 'Announcement deleted.';
            } elseif ($action === 'academy_approve_reco') {
                require_once __DIR__ . '/../lib/academy/sponsorship-admin.php';
                academy_admin_recommendation_update_status($pdo, (int) $_POST['id'], 'approved', (int) ($_SESSION['super_admin_user_id'] ?? 0));
                $message = 'Recommendation approved.';
            } elseif ($action === 'academy_reject_reco') {
                require_once __DIR__ . '/../lib/academy/sponsorship-admin.php';
                academy_admin_recommendation_update_status($pdo, (int) $_POST['id'], 'rejected', (int) ($_SESSION['super_admin_user_id'] ?? 0));
                $message = 'Recommendation rejected.';
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
                super_admin_audit($pdo, 'backup_manifest_created', 'Created backup manifest ' . $backup['backup_ref'] . '.');
                $message = 'Backup manifest created: ' . $backup['backup_ref'] . ' at ' . $backup['path'];
            } elseif ($action === 'queue_sync_ping') {
                dr_queue_sync_event($pdo, 'health_ping', ['queued_by' => 'super_admin', 'queued_at' => date('c')], trim((string) ($_POST['target_node'] ?? '')) ?: null);
                super_admin_audit($pdo, 'sync_ping_queued', 'Queued multisite health ping.');
                $message = 'Health ping queued for multisite sync.';
            } elseif ($action === 'export_users') {
                super_admin_export_users($pdo);
            } elseif ($action === 'export_audit') {
                super_admin_export_audit_log($pdo);
            } elseif ($action === 'cooperative_suspend' || $action === 'cooperative_reactivate') {
                $cooperativeId = (int) ($_POST['cooperative_id'] ?? 0);
                $cooperative = cooperative_find($pdo, $cooperativeId);
                if ($cooperative === null) {
                    throw new RuntimeException('Cooperative not found.');
                }
                $nextStatus = $action === 'cooperative_suspend' ? 'suspended' : 'active';
                $previousStatus = (string) ($cooperative['status'] ?: 'pending');
                $pdo->prepare('UPDATE cooperatives SET status = ? WHERE id = ?')->execute([$nextStatus, $cooperativeId]);
                if (function_exists('admin_audit_event')) {
                    admin_audit_event(
                        $pdo,
                        $action === 'cooperative_suspend' ? 'cooperative_suspended' : 'cooperative_reactivated',
                        'cooperative',
                        $cooperativeId,
                        ['status' => $previousStatus],
                        ['status' => $nextStatus],
                        'success',
                        ($action === 'cooperative_suspend' ? 'Suspended' : 'Reactivated') . ' cooperative ' . (string) ($cooperative['name'] ?: '#' . $cooperativeId)
                    );
                }
                $message = 'Cooperative ' . ($action === 'cooperative_suspend' ? 'suspended.' : 'reactivated.');
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

<?php if (!in_array($view, ['controls', 'disaster'], true)): ?>
<section class="stats">
  <div class="stat"><span>Total Users</span><strong><?= (int) $stats['total_users'] ?></strong></div>
  <div class="stat"><span>Privileged Profiles</span><strong><?= (int) $stats['privileged'] ?></strong></div>
  <div class="stat"><span>Super Admins</span><strong><?= (int) $stats['super_admins'] ?></strong></div>
  <div class="stat"><span>Suspended</span><strong><?= (int) $stats['suspended'] ?></strong></div>
  <div class="stat"><span>Archived</span><strong><?= (int) $stats['archived'] ?></strong></div>
</section>
<?php endif; ?>

<?php
define('NATCODEV_SUPER_ADMIN', true);
if (in_array($view, ['disaster', 'profile', 'overview', 'users', 'controls', 'finance', 'cooperatives'], true)) {
    require __DIR__ . '/modules/' . $view . '.php';
}
?>

<?php super_admin_page_end(); ?>

<?php
