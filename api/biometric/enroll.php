<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config.php';

session_start();
$pdo = db();
$user = require_user_role($pdo, ['grower', 'field_agent', 'admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'POST method required'], 405);
}

$input = json_decode(file_get_contents('php://input') ?: '{}', true);
if (!is_array($input)) {
    $input = $_POST;
}

$credential = $input['credential'] ?? [];
$clientData = $input['clientData'] ?? [];
if (!$credential || !$clientData) {
    json_response(['success' => false, 'error' => 'Credential and client data required'], 422);
}

try {
    app_add_column_if_missing($pdo, 'users', 'biometric_enrolled', 'TINYINT(1) NOT NULL DEFAULT 0');
    app_add_column_if_missing($pdo, 'users', 'biometric_template', 'TEXT NULL');
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS biometric_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            action VARCHAR(30) NOT NULL,
            success TINYINT(1) NOT NULL DEFAULT 0,
            ip_address VARCHAR(45) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_biometric_logs_user (user_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->prepare("UPDATE users SET biometric_enrolled = 1, biometric_template = ? WHERE id = ?")
        ->execute([json_encode(['credential' => $credential, 'clientData' => $clientData], JSON_UNESCAPED_SLASHES), (int) $user['id']]);

    $pdo->prepare("INSERT INTO biometric_logs (user_id, action, success, ip_address) VALUES (?, 'enroll', 1, ?)")
        ->execute([(int) $user['id'], $_SERVER['REMOTE_ADDR'] ?? null]);

    json_response(['success' => true]);
} catch (Throwable $e) {
    error_log('Biometric enrollment API error: ' . $e->getMessage());
    json_response(['success' => false, 'error' => 'Enrollment failed'], 500);
}
