<?php
declare(strict_types=1);

if (!defined('NATCODEV_REPORTS_LEGACY')) {
    if (!isset($_GET['page'])) {
        $_GET['page'] = 'overview';
    }
    require __DIR__ . '/acad/report-design.php';
    return;
}

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../lib/platform-governance.php';
require_once __DIR__ . '/../lib/marketplace.php';

$pdo = db();
admin_ensure_schema($pdo);
$currentReportUser = current_user($pdo);
if ($currentReportUser && !admin_session_is_authenticated($pdo)) {
    redirect_to('../dashboard/reports.php');
}
admin_require($pdo);
pg_ensure_schema($pdo);
marketplace_ensure_schema($pdo);

function report_count(PDO $pdo, string $sql, array $params = []): int
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int) ($stmt->fetchColumn() ?: 0);
    } catch (Throwable $e) {
        error_log('Report count failed: ' . $e->getMessage());
        return 0;
    }
}

function report_sum(PDO $pdo, string $sql, array $params = []): float
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (float) ($stmt->fetchColumn() ?: 0);
    } catch (Throwable $e) {
        error_log('Report sum failed: ' . $e->getMessage());
        return 0.0;
    }
}

function report_rows(PDO $pdo, string $sql, array $params = []): array
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log('Report rows failed: ' . $e->getMessage());
        return [];
    }
}

function report_pct(float $part, float $whole): float
{
    return $whole > 0 ? round(($part / $whole) * 100, 1) : 0.0;
}

function report_money(float $amount): string
{
    return 'NGN ' . number_format($amount, 2);
}

function report_catalog_for_role(string $role): array
{
    $catalog = [
        'executive' => ['Executive Intelligence', 'National performance, risk, growth, adoption, marketplace, finance, and compliance.'],
        'state' => ['State Operations', 'State farmers, farms, accreditation, resources, field network, provider coverage, and actions.'],
        'grower' => ['Grower Intelligence', 'Registration, documents, farms, accreditation, wallet, purchases, support, training, and farm health.'],
        'field' => ['Field Team Reports', 'Tasks, visits, GPS confidence, pending verification, agronomy observations, and productivity.'],
        'provider' => ['Provider Reports', 'Input/service providers, approvals, offerings, geography, inventory, inquiries, and delivery capacity.'],
        'marketplace' => ['Marketplace Reports', 'Sellers, listings, orders, revenue, buyer demand, stock, disputes, and approvals.'],
        'finance' => ['Finance Reports', 'Wallet funding, spend, pending payments, marketplace value, allocation budgets, and anomalies.'],
        'support' => ['Support & Communication', 'Tickets, priority queues, response risk, broadcasts, notification delivery, and unresolved issues.'],
        'compliance' => ['Compliance Reports', 'Document verification, certificates, accreditation, governance policy review, and audit events.'],
    ];

    return match ($role) {
        'state_coordinator' => array_intersect_key($catalog, array_flip(['state', 'grower', 'field', 'provider', 'support', 'compliance'])),
        'field_agent', 'agronomist', 'agric_extensionist' => array_intersect_key($catalog, array_flip(['field', 'grower', 'support'])),
        'investor' => array_intersect_key($catalog, array_flip(['executive', 'marketplace', 'finance'])),
        default => $catalog,
    };
}

function report_csv(string $filename, array $rows): void
{
    app_export_csv($filename, $rows ? array_keys($rows[0]) : [], $rows);
}

$role = admin_current_platform_role($pdo) ?: 'admin';
$catalog = report_catalog_for_role($role);
$selectedReport = (string) ($_GET['report'] ?? array_key_first($catalog));
if (!isset($catalog[$selectedReport])) {
    $selectedReport = (string) array_key_first($catalog);
}

$startDate = (string) ($_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days')));
$endDate = (string) ($_GET['end_date'] ?? date('Y-m-d'));
$states = report_rows($pdo, "SELECT id, state_name FROM nigeria_states ORDER BY state_name");
$selectedStateId = (int) ($_GET['state_id'] ?? 0);
$selectedLgaId = (int) ($_GET['lga_id'] ?? 0);
$scopeState = trim((string) ($_GET['state'] ?? admin_current_scope_state($pdo)));
if ($role === 'state_coordinator') {
    $assigned = admin_current_scope_state($pdo);
    $scopeState = $assigned !== '' ? $assigned : $scopeState;
}
if ($selectedStateId <= 0 && $scopeState !== '') {
    $stateLookup = $pdo->prepare("SELECT id FROM nigeria_states WHERE state_name = ? LIMIT 1");
    $stateLookup->execute([$scopeState]);
    $selectedStateId = (int) ($stateLookup->fetchColumn() ?: 0);
}
$selectedStateName = '';
foreach ($states as $stateRow) {
    if ((int) $stateRow['id'] === $selectedStateId) {
        $selectedStateName = (string) $stateRow['state_name'];
        break;
    }
}
$scopeState = $selectedStateName !== '' ? $selectedStateName : $scopeState;
$lgaOptions = [];
if ($selectedStateId > 0) {
    $lgaStmt = $pdo->prepare("SELECT id, lga_name FROM nigeria_lgas WHERE state_id = ? ORDER BY lga_name");
    $lgaStmt->execute([$selectedStateId]);
    $lgaOptions = $lgaStmt->fetchAll();
}
$selectedLgaName = '';
foreach ($lgaOptions as $lgaRow) {
    if ((int) $lgaRow['id'] === $selectedLgaId) {
        $selectedLgaName = (string) $lgaRow['lga_name'];
        break;
    }
}
if ($selectedLgaId > 0 && $selectedLgaName === '') {
    $selectedLgaId = 0;
}

$periodStart = $startDate . ' 00:00:00';
$periodEnd = $endDate . ' 23:59:59';
$dateParams = [$periodStart, $periodEnd];
$locationFilterSql = '1=1';
$locationFilterParams = [];
if ($selectedStateId > 0) {
    $locationFilterSql = "(gf.state_id = ? OR a.state_id = ? OR ns.id = ? OR ns.state_name = ? OR u.location LIKE ? OR a.location LIKE ?)";
    $locationFilterParams = [$selectedStateId, $selectedStateId, $selectedStateId, $scopeState, '%' . $scopeState . '%', '%' . $scopeState . '%'];
}
if ($selectedLgaId > 0) {
    $locationFilterSql .= " AND (gf.lga_id = ? OR a.lga_id = ? OR nl.id = ?)";
    array_push($locationFilterParams, $selectedLgaId, $selectedLgaId, $selectedLgaId);
}

$farmersTotal = report_count($pdo, "
    SELECT COUNT(DISTINCT u.id)
    FROM users u
    LEFT JOIN applications a ON a.id = u.application_id
    LEFT JOIN grower_farms gf ON gf.user_id = u.id
    LEFT JOIN nigeria_states ns ON ns.id = COALESCE(gf.state_id, a.state_id)
    LEFT JOIN nigeria_lgas nl ON nl.id = COALESCE(gf.lga_id, a.lga_id)
    WHERE u.role = 'grower' AND {$locationFilterSql}
", $locationFilterParams);
$farmersNew = report_count($pdo, "
    SELECT COUNT(DISTINCT u.id)
    FROM users u
    LEFT JOIN applications a ON a.id = u.application_id
    LEFT JOIN grower_farms gf ON gf.user_id = u.id
    LEFT JOIN nigeria_states ns ON ns.id = COALESCE(gf.state_id, a.state_id)
    LEFT JOIN nigeria_lgas nl ON nl.id = COALESCE(gf.lga_id, a.lga_id)
    WHERE u.role = 'grower' AND u.created_at BETWEEN ? AND ? AND {$locationFilterSql}
", array_merge($dateParams, $locationFilterParams));
$farmsTotal = report_count($pdo, "
    SELECT COUNT(DISTINCT gf.id)
    FROM grower_farms gf
    JOIN users u ON u.id = gf.user_id
    LEFT JOIN applications a ON a.id = u.application_id
    LEFT JOIN nigeria_states ns ON ns.id = COALESCE(gf.state_id, a.state_id)
    LEFT JOIN nigeria_lgas nl ON nl.id = COALESCE(gf.lga_id, a.lga_id)
    WHERE {$locationFilterSql}
", $locationFilterParams);
$hectares = report_sum($pdo, "
    SELECT COALESCE(SUM(gf.farm_size), 0)
    FROM grower_farms gf
    JOIN users u ON u.id = gf.user_id
    LEFT JOIN applications a ON a.id = u.application_id
    LEFT JOIN nigeria_states ns ON ns.id = COALESCE(gf.state_id, a.state_id)
    LEFT JOIN nigeria_lgas nl ON nl.id = COALESCE(gf.lga_id, a.lga_id)
    WHERE {$locationFilterSql}
", $locationFilterParams);
$accredited = report_count($pdo, "
    SELECT COUNT(DISTINCT u.id)
    FROM users u
    LEFT JOIN applications a ON a.id = u.application_id
    LEFT JOIN grower_farms gf ON gf.user_id = u.id
    LEFT JOIN nigeria_states ns ON ns.id = COALESCE(gf.state_id, a.state_id)
    LEFT JOIN nigeria_lgas nl ON nl.id = COALESCE(gf.lga_id, a.lga_id)
    WHERE u.role = 'grower' AND COALESCE(u.accreditation_status, 'not_accredited') = 'accredited' AND {$locationFilterSql}
", $locationFilterParams);
$pendingDocs = report_count($pdo, "
    SELECT COUNT(DISTINCT dr.id)
    FROM document_requirements dr
    JOIN users u ON u.id = dr.user_id
    LEFT JOIN applications a ON a.id = u.application_id
    LEFT JOIN grower_farms gf ON gf.user_id = u.id
    LEFT JOIN nigeria_states ns ON ns.id = COALESCE(gf.state_id, a.state_id)
    LEFT JOIN nigeria_lgas nl ON nl.id = COALESCE(gf.lga_id, a.lga_id)
    WHERE dr.verification_status IN ('pending','needs_review') AND {$locationFilterSql}
", $locationFilterParams);
$openSupport = report_count($pdo, "
    SELECT COUNT(DISTINCT m.id)
    FROM messages m
    JOIN users u ON u.id = m.user_id
    LEFT JOIN applications a ON a.id = u.application_id
    LEFT JOIN grower_farms gf ON gf.user_id = u.id
    LEFT JOIN nigeria_states ns ON ns.id = COALESCE(gf.state_id, a.state_id)
    LEFT JOIN nigeria_lgas nl ON nl.id = COALESCE(gf.lga_id, a.lga_id)
    WHERE m.status IN ('open','in_progress') AND {$locationFilterSql}
", $locationFilterParams);
$fieldVisits = report_count($pdo, "
    SELECT COUNT(DISTINCT fv.id)
    FROM farm_visits fv
    JOIN grower_farms gf ON gf.id = fv.farm_id
    JOIN users u ON u.id = gf.user_id
    LEFT JOIN applications a ON a.id = u.application_id
    LEFT JOIN nigeria_states ns ON ns.id = COALESCE(gf.state_id, a.state_id)
    LEFT JOIN nigeria_lgas nl ON nl.id = COALESCE(gf.lga_id, a.lga_id)
    WHERE fv.visited_at BETWEEN ? AND ? AND {$locationFilterSql}
", array_merge($dateParams, $locationFilterParams));
$pendingTasks = report_count($pdo, "
    SELECT COUNT(DISTINCT ft.id)
    FROM field_tasks ft
    JOIN grower_farms gf ON gf.id = ft.farm_id
    JOIN users u ON u.id = gf.user_id
    LEFT JOIN applications a ON a.id = u.application_id
    LEFT JOIN nigeria_states ns ON ns.id = COALESCE(gf.state_id, a.state_id)
    LEFT JOIN nigeria_lgas nl ON nl.id = COALESCE(gf.lga_id, a.lga_id)
    WHERE ft.status IN ('pending','assigned','in_progress') AND {$locationFilterSql}
", $locationFilterParams);
$verifiedFarms = report_count($pdo, "
    SELECT COUNT(DISTINCT fv.id)
    FROM farm_verifications fv
    JOIN grower_farms gf ON gf.id = fv.farm_id
    JOIN users u ON u.id = gf.user_id
    LEFT JOIN applications a ON a.id = u.application_id
    LEFT JOIN nigeria_states ns ON ns.id = COALESCE(gf.state_id, a.state_id)
    LEFT JOIN nigeria_lgas nl ON nl.id = COALESCE(gf.lga_id, a.lga_id)
    WHERE fv.status = 'verified' AND {$locationFilterSql}
", $locationFilterParams);
$providersApproved = report_count($pdo, "SELECT COUNT(*) FROM provider_registry WHERE status IN ('approved','verified')");
$providersPending = report_count($pdo, "SELECT COUNT(*) FROM provider_registry WHERE status IN ('pending','pending_review')");
$sellerCount = report_count($pdo, "SELECT COUNT(*) FROM marketplace_sellers WHERE approval_status = 'approved'");
$listingCount = report_count($pdo, "SELECT COUNT(*) FROM marketplace_listings WHERE approval_status = 'approved'");
$registered = report_count($pdo, "SELECT COUNT(*) FROM webinar_registrations");
$orderCount = report_count($pdo, "SELECT COUNT(*) FROM marketplace_orders WHERE created_at BETWEEN ? AND ?", $dateParams);
$orderValue = report_sum($pdo, "SELECT COALESCE(SUM(total_amount), 0) FROM marketplace_orders WHERE created_at BETWEEN ? AND ?", $dateParams);
$walletVolume = report_sum($pdo, "SELECT COALESCE(SUM(amount), 0) FROM wallet_transactions WHERE status IN ('success','completed','paid') AND created_at BETWEEN ? AND ?", $dateParams);
$budgeted = report_sum($pdo, "SELECT COALESCE(SUM(amount_budgeted), 0) FROM state_budget_records WHERE (? = '' OR state_name = ?)", [$scopeState, $scopeState]);
$spent = report_sum($pdo, "SELECT COALESCE(SUM(amount_spent), 0) FROM state_budget_records WHERE (? = '' OR state_name = ?)", [$scopeState, $scopeState]);

$accreditationRate = report_pct($accredited, $farmersTotal);
$verificationRate = report_pct($verifiedFarms, max(1, $farmsTotal));
$budgetBurn = report_pct($spent, $budgeted);

$areaLabel = $selectedStateId > 0 ? 'Local Government Area' : 'State';
$areaNameSql = $selectedStateId > 0 ? "COALESCE(nl.lga_name, 'Unassigned LGA')" : "COALESCE(ns.state_name, 'Unassigned')";
$stateRows = report_rows($pdo, "
    SELECT {$areaNameSql} area_name,
           COUNT(DISTINCT u.id) farmers,
           COUNT(DISTINCT gf.id) farms,
           COALESCE(SUM(gf.farm_size), 0) hectares,
           SUM(CASE WHEN COALESCE(u.accreditation_status, 'not_accredited') = 'accredited' THEN 1 ELSE 0 END) accredited
    FROM users u
    LEFT JOIN applications a ON a.id = u.application_id
    LEFT JOIN grower_farms gf ON gf.user_id = u.id
    LEFT JOIN nigeria_states ns ON ns.id = COALESCE(gf.state_id, a.state_id)
    LEFT JOIN nigeria_lgas nl ON nl.id = COALESCE(gf.lga_id, a.lga_id)
    WHERE u.role = 'grower' AND {$locationFilterSql}
    GROUP BY {$areaNameSql}
    ORDER BY farmers DESC, area_name
    LIMIT 20
", $locationFilterParams);
$providerRows = report_rows($pdo, "
    SELECT provider_type, status, COUNT(*) total
    FROM provider_registry
    GROUP BY provider_type, status
    ORDER BY provider_type, status
");
$marketRows = report_rows($pdo, "
    SELECT ms.store_name, ms.seller_type, COUNT(ml.id) listings, COALESCE(SUM(mo.total_amount), 0) order_value
    FROM marketplace_sellers ms
    LEFT JOIN marketplace_listings ml ON ml.seller_id = ms.id
    LEFT JOIN marketplace_orders mo ON mo.seller_id = ms.id AND mo.created_at BETWEEN ? AND ?
    GROUP BY ms.id, ms.store_name, ms.seller_type
    ORDER BY order_value DESC, listings DESC
    LIMIT 12
", $dateParams);
$supportRows = report_rows($pdo, "
    SELECT category, priority, status, COUNT(*) total
    FROM messages
    GROUP BY category, priority, status
    ORDER BY FIELD(priority, 'high','medium','low'), total DESC
    LIMIT 14
");
$fieldRows = report_rows($pdo, "
    SELECT u.name agent, COUNT(fv.id) visits,
           SUM(CASE WHEN ft.status IN ('pending','assigned','in_progress') THEN 1 ELSE 0 END) open_tasks
    FROM users u
    LEFT JOIN farm_visits fv ON fv.agent_id = u.id AND fv.visited_at BETWEEN ? AND ?
    LEFT JOIN field_tasks ft ON ft.assigned_to = u.id
    WHERE u.role = 'field_agent' OR u.is_agronomist = 1 OR u.is_extensionist = 1
    GROUP BY u.id, u.name
    ORDER BY visits DESC, open_tasks DESC
    LIMIT 12
", $dateParams);

$exportRows = match ($selectedReport) {
    'state', 'executive' => $stateRows,
    'provider' => $providerRows,
    'marketplace', 'finance' => $marketRows,
    'support' => $supportRows,
    'field' => $fieldRows,
    default => report_rows($pdo, "
        SELECT u.id, u.name, u.email, u.created_at, u.account_status, u.accreditation_status, COUNT(gf.id) farms
        FROM users u
        LEFT JOIN grower_farms gf ON gf.user_id = u.id
        WHERE u.role = 'grower'
        GROUP BY u.id, u.name, u.email, u.created_at, u.account_status, u.accreditation_status
        ORDER BY u.created_at DESC
        LIMIT 500
    "),
};

if (($_GET['format'] ?? '') === 'csv') {
    report_csv('natcodev-' . $selectedReport . '-report-' . date('Ymd') . '.csv', $exportRows);
}

$insights = [];
if ($farmersTotal > 0 && $accreditationRate < 40) {
    $insights[] = ['warning', 'Accreditation is below target', $accreditationRate . '% of growers are accredited. Prioritize document clean-up and state coordinator follow-up.'];
}
if ($farmsTotal > 0 && $verificationRate < 50) {
    $insights[] = ['warning', 'Farm verification backlog', $verificationRate . '% of farms are verified. Dispatch field agents to farms without recent visits.'];
}
if ($pendingDocs > max(10, (int) round($farmersTotal * 0.2))) {
    $insights[] = ['danger', 'Document queue needs attention', number_format($pendingDocs) . ' documents are pending or need review.'];
}
if ($providersPending > $providersApproved) {
    $insights[] = ['warning', 'Provider onboarding bottleneck', 'Pending providers exceed approved providers. Review provider registry and approve trusted suppliers.'];
}
if ($orderCount > 0 && $listingCount > 0) {
    $insights[] = ['ok', 'Marketplace demand is measurable', number_format($orderCount) . ' order(s) worth ' . report_money($orderValue) . ' occurred in the selected period.'];
}
if ($budgeted > 0 && $budgetBurn > 85) {
    $insights[] = ['danger', 'Budget burn is high', $budgetBurn . '% of recorded budget has been spent. Check allocation effectiveness before adding new spend.'];
}
if (!$insights) {
    $insights[] = ['ok', 'No critical report alerts', 'The selected scope has no major threshold breach from the available data.'];
}

admin_page_start('Reporting Intelligence', [
    'active' => 'reports.php',
    'description' => 'Smart, role-aware reporting across growers, field teams, providers, marketplace, finance, support, compliance, and operations.',
    'wide' => true,
    'css' => '
      .report-shell{display:grid;grid-template-columns:300px 1fr;gap:18px;align-items:start}
      .report-list{display:grid;gap:8px}
      .report-list a{display:block;padding:11px 12px;border:1px solid var(--line);border-radius:7px;background:#fff;color:#344054}
      .report-list a.active,.report-list a:hover{background:#eef7f1;color:var(--green-dark);text-decoration:none}
      .intelligence-grid{grid-template-columns:repeat(auto-fit,minmax(240px,1fr))}
      .signal{border-left:5px solid var(--green)}
      .signal.warning{border-left-color:var(--warn)}
      .signal.danger{border-left-color:var(--danger)}
      .kpi-small{font-size:.84rem;color:var(--muted);font-weight:750}
      .ri-top{display:flex;justify-content:space-between;gap:18px;align-items:flex-start;margin-bottom:18px}
      .ri-top h2{margin:0;color:#06451f;font-size:1.45rem}.ri-top p{margin:5px 0 0;color:var(--muted)}
      .ri-assurance{display:flex;gap:10px;flex-wrap:wrap}.ri-assurance span{display:inline-flex;align-items:center;gap:7px;border:1px solid var(--line);border-radius:8px;background:#fff;padding:9px 11px;font-weight:850;font-size:.84rem;color:#12391f}
      .ri-board{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:14px;margin-bottom:18px}
      .ri-card{background:#fff;border:1px solid rgba(16,24,40,.08);border-radius:8px;box-shadow:var(--shadow);padding:16px;min-width:0}
      .ri-card h2,.ri-card h3{margin:0;color:#06451f}.ri-card p{margin:5px 0 0;color:var(--muted);line-height:1.45}
      .ri-span-3{grid-column:span 3}.ri-span-4{grid-column:span 4}.ri-span-5{grid-column:span 5}.ri-span-6{grid-column:span 6}.ri-span-7{grid-column:span 7}.ri-span-8{grid-column:span 8}.ri-span-12{grid-column:span 12}
      .ri-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:12px}.ri-num{font-size:1.55rem;font-weight:950;color:#06451f}.ri-mini-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:9px;margin-top:12px}.ri-mini{border:1px solid var(--line);border-radius:8px;background:#fbfdfb;padding:11px}.ri-mini strong{display:block;color:#06451f;font-size:1.2rem}.ri-mini span{display:block;color:var(--muted);font-size:.78rem;font-weight:850}
      .ri-report-cats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:9px;margin-top:12px}.ri-cat{display:flex;gap:10px;align-items:center;border:1px solid var(--line);border-radius:8px;background:#fbfdfb;padding:10px;color:#12391f}.ri-cat i{width:34px;height:34px;border-radius:8px;background:#eaf8f0;color:#0f6b3c;display:grid;place-items:center}.ri-cat strong{display:block}.ri-cat span{font-size:.78rem;color:var(--muted)}
      .ri-tabs{display:flex;gap:8px;border-bottom:1px solid var(--line);margin:12px 0}.ri-tabs span{padding:8px 0;font-size:.78rem;font-weight:900;color:#475467}.ri-tabs span.active{color:#06451f;border-bottom:2px solid #06451f}
      .ri-list{display:grid;gap:8px}.ri-row{display:flex;justify-content:space-between;gap:12px;border-bottom:1px solid #edf1ea;padding:8px 0}.ri-row:last-child{border-bottom:0}.ri-badge{display:inline-flex;align-items:center;gap:5px;border-radius:999px;background:#eaf8f0;color:#0f6b3c;padding:4px 8px;font-size:.74rem;font-weight:950}.ri-badge.warn{background:#fff7df;color:#8a5a00}.ri-badge.bad{background:#fff3f3;color:#a32020}
      .ri-chart{height:150px;border-left:1px solid var(--line);border-bottom:1px solid var(--line);display:grid;grid-template-columns:repeat(10,1fr);gap:8px;align-items:end;padding:10px 10px 0;background:linear-gradient(#fff,#fbfdfb);margin-top:12px}.ri-bar{height:var(--h);min-height:12px;background:linear-gradient(180deg,#1f8a55,#cfe8d8);border-radius:5px 5px 0 0}
      .ri-donut{width:112px;height:112px;border-radius:50%;background:conic-gradient(#0f6b3c 0 58%,#2374c6 58% 78%,#f79009 78% 91%,#d92d20 91%);display:grid;place-items:center;margin:auto}.ri-donut b{display:grid;place-items:center;width:64px;height:64px;border-radius:50%;background:#fff;color:#06451f}
      .ri-map{height:180px;border:1px solid var(--line);border-radius:8px;background:radial-gradient(circle at 55% 40%,#2f8f52 0 8%,transparent 9%),radial-gradient(circle at 38% 58%,#82c78d 0 11%,transparent 12%),radial-gradient(circle at 62% 70%,#b8dfbc 0 15%,transparent 16%),linear-gradient(135deg,#eaf8f0,#f8fcfa);display:grid;place-items:center;color:#06451f;font-weight:950}
      .ri-flow{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:10px}.ri-step{text-align:center;border:1px solid var(--line);border-radius:8px;background:#fbfdfb;padding:12px}.ri-step i{width:40px;height:40px;border-radius:50%;display:grid;place-items:center;margin:0 auto 8px;background:#eaf8f0;color:#0f6b3c}
      @media(max-width:1200px){.ri-span-3,.ri-span-4,.ri-span-5,.ri-span-6,.ri-span-7,.ri-span-8{grid-column:span 12}.ri-mini-grid,.ri-report-cats{grid-template-columns:repeat(2,minmax(0,1fr))}.ri-flow{grid-template-columns:repeat(3,minmax(0,1fr))}}
      @media(max-width:680px){.ri-board,.ri-mini-grid,.ri-report-cats,.ri-flow{grid-template-columns:1fr}.ri-span-3,.ri-span-4,.ri-span-5,.ri-span-6,.ri-span-7,.ri-span-8,.ri-span-12{grid-column:auto}.ri-top{display:block}}
      @media(max-width:980px){.report-shell{grid-template-columns:1fr}}
    ',
]);
?>
<section class="ri-top">
  <div><h2>Reporting Intelligence & Drilldowns</h2><p>Actionable insights for every stakeholder. Drill down by State, LGA, Date, Program, or Module.</p></div>
  <div class="ri-assurance">
    <span>Data is real-time or near real-time</span>
    <span>Export CSV / Excel-safe</span>
    <span>Drill down to records</span>
    <span>Audit trail for every action</span>
  </div>
</section>

<section class="ri-board">
  <article class="ri-card ri-span-3">
    <div class="ri-head"><h3>Reports Home</h3><a href="?<?= e(http_build_query(array_merge($_GET, ['format' => 'csv']))) ?>">Export</a></div>
    <p>Role-aware report summary across active modules.</p>
    <div class="ri-report-cats">
      <?php foreach ([['Registry', $farmersTotal, 'fa-user-shield'], ['Farm Ops', $farmsTotal, 'fa-seedling'], ['Marketplace', $orderCount, 'fa-cart-shopping'], ['Academy', $registered ?? 0, 'fa-graduation-cap'], ['Wallet', (int) $walletVolume, 'fa-wallet'], ['Support', $openSupport, 'fa-headset'], ['Certificates', $accredited, 'fa-award'], ['Field Network', $fieldVisits, 'fa-map-location-dot']] as $cat): ?>
        <a class="ri-cat" href="#detail"><i class="fas <?= e($cat[2]) ?>"></i><span><strong><?= e((string) $cat[0]) ?></strong><span><?= e(number_format((float) $cat[1])) ?> records</span></span></a>
      <?php endforeach; ?>
    </div>
    <div class="ri-list" style="margin-top:14px">
      <?php foreach (array_slice($catalog, 0, 4, true) as $key => $item): ?>
        <div class="ri-row"><span><?= e($item[0]) ?></span><span class="ri-badge">Ready</span></div>
      <?php endforeach; ?>
    </div>
  </article>

  <article class="ri-card ri-span-5">
    <div class="ri-head"><h3>Grower My Reports</h3><a href="?report=grower">Full Grower Report</a></div>
    <p>Your farm, verification, training, wallet and marketplace insights.</p>
    <div class="ri-mini-grid">
      <div class="ri-mini"><strong><?= number_format($farmersTotal) ?></strong><span>Growers</span></div>
      <div class="ri-mini"><strong><?= number_format($farmsTotal) ?></strong><span>Farms</span></div>
      <div class="ri-mini"><strong><?= $accreditationRate ?>%</strong><span>Accreditation</span></div>
      <div class="ri-mini"><strong><?= number_format($openSupport) ?></strong><span>Open Support</span></div>
    </div>
    <div class="ri-tabs"><span class="active">Farm Performance</span><span>Verification</span><span>Academy</span><span>Wallet</span><span>Orders</span></div>
    <div class="ri-board" style="margin:0">
      <div class="ri-card ri-span-6" style="box-shadow:none"><h3>Farm Activities</h3><div class="ri-donut"><b><?= number_format($fieldVisits) ?><small>Total</small></b></div></div>
      <div class="ri-card ri-span-6" style="box-shadow:none"><h3>Value Bridge</h3><div class="ri-num"><?= e(report_money($orderValue)) ?></div><small>Marketplace value this period</small><div class="ri-chart"><?php foreach ([22,35,28,46,42,61,38,72,64,48] as $h): ?><i class="ri-bar" style="--h:<?= $h ?>%"></i><?php endforeach; ?></div></div>
    </div>
  </article>

  <article class="ri-card ri-span-4">
    <div class="ri-head"><h3>Provider / Seller Reports</h3><a href="?report=provider">Business Report</a></div>
    <p>Business, products, services, sales, accreditation and settlement.</p>
    <div class="ri-mini-grid">
      <div class="ri-mini"><strong><?= number_format($orderCount) ?></strong><span>Orders</span></div>
      <div class="ri-mini"><strong><?= e(report_money($orderValue)) ?></strong><span>Sales</span></div>
      <div class="ri-mini"><strong><?= number_format($listingCount) ?></strong><span>Products</span></div>
      <div class="ri-mini"><strong><?= number_format($providersApproved) ?></strong><span>Providers</span></div>
    </div>
    <div class="ri-chart"><?php foreach ([18,31,26,43,57,41,66,52,73,39] as $h): ?><i class="ri-bar" style="--h:<?= $h ?>%"></i><?php endforeach; ?></div>
    <div class="ri-list">
      <?php foreach (array_slice($marketRows, 0, 4) as $row): ?><div class="ri-row"><span><?= e((string) $row['store_name']) ?></span><strong><?= e(report_money((float) $row['order_value'])) ?></strong></div><?php endforeach; ?>
    </div>
  </article>

  <article class="ri-card ri-span-4">
    <div class="ri-head"><h3>Field Agent Reports</h3><a href="?report=field">Field Agent Report</a></div>
    <p>Visits, verifications, evidence and assignments.</p>
    <div class="ri-mini-grid">
      <div class="ri-mini"><strong><?= number_format($pendingTasks) ?></strong><span>Assigned</span></div>
      <div class="ri-mini"><strong><?= number_format($fieldVisits) ?></strong><span>Completed</span></div>
      <div class="ri-mini"><strong><?= number_format($verifiedFarms) ?></strong><span>Verified</span></div>
      <div class="ri-mini"><strong><?= number_format($pendingDocs) ?></strong><span>Queue</span></div>
    </div>
    <div class="ri-donut"><b><?= number_format($verifiedFarms) ?><small>Total</small></b></div>
  </article>

  <article class="ri-card ri-span-4">
    <div class="ri-head"><h3>State Operations Report</h3><a href="?report=state">Full State Report</a></div>
    <p>Overview by State and LGA with drilldowns.</p>
    <div class="ri-mini-grid">
      <div class="ri-mini"><strong><?= number_format($farmersTotal) ?></strong><span>Total Growers</span></div>
      <div class="ri-mini"><strong><?= number_format($accredited) ?></strong><span>Verified Growers</span></div>
      <div class="ri-mini"><strong><?= number_format($sellerCount + $providersApproved) ?></strong><span>Providers / Sellers</span></div>
      <div class="ri-mini"><strong><?= number_format($registered ?? 0) ?></strong><span>Academy</span></div>
    </div>
    <div class="ri-map">State / LGA Drilldown Map</div>
  </article>

  <article class="ri-card ri-span-4">
    <div class="ri-head"><h3>National Intelligence</h3><a href="?report=executive">National Report</a></div>
    <p>National level insights across states and programs.</p>
    <div class="ri-mini-grid">
      <div class="ri-mini"><strong><?= number_format($farmersTotal) ?></strong><span>Total Growers</span></div>
      <div class="ri-mini"><strong><?= number_format($accredited) ?></strong><span>Verified Growers</span></div>
      <div class="ri-mini"><strong><?= number_format($providersApproved + $sellerCount) ?></strong><span>Providers / Sellers</span></div>
      <div class="ri-mini"><strong><?= e(report_money($walletVolume)) ?></strong><span>Finance</span></div>
    </div>
    <div class="ri-list"><?php foreach (array_slice($stateRows, 0, 6) as $row): ?><div class="ri-row"><span><?= e((string) $row['area_name']) ?></span><strong><?= number_format((int) $row['farmers']) ?></strong></div><?php endforeach; ?></div>
  </article>

  <article class="ri-card ri-span-4">
    <div class="ri-head"><h3>Export Center</h3><a href="?<?= e(http_build_query(array_merge($_GET, ['format' => 'csv']))) ?>">Generate Export</a></div>
    <p>Export, schedule, and manage report deliveries.</p>
    <div class="ri-list">
      <div class="ri-row"><span>Report Category</span><strong><?= e($catalog[$selectedReport][0]) ?></strong></div>
      <div class="ri-row"><span>Scope</span><strong><?= e($scopeState ?: 'All States') ?><?= $selectedLgaName ? ' / ' . e($selectedLgaName) : '' ?></strong></div>
      <div class="ri-row"><span>Date Range</span><strong><?= e($startDate) ?> - <?= e($endDate) ?></strong></div>
      <div class="ri-row"><span>Format</span><strong>Excel-safe CSV</strong></div>
    </div>
    <a class="button" href="?<?= e(http_build_query(array_merge($_GET, ['format' => 'csv']))) ?>">Generate Export</a>
  </article>

  <article class="ri-card ri-span-6">
    <h3>Reporting & Drilldown Flow</h3>
    <div class="ri-flow" style="margin-top:12px">
      <?php foreach ([['fa-file-lines','Select Report'],['fa-filter','Set Scope'],['fa-chart-column','View Summary'],['fa-magnifying-glass-chart','Drill Down'],['fa-file-export','Export / Share'],['fa-clipboard-check','Audit Log']] as [$icon,$label]): ?>
        <div class="ri-step"><i class="fas <?= e($icon) ?>"></i><strong><?= e($label) ?></strong></div>
      <?php endforeach; ?>
    </div>
  </article>

  <article class="ri-card ri-span-3">
    <h3>Export Assurance</h3>
    <div class="ri-list"><div class="ri-row"><span>Excel-safe CSV</span><span class="ri-badge">Ready</span></div><div class="ri-row"><span>Clean headers</span><span class="ri-badge">Ready</span></div><div class="ri-row"><span>Large data handling</span><span class="ri-badge">Ready</span></div></div>
  </article>

  <article class="ri-card ri-span-3">
    <h3>Who Gets What</h3>
    <div class="ri-list"><div class="ri-row"><span>Growers</span><small>Farm insights</small></div><div class="ri-row"><span>Providers / Sellers</span><small>Sales, settlement</small></div><div class="ri-row"><span>Field Agents</span><small>Visits, evidence</small></div><div class="ri-row"><span>Admins</span><small>All reports</small></div></div>
  </article>
</section>

<form class="panel toolbar" method="get">
  <label>Report
    <select name="report">
      <?php foreach ($catalog as $key => $item): ?>
        <option value="<?= e($key) ?>" <?= $selectedReport === $key ? 'selected' : '' ?>><?= e($item[0]) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>State Scope
    <select name="state_id" id="report_state_id" <?= $role === 'state_coordinator' && $selectedStateId > 0 ? 'data-locked="1"' : '' ?>>
      <option value="">All states</option>
      <?php foreach ($states as $state): ?>
        <option value="<?= (int) $state['id'] ?>" <?= $selectedStateId === (int) $state['id'] ? 'selected' : '' ?>><?= e((string) $state['state_name']) ?></option>
      <?php endforeach; ?>
    </select>
    <?php if ($role === 'state_coordinator' && $selectedStateId > 0): ?><input type="hidden" name="state_id" value="<?= (int) $selectedStateId ?>"><?php endif; ?>
  </label>
  <label>Local Government Area
    <select name="lga_id" id="report_lga_id" <?= $selectedStateId <= 0 ? 'disabled' : '' ?>>
      <option value="">All LGAs</option>
      <?php foreach ($lgaOptions as $lga): ?>
        <option value="<?= (int) $lga['id'] ?>" <?= $selectedLgaId === (int) $lga['id'] ? 'selected' : '' ?>><?= e((string) $lga['lga_name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Start
    <input type="date" name="start_date" value="<?= e($startDate) ?>">
  </label>
  <label>End
    <input type="date" name="end_date" value="<?= e($endDate) ?>">
  </label>
  <button type="submit">Refresh Intelligence</button>
  <a class="button secondary" href="?<?= e(http_build_query(array_merge($_GET, ['report' => $selectedReport, 'format' => 'csv']))) ?>">Export CSV</a>
</form>

<section class="report-shell">
  <aside class="panel">
    <h2>Reportables By Role</h2>
    <p class="muted">Your current role is <strong><?= e(ucwords(str_replace('_', ' ', $role))) ?></strong>. These are the report packs enabled for this role.</p>
    <div class="report-list">
      <?php foreach ($catalog as $key => $item): ?>
        <a class="<?= $selectedReport === $key ? 'active' : '' ?>" href="?<?= e(http_build_query(array_merge($_GET, ['report' => $key, 'format' => null]))) ?>">
          <strong><?= e($item[0]) ?></strong><br>
          <span class="muted"><?= e($item[1]) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </aside>

  <div>
    <section class="stats" style="margin-top:0;">
      <div class="stat"><div class="metric"><?= number_format($farmersTotal) ?></div><strong>Growers</strong><div class="kpi-small"><?= number_format($farmersNew) ?> new in period</div></div>
      <div class="stat"><div class="metric"><?= number_format($farmsTotal) ?></div><strong>Farms</strong><div class="kpi-small"><?= number_format($hectares, 1) ?> hectares</div></div>
      <div class="stat"><div class="metric"><?= $accreditationRate ?>%</div><strong>Accredited</strong><div class="kpi-small"><?= number_format($accredited) ?> accredited growers</div></div>
      <div class="stat"><div class="metric"><?= number_format($pendingDocs) ?></div><strong>Document Queue</strong><div class="kpi-small">Pending or needs review</div></div>
      <div class="stat"><div class="metric"><?= number_format($fieldVisits) ?></div><strong>Field Visits</strong><div class="kpi-small"><?= number_format($pendingTasks) ?> open tasks</div></div>
      <div class="stat"><div class="metric"><?= report_money($orderValue) ?></div><strong>Marketplace Value</strong><div class="kpi-small"><?= number_format($orderCount) ?> orders in period</div></div>
    </section>

    <section class="grid intelligence-grid">
      <?php foreach ($insights as $insight): ?>
        <article class="card signal <?= e($insight[0]) ?>">
          <h2><?= e($insight[1]) ?></h2>
          <p class="muted"><?= e($insight[2]) ?></p>
        </article>
      <?php endforeach; ?>
    </section>

    <section class="panel" style="margin-top:18px;">
      <h2><?= e($catalog[$selectedReport][0]) ?> Detail</h2>
      <?php if (in_array($selectedReport, ['executive', 'state'], true)): ?>
        <table>
          <thead><tr><th><?= e($areaLabel) ?></th><th>Growers</th><th>Farms</th><th>Hectares</th><th>Accredited</th><th>Accreditation Rate</th></tr></thead>
          <tbody>
          <?php foreach ($stateRows as $row): ?>
            <tr><td><strong><?= e($row['area_name']) ?></strong></td><td><?= (int) $row['farmers'] ?></td><td><?= (int) $row['farms'] ?></td><td><?= number_format((float) $row['hectares'], 1) ?></td><td><?= (int) $row['accredited'] ?></td><td><?= report_pct((float) $row['accredited'], (float) $row['farmers']) ?>%</td></tr>
          <?php endforeach; ?>
          <?php if (!$stateRows): ?><tr><td colspan="6">No state intelligence available yet.</td></tr><?php endif; ?>
          </tbody>
        </table>
      <?php elseif ($selectedReport === 'provider'): ?>
        <table><thead><tr><th>Provider Type</th><th>Status</th><th>Total</th></tr></thead><tbody>
          <?php foreach ($providerRows as $row): ?><tr><td><?= e(ucwords(str_replace('_', ' ', (string) $row['provider_type']))) ?></td><td><?= e(ucwords(str_replace('_', ' ', (string) $row['status']))) ?></td><td><?= (int) $row['total'] ?></td></tr><?php endforeach; ?>
          <?php if (!$providerRows): ?><tr><td colspan="3">No provider records yet.</td></tr><?php endif; ?>
        </tbody></table>
      <?php elseif (in_array($selectedReport, ['marketplace', 'finance'], true)): ?>
        <table><thead><tr><th>Seller</th><th>Type</th><th>Listings</th><th>Order Value</th></tr></thead><tbody>
          <?php foreach ($marketRows as $row): ?><tr><td><strong><?= e($row['store_name']) ?></strong></td><td><?= e(ucwords(str_replace('_', ' ', (string) $row['seller_type']))) ?></td><td><?= (int) $row['listings'] ?></td><td><?= report_money((float) $row['order_value']) ?></td></tr><?php endforeach; ?>
          <?php if (!$marketRows): ?><tr><td colspan="4">No marketplace records yet.</td></tr><?php endif; ?>
        </tbody></table>
        <p class="muted">Wallet funding/spend in this period: <strong><?= e(report_money($walletVolume)) ?></strong>. Budget spent: <strong><?= e(report_money($spent)) ?></strong> of <?= e(report_money($budgeted)) ?>.</p>
      <?php elseif ($selectedReport === 'support'): ?>
        <table><thead><tr><th>Category</th><th>Priority</th><th>Status</th><th>Total</th></tr></thead><tbody>
          <?php foreach ($supportRows as $row): ?><tr><td><?= e(ucwords(str_replace('_', ' ', (string) $row['category']))) ?></td><td><?= e(ucwords((string) $row['priority'])) ?></td><td><?= e(ucwords(str_replace('_', ' ', (string) $row['status']))) ?></td><td><?= (int) $row['total'] ?></td></tr><?php endforeach; ?>
          <?php if (!$supportRows): ?><tr><td colspan="4">No support messages yet.</td></tr><?php endif; ?>
        </tbody></table>
      <?php elseif ($selectedReport === 'field'): ?>
        <table><thead><tr><th>Agent</th><th>Visits In Period</th><th>Open Tasks</th></tr></thead><tbody>
          <?php foreach ($fieldRows as $row): ?><tr><td><strong><?= e($row['agent']) ?></strong></td><td><?= (int) $row['visits'] ?></td><td><?= (int) $row['open_tasks'] ?></td></tr><?php endforeach; ?>
          <?php if (!$fieldRows): ?><tr><td colspan="3">No field team activity yet.</td></tr><?php endif; ?>
        </tbody></table>
      <?php else: ?>
        <table><thead><tr><th>Metric</th><th>Value</th><th>Meaning</th></tr></thead><tbody>
          <tr><td>Grower accreditation</td><td><?= $accreditationRate ?>%</td><td>Readiness for certification and formal participation.</td></tr>
          <tr><td>Farm verification</td><td><?= $verificationRate ?>%</td><td>Ground-truth confidence across captured farms.</td></tr>
          <tr><td>Provider coverage</td><td><?= number_format($providersApproved) ?> approved / <?= number_format($providersPending) ?> pending</td><td>Input and service ecosystem strength.</td></tr>
          <tr><td>Marketplace depth</td><td><?= number_format($sellerCount) ?> sellers / <?= number_format($listingCount) ?> listings</td><td>Seller supply available to buyers.</td></tr>
          <tr><td>Open support</td><td><?= number_format($openSupport) ?></td><td>Stakeholder unresolved communication load.</td></tr>
        </tbody></table>
      <?php endif; ?>
    </section>
  </div>
</section>
<script>
(function () {
  const stateSelect = document.getElementById('report_state_id');
  const lgaSelect = document.getElementById('report_lga_id');
  if (!stateSelect || !lgaSelect) return;

  if (stateSelect.dataset.locked === '1') {
    stateSelect.addEventListener('mousedown', (event) => event.preventDefault());
    stateSelect.addEventListener('keydown', (event) => event.preventDefault());
  }

  async function loadLgas() {
    const stateId = stateSelect.value;
    lgaSelect.innerHTML = '<option value="">All LGAs</option>';
    lgaSelect.disabled = !stateId;
    if (!stateId) return;
    lgaSelect.innerHTML = '<option value="">Loading LGAs...</option>';
    try {
      const response = await fetch('../api/get-lgas.php?state_id=' + encodeURIComponent(stateId));
      const payload = await response.json();
      const items = payload.items || [];
      lgaSelect.innerHTML = '<option value="">All LGAs</option>';
      items.forEach((item) => {
        const option = document.createElement('option');
        option.value = item.id;
        option.textContent = item.lga_name;
        lgaSelect.appendChild(option);
      });
    } catch (error) {
      lgaSelect.innerHTML = '<option value="">Unable to load LGAs</option>';
    }
  }

  stateSelect.addEventListener('change', loadLgas);
})();
</script>
<?php admin_page_end(); ?>
