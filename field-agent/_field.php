<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/field-management.php';
require_once __DIR__ . '/../lib/agronomy.php';

function fa_pdo(): PDO
{
    static $pdo = null;
    if (!$pdo instanceof PDO) {
        $pdo = db();
        fm_ensure_schema($pdo);
        agronomy_ensure_schema($pdo);
    }
    return $pdo;
}

function fa_current_user(PDO $pdo): ?array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $user = current_user($pdo);
    if (!$user) {
        return null;
    }
    $role = (string) ($user['role'] ?? '');
    $platformRole = (string) ($user['platform_role'] ?? '');
    if (in_array($role, ['field_agent', 'admin'], true) || in_array($platformRole, ['field_agent', 'admin'], true)) {
        return $user;
    }
    return null;
}

function fa_require_user(PDO $pdo): array
{
    $user = fa_current_user($pdo);
    if (!$user) {
        header('Location: login.php');
        exit;
    }
    return $user;
}

function fa_avatar(array $user): string
{
    $picture = trim((string) ($user['profile_picture'] ?? ''));
    if ($picture !== '') {
        return str_starts_with($picture, 'http') ? $picture : '../' . ltrim($picture, '/');
    }
    return '../assets/public/field-agent-operations-hero.png';
}

function fa_task_rows(PDO $pdo, array $user, ?string $status = null): array
{
    $where = "(ft.assigned_to = ? OR ? = 'admin')";
    $params = [(int) $user['id'], (string) ($user['role'] ?? '')];
    if ($status) {
        $where .= " AND ft.status = ?";
        $params[] = $status;
    } else {
        $where .= " AND ft.status IN ('pending','assigned','in_progress')";
    }
    $stmt = $pdo->prepare("
        SELECT ft.*, gf.farm_name, gf.street_address, gf.latitude, gf.longitude,
               u.name grower_name, u.phone grower_phone,
               s.state_name, l.lga_name
        FROM field_tasks ft
        JOIN grower_farms gf ON gf.id = ft.farm_id
        JOIN users u ON u.id = gf.user_id
        LEFT JOIN nigeria_states s ON s.id = gf.state_id
        LEFT JOIN nigeria_lgas l ON l.id = gf.lga_id
        WHERE {$where}
        ORDER BY FIELD(ft.priority, 'urgent','high','normal','low'), ft.due_date IS NULL, ft.due_date, ft.created_at DESC
    ");
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function fa_visit_rows(PDO $pdo, array $user, int $limit = 8): array
{
    if (!app_table_exists($pdo, 'farm_visits')) {
        return [];
    }
    $stmt = $pdo->prepare("
        SELECT fv.*, gf.farm_name, u.name grower_name, s.state_name, l.lga_name
        FROM farm_visits fv
        JOIN grower_farms gf ON gf.id = fv.farm_id
        JOIN users u ON u.id = gf.user_id
        LEFT JOIN nigeria_states s ON s.id = gf.state_id
        LEFT JOIN nigeria_lgas l ON l.id = gf.lga_id
        WHERE (fv.agent_id = ? OR ? = 'admin')
        ORDER BY fv.visited_at DESC
        LIMIT {$limit}
    ");
    $stmt->execute([(int) $user['id'], (string) ($user['role'] ?? '')]);
    return $stmt->fetchAll();
}

function fa_count(array $rows, ?string $status = null): int
{
    if ($status === null) {
        return count($rows);
    }
    return count(array_filter($rows, static fn(array $row): bool => (string) ($row['status'] ?? '') === $status));
}

function fa_priority_class(string $priority): string
{
    return match ($priority) {
        'urgent', 'high' => 'danger',
        'normal' => 'warn',
        'low' => 'good',
        default => 'neutral',
    };
}

function fa_bootstrap_payload(array $user, array $tasks): array
{
    return [
        'csrf' => csrf_token(),
        'user' => ['id' => (int) $user['id'], 'name' => (string) $user['name'], 'role' => (string) $user['role']],
        'tasks' => array_map(static function (array $task): array {
            return [
                'id' => (int) $task['id'],
                'farm_id' => (int) $task['farm_id'],
                'farm_name' => (string) $task['farm_name'],
                'grower_name' => (string) $task['grower_name'],
                'grower_phone' => (string) ($task['grower_phone'] ?? ''),
                'street_address' => (string) ($task['street_address'] ?? ''),
                'state_name' => (string) ($task['state_name'] ?? ''),
                'lga_name' => (string) ($task['lga_name'] ?? ''),
                'latitude' => $task['latitude'] === null ? null : (float) $task['latitude'],
                'longitude' => $task['longitude'] === null ? null : (float) $task['longitude'],
                'priority' => (string) $task['priority'],
                'status' => (string) $task['status'],
                'due_date' => (string) ($task['due_date'] ?? ''),
            ];
        }, $tasks),
    ];
}

function fa_header(string $title, string $subtitle, array $user, string $active = 'overview'): void
{
    $menu = [
        'overview' => ['Overview', 'index.php', 'home'],
        'assignments' => ['My Assignments', 'assignments.php', 'clipboard-list'],
        'visits' => ['Grower Visits', 'visits.php', 'map-pin'],
        'verification' => ['Verification Queue', 'verification.php', 'shield-check'],
        'evidence' => ['Field Evidence', 'evidence.php', 'camera'],
        'map' => ['Farm Map', 'map.php', 'map'],
        'reports' => ['Reports', 'reports.php', 'file-text'],
        'messages' => ['Messages', 'messages.php', 'message-square'],
        'academy' => ['Academy', 'academy.php', 'graduation-cap'],
        'wallet' => ['Wallet & Allowance', 'wallet.php', 'wallet'],
        'support' => ['Support Desk', 'support.php', 'headphones'],
        'profile' => ['Profile', 'profile.php', 'user'],
    ];
    $avatar = fa_avatar($user);
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($title) ?> - NATCODEV Field Agent</title>
  <link rel="manifest" href="../manifest.json">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <script src="https://unpkg.com/lucide@latest"></script>
  <style>
    :root{--green:#007a3d;--green2:#0f6b3c;--deep:#003f25;--ink:#07162f;--muted:#64748b;--line:#e2e8f0;--soft:#f6fbf7;--gold:#d49400;--blue:#246bfe;--orange:#f97316;--red:#dc2626;--shadow:0 18px 48px rgba(15,23,42,.08)}
    *{box-sizing:border-box}body{margin:0;background:#f8fbfa;color:var(--ink);font-family:Inter,"Segoe UI",Arial,sans-serif}.fa-shell{display:grid;grid-template-columns:278px minmax(0,1fr);min-height:100vh}.fa-side{background:linear-gradient(155deg,#006b36,#00381f 72%);color:#fff;padding:22px 18px;position:sticky;top:0;height:100vh;overflow:auto}.fa-brand{display:flex;gap:12px;align-items:center;padding-bottom:18px;border-bottom:1px solid rgba(255,255,255,.15)}.fa-brand img{width:54px;height:54px;border-radius:50%;background:#fff;object-fit:contain}.fa-brand strong{font-size:1.45rem}.fa-brand span{display:block;font-size:.78rem;line-height:1.25;opacity:.9}.fa-person{display:flex;gap:12px;align-items:center;margin:24px 0;padding:14px;border:1px solid rgba(255,255,255,.18);border-radius:14px;background:rgba(255,255,255,.08)}.fa-person img{width:58px;height:58px;border-radius:50%;object-fit:cover}.fa-person b,.fa-person span{display:block}.fa-person span{font-size:.82rem;color:#c8f5d9}.fa-menu{display:grid;gap:7px}.fa-menu a{display:flex;align-items:center;gap:12px;color:#fff;text-decoration:none;font-weight:800;padding:12px 13px;border-radius:10px}.fa-menu a.active,.fa-menu a:hover{background:linear-gradient(90deg,#0f9f55,#087140);box-shadow:0 10px 22px rgba(0,0,0,.18)}.fa-sync{margin-top:36px;border:1px solid rgba(255,255,255,.18);border-radius:14px;padding:16px;background:rgba(255,255,255,.06)}.fa-main{min-width:0}.fa-top{height:72px;border-bottom:1px solid var(--line);background:#fff;display:flex;align-items:center;gap:18px;padding:0 28px;position:sticky;top:0;z-index:5}.fa-search{flex:1;max-width:610px;display:flex;align-items:center;gap:9px;background:#f8fafc;border:1px solid var(--line);border-radius:10px;padding:11px 14px}.fa-search input{border:0;background:transparent;outline:0;width:100%;font:inherit}.fa-chip{display:inline-flex;align-items:center;gap:7px;border:1px solid var(--line);border-radius:10px;padding:9px 11px;background:#fff;font-weight:800}.fa-content{padding:28px}.fa-title{display:flex;justify-content:space-between;gap:18px;align-items:flex-start;margin-bottom:22px}.fa-title h1{margin:0 0 6px;font-size:2rem;line-height:1.1}.fa-title p{margin:0;color:var(--muted)}.fa-kpis{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:16px;margin-bottom:18px}.fa-card{background:#fff;border:1px solid var(--line);border-radius:12px;box-shadow:var(--shadow)}.fa-kpi{padding:18px;display:flex;align-items:center;gap:13px}.fa-icon{width:48px;height:48px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;background:#eaf7ef;color:var(--green);flex:none}.fa-icon.blue{background:#eaf1ff;color:var(--blue)}.fa-icon.orange{background:#fff2e6;color:var(--orange)}.fa-icon.purple{background:#f2e8ff;color:#7c3aed}.fa-icon.gold{background:#fff6dc;color:var(--gold)}.fa-icon.red{background:#fee2e2;color:var(--red)}.fa-kpi small{display:block;color:var(--muted);font-weight:800}.fa-kpi b{display:block;font-size:1.5rem}.fa-kpi span{font-size:.83rem;color:var(--green);font-weight:800}.fa-grid{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:16px}.span-3{grid-column:span 3}.span-4{grid-column:span 4}.span-5{grid-column:span 5}.span-6{grid-column:span 6}.span-7{grid-column:span 7}.span-8{grid-column:span 8}.span-12{grid-column:span 12}.fa-panel{padding:16px}.fa-panel-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;border-bottom:1px solid #edf2f7;padding-bottom:10px}.fa-panel-head h2{font-size:1rem;margin:0}.fa-link{color:var(--green2);font-weight:900;text-decoration:none}.fa-list{display:grid;gap:10px}.fa-row{display:grid;grid-template-columns:auto 1fr auto;gap:12px;align-items:center;padding:10px;border-bottom:1px solid #edf2f7}.fa-row:last-child{border-bottom:0}.thumb{width:52px;height:52px;border-radius:8px;object-fit:cover;background:#eaf7ef}.muted{color:var(--muted)}.badge{display:inline-flex;align-items:center;gap:5px;border-radius:999px;padding:5px 9px;font-size:.75rem;font-weight:900;background:#eef2f7;color:#334155}.badge.good{background:#e8f8ee;color:#087140}.badge.warn{background:#fff4dc;color:#a16207}.badge.danger{background:#fee2e2;color:#b91c1c}.badge.neutral{background:#eef2f7;color:#334155}.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;border:1px solid var(--green);background:var(--green);color:#fff;border-radius:8px;padding:10px 14px;text-decoration:none;font-weight:900;cursor:pointer}.btn.secondary{background:#fff;color:var(--green)}.btn.soft{background:#eef8f1;color:var(--green);border-color:#d9efe1}.field-form{display:grid;gap:12px}.field-form input,.field-form select,.field-form textarea{width:100%;padding:12px;border:1px solid var(--line);border-radius:8px;font:inherit}.field-form textarea{min-height:94px}.field-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}#map{height:360px;border-radius:12px;border:1px solid var(--line)}.quick-actions{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:12px}.quick-actions a{display:grid;place-items:center;text-align:center;gap:7px;min-height:104px;border:1px solid var(--line);border-radius:12px;text-decoration:none;color:var(--ink);font-weight:900;background:#fff}.quick-actions small{display:block;color:var(--muted);font-weight:500}.footer{display:flex;justify-content:space-between;align-items:center;color:var(--muted);font-size:.9rem;padding:20px 28px;border-top:1px solid var(--line);background:#fff;margin-top:12px}.empty{padding:22px;border:1px dashed var(--line);border-radius:12px;color:var(--muted);background:#fbfdfc}@media(max-width:1250px){.fa-kpis{grid-template-columns:repeat(3,1fr)}.span-3,.span-4,.span-5,.span-6,.span-7,.span-8{grid-column:span 12}.quick-actions{grid-template-columns:repeat(3,1fr)}}@media(max-width:860px){.fa-shell{grid-template-columns:1fr}.fa-side{position:relative;height:auto}.fa-top{position:relative;flex-wrap:wrap;height:auto;padding:14px}.fa-content{padding:18px}.fa-kpis{grid-template-columns:1fr}.field-grid{grid-template-columns:1fr}.quick-actions{grid-template-columns:1fr}.footer{display:block}.fa-title{display:block}}
  </style>
</head>
<body>
<div class="fa-shell">
  <aside class="fa-side">
    <a class="fa-brand" href="../index.php">
      <img src="<?= e(app_primary_logo_url()) ?>" alt="NATCODEV">
      <span><strong>NATCODEV</strong>National Coconut Development &<br>Propagation Initiative</span>
    </a>
    <div class="fa-person">
      <img src="<?= e($avatar) ?>" alt="<?= e((string) $user['name']) ?>">
      <div><b><?= e((string) $user['name']) ?></b><span>Field Agent</span><span>Online</span></div>
    </div>
    <nav class="fa-menu">
      <?php foreach ($menu as $key => [$label, $href, $icon]): ?>
        <a href="<?= e($href) ?>" class="<?= $active === $key ? 'active' : '' ?>"><i data-lucide="<?= e($icon) ?>"></i><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="fa-sync">
      <h3>Need to sync data?</h3>
      <p>Offline records are kept locally until the browser can send them.</p>
      <button class="btn secondary" type="button" id="syncNowButton"><i data-lucide="refresh-cw"></i> Sync Now</button>
    </div>
  </aside>
  <section class="fa-main">
    <header class="fa-top">
      <a class="fa-chip" href="index.php"><i data-lucide="menu"></i></a>
      <form class="fa-search" action="search.php" method="get"><i data-lucide="search"></i><input name="q" placeholder="Search growers, farms, tickets, locations..."><span class="badge good">Ctrl + K</span></form>
      <span class="fa-chip"><i data-lucide="cloud-off"></i> Offline-ready</span>
      <a class="fa-chip" href="messages.php"><i data-lucide="bell"></i></a>
      <a class="fa-chip" href="messages.php"><i data-lucide="message-square"></i></a>
      <span class="fa-chip"><i data-lucide="map-pin"></i> Field Network</span>
      <a class="fa-chip" href="profile.php"><img src="<?= e($avatar) ?>" alt="" style="width:28px;height:28px;border-radius:50%;object-fit:cover"> <?= e((string) $user['name']) ?></a>
      <a class="fa-chip" href="../dashboard/logout.php"><i data-lucide="log-out"></i></a>
    </header>
    <main class="fa-content">
      <div class="fa-title">
        <div><h1><?= e($title) ?></h1><p><?= e($subtitle) ?></p></div>
      </div>
<?php
}

function fa_footer(): void
{
    ?>
    </main>
    <footer class="footer">
      <span>&copy; 2026 NATCODEV. All rights reserved.</span>
      <span>Grow more. Earn more. Empowering coconut communities.</span>
      <span>Last login: <?= e(date('M j, Y h:i A')) ?></span>
    </footer>
  </section>
</div>
<script src="../lib/location-picker.js"></script>
<script>if (window.lucide) { lucide.createIcons(); }</script>
</body>
</html>
<?php
}

function fa_task_card(array $task): void
{
    ?>
    <article class="fa-row">
      <img class="thumb" src="../assets/public/field-agent-operations-hero.png" alt="">
      <div>
        <strong><?= e((string) $task['farm_name']) ?></strong><br>
        <span class="muted"><?= e((string) $task['grower_name']) ?> / <?= e(trim((string) (($task['lga_name'] ?? '') . ', ' . ($task['state_name'] ?? '')), ', ')) ?></span>
      </div>
      <span class="badge <?= e(fa_priority_class((string) $task['priority'])) ?>"><?= e((string) $task['priority']) ?></span>
    </article>
<?php
}
