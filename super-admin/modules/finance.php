<?php defined('NATCODEV_SUPER_ADMIN') || exit;
$finance = [
    'wallet_total' => 0.0,
    'wallet_hold' => 0.0,
    'negative_wallets' => 0,
    'wallet_count' => 0,
    'pending_withdrawals' => 0,
    'pending_withdrawal_amount' => 0.0,
    'revenue_total' => 0.0,
    'revenue_month' => 0.0,
    'orders_paid' => 0,
    'settlements_pending' => 0,
];
if (app_table_exists($pdo, 'wallets')) {
    $finance['wallet_total'] = (float) $pdo->query("SELECT COALESCE(SUM(balance),0) FROM wallets")->fetchColumn();
    $finance['wallet_hold'] = (float) $pdo->query("SELECT COALESCE(SUM(hold_balance),0) FROM wallets")->fetchColumn();
    $finance['negative_wallets'] = (int) $pdo->query("SELECT COUNT(*) FROM wallets WHERE balance < 0")->fetchColumn();
    $finance['wallet_count'] = (int) $pdo->query("SELECT COUNT(*) FROM wallets")->fetchColumn();
}
if (app_table_exists($pdo, 'wallet_withdrawals')) {
    $finance['pending_withdrawals'] = (int) $pdo->query("SELECT COUNT(*) FROM wallet_withdrawals WHERE status IN ('pending','processing')")->fetchColumn();
    $finance['pending_withdrawal_amount'] = (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM wallet_withdrawals WHERE status IN ('pending','processing')")->fetchColumn();
}
if (app_table_exists($pdo, 'platform_revenue_ledger')) {
    $finance['revenue_total'] = (float) $pdo->query("SELECT COALESCE(SUM(revenue_amount),0) FROM platform_revenue_ledger WHERE status IN ('earned','collected')")->fetchColumn();
    $finance['revenue_month'] = (float) $pdo->query("SELECT COALESCE(SUM(revenue_amount),0) FROM platform_revenue_ledger WHERE status IN ('earned','collected') AND created_at >= DATE_FORMAT(CURDATE(),'%Y-%m-01')")->fetchColumn();
}
if (app_table_exists($pdo, 'marketplace_orders')) {
    $finance['orders_paid'] = (int) $pdo->query("SELECT COUNT(*) FROM marketplace_orders WHERE payment_status='paid'")->fetchColumn();
    $finance['settlements_pending'] = (int) $pdo->query("SELECT COUNT(*) FROM marketplace_orders WHERE payment_status='paid' AND settled_at IS NULL")->fetchColumn();
}
$recentWithdrawals = app_table_exists($pdo, 'wallet_withdrawals')
    ? $pdo->query("SELECT wd.reference, wd.amount, wd.status, wd.provider, wd.created_at, u.name user_name FROM wallet_withdrawals wd LEFT JOIN wallets w ON w.id = wd.wallet_id LEFT JOIN users u ON u.id = w.user_id ORDER BY wd.created_at DESC, wd.id DESC LIMIT 25")->fetchAll()
    : [];
$recentRevenue = app_table_exists($pdo, 'platform_revenue_ledger')
    ? $pdo->query("SELECT revenue_ref, source_module, source_type, gross_amount, revenue_amount, status, created_at FROM platform_revenue_ledger ORDER BY created_at DESC, id DESC LIMIT 25")->fetchAll()
    : [];
$n = static fn (float $amount): string => 'NGN ' . number_format($amount, 2);
?>
<section class="panel">
  <div class="section-head">
    <div>
      <h2>Finance Oversight</h2>
      <p>Read-only platform finance position. Payouts, refunds, and fee changes remain in the admin finance room and are now fully audited.</p>
    </div>
    <div class="actions">
      <a class="button secondary" href="../admin/wallet/index.php">Open Finance Room</a>
      <a class="button secondary" href="../admin/revenue/index.php">Revenue Workspace</a>
    </div>
  </div>
  <div class="notice warn"><strong>Handling money:</strong> Super Admin monitors here; operator actions (manual credit, withdrawal approve/reject, refunds) are performed by admins with the wallet/revenue features and are recorded in the Audit Trail.</div>
</section>

<section class="stats">
  <div class="stat"><span>Total Wallet Balance</span><strong><?= e($n($finance['wallet_total'])) ?></strong></div>
  <div class="stat"><span>Held for Withdrawal</span><strong><?= e($n($finance['wallet_hold'])) ?></strong></div>
  <div class="stat"><span>Wallet Accounts</span><strong><?= (int) $finance['wallet_count'] ?></strong></div>
  <div class="stat"><span>Debit Wallets</span><strong><?= (int) $finance['negative_wallets'] ?></strong></div>
  <div class="stat"><span>Pending Withdrawals</span><strong><?= (int) $finance['pending_withdrawals'] ?></strong></div>
  <div class="stat"><span>Pending Payout Value</span><strong><?= e($n($finance['pending_withdrawal_amount'])) ?></strong></div>
  <div class="stat"><span>Platform Revenue</span><strong><?= e($n($finance['revenue_total'])) ?></strong></div>
  <div class="stat"><span>Revenue This Month</span><strong><?= e($n($finance['revenue_month'])) ?></strong></div>
  <div class="stat"><span>Paid Orders</span><strong><?= (int) $finance['orders_paid'] ?></strong></div>
  <div class="stat"><span>Settlements Pending</span><strong><?= (int) $finance['settlements_pending'] ?></strong></div>
</section>

<section class="console-grid">
  <section class="panel">
    <h2>Recent Withdrawals</h2>
    <div class="compact-list">
      <?php foreach ($recentWithdrawals as $w): ?>
        <article>
          <strong><?= e((string) ($w['user_name'] ?: 'Unknown')) ?> &middot; <?= e($n((float) $w['amount'])) ?></strong>
          <span><?= e((string) $w['status']) ?> | <?= e((string) $w['provider']) ?> | <?= e((string) $w['reference']) ?></span>
          <small><?= e(date('M j, Y g:i A', strtotime((string) $w['created_at']))) ?></small>
        </article>
      <?php endforeach; ?>
      <?php if (!$recentWithdrawals): ?><p class="empty">No withdrawal requests recorded.</p><?php endif; ?>
    </div>
  </section>
  <section class="panel">
    <h2>Recent Platform Revenue</h2>
    <div class="compact-list">
      <?php foreach ($recentRevenue as $r): ?>
        <article>
          <strong><?= e($n((float) $r['revenue_amount'])) ?> revenue</strong>
          <span><?= e((string) $r['source_module']) ?> / <?= e((string) $r['source_type']) ?> | gross <?= e($n((float) $r['gross_amount'])) ?> | <?= e((string) $r['status']) ?></span>
          <small><?= e((string) $r['revenue_ref']) ?> &middot; <?= e(date('M j, Y g:i A', strtotime((string) $r['created_at']))) ?></small>
        </article>
      <?php endforeach; ?>
      <?php if (!$recentRevenue): ?><p class="empty">No revenue ledger entries recorded.</p><?php endif; ?>
    </div>
  </section>
</section>
