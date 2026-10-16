<?php
declare(strict_types=1);
require_once __DIR__ . '/_coordination.php';

$pdo = coord_pdo();
$user = coord_require($pdo);
$scope = coord_scope_filter($pdo, $user);
$where = $scope['where'];
$params = $scope['params'];
$stateLabel = (string) ($scope['label'] ?: 'National');

// Scoping mechanism: every scoped table is tied to the coordinator's state
// through the owner user's application record (aliased `a`, with `ns` for the
// state name fallback), so the single coord_state_filter() fragment governs
// applications, users, farms, field work, imports, support, and wallet data.
$userScopeJoin = 'LEFT JOIN applications a ON a.id = u.application_id LEFT JOIN nigeria_states ns ON ns.id = a.state_id';
$farmScopeJoin = 'LEFT JOIN applications a ON a.id = gf.application_id LEFT JOIN nigeria_states ns ON ns.id = a.state_id';
$appFrom = coord_app_from();

$registrations = coord_scalar($pdo, "SELECT COUNT(*) FROM {$appFrom} WHERE {$where}", $params);
$activeRegistrations = coord_scalar($pdo, "SELECT COUNT(*) FROM {$appFrom} WHERE {$where} AND " . coord_app_status_expr() . " IN ('active','verified','approved','confirmed')", $params);
$pendingRegistrations = coord_scalar($pdo, "SELECT COUNT(*) FROM {$appFrom} WHERE {$where} AND " . coord_app_status_expr() . " IN ('pending','submitted','under_review','new')", $params);
$usersInScope = coord_scalar($pdo, "SELECT COUNT(DISTINCT u.id) FROM users u {$userScopeJoin} WHERE {$where}", $params);

$farmsTotal = 0;
$farmsGps = 0;
$verificationsPending = 0;
$verificationsVerified = 0;
$fieldOpen = 0;
$fieldCompleted = 0;
$visits30 = 0;
if (app_table_exists($pdo, 'grower_farms')) {
    $farmsTotal = coord_scalar($pdo, "SELECT COUNT(*) FROM grower_farms gf {$farmScopeJoin} WHERE gf.deleted_at IS NULL AND {$where}", $params);
    $farmsGps = coord_scalar($pdo, "SELECT COUNT(*) FROM grower_farms gf {$farmScopeJoin} WHERE gf.deleted_at IS NULL AND gf.latitude IS NOT NULL AND gf.longitude IS NOT NULL AND {$where}", $params);
    if (app_table_exists($pdo, 'farm_verifications')) {
        $verificationsPending = coord_scalar($pdo, "SELECT COUNT(*) FROM farm_verifications fv JOIN grower_farms gf ON gf.id = fv.farm_id AND gf.deleted_at IS NULL {$farmScopeJoin} WHERE fv.deleted_at IS NULL AND fv.status = 'pending' AND {$where}", $params);
        $verificationsVerified = coord_scalar($pdo, "SELECT COUNT(*) FROM farm_verifications fv JOIN grower_farms gf ON gf.id = fv.farm_id AND gf.deleted_at IS NULL {$farmScopeJoin} WHERE fv.deleted_at IS NULL AND fv.status = 'verified' AND {$where}", $params);
    }
    if (app_table_exists($pdo, 'field_tasks')) {
        $fieldOpen = coord_scalar($pdo, "SELECT COUNT(*) FROM field_tasks ft JOIN grower_farms gf ON gf.id = ft.farm_id AND gf.deleted_at IS NULL {$farmScopeJoin} WHERE ft.status IN ('pending','assigned','in_progress') AND {$where}", $params);
        $fieldCompleted = coord_scalar($pdo, "SELECT COUNT(*) FROM field_tasks ft JOIN grower_farms gf ON gf.id = ft.farm_id AND gf.deleted_at IS NULL {$farmScopeJoin} WHERE ft.status = 'completed' AND {$where}", $params);
    }
    if (app_table_exists($pdo, 'farm_visits')) {
        $visits30 = coord_scalar($pdo, "SELECT COUNT(*) FROM farm_visits fv JOIN grower_farms gf ON gf.id = fv.farm_id AND gf.deleted_at IS NULL {$farmScopeJoin} WHERE fv.visited_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND {$where}", $params);
    }
}

$importsTotal = 0;
$importsPending = 0;
if (app_table_exists($pdo, 'user_import_records')) {
    $importsTotal = coord_scalar($pdo, "SELECT COUNT(*) FROM user_import_records ur LEFT JOIN applications a ON a.id = ur.application_id LEFT JOIN nigeria_states ns ON ns.id = a.state_id WHERE ur.deleted_at IS NULL AND {$where}", $params);
    $importsPending = coord_scalar($pdo, "SELECT COUNT(*) FROM user_import_records ur LEFT JOIN applications a ON a.id = ur.application_id LEFT JOIN nigeria_states ns ON ns.id = a.state_id WHERE ur.deleted_at IS NULL AND ur.status = 'pending' AND {$where}", $params);
}

$documentsPending = app_table_exists($pdo, 'document_requirements')
    ? coord_scalar($pdo, "SELECT COUNT(*) FROM document_requirements dr LEFT JOIN users u ON u.id = dr.user_id {$userScopeJoin} WHERE dr.deleted_at IS NULL AND dr.verification_status = 'pending' AND {$where}", $params)
    : 0;
$certificatesIssued = app_table_exists($pdo, 'certificates')
    ? coord_scalar($pdo, "SELECT COUNT(*) FROM certificates c LEFT JOIN applications a ON a.id = c.application_id LEFT JOIN nigeria_states ns ON ns.id = a.state_id WHERE c.deleted_at IS NULL AND c.status = 'issued' AND {$where}", $params)
    : 0;

$supportOpen = coord_scalar($pdo, "SELECT COUNT(*) FROM support_tickets st LEFT JOIN users u ON u.id = st.user_id {$userScopeJoin} WHERE {$where} AND st.status IN ('open','in_progress','waiting_on_user','escalated')", $params);
$supportOverdue = coord_scalar($pdo, "SELECT COUNT(*) FROM support_tickets st LEFT JOIN users u ON u.id = st.user_id {$userScopeJoin} WHERE {$where} AND st.status IN ('open','in_progress','waiting_on_user','escalated') AND st.sla_due_at IS NOT NULL AND st.sla_due_at < NOW()", $params);

$walletTransactions = coord_scalar($pdo, "SELECT COUNT(*) FROM wallet_transactions wt LEFT JOIN users u ON u.id = wt.user_id {$userScopeJoin} WHERE {$where}", $params);
$inflowRow = coord_rows($pdo, "SELECT COALESCE(SUM(wt.amount), 0) AS total FROM wallet_transactions wt LEFT JOIN users u ON u.id = wt.user_id {$userScopeJoin} WHERE {$where} AND wt.direction = 'inflow'", $params);
$outflowRow = coord_rows($pdo, "SELECT COALESCE(SUM(wt.amount), 0) AS total FROM wallet_transactions wt LEFT JOIN users u ON u.id = wt.user_id {$userScopeJoin} WHERE {$where} AND wt.direction = 'outflow'", $params);
$inflow = (float) ($inflowRow[0]['total'] ?? 0);
$outflow = (float) ($outflowRow[0]['total'] ?? 0);
$withdrawalsPending = coord_scalar($pdo, "SELECT COUNT(*) FROM wallet_withdrawals wd LEFT JOIN users u ON u.id = wd.user_id {$userScopeJoin} WHERE {$where} AND wd.status IN ('pending','processing')", $params);

$metrics = [
    ['Registrations in scope', number_format($registrations), 'applications mapped to ' . $stateLabel],
    ['Active / verified registrations', number_format($activeRegistrations), 'confirmed registry records'],
    ['Pending review registrations', number_format($pendingRegistrations), 'awaiting review in this scope'],
    ['Registered users in scope', number_format($usersInScope), 'users linked to scoped applications'],
    ['Grower farms', number_format($farmsTotal), 'active farm profiles'],
    ['Farms with GPS capture', number_format($farmsGps), 'latitude and longitude recorded'],
    ['Pending farm verifications', number_format($verificationsPending), 'field verification queue'],
    ['Verified farms', number_format($verificationsVerified), 'field-verified records'],
    ['Open field tasks', number_format($fieldOpen), 'pending, assigned, or in progress'],
    ['Completed field tasks', number_format($fieldCompleted), 'finished field work'],
    ['Farm visits (30 days)', number_format($visits30), 'logged field visits'],
    ['Import records in scope', number_format($importsTotal), 'bulk import rows linked to scope'],
    ['Import records pending engagement', number_format($importsPending), 'awaiting grower confirmation'],
    ['Pending document verifications', number_format($documentsPending), 'identity / land document queue'],
    ['Certificates issued', number_format($certificatesIssued), 'issued grower certificates'],
    ['Open support tickets', number_format($supportOpen), 'open, in progress, waiting, escalated'],
    ['SLA-overdue tickets', number_format($supportOverdue), 'past their SLA due time'],
    ['Wallet transactions', number_format($walletTransactions), 'ledger entries for scoped users'],
    ['Wallet inflow', 'NGN ' . number_format($inflow, 2), 'credits to scoped wallets'],
    ['Wallet outflow', 'NGN ' . number_format($outflow, 2), 'debits from scoped wallets'],
    ['Pending withdrawal requests', number_format($withdrawalsPending), 'withdrawals awaiting payout'],
];

$recent = coord_rows($pdo, "SELECT a.app_ref, a.name, " . coord_app_state_expr() . " state, " . coord_app_lga_expr() . " lga, " . coord_app_status_expr() . " status, a.created_at FROM {$appFrom} WHERE {$where} ORDER BY a.created_at DESC LIMIT 12", $params);

// CSV export: POST + CSRF protected, streamed with app_export_csv() (field-agent/reports.php pattern).
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && (string) ($_POST['export'] ?? '') === 'scope_report') {
    if (!verify_csrf((string) ($_POST['_csrf'] ?? ''))) {
        http_response_code(403);
        exit('Invalid CSRF token.');
    }
    $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $stateLabel), '-')) ?: 'national';
    $exportRows = [];
    foreach ($metrics as $metric) {
        $exportRows[] = ['section' => 'Scope summary', 'metric' => $metric[0], 'value' => $metric[1], 'detail' => $metric[2]];
    }
    foreach ($recent as $row) {
        $exportRows[] = [
            'section' => 'Recent registrations',
            'metric' => (string) ($row['app_ref'] ?? ''),
            'value' => (string) ($row['status'] ?? ''),
            'detail' => sprintf('%s | %s | %s | %s', (string) ($row['name'] ?? ''), (string) ($row['state'] ?? ''), (string) ($row['lga'] ?? ''), (string) ($row['created_at'] ?? '')),
        ];
    }
    app_export_csv('natcodev-coordination-report-' . $slug . '-' . date('Ymd') . '.csv', array_keys($exportRows[0] ?: ['section', 'metric', 'value', 'detail']), $exportRows);
}

coord_header('Coordination Reports', 'Download and review leadership intelligence across registrations, field activity, support, training, and wallet movement.', $user, 'reports');
?>
<style>
.coord-table{width:100%;border-collapse:collapse}
.coord-table th,.coord-table td{padding:10px 8px;border-bottom:1px solid var(--line);text-align:left;font-size:.86rem;vertical-align:top}
.coord-table th{font-size:.72rem;text-transform:uppercase;color:var(--muted)}
.coord-empty{color:var(--muted);padding:14px;border:1px dashed var(--line);border-radius:8px}
</style>
<?php if (!empty($scope['missing'])): ?><div class="card" style="margin-bottom:16px;border-color:#facc15;background:#fffbeb"><h2><?= e(ucfirst((string) ($scope['tier'] ?? 'state'))) ?> Assignment Required</h2><p>This coordinator has access, but no <?= e((string) ($scope['tier'] ?? 'state')) ?> scope is assigned. An operator must assign the enclave in RBAC before scoped reports can be produced.</p></div><?php endif; ?>
<div class="kpis"><div class="kpi"><i class="fas fa-chart-line"></i><b><?= number_format($registrations) ?></b><span>Scoped registrations</span></div><div class="kpi"><i class="fas fa-circle-check"></i><b><?= number_format($activeRegistrations) ?></b><span>Active / verified</span></div><div class="kpi"><i class="fas fa-hourglass-half"></i><b><?= number_format($pendingRegistrations) ?></b><span>Pending review</span></div><div class="kpi"><i class="fas fa-clipboard-list"></i><b><?= number_format($fieldOpen) ?></b><span>Open field tasks</span></div><div class="kpi"><i class="fas fa-route"></i><b><?= number_format($visits30) ?></b><span>Visits (30 days)</span></div><div class="kpi"><i class="fas fa-map-location-dot"></i><b><?= e($stateLabel) ?></b><span>Reporting scope</span></div></div>
<div class="grid">
<section class="card span-8"><h2>Scope Aggregate Report — <?= e($stateLabel) ?></h2><table class="coord-table"><thead><tr><th>Metric</th><th>Value</th><th>Detail</th></tr></thead><tbody><?php foreach ($metrics as $metric): ?><tr><td><?= e($metric[0]) ?></td><td><strong><?= e($metric[1]) ?></strong></td><td><small><?= e($metric[2]) ?></small></td></tr><?php endforeach; ?></tbody></table></section>
<section class="card span-4"><h2>Export Report</h2><p style="color:var(--muted)">Download the aggregate report for <strong><?= e($stateLabel) ?></strong> as CSV. The export is protected by CSRF and covers exactly the same scope as this page.</p><form method="post" style="margin:12px 0"><input type="hidden" name="export" value="scope_report"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><button class="btn" type="submit"><i class="fas fa-file-csv"></i>Export CSV</button></form><div class="list"><a class="row" href="operations.php"><strong>Operations queue</strong><span class="badge">Lead</span></a><a class="row" href="support.php"><strong>Support queue</strong><span class="badge">Support</span></a><a class="row" href="wallet.php"><strong>Wallet oversight</strong><span class="badge">Wallet</span></a><a class="row" href="<?= e(coord_home(coord_role_key($user))) ?>"><strong>Coordinator home</strong><span class="badge">Home</span></a></div></section>
<section class="card span-12"><h2>Recent Registrations In Scope</h2><div class="list"><?php foreach ($recent as $row): ?><div class="row"><span><strong><?= e((string) $row['name']) ?></strong><br><small><?= e((string) $row['app_ref']) ?> / <?= e((string) $row['state']) ?> / <?= e((string) $row['lga']) ?></small></span><span class="badge"><?= e((string) $row['status']) ?></span></div><?php endforeach; ?><?php if (!$recent): ?><div class="row">No registration activity found for this scope yet.</div><?php endif; ?></div></section>
</div>
<?php coord_footer(); ?>
