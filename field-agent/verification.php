<?php
declare(strict_types=1);
require_once __DIR__ . '/_field.php';
$pdo = fa_pdo(); $user = fa_require_user($pdo); $tasks = fa_task_rows($pdo, $user);
fa_header('Verification Queue', 'Confirm farm location, grower identity, and field evidence for registry trust.', $user, 'verification');
?>
<section class="fa-card fa-panel"><div class="fa-panel-head"><h2>Growers Waiting For Field Verification</h2><a class="btn soft" href="assignments.php"><i data-lucide="clipboard-check"></i> Open Assignment Form</a></div><div class="fa-list"><?php foreach ($tasks as $task): ?><div class="fa-row"><span class="fa-icon"><i data-lucide="shield-check"></i></span><div><strong><?= e((string) $task['grower_name']) ?></strong><br><span class="muted"><?= e((string) $task['farm_name']) ?> / <?= e((string) ($task['lga_name'] ?? '')) ?> <?= e((string) ($task['state_name'] ?? '')) ?></span></div><span class="badge <?= e(fa_priority_class((string) $task['priority'])) ?>"><?= e((string) $task['status']) ?></span></div><?php endforeach; ?><?php if (!$tasks): ?><div class="empty">Your verification queue is clear.</div><?php endif; ?></div></section>
<?php fa_footer(); ?>
