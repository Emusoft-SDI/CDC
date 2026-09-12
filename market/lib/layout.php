<?php
declare(strict_types=1);

function market_css(): string
{
    return '
    :root{--mk-green:#0f5b2c;--mk-green-2:#178448;--mk-deep:#0c2f1d;--mk-mint:#eef8ef;--mk-gold:#d6a928;--mk-teal:#20a69a;--mk-blue:#2374c6;--mk-orange:#f59e0b;--mk-red:#dc2626;--mk-ink:#101828;--mk-muted:#667085;--mk-line:#dce8dc;--mk-bg:#f4f9f1;--mk-panel:#fff;--mk-shadow:0 18px 45px rgba(16,24,40,.08)}
    *{box-sizing:border-box} body{margin:0;background:linear-gradient(135deg,#f4f9f1 0%,#edf7f1 52%,#f9fbf4 100%);color:var(--mk-ink);font-family:"Segoe UI",Arial,sans-serif} a{color:var(--mk-green);font-weight:850;text-decoration:none} a:hover{text-decoration:none;color:var(--mk-green-2)}
    .mk-brand{display:flex;gap:12px;align-items:center}.mk-brand img{width:48px;height:48px;border-radius:50%;background:#fff;border:1px solid var(--mk-line);object-fit:contain}.mk-brand strong{display:block;font-size:1.15rem;line-height:1}.mk-brand span{display:block;color:var(--mk-muted);font-size:.78rem;line-height:1.25;margin-top:3px}
    .mk-main{min-width:0}.mk-top{position:sticky;top:0;z-index:20;background:rgba(255,255,255,.92);backdrop-filter:blur(14px);border-bottom:1px solid rgba(16,24,40,.08);padding:14px 26px;display:flex;gap:16px;align-items:center;justify-content:space-between}.mk-search{flex:1;max-width:680px;position:relative}.mk-search input{width:100%;border:1px solid var(--mk-line);border-radius:10px;background:#fff;padding:13px 46px 13px 15px;font:inherit}.mk-search button{position:absolute;right:6px;top:6px;border:0;background:var(--mk-green);color:#fff;border-radius:8px;height:34px;min-width:38px;font-weight:900}.mk-top-actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap}.mk-pill{border:1px solid var(--mk-line);background:#fff;border-radius:10px;padding:10px 12px;font-weight:900;color:#283827}.mk-pill.primary{background:linear-gradient(135deg,var(--mk-green),var(--mk-teal));border:0;color:#fff}.mk-content{padding:26px;max-width:1520px;margin:0 auto}
    .mk-hero{display:grid;grid-template-columns:minmax(0,1.3fr) minmax(320px,.7fr);gap:18px;margin-bottom:18px}.mk-hero-card{border-radius:14px;padding:26px;background:linear-gradient(135deg,rgba(15,91,44,.96),rgba(32,166,154,.88)),url("../assets/hero/hero-banner.jpg") center/cover;color:#fff;box-shadow:var(--mk-shadow);min-height:260px;display:grid;align-content:end}.mk-hero-card h1{margin:0 0 10px;font-size:clamp(2rem,4vw,4rem);line-height:1}.mk-hero-card p{margin:0;max-width:760px;color:#eaf8ee;line-height:1.55}.mk-kpis{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.mk-kpi{background:#fff;border:1px solid rgba(16,24,40,.08);border-radius:12px;padding:16px;box-shadow:var(--mk-shadow)}.mk-kpi b{display:block;font-size:1.7rem;color:var(--mk-green);line-height:1}.mk-kpi span{display:block;color:var(--mk-muted);font-weight:800;font-size:.82rem;margin-top:6px}
    .mk-section{background:#fff;border:1px solid rgba(16,24,40,.08);border-radius:12px;box-shadow:var(--mk-shadow);padding:18px;margin-bottom:18px}.mk-section-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px}.mk-section-head h2{margin:0;color:#103d1b;font-size:1.2rem}.mk-section-head p{margin:4px 0 0;color:var(--mk-muted)}.mk-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.mk-card{border:1px solid var(--mk-line);border-radius:12px;background:#fff;overflow:hidden;display:flex;flex-direction:column;min-height:100%;transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease}.mk-card:hover{transform:translateY(-2px);border-color:#b8dbbc;box-shadow:0 14px 28px rgba(16,24,40,.1)}.mk-img{height:150px;background:linear-gradient(135deg,#dff4e2,#fff7d7);display:grid;place-items:center;color:var(--mk-green);font-size:2.3rem;font-weight:950}.mk-card-body{padding:13px;display:grid;gap:8px}.mk-card h3{margin:0;color:#132719;font-size:1rem;line-height:1.3}.mk-meta{color:var(--mk-muted);font-size:.84rem;line-height:1.35}.mk-price{font-size:1.18rem;font-weight:950;color:#0b3b1d}.mk-badges{display:flex;gap:6px;flex-wrap:wrap}.mk-badge{display:inline-flex;align-items:center;gap:5px;border-radius:999px;background:#edf8ee;color:#12622f;padding:5px 8px;font-size:.72rem;font-weight:950}.mk-badge.gold{background:#fff6d7;color:#8a6100}.mk-badge.blue{background:#eaf3ff;color:#175eaa}.mk-badge.red{background:#fff1f2;color:#b91c1c}.mk-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:auto}.mk-btn,button.mk-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;border:0;border-radius:9px;padding:10px 12px;background:var(--mk-green);color:#fff;font-weight:950;cursor:pointer;text-decoration:none}.mk-btn:hover{background:var(--mk-green-2);color:#fff}.mk-btn.secondary{background:#eef8ef;color:var(--mk-green);border:1px solid var(--mk-line)}.mk-btn.gold{background:var(--mk-gold);color:#102514}
    .mk-filter{display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:10px;align-items:end}.mk-field label{display:block;font-size:.78rem;font-weight:950;color:#344232;margin:0 0 6px}.mk-field input,.mk-field select,.mk-field textarea{width:100%;border:1px solid var(--mk-line);border-radius:9px;padding:11px 12px;font:inherit;background:#fff}.mk-field textarea{min-height:110px}.mk-table{width:100%;border-collapse:separate;border-spacing:0}.mk-table th,.mk-table td{text-align:left;padding:11px;border-bottom:1px solid #edf3ec;vertical-align:top}.mk-table th{background:#f1faef;color:#173c20;font-size:.82rem;text-transform:uppercase;letter-spacing:.04em}.mk-alert{padding:12px 14px;border-radius:10px;margin-bottom:14px;font-weight:800}.mk-alert.ok{background:#eaf8ef;color:#11602f;border:1px solid #bce6c8}.mk-alert.err{background:#fff1f2;color:#b91c1c;border:1px solid #fecdd3}.mk-two{display:grid;grid-template-columns:minmax(0,1.4fr) minmax(330px,.6fr);gap:18px}.mk-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.mk-form-grid .wide{grid-column:1/-1}.mk-store-head{display:flex;gap:16px;align-items:center}.mk-store-avatar{width:74px;height:74px;border-radius:18px;background:linear-gradient(135deg,#dcfce7,#fff7d7);display:grid;place-items:center;color:var(--mk-green);font-size:1.35rem;font-weight:950;flex:0 0 auto;overflow:hidden}.mk-store-avatar img{width:100%;height:100%;object-fit:cover;display:block}.mk-empty{border:1px dashed var(--mk-line);border-radius:10px;padding:18px;color:var(--mk-muted);background:#fbfdf9}.mk-tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px}.mk-tabs a{padding:10px 12px;border-radius:9px;background:#fff;border:1px solid var(--mk-line);color:#29462c}.mk-tabs a.active{background:var(--mk-green);color:#fff;border-color:var(--mk-green)}
    .public-market{min-height:100vh}.mk-public-top{position:sticky;top:0;z-index:50;background:rgba(255,255,255,.96);backdrop-filter:blur(14px);border-bottom:1px solid rgba(16,24,40,.08);box-shadow:0 8px 24px rgba(16,24,40,.04)}.mk-public-bar{max-width:1480px;margin:0 auto;padding:12px 26px;display:flex;align-items:center;justify-content:space-between;gap:18px}.public-brand{color:var(--mk-green)}.mk-public-nav{display:flex;align-items:center;gap:6px;flex-wrap:wrap}.mk-public-nav a{padding:9px 10px;border-radius:8px;color:#344232;font-weight:900}.mk-public-nav a.active,.mk-public-nav a:hover{background:#eef8ef;color:var(--mk-green)}.mk-public-search{max-width:1480px;margin:0 auto;padding:0 26px 12px}.mk-public-search .mk-search{max-width:760px}.public-market .mk-content{max-width:1480px}.public-market .mk-section{box-shadow:0 10px 30px rgba(16,24,40,.07)}
    @media(max-width:1180px){.mk-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.mk-hero,.mk-two{grid-template-columns:1fr}.mk-filter{grid-template-columns:1fr 1fr}.mk-form-grid{grid-template-columns:1fr}.mk-public-bar{align-items:flex-start;flex-direction:column}.mk-public-nav{width:100%}}
    @media(max-width:720px){.mk-content{padding:16px}.mk-grid,.mk-kpis,.mk-filter{grid-template-columns:1fr}.mk-public-bar,.mk-public-search{padding-left:16px;padding-right:16px}.mk-top{flex-wrap:wrap}.mk-top-actions{width:100%;justify-content:flex-start}.mk-top-actions .buyer-dashboard{order:-1;width:100%;justify-content:center;padding:12px 14px}.mk-public-nav{overflow:auto;flex-wrap:nowrap}}
    ';
}

function market_icon(string $name): string
{
    $icons = [
        'home' => '<i class="fas fa-home"></i>',
        'store' => '<i class="fas fa-store"></i>',
        'seller' => '<i class="fas fa-user-tie"></i>',
        'cart' => '<i class="fas fa-shopping-cart"></i>',
        'orders' => '<i class="fas fa-receipt"></i>',
        'catalog' => '<i class="fas fa-list"></i>',
        'leaf' => '<i class="fas fa-leaf"></i>',
        'shield' => '<i class="fas fa-shield-alt"></i>',
        'search' => '<i class="fas fa-search"></i>',
        'logout' => '<i class="fas fa-sign-out-alt"></i>',
        'dash' => '<i class="fas fa-chart-line"></i>',
        'plus' => '+',
        'chat' => '<i class="fas fa-comments"></i>',
        'truck' => '<i class="fas fa-truck"></i>',
    ];
    return $icons[$name] ?? '&bull;';
}

function market_header(string $title, string $active = 'marketplace', ?PDO $pdo = null): void
{
    $pdo = $pdo ?: market_boot();
    $user = market_user($pdo);
    $logo = market_asset_logo();
    $q = trim((string) ($_GET['q'] ?? ''));
    $cartCount = market_cart_count();
    $initials = $user ? market_initials((string) ($user['name'] ?? 'User')) : 'NT';
    $userRoles = $user ? market_user_role_keys($pdo, $user) : [];
    $hasBuyerAccess = $user ? (bool) array_intersect(['buyer', 'consumer', 'user'], $userRoles) : false;
    $categoryMenu = [];
    $stateMenu = [];
    try {
        $categoryMenu = $pdo->query("SELECT id, name FROM marketplace_categories WHERE is_active = 1 ORDER BY sort_order, name LIMIT 10")->fetchAll();
    } catch (Throwable $e) {
        $categoryMenu = [];
    }
    try {
        $stateMenu = app_table_exists($pdo, 'nigeria_states') ? $pdo->query("SELECT state_name FROM nigeria_states ORDER BY state_name LIMIT 12")->fetchAll() : [];
    } catch (Throwable $e) {
        $stateMenu = [];
    }
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($title) ?> - NATCODEV Marketplace</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root{--primary:#1a5f2a;--primary-dark:#144a21;--primary-light:#2d8041;--secondary:#f5f5f5;--accent:#ffc107;--text-primary:#1f2937;--text-secondary:#6b7280;--border:#e5e7eb;--success:#10b981;--warning:#f59e0b;--danger:#ef4444;--white:#fff;--shadow:0 1px 3px rgba(0,0,0,.1),0 1px 2px rgba(0,0,0,.06);--shadow-lg:0 10px 15px -3px rgba(0,0,0,.1),0 4px 6px -2px rgba(0,0,0,.05);--mk-line:var(--border)}
    *{margin:0;padding:0;box-sizing:border-box}body{font-family:Inter,"Segoe UI",Arial,sans-serif;background:#f9fafb;color:var(--text-primary);line-height:1.5}a{text-decoration:none;color:inherit}
    .header{background:var(--white);border-bottom:1px solid var(--border);position:sticky;top:0;z-index:100}.header-container{max-width:1480px;margin:0 auto;padding:0 1.5rem;height:70px;display:flex;align-items:center;justify-content:space-between;gap:1.5rem}
    .logo{display:flex;align-items:center;gap:.75rem;color:var(--primary);min-width:210px}.logo-icon{width:42px;height:42px;background:#fff;border:1px solid var(--border);border-radius:9px;display:grid;place-items:center;overflow:hidden}.logo-icon img{width:100%;height:100%;object-fit:contain}.logo-text{font-size:1.25rem;font-weight:800;line-height:1.1}.logo-text span{display:block;font-size:.72rem;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.05em}
    .nav-menu{display:flex;gap:1.1rem;list-style:none;align-items:center}.nav-menu a,.nav-menu summary{color:var(--text-secondary);font-weight:800;font-size:.94rem;padding:.5rem 0;border-bottom:2px solid transparent;cursor:pointer;list-style:none}.nav-menu a:hover,.nav-menu a.active,.nav-menu details[open]>summary{color:var(--primary);border-bottom-color:var(--primary)}.nav-menu details{position:relative}.nav-menu summary::-webkit-details-marker{display:none}.mk-dropdown{position:absolute;top:100%;left:0;min-width:230px;background:#fff;border:1px solid var(--border);border-radius:.65rem;box-shadow:var(--shadow-lg);padding:.55rem;display:grid;gap:.15rem;z-index:150}.mk-dropdown a{padding:.55rem .65rem;border:0;border-radius:.45rem;white-space:nowrap}.mk-dropdown a:hover{background:#f3f7f1}
    .header-actions{display:flex;align-items:center;gap:.65rem}.header-actions .mk-auth{border:1px solid var(--border);border-radius:.5rem;padding:.58rem .85rem;font-weight:850;color:var(--text-secondary);background:#fff}.header-actions .mk-auth.primary{background:var(--primary);border-color:var(--primary);color:#fff}.notification-btn{position:relative;background:none;border:0;font-size:1.2rem;color:var(--text-secondary);cursor:pointer;padding:.5rem}.badge{position:absolute;top:0;right:0;background:var(--danger);color:#fff;font-size:.62rem;font-weight:700;padding:.12rem .36rem;border-radius:999px}.user-menu{display:flex;align-items:center;gap:.55rem;min-width:0;max-width:220px;padding:.45rem .7rem;background:var(--secondary);border-radius:.5rem;font-weight:700;font-size:.9rem}.user-avatar{width:32px;height:32px;flex:0 0 32px;background:var(--primary);color:#fff;border-radius:50%;display:grid;place-items:center;font-weight:800;font-size:.82rem}.user-menu span{display:block;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.header-actions .buyer-dashboard{white-space:nowrap}
    .mk-section{max-width:1480px;margin:2rem auto;padding:0 1.5rem}.mk-section-head{display:flex;justify-content:space-between;align-items:center;gap:1.5rem;margin-bottom:1.5rem}.mk-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1.5rem}
    .mk-card{background:var(--white);border-radius:.75rem;box-shadow:var(--shadow);border:1px solid var(--border);overflow:hidden;transition:.2s}.mk-card:hover{transform:translateY(-2px);box-shadow:var(--shadow-lg)}
    .mk-img{display:block;height:200px;background:#f3f4f6;position:relative}.mk-img img{width:100%;height:100%;object-fit:cover}.mk-badge{position:absolute;top:.75rem;left:.75rem;background:rgba(26,95,42,.94);color:#fff;padding:.32rem .55rem;border-radius:999px;font-size:.75rem;font-weight:850}
    .mk-card-body{padding:1rem}.mk-card-body h3{font-size:1rem;font-weight:850;margin-bottom:.5rem}.mk-meta{color:var(--text-secondary);font-size:.84rem;margin-bottom:.45rem;display:flex;align-items:center;gap:.4rem}
    .mk-price{font-size:1.2rem;font-weight:900;color:var(--primary);margin:.75rem 0}.mk-actions{display:grid;grid-template-columns:1fr 1fr;gap:.65rem;margin-top:1rem}
    .mk-btn,.btn{display:inline-flex;align-items:center;justify-content:center;gap:.5rem;padding:.65rem 1rem;border-radius:.5rem;font-weight:800;cursor:pointer;border:1px solid transparent;transition:.2s}
    .mk-btn.primary,.btn-primary{background:var(--primary);color:#fff}.mk-btn.primary:hover{background:var(--primary-dark)}
    .mk-btn.secondary,.btn-outline{background:#fff;color:var(--primary);border-color:var(--border)}.mk-btn.secondary:hover{background:#f9fafb}
    .mk-empty{background:var(--white);border:1px dashed var(--border);padding:3rem;text-align:center;border-radius:1rem;color:var(--text-secondary);font-weight:700}
    .mk-alert{padding:1rem;border-radius:.5rem;margin-bottom:1.5rem;font-weight:700}.mk-alert.ok{background:#f0fdf4;color:var(--primary-dark);border:1px solid #bbf7d0}.mk-alert.err{background:#fef2f2;color:var(--danger);border:1px solid #fecaca}
    .mk-store-head{display:flex;gap:1.5rem;align-items:center}.mk-store-avatar{width:90px;height:90px;border-radius:1rem;background:linear-gradient(135deg,#e0ffe0,#fff7e0);display:grid;place-items:center;color:var(--primary);font-size:3rem;flex:0 0 auto}.mk-store-head .mk-badges{margin-bottom:.5rem}.mk-store-head .mk-badge{font-size:.85rem;padding:.4rem .7rem}
    <?= market_css() ?>
  </style>
</head>
<body>
  <header class="header">
    <div class="header-container">
      <a href="index.php" class="logo">
        <div class="logo-icon"><img src="<?= e($logo) ?>" alt="NATCODEV"></div>
        <div class="logo-text">NATCODEV<span>Marketplace</span></div>
      </a>
      <nav>
        <ul class="nav-menu">
          <li><a href="../index.php">Home</a></li>
          <li><a href="index.php" class="<?= $active === 'marketplace' ? 'active' : '' ?>">Shop</a></li>
          <li><details><summary>Categories <span aria-hidden="true">&#x25BE;</span></summary><div class="mk-dropdown"><?php foreach ($categoryMenu as $cat): ?><a href="index.php?category_id=<?= (int) $cat['id'] ?>"><?= e((string) $cat['name']) ?></a><?php endforeach; ?><?php if (!$categoryMenu): ?><a href="index.php">All Categories</a><?php endif; ?></div></details></li>
          <li><a href="stores.php" class="<?= $active === 'stores' ? 'active' : '' ?>">Sellers</a></li>
          <li><a href="featured.php" class="<?= $active === 'featured' ? 'active' : '' ?>">Deals</a></li>
          <li><details><summary>Shop by State <span aria-hidden="true">&#x25BE;</span></summary><div class="mk-dropdown"><?php foreach ($stateMenu as $state): ?><a href="index.php?state=<?= rawurlencode((string) $state['state_name']) ?>"><?= e((string) $state['state_name']) ?></a><?php endforeach; ?><?php if (!$stateMenu): ?><a href="index.php">All States</a><?php endif; ?></div></details></li>
          <li><a href="orders.php" class="<?= $active === 'orders' ? 'active' : '' ?>">Orders</a></li>
          <li><a href="../support/index.php?category=marketplace">Support</a></li>
        </ul>
      </nav>
      <div class="header-actions">
        <a class="notification-btn" href="index.php#market-search" aria-label="Search"><i class="fas fa-search"></i></a>
        <a class="notification-btn" href="cart.php" aria-label="Cart"><i class="fas fa-shopping-cart"></i><?php if ($cartCount > 0): ?><span class="badge"><?= $cartCount ?></span><?php endif; ?></a>
        <?php if ($hasBuyerAccess): ?>
          <a class="mk-auth primary buyer-dashboard" href="../buyer/index.php">Buyer Dashboard</a>
        <?php else: ?>
          <a class="mk-auth" href="seller-login.php">Login</a>
          <a class="mk-auth primary" href="seller-join.php">Sign up</a>
        <?php endif; ?>
      </div>
    </div>
  </header>
  <main>

<?php
}

function market_footer(): void
{
    $office = app_contact_office();
    ?>
  </main>
  <footer style="background:#fff;border-top:1px solid #e5e7eb;padding:3rem 1.5rem;margin-top:4rem">
    <div style="max-width:1480px;margin:0 auto;display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:2rem">
      <div>
        <div style="color:var(--primary);font-weight:800;font-size:1.2rem;margin-bottom:1rem">NATCODEV Marketplace</div>
        <p style="color:var(--text-secondary);font-size:.9rem;line-height:1.6">The official hub for coconut business transactions, connecting growers, providers, and offtakers across Nigeria.</p>
      </div>
      <div>
        <h4 style="margin-bottom:1rem">Quick Links</h4>
        <ul style="list-style:none;color:var(--text-secondary);font-size:.9rem;display:grid;gap:.5rem">
          <li><a href="index.php">Marketplace Home</a></li>
          <li><a href="stores.php">Seller Directory</a></li>
          <li><a href="seller-join.php">Become a Seller</a></li>
          <li><a href="orders.php">Track Orders</a></li>
        </ul>
      </div>
      <div>
        <h4 style="margin-bottom:1rem">Support</h4>
        <ul style="list-style:none;color:var(--text-secondary);font-size:.9rem;display:grid;gap:.5rem">
          <li><a href="../support/index.php?category=marketplace">Help Center</a></li>
          <li><a href="../buyer/wallet.php">Wallet & Payments</a></li>
          <li><a href="../contact.php">Contact Us</a></li>
          <li><a href="../terms.php">Terms of Service</a></li>
        </ul>
        <div style="margin-top:1rem;color:var(--text-secondary);font-size:.9rem;line-height:1.6">
          <strong style="display:block;color:var(--text-primary);margin-bottom:.35rem">Head Office</strong>
          <span><?= e(implode(', ', $office['address_lines'])) ?></span><br>
          <a href="tel:<?= e($office['phone_tel']) ?>">Call Us: <?= e($office['phone_display']) ?></a><br>
          <a href="mailto:<?= e($office['email']) ?>"><?= e($office['email']) ?></a>
        </div>
      </div>
    </div>
    <div style="max-width:1480px;margin:2rem auto 0;padding-top:2rem;border-top:1px solid #e5e7eb;display:flex;justify-content:space-between;color:var(--text-secondary);font-size:.85rem">
      <div>&copy; 2026 NATCODEV. All rights reserved.</div>
      <div style="display:flex;gap:1.5rem"><a href="../privacy.php">Privacy</a><a href="../cookies.php">Cookies</a></div>
    </div>
  </footer>
  <script src="../lib/location-picker.js"></script>
</body>
</html>
<?php
}
