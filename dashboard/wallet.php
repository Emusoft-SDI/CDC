<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/dashboard-layout.php';

session_start();
$pdo = db();
app_ensure_farmer_engagement_schema($pdo);

if (empty($_SESSION['user_id'])) {
    redirect_to('login.php');
}

$userId = (int) $_SESSION['user_id'];
$pdo->prepare("INSERT IGNORE INTO wallets (user_id) VALUES (?)")->execute([$userId]);

$stmt = $pdo->prepare("SELECT * FROM wallets WHERE user_id = ?");
$stmt->execute([$userId]);
$wallet = $stmt->fetch();

$txStmt = $pdo->prepare("SELECT * FROM wallet_transactions WHERE wallet_id = ? ORDER BY created_at DESC LIMIT 20");
$txStmt->execute([(int) $wallet['id']]);
$transactions = $txStmt->fetchAll();
?>
<?php dashboard_page_start('Wallet', ['active' => 'wallet.php', 'description' => 'View your wallet balance and transaction history.', 'wide' => true]); ?>
<section class="card">
      <h1>Wallet</h1>
      <div class="balance">NGN <?= e(number_format((float) $wallet['balance'], 2)) ?></div>
      <p>Wallet funding through live gateways requires Paystack/Flutterwave public keys and webhook credentials in production.</p>
    </section>
    <section class="card">
      <h2>Recent Transactions</h2>
      <table>
        <tr><th>Date</th><th>Description</th><th>Type</th><th>Amount</th><th>Status</th></tr>
        <?php foreach ($transactions as $tx): ?>
          <tr>
            <td><?= e(date('M j, Y', strtotime((string) $tx['created_at']))) ?></td>
            <td><?= e($tx['description']) ?></td>
            <td><?= e($tx['type']) ?></td>
            <td>NGN <?= e(number_format((float) $tx['amount'], 2)) ?></td>
            <td><?= e($tx['status']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$transactions): ?><tr><td colspan="5">No transactions yet.</td></tr><?php endif; ?>
      </table>
    </section>
  <?php dashboard_page_end(); ?>
