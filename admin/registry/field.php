<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/common.php';

$pageTitle = 'Field Network - NATCODEV Registry';
$activeNav = 'field';

$agents = rx_rows($pdo, "
    SELECT u.id, u.name, u.email, u.phone, u.location,
           (SELECT COUNT(*) FROM visits v WHERE v.agent_id = u.id) visit_count,
           (SELECT COUNT(*) FROM grower_assignments ga WHERE ga.agent_id = u.id) grower_count
    FROM users u
    WHERE u.role = 'field_agent'
    ORDER BY visit_count DESC
");

$agentLocations = rx_rows($pdo, "
    SELECT u.name, u.location, l.lat, l.lng, l.updated_at
    FROM users u
    JOIN agent_locations l ON l.user_id = u.id
    WHERE u.role = 'field_agent' AND l.updated_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
");

require __DIR__ . '/layout/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Field Network</h1>
    <p class="page-subtitle">Coordination and activity tracking for verified Field Agents.</p>
  </div>
  <div class="header-actions">
    <button class="btn btn-primary" onclick="openModal('agentModal')">+ Deploy Agent</button>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h3 class="card-title">Live Field Activity</h3>
  </div>
  <div class="card-body" style="padding:0">
    <div id="fieldMap" style="height:400px; width:100%; z-index:10"></div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h3 class="card-title">Field Agent Directory</h3>
  </div>
  <div class="card-body p0">
    <table>
      <thead>
        <tr>
          <th>Agent</th>
          <th>Base Location</th>
          <th>Visits</th>
          <th>Growers</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($agents as $row): ?>
          <tr>
            <td>
              <strong><?= rx_e($row['name']) ?></strong><br>
              <small><?= rx_e($row['email']) ?></small>
            </td>
            <td><?= rx_e($row['location'] ?: 'Not Set') ?></td>
            <td><strong><?= number_format($row['visit_count']) ?></strong></td>
            <td><?= number_format($row['grower_count']) ?></td>
            <td>
              <a href="agent-view.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-secondary">Logs</a>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$agents): ?><tr><td colspan="5" style="text-align:center; padding:40px">No field agents deployed yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- DEPLOY MODAL -->
<div class="modal-overlay" id="agentModal">
  <div class="modal">
    <div class="modal-header">
      <h3 class="card-title" style="margin:20px">Deploy New Field Agent</h3>
      <button class="btn-icon" onclick="closeModal('agentModal')" style="margin:15px">✕</button>
    </div>
    <form action="inc/actions.php" method="post">
      <input type="hidden" name="action" value="deploy_agent">
      <input type="hidden" name="page" value="../field.php">
      <div class="card-body">
        <div class="form-group">
          <label class="form-label">Full Name</label>
          <input type="text" name="name" class="form-input" required>
        </div>
        <div class="form-group">
          <label class="form-label">Email / Username</label>
          <input type="email" name="email" class="form-input" required>
        </div>
        <div class="form-group">
          <label class="form-label">Primary Operating Location (State/LGA)</label>
          <input type="text" name="location" class="form-input">
        </div>
      </div>
      <div class="card-header" style="justify-content:flex-end">
        <button type="button" class="btn btn-secondary" onclick="closeModal('agentModal')">Cancel</button>
        <button type="submit" class="btn btn-primary" style="margin-left:10px">Deploy Agent</button>
      </div>
    </form>
  </div>
</div>

<script>
const map = L.map('fieldMap').setView([9.0820, 8.6753], 6);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

const locations = <?= json_encode($agentLocations) ?>;
locations.forEach(loc => {
    if(loc.lat && loc.lng) {
        L.marker([loc.lat, loc.lng])
         .addTo(map)
         .bindPopup(`<b>${loc.name}</b><br>${loc.location}<br><small>Updated: ${loc.updated_at}</small>`);
    }
});
</script>

<?php require __DIR__ . '/layout/footer.php'; ?>
