<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'POST method required'], 405);
}

$user = require_api_user();
$input = json_decode(file_get_contents('php://input') ?: '{}', true);
if (!is_array($input)) {
    json_response(['success' => false, 'error' => 'Invalid JSON'], 400);
}

$productId = (int) ($input['product_id'] ?? 0);
$amount = (float) ($input['amount'] ?? 0);
if ($productId <= 0 || $amount <= 0) {
    json_response(['success' => false, 'error' => 'Invalid purchase details'], 422);
}

try {
    $pdo = db();
    $reference = 'PUR-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS purchases (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            product_id INT NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            reference VARCHAR(80) NOT NULL UNIQUE,
            status VARCHAR(30) NOT NULL DEFAULT 'pending',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_purchases_user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $stmt = $pdo->prepare("INSERT INTO purchases (user_id, product_id, amount, reference) VALUES (?, ?, ?, ?)");
    $stmt->execute([(int) $user['user_id'], $productId, $amount, $reference]);

    json_response(['success' => true, 'reference' => $reference, 'status' => 'pending']);
} catch (Throwable $e) {
    error_log('Mobile purchase error: ' . $e->getMessage());
    json_response(['success' => false, 'error' => 'Unable to create purchase'], 500);
}
