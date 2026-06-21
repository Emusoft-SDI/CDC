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

$amount = floatval($_POST['amount'] ?? 0);
if ($amount <= 0) {
    json_response(['success' => false, 'error' => 'Invalid amount'], 422);
}

$returnUrl = !empty($_POST['return_url']) ? (string) $_POST['return_url'] : null;

try {
    $result = monnify_initialize_wallet_funding($pdo, $user, $amount, $returnUrl);
    json_response($result, $result['success'] ? 200 : 422);
} catch (Throwable $e) {
    error_log('Wallet error: ' . $e->getMessage());
    json_response(['success' => false, 'error' => 'System error'], 500);
}
