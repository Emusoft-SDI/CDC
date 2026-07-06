<?php
declare(strict_types=1);
require_once __DIR__ . '/_buyer.php';

$pdo = buyer_boot();
$user = buyer_require($pdo);
$userId = (int) $user['id'];

$walletStmt = $pdo->prepare("SELECT * FROM wallet_transactions WHERE user_id = ? AND (LOWER(description) LIKE '%refund%' OR LOWER(type) LIKE '%refund%' OR LOWER(status) IN ('refunded', 'reversed')) ORDER BY created_at DESC, id DESC LIMIT 80");
$walletStmt->execute([$userId]);
$refundTransactions = $walletStmt->fetchAll();

$orderStmt = $pdo->prepare("
    SELECT checkout_ref,
           COUNT(*) item_count,
           SUM(total_amount) subtotal,
           MAX(checkout_total) checkout_total,
           MAX(payment_status) payment_status,
           MAX(delivery_status) delivery_status,
           MAX(status) order_status,
           MAX(created_at) created_at
    FROM marketplace_orders
    WHERE buyer_user_id = ?
      AND (LOWER(payment_status) IN ('refunded', 'refund_pending', 'reversed')
        OR LOWER(status) IN ('refunded', 'cancelled', 'reversed')
        OR LOWER(delivery_status) IN ('returned', 'cancelled'))
    GROUP BY checkout_ref
    ORDER BY MAX(created_at) DESC
    LIMIT 80
");
$orderStmt->execute([$userId]);
$refundOrders = $orderStmt->fetchAll();

$refundTotal = array_reduce($refundTransactions, static fn(float $sum, array $row): float => $sum + abs((float) ($row['amount'] ?? 0)), 0.0);
$counts = buyer_counts($pdo, $user);
buyer_page_start('Buyer Refunds', 'refunds', $user, $counts);
?>
<div class="page-head"><div><h1>Refunds</h1><p>Track refund credits, reversed payments, cancelled checkouts, and support follow-up from your buyer account.</p></div><a class="btn" href="support.php"><i class="fas fa-headset"></i> Open Refund Support</a></div>
<div class="kpis">
  <div class="kpi"><i class="fas fa-rotate-left"></i><span><b><?= count($refundTransactions) ?></b><br>Refund Entries</span></div>
  <div class="kpi"><i class="fas fa-naira-sign"></i><span><b><?= e(buyer_money($refundTotal)) ?></b><br>Refund Value</span></div>
  <div class="kpi"><i class="fas fa-cart-shopping"></i><span><b><?= count($refundOrders) ?></b><br>Affected Checkouts</span></div>
  <div class="kpi"><i class="fas fa-wallet"></i><span><b><?= e(buyer_money((float) ($counts['wallet'] ?? 0))) ?></b><br>Wallet Balance</span></div>
</div>
<div class="grid">
  <section class="card span-7">
    <div class="card-head"><h2>Refund Ledger</h2><span class="badge"><?= count($refundTransactions) ?> record<?= count($refundTransactions) === 1 ? '' : 's' ?></span></div>
    <div class="list">
      <?php foreach ($refundTransactions as $tx): ?>
        <div class="row">
          <span><strong><?= e((string) ($tx['reference'] ?? 'Wallet entry')) ?></strong><br><small><?= e((string) ($tx['description'] ?? 'Refund adjustment')) ?></small></span>
          <span><strong><?= e(buyer_money(abs((float) ($tx['amount'] ?? 0)))) ?></strong><br><?= buyer_status_badge((string) ($tx['status'] ?? 'posted')) ?><br><small><?= e((string) ($tx['created_at'] ?? '')) ?></small></span>
        </div>
      <?php endforeach; ?>
      <?php if (!$refundTransactions): ?><div class="alert ok">No refund ledger entry has been posted to your buyer wallet yet.</div><?php endif; ?>
    </div>
  </section>
  <section class="card span-5">
    <div class="card-head"><h2>Refund-Related Orders</h2><a class="view" href="orders.php">All orders</a></div>
    <div class="list">
      <?php foreach ($refundOrders as $order): ?>
        <div class="row">
          <span><strong><?= e((string) $order['checkout_ref']) ?></strong><br><small><?= (int) $order['item_count'] ?> item(s) / <?= e((string) $order['created_at']) ?></small></span>
          <span><strong><?= e(buyer_money((float) ($order['checkout_total'] ?: $order['subtotal']))) ?></strong><br><?= buyer_status_badge((string) $order['payment_status']) ?></span>
        </div>
      <?php endforeach; ?>
      <?php if (!$refundOrders): ?><div class="alert ok">No cancelled, reversed, or refunded checkout record is attached to this buyer account.</div><?php endif; ?>
    </div>
  </section>
  <section class="card span-12">
    <div class="card-head"><h2>Need A Refund Review?</h2><span class="badge gold">Buyer protection</span></div>
    <p>Use support when a payment was deducted, an order was cancelled, a seller could not fulfill, or a wallet refund has not appeared. Include the checkout reference, wallet transaction reference, amount, and seller name.</p>
    <a class="btn" href="support.php?linked_record_type=refund"><i class="fas fa-paper-plane"></i> Request Refund Review</a>
  </section>
</div>
<?php buyer_page_end(); ?>
