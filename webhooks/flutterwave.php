<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/monnify.php';

$pdo = db();
wallet_ensure_schema($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'POST method required'], 405);
}

if (!app_check_rate_limit('flutterwave_webhook', 60, 60)) {
    json_response(['success' => false, 'error' => 'Too many requests'], 429);
}

$secret = (string) app_env('FLUTTERWAVE_SECRET_HASH', app_env('FLUTTERWAVE_SECRET_KEY', ''));
if ($secret === '') {
    error_log('Flutterwave webhook rejected because FLUTTERWAVE_SECRET_HASH is not configured.');
    json_response(['success' => false, 'error' => 'Webhook not configured'], 503);
}

$signature = (string) ($_SERVER['HTTP_VERIF_HASH'] ?? '');
if ($signature === '' || !hash_equals($secret, $signature)) {
    json_response(['success' => false, 'error' => 'Invalid signature'], 401);
}

$raw = file_get_contents('php://input') ?: '';
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    json_response(['success' => false, 'error' => 'Invalid JSON'], 400);
}

if (!in_array((string) ($payload['event'] ?? ''), ['charge.completed', 'charge.successful'], true)) {
    json_response(['success' => true, 'ignored' => true]);
}

$data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
$reference = trim((string) ($data['tx_ref'] ?? $data['reference'] ?? ''));
$amount = (float) ($data['amount'] ?? 0);
$meta = is_array($data['meta'] ?? null) ? $data['meta'] : [];
$userId = (int) ($meta['user_id'] ?? 0);
$status = strtolower((string) ($data['status'] ?? ''));

if ($status !== '' && !in_array($status, ['successful', 'success'], true)) {
    json_response(['success' => true, 'ignored' => true, 'status' => $status]);
}
if ($reference === '' || $amount <= 0 || $userId <= 0) {
    json_response(['success' => true, 'ignored' => true, 'reason' => 'Missing wallet funding metadata']);
}

// Only credit a pre-registered pending funding request, and only for the
// amount that was requested. This blocks underpayment and unknown references.
$pendingStmt = $pdo->prepare("SELECT id, user_id, amount FROM wallet_transactions WHERE reference = ? AND status = 'pending' LIMIT 1");
$pendingStmt->execute([$reference]);
$pending = $pendingStmt->fetch();
if (!$pending || (int) $pending['user_id'] !== $userId) {
    error_log('Flutterwave webhook ignored: no pending wallet funding for reference ' . $reference);
    json_response(['success' => true, 'ignored' => true, 'reason' => 'No pending wallet funding for this reference']);
}
if ($amount + 0.01 < (float) $pending['amount']) {
    error_log('Underpaid Flutterwave webhook ignored for ' . $reference . ': paid=' . $amount . ', expected=' . $pending['amount']);
    json_response(['success' => true, 'ignored' => true, 'reason' => 'Wallet funding amount is incomplete']);
}

try {
    $credit = wallet_credit_once($pdo, $userId, (float) $pending['amount'], 'Wallet funding via Flutterwave', $reference, 'flutterwave', (string) ($data['id'] ?? $reference), $payload);
    json_response(['success' => true, 'credit' => $credit]);
} catch (Throwable $e) {
    error_log('Flutterwave webhook processing failed: ' . $e->getMessage());
    json_response(['success' => false, 'error' => 'Webhook processing failed'], 500);
}