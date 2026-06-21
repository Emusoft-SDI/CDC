<?php
declare(strict_types=1);

require_once __DIR__ . '/_market.php';

$pdo = market_boot();
$slug = trim((string) ($_GET['seller'] ?? ''));
$stmt = $pdo->prepare("SELECT * FROM marketplace_sellers WHERE slug = ? AND approval_status = 'approved' LIMIT 1");
$stmt->execute([$slug]);
$seller = $stmt->fetch();
if (!$seller) {
    http_response_code(404);
    market_header('Store Not Found', 'stores', $pdo);
    echo '<section class="mk-section"><div class="mk-empty">This storefront is not available or has not been approved.</div></section>';
    market_footer();
    exit;
}

$stmt = $pdo->prepare("
    SELECT l.*, c.name category_name, s.store_name, s.slug seller_slug, s.seller_type, s.verification_status, s.location_label seller_location
    FROM marketplace_listings l
    JOIN marketplace_sellers s ON s.id = l.seller_id
    LEFT JOIN marketplace_categories c ON c.id = l.category_id
    WHERE l.seller_id = ? AND l.approval_status = 'approved'
    ORDER BY l.is_featured DESC, l.created_at DESC
");
$stmt->execute([(int) $seller['id']]);
$listings = $stmt->fetchAll();

market_header((string) $seller['store_name'], 'stores', $pdo);
?>
<section class="mk-section" style="background:linear-gradient(135deg,#0f5b2c,#20a69a);color:#fff;overflow:hidden;position:relative">
  <div style="position:absolute;right:-80px;top:-80px;width:260px;height:260px;border-radius:50%;background:rgba(255,255,255,.12)"></div>
  <div style="position:absolute;right:120px;bottom:-120px;width:260px;height:260px;border-radius:50%;background:rgba(255,255,255,.10)"></div>
  <div class="mk-store-head">
    <div class="mk-store-avatar"><?= market_icon('store') ?></div>
    <div>
      <div class="mk-badges">
        <span class="mk-badge"><?= e(marketplace_status_label((string) $seller['seller_type'])) ?></span>
        <span class="mk-badge gold"><?= e(marketplace_status_label((string) $seller['verification_status'])) ?></span>
      </div>
      <h1 style="margin:10px 0 6px;color:#fff;font-size:clamp(2rem,4vw,3.8rem)"><?= e((string) $seller['store_name']) ?></h1>
      <p style="max-width:760px;color:#e8fff0;line-height:1.65"><?= e((string) ($seller['description'] ?: 'Approved NATCODEV marketplace seller.')) ?></p>
      <div style="color:#fff8d6;font-weight:800;margin-top:10px"><?= e((string) ($seller['location_label'] ?: 'Coverage available')) ?> · <?= e((string) ($seller['coverage_area'] ?: 'Contact seller for coverage')) ?></div>
    </div>
  </div>
</section>

<section class="mk-section">
  <div class="mk-section-head">
    <div>
      <h2>Store Listings</h2>
      <p><?= number_format(count($listings)) ?> approved product/service listing(s). Add items to cart, checkout, or ask seller before buying.</p>
    </div>
    <a class="mk-btn secondary" href="stores.php">Back to directory</a>
  </div>
  <?php if ($listings): ?>
    <div class="mk-grid"><?php foreach ($listings as $item) { market_render_listing_card($item); } ?></div>
  <?php else: ?>
    <div class="mk-empty">This seller has no approved listing yet.</div>
  <?php endif; ?>
</section>
<?php market_footer(); ?>
