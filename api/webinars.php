<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

try {
    $pdo = db();
    if (app_table_exists($pdo, 'webinars')) {
        foreach ([
            'delivery_type' => "VARCHAR(40) NOT NULL DEFAULT 'live_zoom'",
            'delivery_url' => "VARCHAR(500) NULL",
            'delivery_instructions' => "TEXT NULL",
        ] as $column => $definition) {
            app_add_column_if_missing($pdo, 'webinars', $column, $definition);
        }
    }
    $stmt = $pdo->query("
        SELECT id, title, description, start_time, duration_minutes, is_free, price, category, certification_required,
               delivery_type, COALESCE(delivery_url, zoom_link) AS delivery_url, delivery_instructions
        FROM webinars
        WHERE start_time > NOW() AND COALESCE(status, 'active') = 'active'
        ORDER BY start_time ASC
        LIMIT 50
    ");
    json_response(['success' => true, 'items' => $stmt->fetchAll()]);
} catch (Throwable $e) {
    error_log('Public webinars API error: ' . $e->getMessage());
    json_response(['success' => true, 'items' => []]);
}
