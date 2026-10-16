<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-2">
    <div class="card h-100 rp-kpi" style="border-top-color:#0f6b3c"><div class="card-body">
      <div class="text-secondary small">Total Growers</div>
      <div class="display-6 fw-bold text-dark"><?= number_format($farmersTotal) ?></div>
      <div class="small text-muted"><?= number_format($farmsTotal) ?> farms · <?= number_format($hectares, 1) ?> ha</div>
      <div class="small fw-bold text-success mt-1"><i class="fas fa-arrow-up"></i> <?= number_format($farmersNew) ?> new this period</div>
    </div></div>
  </div>
  <div class="col-sm-6 col-xl-2">
    <div class="card h-100 rp-kpi" style="border-top-color:#2374c6"><div class="card-body">
      <div class="text-secondary small">Accreditation Rate</div>
      <div class="display-6 fw-bold text-dark"><?= $accreditationRate ?>%</div>
      <div class="small text-muted"><?= number_format($accredited) ?> accredited growers</div>
      <div class="small text-muted mt-1">Farm verification: <?= $verificationRate ?>%</div>
    </div></div>
  </div>
  <div class="col-sm-6 col-xl-2">
    <div class="card h-100 rp-kpi" style="border-top-color:#f79009"><div class="card-body">
      <div class="text-secondary small">Marketplace Orders</div>
      <div class="display-6 fw-bold text-dark"><?= number_format($orderCount) ?></div>
      <div class="small text-muted"><?= report_money($orderValue) ?></div>
      <div class="small fw-bold <?= $orderGrowth >= 0 ? 'text-success' : 'text-danger' ?> mt-1"><i class="fas fa-arrow-<?= $orderGrowth >= 0 ? 'up' : 'down' ?>"></i> <?= abs($orderGrowth) ?>% vs prior period</div>
    </div></div>
  </div>
  <div class="col-sm-6 col-xl-2">
    <div class="card h-100 rp-kpi" style="border-top-color:#8b5cf6"><div class="card-body">
      <div class="text-secondary small">Wallet Volume</div>
      <div class="display-6 fw-bold text-dark" style="font-size:1.35rem"><?= report_money($walletVolume) ?></div>
      <div class="small text-muted"><?= number_format($pendingDocs) ?> documents pending review</div>
    </div></div>
  </div>
  <div class="col-sm-6 col-xl-2">
    <div class="card h-100 rp-kpi" style="border-top-color:#14b8a6"><div class="card-body">
      <div class="text-secondary small">Field Visits</div>
      <div class="display-6 fw-bold text-dark"><?= number_format($fieldVisits) ?></div>
      <div class="small text-muted"><?= number_format($verifiedFarms) ?> farms verified</div>
      <div class="small text-muted mt-1"><?= number_format($pendingTasks) ?> open field tasks</div>
    </div></div>
  </div>
  <div class="col-sm-6 col-xl-2">
    <div class="card h-100 rp-kpi" style="border-top-color:#d92d20"><div class="card-body">
      <div class="text-secondary small">Providers &amp; Sellers</div>
      <div class="display-6 fw-bold text-dark"><?= number_format($providersApproved + $sellerCount) ?></div>
      <div class="small text-muted"><?= number_format($providersApproved) ?> providers · <?= number_format($sellerCount) ?> sellers</div>
      <div class="small fw-bold text-danger mt-1"><?= number_format($providersPending) ?> pending approval</div>
    </div></div>
  </div>
</div>

<div class="row g-4 mb-4">
  <div class="col-xl-6">
    <div class="card h-100">
      <div class="card-header bg-white fw-bold"><i class="fas fa-arrow-trend-up text-success me-2"></i>30-Day Grower Enrollment Trend</div>
      <div class="card-body"><div style="height:280px"><canvas id="chartEnrollment"></canvas></div></div>
    </div>
  </div>
  <div class="col-xl-6">
    <div class="card h-100">
      <div class="card-header bg-white fw-bold"><i class="fas fa-coins text-warning me-2"></i>30-Day Order Value Trend</div>
      <div class="card-body"><div style="height:280px"><canvas id="chartOrderTrend"></canvas></div></div>
    </div>
  </div>
</div>

<div class="row g-4 mb-4">
  <div class="col-xl-6">
    <div class="card h-100">
      <div class="card-header bg-white fw-bold"><i class="fas fa-chart-column text-primary me-2"></i>Monthly Marketplace Revenue (12 Months)</div>
      <div class="card-body"><div style="height:280px"><canvas id="chartMonthlyRevenue"></canvas></div></div>
    </div>
  </div>
  <div class="col-xl-6">
    <div class="card h-100">
      <div class="card-header bg-white fw-bold"><i class="fas fa-map-location-dot text-success me-2"></i>Top 10 States by Grower Count</div>
      <div class="card-body"><div style="height:280px"><canvas id="chartTopStates"></canvas></div></div>
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

<div class="card mb-4">
  <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span><i class="fas fa-map text-success me-2"></i>Geographic Distribution — Growers by State</span>
    <span class="badge text-bg-secondary ms-2"><?= number_format(array_sum($tileGrowerCounts)) ?> growers mapped</span>
  </div>
  <div class="card-body">
    <div class="rp-tilemap" role="list" aria-label="Grower distribution by state">
      <?php foreach ($reportTileLayout as [$tileName, $tileCol, $tileRow]):
        [$tileFarmers, $tileStateId] = $tileLookup($tileName);
        $tileIntensity = $maxTileFarmers > 0 ? $tileFarmers / $maxTileFarmers : 0.0;
        $tileBg = sprintf('#%02x%02x%02x', (int) round(232 + (7 - 232) * $tileIntensity), (int) round(246 + (75 - 246) * $tileIntensity), (int) round(236 + (42 - 236) * $tileIntensity));
        $tileText = $tileIntensity > 0.55 ? '#ffffff' : '#0b1f16';
        $tileSelected = $tileStateId > 0 && $tileStateId === $selectedStateId;
        $tileHref = $tileStateId > 0 ? '?' . e(http_build_query($sidebarQuery + ['page' => $subpage, 'state_id' => $tileStateId])) : null;
      ?>
      <a<?= $tileHref !== null ? ' href="' . $tileHref . '"' : '' ?> class="rp-tile<?= $tileSelected ? ' rp-tile-selected' : '' ?>" style="grid-column:<?= (int) $tileCol + 1 ?>;grid-row:<?= (int) $tileRow + 1 ?>;background:<?= $tileBg ?>;color:<?= $tileText ?>" title="<?= e($tileName) ?>: <?= number_format($tileFarmers) ?> registered growers<?= $tileStateId > 0 ? ' — click to filter this report' : '' ?>" role="listitem">
        <span class="rp-tile-name"><?= e($tileDisplayName($tileName)) ?></span>
        <span class="rp-tile-value"><?= number_format($tileFarmers) ?></span>
      </a>
      <?php endforeach; ?>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap mt-3">
      <span class="small text-muted">Fewer growers</span>
      <span class="rp-tile-legend" aria-hidden="true"></span>
      <span class="small text-muted">More growers</span>
      <span class="small text-muted ms-auto"><i class="fas fa-mouse-pointer me-1"></i>Select any state tile to filter this report to that state.</span>
    </div>
  </div>
</div>

<div class="card mb-4">
  <div class="card-header bg-white fw-bold"><i class="fas fa-table-list text-success me-2"></i><?= e($areaLabel) ?> Breakdown <span class="badge text-bg-secondary ms-2"><?= count($stateRows) ?> areas</span></div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="table-light"><tr><th>Area</th><th class="text-end">Growers</th><th class="text-end">Farms</th><th class="text-end">Hectares</th><th class="text-end">Accredited</th></tr></thead>
        <tbody>
        <?php foreach ($stateRows as $r): ?>
          <tr>
            <td class="fw-semibold"><?= e((string) $r['area_name']) ?></td>
            <td class="text-end"><?= number_format((int) $r['farmers']) ?></td>
            <td class="text-end"><?= number_format((int) $r['farms']) ?></td>
            <td class="text-end"><?= number_format((float) $r['hectares'], 1) ?></td>
            <td class="text-end"><?= number_format((int) $r['accredited']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$stateRows): ?><tr><td colspan="5" class="text-center text-secondary py-4">No state-level data available for this scope.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
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

new Chart(document.getElementById('chartOrderTrend'), {
  type: 'line',
  data: {
    labels: <?= json_encode($orderLabels) ?>,
    datasets: [{
      label: 'Order value (NGN)',
      data: <?= json_encode($orderAmounts) ?>,
      borderColor: '#f79009',
      backgroundColor: 'rgba(247,144,9,.10)',
      fill: true,
      tension: .35,
      pointRadius: 2,
      pointHoverRadius: 5,
      borderWidth: 2
    }]
  },
  options: {responsive: true, maintainAspectRatio: false, plugins: {legend: {display: false}}, scales: {y: {beginAtZero: true, ticks: {callback: window.rpMoneyTick}}}}
});

new Chart(document.getElementById('chartMonthlyRevenue'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($monthlyLabels) ?>,
    datasets: [{
      label: 'Revenue (NGN)',
      data: <?= json_encode($monthlyOrders) ?>,
      backgroundColor: 'rgba(15,107,60,.72)',
      borderRadius: 4,
      barPercentage: .6
    }]
  },
  options: {responsive: true, maintainAspectRatio: false, plugins: {legend: {display: false}}, scales: {y: {beginAtZero: true, ticks: {callback: window.rpMoneyTick}}}}
});

new Chart(document.getElementById('chartTopStates'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($stateLabels) ?>,
    datasets: [{
      label: 'Growers',
      data: <?= json_encode($stateFarmers) ?>,
      backgroundColor: window.rpColors.slice(0, <?= max(1, count($stateLabels)) ?>),
      borderRadius: 4,
      barPercentage: .6
    }]
  },
  options: {indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: {legend: {display: false}}, scales: {x: {beginAtZero: true}}}
});
</script>
