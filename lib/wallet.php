<?php

declare(strict_types=1);

require_once __DIR__ . '/monnify.php';
require_once __DIR__ . '/wallet-withdrawals.php';

function wallet_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

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
    try {
        $pdo->exec("ALTER TABLE wallets ADD UNIQUE KEY uniq_wallets_user_id (user_id)");
    } catch (Throwable $e) {
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
    try {
        $pdo->exec("ALTER TABLE `wallet_transactions` MODIFY COLUMN `status` VARCHAR(40) NOT NULL DEFAULT 'pending'");
    } catch (Throwable $e) {
        error_log('wallet_transactions status migration failed: ' . $e->getMessage());
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

function wallet_get_or_create(PDO $pdo, int $userId): array
{
    if (!$pdo->inTransaction()) {
        wallet_ensure_schema($pdo);
    }
    if ($userId <= 0) {
        return [];
    }
    $stmt = $pdo->prepare("SELECT * FROM wallets WHERE user_id = ? ORDER BY id ASC LIMIT 1");
    $stmt->execute([$userId]);
    $existing = $stmt->fetch();
    if ($existing) {
        return $existing;
    }
    $pdo->prepare("INSERT INTO wallets (user_id) VALUES (?) ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)")->execute([$userId]);
    $stmt = $pdo->prepare("SELECT * FROM wallets WHERE user_id = ? ORDER BY id ASC LIMIT 1");
    $stmt->execute([$userId]);
    return $stmt->fetch() ?: [];
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
    if ($userId <= 0) {
        return ['success' => false, 'error' => 'Select a valid wallet owner.'];
    }
    if (!is_finite($amount) || $amount <= 0 || $amount > 1000000000) {
        return ['success' => false, 'error' => 'Enter a valid amount between NGN 0.01 and NGN 1,000,000,000.'];
    }
    $stmt = $pdo->prepare('SELECT id, name, email FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        return ['success' => false, 'error' => 'Wallet owner was not found.'];
    }

    $reference = 'ADMIN-CREDIT-' . date('YmdHis') . '-' . $userId . '-' . strtoupper(bin2hex(random_bytes(3)));
    $description = 'Admin wallet credit' . ($note !== '' ? ': ' . mb_substr($note, 0, 300) : '');
    try {
        $result = wallet_credit_once($pdo, $userId, round($amount, 2), $reference, $description, 'admin_manual', null, [
            'admin_id' => $adminId,
            'note' => mb_substr($note, 0, 500),
            'credited_at' => date(DATE_ATOM),
        ]);
        return $result + ['reference' => $reference, 'user' => $user];
    } catch (Throwable $e) {
        error_log('Admin wallet credit failed: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Wallet credit could not be completed safely.'];
    }
}

