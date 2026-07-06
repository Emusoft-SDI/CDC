<?php
declare(strict_types=1);
require_once __DIR__ . '/_buyer.php';
$pdo = buyer_boot();
$user = buyer_require($pdo);
$counts = buyer_counts($pdo, $user);
$featured = market_listing_query($pdo, [], 4);
$orders = [];
$stmt = $pdo->prepare("SELECT * FROM marketplace_orders WHERE buyer_user_id = ? ORDER BY created_at DESC LIMIT 3");
$stmt->execute([(int) $user['id']]);
$orders = $stmt->fetchAll();
$supportStmt = $pdo->prepare("SELECT COUNT(*) FROM support_tickets WHERE user_id = ? AND status NOT IN ('resolved','closed','rejected')");
$supportStmt->execute([(int) $user['id']]);
$openSupport = (int) $supportStmt->fetchColumn();
buyer_page_start('Buyer Dashboard', 'overview', $user, $counts);
?>
<div class="page-head"><div><h1>Buyer Dashboard</h1><p>Welcome, <?= e((string) $user['name']) ?>. Manage purchases, wallet, refunds, support, messages, and your buyer profile.</p></div><a class="btn" href="../market/index.php"><i class="fas fa-store"></i> Shop Marketplace</a></div>
<section class="hero-card"><h2>Your buyer workspace.</h2><p>Everything here is tied to your buyer account: marketplace orders, shipment tracking, wallet finance, refunds, private support, quote messages, and profile details.</p><a class="btn" href="orders.php">Track Orders</a> <a class="btn light" href="wallet.php">Wallet & Finance</a></section>
<div class="kpis">
  <div class="kpi"><i class="fas fa-cart-shopping"></i><span><b><?= (int) $counts['cartCount'] ?></b><br>Cart Items</span></div>
  <div class="kpi"><i class="fas fa-truck-fast"></i><span><b><?= (int) $counts['orders'] ?></b><br>Orders</span></div>
  <div class="kpi"><i class="fas fa-file-signature"></i><span><b><?= (int) $counts['quotes'] ?></b><br>Quote Requests</span></div>
  <div class="kpi"><i class="fas fa-graduation-cap"></i><span><b><?= (int) $counts['academy'] ?></b><br>Courses</span></div>
  <div class="kpi"><i class="fas fa-wallet"></i><span><b><?= e(marketplace_money((float) $counts['wallet'])) ?></b><br>Wallet</span></div>
  <div class="kpi"><i class="fas fa-headset"></i><span><b><?= $openSupport ?></b><br>Open Support</span></div>
</div>
<div class="grid">
  <section class="card span-6"><div class="card-head"><h2>Featured Products & Services</h2><a class="view" href="../market/index.php">View all</a></div><div class="list"><?php foreach ($featured as $item): ?><a class="row" href="../market/product.php?id=<?= (int) $item['id'] ?>"><span style="display:flex;gap:10px;align-items:center"><img class="thumb" src="<?= e(market_listing_image_url($item)) ?>" alt=""><span><strong><?= e((string) $item['title']) ?></strong><br><small><?= e((string) $item['store_name']) ?></small></span></span><strong><?= e(marketplace_money((float) $item['price'])) ?></strong></a><?php endforeach; ?></div></section>
  <section class="card span-3"><div class="card-head"><h2>Orders</h2><a class="view" href="orders.php">View all</a></div><div class="list"><?php if ($orders): foreach ($orders as $order): ?><a class="row" href="orders.php?checkout_ref=<?= urlencode((string) $order['checkout_ref']) ?>"><span><strong><?= e((string) $order['checkout_ref']) ?></strong><br><small><?= e(marketplace_status_label((string) $order['payment_status'])) ?></small></span><span class="badge"><?= e(marketplace_status_label((string) $order['delivery_status'])) ?></span></a><?php endforeach; else: ?><p>No buyer orders yet.</p><?php endif; ?></div></section>
  <section class="card span-3"><div class="card-head"><h2>Academy</h2><a class="view" href="academy.php">Open</a></div><p>Access free learning, paid courses, delivery links, and certificates.</p><a class="btn light" href="../academy/index.php?screen=catalog">Browse Courses</a></section>
  <section class="card span-12"><div class="card-head"><h2>Quick Actions</h2></div><div class="action-grid"><a class="quick" href="../market/index.php"><i class="fas fa-store"></i>Shop</a><a class="quick" href="cart.php"><i class="fas fa-cart-shopping"></i>Cart</a><a class="quick" href="orders.php"><i class="fas fa-truck"></i>Track Order</a><a class="quick" href="../academy/index.php?screen=catalog"><i class="fas fa-graduation-cap"></i>Academy</a><a class="quick" href="wallet.php"><i class="fas fa-wallet"></i>Wallet</a><a class="quick" href="messages.php"><i class="fas fa-comments"></i>Messages</a><a class="quick" href="support.php"><i class="fas fa-headset"></i>Support</a></div></section>
</div>
<?php buyer_page_end(); ?>
