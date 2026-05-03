<?php
// webhooks/paystack.php
require_once '../config.php';
require_once '../lib/payments.php';

$payment = new PaymentGateway($pdo);

// Verify Paystack signature
$signature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';
$body = file_get_contents('php://input');

if (!hash_equals($signature, hash_hmac('sha512', $body, 'YOUR_PAYSTACK_SECRET'))) {
    http_response_code(400);
    exit('Invalid signature');
}

$data = json_decode($body, true);
if ($data['event'] === 'charge.success') {
    $reference = $data['data']['reference'];
    $amount = $data['data']['amount'] / 100; // Convert kobo to Naira
    $userId = $data['data']['metadata']['user_id'] ?? null;
    
    if ($userId) {
        $payment->processPayment($userId, $amount, $reference, 'Wallet funding via Paystack');
    }
}

http_response_code(200);
?>