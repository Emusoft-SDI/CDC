<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/monnify.php';
require_once __DIR__ . '/../lib/paystack.php';
require_once __DIR__ . '/../lib/gateway-manager.php';
require_once __DIR__ . '/../lib/wallet.php';

app_require_cli('payout-reconciliation');

$pdo = db();
wallet_ensure_schema($pdo);

// Withdrawals stuck in 'processing' (or pending with a provider transfer
// code) are re-queried at the provider so a payout that succeeded or failed
// at the bank is reflected here: confirmed payouts are finalized and the
// hold released, confirmed failures have the hold returned to the user.
$stmt = $pdo->query("
    SELECT * FROM wallet_withdrawals
    WHERE provider IN ('monnify', 'paystack', 'fincra', 'woven', 'korapay', 'allawee')
      AND funds_released = 0
      AND status IN ('processing', 'pending')
      AND (
        status = 'processing'
        OR (payout_transfer_code IS NOT NULL AND payout_transfer_code <> '')
      )
      AND updated_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)
    LIMIT 200
");
$withdrawals = $stmt->fetchAll(PDO::FETCH_ASSOC);

$finalized = 0;
$released = 0;
$inFlight = 0;
foreach ($withdrawals as $withdrawal) {
    $status = wallet_query_payout_status($withdrawal);

    if ($status['state'] === 'success') {
        $provider = strtolower((string) $withdrawal['provider']);
        $result = wallet_finalize_withdrawal_payout($pdo, (int) $withdrawal['id'], [
            'success' => true,
            'provider' => $provider,
            'payout_reference' => (string) ($withdrawal['payout_reference'] ?: $withdrawal['reference']),
            'transfer_code' => (string) ($withdrawal['payout_transfer_code'] ?: ''),
            'payout_status' => 'confirmed',
            'payload' => $status['payload'] ?? [],
        ], $provider);
        if (!empty($result['success'])) {
            $finalized++;
        } else {
            error_log('payout-reconciliation: finalize failed for withdrawal #' . $withdrawal['id'] . ': ' . ($result['error'] ?? 'unknown'));
            $inFlight++;
        }
        continue;
    }

    if ($status['state'] === 'failed') {
        $release = wallet_release_withdrawal_funds($pdo, (int) $withdrawal['id'], 'failed', 'Provider confirmed the payout failed; funds returned to the wallet.', 0);
        if (!empty($release['success']) && !empty($release['released'])) {
            $released++;
        } else {
            $inFlight++;
        }
        continue;
    }

    // Still in flight at the provider (or status unknown): leave it alone.
    $inFlight++;
}

// Dispatch pre-flight buffered withdrawals whose hold window has elapsed
$bufferedDispatched = 0;
$policy = function_exists('wallet_withdrawal_policy') ? wallet_withdrawal_policy($pdo) : [];
$autoEnabled = !empty($policy['auto_enabled']);

$bufferedStmt = $pdo->query("
    SELECT * FROM wallet_withdrawals
    WHERE status = 'pending'
      AND automation_eligible = 1
      AND preflight_buffered = 1
      AND funds_released = 0
      AND preflight_buffered_until IS NOT NULL
      AND preflight_buffered_until <= NOW()
    ORDER BY id ASC
    LIMIT 50
");
$bufferedList = $bufferedStmt->fetchAll(PDO::FETCH_ASSOC);

if (!$autoEnabled && !empty($bufferedList)) {
    // Master switch disabled: route elapsed buffered items to manual review so they are not dispatched
    foreach ($bufferedList as $bufWd) {
        $pdo->prepare("
            UPDATE wallet_withdrawals
            SET preflight_buffered = 0, review_required = 1, automation_eligible = 0,
                review_reason = 'Withdrawal automation master switch is disabled; queued for manual review.',
                updated_at = NOW()
            WHERE id = ?
        ")->execute([(int) $bufWd['id']]);
    }
} elseif ($autoEnabled) {
    foreach ($bufferedList as $bufWd) {
        $amount = (float) ($bufWd['amount'] ?? 0);
        $globalCap = (float) ($policy['global_daily_cap'] ?? 0);

        // Verify global platform daily cap before dispatching
        if ($globalCap > 0) {
            $capStmt = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM wallet_withdrawals WHERE status IN ('processing', 'approved', 'paid') AND requested_at >= CURDATE()");
            $usedToday = (float) $capStmt->fetchColumn();
            if (($usedToday + $amount) > $globalCap) {
                $pdo->prepare("
                    UPDATE wallet_withdrawals
                    SET preflight_buffered = 0, review_required = 1, automation_eligible = 0,
                        review_reason = 'Platform global daily withdrawal cap reached while buffered; queued for operator review.',
                        updated_at = NOW()
                    WHERE id = ?
                ")->execute([(int) $bufWd['id']]);
                continue;
            }
        }

        $dispatch = wallet_admin_process_withdrawal($pdo, (int) $bufWd['id'], 0, 'approve', 'Auto-dispatched after pre-flight inspection buffer elapsed.');
        if (!empty($dispatch['success'])) {
            $pdo->prepare("UPDATE wallet_withdrawals SET preflight_buffered = 0, auto_processed_at = NOW() WHERE id = ?")->execute([(int) $bufWd['id']]);
            $bufferedDispatched++;
        } else {
            $failNote = (string) ($dispatch['error'] ?? 'Auto-dispatch failed after pre-flight buffer elapsed');
            $pdo->prepare("UPDATE wallet_withdrawals SET preflight_buffered = 0, admin_note = CONCAT(COALESCE(admin_note,''), ' | ', ?) WHERE id = ?")
                ->execute([$failNote, (int) $bufWd['id']]);
        }
    }
}

echo "Payout reconciliation: finalized={$finalized} released={$released} in-flight={$inFlight} buffered_dispatched={$bufferedDispatched}" . PHP_EOL;
