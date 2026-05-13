<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

session_start();
$pdo = db();
$user = require_user_role($pdo, ['field_agent', 'admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'POST method required'], 405);
}

$input = json_decode(file_get_contents('php://input') ?: '[]', true);
if (!is_array($input)) {
    json_response(['success' => false, 'error' => 'Invalid JSON'], 400);
}

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS field_visits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            grower_id INT NOT NULL,
            agent_id INT NOT NULL,
            notes TEXT NULL,
            visited_at DATETIME NOT NULL,
            latitude DECIMAL(10,7) NULL,
            longitude DECIMAL(10,7) NULL,
            accuracy DECIMAL(10,2) NULL,
            location_source VARCHAR(30) NOT NULL DEFAULT 'manual',
            synced_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_field_visits_agent_time (agent_id, visited_at),
            INDEX idx_field_visits_grower_time (grower_id, visited_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $stmt = $pdo->prepare("
        INSERT INTO field_visits
            (grower_id, agent_id, notes, visited_at, latitude, longitude, accuracy, location_source)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $count = 0;
    foreach ($input as $visit) {
        if (!is_array($visit) || empty($visit['grower_id'])) {
            continue;
        }

        $stmt->execute([
            (int) $visit['grower_id'],
            (int) $user['id'],
            trim((string) ($visit['notes'] ?? '')),
            date('Y-m-d H:i:s', strtotime((string) ($visit['timestamp'] ?? 'now')) ?: time()),
            isset($visit['latitude']) ? (float) $visit['latitude'] : null,
            isset($visit['longitude']) ? (float) $visit['longitude'] : null,
            isset($visit['accuracy']) ? (float) $visit['accuracy'] : null,
            (string) ($visit['source'] ?? 'manual'),
        ]);
        $count++;
    }

    json_response(['success' => true, 'synced' => $count]);
} catch (Throwable $e) {
    error_log('Sync visits API error: ' . $e->getMessage());
    json_response(['success' => false, 'error' => 'Unable to sync visits'], 500);
}
