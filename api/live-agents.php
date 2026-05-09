<?php
session_start();
header('Content-Type: application/json');

// Only admins can access
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    http_response_code(403);
    exit;
}

$pdo = new PDO("mysql:host=localhost;dbname=natcodevcom_data;charset=utf8mb4", 
               "natcodevcom_data", "XC^#3)[;*xTcm&V9");

// Get agents active in last 5 minutes
$stmt = $pdo->prepare("
    SELECT 
        u.id,
        u.name,
        al.latitude,
        al.longitude,
        al.battery_level,
        al.timestamp
    FROM agent_locations al
    JOIN users u ON al.agent_id = u.id
    WHERE al.timestamp > NOW() - INTERVAL 5 MINUTE
    AND u.role = 'field_agent'
    ORDER BY al.timestamp DESC
");
$stmt->execute();

// Remove duplicates (keep latest per agent)
$agents = [];
foreach ($stmt->fetchAll() as $row) {
    if (!isset($agents[$row['id']])) {
        $agents[$row['id']] = $row;
    }
}

echo json_encode(array_values($agents));
?>