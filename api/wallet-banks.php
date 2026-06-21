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

$provider = strtolower(trim((string) ($_GET['provider'] ?? 'monnify')));
$result = wallet_payout_banks($provider);
json_response($result, $result['success'] ? 200 : 422);
