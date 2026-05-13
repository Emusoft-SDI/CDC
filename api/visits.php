<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

session_start();
$pdo = db();
$user = require_user_role($pdo, ['field_agent', 'admin']);

try {
    $where = '';
    $params = [];
    if ($user['role'] === 'field_agent') {
        $where = 'WHERE fv.agent_id = ?';
        $params[] = $user['id'];
    }

    $stmt = $pdo->prepare("
        SELECT fv.*, a.name AS grower_name, a.location
        FROM field_visits fv
        JOIN applications a ON fv.grower_id = a.id
        {$where}
        ORDER BY fv.visited_at DESC
        LIMIT 500
    ");
    $stmt->execute($params);
    json_response(['success' => true, 'items' => $stmt->fetchAll()]);
} catch (Throwable $e) {
    error_log('Visits API error: ' . $e->getMessage());
    json_response(['success' => true, 'items' => []]);
}
