<?php
declare(strict_types=1);
require_once __DIR__ . '/_field.php';

$pdo = fa_pdo();
$user = fa_require_user($pdo);
$userId = (int) $user['id'];
$report = preg_replace('/[^a-z_]/', '', (string) ($_GET['report'] ?? 'overview'));
$allowed = ['overview', 'assignments', 'visits', 'verification', 'finance', 'support'];
if (!in_array($report, $allowed, true)) { $report = 'overview'; }
$startDate = (string) ($_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days')));
$endDate = (string) ($_GET['end_date'] ?? date('Y-m-d'));
$periodStart = $startDate . ' 00:00:00';
$periodEnd = $endDate . ' 23:59:59';
$isAdmin = (string) ($user['role'] ?? '') === 'admin';
$agentWhere = $isAdmin ? '1=1' : 'assigned_to = ?';
$agentParams = $isAdmin ? [] : [$userId];
$visitWhere = $isAdmin ? '1=1' : 'agent_id = ?';
$visitParams = $isAdmin ? [] : [$userId];

function fr_scalar(PDO $pdo, string $sql, array $params = [], bool $float = false): int|float
{
    try { $stmt = $pdo->prepare($sql); $stmt->execute($params); $value = $stmt->fetchColumn(); return $float ? (float) ($value ?: 0) : (int) ($value ?: 0); } catch (Throwable $e) { return $float ? 0.0 : 0; }
}
function fr_rows(PDO $pdo, string $sql, array $params = []): array
{
    try { $stmt = $pdo->prepare($sql); $stmt->execute($params); return $stmt->fetchAll(); } catch (Throwable $e) { return []; }
}
function fr_money(float $amount): string { return 'NGN ' . number_format($amount, 2); }

$totalTasks = fr_scalar($pdo, "SELECT COUNT(*) FROM field_tasks WHERE {$agentWhere}", $agentParams);
$openTasks = fr_scalar($pdo, "SELECT COUNT(*) FROM field_tasks WHERE {$agentWhere} AND status IN ('pending','assigned','in_progress')", $agentParams);
$completedTasks = fr_scalar($pdo, "SELECT COUNT(*) FROM field_tasks WHERE {$agentWhere} AND status = 'completed'", $agentParams);
$urgentTasks = fr_scalar($pdo, "SELECT COUNT(*) FROM field_tasks WHERE {$agentWhere} AND priority IN ('urgent','high')", $agentParams);
$visits = fr_scalar($pdo, "SELECT COUNT(*) FROM farm_visits WHERE {$visitWhere} AND visited_at BETWEEN ? AND ?", array_merge($visitParams, [$periodStart, $periodEnd]));
$verified = fr_scalar($pdo, "SELECT COUNT(*) FROM farm_verifications fv JOIN field_tasks ft ON ft.farm_id = fv.farm_id WHERE {$agentWhere} AND fv.status = 'verified'", $agentParams);
$walletBalance = fr_scalar($pdo, "SELECT COALESCE(balance,0) FROM wallets WHERE user_id = ?", [$userId], true);
$walletVolume = fr_scalar($pdo, "SELECT COALESCE(SUM(amount),0) FROM wallet_transactions WHERE user_id = ? AND created_at BETWEEN ? AND ?", [$userId, $periodStart, $periodEnd], true);
$supportOpen = fr_scalar($pdo, "SELECT COUNT(*) FROM messages WHERE user_id = ? AND status IN ('open','in_progress')", [$userId]);

$assignmentRows = fr_rows($pdo, "SELECT ft.id, ft.task_type, ft.priority, ft.status, ft.due_date, ft.created_at, gf.farm_name, u.name grower_name FROM field_tasks ft JOIN grower_farms gf ON gf.id=ft.farm_id JOIN users u ON u.id=gf.user_id WHERE {$agentWhere} ORDER BY FIELD(ft.priority,'urgent','high','normal','low'), ft.created_at DESC LIMIT 150", $agentParams);
$visitRows = fr_rows($pdo, "SELECT fv.id, fv.visited_at, fv.result, fv.notes, gf.farm_name, u.name grower_name FROM farm_visits fv JOIN grower_farms gf ON gf.id=fv.farm_id JOIN users u ON u.id=gf.user_id WHERE {$visitWhere} AND fv.visited_at BETWEEN ? AND ? ORDER BY fv.visited_at DESC LIMIT 150", array_merge($visitParams, [$periodStart, $periodEnd]));
$verificationRows = fr_rows($pdo, "SELECT fv.status, fv.system_confidence_score, fv.reviewed_at, gf.farm_name, u.name grower_name FROM farm_verifications fv JOIN grower_farms gf ON gf.id=fv.farm_id JOIN users u ON u.id=gf.user_id JOIN field_tasks ft ON ft.farm_id=gf.id WHERE {$agentWhere} ORDER BY fv.reviewed_at DESC, fv.id DESC LIMIT 150", $agentParams);
$financeRows = fr_rows($pdo, "SELECT created_at, type, direction, description, reference, amount, status FROM wallet_transactions WHERE user_id = ? AND created_at BETWEEN ? AND ? ORDER BY created_at DESC LIMIT 150", [$userId, $periodStart, $periodEnd]);
$supportRows = fr_rows($pdo, "SELECT ticket_id, category, priority, status, created_at, message FROM messages WHERE user_id = ? ORDER BY created_at DESC LIMIT 150", [$userId]);

$exportRows = match ($report) {
    'assignments' => $assignmentRows,
    'visits' => $visitRows,
    'verification' => $verificationRows,
    'finance' => $financeRows,
    'support' => $supportRows,
    default => [
        ['metric' => 'Total tasks', 'value' => $totalTasks],
        ['metric' => 'Open tasks', 'value' => $openTasks],
        ['metric' => 'Completed tasks', 'value' => $completedTasks],
        ['metric' => 'Visits in period', 'value' => $visits],
        ['metric' => 'Wallet balance', 'value' => fr_money((float) $walletBalance)],
        ['metric' => 'Open support tickets', 'value' => $supportOpen],
    ],
};
if (($_GET['format'] ?? '') === 'csv') {
    app_export_csv('natcodev-field-' . $report . '-report-' . date('Ymd') . '.csv', $exportRows ? array_keys($exportRows[0]) : [], $exportRows);
}

$insights = [];
if ($urgentTasks > 0) { $insights[] = ['danger', 'Urgent assignments', $urgentTasks . ' urgent or high priority task(s) need attention.']; }
if ($openTasks > 0) { $insights[] = ['warn', 'Open field work', $openTasks . ' task(s) remain pending, assigned, or in progress.']; }
if ($supportOpen > 0) { $insights[] = ['warn', 'Support still open', $supportOpen . ' support ticket(s) need closure or follow-up.']; }
if (!$insights) { $insights[] = ['good', 'Operations stable', 'No urgent task, support, or reporting alert was detected.']; }

fa_header('Field Reports', 'Downloadable intelligence for assignments, visits, verification, wallet movement, and support activity.', $user, 'reports');
?>
<form class="fa-card fa-panel field-form" method="get" style="margin-bottom:16px">
  <input type="hidden" name="report" value="<?= e($report) ?>">
  <div class="field-grid"><label>Start<input type="date" name="start_date" value="<?= e($startDate) ?>"></label><label>End<input type="date" name="end_date" value="<?= e($endDate) ?>"></label></div>
  <div style="display:flex;gap:10px;flex-wrap:wrap"><button class="btn"><i data-lucide="refresh-cw"></i>Refresh</button><a class="btn secondary" href="?<?= e(http_build_query(array_merge($_GET, ['report' => $report, 'format' => 'csv']))) ?>"><i data-lucide="download"></i>Export CSV</a></div>
</form>
<nav style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px"><?php foreach (['overview'=>'Overview','assignments'=>'Assignments','visits'=>'Visits','verification'=>'Verification','finance'=>'Finance','support'=>'Support'] as $key=>$label): ?><a class="btn <?= $report===$key?'':'secondary' ?>" href="?<?= e(http_build_query(array_merge($_GET, ['report'=>$key, 'format'=>null]))) ?>"><?= e($label) ?></a><?php endforeach; ?></nav>
<section class="fa-kpis">
  <article class="fa-card fa-kpi"><span class="fa-icon"><i data-lucide="clipboard-list"></i></span><div><small>Total Tasks</small><b><?= (int) $totalTasks ?></b><span><?= (int) $completedTasks ?> completed</span></div></article>
  <article class="fa-card fa-kpi"><span class="fa-icon orange"><i data-lucide="timer"></i></span><div><small>Open Tasks</small><b><?= (int) $openTasks ?></b><span><?= (int) $urgentTasks ?> urgent</span></div></article>
  <article class="fa-card fa-kpi"><span class="fa-icon blue"><i data-lucide="map-pin"></i></span><div><small>Visits</small><b><?= (int) $visits ?></b><span>Selected period</span></div></article>
  <article class="fa-card fa-kpi"><span class="fa-icon gold"><i data-lucide="shield-check"></i></span><div><small>Verified Farms</small><b><?= (int) $verified ?></b><span>Linked to tasks</span></div></article>
  <article class="fa-card fa-kpi"><span class="fa-icon"><i data-lucide="wallet"></i></span><div><small>Wallet</small><b><?= e(fr_money((float) $walletBalance)) ?></b><span><?= e(fr_money((float) $walletVolume)) ?> movement</span></div></article>
  <article class="fa-card fa-kpi"><span class="fa-icon red"><i data-lucide="life-buoy"></i></span><div><small>Support</small><b><?= (int) $supportOpen ?></b><span>Open tickets</span></div></article>
</section>
<section class="fa-grid" style="margin-bottom:16px"><?php foreach ($insights as $insight): ?><article class="fa-card fa-panel span-4"><span class="badge <?= e($insight[0]) ?>"><?= e(strtoupper($insight[0])) ?></span><h2><?= e($insight[1]) ?></h2><p class="muted"><?= e($insight[2]) ?></p></article><?php endforeach; ?></section>
<section class="fa-card fa-panel"><div class="fa-panel-head"><h2><?= e(ucwords(str_replace('_', ' ', $report))) ?> Detail</h2></div><div class="fa-list">
<?php foreach ($exportRows as $row): ?><div class="fa-row"><span class="fa-icon blue"><i data-lucide="file-text"></i></span><div><?php foreach ($row as $key => $value): ?><strong><?= e((string) $key) ?>:</strong> <span class="muted"><?= e((string) $value) ?></span><br><?php endforeach; ?></div><span class="badge neutral">Report</span></div><?php endforeach; ?>
<?php if (!$exportRows): ?><div class="empty">No records found for this report.</div><?php endif; ?>
</div></section>
<?php fa_footer(); ?>