<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/dashboard-layout.php';

session_start();
$pdo = db();
app_ensure_farmer_engagement_schema($pdo);

if (empty($_SESSION['user_id'])) {
    redirect_to('login.php');
}

$items = [];
if (app_table_exists($pdo, 'marketplace_items')) {
    $items = $pdo->query("
        SELECT m.*, u.name seller_name
        FROM marketplace_items m
        LEFT JOIN users u ON m.seller_id = u.id
        WHERE m.is_active = 1
        ORDER BY m.created_at DESC
        LIMIT 100
    ")->fetchAll();
}
?>
<?php dashboard_page_start('Marketplace', ['active' => 'marketplace.php', 'description' => 'Browse inputs, services, and offers published for growers.', 'wide' => true]); ?>
<h1>Marketplace</h1>
    <div class="grid">
      <?php foreach ($items as $item): ?>
        <article class="card">
          <h2><?= e($item['title']) ?></h2>
          <p><?= e(substr((string) $item['description'], 0, 160)) ?></p>
          <p class="price">NGN <?= e(number_format((float) $item['price'], 2)) ?></p>
          <p>Seller: <?= (int) ($item['seller_id'] ?? 0) === 0 ? 'NATCODEV' : e($item['seller_name']) ?></p>
          <a class="button" href="inbox.php?topic=marketplace">Ask About This</a>
        </article>
      <?php endforeach; ?>
      <?php if (!$items): ?><div class="card">Marketplace items will appear here when published.</div><?php endif; ?>
    </div>
  <?php dashboard_page_end(); ?>
