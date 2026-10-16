<?php
require_once '_user_auth.php';
require_once '../config.php';
require_once '../lib/dashboard-layout.php';

$pdo = db();
$user = current_user($pdo);
if (!$user) {
    redirect_to('login.php');
}

// Premium gate: users.plan is maintained by api/upgrade-plan.php (current_user() does not select it).
$isPremium = false;
if (app_column_exists($pdo, 'users', 'plan')) {
    $planStmt = $pdo->prepare('SELECT plan FROM users WHERE id = ? LIMIT 1');
    $planStmt->execute([(int) $user['id']]);
    $isPremium = (string) $planStmt->fetchColumn() === 'premium';
}
dashboard_page_start('Agricultural Inputs & Loans', ['description' => 'Access farm inputs and cooperative financing.']);
?>
<?php if (!$isPremium): ?>
<div class="card" style="text-align: center; padding: 40px 20px; max-width: 600px; margin: 0 auto;">
    <h2>Loans are part of the Premium plan</h2>
    <p class="muted" style="margin-bottom: 30px; font-size: 16px;">
        Farm input loans, grants, and savings through the Coconut Farmers Cooperative are a Premium plan benefit. Upgrade your account to unlock the cooperative portal.
    </p>
    <div class="actions" style="justify-content: center;">
        <a href="pricing.php" class="button" style="display: inline-block; padding: 12px 24px; font-size: 16px;">View Premium Plan</a>
        <a href="wallet.php" class="button secondary" style="display: inline-block; padding: 12px 24px; font-size: 16px;">Open Wallet</a>
    </div>
</div>
<?php else: ?>
<div class="card" style="text-align: center; padding: 40px 20px; max-width: 600px; margin: 0 auto;">
    <img src="../assets/cfc_logo.png" alt="CFC Logo" style="max-height: 80px; margin-bottom: 20px;">
    <h2>Coconut Farmers Cooperative</h2>
    <p class="muted" style="margin-bottom: 30px; font-size: 16px;">
        All financial services, including farm input loans, grants, and savings, are managed exclusively through our dedicated cooperative platform.
    </p>
    <a href="https://cfc.natcodev.com.ng" target="_blank" class="button" style="display: inline-block; padding: 12px 24px; font-size: 16px;">
        Go to Cooperative Portal
    </a>
</div>
<?php endif; ?>

<?php dashboard_page_end(); ?>
