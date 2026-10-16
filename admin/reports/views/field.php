<?php
$agentLabels = array_map(fn($r) => substr((string) $r['agent'], 0, 24), $fieldRows);
$agentVisits = array_map(fn($r) => (int) $r['visits'], $fieldRows);
$tasksLabels = ['Open Tasks', 'Completed Visits', 'Farms Verified'];
$tasksData = [(int) $pendingTasks, (int) $fieldVisits, (int) $verifiedFarms];
?>
<?php if ((string) $scopeState !== ''): ?>
<div class="alert alert-info d-flex align-items-start mb-3">
  <i class="fas fa-filter mt-1 me-2"></i>
  <div class="flex-grow-1"><strong>Scope: <?= e($scopeState) ?></strong><br><span class="small">Field operations filtered to this scope.</span></div>
  <a class="btn btn-sm btn-outline-primary ms-2" href="<?= e($exportHref) ?>"><i class="fas fa-download me-1"></i>Export CSV</a>
</div>
<?php endif; ?>
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-3">
    <div class="card h-100 rp-kpi" style="border-top-color:#14b8a6"><div class="card-body">
      <div class="text-secondary small">Completed Visits</div>
      <div class="display-6 fw-bold text-dark"><?= number_format($fieldVisits) ?></div>
      <div class="small fw-bold <?= $visitGrowth >= 0 ? 'text-success' : 'text-danger' ?> mt-1"><i class="fas fa-arrow-<?= $visitGrowth >= 0 ? 'up' : 'down' ?>"></i> <?= abs($visitGrowth) ?>% vs prior period</div>
    </div></div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card h-100 rp-kpi" style="border-top-color:#f79009"><div class="card-body">
      <div class="text-secondary small">Open Field Tasks</div>
      <div class="display-6 fw-bold text-dark"><?= number_format($pendingTasks) ?></div>
      <div class="small text-muted">assigned or in progress</div>
    </div></div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card h-100 rp-kpi" style="border-top-color:#0f6b3c"><div class="card-body">
      <div class="text-secondary small">Verified Farms</div>
      <div class="display-6 fw-bold text-dark"><?= number_format($verifiedFarms) ?></div>
      <div class="small text-muted"><?= $verificationRate ?>% verification rate</div>
    </div></div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card h-100 rp-kpi" style="border-top-color:#8b5cf6"><div class="card-body">
      <div class="text-secondary small">Visit Purposes Tracked</div>
      <div class="display-6 fw-bold text-dark"><?= number_format(array_sum($vpData)) ?></div>
      <div class="small text-muted"><?= count($vpLabels) ?> purpose categories</div>
    </div></div>
  </div>
</div>

<div class="row g-4 mb-4">
  <div class="col-xl-6">
    <div class="card h-100">
      <div class="card-header bg-white fw-bold"><i class="fas fa-route text-info me-2"></i>30-Day Visit Trend</div>
      <div class="card-body"><div style="height:280px"><canvas id="chartVisitTrend"></canvas></div></div>
    </div>
  </div>
  <div class="col-xl-6">
    <div class="card h-100">
      <div class="card-header bg-white fw-bold"><i class="fas fa-chart-pie text-warning me-2"></i>Visit Purpose Distribution</div>
      <div class="card-body"><div style="height:280px"><canvas id="chartPurposeDistribution"></canvas></div></div>
    </div>
  </div>
</div>

<div class="row g-4 mb-4">
  <div class="col-xl-6">
    <div class="card h-100">
      <div class="card-header bg-white fw-bold"><i class="fas fa-users text-primary me-2"></i>Field Agent Workload (Visits in Period)</div>
      <div class="card-body"><div style="height:280px"><canvas id="chartAgentWorkload"></canvas></div></div>
    </div>
  </div>
  <div class="col-xl-6">
    <div class="card h-100">
      <div class="card-header bg-white fw-bold"><i class="fas fa-list-check text-primary me-2"></i>Tasks vs Completed Visits</div>
      <div class="card-body"><div style="height:280px"><canvas id="chartTasksVsVisits"></canvas></div></div>
    </div>
  </div>
</div>

<div class="card mb-4">
  <div class="card-header bg-white fw-bold"><i class="fas fa-trophy text-warning me-2"></i>Field Agent Leaderboard <span class="badge text-bg-secondary ms-2"><?= count($fieldRows) ?> agents</span></div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="table-light"><tr><th>Agent</th><th class="text-end">Visits In Period</th><th class="text-end">Open Tasks</th></tr></thead>
        <tbody>
        <?php foreach ($fieldRows as $r): ?>
          <tr>
            <td class="fw-semibold"><?= e((string) $r['agent']) ?></td>
            <td class="text-end"><?= number_format((int) $r['visits']) ?></td>
            <td class="text-end"><?= number_format((int) $r['open_tasks']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$fieldRows): ?><tr><td colspan="3" class="text-center text-secondary py-4">No field team activity yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="card mb-4">
  <div class="card-header bg-white fw-bold"><i class="fas fa-lightbulb text-warning me-2"></i>Intelligence Insights <span class="badge text-bg-success ms-2"><?= count($insights) ?> alert(s)</span></div>
  <div class="card-body">
    <?php foreach ($insights as $insight): $tone = ['ok' => 'success', 'warning' => 'warning', 'danger' => 'danger'][(string) $insight[0]] ?? 'success'; ?>
      <div class="alert alert-<?= $tone ?> d-flex align-items-start mb-2">
        <i class="fas fa-<?= $tone === 'success' ? 'check-circle' : ($tone === 'warning' ? 'exclamation-triangle' : 'times-circle') ?> mt-1 me-2"></i>
        <div><strong><?= e($insight[1]) ?></strong><br><span class="small"><?= e($insight[2]) ?></span></div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<script>
new Chart(document.getElementById('chartVisitTrend'), {
  type: 'line',
  data: {
    labels: <?= json_encode($visitLabels) ?>,
    datasets: [{
      label: 'Farm visits',
      data: <?= json_encode($visitCounts) ?>,
      borderColor: '#14b8a6',
      backgroundColor: 'rgba(20,184,166,.10)',
      fill: true,
      tension: .35,
      pointRadius: 2,
      pointHoverRadius: 5,
      borderWidth: 2
    }]
  },
  options: {responsive: true, maintainAspectRatio: false, plugins: {legend: {display: false}}, scales: {y: {beginAtZero: true, ticks: {stepSize: 1}}}}
});

new Chart(document.getElementById('chartPurposeDistribution'), {
  type: 'doughnut',
  data: {
    labels: <?= json_encode($vpLabels) ?>,
    datasets: [{
      label: 'Visits',
      data: <?= json_encode($vpData) ?>,
      backgroundColor: window.rpColors.slice(0, <?= max(1, count($vpLabels)) ?>)
    }]
  },
  options: {responsive: true, maintainAspectRatio: false, cutout: '55%', plugins: {legend: {position: 'bottom', labels: {usePointStyle: true}}}}
});

new Chart(document.getElementById('chartAgentWorkload'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($agentLabels) ?>,
    datasets: [{
      label: 'Visits',
      data: <?= json_encode($agentVisits) ?>,
      backgroundColor: 'rgba(247,144,9,.72)',
      borderRadius: 4,
      barPercentage: .6
    }]
  },
  options: {indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: {legend: {display: false}}, scales: {x: {beginAtZero: true}}}
});

new Chart(document.getElementById('chartTasksVsVisits'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($tasksLabels) ?>,
    datasets: [{
      label: 'Count',
      data: <?= json_encode($tasksData) ?>,
      backgroundColor: ['rgba(247,144,9,.72)', 'rgba(20,184,166,.72)', 'rgba(15,107,60,.72)'],
      borderRadius: 6,
      barPercentage: .5
    }]
  },
  options: {responsive: true, maintainAspectRatio: false, plugins: {legend: {display: false}}, scales: {y: {beginAtZero: true}}}
});
</script>
