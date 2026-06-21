<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../lib/admin-layout.php';

$pdo = db();
admin_ensure_schema($pdo);
admin_require($pdo);

admin_page_start('Validation Stats', [
    'active' => 'validation-stats.php',
    'description' => 'Monitor API validation results for identity and farm documents.',
    'wide' => true,
]);
?>
<section class="stats">
  <div class="stat"><span>Total Validations</span><div class="metric" id="totalValidations">0</div></div>
  <div class="stat"><span>Success Rate</span><div class="metric" id="successRate">0%</div></div>
  <div class="stat"><span>Failed</span><div class="metric" id="failedValidations">0</div></div>
  <div class="stat"><span>Avg Response</span><div class="metric" id="avgResponseTime">0s</div></div>
</section>

<section class="grid">
  <div class="panel"><h2>Validation Trend</h2><canvas id="validationTrendChart" height="130"></canvas></div>
  <div class="panel"><h2>Document Types</h2><canvas id="documentTypeChart" height="130"></canvas></div>
  <div class="panel"><h2>State Performance</h2><canvas id="statePerformanceChart" height="130"></canvas></div>
</section>

<section class="panel" style="margin-top:18px;">
  <h2>Recent Activity</h2>
  <table id="recentActivity">
    <thead><tr><th>Timestamp</th><th>User</th><th>Document</th><th>Status</th><th>Response</th></tr></thead>
    <tbody><tr><td colspan="5">Loading...</td></tr></tbody>
  </table>
</section>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const charts = {};

async function loadValidationStats() {
  const response = await fetch('../api/validation-stats.php');
  const stats = await response.json();
  const totals = stats.totals || {};
  document.getElementById('totalValidations').textContent = totals.total || 0;
  document.getElementById('successRate').textContent = `${totals.success_rate || 0}%`;
  document.getElementById('failedValidations').textContent = totals.failed || 0;
  document.getElementById('avgResponseTime').textContent = `${totals.avg_response_time || 0}s`;
  renderCharts(stats);
  renderRecent(stats.recent_activity || []);
}

function makeChart(id, config) {
  if (charts[id]) charts[id].destroy();
  charts[id] = new Chart(document.getElementById(id), config);
}

function renderCharts(stats) {
  const trends = stats.trends || {dates: [], success: [], failed: []};
  makeChart('validationTrendChart', {
    type: 'line',
    data: { labels: trends.dates || [], datasets: [
      { label: 'Successful', data: trends.success || [], borderColor: '#1f8a55' },
      { label: 'Failed', data: trends.failed || [], borderColor: '#a32020' }
    ]}
  });
  makeChart('documentTypeChart', {
    type: 'pie',
    data: { labels: Object.keys(stats.by_document_type || {}), datasets: [{ data: Object.values(stats.by_document_type || {}), backgroundColor: ['#1f8a55', '#1a5276', '#c9a227', '#8fc27d'] }] }
  });
  makeChart('statePerformanceChart', {
    type: 'bar',
    data: { labels: Object.keys(stats.by_state || {}), datasets: [{ label: 'Success Rate', data: Object.values(stats.by_state || {}), backgroundColor: '#1a5276' }] }
  });
}

function renderRecent(activity) {
  const tbody = document.querySelector('#recentActivity tbody');
  tbody.innerHTML = activity.length ? activity.map(item => `<tr><td>${escapeHtml(item.timestamp)}</td><td>${escapeHtml(item.user_name)}</td><td>${escapeHtml(item.document_type)}</td><td><span class="badge ${item.status === 'valid' ? 'verified' : 'rejected'}">${escapeHtml(item.status)}</span></td><td>${escapeHtml(item.response_time || 0)}s</td></tr>`).join('') : '<tr><td colspan="5">No validation activity yet.</td></tr>';
}

function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
}

document.addEventListener('DOMContentLoaded', loadValidationStats);
</script>
<?php admin_page_end(); ?>
