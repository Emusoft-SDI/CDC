<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/admin-layout.php';
require_once __DIR__ . '/../lib/field-management.php';

session_start();
$pdo = db();
admin_ensure_schema($pdo);
fm_ensure_schema($pdo);
admin_require($pdo);

$message = '';
$error = '';
$currentAdmin = current_user($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Please refresh and try again.';
    } else {
        try {
            $action = (string) ($_POST['action'] ?? '');
            $farmId = (int) ($_POST['farm_id'] ?? 0);
            $farmStmt = $pdo->prepare("SELECT * FROM grower_farms WHERE id = ? LIMIT 1");
            $farmStmt->execute([$farmId]);
            $farm = $farmStmt->fetch();
            if (!$farm) {
                throw new RuntimeException('Farm record not found.');
            }

            if ($action === 'approve_farm') {
                [$score, $notes] = fm_coordinate_score(
                    $farm['latitude'] !== null ? (float) $farm['latitude'] : null,
                    $farm['longitude'] !== null ? (float) $farm['longitude'] : null,
                    $farm['state_id'] !== null ? (int) $farm['state_id'] : null,
                    $farm['lga_id'] !== null ? (int) $farm['lga_id'] : null
                );
                $stmt = $pdo->prepare("
                    INSERT INTO farm_verifications
                        (farm_id, requested_by, status, system_confidence_score, system_notes, admin_decision, reviewed_by, reviewed_at)
                    VALUES (?, ?, 'verified', ?, ?, 'approved', ?, NOW())
                    ON DUPLICATE KEY UPDATE
                        status = 'verified',
                        system_confidence_score = VALUES(system_confidence_score),
                        system_notes = VALUES(system_notes),
                        admin_decision = 'approved',
                        reviewed_by = VALUES(reviewed_by),
                        reviewed_at = NOW(),
                        rejection_reason = NULL
                ");
                $stmt->execute([$farmId, (int) ($farm['user_id'] ?? 0), $score, $notes, (int) ($currentAdmin['id'] ?? 0)]);
                $message = 'Farm approved and marked verified.';
            } elseif ($action === 'reject_farm') {
                $reason = trim((string) ($_POST['reason'] ?? ''));
                $stmt = $pdo->prepare("
                    INSERT INTO farm_verifications
                        (farm_id, requested_by, status, admin_decision, reviewed_by, reviewed_at, rejection_reason)
                    VALUES (?, ?, 'rejected', 'rejected', ?, NOW(), ?)
                    ON DUPLICATE KEY UPDATE
                        status = 'rejected',
                        admin_decision = 'rejected',
                        reviewed_by = VALUES(reviewed_by),
                        reviewed_at = NOW(),
                        rejection_reason = VALUES(rejection_reason)
                ");
                $stmt->execute([$farmId, (int) ($farm['user_id'] ?? 0), (int) ($currentAdmin['id'] ?? 0), $reason]);
                $message = 'Farm rejected with review notes.';
            } elseif ($action === 'assign_task') {
                $agentId = (int) ($_POST['agent_id'] ?? 0);
                if ($agentId <= 0) {
                    throw new RuntimeException('Select a field agent.');
                }
                $taskType = in_array((string) ($_POST['task_type'] ?? 'verification'), ['verification', 'health_check', 'imagery_review'], true)
                    ? (string) $_POST['task_type']
                    : 'verification';
                $priority = in_array((string) ($_POST['priority'] ?? 'normal'), ['low', 'normal', 'high', 'urgent'], true)
                    ? (string) $_POST['priority']
                    : 'normal';
                $pdo->prepare("
                    INSERT INTO field_tasks (farm_id, assigned_to, task_type, priority, status, due_date, notes, created_by)
                    VALUES (?, ?, ?, ?, 'assigned', ?, ?, ?)
                ")->execute([
                    $farmId,
                    $agentId,
                    $taskType,
                    $priority,
                    trim((string) ($_POST['due_date'] ?? '')) ?: null,
                    trim((string) ($_POST['notes'] ?? '')),
                    (int) ($currentAdmin['id'] ?? 0),
                ]);
                $pdo->prepare("
                    INSERT INTO farm_verifications (farm_id, requested_by, status)
                    VALUES (?, ?, 'assigned')
                    ON DUPLICATE KEY UPDATE status = 'assigned'
                ")->execute([$farmId, (int) ($farm['user_id'] ?? 0)]);
                $message = 'Field task assigned.';
            } elseif ($action === 'refresh_weather') {
                fm_weather_estimate(
                    $pdo,
                    $farmId,
                    $farm['latitude'] !== null ? (float) $farm['latitude'] : null,
                    $farm['longitude'] !== null ? (float) $farm['longitude'] : null
                );
                $message = 'Weather snapshot refreshed.';
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$status = (string) ($_GET['status'] ?? '');
$where = [];
$params = [];
if ($status !== '') {
    $where[] = 'COALESCE(fv.status, "pending") = ?';
    $params[] = $status;
}
$scopeState = admin_current_scope_state($pdo);
if ($scopeState !== '') {
    $where[] = 's.state_name = ?';
    $params[] = $scopeState;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("
    SELECT gf.*, u.name grower_name, u.email grower_email, u.phone grower_phone,
           s.state_name, l.lga_name,
           COALESCE(fv.status, 'pending') verification_status,
           fv.system_confidence_score, fv.system_notes, fv.rejection_reason, fv.reviewed_at,
           (SELECT COUNT(*) FROM field_tasks ft WHERE ft.farm_id = gf.id AND ft.status IN ('pending','assigned','in_progress')) open_tasks
    FROM grower_farms gf
    JOIN users u ON u.id = gf.user_id
    LEFT JOIN nigeria_states s ON s.id = gf.state_id
    LEFT JOIN nigeria_lgas l ON l.id = gf.lga_id
    LEFT JOIN farm_verifications fv ON fv.farm_id = gf.id
    {$whereSql}
    ORDER BY gf.updated_at DESC, gf.created_at DESC
    LIMIT 250
");
$stmt->execute($params);
$farms = $stmt->fetchAll();

$agentWhere = $scopeState !== '' ? 'AND sp.state = ?' : '';
$agentStmt = $pdo->prepare("
    SELECT u.id, u.name, u.email, COALESCE(sp.staff_type, 'field_agent') staff_type
    FROM users u
    LEFT JOIN staff_profiles sp ON sp.user_id = u.id
    WHERE (u.role = 'field_agent' OR u.role = 'admin') {$agentWhere}
    ORDER BY u.name
");
$agentStmt->execute($scopeState !== '' ? [$scopeState] : []);
$agents = $agentStmt->fetchAll();

$mapItems = [];
foreach ($farms as $farm) {
    if ($farm['latitude'] !== null && $farm['longitude'] !== null) {
        $mapItems[] = [
            'id' => (int) $farm['id'],
            'name' => (string) $farm['farm_name'],
            'grower' => (string) $farm['grower_name'],
            'lat' => (float) $farm['latitude'],
            'lng' => (float) $farm['longitude'],
            'status' => (string) $farm['verification_status'],
        ];
    }
}
?>
<?php admin_page_start('Fields Management', [
    'active' => 'fields-management.php',
    'description' => 'Approve farm locations, assign field verification tasks, review GPS evidence, and monitor weather snapshots.',
    'wide' => true,
    'css' => '#fieldsMap{height:430px;border:1px solid var(--line);border-radius:8px}.field-row{display:grid;grid-template-columns:minmax(220px,1.2fr) minmax(220px,1fr) minmax(260px,1.2fr);gap:14px;align-items:start}.mini-actions{display:flex;gap:8px;flex-wrap:wrap}.mini-actions form{margin:0}.field-card{border:1px solid var(--line);border-radius:8px;padding:12px;background:#fff}.status-pill{display:inline-flex;border-radius:999px;padding:5px 9px;font-weight:850;font-size:.82rem;background:#eef2f6;color:#475467}.status-pill.verified{background:#eaf8f0;color:#0f6b3c}.status-pill.rejected{background:#fff3f3;color:#a32020}.status-pill.assigned,.status-pill.pending{background:#fff7df;color:#8a5a00}@media(max-width:900px){.field-row{grid-template-columns:1fr}}',
]); ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<?php if ($message): ?><p class="success"><?= e($message) ?></p><?php endif; ?>
<?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>
<?php if ($scopeState !== ''): ?><p class="success">State Coordinator scope: showing farms in <?= e($scopeState) ?>.</p><?php endif; ?>

<section class="panel">
  <div class="toolbar">
    <a class="button secondary" href="fields-management.php">All</a>
    <?php foreach (['pending' => 'Pending', 'assigned' => 'Assigned', 'verified' => 'Verified', 'rejected' => 'Rejected'] as $key => $label): ?>
      <a class="button secondary" href="fields-management.php?status=<?= e($key) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
  </div>
  <div id="fieldsMap"></div>
</section>

<section class="grid">
  <?php foreach ($farms as $farm): ?>
    <?php
      [$score, $notes] = fm_coordinate_score(
          $farm['latitude'] !== null ? (float) $farm['latitude'] : null,
          $farm['longitude'] !== null ? (float) $farm['longitude'] : null,
          $farm['state_id'] !== null ? (int) $farm['state_id'] : null,
          $farm['lga_id'] !== null ? (int) $farm['lga_id'] : null
      );
      $weather = fm_weather_estimate($pdo, (int) $farm['id'], $farm['latitude'] !== null ? (float) $farm['latitude'] : null, $farm['longitude'] !== null ? (float) $farm['longitude'] : null);
      $statusClass = preg_replace('/[^a-z0-9_-]/i', '', (string) $farm['verification_status']);
    ?>
    <article class="card">
      <div class="field-row">
        <div>
          <h2><?= e($farm['farm_name']) ?></h2>
          <p><strong><?= e($farm['grower_name']) ?></strong><br><span class="muted"><?= e($farm['grower_email']) ?><?= $farm['grower_phone'] ? ' / ' . e($farm['grower_phone']) : '' ?></span></p>
          <p><?= e(trim((string) ($farm['street_address'] ?? ''))) ?><br><span class="muted"><?= e((string) ($farm['lga_name'] ?? '')) ?> <?= e((string) ($farm['state_name'] ?? '')) ?></span></p>
          <span class="status-pill <?= e($statusClass) ?>"><?= e(ucwords(str_replace('_', ' ', (string) $farm['verification_status']))) ?></span>
        </div>
        <div class="field-card">
          <h3>System Authentication</h3>
          <p><strong><?= number_format((float) ($farm['system_confidence_score'] ?? $score), 1) ?>%</strong> confidence</p>
          <p class="muted"><?= e((string) ($farm['system_notes'] ?: $notes)) ?></p>
          <p class="muted">GPS: <?= e((string) ($farm['latitude'] ?? 'missing')) ?>, <?= e((string) ($farm['longitude'] ?? 'missing')) ?></p>
        </div>
        <div class="field-card">
          <h3>Weather</h3>
          <p><strong><?= e((string) $weather['temperature_c']) ?>°C</strong> / Humidity <?= e((string) $weather['humidity_percent']) ?>% / Rain <?= e((string) $weather['rainfall_mm']) ?>mm</p>
          <p class="muted"><?= e((string) $weather['summary']) ?></p>
          <form method="post">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="refresh_weather">
            <input type="hidden" name="farm_id" value="<?= (int) $farm['id'] ?>">
            <button type="submit" class="secondary">Refresh Weather</button>
          </form>
        </div>
      </div>
      <div class="toolbar">
        <form method="post">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="approve_farm">
          <input type="hidden" name="farm_id" value="<?= (int) $farm['id'] ?>">
          <button type="submit">Approve</button>
        </form>
        <form method="post" class="mini-actions">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="reject_farm">
          <input type="hidden" name="farm_id" value="<?= (int) $farm['id'] ?>">
          <input name="reason" placeholder="Reason">
          <button type="submit" class="danger">Reject</button>
        </form>
      </div>
      <form method="post" class="toolbar">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="assign_task">
        <input type="hidden" name="farm_id" value="<?= (int) $farm['id'] ?>">
        <select name="agent_id" required>
          <option value="">Assign field agent</option>
          <?php foreach ($agents as $agent): ?>
            <option value="<?= (int) $agent['id'] ?>"><?= e($agent['name']) ?> - <?= e($agent['staff_type']) ?></option>
          <?php endforeach; ?>
        </select>
        <select name="task_type"><option value="verification">Verification</option><option value="health_check">Health Check</option><option value="imagery_review">Imagery Review</option></select>
        <select name="priority"><option value="normal">Normal</option><option value="high">High</option><option value="urgent">Urgent</option><option value="low">Low</option></select>
        <input type="date" name="due_date">
        <input name="notes" placeholder="Task note">
        <button type="submit">Assign Task</button>
      </form>
      <p class="muted">Open tasks: <?= (int) $farm['open_tasks'] ?></p>
    </article>
  <?php endforeach; ?>
  <?php if (!$farms): ?><p class="empty">No farms match this field management view.</p><?php endif; ?>
</section>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
  const farmItems = <?= json_encode($mapItems, JSON_UNESCAPED_SLASHES) ?>;
  const map = L.map('fieldsMap').setView([9.0820, 8.6753], 6);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(map);
  farmItems.forEach((farm) => {
    const color = farm.status === 'verified' ? '#14733a' : farm.status === 'rejected' ? '#a32020' : '#9b6500';
    L.circleMarker([farm.lat, farm.lng], { radius: 9, color, fillColor: color, fillOpacity: .82 })
      .addTo(map)
      .bindPopup(`<strong>${farm.name}</strong><br>${farm.grower}<br>${farm.status}`);
  });
</script>
<?php admin_page_end(); ?>
