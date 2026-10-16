<?php

declare(strict_types=1);

require_once __DIR__ . '/wallet.php';
require_once __DIR__ . '/circuit-breaker.php';

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
        // Written only by an explicit cancel/refund action, so one withdrawal can never
        // be refunded twice.
        'funds_released' => "TINYINT(1) NOT NULL DEFAULT 0",
        'failure_reason' => "VARCHAR(255) NULL",
        'preflight_buffered' => "TINYINT(1) NOT NULL DEFAULT 0",
        'preflight_buffered_until' => "DATETIME NULL",
        'total_debited' => "DECIMAL(12,2) NOT NULL DEFAULT 0",
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

function wallet_withdrawal_charge(float $amount, ?PDO $pdo = null): float
{
    $flat = $pdo !== null && function_exists('admin_setting')
        ? (float) admin_setting($pdo, 'wallet_withdrawal_flat_fee', app_env('WALLET_WITHDRAWAL_FLAT_FEE', '0'))
        : (float) app_env('WALLET_WITHDRAWAL_FLAT_FEE', '0');
    $percent = $pdo !== null && function_exists('admin_setting')
        ? (float) admin_setting($pdo, 'wallet_withdrawal_percent_fee', app_env('WALLET_WITHDRAWAL_PERCENT_FEE', '0'))
        : (float) app_env('WALLET_WITHDRAWAL_PERCENT_FEE', '0');
    return round(max(0, $flat) + (($amount * max(0, $percent)) / 100), 2);
}

function wallet_deposit_charge(PDO $pdo, float $amount): float
{
    $flat = function_exists('admin_setting')
        ? (float) admin_setting($pdo, 'wallet_deposit_flat_fee', app_env('WALLET_DEPOSIT_FLAT_FEE', '0'))
        : (float) app_env('WALLET_DEPOSIT_FLAT_FEE', '0');
    $percent = function_exists('admin_setting')
        ? (float) admin_setting($pdo, 'wallet_deposit_percent_fee', app_env('WALLET_DEPOSIT_PERCENT_FEE', '0'))
        : (float) app_env('WALLET_DEPOSIT_PERCENT_FEE', '0');
    return round(max(0, $flat) + (($amount * max(0, $percent)) / 100), 2);
}

function wallet_withdrawal_reference(int $userId): string
{
    return 'NAT-WD-' . $userId . '-' . date('ymdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

function wallet_withdrawal_policy_defaults(): array
{
    return [
        'wallet_auto_withdrawals_enabled' => '0',
        'wallet_auto_preflight_buffer_enabled' => '0',
        'wallet_auto_preflight_buffer_minutes' => '3',
        'wallet_auto_global_daily_cap' => '1000000',
        'wallet_auto_max_amount' => '50000',
        'wallet_auto_daily_user_limit' => '100000',
        'wallet_auto_min_completed_withdrawals' => '1',
        'wallet_auto_first_withdrawal_review_hours' => '24',
        'wallet_auto_allowed_providers' => 'monnify,paystack,fincra,korapay,woven,allawee',
        'wallet_auto_require_account_name' => '1',
        'wallet_withdrawal_flat_fee' => '0',
        'wallet_withdrawal_percent_fee' => '0',
        'wallet_deposit_flat_fee' => '0',
        'wallet_deposit_percent_fee' => '0',
        'circuit_breaker_failure_threshold' => '3',
        'circuit_breaker_cooldown_seconds' => '300',
    ];
}

function wallet_withdrawal_policy(PDO $pdo): array
{
    $policy = wallet_withdrawal_policy_defaults();
    foreach ($policy as $key => $default) {
        $policy[$key] = function_exists('admin_setting') ? admin_setting($pdo, $key, $default) : $default;
    }
    $policy['auto_enabled'] = (string) $policy['wallet_auto_withdrawals_enabled'] === '1';
    $policy['preflight_buffer_enabled'] = (string) $policy['wallet_auto_preflight_buffer_enabled'] === '1';
    $policy['preflight_buffer_minutes'] = max(1, min(60, (int) $policy['wallet_auto_preflight_buffer_minutes']));
    $policy['global_daily_cap'] = max(0, (float) $policy['wallet_auto_global_daily_cap']);
    $policy['max_amount'] = max(0, (float) $policy['wallet_auto_max_amount']);
    $policy['daily_user_limit'] = max(0, (float) $policy['wallet_auto_daily_user_limit']);
    $policy['min_completed_withdrawals'] = max(1, (int) $policy['wallet_auto_min_completed_withdrawals']);
    $policy['first_withdrawal_review_hours'] = max(1, min(168, (int) $policy['wallet_auto_first_withdrawal_review_hours']));
    $policy['allowed_providers'] = array_values(array_filter(array_map(static fn(string $provider): string => strtolower(trim($provider)), explode(',', (string) $policy['wallet_auto_allowed_providers']))));
    $policy['require_account_name'] = (string) $policy['wallet_auto_require_account_name'] === '1';
    $policy['withdrawal_flat_fee'] = max(0, (float) $policy['wallet_withdrawal_flat_fee']);
    $policy['withdrawal_percent_fee'] = max(0, (float) $policy['wallet_withdrawal_percent_fee']);
    $policy['deposit_flat_fee'] = max(0, (float) $policy['wallet_deposit_flat_fee']);
    $policy['deposit_percent_fee'] = max(0, (float) $policy['wallet_deposit_percent_fee']);
    $policy['circuit_breaker_failure_threshold'] = max(1, (int) $policy['circuit_breaker_failure_threshold']);
    $policy['circuit_breaker_cooldown_seconds'] = max(10, (int) $policy['circuit_breaker_cooldown_seconds']);
    return $policy;
}

function wallet_save_withdrawal_policy(PDO $pdo, array $data, ?array $adminUser = null): void
{
    $isSuper = false;
    if ($adminUser !== null) {
        $isSuper = !empty($adminUser['is_super_admin']);
    }
    if (!$isSuper && function_exists('super_admin_is_authorized')) {
        $isSuper = super_admin_is_authorized($pdo);
    }
    if (!$isSuper) {
        throw new RuntimeException('Unauthorized: Super Admin privileges are required to modify withdrawal policy.');
    }
    if (!app_table_exists($pdo, 'settings')) {
        return;
    }
    $allowedProviders = array_values(array_intersect(['monnify', 'paystack', 'fincra', 'woven', 'korapay', 'allawee'], array_map('strtolower', (array) ($data['wallet_auto_allowed_providers'] ?? []))));
    $settings = [
        'wallet_auto_withdrawals_enabled' => !empty($data['wallet_auto_withdrawals_enabled']) ? '1' : '0',
        'wallet_auto_preflight_buffer_enabled' => !empty($data['wallet_auto_preflight_buffer_enabled']) ? '1' : '0',
        'wallet_auto_preflight_buffer_minutes' => (string) max(1, min(60, (int) ($data['wallet_auto_preflight_buffer_minutes'] ?? 3))),
        'wallet_auto_global_daily_cap' => (string) max(0, round((float) ($data['wallet_auto_global_daily_cap'] ?? 1000000), 2)),
        'wallet_auto_max_amount' => (string) max(0, round((float) ($data['wallet_auto_max_amount'] ?? 0), 2)),
        'wallet_auto_daily_user_limit' => (string) max(0, round((float) ($data['wallet_auto_daily_user_limit'] ?? 0), 2)),
        'wallet_auto_min_completed_withdrawals' => (string) max(1, (int) ($data['wallet_auto_min_completed_withdrawals'] ?? 1)),
        'wallet_auto_first_withdrawal_review_hours' => (string) max(1, min(168, (int) ($data['wallet_auto_first_withdrawal_review_hours'] ?? 24))),
        'wallet_auto_allowed_providers' => implode(',', $allowedProviders ?: ['monnify', 'paystack', 'fincra', 'korapay', 'woven', 'allawee']),
        'wallet_auto_require_account_name' => !empty($data['wallet_auto_require_account_name']) ? '1' : '0',
        'wallet_withdrawal_flat_fee' => (string) max(0, round((float) ($data['wallet_withdrawal_flat_fee'] ?? 0), 2)),
        'wallet_withdrawal_percent_fee' => (string) max(0, round((float) ($data['wallet_withdrawal_percent_fee'] ?? 0), 2)),
        'wallet_deposit_flat_fee' => (string) max(0, round((float) ($data['wallet_deposit_flat_fee'] ?? 0), 2)),
        'wallet_deposit_percent_fee' => (string) max(0, round((float) ($data['wallet_deposit_percent_fee'] ?? 0), 2)),
        'circuit_breaker_failure_threshold' => (string) max(1, min(20, (int) ($data['circuit_breaker_failure_threshold'] ?? 3))),
        'circuit_breaker_cooldown_seconds' => (string) max(10, min(86400, (int) ($data['circuit_breaker_cooldown_seconds'] ?? 300))),
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
    $completedStmt = $pdo->prepare("SELECT COUNT(*) FROM wallet_withdrawals WHERE user_id = ? AND status IN ('approved', 'paid')");
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
    if (wallet_dual_control_required($pdo, $amount)) {
        $base['review_reason'] = 'Amount is at or above the Super Admin dual-control limit; payout requires Super Admin approval.';
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

    if ($provider !== 'manual' && function_exists('circuit_breaker_is_available')) {
        if (!circuit_breaker_is_available($pdo, $provider)) {
            // Check if any alternative allowed provider is healthy for fallback
            $hasFallback = false;
            foreach ($policy['allowed_providers'] as $alt) {
                if ($alt !== $provider && circuit_breaker_is_available($pdo, $alt)) {
                    $hasFallback = true;
                    break;
                }
            }
            if (!$hasFallback) {
                $base['review_reason'] = "Selected payout provider [{$provider}] and all fallback gateways are in temporary circuit breaker cooldown; payout queued for review.";
                return $base;
            }
        }
    }

    $dailyStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM wallet_withdrawals WHERE user_id = ? AND (status IN ('processing', 'approved', 'paid') OR (status = 'pending' AND automation_eligible = 1)) AND requested_at >= CURDATE()");
    $dailyStmt->execute([$userId]);
    $dailyTotal = (float) $dailyStmt->fetchColumn();
    if ((float) $policy['daily_user_limit'] > 0 && ($dailyTotal + $amount) > (float) $policy['daily_user_limit']) {
        $base['review_reason'] = 'User daily automatic withdrawal limit would be exceeded.';
        return $base;
    }

    $globalDailyStmt = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM wallet_withdrawals WHERE (status IN ('processing', 'approved', 'paid') OR (status = 'pending' AND automation_eligible = 1)) AND requested_at >= CURDATE()");
    $globalDailyTotal = (float) $globalDailyStmt->fetchColumn();
    if ((float) $policy['global_daily_cap'] > 0 && ($globalDailyTotal + $amount) > (float) $policy['global_daily_cap']) {
        $base['review_reason'] = 'Platform global daily withdrawal cap reached; payout requires Super Admin / operator manual review.';
        return $base;
    }

    if (!empty($policy['preflight_buffer_enabled'])) {
        $bufferMins = (int) ($policy['preflight_buffer_minutes'] ?? 3);
        $bufferedUntil = date('Y-m-d H:i:s', time() + ($bufferMins * 60));
        $base['review_required'] = false;
        $base['automation_eligible'] = true;
        $base['preflight_buffered'] = true;
        $base['preflight_buffered_until'] = $bufferedUntil;
        $base['review_reason'] = 'Pre-flight hold mode is active: buffered for ' . $bufferMins . ' minutes before automated dispatch.';
        $base['review_due_at'] = null;
        return $base;
    }

    $base['review_required'] = false;
    $base['automation_eligible'] = true;
    $base['preflight_buffered'] = false;
    $base['preflight_buffered_until'] = null;
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
    if (!in_array($provider, ['manual', 'monnify', 'paystack', 'fincra', 'woven', 'korapay', 'allawee'], true)) {
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
    if (in_array($provider, ['monnify', 'paystack', 'fincra', 'woven', 'korapay', 'allawee'], true) && $bankCode === '') {
        return ['success' => false, 'error' => ucfirst($provider) . ' withdrawal requires a bank code.'];
    }

    $charge = wallet_withdrawal_charge($amount, $pdo);
    $finalAmount = round($amount, 2);
    $totalDebited = round($amount + $charge, 2);

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
        if ($before + 0.01 < $totalDebited) {
            $feeMsg = $charge > 0 ? ' Total required including fee (NGN ' . number_format($charge, 2) . ') is NGN ' . number_format($totalDebited, 2) . '.' : '';
            throw new RuntimeException('Insufficient wallet balance.' . $feeMsg);
        }

        $idempotencyKey = trim((string) ($data['idempotency_key'] ?? ''));
        if ($idempotencyKey !== '') {
            $checkDup = $pdo->prepare("SELECT * FROM wallet_withdrawals WHERE user_id = ? AND reference = ? LIMIT 1");
            $checkDup->execute([$userId, $idempotencyKey]);
            $dup = $checkDup->fetch();
            if ($dup) {
                $pdo->commit();
                return ['success' => true, 'withdrawal_id' => (int) $dup['id'], 'reference' => (string) $dup['reference'], 'withdrawal' => $dup, 'duplicate' => true];
            }
        }

        // Prevent rapid double-clicks within 15 seconds for identical pending withdrawal
        $recentStmt = $pdo->prepare("
            SELECT * FROM wallet_withdrawals
            WHERE user_id = ? AND amount = ? AND account_number = ? AND status = 'pending'
              AND requested_at >= DATE_SUB(NOW(), INTERVAL 15 SECOND)
            ORDER BY id DESC LIMIT 1
        ");
        $recentStmt->execute([$userId, $amount, $accountNumber]);
        $recentDup = $recentStmt->fetch();
        if ($recentDup) {
            $pdo->commit();
            return ['success' => true, 'withdrawal_id' => (int) $recentDup['id'], 'reference' => (string) $recentDup['reference'], 'withdrawal' => $recentDup, 'duplicate' => true];
        }

        $after = $before - $totalDebited;
        $holdAfter = (float) ($wallet['hold_balance'] ?? 0) + $totalDebited;
        $reference = $idempotencyKey !== '' ? $idempotencyKey : wallet_withdrawal_reference($userId);
        $policyDecision = wallet_evaluate_withdrawal_policy($pdo, $userId, $amount, $provider, $accountName);
        $pdo->prepare("UPDATE wallets SET balance = ?, hold_balance = ?, last_activity_at = NOW() WHERE id = ?")
            ->execute([$after, $holdAfter, (int) $wallet['id']]);

        $isBuffered = !empty($policyDecision['preflight_buffered']);
        $bufferedUntil = $policyDecision['preflight_buffered_until'] ?? null;

        $pdo->prepare("
            INSERT INTO wallet_withdrawals
                (wallet_id, user_id, reference, amount, charge, final_amount, total_debited, provider, bank_code, bank_name, account_number, account_name, note, review_required, review_reason, review_due_at, automation_eligible, automation_rule_snapshot, preflight_buffered, preflight_buffered_until)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            (int) $wallet['id'],
            $userId,
            $reference,
            $amount,
            $charge,
            $finalAmount,
            $totalDebited,
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
            $isBuffered ? 1 : 0,
            $bufferedUntil,
        ]);
        $withdrawalId = (int) $pdo->lastInsertId();
        $pdo->prepare("
            INSERT INTO wallet_transactions
                (wallet_id, user_id, amount, type, direction, description, reference, provider, provider_reference, provider_payload, status, balance_before, balance_after)
            VALUES (?, ?, ?, 'withdrawal', 'outflow', ?, ?, ?, ?, ?, 'pending', ?, ?)
        ")->execute([
            (int) $wallet['id'],
            $userId,
            $totalDebited,
            'Withdrawal request to ' . ($bankName ?: 'bank account') . ($charge > 0 ? ' (fee: NGN ' . number_format($charge, 2) . ')' : ''),
            $reference,
            $provider,
            $reference,
            json_encode(['payout_amount' => $finalAmount, 'charge' => $charge, 'final_amount' => $finalAmount, 'total_debited' => $totalDebited, 'account_number' => $accountNumber, 'account_name' => $accountName, 'bank_code' => $bankCode, 'bank_name' => $bankName], JSON_UNESCAPED_SLASHES),
            $before,
            $after,
        ]);
        $pdo->commit();
        $result = [
            'success' => true,
            'withdrawal_id' => $withdrawalId,
            'reference' => $reference,
            'amount' => $amount,
            'final_amount' => $finalAmount,
            'charge' => $charge,
            'total_debited' => $totalDebited,
            'review_required' => !empty($policyDecision['review_required']),
            'review_reason' => (string) ($policyDecision['review_reason'] ?? ''),
            'review_due_at' => $policyDecision['review_due_at'] ?? null,
            'automation_eligible' => !empty($policyDecision['automation_eligible']),
            'preflight_buffered' => $isBuffered,
            'preflight_buffered_until' => $bufferedUntil,
        ];
        if (!empty($policyDecision['automation_eligible']) && $withdrawalId > 0) {
            if ($isBuffered) {
                $result['auto_processed'] = false;
                $result['auto_status'] = 'buffered_preflight';
            } else {
                $auto = wallet_admin_process_withdrawal($pdo, $withdrawalId, 0, 'approve', 'Automated approval by withdrawal threshold policy.');
                $result['auto_processed'] = (bool) ($auto['success'] ?? false);
                $result['auto_status'] = (string) ($auto['status'] ?? ($auto['error'] ?? 'pending'));
                if (!empty($auto['success'])) {
                    $pdo->prepare("UPDATE wallet_withdrawals SET auto_processed_at = NOW() WHERE id = ?")->execute([$withdrawalId]);
                }
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
    $recipientData = $recipient['data'] ?? [];
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
    $body = $transfer['data'] ?? [];
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
    require_once __DIR__ . '/gateway-manager.php';
    return gateway_list_banks($provider);
}

function wallet_resolve_payout_account(string $provider, string $accountNumber, string $bankCode): array
{
    require_once __DIR__ . '/gateway-manager.php';
    return gateway_resolve_bank_account($provider, $accountNumber, $bankCode);
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
        if (!in_array((string) $withdrawal['status'], ['pending', 'failed'], true)) {
            // 'failed' is retryable: nothing left the account, so an operator may resend.
            throw new RuntimeException('Only pending or failed withdrawals can be processed.');
        }
        if ((int) ($withdrawal['funds_released'] ?? 0) === 1) {
            // The held money has already gone back to the user, so paying it out now
            // would move funds twice.
            throw new RuntimeException('The held funds for this withdrawal were already returned to the wallet, so it cannot be paid out.');
        }
        $walletStmt = $pdo->prepare("SELECT * FROM wallets WHERE id = ? FOR UPDATE");
        $walletStmt->execute([(int) $withdrawal['wallet_id']]);
        $wallet = $walletStmt->fetch();
        if (!$wallet) {
            throw new RuntimeException('Linked wallet was not found.');
        }

        if ($decision === 'reject') {
            $amountHeld = !empty($withdrawal['total_debited']) && (float) $withdrawal['total_debited'] > 0
                ? (float) $withdrawal['total_debited']
                : (float) $withdrawal['amount'];
            $balance = (float) $wallet['balance'] + $amountHeld;
            $hold = max(0, (float) $wallet['hold_balance'] - $amountHeld);
            $pdo->prepare("UPDATE wallets SET balance = ?, hold_balance = ?, last_activity_at = NOW() WHERE id = ?")
                ->execute([$balance, $hold, (int) $wallet['id']]);
            $pdo->prepare("UPDATE wallet_withdrawals SET status = 'rejected', payout_status = 'rejected', admin_note = ?, reviewed_by = ?, reviewed_at = NOW(), updated_at = NOW() WHERE id = ?")
                ->execute([$adminNote, $adminId, $withdrawalId]);
            $pdo->prepare("UPDATE wallet_transactions SET status = 'rejected', provider_payload = ? WHERE reference = ?")
                ->execute([json_encode(['admin_note' => $adminNote, 'decision' => 'rejected'], JSON_UNESCAPED_SLASHES), (string) $withdrawal['reference']]);
            $pdo->commit();
            if (function_exists('admin_audit')) {
                admin_audit($pdo, 'withdrawal_rejected', 'Admin #' . $adminId . ' rejected withdrawal #' . $withdrawalId . ' (NGN ' . number_format((float) $withdrawal['amount'], 2) . ').');
            }
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
    require_once __DIR__ . '/gateway-manager.php';
    $payout = gateway_initiate_withdrawal($withdrawal);
    if ($adminNote !== '') {
        if (!isset($payout['payload']) || !is_array($payout['payload'])) {
            $payout['payload'] = [];
        }
        $payout['payload']['admin_note'] = $adminNote;
    }

    $effectiveProvider = strtolower((string) ($payout['provider'] ?? $provider));
    return wallet_finalize_withdrawal_payout($pdo, $withdrawalId, $payout, $effectiveProvider, $adminId);
}

/**
 * Finalize an approved withdrawal once the provider accepted the payout.
 *
 * Runs in its own transaction with row locks, and re-checks that the
 * withdrawal is still in a payable state, so a payout can never be
 * finalized twice or after the row moved on. Both the interactive
 * approval flow and the reconciliation cron go through here.
 *
 * @return array{success: bool, status?: string, provider?: string, payout_status?: string, error?: string}
 */
function wallet_finalize_withdrawal_payout(PDO $pdo, int $withdrawalId, array $payout, string $provider, int $adminId = 0): array
{
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT * FROM wallet_withdrawals WHERE id = ? FOR UPDATE");
        $stmt->execute([$withdrawalId]);
        $current = $stmt->fetch();
        if (!$current) {
            throw new RuntimeException('Withdrawal state changed before payout update.');
        }

        $actualProvider = strtolower((string) ($payout['provider'] ?? $current['provider'] ?? $provider));

        if ((string) ($current['status'] ?? '') === 'paid') {
            $pdo->commit();
            return [
                'success' => true,
                'status' => 'paid',
                'provider' => $actualProvider,
                'payout_status' => (string) ($current['payout_status'] ?? 'confirmed'),
                'duplicate' => true,
            ];
        }

        $isFailedPayout = empty($payout['success'])
            || !empty($payout['failed'])
            || in_array(strtolower((string) ($payout['payout_status'] ?? '')), ['failed', 'reversed', 'rejected'], true);

        if ($isFailedPayout) {
            $pdo->commit();
            $reason = (string) ($payout['error'] ?? $payout['reason'] ?? 'Payout failed at provider');
            if ($actualProvider !== 'manual' && empty($payout['circuit_open']) && function_exists('circuit_breaker_record_failure')) {
                circuit_breaker_record_failure($pdo, $actualProvider, $reason);
            }
            $release = wallet_release_withdrawal_funds($pdo, $withdrawalId, 'failed', $reason, $adminId);
            return [
                'success' => !empty($release['success']),
                'status' => 'failed',
                'provider' => $actualProvider,
                'payout_status' => 'failed',
                'released' => !empty($release['released']),
                'error' => $reason,
            ];
        }

        if ($actualProvider !== 'manual' && function_exists('circuit_breaker_record_success')) {
            circuit_breaker_record_success($pdo, $actualProvider);
        }

        if (!in_array((string) $current['status'], ['processing', 'pending', 'approved'], true)) {
            throw new RuntimeException('Withdrawal is no longer in a payable state.');
        }
        $walletStmt = $pdo->prepare("SELECT * FROM wallets WHERE id = ? FOR UPDATE");
        $walletStmt->execute([(int) $current['wallet_id']]);
        $wallet = $walletStmt->fetch();
        if (!$wallet) {
            throw new RuntimeException('Linked wallet was not found.');
        }

        $amountHeld = !empty($current['total_debited']) && (float) $current['total_debited'] > 0
            ? (float) $current['total_debited']
            : (float) $current['amount'];
        $hold = max(0, (float) $wallet['hold_balance'] - $amountHeld);
        $pdo->prepare("UPDATE wallets SET hold_balance = ?, last_activity_at = NOW() WHERE id = ?")
            ->execute([$hold, (int) $wallet['id']]);
        $pdo->prepare("
            UPDATE wallet_withdrawals
            SET provider = ?, status = 'paid', funds_released = 1, payout_status = ?, payout_reference = ?, payout_transfer_code = ?,
                provider_payload = ?, completed_at = NOW(), updated_at = NOW()
            WHERE id = ?
        ")->execute([
            $actualProvider,
            (string) ($payout['payout_status'] ?? 'initiated'),
            (string) ($payout['payout_reference'] ?: $current['reference']),
            (string) ($payout['transfer_code'] ?: $current['payout_transfer_code']),
            json_encode($payout['payload'] ?? $payout, JSON_UNESCAPED_SLASHES),
            $withdrawalId,
        ]);
        $pdo->prepare("
            UPDATE wallet_transactions
            SET provider = ?, status = 'completed', provider_reference = ?, provider_payload = ?, completed_at = NOW()
            WHERE reference = ?
        ")->execute([
            $actualProvider,
            (string) ($payout['payout_reference'] ?: $current['reference']),
            json_encode($payout['payload'] ?? $payout, JSON_UNESCAPED_SLASHES),
            (string) $current['reference'],
        ]);
        $pdo->commit();
        if (function_exists('admin_audit')) {
            $actor = $adminId > 0 ? 'Admin #' . $adminId : 'System reconciliation';
            admin_audit($pdo, 'withdrawal_approved', $actor . ' approved withdrawal #' . $withdrawalId . ' (NGN ' . number_format((float) $current['amount'], 2) . ') via ' . $actualProvider . '.');
        }
        return ['success' => true, 'status' => 'paid', 'provider' => $actualProvider, 'payout_status' => (string) ($payout['payout_status'] ?? 'initiated')];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Ask the payout provider what actually happened to a withdrawal.
 *
 * Used by the reconciliation cron to resolve withdrawals that are stuck
 * in 'processing' (or pending with a provider transfer code) after a
 * crash between the provider call and the database commit.
 *
 * @return array{state: string, payload?: array, error?: string} state: success|failed|pending|unknown
 */
function wallet_query_payout_status(array $withdrawal): array
{
    require_once __DIR__ . '/gateway-manager.php';
    return gateway_query_payout_status($withdrawal);
}

/**
 * Return a withdrawal's held amount to the available balance.
 *
 * A withdrawal request moves money out of `balance` into `hold_balance`. When a payout
 * never happens, that money used to sit in hold with nothing the user could do about it.
 * This is the single place that undoes the hold, and it runs ONLY when an explicit
 * action asks for it — a failed payout never calls this on its own, because whether the
 * transfer reached the bank is not always known.
 *
 * Guarded by `funds_released`, so calling it twice cannot pay twice.
 *
 * @param string $status terminal status to record: failed | cancelled
 * @return array{success: bool, released: bool, amount: float, error?: string}
 */
function wallet_release_withdrawal_funds(PDO $pdo, int $withdrawalId, string $status, string $reason = '', int $actorId = 0): array
{
    $status = in_array($status, ['failed', 'cancelled'], true) ? $status : 'cancelled';
    $ownsTransaction = !$pdo->inTransaction();

    try {
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }

        $stmt = $pdo->prepare('SELECT * FROM wallet_withdrawals WHERE id = ? FOR UPDATE');
        $stmt->execute([$withdrawalId]);
        $withdrawal = $stmt->fetch();
        if (!$withdrawal) {
            if ($ownsTransaction) {
                $pdo->rollBack();
            }
            return ['success' => false, 'released' => false, 'amount' => 0.0, 'error' => 'Withdrawal not found.'];
        }

        if ((int) ($withdrawal['funds_released'] ?? 0) === 1) {
            if ($ownsTransaction) {
                $pdo->rollBack();
            }
            return ['success' => true, 'released' => false, 'amount' => 0.0, 'duplicate' => true];
        }

        // A payout that actually completed must never be reversed here.
        if (in_array((string) $withdrawal['status'], ['approved', 'paid'], true)) {
            if ($ownsTransaction) {
                $pdo->rollBack();
            }
            return ['success' => false, 'released' => false, 'amount' => 0.0, 'error' => 'This withdrawal was already paid out.'];
        }

        $walletStmt = $pdo->prepare('SELECT * FROM wallets WHERE id = ? FOR UPDATE');
        $walletStmt->execute([(int) $withdrawal['wallet_id']]);
        $wallet = $walletStmt->fetch();
        if (!$wallet) {
            if ($ownsTransaction) {
                $pdo->rollBack();
            }
            return ['success' => false, 'released' => false, 'amount' => 0.0, 'error' => 'Linked wallet not found.'];
        }

        $amountHeld = !empty($withdrawal['total_debited']) && (float) $withdrawal['total_debited'] > 0
            ? (float) $withdrawal['total_debited']
            : (float) $withdrawal['amount'];
        $newBalance = (float) $wallet['balance'] + $amountHeld;
        $newHold = max(0, (float) ($wallet['hold_balance'] ?? 0) - $amountHeld);

        $pdo->prepare('UPDATE wallets SET balance = ?, hold_balance = ?, last_activity_at = NOW() WHERE id = ?')
            ->execute([$newBalance, $newHold, (int) $wallet['id']]);

        $note = trim($reason) !== '' ? trim($reason) : 'Withdrawal ' . $status . '; funds returned to your balance.';
        $pdo->prepare("UPDATE wallet_withdrawals
            SET status = ?, funds_released = 1, failure_reason = ?, admin_note = ?, updated_at = NOW()
            WHERE id = ?")
            ->execute([$status, $note, $note, $withdrawalId]);

        // wallet_transactions.description is a legacy latin1 column, so it is not
        // appended to: mixing it with a utf8mb4 binding threw
        // "Illegal mix of collations ... for operation 'concat'".
        $pdo->prepare("UPDATE wallet_transactions SET status = ?, completed_at = NOW() WHERE reference = ?")
            ->execute([$status, (string) $withdrawal['reference']]);

        if ($ownsTransaction) {
            $pdo->commit();
        }
        if (function_exists('admin_audit')) {
            admin_audit($pdo, 'withdrawal_funds_released', 'Returned NGN ' . number_format($amountHeld, 2) . ' to wallet for withdrawal #' . $withdrawalId . ' (' . $status . ').');
        }

        return ['success' => true, 'released' => true, 'amount' => $amountHeld, 'balance' => $newBalance, 'status' => $status];
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('wallet_release_withdrawal_funds failed: ' . $e->getMessage());

        return ['success' => false, 'released' => false, 'amount' => 0.0, 'error' => 'Unable to return the funds right now.'];
    }
}

/**
 * Let a user withdraw their own stuck request and get the money back.
 *
 * Only ever called from an explicit button. Allowed while nothing has been sent, or
 * after a payout failed. A withdrawal whose transfer state is unknown stays with an
 * operator, because the money may already be with the bank.
 *
 * @return array{success: bool, error?: string, amount?: float, status?: string}
 */
function wallet_cancel_withdrawal(PDO $pdo, int $withdrawalId, int $userId, string $reason = ''): array
{
    wallet_ensure_schema($pdo);
    wallet_withdrawals_ensure_schema($pdo);

    $stmt = $pdo->prepare('SELECT * FROM wallet_withdrawals WHERE id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$withdrawalId, $userId]);
    $withdrawal = $stmt->fetch();
    if (!$withdrawal) {
        return ['success' => false, 'error' => 'Withdrawal request not found.'];
    }
    if ((int) ($withdrawal['funds_released'] ?? 0) === 1) {
        return ['success' => true, 'amount' => (float) $withdrawal['amount'], 'status' => (string) $withdrawal['status'], 'duplicate' => true];
    }
    if (!in_array((string) $withdrawal['status'], ['pending', 'failed'], true)) {
        return ['success' => false, 'error' => 'This withdrawal cannot be cancelled now — it has already been sent.'];
    }
    if ((string) ($withdrawal['payout_status'] ?? '') === 'unconfirmed') {
        return ['success' => false, 'error' => 'We are still confirming this transfer with the bank. Please contact support so we can resolve it.'];
    }

    $note = trim($reason) !== '' ? trim($reason) : 'Cancelled by the account holder; funds returned to the wallet.';

    return wallet_release_withdrawal_funds($pdo, $withdrawalId, 'cancelled', $note, $userId);
}
