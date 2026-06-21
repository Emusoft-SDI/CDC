<?php
declare(strict_types=1);
require_once __DIR__ . '/_field.php';
$pdo = fa_pdo(); $user = fa_require_user($pdo); $visits = fa_visit_rows($pdo, $user, 30);
fa_header('Field Reports', 'Review visit submissions, verification outcomes, and agronomy observations.', $user, 'reports');
?>
<section class="fa-card fa-panel"><div class="fa-panel-head"><h2>Recent Reports</h2><a class="btn soft" href="assignments.php"><i data-lucide="file-plus-2"></i> Create From Visit</a></div><div class="fa-list"><?php foreach ($visits as $visit): ?><div class="fa-row"><span class="fa-icon blue"><i data-lucide="file-text"></i></span><div><strong>RPT-<?= (int) $visit['id'] ?> / <?= e((string) $visit['farm_name']) ?></strong><br><span class="muted"><?= e((string) $visit['notes']) ?></span></div><span class="badge good"><?= e((string) $visit['result']) ?></span></div><?php endforeach; ?><?php if (!$visits): ?><div class="empty">No reports submitted yet.</div><?php endif; ?></div></section>
<?php fa_footer(); ?>
