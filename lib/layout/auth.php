<?php

function admin_password_is_valid(string $password): bool
{
    $hash = app_env('ADMIN_PASSWORD_HASH');
    if ($hash) {
        return password_verify($password, $hash);
    }

    if (app_is_production()) {
        error_log('ADMIN_PASSWORD_HASH is required for production operator login.');
        return false;
    }

    $plain = app_env('ADMIN_PASSWORD');
    return $plain !== null && $plain !== '' && hash_equals($plain, $password);
}


function admin_require(PDO $pdo, ?string $feature = null): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (!admin_session_is_authenticated($pdo)) {
        $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/admin/index.php'));
        $scriptDir = str_replace('\\', '/', dirname($scriptName));
        if (basename($scriptDir) === 'admin') {
            redirect_to('login.php');
        }
        $basePath = (string) (parse_url(app_base_url(), PHP_URL_PATH) ?: '');
        if ($basePath !== '' && str_starts_with($scriptName, rtrim($basePath, '/') . '/')) {
            $scriptName = substr($scriptName, strlen(rtrim($basePath, '/')) + 1);
        }
        $next = ltrim($scriptName, '/');
        redirect_to(app_base_url() . '/login.php?next=' . urlencode($next));
    }
    admin_require_feature($pdo, $feature ?? admin_feature_for_script());
}


function admin_logout(string $destination = 'login.php'): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
    }
    session_destroy();
    redirect_to($destination);
}


function admin_handle_logout_request(string $destination = 'login.php'): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logout'])) {
        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            http_response_code(403);
            exit('Invalid security token.');
        }
        admin_logout($destination);
    }
    if (isset($_GET['logout'])) {
        admin_logout($destination);
    }
}


function admin_current_user_id(PDO $pdo): ?int
{
    $user = current_user($pdo);
    if ($user && (int) ($user['id'] ?? 0) > 0) {
        return (int) $user['id'];
    }
    return $_SESSION['super_admin_user_id'] ?? null;
}


function admin_current_user_name(PDO $pdo): ?string
{
    $user = current_user($pdo);
    if ($user && !empty($user['name'])) {
        return (string) $user['name'];
    }
    return $_SESSION['super_admin_user_name'] ?? null;
}


function admin_current_user_is_super_admin(PDO $pdo): bool
{
    return admin_current_platform_role($pdo) === 'super_admin';
}


function admin_require_feature(PDO $pdo, string $feature): void
{
    if (!admin_feature_is_allowed($pdo, $feature)) {
        http_response_code(403);
        exit('Forbidden: this admin role does not have access to this feature.');
    }
}


function admin_logout_action_path(): string
{
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/admin/index.php'));
    $marker = '/admin/';
    $pos = strpos($scriptName, $marker);
    if ($pos === false) {
        return 'admin.php';
    }
    $insideAdmin = substr($scriptName, $pos + strlen($marker));
    $dir = trim(str_replace('\\', '/', dirname($insideAdmin)), './');
    if ($dir === '' || $dir === '.') {
        return 'admin.php';
    }
    return str_repeat('../', substr_count($dir, '/') + 1) . 'admin.php';
}
