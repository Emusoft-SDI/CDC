<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/admin-layout.php';
require_once __DIR__ . '/../../lib/monnify.php';
require_once __DIR__ . '/../../lib/academy.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$pdo = db();
admin_ensure_schema($pdo);
wallet_ensure_schema($pdo);
academy_ensure_schema($pdo);
admin_require($pdo);

$walletAdmin = current_user($pdo) ?: [];
$walletScriptDir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/admin/wallet.php')));
$walletAdminBase = basename($walletScriptDir) === 'acad' ? dirname($walletScriptDir) : $walletScriptDir;
$walletAdminBase = rtrim($walletAdminBase, '/') ?: '/admin';
$walletPublicBase = preg_replace('#/admin$#', '', $walletAdminBase) ?: '';

$pdo->exec("
    CREATE TABLE IF NOT EXISTS wallet_admin_bank_accounts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        bank_name VARCHAR(180) NOT NULL,
        account_type VARCHAR(60) NOT NULL DEFAULT 'primary',
        account_name VARCHAR(180) NOT NULL,
        account_number VARCHAR(80) NOT NULL,
        bvn_reference VARCHAR(120) NULL,
        status VARCHAR(40) NOT NULL DEFAULT 'active',
        created_by INT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_wallet_bank_status (status, account_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
app_ensure_primary_auto_increment($pdo, 'wallet_admin_bank_accounts');

$pdo->exec("
    CREATE TABLE IF NOT EXISTS wallet_reconciliation_runs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        run_ref VARCHAR(80) NOT NULL UNIQUE,
        scope VARCHAR(80) NOT NULL DEFAULT 'all',
        status VARCHAR(40) NOT NULL DEFAULT 'completed',
        matched_count INT NOT NULL DEFAULT 0,
        exception_count INT NOT NULL DEFAULT 0,
        notes TEXT NULL,
        created_by INT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_wallet_recon_status (status, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
app_ensure_primary_auto_increment($pdo, 'wallet_reconciliation_runs');

app_add_column_if_missing($pdo, 'wallets', 'status', "VARCHAR(40) NOT NULL DEFAULT 'active'");
app_add_column_if_missing($pdo, 'wallets', 'hold_balance', "DECIMAL(12,2) NOT NULL DEFAULT 0");
app_add_column_if_missing($pdo, 'wallets', 'wallet_type', "VARCHAR(60) NULL");
app_add_column_if_missing($pdo, 'wallets', 'last_activity_at', "DATETIME NULL");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS wallet_payment_gateways (
        id INT AUTO_INCREMENT PRIMARY KEY,
        provider VARCHAR(80) NOT NULL,
        label VARCHAR(140) NOT NULL,
        methods VARCHAR(220) NULL,
        fee_percent DECIMAL(8,3) NOT NULL DEFAULT 0,
        min_fee DECIMAL(12,2) NOT NULL DEFAULT 0,
        max_fee DECIMAL(12,2) NOT NULL DEFAULT 0,
        status VARCHAR(40) NOT NULL DEFAULT 'inactive',
        mode VARCHAR(40) NOT NULL DEFAULT 'live',
        public_key VARCHAR(255) NULL,
        secret_hint VARCHAR(120) NULL,
        created_by INT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_wallet_gateway_provider (provider),
        INDEX idx_wallet_gateway_status (status, mode)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
app_ensure_primary_auto_increment($pdo, 'wallet_payment_gateways');

$pdo->exec("
    CREATE TABLE IF NOT EXISTS wallet_fee_rules (
        id INT AUTO_INCREMENT PRIMARY KEY,
        rule_key VARCHAR(80) NOT NULL UNIQUE,
        label VARCHAR(160) NOT NULL,
        percent_rate DECIMAL(8,3) NOT NULL DEFAULT 0,
        flat_fee DECIMAL(12,2) NOT NULL DEFAULT 0,
        min_fee DECIMAL(12,2) NOT NULL DEFAULT 0,
        max_fee DECIMAL(12,2) NOT NULL DEFAULT 0,
        applies_to VARCHAR(80) NOT NULL DEFAULT 'all',
        status VARCHAR(40) NOT NULL DEFAULT 'active',
        updated_by INT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
app_ensure_primary_auto_increment($pdo, 'wallet_fee_rules');

$pdo->exec("
    CREATE TABLE IF NOT EXISTS wallet_tax_compliance_documents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(180) NOT NULL,
        category VARCHAR(80) NOT NULL DEFAULT 'tax',
        reference VARCHAR(120) NULL,
        amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        due_date DATE NULL,
        status VARCHAR(40) NOT NULL DEFAULT 'pending',
        notes TEXT NULL,
        created_by INT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_wallet_tax_status (status, due_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
app_ensure_primary_auto_increment($pdo, 'wallet_tax_compliance_documents');

$pdo->exec("
    CREATE TABLE IF NOT EXISTS wallet_admin_audit_events (
        id INT AUTO_INCREMENT PRIMARY KEY,
        actor_id INT NULL,
        action VARCHAR(100) NOT NULL,
        resource_type VARCHAR(80) NOT NULL DEFAULT 'wallet',
        resource_ref VARCHAR(160) NULL,
        details TEXT NULL,
        ip_address VARCHAR(80) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_wallet_audit_action (action, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
app_ensure_primary_auto_increment($pdo, 'wallet_admin_audit_events');

if ((int) ($pdo->query('SELECT COUNT(*) FROM wallet_payment_gateways')->fetchColumn() ?: 0) === 0) {
    $seedGateways = [
        ['monnify', 'Monnify', 'Cards, Bank Transfer, USSD, Reserved Accounts', 0.5, 50, 2000, 'active', 'live'],
        ['paystack', 'Paystack', 'Cards, Bank, Mobile Money', 1.5, 100, 2500, 'inactive', 'test'],
        ['flutterwave', 'Flutterwave', 'Cards, Transfer, USSD', 1.4, 100, 2200, 'inactive', 'test'],
    ];
    $stmt = $pdo->prepare("INSERT INTO wallet_payment_gateways (provider, label, methods, fee_percent, min_fee, max_fee, status, mode, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($seedGateways as $gateway) {
        $stmt->execute([...$gateway, (int) ($walletAdmin['id'] ?? 0)]);
    }
}

if ((int) ($pdo->query('SELECT COUNT(*) FROM wallet_fee_rules')->fetchColumn() ?: 0) === 0) {
    $seedFees = [
        ['gateway_monnify', 'Monnify Gateway Fee', 0.5, 0, 50, 2000, 'funding'],
        ['card_payment', 'Card Payment Fee', 1.5, 0, 100, 2500, 'payment'],
        ['seller_payout', 'Seller Payout Charge', 0.0, 100, 0, 100, 'payout'],
        ['academy_refund', 'Academy Refund Charge', 0.0, 0, 0, 0, 'refund'],
    ];
    $stmt = $pdo->prepare("INSERT INTO wallet_fee_rules (rule_key, label, percent_rate, flat_fee, min_fee, max_fee, applies_to) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($seedFees as $fee) {
        $stmt->execute($fee);
    }
}

function wx_e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function wx_scalar(PDO $pdo, string $sql, array $params = []): float
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (float) ($stmt->fetchColumn() ?: 0);
    } catch (Throwable $e) {
        error_log('Wallet design scalar failed: ' . $e->getMessage());
        return 0.0;
    }
}

function wx_rows(PDO $pdo, string $sql, array $params = []): array
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('Wallet design rows failed: ' . $e->getMessage());
        return [];
    }
}

function wx_post(string $key, string $default = ''): string
{
    return trim((string) ($_POST[$key] ?? $default));
}

function wx_ref(string $prefix): string
{
    return $prefix . '-' . date('ymd-His') . '-' . strtoupper(bin2hex(random_bytes(2)));
}

function wx_redirect(string $page, string $message = '', string $error = ''): void
{
    $query = ['page' => $page];
    if ($message !== '') {
        $query['message'] = $message;
    }
    if ($error !== '') {
        $query['error'] = $error;
    }
    $scriptDir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/admin/wallet.php')));
    if (basename($scriptDir) === 'acad') {
        $scriptDir = dirname($scriptDir);
    }
    header('Location: ' . rtrim($scriptDir, '/') . '/wallet.php?' . http_build_query($query));
    exit;
}

function wx_wallet_url(array $params = []): string
{
    $query = array_filter($params, static fn($value): bool => $value !== '' && $value !== null);
    return 'wallet.php' . ($query ? '?' . http_build_query($query) : '');
}

function wx_csv(string $filename, array $headers, array $rows): void
{
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'wb');
    fputcsv($out, $headers);
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

function wx_wallet_for_user(PDO $pdo, int $userId): array
{
    return wallet_get_or_create($pdo, $userId);
}

function wx_audit(PDO $pdo, int $actorId, string $action, string $resourceType, string $resourceRef = '', string $details = ''): void
{
    try {
        $pdo->prepare("
            INSERT INTO wallet_admin_audit_events (actor_id, action, resource_type, resource_ref, details, ip_address)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([$actorId ?: null, $action, $resourceType, $resourceRef, $details, (string) ($_SERVER['REMOTE_ADDR'] ?? 'local')]);
    } catch (Throwable $e) {
        error_log('Wallet audit failed: ' . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = wx_post('action');
    try {
        if ($action === 'fund_wallet') {
            $userId = (int) wx_post('user_id', (string) ($walletAdmin['id'] ?? 0));
            $amount = max(0.0, (float) wx_post('amount'));
            if ($userId <= 0 || $amount <= 0) {
                throw new RuntimeException('Select a user and enter a valid amount.');
            }
            $wallet = wx_wallet_for_user($pdo, $userId);
            $before = (float) ($wallet['balance'] ?? 0);
            $after = $before + $amount;
            $reference = wx_post('reference', wx_ref('WALLET-FUND'));
            $pdo->prepare('UPDATE wallets SET balance = balance + ? WHERE id = ?')->execute([$amount, (int) $wallet['id']]);
            $pdo->prepare("
                INSERT INTO wallet_transactions
                    (wallet_id, user_id, amount, type, direction, description, reference, provider, status, balance_before, balance_after, completed_at)
                VALUES (?, ?, ?, 'credit', 'inflow', ?, ?, 'admin', 'completed', ?, ?, NOW())
            ")->execute([(int) $wallet['id'], $userId, $amount, wx_post('notes', 'Admin wallet funding'), $reference, $before, $after]);
            wx_audit($pdo, (int) ($walletAdmin['id'] ?? 0), 'fund_wallet', 'wallet', (string) $wallet['id'], 'Credited ' . number_format($amount, 2) . ' to user #' . $userId);
            wx_redirect('transactions', 'Wallet funded successfully.');
        }

        if ($action === 'create_user_wallet') {
            $userId = (int) wx_post('user_id');
            $initial = max(0.0, (float) wx_post('initial_balance'));
            if ($userId <= 0) {
                throw new RuntimeException('Select a user.');
            }
            $wallet = wx_wallet_for_user($pdo, $userId);
            if ($initial > 0) {
                $before = (float) ($wallet['balance'] ?? 0);
                $after = $before + $initial;
                $pdo->prepare('UPDATE wallets SET balance = balance + ? WHERE id = ?')->execute([$initial, (int) $wallet['id']]);
                $pdo->prepare("
                    INSERT INTO wallet_transactions
                        (wallet_id, user_id, amount, type, direction, description, reference, provider, status, balance_before, balance_after, completed_at)
                    VALUES (?, ?, ?, 'credit', 'inflow', 'Initial wallet balance', ?, 'admin', 'completed', ?, ?, NOW())
                ")->execute([(int) $wallet['id'], $userId, $initial, wx_ref('WALLET-INIT'), $before, $after]);
            }
            wx_audit($pdo, (int) ($walletAdmin['id'] ?? 0), 'create_user_wallet', 'wallet', (string) $wallet['id'], 'Ensured wallet for user #' . $userId);
            wx_redirect('user-wallets', 'User wallet is ready.');
        }

        if ($action === 'process_refund') {
            $userId = (int) wx_post('user_id');
            $amount = max(0.0, (float) wx_post('amount'));
            if ($userId <= 0 || $amount <= 0) {
                throw new RuntimeException('Select a requester and refund amount.');
            }
            $wallet = wx_wallet_for_user($pdo, $userId);
            $before = (float) ($wallet['balance'] ?? 0);
            $after = $before + $amount;
            $pdo->prepare('UPDATE wallets SET balance = balance + ? WHERE id = ?')->execute([$amount, (int) $wallet['id']]);
            $pdo->prepare("
                INSERT INTO wallet_transactions
                    (wallet_id, user_id, amount, type, direction, description, reference, provider, status, balance_before, balance_after, completed_at)
                VALUES (?, ?, ?, 'refund', 'inflow', ?, ?, 'admin', 'completed', ?, ?, NOW())
            ")->execute([(int) $wallet['id'], $userId, $amount, wx_post('reason', 'Refund processed by admin'), wx_ref('REFUND'), $before, $after]);
            $refundId = (int) wx_post('refund_id');
            if ($refundId > 0) {
                $pdo->prepare("UPDATE academy_refund_requests SET status = 'approved', admin_notes = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?")
                    ->execute([wx_post('notes'), (int) ($walletAdmin['id'] ?? 0), $refundId]);
            }
            wx_audit($pdo, (int) ($walletAdmin['id'] ?? 0), 'process_refund', 'wallet', (string) $wallet['id'], 'Refunded ' . number_format($amount, 2) . ' to user #' . $userId);
            wx_redirect('refunds', 'Refund processed.');
        }

        if ($action === 'review_refund') {
            $refundId = (int) wx_post('refund_id');
            $status = wx_post('status', 'under_review');
            if (!in_array($status, ['pending', 'under_review', 'approved', 'rejected'], true)) {
                throw new RuntimeException('Invalid refund status.');
            }
            $pdo->prepare('UPDATE academy_refund_requests SET status = ?, admin_notes = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?')
                ->execute([$status, wx_post('notes'), (int) ($walletAdmin['id'] ?? 0), $refundId]);
            wx_audit($pdo, (int) ($walletAdmin['id'] ?? 0), 'review_refund', 'refund', (string) $refundId, 'Refund marked ' . $status);
            wx_redirect('refunds', 'Refund status updated.');
        }

        if ($action === 'settle_order') {
            $orderId = (int) wx_post('order_id');
            $pdo->prepare("UPDATE marketplace_orders SET settled_at = NOW(), status = IF(status = '', 'settled', status) WHERE id = ?")->execute([$orderId]);
            wx_audit($pdo, (int) ($walletAdmin['id'] ?? 0), 'settle_order', 'marketplace_order', (string) $orderId, 'Order marked settled');
            wx_redirect('marketplace-payouts', 'Marketplace order marked as settled.');
        }

        if ($action === 'settle_all_due') {
            $updated = $pdo->exec("UPDATE marketplace_orders SET settled_at = NOW() WHERE payment_status IN ('paid','successful') AND settled_at IS NULL");
            wx_audit($pdo, (int) ($walletAdmin['id'] ?? 0), 'settle_all_due', 'marketplace_order', 'bulk', number_format((int) $updated) . ' orders settled');
            wx_redirect('marketplace-payouts', number_format((int) $updated) . ' marketplace settlement(s) marked settled.');
        }

        if ($action === 'add_bank_account') {
            $pdo->prepare("
                INSERT INTO wallet_admin_bank_accounts (bank_name, account_type, account_name, account_number, bvn_reference, created_by)
                VALUES (?, ?, ?, ?, ?, ?)
            ")->execute([wx_post('bank_name'), wx_post('account_type', 'primary'), wx_post('account_name'), wx_post('account_number'), wx_post('bvn_reference'), (int) ($walletAdmin['id'] ?? 0)]);
            wx_audit($pdo, (int) ($walletAdmin['id'] ?? 0), 'add_bank_account', 'bank_account', wx_post('account_number'), wx_post('bank_name'));
            wx_redirect('bank-accounts', 'Bank account added.');
        }

        if ($action === 'update_bank_account_status') {
            $bankId = (int) wx_post('bank_id');
            $status = wx_post('status', 'active');
            if (!in_array($status, ['active', 'inactive', 'suspended'], true)) {
                throw new RuntimeException('Invalid bank account status.');
            }
            $pdo->prepare('UPDATE wallet_admin_bank_accounts SET status = ? WHERE id = ?')->execute([$status, $bankId]);
            wx_audit($pdo, (int) ($walletAdmin['id'] ?? 0), 'update_bank_account_status', 'bank_account', (string) $bankId, 'Bank account marked ' . $status);
            wx_redirect('bank-accounts', 'Bank account status updated.');
        }

        if ($action === 'reconcile_payments') {
            $matched = (int) wx_scalar($pdo, "SELECT COUNT(*) FROM wallet_transactions WHERE status IN ('completed','successful','success','paid')");
            $exceptions = (int) wx_scalar($pdo, "SELECT COUNT(*) FROM wallet_transactions WHERE status IN ('failed','rejected','pending')");
            $pdo->prepare("
                INSERT INTO wallet_reconciliation_runs (run_ref, scope, status, matched_count, exception_count, notes, created_by)
                VALUES (?, ?, 'completed', ?, ?, ?, ?)
            ")->execute([wx_ref('RECON'), wx_post('scope', 'all'), $matched, $exceptions, wx_post('notes'), (int) ($walletAdmin['id'] ?? 0)]);
            wx_audit($pdo, (int) ($walletAdmin['id'] ?? 0), 'reconcile_payments', 'reconciliation', wx_post('scope', 'all'), 'Matched ' . $matched . ', exceptions ' . $exceptions);
            wx_redirect('reconciliation', 'Reconciliation completed.');
        }

        if ($action === 'update_wallet_status') {
            $walletId = (int) wx_post('wallet_id');
            $status = wx_post('status', 'active');
            if (!in_array($status, ['active', 'frozen', 'suspended', 'closed'], true)) {
                throw new RuntimeException('Invalid wallet status.');
            }
            $pdo->prepare('UPDATE wallets SET status = ?, last_activity_at = NOW() WHERE id = ?')->execute([$status, $walletId]);
            wx_audit($pdo, (int) ($walletAdmin['id'] ?? 0), 'update_wallet_status', 'wallet', (string) $walletId, 'Wallet marked ' . $status);
            wx_redirect('user-wallets', 'Wallet status updated.');
        }

        if ($action === 'adjust_wallet_balance') {
            $walletId = (int) wx_post('wallet_id');
            $direction = wx_post('direction', 'credit');
            $amount = max(0.0, (float) wx_post('amount'));
            if ($walletId <= 0 || $amount <= 0 || !in_array($direction, ['credit', 'debit', 'hold', 'release_hold'], true)) {
                throw new RuntimeException('Select a wallet, direction, and valid amount.');
            }
            $wallet = wx_rows($pdo, 'SELECT * FROM wallets WHERE id = ? LIMIT 1', [$walletId])[0] ?? null;
            if (!$wallet) {
                throw new RuntimeException('Wallet not found.');
            }
            $before = (float) ($wallet['balance'] ?? 0);
            $holdBefore = (float) ($wallet['hold_balance'] ?? 0);
            $description = wx_post('notes', 'Admin wallet adjustment');
            $reference = wx_ref('WALLET-ADJ');
            if ($direction === 'credit') {
                $after = $before + $amount;
                $pdo->prepare('UPDATE wallets SET balance = balance + ?, last_activity_at = NOW() WHERE id = ?')->execute([$amount, $walletId]);
                $txType = 'credit';
                $txDirection = 'inflow';
            } elseif ($direction === 'debit') {
                if ($before < $amount) {
                    throw new RuntimeException('Insufficient wallet balance for debit.');
                }
                $after = $before - $amount;
                $pdo->prepare('UPDATE wallets SET balance = balance - ?, last_activity_at = NOW() WHERE id = ?')->execute([$amount, $walletId]);
                $txType = 'debit';
                $txDirection = 'outflow';
            } elseif ($direction === 'hold') {
                if ($before < $amount) {
                    throw new RuntimeException('Insufficient wallet balance for hold.');
                }
                $after = $before - $amount;
                $pdo->prepare('UPDATE wallets SET balance = balance - ?, hold_balance = hold_balance + ?, last_activity_at = NOW() WHERE id = ?')->execute([$amount, $amount, $walletId]);
                $txType = 'hold';
                $txDirection = 'outflow';
            } else {
                if ($holdBefore < $amount) {
                    throw new RuntimeException('Insufficient held balance to release.');
                }
                $after = $before + $amount;
                $pdo->prepare('UPDATE wallets SET balance = balance + ?, hold_balance = hold_balance - ?, last_activity_at = NOW() WHERE id = ?')->execute([$amount, $amount, $walletId]);
                $txType = 'release_hold';
                $txDirection = 'inflow';
            }
            $pdo->prepare("
                INSERT INTO wallet_transactions
                    (wallet_id, user_id, amount, type, direction, description, reference, provider, status, balance_before, balance_after, completed_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'admin', 'completed', ?, ?, NOW())
            ")->execute([$walletId, (int) ($wallet['user_id'] ?? 0), $amount, $txType, $txDirection, $description, $reference, $before, $after]);
            wx_audit($pdo, (int) ($walletAdmin['id'] ?? 0), 'adjust_wallet_balance', 'wallet', (string) $walletId, $direction . ' ' . number_format($amount, 2));
            wx_redirect('user-wallets', 'Wallet adjustment saved.');
        }

        if ($action === 'save_gateway') {
            $provider = strtolower(preg_replace('/[^a-z0-9_-]/', '', wx_post('provider')));
            $label = wx_post('label', ucwords($provider));
            if ($provider === '' || $label === '') {
                throw new RuntimeException('Gateway provider and label are required.');
            }
            $secret = wx_post('secret_key');
            $secretHint = $secret !== '' ? substr($secret, 0, 3) . '...' . substr($secret, -3) : wx_post('secret_hint');
            $pdo->prepare("
                INSERT INTO wallet_payment_gateways (provider, label, methods, fee_percent, min_fee, max_fee, status, mode, public_key, secret_hint, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE label = VALUES(label), methods = VALUES(methods), fee_percent = VALUES(fee_percent), min_fee = VALUES(min_fee), max_fee = VALUES(max_fee), status = VALUES(status), mode = VALUES(mode), public_key = VALUES(public_key), secret_hint = VALUES(secret_hint), updated_at = NOW()
            ")->execute([$provider, $label, wx_post('methods'), (float) wx_post('fee_percent'), (float) wx_post('min_fee'), (float) wx_post('max_fee'), wx_post('status', 'inactive'), wx_post('mode', 'live'), wx_post('public_key'), $secretHint, (int) ($walletAdmin['id'] ?? 0)]);
            wx_audit($pdo, (int) ($walletAdmin['id'] ?? 0), 'save_gateway', 'payment_gateway', $provider, $label);
            wx_redirect('payment-gateways', 'Payment gateway saved.');
        }

        if ($action === 'save_fee_rule') {
            $ruleKey = strtolower(preg_replace('/[^a-z0-9_-]/', '', wx_post('rule_key')));
            if ($ruleKey === '') {
                throw new RuntimeException('Fee rule key is required.');
            }
            $pdo->prepare("
                INSERT INTO wallet_fee_rules (rule_key, label, percent_rate, flat_fee, min_fee, max_fee, applies_to, status, updated_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE label = VALUES(label), percent_rate = VALUES(percent_rate), flat_fee = VALUES(flat_fee), min_fee = VALUES(min_fee), max_fee = VALUES(max_fee), applies_to = VALUES(applies_to), status = VALUES(status), updated_by = VALUES(updated_by), updated_at = NOW()
            ")->execute([$ruleKey, wx_post('label'), (float) wx_post('percent_rate'), (float) wx_post('flat_fee'), (float) wx_post('min_fee'), (float) wx_post('max_fee'), wx_post('applies_to', 'all'), wx_post('status', 'active'), (int) ($walletAdmin['id'] ?? 0)]);
            wx_audit($pdo, (int) ($walletAdmin['id'] ?? 0), 'save_fee_rule', 'fee_rule', $ruleKey, wx_post('label'));
            wx_redirect('fees-charges', 'Fee rule saved.');
        }

        if ($action === 'save_tax_document') {
            $pdo->prepare("
                INSERT INTO wallet_tax_compliance_documents (title, category, reference, amount, due_date, status, notes, created_by)
                VALUES (?, ?, ?, ?, NULLIF(?, ''), ?, ?, ?)
            ")->execute([wx_post('title'), wx_post('category', 'tax'), wx_post('reference'), (float) wx_post('amount'), wx_post('due_date'), wx_post('status', 'pending'), wx_post('notes'), (int) ($walletAdmin['id'] ?? 0)]);
            wx_audit($pdo, (int) ($walletAdmin['id'] ?? 0), 'save_tax_document', 'tax_compliance', wx_post('reference'), wx_post('title'));
            wx_redirect('tax-compliance', 'Compliance record saved.');
        }

        if ($action === 'update_transaction_status') {
            $txId = (int) wx_post('transaction_id');
            $status = wx_post('status', 'completed');
            if (!in_array($status, ['pending', 'processing', 'completed', 'successful', 'failed', 'rejected', 'reversed'], true)) {
                throw new RuntimeException('Invalid transaction status.');
            }
            $pdo->prepare("UPDATE wallet_transactions SET status = ?, completed_at = IF(? IN ('completed','successful'), NOW(), completed_at) WHERE id = ?")
                ->execute([$status, $status, $txId]);
            wx_audit($pdo, (int) ($walletAdmin['id'] ?? 0), 'update_transaction_status', 'wallet_transaction', (string) $txId, 'Transaction marked ' . $status);
            wx_redirect('transactions', 'Transaction status updated.');
        }

        if ($action === 'process_withdrawal') {
            $withdrawalId = (int) wx_post('withdrawal_id');
            $decision = wx_post('decision', 'approve');
            $note = wx_post('admin_note');
            $result = wallet_admin_process_withdrawal($pdo, $withdrawalId, (int) ($walletAdmin['id'] ?? 0), $decision, $note);
            if (!$result['success']) {
                throw new RuntimeException((string) ($result['error'] ?? 'Unable to process withdrawal.'));
            }
            wx_audit($pdo, (int) ($walletAdmin['id'] ?? 0), 'process_withdrawal', 'wallet_withdrawal', (string) $withdrawalId, $decision . ': ' . $note);
            wx_redirect('withdrawals', $decision === 'reject' ? 'Withdrawal rejected and funds released.' : 'Withdrawal approved for payout.');
        }
    } catch (Throwable $e) {
        wx_redirect(wx_post('page', 'overview'), '', $e->getMessage());
    }
}

$requestedPage = preg_replace('/[^a-z0-9-]/', '', (string) ($_GET['page'] ?? 'overview')) ?: 'overview';
$walletNotice = (string) ($_GET['message'] ?? '');
$walletError = (string) ($_GET['error'] ?? '');

$txAllowedPerPage = [10, 25, 50, 100, 200, 500];
$txType = strtolower(preg_replace('/[^a-z]/', '', (string) ($_GET['tx_type'] ?? 'all'))) ?: 'all';
$txStatus = strtolower(preg_replace('/[^a-z_]/', '', (string) ($_GET['tx_status'] ?? 'all'))) ?: 'all';
$txCategory = strtolower(preg_replace('/[^a-z]/', '', (string) ($_GET['tx_category'] ?? 'all'))) ?: 'all';
$txSearch = trim((string) ($_GET['tx_q'] ?? ''));
$txDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['tx_date'] ?? '')) ? (string) $_GET['tx_date'] : '';
$txPerPage = (int) ($_GET['tx_per_page'] ?? 25);
if (!in_array($txPerPage, $txAllowedPerPage, true)) {
    $txPerPage = 25;
}
$txPage = max(1, (int) ($_GET['tx_page'] ?? 1));
$txWhere = [];
$txParams = [];
if ($txType === 'credit') {
    $txWhere[] = "(wt.direction IN ('credit','inflow') OR wt.type IN ('credit','funding','deposit'))";
} elseif ($txType === 'debit') {
    $txWhere[] = "(wt.direction IN ('debit','outflow') OR wt.type IN ('debit','payment','withdrawal','payout','refund'))";
}
if ($txStatus !== 'all') {
    $statusMap = [
        'successful' => ['completed', 'successful', 'success', 'paid', 'settled'],
        'pending' => ['pending', 'processing', 'requested', 'scheduled'],
        'failed' => ['failed', 'rejected', 'cancelled'],
    ];
    $statuses = $statusMap[$txStatus] ?? [$txStatus];
    $txWhere[] = 'wt.status IN (' . implode(',', array_fill(0, count($statuses), '?')) . ')';
    array_push($txParams, ...$statuses);
}
if ($txCategory !== 'all') {
    $needle = '%' . $txCategory . '%';
    $txWhere[] = '(LOWER(wt.type) LIKE ? OR LOWER(wt.provider) LIKE ? OR LOWER(wt.description) LIKE ? OR LOWER(wt.reference) LIKE ?)';
    array_push($txParams, $needle, $needle, $needle, $needle);
}
if ($txDate !== '') {
    $txWhere[] = 'DATE(wt.created_at) = ?';
    $txParams[] = $txDate;
}
if ($txSearch !== '') {
    $needle = '%' . $txSearch . '%';
    $txWhere[] = '(wt.reference LIKE ? OR wt.description LIKE ? OR u.name LIKE ? OR u.email LIKE ? OR CAST(wt.id AS CHAR) LIKE ?)';
    array_push($txParams, $needle, $needle, $needle, $needle, $needle);
}
$txWhereSql = $txWhere ? 'WHERE ' . implode(' AND ', $txWhere) : '';
$txJoinSql = 'FROM wallet_transactions wt LEFT JOIN wallets w ON w.id = wt.wallet_id LEFT JOIN users u ON u.id = COALESCE(wt.user_id, w.user_id)';
$txFilterQuery = [
    'page' => 'transactions',
    'tx_type' => $txType,
    'tx_status' => $txStatus,
    'tx_category' => $txCategory,
    'tx_q' => $txSearch,
    'tx_date' => $txDate,
    'tx_per_page' => $txPerPage,
];
$txTabUrl = static function (array $overrides) use ($txFilterQuery): string {
    return wx_wallet_url(array_merge($txFilterQuery, ['tx_page' => 1], $overrides));
};

$adminDisplayName = (string) ($walletAdmin['name'] ?? 'Admin');
$adminDisplayRole = ucwords(str_replace('_', ' ', (string) ($walletAdmin['platform_role'] ?? $walletAdmin['role'] ?? 'Admin')));
$adminInitials = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $adminDisplayName) ?: 'AD', 0, 2));

$walletNetPosition = wx_scalar($pdo, "SELECT COALESCE(SUM(balance), 0) FROM wallets");
$platformBalance = wx_scalar($pdo, "SELECT COALESCE(SUM(CASE WHEN balance > 0 THEN balance ELSE 0 END), 0) FROM wallets");
$walletDebitExposure = wx_scalar($pdo, "SELECT ABS(COALESCE(SUM(CASE WHEN balance < 0 THEN balance ELSE 0 END), 0)) FROM wallets");
$negativeWalletCount = (int) wx_scalar($pdo, "SELECT COUNT(*) FROM wallets WHERE balance < 0");
$todayInflow = wx_scalar($pdo, "SELECT COALESCE(SUM(amount), 0) FROM wallet_transactions WHERE (direction IN ('credit','inflow') OR type IN ('credit','funding','deposit')) AND DATE(created_at) = CURDATE()");
$todayOutflow = wx_scalar($pdo, "SELECT COALESCE(SUM(ABS(amount)), 0) FROM wallet_transactions WHERE (direction IN ('debit','outflow') OR type IN ('debit','withdrawal','payment','payout')) AND DATE(created_at) = CURDATE()");
$pendingRefunds = wx_scalar($pdo, "SELECT COALESCE(SUM(amount), 0) FROM academy_refund_requests WHERE status IN ('pending','under_review','approved')");
$sellerPayoutsDue = wx_scalar($pdo, "SELECT COALESCE(SUM(total_amount), 0) FROM marketplace_orders WHERE payment_status IN ('paid','successful') AND settled_at IS NULL");
$failedPayments = wx_scalar($pdo, "SELECT COALESCE(SUM(ABS(amount)), 0) FROM wallet_transactions WHERE status IN ('failed','rejected') AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$walletCount = (int) wx_scalar($pdo, "SELECT COUNT(*) FROM wallets");
$activeWallets = (int) wx_scalar($pdo, "SELECT COUNT(*) FROM wallets WHERE status = 'active'");
$frozenWallets = (int) wx_scalar($pdo, "SELECT COUNT(*) FROM wallets WHERE status IN ('frozen','suspended')");
$heldBalance = wx_scalar($pdo, "SELECT COALESCE(SUM(hold_balance), 0) FROM wallets");
$reservedAccounts = (int) wx_scalar($pdo, "SELECT COUNT(*) FROM wallets WHERE reserved_account_number IS NOT NULL AND reserved_account_number <> ''");
$successfulPayments = (int) wx_scalar($pdo, "SELECT COUNT(*) FROM wallet_transactions WHERE status IN ('completed','successful','success','paid')");
$failedCount = (int) wx_scalar($pdo, "SELECT COUNT(*) FROM wallet_transactions WHERE status IN ('failed','rejected')");
$creditCount = (int) wx_scalar($pdo, "SELECT COUNT(*) FROM wallet_transactions WHERE direction IN ('credit','inflow') OR type IN ('credit','funding','deposit')");
$debitCount = (int) wx_scalar($pdo, "SELECT COUNT(*) FROM wallet_transactions WHERE direction IN ('debit','outflow') OR type IN ('debit','payment','withdrawal','payout')");
$pendingWithdrawalCount = (int) wx_scalar($pdo, "SELECT COUNT(*) FROM wallet_withdrawals WHERE status = 'pending'");
$pendingWithdrawalAmount = wx_scalar($pdo, "SELECT COALESCE(SUM(amount), 0) FROM wallet_withdrawals WHERE status = 'pending'");
$activeGateways = (int) wx_scalar($pdo, "SELECT COUNT(*) FROM wallet_payment_gateways WHERE status = 'active'");
$pendingCompliance = (int) wx_scalar($pdo, "SELECT COUNT(*) FROM wallet_tax_compliance_documents WHERE status IN ('pending','due','overdue')");
$vatCollected = wx_scalar($pdo, "SELECT COALESCE(SUM(total_amount * 0.075), 0) FROM marketplace_orders WHERE payment_status IN ('paid','successful') AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");
$withholdingTax = wx_scalar($pdo, "SELECT COALESCE(SUM(ABS(amount) * 0.05), 0) FROM wallet_transactions WHERE direction IN ('debit','outflow') AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");

$totalTransactions = (int) wx_scalar($pdo, "SELECT COUNT(*) {$txJoinSql} {$txWhereSql}", $txParams);
$totalTransactionVolume = wx_scalar($pdo, "SELECT COALESCE(SUM(ABS(wt.amount)), 0) {$txJoinSql} {$txWhereSql}", $txParams);
$successfulFilteredTransactions = (int) wx_scalar($pdo, "SELECT COUNT(*) {$txJoinSql} {$txWhereSql}" . ($txWhereSql ? " AND" : " WHERE") . " wt.status IN ('completed','successful','success','paid','settled')", $txParams);
$failedFilteredTransactions = (int) wx_scalar($pdo, "SELECT COUNT(*) {$txJoinSql} {$txWhereSql}" . ($txWhereSql ? " AND" : " WHERE") . " wt.status IN ('failed','rejected','cancelled')", $txParams);
$txTotalPages = max(1, (int) ceil($totalTransactions / $txPerPage));
if ($txPage > $txTotalPages) {
    $txPage = $txTotalPages;
}
$txOffset = ($txPage - 1) * $txPerPage;
$txShowingFrom = $totalTransactions > 0 ? $txOffset + 1 : 0;
$txShowingTo = min($totalTransactions, $txOffset + $txPerPage);
$txPageUrl = static function (int $page) use ($txFilterQuery): string {
    return wx_wallet_url(array_merge($txFilterQuery, ['tx_page' => max(1, $page)]));
};

if ((string) ($_GET['export'] ?? '') === 'transactions') {
    $exportRows = wx_rows($pdo, "
        SELECT wt.*, u.name user_name, u.email user_email
        {$txJoinSql}
        {$txWhereSql}
        ORDER BY wt.created_at DESC, wt.id DESC
    ", $txParams);
    wx_csv('wallet-transactions-' . date('Y-m-d') . '.csv', ['Reference', 'Date', 'Type', 'Provider', 'Description', 'Counterparty', 'Amount', 'Status'], array_map(static function (array $tx): array {
        return [
            $tx['reference'] ?? ('TRX-' . ($tx['id'] ?? '')),
            $tx['created_at'] ?? '',
            $tx['direction'] ?: ($tx['type'] ?? ''),
            $tx['provider'] ?? '',
            $tx['description'] ?? '',
            $tx['user_name'] ?: ($tx['user_email'] ?? 'Platform'),
            $tx['amount'] ?? 0,
            $tx['status'] ?? '',
        ];
    }, $exportRows));
}

$transactions = wx_rows($pdo, "
    SELECT wt.*, u.name user_name, u.email user_email
    {$txJoinSql}
    {$txWhereSql}
    ORDER BY wt.created_at DESC, wt.id DESC
    LIMIT {$txPerPage} OFFSET {$txOffset}
", $txParams);
$refundRows = wx_rows($pdo, "
    SELECT rr.*, u.name requester_name, u.email requester_email, wb.title course_title
    FROM academy_refund_requests rr
    LEFT JOIN users u ON u.id = rr.user_id
    LEFT JOIN webinars wb ON wb.id = rr.webinar_id
    ORDER BY FIELD(rr.status, 'pending','under_review','approved','rejected'), rr.requested_at DESC
    LIMIT 100
");
$payoutRows = wx_rows($pdo, "
    SELECT mo.*, ms.store_name, ms.email seller_email
    FROM marketplace_orders mo
    LEFT JOIN marketplace_sellers ms ON ms.id = mo.seller_id
    WHERE mo.payment_status IN ('paid','successful') AND mo.settled_at IS NULL
    ORDER BY mo.created_at DESC
    LIMIT 100
");
$withdrawalRows = wx_rows($pdo, "
    SELECT ww.*, u.name user_name, u.email user_email, u.role user_role, u.platform_role
    FROM wallet_withdrawals ww
    LEFT JOIN users u ON u.id = ww.user_id
    ORDER BY FIELD(ww.status, 'pending','processing','approved','rejected'), ww.requested_at DESC
    LIMIT 120
");
$settlementRows = wx_rows($pdo, "
    SELECT DATE(created_at) settlement_date, COUNT(*) order_count, COUNT(DISTINCT seller_id) seller_count, COALESCE(SUM(total_amount), 0) amount
    FROM marketplace_orders
    WHERE payment_status IN ('paid','successful') AND settled_at IS NULL
    GROUP BY DATE(created_at)
    ORDER BY settlement_date DESC
    LIMIT 40
");
$academyPayments = wx_rows($pdo, "
    SELECT wr.id, wr.payment_status, wr.registered_at, u.name user_name, u.email user_email, wb.title course_title, wb.price
    FROM webinar_registrations wr
    LEFT JOIN users u ON u.id = wr.user_id
    LEFT JOIN webinars wb ON wb.id = wr.webinar_id
    ORDER BY wr.registered_at DESC
    LIMIT 100
");
$academySummary = wx_rows($pdo, "
    SELECT
      COALESCE(SUM(CASE WHEN payment_status IN ('paid','successful') THEN price ELSE 0 END), 0) collections,
      COALESCE(SUM(CASE WHEN payment_status IN ('pending','processing') THEN price ELSE 0 END), 0) outstanding,
      COALESCE(SUM(CASE WHEN payment_status IN ('paid','successful') THEN 1 ELSE 0 END), 0) successful_count
    FROM webinar_registrations wr
    LEFT JOIN webinars wb ON wb.id = wr.webinar_id
")[0] ?? ['collections' => 0, 'outstanding' => 0, 'successful_count' => 0];
$walletRows = wx_rows($pdo, "
    SELECT w.*, u.name user_name, u.email user_email, u.role, u.platform_role
    FROM wallets w
    LEFT JOIN users u ON u.id = w.user_id
    ORDER BY w.created_at DESC, w.id DESC
    LIMIT 120
");
$bankRows = wx_rows($pdo, "SELECT * FROM wallet_admin_bank_accounts ORDER BY created_at DESC LIMIT 80");
$reconRows = wx_rows($pdo, "SELECT wr.*, u.name admin_name FROM wallet_reconciliation_runs wr LEFT JOIN users u ON u.id = wr.created_by ORDER BY wr.created_at DESC LIMIT 80");
$gatewayRows = wx_rows($pdo, "SELECT * FROM wallet_payment_gateways ORDER BY FIELD(status, 'active','test','inactive'), label LIMIT 50");
$feeRows = wx_rows($pdo, "SELECT * FROM wallet_fee_rules ORDER BY FIELD(status, 'active','inactive'), applies_to, label LIMIT 80");
$taxRows = wx_rows($pdo, "
    SELECT td.*, u.name admin_name
    FROM wallet_tax_compliance_documents td
    LEFT JOIN users u ON u.id = td.created_by
    ORDER BY FIELD(td.status, 'overdue','pending','due','filed','paid'), td.due_date ASC, td.created_at DESC
    LIMIT 80
");
$auditRows = wx_rows($pdo, "
    SELECT ae.*, u.name actor_name, u.email actor_email
    FROM wallet_admin_audit_events ae
    LEFT JOIN users u ON u.id = ae.actor_id
    ORDER BY ae.created_at DESC, ae.id DESC
    LIMIT 120
");
$riskRows = wx_rows($pdo, "
    SELECT reference, description, amount, status, created_at
    FROM wallet_transactions
    WHERE status IN ('failed','rejected') OR ABS(amount) >= 250000
    ORDER BY created_at DESC
    LIMIT 50
");
$userOptions = wx_rows($pdo, "SELECT id, name, email, role, platform_role FROM users ORDER BY name LIMIT 300");

$walletPayload = [
    'page' => $requestedPage,
    'notice' => $walletNotice,
    'error' => $walletError,
    'admin' => [
        'name' => $adminDisplayName,
        'role' => $adminDisplayRole,
        'initials' => $adminInitials,
    ],
    'metrics' => compact('platformBalance', 'walletNetPosition', 'walletDebitExposure', 'negativeWalletCount', 'todayInflow', 'todayOutflow', 'pendingRefunds', 'sellerPayoutsDue', 'pendingWithdrawalCount', 'pendingWithdrawalAmount', 'failedPayments', 'walletCount', 'activeWallets', 'frozenWallets', 'heldBalance', 'reservedAccounts', 'successfulPayments', 'failedCount', 'creditCount', 'debitCount', 'activeGateways', 'pendingCompliance', 'vatCollected', 'withholdingTax', 'totalTransactions', 'totalTransactionVolume', 'successfulFilteredTransactions', 'failedFilteredTransactions'),
    'txMeta' => [
        'page' => $txPage,
        'perPage' => $txPerPage,
        'total' => $totalTransactions,
        'totalPages' => $txTotalPages,
        'showingFrom' => $txShowingFrom,
        'showingTo' => $txShowingTo,
    ],
    'txExportUrl' => wx_wallet_url(array_merge($txFilterQuery, ['export' => 'transactions'])),
    'transactions' => $transactions,
    'refunds' => $refundRows,
    'payouts' => $payoutRows,
    'withdrawals' => $withdrawalRows,
    'settlements' => $settlementRows,
    'academyPayments' => $academyPayments,
    'academySummary' => $academySummary,
    'wallets' => $walletRows,
    'banks' => $bankRows,
    'reconciliations' => $reconRows,
    'gateways' => $gatewayRows,
    'fees' => $feeRows,
    'taxDocs' => $taxRows,
    'audits' => $auditRows,
    'risks' => $riskRows,
    'users' => $userOptions,
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>NATCODEV Wallet - Admin Workspace</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/inter@5.0.0/index.min.css">
<style>
*{margin:0;padding:0;box-sizing:border-box}
:root{
  --g900:#0a2418;--g800:#0f3324;--g700:#164a33;--g600:#1e6b47;--g500:#2a9d6a;--g400:#34c48a;--g300:#5dd8a3;--g200:#a8e6c9;--g100:#d4f5e4;--g50:#eefbf4;
  --bg:#f4f6f4;--card:#fff;--text:#1a1a1a;--text2:#6b7280;--border:#e5e7eb;
  --danger:#dc2626;--warn:#f59e0b;--info:#3b82f6;--success:#10b981;--purple:#8b5cf6;--orange:#f97316;--pink:#ec4899;
}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);display:flex;min-height:100vh;font-size:13px}
.sidebar{width:260px;background:var(--g900);color:#fff;position:fixed;top:0;left:0;bottom:0;overflow-y:auto;z-index:100;display:flex;flex-direction:column}
.sidebar-header{padding:16px;display:flex;align-items:center;gap:12px;border-bottom:1px solid rgba(255,255,255,.1)}
.sidebar-logo{width:44px;height:44px;background:var(--g400);border-radius:10px;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:10px;color:var(--g900);flex-shrink:0;text-align:center;line-height:1.1}
.sidebar-brand{font-size:14px;font-weight:700;line-height:1.2}
.sidebar-brand small{display:block;font-size:9px;font-weight:400;opacity:.7;margin-top:2px;line-height:1.3}
.workspace-badge{margin:14px 16px 4px;padding:5px 10px;background:rgba(255,255,255,.08);border-radius:6px;font-size:9px;text-transform:uppercase;letter-spacing:1px;opacity:.6}
.workspace-select{margin:0 16px 12px;padding:10px 12px;background:rgba(255,255,255,.06);border-radius:8px;display:flex;align-items:center;justify-content:space-between;font-size:13px;font-weight:600;cursor:pointer;border:1px solid rgba(255,255,255,.1)}
.nav-section{padding:6px 0}
.nav-section-title{padding:0 16px;font-size:9px;text-transform:uppercase;letter-spacing:1px;opacity:.5;margin-bottom:4px}
.nav-item{display:flex;align-items:center;gap:11px;padding:9px 16px;cursor:pointer;transition:all .15s;font-size:13px;color:rgba(255,255,255,.75);border-left:3px solid transparent}
.nav-item:hover{background:rgba(255,255,255,.08);color:#fff}
.nav-item.active{background:var(--g600);color:#fff;border-left-color:var(--g400)}
.nav-item svg{width:17px;height:17px;flex-shrink:0}
.nav-item .badge{margin-left:auto;background:var(--orange);color:#fff;font-size:10px;font-weight:700;padding:2px 7px;border-radius:10px}
.nav-group-header{display:flex;align-items:center;justify-content:space-between;padding:9px 16px;cursor:pointer;font-size:13px;color:rgba(255,255,255,.75)}
.nav-group-header:hover{color:#fff}
.nav-group-header svg.arrow{width:14px;height:14px;transition:transform .2s}
.nav-group.open .nav-group-header svg.arrow{transform:rotate(90deg)}
.nav-sub{display:none}
.nav-group.open .nav-sub{display:block}
.nav-sub .nav-item{padding-left:44px;font-size:12px}
.sidebar-footer{margin-top:auto;padding:14px 16px;border-top:1px solid rgba(255,255,255,.1);display:flex;align-items:center;gap:10px}
.sidebar-avatar{width:38px;height:38px;border-radius:50%;background:var(--g600);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0}
.sidebar-user{font-size:13px;font-weight:600}
.sidebar-user small{display:block;font-size:11px;opacity:.6;font-weight:400}
.status-dot{width:7px;height:7px;background:var(--success);border-radius:50%;display:inline-block;margin-right:3px}

.main{margin-left:260px;flex:1;min-height:100vh}
.topbar{background:#fff;padding:12px 24px;display:flex;align-items:center;gap:14px;border-bottom:1px solid var(--border);position:sticky;top:0;z-index:50}
.menu-toggle{display:none;background:none;border:none;cursor:pointer;font-size:20px;color:var(--text)}
.topbar-search{flex:1;max-width:480px;position:relative}
.topbar-search input{width:100%;padding:9px 14px 9px 38px;border:1px solid var(--border);border-radius:8px;font-size:13px;background:var(--bg)}
.topbar-search svg{position:absolute;left:12px;top:50%;transform:translateY(-50%);width:16px;height:16px;color:var(--text2)}
.topbar-kbd{position:absolute;right:12px;top:50%;transform:translateY(-50%);font-size:10px;color:var(--text2);background:#fff;padding:2px 6px;border:1px solid var(--border);border-radius:4px}
.topbar-actions{display:flex;align-items:center;gap:10px;margin-left:auto}
.topbar-icon{width:36px;height:36px;border-radius:8px;border:1px solid var(--border);display:flex;align-items:center;justify-content:center;cursor:pointer;position:relative;background:#fff;color:var(--text);text-decoration:none}
.topbar-icon:hover{background:var(--bg)}
.topbar-icon .dot{position:absolute;top:5px;right:5px;width:8px;height:8px;background:var(--danger);border-radius:50%;border:2px solid #fff}
.wallet-balance-top{display:flex;align-items:center;gap:8px;padding:6px 12px;background:var(--g50);border:1px solid var(--border);border-radius:8px;cursor:pointer;color:var(--text);text-decoration:none}
.wallet-balance-top strong{font-size:13px}
.wallet-balance-top small{font-size:10px;color:var(--text2);display:block}
.profile-menu-wrap{position:relative}
.topbar-profile{display:flex;align-items:center;gap:10px;min-width:0;max-width:260px;cursor:pointer;padding:4px 10px 4px 6px;border-radius:8px}
.topbar-profile:hover{background:var(--bg)}
.topbar-avatar{width:36px;height:36px;border-radius:50%;background:var(--g600);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:13px}
.topbar-profile-info{display:flex;min-width:0;max-width:160px;flex-direction:column;align-items:flex-start;font-size:13px;font-weight:700;line-height:1.15;text-align:left}
.topbar-profile-info,.topbar-profile-info small{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.topbar-profile-info small{display:block;max-width:100%;margin-top:2px;font-size:11px;color:var(--text2);font-weight:500}
.topbar-avatar{flex:0 0 36px}
.topbar-profile svg{flex:0 0 auto}
.topbar-menu{display:none;position:absolute;right:0;top:48px;width:220px;background:#fff;border:1px solid var(--border);border-radius:10px;box-shadow:0 18px 40px rgba(0,0,0,.12);padding:8px;z-index:80}
.topbar-menu.active{display:block}
.topbar-menu a{display:flex;align-items:center;gap:8px;padding:9px 10px;border-radius:8px;color:var(--text);text-decoration:none;font-weight:600}
.topbar-menu a:hover{background:var(--bg)}

.content{padding:22px}
.page{display:none}
.page.active{display:block}
.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px}
.page-title{font-size:22px;font-weight:700}
.page-subtitle{font-size:13px;color:var(--text2);margin-top:2px}
.btn{padding:9px 16px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:none;display:inline-flex;align-items:center;gap:6px;transition:all .15s}
.btn-primary{background:var(--g700);color:#fff}
.btn-primary:hover{background:var(--g800)}
.btn-secondary{background:#fff;color:var(--text);border:1px solid var(--border)}
.btn-secondary:hover{background:var(--bg)}
.btn-danger{background:var(--danger);color:#fff}
.btn-warn{background:var(--warn);color:#fff}
.btn-success{background:var(--success);color:#fff}
.btn-info{background:var(--info);color:#fff}
.btn-sm{padding:6px 12px;font-size:12px}
.btn-icon{padding:6px;background:none;border:1px solid var(--border);border-radius:6px;cursor:pointer;font-size:14px}
.btn-ghost{background:transparent;border:none;color:var(--g700);font-weight:600}

.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:14px;margin-bottom:20px}
.stat-card{background:#fff;padding:18px;border-radius:12px;border:1px solid var(--border)}
.stat-card-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px}
.stat-card-icon{width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px}
.stat-card-label{font-size:11px;color:var(--text2);font-weight:500;text-transform:uppercase;letter-spacing:.3px}
.stat-card-value{font-size:24px;font-weight:700;margin-top:4px}
.stat-card-change{font-size:11px;margin-top:6px;font-weight:500}
.stat-card-change.up{color:var(--success)}
.stat-card-change.down{color:var(--danger)}
.stat-card-sub{font-size:11px;color:var(--text2);margin-top:2px}

.card{background:#fff;border-radius:12px;border:1px solid var(--border);margin-bottom:18px}
.card-header{padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px}
.card-title{font-size:15px;font-weight:700}
.card-body{padding:20px}
.card-body.p0{padding:0}

table{width:100%;border-collapse:collapse}
th,td{padding:11px 18px;text-align:left;font-size:12.5px}
th{background:var(--bg);font-weight:600;color:var(--text2);font-size:10.5px;text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid var(--border)}
td{border-bottom:1px solid var(--border)}
tr:last-child td{border-bottom:none}
tr:hover td{background:var(--g50)}

.status-badge{padding:4px 10px;border-radius:20px;font-size:11px;font-weight:600;display:inline-block}
.status-success,.status-completed,.status-active,.status-verified,.status-valid,.status-delivered,.status-approved,.status-live,.status-reconciled{background:#dcfce7;color:#166534}
.status-pending,.status-review,.status-processing,.status-scheduled{background:#fef3c7;color:#92400e}
.status-info,.status-credit,.status-under-review{background:#dbeafe;color:#1e40af}
.status-debit,.status-draft,.status-inactive{background:#f3f4f6;color:#4b5563}
.status-danger,.status-cancelled,.status-rejected,.status-failed,.status-revoked,.status-open,.status-high-risk{background:#fee2e2;color:#991b1b}
.status-warn,.status-expiring{background:#fff7ed;color:#c2410c}

.progress-bar{height:6px;background:var(--border);border-radius:3px;overflow:hidden;width:100%}
.progress-fill{height:100%;background:var(--g500);border-radius:3px;transition:width .3s}

.form-group{margin-bottom:14px}
.form-label{display:block;font-size:11.5px;font-weight:600;margin-bottom:6px;color:var(--text2);text-transform:uppercase;letter-spacing:.3px}
.form-input,.form-select,.form-textarea{width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:8px;font-size:13px;font-family:inherit}
.form-input:focus,.form-select:focus,.form-textarea:focus{outline:none;border-color:var(--g500);box-shadow:0 0 0 3px rgba(42,157,106,.1)}
.form-textarea{resize:vertical;min-height:80px}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.form-row-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px}

.tabs{display:flex;gap:4px;border-bottom:1px solid var(--border);margin-bottom:18px;overflow-x:auto}
.tab{padding:10px 16px;font-size:13px;font-weight:500;cursor:pointer;border-bottom:2px solid transparent;color:var(--text2);white-space:nowrap;text-decoration:none}
.tab.active{color:var(--g700);border-bottom-color:var(--g700);font-weight:600}
.tab:hover{color:var(--text)}

.filter-bar{display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;align-items:center}
.filter-bar input,.filter-bar select{padding:8px 12px;border:1px solid var(--border);border-radius:8px;font-size:12.5px}

.modal-overlay{display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:200;align-items:center;justify-content:center}
.modal-overlay.active{display:flex}
.modal{background:#fff;border-radius:12px;width:90%;max-width:600px;max-height:90vh;overflow-y:auto}
.modal-header{padding:18px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
.modal-title{font-size:16px;font-weight:700}
.modal-body{padding:22px}
.modal-footer{padding:16px 22px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:10px}

.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:18px}
.grid-3{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}
.grid-4{display:grid;grid-template-columns:repeat(4,1fr);gap:18px}

.avatar-sm{width:32px;height:32px;border-radius:50%;background:var(--g100);color:var(--g700);display:inline-flex;align-items:center;justify-content:center;font-weight:600;font-size:11px;flex-shrink:0}
.avatar-row{display:flex;align-items:center;gap:10px}

.toast{position:fixed;bottom:24px;right:24px;background:var(--g800);color:#fff;padding:12px 20px;border-radius:8px;font-size:13px;z-index:300;display:none;animation:slideIn .3s}
@keyframes slideIn{from{transform:translateX(100%);opacity:0}to{transform:translateX(0);opacity:1}}

.chip{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;background:var(--g100);color:var(--g700);border-radius:20px;font-size:11px;font-weight:500}
.chip-warn{background:#fef3c7;color:#92400e}
.chip-danger{background:#fee2e2;color:#991b1b}

.quick-action-card{padding:18px;border:1px solid var(--border);border-radius:12px;cursor:pointer;transition:all .2s;background:#fff;display:flex;align-items:center;gap:14px}
.quick-action-card:hover{border-color:var(--g500);box-shadow:0 4px 12px rgba(0,0,0,.06);transform:translateY(-1px)}
.quick-action-icon{width:44px;height:44px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0}
.quick-action-title{font-weight:700;font-size:13.5px;margin-bottom:2px}
.quick-action-desc{font-size:11.5px;color:var(--text2)}

.wallet-card{background:linear-gradient(135deg,var(--g800) 0%,var(--g700) 50%,var(--g600) 100%);color:#fff;border-radius:14px;padding:24px;position:relative;overflow:hidden}
.wallet-card::before{content:'';position:absolute;top:-50px;right:-50px;width:200px;height:200px;background:radial-gradient(circle,rgba(255,255,255,.08) 0%,transparent 70%);border-radius:50%}
.wallet-card-label{font-size:12px;opacity:.8;margin-bottom:6px}
.wallet-card-value{font-size:32px;font-weight:700;margin-bottom:16px;display:flex;align-items:center;gap:10px}
.wallet-card-row{display:flex;gap:24px}
.wallet-card-row .item{flex:1}
.wallet-card-row .item-label{font-size:11px;opacity:.7;margin-bottom:3px}
.wallet-card-row .item-value{font-size:16px;font-weight:600}

.fund-method{padding:16px;border:1px solid var(--border);border-radius:10px;cursor:pointer;transition:all .2s;display:flex;align-items:center;gap:14px}
.fund-method:hover{border-color:var(--g500);background:var(--g50)}
.fund-method-icon{width:44px;height:44px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0}
.fund-method-title{font-weight:700;font-size:13.5px;margin-bottom:2px}
.fund-method-desc{font-size:11.5px;color:var(--text2)}

.alert-card{padding:14px;border-radius:10px;border-left:4px solid;display:flex;gap:12px;align-items:start}
.alert-card.high{background:#fef2f2;border-color:var(--danger)}
.alert-card.medium{background:#fff7ed;border-color:var(--warn)}
.alert-card.low{background:#eff6ff;border-color:var(--info)}

.kpi-mini{display:flex;align-items:center;gap:10px;padding:10px 14px;background:var(--bg);border-radius:8px;margin-bottom:8px}
.kpi-mini .icon{font-size:18px}
.kpi-mini .label{font-size:11px;color:var(--text2)}
.kpi-mini .value{font-size:16px;font-weight:700}

@media(max-width:1100px){.grid-4{grid-template-columns:repeat(2,1fr)}.grid-3{grid-template-columns:1fr 1fr}}
@media(max-width:900px){
  .sidebar{width:70px}.sidebar-brand,.workspace-badge,.workspace-select span,.nav-section-title,.nav-item span:not(.badge),.sidebar-user,.sidebar-user small,.nav-item .badge,.nav-group-header span{display:none}
  .nav-item{justify-content:center;padding:12px}.nav-sub .nav-item{padding-left:12px}
  .main{margin-left:70px}.grid-2,.grid-3,.grid-4,.form-row,.form-row-3{grid-template-columns:1fr}
  .menu-toggle{display:block}.topbar-kbd{display:none}
}
</style>
</head>
<body>

<aside class="sidebar">
  <div class="sidebar-header">
    <div class="sidebar-logo">🌴<br>NAT<br>CODEV</div>
    <div class="sidebar-brand">NATCODEV<small>Coconut Development &<br>Propagation Initiative</small></div>
  </div>
  <div class="workspace-badge">WALLET WORKSPACE</div>
  <div class="workspace-select"><span>💰 Wallet</span><span>▾</span></div>

  <div class="nav-section">
    <div class="nav-item active" data-page="overview">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
      <span>Overview</span>
    </div>
    <div class="nav-group open">
      <div class="nav-group-header" onclick="this.parentElement.classList.toggle('open')">
        <span style="display:flex;align-items:center;gap:11px"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:17px;height:17px"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg><span>Transactions</span></span>
        <svg class="arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
      </div>
      <div class="nav-sub">
        <div class="nav-item" data-page="transactions"><span>All Transactions</span></div>
        <div class="nav-item" data-page="credits"><span>Credits</span></div>
        <div class="nav-item" data-page="debits"><span>Debits</span></div>
      </div>
    </div>
    <div class="nav-item" data-page="fund-wallet">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
      <span>Fund Wallet</span>
    </div>
    <div class="nav-item" data-page="payments">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
      <span>Payments</span>
    </div>
    <div class="nav-item" data-page="refunds">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 102.13-9.36L1 10"/></svg>
      <span>Refunds</span>
      <span class="badge">12</span>
    </div>
    <div class="nav-item" data-page="marketplace-payouts">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
      <span>Marketplace Payouts</span>
    </div>
    <div class="nav-item" data-page="withdrawals">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M3 10h18M5 6l7-3 7 3M6 10v8M10 10v8M14 10v8M18 10v8"/></svg>
      <span>Withdrawals</span>
      <span class="badge"><?= (int) $pendingWithdrawalCount ?></span>
    </div>
    <div class="nav-item" data-page="academy-payments">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
      <span>Academy Payments</span>
    </div>
    <div class="nav-item" data-page="settlements">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v16"/></svg>
      <span>Settlements</span>
    </div>
    <div class="nav-item" data-page="bank-accounts">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M3 10h18M5 6l7-3 7 3M4 10v11M20 10v11M8 14v3M12 14v3M16 14v3"/></svg>
      <span>Bank Accounts</span>
    </div>
    <div class="nav-item" data-page="user-wallets">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      <span>Stakeholder Wallets</span>
    </div>
    <div class="nav-item" data-page="reconciliation">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
      <span>Reconciliation</span>
    </div>
    <div class="nav-item" data-page="fraud-alerts">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>
      <span>Fraud & Risk</span>
    </div>
  </div>

  <div class="nav-section">
    <div class="nav-section-title">Wallet Shortcuts</div>
    <div class="nav-item" data-page="fund-wallet">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
      <span>Fund Wallet</span>
    </div>
    <div class="nav-item" data-page="refunds">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 102.13-9.36L1 10"/></svg>
      <span>Request Refund</span>
    </div>
    <div class="nav-item" data-page="export-statement">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
      <span>Export Statement</span>
    </div>
    <div class="nav-item" data-page="reconciliation">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
      <span>Reconcile Payments</span>
    </div>
  </div>

  <div class="nav-section">
    <div class="nav-group">
      <div class="nav-group-header" onclick="this.parentElement.classList.toggle('open')">
        <span style="display:flex;align-items:center;gap:11px"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:17px;height:17px"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg><span>Reports</span></span>
        <svg class="arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
      </div>
      <div class="nav-sub">
        <div class="nav-item" data-page="reports"><span>Analytics</span></div>
        <div class="nav-item" data-page="audit-logs"><span>Audit Logs</span></div>
        <div class="nav-item" data-page="export-statement"><span>Export Data</span></div>
      </div>
    </div>
    <div class="nav-group">
      <div class="nav-group-header" onclick="this.parentElement.classList.toggle('open')">
        <span style="display:flex;align-items:center;gap:11px"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:17px;height:17px"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg><span>Settings</span></span>
        <svg class="arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
      </div>
      <div class="nav-sub">
        <div class="nav-item" data-page="settings"><span>General</span></div>
        <div class="nav-item" data-page="payment-gateways"><span>Payment Gateways</span></div>
        <div class="nav-item" data-page="fees-charges"><span>Fees & Charges</span></div>
        <div class="nav-item" data-page="tax-compliance"><span>Tax & Compliance</span></div>
        <div class="nav-item" data-page="user-wallets"><span>Stakeholder Wallets</span></div>
        <div class="nav-item" data-page="notifications-settings"><span>Notifications</span></div>
      </div>
    </div>
  </div>

  <div class="sidebar-footer">
    <div class="sidebar-avatar"><?= wx_e($adminInitials) ?></div>
    <div class="sidebar-user"><?= wx_e($adminDisplayName) ?><small><span class="status-dot"></span><?= wx_e($adminDisplayRole) ?> Online</small></div>
  </div>
</aside>

<div class="main">
  <div class="topbar">
    <button class="menu-toggle" onclick="document.querySelector('.sidebar').classList.toggle('collapsed')">☰</button>
    <div class="topbar-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" placeholder="Search wallets, transactions, reference, users..." id="globalSearch">
      <span class="topbar-kbd">CTRL + K</span>
    </div>
    <div class="topbar-actions">
      <a class="topbar-icon" href="<?= wx_e($walletAdminBase) ?>/index.php" title="Workspace Hub">⌂</a>
      <a class="topbar-icon" href="<?= wx_e($walletPublicBase) ?>/index.php" title="Public Homepage">↗</a>
      <a class="topbar-icon" href="<?= wx_e($walletAdminBase) ?>/notifications.php" title="Notifications"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg><?= $failedCount > 0 ? '<span class="dot"></span>' : '' ?></a>
      <a class="topbar-icon" href="<?= wx_e($walletAdminBase) ?>/support.php" title="Messages and support"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></a>
      <a class="wallet-balance-top" href="<?= wx_e(wx_wallet_url(['page' => 'user-wallets'])) ?>">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--g700)" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
        <div><small>Available Wallet Balance</small><strong><?= wx_e('NGN ' . number_format($platformBalance, 2)) ?></strong></div>
      </a>
      <div class="profile-menu-wrap">
        <button class="topbar-profile" type="button" id="profileMenuButton" aria-haspopup="true" aria-expanded="false">
          <div class="topbar-avatar"><?= wx_e($adminInitials) ?></div>
          <div class="topbar-profile-info"><?= wx_e($adminDisplayName) ?><small><?= wx_e($adminDisplayRole) ?></small></div>
        </button>
        <div class="topbar-menu" id="profileMenu">
          <a href="<?= wx_e($walletAdminBase) ?>/profile.php">Edit Profile</a>
          <a href="<?= wx_e($walletAdminBase) ?>/settings.php">Settings</a>
          <a href="<?= wx_e($walletAdminBase) ?>/index.php">Workspace Hub</a>
          <a href="<?= wx_e($walletPublicBase) ?>/index.php">Public Homepage</a>
          <a href="<?= wx_e($walletAdminBase) ?>/index.php?logout=1">Logout</a>
          <a href="<?= wx_e($walletAdminBase) ?>/admin.php?logout=1">Logout via Legacy Admin</a>
          <a href="<?= wx_e($walletAdminBase) ?>/login.php?logout=1">Logout to Login</a>
        </div>
      </div>
    </div>
  </div>

  <div class="content">

    <!-- OVERVIEW -->
    <div class="page active" id="page-overview">
      <div class="page-header">
        <div><div class="page-title">NATCODEV Wallet</div><div class="page-subtitle">Monitor balances, transactions, payouts, refunds, and reconciliation across the platform.</div></div>
        <div class="filter-bar" style="margin:0"><button class="btn btn-secondary btn-sm">📅 May 18 – May 24, 2026 ▾</button></div>
      </div>

      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-header"><div class="stat-card-label">Total Wallet Balance</div><div class="stat-card-icon" style="background:var(--g100);color:var(--g700)">💰</div></div><div class="stat-card-value">₦<?= number_format($platformBalance, 0) ?></div><div class="stat-card-change">Platform Position</div></div>
        <div class="stat-card"><div class="stat-card-header"><div class="stat-card-label">Today's Inflow</div><div class="stat-card-icon" style="background:#dbeafe;color:#1e40af">⬇️</div></div><div class="stat-card-value"><?= number_format($todayInflow, 0) ?></div><div class="stat-card-change">Today</div></div>
        <div class="stat-card"><div class="stat-card-header"><div class="stat-card-label">Today's Outflow</div><div class="stat-card-icon" style="background:#fee2e2;color:#991b1b">⬆️</div></div><div class="stat-card-value">₦<?= number_format($todayOutflow, 0) ?></div><div class="stat-card-change">Today</div></div>
        <div class="stat-card"><div class="stat-card-header"><div class="stat-card-label">Pending Refunds</div><div class="stat-card-icon" style="background:#fef3c7;color:#92400e">🕐</div></div><div class="stat-card-value">₦<?= number_format($pendingRefunds, 0) ?></div><div class="stat-card-change">Active Requests</div></div>
        <div class="stat-card"><div class="stat-card-header"><div class="stat-card-label">Seller Payouts (Due)</div><div class="stat-card-icon" style="background:var(--g100);color:var(--g700)">💵</div></div><div class="stat-card-value">₦<?= number_format($sellerPayoutsDue, 0) ?></div><div class="stat-card-change">Pending Settlements</div></div>
        <div class="stat-card"><div class="stat-card-header"><div class="stat-card-label">Failed Payments</div><div class="stat-card-icon" style="background:#fee2e2;color:#991b1b">⚠️</div></div><div class="stat-card-value">₦<?= number_format($failedPayments, 0) ?></div><div class="stat-card-change">Last 30 Days</div></div>
      </div>

      <div class="grid-3">
        <div class="card" style="grid-column:span 1">
          <div class="card-header"><div class="card-title">Wallet Balance Overview</div><button class="btn-ghost btn-sm" onclick="navigateTo('transactions')">View Details</button></div>
          <div class="card-body">
            <div class="wallet-card">
              <div class="wallet-card-label">Platform Wallet Balance</div>
              <div class="wallet-card-value">₦24,977,388.45 <span style="font-size:18px;cursor:pointer">👁</span></div>
              <div class="wallet-card-row">
                <div class="item"><div class="item-label">Available Balance</div><div class="item-value">₦21,643,210.45</div></div>
                <div class="item"><div class="item-label">On Hold / Reserved</div><div class="item-value">₦3,334,178.00</div></div>
              </div>
              <div style="margin-top:16px;padding-top:14px;border-top:1px solid rgba(255,255,255,.15);display:flex;justify-content:space-between;font-size:11px;opacity:.8">
                <span>Currency: NGN</span><span>Wallet ID: WL-2026-00001</span>
              </div>
              <div style="margin-top:8px;font-size:11px;opacity:.7">Last Updated: May 24, 2026 09:45 AM</div>
              <div style="margin-top:10px"><span class="status-badge status-live" style="background:rgba(255,255,255,.2);color:#fff">● Active</span></div>
            </div>
          </div>
        </div>

        <div class="card" style="grid-column:span 2">
          <div class="card-header"><div class="card-title">Recent Transactions</div><button class="btn-ghost btn-sm" onclick="navigateTo('transactions')">View All</button></div>
          <div class="card-body p0">
            <table>
              <thead><tr><th>TXN ID</th><th>Date & Time</th><th>Type</th><th>Description</th><th>Counterparty</th><th>Amount</th><th>Status</th></tr></thead>
              <tbody>
                <tr><td><strong>TRX-260524-00078</strong></td><td>May 24, 09:33 AM</td><td><span class="status-badge status-credit">Credit</span></td><td>Marketplace Order Payment</td><td>John Okafor</td><td style="color:var(--success);font-weight:600">+₦245,000</td><td><span class="status-badge status-success">Successful</span></td></tr>
                <tr><td><strong>TRX-260524-00077</strong></td><td>May 24, 09:12 AM</td><td><span class="status-badge status-debit">Debit</span></td><td>Seller Payout</td><td>Green Farms Ltd</td><td style="color:var(--danger);font-weight:600">-₦185,000</td><td><span class="status-badge status-pending">Processing</span></td></tr>
                <tr><td><strong>TRX-260523-00076</strong></td><td>May 23, 06:45 PM</td><td><span class="status-badge status-credit">Credit</span></td><td>Wallet Funding (Monnify)</td><td>Mary Abiodun</td><td style="color:var(--success);font-weight:600">+₦120,000</td><td><span class="status-badge status-success">Successful</span></td></tr>
                <tr><td><strong>TRX-260523-00075</strong></td><td>May 23, 04:18 PM</td><td><span class="status-badge status-debit">Debit</span></td><td>Refund Processed</td><td>Emeka Okafor</td><td style="color:var(--danger);font-weight:600">-₦45,000</td><td><span class="status-badge status-success">Successful</span></td></tr>
                <tr><td><strong>TRX-260523-00074</strong></td><td>May 23, 11:08 AM</td><td><span class="status-badge status-debit">Debit</span></td><td>Academy Course Payment</td><td>Grace Deh</td><td style="color:var(--danger);font-weight:600">-₦15,500</td><td><span class="status-badge status-success">Successful</span></td></tr>
              </tbody>
            </table>
          </div>
          <div style="padding:12px 20px;border-top:1px solid var(--border);text-align:right"><button class="btn btn-primary btn-sm" onclick="navigateTo('transactions')">View All Transactions →</button></div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><div class="card-title">Fund Wallet</div><button class="btn-ghost btn-sm" onclick="navigateTo('fund-wallet')">View All Methods</button></div>
        <div class="card-body">
          <div class="grid-4">
            <div class="fund-method" onclick="openModal('fundModal')">
              <div class="fund-method-icon" style="background:#dbeafe;color:#1e40af">💳</div>
              <div style="flex:1"><div class="fund-method-title">Monnify Payment <span class="chip" style="margin-left:6px">Recommended</span></div><div class="fund-method-desc">Instant funding via cards, bank transfer, USSD</div></div>
              <span>›</span>
            </div>
            <div class="fund-method" onclick="openModal('fundModal')">
              <div class="fund-method-icon" style="background:var(--g100);color:var(--g700)">🏦</div>
              <div style="flex:1"><div class="fund-method-title">Direct Bank Transfer</div><div class="fund-method-desc">Transfer directly to NATCODEV bank account</div></div>
              <span>›</span>
            </div>
            <div class="fund-method" onclick="openModal('fundModal')">
              <div class="fund-method-icon" style="background:#fce7f3;color:#be185d">💳</div>
              <div style="flex:1"><div class="fund-method-title">Card Payment</div><div class="fund-method-desc">Fund instantly using debit/credit cards</div></div>
              <span>›</span>
            </div>
            <div class="fund-method" onclick="openModal('bulkFundModal')">
              <div class="fund-method-icon" style="background:#ede9fe;color:#5b21b6">📤</div>
              <div style="flex:1"><div class="fund-method-title">Bulk Wallet Funding</div><div class="fund-method-desc">Upload CSV to fund multiple wallets</div></div>
              <span>›</span>
            </div>
          </div>
        </div>
      </div>

      <div class="grid-4">
        <div class="card">
          <div class="card-header"><div class="card-title">Refund Requests Queue</div><button class="btn-ghost btn-sm" onclick="navigateTo('refunds')">View All</button></div>
          <div class="card-body p0">
            <table>
              <thead><tr><th>REF ID</th><th>Requester</th><th>Amount</th><th>Status</th></tr></thead>
              <tbody>
                <?php if (!$withdrawals): ?>
                    <tr><td colspan="4" style="text-align:center;padding:20px;color:var(--muted)">No pending refund requests.</td></tr>
                <?php else: ?>
                    <?php foreach (array_slice($withdrawals, 0, 5) as $wd): ?>
                        <tr>
                            <td><strong><?= wx_e((string)($wd['reference'] ?? '---')) ?></strong></td>
                            <td><?= wx_e((string)($wd['user_name'] ?: ($wd['user_email'] ?? '---'))) ?></td>
                            <td>₦<?= number_format((float)($wd['amount'] ?? 0), 2) ?></td>
                            <td><span class="status-badge status-<?= wx_e(wx_badge((string)($wd['status'] ?? 'pending'))) ?>"><?= wx_e(ucwords((string)($wd['status'] ?? 'pending'))) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
          <div style="padding:12px 20px;border-top:1px solid var(--border);text-align:right"><button class="btn btn-primary btn-sm" onclick="navigateTo('refunds')">View All Refunds →</button></div>
        </div>

        <div class="card">
          <div class="card-header"><div class="card-title">Marketplace Settlement Schedule</div><button class="btn-ghost btn-sm" onclick="navigateTo('settlements')">View All</button></div>
          <div class="card-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px">
              <div style="padding:12px;background:var(--g50);border-radius:8px"><div style="font-size:11px;color:var(--text2)">Total Due for Payout</div><div style="font-size:18px;font-weight:700;color:var(--g700)">₦2,845,900</div></div>
              <div style="padding:12px;background:var(--g50);border-radius:8px"><div style="font-size:11px;color:var(--text2)">Sellers Due</div><div style="font-size:18px;font-weight:700;color:var(--g700)">128</div></div>
            </div>
            <table style="font-size:12px">
              <tbody>
                <tr><td>May 25, 2026</td><td>48 sellers</td><td>₦1,245,600</td><td><span class="status-badge status-scheduled">Scheduled</span></td></tr>
                <tr><td>May 26, 2026</td><td>36 sellers</td><td>₦845,300</td><td><span class="status-badge status-scheduled">Scheduled</span></td></tr>
                <tr><td>May 27, 2026</td><td>28 sellers</td><td>₦755,000</td><td><span class="status-badge status-scheduled">Scheduled</span></td></tr>
              </tbody>
            </table>
          </div>
          <div style="padding:12px 20px;border-top:1px solid var(--border);text-align:right"><button class="btn btn-primary btn-sm" onclick="navigateTo('settlements')">View Settlement Calendar →</button></div>
        </div>

        <div class="card">
          <div class="card-header"><div class="card-title">Academy Payment Summary</div><button class="btn-ghost btn-sm" onclick="navigateTo('academy-payments')">View All</button></div>
          <div class="card-body">
            <div class="kpi-mini"><div class="icon">💰</div><div style="flex:1"><div class="label">Total Collections (MTD)</div><div class="value">₦1,245,600</div></div></div>
            <div class="kpi-mini"><div class="icon">✅</div><div style="flex:1"><div class="label">Successful Payments</div><div class="value" style="color:var(--success)">₦1,210,100</div></div></div>
            <div class="kpi-mini"><div class="icon">↩️</div><div style="flex:1"><div class="label">Refunds Issued</div><div class="value" style="color:var(--danger)">₦35,500</div></div></div>
            <div class="kpi-mini"><div class="icon">⏳</div><div style="flex:1"><div class="label">Outstanding Payments</div><div class="value" style="color:var(--warn)">₦248,750</div></div></div>
          </div>
          <div style="padding:12px 20px;border-top:1px solid var(--border);text-align:right"><button class="btn btn-primary btn-sm" onclick="navigateTo('academy-payments')">View Academy Payments →</button></div>
        </div>

        <div class="card">
          <div class="card-header"><div class="card-title">Reconciliation Status</div><button class="btn-ghost btn-sm" onclick="navigateTo('reconciliation')">View Report</button></div>
          <div class="card-body">
            <div style="display:flex;flex-direction:column;gap:10px">
              <div style="display:flex;justify-content:space-between;align-items:center;font-size:12px"><div style="display:flex;align-items:center;gap:8px"><span style="color:var(--success)">✅</span> Wallet Transactions Reconciled</div><span style="color:var(--text2);font-size:11px">May 24, 09:30 AM</span></div>
              <div style="display:flex;justify-content:space-between;align-items:center;font-size:12px"><div style="display:flex;align-items:center;gap:8px"><span style="color:var(--success)">✅</span> Bank Statement Reconciled</div><span style="color:var(--text2);font-size:11px">May 23, 11:45 PM</span></div>
              <div style="display:flex;justify-content:space-between;align-items:center;font-size:12px"><div style="display:flex;align-items:center;gap:8px"><span style="color:var(--success)">✅</span> Payouts Reconciled</div><span style="color:var(--text2);font-size:11px">May 24, 08:10 AM</span></div>
              <div style="display:flex;justify-content:space-between;align-items:center;font-size:12px"><div style="display:flex;align-items:center;gap:8px"><span style="color:var(--success)">✅</span> Refunds Reconciled</div><span style="color:var(--text2);font-size:11px">May 24, 08:15 AM</span></div>
              <div style="display:flex;justify-content:space-between;align-items:center;font-size:12px"><div style="display:flex;align-items:center;gap:8px"><span style="color:var(--success)">✅</span> Fees & Charges Reconciled</div><span style="color:var(--text2);font-size:11px">May 24, 08:20 AM</span></div>
            </div>
          </div>
          <div style="padding:12px 20px;border-top:1px solid var(--border);text-align:right"><button class="btn btn-primary btn-sm" onclick="navigateTo('reconciliation')">Reconcile Now →</button></div>
        </div>
      </div>

      <div class="grid-2">
        <div class="card">
          <div class="card-header"><div class="card-title">Fraud & Risk Alerts</div><button class="btn-ghost btn-sm" onclick="navigateTo('fraud-alerts')">View All</button></div>
          <div class="card-body">
            <div style="display:flex;flex-direction:column;gap:12px">
              <div class="alert-card high">
                <div style="font-size:20px">🚨</div>
                <div style="flex:1"><div style="font-weight:700;font-size:13px;margin-bottom:3px">High Risk Transaction Detected</div><div style="font-size:12px;color:var(--text2)">Transaction TRX-260524-00061 flagged</div><div style="font-size:14px;font-weight:700;color:var(--danger);margin-top:4px">₦250,000</div><div style="font-size:11px;color:var(--text2);margin-top:3px">May 24, 2026 07:32 AM</div></div>
              </div>
              <div class="alert-card medium">
                <div style="font-size:20px">⚠️</div>
                <div style="flex:1"><div style="font-weight:700;font-size:13px;margin-bottom:3px">Multiple Refunds Detected</div><div style="font-size:12px;color:var(--text2)">User John Okafor has 3 refund requests</div><div style="font-size:11px;color:var(--text2);margin-top:6px">May 24, 2026 06:18 AM</div></div>
              </div>
              <div class="alert-card low">
                <div style="font-size:20px">🔔</div>
                <div style="flex:1"><div style="font-weight:700;font-size:13px;margin-bottom:3px">Unusual Activity</div><div style="font-size:12px;color:var(--text2)">Login from new device - Abia State</div><div style="font-size:11px;color:var(--text2);margin-top:6px">May 24, 2026 05:40 AM</div></div>
              </div>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-header"><div class="card-title">Quick Actions</div></div>
          <div class="card-body">
            <div class="grid-2">
              <div class="quick-action-card" onclick="navigateTo('fund-wallet')"><div class="quick-action-icon" style="background:var(--g100);color:var(--g700)">💰</div><div style="flex:1"><div class="quick-action-title">Fund Wallet</div><div class="quick-action-desc">Add money to platform wallet</div></div><span style="font-size:18px">→</span></div>
              <div class="quick-action-card" onclick="openModal('refundModal')"><div class="quick-action-icon" style="background:#fef3c7;color:#92400e">↩️</div><div style="flex:1"><div class="quick-action-title">Request Refund</div><div class="quick-action-desc">Process customer refund</div></div><span style="font-size:18px">→</span></div>
              <div class="quick-action-card" onclick="navigateTo('export-statement')"><div class="quick-action-icon" style="background:#dbeafe;color:#1e40af">⬇️</div><div style="flex:1"><div class="quick-action-title">Export Statement</div><div class="quick-action-desc">Download transaction report</div></div><span style="font-size:18px">→</span></div>
              <div class="quick-action-card" onclick="navigateTo('reconciliation')"><div class="quick-action-icon" style="background:#ede9fe;color:#5b21b6">️</div><div style="flex:1"><div class="quick-action-title">Reconcile Payments</div><div class="quick-action-desc">Match transactions & bank</div></div><span style="font-size:18px">→</span></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- TRANSACTIONS -->
    <div class="page" id="page-transactions">
      <div class="page-header"><div><div class="page-title">All Transactions</div><div class="page-subtitle">Live transaction history from wallet_transactions, filtered by the controls below</div></div><a class="btn btn-primary" href="<?= wx_e(wx_wallet_url(array_merge($txFilterQuery, ['export' => 'transactions']))) ?>">Export CSV</a></div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">Total Transactions</div><div class="stat-card-value"><?= wx_e(number_format($totalTransactions)) ?></div><div class="stat-card-change up"><?= wx_e(number_format($txShowingFrom) . '-' . number_format($txShowingTo)) ?> showing</div></div>
        <div class="stat-card"><div class="stat-card-label">Total Volume</div><div class="stat-card-value"><?= wx_e('NGN ' . number_format($totalTransactionVolume, 0)) ?></div></div>
        <div class="stat-card"><div class="stat-card-label">Successful</div><div class="stat-card-value" style="color:var(--success)"><?= wx_e(number_format($successfulFilteredTransactions)) ?></div></div>
        <div class="stat-card"><div class="stat-card-label">Failed</div><div class="stat-card-value" style="color:var(--danger)"><?= wx_e(number_format($failedFilteredTransactions)) ?></div></div>
      </div>
      <div class="tabs">
        <a class="tab <?= $txType === 'all' && $txStatus === 'all' ? 'active' : '' ?>" href="<?= wx_e($txTabUrl(['tx_type' => 'all', 'tx_status' => 'all'])) ?>">All Transactions</a>
        <a class="tab <?= $txType === 'credit' ? 'active' : '' ?>" href="<?= wx_e($txTabUrl(['tx_type' => 'credit', 'tx_status' => 'all'])) ?>">Credits</a>
        <a class="tab <?= $txType === 'debit' ? 'active' : '' ?>" href="<?= wx_e($txTabUrl(['tx_type' => 'debit', 'tx_status' => 'all'])) ?>">Debits</a>
        <a class="tab <?= $txStatus === 'successful' ? 'active' : '' ?>" href="<?= wx_e($txTabUrl(['tx_type' => 'all', 'tx_status' => 'successful'])) ?>">Successful</a>
        <a class="tab <?= $txStatus === 'pending' ? 'active' : '' ?>" href="<?= wx_e($txTabUrl(['tx_type' => 'all', 'tx_status' => 'pending'])) ?>">Pending</a>
        <a class="tab <?= $txStatus === 'failed' ? 'active' : '' ?>" href="<?= wx_e($txTabUrl(['tx_type' => 'all', 'tx_status' => 'failed'])) ?>">Failed</a>
      </div>
      <form class="filter-bar" method="get">
        <input type="hidden" name="page" value="transactions">
        <input type="text" name="tx_q" value="<?= wx_e($txSearch) ?>" placeholder="Search by TXN ID, user, reference...">
        <select name="tx_type"><option value="all" <?= $txType === 'all' ? 'selected' : '' ?>>All Types</option><option value="credit" <?= $txType === 'credit' ? 'selected' : '' ?>>Credit</option><option value="debit" <?= $txType === 'debit' ? 'selected' : '' ?>>Debit</option></select>
        <select name="tx_category"><option value="all" <?= $txCategory === 'all' ? 'selected' : '' ?>>All Categories</option><option value="marketplace" <?= $txCategory === 'marketplace' ? 'selected' : '' ?>>Marketplace</option><option value="academy" <?= $txCategory === 'academy' ? 'selected' : '' ?>>Academy</option><option value="payout" <?= $txCategory === 'payout' ? 'selected' : '' ?>>Payout</option><option value="refund" <?= $txCategory === 'refund' ? 'selected' : '' ?>>Refund</option><option value="funding" <?= $txCategory === 'funding' ? 'selected' : '' ?>>Funding</option></select>
        <select name="tx_status"><option value="all" <?= $txStatus === 'all' ? 'selected' : '' ?>>All Statuses</option><option value="successful" <?= $txStatus === 'successful' ? 'selected' : '' ?>>Successful</option><option value="pending" <?= $txStatus === 'pending' ? 'selected' : '' ?>>Pending</option><option value="failed" <?= $txStatus === 'failed' ? 'selected' : '' ?>>Failed</option></select>
        <input type="date" name="tx_date" value="<?= wx_e($txDate) ?>">
        <select name="tx_per_page"><?php foreach ($txAllowedPerPage as $size): ?><option value="<?= $size ?>" <?= $txPerPage === $size ? 'selected' : '' ?>><?= $size ?> / page</option><?php endforeach; ?></select>
        <button class="btn btn-secondary btn-sm" type="submit">Filter</button>
      </form>
      <div class="card"><div class="card-body p0">
        <table id="transactionsTable">
          <thead><tr><th>TXN ID</th><th>Date & Time</th><th>Type</th><th>Category</th><th>Description</th><th>Counterparty</th><th>Reference</th><th>Amount</th><th>Fee</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
<?php foreach ($transactions as $tx):
    $txDirection = (str_contains(strtolower((string) ($tx['direction'] ?? '')), 'out') || in_array(strtolower((string) ($tx['type'] ?? '')), ['debit', 'payment', 'payout', 'withdrawal', 'refund'], true)) ? 'Debit' : 'Credit';
    $txRef = (string) ($tx['reference'] ?? ('TRX-' . ($tx['id'] ?? '')));
    $txCategory = (string) ($tx['provider'] ?: ($tx['type'] ?? 'wallet'));
    $txAmount = (float) ($tx['amount'] ?? 0);
    $txSigned = ($txDirection === 'Debit' ? '-' : '+') . 'NGN ' . number_format(abs($txAmount), 2);
?>
            <tr><td><strong><?= wx_e($txRef) ?></strong></td><td><?= wx_e(wx_dt((string) ($tx['created_at'] ?? ''))) ?></td><td><span class="status-badge <?= $txDirection === 'Debit' ? 'status-debit' : 'status-credit' ?>"><?= wx_e($txDirection) ?></span></td><td><?= wx_e(ucwords(str_replace('_', ' ', $txCategory))) ?></td><td><?= wx_e((string) ($tx['description'] ?? 'Wallet transaction')) ?></td><td><?= wx_e((string) ($tx['user_name'] ?: ($tx['user_email'] ?? 'Platform'))) ?></td><td><?= wx_e($txRef) ?></td><td style="color:var(<?= $txDirection === 'Debit' ? '--danger' : '--success' ?>);font-weight:600"><?= wx_e($txSigned) ?></td><td><?= wx_e('NGN ' . number_format((float) ($tx['fee'] ?? $tx['charge'] ?? 0), 2)) ?></td><td><span class="status-badge status-<?= wx_e(wx_badge((string) ($tx['status'] ?? 'pending'))) ?>"><?= wx_e(ucwords(str_replace('_', ' ', (string) ($tx['status'] ?? 'pending')))) ?></span></td><td><button class="btn btn-sm btn-secondary" type="button">View</button></td></tr>
<?php endforeach; ?>
<?php if (!$transactions): ?>
            <tr><td colspan="11">No transactions found for the selected filters.</td></tr>
<?php endif; ?>
          </tbody>
        </table>
        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;padding:14px 20px;border-top:1px solid var(--border)">
          <span class="chip">Showing <?= wx_e(number_format($txShowingFrom)) ?>-<?= wx_e(number_format($txShowingTo)) ?> of <?= wx_e(number_format($totalTransactions)) ?></span>
          <div style="display:flex;gap:8px;align-items:center">
            <a class="btn btn-sm btn-secondary" href="<?= wx_e($txPageUrl(max(1, $txPage - 1))) ?>" <?= $txPage <= 1 ? 'aria-disabled="true" style="pointer-events:none;opacity:.55"' : '' ?>>Previous</a>
            <span class="chip">Page <?= wx_e((string) $txPage) ?> of <?= wx_e((string) $txTotalPages) ?></span>
            <a class="btn btn-sm btn-secondary" href="<?= wx_e($txPageUrl(min($txTotalPages, $txPage + 1))) ?>" <?= $txPage >= $txTotalPages ? 'aria-disabled="true" style="pointer-events:none;opacity:.55"' : '' ?>>Next</a>
          </div>
        </div>
      </div></div>
    </div>

    <!-- CREDITS -->
    <div class="page" id="page-credits">
      <div class="page-header"><div><div class="page-title">Credits</div><div class="page-subtitle">All incoming transactions to the wallet</div></div></div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">Total Credits (7D)</div><div class="stat-card-value" style="color:var(--success)">₦5,431,670</div><div class="stat-card-change up">↑ 24.2%</div></div>
        <div class="stat-card"><div class="stat-card-label">Transaction Count</div><div class="stat-card-value">2,847</div></div>
        <div class="stat-card"><div class="stat-card-label">Avg. Credit</div><div class="stat-card-value">₦1,908</div></div>
        <div class="stat-card"><div class="stat-card-label">Largest Credit</div><div class="stat-card-value">₦245,000</div></div>
      </div>
      <div class="card"><div class="card-body p0">
        <table>
          <thead><tr><th>TXN ID</th><th>Date</th><th>Source</th><th>Description</th><th>Counterparty</th><th>Amount</th><th>Status</th></tr></thead>
          <tbody>
            <tr><td><strong>TRX-260524-00078</strong></td><td>May 24, 09:33</td><td>Marketplace</td><td>Order Payment</td><td>John Okafor</td><td style="color:var(--success);font-weight:600">+₦245,000</td><td><span class="status-badge status-success">Successful</span></td></tr>
            <tr><td><strong>TRX-260523-00076</strong></td><td>May 23, 18:45</td><td>Monnify</td><td>Wallet Funding</td><td>Mary Abiodun</td><td style="color:var(--success);font-weight:600">+₦120,000</td><td><span class="status-badge status-success">Successful</span></td></tr>
            <tr><td><strong>TRX-260523-00073</strong></td><td>May 23, 09:24</td><td>Marketplace</td><td>Order Payment</td><td>Mary Abiodun</td><td style="color:var(--success);font-weight:600">+₦87,000</td><td><span class="status-badge status-success">Successful</span></td></tr>
            <tr><td><strong>TRX-260522-00071</strong></td><td>May 22, 11:32</td><td>Marketplace</td><td>Order Payment</td><td>Tunde Adewale</td><td style="color:var(--success);font-weight:600">+₦156,750</td><td><span class="status-badge status-failed">Failed</span></td></tr>
          </tbody>
        </table>
      </div></div>
    </div>

    <!-- DEBITS -->
    <div class="page" id="page-debits">
      <div class="page-header"><div><div class="page-title">Debits</div><div class="page-subtitle">All outgoing transactions from the wallet</div></div></div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">Total Debits (7D)</div><div class="stat-card-value" style="color:var(--danger)">₦3,127,450</div><div class="stat-card-change up">↑ 11.3%</div></div>
        <div class="stat-card"><div class="stat-card-label">Transaction Count</div><div class="stat-card-value">2,045</div></div>
        <div class="stat-card"><div class="stat-card-label">Avg. Debit</div><div class="stat-card-value">₦1,529</div></div>
        <div class="stat-card"><div class="stat-card-label">Largest Debit</div><div class="stat-card-value">₦320,000</div></div>
      </div>
      <div class="card"><div class="card-body p0">
        <table>
          <thead><tr><th>TXN ID</th><th>Date</th><th>Category</th><th>Description</th><th>Counterparty</th><th>Amount</th><th>Status</th></tr></thead>
          <tbody>
            <tr><td><strong>TRX-260524-00077</strong></td><td>May 24, 09:12</td><td>Payout</td><td>Seller Payout</td><td>Green Farms Ltd</td><td style="color:var(--danger);font-weight:600">-₦185,000</td><td><span class="status-badge status-pending">Processing</span></td></tr>
            <tr><td><strong>TRX-260523-00075</strong></td><td>May 23, 16:18</td><td>Refund</td><td>Refund Processed</td><td>Emeka Okafor</td><td style="color:var(--danger);font-weight:600">-45,000</td><td><span class="status-badge status-success">Successful</span></td></tr>
            <tr><td><strong>TRX-260523-00074</strong></td><td>May 23, 11:08</td><td>Academy</td><td>Course Payment</td><td>Grace Deh</td><td style="color:var(--danger);font-weight:600">-₦15,500</td><td><span class="status-badge status-success">Successful</span></td></tr>
            <tr><td><strong>TRX-260522-00072</strong></td><td>May 22, 14:56</td><td>Payout</td><td>Seller Payout</td><td>Palmbest Agro</td><td style="color:var(--danger);font-weight:600">-320,000</td><td><span class="status-badge status-success">Successful</span></td></tr>
          </tbody>
        </table>
      </div></div>
    </div>

    <!-- FUND WALLET -->
    <div class="page" id="page-fund-wallet">
      <div class="page-header"><div><div class="page-title">Fund Wallet</div><div class="page-subtitle">Add money to the platform wallet</div></div></div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">Current Balance</div><div class="stat-card-value">₦24,977,388</div></div>
        <div class="stat-card"><div class="stat-card-label">Funded Today</div><div class="stat-card-value" style="color:var(--success)">₦5,431,670</div></div>
        <div class="stat-card"><div class="stat-card-label">Funded (7D)</div><div class="stat-card-value">₦18,245,900</div></div>
        <div class="stat-card"><div class="stat-card-label">Funded (MTD)</div><div class="stat-card-value">₦72,845,200</div></div>
      </div>
      <div class="card"><div class="card-header"><div class="card-title">Funding Methods</div></div><div class="card-body">
        <div class="grid-2">
          <div class="fund-method" onclick="openModal('fundModal')" style="border:2px solid var(--g400);background:var(--g50)">
            <div class="fund-method-icon" style="background:#dbeafe;color:#1e40af">💳</div>
            <div style="flex:1"><div class="fund-method-title">Monnify Payment <span class="chip">Recommended</span></div><div class="fund-method-desc">Instant funding via cards, bank transfer, USSD. Lowest fees.</div><div style="margin-top:6px;font-size:11px;color:var(--success)">Fee: 0.5% (min ₦50, max ₦2,000)</div></div>
            <span style="font-size:20px">›</span>
          </div>
          <div class="fund-method" onclick="openModal('fundModal')">
            <div class="fund-method-icon" style="background:var(--g100);color:var(--g700)">🏦</div>
            <div style="flex:1"><div class="fund-method-title">Direct Bank Transfer</div><div class="fund-method-desc">Transfer directly to NATCODEV bank account</div><div style="margin-top:6px;font-size:11px;color:var(--text2)">Bank: GTBank | Acc: 0123456789</div></div>
            <span style="font-size:20px">›</span>
          </div>
          <div class="fund-method" onclick="openModal('fundModal')">
            <div class="fund-method-icon" style="background:#fce7f3;color:#be185d">💳</div>
            <div style="flex:1"><div class="fund-method-title">Card Payment</div><div class="fund-method-desc">Fund instantly using debit/credit cards</div><div style="margin-top:6px;font-size:11px;color:var(--text2)">Fee: 1.5%</div></div>
            <span style="font-size:20px">›</span>
          </div>
          <div class="fund-method" onclick="openModal('bulkFundModal')">
            <div class="fund-method-icon" style="background:#ede9fe;color:#5b21b6"></div>
            <div style="flex:1"><div class="fund-method-title">Bulk Wallet Funding</div><div class="fund-method-desc">Upload CSV to fund multiple wallets at once</div><div style="margin-top:6px;font-size:11px;color:var(--text2)">Best for batch operations</div></div>
            <span style="font-size:20px">›</span>
          </div>
        </div>
      </div></div>
      <div class="card"><div class="card-header"><div class="card-title">Recent Funding History</div></div><div class="card-body p0">
        <table>
          <thead><tr><th>TXN ID</th><th>Date</th><th>Method</th><th>Funded By</th><th>Amount</th><th>Fee</th><th>Status</th></tr></thead>
          <tbody>
            <tr><td><strong>TRX-260523-00076</strong></td><td>May 23, 18:45</td><td>Monnify</td><td>Mary Abiodun</td><td style="color:var(--success)">+₦120,000</td><td>₦600</td><td><span class="status-badge status-success">Successful</span></td></tr>
            <tr><td><strong>TRX-260522-00065</strong></td><td>May 22, 10:20</td><td>Bank Transfer</td><td>Green Farms Ltd</td><td style="color:var(--success)">+₦500,000</td><td>₦0</td><td><span class="status-badge status-success">Successful</span></td></tr>
            <tr><td><strong>TRX-260521-00058</strong></td><td>May 21, 15:30</td><td>Card</td><td>Grace Deh</td><td style="color:var(--success)">+250,000</td><td>₦3,750</td><td><span class="status-badge status-success">Successful</span></td></tr>
            <tr><td><strong>TRX-260520-00052</strong></td><td>May 20, 09:15</td><td>Monnify</td><td>Palmbest Agro</td><td style="color:var(--success)">+₦180,000</td><td>₦900</td><td><span class="status-badge status-success">Successful</span></td></tr>
          </tbody>
        </table>
      </div></div>
    </div>

    <!-- PAYMENTS -->
    <div class="page" id="page-payments">
      <div class="page-header"><div><div class="page-title">Payments</div><div class="page-subtitle">All payment transactions across the platform</div></div><button class="btn btn-primary" onclick="showToast('Payment report exported')">📥 Export</button></div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">Total Payments (7D)</div><div class="stat-card-value">3,247</div></div>
        <div class="stat-card"><div class="stat-card-label">Payment Volume</div><div class="stat-card-value">₦12.8M</div></div>
        <div class="stat-card"><div class="stat-card-label">Success Rate</div><div class="stat-card-value" style="color:var(--success)">97.2%</div></div>
        <div class="stat-card"><div class="stat-card-label">Failed</div><div class="stat-card-value" style="color:var(--danger)">91</div></div>
      </div>
      <div class="tabs"><div class="tab active">All Payments</div><div class="tab">Marketplace</div><div class="tab">Academy</div><div class="tab">Payouts</div><div class="tab">Failed</div></div>
      <div class="filter-bar"><input type="text" placeholder="Search payments..." oninput="filterTable('paymentsTable',this.value)"><select><option>All Channels</option><option>Monnify</option><option>Bank Transfer</option><option>Card</option></select><input type="date"><button class="btn btn-secondary btn-sm">Filter</button></div>
      <div class="card"><div class="card-body p0">
        <table id="paymentsTable">
          <thead><tr><th>Payment ID</th><th>Date</th><th>Channel</th><th>Payer</th><th>Description</th><th>Amount</th><th>Fee</th><th>Net</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
            <tr><td><strong>PAY-260524-00182</strong></td><td>May 24, 09:33</td><td>Monnify</td><td>John Okafor</td><td>Marketplace Order</td><td>₦245,000</td><td>₦2,450</td><td>₦242,550</td><td><span class="status-badge status-success">Successful</span></td><td><button class="btn-icon">👁</button></td></tr>
            <tr><td><strong>PAY-260524-00181</strong></td><td>May 24, 09:12</td><td>Bank</td><td>Green Farms Ltd</td><td>Seller Payout</td><td>₦185,000</td><td>₦1,850</td><td>₦183,150</td><td><span class="status-badge status-pending">Processing</span></td><td><button class="btn-icon"></button></td></tr>
            <tr><td><strong>PAY-260523-00180</strong></td><td>May 23, 18:45</td><td>Monnify</td><td>Mary Abiodun</td><td>Wallet Funding</td><td>₦120,000</td><td>600</td><td>₦119,400</td><td><span class="status-badge status-success">Successful</span></td><td><button class="btn-icon">👁</button></td></tr>
            <tr><td><strong>PAY-260523-00179</strong></td><td>May 23, 11:08</td><td>Card</td><td>Grace Deh</td><td>Academy Course</td><td>₦15,500</td><td>₦155</td><td>₦15,345</td><td><span class="status-badge status-success">Successful</span></td><td><button class="btn-icon">👁</button></td></tr>
            <tr><td><strong>PAY-260522-00178</strong></td><td>May 22, 11:32</td><td>Monnify</td><td>Tunde Adewale</td><td>Marketplace Order</td><td>₦156,750</td><td>₦1,567</td><td>—</td><td><span class="status-badge status-failed">Failed</span></td><td><button class="btn btn-sm btn-warn" onclick="showToast('Retry initiated')">Retry</button></td></tr>
          </tbody>
        </table>
      </div></div>
    </div>

    <!-- REFUNDS -->
    <div class="page" id="page-refunds">
      <div class="page-header"><div><div class="page-title">Refunds</div><div class="page-subtitle">12 refund requests pending action</div></div><button class="btn btn-primary" onclick="openModal('refundModal')">+ New Refund</button></div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">Pending Refunds</div><div class="stat-card-value" style="color:var(--warn)">₦856,450</div></div>
        <div class="stat-card"><div class="stat-card-label">Processed (7D)</div><div class="stat-card-value" style="color:var(--success)">₦245,000</div></div>
        <div class="stat-card"><div class="stat-card-label">Total Requests</div><div class="stat-card-value">47</div></div>
        <div class="stat-card"><div class="stat-card-label">Avg. Processing Time</div><div class="stat-card-value">2.4 days</div></div>
      </div>
      <div class="tabs"><div class="tab active">Pending (12)</div><div class="tab">Under Review</div><div class="tab">Approved</div><div class="tab">Rejected</div><div class="tab">All Refunds</div></div>
      <div class="filter-bar"><input type="text" placeholder="Search refunds..." oninput="filterTable('refundsTable',this.value)"><select><option>All Reasons</option><option>Order not received</option><option>Duplicate payment</option><option>Cancelled order</option><option>Wrong item</option></select><button class="btn btn-secondary btn-sm">Filter</button></div>
      <div class="card"><div class="card-body p0">
        <table id="refundsTable">
          <thead><tr><th>REF ID</th><th>Requester</th><th>Original TXN</th><th>Reason</th><th>Amount</th><th>Requested</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
            <tr><td><strong>TRF-260524-00015</strong></td><td><div class="avatar-row"><div class="avatar-sm">CU</div>Chinedu Uzor</div></td><td>TRX-260520-00045</td><td>Order not received</td><td>₦85,000</td><td>May 24</td><td><span class="status-badge status-pending">Pending</span></td><td><button class="btn btn-sm btn-success" onclick="showToast('Refund approved')">Approve</button> <button class="btn btn-sm btn-danger">Reject</button></td></tr>
            <tr><td><strong>TRF-260524-00014</strong></td><td><div class="avatar-row"><div class="avatar-sm">AM</div>Aisha Musa</div></td><td>TRX-260519-00038</td><td>Duplicate payment</td><td>₦45,000</td><td>May 24</td><td><span class="status-badge status-pending">Pending</span></td><td><button class="btn btn-sm btn-success" onclick="showToast('Refund approved')">Approve</button> <button class="btn btn-sm btn-danger">Reject</button></td></tr>
            <tr><td><strong>TRF-260524-00013</strong></td><td><div class="avatar-row"><div class="avatar-sm">TA</div>Tunde Adewale</div></td><td>TRX-260518-00032</td><td>Cancelled order</td><td>₦125,000</td><td>May 24</td><td><span class="status-badge status-under-review">Under Review</span></td><td><button class="btn btn-sm btn-success" onclick="showToast('Refund approved')">Approve</button> <button class="btn btn-sm btn-danger">Reject</button></td></tr>
            <tr><td><strong>TRF-260524-00012</strong></td><td><div class="avatar-row"><div class="avatar-sm">IN</div>Ifeoma Nwosu</div></td><td>TRX-260517-00028</td><td>Wrong item delivered</td><td>₦32,450</td><td>May 23</td><td><span class="status-badge status-pending">Pending</span></td><td><button class="btn btn-sm btn-success" onclick="showToast('Refund approved')">Approve</button> <button class="btn btn-sm btn-danger">Reject</button></td></tr>
            <tr><td><strong>TRF-260524-00011</strong></td><td><div class="avatar-row"><div class="avatar-sm">UL</div>Usman Lawal</div></td><td>TRX-260516-00024</td><td>Service not rendered</td><td>₦78,000</td><td>May 23</td><td><span class="status-badge status-pending">Pending</span></td><td><button class="btn btn-sm btn-success" onclick="showToast('Refund approved')">Approve</button> <button class="btn btn-sm btn-danger">Reject</button></td></tr>
            <tr><td><strong>TRF-260523-00010</strong></td><td><div class="avatar-row"><div class="avatar-sm">EO</div>Emeka Okafor</div></td><td>TRX-260515-00020</td><td>Product defective</td><td>₦45,000</td><td>May 22</td><td><span class="status-badge status-success">Approved</span></td><td><button class="btn-icon">👁</button></td></tr>
          </tbody>
        </table>
      </div></div>
    </div>

    <!-- MARKETPLACE PAYOUTS -->
    <div class="page" id="page-marketplace-payouts">
      <div class="page-header"><div><div class="page-title">Marketplace Payouts</div><div class="page-subtitle">Manage seller payouts and disbursements</div></div><button class="btn btn-primary" onclick="showToast('Batch payout initiated')">⚡ Process Batch Payout</button></div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">Due for Payout</div><div class="stat-card-value">₦2,845,900</div></div>
        <div class="stat-card"><div class="stat-card-label">Sellers Due</div><div class="stat-card-value">128</div></div>
        <div class="stat-card"><div class="stat-card-label">Processed (7D)</div><div class="stat-card-value" style="color:var(--success)">₦8,245,600</div></div>
        <div class="stat-card"><div class="stat-card-label">Failed Payouts</div><div class="stat-card-value" style="color:var(--danger)">12</div></div>
      </div>
      <div class="tabs"><div class="tab active">Scheduled</div><div class="tab">Processing</div><div class="tab">Completed</div><div class="tab">Failed</div></div>
      <div class="card"><div class="card-body p0">
        <table>
          <thead><tr><th>Payout ID</th><th>Seller</th><th>Amount</th><th>Bank</th><th>Account</th><th>Scheduled</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
            <tr><td><strong>PO-260525-00046</strong></td><td><div class="avatar-row"><div class="avatar-sm">GF</div>Green Farms Ltd</div></td><td>₦245,000</td><td>GTBank</td><td>0123456789</td><td>May 25, 2026</td><td><span class="status-badge status-scheduled">Scheduled</span></td><td><button class="btn btn-sm btn-primary" onclick="showToast('Payout processed early')">Process Now</button></td></tr>
            <tr><td><strong>PO-260525-00045</strong></td><td><div class="avatar-row"><div class="avatar-sm">PA</div>Palmbest Agro</div></td><td>187,500</td><td>First Bank</td><td>9876543210</td><td>May 25, 2026</td><td><span class="status-badge status-scheduled">Scheduled</span></td><td><button class="btn btn-sm btn-primary" onclick="showToast('Payout processed early')">Process Now</button></td></tr>
            <tr><td><strong>PO-260524-00044</strong></td><td><div class="avatar-row"><div class="avatar-sm">CH</div>Coconut Hub</div></td><td>156,750</td><td>Access Bank</td><td>5647382910</td><td>May 24, 2026</td><td><span class="status-badge status-pending">Processing</span></td><td><button class="btn-icon">👁</button></td></tr>
            <tr><td><strong>PO-260524-00043</strong></td><td><div class="avatar-row"><div class="avatar-sm">AF</div>AgroFuture Nigeria</div></td><td>98,400</td><td>UBA</td><td>1928374650</td><td>May 24, 2026</td><td><span class="status-badge status-success">Completed</span></td><td><button class="btn-icon">👁</button></td></tr>
            <tr><td><strong>PO-260523-00042</strong></td><td><div class="avatar-row"><div class="avatar-sm">IH</div>Island Harvest</div></td><td>112,400</td><td>Zenith Bank</td><td>—</td><td>May 23, 2026</td><td><span class="status-badge status-failed">Failed</span></td><td><button class="btn btn-sm btn-warn" onclick="showToast('Retry initiated')">Retry</button></td></tr>
          </tbody>
        </table>
      </div></div>
    </div>

    <!-- WITHDRAWALS -->
    <div class="page" id="page-withdrawals">
      <div class="page-header"><div><div class="page-title">Wallet Withdrawals</div><div class="page-subtitle">Approve or reject stakeholder withdrawal requests through Monnify, Paystack, or manual payout.</div></div></div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">Pending Requests</div><div class="stat-card-value"><?= (int) $pendingWithdrawalCount ?></div></div>
        <div class="stat-card"><div class="stat-card-label">Pending Amount</div><div class="stat-card-value" style="color:var(--warn)">NGN <?= wx_e(number_format($pendingWithdrawalAmount, 2)) ?></div></div>
        <div class="stat-card"><div class="stat-card-label">Monnify</div><div class="stat-card-value" style="font-size:18px"><?= monnify_is_configured() ? 'Configured' : 'Missing Keys' ?></div></div>
        <div class="stat-card"><div class="stat-card-label">Paystack</div><div class="stat-card-value" style="font-size:18px"><?= paystack_is_configured() ? 'Configured' : 'Missing Key' ?></div></div>
      </div>
      <div class="card"><div class="card-body p0">
        <table>
          <thead><tr><th>Reference</th><th>Stakeholder</th><th>Route</th><th>Bank</th><th>Gross</th><th>Net</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody></tbody>
        </table>
      </div></div>
    </div>

    <!-- ACADEMY PAYMENTS -->
    <div class="page" id="page-academy-payments">
      <div class="page-header"><div><div class="page-title">Academy Payments</div><div class="page-subtitle">Course payments and learner transactions</div></div><button class="btn btn-primary" onclick="showToast('Report exported')">📥 Export</button></div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">Total Collections (MTD)</div><div class="stat-card-value">₦1,245,600</div></div>
        <div class="stat-card"><div class="stat-card-label">Successful</div><div class="stat-card-value" style="color:var(--success)">₦1,210,100</div></div>
        <div class="stat-card"><div class="stat-card-label">Refunds Issued</div><div class="stat-card-value" style="color:var(--danger)">₦35,500</div></div>
        <div class="stat-card"><div class="stat-card-label">Outstanding</div><div class="stat-card-value" style="color:var(--warn)">₦248,750</div></div>
      </div>
      <div class="card"><div class="card-header"><div class="card-title">Recent Academy Payments</div></div><div class="card-body p0">
        <table>
          <thead><tr><th>Payment ID</th><th>Learner</th><th>Course</th><th>Amount</th><th>Date</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
            <tr><td><strong>ACAD-260524-0015</strong></td><td>Grace Deh</td><td>Power BI Essentials</td><td>₦15,500</td><td>May 24, 2026</td><td><span class="status-badge status-success">Paid</span></td><td><button class="btn-icon">👁</button></td></tr>
            <tr><td><strong>ACAD-260523-0014</strong></td><td>Aisha Koroma</td><td>Python for Data Science</td><td>₦25,000</td><td>May 23, 2026</td><td><span class="status-badge status-success">Paid</span></td><td><button class="btn-icon">👁</button></td></tr>
            <tr><td><strong>ACAD-260523-0013</strong></td><td>Tunde Salami</td><td>Agile Project Management</td><td>₦18,500</td><td>May 23, 2026</td><td><span class="status-badge status-success">Paid</span></td><td><button class="btn-icon">👁</button></td></tr>
            <tr><td><strong>ACAD-260522-0012</strong></td><td>Miriam Osei</td><td>UX/UI Design Fundamentals</td><td>₦22,000</td><td>May 22, 2026</td><td><span class="status-badge status-pending">Pending</span></td><td><button class="btn btn-sm btn-warn" onclick="showToast('Reminder sent')">Send Reminder</button></td></tr>
            <tr><td><strong>ACAD-260521-0011</strong></td><td>Fatima Ndiaye</td><td>Leadership in Public Health</td><td>₦20,000</td><td>May 21, 2026</td><td><span class="status-badge status-success">Paid</span></td><td><button class="btn-icon">👁</button></td></tr>
          </tbody>
        </table>
      </div></div>
    </div>

    <!-- SETTLEMENTS -->
    <div class="page" id="page-settlements">
      <div class="page-header"><div><div class="page-title">Settlements</div><div class="page-subtitle">Scheduled and completed settlements</div></div><button class="btn btn-primary" onclick="showToast('Settlement calendar opened')">📅 View Calendar</button></div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">Total Due</div><div class="stat-card-value">₦2,845,900</div></div>
        <div class="stat-card"><div class="stat-card-label">Settled (7D)</div><div class="stat-card-value" style="color:var(--success)">₦8,245,600</div></div>
        <div class="stat-card"><div class="stat-card-label">Sellers</div><div class="stat-card-value">128</div></div>
        <div class="stat-card"><div class="stat-card-label">Next Settlement</div><div class="stat-card-value" style="font-size:16px">May 25, 2026</div></div>
      </div>
      <div class="card"><div class="card-header"><div class="card-title">Settlement Schedule</div></div><div class="card-body p0">
        <table>
          <thead><tr><th>Settlement Date</th><th>Sellers</th><th>Total Amount</th><th>Platform Fee</th><th>Net Payout</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
            <tr><td><strong>May 25, 2026</strong></td><td>48</td><td>₦1,245,600</td><td>₦124,560</td><td>₦1,121,040</td><td><span class="status-badge status-scheduled">Scheduled</span></td><td><button class="btn btn-sm btn-primary" onclick="showToast('Settlement preview opened')">Preview</button></td></tr>
            <tr><td><strong>May 26, 2026</strong></td><td>36</td><td>₦845,300</td><td>₦84,530</td><td>₦760,770</td><td><span class="status-badge status-scheduled">Scheduled</span></td><td><button class="btn btn-sm btn-primary" onclick="showToast('Settlement preview opened')">Preview</button></td></tr>
            <tr><td><strong>May 27, 2026</strong></td><td>28</td><td>755,000</td><td>₦75,500</td><td>₦679,500</td><td><span class="status-badge status-scheduled">Scheduled</span></td><td><button class="btn btn-sm btn-primary" onclick="showToast('Settlement preview opened')">Preview</button></td></tr>
            <tr><td><strong>May 24, 2026</strong></td><td>52</td><td>1,456,800</td><td>₦145,680</td><td>₦1,311,120</td><td><span class="status-badge status-pending">Processing</span></td><td><button class="btn-icon">👁</button></td></tr>
            <tr><td><strong>May 23, 2026</strong></td><td>45</td><td>₦1,234,500</td><td>₦123,450</td><td>₦1,111,050</td><td><span class="status-badge status-success">Completed</span></td><td><button class="btn-icon">👁</button></td></tr>
          </tbody>
        </table>
      </div></div>
    </div>

    <!-- BANK ACCOUNTS -->
    <div class="page" id="page-bank-accounts">
      <div class="page-header"><div><div class="page-title">Bank Accounts</div><div class="page-subtitle">Manage platform bank accounts</div></div><button class="btn btn-primary" onclick="openModal('bankModal')">+ Add Bank Account</button></div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">Active Accounts</div><div class="stat-card-value">4</div></div>
        <div class="stat-card"><div class="stat-card-label">Total Balance</div><div class="stat-card-value">₦24,977,388</div></div>
        <div class="stat-card"><div class="stat-card-label">Primary Account</div><div class="stat-card-value" style="font-size:14px">GTBank ••••6789</div></div>
        <div class="stat-card"><div class="stat-card-label">Last Reconciled</div><div class="stat-card-value" style="font-size:14px">May 24, 2026</div></div>
      </div>
      <div class="card"><div class="card-body p0">
        <table>
          <thead><tr><th>Bank</th><th>Account Name</th><th>Account Number</th><th>Type</th><th>Balance</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
            <tr><td><div class="avatar-row"><div class="avatar-sm" style="background:#fef3c7;color:#92400e">GT</div><strong>GTBank</strong></div></td><td>NATCODEV Operations</td><td>0123456789</td><td><span class="chip">Primary</span></td><td>18,245,900</td><td><span class="status-badge status-active">Active</span></td><td><button class="btn-icon">✏️</button></td></tr>
            <tr><td><div class="avatar-row"><div class="avatar-sm" style="background:#dbeafe;color:#1e40af">FB</div><strong>First Bank</strong></div></td><td>NATCODEV Payouts</td><td>9876543210</td><td><span class="chip">Payout</span></td><td>₦4,245,600</td><td><span class="status-badge status-active">Active</span></td><td><button class="btn-icon">✏️</button></td></tr>
            <tr><td><div class="avatar-row"><div class="avatar-sm" style="background:#ede9fe;color:#5b21b6">AB</div><strong>Access Bank</strong></div></td><td>NATCODEV Reserve</td><td>5647382910</td><td><span class="chip">Reserve</span></td><td>₦2,000,000</td><td><span class="status-badge status-active">Active</span></td><td><button class="btn-icon">✏️</button></td></tr>
            <tr><td><div class="avatar-row"><div class="avatar-sm" style="background:#fce7f3;color:#be185d">UB</div><strong>UBA</strong></div></td><td>NATCODEV Academy</td><td>1928374650</td><td><span class="chip">Academy</span></td><td>₦485,888</td><td><span class="status-badge status-active">Active</span></td><td><button class="btn-icon">✏️</button></td></tr>
          </tbody>
        </table>
      </div></div>
    </div>

    <!-- RECONCILIATION -->
    <div class="page" id="page-reconciliation">
      <div class="page-header"><div><div class="page-title">Reconciliation</div><div class="page-subtitle">Match transactions with bank statements</div></div><button class="btn btn-primary" onclick="showToast('Reconciliation started...')">⚡ Reconcile Now</button></div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">Last Reconciled</div><div class="stat-card-value" style="font-size:16px">May 24, 2026</div></div>
        <div class="stat-card"><div class="stat-card-label">Matched Transactions</div><div class="stat-card-value" style="color:var(--success)">4,756</div></div>
        <div class="stat-card"><div class="stat-card-label">Unmatched</div><div class="stat-card-value" style="color:var(--warn)">23</div></div>
        <div class="stat-card"><div class="stat-card-label">Discrepancies</div><div class="stat-card-value" style="color:var(--danger)">7</div></div>
      </div>
      <div class="grid-2">
        <div class="card"><div class="card-header"><div class="card-title">Reconciliation Status</div></div><div class="card-body">
          <div style="display:flex;flex-direction:column;gap:12px">
            <div style="display:flex;justify-content:space-between;align-items:center;padding:12px;background:var(--g50);border-radius:8px"><div style="display:flex;align-items:center;gap:10px"><span style="font-size:18px">✅</span><div><div style="font-weight:600;font-size:13px">Wallet Transactions</div><div style="font-size:11px;color:var(--text2)">4,756 matched</div></div></div><span style="font-size:11px;color:var(--text2)">May 24, 09:30 AM</span></div>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:12px;background:var(--g50);border-radius:8px"><div style="display:flex;align-items:center;gap:10px"><span style="font-size:18px">✅</span><div><div style="font-weight:600;font-size:13px">Bank Statement</div><div style="font-size:11px;color:var(--text2)">GTBank statement matched</div></div></div><span style="font-size:11px;color:var(--text2)">May 23, 11:45 PM</span></div>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:12px;background:var(--g50);border-radius:8px"><div style="display:flex;align-items:center;gap:10px"><span style="font-size:18px">✅</span><div><div style="font-weight:600;font-size:13px">Payouts</div><div style="font-size:11px;color:var(--text2)">128 payouts reconciled</div></div></div><span style="font-size:11px;color:var(--text2)">May 24, 08:10 AM</span></div>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:12px;background:var(--g50);border-radius:8px"><div style="display:flex;align-items:center;gap:10px"><span style="font-size:18px">✅</span><div><div style="font-weight:600;font-size:13px">Refunds</div><div style="font-size:11px;color:var(--text2)">47 refunds reconciled</div></div></div><span style="font-size:11px;color:var(--text2)">May 24, 08:15 AM</span></div>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:12px;background:var(--g50);border-radius:8px"><div style="display:flex;align-items:center;gap:10px"><span style="font-size:18px">✅</span><div><div style="font-weight:600;font-size:13px">Fees & Charges</div><div style="font-size:11px;color:var(--text2)">All fees accounted</div></div></div><span style="font-size:11px;color:var(--text2)">May 24, 08:20 AM</span></div>
          </div>
        </div></div>
        <div class="card"><div class="card-header"><div class="card-title">Upload Bank Statement</div></div><div class="card-body">
          <div class="upload-zone" style="padding:30px" onclick="showToast('Statement upload opened')">
            <div style="font-size:40px;margin-bottom:10px">📤</div>
            <div style="font-weight:600;margin-bottom:4px">Drop bank statement here</div>
            <div style="font-size:12px;color:var(--text2)">Supports CSV, Excel, PDF (Max 20MB)</div>
          </div>
          <div style="margin-top:14px">
            <div class="form-group"><label class="form-label">Bank Account</label><select class="form-select"><option>GTBank - NATCODEV Operations</option><option>First Bank - NATCODEV Payouts</option><option>Access Bank - NATCODEV Reserve</option><option>UBA - NATCODEV Academy</option></select></div>
            <div class="form-group"><label class="form-label">Statement Period</label><div class="form-row"><input class="form-input" type="date" value="2026-05-01"><input class="form-input" type="date" value="2026-05-24"></div></div>
            <button class="btn btn-primary" style="width:100%" onclick="showToast('Reconciliation started')">Start Reconciliation</button>
          </div>
        </div></div>
      </div>
      <div class="card"><div class="card-header"><div class="card-title">Unmatched Transactions</div></div><div class="card-body p0">
        <table>
          <thead><tr><th>TXN ID</th><th>Date</th><th>Amount</th><th>Description</th><th>Issue</th><th>Actions</th></tr></thead>
          <tbody>
            <tr><td><strong>TRX-260524-00061</strong></td><td>May 24, 07:32</td><td>₦250,000</td><td>Unknown credit</td><td><span class="status-badge status-warn">No match in bank</span></td><td><button class="btn btn-sm btn-primary" onclick="showToast('Manual match opened')">Match Manually</button></td></tr>
            <tr><td><strong>TRX-260523-00058</strong></td><td>May 23, 15:20</td><td>₦45,000</td><td>Refund discrepancy</td><td><span class="status-badge status-danger">Amount mismatch</span></td><td><button class="btn btn-sm btn-primary" onclick="showToast('Manual match opened')">Match Manually</button></td></tr>
            <tr><td><strong>TRX-260522-00052</strong></td><td>May 22, 10:15</td><td>₦18,500</td><td>Duplicate entry</td><td><span class="status-badge status-warn">Possible duplicate</span></td><td><button class="btn btn-sm btn-warn" onclick="showToast('Flagged for review')">Flag</button></td></tr>
          </tbody>
        </table>
      </div></div>
    </div>

    <!-- FRAUD ALERTS -->
    <div class="page" id="page-fraud-alerts">
      <div class="page-header"><div><div class="page-title">Fraud & Risk Alerts</div><div class="page-subtitle">Monitor suspicious activities and high-risk transactions</div></div><button class="btn btn-primary" onclick="showToast('Risk report exported')"> Export Report</button></div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">High Risk</div><div class="stat-card-value" style="color:var(--danger)">3</div></div>
        <div class="stat-card"><div class="stat-card-label">Medium Risk</div><div class="stat-card-value" style="color:var(--warn)">12</div></div>
        <div class="stat-card"><div class="stat-card-label">Low Risk</div><div class="stat-card-value" style="color:var(--info)">28</div></div>
        <div class="stat-card"><div class="stat-card-label">Resolved (7D)</div><div class="stat-card-value" style="color:var(--success)">47</div></div>
      </div>
      <div class="card"><div class="card-body">
        <div style="display:flex;flex-direction:column;gap:12px">
          <div class="alert-card high"><div style="font-size:24px"></div><div style="flex:1"><div style="font-weight:700;font-size:14px;margin-bottom:4px">High Risk Transaction Detected</div><div style="font-size:12px;color:var(--text2);margin-bottom:6px">Transaction TRX-260524-00061 flagged - Unusual amount pattern</div><div style="display:flex;gap:12px;align-items:center"><span style="font-size:16px;font-weight:700;color:var(--danger)">₦250,000</span><span style="font-size:11px;color:var(--text2)">May 24, 2026 07:32 AM</span><span class="chip chip-danger">High Risk</span></div></div><button class="btn btn-sm btn-danger" onclick="showToast('Transaction blocked')">Block</button></div>
          <div class="alert-card medium"><div style="font-size:24px">️</div><div style="flex:1"><div style="font-weight:700;font-size:14px;margin-bottom:4px">Multiple Refunds Detected</div><div style="font-size:12px;color:var(--text2);margin-bottom:6px">User John Okafor has 3 refund requests in 24 hours</div><div style="display:flex;gap:12px;align-items:center"><span style="font-size:16px;font-weight:700">3 requests</span><span style="font-size:11px;color:var(--text2)">May 24, 2026 06:18 AM</span><span class="chip chip-warn">Medium Risk</span></div></div><button class="btn btn-sm btn-warn" onclick="showToast('User flagged')">Flag User</button></div>
          <div class="alert-card low"><div style="font-size:24px">🔔</div><div style="flex:1"><div style="font-weight:700;font-size:14px;margin-bottom:4px">Unusual Activity</div><div style="font-size:12px;color:var(--text2);margin-bottom:6px">Login from new device - Abia State</div><div style="display:flex;gap:12px;align-items:center"><span style="font-size:11px;color:var(--text2)">May 24, 2026 05:40 AM</span><span class="chip">Low Risk</span></div></div><button class="btn btn-sm btn-secondary" onclick="showToast('Alert acknowledged')">Acknowledge</button></div>
          <div class="alert-card medium"><div style="font-size:24px">⚠️</div><div style="flex:1"><div style="font-weight:700;font-size:14px;margin-bottom:4px">Rapid Successive Transactions</div><div style="font-size:12px;color:var(--text2);margin-bottom:6px">5 transactions within 10 minutes from same IP</div><div style="display:flex;gap:12px;align-items:center"><span style="font-size:16px;font-weight:700">5 TXNs</span><span style="font-size:11px;color:var(--text2)">May 23, 2026 14:22 PM</span><span class="chip chip-warn">Medium Risk</span></div></div><button class="btn btn-sm btn-warn" onclick="showToast('Under investigation')">Investigate</button></div>
        </div>
      </div></div>
    </div>

    <!-- REPORTS -->
    <div class="page" id="page-reports">
      <div class="page-header"><div><div class="page-title">Reports & Analytics</div><div class="page-subtitle">Comprehensive wallet insights</div></div><button class="btn btn-primary" onclick="showToast('Report generated')">📊 Generate Report</button></div>
      <div class="grid-2">
        <div class="card"><div class="card-header"><div class="card-title">Transaction Volume Trends</div></div><div class="card-body"><div style="display:flex;align-items:end;gap:10px;height:180px"><div style="flex:1;background:var(--g200);border-radius:4px 4px 0 0;height:60%"></div><div style="flex:1;background:var(--g200);border-radius:4px 4px 0 0;height:72%"></div><div style="flex:1;background:var(--g200);border-radius:4px 4px 0 0;height:65%"></div><div style="flex:1;background:var(--g200);border-radius:4px 4px 0 0;height:80%"></div><div style="flex:1;background:var(--g200);border-radius:4px 4px 0 0;height:88%"></div><div style="flex:1;background:var(--g400);border-radius:4px 4px 0 0;height:95%"></div></div><div style="display:flex;justify-content:space-between;font-size:10px;color:var(--text2);margin-top:6px"><span>Jan</span><span>Feb</span><span>Mar</span><span>Apr</span><span>May</span><span>Jun</span></div></div></div>
        <div class="card"><div class="card-header"><div class="card-title">Payment Channel Distribution</div></div><div class="card-body"><div style="display:flex;flex-direction:column;gap:10px"><div><div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px"><span>Monnify</span><strong>58%</strong></div><div class="progress-bar"><div class="progress-fill" style="width:58%"></div></div></div><div><div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px"><span>Bank Transfer</span><strong>28%</strong></div><div class="progress-bar"><div class="progress-fill" style="width:28%;background:var(--info)"></div></div></div><div><div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px"><span>Card</span><strong>14%</strong></div><div class="progress-bar"><div class="progress-fill" style="width:14%;background:var(--warn)"></div></div></div></div></div></div>
      </div>
      <div class="card"><div class="card-header"><div class="card-title">Available Reports</div></div><div class="card-body p0">
        <table>
          <thead><tr><th>Report</th><th>Description</th><th>Last Generated</th><th>Actions</th></tr></thead>
          <tbody>
            <tr><td><strong>Transaction Report</strong></td><td>Complete transaction history with filters</td><td>May 24, 2026</td><td><button class="btn btn-sm btn-primary" onclick="showToast('Downloading...')"> Download</button></td></tr>
            <tr><td><strong>Settlement Report</strong></td><td>All settlements with breakdown</td><td>May 24, 2026</td><td><button class="btn btn-sm btn-primary" onclick="showToast('Downloading...')">⬇ Download</button></td></tr>
            <tr><td><strong>Refund Report</strong></td><td>Refund requests and processing metrics</td><td>May 23, 2026</td><td><button class="btn btn-sm btn-primary" onclick="showToast('Downloading...')">⬇ Download</button></td></tr>
            <tr><td><strong>Financial Summary</strong></td><td>Revenue, fees, and net position</td><td>May 22, 2026</td><td><button class="btn btn-sm btn-primary" onclick="showToast('Downloading...')"> Download</button></td></tr>
            <tr><td><strong>Fraud & Risk Report</strong></td><td>Risk alerts and suspicious activities</td><td>May 20, 2026</td><td><button class="btn btn-sm btn-primary" onclick="showToast('Downloading...')">⬇ Download</button></td></tr>
            <tr><td><strong>Reconciliation Report</strong></td><td>Bank reconciliation status and discrepancies</td><td>May 20, 2026</td><td><button class="btn btn-sm btn-primary" onclick="showToast('Downloading...')">⬇ Download</button></td></tr>
          </tbody>
        </table>
      </div></div>
    </div>

    <!-- AUDIT LOGS -->
    <div class="page" id="page-audit-logs">
      <div class="page-header"><div><div class="page-title">Audit Logs</div><div class="page-subtitle">Track all wallet activities and changes</div></div><button class="btn btn-primary" onclick="showToast('Audit log exported')">📥 Export Logs</button></div>
      <div class="filter-bar"><input type="text" placeholder="Search logs..." oninput="filterTable('auditTable',this.value)"><select><option>All Actions</option><option>Transaction</option><option>Payout</option><option>Refund</option><option>Settings Change</option><option>Login</option></select><select><option>All Users</option><option>Grace Deh</option><option>System</option></select><input type="date" value="2026-05-24"><button class="btn btn-secondary btn-sm">Filter</button></div>
      <div class="card"><div class="card-body p0">
        <table id="auditTable">
          <thead><tr><th>Timestamp</th><th>User</th><th>Action</th><th>Resource</th><th>Details</th><th>IP Address</th></tr></thead>
          <tbody>
            <tr><td>May 24, 10:42 AM</td><td><strong>Grace Deh</strong></td><td><span class="status-badge status-success">Approve</span></td><td>Refund TRF-260524-00010</td><td>Approved refund for Emeka Okafor</td><td>197.210.xx.xx</td></tr>
            <tr><td>May 24, 10:38 AM</td><td><strong>System</strong></td><td><span class="status-badge status-info">Auto</span></td><td>Transaction TRX-260524-00078</td><td>Auto-credited marketplace payment</td><td>—</td></tr>
            <tr><td>May 24, 10:24 AM</td><td><strong>Grace Deh</strong></td><td><span class="status-badge status-success">Payout</span></td><td>PO-260524-00043</td><td>Processed payout to AgroFuture</td><td>197.210.xx.xx</td></tr>
            <tr><td>May 24, 09:56 AM</td><td><strong>System</strong></td><td><span class="status-badge status-warn">Alert</span></td><td>Fraud Detection</td><td>High risk transaction flagged</td><td>—</td></tr>
            <tr><td>May 24, 09:30 AM</td><td><strong>System</strong></td><td><span class="status-badge status-success">Reconcile</span></td><td>Daily Reconciliation</td><td>4,756 transactions matched</td><td>—</td></tr>
            <tr><td>May 24, 09:15 AM</td><td><strong>Grace Deh</strong></td><td><span class="status-badge status-info">Login</span></td><td>Admin Session</td><td>Super Admin login successful</td><td>197.210.xx.xx</td></tr>
          </tbody>
        </table>
      </div></div>
    </div>

    <!-- EXPORT STATEMENT -->
    <div class="page" id="page-export-statement">
      <div class="page-header"><div><div class="page-title">Export Statement</div><div class="page-subtitle">Download wallet and transaction data</div></div></div>
      <div class="grid-3">
        <div class="card" style="cursor:pointer" onclick="showToast('Transaction statement exporting...')"><div class="card-body" style="text-align:center;padding:30px"><div style="font-size:40px;margin-bottom:12px">📊</div><div style="font-weight:700;font-size:15px">Transaction Statement</div><div style="font-size:12px;color:var(--text2);margin:6px 0">Complete transaction history</div><button class="btn btn-sm btn-primary" style="margin-top:10px">Export CSV</button></div></div>
        <div class="card" style="cursor:pointer" onclick="showToast('Settlement statement exporting...')"><div class="card-body" style="text-align:center;padding:30px"><div style="font-size:40px;margin-bottom:12px">💰</div><div style="font-weight:700;font-size:15px">Settlement Report</div><div style="font-size:12px;color:var(--text2);margin:6px 0">All settlements breakdown</div><button class="btn btn-sm btn-primary" style="margin-top:10px">Export CSV</button></div></div>
        <div class="card" style="cursor:pointer" onclick="showToast('Refund report exporting...')"><div class="card-body" style="text-align:center;padding:30px"><div style="font-size:40px;margin-bottom:12px">↩️</div><div style="font-weight:700;font-size:15px">Refund Report</div><div style="font-size:12px;color:var(--text2);margin:6px 0">Refund requests & processing</div><button class="btn btn-sm btn-primary" style="margin-top:10px">Export CSV</button></div></div>
        <div class="card" style="cursor:pointer" onclick="showToast('Payout report exporting...')"><div class="card-body" style="text-align:center;padding:30px"><div style="font-size:40px;margin-bottom:12px">💵</div><div style="font-weight:700;font-size:15px">Payout Report</div><div style="font-size:12px;color:var(--text2);margin:6px 0">Seller payouts & disbursements</div><button class="btn btn-sm btn-primary" style="margin-top:10px">Export CSV</button></div></div>
        <div class="card" style="cursor:pointer" onclick="showToast('Financial summary exporting...')"><div class="card-body" style="text-align:center;padding:30px"><div style="font-size:40px;margin-bottom:12px">📈</div><div style="font-weight:700;font-size:15px">Financial Summary</div><div style="font-size:12px;color:var(--text2);margin:6px 0">Revenue, fees, net position</div><button class="btn btn-sm btn-primary" style="margin-top:10px">Export CSV</button></div></div>
        <div class="card" style="cursor:pointer" onclick="showToast('Audit log exporting...')"><div class="card-body" style="text-align:center;padding:30px"><div style="font-size:40px;margin-bottom:12px">🔒</div><div style="font-weight:700;font-size:15px">Audit Logs</div><div style="font-size:12px;color:var(--text2);margin:6px 0">Full system activity logs</div><button class="btn btn-sm btn-primary" style="margin-top:10px">Export CSV</button></div></div>
      </div>
      <div class="card"><div class="card-header"><div class="card-title">Custom Export</div></div><div class="card-body"><div class="form-row"><div class="form-group"><label class="form-label">Data Type</label><select class="form-select"><option>Transactions</option><option>Settlements</option><option>Refunds</option><option>Payouts</option><option>Fees</option></select></div><div class="form-group"><label class="form-label">Format</label><select class="form-select"><option>CSV</option><option>Excel (XLSX)</option><option>PDF</option><option>JSON</option></select></div></div><div class="form-row"><div class="form-group"><label class="form-label">Date From</label><input class="form-input" type="date"></div><div class="form-group"><label class="form-label">Date To</label><input class="form-input" type="date"></div></div><div class="form-group"><label class="form-label">Filters</label><select class="form-select" multiple style="min-height:80px"><option>All Categories</option><option>Marketplace</option><option>Academy</option><option>Payouts</option><option>Refunds</option><option>Funding</option></select></div><button class="btn btn-primary" onclick="showToast('Custom export generated')">Generate Export</button></div></div>
    </div>

    <!-- SETTINGS -->
    <div class="page" id="page-settings">
      <div class="page-header"><div><div class="page-title">Settings</div><div class="page-subtitle">Configure wallet workspace</div></div></div>
      <div class="tabs"><div class="tab active">General</div><div class="tab">Security</div><div class="tab">Limits</div><div class="tab">Webhooks</div></div>
      <div class="card"><div class="card-body"><div class="form-row"><div class="form-group"><label class="form-label">Platform Name</label><input class="form-input" value="NATCODEV Wallet"></div><div class="form-group"><label class="form-label">Support Email</label><input class="form-input" value="wallet@natcodev.org"></div></div><div class="form-row"><div class="form-group"><label class="form-label">Default Currency</label><select class="form-select"><option>Nigerian Naira (NGN)</option><option>USD</option></select></div><div class="form-group"><label class="form-label">Timezone</label><select class="form-select"><option>Africa/Lagos (WAT)</option></select></div></div><div class="form-group"><label class="form-label">Auto-Reconciliation</label><select class="form-select"><option>Daily at 11:45 PM</option><option>Twice daily</option><option>Manual only</option></select></div><div style="display:flex;gap:10px"><button class="btn btn-primary" onclick="showToast('Settings saved')">Save Changes</button><button class="btn btn-secondary">Cancel</button></div></div></div>
    </div>

    <!-- PAYMENT GATEWAYS -->
    <div class="page" id="page-payment-gateways">
      <div class="page-header"><div><div class="page-title">Payment Gateways</div><div class="page-subtitle">Manage payment processing integrations</div></div><button class="btn btn-primary" onclick="openModal('gatewayModal')">+ Add Gateway</button></div>
      <div class="grid-3">
        <div class="card"><div class="card-body"><div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:12px"><div><div style="font-weight:700;font-size:15px">Monnify</div><div style="font-size:12px;color:var(--text2);margin-top:2px">Primary gateway</div></div><span class="status-badge status-active">Active</span></div><div style="font-size:12px;color:var(--text2);margin-bottom:10px">Cards, Bank Transfer, USSD</div><div style="display:flex;justify-content:space-between;font-size:12px;padding:8px 0;border-top:1px solid var(--border)"><span>Fee</span><strong>0.5%</strong></div><div style="display:flex;justify-content:space-between;font-size:12px;padding:8px 0;border-top:1px solid var(--border)"><span>Volume (7D)</span><strong>₦10.6M</strong></div><button class="btn btn-sm btn-secondary" style="width:100%;margin-top:10px">Configure</button></div></div>
        <div class="card"><div class="card-body"><div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:12px"><div><div style="font-weight:700;font-size:15px">Paystack</div><div style="font-size:12px;color:var(--text2);margin-top:2px">Secondary gateway</div></div><span class="status-badge status-active">Active</span></div><div style="font-size:12px;color:var(--text2);margin-bottom:10px">Cards, Bank, Mobile Money</div><div style="display:flex;justify-content:space-between;font-size:12px;padding:8px 0;border-top:1px solid var(--border)"><span>Fee</span><strong>1.5%</strong></div><div style="display:flex;justify-content:space-between;font-size:12px;padding:8px 0;border-top:1px solid var(--border)"><span>Volume (7D)</span><strong>₦2.4M</strong></div><button class="btn btn-sm btn-secondary" style="width:100%;margin-top:10px">Configure</button></div></div>
        <div class="card"><div class="card-body"><div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:12px"><div><div style="font-weight:700;font-size:15px">Flutterwave</div><div style="font-size:12px;color:var(--text2);margin-top:2px">Backup gateway</div></div><span class="status-badge status-draft">Inactive</span></div><div style="font-size:12px;color:var(--text2);margin-bottom:10px">Cards, Bank, USSD</div><div style="display:flex;justify-content:space-between;font-size:12px;padding:8px 0;border-top:1px solid var(--border)"><span>Fee</span><strong>1.4%</strong></div><div style="display:flex;justify-content:space-between;font-size:12px;padding:8px 0;border-top:1px solid var(--border)"><span>Volume (7D)</span><strong>0</strong></div><button class="btn btn-sm btn-primary" style="width:100%;margin-top:10px">Activate</button></div></div>
      </div>
    </div>

    <!-- FEES & CHARGES -->
    <div class="page" id="page-fees-charges">
      <div class="page-header"><div><div class="page-title">Fees & Charges</div><div class="page-subtitle">Configure platform fees and charges</div></div><button class="btn btn-primary" onclick="showToast('Fee structure saved')">💾 Save Changes</button></div>
      <div class="card"><div class="card-body"><div style="font-weight:700;font-size:15px;margin-bottom:14px">Transaction Fees</div>
        <div class="form-row"><div class="form-group"><label class="form-label">Monnify Fee (%)</label><input class="form-input" type="number" value="0.5" step="0.1"></div><div class="form-group"><label class="form-label">Monnify Min Fee (₦)</label><input class="form-input" type="number" value="50"></div></div>
        <div class="form-row"><div class="form-group"><label class="form-label">Monnify Max Fee ()</label><input class="form-input" type="number" value="2000"></div><div class="form-group"><label class="form-label">Card Payment Fee (%)</label><input class="form-input" type="number" value="1.5" step="0.1"></div></div>
        <div class="form-row"><div class="form-group"><label class="form-label">Bank Transfer Fee (%)</label><input class="form-input" type="number" value="0"></div><div class="form-group"><label class="form-label">Payout Fee (%)</label><input class="form-input" type="number" value="1.0" step="0.1"></div></div>
        <div style="font-weight:700;font-size:15px;margin:20px 0 14px">Platform Commission</div>
        <div class="form-row"><div class="form-group"><label class="form-label">Marketplace Commission (%)</label><input class="form-input" type="number" value="10"></div><div class="form-group"><label class="form-label">Academy Commission (%)</label><input class="form-input" type="number" value="15"></div></div>
        <div class="form-row"><div class="form-group"><label class="form-label">Refund Processing Fee</label><select class="form-select"><option>Free</option><option>1% of refund amount</option><option>Fixed 500</option></select></div><div class="form-group"><label class="form-label">Settlement Fee</label><input class="form-input" type="number" value="100"></div></div>
      </div></div>
    </div>

    <!-- TAX & COMPLIANCE -->
    <div class="page" id="page-tax-compliance">
      <div class="page-header"><div><div class="page-title">Tax & Compliance</div><div class="page-subtitle">Manage tax obligations and regulatory compliance</div></div></div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">VAT Collected (MTD)</div><div class="stat-card-value">₦1,245,600</div></div>
        <div class="stat-card"><div class="stat-card-label">Withholding Tax</div><div class="stat-card-value">₦284,500</div></div>
        <div class="stat-card"><div class="stat-card-label">Next Filing Date</div><div class="stat-card-value" style="font-size:16px">Jun 15, 2026</div></div>
        <div class="stat-card"><div class="stat-card-label">Compliance Status</div><div class="stat-card-value" style="color:var(--success);font-size:16px">✓ Compliant</div></div>
      </div>
      <div class="card"><div class="card-header"><div class="card-title">Tax Configuration</div></div><div class="card-body"><div class="form-row"><div class="form-group"><label class="form-label">VAT Rate (%)</label><input class="form-input" type="number" value="7.5"></div><div class="form-group"><label class="form-label">Withholding Tax Rate (%)</label><input class="form-input" type="number" value="5"></div></div><div class="form-row"><div class="form-label" style="grid-column:span 2">Tax Identification Number (TIN)</label><input class="form-input" value="12345678-0001" style="grid-column:span 2"></div></div><div style="margin-top:14px"><button class="btn btn-primary" onclick="showToast('Tax settings saved')">Save Configuration</button></div></div></div>
      <div class="card"><div class="card-header"><div class="card-title">Compliance Documents</div></div><div class="card-body p0">
        <table>
          <thead><tr><th>Document</th><th>Type</th><th>Due Date</th><th>Amount</th><th>Status</th><th>Managed By</th></tr></thead>
          <tbody>
            <tr><td><strong>CAC Certificate</strong></td><td>Business Registration</td><td>Dec 31, 2027</td><td><span class="status-badge status-active">Valid</span></td><td><button class="btn-icon">👁</button></td></tr>
            <tr><td><strong>Tax Clearance</strong></td><td>FIRS</td><td>Dec 31, 2026</td><td><span class="status-badge status-active">Valid</span></td><td><button class="btn-icon">👁</button></td></tr>
            <tr><td><strong>SCUML Certificate</strong></td><td>AML Compliance</td><td>Jun 30, 2026</td><td><span class="status-badge status-expiring">Expiring</span></td><td><button class="btn btn-sm btn-warn" onclick="showToast('Renewal reminder sent')">Renew</button></td></tr>
            <tr><td><strong>CBN License</strong></td><td>PSP License</td><td>Mar 31, 2028</td><td><span class="status-badge status-active">Valid</span></td><td><button class="btn-icon">👁</button></td></tr>
          </tbody>
        </table>
      </div></div>
    </div>

    <!-- USER WALLETS -->
    <div class="page" id="page-user-wallets">
      <div class="page-header"><div><div class="page-title">User Wallets</div><div class="page-subtitle">Manage individual user wallets</div></div><button class="btn btn-primary" onclick="openModal('userWalletModal')">+ Create Wallet</button></div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">Total Wallets</div><div class="stat-card-value">1,847</div></div>
        <div class="stat-card"><div class="stat-card-label">Active Wallets</div><div class="stat-card-value">1,624</div></div>
        <div class="stat-card"><div class="stat-card-label">Total Balance</div><div class="stat-card-value">₦24,977,388</div></div>
        <div class="stat-card"><div class="stat-card-label">Frozen Wallets</div><div class="stat-card-value" style="color:var(--danger)">12</div></div>
      </div>
      <div class="filter-bar"><input type="text" placeholder="Search by user, wallet ID..." oninput="filterTable('userWalletsTable',this.value)"><select><option>All Types</option><option>Seller</option><option>Learner</option><option>Admin</option></select><select><option>All Status</option><option>Active</option><option>Frozen</option><option>Closed</option></select></div>
      <div class="card"><div class="card-body p0">
        <table id="userWalletsTable">
          <thead><tr><th>Wallet ID</th><th>User</th><th>Type</th><th>Balance</th><th>On Hold</th><th>Last Activity</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
            <tr><td><strong>WL-2026-00001</strong></td><td><div class="avatar-row"><div class="avatar-sm">GD</div>Grace Deh</div></td><td>Admin</td><td>₦24,977,388</td><td>₦3,334,178</td><td>May 24, 2026</td><td><span class="status-badge status-active">Active</span></td><td><button class="btn-icon">👁</button></td></tr>
            <tr><td><strong>WL-2026-00045</strong></td><td><div class="avatar-row"><div class="avatar-sm">GF</div>Green Farms Ltd</div></td><td>Seller</td><td>₦245,000</td><td>₦0</td><td>May 24, 2026</td><td><span class="status-badge status-active">Active</span></td><td><button class="btn-icon">👁</button></td></tr>
            <tr><td><strong>WL-2026-00044</strong></td><td><div class="avatar-row"><div class="avatar-sm">PA</div>Palmbest Agro</div></td><td>Seller</td><td>₦187,500</td><td>₦0</td><td>May 24, 2026</td><td><span class="status-badge status-active">Active</span></td><td><button class="btn-icon">👁</button></td></tr>
            <tr><td><strong>WL-2026-00043</strong></td><td><div class="avatar-row"><div class="avatar-sm">JO</div>John Okafor</div></td><td>Learner</td><td>₦15,500</td><td>0</td><td>May 23, 2026</td><td><span class="status-badge status-active">Active</span></td><td><button class="btn-icon">👁</button></td></tr>
            <tr><td><strong>WL-2026-00042</strong></td><td><div class="avatar-row"><div class="avatar-sm">SK</div>Sarah Koffi</div></td><td>Learner</td><td>0</td><td>₦0</td><td>Apr 15, 2026</td><td><span class="status-badge status-cancelled">Frozen</span></td><td><button class="btn btn-sm btn-warn" onclick="showToast('Wallet unfrozen')">Unfreeze</button></td></tr>
          </tbody>
        </table>
      </div></div>
    </div>

    <!-- NOTIFICATIONS SETTINGS -->
    <div class="page" id="page-notifications-settings">
      <div class="page-header"><div><div class="page-title">Notification Settings</div><div class="page-subtitle">Configure wallet notifications</div></div></div>
      <div class="card"><div class="card-body"><div style="display:flex;flex-direction:column;gap:14px">
        <div style="display:flex;justify-content:space-between;align-items:center;padding:14px;background:var(--bg);border-radius:10px"><div><div style="font-weight:600">Transaction Alerts</div><div style="font-size:12px;color:var(--text2)">Notify on every transaction</div></div><label style="position:relative;width:44px;height:24px;cursor:pointer"><input type="checkbox" checked style="opacity:0;width:0"><span style="position:absolute;top:0;left:0;right:0;bottom:0;background:var(--g500);border-radius:24px"></span></label></div>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:14px;background:var(--bg);border-radius:10px"><div><div style="font-weight:600">Fraud Alerts</div><div style="font-size:12px;color:var(--text2)">Immediate notification on suspicious activity</div></div><label style="position:relative;width:44px;height:24px;cursor:pointer"><input type="checkbox" checked style="opacity:0;width:0"><span style="position:absolute;top:0;left:0;right:0;bottom:0;background:var(--g500);border-radius:24px"></span></label></div>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:14px;background:var(--bg);border-radius:10px"><div><div style="font-weight:600">Settlement Notifications</div><div style="font-size:12px;color:var(--text2)">Alert on scheduled settlements</div></div><label style="position:relative;width:44px;height:24px;cursor:pointer"><input type="checkbox" checked style="opacity:0;width:0"><span style="position:absolute;top:0;left:0;right:0;bottom:0;background:var(--g500);border-radius:24px"></span></label></div>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:14px;background:var(--bg);border-radius:10px"><div><div style="font-weight:600">Failed Payment Alerts</div><div style="font-size:12px;color:var(--text2)">Notify on payment failures</div></div><label style="position:relative;width:44px;height:24px;cursor:pointer"><input type="checkbox" checked style="opacity:0;width:0"><span style="position:absolute;top:0;left:0;right:0;bottom:0;background:var(--g500);border-radius:24px"></span></label></div>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:14px;background:var(--bg);border-radius:10px"><div><div style="font-weight:600">Daily Summary</div><div style="font-size:12px;color:var(--text2)">End-of-day wallet summary</div></div><label style="position:relative;width:44px;height:24px;cursor:pointer"><input type="checkbox" style="opacity:0;width:0"><span style="position:absolute;top:0;left:0;right:0;bottom:0;background:#ccc;border-radius:24px"></span></label></div>
      </div><button class="btn btn-primary" style="margin-top:16px" onclick="showToast('Notification settings saved')">Save Settings</button></div></div>
    </div>

  </div>
</div>

<!-- MODALS -->
<div class="modal-overlay" id="fundModal"><div class="modal"><div class="modal-header"><div class="modal-title">Fund Wallet</div><button class="btn-icon" onclick="closeModal('fundModal')">✕</button></div><div class="modal-body"><div class="form-group"><label class="form-label">Funding Method</label><select class="form-select"><option>Monnify Payment (Recommended)</option><option>Direct Bank Transfer</option><option>Card Payment</option></select></div><div class="form-group"><label class="form-label">Amount (₦)</label><input class="form-input" type="number" placeholder="e.g. 100000"></div><div class="form-group"><label class="form-label">Reference (Optional)</label><input class="form-input" placeholder="e.g. Monthly funding"></div><div class="form-group"><label class="form-label">Notes</label><textarea class="form-textarea" placeholder="Optional notes..."></textarea></div><div style="padding:12px;background:var(--g50);border-radius:8px;font-size:12px"><strong>Fee:</strong> 0.5% (min ₦50, max ₦2,000)<br><strong>Estimated Net:</strong> <span id="fundNet">₦0.00</span></div></div><div class="modal-footer"><button class="btn btn-secondary" onclick="closeModal('fundModal')">Cancel</button><button class="btn btn-primary" onclick="closeModal('fundModal');showToast('Wallet funded successfully')">Fund Wallet</button></div></div></div>

<div class="modal-overlay" id="bulkFundModal"><div class="modal"><div class="modal-header"><div class="modal-title">Bulk Wallet Funding</div><button class="btn-icon" onclick="closeModal('bulkFundModal')">✕</button></div><div class="modal-body"><div class="upload-zone" style="padding:30px" onclick="showToast('CSV upload opened')">📤 Drop CSV file here or click to upload<br><small>Format: WalletID, Amount, Reference</small></div><div class="form-group" style="margin-top:14px"><label class="form-label">Download Template</label><button class="btn btn-secondary btn-sm" style="width:100%" onclick="showToast('Template downloaded')">⬇ Download CSV Template</button></div></div><div class="modal-footer"><button class="btn btn-secondary" onclick="closeModal('bulkFundModal')">Cancel</button><button class="btn btn-primary" onclick="closeModal('bulkFundModal');showToast('Bulk funding initiated')">Process Bulk Funding</button></div></div></div>

<div class="modal-overlay" id="refundModal"><div class="modal"><div class="modal-header"><div class="modal-title">Process Refund</div><button class="btn-icon" onclick="closeModal('refundModal')">✕</button></div><div class="modal-body"><div class="form-group"><label class="form-label">Original Transaction ID</label><input class="form-input" placeholder="TRX-..."></div><div class="form-group"><label class="form-label">Requester</label><select class="form-select"><option>Chinedu Uzor</option><option>Aisha Musa</option><option>Tunde Adewale</option><option>Ifeoma Nwosu</option><option>Usman Lawal</option></select></div><div class="form-row"><div class="form-group"><label class="form-label">Refund Amount (₦)</label><input class="form-input" type="number"></div><div class="form-group"><label class="form-label">Reason</label><select class="form-select"><option>Order not received</option><option>Duplicate payment</option><option>Cancelled order</option><option>Wrong item delivered</option><option>Service not rendered</option><option>Product defective</option></select></div></div><div class="form-group"><label class="form-label">Notes</label><textarea class="form-textarea" placeholder="Refund notes..."></textarea></div></div><div class="modal-footer"><button class="btn btn-secondary" onclick="closeModal('refundModal')">Cancel</button><button class="btn btn-primary" onclick="closeModal('refundModal');showToast('Refund processed')">Process Refund</button></div></div></div>

<div class="modal-overlay" id="bankModal"><div class="modal"><div class="modal-header"><div class="modal-title">Add Bank Account</div><button class="btn-icon" onclick="closeModal('bankModal')">✕</button></div><div class="modal-body"><div class="form-row"><div class="form-group"><label class="form-label">Bank Name</label><select class="form-select"><option>GTBank</option><option>First Bank</option><option>Access Bank</option><option>UBA</option><option>Zenith Bank</option></select></div><div class="form-group"><label class="form-label">Account Type</label><select class="form-select"><option>Primary</option><option>Payout</option><option>Reserve</option><option>Academy</option></select></div></div><div class="form-row"><div class="form-group"><label class="form-label">Account Name</label><input class="form-input"></div><div class="form-group"><label class="form-label">Account Number</label><input class="form-input"></div></div><div class="form-group"><label class="form-label">BVN (for verification)</label><input class="form-input"></div></div><div class="modal-footer"><button class="btn btn-secondary" onclick="closeModal('bankModal')">Cancel</button><button class="btn btn-primary" onclick="closeModal('bankModal');showToast('Bank account added')">Add Account</button></div></div></div>

<div class="modal-overlay" id="gatewayModal"><div class="modal"><div class="modal-header"><div class="modal-title">Add Payment Gateway</div><button class="btn-icon" onclick="closeModal('gatewayModal')">✕</button></div><div class="modal-body"><div class="form-group"><label class="form-label">Gateway Provider</label><select class="form-select"><option>Monnify</option><option>Paystack</option><option>Flutterwave</option><option>Interswitch</option></select></div><div class="form-group"><label class="form-label">API Key</label><input class="form-input" type="password"></div><div class="form-group"><label class="form-label">Secret Key</label><input class="form-input" type="password"></div><div class="form-row"><div class="form-group"><label class="form-label">Fee (%)</label><input class="form-input" type="number" step="0.1"></div><div class="form-group"><label class="form-label">Status</label><select class="form-select"><option>Active</option><option>Inactive</option><option>Test Mode</option></select></div></div></div><div class="modal-footer"><button class="btn btn-secondary" onclick="closeModal('gatewayModal')">Cancel</button><button class="btn btn-primary" onclick="closeModal('gatewayModal');showToast('Gateway added')">Add Gateway</button></div></div></div>

<div class="modal-overlay" id="userWalletModal"><div class="modal"><div class="modal-header"><div class="modal-title">Create User Wallet</div><button class="btn-icon" onclick="closeModal('userWalletModal')">✕</button></div><div class="modal-body"><div class="form-row"><div class="form-group"><label class="form-label">User Name</label><input class="form-input"></div><div class="form-group"><label class="form-label">Email</label><input class="form-input" type="email"></div></div><div class="form-row"><div class="form-group"><label class="form-label">Wallet Type</label><select class="form-select"><option>Seller</option><option>Learner</option><option>Admin</option><option>Agent</option></select></div><div class="form-group"><label class="form-label">Initial Balance (₦)</label><input class="form-input" type="number" value="0"></div></div><div class="form-group"><label class="form-label">Notes</label><textarea class="form-textarea"></textarea></div></div><div class="modal-footer"><button class="btn btn-secondary" onclick="closeModal('userWalletModal')">Cancel</button><button class="btn btn-primary" onclick="closeModal('userWalletModal');showToast('Wallet created')">Create Wallet</button></div></div></div>

<div class="toast" id="toast"></div>

<script>
const WALLET = <?= json_encode($walletPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
const h = value => String(value ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch]));
const fmt = value => Number(value || 0).toLocaleString();
const shortNaira = value => 'NGN ' + Number(value || 0).toLocaleString(undefined, {maximumFractionDigits:0});
const dt = value => value ? new Date(String(value).replace(' ', 'T')).toLocaleString(undefined, {month:'short', day:'numeric', hour:'2-digit', minute:'2-digit'}) : '-';
const d = value => value ? new Date(String(value).replace(' ', 'T')).toLocaleDateString(undefined, {month:'short', day:'numeric', year:'numeric'}) : '-';
const statusClass = status => {
  const s = String(status || '').toLowerCase();
  if (['completed','successful','success','paid','settled','approved','active'].includes(s)) return 'status-success';
  if (['credit','inflow','funding','deposit'].includes(s)) return 'status-credit';
  if (['debit','outflow','payment','payout','withdrawal','refund'].includes(s)) return 'status-debit';
  if (['failed','rejected','cancelled','overdue','suspended'].includes(s)) return 'status-failed';
  return 'status-pending';
};
const statusText = status => h(String(status || 'pending').replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()));
const badge = status => `<span class="status-badge ${statusClass(status)}">${statusText(status)}</span>`;
const directionLabel = tx => String(tx.direction || '').toLowerCase().includes('out') || ['debit','payment','payout','withdrawal'].includes(String(tx.type || '').toLowerCase()) ? 'Debit' : 'Credit';
const signedAmount = tx => {
  const isDebit = directionLabel(tx) === 'Debit';
  return `<span style="color:var(${isDebit ? '--danger' : '--success'});font-weight:700">${isDebit ? '-' : '+'}${shortNaira(Math.abs(Number(tx.amount || 0)))}</span>`;
};
function metric(name){ return WALLET.metrics?.[name] ?? 0; }
function setStat(label, value, sub){
  document.querySelectorAll('.stat-card-label').forEach(el => {
    if (el.textContent.trim().toLowerCase() === label.toLowerCase()) {
      const card = el.closest('.stat-card');
      const valueEl = card?.querySelector('.stat-card-value');
      if (valueEl) valueEl.textContent = value;
      const subEl = card?.querySelector('.stat-card-sub,.stat-card-change');
      if (subEl && sub !== undefined) subEl.textContent = sub;
    }
  });
}
function fillTable(selector, rows, cols){
  const body = document.querySelector(selector);
  if (!body) return;
  body.innerHTML = rows.length ? rows.join('') : `<tr><td colspan="${cols}">No records found.</td></tr>`;
}
const postButton = (action, fields, label, cls='btn btn-sm btn-primary') => {
  const inputs = Object.entries({action, page: WALLET.page, ...fields}).map(([k,v]) => `<input type="hidden" name="${h(k)}" value="${h(v)}">`).join('');
  return `<form method="post" style="display:inline">${inputs}<button class="${cls}" type="submit">${h(label)}</button></form>`;
};
function txRow(tx){
  const type = directionLabel(tx);
  return `<tr><td><strong>${h(tx.reference || 'TRX-' + tx.id)}</strong></td><td>${dt(tx.created_at)}</td><td>${badge(type)}</td><td>${h(tx.description || 'Wallet transaction')}</td><td>${h(tx.user_name || tx.user_email || 'Platform')}</td><td>${signedAmount(tx)}</td><td>${badge(tx.status)}</td></tr>`;
}
function txFullRow(tx){
  const type = directionLabel(tx);
  const ref = tx.reference || 'TRX-' + tx.id;
  const category = tx.provider || tx.type || (String(tx.description || '').split(':')[0]) || 'wallet';
  return `<tr><td><strong>${h(ref)}</strong></td><td>${dt(tx.created_at)}</td><td>${badge(type)}</td><td>${h(statusText(category))}</td><td>${h(tx.description || 'Wallet transaction')}</td><td>${h(tx.user_name || tx.user_email || 'Platform')}</td><td>${h(ref)}</td><td>${signedAmount(tx)}</td><td>${shortNaira(tx.fee || tx.charge || 0)}</td><td>${badge(tx.status)}</td><td><button class="btn btn-sm btn-secondary" type="button" onclick="showTransactionDetail('${h(ref)}')">View</button></td></tr>`;
}
function paymentRow(tx){
  const status = String(tx.status || '').toLowerCase();
  const isFailed = ['failed','rejected'].includes(status);
  const action = isFailed
    ? postButton('update_transaction_status', {transaction_id: tx.id, status:'processing'}, 'Retry', 'btn btn-sm btn-warn')
    : `<button class="btn btn-sm btn-secondary" type="button" onclick="showTransactionDetail('${h(tx.reference || 'TRX-' + tx.id)}')">View</button>`;
  return `<tr><td><strong>${h(tx.reference || 'PAY-' + tx.id)}</strong></td><td>${dt(tx.created_at)}</td><td>${h(tx.provider || 'wallet')}</td><td>${h(tx.user_name || tx.user_email || 'Platform')}</td><td>${h(tx.description || 'Wallet transaction')}</td><td>${shortNaira(tx.amount)}</td><td>${shortNaira(0)}</td><td>${shortNaira(tx.amount)}</td><td>${badge(tx.status)}</td><td>${action}</td></tr>`;
}
function refundRow(row){
  return `<tr><td><strong>REF-${String(row.id).padStart(6,'0')}</strong></td><td>${h(row.requester_name || row.requester_email || 'Learner')}</td><td>${h((row.reason || row.course_title || 'Refund request').slice(0,70))}</td><td>${shortNaira(row.amount)}</td><td>${badge(row.status)}</td><td>${row.status === 'approved' ? '-' : `${postButton('review_refund', {refund_id: row.id, status:'under_review'}, 'Review', 'btn btn-sm btn-secondary')} ${postButton('process_refund', {refund_id: row.id, user_id: row.user_id, amount: row.amount, reason: row.reason || 'Approved refund'}, 'Pay', 'btn btn-sm btn-primary')} ${postButton('review_refund', {refund_id: row.id, status:'rejected'}, 'Reject', 'btn btn-sm btn-danger')}`}</td></tr>`;
}
function payoutRow(row){
  return `<tr><td><strong>${h(row.order_ref)}</strong></td><td>${h(row.store_name || row.seller_email || 'Seller')}</td><td>${h(row.buyer_name || 'Buyer')}</td><td>${shortNaira(row.total_amount)}</td><td>${d(row.created_at)}</td><td>${badge(row.payment_status)}</td><td>${postButton('settle_order', {order_id: row.id}, 'Settle', 'btn btn-sm btn-primary')}</td></tr>`;
}
function withdrawalRow(row){
  const name = row.user_name || row.user_email || 'Stakeholder';
  const role = row.platform_role || row.user_role || 'user';
  const bank = `${row.bank_name || 'Bank'} / ${row.account_number || ''}`;
  const approve = row.status === 'pending'
    ? postButton('process_withdrawal', {withdrawal_id: row.id, decision:'approve', admin_note:`Approved ${row.provider || 'manual'} payout`}, 'Approve', 'btn btn-sm btn-primary')
    : '';
  const reject = row.status === 'pending'
    ? postButton('process_withdrawal', {withdrawal_id: row.id, decision:'reject', admin_note:'Rejected by admin'}, 'Reject', 'btn btn-sm btn-danger')
    : '';
  return `<tr><td><strong>${h(row.reference)}</strong><br><small>${dt(row.requested_at)}</small></td><td>${h(name)}<br><small>${statusText(role)}</small></td><td>${statusText(row.provider || 'manual')}<br><small>${h(row.payout_status || '')}</small></td><td>${h(bank)}<br><small>${h(row.account_name || '')}</small></td><td>${shortNaira(row.amount)}</td><td>${shortNaira(row.final_amount)}<br><small>Charge ${shortNaira(row.charge)}</small></td><td>${badge(row.status)}</td><td>${approve} ${reject}</td></tr>`;
}
function settlementRow(row){
  return `<tr><td>${d(row.settlement_date)}</td><td>${fmt(row.seller_count)}</td><td>${fmt(row.order_count)}</td><td>${shortNaira(row.amount)}</td><td>${badge('scheduled')}</td></tr>`;
}
function academyPaymentRow(row){
  return `<tr><td><strong>${h(row.payment_reference || 'REG-' + row.id)}</strong></td><td>${h(row.user_name || row.user_email || 'Learner')}</td><td>${h(row.course_title || 'Academy course')}</td><td>${shortNaira(row.price)}</td><td>${d(row.registered_at)}</td><td>${badge(row.payment_status)}</td></tr>`;
}
function walletRow(row){
  const status = String(row.status || 'active').toLowerCase();
  const nextStatus = status === 'active' ? 'frozen' : 'active';
  const nextLabel = status === 'active' ? 'Freeze' : 'Activate';
  const actions = `${postButton('update_wallet_status', {wallet_id: row.id, status: nextStatus}, nextLabel, status === 'active' ? 'btn btn-sm btn-warn' : 'btn btn-sm btn-primary')}
    ${postButton('adjust_wallet_balance', {wallet_id: row.id, direction: 'release_hold', amount: row.hold_balance || 0, notes: 'Release wallet hold'}, 'Release Hold', 'btn btn-sm btn-secondary')}`;
  return `<tr><td><strong>WL-${String(row.id).padStart(6,'0')}</strong></td><td>${h(row.user_name || row.user_email || 'User')}</td><td>${statusText(row.wallet_type || row.platform_role || row.role || 'user')}</td><td>${shortNaira(row.balance)}</td><td>${shortNaira(row.hold_balance)}</td><td>${d(row.last_activity_at || row.updated_at || row.created_at)}</td><td>${badge(status)}</td><td>${actions}</td></tr>`;
}
function bankRow(row){
  const next = String(row.status || 'active').toLowerCase() === 'active' ? 'inactive' : 'active';
  return `<tr><td><strong>${h(row.bank_name)}</strong></td><td>${h(row.account_name)}</td><td>${h(row.account_number)}</td><td>${statusText(row.account_type)}</td><td>${shortNaira(0)}</td><td>${badge(row.status)}</td><td>${postButton('update_bank_account_status', {bank_id: row.id, status: next}, statusText(next), 'btn btn-sm btn-secondary')}</td></tr>`;
}
function reconRow(row){
  return `<tr><td><strong>${h(row.run_ref)}</strong></td><td>${statusText(row.scope)}</td><td>${fmt(row.matched_count)}</td><td>${fmt(row.exception_count)} exception(s)</td><td>${badge(row.status)}</td><td>${h(row.admin_name || 'Admin')} / ${d(row.created_at)}</td></tr>`;
}
function riskRow(row){
  return `<tr><td><strong>${h(row.reference || 'No reference')}</strong></td><td>${h(row.description || 'Risk transaction')}</td><td>${shortNaira(row.amount)}</td><td>${badge(row.status)}</td><td>${dt(row.created_at)}</td></tr>`;
}
function gatewayCard(row){
  return `<div class="card"><div class="card-body"><div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:12px"><div><div style="font-weight:700;font-size:15px">${h(row.label)}</div><div style="font-size:12px;color:var(--text2);margin-top:2px">${statusText(row.provider)} / ${statusText(row.mode)}</div></div>${badge(row.status)}</div><div style="font-size:12px;color:var(--text2);margin-bottom:10px">${h(row.methods || 'Payment processing')}</div><div style="display:flex;justify-content:space-between;font-size:12px;padding:8px 0;border-top:1px solid var(--border)"><span>Fee</span><strong>${Number(row.fee_percent || 0)}%</strong></div><div style="display:flex;justify-content:space-between;font-size:12px;padding:8px 0;border-top:1px solid var(--border)"><span>Bounds</span><strong>${shortNaira(row.min_fee)} - ${shortNaira(row.max_fee)}</strong></div><form method="post" style="margin-top:10px"><input type="hidden" name="action" value="save_gateway"><input type="hidden" name="page" value="payment-gateways"><input type="hidden" name="provider" value="${h(row.provider)}"><input type="hidden" name="label" value="${h(row.label)}"><input type="hidden" name="methods" value="${h(row.methods)}"><input type="hidden" name="fee_percent" value="${h(row.fee_percent)}"><input type="hidden" name="min_fee" value="${h(row.min_fee)}"><input type="hidden" name="max_fee" value="${h(row.max_fee)}"><input type="hidden" name="mode" value="${h(row.mode)}"><input type="hidden" name="status" value="${String(row.status).toLowerCase() === 'active' ? 'inactive' : 'active'}"><button class="btn btn-sm btn-secondary" style="width:100%" type="submit">${String(row.status).toLowerCase() === 'active' ? 'Disable Gateway' : 'Enable Gateway'}</button></form></div></div>`;
}
function feeRow(row){
  return `<tr><td><strong>${h(row.label)}</strong><br><small>${h(row.rule_key)}</small></td><td>${statusText(row.applies_to)}</td><td>${Number(row.percent_rate || 0)}%</td><td>${shortNaira(row.flat_fee)}</td><td>${shortNaira(row.min_fee)} - ${shortNaira(row.max_fee)}</td><td>${badge(row.status)}</td></tr>`;
}
function taxRow(row){
  return `<tr><td><strong>${h(row.title)}</strong><br><small>${h(row.reference || row.category)}</small></td><td>${statusText(row.category)}</td><td>${d(row.due_date)}</td><td>${shortNaira(row.amount)}</td><td>${badge(row.status)}</td><td>${h(row.admin_name || 'Admin')}</td></tr>`;
}
function auditRow(row){
  return `<tr><td>${dt(row.created_at)}</td><td><strong>${h(row.actor_name || row.actor_email || 'System')}</strong></td><td>${badge(row.action)}</td><td>${h(row.resource_type)} ${h(row.resource_ref || '')}</td><td>${h(row.details || '')}</td><td>${h(row.ip_address || '-')}</td></tr>`;
}
function userOptions(selected=''){
  return WALLET.users.map(u => `<option value="${h(u.id)}" ${String(u.id) === String(selected) ? 'selected' : ''}>${h(u.name)} (${h(u.email)} / ${statusText(u.platform_role || u.role)})</option>`).join('') || '<option value="">No users available</option>';
}
function hydrateWalletForms(){
  const formStart = (action, page) => `<form method="post"><input type="hidden" name="action" value="${action}"><input type="hidden" name="page" value="${page}">`;
  const footer = label => `<div class="modal-footer"><button class="btn btn-secondary" type="button" onclick="closeModal(this.closest('.modal-overlay').id)">Cancel</button><button class="btn btn-primary" type="submit">${label}</button></div></form>`;
  const fund = document.querySelector('#fundModal .modal');
  if (fund) fund.innerHTML = `<div class="modal-header"><div class="modal-title">Fund Wallet</div><button class="btn-icon" onclick="closeModal('fundModal')" type="button">X</button></div>${formStart('fund_wallet','transactions')}<div class="modal-body"><div class="form-group"><label class="form-label">User Wallet</label><select class="form-select" name="user_id">${userOptions()}</select></div><div class="form-row"><div class="form-group"><label class="form-label">Amount (NGN)</label><input class="form-input" name="amount" type="number" step="0.01" required></div><div class="form-group"><label class="form-label">Reference</label><input class="form-input" name="reference" placeholder="Optional"></div></div><div class="form-group"><label class="form-label">Notes</label><textarea class="form-textarea" name="notes"></textarea></div></div>${footer('Fund Wallet')}`;
  const refund = document.querySelector('#refundModal .modal');
  if (refund) refund.innerHTML = `<div class="modal-header"><div class="modal-title">Process Refund</div><button class="btn-icon" onclick="closeModal('refundModal')" type="button">X</button></div>${formStart('process_refund','refunds')}<div class="modal-body"><div class="form-group"><label class="form-label">Requester</label><select class="form-select" name="user_id">${userOptions()}</select></div><div class="form-row"><div class="form-group"><label class="form-label">Refund Amount (NGN)</label><input class="form-input" name="amount" type="number" step="0.01" required></div><div class="form-group"><label class="form-label">Reason</label><input class="form-input" name="reason" placeholder="Reason"></div></div><div class="form-group"><label class="form-label">Notes</label><textarea class="form-textarea" name="notes"></textarea></div></div>${footer('Process Refund')}`;
  const bank = document.querySelector('#bankModal .modal');
  if (bank) bank.innerHTML = `<div class="modal-header"><div class="modal-title">Add Bank Account</div><button class="btn-icon" onclick="closeModal('bankModal')" type="button">X</button></div>${formStart('add_bank_account','bank-accounts')}<div class="modal-body"><div class="form-row"><div class="form-group"><label class="form-label">Bank Name</label><input class="form-input" name="bank_name" required></div><div class="form-group"><label class="form-label">Account Type</label><select class="form-select" name="account_type"><option value="primary">Primary</option><option value="payout">Payout</option><option value="reserve">Reserve</option><option value="academy">Academy</option></select></div></div><div class="form-row"><div class="form-group"><label class="form-label">Account Name</label><input class="form-input" name="account_name" required></div><div class="form-group"><label class="form-label">Account Number</label><input class="form-input" name="account_number" required></div></div><div class="form-group"><label class="form-label">BVN / Verification Reference</label><input class="form-input" name="bvn_reference"></div></div>${footer('Add Account')}`;
  const gateway = document.querySelector('#gatewayModal .modal');
  if (gateway) gateway.innerHTML = `<div class="modal-header"><div class="modal-title">Add Payment Gateway</div><button class="btn-icon" onclick="closeModal('gatewayModal')" type="button">X</button></div>${formStart('save_gateway','payment-gateways')}<div class="modal-body"><div class="form-row"><div class="form-group"><label class="form-label">Provider Key</label><input class="form-input" name="provider" placeholder="monnify" required></div><div class="form-group"><label class="form-label">Display Label</label><input class="form-input" name="label" placeholder="Monnify" required></div></div><div class="form-group"><label class="form-label">Supported Methods</label><input class="form-input" name="methods" placeholder="Cards, Transfer, USSD"></div><div class="form-row"><div class="form-group"><label class="form-label">Fee (%)</label><input class="form-input" name="fee_percent" type="number" step="0.001" value="0"></div><div class="form-group"><label class="form-label">Mode</label><select class="form-select" name="mode"><option value="live">Live</option><option value="test">Test</option></select></div></div><div class="form-row"><div class="form-group"><label class="form-label">Min Fee</label><input class="form-input" name="min_fee" type="number" step="0.01" value="0"></div><div class="form-group"><label class="form-label">Max Fee</label><input class="form-input" name="max_fee" type="number" step="0.01" value="0"></div></div><div class="form-row"><div class="form-group"><label class="form-label">Public Key</label><input class="form-input" name="public_key"></div><div class="form-group"><label class="form-label">Secret Key</label><input class="form-input" name="secret_key" type="password"></div></div><div class="form-group"><label class="form-label">Status</label><select class="form-select" name="status"><option value="active">Active</option><option value="inactive">Inactive</option><option value="test">Test Mode</option></select></div></div>${footer('Save Gateway')}`;
  const userWallet = document.querySelector('#userWalletModal .modal');
  if (userWallet) userWallet.innerHTML = `<div class="modal-header"><div class="modal-title">Create User Wallet</div><button class="btn-icon" onclick="closeModal('userWalletModal')" type="button">X</button></div>${formStart('create_user_wallet','user-wallets')}<div class="modal-body"><div class="form-group"><label class="form-label">User</label><select class="form-select" name="user_id">${userOptions()}</select></div><div class="form-group"><label class="form-label">Initial Balance (NGN)</label><input class="form-input" name="initial_balance" type="number" step="0.01" value="0"></div></div>${footer('Create Wallet')}`;
}
function submitWalletAction(action, page, fields={}){
  const form = document.createElement('form');
  form.method = 'post';
  form.style.display = 'none';
  Object.entries({action, page, ...fields}).forEach(([key, value]) => {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = key;
    input.value = value;
    form.appendChild(input);
  });
  document.body.appendChild(form);
  form.submit();
}
function csvEscape(value){
  const text = String(value ?? '');
  return /[",\r\n]/.test(text) ? `"${text.replace(/"/g, '""')}"` : text;
}
function downloadCsv(filename, headers, rows){
  const csv = [headers.map(csvEscape).join(','), ...rows.map(row => row.map(csvEscape).join(','))].join('\r\n');
  const blob = new Blob([csv], {type:'text/csv;charset=utf-8'});
  const link = document.createElement('a');
  link.href = URL.createObjectURL(blob);
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  URL.revokeObjectURL(link.href);
  link.remove();
  showToast(`${filename} downloaded`);
}
function walletExport(type='transactions'){
  if (type === 'transactions') {
    window.location.href = WALLET.txExportUrl || 'wallet.php?page=transactions&export=transactions';
    return;
  }
  const now = new Date().toISOString().slice(0,10);
  const exports = {
    transactions: {
      file: `wallet-transactions-${now}.csv`,
      headers: ['Reference','Date','Type','Description','Counterparty','Amount','Status'],
      rows: WALLET.transactions.map(tx => [tx.reference || `TRX-${tx.id}`, tx.created_at, directionLabel(tx), tx.description || '', tx.user_name || tx.user_email || '', tx.amount || 0, tx.status || ''])
    },
    settlements: {
      file: `wallet-settlements-${now}.csv`,
      headers: ['Settlement Date','Sellers','Orders','Amount','Status'],
      rows: WALLET.settlements.map(row => [row.settlement_date, row.seller_count, row.order_count, row.amount, 'scheduled'])
    },
    refunds: {
      file: `wallet-refunds-${now}.csv`,
      headers: ['Refund ID','Requester','Reason','Amount','Status'],
      rows: WALLET.refunds.map(row => [`REF-${row.id}`, row.requester_name || row.requester_email || '', row.reason || row.course_title || '', row.amount || 0, row.status || ''])
    },
    payouts: {
      file: `wallet-payouts-${now}.csv`,
      headers: ['Order Ref','Seller','Buyer','Amount','Created','Payment Status'],
      rows: WALLET.payouts.map(row => [row.order_ref || row.id, row.store_name || row.seller_email || '', row.buyer_name || '', row.total_amount || 0, row.created_at || '', row.payment_status || ''])
    },
    financial: {
      file: `wallet-financial-summary-${now}.csv`,
      headers: ['Metric','Value'],
      rows: Object.entries(WALLET.metrics || {})
    },
    audit: {
      file: `wallet-audit-log-${now}.csv`,
      headers: ['Timestamp','Actor','Action','Resource','Details','IP Address'],
      rows: WALLET.audits.map(row => [row.created_at, row.actor_name || row.actor_email || 'System', row.action, `${row.resource_type || ''} ${row.resource_ref || ''}`, row.details || '', row.ip_address || ''])
    },
    risk: {
      file: `wallet-risk-alerts-${now}.csv`,
      headers: ['Reference','Description','Amount','Status','Date'],
      rows: WALLET.risks.map(row => [row.reference || '', row.description || '', row.amount || 0, row.status || '', row.created_at || ''])
    }
  };
  const data = exports[type] || exports.transactions;
  downloadCsv(data.file, data.headers, data.rows);
}
function showTransactionDetail(reference){
  const tx = WALLET.transactions.find(row => String(row.reference || `TRX-${row.id}`) === String(reference));
  if (!tx) {
    showToast('Transaction details unavailable');
    return;
  }
  showToast(`${tx.reference || 'Transaction'}: ${statusText(tx.status)} / ${shortNaira(tx.amount)}`);
}
function enhanceWalletFlows(){
  document.querySelectorAll('#page-payments .page-header .btn-primary, #page-academy-payments .page-header .btn-primary').forEach(btn => {
    btn.onclick = () => walletExport('transactions');
  });
  document.querySelectorAll('#page-reports .page-header .btn-primary').forEach(btn => {
    btn.onclick = () => walletExport('financial');
  });
  document.querySelectorAll('#page-audit-logs .page-header .btn-primary').forEach(btn => {
    btn.onclick = () => walletExport('audit');
  });
  document.querySelectorAll('#page-fraud-alerts .page-header .btn-primary').forEach(btn => {
    btn.onclick = () => walletExport('risk');
  });
  document.querySelectorAll('#page-marketplace-payouts .page-header .btn-primary').forEach(btn => {
    btn.onclick = () => submitWalletAction('settle_all_due', 'marketplace-payouts');
  });
  document.querySelectorAll('#page-reconciliation .page-header .btn-primary').forEach(btn => {
    btn.onclick = () => submitWalletAction('reconcile_payments', 'reconciliation', {scope:'all', notes:'Manual reconciliation from wallet workspace'});
  });
  document.querySelectorAll('#page-reconciliation .card .btn-primary').forEach(btn => {
    if (btn.textContent.toLowerCase().includes('start reconciliation')) {
      btn.onclick = () => submitWalletAction('reconcile_payments', 'reconciliation', {scope:'all', notes:'Manual reconciliation from wallet workspace'});
    }
  });
  const reportButtons = Array.from(document.querySelectorAll('#page-reports tbody .btn-primary'));
  ['transactions','settlements','refunds','financial','risk','audit'].forEach((type, index) => {
    if (reportButtons[index]) reportButtons[index].onclick = () => walletExport(type);
  });
  const exportCards = Array.from(document.querySelectorAll('#page-export-statement .grid-3 .card'));
  ['transactions','settlements','refunds','payouts','financial','audit'].forEach((type, index) => {
    if (exportCards[index]) exportCards[index].onclick = () => walletExport(type);
  });
  const customExportButton = document.querySelector('#page-export-statement .card:last-child .btn-primary');
  if (customExportButton) {
    customExportButton.onclick = () => {
      const selected = document.querySelector('#page-export-statement select')?.value?.toLowerCase() || 'transactions';
      walletExport(selected === 'fees' ? 'financial' : selected);
    };
  }
  document.querySelectorAll('#page-settlements tbody .btn-primary').forEach(btn => {
    btn.onclick = () => navigateTo('marketplace-payouts');
    btn.textContent = 'View Payouts';
  });
  document.querySelectorAll('#page-reconciliation .upload-zone, #bulkFundModal .upload-zone').forEach(zone => {
    zone.onclick = () => showToast('File upload is not enabled here yet. Use the saved forms below for live wallet actions.');
  });
  document.querySelectorAll('.btn-icon').forEach(btn => {
    if (!btn.textContent.trim()) {
      btn.textContent = 'View';
      btn.classList.add('btn-sm');
      btn.onclick = () => showToast('Details are shown in the live tables after data loads.');
    }
  });
}
function hydrateWallet(){
  const refundBadge = document.querySelector('.nav-item[data-page="refunds"] .badge');
  if (refundBadge) refundBadge.textContent = fmt(WALLET.refunds.filter(r => ['pending','under_review','approved'].includes(String(r.status))).length);
  document.querySelectorAll('.wallet-balance-top strong').forEach(el => el.textContent = shortNaira(metric('platformBalance')));
  setStat('Total Wallet Balance', shortNaira(metric('platformBalance')), `${fmt(metric('walletCount'))} wallet(s), ${shortNaira(metric('walletDebitExposure'))} debit exposure`);
  setStat("Today's Inflow", shortNaira(metric('todayInflow')), `${fmt(metric('creditCount'))} credit entries`);
  setStat("Today's Outflow", shortNaira(metric('todayOutflow')), `${fmt(metric('debitCount'))} debit entries`);
  setStat('Pending Refunds', shortNaira(metric('pendingRefunds')), `${fmt(WALLET.refunds.length)} request(s)`);
  setStat('Seller Payouts (Due)', shortNaira(metric('sellerPayoutsDue')), 'Marketplace settlement');
  setStat('Pending Requests', fmt(metric('pendingWithdrawalCount')));
  setStat('Pending Amount', shortNaira(metric('pendingWithdrawalAmount')));
  setStat('Failed Payments', shortNaira(metric('failedPayments')), `${fmt(metric('failedCount'))} failed entry(s)`);
  setStat('Active Wallets', fmt(metric('activeWallets')), 'Usable stakeholder wallets');
  setStat('Frozen Wallets', fmt(metric('frozenWallets')), 'Restricted wallets');
  setStat('Total Balance', shortNaira(metric('platformBalance')), `${shortNaira(metric('heldBalance'))} on hold`);
  setStat('Reserved Accounts', fmt(metric('reservedAccounts')), 'Monnify reserved accounts');
  setStat('Successful Payments', fmt(metric('successfulPayments')), 'Completed transactions');
  setStat('VAT Collected (MTD)', shortNaira(metric('vatCollected')), 'Marketplace estimate');
  setStat('Withholding Tax', shortNaira(metric('withholdingTax')), 'Wallet outflow estimate');
  setStat('Pending Filings', fmt(metric('pendingCompliance')), 'Compliance records');
  setStat('Total Transactions', fmt(metric('totalTransactions')), `${fmt(WALLET.txMeta?.showingFrom || 0)}-${fmt(WALLET.txMeta?.showingTo || 0)} showing`);
  setStat('Total Volume', shortNaira(metric('totalTransactionVolume')));
  setStat('Successful', fmt(metric('successfulFilteredTransactions')));
  setStat('Failed', fmt(metric('failedFilteredTransactions')));

  fillTable('#page-transactions table tbody', WALLET.transactions.map(txFullRow), 11);
  fillTable('#page-credits table tbody', WALLET.transactions.filter(t => directionLabel(t) === 'Credit').map(txRow), 7);
  fillTable('#page-debits table tbody', WALLET.transactions.filter(t => directionLabel(t) === 'Debit').map(txRow), 7);
  fillTable('#paymentsTable tbody', WALLET.transactions.map(paymentRow), 10);
  fillTable('#page-refunds table tbody', WALLET.refunds.map(refundRow), 6);
  fillTable('#page-marketplace-payouts table tbody', WALLET.payouts.map(payoutRow), 7);
  fillTable('#page-withdrawals table tbody', WALLET.withdrawals.map(withdrawalRow), 8);
  fillTable('#page-settlements table tbody', WALLET.settlements.map(settlementRow), 5);
  fillTable('#page-academy-payments table tbody', WALLET.academyPayments.map(academyPaymentRow), 6);
  fillTable('#page-user-wallets table tbody', WALLET.wallets.map(walletRow), 8);
  fillTable('#page-bank-accounts table tbody', WALLET.banks.map(bankRow), 7);
  const reconHead = document.querySelector('#page-reconciliation table thead tr');
  if (reconHead) reconHead.innerHTML = '<th>Run Ref</th><th>Scope</th><th>Matched</th><th>Exceptions</th><th>Status</th><th>Created By</th>';
  fillTable('#page-reconciliation table tbody', WALLET.reconciliations.map(reconRow), 6);
  fillTable('#page-fraud-alerts table tbody', WALLET.risks.map(riskRow), 5);
  fillTable('#page-audit-logs table tbody', WALLET.audits.map(auditRow), 6);

  const gatewayGrid = document.querySelector('#page-payment-gateways .grid-3');
  if (gatewayGrid) gatewayGrid.innerHTML = WALLET.gateways.map(gatewayCard).join('') || '<div class="card"><div class="card-body">No payment gateways configured.</div></div>';
  const feesCard = document.querySelector('#page-fees-charges .card .card-body');
  if (feesCard) feesCard.innerHTML = `<form method="post" class="grid-2" style="align-items:end;margin-bottom:16px"><input type="hidden" name="action" value="save_fee_rule"><input type="hidden" name="page" value="fees-charges"><div class="form-group"><label class="form-label">Rule Key</label><input class="form-input" name="rule_key" placeholder="seller_payout" required></div><div class="form-group"><label class="form-label">Label</label><input class="form-input" name="label" placeholder="Seller Payout Charge" required></div><div class="form-group"><label class="form-label">Applies To</label><select class="form-select" name="applies_to"><option value="all">All</option><option value="funding">Funding</option><option value="payment">Payment</option><option value="payout">Payout</option><option value="refund">Refund</option></select></div><div class="form-group"><label class="form-label">Status</label><select class="form-select" name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select></div><div class="form-group"><label class="form-label">Percent Rate</label><input class="form-input" name="percent_rate" type="number" step="0.001" value="0"></div><div class="form-group"><label class="form-label">Flat Fee</label><input class="form-input" name="flat_fee" type="number" step="0.01" value="0"></div><div class="form-group"><label class="form-label">Min Fee</label><input class="form-input" name="min_fee" type="number" step="0.01" value="0"></div><div class="form-group"><label class="form-label">Max Fee</label><input class="form-input" name="max_fee" type="number" step="0.01" value="0"></div><button class="btn btn-primary" type="submit">Save Fee Rule</button></form><table><thead><tr><th>Rule</th><th>Applies To</th><th>Percent</th><th>Flat Fee</th><th>Bounds</th><th>Status</th></tr></thead><tbody>${WALLET.fees.map(feeRow).join('') || '<tr><td colspan="6">No fee rules configured.</td></tr>'}</tbody></table>`;
  const taxTable = document.querySelector('#page-tax-compliance table tbody');
  if (taxTable) taxTable.innerHTML = WALLET.taxDocs.map(taxRow).join('') || '<tr><td colspan="6">No compliance records found.</td></tr>';
  const taxPage = document.querySelector('#page-tax-compliance');
  if (taxPage && !taxPage.querySelector('.wallet-tax-form')) {
    const form = document.createElement('div');
    form.className = 'card wallet-tax-form';
    form.innerHTML = `<div class="card-body"><form method="post" class="grid-3" style="align-items:end"><input type="hidden" name="action" value="save_tax_document"><input type="hidden" name="page" value="tax-compliance"><div class="form-group"><label class="form-label">Compliance Item</label><input class="form-input" name="title" required></div><div class="form-group"><label class="form-label">Category</label><select class="form-select" name="category"><option value="tax">Tax</option><option value="aml">AML</option><option value="gateway">Gateway</option><option value="audit">Audit</option></select></div><div class="form-group"><label class="form-label">Reference</label><input class="form-input" name="reference"></div><div class="form-group"><label class="form-label">Amount</label><input class="form-input" name="amount" type="number" step="0.01" value="0"></div><div class="form-group"><label class="form-label">Due Date</label><input class="form-input" name="due_date" type="date"></div><div class="form-group"><label class="form-label">Status</label><select class="form-select" name="status"><option value="pending">Pending</option><option value="due">Due</option><option value="filed">Filed</option><option value="paid">Paid</option><option value="overdue">Overdue</option></select></div><div class="form-group" style="grid-column:1/-1"><label class="form-label">Notes</label><textarea class="form-textarea" name="notes"></textarea></div><button class="btn btn-primary" type="submit">Save Compliance Record</button></form></div>`;
    taxPage.appendChild(form);
  }
  const walletPage = document.querySelector('#page-user-wallets');
  if (walletPage && !walletPage.querySelector('.wallet-adjust-form')) {
    const adjust = document.createElement('div');
    adjust.className = 'card wallet-adjust-form';
    adjust.innerHTML = `<div class="card-body"><div style="font-weight:700;font-size:15px;margin-bottom:12px">Manual Wallet Adjustment</div><form method="post" class="grid-3" style="align-items:end"><input type="hidden" name="action" value="adjust_wallet_balance"><input type="hidden" name="page" value="user-wallets"><div class="form-group"><label class="form-label">Wallet</label><select class="form-select" name="wallet_id">${WALLET.wallets.map(w => `<option value="${h(w.id)}">WL-${String(w.id).padStart(6,'0')} - ${h(w.user_name || w.user_email || 'User')}</option>`).join('')}</select></div><div class="form-group"><label class="form-label">Direction</label><select class="form-select" name="direction"><option value="credit">Credit</option><option value="debit">Debit</option><option value="hold">Place Hold</option><option value="release_hold">Release Hold</option></select></div><div class="form-group"><label class="form-label">Amount</label><input class="form-input" name="amount" type="number" step="0.01" required></div><div class="form-group" style="grid-column:1/-1"><label class="form-label">Reason</label><textarea class="form-textarea" name="notes" required></textarea></div><button class="btn btn-primary" type="submit">Save Adjustment</button></form></div>`;
    const tableCard = walletPage.querySelector('.card');
    walletPage.insertBefore(adjust, tableCard);
  }

  const overviewTables = document.querySelectorAll('#page-overview table tbody');
  if (overviewTables[0]) overviewTables[0].innerHTML = WALLET.transactions.slice(0,5).map(txRow).join('') || '<tr><td colspan="7">No transactions yet.</td></tr>';
  if (overviewTables[1]) overviewTables[1].innerHTML = WALLET.refunds.slice(0,5).map(r => `<tr><td>REF-${h(r.id)}</td><td>${h(r.requester_name || 'Learner')}</td><td>${h((r.reason || r.course_title || 'Refund').slice(0,40))}</td><td>${shortNaira(r.amount)}</td><td>${badge(r.status)}</td></tr>`).join('') || '<tr><td colspan="5">No refund requests.</td></tr>';

  hydrateWalletForms();
  enhanceWalletFlows();
  paginateWalletTables();
  if (WALLET.notice) showToast(WALLET.notice);
  if (WALLET.error) showToast(WALLET.error);
  navigateTo(WALLET.page || 'overview');
}
function paginateWalletTables(pageSize=25){
  document.querySelectorAll('.page table').forEach(table => {
    if (table.id === 'transactionsTable') return;
    const tbody = table.querySelector('tbody');
    if (!tbody || table.dataset.paginated === '1') return;
    const rows = Array.from(tbody.querySelectorAll('tr'));
    if (rows.length <= pageSize) return;
    table.dataset.paginated = '1';
    let page = 1;
    const totalPages = Math.ceil(rows.length / pageSize);
    const nav = document.createElement('div');
    nav.style.cssText = 'display:flex;gap:8px;align-items:center;margin:12px 20px;flex-wrap:wrap';
    const render = () => {
      rows.forEach((row, index) => {
        row.style.display = index >= (page - 1) * pageSize && index < page * pageSize ? '' : 'none';
      });
      nav.innerHTML = `<button class="btn btn-sm btn-secondary" type="button" ${page === 1 ? 'disabled' : ''}>Previous</button><span class="chip">Page ${page} of ${totalPages}</span><button class="btn btn-sm btn-secondary" type="button" ${page === totalPages ? 'disabled' : ''}>Next</button>`;
      nav.querySelector('button:first-child')?.addEventListener('click', () => { page = Math.max(1, page - 1); render(); });
      nav.querySelector('button:last-child')?.addEventListener('click', () => { page = Math.min(totalPages, page + 1); render(); });
    };
    table.closest('.card-body')?.appendChild(nav);
    render();
  });
}
function navigateTo(page){
  document.querySelectorAll('.page').forEach(p=>p.classList.remove('active'));
  document.querySelectorAll('.nav-item').forEach(n=>n.classList.remove('active'));
  const el=document.getElementById('page-'+page);
  if(el)el.classList.add('active');
  const nav=document.querySelector(`.nav-item[data-page="${page}"]`);
  if(nav)nav.classList.add('active');
  window.scrollTo(0,0);
}
document.querySelectorAll('.nav-item[data-page]').forEach(item=>{
  item.addEventListener('click',()=>{const p=item.getAttribute('data-page');if(p)navigateTo(p)});
});
function openModal(id){
  const modal = document.getElementById(id);
  if (modal) modal.classList.add('active');
}
function closeModal(id){
  const modal = document.getElementById(id);
  if (modal) modal.classList.remove('active');
}
document.querySelectorAll('.modal-overlay').forEach(o=>{o.addEventListener('click',e=>{if(e.target===o)o.classList.remove('active')})});
function showToast(msg){const t=document.getElementById('toast');t.textContent=msg;t.style.display='block';setTimeout(()=>t.style.display='none',2500)}
function filterTable(tableId,q){
  const t=document.getElementById(tableId);if(!t)return;
  const rows=t.querySelectorAll('tbody tr');const s=q.toLowerCase();
  rows.forEach(r=>{r.style.display=r.textContent.toLowerCase().includes(s)?'':'none'});
}
document.querySelectorAll('.tab').forEach(tab=>{tab.addEventListener('click',function(){this.parentElement.querySelectorAll('.tab').forEach(t=>t.classList.remove('active'));this.classList.add('active')})});
const profileMenuButton = document.getElementById('profileMenuButton');
const profileMenu = document.getElementById('profileMenu');
if (profileMenuButton && profileMenu) {
  profileMenuButton.addEventListener('click', event => {
    event.stopPropagation();
    profileMenu.classList.toggle('active');
    profileMenuButton.setAttribute('aria-expanded', profileMenu.classList.contains('active') ? 'true' : 'false');
  });
  document.addEventListener('click', event => {
    if (!profileMenu.contains(event.target) && event.target !== profileMenuButton) {
      profileMenu.classList.remove('active');
      profileMenuButton.setAttribute('aria-expanded', 'false');
    }
  });
}
document.addEventListener('keydown',e=>{if((e.ctrlKey||e.metaKey)&&e.key==='k'){e.preventDefault();document.getElementById('globalSearch').focus()}});
document.getElementById('globalSearch')?.addEventListener('keydown', e => {
  if (e.key === 'Enter') {
    e.preventDefault();
    const q = e.currentTarget.value.trim();
    if (q) window.location.href = `wallet.php?page=transactions&tx_q=${encodeURIComponent(q)}&tx_per_page=${encodeURIComponent(WALLET.txMeta?.perPage || 25)}`;
  }
});

// Fund amount calculator
document.addEventListener('input',e=>{
  if(e.target.parentElement.querySelector('.form-label')?.textContent.includes('Amount')){
    const val=parseFloat(e.target.value)||0;
    const fee=Math.min(Math.max(val*0.005,50),2000);
    const netEl=document.getElementById('fundNet');
    if(netEl)netEl.textContent='₦'+(val-fee).toLocaleString('en-NG',{minimumFractionDigits:2});
  }
});
hydrateWallet();
</script>
</body>
</html>

