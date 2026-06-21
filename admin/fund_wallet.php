<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../lib/admin-layout.php';
require_once __DIR__ . '/../lib/monnify.php';

$pdo = db();
admin_require($pdo);
$admin = current_user($pdo) ?: [];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'], $_POST['amount'])) {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid security token.';
    } elseif (!app_check_rate_limit('admin_wallet_fund', 10, 600)) {
        $error = 'Too many funding attempts. Try again later.';
    } else {
        $result = wallet_admin_credit(
            $pdo,
            (int) $_POST['user_id'],
            (float) $_POST['amount'],
            (int) ($admin['id'] ?? 0),
            (string) ($_POST['note'] ?? '')
        );
        if ($result['success']) {
            header('Location: fund_wallet.php?msg=success');
            exit;
        }
        $error = (string) ($result['error'] ?? 'Wallet funding failed.');
    }
}

$users = $pdo->query("SELECT id, name FROM users ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Fund Wallet</title>
<style>body{font-family: 'Inter', sans-serif; background: #f4f6f4; display: flex;}.main{flex:1; padding:30px;}.card{background:#fff; padding:20px; border-radius:12px;}.form-input{width:100%; padding:10px; margin:10px 0;}</style>
</head>
<body>
<?php require_once __DIR__ . '/_sidebar.php'; ?>
<div class="main">
    <h1>Fund Wallet</h1>
    <div class="card">
        <?php if (isset($_GET['msg'])): ?><p style="color:#166534;">Wallet funded successfully.</p><?php endif; ?>
        <?php if ($error): ?><p style="color:#a32020;"><?= e($error) ?></p><?php endif; ?>
        <form method="POST">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <select name="user_id" class="form-input" required>
                <?php foreach($users as $u): ?><option value="<?= (int) $u['id'] ?>"><?= e($u['name']) ?></option><?php endforeach; ?>
            </select>
            <input type="number" name="amount" class="form-input" placeholder="Amount" step="0.01" required>
            <input type="text" name="note" class="form-input" placeholder="Funding note optional" maxlength="500">
            <button type="submit" style="padding:10px 20px; background:#164a33; color:#fff; border:none; border-radius:6px;">Fund Wallet</button>
        </form>
    </div>
</div>
</body>
</html>
