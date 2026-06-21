<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../lib/admin-layout.php';
require_once __DIR__ . '/../lib/monnify.php';

$pdo = db();
admin_require($pdo);
$admin = current_user($pdo) ?: [];

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header("Location: withdrawals.php"); exit; }

// Handle Actions (Approve/Reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        http_response_code(403);
        exit('Invalid security token.');
    }
    $action = (string) $_POST['action'];
    if (!in_array($action, ['approve', 'reject'], true)) {
        http_response_code(422);
        exit('Invalid withdrawal action.');
    }

    $result = wallet_admin_process_withdrawal($pdo, $id, (int) ($admin['id'] ?? 0), $action, (string) ($_POST['admin_note'] ?? ''));
    if (!$result['success']) {
        die("Action failed: " . wx_e($result['error'] ?? 'Unable to process withdrawal.'));
    }
    
    header("Location: withdrawal_details.php?id=$id&msg=updated");
    exit;
}

$stmt = $pdo->prepare("
    SELECT ww.*, u.name as user_name, u.email as user_email
    FROM wallet_withdrawals ww 
    LEFT JOIN users u ON u.id = ww.user_id 
    WHERE ww.id = ?
");
$stmt->execute([$id]);
$wd = $stmt->fetch();

if (!$wd) { die("Withdrawal not found."); }

function wx_e($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Withdrawal Details</title>
    <style>
        body { font-family: 'Inter', sans-serif; background: #f4f6f4; display: flex; }
        .sidebar { width: 240px; background: #0a2418; color: #fff; min-height: 100vh; padding: 20px; }
        .main { flex: 1; padding: 30px; }
        .card { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .label { color: #6b7280; font-size: 12px; text-transform: uppercase; }
        .value { font-weight: 600; margin-bottom: 15px; }
        .btn { padding: 10px 20px; border-radius: 6px; border: none; cursor: pointer; font-weight: 600; }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/_sidebar.php'; ?>
<div class="main">
    <h1>Withdrawal #<?= $wd['id'] ?></h1>
    <?php if(isset($_GET['msg'])): ?>
        <div style="background:#dcfce7; color:#166534; padding:10px; border-radius:6px; margin-bottom:20px">✅ Updated.</div>
    <?php endif; ?>
    <div class="card">
        <div class="detail-grid">
            <div>
                <div class="label">User</div><div class="value"><?= wx_e($wd['user_name']) ?> (<?= wx_e($wd['user_email']) ?>)</div>
                <div class="label">Amount</div><div class="value">₦<?= number_format($wd['amount'], 2) ?></div>
            </div>
            <div>
                <div class="label">Bank</div><div class="value"><?= wx_e($wd['bank_name']) ?></div>
                <div class="label">Account</div><div class="value"><?= wx_e($wd['account_number']) ?> (<?= wx_e($wd['account_name']) ?>)</div>
                <div class="label">Status</div><div class="value"><?= wx_e($wd['status']) ?></div>
            </div>
        </div>
        <?php if($wd['status'] === 'pending'): ?>
        <form method="POST">
            <input type="hidden" name="_csrf" value="<?= wx_e(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= $wd['id'] ?>">
            <input type="text" name="admin_note" placeholder="Admin note optional" style="display:block; margin-bottom:12px; padding:10px; width:100%; max-width:420px;">
            <button type="submit" name="action" value="approve" class="btn" style="background:#dcfce7">Approve</button>
            <button type="submit" name="action" value="reject" class="btn" style="background:#fee2e2">Reject</button>
        </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
