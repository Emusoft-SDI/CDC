<?php
declare(strict_types=1);

require_once __DIR__ . '/_market.php';

$pdo = market_boot();
$q = trim((string) ($_GET['q'] ?? ''));
$where = ["approval_status = 'approved'"];
$params = [];
if ($q !== '') {
    $where[] = "(store_name LIKE ? OR description LIKE ? OR location_label LIKE ? OR seller_type LIKE ?)";
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like);
}
$stmt = $pdo->prepare("SELECT * FROM marketplace_sellers WHERE " . implode(' AND ', $where) . " ORDER BY is_featured DESC, store_name LIMIT 80");
$stmt->execute($params);
$sellers = $stmt->fetchAll();

market_header('Seller Directory', 'stores', $pdo);
?>
<section class="mk-section">
  <div class="mk-section-head">
    <div><h2>Seller Directory</h2><p>Verified storefronts for growers, providers, processors, farm hands, consultants, logistics, and offtakers.</p></div>
    <form class="mk-search" method="get" action="stores.php"><input name="q" value="<?= e($q) ?>" placeholder="Search sellers"><button type="submit"><?= market_icon('search') ?></button></form>
  </div>
  <?php if ($sellers): ?>
    <div class="mk-grid">
      <?php foreach ($sellers as $seller): ?>
        <article class="mk-card">
          <div class="mk-card-body">
            <div class="mk-store-head">
              <div class="mk-store-avatar"><?= market_icon('store') ?></div>
              <div>
                <h3><a href="store.php?seller=<?= e((string) $seller['slug']) ?>"><?= e((string) $seller['store_name']) ?></a></h3>
                <div class="mk-meta"><?= e(marketplace_status_label((string) $seller['seller_type'])) ?></div>
              </div>
            </div>
            <div class="mk-badges"><span class="mk-badge"><?= e(marketplace_status_label((string) $seller['verification_status'])) ?></span><?php if ((int) $seller['is_featured'] === 1): ?><span class="mk-badge gold">Featured</span><?php endif; ?></div>
            <p class="mk-meta"><?= e((string) ($seller['description'] ?: 'Marketplace seller profile.')) ?></p>
            <div class="mk-meta"><?= e((string) ($seller['location_label'] ?: 'Location pending')) ?></div>
            <div class="mk-actions"><a class="mk-btn" href="store.php?seller=<?= e((string) $seller['slug']) ?>">Open Store</a></div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="mk-empty">No approved sellers found for this search.</div>
  <?php endif; ?>
</section>
<?php market_footer(); ?>
