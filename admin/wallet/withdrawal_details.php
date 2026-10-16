<?php
declare(strict_types=1);

require_once __DIR__ . '/../_auth.php';
require_once __DIR__ . '/../../lib/admin-layout.php';
require_once __DIR__ . '/../../lib/admin-operator-strip.php';
require_once __DIR__ . '/../../lib/monnify.php';
require_once __DIR__ . '/../../lib/wallet-dual-control.php';

$pdo = db();
admin_ensure_schema($pdo);
wallet_ensure_schema($pdo);
admin_require($pdo, 'wallet');
$admin = current_user($pdo) ?: [];

function wx_e($value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function wx_money($value): string { return 'NGN ' . number_format((float) $value, 2); }
function wx_dt($value): string { if (!$value) return '-'; try { return (new DateTime((string) $value))->format('M j, Y g:i A'); } catch (Throwable $e) { return (string) $value; } }
function wx_dt_input($value): string { if (!$value) return ''; try { return (new DateTime((string) $value))->format('Y-m-d\TH:i'); } catch (Throwable $e) { return ''; } }
function wx_status_badge(string $status): string { return ['pending'=>'warning','processing'=>'info','approved'=>'success','completed'=>'success','rejected'=>'danger','failed'=>'danger'][strtolower(trim($status))] ?? 'secondary'; }

function wx_platform_earnings_allowed(PDO $pdo): bool {
    $role = admin_current_platform_role($pdo);
    return in_array($role, ['super_admin','admin','field_agent','support_agent','national_coordinator','state_coordinator','agronomist','agric_extensionist','extensionist'], true);
}

function wx_platform_earnings_info(PDO $pdo): ?array {
    if (!wx_platform_earnings_allowed($pdo) || !app_table_exists($pdo, 'platform_revenue_ledger')) {
        return null;
    }

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(revenue_amount),0) FROM platform_revenue_ledger WHERE source_module='registry' AND source_type='certificate_access_fee' AND status IN ('earned','collected')");
    $stmt->execute();
    $grower = (float) ($stmt->fetchColumn() ?: 0);

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(revenue_amount),0) FROM platform_revenue_ledger WHERE source_module='registry' AND source_type='provider_accreditation_fee' AND status IN ('earned','collected')");
    $stmt->execute();
    $provider = (float) ($stmt->fetchColumn() ?: 0);

    return ['grower' => $grower, 'provider' => $provider, 'total' => $grower + $provider];
}

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) { redirect_to('index.php?page=withdrawals'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $returnUrl = 'withdrawal_details.php?id=' . $id;
    try {
        if (!verify_csrf($_POST['_csrf'] ?? null)) { throw new RuntimeException('Invalid security token. Refresh the page and try again.'); }
        $action = (string) $_POST['action'];
        if ($action === 'update_policy') {
            $isSuperAdmin = !empty($admin['is_super_admin']) || (function_exists('super_admin_is_authorized') && super_admin_is_authorized($pdo));
            if (!$isSuperAdmin) {
                throw new RuntimeException('Access denied: Only Super Administrators have permission to modify withdrawal automation policy.');
            }
            $reviewMode = in_array((string) ($_POST['review_mode'] ?? 'manual'), ['manual','auto'], true) ? (string) $_POST['review_mode'] : 'manual';
            $reviewReason = trim((string) ($_POST['review_reason'] ?? ''));
            if ($reviewReason === '') { throw new RuntimeException('Add a clear policy reason for this withdrawal.'); }
            $reviewReason = mb_substr($reviewReason, 0, 255);
            $reviewDueAt = null;
            $rawDueAt = trim((string) ($_POST['review_due_at'] ?? ''));
            if ($rawDueAt !== '') { $reviewDueAt = (new DateTime($rawDueAt))->format('Y-m-d H:i:s'); }
            $policyNote = trim((string) ($_POST['policy_admin_note'] ?? ''));
            $isManual = $reviewMode === 'manual' ? 1 : 0;
            $pdo->prepare("UPDATE wallet_withdrawals SET review_required=?, automation_eligible=?, review_reason=?, review_due_at=?, admin_note=CASE WHEN ?='' THEN admin_note ELSE ? END, reviewed_by=COALESCE(reviewed_by, ?), updated_at=NOW() WHERE id=?")
                ->execute([$isManual, $isManual ? 0 : 1, $reviewReason, $isManual ? $reviewDueAt : null, $policyNote, $policyNote, (int) ($admin['id'] ?? 0), $id]);
            redirect_to($returnUrl . '&msg=' . urlencode('Withdrawal review policy updated.'));
        }
        if ($action === 'cancel_withdrawal') {
            // Operator-side cancel for a pending withdrawal that has
            // not been paid out; the held funds go back to the wallet.
            $stateStmt = $pdo->prepare("SELECT reference, status, funds_released FROM wallet_withdrawals WHERE id = ? LIMIT 1");
            $stateStmt->execute([$id]);
            $current = $stateStmt->fetch();
            if (!$current) { throw new RuntimeException('Withdrawal request was not found.'); }
            if ((int) ($current['funds_released'] ?? 0) === 1) { throw new RuntimeException('The held funds were already returned to the wallet.'); }
            if ((string) ($current['status'] ?? '') !== 'pending') { throw new RuntimeException('Only pending withdrawals can be cancelled by an operator.'); }
            $cancelNote = trim((string) ($_POST['cancel_note'] ?? ''));
            if ($cancelNote === '') { throw new RuntimeException('Add a cancellation reason before cancelling this withdrawal.'); }
            $release = wallet_release_withdrawal_funds($pdo, $id, 'cancelled', 'Cancelled by operator #' . (int) ($admin['id'] ?? 0) . ': ' . $cancelNote, (int) ($admin['id'] ?? 0));
            if (empty($release['success'])) { throw new RuntimeException((string) ($release['error'] ?? 'Unable to cancel this withdrawal.')); }
            redirect_to($returnUrl . '&msg=' . urlencode('Withdrawal cancelled; NGN ' . number_format((float) ($release['amount'] ?? 0), 2) . ' returned to the wallet.'));
        }
        if ($action === 'mark_payout_confirmed') {
            // Operator verified the payout at the bank (e.g. a manual
            // transfer done outside the platform).
            $stateStmt = $pdo->prepare("SELECT reference, status, payout_status FROM wallet_withdrawals WHERE id = ? LIMIT 1");
            $stateStmt->execute([$id]);
            $current = $stateStmt->fetch();
            if (!$current) { throw new RuntimeException('Withdrawal request was not found.'); }
            if (!in_array((string) ($current['status'] ?? ''), ['approved', 'paid'], true)) { throw new RuntimeException('Only approved or paid withdrawals can be marked as paid out.'); }
            $payoutState = strtolower((string) ($current['payout_status'] ?? ''));
            if (!in_array($payoutState, ['manual_review', 'initiated', 'pending'], true)) {
                throw new RuntimeException('This payout is already in a confirmed state (' . ($payoutState === '' ? 'none' : $payoutState) . ').');
            }
            $confirmNote = trim((string) ($_POST['confirm_note'] ?? ''));
            if ($confirmNote === '') { throw new RuntimeException('Add a confirmation note before marking this payout as paid.'); }
            $pdo->prepare("UPDATE wallet_withdrawals SET payout_status = 'confirmed', admin_note = CASE WHEN ? = '' THEN admin_note ELSE CONCAT(COALESCE(admin_note, ''), ' | ', ?) END, updated_at = NOW() WHERE id = ?")
                ->execute([$confirmNote, $confirmNote, $id]);
            if (function_exists('admin_audit')) {
                admin_audit($pdo, 'withdrawal_payout_confirmed', 'Admin #' . (int) ($admin['id'] ?? 0) . ' confirmed the payout for withdrawal #' . $id . ' (ref ' . $current['reference'] . ').');
            }
            redirect_to($returnUrl . '&msg=' . urlencode('Payout marked as confirmed.'));
        }
        if (!in_array($action, ['approve','reject'], true)) { throw new RuntimeException('Invalid withdrawal action.'); }
        $adminNote = trim((string) ($_POST['admin_note'] ?? ''));
        if ($action === 'reject' && $adminNote === '') { throw new RuntimeException('Add a rejection reason before rejecting this withdrawal.'); }
        if ($action === 'approve' && !admin_current_user_is_super_admin($pdo)) {
            $amountStmt = $pdo->prepare("SELECT amount FROM wallet_withdrawals WHERE id = ? LIMIT 1");
            $amountStmt->execute([$id]);
            $withdrawalAmount = (float) ($amountStmt->fetchColumn() ?: 0);
            if (wallet_dual_control_required($pdo, $withdrawalAmount)) {
                $requestId = wallet_queue_withdrawal_approval($pdo, $id, (int) ($admin['id'] ?? 0), $adminNote);
                redirect_to($returnUrl . '&msg=' . urlencode('Withdrawal queued for Super Admin approval (request #' . $requestId . '). No payout was sent.'));
            }
        }
        $result = wallet_admin_process_withdrawal($pdo, $id, (int) ($admin['id'] ?? 0), $action, $adminNote);
        if (empty($result['success'])) { throw new RuntimeException((string) ($result['error'] ?? 'Unable to process withdrawal.')); }
        redirect_to($returnUrl . '&msg=' . urlencode('Withdrawal ' . (string) ($result['status'] ?? 'updated') . '.'));
    } catch (Throwable $e) {
        redirect_to($returnUrl . '&error=' . urlencode($e->getMessage()));
    }
}

$stmt = $pdo->prepare("SELECT ww.*, u.name AS user_name, u.email AS user_email, u.phone AS user_phone, u.role, u.platform_role, w.balance, w.hold_balance, w.currency wallet_currency FROM wallet_withdrawals ww LEFT JOIN users u ON u.id=ww.user_id LEFT JOIN wallets w ON w.id=ww.wallet_id WHERE ww.id=?");
$stmt->execute([$id]);
$wd = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$wd) { app_render_error_page(404, 'This withdrawal could not be found.'); }

$platformEarnings = wx_platform_earnings_info($pdo);
$showPlatformEarnings = $platformEarnings !== null;
$platformGrowerCertificateEarnings = $platformEarnings['grower'] ?? 0.0;
$platformProviderAccreditationEarnings = $platformEarnings['provider'] ?? 0.0;
$platformTotalCertificateEarnings = $platformEarnings['total'] ?? 0.0;

$notice = trim((string) ($_GET['msg'] ?? ''));
$error = trim((string) ($_GET['error'] ?? ''));
$isPending = (string) ($wd['status'] ?? '') === 'pending';
// A failed payout never moved any money, so an operator can explicitly send it again.
$isFailed = (string) ($wd['status'] ?? '') === 'failed';
$fundsReleased = (int) ($wd['funds_released'] ?? 0) === 1;
$isDecidable = ($isPending || $isFailed) && !$fundsReleased;
$roleLabel = ucwords(str_replace('_', ' ', (string) (($wd['platform_role'] ?? '') ?: ($wd['role'] ?? 'Public user'))));
$provider = strtolower((string) ($wd['provider'] ?? 'manual'));
$reviewDueInput = wx_dt_input($wd['review_due_at'] ?? null);
?>
<?php admin_page_start('Withdrawal Review', [
    'topbar_only' => true,
    'active' => 'wallet/',
    'breadcrumbs' => array (
  0 => 
  array (
    'label' => 'Wallet & Payments',
  ),
  1 => 
  array (
    'label' => 'Wallet Workspace',
  ),
  2 => 
  array (
    'label' => 'Withdrawal Review',
  ),
),
    'head_pre' => '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"><link rel="stylesheet" href="../../assets/css/admin-workspaces.css"><script defer src="../../assets/js/nc-collapse.js"></script>',
    'css' => '.review-main{max-width:1180px;margin:0 auto;padding:28px 18px 52px}.review-hero{background:linear-gradient(135deg,#0b3b2a,#12613f);color:#fff;border-radius:12px;padding:24px}.metric-card,.card{border:1px solid #dde8df}.detail-item{display:flex;justify-content:space-between;gap:18px;border-bottom:1px solid #eef2ef;padding:10px 0}.detail-item span:first-child{color:#66756d;font-weight:700}.decision-panel{position:sticky;top:18px}.btn-approve{background:#0f7a4f;color:#fff}.btn-reject{background:#b42318;color:#fff}.mono{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace}@media(max-width:991px){.decision-panel{position:static}.detail-item{display:block}}',
]); ?><div class="review-main">
<?= admin_workspace_operator_strip($pdo, ['asset_prefix'=>'../../','profile_href'=>'../profile.php','password_href'=>'../profile.php#password','logout_action'=>'../admin.php','title'=>'Withdrawal review workspace','placeholder'=>'Search wallet withdrawals...']) ?>
<div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-3"><a class="btn btn-outline-success" href="index.php?page=withdrawals">Back to Withdrawals</a><div class="d-flex gap-2 flex-wrap"><a class="btn btn-outline-success" href="index.php?page=rules">Global Withdrawal Rules</a><a class="btn btn-success" href="reports.php">Wallet Reports</a></div></div>
<?php if ($notice !== ''): ?><div class="alert alert-success"><?= wx_e($notice) ?></div><?php endif; ?><?php if ($error !== ''): ?><div class="alert alert-danger"><strong>Action failed:</strong> <?= wx_e($error) ?></div><?php endif; ?>
<section class="review-hero mb-4"><div class="d-flex justify-content-between gap-3 flex-wrap"><div><div class="text-uppercase fw-bold small opacity-75">Withdrawal Review</div><h1 class="h2 mb-2"><?= wx_e((string) ($wd['reference'] ?? ('WD-' . $id))) ?></h1><p class="mb-0 opacity-75">Review payout destination, policy reason, wallet hold, and operator decision before funds leave the platform.</p></div><div class="text-end"><span class="badge text-bg-<?= wx_status_badge((string) $wd['status']) ?> fs-6"><?= wx_e(ucwords(str_replace('_', ' ', (string) $wd['status']))) ?></span><div class="h3 mt-3 mb-0"><?= wx_e(wx_money($wd['amount'])) ?></div><small class="opacity-75">Final payout: <?= wx_e(wx_money($wd['final_amount'] ?? $wd['amount'])) ?></small></div></div></section>
<div class="row g-4"><div class="col-lg-8"><div class="row g-3 mb-4"><div class="col-md-4"><div class="card card-body"><small>Requested</small><strong><?= wx_e(wx_dt($wd['requested_at'] ?? null)) ?></strong></div></div><div class="col-md-4"><div class="card card-body"><small>Provider</small><strong><?= wx_e(ucwords($provider ?: 'manual')) ?></strong></div></div><div class="col-md-4"><div class="card card-body"><small>Review Rule</small><strong><?= !empty($wd['review_required']) ? 'Manual Review' : 'Automation Eligible' ?></strong></div></div></div>
<div class="card shadow-sm mb-4"><div class="card-body"><h2 class="h5 mb-3">Requester</h2><?php foreach ([['Name',$wd['user_name'] ?: 'Unknown user'],['Email',$wd['user_email'] ?? '-'],['Phone',$wd['user_phone'] ?? '-'],['User Type',$roleLabel],['Wallet Balance',wx_money($wd['balance'] ?? 0)],['Wallet Hold',wx_money($wd['hold_balance'] ?? 0)]] as $item): ?><div class="detail-item"><span><?= wx_e($item[0]) ?></span><strong><?= wx_e($item[1]) ?></strong></div><?php endforeach; ?></div></div>
<div class="card shadow-sm mb-4"><div class="card-body"><h2 class="h5 mb-3">Payout Destination</h2><?php foreach ([['Bank',$wd['bank_name'] ?? '-'],['Account Number',$wd['account_number'] ?? '-'],['Account Name',$wd['account_name'] ?? '-'],['Bank Code',$wd['bank_code'] ?? '-'],['Charge',wx_money($wd['charge'] ?? 0)]] as $item): ?><div class="detail-item"><span><?= wx_e($item[0]) ?></span><strong class="<?= $item[0] === 'Account Number' ? 'mono' : '' ?>"><?= wx_e($item[1]) ?></strong></div><?php endforeach; ?></div></div>
<div class="card shadow-sm"><div class="card-body"><h2 class="h5 mb-3">Policy & Audit</h2><p class="text-secondary"><?= wx_e((string) ($wd['review_reason'] ?: 'No policy reason recorded.')) ?></p><?php foreach ([['Due By',wx_dt($wd['review_due_at'] ?? null)],['Reviewed At',wx_dt($wd['reviewed_at'] ?? null)],['Payout Status',$wd['payout_status'] ?: '-'],['Payout Reference',$wd['payout_reference'] ?: '-'],['User Note',$wd['note'] ?: '-'],['Admin Note',$wd['admin_note'] ?: '-']] as $item): ?><div class="detail-item"><span><?= wx_e($item[0]) ?></span><strong><?= wx_e($item[1]) ?></strong></div><?php endforeach; ?><hr><h3 class="h6">Change This Withdrawal Policy</h3><?php $isSuperAdmin = !empty($admin['is_super_admin']) || (function_exists('super_admin_is_authorized') && super_admin_is_authorized($pdo)); ?><?php if (!$isSuperAdmin): ?><div class="alert alert-warning small mb-3"><strong><i class="fas fa-lock me-1"></i> RBAC Protected:</strong> Only Super Administrators can alter individual withdrawal automation review policies and eligibility. Standard staff and regular admins have read-only visibility.</div><?php endif; ?><form method="post" class="row g-3"><input type="hidden" name="_csrf" value="<?= wx_e(csrf_token()) ?>"><input type="hidden" name="action" value="update_policy"><div class="col-md-5"><label class="form-label">Review mode</label><select class="form-select" name="review_mode" <?= $isSuperAdmin ? '' : 'disabled' ?>><option value="manual" <?= !empty($wd['review_required']) ? 'selected' : '' ?>>Manual review required</option><option value="auto" <?= empty($wd['review_required']) ? 'selected' : '' ?>>Automation eligible</option></select></div><div class="col-md-7"><label class="form-label">Manual review due</label><input class="form-control" type="datetime-local" name="review_due_at" value="<?= wx_e($reviewDueInput) ?>" <?= $isSuperAdmin ? '' : 'disabled' ?>></div><div class="col-12"><label class="form-label">Policy reason</label><input class="form-control" name="review_reason" maxlength="255" required value="<?= wx_e((string) ($wd['review_reason'] ?? '')) ?>" <?= $isSuperAdmin ? '' : 'disabled' ?>></div><div class="col-12"><label class="form-label">Operator note</label><textarea class="form-control" name="policy_admin_note" rows="3" maxlength="1000" <?= $isSuperAdmin ? '' : 'disabled' ?>></textarea></div><div class="col-12"><?php if ($isSuperAdmin): ?><button class="btn btn-outline-success">Update Review Policy</button><?php else: ?><button class="btn btn-outline-secondary" disabled><i class="fas fa-lock me-1"></i> Super Admin Privileges Required</button><?php endif; ?></div></form></div></div></div>
<div class="col-lg-4">
    <?php if ($showPlatformEarnings): ?>
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h5 mb-2">Platform Earnings Summary</h2>
            <p class="text-secondary small">These fees are retained in the NATCODEV platform revenue ledger; they are separate from the user wallet and should be considered when reviewing withdrawal approvals.</p>
            <div class="detail-item"><span>Grower certificate access / renewal</span><strong><?= wx_e(wx_money($platformGrowerCertificateEarnings)) ?></strong></div>
            <div class="detail-item"><span>Provider accreditation fees</span><strong><?= wx_e(wx_money($platformProviderAccreditationEarnings)) ?></strong></div>
            <div class="detail-item"><span class="fw-bold">Total platform earnings</span><strong class="fw-bold"><?= wx_e(wx_money($platformTotalCertificateEarnings)) ?></strong></div>
        </div>
    </div>
    <?php endif; ?>
    <div class="card shadow-sm decision-panel"><div class="card-body"><h2 class="h5 mb-2">Operator Decision</h2><p class="text-secondary small">Approve only after the bank details and review rule are acceptable. Rejecting returns the held funds to the user's wallet.</p><?php if ($isDecidable): ?><?php if ($isFailed): ?><div class="alert alert-warning small"><strong>Previous payout attempt failed.</strong><br><?= wx_e((string) ($wd['failure_reason'] ?? '')) ?></div><?php endif; ?><?php if (wallet_dual_control_required($pdo, (float) $wd['amount']) && !admin_current_user_is_super_admin($pdo)): ?><div class="alert alert-warning small"><strong>Dual control:</strong> this amount is at or above the Super Admin approval threshold, so approving queues it for Super Admin sign-off instead of paying out immediately.</div><?php endif; ?><form method="post" class="d-grid gap-3"><input type="hidden" name="_csrf" value="<?= wx_e(csrf_token()) ?>"><label class="form-label">Admin note</label><textarea class="form-control" name="admin_note" rows="5" maxlength="1000"></textarea><button class="btn btn-approve btn-lg" name="action" value="approve" onclick="return confirm('<?= $isFailed ? 'Retry this payout now?' : 'Approve this withdrawal and initiate payout?' ?>')"><?= $isFailed ? 'Retry Payout' : 'Approve &amp; Initiate Payout' ?></button><button class="btn btn-reject" name="action" value="reject" onclick="return confirm('Reject this withdrawal and release held funds back to the wallet?')">Reject & Release Hold</button></form><?php else: ?><div class="alert alert-secondary mb-0">This withdrawal is <?= wx_e((string) $wd['status']) ?> and needs no further decision.<?php if ($fundsReleased): ?> The held funds were returned to the user's wallet, so it must not be paid out again.<?php endif; ?></div><?php endif; ?></div></div>
</div>
</div></div>
<?php admin_page_end(); ?>
