<style>
    .sidebar { width: 240px; background: #0a2418 !important; color: #fff !important; min-height: 100vh; padding: 20px; flex-shrink: 0; }
    .sidebar h2 { font-size: 18px; margin-top: 0; color: #fff !important; }
    .sidebar a { color: #fff !important; display: block; padding: 10px; text-decoration: none; }
    .sidebar a:hover { background: rgba(255,255,255,0.1) !important; }
    .sidebar .group-title { padding: 10px; opacity: 0.7; font-weight: bold; font-size: 12px; text-transform: uppercase; margin-top: 10px; color: #fff !important; }
</style>

<!-- Sidebar Inclusion -->
<div class="sidebar">
    <h2 style="margin-top:0">💰 Wallet Workspace</h2>
    <div style="margin-top:20px">
        <a href="wal.php">Overview</a>
        
        <div class="group-title">Transactions</div>
        <a href="transactions.php" style="padding-left:20px;">All Transactions</a>
        <a href="transactions.php?type=credit" style="padding-left:20px;">Credits</a>
        <a href="transactions.php?type=debit" style="padding-left:20px;">Debits</a>
        <a href="fund_wallet.php" style="padding-left:20px;">Fund Wallet</a>
        <a href="payments.php" style="padding-left:20px;">Payments</a>
        <a href="refunds.php" style="padding-left:20px;">Refunds</a>

        <div class="group-title">Payouts</div>
        <a href="marketplace_payouts.php" style="padding-left:20px;">Marketplace Payouts</a>
        <a href="withdrawals.php" style="padding-left:20px;">Withdrawals</a>
        <a href="academy_payments.php" style="padding-left:20px;">Academy Payments</a>
        
        <div class="group-title">Operations</div>
        <a href="settlements.php" style="padding-left:20px;">Settlements</a>
        <a href="bank_accounts.php" style="padding-left:20px;">Bank Accounts</a>
        <a href="reconciliation.php" style="padding-left:20px;">Reconciliation</a>
        <a href="fraud_risk.php" style="padding-left:20px;">Fraud & Risk</a>
    </div>
</div>
