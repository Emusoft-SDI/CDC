<?php
// Handle payment success from Paystack/Flutterwave
$reference = $_GET['reference'] ?? '';
$status = $_GET['status'] ?? '';

if ($status === 'success') {
    $pdo = new PDO("mysql:host=localhost;dbname=coconutventure_growers;charset=utf8mb4", 
                   "coconutventure_growers", "1^v1V&Ak{DIPL~Y.");
    
    // Get transaction
    $stmt = $pdo->prepare("SELECT w.user_id, t.amount FROM wallet_transactions t JOIN wallets w ON t.wallet_id = w.id WHERE t.reference = ?");
    $stmt->execute([$reference]);
    $tx = $stmt->fetch();
    
    if ($tx) {
        // Complete transaction
        $pdo->prepare("UPDATE wallet_transactions SET status = 'completed' WHERE reference = ?")->execute([$reference]);
        
        // Update wallet balance
        $pdo->prepare("UPDATE wallets SET balance = balance + ? WHERE user_id = ?")->execute([$tx['amount'], $tx['user_id']]);
    }
}

header('Location: /dashboard/wallet.php?status=' . $status);
exit;
?>