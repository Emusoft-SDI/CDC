<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/admin-layout.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/data.php';

$pdo = db();
wallets_auth_check($pdo);

// Initialize helpers
$page = admin_current_page();
$perPage = admin_per_page(25);
$offset = admin_pagination_offset($page, $perPage);

// Handle status messages
$message = $_GET['message'] ?? '';
$error = $_GET['error'] ?? '';

// Fetch Data
$platformBalance = wallets_db_scalar($pdo, "SELECT COALESCE(SUM(balance), 0) FROM wallets");
$todayInflow = wallets_db_scalar($pdo, "SELECT COALESCE(SUM(amount), 0) FROM wallet_transactions WHERE amount > 0 AND DATE(created_at) = CURDATE()");
$todayOutflow = wallets_db_scalar($pdo, "SELECT COALESCE(SUM(ABS(amount)), 0) FROM wallet_transactions WHERE amount < 0 AND DATE(created_at) = CURDATE()");
$pendingWithdrawalCount = (int)wallets_db_scalar($pdo, "SELECT COUNT(*) FROM wallet_withdrawals WHERE status = 'pending'");
$unsettledOrdersCount = (int)wallets_db_scalar($pdo, "SELECT COUNT(*) FROM marketplace_orders WHERE payment_status = 'paid' AND settled_at IS NULL");

// Transactions
$txSearch = trim((string)($_GET['q'] ?? ''));
$txWhereSql = $txSearch !== '' ? 'WHERE (wt.reference LIKE ? OR u.name LIKE ?)' : 'WHERE 1=1';
$txParams = $txSearch !== '' ? ["%$txSearch%", "%$txSearch%"] : [];

$totalTransactions = (int)wallets_db_scalar($pdo, "SELECT COUNT(*) FROM wallet_transactions wt LEFT JOIN wallets w ON w.id = wt.wallet_id LEFT JOIN users u ON u.id = w.user_id $txWhereSql", $txParams);
$transactions = wallets_db_query($pdo, "SELECT wt.*, u.name as user_name FROM wallet_transactions wt LEFT JOIN wallets w ON w.id = wt.wallet_id LEFT JOIN users u ON u.id = w.user_id $txWhereSql ORDER BY wt.created_at DESC LIMIT $perPage OFFSET $offset", $txParams);

// Lists
$withdrawals = wallets_db_query($pdo, "SELECT ww.*, u.name as user_name FROM wallet_withdrawals ww LEFT JOIN users u ON u.id = ww.user_id WHERE ww.status = 'pending' ORDER BY ww.requested_at DESC");
$users = wallets_db_query($pdo, "SELECT id, name FROM users WHERE account_status = 'active' LIMIT 500");

// Standard UI Template for Admin Workspace
admin_page_start('Wallet Workspace', ['active' => 'wallet.php', 'wide' => true]);
?>

<div class="stats">
    <div class="stat"><div class="metric">₦<?= number_format($platformBalance, 2) ?></div><div class="meta">Platform Balance</div></div>
    <div class="stat"><div class="metric" style="color:var(--green)">₦<?= number_format($todayInflow, 2) ?></div><div class="meta">Today's Inflow</div></div>
    <div class="stat"><div class="metric" style="color:var(--danger)">₦<?= number_format($todayOutflow, 2) ?></div><div class="meta">Today's Outflow</div></div>
    <div class="stat"><div class="metric"><?= $pendingWithdrawalCount + $unsettledOrdersCount ?></div><div class="meta">Pending Tasks</div></div>
</div>

<div class="layout">
    <div class="card">
        <h3>Recent Transactions</h3>
        <div class="toolbar">
            <form method="get" class="actions" style="width: 100%;">
                <input type="text" name="q" value="<?= e($txSearch) ?>" placeholder="Search reference or user...">
                <button type="submit" class="button secondary">Search</button>
            </form>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>User</th>
                    <th>Amount</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $tx): ?>
                <tr>
                    <td><strong><?= e($tx['reference']) ?></strong></td>
                    <td><?= e($tx['user_name'] ?: 'System') ?></td>
                    <td style="color: <?= (float)$tx['amount'] >= 0 ? 'var(--green)' : 'var(--danger)' ?>; font-weight:700">
                        <?= (float)$tx['amount'] >= 0 ? '+' : '-' ?>₦<?= number_format(abs((float)$tx['amount']), 2) ?>
                    </td>
                    <td><?= date('M j, H:i', strtotime($tx['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?= admin_pagination_controls($totalTransactions, $page, $perPage) ?>
    </div>

    <div class="card">
        <h3>Pending Withdrawals</h3>
        <div class="toolbar">
            <button type="button" class="button secondary" onclick="bulkApprove()">Bulk Approve</button>
        </div>
        <form id="bulkWithdrawalForm" action="actions.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= wallets_csrf_token() ?>">
            <input type="hidden" name="action" value="bulk_process_withdrawal">
            <table>
                <thead>
                    <tr>
                        <th><input type="checkbox" id="selectAll"></th>
                        <th>User</th>
                        <th>Amount</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($withdrawals as $wd): ?>
                    <tr>
                        <td><input type="checkbox" name="withdrawal_ids[]" value="<?= $wd['id'] ?>"></td>
                        <td><strong><?= e($wd['user_name']) ?></strong></td>
                        <td>₦<?= number_format((float)$wd['amount'], 2) ?></td>
                        <td><?= date('M j', strtotime($wd['requested_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </form>
    </div>
</div>

<!-- Simple Fund Wallet Modal -->
<div id="fundModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:100; align-items:center; justify-content:center;">
    <div class="card" style="width:400px; padding:20px;">
        <h3>Fund Wallet</h3>
        <form action="actions.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= wallets_csrf_token() ?>">
            <input type="hidden" name="action" value="fund_wallet">
            <label>User</label>
            <select name="user_id" required>
                <?php foreach ($users as $u): ?>
                <option value="<?= $u['id'] ?>"><?= e($u['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <label>Amount</label>
            <input type="number" name="amount" step="0.01" required>
            <div style="margin-top:15px;">
                <button type="submit" class="button">Fund</button>
                <button type="button" class="button secondary" onclick="document.getElementById('fundModal').style.display='none'">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.getElementById('selectAll').addEventListener('change', function() {
        document.querySelectorAll('input[name="withdrawal_ids[]"]').forEach(cb => cb.checked = this.checked);
    });
    function bulkApprove() {
        if(confirm('Approve selected withdrawals?')) {
            document.getElementById('bulkWithdrawalForm').submit();
        }
    }
</script>

<?php admin_page_end(); ?>
