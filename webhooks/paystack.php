<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/monnify.php';

$pdo = db();
wallet_ensure_schema($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'POST method required'], 405);
}

if (!app_check_rate_limit('paystack_webhook', 60, 60)) {
    json_response(['success' => false, 'error' => 'Too many requests'], 429);
}

$secret = paystack_env('PAYSTACK_SECRET_KEY');
if ($secret === '') {
    error_log('Paystack webhook rejected because PAYSTACK_SECRET_KEY is not configured.');
    json_response(['success' => false, 'error' => 'Webhook not configured'], 503);
}

$raw = file_get_contents('php://input') ?: '';
$signature = (string) ($_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '');
$expected = hash_hmac('sha512', $raw, $secret);
if ($signature === '' || !hash_equals($expected, $signature)) {
    json_response(['success' => false, 'error' => 'Invalid signature'], 401);
}

$payload = json_decode($raw, true);
if (!is_array($payload)) {
    json_response(['success' => false, 'error' => 'Invalid JSON'], 400);
}

if ((string) ($payload['event'] ?? '') !== 'charge.success') {
    json_response(['success' => true, 'ignored' => true]);
}

$data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
$reference = trim((string) ($data['reference'] ?? ''));
$amount = ((float) ($data['amount'] ?? 0)) / 100;
$metadata = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];
$userId = (int) ($metadata['user_id'] ?? 0);

if ($reference === '' || $amount <= 0 || $userId <= 0) {
    json_response(['success' => true, 'ignored' => true, 'reason' => 'Missing wallet funding metadata']);
}

try {
    $credit = wallet_credit_once($pdo, $userId, round($amount, 2), $reference, 'Wallet funding via Paystack', 'paystack', $reference, $payload);
    json_response(['success' => true, 'credit' => $credit]);
} catch (Throwable $e) {
    error_log('Paystack webhook processing failed: ' . $e->getMessage());
    json_response(['success' => false, 'error' => 'Webhook processing failed'], 500);
}