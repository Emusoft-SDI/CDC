<?php
declare(strict_types=1);

require_once __DIR__ . '/_provider.php';
require_once __DIR__ . '/../lib/monnify.php';

$pdo = provider_boot();
wallet_ensure_schema($pdo);
$user = provider_full_user($pdo, provider_require($pdo));
$userId = (int) $user['id'];
$provider = provider_active($pdo, $user);
$counts = provider_counts($pdo, $provider, $user);
$wallet = wallet_get_or_create($pdo, $userId);

$notice = '';
$error = '';
$monnifyConfigured = monnify_is_configured();
$callbackReference = (string) ($_GET['paymentReference'] ?? $_GET['reference'] ?? '');
if ($monnifyConfigured && $callbackReference !== '') {
    $verification = monnify_verify_wallet_funding($pdo, $userId, $callbackReference);
    if (($verification['status'] ?? '') === 'completed') {
        $notice = 'Payment confirmed. Provider wallet credited.';
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
$sellerRows = [];
if (app_table_exists($pdo, 'marketplace_sellers') && app_table_exists($pdo, 'marketplace_orders')) {
    $stmt = $pdo->prepare("SELECT o.*, l.title listing_title, s.store_name FROM marketplace_orders o JOIN marketplace_sellers s ON s.id=o.seller_id LEFT JOIN marketplace_listings l ON l.id=o.listing_id WHERE s.user_id=? ORDER BY o.created_at DESC LIMIT 40");
    $stmt->execute([$userId]);
    $sellerRows = $stmt->fetchAll();
}
$academyRows = [];
if (app_table_exists($pdo, 'academy_instructors') && app_table_exists($pdo, 'academy_cohorts') && app_table_exists($pdo, 'webinar_registrations')) {
    $stmt = $pdo->prepare("SELECT c.title cohort_title, w.title course_title, COUNT(r.id) learners, COALESCE(SUM(w.price),0) gross_amount FROM academy_instructors i JOIN academy_cohorts c ON c.instructor_id=i.id JOIN webinars w ON w.id=c.webinar_id LEFT JOIN webinar_registrations r ON r.webinar_id=w.id WHERE i.email=? GROUP BY c.id ORDER BY c.start_at DESC LIMIT 20");
    $stmt->execute([(string) ($user['email'] ?? '')]);
    $academyRows = $stmt->fetchAll();
}
$settledSeller = array_sum(array_map(static fn(array $row): float => empty($row['settled_at']) ? 0.0 : (float) $row['total_amount'], $sellerRows));
$pendingSeller = array_sum(array_map(static fn(array $row): float => empty($row['settled_at']) && (string) ($row['payment_status'] ?? '') === 'paid' ? (float) $row['total_amount'] : 0.0, $sellerRows));
$refundCount = count(array_filter($transactions, static fn(array $tx): bool => stripos((string) ($tx['description'] ?? ''), 'refund') !== false));

provider_page_start('Provider Wallet & Payouts', 'wallet', $user, $provider, $counts);
?>
<?php if ($notice): ?><div class="notice ok"><?= e($notice) ?></div><?php endif; ?>
<?php if ($error): ?><div class="notice err"><?= e($error) ?></div><?php endif; ?>
<?php if (!$monnifyConfigured): ?><div class="notice err"><?= e(monnify_configuration_error()) ?></div><?php endif; ?>
<div class="page-head"><div><h1>Provider Wallet & Payouts</h1><p>Fund your wallet, receive marketplace/provider/Academy credits, and request Monnify or Paystack withdrawal payouts.</p></div><a class="btn light" href="support.php"><i class="fas fa-headset"></i> Payment Support</a></div>
<div class="kpis">
  <div class="kpi"><i class="fas fa-wallet"></i><span><b><?= e(marketplace_money((float) $wallet['balance'])) ?></b><br>Available</span></div>
  <div class="kpi"><i class="fas fa-lock"></i><span><b><?= e(marketplace_money((float) ($wallet['hold_balance'] ?? 0))) ?></b><br>Held Withdrawals</span></div>
  <div class="kpi"><i class="fas fa-store"></i><span><b><?= e(marketplace_money($pendingSeller)) ?></b><br>Pending Seller Settlement</span></div>
  <div class="kpi"><i class="fas fa-circle-check"></i><span><b><?= e(marketplace_money($settledSeller)) ?></b><br>Settled Sales</span></div>
  <div class="kpi"><i class="fas fa-graduation-cap"></i><span><b><?= count($academyRows) ?></b><br>Instructor Cohorts</span></div>
  <div class="kpi"><i class="fas fa-rotate-left"></i><span><b><?= (int) $refundCount ?></b><br>Refund Credits</span></div>
</div>
<div class="grid">
  <section class="card span-7"><div class="card-head"><h2>Wallet Ledger</h2><span class="badge">Deposits, earnings, refunds</span></div><div class="list"><?php foreach ($transactions as $tx): ?><div class="row"><span><strong><?= e((string) ($tx['description'] ?: $tx['reference'])) ?></strong><br><small><?= e((string) $tx['created_at']) ?> / <?= e((string) $tx['reference']) ?></small></span><span><strong><?= e(marketplace_money((float) $tx['amount'])) ?></strong><br><span class="badge"><?= e((string) $tx['status']) ?></span></span></div><?php endforeach; ?><?php if (!$transactions): ?><div class="notice ok">No wallet transactions yet.</div><?php endif; ?></div></section>
  <section class="card span-5"><div class="card-head"><h2>Fund Wallet</h2><span class="badge">Monnify</span></div><form id="provider-fund-wallet" class="form-grid"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="return_url" value="<?= e(app_base_url() . '/provider/wallet.php') ?>"><label class="wide">Amount<input type="number" name="amount" min="100" step="50" value="5000" required></label><div class="wide"><button class="btn" type="submit" <?= $monnifyConfigured ? '' : 'disabled' ?>><i class="fas fa-plus"></i> Fund With Monnify</button></div></form><div id="provider-wallet-result" class="notice ok" style="display:none"></div>
    <div class="card-head" style="margin-top:16px"><h2>Reserved Transfer Account</h2></div><?php if (!empty($wallet['reserved_account_number'])): ?><p><strong><?= e((string) $wallet['reserved_account_bank_name']) ?></strong><br><span style="font-size:1.6rem;font-weight:950;color:#06451f"><?= e((string) $wallet['reserved_account_number']) ?></span><br><?= e((string) $wallet['reserved_account_name']) ?></p><?php else: ?><form method="post"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><button class="btn light" type="submit" name="create_reserved_account" <?= $monnifyConfigured ? '' : 'disabled' ?>>Create Reserved Account</button></form><?php endif; ?></section>
  <section class="card span-5"><div class="card-head"><h2>Withdraw Funds</h2><span class="badge">Monnify / Paystack</span></div><form method="post" class="form-grid" data-withdrawal-form data-bank-url="../api/wallet-banks.php" data-resolve-url="../api/resolve-bank-account.php"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="bank_name" data-bank-name><input type="hidden" name="bank_code" data-bank-code><label>Amount<input type="number" name="withdraw_amount" min="<?= e((string) wallet_withdrawal_min_amount()) ?>" step="50" required></label><label>Withdrawal Method<select name="withdraw_provider" data-provider><option value="monnify">Monnify withdrawal</option><option value="paystack">Paystack withdrawal</option></select></label><label class="wide">Receiving Bank<select data-bank-select required><option value="">Loading banks...</option></select></label><label>Account Number<input name="account_number" data-account-number inputmode="numeric" maxlength="10" required></label><label>Verified Name<input name="account_name" data-account-name readonly required></label><div class="wide notice ok" data-resolve-status>Verify bank account before withdrawal.</div><label class="wide">Note<textarea name="withdraw_note"></textarea></label><div class="wide"><button class="btn" type="submit" name="request_withdrawal" data-submit-withdrawal disabled>Submit Monnify/Paystack Withdrawal</button></div></form></section>
  <section class="card span-7"><div class="card-head"><h2>Withdrawal Requests</h2><span class="badge"><?= count($withdrawals) ?></span></div><div class="list"><?php foreach ($withdrawals as $wd): ?><div class="row"><span><strong><?= e((string) $wd['reference']) ?></strong><br><small><?= e((string) $wd['provider']) ?> / <?= e((string) $wd['requested_at']) ?></small></span><span><strong><?= e(marketplace_money((float) $wd['final_amount'])) ?></strong><br><span class="badge"><?= e((string) $wd['status']) ?></span></span></div><?php endforeach; ?><?php if (!$withdrawals): ?><div class="notice ok">No withdrawal requests yet.</div><?php endif; ?></div></section>
  <section class="card span-6"><div class="card-head"><h2>Seller Settlement Activity</h2><a class="view" href="../market/seller-payouts.php">Seller Payouts</a></div><div class="list"><?php foreach ($sellerRows as $row): ?><div class="row"><span><strong><?= e((string) $row['order_ref']) ?></strong><br><small><?= e((string) ($row['listing_title'] ?? $row['store_name'] ?? 'Marketplace order')) ?> / <?= empty($row['settled_at']) ? 'Pending settlement' : 'Settled ' . e((string) $row['settled_at']) ?></small></span><strong><?= e(marketplace_money((float) $row['total_amount'])) ?></strong></div><?php endforeach; ?><?php if (!$sellerRows): ?><div class="notice ok">No marketplace seller settlements yet.</div><?php endif; ?></div></section>
  <section class="card span-6"><div class="card-head"><h2>Instructor Revenue</h2><span class="badge">Academy</span></div><div class="list"><?php foreach ($academyRows as $row): ?><div class="row"><span><strong><?= e((string) ($row['cohort_title'] ?: $row['course_title'])) ?></strong><br><small><?= (int) $row['learners'] ?> learner(s). Admin settlement credits instructor wallet after approval.</small></span><strong><?= e(marketplace_money((float) $row['gross_amount'])) ?></strong></div><?php endforeach; ?><?php if (!$academyRows): ?><div class="notice ok">No instructor revenue records linked to this email yet.</div><?php endif; ?></div></section>
</div>
<script>
const walletResult = document.getElementById('provider-wallet-result');
document.getElementById('provider-fund-wallet')?.addEventListener('submit', async function(event){event.preventDefault(); walletResult.style.display='block'; walletResult.className='notice ok'; walletResult.textContent='Initializing Monnify funding...'; try{const response=await fetch('../api/fund-wallet.php',{method:'POST',body:new FormData(this),credentials:'same-origin'}); const data=await response.json(); if(!data.success) throw new Error(data.error||'Unable to initialize payment.'); const url=data.checkout_url||data.payment_url||data.authorization_url||''; if(url){window.location.href=url;} else {walletResult.textContent='Funding initialized. Follow the Monnify payment instruction.';}}catch(error){walletResult.className='notice err'; walletResult.textContent=error.message||'Unable to initialize funding.';}});
document.querySelectorAll('[data-withdrawal-form]').forEach(form=>{const provider=form.querySelector('[data-provider]'), bankSelect=form.querySelector('[data-bank-select]'), bankName=form.querySelector('[data-bank-name]'), bankCode=form.querySelector('[data-bank-code]'), accountNumber=form.querySelector('[data-account-number]'), accountName=form.querySelector('[data-account-name]'), status=form.querySelector('[data-resolve-status]'), submit=form.querySelector('[data-submit-withdrawal]'); let timer=null; function setStatus(message,ok=true){status.className='wide notice '+(ok?'ok':'err'); status.textContent=message;} function reset(){accountName.value=''; submit.disabled=true; setStatus('Verify bank account before withdrawal.');} async function loadBanks(){reset(); bankSelect.innerHTML='<option value="">Loading banks...</option>'; try{const response=await fetch(form.dataset.bankUrl+'?provider='+encodeURIComponent(provider.value),{credentials:'same-origin'}); const payload=await response.json(); if(!response.ok||!payload.success) throw new Error(payload.error||'Unable to load banks.'); bankSelect.innerHTML='<option value="">Select receiving bank</option>'+payload.banks.map(bank=>'<option value="'+String(bank.code).replace(/"/g,'&quot;')+'" data-name="'+String(bank.name).replace(/"/g,'&quot;')+'">'+bank.name+'</option>').join('');}catch(error){bankSelect.innerHTML='<option value="">Bank lookup unavailable</option>'; setStatus(error.message||'Bank lookup unavailable.',false);}} async function resolve(){const option=bankSelect.options[bankSelect.selectedIndex]; bankCode.value=bankSelect.value||''; bankName.value=option?(option.dataset.name||''):''; accountName.value=''; submit.disabled=true; const digits=accountNumber.value.replace(/\D/g,'').slice(0,10); accountNumber.value=digits; if(!bankCode.value||digits.length!==10) return reset(); setStatus('Verifying account name...'); const data=new FormData(); data.append('_csrf',form.querySelector('[name="_csrf"]').value); data.append('provider',provider.value); data.append('bank_code',bankCode.value); data.append('account_number',digits); try{const response=await fetch(form.dataset.resolveUrl,{method:'POST',body:data,credentials:'same-origin'}); const payload=await response.json(); if(!response.ok||!payload.success||!payload.account_name) throw new Error(payload.error||'Account could not be verified.'); accountName.value=payload.account_name; submit.disabled=false; setStatus('Verified: '+payload.account_name);}catch(error){setStatus(error.message||'Account could not be verified.',false);}} provider.addEventListener('change',loadBanks); bankSelect.addEventListener('change',resolve); accountNumber.addEventListener('input',()=>{clearTimeout(timer); reset(); timer=setTimeout(resolve,450);}); form.addEventListener('submit',event=>{if(submit.disabled||!accountName.value){event.preventDefault(); setStatus('Verify the bank account before submitting withdrawal.',false);}}); loadBanks();});
</script>
<?php provider_page_end(); ?>