<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit(json_encode(['error' => 'Not logged in']));
}

$amount = floatval($_POST['amount'] ?? 0);
if ($amount <= 0) {
    exit(json_encode(['error' => 'Invalid amount']));
}

// In production: integrate with Paystack/Flutterwave
// For now: simulate payment
$reference = 'REF_' . time() . '_' . rand(1000, 9999);

try {
    $pdo = new PDO("mysql:host=localhost;dbname=natcodevcom_data;charset=utf8mb4", 
                   "natcodevcom_data", "XC^#3)[;*xTcm&V9");
    
    // Get wallet ID
    $stmt = $pdo->prepare("SELECT id FROM wallets WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $walletId = $stmt->fetchColumn();
    
    if (!$walletId) {
        exit(json_encode(['error' => 'Wallet not found']));
    }
    
    // Create pending transaction
    $pdo->prepare("
        INSERT INTO wallet_transactions (wallet_id, amount, type, description, reference, status)
        VALUES (?, ?, 'credit', 'Wallet funding', ?, 'pending')
    ")->execute([$walletId, $amount, $reference]);
    
    echo json_encode([
        'success' => true,
        'reference' => $reference,
        'payment_url' => '/payment-gateway?ref=' . $reference // Your payment gateway
    ]);
    
} catch (Exception $e) {
    error_log("Wallet error: " . $e->getMessage());
    echo json_encode(['error' => 'System error']);
}
?>