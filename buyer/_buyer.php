<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../market/_market.php';
require_once __DIR__ . '/../lib/monnify.php';
require_once __DIR__ . '/../lib/support.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function buyer_boot(): PDO
{
    $pdo = db();
    app_ensure_core_schema($pdo);
    marketplace_ensure_schema($pdo);
    wallet_ensure_schema($pdo);
    support_ensure_schema($pdo);
    foreach ([
        'platform_role' => "VARCHAR(60) NULL",
        'account_status' => "VARCHAR(40) NOT NULL DEFAULT 'active'",
        'phone' => "VARCHAR(30) NULL",
        'location' => "VARCHAR(255) NULL",
        'profile_picture' => "VARCHAR(255) NULL",
    ] as $column => $definition) {
        app_add_column_if_missing($pdo, 'users', $column, $definition);
    }
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS buyer_profiles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL UNIQUE,
            preferred_state VARCHAR(120) NULL,
            preferred_lga VARCHAR(120) NULL,
            delivery_address TEXT NULL,
            buyer_type VARCHAR(60) NOT NULL DEFAULT 'individual',
            interests TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_buyer_profiles_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'buyer_profiles');
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_role_assignments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            role_key VARCHAR(60) NOT NULL,
            scope_type VARCHAR(40) NOT NULL DEFAULT 'global',
            scope_value VARCHAR(160) NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'active',
            notes TEXT NULL,
            assigned_by INT NULL,
            assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            revoked_at DATETIME NULL,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_user_role_scope (user_id, role_key, scope_type, scope_value),
            INDEX idx_user_role_active (user_id, role_key, status),
            INDEX idx_user_role_scope (role_key, scope_type, scope_value, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'user_role_assignments');
    foreach ([
        'business_name' => 'VARCHAR(180) NULL',
        'delivery_phone' => 'VARCHAR(40) NULL',
        'delivery_notes' => 'TEXT NULL',
    ] as $column => $definition) {
        app_add_column_if_missing($pdo, 'buyer_profiles', $column, $definition);
    }
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS buyer_knowledge_base (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(180) NOT NULL,
            body TEXT NOT NULL,
            category VARCHAR(80) NOT NULL DEFAULT 'general',
            status VARCHAR(30) NOT NULL DEFAULT 'published',
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_buyer_kb_title (title),
            INDEX idx_buyer_kb_status (status, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'buyer_knowledge_base');
    $kbCount = (int) $pdo->query("SELECT COUNT(*) FROM buyer_knowledge_base")->fetchColumn();
    if ($kbCount === 0) {
        $seed = $pdo->prepare("INSERT INTO buyer_knowledge_base (title, body, category, sort_order) VALUES (?, ?, ?, ?)");
        foreach ([
            ['Order not updating', 'Open the order detail, copy the checkout reference, and create an order support ticket from Buyer Help & Support.', 'orders', 10],
            ['Payment or refund issue', 'Open Wallet & Finance and copy the payment or wallet transaction reference before contacting buyer support.', 'payments', 20],
            ['Delivery address change', 'Update your buyer profile first, then send the checkout reference to support before shipment is completed.', 'delivery', 30],
            ['Seller quote question', 'Use Quote Requests for listing-specific conversations. Use Buyer Support for payment, fulfilment, or account issues.', 'quotes', 40],
        ] as $row) {
            $seed->execute($row);
        }
    }
    return $pdo;
}

function buyer_user(PDO $pdo): ?array
{
    $user = current_user($pdo);
    if (!$user) {
        return null;
    }
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([(int) $user['id']]);
    return $stmt->fetch() ?: $user;
}

function buyer_require(PDO $pdo): array
{
    $user = buyer_user($pdo);
    if (!$user) {
        redirect_to('login.php');
    }
    if (!buyer_has_access($pdo, $user)) {
        redirect_to('register.php?activate=buyer');
    }
    return $user;
}

function buyer_has_access(PDO $pdo, ?array $user): bool
{
    if (!$user) {
        return false;
    }
    $role = strtolower((string) ($user['platform_role'] ?? $user['role'] ?? ''));
    if ($role === 'buyer' || (string) ($user['role'] ?? '') === 'admin') {
        return true;
    }
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_role_assignments WHERE user_id = ? AND role_key = 'buyer' AND status = 'active'");
    $stmt->execute([(int) $user['id']]);
    return (int) $stmt->fetchColumn() > 0;
}

function buyer_activate_access(PDO $pdo, array $user): void
{
    $pdo->prepare("
        INSERT INTO user_role_assignments (user_id, role_key, scope_type, scope_value, status, notes)
        VALUES (?, 'buyer', 'global', '', 'active', 'Self-activated buyer workspace access')
        ON DUPLICATE KEY UPDATE status = 'active', revoked_at = NULL, notes = VALUES(notes)
    ")->execute([(int) $user['id']]);
    $pdo->prepare("INSERT INTO buyer_profiles (user_id, buyer_type) VALUES (?, 'individual') ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP")->execute([(int) $user['id']]);
}

function buyer_initials(string $name): string
{
    return market_initials($name);
}

function buyer_counts(PDO $pdo, ?array $user): array
{
    $cartCount = market_cart_count();
    $orders = 0;
    $quotes = 0;
    $academy = 0;
    $wallet = 0.0;
    if ($user) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM marketplace_orders WHERE buyer_user_id = ?");
        $stmt->execute([(int) $user['id']]);
        $orders = (int) $stmt->fetchColumn();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM marketplace_inquiries WHERE buyer_user_id = ?");
        $stmt->execute([(int) $user['id']]);
        $quotes = (int) $stmt->fetchColumn();
        if (app_table_exists($pdo, 'academy_enrollments')) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM academy_enrollments WHERE user_id = ?");
            $stmt->execute([(int) $user['id']]);
            $academy = (int) $stmt->fetchColumn();
        } elseif (app_table_exists($pdo, 'webinar_registrations')) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM webinar_registrations WHERE user_id = ?");
            $stmt->execute([(int) $user['id']]);
            $academy = (int) $stmt->fetchColumn();
        }
        $walletRow = wallet_get_or_create($pdo, (int) $user['id']);
        $wallet = (float) ($walletRow['balance'] ?? 0);
    }
    return compact('cartCount', 'orders', 'quotes', 'academy', 'wallet');
}

function buyer_money(float $amount): string
{
    return marketplace_money($amount);
}

function buyer_status_badge(string $status): string
{
    return '<span class="badge">' . e(marketplace_status_label($status)) . '</span>';
}

function buyer_support_categories(): array
{
    return array_intersect_key(support_categories(), array_flip(['marketplace', 'payments', 'account', 'technical', 'general']));
}

function buyer_knowledge_articles(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT * FROM buyer_knowledge_base WHERE status = 'published' ORDER BY sort_order ASC, title ASC LIMIT 20");
    return $stmt->fetchAll();
}

function buyer_nav(): array
{
    return [
        ['overview', 'Overview', 'fa-home', 'index.php'],
        ['marketplace', 'Marketplace', 'fa-store', 'marketplace.php'],
        ['cart', 'Cart', 'fa-cart-shopping', 'cart.php'],
        ['orders', 'Orders & Tracking', 'fa-truck-fast', 'orders.php'],
        ['quotes', 'Quote Requests', 'fa-file-signature', 'quotes.php'],
        ['academy', 'Academy', 'fa-graduation-cap', 'academy.php'],
        ['wallet', 'Wallet & Payments', 'fa-wallet', 'wallet.php'],
        ['messages', 'Messages', 'fa-comments', 'messages.php'],
        ['support', 'Support Desk', 'fa-headset', 'support.php'],
        ['profile', 'Profile', 'fa-user', 'profile.php'],
    ];
}

function buyer_page_start(string $title, string $active, ?array $user, array $counts = []): void
{
    $logo = app_primary_logo_url();
    $name = (string) ($user['name'] ?? 'Guest Buyer');
    $location = (string) ($user['location'] ?? 'Public Marketplace');
    $avatar = !empty($user['profile_picture']) ? '../' . ltrim((string) $user['profile_picture'], '/') : '';
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($title) ?> - NATCODEV Buyer</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    :root{--green:#06451f;--green2:#0b7a3b;--soft:#eef8ef;--gold:#d89b10;--teal:#0f9d8d;--blue:#2f72d8;--red:#d92d20;--ink:#101828;--muted:#667085;--line:#dfe8d8;--bg:#f7faf5;--shadow:0 16px 40px rgba(16,24,40,.08)}
    *{box-sizing:border-box}body{margin:0;background:var(--bg);font-family:"Segoe UI",Arial,sans-serif;color:var(--ink)}a{text-decoration:none;color:inherit}.shell{display:grid;grid-template-columns:280px 1fr;min-height:100vh}.side{background:linear-gradient(180deg,#06451f,#012f17);color:#fff;padding:20px;position:sticky;top:0;height:100vh;overflow:auto}.brand{display:flex;gap:12px;align-items:center;margin-bottom:26px}.brand img{width:58px;height:58px;border-radius:50%;background:#fff}.brand strong{font-size:1.55rem}.brand span{display:block;font-size:.78rem;line-height:1.25}.person{display:flex;gap:12px;align-items:center;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.16);border-radius:14px;padding:14px;margin-bottom:22px}.avatar{width:52px;height:52px;border-radius:50%;background:#e8f6ec;color:var(--green);display:grid;place-items:center;font-weight:950}.person small{display:block;color:#a6f2b7;font-weight:850}.nav{display:grid;gap:8px}.nav a{display:flex;gap:12px;align-items:center;padding:12px 13px;border-radius:9px;font-weight:900;color:#eef8ef}.nav a.active,.nav a:hover{background:linear-gradient(135deg,#118b42,#0d6b34)}.side-card{margin-top:28px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.16);border-radius:14px;padding:16px}.main{min-width:0}.top{height:72px;background:#fff;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;gap:18px;padding:0 26px;position:sticky;top:0;z-index:10}.search{max-width:620px;flex:1;position:relative}.search input{width:100%;border:1px solid var(--line);border-radius:10px;padding:13px 46px 13px 16px}.search i{position:absolute;left:14px;top:14px;color:var(--muted)}.search input{padding-left:40px}.top-actions{display:flex;align-items:center;gap:18px;font-weight:850}.content{padding:24px 28px}.page-head{display:flex;justify-content:space-between;gap:18px;align-items:flex-end;margin-bottom:18px}.page-head h1{font-size:2rem;margin:0;color:#08122b}.page-head p{margin:5px 0 0;color:#344054}.kpis{display:grid;grid-template-columns:repeat(6,1fr);gap:14px;margin-bottom:18px}.kpi{background:#fff;border:1px solid var(--line);border-radius:12px;padding:17px;box-shadow:var(--shadow);display:flex;gap:14px;align-items:center}.kpi i{width:54px;height:54px;border-radius:50%;display:grid;place-items:center;background:#e8f6ec;color:var(--green);font-size:1.35rem}.kpi b{font-size:1.45rem}.grid{display:grid;grid-template-columns:repeat(12,1fr);gap:16px}.card{background:#fff;border:1px solid var(--line);border-radius:12px;padding:18px;box-shadow:var(--shadow)}.span-3{grid-column:span 3}.span-4{grid-column:span 4}.span-5{grid-column:span 5}.span-6{grid-column:span 6}.span-7{grid-column:span 7}.span-8{grid-column:span 8}.span-12{grid-column:span 12}.card-head{display:flex;justify-content:space-between;gap:12px;align-items:center;margin-bottom:12px}.card h2,.card h3{margin:0;color:#08122b}.view{color:var(--green);font-weight:950}.badge{display:inline-flex;align-items:center;gap:6px;border-radius:999px;background:#e8f6ec;color:var(--green);font-size:.78rem;font-weight:950;padding:5px 9px}.badge.gold{background:#fff3d6;color:#9a6500}.list{display:grid;gap:10px}.row{display:flex;justify-content:space-between;gap:12px;align-items:center;border-top:1px solid var(--line);padding-top:10px}.row:first-child{border-top:0;padding-top:0}.thumb{width:62px;height:56px;border-radius:9px;object-fit:cover}.action-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:12px}.quick{border:1px solid var(--line);border-radius:10px;padding:14px;text-align:center;background:#fbfdf9;font-weight:900}.quick i{display:grid;place-items:center;width:48px;height:48px;border-radius:50%;background:#e8f6ec;color:var(--green);font-size:1.3rem;margin:0 auto 8px}.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;border-radius:9px;border:1px solid var(--green);background:var(--green);color:#fff;font-weight:950;padding:10px 14px}.btn.light{background:#fff;color:var(--green)}.footer{display:flex;justify-content:space-between;gap:20px;color:#667085;font-size:.9rem;padding:18px 28px}.hero-card{background:linear-gradient(90deg,rgba(255,255,255,.96),rgba(255,255,255,.78)),url("../assets/public/buyer-marketplace-entry.png") center/cover;border:1px solid var(--line);border-radius:14px;padding:24px;min-height:190px}.form-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px}.form-grid .wide{grid-column:1/-1}label{font-weight:850}input,select,textarea{width:100%;border:1px solid var(--line);border-radius:9px;padding:12px;margin-top:6px}textarea{min-height:110px}.alert{padding:12px 14px;border-radius:10px;margin-bottom:14px;font-weight:850}.ok{background:#e8f6ec;color:var(--green)}.err{background:#fff1f2;color:#b42318}
    @media(max-width:1200px){.shell{grid-template-columns:1fr}.side{position:relative;height:auto}.kpis{grid-template-columns:repeat(2,1fr)}.span-3,.span-4,.span-5,.span-6,.span-7,.span-8{grid-column:span 12}.action-grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:700px){.top,.page-head,.footer{align-items:flex-start;flex-direction:column}.kpis,.form-grid,.action-grid{grid-template-columns:1fr}.content{padding:18px}}
  </style>
</head>
<body>
<div class="shell">
  <aside class="side">
    <a class="brand" href="../index.php"><img src="<?= e($logo) ?>" alt="NATCODEV"><span><strong>NATCODEV</strong><span>Buyer Marketplace & Academy</span></span></a>
    <div class="person"><span class="avatar"><?php if ($avatar): ?><img src="<?= e($avatar) ?>" alt="" style="width:100%;height:100%;border-radius:50%;object-fit:cover"><?php else: ?><?= e(buyer_initials($name)) ?><?php endif; ?></span><span><strong><?= e($name) ?></strong><small><?= $user ? 'Buyer Account' : 'Public Visitor' ?></small><small><?= e((string) ($user['account_status'] ?? 'active')) ?></small></span></div>
    <nav class="nav">
      <?php foreach (buyer_nav() as $item): ?>
        <a class="<?= $active === $item[0] ? 'active' : '' ?>" href="<?= e($item[3]) ?>"><i class="fas <?= e($item[2]) ?>"></i><?= e($item[1]) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="side-card"><strong>Buyer workspace</strong><p>Orders, wallet, refunds, support, and profile tools stay inside your buyer account.</p><a class="btn light" href="../market/index.php">Shop Marketplace</a></div>
  </aside>
  <main class="main">
    <header class="top">
      <form class="search" action="search.php" method="get"><i class="fas fa-search"></i><input name="q" placeholder="Search products, orders, tickets, sellers..."></form>
      <div class="top-actions"><a href="cart.php"><i class="fas fa-cart-shopping"></i> Cart</a><a href="orders.php"><i class="fas fa-truck"></i> Track</a><span><i class="fas fa-location-dot"></i> <?= e($location ?: 'Nigeria') ?></span><?php if ($user): ?><a href="../dashboard/logout.php">Logout</a><?php else: ?><a href="login.php">Login</a><?php endif; ?></div>
    </header>
    <section class="content">
<?php
}

function buyer_page_end(): void
{
    ?>
    </section>
    <footer class="footer"><span>&copy; <?= e(date('Y')) ?> NATCODEV. All rights reserved.</span><span>Empowering buyers, farmers, and coconut communities.</span><span>Last updated: <?= e(date('M j, Y h:i A')) ?></span></footer>
  </main>
</div>
<script src="../lib/location-picker.js"></script>
</body>
</html>
<?php
}

function buyer_simple_page(string $active, string $title, string $intro, callable $body): void
{
    $pdo = buyer_boot();
    $user = buyer_user($pdo);
    $counts = buyer_counts($pdo, $user);
    buyer_page_start($title, $active, $user, $counts);
    echo '<div class="page-head"><div><h1>' . e($title) . '</h1><p>' . e($intro) . '</p></div></div>';
    $body($pdo, $user, $counts);
    buyer_page_end();
}
