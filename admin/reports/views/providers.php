<?php if ($providersPending > $providersApproved): ?>
<div class="alert alert-warning d-flex align-items-start mb-4">
  <i class="fas fa-exclamation-triangle mt-1 me-2"></i>
  <div><strong>Provider onboarding bottleneck</strong><br><span class="small">Pending providers exceed approved providers. Review the provider registry and approve trusted suppliers to unblock listings.</span></div>
</div>
<?php endif; ?>

<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-3">
    <div class="card h-100 rp-kpi" style="border-top-color:#0f6b3c"><div class="card-body">
      <div class="text-secondary small">Approved Providers</div>
      <div class="display-6 fw-bold text-dark"><?= number_format($providersApproved) ?></div>
      <div class="small text-muted">approved or verified status</div>
    </div></div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card h-100 rp-kpi" style="border-top-color:#f79009"><div class="card-body">
      <div class="text-secondary small">Pending Approval</div>
      <div class="display-6 fw-bold text-dark"><?= number_format($providersPending) ?></div>
      <div class="small text-muted">awaiting admin review</div>
    </div></div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card h-100 rp-kpi" style="border-top-color:#2374c6"><div class="card-body">
      <div class="text-secondary small">Provider Offerings</div>
      <div class="display-6 fw-bold text-dark"><?= number_format($listingCount) ?></div>
      <div class="small text-muted">approved marketplace listings</div>
    </div></div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card h-100 rp-kpi" style="border-top-color:#8b5cf6"><div class="card-body">
      <div class="text-secondary small">Active Sellers</div>
      <div class="display-6 fw-bold text-dark"><?= number_format($sellerCount) ?></div>
      <div class="small text-muted">marketplace seller accounts</div>
    </div></div>
  </div>
</div>

<div class="row g-4 mb-4">
  <div class="col-xl-6">
    <div class="card h-100">
      <div class="card-header bg-white fw-bold"><i class="fas fa-users text-success me-2"></i>Provider Status Breakdown</div>
      <div class="card-body"><div style="height:280px"><canvas id="chartProviderStatus"></canvas></div></div>
    </div>
  </div>
  <div class="col-xl-6">
    <div class="card h-100">
      <div class="card-header bg-white fw-bold"><i class="fas fa-chart-column text-primary me-2"></i>Offerings by Seller Type</div>
      <div class="card-body"><div style="height:280px"><canvas id="chartOfferingsByType"></canvas></div></div>
    </div>
  </div>
</div>

<div class="card mb-4">
  <div class="card-header bg-white fw-bold"><i class="fas fa-table-list text-success me-2"></i>Provider Type &amp; Status Matrix <span class="badge text-bg-secondary ms-2"><?= count($providerRows) ?> segments</span></div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="table-light"><tr><th>Provider Type</th><th>Status</th><th class="text-end">Total</th></tr></thead>
        <tbody>
        <?php foreach ($providerRows as $r): ?>
          <tr>
            <td class="fw-semibold"><?= e(ucwords(str_replace('_', ' ', (string) $r['provider_type']))) ?></td>
            <td><?= e(ucfirst((string) $r['status'])) ?></td>
            <td class="text-end"><?= number_format((int) $r['total']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$providerRows): ?><tr><td colspan="3" class="text-center text-secondary py-4">No provider records yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
if (<?= count($prData) ?> > 0) {
  new Chart(document.getElementById('chartProviderStatus'), {
    type: 'doughnut',
    data: {
      labels: <?= json_encode($prLabels) ?>,
      datasets: [{
        data: <?= json_encode($prData) ?>,
        backgroundColor: window.rpColors.slice(0, <?= count($prData) ?>)
      }]
    },
    options: {responsive: true, maintainAspectRatio: false, cutout: '55%', plugins: {legend: {position: 'bottom'}}}
  });
}

if (<?= count($stLabels) ?> > 0) {
  new Chart(document.getElementById('chartOfferingsByType'), {
    type: 'bar',
    data: {
      labels: <?= json_encode($stLabels) ?>,
      datasets: [{
        label: 'Listings',
        data: <?= json_encode($stListings) ?>,
        backgroundColor: 'rgba(139,92,246,.65)',
        borderRadius: 4,
        barPercentage: .6
      }]
    },
    options: {responsive: true, maintainAspectRatio: false, plugins: {legend: {display: false}}, scales: {y: {beginAtZero: true}}}
  });
}
</script>
