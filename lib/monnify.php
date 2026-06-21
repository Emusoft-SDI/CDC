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

function wallet_ensure_schema(PDO $pdo): void
{
    app_ensure_farmer_engagement_schema($pdo);
    foreach ([
        'currency' => "VARCHAR(10) NOT NULL DEFAULT 'NGN'",
        'reserved_account_reference' => "VARCHAR(100) NULL",
        'reserved_account_name' => "VARCHAR(180) NULL",
        'reserved_account_bank_name' => "VARCHAR(160) NULL",
        'reserved_account_number' => "VARCHAR(40) NULL",
        'reserved_provider' => "VARCHAR(40) NULL",
        'reserved_provider_payload' => "LONGTEXT NULL",
        'hold_balance' => "DECIMAL(12,2) NOT NULL DEFAULT 0",
        'status' => "VARCHAR(40) NOT NULL DEFAULT 'active'",
    ] as $column => $definition) {
        app_add_column_if_missing($pdo, 'wallets', $column, $definition);
    }
    foreach ([
        'user_id' => "INT NULL",
        'direction' => "VARCHAR(20) NULL",
        'provider' => "VARCHAR(40) NULL",
        'provider_reference' => "VARCHAR(120) NULL",
        'provider_payload' => "LONGTEXT NULL",
        'balance_before' => "DECIMAL(12,2) NULL",
        'balance_after' => "DECIMAL(12,2) NULL",
        'completed_at' => "DATETIME NULL",
    ] as $column => $definition) {
        app_add_column_if_missing($pdo, 'wallet_transactions', $column, $definition);
    }
    // Ensure wallet_transactions.type is large enough
    if (app_column_exists($pdo, 'wallet_transactions', 'type')) {
        $stmt = $pdo->prepare("
            SELECT CHARACTER_MAXIMUM_LENGTH
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'wallet_transactions'
              AND COLUMN_NAME = 'type'
        ");
        $stmt->execute();
        $currentLength = (int) ($stmt->fetchColumn() ?: 0);

        if ($currentLength < 50) { // If current length is less than 50, alter it
            $pdo->exec("ALTER TABLE `wallet_transactions` MODIFY COLUMN `type` VARCHAR(50) NOT NULL");
        }
    }
    try {
        $pdo->exec("ALTER TABLE wallet_transactions ADD UNIQUE KEY uniq_wallet_transactions_reference (reference)");
    } catch (Throwable $e) {
    }
    try {
        $pdo->exec("CREATE INDEX idx_wallet_transactions_user ON wallet_transactions (user_id, created_at)");
    } catch (Throwable $e) {
    }
    wallet_withdrawals_ensure_schema($pdo);
}

function wallet_withdrawals_ensure_schema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS wallet_withdrawals (
            id INT AUTO_INCREMENT PRIMARY KEY,
            wallet_id INT NOT NULL,
            user_id INT NOT NULL,
            reference VARCHAR(100) NOT NULL UNIQUE,
            amount DECIMAL(12,2) NOT NULL,
            charge DECIMAL(12,2) NOT NULL DEFAULT 0,
            final_amount DECIMAL(12,2) NOT NULL,
            currency VARCHAR(10) NOT NULL DEFAULT 'NGN',
            provider VARCHAR(40) NOT NULL DEFAULT 'manual',
            bank_code VARCHAR(40) NULL,
            bank_name VARCHAR(180) NULL,
            account_number VARCHAR(40) NOT NULL,
            account_name VARCHAR(180) NOT NULL,
            note TEXT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'pending',
            payout_status VARCHAR(60) NULL,
            payout_reference VARCHAR(140) NULL,
            payout_transfer_code VARCHAR(160) NULL,
            provider_payload LONGTEXT NULL,
            admin_note TEXT NULL,
            requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            reviewed_by INT NULL,
            reviewed_at DATETIME NULL,
            completed_at DATETIME NULL,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_wallet_withdrawals_user (user_id, requested_at),
            INDEX idx_wallet_withdrawals_status (status, requested_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'wallet_withdrawals');
}

function wallet_withdrawal_min_amount(): float
{
    return max(100, (float) app_env('WALLET_WITHDRAWAL_MIN_AMOUNT', '1000'));
}

function wallet_withdrawal_charge(float $amount): float
{
    $flat = max(0, (float) app_env('WALLET_WITHDRAWAL_FLAT_FEE', '100'));
    $percent = max(0, (float) app_env('WALLET_WITHDRAWAL_PERCENT_FEE', '0'));
    return round($flat + (($amount * $percent) / 100), 2);
}

function wallet_withdrawal_reference(int $userId): string
{
    return 'NAT-WD-' . $userId . '-' . date('ymdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

function wallet_request_withdrawal(PDO $pdo, array $user, array $data): array
{
    wallet_ensure_schema($pdo);
    $userId = (int) ($user['id'] ?? 0);
    $amount = round((float) ($data['amount'] ?? 0), 2);
    $provider = strtolower(trim((string) ($data['provider'] ?? 'manual')));
    if (!in_array($provider, ['manual', 'monnify', 'paystack'], true)) {
        $provider = 'manual';
    }
    $bankCode = trim((string) ($data['bank_code'] ?? ''));
    $bankName = trim((string) ($data['bank_name'] ?? ''));
    $accountNumber = preg_replace('/[^0-9]/', '', (string) ($data['account_number'] ?? ''));
    $accountName = trim((string) ($data['account_name'] ?? ''));
    $note = trim((string) ($data['note'] ?? ''));
    $min = wallet_withdrawal_min_amount();

    if ($userId <= 0) {
        return ['success' => false, 'error' => 'Login required.'];
    }
    if ($amount < $min) {
        return ['success' => false, 'error' => 'Minimum withdrawal amount is NGN ' . number_format($min, 2) . '.'];
    }
    if ($accountNumber === '' || $accountName === '') {
        return ['success' => false, 'error' => 'Account name and account number are required.'];
    }
    if (in_array($provider, ['monnify', 'paystack'], true) && $bankCode === '') {
        return ['success' => false, 'error' => ucfirst($provider) . ' withdrawal requires a bank code.'];
    }

    $charge = wallet_withdrawal_charge($amount);
    $finalAmount = round($amount - $charge, 2);
    if ($finalAmount <= 0) {
        return ['success' => false, 'error' => 'Withdrawal amount must be greater than the withdrawal charge.'];
    }

    $pdo->beginTransaction();
    try {
        $wallet = wallet_get_or_create($pdo, $userId);
        $lock = $pdo->prepare("SELECT * FROM wallets WHERE id = ? FOR UPDATE");
        $lock->execute([(int) $wallet['id']]);
        $wallet = $lock->fetch();
        if (!$wallet) {
            throw new RuntimeException('Wallet was not found.');
        }
        if (!in_array((string) ($wallet['status'] ?? 'active'), ['', 'active'], true)) {
            throw new RuntimeException('Wallet is not active for withdrawals.');
        }
        $before = (float) ($wallet['balance'] ?? 0);
        if ($before + 0.01 < $amount) {
            throw new RuntimeException('Insufficient wallet balance.');
        }

        $after = $before - $amount;
        $holdAfter = (float) ($wallet['hold_balance'] ?? 0) + $amount;
        $reference = wallet_withdrawal_reference($userId);
        $pdo->prepare("UPDATE wallets SET balance = ?, hold_balance = ?, last_activity_at = NOW() WHERE id = ?")
            ->execute([$after, $holdAfter, (int) $wallet['id']]);
        $pdo->prepare("
            INSERT INTO wallet_withdrawals
                (wallet_id, user_id, reference, amount, charge, final_amount, provider, bank_code, bank_name, account_number, account_name, note)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([(int) $wallet['id'], $userId, $reference, $amount, $charge, $finalAmount, $provider, $bankCode, $bankName, $accountNumber, $accountName, $note]);
        $pdo->prepare("
            INSERT INTO wallet_transactions
                (wallet_id, user_id, amount, type, direction, description, reference, provider, provider_reference, provider_payload, status, balance_before, balance_after)
            VALUES (?, ?, ?, 'withdrawal', 'outflow', ?, ?, ?, ?, ?, 'pending', ?, ?)
        ")->execute([
            (int) $wallet['id'],
            $userId,
            $amount,
            'Withdrawal request to ' . ($bankName ?: 'bank account'),
            $reference,
            $provider,
            $reference,
            json_encode(['charge' => $charge, 'final_amount' => $finalAmount, 'account_number' => $accountNumber, 'account_name' => $accountName, 'bank_code' => $bankCode, 'bank_name' => $bankName], JSON_UNESCAPED_SLASHES),
            $before,
            $after,
        ]);
        $pdo->commit();
        return ['success' => true, 'reference' => $reference, 'final_amount' => $finalAmount, 'charge' => $charge];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function wallet_get_or_create(PDO $pdo, int $userId): array
{
    if (!$pdo->inTransaction()) {
        wallet_ensure_schema($pdo);
    }
    $pdo->prepare("INSERT IGNORE INTO wallets (user_id) VALUES (?)")->execute([$userId]);
    $stmt = $pdo->prepare("SELECT * FROM wallets WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    return $stmt->fetch() ?: [];
}

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

function paystack_initiate_wallet_withdrawal(array $withdrawal): array
{
    if (!paystack_is_configured()) {
        return ['success' => false, 'error' => paystack_configuration_error()];
    }
    $recipient = paystack_request('POST', '/transferrecipient', [
        'type' => 'nuban',
        'name' => (string) $withdrawal['account_name'],
        'account_number' => (string) $withdrawal['account_number'],
        'bank_code' => (string) $withdrawal['bank_code'],
        'currency' => 'NGN',
    ]);
    $recipientData = $recipient['data']['data'] ?? [];
    $recipientCode = (string) ($recipientData['recipient_code'] ?? '');
    if (!$recipient['success'] || $recipientCode === '') {
        return ['success' => false, 'error' => $recipient['error'] ?? 'Unable to create Paystack recipient', 'response' => $recipient];
    }

    $transfer = paystack_request('POST', '/transfer', [
        'source' => 'balance',
        'amount' => (int) round(((float) $withdrawal['final_amount']) * 100),
        'recipient' => $recipientCode,
        'reason' => 'NATCODEV withdrawal ' . (string) $withdrawal['reference'],
        'reference' => (string) $withdrawal['reference'],
    ]);
    if (!$transfer['success']) {
        return ['success' => false, 'error' => $transfer['error'] ?? 'Paystack transfer failed', 'response' => $transfer];
    }
    $body = $transfer['data']['data'] ?? [];
    return [
        'success' => true,
        'provider' => 'paystack',
        'payout_reference' => (string) ($body['reference'] ?? $withdrawal['reference']),
        'transfer_code' => (string) ($body['transfer_code'] ?? ''),
        'payout_status' => strtolower((string) ($body['status'] ?? 'pending')),
        'payload' => ['recipient' => $recipientData, 'transfer' => $body],
    ];
}

function wallet_payout_banks(string $provider): array
{
    $provider = strtolower($provider);
    if ($provider === 'paystack') {
        $res = paystack_request('GET', '/bank', ['country' => 'nigeria', 'currency' => 'NGN']);
        if (!$res['success']) {
            return ['success' => false, 'error' => $res['error'] ?? 'Unable to load Paystack banks'];
        }
        $banks = array_map(static fn(array $bank): array => [
            'name' => (string) ($bank['name'] ?? ''),
            'code' => (string) ($bank['code'] ?? ''),
        ], $res['data']['data'] ?? []);
        return ['success' => true, 'banks' => array_values(array_filter($banks, static fn(array $bank): bool => $bank['name'] !== '' && $bank['code'] !== ''))];
    }

    if ($provider === 'monnify') {
        if (!monnify_is_configured()) {
            return ['success' => false, 'error' => monnify_configuration_error()];
        }
        $res = monnify_request('GET', '/api/v1/banks');
        if (!$res['success']) {
            return ['success' => false, 'error' => $res['error'] ?? 'Unable to load Monnify banks'];
        }
        $banks = array_map(static fn(array $bank): array => [
            'name' => (string) ($bank['name'] ?? $bank['bankName'] ?? ''),
            'code' => (string) ($bank['code'] ?? $bank['bankCode'] ?? ''),
        ], $res['data']['responseBody'] ?? []);
        return ['success' => true, 'banks' => array_values(array_filter($banks, static fn(array $bank): bool => $bank['name'] !== '' && $bank['code'] !== ''))];
    }

    return ['success' => false, 'error' => 'Select Monnify or Paystack to verify a bank account.'];
}

function wallet_resolve_payout_account(string $provider, string $accountNumber, string $bankCode): array
{
    $provider = strtolower($provider);
    $accountNumber = preg_replace('/[^0-9]/', '', $accountNumber);
    $bankCode = trim($bankCode);
    if ($accountNumber === '' || $bankCode === '') {
        return ['success' => false, 'error' => 'Bank and account number are required.'];
    }

    if ($provider === 'paystack') {
        $res = paystack_request('GET', '/bank/resolve', ['account_number' => $accountNumber, 'bank_code' => $bankCode]);
        if (!$res['success']) {
            return ['success' => false, 'error' => $res['error'] ?? 'Unable to resolve Paystack account'];
        }
        $data = $res['data']['data'] ?? [];
        return [
            'success' => true,
            'account_number' => (string) ($data['account_number'] ?? $accountNumber),
            'account_name' => (string) ($data['account_name'] ?? ''),
            'provider' => 'paystack',
        ];
    }

    if ($provider === 'monnify') {
        if (!monnify_is_configured()) {
            return ['success' => false, 'error' => monnify_configuration_error()];
        }
        $res = monnify_request('GET', '/api/v1/disbursements/account/validate?' . http_build_query([
            'accountNumber' => $accountNumber,
            'bankCode' => $bankCode,
        ]));
        if (!$res['success']) {
            return ['success' => false, 'error' => $res['error'] ?? 'Unable to resolve Monnify account'];
        }
        $data = $res['data']['responseBody'] ?? [];
        return [
            'success' => true,
            'account_number' => (string) ($data['accountNumber'] ?? $accountNumber),
            'account_name' => (string) ($data['accountName'] ?? ''),
            'provider' => 'monnify',
        ];
    }

    return ['success' => false, 'error' => 'Select Monnify or Paystack to verify a bank account.'];
}

function wallet_admin_process_withdrawal(PDO $pdo, int $withdrawalId, int $adminId, string $decision, string $adminNote = ''): array
{
    wallet_ensure_schema($pdo);
    $decision = strtolower($decision);
    if (!in_array($decision, ['approve', 'reject'], true)) {
        return ['success' => false, 'error' => 'Invalid withdrawal action.'];
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT * FROM wallet_withdrawals WHERE id = ? FOR UPDATE");
        $stmt->execute([$withdrawalId]);
        $withdrawal = $stmt->fetch();
        if (!$withdrawal) {
            throw new RuntimeException('Withdrawal request was not found.');
        }
        if ((string) $withdrawal['status'] !== 'pending') {
            throw new RuntimeException('Only pending withdrawals can be processed.');
        }
        $walletStmt = $pdo->prepare("SELECT * FROM wallets WHERE id = ? FOR UPDATE");
        $walletStmt->execute([(int) $withdrawal['wallet_id']]);
        $wallet = $walletStmt->fetch();
        if (!$wallet) {
            throw new RuntimeException('Linked wallet was not found.');
        }

        if ($decision === 'reject') {
            $balance = (float) $wallet['balance'] + (float) $withdrawal['amount'];
            $hold = max(0, (float) $wallet['hold_balance'] - (float) $withdrawal['amount']);
            $pdo->prepare("UPDATE wallets SET balance = ?, hold_balance = ?, last_activity_at = NOW() WHERE id = ?")
                ->execute([$balance, $hold, (int) $wallet['id']]);
            $pdo->prepare("UPDATE wallet_withdrawals SET status = 'rejected', payout_status = 'rejected', admin_note = ?, reviewed_by = ?, reviewed_at = NOW(), updated_at = NOW() WHERE id = ?")
                ->execute([$adminNote, $adminId, $withdrawalId]);
            $pdo->prepare("UPDATE wallet_transactions SET status = 'rejected', provider_payload = ? WHERE reference = ?")
                ->execute([json_encode(['admin_note' => $adminNote, 'decision' => 'rejected'], JSON_UNESCAPED_SLASHES), (string) $withdrawal['reference']]);
            $pdo->commit();
            return ['success' => true, 'status' => 'rejected'];
        }

        $pdo->prepare("UPDATE wallet_withdrawals SET status = 'processing', admin_note = ?, reviewed_by = ?, reviewed_at = NOW(), updated_at = NOW() WHERE id = ?")
            ->execute([$adminNote, $adminId, $withdrawalId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['success' => false, 'error' => $e->getMessage()];
    }

    $provider = strtolower((string) $withdrawal['provider']);
    if ($provider === 'monnify') {
        $payout = monnify_initiate_wallet_withdrawal($withdrawal);
    } elseif ($provider === 'paystack') {
        $payout = paystack_initiate_wallet_withdrawal($withdrawal);
    } else {
        $payout = [
            'success' => true,
            'provider' => 'manual',
            'payout_reference' => (string) $withdrawal['reference'],
            'transfer_code' => '',
            'payout_status' => 'manual_review',
            'payload' => ['admin_note' => $adminNote],
        ];
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT * FROM wallet_withdrawals WHERE id = ? FOR UPDATE");
        $stmt->execute([$withdrawalId]);
        $current = $stmt->fetch();
        $walletStmt = $pdo->prepare("SELECT * FROM wallets WHERE id = ? FOR UPDATE");
        $walletStmt->execute([(int) $withdrawal['wallet_id']]);
        $wallet = $walletStmt->fetch();
        if (!$current || !$wallet) {
            throw new RuntimeException('Withdrawal state changed before payout update.');
        }
        if (!$payout['success']) {
            $pdo->prepare("UPDATE wallet_withdrawals SET status = 'pending', payout_status = 'failed', admin_note = ?, provider_payload = ?, updated_at = NOW() WHERE id = ?")
                ->execute([($payout['error'] ?? 'Payout failed'), json_encode($payout, JSON_UNESCAPED_SLASHES), $withdrawalId]);
            $pdo->commit();
            return ['success' => false, 'error' => $payout['error'] ?? 'Payout failed.'];
        }

        $hold = max(0, (float) $wallet['hold_balance'] - (float) $withdrawal['amount']);
        $pdo->prepare("UPDATE wallets SET hold_balance = ?, last_activity_at = NOW() WHERE id = ?")
            ->execute([$hold, (int) $wallet['id']]);
        $pdo->prepare("
            UPDATE wallet_withdrawals
            SET status = 'approved', payout_status = ?, payout_reference = ?, payout_transfer_code = ?,
                provider_payload = ?, completed_at = NOW(), updated_at = NOW()
            WHERE id = ?
        ")->execute([
            (string) ($payout['payout_status'] ?? 'initiated'),
            (string) ($payout['payout_reference'] ?? $withdrawal['reference']),
            (string) ($payout['transfer_code'] ?? ''),
            json_encode($payout['payload'] ?? $payout, JSON_UNESCAPED_SLASHES),
            $withdrawalId,
        ]);
        $pdo->prepare("
            UPDATE wallet_transactions
            SET status = 'completed', provider_reference = ?, provider_payload = ?, completed_at = NOW()
            WHERE reference = ?
        ")->execute([
            (string) ($payout['payout_reference'] ?? $withdrawal['reference']),
            json_encode($payout['payload'] ?? $payout, JSON_UNESCAPED_SLASHES),
            (string) $withdrawal['reference'],
        ]);
        $pdo->commit();
        return ['success' => true, 'status' => 'approved', 'provider' => $provider, 'payout_status' => (string) ($payout['payout_status'] ?? 'initiated')];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function wallet_credit_once(PDO $pdo, int $userId, float $amount, string $reference, string $description, string $provider, ?string $providerReference, array $payload): array
{
    wallet_ensure_schema($pdo);
    $pdo->beginTransaction();
    try {
        $wallet = wallet_get_or_create($pdo, $userId);
        $lock = $pdo->prepare("SELECT * FROM wallets WHERE id = ? FOR UPDATE");
        $lock->execute([(int) $wallet['id']]);
        $wallet = $lock->fetch();

        $existing = $pdo->prepare("SELECT id, status FROM wallet_transactions WHERE reference = ? LIMIT 1");
        $existing->execute([$reference]);
        $existingRow = $existing->fetch();
        if ($existingRow && $existingRow['status'] === 'completed') {
            if ($pdo->inTransaction()) {
                $pdo->commit();
            }
            return ['success' => true, 'duplicate' => true];
        }

        $before = (float) $wallet['balance'];
        $after = $before + $amount;
        if ($existingRow) {
            $pdo->prepare("
                UPDATE wallet_transactions
                SET status = 'completed', amount = ?, provider_reference = ?, provider_payload = ?,
                    balance_before = ?, balance_after = ?, completed_at = NOW()
                WHERE id = ?
            ")->execute([$amount, $providerReference, json_encode($payload, JSON_UNESCAPED_SLASHES), $before, $after, (int) $existingRow['id']]);
        } else {
            $pdo->prepare("
                INSERT INTO wallet_transactions
                    (wallet_id, user_id, amount, type, direction, description, reference, provider, provider_reference, provider_payload, status, balance_before, balance_after, completed_at)
                VALUES (?, ?, ?, 'credit', 'inflow', ?, ?, ?, ?, ?, 'completed', ?, ?, NOW())
            ")->execute([
                (int) $wallet['id'],
                $userId,
                $amount,
                $description,
                $reference,
                $provider,
                $providerReference,
                json_encode($payload, JSON_UNESCAPED_SLASHES),
                $before,
                $after,
            ]);
        }
        $pdo->prepare("UPDATE wallets SET balance = ? WHERE id = ?")->execute([$after, (int) $wallet['id']]);
        if ($pdo->inTransaction()) {
            $pdo->commit();
        }
        return ['success' => true, 'duplicate' => false, 'balance' => $after];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function wallet_admin_credit(PDO $pdo, int $userId, float $amount, int $adminId, string $note = ''): array
{
    wallet_ensure_schema($pdo);
    if ($userId <= 0) {
        return ['success' => false, 'error' => 'Select a valid user.'];
    }
    if (!is_finite($amount) || $amount <= 0) {
        return ['success' => false, 'error' => 'Enter a positive funding amount.'];
    }

    $maxAmount = (float) app_env('ADMIN_WALLET_FUNDING_MAX_AMOUNT', '10000000');
    if ($maxAmount > 0 && $amount > $maxAmount) {
        return ['success' => false, 'error' => 'Funding amount exceeds the configured admin limit.'];
    }

    $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) {
        return ['success' => false, 'error' => 'User account was not found.'];
    }

    $reference = 'ADMIN-FUND-' . $userId . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
    $payload = [
        'admin_id' => $adminId,
        'user_id' => $userId,
        'note' => mb_substr(trim($note), 0, 500),
        'source' => 'admin_wallet_funding',
    ];

    try {
        $credit = wallet_credit_once(
            $pdo,
            $userId,
            round($amount, 2),
            $reference,
            'Admin wallet funding',
            'admin',
            $reference,
            $payload
        );
        return [
            'success' => true,
            'reference' => $reference,
            'balance' => $credit['balance'] ?? null,
            'user' => $user,
        ];
    } catch (Throwable $e) {
        error_log('Admin wallet credit failed: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Wallet funding could not be completed.'];
    }
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
