<?php
declare(strict_types=1);

require_once __DIR__ . '/_market.php';

$pdo = market_boot();
$user = market_user($pdo);
$q = trim((string) ($_GET['q'] ?? ''));
$categoryId = (int) ($_GET['category_id'] ?? 0);
$listingType = trim((string) ($_GET['listing_type'] ?? ''));
$stateFilter = trim((string) ($_GET['state'] ?? ''));
$filters = array_filter([
    'q' => $q,
    'category_id' => $categoryId,
    'listing_type' => $listingType,
    'state' => $stateFilter,
], static fn($value): bool => $value !== '' && $value !== 0);

function market_index_scalar(PDO $pdo, string $sql): int
{
    try {
        return (int) ($pdo->query($sql)->fetchColumn() ?: 0);
    } catch (Throwable $e) {
        return 0;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['_csrf'] ?? null)) {
    if ((string) ($_POST['action'] ?? '') === 'add_to_cart') {
        market_cart_add((int) ($_POST['listing_id'] ?? 0), (int) ($_POST['quantity'] ?? 1));
        redirect_to('cart.php');
    }
}

$categories = $pdo->query("
    SELECT c.*, COUNT(s.id) listing_count
    FROM marketplace_categories c
    LEFT JOIN marketplace_listings l ON l.category_id = c.id AND l.approval_status = 'approved'
    LEFT JOIN marketplace_sellers s ON s.id = l.seller_id AND s.approval_status = 'approved'
    WHERE c.is_active = 1
    GROUP BY c.id
    ORDER BY c.sort_order, c.name
")->fetchAll();
$populatedCategories = array_values(array_filter($categories, static fn(array $cat): bool => (int) ($cat['listing_count'] ?? 0) > 0));

$states = app_table_exists($pdo, 'nigeria_states')
    ? $pdo->query("SELECT state_name FROM nigeria_states ORDER BY state_name LIMIT 40")->fetchAll()
    : [];

$listings = market_listing_query($pdo, $filters, 32);
$heroListings = market_listing_query($pdo, ['listing_type' => 'product'], 4);
$serviceListings = market_listing_query($pdo, ['listing_type' => 'service'], 8);
$featuredProducts = market_listing_query($pdo, ['listing_type' => 'product'], 8);
$featuredSellers = $pdo->query("
    SELECT s.*,
        (
            SELECT COUNT(*)
            FROM marketplace_listings l
            WHERE l.seller_id = s.id AND l.approval_status = 'approved'
        ) item_count
    FROM marketplace_sellers s
    WHERE s.approval_status = 'approved'
      AND EXISTS (
          SELECT 1
          FROM marketplace_listings l2
          WHERE l2.seller_id = s.id AND l2.approval_status = 'approved'
      )
    ORDER BY s.is_featured DESC, s.verification_status = 'verified' DESC, item_count DESC, s.created_at DESC
    LIMIT 8
")->fetchAll();
$sellerListingMap = [];
if ($featuredSellers) {
    $sellerIds = array_map(static fn(array $seller): int => (int) $seller['id'], $featuredSellers);
    $placeholders = implode(',', array_fill(0, count($sellerIds), '?'));
    $sellerListingStmt = $pdo->prepare("
        SELECT l.*, c.name category_name
        FROM marketplace_listings l
        LEFT JOIN marketplace_categories c ON c.id = l.category_id
        WHERE l.approval_status = 'approved'
          AND l.seller_id IN ($placeholders)
        ORDER BY l.is_featured DESC, l.created_at DESC
    ");
    $sellerListingStmt->execute($sellerIds);
    foreach ($sellerListingStmt->fetchAll() as $row) {
        $sellerId = (int) $row['seller_id'];
        $sellerListingMap[$sellerId] = $sellerListingMap[$sellerId] ?? [];
        if (count($sellerListingMap[$sellerId]) < 2) {
            $sellerListingMap[$sellerId][] = $row;
        }
    }
}

$shopPaths = [
    ['icon' => 'fa-seedling', 'title' => 'Planting Materials', 'text' => 'Seedlings, nursery stock, and coconut inputs.', 'href' => 'index.php?q=seedling#results'],
    ['icon' => 'fa-box-open', 'title' => 'Farm Inputs', 'text' => 'Fertilizer, tools, irrigation, and equipment.', 'href' => 'index.php?listing_type=product&q=farm#results'],
    ['icon' => 'fa-tractor', 'title' => 'Hire Services', 'text' => 'Field crews, logistics, processing, and advisory.', 'href' => 'index.php?listing_type=service#results'],
    ['icon' => 'fa-store', 'title' => 'Sell Products', 'text' => 'Open a vendor profile and reach buyers.', 'href' => 'seller-central.php'],
];

$categoryName = '';
foreach ($categories as $cat) {
    if ((int) $cat['id'] === $categoryId) {
        $categoryName = (string) $cat['name'];
        break;
    }
}
$activeLabel = $categoryName !== '' ? $categoryName : ($listingType !== '' ? marketplace_status_label($listingType) : ($q !== '' ? 'Search results' : 'All listings'));
market_header('NATCODEV Marketplace', 'marketplace', $pdo);
?>
<style>
  .mv-page{background:linear-gradient(135deg,#f4f9f1 0%,#fff 44%,#eef8f4 100%);min-height:100vh}
  .mv-wrap{max-width:1480px;margin:0 auto;padding:26px}
  .mv-hero{display:grid;grid-template-columns:minmax(0,1.15fr) minmax(360px,.85fr);gap:18px;align-items:stretch}
  .mv-hero-main{position:relative;overflow:hidden;border-radius:14px;min-height:430px;padding:34px;background:linear-gradient(90deg,rgba(5,49,25,.96),rgba(8,96,49,.86)),url("../assets/public/natcodev-home-hero.png") center/cover;color:#fff;display:grid;align-content:end;box-shadow:0 24px 54px rgba(16,24,40,.14)}
  .mv-hero-main:after{content:"";position:absolute;right:24px;top:24px;width:128px;height:128px;border:1px solid rgba(255,255,255,.22);border-radius:50%}
  .mv-kicker{font-size:.78rem;text-transform:uppercase;letter-spacing:.08em;font-weight:950;color:#bdf3c9;margin-bottom:10px}
  .mv-hero h1{position:relative;margin:0 0 12px;font-size:clamp(2.45rem,5vw,5.2rem);line-height:1;letter-spacing:0;max-width:920px}
  .mv-hero p{position:relative;margin:0 0 22px;max-width:760px;color:#eaf8ee;font-size:1.08rem;line-height:1.65;font-weight:700}
  .mv-search{position:relative;display:grid;grid-template-columns:minmax(0,1fr) 170px 138px;gap:10px;max-width:860px;background:#fff;border-radius:12px;padding:10px;box-shadow:0 16px 34px rgba(0,0,0,.18)}
  .mv-search input,.mv-search select{border:1px solid #dfe8d8;border-radius:9px;padding:13px 14px;font:inherit;color:#102033;background:#fff;min-width:0}
  .mv-search button{border:0;border-radius:9px;background:#08753a;color:#fff;font-weight:950;padding:0 18px}
  .mv-hero-side{display:grid;grid-template-rows:auto 1fr;gap:14px}
  .mv-shop-paths{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
  .mv-shop-path{background:#fff;border:1px solid rgba(16,24,40,.08);border-radius:12px;padding:16px;box-shadow:0 16px 38px rgba(16,24,40,.08);color:inherit;display:grid;gap:8px;min-height:132px}
  .mv-shop-path:hover{border-color:#b7dcbc;box-shadow:0 18px 40px rgba(16,24,40,.12);transform:translateY(-1px)}
  .mv-shop-path i{width:42px;height:42px;border-radius:50%;display:grid;place-items:center;background:#e8f6ec;color:#08753a;font-size:1.1rem}
  .mv-shop-path strong{display:block;color:#063f20;font-size:1rem;line-height:1.2}
  .mv-shop-path span{display:block;color:#667085;font-weight:750;font-size:.86rem;line-height:1.35}
  .mv-featured-panel{background:#fff;border:1px solid rgba(16,24,40,.08);border-radius:12px;padding:16px;box-shadow:0 16px 38px rgba(16,24,40,.08);min-height:0}
  .mv-panel-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:12px}
  .mv-panel-head h2{margin:0;color:#063f20;font-size:1.05rem}
  .mv-panel-head a{font-size:.84rem;color:#08753a;font-weight:950}
  .mv-pick-list{display:grid;gap:10px; max-height: 250px; overflow-y: auto;}
  .mv-pick{display:grid;grid-template-columns:74px 1fr;gap:11px;align-items:center;border:1px solid #edf2ec;border-radius:10px;padding:8px;color:inherit}
  .mv-pick img{width:74px;height:64px;object-fit:cover;border-radius:8px;background:#eef8ef}
  .mv-pick strong{display:block;color:#102033;line-height:1.25}
  .mv-pick span{display:block;color:#08753a;font-size:.86rem;font-weight:950;margin-top:3px}
  .mv-strip{display:flex;gap:10px;align-items:center;overflow:auto;padding:18px 2px 4px;margin:12px 0}
  .mv-chip{display:inline-flex;align-items:center;gap:9px;white-space:nowrap;border:1px solid #dfe8d8;background:#fff;color:#173b20;border-radius:999px;padding:10px 14px;font-weight:900;box-shadow:0 8px 20px rgba(16,24,40,.04)}
  .mv-chip.active,.mv-chip:hover{background:#063f20;color:#fff;border-color:#063f20}
  .mv-layout{display:grid;grid-template-columns:270px minmax(0,1fr);gap:18px;align-items:start;margin-top:18px}
  .mv-filter,.mv-section{background:#fff;border:1px solid rgba(16,24,40,.08);border-radius:12px;box-shadow:0 16px 38px rgba(16,24,40,.08)}
  .mv-filter{position:sticky;top:92px;padding:16px;display:grid;gap:14px}
  .mv-filter h2{margin:0;color:#063f20;font-size:1.08rem}
  .mv-field label{display:block;font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;font-weight:950;color:#667085;margin-bottom:6px}
  .mv-field input,.mv-field select{width:100%;border:1px solid #dfe8d8;border-radius:9px;padding:11px 12px;background:#fbfdf9}
  .mv-filter button{border:0;border-radius:9px;background:#08753a;color:#fff;font-weight:950;padding:12px}
  .mv-filter .ghost{display:block;text-align:center;border:1px solid #dfe8d8;border-radius:9px;background:#fff;color:#063f20;font-weight:950;padding:10px}
  .mv-content{display:grid;gap:18px;min-width:0}
  .mv-section{padding:18px;overflow:hidden}
  .mv-section-title{display:flex;align-items:flex-end;justify-content:space-between;gap:12px;margin-bottom:14px}
  .mv-section-title h2{margin:0;color:#063f20;font-size:1.35rem}
  .mv-section-title p{margin:4px 0 0;color:#667085;font-weight:700}
  .mv-vendors{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
  .mv-vendor{border:1px solid #dfe8d8;border-radius:11px;padding:14px;background:#fbfdf9;display:grid;gap:10px;color:inherit}
  .mv-vendor-top{display:flex;gap:10px;align-items:center}
  .mv-avatar{width:48px;height:48px;border-radius:12px;background:linear-gradient(135deg,#dcfce7,#fff6d7);display:grid;place-items:center;color:#063f20;font-weight:950;flex:0 0 auto}
  .mv-vendor strong{display:block;color:#102033;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .mv-vendor span{display:block;color:#667085;font-size:.84rem;font-weight:750}
  .mv-badge{display:inline-flex;justify-self:start;border-radius:999px;background:#e8f6ec;color:#075c34;padding:5px 9px;font-size:.74rem;font-weight:950}
  .mv-vendor-items{display:grid;gap:7px;border-top:1px solid #e2ece4;padding-top:10px; max-height: 150px; overflow-y: auto;} /* Added max-height and overflow-y */
  .mv-vendor-item{display:grid;grid-template-columns:42px 1fr;gap:8px;align-items:center;color:inherit}
  .mv-vendor-item img{width:42px;height:38px;border-radius:7px;object-fit:cover;background:#eef8ef;border:1px solid #dfe8d8}
  .mv-vendor-item b{display:block;color:#102033;font-size:.8rem;line-height:1.2;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .mv-vendor-item small{display:block;color:#08753a;font-weight:900;margin-top:2px}
  .mv-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
  .mv-card{border:1px solid #dfe8d8;border-radius:12px;background:#fff;overflow:hidden;display:flex;flex-direction:column;min-height:100%;transition:.18s ease}
  .mv-card:hover{transform:translateY(-2px);box-shadow:0 16px 36px rgba(16,24,40,.1);border-color:#b7dcbc}
  .mv-img{height:178px;background:#eef8ef;position:relative;overflow:hidden}
  .mv-img img{width:100%;height:100%;object-fit:cover;display:block}
  .mv-type{position:absolute;left:10px;top:10px;border-radius:999px;background:rgba(6,63,32,.92);color:#fff;padding:5px 9px;font-size:.72rem;font-weight:950}
  .mv-card-body{padding:14px;display:grid;gap:8px;flex:1}
  .mv-card h3{margin:0;color:#102033;font-size:1rem;line-height:1.3}
  .mv-meta{color:#667085;font-size:.84rem;line-height:1.35}
  .mv-price{font-size:1.18rem;font-weight:950;color:#063f20}
  .mv-actions{display:grid;grid-template-columns:1fr auto;gap:8px;margin-top:auto}
  .mv-btn{border:0;border-radius:9px;background:#08753a;color:#fff;font-weight:950;padding:10px 12px;text-align:center;display:inline-flex;align-items:center;justify-content:center;gap:8px}
  .mv-btn.light{background:#f1faf3;color:#063f20;border:1px solid #dfe8d8}
  .mv-empty{border:1px dashed #d1dfd4;border-radius:12px;background:#fbfdf9;color:#667085;padding:26px;text-align:center;font-weight:850}
  .mv-cta{display:grid;grid-template-columns:1fr auto;gap:16px;align-items:center;background:linear-gradient(135deg,#063f20,#08753a);border-radius:12px;color:#fff;padding:22px;margin-top:18px}
  .mv-cta h2{margin:0 0 6px}.mv-cta p{margin:0;color:#dff5e8}.mv-cta a{background:#fff;color:#063f20;border-radius:9px;padding:12px 16px;font-weight:950}
  @media(max-width:1180px){.mv-hero,.mv-layout{grid-template-columns:1fr}.mv-filter{position:relative;top:auto}.mv-grid,.mv-vendors{grid-template-columns:repeat(2,minmax(0,1fr))}}
  @media(max-width:760px){.mv-wrap{padding:16px}.mv-search{grid-template-columns:1fr}.mv-shop-paths,.mv-grid,.mv-vendors,.mv-cta{grid-template-columns:1fr}.mv-hero-main{min-height:360px;padding:24px}}
</style>

<div class="mv-page">
  <div class="mv-wrap">
    <section class="mv-hero">
      <div class="mv-hero-main">
        <div class="mv-kicker">NATCODEV Multi-Vendor Marketplace</div>
        <h1>Find what your farm needs and buy from trusted coconut vendors.</h1>
        <p>Shop seedlings, inputs, tools, farm services, logistics, processing support, and marketplace sellers built around the NATCODEV coconut value chain.</p>
        <form class="mv-search" action="index.php#results" method="get">
          <input name="q" value="<?= e($q) ?>" placeholder="Search seedlings, inputs, tools, logistics, services..." aria-label="Search marketplace">
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

    <nav class="mv-strip" aria-label="Marketplace categories">
      <a class="mv-chip <?= $categoryId === 0 ? 'active' : '' ?>" href="index.php#results"><i class="fas fa-border-all"></i> All categories</a>
      <?php foreach (array_slice($populatedCategories, 0, 12) as $cat): ?>
        <a class="mv-chip <?= $categoryId === (int) $cat['id'] ? 'active' : '' ?>" href="index.php?category_id=<?= (int) $cat['id'] ?>#results">
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
</div>
<?php market_footer(); ?>