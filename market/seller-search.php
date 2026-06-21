<?php
declare(strict_types=1);
require_once __DIR__ . '/_seller.php';

$pdo = market_boot();
$user = market_require_user($pdo);
seller_access_or_message($pdo, $user);
$ctx = seller_query_context($pdo, $user, false);
$seller = $ctx['seller'];
$q = trim((string) ($_GET['q'] ?? ''));
$results = [];

if ($q !== '') {
    $needle = strtolower($q);
    foreach ($ctx['listings'] as $row) {
        $haystack = strtolower((string) ($row['title'] ?? '') . ' ' . (string) ($row['summary'] ?? '') . ' ' . (string) ($row['category_name'] ?? '') . ' ' . (string) ($row['approval_status'] ?? ''));
        if (str_contains($haystack, $needle)) {
            $results[] = ['type' => 'Product', 'title' => $row['title'], 'description' => ($row['category_name'] ?? 'Marketplace') . ' / ' . marketplace_status_label((string) $row['approval_status']), 'href' => 'seller-add-product.php?edit_listing=' . (int) $row['id']];
        }
    }
    foreach ($ctx['orders'] as $row) {
        $haystack = strtolower((string) ($row['order_ref'] ?? '') . ' ' . (string) ($row['checkout_ref'] ?? '') . ' ' . (string) ($row['buyer_name'] ?? '') . ' ' . (string) ($row['listing_title'] ?? '') . ' ' . (string) ($row['status'] ?? ''));
        if (str_contains($haystack, $needle)) {
            $results[] = ['type' => 'Order', 'title' => $row['order_ref'], 'description' => ($row['listing_title'] ?? 'Order') . ' / ' . marketplace_status_label((string) $row['status']), 'href' => 'seller-orders.php'];
        }
    }
    foreach ($ctx['inquiries'] as $row) {
        $haystack = strtolower((string) ($row['buyer_name'] ?? '') . ' ' . (string) ($row['buyer_email'] ?? '') . ' ' . (string) ($row['listing_title'] ?? '') . ' ' . (string) ($row['message'] ?? '') . ' ' . (string) ($row['status'] ?? ''));
        if (str_contains($haystack, $needle)) {
            $results[] = ['type' => 'Buyer Message', 'title' => $row['buyer_name'] ?: 'Buyer inquiry', 'description' => ($row['listing_title'] ?? 'Inquiry') . ' / ' . marketplace_status_label((string) $row['status']), 'href' => 'seller-messages.php'];
        }
    }
}

seller_header('Seller Search', 'products', $user, $seller);
?>
<form class="sc-card sc-panel" method="get" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px">
  <input name="q" value="<?= e($q) ?>" placeholder="Search products, orders, buyers..." style="flex:1;min-width:220px">
  <button class="sc-btn" type="submit">Search</button>
</form>
<section class="sc-card sc-panel">
  <div class="sc-list">
    <?php foreach ($results as $row): ?><a class="sc-row" href="<?= e((string) $row['href']) ?>"><span class="badge good"><?= e((string) $row['type']) ?></span><span><strong><?= e((string) $row['title']) ?></strong><br><small class="muted"><?= e((string) $row['description']) ?></small></span><span>Open</span></a><?php endforeach; ?>
    <?php if ($q === ''): ?><div class="empty">Type a search term to find seller products, orders, and buyers.</div><?php elseif (!$results): ?><div class="empty">No seller result matched your search.</div><?php endif; ?>
  </div>
</section>
<?php seller_footer(); ?>
