<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../lib/admin-layout.php';
$pdo = db();
admin_require($pdo);

$payouts = $pdo->query("SELECT * FROM marketplace_orders ORDER BY settled_at DESC LIMIT 50")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Marketplace Payouts</title>
<style>
    body{font-family: 'Inter', sans-serif; background: #f4f6f4; display: flex; margin:0;}
    .main{flex:1; padding:30px;}
    .card{background:#fff; padding:20px; border-radius:12px;}
    table{width:100%; border-collapse:collapse;} th,td{padding:12px; border-bottom:1px solid #eee; text-align:left;}
    .badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
    .badge-success { background: #dcfce7; color: #166534; }
    .badge-pending { background: #fef3c7; color: #92400e; }
</style>
</head>
<body>
<?php require_once __DIR__ . '/_sidebar.php'; ?>
<div class="main">
    <h1>Marketplace Payouts</h1>
    <div class="card">
        <table>
            <thead><tr><th>Order Ref</th><th>Buyer</th><th>Amount</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
                <?php foreach($payouts as $p): ?>
                <tr>
                    <td><?= htmlspecialchars($p['order_ref']) ?></td>
                    <td><?= htmlspecialchars($p['buyer_name']) ?></td>
                    <td>₦<?= number_format($p['total_amount'], 2) ?></td>
                    <td><span class="badge <?= $p['payout_status'] === 'approved' ? 'badge-success' : 'badge-pending' ?>"><?= htmlspecialchars($p['payout_status']) ?></span></td>
                    <td><a href="payout_details.php?id=<?= $p['id'] ?>">Manage</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
