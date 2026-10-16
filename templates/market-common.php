<?php
/**
 * Shared marketplace landing sections (Template A + Template B).
 *
 * Required from market-a.php / market-b.php via market/index.php.
 * Receives: $categories, $populatedCategories, $listings, $featuredSellers,
 * $sellerListingMap, $serviceListings, $q, $categoryId, $listingType,
 * $stateFilter, $activeLabel, $catalogBase.
 */
?>
  <nav class="mv-strip" aria-label="Marketplace categories">
    <a class="mv-chip <?= $categoryId === 0 ? 'active' : '' ?>" href="index.php"><i class="fas fa-border-all"></i> All categories</a>
    <?php foreach (array_slice($populatedCategories, 0, 12) as $cat): ?>
      <a class="mv-chip <?= $categoryId === (int) $cat['id'] ? 'active' : '' ?>" href="index.php?category_id=<?= (int) $cat['id'] ?>">
        <i class="fas fa-leaf"></i> <?= e((string) $cat['name']) ?> (<?= number_format((int) $cat['listing_count']) ?>)
      </a>
    <?php endforeach; ?>
  </nav>

  <div class="mv-layout">
    <aside class="mv-filter">
      <h2>Refine results</h2>
      <form method="get" action="index.php#results" style="display:grid;gap:14px">
        <div class="mv-field"><label for="q">Keyword</label><input id="q" name="q" value="<?= e($q) ?>" placeholder="Product, seller, service"></div>
        <div class="mv-field">
          <label for="category_id">Category</label>
          <select id="category_id" name="category_id">
            <option value="">All categories</option>
            <?php foreach ($populatedCategories as $cat): ?><option value="<?= (int) $cat['id'] ?>" <?= $categoryId === (int) $cat['id'] ? 'selected' : '' ?>><?= e((string) $cat['name']) ?> (<?= number_format((int) $cat['listing_count']) ?>)</option><?php endforeach; ?>
          </select>
        </div>
        <div class="mv-field">
          <label for="listing_type">Type</label>
          <select id="listing_type" name="listing_type">
            <option value="">Products and services</option>
            <?php foreach (marketplace_listing_types() as $value => $label): ?><option value="<?= e($value) ?>" <?= $listingType === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="mv-field">
          <label for="state">Location</label>
          <select id="state" name="state">
            <option value="">All Nigeria</option>
            <?php foreach ($states as $state): ?><option value="<?= e((string) $state['state_name']) ?>" <?= strcasecmp($stateFilter, (string) $state['state_name']) === 0 ? 'selected' : '' ?>><?= e((string) $state['state_name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <button type="submit">Apply filters</button>
        <a class="ghost" href="index.php#results">Reset</a>
      </form>
      <div style="border-top:1px solid #edf2ec;padding-top:14px;display:grid;gap:10px;color:#475467;font-weight:800">
        <span><i class="fas fa-shield-halved" style="color:#08753a"></i> Admin moderated vendors</span>
        <span><i class="fas fa-wallet" style="color:#08753a"></i> Wallet-aware settlement</span>
        <span><i class="fas fa-award" style="color:#08753a"></i> Verified seller badges</span>
      </div>
    </aside>

    <main class="mv-content">
      <div id="results" style="scroll-margin-top:96px"></div>
      <section class="mv-section">
        <div class="mv-section-title">
          <div><h2><?= e($activeLabel) ?></h2><p><?= number_format(count($listings)) ?> visible result(s). Products and services are ordered by featured status and freshness.</p></div>
          <a class="mv-btn light" href="seller-central.php"><i class="fas fa-store"></i> Sell on NATCODEV</a>
        </div>
        <?php if ($listings): ?>
          <div class="mv-grid">
            <?php foreach ($listings as $item): ?>
              <article class="mv-card">
                <a class="mv-img" href="product.php?id=<?= (int) $item['id'] ?>">
                  <img src="<?= e(market_listing_image_url($item)) ?>" alt="<?= e((string) $item['title']) ?>">
                  <span class="mv-type"><?= e(marketplace_status_label((string) $item['listing_type'])) ?></span>
                </a>
                <div class="mv-card-body">
                  <h3><a href="product.php?id=<?= (int) $item['id'] ?>"><?= e((string) $item['title']) ?></a></h3>
                  <div class="mv-meta"><?= e((string) ($item['category_name'] ?: $item['store_name'])) ?></div>
                  <div class="mv-price"><?= e(marketplace_money((float) $item['price'])) ?> <small>/ <?= e((string) ($item['unit'] ?: $item['price_unit'] ?: 'unit')) ?></small></div>
                  <div class="mv-meta"><i class="fas fa-store"></i> <?= e((string) $item['store_name']) ?><br><i class="fas fa-location-dot"></i> <?= e((string) ($item['location_label'] ?: $item['seller_location'] ?: 'Nigeria')) ?></div>
                  <div class="mv-actions">
                    <a class="mv-btn" href="product.php?id=<?= (int) $item['id'] ?>">View details</a>
                    <form method="post">
                      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                      <input type="hidden" name="action" value="add_to_cart">
                      <input type="hidden" name="listing_id" value="<?= (int) $item['id'] ?>">
                      <input type="hidden" name="quantity" value="1">
                      <button class="mv-btn light" type="submit" aria-label="Add <?= e((string) $item['title']) ?> to cart"><i class="fas fa-cart-plus"></i></button>
                    </form>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="mv-empty">No approved listing matches this filter yet. Try a broader search or check the seller directory.</div>
        <?php endif; ?>
      </section>

      <?php if ($featuredSellers): ?>
      <section class="mv-section">
        <div class="mv-section-title">
          <div><h2>Featured vendors and items</h2><p>Promoted sellers with approved products ready for buyers to open.</p></div>
          <a class="mv-btn light" href="stores.php">Seller directory</a>
        </div>
        <div class="mv-vendors">
          <?php foreach ($featuredSellers as $seller): ?>
            <div class="mv-vendor">
              <div class="mv-vendor-top">
                <div class="mv-avatar"><?= e(market_initials((string) $seller['store_name'])) ?></div>
                <div style="min-width:0"><a href="store.php?seller=<?= e((string) $seller['slug']) ?>"><strong><?= e((string) $seller['store_name']) ?></strong></a><span><?= e((string) ($seller['location_label'] ?: $seller['coverage_area'] ?: 'Nigeria coverage')) ?></span></div>
              </div>
              <span class="mv-badge"><?= e(marketplace_status_label((string) $seller['seller_type'])) ?><?= (string) $seller['verification_status'] === 'verified' ? ' / Verified' : '' ?></span>
              <div class="mv-vendor-items">
                <?php foreach (($sellerListingMap[(int) $seller['id']] ?? []) as $sellerItem): ?>
                  <a class="mv-vendor-item" href="product.php?id=<?= (int) $sellerItem['id'] ?>">
                    <img src="<?= e(market_listing_image_url($sellerItem)) ?>" alt="<?= e((string) $sellerItem['title']) ?>">
                    <span><b><?= e((string) $sellerItem['title']) ?></b><small><?= e(marketplace_money((float) $sellerItem['price'])) ?></small></span>
                  </a>
                <?php endforeach; ?>
                <?php if (empty($sellerListingMap[(int) $seller['id']])): ?>
                  <span style="color:#667085;font-weight:800">Items are pending approval.</span>
                <?php endif; ?>
              </div>
              <a class="mv-btn light" href="store.php?seller=<?= e((string) $seller['slug']) ?>">Shop vendor</a>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>

      <?php if ($serviceListings): ?>
      <section class="mv-section">
        <div class="mv-section-title">
          <div><h2>Service providers</h2><p>Field operations, logistics, processing, advisory, and farm service support.</p></div>
          <a class="mv-btn light" href="index.php?listing_type=service">View services</a>
        </div>
        <div class="mv-grid">
          <?php foreach (array_slice($serviceListings, 0, 4) as $item): ?>
            <article class="mv-card">
              <a class="mv-img" href="product.php?id=<?= (int) $item['id'] ?>"><img src="<?= e(market_listing_image_url($item)) ?>" alt="<?= e((string) $item['title']) ?>"><span class="mv-type">Service</span></a>
              <div class="mv-card-body">
                <h3><a href="product.php?id=<?= (int) $item['id'] ?>"><?= e((string) $item['title']) ?></a></h3>
                <div class="mv-meta"><?= e((string) ($item['summary'] ?: $item['store_name'])) ?></div>
                <div class="mv-price"><?= e(marketplace_money((float) $item['price'])) ?></div>
                <a class="mv-btn light" href="store.php?seller=<?= e((string) $item['seller_slug']) ?>">Open vendor</a>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>

      <section class="mv-cta">
        <div>
          <h2>Grow your store inside NATCODEV Marketplace</h2>
          <p>Providers, growers, sellers, processors, and service teams can list products, receive orders, and manage settlements through the platform wallet.</p>
        </div>
        <a href="seller-central.php"><i class="fas fa-arrow-right"></i> Open Seller Central</a>
      </section>
    </main>
  </div>
</div>
