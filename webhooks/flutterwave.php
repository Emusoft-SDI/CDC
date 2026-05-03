<?php
// webhooks/flutterwave.php
require_once '../config.php';
require_once '../lib/payments.php';

$payment = new PaymentGateway($pdo);

// Verify Flutterwave signature
$signature = $_SERVER['HTTP_VERIF_HASH'] ?? '';
$body = file_get_contents('php://input');

if ($signature !== hash('sha256', $body . 'YOUR_FLUTTERWAVE_SECRET')) {
    http_response_code(400);
    exit('Invalid signature');
}

$data = json_decode($body, true);
if ($data['event'] === 'charge.completed') {
    $tx_ref = $data['data']['tx_ref'];
    $amount = $data['data']['amount'];
    $userId = $data['data']['meta']['user_id'] ?? null;
    
    if ($userId) {
        $payment->processPayment($userId, $amount, $tx_ref, 'Wallet funding via Flutterwave');
    }
}

http_response_code(200);
?>