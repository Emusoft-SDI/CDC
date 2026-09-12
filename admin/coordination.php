<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../lib/admin-layout.php';
require_once __DIR__ . '/../lib/admin-operator-strip.php';
require_once __DIR__ . '/../lib/platform-governance.php';
require_once __DIR__ . '/../lib/marketplace.php';
require_once __DIR__ . '/../lib/monnify.php';
require_once __DIR__ . '/../lib/academy.php';
require_once __DIR__ . '/../lib/support.php';
require_once __DIR__ . '/../lib/field-management.php';

$pdo = db();
admin_ensure_schema($pdo);
pg_ensure_schema($pdo);
marketplace_ensure_schema($pdo);
wallet_ensure_schema($pdo);
academy_ensure_schema($pdo);
support_ensure_schema($pdo);
fm_ensure_schema($pdo);
admin_require($pdo);

$admin = current_user($pdo) ?: [];

function ao_scalar(PDO $pdo, string $sql, array $params = []): float
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (float) ($stmt->fetchColumn() ?: 0);
    } catch (Throwable $e) {
        error_log('Admin outlook scalar failed: ' . $e->getMessage());
        return 0.0;
    }
}

function ao_rows(PDO $pdo, string $sql, array $params = []): array
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log('Admin outlook rows failed: ' . $e->getMessage());
        return [];
    }
}

function ao_money(float $amount): string
{
    return 'N' . number_format($amount, 2);
}

function ao_badge(string $status): string
{
    return match ($status) {
        'active', 'verified', 'confirmed', 'issued', 'resolved', 'completed', 'approved', 'paid', 'successful' => 'ok',
        'pending', 'pending_review', 'open', 'in_progress', 'under_review', 'processing' => 'warn',
        'rejected', 'failed', 'escalated', 'revoked' => 'bad',
        default => 'info',
    };
}

$verifiedGrowers = (int) ao_scalar($pdo, "SELECT COUNT(DISTINCT gf.user_id) FROM grower_farms gf JOIN farm_verifications fv ON fv.farm_id = gf.id WHERE fv.status = 'verified'");
$activeProviders = (int) ao_scalar($pdo, "SELECT COUNT(*) FROM provider_registry WHERE status IN ('verified','active','approved')");
$marketplaceOrders = (int) ao_scalar($pdo, "SELECT COUNT(*) FROM marketplace_orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$walletBalance = ao_scalar($pdo, "SELECT COALESCE(SUM(balance), 0) FROM wallets");
$trainingCompletion = ao_scalar($pdo, "SELECT CASE WHEN COUNT(*) = 0 THEN 0 ELSE ROUND((SUM(completion_status = 'completed') / COUNT(*)) * 100, 1) END FROM webinar_registrations");
$openTickets = (int) ao_scalar($pdo, "SELECT COUNT(*) FROM support_tickets WHERE status IN ('open','in_progress','waiting_on_user','escalated')");
$pendingApplications = (int) ao_scalar($pdo, "SELECT COUNT(*) FROM applications WHERE confirmed = 0 OR review_status IN ('pending','pending_review','under_review')");
$unreadMessages = (int) ao_scalar($pdo, "SELECT COUNT(*) FROM support_ticket_messages WHERE visibility = 'public' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");

$recentApplications = ao_rows($pdo, "
    SELECT a.app_ref, a.name, a.created_at, a.review_status, COALESCE(ns.state_name, a.location) state_name, COALESCE(nl.lga_name, '') lga_name
    FROM applications a
    LEFT JOIN nigeria_states ns ON ns.id = a.state_id
    LEFT JOIN nigeria_lgas nl ON nl.id = a.lga_id
    ORDER BY a.created_at DESC
    LIMIT 6
");
$activityRows = ao_rows($pdo, "
    SELECT 'Application submitted' title, name actor, created_at, location detail FROM applications ORDER BY created_at DESC LIMIT 3
");
$ticketRows = ao_rows($pdo, "
    SELECT ticket_ref, subject, requester_name, priority, status, created_at
    FROM support_tickets
    ORDER BY last_activity_at DESC, id DESC
    LIMIT 5
");
$stateActivityRows = ao_rows($pdo, "
    SELECT state_name,
           COUNT(*) activity_total,
           SUM(created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) recent_total
    FROM (
        SELECT COALESCE(ns.state_name, NULLIF(a.location, ''), 'Unspecified') state_name, a.created_at
        FROM applications a
        LEFT JOIN nigeria_states ns ON ns.id = a.state_id
    ) state_activity
    GROUP BY state_name
    ORDER BY activity_total DESC, state_name
    LIMIT 12
");
$stateActivityRows = is_array($stateActivityRows) ? $stateActivityRows : [];
$maxStateActivity = max(1, ...array_map(static fn(array $row): int => (int) ($row['activity_total'] ?? 0), $stateActivityRows ?: [['activity_total' => 1]]));
$stateMapPoints = [
    'Abia' => [70, 72], 'Adamawa' => [76, 42], 'Akwa Ibom' => [70, 82], 'Anambra' => [62, 70], 'Bauchi' => [58, 35],
    'Bayelsa' => [50, 82], 'Benue' => [60, 58], 'Borno' => [82, 23], 'Cross River' => [76, 75], 'Delta' => [52, 75],
    'Ebonyi' => [68, 68], 'Edo' => [48, 68], 'Ekiti' => [39, 60], 'Enugu' => [64, 66], 'Federal Capital Territory' => [50, 51],
    'FCT' => [50, 51], 'Gombe' => [64, 38], 'Imo' => [66, 74], 'Jigawa' => [54, 21], 'Kaduna' => [44, 35],
    'Kano' => [47, 24], 'Katsina' => [40, 19], 'Kebbi' => [22, 29], 'Kogi' => [50, 61], 'Kwara' => [36, 52],
    'Lagos' => [25, 74], 'Nasarawa' => [56, 52], 'Niger' => [36, 43], 'Ogun' => [29, 70], 'Ondo' => [42, 66],
    'Osun' => [36, 64], 'Oyo' => [31, 61], 'Plateau' => [58, 44], 'Rivers' => [58, 80], 'Sokoto' => [25, 20],
    'Taraba' => [72, 52], 'Yobe' => [72, 25], 'Zamfara' => [34, 27],
];
function ao_map_point_for_state(string $state, array $points, int $index): array
{
    $state = trim($state);
    if (isset($points[$state])) {
        return $points[$state];
    }

    return [34 + (($index * 13) % 42), 28 + (($index * 17) % 46)];
}

admin_page_start('Admin Operations Outlook', [
    'active' => 'coordination.php',
    'description' => 'Overview of registry operations, marketplace, learning, and support activities.',
    'wide' => true,
    'chrome' => false,
    'css' => '
    html,body,.admin-shell{width:100%;max-width:none}body{overflow-x:hidden}main.admin-main{max-width:none!important;width:100vw!important;margin:0!important;padding:18px!important}.ao-workspace{display:grid;grid-template-columns:230px minmax(0,1fr);gap:18px;align-items:start;width:100%;max-width:none;margin:0}.ao-rail{position:sticky;top:18px;min-height:calc(100vh - 36px);border-radius:8px;background:linear-gradient(180deg,#063f24,#005b32);color:#fff;padding:16px;box-shadow:0 18px 42px rgba(6,63,36,.22)}.ao-brand{display:flex;gap:10px;align-items:center;border-bottom:1px solid rgba(255,255,255,.14);padding-bottom:14px;margin-bottom:14px}.ao-brand img{width:46px;height:46px;border-radius:50%;background:#fff;padding:4px}.ao-brand strong{display:block}.ao-brand small{display:block;color:#dff5e8;font-size:.72rem}.ao-label{font-size:.72rem;text-transform:uppercase;color:#aee4c4;font-weight:900;margin:14px 4px 8px}.ao-nav{display:grid;gap:5px}.ao-nav a{display:flex;align-items:center;justify-content:space-between;gap:10px;color:#fff;text-decoration:none;padding:10px 11px;border-radius:8px;font-weight:850}.ao-nav a:hover,.ao-nav a.active{background:rgba(46,204,113,.24)}.ao-content{min-width:0}.ao-top{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:12px}.ao-search{flex:1;min-width:280px;border:1px solid var(--line);border-radius:8px;background:#fff;display:flex;align-items:center;gap:10px;padding:9px 12px;color:var(--muted)}.ao-search input{border:0;box-shadow:none;padding:0}.ao-head{display:flex;justify-content:space-between;align-items:end;gap:12px;flex-wrap:wrap;margin-bottom:14px}.ao-head h2{font-size:1.65rem;margin:0;color:#0b1f16}.ao-head p{margin:4px 0 0;color:var(--muted)}.ao-tool{border:1px solid var(--line);border-radius:8px;background:#fff;padding:9px 11px;font-weight:850;color:#102033}.ao-kpis{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.ao-kpi{border:1px solid var(--line);border-radius:8px;background:#fff;box-shadow:var(--shadow);padding:14px;display:flex;justify-content:space-between;gap:10px;min-height:112px}.ao-kpi small{display:block;text-transform:uppercase;font-size:.72rem;font-weight:900;color:#536171}.ao-kpi strong{display:block;font-size:1.45rem;color:#101828;margin-top:7px}.ao-kpi span{display:block;color:#079455;font-size:.78rem;font-weight:850;margin-top:5px}.ao-icon{width:48px;height:48px;border-radius:50%;display:grid;place-items:center;background:#e8f5ed;color:#087443}.ao-icon.blue{background:#e8f1ff;color:#175cd3}.ao-icon.orange{background:#fff1df;color:#c05600}.ao-icon.purple{background:#f1e9ff;color:#6941c6}.ao-icon.red{background:#fee4e2;color:#d92d20}.ao-grid{display:grid;grid-template-columns:1.35fr .9fr;gap:14px;margin-top:14px}.ao-row{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:14px}.ao-panel{border:1px solid var(--line);border-radius:8px;background:#fff;box-shadow:var(--shadow);padding:14px}.ao-panel-head{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:10px}.ao-panel-head h3{margin:0;color:#102033;font-size:1rem}.ao-panel-head a{color:#0f6b3c;text-decoration:none;font-weight:900;font-size:.82rem}.ao-chart{height:230px;display:flex;align-items:end;gap:16px;border-bottom:1px solid #d8dee6;padding:12px 8px 0}.ao-bar{flex:1;border-radius:8px 8px 0 0;background:linear-gradient(180deg,#0f6b3c,#9bd6ae);min-height:34px}.ao-map{height:250px;border-radius:8px;background:linear-gradient(135deg,#eef8f0,#d8efe0);position:relative;overflow:hidden;color:#0f6b3c;font-weight:850;border:1px solid #c7e4d0}.ao-map-shape{position:absolute;inset:18px 28px;background:rgba(15,107,60,.14);clip-path:polygon(10% 47%,24% 18%,45% 10%,69% 15%,88% 30%,94% 50%,82% 72%,57% 90%,33% 84%,17% 66%);border:1px solid rgba(15,107,60,.25)}.ao-map-dot{position:absolute;left:var(--x);top:var(--y);width:var(--s);height:var(--s);transform:translate(-50%,-50%);border-radius:50%;background:#0f6b3c;border:3px solid #fff;box-shadow:0 8px 18px rgba(6,63,36,.24);display:grid;place-items:center;color:#fff;font-size:.68rem;line-height:1;cursor:default}.ao-map-dot small{position:absolute;left:50%;top:calc(100% + 4px);transform:translateX(-50%);white-space:nowrap;background:#fff;color:#0b5131;border:1px solid #cfe8d8;border-radius:999px;padding:2px 6px;font-size:.64rem;font-weight:900;box-shadow:0 5px 12px rgba(6,63,36,.08)}.ao-map-empty{position:absolute;inset:0;display:grid;place-items:center;text-align:center;color:#0f6b3c;padding:20px}.ao-map-legend{position:absolute;left:12px;right:12px;bottom:10px;display:flex;justify-content:space-between;gap:8px;font-size:.72rem;color:#315342}.ao-map-legend b{color:#0f6b3c}.ao-table{width:100%;border-collapse:collapse}.ao-table th,.ao-table td{padding:9px 8px;border-bottom:1px solid #edf1f4;text-align:left;font-size:.8rem}.ao-table th{font-size:.72rem;text-transform:uppercase;color:#667085}.ao-badge{display:inline-flex;border-radius:999px;padding:3px 7px;font-size:.7rem;font-weight:900}.ao-badge.ok{background:#dcfae6;color:#067647}.ao-badge.info{background:#dbeafe;color:#175cd3}.ao-badge.warn{background:#fef0c7;color:#b54708}.ao-badge.bad{background:#fee4e2;color:#b42318}.ao-list{display:grid;gap:9px}.ao-list-row{display:flex;justify-content:space-between;gap:10px;border-bottom:1px solid #eef2f4;padding-bottom:9px;font-size:.83rem}.ao-list-row small{display:block;color:var(--muted);margin-top:2px}.ao-actions{display:grid;gap:10px}.ao-action{border:1px solid var(--line);border-radius:8px;background:#fff;padding:13px;display:flex;gap:12px;align-items:center;color:inherit;text-decoration:none}.ao-action i{width:34px;height:34px;border-radius:50%;display:grid;place-items:center;background:#e8f5ed;color:#0f6b3c}@media(max-width:1100px){.ao-workspace,.ao-grid,.ao-row{grid-template-columns:1fr}.ao-rail{position:relative;top:auto;min-height:auto}.ao-nav,.ao-kpis{grid-template-columns:1fr 1fr}}@media(max-width:700px){.ao-nav,.ao-kpis{grid-template-columns:1fr}}',
]);
?>
<div class="ao-workspace">
  <aside class="ao-rail" aria-label="Admin operations navigation">
    <div class="ao-brand"><img src="<?= e(app_admin_logo_url()) ?>" alt="NATCODEV"><div><strong>NATCODEV</strong><small>Admin Operations</small></div></div>
    <div class="ao-label">Main Navigation</div>
    <nav class="ao-nav">
      <a class="active" href="coordination.php"><span><i class="fa-solid fa-house"></i> Dashboard</span></a>
      <a href="registry.php"><span><i class="fa-solid fa-id-card"></i> Registry</span></a>
      <a href="wallet.php"><span><i class="fa-solid fa-wallet"></i> Wallet</span></a>
      <a href="marketplace.php"><span><i class="fa-solid fa-cart-shopping"></i> Marketplace</span></a>
      <a href="academy.php"><span><i class="fa-solid fa-graduation-cap"></i> Academy</span></a>
      <a href="reports.php"><span><i class="fa-solid fa-chart-line"></i> Reports</span></a>
      <a href="support.php"><span><i class="fa-solid fa-headset"></i> Support Desk</span></a>
      <a href="settings.php"><span><i class="fa-solid fa-gear"></i> Settings</span></a>
    </nav>
    <div class="ao-label">Quick Links</div>
    <nav class="ao-nav">
      <a href="admin.php"><span><i class="fa-solid fa-user-plus"></i> Add New Grower</span></a>
      <a href="document-verification.php"><span><i class="fa-solid fa-shield-check"></i> Verify Documents</span></a>
      <a href="communications.php"><span><i class="fa-solid fa-bullhorn"></i> Create Announcement</span></a>
      <a href="monitoring.php"><span><i class="fa-solid fa-heart-pulse"></i> System Health</span></a>
    </nav>
  </aside>
  <main class="ao-content">
    <?= admin_workspace_operator_strip($pdo, ['asset_prefix' => '../', 'profile_href' => 'profile.php', 'password_href' => 'profile.php#password', 'logout_action' => 'admin.php', 'title' => 'Operations workspace', 'placeholder' => 'Search growers, applications, operations...']) ?>
    <div class="ao-top">
      <div class="ao-search"><i class="fa-solid fa-magnifying-glass"></i><input aria-label="Search operations" placeholder="Search growers, applications, documents..."></div>
      <span class="ao-tool"><i class="fa-regular fa-bell"></i> <?= $pendingApplications ?></span>
      <span class="ao-tool"><i class="fa-regular fa-envelope"></i> <?= $unreadMessages ?></span>
      <a class="button secondary" href="reports.php">Export</a>
    </div>
    <div class="ao-head">
      <div><h2>NATCODEV Operations Dashboard</h2><p>Overview of registry operations, marketplace, learning, and support activities.</p></div>
      <span class="ao-tool"><?= e(date('M j, Y')) ?></span>
    </div>

    <section class="notice ok">Welcome back, <?= e((string) ($admin['name'] ?? 'Admin')) ?>. You have <?= number_format($pendingApplications) ?> applications and <?= number_format($unreadMessages) ?> new support messages.</section>

    <section class="ao-kpis">
      <div class="ao-kpi"><div><small>Verified Growers</small><strong><?= number_format($verifiedGrowers) ?></strong><span>Registry confidence</span></div><div class="ao-icon"><i class="fa-solid fa-users"></i></div></div>
      <div class="ao-kpi"><div><small>Active Providers</small><strong><?= number_format($activeProviders) ?></strong><span>Provider network</span></div><div class="ao-icon blue"><i class="fa-solid fa-store"></i></div></div>
      <div class="ao-kpi"><div><small>Marketplace Orders</small><strong><?= number_format($marketplaceOrders) ?></strong><span>Last 30 days</span></div><div class="ao-icon orange"><i class="fa-solid fa-cart-shopping"></i></div></div>
      <div class="ao-kpi"><div><small>Wallet Volume</small><strong><?= e(ao_money($walletBalance)) ?></strong><span>Current balance</span></div><div class="ao-icon"><i class="fa-solid fa-wallet"></i></div></div>
      <div class="ao-kpi"><div><small>Training Completion</small><strong><?= number_format($trainingCompletion, 1) ?>%</strong><span>Academy completion</span></div><div class="ao-icon purple"><i class="fa-solid fa-graduation-cap"></i></div></div>
      <div class="ao-kpi"><div><small>Open Support Tickets</small><strong><?= number_format($openTickets) ?></strong><span>Needs attention</span></div><div class="ao-icon red"><i class="fa-solid fa-headset"></i></div></div>
    </section>

    <section class="ao-grid">
      <div class="ao-panel">
        <div class="ao-panel-head"><h3>Registry Growth</h3><a href="registry.php">Last 6 months</a></div>
        <div class="ao-chart"><?php foreach ([35, 44, 51, 68, 82, 94] as $h): ?><div class="ao-bar" style="height:<?= $h ?>%"></div><?php endforeach; ?></div>
      </div>
      <div class="ao-panel">
        <div class="ao-panel-head"><h3>Activity by State</h3><a href="national-dashboard.php">This month</a></div>
                <div class="ao-map" role="img" aria-label="National activity map by state">
          <div class="ao-map-shape" aria-hidden="true"></div>
          <?php foreach ($stateActivityRows as $index => $stateRow): ?>
            <?php
              $stateName = (string) ($stateRow['state_name'] ?? 'Unspecified');
              [$x, $y] = ao_map_point_for_state($stateName, $stateMapPoints, $index);
              $total = max(1, (int) ($stateRow['activity_total'] ?? 0));
              $size = 18 + (int) round(($total / $maxStateActivity) * 22);
            ?>
            <span class="ao-map-dot" style="--x:<?= (int) $x ?>%;--y:<?= (int) $y ?>%;--s:<?= (int) $size ?>px" title="<?= e($stateName) ?>: <?= number_format($total) ?> activities">
              <?= number_format($total) ?>
              <small><?= e($stateName) ?></small>
            </span>
          <?php endforeach; ?>
          <?php if (!$stateActivityRows): ?><div class="ao-map-empty"><strong>National Activity Map</strong><br><small>No state activity data is available yet.</small></div><?php endif; ?>
          <div class="ao-map-legend"><span><b>National Activity Map</b></span><span>Top states by applications</span></div>
        </div>
      </div>
    </section>

    <section class="ao-row">
      <div class="ao-panel">
        <div class="ao-panel-head"><h3>Recent Applications</h3><a href="admin.php">View All</a></div>
        <table class="ao-table"><thead><tr><th>ID</th><th>Applicant</th><th>State</th><th>LGA</th><th>Submitted</th><th>Status</th></tr></thead><tbody>
          <?php foreach ($recentApplications as $row): ?><tr><td><?= e((string) $row['app_ref']) ?></td><td><?= e((string) $row['name']) ?></td><td><?= e((string) $row['state_name']) ?></td><td><?= e((string) $row['lga_name']) ?></td><td><?= e(date('M j, Y', strtotime((string) $row['created_at']))) ?></td><td><span class="ao-badge <?= e(ao_badge((string) $row['review_status'])) ?>"><?= e(ucwords(str_replace('_', ' ', (string) $row['review_status']))) ?></span></td></tr><?php endforeach; ?>
        </tbody></table>
      </div>
      <div class="ao-panel">
        <div class="ao-panel-head"><h3>Recent Activity</h3><a href="support.php">View</a></div>
        <div class="ao-list">
          <?php foreach ($activityRows as $row): ?><div class="ao-list-row"><div><strong><?= e((string) $row['title']) ?></strong><small><?= e((string) $row['actor']) ?> / <?= e((string) $row['detail']) ?></small></div><span><?= e(date('g:i A', strtotime((string) $row['created_at']))) ?></span></div><?php endforeach; ?>
          <?php foreach ($ticketRows as $row): ?><div class="ao-list-row"><div><strong><?= e((string) $row['ticket_ref']) ?></strong><small><?= e((string) $row['subject']) ?></small></div><span class="ao-badge <?= e(ao_badge((string) $row['status'])) ?>"><?= e((string) $row['priority']) ?></span></div><?php endforeach; ?>
        </div>
      </div>
    </section>

    <section class="ao-panel" style="margin-top:14px">
      <div class="ao-panel-head"><h3>Quick Actions</h3></div>
      <div class="ao-actions">
        <a class="ao-action" href="admin.php"><i class="fa-solid fa-user-plus"></i><span><strong>Add New Grower</strong><small>Register or review application</small></span></a>
        <a class="ao-action" href="document-verification.php"><i class="fa-solid fa-shield-check"></i><span><strong>Verify Applications</strong><small>Review documents</small></span></a>
        <a class="ao-action" href="import-users.php"><i class="fa-solid fa-upload"></i><span><strong>Upload Document</strong><small>Import registry records</small></span></a>
        <a class="ao-action" href="communications.php"><i class="fa-solid fa-bullhorn"></i><span><strong>Create Announcement</strong><small>Notify stakeholders</small></span></a>
        <a class="ao-action" href="reports.php"><i class="fa-solid fa-file-export"></i><span><strong>View Reports</strong><small>Open reporting intelligence</small></span></a>
      </div>
    </section>
  </main>
</div>
<?php admin_page_end(); ?>
