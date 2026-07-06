<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/monnify.php';
require_once __DIR__ . '/../market/_market.php';

$pdo = market_boot();
wallet_ensure_schema($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'POST method required'], 405);
}

if (!app_check_rate_limit('monnify_webhook', 60, 60)) {
    json_response(['success' => false, 'error' => 'Too many requests'], 429);
}

$raw = file_get_contents('php://input') ?: '';
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    json_response(['success' => false, 'error' => 'Invalid JSON'], 400);
}

if (!monnify_webhook_is_valid($raw, $payload)) {
    error_log('Invalid Monnify webhook signature: ' . mb_substr($raw, 0, 600));
    json_response(['success' => false, 'error' => 'Invalid signature'], 401);
}

$eventType = strtoupper((string) ($payload['eventType'] ?? ''));
$data = $payload['eventData'] ?? $payload;
$status = strtoupper((string) ($data['paymentStatus'] ?? $data['status'] ?? ''));
$reference = (string) ($data['paymentReference'] ?? $data['product']['reference'] ?? '');
$transactionReference = (string) ($data['transactionReference'] ?? '');
$amount = (float) ($data['amountPaid'] ?? $data['amount'] ?? 0);

try {
    if ($amount <= 0 || $reference === '') {
        json_response(['success' => true, 'ignored' => true, 'reason' => 'No payable wallet reference']);
    }

    if (preg_match('/^NAT-MKT-/i', $reference)) {
        $orderStmt = $pdo->prepare("
            SELECT
                checkout_ref,
                SUM(CASE WHEN payment_status = 'paid' THEN 0 ELSE 1 END) unpaid_count,
                MAX(COALESCE(checkout_total, 0)) checkout_total,
                SUM(COALESCE(total_amount, 0)) order_total,
                MAX(COALESCE(delivery_fee, 0)) delivery_fee,
                MAX(COALESCE(service_fee, 0)) service_fee
            FROM marketplace_orders
            WHERE payment_reference = ?
            GROUP BY checkout_ref
            LIMIT 1
        ");
        $orderStmt->execute([$reference]);
        $marketOrder = $orderStmt->fetch();
        if (!$marketOrder) {
            json_response(['success' => true, 'ignored' => true, 'reason' => 'Marketplace order not found']);
        }
        if ($status !== '' && !in_array($status, ['PAID', 'SUCCESS', 'SUCCESSFUL'], true)) {
            json_response(['success' => true, 'ignored' => true, 'status' => $status]);
        }
        $expectedAmount = (float) ($marketOrder['checkout_total'] ?: ((float) $marketOrder['order_total'] + (float) $marketOrder['delivery_fee'] + (float) $marketOrder['service_fee']));
        if ($expectedAmount <= 0 || $amount + 0.01 < $expectedAmount) {
            error_log('Underpaid Monnify marketplace webhook ignored for ' . $reference . ': paid=' . $amount . ', expected=' . $expectedAmount);
            json_response(['success' => true, 'ignored' => true, 'reason' => 'Marketplace payment amount is incomplete']);
        }
        if ((int) $marketOrder['unpaid_count'] > 0) {
            $pdo->beginTransaction();
            try {
                $pdo->prepare("
                    UPDATE marketplace_orders
                    SET payment_status = 'paid', payment_method = 'monnify', status = 'paid',
                        payment_provider_reference = ?, payment_provider_payload = ?,
                        paid_at = COALESCE(paid_at, NOW()), settled_at = COALESCE(settled_at, NOW())
                    WHERE payment_reference = ?
                ")->execute([$transactionReference, json_encode($payload, JSON_UNESCAPED_SLASHES), $reference]);
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }
            market_settle_checkout_orders($pdo, (string) $marketOrder['checkout_ref']);
        }
        json_response(['success' => true, 'event' => $eventType, 'marketplace' => true, 'checkout_ref' => (string) $marketOrder['checkout_ref']]);
    }

    $userId = 0;
    if (preg_match('/^NAT-FUND-(\d+)-/i', $reference, $match) || preg_match('/^NAT-WALLET-(\d+)$/i', $reference, $match)) {
        $userId = (int) $match[1];
    }
    if ($userId <= 0) {
        $walletStmt = $pdo->prepare("SELECT user_id FROM wallets WHERE reserved_account_reference = ? LIMIT 1");
        $walletStmt->execute([$reference]);
        $userId = (int) ($walletStmt->fetchColumn() ?: 0);
    }
    if ($userId <= 0) {
        json_response(['success' => true, 'ignored' => true, 'reason' => 'Wallet owner not found']);
    }

    if ($status !== '' && !in_array($status, ['PAID', 'SUCCESS', 'SUCCESSFUL'], true)) {
        json_response(['success' => true, 'ignored' => true, 'status' => $status]);
    }

    $credit = wallet_credit_once(
        $pdo,
        $userId,
        $amount,
        $reference,
        'Wallet funding via Monnify',
        'monnify',
        $transactionReference,
        $payload
    );

    json_response(['success' => true, 'event' => $eventType, 'credit' => $credit]);
} catch (Throwable $e) {
    error_log('Monnify webhook error: ' . $e->getMessage());
    json_response(['success' => false, 'error' => 'Webhook processing failed'], 500);
}
