<?php
// api/ussd-payment.php
session_start();
header('Content-Type: application/json');

$amount = floatval($_POST['amount'] ?? 0);
$phone = $_POST['phone'] ?? '';

if ($amount < 100 || !$phone) {
    echo json_encode(['error' => 'Invalid amount or phone']);
    exit;
}

require_once '../lib/ussd.php';
$ussd = new USSDPayment($pdo);

try {
    $reference = $ussd->initiatePayment($_SESSION['user_id'], $amount, $phone);
    echo json_encode(['success' => true, 'reference' => $reference]);
} catch (Exception $e) {
    error_log("USSD error: " . $e->getMessage());
    echo json_encode(['error' => 'Payment initiation failed']);
}
?>