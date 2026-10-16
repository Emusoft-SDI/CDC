<?php
/**
 * Coordinator Coverage report view.
 *
 * Renders tier distribution, enrollment pipeline, scope coverage,
 * and coordinator assignment detail for the Coordinator Coverage
 * subpage.
 */
?>

<div class="row g-3 mb-4">
  <?php foreach ($coordTierLabels as $i => $label): ?>
  <div class="col-sm-6 col-xl-2">
    <div class="card h-100 rp-kpi" style="border-top-color:#6365f1"><div class="card-body">
      <div class="text-secondary small"><?= e($label) ?> Coordinators</div>
      <div class="display-6 fw-bold text-dark"><?= number_format($coordTierCounts[$i] ?? 0) ?></div>
      <div class="small text-muted">active assignments</div>
    </div></div>
  </div>
  <?php endforeach; ?>
</div>

<div class="row g-4 mb-4">
  <div class="col-xl-6">
    <div class="card h-100">
      <div class="card-header bg-white fw-bold"><i class="fas fa-users-crown text-primary me-2"></i>Tier Distribution</div>
      <div class="card-body"><div style="height:240px"><canvas id="chartCoordTiers"></canvas></div></div>
    </div>
  </div>
  <div class="col-xl-6">
    <div class="card h-100">
      <div class="card-header bg-white fw-bold"><i class="fas fa-route text-warning me-2"></i>Scope Coverage</div>
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div><span class="fw-bold"><?= number_format(count($coordCoverageStates) - $uncoveredStatesCount) ?></span> of <span class="fw-bold"><?= number_format(count($coordCoverageStates)) ?></span> states covered</div>
          <div><span class="badge text-bg-danger"><?= number_format($uncoveredStatesCount) ?> uncovered</span></div>
        </div>
        <div style="height:200px"><canvas id="chartStateCoverage"></canvas></div>
      </div>
    </div>
  </div>
</div>

<div class="card mb-4">
  <div class="card-header bg-white fw-bold"><i class="fas fa-table-list text-info me-2"></i>Enrollment Pipeline by Applicant Type</div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="table-light">
          <tr><th>Applicant Type</th><th>Total</th><th>Pending</th><th>Approved</th><th>Rejected</th></tr>
        </thead>
        <tbody>
          <?php foreach ($coordPipelineRows as $type => $counts): ?>
          <tr>
            <td class="fw-semibold"><?= e(ucfirst($type)) ?></td>
            <td><?= number_format($coordPipelineTotals[$type] ?? 0) ?></td>
            <td><?= number_format($counts['pending'] ?? 0) ?></td>
            <td><?= number_format($counts['approved'] ?? 0) ?></td>
            <td><?= number_format($counts['rejected'] ?? 0) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$coordPipelineRows): ?>
          <tr><td colspan="5" class="text-center text-secondary py-4">No enrollment applications found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="card mb-4">
  <div class="card-header bg-white fw-bold"><i class="fas fa-user-group text-success me-2"></i>Coordinator Assignments (latest 200)</div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr><th>Coordinator</th><th>Tier(s)</th><th>Scope(s)</th><th>Activity</th></tr>
        </thead>
        <tbody>
          <?php foreach ($coordAssignmentRows as $r): ?>
          <tr>
            <td class="fw-semibold"><?= e((string) ($r['name'] ?? '')) ?><br><small class="text-muted"><?= e((string) ($r['email'] ?? '')) ?></small></td>
            <td>
              <?php foreach (explode(',', (string) ($r['tiers'] ?? '')) as $tier): ?>
                <?php if ($tier !== ''): ?>
                  <span class="badge text-bg-secondary"><?= e(ucfirst(str_replace('_', ' ', $tier))) ?></span>
                <?php endif; ?>
              <?php endforeach; ?>
            </td>
            <td>
              <?php foreach (explode(',', (string) ($r['scopes'] ?? '')) as $scope): ?>
                <?php if ($scope !== ''): ?>
                  <small class="text-muted"><?= e($scope) ?></small>
                <?php endif; ?>
              <?php endforeach; ?>
            </td>
            <td><small class="text-muted">See User Governance for details</small></td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$coordAssignmentRows): ?>
          <tr><td colspan="4" class="text-center text-secondary py-4">No coordinator assignments found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
if (window.Chart) {
  new Chart(document.getElementById('chartCoordTiers'), {
    type: 'bar',
    data: {
      labels: <?= json_encode(array_keys($coordTierLabels)) ?>,
      datasets: [{
        label: 'Active Coordinators',
        data: <?= json_encode(array_values($coordTierCounts)) ?>,
        backgroundColor: '#6365f1'
      }]
    },
    options: {responsive: true, maintainAspectRatio: false, indexAxis: 'y', scales: {x: {beginAtZero: true}}, plugins: {legend: {display: false}}}
  });

  var coverageLabels = <?= json_encode(array_map(fn($s) => (string) ($s['state_name'] ?? ''), $coordCoverageStates)) ?>;
  var coverageData = <?= json_encode(array_map(fn($s) => $s['covered'] ? 1 : 0, $coordCoverageStates)) ?>;
  new Chart(document.getElementById('chartStateCoverage'), {
    type: 'bar',
    data: {
      labels: coverageLabels,
      datasets: [{
        label: 'Covered',
        data: coverageData,
        backgroundColor: function(ctx) { return ctx.dataset.data[ctx.dataIndex] === 1 ? '#0f6b3c' : '#dc2626'; }
      }]
    },
    options: {responsive: true, maintainAspectRatio: false, indexAxis: 'y', scales: {x: {beginAtZero: true, max: 1}}, plugins: {legend: {display: false}, tooltip: {callbacks: {label: function(c) { return c.dataset.data[c.dataIndex] === 1 ? 'Covered' : 'Uncovered'; }}}}}
  });
}
</script>
