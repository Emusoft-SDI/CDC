<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/monnify.php';

session_start();
$pdo = db();
$user = current_user($pdo);
if (!$user) {
    json_response(['success' => false, 'error' => 'Login required'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'POST method required'], 405);
}

if (!verify_csrf($_POST['_csrf'] ?? null)) {
    json_response(['success' => false, 'error' => 'Invalid request token'], 403);
}

$userId = (int) ($user['id'] ?? 0);
if (!app_check_rate_limit('wallet_fund_' . $userId, 10, 600)) {
    json_response(['success' => false, 'error' => 'Too many funding attempts. Please wait and try again.'], 429);
}

$amount = round((float) ($_POST['amount'] ?? 0), 2);
$maxFundingAmount = max(1000, (float) app_env('WALLET_FUNDING_MAX_AMOUNT', '5000000'));
if ($amount < 100) {
    json_response(['success' => false, 'error' => 'Minimum wallet funding amount is NGN 100.'], 422);
}
if ($amount > $maxFundingAmount) {
    json_response(['success' => false, 'error' => 'Amount exceeds the platform wallet funding limit.'], 422);
}

$returnUrl = !empty($_POST['return_url']) ? (string) $_POST['return_url'] : null;

try {
    $result = monnify_initialize_wallet_funding($pdo, $user, $amount, $returnUrl);
    json_response($result, $result['success'] ? 200 : 422);
} catch (Throwable $e) {
    error_log('Wallet error: ' . $e->getMessage());
    json_response(['success' => false, 'error' => 'System error'], 500);
}