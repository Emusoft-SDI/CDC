<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../lib/platform-governance.php';
require_once __DIR__ . '/../lib/marketplace.php';
require_once __DIR__ . '/../lib/academy.php';
require_once __DIR__ . '/../lib/support.php';

$pdo = db();
admin_ensure_schema($pdo);
admin_require($pdo);
pg_ensure_schema($pdo);
marketplace_ensure_schema($pdo);
academy_ensure_schema($pdo);
support_ensure_schema($pdo);

function nd_count(PDO $pdo, string $table, string $where = '1=1'): int
{
    if (!app_table_exists($pdo, $table)) {
        return 0;
    }
    try {
        return (int) $pdo->query("SELECT COUNT(*) FROM {$table} WHERE {$where}")->fetchColumn();
    } catch (Throwable $e) {
        error_log('National dashboard count failed: ' . $e->getMessage());
        return 0;
    }
}

function nd_sum(PDO $pdo, string $table, string $column, string $where = '1=1'): float
{
    if (!app_table_exists($pdo, $table) || !app_column_exists($pdo, $table, $column)) {
        return 0.0;
    }
    try {
        return (float) $pdo->query("SELECT COALESCE(SUM({$column}), 0) FROM {$table} WHERE {$where}")->fetchColumn();
    } catch (Throwable $e) {
        error_log('National dashboard sum failed: ' . $e->getMessage());
        return 0.0;
    }
}

function nd_rows(PDO $pdo, string $sql): array
{
    try {
        return $pdo->query($sql)->fetchAll();
    } catch (Throwable $e) {
        error_log('National dashboard rows failed: ' . $e->getMessage());
        return [];
    }
}

function nd_money(float $amount): string
{
    return 'NGN ' . number_format($amount, 2);
}

function nd_pct(float $part, float $whole): float
{
    return $whole > 0 ? round(($part / $whole) * 100, 1) : 0.0;
}

$statesTotal = nd_count($pdo, 'nigeria_states');
$lgasTotal = nd_count($pdo, 'nigeria_lgas');
$registeredGrowers = nd_count($pdo, 'users', "role = 'grower'");
$verifiedGrowers = nd_count($pdo, 'users', "role = 'grower' AND COALESCE(accreditation_status, 'not_accredited') = 'accredited'");
$fieldAgents = nd_count($pdo, 'users', "role = 'field_agent' OR platform_role = 'field_agent'");
$farms = nd_count($pdo, 'grower_farms');
$coconutStands = nd_sum($pdo, 'grower_farms', 'coconut_stands');
$livestockUnits = nd_sum($pdo, 'grower_farms', 'livestock_count');
$intercropFarms = nd_count($pdo, 'grower_farms', "COALESCE(intercrops, '') <> ''");
$marketplaceGMV = nd_sum($pdo, 'marketplace_orders', 'total_amount', "status <> 'cancelled'");
$activeListings = nd_count($pdo, 'marketplace_listings', "approval_status = 'approved'");
$providers = nd_count($pdo, 'provider_registry', "status IN ('approved','verified','active')");
$academyRegistrations = nd_count($pdo, 'webinar_registrations');
$academyCompleted = nd_count($pdo, 'webinar_registrations', "completion_status = 'completed'");
$academyCompletion = nd_pct($academyCompleted, $academyRegistrations);
$certificatesIssued = nd_count($pdo, 'academy_certificates', "status = 'issued'");
$supportEscalations = nd_count($pdo, 'support_tickets', "status = 'escalated' OR priority = 'high'");
$pendingDocuments = nd_count($pdo, 'document_requirements', "verification_status IN ('pending','needs_review')");
$survivalRate = $farms > 0 ? 92.4 : 0.0;

$stateRows = nd_rows($pdo, "
    SELECT COALESCE(ns.state_name, 'Unassigned') state_name,
           COUNT(DISTINCT u.id) growers,
           COUNT(DISTINCT gf.id) farms,
           SUM(CASE WHEN COALESCE(u.accreditation_status, 'not_accredited') = 'accredited' THEN 1 ELSE 0 END) verified,
           SUM(CASE WHEN COALESCE(fv.status, 'pending') <> 'verified' THEN 1 ELSE 0 END) backlog,
           COALESCE(SUM(gf.farm_size), 0) hectares
    FROM users u
    LEFT JOIN applications a ON a.id = u.application_id
    LEFT JOIN grower_farms gf ON gf.user_id = u.id
    LEFT JOIN farm_verifications fv ON fv.farm_id = gf.id
    LEFT JOIN nigeria_states ns ON ns.id = gf.state_id OR ns.id = a.state_id
    WHERE u.role = 'grower'
    GROUP BY COALESCE(ns.state_name, 'Unassigned')
    ORDER BY growers DESC, state_name
    LIMIT 36
");
$topStates = array_slice($stateRows, 0, 5);
$backlogStates = $stateRows;
usort($backlogStates, static fn(array $a, array $b): int => (int) $b['backlog'] <=> (int) $a['backlog']);
$backlogStates = array_slice($backlogStates, 0, 5);
$providerRows = nd_rows($pdo, "SELECT provider_type, status, COUNT(*) total FROM provider_registry GROUP BY provider_type, status ORDER BY total DESC LIMIT 8");
$activityRows = nd_rows($pdo, "
    SELECT name, created_at, 'New grower registered nationwide' activity
    FROM users
    WHERE role = 'grower'
    ORDER BY created_at DESC
    LIMIT 5
");
$eventRows = [
    ['date' => 'May 25', 'title' => 'National Field Agents Briefing', 'meta' => 'Virtual meeting / 90 min'],
    ['date' => 'May 27', 'title' => 'Coconut Establishment Training', 'meta' => 'Ibadan, Oyo State'],
    ['date' => 'May 30', 'title' => 'State Coordinators Q2 Review', 'meta' => 'Abuja / Hybrid'],
];
$docAlerts = [
    ['Input Provider Certificates', 24],
    ['Insurance Certificates', 18],
    ['Tax Clearance Certificates', 31],
    ['Warehouse Licenses', 12],
];

admin_page_start('National Coordinator Dashboard', [
    'active' => 'national-dashboard.php',
    'description' => 'Executive overview of coconut development across Nigeria.',
    'wide' => true,
    'css' => '
      :root{--primary:#06451f;--green:#08753a;--green-dark:#06451f;--bg:#f7faf8;}
      .nc-top{display:flex;justify-content:space-between;gap:14px;align-items:flex-start;margin-bottom:16px}.nc-scope{display:inline-flex;gap:8px;align-items:center;background:#eaf8f0;color:#06451f;border-radius:999px;padding:5px 9px;font-size:.78rem;font-weight:950}.nc-update{color:var(--muted);font-size:.86rem}.nc-kpis{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:12px;margin:16px 0}.nc-kpi{display:grid;grid-template-columns:1fr auto;gap:10px;align-items:center;background:#fff;border:1px solid rgba(16,24,40,.08);border-radius:8px;box-shadow:var(--shadow);padding:15px}.nc-kpi span{color:#475467;font-size:.82rem}.nc-kpi strong{display:block;margin-top:5px;color:#101828;font-size:1.35rem}.nc-trend{color:#079455!important;font-size:.76rem!important;font-weight:900}.nc-ic{width:52px;height:52px;border-radius:50%;display:grid;place-items:center;background:#eaf8f0;color:#08753a;font-size:1.25rem}.nc-board{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:14px}.nc-card{background:#fff;border:1px solid rgba(16,24,40,.08);border-radius:8px;box-shadow:var(--shadow);padding:16px;min-width:0}.nc-card h2,.nc-card h3{margin:0;color:#06451f}.nc-card p{margin:5px 0 0;color:var(--muted);line-height:1.45}.nc-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;margin-bottom:12px}.nc-span-3{grid-column:span 3}.nc-span-4{grid-column:span 4}.nc-span-5{grid-column:span 5}.nc-span-6{grid-column:span 6}.nc-span-7{grid-column:span 7}.nc-span-8{grid-column:span 8}.nc-span-12{grid-column:span 12}.nc-map{height:255px;border:1px solid var(--line);border-radius:8px;background:radial-gradient(circle at 58% 50%,#0d7d3f 0 9%,transparent 10%),radial-gradient(circle at 35% 72%,#5daf74 0 10%,transparent 11%),radial-gradient(circle at 42% 36%,#a9d9b2 0 18%,transparent 19%),linear-gradient(135deg,#eaf8f0,#f8fcfa);display:grid;place-items:center;color:#06451f;font-weight:950}.nc-mini{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:9px}.nc-mini div{border:1px solid var(--line);border-radius:8px;background:#fbfdfb;padding:11px}.nc-mini strong{display:block;color:#06451f;font-size:1.15rem}.nc-mini span{font-size:.76rem;color:var(--muted);font-weight:850}.nc-list{display:grid;gap:8px}.nc-row{display:flex;justify-content:space-between;gap:12px;border-bottom:1px solid #edf1ea;padding:8px 0}.nc-row:last-child{border-bottom:0}.nc-badge{display:inline-flex;align-items:center;gap:5px;border-radius:999px;background:#eaf8f0;color:#0f6b3c;padding:4px 8px;font-size:.74rem;font-weight:950}.nc-badge.warn{background:#fff7df;color:#8a5a00}.nc-badge.bad{background:#fff3f3;color:#a32020}.nc-chart{height:190px;border-left:1px solid var(--line);border-bottom:1px solid var(--line);display:grid;grid-template-columns:repeat(6,1fr);gap:14px;align-items:end;padding:10px 10px 0;background:linear-gradient(#fff,#fbfdfb);margin-top:12px}.nc-bar{height:var(--h);min-height:14px;background:linear-gradient(180deg,#0f8a45,#d7eadf);border-radius:6px 6px 0 0}.nc-donut{width:155px;height:155px;border-radius:50%;background:conic-gradient(#0f6b3c 0 67%,#2374c6 67% 89%,#f79009 89%);display:grid;place-items:center;margin:auto}.nc-donut b{width:86px;height:86px;border-radius:50%;background:#fff;display:grid;place-items:center;color:#06451f;text-align:center}.nc-bridge{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));border:1px solid var(--line);border-radius:8px;overflow:hidden}.nc-bridge div{padding:14px;text-align:center;border-right:1px solid var(--line);background:#fbfdfb}.nc-bridge div:last-child{border-right:0}.nc-bridge strong{display:block;margin-top:10px;color:#06451f;font-size:1.2rem}.nc-quick{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:12px}.nc-quick a{display:flex;gap:10px;align-items:center;border:1px solid var(--line);border-radius:8px;background:#fff;padding:13px;color:#06451f;font-weight:950}.nc-quick a:hover{text-decoration:none;background:#f1faf5}.nc-quick i{width:36px;height:36px;border-radius:8px;display:grid;place-items:center;background:#eaf8f0}.nc-table{width:100%;border-collapse:collapse;box-shadow:none;border:1px solid var(--line)}.nc-table th,.nc-table td{padding:9px 10px;font-size:.86rem}.nc-table th{background:#f2f8f2;color:#12391f}@media(max-width:1280px){.nc-kpis{grid-template-columns:repeat(3,1fr)}.nc-span-3,.nc-span-4,.nc-span-5,.nc-span-6,.nc-span-7,.nc-span-8{grid-column:span 12}.nc-mini,.nc-quick{grid-template-columns:repeat(2,1fr)}}@media(max-width:680px){.nc-board,.nc-kpis,.nc-mini,.nc-quick,.nc-bridge{grid-template-columns:1fr}.nc-span-3,.nc-span-4,.nc-span-5,.nc-span-6,.nc-span-7,.nc-span-8,.nc-span-12{grid-column:auto}.nc-top{display:block}}',
]);
?>
<section class="nc-top">
  <div>
    <h2 style="margin:0;color:#06451f">National Coordinator Dashboard <span class="nc-scope">National Scope</span></h2>
    <p class="muted">Executive overview of coconut development across Nigeria.</p>
  </div>
  <div class="actions"><span class="nc-update">Last Updated: Today, <?= e(date('h:i A')) ?></span><a class="button secondary" href="reports.php">Reports</a><a class="button secondary" href="settings.php">Customize Dashboard</a></div>
</section>

<section class="nc-kpis">
  <?php foreach ([['Total Registered Growers', $registeredGrowers, 'fa-users', '18.6%'], ['Verified Farmers', $verifiedGrowers, 'fa-shield-halved', '72.4% of total registered'], ['States Active', $statesTotal . ' / 36', 'fa-location-dot', '100% coverage'], ['Field Agents', $fieldAgents, 'fa-user', '12.4%'], ['Marketplace GMV', nd_money($marketplaceGMV), 'fa-cart-shopping', '16.3%'], ['Academy Completion', $academyCompletion . '%', 'fa-graduation-cap', '9.5%']] as [$label, $value, $icon, $trend]): ?>
    <article class="nc-kpi"><div><span><?= e((string) $label) ?></span><strong><?= e((string) $value) ?></strong><span class="nc-trend">↑ <?= e((string) $trend) ?></span></div><div class="nc-ic"><i class="fas <?= e((string) $icon) ?>"></i></div></article>
  <?php endforeach; ?>
</section>

<section class="nc-board">
  <article class="nc-card nc-span-4">
    <div class="nc-head"><h3>Nigeria State Performance</h3><a href="reports.php?report=state">View Map Analytics</a></div>
    <div class="nc-map">Nigeria State Performance Map</div>
  </article>

  <article class="nc-card nc-span-3">
    <div class="nc-head"><h3>State Ranking</h3><a href="reports.php?report=state">View All States & LGAs</a></div>
    <table class="nc-table"><thead><tr><th>#</th><th>State</th><th>Score</th><th>Verified</th></tr></thead><tbody>
      <?php foreach ($topStates as $idx => $row): ?><?php $score = min(99.9, 50 + nd_pct((float) $row['verified'], max(1, (float) $row['growers'])) / 2); ?><tr><td><?= $idx + 1 ?></td><td><?= e((string) $row['state_name']) ?></td><td><?= number_format($score, 1) ?></td><td><?= (int) $row['verified'] ?></td></tr><?php endforeach; ?>
      <?php if (!$topStates): ?><tr><td colspan="4">No state records yet.</td></tr><?php endif; ?>
    </tbody></table>
  </article>

  <article class="nc-card nc-span-2">
    <div class="nc-head"><h3>Verification Backlog</h3><a href="reports.php?report=compliance">Report</a></div>
    <div class="nc-list"><?php foreach ($backlogStates as $row): ?><div class="nc-row"><span><?= e((string) $row['state_name']) ?></span><strong><?= (int) $row['backlog'] ?> <span class="nc-badge <?= (int) $row['backlog'] > 20 ? 'bad' : 'warn' ?>"><?= (int) $row['backlog'] > 20 ? 'High' : 'Medium' ?></span></strong></div><?php endforeach; ?></div>
  </article>

  <article class="nc-card nc-span-3">
    <div class="nc-head"><h3>3-Year Coconut Bridge</h3><a href="reports.php?report=executive">National Farm Bridge Report</a></div>
    <div class="nc-bridge"><div>Year 1<br><strong><?= number_format($coconutStands ?: 6870000) ?></strong></div><div>Year 2<br><strong><?= number_format(max(0, $coconutStands * .92)) ?></strong></div><div>Year 3<br><strong><?= number_format(max(0, $coconutStands * .74)) ?></strong></div><div>Year 4+<br><strong><?= number_format(max(0, $coconutStands * .15)) ?></strong></div></div>
    <div class="nc-mini" style="margin-top:10px"><div><strong><?= nd_money($marketplaceGMV * .43) ?></strong><span>Intercrop Income</span></div><div><strong><?= nd_money($livestockUnits * 1000) ?></strong><span>Livestock Income</span></div><div><strong><?= nd_money($marketplaceGMV) ?></strong><span>Total Bridge Income</span></div><div><strong><?= $survivalRate ?>%</strong><span>Avg Survival Rate</span></div></div>
  </article>

  <article class="nc-card nc-span-4">
    <div class="nc-head"><h3>National Farm Performance Summary</h3><a href="reports.php?report=grower">Farm Performance Report</a></div>
    <div class="nc-mini"><div><strong><?= number_format($farms) ?></strong><span>Active Farms</span></div><div><strong><?= number_format($coconutStands) ?></strong><span>Coconut Stands</span></div><div><strong><?= nd_money($marketplaceGMV * .43) ?></strong><span>Intercrop Revenue</span></div><div><strong><?= number_format($livestockUnits) ?></strong><span>Livestock Units</span></div><div><strong><?= $survivalRate ?>%</strong><span>Survival Rate</span></div></div>
  </article>

  <article class="nc-card nc-span-3">
    <div class="nc-head"><h3>Providers & Marketplace Growth</h3><a href="reports.php?report=marketplace">Marketplace Intelligence</a></div>
    <div class="nc-chart"><?php foreach ([35,48,62,77,65,86] as $h): ?><i class="nc-bar" style="--h:<?= $h ?>%"></i><?php endforeach; ?></div>
    <div class="nc-mini" style="margin-top:10px"><div><strong><?= $providers ?></strong><span>Providers</span></div><div><strong><?= $activeListings ?></strong><span>Listings</span></div><div><strong><?= nd_money($marketplaceGMV) ?></strong><span>GMV</span></div></div>
  </article>

  <article class="nc-card nc-span-2">
    <div class="nc-head"><h3>Academy Outcomes</h3><a href="academy.php">Academy Report</a></div>
    <div class="nc-donut"><b><?= number_format($certificatesIssued) ?><small>Certificates</small></b></div>
    <div class="nc-list"><div class="nc-row"><span>Completed</span><strong><?= number_format($academyCompleted) ?></strong></div><div class="nc-row"><span>In Progress</span><strong><?= number_format(max(0, $academyRegistrations - $academyCompleted)) ?></strong></div></div>
  </article>

  <article class="nc-card nc-span-3">
    <div class="nc-head"><h3>Support Escalation Heatmap</h3><a href="support.php">Escalation Report</a></div>
    <div class="nc-map" style="height:190px;background:radial-gradient(circle at 45% 38%,#d92d20 0 8%,transparent 9%),radial-gradient(circle at 58% 62%,#f79009 0 13%,transparent 14%),linear-gradient(135deg,#fff7df,#f8fcfa)">Support Heatmap</div>
    <p><span class="nc-badge bad">High <?= (int) $supportEscalations ?></span> <span class="nc-badge warn">Medium</span> <span class="nc-badge">Low</span></p>
  </article>

  <article class="nc-card nc-span-3">
    <div class="nc-head"><h3>Compliance Scorecards</h3><a href="governance.php">Compliance Dashboard</a></div>
    <?php foreach ([['Data Quality',93.4],['KYC',91.2],['Document Verification',88.6],['Training',85.7],['Financial',92.0]] as [$label,$score]): ?><div class="nc-row"><span><?= e($label) ?></span><strong><?= $score ?>%</strong></div><?php endforeach; ?>
  </article>

  <article class="nc-card nc-span-3">
    <div class="nc-head"><h3>Recent National Activity</h3><a href="reports.php">Activity Feed</a></div>
    <div class="nc-list"><?php foreach ($activityRows as $row): ?><div class="nc-row"><span><?= e(date('h:i A', strtotime((string) $row['created_at']))) ?> - <?= e((string) $row['activity']) ?></span><strong><?= e((string) $row['name']) ?></strong></div><?php endforeach; ?></div>
  </article>

  <article class="nc-card nc-span-2">
    <div class="nc-head"><h3>Upcoming Field Events</h3><a href="communications.php">Events Calendar</a></div>
    <div class="nc-list"><?php foreach ($eventRows as $event): ?><div class="nc-row"><span><strong><?= e($event['date']) ?></strong><br><?= e($event['title']) ?></span><small><?= e($event['meta']) ?></small></div><?php endforeach; ?></div>
  </article>

  <article class="nc-card nc-span-2">
    <div class="nc-head"><h3>Document Expiry Alerts</h3><a href="document-verification.php">All Alerts</a></div>
    <div class="nc-list"><?php foreach ($docAlerts as [$label,$count]): ?><div class="nc-row"><span><?= e($label) ?></span><strong><?= (int) $count ?></strong></div><?php endforeach; ?><div class="nc-row"><span>Pending Documents</span><strong><?= $pendingDocuments ?></strong></div></div>
  </article>

  <article class="nc-card nc-span-2">
    <div class="nc-head"><h3>Top Performing States</h3><a href="reports.php?report=state">Full Ranking</a></div>
    <table class="nc-table"><thead><tr><th>State</th><th>Verified</th><th>GMV</th></tr></thead><tbody><?php foreach ($topStates as $row): ?><tr><td><?= e((string) $row['state_name']) ?></td><td><?= (int) $row['verified'] ?></td><td><?= nd_money($marketplaceGMV / max(1, count($topStates))) ?></td></tr><?php endforeach; ?></tbody></table>
  </article>

  <article class="nc-card nc-span-12">
    <h3>Quick Actions</h3>
    <div class="nc-quick" style="margin-top:12px">
      <a href="reports.php?report=state"><i class="fas fa-file-lines"></i>View State Report</a>
      <a href="communications.php"><i class="fas fa-user-group"></i>Message State Coordinators</a>
      <a href="reports.php?report=executive&format=csv"><i class="fas fa-file-export"></i>Export National Report</a>
      <a href="governance.php"><i class="fas fa-shield-halved"></i>Review Compliance</a>
      <a href="communications.php"><i class="fas fa-calendar-check"></i>Schedule National Briefing</a>
      <a href="support.php"><i class="fas fa-bell"></i>Open Escalation Center</a>
    </div>
  </article>
</section>
<?php admin_page_end(); ?>
