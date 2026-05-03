<?php
// ussd-callback.php
require_once 'config.php';
require_once 'lib/ussd.php';

$ussd = new USSDPayment($pdo);

// Get callback data (format depends on provider)
$input = json_decode(file_get_contents('php://input'), true);

// Termii example format
$reference = $input['reference'] ?? '';
$status = $input['status'] ?? '';
$amount = $input['amount'] ?? 0;

if ($reference && $status) {
    $ussd->handleCallback($reference, $status, $amount);
}

http_response_code(200);
?>