<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/nigeria-locations.php';

$stateId = filter_input(INPUT_GET, 'state_id', FILTER_VALIDATE_INT);
if (!$stateId) {
    json_response(['success' => false, 'error' => 'State ID required'], 422);
}

try {
    $pdo = db();
    $stateStmt = $pdo->prepare("SELECT id, state_name, state_code FROM nigeria_states WHERE id = ? LIMIT 1");
    $stateStmt->execute([$stateId]);
    $state = $stateStmt->fetch();
    if (!$state) {
        json_response(['success' => true, 'items' => []]);
    }

    $rows = nigeria_ensure_lgas_for_state($pdo, (int) $state['id'], (string) $state['state_name'], (string) ($state['state_code'] ?? ''));
    header('Cache-Control: public, max-age=86400');
    json_response(['success' => true, 'items' => $rows]);
} catch (Throwable $e) {
    error_log('Get LGAs API error: ' . $e->getMessage());
    json_response(['success' => true, 'items' => []]);
}
