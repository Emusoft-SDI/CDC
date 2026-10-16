<?php
declare(strict_types=1);

require_once __DIR__ . '/_market.php';
require_once __DIR__ . '/../lib/landing-templates.php';

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
    LEFT JOIN marketplace_listings l ON l.category_id = c.id AND l.approval_status = 'approved' AND l.deleted_at IS NULL
    LEFT JOIN marketplace_sellers s ON s.id = l.seller_id AND s.approval_status = 'approved' AND s.deleted_at IS NULL
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
            WHERE l.seller_id = s.id AND l.approval_status = 'approved' AND l.deleted_at IS NULL
        ) item_count
    FROM marketplace_sellers s
    WHERE s.approval_status = 'approved'
      AND s.deleted_at IS NULL
      AND EXISTS (
          SELECT 1
          FROM marketplace_listings l2
          WHERE l2.seller_id = s.id AND l2.approval_status = 'approved' AND l2.deleted_at IS NULL
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
          AND l.deleted_at IS NULL
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
$marketTemplate = landing_template($pdo, 'market');
market_header('NATCODEV Marketplace', 'marketplace', $pdo, [], 'market-template-' . $marketTemplate);
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
  .market-template-b .mv-hero-main{min-height:380px}
  .market-template-b .mv-shop-paths{gap:18px}
  .market-template-b .mv-shop-path{min-height:140px;padding:20px}
  .market-template-b .mv-pick-list{max-height:200px}
  .market-template-b .mv-grid{grid-template-columns:repeat(3,minmax(0,1fr));gap:20px}
  .market-template-b .mv-vendors{grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
  @media(max-width:1180px){.market-template-b .mv-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
  @media(max-width:760px){.market-template-b .mv-grid{grid-template-columns:1fr}}
</style>

<?php require __DIR__ . '/../templates/market-' . $marketTemplate . '.php'; ?>
<?php market_footer(); ?>
