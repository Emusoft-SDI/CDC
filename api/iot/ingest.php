<?php
// api/iot/ingest.php - Secure IoT data ingestion
header('Content-Type: application/json');

// Verify API key (store in settings table)
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
$validKey = $pdo->query("SELECT value FROM settings WHERE key_name = 'iot_api_key'")->fetchColumn();

if ($apiKey !== $validKey) {
    http_response_code(401);
    exit(json_encode(['error' => 'Unauthorized']));
}

$input = json_decode(file_get_contents('php://input'), true);
$deviceId = $input['device_id'] ?? '';
$value = $input['value'] ?? null;
$unit = $input['unit'] ?? '';
$timestamp = $input['timestamp'] ?? date('Y-m-d H:i:s');

if (!$deviceId || !is_numeric($value)) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid data']));
}

try {
    // Find sensor
    $stmt = $pdo->prepare("SELECT id, farm_id FROM iot_sensors WHERE device_id = ? AND status = 'active'");
    $stmt->execute([$deviceId]);
    $sensor = $stmt->fetch();
    
    if (!$sensor) {
        // Auto-register new sensors (optional)
        if ($pdo->query("SELECT value FROM settings WHERE key_name = 'auto_register_sensors'")->fetchColumn()) {
            // Register sensor (implementation depends on your hardware setup)
            registerNewSensor($deviceId, $input['sensor_type'] ?? 'unknown');
        }
        http_response_code(404);
        exit(json_encode(['error' => 'Sensor not found']));
    }
    
    // Insert reading
    $pdo->prepare("
        INSERT INTO sensor_readings (sensor_id, reading_value, reading_unit, reading_timestamp, metadata)
        VALUES (?, ?, ?, ?, ?)
    ")->execute([
        $sensor['id'],
        $value,
        $unit,
        $timestamp,
        json_encode($input['metadata'] ?? [])
    ]);
    
    // Update last_reading
    $pdo->prepare("
        UPDATE iot_sensors 
        SET last_reading = JSON_OBJECT('value', ?, 'unit', ?, 'timestamp', ?), last_updated = NOW()
        WHERE id = ?
    ")->execute([$value, $unit, $timestamp, $sensor['id']]);
    
    echo json_encode(['success' => true, 'farm_id' => $sensor['farm_id']]);
    
} catch (Exception $e) {
    error_log("IoT ingestion error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal error']);
}

function registerNewSensor($deviceId, $sensorType) {
    global $pdo;
    // Implementation depends on your hardware provisioning system
    // For now, log for manual review
    error_log("New sensor detected: $deviceId ($sensorType)");
}
?>