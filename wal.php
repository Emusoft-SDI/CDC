<?php
/**
 * NATCODEV Wallet Admin - Self-Contained Fix
 * This file automatically updates the database schema and provides the full UI.
 */

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../lib/admin-layout.php';
require_once __DIR__ . '/../lib/monnify.php';
require_once __DIR__ . '/../lib/marketplace.php';
require_once __DIR__ . '/../lib/academy.php';

$pdo = db();
admin_ensure_schema($pdo);
wallet_ensure_schema($pdo);
marketplace_ensure_schema($pdo);
academy_ensure_schema($pdo);
admin_require($pdo);

$admin = current_user($pdo) ?: [];
// 1. DATABASE CONFIGURATION (UPDATE THESE WITH YOUR REAL CREDENTIALS)
$dbHost = 'localhost';
$dbName = 'natcodevcom_data'; // From your db.sql
$dbUser = 'natcodevcom_data';             // Update this
$dbPass = 'XC^#3)[;*xTcm&V9';                 // Update this

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}

// 2. AUTO-FIX DATABASE SCHEMA
// This ensures all tables required by the UI exist, even if they aren't in db.sql
function ensureWalletSchema($pdo) {
    // Add missing columns to existing tables
    $columns = [
        ['wallets', 'status', "VARCHAR(40) NOT NULL DEFAULT 'active'"],
        ['wallets', 'hold_balance', "DECIMAL(12,2) NOT NULL DEFAULT 0"],
        ['wallets', 'wallet_type', "VARCHAR(60) NULL"],
        ['wallets', 'last_activity_at', "DATETIME NULL"],
        ['wallets', 'reserved_account_number', "VARCHAR(80) NULL"],
        ['users', 'platform_role', "VARCHAR(50) NULL"],
        ['webinar_registrations', 'payment_reference', "VARCHAR(100) NULL"]
    ];

    foreach ($columns as $col) {
        try {
            $pdo->exec("ALTER TABLE `{$col[0]}` ADD COLUMN IF NOT EXISTS `{$col[1]}` {$col[2]}");
        } catch (Exception $e) { /* Ignore if exists */ }
    }

    // Create missing tables
    $tables = [
        "CREATE TABLE IF NOT EXISTS `wallet_withdrawals` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `amount` DECIMAL(12,2) NOT NULL,
            `charge` DECIMAL(12,2) DEFAULT 0,
            `final_amount` DECIMAL(12,2) NOT NULL,
            `bank_name` VARCHAR(100),
            `account_number` VARCHAR(50),
            `account_name` VARCHAR(100),
            `provider` VARCHAR(50) DEFAULT 'manual',
            `payout_status` VARCHAR(50) DEFAULT 'pending',
            `reference` VARCHAR(100),
            `status` ENUM('pending','processing','approved','rejected') DEFAULT 'pending',
            `requested_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS `marketplace_orders` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `seller_id` INT NOT NULL,
            `buyer_name` VARCHAR(255),
            `total_amount` DECIMAL(12,2),
            `payment_status` VARCHAR(50) DEFAULT 'pending',
            `order_ref` VARCHAR(100),
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `settled_at` DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS `marketplace_sellers` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `store_name` VARCHAR(255),
            `email` VARCHAR(255)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS `academy_refund_requests` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `webinar_id` INT,
            `amount` DECIMAL(12,2),
            `reason` TEXT,
            `status` ENUM('pending','under_review','approved','rejected') DEFAULT 'pending',
            `admin_notes` TEXT,
            `reviewed_by` INT,
            `reviewed_at` DATETIME,
            `requested_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        
        "CREATE TABLE IF NOT EXISTS `wallet_admin_bank_accounts` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `bank_name` VARCHAR(180) NOT NULL,
            `account_type` VARCHAR(60) NOT NULL DEFAULT 'primary',
            `account_name` VARCHAR(180) NOT NULL,
            `account_number` VARCHAR(80) NOT NULL,
            `bvn_reference` VARCHAR(120) NULL,
            `status` VARCHAR(40) NOT NULL DEFAULT 'active',
            `created_by` INT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS `wallet_payment_gateways` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `provider` VARCHAR(80) NOT NULL,
            `label` VARCHAR(140) NOT NULL,
            `methods` VARCHAR(220) NULL,
            `fee_percent` DECIMAL(8,3) NOT NULL DEFAULT 0,
            `min_fee` DECIMAL(12,2) NOT NULL DEFAULT 0,
            `max_fee` DECIMAL(12,2) NOT NULL DEFAULT 0,
            `status` VARCHAR(40) NOT NULL DEFAULT 'inactive',
            `mode` VARCHAR(40) NOT NULL DEFAULT 'live',
            `public_key` VARCHAR(255) NULL,
            `secret_hint` VARCHAR(120) NULL,
            `created_by` INT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uq_provider` (`provider`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];

    foreach ($tables as $sql) {
        try {
            $pdo->exec($sql);
        } catch (Exception $e) { /* Ignore if exists */ }
    }
}

ensureWalletSchema($pdo);

// 3. HELPER FUNCTIONS (Internal to this file)
function wx_e($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function wx_badge($status) {
    $s = strtolower(trim($status));
    if (in_array($s, ['completed', 'successful', 'success', 'paid', 'settled', 'approved', 'active'])) return 'success';
    if (in_array($s, ['pending', 'processing', 'scheduled'])) return 'pending';
    if (in_array($s, ['failed', 'rejected', 'cancelled'])) return 'danger';
    return 'info';
}
function wx_dt($date) {
    if (!$date) return '-';
    try { return (new DateTime($date))->format('M d, Y h:i A'); } catch (Exception $e) { return $date; }
}
function wx_scalar($pdo, $sql, $params = []) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (float) ($stmt->fetchColumn() ?: 0);
    } catch (Exception $e) { return 0.0; }
}
function wx_rows($pdo, $sql, $params = []) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Exception $e) { return []; }
}

// 4. DATA FETCHING
$platformBalance = wx_scalar($pdo, "SELECT COALESCE(SUM(balance), 0) FROM wallets");
$todayInflow = wx_scalar($pdo, "SELECT COALESCE(SUM(amount), 0) FROM wallet_transactions WHERE type='credit' AND DATE(created_at) = CURDATE()");
$todayOutflow = wx_scalar($pdo, "SELECT COALESCE(SUM(amount), 0) FROM wallet_transactions WHERE type='debit' AND DATE(created_at) = CURDATE()");
$pendingWithdrawals = wx_rows($pdo, "SELECT ww.*, u.name as user_name FROM wallet_withdrawals ww LEFT JOIN users u ON u.id = ww.user_id WHERE ww.status = 'pending' ORDER BY ww.requested_at DESC LIMIT 10");
$recentTransactions = wx_rows($pdo, "SELECT wt.*, u.name as user_name FROM wallet_transactions wt LEFT JOIN wallets w ON w.id = wt.wallet_id LEFT JOIN users u ON u.id = w.user_id ORDER BY wt.created_at DESC LIMIT 10");
$users = wx_rows($pdo, "SELECT id, name, email FROM users ORDER BY name LIMIT 100");

// Handle Simple Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'fund_wallet') {
        $userId = (int)$_POST['user_id'];
        $amount = (float)$_POST['amount'];
        if ($userId > 0 && $amount > 0) {
            // Get or Create Wallet
            $wallet = $pdo->query("SELECT * FROM wallets WHERE user_id = $userId")->fetch();
            if (!$wallet) {
                $pdo->exec("INSERT INTO wallets (user_id, balance) VALUES ($userId, 0)");
                $walletId = $pdo->lastInsertId();
            } else {
                $walletId = $wallet['id'];
            }
            
            // Update Balance
            $pdo->exec("UPDATE wallets SET balance = balance + $amount WHERE id = $walletId");
            
            // Record Transaction
            $ref = 'ADMIN-FUND-' . date('YmdHis');
            $pdo->prepare("INSERT INTO wallet_transactions (wallet_id, amount, type, description, reference, status) VALUES (?, ?, 'credit', 'Admin Funding', ?, 'completed')")
                ->execute([$walletId, $amount, $ref]);
                
            header("Location: ?msg=funded");
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>NATCODEV Wallet Admin</title>
    <style>
        body { font-family: 'Inter', sans-serif; background: #f4f6f4; color: #1a1a1a; margin: 0; display: flex; }
        .sidebar { width: 240px; background: #0a2418; color: #fff; min-height: 100vh; padding: 20px; }
        .main { flex: 1; padding: 30px; }
        .card { background: #fff; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: #fff; padding: 20px; border-radius: 12px; border: 1px solid #e5e7eb; }
        .stat-value { font-size: 24px; font-weight: 700; margin-top: 10px; }
        .btn { padding: 10px 20px; border-radius: 8px; border: none; cursor: pointer; font-weight: 600; }
        .btn-primary { background: #164a33; color: #fff; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f9fafb; font-size: 12px; text-transform: uppercase; color: #6b7280; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .form-group { margin-bottom: 15px; }
        .form-label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px; }
        .form-input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; }
    </style>
</head>
<body>

<div class="sidebar">
    <h2 style="margin-top:0">🌴 NATCODEV</h2>
    <p style="opacity:0.7; font-size:12px;">Wallet Workspace</p>
    <hr style="border-color:rgba(255,255,255,0.1)">
    <div style="margin-top:20px">
        <div style="padding:10px; background:rgba(255,255,255,0.1); border-radius:6px; margin-bottom:10px">Overview</div>
        <div style="padding:10px; opacity:0.7">Transactions</div>
        <div style="padding:10px; opacity:0.7">Withdrawals</div>
    </div>
</div>

<div class="main">
    <h1>Wallet Dashboard</h1>
    
    <?php if(isset($_GET['msg'])): ?>
        <div style="background:#dcfce7; color:#166534; padding:10px; border-radius:6px; margin-bottom:20px">
            ✅ Action completed successfully.
        </div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card">
            <div style="color:#6b7280; font-size:12px; text-transform:uppercase">Platform Balance</div>
            <div class="stat-value">₦<?= number_format($platformBalance, 2) ?></div>
        </div>
        <div class="stat-card">
            <div style="color:#6b7280; font-size:12px; text-transform:uppercase">Today's Inflow</div>
            <div class="stat-value" style="color:#166534">+₦<?= number_format($todayInflow, 2) ?></div>
        </div>
        <div class="stat-card">
            <div style="color:#6b7280; font-size:12px; text-transform:uppercase">Today's Outflow</div>
            <div class="stat-value" style="color:#dc2626">-₦<?= number_format($todayOutflow, 2) ?></div>
        </div>
    </div>

    <div style="display:grid; grid-template-columns: 2fr 1fr; gap:20px">
        <!-- Recent Transactions -->
        <div class="card">
            <h3>Recent Transactions</h3>
            <table>
                <thead>
                    <tr><th>User</th><th>Type</th><th>Amount</th><th>Date</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <?php foreach($recentTransactions as $tx): ?>
                    <tr>
                        <td><?= wx_e($tx['user_name'] ?? 'Unknown') ?></td>
                        <td><?= wx_e($tx['type']) ?></td>
                        <td style="font-weight:600">₦<?= number_format($tx['amount'], 2) ?></td>
                        <td><?= wx_dt($tx['created_at']) ?></td>
                        <td><span class="badge badge-<?= wx_badge($tx['status']) ?>"><?= wx_e($tx['status']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($recentTransactions)): ?>
                        <tr><td colspan="5" style="text-align:center; color:#999">No transactions found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Quick Fund Wallet -->
        <div class="card">
            <h3>Fund User Wallet</h3>
            <form method="POST">
                <input type="hidden" name="action" value="fund_wallet">
                <div class="form-group">
                    <label class="form-label">Select User</label>
                    <select name="user_id" class="form-input">
                        <?php foreach($users as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= wx_e($u['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Amount (NGN)</label>
                    <input type="number" name="amount" class="form-input" step="0.01" required>
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%">Fund Wallet</button>
            </form>
        </div>
    </div>

    <!-- Withdrawals Section -->
    <div class="card">
        <h3>Pending Withdrawals</h3>
        <table>
            <thead>
                <tr><th>User</th><th>Bank</th><th>Account</th><th>Amount</th><th>Status</th><th>Action</th></tr>
            </thead>
            <tbody>
                <?php foreach($pendingWithdrawals as $wd): ?>
                <tr>
                    <td><?= wx_e($wd['user_name']) ?></td>
                    <td><?= wx_e($wd['bank_name']) ?></td>
                    <td><?= wx_e($wd['account_number']) ?></td>
                    <td>₦<?= number_format($wd['amount'], 2) ?></td>
                    <td><span class="badge badge-pending">Pending</span></td>
                    <td><button class="btn btn-primary" style="padding:5px 10px; font-size:12px">Approve</button></td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($pendingWithdrawals)): ?>
                    <tr><td colspan="6" style="text-align:center; color:#999">No pending withdrawals.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>