<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/platform-governance.php';
require_once __DIR__ . '/../market/_market.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function provider_boot(): PDO
{
    $pdo = db();
    app_ensure_core_schema($pdo);
    pg_ensure_schema($pdo);
    marketplace_ensure_schema($pdo);
    foreach ([
        'platform_role' => "VARCHAR(60) NULL",
        'account_status' => "VARCHAR(40) NOT NULL DEFAULT 'active'",
    ] as $column => $definition) {
        app_add_column_if_missing($pdo, 'users', $column, $definition);
    }
    app_add_column_if_missing($pdo, 'provider_registry', 'user_id', 'INT NULL');
    return $pdo;
}

function provider_user(PDO $pdo): ?array
{
    return current_user($pdo);
}

function provider_require(PDO $pdo): array
{
    $user = provider_user($pdo);
    if (!$user) {
        redirect_to('login.php');
    }
    return $user;
}

function provider_full_user(PDO $pdo, array $user): array
{
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([(int) $user['id']]);
    return $stmt->fetch() ?: $user;
}

function provider_records(PDO $pdo, ?array $user): array
{
    if (!$user) {
        return [];
    }
    $email = (string) ($user['email'] ?? '');
    $stmt = $pdo->prepare("
        SELECT pr.*, COALESCE(oc.offerings, 0) offerings
        FROM provider_registry pr
        LEFT JOIN (
            SELECT provider_id, COUNT(*) offerings
            FROM provider_offerings
            WHERE status = 'active'
            GROUP BY provider_id
        ) oc ON oc.provider_id = pr.id
        WHERE pr.user_id = ? OR pr.email = ?
        ORDER BY pr.created_at DESC
    ");
    $stmt->execute([(int) $user['id'], $email]);
    return $stmt->fetchAll();
}

function provider_active(PDO $pdo, ?array $user): ?array
{
    $providers = provider_records($pdo, $user);
    return $providers[0] ?? null;
}

function provider_status_label(?string $status): string
{
    return ucwords(str_replace('_', ' ', (string) ($status ?: 'pending_review')));
}

function provider_counts(PDO $pdo, ?array $provider, ?array $user): array
{
    $providerId = (int) ($provider['id'] ?? 0);
    $userId = (int) ($user['id'] ?? 0);
    $sellerIds = [];
    $counts = [
        'activeListings' => 0,
        'orders' => 0,
        'requests' => 0,
        'wallet' => 0.0,
        'coverageStates' => 0,
        'coverageLgas' => 0,
        'academy' => 0,
        'support' => 0,
    ];
    if ($providerId > 0) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM provider_offerings WHERE provider_id = ? AND status = 'active'");
        $stmt->execute([$providerId]);
        $counts['activeListings'] = (int) $stmt->fetchColumn();
        $stmt = $pdo->prepare("SELECT state_ids, lga_ids FROM provider_registry WHERE id = ? LIMIT 1");
        $stmt->execute([$providerId]);
        $coverage = $stmt->fetch() ?: [];
        $counts['coverageStates'] = count(array_filter(array_unique(array_map('trim', explode(',', (string) ($coverage['state_ids'] ?? ''))))));
        $counts['coverageLgas'] = count(array_filter(array_unique(array_map('trim', explode(',', (string) ($coverage['lga_ids'] ?? ''))))));
    }
    if ($user) {
        if ($userId > 0 && app_table_exists($pdo, 'marketplace_sellers')) {
            $stmt = $pdo->prepare("SELECT id FROM marketplace_sellers WHERE user_id = ?");
            $stmt->execute([$userId]);
            $sellerIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        }
        if (app_table_exists($pdo, 'marketplace_orders')) {
            if ($sellerIds) {
                $placeholders = implode(',', array_fill(0, count($sellerIds), '?'));
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM marketplace_orders WHERE seller_id IN ($placeholders)");
                $stmt->execute($sellerIds);
                $counts['orders'] = (int) $stmt->fetchColumn();
            }
        }
        if (app_table_exists($pdo, 'marketplace_inquiries')) {
            if ($sellerIds) {
                $placeholders = implode(',', array_fill(0, count($sellerIds), '?'));
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM marketplace_inquiries WHERE seller_id IN ($placeholders)");
                $stmt->execute($sellerIds);
                $counts['requests'] = (int) $stmt->fetchColumn();
            }
        }
        if (app_table_exists($pdo, 'wallets')) {
            $stmt = $pdo->prepare("SELECT balance FROM wallets WHERE user_id = ? LIMIT 1");
            $stmt->execute([(int) $user['id']]);
            $counts['wallet'] = (float) ($stmt->fetchColumn() ?: 0);
        }
        if (app_table_exists($pdo, 'academy_enrollments')) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM academy_enrollments WHERE user_id = ?");
            $stmt->execute([(int) $user['id']]);
            $counts['academy'] = (int) $stmt->fetchColumn();
        }
        if (app_table_exists($pdo, 'support_tickets')) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM support_tickets WHERE user_id = ?");
            $stmt->execute([(int) $user['id']]);
            $counts['support'] = (int) $stmt->fetchColumn();
        }
    }
    return $counts;
}

function provider_offerings(PDO $pdo, int $providerId, int $limit = 12): array
{
    if ($providerId <= 0) {
        return [];
    }
    $stmt = $pdo->prepare("SELECT * FROM provider_offerings WHERE provider_id = ? ORDER BY created_at DESC LIMIT " . max(1, min(80, $limit)));
    $stmt->execute([$providerId]);
    return $stmt->fetchAll();
}

function provider_nav(): array
{
    return [
        ['overview', 'Overview', 'fa-home', 'dashboard.php'],
        ['profile', 'Business Profile', 'fa-id-card', 'profile.php'],
        ['products', 'Products & Services', 'fa-screwdriver-wrench', 'products.php'],
        ['coverage', 'Coverage Areas', 'fa-map-location-dot', 'coverage.php'],
        ['orders', 'Orders & Requests', 'fa-cart-shopping', 'orders.php'],
        ['accreditation', 'Accreditation', 'fa-shield-halved', 'accreditation.php'],
        ['wallet', 'Wallet', 'fa-wallet', 'wallet.php'],
        ['marketplace', 'Marketplace', 'fa-store', 'marketplace.php'],
        ['academy', 'NATCODEV Academy', 'fa-graduation-cap', 'academy.php'],
        ['reports', 'Reports', 'fa-chart-line', 'reports.php'],
        ['support', 'Support Desk', 'fa-headset', 'support.php'],
        ['settings', 'Settings', 'fa-gear', 'settings.php'],
    ];
}

function provider_page_start(string $title, string $active, ?array $user, ?array $provider, array $counts): void
{
    $logo = app_primary_logo_url();
    $name = (string) ($provider['company_name'] ?? $user['name'] ?? 'Provider Workspace');
    $owner = (string) ($user['name'] ?? $provider['contact_person'] ?? 'Business Owner');
    $status = provider_status_label((string) ($provider['status'] ?? 'pending_review'));
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($title) ?> - NATCODEV Provider</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    :root{--green:#06451f;--green2:#0a7a3a;--mint:#eef8ef;--gold:#d89b10;--orange:#f79009;--blue:#2f72d8;--red:#d92d20;--ink:#101828;--muted:#667085;--line:#dfe8d8;--bg:#f7faf5;--shadow:0 16px 40px rgba(16,24,40,.08)}
    *{box-sizing:border-box}body{margin:0;background:var(--bg);font-family:"Segoe UI",Arial,sans-serif;color:var(--ink)}a{text-decoration:none;color:inherit}.shell{display:grid;grid-template-columns:260px 1fr;min-height:100vh}.side{background:linear-gradient(180deg,#06451f,#013417);color:#fff;padding:18px;position:sticky;top:0;height:100vh;overflow:auto}.brand{display:flex;gap:12px;align-items:center;margin-bottom:24px}.brand img{width:58px;height:58px;border-radius:50%;background:#fff}.brand strong{font-size:1.42rem}.brand span span{display:block;font-size:.76rem}.provider-card{background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.16);border-radius:14px;padding:14px;margin-bottom:22px;display:flex;gap:12px;align-items:center}.avatar{width:54px;height:54px;border-radius:50%;background:#e8f6ec;color:var(--green);display:grid;place-items:center;font-weight:950}.provider-card small{display:block;color:#a6f2b7;font-weight:850}.nav{display:grid;gap:7px}.nav a{display:flex;align-items:center;gap:11px;color:#f3fff4;padding:11px 12px;border-radius:9px;font-weight:900}.nav a.active,.nav a:hover{background:linear-gradient(135deg,#118b42,#0d6b34)}.side-cta{margin-top:24px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.16);border-radius:14px;padding:16px}.main{min-width:0}.top{height:70px;background:#fff;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;align-items:center;gap:18px;padding:0 26px;position:sticky;top:0;z-index:10}.search{max-width:580px;flex:1;position:relative}.search input{width:100%;border:1px solid var(--line);border-radius:10px;padding:13px 42px}.search i{position:absolute;left:14px;top:14px;color:var(--muted)}.top-actions{display:flex;gap:18px;align-items:center;font-weight:850}.content{padding:24px 28px}.page-head{display:flex;justify-content:space-between;gap:18px;align-items:flex-end;margin-bottom:18px}.page-head h1{font-size:2rem;margin:0;color:#08122b}.page-head p{margin:5px 0 0;color:#344054}.btn{display:inline-flex;gap:8px;align-items:center;justify-content:center;border:1px solid var(--green);border-radius:9px;background:var(--green);color:#fff;font-weight:950;padding:10px 14px}.btn.light{background:#fff;color:var(--green)}.btn.warn{background:#fff7e6;color:#9a6500;border-color:#f3d391}.kpis{display:grid;grid-template-columns:repeat(6,1fr);gap:14px;margin-bottom:18px}.kpi{background:#fff;border:1px solid var(--line);border-radius:12px;padding:17px;box-shadow:var(--shadow);display:flex;gap:14px;align-items:center}.kpi i{width:54px;height:54px;border-radius:50%;display:grid;place-items:center;background:#e8f6ec;color:var(--green);font-size:1.35rem}.kpi b{font-size:1.35rem}.grid{display:grid;grid-template-columns:repeat(12,1fr);gap:16px}.card{background:#fff;border:1px solid var(--line);border-radius:12px;padding:18px;box-shadow:var(--shadow)}.span-3{grid-column:span 3}.span-4{grid-column:span 4}.span-5{grid-column:span 5}.span-6{grid-column:span 6}.span-7{grid-column:span 7}.span-8{grid-column:span 8}.span-12{grid-column:span 12}.card-head{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:12px}.card h2,.card h3{margin:0;color:#08122b}.view{color:var(--green);font-weight:950}.badge{display:inline-flex;gap:6px;align-items:center;border-radius:999px;background:#e8f6ec;color:var(--green);font-size:.78rem;font-weight:950;padding:5px 9px}.badge.warn{background:#fff3d6;color:#9a6500}.badge.red{background:#fff1f2;color:#b42318}.list{display:grid;gap:10px}.row{display:flex;justify-content:space-between;gap:12px;align-items:center;border-top:1px solid var(--line);padding-top:10px}.row:first-child{border-top:0;padding-top:0}.thumb{width:64px;height:56px;object-fit:cover;border-radius:9px}.action-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:12px}.quick{border:1px solid var(--line);border-radius:10px;padding:14px;text-align:center;background:#fbfdf9;font-weight:900}.quick i{display:grid;place-items:center;width:48px;height:48px;border-radius:50%;background:#e8f6ec;color:var(--green);font-size:1.3rem;margin:0 auto 8px}label{display:block;font-weight:850}input,select,textarea{width:100%;border:1px solid var(--line);border-radius:9px;padding:12px;margin-top:6px}textarea{min-height:110px}.form-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px}.wide{grid-column:1/-1}.notice{padding:12px 14px;border-radius:10px;margin-bottom:14px;font-weight:850}.ok{background:#e8f6ec;color:var(--green)}.err{background:#fff1f2;color:#b42318}.hero-card{background:linear-gradient(90deg,rgba(255,255,255,.97),rgba(255,255,255,.78)),url("../assets/public/provider-commerce-hero.png") center/cover;border:1px solid var(--line);border-radius:14px;padding:24px;min-height:210px}.footer{display:flex;justify-content:space-between;gap:20px;color:#667085;font-size:.9rem;padding:18px 28px}
    @media(max-width:1250px){.shell{grid-template-columns:1fr}.side{position:relative;height:auto}.kpis{grid-template-columns:repeat(2,1fr)}.span-3,.span-4,.span-5,.span-6,.span-7,.span-8{grid-column:span 12}.action-grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:700px){.top,.page-head,.footer{align-items:flex-start;flex-direction:column;height:auto;padding:14px}.kpis,.form-grid,.action-grid{grid-template-columns:1fr}.content{padding:18px}}
  </style>
</head>
<body>
<div class="shell">
  <aside class="side">
    <a class="brand" href="../index.php"><img src="<?= e($logo) ?>" alt="NATCODEV"><span><strong>NATCODEV</strong><span>Provider Console</span></span></a>
    <div class="provider-card"><span class="avatar"><?= e(market_initials($name)) ?></span><span><strong><?= e($name) ?></strong><small><?= e($status) ?></small><small><?= e($owner) ?></small></span></div>
    <nav class="nav">
      <?php foreach (provider_nav() as $item): ?>
        <a class="<?= $active === $item[0] ? 'active' : '' ?>" href="<?= e($item[3]) ?>"><i class="fas <?= e($item[2]) ?>"></i><?= e($item[1]) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="side-cta"><strong>Grow with NATCODEV</strong><p>Sell approved products, services, and training after accreditation.</p><a class="btn light" href="../market/seller-central.php">Seller Central</a></div>
  </aside>
  <main class="main">
    <header class="top">
      <form class="search" action="search.php" method="get"><i class="fas fa-search"></i><input name="q" placeholder="Search orders, requests, products, customers..."></form>
      <div class="top-actions"><a href="../market/orders.php"><i class="fas fa-bell"></i></a><a href="../dashboard/inbox.php"><i class="fas fa-envelope"></i></a><a href="coverage.php"><i class="fas fa-location-dot"></i> Coverage</a><span><?= e($owner) ?></span><a href="../dashboard/logout.php">Logout</a></div>
    </header>
    <section class="content">
<?php
}

function provider_page_end(): void
{
    ?>
    </section>
    <footer class="footer"><span>&copy; <?= e(date('Y')) ?> NATCODEV. All rights reserved.</span><span>Provider Console v2.1.0</span><span>Last login: <?= e(date('M j, Y h:i A')) ?></span></footer>
  </main>
</div>
<script src="../lib/location-picker.js"></script>
</body>
</html>
<?php
}

function provider_simple_page(string $active, string $title, string $intro, callable $body): void
{
    $pdo = provider_boot();
    $user = provider_full_user($pdo, provider_require($pdo));
    $provider = provider_active($pdo, $user);
    $counts = provider_counts($pdo, $provider, $user);
    provider_page_start($title, $active, $user, $provider, $counts);
    echo '<div class="page-head"><div><h1>' . e($title) . '</h1><p>' . e($intro) . '</p></div></div>';
    if (!$provider) {
        echo '<div class="notice err">No provider profile is linked to this account yet. Complete provider registration first.</div><a class="btn" href="index.php">Register Provider Profile</a>';
    } else {
        $body($pdo, $user, $provider, $counts);
    }
    provider_page_end();
}
