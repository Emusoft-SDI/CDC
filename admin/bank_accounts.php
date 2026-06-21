<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../lib/admin-layout.php';
$pdo = db();
admin_require($pdo);

$accounts = $pdo->query("SELECT * FROM wallet_admin_bank_accounts")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Bank Accounts</title>
<style>body{font-family: 'Inter', sans-serif; background: #f4f6f4; display: flex;}.main{flex:1; padding:30px;}.card{background:#fff; padding:20px; border-radius:12px;}
table{width:100%; border-collapse:collapse;} th,td{padding:12px; border-bottom:1px solid #eee;}</style>
</head>
<body>
<?php require_once __DIR__ . '/_sidebar.php'; ?>
<div class="main"><h1>Bank Accounts</h1>
    <div class="card">
        <table>
            <thead><tr><th>Bank</th><th>Account Name</th><th>Account Number</th></tr></thead>
            <tbody>
                <?php foreach($accounts as $a): ?>
                <tr>
                    <td><?= htmlspecialchars($a['bank_name']) ?></td>
                    <td><?= htmlspecialchars($a['account_name']) ?></td>
                    <td><?= htmlspecialchars($a['account_number']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
