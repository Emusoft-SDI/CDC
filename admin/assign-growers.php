<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../lib/admin-layout.php';
require_once __DIR__ . '/../lib/field-management.php';

$pdo = db();
admin_ensure_schema($pdo);
fm_ensure_schema($pdo);
admin_require($pdo);

$agentId = filter_input(INPUT_GET, 'agent', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'agent_id', FILTER_VALIDATE_INT);
$message = '';
$error = '';
$scopeState = admin_current_scope_state($pdo);

$agent = null;
if ($agentId) {
    $stmt = $pdo->prepare("
        SELECT u.id, u.name, u.email, sp.staff_type
        FROM users u
        LEFT JOIN staff_profiles sp ON sp.user_id = u.id AND sp.deleted_at IS NULL
        WHERE u.id = ? AND u.role = 'field_agent'
    ");
    $stmt->execute([$agentId]);
    $agent = $stmt->fetch() ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid security token.';
    } elseif (!$agent) {
        $error = 'Select a valid field agent.';
    } else {
        $growerIds = array_values(array_filter(array_map('intval', $_POST['grower_ids'] ?? [])));
        if (!app_table_exists($pdo, 'agent_assignments')) {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS agent_assignments (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    agent_id INT NOT NULL,
                    grower_id INT NOT NULL,
                    assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uniq_agent_grower (agent_id, grower_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
        $stmt = $pdo->prepare("INSERT IGNORE INTO agent_assignments (agent_id, grower_id) VALUES (?, ?)");
        foreach ($growerIds as $growerId) {
            $stmt->execute([(int) $agent['id'], $growerId]);
        }
        $message = count($growerIds) . ' grower assignment(s) saved.';
    }
}

$agentWhere = $scopeState !== '' ? 'AND sp.state = ?' : '';
$agentStmt = $pdo->prepare("
    SELECT u.id, u.name, u.email, COALESCE(sp.staff_type, 'field_agent') staff_type
    FROM users u
    LEFT JOIN staff_profiles sp ON sp.user_id = u.id AND sp.deleted_at IS NULL
    WHERE u.role = 'field_agent' {$agentWhere}
    ORDER BY sp.staff_type, u.name
");
$agentStmt->execute($scopeState !== '' ? [$scopeState] : []);
$agents = $agentStmt->fetchAll();
$page = admin_current_page();
$perPage = admin_per_page(50);
$offset = admin_pagination_offset($page, $perPage);
$growerScopeSql = $scopeState !== '' ? "AND (ns.state_name = ? OR a.location LIKE ? OR u.location LIKE ?)" : '';
$growerParams = $scopeState !== '' ? [$scopeState, '%' . $scopeState . '%', '%' . $scopeState . '%'] : [];
$totalStmt = $pdo->prepare("
    SELECT COUNT(DISTINCT u.id)
    FROM users u
    LEFT JOIN applications a ON u.application_id = a.id
    LEFT JOIN grower_farms gf ON gf.user_id = u.id AND gf.deleted_at IS NULL
    LEFT JOIN nigeria_states ns ON ns.id = gf.state_id OR ns.id = a.state_id
    WHERE u.role = 'grower' {$growerScopeSql}
");
$totalStmt->execute($growerParams);
$totalGrowers = (int) $totalStmt->fetchColumn();
$growerStmt = $pdo->prepare("
    SELECT u.id, u.name, u.email, a.app_ref
    FROM users u
    LEFT JOIN applications a ON u.application_id = a.id
    LEFT JOIN grower_farms gf ON gf.user_id = u.id AND gf.deleted_at IS NULL
    LEFT JOIN nigeria_states ns ON ns.id = gf.state_id OR ns.id = a.state_id
    WHERE u.role = 'grower' {$growerScopeSql}
    GROUP BY u.id
    ORDER BY u.name
    LIMIT {$perPage} OFFSET {$offset}
");
$growerStmt->execute($growerParams);
$growers = $growerStmt->fetchAll();

$totalAssignments = app_table_exists($pdo, 'agent_assignments') ? (int) $pdo->query('SELECT COUNT(*) FROM agent_assignments')->fetchColumn() : 0;
$assignedGrowers = app_table_exists($pdo, 'agent_assignments') ? (int) $pdo->query('SELECT COUNT(DISTINCT grower_id) FROM agent_assignments')->fetchColumn() : 0;
$agentAssignmentCount = 0;
if ($agent) {
    $agentCountStmt = $pdo->prepare('SELECT COUNT(*) FROM agent_assignments WHERE agent_id = ?');
    $agentCountStmt->execute([(int) $agent['id']]);
    $agentAssignmentCount = (int) $agentCountStmt->fetchColumn();
}

admin_page_start('Assignments', [
    'active' => 'assign-growers.php',
    'description' => 'Assign growers to field agents for visits and follow-up workflows.',
    'wide' => true,
]);
?>
<?php if ($message): ?><div class="notice ok"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>
<?php if ($scopeState !== ''): ?><div class="notice ok">State Coordinator scope: assignments are limited to <?= e($scopeState) ?>.</div><?php endif; ?>

<?= admin_kpi_grid([
    ['Field Agents', number_format(count($agents)), 'Available for assignment', 'fa-user-shield', ''],
    ['Growers', number_format($totalGrowers), 'In current scope', 'fa-seedling', 'blue'],
    ['Assignments', number_format($totalAssignments), 'Agent-grower links', 'fa-link', 'purple'],
    ['Unassigned Growers', number_format(max(0, $totalGrowers - $assignedGrowers)), 'Awaiting an agent', 'fa-user-clock', 'orange'],
]) ?>

<details class="collapse-card"<?= $error !== '' ? ' open' : '' ?>>
  <summary>
    <span class="cc-icon"><i class="fas fa-user-check"></i></span>
    <span class="collapse-title">Select Field Agent<small>Choose an agent to view and manage grower assignments</small></span>
    <span class="caret"><i class="fas fa-chevron-down"></i></span>
  </summary>
  <div class="collapse-body">
    <form method="get">
      <div class="field-grid">
        <label class="field"><span>Field Agent</span>
          <select name="agent" onchange="this.form.submit()">
            <option value="">Choose agent</option>
            <?php foreach ($agents as $row): ?>
              <option value="<?= (int) $row['id'] ?>" <?= $agent && (int) $agent['id'] === (int) $row['id'] ? 'selected' : '' ?>><?= e($row['name']) ?> - <?= e(ucfirst(str_replace('_', ' ', (string) $row['staff_type']))) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>
      <div class="actions"><button type="submit"><i class="fas fa-user-check"></i> Load Agent</button></div>
    </form>
  </div>
</details>

<section class="panel">
  <div class="toolbar" style="justify-content:space-between">
    <h2 style="margin:0"><?= $agent ? 'Assign to ' . e($agent['name']) : 'Growers' ?></h2>
    <span class="meta"><?= $agent ? number_format($agentAssignmentCount) . ' already assigned' : 'Select an agent to enable assignment' ?></span>
  </div>
  <?= admin_pagination_controls($totalGrowers, $page, $perPage, ['agent' => $agent ? (int) $agent['id'] : '']) ?>
  <form method="post">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="agent_id" value="<?= $agent ? (int) $agent['id'] : 0 ?>">
    <div class="record-list">
      <?php foreach ($growers as $grower): ?>
        <label class="record-row compact" style="cursor:<?= $agent ? 'pointer' : 'not-allowed' ?>">
          <span class="record-avatar<?= $agent ? '' : ' gray' ?>"><input class="record-check" type="checkbox" name="grower_ids[]" value="<?= (int) $grower['id'] ?>" <?= !$agent ? 'disabled' : '' ?>></span>
          <div class="record-main">
            <div class="record-title"><?= e($grower['name']) ?></div>
            <div class="record-contact"><span><i class="far fa-envelope"></i><?= e($grower['email']) ?></span></div>
          </div>
          <div class="record-actions">
            <?php if (!empty($grower['app_ref'])): ?><span class="ref-pill"><i class="fas fa-hashtag"></i><?= e($grower['app_ref']) ?></span><?php endif; ?>
          </div>
        </label>
      <?php endforeach; ?>
      <?php if (!$growers): ?><div class="record-empty">No growers found.</div><?php endif; ?>
    </div>
    <div class="actions"><button type="submit" <?= !$agent ? 'disabled' : '' ?>><i class="fas fa-floppy-disk"></i> Save Assignments</button></div>
  </form>
  <?= admin_pagination_controls($totalGrowers, $page, $perPage, ['agent' => $agent ? (int) $agent['id'] : '']) ?>
</section>
<?php admin_page_end(); ?>
