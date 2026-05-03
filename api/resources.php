
<?php
header('Content-Type: application/json');

$pdo = new PDO("mysql:host=localhost;dbname=coconutventure_growers;charset=utf8mb4", 
               "coconutventure_growers", "1^v1V&Ak{DIPL~Y.");

$stmt = $pdo->query("SELECT id, title, file_path, category FROM resources ORDER BY created_at DESC");
echo json_encode($stmt->fetchAll());

// Only show offline-available resources to agents
$stmt = $pdo->query("SELECT * FROM resources WHERE offline_available = 1 ORDER BY created_at DESC");

?>