<?php
declare(strict_types=1);
require_once __DIR__ . '/_field.php';
$pdo = fa_pdo(); $user = fa_require_user($pdo);
fa_header('Support Desk', 'Get help with field tasks, app sync, assignment issues, and evidence uploads.', $user, 'support');
?>
<section class="fa-grid"><article class="fa-card fa-panel span-8"><div class="fa-panel-head"><h2>Support Tickets</h2><a class="btn" href="../support/index.php?category=field"><i data-lucide="plus"></i> Open Support Desk</a></div><div class="fa-list"><div class="fa-row"><span class="fa-icon red"><i data-lucide="life-buoy"></i></span><div><strong>Field Support</strong><br><span class="muted">GPS, offline sync, assignments, or evidence upload issues route to Field Operations.</span></div><span class="badge danger">RBAC</span></div><div class="fa-row"><span class="fa-icon orange"><i data-lucide="timer"></i></span><div><strong>Shared Queue</strong><br><span class="muted">Platform admins can triage, assign, respond, and resolve from one support queue.</span></div><span class="badge warn">Tracked</span></div></div></article><aside class="fa-card fa-panel span-4"><h2>Fast Help</h2><p class="muted">Use support when GPS, offline sync, or assigned farm data is blocking field work.</p></aside></section>
<?php fa_footer(); ?>
