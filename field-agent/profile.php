<?php
declare(strict_types=1);
require_once __DIR__ . '/_field.php';
$pdo = fa_pdo(); $user = fa_require_user($pdo);
fa_header('Field Agent Profile', 'Manage your field identity and account settings.', $user, 'profile');
?>
<section class="fa-grid"><article class="fa-card fa-panel span-7"><div class="fa-panel-head"><h2>Profile</h2><a class="btn soft" href="../dashboard/profile.php"><i data-lucide="edit"></i> Edit Account</a></div><div class="fa-row"><img class="thumb" src="<?= e(fa_avatar($user)) ?>" alt=""><div><strong><?= e((string) $user['name']) ?></strong><br><span class="muted"><?= e((string) $user['email']) ?></span></div><span class="badge good">Field Agent</span></div></article><aside class="fa-card fa-panel span-5"><h2>Account Actions</h2><p><a class="btn" href="../dashboard/change-password.php"><i data-lucide="lock"></i> Change Password</a></p><p><a class="btn secondary" href="../dashboard/logout.php"><i data-lucide="log-out"></i> Logout</a></p></aside></section>
<?php fa_footer(); ?>
