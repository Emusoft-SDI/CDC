<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/field-management.php';
require_once __DIR__ . '/../lib/agronomy.php';
require_once __DIR__ . '/../lib/monnify.php';
require_once __DIR__ . '/../lib/notification-dispatch.php';
require_once __DIR__ . '/../market/_market.php';
require_once __DIR__ . '/../lib/user-workspaces.php';

function fa_pdo(): PDO
{
    static $pdo = null;
    if (!$pdo instanceof PDO) {
        $pdo = db();
        fm_ensure_schema($pdo);
        agronomy_ensure_schema($pdo);
        wallet_ensure_schema($pdo);
        app_ensure_farmer_engagement_schema($pdo);
    }
    return $pdo;
}

function fa_role_key(array $user): string
{
    return app_role_normalize((string) (($user['platform_role'] ?? '') ?: ($user['role'] ?? 'field_agent')));
}

function fa_role_label(string $role): string
{
    return match ($role) {
        'field_agent' => 'Field Agent',
        'agronomist' => 'Agronomist',
        'extensionist' => 'Agric Extensionist',
        'farm_hand' => 'Farm Hand',
        'admin' => 'Field Operations Admin',
        default => ucwords(str_replace('_', ' ', $role)),
    };
}

function fa_role_home(string $role): string
{
    return match ($role) {
        'agronomist' => 'agronomist.php',
        'extensionist' => 'extensionist.php',
        'farm_hand' => 'farm-hand.php',
        default => 'field-agent.php',
    };
}

function field_login_home(array $user): string
{
    $roles = app_user_role_keys(fa_pdo(), $user);
    $preferred = [fa_role_key($user), 'field_agent', 'farm_hand', 'agronomist', 'extensionist'];
    foreach ($preferred as $role) {
        $role = app_role_normalize((string) $role);
        if ($role !== '' && in_array($role, $roles, true) && in_array($role, ['field_agent', 'farm_hand', 'agronomist', 'extensionist'], true)) {
            return fa_role_home($role);
        }
    }
    return 'field-agent.php';
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
    $fieldRoles = ['field_agent', 'agronomist', 'extensionist', 'farm_hand', 'admin', 'super_admin'];
    return app_user_has_any_role($pdo, $user, $fieldRoles) ? $user : null;
}

function fa_require_user(PDO $pdo): array
{
    $user = fa_current_user($pdo);
    if (!$user) {
        redirect_to('login.php');
    }
    return $user;
}

function fa_user_can_enter_workspace(PDO $pdo, array $user, string $workspaceRole): bool
{
    return app_user_has_any_role($pdo, $user, ['admin', 'super_admin', $workspaceRole]);
}

function fa_require_workspace_role(PDO $pdo, string $workspaceRole): array
{
    $user = fa_require_user($pdo);
    if (!fa_user_can_enter_workspace($pdo, $user, $workspaceRole)) {
        redirect_to(field_login_home($user));
    }
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $_SESSION['field_workspace_role'] = app_role_normalize($workspaceRole);
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
    $params = [(int) $user['id'], fa_role_key($user)];
    $where .= $status ? ' AND ft.status = ?' : " AND ft.status IN ('pending','assigned','in_progress')";
    if ($status) { $params[] = $status; }
    try {
        $stmt = $pdo->prepare("SELECT ft.*, gf.farm_name, gf.street_address, gf.latitude, gf.longitude, u.name grower_name, u.phone grower_phone, s.state_name, l.lga_name FROM field_tasks ft JOIN grower_farms gf ON gf.id = ft.farm_id JOIN users u ON u.id = gf.user_id LEFT JOIN nigeria_states s ON s.id = gf.state_id LEFT JOIN nigeria_lgas l ON l.id = gf.lga_id WHERE {$where} ORDER BY FIELD(ft.priority, 'urgent','high','normal','low'), ft.due_date IS NULL, ft.due_date, ft.created_at DESC");
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function fa_visit_rows(PDO $pdo, array $user, int $limit = 8): array
{
    if (!app_table_exists($pdo, 'farm_visits')) { return []; }
    try {
        $stmt = $pdo->prepare("SELECT fv.*, gf.farm_name, u.name grower_name, s.state_name, l.lga_name FROM farm_visits fv JOIN grower_farms gf ON gf.id = fv.farm_id JOIN users u ON u.id = gf.user_id LEFT JOIN nigeria_states s ON s.id = gf.state_id LEFT JOIN nigeria_lgas l ON l.id = gf.lga_id WHERE (fv.agent_id = ? OR ? = 'admin') ORDER BY fv.visited_at DESC LIMIT {$limit}");
        $stmt->execute([(int) $user['id'], fa_role_key($user)]);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function fa_count(array $rows, ?string $status = null): int
{
    return $status === null ? count($rows) : count(array_filter($rows, static fn(array $row): bool => (string) ($row['status'] ?? '') === $status));
}

function fa_priority_class(string $priority): string
{
    return match ($priority) { 'urgent', 'high' => 'danger', 'normal' => 'warn', 'low' => 'good', default => 'neutral' };
}

function fa_bootstrap_payload(array $user, array $tasks): array
{
    return [
        'csrf' => csrf_token(),
        'user' => ['id' => (int) $user['id'], 'name' => (string) $user['name'], 'role' => fa_role_key($user)],
        'tasks' => array_map(static fn(array $task): array => [
            'id' => (int) $task['id'], 'farm_id' => (int) $task['farm_id'], 'farm_name' => (string) $task['farm_name'],
            'grower_name' => (string) $task['grower_name'], 'grower_phone' => (string) ($task['grower_phone'] ?? ''),
            'street_address' => (string) ($task['street_address'] ?? ''), 'state_name' => (string) ($task['state_name'] ?? ''),
            'lga_name' => (string) ($task['lga_name'] ?? ''), 'latitude' => $task['latitude'] === null ? null : (float) $task['latitude'],
            'longitude' => $task['longitude'] === null ? null : (float) $task['longitude'], 'priority' => (string) $task['priority'],
            'status' => (string) $task['status'], 'due_date' => (string) ($task['due_date'] ?? ''),
        ], $tasks),
    ];
}

function fa_role_profile(string $role): array
{
    return match ($role) {
        'agronomist' => ['title' => 'Agronomist Workspace', 'subtitle' => 'Review farm health, advisory cases, evidence, field reports, wallet allowance, support, and Academy progression.', 'accent' => 'Agronomy advisory', 'primary' => 'Farm Health Review', 'primary_href' => 'reports.php?report=verification', 'secondary' => 'Field Evidence', 'secondary_href' => 'evidence.php'],
        'extensionist' => ['title' => 'Extensionist Workspace', 'subtitle' => 'Coordinate grower outreach, visits, messages, Academy readiness, field reports, wallet allowance, and support.', 'accent' => 'Extension services', 'primary' => 'Grower Visits', 'primary_href' => 'visits.php', 'secondary' => 'Messages', 'secondary_href' => 'messages.php'],
        'farm_hand' => ['title' => 'Farm Hand Workspace', 'subtitle' => 'Practical farm workers review assigned work, safety learning, support, wallet allowance, and logout from one simple workspace.', 'accent' => 'Farm workforce', 'primary' => 'My Assignments', 'primary_href' => 'assignments.php', 'secondary' => 'Academy Safety', 'secondary_href' => 'academy.php'],
        default => ['title' => 'Field Agent Workspace', 'subtitle' => 'Complete assigned registry and farm work, sync evidence, review reports, manage allowance, ask for support, then logout.', 'accent' => 'Field operations', 'primary' => 'My Assignments', 'primary_href' => 'assignments.php', 'secondary' => 'Submit Evidence', 'secondary_href' => 'evidence.php'],
    };
}

function fa_header(string $title, string $subtitle, array $user, string $active = 'overview'): void
{
    $sessionRole = app_role_normalize((string) ($_SESSION['field_workspace_role'] ?? ''));
    $roleKey = ($sessionRole !== '' && app_user_has_role(fa_pdo(), $user, $sessionRole)) ? $sessionRole : fa_role_key($user);
    $roleLabel = fa_role_label($roleKey);
    $roleHome = fa_role_home($roleKey);
    $avatar = fa_avatar($user);
    $menu = [
        'overview' => ['My Workspace', $roleHome, 'home'], 'assignments' => ['My Assignments', 'assignments.php', 'clipboard-list'],
        'visits' => ['Grower Visits', 'visits.php', 'map-pin'], 'verification' => ['Verification Queue', 'verification.php', 'shield-check'],
        'evidence' => ['Field Evidence', 'evidence.php', 'camera'], 'map' => ['Farm Map', 'map.php', 'map'],
        'reports' => ['Reports', 'reports.php', 'file-text'], 'messages' => ['Messages', 'messages.php', 'message-square'],
        'academy' => ['Academy', 'academy.php', 'graduation-cap'], 'wallet' => ['Wallet & Allowance', 'wallet.php', 'wallet'],
        'support' => ['Support Desk', 'support.php', 'headphones'], 'profile' => ['Profile', 'profile.php', 'user'],
    ];
    ?>
<!doctype html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title><?= e($title) ?> - NATCODEV <?= e($roleLabel) ?></title><link rel="manifest" href="../manifest.json"><link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"><script src="https://unpkg.com/lucide@latest"></script><style>
:root{--green:#007a3d;--green2:#0f6b3c;--deep:#003f25;--ink:#07162f;--muted:#64748b;--line:#e2e8f0;--gold:#d49400;--blue:#246bfe;--orange:#f97316;--red:#dc2626;--shadow:0 18px 48px rgba(15,23,42,.08)}*{box-sizing:border-box}body{margin:0;background:#f8fbfa;color:var(--ink);font-family:Inter,"Segoe UI",Arial,sans-serif}.fa-shell{display:grid;grid-template-columns:278px minmax(0,1fr);min-height:100vh}.fa-side{background:linear-gradient(155deg,#006b36,#00381f 72%);color:#fff;padding:22px 18px;position:sticky;top:0;height:100vh;overflow:auto}.fa-brand{display:flex;gap:12px;align-items:center;padding-bottom:18px;border-bottom:1px solid rgba(255,255,255,.15);color:#fff;text-decoration:none}.fa-brand img{width:54px;height:54px;border-radius:50%;background:#fff}.fa-brand strong{font-size:1.45rem}.fa-person{display:flex;gap:12px;align-items:center;margin:24px 0;padding:14px;border:1px solid rgba(255,255,255,.18);border-radius:14px;background:rgba(255,255,255,.08)}.fa-person img{width:58px;height:58px;border-radius:50%;object-fit:cover}.fa-person b,.fa-person span{display:block}.fa-person span{font-size:.82rem;color:#c8f5d9}.fa-menu{display:grid;gap:7px}.fa-menu a{display:flex;align-items:center;gap:12px;color:#fff;text-decoration:none;font-weight:800;padding:12px 13px;border-radius:10px}.fa-menu a.active,.fa-menu a:hover{background:#0f9f55}.fa-sync{margin-top:28px;border:1px solid rgba(255,255,255,.18);border-radius:14px;padding:16px;background:rgba(255,255,255,.06)}.workspace-switcher{display:grid;gap:7px;margin:14px 0;padding:12px;border:1px solid rgba(255,255,255,.18);border-radius:12px;background:rgba(255,255,255,.06)}.workspace-switcher strong{font-size:.82rem;color:#c8f5d9;text-transform:uppercase}.workspace-link{display:block;color:#fff;text-decoration:none;padding:9px 10px;border-radius:8px;font-weight:850}.workspace-link.active,.workspace-link:hover{background:#0f9f55}.fa-main{min-width:0}.fa-top{min-height:72px;border-bottom:1px solid var(--line);background:#fff;display:flex;align-items:center;gap:14px;padding:12px 28px;position:sticky;top:0;z-index:5;flex-wrap:wrap}.fa-search{flex:1;min-width:240px;display:flex;gap:9px;background:#f8fafc;border:1px solid var(--line);border-radius:10px;padding:11px 14px}.fa-search input{border:0;background:transparent;outline:0;width:100%}.fa-chip{display:inline-flex;align-items:center;gap:7px;border:1px solid var(--line);border-radius:10px;padding:9px 11px;background:#fff;font-weight:800;text-decoration:none;color:var(--ink)}.fa-content{padding:28px}.fa-title{margin-bottom:22px}.fa-title h1{margin:0 0 6px}.fa-title p{margin:0;color:var(--muted)}.fa-kpis{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:16px;margin-bottom:18px}.fa-card{background:#fff;border:1px solid var(--line);border-radius:12px;box-shadow:var(--shadow)}.fa-kpi{padding:18px;display:flex;align-items:center;gap:13px}.fa-icon{width:48px;height:48px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;background:#eaf7ef;color:var(--green);flex:none}.fa-icon.blue{background:#eaf1ff;color:var(--blue)}.fa-icon.orange{background:#fff2e6;color:var(--orange)}.fa-icon.purple{background:#f2e8ff;color:#7c3aed}.fa-icon.gold{background:#fff6dc;color:var(--gold)}.fa-icon.red{background:#fee2e2;color:var(--red)}.fa-kpi small{display:block;color:var(--muted);font-weight:800}.fa-kpi b{display:block;font-size:1.35rem}.fa-kpi span{font-size:.83rem;color:var(--green);font-weight:800}.fa-grid{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:16px}.span-3{grid-column:span 3}.span-4{grid-column:span 4}.span-5{grid-column:span 5}.span-6{grid-column:span 6}.span-7{grid-column:span 7}.span-8{grid-column:span 8}.span-12{grid-column:span 12}.fa-panel{padding:16px}.fa-panel-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;border-bottom:1px solid #edf2f7;padding-bottom:10px}.fa-list{display:grid;gap:10px}.fa-row{display:grid;grid-template-columns:auto 1fr auto;gap:12px;align-items:center;padding:10px;border-bottom:1px solid #edf2f7;text-decoration:none;color:inherit}.fa-row:last-child{border-bottom:0}.thumb{width:52px;height:52px;border-radius:8px;object-fit:cover;background:#eaf7ef}.muted{color:var(--muted)}.badge{display:inline-flex;align-items:center;gap:5px;border-radius:999px;padding:5px 9px;font-size:.75rem;font-weight:900;background:#eef2f7;color:#334155}.badge.good{background:#e8f8ee;color:#087140}.badge.warn{background:#fff4dc;color:#a16207}.badge.danger{background:#fee2e2;color:#b91c1c}.badge.neutral{background:#eef2f7;color:#334155}.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;border:1px solid var(--green);background:var(--green);color:#fff;border-radius:8px;padding:10px 14px;text-decoration:none;font-weight:900}.btn.secondary,.btn.soft{background:#fff;color:var(--green)}.field-form{display:grid;gap:12px}.field-form input,.field-form select,.field-form textarea{width:100%;padding:12px;border:1px solid var(--line);border-radius:8px}.field-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}#map{height:360px;border-radius:12px;border:1px solid var(--line)}.quick-actions{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.quick-actions a{display:grid;place-items:center;text-align:center;gap:7px;min-height:104px;border:1px solid var(--line);border-radius:12px;text-decoration:none;color:var(--ink);font-weight:900;background:#fff}.footer{color:var(--muted);font-size:.9rem;padding:20px 28px;border-top:1px solid var(--line);background:#fff}.empty{padding:22px;border:1px dashed var(--line);border-radius:12px;color:var(--muted);background:#fbfdfc}@media(max-width:1250px){.fa-kpis{grid-template-columns:repeat(3,1fr)}.span-3,.span-4,.span-5,.span-6,.span-7,.span-8{grid-column:span 12}}@media(max-width:860px){.fa-shell{grid-template-columns:1fr}.fa-side{position:relative;height:auto}.fa-content{padding:18px}.fa-kpis,.field-grid,.quick-actions{grid-template-columns:1fr}}
</style></head><body><div class="fa-shell"><aside class="fa-side"><a class="fa-brand" href="<?= e($roleHome) ?>"><img src="<?= e(app_primary_logo_url()) ?>" alt="NATCODEV"><span><strong>NATCODEV</strong><br><?= e($roleLabel) ?> Workspace</span></a><div class="fa-person"><img src="<?= e($avatar) ?>" alt="<?= e((string) $user['name']) ?>"><div><b><?= e((string) $user['name']) ?></b><span><?= e($roleLabel) ?></span><span>Online</span></div></div><?php app_render_internal_public_workspace_switcher(fa_pdo(), $user, $roleKey); ?><nav class="fa-menu"><?php foreach ($menu as $key => [$label, $href, $icon]): ?><a href="<?= e($href) ?>" class="<?= $active === $key ? 'active' : '' ?>"><i data-lucide="<?= e($icon) ?>"></i><?= e($label) ?></a><?php endforeach; ?><a href="logout.php"><i data-lucide="log-out"></i>Logout</a></nav><div class="fa-sync"><h3>Field Workforce</h3><p>Practical work, evidence, safety, support, wallet, and exit stay here.</p></div></aside><section class="fa-main"><header class="fa-top"><a class="fa-chip" href="<?= e($roleHome) ?>"><i data-lucide="menu"></i></a><form class="fa-search" action="search.php" method="get"><i data-lucide="search"></i><input name="q" placeholder="Search growers, farms, tickets, locations..."></form><span class="fa-chip"><i data-lucide="map-pin"></i> <?= e($roleLabel) ?></span><a class="fa-chip" href="../index.php"><i data-lucide="home"></i> NATCODEV</a><a class="fa-chip" href="profile.php"><img src="<?= e($avatar) ?>" alt="" style="width:28px;height:28px;border-radius:50%;object-fit:cover"> Profile</a><a class="fa-chip" href="profile.php#account"><i data-lucide="settings"></i> Account</a><a class="fa-chip" href="logout.php"><i data-lucide="log-out"></i> Logout</a></header><main class="fa-content"><div class="fa-title"><h1><?= e($title) ?></h1><p><?= e($subtitle) ?></p></div>
<?php
}

function fa_footer(): void
{
    ?>
</main><footer class="footer">NATCODEV Field & Farm Workforce Channel / assignments, evidence, reports, support, wallet, Academy, and safe logout.</footer></section></div><script src="../lib/location-picker.js"></script><script>if(window.lucide){lucide.createIcons();}</script></body></html>
<?php
}

function fa_task_card(array $task): void
{
    ?>
<article class="fa-row"><img class="thumb" src="../assets/public/field-agent-operations-hero.png" alt=""><div><strong><?= e((string) $task['farm_name']) ?></strong><br><span class="muted"><?= e((string) $task['grower_name']) ?> / <?= e(trim((string) (($task['lga_name'] ?? '') . ', ' . ($task['state_name'] ?? '')), ', ')) ?></span></div><span class="badge <?= e(fa_priority_class((string) ($task['priority'] ?? 'normal'))) ?>"><?= e((string) ($task['priority'] ?? 'normal')) ?></span></article>
<?php
}

function fa_render_role_workspace(PDO $pdo, array $user, string $workspaceRole): void
{
    $profile = fa_role_profile($workspaceRole);
    $tasks = fa_task_rows($pdo, $user);
    $visits = fa_visit_rows($pdo, $user, 6);
    $wallet = wallet_get_or_create($pdo, (int) $user['id']);
    $supportOpen = 0;
    try { $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE user_id = ? AND status IN ('open','in_progress')"); $stmt->execute([(int) $user['id']]); $supportOpen = (int) $stmt->fetchColumn(); } catch (Throwable $e) {}
    fa_header((string) $profile['title'], (string) $profile['subtitle'], $user, 'overview');
    ?>
<section class="fa-kpis"><article class="fa-card fa-kpi"><span class="fa-icon"><i data-lucide="clipboard-list"></i></span><div><small>Active Work</small><b><?= count($tasks) ?></b><span><?= e((string) $profile['accent']) ?></span></div></article><article class="fa-card fa-kpi"><span class="fa-icon orange"><i data-lucide="timer"></i></span><div><small>Awaiting Action</small><b><?= fa_count($tasks, 'assigned') + fa_count($tasks, 'pending') ?></b><span>Pending or assigned</span></div></article><article class="fa-card fa-kpi"><span class="fa-icon blue"><i data-lucide="navigation"></i></span><div><small>In Progress</small><b><?= fa_count($tasks, 'in_progress') ?></b><span>Field work</span></div></article><article class="fa-card fa-kpi"><span class="fa-icon gold"><i data-lucide="wallet"></i></span><div><small>Wallet</small><b>NGN <?= e(number_format((float) ($wallet['balance'] ?? 0), 2)) ?></b><span>Available</span></div></article><article class="fa-card fa-kpi"><span class="fa-icon purple"><i data-lucide="life-buoy"></i></span><div><small>Support</small><b><?= $supportOpen ?></b><span>Open tickets</span></div></article></section>
<section class="fa-grid"><article class="fa-card fa-panel span-8"><div class="fa-panel-head"><h2><?= e((string) $profile['primary']) ?></h2><a class="btn soft" href="<?= e((string) $profile['primary_href']) ?>">Open</a></div><div class="fa-list"><?php foreach (array_slice($tasks, 0, 6) as $task) fa_task_card($task); ?><?php if (!$tasks): ?><div class="empty">No active field task is assigned to this workspace yet.</div><?php endif; ?></div></article><article class="fa-card fa-panel span-4"><div class="fa-panel-head"><h2>Enter, Work, Exit</h2><span class="badge good">Dedicated flow</span></div><div class="fa-list"><a class="fa-row" href="<?= e((string) $profile['primary_href']) ?>"><span class="fa-icon"><i data-lucide="log-in"></i></span><div><strong>1. Open role work</strong><br><span class="muted"><?= e((string) $profile['primary']) ?></span></div><span class="badge neutral">Enter</span></a><a class="fa-row" href="<?= e((string) $profile['secondary_href']) ?>"><span class="fa-icon blue"><i data-lucide="check-circle"></i></span><div><strong>2. Complete action</strong><br><span class="muted"><?= e((string) $profile['secondary']) ?></span></div><span class="badge neutral">Work</span></a><a class="fa-row" href="reports.php"><span class="fa-icon gold"><i data-lucide="file-text"></i></span><div><strong>3. Review output</strong><br><span class="muted">Reports and evidence trail</span></div><span class="badge neutral">Report</span></a><a class="fa-row" href="logout.php"><span class="fa-icon red"><i data-lucide="log-out"></i></span><div><strong>4. Logout</strong><br><span class="muted">Leave the system securely</span></div><span class="badge neutral">Exit</span></a></div></article><article class="fa-card fa-panel span-12"><div class="fa-panel-head"><h2>Workspace Tools</h2><span class="badge good"><?= e(fa_role_label($workspaceRole)) ?></span></div><div class="quick-actions"><a href="assignments.php"><i data-lucide="clipboard-list"></i>Assignments</a><a href="verification.php"><i data-lucide="shield-check"></i>Verification</a><a href="map.php"><i data-lucide="map"></i>Map</a><a href="academy.php"><i data-lucide="graduation-cap"></i>Academy</a><a href="wallet.php"><i data-lucide="wallet"></i>Wallet</a><a href="support.php"><i data-lucide="headphones"></i>Support</a><a href="profile.php"><i data-lucide="user"></i>Profile</a><a href="logout.php"><i data-lucide="log-out"></i>Logout</a></div></article></section>
<?php fa_footer(); }
