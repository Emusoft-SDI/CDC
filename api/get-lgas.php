<?php
header('Content-Type: application/json');

$stateId = intval($_GET['state_id'] ?? 0);
if (!$stateId) {
    http_response_code(400);
    exit(json_encode(['error' => 'State ID required']));
}

$pdo = new PDO("mysql:host=localhost;dbname=coconutventure_growers", "user", "password");
$stmt = $pdo->prepare("SELECT id, lga_name FROM nigeria_lgas WHERE state_id = ? ORDER BY lga_name");
$stmt->execute([$stateId]);
$lgas = $stmt->fetchAll();

echo json_encode($lgas);
?>