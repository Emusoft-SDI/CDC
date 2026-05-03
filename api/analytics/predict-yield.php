<?php
// api/analytics/predict-yield.php
session_start();
header('Content-Type: application/json');

require_once '../../lib/analytics/engine.php';

try {
    $engine = new AnalyticsEngine($pdo);
    $result = $engine->generateYieldPrediction($_GET['farm_id'], $_SESSION['user_id']);
    echo json_encode(['success' => true, 'prediction' => $result]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>