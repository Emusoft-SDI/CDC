<?php
declare(strict_types=1);
require_once __DIR__ . '/_seller.php';
$pdo = market_boot(); $user = market_require_user($pdo); seller_access_or_message($pdo, $user);
$ctx = seller_query_context($pdo, $user, false); $seller = $ctx['seller']; $listings = $ctx['listings'];
seller_header('Products', 'products', $user, $seller); seller_kpis($ctx);
?>
<section class="sc-card sc-panel"><div class="sc-panel-head"><h2>Product Listings</h2><a class="sc-btn" href="seller-add-product.php"><i data-lucide="plus"></i> Add Product</a></div>
<table class="sc-table"><thead><tr><th>Image</th><th>Name</th><th>Type</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th><th>Action</th></tr></thead><tbody>
<?php foreach ($listings as $row): ?><tr><td><img class="thumb" src="<?= e(market_listing_image_url($row)) ?>" alt=""></td><td><strong><?= e((string) $row['title']) ?></strong><br><span class="muted"><?= e((string) $row['summary']) ?></span></td><td><?= e(marketplace_status_label((string) $row['listing_type'])) ?></td><td><?= e((string) ($row['category_name'] ?? 'Marketplace')) ?></td><td><?= e(marketplace_money((float) $row['price'])) ?></td><td><?= e((string) ($row['quantity_available'] ?? 'Ask')) ?></td><td><span class="badge <?= (string) $row['approval_status'] === 'approved' ? 'good' : 'warn' ?>"><?= e(marketplace_status_label((string) $row['approval_status'])) ?></span></td><td><div class="sc-actions"><a href="seller-add-product.php?edit_listing=<?= (int) $row['id'] ?>">Edit</a><a href="product.php?id=<?= (int) $row['id'] ?>">View</a><form method="post" onsubmit="return confirm('Delete this listing from your store?')"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete_listing"><input type="hidden" name="listing_id" value="<?= (int) $row['id'] ?>"><button class="sc-btn secondary" type="submit" style="padding:4px 8px">Delete</button></form></div></td></tr><?php endforeach; ?>
<?php if (!$listings): ?><tr><td colspan="8">No listing yet.</td></tr><?php endif; ?></tbody></table></section>
<?php seller_footer(); ?>
