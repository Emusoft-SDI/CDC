<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

session_start();
$pdo = db();
$user = require_user_role($pdo, ['grower', 'field_agent', 'admin']);

try {
    $accepted = false;
    if (app_column_exists($pdo, 'users', 'terms_accepted')) {
        $stmt = $pdo->prepare("SELECT terms_accepted FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([(int) $user['id']]);
        $accepted = (bool) $stmt->fetchColumn();
    }

    json_response(['success' => true, 'accepted' => $accepted]);
} catch (Throwable $e) {
    error_log('Check terms API error: ' . $e->getMessage());
    json_response(['success' => false, 'error' => 'Unable to check terms'], 500);
}
