<?php
// lib/twilio.php - Enhanced with SMS fallback
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/sendchamp.php';

$autoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

function twilio_setting_value(PDO $pdo, string $settingKey, string $envKey, string $default = ''): string {
    $value = (string) app_env($envKey, '');
    if ($value !== '') {
        return $value;
    }
    if (app_table_exists($pdo, 'settings')) {
        $stmt = $pdo->prepare("SELECT value FROM settings WHERE key_name = ? LIMIT 1");
        $stmt->execute([$settingKey]);
        $stored = (string) ($stmt->fetchColumn() ?: '');
        if ($stored !== '') {
            return $stored;
        }
    }
    return $default;
}

function twilio_value_is_placeholder(string $value): bool {
    $value = trim($value);
    if ($value === '') {
        return true;
    }
    $lower = strtolower($value);
    return str_contains($lower, 'authtoken')
        || str_contains($lower, '[auth')
        || str_contains($lower, 'change-this')
        || str_contains($lower, 'put-');
}

function twilio_normalize_phone(string $to): ?string {
    $digits = preg_replace('/\D+/', '', $to);
    if ($digits === '') {
        return null;
    }
    if (str_starts_with($digits, '234')) {
        return '+' . $digits;
    }
    if (str_starts_with($digits, '0')) {
        return '+234' . substr($digits, 1);
    }
    if (strlen($digits) >= 10) {
        return '+234' . ltrim($digits, '0');
    }
    return null;
}

function twilio_whatsapp_address(string $number): string {
    return str_starts_with($number, 'whatsapp:') ? $number : 'whatsapp:' . $number;
}

function twilio_post_message(string $sid, string $token, string $to, array $payload): array {
    if (class_exists('Twilio\\Rest\\Client')) {
        $client = new Twilio\Rest\Client($sid, $token);
        $result = $client->messages->create($to, $payload);
        return ['ok' => true, 'sid' => (string) ($result->sid ?? 'accepted')];
    }

    $url = 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($sid) . '/Messages.json';
    $postFields = http_build_query(['To' => $to] + array_filter([
        'From' => $payload['from'] ?? null,
        'MessagingServiceSid' => $payload['messagingServiceSid'] ?? null,
        'Body' => $payload['body'] ?? '',
    ], static fn ($value): bool => $value !== null && $value !== ''));

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => $sid . ':' . $token,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT => 20,
        ]);
        $body = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\nAuthorization: Basic " . base64_encode($sid . ':' . $token) . "\r\n",
                'content' => $postFields,
                'timeout' => 20,
                'ignore_errors' => true,
            ],
        ]);
        $body = (string) file_get_contents($url, false, $context);
        $statusLine = $http_response_header[0] ?? '';
        preg_match('/\s(\d{3})\s/', $statusLine, $match);
        $status = (int) ($match[1] ?? 0);
        $error = '';
    }

    $decoded = json_decode($body, true);
    if ($status >= 200 && $status < 300) {
        return ['ok' => true, 'sid' => (string) ($decoded['sid'] ?? 'accepted')];
    }

    return [
        'ok' => false,
        'error' => (string) ($decoded['message'] ?? $error ?: "Twilio HTTP {$status}"),
        'response' => mb_substr($body, 0, 500),
    ];
}

function sendWhatsAppMessage($to, $message) {
    return sendMessage($to, $message, 'whatsapp');
}

function sendSMSMessage($to, $message) {
    return sendMessage($to, $message, 'sms');
}

function sendMessage($to, $message, $channel = 'whatsapp') {
    $pdo = db();
    app_ensure_core_schema($pdo);
    $channel = strtolower((string) $channel);
    if (!in_array($channel, ['sms', 'whatsapp'], true)) {
        app_log_notification($channel, (string) $to, null, (string) $message, 'failed', 'twilio', null, 'Unsupported channel');
        return false;
    }

    $sid = twilio_setting_value($pdo, 'twilio_sid', 'TWILIO_ACCOUNT_SID');
    $token = twilio_setting_value($pdo, 'twilio_token', 'TWILIO_AUTH_TOKEN');
    $whatsappFrom = twilio_setting_value($pdo, 'twilio_whatsapp_number', 'TWILIO_WHATSAPP_FROM');
    $smsFrom = twilio_setting_value($pdo, 'twilio_sms_number', 'TWILIO_SMS_FROM');
    $messagingServiceSid = twilio_setting_value($pdo, 'twilio_messaging_service_sid', 'TWILIO_MESSAGING_SERVICE_SID');
    $transport = strtolower(twilio_setting_value($pdo, $channel . '_transport', strtoupper($channel) . '_TRANSPORT', app_is_production() ? 'twilio' : 'log'));

    $number = twilio_normalize_phone((string) $to);
    if ($number === null) {
        app_log_notification($channel, (string) $to, null, (string) $message, 'failed', $transport, null, 'Recipient phone number is invalid');
        return false;
    }

    if ($transport === 'log') {
        app_log_notification($channel, $number, null, (string) $message, 'logged', $transport, 'Channel is in log mode; no live SMS/WhatsApp was sent');
        return true;
    }

    if (in_array($transport, ['disabled', 'off', 'none'], true)) {
        app_log_notification($channel, $number, null, (string) $message, 'skipped', $transport, null, 'Channel is disabled');
        return false;
    }

    if ($channel === 'sms' && $transport === 'sendchamp') {
        return sendSendchampSMS((string) $to, (string) $message);
    }

    if ($transport !== 'twilio') {
        app_log_notification($channel, $number, null, (string) $message, 'failed', $transport, null, 'Unsupported notification transport');
        return false;
    }

    if (twilio_value_is_placeholder($sid) || twilio_value_is_placeholder($token)) {
        $reason = 'Twilio SID/Auth Token is missing or still set to a placeholder';
        app_log_notification($channel, $number, null, (string) $message, 'failed', 'twilio', null, $reason);
        return false;
    }

    try {
        $payload = ['body' => $message];
        if ($channel === 'whatsapp') {
            $from = $whatsappFrom !== '' ? twilio_whatsapp_address($whatsappFrom) : '';
            $to = twilio_whatsapp_address($number);
        } else {
            $from = $smsFrom;
            $to = $number;
        }

        if ($from !== '') {
            $payload['from'] = $from;
        } elseif ($messagingServiceSid !== '') {
            $payload['messagingServiceSid'] = $messagingServiceSid;
        } else {
            app_log_notification($channel, $number, null, (string) $message, 'failed', 'twilio', null, "Twilio {$channel} sender or Messaging Service SID is missing");
            return false;
        }

        $result = twilio_post_message($sid, $token, $to, $payload);
        if (!$result['ok']) {
            app_log_notification($channel, $number, null, (string) $message, 'failed', 'twilio', $result['response'] ?? null, $result['error'] ?? 'Twilio request failed');
            return false;
        }
        app_log_notification($channel, $number, null, (string) $message, 'sent', 'twilio', 'SID: ' . ($result['sid'] ?? 'accepted'));
        return true;
    } catch (Throwable $e) {
        error_log("Twilio {$channel} Error: " . $e->getMessage());
        app_log_notification($channel, $number, null, (string) $message, 'failed', 'twilio', null, $e->getMessage());
        return false;
    }
}
