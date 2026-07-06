<?php
declare(strict_types=1);

require_once __DIR__ . '/_user_auth.php';
require_once __DIR__ . '/../lib/dashboard-layout.php';

$pdo = db();
if (!current_user($pdo)) {
    redirect_to('login.php');
}
?>
<?php dashboard_page_start('Healthcare', ['active' => 'healthcare.php', 'description' => 'Access health-related grower services when enabled.', 'wide' => true]); ?>
  <section class="card">
    <h2>Service Status</h2>
    <p class="muted">Healthcare services are not enabled yet. When they are available, enrollment and support options will appear here.</p>
    <a class="button secondary" href="inbox.php?topic=general">Ask Support</a>
  </section>
<?php dashboard_page_end(); ?>
