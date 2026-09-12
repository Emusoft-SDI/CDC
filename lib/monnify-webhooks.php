<?php

declare(strict_types=1);

require_once __DIR__ . '/monnify-client.php';
require_once __DIR__ . '/wallet.php';

function monnify_wallet_reference(int $userId): string
{
    return 'NAT-WALLET-' . $userId;
}

function monnify_ensure_reserved_account(PDO $pdo, array $user): array
{
    $wallet = wallet_get_or_create($pdo, (int) $user['id']);
    if (!empty($wallet['reserved_account_number'])) {
        return ['success' => true, 'wallet' => $wallet, 'created' => false];
    }
    if (!monnify_is_configured()) {
        return ['success' => false, 'error' => monnify_configuration_error()];
    }

    $reference = monnify_wallet_reference((int) $user['id']);
    $payload = [
        'accountReference' => $reference,
        'accountName' => 'NATCODEV ' . preg_replace('/[^a-zA-Z0-9 .-]/', '', (string) ($user['name'] ?? 'Wallet')),
        'currencyCode' => 'NGN',
        'contractCode' => monnify_env('MONNIFY_CONTRACT_CODE'),
        'customerEmail' => (string) ($user['email'] ?? ''),
        'customerName' => (string) ($user['name'] ?? 'NATCODEV User'),
    ];
    $preferredBanks = array_values(array_filter(array_map('trim', explode(',', monnify_env('MONNIFY_PREFERRED_BANK_CODES', '')))));
    if ($preferredBanks) {
        $payload['getAllAvailableBanks'] = false;
        $payload['preferredBanks'] = $preferredBanks;
    } else {
        $payload['getAllAvailableBanks'] = true;
    }

    $res = monnify_request('POST', '/api/v2/bank-transfer/reserved-accounts', $payload);
    if (!$res['success']) {
        return ['success' => false, 'error' => $res['error'] ?? 'Unable to create reserved account', 'response' => $res['raw'] ?? ''];
    }
    $body = $res['data']['responseBody'] ?? [];
    $account = $body['accounts'][0] ?? [];
    $pdo->prepare("
        UPDATE wallets
        SET reserved_account_reference = ?, reserved_account_name = ?, reserved_account_bank_name = ?,
            reserved_account_number = ?, reserved_provider = 'monnify', reserved_provider_payload = ?
        WHERE id = ?
    ")->execute([
        $reference,
        (string) ($body['accountName'] ?? $payload['accountName']),
        (string) ($account['bankName'] ?? ''),
        (string) ($account['accountNumber'] ?? ''),
        json_encode($body, JSON_UNESCAPED_SLASHES),
        (int) $wallet['id'],
    ]);
    return ['success' => true, 'wallet' => wallet_get_or_create($pdo, (int) $user['id']), 'created' => true];
}

function monnify_initialize_wallet_funding(PDO $pdo, array $user, float $amount, ?string $redirectUrl = null): array
{
    if (!monnify_is_configured()) {
        return ['success' => false, 'error' => monnify_configuration_error()];
    }
    $wallet = wallet_get_or_create($pdo, (int) $user['id']);
    $reference = 'NAT-FUND-' . (int) $user['id'] . '-' . date('ymdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
    
    if (!$redirectUrl) {
        $redirectUrl = app_base_url() . '/dashboard/wallet.php';
    }
    
    $payload = [
        'amount' => round($amount, 2),
        'customerName' => (string) ($user['name'] ?? 'NATCODEV User'),
        'customerEmail' => (string) ($user['email'] ?? ''),
        'paymentReference' => $reference,
        'paymentDescription' => 'NATCODEV wallet funding',
        'currencyCode' => 'NGN',
        'contractCode' => monnify_env('MONNIFY_CONTRACT_CODE'),
        'redirectUrl' => $redirectUrl . (str_contains($redirectUrl, '?') ? '&' : '?') . 'reference=' . urlencode($reference),
        'paymentMethods' => monnify_payment_methods(),
    ];
    $res = monnify_request('POST', '/api/v1/merchant/transactions/init-transaction', $payload);
    if (!$res['success']) {
        return ['success' => false, 'error' => $res['error'] ?? 'Unable to initialize Monnify payment'];
    }
    $body = $res['data']['responseBody'] ?? [];
    $pdo->prepare("
        INSERT INTO wallet_transactions
            (wallet_id, user_id, amount, type, direction, description, reference, provider, provider_reference, provider_payload, status)
        VALUES (?, ?, ?, 'credit', 'inflow', 'Wallet funding via Monnify checkout', ?, 'monnify', ?, ?, 'pending')
    ")->execute([
        (int) $wallet['id'],
        (int) $user['id'],
        $amount,
        $reference,
        (string) ($body['transactionReference'] ?? ''),
        json_encode($body, JSON_UNESCAPED_SLASHES),
    ]);
    return [
        'success' => true,
        'reference' => $reference,
        'transaction_reference' => (string) ($body['transactionReference'] ?? ''),
        'payment_url' => (string) ($body['checkoutUrl'] ?? ''),
    ];
}

function monnify_verify_wallet_funding(PDO $pdo, int $userId, string $reference): array
{
    $reference = trim($reference);
    $reference = preg_split('/[?&]/', $reference, 2)[0] ?? $reference;
    if ($reference === '') {
        return ['success' => false, 'error' => 'Missing payment reference'];
    }

    $stmt = $pdo->prepare("
        SELECT *
        FROM wallet_transactions
        WHERE user_id = ? AND reference = ? AND provider = 'monnify'
        LIMIT 1
    ");
    $stmt->execute([$userId, $reference]);
    $transaction = $stmt->fetch();
    if (!$transaction) {
        return ['success' => false, 'error' => 'Payment reference was not found'];
    }
    if (($transaction['status'] ?? '') === 'completed') {
        return ['success' => true, 'status' => 'completed', 'duplicate' => true];
    }

    $providerReference = (string) ($transaction['provider_reference'] ?: $reference);
    $res = monnify_request('GET', '/api/v2/transactions/' . rawurlencode($providerReference));
    if (!$res['success']) {
        return ['success' => false, 'error' => $res['error'] ?? 'Unable to verify Monnify payment'];
    }

    $body = $res['data']['responseBody'] ?? [];
    $status = strtoupper((string) ($body['paymentStatus'] ?? ''));
    if ($status !== 'PAID') {
        return ['success' => true, 'status' => strtolower($status ?: 'pending'), 'credited' => false];
    }

    $amountPaid = (float) ($body['amountPaid'] ?? 0);
    $expectedAmount = (float) ($transaction['amount'] ?? 0);
    if ($amountPaid <= 0 || $amountPaid + 0.01 < $expectedAmount) {
        return ['success' => false, 'error' => 'Payment amount is incomplete'];
    }

    return wallet_credit_once(
        $pdo,
        $userId,
        $expectedAmount,
        $reference,
        'Wallet funding via Monnify checkout',
        'monnify',
        (string) ($body['transactionReference'] ?? $providerReference),
        $body
    ) + ['status' => 'completed', 'credited' => true];
}

function monnify_initiate_wallet_withdrawal(array $withdrawal): array
{
    if (!monnify_is_configured()) {
        return ['success' => false, 'error' => monnify_configuration_error()];
    }
    $payload = [
        'amount' => round((float) $withdrawal['final_amount'], 2),
        'reference' => (string) $withdrawal['reference'],
        'narration' => 'NATCODEV withdrawal ' . (string) $withdrawal['reference'],
        'destinationBankCode' => (string) $withdrawal['bank_code'],
        'destinationAccountNumber' => (string) $withdrawal['account_number'],
        'currency' => 'NGN',
    ];
    $sourceAccount = monnify_env('MONNIFY_SOURCE_ACCOUNT_NUMBER');
    if ($sourceAccount !== '') {
        $payload['sourceAccountNumber'] = $sourceAccount;
    }
    $res = monnify_request('POST', '/api/v2/disbursements/single', $payload);
    if (!$res['success']) {
        return ['success' => false, 'error' => $res['error'] ?? 'Monnify transfer failed', 'response' => $res];
    }
    $body = $res['data']['responseBody'] ?? [];
    return [
        'success' => true,
        'provider' => 'monnify',
        'payout_reference' => (string) ($body['reference'] ?? $withdrawal['reference']),
        'transfer_code' => (string) ($body['transactionReference'] ?? ''),
        'payout_status' => strtolower((string) ($body['status'] ?? 'pending')),
        'payload' => $body,
    ];
}

function monnify_webhook_is_valid(string $rawBody, array $payload): bool
{
    $secret = monnify_env('MONNIFY_SECRET_KEY');
    if ($secret === '') {
        return false;
    }
    $headerSignature = $_SERVER['HTTP_MONNIFY_SIGNATURE'] ?? $_SERVER['HTTP_X_MONNIFY_SIGNATURE'] ?? '';
    if ($headerSignature !== '') {
        $expected = hash_hmac('sha512', $rawBody, $secret);
        if (hash_equals(strtolower((string) $headerSignature), strtolower($expected))) {
            return true;
        }
    }

    $data = $payload['eventData'] ?? $payload;
    $hash = (string) ($data['transactionHash'] ?? $payload['transactionHash'] ?? '');
    if ($hash === '') {
        return false;
    }
    $plain = $secret
        . (string) ($data['paymentReference'] ?? '')
        . (string) ($data['amountPaid'] ?? $data['amount'] ?? '')
        . (string) ($data['paidOn'] ?? '')
        . (string) ($data['transactionReference'] ?? '');
    return hash_equals(strtolower($hash), strtolower(hash('sha512', $plain)));
}