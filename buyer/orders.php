<?php
declare(strict_types=1);
require_once __DIR__ . '/_buyer.php';

$pdo = buyer_boot();
$user = buyer_require($pdo);
$selectedRef = preg_replace('/[^A-Z0-9-]/i', '', (string) ($_GET['checkout_ref'] ?? ''));
$summaryStmt = $pdo->prepare("
    SELECT checkout_ref, buyer_name, buyer_phone, delivery_address,
           COUNT(*) item_count,
           SUM(total_amount) subtotal,
           MAX(delivery_fee) delivery_fee,
           MAX(service_fee) service_fee,
           MAX(checkout_total) checkout_total,
           MAX(payment_status) payment_status,
           MAX(delivery_status) delivery_status,
           MAX(status) order_status,
           MAX(created_at) created_at
    FROM marketplace_orders
    WHERE buyer_user_id = ?
    GROUP BY checkout_ref, buyer_name, buyer_phone, delivery_address
    ORDER BY MAX(created_at) DESC
    LIMIT 80
");
$summaryStmt->execute([(int) $user['id']]);
$orders = $summaryStmt->fetchAll();
if ($selectedRef === '' && $orders) {
    $selectedRef = (string) $orders[0]['checkout_ref'];
}
$items = [];
if ($selectedRef !== '') {
    $itemStmt = $pdo->prepare("
        SELECT o.*, l.title listing_title, s.store_name
        FROM marketplace_orders o
        LEFT JOIN marketplace_listings l ON l.id = o.listing_id
        LEFT JOIN marketplace_sellers s ON s.id = o.seller_id
        WHERE o.buyer_user_id = ? AND o.checkout_ref = ?
        ORDER BY o.id ASC
    ");
    $itemStmt->execute([(int) $user['id'], $selectedRef]);
    $items = $itemStmt->fetchAll();
}

buyer_page_start('Buyer Orders & Shipments', 'orders', $user, buyer_counts($pdo, $user));
?>
<div class="page-head"><div><h1>Orders & Shipments</h1><p>Track your marketplace checkouts, seller processing, delivery status, and payment progress.</p></div><a class="btn" href="../market/index.php"><i class="fas fa-store"></i> Shop Marketplace</a></div>
<div class="grid">
  <section class="card span-5">
    <div class="card-head"><h2>My Checkouts</h2><span class="badge"><?= count($orders) ?> order<?= count($orders) === 1 ? '' : 's' ?></span></div>
    <div class="list">
      <?php foreach ($orders as $order): ?>
        <a class="row" href="orders.php?checkout_ref=<?= urlencode((string) $order['checkout_ref']) ?>">
          <span><strong><?= e((string) $order['checkout_ref']) ?></strong><br><small><?= e((string) $order['created_at']) ?> / <?= (int) $order['item_count'] ?> item(s)</small></span>
          <span><?= buyer_status_badge((string) $order['delivery_status']) ?><br><strong><?= e(buyer_money((float) ($order['checkout_total'] ?: $order['subtotal']))) ?></strong></span>
        </a>
      <?php endforeach; ?>
      <?php if (!$orders): ?><div class="alert ok">No buyer orders yet. Marketplace purchases tied to your buyer account will appear here.</div><?php endif; ?>
    </div>
  </section>
  <section class="card span-7">
    <div class="card-head"><h2>Shipment Detail</h2><?php if ($selectedRef): ?><span class="badge"><?= e($selectedRef) ?></span><?php endif; ?></div>
    <?php if ($items): ?>
      <?php $first = $items[0]; ?>
      <div class="kpis" style="grid-template-columns:repeat(3,1fr)">
        <div class="kpi"><i class="fas fa-credit-card"></i><span><b><?= e(marketplace_status_label((string) $first['payment_status'])) ?></b><br>Payment</span></div>
        <div class="kpi"><i class="fas fa-box"></i><span><b><?= e(marketplace_status_label((string) $first['status'])) ?></b><br>Order</span></div>
        <div class="kpi"><i class="fas fa-truck-fast"></i><span><b><?= e(marketplace_status_label((string) $first['delivery_status'])) ?></b><br>Shipment</span></div>
      </div>
      <p><strong>Delivery address:</strong> <?= e((string) $first['delivery_address']) ?><br><strong>Contact:</strong> <?= e((string) $first['delivery_contact']) ?><br><strong>Tracking:</strong> <?= e((string) $first['tracking_ref']) ?></p>
      <div class="list">
        <?php foreach ($items as $item): ?>
          <div class="row"><span><strong><?= e((string) ($item['listing_title'] ?: $item['order_ref'])) ?></strong><br><small><?= e((string) ($item['store_name'] ?: 'Seller')) ?> / <?= e((string) $item['order_ref']) ?></small></span><span><strong><?= e((string) $item['quantity']) ?> x <?= e(buyer_money((float) $item['unit_price'])) ?></strong><br><?= buyer_status_badge((string) $item['status']) ?></span></div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="alert ok">Select an order to view shipment details.</div>
    <?php endif; ?>
  </section>
</div>
<?php buyer_page_end(); ?>
