<?php
$docStatusBadges = [
  'pending' => 'text-bg-warning',
  'submitted' => 'text-bg-warning',
  'under_review' => 'text-bg-warning',
  'needs_review' => 'text-bg-warning',
  'verified' => 'text-bg-success',
];
?>

<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-3">
    <div class="card h-100 rp-kpi" style="border-top-color:#f79009"><div class="card-body">
      <div class="text-secondary small">Documents Pending Review</div>
      <div class="display-6 fw-bold text-dark"><?= number_format($pendingDocs) ?></div>
      <div class="small text-muted">pending or needs review</div>
    </div></div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card h-100 rp-kpi" style="border-top-color:#0f6b3c"><div class="card-body">
      <div class="text-secondary small">Document Verification Rate</div>
      <div class="display-6 fw-bold text-dark"><?= $docVerifyRate ?>%</div>
      <div class="small text-muted"><?= number_format($docVerified) . ' of ' . number_format($docTotal) . ' documents verified' ?></div>
    </div></div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card h-100 rp-kpi" style="border-top-color:#2374c6"><div class="card-body">
      <div class="text-secondary small">Accredited Growers</div>
      <div class="display-6 fw-bold text-dark"><?= number_format($accredited) ?></div>
      <div class="small text-muted"><?= $accreditationRate ?>% accreditation rate</div>
    </div></div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card h-100 rp-kpi" style="border-top-color:#8b5cf6"><div class="card-body">
      <div class="text-secondary small">Verified Farms</div>
      <div class="display-6 fw-bold text-dark"><?= number_format($verifiedFarms) ?></div>
      <div class="small text-muted"><?= $verificationRate ?>% farm verification rate</div>
    </div></div>
  </div>
</div>

<div class="row g-4 mb-4">
  <div class="col-xl-6">
    <div class="card h-100">
      <div class="card-header bg-white fw-bold"><i class="fas fa-file-contract text-warning me-2"></i>Document Verification Status</div>
      <div class="card-body"><div style="height:280px"><canvas id="chartDocVerification"></canvas></div></div>
    </div>
  </div>
  <div class="col-xl-6">
    <div class="card h-100">
      <div class="card-header bg-white fw-bold"><i class="fas fa-award text-success me-2"></i>Grower Accreditation Status</div>
      <div class="card-body"><div style="height:280px"><canvas id="chartAccreditation"></canvas></div></div>
    </div>
  </div>
</div>

<div class="card mb-4">
  <div class="card-header bg-white fw-bold"><i class="fas fa-clipboard-list text-primary me-2"></i>Pending Document Review Queue <span class="badge text-bg-secondary ms-2"><?= count($pendingDocQueue) ?> documents</span></div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="table-light"><tr><th>Grower</th><th>Document Type</th><th>Status</th><th>Uploaded</th></tr></thead>
        <tbody>
        <?php foreach ($pendingDocQueue as $r): $vs = (string) $r['verification_status']; ?>
          <tr>
            <td class="fw-semibold"><?= e((string) $r['name']) ?><br><small class="text-muted"><?= e((string) $r['email']) ?></small></td>
            <td><?= e(ucwords(str_replace('_', ' ', (string) $r['document_type']))) ?></td>
            <td><span class="badge <?= $docStatusBadges[$vs] ?? 'text-bg-secondary' ?>"><?= e(ucwords(str_replace('_', ' ', $vs))) ?></span></td>
            <td><?= empty($r['uploaded_at']) ? '—' : e(date('M j, Y H:i', strtotime((string) $r['uploaded_at']))) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$pendingDocQueue): ?><tr><td colspan="4" class="text-center text-secondary py-4">No documents are waiting for review.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php if ($pendingDocs > 0): ?>
<div class="alert alert-info d-flex align-items-start">
  <i class="fas fa-info-circle mt-1 me-2"></i>
  <div class="small">Dispatch verification work to field agents or state coordinators to clear the document backlog faster.</div>
</div>
<?php endif; ?>

<script>
if (<?= count($docData) ?> > 0) {
  new Chart(document.getElementById('chartDocVerification'), {
    type: 'doughnut',
    data: {
      labels: <?= json_encode($docLabels) ?>,
      datasets: [{
        data: <?= json_encode($docData) ?>,
        backgroundColor: window.rpColors.slice(0, <?= count($docData) ?>)
      }]
    },
    options: {responsive: true, maintainAspectRatio: false, cutout: '55%', plugins: {legend: {position: 'bottom'}}}
  });
}

if (<?= count($accData) ?> > 0) {
  new Chart(document.getElementById('chartAccreditation'), {
    type: 'doughnut',
    data: {
      labels: <?= json_encode($accLabels) ?>,
      datasets: [{
        data: <?= json_encode($accData) ?>,
        backgroundColor: window.rpColors.slice(0, <?= count($accData) ?>)
      }]
    },
    options: {responsive: true, maintainAspectRatio: false, cutout: '55%', plugins: {legend: {position: 'bottom'}}}
  });
}
</script>
