<?php
header('Content-Type: application/json');

$type = $_GET['type'] ?? '';
$pdo = new PDO("mysql:host=localhost;dbname=natcodevcom_data", "user", "password");

switch ($type) {
    case 'states':
        $stmt = $pdo->query("SELECT id, state_name FROM nigeria_states ORDER BY state_name");
        echo json_encode($stmt->fetchAll());
        break;
        
    case 'lgas':
        $stmt = $pdo->query("SELECT id, lga_name, state_id FROM nigeria_lgas ORDER BY lga_name");
        echo json_encode($stmt->fetchAll());
        break;
        
    case 'streets':
        // Only return streets for metropolitan areas to save space
        $stmt = $pdo->query("
            SELECT id, street_name, area_name, lga_id 
            FROM nigeria_streets 
            WHERE city_type = 'metropolitan' 
            ORDER BY street_name
        ");
        echo json_encode($stmt->fetchAll());
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid type']);
}
?>