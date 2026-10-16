<?php
declare(strict_types=1);

/**
 * Allawee Payment Gateway Driver for NATCODEV
 * Handles automated bank tools (lookup & account resolution), transfers/payouts,
 * payment collections, verification, and webhooks.
 *
 * Documentation: https://docs.allawee.com/
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../wallet.php';

function allawee_env(string $key, string $default = ''): string
{
    $consoleKeys = [
        'ALLAWEE_API_KEY' => 'allawee_api_key',
        'ALLAWEE_WEBHOOK_SECRET' => 'allawee_webhook_secret',
        'ALLAWEE_BASE_URL' => 'allawee_base_url',
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

function allawee_api_key(): string
{
    return allawee_env('ALLAWEE_API_KEY', 'sk.test.KKMHTxyU.7u5wJNLFhTS8kud7p7xE7JGz$OBYLyb0Z@r9NJ2a9QuDP_aDXMoQp8K_nkIwkEu@');
}

function allawee_webhook_secret(): string
{
    return allawee_env('ALLAWEE_WEBHOOK_SECRET', 'wh.31ue9qHdQAgPLzhQe');
}

function allawee_base_url(): string
{
    return rtrim(allawee_env('ALLAWEE_BASE_URL', 'https://api.allawee.com'), '/');
}

function allawee_is_configured(): bool
{
    return allawee_api_key() !== '';
}

function allawee_configuration_error(): string
{
    return allawee_is_configured() ? '' : 'Allawee is missing: ALLAWEE_API_KEY';
}

function allawee_request(string $method, string $path, array $payload = []): array
{
    $url = allawee_base_url() . '/' . ltrim($path, '/');
    if (strtoupper($method) === 'GET' && $payload) {
        $url .= '?' . http_build_query($payload);
    }

    $apiKey = allawee_api_key();
    $headers = [
        'Accept: application/json',
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
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
        'error' => $ok ? null : (string) ($decoded['message'] ?? $decoded['error'] ?? $error ?: "Allawee HTTP {$status}"),
    ];
}

/**
 * List Nigerian Banks from Allawee
 */
function allawee_list_banks(): array
{
    $res = allawee_request('GET', '/tools/banks');
    if ($res['success'] && is_array($res['data'])) {
        $banks = [];
        $rawList = is_array($res['data']) ? ($res['data']['banks'] ?? $res['data']) : [];
        foreach ($rawList as $bank) {
            $name = (string) ($bank['name'] ?? $bank['bank_name'] ?? '');
            $code = (string) ($bank['code'] ?? $bank['cbn_code'] ?? $bank['bank_code'] ?? '');
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

    return ['success' => false, 'error' => $res['error'] ?? 'Unable to load Allawee banks'];
}

/**
 * Resolve bank account via Allawee
 */
function allawee_resolve_account(string $bankCode, string $accountNumber): array
{
    $accountNumber = preg_replace('/[^0-9]/', '', $accountNumber);
    $bankCode = trim($bankCode);

    if ($accountNumber === '' || $bankCode === '') {
        return ['success' => false, 'error' => 'Bank code and account number are required.'];
    }

    $res = allawee_request('GET', '/tools/banks/resolve', [
        'account_number' => $accountNumber,
        'bank_code' => $bankCode,
    ]);

    if ($res['success']) {
        $data = is_array($res['data']) ? $res['data'] : [];
        $resolvedName = trim((string) ($data['account_name'] ?? $data['accountName'] ?? $data['name'] ?? ''));
        if ($resolvedName !== '') {
            return [
                'success' => true,
                'account_number' => $accountNumber,
                'account_name' => $resolvedName,
                'bank_code' => $bankCode,
                'provider' => 'allawee',
            ];
        }
    }

    if (!app_is_production()) {
        return [
            'success' => true,
            'account_number' => $accountNumber,
            'account_name' => 'Verified Allawee Test User',
            'bank_code' => $bankCode,
            'provider' => 'allawee',
            'sandbox_fallback' => true,
        ];
    }

    return ['success' => false, 'error' => $res['error'] ?? 'Allawee could not resolve this bank account.'];
}

/**
 * Initialize Checkout for Wallet Funding
 */
function allawee_initialize_checkout(float $amount, int $userId, string $email, string $reference, string $callbackUrl, array $metadata = []): array
{
    $amount = round($amount, 2);
    $payload = [
        'amount' => $amount,
        'currency' => 'NGN',
        'reference' => $reference,
        'customer' => [
            'name' => (string) ($metadata['name'] ?? 'NATCODEV User'),
            'email' => $email,
        ],
        'redirect_url' => $callbackUrl,
        'description' => 'NATCODEV wallet funding',
        'metadata' => array_merge([
            'user_id' => $userId,
            'reference' => $reference,
            'platform' => 'NATCODEV',
        ], $metadata),
    ];

    $res = allawee_request('POST', '/payments/collection', $payload);

    if ($res['success']) {
        $data = $res['data'] ?? [];
        $checkoutUrl = (string) ($data['payment_url'] ?? $data['link'] ?? $data['url'] ?? '');
        if ($checkoutUrl !== '') {
            return [
                'success' => true,
                'authorization_url' => $checkoutUrl,
                'reference' => $reference,
                'provider' => 'allawee',
                'data' => $data,
            ];
        }
    }

    if (!app_is_production()) {
        $mockCheckout = $callbackUrl . (str_contains($callbackUrl, '?') ? '&' : '?') . 'reference=' . urlencode($reference) . '&provider=allawee&status=success';
        return [
            'success' => true,
            'authorization_url' => $mockCheckout,
            'reference' => $reference,
            'provider' => 'allawee',
            'sandbox_simulated' => true,
        ];
    }

    return ['success' => false, 'error' => $res['error'] ?? 'Unable to initialize Allawee checkout'];
}

/**
 * Verify payment status
 */
function allawee_verify_payment(string $reference): array
{
    $res = allawee_request('GET', '/payments/' . rawurlencode($reference));
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
        'error' => $res['error'] ?? 'Unable to verify Allawee payment',
    ];
}

/**
 * Initiate transfer payout via Allawee
 */
function allawee_initiate_wallet_withdrawal(array $withdrawal): array
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

    $res = allawee_request('POST', '/payments/payout', $payload);

    if ($res['success']) {
        $body = $res['data'] ?? [];
        return [
            'success' => true,
            'provider' => 'allawee',
            'payout_reference' => (string) ($body['reference'] ?? $reference),
            'transfer_code' => (string) ($body['id'] ?? $body['transfer_id'] ?? ''),
            'payout_status' => strtolower((string) ($body['status'] ?? 'pending')),
            'payload' => $body,
        ];
    }

    if (!app_is_production()) {
        return [
            'success' => true,
            'provider' => 'allawee',
            'payout_reference' => $reference,
            'transfer_code' => 'ALLAWEE-SB-' . bin2hex(random_bytes(4)),
            'payout_status' => 'initiated',
            'payload' => ['mock' => true, 'request' => $payload],
        ];
    }

    return ['success' => false, 'error' => $res['error'] ?? 'Allawee payout failed', 'response' => $res];
}

/**
 * Verify transfer payout status
 */
function allawee_verify_transfer(string $reference): array
{
    $res = allawee_request('GET', '/payments/' . rawurlencode($reference));
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

    return ['state' => 'unknown', 'error' => $res['error'] ?? 'Unable to verify Allawee payout'];
}

/**
 * Validate webhook cryptographic signature
 */
function allawee_webhook_is_valid(string $rawBody, string|array $signatureOrHeaders = [], ?string $secret = null): bool
{
    $secret = $secret ?: allawee_webhook_secret();
    if ($secret === '') {
        return false;
    }

    if (is_array($signatureOrHeaders)) {
        $signature = $signatureOrHeaders['allawee-signature'] ?? $signatureOrHeaders['Allawee-Signature']
            ?? $signatureOrHeaders['x-allawee-signature'] ?? $signatureOrHeaders['signature']
            ?? $_SERVER['HTTP_ALLAWEE_SIGNATURE'] ?? $_SERVER['HTTP_X_ALLAWEE_SIGNATURE']
            ?? '';
    } else {
        $signature = (string) $signatureOrHeaders;
    }

    if ($signature === '') {
        return false;
    }

    $expected = hash_hmac('sha256', $rawBody, $secret);
    return hash_equals(strtolower((string) $signature), strtolower($expected));
}

