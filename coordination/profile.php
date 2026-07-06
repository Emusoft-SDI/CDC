<?php
declare(strict_types=1);
require_once __DIR__ . '/_coordination.php';
require_once __DIR__ . '/../lib/workspace-account.php';

$pdo = coord_pdo();
$user = coord_require($pdo);
$msg = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Security session expired. Refresh and try again.';
    } else {
        try {
            $action = (string) ($_POST['action'] ?? 'account_profile');
            if ($action === 'account_password') {
                workspace_account_change_password($pdo, (int) $user['id'], (string) ($_POST['current_password'] ?? ''), (string) ($_POST['new_password'] ?? ''), (string) ($_POST['confirm_password'] ?? ''));
                $msg = 'Password changed.';
            } else {
                workspace_account_update_profile($pdo, (int) $user['id'], $_POST);
                $msg = 'Profile updated.';
            }
            $user = coord_require($pdo);
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}
$scope = coord_state_filter($pdo, $user);
$wallet = wallet_get_or_create($pdo, (int) $user['id']);
coord_header('Coordinator Profile & Security', 'Manage leadership identity, contact details, password, scope, account settings, and safe exit.', $user, 'profile');
?>
<?php if ($msg): ?><div class="badge" style="margin-bottom:12px"><?= e($msg) ?></div><?php endif; ?>
<?php if ($error): ?><div class="badge" style="margin-bottom:12px;background:#fff1f2;color:#b42318"><?= e($error) ?></div><?php endif; ?>
<div class="kpis"><div class="kpi"><i class="fas fa-crown"></i><b><?= e(coord_role_label(coord_role_key($user))) ?></b><span>Leadership channel</span></div><div class="kpi"><i class="fas fa-location-dot"></i><b><?= e((string) $scope['label']) ?></b><span>Operating scope</span></div><div class="kpi"><i class="fas fa-wallet"></i><b>NGN <?= e(number_format((float) ($wallet['balance'] ?? 0), 2)) ?></b><span>Wallet balance</span></div><div class="kpi"><i class="fas fa-user-shield"></i><b><?= e((string) ($user['account_status'] ?? 'active')) ?></b><span>Account status</span></div><div class="kpi"><i class="fas fa-envelope"></i><b><?= e((string) ($user['email'] ?? '')) ?></b><span>Email</span></div></div>
<div class="grid">
  <?php workspace_account_render_profile_forms($user, 'coordination', 'Account Profile', 'Change Password'); ?>
  <section class="card span-12"><h2>Workspace Exit</h2><p><a class="btn" href="<?= e(coord_home(coord_role_key($user))) ?>">Back to Workspace</a> <a class="btn light" href="../index.php">NATCODEV Home</a> <a class="btn light" href="logout.php">Logout</a></p></section>
</div>
<?php coord_footer(); ?>