<?php
declare(strict_types=1);

require_once __DIR__ . '/_market.php';

$pdo = market_boot();
$user = market_user($pdo);

// Pagination settings
$limit = 24;
$page = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

// Fetch total count of featured items
$totalCount = (int) $pdo->query("
    SELECT COUNT(*) 
    FROM marketplace_listings l
    JOIN marketplace_sellers s ON s.id = l.seller_id
    WHERE l.approval_status = 'approved' 
      AND l.availability_status = 'available' 
      AND l.is_featured = 1 
      AND s.approval_status = 'approved'
")->fetchColumn();

$totalPages = (int) ceil($totalCount / $limit);

// Fetch featured items for the current page
$stmt = $pdo->prepare("
    SELECT l.*, c.name category_name, s.store_name, s.slug seller_slug, s.seller_type, s.verification_status, s.location_label seller_location
    FROM marketplace_listings l
    JOIN marketplace_sellers s ON s.id = l.seller_id
    LEFT JOIN marketplace_categories c ON c.id = l.category_id
    WHERE l.approval_status = 'approved' 
      AND l.availability_status = 'available' 
      AND l.is_featured = 1 
      AND s.approval_status = 'approved'
    ORDER BY l.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->bindValue(1, $limit, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
$stmt->execute();
$listings = $stmt->fetchAll();

$categories = $pdo->query("
    SELECT c.*, COUNT(l.id) listing_count
    FROM marketplace_categories c
    LEFT JOIN marketplace_listings l ON l.category_id = c.id AND l.approval_status = 'approved'
    WHERE c.is_active = 1
    GROUP BY c.id
    ORDER BY c.sort_order, c.name
")->fetchAll();

$stats = [
    'total_listings' => (int) $pdo->query("SELECT COUNT(*) FROM marketplace_listings WHERE approval_status = 'approved'")->fetchColumn(),
];

$logo = app_primary_logo_url();
$initials = $user ? market_initials((string) ($user['name'] ?? 'User')) : 'NT';

$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['_csrf'] ?? null)) {
    if ((string) ($_POST['action'] ?? '') === 'add_to_cart') {
        market_cart_add((int) ($_POST['listing_id'] ?? 0), (int) ($_POST['quantity'] ?? 1));
        $notice = 'Item added to cart.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Featured Products - NATCODEV Marketplace</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    /* Reusing styles from index.php for consistency */
    :root{--primary:#1a5f2a;--primary-dark:#144a21;--primary-light:#2d8041;--secondary:#f5f5f5;--accent:#ffc107;--text-primary:#1f2937;--text-secondary:#6b7280;--border:#e5e7eb;--success:#10b981;--warning:#f59e0b;--danger:#ef4444;--white:#fff;--shadow:0 1px 3px rgba(0,0,0,.1),0 1px 2px rgba(0,0,0,.06);--shadow-lg:0 10px 15px -3px rgba(0,0,0,.1),0 4px 6px -2px rgba(0,0,0,.05)}
    *{margin:0;padding:0;box-sizing:border-box}body{font-family:Inter,"Segoe UI",Arial,sans-serif;background:#f9fafb;color:var(--text-primary);line-height:1.5}a{text-decoration:none;color:inherit}
    .header{background:var(--white);border-bottom:1px solid var(--border);position:sticky;top:0;z-index:100}.header-container{max-width:1480px;margin:0 auto;padding:0 1.5rem;height:70px;display:flex;align-items:center;justify-content:space-between;gap:1.5rem}.logo{display:flex;align-items:center;gap:.75rem;color:var(--primary);min-width:210px}.logo-icon{width:42px;height:42px;background:#fff;border:1px solid var(--border);border-radius:9px;display:grid;place-items:center;overflow:hidden}.logo-icon img{width:100%;height:100%;object-fit:contain}.logo-text{font-size:1.25rem;font-weight:800;line-height:1.1}.logo-text span{display:block;font-size:.72rem;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.05em}
    .nav-menu{display:flex;gap:1.4rem;list-style:none}.nav-menu a{color:var(--text-secondary);font-weight:700;font-size:.94rem;padding:.5rem 0;border-bottom:2px solid transparent}.nav-menu a:hover,.nav-menu a.active{color:var(--primary);border-bottom-color:var(--primary)}.header-actions{display:flex;align-items:center;gap:.8rem}.notification-btn{position:relative;background:none;border:0;font-size:1.2rem;color:var(--text-secondary);cursor:pointer;padding:.5rem}.badge{position:absolute;top:0;right:0;background:var(--danger);color:#fff;font-size:.62rem;font-weight:700;padding:.12rem .36rem;border-radius:999px}.location-selector,.user-menu{display:flex;align-items:center;gap:.5rem;padding:.5rem .8rem;background:var(--secondary);border-radius:.5rem;font-weight:700;font-size:.9rem}.user-avatar{width:32px;height:32px;background:var(--primary);color:#fff;border-radius:50%;display:grid;place-items:center;font-weight:800;font-size:.82rem}
    .main-container{max-width:1480px;margin:0 auto;padding:2rem 1.5rem;display:grid;grid-template-columns:280px 1fr;gap:2rem}.sidebar{position:sticky;top:90px;height:fit-content}.sidebar-section,.product-card{background:var(--white);border-radius:.75rem;box-shadow:var(--shadow);border:1px solid rgba(229,231,235,.75)}.sidebar-section{padding:1.5rem;margin-bottom:1.5rem}.sidebar-title{font-size:1rem;font-weight:800;margin-bottom:1rem;color:var(--text-primary)}.category-list{list-style:none}.category-item{display:flex;justify-content:space-between;align-items:center;padding:.62rem 0;color:var(--text-secondary);border-radius:.38rem;transition:.2s;font-weight:650}.category-item:hover,.category-item.active{background:#f0fdf4;color:var(--primary);padding-left:.75rem;padding-right:.5rem}.category-count{background:#eef2f7;color:#4b5563;font-size:.75rem;font-weight:800;padding:.12rem .5rem;border-radius:999px}
    .product-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1.5rem}.product-card{overflow:hidden;transition:.2s}.product-card:hover{transform:translateY(-2px);box-shadow:var(--shadow-lg)}.product-image{height:200px;background:linear-gradient(135deg,#e9f7ec,#fff7d6);position:relative;display:grid;place-items:center;color:var(--primary);font-size:4rem}.product-badge{position:absolute;top:.75rem;left:.75rem;background:rgba(26,95,42,.94);color:#fff;padding:.32rem .55rem;border-radius:999px;font-size:.75rem;font-weight:850}.product-actions{position:absolute;top:.75rem;right:.75rem}.product-action-btn{width:36px;height:36px;border-radius:50%;border:0;background:#fff;color:var(--text-secondary);box-shadow:var(--shadow);cursor:pointer}.product-info{padding:1rem}.product-title{font-size:1rem;font-weight:850;margin-bottom:.5rem;line-height:1.35}.product-seller,.product-location{display:flex;align-items:center;gap:.4rem;color:var(--text-secondary);font-size:.84rem;margin-bottom:.45rem}.verified-badge{color:var(--success)}.seller-name{font-weight:750;color:var(--text-primary)}.product-price{font-size:1.28rem;font-weight:900;color:var(--primary);margin:.7rem 0}.product-price span{font-size:.85rem;font-weight:500;color:var(--text-secondary)}.product-buttons{display:grid;grid-template-columns:1fr 1.2fr;gap:.65rem}
    .pagination{display:flex;justify-content:center;gap:.5rem;margin-top:2.5rem}.page-link{display:grid;place-items:center;width:40px;height:40px;border:1px solid var(--border);border-radius:.5rem;background:var(--white);font-weight:700;color:var(--text-secondary)}.page-link.active{background:var(--primary);color:#fff;border-color:var(--primary)}.page-link:hover:not(.active){background:#f3f4f6}
    .mk-alert{padding:1rem;border-radius:.75rem;margin-bottom:1.5rem;font-weight:700}.mk-alert.ok{background:#f0fdf4;color:var(--primary-dark);border:1px solid #bbf7d0}
    .empty{background:#fff;border:1px dashed var(--border);border-radius:.75rem;padding:2rem;text-align:center;color:var(--text-secondary);font-weight:750}
    @media(max-width:1200px){.product-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:900px){.main-container{grid-template-columns:1fr}.sidebar{display:none}}@media(max-width:620px){.product-grid{grid-template-columns:1fr}}
  </style>
</head>
<body>
  <header class="header">
    <div class="header-container">
      <a href="../index.php" class="logo">
        <div class="logo-icon"><img src="<?= e($logo) ?>" alt="NATCODEV"></div>
        <div class="logo-text">NATCODEV<span>Marketplace</span></div>
      </a>
      <nav>
        <ul class="nav-menu">
          <li><a href="index.php">Marketplace</a></li>
          <li><a href="stores.php">Seller Directory</a></li>
          <li><a href="featured.php" class="active">Featured</a></li>
          <li><a href="orders.php">Orders</a></li>
          <li><a href="../dashboard/wallet.php">Wallet</a></li>
        </ul>
      </nav>
      <div class="header-actions">
        <a class="notification-btn" href="cart.php" aria-label="Cart"><i class="fas fa-shopping-cart"></i><?php if (market_cart_count() > 0): ?><span class="badge"><?= market_cart_count() ?></span><?php endif; ?></a>
        <a class="user-menu" href="<?= $user ? '../buyer/index.php' : '../buyer/login.php' ?>">
          <div class="user-avatar"><?= e($initials) ?></div>
          <span><?= e((string) ($user['name'] ?? 'Guest')) ?></span>
        </a>
      </div>
    </div>
  </header>

  <div class="main-container">
    <aside class="sidebar">
      <div class="sidebar-section">
        <h3 class="sidebar-title">Categories</h3>
        <ul class="category-list">
          <li><a class="category-item" href="index.php"><span><i class="fas fa-th-large" style="margin-right:.5rem"></i>All Categories</span><span class="category-count"><?= number_format($stats['total_listings']) ?></span></a></li>
          <?php foreach ($categories as $cat): ?>
            <li><a class="category-item" href="index.php?category_id=<?= (int) $cat['id'] ?>"><span><?= e((string) $cat['name']) ?></span><span class="category-count"><?= number_format((int) $cat['listing_count']) ?></span></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </aside>

    <main class="main-content">
      <div style="margin-bottom:2rem">
        <h1 style="font-size:2rem; color:var(--primary); margin-bottom:.5rem">Featured Products & Services</h1>
        <p style="color:var(--text-secondary); font-weight:600">Discover handpicked premium offerings from our verified community of coconut business sellers.</p>
      </div>

      <?php if ($notice): ?><div class="mk-alert ok"><?= e($notice) ?> <a href="cart.php" style="text-decoration:underline">View cart</a></div><?php endif; ?>

      <?php if ($listings): ?>
        <div class="product-grid">
          <?php foreach ($listings as $listing): ?>
            <article class="product-card">
              <div class="product-image">
                <img src="<?= e(market_listing_image_url($listing)) ?>" alt="<?= e((string) $listing['title']) ?>" style="width:100%;height:100%;object-fit:cover">
                <?php if ((string) ($listing['verification_status'] ?? '') === 'verified'): ?><div class="product-badge"><i class="fas fa-check-circle"></i> Verified</div><?php endif; ?>
              </div>
              <div class="product-info">
                <h3 class="product-title"><a href="product.php?id=<?= (int) $listing['id'] ?>"><?= e((string) $listing['title']) ?></a></h3>
                <div class="product-seller"><i class="fas fa-check-circle verified-badge"></i><a class="seller-name" href="store.php?seller=<?= e((string) $listing['seller_slug']) ?>"><?= e((string) $listing['store_name']) ?></a></div>
                <div class="product-location"><i class="fas fa-map-marker-alt"></i><?= e((string) ($listing['location_label'] ?: $listing['seller_location'] ?: 'Nigeria')) ?></div>
                <div class="product-price"><?= e(marketplace_money((float) $listing['price'])) ?> <span>/ <?= e((string) ($listing['unit'] ?: $listing['price_unit'] ?: 'unit')) ?></span></div>
                <div class="product-buttons">
                  <a class="btn btn-outline" href="product.php?id=<?= (int) $listing['id'] ?>">View</a>
                  <form method="post">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="add_to_cart">
                    <input type="hidden" name="listing_id" value="<?= (int) $listing['id'] ?>">
                    <input type="hidden" name="quantity" value="1">
                    <button class="btn btn-primary" type="submit"><i class="fas fa-cart-plus"></i> Add to Cart</button>
                  </form>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
          <div class="pagination">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
              <a href="featured.php?page=<?= $i ?>" class="page-link <?= $page === $i ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
          </div>
        <?php endif; ?>

      <?php else: ?>
        <div class="empty">
          <i class="fas fa-star" style="font-size:3rem; color:#e5e7eb; margin-bottom:1rem; display:block"></i>
          <p>No featured products available at the moment. Please check back later!</p>
          <a href="index.php" class="btn btn-primary" style="margin-top:1.5rem">Browse All Products</a>
        </div>
      <?php endif; ?>
    </main>
  </div>
</body>
</html>
