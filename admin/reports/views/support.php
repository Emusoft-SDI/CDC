<?php
$highPriority = $tpData[array_search('High', $tpLabels, true)] ?? 0;
?>

<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-3">
    <div class="card h-100 rp-kpi" style="border-top-color:#f79009"><div class="card-body">
      <div class="text-secondary small">Open Tickets</div>
      <div class="display-6 fw-bold text-dark"><?= number_format($openSupport) ?></div>
      <div class="small text-muted">open or in progress</div>
    </div></div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card h-100 rp-kpi" style="border-top-color:#d92d20"><div class="card-body">
      <div class="text-secondary small">High Priority Queue</div>
      <div class="display-6 fw-bold text-dark"><?= number_format($highPriority) ?></div>
      <div class="small text-muted">needs immediate attention</div>
    </div></div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card h-100 rp-kpi" style="border-top-color:#2374c6"><div class="card-body">
      <div class="text-secondary small">Support Categories</div>
      <div class="display-6 fw-bold text-dark"><?= number_format(count($scLabels)) ?></div>
      <div class="small text-muted">message categories tracked</div>
    </div></div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card h-100 rp-kpi" style="border-top-color:#0f6b3c"><div class="card-body">
      <div class="text-secondary small">Total Conversations</div>
      <div class="display-6 fw-bold text-dark"><?= number_format(array_sum($scData)) ?></div>
      <div class="small text-muted">across all statuses</div>
    </div></div>
  </div>
</div>

<div class="row g-4 mb-4">
  <div class="col-xl-6">
    <div class="card h-100">
      <div class="card-header bg-white fw-bold"><i class="fas fa-chart-pie text-danger me-2"></i>Ticket Priority Distribution</div>
      <div class="card-body"><div style="height:280px"><canvas id="chartTicketPriority"></canvas></div></div>
    </div>
  </div>
  <div class="col-xl-6">
    <div class="card h-100">
      <div class="card-header bg-white fw-bold"><i class="fas fa-comments text-warning me-2"></i>Message Status Overview</div>
      <div class="card-body"><div style="height:280px"><canvas id="chartMessageStatus"></canvas></div></div>
    </div>
  </div>
</div>

<div class="row g-4 mb-4">
  <div class="col-xl-12">
    <div class="card h-100">
      <div class="card-header bg-white fw-bold"><i class="fas fa-chart-bar text-info me-2"></i>Conversation Volume by Category</div>
      <div class="card-body"><div style="height:280px"><canvas id="chartCategoryVolume"></canvas></div></div>
    </div>
  </div>
</div>

<div class="card mb-4">
  <div class="card-header bg-white fw-bold"><i class="fas fa-table-list text-primary me-2"></i>Support Matrix <span class="badge text-bg-secondary ms-2"><?= count($supportRows) ?> segments</span></div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="table-light"><tr><th>Category</th><th>Priority</th><th>Status</th><th class="text-end">Total</th></tr></thead>
        <tbody>
        <?php foreach ($supportRows as $r): ?>
          <tr>
            <td class="fw-semibold"><?= e(ucwords(str_replace('_', ' ', (string) $r['category']))) ?></td>
            <td><?= e(ucfirst((string) $r['priority'])) ?></td>
            <td><?= e(ucwords(str_replace('_', ' ', (string) $r['status']))) ?></td>
            <td class="text-end"><?= number_format((int) $r['total']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$supportRows): ?><tr><td colspan="4" class="text-center text-secondary py-4">No support messages yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
if (<?= count($tpData) ?> > 0) {
  new Chart(document.getElementById('chartTicketPriority'), {
    type: 'doughnut',
    data: {
      labels: <?= json_encode($tpLabels) ?>,
      datasets: [{
        data: <?= json_encode($tpData) ?>,
        backgroundColor: ['#d92d20','#f79009','#2374c6','#0f6b3c'].slice(0, <?= count($tpData) ?>)
      }]
    },
    options: {responsive: true, maintainAspectRatio: false, cutout: '55%', plugins: {legend: {position: 'bottom'}}}
  });
}

new Chart(document.getElementById('chartMessageStatus'), {
  type: 'bar',
  data: {
    labels: ['Open','In Progress','Resolved'],
    datasets: [{
      label: 'Conversations',
      data: <?= json_encode($messageStatusCounts) ?>,
      backgroundColor: ['rgba(247,144,9,.72)','rgba(35,116,198,.72)','rgba(15,107,60,.72)'],
      borderRadius: 6,
      barPercentage: .5
    }]
  },
  options: {responsive: true, maintainAspectRatio: false, plugins: {legend: {display: false}}, scales: {y: {beginAtZero: true, ticks: {stepSize: 1}}}}
});

if (<?= count($scData) ?> > 0) {
  new Chart(document.getElementById('chartCategoryVolume'), {
    type: 'bar',
    data: {
      labels: <?= json_encode($scLabels) ?>,
      datasets: [{
        label: 'Messages',
        data: <?= json_encode($scData) ?>,
        backgroundColor: window.rpColors.slice(0, <?= count($scData) ?>),
        borderRadius: 4,
        barPercentage: .6
      }]
    },
    options: {responsive: true, maintainAspectRatio: false, plugins: {legend: {display: false}}, scales: {y: {beginAtZero: true, ticks: {stepSize: 1}}}}
  });
}
</script>
