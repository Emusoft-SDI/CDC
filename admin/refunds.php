<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../lib/admin-layout.php';

$pdo = db();
admin_require($pdo);

// Fetch Refund Requests
$refunds = $pdo->query("
    SELECT ar.*, u.name as user_name 
    FROM academy_refund_requests ar 
    LEFT JOIN users u ON u.id = ar.user_id 
    ORDER BY ar.requested_at DESC
")->fetchAll();

function wx_e($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Refunds</title>
<style>body{font-family: 'Inter', sans-serif; background: #f4f6f4; display: flex;}.main{flex:1; padding:30px;}.card{background:#fff; padding:20px; border-radius:12px;}
table{width:100%; border-collapse:collapse;} th,td{padding:12px; border-bottom:1px solid #eee;}</style>
</head>
<body>
<?php require_once __DIR__ . '/_sidebar.php'; ?>
<div class="main">
    <h1>Refund Requests</h1>
    <div class="card">
        <table>
            <thead><tr><th>User</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
                <?php foreach($refunds as $r): ?>
                <tr>
                    <td><?= wx_e($r['user_name']) ?></td>
                    <td>₦<?= number_format($r['amount'], 2) ?></td>
                    <td><?= wx_e($r['status']) ?></td>
                    <td><?= wx_e($r['requested_at']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
