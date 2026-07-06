<?php
declare(strict_types=1);
require_once __DIR__ . '/_field.php';

$pdo = fa_pdo();
$user = fa_require_user($pdo);
$userId = (int) $user['id'];
$wallet = wallet_get_or_create($pdo, $userId);
$notice = '';
$error = '';
$monnifyConfigured = monnify_is_configured();

if (isset($_POST['create_reserved_account']) && verify_csrf($_POST['_csrf'] ?? null)) {
    $reserved = monnify_ensure_reserved_account($pdo, $user);
    if (!empty($reserved['success'])) {
        $wallet = $reserved['wallet'];
        $notice = !empty($reserved['created']) ? 'Reserved Monnify account created for field allowance funding.' : 'Reserved Monnify account is already active.';
    } else {
        $error = (string) ($reserved['error'] ?? 'Unable to create reserved account.');
    }
}

if (isset($_POST['request_withdrawal']) && verify_csrf($_POST['_csrf'] ?? null)) {
    $withdrawal = wallet_request_withdrawal($pdo, $user, [
        'amount' => $_POST['withdraw_amount'] ?? 0,
        'provider' => $_POST['withdraw_provider'] ?? 'manual',
        'bank_code' => $_POST['bank_code'] ?? '',
        'bank_name' => $_POST['bank_name'] ?? '',
        'account_number' => $_POST['account_number'] ?? '',
        'account_name' => $_POST['account_name'] ?? '',
        'note' => $_POST['withdraw_note'] ?? 'Field allowance withdrawal',
    ]);
    if (!empty($withdrawal['success'])) {
        $notice = 'Withdrawal submitted. Reference: ' . (string) $withdrawal['reference'] . '. ' . ((string) ($withdrawal['review_reason'] ?? 'Operator review may apply.'));
        $wallet = wallet_get_or_create($pdo, $userId);
    } else {
        $error = (string) ($withdrawal['error'] ?? 'Unable to submit withdrawal.');
    }
}

$txStmt = $pdo->prepare("SELECT * FROM wallet_transactions WHERE wallet_id = ? ORDER BY created_at DESC LIMIT 30");
$txStmt->execute([(int) $wallet['id']]);
$transactions = $txStmt->fetchAll();
$wdStmt = $pdo->prepare("SELECT * FROM wallet_withdrawals WHERE wallet_id = ? ORDER BY requested_at DESC LIMIT 20");
$wdStmt->execute([(int) $wallet['id']]);
$withdrawals = $wdStmt->fetchAll();
$monthCredit = 0.0;
$monthDebit = 0.0;
foreach ($transactions as $tx) {
    if (strtotime((string) $tx['created_at']) >= strtotime(date('Y-m-01 00:00:00'))) {
        if (($tx['type'] ?? '') === 'credit') { $monthCredit += (float) $tx['amount']; }
        if (($tx['type'] ?? '') === 'withdrawal' || ($tx['direction'] ?? '') === 'outflow') { $monthDebit += abs((float) $tx['amount']); }
    }
}

fa_header('Wallet & Allowance', 'Self-contained finance for allowances, reimbursements, deposits, withdrawals, and payout status.', $user, 'wallet');
?>
<?php if ($notice): ?><div class="fa-card fa-panel" style="border-left:5px solid var(--green);margin-bottom:16px"><?= e($notice) ?></div><?php endif; ?>
<?php if ($error): ?><div class="fa-card fa-panel" style="border-left:5px solid var(--red);margin-bottom:16px"><?= e($error) ?></div><?php endif; ?>
<section class="fa-kpis">
  <article class="fa-card fa-kpi"><span class="fa-icon"><i data-lucide="wallet"></i></span><div><small>Available</small><b>NGN <?= e(number_format((float) $wallet['balance'], 2)) ?></b><span>Spendable</span></div></article>
  <article class="fa-card fa-kpi"><span class="fa-icon gold"><i data-lucide="lock"></i></span><div><small>On Hold</small><b>NGN <?= e(number_format((float) ($wallet['hold_balance'] ?? 0), 2)) ?></b><span>Withdrawals</span></div></article>
  <article class="fa-card fa-kpi"><span class="fa-icon blue"><i data-lucide="arrow-down-left"></i></span><div><small>Month Inflow</small><b>NGN <?= e(number_format($monthCredit, 2)) ?></b><span>Credits</span></div></article>
  <article class="fa-card fa-kpi"><span class="fa-icon orange"><i data-lucide="arrow-up-right"></i></span><div><small>Month Outflow</small><b>NGN <?= e(number_format($monthDebit, 2)) ?></b><span>Withdrawals</span></div></article>
</section>
<section class="fa-grid">
  <article class="fa-card fa-panel span-6">
    <div class="fa-panel-head"><h2>Reserved Funding Account</h2><span class="badge <?= $monnifyConfigured ? 'good' : 'warn' ?>"><?= $monnifyConfigured ? 'Monnify ready' : 'Setup needed' ?></span></div>
    <?php if (!empty($wallet['reserved_account_number'])): ?>
      <div class="fa-list"><div class="fa-row"><span class="fa-icon"><i data-lucide="building-2"></i></span><div><strong><?= e((string) $wallet['reserved_account_bank_name']) ?></strong><br><span class="muted"><?= e((string) $wallet['reserved_account_name']) ?></span></div><b><?= e((string) $wallet['reserved_account_number']) ?></b></div></div>
      <p class="muted">Transfers to this account are reconciled to this field wallet after Monnify confirmation.</p>
    <?php else: ?>
      <form method="post" class="field-form"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><button class="btn" name="create_reserved_account" <?= $monnifyConfigured ? '' : 'disabled' ?>><i data-lucide="plus"></i>Create Monnify Reserved Account</button></form>
      <?php if (!$monnifyConfigured): ?><p class="muted"><?= e(monnify_configuration_error()) ?></p><?php endif; ?>
    <?php endif; ?>
  </article>
  <article class="fa-card fa-panel span-6">
    <div class="fa-panel-head"><h2>Request Withdrawal</h2><span class="badge warn">First withdrawal 24h approval</span></div>
    <form method="post" class="field-form">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <div class="field-grid"><label>Amount<input type="number" min="<?= e((string) wallet_withdrawal_min_amount()) ?>" step="50" name="withdraw_amount" required></label><label>Provider<select name="withdraw_provider"><option value="monnify">Monnify</option><option value="paystack">Paystack</option><option value="manual">Manual admin payout</option></select></label></div>
      <div class="field-grid"><label>Bank Name<input name="bank_name" placeholder="Receiving bank" required></label><label>Bank Code<input name="bank_code" placeholder="Provider bank code"></label></div>
      <div class="field-grid"><label>Account Number<input name="account_number" inputmode="numeric" maxlength="10" required></label><label>Account Name<input name="account_name" required></label></div>
      <label>Note<textarea name="withdraw_note" placeholder="Allowance, transport reimbursement, field expense, or payout note"></textarea></label>
      <button class="btn" name="request_withdrawal"><i data-lucide="send"></i>Submit Withdrawal</button>
    </form>
  </article>
  <article class="fa-card fa-panel span-12">
    <div class="fa-panel-head"><h2>Withdrawal Requests</h2><span class="badge neutral"><?= count($withdrawals) ?> record(s)</span></div>
    <div class="fa-list"><?php foreach ($withdrawals as $wd): ?><div class="fa-row"><span class="fa-icon <?= !empty($wd['review_required']) ? 'orange' : '' ?>"><i data-lucide="banknote"></i></span><div><strong><?= e((string) $wd['reference']) ?> / NGN <?= e(number_format((float) $wd['amount'], 2)) ?></strong><br><span class="muted"><?= e(ucfirst((string) $wd['provider'])) ?> payout. <?= e((string) ($wd['review_reason'] ?? '')) ?> <?= !empty($wd['review_due_at']) ? 'Due ' . e((string) $wd['review_due_at']) : '' ?></span></div><span class="badge <?= $wd['status']==='approved'?'good':($wd['status']==='rejected'?'danger':'warn') ?>"><?= e((string) $wd['status']) ?></span></div><?php endforeach; ?><?php if (!$withdrawals): ?><div class="empty">No withdrawal requests yet.</div><?php endif; ?></div>
  </article>
  <article class="fa-card fa-panel span-12">
    <div class="fa-panel-head"><h2>Ledger</h2><a class="btn soft" href="reports.php?report=finance&format=csv"><i data-lucide="download"></i>Export Finance</a></div>
    <div class="fa-list"><?php foreach ($transactions as $tx): ?><div class="fa-row"><span class="fa-icon <?= ($tx['type'] ?? '') === 'credit' ? 'blue' : 'gold' ?>"><i data-lucide="receipt"></i></span><div><strong><?= e((string) $tx['description']) ?></strong><br><span class="muted"><?= e((string) $tx['reference']) ?> / <?= e((string) $tx['created_at']) ?></span></div><b>NGN <?= e(number_format((float) $tx['amount'], 2)) ?><br><span class="badge neutral"><?= e((string) $tx['status']) ?></span></b></div><?php endforeach; ?><?php if (!$transactions): ?><div class="empty">No wallet transactions yet.</div><?php endif; ?></div>
  </article>
</section>
<?php fa_footer(); ?>