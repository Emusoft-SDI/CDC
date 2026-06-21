<?php
/**
 * NATCODEV wallet operations dashboard.
 */

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../lib/admin-layout.php';
require_once __DIR__ . '/../lib/monnify.php';
require_once __DIR__ . '/../lib/marketplace.php';
require_once __DIR__ . '/../lib/academy.php';

$pdo = db();
admin_ensure_schema($pdo);
wallet_ensure_schema($pdo);
marketplace_ensure_schema($pdo);
academy_ensure_schema($pdo);
admin_require($pdo);

$admin = current_user($pdo) ?: [];
$message = '';
$error = '';

function wx_e($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function wx_badge($status) {
    $s = strtolower(trim($status));
    if (in_array($s, ['completed', 'successful', 'success', 'paid', 'settled', 'approved', 'active'])) return 'success';
    if (in_array($s, ['pending', 'processing', 'scheduled'])) return 'pending';
    if (in_array($s, ['failed', 'rejected', 'cancelled'])) return 'danger';
    return 'info';
}
function wx_dt($date) {
    if (!$date) return '-';
    try { return (new DateTime($date))->format('M d, Y h:i A'); } catch (Exception $e) { return $date; }
}
function wx_scalar($pdo, $sql, $params = []) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (float) ($stmt->fetchColumn() ?: 0);
    } catch (Exception $e) { return 0.0; }
}
function wx_rows($pdo, $sql, $params = []) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Exception $e) { return []; }
}

$platformBalance = wx_scalar($pdo, "SELECT COALESCE(SUM(balance), 0) FROM wallets");
$todayInflow = wx_scalar($pdo, "SELECT COALESCE(SUM(amount), 0) FROM wallet_transactions WHERE type='credit' AND DATE(created_at) = CURDATE()");
$todayOutflow = wx_scalar($pdo, "SELECT COALESCE(SUM(amount), 0) FROM wallet_transactions WHERE type='debit' AND DATE(created_at) = CURDATE()");
$pendingWithdrawals = wx_rows($pdo, "SELECT ww.*, u.name as user_name FROM wallet_withdrawals ww LEFT JOIN users u ON u.id = ww.user_id WHERE ww.status = 'pending' ORDER BY ww.requested_at DESC LIMIT 10");
$recentTransactions = wx_rows($pdo, "SELECT wt.*, u.name as user_name FROM wallet_transactions wt LEFT JOIN wallets w ON w.id = wt.wallet_id LEFT JOIN users u ON u.id = w.user_id ORDER BY wt.created_at DESC LIMIT 10");
$users = wx_rows($pdo, "SELECT id, name, email FROM users ORDER BY name LIMIT 100");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid security token.';
    } elseif ($action === 'fund_wallet') {
        if (!app_check_rate_limit('admin_wallet_fund', 10, 600)) {
            $error = 'Too many funding attempts. Try again later.';
        } else {
            $result = wallet_admin_credit(
                $pdo,
                (int) ($_POST['user_id'] ?? 0),
                (float) ($_POST['amount'] ?? 0),
                (int) ($admin['id'] ?? 0),
                (string) ($_POST['note'] ?? '')
            );
            if (!$result['success']) {
                $error = (string) ($result['error'] ?? 'Wallet funding failed.');
            } else {
                $message = 'Wallet funded successfully.';
            }
        }
        if ($message !== '') {
            header("Location: ?msg=funded");
            exit;
        }
    } else {
        $error = 'Invalid wallet action.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>NATCODEV Wallet Admin</title>
    <style>
        body { font-family: 'Inter', sans-serif; background: #f4f6f4; color: #1a1a1a; margin: 0; display: flex; }
        .main { flex: 1; padding: 30px; }
        .card { background: #fff; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: #fff; padding: 20px; border-radius: 12px; border: 1px solid #e5e7eb; }
        .stat-value { font-size: 24px; font-weight: 700; margin-top: 10px; }
        .btn { padding: 10px 20px; border-radius: 8px; border: none; cursor: pointer; font-weight: 600; }
        .btn-primary { background: #164a33; color: #fff; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f9fafb; font-size: 12px; text-transform: uppercase; color: #6b7280; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .form-group { margin-bottom: 15px; }
        .form-label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px; }
        .form-input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/_sidebar.php'; ?>

<div class="main">
    <h1>Wallet Dashboard</h1>
    
    <?php if(isset($_GET['msg'])): ?>
        <div style="background:#dcfce7; color:#166534; padding:10px; border-radius:6px; margin-bottom:20px">
            ✅ Action completed successfully.
        </div>
    <?php endif; ?>

    <?php if($error): ?>
        <div style="background:#fee2e2; color:#991b1b; padding:10px; border-radius:6px; margin-bottom:20px">
            <?= wx_e($error) ?>
        </div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card">
            <div style="color:#6b7280; font-size:12px; text-transform:uppercase">Platform Balance</div>
            <div class="stat-value">₦<?= number_format($platformBalance, 2) ?></div>
        </div>
        <div class="stat-card">
            <div style="color:#6b7280; font-size:12px; text-transform:uppercase">Today's Inflow</div>
            <div class="stat-value" style="color:#166534">+₦<?= number_format($todayInflow, 2) ?></div>
        </div>
        <div class="stat-card">
            <div style="color:#6b7280; font-size:12px; text-transform:uppercase">Today's Outflow</div>
            <div class="stat-value" style="color:#dc2626">-₦<?= number_format($todayOutflow, 2) ?></div>
        </div>
    </div>

    <div style="display:grid; grid-template-columns: 2fr 1fr; gap:20px">
        <!-- Recent Transactions -->
        <div class="card">
            <h3>Recent Transactions</h3>
            <table>
                <thead>
                    <tr><th>User</th><th>Type</th><th>Amount</th><th>Date</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <?php foreach($recentTransactions as $tx): ?>
                    <tr>
                        <td><?= wx_e($tx['user_name'] ?? 'Unknown') ?></td>
                        <td><?= wx_e($tx['type']) ?></td>
                        <td style="font-weight:600">₦<?= number_format($tx['amount'], 2) ?></td>
                        <td><?= wx_dt($tx['created_at']) ?></td>
                        <td><span class="badge badge-<?= wx_badge($tx['status']) ?>"><?= wx_e($tx['status']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($recentTransactions)): ?>
                        <tr><td colspan="5" style="text-align:center; color:#999">No transactions found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Quick Fund Wallet -->
        <div class="card">
            <h3>Fund User Wallet</h3>
            <form method="POST">
                <input type="hidden" name="_csrf" value="<?= wx_e(csrf_token()) ?>">
                <input type="hidden" name="action" value="fund_wallet">
                <div class="form-group">
                    <label class="form-label">Select User</label>
                    <select name="user_id" class="form-input">
                        <?php foreach($users as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= wx_e($u['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Amount (NGN)</label>
                    <input type="number" name="amount" class="form-input" step="0.01" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Funding Note</label>
                    <input type="text" name="note" class="form-input" maxlength="500">
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%">Fund Wallet</button>
            </form>
        </div>
    </div>

    <!-- Withdrawals Section -->
    <div class="card">
        <h3>Pending Withdrawals</h3>
        <table>
            <thead>
                <tr><th>User</th><th>Bank</th><th>Account</th><th>Amount</th><th>Status</th><th>Action</th></tr>
            </thead>
            <tbody>
                <?php foreach($pendingWithdrawals as $wd): ?>
                <tr>
                    <td><?= wx_e($wd['user_name']) ?></td>
                    <td><?= wx_e($wd['bank_name']) ?></td>
                    <td><?= wx_e($wd['account_number']) ?></td>
                    <td>₦<?= number_format($wd['amount'], 2) ?></td>
                    <td><span class="badge badge-pending">Pending</span></td>
                    <td><a class="btn btn-primary" style="padding:5px 10px; font-size:12px; text-decoration:none" href="withdrawal_details.php?id=<?= (int) $wd['id'] ?>">Review</a></td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($pendingWithdrawals)): ?>
                    <tr><td colspan="6" style="text-align:center; color:#999">No pending withdrawals.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
