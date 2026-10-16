<?php
declare(strict_types=1);
require_once __DIR__ . '/../_auth.php';
require_once __DIR__ . '/../../lib/admin-layout.php';
require_once __DIR__ . '/../../lib/admin-operator-strip.php';
require_once __DIR__ . '/../../lib/monnify.php';
require_once __DIR__ . '/../../lib/paystack.php';
require_once __DIR__ . '/../../lib/gateway-manager.php';
require_once __DIR__ . '/../../lib/marketplace.php';
require_once __DIR__ . '/../../lib/academy.php';
require_once __DIR__ . '/../../lib/wallet-dual-control.php';
require_once __DIR__ . '/../../lib/marketplace-refunds.php';
$pdo=db(); admin_ensure_schema($pdo); wallet_ensure_schema($pdo); marketplace_ensure_schema($pdo); academy_ensure_schema($pdo); admin_require($pdo,'wallet');
$admin=current_user($pdo)?:[];
$page=(string)($_GET['page']??'overview');
if($page==='reports'){ $q=$_GET; unset($q['page']); header('Location: reports.php'.($q?'?'.http_build_query($q):'')); exit; }
$allowed=['overview','transactions','wallets','withdrawals','refunds','payouts','reconciliation','integrity','rules','gateways']; if(!in_array($page,$allowed,true)){$page='overview';}
$search=trim((string)($_GET['search']??'')); $status=trim((string)($_GET['status']??'')); $walletStatus=trim((string)($_GET['wallet_status']??''));
if(!in_array($walletStatus,['','active','inactive','missing','duplicates','with_balance','held'],true)){$walletStatus='';}
$perPage=max(10,min(200,(int)($_GET['per_page']??50))); $currentPage=max(1,(int)($_GET['p']??1)); $offset=($currentPage-1)*$perPage; $message=''; $error='';
function fin_scalar(PDO $pdo,string $sql,array $params=[]):float{try{$s=$pdo->prepare($sql);$s->execute($params);return(float)($s->fetchColumn()?:0);}catch(Throwable $e){return 0;}}
function fin_rows(PDO $pdo,string $sql,array $params=[]):array{try{$s=$pdo->prepare($sql);$s->execute($params);return$s->fetchAll(PDO::FETCH_ASSOC);}catch(Throwable $e){return[];}}
function fin_money(float $n):string{return 'NGN '.number_format($n,2);} function fin_role_label(string $r):string{return ucwords(str_replace(['_','-'],' ',preg_replace('/^(marketplace_|provider_)/','',$r)));}
function fin_page(int $total,int $size,int $page):string{$pages=max(1,(int)ceil($total/max(1,$size)));$base=$_GET;unset($base['p']);ob_start();?><div class="d-flex justify-content-between mt-3"><span class="text-secondary"><?=number_format($total)?> result(s)</span><div class="btn-group"><a class="btn btn-sm btn-outline-success" href="?<?=e(http_build_query($base+['p'=>max(1,$page-1)]))?>">Previous</a><span class="btn btn-sm btn-light">Page <?=$page?> / <?=$pages?></span><a class="btn btn-sm btn-outline-success" href="?<?=e(http_build_query($base+['p'=>min($pages,$page+1)]))?>">Next</a></div></div><?php return(string)ob_get_clean();}
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!verify_csrf($_POST['_csrf']??null)){$error='Invalid security token.';}
  elseif(($_POST['action']??'')==='fund_wallet'){if(!app_check_rate_limit('admin_wallet_credit',10,300)){$error='Too many credit attempts. Try again later.';}else{$fundUser=(int)($_POST['user_id']??0);$fundAmount=(float)($_POST['amount']??0);$fundNote=(string)($_POST['note']??'');if(wallet_dual_control_required($pdo,$fundAmount)){$requestId=wallet_queue_credit_approval($pdo,$fundUser,$fundAmount,(int)($admin['id']??0),$fundNote);$message='Credit of '.fin_money($fundAmount).' queued for Super Admin approval (request #'.$requestId.'). No funds moved yet.';}else{$r=wallet_admin_credit($pdo,$fundUser,$fundAmount,(int)($admin['id']??0),$fundNote);$r['success']?$message='Wallet funded. Reference: '.($r['reference']??''):$error=(string)($r['error']??'Funding failed.');}}}
 elseif(($_POST['action']??'')==='create_wallet'){if(!app_check_rate_limit('admin_wallet_create',10,300)){$error='Too many wallet creation attempts. Try again later.';}else{$uid=(int)($_POST['user_id']??0);if($uid>0){wallet_get_or_create($pdo,$uid);$message='Wallet profile is ready for this user.';}else{$error='Select a valid user.';}}}
  elseif(($_POST['action']??'')==='save_withdrawal_rules'){
    $isSuperAdmin = !empty($admin['is_super_admin']) || (function_exists('super_admin_is_authorized') && super_admin_is_authorized($pdo));
    if(!$isSuperAdmin){
        $error='Access denied: Only Super Administrators have permission to modify withdrawal automation rules and switches.';
        $page='rules';
    }elseif(!app_check_rate_limit('admin_withdrawal_rules',5,300)){
        $error='Too many rule updates. Try again later.';
    }else{
        $admin['is_super_admin'] = 1;
        wallet_save_withdrawal_policy($pdo,$_POST,$admin);
        $message='Withdrawal automation rules saved.';
        $page='rules';
    }
  }
  elseif(($_POST['action']??'')==='dispatch_buffered_withdrawal'){
    $bufId=(int)($_POST['withdrawal_id']??0);
    $res=wallet_admin_process_withdrawal($pdo,$bufId,(int)($admin['id']??0),'approve','Operator manual release from pre-flight inspection buffer.');
    if(!empty($res['success'])){
        $pdo->prepare("UPDATE wallet_withdrawals SET preflight_buffered = 0, auto_processed_at = NOW() WHERE id = ?")->execute([$bufId]);
        $message='Withdrawal released from pre-flight buffer and dispatched.';
    }else{
        $error='Dispatch failed: '.($res['error']??'Unknown error');
    }
    $page='withdrawals';
  }
  elseif(($_POST['action']??'')==='toggle_preflight_buffer'){
    $isSuperAdmin = !empty($admin['is_super_admin']) || (function_exists('super_admin_is_authorized') && super_admin_is_authorized($pdo));
    if(!$isSuperAdmin){
        $error='Access denied: Only Super Administrators have permission to toggle pre-flight buffer mode.';
    }elseif(!app_check_rate_limit('admin_preflight_toggle',10,60)){
        $error='Too many toggle requests. Please wait.';
    }else{
        $currentPolicy = wallet_withdrawal_policy($pdo);
        $newVal = empty($currentPolicy['preflight_buffer_enabled']) ? '1' : '0';
        $stmt = $pdo->prepare("INSERT INTO settings (key_name, value) VALUES ('wallet_auto_preflight_buffer_enabled', ?) ON DUPLICATE KEY UPDATE value = VALUES(value)");
        $stmt->execute([$newVal]);
        if (function_exists('admin_audit')) {
            admin_audit($pdo, 'preflight_buffer_toggled', 'Super Admin #' . (int)($admin['id']??0) . ' toggled pre-flight buffer mode to ' . ($newVal === '1' ? 'ON' : 'OFF'));
        }
        $message = 'Pre-flight buffer mode switched ' . ($newVal === '1' ? 'ON (critical moment hold active).' : 'OFF (immediate autonomous dispatch restored).');
    }
    $page='withdrawals';
  }
  elseif(($_POST['action']??'')==='review_refund'){if(!app_check_rate_limit('admin_academy_refund_review',10,300)){$error='Too many refund reviews. Try again later.';}else{$refundId=(int)($_POST['refund_id']??0);$refundStatus=in_array((string)($_POST['decision']??$_POST['status']??'pending'),['pending','under_review','approved','paid','rejected','closed'],true)?(string)($_POST['decision']??$_POST['status']??'pending'):'pending';$refundNotes=trim((string)($_POST['admin_notes']??''));$r=admin_process_academy_refund($pdo,$refundId,$refundStatus,$refundNotes,(int)($admin['id']??0));if(!empty($r['queued'])){$message='Refund queued for Super Admin money approval (request #'.$r['queued'].'). No funds moved yet.';}elseif(!empty($r['success'])){$message=$refundStatus==='rejected'?'Refund rejected.':'Refund review saved.';}else{$error=(string)($r['error']??'Refund review failed.');}}}
  elseif(($_POST['action']??'')==='refund_checkout'){
    if(!app_check_rate_limit('admin_marketplace_refund',10,300)){$error='Too many refund attempts. Try again later.';}
    else{
      $refCheckout=trim((string)($_POST['checkout_ref']??''));$refReason=trim((string)($_POST['reason']??''));
      if($refCheckout===''){$error='Enter the checkout reference to refund.';}
      elseif($refReason===''){$error='A refund reason is required before money can be moved.';}
      else{
        $amtStmt=$pdo->prepare("SELECT COALESCE(SUM(total_amount),0)+COALESCE(MAX(delivery_fee),0)+COALESCE(MAX(service_fee),0) refund_total FROM marketplace_orders WHERE checkout_ref=? AND payment_status='paid'");
        $amtStmt->execute([$refCheckout]);
        $refundTotal=(float)$amtStmt->fetchColumn();
        if($refundTotal<=0){$error='This checkout has no paid orders to refund.';}
        elseif(wallet_dual_control_required($pdo,$refundTotal)){$requestId=market_queue_refund_approval($pdo,$refCheckout,$refundTotal,(int)($admin['id']??0),$refReason);$message='Refund of '.fin_money($refundTotal).' queued for Super Admin approval (request #'.$requestId.'). No funds moved yet.';}
        else{$r=market_refund_checkout_once($pdo,$refCheckout,['reason'=>$refReason,'admin_id'=>(int)($admin['id']??0)]);if(!empty($r['success'])){$message='Refund completed: '.fin_money((float)$r['refund_amount']).' ('.($r['status']==='completed'?'buyer wallet credited':'provider refund pending at gateway').'). Refund ref: '.($r['refund_ref']??'');}else{$error=(string)($r['error']??'Refund failed.');}}
      }
    }
  }
  elseif(($_POST['action']??'')==='debit_wallet'){
    if(!app_check_rate_limit('admin_wallet_debit',10,300)){$error='Too many debit attempts. Try again later.';}
    else{
      $debitUser=(int)($_POST['user_id']??0);$debitAmount=(float)($_POST['amount']??0);$debitNote=trim((string)($_POST['note']??''));
      if($debitUser<=0){$error='Select a valid wallet owner.';}
      elseif($debitAmount<=0){$error='Enter a valid debit amount.';}
      elseif($debitNote===''){$error='A reversal reason is required before debiting a wallet.';}
      elseif(wallet_dual_control_required($pdo,$debitAmount)){$requestId=wallet_queue_debit_approval($pdo,$debitUser,$debitAmount,(int)($admin['id']??0),$debitNote);$message='Debit of '.fin_money($debitAmount).' queued for Super Admin approval (request #'.$requestId.'). No funds moved yet.';}
      else{$r=wallet_admin_debit($pdo,$debitUser,$debitAmount,(int)($admin['id']??0),$debitNote);$r['success']?$message='Wallet debited. Reference: '.($r['reference']??''):$error=(string)($r['error']??'Debit failed.');}
    }
  }
  elseif(($_POST['action']??'')==='test_gateway'){
    if(!app_check_rate_limit('gateway_test_connection',10,300)){$error='Too many connection tests. Try again later.';}
    else{
      $gatewayProvider=strtolower(trim((string)($_POST['provider']??'')));
      if(!in_array($gatewayProvider,['monnify','paystack','fincra','woven','korapay','allawee'],true)){$error='Select a gateway to test.';}
      else{
        $probe=gateway_test_connection($gatewayProvider);
        $latency=(int)($probe['latency_ms']??0);
        if(!empty($probe['success'])){$message=ucfirst($gatewayProvider).' connection OK — responded in '.$latency.'ms.';}
        else{$error=ucfirst($gatewayProvider).' connection failed: '.(string)($probe['error']??'No response').' ('.$latency.'ms).';}
      }
    }
  }
}
$roleCase="CASE WHEN COALESCE(u.is_super_admin,0)=1 OR u.role='admin' OR u.platform_role IN ('admin','super_admin','support_agent','field_agent','agronomist','agric_extensionist','extensionist','state_coordinator','national_coordinator') THEN 'internal_operator' WHEN ms.id IS NOT NULL THEN CONCAT('marketplace_',COALESCE(NULLIF(ms.seller_type,''),'seller')) WHEN pr.id IS NOT NULL THEN CONCAT('provider_',COALESCE(NULLIF(pr.provider_type,''),'general')) WHEN u.application_id IS NOT NULL OR u.role='grower' THEN 'grower' ELSE COALESCE(NULLIF(u.platform_role,''),NULLIF(u.role,''),'public_user') END";
$balance=fin_scalar($pdo,'SELECT COALESCE(SUM(balance),0) FROM wallets');
$inflow=fin_scalar($pdo,"SELECT COALESCE(SUM(amount),0) FROM wallet_transactions WHERE (type IN ('credit','funding') OR direction = 'inflow') AND status IN ('completed','successful','success','paid')");
$outflow=fin_scalar($pdo,"SELECT COALESCE(SUM(ABS(amount)),0) FROM wallet_transactions WHERE type IN ('debit','withdrawal') AND status IN ('completed','successful','success','paid') AND provider <> 'monnify_external'");
$pendingWithdrawals=(int)fin_scalar($pdo,"SELECT COUNT(*) FROM wallet_withdrawals WHERE status IN ('pending','processing')");
$pendingRefunds=(int)fin_scalar($pdo,"SELECT COUNT(*) FROM academy_refund_requests WHERE status IN ('pending','under_review','approved')");
$payoutDue=fin_scalar($pdo,"SELECT COALESCE(SUM(total_amount),0) FROM marketplace_orders WHERE payment_status IN ('paid','successful') AND settled_at IS NULL");
$withdrawalPolicy=wallet_withdrawal_policy($pdo); $autoPending=(int)fin_scalar($pdo,"SELECT COUNT(*) FROM wallet_withdrawals WHERE status='pending' AND automation_eligible=1"); $firstReviewDue=(int)fin_scalar($pdo,"SELECT COUNT(*) FROM wallet_withdrawals WHERE status='pending' AND review_required=1 AND review_due_at IS NOT NULL AND review_due_at <= DATE_ADD(NOW(), INTERVAL 24 HOUR)");
$where='1=1';$params=[];if($search!==''){$where.=' AND (wt.reference LIKE ? OR wt.description LIKE ? OR u.name LIKE ? OR u.email LIKE ?)';$t='%'.$search.'%';$params=[$t,$t,$t,$t];}if($status!==''){$where.=' AND wt.status=?';$params[]=$status;}
$transactionTotal=(int)fin_scalar($pdo,"SELECT COUNT(*) FROM wallet_transactions wt LEFT JOIN users u ON u.id=wt.user_id WHERE {$where}",$params);
$transactions=fin_rows($pdo,"SELECT wt.*,u.name user_name,u.email user_email,{$roleCase} stakeholder_role FROM wallet_transactions wt LEFT JOIN users u ON u.id=wt.user_id LEFT JOIN marketplace_sellers ms ON ms.user_id=u.id LEFT JOIN provider_registry pr ON pr.user_id=u.id WHERE {$where} ORDER BY wt.created_at DESC,wt.id DESC LIMIT {$perPage} OFFSET {$offset}",$params);
$daily=fin_rows($pdo,"SELECT DATE(created_at) day,SUM(CASE WHEN (type IN ('credit','funding') OR direction = 'inflow') AND status IN ('completed','successful','success','paid') THEN amount ELSE 0 END) inflow,SUM(CASE WHEN type IN ('debit','withdrawal') AND status IN ('completed','successful','success','paid') AND provider <> 'monnify_external' THEN ABS(amount) ELSE 0 END) outflow,COUNT(*) transactions FROM wallet_transactions GROUP BY DATE(created_at) ORDER BY day DESC LIMIT 90");
$users=fin_rows($pdo,'SELECT id,name,email FROM users ORDER BY name LIMIT 500');
?><?php
$walletSummary="SELECT user_id,MIN(id) wallet_id,COUNT(*) wallet_count,GROUP_CONCAT(id ORDER BY id) wallet_ids,COALESCE(SUM(balance),0) balance,COALESCE(SUM(hold_balance),0) hold_balance,MAX(status) wallet_status,MAX(reserved_account_bank_name) reserved_account_bank_name,MAX(reserved_account_number) reserved_account_number,MAX(reserved_provider) reserved_provider FROM wallets GROUP BY user_id";
$walletUserWhere="(?='' OR u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR w.reserved_account_number LIKE ? OR w.reserved_account_bank_name LIKE ? OR {$roleCase} LIKE ?)";
$walletUserParams=[$search,'%'.$search.'%','%'.$search.'%','%'.$search.'%','%'.$search.'%','%'.$search.'%','%'.$search.'%'];
if($walletStatus==='active'){$walletUserWhere.=" AND w.wallet_id IS NOT NULL AND COALESCE(NULLIF(w.wallet_status,''),'active')='active'";}elseif($walletStatus==='inactive'){$walletUserWhere.=" AND w.wallet_id IS NOT NULL AND COALESCE(NULLIF(w.wallet_status,''),'active') IN ('inactive','suspended','disabled','blocked','closed')";}elseif($walletStatus==='missing'){$walletUserWhere.=' AND w.wallet_id IS NULL';}elseif($walletStatus==='duplicates'){$walletUserWhere.=' AND COALESCE(w.wallet_count,0)>1';}elseif($walletStatus==='with_balance'){$walletUserWhere.=' AND COALESCE(w.balance,0)>0';}elseif($walletStatus==='held'){$walletUserWhere.=' AND COALESCE(w.hold_balance,0)>0';}
$walletUsers=fin_rows($pdo,"SELECT u.id,u.name,u.email,u.phone,u.account_status,w.wallet_id,w.wallet_count,w.wallet_ids,w.balance,w.hold_balance,w.wallet_status,w.reserved_account_bank_name,w.reserved_account_number,w.reserved_provider,{$roleCase} stakeholder_role,(SELECT COUNT(*) FROM wallet_transactions wt WHERE wt.user_id=u.id) tx_count,(SELECT COALESCE(SUM(CASE WHEN (wt.type IN ('credit','funding') OR wt.direction = 'inflow') THEN wt.amount ELSE 0 END),0) FROM wallet_transactions wt WHERE wt.user_id=u.id) inflow,(SELECT COALESCE(SUM(CASE WHEN wt.type IN ('debit','withdrawal') AND wt.provider <> 'monnify_external' THEN ABS(wt.amount) ELSE 0 END),0) FROM wallet_transactions wt WHERE wt.user_id=u.id) outflow,(SELECT COUNT(*) FROM wallet_withdrawals ww WHERE ww.user_id=u.id) withdrawal_count FROM users u LEFT JOIN ({$walletSummary}) w ON w.user_id=u.id LEFT JOIN marketplace_sellers ms ON ms.user_id=u.id LEFT JOIN provider_registry pr ON pr.user_id=u.id WHERE {$walletUserWhere} ORDER BY w.wallet_id IS NULL DESC,w.balance DESC,u.created_at DESC LIMIT {$perPage} OFFSET {$offset}",$walletUserParams);
$walletUserTotal=(int)fin_scalar($pdo,"SELECT COUNT(DISTINCT u.id) FROM users u LEFT JOIN ({$walletSummary}) w ON w.user_id=u.id LEFT JOIN marketplace_sellers ms ON ms.user_id=u.id LEFT JOIN provider_registry pr ON pr.user_id=u.id WHERE {$walletUserWhere}",$walletUserParams);
$walletTotal=(int)fin_scalar($pdo,'SELECT COUNT(*) FROM wallets'); $walletOwnerTotal=(int)fin_scalar($pdo,'SELECT COUNT(DISTINCT user_id) FROM wallets'); $walletMissingTotal=(int)fin_scalar($pdo,'SELECT COUNT(*) FROM users u LEFT JOIN wallets w ON w.user_id=u.id WHERE w.id IS NULL'); $duplicateWalletUsers=(int)fin_scalar($pdo,'SELECT COUNT(*) FROM (SELECT user_id FROM wallets GROUP BY user_id HAVING COUNT(*) > 1) d'); $duplicateWalletRows=max(0,$walletTotal-$walletOwnerTotal);
$ledgerNet=fin_scalar($pdo,"SELECT COALESCE(SUM(CASE WHEN (type IN ('credit','funding') OR direction = 'inflow') AND status IN ('completed','successful','success','paid') THEN amount WHEN type IN ('debit','withdrawal') AND status IN ('completed','successful','success','paid') AND provider <> 'monnify_external' THEN -ABS(amount) ELSE 0 END),0) FROM wallet_transactions"); $walletVariance=$balance-$ledgerNet;
$walletIntegrityRows=fin_rows($pdo,"SELECT u.id,u.name,u.email,COUNT(w.id) wallet_rows,GROUP_CONCAT(w.id ORDER BY w.id) wallet_ids,COALESCE(SUM(w.balance),0) wallet_balance,COALESCE(SUM(w.hold_balance),0) hold_balance,(SELECT COUNT(*) FROM wallet_transactions wt WHERE wt.user_id=u.id) tx_count,(SELECT COALESCE(SUM(CASE WHEN (wt.type IN ('credit','funding') OR wt.direction = 'inflow') AND wt.status IN ('completed','successful','success','paid') THEN wt.amount WHEN wt.type IN ('debit','withdrawal') AND wt.status IN ('completed','successful','success','paid') AND wt.provider <> 'monnify_external' THEN -ABS(wt.amount) ELSE 0 END),0) FROM wallet_transactions wt WHERE wt.user_id=u.id) ledger_net FROM wallets w JOIN users u ON u.id=w.user_id GROUP BY u.id,u.name,u.email HAVING COUNT(w.id)>1 ORDER BY wallet_rows DESC,u.name ASC LIMIT 100");
$withdrawalWhere='1=1';$withdrawalParams=[];if($search!==''){$withdrawalWhere.=' AND (ww.reference LIKE ? OR u.name LIKE ? OR u.email LIKE ? OR ww.bank_name LIKE ? OR ww.account_number LIKE ?)';$t='%'.$search.'%';$withdrawalParams=[$t,$t,$t,$t,$t];}
$withdrawals=fin_rows($pdo,"SELECT ww.*,u.name,u.email,{$roleCase} stakeholder_role FROM wallet_withdrawals ww LEFT JOIN users u ON u.id=ww.user_id LEFT JOIN marketplace_sellers ms ON ms.user_id=u.id LEFT JOIN provider_registry pr ON pr.user_id=u.id WHERE {$withdrawalWhere} ORDER BY FIELD(ww.status,'pending','processing','approved','paid','failed','rejected'),ww.review_due_at IS NULL,ww.review_due_at ASC,ww.requested_at DESC LIMIT {$perPage} OFFSET {$offset}",$withdrawalParams); $withdrawalTotal=(int)fin_scalar($pdo,"SELECT COUNT(*) FROM wallet_withdrawals ww LEFT JOIN users u ON u.id=ww.user_id WHERE {$withdrawalWhere}",$withdrawalParams);
$refunds=fin_rows($pdo,"SELECT rr.*,u.name,u.email,w.title course_title FROM academy_refund_requests rr LEFT JOIN users u ON u.id=rr.user_id LEFT JOIN webinars w ON w.id=rr.webinar_id ORDER BY FIELD(rr.status,'pending','under_review','approved','paid','rejected'),rr.requested_at DESC LIMIT {$perPage} OFFSET {$offset}"); $refundTotal=(int)fin_scalar($pdo,'SELECT COUNT(*) FROM academy_refund_requests');
$payouts=fin_rows($pdo,"SELECT o.*,s.store_name FROM marketplace_orders o LEFT JOIN marketplace_sellers s ON s.id=o.seller_id WHERE o.payment_status IN ('paid','successful') ORDER BY o.settled_at IS NULL DESC,o.created_at DESC LIMIT {$perPage} OFFSET {$offset}"); $payoutTotal=(int)fin_scalar($pdo,"SELECT COUNT(*) FROM marketplace_orders WHERE payment_status IN ('paid','successful')");
?>
<?php
$walletPageLabels=['overview'=>'Overview','transactions'=>'Transactions','wallets'=>'Wallet Users','withdrawals'=>'Withdrawals','refunds'=>'Academy Refunds','payouts'=>'Marketplace Payouts','reconciliation'=>'Reconciliation','integrity'=>'Wallet Integrity','rules'=>'Withdrawal Rules','gateways'=>'Payment Gateways'];
admin_page_start('Wallet & Finance Control Room', [
    'topbar_only' => true,
    'active' => 'wallet/',
    'breadcrumbs' => [['label' => 'Wallet & Payments'], ['label' => 'Wallet Workspace', 'href' => '?page=overview'], ['label' => $walletPageLabels[$page] ?? ucwords(str_replace('_', ' ', (string) $page))]],
    'head_pre' => '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"><link rel="stylesheet" href="../../assets/css/admin-workspaces.css"><script defer src="../../assets/js/nc-collapse.js"></script>',
    'css' => '.fin-shell{display:grid;grid-template-columns:275px 1fr;min-height:100vh;align-items:start}.fin-side{background:linear-gradient(180deg,var(--deep),#00301c);color:#fff;padding:24px 18px;position:sticky;top:0;height:100vh;overflow:auto}.fin-side a{color:#d8eee2;text-decoration:none;padding:11px 12px;border-radius:10px;font-weight:800;display:block}.fin-side a.active,.fin-side a:hover{background:var(--green);color:#fff;text-decoration:none}.fin-side h4{font-size:1.15rem;margin:0}.fin-main{padding:24px}.money{font-variant-numeric:tabular-nums}.fin-main .table td{vertical-align:middle}.fin-main .card{border:1px solid var(--line);border-radius:12px;box-shadow:var(--shadow)}.fin-main .card-header{background:#fff;font-weight:900;color:#06451f;border-bottom:1px solid var(--line)}.fin-main .btn-success{background:var(--green);border-color:var(--green)}.fin-main .btn-outline-success{color:var(--green-dark);border-color:#bcdfca}@media(max-width:1024px){.fin-shell{grid-template-columns:1fr}.fin-side{position:relative;height:auto}.fin-main{padding:16px}}',
]);
?><div class="fin-shell"><aside class="fin-side" aria-label="Wallet workspace navigation"><div style="margin-bottom:1rem"><img src="<?= e(app_admin_logo_url()) ?>" alt="NATCODEV" style="max-width:150px;max-height:45px;margin-bottom:10px;display:block"><h4>NATCODEV</h4></div><p class="small" style="color:#aee4c4">Wallet &amp; Finance Control Room</p><nav class="d-grid gap-1 mt-4"><?php $finIcons=['overview'=>'fa-table-columns','transactions'=>'fa-right-left','wallets'=>'fa-wallet','withdrawals'=>'fa-money-bill-transfer','refunds'=>'fa-rotate-left','payouts'=>'fa-hand-holding-dollar','reconciliation'=>'fa-scale-balanced','integrity'=>'fa-shield-halved','rules'=>'fa-sliders','gateways'=>'fa-plug']; ?><?php foreach(['overview'=>'Overview','transactions'=>'Transactions','wallets'=>'Wallet Users','withdrawals'=>'Withdrawals','refunds'=>'Academy Refunds','payouts'=>'Marketplace Payouts','reconciliation'=>'Reconciliation','integrity'=>'Wallet Integrity','rules'=>'Withdrawal Rules','gateways'=>'Payment Gateways'] as $k=>$v):?><a class="<?=$page===$k?'active':''?>" href="?page=<?=$k?>"><i class="fas <?=$finIcons[$k]??'fa-circle'?>"></i><span><?=e($v)?></span></a><?php endforeach?><a href="reports.php"><i class="fas fa-chart-line"></i><span>Reports &amp; Intelligence</span></a><hr class="border-light opacity-25"><a href="../support/?view=tickets"><i class="fas fa-headset"></i><span>Finance Support</span></a><a href="../index.php"><i class="fas fa-gauge-high"></i><span>Workspace Hub</span></a></nav></aside><div class="fin-main"><?= admin_workspace_operator_strip($pdo, ['asset_prefix' => '../../', 'profile_href' => '../profile.php', 'password_href' => '../profile.php#password', 'logout_action' => '../admin.php', 'title' => 'Finance workspace', 'placeholder' => 'Search wallets, ledgers, withdrawals...']) ?>
<div class="d-flex justify-content-between flex-wrap gap-3 mb-4"><div><span class="text-success fw-bold small text-uppercase">Financial control room</span><h1 class="h3 mb-1"><?=e(ucwords(str_replace('_',' ',$page)))?></h1><p class="text-secondary">Audited balances, collections, refunds, withdrawals, payouts and gateway readiness.</p></div><a class="btn btn-success" href="reports.php">Reports</a></div><?php if($message):?><div class="alert alert-success"><?=e($message)?></div><?php endif?><?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif?>
<?= admin_kpi_grid([
    ['Platform balance', fin_money($balance), 'Total wallet balance', 'fa-wallet', ''],
    ['Completed inflow', fin_money($inflow), 'Credits settled', 'fa-arrow-down', 'blue'],
    ['Completed outflow', fin_money($outflow), 'Debits settled', 'fa-arrow-up', 'orange'],
    ['Pending withdrawals', number_format($pendingWithdrawals), 'Awaiting review', 'fa-hourglass-half', $pendingWithdrawals > 0 ? 'orange' : ''],
    ['Auto eligible pending', number_format($autoPending), 'Automation ready', 'fa-robot', 'purple'],
    ['24h review SLA', number_format($firstReviewDue), 'Due within 24 hours', 'fa-stopwatch', $firstReviewDue > 0 ? 'red' : ''],
    ['Pending refunds', number_format($pendingRefunds), 'Academy refunds', 'fa-rotate-left', ''],
    ['Seller payouts due', fin_money($payoutDue), 'Unsettled orders', 'fa-money-bill-transfer', ''],
]) ?><?php if($page==='overview'):?><div class="row g-4"><div class="col-xl-7"><div class="card"><div class="card-header bg-white fw-bold">Recent Ledger Activity</div><div class="table-responsive"><table class="table mb-0"><tr><th>Reference</th><th>Stakeholder</th><th>Direction</th><th>Amount</th><th>Status</th></tr><?php foreach($transactions as $r):?><tr><td><?=e($r['reference'])?></td><td><?=e((string)($r['user_name']??'System'))?></td><td><?=e((string)($r['direction']??$r['type']))?></td><td class="money"><?=e(fin_money((float)$r['amount']))?></td><td><?=e($r['status'])?></td></tr><?php endforeach?></table></div><div class="card-body pt-0"><?=fin_page($transactionTotal,$perPage,$currentPage)?></div></div></div><div class="col-xl-5"><div class="card"><div class="card-body"><h2 class="h5">Controlled Manual Credit</h2><?php $dualControl=wallet_dual_control_settings($pdo);if($dualControl['enabled']):?><p class="text-secondary small">Dual control is on: credits of <?=e(fin_money($dualControl['limit']))?> or more queue for Super Admin approval instead of crediting immediately.</p><?php endif;?><form method="post" class="d-grid gap-2"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="fund_wallet"><select class="form-select" name="user_id" required><option value="">Select stakeholder</option><?php foreach($users as $u):?><option value="<?=(int)$u['id']?>"><?=e($u['name'].' / '.$u['email'])?></option><?php endforeach?></select><input class="form-control" type="number" name="amount" min=".01" step=".01" placeholder="Amount NGN" required><textarea class="form-control" name="note" placeholder="Mandatory operational reason" required></textarea><button class="btn btn-success">Credit Wallet</button></form></div></div><div class="card mt-3"><div class="card-body"><h2 class="h5">Controlled Debit (Reversal)</h2><p class="text-secondary small">Operator reversal: moves money out of a wallet with a paired ledger entry and audit trail. Dual control applies at the Super Admin threshold.</p><form method="post" class="d-grid gap-2"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="debit_wallet"><select class="form-select" name="user_id" required><option value="">Select stakeholder</option><?php foreach($users as $u):?><option value="<?=(int)$u['id']?>"><?=e($u['name'].' / '.$u['email'])?></option><?php endforeach?></select><input class="form-control" type="number" name="amount" min=".01" step=".01" placeholder="Amount NGN" required><textarea class="form-control" name="note" placeholder="Mandatory reversal reason" required></textarea><button class="btn btn-danger">Debit Wallet</button></form></div></div></div></div></div><?php endif?>
<?php if($page==='transactions'):?><form class="card card-body mb-3" method="get"><input type="hidden" name="page" value="transactions"><div class="row g-2"><div class="col-md-7"><input class="form-control" name="search" value="<?=e($search)?>" placeholder="Search reference, description, stakeholder, email"></div><div class="col-md-3"><select class="form-select" name="status"><option value="">All statuses</option><?php foreach(['pending','processing','completed','failed','rejected'] as $s):?><option value="<?=e($s)?>" <?=$status===$s?'selected':''?>><?=e($s)?></option><?php endforeach?></select></div><div class="col-md-2"><button class="btn btn-success w-100">Search</button></div></div></form><div class="card"><div class="table-responsive"><table class="table mb-0"><tr><th>Date</th><th>Reference</th><th>Stakeholder</th><th>Provider</th><th>Direction</th><th>Amount</th><th>Status</th></tr><?php foreach($transactions as $r):?><tr><td><?=e($r['created_at'])?></td><td><?=e($r['reference'])?></td><td><?=e((string)($r['user_name']??'System'))?></td><td><?=e((string)($r['provider']??'manual'))?></td><td><?=e((string)($r['direction']??$r['type']))?></td><td><?=e(fin_money((float)$r['amount']))?></td><td><?=e($r['status'])?></td></tr><?php endforeach?></table></div></div><?=fin_page($transactionTotal,$perPage,$currentPage)?><?php endif?>
<?php if($page==='wallets'):?><form class="card card-body mb-3" method="get"><input type="hidden" name="page" value="wallets"><div class="row g-2 align-items-end"><div class="col-md-5"><label class="form-label">Search wallet users</label><input class="form-control" name="search" value="<?=e($search)?>" placeholder="Name, email, phone, role, reserved account, bank"></div><div class="col-md-3"><label class="form-label">Wallet filter</label><select class="form-select" name="wallet_status"><option value="">All wallet users</option><?php foreach(['active'=>'Active wallets','inactive'=>'Inactive / blocked','missing'=>'Missing wallet','duplicates'=>'Duplicate wallet rows','with_balance'=>'With available balance','held'=>'With held funds'] as $k=>$label):?><option value="<?=e($k)?>" <?=$walletStatus===$k?'selected':''?>><?=e($label)?></option><?php endforeach?></select></div><div class="col-md-2"><button class="btn btn-success w-100">Filter</button></div><div class="col-md-2"><a class="btn btn-outline-success w-100" href="?page=wallets">Clear</a></div></div></form><div class="row g-3 mb-3"><div class="col-md-3"><div class="card h-100"><div class="card-body"><small class="text-secondary">Wallet user records</small><div class="h4 mb-0"><?=number_format($walletUserTotal)?></div></div></div></div><div class="col-md-3"><div class="card h-100"><div class="card-body"><small class="text-secondary">Users missing wallet</small><div class="h4 mb-0 text-warning"><?=number_format($walletMissingTotal)?></div></div></div></div><div class="col-md-3"><div class="card h-100"><div class="card-body"><small class="text-secondary">Wallets created</small><div class="h4 mb-0 text-success"><?=number_format($walletTotal)?></div></div></div></div><div class="col-md-3"><div class="card h-100"><div class="card-body"><small class="text-secondary">Duplicate users</small><div class="h4 mb-0 text-danger"><?=number_format($duplicateWalletUsers)?></div></div></div></div></div><div class="card"><div class="table-responsive"><table class="table mb-0"><tr><th>User</th><th>User Group</th><th>Wallet</th><th>Balance</th><th>Activity</th><th>Reserved Account</th><th>Actions</th></tr><?php foreach($walletUsers as $r):?><tr><td><strong><?=e((string)($r['name']?:'Unnamed user'))?></strong><br><small><?=e((string)$r['email'])?><?=!empty($r['phone'])?' / '.e((string)$r['phone']):''?></small><br><span class="badge text-bg-light"><?=e((string)($r['account_status']??'active'))?></span></td><td><?=e(fin_role_label((string)$r['stakeholder_role']))?></td><td><?php if(!empty($r['wallet_id'])):?><span class="badge text-bg-success">Wallet #<?=(int)$r['wallet_id']?></span><br><small><?=e((string)($r['wallet_status']?:'active'))?></small><?php else:?><span class="badge text-bg-warning">No wallet yet</span><?php endif?></td><td class="money"><strong><?=e(fin_money((float)($r['balance']??0)))?></strong><br><small>Hold <?=e(fin_money((float)($r['hold_balance']??0)))?></small></td><td><small><?=number_format((int)$r['tx_count'])?> tx / <?=number_format((int)$r['withdrawal_count'])?> withdrawal(s)</small><br><small>In <?=e(fin_money((float)$r['inflow']))?> / Out <?=e(fin_money((float)$r['outflow']))?></small></td><td><?=e(trim((string)($r['reserved_account_bank_name']??'').' '.(string)($r['reserved_account_number']??''))?:'-')?></td><td><?php if(empty($r['wallet_id'])):?><form method="post"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="create_wallet"><input type="hidden" name="user_id" value="<?=(int)$r['id']?>"><button class="btn btn-sm btn-success">Create Wallet</button></form><?php endif?><a class="btn btn-sm btn-outline-success" href="?page=transactions&search=<?=e(urlencode((string)$r['email']))?>">Ledger</a> <a class="btn btn-sm btn-outline-success" href="?page=withdrawals&search=<?=e(urlencode((string)$r['email']))?>">Withdrawals</a></td></tr><?php endforeach?></table></div></div><?=fin_page($walletUserTotal,$perPage,$currentPage)?><?php endif?>
<?php if($page==='withdrawals'):?>
<?php
$isSuperAdmin = !empty($admin['is_super_admin']) || (function_exists('super_admin_is_authorized') && super_admin_is_authorized($pdo));
$dailyCap = (float)($withdrawalPolicy['global_daily_cap'] ?? 0);
$globalDailyUsed = fin_scalar($pdo, "SELECT COALESCE(SUM(amount),0) FROM wallet_withdrawals WHERE (status IN ('processing', 'approved', 'paid') OR (status = 'pending' AND automation_eligible = 1)) AND requested_at >= CURDATE()");
$capPercent = $dailyCap > 0 ? min(100, round(($globalDailyUsed / $dailyCap) * 100, 1)) : 0;
?>
<div class="row g-3 mb-3">
  <div class="col-md-7">
    <div class="card h-100">
      <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
          <span class="badge text-bg-<?=!empty($withdrawalPolicy['preflight_buffer_enabled'])?'warning':'success'?> mb-1">
            <i class="fas <?=!empty($withdrawalPolicy['preflight_buffer_enabled'])?'fa-pause-circle':'fa-bolt'?> me-1"></i>
            <?=!empty($withdrawalPolicy['preflight_buffer_enabled'])?'Pre-Flight Hold Buffer: ACTIVE ('.(int)$withdrawalPolicy['preflight_buffer_minutes'].'m)':'Autonomous Mode: IMMEDIATE DISPATCH'?>
          </span>
          <div class="small text-secondary">
            <?=!empty($withdrawalPolicy['preflight_buffer_enabled'])?'Critical moment hold active: eligible withdrawals wait '.(int)$withdrawalPolicy['preflight_buffer_minutes'].'m before auto-dispatch.':'Standard operations: eligible withdrawals are dispatched immediately without delay.'?>
          </div>
        </div>
        <form method="post" class="d-inline">
          <input type="hidden" name="_csrf" value="<?=e(csrf_token())?>">
          <input type="hidden" name="action" value="toggle_preflight_buffer">
          <?php if($isSuperAdmin): ?>
            <button class="btn btn-sm btn-<?=!empty($withdrawalPolicy['preflight_buffer_enabled'])?'outline-success':'outline-warning'?>" type="submit">
              <i class="fas fa-toggle-<?=!empty($withdrawalPolicy['preflight_buffer_enabled'])?'on':'off'?> me-1"></i>
              <?=!empty($withdrawalPolicy['preflight_buffer_enabled'])?'Turn Buffer OFF (Restore Fast Dispatch)':'Turn Buffer ON (Critical Hold)'?>
            </button>
          <?php else: ?>
            <button class="btn btn-sm btn-outline-secondary" disabled title="Super Admin RBAC Protected">
              <i class="fas fa-lock me-1"></i> Super Admin Only
            </button>
          <?php endif; ?>
        </form>
      </div>
    </div>
  </div>
  <div class="col-md-5">
    <div class="card h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between">
          <span class="small text-secondary">Global Daily Cap: <?=fin_money($globalDailyUsed)?> / <?=fin_money($dailyCap)?></span>
          <span class="small fw-bold <?=$capPercent>=90?'text-danger':($capPercent>=70?'text-warning':'text-success')?>"><?=$capPercent?>%</span>
        </div>
        <div class="progress mt-2" style="height:8px;">
          <div class="progress-bar <?=$capPercent>=90?'bg-danger':($capPercent>=70?'bg-warning':'bg-success')?>" style="width:<?=$capPercent?>%"></div>
        </div>
      </div>
    </div>
  </div>
</div>
<div class="alert alert-info"><strong>First withdrawal rule:</strong> first-time withdrawals stay in manual review and must be handled within <?=e((string)$withdrawalPolicy['first_withdrawal_review_hours'])?> hour(s).</div><div class="card"><div class="table-responsive"><table class="table mb-0"><tr><th>Reference</th><th>Stakeholder</th><th>Amount</th><th>Provider</th><th>Bank</th><th>Review</th><th>Status</th><th></th></tr><?php foreach($withdrawals as $r):?><tr><td><?=e($r['reference'])?></td><td><?=e((string)$r['name'])?></td><td><?=e(fin_money((float)$r['amount']))?></td><td><?=e($r['provider'])?></td><td><?=e($r['bank_name'].' / '.$r['account_number'])?></td><td><span class="badge text-bg-<?=!empty($r['review_required'])?'warning':(!empty($r['preflight_buffered'])?'info':'success')?>"><?=!empty($r['review_required'])?'Manual':(!empty($r['preflight_buffered'])?'Pre-flight buffered':'Auto eligible')?></span><br><small><?=e((string)($r['review_reason']??''))?></small></td><td><?=e($r['status'])?></td><td><a class="btn btn-sm btn-outline-success" href="withdrawal_details.php?id=<?=(int)$r['id']?>">Review</a><?php if(!empty($r['preflight_buffered'])&&$r['status']==='pending'):?><form method="post" class="d-inline ms-1" onsubmit="return confirm('Release from pre-flight buffer and dispatch now?');"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="dispatch_buffered_withdrawal"><input type="hidden" name="withdrawal_id" value="<?=(int)$r['id']?>"><button class="btn btn-sm btn-success" title="Dispatch now">Release</button></form><?php endif;?></td></tr><?php endforeach?></table></div></div><?=fin_page($withdrawalTotal,$perPage,$currentPage)?><?php endif?><?php if($page==='refunds'):?><div class="card"><div class="table-responsive"><table class="table mb-0"><tr><th>Requester</th><th>Course</th><th>Amount</th><th>Reason</th><th>Decision</th></tr><?php foreach($refunds as $r):?><tr><td><?=e((string)$r['name'])?><br><small><?=e((string)$r['email'])?></small></td><td><?=e((string)$r['course_title'])?></td><td><?=e(fin_money((float)$r['amount']))?></td><td><?=e((string)$r['reason'])?></td><td><form method="post" class="d-flex gap-2"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="review_refund"><input type="hidden" name="refund_id" value="<?=(int)$r['id']?>"><select class="form-select form-select-sm" name="decision"><?php foreach(['under_review','approved','rejected','paid'] as $s):?><option <?=$r['status']===$s?'selected':''?>><?=e($s)?></option><?php endforeach?></select><input class="form-control form-control-sm" name="admin_notes" value="<?=e((string)$r['admin_notes'])?>"><button class="btn btn-sm btn-success">Save</button></form></td></tr><?php endforeach?></table></div></div><?=fin_page($refundTotal,$perPage,$currentPage)?><?php endif?>
<?php if($page==='payouts'):?><?php market_refund_ensure_schema($pdo); $marketplaceRefundTotal=(int)fin_scalar($pdo,'SELECT COUNT(*) FROM marketplace_refunds');$refundOffset=($currentPage-1)*$perPage;$marketplaceRefunds=fin_rows($pdo,'SELECT r.*, u.name buyer_name, u.email buyer_email FROM marketplace_refunds r LEFT JOIN users u ON u.id=r.buyer_user_id ORDER BY r.created_at DESC, r.id DESC LIMIT ? OFFSET ?',[$perPage,$refundOffset]);?><div class="card mb-3"><div class="card-header bg-white fw-bold">Marketplace Refunds</div><div class="table-responsive"><table class="table mb-0"><tr><th>Refund</th><th>Checkout</th><th>Buyer</th><th>Amount</th><th>Seller Reversal</th><th>Commission Reversed</th><th>Status</th><th>Created</th></tr><?php foreach($marketplaceRefunds as $rf):?><tr><td><?=e((string)$rf['refund_ref'])?></td><td><?=e((string)$rf['checkout_ref'])?></td><td><?=e((string)($rf['buyer_name']?:'Guest'))?></td><td class="money"><?=e(fin_money((float)$rf['refund_amount']))?></td><td class="money"><?=e(fin_money((float)$rf['settlement_reversal_amount']))?><?php if((float)$rf['settlement_shortfall_amount']>0):?><br><small class="text-danger">Shortfall <?=e(fin_money((float)$rf['settlement_shortfall_amount']))?></small><?php endif;?></td><td class="money"><?=e(fin_money((float)$rf['commission_reversal_amount']))?></td><td><?=e((string)$rf['status'])?></td><td><?=e((string)$rf['created_at'])?></td></tr><?php endforeach?><?php if(!$marketplaceRefunds):?><tr><td colspan="8" class="text-center text-secondary py-4">No marketplace refunds recorded.</td></tr><?php endif;?></table></div></div><?=fin_page($marketplaceRefundTotal,$perPage,$currentPage)?><div class="card"><div class="table-responsive"><table class="table mb-0"><tr><th>Order</th><th>Store</th><th>Amount</th><th>Payment</th><th>Payout</th><th>Settled</th><th>Refund</th></tr><?php foreach($payouts as $r):?><tr><td><?=e((string)$r['order_ref'])?></td><td><?=e((string)$r['store_name'])?></td><td><?=e(fin_money((float)$r['total_amount']))?></td><td><?=e((string)$r['payment_status'])?></td><td><?=e((string)($r['payout_status']??'pending'))?></td><td><?=e((string)($r['settled_at']??'-'))?></td><td><form method="post" class="d-flex gap-1" onsubmit="return confirm('Refund this checkout? The seller settlement and platform commission are reversed and the buyer is credited.')"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="refund_checkout"><input type="hidden" name="checkout_ref" value="<?=e((string)$r['checkout_ref'])?>"><input class="form-control form-control-sm" name="reason" placeholder="Refund reason" required><button class="btn btn-sm btn-outline-danger" type="submit">Refund</button></form></td></tr><?php endforeach?></table></div></div><?=fin_page($payoutTotal,$perPage,$currentPage)?><?php endif?>
<?php if($page==='reconciliation'):?><div class="card"><div class="card-header bg-white fw-bold">Daily Ledger Reconciliation</div><div class="table-responsive"><table class="table mb-0"><tr><th>Date</th><th>Transactions</th><th>Inflow</th><th>Outflow</th><th>Net</th></tr><?php foreach($daily as $r):?><tr><td><?=e($r['day'])?></td><td><?=number_format((int)$r['transactions'])?></td><td><?=e(fin_money((float)$r['inflow']))?></td><td><?=e(fin_money((float)$r['outflow']))?></td><td><?=e(fin_money((float)$r['inflow']-(float)$r['outflow']))?></td></tr><?php endforeach?></table></div></div><?php endif?>
<?php if($page==='integrity'):?><div class="row g-3 mb-3"><div class="col-md-3"><div class="card h-100"><div class="card-body"><small>Wallet rows</small><div class="h4"><?=number_format($walletTotal)?></div></div></div></div><div class="col-md-3"><div class="card h-100"><div class="card-body"><small>Wallet owners</small><div class="h4"><?=number_format($walletOwnerTotal)?></div></div></div></div><div class="col-md-3"><div class="card h-100"><div class="card-body"><small>Duplicate rows</small><div class="h4 text-danger"><?=number_format($duplicateWalletRows)?></div></div></div></div><div class="col-md-3"><div class="card h-100"><div class="card-body"><small>Wallet vs Ledger Variance</small><div class="h4 <?=abs($walletVariance)>0.009?'text-danger':'text-success'?>"><?=e(fin_money($walletVariance))?></div></div></div></div></div><div class="card"><div class="table-responsive"><table class="table mb-0"><tr><th>User</th><th>Rows</th><th>Wallet IDs</th><th>Wallet Exposure</th><th>Ledger Net</th><th>Variance</th></tr><?php foreach($walletIntegrityRows as $r):$variance=(float)$r['wallet_balance']-(float)$r['ledger_net'];?><tr><td><?=e($r['name'])?><br><small><?=e($r['email'])?></small></td><td><?=number_format((int)$r['wallet_rows'])?></td><td><?=e($r['wallet_ids'])?></td><td><?=e(fin_money((float)$r['wallet_balance']))?></td><td><?=e(fin_money((float)$r['ledger_net']))?></td><td><?=e(fin_money($variance))?></td></tr><?php endforeach?><?php if(!$walletIntegrityRows):?><tr><td colspan="6" class="text-center text-secondary py-4">No duplicate wallet rows detected.</td></tr><?php endif?></table></div></div><?php endif?>
<?php if($page==='rules'):?>
<?php $isSuperAdmin = !empty($admin['is_super_admin']) || (function_exists('super_admin_is_authorized') && super_admin_is_authorized($pdo)); ?>
<div class="row g-4">
  <div class="col-lg-7">
    <?php if (!$isSuperAdmin): ?>
      <div class="alert alert-warning mb-3"><strong><i class="fas fa-lock me-1"></i> RBAC Protected:</strong> Withdrawal automation switches and rules can only be altered by a Super Administrator. Standard staff and regular admins have read-only visibility.</div>
    <?php endif; ?>
    <div class="card"><div class="card-body"><h2 class="h5">Withdrawal Automation Rules</h2>
      <form method="post" class="d-grid gap-3">
        <input type="hidden" name="_csrf" value="<?=e(csrf_token())?>">
        <input type="hidden" name="action" value="save_withdrawal_rules">
        <label class="form-check"><input class="form-check-input" type="checkbox" name="wallet_auto_withdrawals_enabled" value="1" <?=$withdrawalPolicy['auto_enabled']?'checked':''?> <?=$isSuperAdmin?'':'disabled'?>> Enable automatic withdrawal approval</label>
        <label class="form-check"><input class="form-check-input" type="checkbox" name="wallet_auto_preflight_buffer_enabled" value="1" <?=$withdrawalPolicy['preflight_buffer_enabled']?'checked':''?> <?=$isSuperAdmin?'':'disabled'?>> Enable pre-flight inspection hold mode (critical moments buffer)</label>
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">Pre-flight buffer delay (minutes)</label><input class="form-control" type="number" name="wallet_auto_preflight_buffer_minutes" min="1" max="60" value="<?=e((string)$withdrawalPolicy['preflight_buffer_minutes'])?>" <?=$isSuperAdmin?'':'disabled'?>></div>
          <div class="col-md-6"><label class="form-label">Global platform daily cap (NGN)</label><input class="form-control" type="number" name="wallet_auto_global_daily_cap" min="0" step="0.01" value="<?=e((string)$withdrawalPolicy['global_daily_cap'])?>" <?=$isSuperAdmin?'':'disabled'?>></div>
          <div class="col-md-6"><label class="form-label">Maximum auto withdrawal (NGN)</label><input class="form-control" type="number" name="wallet_auto_max_amount" value="<?=e((string)$withdrawalPolicy['max_amount'])?>" <?=$isSuperAdmin?'':'disabled'?>></div>
          <div class="col-md-6"><label class="form-label">User daily auto limit (NGN)</label><input class="form-control" type="number" name="wallet_auto_daily_user_limit" value="<?=e((string)$withdrawalPolicy['daily_user_limit'])?>" <?=$isSuperAdmin?'':'disabled'?>></div>
          <div class="col-md-6"><label class="form-label">Completed withdrawals before automation</label><input class="form-control" type="number" name="wallet_auto_min_completed_withdrawals" value="<?=e((string)$withdrawalPolicy['min_completed_withdrawals'])?>" <?=$isSuperAdmin?'':'disabled'?>></div>
          <div class="col-md-6"><label class="form-label">First withdrawal SLA hours</label><input class="form-control" type="number" name="wallet_auto_first_withdrawal_review_hours" value="<?=e((string)$withdrawalPolicy['first_withdrawal_review_hours'])?>" <?=$isSuperAdmin?'':'disabled'?>></div>
        </div>
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">Withdrawal Flat Fee (NGN - added to debit)</label><input class="form-control" type="number" name="wallet_withdrawal_flat_fee" min="0" step="0.01" value="<?=e((string)$withdrawalPolicy['withdrawal_flat_fee'])?>" <?=$isSuperAdmin?'':'disabled'?>></div>
          <div class="col-md-6"><label class="form-label">Withdrawal Percent Fee (% - added to debit)</label><input class="form-control" type="number" name="wallet_withdrawal_percent_fee" min="0" step="0.01" value="<?=e((string)$withdrawalPolicy['withdrawal_percent_fee'])?>" <?=$isSuperAdmin?'':'disabled'?>></div>
          <div class="col-md-6"><label class="form-label">Deposit Flat Fee (NGN - added to depositor)</label><input class="form-control" type="number" name="wallet_deposit_flat_fee" min="0" step="0.01" value="<?=e((string)$withdrawalPolicy['deposit_flat_fee'])?>" <?=$isSuperAdmin?'':'disabled'?>></div>
          <div class="col-md-6"><label class="form-label">Deposit Percent Fee (% - added to depositor)</label><input class="form-control" type="number" name="wallet_deposit_percent_fee" min="0" step="0.01" value="<?=e((string)$withdrawalPolicy['deposit_percent_fee'])?>" <?=$isSuperAdmin?'':'disabled'?>></div>
        </div>
        <div>
          <label class="form-label">Providers allowed for automatic payout</label>
          <div class="d-flex flex-wrap gap-3">
            <?php foreach(['monnify'=>'Monnify','paystack'=>'Paystack','fincra'=>'Fincra','woven'=>'Woven Finance','korapay'=>'Korapay','allawee'=>'Allawee'] as $pCode=>$pLabel):?>
              <label class="me-2"><input type="checkbox" name="wallet_auto_allowed_providers[]" value="<?=e($pCode)?>" <?=in_array($pCode,$withdrawalPolicy['allowed_providers'],true)?'checked':''?> <?=$isSuperAdmin?'':'disabled'?>> <?=e($pLabel)?></label>
            <?php endforeach;?>
          </div>
        </div>
        <label class="form-check"><input class="form-check-input" type="checkbox" name="wallet_auto_require_account_name" value="1" <?=$withdrawalPolicy['require_account_name']?'checked':''?> <?=$isSuperAdmin?'':'disabled'?>> Require verified account name</label>
        <?php if ($isSuperAdmin): ?>
          <button class="btn btn-success">Save Withdrawal Rules</button>
        <?php else: ?>
          <button class="btn btn-secondary" disabled><i class="fas fa-lock me-1"></i> Super Admin Privileges Required to Save</button>
        <?php endif; ?>
      </form>
    </div></div>
  </div>
  <div class="col-lg-5">
    <div class="card"><div class="card-body">
      <h2 class="h5">Current Enforcement</h2>
      <p>Automation: <strong><?=$withdrawalPolicy['auto_enabled']?'Enabled':'Disabled'?></strong></p>
      <p>Pre-flight Buffer: <strong><?=$withdrawalPolicy['preflight_buffer_enabled']?'Enabled ('.$withdrawalPolicy['preflight_buffer_minutes'].'m hold)':'Disabled (Immediate)'?></strong></p>
      <p>Global Daily Cap: <strong><?=fin_money($withdrawalPolicy['global_daily_cap'])?></strong></p>
      <p>First withdrawal SLA: <strong><?=e((string)$withdrawalPolicy['first_withdrawal_review_hours'])?>h</strong></p>
      <p>Auto eligible pending: <strong><?=number_format($autoPending)?></strong></p>
      <p>Manual reviews due in 24h: <strong><?=number_format($firstReviewDue)?></strong></p>
    </div></div>
  </div>
</div>
<?php endif?>
<?php if($page==='gateways'):?>
<?php
$gatewayConfigs = [
  'monnify' => ['name' => 'Monnify', 'desc' => 'Reserved bank accounts, checkout funding, verification, and disbursements.'],
  'paystack' => ['name' => 'Paystack', 'desc' => 'Standard checkout modal, bank resolution, webhook idempotency, and transfer payouts.'],
  'fincra' => ['name' => 'Fincra', 'desc' => 'Multi-currency checkout, NUBAN account name inquiry, SHA-512 webhook validation, and bank payouts.'],
  'woven' => ['name' => 'Woven Finance', 'desc' => 'Checkout initialization, automated bank resolution, HMAC webhook verification, and transfer payouts.'],
  'korapay' => ['name' => 'Korapay', 'desc' => 'Checkout collections, instant bank resolution, HMAC-SHA256 signature security, and bulk transfer disbursements.'],
  'allawee' => ['name' => 'Allawee', 'desc' => 'Direct collection API, financial institution directory, Allawee signature verification, and payout processing.'],
];
?>
<div class="row g-4">
<?php foreach($gatewayConfigs as $gKey => $gMeta):
  $isCfg = gateway_is_configured($gKey);
?>
<div class="col-md-6 col-lg-4">
  <div class="card h-100">
    <div class="card-body d-flex flex-column">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="h5 mb-0"><?=e($gMeta['name'])?></h2>
        <span class="badge text-bg-<?=$isCfg?'success':'warning'?>"><?=$isCfg?'Configured':'Configuration required'?></span>
      </div>
      <p class="text-secondary small flex-grow-1"><?=e($gMeta['desc'])?></p>
      <form method="post" class="mt-2">
        <input type="hidden" name="_csrf" value="<?=e(csrf_token())?>">
        <input type="hidden" name="action" value="test_gateway">
        <button class="btn btn-sm btn-outline-success w-100" name="provider" value="<?=e($gKey)?>" type="submit">
          <i class="fas fa-plug me-1"></i> Test <?=e($gMeta['name'])?> Connection
        </button>
      </form>
    </div>
  </div>
</div>
<?php endforeach;?>
</div>
<div class="card mt-4">
  <div class="card-body">
    <h3 class="h6 mb-2">Multi-Gateway Failover &amp; Routing Engine</h3>
    <p class="text-secondary small mb-3">Deposits cascade to fallback gateways if the selected gateway is unavailable. Webhooks are authenticated with gateway-specific HMAC cryptographic signatures and execute idempotent wallet crediting with row-level transaction locks.</p>
    <form method="post" class="d-flex flex-wrap gap-2 align-items-center">
      <input type="hidden" name="_csrf" value="<?=e(csrf_token())?>">
      <input type="hidden" name="action" value="test_gateway">
      <span class="text-secondary small me-2">Quick probe:</span>
      <?php foreach($gatewayConfigs as $gKey => $gMeta): ?>
        <button class="btn btn-sm btn-outline-secondary" name="provider" value="<?=e($gKey)?>" type="submit"><?=e($gMeta['name'])?></button>
      <?php endforeach; ?>
    </form>
  </div>
</div>
<?php endif?>
</div></div>
<?php admin_page_end(); ?>