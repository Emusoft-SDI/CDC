<?php
declare(strict_types=1);

/**
 * Woven Finance Payment Gateway Driver for NATCODEV
 * Handles automated checkouts, virtual accounts, bank name enquiry,
 * transfer payouts, verification, and webhooks.
 *
 * Documentation: https://docs.woven.finance/
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../wallet.php';

function woven_env(string $key, string $default = ''): string
{
    $consoleKeys = [
        'WOVEN_API_KEY' => 'woven_api_key',
        'WOVEN_SECRET_KEY' => 'woven_secret_key',
        'WOVEN_API_SECRET' => 'woven_secret_key',
        'WOVEN_BASE_URL' => 'woven_base_url',
    ];
    if (isset($consoleKeys[$key]) && function_exists('app_secret')) {
        try {
            $stored = app_secret(db(), $consoleKeys[$key], $key, $default);
            if (trim((string) $stored) !== '') {
                return trim((string) $stored);
            }
        } catch (Throwable $e) {
            // fall through
        }
    }
    return trim((string) app_env($key, $default));
}

function woven_api_key(): string
{
    return woven_env('WOVEN_API_KEY', 'vb_tk_f14c1db5e2daa7ace852ae6e');
}

function woven_secret_key(): string
{
    $secret = woven_env('WOVEN_SECRET_KEY');
    if ($secret === '') {
        $secret = woven_env('WOVEN_API_SECRET');
    }
    return $secret !== '' ? $secret : 'vb_ts_a143e315321d2364a935c7e9883c340e43290f015685';
}

function woven_base_url(): string
{
    return rtrim(woven_env('WOVEN_BASE_URL', 'https://api.woven.finance'), '/');
}

function woven_is_configured(): bool
{
    return woven_api_key() !== '' || woven_secret_key() !== '';
}

function woven_configuration_error(): string
{
    return woven_is_configured() ? '' : 'Woven Finance is missing: WOVEN_API_KEY or WOVEN_SECRET_KEY';
}

function woven_request(string $method, string $path, array $payload = []): array
{
    $url = woven_base_url() . '/' . ltrim($path, '/');
    if (strtoupper($method) === 'GET' && $payload) {
        $url .= '?' . http_build_query($payload);
    }

    $secretKey = woven_secret_key();
    $apiKey = woven_api_key();
    $headers = [
        'Accept: application/json',
        'Content-Type: application/json',
        'api-secret: ' . $secretKey,
        'api-key: ' . $apiKey,
        'Authorization: Bearer ' . $secretKey,
    ];

    $body = strtoupper($method) === 'GET' ? '' : json_encode($payload, JSON_UNESCAPED_SLASHES);

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => app_is_production(),
            CURLOPT_SSL_VERIFYHOST => app_is_production() ? 2 : 0,
        ]);
        if (strtoupper($method) !== 'GET' && $body !== '') {
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
                'verify_peer' => app_is_production(),
                'verify_peer_name' => app_is_production(),
            ],
        ]);
        $response = (string) file_get_contents($url, false, $context);
        $statusLine = $http_response_header[0] ?? '';
        preg_match('/\s(\d{3})\s/', $statusLine, $match);
        $status = (int) ($match[1] ?? 0);
        $error = '';
    }

    $decoded = json_decode($response, true);
    $ok = $status >= 200 && $status < 300 && is_array($decoded) && (($decoded['status'] ?? true) !== false) && (($decoded['success'] ?? true) !== false);

    return [
        'success' => $ok,
        'status' => $status,
        'data' => is_array($decoded) ? ($decoded['data'] ?? $decoded) : [],
        'raw' => mb_substr($response, 0, 1500),
        'error' => $ok ? null : (string) ($decoded['message'] ?? $decoded['error'] ?? $error ?: "Woven HTTP {$status}"),
    ];
}

/**
 * List Nigerian Banks from Woven
 */
function woven_list_banks(): array
{
    $res = woven_request('GET', '/api/v1/enquiry/banks');
    if ($res['success'] && is_array($res['data'])) {
        $banks = [];
        $rawList = is_array($res['data']) ? ($res['data']['banks'] ?? $res['data']) : [];
        foreach ($rawList as $bank) {
            $name = (string) ($bank['name'] ?? $bank['bank_name'] ?? '');
            $code = (string) ($bank['code'] ?? $bank['bank_code'] ?? '');
            if ($name !== '' && $code !== '') {
                $banks[] = ['name' => $name, 'code' => $code];
            }
        }
        if ($banks) {
            return ['success' => true, 'banks' => $banks];
        }
    }

    if (!app_is_production()) {
        return ['success' => true, 'banks' => [
            ['name' => 'Access Bank', 'code' => '044'],
            ['name' => 'First Bank of Nigeria', 'code' => '011'],
            ['name' => 'Guaranty Trust Bank (GTBank)', 'code' => '058'],
            ['name' => 'United Bank for Africa (UBA)', 'code' => '033'],
            ['name' => 'Zenith Bank', 'code' => '057'],
            ['name' => 'VFD Microfinance Bank', 'code' => '566'],
            ['name' => 'Kuda Microfinance Bank', 'code' => '50211'],
            ['name' => 'Opay', 'code' => '999992'],
        ]];
    }

    return ['success' => false, 'error' => $res['error'] ?? 'Unable to load Woven banks'];
}

/**
 * Resolve bank account via Woven
 */
function woven_resolve_account(string $bankCode, string $accountNumber): array
{
    $accountNumber = preg_replace('/[^0-9]/', '', $accountNumber);
    $bankCode = trim($bankCode);

    if ($accountNumber === '' || $bankCode === '') {
        return ['success' => false, 'error' => 'Bank code and account number are required.'];
    }

    $res = woven_request('POST', '/api/v1/enquiry/name', [
        'bank_code' => $bankCode,
        'account_number' => $accountNumber,
    ]);

    if ($res['success']) {
        $data = is_array($res['data']) ? $res['data'] : [];
        $resolvedName = trim((string) ($data['account_name'] ?? $data['accountName'] ?? ''));
        if ($resolvedName !== '') {
            return [
                'success' => true,
                'account_number' => $accountNumber,
                'account_name' => $resolvedName,
                'bank_code' => $bankCode,
                'provider' => 'woven',
            ];
        }
    }

    if (!app_is_production()) {
        return [
            'success' => true,
            'account_number' => $accountNumber,
            'account_name' => 'Verified Woven Test User',
            'bank_code' => $bankCode,
            'provider' => 'woven',
            'sandbox_fallback' => true,
        ];
    }

    return ['success' => false, 'error' => $res['error'] ?? 'Woven could not verify this bank account.'];
}

/**
 * Initialize Checkout for Wallet Funding
 */
function woven_initialize_checkout(float $amount, int $userId, string $email, string $reference, string $callbackUrl, array $metadata = []): array
{
    $amount = round($amount, 2);
    $payload = [
        'amount' => $amount,
        'currency' => 'NGN',
        'reference' => $reference,
        'customer_email' => $email,
        'callback_url' => $callbackUrl,
        'description' => 'NATCODEV wallet funding',
        'metadata' => array_merge([
            'user_id' => $userId,
            'reference' => $reference,
            'platform' => 'NATCODEV',
        ], $metadata),
    ];

    $res = woven_request('POST', '/api/v1/checkout/initialize', $payload);

    if ($res['success']) {
        $data = $res['data'] ?? [];
        $checkoutUrl = (string) ($data['payment_url'] ?? $data['url'] ?? $data['checkout_url'] ?? '');
        if ($checkoutUrl !== '') {
            return [
                'success' => true,
                'authorization_url' => $checkoutUrl,
                'reference' => $reference,
                'provider' => 'woven',
                'data' => $data,
            ];
        }
    }

    if (!app_is_production()) {
        $mockCheckout = $callbackUrl . (str_contains($callbackUrl, '?') ? '&' : '?') . 'reference=' . urlencode($reference) . '&provider=woven&status=success';
        return [
            'success' => true,
            'authorization_url' => $mockCheckout,
            'reference' => $reference,
            'provider' => 'woven',
            'sandbox_simulated' => true,
        ];
    }

    return ['success' => false, 'error' => $res['error'] ?? 'Unable to initialize Woven checkout'];
}

/**
 * Verify payment status
 */
function woven_verify_payment(string $reference): array
{
    $res = woven_request('GET', '/api/v1/transactions/verify/' . rawurlencode($reference));
    if ($res['success']) {
        $data = $res['data'] ?? [];
        $rawStatus = strtolower((string) ($data['status'] ?? ''));
        $status = in_array($rawStatus, ['success', 'successful', 'paid', 'completed'], true) ? 'success'
            : (in_array($rawStatus, ['failed', 'reversed', 'cancelled'], true) ? 'failed' : 'pending');

        return [
            'success' => true,
            'status' => $status,
            'amount' => (float) ($data['amount'] ?? 0),
            'reference' => (string) ($data['reference'] ?? $reference),
            'paid_at' => (string) ($data['created_at'] ?? $data['paid_at'] ?? ''),
            'data' => $data,
        ];
    }

    return [
        'success' => false,
        'status' => 'unknown',
        'error' => $res['error'] ?? 'Unable to verify Woven transaction',
    ];
}

/**
 * Initiate transfer payout via Woven
 */
function woven_initiate_wallet_withdrawal(array $withdrawal): array
{
    $amount = round((float) $withdrawal['final_amount'], 2);
    $reference = (string) $withdrawal['reference'];

    $payload = [
        'amount' => $amount,
        'currency' => 'NGN',
        'bank_code' => (string) $withdrawal['bank_code'],
        'account_number' => (string) $withdrawal['account_number'],
        'account_name' => (string) $withdrawal['account_name'],
        'reference' => $reference,
        'narration' => 'NATCODEV withdrawal ' . $reference,
    ];

    $res = woven_request('POST', '/api/v1/payouts/single', $payload);

    if ($res['success']) {
        $body = $res['data'] ?? [];
        return [
            'success' => true,
            'provider' => 'woven',
            'payout_reference' => (string) ($body['reference'] ?? $reference),
            'transfer_code' => (string) ($body['transfer_code'] ?? $body['id'] ?? ''),
            'payout_status' => strtolower((string) ($body['status'] ?? 'pending')),
            'payload' => $body,
        ];
    }

    if (!app_is_production()) {
        return [
            'success' => true,
            'provider' => 'woven',
            'payout_reference' => $reference,
            'transfer_code' => 'WOVEN-SB-' . bin2hex(random_bytes(4)),
            'payout_status' => 'initiated',
            'payload' => ['mock' => true, 'request' => $payload],
        ];
    }

    return ['success' => false, 'error' => $res['error'] ?? 'Woven payout failed', 'response' => $res];
}

/**
 * Verify payout status
 */
function woven_verify_transfer(string $reference): array
{
    $res = woven_request('GET', '/api/v1/payouts/' . rawurlencode($reference));
    if ($res['success']) {
        $body = $res['data'] ?? [];
        $rawStatus = strtolower((string) ($body['status'] ?? ''));
        $state = in_array($rawStatus, ['success', 'successful', 'paid'], true) ? 'success'
            : (in_array($rawStatus, ['failed', 'reversed', 'rejected'], true) ? 'failed' : 'pending');

        return [
            'state' => $state,
            'status' => $rawStatus,
            'reference' => $reference,
            'amount' => (float) ($body['amount'] ?? 0),
            'payload' => $body,
        ];
    }

    return ['state' => 'unknown', 'error' => $res['error'] ?? 'Unable to verify Woven payout'];
}

/**
 * Validate webhook signature
 */
function woven_webhook_is_valid(string $rawBody, string|array $signatureOrHeaders = [], ?string $secret = null): bool
{
    $secret = $secret ?: woven_secret_key();
    if ($secret === '') {
        return false;
    }

    if (is_array($signatureOrHeaders)) {
        $signature = $signatureOrHeaders['x-woven-signature'] ?? $signatureOrHeaders['X-Woven-Signature']
            ?? $signatureOrHeaders['x-signature'] ?? $signatureOrHeaders['signature']
            ?? $_SERVER['HTTP_X_WOVEN_SIGNATURE'] ?? $_SERVER['HTTP_X_SIGNATURE']
            ?? '';
    } else {
        $signature = (string) $signatureOrHeaders;
    }

    if ($signature === '') {
        return false;
    }

    $expected = hash_hmac('sha512', $rawBody, $secret);
    if (hash_equals(strtolower((string) $signature), strtolower($expected))) {
        return true;
    }

    $expected256 = hash_hmac('sha256', $rawBody, $secret);
    return hash_equals(strtolower((string) $signature), strtolower($expected256));
}

