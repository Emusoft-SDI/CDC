<?php
declare(strict_types=1);

require_once __DIR__ . '/wallet.php';
require_once __DIR__ . '/platform-revenue.php';

/**
 * Shared marketplace refund handler.
 *
 * One code path for every marketplace refund decision (admin console,
 * support desk, future automation): it reverses the seller settlement,
 * reverses the platform commission in the revenue ledger, returns the
 * buyer's money (wallet-funded checkouts are credited back to the
 * wallet; external payments are recorded as a provider refund the
 * operator must issue at the gateway), and marks the orders refunded.
 *
 * Idempotent: a second call for the same checkout returns the recorded
 * refund without moving any money again.
 */

function market_refund_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS marketplace_refunds (
            id INT AUTO_INCREMENT PRIMARY KEY,
            refund_ref VARCHAR(100) NOT NULL UNIQUE,
            checkout_ref VARCHAR(100) NOT NULL,
            buyer_user_id INT NULL,
            order_ids_json LONGTEXT NULL,
            refund_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
            settlement_reversal_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
            settlement_shortfall_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
            commission_reversal_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
            payment_method VARCHAR(40) NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'completed',
            reason TEXT NULL,
            admin_note TEXT NULL,
            requested_by INT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_marketplace_refunds_checkout (checkout_ref),
            INDEX idx_marketplace_refunds_buyer (buyer_user_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'marketplace_refunds');

    app_add_column_if_missing($pdo, 'marketplace_orders', 'refunded_at', "DATETIME NULL");
}

function market_refund_ref(string $checkoutRef): string
{
    return 'RFND-' . date('ymdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

/**
 * Reverse one seller settlement inside the caller's transaction.
 *
 * Debits the seller wallet by the net amount it received. If the seller
 * has already withdrawn the funds, the platform absorbs the difference
 * (buyer protection) and the shortfall is recorded on the refund row so
 * operators can follow up — the buyer is always made whole.
 *
 * @return array{debited: float, shortfall: float}
 */
function market_reverse_seller_settlement(PDO $pdo, int $sellerUserId, float $net, string $orderRef): array
{
    if ($sellerUserId <= 0 || $net <= 0) {
        return ['debited' => 0.0, 'shortfall' => 0.0];
    }

    $wallet = wallet_get_or_create($pdo, $sellerUserId);
    $lock = $pdo->prepare('SELECT * FROM wallets WHERE id = ? FOR UPDATE');
    $lock->execute([(int) $wallet['id']]);
    $wallet = $lock->fetch();
    if (!$wallet) {
        return ['debited' => 0.0, 'shortfall' => $net];
    }

    $balance = (float) $wallet['balance'];
    $debited = min($balance, $net);
    $shortfall = round($net - $debited, 2);
    $after = round($balance - $debited, 2);

    if ($debited > 0) {
        $pdo->prepare('UPDATE wallets SET balance = ?, last_activity_at = NOW() WHERE id = ?')
            ->execute([$after, (int) $wallet['id']]);
        $pdo->prepare("
            INSERT INTO wallet_transactions
                (wallet_id, user_id, amount, type, direction, description, reference, provider, status, balance_before, balance_after, completed_at)
            VALUES (?, ?, ?, 'refund_reversal', 'outflow', ?, ?, 'marketplace', 'completed', ?, ?, NOW())
        ")->execute([
            (int) $wallet['id'],
            $sellerUserId,
            $debited,
            'Marketplace refund reversal for order ' . $orderRef,
            'REFUND-REV-' . $orderRef,
            $balance,
            $after,
        ]);
    }

    return ['debited' => round($debited, 2), 'shortfall' => $shortfall];
}

/**
 * Execute (or replay) a full refund for a marketplace checkout.
 *
 * @return array{success: bool, duplicate?: bool, refund_ref?: string, status?: string, refund_amount?: float, error?: string}
 */
function market_refund_checkout_once(PDO $pdo, string $checkoutRef, array $opts = []): array
{
    market_refund_ensure_schema($pdo);
    $checkoutRef = trim((string) $checkoutRef);
    if ($checkoutRef === '') {
        return ['success' => false, 'error' => 'A checkout reference is required.'];
    }

    // Idempotency: a refund already recorded for this checkout is
    // returned as-is, no matter which door (console, support, approval
    // handler) asks for it.
    $existing = $pdo->prepare('SELECT * FROM marketplace_refunds WHERE checkout_ref = ? LIMIT 1');
    $existing->execute([$checkoutRef]);
    $recorded = $existing->fetch(PDO::FETCH_ASSOC);
    if ($recorded) {
        return [
            'success' => true,
            'duplicate' => true,
            'refund_ref' => (string) $recorded['refund_ref'],
            'status' => (string) $recorded['status'],
            'refund_amount' => (float) $recorded['refund_amount'],
        ];
    }

    $reason = mb_substr(trim((string) ($opts['reason'] ?? '')), 0, 1000);
    $adminId = (int) ($opts['admin_id'] ?? 0);

    $pdo->beginTransaction();
    try {
        $orderStmt = $pdo->prepare("SELECT * FROM marketplace_orders WHERE checkout_ref = ? ORDER BY id FOR UPDATE");
        $orderStmt->execute([$checkoutRef]);
        $orders = $orderStmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$orders) {
            $pdo->rollBack();
            return ['success' => false, 'error' => 'No orders found for this checkout.'];
        }

        $paid = array_values(array_filter($orders, static fn(array $o): bool => (string) $o['payment_status'] === 'paid'));
        if (!$paid) {
            $pdo->rollBack();
            return ['success' => false, 'error' => 'This checkout has no paid orders to refund.'];
        }

        $subtotal = 0.0;
        $deliveryFee = 0.0;
        $serviceFee = 0.0;
        $commission = 0.0;
        $settledTotal = 0.0;
        $shortfallTotal = 0.0;
        $buyerUserId = 0;
        $paymentMethod = '';
        $orderIds = [];

        foreach ($paid as $order) {
            $subtotal += (float) $order['total_amount'];
            $deliveryFee = max($deliveryFee, (float) $order['delivery_fee']);
            $serviceFee = max($serviceFee, (float) $order['service_fee']);
            $commission += (float) $order['platform_fee_amount'];
            $buyerUserId = (int) ($order['buyer_user_id'] ?? 0);
            $paymentMethod = (string) ($order['payment_method'] ?? '');
            $orderIds[] = (int) $order['id'];

            // Reverse the seller settlement for orders that were settled.
            if ((string) ($order['payout_status'] ?? '') === 'settled') {
                $sellerStmt = $pdo->prepare('SELECT user_id FROM marketplace_sellers WHERE id = ? LIMIT 1');
                $sellerStmt->execute([(int) $order['seller_id']]);
                $sellerUserId = (int) ($sellerStmt->fetchColumn() ?: 0);
                $net = (float) ($order['seller_net_amount'] ?? 0);
                if ($net <= 0) {
                    $net = round((float) $order['total_amount'] - (float) $order['platform_fee_amount'], 2);
                }
                $settledTotal += $net;
                $reversal = market_reverse_seller_settlement($pdo, $sellerUserId, $net, (string) $order['order_ref']);
                $shortfallTotal += $reversal['shortfall'];
            }

            // Reverse the commission this order earned in the revenue ledger.
            $orderCommission = (float) ($order['platform_fee_amount'] ?? 0);
            if ($orderCommission > 0) {
                revenue_record_once($pdo, [
                    'revenue_ref' => 'REV-REFUND-' . (string) $order['order_ref'],
                    'source_module' => 'marketplace',
                    'source_type' => 'order_commission_refund',
                    'source_id' => (int) $order['id'],
                    'user_id' => $buyerUserId,
                    'seller_id' => (int) $order['seller_id'],
                    'gross_amount' => $orderCommission,
                    'revenue_amount' => $orderCommission,
                    'net_payable_amount' => 0,
                    'rule_key' => ((string) ($order['platform_fee_rule'] ?: 'marketplace_commission_default')) . '_refund',
                    'status' => 'reversed',
                    'description' => 'Commission reversal for refunded order ' . (string) $order['order_ref'],
                    'metadata' => ['checkout_ref' => $checkoutRef, 'order_ref' => (string) $order['order_ref']],
                ]);
            }
        }

        // The buyer paid subtotal + delivery + service fees across the checkout.
        $refundAmount = round($subtotal + $deliveryFee + $serviceFee, 2);

        // Wallet-funded checkouts: the money is in the platform wallet, so
        // the buyer is credited back immediately. External payments (Monnify
        // direct, bank transfer): the charge lives at the provider, so the
        // refund is recorded as an obligation the operator must issue there.
        $walletCredited = false;
        if (strtolower($paymentMethod) === 'wallet' && $buyerUserId > 0 && $refundAmount > 0) {
            $credit = wallet_credit_once(
                $pdo,
                $buyerUserId,
                $refundAmount,
                'REFUND-' . $checkoutRef,
                'Marketplace refund for checkout ' . $checkoutRef,
                'marketplace',
                'REFUND-' . $checkoutRef,
                ['checkout_ref' => $checkoutRef, 'reason' => $reason]
            );
            if (empty($credit['success'])) {
                $pdo->rollBack();
                return ['success' => false, 'error' => 'Unable to credit the buyer wallet.'];
            }
            $walletCredited = true;
        }

        $status = $walletCredited ? 'completed' : 'provider_refund_pending';

        $pdo->prepare("
            UPDATE marketplace_orders
            SET payment_status = 'refunded', status = 'refunded', payout_status = 'refunded',
                refunded_at = NOW(), updated_at = NOW()
            WHERE checkout_ref = ? AND payment_status = 'paid'
        ")->execute([$checkoutRef]);

        $insert = $pdo->prepare("
            INSERT INTO marketplace_refunds
                (refund_ref, checkout_ref, buyer_user_id, order_ids_json, refund_amount,
                 settlement_reversal_amount, settlement_shortfall_amount, commission_reversal_amount,
                 payment_method, status, reason, admin_note, requested_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $insert->execute([
            market_refund_ref($checkoutRef),
            $checkoutRef,
            $buyerUserId > 0 ? $buyerUserId : null,
            json_encode($orderIds),
            $refundAmount,
            round($settledTotal, 2),
            round($shortfallTotal, 2),
            round($commission, 2),
            $paymentMethod,
            $status,
            $reason,
            $reason !== '' ? $reason : null,
            $adminId > 0 ? $adminId : null,
        ]);
        $refundId = (int) $pdo->lastInsertId();

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('market_refund_checkout_once failed for ' . $checkoutRef . ': ' . $e->getMessage());
        return ['success' => false, 'error' => 'The refund could not be completed safely.'];
    }

    if (function_exists('admin_audit')) {
        admin_audit(
            $pdo,
            'marketplace_refund',
            'Admin #' . $adminId . ' refunded checkout ' . $checkoutRef . ' (NGN ' . number_format($refundAmount, 2) . ', ' . $status . ').'
        );
    }

    return [
        'success' => true,
        'refund_id' => $refundId,
        'refund_ref' => (string) $pdo->query("SELECT refund_ref FROM marketplace_refunds WHERE id = {$refundId}")->fetchColumn(),
        'status' => $status,
        'refund_amount' => $refundAmount,
        'settlement_reversal_amount' => round($settledTotal, 2),
        'settlement_shortfall_amount' => round($shortfallTotal, 2),
        'commission_reversal_amount' => round($commission, 2),
        'wallet_credited' => $walletCredited,
    ];
}

/**
 * Queue a refund for Super Admin approval when dual control is on.
 */
function market_queue_refund_approval(PDO $pdo, string $checkoutRef, float $amount, int $adminId, string $reason): int
{
    admin_ensure_action_request_schema($pdo);
    $dup = $pdo->prepare("SELECT id FROM admin_action_requests WHERE request_type = 'refund_approval' AND target_table = 'marketplace_orders' AND target_key = ? AND status = 'pending' ORDER BY id DESC LIMIT 1");
    $dup->execute([$checkoutRef]);
    $existingId = (int) ($dup->fetchColumn() ?: 0);
    if ($existingId > 0) {
        return $existingId;
    }

    $firstOrder = $pdo->prepare("SELECT id FROM marketplace_orders WHERE checkout_ref = ? ORDER BY id LIMIT 1");
    $firstOrder->execute([$checkoutRef]);
    $orderId = (int) ($firstOrder->fetchColumn() ?: 0);

    $stmt = $pdo->prepare("
        INSERT INTO admin_action_requests
            (request_type, target_table, target_id, target_key, target_label, requested_by, reason, payload_json)
        VALUES ('refund_approval', 'marketplace_orders', ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $orderId,
        $checkoutRef,
        'Marketplace refund of NGN ' . number_format(round($amount, 2), 2) . ' for checkout ' . $checkoutRef,
        $adminId,
        $reason !== '' ? $reason : 'Super Admin approval required for this marketplace refund.',
        json_encode([
            'checkout_ref' => $checkoutRef,
            'amount' => round($amount, 2),
            'reason' => mb_substr($reason, 0, 500),
            'admin_id' => $adminId,
        ], JSON_UNESCAPED_SLASHES),
    ]);
    $requestId = (int) $pdo->lastInsertId();
    if (function_exists('admin_audit')) {
        admin_audit($pdo, 'marketplace_refund_queued', 'Admin #' . $adminId . ' queued a refund of NGN ' . number_format(round($amount, 2), 2) . ' for checkout ' . $checkoutRef . ' (approval request #' . $requestId . ').');
    }
    return $requestId;
}

/**
 * Approval handler: approving executes the queued refund through the shared
 * handler; rejecting only records the rejection and moves no money.
 */
function admin_approval_marketplace_refund(PDO $pdo, array $request, string $decision, string $note): void
{
    admin_ensure_action_request_schema($pdo);
    $requestId = (int) $request['id'];
    $checkoutRef = (string) ($request['target_key'] ?? '');
    $reviewedBy = admin_approval_reviewer_id($pdo);
    $initiator = (int) ($request['requested_by'] ?? 0);

    if ($decision === 'reject') {
        admin_approval_mark_reviewed($pdo, $requestId, $decision, $note, $reviewedBy, 'Rejected by Super Admin.');
        admin_approval_audit($pdo, 'marketplace_refund_rejected', 'Super Admin rejected a refund for checkout ' . $checkoutRef . ' (requested by admin #' . $initiator . ').');
        return;
    }

    if (!function_exists('market_refund_checkout_once')) {
        require_once __DIR__ . '/marketplace-refunds.php';
    }
    $payload = json_decode((string) ($request['payload_json'] ?? ''), true);
    $payload = is_array($payload) ? $payload : [];
    $reason = trim((string) ($payload['reason'] ?? $request['reason'] ?? 'Super Admin approved refund.'));

    $result = market_refund_checkout_once($pdo, $checkoutRef, [
        'reason' => $reason . ' (Super Admin approved; requested by admin #' . $initiator . ')',
        'admin_id' => $reviewedBy,
    ]);
    if (empty($result['success'])) {
        throw new RuntimeException((string) ($result['error'] ?? 'The refund could not be completed.'));
    }
    admin_approval_mark_reviewed($pdo, $requestId, $decision, $note, $reviewedBy, 'Approved by Super Admin.');
    admin_approval_audit($pdo, 'marketplace_refund_approved', 'Super Admin approved a refund of NGN ' . number_format((float) ($result['refund_amount'] ?? 0), 2) . ' for checkout ' . $checkoutRef . ' (requested by admin #' . $initiator . ').');
}
