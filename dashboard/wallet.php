<?php
declare(strict_types=1);

require_once __DIR__ . '/_user_auth.php';
require_once __DIR__ . '/../lib/dashboard-layout.php';
require_once __DIR__ . '/../lib/monnify.php';

$pdo = db();
wallet_ensure_schema($pdo);

$user = current_user($pdo);
if (!$user) {
    redirect_to('login.php');
}
$userId = (int) $user['id'];
$wallet = wallet_get_or_create($pdo, $userId);
$walletNotice = '';
$walletError = '';
$monnifyConfigured = monnify_is_configured();
$monnifySetupMessage = $monnifyConfigured ? '' : monnify_configuration_error();
$callbackReference = (string) ($_GET['paymentReference'] ?? $_GET['reference'] ?? '');
if ($monnifyConfigured && $callbackReference !== '') {
    $verification = monnify_verify_wallet_funding($pdo, $userId, $callbackReference);
    if (($verification['status'] ?? '') === 'completed') {
        $walletNotice = 'Payment confirmed. Wallet credited.';
        $wallet = wallet_get_or_create($pdo, $userId);
    } elseif (($verification['success'] ?? false) && empty($verification['credited'])) {
        $walletNotice = 'Payment is still pending. Complete the transfer with the exact amount, then refresh this page.';
    } else {
        $walletError = (string) ($verification['error'] ?? 'Unable to verify payment yet.');
    }
}
if ($monnifyConfigured && isset($_POST['check_payment_status']) && verify_csrf($_POST['_csrf'] ?? null)) {
    $checkedReference = (string) ($_POST['reference'] ?? '');
    $verification = monnify_verify_wallet_funding($pdo, $userId, $checkedReference);
    if (($verification['status'] ?? '') === 'completed') {
        $walletNotice = 'Payment confirmed. Wallet credited for ' . $checkedReference . '.';
        $wallet = wallet_get_or_create($pdo, $userId);
    } elseif (($verification['success'] ?? false) && empty($verification['credited'])) {
        $walletNotice = 'Still pending at Monnify for ' . $checkedReference . '. Complete the payment, then check again.';
    } else {
        $walletError = (string) ($verification['error'] ?? 'Unable to verify payment yet.');
    }
}
if (isset($_POST['create_reserved_account']) && verify_csrf($_POST['_csrf'] ?? null)) {
    $reserved = monnify_ensure_reserved_account($pdo, $user);
    if ($reserved['success']) {
        $wallet = $reserved['wallet'];
        $walletNotice = $reserved['created'] ? 'Reserved bank account created.' : 'Reserved bank account already exists.';
    } else {
        $walletError = (string) ($reserved['error'] ?? 'Unable to create reserved account.');
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
        $walletNotice = 'Withdrawal request submitted. Reference: ' . (string) $withdrawal['reference'] . '. Admin approval is required before payout.';
        $wallet = wallet_get_or_create($pdo, $userId);
    } else {
        $walletError = (string) ($withdrawal['error'] ?? 'Unable to submit withdrawal request.');
    }
}

$txStmt = $pdo->prepare("SELECT * FROM wallet_transactions WHERE wallet_id = ? ORDER BY created_at DESC LIMIT 20");
$txStmt->execute([(int) $wallet['id']]);
$transactions = $txStmt->fetchAll();
$wdStmt = $pdo->prepare("SELECT * FROM wallet_withdrawals WHERE wallet_id = ? ORDER BY requested_at DESC LIMIT 10");
$wdStmt->execute([(int) $wallet['id']]);
$withdrawals = $wdStmt->fetchAll();
$prefillAmount = max(0, (float) ($_GET['amount'] ?? 0));

function wallet_tx_payload(array $tx): array
{
    $payload = json_decode((string) ($tx['provider_payload'] ?? ''), true);
    return is_array($payload) ? $payload : [];
}
?>
<?php dashboard_page_start('Wallet', [
    'active' => 'wallet.php',
    'description' => 'Fund, withdraw, track, reconcile, and audit your NATCODEV wallet through Monnify and Paystack.',
    'wide' => true,
    'css' => '
      .wallet-grid { display:grid; grid-template-columns:1.1fr .9fr; gap:18px; align-items:start; }
      .wallet-actions-grid { display:grid; grid-template-columns:1fr 1fr; gap:18px; align-items:start; margin-top:18px; }
      .balance { font-size:2.4rem; color:var(--green); font-weight:900; margin:10px 0; }
      .withdraw-card { border-top:4px solid var(--green); }
      .withdraw-flow { display:grid; gap:14px; }
      .flow-step { display:flex; gap:12px; align-items:flex-start; }
      .flow-num { width:28px; height:28px; border-radius:50%; background:#e8f6ec; color:var(--green); display:grid; place-items:center; font-weight:950; flex:0 0 auto; }
      .flow-step > div:last-child { flex:1; min-width:0; }
      .resolve-box { border:1px solid var(--line); border-radius:10px; padding:12px; background:#f8fbf8; color:#475467; font-weight:750; }
      .resolve-box.ok { border-color:#b9e3c3; color:#06451f; background:#eef8ef; }
      .resolve-box.err { border-color:#fecdd3; color:#b42318; background:#fff1f2; }
      .bank-box { border:1px solid var(--line); border-radius:8px; padding:14px; background:#f8fbf8; }
      .reserved-form { margin:0; }
      .reserved-action { width:100%; text-align:left; border:1px dashed var(--line); border-radius:8px; padding:16px; background:#f8fbf8; cursor:pointer; transition:border-color .15s ease, background .15s ease; }
      .reserved-action:hover { border-color:var(--green); background:#f1faf5; }
      .reserved-action p { margin:0 0 12px; }
      .copy-account { margin-top:12px; }
      .tx-actions { display:flex; gap:8px; flex-wrap:wrap; }
      .tx-actions .button { padding:8px 10px; font-size:.86rem; box-shadow:none; }
      .tx-actions form { margin:0; }
      .fund-form { display:flex; gap:10px; flex-wrap:wrap; align-items:end; }
      .fund-form input { min-width:220px; }
      @media(max-width:820px){ .wallet-grid,.wallet-actions-grid { grid-template-columns:1fr; } .fund-form input, .fund-form button { width:100%; } }
    ',
]); ?>
<?php if ($walletNotice): ?><div class="notice ok"><?= e($walletNotice) ?></div><?php endif; ?>
<?php if ($walletError): ?><div class="notice error"><?= e($walletError) ?></div><?php endif; ?>
<?php if ($monnifySetupMessage): ?><div class="notice error"><?= e($monnifySetupMessage) ?></div><?php endif; ?>
<div class="wallet-grid">
<section class="card">
      <h1>Wallet</h1>
      <div class="balance">NGN <?= e(number_format((float) $wallet['balance'], 2)) ?></div>
      <p class="muted">Available balance. Held withdrawals awaiting admin review: NGN <?= e(number_format((float) ($wallet['hold_balance'] ?? 0), 2)) ?>.</p>
      <p class="muted">Funding is powered by Monnify checkout and bank transfer reconciliation. Withdrawals can be processed by Monnify, Paystack, or manual admin payout.</p>
    </section>
    <section class="card withdraw-card">
      <h2>Withdraw Earnings</h2>
      <p class="muted">Declare your withdrawal intention, verify the receiving bank account, then submit for admin payout approval.</p>
      <form method="post" class="withdraw-flow" data-withdrawal-form data-bank-url="../api/wallet-banks.php" data-resolve-url="../api/resolve-bank-account.php">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="bank_name" data-bank-name>
        <input type="hidden" name="bank_code" data-bank-code>
        <div class="flow-step"><span class="flow-num">1</span><div class="form-grid">
          <label>Amount
            <input type="number" name="withdraw_amount" min="<?= e((string) wallet_withdrawal_min_amount()) ?>" step="50" placeholder="10000" required>
          </label>
          <label>Payout Route
            <select name="withdraw_provider" data-provider>
              <option value="monnify">Monnify transfer</option>
              <option value="paystack">Paystack transfer</option>
            </select>
          </label>
        </div></div>
        <div class="flow-step"><span class="flow-num">2</span><div class="form-grid">
          <label class="wide">Receiving Bank
            <select data-bank-select required><option value="">Loading banks...</option></select>
          </label>
          <label>Account Number<input name="account_number" data-account-number inputmode="numeric" maxlength="10" placeholder="10-digit NUBAN" required></label>
          <label>Verified Account Name<input name="account_name" data-account-name readonly required placeholder="Account name appears after verification"></label>
        </div></div>
        <div class="flow-step"><span class="flow-num">3</span><div>
          <div class="resolve-box" data-resolve-status>Select a bank and enter a 10-digit account number to verify the account name.</div>
          <label style="margin-top:12px">Payout Note<textarea name="withdraw_note" placeholder="Optional payout note"></textarea></label>
          <button type="submit" name="request_withdrawal" data-submit-withdrawal disabled>Submit Verified Withdrawal</button>
        </div></div>
      </form>
      <p class="muted">A verified request places the amount on hold until an admin approves or rejects it.</p>
    </section>
</div>
<div class="wallet-actions-grid">
    <section class="card">
      <h2>Fund Wallet</h2>
      <form class="fund-form" id="fundWalletForm">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="return_url" value="<?= e(app_base_url() . '/dashboard/wallet.php') ?>">
        <label>Amount
          <input type="number" name="amount" min="100" step="50" placeholder="5000" value="<?= $prefillAmount > 0 ? e((string) $prefillAmount) : '' ?>" required>
        </label>
        <button type="submit" <?= $monnifyConfigured ? '' : 'disabled' ?>>Fund With Monnify</button>
      </form>
      <p id="fundWalletStatus" class="muted"></p>
    </section>
    <section class="card">
      <h2>Reserved Transfer Account</h2>
      <?php if (!empty($wallet['reserved_account_number'])): ?>
        <div class="bank-box">
          <strong><?= e((string) $wallet['reserved_account_bank_name']) ?></strong><br>
          <span class="balance" style="font-size:1.7rem;" id="reservedAccountNumber"><?= e((string) $wallet['reserved_account_number']) ?></span><br>
          <span><?= e((string) $wallet['reserved_account_name']) ?></span>
          <br><button type="button" class="copy-account" data-copy-account>Copy Account Number</button>
        </div>
        <p class="muted">Transfers to this account are credited by Monnify webhook after payment confirmation.</p>
      <?php else: ?>
        <form method="post" class="reserved-form" id="reservedAccountForm">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <button type="submit" name="create_reserved_account" class="reserved-action" <?= $monnifyConfigured ? '' : 'disabled' ?>>
            <span>Create a dedicated Monnify account number for direct bank-transfer funding.</span>
            <strong>Create Reserved Account</strong>
          </button>
        </form>
      <?php endif; ?>
    </section>
</div>
    <section class="card">
      <h2>Withdrawal Requests</h2>
      <table>
        <tr><th>Date</th><th>Reference</th><th>Route</th><th>Amount</th><th>Net</th><th>Status</th></tr>
        <?php foreach ($withdrawals as $wd): ?>
          <tr>
            <td><?= e(date('M j, Y', strtotime((string) $wd['requested_at']))) ?></td>
            <td><?= e((string) $wd['reference']) ?></td>
            <td><?= e(ucfirst((string) $wd['provider'])) ?></td>
            <td>NGN <?= e(number_format((float) $wd['amount'], 2)) ?></td>
            <td>NGN <?= e(number_format((float) $wd['final_amount'], 2)) ?></td>
            <td><?= e((string) $wd['status']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$withdrawals): ?><tr><td colspan="6">No withdrawal requests yet.</td></tr><?php endif; ?>
      </table>
    </section>
    <section class="card">
      <h2>Recent Transactions</h2>
      <table>
        <tr><th>Date</th><th>Description</th><th>Reference</th><th>Type</th><th>Amount</th><th>Status</th><th>Action</th></tr>
        <?php foreach ($transactions as $tx): ?>
          <?php
            $txPayload = wallet_tx_payload($tx);
            $checkoutUrl = (string) ($txPayload['checkoutUrl'] ?? '');
            $reference = (string) $tx['reference'];
            $isPendingMonnify = (string) ($tx['provider'] ?? '') === 'monnify' && (string) ($tx['status'] ?? '') === 'pending';
          ?>
          <tr>
            <td><?= e(date('M j, Y', strtotime((string) $tx['created_at']))) ?></td>
            <td><?= e($tx['description']) ?></td>
            <td><?= e((string) $tx['reference']) ?></td>
            <td><?= e($tx['type']) ?></td>
            <td>NGN <?= e(number_format((float) $tx['amount'], 2)) ?></td>
            <td><?= e($tx['status']) ?></td>
            <td>
              <div class="tx-actions">
                <?php if ($isPendingMonnify && $checkoutUrl !== ''): ?>
                  <a class="button" href="<?= e($checkoutUrl) ?>">Continue</a>
                <?php endif; ?>
                <?php if ($isPendingMonnify): ?>
                  <form method="post">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="reference" value="<?= e($reference) ?>">
                    <button type="submit" name="check_payment_status" class="button secondary">Check Status</button>
                  </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$transactions): ?><tr><td colspan="7">No transactions yet.</td></tr><?php endif; ?>
      </table>
    </section>
    <script>
      document.getElementById('fundWalletForm')?.addEventListener('submit', async event => {
        event.preventDefault();
        const status = document.getElementById('fundWalletStatus');
        status.textContent = 'Initializing Monnify checkout...';
        const form = new FormData(event.currentTarget);
        try {
          const res = await fetch('../api/fund-wallet.php', { method: 'POST', body: form });
          const payload = await res.json();
          if (!res.ok || !payload.success) throw new Error(payload.error || 'Unable to initialize payment.');
          status.textContent = 'Redirecting to payment...';
          if (payload.payment_url) window.location.href = payload.payment_url;
        } catch (err) {
          status.textContent = err.message;
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
        let resolveTimer = null;

        function setStatus(message, type = '') {
          status.textContent = message;
          status.className = 'resolve-box' + (type ? ' ' + type : '');
        }
        function resetResolved() {
          accountName.value = '';
          submit.disabled = true;
          setStatus('Select a bank and enter a 10-digit account number to verify the account name.');
        }
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
            setStatus(error.message || 'Bank lookup unavailable.', 'err');
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
          if (!bankCode.value || digits.length !== 10) {
            setStatus('Select a bank and enter a 10-digit account number to verify the account name.');
            return;
          }
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
            setStatus('Verified: ' + payload.account_name, 'ok');
          } catch (error) {
            setStatus(error.message || 'Account could not be verified.', 'err');
          }
        }
        provider.addEventListener('change', loadBanks);
        bankSelect.addEventListener('change', resolveAccount);
        accountNumber.addEventListener('input', () => {
          clearTimeout(resolveTimer);
          resetResolved();
          resolveTimer = setTimeout(resolveAccount, 450);
        });
        form.addEventListener('submit', event => {
          if (submit.disabled || !accountName.value) {
            event.preventDefault();
            setStatus('Verify the bank account before submitting withdrawal.', 'err');
          }
        });
        loadBanks();
      });
      document.getElementById('reservedAccountForm')?.addEventListener('submit', event => {
        const button = event.currentTarget.querySelector('button[type="submit"]');
        if (button) {
          button.disabled = true;
          button.querySelector('strong').textContent = 'Creating...';
        }
      });
      document.querySelector('[data-copy-account]')?.addEventListener('click', async event => {
        const accountNumber = document.getElementById('reservedAccountNumber')?.textContent?.trim() || '';
        if (!accountNumber) return;
        await navigator.clipboard.writeText(accountNumber);
        event.currentTarget.textContent = 'Copied';
        setTimeout(() => { event.currentTarget.textContent = 'Copy Account Number'; }, 1600);
      });
    </script>
  <?php dashboard_page_end(); ?>
