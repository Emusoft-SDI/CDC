<?php
$verificationLabels = ['Verified', 'Unverified'];
$verificationData = [(int) $verifiedFarms, max(0, (int) $farmsTotal - (int) $verifiedFarms)];
?>
<?php if ((string) $scopeState !== ''): ?>
<div class="alert alert-info d-flex align-items-start mb-3">
  <i class="fas fa-filter mt-1 me-2"></i>
  <div class="flex-grow-1"><strong>Scope: <?= e($scopeState) ?></strong><br><span class="small">Grower and farm intelligence filtered to this scope.</span></div>
  <a class="btn btn-sm btn-outline-primary ms-2" href="<?= e($exportHref) ?>"><i class="fas fa-download me-1"></i>Export CSV</a>
</div>
<?php endif; ?>
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-md-4 col-xl-2">
    <div class="card h-100 rp-kpi" style="border-top-color:#0f6b3c"><div class="card-body">
      <div class="text-secondary small">Registered Growers</div>
      <div class="display-6 fw-bold text-dark"><?= number_format($farmersTotal) ?></div>
      <div class="small text-muted"><?= number_format($farmsTotal) ?> farms · <?= number_format($hectares, 1) ?> ha</div>
    </div></div>
  </div>
  <div class="col-sm-6 col-md-4 col-xl-2">
    <div class="card h-100 rp-kpi" style="border-top-color:#2374c6"><div class="card-body">
      <div class="text-secondary small">Accredited Growers</div>
      <div class="display-6 fw-bold text-dark"><?= number_format($accredited) ?></div>
      <div class="small text-muted"><?= $accreditationRate ?>% accreditation rate</div>
    </div></div>
  </div>
  <div class="col-sm-6 col-md-4 col-xl-2">
    <div class="card h-100 rp-kpi" style="border-top-color:#14b8a6"><div class="card-body">
      <div class="text-secondary small">New This Period</div>
      <div class="display-6 fw-bold text-dark"><?= number_format($farmersNew) ?></div>
      <div class="small fw-bold <?= $enrollGrowth >= 0 ? 'text-success' : 'text-danger' ?> mt-1"><i class="fas fa-arrow-<?= $enrollGrowth >= 0 ? 'up' : 'down' ?>"></i> <?= abs($enrollGrowth) ?>% growth vs prior period</div>
    </div></div>
  </div>
  <div class="col-sm-6 col-md-4 col-xl-2">
    <div class="card h-100 rp-kpi" style="border-top-color:#f79009"><div class="card-body">
      <div class="text-secondary small">Pending Documents</div>
      <div class="display-6 fw-bold text-dark"><?= number_format($pendingDocs) ?></div>
      <div class="small text-muted">awaiting verification</div>
    </div></div>
  </div>
  <div class="col-sm-6 col-md-4 col-xl-2">
    <div class="card h-100 rp-kpi" style="border-top-color:#8b5cf6"><div class="card-body">
      <div class="text-secondary small">Verified Farms</div>
      <div class="display-6 fw-bold text-dark"><?= number_format($verifiedFarms) ?></div>
      <div class="small text-muted"><?= $verificationRate ?>% of <?= number_format($farmsTotal) ?> farms</div>
    </div></div>
  </div>
</div>

<div class="row g-4 mb-4">
  <div class="col-xl-6">
    <div class="card h-100">
      <div class="card-header bg-white fw-bold"><i class="fas fa-user-check text-primary me-2"></i>Grower Accreditation Status</div>
      <div class="card-body"><div style="height:280px"><canvas id="chartAccreditation"></canvas></div></div>
    </div>
  </div>
  <div class="col-xl-6">
    <div class="card h-100">
      <div class="card-header bg-white fw-bold"><i class="fas fa-map-location-dot text-success me-2"></i>Growers by State (Top 10)</div>
      <div class="card-body"><div style="height:280px"><canvas id="chartGrowersByState"></canvas></div></div>
    </div>
  </div>
</div>

<div class="row g-4 mb-4">
  <div class="col-xl-6">
    <div class="card h-100">
      <div class="card-header bg-white fw-bold"><i class="fas fa-clipboard-check text-success me-2"></i>Farm Verification Progress</div>
      <div class="card-body"><div style="height:280px"><canvas id="chartVerification"></canvas></div></div>
    </div>
  </div>
  <div class="col-xl-6">
    <div class="card h-100">
      <div class="card-header bg-white fw-bold"><i class="fas fa-arrow-trend-up text-success me-2"></i>30-Day Enrollment Trend</div>
      <div class="card-body"><div style="height:280px"><canvas id="chartEnrollment"></canvas></div></div>
    </div>
  </div>
</div>

<div class="card mb-4">
  <div class="card-header bg-white fw-bold"><i class="fas fa-table-list text-success me-2"></i>State / LGA Breakdown <span class="badge text-bg-secondary ms-2"><?= count($stateRows) ?> areas</span></div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="table-light"><tr><th>Area</th><th class="text-end">Growers</th><th class="text-end">Farms</th><th class="text-end">Hectares</th><th class="text-end">Accredited</th><th class="text-end">Accreditation Rate</th></tr></thead>
        <tbody>
        <?php foreach ($stateRows as $r): ?>
          <tr>
            <td class="fw-semibold"><?= e((string) $r['area_name']) ?></td>
            <td class="text-end"><?= number_format((int) $r['farmers']) ?></td>
            <td class="text-end"><?= number_format((int) $r['farms']) ?></td>
            <td class="text-end"><?= number_format((float) $r['hectares'], 1) ?></td>
            <td class="text-end"><?= number_format((int) $r['accredited']) ?></td>
            <td class="text-end"><?= e(report_pct((float) $r['accredited'], (float) $r['farmers']) . '%') ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$stateRows): ?><tr><td colspan="6" class="text-center text-secondary py-4">No state-level data available for this scope.</td></tr><?php endif; ?>
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
new Chart(document.getElementById('chartAccreditation'), {
  type: 'doughnut',
  data: {
    labels: <?= json_encode($accLabels) ?>,
    datasets: [{
      label: 'Growers',
      data: <?= json_encode($accData) ?>,
      backgroundColor: window.rpColors.slice(0, <?= max(1, count($accLabels)) ?>)
    }]
  },
  options: {responsive: true, maintainAspectRatio: false, cutout: '55%', plugins: {legend: {position: 'bottom', labels: {usePointStyle: true}}}}
});

new Chart(document.getElementById('chartGrowersByState'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($stateLabels) ?>,
    datasets: [{
      label: 'Growers',
      data: <?= json_encode($stateFarmers) ?>,
      backgroundColor: 'rgba(15,107,60,.72)',
      borderRadius: 4,
      barPercentage: .6
    }]
  },
  options: {indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: {legend: {display: false}}, scales: {x: {beginAtZero: true}}}
});

new Chart(document.getElementById('chartVerification'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($verificationLabels) ?>,
    datasets: [{
      label: 'Farms',
      data: <?= json_encode($verificationData) ?>,
      backgroundColor: ['#0f6b3c', '#f1f5f9'],
      borderRadius: 4,
      barPercentage: .6
    }]
  },
  options: {responsive: true, maintainAspectRatio: false, plugins: {legend: {display: false}}, scales: {y: {beginAtZero: true}}}
});

new Chart(document.getElementById('chartEnrollment'), {
  type: 'line',
  data: {
    labels: <?= json_encode($enrollLabels) ?>,
    datasets: [{
      label: 'New growers',
      data: <?= json_encode($enrollCounts) ?>,
      borderColor: '#0f6b3c',
      backgroundColor: 'rgba(15,107,60,.10)',
      fill: true,
      tension: .35,
      pointRadius: 2,
      pointHoverRadius: 5,
      borderWidth: 2
    }]
  },
  options: {responsive: true, maintainAspectRatio: false, plugins: {legend: {display: false}}, scales: {y: {beginAtZero: true, ticks: {stepSize: 1}}}}
});
</script>
