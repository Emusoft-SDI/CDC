<?php
declare(strict_types=1);
require_once __DIR__ . '/_seller.php';
$pdo = market_boot(); $user = market_require_user($pdo); seller_access_or_message($pdo, $user);
$ctx = seller_query_context($pdo, $user, false); seller_header('Disputes / Refunds', 'disputes', $user, $ctx['seller']);
?>
<section class="sc-card sc-panel"><div class="sc-panel-head"><h2>Disputes and Refunds</h2><a class="sc-btn secondary" href="../dashboard/inbox.php">Open Support Desk</a></div><div class="empty">No active seller dispute or refund queue. Marketplace buyer protection cases will appear here when raised.</div></section>
<?php seller_footer(); ?>
