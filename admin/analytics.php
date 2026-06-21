<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../lib/admin-layout.php';

$pdo = db();
admin_ensure_schema($pdo);
admin_require($pdo);

$applicationTrend = [];
try {
    $applicationTrend = $pdo->query("
        SELECT DATE(created_at) label, COUNT(*) total
        FROM applications
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
        ORDER BY label
    ")->fetchAll();
} catch (Throwable $e) {
    $applicationTrend = [];
}

$statusCounts = $pdo->query("
    SELECT
      SUM(CASE WHEN confirmed = 1 THEN 1 ELSE 0 END) confirmed,
      SUM(CASE WHEN confirmed = 0 THEN 1 ELSE 0 END) pending
    FROM applications
")->fetch() ?: ['confirmed' => 0, 'pending' => 0];

admin_page_start('Analytics', [
    'active' => 'analytics.php',
    'description' => 'Track application flow, confirmation progress, and operational activity trends.',
    'wide' => true,
]);
?>
<section class="stats">
  <div class="stat"><span>Confirmed</span><div class="metric"><?= (int) $statusCounts['confirmed'] ?></div></div>
  <div class="stat"><span>Pending</span><div class="metric"><?= (int) $statusCounts['pending'] ?></div></div>
</section>

<section class="grid">
  <div class="panel">
    <h2>Applications: Last 30 Days</h2>
    <canvas id="applicationTrend" height="130"></canvas>
  </div>
  <div class="panel">
    <h2>Confirmation Status</h2>
    <canvas id="statusChart" height="130"></canvas>
  </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const trend = <?= json_encode($applicationTrend, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
new Chart(document.getElementById('applicationTrend'), {
  type: 'line',
  data: {
    labels: trend.map(row => row.label),
    datasets: [{ label: 'Applications', data: trend.map(row => Number(row.total)), borderColor: '#1f8a55', backgroundColor: 'rgba(31,138,85,.12)', fill: true, tension: .25 }]
  }
});
new Chart(document.getElementById('statusChart'), {
  type: 'doughnut',
  data: {
    labels: ['Confirmed', 'Pending'],
    datasets: [{ data: [<?= (int) $statusCounts['confirmed'] ?>, <?= (int) $statusCounts['pending'] ?>], backgroundColor: ['#1f8a55', '#c9a227'] }]
  }
});
</script>
<?php admin_page_end(); ?>
