<?php
declare(strict_types=1);

/**
 * Paystack Integration for NATCODEV
 * Handles bank resolution, transfers, and transaction verification
 */

function paystack_is_configured(): bool
{
    $secretKey = (string) app_env('PAYSTACK_SECRET_KEY', '');
    return $secretKey !== '';
}

function paystack_configuration_error(): string
{
    return 'Paystack is not configured. Add PAYSTACK_SECRET_KEY to .env file.';
}

function paystack_request(string $method, string $endpoint, array $payload = []): array
{
    if (!paystack_is_configured()) {
        return ['success' => false, 'error' => paystack_configuration_error()];
    }

    $secretKey = (string) app_env('PAYSTACK_SECRET_KEY', '');
    $baseUrl = strtolower((string) app_env('PAYSTACK_IS_LIVE', 'false')) === 'true'
        ? 'https://api.paystack.co'
        : 'https://api.paystack.co'; // Paystack uses same URL for test/live

    $url = rtrim($baseUrl, '/') . '/' . ltrim($endpoint, '/');

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $secretKey,
            'Content-Type: application/json',
        ],
    ]);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_SLASHES));
    } elseif ($method === 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_SLASHES));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        error_log('Paystack request failed: ' . $error);
        return ['success' => false, 'error' => 'Paystack API request failed: ' . $error];
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        return ['success' => false, 'error' => 'Invalid Paystack response'];
    }

    if ($httpCode >= 400 || !($data['status'] ?? false)) {
        $errorMessage = $data['message'] ?? 'Paystack API error';
        error_log('Paystack API error: ' . $errorMessage);
        return ['success' => false, 'error' => $errorMessage, 'data' => $data];
    }

    return ['success' => true, 'data' => $data['data'] ?? []];
}

/**
 * Resolve bank account details
 * Returns account name if valid
 */
function paystack_resolve_account(string $bankCode, string $accountNumber): array
{
    if (!paystack_is_configured()) {
        return ['success' => false, 'error' => paystack_configuration_error()];
    }

    if (!preg_match('/^\d{10}$/', $accountNumber)) {
        return ['success' => false, 'error' => 'Account number must be 10 digits'];
    }

    $response = paystack_request('GET', '/bank/resolve?account_number=' . urlencode($accountNumber) . '&bank_code=' . urlencode($bankCode));

    if (!$response['success']) {
        return $response;
    }

    $data = $response['data'];
    $accountName = (string) ($data['account_name'] ?? '');

    if ($accountName === '') {
        return ['success' => false, 'error' => 'Could not resolve account name'];
    }

    return [
        'success' => true,
        'account_name' => $accountName,
        'account_number' => $accountNumber,
        'bank_code' => $bankCode,
    ];
}

/**
 * Get list of Nigerian banks
 */
function paystack_list_banks(): array
{
    if (!paystack_is_configured()) {
        return ['success' => false, 'error' => paystack_configuration_error(), 'banks' => []];
    }

    $response = paystack_request('GET', '/bank?currency=NGN&perPage=100&page=1');

    if (!$response['success']) {
        return ['success' => false, 'error' => $response['error'], 'banks' => []];
    }

    $banks = [];
    foreach ($response['data'] as $bank) {
        $banks[] = [
            'name' => (string) $bank['name'],
            'code' => (string) $bank['code'],
        ];
    }

    return ['success' => true, 'banks' => $banks];
}

/**
 * Initiate a transfer to a bank account
 */
function paystack_transfer(string $bankCode, string $accountNumber, float $amount, string $reference, string $reason = ''): array
{
    if (!paystack_is_configured()) {
        return ['success' => false, 'error' => paystack_configuration_error()];
    }

    // Step 1: Create transfer recipient
    $recipientPayload = [
        'type' => 'nuban',
        'name' => 'NATCODEV Withdrawal',
        'description' => $reason !== '' ? $reason : 'Wallet withdrawal',
        'account_number' => $accountNumber,
        'bank_code' => $bankCode,
        'currency' => 'NGN',
    ];

    $recipientResponse = paystack_request('POST', '/transferrecipient', $recipientPayload);

    if (!$recipientResponse['success']) {
        return ['success' => false, 'error' => 'Failed to create transfer recipient: ' . ($recipientResponse['error'] ?? 'Unknown error')];
    }

    $recipientCode = (string) ($recipientResponse['data']['recipient_code'] ?? '');
    if ($recipientCode === '') {
        return ['success' => false, 'error' => 'Paystack did not return recipient code'];
    }

    // Step 2: Initiate transfer
    $transferPayload = [
        'source' => 'balance',
        'reason' => $reason !== '' ? $reason : 'NATCODEV wallet withdrawal',
        'amount' => (int) round($amount * 100), // Paystack uses kobo
        'recipient' => $recipientCode,
        'reference' => $reference,
    ];

    $transferResponse = paystack_request('POST', '/transfer', $transferPayload);

    if (!$transferResponse['success']) {
        return ['success' => false, 'error' => 'Transfer failed: ' . ($transferResponse['error'] ?? 'Unknown error')];
    }

    return [
        'success' => true,
        'transfer_code' => (string) ($transferResponse['data']['transfer_code'] ?? ''),
        'reference' => $reference,
        'amount' => $amount,
    ];
}

/**
 * Verify transfer status
 */
function paystack_verify_transfer(string $transferCode): array
{
    if (!paystack_is_configured()) {
        return ['success' => false, 'error' => paystack_configuration_error()];
    }

    $response = paystack_request('GET', '/transfer/verify/' . urlencode($transferCode));

    if (!$response['success']) {
        return $response;
    }

    $data = $response['data'];
    $status = strtolower((string) ($data['status'] ?? ''));

    return [
        'success' => true,
        'status' => $status, // success, pending, failed
        'amount' => ((float) ($data['amount'] ?? 0)) / 100,
        'reference' => (string) ($data['reference'] ?? ''),
    ];
}

/**
 * Initialize a payment (for wallet funding)
 */
function paystack_initialize_payment(float $amount, string $email, string $reference, string $callbackUrl): array
{
    if (!paystack_is_configured()) {
        return ['success' => false, 'error' => paystack_configuration_error()];
    }

    $payload = [
        'amount' => (int) round($amount * 100), // Paystack uses kobo
        'email' => $email,
        'reference' => $reference,
        'callback_url' => $callbackUrl,
        'currency' => 'NGN',
        'metadata' => [
            'custom_fields' => [
                [
                    'display_name' => 'Platform',
                    'variable_name' => 'platform',
                    'value' => 'NATCODEV',
                ],
            ],
        ],
    ];

    $response = paystack_request('POST', '/transaction/initialize', $payload);

    if (!$response['success']) {
        return $response;
    }

    return [
        'success' => true,
        'authorization_url' => (string) ($response['data']['authorization_url'] ?? ''),
        'access_code' => (string) ($response['data']['access_code'] ?? ''),
        'reference' => (string) ($response['data']['reference'] ?? ''),
    ];
}

/**
 * Verify payment status
 */
function paystack_verify_payment(string $reference): array
{
    if (!paystack_is_configured()) {
        return ['success' => false, 'error' => paystack_configuration_error()];
    }

    $response = paystack_request('GET', '/transaction/verify/' . urlencode($reference));

    if (!$response['success']) {
        return $response;
    }

    $data = $response['data'];
    $status = strtolower((string) ($data['status'] ?? ''));
    $amount = ((float) ($data['amount'] ?? 0)) / 100;

    return [
        'success' => true,
        'status' => $status, // success, abandoned, failed
        'amount' => $amount,
        'reference' => (string) ($data['reference'] ?? ''),
        'paid_at' => (string) ($data['paid_at'] ?? ''),
    ];
}