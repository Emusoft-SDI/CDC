<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/monnify.php';
require_once __DIR__ . '/lib/paystack.php';

// Handle payment success from Paystack/Monnify after provider verification
$pdo = db();
$user = current_user($pdo);
if (!$user) {
    redirect_to('login.php?next=payment-callback.php');
}

$reference = $_GET['reference'] ?? '';
$status = $_GET['status'] ?? '';

try {
    $stmt = $pdo->prepare("SELECT t.*, w.user_id AS wallet_user_id FROM wallet_transactions t JOIN wallets w ON t.wallet_id = w.id WHERE t.reference = ? FOR UPDATE");
    $stmt->execute([$reference]);
    $tx = $stmt->fetch();

    if (!$tx || (int) $tx['wallet_user_id'] !== (int) $user['id']) {
        $status = 'error';
    } elseif ((string) $tx['status'] === 'completed') {
        $status = 'success';
    } else {
        switch ((string) $tx['provider']) {
            case 'monnify':
                $result = monnify_verify_wallet_funding($pdo, (int) $user['id'], $reference);
                if ((string) ($result['status'] ?? '') === 'completed') {
                    $status = 'success';
                } elseif ((string) ($result['status'] ?? '') === 'pending') {
                    $status = 'pending';
                } else {
                    $status = 'error';
                }
                break;
            case 'paystack':
                $verify = paystack_verify_payment($reference);
                if (($verify['success'] ?? false) && (string) ($verify['status'] ?? '') === 'success' && (float) $verify['amount'] >= (float) $tx['amount']) {
                    wallet_credit_once($pdo, (int) $user['id'], (float) $tx['amount'], $reference, 'Wallet funding via Paystack', 'paystack', $reference, ['payment_callback' => true]);
                    $status = 'success';
                } else {
                    $status = 'error';
                }
                break;
            default:
                $status = 'error';
        }
    }
} catch (Throwable $e) {
    error_log('Payment callback error: ' . $e->getMessage());
    $status = 'error';
}

redirect_to('dashboard/wallet.php?status=' . urlencode((string) $status));
exit;
?>
