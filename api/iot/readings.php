<?php
// api/iot/readings.php - Get sensor readings for dashboard
session_start();
header('Content-Type: application/json');

$farmId = intval($_GET['farm_id'] ?? 0);
if (!$farmId) {
    http_response_code(400);
    exit(json_encode(['error' => 'Farm ID required']));
}

// Verify ownership
$stmt = $pdo->prepare("SELECT id FROM applications WHERE id = ? AND user_id = ?");
$stmt->execute([$farmId, $_SESSION['user_id']]);
if (!$stmt->fetch()) {
    http_response_code(403);
    exit(json_encode(['error' => 'Unauthorized']));
}

// Get last 24 hours of data
$stmt = $pdo->prepare("
    SELECT sr.*, s.sensor_type, s.device_id
    FROM sensor_readings sr
    JOIN iot_sensors s ON sr.sensor_id = s.id
    WHERE s.farm_id = ? AND sr.reading_timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ORDER BY sr.reading_timestamp ASC
");
$stmt->execute([$farmId]);
$readings = $stmt->fetchAll();

echo json_encode(['success' => true, 'readings' => $readings]);
?>