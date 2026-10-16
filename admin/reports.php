<?php
declare(strict_types=1);

if (!defined('NATCODEV_REPORTS_LEGACY')) {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        $query = $_GET;
        $query['page'] = preg_replace('/[^a-z_-]/', '', (string) ($query['page'] ?? 'overview')) ?: 'overview';
        header('Location: reports/?' . http_build_query($query), true, 302);
        exit;
    }
    define('NATCODEV_REPORTS_LEGACY', true);
}

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../lib/platform-governance.php';
require_once __DIR__ . '/../lib/marketplace.php';
require_once __DIR__ . '/../coordination/_coordination.php';

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

function report_csv(string $filename, array $rows): void
{
    app_export_csv($filename, $rows ? array_keys($rows[0]) : [], $rows);
}

function report_fill_series(array $rows, int $days, string $valueField = 'c'): array
{
    $map = [];
    foreach ($rows as $row) {
        $key = (string) ($row['d'] ?? '');
        if ($key === '') {
            continue;
        }
        $map[$key] = $valueField === 'c' ? (int) ($row['c'] ?? 0) : (float) ($row[$valueField] ?? 0);
    }
    $labels = [];
    $values = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $ts = strtotime("-{$i} days");
        $labels[] = date('M j', $ts);
        $values[] = $map[date('Y-m-d', $ts)] ?? 0;
    }

    return [$labels, $values];
}

function report_fill_monthly(array $rows, int $months, string $valueField = 'c'): array
{
    $map = [];
    foreach ($rows as $row) {
        $key = (string) ($row['m'] ?? '');
        if ($key === '') {
            continue;
        }
        $map[$key] = $valueField === 'c' ? (int) ($row['c'] ?? 0) : (float) ($row[$valueField] ?? 0);
    }
    $labels = [];
    $values = [];
    for ($i = $months - 1; $i >= 0; $i--) {
        $ts = strtotime("-{$i} months");
        $labels[] = date('M Y', $ts);
        $values[] = $map[date('Y-m', $ts)] ?? 0;
    }

    return [$labels, $values];
}

function report_subpages(): array
{
    return [
        'overview' => ['Executive Overview', 'National performance, growth, adoption, marketplace, finance, and risk signals.', 'fa-chart-pie'],
        'growers' => ['Grower & Farm Intelligence', 'Registration, documents, farms, accreditation, geography, and farm health.', 'fa-seedling'],
        'marketplace' => ['Marketplace Intelligence', 'Sellers, listings, orders, revenue, buyer demand, and seller performance.', 'fa-store'],
        'finance' => ['Finance & Wallet', 'Wallet volume, budget burn, transaction mix, and daily cash movement.', 'fa-wallet'],
        'field' => ['Field Operations', 'Visits, tasks, verification coverage, purpose mix, and agent workload.', 'fa-map-marked-alt'],
        'providers' => ['Provider Ecosystem', 'Provider approvals, types, offerings, geography, and onboarding backlog.', 'fa-truck'],
        'support' => ['Support & Communications', 'Ticket queues, priority risk, category load, and resolution status.', 'fa-headset'],
        'compliance' => ['Compliance & Accreditation', 'Document verification queue, accreditation coverage, and audit posture.', 'fa-shield-halved'],
        'coordinator_coverage' => ['Coordinator Coverage', 'Tiers, enrollment pipeline, and scope coverage gaps.', 'fa-users-crown'],
    ];
}

function report_subpages_for_role(string $role): array
{
    return match ($role) {
        'state_coordinator', 'lga_coordinator', 'district_coordinator', 'village_coordinator' => ['growers', 'field', 'providers', 'support', 'compliance'],
        'national_coordinator', 'regional_coordinator' => array_keys(report_subpages()),
        'field_agent', 'agronomist', 'agric_extensionist' => ['field', 'growers', 'support'],
        'investor' => ['overview', 'marketplace', 'finance'],
        default => array_keys(report_subpages()),
    };
}

$role = admin_current_platform_role($pdo) ?: 'admin';
$reportCatalog = report_subpages();
$subpageKeys = report_subpages_for_role($role);
$legacyAliases = ['executive' => 'overview', 'state' => 'growers', 'grower' => 'growers', 'provider' => 'providers'];
$requested = strtolower(trim((string) ($_GET['page'] ?? $_GET['report'] ?? 'overview')));
$requested = $legacyAliases[$requested] ?? $requested;
$subpage = in_array($requested, $subpageKeys, true) ? $requested : $subpageKeys[0];

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

// Coordinator enclave scoping — only narrows, never widens
$coordScopeSql = report_coord_scope_sql($pdo, $currentReportUser);
if ($coordScopeSql['sql'] !== '') {
    $locationFilterSql .= ' AND ' . $coordScopeSql['sql'];
    $locationFilterParams = array_merge($locationFilterParams, $coordScopeSql['params']);
}

$farmersTotal = report_count($pdo, "
    SELECT COUNT(DISTINCT u.id)
    FROM users u
    LEFT JOIN applications a ON a.id = u.application_id
    LEFT JOIN grower_farms gf ON gf.user_id = u.id AND gf.deleted_at IS NULL
    LEFT JOIN nigeria_states ns ON ns.id = COALESCE(gf.state_id, a.state_id)
    LEFT JOIN nigeria_lgas nl ON nl.id = COALESCE(gf.lga_id, a.lga_id)
    WHERE u.role = 'grower' AND {$locationFilterSql}
", $locationFilterParams);
$farmersNew = report_count($pdo, "
    SELECT COUNT(DISTINCT u.id)
    FROM users u
    LEFT JOIN applications a ON a.id = u.application_id
    LEFT JOIN grower_farms gf ON gf.user_id = u.id AND gf.deleted_at IS NULL
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
    WHERE {$locationFilterSql} AND gf.deleted_at IS NULL
", $locationFilterParams);
$hectares = report_sum($pdo, "
    SELECT COALESCE(SUM(gf.farm_size), 0)
    FROM grower_farms gf
    JOIN users u ON u.id = gf.user_id
    LEFT JOIN applications a ON a.id = u.application_id
    LEFT JOIN nigeria_states ns ON ns.id = COALESCE(gf.state_id, a.state_id)
    LEFT JOIN nigeria_lgas nl ON nl.id = COALESCE(gf.lga_id, a.lga_id)
    WHERE {$locationFilterSql} AND gf.deleted_at IS NULL
", $locationFilterParams);
$accredited = report_count($pdo, "
    SELECT COUNT(DISTINCT u.id)
    FROM users u
    LEFT JOIN applications a ON a.id = u.application_id
    LEFT JOIN grower_farms gf ON gf.user_id = u.id AND gf.deleted_at IS NULL
    LEFT JOIN nigeria_states ns ON ns.id = COALESCE(gf.state_id, a.state_id)
    LEFT JOIN nigeria_lgas nl ON nl.id = COALESCE(gf.lga_id, a.lga_id)
    WHERE u.role = 'grower' AND COALESCE(u.accreditation_status, 'not_accredited') = 'accredited' AND {$locationFilterSql}
", $locationFilterParams);
$pendingDocs = report_count($pdo, "
    SELECT COUNT(DISTINCT dr.id)
    FROM document_requirements dr
    JOIN users u ON u.id = dr.user_id
    LEFT JOIN applications a ON a.id = u.application_id
    LEFT JOIN grower_farms gf ON gf.user_id = u.id AND gf.deleted_at IS NULL
    LEFT JOIN nigeria_states ns ON ns.id = COALESCE(gf.state_id, a.state_id)
    LEFT JOIN nigeria_lgas nl ON nl.id = COALESCE(gf.lga_id, a.lga_id)
    WHERE dr.verification_status IN ('pending','needs_review') AND dr.deleted_at IS NULL AND {$locationFilterSql}
", $locationFilterParams);
$openSupport = report_count($pdo, "
    SELECT COUNT(DISTINCT m.id)
    FROM messages m
    JOIN users u ON u.id = m.user_id
    LEFT JOIN applications a ON a.id = u.application_id
    LEFT JOIN grower_farms gf ON gf.user_id = u.id AND gf.deleted_at IS NULL
    LEFT JOIN nigeria_states ns ON ns.id = COALESCE(gf.state_id, a.state_id)
    LEFT JOIN nigeria_lgas nl ON nl.id = COALESCE(gf.lga_id, a.lga_id)
    WHERE m.status IN ('open','in_progress') AND {$locationFilterSql}
", $locationFilterParams);
$fieldVisits = report_count($pdo, "
    SELECT COUNT(DISTINCT fv.id)
    FROM farm_visits fv
    JOIN grower_farms gf ON gf.id = fv.farm_id AND gf.deleted_at IS NULL
    JOIN users u ON u.id = gf.user_id
    LEFT JOIN applications a ON a.id = u.application_id
    LEFT JOIN nigeria_states ns ON ns.id = COALESCE(gf.state_id, a.state_id)
    LEFT JOIN nigeria_lgas nl ON nl.id = COALESCE(gf.lga_id, a.lga_id)
    WHERE fv.visited_at BETWEEN ? AND ? AND {$locationFilterSql}
", array_merge($dateParams, $locationFilterParams));
$pendingTasks = report_count($pdo, "
    SELECT COUNT(DISTINCT ft.id)
    FROM field_tasks ft
    JOIN grower_farms gf ON gf.id = ft.farm_id AND gf.deleted_at IS NULL
    JOIN users u ON u.id = gf.user_id
    LEFT JOIN applications a ON a.id = u.application_id
    LEFT JOIN nigeria_states ns ON ns.id = COALESCE(gf.state_id, a.state_id)
    LEFT JOIN nigeria_lgas nl ON nl.id = COALESCE(gf.lga_id, a.lga_id)
    WHERE ft.status IN ('pending','assigned','in_progress') AND {$locationFilterSql}
", $locationFilterParams);
$verifiedFarms = report_count($pdo, "
    SELECT COUNT(DISTINCT fv.id)
    FROM farm_verifications fv
    JOIN grower_farms gf ON gf.id = fv.farm_id AND gf.deleted_at IS NULL
    JOIN users u ON u.id = gf.user_id
    LEFT JOIN applications a ON a.id = u.application_id
    LEFT JOIN nigeria_states ns ON ns.id = COALESCE(gf.state_id, a.state_id)
    LEFT JOIN nigeria_lgas nl ON nl.id = COALESCE(gf.lga_id, a.lga_id)
    WHERE fv.status = 'verified' AND fv.deleted_at IS NULL AND {$locationFilterSql}
", $locationFilterParams);
$providersApproved = report_count($pdo, "SELECT COUNT(*) FROM provider_registry WHERE status IN ('approved','verified') AND deleted_at IS NULL");
$providersPending = report_count($pdo, "SELECT COUNT(*) FROM provider_registry WHERE status IN ('pending','pending_review') AND deleted_at IS NULL");
$sellerCount = report_count($pdo, "SELECT COUNT(*) FROM marketplace_sellers WHERE approval_status = 'approved' AND deleted_at IS NULL");
$listingCount = report_count($pdo, "SELECT COUNT(*) FROM marketplace_listings WHERE approval_status = 'approved' AND deleted_at IS NULL");
$registered = report_count($pdo, "SELECT COUNT(*) FROM webinar_registrations");
$orderCount = report_count($pdo, "SELECT COUNT(*) FROM marketplace_orders WHERE created_at BETWEEN ? AND ?", $dateParams);
$orderValue = report_sum($pdo, "SELECT COALESCE(SUM(total_amount), 0) FROM marketplace_orders WHERE created_at BETWEEN ? AND ?", $dateParams);
$walletVolume = report_sum($pdo, "SELECT COALESCE(SUM(amount), 0) FROM wallet_transactions WHERE status IN ('success','completed','paid') AND created_at BETWEEN ? AND ?", $dateParams);
$budgeted = report_sum($pdo, "SELECT COALESCE(SUM(amount_budgeted), 0) FROM state_budget_records WHERE (? = '' OR state_name = ?)", [$scopeState, $scopeState]);
$spent = report_sum($pdo, "SELECT COALESCE(SUM(amount_spent), 0) FROM state_budget_records WHERE (? = '' OR state_name = ?)", [$scopeState, $scopeState]);

$accreditationRate = report_pct($accredited, $farmersTotal);
$verificationRate = report_pct($verifiedFarms, max(1, $farmsTotal));
$budgetBurn = report_pct($spent, $budgeted);

$prevStart = date('Y-m-d 00:00:00', strtotime('-60 days'));
$prevEnd = date('Y-m-d 00:00:00', strtotime('-31 days'));
$prevOrderCount = report_count($pdo, "SELECT COUNT(*) FROM marketplace_orders WHERE created_at BETWEEN ? AND ?", [$prevStart, $prevEnd]);
$prevOrderValue = report_sum($pdo, "SELECT COALESCE(SUM(total_amount),0) FROM marketplace_orders WHERE created_at BETWEEN ? AND ?", [$prevStart, $prevEnd]);
$prevFarmersNew = report_count($pdo, "SELECT COUNT(DISTINCT u.id) FROM users u WHERE u.role='grower' AND u.created_at BETWEEN ? AND ?", [$prevStart, $prevEnd]);
$prevWalletVolume = report_sum($pdo, "SELECT COALESCE(SUM(amount),0) FROM wallet_transactions WHERE status IN ('success','completed','paid') AND created_at BETWEEN ? AND ?", [$prevStart, $prevEnd]);
$prevFieldVisits = report_count($pdo, "SELECT COUNT(*) FROM farm_visits fv JOIN grower_farms gf ON gf.id=fv.farm_id WHERE fv.visited_at BETWEEN ? AND ?", [$prevStart, $prevEnd]);

$growthRate = fn($curr, $prev) => $prev > 0 ? round((($curr - $prev) / $prev) * 100, 1) : ($curr > 0 ? 100.0 : 0.0);
$enrollGrowth = $growthRate($farmersNew, $prevFarmersNew);
$orderGrowth = $growthRate($orderCount, $prevOrderCount);
$valueGrowth = $growthRate($orderValue, $prevOrderValue);
$walletGrowth = $growthRate($walletVolume, $prevWalletVolume);
$visitGrowth = $growthRate($fieldVisits, $prevFieldVisits);

[$enrollLabels, $enrollCounts] = report_fill_series(report_rows($pdo, "SELECT DATE(created_at) d, COUNT(*) c FROM users WHERE role='grower' AND created_at BETWEEN ? AND ? GROUP BY DATE(created_at)", $dateParams), 30);
[$orderLabels, $orderAmounts] = report_fill_series(report_rows($pdo, "SELECT DATE(created_at) d, COALESCE(SUM(total_amount),0) v FROM marketplace_orders WHERE created_at BETWEEN ? AND ? GROUP BY DATE(created_at)", $dateParams), 30, 'v');
[$visitLabels, $visitCounts] = report_fill_series(report_rows($pdo, "SELECT DATE(fv.visited_at) d, COUNT(*) c FROM farm_visits fv JOIN grower_farms gf ON gf.id = fv.farm_id AND gf.deleted_at IS NULL WHERE fv.visited_at BETWEEN ? AND ? GROUP BY DATE(fv.visited_at)", $dateParams), 30);
[$monthlyLabels, $monthlyOrders] = report_fill_monthly(report_rows($pdo, "SELECT DATE_FORMAT(created_at,'%Y-%m') m, COALESCE(SUM(total_amount),0) v FROM marketplace_orders WHERE created_at >= ? GROUP BY m", [date('Y-m-01', strtotime('-11 months'))]), 12, 'v');
[, $monthlyEnroll] = report_fill_monthly(report_rows($pdo, "SELECT DATE_FORMAT(created_at,'%Y-%m') m, COUNT(*) c FROM users WHERE role='grower' AND created_at >= ? GROUP BY m", [date('Y-m-01', strtotime('-11 months'))]), 12);
[$wlbl, $walletDaily] = report_fill_series(report_rows($pdo, "SELECT DATE(created_at) d, COALESCE(SUM(amount),0) v FROM wallet_transactions WHERE status IN ('success','completed','paid') AND created_at BETWEEN ? AND ? GROUP BY DATE(created_at)", $dateParams), 10, 'v');

$roleBreakdown = report_rows($pdo, "SELECT role, COUNT(*) cnt FROM users GROUP BY role ORDER BY cnt DESC LIMIT 8");
$roleLabels = [];
$roleData = [];
foreach ($roleBreakdown as $r) {
    $roleLabels[] = ucwords(str_replace('_', ' ', (string) $r['role']));
    $roleData[] = (int) $r['cnt'];
}

$accStatus = report_rows($pdo, "
    SELECT COALESCE(accreditation_status, 'not_accredited') status, COUNT(*) cnt
    FROM users WHERE role='grower'
    GROUP BY accreditation_status
    ORDER BY cnt DESC
");
$accLabels = [];
$accData = [];
foreach ($accStatus as $r) {
    $status = $r['status'] ?? 'not_accredited';
    $accLabels[] = ucfirst(str_replace('_', ' ', $status));
    $accData[] = (int) $r['cnt'];
}

$topStates = report_rows($pdo, "
    SELECT ns.state_name, COUNT(DISTINCT u.id) farmers, COUNT(DISTINCT gf.id) farms
    FROM users u
    LEFT JOIN applications a ON a.id = u.application_id
    LEFT JOIN grower_farms gf ON gf.user_id = u.id AND gf.deleted_at IS NULL
    LEFT JOIN nigeria_states ns ON ns.id = COALESCE(gf.state_id, a.state_id)
    WHERE u.role = 'grower' AND ns.state_name IS NOT NULL
    GROUP BY ns.state_name
    ORDER BY farmers DESC
    LIMIT 10
");
$stateLabels = [];
$stateFarmers = [];
$stateFarms = [];
foreach ($topStates as $r) {
    $stateLabels[] = (string) $r['state_name'];
    $stateFarmers[] = (int) $r['farmers'];
    $stateFarms[] = (int) $r['farms'];
}

$walletTypes = report_rows($pdo, "
    SELECT type, status, COUNT(*) cnt, SUM(ABS(amount)) total
    FROM wallet_transactions
    WHERE created_at BETWEEN ? AND ?
    GROUP BY type, status
    ORDER BY cnt DESC
    LIMIT 10
", $dateParams);
$wtLabels = [];
$wtValues = [];
foreach ($walletTypes as $r) {
    $wtLabels[] = ucfirst((string) $r['type']) . ' – ' . ucfirst((string) $r['status']);
    $wtValues[] = (float) $r['total'];
}

$sellerTiers = report_rows($pdo, "
    SELECT ms.seller_type, COUNT(ms.id) sellers, COUNT(ml.id) listings
    FROM marketplace_sellers ms
    LEFT JOIN marketplace_listings ml ON ml.seller_id = ms.id AND ml.deleted_at IS NULL
    WHERE ms.deleted_at IS NULL
    GROUP BY ms.seller_type
    ORDER BY sellers DESC
");
$stLabels = [];
$stSellers = [];
$stListings = [];
foreach ($sellerTiers as $r) {
    $stLabels[] = ucwords((string) $r['seller_type']);
    $stSellers[] = (int) $r['sellers'];
    $stListings[] = (int) $r['listings'];
}

$provStatus = report_rows($pdo, "SELECT status, COUNT(*) cnt FROM provider_registry WHERE deleted_at IS NULL GROUP BY status ORDER BY cnt DESC");
$prLabels = [];
$prData = [];
foreach ($provStatus as $r) {
    $prLabels[] = ucfirst((string) $r['status']);
    $prData[] = (int) $r['cnt'];
}

$visitPurposes = report_rows($pdo, "
    SELECT COALESCE(ft.task_type, fv.result, 'general') purpose, COUNT(*) cnt
    FROM farm_visits fv
    LEFT JOIN field_tasks ft ON ft.id = fv.task_id
    JOIN grower_farms gf ON gf.id = fv.farm_id AND gf.deleted_at IS NULL
    WHERE fv.visited_at BETWEEN ? AND ?
    GROUP BY purpose ORDER BY cnt DESC
    LIMIT 8
", $dateParams);
$vpLabels = [];
$vpData = [];
foreach ($visitPurposes as $r) {
    $vpLabels[] = ucwords(str_replace('_', ' ', (string) $r['purpose']));
    $vpData[] = (int) $r['cnt'];
}

$ticketPriorities = report_rows($pdo, "
    SELECT COALESCE(priority,'normal') priority, COUNT(*) cnt
    FROM messages m WHERE m.status IN ('open','in_progress','resolved')
    GROUP BY priority ORDER BY cnt DESC
");
$tpLabels = [];
$tpData = [];
foreach ($ticketPriorities as $r) {
    $tpLabels[] = ucfirst((string) $r['priority']);
    $tpData[] = (int) $r['cnt'];
}

$orderStatuses = report_rows($pdo, "SELECT payment_status, COUNT(*) cnt FROM marketplace_orders GROUP BY payment_status ORDER BY cnt DESC");
$osLabels = [];
$osData = [];
foreach ($orderStatuses as $r) {
    $osLabels[] = str_replace('_', ' ', (string) $r['payment_status']);
    $osData[] = (int) $r['cnt'];
}

$messageStatusCounts = [
    (int) report_count($pdo, "SELECT COUNT(*) FROM messages WHERE status='open'"),
    (int) report_count($pdo, "SELECT COUNT(*) FROM messages WHERE status='in_progress'"),
    (int) report_count($pdo, "SELECT COUNT(*) FROM messages WHERE status='resolved'"),
];
$supportCategories = report_rows($pdo, "
    SELECT COALESCE(category,'general') category, COUNT(*) cnt
    FROM messages
    GROUP BY category ORDER BY cnt DESC
    LIMIT 8
");
$scLabels = [];
$scData = [];
foreach ($supportCategories as $r) {
    $scLabels[] = ucwords(str_replace('_', ' ', (string) $r['category']));
    $scData[] = (int) $r['cnt'];
}

$docStatusRows = report_rows($pdo, "
    SELECT COALESCE(verification_status,'pending') status, COUNT(*) cnt
    FROM document_requirements
    WHERE deleted_at IS NULL
    GROUP BY verification_status
    ORDER BY cnt DESC
");
$docLabels = [];
$docData = [];
foreach ($docStatusRows as $r) {
    $docLabels[] = ucfirst(str_replace('_', ' ', (string) $r['status']));
    $docData[] = (int) $r['cnt'];
}
$pendingDocQueue = report_rows($pdo, "
    SELECT dr.id, u.name, u.email, dr.document_type, dr.verification_status, dr.uploaded_at
    FROM document_requirements dr
    JOIN users u ON u.id = dr.user_id
    WHERE dr.verification_status IN ('pending','submitted','under_review','needs_review') AND dr.deleted_at IS NULL
    ORDER BY dr.uploaded_at DESC
    LIMIT 25
");
$docVerified = report_count($pdo, "SELECT COUNT(*) FROM document_requirements WHERE verification_status='verified' AND deleted_at IS NULL");
$docTotal = report_count($pdo, "SELECT COUNT(*) FROM document_requirements WHERE deleted_at IS NULL");
$docVerifyRate = report_pct($docVerified, $docTotal);

$areaNameSql = $selectedLgaId > 0 ? 'COALESCE(nl.lga_name, u.location, a.location, "Unspecified")' : 'COALESCE(ns.state_name, a.location, u.location, "Unspecified")';
$areaLabel = $selectedLgaId > 0 ? 'LGA' : 'State';

$marketRows = report_rows($pdo, "
    SELECT ms.store_name, ms.seller_type, COUNT(ml.id) listings, COALESCE(SUM(mo.total_amount), 0) order_value
    FROM marketplace_sellers ms
    LEFT JOIN marketplace_listings ml ON ml.seller_id = ms.id AND ml.deleted_at IS NULL
    LEFT JOIN marketplace_orders mo ON mo.seller_id = ms.id AND mo.created_at BETWEEN ? AND ?
    WHERE ms.deleted_at IS NULL
    GROUP BY ms.id, ms.store_name, ms.seller_type
    ORDER BY order_value DESC, listings DESC
    LIMIT 12
", $dateParams);

$stateRows = report_rows($pdo, "
    SELECT {$areaNameSql} area_name,
           COUNT(DISTINCT u.id) farmers,
           COUNT(DISTINCT gf.id) farms,
           COALESCE(SUM(gf.farm_size), 0) hectares,
           SUM(CASE WHEN COALESCE(u.accreditation_status, 'not_accredited') = 'accredited' THEN 1 ELSE 0 END) accredited
    FROM users u
    LEFT JOIN applications a ON a.id = u.application_id
    LEFT JOIN grower_farms gf ON gf.user_id = u.id AND gf.deleted_at IS NULL
    LEFT JOIN nigeria_states ns ON ns.id = COALESCE(gf.state_id, a.state_id)
    LEFT JOIN nigeria_lgas nl ON nl.id = COALESCE(gf.lga_id, a.lga_id)
    WHERE u.role = 'grower' AND {$locationFilterSql}
    GROUP BY {$areaNameSql}
    ORDER BY farmers DESC, area_name
    LIMIT 20
", $locationFilterParams);

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

$providerRows = report_rows($pdo, "
    SELECT provider_type, status, COUNT(*) total
    FROM provider_registry
    WHERE deleted_at IS NULL
    GROUP BY provider_type, status
    ORDER BY provider_type, status
");

$supportRows = report_rows($pdo, "
    SELECT category, priority, status, COUNT(*) total
    FROM messages
    GROUP BY category, priority, status
    ORDER BY FIELD(priority, 'high','medium','low'), total DESC
    LIMIT 14
");

$moduleMap = [
    'overview' => 'executive.php',
    'growers' => 'state.php',
    'marketplace' => 'marketplace.php',
    'finance' => 'finance.php',
    'field' => 'field.php',
    'providers' => 'provider.php',
    'support' => 'support.php',
    'compliance' => 'default.php',
    'coordinator_coverage' => 'coordinator-coverage.php',
];
$moduleFile = __DIR__ . '/reports/modules/' . ($moduleMap[$subpage] ?? 'default.php');
if (!is_file($moduleFile)) {
    $moduleFile = __DIR__ . '/reports/modules/default.php';
}
require $moduleFile;

$marketRows = is_array($marketRows ?? null) ? $marketRows : [];
$stateRows = is_array($stateRows ?? null) ? $stateRows : [];
$fieldRows = is_array($fieldRows ?? null) ? $fieldRows : [];
$providerRows = is_array($providerRows ?? null) ? $providerRows : [];
$supportRows = is_array($supportRows ?? null) ? $supportRows : [];
$exportRows = is_array($exportRows ?? null) ? $exportRows : [];

$reportTileLayout = [
    ['Kebbi', 0, 0], ['Sokoto', 1, 0], ['Zamfara', 2, 0], ['Katsina', 3, 0], ['Kano', 4, 0], ['Jigawa', 5, 0], ['Yobe', 6, 0], ['Borno', 7, 0],
    ['Niger', 1, 1], ['Kaduna', 2, 1], ['Bauchi', 4, 1], ['Gombe', 5, 1], ['Taraba', 6, 1], ['Adamawa', 7, 1],
    ['Kwara', 0, 2], ['Federal Capital Territory', 1, 2], ['Kogi', 2, 2], ['Nasarawa', 3, 2], ['Plateau', 4, 2], ['Benue', 5, 2],
    ['Lagos', 1, 3], ['Ogun', 2, 3], ['Oyo', 3, 3], ['Osun', 4, 3], ['Ekiti', 5, 3], ['Ondo', 6, 3],
    ['Edo', 3, 4], ['Delta', 4, 4], ['Enugu', 5, 4], ['Anambra', 6, 4], ['Imo', 7, 4],
    ['Bayelsa', 4, 5], ['Rivers', 5, 5], ['Abia', 6, 5], ['Ebonyi', 7, 5],
    ['Akwa Ibom', 6, 6], ['Cross River', 7, 6],
];
$stateGrowerCounts = report_rows($pdo, "
    SELECT COALESCE(ns.state_name, '') state_name, COUNT(DISTINCT u.id) farmers
    FROM users u
    LEFT JOIN applications a ON a.id = u.application_id
    LEFT JOIN grower_farms gf ON gf.user_id = u.id AND gf.deleted_at IS NULL
    LEFT JOIN nigeria_states ns ON ns.id = COALESCE(gf.state_id, a.state_id)
    WHERE u.role = 'grower' AND ns.state_name IS NOT NULL AND ns.state_name <> ''
    GROUP BY ns.state_name
");
$tileGrowerCounts = [];
$tileStateIds = [];
foreach ($stateGrowerCounts as $row) {
    $tileGrowerCounts[mb_strtolower(trim((string) $row['state_name']))] = (int) $row['farmers'];
}
foreach ($states as $stateRow) {
    $tileStateIds[mb_strtolower(trim((string) $stateRow['state_name']))] = (int) $stateRow['id'];
}
$tileLookup = static function (string $name) use ($tileGrowerCounts, $tileStateIds): array {
    $key = mb_strtolower(trim($name));
    $alt = $key === 'fct' ? 'federal capital territory' : ($key === 'federal capital territory' ? 'fct' : $key);
    return [
        $tileGrowerCounts[$key] ?? $tileGrowerCounts[$alt] ?? 0,
        $tileStateIds[$key] ?? $tileStateIds[$alt] ?? 0,
    ];
};
$tileDisplayName = static fn(string $name): string => $name === 'Federal Capital Territory' ? 'FCT' : $name;
$maxTileFarmers = max(1, max(array_values($tileGrowerCounts) ?: [0]));

if (($_GET['format'] ?? '') === 'csv') {
    report_csv('natcodev-' . $subpage . '-report-' . date('Ymd') . '.csv', $exportRows);
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

$sidebarQuery = $_GET;
unset($sidebarQuery['page'], $sidebarQuery['report'], $sidebarQuery['format']);
$exportHref = '?' . e(http_build_query($sidebarQuery + ['page' => $subpage, 'format' => 'csv']));

$subpageMeta = $reportCatalog[$subpage];
admin_page_start('Reporting Intelligence', [
    'active' => 'reports/',
    'description' => 'Smart, role-aware reporting across growers, field teams, providers, marketplace, finance, support, and compliance.',
    'wide' => true,
    'topbar_only' => true,
    'breadcrumbs' => [
        ['label' => 'Insights & Reports'],
        ['label' => 'Reporting Intelligence'],
    ],
    'head_html' => '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"><link rel="stylesheet" href="../../assets/css/admin-workspaces.css">',
    'css' => '
      .rp-shell,.rep-shell{display:contents}
      .rep-rail,.rp-side{background:linear-gradient(180deg,#074b2a,#003719);color:#fff;padding:24px 18px}
      .rep-rail a,.rp-side a{color:#d9e9e2;text-decoration:none;padding:11px 12px;border-radius:8px;display:flex;align-items:center;gap:10px;font-weight:600}
      .rep-rail a.active,.rep-rail a:hover,.rp-side a.active,.rp-side a:hover{background:#28644d;color:#fff}
      .rep-rail a.active *,.rp-side a.active *{color:#fff}
      .rep-main,.rp-main{padding:28px;min-width:0}
      .rp-brand{display:flex;align-items:center;gap:10px;padding-bottom:14px;margin-bottom:12px;border-bottom:1px solid rgba(255,255,255,.14);color:#fff}
      .rp-brand img{width:44px;height:44px;border-radius:50%;background:#fff;object-fit:contain;padding:4px}
      .rp-brand strong{display:block;line-height:1.1}
      .rp-brand span{display:block;margin-top:3px;color:#dff5e8;font-size:.72rem;font-weight:750}
      .rp-label{margin:14px 4px 7px;color:#aee4c4;font-size:.7rem;font-weight:950;text-transform:uppercase;letter-spacing:.03em}
      .rep-rail nav,.rp-side nav{display:grid;gap:5px}
      .rep-rail a small,.rp-side a small{display:block;font-size:.68rem;font-weight:500;color:inherit;opacity:.75;margin-top:1px}
      .rp-kpi{border-top:4px solid var(--workspace-green-600,#198754)}
      .rp-geo-dot{width:var(--size,24px);height:var(--size,24px);border-radius:50%;display:inline-flex;align-items:center;justify-content:center;color:#fff;font-size:.65rem;font-weight:800;border:2px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,.15)}
      .rp-tilemap{display:grid;grid-template-columns:repeat(8,minmax(0,1fr));gap:6px}
      .rp-tile{border:1px solid rgba(16,24,40,.10);border-radius:8px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:1px;padding:5px 2px;text-decoration:none;transition:transform .12s ease,box-shadow .12s ease;min-width:0;min-height:56px}
      .rp-tile:hover{transform:translateY(-2px);box-shadow:0 6px 16px rgba(16,24,40,.18);z-index:1}
      .rp-tile-name{font-size:.6rem;font-weight:800;line-height:1.05;text-align:center;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:100%;opacity:.92}
      .rp-tile-value{font-size:.74rem;font-weight:900;font-variant-numeric:tabular-nums}
      .rp-tile-selected{outline:3px solid #f79009;outline-offset:1px;box-shadow:0 0 0 2px #fff}
      .rp-tile-legend{display:inline-block;width:130px;height:10px;border-radius:999px;background:linear-gradient(90deg,#e8f6ec,#074b2a);border:1px solid rgba(16,24,40,.10)}
      @media(max-width:768px){.rp-tilemap{gap:4px}.rp-tile{min-height:48px}.rp-tile-name{font-size:.52rem}.rp-tile-value{font-size:.64rem}}
      .rp-meter{background:#e2e8f0;border-radius:999px;height:24px;overflow:hidden}
      .rp-meter-fill{background:linear-gradient(90deg,#0f6b3c,#2374c6);height:100%;border-radius:999px;display:flex;align-items:center;justify-content:flex-end;padding-right:10px;color:#fff;font-size:.8rem;font-weight:800}
      @media(max-width:1024px){.rp-shell,.rep-shell{display:block}.rep-main,.rp-main{padding:16px}}
    ',
]);
?>
<div class="rep-shell rp-shell">
  <aside class="rep-rail rp-side" aria-label="Reporting workspace navigation">
    <div class="rp-brand"><img src="<?= e(app_admin_logo_url()) ?>" alt="NATCODEV"><div><strong>NATCODEV</strong><span>Reporting Intelligence</span></div></div>
    <div class="rp-label">Report Modules</div>
    <nav aria-label="Report modules">
      <?php foreach ($subpageKeys as $key): $meta = $reportCatalog[$key]; ?><a class="<?= $subpage === $key ? 'active' : '' ?>" href="?<?= e(http_build_query($sidebarQuery + ['page' => $key])) ?>"><i class="fas <?= e($meta[2]) ?>"></i><span><?= e($meta[0]) ?><small><?= e($meta[1]) ?></small></span></a><?php endforeach; ?>
    </nav>
    <div class="rp-label">Exits</div>
    <nav aria-label="Reporting exits">
      <a href="../index.php"><i class="fas fa-gauge-high"></i><span>Workspace Hub</span></a>
      <a href="../analytics.php"><i class="fas fa-chart-line"></i><span>Analytics Console</span></a>
      <a href="../dashboard/"><i class="fas fa-house"></i><span>Dashboard</span></a>
    </nav>
  </aside>

  <div class="rep-main rp-main">
    <form method="get" class="card card-body mb-4">
      <input type="hidden" name="page" value="<?= e($subpage) ?>">
      <div class="row g-2 align-items-end">
        <div class="col-md-3"><label class="form-label">State</label><select class="form-select" name="state_id" id="dash_state_id"><option value="">All states</option><?php foreach ($states as $s): ?><option value="<?= (int) $s['id'] ?>" <?= $selectedStateId === (int) $s['id'] ? 'selected' : '' ?>><?= e((string) $s['state_name']) ?></option><?php endforeach; ?></select></div>
        <?php if ($role !== 'state_coordinator'): ?>
        <div class="col-md-3"><label class="form-label">LGA</label><select class="form-select" name="lga_id" id="dash_lga_id" <?= !$selectedStateId ? 'disabled' : '' ?>><option value="">All LGAs</option><?php foreach ($lgaOptions as $l): ?><option value="<?= (int) $l['id'] ?>" <?= $selectedLgaId === (int) $l['id'] ? 'selected' : '' ?>><?= e((string) $l['lga_name']) ?></option><?php endforeach; ?></select></div>
        <?php endif; ?>
        <div class="col-md-2"><label class="form-label">From</label><input class="form-control" type="date" name="start_date" value="<?= e($startDate) ?>"></div>
        <div class="col-md-2"><label class="form-label">To</label><input class="form-control" type="date" name="end_date" value="<?= e($endDate) ?>"></div>
        <div class="col-md-2 d-flex gap-2"><button class="btn btn-success w-100" type="submit"><i class="fas fa-filter"></i> Refresh</button></div>
      </div>
    </form>

    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
      <div>
        <span class="small text-success fw-bold text-uppercase"><?= e($subpageMeta[0]) ?></span>
        <h1 class="h3 mb-1"><?= e($subpageMeta[0]) ?></h1>
        <p class="text-secondary mb-0"><?= e($subpageMeta[1]) ?><?= $scopeState !== '' ? ' · Scope: ' . e($scopeState) : '' ?><?= $selectedLgaName !== '' ? ' / ' . e($selectedLgaName) : '' ?>.</p>
      </div>
      <div class="d-flex gap-2">
        <a class="btn btn-outline-success" href="<?= $exportHref ?>"><i class="fas fa-download"></i> Export CSV</a>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <script>
      Chart.defaults.font.family = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
      Chart.defaults.font.size = 12;
      Chart.defaults.color = '#64748b';
      window.rpColors = ['#0f6b3c','#2374c6','#f79009','#d92d20','#8b5cf6','#ec4899','#14b8a6','#64748b','#e11d48','#0891b2','#f59e0b','#10b981'];
      window.rpMoneyTick = function (v) { return '₦' + (v >= 1000 ? (v / 1000).toFixed(0) + 'K' : v); };
    </script>

    <?php require __DIR__ . '/reports/views/' . $subpage . '.php'; ?>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
  const stateSelect = document.getElementById('dash_state_id');
  const lgaSelect = document.getElementById('dash_lga_id');
  if (!stateSelect || !lgaSelect) return;
  async function loadLgas() {
    const stateId = stateSelect.value;
    lgaSelect.innerHTML = '<option value="">All LGAs</option>';
    lgaSelect.disabled = !stateId;
    if (!stateId) return;
    try {
      const resp = await fetch('../../api/get-lgas.php?state_id=' + encodeURIComponent(stateId));
      const data = await resp.json();
      (data.items || []).forEach(function(item) {
        const opt = document.createElement('option');
        opt.value = item.id;
        opt.textContent = item.lga_name;
        lgaSelect.appendChild(opt);
      });
    } catch(e) {
      lgaSelect.innerHTML = '<option value="">Error loading LGAs</option>';
    }
  }
  stateSelect.addEventListener('change', loadLgas);
  if (stateSelect.value) loadLgas();
});
</script>
<?php admin_page_end(); ?>
