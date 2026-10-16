<?php
declare(strict_types=1);
require_once __DIR__ . '/_coordination.php';

$pdo = coord_pdo();
$user = coord_require($pdo);
$scope = coord_scope_filter($pdo, $user);
$where = $scope['where'];
$params = $scope['params'];
$stateLabel = (string) ($scope['label'] ?: 'National');

// Scoping mechanism: every queue is tied to the coordinator's state
// through the owner user's application record (alias `a`, with `ns` for
// the state-name fallback), so the single coord_state_filter() fragment
// scopes applications, documents, certificates, and field work alike.
$userScopeJoin = 'LEFT JOIN applications a ON a.id = u.application_id LEFT JOIN nigeria_states ns ON ns.id = a.state_id';
$farmScopeJoin = 'LEFT JOIN applications a ON a.id = gf.application_id LEFT JOIN nigeria_states ns ON ns.id = a.state_id';
$appFrom = coord_app_from();

$pendingCount = coord_scalar($pdo, "SELECT COUNT(*) FROM {$appFrom} WHERE {$where} AND " . coord_app_status_expr() . " IN ('pending','submitted','under_review','new')", $params);
$pendingApplications = coord_rows($pdo, "SELECT a.app_ref, a.name, " . coord_app_state_expr() . " state, " . coord_app_lga_expr() . " lga, " . coord_app_status_expr() . " status, a.created_at FROM {$appFrom} WHERE {$where} AND " . coord_app_status_expr() . " IN ('pending','submitted','under_review','new') ORDER BY a.created_at DESC LIMIT 12", $params);

$documentCount = 0;
$pendingDocuments = [];
if (app_table_exists($pdo, 'document_requirements')) {
    $documentCount = coord_scalar($pdo, "SELECT COUNT(*) FROM document_requirements dr LEFT JOIN users u ON u.id = dr.user_id {$userScopeJoin} WHERE dr.deleted_at IS NULL AND dr.verification_status = 'pending' AND {$where}", $params);
    $pendingDocuments = coord_rows($pdo, "SELECT dr.id, dr.document_type, dr.document_number, dr.verification_status, dr.uploaded_at, u.name user_name, u.email user_email FROM document_requirements dr LEFT JOIN users u ON u.id = dr.user_id {$userScopeJoin} WHERE dr.deleted_at IS NULL AND dr.verification_status = 'pending' AND {$where} ORDER BY dr.uploaded_at DESC LIMIT 15", $params);
}

$certificateCount = 0;
$pendingCertificates = [];
if (app_table_exists($pdo, 'certificates')) {
    $certificateCount = coord_scalar($pdo, "SELECT COUNT(*) FROM certificates c LEFT JOIN applications a ON a.id = c.application_id LEFT JOIN nigeria_states ns ON ns.id = a.state_id WHERE c.deleted_at IS NULL AND c.status NOT IN ('issued','revoked') AND {$where}", $params);
    $pendingCertificates = coord_rows($pdo, "SELECT c.id, c.certificate_ref, c.status, c.issued_at, a.app_ref, a.name FROM certificates c LEFT JOIN applications a ON a.id = c.application_id LEFT JOIN nigeria_states ns ON ns.id = a.state_id WHERE c.deleted_at IS NULL AND c.status NOT IN ('issued','revoked') AND {$where} ORDER BY c.issued_at DESC, c.id DESC LIMIT 15", $params);
}

$fieldOpen = 0;
$visits30 = 0;
$recentVisits = [];
if (app_table_exists($pdo, 'grower_farms')) {
    if (app_table_exists($pdo, 'field_tasks')) {
        $fieldOpen = coord_scalar($pdo, "SELECT COUNT(*) FROM field_tasks ft JOIN grower_farms gf ON gf.id = ft.farm_id AND gf.deleted_at IS NULL {$farmScopeJoin} WHERE ft.status IN ('pending','assigned','in_progress') AND {$where}", $params);
    }
    if (app_table_exists($pdo, 'farm_visits')) {
        $visits30 = coord_scalar($pdo, "SELECT COUNT(*) FROM farm_visits fv JOIN grower_farms gf ON gf.id = fv.farm_id AND gf.deleted_at IS NULL {$farmScopeJoin} WHERE fv.visited_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND {$where}", $params);
        $recentVisits = coord_rows($pdo, "SELECT fv.id, fv.visited_at, fv.result, fv.sync_source, gf.farm_name, u.name grower_name, au.name agent_name FROM farm_visits fv JOIN grower_farms gf ON gf.id = fv.farm_id AND gf.deleted_at IS NULL LEFT JOIN users u ON u.id = gf.user_id LEFT JOIN users au ON au.id = fv.agent_id {$farmScopeJoin} WHERE {$where} ORDER BY fv.visited_at DESC, fv.id DESC LIMIT 12", $params);
    }
}

coord_header('Operations', 'Review registry activity, field-network progress, state/national oversight, and leadership actions.', $user, 'operations');
?>
<style>
.coord-table{width:100%;border-collapse:collapse}
.coord-table th,.coord-table td{padding:10px 8px;border-bottom:1px solid var(--line);text-align:left;font-size:.86rem;vertical-align:top}
.coord-table th{font-size:.72rem;text-transform:uppercase;color:var(--muted)}
.coord-empty{color:var(--muted);padding:14px;border:1px dashed var(--line);border-radius:8px}
</style>
<?php if (!empty($scope['missing'])): ?><div class="card" style="margin-bottom:16px;border-color:#facc15;background:#fffbeb"><h2><?= e(ucfirst((string) ($scope['tier'] ?? 'state'))) ?> Assignment Required</h2><p>This coordinator has access, but no <?= e((string) ($scope['tier'] ?? 'state')) ?> scope is assigned. An operator must assign the enclave in RBAC before operations work can appear here.</p></div><?php endif; ?>
<div class="kpis"><div class="kpi"><i class="fas fa-hourglass-half"></i><b><?= number_format($pendingCount) ?></b><span>Pending applications</span></div><div class="kpi"><i class="fas fa-id-card"></i><b><?= number_format($documentCount) ?></b><span>Pending document verifications</span></div><div class="kpi"><i class="fas fa-award"></i><b><?= number_format($certificateCount) ?></b><span>Pending certificates</span></div><div class="kpi"><i class="fas fa-clipboard-list"></i><b><?= number_format($fieldOpen) ?></b><span>Open field tasks</span></div><div class="kpi"><i class="fas fa-route"></i><b><?= number_format($visits30) ?></b><span>Visits (30 days)</span></div><div class="kpi"><i class="fas fa-map-location-dot"></i><b><?= e($stateLabel) ?></b><span>Operating scope</span></div></div>
<div class="grid">
<section class="card span-6"><h2>Pending Applications — <?= e($stateLabel) ?></h2><table class="coord-table"><thead><tr><th>Ref</th><th>Name</th><th>State / LGA</th><th>Status</th><th>Submitted</th></tr></thead><tbody><?php foreach ($pendingApplications as $row): ?><tr><td><?= e((string) ($row['app_ref'] ?: '-')) ?></td><td><strong><?= e((string) ($row['name'] ?: '')) ?></strong></td><td><?= e((string) ($row['state'] ?: '-')) ?><br><small><?= e((string) ($row['lga'] ?: '-')) ?></small></td><td><span class="badge"><?= e((string) ($row['status'] ?: '')) ?></span></td><td><?= e($row['created_at'] ? date('M j, Y', strtotime((string) $row['created_at'])) : '-') ?></td></tr><?php endforeach; ?><?php if (!$pendingApplications): ?><tr><td colspan="5"><div class="coord-empty">No pending applications in this scope.</div></td></tr><?php endif; ?></tbody></table></section>
<section class="card span-6"><h2>Pending Document Verifications</h2><table class="coord-table"><thead><tr><th>Document</th><th>Number</th><th>Grower</th><th>Status</th><th>Uploaded</th></tr></thead><tbody><?php foreach ($pendingDocuments as $row): ?><tr><td><?= e(ucwords(str_replace('_', ' ', (string) ($row['document_type'] ?? '')))) ?></td><td><small><?= e((string) ($row['document_number'] ?: '-')) ?></small></td><td><strong><?= e((string) ($row['user_name'] ?: 'Unknown user')) ?></strong><br><small><?= e((string) ($row['user_email'] ?: '')) ?></small></td><td><span class="badge"><?= e(ucwords(str_replace('_', ' ', (string) ($row['verification_status'] ?? '')))) ?></span></td><td><?= e($row['uploaded_at'] ? date('M j, Y', strtotime((string) $row['uploaded_at'])) : '-') ?></td></tr><?php endforeach; ?><?php if (!$pendingDocuments): ?><tr><td colspan="5"><div class="coord-empty">No pending document verifications in this scope.</div></td></tr><?php endif; ?></tbody></table></section>
<section class="card span-6"><h2>Pending Certificates</h2><table class="coord-table"><thead><tr><th>Certificate</th><th>Application</th><th>Grower</th><th>Status</th><th>Requested</th></tr></thead><tbody><?php foreach ($pendingCertificates as $row): ?><tr><td><?= e((string) ($row['certificate_ref'] ?: '-')) ?></td><td><small><?= e((string) ($row['app_ref'] ?: '-')) ?></small></td><td><strong><?= e((string) ($row['name'] ?: 'Unknown grower')) ?></strong></td><td><span class="badge"><?= e(ucwords(str_replace('_', ' ', (string) ($row['status'] ?? '')))) ?></span></td><td><?= e($row['issued_at'] ? date('M j, Y', strtotime((string) $row['issued_at'])) : '-') ?></td></tr><?php endforeach; ?><?php if (!$pendingCertificates): ?><tr><td colspan="5"><div class="coord-empty">No certificates awaiting issuance in this scope. Certificates are issued directly as "issued", so an empty queue is expected unless a non-terminal status exists.</div></td></tr><?php endif; ?></tbody></table></section>
<section class="card span-6"><h2>Recent Field Visits</h2><table class="coord-table"><thead><tr><th>Farm</th><th>Grower</th><th>Agent</th><th>Result</th><th>Visited</th></tr></thead><tbody><?php foreach ($recentVisits as $row): ?><tr><td><strong><?= e((string) ($row['farm_name'] ?: '-')) ?></strong><br><small><?= e((string) ($row['sync_source'] ?: 'online_form')) ?></small></td><td><?= e((string) ($row['grower_name'] ?: 'Unknown grower')) ?></td><td><?= e((string) ($row['agent_name'] ?: '-')) ?></td><td><span class="badge"><?= e(ucwords(str_replace('_', ' ', (string) ($row['result'] ?? '')))) ?></span></td><td><?= e($row['visited_at'] ? date('M j, Y h:i A', strtotime((string) $row['visited_at'])) : '-') ?></td></tr><?php endforeach; ?><?php if (!$recentVisits): ?><tr><td colspan="5"><div class="coord-empty">No field visits logged for this scope yet.</div></td></tr><?php endif; ?></tbody></table></section>
<section class="card span-12"><div class="list"><a class="row" href="reports.php"><strong>Reports</strong><span class="badge">Report</span></a><a class="row" href="support.php"><strong>Support queue</strong><span class="badge">Support</span></a><a class="row" href="wallet.php"><strong>Wallet oversight</strong><span class="badge">Wallet</span></a><a class="row" href="<?= e(coord_home(coord_role_key($user))) ?>"><strong>Coordinator home</strong><span class="badge">Home</span></a></div></section>
</div>
<?php coord_footer(); ?>
