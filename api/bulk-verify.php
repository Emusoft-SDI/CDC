<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/certificates.php';

session_start();
$pdo = db();

if (!admin_session_is_authenticated($pdo)) {
    json_response(['success' => false, 'error' => 'Forbidden'], 403);
}

$action = (string) ($_POST['bulk_action'] ?? '');
$docIds = array_map('intval', (array) ($_POST['doc_ids'] ?? []));
$reason = trim((string) ($_POST['rejection_reason'] ?? ''));

if (!$docIds || !in_array($action, ['verify', 'reject'], true)) {
    json_response(['success' => false, 'error' => 'Invalid request'], 400);
}
if ($action === 'reject' && $reason === '') {
    json_response(['success' => false, 'error' => 'Rejection reason is required'], 422);
}

try {
    $issued = 0;
    foreach ($docIds as $docId) {
        if ($action === 'verify') {
            $pdo->prepare("
                UPDATE document_requirements
                SET verification_status = 'verified', verified = 1, verified_at = NOW(), verified_by = ?
                WHERE id = ?
            ")->execute([$_SESSION['user_id'] ?? null, $docId]);
        } else {
            $pdo->prepare("
                UPDATE document_requirements
                SET verification_status = 'rejected', verified = 0, verification_notes = ?, verified_by = ?
                WHERE id = ?
            ")->execute([$reason, $_SESSION['user_id'] ?? null, $docId]);
        }

        if ($action === 'verify') {
            $stmt = $pdo->prepare("SELECT user_id FROM document_requirements WHERE id = ?");
            $stmt->execute([$docId]);
            $userId = (int) $stmt->fetchColumn();
            if ($userId > 0 && canIssueCertificate($userId, $pdo)) {
                $appStmt = $pdo->prepare("SELECT application_id FROM users WHERE id = ?");
                $appStmt->execute([$userId]);
                $appId = (int) $appStmt->fetchColumn();
                if ($appId > 0) {
                    generateCertificate($appId, $userId, $pdo);
                    $issued++;
                }
            }
        }
    }

    json_response(['success' => true, 'certificates_issued' => $issued]);
} catch (Throwable $e) {
    error_log('Bulk verification error: ' . $e->getMessage());
    json_response(['success' => false, 'error' => 'Bulk verification failed'], 500);
}
