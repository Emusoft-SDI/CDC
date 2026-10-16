<?php
declare(strict_types=1);

/**
 * Korapay Webhook Handler for NATCODEV
 * Validates HMAC SHA-256 x-korapay-signature and processes collections and payouts.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/gateways/korapay.php';
require_once __DIR__ . '/../lib/wallet.php';
require_once __DIR__ . '/../lib/wallet-withdrawals.php';
require_once __DIR__ . '/../market/_market.php';

$pdo = db();
wallet_ensure_schema($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'POST method required'], 405);
}

if (!app_check_rate_limit('korapay_webhook', 60, 60)) {
    json_response(['success' => false, 'error' => 'Too many requests'], 429);
}

$raw = file_get_contents('php://input') ?: '';
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    json_response(['success' => false, 'error' => 'Invalid JSON'], 400);
}

if (!korapay_webhook_is_valid($raw, $payload, getallheaders() ?: [])) {
    error_log('Invalid Korapay webhook signature: ' . mb_substr($raw, 0, 400));
    json_response(['success' => false, 'error' => 'Invalid signature'], 401);
}

$event = strtolower((string) ($payload['event'] ?? ''));
$data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

// Handle Collections / Pay-in
if (in_array($event, ['charge.success', 'charge.completed', 'payment.success'], true)) {
    $reference = trim((string) ($data['reference'] ?? ''));
    $amount = (float) ($data['amount'] ?? $data['amount_paid'] ?? 0);
    $transactionRef = (string) ($data['id'] ?? $data['transaction_reference'] ?? $reference);

    if ($reference === '' || $amount <= 0) {
        json_response(['success' => true, 'ignored' => true, 'reason' => 'Missing reference or amount']);
    }

    // Check Marketplace order
    if (preg_match('/^NAT-MKT-/i', $reference)) {
        $stmt = $pdo->prepare("SELECT checkout_ref, MAX(checkout_total) total FROM marketplace_orders WHERE payment_reference = ? GROUP BY checkout_ref LIMIT 1");
        $stmt->execute([$reference]);
        $order = $stmt->fetch();
        if ($order && $amount + 0.01 >= (float) $order['total']) {
            $pdo->prepare("UPDATE marketplace_orders SET payment_status = 'paid', payment_method = 'korapay', status = 'paid', payment_provider_reference = ?, paid_at = NOW() WHERE payment_reference = ? AND payment_status != 'paid'")
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
            error_log('Underpaid Korapay webhook ignored for ' . $reference . ': paid=' . $amount . ', expected=' . $expectedAmount);
            json_response(['success' => true, 'ignored' => true, 'reason' => 'Incomplete payment amount']);
        }
        $amount = $expectedAmount;
    }

    if ($userId <= 0) {
        json_response(['success' => true, 'ignored' => true, 'reason' => 'No wallet owner linked']);
    }

    try {
        $credit = wallet_credit_once($pdo, $userId, $amount, 'Wallet funding via Korapay', $reference, 'korapay', $transactionRef, $payload);
        json_response(['success' => true, 'credit' => $credit]);
    } catch (Throwable $e) {
        error_log('Korapay webhook credit error: ' . $e->getMessage());
        json_response(['success' => false, 'error' => 'Credit processing error'], 500);
    }
}

// Handle transfer / payout updates
if (str_starts_with($event, 'transfer.') || str_starts_with($event, 'payout.')) {
    $ref = (string) ($data['reference'] ?? '');
    if ($ref !== '') {
        $wdStmt = $pdo->prepare("SELECT id, status FROM wallet_withdrawals WHERE reference = ? LIMIT 1");
        $wdStmt->execute([$ref]);
        $wd = $wdStmt->fetch();
        if ($wd) {
            $withdrawalId = (int) $wd['id'];
            if (in_array($event, ['transfer.success', 'payout.success'], true)) {
                wallet_finalize_withdrawal_payout($pdo, $withdrawalId, [
                    'success' => true,
                    'provider' => 'korapay',
                    'payout_reference' => (string) ($data['id'] ?? $ref),
                    'transfer_code' => (string) ($data['transfer_code'] ?? ''),
                    'payout_status' => 'confirmed',
                    'payload' => $payload,
                ], 'korapay');
            } elseif (in_array($event, ['transfer.failed', 'payout.failed', 'transfer.reversed'], true)) {
                wallet_release_withdrawal_funds($pdo, $withdrawalId, 'failed', 'Korapay transfer failed: ' . ($data['reason'] ?? 'declined'), 0);
            }
        }
    }
    json_response(['success' => true, 'payout_event' => $event]);
}

json_response(['success' => true, 'ignored' => true, 'event' => $event]);
