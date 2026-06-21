<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../lib/admin-layout.php';
$pdo = db();
admin_require($pdo);

// Fetch Payments with details
$payments = $pdo->query("
    SELECT r.*, u.name user_name, u.email, w.title course_title
    FROM webinar_registrations r
    JOIN users u ON u.id = r.user_id
    JOIN webinars w ON w.id = r.webinar_id
    WHERE r.payment_status = 'paid' 
    ORDER BY r.registered_at DESC 
    LIMIT 50
")->fetchAll();

// Fetch summary stats for chart
$stats = $pdo->query("
    SELECT DATE(registered_at) as date, COUNT(*) as count
    FROM webinar_registrations
    WHERE payment_status = 'paid'
    GROUP BY DATE(registered_at)
    ORDER BY date ASC
    LIMIT 7
")->fetchAll();

$chartLabels = json_encode(array_column($stats, 'date'));
$chartData = json_encode(array_column($stats, 'count'));
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Academy Payments</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
    body{font-family: 'Inter', sans-serif; background: #f4f6f4; display: flex; margin:0;}
    .main{flex:1; padding:30px;}
    .card{background:#fff; padding:20px; border-radius:12px; margin-bottom:20px;}
    table{width:100%; border-collapse:collapse;} th,td{padding:12px; border-bottom:1px solid #eee; text-align:left;}
</style>
</head>
<body>
<?php require_once __DIR__ . '/_sidebar.php'; ?>
<div class="main">
    <h1>Academy Payments</h1>
    
    <div class="card">
        <canvas id="paymentChart" height="50"></canvas>
    </div>

    <div class="card">
        <table>
            <thead><tr><th>User</th><th>Email</th><th>Course</th><th>Date</th></tr></thead>
            <tbody>
                <?php foreach($payments as $p): ?>
                <tr>
                    <td><?= htmlspecialchars($p['user_name']) ?></td>
                    <td><?= htmlspecialchars($p['email']) ?></td>
                    <td><?= htmlspecialchars($p['course_title']) ?></td>
                    <td><?= htmlspecialchars($p['registered_at']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
    const ctx = document.getElementById('paymentChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= $chartLabels ?>,
            datasets: [{
                label: 'Successful Payments',
                data: <?= $chartData ?>,
                backgroundColor: 'rgba(31, 138, 85, 0.5)'
            }]
        }
    });
</script>
</body>
</html>
