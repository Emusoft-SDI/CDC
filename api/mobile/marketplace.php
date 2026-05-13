<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../auth.php';

require_api_user();

try {
    $pdo = db();
    $stmt = $pdo->query("
        SELECT id, title, description, price, category
        FROM marketplace_items
        WHERE is_active = 1
        ORDER BY created_at DESC
        LIMIT 100
    ");
    json_response(['success' => true, 'items' => $stmt->fetchAll()]);
} catch (Throwable $e) {
    error_log('Marketplace API error: ' . $e->getMessage());
    json_response(['success' => true, 'items' => []]);
}
