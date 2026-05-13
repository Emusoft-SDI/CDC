<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/platform-governance.php';

session_start();
$pdo = db();
admin_ensure_schema($pdo);
admin_require($pdo);
pg_ensure_schema($pdo);

$user = current_user($pdo) ?: [];
$state = pg_scope_state($pdo);
if ($state === '') {
    $state = trim((string) ($_GET['state'] ?? $user['location'] ?? ''));
}
$stateMissing = $state === '';
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid security token.';
    } else {
        try {
            $action = (string) ($_POST['action'] ?? '');
            if ($action === 'update_accreditation') {
                $farmerId = (int) ($_POST['farmer_id'] ?? 0);
                $status = in_array((string) ($_POST['accreditation_status'] ?? ''), ['not_accredited', 'pending', 'accredited', 'suspended'], true)
                    ? (string) $_POST['accreditation_status']
                    : 'pending';
                $program = trim((string) ($_POST['accreditation_program'] ?? ''));
                $pdo->prepare("
                    UPDATE users
                    SET accreditation_status = ?, accreditation_program = ?, accredited_at = IF(? = 'accredited', COALESCE(accredited_at, NOW()), accredited_at)
                    WHERE id = ? AND role = 'grower'
                ")->execute([$status, $program ?: null, $status, $farmerId]);
                $message = 'Farmer accreditation updated.';
            } elseif ($action === 'record_budget') {
                $pdo->prepare("
                    INSERT INTO state_budget_records (state_name, budget_line, amount_budgeted, amount_spent, fiscal_period, notes, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ")->execute([
                    $state,
                    trim((string) ($_POST['budget_line'] ?? '')),
                    (float) ($_POST['amount_budgeted'] ?? 0),
                    (float) ($_POST['amount_spent'] ?? 0),
                    trim((string) ($_POST['fiscal_period'] ?? '')),
                    trim((string) ($_POST['notes'] ?? '')),
                    (int) ($user['id'] ?? 0),
                ]);
                $message = 'State budget line recorded.';
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$params = $stateMissing ? [] : [$state, '%' . $state . '%', '%' . $state . '%'];
$statePredicate = $stateMissing ? '1=1' : "(ns.state_name = ? OR a.location LIKE ? OR u.location LIKE ?)";

$statsStmt = $pdo->prepare("
    SELECT
      COUNT(DISTINCT u.id) farmers,
      SUM(CASE WHEN COALESCE(u.accreditation_status, 'not_accredited') = 'accredited' THEN 1 ELSE 0 END) accredited,
      SUM(CASE WHEN COALESCE(u.accreditation_status, 'not_accredited') <> 'accredited' THEN 1 ELSE 0 END) not_accredited,
      SUM(CASE WHEN COALESCE(fv.status, 'pending') = 'verified' THEN 1 ELSE 0 END) verified_farms,
      SUM(CASE WHEN COALESCE(fv.status, 'pending') <> 'verified' THEN 1 ELSE 0 END) unverified_farms,
      COUNT(DISTINCT gf.id) farms,
      COALESCE(SUM(gf.farm_size), 0) hectares
    FROM users u
    LEFT JOIN applications a ON a.id = u.application_id
    LEFT JOIN grower_farms gf ON gf.user_id = u.id
    LEFT JOIN farm_verifications fv ON fv.farm_id = gf.id
    LEFT JOIN nigeria_states ns ON ns.id = gf.state_id OR ns.id = a.state_id
    WHERE u.role = 'grower' AND {$statePredicate}
");
$statsStmt->execute($params);
$stats = $statsStmt->fetch() ?: [];

$fieldStmt = $pdo->prepare("
    SELECT
      SUM(CASE WHEN u.role = 'field_agent' THEN 1 ELSE 0 END) field_agents,
      SUM(CASE WHEN u.is_agronomist = 1 OR u.platform_role = 'agronomist' OR sp.staff_type = 'agronomist' THEN 1 ELSE 0 END) agronomists,
      SUM(CASE WHEN u.is_extensionist = 1 OR u.platform_role = 'agric_extensionist' OR sp.staff_type IN ('extensionist','agric_extensionist') THEN 1 ELSE 0 END) extensionists
    FROM users u
    LEFT JOIN staff_profiles sp ON sp.user_id = u.id
    WHERE (? = '' OR sp.state = ? OR u.location LIKE ?)
");
$fieldStmt->execute([$state, $state, '%' . $state . '%']);
$field = $fieldStmt->fetch() ?: [];

$caseStmt = $pdo->prepare("
    SELECT ac.category, COUNT(*) total
    FROM agronomy_cases ac
    JOIN users u ON u.id = ac.grower_id
    LEFT JOIN grower_farms gf ON gf.id = ac.farm_id
    LEFT JOIN nigeria_states ns ON ns.id = gf.state_id
    WHERE ac.status NOT IN ('resolved','closed') AND (? = '' OR ns.state_name = ? OR u.location LIKE ?)
    GROUP BY ac.category
    ORDER BY total DESC
");
$caseStmt->execute([$state, $state, '%' . $state . '%']);
$caseRows = $caseStmt->fetchAll();

$farmersStmt = $pdo->prepare("
    SELECT u.id, u.name, u.email, u.created_at, u.account_status, u.accreditation_status, u.accreditation_program,
           a.app_ref, COALESCE(fv.status, 'pending') verification_status, gf.farm_name, gf.farm_size
    FROM users u
    LEFT JOIN applications a ON a.id = u.application_id
    LEFT JOIN grower_farms gf ON gf.user_id = u.id
    LEFT JOIN farm_verifications fv ON fv.farm_id = gf.id
    LEFT JOIN nigeria_states ns ON ns.id = gf.state_id OR ns.id = a.state_id
    WHERE u.role = 'grower' AND {$statePredicate}
    GROUP BY u.id
    ORDER BY u.created_at DESC
    LIMIT 12
");
$farmersStmt->execute($params);
$farmers = $farmersStmt->fetchAll();

$resourceStmt = $pdo->prepare("
    SELECT resource_name, SUM(quantity_available) quantity, unit
    FROM state_resource_inventory
    WHERE (? = '' OR state_name = ?)
    GROUP BY resource_name, unit
    ORDER BY resource_name
    LIMIT 8
");
$resourceStmt->execute([$state, $state]);
$resources = $resourceStmt->fetchAll();

$budgetStmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount_budgeted), 0) budgeted, COALESCE(SUM(amount_spent), 0) spent
    FROM state_budget_records
    WHERE (? = '' OR state_name = ?)
");
$budgetStmt->execute([$state, $state]);
$budget = $budgetStmt->fetch() ?: ['budgeted' => 0, 'spent' => 0];

admin_page_start('State Coordinator Dashboard', [
    'active' => 'state-dashboard.php',
    'description' => 'State operations for farmer management, accreditation, field network, resources, communication, training, weather, finance, and reporting.',
    'wide' => true,
    'css' => '
      :root{--primary:#0f766e;--green:#15803d;--green-dark:#0f5132;--bg:#f3fbf8;}
      .state-hero{background:linear-gradient(135deg,#ecfdf5,#fff);border-left:5px solid #0f766e}
      .ops-grid{grid-template-columns:repeat(auto-fit,minmax(250px,1fr))}
      .mini-table td,.mini-table th{font-size:.92rem}
    ',
]);
?>
<?php if ($message): ?><div class="notice ok"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>
<?php if ($stateMissing): ?><div class="notice error">Assign a state to this coordinator profile to make this dashboard production-scoped.</div><?php endif; ?>

<section class="panel state-hero">
  <h2><?= e($state ?: 'State Not Assigned') ?> Operations</h2>
  <p class="muted">This dashboard is intentionally operational: manage farmers, verification, accreditation, field network, resources, messages, training, finance, and state reporting from one place.</p>
  <div class="actions">
    <a class="button" href="users.php?role=grower">Manage Farmers</a>
    <a class="button secondary" href="fields-management.php">Farm Verification</a>
    <a class="button secondary" href="communications.php">Broadcast</a>
    <a class="button secondary" href="resource-allocation.php">Resources</a>
  </div>
</section>

<section class="stats">
  <div class="stat"><div class="metric"><?= (int) ($stats['farmers'] ?? 0) ?></div><strong>Farmers</strong><p class="muted">Registered in state</p></div>
  <div class="stat"><div class="metric"><?= (int) ($stats['accredited'] ?? 0) ?></div><strong>Accredited</strong><p class="muted"><?= (int) ($stats['not_accredited'] ?? 0) ?> not accredited</p></div>
  <div class="stat"><div class="metric"><?= (int) ($stats['verified_farms'] ?? 0) ?></div><strong>Verified Farms</strong><p class="muted"><?= (int) ($stats['unverified_farms'] ?? 0) ?> pending/rejected</p></div>
  <div class="stat"><div class="metric"><?= number_format((float) ($stats['hectares'] ?? 0), 1) ?></div><strong>Hectares</strong><p class="muted"><?= (int) ($stats['farms'] ?? 0) ?> farms</p></div>
  <div class="stat"><div class="metric"><?= (int) ($field['field_agents'] ?? 0) ?></div><strong>Field Agents</strong><p class="muted"><?= (int) ($field['agronomists'] ?? 0) ?> agronomists / <?= (int) ($field['extensionists'] ?? 0) ?> extensionists</p></div>
  <div class="stat"><div class="metric"><?= pg_currency((float) ($budget['spent'] ?? 0)) ?></div><strong>Spent</strong><p class="muted">Budgeted <?= pg_currency((float) ($budget['budgeted'] ?? 0)) ?></p></div>
</section>

<section class="grid ops-grid">
  <article class="card">
    <h2>Performance Metrics</h2>
    <p class="muted">Regional farming performance is measured by verified farms, cultivated hectares, accreditation progress, engagement, and agronomy cases.</p>
    <?php foreach ($caseRows as $row): ?><p><strong><?= e(ucwords(str_replace('_', ' ', (string) $row['category']))) ?></strong><br><span class="muted"><?= (int) $row['total'] ?> open case(s)</span></p><?php endforeach; ?>
    <?php if (!$caseRows): ?><p class="empty">No active agronomy cases in this scope.</p><?php endif; ?>
  </article>

  <article class="card">
    <h2>Resource Inventory</h2>
    <?php foreach ($resources as $resource): ?><p><strong><?= e($resource['resource_name']) ?></strong><br><span class="muted"><?= e((string) $resource['quantity']) ?> <?= e((string) $resource['unit']) ?> available</span></p><?php endforeach; ?>
    <?php if (!$resources): ?><p class="empty">No state resources recorded yet.</p><?php endif; ?>
    <a class="button secondary" href="resource-allocation.php">Manage Allocation</a>
  </article>

  <article class="card">
    <h2>Financial Management</h2>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="record_budget">
      <label>Budget Line<input name="budget_line" required></label>
      <label>Fiscal Period<input name="fiscal_period" placeholder="2026 Q2"></label>
      <label>Budgeted<input name="amount_budgeted" inputmode="decimal"></label>
      <label>Spent<input name="amount_spent" inputmode="decimal"></label>
      <label>Notes<textarea name="notes"></textarea></label>
      <button type="submit">Record Budget</button>
    </form>
  </article>
</section>

<section class="panel">
  <h2>State Farmers Management</h2>
  <table class="mini-table">
    <thead><tr><th>Farmer</th><th>Registration</th><th>Status</th><th>Farm</th><th>Accreditation</th></tr></thead>
    <tbody>
      <?php foreach ($farmers as $farmer): ?>
        <tr>
          <td><strong><?= e($farmer['name']) ?></strong><br><span class="muted"><?= e($farmer['email']) ?></span></td>
          <td><?= e(date('M j, Y', strtotime((string) $farmer['created_at']))) ?><br><span class="muted"><?= e((string) $farmer['app_ref']) ?></span></td>
          <td><?= e(ucwords((string) $farmer['account_status'])) ?><br><span class="muted">Farm <?= e(ucwords((string) $farmer['verification_status'])) ?></span></td>
          <td><?= e((string) ($farmer['farm_name'] ?? '')) ?><br><span class="muted"><?= e((string) ($farmer['farm_size'] ?? '')) ?> ha</span></td>
          <td>
            <form method="post" class="toolbar">
              <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="update_accreditation">
              <input type="hidden" name="farmer_id" value="<?= (int) $farmer['id'] ?>">
              <select name="accreditation_status">
                <?php foreach (['not_accredited' => 'Not Accredited', 'pending' => 'Pending', 'accredited' => 'Accredited', 'suspended' => 'Suspended'] as $key => $label): ?>
                  <option value="<?= e($key) ?>" <?= (string) $farmer['accreditation_status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
              <input name="accreditation_program" value="<?= e((string) $farmer['accreditation_program']) ?>" placeholder="Program">
              <button type="submit">Save</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$farmers): ?><tr><td colspan="5">No farmers found in this state scope.</td></tr><?php endif; ?>
    </tbody>
  </table>
</section>

<section class="grid ops-grid">
  <?php foreach ([
      ['Communication Hub', 'Statewide broadcasts, announcements, and query resolution.', 'communications.php'],
      ['Training & Development', 'State programs, certification tracking, and educational resources.', 'webinars.php'],
      ['Regional Weather', 'Weather snapshots, risk alerts, and historical state farming analysis.', 'fields-management.php'],
      ['Stakeholder Collaboration', 'Local businesses, partnerships, and impact tracking.', 'providers.php'],
      ['State Reports', 'Exportable state farmers, verification, resources, and performance reports.', 'reports.php'],
  ] as $tile): ?>
    <article class="card">
      <h2><?= e($tile[0]) ?></h2>
      <p class="muted"><?= e($tile[1]) ?></p>
      <a class="button secondary" href="<?= e($tile[2]) ?>">Open</a>
    </article>
  <?php endforeach; ?>
</section>
<?php admin_page_end(); ?>
