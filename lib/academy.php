<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/academy/schema.php';
require_once __DIR__ . '/academy/courses.php';
require_once __DIR__ . '/academy/homepage.php';
require_once __DIR__ . '/academy/assessments.php';
require_once __DIR__ . '/academy/certificates.php';
require_once __DIR__ . '/academy/content-formatter.php';
require_once __DIR__ . '/academy/sponsorships.php';
require_once __DIR__ . '/academy/sponsorship-admin.php';
require_once __DIR__ . '/academy/sponsorship-views.php';

/**
 * Review an Academy refund request.
 *
 * Approving credits the learner's wallet through the idempotent
 * wallet_credit_once() path — the deterministic REFUND-{id}
 * reference means a repeated approval cannot pay twice. When dual
 * control is enabled and the refund is at or above the threshold,
 * the credit queues for Super Admin approval instead of moving
 * funds immediately.
 *
 * @return array{success: bool, status?: string, queued?: int, error?: string}
 */
function admin_process_academy_refund(PDO $pdo, int $refundId, string $status, string $adminNotes, int $adminId): array
{
    $status = in_array($status, ['pending', 'under_review', 'approved', 'paid', 'rejected', 'closed'], true) ? $status : 'pending';
    $refundId = max(0, $refundId);
    if ($refundId <= 0) {
        return ['success' => false, 'error' => 'Select a valid refund request.'];
    }
    $refundStmt = $pdo->prepare("SELECT r.user_id, r.amount, r.transaction_id FROM academy_refund_requests r WHERE r.id = ? LIMIT 1");
    $refundStmt->execute([$refundId]);
    $refund = $refundStmt->fetch();
    if (!$refund) {
        return ['success' => false, 'error' => 'Refund request was not found.'];
    }
    $amount = (float) $refund['amount'];
    $userId = (int) $refund['user_id'];

    if (in_array($status, ['approved', 'paid'], true)) {
        if (function_exists('wallet_dual_control_required') && wallet_dual_control_required($pdo, $amount)) {
            if (!function_exists('wallet_queue_credit_approval')) {
                return ['success' => false, 'error' => 'Dual control is enabled but the approval queue is unavailable.'];
            }
            $requestId = wallet_queue_credit_approval($pdo, $userId, $amount, $adminId, 'Academy refund #' . $refundId);
            $queueNote = trim($adminNotes);
            $queueNote .= ($queueNote !== '' ? ' ' : '') . '[Queued for Super Admin money approval: request #' . $requestId . ']';
            $pdo->prepare("UPDATE academy_refund_requests SET status = 'pending', admin_notes = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?")
                ->execute([$queueNote, $adminId, $refundId]);
            return ['success' => true, 'status' => 'pending', 'queued' => $requestId];
        }
        if (!function_exists('wallet_credit_once')) {
            return ['success' => false, 'error' => 'Wallet functions are unavailable.'];
        }
        $refundReference = 'REFUND-' . $refundId;
        wallet_credit_once(
            $pdo,
            $userId,
            $amount,
            $refundReference,
            'Academy refund (request #' . $refundId . ')',
            'academy_refund',
            $refundReference,
            ['refund_id' => $refundId, 'transaction_id' => (int) $refund['transaction_id']]
        );
    }

    $stmt = $pdo->prepare("UPDATE academy_refund_requests SET status = ?, admin_notes = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
    $stmt->execute([$status, trim($adminNotes), $adminId, $refundId]);
    return ['success' => true, 'status' => $status];
}

