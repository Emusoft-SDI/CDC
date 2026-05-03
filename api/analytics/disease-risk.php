<?php
// api/analytics/disease-risk.php
session_start();
header('Content-Type: application/json');

require_once '../../lib/analytics/engine.php';

try {
    $engine = new AnalyticsEngine($pdo);
    $result = $engine->detectDiseaseRisk($_GET['farm_id'], $_SESSION['user_id']);
    echo json_encode(['success' => true, 'risk' => $result]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>