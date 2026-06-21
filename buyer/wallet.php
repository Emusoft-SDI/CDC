<?php
declare(strict_types=1);
require_once __DIR__ . '/_buyer.php';

$pdo = buyer_boot();
$user = buyer_require($pdo);
$userId = (int) $user['id'];
$wallet = wallet_get_or_create($pdo, $userId);

$walletNotice = '';
$walletError = '';
$callbackReference = (string) ($_GET['paymentReference'] ?? $_GET['reference'] ?? '');
if ($callbackReference !== '') {
    $verification = monnify_verify_wallet_funding($pdo, $userId, $callbackReference);
    if (($verification['status'] ?? '') === 'completed') {
        $walletNotice = 'Payment confirmed. Wallet credited.';
        $wallet = wallet_get_or_create($pdo, $userId);
    } elseif (($verification['success'] ?? false) && empty($verification['credited'])) {
        $walletNotice = 'Payment is still pending. Complete the transfer, then refresh this page.';
    } else {
        $walletError = (string) ($verification['error'] ?? 'Unable to verify payment yet.');
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
        'note' => $_POST['withdraw_note'] ?? '',
    ]);
    if ($withdrawal['success']) {
        $walletNotice = 'Withdrawal request submitted. Reference: ' . (string) $withdrawal['reference'] . '.';
        $wallet = wallet_get_or_create($pdo, $userId);
    } else {
        $walletError = (string) ($withdrawal['error'] ?? 'Unable to submit withdrawal request.');
    }
}

$stmt = $pdo->prepare("SELECT * FROM wallet_transactions WHERE wallet_id = ? ORDER BY created_at DESC LIMIT 80");
$stmt->execute([(int) $wallet['id']]);
$transactions = $stmt->fetchAll();
$wdStmt = $pdo->prepare("SELECT * FROM wallet_withdrawals WHERE wallet_id = ? ORDER BY requested_at DESC LIMIT 10");
$wdStmt->execute([(int) $wallet['id']]);
$withdrawals = $wdStmt->fetchAll();
$orderStmt = $pdo->prepare("
    SELECT checkout_ref, SUM(total_amount + COALESCE(delivery_fee, 0) + COALESCE(service_fee, 0)) total_amount, MAX(payment_status) payment_status, MAX(created_at) created_at
    FROM marketplace_orders
    WHERE buyer_user_id = ?
    GROUP BY checkout_ref
    ORDER BY MAX(created_at) DESC
    LIMIT 20
");
$orderStmt->execute([(int) $user['id']]);
$orderPayments = $orderStmt->fetchAll();

buyer_page_start('Buyer Wallet & Finance', 'wallet', $user, buyer_counts($pdo, $user));
?>
<?php if ($walletNotice): ?><div class="alert ok"><?= e($walletNotice) ?></div><?php endif; ?>
<?php if ($walletError): ?><div class="alert err"><?= e($walletError) ?></div><?php endif; ?>

<div class="page-head"><div><h1>Buyer Wallet & Finance</h1><p>Manage marketplace payments, refunds, receipts, wallet funding, and withdrawal requests from your buyer account.</p></div><a class="btn" href="../market/checkout.php"><i class="fas fa-cart-shopping"></i> Checkout</a></div>
<div class="kpis">
  <div class="kpi"><i class="fas fa-wallet"></i><span><b><?= e(buyer_money((float) $wallet['balance'])) ?></b><br>Available Balance</span></div>
  <div class="kpi"><i class="fas fa-lock"></i><span><b><?= e(buyer_money((float) ($wallet['hold_balance'] ?? 0))) ?></b><br>Held for Withdrawal</span></div>
  <div class="kpi"><i class="fas fa-receipt"></i><span><b><?= count($transactions) ?></b><br>Wallet Entries</span></div>
  <div class="kpi"><i class="fas fa-cart-shopping"></i><span><b><?= count($orderPayments) ?></b><br>Checkout Payments</span></div>
  <div class="kpi"><i class="fas fa-rotate-left"></i><span><b><?= count(array_filter($transactions, static fn(array $tx): bool => stripos((string) ($tx['description'] ?? ''), 'refund') !== false)) ?></b><br>Refund Entries</span></div>
</div>
<div class="grid">
  <section class="card span-7">
    <div class="card-head"><h2>Wallet Transactions</h2><span class="badge">Buyer finance</span></div>
    <div class="list">
      <?php foreach ($transactions as $tx): ?>
        <div class="row"><span><strong><?= e((string) ($tx['description'] ?: $tx['reference'])) ?></strong><br><small><?= e((string) $tx['created_at']) ?> / <?= e((string) $tx['reference']) ?></small></span><span><strong><?= e(buyer_money((float) $tx['amount'])) ?></strong><br><?= buyer_status_badge((string) $tx['status']) ?></span></div>
      <?php endforeach; ?>
      <?php if (!$transactions): ?><div class="alert ok">No wallet transactions yet.</div><?php endif; ?>
    </div>
  </section>
  <section class="card span-5">
    <div class="card-head"><h2>Fund Wallet</h2><span class="badge">Monnify</span></div>
    <form id="buyer-fund-wallet" class="form-grid" style="margin-bottom:18px">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="return_url" value="<?= e(app_base_url() . '/buyer/wallet.php') ?>">
      <label class="wide">Amount<input type="number" name="amount" min="100" step="50" value="5000" required></label>
      <div class="wide"><button class="btn" type="submit"><i class="fas fa-plus"></i> Initialize Funding</button></div>
    </form>
    <div id="buyer-wallet-result" class="alert ok" style="display:none"></div>
    <div class="card-head"><h2>Withdraw Funds</h2><span class="badge">Verify account</span></div>
    <form method="post" class="form-grid" style="margin-bottom:18px" data-withdrawal-form data-bank-url="../api/wallet-banks.php" data-resolve-url="../api/resolve-bank-account.php">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="bank_name" data-bank-name>
      <input type="hidden" name="bank_code" data-bank-code>
      <label>Amount<input type="number" name="withdraw_amount" min="<?= e((string) wallet_withdrawal_min_amount()) ?>" step="50" required></label>
      <label>Payout Route<select name="withdraw_provider" data-provider><option value="monnify">Monnify transfer</option><option value="paystack">Paystack transfer</option></select></label>
      <label class="wide">Receiving Bank<select data-bank-select required><option value="">Loading banks...</option></select></label>
      <label>Account Number<input name="account_number" data-account-number inputmode="numeric" maxlength="10" placeholder="10-digit NUBAN" required></label>
      <label>Verified Account Name<input name="account_name" data-account-name readonly required></label>
      <div class="wide alert ok" data-resolve-status>Select a bank and enter account number to verify account name.</div>
      <label class="wide">Note<textarea name="withdraw_note"></textarea></label>
      <div class="wide"><button class="btn" type="submit" name="request_withdrawal" data-submit-withdrawal disabled><i class="fas fa-money-bill-transfer"></i> Submit Verified Withdrawal</button></div>
    </form>
    <div class="card-head"><h2>Withdrawal Requests</h2><span class="badge"><?= count($withdrawals) ?></span></div>
    <div class="list">
      <?php foreach ($withdrawals as $wd): ?>
        <div class="row"><span><strong><?= e((string) $wd['reference']) ?></strong><br><small><?= e((string) $wd['provider']) ?> / <?= e((string) $wd['requested_at']) ?></small></span><span><strong><?= e(buyer_money((float) $wd['final_amount'])) ?></strong><br><?= buyer_status_badge((string) $wd['status']) ?></span></div>
      <?php endforeach; ?>
      <?php if (!$withdrawals): ?><div class="alert ok">No withdrawal requests yet.</div><?php endif; ?>
    </div>
    <div class="card-head"><h2>Marketplace Payments</h2><a class="view" href="orders.php">Orders</a></div>
    <div class="list">
      <?php foreach ($orderPayments as $payment): ?>
        <a class="row" href="orders.php?checkout_ref=<?= urlencode((string) $payment['checkout_ref']) ?>"><span><strong><?= e((string) $payment['checkout_ref']) ?></strong><br><small><?= e((string) $payment['created_at']) ?></small></span><span><strong><?= e(buyer_money((float) $payment['total_amount'])) ?></strong><br><?= buyer_status_badge((string) $payment['payment_status']) ?></span></a>
      <?php endforeach; ?>
      <?php if (!$orderPayments): ?><div class="alert ok">No marketplace payments yet.</div><?php endif; ?>
    </div>
  </section>
</div>
<script>
document.getElementById('buyer-fund-wallet')?.addEventListener('submit', async function(event) {
  event.preventDefault();
  const result = document.getElementById('buyer-wallet-result');
  result.style.display = 'block';
  result.className = 'alert ok';
  result.textContent = 'Initializing wallet funding...';
  try {
    const response = await fetch('../api/fund-wallet.php', { method: 'POST', body: new FormData(this), credentials: 'same-origin' });
    const data = await response.json();
    if (!data.success) throw new Error(data.error || 'Unable to initialize payment.');
    const url = data.checkout_url || data.payment_url || data.authorization_url || '';
    if (url) {
      result.innerHTML = '<strong>Funding initialized.</strong> <a class="view" href="' + url.replace(/"/g, '&quot;') + '">Continue payment</a>';
      window.location.href = url;
    } else {
      result.textContent = 'Funding initialized. Follow the payment instruction returned by Monnify.';
    }
  } catch (error) {
    result.className = 'alert err';
    result.textContent = error.message || 'Unable to initialize funding.';
  }
});
document.querySelectorAll('[data-withdrawal-form]').forEach(form => {
  const provider = form.querySelector('[data-provider]');
  const bankSelect = form.querySelector('[data-bank-select]');
  const bankName = form.querySelector('[data-bank-name]');
  const bankCode = form.querySelector('[data-bank-code]');
  const accountNumber = form.querySelector('[data-account-number]');
  const accountName = form.querySelector('[data-account-name]');
  const status = form.querySelector('[data-resolve-status]');
  const submit = form.querySelector('[data-submit-withdrawal]');
  let timer = null;
  function setStatus(message, ok = true) { status.className = 'wide alert ' + (ok ? 'ok' : 'err'); status.textContent = message; }
  function resetResolved() { accountName.value = ''; submit.disabled = true; setStatus('Select a bank and enter account number to verify account name.'); }
  async function loadBanks() {
    resetResolved();
    bankSelect.innerHTML = '<option value="">Loading banks...</option>';
    try {
      const response = await fetch(form.dataset.bankUrl + '?provider=' + encodeURIComponent(provider.value), { credentials: 'same-origin' });
      const payload = await response.json();
      if (!response.ok || !payload.success) throw new Error(payload.error || 'Unable to load banks.');
      bankSelect.innerHTML = '<option value="">Select receiving bank</option>' + payload.banks.map(bank => '<option value="' + String(bank.code).replace(/"/g, '&quot;') + '" data-name="' + String(bank.name).replace(/"/g, '&quot;') + '">' + bank.name + '</option>').join('');
    } catch (error) {
      bankSelect.innerHTML = '<option value="">Bank lookup unavailable</option>';
      setStatus(error.message || 'Bank lookup unavailable.', false);
    }
  }
  async function resolveAccount() {
    const option = bankSelect.options[bankSelect.selectedIndex];
    bankCode.value = bankSelect.value || '';
    bankName.value = option ? (option.dataset.name || '') : '';
    accountName.value = '';
    submit.disabled = true;
    const digits = accountNumber.value.replace(/\D/g, '').slice(0, 10);
    accountNumber.value = digits;
    if (!bankCode.value || digits.length !== 10) return resetResolved();
    setStatus('Verifying account name...');
    const data = new FormData();
    data.append('_csrf', form.querySelector('[name="_csrf"]').value);
    data.append('provider', provider.value);
    data.append('bank_code', bankCode.value);
    data.append('account_number', digits);
    try {
      const response = await fetch(form.dataset.resolveUrl, { method: 'POST', body: data, credentials: 'same-origin' });
      const payload = await response.json();
      if (!response.ok || !payload.success || !payload.account_name) throw new Error(payload.error || 'Account could not be verified.');
      accountName.value = payload.account_name;
      submit.disabled = false;
      setStatus('Verified: ' + payload.account_name);
    } catch (error) {
      setStatus(error.message || 'Account could not be verified.', false);
    }
  }
  provider.addEventListener('change', loadBanks);
  bankSelect.addEventListener('change', resolveAccount);
  accountNumber.addEventListener('input', () => { clearTimeout(timer); resetResolved(); timer = setTimeout(resolveAccount, 450); });
  form.addEventListener('submit', event => { if (submit.disabled || !accountName.value) { event.preventDefault(); setStatus('Verify the bank account before submitting withdrawal.', false); } });
  loadBanks();
});
</script>
<?php buyer_page_end(); ?>
