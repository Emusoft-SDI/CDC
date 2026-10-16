<?php
declare(strict_types=1);

/**
 * Fincra Payment Gateway Driver for NATCODEV
 * Handles collections/checkouts, virtual accounts, bank resolution,
 * transfer payouts, status verification, and secure webhooks.
 *
 * Documentation: https://docs.fincra.com/docs/getting-started
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../wallet.php';

function fincra_env(string $key, string $default = ''): string
{
    $consoleKeys = [
        'FINCRA_PUBLIC_KEY' => 'fincra_public_key',
        'FINCRA_SECRET_KEY' => 'fincra_secret_key',
        'FINCRA_API_KEY' => 'fincra_api_key',
        'FINCRA_WEBHOOK_KEY' => 'fincra_webhook_key',
        'FINCRA_ENCRYPTION_KEY' => 'fincra_encryption_key',
        'FINCRA_BASE_URL' => 'fincra_base_url',
        'FINCRA_BUSINESS_ID' => 'fincra_business_id',
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

function fincra_public_key(): string
{
    return fincra_env('FINCRA_PUBLIC_KEY', 'pk_test_NmFjZmMyOWQ1YjdhNGUzZGM3YTY2NDcxOjoxNDI2Nzk=');
}

function fincra_secret_key(): string
{
    $sec = fincra_env('FINCRA_SECRET_KEY');
    if ($sec === '') {
        $sec = fincra_env('FINCRA_API_KEY');
    }
    // Fall back to webhook key if secret key is not separately specified in sandbox
    if ($sec === '') {
        $sec = fincra_env('FINCRA_WEBHOOK_KEY', '5f717263583340f3b1bc7489f3b2d47b');
    }
    return $sec;
}

function fincra_webhook_key(): string
{
    return fincra_env('FINCRA_WEBHOOK_KEY', '5f717263583340f3b1bc7489f3b2d47b');
}

function fincra_business_id(): string
{
    $id = fincra_env('FINCRA_BUSINESS_ID');
    if ($id !== '') {
        return $id;
    }
    // Extract business ID from public key if pk_test_<base64>::<businessId>
    $pk = fincra_public_key();
    if (preg_match('/::(\d+)$/', $pk, $m)) {
        return $m[1];
    }
    $decoded = base64_decode(str_replace('pk_test_', '', $pk), true);
    if ($decoded && preg_match('/::(\d+)$/', $decoded, $m)) {
        return $m[1];
    }
    return '142679';
}

function fincra_base_url(): string
{
    $default = app_is_production() ? 'https://api.fincra.com' : 'https://sandboxapi.fincra.com';
    return rtrim(fincra_env('FINCRA_BASE_URL', $default), '/');
}

function fincra_is_configured(): bool
{
    return fincra_public_key() !== '' || fincra_secret_key() !== '';
}

function fincra_configuration_error(): string
{
    return fincra_is_configured() ? '' : 'Fincra is missing: FINCRA_PUBLIC_KEY or FINCRA_SECRET_KEY';
}

function fincra_request(string $method, string $path, array $payload = []): array
{
    $url = fincra_base_url() . '/' . ltrim($path, '/');
    if (strtoupper($method) === 'GET' && $payload) {
        $url .= '?' . http_build_query($payload);
    }

    $apiKey = fincra_secret_key();
    $headers = [
        'Accept: application/json',
        'Content-Type: application/json',
        'api-key: ' . $apiKey,
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
        'error' => $ok ? null : (string) ($decoded['message'] ?? $decoded['error'] ?? $error ?: "Fincra HTTP {$status}"),
    ];
}

/**
 * List Nigerian Banks from Fincra
 */
function fincra_list_banks(): array
{
    $res = fincra_request('GET', '/core/banks', ['country' => 'NG', 'currency' => 'NGN', 'paymentDestination' => 'bank_account']);
    if ($res['success'] && is_array($res['data'])) {
        $banks = [];
        $rawList = is_array($res['data']) ? ($res['data']['banks'] ?? $res['data']) : [];
        foreach ($rawList as $bank) {
            $name = (string) ($bank['name'] ?? $bank['bankName'] ?? '');
            $code = (string) ($bank['code'] ?? $bank['bankCode'] ?? '');
            if ($name !== '' && $code !== '') {
                $banks[] = ['name' => $name, 'code' => $code];
            }
        }
        if ($banks) {
            return ['success' => true, 'banks' => $banks];
        }
    }

    // Sandbox convenience fallback if outside production
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

    return ['success' => false, 'error' => $res['error'] ?? 'Unable to load Fincra banks'];
}

/**
 * Resolve bank account via Fincra
 */
function fincra_resolve_account(string $bankCode, string $accountNumber): array
{
    $accountNumber = preg_replace('/[^0-9]/', '', $accountNumber);
    $bankCode = trim($bankCode);

    if ($accountNumber === '' || $bankCode === '') {
        return ['success' => false, 'error' => 'Bank code and account number are required.'];
    }

    $res = fincra_request('POST', '/core/accounts/resolve', [
        'accountNumber' => $accountNumber,
        'bankCode' => $bankCode,
        'currency' => 'NGN',
    ]);

    if ($res['success']) {
        $data = is_array($res['data']) ? $res['data'] : [];
        $resolvedName = trim((string) ($data['accountName'] ?? $data['account_name'] ?? ''));
        if ($resolvedName !== '') {
            return [
                'success' => true,
                'account_number' => $accountNumber,
                'account_name' => $resolvedName,
                'bank_code' => $bankCode,
                'provider' => 'fincra',
            ];
        }
    }

    if (!app_is_production()) {
        return [
            'success' => true,
            'account_number' => $accountNumber,
            'account_name' => 'Verified Fincra Test User',
            'bank_code' => $bankCode,
            'provider' => 'fincra',
            'sandbox_fallback' => true,
        ];
    }

    return ['success' => false, 'error' => $res['error'] ?? 'Fincra could not resolve this bank account.'];
}

/**
 * Initialize Checkout for Wallet Funding
 */
function fincra_initialize_checkout(float $amount, int $userId, string $email, string $reference, string $callbackUrl, array $metadata = []): array
{
    $amount = round($amount, 2);
    $payload = [
        'amount' => $amount,
        'currency' => 'NGN',
        'customer' => [
            'name' => (string) ($metadata['name'] ?? 'NATCODEV User'),
            'email' => $email,
        ],
        'paymentReference' => $reference,
        'redirectUrl' => $callbackUrl,
        'feeBearer' => 'business',
        'metadata' => array_merge([
            'user_id' => $userId,
            'platform' => 'NATCODEV',
            'reference' => $reference,
        ], $metadata),
    ];

    $res = fincra_request('POST', '/checkout/payments', $payload);

    if ($res['success']) {
        $data = $res['data'] ?? [];
        $checkoutUrl = (string) ($data['link'] ?? $data['checkoutUrl'] ?? $data['redirectUrl'] ?? '');
        if ($checkoutUrl !== '') {
            return [
                'success' => true,
                'authorization_url' => $checkoutUrl,
                'reference' => $reference,
                'provider' => 'fincra',
                'data' => $data,
            ];
        }
    }

    // Direct sandbox checkout simulation fallback if outside production
    if (!app_is_production()) {
        $mockCheckout = $callbackUrl . (str_contains($callbackUrl, '?') ? '&' : '?') . 'reference=' . urlencode($reference) . '&provider=fincra&status=success';
        return [
            'success' => true,
            'authorization_url' => $mockCheckout,
            'reference' => $reference,
            'provider' => 'fincra',
            'sandbox_simulated' => true,
        ];
    }

    return ['success' => false, 'error' => $res['error'] ?? 'Unable to initialize Fincra checkout'];
}

/**
 * Verify payment status
 */
function fincra_verify_payment(string $reference): array
{
    $res = fincra_request('GET', '/checkout/payments/verify/' . rawurlencode($reference));
    if ($res['success']) {
        $data = $res['data'] ?? [];
        $rawStatus = strtoupper((string) ($data['status'] ?? $data['paymentStatus'] ?? ''));
        $status = in_array($rawStatus, ['SUCCESSFUL', 'SUCCESS', 'PAID', 'COMPLETED'], true) ? 'success'
            : (in_array($rawStatus, ['FAILED', 'CANCELLED'], true) ? 'failed' : 'pending');

        return [
            'success' => true,
            'status' => $status,
            'amount' => (float) ($data['amount'] ?? $data['amountPaid'] ?? 0),
            'reference' => (string) ($data['reference'] ?? $data['paymentReference'] ?? $reference),
            'paid_at' => (string) ($data['paidAt'] ?? $data['updatedAt'] ?? ''),
            'data' => $data,
        ];
    }

    return [
        'success' => false,
        'status' => 'unknown',
        'error' => $res['error'] ?? 'Unable to verify Fincra transaction',
    ];
}

/**
 * Initiate a transfer payout to a beneficiary bank account
 */
function fincra_initiate_wallet_withdrawal(array $withdrawal): array
{
    $amount = round((float) $withdrawal['final_amount'], 2);
    $reference = (string) $withdrawal['reference'];

    $payload = [
        'sourceCurrency' => 'NGN',
        'destinationCurrency' => 'NGN',
        'amount' => $amount,
        'description' => 'NATCODEV withdrawal ' . $reference,
        'customerReference' => $reference,
        'paymentDestination' => 'bank_account',
        'beneficiary' => [
            'accountNumber' => (string) $withdrawal['account_number'],
            'accountName' => (string) $withdrawal['account_name'],
            'bankCode' => (string) $withdrawal['bank_code'],
            'type' => 'individual',
        ],
        'business' => fincra_business_id(),
    ];

    $res = fincra_request('POST', '/disbursements/payouts', $payload);

    if ($res['success']) {
        $body = $res['data'] ?? [];
        return [
            'success' => true,
            'provider' => 'fincra',
            'payout_reference' => (string) ($body['reference'] ?? $reference),
            'transfer_code' => (string) ($body['id'] ?? $body['transactionReference'] ?? ''),
            'payout_status' => strtolower((string) ($body['status'] ?? 'pending')),
            'payload' => $body,
        ];
    }

    if (!app_is_production()) {
        return [
            'success' => true,
            'provider' => 'fincra',
            'payout_reference' => $reference,
            'transfer_code' => 'FINCRA-SB-' . bin2hex(random_bytes(4)),
            'payout_status' => 'initiated',
            'payload' => ['mock' => true, 'request' => $payload],
        ];
    }

    return ['success' => false, 'error' => $res['error'] ?? 'Fincra payout failed', 'response' => $res];
}

/**
 * Verify transfer payout status
 */
function fincra_verify_transfer(string $customerReference): array
{
    $res = fincra_request('GET', '/disbursements/payouts/customer-reference/' . rawurlencode($customerReference));
    if ($res['success']) {
        $body = $res['data'] ?? [];
        $rawStatus = strtoupper((string) ($body['status'] ?? ''));
        $state = in_array($rawStatus, ['SUCCESSFUL', 'SUCCESS', 'PAID'], true) ? 'success'
            : (in_array($rawStatus, ['FAILED', 'FAILURE', 'REJECTED', 'RETURNED'], true) ? 'failed' : 'pending');

        return [
            'state' => $state,
            'status' => strtolower($rawStatus),
            'reference' => $customerReference,
            'amount' => (float) ($body['amount'] ?? 0),
            'payload' => $body,
        ];
    }

    return ['state' => 'unknown', 'error' => $res['error'] ?? 'Unable to verify Fincra payout'];
}

/**
 * Validate webhook cryptographic signature
 */
function fincra_webhook_is_valid(string $rawBody, string|array $signatureOrHeaders = [], ?string $secret = null): bool
{
    $secret = $secret ?: fincra_webhook_key();
    if ($secret === '') {
        return false;
    }

    if (is_array($signatureOrHeaders)) {
        $signature = $signatureOrHeaders['signature'] ?? $signatureOrHeaders['Signature']
            ?? $signatureOrHeaders['x-fincra-signature'] ?? $signatureOrHeaders['X-Fincra-Signature']
            ?? $_SERVER['HTTP_SIGNATURE'] ?? $_SERVER['HTTP_X_FINCRA_SIGNATURE']
            ?? '';
    } else {
        $signature = (string) $signatureOrHeaders;
    }

    if ($signature === '') {
        return false;
    }

    $expected = hash_hmac('sha512', $rawBody, $secret);
    return hash_equals(strtolower((string) $signature), strtolower($expected));
}

