<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config.php';

session_start();
$pdo = db();
$user = require_user_role($pdo, ['grower', 'field_agent', 'admin']);

$farmId = filter_input(INPUT_GET, 'farm_id', FILTER_VALIDATE_INT);
if (!$farmId) {
    json_response(['success' => false, 'error' => 'Farm ID required'], 422);
}

try {
    if (($user['role'] ?? '') === 'grower') {
        $stmt = $pdo->prepare("
            SELECT a.id
            FROM applications a
            JOIN users u ON u.application_id = a.id
            WHERE a.id = ? AND u.id = ?
            LIMIT 1
        ");
        $stmt->execute([$farmId, (int) $user['id']]);
        if (!$stmt->fetch()) {
            json_response(['success' => false, 'error' => 'Unauthorized'], 403);
        }
    }

    $stmt = $pdo->prepare("
        SELECT sr.*, s.sensor_type, s.device_id
        FROM sensor_readings sr
        JOIN iot_sensors s ON sr.sensor_id = s.id
        WHERE s.farm_id = ? AND sr.reading_timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY sr.reading_timestamp ASC
    ");
    $stmt->execute([$farmId]);

    json_response(['success' => true, 'readings' => $stmt->fetchAll()]);
} catch (Throwable $e) {
    error_log('IoT readings API error: ' . $e->getMessage());
    json_response(['success' => true, 'readings' => []]);
}
