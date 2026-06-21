<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../lib/admin-layout.php';
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

admin_page_start('Admin Operations Outlook', [
    'active' => 'coordination.php',
    'description' => 'Overview of registry operations, marketplace, learning, and support activities.',
    'wide' => true,
    'css' => '
    .ao-workspace{display:grid;grid-template-columns:230px minmax(0,1fr);gap:18px;align-items:start}.ao-rail{position:sticky;top:92px;min-height:calc(100vh - 140px);border-radius:8px;background:linear-gradient(180deg,#063f24,#005b32);color:#fff;padding:16px;box-shadow:0 18px 42px rgba(6,63,36,.22)}.ao-brand{display:flex;gap:10px;align-items:center;border-bottom:1px solid rgba(255,255,255,.14);padding-bottom:14px;margin-bottom:14px}.ao-brand img{width:46px;height:46px;border-radius:50%;background:#fff;padding:4px}.ao-brand strong{display:block}.ao-brand small{display:block;color:#dff5e8;font-size:.72rem}.ao-label{font-size:.72rem;text-transform:uppercase;color:#aee4c4;font-weight:900;margin:14px 4px 8px}.ao-nav{display:grid;gap:5px}.ao-nav a{display:flex;align-items:center;justify-content:space-between;gap:10px;color:#fff;text-decoration:none;padding:10px 11px;border-radius:8px;font-weight:850}.ao-nav a:hover,.ao-nav a.active{background:rgba(46,204,113,.24)}.ao-content{min-width:0}.ao-top{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:12px}.ao-search{flex:1;min-width:280px;border:1px solid var(--line);border-radius:8px;background:#fff;display:flex;align-items:center;gap:10px;padding:9px 12px;color:var(--muted)}.ao-search input{border:0;box-shadow:none;padding:0}.ao-head{display:flex;justify-content:space-between;align-items:end;gap:12px;flex-wrap:wrap;margin-bottom:14px}.ao-head h2{font-size:1.65rem;margin:0;color:#0b1f16}.ao-head p{margin:4px 0 0;color:var(--muted)}.ao-tool{border:1px solid var(--line);border-radius:8px;background:#fff;padding:9px 11px;font-weight:850;color:#102033}.ao-kpis{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.ao-kpi{border:1px solid var(--line);border-radius:8px;background:#fff;box-shadow:var(--shadow);padding:14px;display:flex;justify-content:space-between;gap:10px;min-height:112px}.ao-kpi small{display:block;text-transform:uppercase;font-size:.72rem;font-weight:900;color:#536171}.ao-kpi strong{display:block;font-size:1.45rem;color:#101828;margin-top:7px}.ao-kpi span{display:block;color:#079455;font-size:.78rem;font-weight:850;margin-top:5px}.ao-icon{width:48px;height:48px;border-radius:50%;display:grid;place-items:center;background:#e8f5ed;color:#087443}.ao-icon.blue{background:#e8f1ff;color:#175cd3}.ao-icon.orange{background:#fff1df;color:#c05600}.ao-icon.purple{background:#f1e9ff;color:#6941c6}.ao-icon.red{background:#fee4e2;color:#d92d20}.ao-grid{display:grid;grid-template-columns:1.35fr .9fr;gap:14px;margin-top:14px}.ao-row{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:14px}.ao-panel{border:1px solid var(--line);border-radius:8px;background:#fff;box-shadow:var(--shadow);padding:14px}.ao-panel-head{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:10px}.ao-panel-head h3{margin:0;color:#102033;font-size:1rem}.ao-panel-head a{color:#0f6b3c;text-decoration:none;font-weight:900;font-size:.82rem}.ao-chart{height:230px;display:flex;align-items:end;gap:16px;border-bottom:1px solid #d8dee6;padding:12px 8px 0}.ao-bar{flex:1;border-radius:8px 8px 0 0;background:linear-gradient(180deg,#0f6b3c,#9bd6ae);min-height:34px}.ao-map{height:230px;border-radius:8px;background:linear-gradient(135deg,#eef8f0,#c9e8d1);display:grid;place-items:center;position:relative;overflow:hidden;color:#0f6b3c;font-weight:950}.ao-map:before{content:"";position:absolute;inset:28px 48px;background:rgba(15,107,60,.16);clip-path:polygon(8% 47%,24% 20%,50% 12%,78% 22%,94% 46%,82% 72%,54% 88%,28% 82%);border:2px solid rgba(15,107,60,.2)}.ao-map span{position:relative}.ao-table{width:100%;border-collapse:collapse}.ao-table th,.ao-table td{padding:9px 8px;border-bottom:1px solid #edf1f4;text-align:left;font-size:.8rem}.ao-table th{font-size:.72rem;text-transform:uppercase;color:#667085}.ao-badge{display:inline-flex;border-radius:999px;padding:3px 7px;font-size:.7rem;font-weight:900}.ao-badge.ok{background:#dcfae6;color:#067647}.ao-badge.info{background:#dbeafe;color:#175cd3}.ao-badge.warn{background:#fef0c7;color:#b54708}.ao-badge.bad{background:#fee4e2;color:#b42318}.ao-list{display:grid;gap:9px}.ao-list-row{display:flex;justify-content:space-between;gap:10px;border-bottom:1px solid #eef2f4;padding-bottom:9px;font-size:.83rem}.ao-list-row small{display:block;color:var(--muted);margin-top:2px}.ao-actions{display:grid;gap:10px}.ao-action{border:1px solid var(--line);border-radius:8px;background:#fff;padding:13px;display:flex;gap:12px;align-items:center;color:inherit;text-decoration:none}.ao-action i{width:34px;height:34px;border-radius:50%;display:grid;place-items:center;background:#e8f5ed;color:#0f6b3c}@media(max-width:1100px){.ao-workspace,.ao-grid,.ao-row{grid-template-columns:1fr}.ao-rail{position:relative;top:auto;min-height:auto}.ao-nav,.ao-kpis{grid-template-columns:1fr 1fr}}@media(max-width:700px){.ao-nav,.ao-kpis{grid-template-columns:1fr}}',
]);
?>
<div class="ao-workspace">
  <aside class="ao-rail" aria-label="Admin operations navigation">
    <div class="ao-brand"><img src="<?= e(app_primary_logo_url()) ?>" alt="NATCODEV"><div><strong>NATCODEV</strong><small>Admin Operations</small></div></div>
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
        <div class="ao-map"><span>National Activity Map</span></div>
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
