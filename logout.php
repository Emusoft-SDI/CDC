<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$next = platform_safe_next_for_logout((string) ($_GET['next'] ?? ''));
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $params['path'] ?: '/',
        'domain' => $params['domain'] ?: '',
        'secure' => (bool) $params['secure'],
        'httponly' => (bool) $params['httponly'],
        'samesite' => $params['samesite'] ?? 'Lax',
    ]);
}

session_destroy();
$target = 'login.php?message=logged_out';
if ($next !== '') {
    $target .= '&next=' . urlencode($next);
}
redirect_to($target);

function platform_safe_next_for_logout(string $next): string
{
    $next = trim(str_replace(["\0", '\\'], ['', '/'], $next));
    if ($next === '' || preg_match('/^[a-z][a-z0-9+.-]*:/i', $next) || str_starts_with($next, '//') || str_starts_with($next, '/')) {
        return '';
    }
    while (str_starts_with($next, '../')) {
        $next = substr($next, 3);
    }
    return $next;
}