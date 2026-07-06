<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../lib/platform-governance.php';
require_once __DIR__ . '/../lib/field-management.php';
require_once __DIR__ . '/../lib/marketplace.php';
require_once __DIR__ . '/../lib/academy.php';
require_once __DIR__ . '/../lib/support.php';

$pdo = db();
admin_ensure_schema($pdo);
pg_ensure_schema($pdo);
fm_ensure_schema($pdo);
marketplace_ensure_schema($pdo);
academy_ensure_schema($pdo);
support_ensure_schema($pdo);
admin_require($pdo);

$user = current_user($pdo) ?: [];
$state = pg_scope_state($pdo);
if ($state === '') {
    $state = trim((string) ($_GET['state'] ?? $user['location'] ?? ''));
}
if ($state === '') {
    $state = 'Lagos State';
}
$stateMissing = trim((string) pg_scope_state($pdo)) === '' && trim((string) ($user['location'] ?? '')) === '' && !isset($_GET['state']);
$message = '';
$error = '';

function sc_scalar(PDO $pdo, string $sql, array $params = []): int
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

function sc_float(PDO $pdo, string $sql, array $params = []): float
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (float) $stmt->fetchColumn();
}

function sc_rows(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function sc_pct(float $value, float $total): float
{
    return $total > 0 ? round(($value / $total) * 100, 1) : 0.0;
}

function sc_money(float $amount): string
{
    return pg_currency($amount);
}

function sc_day(?string $date): string
{
    if (!$date) {
        return '-';
    }
    $time = strtotime($date);
    return $time ? date('M j', $time) : '-';
}

function sc_time(?string $date): string
{
    if (!$date) {
        return '-';
    }
    $time = strtotime($date);
    return $time ? date('g:i A', $time) : '-';
}

$farmCoconutExpr = app_column_exists($pdo, 'grower_farms', 'coconut_stands') ? 'COALESCE(SUM(gf.coconut_stands), 0)' : '0';
$farmIntercropExpr = app_column_exists($pdo, 'grower_farms', 'intercrop_revenue') ? 'COALESCE(SUM(gf.intercrop_revenue), 0)' : '0';
$farmLivestockExpr = app_column_exists($pdo, 'grower_farms', 'livestock_count') ? 'COALESCE(SUM(gf.livestock_count), 0)' : '0';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid security token.';
    } else {
        try {
            $action = (string) ($_POST['action'] ?? '');
            if ($action === 'update_accreditation') {
                $farmerId = (int) ($_POST['farmer_id'] ?? 0);
                $status = in_array((string) ($_POST['accreditation_status'] ?? ''), ['not_accredited', 'pending', 'accredited', 'suspended'], true)
                    ? (string) $_POST['accreditation_status']
                    : 'pending';
                $program = trim((string) ($_POST['accreditation_program'] ?? ''));
                $pdo->prepare("
                    UPDATE users
                    SET accreditation_status = ?, accreditation_program = ?, accredited_at = IF(? = 'accredited', COALESCE(accredited_at, NOW()), accredited_at)
                    WHERE id = ? AND role = 'grower'
                ")->execute([$status, $program ?: null, $status, $farmerId]);
                $message = 'Farmer accreditation updated.';
            } elseif ($action === 'record_budget') {
                $pdo->prepare("
                    INSERT INTO state_budget_records (state_name, budget_line, amount_budgeted, amount_spent, fiscal_period, notes, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ")->execute([
                    $state,
                    trim((string) ($_POST['budget_line'] ?? '')),
                    (float) ($_POST['amount_budgeted'] ?? 0),
                    (float) ($_POST['amount_spent'] ?? 0),
                    trim((string) ($_POST['fiscal_period'] ?? '')),
                    trim((string) ($_POST['notes'] ?? '')),
                    (int) ($user['id'] ?? 0),
                ]);
                $message = 'State budget line recorded.';
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$stateLike = '%' . $state . '%';
$stateUserWhere = "(ns.state_name = ? OR app_state.state_name = ? OR farm_summary.state_name = ? OR a.location LIKE ? OR u.location LIKE ?)";
$stateUserParams = [$state, $state, $state, $stateLike, $stateLike];

$statsStmt = $pdo->prepare("
    SELECT
      COUNT(DISTINCT u.id) growers,
      SUM(CASE WHEN COALESCE(u.accreditation_status, 'not_accredited') = 'accredited' THEN 1 ELSE 0 END) accredited,
      SUM(CASE WHEN COALESCE(farm_summary.verification_rank, 0) >= 2 THEN 1 ELSE 0 END) verified_growers,
      COUNT(DISTINCT gf.id) farms,
      COALESCE(SUM(gf.farm_size), 0) hectares,
      {$farmCoconutExpr} coconut_stands,
      {$farmIntercropExpr} intercrop_revenue,
      {$farmLivestockExpr} livestock_units
    FROM users u
    LEFT JOIN applications a ON a.id = u.application_id
    LEFT JOIN nigeria_states app_state ON app_state.id = a.state_id
    LEFT JOIN grower_farms gf ON gf.user_id = u.id
    LEFT JOIN nigeria_states ns ON ns.id = gf.state_id
    LEFT JOIN (
        SELECT gf2.user_id, MAX(ns2.state_name) state_name,
               MAX(CASE WHEN fv.status = 'verified' THEN 2 WHEN fv.status = 'rejected' THEN 1 ELSE 0 END) verification_rank
        FROM grower_farms gf2
        LEFT JOIN farm_verifications fv ON fv.farm_id = gf2.id
        LEFT JOIN nigeria_states ns2 ON ns2.id = gf2.state_id
        GROUP BY gf2.user_id
    ) farm_summary ON farm_summary.user_id = u.id
    WHERE u.role = 'grower' AND {$stateUserWhere}
");
$statsStmt->execute($stateUserParams);
$stats = $statsStmt->fetch() ?: [];

$registeredGrowers = (int) ($stats['growers'] ?? 0);
$verifiedGrowers = (int) ($stats['verified_growers'] ?? 0);
$verificationRate = sc_pct($verifiedGrowers, max(1, $registeredGrowers));
$activeLgas = sc_scalar($pdo, "SELECT COUNT(DISTINCT COALESCE(app_lga.lga_name, nl.lga_name, u.location)) FROM users u LEFT JOIN applications a ON a.id = u.application_id LEFT JOIN nigeria_lgas app_lga ON app_lga.id = a.lga_id LEFT JOIN grower_farms gf ON gf.user_id = u.id LEFT JOIN nigeria_states ns ON ns.id = gf.state_id LEFT JOIN nigeria_lgas nl ON nl.id = gf.lga_id WHERE u.role = 'grower' AND (ns.state_name = ? OR a.location LIKE ? OR u.location LIKE ?)", [$state, $stateLike, $stateLike]);
$activeLgas = max(1, $activeLgas);
$fieldAgents = sc_scalar($pdo, "SELECT COUNT(*) FROM users u LEFT JOIN staff_profiles sp ON sp.user_id = u.id WHERE u.role = 'field_agent' AND (sp.state = ? OR u.location LIKE ?)", [$state, $stateLike]);
$pendingVerifications = sc_scalar($pdo, "SELECT COUNT(*) FROM farm_verifications fv JOIN grower_farms gf ON gf.id = fv.farm_id JOIN users u ON u.id = gf.user_id LEFT JOIN applications a ON a.id = u.application_id LEFT JOIN nigeria_states ns ON ns.id = gf.state_id WHERE fv.status IN ('pending','needs_review','submitted') AND (ns.state_name = ? OR a.location LIKE ? OR u.location LIKE ?)", [$state, $stateLike, $stateLike]);
$stateScore = min(99, (int) round(($verificationRate * 0.55) + (min(100, $activeLgas * 5) * 0.15) + (min(100, $fieldAgents * 2) * 0.15) + 15));
$survivalRate = min(98.5, 82 + ($stateScore / 8));

$lgaRows = sc_rows($pdo, "
    SELECT COALESCE(app_lga.lga_name, nl.lga_name, 'Unassigned LGA') lga,
           COUNT(DISTINCT u.id) growers,
           SUM(CASE WHEN COALESCE(fv.status, 'pending') = 'verified' THEN 1 ELSE 0 END) verified,
           COALESCE(SUM(gf.farm_size), 0) hectares
    FROM users u
    LEFT JOIN applications a ON a.id = u.application_id
    LEFT JOIN nigeria_lgas app_lga ON app_lga.id = a.lga_id
    LEFT JOIN grower_farms gf ON gf.user_id = u.id
    LEFT JOIN farm_verifications fv ON fv.farm_id = gf.id
    LEFT JOIN nigeria_states ns ON ns.id = gf.state_id
    LEFT JOIN nigeria_lgas nl ON nl.id = gf.lga_id
    WHERE u.role = 'grower' AND (ns.state_name = ? OR a.location LIKE ? OR u.location LIKE ?)
    GROUP BY COALESCE(app_lga.lga_name, nl.lga_name, 'Unassigned LGA')
    ORDER BY growers DESC
    LIMIT 6
", [$state, $stateLike, $stateLike]);

$verificationRows = sc_rows($pdo, "
    SELECT fv.id, u.name grower_name, COALESCE(app_lga.lga_name, nl.lga_name, 'Unassigned') lga, fv.created_at submitted_at, fv.status, ft.priority
    FROM farm_verifications fv
    JOIN grower_farms gf ON gf.id = fv.farm_id
    JOIN users u ON u.id = gf.user_id
    LEFT JOIN applications a ON a.id = u.application_id
    LEFT JOIN nigeria_lgas app_lga ON app_lga.id = a.lga_id
    LEFT JOIN field_tasks ft ON ft.farm_id = gf.id
    LEFT JOIN nigeria_states ns ON ns.id = gf.state_id
    LEFT JOIN nigeria_lgas nl ON nl.id = gf.lga_id
    WHERE fv.status NOT IN ('verified','rejected') AND (ns.state_name = ? OR a.location LIKE ? OR u.location LIKE ?)
    ORDER BY FIELD(COALESCE(ft.priority, 'normal'), 'urgent','high','normal','low'), fv.created_at DESC
    LIMIT 5
", [$state, $stateLike, $stateLike]);

$agentRows = sc_rows($pdo, "
    SELECT u.id, u.name, COALESCE(sp.lga, 'Multi-LGA') lga,
           COUNT(DISTINCT ft.id) tasks,
           SUM(CASE WHEN ft.status IN ('completed','verified','done') THEN 1 ELSE 0 END) completed
    FROM users u
    LEFT JOIN staff_profiles sp ON sp.user_id = u.id
    LEFT JOIN field_tasks ft ON ft.assigned_to = u.id
    WHERE u.role = 'field_agent' AND (sp.state = ? OR u.location LIKE ?)
    GROUP BY u.id, u.name, sp.lga
    ORDER BY tasks DESC, u.name
    LIMIT 5
", [$state, $stateLike]);

$marketplaceProviders = sc_scalar($pdo, "SELECT COUNT(*) FROM provider_registry WHERE (states_served LIKE ? OR business_address LIKE ?) AND status IN ('verified','active','approved')", [$stateLike, $stateLike]);
$activeListings = sc_scalar($pdo, "SELECT COUNT(*) FROM marketplace_listings ml JOIN marketplace_sellers ms ON ms.id = ml.seller_id WHERE ml.approval_status = 'approved' AND ml.availability_status = 'available' AND (ml.location_label LIKE ? OR ms.location_label LIKE ? OR ms.coverage_area LIKE ?)", [$stateLike, $stateLike, $stateLike]);
$ordersThisMonth = sc_scalar($pdo, "SELECT COUNT(*) FROM marketplace_orders WHERE created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");
$gmvThisMonth = sc_float($pdo, "SELECT COALESCE(SUM(total_amount), 0) FROM marketplace_orders WHERE created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");

$academyRows = sc_rows($pdo, "
    SELECT COALESCE(app_lga.lga_name, 'Unassigned LGA') lga,
           COUNT(wr.id) enrolled,
           SUM(CASE WHEN wr.completion_status = 'completed' THEN 1 ELSE 0 END) completed
    FROM webinar_registrations wr
    JOIN users u ON u.id = wr.user_id
    LEFT JOIN applications a ON a.id = u.application_id
    LEFT JOIN nigeria_lgas app_lga ON app_lga.id = a.lga_id
    WHERE u.location LIKE ? OR a.location LIKE ?
    GROUP BY COALESCE(app_lga.lga_name, 'Unassigned LGA')
    ORDER BY enrolled DESC
    LIMIT 5
", [$stateLike, $stateLike]);
$academyEnrolled = array_sum(array_map(static fn(array $row): int => (int) $row['enrolled'], $academyRows));
$academyCompleted = array_sum(array_map(static fn(array $row): int => (int) $row['completed'], $academyRows));

$supportRows = sc_rows($pdo, "
    SELECT ticket_ref, category, requester_name, priority, created_at
    FROM support_tickets
    WHERE status IN ('open','in_progress','waiting_on_user','escalated') AND (linked_record_ref LIKE ? OR requester_role IN ('state_coordinator','field_agent','grower'))
    ORDER BY FIELD(priority, 'high','medium','low'), created_at DESC
    LIMIT 5
", [$stateLike]);
$supportEscalations = sc_scalar($pdo, "SELECT COUNT(*) FROM support_tickets WHERE status = 'escalated' AND (linked_record_ref LIKE ? OR requester_role IN ('state_coordinator','field_agent','grower'))", [$stateLike]);

$budget = sc_rows($pdo, "SELECT COALESCE(SUM(amount_budgeted), 0) budgeted, COALESCE(SUM(amount_spent), 0) spent FROM state_budget_records WHERE state_name = ?", [$state])[0] ?? ['budgeted' => 0, 'spent' => 0];
$walletAvailable = max(0, (float) ($budget['budgeted'] ?? 0) - (float) ($budget['spent'] ?? 0));
$resourceAlerts = sc_rows($pdo, "SELECT resource_name, quantity_available, reorder_level FROM state_resource_inventory WHERE state_name = ? ORDER BY (quantity_available <= reorder_level) DESC, updated_at DESC LIMIT 3", [$state]);
$caseRows = sc_rows($pdo, "
    SELECT ac.category, COUNT(*) total
    FROM agronomy_cases ac
    JOIN users u ON u.id = ac.grower_id
    LEFT JOIN grower_farms gf ON gf.id = ac.farm_id
    LEFT JOIN nigeria_states ns ON ns.id = gf.state_id
    WHERE ac.status NOT IN ('resolved','closed') AND (ns.state_name = ? OR u.location LIKE ?)
    GROUP BY ac.category
    ORDER BY total DESC
    LIMIT 4
", [$state, $stateLike]);
$farmers = sc_rows($pdo, "
    SELECT u.id, u.name, u.email, u.created_at, u.account_status, u.accreditation_status, u.accreditation_program,
           a.app_ref, COALESCE(app_lga.lga_name, nl.lga_name, 'Unassigned') lga,
           CASE WHEN COALESCE(fv.status, 'pending') = 'verified' THEN 'verified' WHEN COALESCE(fv.status, 'pending') = 'rejected' THEN 'rejected' ELSE 'pending' END verification_status
    FROM users u
    LEFT JOIN applications a ON a.id = u.application_id
    LEFT JOIN nigeria_lgas app_lga ON app_lga.id = a.lga_id
    LEFT JOIN grower_farms gf ON gf.user_id = u.id
    LEFT JOIN farm_verifications fv ON fv.farm_id = gf.id
    LEFT JOIN nigeria_states ns ON ns.id = gf.state_id
    LEFT JOIN nigeria_lgas nl ON nl.id = gf.lga_id
    WHERE u.role = 'grower' AND (ns.state_name = ? OR a.location LIKE ? OR u.location LIKE ?)
    ORDER BY u.created_at DESC
    LIMIT 6
", [$state, $stateLike, $stateLike]);

admin_page_start('State Coordinator Dashboard', [
    'active' => 'state-dashboard.php',
    'description' => 'Monitor and coordinate all coconut development activities across your state and LGAs.',
    'wide' => true,
    'css' => '
      .sc-shell{display:grid;gap:16px}.sc-top{display:flex;justify-content:space-between;align-items:end;gap:12px;flex-wrap:wrap}.sc-title h2{margin:0;color:#0b1f16;font-size:1.65rem}.sc-title p{margin:4px 0 0;color:var(--muted)}.sc-scope{display:inline-flex;align-items:center;border:1px solid #bfe8cf;background:#eaf8f0;color:#0f6b3c;border-radius:999px;padding:3px 8px;font-size:.76rem;font-weight:900}.sc-date{border:1px solid var(--line);border-radius:8px;padding:10px 12px;background:#fff;font-weight:850}.sc-kpis{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:12px}.sc-kpi{border:1px solid var(--line);border-radius:8px;background:#fff;box-shadow:var(--shadow);padding:14px;display:flex;justify-content:space-between;gap:10px;min-height:118px}.sc-kpi small{display:block;text-transform:uppercase;color:#667085;font-size:.72rem;font-weight:900}.sc-kpi strong{display:block;color:#101828;font-size:1.55rem;margin-top:4px}.sc-kpi span{display:block;color:#079455;font-size:.78rem;font-weight:850;margin-top:4px}.sc-kpi a{display:inline-flex;margin-top:10px;color:#0f6b3c;text-decoration:none;font-weight:900;font-size:.82rem}.sc-icon{width:48px;height:48px;border-radius:50%;display:grid;place-items:center;background:#e8f5ed;color:#0f6b3c;font-size:1.15rem}.sc-icon.blue{background:#e8f1ff;color:#175cd3}.sc-icon.orange{background:#fff3e5;color:#c05600}.sc-icon.purple{background:#f1e9ff;color:#6941c6}.sc-grid{display:grid;grid-template-columns:1.35fr 1fr 1fr 1fr;gap:14px}.sc-row{display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:14px}.sc-row2{display:grid;grid-template-columns:1fr 1fr .8fr 1fr 1fr;gap:14px}.sc-panel{border:1px solid var(--line);border-radius:8px;background:#fff;box-shadow:var(--shadow);padding:14px}.sc-head{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:10px}.sc-head h3{margin:0;color:#102033;font-size:1rem}.sc-head a{color:#0f6b3c;text-decoration:none;font-weight:900;font-size:.82rem}.sc-map{min-height:210px;border-radius:8px;background:linear-gradient(135deg,#eef8f0,#c9e8d1);display:grid;place-items:center;position:relative;overflow:hidden;color:#0f6b3c;font-weight:950}.sc-map:before{content:"";position:absolute;inset:26px 38px;background:rgba(15,107,60,.16);clip-path:polygon(8% 47%,24% 20%,50% 12%,78% 22%,94% 46%,82% 72%,54% 88%,28% 82%);border:2px solid rgba(15,107,60,.2)}.sc-map span{position:relative}.sc-table{width:100%;border-collapse:collapse}.sc-table th,.sc-table td{padding:9px 8px;border-bottom:1px solid #edf1f4;text-align:left;font-size:.8rem;vertical-align:top}.sc-table th{font-size:.72rem;text-transform:uppercase;color:#667085}.sc-table strong{color:#102033}.sc-badge{display:inline-flex;border-radius:999px;padding:3px 7px;font-size:.7rem;font-weight:900}.sc-badge.ok{background:#dcfae6;color:#067647}.sc-badge.info{background:#dbeafe;color:#175cd3}.sc-badge.warn{background:#fef0c7;color:#b54708}.sc-badge.bad{background:#fee4e2;color:#b42318}.sc-metric-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}.sc-mini{border:1px solid #edf1f4;border-radius:8px;padding:12px;background:#fff}.sc-mini i{width:34px;height:34px;border-radius:50%;display:grid;place-items:center;background:#e8f5ed;color:#0f6b3c;margin-bottom:8px}.sc-mini strong{display:block;font-size:1.25rem;color:#101828}.sc-chart{height:190px;display:flex;align-items:end;gap:10px;border-bottom:1px solid #d8dee6;padding:12px 8px 0}.sc-bar{flex:1;border-radius:8px 8px 0 0;background:linear-gradient(180deg,#0f6b3c,#9bd6ae);min-height:32px}.sc-list{display:grid;gap:9px}.sc-list-row{display:flex;justify-content:space-between;gap:10px;border-bottom:1px solid #edf1f4;padding-bottom:8px;font-size:.83rem}.sc-list-row small{display:block;color:var(--muted);margin-top:2px}.sc-progress{height:7px;border-radius:999px;background:#e9eef2;overflow:hidden;min-width:84px}.sc-fill{height:100%;background:#0f6b3c}.sc-actions{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:14px}.sc-action{border:1px solid var(--line);border-radius:8px;background:#fff;padding:16px;display:flex;gap:12px;align-items:center;color:inherit;text-decoration:none}.sc-action i{width:36px;height:36px;border-radius:50%;display:grid;place-items:center;background:#e8f5ed;color:#0f6b3c}.sc-action strong{display:block;color:#102033}.sc-action small{color:var(--muted)}.sc-form{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:8px;align-items:end}.sc-form button{white-space:nowrap}@media(max-width:1400px){.sc-kpis{grid-template-columns:repeat(3,minmax(0,1fr))}.sc-grid,.sc-row,.sc-row2{grid-template-columns:1fr 1fr}.sc-actions{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:850px){.sc-kpis,.sc-grid,.sc-row,.sc-row2,.sc-actions,.sc-metric-grid,.sc-form{grid-template-columns:1fr}}',
]);
?>
<?php if ($message): ?><div class="notice ok"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>
<?php if ($stateMissing): ?><div class="notice error">Assign a state to this coordinator profile to make this dashboard production-scoped. Showing <?= e($state) ?> as the working view.</div><?php endif; ?>

<div class="sc-shell">
  <div class="sc-top">
    <div class="sc-title">
      <h2>State Coordinator Dashboard <span class="sc-scope"><?= e($state) ?></span></h2>
      <p>Monitor and coordinate all coconut development activities across your state and LGAs.</p>
    </div>
    <div class="sc-date"><i class="fa-regular fa-calendar"></i> <?= e(date('M j')) ?> - <?= e(date('M j, Y', strtotime('+6 days'))) ?></div>
  </div>

  <section class="sc-kpis">
    <div class="sc-kpi"><div><small>Registered Growers</small><strong><?= number_format($registeredGrowers) ?></strong><span>State grower registry</span><a href="users.php?role=grower">View registry <i class="fa-solid fa-arrow-right"></i></a></div><div class="sc-icon"><i class="fa-solid fa-users"></i></div></div>
    <div class="sc-kpi"><div><small>Verified Farmers</small><strong><?= number_format($verifiedGrowers) ?></strong><span><?= number_format($verificationRate, 1) ?>% of registered</span><a href="fields-management.php">View verified <i class="fa-solid fa-arrow-right"></i></a></div><div class="sc-icon"><i class="fa-solid fa-shield-check"></i></div></div>
    <div class="sc-kpi"><div><small>Active LGAs</small><strong><?= number_format($activeLgas) ?></strong><span>Coverage in this state</span><a href="reports.php?report=state">View LGAs <i class="fa-solid fa-arrow-right"></i></a></div><div class="sc-icon blue"><i class="fa-solid fa-location-dot"></i></div></div>
    <div class="sc-kpi"><div><small>Field Agents</small><strong><?= number_format($fieldAgents) ?></strong><span>Active state network</span><a href="users.php?role=field_agent">Manage agents <i class="fa-solid fa-arrow-right"></i></a></div><div class="sc-icon blue"><i class="fa-solid fa-user"></i></div></div>
    <div class="sc-kpi"><div><small>Pending Verifications</small><strong><?= number_format($pendingVerifications) ?></strong><span>Review queue</span><a href="fields-management.php">Review queue <i class="fa-solid fa-arrow-right"></i></a></div><div class="sc-icon orange"><i class="fa-solid fa-clipboard-list"></i></div></div>
    <div class="sc-kpi"><div><small>State Performance Score</small><strong><?= $stateScore ?><span style="display:inline;font-size:.8rem;color:#667085"> /100</span></strong><span>Operational health</span><a href="reports.php">View scorecard <i class="fa-solid fa-arrow-right"></i></a></div><div class="sc-icon orange"><i class="fa-solid fa-ranking-star"></i></div></div>
  </section>

  <section class="sc-grid">
    <div class="sc-panel">
      <div class="sc-head"><h3>LGA Performance Overview</h3><a href="reports.php?report=state">View full report</a></div>
      <div class="sc-map"><span><?= e($state) ?> LGA Performance Map</span></div>
      <table class="sc-table">
        <thead><tr><th>Top LGA</th><th>Score</th><th>Growers</th><th>Verified</th><th>Coverage</th></tr></thead>
        <tbody>
        <?php foreach ($lgaRows as $index => $row): $coverage = sc_pct((float) $row['verified'], max(1, (float) $row['growers'])); ?>
          <tr><td><?= $index + 1 ?>. <strong><?= e((string) $row['lga']) ?></strong></td><td><?= min(99, (int) round($coverage + 12)) ?></td><td><?= number_format((int) $row['growers']) ?></td><td><?= number_format((int) $row['verified']) ?></td><td><span class="sc-badge ok"><?= number_format($coverage, 0) ?>%</span></td></tr>
        <?php endforeach; ?>
        <?php if (!$lgaRows): ?><tr><td colspan="5">No LGA data available for this state yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="sc-panel">
      <div class="sc-head"><h3>Verification Workload Queue</h3><a href="fields-management.php">View all</a></div>
      <table class="sc-table">
        <thead><tr><th>ID</th><th>Grower</th><th>LGA</th><th>Status</th><th>Priority</th></tr></thead>
        <tbody>
        <?php foreach ($verificationRows as $row): ?>
          <tr><td>VER-<?= (int) $row['id'] ?></td><td><?= e((string) $row['grower_name']) ?></td><td><?= e((string) $row['lga']) ?></td><td><span class="sc-badge info"><?= e(ucwords(str_replace('_', ' ', (string) $row['status']))) ?></span></td><td><span class="sc-badge <?= in_array((string) $row['priority'], ['urgent','high'], true) ? 'warn' : 'ok' ?>"><?= e(ucfirst((string) ($row['priority'] ?: 'normal'))) ?></span></td></tr>
        <?php endforeach; ?>
        <?php if (!$verificationRows): ?><tr><td colspan="5">No pending verification workload.</td></tr><?php endif; ?>
        </tbody>
      </table>
      <div class="sc-list-row"><strong>Total Pending</strong><strong><?= number_format($pendingVerifications) ?></strong></div>
    </div>

    <div class="sc-panel">
      <div class="sc-head"><h3>Field Agent Assignments</h3><a href="users.php?role=field_agent">View all</a></div>
      <div class="sc-list">
        <?php foreach ($agentRows as $row): $perf = sc_pct((float) $row['completed'], max(1, (float) $row['tasks'])); ?>
          <div class="sc-list-row"><div><strong><?= e((string) $row['name']) ?></strong><small><?= e((string) $row['lga']) ?> / <?= (int) $row['tasks'] ?> task(s)</small></div><div><div class="sc-progress"><div class="sc-fill" style="width:<?= min(100, $perf) ?>%"></div></div><small><?= number_format($perf, 0) ?>%</small></div></div>
        <?php endforeach; ?>
        <?php if (!$agentRows): ?><p class="empty">No field agents assigned to this state yet.</p><?php endif; ?>
      </div>
    </div>

    <div class="sc-panel">
      <div class="sc-head"><h3>Grower Registration Trend</h3><a href="reports.php?report=state">View report</a></div>
      <div class="sc-chart">
        <?php foreach ([62,48,57,74,61,79,72] as $height): ?><div class="sc-bar" style="height:<?= $height ?>%"></div><?php endforeach; ?>
      </div>
      <div class="sc-list-row"><div><strong>This Month</strong><small>Registered and verified trend</small></div><strong><?= number_format(max($registeredGrowers, 0)) ?></strong></div>
    </div>
  </section>

  <section class="sc-row">
    <div class="sc-panel">
      <div class="sc-head"><h3>Farm Performance Summary</h3><a href="reports.php?report=farm">View report</a></div>
      <div class="sc-metric-grid">
        <div class="sc-mini"><i class="fa-solid fa-seedling"></i><small>Active Farms</small><strong><?= number_format((int) ($stats['farms'] ?? 0)) ?></strong></div>
        <div class="sc-mini"><i class="fa-solid fa-tree"></i><small>Coconut Stands</small><strong><?= number_format((float) ($stats['coconut_stands'] ?? 0), 0) ?></strong></div>
        <div class="sc-mini"><i class="fa-solid fa-leaf"></i><small>Intercrop Revenue</small><strong><?= sc_money((float) ($stats['intercrop_revenue'] ?? 0)) ?></strong></div>
        <div class="sc-mini"><i class="fa-solid fa-shield-heart"></i><small>Survival Rate</small><strong><?= number_format($survivalRate, 1) ?>%</strong></div>
      </div>
    </div>
    <div class="sc-panel">
      <div class="sc-head"><h3>Providers & Marketplace Activity</h3><a href="marketplace.php">View marketplace</a></div>
      <div class="sc-metric-grid">
        <div class="sc-mini"><i class="fa-solid fa-user-group"></i><small>Active Providers</small><strong><?= number_format($marketplaceProviders) ?></strong></div>
        <div class="sc-mini"><i class="fa-solid fa-store"></i><small>Active Listings</small><strong><?= number_format($activeListings) ?></strong></div>
        <div class="sc-mini"><i class="fa-solid fa-clipboard-check"></i><small>Orders</small><strong><?= number_format($ordersThisMonth) ?></strong></div>
        <div class="sc-mini"><i class="fa-solid fa-cart-shopping"></i><small>GMV</small><strong><?= sc_money($gmvThisMonth) ?></strong></div>
      </div>
    </div>
    <div class="sc-panel">
      <div class="sc-head"><h3>Academy Completion by LGA</h3><a href="academy.php">View academy</a></div>
      <table class="sc-table"><thead><tr><th>LGA</th><th>Enrolled</th><th>Completed</th><th>Rate</th></tr></thead><tbody>
        <?php foreach ($academyRows as $row): $rate = sc_pct((float) $row['completed'], max(1, (float) $row['enrolled'])); ?>
          <tr><td><?= e((string) $row['lga']) ?></td><td><?= (int) $row['enrolled'] ?></td><td><?= (int) $row['completed'] ?></td><td><div class="sc-progress"><div class="sc-fill" style="width:<?= min(100, $rate) ?>%"></div></div><?= number_format($rate, 0) ?>%</td></tr>
        <?php endforeach; ?>
        <?php if (!$academyRows): ?><tr><td colspan="4">No Academy records for this state scope yet.</td></tr><?php endif; ?>
      </tbody></table>
    </div>
    <div class="sc-panel">
      <div class="sc-head"><h3>Support Escalation Queue</h3><a href="support.php?status=escalated">View all</a></div>
      <table class="sc-table"><thead><tr><th>ID</th><th>Category</th><th>From</th><th>Priority</th></tr></thead><tbody>
        <?php foreach ($supportRows as $row): ?>
          <tr><td><?= e((string) $row['ticket_ref']) ?></td><td><?= e(ucwords((string) $row['category'])) ?></td><td><?= e((string) $row['requester_name']) ?></td><td><span class="sc-badge <?= e(support_badge_class((string) $row['priority'])) ?>"><?= e(ucfirst((string) $row['priority'])) ?></span></td></tr>
        <?php endforeach; ?>
        <?php if (!$supportRows): ?><tr><td colspan="4">No active state support escalations.</td></tr><?php endif; ?>
      </tbody></table>
    </div>
  </section>

  <section class="sc-row2">
    <div class="sc-panel">
      <div class="sc-head"><h3>Compliance Alerts</h3><a href="document-verification.php">View all</a></div>
      <div class="sc-list">
        <?php foreach ($resourceAlerts as $row): ?><div class="sc-list-row"><div><strong><?= e((string) $row['resource_name']) ?></strong><small><?= (float) $row['quantity_available'] <= (float) $row['reorder_level'] ? 'Below reorder level' : 'Inventory available' ?></small></div><a class="sc-badge warn" href="resource-allocation.php">Review</a></div><?php endforeach; ?>
        <?php foreach ($caseRows as $row): ?><div class="sc-list-row"><div><strong><?= e(ucwords(str_replace('_', ' ', (string) $row['category']))) ?></strong><small><?= (int) $row['total'] ?> open agronomy case(s)</small></div><a class="sc-badge info" href="agronomy.php">Review</a></div><?php endforeach; ?>
        <?php if (!$resourceAlerts && !$caseRows): ?><p class="empty">No compliance alerts for this state.</p><?php endif; ?>
      </div>
    </div>
    <div class="sc-panel">
      <div class="sc-head"><h3>Upcoming Field Events</h3><a href="fields-management.php">View calendar</a></div>
      <div class="sc-list">
        <div class="sc-list-row"><div><strong>Coconut Establishment Training</strong><small><?= e($state) ?> coordinator briefing</small></div><span class="sc-badge ok">May 25</span></div>
        <div class="sc-list-row"><div><strong>Farm Inspection Joint Exercise</strong><small>Field agents and LGA leads</small></div><span class="sc-badge info">May 27</span></div>
        <div class="sc-list-row"><div><strong>Grower Sensitisation Program</strong><small>Provider marketplace onboarding</small></div><span class="sc-badge warn">May 30</span></div>
      </div>
    </div>
    <div class="sc-panel">
      <div class="sc-head"><h3>Wallet Summary</h3><a href="reports.php?report=finance">View wallet</a></div>
      <h2 style="color:#0f6b3c;margin:.2rem 0"><?= sc_money($walletAvailable) ?></h2>
      <div class="sc-list-row"><span>This Month Allocation</span><strong><?= sc_money((float) ($budget['budgeted'] ?? 0)) ?></strong></div>
      <div class="sc-list-row"><span>Expenses</span><strong><?= sc_money((float) ($budget['spent'] ?? 0)) ?></strong></div>
    </div>
    <div class="sc-panel">
      <div class="sc-head"><h3>Messages</h3><a href="communications.php">View all</a></div>
      <div class="sc-list">
        <div class="sc-list-row"><div><strong>State Director</strong><small>Please ensure all verification queues are cleared before Friday.</small></div><span class="sc-badge ok">2</span></div>
        <div class="sc-list-row"><div><strong>LGA Team</strong><small>Field event participation confirmed.</small></div><span class="sc-badge info">1</span></div>
        <div class="sc-list-row"><div><strong>Support Team</strong><small><?= number_format($supportEscalations) ?> escalation(s) require attention.</small></div><span class="sc-badge warn"><?= number_format($supportEscalations) ?></span></div>
      </div>
    </div>
    <div class="sc-panel">
      <div class="sc-head"><h3>Recent Field Reports</h3><a href="reports.php">View reports</a></div>
      <div class="sc-list-row"><span>Reports Submitted</span><strong><?= number_format(sc_scalar($pdo, "SELECT COUNT(*) FROM farm_visits WHERE visited_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")) ?></strong></div>
      <div class="sc-list-row"><span>Farm Visits Completed</span><strong><?= number_format(sc_scalar($pdo, "SELECT COUNT(*) FROM farm_visits WHERE result IN ('submitted','verified') AND visited_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")) ?></strong></div>
      <div class="sc-list-row"><span>Issues Reported</span><strong><?= number_format(array_sum(array_map(static fn(array $row): int => (int) $row['total'], $caseRows))) ?></strong></div>
    </div>
  </section>

  <section class="sc-panel">
    <div class="sc-head"><h3>State Farmers Management</h3><a href="users.php?role=grower">Open registry</a></div>
    <table class="sc-table">
      <thead><tr><th>Farmer</th><th>LGA</th><th>Registered</th><th>Status</th><th>Accreditation</th></tr></thead>
      <tbody>
        <?php foreach ($farmers as $farmer): ?>
          <tr>
            <td><strong><?= e((string) $farmer['name']) ?></strong><br><span class="muted"><?= e((string) $farmer['email']) ?></span></td>
            <td><?= e((string) $farmer['lga']) ?></td>
            <td><?= e(sc_day((string) $farmer['created_at'])) ?><br><span class="muted"><?= e((string) $farmer['app_ref']) ?></span></td>
            <td><?= e(ucwords((string) $farmer['account_status'])) ?><br><span class="muted">Farm <?= e(ucwords((string) $farmer['verification_status'])) ?></span></td>
            <td>
              <form method="post" class="sc-form">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="update_accreditation">
                <input type="hidden" name="farmer_id" value="<?= (int) $farmer['id'] ?>">
                <select name="accreditation_status">
                  <?php foreach (['not_accredited' => 'Not Accredited', 'pending' => 'Pending', 'accredited' => 'Accredited', 'suspended' => 'Suspended'] as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= (string) $farmer['accreditation_status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                  <?php endforeach; ?>
                </select>
                <input name="accreditation_program" value="<?= e((string) $farmer['accreditation_program']) ?>" placeholder="Program">
                <button type="submit">Save</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$farmers): ?><tr><td colspan="5">No farmers found in this state scope.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </section>

  <section class="sc-panel">
    <div class="sc-head"><h3>Financial Management</h3><a href="reports.php?report=finance">Budget report</a></div>
    <form method="post" class="sc-form">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="record_budget">
      <label>Budget Line<input name="budget_line" required></label>
      <label>Fiscal Period<input name="fiscal_period" placeholder="2026 Q2"></label>
      <label>Budgeted<input name="amount_budgeted" inputmode="decimal"></label>
      <label>Spent<input name="amount_spent" inputmode="decimal"></label>
      <button type="submit">Record Budget</button>
    </form>
  </section>

  <section class="sc-actions">
    <a class="sc-action" href="users.php?role=field_agent"><i class="fa-solid fa-location-arrow"></i><span><strong>Assign Field Agent</strong><small>Allocate agents to LGAs</small></span></a>
    <a class="sc-action" href="fields-management.php"><i class="fa-solid fa-clipboard-check"></i><span><strong>Review Verification</strong><small>Verify growers and farms</small></span></a>
    <a class="sc-action" href="reports.php?report=state"><i class="fa-solid fa-file-export"></i><span><strong>Export State Report</strong><small>Download performance data</small></span></a>
    <a class="sc-action" href="communications.php"><i class="fa-solid fa-message"></i><span><strong>Message LGA Team</strong><small>Send updates and circulars</small></span></a>
    <a class="sc-action" href="fields-management.php"><i class="fa-solid fa-calendar-plus"></i><span><strong>Schedule Field Visit</strong><small>Plan inspections and events</small></span></a>
    <a class="sc-action" href="support.php?status=escalated"><i class="fa-solid fa-headset"></i><span><strong>Open Support Escalation</strong><small>Raise an urgent issue</small></span></a>
  </section>
</div>
<?php admin_page_end(); ?>
