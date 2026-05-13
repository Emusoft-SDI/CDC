<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/dashboard-layout.php';

session_start();
$pdo = db();
if (!current_user($pdo)) {
    redirect_to('login.php');
}
?>
<?php dashboard_page_start('Pricing', ['active' => 'pricing.php', 'description' => 'Review premium services and upgrade options when enabled.', 'wide' => true]); ?>
  <section class="card">
    <h2>Premium Services</h2>
    <p class="muted">Premium plan upgrades will be available from your wallet when enabled by NATCODEV.</p>
    <a class="button secondary" href="wallet.php">Open Wallet</a>
  </section>
<?php dashboard_page_end(); ?>
