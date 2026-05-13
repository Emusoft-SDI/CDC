<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/assignments.php';

session_start();
$pdo = db();
if (!admin_session_is_authenticated($pdo)) {
    json_response(['success' => false, 'error' => 'Forbidden'], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'POST method required'], 405);
}

$input = json_decode(file_get_contents('php://input') ?: '{}', true);
if (!is_array($input)) {
    json_response(['success' => false, 'error' => 'Invalid JSON'], 400);
}

$criteria = is_array($input['criteria'] ?? null) ? $input['criteria'] : [];

try {
    [$sql, $params] = assignment_grower_query($pdo, $criteria, 50);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $growers = $stmt->fetchAll();

    json_response([
        'success' => true,
        'count' => count($growers),
        'growers' => $growers,
    ]);
} catch (Throwable $e) {
    error_log('Preview assignment API error: ' . $e->getMessage());
    json_response(['success' => false, 'error' => 'Unable to preview assignment'], 500);
}
