<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../lib/admin-layout.php';

$pdo = db();
admin_ensure_schema($pdo);
admin_require($pdo);

// Fetch Payments with details
try {
    $payments = $pdo->query("
        SELECT p.*, u.name as user_name 
        FROM payments p 
        LEFT JOIN users u ON u.id = p.user_id 
        ORDER BY p.created_at DESC LIMIT 50
    ")->fetchAll();
} catch (PDOException $e) {
    die("Database Query Failed: " . $e->getMessage());
}

function wx_e($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Payments</title>
<style>body{font-family: 'Inter', sans-serif; background: #f4f6f4; display: flex;}.main{flex:1; padding:30px;}.card{background:#fff; padding:20px; border-radius:12px;}
table{width:100%; border-collapse:collapse;} th,td{padding:12px; border-bottom:1px solid #eee;}</style>
</head>
<body>
<?php require_once __DIR__ . '/_sidebar.php'; ?>
<div class="main">
    <h1>Payments</h1>
    <div class="card">
        <table>
            <thead><tr><th>User</th><th>Amount</th><th>Reference</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
                <?php foreach($payments as $p): ?>
                <tr>
                    <td><?= wx_e($p['user_name'] ?? 'N/A') ?></td>
                    <td>₦<?= number_format($p['amount'], 2) ?></td>
                    <td><?= wx_e($p['reference']) ?></td>
                    <td><?= wx_e($p['status']) ?></td>
                    <td><?= wx_e($p['created_at']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
