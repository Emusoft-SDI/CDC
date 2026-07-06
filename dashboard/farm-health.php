<?php
declare(strict_types=1);

require_once __DIR__ . '/_user_auth.php';
require_once __DIR__ . '/../lib/dashboard-layout.php';
require_once __DIR__ . '/../lib/field-management.php';
require_once __DIR__ . '/../lib/agronomy.php';

$pdo = db();
agronomy_ensure_schema($pdo);
app_ensure_farmer_engagement_schema($pdo);

$userId = (int) $_SESSION['user_id'];
$currentUser = current_user($pdo);
if (!$currentUser) {
    session_destroy();
    redirect_to('login.php');
}
dashboard_redirect_learner_only($pdo, $currentUser);
agronomy_ensure_schema($pdo);

foreach ([
    'climate_zone' => "VARCHAR(120) NULL",
    'topography' => "VARCHAR(120) NULL",
    'soil_type' => "VARCHAR(120) NULL",
    'water_source' => "VARCHAR(120) NULL",
    'irrigation_method' => "VARCHAR(120) NULL",
    'coconut_variety' => "VARCHAR(180) NULL",
    'intercrops' => "VARCHAR(255) NULL",
    'livestock_integration' => "VARCHAR(255) NULL",
    'current_farm_activities' => "TEXT NULL",
    'production_stage' => "VARCHAR(80) NULL",
    'estimated_tree_count' => "INT NULL",
    'annual_yield_estimate' => "VARCHAR(120) NULL",
    'farming_practices' => "TEXT NULL",
    'major_challenges' => "TEXT NULL",
    'support_needs' => "TEXT NULL",
    'market_channels' => "VARCHAR(255) NULL",
] as $column => $definition) {
    app_add_column_if_missing($pdo, 'grower_farms', $column, $definition);
}

function farm_ops_clean(?string $value, string $fallback = 'Not set'): string
{
    $value = trim((string) $value);
    return $value === '' ? $fallback : $value;
}

function farm_ops_has_value(?string $value): bool
{
    $value = strtolower(trim((string) $value));
    return $value !== '' && $value !== 'none' && $value !== 'not applicable';
}

function farm_ops_short_date(?string $date, string $fallback = 'Pending'): string
{
    if (!$date) {
        return $fallback;
    }
    $time = strtotime($date);
    return $time ? date('M j, Y', $time) : $fallback;
}

$farmStmt = $pdo->prepare("
    SELECT gf.*, ns.state_name, nl.lga_name,
           COALESCE(fv.status, 'pending') verification_status,
           fv.system_confidence_score, fv.system_notes, fv.rejection_reason
    FROM grower_farms gf
    LEFT JOIN nigeria_states ns ON ns.id = gf.state_id
    LEFT JOIN nigeria_lgas nl ON nl.id = gf.lga_id
    LEFT JOIN farm_verifications fv ON fv.farm_id = gf.id
    WHERE gf.user_id = ?
    ORDER BY gf.is_primary DESC, gf.created_at ASC
");
$farmStmt->execute([$userId]);
$farms = $farmStmt->fetchAll();
$primaryFarm = $farms[0] ?? null;

$farmIds = array_map(static fn(array $farm): int => (int) $farm['id'], $farms);
$placeholders = $farmIds ? implode(',', array_fill(0, count($farmIds), '?')) : '';

$totalHectares = array_sum(array_map(static fn(array $farm): float => (float) ($farm['farm_size'] ?? 0), $farms));
$treeCount = array_sum(array_map(static fn(array $farm): int => (int) ($farm['estimated_tree_count'] ?? 0), $farms));
$intercropFarms = count(array_filter($farms, static fn(array $farm): bool => farm_ops_has_value($farm['intercrops'] ?? '')));
$livestockFarms = count(array_filter($farms, static fn(array $farm): bool => farm_ops_has_value($farm['livestock_integration'] ?? '')));

$activeHands = 0;
$farmHandRows = [];
if (app_table_exists($pdo, 'farm_hands')) {
    $handCount = $pdo->prepare("SELECT COUNT(*) FROM farm_hands WHERE grower_id = ? AND status = 'active'");
    $handCount->execute([$userId]);
    $activeHands = (int) $handCount->fetchColumn();

    $handStmt = $pdo->prepare("
        SELECT activity_category, engagement_type, status, COUNT(*) total
        FROM farm_hands
        WHERE grower_id = ?
        GROUP BY activity_category, engagement_type, status
        ORDER BY total DESC, activity_category ASC
        LIMIT 8
    ");
    $handStmt->execute([$userId]);
    $farmHandRows = $handStmt->fetchAll();
}

$fieldTasks = [];
$openTaskCount = 0;
if ($farmIds && app_table_exists($pdo, 'field_tasks')) {
    $taskCountStmt = $pdo->prepare("SELECT COUNT(*) FROM field_tasks WHERE farm_id IN ({$placeholders}) AND status NOT IN ('completed','cancelled')");
    $taskCountStmt->execute($farmIds);
    $openTaskCount = (int) $taskCountStmt->fetchColumn();

    $taskStmt = $pdo->prepare("
        SELECT ft.*, gf.farm_name, agent.name agent_name
        FROM field_tasks ft
        JOIN grower_farms gf ON gf.id = ft.farm_id
        LEFT JOIN users agent ON agent.id = ft.assigned_to
        WHERE ft.farm_id IN ({$placeholders})
        ORDER BY FIELD(ft.status, 'in_progress', 'assigned', 'pending', 'completed', 'cancelled'), ft.due_date IS NULL, ft.due_date ASC, ft.created_at DESC
        LIMIT 6
    ");
    $taskStmt->execute($farmIds);
    $fieldTasks = $taskStmt->fetchAll();
}

$recentVisits = [];
if ($farmIds && app_table_exists($pdo, 'farm_visits')) {
    $visitStmt = $pdo->prepare("
        SELECT fv.*, gf.farm_name, agent.name agent_name
        FROM farm_visits fv
        JOIN grower_farms gf ON gf.id = fv.farm_id
        LEFT JOIN users agent ON agent.id = fv.agent_id
        WHERE fv.farm_id IN ({$placeholders})
        ORDER BY fv.visited_at DESC, fv.created_at DESC
        LIMIT 5
    ");
    $visitStmt->execute($farmIds);
    $recentVisits = $visitStmt->fetchAll();
}

$agronomyCases = [];
$openAgronomyCount = 0;
if (app_table_exists($pdo, 'agronomy_cases')) {
    $caseCount = $pdo->prepare("SELECT COUNT(*) FROM agronomy_cases WHERE grower_id = ? AND status NOT IN ('resolved','closed')");
    $caseCount->execute([$userId]);
    $openAgronomyCount = (int) $caseCount->fetchColumn();

    $caseStmt = $pdo->prepare("
        SELECT ac.*, gf.farm_name,
               (SELECT COUNT(*) FROM agronomy_recommendations ar WHERE ar.case_id = ac.id AND ar.is_visible_to_grower = 1) visible_recommendations
        FROM agronomy_cases ac
        LEFT JOIN grower_farms gf ON gf.id = ac.farm_id
        WHERE ac.grower_id = ?
        ORDER BY ac.created_at DESC
        LIMIT 5
    ");
    $caseStmt->execute([$userId]);
    $agronomyCases = $caseStmt->fetchAll();
}

$imagery = [];
if ($primaryFarm && app_table_exists($pdo, 'farm_imagery')) {
    $imageryParams = [(int) $primaryFarm['id']];
    $imageryWhere = 'farm_id = ?';
    if (!empty($primaryFarm['application_id'])) {
        $imageryWhere .= ' OR farm_id = ?';
        $imageryParams[] = (int) $primaryFarm['application_id'];
    }
    $imgStmt = $pdo->prepare("SELECT * FROM farm_imagery WHERE {$imageryWhere} ORDER BY capture_date DESC LIMIT 6");
    $imgStmt->execute($imageryParams);
    $imagery = $imgStmt->fetchAll();
}

$weather = null;
if ($primaryFarm) {
    $weather = fm_weather_estimate(
        $pdo,
        (int) $primaryFarm['id'],
        $primaryFarm['latitude'] !== null ? (float) $primaryFarm['latitude'] : null,
        $primaryFarm['longitude'] !== null ? (float) $primaryFarm['longitude'] : null
    );
}

$productionStage = farm_ops_clean((string) ($primaryFarm['production_stage'] ?? ''), 'Pre-yield / profile pending');
$yieldEstimate = farm_ops_clean((string) ($primaryFarm['annual_yield_estimate'] ?? ''), 'Not producing yet');
$primaryLocation = $primaryFarm
    ? trim(farm_ops_clean((string) ($primaryFarm['lga_name'] ?? ''), '') . ' ' . farm_ops_clean((string) ($primaryFarm['state_name'] ?? ''), ''))
    : 'Farm location pending';

dashboard_page_start('Farm Performance & Operations', [
    'active' => 'farm-health.php',
    'description' => 'Track dwarf coconut establishment, pre-yield progress, intercrops, livestock, labor, field review, and agronomy outcomes.',
    'wide' => true,
]);
?>
<style>
  .ops-workspace { display:grid; gap:18px; }
  .ops-hero { display:grid; grid-template-columns:minmax(0,1.2fr) minmax(320px,.8fr); gap:18px; align-items:stretch; }
  .ops-panel { background:#fff; border:1px solid rgba(24,43,18,.1); border-radius:8px; box-shadow:var(--shadow); padding:18px; }
  .ops-head { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:14px; }
  .ops-head h2, .ops-panel h2 { margin:0; color:var(--green); font-size:1.15rem; }
  .ops-head p { margin:6px 0 0; color:var(--muted); line-height:1.5; }
  .ops-summary { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px; }
  .ops-stat { border:1px solid var(--line); border-radius:8px; background:#fbfcfa; padding:14px; }
  .ops-stat span { display:block; color:var(--muted); font-size:.84rem; font-weight:800; }
  .ops-stat strong { display:block; color:var(--green); font-size:1.75rem; line-height:1.05; margin-top:7px; }
  .ops-stage { display:flex; align-items:flex-start; gap:14px; padding:15px; border:1px solid var(--line); border-radius:8px; background:linear-gradient(135deg,#fffdf5,#f7fbf4); }
  .ops-icon { width:58px; height:58px; border-radius:8px; display:grid; place-items:center; background:#14733a; color:#fff; font-weight:900; flex:0 0 auto; }
  .ops-stage h3 { margin:0; color:var(--green); font-size:1.35rem; }
  .ops-stage p { margin:7px 0 0; color:var(--muted); line-height:1.5; }
  .ops-meta { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; margin-top:14px; }
  .ops-meta div { border:1px solid var(--line); border-radius:7px; padding:10px; background:#fbfcfa; }
  .ops-meta span { display:block; color:var(--muted); font-size:.82rem; margin-bottom:4px; }
  .ops-meta strong { color:#26351f; }
  .ops-library { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; }
  .ops-card { border:1px solid rgba(24,43,18,.1); border-radius:8px; background:#fff; box-shadow:0 10px 26px rgba(24,43,18,.06); padding:15px; display:grid; gap:12px; }
  .ops-card-top { display:flex; justify-content:space-between; gap:12px; align-items:flex-start; }
  .ops-card h3 { margin:0; color:var(--green); font-size:1.08rem; line-height:1.25; }
  .ops-detail-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:9px; }
  .ops-detail-grid div { border-top:1px solid #edf1ea; padding-top:9px; }
  .ops-detail-grid span { display:block; color:var(--muted); font-size:.8rem; font-weight:800; margin-bottom:3px; }
  .ops-detail-grid strong { color:#26351f; font-size:.94rem; }
  .ops-row { border:1px solid var(--line); border-radius:8px; padding:12px; background:#fbfcfb; display:grid; gap:5px; }
  .ops-empty { border:1px dashed var(--line); border-radius:8px; padding:20px; background:#fbfcfa; color:var(--muted); }
  .ops-actions { display:flex; flex-wrap:wrap; gap:9px; margin-top:2px; }
  @media (max-width: 980px) {
    .ops-hero, .ops-summary, .ops-library { grid-template-columns:1fr; }
    .ops-detail-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }
  }
  @media (max-width: 560px) {
    .ops-meta, .ops-detail-grid { grid-template-columns:1fr; }
  }
</style>

<section class="ops-workspace" aria-label="Farm operations summary">
  <section class="ops-hero">
    <article class="ops-panel">
      <div class="ops-head">
        <div>
          <h2>Farm Operations Summary</h2>
          <p>Dwarf coconut establishment, intercrops, livestock, labor, field tasks, and advisory cases are summarized as farm performance.</p>
        </div>
        <?= ntv_badge($farms ? 'active' : 'pending', $farms ? count($farms) . ' farm record(s)' : 'Farm profile pending') ?>
      </div>
      <div class="ops-summary">
        <div class="ops-stat"><span>Registered Farms</span><strong><?= count($farms) ?></strong></div>
        <div class="ops-stat"><span>Total Hectares</span><strong><?= e(number_format($totalHectares, 2)) ?></strong></div>
        <div class="ops-stat"><span>Coconut Trees</span><strong><?= $treeCount > 0 ? e(number_format($treeCount)) : '0' ?></strong></div>
        <div class="ops-stat"><span>Farm Hands</span><strong><?= $activeHands ?></strong></div>
      </div>
      <div class="ops-actions" style="margin-top:14px;">
        <a class="button" href="fields.php">Open Fields</a>
        <a class="button secondary" href="profile.php#hands">Farm Hands</a>
        <a class="button secondary" href="agronomist.php">Agronomy Advisory</a>
        <a class="button secondary" href="reports.php">My Reports</a>
      </div>
    </article>

    <article class="ops-panel">
      <div class="ops-stage">
        <div class="ops-icon">3Y</div>
        <div>
          <h3><?= e($productionStage) ?></h3>
          <p>Coconut may not yield in the first 3 years, so this page tracks establishment work and bridge-income activity instead of treating the farm as inactive.</p>
        </div>
      </div>
      <div class="ops-meta">
        <div><span>Primary Farm</span><strong><?= e((string) ($primaryFarm['farm_name'] ?? 'Not registered')) ?></strong></div>
        <div><span>Location</span><strong><?= e($primaryLocation !== '' ? $primaryLocation : 'Pending') ?></strong></div>
        <div><span>Yield Estimate</span><strong><?= e($yieldEstimate) ?></strong></div>
        <div><span>Weather</span><strong><?= $weather ? e((string) $weather['temperature_c']) . 'C / Rain ' . e((string) $weather['rainfall_mm']) . 'mm' : 'Pending' ?></strong></div>
      </div>
      <?php if ($weather): ?><p class="note"><?= e((string) $weather['summary']) ?></p><?php endif; ?>
    </article>
  </section>

  <section class="ops-library">
    <article class="ops-panel">
      <div class="ops-head">
        <div>
          <h2>Coconut, Intercrops & Livestock</h2>
          <p>Bridge income and livestock activity count as farm performance before coconut harvest begins.</p>
        </div>
      </div>
      <div class="ops-detail-grid">
        <div><span>Coconut Variety</span><strong><?= e(farm_ops_clean((string) ($primaryFarm['coconut_variety'] ?? ''))) ?></strong></div>
        <div><span>Intercrop Farms</span><strong><?= $intercropFarms ?> of <?= count($farms) ?></strong></div>
        <div><span>Livestock Farms</span><strong><?= $livestockFarms ?> of <?= count($farms) ?></strong></div>
      </div>
      <div class="ops-row">
        <strong>Intercrops</strong>
        <span class="muted"><?= e(farm_ops_clean((string) ($primaryFarm['intercrops'] ?? ''), 'No intercrop recorded yet')) ?></span>
      </div>
      <div class="ops-row">
        <strong>Livestock</strong>
        <span class="muted"><?= e(farm_ops_clean((string) ($primaryFarm['livestock_integration'] ?? ''), 'No livestock recorded yet')) ?></span>
      </div>
      <div class="ops-row">
        <strong>Current Activities</strong>
        <span class="muted"><?= nl2br(e(farm_ops_clean((string) ($primaryFarm['current_farm_activities'] ?? ''), 'No current activity summary recorded yet'))) ?></span>
      </div>
    </article>

    <article class="ops-panel">
      <div class="ops-head">
        <div>
          <h2>Field Work & Advisory</h2>
          <p>Visits, assignments, and agronomy cases show what the farm team is acting on.</p>
        </div>
      </div>
      <div class="ops-detail-grid">
        <div><span>Open Field Tasks</span><strong><?= $openTaskCount ?></strong></div>
        <div><span>Recent Visits</span><strong><?= count($recentVisits) ?></strong></div>
        <div><span>Open Advisory Cases</span><strong><?= $openAgronomyCount ?></strong></div>
      </div>
      <div class="ops-actions">
        <a class="button secondary" href="inbox.php?topic=farm-health">Request Farm Review</a>
        <a class="button secondary" href="agronomist.php">Request Agronomy Help</a>
      </div>
    </article>
  </section>

  <section class="ops-panel">
    <div class="ops-head">
      <div>
        <h2>Registered Farms</h2>
        <p>Each farm shows establishment status, verification, production activity, and next useful action.</p>
      </div>
    </div>
    <div class="ops-library">
      <?php foreach ($farms as $farm): ?>
        <article class="ops-card">
          <div class="ops-card-top">
            <div>
              <h3><?= e((string) $farm['farm_name']) ?></h3>
              <p class="note" style="margin:5px 0 0;"><?= e((string) ($farm['lga_name'] ?? 'LGA pending')) ?> / <?= e((string) ($farm['state_name'] ?? 'State pending')) ?></p>
            </div>
            <?= ntv_badge((string) $farm['verification_status']) ?>
          </div>
          <div class="ops-detail-grid">
            <div><span>Size</span><strong><?= e(number_format((float) ($farm['farm_size'] ?? 0), 2)) ?> ha</strong></div>
            <div><span>Stage</span><strong><?= e(farm_ops_clean((string) ($farm['production_stage'] ?? ''))) ?></strong></div>
            <div><span>Trees</span><strong><?= (int) ($farm['estimated_tree_count'] ?? 0) ?></strong></div>
            <div><span>Intercrops</span><strong><?= e(farm_ops_clean((string) ($farm['intercrops'] ?? ''), 'None recorded')) ?></strong></div>
            <div><span>Livestock</span><strong><?= e(farm_ops_clean((string) ($farm['livestock_integration'] ?? ''), 'None recorded')) ?></strong></div>
            <div><span>Yield</span><strong><?= e(farm_ops_clean((string) ($farm['annual_yield_estimate'] ?? ''), 'Not producing yet')) ?></strong></div>
          </div>
          <?php if (!empty($farm['major_challenges']) || !empty($farm['support_needs'])): ?>
            <div class="ops-row">
              <strong>Needs Attention</strong>
              <span class="muted"><?= nl2br(e(trim((string) ($farm['major_challenges'] ?? '') . "\n" . (string) ($farm['support_needs'] ?? '')))) ?></span>
            </div>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
      <?php if (!$farms): ?><div class="ops-empty">No farm is registered yet. Use Profile when you are ready to add farm details; the operations summary will fill from that record.</div><?php endif; ?>
    </div>
  </section>

  <section class="ops-library">
    <article class="ops-panel">
      <div class="ops-head"><div><h2>Field Tasks</h2><p>Assignments created by the back office or field network.</p></div></div>
      <?php foreach ($fieldTasks as $task): ?>
        <div class="ops-row">
          <strong><?= e(ntv_status_label((string) $task['task_type'])) ?> / <?= e((string) $task['farm_name']) ?></strong>
          <span class="muted"><?= e(ntv_status_label((string) $task['status'])) ?><?= !empty($task['agent_name']) ? ' / ' . e((string) $task['agent_name']) : '' ?><?= !empty($task['due_date']) ? ' / Due ' . e(farm_ops_short_date((string) $task['due_date'])) : '' ?></span>
        </div>
      <?php endforeach; ?>
      <?php if (!$fieldTasks): ?><div class="ops-empty">No field task is currently assigned. Request review only when you need a visit or operational support.</div><?php endif; ?>
    </article>

    <article class="ops-panel">
      <div class="ops-head"><div><h2>Agronomy Cases</h2><p>Technical issues and recommendations linked to your farms.</p></div></div>
      <?php foreach ($agronomyCases as $case): ?>
        <div class="ops-row">
          <strong><?= e((string) $case['case_ref']) ?> - <?= e((string) $case['title']) ?></strong>
          <span class="muted"><?= e(agronomy_statuses()[$case['status']] ?? (string) $case['status']) ?> / <?= e((string) ($case['farm_name'] ?? 'General')) ?> / <?= (int) $case['visible_recommendations'] ?> recommendation(s)</span>
        </div>
      <?php endforeach; ?>
      <?php if (!$agronomyCases): ?><div class="ops-empty">No agronomy case yet. Open Agronomy Advisory when you need technical support.</div><?php endif; ?>
    </article>
  </section>

  <section class="ops-library">
    <article class="ops-panel">
      <div class="ops-head"><div><h2>Labor Mix</h2><p>Farm hands by activity, work type, and status.</p></div><a class="button secondary" href="profile.php#hands">Manage</a></div>
      <?php foreach ($farmHandRows as $row): ?>
        <div class="ops-row">
          <strong><?= e(ntv_status_label((string) $row['activity_category'])) ?></strong>
          <span class="muted"><?= (int) $row['total'] ?> worker(s) / <?= e(ntv_status_label((string) $row['engagement_type'])) ?> / <?= e(ntv_status_label((string) $row['status'])) ?></span>
        </div>
      <?php endforeach; ?>
      <?php if (!$farmHandRows): ?><div class="ops-empty">No farm hand record is attached yet.</div><?php endif; ?>
    </article>

    <article class="ops-panel">
      <div class="ops-head"><div><h2>Recent Imagery & Visits</h2><p>Visual evidence and visit records support verification and reporting.</p></div></div>
      <?php foreach ($imagery as $img): ?>
        <div class="ops-row">
          <strong><?= e(ucfirst((string) ($img['imagery_type'] ?? 'Imagery'))) ?></strong>
          <span class="muted"><a href="<?= e((string) $img['image_url']) ?>" target="_blank" rel="noopener">Open image</a> / <?= e(farm_ops_short_date((string) ($img['capture_date'] ?? null))) ?></span>
        </div>
      <?php endforeach; ?>
      <?php foreach ($recentVisits as $visit): ?>
        <div class="ops-row">
          <strong><?= e((string) $visit['farm_name']) ?> visit</strong>
          <span class="muted"><?= e(ntv_status_label((string) $visit['result'])) ?><?= !empty($visit['agent_name']) ? ' / ' . e((string) $visit['agent_name']) : '' ?> / <?= e(farm_ops_short_date((string) $visit['visited_at'])) ?></span>
        </div>
      <?php endforeach; ?>
      <?php if (!$imagery && !$recentVisits): ?><div class="ops-empty">No imagery or field visit has been recorded yet.</div><?php endif; ?>
    </article>
  </section>
</section>
<?php dashboard_page_end(); ?>
