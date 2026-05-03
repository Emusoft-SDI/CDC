<?php
// api/mobile/marketplace.php
header('Content-Type: application/json');
require_once '../../config.php';
require_once 'auth.php';

// Get token from header
$headers = getallheaders();
$token = str_replace('Bearer ', '', $headers['Authorization'] ?? '');

if (!$token) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$auth = new Auth();
$userData = $auth->validateToken($token);

if (!$userData) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid token']);
    exit;
}

// Fetch marketplace items
$stmt = $pdo->prepare("
    SELECT id, title, description, price, category 
    FROM marketplace_items 
    WHERE is_active = 1 
    ORDER BY created_at DESC
");
$stmt->execute();
$items = $stmt->fetchAll();

echo json_encode(['success' => true, 'items' => $items]);
?>