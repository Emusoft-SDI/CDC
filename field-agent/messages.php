<?php
declare(strict_types=1);
require_once __DIR__ . '/_field.php';
$pdo = fa_pdo(); $user = fa_require_user($pdo);
fa_header('Messages', 'Coordinate with state teams, growers, and support.', $user, 'messages');
?>
<section class="fa-grid"><article class="fa-card fa-panel span-8"><div class="fa-panel-head"><h2>Field Messages</h2><a class="btn soft" href="../dashboard/inbox.php"><i data-lucide="external-link"></i> Open Full Inbox</a></div><div class="fa-list"><div class="fa-row"><span class="fa-icon"><i data-lucide="message-circle"></i></span><div><strong>State Coordinator</strong><br><span class="muted">Monthly verification target updated to 150 growers.</span></div><span class="badge good">New</span></div><div class="fa-row"><span class="fa-icon blue"><i data-lucide="message-circle"></i></span><div><strong>Agronomy Advisory</strong><br><span class="muted">New pest management guideline shared.</span></div><span class="badge neutral">Read</span></div></div></article><aside class="fa-card fa-panel span-4"><h2>Outcome</h2><p class="muted">Messages route into the existing platform inbox so records are not split between two systems.</p></aside></section>
<?php fa_footer(); ?>
