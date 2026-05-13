<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config.php';

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'POST method required'], 405);
}

$apiKey = (string) ($_SERVER['HTTP_X_API_KEY'] ?? '');
$validKey = '';
if (app_table_exists($pdo, 'settings')) {
    $validKey = (string) $pdo->query("SELECT value FROM settings WHERE key_name = 'iot_api_key'")->fetchColumn();
}

if ($validKey === '' || !hash_equals($validKey, $apiKey)) {
    json_response(['success' => false, 'error' => 'Unauthorized'], 401);
}

$input = json_decode(file_get_contents('php://input') ?: '{}', true);
if (!is_array($input)) {
    json_response(['success' => false, 'error' => 'Invalid JSON'], 400);
}

$deviceId = trim((string) ($input['device_id'] ?? ''));
$value = $input['value'] ?? null;
$unit = trim((string) ($input['unit'] ?? ''));
$timestamp = date('Y-m-d H:i:s', strtotime((string) ($input['timestamp'] ?? 'now')) ?: time());

if ($deviceId === '' || !is_numeric($value)) {
    json_response(['success' => false, 'error' => 'Invalid data'], 422);
}

try {
    $stmt = $pdo->prepare("SELECT id, farm_id FROM iot_sensors WHERE device_id = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$deviceId]);
    $sensor = $stmt->fetch();

    if (!$sensor) {
        $autoRegister = app_table_exists($pdo, 'settings')
            && $pdo->query("SELECT value FROM settings WHERE key_name = 'auto_register_sensors'")->fetchColumn() === '1';
        if ($autoRegister) {
            error_log('New sensor detected: ' . $deviceId . ' (' . (string) ($input['sensor_type'] ?? 'unknown') . ')');
        }
        json_response(['success' => false, 'error' => 'Sensor not found'], 404);
    }

    $pdo->prepare("
        INSERT INTO sensor_readings (sensor_id, reading_value, reading_unit, reading_timestamp, metadata)
        VALUES (?, ?, ?, ?, ?)
    ")->execute([
        (int) $sensor['id'],
        (float) $value,
        $unit,
        $timestamp,
        json_encode($input['metadata'] ?? [], JSON_UNESCAPED_SLASHES),
    ]);

    $pdo->prepare("
        UPDATE iot_sensors
        SET last_reading = JSON_OBJECT('value', ?, 'unit', ?, 'timestamp', ?), last_updated = NOW()
        WHERE id = ?
    ")->execute([(float) $value, $unit, $timestamp, (int) $sensor['id']]);

    json_response(['success' => true, 'farm_id' => (int) $sensor['farm_id']]);
} catch (Throwable $e) {
    error_log('IoT ingestion error: ' . $e->getMessage());
    json_response(['success' => false, 'error' => 'Internal error'], 500);
}
