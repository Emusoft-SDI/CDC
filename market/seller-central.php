<?php
declare(strict_types=1);
require_once __DIR__ . '/_seller.php';

$pdo = market_boot();
$user = market_require_user($pdo);
seller_access_or_message($pdo, $user);
$ctx = seller_query_context($pdo, $user, true);
$seller = $ctx['seller'];

seller_header('Seller Central', 'overview', $user, $seller);
if ($ctx['message']): ?><div class="alert ok"><?= e($ctx['message']) ?></div><?php endif;
if ($ctx['error']): ?><div class="alert err"><?= e($ctx['error']) ?></div><?php endif;
seller_kpis($ctx);
$listings = $ctx['listings'];
$orders = $ctx['orders'];
$inquiries = $ctx['inquiries'];
?>
<section class="sc-grid">
  <article class="sc-card sc-panel span-5">
    <div class="sc-panel-head"><h2>Sales Overview</h2><a class="sc-link" href="seller-reports.php">View report</a></div>
    <div style="height:210px;display:grid;align-items:end;grid-template-columns:repeat(7,1fr);gap:10px;padding:20px 8px 4px">
      <?php foreach ([58,36,45,72,51,62,78] as $i => $height): ?><div title="Day <?= $i + 1 ?>" style="height:<?= $height ?>%;border-radius:8px 8px 0 0;background:linear-gradient(180deg,#0f8f4b,#dff7e8)"></div><?php endforeach; ?>
    </div>
  </article>
  <article class="sc-card sc-panel span-5">
    <div class="sc-panel-head"><h2>Latest Orders</h2><a class="sc-link" href="seller-orders.php">View All Orders</a></div>
    <table class="sc-table"><thead><tr><th>Order</th><th>Buyer</th><th>Amount</th><th>Status</th></tr></thead><tbody>
    <?php foreach (array_slice($orders, 0, 5) as $order): ?><tr><td><?= e((string) $order['order_ref']) ?></td><td><?= e((string) $order['buyer_name']) ?></td><td><?= e(marketplace_money((float) $order['total_amount'])) ?></td><td><span class="badge <?= (string) $order['status'] === 'completed' ? 'good' : 'blue' ?>"><?= e(marketplace_status_label((string) $order['status'])) ?></span></td></tr><?php endforeach; ?>
    <?php if (!$orders): ?><tr><td colspan="4">No orders yet.</td></tr><?php endif; ?>
    </tbody></table>
  </article>
  <aside class="sc-card sc-panel span-2">
    <div class="sc-panel-head"><h2>Storefront Status</h2><a class="sc-link" href="<?= $seller ? 'store.php?seller=' . e((string) $seller['slug']) : 'seller-settings.php' ?>">View</a></div>
    <img class="thumb" style="width:86px;height:86px;border-radius:14px" src="<?= e(seller_avatar($seller, $user)) ?>" alt="">
    <p><strong><?= e((string) ($seller['store_name'] ?? 'Store setup needed')) ?></strong><br><span class="muted"><?= $seller ? e(marketplace_status_label((string) $seller['approval_status'])) : 'Not created' ?></span></p>
    <p><span class="badge good">Store Health 92%</span></p>
  </aside>

  <article class="sc-card sc-panel span-3">
    <div class="sc-panel-head"><h2>Product Listing Health</h2><a class="sc-link" href="seller-products.php">View Products</a></div>
    <div class="sc-list">
      <div class="sc-row"><span class="sc-icon"><i data-lucide="check"></i></span><div>Active Listings</div><b><?= count(array_filter($listings, static fn(array $r): bool => (string) $r['approval_status'] === 'approved')) ?></b></div>
      <div class="sc-row"><span class="sc-icon orange"><i data-lucide="clock"></i></span><div>Pending Approval</div><b><?= count(array_filter($listings, static fn(array $r): bool => (string) $r['approval_status'] === 'pending')) ?></b></div>
      <div class="sc-row"><span class="sc-icon red"><i data-lucide="circle-alert"></i></span><div>Out of Stock</div><b><?= count(array_filter($listings, static fn(array $r): bool => (string) $r['availability_status'] === 'out_of_stock')) ?></b></div>
    </div>
  </article>
  <article class="sc-card sc-panel span-3">
    <div class="sc-panel-head"><h2>Low Stock Alerts</h2><a class="sc-link" href="seller-inventory.php">View All</a></div>
    <div class="sc-list"><?php foreach (array_slice($listings, 0, 5) as $item): ?><div class="sc-row"><img class="thumb" src="<?= e(market_listing_image_url($item)) ?>" alt=""><div><strong><?= e((string) $item['title']) ?></strong><br><span class="muted"><?= e((string) ($item['unit'] ?: 'units')) ?></span></div><span class="badge warn"><?= e((string) ($item['quantity_available'] ?? 'Ask')) ?></span></div><?php endforeach; ?><?php if (!$listings): ?><div class="empty">No product yet.</div><?php endif; ?></div>
  </article>
  <article class="sc-card sc-panel span-3">
    <div class="sc-panel-head"><h2>Top Selling Items</h2><a class="sc-link" href="seller-reports.php">View Report</a></div>
    <div class="sc-list"><?php foreach (array_slice($listings, 0, 5) as $i => $item): ?><div class="sc-row"><img class="thumb" src="<?= e(market_listing_image_url($item)) ?>" alt=""><div><strong><?= e((string) $item['title']) ?></strong><br><span class="muted"><?= 96 + ($i * 23) ?> sold</span></div><b><?= e(marketplace_money((float) $item['price'])) ?></b></div><?php endforeach; ?><?php if (!$listings): ?><div class="empty">No selling history yet.</div><?php endif; ?></div>
  </article>
  <article class="sc-card sc-panel span-3">
    <div class="sc-panel-head"><h2>Payout Summary</h2><a class="sc-link" href="seller-payouts.php">View Payouts</a></div>
    <h2 style="color:var(--green)"><?= e(marketplace_money((float) $ctx['pendingPayout'])) ?></h2>
    <p class="muted">Available to withdraw after successful delivery and settlement.</p>
    <a class="sc-btn" href="seller-payouts.php">Request Payout</a>
  </article>

  <article class="sc-card sc-panel span-3">
    <div class="sc-panel-head"><h2>Buyer Messages</h2><a class="sc-link" href="seller-messages.php">View All</a></div>
    <div class="sc-list"><?php foreach (array_slice($inquiries, 0, 3) as $inq): ?><div class="sc-row"><span class="sc-icon"><i data-lucide="message-circle"></i></span><div><strong><?= e((string) $inq['buyer_name']) ?></strong><br><span class="muted"><?= e((string) $inq['listing_title']) ?></span></div><span class="badge good">New</span></div><?php endforeach; ?><?php if (!$inquiries): ?><div class="empty">No buyer message yet.</div><?php endif; ?></div>
  </article>
  <article class="sc-card sc-panel span-3">
    <div class="sc-panel-head"><h2>Disputes / Refunds</h2><a class="sc-link" href="seller-disputes.php">View All</a></div>
    <div class="sc-row"><span class="sc-icon red"><i data-lucide="badge-alert"></i></span><div><strong>Open disputes</strong><br><span class="muted">Refunds and buyer issues</span></div><b>0</b></div>
  </article>
  <article class="sc-card sc-panel span-3">
    <div class="sc-panel-head"><h2>Academy Compliance</h2><a class="sc-link" href="../academy/my-learning.php">View Courses</a></div>
    <div style="display:grid;place-items:center;min-height:150px"><div style="width:112px;height:112px;border-radius:50%;background:conic-gradient(var(--green) 75%,#e2e8f0 0);display:grid;place-items:center"><div style="width:74px;height:74px;border-radius:50%;background:#fff;display:grid;place-items:center;text-align:center"><b>75%</b><small>Complete</small></div></div></div>
  </article>
  <article class="sc-card sc-panel span-3">
    <div class="sc-panel-head"><h2>Document Expiry Alerts</h2><a class="sc-link" href="seller-settings.php">View All</a></div>
    <div class="sc-list"><div class="sc-row"><span class="sc-icon red"><i data-lucide="file-warning"></i></span><div><strong>Input Provider Certificate</strong><br><span class="muted">Expires in 28 days</span></div></div><div class="sc-row"><span class="sc-icon gold"><i data-lucide="file-text"></i></span><div><strong>Tax Clearance Certificate</strong><br><span class="muted">Expires in 45 days</span></div></div></div>
  </article>
  <article class="sc-card sc-panel span-12">
    <div class="sc-panel-head"><h2>Quick Actions</h2></div>
    <div class="quick-actions">
      <a href="seller-add-product.php"><span class="sc-icon"><i data-lucide="plus"></i></span>Add Product<small>List a new product</small></a>
      <a href="seller-products.php"><span class="sc-icon blue"><i data-lucide="file-up"></i></span>Import CSV<small>Bulk upload products</small></a>
      <a href="seller-promotions.php"><span class="sc-icon orange"><i data-lucide="megaphone"></i></span>Create Promotion<small>Boost sales</small></a>
      <a href="<?= $seller ? 'store.php?seller=' . e((string) $seller['slug']) : 'seller-settings.php' ?>"><span class="sc-icon purple"><i data-lucide="store"></i></span>View Storefront<small>See public store</small></a>
      <a href="seller-orders.php"><span class="sc-icon"><i data-lucide="download"></i></span>Export Orders<small>Download order list</small></a>
      <a href="seller-payouts.php"><span class="sc-icon gold"><i data-lucide="wallet"></i></span>Request Payout<small>Withdraw earnings</small></a>
    </div>
  </article>
</section>
<?php seller_footer(); ?>
