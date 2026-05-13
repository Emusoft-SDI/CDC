<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

session_start();
$pdo = db();
$user = require_user_role($pdo, ['grower', 'admin']);

$amount = floatval($_POST['amount'] ?? 0);
if ($amount <= 0) {
    json_response(['success' => false, 'error' => 'Invalid amount'], 422);
}

// In production: integrate with Paystack/Flutterwave
// For now: simulate payment
$reference = 'REF_' . time() . '_' . rand(1000, 9999);

try {
    app_ensure_farmer_engagement_schema($pdo);

    // Get wallet ID
    $stmt = $pdo->prepare("SELECT id FROM wallets WHERE user_id = ?");
    $stmt->execute([(int) $user['id']]);
    $walletId = $stmt->fetchColumn();

    if (!$walletId) {
        $pdo->prepare("INSERT INTO wallets (user_id) VALUES (?)")->execute([(int) $user['id']]);
        $walletId = $pdo->lastInsertId();
    }

    // Create pending transaction
    $pdo->prepare("
        INSERT INTO wallet_transactions (wallet_id, amount, type, description, reference, status)
        VALUES (?, ?, 'credit', 'Wallet funding', ?, 'pending')
    ")->execute([$walletId, $amount, $reference]);

    json_response([
        'success' => true,
        'reference' => $reference,
        'payment_url' => '/payment-gateway?ref=' . $reference // Your payment gateway
    ]);
} catch (Throwable $e) {
    error_log('Wallet error: ' . $e->getMessage());
    json_response(['success' => false, 'error' => 'System error'], 500);
}
