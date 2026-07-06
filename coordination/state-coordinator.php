<?php
declare(strict_types=1);
require_once __DIR__ . '/_coordination.php';

$pdo = coord_pdo();
$user = coord_require($pdo, 'state_coordinator');
$scopeOptions = coord_state_scope_options($pdo, $user);
$assignedStates = coord_assigned_states($pdo, $user);
$selectedState = coord_selected_state($pdo, $user);
$resolvedState = coord_resolve_state($pdo, $selectedState);
$stateId = (int) ($resolvedState['id'] ?? 0);
$canonicalState = (string) (($resolvedState['state_name'] ?? '') ?: $selectedState);
$assignedLabel = (string) (($resolvedState['assigned_label'] ?? '') ?: $selectedState);

$totalApps = coord_scalar($pdo, 'SELECT COUNT(*) FROM applications');
$mappedApps = coord_scalar($pdo, 'SELECT COUNT(*) FROM applications WHERE state_id IS NOT NULL');
$unmappedApps = coord_scalar($pdo, 'SELECT COUNT(*) FROM applications WHERE state_id IS NULL');
$stateApps = $stateId > 0 ? coord_scalar($pdo, 'SELECT COUNT(*) FROM applications WHERE state_id = ?', [$stateId]) : 0;
$stateLgaMapped = $stateId > 0 ? coord_scalar($pdo, 'SELECT COUNT(*) FROM applications WHERE state_id = ? AND lga_id IS NOT NULL', [$stateId]) : 0;
$stateConfirmed = $stateId > 0 ? coord_scalar($pdo, 'SELECT COUNT(*) FROM applications WHERE state_id = ? AND confirmed = 1', [$stateId]) : 0;
$stateActive = $stateId > 0 ? coord_scalar($pdo, "SELECT COUNT(*) FROM applications WHERE state_id = ? AND (review_status IN ('active','verified','approved','confirmed') OR confirmed = 1)", [$stateId]) : 0;
$stateFarms = $stateId > 0 && app_table_exists($pdo, 'grower_farms') ? coord_scalar($pdo, 'SELECT COUNT(*) FROM grower_farms WHERE state_id = ?', [$stateId]) : 0;
$stateFarmGps = $stateId > 0 && app_table_exists($pdo, 'grower_farms') ? coord_scalar($pdo, 'SELECT COUNT(*) FROM grower_farms WHERE state_id = ? AND latitude IS NOT NULL AND longitude IS NOT NULL', [$stateId]) : 0;
$fieldOpen = $stateId > 0 && app_table_exists($pdo, 'field_tasks') && app_table_exists($pdo, 'grower_farms') ? coord_scalar($pdo, "SELECT COUNT(*) FROM field_tasks ft JOIN grower_farms gf ON gf.id = ft.farm_id WHERE gf.state_id = ? AND ft.status IN ('pending','assigned','in_progress')", [$stateId]) : 0;
$visits30 = $stateId > 0 && app_table_exists($pdo, 'farm_visits') && app_table_exists($pdo, 'grower_farms') ? coord_scalar($pdo, "SELECT COUNT(*) FROM farm_visits fv JOIN grower_farms gf ON gf.id = fv.farm_id WHERE gf.state_id = ? AND fv.visited_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)", [$stateId]) : 0;
$legacyTerms = array_values(array_unique(array_filter(array_merge([$assignedLabel, $canonicalState], coord_state_aliases($selectedState)))));
$legacyHints = [];
foreach ($legacyTerms as $term) {
    if (count($legacyHints) >= 8) {
        break;
    }
    $rows = coord_rows($pdo, "SELECT COALESCE(NULLIF(location,''),'Blank location') location_hint, COUNT(*) total FROM applications WHERE state_id IS NULL AND location LIKE ? GROUP BY COALESCE(NULLIF(location,''),'Blank location') ORDER BY total DESC LIMIT 8", ['%' . $term . '%']);
    foreach ($rows as $row) {
        $key = (string) $row['location_hint'];
        if (!isset($legacyHints[$key])) {
            $row['matched_term'] = $term;
            $legacyHints[$key] = $row;
        }
    }
}
$legacyHints = array_slice(array_values($legacyHints), 0, 8);
$lgaRows = $stateId > 0 ? coord_rows($pdo, "SELECT COALESCE(nl.lga_name,'Unmapped LGA') lga_name, COUNT(*) total, SUM(a.confirmed=1) confirmed_total FROM applications a LEFT JOIN nigeria_lgas nl ON nl.id=a.lga_id WHERE a.state_id=? GROUP BY COALESCE(nl.lga_name,'Unmapped LGA') ORDER BY total DESC", [$stateId]) : [];
$recent = $stateId > 0 ? coord_rows($pdo, "SELECT a.app_ref, a.name, COALESCE(nl.lga_name,'Unmapped LGA') lga, a.location, COALESCE(NULLIF(a.review_status,''), IF(a.confirmed=1,'active','pending')) status, a.created_at FROM applications a LEFT JOIN nigeria_lgas nl ON nl.id=a.lga_id WHERE a.state_id=? ORDER BY a.created_at DESC LIMIT 10", [$stateId]) : [];
$stateIntegrity = $stateApps > 0 ? round(($stateLgaMapped / $stateApps) * 100, 1) : 0;
$truthClass = $stateId > 0 && ($stateApps > 0 || $stateIntegrity >= 80) ? 'good' : '';
$truthMessage = 'This state assignment has not been resolved to a database state.';
if ($stateId > 0 && $stateApps > 0) {
    $truthMessage = 'This workspace is using mapped applications.state_id records for the resolved state.';
} elseif ($stateId > 0) {
    $truthMessage = 'This is a valid state assignment, but there are no applications mapped to this state yet. That is true zero activity, not a query failure.';
}

$title = ($canonicalState !== '' ? $canonicalState : 'Unassigned') . ' State Coordination Desk';
coord_header($title, 'State coordination is scoped governance: mapped registry records, farms, field work, LGA coverage, and data-quality exceptions for this state only.', $user, 'home');
?>
<style>.sc-truth{border-left:5px solid #b7791f;background:#fffbeb}.sc-truth.good{border-left-color:#087443;background:#f0fdf4}.sc-grid{display:grid;grid-template-columns:1.1fr .9fr;gap:16px}.sc-table{width:100%;border-collapse:collapse}.sc-table th,.sc-table td{padding:10px 8px;border-bottom:1px solid var(--line);text-align:left;font-size:.86rem}.sc-table th{font-size:.72rem;text-transform:uppercase;color:var(--muted)}.sc-list{display:grid;gap:10px}.sc-item{display:flex;justify-content:space-between;gap:12px;border:1px solid var(--line);border-radius:8px;padding:11px;background:#fff}.sc-item small{display:block;color:var(--muted);margin-top:3px}.state-tabs{display:flex;gap:8px;flex-wrap:wrap}.state-tabs a{border:1px solid var(--line);border-radius:8px;padding:9px 12px;background:#fff;font-weight:900}.state-tabs a.active{background:var(--green);color:#fff;border-color:var(--green)}.truth-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-top:12px}.truth-cell{background:#fff;border:1px solid var(--line);border-radius:8px;padding:11px}.truth-cell small{display:block;color:var(--muted);font-weight:800;text-transform:uppercase;font-size:.7rem}.truth-cell strong{display:block;margin-top:4px}.empty{color:var(--muted);padding:12px;border:1px dashed var(--line);border-radius:8px}@media(max-width:1000px){.sc-grid,.truth-grid{grid-template-columns:1fr}}</style>
<section class="card sc-truth <?= e($truthClass) ?>">
  <h2>State Scope & Data Integrity</h2>
  <p><?= e($truthMessage) ?></p>
  <div class="truth-grid">
    <div class="truth-cell"><small>Assigned label</small><strong><?= e($assignedLabel ?: 'Unassigned') ?></strong></div>
    <div class="truth-cell"><small>Resolved database state</small><strong><?= e($stateId > 0 ? $canonicalState : 'Not resolved') ?></strong></div>
    <div class="truth-cell"><small>State ID / code</small><strong><?= e($stateId > 0 ? ('#' . $stateId . ' / ' . ((string) $resolvedState['state_code'] ?: '-')) : '-') ?></strong></div>
    <div class="truth-cell"><small>Resolution</small><strong><?= !empty($resolvedState['alias_used']) ? 'Alias resolved' : ($stateId > 0 ? 'Exact match' : 'Needs operator correction') ?></strong></div>
  </div>
  <p>Assigned states: <strong><?= e(implode(', ', $assignedStates) ?: 'No state assigned') ?></strong>. Platform total: <?= number_format($totalApps) ?> applications; <?= number_format($mappedApps) ?> mapped; <?= number_format($unmappedApps) ?> still unmapped legacy records.</p>
  <div class="state-tabs"><?php foreach ($scopeOptions as $option): ?><a class="<?= strcasecmp((string) $option['assigned_label'], $selectedState) === 0 ? 'active' : '' ?>" href="<?= e(coord_state_query('state-coordinator.php', (string) $option['assigned_label'])) ?>"><?= e((string) $option['assigned_label']) ?> <span class="badge"><?= number_format((int) $option['mapped_applications']) ?></span></a><?php endforeach; ?></div>
</section>

<div class="kpis"><div class="kpi"><i class="fas fa-users"></i><b><?= number_format($stateApps) ?></b><span>Mapped applications</span></div><div class="kpi"><i class="fas fa-location-dot"></i><b><?= number_format($stateLgaMapped) ?></b><span>LGA mapped</span></div><div class="kpi"><i class="fas fa-seedling"></i><b><?= number_format($stateFarms) ?></b><span>State farms</span></div><div class="kpi"><i class="fas fa-clipboard-list"></i><b><?= number_format($fieldOpen) ?></b><span>Open field tasks</span></div><div class="kpi"><i class="fas fa-route"></i><b><?= number_format($visits30) ?></b><span>Visits last 30 days</span></div></div>
<div class="kpis"><div class="kpi"><i class="fas fa-circle-check"></i><b><?= number_format($stateActive) ?></b><span>Active / verified</span></div><div class="kpi"><i class="fas fa-user-check"></i><b><?= number_format($stateConfirmed) ?></b><span>Email confirmed</span></div><div class="kpi"><i class="fas fa-map-pin"></i><b><?= number_format($stateFarmGps) ?></b><span>Farms with GPS</span></div><div class="kpi"><i class="fas fa-gauge"></i><b><?= e((string) $stateIntegrity) ?>%</b><span>LGA integrity</span></div><div class="kpi"><i class="fas fa-triangle-exclamation"></i><b><?= number_format($unmappedApps) ?></b><span>National unmapped backlog</span></div></div>

<div class="sc-grid"><section class="card"><h2>LGA Operating Breakdown</h2><table class="sc-table"><thead><tr><th>LGA</th><th>Records</th><th>Confirmed</th></tr></thead><tbody><?php foreach ($lgaRows as $row): ?><tr><td><?= e((string) $row['lga_name']) ?></td><td><?= number_format((int) $row['total']) ?></td><td><?= number_format((int) $row['confirmed_total']) ?></td></tr><?php endforeach; ?><?php if (!$lgaRows): ?><tr><td colspan="3">No registry records are mapped to <?= e($canonicalState ?: 'this state') ?> yet.</td></tr><?php endif; ?></tbody></table></section><section class="card"><h2>Possible Legacy Records For This State</h2><div class="sc-list"><?php foreach ($legacyHints as $row): ?><div class="sc-item"><span><strong><?= e((string) $row['location_hint']) ?></strong><small>Matched legacy text: <?= e((string) $row['matched_term']) ?>; needs state_id/LGA mapping review</small></span><span class="badge"><?= number_format((int) $row['total']) ?></span></div><?php endforeach; ?><?php if (!$legacyHints): ?><div class="empty">No unmapped legacy location text matches <?= e($assignedLabel ?: $canonicalState) ?> or its aliases.</div><?php endif; ?></div></section></div>

<section class="card"><h2>Recent Mapped Registry Activity In <?= e($canonicalState ?: 'This State') ?></h2><table class="sc-table"><thead><tr><th>Ref</th><th>Name</th><th>LGA</th><th>Legacy Location</th><th>Status</th><th>Submitted</th></tr></thead><tbody><?php foreach ($recent as $row): ?><tr><td><?= e((string) $row['app_ref']) ?></td><td><?= e((string) $row['name']) ?></td><td><?= e((string) $row['lga']) ?></td><td><?= e((string) $row['location']) ?></td><td><span class="badge"><?= e((string) $row['status']) ?></span></td><td><?= e($row['created_at'] ? date('M j, Y', strtotime((string) $row['created_at'])) : '-') ?></td></tr><?php endforeach; ?><?php if (!$recent): ?><tr><td colspan="6">No mapped application activity exists for this state yet. If real activity belongs here, the registry records must be mapped to the correct state_id/LGA.</td></tr><?php endif; ?></tbody></table></section>
<?php coord_footer(); ?>