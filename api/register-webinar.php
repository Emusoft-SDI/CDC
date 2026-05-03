<?php
session_start();
$webinarId = intval($_POST['webinar_id'] ?? 0);

// Check if free
$stmt = $pdo->prepare("SELECT is_free, price FROM webinars WHERE id = ?");
$stmt->execute([$webinarId]);
$webinar = $stmt->fetch();

if (!$webinar) exit(json_encode(['error' => 'Webinar not found']));

if ($webinar['is_free']) {
    // Free registration
    $pdo->prepare("INSERT IGNORE INTO webinar_registrations (webinar_id, user_id, payment_status) VALUES (?, ?, 'free')")->execute([$webinarId, $_SESSION['user_id']]);
    echo json_encode(['success' => true, 'message' => 'Registered successfully!']);
} else {
    // Check wallet balance
    $stmt = $pdo->prepare("SELECT w.balance FROM wallets w WHERE w.user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $balance = $stmt->fetchColumn();
    
    if ($balance >= $webinar['price']) {
        // Deduct payment
        $pdo->prepare("UPDATE wallets SET balance = balance - ? WHERE user_id = ?")->execute([$webinar['price'], $_SESSION['user_id']]);
        
        // Register
        $pdo->prepare("INSERT INTO webinar_registrations (webinar_id, user_id, payment_status) VALUES (?, ?, 'paid')")->execute([$webinarId, $_SESSION['user_id']]);
        echo json_encode(['success' => true, 'message' => 'Payment processed! You\'re registered.']);
    } else {
        echo json_encode(['error' => 'Insufficient balance. Fund your wallet first.']);
    }
}
?>