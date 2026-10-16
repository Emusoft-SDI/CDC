<?php
declare(strict_types=1);

require_once __DIR__ . '/admin-approvals.php';

/**
 * Toggleable dual control for money movement.
 *
 * Super Admin can switch this on/off and set an NGN threshold. When enabled,
 * wallet credits and withdrawal payouts at or above the threshold queue for
 * Super Admin approval instead of executing immediately; smaller amounts flow
 * automatically so operators keep working without a support queue.
 */

function wallet_dual_control_defaults(): array
{
    return [
        'wallet_dual_control_enabled' => '0',
        'wallet_dual_control_limit' => '100000',
    ];
}

function wallet_dual_control_settings(PDO $pdo): array
{
    $settings = wallet_dual_control_defaults();
    foreach ($settings as $key => $default) {
        $settings[$key] = admin_setting($pdo, $key, $default);
    }
    $settings['enabled'] = $settings['wallet_dual_control_enabled'] === '1';
    $settings['limit'] = max(0, (float) $settings['wallet_dual_control_limit']);
    return $settings;
}

function wallet_dual_control_required(PDO $pdo, float $amount): bool
{
    $settings = wallet_dual_control_settings($pdo);
    if (!$settings['enabled']) {
        return false;
    }
    if ($settings['limit'] <= 0) {
        // Enabled with a zero threshold: every movement needs Super Admin sign-off.
        return true;
    }
    return round($amount, 2) >= $settings['limit'];
}

function wallet_queue_credit_approval(PDO $pdo, int $userId, float $amount, int $adminId, string $note): int
{
    admin_ensure_action_request_schema($pdo);
    $reference = 'ADMIN-CREDIT-' . date('YmdHis') . '-' . $userId . '-' . strtoupper(bin2hex(random_bytes(3)));
    $stmt = $pdo->prepare("
        INSERT INTO admin_action_requests
            (request_type, target_table, target_id, target_key, target_label, requested_by, reason, payload_json)
        VALUES ('wallet_credit', 'wallets', ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $userId,
        $reference,
        'Wallet credit NGN ' . number_format(round($amount, 2), 2) . ' for user #' . $userId,
        $adminId,
        $note !== '' ? $note : 'Super Admin approval required for this wallet credit.',
        json_encode([
            'amount' => round($amount, 2),
            'note' => mb_substr($note, 0, 500),
            'admin_id' => $adminId,
            'reference' => $reference,
        ], JSON_UNESCAPED_SLASHES),
    ]);
    $requestId = (int) $pdo->lastInsertId();
    if (function_exists('admin_audit')) {
        admin_audit($pdo, 'wallet_credit_queued', 'Admin #' . $adminId . ' queued a wallet credit of NGN ' . number_format(round($amount, 2), 2) . ' for user #' . $userId . ' (approval request #' . $requestId . ').');
    }
    return $requestId;
}

function wallet_queue_withdrawal_approval(PDO $pdo, int $withdrawalId, int $adminId, string $adminNote): int
{
    admin_ensure_action_request_schema($pdo);
    $stmt = $pdo->prepare("SELECT reference, amount, user_id FROM wallet_withdrawals WHERE id = ? LIMIT 1");
    $stmt->execute([$withdrawalId]);
    $withdrawal = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$withdrawal) {
        throw new RuntimeException('Withdrawal request was not found.');
    }
    // One pending request per withdrawal: a second operator clicking approve
    // reuses the same queued request instead of creating a duplicate.
    $dup = $pdo->prepare("SELECT id FROM admin_action_requests WHERE request_type = 'withdrawal_approval' AND target_table = 'wallet_withdrawals' AND target_id = ? AND status = 'pending' ORDER BY id DESC LIMIT 1");
    $dup->execute([$withdrawalId]);
    $existingId = (int) ($dup->fetchColumn() ?: 0);
    if ($existingId > 0) {
        return $existingId;
    }
    $insert = $pdo->prepare("
        INSERT INTO admin_action_requests
            (request_type, target_table, target_id, target_key, target_label, requested_by, reason, payload_json)
        VALUES ('withdrawal_approval', 'wallet_withdrawals', ?, ?, ?, ?, ?, ?)
    ");
    $insert->execute([
        $withdrawalId,
        (string) $withdrawal['reference'],
        'Withdrawal payout NGN ' . number_format((float) $withdrawal['amount'], 2) . ' for user #' . $withdrawal['user_id'],
        $adminId,
        $adminNote !== '' ? $adminNote : 'Super Admin approval required for this withdrawal payout.',
        json_encode(['admin_id' => $adminId, 'admin_note' => mb_substr($adminNote, 0, 500)], JSON_UNESCAPED_SLASHES),
    ]);
    $requestId = (int) $pdo->lastInsertId();
    if (function_exists('admin_audit')) {
        admin_audit($pdo, 'withdrawal_approval_queued', 'Admin #' . $adminId . ' queued withdrawal #' . $withdrawalId . ' (NGN ' . number_format((float) $withdrawal['amount'], 2) . ') for Super Admin approval (request #' . $requestId . ').');
    }
    return $requestId;
}

/**
 * Queue an operator wallet debit (reversal) for Super Admin approval
 * when dual control is on. Mirrors wallet_queue_credit_approval.
 */
function wallet_queue_debit_approval(PDO $pdo, int $userId, float $amount, int $adminId, string $note): int
{
    admin_ensure_action_request_schema($pdo);
    $reference = 'ADMIN-DEBIT-' . date('YmdHis') . '-' . $userId . '-' . strtoupper(bin2hex(random_bytes(3)));
    $stmt = $pdo->prepare("
        INSERT INTO admin_action_requests
            (request_type, target_table, target_id, target_key, target_label, requested_by, reason, payload_json)
        VALUES ('wallet_debit', 'wallets', ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $userId,
        $reference,
        'Wallet debit (reversal) NGN ' . number_format(round($amount, 2), 2) . ' for user #' . $userId,
        $adminId,
        $note !== '' ? $note : 'Super Admin approval required for this wallet debit.',
        json_encode([
            'amount' => round($amount, 2),
            'note' => mb_substr($note, 0, 500),
            'admin_id' => $adminId,
            'reference' => $reference,
        ], JSON_UNESCAPED_SLASHES),
    ]);
    $requestId = (int) $pdo->lastInsertId();
    if (function_exists('admin_audit')) {
        admin_audit($pdo, 'wallet_debit_queued', 'Admin #' . $adminId . ' queued a wallet debit of NGN ' . number_format(round($amount, 2), 2) . ' for user #' . $userId . ' (approval request #' . $requestId . ').');
    }
    return $requestId;
}

function wallet_pending_approvals(PDO $pdo): array
{
    admin_ensure_action_request_schema($pdo);
    $stmt = $pdo->query("
        SELECT ar.*, u.name requester_name, u.email requester_email,
          ow.reference withdrawal_reference, ow.amount withdrawal_amount,
          mo.order_ref payout_order_ref, mo.total_amount payout_amount
        FROM admin_action_requests ar
        LEFT JOIN users u ON u.id = ar.requested_by
        LEFT JOIN wallet_withdrawals ow ON ow.id = ar.target_id
        LEFT JOIN marketplace_orders mo ON mo.id = ar.target_id
        WHERE ar.status = 'pending' AND ar.request_type IN ('wallet_credit', 'wallet_debit', 'withdrawal_approval', 'payout_approval', 'refund_approval')
        ORDER BY ar.created_at DESC, ar.id DESC
        LIMIT 200
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function wallet_pending_approval_count(PDO $pdo): int
{
    admin_ensure_action_request_schema($pdo);
    $stmt = $pdo->query("SELECT COUNT(*) FROM admin_action_requests WHERE status = 'pending' AND request_type IN ('wallet_credit', 'wallet_debit', 'withdrawal_approval', 'payout_approval', 'refund_approval')");
    return (int) ($stmt->fetchColumn() ?: 0);
}

function wallet_review_credit_approval(PDO $pdo): string
{
    admin_ensure_action_request_schema($pdo);
    $requestId = (int) ($_POST['request_id'] ?? 0);
    $decision = (string) ($_POST['decision'] ?? '');
    $note = trim((string) ($_POST['review_note'] ?? ''));
    if ($requestId <= 0 || !in_array($decision, ['approve', 'reject'], true)) {
        throw new RuntimeException('Select a valid wallet credit request and decision.');
    }
    $stmt = $pdo->prepare("SELECT * FROM admin_action_requests WHERE id = ? AND request_type = 'wallet_credit' AND target_table = 'wallets' AND status = 'pending' LIMIT 1");
    $stmt->execute([$requestId]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$request) {
        throw new RuntimeException('Wallet credit request was not found or has already been reviewed.');
    }

    // Execution lives in the registered approval handler so the same logic is
    // reachable via admin_apply_approval() (super-admin console, jobs, CLI).
    admin_apply_approval($pdo, $request, $decision, $note);

    return $decision === 'reject'
        ? 'Wallet credit request rejected. No funds were moved.'
        : 'Wallet credit of NGN ' . number_format(round((float) (json_decode((string) $request['payload_json'], true) ?: ['amount' => 0])['amount'] ?? 0, 2), 2) . ' completed after Super Admin approval.';
}

function wallet_review_withdrawal_approval(PDO $pdo): string
{
    admin_ensure_action_request_schema($pdo);
    $requestId = (int) ($_POST['request_id'] ?? 0);
    $decision = (string) ($_POST['decision'] ?? '');
    $note = trim((string) ($_POST['review_note'] ?? ''));
    if ($requestId <= 0 || !in_array($decision, ['approve', 'reject'], true)) {
        throw new RuntimeException('Select a valid withdrawal approval request and decision.');
    }
    $stmt = $pdo->prepare("SELECT * FROM admin_action_requests WHERE id = ? AND request_type = 'withdrawal_approval' AND target_table = 'wallet_withdrawals' AND status = 'pending' LIMIT 1");
    $stmt->execute([$requestId]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$request) {
        throw new RuntimeException('Withdrawal approval request was not found or has already been reviewed.');
    }

    // Execution lives in the registered approval handler so the same logic is
    // reachable via admin_apply_approval() (super-admin console, jobs, CLI).
    admin_apply_approval($pdo, $request, $decision, $note);

    return $decision === 'approve'
        ? 'Withdrawal payout initiated after Super Admin approval.'
        : 'Withdrawal rejected by Super Admin; the held funds were returned to the user wallet.';
}

function wallet_review_payout_approval(PDO $pdo): string
{
    admin_ensure_action_request_schema($pdo);
    $requestId = (int) ($_POST['request_id'] ?? 0);
    $decision = (string) ($_POST['decision'] ?? '');
    $note = trim((string) ($_POST['review_note'] ?? ''));
    if ($requestId <= 0 || !in_array($decision, ['approve', 'reject'], true)) {
        throw new RuntimeException('Select a valid payout request and decision.');
    }
    $stmt = $pdo->prepare("SELECT * FROM admin_action_requests WHERE id = ? AND request_type = 'payout_approval' AND target_table = 'marketplace_orders' AND status = 'pending' LIMIT 1");
    $stmt->execute([$requestId]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$request) {
        throw new RuntimeException('Payout approval request was not found or has already been reviewed.');
    }

    // Execution lives in the registered approval handler so the same logic is
    // reachable via admin_apply_approval() (super-admin console, jobs, CLI).
    admin_apply_approval($pdo, $request, $decision, $note);

    return $decision === 'approve'
        ? 'Marketplace payout applied after Super Admin approval.'
        : 'Marketplace payout rejected by Super Admin; the order payout status was not changed.';
}
