<?php
declare(strict_types=1);
require_once __DIR__ . '/_field.php';
$pdo = fa_pdo();
$user = fa_require_user($pdo);
$tasks = fa_task_rows($pdo, $user);
$role = fa_role_key($user);
$visitRows = [];
try {
    $where = "(fv.agent_id = ? OR ? = 'admin')";
    $params = [(int) $user['id'], $role];
    $stmt = $pdo->prepare("SELECT fv.*, gf.farm_name, u.name grower_name, s.state_name, l.lga_name FROM farm_visits fv JOIN grower_farms gf ON gf.id=fv.farm_id JOIN users u ON u.id=gf.user_id LEFT JOIN nigeria_states s ON s.id=gf.state_id LEFT JOIN nigeria_lgas l ON l.id=gf.lga_id WHERE {$where} ORDER BY fv.visited_at DESC, fv.id DESC LIMIT 40");
    $stmt->execute($params);
    $visitRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $visitRows = [];
}
fa_header('Field Evidence', 'Capture photos, GPS notes, visit findings, and supporting documents.', $user, 'evidence');
?>
<section class="fa-grid">
  <article class="fa-card fa-panel span-7">
    <div class="fa-panel-head"><h2>Evidence Queue</h2><a class="btn" href="assignments.php"><i data-lucide="camera"></i> Upload Through Visit Form</a></div>
    <div class="fa-list">
      <?php foreach ($tasks as $task): ?>
        <a class="fa-row" href="assignments.php">
          <img class="thumb" src="../assets/public/field-agent-operations-hero.png" alt="">
          <div><strong><?= e((string) $task['farm_name']) ?></strong><br><span class="muted">Capture GPS, photos/documents, crop condition, pest signs, soil notes, and farmer notes.</span></div>
          <span class="badge warn">Pending</span>
        </a>
      <?php endforeach; ?>
      <?php if (!$tasks): ?><div class="empty">No evidence pending.</div><?php endif; ?>
    </div>
  </article>
  <aside class="fa-card fa-panel span-5">
    <div class="fa-panel-head"><h2>Evidence Checklist</h2></div>
    <div class="fa-list">
      <div class="fa-row"><span class="fa-icon"><i data-lucide="map-pinned"></i></span><div><strong>GPS point</strong><br><span class="muted">Capture current farm location.</span></div></div>
      <div class="fa-row"><span class="fa-icon blue"><i data-lucide="image"></i></span><div><strong>Farm photos</strong><br><span class="muted">Boundary, stands, intercropping, access road.</span></div></div>
      <div class="fa-row"><span class="fa-icon orange"><i data-lucide="sprout"></i></span><div><strong>Agronomy notes</strong><br><span class="muted">Pests, water stress, weeds, soil.</span></div></div>
      <div class="fa-row"><span class="fa-icon gold"><i data-lucide="file-text"></i></span><div><strong>Supporting records</strong><br><span class="muted">PDF/DOC records where relevant.</span></div></div>
    </div>
  </aside>
  <article class="fa-card fa-panel span-12">
    <div class="fa-panel-head"><h2>Submitted Evidence</h2><span class="badge good"><?= count($visitRows) ?> visit(s)</span></div>
    <div class="fa-list">
      <?php foreach ($visitRows as $visit): $files = json_decode((string) ($visit['photos'] ?? '[]'), true); $files = is_array($files) ? $files : []; ?>
        <div class="fa-row">
          <span class="fa-icon <?= $files ? 'blue' : 'neutral' ?>"><i data-lucide="<?= $files ? 'paperclip' : 'clipboard-check' ?>"></i></span>
          <div>
            <strong><?= e((string) $visit['farm_name']) ?></strong><br>
            <span class="muted"><?= e((string) $visit['grower_name']) ?> / <?= e(trim((string) (($visit['lga_name'] ?? '') . ', ' . ($visit['state_name'] ?? '')), ', ')) ?> / <?= e(date('M j, Y g:i A', strtotime((string) $visit['visited_at']))) ?></span><br>
            <span class="muted">Result: <?= e(ucwords(str_replace('_', ' ', (string) $visit['result']))) ?><?= $visit['distance_from_submitted_location_m'] !== null ? ' / GPS distance ' . e(number_format((float) $visit['distance_from_submitted_location_m'], 1)) . 'm' : '' ?></span>
            <?php if (trim((string) $visit['notes']) !== ''): ?><br><span class="muted"><?= e((string) $visit['notes']) ?></span><?php endif; ?>
            <?php if ($files): ?><div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px"><?php foreach ($files as $i => $file): ?><a class="badge good" target="_blank" href="view-evidence.php?visit_id=<?= (int) $visit['id'] ?>&file=<?= (int) $i ?>"><?= e((string) ($file['kind'] ?? 'file')) ?>: <?= e((string) ($file['name'] ?? 'evidence')) ?></a><?php endforeach; ?></div><?php endif; ?>
          </div>
          <span class="badge <?= $files ? 'good' : 'neutral' ?>"><?= count($files) ?> file(s)</span>
        </div>
      <?php endforeach; ?>
      <?php if (!$visitRows): ?><div class="empty">No submitted evidence yet. Use Assignments to capture the first visit evidence.</div><?php endif; ?>
    </div>
  </article>
</section>
<?php fa_footer(); ?>