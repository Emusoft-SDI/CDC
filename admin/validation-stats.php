<!-- admin/validation-stats.php -->
<h2>Validation Statistics Dashboard</h2>

<!-- Key Metrics -->
<div class="metrics-grid">
  <div class="metric-card">
    <h3>Total Validations</h3>
    <div id="totalValidations">0</div>
  </div>
  <div class="metric-card">
    <h3>Success Rate</h3>
    <div id="successRate">0%</div>
  </div>
  <div class="metric-card">
    <h3>Failed Validations</h3>
    <div id="failedValidations">0</div>
  </div>
  <div class="metric-card">
    <h3>Avg. Response Time</h3>
    <div id="avgResponseTime">0s</div>
  </div>
</div>

<!-- Charts -->
<div class="charts-container">
  <div class="chart">
    <canvas id="validationTrendChart"></canvas>
  </div>
  <div class="chart">
    <canvas id="documentTypeChart"></canvas>
  </div>
  <div class="chart">
    <canvas id="statePerformanceChart"></canvas>
  </div>
</div>

<!-- Recent Activity -->
<h3>Recent Validation Activity</h3>
<table id="recentActivity">
  <thead>
    <tr>
      <th>Timestamp</th>
      <th>User</th>
      <th>Document</th>
      <th>Status</th>
      <th>Response Time</th>
    </tr>
  </thead>
  <tbody></tbody>
</table>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
async function loadValidationStats() {
  const response = await fetch('/api/validation-stats.php');
  const stats = await response.json();
  
  // Update metrics
  document.getElementById('totalValidations').textContent = stats.totals.total;
  document.getElementById('successRate').textContent = stats.totals.success_rate + '%';
  document.getElementById('failedValidations').textContent = stats.totals.failed;
  document.getElementById('avgResponseTime').textContent = stats.totals.avg_response_time + 's';
  
  // Update charts
  updateCharts(stats);
  
  // Update recent activity
  updateRecentActivity(stats.recent_activity);
}

function updateCharts(stats) {
  // Validation trend chart
  const trendCtx = document.getElementById('validationTrendChart').getContext('2d');
  new Chart(trendCtx, {
    type: 'line',
     {
      labels: stats.trends.dates,
      datasets: [{
        label: 'Successful Validations',
         stats.trends.success,
        borderColor: '#2d5016',
        fill: false
      }, {
        label: 'Failed Validations',
         stats.trends.failed,
        borderColor: '#c62828',
        fill: false
      }]
    }
  });
  
  // Document type chart
  const docCtx = document.getElementById('documentTypeChart').getContext('2d');
  new Chart(docCtx, {
    type: 'pie',
     {
      labels: Object.keys(stats.by_document_type),
       [Object.values(stats.by_document_type)],
      backgroundColor: ['#2d5016', '#8fc27d', '#c8e6c9']
    }
  });
  
  // State performance chart
  const stateCtx = document.getElementById('statePerformanceChart').getContext('2d');
  new Chart(stateCtx, {
    type: 'bar',
     {
      labels: Object.keys(stats.by_state),
      datasets: [{
        label: 'Success Rate by State',
         Object.values(stats.by_state),
        backgroundColor: '#2d5016'
      }]
    }
  });
}

function updateRecentActivity(activity) {
  const tbody = document.querySelector('#recentActivity tbody');
  tbody.innerHTML = activity.map(item => `
    <tr>
      <td>${item.timestamp}</td>
      <td>${item.user_name}</td>
      <td>${item.document_type}</td>
      <td class="${item.status === 'valid' ? 'success' : 'error'}">${item.status}</td>
      <td>${item.response_time}s</td>
    </tr>
  `).join('');
}

// Load stats on page load
document.addEventListener('DOMContentLoaded', loadValidationStats);
</script>

<style>
.metrics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0; }
.metric-card { background: #f9f9f9; padding: 20px; border-radius: 8px; text-align: center; }
.charts-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 30px 0; }
.success { color: green; }
.error { color: red; }
</style>