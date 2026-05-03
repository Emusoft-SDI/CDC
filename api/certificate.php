<?php
header('Content-Type: application/json');

$pdo = new PDO("mysql:host=localhost;dbname=coconutventure_growers;charset=utf8mb4", 
               "coconutventure_growers", "1^v1V&Ak{DIPL~Y.");

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