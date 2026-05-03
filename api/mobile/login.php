<?php
// api/mobile/login.php
header('Content-Type: application/json');
require_once '../../config.php';
require_once 'auth.php';

$input = json_decode(file_get_contents('php://input'), true);
$email = $input['email'] ?? '';
$password = $input['password'] ?? '';

if (!$email || !$password) {
    http_response_code(400);
    echo json_encode(['error' => 'Email and password required']);
    exit;
}

$stmt = $pdo->prepare("SELECT id, password, role FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if ($user && password_verify($password, $user['password'])) {
    $auth = new Auth();
    $token = $auth->generateToken($user['id'], $user['role']);
    
    echo json_encode([
        'success' => true,
        'token' => $token,
        'user' => [
            'id' => $user['id'],
            'role' => $user['role']
        ]
    ]);
} else {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid credentials']);
}
?>