
<?php
header('Content-Type: application/json');

$pdo = new PDO("mysql:host=localhost;dbname=natcodevcom_data;charset=utf8mb4", 
               "natcodevcom_data", "XC^#3)[;*xTcm&V9");

$stmt = $pdo->query("SELECT id, title, file_path, category FROM resources ORDER BY created_at DESC");
echo json_encode($stmt->fetchAll());

// Only show offline-available resources to agents
$stmt = $pdo->query("SELECT * FROM resources WHERE offline_available = 1 ORDER BY created_at DESC");

?>