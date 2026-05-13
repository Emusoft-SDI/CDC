<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/nigeria-locations.php';

$state = trim((string) ($_GET['state'] ?? ''));
if ($state === '') {
    json_response(['success' => false, 'error' => 'State required'], 422);
}

try {
    $pdo = db();
    if (!app_table_exists($pdo, 'nigeria_states') || !app_table_exists($pdo, 'nigeria_lgas')) {
        json_response([]);
    }

    $stmt = $pdo->prepare("
        SELECT id, state_name, state_code
        FROM nigeria_states
        WHERE state_name = ? OR state_code = ?
        LIMIT 1
    ");
    $stmt->execute([$state, $state]);
    $stateRow = $stmt->fetch();
    if ($stateRow) {
        json_response(nigeria_ensure_lgas_for_state($pdo, (int) $stateRow['id'], (string) $stateRow['state_name'], (string) ($stateRow['state_code'] ?? '')));
    }

    $items = array_map(
        static fn(string $name): array => ['id' => 0, 'lga_name' => $name],
        nigeria_lgas_for_state($state)
    );
    json_response($items);
} catch (Throwable $e) {
    error_log('Get LGAs by state API error: ' . $e->getMessage());
    json_response([]);
}
