<?php
declare(strict_types=1);

require_once __DIR__ . '/_user_auth.php';
require_once __DIR__ . '/../lib/dashboard-layout.php';
require_once __DIR__ . '/../lib/agronomy.php';

$pdo = db();
agronomy_ensure_schema($pdo);

$user = current_user($pdo);
if (!$user) {
    redirect_to('login.php');
}
dashboard_redirect_learner_only($pdo, $user);
$userId = (int) $_SESSION['user_id'];
$message = '';
$error = '';

$farmsStmt = $pdo->prepare("SELECT id, farm_name FROM grower_farms WHERE user_id = ? ORDER BY is_primary DESC, farm_name");
$farmsStmt->execute([$userId]);
$farms = $farmsStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Please refresh and try again.';
    } else {
        $farmId = (int) ($_POST['farm_id'] ?? 0);
        $ownedFarmIds = array_map(static fn (array $farm): int => (int) $farm['id'], $farms);
        if ($farmId > 0 && !in_array($farmId, $ownedFarmIds, true)) {
            $error = 'Select one of your registered farms.';
        } else {
            $category = array_key_exists((string) ($_POST['category'] ?? ''), agronomy_categories()) ? (string) $_POST['category'] : 'general';
            $priority = array_key_exists((string) ($_POST['priority'] ?? ''), agronomy_priorities()) ? (string) $_POST['priority'] : 'normal';
            $title = trim((string) ($_POST['title'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            if ($title === '' || $description === '') {
                $error = 'Title and description are required.';
            } else {
                $pdo->prepare("
                    INSERT INTO agronomy_cases
                        (case_ref, grower_id, farm_id, source, category, priority, status, title, description, symptoms, crop_stage, created_by)
                    VALUES (?, ?, ?, 'grower', ?, ?, 'open', ?, ?, ?, ?, ?)
                ")->execute([
                    agronomy_case_ref(),
                    $userId,
                    $farmId ?: null,
                    $category,
                    $priority,
                    $title,
                    $description,
                    trim((string) ($_POST['symptoms'] ?? '')),
                    trim((string) ($_POST['crop_stage'] ?? '')),
                    $userId,
                ]);
                $message = 'Agronomy request submitted.';
            }
        }
    }
}

$casesStmt = $pdo->prepare("
    SELECT ac.*, gf.farm_name,
           (SELECT COUNT(*) FROM agronomy_recommendations ar WHERE ar.case_id = ac.id AND ar.is_visible_to_grower = 1) visible_recommendations
    FROM agronomy_cases ac
    LEFT JOIN grower_farms gf ON gf.id = ac.farm_id
    WHERE ac.grower_id = ?
    ORDER BY ac.created_at DESC
    LIMIT 30
");
$casesStmt->execute([$userId]);
$cases = $casesStmt->fetchAll();

$recommendations = [];
if ($cases) {
    $ids = array_column($cases, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $recStmt = $pdo->prepare("
        SELECT ar.*, ac.case_ref
        FROM agronomy_recommendations ar
        JOIN agronomy_cases ac ON ac.id = ar.case_id
        WHERE ar.is_visible_to_grower = 1 AND ar.case_id IN ({$placeholders})
        ORDER BY ar.created_at DESC
    ");
    $recStmt->execute($ids);
    foreach ($recStmt->fetchAll() as $rec) {
        $recommendations[(int) $rec['case_id']][] = $rec;
    }
}
?>
<?php dashboard_page_start('Agronomy Advisory', ['active' => 'agronomist.php', 'description' => 'Request specialist crop, soil, pest, disease, and farm management advice.', 'wide' => true]); ?>
<?php if ($message): ?><p class="success"><?= e($message) ?></p><?php endif; ?>
<?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>

<section class="layout">
  <form method="post" class="panel">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <h2>Request Agronomy Support</h2>
    <label>Farm
      <select name="farm_id">
        <option value="">General / not farm-specific</option>
        <?php foreach ($farms as $farm): ?><option value="<?= (int) $farm['id'] ?>"><?= e($farm['farm_name']) ?></option><?php endforeach; ?>
      </select>
    </label>
    <label>Category
      <select name="category">
        <?php foreach (agronomy_categories() as $key => $label): ?><option value="<?= e($key) ?>"><?= e($label) ?></option><?php endforeach; ?>
      </select>
    </label>
    <label>Priority
      <select name="priority">
        <?php foreach (agronomy_priorities() as $key => $label): ?><option value="<?= e($key) ?>"><?= e($label) ?></option><?php endforeach; ?>
      </select>
    </label>
    <label>Title<input name="title" required placeholder="e.g. Yellowing coconut seedlings"></label>
    <label>Crop Stage<input name="crop_stage" placeholder="Nursery, transplanting, mature, fruiting..."></label>
    <label>Symptoms<textarea name="symptoms" placeholder="Visible symptoms, pest signs, soil/water condition"></textarea></label>
    <label>Description<textarea name="description" required placeholder="Explain what is happening and what support you need."></textarea></label>
    <button type="submit">Submit Request</button>
  </form>

  <section>
    <h2>My Agronomy Cases</h2>
    <?php foreach ($cases as $case): ?>
      <article class="card">
        <h3><?= e($case['case_ref']) ?> - <?= e($case['title']) ?></h3>
        <p><strong><?= e(agronomy_statuses()[$case['status']] ?? $case['status']) ?></strong> / <?= e(agronomy_categories()[$case['category']] ?? $case['category']) ?> / <?= e($case['farm_name'] ?? 'General') ?></p>
        <p class="muted"><?= e($case['description']) ?></p>
        <?php foreach ($recommendations[(int) $case['id']] ?? [] as $rec): ?>
          <div class="panel">
            <strong>Recommendation</strong>
            <p><?= nl2br(e($rec['recommended_action'])) ?></p>
            <?php if ($rec['likely_cause']): ?><p class="muted">Likely cause: <?= e($rec['likely_cause']) ?></p><?php endif; ?>
            <?php if ($rec['inputs_needed']): ?><p class="muted">Inputs needed: <?= e($rec['inputs_needed']) ?></p><?php endif; ?>
            <?php if ($rec['safety_note']): ?><p class="muted">Safety/environment note: <?= e($rec['safety_note']) ?></p><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </article>
    <?php endforeach; ?>
    <?php if (!$cases): ?><p class="empty">No agronomy cases yet.</p><?php endif; ?>
  </section>
</section>
<?php dashboard_page_end(); ?>
