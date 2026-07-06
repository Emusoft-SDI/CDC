<?php
declare(strict_types=1);
require_once __DIR__ . '/_field.php';
$pdo = fa_pdo(); $user = fa_require_user($pdo);
fa_header('Field Agent Academy', 'Continue required field verification, data collection, and safety learning.', $user, 'academy');
?>
<section class="fa-grid"><article class="fa-card fa-panel span-8"><div class="fa-panel-head"><h2>Required Learning</h2><a class="btn" href="../academy/my-learning.php"><i data-lucide="graduation-cap"></i> Open Academy</a></div><div class="fa-list"><div class="fa-row"><span class="fa-icon"><i data-lucide="shield-check"></i></span><div><strong>Verification & Documentation</strong><br><span class="muted">Required for field evidence quality.</span></div><span class="badge good">Completed</span></div><div class="fa-row"><span class="fa-icon orange"><i data-lucide="sprout"></i></span><div><strong>Pest & Disease Identification</strong><br><span class="muted">Field observation and escalation.</span></div><span class="badge warn">In Progress</span></div><div class="fa-row"><span class="fa-icon blue"><i data-lucide="database"></i></span><div><strong>Data Collection & Reporting</strong><br><span class="muted">Clean records and offline sync.</span></div><span class="badge neutral">Not Started</span></div></div></article><aside class="fa-card fa-panel span-4"><h2>Compliance</h2><p class="muted">Training completion can be used by back office before assigning sensitive verification work.</p></aside></section>
<?php fa_footer(); ?>
