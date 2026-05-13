<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

session_start();
$pdo = db();
$user = require_user_role($pdo, ['field_agent', 'admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'POST method required'], 405);
}

$input = json_decode(file_get_contents('php://input') ?: '{}', true);
if (!is_array($input) || !isset($input['latitude'], $input['longitude'])) {
    json_response(['success' => false, 'error' => 'Invalid data'], 400);
}

$lat = filter_var($input['latitude'], FILTER_VALIDATE_FLOAT);
$lng = filter_var($input['longitude'], FILTER_VALIDATE_FLOAT);
if ($lat === false || $lng === false || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    json_response(['success' => false, 'error' => 'Invalid coordinates'], 422);
}

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS agent_locations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            agent_id INT NOT NULL,
            latitude DECIMAL(10,7) NOT NULL,
            longitude DECIMAL(10,7) NOT NULL,
            accuracy DECIMAL(10,2) NULL,
            battery_level INT NULL,
            timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_agent_locations_agent_time (agent_id, timestamp)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $stmt = $pdo->prepare("
        INSERT INTO agent_locations (agent_id, latitude, longitude, accuracy, battery_level)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        (int) $user['id'],
        $lat,
        $lng,
        isset($input['accuracy']) ? (float) $input['accuracy'] : null,
        isset($input['battery_level']) ? (int) $input['battery_level'] : null,
    ]);

    json_response(['success' => true]);
} catch (Throwable $e) {
    error_log('Track location error: ' . $e->getMessage());
    json_response(['success' => false, 'error' => 'Unable to save location'], 500);
}
