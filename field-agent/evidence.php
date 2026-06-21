<?php
declare(strict_types=1);
require_once __DIR__ . '/_field.php';
$pdo = fa_pdo(); $user = fa_require_user($pdo); $tasks = fa_task_rows($pdo, $user);
fa_header('Field Evidence', 'Capture photos, GPS notes, visit findings, and supporting documents.', $user, 'evidence');
?>
<section class="fa-grid">
  <article class="fa-card fa-panel span-8"><div class="fa-panel-head"><h2>Evidence Queue</h2><a class="btn" href="assignments.php"><i data-lucide="camera"></i> Upload Through Visit Form</a></div><div class="fa-list"><?php foreach ($tasks as $task): ?><div class="fa-row"><img class="thumb" src="../assets/public/field-agent-operations-hero.png" alt=""><div><strong><?= e((string) $task['farm_name']) ?></strong><br><span class="muted">Photos, GPS, crop condition, and farmer notes.</span></div><span class="badge warn">Pending</span></div><?php endforeach; ?><?php if (!$tasks): ?><div class="empty">No evidence pending.</div><?php endif; ?></div></article>
  <aside class="fa-card fa-panel span-4"><div class="fa-panel-head"><h2>Evidence Checklist</h2></div><div class="fa-list"><div class="fa-row"><span class="fa-icon"><i data-lucide="map-pinned"></i></span><div><strong>GPS point</strong><br><span class="muted">Capture current farm location.</span></div></div><div class="fa-row"><span class="fa-icon blue"><i data-lucide="image"></i></span><div><strong>Farm photos</strong><br><span class="muted">Boundary, stands, intercropping, access road.</span></div></div><div class="fa-row"><span class="fa-icon orange"><i data-lucide="sprout"></i></span><div><strong>Agronomy notes</strong><br><span class="muted">Pests, water stress, weeds, soil.</span></div></div></div></aside>
</section>
<?php fa_footer(); ?>
