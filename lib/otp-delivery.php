<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/twilio.php';

function otp_ensure_schema(PDO $pdo): void
{
    app_ensure_core_schema($pdo);
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS otp_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            otp_code VARCHAR(10) NOT NULL,
            expires_at DATETIME NOT NULL,
            used TINYINT(1) NOT NULL DEFAULT 0,
            purpose VARCHAR(40) NOT NULL DEFAULT 'login',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_otp_sessions_user (user_id, expires_at),
            INDEX idx_otp_sessions_purpose (purpose, used, expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'otp_sessions');
    app_add_column_if_missing($pdo, 'otp_sessions', 'purpose', "VARCHAR(40) NOT NULL DEFAULT 'login'");
}

function otp_delivery_transport(string $channel): string
{
    return strtolower((string) app_env(strtoupper($channel) . '_TRANSPORT', app_is_production() ? 'twilio' : 'log'));
}

function otp_send_code(PDO $pdo, int $userId, string $code, string $purpose, ?string $phone, ?string $email = null): array
{
    $message = $purpose === 'phone_verification'
        ? "NATCODEV: Your phone verification code is {$code}. It expires in 5 minutes."
        : "NATCODEV: Your login code is {$code}. It expires in 10 minutes.";

    $result = [
        'sent' => [],
        'logged' => [],
        'failed' => [],
        'errors' => [],
    ];

    $phone = trim((string) $phone);
    if ($phone !== '') {
        foreach (['whatsapp' => 'WhatsApp', 'sms' => 'SMS'] as $channel => $label) {
            $transport = otp_delivery_transport($channel);
            if (in_array($transport, ['disabled', 'off', 'none'], true)) {
                continue;
            }
            if (sendMessage($phone, $message, $channel)) {
                if ($transport === 'log') {
                    $result['logged'][] = $label;
                } else {
                    $result['sent'][] = $label;
                }
            } else {
                $result['failed'][] = $label;
                $result['errors'][] = "{$label} failed. Check Notification Log for provider details.";
            }
        }
    }

    if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mailTransport = strtolower((string) app_env('MAIL_TRANSPORT', app_is_production() ? 'mail' : 'log'));
        if (app_send_mail($email, 'NATCODEV OTP Code', $message)) {
            if ($mailTransport === 'log') {
                $result['logged'][] = 'email';
            } else {
                $result['sent'][] = 'email';
            }
        } else {
            $result['failed'][] = 'email';
            $result['errors'][] = 'Email failed. Check mail transport configuration.';
        }
    }

    $result['sent'] = array_values(array_unique($result['sent']));
    $result['logged'] = array_values(array_unique($result['logged']));
    $result['failed'] = array_values(array_unique($result['failed']));
    $result['ok'] = (bool) ($result['sent'] || $result['logged']);

    return $result;
}

function otp_delivery_message(array $delivery): string
{
    $parts = [];
    if (!empty($delivery['sent'])) {
        $parts[] = 'sent by ' . implode(', ', $delivery['sent']);
    }
    if (!empty($delivery['logged'])) {
        $parts[] = 'logged by ' . implode(', ', $delivery['logged']);
    }
    if (!empty($delivery['failed'])) {
        $parts[] = 'failed on ' . implode(', ', $delivery['failed']);
    }
    return $parts ? implode('; ', $parts) : 'no delivery channel was available';
}

function otp_verify_code(PDO $pdo, int $userId, string $code, string $purpose): bool
{
    otp_ensure_schema($pdo);
    $stmt = $pdo->prepare("SELECT id FROM otp_sessions WHERE user_id = ? AND otp_code = ? AND purpose = ? AND expires_at > NOW() AND used = 0 ORDER BY id DESC LIMIT 1");
    $stmt->execute([$userId, $code, $purpose]);
    $otpId = (int) ($stmt->fetchColumn() ?: 0);
    if ($otpId <= 0) {
        return false;
    }
    $pdo->prepare("UPDATE otp_sessions SET used = 1 WHERE id = ?")->execute([$otpId]);
    return true;
}

function otp_begin_email_login_challenge(PDO $pdo, array $user, string $nextDestination = '', string $purpose = 'login'): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $email = filter_var((string) ($user['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    if (!$email) {
        return ['ok' => false, 'message' => 'This account does not have a valid confirmed email for OTP delivery.'];
    }

    $purpose = preg_replace('/[^a-z0-9_:-]/i', '', $purpose) ?: 'login';

    otp_ensure_schema($pdo);
    $otp = (string) random_int(100000, 999999);
    $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    $delivery = otp_send_code($pdo, (int) $user['id'], $otp, 'login', null, (string) $email);
    if (!$delivery['ok']) {
        return ['ok' => false, 'message' => 'OTP could not be delivered to the confirmed email. ' . implode(' ', $delivery['errors'] ?: ['Check mail settings and try again.'])];
    }

    $pdo->prepare('UPDATE otp_sessions SET used = 1 WHERE user_id = ? AND purpose = ? AND used = 0')->execute([(int) $user['id'], $purpose]);
    $pdo->prepare('INSERT INTO otp_sessions (user_id, otp_code, expires_at, purpose) VALUES (?, ?, ?, ?)')->execute([(int) $user['id'], $otp, $expires, $purpose]);

    $_SESSION['otp_user_id'] = (int) $user['id'];
    $_SESSION['otp_verified'] = false;
    $_SESSION['otp_purpose'] = $purpose;
    $_SESSION['otp_delivery_message'] = otp_delivery_message($delivery);
    if ($nextDestination !== '') {
        $_SESSION['otp_next_destination'] = $nextDestination;
    } else {
        unset($_SESSION['otp_next_destination']);
    }
    if (!app_is_production()) {
        $_SESSION['otp_debug_code'] = $otp;
    }

    return ['ok' => true, 'message' => 'OTP sent to the confirmed email.'];
}