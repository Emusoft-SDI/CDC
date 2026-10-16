<?php
/**
 * Marketplace Template A (original dense layout).
 *
 * Required from market/index.php. Receives $q, $shopPaths, $heroListings.
 * Renders the original dense hero (search form + 4 shop-paths + featured picks
 * panel), then the shared marketplace landing sections.
 */
?>
  <div class="mv-page">
    <div class="mv-wrap">
      <section class="mv-hero">
        <div class="mv-hero-main">
          <div class="mv-kicker">NATCODEV Multi-Vendor Marketplace</div>
          <h1>Find what your farm needs and buy from trusted coconut vendors.</h1>
          <p>Shop seedlings, inputs, tools, farm services, logistics, processing support, and marketplace sellers built around the NATCODEV coconut value chain.</p>
          <form class="mv-search" action="index.php#results" method="get">
            <input name="q" value="<?= e($q) ?>" placeholder="Search seedlings, inputs, tools, logistics, service..." aria-label="Search marketplace">
            <select name="listing_type" aria-label="Listing type">
              <option value="">All types</option>
              <?php foreach (marketplace_listing_types() as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= $listingType === $value ? 'selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
            <button type="submit"><i class="fas fa-search"></i> Search</button>
          </form>
        </div>
        <aside class="mv-hero-side">
          <div class="mv-shop-paths">
            <?php foreach ($shopPaths as $path): ?>
              <a class="mv-shop-path" href="<?= e($path['href']) ?>">
                <i class="fas <?= e($path['icon']) ?>"></i>
                <strong><?= e($path['title']) ?></strong>
                <span><?= e($path['text']) ?></span>
              </a>
            <?php endforeach; ?>
          </div>
          <div class="mv-featured-panel">
            <div class="mv-panel-head"><h2>Start with these picks</h2><a href="featured.php">Shop featured</a></div>
            <div class="mv-pick-list">
              <?php foreach (array_slice($heroListings, 0, 4) as $item): ?>
                <a class="mv-pick" href="product.php?id=<?= (int) $item['id'] ?>">
                  <img src="<?= e(market_listing_image_url($item)) ?>" alt="<?= e((string) $item['title']) ?>">
                  <div><strong><?= e((string) $item['title']) ?></strong><span><?= e(marketplace_money((float) $item['price'])) ?></span></div>
                </a>
              <?php endforeach; ?>
              <?php if (!$heroListings): ?><div class="mv-empty">Featured products will appear here after vendor approval.</div><?php endif; ?>
            </div>
          </div>
        </aside>
      </section>

      <?php require __DIR__ . '/market-common.php'; ?>
