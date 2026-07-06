<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../lib/admin-layout.php';
require_once __DIR__ . '/../lib/agronomy.php';

$pdo = db();
admin_ensure_schema($pdo);
agronomy_ensure_schema($pdo);
admin_require($pdo);

$message = '';
$error = '';
$admin = current_user($pdo);
$categories = agronomy_categories();
$statuses = agronomy_statuses();
$priorities = agronomy_priorities();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid security token.';
    } else {
        try {
            $action = (string) ($_POST['action'] ?? '');
            if ($action === 'create_case') {
                $growerId = (int) ($_POST['grower_id'] ?? 0);
                $farmId = (int) ($_POST['farm_id'] ?? 0) ?: null;
                $title = trim((string) ($_POST['title'] ?? ''));
                $description = trim((string) ($_POST['description'] ?? ''));
                if ($growerId <= 0 || $title === '') {
                    throw new RuntimeException('Select a grower and enter a case title.');
                }
                if ($farmId !== null) {
                    $farmOwner = $pdo->prepare("SELECT user_id FROM grower_farms WHERE id = ? LIMIT 1");
                    $farmOwner->execute([$farmId]);
                    $ownerId = (int) ($farmOwner->fetchColumn() ?: 0);
                    if ($ownerId > 0 && $ownerId !== $growerId) {
                        throw new RuntimeException('The selected farm does not belong to the selected grower.');
                    }
                }
                $pdo->prepare("
                    INSERT INTO agronomy_cases
                        (case_ref, grower_id, farm_id, assigned_to, source, category, priority, status, title, description, symptoms, crop_stage, created_by)
                    VALUES (?, ?, ?, ?, 'admin', ?, ?, 'open', ?, ?, ?, ?, ?)
                ")->execute([
                    agronomy_case_ref(),
                    $growerId,
                    $farmId,
                    (int) ($_POST['assigned_to'] ?? 0) ?: null,
                    array_key_exists((string) ($_POST['category'] ?? ''), $categories) ? (string) $_POST['category'] : 'general',
                    array_key_exists((string) ($_POST['priority'] ?? ''), $priorities) ? (string) $_POST['priority'] : 'normal',
                    $title,
                    $description,
                    trim((string) ($_POST['symptoms'] ?? '')),
                    trim((string) ($_POST['crop_stage'] ?? '')),
                    (int) ($admin['id'] ?? 0),
                ]);
                $message = 'Agronomy case created and attached to the grower profile.';
            } elseif ($action === 'update_case') {
                $caseId = (int) ($_POST['case_id'] ?? 0);
                $status = array_key_exists((string) ($_POST['status'] ?? ''), $statuses) ? (string) $_POST['status'] : 'under_review';
                $priority = array_key_exists((string) ($_POST['priority'] ?? ''), $priorities) ? (string) $_POST['priority'] : 'normal';
                $assignedTo = (int) ($_POST['assigned_to'] ?? 0) ?: null;
                $pdo->prepare("UPDATE agronomy_cases SET status = ?, priority = ?, assigned_to = ?, follow_up_at = ? WHERE id = ?")
                    ->execute([$status, $priority, $assignedTo, trim((string) ($_POST['follow_up_at'] ?? '')) ?: null, $caseId]);
                $message = 'Agronomy case updated.';
            } elseif ($action === 'add_recommendation') {
                $caseId = (int) ($_POST['case_id'] ?? 0);
                $recommendation = trim((string) ($_POST['recommended_action'] ?? ''));
                if ($recommendation === '') {
                    throw new RuntimeException('Recommendation is required.');
                }
                $pdo->prepare("
                    INSERT INTO agronomy_recommendations
                        (case_id, author_id, problem_observed, likely_cause, recommended_action, urgency, inputs_needed, safety_note, follow_up_at, is_visible_to_grower)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ")->execute([
                    $caseId,
                    (int) ($admin['id'] ?? 0),
                    trim((string) ($_POST['problem_observed'] ?? '')),
                    trim((string) ($_POST['likely_cause'] ?? '')),
                    $recommendation,
                    array_key_exists((string) ($_POST['urgency'] ?? ''), $priorities) ? (string) $_POST['urgency'] : 'normal',
                    trim((string) ($_POST['inputs_needed'] ?? '')),
                    trim((string) ($_POST['safety_note'] ?? '')),
                    trim((string) ($_POST['follow_up_at'] ?? '')) ?: null,
                    isset($_POST['is_visible_to_grower']) ? 1 : 0,
                ]);
                $pdo->prepare("UPDATE agronomy_cases SET status = 'recommendation_issued', updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$caseId]);
                $message = 'Recommendation issued.';
            } elseif ($action === 'save_soil_record') {
                $pdo->prepare("
                    INSERT INTO agronomy_soil_crop_records
                        (farm_id, case_id, recorded_by, soil_ph, nitrogen, phosphorus, potassium, organic_matter, salinity, moisture_condition, crop_variety, tree_age_years, production_stage, yield_estimate, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ")->execute([
                    (int) $_POST['farm_id'],
                    (int) ($_POST['case_id'] ?? 0) ?: null,
                    (int) ($admin['id'] ?? 0),
                    ($_POST['soil_ph'] ?? '') === '' ? null : (float) $_POST['soil_ph'],
                    trim((string) ($_POST['nitrogen'] ?? '')),
                    trim((string) ($_POST['phosphorus'] ?? '')),
                    trim((string) ($_POST['potassium'] ?? '')),
                    trim((string) ($_POST['organic_matter'] ?? '')),
                    trim((string) ($_POST['salinity'] ?? '')),
                    trim((string) ($_POST['moisture_condition'] ?? '')),
                    trim((string) ($_POST['crop_variety'] ?? '')),
                    ($_POST['tree_age_years'] ?? '') === '' ? null : (float) $_POST['tree_age_years'],
                    trim((string) ($_POST['production_stage'] ?? '')),
                    trim((string) ($_POST['yield_estimate'] ?? '')),
                    trim((string) ($_POST['notes'] ?? '')),
                ]);
                $message = 'Soil and crop record saved.';
            } elseif ($action === 'save_template') {
                $pdo->prepare("
                    INSERT INTO agronomy_advisory_templates (title, category, crop_stage, body, is_active, created_by)
                    VALUES (?, ?, ?, ?, ?, ?)
                ")->execute([
                    trim((string) ($_POST['title'] ?? '')),
                    array_key_exists((string) ($_POST['category'] ?? ''), $categories) ? (string) $_POST['category'] : 'general',
                    trim((string) ($_POST['crop_stage'] ?? '')),
                    trim((string) ($_POST['body'] ?? '')),
                    isset($_POST['is_active']) ? 1 : 0,
                    (int) ($admin['id'] ?? 0),
                ]);
                $message = 'Advisory template saved.';
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$filter = (string) ($_GET['status'] ?? 'active');
$where = $filter === 'active' ? "ac.status NOT IN ('resolved','closed')" : '1=1';
if (array_key_exists($filter, $statuses)) {
    $where = 'ac.status = ' . $pdo->quote($filter);
}
$cases = $pdo->query("
    SELECT ac.*, u.name grower_name, u.email grower_email, gf.farm_name,
           assignee.name assigned_name
    FROM agronomy_cases ac
    JOIN users u ON u.id = ac.grower_id
    LEFT JOIN grower_farms gf ON gf.id = ac.farm_id
    LEFT JOIN users assignee ON assignee.id = ac.assigned_to
    WHERE {$where}
    ORDER BY FIELD(ac.priority, 'urgent','high','normal','low'), ac.updated_at DESC, ac.created_at DESC
    LIMIT 80
")->fetchAll();

$caseIds = array_column($cases, 'id');
$recommendations = [];
$soilRecords = [];
if ($caseIds) {
    $placeholders = implode(',', array_fill(0, count($caseIds), '?'));
    $stmt = $pdo->prepare("SELECT * FROM agronomy_recommendations WHERE case_id IN ({$placeholders}) ORDER BY created_at DESC");
    $stmt->execute($caseIds);
    foreach ($stmt->fetchAll() as $row) {
        $recommendations[(int) $row['case_id']][] = $row;
    }
    $stmt = $pdo->prepare("SELECT * FROM agronomy_soil_crop_records WHERE case_id IN ({$placeholders}) ORDER BY recorded_at DESC");
    $stmt->execute($caseIds);
    foreach ($stmt->fetchAll() as $row) {
        $soilRecords[(int) $row['case_id']][] = $row;
    }
}

$agronomists = $pdo->query("
    SELECT id, name, email
    FROM users
    WHERE role = 'admin' OR platform_role IN ('agronomist','agric_extensionist') OR is_agronomist = 1 OR is_extensionist = 1
    ORDER BY name
")->fetchAll();

$growers = $pdo->query("
    SELECT id, name, email
    FROM users
    WHERE role = 'grower' OR platform_role IN ('grower','investor')
    ORDER BY name
    LIMIT 500
")->fetchAll();

$farms = $pdo->query("
    SELECT gf.id, gf.user_id, gf.farm_name, u.name grower_name
    FROM grower_farms gf
    JOIN users u ON u.id = gf.user_id
    ORDER BY u.name, gf.farm_name
    LIMIT 800
")->fetchAll();

$stats = [
    'open_cases' => (int) $pdo->query("SELECT COUNT(*) FROM agronomy_cases WHERE status NOT IN ('resolved','closed')")->fetchColumn(),
    'recommendations' => (int) $pdo->query("SELECT COUNT(*) FROM agronomy_recommendations")->fetchColumn(),
    'field_observations' => (int) $pdo->query("SELECT COUNT(*) FROM agronomy_field_checklists")->fetchColumn(),
    'templates' => (int) $pdo->query("SELECT COUNT(*) FROM agronomy_advisory_templates WHERE is_active = 1")->fetchColumn(),
];

$recentObservations = $pdo->query("
    SELECT afc.*, gf.farm_name, u.name grower_name, agent.name agent_name
    FROM agronomy_field_checklists afc
    JOIN grower_farms gf ON gf.id = afc.farm_id
    JOIN users u ON u.id = gf.user_id
    LEFT JOIN users agent ON agent.id = afc.agent_id
    ORDER BY afc.created_at DESC
    LIMIT 8
")->fetchAll();

$templates = $pdo->query("
    SELECT t.*, u.name author_name
    FROM agronomy_advisory_templates t
    LEFT JOIN users u ON u.id = t.created_by
    ORDER BY t.is_active DESC, t.updated_at DESC, t.created_at DESC
    LIMIT 10
")->fetchAll();

$entryPoints = [
    ['title' => 'Grower Request', 'href' => '../dashboard/agronomist.php', 'body' => 'Growers submit crop, soil, pest, water, and farm-practice support cases.'],
    ['title' => 'Field Visit Checklist', 'href' => '../field-agent/index.php', 'body' => 'Field agents capture observations during farm visits and create agronomy cases.'],
    ['title' => 'Field Management', 'href' => 'fields-management.php', 'body' => 'Admins verify farm location, assign visits, and review GPS-based field evidence.'],
    ['title' => 'Module Setup', 'href' => '../super-admin/index.php?view=controls', 'body' => 'Super Admin activates the module, ownership, notes, and role access boundaries.'],
];
?>
<?php admin_page_start('Agronomy Advisory', [
    'active' => 'agronomy.php',
    'description' => 'Manage agronomy cases, soil and crop observations, recommendations, and advisory templates.',
    'wide' => true,
    'css' => '
      .agronomy-layout { grid-template-columns:minmax(0,1fr) 380px; }
      .entry-grid { grid-template-columns:repeat(auto-fit,minmax(230px,1fr)); }
      @media (max-width:960px) { .agronomy-layout { grid-template-columns:1fr; } }
    ',
]); ?>
<?php if ($message): ?><p class="success"><?= e($message) ?></p><?php endif; ?>
<?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>

<section class="stats">
  <div class="stat"><div class="metric"><?= (int) $stats['open_cases'] ?></div><strong>Open Cases</strong><p class="muted">Requests still needing agronomy action.</p></div>
  <div class="stat"><div class="metric"><?= (int) $stats['recommendations'] ?></div><strong>Recommendations</strong><p class="muted">Advice published by admins and agronomists.</p></div>
  <div class="stat"><div class="metric"><?= (int) $stats['field_observations'] ?></div><strong>Field Observations</strong><p class="muted">Farm visit notes feeding this module.</p></div>
  <div class="stat"><div class="metric"><?= (int) $stats['templates'] ?></div><strong>Active Templates</strong><p class="muted">Reusable advisory content.</p></div>
</section>

<section class="panel">
  <h2>Agronomy Entry Points</h2>
  <p class="muted">These are the places where this module is attached across the system.</p>
  <div class="grid entry-grid">
    <?php foreach ($entryPoints as $entry): ?>
      <article class="card">
        <h3><?= e($entry['title']) ?></h3>
        <p class="muted"><?= e($entry['body']) ?></p>
        <a class="button secondary" href="<?= e($entry['href']) ?>">Open</a>
      </article>
    <?php endforeach; ?>
  </div>
</section>

<section class="toolbar">
  <a class="button secondary" href="agronomy.php">Active</a>
  <?php foreach ($statuses as $key => $label): ?><a class="button secondary" href="agronomy.php?status=<?= e($key) ?>"><?= e($label) ?></a><?php endforeach; ?>
</section>

<section class="layout agronomy-layout">
  <div>
    <article class="card">
      <h2>Create Agronomy Case</h2>
      <p class="muted">Use this when support, farm review, phone calls, or field intelligence should become a formal agronomy case.</p>
      <form method="post" class="grid">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="create_case">
        <label>Grower
          <select name="grower_id" required>
            <option value="">Select grower</option>
            <?php foreach ($growers as $grower): ?><option value="<?= (int) $grower['id'] ?>"><?= e($grower['name']) ?><?= $grower['email'] ? ' / ' . e($grower['email']) : '' ?></option><?php endforeach; ?>
          </select>
        </label>
        <label>Farm
          <select name="farm_id">
            <option value="">General / no farm selected</option>
            <?php foreach ($farms as $farm): ?><option value="<?= (int) $farm['id'] ?>"><?= e($farm['grower_name']) ?> / <?= e($farm['farm_name']) ?></option><?php endforeach; ?>
          </select>
        </label>
        <label>Category<select name="category"><?php foreach ($categories as $key => $label): ?><option value="<?= e($key) ?>"><?= e($label) ?></option><?php endforeach; ?></select></label>
        <label>Priority<select name="priority"><?php foreach ($priorities as $key => $label): ?><option value="<?= e($key) ?>"><?= e($label) ?></option><?php endforeach; ?></select></label>
        <label>Assign To<select name="assigned_to"><option value="">Unassigned</option><?php foreach ($agronomists as $person): ?><option value="<?= (int) $person['id'] ?>"><?= e($person['name']) ?></option><?php endforeach; ?></select></label>
        <label>Crop Stage<input name="crop_stage" placeholder="e.g. seedling, flowering, fruiting"></label>
        <label>Title<input name="title" required placeholder="e.g. Yellowing coconut leaves"></label>
        <label>Symptoms<textarea name="symptoms" placeholder="Visible symptoms, pest signs, water stress, soil concerns"></textarea></label>
        <label>Description<textarea name="description" placeholder="Background, history, farmer report, urgency, photos or visit notes"></textarea></label>
        <button type="submit">Create Case</button>
      </form>
    </article>

    <?php foreach ($cases as $case): ?>
      <article class="card">
        <h2><?= e($case['case_ref']) ?> - <?= e($case['title']) ?></h2>
        <p><strong><?= e($statuses[$case['status']] ?? $case['status']) ?></strong> / <?= e($priorities[$case['priority']] ?? $case['priority']) ?> / <?= e($categories[$case['category']] ?? $case['category']) ?></p>
        <p><?= e($case['grower_name']) ?> / <?= e($case['farm_name'] ?? 'General') ?><?= $case['assigned_name'] ? ' / Assigned: ' . e($case['assigned_name']) : '' ?></p>
        <p class="muted"><?= nl2br(e((string) $case['description'])) ?></p>

        <form method="post" class="toolbar">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="update_case">
          <input type="hidden" name="case_id" value="<?= (int) $case['id'] ?>">
          <select name="status"><?php foreach ($statuses as $key => $label): ?><option value="<?= e($key) ?>" <?= $case['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
          <select name="priority"><?php foreach ($priorities as $key => $label): ?><option value="<?= e($key) ?>" <?= $case['priority'] === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
          <select name="assigned_to"><option value="">Assign agronomist</option><?php foreach ($agronomists as $person): ?><option value="<?= (int) $person['id'] ?>" <?= (int) ($case['assigned_to'] ?? 0) === (int) $person['id'] ? 'selected' : '' ?>><?= e($person['name']) ?></option><?php endforeach; ?></select>
          <input type="date" name="follow_up_at" value="<?= e((string) ($case['follow_up_at'] ?? '')) ?>">
          <button type="submit">Update</button>
        </form>

        <details>
          <summary><strong>Issue Recommendation</strong></summary>
          <form method="post">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="add_recommendation">
            <input type="hidden" name="case_id" value="<?= (int) $case['id'] ?>">
            <label>Problem Observed<textarea name="problem_observed"></textarea></label>
            <label>Likely Cause<textarea name="likely_cause"></textarea></label>
            <label>Recommended Action<textarea name="recommended_action" required></textarea></label>
            <label>Inputs Needed<textarea name="inputs_needed"></textarea></label>
            <label>Safety / Environment Note<textarea name="safety_note"></textarea></label>
            <label>Urgency<select name="urgency"><?php foreach ($priorities as $key => $label): ?><option value="<?= e($key) ?>"><?= e($label) ?></option><?php endforeach; ?></select></label>
            <label>Follow-up Date<input type="date" name="follow_up_at"></label>
            <label><input type="checkbox" name="is_visible_to_grower" value="1" checked> Visible to grower</label>
            <button type="submit">Publish Recommendation</button>
          </form>
        </details>

        <details>
          <summary><strong>Soil / Crop Record</strong></summary>
          <form method="post" class="grid">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="save_soil_record">
            <input type="hidden" name="case_id" value="<?= (int) $case['id'] ?>">
            <input type="hidden" name="farm_id" value="<?= (int) ($case['farm_id'] ?? 0) ?>">
            <label>Soil pH<input name="soil_ph" inputmode="decimal"></label>
            <label>Nitrogen<input name="nitrogen"></label>
            <label>Phosphorus<input name="phosphorus"></label>
            <label>Potassium<input name="potassium"></label>
            <label>Organic Matter<input name="organic_matter"></label>
            <label>Salinity<input name="salinity"></label>
            <label>Moisture Condition<input name="moisture_condition"></label>
            <label>Crop Variety<input name="crop_variety"></label>
            <label>Tree Age<input name="tree_age_years" inputmode="decimal"></label>
            <label>Production Stage<input name="production_stage"></label>
            <label>Yield Estimate<input name="yield_estimate"></label>
            <label>Notes<textarea name="notes"></textarea></label>
            <button type="submit">Save Soil/Crop Record</button>
          </form>
        </details>

        <?php foreach ($recommendations[(int) $case['id']] ?? [] as $rec): ?>
          <div class="panel"><strong>Recommendation</strong><p><?= nl2br(e($rec['recommended_action'])) ?></p></div>
        <?php endforeach; ?>
      </article>
    <?php endforeach; ?>
    <?php if (!$cases): ?><p class="empty">No agronomy cases match this view.</p><?php endif; ?>
  </div>

  <aside class="panel">
    <h2>Advisory Template</h2>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="save_template">
      <label>Title<input name="title" required></label>
      <label>Category<select name="category"><?php foreach ($categories as $key => $label): ?><option value="<?= e($key) ?>"><?= e($label) ?></option><?php endforeach; ?></select></label>
      <label>Crop Stage<input name="crop_stage"></label>
      <label>Body<textarea name="body" required></textarea></label>
      <label><input type="checkbox" name="is_active" checked> Active</label>
      <button type="submit">Save Template</button>
    </form>

    <h3>Saved Templates</h3>
    <?php foreach ($templates as $template): ?>
      <div class="panel" style="box-shadow:none;margin-top:10px;">
        <strong><?= e($template['title']) ?></strong>
        <p class="muted"><?= e($categories[$template['category']] ?? $template['category']) ?><?= $template['crop_stage'] ? ' / ' . e($template['crop_stage']) : '' ?></p>
        <p><?= nl2br(e(mb_strimwidth((string) $template['body'], 0, 180, '...'))) ?></p>
      </div>
    <?php endforeach; ?>
    <?php if (!$templates): ?><p class="empty">No advisory templates saved yet.</p><?php endif; ?>

    <h3>Recent Field Observations</h3>
    <?php foreach ($recentObservations as $observation): ?>
      <div class="panel" style="box-shadow:none;margin-top:10px;">
        <strong><?= e($observation['farm_name']) ?></strong>
        <p class="muted"><?= e($observation['grower_name']) ?><?= $observation['agent_name'] ? ' / Agent: ' . e($observation['agent_name']) : '' ?></p>
        <?php if ($observation['crop_symptoms']): ?><p><strong>Symptoms:</strong> <?= nl2br(e(mb_strimwidth((string) $observation['crop_symptoms'], 0, 160, '...'))) ?></p><?php endif; ?>
        <?php if ($observation['pest_signs']): ?><p><strong>Pest signs:</strong> <?= nl2br(e(mb_strimwidth((string) $observation['pest_signs'], 0, 160, '...'))) ?></p><?php endif; ?>
      </div>
    <?php endforeach; ?>
    <?php if (!$recentObservations): ?><p class="empty">No field agronomy observations yet.</p><?php endif; ?>
  </aside>
</section>
<?php admin_page_end(); ?>
