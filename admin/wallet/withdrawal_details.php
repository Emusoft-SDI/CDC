<?php
declare(strict_types=1);

require_once __DIR__ . '/../_auth.php';
require_once __DIR__ . '/../../lib/admin-layout.php';
require_once __DIR__ . '/../../lib/admin-operator-strip.php';
require_once __DIR__ . '/../../lib/monnify.php';

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
        if (!in_array($action, ['approve','reject'], true)) { throw new RuntimeException('Invalid withdrawal action.'); }
        $adminNote = trim((string) ($_POST['admin_note'] ?? ''));
        if ($action === 'reject' && $adminNote === '') { throw new RuntimeException('Add a rejection reason before rejecting this withdrawal.'); }
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
if (!$wd) { http_response_code(404); exit('Withdrawal not found.'); }

$platformEarnings = wx_platform_earnings_info($pdo);
$showPlatformEarnings = $platformEarnings !== null;
$platformGrowerCertificateEarnings = $platformEarnings['grower'] ?? 0.0;
$platformProviderAccreditationEarnings = $platformEarnings['provider'] ?? 0.0;
$platformTotalCertificateEarnings = $platformEarnings['total'] ?? 0.0;

$notice = trim((string) ($_GET['msg'] ?? ''));
$error = trim((string) ($_GET['error'] ?? ''));
$isPending = (string) ($wd['status'] ?? '') === 'pending';
$roleLabel = ucwords(str_replace('_', ' ', (string) (($wd['platform_role'] ?? '') ?: ($wd['role'] ?? 'Public user'))));
$provider = strtolower((string) ($wd['provider'] ?? 'manual'));
$reviewDueInput = wx_dt_input($wd['review_due_at'] ?? null);
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Withdrawal Review - NATCODEV Wallet</title><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"><link rel="stylesheet" href="../../assets/css/admin-workspaces.css"><style>body{background:#f4f7f5;color:#17231d}.review-main{max-width:1180px;margin:0 auto;padding:28px 18px 52px}.review-hero{background:linear-gradient(135deg,#0b3b2a,#12613f);color:#fff;border-radius:12px;padding:24px}.metric-card,.card{border:1px solid #dde8df}.detail-item{display:flex;justify-content:space-between;gap:18px;border-bottom:1px solid #eef2ef;padding:10px 0}.detail-item span:first-child{color:#66756d;font-weight:700}.decision-panel{position:sticky;top:18px}.btn-approve{background:#0f7a4f;color:#fff}.btn-reject{background:#b42318;color:#fff}.mono{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace}@media(max-width:991px){.decision-panel{position:static}.detail-item{display:block}}</style></head><body><main class="review-main">
<?= admin_workspace_operator_strip($pdo, ['asset_prefix'=>'../../','profile_href'=>'../profile.php','password_href'=>'../profile.php#password','logout_action'=>'../admin.php','title'=>'Withdrawal review workspace','placeholder'=>'Search wallet withdrawals...']) ?>
<div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-3"><a class="btn btn-outline-success" href="index.php?page=withdrawals">Back to Withdrawals</a><div class="d-flex gap-2 flex-wrap"><a class="btn btn-outline-success" href="index.php?page=rules">Global Withdrawal Rules</a><a class="btn btn-success" href="reports.php">Wallet Reports</a></div></div>
<?php if ($notice !== ''): ?><div class="alert alert-success"><?= wx_e($notice) ?></div><?php endif; ?><?php if ($error !== ''): ?><div class="alert alert-danger"><strong>Action failed:</strong> <?= wx_e($error) ?></div><?php endif; ?>
<section class="review-hero mb-4"><div class="d-flex justify-content-between gap-3 flex-wrap"><div><div class="text-uppercase fw-bold small opacity-75">Withdrawal Review</div><h1 class="h2 mb-2"><?= wx_e((string) ($wd['reference'] ?? ('WD-' . $id))) ?></h1><p class="mb-0 opacity-75">Review payout destination, policy reason, wallet hold, and operator decision before funds leave the platform.</p></div><div class="text-end"><span class="badge text-bg-<?= wx_status_badge((string) $wd['status']) ?> fs-6"><?= wx_e(ucwords(str_replace('_', ' ', (string) $wd['status']))) ?></span><div class="h3 mt-3 mb-0"><?= wx_e(wx_money($wd['amount'])) ?></div><small class="opacity-75">Final payout: <?= wx_e(wx_money($wd['final_amount'] ?? $wd['amount'])) ?></small></div></div></section>
<div class="row g-4"><div class="col-lg-8"><div class="row g-3 mb-4"><div class="col-md-4"><div class="card card-body"><small>Requested</small><strong><?= wx_e(wx_dt($wd['requested_at'] ?? null)) ?></strong></div></div><div class="col-md-4"><div class="card card-body"><small>Provider</small><strong><?= wx_e(ucwords($provider ?: 'manual')) ?></strong></div></div><div class="col-md-4"><div class="card card-body"><small>Review Rule</small><strong><?= !empty($wd['review_required']) ? 'Manual Review' : 'Automation Eligible' ?></strong></div></div></div>
<div class="card shadow-sm mb-4"><div class="card-body"><h2 class="h5 mb-3">Requester</h2><?php foreach ([['Name',$wd['user_name'] ?: 'Unknown user'],['Email',$wd['user_email'] ?? '-'],['Phone',$wd['user_phone'] ?? '-'],['User Type',$roleLabel],['Wallet Balance',wx_money($wd['balance'] ?? 0)],['Wallet Hold',wx_money($wd['hold_balance'] ?? 0)]] as $item): ?><div class="detail-item"><span><?= wx_e($item[0]) ?></span><strong><?= wx_e($item[1]) ?></strong></div><?php endforeach; ?></div></div>
<div class="card shadow-sm mb-4"><div class="card-body"><h2 class="h5 mb-3">Payout Destination</h2><?php foreach ([['Bank',$wd['bank_name'] ?? '-'],['Account Number',$wd['account_number'] ?? '-'],['Account Name',$wd['account_name'] ?? '-'],['Bank Code',$wd['bank_code'] ?? '-'],['Charge',wx_money($wd['charge'] ?? 0)]] as $item): ?><div class="detail-item"><span><?= wx_e($item[0]) ?></span><strong class="<?= $item[0] === 'Account Number' ? 'mono' : '' ?>"><?= wx_e($item[1]) ?></strong></div><?php endforeach; ?></div></div>
<div class="card shadow-sm"><div class="card-body"><h2 class="h5 mb-3">Policy & Audit</h2><p class="text-secondary"><?= wx_e((string) ($wd['review_reason'] ?: 'No policy reason recorded.')) ?></p><?php foreach ([['Due By',wx_dt($wd['review_due_at'] ?? null)],['Reviewed At',wx_dt($wd['reviewed_at'] ?? null)],['Payout Status',$wd['payout_status'] ?: '-'],['Payout Reference',$wd['payout_reference'] ?: '-'],['User Note',$wd['note'] ?: '-'],['Admin Note',$wd['admin_note'] ?: '-']] as $item): ?><div class="detail-item"><span><?= wx_e($item[0]) ?></span><strong><?= wx_e($item[1]) ?></strong></div><?php endforeach; ?><hr><h3 class="h6">Change This Withdrawal Policy</h3><form method="post" class="row g-3"><input type="hidden" name="_csrf" value="<?= wx_e(csrf_token()) ?>"><input type="hidden" name="action" value="update_policy"><div class="col-md-5"><label class="form-label">Review mode</label><select class="form-select" name="review_mode"><option value="manual" <?= !empty($wd['review_required']) ? 'selected' : '' ?>>Manual review required</option><option value="auto" <?= empty($wd['review_required']) ? 'selected' : '' ?>>Automation eligible</option></select></div><div class="col-md-7"><label class="form-label">Manual review due</label><input class="form-control" type="datetime-local" name="review_due_at" value="<?= wx_e($reviewDueInput) ?>"></div><div class="col-12"><label class="form-label">Policy reason</label><input class="form-control" name="review_reason" maxlength="255" required value="<?= wx_e((string) ($wd['review_reason'] ?? '')) ?>"></div><div class="col-12"><label class="form-label">Operator note</label><textarea class="form-control" name="policy_admin_note" rows="3" maxlength="1000"></textarea></div><div class="col-12"><button class="btn btn-outline-success">Update Review Policy</button></div></form></div></div></div>
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
    <div class="card shadow-sm decision-panel"><div class="card-body"><h2 class="h5 mb-2">Operator Decision</h2><p class="text-secondary small">Approve only after the bank details and review rule are acceptable. Rejecting returns the held funds to the user's wallet.</p><?php if ($isPending): ?><form method="post" class="d-grid gap-3"><input type="hidden" name="_csrf" value="<?= wx_e(csrf_token()) ?>"><label class="form-label">Admin note</label><textarea class="form-control" name="admin_note" rows="5" maxlength="1000"></textarea><button class="btn btn-approve btn-lg" name="action" value="approve" onclick="return confirm('Approve this withdrawal and initiate payout?')">Approve & Initiate Payout</button><button class="btn btn-reject" name="action" value="reject" onclick="return confirm('Reject this withdrawal and release held funds back to the wallet?')">Reject & Release Hold</button></form><?php else: ?><div class="alert alert-secondary mb-0">This withdrawal is no longer pending.</div><?php endif; ?></div></div>
</div>
</div></main><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script></body></html>
