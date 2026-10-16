<?php
// ussd-callback.php
require_once 'config.php';
require_once 'lib/ussd.php';

$pdo = db();
$ussd = new USSDPayment($pdo);

$raw = file_get_contents('php://input') ?: '';
$signature = (string) ($_SERVER['HTTP_X_TERMII_SIGNATURE'] ?? $_SERVER['HTTP_X_USSD_SIGNATURE'] ?? '');

if (!$ussd->verifyCallbackSignature($raw, $signature)) {
    json_response(['success' => false, 'error' => 'Invalid signature'], 401);
}

$input = json_decode($raw, true);

// Termii example format
$reference = is_array($input) ? (string) ($input['reference'] ?? '') : '';
$status = is_array($input) ? (string) ($input['status'] ?? '') : '';
$amount = is_array($input) ? (float) ($input['amount'] ?? 0) : 0;

if ($reference !== '' && $status !== '') {
    json_response($ussd->handleCallback($reference, $status, $amount));
}

json_response(['success' => true, 'ignored' => true]);
