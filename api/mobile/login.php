<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'POST method required'], 405);
}

$input = json_decode(file_get_contents('php://input') ?: '{}', true);
if (!is_array($input)) {
    json_response(['success' => false, 'error' => 'Invalid JSON'], 400);
}

$email = filter_var(trim((string) ($input['email'] ?? '')), FILTER_VALIDATE_EMAIL);
$password = (string) ($input['password'] ?? '');

if (!$email || $password === '') {
    json_response(['success' => false, 'error' => 'Email and password required'], 422);
}

try {
    $pdo = db();
    app_ensure_core_schema($pdo);

    $stmt = $pdo->prepare("SELECT id, name, email, password, role FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, (string) $user['password'])) {
        json_response(['success' => false, 'error' => 'Invalid credentials'], 401);
    }

    $token = (new Auth())->generateToken((int) $user['id'], (string) $user['role']);
    json_response([
        'success' => true,
        'token' => $token,
        'user' => [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
        ],
    ]);
} catch (Throwable $e) {
    error_log('Mobile login error: ' . $e->getMessage());
    json_response(['success' => false, 'error' => 'System error'], 500);
}
