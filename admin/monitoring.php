<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/admin-layout.php';

session_start();
$pdo = db();
admin_ensure_schema($pdo);
admin_require($pdo);

$checks = [];
try {
    $checks[] = ['name' => 'Database', 'status' => 'ok', 'detail' => 'Connected, ' . (int) $pdo->query("SELECT COUNT(*) FROM applications")->fetchColumn() . ' applications'];
} catch (Throwable $e) {
    $checks[] = ['name' => 'Database', 'status' => 'error', 'detail' => 'Connection failed'];
}
$checks[] = ['name' => 'Email Service', 'status' => function_exists('mail') ? 'ok' : 'warning', 'detail' => function_exists('mail') ? 'mail() available' : 'mail() unavailable'];
$free = @disk_free_space(dirname(__DIR__));
$total = @disk_total_space(dirname(__DIR__));
$freePercent = $free && $total ? round(($free / $total) * 100) : 0;
$checks[] = ['name' => 'Storage', 'status' => $freePercent > 15 ? 'ok' : 'warning', 'detail' => $freePercent . '% free'];
$checks[] = ['name' => 'Payments', 'status' => admin_setting($pdo, 'paystack_secret_key') !== '' ? 'ok' : 'warning', 'detail' => admin_setting($pdo, 'paystack_secret_key') !== '' ? 'Configured' : 'No secret key configured'];

$trend = $pdo->query("
    SELECT HOUR(created_at) hour, COUNT(*) total
    FROM applications
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    GROUP BY HOUR(created_at)
    ORDER BY hour
")->fetchAll();

$logs = app_table_exists($pdo, 'audit_log')
    ? $pdo->query("SELECT created_at, action, description FROM audit_log ORDER BY created_at DESC LIMIT 20")->fetchAll()
    : [];

admin_page_start('System Health', [
    'active' => 'monitoring.php',
    'description' => 'Check service status, recent application activity, and admin audit events.',
    'wide' => true,
]);
?>
<section class="stats">
  <?php foreach ($checks as $check): ?>
    <div class="stat">
      <span><?= e($check['name']) ?></span>
      <div><span class="badge <?= e($check['status']) ?>"><?= e(ucfirst($check['status'])) ?></span></div>
      <p class="meta"><?= e($check['detail']) ?></p>
    </div>
  <?php endforeach; ?>
</section>

<section class="panel">
  <h2>Applications: Last 24 Hours</h2>
  <canvas id="trafficChart" height="100"></canvas>
</section>

<section class="panel" style="margin-top:18px;">
  <h2>Recent Activity</h2>
  <table>
    <thead><tr><th>Time</th><th>Event</th><th>Description</th></tr></thead>
    <tbody>
      <?php foreach ($logs as $log): ?>
        <tr><td><?= e(date('M j, g:i A', strtotime((string) $log['created_at']))) ?></td><td><?= e($log['action']) ?></td><td><?= e($log['description']) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$logs): ?><tr><td colspan="3">No audit activity yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</section>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const trend = <?= json_encode($trend, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
new Chart(document.getElementById('trafficChart'), {
  type: 'line',
  data: {
    labels: trend.map(row => `${row.hour}:00`),
    datasets: [{ label: 'Applications', data: trend.map(row => Number(row.total)), borderColor: '#1f8a55', backgroundColor: 'rgba(31,138,85,.12)', fill: true }]
  }
});
</script>
<?php admin_page_end(); ?>
