<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../lib/admin-layout.php';

function wallets_auth_check(PDO $pdo): void {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    if (!admin_session_is_authenticated($pdo)) {
        header('Location: ' . app_base_url() . '/admin/login.php');
        exit;
    }
}

function wallets_require_role(PDO $pdo, array $allowedRoles): void {
    $role = admin_current_platform_role($pdo) ?? 'admin';
    if (!in_array($role, $allowedRoles, true) && !($role === 'super_admin')) {
        header('HTTP/1.0 403 Forbidden');
        echo "Access Denied.";
        exit;
    }
}

// CSRF Helpers
if (!function_exists('wallets_csrf_token')) {
    function wallets_csrf_token(): string {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        if (empty($_SESSION['wallets_csrf'])) $_SESSION['wallets_csrf'] = bin2hex(random_bytes(32));
        return $_SESSION['wallets_csrf'];
    }
}

if (!function_exists('wallets_verify_csrf')) {
    function wallets_verify_csrf(?string $token): bool {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        return !empty($token) && hash_equals($_SESSION['wallets_csrf'] ?? '', $token);
    }
}
