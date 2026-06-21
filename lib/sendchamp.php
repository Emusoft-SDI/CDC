<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

function sendchamp_setting_value(PDO $pdo, string $settingKey, string $envKey, string $default = ''): string
{
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

function sendchamp_normalize_phone(string $to): ?string
{
    $digits = preg_replace('/\D+/', '', $to);
    if ($digits === '') {
        return null;
    }
    if (str_starts_with($digits, '234')) {
        return $digits;
    }
    if (str_starts_with($digits, '0')) {
        return '234' . substr($digits, 1);
    }
    if (strlen($digits) >= 10) {
        return '234' . ltrim($digits, '0');
    }
    return null;
}

function sendchamp_value_is_placeholder(string $value): bool
{
    $value = trim($value);
    if ($value === '') {
        return true;
    }
    $lower = strtolower($value);
    return str_contains($lower, 'your_')
        || str_contains($lower, 'change-this')
        || str_contains($lower, 'put-')
        || str_contains($lower, '{access');
}

function sendchamp_post_json(string $url, string $accessKey, array $payload): array
{
    $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
    if (!is_string($body)) {
        return ['ok' => false, 'error' => 'Unable to encode Sendchamp payload'];
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessKey,
            ],
            CURLOPT_TIMEOUT => 25,
        ]);
        $response = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Accept: application/json\r\nContent-Type: application/json\r\nAuthorization: Bearer {$accessKey}\r\n",
                'content' => $body,
                'timeout' => 25,
                'ignore_errors' => true,
            ],
        ]);
        $response = (string) file_get_contents($url, false, $context);
        $statusLine = $http_response_header[0] ?? '';
        preg_match('/\s(\d{3})\s/', $statusLine, $match);
        $status = (int) ($match[1] ?? 0);
        $error = '';
    }

    $decoded = json_decode($response, true);
    if ($status >= 200 && $status < 300) {
        return [
            'ok' => true,
            'response' => mb_substr($response, 0, 700),
            'reference' => (string) ($decoded['data']['reference'] ?? $decoded['data']['id'] ?? $decoded['message_id'] ?? 'accepted'),
        ];
    }

    return [
        'ok' => false,
        'error' => (string) ($decoded['message'] ?? $decoded['error'] ?? $error ?: "Sendchamp HTTP {$status}"),
        'response' => mb_substr($response, 0, 700),
    ];
}

function sendSendchampSMS(string $to, string $message): bool
{
    $pdo = db();
    app_ensure_core_schema($pdo);

    $number = sendchamp_normalize_phone($to);
    if ($number === null) {
        app_log_notification('sms', $to, null, $message, 'failed', 'sendchamp', null, 'Recipient phone number is invalid');
        return false;
    }

    $accessKey = sendchamp_setting_value($pdo, 'sendchamp_access_key', 'SENDCHAMP_ACCESS_KEY');
    if (sendchamp_value_is_placeholder($accessKey)) {
        app_log_notification('sms', $number, null, $message, 'failed', 'sendchamp', null, 'Sendchamp access key is missing or still a placeholder');
        return false;
    }

    $baseUrl = rtrim(sendchamp_setting_value($pdo, 'sendchamp_base_url', 'SENDCHAMP_BASE_URL', 'https://api.sendchamp.com/api/v1'), '/');
    $sender = sendchamp_setting_value($pdo, 'sendchamp_sender_name', 'SENDCHAMP_SENDER_NAME', 'Sendchamp');
    $route = sendchamp_setting_value($pdo, 'sendchamp_route', 'SENDCHAMP_ROUTE', 'dnd');
    $payload = [
        'to' => [$number],
        'message' => $message,
        'sender_name' => $sender,
        'route' => $route,
    ];

    $result = sendchamp_post_json($baseUrl . '/sms/send', $accessKey, $payload);
    if (!$result['ok']) {
        app_log_notification('sms', $number, null, $message, 'failed', 'sendchamp', $result['response'] ?? null, $result['error'] ?? 'Sendchamp request failed');
        return false;
    }

    app_log_notification('sms', $number, null, $message, 'sent', 'sendchamp', 'Reference: ' . ($result['reference'] ?? 'accepted'));
    return true;
}
