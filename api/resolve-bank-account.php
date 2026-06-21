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

$result = wallet_resolve_payout_account(
    (string) ($_POST['provider'] ?? 'monnify'),
    (string) ($_POST['account_number'] ?? ''),
    (string) ($_POST['bank_code'] ?? '')
);
json_response($result, $result['success'] ? 200 : 422);
