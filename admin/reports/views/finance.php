<?php
$burnRemaining = max(0.0, (float) $budgeted - (float) $spent);
?>
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-3">
    <div class="card h-100 rp-kpi" style="border-top-color:#0f6b3c"><div class="card-body">
      <div class="text-secondary small">Wallet Volume (Period)</div>
      <div class="display-6 fw-bold text-dark" style="font-size:1.35rem"><?= report_money((float) $walletVolume) ?></div>
      <div class="small fw-bold <?= $walletGrowth >= 0 ? 'text-success' : 'text-danger' ?> mt-1"><i class="fas fa-arrow-<?= $walletGrowth >= 0 ? 'up' : 'down' ?>"></i> <?= abs($walletGrowth) ?>% vs prior period</div>
    </div></div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card h-100 rp-kpi" style="border-top-color:#2374c6"><div class="card-body">
      <div class="text-secondary small">Budget Allocated</div>
      <div class="display-6 fw-bold text-dark" style="font-size:1.35rem"><?= report_money((float) $budgeted) ?></div>
      <div class="small text-muted">for <?= $scopeState !== '' ? e($scopeState) : 'all states' ?></div>
    </div></div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card h-100 rp-kpi" style="border-top-color:#f79009"><div class="card-body">
      <div class="text-secondary small">Budget Spent</div>
      <div class="display-6 fw-bold text-dark" style="font-size:1.35rem"><?= report_money((float) $spent) ?></div>
      <div class="small text-muted"><?= $budgetBurn ?>% utilization</div>
    </div></div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card h-100 rp-kpi" style="border-top-color:#d92d20"><div class="card-body">
      <div class="text-secondary small">Budget Burn Rate</div>
      <div class="display-6 fw-bold text-dark"><?= $budgetBurn ?>%</div>
      <div class="small text-muted"><?= report_money((float) $spent) ?> of <?= report_money((float) $budgeted) ?></div>
    </div></div>
  </div>
</div>

<div class="row g-4 mb-4">
  <div class="col-xl-6">
    <div class="card h-100">
      <div class="card-header bg-white fw-bold"><i class="fas fa-wallet text-success me-2"></i>Wallet Transaction Types</div>
      <div class="card-body"><div style="height:280px"><canvas id="chartWalletTypes"></canvas></div></div>
    </div>
  </div>
  <div class="col-xl-6">
    <div class="card h-100">
      <div class="card-header bg-white fw-bold"><i class="fas fa-chart-column text-primary me-2"></i>Wallet Activity by Day (Last 10 Days)</div>
      <div class="card-body"><div style="height:280px"><canvas id="chartWalletDaily"></canvas></div></div>
    </div>
  </div>
</div>

<div class="row g-4 mb-4">
  <div class="col-xl-6">
    <div class="card h-100">
      <div class="card-header bg-white fw-bold"><i class="fas fa-chart-line text-success me-2"></i>Monthly Marketplace Revenue (12 Months)</div>
      <div class="card-body"><div style="height:280px"><canvas id="chartMonthlyRevenue"></canvas></div></div>
    </div>
  </div>
  <div class="col-xl-6">
    <div class="card h-100">
      <div class="card-header bg-white fw-bold"><i class="fas fa-gauge-high text-danger me-2"></i>Budget Burn Rate</div>
      <div class="card-body">
        <div class="rp-meter"><div class="rp-meter-fill" style="width:<?= min($budgetBurn, 100) ?>%"><?= round($budgetBurn, 1) ?>%</div></div>
        <div class="d-flex justify-content-between small mt-3"><span>Spent: <?= report_money((float) $spent) ?></span><span>Allocated: <?= report_money((float) $budgeted) ?></span></div>
        <div class="small text-muted mt-2">Remaining headroom: <?= report_money($burnRemaining) ?></div>
      </div>
    </div>
  </div>
</div>

<div class="card mb-4">
  <div class="card-header bg-white fw-bold"><i class="fas fa-table-list text-primary me-2"></i>Wallet Transaction Mix <span class="badge text-bg-secondary ms-2"><?= count($wtLabels) ?> segments</span></div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="table-light"><tr><th>Segment</th><th class="text-end">Volume</th></tr></thead>
        <tbody>
        <?php foreach ($wtLabels as $i => $label): ?>
          <tr>
            <td class="fw-semibold"><?= e((string) $label) ?></td>
            <td class="text-end"><?= report_money((float) ($wtValues[$i] ?? 0)) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$wtLabels): ?><tr><td colspan="2" class="text-center text-secondary py-4">No wallet activity in this period.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
if (<?= count($wtLabels) ?> > 0) {
new Chart(document.getElementById('chartWalletTypes'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($wtLabels) ?>,
    datasets: [{
      label: 'Volume (NGN)',
      data: <?= json_encode($wtValues) ?>,
      backgroundColor: window.rpColors.slice(0, <?= count($wtLabels) ?>),
      borderRadius: 4
    }]
  },
  options: {responsive: true, maintainAspectRatio: false, plugins: {legend: {display: false}}, scales: {y: {beginAtZero: true, ticks: {callback: window.rpMoneyTick}}}}
});
}

new Chart(document.getElementById('chartWalletDaily'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($wlbl) ?>,
    datasets: [{
      label: 'Wallet volume (NGN)',
      data: <?= json_encode($walletDaily) ?>,
      backgroundColor: 'rgba(139,92,246,.65)',
      borderRadius: 4,
      barPercentage: .6
    }]
  },
  options: {responsive: true, maintainAspectRatio: false, plugins: {legend: {display: false}}, scales: {y: {beginAtZero: true, ticks: {callback: window.rpMoneyTick}}}}
});

new Chart(document.getElementById('chartMonthlyRevenue'), {
  type: 'line',
  data: {
    labels: <?= json_encode($monthlyLabels) ?>,
    datasets: [{
      label: 'Revenue (NGN)',
      data: <?= json_encode($monthlyOrders) ?>,
      borderColor: '#0f6b3c',
      backgroundColor: 'rgba(15,107,60,.10)',
      fill: true,
      tension: .35,
      pointRadius: 2,
      pointHoverRadius: 5,
      borderWidth: 2
    }]
  },
  options: {responsive: true, maintainAspectRatio: false, plugins: {legend: {display: false}}, scales: {y: {beginAtZero: true, ticks: {callback: window.rpMoneyTick}}}}
});
</script>
