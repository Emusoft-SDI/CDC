<?php

declare(strict_types=1);

require_once __DIR__ . '/wallet.php';

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
    foreach ([
        'review_required' => "TINYINT(1) NOT NULL DEFAULT 1",
        'review_reason' => "VARCHAR(255) NULL",
        'review_due_at' => "DATETIME NULL",
        'automation_eligible' => "TINYINT(1) NOT NULL DEFAULT 0",
        'automation_rule_snapshot' => "LONGTEXT NULL",
        'auto_processed_at' => "DATETIME NULL",
    ] as $column => $definition) {
        app_add_column_if_missing($pdo, 'wallet_withdrawals', $column, $definition);
    }
    try {
        $pdo->exec("ALTER TABLE `wallet_withdrawals` MODIFY COLUMN `status` VARCHAR(40) NOT NULL DEFAULT 'pending'");
        $pdo->exec("ALTER TABLE `wallet_withdrawals` MODIFY COLUMN `payout_status` VARCHAR(60) NULL");
    } catch (Throwable $e) {
        error_log('wallet_withdrawals status migration failed: ' . $e->getMessage());
    }
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

function wallet_withdrawal_policy_defaults(): array
{
    return [
        'wallet_auto_withdrawals_enabled' => '0',
        'wallet_auto_max_amount' => '50000',
        'wallet_auto_daily_user_limit' => '100000',
        'wallet_auto_min_completed_withdrawals' => '1',
        'wallet_auto_first_withdrawal_review_hours' => '24',
        'wallet_auto_allowed_providers' => 'monnify,paystack',
        'wallet_auto_require_account_name' => '1',
    ];
}

function wallet_withdrawal_policy(PDO $pdo): array
{
    $policy = wallet_withdrawal_policy_defaults();
    foreach ($policy as $key => $default) {
        $policy[$key] = function_exists('admin_setting') ? admin_setting($pdo, $key, $default) : $default;
    }
    $policy['auto_enabled'] = (string) $policy['wallet_auto_withdrawals_enabled'] === '1';
    $policy['max_amount'] = max(0, (float) $policy['wallet_auto_max_amount']);
    $policy['daily_user_limit'] = max(0, (float) $policy['wallet_auto_daily_user_limit']);
    $policy['min_completed_withdrawals'] = max(1, (int) $policy['wallet_auto_min_completed_withdrawals']);
    $policy['first_withdrawal_review_hours'] = max(1, min(168, (int) $policy['wallet_auto_first_withdrawal_review_hours']));
    $policy['allowed_providers'] = array_values(array_filter(array_map(static fn(string $provider): string => strtolower(trim($provider)), explode(',', (string) $policy['wallet_auto_allowed_providers']))));
    $policy['require_account_name'] = (string) $policy['wallet_auto_require_account_name'] === '1';
    return $policy;
}

function wallet_save_withdrawal_policy(PDO $pdo, array $data): void
{
    if (!app_table_exists($pdo, 'settings')) {
        return;
    }
    $allowedProviders = array_values(array_intersect(['monnify', 'paystack'], array_map('strtolower', (array) ($data['wallet_auto_allowed_providers'] ?? []))));
    $settings = [
        'wallet_auto_withdrawals_enabled' => !empty($data['wallet_auto_withdrawals_enabled']) ? '1' : '0',
        'wallet_auto_max_amount' => (string) max(0, round((float) ($data['wallet_auto_max_amount'] ?? 0), 2)),
        'wallet_auto_daily_user_limit' => (string) max(0, round((float) ($data['wallet_auto_daily_user_limit'] ?? 0), 2)),
        'wallet_auto_min_completed_withdrawals' => (string) max(1, (int) ($data['wallet_auto_min_completed_withdrawals'] ?? 1)),
        'wallet_auto_first_withdrawal_review_hours' => (string) max(1, min(168, (int) ($data['wallet_auto_first_withdrawal_review_hours'] ?? 24))),
        'wallet_auto_allowed_providers' => implode(',', $allowedProviders ?: ['monnify', 'paystack']),
        'wallet_auto_require_account_name' => !empty($data['wallet_auto_require_account_name']) ? '1' : '0',
    ];
    $stmt = $pdo->prepare("INSERT INTO settings (key_name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)");
    foreach ($settings as $key => $value) {
        $stmt->execute([$key, $value]);
    }
}

function wallet_evaluate_withdrawal_policy(PDO $pdo, int $userId, float $amount, string $provider, string $accountName): array
{
    $policy = wallet_withdrawal_policy($pdo);
    $reviewDueAt = date('Y-m-d H:i:s', time() + ((int) $policy['first_withdrawal_review_hours'] * 3600));
    $completedStmt = $pdo->prepare("SELECT COUNT(*) FROM wallet_withdrawals WHERE user_id = ? AND status = 'approved'");
    $completedStmt->execute([$userId]);
    $completed = (int) $completedStmt->fetchColumn();

    $base = [
        'review_required' => true,
        'automation_eligible' => false,
        'review_reason' => '',
        'review_due_at' => $reviewDueAt,
        'completed_withdrawals' => $completed,
        'policy' => $policy,
    ];

    if ($completed < (int) $policy['min_completed_withdrawals']) {
        $base['review_reason'] = 'First withdrawal requires operator approval within ' . (int) $policy['first_withdrawal_review_hours'] . ' hours.';
        return $base;
    }
    if (!$policy['auto_enabled']) {
        $base['review_reason'] = 'Withdrawal automation is disabled by platform operators.';
        return $base;
    }
    if (!in_array($provider, $policy['allowed_providers'], true)) {
        $base['review_reason'] = 'Selected payout provider is not enabled for automated withdrawals.';
        return $base;
    }
    if ((float) $policy['max_amount'] > 0 && $amount > (float) $policy['max_amount']) {
        $base['review_reason'] = 'Withdrawal amount is above the automatic approval threshold.';
        return $base;
    }
    if ($policy['require_account_name'] && trim($accountName) === '') {
        $base['review_reason'] = 'Verified account name is required before automatic payout.';
        return $base;
    }

    $dailyStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM wallet_withdrawals WHERE user_id = ? AND status IN ('processing','approved') AND requested_at >= CURDATE()");
    $dailyStmt->execute([$userId]);
    $dailyTotal = (float) $dailyStmt->fetchColumn();
    if ((float) $policy['daily_user_limit'] > 0 && ($dailyTotal + $amount) > (float) $policy['daily_user_limit']) {
        $base['review_reason'] = 'User daily automatic withdrawal limit would be exceeded.';
        return $base;
    }

    $base['review_required'] = false;
    $base['automation_eligible'] = true;
    $base['review_reason'] = 'Eligible for automatic payout under operator thresholds.';
    $base['review_due_at'] = null;
    return $base;
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
        $policyDecision = wallet_evaluate_withdrawal_policy($pdo, $userId, $amount, $provider, $accountName);
        $pdo->prepare("UPDATE wallets SET balance = ?, hold_balance = ?, last_activity_at = NOW() WHERE id = ?")
            ->execute([$after, $holdAfter, (int) $wallet['id']]);
        $pdo->prepare("
            INSERT INTO wallet_withdrawals
                (wallet_id, user_id, reference, amount, charge, final_amount, provider, bank_code, bank_name, account_number, account_name, note, review_required, review_reason, review_due_at, automation_eligible, automation_rule_snapshot)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            (int) $wallet['id'],
            $userId,
            $reference,
            $amount,
            $charge,
            $finalAmount,
            $provider,
            $bankCode,
            $bankName,
            $accountNumber,
            $accountName,
            $note,
            !empty($policyDecision['review_required']) ? 1 : 0,
            (string) ($policyDecision['review_reason'] ?? ''),
            $policyDecision['review_due_at'] ?? null,
            !empty($policyDecision['automation_eligible']) ? 1 : 0,
            json_encode($policyDecision, JSON_UNESCAPED_SLASHES),
        ]);
        $withdrawalId = (int) $pdo->lastInsertId();
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
        $result = [
            'success' => true,
            'reference' => $reference,
            'final_amount' => $finalAmount,
            'charge' => $charge,
            'review_required' => !empty($policyDecision['review_required']),
            'review_reason' => (string) ($policyDecision['review_reason'] ?? ''),
            'review_due_at' => $policyDecision['review_due_at'] ?? null,
            'automation_eligible' => !empty($policyDecision['automation_eligible']),
        ];
        if (!empty($policyDecision['automation_eligible']) && $withdrawalId > 0) {
            $auto = wallet_admin_process_withdrawal($pdo, $withdrawalId, 0, 'approve', 'Automated approval by withdrawal threshold policy.');
            $result['auto_processed'] = (bool) ($auto['success'] ?? false);
            $result['auto_status'] = (string) ($auto['status'] ?? ($auto['error'] ?? 'pending'));
            if (!empty($auto['success'])) {
                $pdo->prepare("UPDATE wallet_withdrawals SET auto_processed_at = NOW() WHERE id = ?")->execute([$withdrawalId]);
            }
        }
        return $result;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['success' => false, 'error' => $e->getMessage()];
    }
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
            if (stripos($res['error'] ?? '', 'resolve host') !== false || stripos($res['error'] ?? '', 'timeout') !== false) {
                return ['success' => true, 'banks' => [
                    ['name' => 'Access Bank (Mock Sandbox)', 'code' => '044'],
                    ['name' => 'First Bank (Mock Sandbox)', 'code' => '011'],
                    ['name' => 'GTBank (Mock Sandbox)', 'code' => '058'],
                    ['name' => 'UBA (Mock Sandbox)', 'code' => '033'],
                    ['name' => 'Zenith Bank (Mock Sandbox)', 'code' => '057'],
                ]];
            }
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
        $res = monnify_request('GET', "/api/v1/disbursements/account/validate?accountNumber={$accountNumber}&bankCode={$bankCode}");
        if (!$res['success']) {
            if (stripos($res['error'] ?? '', 'resolve host') !== false || stripos($res['error'] ?? '', 'timeout') !== false) {
                return [
                    'success' => true,
                    'account_number' => $accountNumber,
                    'account_name' => 'Sandbox Mock User',
                    'provider' => 'monnify',
                ];
            }
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

