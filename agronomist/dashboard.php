<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/agronomy.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$pdo = db();
agronomy_ensure_schema($pdo);

// --- Authentication & Authorisation -------------------------------------------
$user = current_user($pdo);
if (!$user) {
    redirect_to('/login.php');
}

$allowedRoles = ['agronomist', 'agric_extensionist', 'extensionist'];
$effectiveRole = app_role_normalize((string) (($user['platform_role'] ?? '') ?: ($user['role'] ?? '')));
$isAllowedRole = in_array($effectiveRole, $allowedRoles, true);

foreach (admin_active_role_assignments($pdo, (int) $user['id']) as $assignment) {
    if (in_array(app_role_normalize((string) $assignment['role_key']), $allowedRoles, true)) {
        $isAllowedRole = true;
        break;
    }
}

if (!$isAllowedRole) {
    app_render_error_page(403, 'Only agronomists and agricultural extension staff can access the case management queue.');
}

if (!admin_feature_is_allowed($pdo, 'agronomy_advisory')) {
    app_render_error_page(403, 'The Agronomy Advisory feature is disabled for your role.');
}

// --- Lazy column additions ----------------------------------------------------
app_add_column_if_missing($pdo, 'agronomy_cases', 'reference', "VARCHAR(60) NULL");
app_add_column_if_missing($pdo, 'agronomy_cases', 'user_id', "INT NULL");
app_add_column_if_missing($pdo, 'agronomy_cases', 'farm_id', "INT NULL");
app_add_column_if_missing($pdo, 'agronomy_cases', 'assigned_to', "INT NULL");
app_add_column_if_missing($pdo, 'agronomy_cases', 'resolved_at', "DATETIME NULL");
app_add_column_if_missing($pdo, 'agronomy_recommendations', 'case_id', "INT NOT NULL");
app_add_column_if_missing($pdo, 'agronomy_recommendations', 'recommended_action', "TEXT NOT NULL");
app_add_column_if_missing($pdo, 'agronomy_recommendations', 'likely_cause', "TEXT NULL");
app_add_column_if_missing($pdo, 'agronomy_recommendations', 'inputs_needed', "TEXT NULL");
app_add_column_if_missing($pdo, 'agronomy_recommendations', 'safety_note', "TEXT NULL");
app_add_column_if_missing($pdo, 'agronomy_recommendations', 'created_by', "INT NULL");
app_add_column_if_missing($pdo, 'agronomy_recommendations', 'created_at', "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");

// Resolve columns: use requested aliases when available, otherwise fall back to
// canonical names from lib/agronomy.php so queries never break.
$caseRefCol  = app_column_exists($pdo, 'agronomy_cases', 'reference') ? 'ac.reference' : 'ac.case_ref';
$growerIdCol = app_column_exists($pdo, 'agronomy_cases', 'user_id') ? 'ac.user_id' : 'ac.grower_id';
$farmIdCol   = app_column_exists($pdo, 'agronomy_cases', 'farm_id') ? 'ac.farm_id' : 'ac.farm_id';
$assignToCol = app_column_exists($pdo, 'agronomy_cases', 'assigned_to') ? 'ac.assigned_to' : 'ac.assigned_to';
$resolvedAtCol = app_column_exists($pdo, 'agronomy_cases', 'resolved_at') ? 'ac.resolved_at' : 'ac.updated_at';

$recCaseCol  = app_column_exists($pdo, 'agronomy_recommendations', 'case_id') ? 'ar.case_id' : 'ar.case_id';
$recActionCol = app_column_exists($pdo, 'agronomy_recommendations', 'recommended_action') ? 'ar.recommended_action' : 'ar.recommended_action';
$recCauseCol  = app_column_exists($pdo, 'agronomy_recommendations', 'likely_cause') ? 'ar.likely_cause' : 'ar.likely_cause';
$recInputsCol = app_column_exists($pdo, 'agronomy_recommendations', 'inputs_needed') ? 'ar.inputs_needed' : 'ar.inputs_needed';
$recSafetyCol = app_column_exists($pdo, 'agronomy_recommendations', 'safety_note') ? 'ar.safety_note' : 'ar.safety_note';
$recCreatedBy = app_column_exists($pdo, 'agronomy_recommendations', 'created_by') ? 'ar.created_by' : 'ar.author_id';
$recCreatedAt = app_column_exists($pdo, 'agronomy_recommendations', 'created_at') ? 'ar.created_at' : 'ar.created_at';

$statuses       = agronomy_statuses();
$priorities     = agronomy_priorities();
$categories     = agronomy_categories();

// Sort order helper for priority display
$priorityOrder = ['critical' => 0, 'urgent' => 1, 'high' => 2, 'normal' => 3, 'low' => 4];

// --- KPI calculations ---------------------------------------------------------
$unresolvedStatuses = array_keys(array_filter($statuses, fn($s) => !in_array(strtolower($s), ['resolved', 'closed'], true)));
$statusPlaceholders = implode(',', array_fill(0, count($unresolvedStatuses), '?'));
$paramsResolved = array_values($unresolvedStatuses);

$sqlOpenCases      = "SELECT COUNT(*) FROM agronomy_cases WHERE status NOT IN ($statusPlaceholders)";
$sqlHighPriority   = "SELECT COUNT(*) FROM agronomy_cases WHERE priority IN ('urgent','high') AND status NOT IN ($statusPlaceholders)";
$sqlAwaiting       = "SELECT COUNT(*) FROM agronomy_cases ac JOIN users u ON u.id = {$assignToCol} WHERE {$assignToCol} IS NOT NULL AND status NOT IN ('resolved','closed') AND ac.id NOT IN (SELECT DISTINCT {$recCaseCol} FROM agronomy_recommendations WHERE {$recCaseCol} IS NOT NULL)";
$sqlResolvedToday  = "SELECT COUNT(*) FROM agronomy_cases WHERE {$resolvedAtCol} IS NOT NULL AND DATE({$resolvedAtCol}) = CURDATE()";
$sqlAvgHours       = "SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, {$resolvedAtCol})) FROM agronomy_cases WHERE {$resolvedAtCol} IS NOT NULL AND TIMESTAMPDIFF(HOUR, created_at, {$resolvedAtCol}) > 0";

try {
    $stmtO = $pdo->prepare($sqlOpenCases);
    $stmtO->execute($paramsResolved);
    $openCases = (int) $stmtO->fetchColumn();
} catch (Throwable) {
    $openCases = 0;
}

try {
    $stmtH = $pdo->prepare($sqlHighPriority);
    $stmtH->execute($paramsResolved);
    $highPriority = (int) $stmtH->fetchColumn();
} catch (Throwable) {
    $highPriority = 0;
}

try {
    $stmtA = $pdo->prepare($sqlAwaiting);
    $stmtA->execute([]);
    $awaitingResponse = (int) $stmtA->fetchColumn();
} catch (Throwable) {
    $awaitingResponse = 0;
}

try {
    $stmtR = $pdo->prepare($sqlResolvedToday);
    $stmtR->execute([]);
    $resolvedToday = (int) $stmtR->fetchColumn();
} catch (Throwable) {
    $resolvedToday = 0;
}

try {
    $stmtHr = $pdo->prepare($sqlAvgHours);
    $stmtHr->execute([]);
    $avgRows = $stmtHr->fetchColumn();
    $avgResolutionTime = $avgRows !== false && $avgRows !== null ? round((float) $avgRows, 1) : null;
} catch (Throwable) {
    $avgResolutionTime = null;
}

// --- Search / filter params ---------------------------------------------------
$searchTerm = trim((string) ($_GET['search'] ?? ''));
$filterStatus = $_GET['status'] ?? '';
$filterCategory = $_GET['category'] ?? '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 30;
$offset = ($page - 1) * $perPage;

// --- Build WHERE clause -------------------------------------------------------
$whereClauses = [];
$whereParams = [];

if ($searchTerm !== '') {
    $whereClauses[] = "(ac.title LIKE ? OR u.name LIKE ? OR ac.description LIKE ? OR ac.symptoms LIKE ? OR ac.crop_stage LIKE ?)";
    $likeVal = "%{$searchTerm}%";
    $whereParams[] = $likeVal;
    $whereParams[] = $likeVal;
    $whereParams[] = $likeVal;
    $whereParams[] = $likeVal;
    $whereParams[] = $likeVal;
}

if ($filterStatus !== '' && isset($statuses[$filterStatus])) {
    $whereClauses[] = "ac.status = ?";
    $whereParams[] = $filterStatus;
}

if ($filterCategory !== '' && isset($categories[$filterCategory])) {
    $whereClauses[] = "ac.category = ?";
    $whereParams[] = $filterCategory;
}

$whereSql = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// --- Total count for pagination -----------------------------------------------
$countSql = "SELECT COUNT(*) FROM agronomy_cases ac LEFT JOIN users u ON u.id = {$growerIdCol} {$whereSql}";
$stmtCount = $pdo->prepare($countSql);
$stmtCount->execute($whereParams);
$totalCases = (int) $stmtCount->fetchColumn();
$totalPages = max(1, (int) ceil($totalCases / $perPage));

// --- Fetch cases ---------------------------------------------------------------
$querySql = "
    SELECT ac.id, {$caseRefCol} AS case_ref, {$growerIdCol} AS grower_id, u.name AS grower_name, u.email AS grower_email,
           gf.farm_name, {$farmIdCol} AS farm_id, ac.category, ac.priority, ac.title, ac.status,
           ac.description, ac.symptoms, ac.crop_stage, ac.created_at, {$assignToCol} AS assigned_to,
           assignee.name AS assigned_name, assignee.email AS assigned_email
    FROM agronomy_cases ac
    LEFT JOIN users u ON u.id = {$growerIdCol}
    LEFT JOIN grower_farms gf ON gf.id = ac.farm_id AND gf.deleted_at IS NULL
    LEFT JOIN users assignee ON assignee.id = {$assignToCol}
    {$whereSql}
    ORDER BY FIELD(ac.priority, 'urgent','high','normal','low'), ac.updated_at DESC, ac.created_at DESC
    LIMIT ? OFFSET ?
";
$stmtCases = $pdo->prepare($querySql);
$bindAll = array_merge($whereParams, [$perPage, $offset]);
$stmtCases->execute($bindAll);
$cases = $stmtCases->fetchAll();

// Cache recommended-case IDs for fast lookup
$caseIds = array_column($cases, 'id');
$hasRecommendations = [];
if ($caseIds) {
    $placeholders = implode(',', array_fill(0, count($caseIds), '?'));
    $stmtRecs = $pdo->prepare("SELECT DISTINCT {$recCaseCol} FROM agronomy_recommendations WHERE {$recCaseCol} IN ({$placeholders})");
    $stmtRecs->execute($caseIds);
    foreach ($stmtRecs->fetchAll(PDO::FETCH_COLUMN) as $id) {
        $hasRecommendations[(int) $id] = true;
    }
}

// Get all agronomists for dropdowns
$agronomists = $pdo->query("
    SELECT id, name, email FROM users
    WHERE role = 'admin' OR platform_role IN ('agronomist','agric_extensionist','extensionist') OR is_agronomist = 1 OR is_extensionist = 1
    ORDER BY name ASC
")->fetchAll();

// Build breadcrumb path
$breadcrumbHtml = <<<HTML
    <nav class="breadcrumb">
        <a href="/dashboard/index.php"><i class="fas fa-home"></i> Home</a>
        <span class="sep">/</span>
        <span class="current">Agronomy Case Queue</span>
    </nav>
HTML;

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Agronomy Case Queue - NATCODEV</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{--green:#075f2a;--deep:#053b1c;--line:#dfe8d8;--bg:#f6faf4;--ink:#101828;--muted:#667085;--gold:#c69320;--red:#b42318;--blue:#175cd3;--card:#fff}
*{box-sizing:border-box}body{margin:0;background:var(--bg);font-family:"Segoe UI",Arial,sans-serif;color:var(--ink)}
a{text-decoration:none;color:inherit}.shell{display:grid;grid-template-columns:260px 1fr;min-height:100vh}
.side{background:linear-gradient(180deg,#06451f,#092f22);color:#fff;padding:22px 18px;position:sticky;top:0;height:100vh;overflow:auto}
.brand{display:flex;gap:12px;align-items:center;padding-bottom:18px;border-bottom:1px solid rgba(255,255,255,.16)}
.brand img{width:52px;height:52px;border-radius:50%;background:#fff}.brand strong{font-size:1.2rem}
.brand small{display:block;color:#bff0ce;font-weight:400}
.person{margin:18px 0;padding:14px;border:1px solid rgba(255,255,255,.16);border-radius:8px;background:rgba(255,255,255,.08)}
.person strong{display:block;font-size:.9rem}.person small{color:#bff0ce;font-size:.8rem}
.nav{display:grid;gap:8px;margin-top:18px}
.nav a{display:flex;gap:10px;align-items:center;padding:12px;border-radius:8px;color:#eef8ef;font-weight:700;font-size:.92rem;transition:background .15s}
.nav a.active,.nav a:hover{background:#118b42}
.main{min-width:0}
.topbar{height:64px;background:#fff;border-bottom:1px solid var(--line);display:flex;align-items:center;padding:0 24px;gap:16px;position:sticky;top:0;z-index:10}
.chip{padding:5px 12px;background:#e8f5e1;border-radius:20px;font-size:.78rem;font-weight:700;color:var(--deep)}
.logout-link{margin-left:auto;color:var(--red);font-weight:700;display:flex;align-items:center;gap:6px;font-size:.85rem}
.logout-link:hover{text-decoration:underline}
.content{padding:24px;max-width:1400px}

/* Breadcrumb */
.breadcrumb{display:flex;gap:6px;align-items:center;margin-bottom:20px;font-size:.88rem;color:var(--muted)}
.breadcrumb a{color:var(--green);font-weight:600}
.breadcrumb a:hover{text-decoration:underline}
.breadcrumb .sep{color:#ccc;font-weight:300}
.breadcrumb .current{color:var(--ink);font-weight:700}

/* Section heading */
.section-header{margin-bottom:20px}
.section-header h1{font-size:1.6rem;margin:0 0 4px 0}
.section-header p{color:var(--muted);margin:0;font-size:.92rem}

/* KPI cards */
.kpi-row{display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:24px}
.kpi-card{background:var(--card);border:1px solid var(--line);border-radius:10px;padding:16px;box-shadow:0 1px 3px rgba(0,0,0,.04)}
.kpi-card .kpi-icon{width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center;margin-bottom:10px;font-size:1rem}
.kpi-card .kpi-value{font-size:1.5rem;font-weight:800;line-height:1.2}
.kpi-card .kpi-label{font-size:.78rem;color:var(--muted);text-transform:uppercase;font-weight:600;margin-top:2px}
.kpi-blue .kpi-icon{background:#dbeafe;color:var(--blue)}.kpi-green .kpi-icon{background:#dcfce7;color:var(--green)}
.kpi-gold .kpi-icon{background:#fef3c7;color:var(--gold)}.kpi-red .kpi-icon{background:#fee2e2;color:var(--red)}
.kpi-teal .kpi-icon{background:#ccfbf1;color:#0d9488}

/* Filters bar */
.filters-bar{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:16px;align-items:center}
.filters-bar select,.filters-bar input[type="text"]{padding:8px 12px;border:1px solid var(--line);border-radius:6px;background:#fff;font-size:.88rem}
.filters-bar select:focus,.filters-bar input[type="text"]:focus{outline:none;border-color:var(--green);box-shadow:0 0 0 2px rgba(7,95,42,.15)}
.filters-bar input[type="text"]{min-width:220px}
.filter-search-btn{padding:8px 16px;background:var(--green);color:#fff;border:none;border-radius:6px;font-weight:700;cursor:pointer;font-size:.88rem}
.filter-search-btn:hover{background:#087443}
.filter-clear{padding:8px 14px;background:#fff;border:1px solid var(--line);border-radius:6px;font-weight:600;color:var(--muted);cursor:pointer;font-size:.88rem}
.filter-clear:hover{border-color:var(--green);color:var(--green)}

/* Table */
.table-wrap{background:var(--card);border:1px solid var(--line);border-radius:10px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.04)}
.table-scroll{overflow-x:auto}
table.queue-table{width:100%;border-collapse:collapse}
table.queue-table th{padding:12px 14px;text-align:left;font-size:.72rem;text-transform:uppercase;color:var(--muted);font-weight:700;background:#f0f7ed;border-bottom:1px solid var(--line)}
table.queue-table td{padding:12px 14px;border-bottom:1px solid var(--line);font-size:.88rem;vertical-align:middle}
table.queue-table tr:last-child td{border-bottom:none}
table.queue-table tr:hover{background:#fafff7}

.ref-link{color:var(--blue);font-weight:600;cursor:pointer}
.ref-link:hover{text-decoration:underline}

.badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:.74rem;font-weight:700;white-space:nowrap}
.badge-priority-critical{background:#fee2e2;color:#b42318}
.badge-priority-urgent{background:#fed3d3;color:#b42318}
.badge-priority-high{background:#fde68a;color:#92400e}
.badge-priority-normal{background:#d1fae5;color:#065f46}
.badge-priority-low{background:#e0f2fe;color:#075985}

.badge-status-open{background:#dbeafe;color:#1e40af}
.badge-status-assigned{background:#e0e7ff;color:#3730a3}
.badge-status-under_review{background:#fef3c7;color:#92400e}
.badge-status-recommendation_issued{background:#d1fae5;color:#065f46}
.badge-status-follow_up_needed{background:#ede9fe;color:#5b21b6}
.badge-status-resolved{background:#e0f2fe;color:#075985}
.badge-status-closed{background:#f1f5f9;color:#475569}

.action-link{color:var(--green);font-weight:600;font-size:.85rem;white-space:nowrap}
.action-link:hover{text-decoration:underline}

.empty-msg{text-align:center;padding:40px 20px;color:var(--muted);font-style:italic}

/* Pagination */
.pagination{display:flex;align-items:center;justify-content:center;gap:6px;padding:16px}
.page-link,.page-current{padding:6px 12px;border:1px solid var(--line);border-radius:6px;font-size:.85rem;font-weight:600;background:#fff;cursor:pointer}
.page-current{background:var(--green);color:#fff;border-color:var(--green)}
.page-link:hover{border-color:var(--green);color:var(--green)}

/* Responsive */
@media(max-width:1024px){
    .shell{grid-template-columns:220px 1fr}
    .kpi-row{grid-template-columns:repeat(3,1fr)}
}
@media(max-width:768px){
    .shell{grid-template-columns:1fr}
    .side{height:auto;position:relative;padding:16px}
    .nav{flex-direction:row;flex-wrap:wrap}
    .main{min-width:100vw}
    .topbar{padding:0 16px}
    .content{padding:16px}
    .kpi-row{grid-template-columns:repeat(2,1fr);gap:10px}
    .filters-bar{flex-direction:column}
    .filters-bar select,.filters-bar input[type="text"]{width:100%}
    .filters-bar button{width:100%}
    table.queue-table{min-width:600px}
}
</style>
</head>
<body>
<div class="shell">
<!-- Sidebar -->
<aside class="side">
    <div class="brand">
        <img src="/assets/logo/natcodev.jpeg" alt="NATCODEV">
        <span><strong>NATCODEV</strong><small>Agronomy Advisor</small></span>
    </div>
    <div class="person">
        <strong><?= e($user['name'] ?? 'Advisor') ?></strong>
        <small><?= e($effectiveRole) ?></small>
        <br><span style="font-size:.78rem;color:#bff0ce"><?= e($user['email'] ?? '') ?></span>
    </div>
    <nav class="nav">
        <a class="active" href="dashboard.php"><i class="fas fa-inbox"></i> View Case Queue</a>
        <a href="dashboard.php?view=search"><i class="fas fa-search"></i> Search Growers / Farms</a>
        <a href="dashboard.php?view=add_case"><i class="fas fa-plus-circle"></i> Add New Case</a>
        <a href="/academy/index.php"><i class="fas fa-graduation-cap"></i> Academy Resources</a>
        <a href="/dashboard/profile.php"><i class="fas fa-user-cog"></i> Profile &amp; Settings</a>
        <a href="/logout.php"><i class="fas fa-right-from-bracket"></i> Logout</a>
    </nav>
</aside>

<!-- Main content -->
<div class="main">
    <header class="topbar">
        <span class="chip"><i class="fas fa-seedling"></i> Agronomy Advisory</span>
        <a class="logout-link" href="/logout.php"><i class="fas fa-right-from-bracket"></i> Logout</a>
    </header>

    <div class="content">
        <?= $breadcrumbHtml ?>

        <div class="section-header">
            <h1><i class="fas fa-clipboard-list"></i> Agronomy Case Queue</h1>
            <p>Manage and resolve grower agronomy support requests. Track progress, issue recommendations, and monitor resolution times.</p>
        </div>

        <!-- KPI Row -->
        <div class="kpi-row">
            <div class="kpi-card kpi-blue">
                <div class="kpi-icon"><i class="fas fa-folder-open"></i></div>
                <div class="kpi-value"><?= number_format($openCases) ?></div>
                <div class="kpi-label">Open Cases</div>
            </div>
            <div class="kpi-card kpi-red">
                <div class="kpi-icon"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="kpi-value"><?= number_format($highPriority) ?></div>
                <div class="kpi-label">High Priority</div>
            </div>
            <div class="kpi-card kpi-gold">
                <div class="kpi-icon"><i class="fas fa-hourglass-half"></i></div>
                <div class="kpi-value"><?= number_format($awaitingResponse) ?></div>
                <div class="kpi-label">Awaiting Response</div>
            </div>
            <div class="kpi-card kpi-green">
                <div class="kpi-icon"><i class="fas fa-calendar-check"></i></div>
                <div class="kpi-value"><?= number_format($resolvedToday) ?></div>
                <div class="kpi-label">Resolved Today</div>
            </div>
            <div class="kpi-card kpi-teal">
                <div class="kpi-icon"><i class="fas fa-clock"></i></div>
                <div class="kpi-value"><?= $avgResolutionTime !== null ? $avgResolutionTime . ' hrs' : 'N/A' ?></div>
                <div class="kpi-label">Avg Resolution Time</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-bar">
            <form method="get" action="dashboard.php" style="display:flex;gap:10px;width:100%;align-items:center;flex-wrap:wrap;">
                <input type="hidden" name="view" value="<?= e($_GET['view'] ?? '') ?>">
                <input type="text" name="search" placeholder="Search by title, grower, symptoms&hellip;" value="<?= e($searchTerm) ?>" style="flex:1;min-width:180px;padding:8px 12px;border:1px solid var(--line);border-radius:6px;font-size:.88rem">
                <select name="status" style="min-width:140px;padding:8px 12px;border:1px solid var(--line);border-radius:6px;font-size:.88rem">
                    <option value="">All Statuses</option>
                    <?php foreach ($statuses as $key => $label): ?><option value="<?= e($key) ?>" <?= $filterStatus === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                </select>
                <select name="category" style="min-width:160px;padding:8px 12px;border:1px solid var(--line);border-radius:6px;font-size:.88rem">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $key => $label): ?><option value="<?= e($key) ?>" <?= $filterCategory === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                </select>
                <button type="submit" class="filter-search-btn"><i class="fas fa-search"></i> Filter</button>
                <?php if ($searchTerm || $filterStatus || $filterCategory): ?><a href="dashboard.php" class="filter-clear"><i class="fas fa-times"></i> Clear</a><?php endif; ?>
            </form>
        </div>

        <!-- Case Queue Table -->
        <div class="table-wrap">
            <div class="table-scroll">
                <table class="queue-table">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Grower / Farm</th>
                            <th>Category</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Assigned To</th>
                            <th>Submitted Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$cases): ?>
                        <tr><td colspan="8" class="empty-msg">No cases match your current filters. Try adjusting your search or clearing filters.</td></tr>
                        <?php else: ?>
                        <?php foreach ($cases as $case):
                            $recIndicator = isset($hasRecommendations[(int) $case['id']]) ? '<i class="fas fa-comment-medical" style="color:var(--green);margin-left:4px;font-size:.75rem;" title="Has recommendation(s)"></i>' : '';
                        ?>
                        <tr>
                            <td>
                                <a class="ref-link" href="case-view.php?id=<?= (int) $case['id'] ?>"><?= e($case['case_ref'] ?? '—') ?></a>
                                <?= $recIndicator ?>
                            </td>
                            <td>
                                <strong><?= e($case['grower_name'] ?? 'Unknown') ?></strong>
                                <?php if ($case['farm_name']): ?><br><small style="color:var(--muted)"><?= e($case['farm_name']) ?></small><?php endif; ?>
                            </td>
                            <td><span class="badge"><?= e($categories[$case['category']] ?? $case['category']) ?></span></td>
                            <td>
                                <?php
                                    $pri = strtolower((string) $case['priority']);
                                    $priClass = str_starts_with($pri, 'crit') ? 'badge-priority-critical' : ($pri === 'urgent' ? 'badge-priority-urgent' : ($pri === 'high' ? 'badge-priority-high' : ($pri === 'normal' ? 'badge-priority-normal' : 'badge-priority-low')));
                                ?>
                                <span class="badge <?= $priClass ?>"><?= e($priorities[$pri] ?? ucfirst($pri)) ?></span>
                            </td>
                            <td>
                                <?php
                                    $st = strtolower((string) $case['status']);
                                    $stKey = str_replace(' ', '_', $st);
                                    $stClass = str_starts_with($stKey, 'badge-status-') ? 'badge-status-' . $stKey : 'badge-status-open';
                                    // Map to known classes
                                    if (!in_array($st, $statuses, true)) {
                                        $stClass = 'badge-status-open';
                                    }
                                ?>
                                <span class="badge badge-status-<?= e(str_replace(' ','_',$case['status'])) ?>"><?= e($statuses[$case['status']] ?? $case['status']) ?></span>
                            </td>
                            <td><?= e($case['assigned_name'] ?: 'Unassigned') ?></td>
                            <td><?= e($case['created_at'] ? date('M j, Y g:i A', strtotime((string) $case['created_at'])) : '-') ?></td>
                            <td>
                                <a class="action-link" href="case-view.php?id=<?= (int) $case['id'] ?>"><i class="fas fa-eye"></i> View</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?><a class="page-link" href="dashboard.php?page=<?= $page - 1 ?>&search=<?= rawurlencode($searchTerm) ?>&status=<?= urlencode($filterStatus) ?>&category=<?= urlencode($filterCategory) ?>">&laquo; Prev</a><?php endif; ?>
                <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                    <?php if ($p === $page): ?><span class="page-current"><?= $p ?></span><?php else: ?><a class="page-link" href="dashboard.php?page=<?= $p ?>&search=<?= rawurlencode($searchTerm) ?>&status=<?= urlencode($filterStatus) ?>&category=<?= urlencode($filterCategory) ?>"><?= $p ?></a><?php endif; ?>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?><a class="page-link" href="dashboard.php?page=<?= $page + 1 ?>&search=<?= rawurlencode($searchTerm) ?>&status=<?= urlencode($filterStatus) ?>&category=<?= urlencode($filterCategory) ?>">Next &raquo;</a><?php endif; ?>
            </div>
            <?php endif; ?>

            <div style="padding:12px 14px;border-top:1px solid var(--line);font-size:.82rem;color:var(--muted);display:flex;justify-content:space-between;align-items:center;">
                <span>Showing <?= $cases ? $offset + 1 : 0 ?>&ndash;<?= min($offset + $perPage, $totalCases) ?> of <?= number_format($totalCases) ?> cases</span>
                <span>Last updated: <?= date('g:i A') ?></span>
            </div>
        </div>
    </div>
</div>
</div>
</body>
</html>
