<?php
declare(strict_types=1);

/**
 * Woven Finance Webhook Handler for NATCODEV
 * Handles collections and payout notifications.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/gateways/woven.php';
require_once __DIR__ . '/../lib/wallet.php';
require_once __DIR__ . '/../lib/wallet-withdrawals.php';
require_once __DIR__ . '/../market/_market.php';

$pdo = db();
wallet_ensure_schema($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'POST method required'], 405);
}

if (!app_check_rate_limit('woven_webhook', 60, 60)) {
    json_response(['success' => false, 'error' => 'Too many requests'], 429);
}

$raw = file_get_contents('php://input') ?: '';
if (!woven_webhook_is_valid($raw, getallheaders() ?: [])) {
    error_log('Invalid Woven webhook signature: ' . mb_substr($raw, 0, 400));
    json_response(['success' => false, 'error' => 'Invalid signature'], 401);
}

$payload = json_decode($raw, true);
if (!is_array($payload)) {
    json_response(['success' => false, 'error' => 'Invalid JSON'], 400);
}

$event = strtolower((string) ($payload['event'] ?? $payload['type'] ?? ''));
$data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;

// Collections / Pay-in
if (in_array($event, ['charge.success', 'collection.success', 'payment.success', 'transaction.successful', ''], true) || isset($data['amount'])) {
    $reference = trim((string) ($data['reference'] ?? $data['tx_ref'] ?? $data['payment_reference'] ?? ''));
    $amount = (float) ($data['amount'] ?? 0);
    $transactionRef = (string) ($data['id'] ?? $data['transaction_id'] ?? $reference);

    if ($reference === '' || $amount <= 0) {
        json_response(['success' => true, 'ignored' => true, 'reason' => 'Missing reference or amount']);
    }

    // Check Marketplace order
    if (preg_match('/^NAT-MKT-/i', $reference)) {
        $stmt = $pdo->prepare("SELECT checkout_ref, MAX(checkout_total) total FROM marketplace_orders WHERE payment_reference = ? GROUP BY checkout_ref LIMIT 1");
        $stmt->execute([$reference]);
        $order = $stmt->fetch();
        if ($order && $amount + 0.01 >= (float) $order['total']) {
            $pdo->prepare("UPDATE marketplace_orders SET payment_status = 'paid', payment_method = 'woven', status = 'paid', payment_provider_reference = ?, paid_at = NOW() WHERE payment_reference = ? AND payment_status != 'paid'")
                ->execute([$transactionRef, $reference]);
            market_settle_checkout_orders($pdo, (string) $order['checkout_ref']);
        }
        json_response(['success' => true, 'marketplace' => true, 'reference' => $reference]);
    }

    // Resolve User ID
    $userId = 0;
    if (preg_match('/^NAT-FUND-(\d+)-/i', $reference, $m) || preg_match('/^NAT-WALLET-(\d+)$/i', $reference, $m)) {
        $userId = (int) $m[1];
    }
    if ($userId <= 0) {
        $meta = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];
        $userId = (int) ($meta['user_id'] ?? 0);
    }

    $pendingStmt = $pdo->prepare("SELECT id, user_id, amount FROM wallet_transactions WHERE reference = ? AND status = 'pending' LIMIT 1");
    $pendingStmt->execute([$reference]);
    $pending = $pendingStmt->fetch();

    if ($pending) {
        $userId = (int) $pending['user_id'];
        $expectedAmount = (float) $pending['amount'];
        if ($amount + 0.01 < $expectedAmount) {
            error_log('Underpaid Woven webhook ignored for ' . $reference . ': paid=' . $amount . ', expected=' . $expectedAmount);
            json_response(['success' => true, 'ignored' => true, 'reason' => 'Incomplete payment amount']);
        }
        $amount = $expectedAmount;
    }

    if ($userId <= 0) {
        json_response(['success' => true, 'ignored' => true, 'reason' => 'No wallet owner linked']);
    }

    try {
        $credit = wallet_credit_once($pdo, $userId, $amount, 'Wallet funding via Woven', $reference, 'woven', $transactionRef, $payload);
        json_response(['success' => true, 'credit' => $credit]);
    } catch (Throwable $e) {
        error_log('Woven webhook credit error: ' . $e->getMessage());
        json_response(['success' => false, 'error' => 'Credit processing error'], 500);
    }
}

// Payout updates
if (str_contains($event, 'payout') || str_contains($event, 'transfer')) {
    $ref = (string) ($data['reference'] ?? '');
    if ($ref !== '') {
        $wdStmt = $pdo->prepare("SELECT id, status FROM wallet_withdrawals WHERE reference = ? LIMIT 1");
        $wdStmt->execute([$ref]);
        $wd = $wdStmt->fetch();
        if ($wd) {
            $withdrawalId = (int) $wd['id'];
            if (str_contains($event, 'success') || str_contains($event, 'completed')) {
                wallet_finalize_withdrawal_payout($pdo, $withdrawalId, [
                    'success' => true,
                    'provider' => 'woven',
                    'payout_reference' => (string) ($data['id'] ?? $ref),
                    'transfer_code' => (string) ($data['transfer_code'] ?? ''),
                    'payout_status' => 'confirmed',
                    'payload' => $payload,
                ], 'woven');
            } elseif (str_contains($event, 'fail') || str_contains($event, 'reversed')) {
                wallet_release_withdrawal_funds($pdo, $withdrawalId, 'failed', 'Woven payout failed: ' . ($data['reason'] ?? 'declined'), 0);
            }
        }
    }
    json_response(['success' => true, 'payout_event' => $event]);
}

json_response(['success' => true, 'ignored' => true, 'event' => $event]);
