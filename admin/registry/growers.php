<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/common.php';

$pageTitle = 'Growers Directory - NATCODEV Registry';
$activeNav = 'growers';

$page = max(1, (int) ($_GET['p'] ?? 1));
$limit = rx_per_page();
$offset = ($page - 1) * $limit;
$search = trim((string) ($_GET['search'] ?? ''));

$where = "role = 'grower'";
$params = [];
if ($search !== '') {
    $where .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR u.location LIKE ?)";
    $term = "%$search%";
    array_push($params, $term, $term, $term, $term);
}

$total = rx_scalar($pdo, "SELECT COUNT(*) FROM users u WHERE $where", $params);
$growers = rx_rows($pdo, "
    SELECT u.id, u.name, u.email, u.phone, u.location, u.created_at,
           a.id application_id, COALESCE(a.confirmed, 0) confirmed,
           COALESCE(ns.state_name, u.location) state_name,
           IF(a.confirmed = 1, 'verified', 'pending') reg_status
    FROM users u
    LEFT JOIN applications a ON a.id = u.application_id
    LEFT JOIN nigeria_states ns ON ns.id = a.state_id
    WHERE $where
    ORDER BY u.created_at DESC
    LIMIT $limit OFFSET $offset
", $params);

foreach ($growers as &$growerRow) {
    $growerRow['readiness'] = grower_certificate_readiness((int) $growerRow['id'], $pdo);
}
unset($growerRow);

$pendingApplications = rx_rows($pdo, "
    SELECT a.id application_id, a.app_ref, a.name, a.email, a.phone, a.location, a.created_at
    FROM applications a
    LEFT JOIN users u ON u.application_id = a.id
    WHERE a.confirmed = 0 AND u.id IS NULL
    ORDER BY a.created_at DESC
    LIMIT 25
");

$pendingImports = app_table_exists($pdo, 'user_import_records') ? rx_rows($pdo, "
    SELECT id, batch_ref, name, email, phone, status, status_note, application_id, created_at
    FROM user_import_records
    WHERE role = 'grower'
      AND status NOT IN ('engagement_confirmed', 'confirmed', 'completed', 'skipped', 'archived_no_response')
    ORDER BY created_at DESC
    LIMIT 25
") : [];

$allStates = rx_rows($pdo, "SELECT id, state_name FROM nigeria_states ORDER BY state_name");

require __DIR__ . '/layout/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Grower Directory</h1>
    <p class="page-subtitle"><?= number_format($total) ?> registered coconut growers found.</p>
  </div>
  <div class="header-actions">
    <button class="btn btn-primary" onclick="openModal('growerModal')">+ Register Grower</button>
  </div>
</div>

<?php if ($pendingApplications || $pendingImports): ?>
<div class="card" style="margin-bottom:20px;border-left:4px solid #d97706">
  <div class="card-header">
    <div>
      <h3 class="card-title">Activation & Engagement Queue</h3>
      <small>Uploaded and admin-created growers remain outside certificate eligibility until they engage and complete onboarding.</small>
    </div>
  </div>
  <div class="card-body p0 table-responsive">
    <table>
      <thead><tr><th>Record</th><th>Contact</th><th>Current State</th><th>Next Action</th></tr></thead>
      <tbody>
        <?php foreach ($pendingApplications as $pending): ?>
          <tr>
            <td><strong><?= rx_e($pending['name']) ?></strong><br><small><?= rx_e($pending['app_ref']) ?></small></td>
            <td><?= rx_e($pending['email'] ?: $pending['phone']) ?></td>
            <td><span class="status-badge status-pending-review">Awaiting activation</span><br><small>No dashboard account or certificate access yet.</small></td>
            <td>
              <form action="inc/actions.php" method="post">
                <input type="hidden" name="_csrf" value="<?= rx_e(csrf_token()) ?>">
                <input type="hidden" name="action" value="send_activation">
                <input type="hidden" name="application_id" value="<?= (int) $pending['application_id'] ?>">
                <input type="hidden" name="page" value="../growers.php">
                <button class="btn btn-sm btn-primary" type="submit">Send Activation</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php foreach ($pendingImports as $pending): ?>
          <tr>
            <td><strong><?= rx_e($pending['name'] ?: 'Imported grower') ?></strong><br><small><?= rx_e($pending['batch_ref']) ?></small></td>
            <td><?= rx_e($pending['email'] ?: $pending['phone']) ?></td>
            <td><span class="status-badge status-pending-review"><?= rx_e(ucwords(str_replace('_', ' ', $pending['status']))) ?></span><br><small><?= rx_e($pending['status_note']) ?></small></td>
            <td>
              <form action="inc/actions.php" method="post">
                <input type="hidden" name="_csrf" value="<?= rx_e(csrf_token()) ?>">
                <input type="hidden" name="action" value="send_import_activation">
                <input type="hidden" name="import_record_id" value="<?= (int) $pending['id'] ?>">
                <input type="hidden" name="page" value="../growers.php">
                <button class="btn btn-sm btn-primary" type="submit">Resend Engagement</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-header">
    <form method="get" style="display:flex; gap:10px; flex:1;">
      <input type="text" name="search" class="form-input" placeholder="Search by name, email, phone or state..." value="<?= rx_e($search) ?>" style="max-width:400px">
      <button type="submit" class="btn btn-secondary">Search</button>
      <?php if($search): ?><a href="growers.php" class="btn btn-secondary">Clear</a><?php endif; ?>
    </form>
  </div>
  <div class="card-body p0">
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Grower</th>
          <th>Location</th>
          <th>Registered</th>
          <th>Certificate Readiness</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($growers as $row): ?>
          <tr>
            <td><strong>#<?= $row['id'] ?></strong></td>
            <td>
              <div class="avatar-row">
                <div class="avatar-sm"><?= rx_user_initials($row['name']) ?></div>
                <div>
                  <strong><?= rx_e($row['name']) ?></strong><br>
                  <small><?= rx_e($row['email'] ?: $row['phone']) ?></small>
                </div>
              </div>
            </td>
            <td><?= rx_e($row['state_name'] ?: 'Unassigned') ?></td>
            <td><?= date('M j, Y', strtotime($row['created_at'])) ?></td>
            <td>
              <?php
                $readiness = $row['readiness'];
                $failedChecks = array_values(array_filter($readiness['checks'], static fn(array $check): bool => !$check['passed']));
              ?>
              <?php if ($readiness['issued']): ?>
                <span class="status-badge status-verified">Certificate issued</span>
              <?php elseif ($readiness['ready']): ?>
                <span class="status-badge status-verified">Ready to issue</span>
              <?php else: ?>
                <span class="status-badge status-pending-review"><?= count($failedChecks) ?> step(s) pending</span>
              <?php endif; ?>
              <details style="margin-top:7px;max-width:330px">
                <summary style="cursor:pointer;font-size:12px;font-weight:700">View authenticity checks</summary>
                <div style="display:grid;gap:5px;margin-top:7px">
                  <?php foreach ($readiness['checks'] as $check): ?>
                    <small style="color:<?= $check['passed'] ? '#166534' : '#92400e' ?>"><strong><?= $check['passed'] ? 'PASS' : 'PENDING' ?>:</strong> <?= rx_e($check['label']) ?> - <?= rx_e($check['detail']) ?></small>
                  <?php endforeach; ?>
                </div>
              </details>
            </td>
            <td>
              <div style="display:flex;gap:5px;flex-wrap:wrap;max-width:310px">
                <?php if (!$readiness['issued'] && $readiness['ready']): ?>
                  <form action="inc/actions.php" method="post">
                    <input type="hidden" name="_csrf" value="<?= rx_e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="issue_certificate">
                    <input type="hidden" name="user_id" value="<?= (int) $row['id'] ?>">
                    <input type="hidden" name="page" value="../growers.php">
                    <button type="submit" class="btn btn-sm btn-primary">Issue Certificate</button>
                  </form>
                <?php endif; ?>
                <?php if (!(int) $row['confirmed']): ?>
                  <form action="inc/actions.php" method="post">
                    <input type="hidden" name="_csrf" value="<?= rx_e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="send_activation">
                    <input type="hidden" name="application_id" value="<?= (int) $row['application_id'] ?>">
                    <input type="hidden" name="page" value="../growers.php">
                    <button type="submit" class="btn btn-sm btn-secondary">Resend Activation</button>
                  </form>
                <?php endif; ?>
                <?php if (!$readiness['ready']): ?>
                  <form action="inc/actions.php" method="post">
                    <input type="hidden" name="_csrf" value="<?= rx_e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="send_onboarding_reminder">
                    <input type="hidden" name="user_id" value="<?= (int) $row['id'] ?>">
                    <input type="hidden" name="page" value="../growers.php">
                    <button type="submit" class="btn btn-sm btn-secondary">Send Onboarding Reminder</button>
                  </form>
                <?php endif; ?>
                <form action="inc/actions.php" method="post">
                  <input type="hidden" name="_csrf" value="<?= rx_e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="send_password_reset">
                  <input type="hidden" name="user_id" value="<?= (int) $row['id'] ?>">
                  <input type="hidden" name="page" value="../growers.php">
                  <button type="submit" class="btn btn-sm btn-secondary">Send Password Reset</button>
                </form>
                <a href="../users.php?search=<?= urlencode((string) ($row['email'] ?? '')) ?>" class="btn btn-sm btn-secondary">View Profile</a>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$growers): ?><tr><td colspan="6" style="text-align:center; padding:40px">No growers found matching your search.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?= rx_pagination_links($total, $limit, $page, 'growers.php') ?>

<!-- REGISTER MODAL -->
<div class="modal-overlay" id="growerModal">
  <div class="modal">
    <div class="modal-header">
      <h3 class="card-title" style="margin:20px">Register New Grower</h3>
      <button class="btn-icon" onclick="closeModal('growerModal')" style="margin:15px">✕</button>
    </div>
    <form action="inc/actions.php" method="post">
      <input type="hidden" name="_csrf" value="<?= rx_e(csrf_token()) ?>">
      <input type="hidden" name="action" value="create_grower">
      <input type="hidden" name="page" value="../growers.php">
      <div class="card-body">
        <div class="form-group">
          <label class="form-label">Full Name / Business Name</label>
          <input type="text" name="name" class="form-input" required>
        </div>
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Email Address</label>
            <input type="email" name="email" class="form-input" required>
          </div>
          <div class="form-group">
            <label class="form-label">Phone Number</label>
            <input type="text" name="phone" class="form-input">
          </div>
        </div>
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Type</label>
            <select name="type" class="form-select">
              <option>Individual</option>
              <option>Group</option>
              <option>Cooperative</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">State</label>
            <select name="state" class="form-select">
              <option value="">Select State</option>
              <?php foreach($allStates as $s): ?><option value="<?= rx_e($s['state_name']) ?>"><?= rx_e($s['state_name']) ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>
      <div class="card-header" style="justify-content:flex-end">
        <button type="button" class="btn btn-secondary" onclick="closeModal('growerModal')">Cancel</button>
        <button type="submit" class="btn btn-primary" style="margin-left:10px">Register Grower</button>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/layout/footer.php'; ?>
