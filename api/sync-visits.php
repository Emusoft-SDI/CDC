<?php
session_start();
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$pdo = new PDO("mysql:host=localhost;dbname=coconutventure_growers;charset=utf8mb4", 
               "coconutventure_growers", "1^v1V&Ak{DIPL~Y.");

foreach ($input as $visit) {
    $stmt = $pdo->prepare("
        INSERT INTO field_visits 
        (grower_id, agent_id, notes, visited_at, latitude, longitude, accuracy, location_source) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $visit['grower_id'],
        $_SESSION['user_id'],
        $visit['notes'] ?? '',
        $visit['timestamp'] ?? date('Y-m-d H:i:s'),
        $visit['latitude'] ?? null,
        $visit['longitude'] ?? null,
        $visit['accuracy'] ?? null,
        $visit['source'] ?? 'manual'
    ]);
}

echo json_encode(['success' => true]);
?>