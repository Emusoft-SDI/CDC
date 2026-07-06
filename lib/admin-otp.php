<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

function admin_operator_otp_email(): string
{
    $email = trim((string) app_env('ADMIN_OTP_EMAIL', ''));
    if ($email === '') {
        $email = trim((string) app_env('ADMIN_NOTIFY_EMAIL', 'info@natcodev.com.ng'));
    }
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
}

function admin_operator_safe_next(string $next): string
{
    $next = trim(str_replace(["\0", '\\'], ['', '/'], $next));
    if ($next === '' || preg_match('/^[a-z][a-z0-9+.-]*:/i', $next) || str_starts_with($next, '//') || str_starts_with($next, '/')) {
        return 'index.php';
    }
    while (str_starts_with($next, '../')) {
        $next = substr($next, 3);
    }
    if (!preg_match('#^(admin\.php|index\.php|wallet/index\.php|support/index\.php|academy/index\.php|marketplace/index\.php)$#', $next)) {
        return 'index.php';
    }
    return $next;
}

function admin_operator_otp_begin(string $next = 'index.php'): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $email = admin_operator_otp_email();
    if ($email === '') {
        return ['ok' => false, 'message' => 'Admin OTP email is not configured. Set ADMIN_OTP_EMAIL or ADMIN_NOTIFY_EMAIL.'];
    }

    $code = (string) random_int(100000, 999999);
    $_SESSION['admin_otp_hash'] = password_hash($code, PASSWORD_DEFAULT);
    $_SESSION['admin_otp_expires'] = time() + 600;
    $_SESSION['admin_otp_grace_expires'] = time() + 1800;
    $_SESSION['admin_otp_email'] = $email;
    $_SESSION['admin_otp_next'] = admin_operator_safe_next($next);
    unset($_SESSION['admin_authenticated'], $_SESSION['admin']);

    $plain = "NATCODEV operator login code: {$code}\n\nThis code expires in 10 minutes. If you did not request it, change the admin password immediately.";
    $html = "<p>NATCODEV operator login code:</p><p style=\"font-size:28px;font-weight:900;letter-spacing:4px\">" . e($code) . "</p><p>This code expires in 10 minutes.</p><p>If you did not request it, change the admin password immediately.</p>";
    $sent = app_send_mail($email, 'NATCODEV Operator Login OTP', $plain, $html);
    if (!$sent) {
        return ['ok' => false, 'message' => 'Operator OTP could not be sent. Check mail transport configuration.'];
    }

    if (!app_is_production()) {
        $_SESSION['admin_otp_debug_code'] = $code;
    }
    return ['ok' => true, 'message' => 'Operator OTP sent to the configured admin email.'];
}

function admin_operator_otp_verify(string $code): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $code = preg_replace('/[^0-9]/', '', $code);
    $hash = (string) ($_SESSION['admin_otp_hash'] ?? '');
    $expires = (int) ($_SESSION['admin_otp_expires'] ?? 0);
    if ($hash === '' || $expires < time()) {
        $graceExpires = (int) ($_SESSION['admin_otp_grace_expires'] ?? 0);
        if ($hash === '' || $graceExpires < time()) {
            unset($_SESSION['admin_otp_hash'], $_SESSION['admin_otp_expires'], $_SESSION['admin_otp_grace_expires'], $_SESSION['admin_otp_email'], $_SESSION['admin_otp_next'], $_SESSION['admin_otp_debug_code']);
            return ['ok' => false, 'message' => 'Operator OTP has expired. Please start admin login again.', 'restart' => true];
        }
        return ['ok' => false, 'message' => 'Operator OTP has expired. Request a new code below.', 'restart' => false, 'expired' => true];
    }
    if (strlen($code) !== 6 || !password_verify($code, $hash)) {
        return ['ok' => false, 'message' => 'Invalid operator OTP. Check the latest 6-digit code and try again before it expires.', 'restart' => false];
    }

    $next = admin_operator_safe_next((string) ($_SESSION['admin_otp_next'] ?? 'index.php'));
    session_regenerate_id(true);
    $_SESSION['admin_authenticated'] = true;
    $_SESSION['admin'] = true;
    unset($_SESSION['admin_otp_hash'], $_SESSION['admin_otp_expires'], $_SESSION['admin_otp_grace_expires'], $_SESSION['admin_otp_email'], $_SESSION['admin_otp_next'], $_SESSION['admin_otp_debug_code']);
    return ['ok' => true, 'next' => $next];
}