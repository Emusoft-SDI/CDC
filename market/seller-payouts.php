<?php
declare(strict_types=1);

require_once __DIR__ . '/_seller.php';
require_once __DIR__ . '/../lib/monnify.php';

$pdo = market_boot();
wallet_ensure_schema($pdo);
$user = market_require_user($pdo);
seller_access_or_message($pdo, $user);
$ctx = seller_query_context($pdo, $user, false);
$userId = (int) $user['id'];
$wallet = wallet_get_or_create($pdo, $userId);
$notice = '';
$error = '';
$monnifyConfigured = monnify_is_configured();
$callbackReference = (string) ($_GET['paymentReference'] ?? $_GET['reference'] ?? '');
if ($monnifyConfigured && $callbackReference !== '') {
    $verification = monnify_verify_wallet_funding($pdo, $userId, $callbackReference);
    if (($verification['status'] ?? '') === 'completed') {
        $notice = 'Payment confirmed. Seller wallet credited.';
        $wallet = wallet_get_or_create($pdo, $userId);
    } elseif (($verification['success'] ?? false) && empty($verification['credited'])) {
        $notice = 'Payment is still pending. Complete the transfer, then refresh this page.';
    } else {
        $error = (string) ($verification['error'] ?? 'Unable to verify payment yet.');
    }
}
if (isset($_POST['create_reserved_account']) && verify_csrf($_POST['_csrf'] ?? null)) {
    $reserved = monnify_ensure_reserved_account($pdo, $user);
    if ($reserved['success']) {
        $wallet = $reserved['wallet'];
        $notice = $reserved['created'] ? 'Reserved bank account created.' : 'Reserved bank account already exists.';
    } else {
        $error = (string) ($reserved['error'] ?? 'Unable to create reserved account.');
    }
}
if (isset($_POST['request_withdrawal']) && verify_csrf($_POST['_csrf'] ?? null)) {
    $withdrawal = wallet_request_withdrawal($pdo, $user, [
        'amount' => $_POST['withdraw_amount'] ?? 0,
        'provider' => $_POST['withdraw_provider'] ?? 'monnify',
        'bank_code' => $_POST['bank_code'] ?? '',
        'bank_name' => $_POST['bank_name'] ?? '',
        'account_number' => $_POST['account_number'] ?? '',
        'account_name' => $_POST['account_name'] ?? '',
        'note' => $_POST['withdraw_note'] ?? '',
    ]);
    if ($withdrawal['success']) {
        $notice = 'Withdrawal request submitted. Reference: ' . (string) $withdrawal['reference'] . '. Admin approval will process payout through Monnify or Paystack.';
        $wallet = wallet_get_or_create($pdo, $userId);
    } else {
        $error = (string) ($withdrawal['error'] ?? 'Unable to submit withdrawal request.');
    }
}
$txStmt = $pdo->prepare("SELECT * FROM wallet_transactions WHERE wallet_id = ? ORDER BY created_at DESC LIMIT 80");
$txStmt->execute([(int) $wallet['id']]);
$transactions = $txStmt->fetchAll();
$wdStmt = $pdo->prepare("SELECT * FROM wallet_withdrawals WHERE wallet_id = ? ORDER BY requested_at DESC LIMIT 20");
$wdStmt->execute([(int) $wallet['id']]);
$withdrawals = $wdStmt->fetchAll();
$settled = array_sum(array_map(static fn(array $row): float => empty($row['settled_at']) ? 0.0 : (float) $row['total_amount'], $ctx['orders']));
$pending = array_sum(array_map(static fn(array $row): float => empty($row['settled_at']) && (string) ($row['payment_status'] ?? '') === 'paid' ? (float) $row['total_amount'] : 0.0, $ctx['orders']));
$refundCount = count(array_filter($transactions, static fn(array $tx): bool => stripos((string) ($tx['description'] ?? ''), 'refund') !== false));

seller_header('Payouts & Wallet', 'payouts', $user, $ctx['seller']);
?>
<?php if ($notice): ?><div class="alert ok"><?= e($notice) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert err"><?= e($error) ?></div><?php endif; ?>
<?php if (!$monnifyConfigured): ?><div class="alert err"><?= e(monnify_configuration_error()) ?></div><?php endif; ?>
<section class="sc-kpis">
  <div class="sc-card sc-kpi"><span class="sc-icon gold"><i data-lucide="wallet"></i></span><div><small>Available Wallet</small><b><?= e(marketplace_money((float) $wallet['balance'])) ?></b><span>Withdrawable credits</span></div></div>
  <div class="sc-card sc-kpi"><span class="sc-icon orange"><i data-lucide="lock"></i></span><div><small>Held Withdrawals</small><b><?= e(marketplace_money((float) ($wallet['hold_balance'] ?? 0))) ?></b><span>Awaiting admin payout</span></div></div>
  <div class="sc-card sc-kpi"><span class="sc-icon blue"><i data-lucide="timer"></i></span><div><small>Pending Settlement</small><b><?= e(marketplace_money($pending)) ?></b><span>Paid orders pending release</span></div></div>
  <div class="sc-card sc-kpi"><span class="sc-icon"><i data-lucide="circle-check"></i></span><div><small>Settled Sales</small><b><?= e(marketplace_money($settled)) ?></b><span>Credited seller revenue</span></div></div>
  <div class="sc-card sc-kpi"><span class="sc-icon purple"><i data-lucide="receipt"></i></span><div><small>Wallet Entries</small><b><?= count($transactions) ?></b><span>Deposits, payouts, refunds</span></div></div>
  <div class="sc-card sc-kpi"><span class="sc-icon red"><i data-lucide="rotate-ccw"></i></span><div><small>Refund Credits</small><b><?= (int) $refundCount ?></b><span>Buyer/seller adjustments</span></div></div>
</section>
<section class="sc-grid">
  <article class="sc-card sc-panel span-7"><div class="sc-panel-head"><h2>Wallet Ledger</h2><span class="badge good">Seller finance</span></div><div class="sc-list"><?php foreach ($transactions as $tx): ?><div class="sc-row"><span class="sc-icon"><i data-lucide="receipt"></i></span><div><strong><?= e((string) ($tx['description'] ?: $tx['reference'])) ?></strong><br><small class="muted"><?= e((string) $tx['created_at']) ?> / <?= e((string) $tx['reference']) ?></small></div><div><strong><?= e(marketplace_money((float) $tx['amount'])) ?></strong><br><span class="badge"><?= e((string) $tx['status']) ?></span></div></div><?php endforeach; ?><?php if (!$transactions): ?><div class="empty">No wallet transactions yet.</div><?php endif; ?></div></article>
  <article class="sc-card sc-panel span-5"><div class="sc-panel-head"><h2>Fund Wallet</h2><span class="badge">Monnify</span></div><form id="seller-fund-wallet" class="sc-form sc-form-grid"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="return_url" value="<?= e(app_base_url() . '/market/seller-payouts.php') ?>"><div class="wide"><label>Amount</label><input type="number" name="amount" min="100" step="50" value="5000" required></div><div class="wide"><button class="sc-btn" type="submit" <?= $monnifyConfigured ? '' : 'disabled' ?>>Fund With Monnify</button></div></form><div id="seller-wallet-result" class="alert ok" style="display:none"></div><div class="sc-panel-head" style="margin-top:14px"><h2>Reserved Transfer Account</h2></div><?php if (!empty($wallet['reserved_account_number'])): ?><p><strong><?= e((string) $wallet['reserved_account_bank_name']) ?></strong><br><span style="font-size:1.5rem;font-weight:950;color:var(--green)"><?= e((string) $wallet['reserved_account_number']) ?></span><br><?= e((string) $wallet['reserved_account_name']) ?></p><?php else: ?><form method="post"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><button class="sc-btn secondary" type="submit" name="create_reserved_account" <?= $monnifyConfigured ? '' : 'disabled' ?>>Create Reserved Account</button></form><?php endif; ?></article>
  <article class="sc-card sc-panel span-5"><div class="sc-panel-head"><h2>Withdraw Seller Funds</h2><span class="badge good">Monnify / Paystack</span></div><form method="post" class="sc-form sc-form-grid" data-withdrawal-form data-bank-url="../api/wallet-banks.php" data-resolve-url="../api/resolve-bank-account.php"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="bank_name" data-bank-name><input type="hidden" name="bank_code" data-bank-code><div><label>Amount</label><input type="number" name="withdraw_amount" min="<?= e((string) wallet_withdrawal_min_amount()) ?>" step="50" required></div><div><label>Withdrawal Method</label><select name="withdraw_provider" data-provider><option value="monnify">Monnify withdrawal</option><option value="paystack">Paystack withdrawal</option></select></div><div class="wide"><label>Receiving Bank</label><select data-bank-select required><option value="">Loading banks...</option></select></div><div><label>Account Number</label><input name="account_number" data-account-number inputmode="numeric" maxlength="10" required></div><div><label>Verified Name</label><input name="account_name" data-account-name readonly required></div><div class="wide alert ok" data-resolve-status>Verify bank account before withdrawal.</div><div class="wide"><label>Note</label><textarea name="withdraw_note"></textarea></div><div class="wide"><button class="sc-btn" type="submit" name="request_withdrawal" data-submit-withdrawal disabled>Submit Monnify/Paystack Withdrawal</button></div></form></article>
  <article class="sc-card sc-panel span-7"><div class="sc-panel-head"><h2>Withdrawal Requests</h2><span class="badge"><?= count($withdrawals) ?></span></div><div class="sc-list"><?php foreach ($withdrawals as $wd): ?><div class="sc-row"><span class="sc-icon"><i data-lucide="banknote-arrow-up"></i></span><div><strong><?= e((string) $wd['reference']) ?></strong><br><small class="muted"><?= e((string) $wd['provider']) ?> / <?= e((string) $wd['requested_at']) ?></small></div><div><strong><?= e(marketplace_money((float) $wd['final_amount'])) ?></strong><br><span class="badge"><?= e((string) $wd['status']) ?></span></div></div><?php endforeach; ?><?php if (!$withdrawals): ?><div class="empty">No withdrawal requests yet.</div><?php endif; ?></div></article>
  <article class="sc-card sc-panel span-12"><div class="sc-panel-head"><h2>Payout Activity</h2></div><table class="sc-table"><thead><tr><th>Order</th><th>Buyer</th><th>Amount</th><th>Payment</th><th>Settlement</th></tr></thead><tbody><?php foreach ($ctx['orders'] as $row): ?><tr><td><?= e((string) $row['order_ref']) ?></td><td><?= e((string) $row['buyer_name']) ?></td><td><?= e(marketplace_money((float) $row['total_amount'])) ?></td><td><?= e(marketplace_status_label((string) ($row['payment_status'] ?? 'unpaid'))) ?></td><td><?= empty($row['settled_at']) ? 'Pending' : e((string) $row['settled_at']) ?></td></tr><?php endforeach; ?><?php if (!$ctx['orders']): ?><tr><td colspan="5">No payout activity yet.</td></tr><?php endif; ?></tbody></table></article>
</section>
<script>
const sellerWalletResult=document.getElementById('seller-wallet-result');
document.getElementById('seller-fund-wallet')?.addEventListener('submit',async function(event){event.preventDefault(); sellerWalletResult.style.display='block'; sellerWalletResult.className='alert ok'; sellerWalletResult.textContent='Initializing Monnify funding...'; try{const response=await fetch('../api/fund-wallet.php',{method:'POST',body:new FormData(this),credentials:'same-origin'}); const data=await response.json(); if(!data.success) throw new Error(data.error||'Unable to initialize payment.'); const url=data.checkout_url||data.payment_url||data.authorization_url||''; if(url){window.location.href=url;} else {sellerWalletResult.textContent='Funding initialized. Follow the Monnify payment instruction.';}}catch(error){sellerWalletResult.className='alert err'; sellerWalletResult.textContent=error.message||'Unable to initialize funding.';}});
document.querySelectorAll('[data-withdrawal-form]').forEach(form=>{const provider=form.querySelector('[data-provider]'), bankSelect=form.querySelector('[data-bank-select]'), bankName=form.querySelector('[data-bank-name]'), bankCode=form.querySelector('[data-bank-code]'), accountNumber=form.querySelector('[data-account-number]'), accountName=form.querySelector('[data-account-name]'), status=form.querySelector('[data-resolve-status]'), submit=form.querySelector('[data-submit-withdrawal]'); let timer=null; function setStatus(message,ok=true){status.className='wide alert '+(ok?'ok':'err'); status.textContent=message;} function reset(){accountName.value=''; submit.disabled=true; setStatus('Verify bank account before withdrawal.');} async function loadBanks(){reset(); bankSelect.innerHTML='<option value="">Loading banks...</option>'; try{const response=await fetch(form.dataset.bankUrl+'?provider='+encodeURIComponent(provider.value),{credentials:'same-origin'}); const payload=await response.json(); if(!response.ok||!payload.success) throw new Error(payload.error||'Unable to load banks.'); bankSelect.innerHTML='<option value="">Select receiving bank</option>'+payload.banks.map(bank=>'<option value="'+String(bank.code).replace(/"/g,'&quot;')+'" data-name="'+String(bank.name).replace(/"/g,'&quot;')+'">'+bank.name+'</option>').join('');}catch(error){bankSelect.innerHTML='<option value="">Bank lookup unavailable</option>'; setStatus(error.message||'Bank lookup unavailable.',false);}} async function resolve(){const option=bankSelect.options[bankSelect.selectedIndex]; bankCode.value=bankSelect.value||''; bankName.value=option?(option.dataset.name||''):''; accountName.value=''; submit.disabled=true; const digits=accountNumber.value.replace(/\D/g,'').slice(0,10); accountNumber.value=digits; if(!bankCode.value||digits.length!==10) return reset(); setStatus('Verifying account name...'); const data=new FormData(); data.append('_csrf',form.querySelector('[name="_csrf"]').value); data.append('provider',provider.value); data.append('bank_code',bankCode.value); data.append('account_number',digits); try{const response=await fetch(form.dataset.resolveUrl,{method:'POST',body:data,credentials:'same-origin'}); const payload=await response.json(); if(!response.ok||!payload.success||!payload.account_name) throw new Error(payload.error||'Account could not be verified.'); accountName.value=payload.account_name; submit.disabled=false; setStatus('Verified: '+payload.account_name);}catch(error){setStatus(error.message||'Account could not be verified.',false);}} provider.addEventListener('change',loadBanks); bankSelect.addEventListener('change',resolve); accountNumber.addEventListener('input',()=>{clearTimeout(timer); reset(); timer=setTimeout(resolve,450);}); form.addEventListener('submit',event=>{if(submit.disabled||!accountName.value){event.preventDefault(); setStatus('Verify the bank account before submitting withdrawal.',false);}}); loadBanks();});
</script>
<?php seller_footer(); ?>