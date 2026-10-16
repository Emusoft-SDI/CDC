<?php
declare(strict_types=1);

/**
 * Cooperative partnership workspace bootstrap.
 *
 * A cooperative that partners with NATCODEV is managed by a
 * cooperative_admin account. Its members are ordinary platform
 * grower accounts linked to the cooperative through
 * cooperative_members (and denormalized onto users.cooperative_id
 * for fast queries). Members activate through the standard grower
 * engagement flow (confirmation link + temporary password), which
 * links them to the cooperative stamped on their application row.
 *
 * This file mirrors coordination/_coordination.php: lazy schema
 * boot, workspace access control, and the sidebar page shell
 * shared by every cooperative page.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/admin-layout.php';
require_once __DIR__ . '/../lib/monnify.php';
require_once __DIR__ . '/../lib/support.php';
require_once __DIR__ . '/../lib/user-workspaces.php';
require_once __DIR__ . '/../lib/admin-user-import.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function coop_pdo(): PDO
{
    static $pdo = null;
    static $booted = false;
    if (!$pdo instanceof PDO) {
        $pdo = db();
    }
    if (!$booted) {
        app_ensure_core_schema($pdo);
        admin_ensure_schema($pdo);
        wallet_ensure_schema($pdo);
        support_ensure_schema($pdo);
        enrollment_ensure_schema($pdo);
        cooperative_ensure_schema($pdo);
        admin_ensure_import_schema($pdo);
        $booted = true;
    }
    return $pdo;
}

/**
 * The current user when they administer a cooperative.
 * cooperative_current() resolves the cooperative for a
 * platform_role='cooperative_admin' account (linked via
 * users.cooperative_id) or for any user holding an active
 * cooperative_members row with role_in_cooperative='admin'
 * on an active cooperative.
 */
function coop_current_user(PDO $pdo): ?array
{
    $user = current_user($pdo);
    if (!$user) {
        return null;
    }
    return cooperative_current($pdo) !== null ? $user : null;
}

/**
 * Active 'admin' membership row on an active cooperative,
 * regardless of platform_role. Used by the login gate for
 * members who administer through an explicit membership row.
 */
function coop_has_admin_membership(PDO $pdo, int $userId): bool
{
    if ($userId <= 0) {
        return false;
    }
    $stmt = $pdo->prepare("SELECT 1 FROM cooperative_members cm JOIN cooperatives c ON c.id = cm.cooperative_id
        WHERE cm.user_id = ? AND cm.role_in_cooperative = 'admin' AND cm.status = 'active' AND c.status = 'active' LIMIT 1");
    $stmt->execute([$userId]);
    return (bool) $stmt->fetchColumn();
}

function coop_require(PDO $pdo): array
{
    $user = coop_current_user($pdo);
    if (!$user) {
        // Distinguish "not logged in" from "logged in but no cooperative
        // context". An authenticated user with no linked cooperative must
        // NOT be dead-ended on a 403 here: login.php would route them right
        // back to this page (platform_user_home() sends cooperative_admin to
        // cooperatives/index.php), creating an infinite 403 loop. Send them
        // to their platform dashboard instead — a safe, working landing page.
        $authenticated = current_user($pdo);
        if ($authenticated) {
            redirect_to('../dashboard/index.php');
        }
        redirect_to('login.php?next=' . urlencode((string) ($_SERVER['REQUEST_URI'] ?? 'index.php')));
    }
    if (app_user_needs_email_verification($user)) {
        redirect_to('login.php?email=' . urlencode((string) ($user['email'] ?? '')));
    }
    if (!admin_feature_is_allowed($pdo, 'applications')) {
        app_render_error_page(403, 'The cooperative module is disabled for your role.');
    }
    return $user;
}

function coop_login_home(array $user): string
{
    return 'index.php';
}

function coop_login_destination(array $user): string
{
    return 'cooperatives/' . coop_login_home($user);
}

/**
 * A ?next= value is only honored when it points at a page
 * inside this workspace, so a guest can never be bounced to
 * an off-site URL after login.
 */
function coop_safe_next(string $next): string
{
    if ($next === '') {
        return '';
    }
    // Strip absolute prefixes like /win/cooperatives/ or http://...
    $path = parse_url(trim($next), PHP_URL_PATH);
    if ($path === '' || $path === null) {
        return '';
    }
    $path = ltrim($path, '/');
    // Extract just the filename part (e.g., "index.php", "members.php")
    $filename = basename($path);
    // Validate: must be a .php file with safe characters
    if (!preg_match('~^[A-Za-z0-9_.\-]+\.php$~', $filename)) {
        return '';
    }
    return 'cooperatives/' . $filename;
}

function coop_state_name(PDO $pdo, int|string|null $stateId): string
{
    $stateId = (int) $stateId;
    if ($stateId <= 0) {
        return '';
    }
    $stmt = $pdo->prepare('SELECT state_name FROM nigeria_states WHERE id = ? LIMIT 1');
    $stmt->execute([$stateId]);
    return (string) ($stmt->fetchColumn() ?: '');
}

function coop_lga_name(PDO $pdo, int|string|null $lgaId): string
{
    $lgaId = (int) $lgaId;
    if ($lgaId <= 0) {
        return '';
    }
    $stmt = $pdo->prepare('SELECT lga_name FROM nigeria_lgas WHERE id = ? LIMIT 1');
    $stmt->execute([$lgaId]);
    return (string) ($stmt->fetchColumn() ?: '');
}

/**
 * Membership total for a status filter; '' or 'all' counts
 * every membership row whatever its status. Thin wrapper over
 * cooperative_member_count() so pages never reinvent the query.
 */
function coop_member_total(PDO $pdo, int $cooperativeId, string $status = ''): int
{
    if ($status !== '' && $status !== 'all') {
        return cooperative_member_count($pdo, $cooperativeId, $status);
    }
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM cooperative_members WHERE cooperative_id = ?');
    $stmt->execute([$cooperativeId]);
    return (int) $stmt->fetchColumn();
}

/**
 * Active members whose platform account is still awaiting
 * confirmation or activation.
 */
function cooperative_pending_members(PDO $pdo, int $cooperativeId): int
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM cooperative_members cm JOIN users u ON u.id = cm.user_id
        WHERE cm.cooperative_id = ? AND cm.status = 'active'
        AND u.account_status IN ('pending', 'needs_confirmation', 'unconfirmed', 'unverified')");
    $stmt->execute([$cooperativeId]);
    return (int) $stmt->fetchColumn();
}

function coop_header(string $title, string $subtitle, array $user, string $active): void
{
    $pdo = coop_pdo();
    $cooperative = cooperative_current($pdo);
    $label = 'Cooperative Admin';
    $home = 'index.php';
    $menu = [
        'home' => ['Dashboard', 'index.php', 'fa-gauge-high'],
        'members' => ['Members', 'members.php', 'fa-users'],
        'import' => ['Import Members', 'import.php', 'fa-file-import'],
        'profile' => ['Cooperative Profile', 'profile.php', 'fa-user'],
    ];
    $cooperativeName = (string) ($cooperative['name'] ?? 'Your Cooperative');
    $stateName = coop_state_name($pdo, (int) ($cooperative['state_id'] ?? 0));
    $lgaName = (string) ($cooperative['lga'] ?? '');
    $locationLabel = trim($stateName . ($lgaName !== '' ? ' / ' . $lgaName : ''));
    ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= e($title) ?> - NATCODEV Cooperative Workspace</title><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"><style>
:root{--green:#075f2a;--deep:#053b1c;--line:#dfe8d8;--bg:#f6faf4;--ink:#101828;--muted:#667085;--gold:#c69320;--red:#b42318;--blue:#175cd3}*{box-sizing:border-box}body{margin:0;background:var(--bg);font-family:"Segoe UI",Arial,sans-serif;color:var(--ink)}a{text-decoration:none;color:inherit}.shell{display:grid;grid-template-columns:286px 1fr;min-height:100vh}.side{background:linear-gradient(180deg,#06451f,#092f22);color:#fff;padding:22px 18px;position:sticky;top:0;height:100vh;overflow:auto}.brand{display:flex;gap:12px;align-items:center;padding-bottom:18px;border-bottom:1px solid rgba(255,255,255,.16)}.brand img{width:56px;height:56px;border-radius:50%;background:#fff}.brand strong{font-size:1.35rem}.brand small{display:block;color:#bff0ce}.person{margin:18px 0;padding:14px;border:1px solid rgba(255,255,255,.16);border-radius:8px;background:rgba(255,255,255,.08)}.scope-box{display:grid;gap:7px;margin:12px 0 16px;padding:12px;border:1px solid rgba(255,255,255,.16);border-radius:8px;background:rgba(255,255,255,.08)}.scope-box strong{font-size:.78rem;color:#bff0ce;text-transform:uppercase}.scope-link{display:block;color:#eef8ef;padding:8px 10px;border-radius:7px;font-weight:900}.scope-link.active,.scope-link:hover{background:#118b42}.scope-warning{color:#ffe8a3;font-size:.84rem;line-height:1.45}.workspace-switcher{display:grid;gap:7px;margin:12px 0 16px;padding:12px;border:1px solid rgba(255,255,255,.16);border-radius:8px;background:rgba(255,255,255,.06)}.workspace-switcher strong{font-size:.78rem;color:#bff0ce;text-transform:uppercase}.workspace-link{display:block;color:#eef8ef;padding:9px 10px;border-radius:7px;font-weight:900}.workspace-link.active,.workspace-link:hover{background:#118b42}.nav{display:grid;gap:8px}.nav a{display:flex;gap:10px;align-items:center;padding:12px;border-radius:8px;color:#eef8ef;font-weight:900}.nav a.active,.nav a:hover{background:#118b42}.main{min-width:0}.top{height:72px;background:#fff;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;align-items:center;padding:0 26px;gap:14px;position:sticky;top:0;z-index:4}.chip{border:1px solid var(--line);background:#fff;border-radius:8px;padding:9px 11px;font-weight:850}.content{padding:26px}.page-head{display:flex;justify-content:space-between;gap:16px;align-items:flex-end;margin-bottom:18px}.page-head h1{margin:0;color:#082f19}.page-head p{margin:5px 0 0;color:var(--muted)}.kpis{display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:16px}.kpi{background:#fff;border:1px solid var(--line);border-radius:8px;padding:16px;box-shadow:0 12px 32px rgba(16,24,40,.06)}.kpi i{color:var(--green);font-size:1.25rem}.kpi b{display:block;font-size:1.55rem;margin-top:6px}.grid{display:grid;grid-template-columns:repeat(12,1fr);gap:16px}.card{background:#fff;border:1px solid var(--line);border-radius:8px;padding:16px;box-shadow:0 12px 32px rgba(16,24,40,.06)}.span-4{grid-column:span 4}.span-6{grid-column:span 6}.span-8{grid-column:span 8}.span-12{grid-column:span 12}.list{display:grid;gap:10px}.row{display:flex;justify-content:space-between;gap:12px;border-top:1px solid var(--line);padding-top:10px}.row:first-child{border-top:0;padding-top:0}.badge{display:inline-flex;border-radius:999px;padding:5px 9px;background:#eef8ef;color:var(--green);font-weight:900;font-size:.78rem}.badge.warn{background:#fffbeb;color:#b45309}.badge.bad{background:#fff1f2;color:var(--red)}.btn{display:inline-flex;gap:8px;align-items:center;justify-content:center;border:1px solid var(--green);background:var(--green);color:#fff;border-radius:8px;padding:10px 14px;font-weight:950}.btn.light{background:#fff;color:var(--green)}.footer{padding:18px 26px;color:var(--muted);border-top:1px solid var(--line);background:#fff}.table{width:100%;border-collapse:collapse}.table th,.table td{padding:10px 8px;border-bottom:1px solid var(--line);text-align:left;font-size:.86rem;vertical-align:top}.table th{font-size:.72rem;text-transform:uppercase;color:var(--muted)}.form-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:14px}.field{display:grid;gap:6px}.field label{font-weight:900;font-size:.85rem}.field input,.field textarea{border:1px solid var(--line);border-radius:8px;padding:11px 12px;font:inherit;background:#fff;color:var(--ink)}.field input[readonly],.field input:disabled{background:#f4f7f2;color:var(--muted)}.filters{display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end}.filters .field{min-width:190px}}.pagination{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px;align-items:center}.pagination a,.pagination span{border:1px solid var(--line);border-radius:8px;padding:8px 11px;font-weight:900;background:#fff}.pagination .current{background:var(--green);color:#fff;border-color:var(--green)}@media(max-width:980px){.shell{grid-template-columns:1fr}.side{position:relative;height:auto}.kpis{grid-template-columns:1fr 1fr}.span-4,.span-6,.span-8{grid-column:span 12}.form-grid{grid-template-columns:1fr}}@media(max-width:640px){.kpis{grid-template-columns:1fr}.top,.page-head,.footer{align-items:flex-start;flex-direction:column}}
</style></head><body><div class="shell"><aside class="side"><a class="brand" href="<?= e($home) ?>"><img src="<?= e(app_primary_logo_url()) ?>" alt="NATCODEV"><span><strong>NATCODEV</strong><small>Cooperative Channel</small></span></a><div class="person"><strong><?= e((string) ($user['name'] ?? 'Cooperative Admin')) ?></strong><small><?= e($label) ?></small><br><span class="badge">Partnership stakeholder</span></div><div class="scope-box"><strong>Cooperative</strong><span class="scope-link active"><i class="fas fa-building-columns"></i> <?= e($cooperativeName) ?></span><?php if ((string) ($cooperative['registration_number'] ?? '') !== ''): ?><span class="scope-link">Reg: <?= e((string) $cooperative['registration_number']) ?></span><?php endif; ?><?php if ($locationLabel !== ''): ?><span class="scope-link"><i class="fas fa-map-location-dot"></i> <?= e($locationLabel) ?></span><?php endif; ?><span class="scope-link">Status: <?= e((string) ($cooperative['status'] ?? 'pending')) ?></span><?php if ((string) ($cooperative['contact_email'] ?? '') !== '' || (string) ($cooperative['contact_phone'] ?? '') !== ''): ?><span class="scope-link">Contact: <?= e(trim(((string) ($cooperative['contact_name'] ?? '') . ' ' . (string) ($cooperative['contact_email'] ?? '') . ' ' . (string) ($cooperative['contact_phone'] ?? '')))) ?></span><?php endif; ?></div><?php if (function_exists('app_render_internal_public_workspace_switcher')): ?><?php app_render_internal_public_workspace_switcher($pdo, $user, 'cooperative_admin'); ?><?php endif; ?><nav class="nav"><?php foreach ($menu as $key => $item): ?><a class="<?= $active === $key ? 'active' : '' ?>" href="<?= e($item[1]) ?>"><i class="fas <?= e($item[2]) ?>"></i><?= e($item[0]) ?></a><?php endforeach; ?><a href="logout.php?_csrf=<?= e(csrf_token()) ?>"><i class="fas fa-right-from-bracket"></i>Logout</a></nav></aside><main class="main"><header class="top"><span class="chip"><i class="fas fa-building-columns"></i> <?= e($label) ?></span><span class="chip"><i class="fas fa-map-location-dot"></i> <?= e($locationLabel !== '' ? $locationLabel : 'Cooperative') ?></span><span class="chip"><?= e(date('M j, Y h:i A')) ?></span><a class="chip" href="../index.php"><i class="fas fa-house"></i> NATCODEV</a><a class="chip" href="profile.php"><i class="fas fa-user"></i> Profile</a><a class="chip" href="logout.php?_csrf=<?= e(csrf_token()) ?>"><i class="fas fa-right-from-bracket"></i> Logout</a></header><?= page_breadcrumbs(auto_breadcrumbs($menu, $active, 'Cooperative Channel', $home), 'NATCODEV', '../index.php') ?><section class="content"><div class="page-head"><div><h1><?= e($title) ?></h1><p><?= e($subtitle) ?></p></div><a class="btn light" href="logout.php?_csrf=<?= e(csrf_token()) ?>"><i class="fas fa-right-from-bracket"></i> Logout</a></div>
<?php
}

function coop_footer(): void
{
    ?>
</section><footer class="footer">NATCODEV Cooperative Workspace / member management, member imports, and cooperative profile.</footer></main></div></body></html>
<?php
}
