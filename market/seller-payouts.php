<?php
declare(strict_types=1);
require_once __DIR__ . '/_seller.php';
$pdo = market_boot(); $user = market_require_user($pdo); seller_access_or_message($pdo, $user);
$ctx = seller_query_context($pdo, $user, false); seller_header('Payouts', 'payouts', $user, $ctx['seller']);
?>
<section class="sc-grid"><article class="sc-card sc-panel span-4"><span class="sc-icon gold"><i data-lucide="wallet"></i></span><h2 style="color:var(--green)"><?= e(marketplace_money((float) $ctx['pendingPayout'])) ?></h2><p class="muted">Available after delivery confirmation and settlement checks.</p><a class="sc-btn" href="../dashboard/wallet.php">Open Wallet</a></article><article class="sc-card sc-panel span-8"><div class="sc-panel-head"><h2>Payout Activity</h2></div><table class="sc-table"><thead><tr><th>Order</th><th>Buyer</th><th>Amount</th><th>Payment</th><th>Settlement</th></tr></thead><tbody><?php foreach ($ctx['orders'] as $row): ?><tr><td><?= e((string) $row['order_ref']) ?></td><td><?= e((string) $row['buyer_name']) ?></td><td><?= e(marketplace_money((float) $row['total_amount'])) ?></td><td><?= e(marketplace_status_label((string) ($row['payment_status'] ?? 'unpaid'))) ?></td><td><?= empty($row['settled_at']) ? 'Pending' : e((string) $row['settled_at']) ?></td></tr><?php endforeach; ?><?php if (!$ctx['orders']): ?><tr><td colspan="5">No payout activity yet.</td></tr><?php endif; ?></tbody></table></article></section>
<?php seller_footer(); ?>
