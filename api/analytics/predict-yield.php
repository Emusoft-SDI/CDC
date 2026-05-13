<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/analytics/engine.php';

session_start();
$pdo = db();
$user = require_user_role($pdo, ['grower', 'field_agent', 'admin']);

$farmId = filter_input(INPUT_GET, 'farm_id', FILTER_VALIDATE_INT);
if (!$farmId) {
    json_response(['success' => false, 'error' => 'Farm ID required'], 422);
}

try {
    $engine = new AnalyticsEngine($pdo);
    $result = $engine->generateYieldPrediction($farmId, (int) $user['id']);
    json_response(['success' => true, 'prediction' => $result]);
} catch (Throwable $e) {
    json_response(['success' => false, 'error' => $e->getMessage()], 400);
}
