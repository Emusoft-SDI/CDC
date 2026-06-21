<?php
declare(strict_types=1);
require_once __DIR__ . '/_provider.php';

provider_simple_page('wallet', 'Wallet', 'Provider payouts, marketplace settlements, Academy revenue, and wallet withdrawals.', function(PDO $pdo, array $user, array $provider, array $counts): void {
    echo '<div class="grid"><section class="card span-4"><h2>Wallet Balance</h2><p style="font-size:2rem;font-weight:950;color:#06451f">' . e(marketplace_money((float) $counts['wallet'])) . '</p><a class="btn" href="../dashboard/wallet.php">Open Wallet & Withdraw</a></section><section class="card span-8"><h2>Settlement & Withdrawal Rules</h2><p>Marketplace buyer payments are held securely and settled to provider/seller wallets after successful delivery and confirmation. Paid training revenue follows NATCODEV Academy approval and settlement rules. Withdrawals are requested from the shared wallet and approved by admin before Monnify, Paystack, or manual payout.</p></section></div>';
});
