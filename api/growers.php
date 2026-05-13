<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

session_start();
$pdo = db();
require_user_role($pdo, ['field_agent', 'admin']);

try {
    app_ensure_core_schema($pdo);

    $stmt = $pdo->prepare("
        SELECT a.id, a.name, a.location, a.farm_size
        FROM applications a
        WHERE a.confirmed = 1
        ORDER BY a.created_at DESC
    ");
    $stmt->execute();

    json_response(['success' => true, 'items' => $stmt->fetchAll()]);
} catch (Throwable $e) {
    error_log('Growers API error: ' . $e->getMessage());
    json_response(['success' => true, 'items' => []]);
}
