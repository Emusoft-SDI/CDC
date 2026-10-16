<?php defined('NATCODEV_SUPER_ADMIN') || exit;
require_once __DIR__ . '/../../lib/wallet-dual-control.php';
require_once __DIR__ . '/../../lib/wallet-withdrawals.php';
require_once __DIR__ . '/../../lib/circuit-breaker.php';
$walletControls = wallet_dual_control_settings($pdo);
$withdrawalPolicy = wallet_withdrawal_policy($pdo);
$cbStatuses = circuit_breaker_all_statuses($pdo);
$pendingApprovals = wallet_pending_approvals($pdo);
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
    ? $pdo->query("SELECT wd.reference, wd.amount, wd.status, wd.provider, wd.requested_at, u.name user_name FROM wallet_withdrawals wd LEFT JOIN wallets w ON w.id = wd.wallet_id LEFT JOIN users u ON u.id = w.user_id ORDER BY wd.requested_at DESC, wd.id DESC LIMIT 25")->fetchAll()
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
  <div class="notice warn"><strong>Handling money:</strong> Super Admin monitors here; operator actions (manual credit, withdrawal approve/reject, refunds) are performed by admins with the wallet/revenue features and are recorded in the Audit Trail. Dual control is <?= $walletControls['enabled'] ? 'ON — amounts at or above NGN ' . number_format($walletControls['limit'], 2) . ' queue for your approval below' : 'off — all money movements flow to operators automatically' ?>.</div>
</section>

<section class="panel">
  <form method="post">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="save_wallet_controls">
    <div class="section-head">
      <div>
        <h2>Money Controls</h2>
        <p>Dual control for wallet credits and withdrawal payouts. When enabled, amounts at or above the threshold queue for Super Admin approval; smaller amounts flow automatically with no support queue.</p>
      </div>
    </div>
    <div class="settings-grid">
      <label><span>Dual Control</span>
        <select name="wallet_dual_control_enabled">
          <option value="1" <?= $walletControls['enabled'] ? 'selected' : '' ?>>Enabled — large movements need Super Admin</option>
          <option value="0" <?= $walletControls['enabled'] ? '' : 'selected' ?>>Disabled — fully automated</option>
        </select>
      </label>
      <label><span>Approval Threshold (NGN)</span>
        <input type="number" name="wallet_dual_control_limit" min="0" step="0.01" value="<?= e((string) $walletControls['limit']) ?>">
      </label>
    </div>
    <small class="meta">Amounts at or above the threshold queue for Super Admin sign-off. With dual control enabled, a threshold of 0 requires approval for every movement.</small>
    <button type="submit" data-busy-text="Saving money controls...">Save Money Controls</button>
  </form>
</section>

<section class="panel">
  <form method="post">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="save_wallet_automation_policy">
    <div class="section-head">
      <div>
        <h2>Withdrawal Automation, Buffer, Caps &amp; Charges</h2>
        <p>Platform-wide controls for automated withdrawals, pre-flight inspection hold mode, global daily limits, charges added to transactions, and payment circuit breaker parameters. Strictly Super Admin protected.</p>
      </div>
    </div>
    <div class="settings-grid">
      <label><span>Automation Master Switch</span>
        <select name="wallet_auto_withdrawals_enabled">
          <option value="1" <?= $withdrawalPolicy['auto_enabled'] ? 'selected' : '' ?>>Enabled — automated execution active</option>
          <option value="0" <?= $withdrawalPolicy['auto_enabled'] ? '' : 'selected' ?>>Disabled — all withdrawals require manual approval</option>
        </select>
      </label>
      <label><span>Pre-Flight Buffer (Hold Mode)</span>
        <select name="wallet_auto_preflight_buffer_enabled">
          <option value="0" <?= $withdrawalPolicy['preflight_buffer_enabled'] ? '' : 'selected' ?>>OFF — immediate autonomous dispatch</option>
          <option value="1" <?= $withdrawalPolicy['preflight_buffer_enabled'] ? 'selected' : '' ?>>ON — buffer eligible requests for operator inspection</option>
        </select>
      </label>
      <label><span>Pre-Flight Buffer Duration (Minutes)</span>
        <input type="number" name="wallet_auto_preflight_buffer_minutes" min="1" max="60" value="<?= e((string) $withdrawalPolicy['preflight_buffer_minutes']) ?>">
        <small class="meta">Hold window before cron auto-dispatches (1-60 min). Operators can manually release sooner.</small>
      </label>
      <label><span>Global Platform Daily Cap (NGN)</span>
        <input type="number" name="wallet_auto_global_daily_cap" min="0" step="0.01" value="<?= e((string) $withdrawalPolicy['global_daily_cap']) ?>">
        <small class="meta">Total platform-wide automated payouts per calendar day across all users.</small>
      </label>
      <label><span>Maximum Single Auto Withdrawal (NGN)</span>
        <input type="number" name="wallet_auto_max_amount" min="0" step="0.01" value="<?= e((string) $withdrawalPolicy['max_amount']) ?>">
      </label>
      <label><span>User Daily Auto Cap (NGN)</span>
        <input type="number" name="wallet_auto_daily_user_limit" min="0" step="0.01" value="<?= e((string) $withdrawalPolicy['daily_user_limit']) ?>">
      </label>
      <label><span>Completed Withdrawals Before Auto</span>
        <input type="number" name="wallet_auto_min_completed_withdrawals" min="1" value="<?= e((string) $withdrawalPolicy['min_completed_withdrawals']) ?>">
      </label>
      <label><span>First Withdrawal Review SLA (Hours)</span>
        <input type="number" name="wallet_auto_first_withdrawal_review_hours" min="1" max="168" value="<?= e((string) $withdrawalPolicy['first_withdrawal_review_hours']) ?>">
      </label>
      <label><span>Withdrawal Flat Fee (NGN)</span>
        <input type="number" name="wallet_withdrawal_flat_fee" min="0" step="0.01" value="<?= e((string) $withdrawalPolicy['withdrawal_flat_fee']) ?>">
        <small class="meta">Added to user's wallet debit; recipient receives full requested payout.</small>
      </label>
      <label><span>Withdrawal Percent Fee (%)</span>
        <input type="number" name="wallet_withdrawal_percent_fee" min="0" step="0.01" value="<?= e((string) $withdrawalPolicy['withdrawal_percent_fee']) ?>">
      </label>
      <label><span>Deposit Flat Fee (NGN)</span>
        <input type="number" name="wallet_deposit_flat_fee" min="0" step="0.01" value="<?= e((string) $withdrawalPolicy['deposit_flat_fee']) ?>">
        <small class="meta">Added to checkout payable; user wallet is credited with full deposit amount.</small>
      </label>
      <label><span>Deposit Percent Fee (%)</span>
        <input type="number" name="wallet_deposit_percent_fee" min="0" step="0.01" value="<?= e((string) $withdrawalPolicy['deposit_percent_fee']) ?>">
      </label>
      <label><span>Circuit Breaker Failure Threshold</span>
        <input type="number" name="circuit_breaker_failure_threshold" min="1" max="20" value="<?= e((string) $withdrawalPolicy['circuit_breaker_failure_threshold']) ?>">
        <small class="meta">Consecutive provider failures before tripping breaker to temporary OPEN cooldown.</small>
      </label>
      <label><span>Circuit Breaker Cooldown (Seconds)</span>
        <input type="number" name="circuit_breaker_cooldown_seconds" min="10" max="86400" value="<?= e((string) $withdrawalPolicy['circuit_breaker_cooldown_seconds']) ?>">
        <small class="meta">Cooldown seconds before breaker transitions to HALF-OPEN for probe recovery.</small>
      </label>
    </div>
    <div style="margin-top:16px;">
      <span style="font-weight:600;display:block;margin-bottom:8px;">Providers Allowed for Auto Payout:</span>
      <div style="display:flex;gap:16px;flex-wrap:wrap;">
        <?php foreach (['monnify' => 'Monnify', 'paystack' => 'Paystack', 'fincra' => 'Fincra', 'woven' => 'Woven Finance', 'korapay' => 'Korapay', 'allawee' => 'Allawee'] as $pCode => $pName): ?>
          <label style="display:flex;align-items:center;gap:6px;">
            <input type="checkbox" name="wallet_auto_allowed_providers[]" value="<?= e($pCode) ?>" <?= in_array($pCode, $withdrawalPolicy['allowed_providers'], true) ? 'checked' : '' ?>>
            <?= e($pName) ?>
          </label>
        <?php endforeach; ?>
      </div>
    </div>
    <div style="margin-top:16px;">
      <label style="display:flex;align-items:center;gap:6px;">
        <input type="checkbox" name="wallet_auto_require_account_name" value="1" <?= $withdrawalPolicy['require_account_name'] ? 'checked' : '' ?>>
        Require verified bank account name before automated payout
      </label>
    </div>
    <button type="submit" style="margin-top:16px;" data-busy-text="Saving policy...">Save Automation &amp; Fee Policy</button>
  </form>
</section>

<section class="panel">
  <div class="section-head">
    <div>
      <h2>Gateway Circuit Breakers &amp; Auto Recovery</h2>
      <p>Real-time circuit breaker states for payout &amp; deposit providers. If consecutive gateway errors trip a breaker (OPEN), withdrawals failover to alternate gateways during cooldown. Once cooled down, a HALF-OPEN trial probe automatically recovers the gateway to CLOSED on success.</p>
    </div>
    <form method="post" style="display:inline;">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="reset_circuit_breaker">
      <input type="hidden" name="provider" value="__all__">
      <button type="submit" class="secondary" onclick="return confirm('Reset all payment gateway circuit breakers to CLOSED?')">Reset All Gateways</button>
    </form>
  </div>
  <div class="table-responsive" style="margin-top:12px;overflow-x:auto;">
    <table class="data-table" style="width:100%;text-align:left;border-collapse:collapse;">
      <thead>
        <tr style="border-bottom:1px solid #ddd;">
          <th style="padding:8px;">Gateway Provider</th>
          <th style="padding:8px;">Circuit State</th>
          <th style="padding:8px;">Failure Count</th>
          <th style="padding:8px;">Cooldown Until</th>
          <th style="padding:8px;">Last Error</th>
          <th style="padding:8px;">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($cbStatuses as $pKey => $cb): ?>
          <?php
          $st = strtolower((string) ($cb['state'] ?? 'closed'));
          $stateColor = $st === 'closed' ? '#0f7a4f' : ($st === 'half_open' ? '#d97706' : '#b42318');
          $stateBadge = $st === 'closed' ? 'CLOSED (Healthy)' : ($st === 'half_open' ? 'HALF-OPEN (Probe)' : 'OPEN (Cooldown)');
          ?>
          <tr style="border-bottom:1px solid #eee;">
            <td style="padding:8px;"><strong><?= e(ucwords($pKey)) ?></strong></td>
            <td style="padding:8px;"><span style="display:inline-block;padding:2px 8px;border-radius:4px;font-size:12px;font-weight:700;color:#fff;background-color:<?= $stateColor ?>;"><?= e($stateBadge) ?></span></td>
            <td style="padding:8px;"><?= (int) ($cb['failure_count'] ?? 0) ?> / <?= (int) ($cb['failure_threshold'] ?? 3) ?></td>
            <td style="padding:8px;"><?= $cb['cooldown_until'] ? e(date('M j, Y g:i:s A', strtotime((string) $cb['cooldown_until']))) : '<span style="color:#999;">None</span>' ?></td>
            <td style="padding:8px;max-width:260px;font-size:12px;word-break:break-word;color:#666;"><?= $cb['last_error'] ? e((string) $cb['last_error']) : '-' ?></td>
            <td style="padding:8px;">
              <form method="post" style="display:inline;">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="reset_circuit_breaker">
                <input type="hidden" name="provider" value="<?= e($pKey) ?>">
                <button type="submit" class="secondary" style="padding:3px 8px;font-size:12px;">Reset</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="panel">
  <div class="section-head">
    <div>
      <h2>Pending Money Approvals</h2>
            <p>Wallet credits, withdrawal payouts and marketplace payouts queued by the dual-control threshold. Approving a credit moves the funds; approving a withdrawal initiates the bank payout; approving a marketplace payout applies the queued payout status; rejecting leaves the funds untouched.</p>
    </div>
    <a class="button secondary" href="../admin/wallet/index.php?page=withdrawals">Open Finance Room</a>
  </div>
  <div class="audit-list">
    <?php foreach ($pendingApprovals as $approval): ?>
      <?php
      $requestType = (string) $approval['request_type'];
      $isCredit = $requestType === 'wallet_credit';
      $isPayout = $requestType === 'payout_approval';
      $payload = json_decode((string) $approval['payload_json'], true);
      $payload = is_array($payload) ? $payload : [];
      $approvalAmount = $isCredit ? (float) ($payload['amount'] ?? 0) : ($isPayout ? (float) ($approval['payout_amount'] ?? 0) : (float) ($approval['withdrawal_amount'] ?? 0));
      $approvalTitle = $isCredit ? 'Wallet credit' : ($isPayout ? 'Marketplace payout' : 'Withdrawal payout');
      $approvalAction = $isCredit ? 'review_wallet_credit' : ($isPayout ? 'review_payout_approval' : 'review_withdrawal_approval');
      $approvalTarget = $isPayout && (string) ($approval['payout_order_ref'] ?? '') !== '' ? (string) $approval['payout_order_ref'] : (string) ($approval['target_label'] ?: ('#' . $approval['target_id']));
      ?>
      <div>
        <strong><?= e($approvalTitle) ?> &middot; NGN <?= e(number_format($approvalAmount, 2)) ?></strong>
        <span><?= e($approvalTarget) ?></span>
        <small>Requested by <?= e((string) ($approval['requester_name'] ?: ($approval['requester_email'] ?: ('Admin #' . $approval['requested_by'])))) ?> on <?= e(date('M j, Y g:i A', strtotime((string) $approval['created_at']))) ?><?= (string) $approval['reason'] !== '' ? ' &middot; ' . e((string) $approval['reason']) : '' ?></small>
        <form method="post" class="actions" style="margin-top:10px">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="<?= e($approvalAction) ?>">
          <input type="hidden" name="request_id" value="<?= (int) $approval['id'] ?>">
          <input name="review_note" placeholder="Optional review note">
          <button type="submit" name="decision" value="approve" data-busy-text="Approving..."><?= $isCredit ? 'Approve &amp; Credit' : 'Approve &amp; Pay Out' ?></button>
          <button type="submit" name="decision" value="reject" class="secondary" data-busy-text="Rejecting...">Reject</button>
        </form>
      </div>
    <?php endforeach; ?>
    <?php if (!$pendingApprovals): ?><p class="empty">No pending money approvals.</p><?php endif; ?>
  </div>
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
          <small><?= e(date('M j, Y g:i A', strtotime((string) $w['requested_at']))) ?></small>
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
