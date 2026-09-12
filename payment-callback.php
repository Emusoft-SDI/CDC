<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

// Handle payment success from Paystack/Flutterwave
$reference = $_GET['reference'] ?? '';
$status = $_GET['status'] ?? '';

if ($status === 'success') {
    try {
        $pdo = db();

        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT t.id, w.user_id, t.amount, t.status FROM wallet_transactions t JOIN wallets w ON t.wallet_id = w.id WHERE t.reference = ? FOR UPDATE");
        $stmt->execute([$reference]);
        $tx = $stmt->fetch();

        if ($tx && (string) $tx['status'] !== 'completed') {
            $pdo->prepare("UPDATE wallet_transactions SET status = 'completed', completed_at = NOW() WHERE id = ?")->execute([(int) $tx['id']]);
            $pdo->prepare("UPDATE wallets SET balance = balance + ? WHERE user_id = ?")->execute([$tx['amount'], $tx['user_id']]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Payment callback error: ' . $e->getMessage());
        $status = 'error';
    }
}

redirect_to('dashboard/wallet.php?status=' . urlencode((string) $status));
exit;
?>
