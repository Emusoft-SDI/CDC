<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

session_start();
$pdo = db();
$user = require_user_role($pdo, ['grower', 'field_agent', 'admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'POST method required'], 405);
}

try {
    app_add_column_if_missing($pdo, 'users', 'terms_accepted', 'TINYINT(1) NOT NULL DEFAULT 0');
    app_add_column_if_missing($pdo, 'users', 'terms_accepted_at', 'DATETIME NULL');
    app_add_column_if_missing($pdo, 'users', 'terms_version', 'VARCHAR(20) NULL');

    $pdo->prepare("
        UPDATE users
        SET terms_accepted = 1, terms_accepted_at = NOW(), terms_version = '1.0'
        WHERE id = ?
    ")->execute([(int) $user['id']]);

    json_response(['success' => true]);
} catch (Throwable $e) {
    error_log('Accept terms API error: ' . $e->getMessage());
    json_response(['success' => false, 'error' => 'Unable to accept terms'], 500);
}
