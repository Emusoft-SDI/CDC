<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

session_start();
$pdo = db();
$user = require_user_role($pdo, ['grower', 'admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'POST method required'], 405);
}

if (!verify_csrf($_POST['_csrf'] ?? null)) {
    json_response(['success' => false, 'error' => 'Invalid security token'], 403);
}

try {
    app_ensure_farmer_engagement_schema($pdo);
    app_add_column_if_missing($pdo, 'users', 'plan', "VARCHAR(30) NOT NULL DEFAULT 'basic'");
    app_add_column_if_missing($pdo, 'users', 'plan_expiry', 'DATETIME NULL');

    $pdo->beginTransaction();

    $userLock = $pdo->prepare("SELECT plan, plan_expiry FROM users WHERE id = ? FOR UPDATE");
    $userLock->execute([(int) $user['id']]);
    $currUser = $userLock->fetch();
    if ($currUser && (string) ($currUser['plan'] ?? '') === 'premium' && !empty($currUser['plan_expiry'])) {
        $expiryTime = strtotime((string) $currUser['plan_expiry']);
        if ($expiryTime > (time() + 30 * 86400)) {
            $pdo->commit();
            json_response(['success' => true, 'message' => 'Plan already upgraded', 'duplicate' => true]);
        }
    }

    $stmt = $pdo->prepare("SELECT id, balance FROM wallets WHERE user_id = ? FOR UPDATE");
    $stmt->execute([(int) $user['id']]);
    $wallet = $stmt->fetch();
    $balance = (float) ($wallet['balance'] ?? 0);

    if ($balance < 5000) {
        $pdo->rollBack();
        json_response(['success' => false, 'error' => 'Insufficient balance. Fund your wallet first.'], 402);
    }

    $walletId = (int) ($wallet['id'] ?? 0);
    $before = $balance;
    $after = $before - 5000;
    $planRef = 'PLAN-UPG-' . (int) $user['id'] . '-' . date('ymd-His') . '-' . strtoupper(bin2hex(random_bytes(2)));

    $pdo->prepare("UPDATE wallets SET balance = balance - 5000 WHERE user_id = ?")->execute([(int) $user['id']]);
    if ($walletId > 0) {
        $pdo->prepare("
            INSERT INTO wallet_transactions
                (wallet_id, user_id, amount, type, direction, description, reference, provider, status, balance_before, balance_after, completed_at)
            VALUES (?, ?, 5000, 'debit', 'outflow', 'Annual Premium Plan Upgrade', ?, 'membership', 'completed', ?, ?, NOW())
        ")->execute([$walletId, (int) $user['id'], $planRef, $before, $after]);
    }
    $pdo->prepare("UPDATE users SET plan = 'premium', plan_expiry = DATE_ADD(NOW(), INTERVAL 1 YEAR) WHERE id = ?")
        ->execute([(int) $user['id']]);
    $pdo->commit();

    json_response(['success' => true]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Upgrade plan API error: ' . $e->getMessage());
    json_response(['success' => false, 'error' => 'Unable to upgrade plan'], 500);
}
