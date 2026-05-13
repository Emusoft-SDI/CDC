<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/ussd.php';

session_start();
$pdo = db();
$user = require_user_role($pdo, ['grower', 'admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'POST method required'], 405);
}

$amount = (float) ($_POST['amount'] ?? 0);
$phone = preg_replace('/[^0-9]/', '', (string) ($_POST['phone'] ?? ''));
if ($amount < 100 || $phone === '') {
    json_response(['success' => false, 'error' => 'Invalid amount or phone'], 422);
}

try {
    $ussd = new USSDPayment($pdo);
    $reference = $ussd->initiatePayment((int) $user['id'], $amount, $phone);
    json_response(['success' => true, 'reference' => $reference]);
} catch (Throwable $e) {
    error_log('USSD error: ' . $e->getMessage());
    json_response(['success' => false, 'error' => 'Payment initiation failed'], 500);
}
