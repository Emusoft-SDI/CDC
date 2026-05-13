<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/dashboard-layout.php';

session_start();
$pdo = db();
if (!current_user($pdo)) {
    redirect_to('login.php');
}

$webinars = [];
if (app_table_exists($pdo, 'webinars')) {
    $webinars = $pdo->query("SELECT title, description, start_time, is_free, price FROM webinars ORDER BY start_time DESC LIMIT 50")->fetchAll();
}
?>
<?php dashboard_page_start('Training & Webinars', ['active' => 'webinars.php', 'description' => 'Review published training sessions and learning opportunities.', 'wide' => true]); ?>
  <section class="grid">
    <?php foreach ($webinars as $webinar): ?>
      <article class="card">
        <h2><?= e($webinar['title']) ?></h2>
        <p><?= e($webinar['description'] ?? '') ?></p>
        <small class="muted"><?= e($webinar['start_time'] ?? '') ?> / <?= (int) ($webinar['is_free'] ?? 0) === 1 ? 'Free' : 'NGN ' . e(number_format((float) ($webinar['price'] ?? 0), 2)) ?></small>
      </article>
    <?php endforeach; ?>
    <?php if (!$webinars): ?><div class="card">No webinars are published yet.</div><?php endif; ?>
  </section>
<?php dashboard_page_end(); ?>
