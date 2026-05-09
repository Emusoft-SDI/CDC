<?php
header('Content-Type: application/json');

$pdo = new PDO("mysql:host=localhost;dbname=natcodevcom_data;charset=utf8mb4", 
               "natcodevcom_data", "XC^#3)[;*xTcm&V9");

$stmt = $pdo->query("
    SELECT 
        a.app_ref,
        a.name,
        c.issued_at
    FROM certificates c
    JOIN applications a ON c.application_id = a.id
    WHERE a.confirmed = 1
    ORDER BY c.issued_at DESC
");
echo json_encode($stmt->fetchAll());
?>