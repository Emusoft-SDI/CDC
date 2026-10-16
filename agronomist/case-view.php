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
    app_render_error_page(403, 'Only agronomists and agricultural extension staff can access case details.');
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

// Resolve column names
$caseRefCol  = app_column_exists($pdo, 'agronomy_cases', 'reference') ? 'ac.reference' : 'ac.case_ref';
$growerIdCol = app_column_exists($pdo, 'agronomy_cases', 'user_id') ? 'ac.user_id' : 'ac.grower_id';
$farmIdCol   = app_column_exists($pdo, 'agronomy_cases', 'farm_id') ? 'ac.farm_id' : 'ac.farm_id';
$assignToCol = app_column_exists($pdo, 'agronomy_cases', 'assigned_to') ? 'ac.assigned_to' : 'ac.assigned_to';
$resolvedAtCol = app_column_exists($pdo, 'agronomy_cases', 'resolved_at') ? 'ac.resolved_at' : 'ac.updated_at';

$recCaseCol     = app_column_exists($pdo, 'agronomy_recommendations', 'case_id') ? 'ar.case_id' : 'ar.case_id';
$recActionCol   = app_column_exists($pdo, 'agronomy_recommendations', 'recommended_action') ? 'ar.recommended_action' : 'ar.recommended_action';
$recCauseCol    = app_column_exists($pdo, 'agronomy_recommendations', 'likely_cause') ? 'ar.likely_cause' : 'ar.likely_cause';
$recInputsCol   = app_column_exists($pdo, 'agronomy_recommendations', 'inputs_needed') ? 'ar.inputs_needed' : 'ar.inputs_needed';
$recSafetyCol   = app_column_exists($pdo, 'agronomy_recommendations', 'safety_note') ? 'ar.safety_note' : 'ar.safety_note';
$recCreatedBy   = app_column_exists($pdo, 'agronomy_recommendations', 'created_by') ? 'ar.created_by' : 'ar.author_id';
$recCreatedAt   = app_column_exists($pdo, 'agronomy_recommendations', 'created_at') ? 'ar.created_at' : 'ar.created_at';
$recProblemCol  = app_column_exists($pdo, 'agronomy_recommendations', 'problem_observed') ? 'ar.problem_observed' : 'ar.problem_observed';
$recUrgencyCol  = app_column_exists($pdo, 'agronomy_recommendations', 'urgency') ? 'ar.urgency' : 'ar.urgency';

$statuses       = agronomy_statuses();
$priorities     = agronomy_priorities();
$categories     = agronomy_categories();

$message = '';
$error   = '';

// --- Fetch case ID from query string ------------------------------------------
$caseId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($caseId <= 0) {
    redirect_to('dashboard.php');
}

// --- Single-case fetch --------------------------------------------------------
$stmt = $pdo->prepare("
    SELECT ac.id, {$caseRefCol} AS case_ref, {$growerIdCol} AS grower_id, u.name AS grower_name, u.email AS grower_email,
           u.phone, u.profile_picture, gf.farm_name, ac.farm_id, ac.category, ac.priority, ac.title, ac.status,
           ac.description, ac.symptoms, ac.crop_stage, ac.created_at, ac.updated_at,
           {$assignToCol} AS assigned_to, assignee.name AS assigned_name, assignee.email AS assigned_email,
           {$resolvedAtCol} AS resolved_at
    FROM agronomy_cases ac
    LEFT JOIN users u ON u.id = {$growerIdCol}
    LEFT JOIN grower_farms gf ON gf.id = {$farmIdCol} AND gf.deleted_at IS NULL
    LEFT JOIN users assignee ON assignee.id = {$assignToCol}
    WHERE ac.id = ? LIMIT 1
");
$stmt->execute([$caseId]);
$case = $stmt->fetch();

if (!$case) {
    $error = 'Agronomy case not found.';
    // Still show the dashboard header but with error message
    ?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Case Not Found - NATCODEV</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{--green:#075f2a;--line:#dfe8d8;--bg:#f6faf4;--ink:#101828;--muted:#667085;--red:#b42318;--card:#fff}
*{box-sizing:border-box}body{margin:0;background:var(--bg);font-family:"Segoe UI",Arial,sans-serif;color:var(--ink)}a{text-decoration:none;color:inherit}
.shell{display:grid;grid-template-columns:260px 1fr;min-height:100vh}
.side{background:linear-gradient(180deg,#06451f,#092f22);color:#fff;padding:22px 18px;position:sticky;top:0;height:100vh;overflow:auto}
.brand{display:flex;gap:12px;align-items:center;padding-bottom:18px;border-bottom:1px solid rgba(255,255,255,.16)}.brand img{width:52px;height:52px;border-radius:50%;background:#fff}.brand strong{font-size:1.2rem}.brand small{display:block;color:#bff0ce;font-weight:400}
.person{margin:18px 0;padding:14px;border:1px solid rgba(255,255,255,.16);border-radius:8px;background:rgba(255,255,255,.08)}.person strong{display:block;font-size:.9rem}.person small{color:#bff0ce;font-size:.8rem}
.nav{display:grid;gap:8px;margin-top:18px}.nav a{display:flex;gap:10px;align-items:center;padding:12px;border-radius:8px;color:#eef8ef;font-weight:700;font-size:.92rem}.nav a.active,.nav a:hover{background:#118b42}
.main{min-width:0}.topbar{height:64px;background:#fff;border-bottom:1px solid var(--line);display:flex;align-items:center;padding:0 24px;gap:16px;position:sticky;top:0;z-index:10}
.chip{padding:5px 12px;background:#e8f5e1;border-radius:20px;font-size:.78rem;font-weight:700;color:#053b1c}
.logout-link{margin-left:auto;color:var(--red);font-weight:700;display:flex;align-items:center;gap:6px;font-size:.85rem}
.content{padding:24px;max-width:1200px}
.breadcrumb{display:flex;gap:6px;align-items:center;margin-bottom:20px;font-size:.88rem;color:var(--muted)}.breadcrumb a{color:var(--green);font-weight:600}.breadcrumb .sep{color:#ccc;font-weight:300}.breadcrumb .current{color:var(--ink);font-weight:700}
.card{background:var(--card);border:1px solid var(--line);border-radius:10px;padding:24px;margin-bottom:20px;box-shadow:0 1px 3px rgba(0,0,0,.04)}
.success{padding:12px 16px;background:#dcfce7;border:1px solid #86efac;border-radius:8px;color:#166534;font-weight:600;margin-bottom:16px}
.error{padding:12px 16px;background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;color:#b42318;font-weight:600;margin-bottom:16px}
.btn{display:inline-block;padding:8px 18px;border-radius:6px;font-weight:700;font-size:.9rem;cursor:pointer;border:none}
.btn-primary{background:var(--green);color:#fff}.btn-primary:hover{background:#087443}
.btn-light{background:#fff;border:1px solid var(--line);color:var(--ink)}.btn-light:hover{border-color:var(--green);color:var(--green)}
@media(max-width:1024px){.shell{grid-template-columns:220px 1fr}}
@media(max-width:768px){.shell{grid-template-columns:1fr}.side{height:auto;position:relative;padding:16px}}
</style></head>
<body><div class="shell"><aside class="side">
<div class="brand"><img src="/assets/logo/natcodev.jpeg" alt="NATCODEV"><span><strong>NATCODEV</strong><small>Agronomy Advisor</small></span></div>
<div class="person"><strong><?= e($user['name'] ?? 'Advisor') ?></strong><small><?= e($effectiveRole) ?></small></div>
<nav class="nav"><a href="dashboard.php"><i class="fas fa-inbox"></i> View Case Queue</a><a href="dashboard.php?view=search"><i class="fas fa-search"></i> Search Growers / Farms</a><a href="dashboard.php?view=add_case"><i class="fas fa-plus-circle"></i> Add New Case</a><a href="/academy/index.php"><i class="fas fa-graduation-cap"></i> Academy Resources</a><a href="/dashboard/profile.php"><i class="fas fa-user-cog"></i> Profile &amp; Settings</a><a href="/logout.php"><i class="fas fa-right-from-bracket"></i> Logout</a></nav></aside>
<main class="main"><header class="topbar"><span class="chip"><i class="fas fa-seedling"></i> Agronomy Advisory</span><a class="logout-link" href="/logout.php"><i class="fas fa-right-from-bracket"></i> Logout</a></header><div class="content"><div class="breadcrumb"><a href="dashboard.php"><i class="fas fa-home"></i> Home</a><span class="sep">/</span><a href="dashboard.php">Case Queue</a><span class="sep">/</span><span class="current"><?= e($caseId > 0 ? '#'.$caseId : 'Unknown') ?></span></div>
<div class="card"><h2 style="color:var(--red)"><i class="fas fa-exclamation-circle"></i> Case Not Found</h2><p>The agronomy case you are looking for does not exist or has been removed.</p><p style="margin-top:12px"><a class="btn btn-primary" href="dashboard.php"><i class="fas fa-arrow-left"></i> Back to Queue</a></p></div></div></main></div></body></html>
    <?php
    exit;
}

// --- Handle POST actions (recommendation / status update) ---------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['_csrf'] ?? null;
    if (!$token || !verify_csrf($token)) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        try {
            $action = trim((string) ($_POST['action'] ?? ''));

            if ($action === 'add_recommendation') {
                $recAction = trim((string) ($_POST['recommended_action'] ?? ''));
                if ($recAction === '') {
                    throw new RuntimeException('Recommended action is required.');
                }
                $pdo->prepare("INSERT INTO agronomy_recommendations
                    ({$recCaseCol}, {$recCreatedBy}, {$recProblemCol}, {$recCauseCol}, {$recActionCol}, {$recUrgencyCol}, {$recInputsCol}, {$recSafetyCol})
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
                    ->execute([
                        $caseId,
                        (int) ($user['id'] ?? 0),
                        trim((string) ($_POST['problem_observed'] ?? '')),
                        trim((string) ($_POST['likely_cause'] ?? '')),
                        $recAction,
                        array_key_exists((string) ($_POST['urgency'] ?? ''), $priorities) ? (string) $_POST['urgency'] : 'normal',
                        trim((string) ($_POST['inputs_needed'] ?? '')),
                        trim((string) ($_POST['safety_note'] ?? '')),
                    ]);
                // Transition case status when recommendation is issued
                $pdo->prepare("UPDATE agronomy_cases SET status = 'recommendation_issued', updated_at = CURRENT_TIMESTAMP WHERE id = ?")
                    ->execute([$caseId]);
                $message = 'Recommendation published successfully.';

            } elseif ($action === 'update_status') {
                $newStatus = (string) ($_POST['status'] ?? '');
                if (isset($statuses[$newStatus])) {
                    $pdo->prepare("UPDATE agronomy_cases SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
                        ->execute([$newStatus, $caseId]);
                    $case['status'] = $newStatus;
                    if (strtolower($newStatus) === 'resolved' && !$pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='agronomy_cases' AND COLUMN_NAME='resolved_at'")->fetch()) {
                        $pdo->exec("ALTER TABLE `agronomy_cases` ADD COLUMN `resolved_at` DATETIME NULL AFTER `updated_at`");
                    }
                    if (app_column_exists($pdo, 'agronomy_cases', 'resolved_at')) {
                        $pdo->prepare("UPDATE agronomy_cases SET resolved_at = NOW() WHERE id = ? AND (resolved_at IS NULL OR COALESCE(resolved_at,'')='')")
                            ->execute([$caseId]);
                    }
                    $message = 'Case status updated.';
                } else {
                    throw new RuntimeException('Invalid status selected.');
                }

            } elseif ($action === 'assign_agronomist') {
                $assigneeId = (int) ($_POST['assigned_to'] ?? 0);
                if ($assigneeId > 0) {
                    $pdo->prepare("UPDATE agronomy_cases SET assigned_to = ?, status = 'assigned', updated_at = CURRENT_TIMESTAMP WHERE id = ?")
                        ->execute([$assigneeId, $caseId]);
                    $case['assigned_to'] = $assigneeId;
                    $message = 'Case reassigned successfully.';
                } else {
                    $pdo->prepare("UPDATE agronomy_cases SET assigned_to = NULL, status = 'open', updated_at = CURRENT_TIMESTAMP WHERE id = ?")
                        ->execute([$caseId]);
                    $case['assigned_to'] = null;
                    $message = 'Case unassigned and reopened.';
                }
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

// --- Fetch existing recommendations for this case -----------------------------
$stmtRecs = $pdo->prepare("SELECT ar.*, u.name as author_name FROM agronomy_recommendations ar LEFT JOIN users u ON u.id = {$recCreatedBy} WHERE {$recCaseCol} = ? ORDER BY ar.created_at ASC");
$stmtRecs->execute([$caseId]);
$recommendations = $stmtRecs->fetchAll();
$hasAnyRecommendation = count($recommendations) > 0;

// Get all agronomists for assignment dropdown
$agronomists = $pdo->query("
    SELECT id, name, email FROM users
    WHERE role = 'admin' OR platform_role IN ('agronomist','agric_extensionist','extensionist') OR is_agronomist = 1 OR is_extensionist = 1
    ORDER BY name ASC
")->fetchAll();

// Compute resolution time display
$resolutionTimeDisplay = '—';
if (!empty($case['resolved_at'])) {
    $createdAt = strtotime((string) $case['created_at']);
    $resolvedAt = strtotime((string) $case['resolved_at']);
    if ($createdAt && $resolvedAt && $resolvedAt > $createdAt) {
        $hours = round(($resolvedAt - $createdAt) / 3600, 1);
        if ($hours < 1) {
            $resolutionTimeDisplay = round(($resolvedAt - $createdAt) / 60, 0) . ' minutes';
        } else {
            $resolutionTimeDisplay = $hours . ' hours';
        }
    }
}

// Status badge class helper
function status_badge_class(string $status): string
{
    $map = [
        'Open' => 'badge-status-open',
        'Assigned' => 'badge-status-assigned',
        'Under Review' => 'badge-status-under_review',
        'Recommendation Issued' => 'badge-status-recommendation_issued',
        'Follow-up Needed' => 'badge-status-follow_up_needed',
        'Resolved' => 'badge-status-resolved',
        'Closed' => 'badge-status-closed',
    ];
    return $map[$status] ?? 'badge-status-open';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($case['case_ref'] ?? '#'.$caseId) ?> — <?= e(mb_strimwidth((string) $case['title'], 0, 60, '...')) ?> | NATCODEV</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{--green:#075f2a;--deep:#053b1c;--line:#dfe8d8;--bg:#f6faf4;--ink:#101828;--muted:#667085;--gold:#c69320;--red:#b42318;--blue:#175cd3;--card:#fff}
*{box-sizing:border-box}body{margin:0;background:var(--bg);font-family:"Segoe UI",Arial,sans-serif;color:var(--ink)}
a{text-decoration:none;color:inherit}
.shell{display:grid;grid-template-columns:260px 1fr;min-height:100vh}
.side{background:linear-gradient(180deg,#06451f,#092f22);color:#fff;padding:22px 18px;position:sticky;top:0;height:100vh;overflow:auto}
.brand{display:flex;gap:12px;align-items:center;padding-bottom:18px;border-bottom:1px solid rgba(255,255,255,.16)}
.brand img{width:52px;height:52px;border-radius:50%;background:#fff}.brand strong{font-size:1.2rem}.brand small{display:block;color:#bff0ce;font-weight:400}
.person{margin:18px 0;padding:14px;border:1px solid rgba(255,255,255,.16);border-radius:8px;background:rgba(255,255,255,.08)}
.person strong{display:block;font-size:.9rem}.person small{color:#bff0ce;font-size:.8rem}
.nav{display:grid;gap:8px;margin-top:18px}
.nav a{display:flex;gap:10px;align-items:center;padding:12px;border-radius:8px;color:#eef8ef;font-weight:700;font-size:.92rem}
.nav a.active,.nav a:hover{background:#118b42}
.main{min-width:0}
.topbar{height:64px;background:#fff;border-bottom:1px solid var(--line);display:flex;align-items:center;padding:0 24px;gap:16px;position:sticky;top:0;z-index:10}
.chip{padding:5px 12px;background:#e8f5e1;border-radius:20px;font-size:.78rem;font-weight:700;color:var(--deep)}
.back-link{color:var(--green);font-weight:600;font-size:.88rem;display:flex;align-items:center;gap:6px}
.back-link:hover{text-decoration:underline}
.logout-link{margin-left:auto;color:var(--red);font-weight:700;display:flex;align-items:center;gap:6px;font-size:.85rem}

.content{padding:24px;max-width:1200px}

/* Breadcrumb */
.breadcrumb{display:flex;gap:6px;align-items:center;margin-bottom:20px;font-size:.88rem;color:var(--muted)}
.breadcrumb a{color:var(--green);font-weight:600}.breadcrumb a:hover{text-decoration:underline}
.breadcrumb .sep{color:#ccc;font-weight:300}.breadcrumb .current{color:var(--ink);font-weight:700}

/* Messages */
.success{padding:12px 16px;background:#dcfce7;border:1px solid #86efac;border-radius:8px;color:#166534;font-weight:600;margin-bottom:16px}
.error{padding:12px 16px;background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;color:#b42318;font-weight:600;margin-bottom:16px}

/* Cards */
.card{background:var(--card);border:1px solid var(--line);border-radius:10px;padding:20px;margin-bottom:16px;box-shadow:0 1px 3px rgba(0,0,0,.04)}
.card h2{margin:0 0 12px 0;font-size:1.1rem}
.card-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:16px}

/* Detail grid */
.detail-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px}
.detail-field label{display:block;font-size:.72rem;text-transform:uppercase;color:var(--muted);font-weight:700;margin-bottom:4px}
.detail-field .value{font-size:.92rem;font-weight:600}
.detail-full{grid-column:1/-1}

/* Badges */
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

/* Forms */
.form-group{margin-bottom:14px}
.form-group label{display:block;font-size:.82rem;font-weight:700;color:var(--ink);margin-bottom:5px}
.form-group input[type="text"],
.form-group input[type="date"],
.form-group select,
.form-group textarea{width:100%;padding:8px 12px;border:1px solid var(--line);border-radius:6px;font-size:.88rem;font-family:inherit;background:#fff;resize:vertical}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus{outline:none;border-color:var(--green);box-shadow:0 0 0 2px rgba(7,95,42,.15)}
textarea{min-height:80px}

/* Buttons */
.btn{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:6px;font-weight:700;font-size:.88rem;cursor:pointer;border:none;transition:background .15s}
.btn-primary{background:var(--green);color:#fff}.btn-primary:hover{background:#087443}
.btn-secondary{background:#fff;border:1px solid var(--line);color:var(--ink)}.btn-secondary:hover{border-color:var(--green);color:var(--green)}
.btn-sm{padding:6px 12px;font-size:.82rem}

/* Recommendations list */
.rec-item{border:1px solid var(--line);border-radius:8px;padding:16px;margin-bottom:12px;background:#fafcf8}
.rec-item + .rec-item{margin-top:16px}
.rec-meta{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:10px;font-size:.8rem;color:var(--muted)}
.rec-section{margin-bottom:10px}
.rec-section strong{display:block;font-size:.76rem;text-transform:uppercase;color:var(--muted);font-weight:700;margin-bottom:3px}
.rec-section p{margin:0;font-size:.9rem;line-height:1.5}

.status-update-bar{display:flex;gap:10px;align-items:center;flex-wrap:wrap;padding:14px 18px;background:#f0f7ed;border-radius:8px;margin-bottom:16px}
.status-update-bar select{padding:7px 12px;border:1px solid var(--line);border-radius:6px;font-size:.88rem}

.action-bar{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px}

/* Sidebar form container */
.aside-card{border:1px solid var(--line);border-radius:10px;padding:18px;margin-bottom:16px;background:var(--card)}
.aside-card h3{margin:0 0 12px 0;font-size:.95rem}

@media(max-width:1024px){
    .shell{grid-template-columns:220px 1fr}
    .detail-grid{grid-template-columns:repeat(2,1fr)}
}
@media(max-width:768px){
    .shell{grid-template-columns:1fr}
    .side{height:auto;position:relative;padding:16px}
    .nav{flex-direction:row;flex-wrap:wrap}
    .topbar{padding:0 16px}
    .content{padding:16px}
    .detail-grid{grid-template-columns:1fr}
    .status-update-bar,.action-bar{flex-direction:column}
    .status-update-bar select,.action-bar .btn{width:100%}
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
        <a href="dashboard.php"><i class="fas fa-inbox"></i> View Case Queue</a>
        <a href="dashboard.php?view=search"><i class="fas fa-search"></i> Search Growers / Farms</a>
        <a href="dashboard.php?view=add_case"><i class="fas fa-plus-circle"></i> Add New Case</a>
        <a href="/academy/index.php"><i class="fas fa-graduation-cap"></i> Academy Resources</a>
        <a href="/dashboard/profile.php"><i class="fas fa-user-cog"></i> Profile &amp; Settings</a>
        <a href="/logout.php"><i class="fas fa-right-from-bracket"></i> Logout</a>
    </nav>
</aside>

<!-- Main -->
<div class="main">
    <header class="topbar">
        <a class="back-link" href="dashboard.php"><i class="fas fa-arrow-left"></i> Back to Queue</a>
        <span class="chip"><i class="fas fa-seedling"></i> Agronomy Advisory</span>
        <a class="logout-link" href="/logout.php"><i class="fas fa-right-from-bracket"></i> Logout</a>
    </header>

    <div class="content">
        <!-- Breadcrumb -->
        <nav class="breadcrumb">
            <a href="/dashboard/index.php"><i class="fas fa-home"></i> Home</a>
            <span class="sep">/</span>
            <a href="dashboard.php">Case Queue</a>
            <span class="sep">/</span>
            <span class="current"><?= e($case['case_ref'] ?? '#'.$caseId) ?></span>
        </nav>

        <!-- Messages -->
        <?php if ($message): ?><p class="success"><i class="fas fa-check-circle"></i> <?= e($message) ?></p><?php endif; ?>
        <?php if ($error): ?><p class="error"><i class="fas fa-exclamation-triangle"></i> <?= e($error) ?></p><?php endif; ?>

        <div style="display:grid;grid-template-columns:1fr 360px;gap:20px">

        <!-- Left column: case details + recommendations -->
        <div>

            <!-- Case Information Card -->
            <section class="card">
                <div class="card-header">
                    <div>
                        <h2><?= e($case['case_ref'] ?? '#'.$caseId) ?> — <?= e($case['title'] ?? 'Untitled Case') ?></h2>
                        <p style="margin:0;color:var(--muted);font-size:.85rem">Submitted <?= e($case['created_at'] ? date('M j, Y \a\t g:i A', strtotime((string) $case['created_at'])) : '-') ?></p>
                    </div>
                    <div style="display:flex;gap:8px;align-items:center">
                        <?php
                            $pri = strtolower((string) $case['priority']);
                            $priClass = str_starts_with($pri, 'crit') ? 'badge-priority-critical' : ($pri === 'urgent' ? 'badge-priority-urgent' : ($pri === 'high' ? 'badge-priority-high' : ($pri === 'normal' ? 'badge-priority-normal' : 'badge-priority-low')));
                        ?>
                        <span class="badge <?= $priClass ?>"><?= e($priorities[$pri] ?? ucfirst($pri)) ?></span>
                        <?php
                            $stKey = str_replace(' ', '_', $case['status']);
                        ?>
                        <span class="badge badge-status-<?= e($stKey) ?>"><?= e($statuses[$case['status']] ?? $case['status']) ?></span>
                    </div>
                </div>

                <div class="detail-grid">
                    <div class="detail-field">
                        <label>Grower</label>
                        <div class="value"><?= e($case['grower_name'] ?? 'Unknown') ?></div>
                        <?php if ($case['grower_email']): ?><div style="font-size:.82rem;color:var(--muted)"><?= e($case['grower_email']) ?></div><?php endif; ?>
                        <?php if ($case['phone']): ?><div style="font-size:.82rem;color:var(--muted)"><?= e($case['phone']) ?></div><?php endif; ?>
                    </div>
                    <div class="detail-field">
                        <label>Farm</label>
                        <div class="value"><?= e($case['farm_name'] ?: 'No farm linked') ?></div>
                    </div>
                    <div class="detail-field">
                        <label>Category</label>
                        <div class="value"><?= e($categories[$case['category']] ?? $case['category']) ?></div>
                    </div>
                    <div class="detail-field">
                        <label>Assigned To</label>
                        <div class="value"><?= e($case['assigned_name'] ?: 'Unassigned') ?></div>
                    </div>
                    <div class="detail-field">
                        <label>Crop Stage</label>
                        <div class="value"><?= e($case['crop_stage'] ?: 'Not recorded') ?></div>
                    </div>
                    <div class="detail-field">
                        <label>Resolution Time</label>
                        <div class="value"><?= e($resolutionTimeDisplay) ?></div>
                    </div>
                    <div class="detail-field detail-full">
                        <label>Symptoms Observed</label>
                        <div class="value"><?= nl2br(e($case['symptoms'] ?: 'None recorded')) ?></div>
                    </div>
                    <div class="detail-field detail-full">
                        <label>Description</label>
                        <div class="value"><?= nl2br(e($case['description'] ?: 'No description provided.')) ?></div>
                    </div>
                </div>
            </section>

            <!-- Quick Actions: assign & status -->
            <section class="card" style="padding-top:14px;padding-bottom:14px">
                <h2 style="font-size:.95rem"><i class="fas fa-wrench"></i> Quick Actions</h2>
                <div class="action-bar">
                    <form method="post" style="display:inline-flex;gap:8px;align-items:center">
                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="assign_agronomist">
                        <select name="assigned_to" style="padding:7px 12px;border:1px solid var(--line);border-radius:6px;font-size:.85rem">
                            <option value="">— Select Agronomist —</option>
                            <option value="0"<?php if ((int)($case['assigned_to']??0)===0): ?> selected<?php endif ?>>Re-open / Unassign</option>
                            <?php foreach ($agronomists as $agr): ?><option value="<?= (int) $agr['id'] ?>"<?= (int)($case['assigned_to']??0)===(int)$agr['id']?' selected':'' ?>><?= e($agr['name']) ?></option><?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-secondary btn-sm"><i class="fas fa-user-plus"></i> Assign</button>
                    </form>
                </div>
                <div class="status-update-bar">
                    <form method="post" style="display:flex;gap:8px;align-items:center;width:100%">
                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="update_status">
                        <label style="font-size:.82rem;font-weight:700;margin:0;white-space:nowrap">Update Status:</label>
                        <select name="status">
                            <?php foreach ($statuses as $key => $label): ?>
                                <option value="<?= e($key) ?>"<?= $case['status']===$key?' selected':'' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-check"></i> Save</button>
                    </form>
                </div>
            </section>

            <!-- Recommendations Section -->
            <section class="card">
                <h2><i class="fas fa-clipboard-check"></i> Recommendations (<?= count($recommendations) ?>)</h2>

                <?php if ($recommendations): ?>
                    <?php foreach ($recommendations as $idx => $rec): ?>
                    <div class="rec-item">
                        <div class="rec-meta">
                            <span><i class="fas fa-user"></i> <?= e($rec['author_name'] ?? 'System') ?></span>
                            <span><i class="fas fa-clock"></i> <?= e($rec['created_at'] ? date('M j, Y g:i A', strtotime((string) $rec['created_at'])) : '-') ?></span>
                        </div>
                        <?php if (!empty($rec[$recProblemCol])): ?>
                        <div class="rec-section">
                            <strong>Problem Observed</strong>
                            <p><?= nl2br(e($rec[$recProblemCol])) ?></p>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($rec[$recCauseCol])): ?>
                        <div class="rec-section">
                            <strong>Likely Cause</strong>
                            <p><?= nl2br(e($rec[$recCauseCol])) ?></p>
                        </div>
                        <?php endif; ?>
                        <div class="rec-section">
                            <strong>Recommended Action</strong>
                            <p><?= nl2br(e($rec[$recActionCol])) ?></p>
                        </div>
                        <?php if (!empty($rec[$recInputsCol])): ?>
                        <div class="rec-section">
                            <strong>Inputs Needed</strong>
                            <p><?= nl2br(e($rec[$recInputsCol])) ?></p>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($rec[$recSafetyCol])): ?>
                        <div class="rec-section">
                            <strong>Safety / Environment Note</strong>
                            <p><?= nl2br(e($rec[$recSafetyCol])) ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- Recommendation entry form -->
                <?php if (!$hasAnyRecommendation): ?>
                    <div style="margin-top:16px;padding-top:16px;border-top:1px dashed var(--line)">
                        <h3 style="font-size:.95rem;margin-bottom:14px"><i class="fas fa-pen-to-square"></i> Issue Recommendation</h3>
                        <form method="post">
                            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="add_recommendation">

                            <div class="form-group">
                                <label>Problem Observed</label>
                                <textarea name="problem_observed" placeholder="Describe what was observed on the farm or reported by the grower."><?= e($_POST['problem_observed'] ?? '') ?></textarea>
                            </div>

                            <div class="form-group">
                                <label>Likely Cause</label>
                                <textarea name="likely_cause" placeholder="What do you believe is the root cause of the problem?"><?= e($_POST['likely_cause'] ?? '') ?></textarea>
                            </div>

                            <div class="form-group">
                                <label>Recommended Action <span style="color:var(--red)">*</span></label>
                                <textarea name="recommended_action" required placeholder="Detailed step-by-step recommended treatment or management practice."><?= e($_POST['recommended_action'] ?? '') ?></textarea>
                            </div>

                            <div class="form-group">
                                <label>Inputs Needed</label>
                                <textarea name="inputs_needed" placeholder="Fertilizers, pesticides, tools, equipment, or other resources required."><?= e($_POST['inputs_needed'] ?? '') ?></textarea>
                            </div>

                            <div class="form-group">
                                <label>Safety / Environment Note</label>
                                <textarea name="safety_note" placeholder="Personal protective equipment, environmental precautions, application safety warnings."><?= e($_POST['safety_note'] ?? '') ?></textarea>
                            </div>

                            <div class="form-group" style="display:flex;gap:12px;align-items:center">
                                <label style="margin:0;white-space:nowrap">Urgency</label>
                                <select name="urgency" style="width:auto;padding:7px 12px;border:1px solid var(--line);border-radius:6px;font-size:.88rem">
                                    <?php foreach ($priorities as $pk => $pl): ?>
                                        <option value="<?= e($pk) ?>"<?= (($pk==='normal')||(strtoupper($_POST['urgency']??'')==$pk))?' selected':'' ?>><?= e($pl) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div style="display:flex;gap:10px;align-items:center;margin-top:8px">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Publish Recommendation</button>
                                <span style="font-size:.82rem;color:var(--muted)">This will update the case status to "Recommendation Issued".</span>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <div style="margin-top:12px;padding:12px 16px;background:#f0f7ed;border-radius:8px;font-size:.88rem;color:var(--muted)">
                        <i class="fas fa-info-circle"></i> This case already has <?= count($recommendations) ?> recommendation(s). The recommendation form is hidden to prevent duplicate entries. To add another recommendation, update the case status first.
                    </div>
                <?php endif; ?>
            </section>
        </div>

        <!-- Right sidebar: info panel -->
        <div>
            <div class="aside-card">
                <h3><i class="fas fa-chart-pie"></i> Case Summary</h3>
                <table style="width:100%;border-collapse:collapse;font-size:.88rem">
                    <tr><td style="padding:6px 0;border-bottom:1px solid var(--line);color:var(--muted);width:40%">Reference</td><td style="padding:6px 0;border-bottom:1px solid var(--line);font-weight:600"><?= e($case['case_ref'] ?? '#'.$caseId) ?></td></tr>
                    <tr><td style="padding:6px 0;border-bottom:1px solid var(--line);color:var(--muted)">Priority</td><td style="padding:6px 0;border-bottom:1px solid var(--line);font-weight:600"><?= e($priorities[strtolower((string)$case['priority'])] ?? ucfirst($case['priority'])) ?></td></tr>
                    <tr><td style="padding:6px 0;border-bottom:1px solid var(--line);color:var(--muted)">Status</td><td style="padding:6px 0;border-bottom:1px solid var(--line);font-weight:600"><?= e($statuses[$case['status']] ?? $case['status']) ?></td></tr>
                    <tr><td style="padding:6px 0;border-bottom:1px solid var(--line);color:var(--muted)">Category</td><td style="padding:6px 0;border-bottom:1px solid var(--line);font-weight:600"><?= e($categories[$case['category']] ?? $case['category']) ?></td></tr>
                    <tr><td style="padding:6px 0;border-bottom:1px solid var(--line);color:var(--muted)">Created</td><td style="padding:6px 0;border-bottom:1px solid var(--line);font-weight:600"><?= e($case['created_at'] ? date('M j, Y', strtotime((string) $case['created_at'])) : '-') ?></td></tr>
                    <tr><td style="padding:6px 0;color:var(--muted)">Last Updated</td><td style="padding:6px 0;font-weight:600"><?= e($case['updated_at'] ? date('M j, Y g:i A', strtotime((string) $case['updated_at'])) : '-') ?></td></tr>
                </table>
            </div>

            <div class="aside-card">
                <h3><i class="fas fa-user-farming"></i> Grower Info</h3>
                <table style="width:100%;border-collapse:collapse;font-size:.88rem">
                    <tr><td style="padding:6px 0;border-bottom:1px solid var(--line);color:var(--muted;width:40%)">Name</td><td style="padding:6px 0;border-bottom:1px solid var(--line);font-weight:600"><?= e($case['grower_name'] ?? 'Unknown') ?></td></tr>
                    <tr><td style="padding:6px 0;border-bottom:1px solid var(--line);color:var(--muted)">Email</td><td style="padding:6px 0;border-bottom:1px solid var(--line);font-weight:600"><?= e($case['grower_email'] ?: '—') ?></td></tr>
                    <?php if ($case['phone']): ?>
                    <tr><td style="padding:6px 0;border-bottom:1px solid var(--line);color:var(--muted)">Phone</td><td style="padding:6px 0;border-bottom:1px solid var(--line);font-weight:600"><?= e($case['phone']) ?></td></tr>
                    <?php endif; ?>
                    <tr><td style="padding:6px 0;color:var(--muted)">Farm</td><td style="padding:6px 0;font-weight:600"><?= e($case['farm_name'] ?: 'No farm linked') ?></td></tr>
                </table>
            </div>

            <div class="aside-card">
                <h3><i class="fas fa-road"></i> Workflow Steps</h3>
                <?php
                    $workflowSteps = ['open'=>'Open','assigned'=>'Assigned','under_review'=>'Under Review','recommendation_issued'=>'Recommendation Issued','follow_up_needed'=>'Follow-up Needed','resolved'=>'Resolved','closed'=>'Closed'];
                    $order = ['open'=>1,'assigned'=>2,'under_review'=>3,'recommendation_issued'=>4,'follow_up_needed'=>5,'resolved'=>6,'closed'=>7];
                    $stepNum = $order[strtolower($case['status'])] ?? 1;
                ?>
                <div style="display:flex;flex-direction:column;gap:6px;margin-top:8px">
                    <?php foreach ($workflowSteps as $wsKey => $wsLabel):
                        $wsActive = ($order[$wsKey] ?? 0) <= $stepNum;
                        $wsCurrent = ($order[$wsKey] ?? 0) === $stepNum;
                    ?>
                    <div style="display:flex;align-items:center;gap:8px;padding:4px 0;font-size:.84rem">
                        <span style="width:22px;height:22px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:700;flex-shrink:0;
                            background:<?= $wsActive ? 'var(--green)' : '#e5e7eb' ?>;color:<?= $wsActive ? '#fff' : 'var(--muted)' ?>">
                            <?= $wsKey ?>
                        </span>
                        <span style="font-weight:<?= $wsCurrent ? '700' : '500' ?>;color:<?= $wsActive ? 'var(--ink)' : 'var(--muted)' ?>"><?= e($wsLabel) ?></span>
                        <?php if ($wsCurrent): ?><span style="font-size:.72rem;color:var(--green);font-weight:700;margin-left:auto">(current)</span><?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="aside-card">
                <h3><i class="fas fa-share"></i> Related Links</h3>
                <div style="display:flex;flex-direction:column;gap:6px;margin-top:8px">
                    <a href="dashboard.php" class="btn btn-secondary btn-sm" style="width:100%"><i class="fas fa-arrow-left"></i> Back to Case Queue</a>
                    <a href="/dashboard/search.php?q=<?= rawurlencode($case['grower_name'] ?? '') ?>" class="btn btn-secondary btn-sm" style="width:100%"><i class="fas fa-search"></i> Search This Grower</a>
                    <a href="/academy/index.php" class="btn btn-secondary btn-sm" style="width:100%"><i class="fas fa-graduation-cap"></i> Academy Resources</a>
                </div>
            </div>
        </div>

        </div><!-- end grid -->
    </div>
</div>
</div>
</body>
</html>
