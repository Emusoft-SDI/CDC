<?php
declare(strict_types=1);
require_once __DIR__ . '/_seller.php';
$pdo = market_boot(); $user = market_require_user($pdo); seller_access_or_message($pdo, $user);
$ctx = seller_query_context($pdo, $user, false); seller_header('Buyers', 'buyers', $user, $ctx['seller']);
?>
<section class="sc-card sc-panel"><div class="sc-panel-head"><h2>Buyer Directory</h2><a class="sc-link" href="seller-messages.php">Open Messages</a></div><div class="sc-list"><?php foreach (array_slice($ctx['inquiries'],0,20) as $row): ?><div class="sc-row"><span class="sc-icon"><i data-lucide="user"></i></span><div><strong><?= e((string) $row['buyer_name']) ?></strong><br><span class="muted"><?= e((string) $row['buyer_phone']) ?> / <?= e((string) $row['buyer_email']) ?></span></div><span class="badge good"><?= e((string) $row['status']) ?></span></div><?php endforeach; ?><?php if (!$ctx['inquiries']): ?><div class="empty">No buyer interaction yet.</div><?php endif; ?></div></section>
<?php seller_footer(); ?>
