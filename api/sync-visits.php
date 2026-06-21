<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/field-management.php';
require_once __DIR__ . '/../lib/agronomy.php';

session_start();
$pdo = db();
$user = require_user_role($pdo, ['field_agent', 'admin']);
fm_ensure_schema($pdo);
agronomy_ensure_schema($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'POST method required'], 405);
}

$input = json_decode(file_get_contents('php://input') ?: '[]', true);
if (!is_array($input)) {
    json_response(['success' => false, 'error' => 'Invalid JSON'], 400);
}
$visits = isset($input['visits']) && is_array($input['visits']) ? $input['visits'] : $input;
$csrf = is_string($input['_csrf'] ?? null) ? (string) $input['_csrf'] : null;
if ($csrf !== null && !verify_csrf($csrf)) {
    json_response(['success' => false, 'error' => 'Invalid sync token'], 403);
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
    $results = [];
    foreach ($visits as $visit) {
        if (!is_array($visit) || empty($visit['grower_id'])) {
            if (is_array($visit) && !empty($visit['task_id'])) {
                try {
                    $pdo->beginTransaction();
                    $saved = fm_record_task_visit($pdo, $user, $visit + ['sync_source' => 'offline_sync']);
                    $pdo->commit();
                    $count++;
                    $results[] = [
                        'local_id' => (string) ($visit['local_id'] ?? $visit['client_visit_id'] ?? ''),
                        'task_id' => (int) $visit['task_id'],
                        'success' => true,
                        'server_visit_id' => $saved['visit_id'],
                        'duplicate' => $saved['duplicate'],
                    ];
                } catch (Throwable $itemError) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $results[] = [
                        'local_id' => (string) ($visit['local_id'] ?? $visit['client_visit_id'] ?? ''),
                        'task_id' => (int) $visit['task_id'],
                        'success' => false,
                        'error' => $itemError->getMessage(),
                    ];
                }
            }
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
        $results[] = [
            'local_id' => (string) ($visit['local_id'] ?? ''),
            'grower_id' => (int) $visit['grower_id'],
            'success' => true,
            'server_visit_id' => (int) $pdo->lastInsertId(),
        ];
    }

    json_response(['success' => true, 'synced' => $count, 'results' => $results]);
} catch (Throwable $e) {
    error_log('Sync visits API error: ' . $e->getMessage());
    json_response(['success' => false, 'error' => 'Unable to sync visits'], 500);
}
