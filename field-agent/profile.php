<?php
declare(strict_types=1);
require_once __DIR__ . '/_field.php';
require_once __DIR__ . '/../lib/workspace-account.php';

$pdo = fa_pdo();
$user = fa_require_user($pdo);
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
            $user = fa_require_user($pdo);
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

fa_header(fa_role_label(fa_role_key($user)) . ' Profile', 'Manage your field identity, contact details, password, account settings, and safe exit.', $user, 'profile');
?>
<?php if ($msg): ?><div class="badge good" style="margin-bottom:12px"><?= e($msg) ?></div><?php endif; ?>
<?php if ($error): ?><div class="badge danger" style="margin-bottom:12px"><?= e($error) ?></div><?php endif; ?>
<section class="fa-grid">
  <?php workspace_account_render_profile_forms($user, 'field', 'Account Profile', 'Change Password'); ?>
  <article class="fa-card fa-panel span-12">
    <div class="fa-panel-head"><h2>Workspace Exit</h2><span class="badge good">NATCODEV parent site</span></div>
    <p><a class="btn" href="<?= e(fa_role_home(fa_role_key($user))) ?>"><i data-lucide="layout-dashboard"></i> Back to Workspace</a> <a class="btn secondary" href="../index.php"><i data-lucide="home"></i> NATCODEV Home</a> <a class="btn secondary" href="logout.php"><i data-lucide="log-out"></i> Logout</a></p>
  </article>
</section>
<?php fa_footer(); ?>