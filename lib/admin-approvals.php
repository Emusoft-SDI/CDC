<?php
declare(strict_types=1);

/**
 * Generalized approval workflow (Phase 2, additive).
 *
 * The platform already stores approvable actions in admin_action_requests with a
 * request_type column. This registry lets new request types (refund, payout
 * release, role grant, rule change, ...) register a handler, while the existing
 * 'delete' path keeps using the legacy reviewer as a fallback.
 */

require_once __DIR__ . '/wallet.php';

function admin_register_approval_handler(string $type, callable $handler): void
{
    $GLOBALS['__admin_approval_handlers'][$type] = $handler;
}

/** @return array<string,callable> */
function admin_approval_handlers(): array
{
    return $GLOBALS['__admin_approval_handlers'] ?? [];
}

/**
 * Apply an approval decision for a queued request row.
 *
 * @param array<string,mixed> $request A row from admin_action_requests.
 */
function admin_apply_approval(PDO $pdo, array $request, string $decision, string $note = ''): void
{
    if (!in_array($decision, ['approve', 'reject'], true)) {
        throw new RuntimeException('Unknown approval decision: ' . $decision);
    }

    $type = (string) ($request['request_type'] ?? '');
    $handlers = admin_approval_handlers();
    if (isset($handlers[$type])) {
        ($handlers[$type])($pdo, $request, $decision, $note);
        return;
    }

    // Legacy fallback preserves exact current behaviour for delete requests.
    if ($type === 'delete' && function_exists('admin_review_action_request')) {
        admin_review_action_request($pdo, (int) ($request['id'] ?? 0), $decision, $note);
        return;
    }

    throw new RuntimeException('No approval handler registered for request type "' . $type . '".');
}

/**
 * Certificate tables that may be revoked/restored through the registry.
 *
 * @return array<int,string>
 */
function admin_approval_certificate_tables(): array
{
    return ['certificates', 'provider_accreditation_certificates', 'academy_certificates', 'academy_group_certificates'];
}

/** Validate a stored target_table against the certificate whitelist. */
function admin_approval_certificate_table(string $table): string
{
    return in_array($table, admin_approval_certificate_tables(), true) ? $table : 'certificates';
}

/** Resolve the Super Admin (or signed-in admin) who records the decision. */
function admin_approval_reviewer_id(PDO $pdo): int
{
    $superId = (int) ($_SESSION['super_admin_user_id'] ?? 0);
    if ($superId > 0) {
        return $superId;
    }
    if (function_exists('admin_current_user_id')) {
        return (int) admin_current_user_id($pdo);
    }
    return 0;
}

/** Mark a queued request approved/rejected with the reviewer and note. */
function admin_approval_mark_reviewed(PDO $pdo, int $requestId, string $decision, string $note, int $reviewedBy, string $defaultNote): void
{
    $pdo->prepare("UPDATE admin_action_requests SET status = ?, reviewed_by = ?, reviewed_at = NOW(), review_note = ? WHERE id = ?")
        ->execute([$decision === 'approve' ? 'approved' : 'rejected', $reviewedBy, $note !== '' ? $note : $defaultNote, $requestId]);
}

/** Best-effort audit write that prefers the Super Admin audit helper. */
function admin_approval_audit(PDO $pdo, string $action, string $description): void
{
    if (function_exists('super_admin_audit')) {
        super_admin_audit($pdo, $action, $description);
        return;
    }
    if (function_exists('admin_audit')) {
        admin_audit($pdo, $action, $description);
    }
}

/** Certificate reference helper for audit messages. */
function admin_approval_certificate_ref(array $request): string
{
    $key = trim((string) ($request['target_key'] ?? ''));
    if ($key !== '') {
        return $key;
    }
    return '#' . (int) ($request['target_id'] ?? 0);
}

/**
 * Handler: revoke_certificate.
 *
 * Mirrors the Super Admin revocation path (super_admin_review_certificate_revocation
 * and the registry's direct execution): the certificate is set to revoked with the
 * queued reason, and the request row is closed out. On reject only the request is
 * recorded as rejected.
 */
function admin_approval_revoke_certificate(PDO $pdo, array $request, string $decision, string $note): void
{
    admin_ensure_action_request_schema($pdo);
    $table = admin_approval_certificate_table((string) ($request['target_table'] ?? 'certificates'));
    $certificateId = (int) ($request['target_id'] ?? 0);
    $requestId = (int) ($request['id'] ?? 0);
    $reviewedBy = admin_approval_reviewer_id($pdo);
    $certificateRef = admin_approval_certificate_ref($request);

    if ($decision === 'reject') {
        admin_approval_mark_reviewed($pdo, $requestId, $decision, $note, $reviewedBy, 'Rejected by Super Admin.');
        admin_approval_audit($pdo, 'certificate_revocation_rejected', 'Rejected certificate revocation request for ' . $certificateRef . '.');
        return;
    }

    $payload = json_decode((string) ($request['payload_json'] ?? ''), true);
    $payload = is_array($payload) ? $payload : [];
    $reason = trim((string) ($payload['reason'] ?? $request['reason'] ?? 'Super Admin approved certificate revocation.'));
    // Preserve the soft-delete guard used by the legacy reviewer for the
    // main certificates table.
    $soft = ($table === 'certificates' && function_exists('app_column_exists') && app_column_exists($pdo, 'certificates', 'deleted_at'))
        ? ' AND deleted_at IS NULL'
        : '';

    $pdo->beginTransaction();
    try {
        $update = $pdo->prepare("UPDATE {$table} SET status = 'revoked', revoked_at = NOW(), revoked_reason = ? WHERE id = ?{$soft} AND status = 'issued'");
        $update->execute([$reason, $certificateId]);
        if ($update->rowCount() !== 1) {
            throw new RuntimeException('Certificate is no longer issued or could not be revoked.');
        }
        admin_approval_mark_reviewed($pdo, $requestId, $decision, $note, $reviewedBy, 'Approved by Super Admin.');
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    admin_approval_audit($pdo, 'certificate_revoked', 'Approved and revoked certificate ' . $certificateRef . '.');
}

/**
 * Handler: restore_certificate.
 *
 * Mirrors the registry's direct restoration path: a revoked certificate is set
 * back to issued and the revocation metadata is cleared.
 */
function admin_approval_restore_certificate(PDO $pdo, array $request, string $decision, string $note): void
{
    admin_ensure_action_request_schema($pdo);
    $table = admin_approval_certificate_table((string) ($request['target_table'] ?? 'certificates'));
    $certificateId = (int) ($request['target_id'] ?? 0);
    $requestId = (int) ($request['id'] ?? 0);
    $reviewedBy = admin_approval_reviewer_id($pdo);
    $certificateRef = admin_approval_certificate_ref($request);

    if ($decision === 'reject') {
        admin_approval_mark_reviewed($pdo, $requestId, $decision, $note, $reviewedBy, 'Rejected by Super Admin.');
        admin_approval_audit($pdo, 'certificate_restore_rejected', 'Rejected certificate restoration request for ' . $certificateRef . '.');
        return;
    }

    $pdo->beginTransaction();
    try {
        $update = $pdo->prepare("UPDATE {$table} SET status = 'issued', revoked_at = NULL, revoked_reason = NULL WHERE id = ? AND status = 'revoked'");
        $update->execute([$certificateId]);
        if ($update->rowCount() !== 1) {
            throw new RuntimeException('Certificate is no longer revoked or could not be restored.');
        }
        admin_approval_mark_reviewed($pdo, $requestId, $decision, $note, $reviewedBy, 'Approved by Super Admin.');
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    admin_approval_audit($pdo, 'certificate_restored', 'Approved and restored certificate ' . $certificateRef . '.');
}

/**
 * Handler: wallet_credit.
 *
 * Mirrors wallet_review_credit_approval: approving executes the queued credit
 * through wallet_admin_credit(); rejecting only records the rejection.
 */
function admin_approval_wallet_credit(PDO $pdo, array $request, string $decision, string $note): void
{
    admin_ensure_action_request_schema($pdo);
    $requestId = (int) ($request['id'] ?? 0);
    $userId = (int) ($request['target_id'] ?? 0);
    $payload = json_decode((string) ($request['payload_json'] ?? ''), true);
    $payload = is_array($payload) ? $payload : [];
    $amount = round((float) ($payload['amount'] ?? 0), 2);
    $initiator = (int) ($payload['admin_id'] ?? (int) $request['requested_by']);
    $creditNote = trim((string) ($payload['note'] ?? ''));
    $reviewedBy = admin_approval_reviewer_id($pdo);

    if ($decision === 'reject') {
        admin_approval_mark_reviewed($pdo, $requestId, $decision, $note, $reviewedBy, 'Rejected by Super Admin.');
        admin_approval_audit($pdo, 'wallet_credit_rejected', 'Super Admin rejected a wallet credit of NGN ' . number_format($amount, 2) . ' for user #' . $userId . ' (requested by admin #' . $initiator . ').');
        return;
    }

    if ($amount <= 0) {
        throw new RuntimeException('This credit request does not carry a valid amount.');
    }
    if (!function_exists('wallet_admin_credit')) {
        throw new RuntimeException('The wallet module is not available.');
    }
    $suffix = 'Super Admin approved (requested by admin #' . $initiator . ')';
    $result = wallet_admin_credit($pdo, $userId, $amount, $reviewedBy, $creditNote !== '' ? $creditNote . ' — ' . $suffix : $suffix);
    if (empty($result['success'])) {
        throw new RuntimeException((string) ($result['error'] ?? 'Wallet credit could not be completed.'));
    }
    admin_approval_mark_reviewed($pdo, $requestId, $decision, $note, $reviewedBy, 'Approved by Super Admin.');
    admin_approval_audit($pdo, 'wallet_credit_approved', 'Super Admin approved a wallet credit of NGN ' . number_format($amount, 2) . ' for user #' . $userId . ' (requested by admin #' . $initiator . ').');
}

/**
 * Handler: wallet_debit.
 *
 * Mirrors admin_approval_wallet_credit: approving executes the
 * queued operator debit through wallet_admin_debit(); rejecting
 * only records the rejection and moves no money.
 */
function admin_approval_wallet_debit(PDO $pdo, array $request, string $decision, string $note): void
{
    admin_ensure_action_request_schema($pdo);
    $requestId = (int) $request['id'];
    $userId = (int) $request['target_id'];
    $payload = json_decode((string) $request['payload_json'] ?? '', true);
    $payload = is_array($payload) ? $payload : [];
    $amount = round((float) ($payload['amount'] ?? 0), 2);
    $initiator = (int) ($payload['admin_id'] ?? (int) $request['requested_by']);
    $debitNote = trim((string) ($payload['note'] ?? ''));
    $reviewedBy = admin_approval_reviewer_id($pdo);

    if ($decision === 'reject') {
        admin_approval_mark_reviewed($pdo, $requestId, $decision, $note, $reviewedBy, 'Rejected by Super Admin.');
        admin_approval_audit($pdo, 'wallet_debit_rejected', 'Super Admin rejected a wallet debit of NGN ' . number_format($amount, 2) . ' for user #' . $userId . ' (requested by admin #' . $initiator . ').');
        return;
    }

    if ($amount <= 0) {
        throw new RuntimeException('This debit request does not carry a valid amount.');
    }
    if (!function_exists('wallet_admin_debit')) {
        throw new RuntimeException('The wallet module is not available.');
    }
    $suffix = 'Super Admin approved (requested by admin #' . $initiator . ')';
    $result = wallet_admin_debit($pdo, $userId, $amount, $reviewedBy, $debitNote !== '' ? $debitNote . ' — ' . $suffix : $suffix);
    if (empty($result['success'])) {
        throw new RuntimeException((string) ($result['error'] ?? 'Wallet debit could not be completed.'));
    }
    admin_approval_mark_reviewed($pdo, $requestId, $decision, $note, $reviewedBy, 'Approved by Super Admin.');
    admin_approval_audit($pdo, 'wallet_debit_approved', 'Super Admin approved a wallet debit of NGN ' . number_format($amount, 2) . ' for user #' . $userId . ' (requested by admin #' . $initiator . ').');
}

/**
 * Handler: withdrawal_approval.
 *
 * Mirrors wallet_review_withdrawal_approval: the withdrawal is processed through
 * wallet_admin_process_withdrawal() for either decision (reject returns the held
 * funds to the user wallet), and the request row records the outcome.
 */
function admin_approval_withdrawal(PDO $pdo, array $request, string $decision, string $note): void
{
    admin_ensure_action_request_schema($pdo);
    $requestId = (int) ($request['id'] ?? 0);
    $withdrawalId = (int) ($request['target_id'] ?? 0);
    $initiator = (int) ($request['requested_by'] ?? 0);
    $reviewedBy = admin_approval_reviewer_id($pdo);
    $adminNote = $note !== '' ? $note : ($decision === 'approve' ? 'Approved by Super Admin.' : 'Rejected by Super Admin.');

    if (!function_exists('wallet_admin_process_withdrawal')) {
        throw new RuntimeException('The wallet module is not available.');
    }
    $result = wallet_admin_process_withdrawal($pdo, $withdrawalId, $reviewedBy, $decision, $adminNote . ' (requested by admin #' . $initiator . ')');
    if (empty($result['success'])) {
        throw new RuntimeException((string) ($result['error'] ?? 'Unable to process the withdrawal.'));
    }
    admin_approval_mark_reviewed($pdo, $requestId, $decision, $note, $reviewedBy, $adminNote);
    admin_approval_audit($pdo, $decision === 'approve' ? 'withdrawal_approval_approved' : 'withdrawal_approval_rejected', 'Super Admin ' . $decision . 'd withdrawal #' . $withdrawalId . ' (requested by admin #' . $initiator . ').');
}

/**
 * Handler: payout_approval.
 *
 * Mirrors the payout_details.php execution path: approving applies the queued
 * payout_status to marketplace_orders and records the audit v2 event; rejecting
 * only records the rejection and leaves the payout pending.
 */
function admin_approval_payout(PDO $pdo, array $request, string $decision, string $note): void
{
    admin_ensure_action_request_schema($pdo);
    $requestId = (int) ($request['id'] ?? 0);
    $orderId = (int) ($request['target_id'] ?? 0);
    $reviewedBy = admin_approval_reviewer_id($pdo);
    $payload = json_decode((string) ($request['payload_json'] ?? ''), true);
    $payload = is_array($payload) ? $payload : [];

    if ($decision === 'reject') {
        admin_approval_mark_reviewed($pdo, $requestId, $decision, $note, $reviewedBy, 'Rejected by Super Admin.');
        admin_approval_audit($pdo, 'payout_approval_rejected', 'Super Admin rejected a queued marketplace payout #' . $orderId . '.');
        return;
    }

    $queuedAction = (string) ($payload['action'] ?? 'approve');
    $newStatus = (string) ($payload['payout_status'] ?: ($queuedAction === 'reject' ? 'rejected' : 'approved'));
    if (!in_array($newStatus, ['approved', 'rejected'], true)) {
        throw new RuntimeException('This payout request does not carry a valid payout decision.');
    }

    $pdo->beginTransaction();
    try {
        $statusStmt = $pdo->prepare("SELECT payout_status FROM marketplace_orders WHERE id = ?");
        $statusStmt->execute([$orderId]);
        $oldStatus = (string) ($statusStmt->fetchColumn() ?: 'pending');

        $pdo->prepare("UPDATE marketplace_orders SET payout_status = ? WHERE id = ?")
            ->execute([$newStatus, $orderId]);

        if (function_exists('admin_audit_event')) {
            admin_audit_event($pdo, 'payout_' . $newStatus, 'marketplace_orders', $orderId, ['payout_status' => $oldStatus], ['payout_status' => $newStatus], 'success', 'Admin ' . $queuedAction . ' marketplace payout #' . $orderId);
        }
        admin_approval_mark_reviewed($pdo, $requestId, $decision, $note, $reviewedBy, 'Approved by Super Admin.');
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Handler: revoke_provider_certification / suspend_provider_certification /
 * reinstate_provider_certification.
 *
 * Mirrors the Super Admin execution path in admin/manage_certificate.php: the
 * provider accreditation certificate is moved to the requested status, the
 * structured audit event is written, and the provider is notified by email.
 *
 * @param array<string,mixed> $request
 */
function admin_approval_provider_certification(PDO $pdo, array $request, string $decision, string $note, string $mode): void
{
    admin_ensure_action_request_schema($pdo);
    $requestId = (int) ($request['id'] ?? 0);
    $providerId = (int) ($request['target_id'] ?? 0);
    $ref = trim((string) ($request['target_key'] ?? ''));
    $reviewedBy = admin_approval_reviewer_id($pdo);
    $reason = trim((string) ($request['reason'] ?? ''));
    $payload = json_decode((string) ($request['payload_json'] ?? ''), true);
    $payload = is_array($payload) ? $payload : [];

    if ($decision === 'reject') {
        admin_approval_mark_reviewed($pdo, $requestId, $decision, $note, $reviewedBy, 'Rejected by Super Admin.');
        admin_approval_audit($pdo, 'provider_certification_rejected', 'Rejected ' . str_replace('_', ' ', $mode) . ' request for certificate ' . ($ref !== '' ? $ref : '#' . $providerId) . '.');
        return;
    }

    if ($mode === 'revoke') {
        $newStatus = 'revoked';
        $auditAction = 'certificate_revoked';
        $revokedAt = 'NOW()';
        $revokedReason = $reason;
    } elseif ($mode === 'suspend') {
        $newStatus = 'suspended';
        $auditAction = 'certificate_suspended';
        $revokedAt = 'NULL';
        $revokedReason = $reason;
    } else {
        $newStatus = 'issued';
        $auditAction = 'certificate_reinstated';
        $revokedAt = 'NULL';
        $revokedReason = null;
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE provider_accreditation_certificates SET status = ?, revoked_at = {$revokedAt}, revoked_reason = ? WHERE certificate_ref = ?")
            ->execute([$newStatus, $revokedReason, $ref]);
        admin_approval_mark_reviewed($pdo, $requestId, $decision, $note, $reviewedBy, 'Approved by Super Admin.');
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    if (function_exists('admin_audit_event')) {
        admin_audit_event(
            $pdo,
            $auditAction,
            'provider_registry',
            $providerId,
            [],
            ['status' => $newStatus],
            'success',
            sprintf('%s: %s (ref=%s)%s', $auditAction, $reason ?: '-', $ref, PHP_SAPI === 'cli' ? ' (cli)' : '')
        );
    }

    // Notify the provider when the change was applied (best-effort, as in
    // admin/manage_certificate.php).
    $providerRow = null;
    if ($providerId > 0 && app_table_exists($pdo, 'provider_registry')) {
        $providerStmt = $pdo->prepare('SELECT * FROM provider_registry WHERE id = ?' . (app_column_exists($pdo, 'provider_registry', 'deleted_at') ? ' AND deleted_at IS NULL' : '') . ' LIMIT 1');
        $providerStmt->execute([$providerId]);
        $providerRow = $providerStmt->fetch(PDO::FETCH_ASSOC);
    }
    if ($providerRow && !empty($providerRow['email']) && function_exists('app_send_mail')) {
        $to = (string) $providerRow['email'];
        $subject = 'NATCODEV: Your accreditation certificate status changed';
        $plain = "Hello " . (($providerRow['contact_person'] ?? '') ?: '') . ",\n\nYour accreditation certificate ({$ref}) status has been changed to: " . ucfirst($newStatus) . ".\n\nReason: " . ($reason ?: 'No reason provided') . "\n\nIf you have questions contact support.\n";
        try {
            app_send_mail($to, $subject, $plain, nl2br(htmlspecialchars($plain)));
        } catch (Throwable $e) {
            // Ignore email errors.
        }
    }
}

/**
 * Register the platform's built-in approval handlers. Idempotent; safe to call
 * from any bootstrap that loads this file so admin_apply_approval() works for
 * every request type the platform enqueues.
 */
function admin_register_core_approval_handlers(): void
{
    admin_register_approval_handler('revoke_certificate', 'admin_approval_revoke_certificate');
    admin_register_approval_handler('restore_certificate', 'admin_approval_restore_certificate');
    admin_register_approval_handler('wallet_credit', 'admin_approval_wallet_credit');
    admin_register_approval_handler('wallet_debit', 'admin_approval_wallet_debit');
    admin_register_approval_handler('withdrawal_approval', 'admin_approval_withdrawal');
    admin_register_approval_handler('payout_approval', 'admin_approval_payout');
    admin_register_approval_handler('refund_approval', static function (PDO $pdo, array $request, string $decision, string $note): void {
        if (!function_exists('admin_approval_marketplace_refund')) {
            require_once __DIR__ . '/marketplace-refunds.php';
        }
        admin_approval_marketplace_refund($pdo, $request, $decision, $note);
    });
    admin_register_approval_handler('revoke_provider_certification', static function (PDO $pdo, array $request, string $decision, string $note): void {
        admin_approval_provider_certification($pdo, $request, $decision, $note, 'revoke');
    });
    admin_register_approval_handler('suspend_provider_certification', static function (PDO $pdo, array $request, string $decision, string $note): void {
        admin_approval_provider_certification($pdo, $request, $decision, $note, 'suspend');
    });
    admin_register_approval_handler('reinstate_provider_certification', static function (PDO $pdo, array $request, string $decision, string $note): void {
        admin_approval_provider_certification($pdo, $request, $decision, $note, 'reinstate');
    });
}

admin_register_core_approval_handlers();
