<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

function monnify_env(string $key, string $default = ''): string
{
    return trim((string) app_env($key, $default));
}

function monnify_base_url(): string
{
    return rtrim(monnify_env('MONNIFY_BASE_URL', 'https://api.monnify.com'), '/');
}

function monnify_is_configured(): bool
{
    return monnify_env('MONNIFY_API_KEY') !== ''
        && monnify_env('MONNIFY_SECRET_KEY') !== ''
        && monnify_env('MONNIFY_CONTRACT_CODE') !== '';
}

function monnify_missing_config(): array
{
    $required = [
        'MONNIFY_API_KEY' => monnify_env('MONNIFY_API_KEY'),
        'MONNIFY_SECRET_KEY' => monnify_env('MONNIFY_SECRET_KEY'),
        'MONNIFY_CONTRACT_CODE' => monnify_env('MONNIFY_CONTRACT_CODE'),
    ];
    return array_keys(array_filter($required, static fn(string $value): bool => $value === ''));
}

function monnify_configuration_error(): string
{
    $missing = monnify_missing_config();
    if (!$missing) {
        return 'Monnify is not configured';
    }
    return 'Monnify is missing: ' . implode(', ', $missing);
}

function paystack_env(string $key, string $default = ''): string
{
    return trim((string) app_env($key, $default));
}

function paystack_base_url(): string
{
    return rtrim(paystack_env('PAYSTACK_BASE_URL', 'https://api.paystack.co'), '/');
}

function paystack_is_configured(): bool
{
    return paystack_env('PAYSTACK_SECRET_KEY') !== '';
}

function paystack_configuration_error(): string
{
    return paystack_is_configured() ? '' : 'Paystack is missing: PAYSTACK_SECRET_KEY';
}

function paystack_ssl_verify(): bool
{
    $value = strtolower(paystack_env('PAYSTACK_SSL_VERIFY', paystack_env('PAYSTACK_ALLOW_INSECURE_SSL') === 'true' ? 'false' : 'true'));
    return !in_array($value, ['0', 'false', 'no', 'off'], true);
}

function paystack_request(string $method, string $path, array $payload = []): array
{
    if (!paystack_is_configured()) {
        return ['success' => false, 'error' => paystack_configuration_error()];
    }
    $url = paystack_base_url() . $path;
    if (strtoupper($method) === 'GET' && $payload) {
        $url .= '?' . http_build_query($payload);
    }
    $headers = [
        'Accept: application/json',
        'Content-Type: application/json',
        'Authorization: Bearer ' . paystack_env('PAYSTACK_SECRET_KEY'),
    ];
    $body = strtoupper($method) === 'GET' ? '' : json_encode($payload, JSON_UNESCAPED_SLASHES);

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        $verifySsl = paystack_ssl_verify();
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => $verifySsl,
            CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
        ]);
        if (strtoupper($method) !== 'GET') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($body) ? $body : '{}');
        }
        $response = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => strtoupper($method),
                'header' => implode("\r\n", $headers) . "\r\n",
                'content' => is_string($body) ? $body : '',
                'timeout' => 30,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => paystack_ssl_verify(),
                'verify_peer_name' => paystack_ssl_verify(),
            ],
        ]);
        $response = (string) file_get_contents($url, false, $context);
        $statusLine = $http_response_header[0] ?? '';
        preg_match('/\s(\d{3})\s/', $statusLine, $match);
        $status = (int) ($match[1] ?? 0);
        $error = '';
    }

    $decoded = json_decode($response, true);
    $ok = $status >= 200 && $status < 300 && is_array($decoded) && (bool) ($decoded['status'] ?? false);
    return [
        'success' => $ok,
        'status' => $status,
        'data' => is_array($decoded) ? $decoded : null,
        'raw' => mb_substr($response, 0, 1500),
        'error' => $ok ? null : (string) ($decoded['message'] ?? $error ?: "Paystack HTTP {$status}"),
    ];
}

function monnify_payment_methods(): array
{
    $methods = array_map('trim', explode(',', monnify_env('MONNIFY_PAYMENT_METHODS', 'CARD,ACCOUNT_TRANSFER,USSD')));
    return array_values(array_filter($methods, static fn(string $method): bool => $method !== ''));
}

function monnify_ssl_verify(): bool
{
    $value = strtolower(monnify_env('MONNIFY_SSL_VERIFY', 'true'));
    return !in_array($value, ['0', 'false', 'no', 'off'], true);
}

function monnify_last_auth_error(?string $error = null): string
{
    static $lastError = '';
    if ($error !== null) {
        $lastError = $error;
    }
    return $lastError;
}

function monnify_request(string $method, string $path, ?array $payload = null, bool $auth = true): array
{
    $url = monnify_base_url() . $path;
    $headers = ['Accept: application/json'];
    if ($payload !== null) {
        $headers[] = 'Content-Type: application/json';
    }

    if ($auth) {
        $token = monnify_access_token();
        if ($token === '') {
            $error = monnify_last_auth_error() ?: 'Monnify authentication failed';
            return ['success' => false, 'error' => $error];
        }
        $headers[] = 'Authorization: Bearer ' . $token;
    } else {
        $basic = base64_encode(monnify_env('MONNIFY_API_KEY') . ':' . monnify_env('MONNIFY_SECRET_KEY'));
        $headers[] = 'Authorization: Basic ' . $basic;
    }

    $body = $payload === null ? '' : json_encode($payload, JSON_UNESCAPED_SLASHES);
    if ($payload !== null && !is_string($body)) {
        return ['success' => false, 'error' => 'Unable to encode Monnify payload'];
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        $verifySsl = monnify_ssl_verify();
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => $verifySsl,
            CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
        ]);
        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $response = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => strtoupper($method),
                'header' => implode("\r\n", $headers) . "\r\n",
                'content' => $body,
                'timeout' => 30,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => monnify_ssl_verify(),
                'verify_peer_name' => monnify_ssl_verify(),
            ],
        ]);
        $response = (string) file_get_contents($url, false, $context);
        $statusLine = $http_response_header[0] ?? '';
        preg_match('/\s(\d{3})\s/', $statusLine, $match);
        $status = (int) ($match[1] ?? 0);
        $error = '';
    }

    $decoded = json_decode($response, true);
    $ok = $status >= 200 && $status < 300 && is_array($decoded) && (($decoded['requestSuccessful'] ?? true) !== false);
    return [
        'success' => $ok,
        'status' => $status,
        'data' => is_array($decoded) ? $decoded : null,
        'raw' => mb_substr($response, 0, 1500),
        'error' => $ok ? null : (string) ($decoded['responseMessage'] ?? $decoded['message'] ?? $error ?: "Monnify HTTP {$status}"),
    ];
}

function monnify_access_token(): string
{
    static $token = null;
    static $expiresAt = 0;
    if (is_string($token) && $token !== '' && $expiresAt > time() + 60) {
        return $token;
    }
    if (!monnify_is_configured()) {
        return '';
    }

    $res = monnify_request('POST', '/api/v1/auth/login', null, false);
    $body = $res['data']['responseBody'] ?? [];
    $token = (string) ($body['accessToken'] ?? '');
    if ($token === '') {
        monnify_last_auth_error((string) ($res['error'] ?? 'Monnify authentication failed'));
    } else {
        monnify_last_auth_error('');
    }
    $expiresIn = (int) ($body['expiresIn'] ?? 3500);
    $expiresAt = time() + max(300, $expiresIn);
    return $token;
}


