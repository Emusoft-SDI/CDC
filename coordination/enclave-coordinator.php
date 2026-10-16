<?php
declare(strict_types=1);
require_once __DIR__ . '/_coordination.php';

$pdo = coord_pdo();
$user = coord_require($pdo);
$scope = coord_scope_filter($pdo, $user);
$where = $scope['where'];
$params = $scope['params'];
$tier = (string) ($scope['tier'] ?? 'state');
$enclaveLabel = (string) ($scope['label'] ?: 'Unassigned');
$enclaves = (array) ($scope['scopes'] ?? []);

// Enclave KPIs: every count routes through the same
// scope fragment the operations and reports pages use.
$enclaveApps = coord_scalar($pdo, "SELECT COUNT(*) FROM " . coord_app_from() . " WHERE {$where}", $params);
$enclaveActive = coord_scalar($pdo, "SELECT COUNT(*) FROM " . coord_app_from() . " WHERE {$where} AND " . coord_app_status_expr() . " IN ('active','verified','approved','confirmed')", $params);
$enclavePending = coord_scalar($pdo, "SELECT COUNT(*) FROM " . coord_app_from() . " WHERE {$where} AND " . coord_app_status_expr() . " IN ('pending','submitted','under_review','new')", $params);
$enclaveConfirmed = coord_scalar($pdo, "SELECT COUNT(*) FROM " . coord_app_from() . " WHERE {$where} AND confirmed = 1", $params);
$enclaveLgas = coord_scalar($pdo, "SELECT COUNT(DISTINCT " . coord_app_lga_expr() . ") FROM " . coord_app_from() . " WHERE {$where}", $params);

// Farms and field work scoped through the farm's
// application record (the same join pattern as
// operations.php), so the enclave fragment governs.
$farmScopeJoin = 'LEFT JOIN applications a ON a.id = gf.application_id LEFT JOIN nigeria_states ns ON ns.id = a.state_id';
$farmCount = 0;
$fieldOpen = 0;
$visits30 = 0;
if (app_table_exists($pdo, 'grower_farms')) {
    $farmCount = coord_scalar($pdo, "SELECT COUNT(*) FROM grower_farms gf {$farmScopeJoin} WHERE gf.deleted_at IS NULL AND {$where}", $params);
    if (app_table_exists($pdo, 'field_tasks')) {
        $fieldOpen = coord_scalar($pdo, "SELECT COUNT(*) FROM field_tasks ft JOIN grower_farms gf ON gf.id = ft.farm_id AND gf.deleted_at IS NULL {$farmScopeJoin} WHERE ft.status IN ('pending','assigned','in_progress') AND {$where}", $params);
    }
    if (app_table_exists($pdo, 'farm_visits')) {
        $visits30 = coord_scalar($pdo, "SELECT COUNT(*) FROM farm_visits fv JOIN grower_farms gf ON gf.id = fv.farm_id AND gf.deleted_at IS NULL {$farmScopeJoin} WHERE fv.visited_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND {$where}", $params);
    }
}

// Breakdown: an LGA coordinator sees the districts in
// their LGA; a district coordinator sees the villages
// in their district; a village coordinator sees the
// member types in their village.
$breakdownLabel = $tier === 'lga' ? 'District' : ($tier === 'district' ? 'Village' : 'Registration Type');
$breakdownRows = [];
if ($tier === 'lga' && app_column_exists($pdo, 'applications', 'district')) {
    $breakdownRows = coord_rows($pdo, "SELECT COALESCE(NULLIF(a.district, ''), 'Unspecified district') name, COUNT(*) total, SUM(a.confirmed = 1) confirmed FROM " . coord_app_from() . " WHERE {$where} GROUP BY COALESCE(NULLIF(a.district, ''), 'Unspecified district') ORDER BY total DESC LIMIT 15", $params);
} elseif ($tier === 'district' && app_column_exists($pdo, 'applications', 'village')) {
    $breakdownRows = coord_rows($pdo, "SELECT COALESCE(NULLIF(a.village, ''), 'Unspecified village') name, COUNT(*) total, SUM(a.confirmed = 1) confirmed FROM " . coord_app_from() . " WHERE {$where} GROUP BY COALESCE(NULLIF(a.village, ''), 'Unspecified village') ORDER BY total DESC LIMIT 15", $params);
} else {
    $breakdownRows = coord_rows($pdo, "SELECT COALESCE(NULLIF(a.member_type, ''), 'individual') name, COUNT(*) total, SUM(a.confirmed = 1) confirmed FROM " . coord_app_from() . " WHERE {$where} GROUP BY COALESCE(NULLIF(a.member_type, ''), 'individual') ORDER BY total DESC LIMIT 15", $params);
}

$recent = coord_rows($pdo, "SELECT a.app_ref, a.name, " . coord_app_state_expr() . " state, " . coord_app_lga_expr() . " lga, " . coord_app_status_expr() . " status, a.created_at FROM " . coord_app_from() . " WHERE {$where} ORDER BY a.created_at DESC LIMIT 10", $params);

$title = ucfirst($tier) . ' Coordination Desk';
coord_header($title, 'Scoped governance for the assigned ' . $tier . ' enclave: registry records, farms, field work, and coverage for ' . $enclaveLabel . ' only.', $user, 'home');
?>
<style>.sc-table{width:100%;border-collapse:collapse}.sc-table th,.sc-table td{padding:10px 8px;border-bottom:1px solid var(--line);text-align:left;font-size:.86rem}.sc-table th{font-size:.72rem;text-transform:uppercase;color:var(--muted)}.sc-list{display:grid;gap:10px}.sc-item{display:flex;justify-content:space-between;gap:12px;border:1px solid var(--line);border-radius:8px;padding:11px;background:#fff}.empty{color:var(--muted);padding:12px;border:1px dashed var(--line);border-radius:8px}</style>
<?php if (!empty($scope['missing'])): ?><div class="card" style="margin-bottom:16px;border-color:#facc15;background:#fffbeb"><h2><?= e(ucfirst($tier)) ?> Assignment Required</h2><p>This coordinator has access, but no <?= e($tier) ?> is assigned. An operator must assign the enclave in RBAC before work appears here.</p></div><?php endif; ?>
<div class="kpis"><div class="kpi"><i class="fas fa-users"></i><b><?= number_format($enclaveApps) ?></b><span>Registrations in enclave</span></div><div class="kpi"><i class="fas fa-circle-check"></i><b><?= number_format($enclaveActive) ?></b><span>Active / verified</span></div><div class="kpi"><i class="fas fa-hourglass-half"></i><b><?= number_format($enclavePending) ?></b><span>Pending review</span></div><div class="kpi"><i class="fas fa-user-check"></i><b><?= number_format($enclaveConfirmed) ?></b><span>Email confirmed</span></div><div class="kpi"><i class="fas fa-location-dot"></i><b><?= number_format($enclaveLgas) ?></b><span>LGAs represented</span></div></div>
<div class="kpis"><div class="kpi"><i class="fas fa-seedling"></i><b><?= number_format($farmCount) ?></b><span>Farms</span></div><div class="kpi"><i class="fas fa-clipboard-list"></i><b><?= number_format($fieldOpen) ?></b><span>Open field tasks</span></div><div class="kpi"><i class="fas fa-route"></i><b><?= number_format($visits30) ?></b><span>Visits (30 days)</span></div><div class="kpi"><i class="fas fa-map-location-dot"></i><b><?= e($enclaveLabel) ?></b><span>Assigned <?= e($tier) ?></span></div></div>
<div class="grid">
<section class="card span-6"><h2><?= e($breakdownLabel) ?> Breakdown — <?= e($enclaveLabel) ?></h2><table class="sc-table"><thead><tr><th><?= e($breakdownLabel) ?></th><th>Records</th><th>Confirmed</th></tr></thead><tbody><?php foreach ($breakdownRows as $row): ?><tr><td><?= e((string) $row['name']) ?></td><td><?= number_format((int) $row['total']) ?></td><td><?= number_format((int) $row['confirmed']) ?></td></tr><?php endforeach; ?><?php if (!$breakdownRows): ?><tr><td colspan="3"><div class="empty">No registry records in this enclave yet.</div></td></tr><?php endif; ?></tbody></table></section>
<section class="card span-6"><h2>Assigned Enclave</h2><div class="sc-list"><?php foreach ($enclaves as $enclave): ?><div class="sc-item"><span><strong><?= e((string) $enclave) ?></strong><small>Assigned <?= e($tier) ?> scope</small></span><span class="badge">Active</span></div><?php endforeach; ?><?php if (!$enclaves): ?><div class="empty">No <?= e($tier) ?> assigned. Contact an operator to assign your enclave.</div><?php endif; ?></div><p style="margin-top:14px"><a class="btn" href="import.php"><i class="fas fa-file-import"></i> Import Members Into This Enclave</a></p></section>
<section class="card span-12"><h2>Recent Registry Activity in <?= e($enclaveLabel) ?></h2><table class="sc-table"><thead><tr><th>Ref</th><th>Name</th><th>State</th><th>LGA</th><th>Status</th><th>Submitted</th></tr></thead><tbody><?php foreach ($recent as $row): ?><tr><td><?= e((string) $row['app_ref']) ?></td><td><strong><?= e((string) $row['name']) ?></strong></td><td><?= e((string) $row['state']) ?></td><td><?= e((string) $row['lga']) ?></td><td><span class="badge"><?= e((string) $row['status']) ?></span></td><td><?= e($row['created_at'] ? date('M j, Y', strtotime((string) $row['created_at'])) : '-') ?></td></tr><?php endforeach; ?><?php if (!$recent): ?><tr><td colspan="6"><div class="empty">No registration activity in this enclave yet.</div></td></tr><?php endif; ?></tbody></table></section>
<section class="card span-12"><div class="list"><?php if (admin_feature_is_allowed($pdo, 'applications') || admin_feature_is_allowed($pdo, 'reports')): ?><a class="row" href="operations.php"><strong>Operations</strong><span class="badge">Lead</span></a><?php endif; ?><?php if (admin_feature_is_allowed($pdo, 'reports')): ?><a class="row" href="reports.php"><strong>Reports</strong><span class="badge">Report</span></a><?php endif; ?><?php if (admin_feature_is_allowed($pdo, 'support')): ?><a class="row" href="support.php"><strong>Support</strong><span class="badge">Support</span></a><?php endif; ?><?php if (admin_feature_is_allowed($pdo, 'training')): ?><a class="row" href="academy.php"><strong>Academy</strong><span class="badge">Training</span></a><?php endif; ?><?php if (admin_feature_is_allowed($pdo, 'wallet')): ?><a class="row" href="wallet.php"><strong>Wallet</strong><span class="badge">Wallet</span></a><?php endif; ?></div></section>
</div>
<?php coord_footer(); ?>
