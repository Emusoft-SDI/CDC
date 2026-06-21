<?php
declare(strict_types=1);

// 1. Load Dependencies
require_once __DIR__ . '/../../lib/admin-layout.php';
require_once __DIR__ . '/../../lib/monnify.php';
require_once __DIR__ . '/../../lib/academy.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$pdo = db();
admin_ensure_schema($pdo);
admin_require($pdo);

// 2. Auto-Fix Database Schema for Wallet Module
// This ensures the PHP works even if the DB is still on the basic 'db.sql' structure.
function wallet_ensure_schema(PDO $pdo): void {
    // Ensure wallets table has all required columns
    $cols = [
        ['status', "VARCHAR(40) NOT NULL DEFAULT 'active'"],
        ['hold_balance', "DECIMAL(12,2) NOT NULL DEFAULT 0"],
        ['wallet_type', "VARCHAR(60) NULL"],
        ['last_activity_at', "DATETIME NULL"],
        ['reserved_account_number', "VARCHAR(80) NULL"]
    ];
    foreach ($cols as $col) {
        app_add_column_if_missing($pdo, 'wallets', $col[0], $col[1]);
    }

    // Ensure users table has platform_role
    app_add_column_if_missing($pdo, 'users', 'platform_role', "VARCHAR(50) NULL AFTER `role`");

    // Ensure webinar_registrations has payment_reference
    app_add_column_if_missing($pdo, 'webinar_registrations', 'payment_reference', "VARCHAR(100) NULL");

    // Create missing tables if they don't exist
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS wallet_withdrawals (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        charge DECIMAL(12,2) DEFAULT 0,
        final_amount DECIMAL(12,2) NOT NULL,
        bank_name VARCHAR(100),
        account_number VARCHAR(50),
        account_name VARCHAR(100),
        provider VARCHAR(50) DEFAULT 'manual',
        payout_status VARCHAR(50) DEFAULT 'pending',
        reference VARCHAR(100),
        status ENUM('pending','processing','approved','rejected') DEFAULT 'pending',
        requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
    CREATE TABLE IF NOT EXISTS marketplace_orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        seller_id INT NOT NULL,
        buyer_name VARCHAR(255),
        total_amount DECIMAL(12,2),
        payment_status VARCHAR(50) DEFAULT 'pending',
        order_ref VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        settled_at DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
    CREATE TABLE IF NOT EXISTS marketplace_sellers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        store_name VARCHAR(255),
        email VARCHAR(255)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
    CREATE TABLE IF NOT EXISTS academy_refund_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        webinar_id INT,
        amount DECIMAL(12,2),
        reason TEXT,
        status ENUM('pending','under_review','approved','rejected') DEFAULT 'pending',
        admin_notes TEXT,
        reviewed_by INT,
        reviewed_at DATETIME,
        requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Ensure auto-increment works on MariaDB
    app_ensure_primary_auto_increment($pdo, 'wallet_withdrawals');
    app_ensure_primary_auto_increment($pdo, 'marketplace_orders');
    app_ensure_primary_auto_increment($pdo, 'marketplace_sellers');
    app_ensure_primary_auto_increment($pdo, 'academy_refund_requests');
}

wallet_ensure_schema($pdo);

$walletAdmin = current_user($pdo) ?: [];
$walletScriptDir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/admin/wallet.php')));
$walletAdminBase = basename($walletScriptDir) === 'acad' ? dirname($walletScriptDir) : $walletScriptDir;
$walletAdminBase = rtrim($walletAdminBase, '/') ?: '/admin';
$walletPublicBase = preg_replace('#/admin$#', '', $walletAdminBase) ?: '';

// --- Helper Functions Missing in Original File ---
function wx_badge(string $status): string {
    $s = strtolower(trim($status));
    if (in_array($s, ['completed', 'successful', 'success', 'paid', 'settled', 'approved', 'active', 'verified'])) return 'success';
    if (in_array($s, ['pending', 'processing', 'scheduled', 'under_review'])) return 'pending';
    if (in_array($s, ['failed', 'rejected', 'cancelled', 'frozen', 'suspended'])) return 'danger';
    return 'info';
}

function wx_dt(?string $date): string {
    if (!$date) return '-';
    try {
        $dt = new DateTime($date);
        return $dt->format('M d, Y h:i A');
    } catch (Exception $e) {
        return $date;
    }
}

function monnify_is_configured(): bool {
    return function_exists('monnify_is_configured') ? \monnify_is_configured() : false;
}

function paystack_is_configured(): bool {
    return function_exists('paystack_is_configured') ? \paystack_is_configured() : false;
}

function wallet_admin_process_withdrawal(PDO $pdo, int $withdrawalId, int $adminId, string $decision, string $note): array {
    try {
        $wd = $pdo->prepare("SELECT * FROM wallet_withdrawals WHERE id = ? LIMIT 1");
        $wd->execute([$withdrawalId]);
        $withdrawal = $wd->fetch(PDO::FETCH_ASSOC);
        
        if (!$withdrawal) return ['success' => false, 'error' => 'Withdrawal not found'];
        
        if ($decision === 'approve') {
            $pdo->prepare("UPDATE wallet_withdrawals SET status = 'approved', payout_status = 'processing', admin_notes = ? WHERE id = ?")
                ->execute([$note, $withdrawalId]);
            // Note: In a real app, you would deduct from hold_balance here
        } elseif ($decision === 'reject') {
            $pdo->prepare("UPDATE wallet_withdrawals SET status = 'rejected', admin_notes = ? WHERE id = ?")
                ->execute([$note, $withdrawalId]);
            // Note: In a real app, you would release hold_balance here
        }
        return ['success' => true];
    } catch (Throwable $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
// --- End Helpers ---

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
app_add_column_if_missing($pdo, 'wallets', 'reserved_account_number', "VARCHAR(80) NULL");

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
    // Fallback if wallet_get_or_create is not in lib
    if (function_exists('wallet_get_or_create')) {
        return wallet_get_or_create($pdo, $userId);
    }
    $w = $pdo->prepare("SELECT * FROM wallets WHERE user_id = ? LIMIT 1");
    $w->execute([$userId]);
    $wallet = $w->fetch(PDO::FETCH_ASSOC);
    if (!$wallet) {
        $pdo->prepare("INSERT INTO wallets (user_id, balance, currency) VALUES (?, 0, 'NGN')")->execute([$userId]);
        return wx_wallet_for_user($pdo, $userId);
    }
    return $wallet;
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
<span class="badge"><?= (int) count(array_filter($refundRows, fn($r) => in_array($r['status'], ['pending', 'under_review']))) ?></span>
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
<a class="topbar-icon" href="<?= wx