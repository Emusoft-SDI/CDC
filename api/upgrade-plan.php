<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

session_start();
$pdo = db();
$user = require_user_role($pdo, ['grower', 'admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'POST method required'], 405);
}

try {
    app_ensure_farmer_engagement_schema($pdo);
    app_add_column_if_missing($pdo, 'users', 'plan', "VARCHAR(30) NOT NULL DEFAULT 'basic'");
    app_add_column_if_missing($pdo, 'users', 'plan_expiry', 'DATETIME NULL');

    $pdo->beginTransaction();
    $stmt = $pdo->prepare("SELECT balance FROM wallets WHERE user_id = ? FOR UPDATE");
    $stmt->execute([(int) $user['id']]);
    $balance = (float) $stmt->fetchColumn();

    if ($balance < 5000) {
        $pdo->rollBack();
        json_response(['success' => false, 'error' => 'Insufficient balance. Fund your wallet first.'], 402);
    }

    $pdo->prepare("UPDATE wallets SET balance = balance - 5000 WHERE user_id = ?")->execute([(int) $user['id']]);
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
