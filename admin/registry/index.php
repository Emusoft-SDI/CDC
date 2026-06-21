<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/common.php';

$pageTitle = 'Registry Overview - NATCODEV';
$activeNav = 'overview';

// Metrics
$totalGrowers = rx_scalar($pdo, "SELECT COUNT(*) FROM users WHERE role = 'grower'");
$verifiedGrowers = rx_scalar($pdo, "SELECT COUNT(DISTINCT u.id) FROM users u JOIN applications a ON a.id = u.application_id WHERE u.role = 'grower' AND a.confirmed = 1");
$pendingApps = rx_scalar($pdo, "SELECT COUNT(*) FROM applications WHERE confirmed = 0");
$certTotal = rx_scalar($pdo, "SELECT COUNT(*) FROM certificates");
$docPending = rx_scalar($pdo, "SELECT COUNT(*) FROM document_requirements WHERE verification_status = 'pending'");

$recentApps = rx_rows($pdo, "
    SELECT a.id, a.app_ref, a.name, a.created_at, a.confirmed, COALESCE(ns.state_name, '') state_name
    FROM applications a
    LEFT JOIN nigeria_states ns ON ns.id = a.state_id
    ORDER BY a.created_at DESC
    LIMIT 5
");

require __DIR__ . '/layout/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Registry Overview</h1>
    <p class="page-subtitle">National Coconut Development & Propagation Initiative Workspace</p>
  </div>
  <div class="header-actions">
    <a href="export.php" class="btn btn-secondary">Export CSV</a>
    <a href="growers.php" class="btn btn-primary">Manage Growers</a>
  </div>
</div>

<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-card-label">Total Registered Growers</div>
    <div class="stat-card-value"><?= number_format($totalGrowers) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-card-label">Verified Farmers</div>
    <div class="stat-card-value"><?= number_format($verifiedGrowers) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-card-label">Pending Applications</div>
    <div class="stat-card-value" style="color:var(--warning)"><?= number_format($pendingApps) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-card-label">Pending Documents</div>
    <div class="stat-card-value"><?= number_format($docPending) ?></div>
  </div>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">Recent Applications</h3>
      <a href="applications.php" class="btn btn-secondary btn-sm">View All</a>
    </div>
    <div class="card-body p0">
      <table>
        <thead>
          <tr><th>App Ref</th><th>Name</th><th>State</th><th>Status</th></tr>
        </thead>
        <tbody>
          <?php foreach ($recentApps as $app): ?>
            <tr>
              <td><strong><?= rx_e($app['app_ref']) ?></strong></td>
              <td><?= rx_e($app['name']) ?></td>
              <td><?= rx_e($app['state_name']) ?></td>
              <td><span class="status-badge <?= $app['confirmed'] ? 'status-approved' : 'status-pending-review' ?>"><?= $app['confirmed'] ? 'Approved' : 'Pending' ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h3 class="card-title">Quick Actions</h3>
    </div>
    <div class="card-body">
      <div style="display:grid; gap:10px;">
        <a href="applications.php" class="btn btn-secondary" style="justify-content:flex-start">📋 Review Pending Applications (<?= $pendingApps ?>)</a>
        <a href="documents.php" class="btn btn-secondary" style="justify-content:flex-start">📄 Verify Pending Documents (<?= $docPending ?>)</a>
        <a href="certificates.php" class="btn btn-secondary" style="justify-content:flex-start">🏆 Issue New Certificates</a>
        <a href="import.php" class="btn btn-secondary" style="justify-content:flex-start">📤 Bulk Import Growers</a>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/layout/footer.php'; ?>
