<?php
declare(strict_types=1);
require_once __DIR__ . '/_buyer.php';

$pdo = buyer_boot();
$user = buyer_require($pdo);
$q = trim((string) ($_GET['q'] ?? ''));
$like = '%' . $q . '%';
$results = [];

if ($q !== '') {
    $stmt = $pdo->prepare("
        SELECT 'Order' type, checkout_ref title, CONCAT('Status: ', status, ' / Payment: ', payment_status) description, CONCAT('orders.php?checkout_ref=', checkout_ref) href
        FROM marketplace_orders
        WHERE buyer_user_id = ? AND (checkout_ref LIKE ? OR order_ref LIKE ? OR delivery_status LIKE ? OR status LIKE ?)
        GROUP BY checkout_ref, status, payment_status
        ORDER BY MAX(created_at) DESC
        LIMIT 20
    ");
    $stmt->execute([(int) $user['id'], $like, $like, $like, $like]);
    $results = array_merge($results, $stmt->fetchAll());

    $stmt = $pdo->prepare("
        SELECT 'Support' type, ticket_ref title, subject description, CONCAT('support.php?ticket=', ticket_ref) href
        FROM support_tickets
        WHERE user_id = ? AND (ticket_ref LIKE ? OR subject LIKE ? OR message LIKE ? OR status LIKE ?)
        ORDER BY last_activity_at DESC
        LIMIT 20
    ");
    $stmt->execute([(int) $user['id'], $like, $like, $like, $like]);
    $results = array_merge($results, $stmt->fetchAll());

    $stmt = $pdo->prepare("
        SELECT 'Marketplace' type, l.title, COALESCE(l.summary, s.store_name) description, CONCAT('../market/product.php?id=', l.id) href
        FROM marketplace_listings l
        JOIN marketplace_sellers s ON s.id = l.seller_id
        WHERE l.approval_status = 'approved' AND (l.title LIKE ? OR l.summary LIKE ? OR l.description LIKE ? OR s.store_name LIKE ?)
        ORDER BY l.is_featured DESC, l.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$like, $like, $like, $like]);
    $results = array_merge($results, $stmt->fetchAll());
}

buyer_page_start('Buyer Search', 'marketplace', $user, buyer_counts($pdo, $user));
?>
<div class="page-head"><div><h1>Buyer Search</h1><p>Search orders, support tickets, sellers, and marketplace listings.</p></div></div>
<form class="card" method="get" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px">
  <input name="q" value="<?= e($q) ?>" placeholder="Search products, orders, tickets, sellers..." style="flex:1;min-width:220px">
  <button class="btn" type="submit">Search</button>
</form>
<section class="card"><div class="list">
  <?php foreach ($results as $row): ?><a class="row" href="<?= e((string) $row['href']) ?>"><span><strong><?= e((string) $row['title']) ?></strong><br><small><?= e((string) $row['description']) ?></small></span><span class="badge"><?= e((string) $row['type']) ?></span></a><?php endforeach; ?>
  <?php if ($q === ''): ?><div class="alert ok">Type a search term to find buyer records.</div><?php elseif (!$results): ?><div class="alert ok">No buyer result matched your search.</div><?php endif; ?>
</div></section>
<?php buyer_page_end(); ?>
