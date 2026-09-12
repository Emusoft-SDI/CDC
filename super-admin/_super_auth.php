<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$pdo = db();
$userId = (int) ($_SESSION['super_admin_user_id'] ?? $_SESSION['user_id'] ?? 0);
$isAuthorized = false;

if ($userId > 0 && app_column_exists($pdo, 'users', 'is_super_admin')) {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND is_super_admin = 1 AND account_status = 'active' LIMIT 1");
    $stmt->execute([$userId]);
    $isAuthorized = (bool) $stmt->fetchColumn();
}

if ($isAuthorized) {
    $_SESSION['user_id'] = $userId;
    $_SESSION['super_admin_authenticated'] = true;
    $_SESSION['super_admin_user_id'] = $userId;
    return;
}

unset($_SESSION['super_admin_authenticated'], $_SESSION['super_admin_user_id'], $_SESSION['super_admin_login_audited']);
http_response_code(403);
redirect_to('index.php');