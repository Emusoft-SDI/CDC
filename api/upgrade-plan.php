<?php
session_start();
// Check wallet balance
$stmt = $pdo->prepare("
    SELECT w.balance 
    FROM wallets w 
    JOIN users u ON w.user_id = u.id 
    WHERE u.id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$balance = $stmt->fetchColumn();

if ($balance >= 5000) {
    // Deduct from wallet
    $pdo->prepare("UPDATE wallets SET balance = balance - 5000 WHERE user_id = ?")->execute([$_SESSION['user_id']]);
    
    // Update plan
    $pdo->prepare("UPDATE users SET plan = 'premium', plan_expiry = DATE_ADD(NOW(), INTERVAL 1 YEAR) WHERE id = ?")->execute([$_SESSION['user_id']]);
    
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['error' => 'Insufficient balance. Fund your wallet first.']);
}
?>