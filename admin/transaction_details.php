<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../lib/admin-layout.php';

$pdo = db();
admin_require($pdo);

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header("Location: transactions.php"); exit; }

$stmt = $pdo->prepare("
    SELECT wt.*, u.name as user_name, u.email as user_email
    FROM wallet_transactions wt 
    LEFT JOIN wallets w ON w.id = wt.wallet_id 
    LEFT JOIN users u ON u.id = w.user_id 
    WHERE wt.id = ?
");
$stmt->execute([$id]);
$tx = $stmt->fetch();

if (!$tx) { die("Transaction not found."); }

function wx_e($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Transaction Details</title>
    <style>
        body { font-family: 'Inter', sans-serif; background: #f4f6f4; display: flex; }
        .sidebar { width: 240px; background: #0a2418; color: #fff; min-height: 100vh; padding: 20px; }
        .main { flex: 1; padding: 30px; }
        .card { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .label { color: #6b7280; font-size: 12px; text-transform: uppercase; }
        .value { font-weight: 600; margin-bottom: 15px; }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/_sidebar.php'; ?>
<div class="main">
    <h1>Transaction #<?= $tx['id'] ?></h1>
    <div class="card">
        <div class="detail-grid">
            <div>
                <div class="label">User</div><div class="value"><?= wx_e($tx['user_name']) ?> (<?= wx_e($tx['user_email']) ?>)</div>
                <div class="label">Amount</div><div class="value">₦<?= number_format($tx['amount'], 2) ?></div>
            </div>
            <div>
                <div class="label">Type</div><div class="value"><?= wx_e($tx['type']) ?></div>
                <div class="label">Status</div><div class="value"><?= wx_e($tx['status']) ?></div>
                <div class="label">Reference</div><div class="value"><?= wx_e($tx['reference']) ?></div>
                <div class="label">Date</div><div class="value"><?= wx_e($tx['created_at']) ?></div>
            </div>
        </div>
        <div class="label">Description</div><div class="value"><?= wx_e($tx['description']) ?></div>
    </div>
</div>
</body>
</html>
