<?php
declare(strict_types=1);
require_once __DIR__ . '/_coordination.php';

$pdo = coord_pdo();
$user = coord_require($pdo);
$scope = coord_state_filter($pdo, $user);
$wallet = wallet_get_or_create($pdo, (int) $user['id']);
$where = $scope['where'];
$params = $scope['params'];
$apps = coord_scalar($pdo, "SELECT COUNT(*) FROM " . coord_app_from() . " WHERE {$where}", $params);
$pending = coord_scalar($pdo, "SELECT COUNT(*) FROM " . coord_app_from() . " WHERE {$where} AND " . coord_app_status_expr() . " IN ('pending','submitted','under_review','new')", $params);
$support = coord_scalar($pdo, "SELECT COUNT(*) FROM support_tickets WHERE status IN ('open','in_progress','waiting_on_user','escalated')");
$recent = coord_rows($pdo, "SELECT a.app_ref, a.name, " . coord_app_state_expr() . " state, " . coord_app_lga_expr() . " lga, " . coord_app_status_expr() . " status, a.created_at FROM " . coord_app_from() . " WHERE {$where} ORDER BY a.created_at DESC LIMIT 12", $params);
coord_header('Coordination Wallet', 'Manage coordinator allowance, wallet activity, withdrawal status, and finance support.', $user, 'wallet');
?>
<div class="kpis"><div class="kpi"><i class="fas fa-users"></i><b><?= number_format($apps) ?></b><span>Scoped registrations</span></div><div class="kpi"><i class="fas fa-hourglass-half"></i><b><?= number_format($pending) ?></b><span>Pending actions</span></div><div class="kpi"><i class="fas fa-headset"></i><b><?= number_format($support) ?></b><span>Support issues</span></div><div class="kpi"><i class="fas fa-wallet"></i><b>NGN <?= e(number_format((float) ($wallet['balance'] ?? 0), 2)) ?></b><span>Wallet balance</span></div><div class="kpi"><i class="fas fa-location-dot"></i><b><?= e((string) $scope['label']) ?></b><span>Operating scope</span></div></div>
<div class="grid"><section class="card span-8"><h2>Current Activity</h2><div class="list"><?php foreach ($recent as $row): ?><div class="row"><span><strong><?= e((string) $row['name']) ?></strong><br><small><?= e((string) $row['app_ref']) ?> / <?= e((string) $row['state']) ?> / <?= e((string) $row['lga']) ?></small></span><span class="badge"><?= e((string) $row['status']) ?></span></div><?php endforeach; ?><?php if (!$recent): ?><div class="row">No scoped activity yet.</div><?php endif; ?></div></section><section class="card span-4"><h2>Leadership Actions</h2><p><a class="btn" href="operations.php">Operations</a></p><p><a class="btn light" href="reports.php">Reports</a></p><p><a class="btn light" href="support.php">Support</a></p><p><a class="btn light" href="logout.php">Logout</a></p></section></div>
<?php coord_footer(); ?>
