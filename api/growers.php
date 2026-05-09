<?php
session_start();
header('Content-Type: application/json');

// Verify field agent role
$pdo = new PDO("mysql:host=localhost;dbname=natcodevcom_data;charset=utf8mb4", 
               "natcodevcom_data", "XC^#3)[;*xTcm&V9");

$stmt = $pdo->prepare("
    SELECT a.id, a.name, a.location, a.farm_size 
    FROM applications a 
    WHERE a.confirmed = 1
    ORDER BY a.created_at DESC
");
$stmt->execute();
echo json_encode($stmt->fetchAll());
?>