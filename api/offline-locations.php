<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

$type = (string) ($_GET['type'] ?? '');
$pdo = db();

try {
    switch ($type) {
        case 'states':
            $stmt = $pdo->query("SELECT id, state_name FROM nigeria_states ORDER BY state_name");
            json_response(['success' => true, 'items' => $stmt->fetchAll()]);

        case 'lgas':
            $stmt = $pdo->query("SELECT id, lga_name, state_id FROM nigeria_lgas ORDER BY lga_name");
            json_response(['success' => true, 'items' => $stmt->fetchAll()]);

        case 'streets':
            $stmt = $pdo->query("
                SELECT id, street_name, area_name, lga_id
                FROM nigeria_streets
                WHERE city_type = 'metropolitan'
                ORDER BY street_name
            ");
            json_response(['success' => true, 'items' => $stmt->fetchAll()]);

        default:
            json_response(['success' => false, 'error' => 'Invalid type'], 422);
    }
} catch (Throwable $e) {
    error_log('Offline locations API error: ' . $e->getMessage());
    json_response(['success' => true, 'items' => []]);
}
