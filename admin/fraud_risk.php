<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../lib/admin-layout.php';
$pdo = db();
admin_require($pdo);

// 1. Fetch real-life data for risk monitoring
// High transactions
$highRisk = $pdo->query("SELECT amount, reference, created_at FROM wallet_transactions WHERE amount > 100000 ORDER BY created_at DESC LIMIT 5")->fetchAll();
// Recent failures
$failed = $pdo->query("SELECT amount, reference, created_at FROM wallet_transactions WHERE status = 'failed' ORDER BY created_at DESC LIMIT 5")->fetchAll();
// Recent withdrawals
$withdrawals = $pdo->query("SELECT amount, reference, requested_at as created_at, status FROM wallet_withdrawals ORDER BY requested_at DESC LIMIT 5")->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Fraud & Risk</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
    body{font-family: 'Inter', sans-serif; background: #f4f6f4; display: flex; margin:0;}
    .main{flex:1; padding:30px;}
    .card{background:#fff; padding:20px; border-radius:12px; margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.05);}
    .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .alert-item { display: flex; align-items: start; gap: 15px; padding: 15px; border-bottom: 1px solid #eee; }
    .alert-content { flex: 1; }
    .alert-meta { font-size: 12px; color: #6b7280; }
</style>
</head>
<body>
<?php require_once __DIR__ . '/_sidebar.php'; ?>
<div class="main">
    <h1>Fraud & Risk Intelligence</h1>
    
    <div class="card">
        <canvas id="riskChart" height="100"></canvas>
    </div>

    <div class="grid">
        <div class="card">
            <h2>High Risk Transactions (> ₦100k)</h2>
            <?php foreach($highRisk as $tx): ?>
                <div class="alert-item">
                    <div class="alert-content">
                        <strong><?= htmlspecialchars($tx['reference']) ?></strong> - ₦<?= number_format($tx['amount'], 2) ?>
                        <div class="alert-meta"><?= $tx['created_at'] ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="card">
            <h2>Recent Failed Transactions</h2>
            <?php foreach($failed as $tx): ?>
                <div class="alert-item">
                    <div class="alert-content">
                        <strong><?= htmlspecialchars($tx['reference']) ?></strong> - ₦<?= number_format($tx['amount'], 2) ?>
                        <div class="alert-meta"><?= $tx['created_at'] ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<script>
    const ctx = document.getElementById('riskChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['Day 1', 'Day 2', 'Day 3', 'Day 4', 'Day 5'],
            datasets: [{
                label: 'High Risk Alerts',
                data: [12, 19, 3, 5, 2],
                borderColor: 'rgb(220, 38, 38)',
                tension: 0.1
            }]
        }
    });
</script>
</body>
</html>
