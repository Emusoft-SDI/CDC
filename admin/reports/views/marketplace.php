<?php
$marketLabels = array_map(fn($r) => substr((string) $r['store_name'], 0, 30), $marketRows);
$marketValues = array_map(fn($r) => (float) $r['order_value'], $marketRows);
?>
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-3">
    <div class="card h-100 rp-kpi" style="border-top-color:#2374c6"><div class="card-body">
      <div class="text-secondary small">Orders This Period</div>
      <div class="display-6 fw-bold text-dark"><?= number_format($orderCount) ?></div>
      <div class="small fw-bold <?= $orderGrowth >= 0 ? 'text-success' : 'text-danger' ?> mt-1"><i class="fas fa-arrow-<?= $orderGrowth >= 0 ? 'up' : 'down' ?>"></i> <?= abs($orderGrowth) ?>% vs prior period</div>
    </div></div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card h-100 rp-kpi" style="border-top-color:#f79009"><div class="card-body">
      <div class="text-secondary small">Order Value</div>
      <div class="display-6 fw-bold text-dark" style="font-size:1.35rem"><?= report_money((float) $orderValue) ?></div>
      <div class="small fw-bold <?= $valueGrowth >= 0 ? 'text-success' : 'text-danger' ?> mt-1"><i class="fas fa-arrow-<?= $valueGrowth >= 0 ? 'up' : 'down' ?>"></i> <?= abs($valueGrowth) ?>% revenue change</div>
    </div></div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card h-100 rp-kpi" style="border-top-color:#0f6b3c"><div class="card-body">
      <div class="text-secondary small">Active Sellers</div>
      <div class="display-6 fw-bold text-dark"><?= number_format($sellerCount) ?></div>
      <div class="small text-muted"><?= number_format($listingCount) ?> approved listings</div>
    </div></div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card h-100 rp-kpi" style="border-top-color:#8b5cf6"><div class="card-body">
      <div class="text-secondary small">Registered Buyers</div>
      <div class="display-6 fw-bold text-dark"><?= number_format($registered) ?></div>
      <div class="small text-muted">buyer registrations on record</div>
    </div></div>
  </div>
</div>

<div class="row g-4 mb-4">
  <div class="col-xl-6">
    <div class="card h-100">
      <div class="card-header bg-white fw-bold"><i class="fas fa-chart-line text-primary me-2"></i>Daily Order Value (30 Days)</div>
      <div class="card-body"><div style="height:280px"><canvas id="chartDailyOrderValue"></canvas></div></div>
    </div>
  </div>
  <div class="col-xl-6">
    <div class="card h-100">
      <div class="card-header bg-white fw-bold"><i class="fas fa-layer-group text-success me-2"></i>Seller Type Breakdown</div>
      <div class="card-body"><div style="height:280px"><canvas id="chartSellerTypes"></canvas></div></div>
    </div>
  </div>
</div>

<div class="row g-4 mb-4">
  <div class="col-xl-6">
    <div class="card h-100">
      <div class="card-header bg-white fw-bold"><i class="fas fa-credit-card text-warning me-2"></i>Order Payment Status</div>
      <div class="card-body"><div style="height:280px"><canvas id="chartOrderStatus"></canvas></div></div>
    </div>
  </div>
  <div class="col-xl-6">
    <div class="card h-100">
      <div class="card-header bg-white fw-bold"><i class="fas fa-ranking-star text-warning me-2"></i>Seller Revenue Ranking</div>
      <div class="card-body"><div style="height:280px"><canvas id="chartSellerRanking"></canvas></div></div>
    </div>
  </div>
</div>

<div class="card mb-4">
  <div class="card-header bg-white fw-bold"><i class="fas fa-table-list text-success me-2"></i>Seller Revenue Rankings <span class="badge text-bg-primary ms-2"><?= count($marketRows) ?> sellers</span></div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="table-light"><tr><th>Seller</th><th>Type</th><th class="text-end">Listings</th><th class="text-end">Order Value</th></tr></thead>
        <tbody>
        <?php foreach ($marketRows as $r): ?>
          <tr>
            <td class="fw-semibold"><?= e((string) $r['store_name']) ?></td>
            <td><?= e(ucwords(str_replace('_', ' ', (string) $r['seller_type']))) ?></td>
            <td class="text-end"><?= number_format((int) $r['listings']) ?></td>
            <td class="text-end"><?= report_money((float) $r['order_value']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$marketRows): ?><tr><td colspan="4" class="text-center text-secondary py-4">No marketplace records yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
new Chart(document.getElementById('chartDailyOrderValue'), {
  type: 'line',
  data: {
    labels: <?= json_encode($orderLabels) ?>,
    datasets: [{
      label: 'Order value (NGN)',
      data: <?= json_encode($orderAmounts) ?>,
      borderColor: '#2374c6',
      backgroundColor: 'rgba(35,116,198,.10)',
      fill: true,
      tension: .35,
      pointRadius: 2,
      pointHoverRadius: 5,
      borderWidth: 2
    }]
  },
  options: {responsive: true, maintainAspectRatio: false, plugins: {legend: {display: false}}, scales: {y: {beginAtZero: true, ticks: {callback: window.rpMoneyTick}}}}
});

if (<?= count($stLabels) ?> > 0) {
new Chart(document.getElementById('chartSellerTypes'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($stLabels) ?>,
    datasets: [{
      label: 'Sellers',
      data: <?= json_encode($stSellers) ?>,
      backgroundColor: 'rgba(15,107,60,.72)',
      borderRadius: 4,
      barPercentage: .6
    }, {
      label: 'Listings',
      data: <?= json_encode($stListings) ?>,
      backgroundColor: 'rgba(35,116,198,.72)',
      borderRadius: 4,
      barPercentage: .6
    }]
  },
  options: {responsive: true, maintainAspectRatio: false, plugins: {legend: {position: 'bottom'}}, scales: {y: {beginAtZero: true}}}
});
}

if (<?= count($osData) ?> > 0) {
new Chart(document.getElementById('chartOrderStatus'), {
  type: 'doughnut',
  data: {
    labels: <?= json_encode($osLabels) ?>,
    datasets: [{
      data: <?= json_encode($osData) ?>,
      backgroundColor: window.rpColors.slice(0, <?= count($osData) ?>)
    }]
  },
  options: {responsive: true, maintainAspectRatio: false, cutout: '50%', plugins: {legend: {position: 'bottom'}}}
});
}

if (<?= count($marketRows) ?> > 0) {
new Chart(document.getElementById('chartSellerRanking'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($marketLabels) ?>,
    datasets: [{
      label: 'Order value (NGN)',
      data: <?= json_encode($marketValues) ?>,
      backgroundColor: 'rgba(247,144,9,.72)',
      borderRadius: 4,
      barPercentage: .6
    }]
  },
  options: {indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: {legend: {display: false}}, scales: {x: {beginAtZero: true, ticks: {callback: window.rpMoneyTick}}}}
});
}
</script>
