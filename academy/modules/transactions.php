<section class="grid g2" style="margin-bottom:18px">
    <article class="card">
      <div class="card-h"><h3>Academy Wallet</h3><span class="badge-pill bp-green">Learner Wallet</span></div>
      <p class="muted">Learners use the same NATCODEV wallet for paid courses, refunds, receipts, and future platform services.</p>
      <div style="font-size:2rem;font-weight:950;color:var(--green-700);margin:10px 0">NGN <?= e(number_format($walletBalance, 2)) ?></div>
      <form id="academy-fund-wallet" class="actions" style="align-items:end">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="return_url" value="<?= e(app_base_url() . '/academy/dashboard.php?screen=transactions') ?>">
        <label style="margin:0;min-width:180px">Amount<input type="number" name="amount" min="100" step="50" value="5000" required></label>
        <button class="btn btn-p" type="submit"><i class="fas fa-wallet"></i> Fund Wallet</button>
        <a class="btn btn-o" href="dashboard.php?screen=catalog"><i class="fas fa-book-open"></i> Browse Paid Courses</a>
      </form>
      <div id="academy-wallet-result" class="notice ok" style="display:none;margin-top:12px"></div>
    </article>
    <article class="card">
      <div class="card-h"><h3>Wallet Snapshot</h3></div>
      <div class="info-row"><span>Current Balance</span><strong><?= e(ac_money($walletBalance)) ?></strong></div>
      <div class="info-row"><span>Academy Transactions</span><strong><?= count($transactions) ?></strong></div>
      <div class="info-row"><span>Refund Requests</span><strong><?= $refundRequestCount ?></strong></div>
      <div class="info-row"><span>Withdrawal Requests</span><strong><?= count($academyWithdrawals) ?></strong></div>
      <div class="info-row"><span>Held Withdrawals</span><strong><?= e(ac_money((float) ($wallet['hold_balance'] ?? 0))) ?></strong></div>
      <div class="info-row"><span>Registered Courses</span><strong><?= count($registered) ?></strong></div>
    </article>
    <article class="card">
      <div class="card-h"><h3>Withdraw Academy Funds</h3><span class="badge-pill bp-teal">Monnify / Paystack</span></div>
      <form method="post" class="grid" style="gap:10px" data-withdrawal-form data-bank-url="../api/wallet-banks.php" data-resolve-url="../api/resolve-bank-account.php">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="academy_request_withdrawal"><input type="hidden" name="bank_name" data-bank-name><input type="hidden" name="bank_code" data-bank-code>
        <label>Amount<input type="number" name="withdraw_amount" min="<?= e((string) wallet_withdrawal_min_amount()) ?>" step="50" required></label><label>Withdrawal Method<select name="withdraw_provider" data-provider><option value="monnify">Monnify withdrawal</option><option value="paystack">Paystack withdrawal</option></select></label>
        <label>Receiving Bank<select data-bank-select required><option value="">Loading banks...</option></select></label><label>Account Number<input name="account_number" data-account-number inputmode="numeric" maxlength="10" required></label><label>Verified Name<input name="account_name" data-account-name readonly required></label>
        <div class="notice ok" data-resolve-status>Verify bank account before withdrawal.</div><label>Note<textarea name="withdraw_note" placeholder="Academy refund, instructor earning, or learner wallet withdrawal"></textarea></label><button class="btn btn-p" type="submit" data-submit-withdrawal disabled>Submit Monnify/Paystack Withdrawal</button>
      </form>
    </article>
  </section>
  <section class="card" style="margin-bottom:18px"><div class="card-h"><h3>Withdrawal Requests</h3><span class="badge-pill bp-blue"><?= count($academyWithdrawals) ?> Request<?= count($academyWithdrawals) === 1 ? '' : 's' ?></span></div><table><tr><th>Date</th><th>Reference</th><th>Route</th><th>Amount</th><th>Status</th></tr><?php foreach ($academyWithdrawals as $wd): ?><tr><td><?= e((string) $wd['requested_at']) ?></td><td><?= e((string) $wd['reference']) ?></td><td><?= e((string) $wd['provider']) ?></td><td><?= e(ac_money((float) $wd['final_amount'])) ?></td><td><?= ac_badge((string) $wd['status']) ?></td></tr><?php endforeach; ?><?php if (!$academyWithdrawals): ?><tr><td colspan="5">No withdrawal requests yet.</td></tr><?php endif; ?></table></section>
  <section class="card"><div class="card-h"><h3>Academy Payment History</h3><span class="badge-pill bp-blue"><?= count($transactions) ?> Record<?= count($transactions) === 1 ? '' : 's' ?></span></div><table><tr><th>Date</th><th>Description</th><th>Reference</th><th>Amount</th><th>Status</th><th>Refund</th></tr><?php foreach ($transactions as $tx): ?><?php $courseIdForTx = ac_course_id_from_tx($tx); $courseRegistration = null; foreach ($registered as $r) { if ((int) $r['id'] === $courseIdForTx) $courseRegistration = $r; } $blocked = $courseRegistration && ((string) $courseRegistration['completion_status'] === 'completed' || (string) $courseRegistration['certificate_status'] === 'issued'); ?><tr><td><?= e((string) $tx['created_at']) ?></td><td><?= e((string) $tx['description']) ?></td><td><?= e((string) $tx['reference']) ?></td><td><?= e(ac_money((float) $tx['amount'])) ?></td><td><?= ac_badge((string) $tx['status']) ?></td><td><?php if (!empty($tx['refund_status'])): ?><?= ac_badge((string) $tx['refund_status'], 'Refund ' . ac_status((string) $tx['refund_status'])) ?><?php elseif ($blocked): ?><?= ac_badge('completed', 'Not refundable') ?><?php elseif ((string) $tx['status'] === 'completed' && (float) $tx['amount'] > 0): ?><form method="post" class="actions"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="request_refund"><input type="hidden" name="transaction_id" value="<?= (int) $tx['id'] ?>"><input name="reason" maxlength="255" placeholder="Refund reason" required><button class="btn-s" type="submit">Request</button></form><?php else: ?><span class="muted">No action</span><?php endif; ?></td></tr><?php endforeach; ?><?php if (!$transactions): ?><tr><td colspan="6">No Academy payment transactions yet.</td></tr><?php endif; ?></table></section>