<?php
// api/bulk-verify.php
session_start();
header('Content-Type: application/json');

$action = $_POST['bulk_action'] ?? '';
$docIds = $_POST['doc_ids'] ?? [];
$reason = trim($_POST['rejection_reason'] ?? '');

if (empty($docIds) || !$action) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

foreach ($docIds as $docId) {
    if ($action === 'verify') {
        $pdo->prepare("
            UPDATE document_requirements 
            SET verification_status = 'verified', 
                verified_at = NOW(), 
                verified_by = ?
            WHERE id = ?
        ")->execute([$_SESSION['user_id'], $docId]);
    } elseif ($action === 'reject') {
        if (empty($reason)) {
            continue; // Skip if no reason
        }
        $pdo->prepare("
            UPDATE document_requirements 
            SET verification_status = 'rejected', 
                verification_notes = ?,
                verified_by = ?
            WHERE id = ?
        ")->execute([$reason, $_SESSION['user_id'], $docId]);
    }
}

// Auto-generate certificates for verified users
if ($action === 'verify') {
    foreach ($docIds as $docId) {
        $stmt = $pdo->prepare("SELECT user_id FROM document_requirements WHERE id = ?");
        $stmt->execute([$docId]);
        $userId = $stmt->fetchColumn();
        
        if ($userId && canIssueCertificate($userId, $pdo)) {
            $appStmt = $pdo->prepare("SELECT application_id FROM users WHERE id = ?");
            $appStmt->execute([$userId]);
            $appId = $appStmt->fetchColumn();
            
            if ($appId) {
                generateCertificate($appId, $userId, $pdo);
            }
        }
    }
}

echo json_encode(['success' => true]);
?>