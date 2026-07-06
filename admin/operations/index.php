<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/admin-workspace.php';

$pdo = db();
admin_workspace_require_feature($pdo, 'dashboard');
admin_ensure_schema($pdo);

function ops_count(PDO $pdo, string $table, string $where = '1=1'): int
{
    if (!app_table_exists($pdo, $table)) return 0;
    try { return (int) $pdo->query("SELECT COUNT(*) FROM `{$table}` WHERE {$where}")->fetchColumn(); }
    catch (Throwable $e) { return 0; }
}

function ops_rows(PDO $pdo, string $sql): array
{
    try { return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC); }
    catch (Throwable $e) { return []; }
}

$page = in_array((string) ($_GET['page'] ?? 'overview'), ['overview', 'queues', 'network', 'platform'], true)
    ? (string) ($_GET['page'] ?? 'overview') : 'overview';
$metrics = [
    ['Pending applications', ops_count($pdo, 'applications', 'confirmed = 0'), '../registry/applications.php', 'warning'],
    ['Document reviews', ops_count($pdo, 'document_requirements', "verification_status IN ('pending','needs_review')"), '../registry/documents.php', 'info'],
    ['Farm verifications', ops_count($pdo, 'farm_verifications', "status IN ('pending','under_review')"), '../fields-management.php', 'success'],
    ['Open support tickets', ops_count($pdo, 'support_tickets', "status IN ('open','in_progress','escalated')"), '../support/', 'danger'],
];
$queues = [
    ['Grower applications', ops_count($pdo, 'applications', 'confirmed = 0'), '../registry/applications.php', 'Review, approve, reject, or resend confirmation.'],
    ['Imported engagements', ops_count($pdo, 'user_import_records', "status IN ('pending','pending_engagement','pending_phone_engagement')"), '../import-users.php', 'Correct rows, resend engagement, archive, or delete.'],
    ['Identity documents', ops_count($pdo, 'document_requirements', "verification_status IN ('pending','needs_review')"), '../registry/documents.php', 'View evidence and record verification decisions.'],
    ['Provider approvals', ops_count($pdo, 'provider_registry', "status IN ('pending','under_review')"), '../providers.php', 'Approve, reject, suspend, and maintain providers.'],
    ['Marketplace orders', ops_count($pdo, 'marketplace_orders', "status IN ('pending','processing','disputed')"), '../marketplace/?page=orders', 'Manage order exceptions, disputes, and fulfillment.'],
    ['Wallet exceptions', ops_count($pdo, 'wallet_transactions', "status IN ('pending','failed')"), '../wallet/', 'Review transaction, withdrawal, payout, and reconciliation queues.'],
];
$recent = app_table_exists($pdo, 'applications') ? ops_rows($pdo, "SELECT app_ref reference, name title, created_at occurred_at, IF(confirmed=1,'confirmed','pending') status FROM applications ORDER BY created_at DESC LIMIT 8") : [];
$commands = [
    ['Registry', 'Growers, applications, documents, certificates, field network and imports.', '../registry/', 'applications'],
    ['Field Operations', 'Farm records, assignments, agent mapping and agronomy cases.', '../fields-management.php', 'field_management'],
    ['Providers', 'Provider onboarding, review, accreditation and service records.', '../providers.php', 'providers'],
    ['Resources', 'Allocate inputs, equipment and operational support.', '../resource-allocation.php', 'resource_allocation'],
    ['Communications', 'Send notices and review delivery activity.', '../communications.php', 'communications'],
    ['Marketplace', 'Seller, product, order, dispute and payout operations.', '../marketplace/', 'marketplace'],
    ['Wallet', 'Funding, withdrawals, payouts and reconciliation.', '../wallet/', 'wallet'],
    ['Support', 'Ticket queues, escalations and knowledge operations.', '../support/', 'support'],
];
?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Operations Workspace - NATCODEV</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="../../assets/css/admin-workspaces.css">
<style>
body{background:#f4f7f5}.ops-shell{display:grid;grid-template-columns:270px 1fr;min-height:100vh}.ops-side{background:#0b3b28;color:#fff;padding:24px 18px}.ops-side a{color:#d8eee2;text-decoration:none;padding:11px 12px;border-radius:8px}.ops-side a.active,.ops-side a:hover{background:#17603f;color:#fff}.ops-main{padding:28px}.metric{border:0;border-left:4px solid var(--bs-success)}.command-card{transition:.15s}.command-card:hover{transform:translateY(-2px)}@media(max-width:900px){.ops-shell{grid-template-columns:1fr}.ops-side{position:static}.ops-main{padding:18px}}
</style></head><body>
<div class="ops-shell">
<aside class="ops-side">
  <h4 class="mb-1">NATCODEV</h4><p class="small text-white-50 mb-4">Operations Workspace</p>
  <nav class="d-grid gap-1">
    <?php foreach (['overview'=>'Overview','queues'=>'Work Queues','network'=>'Operations Network','platform'=>'Platform Commands'] as $key=>$label): ?><a class="<?= $page===$key?'active':'' ?>" href="?page=<?= $key ?>"><?= e($label) ?></a><?php endforeach; ?>
    <hr class="border-light opacity-25"><a href="../index.php">Workspace Hub</a><a href="../admin.php?logout=1">Logout</a>
  </nav>
</aside>
<main class="ops-main">
  <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4"><div><span class="text-success fw-bold text-uppercase small">Live operational data</span><h1 class="h3 mb-1"><?= e(ucwords(str_replace('_',' ',$page))) ?></h1><p class="text-secondary mb-0">One command center for authenticated NATCODEV administration.</p></div><a class="btn btn-success" href="../search.php">Search Platform</a></div>
  <div class="row g-3 mb-4"><?php foreach($metrics as [$label,$value,$href,$tone]): ?><div class="col-sm-6 col-xl-3"><a class="card metric h-100 text-decoration-none" href="<?= e($href) ?>"><div class="card-body"><div class="text-secondary small"><?= e($label) ?></div><div class="display-6 fw-bold text-dark"><?= number_format($value) ?></div><small class="text-success">Open queue</small></div></a></div><?php endforeach; ?></div>
  <?php if($page==='overview' || $page==='queues'): ?><section class="card mb-4"><div class="card-header bg-white"><h2 class="h5 mb-0">Action Queues</h2></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Queue</th><th>Pending</th><th>Operational scope</th><th></th></tr></thead><tbody><?php foreach($queues as [$label,$count,$href,$detail]): ?><tr><td class="fw-semibold"><?= e($label) ?></td><td><span class="badge text-bg-<?= $count?'warning':'success' ?>"><?= number_format($count) ?></span></td><td class="text-secondary"><?= e($detail) ?></td><td><a class="btn btn-sm btn-outline-success" href="<?= e($href) ?>">Manage</a></td></tr><?php endforeach; ?></tbody></table></div></section><?php endif; ?>
  <?php if($page==='overview'): ?><section class="card mb-4"><div class="card-header bg-white"><h2 class="h5 mb-0">Recent Applications</h2></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Reference</th><th>Grower</th><th>Status</th><th>Received</th></tr></thead><tbody><?php foreach($recent as $row): ?><tr><td><?= e($row['reference']) ?></td><td><?= e($row['title']) ?></td><td><?= e(ucfirst($row['status'])) ?></td><td><?= e((string)$row['occurred_at']) ?></td></tr><?php endforeach; ?></tbody></table></div></section><?php endif; ?>
  <?php if($page!=='queues'): ?><div class="row g-3"><?php foreach($commands as [$title,$detail,$href,$feature]): if(!admin_feature_is_allowed($pdo,$feature))continue; ?><div class="col-md-6 col-xl-4"><a class="card command-card h-100 text-decoration-none" href="<?= e($href) ?>"><div class="card-body"><h3 class="h5 text-dark"><?= e($title) ?></h3><p class="text-secondary"><?= e($detail) ?></p><span class="text-success fw-semibold">Open CRUD workspace</span></div></a></div><?php endforeach; ?></div><?php endif; ?>
</main></div><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script></body></html>
