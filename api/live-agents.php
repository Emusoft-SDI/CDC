<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

session_start();
$pdo = db();
if (!admin_session_is_authenticated($pdo)) {
    json_response(['success' => false, 'error' => 'Forbidden'], 403);
}

try {
    $stmt = $pdo->prepare("
        SELECT u.id, u.name, al.latitude, al.longitude, al.battery_level, al.timestamp
        FROM agent_locations al
        JOIN users u ON al.agent_id = u.id
        WHERE al.timestamp > NOW() - INTERVAL 5 MINUTE
          AND u.role = 'field_agent'
        ORDER BY al.timestamp DESC
    ");
    $stmt->execute();

    $agents = [];
    foreach ($stmt->fetchAll() as $row) {
        if (!isset($agents[$row['id']])) {
            $agents[$row['id']] = $row;
        }
    }

    json_response(['success' => true, 'items' => array_values($agents)]);
} catch (Throwable $e) {
    error_log('Live agents API error: ' . $e->getMessage());
    json_response(['success' => true, 'items' => []]);
}
