<?php
declare(strict_types=1);

/**
 * Fincra Webhook Handler for NATCODEV
 * Idempotently processes charge.successful and payout notifications.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/gateways/fincra.php';
require_once __DIR__ . '/../lib/wallet.php';
require_once __DIR__ . '/../lib/wallet-withdrawals.php';
require_once __DIR__ . '/../market/_market.php';

$pdo = db();
wallet_ensure_schema($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'POST method required'], 405);
}

if (!app_check_rate_limit('fincra_webhook', 60, 60)) {
    json_response(['success' => false, 'error' => 'Too many requests'], 429);
}

$raw = file_get_contents('php://input') ?: '';
if (!fincra_webhook_is_valid($raw, getallheaders() ?: [])) {
    error_log('Invalid Fincra webhook signature: ' . mb_substr($raw, 0, 400));
    json_response(['success' => false, 'error' => 'Invalid signature'], 401);
}

$payload = json_decode($raw, true);
if (!is_array($payload)) {
    json_response(['success' => false, 'error' => 'Invalid JSON'], 400);
}

$event = strtolower((string) ($payload['event'] ?? $payload['eventType'] ?? ''));
$data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;

// Handle charge/pay-in success
if (in_array($event, ['charge.successful', 'collection.successful', 'payment.successful'], true)) {
    $reference = trim((string) ($data['customerReference'] ?? $data['paymentReference'] ?? $data['reference'] ?? ''));
    $amount = (float) ($data['amountReceived'] ?? $data['amount'] ?? 0);
    $transactionRef = (string) ($data['chargeReference'] ?? $data['id'] ?? $reference);

    if ($reference === '' || $amount <= 0) {
        json_response(['success' => true, 'ignored' => true, 'reason' => 'Missing reference or amount']);
    }

    // Check Marketplace order
    if (preg_match('/^NAT-MKT-/i', $reference)) {
        $stmt = $pdo->prepare("SELECT checkout_ref, MAX(checkout_total) total FROM marketplace_orders WHERE payment_reference = ? GROUP BY checkout_ref LIMIT 1");
        $stmt->execute([$reference]);
        $order = $stmt->fetch();
        if ($order && $amount + 0.01 >= (float) $order['total']) {
            $pdo->prepare("UPDATE marketplace_orders SET payment_status = 'paid', payment_method = 'fincra', status = 'paid', payment_provider_reference = ?, paid_at = NOW() WHERE payment_reference = ? AND payment_status != 'paid'")
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
            error_log('Underpaid Fincra webhook ignored for ' . $reference . ': paid=' . $amount . ', expected=' . $expectedAmount);
            json_response(['success' => true, 'ignored' => true, 'reason' => 'Incomplete payment amount']);
        }
        $amount = $expectedAmount;
    }

    if ($userId <= 0) {
        json_response(['success' => true, 'ignored' => true, 'reason' => 'No wallet owner linked']);
    }

    try {
        $credit = wallet_credit_once($pdo, $userId, $amount, 'Wallet funding via Fincra', $reference, 'fincra', $transactionRef, $payload);
        json_response(['success' => true, 'credit' => $credit]);
    } catch (Throwable $e) {
        error_log('Fincra webhook credit error: ' . $e->getMessage());
        json_response(['success' => false, 'error' => 'Credit processing error'], 500);
    }
}

// Handle payout/disbursement updates
if (str_starts_with($event, 'payout.') || str_starts_with($event, 'disbursement.')) {
    $ref = (string) ($data['customerReference'] ?? $data['reference'] ?? '');
    if ($ref !== '') {
        $wdStmt = $pdo->prepare("SELECT id, status, amount FROM wallet_withdrawals WHERE reference = ? LIMIT 1");
        $wdStmt->execute([$ref]);
        $wd = $wdStmt->fetch();
        if ($wd) {
            $withdrawalId = (int) $wd['id'];
            if (in_array($event, ['payout.successful', 'disbursement.successful'], true)) {
                wallet_finalize_withdrawal_payout($pdo, $withdrawalId, [
                    'success' => true,
                    'provider' => 'fincra',
                    'payout_reference' => (string) ($data['id'] ?? $ref),
                    'transfer_code' => (string) ($data['id'] ?? ''),
                    'payout_status' => 'confirmed',
                    'payload' => $payload,
                ], 'fincra');
            } elseif (in_array($event, ['payout.failed', 'disbursement.failed'], true)) {
                wallet_release_withdrawal_funds($pdo, $withdrawalId, 'failed', 'Fincra payout failed: ' . ($data['reason'] ?? 'declined'), 0);
            }
        }
    }
    json_response(['success' => true, 'payout_event' => $event]);
}

json_response(['success' => true, 'ignored' => true, 'event' => $event]);
