<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/data.php';

$pdo = db();
wallets_auth_check($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

$action = $_POST['action'] ?? '';
$page = $_POST['page'] ?? 'dashboard';
$message = '';
$error = '';

try {
    if (!wallets_verify_csrf($_POST['csrf_token'] ?? '')) {
        throw new Exception('Invalid CSRF token.');
    }

    wallets_require_role($pdo, ['super_admin', 'national_coordinator']);

    switch ($action) {
        case 'fund_wallet':
            $userId = (int) ($_POST['user_id'] ?? 0);
            $amount = (float) ($_POST['amount'] ?? 0);
            $description = (string) ($_POST['description'] ?? 'Admin funding');
            $reference = (string) ($_POST['reference'] ?? 'FUND-' . time());

            if ($userId <= 0 || $amount <= 0) {
                throw new Exception('Invalid user or amount for funding.');
            }

            $wallet = wallets_db_query($pdo, "SELECT * FROM wallets WHERE user_id = ?", [$userId])[0] ?? null;

            if (!$wallet) {
                wallets_db_execute($pdo, "INSERT INTO wallets (user_id, balance) VALUES (?, 0)", [$userId]);
                $wallet = wallets_db_query($pdo, "SELECT * FROM wallets WHERE user_id = ?", [$userId])[0];
            }

            $newBalance = (float)$wallet['balance'] + $amount;
            wallets_db_execute($pdo, "UPDATE wallets SET balance = ? WHERE id = ?", [$newBalance, $wallet['id']]);

            wallets_db_execute($pdo, "
                INSERT INTO wallet_transactions 
                (wallet_id, amount, type, description, reference, status, created_at) 
                VALUES (?, ?, 'credit', ?, ?, 'completed', NOW())
            ", [(int)$wallet['id'], $amount, $description, $reference]);

            $message = 'Wallet funded successfully!';
            break;

        case 'bulk_process_withdrawal':
            $ids = (array) ($_POST['withdrawal_ids'] ?? []);
            if (empty($ids)) throw new Exception('No withdrawals selected.');
            
            foreach ($ids as $id) {
                wallets_db_execute($pdo, "UPDATE wallet_withdrawals SET status = 'approved', payout_status = 'processing', reviewed_at = NOW() WHERE id = ? AND status = 'pending'", [(int)$id]);
            }
            $message = 'Bulk approval processed.';
            break;

        case 'settle_order':
            $orderId = (int) ($_POST['order_id'] ?? 0);
            if ($orderId <= 0) {
                throw new Exception('Invalid order ID for settlement.');
            }
            wallets_db_execute($pdo, "UPDATE marketplace_orders SET settled_at = NOW(), payment_status = 'settled' WHERE id = ?", [$orderId]);
            $message = 'Marketplace order settled successfully!';
            break;

        default:
            throw new Exception('Unknown action.');
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}

$query = [];
if ($message) $query['message'] = urlencode($message);
if ($error) $query['error'] = urlencode($error);

header('Location: ' . $page . '.php?' . http_build_query($query));
exit;
