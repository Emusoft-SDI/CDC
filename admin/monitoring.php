<?php
session_start();
// Admin auth check...
?>
<!DOCTYPE html>
<html>
<head>
  <title>System Health - NATCODEV</title>
  <style>
    body { font-family: Arial; margin: 20px; }
    .metric { display: inline-block; background: #f0f7eb; padding: 15px; margin: 10px; border-radius: 8px; min-width: 150px; }
    .status-ok { background: #e8f5e9; color: #2d5016; }
    .status-warning { background: #fff8e1; color: #ff8f00; }
    .status-error { background: #ffebee; color: #c62828; }
    .chart-container { width: 100%; height: 300px; margin: 20px 0; }
  </style>
</head>
<body>
  <h1>🔍 System Health Monitoring</h1>
  
  <!-- System Metrics -->
  <div class="metric status-<?= getSystemStatus('database') ?>">
    <strong>Database</strong><br>
    <?= getDatabaseStatus() ?>
  </div>
  
  <div class="metric status-<?= getSystemStatus('email') ?>">
    <strong>Email Service</strong><br>
    <?= getEmailStatus() ?>
  </div>
  
  <div class="metric status-<?= getSystemStatus('storage') ?>">
    <strong>Storage</strong><br>
    <?= getStorageStatus() ?>
  </div>
  
  <div class="metric status-<?= getSystemStatus('payments') ?>">
    <strong>Payment Gateways</strong><br>
    <?= getPaymentStatus() ?>
  </div>
  
  <!-- Real-time Charts -->
  <div class="chart-container">
    <canvas id="trafficChart"></canvas>
  </div>
  
  <!-- Recent Activity Log -->
  <h2>Recent Activity</h2>
  <table style="width:100%; border-collapse: collapse;">
    <tr style="background: #f5f5f5;">
      <th>Timestamp</th><th>Event</th><th>Status</th>
    </tr>
    <?php foreach (getRecentLogs() as $log): ?>
    <tr>
      <td><?= $log['timestamp'] ?></td>
      <td><?= htmlspecialchars($log['event']) ?></td>
      <td class="status-<?= $log['status'] ?>"><?= ucfirst($log['status']) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>

  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
    // Traffic chart
    const ctx = document.getElementById('trafficChart').getContext('2d');
    new Chart(ctx, {
      type: 'line',
       {
        labels: <?= json_encode(getLast24Hours()) ?>,
        datasets: [{
          label: 'Applications',
           <?= json_encode(getApplicationCounts()) ?>,
          borderColor: '#2d5016',
          tension: 0.1
        }]
      }
    });
  </script>
</body>
</html>

<?php
function getDatabaseStatus() {
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=natcodevcom_data", "natcodevcom_data", "XC^#3)[;*xTcm&V9");
        $count = $pdo->query("SELECT COUNT(*) FROM applications")->fetchColumn();
        return "Connected ($count records)";
    } catch (Exception $e) {
        return "Disconnected";
    }
}

function getEmailStatus() {
    // Check if mail() is working
    return function_exists('mail') ? "Operational" : "Not Available";
}

function getStorageStatus() {
    $free = disk_free_space('/var/www/html');
    $total = disk_total_space('/var/www/html');
    $percent = round(($free / $total) * 100);
    return "$percent% free";
}

function getPaymentStatus() {
    // Check if API keys exist
    $pdo = new PDO("mysql:host=localhost;dbname=natcodevcom_data", "natcodevcom_data", "XC^#3)[;*xTcm&V9");
    $keys = $pdo->query("SELECT COUNT(*) FROM settings WHERE key_name LIKE '%_key' AND value != ''")->fetchColumn();
    return $keys > 0 ? "Configured" : "Not Configured";
}

function getSystemStatus($service) {
    // Implement logic to return ok/warning/error
    return 'ok';
}

function getRecentLogs() {
    // Return recent system events
    return [
        ['timestamp' => date('Y-m-d H:i:s'), 'event' => 'Application submitted', 'status' => 'ok'],
        ['timestamp' => date('Y-m-d H:i:s', strtotime('-1 hour')), 'event' => 'Email sent', 'status' => 'ok']
    ];
}

function getLast24Hours() {
    $hours = [];
    for ($i = 23; $i >= 0; $i--) {
        $hours[] = date('H:00', strtotime("-$i hours"));
    }
    return $hours;
}

function getApplicationCounts() {
    // Return dummy data or real counts
    return array_fill(0, 24, rand(5, 20));
}
?>