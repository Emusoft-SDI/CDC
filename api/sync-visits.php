<?php
session_start();
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$pdo = new PDO("mysql:host=localhost;dbname=natcodevcom_data;charset=utf8mb4", 
               "natcodevcom_data", "XC^#3)[;*xTcm&V9");

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