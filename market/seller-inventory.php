<?php
declare(strict_types=1);
require_once __DIR__ . '/_seller.php';
$pdo = market_boot(); $user = market_require_user($pdo); seller_access_or_message($pdo, $user);
$ctx = seller_query_context($pdo, $user, false); $seller = $ctx['seller']; seller_header('Inventory', 'inventory', $user, $seller);
?>
<section class="sc-card sc-panel"><div class="sc-panel-head"><h2>Inventory Health</h2><a class="sc-btn" href="seller-add-product.php">Add Stock Item</a></div><table class="sc-table"><thead><tr><th>Product</th><th>Available</th><th>Unit</th><th>Status</th><th>Action</th></tr></thead><tbody><?php foreach ($ctx['listings'] as $row): ?><tr><td><strong><?= e((string) $row['title']) ?></strong></td><td><?= e((string) ($row['quantity_available'] ?? 'Ask seller')) ?></td><td><?= e((string) ($row['unit'] ?: $row['price_unit'])) ?></td><td><span class="badge <?= (string) $row['availability_status'] === 'available' ? 'good' : 'warn' ?>"><?= e(marketplace_status_label((string) $row['availability_status'])) ?></span></td><td><a href="seller-add-product.php?edit_listing=<?= (int) $row['id'] ?>">Update</a></td></tr><?php endforeach; ?><?php if (!$ctx['listings']): ?><tr><td colspan="5">No inventory item yet.</td></tr><?php endif; ?></tbody></table></section>
<?php seller_footer(); ?>
