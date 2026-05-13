<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../auth.php';

require_api_user();

try {
    $pdo = db();
    $stmt = $pdo->query("
        SELECT id, title, description, start_time, duration_minutes, is_free, price
        FROM webinars
        WHERE start_time > NOW()
        ORDER BY start_time ASC
        LIMIT 50
    ");
    json_response(['success' => true, 'items' => $stmt->fetchAll()]);
} catch (Throwable $e) {
    error_log('Webinars API error: ' . $e->getMessage());
    json_response(['success' => true, 'items' => []]);
}
