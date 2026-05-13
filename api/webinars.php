<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

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
    error_log('Public webinars API error: ' . $e->getMessage());
    json_response(['success' => true, 'items' => []]);
}
