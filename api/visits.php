<?php
session_start();
header('Content-Type: application/json');

$pdo = new PDO("mysql:host=localhost;dbname=natcodevcom_data;charset=utf8mb4", 
               "natcodevcom_data", "XC^#3)[;*xTcm&V9");

// Get all visits with grower info
$stmt = $pdo->prepare("
    SELECT 
        fv.*,
        a.name as grower_name,
        a.location
    FROM field_visits fv
    JOIN applications a ON fv.grower_id = a.id
    ORDER BY fv.visited_at DESC
");
$stmt->execute();
echo json_encode($stmt->fetchAll());
?>