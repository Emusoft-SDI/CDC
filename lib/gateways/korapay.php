<?php
declare(strict_types=1);

/**
 * Korapay Payment Gateway Driver for NATCODEV
 * Handles automated collections/checkout, bank lookup, account resolution,
 * transfer payouts, verification, and webhooks.
 *
 * Documentation: https://developers.korapay.com/docs/getting-started
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../wallet.php';

function korapay_env(string $key, string $default = ''): string
{
    $consoleKeys = [
        'KORAPAY_PUBLIC_KEY' => 'korapay_public_key',
        'KORAPAY_SECRET_KEY' => 'korapay_secret_key',
        'KORAPAY_ENCRYPTION_KEY' => 'korapay_encryption_key',
        'KORAPAY_BASE_URL' => 'korapay_base_url',
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

function korapay_public_key(): string
{
    return korapay_env('KORAPAY_PUBLIC_KEY', 'pk_test_LC7hEsJLHNpJxa8N2i24b9toWX5agaEg1yGHMzLt');
}

function korapay_secret_key(): string
{
    return korapay_env('KORAPAY_SECRET_KEY', 'sk_test_k1MXqDCmsmS3nMZ52PFivCD4Xyoew1TAZu9kstGW');
}

function korapay_encryption_key(): string
{
    return korapay_env('KORAPAY_ENCRYPTION_KEY', 'PLghJKaQtJUZDAqZL1Uwbpr6rux94NcG');
}

function korapay_base_url(): string
{
    return rtrim(korapay_env('KORAPAY_BASE_URL', 'https://api.korapay.com/merchant/api/v1'), '/');
}

function korapay_is_configured(): bool
{
    return korapay_secret_key() !== '';
}

function korapay_configuration_error(): string
{
    return korapay_is_configured() ? '' : 'Korapay is missing: KORAPAY_SECRET_KEY';
}

function korapay_request(string $method, string $path, array $payload = []): array
{
    $url = korapay_base_url() . '/' . ltrim($path, '/');
    if (strtoupper($method) === 'GET' && $payload) {
        $url .= '?' . http_build_query($payload);
    }

    $secretKey = korapay_secret_key();
    $headers = [
        'Accept: application/json',
        'Content-Type: application/json',
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
    $ok = $status >= 200 && $status < 300 && is_array($decoded) && (($decoded['status'] ?? true) !== false);

    return [
        'success' => $ok,
        'status' => $status,
        'data' => is_array($decoded) ? ($decoded['data'] ?? $decoded) : [],
        'raw' => mb_substr($response, 0, 1500),
        'error' => $ok ? null : (string) ($decoded['message'] ?? $error ?: "Korapay HTTP {$status}"),
    ];
}

/**
 * List Nigerian Banks from Korapay
 */
function korapay_list_banks(): array
{
    $res = korapay_request('GET', '/misc/banks', ['currency' => 'NGN']);
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
            ['name' => 'Kuda Microfinance Bank', 'code' => '50211'],
            ['name' => 'Opay (Paycom)', 'code' => '999992'],
            ['name' => 'PalmPay', 'code' => '999991'],
        ]];
    }

    return ['success' => false, 'error' => $res['error'] ?? 'Unable to load Korapay banks'];
}

/**
 * Resolve bank account via Korapay
 */
function korapay_resolve_account(string $bankCode, string $accountNumber): array
{
    $accountNumber = preg_replace('/[^0-9]/', '', $accountNumber);
    $bankCode = trim($bankCode);

    if ($accountNumber === '' || $bankCode === '') {
        return ['success' => false, 'error' => 'Bank code and account number are required.'];
    }

    $res = korapay_request('POST', '/misc/banks/resolve', [
        'currency' => 'NGN',
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
                'provider' => 'korapay',
            ];
        }
    }

    if (!app_is_production()) {
        return [
            'success' => true,
            'account_number' => $accountNumber,
            'account_name' => 'Verified Korapay Test User',
            'bank_code' => $bankCode,
            'provider' => 'korapay',
            'sandbox_fallback' => true,
        ];
    }

    return ['success' => false, 'error' => $res['error'] ?? 'Korapay could not resolve this bank account.'];
}

/**
 * Initialize Checkout for Wallet Funding
 */
function korapay_initialize_checkout(float $amount, int $userId, string $email, string $reference, string $callbackUrl, array $metadata = []): array
{
    $amount = round($amount, 2);
    $payload = [
        'amount' => $amount,
        'currency' => 'NGN',
        'reference' => $reference,
        'narration' => 'NATCODEV wallet funding',
        'notification_url' => app_base_url() . '/webhooks/korapay.php',
        'redirect_url' => $callbackUrl,
        'customer' => [
            'name' => (string) ($metadata['name'] ?? 'NATCODEV User'),
            'email' => $email,
        ],
        'channels' => ['card', 'bank_transfer'],
        'metadata' => array_merge([
            'user_id' => $userId,
            'reference' => $reference,
            'platform' => 'NATCODEV',
        ], $metadata),
    ];

    $res = korapay_request('POST', '/charges/initialize', $payload);

    if ($res['success']) {
        $data = $res['data'] ?? [];
        $checkoutUrl = (string) ($data['checkout_url'] ?? $data['payment_url'] ?? $data['url'] ?? '');
        if ($checkoutUrl !== '') {
            return [
                'success' => true,
                'authorization_url' => $checkoutUrl,
                'reference' => $reference,
                'provider' => 'korapay',
                'data' => $data,
            ];
        }
    }

    if (!app_is_production()) {
        $mockCheckout = $callbackUrl . (str_contains($callbackUrl, '?') ? '&' : '?') . 'reference=' . urlencode($reference) . '&provider=korapay&status=success';
        return [
            'success' => true,
            'authorization_url' => $mockCheckout,
            'reference' => $reference,
            'provider' => 'korapay',
            'sandbox_simulated' => true,
        ];
    }

    return ['success' => false, 'error' => $res['error'] ?? 'Unable to initialize Korapay checkout'];
}

/**
 * Verify payment status
 */
function korapay_verify_payment(string $reference): array
{
    $res = korapay_request('GET', '/charges/' . rawurlencode($reference));
    if ($res['success']) {
        $data = $res['data'] ?? [];
        $rawStatus = strtolower((string) ($data['status'] ?? ''));
        $status = in_array($rawStatus, ['success', 'successful', 'paid', 'completed'], true) ? 'success'
            : (in_array($rawStatus, ['failed', 'reversed', 'cancelled'], true) ? 'failed' : 'pending');

        return [
            'success' => true,
            'status' => $status,
            'amount' => (float) ($data['amount'] ?? $data['amount_paid'] ?? 0),
            'reference' => (string) ($data['reference'] ?? $reference),
            'paid_at' => (string) ($data['paid_at'] ?? ''),
            'data' => $data,
        ];
    }

    return [
        'success' => false,
        'status' => 'unknown',
        'error' => $res['error'] ?? 'Unable to verify Korapay payment',
    ];
}

/**
 * Initiate transfer payout via Korapay
 */
function korapay_initiate_wallet_withdrawal(array $withdrawal): array
{
    $amount = round((float) $withdrawal['final_amount'], 2);
    $reference = (string) $withdrawal['reference'];

    $payload = [
        'reference' => $reference,
        'amount' => $amount,
        'currency' => 'NGN',
        'narration' => 'NATCODEV withdrawal ' . $reference,
        'destination' => [
            'type' => 'bank_account',
            'amount' => $amount,
            'currency' => 'NGN',
            'narration' => 'NATCODEV withdrawal',
            'bank_account' => [
                'bank' => (string) $withdrawal['bank_code'],
                'account' => (string) $withdrawal['account_number'],
            ],
            'customer' => [
                'name' => (string) $withdrawal['account_name'],
            ],
        ],
    ];

    $res = korapay_request('POST', '/transactions/disburse', $payload);

    if ($res['success']) {
        $body = $res['data'] ?? [];
        return [
            'success' => true,
            'provider' => 'korapay',
            'payout_reference' => (string) ($body['reference'] ?? $reference),
            'transfer_code' => (string) ($body['transfer_code'] ?? $body['id'] ?? ''),
            'payout_status' => strtolower((string) ($body['status'] ?? 'pending')),
            'payload' => $body,
        ];
    }

    if (!app_is_production()) {
        return [
            'success' => true,
            'provider' => 'korapay',
            'payout_reference' => $reference,
            'transfer_code' => 'KORAPAY-SB-' . bin2hex(random_bytes(4)),
            'payout_status' => 'initiated',
            'payload' => ['mock' => true, 'request' => $payload],
        ];
    }

    return ['success' => false, 'error' => $res['error'] ?? 'Korapay payout failed', 'response' => $res];
}

/**
 * Verify transfer payout status
 */
function korapay_verify_transfer(string $reference): array
{
    $res = korapay_request('GET', '/transactions/disburse/' . rawurlencode($reference));
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

    return ['state' => 'unknown', 'error' => $res['error'] ?? 'Unable to verify Korapay payout'];
}

/**
 * Validate webhook cryptographic signature
 * Korapay hashes the payload or the `data` object inside using HMAC SHA-256 with the secret key.
 */
function korapay_webhook_is_valid(string $rawBody, string|array $payloadOrSignature, array|string|null $headersOrSecret = null): bool
{
    if (is_string($payloadOrSignature)) {
        // Called as ($rawBody, $signature, $secret)
        $signature = $payloadOrSignature;
        $secret = is_string($headersOrSecret) && $headersOrSecret !== '' ? $headersOrSecret : korapay_secret_key();
        $payload = json_decode($rawBody, true) ?: [];
    } else {
        // Called as ($rawBody, $payload, $headers)
        $payload = $payloadOrSignature;
        $headers = is_array($headersOrSecret) ? $headersOrSecret : [];
        $signature = $headers['x-korapay-signature'] ?? $headers['X-Korapay-Signature']
            ?? $_SERVER['HTTP_X_KORAPAY_SIGNATURE'] ?? '';
        $secret = korapay_secret_key();
    }

    if ($secret === '' || $signature === '') {
        return false;
    }

    $rawExpected = hash_hmac('sha256', $rawBody, $secret);
    if (hash_equals(strtolower((string) $signature), strtolower($rawExpected))) {
        return true;
    }

    $data = $payload['data'] ?? $payload;
    $dataJson = json_encode($data, JSON_UNESCAPED_SLASHES);
    $expected = hash_hmac('sha256', $dataJson, $secret);

    return hash_equals(strtolower((string) $signature), strtolower($expected));
}

