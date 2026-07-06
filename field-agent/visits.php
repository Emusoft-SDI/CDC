<?php
declare(strict_types=1);
require_once __DIR__ . '/_field.php';
$pdo = fa_pdo(); $user = fa_require_user($pdo); $tasks = fa_task_rows($pdo, $user); $visits = fa_visit_rows($pdo, $user, 12);
fa_header('Grower Visits', 'Plan today’s field movement and review completed visit history.', $user, 'visits');
?>
<section class="fa-grid">
  <article class="fa-card fa-panel span-7"><div class="fa-panel-head"><h2>Visit Schedule</h2><a class="btn soft" href="assignments.php"><i data-lucide="plus"></i> Submit Visit</a></div><?php foreach ($tasks as $task) fa_task_card($task); ?><?php if (!$tasks): ?><div class="empty">No visits scheduled.</div><?php endif; ?></article>
  <article class="fa-card fa-panel span-5"><div class="fa-panel-head"><h2>Completed Visit History</h2><span class="badge good"><?= count($visits) ?> recent</span></div><div class="fa-list"><?php foreach ($visits as $visit): ?><div class="fa-row"><span class="fa-icon"><i data-lucide="check-circle"></i></span><div><strong><?= e((string) $visit['farm_name']) ?></strong><br><span class="muted"><?= e((string) $visit['result']) ?> / <?= e(date('M j, h:i A', strtotime((string) $visit['visited_at']))) ?></span></div></div><?php endforeach; ?><?php if (!$visits): ?><div class="empty">No completed visits yet.</div><?php endif; ?></div></article>
</section>
<?php fa_footer(); ?>
