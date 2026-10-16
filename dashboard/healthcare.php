<?php
declare(strict_types=1);

require_once __DIR__ . '/_user_auth.php';
require_once __DIR__ . '/../lib/dashboard-layout.php';

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
?>
<?php dashboard_page_start('Healthcare', ['active' => 'healthcare.php', 'description' => 'Access health-related grower services when enabled.', 'wide' => true]); ?>
<?php if (!$isPremium): ?>
  <section class="card" style="text-align:center; padding:40px 20px; max-width:600px; margin:0 auto;">
    <h2>Healthcare is part of the Premium plan</h2>
    <p class="muted" style="margin-bottom:30px; font-size:16px;">
        Healthcare enrollment and support services are a Premium plan benefit. Upgrade your account to activate this surface.
    </p>
    <div class="actions" style="justify-content:center;">
      <a class="button" href="pricing.php" style="padding:12px 24px; font-size:16px;">View Premium Plan</a>
      <a class="button secondary" href="wallet.php" style="padding:12px 24px; font-size:16px;">Open Wallet</a>
    </div>
  </section>
<?php else: ?>
  <section class="card">
    <h2>Service Status</h2>
    <p class="muted">Healthcare services are not enabled yet. When they are available, enrollment and support options will appear here.</p>
    <a class="button secondary" href="inbox.php?topic=general">Ask Support</a>
  </section>
<?php endif; ?>
<?php dashboard_page_end(); ?>
