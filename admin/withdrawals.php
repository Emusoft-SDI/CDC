<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../lib/admin-layout.php';

$pdo = db();
admin_require($pdo);

// Pagination setup
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Fetch KPIs
$totalWithdrawals = (float)$pdo->query("SELECT SUM(amount) FROM wallet_withdrawals WHERE status = 'approved'")->fetchColumn();
$pendingWithdrawals = (int)$pdo->query("SELECT COUNT(*) FROM wallet_withdrawals WHERE status = 'pending'")->fetchColumn();

// Fetch Withdrawals with pagination
$withdrawals = $pdo->prepare("
    SELECT ww.*, u.name as user_name 
    FROM wallet_withdrawals ww 
    LEFT JOIN users u ON u.id = ww.user_id 
    ORDER BY ww.requested_at DESC LIMIT ? OFFSET ?
");
$withdrawals->execute([$limit, $offset]);
$withdrawals = $withdrawals->fetchAll();

$totalCount = (int)$pdo->query("SELECT COUNT(*) FROM wallet_withdrawals")->fetchColumn();
$totalPages = ceil($totalCount / $limit);

function wx_e($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function wx_badge($status) {
    $s = strtolower(trim($status));
    if ($s === 'approved') return 'success';
    if ($s === 'pending') return 'pending';
    return 'danger';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>NATCODEV Withdrawals</title>
    <style>
        body { font-family: 'Inter', sans-serif; background: #f4f6f4; display: flex; }
        .sidebar { width: 240px; background: #0a2418; color: #fff; min-height: 100vh; padding: 20px; }
        .main { flex: 1; padding: 30px; }
        .card { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .stats { display: flex; gap: 20px; margin-bottom: 20px; }
        .stat-card { background: #fff; padding: 20px; border-radius: 12px; border: 1px solid #e5e7eb; flex: 1; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        tr:hover { background: #f9fafb; cursor: pointer; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .pagination { margin-top: 20px; }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/_sidebar.php'; ?>
<div class="main">
    <h1>Withdrawals</h1>
    <div class="stats">
        <div class="stat-card"><div>Total Payouts</div><div style="font-size:20px; font-weight:700">₦<?= number_format($totalWithdrawals, 2) ?></div></div>
        <div class="stat-card"><div>Pending</div><div style="font-size:20px; font-weight:700"><?= $pendingWithdrawals ?></div></div>
    </div>
    <div class="card">
        <table>
            <thead><tr><th>User</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
                <?php foreach($withdrawals as $wd): ?>
                <tr onclick="window.location='withdrawal_details.php?id=<?= $wd['id'] ?>'">
                    <td><?= wx_e($wd['user_name'] ?? 'N/A') ?></td>
                    <td>₦<?= number_format($wd['amount'], 2) ?></td>
                    <td><span class="badge badge-<?= wx_badge($wd['status']) ?>"><?= wx_e($wd['status']) ?></span></td>
                    <td><?= wx_e($wd['requested_at']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="pagination">
            <?php for($i=1; $i<=$totalPages; $i++): ?>
                <a href="?page=<?= $i ?>" style="padding:5px 10px; border:1px solid #ddd; text-decoration:none; color:black; <?= $i==$page ? 'background:#eee':'' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    </div>
</div>
</body>
</html>
