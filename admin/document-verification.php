<!-- admin/document-verification.php -->
<?php
// Get pending verifications
$stmt = $pdo->prepare("
    SELECT dr.*, u.name, u.email, u.role
    FROM document_requirements dr
    JOIN users u ON dr.user_id = u.id
    WHERE dr.verification_status = 'pending'
    ORDER BY dr.uploaded_at DESC
");
$stmt->execute();
$pendingDocs = $stmt->fetchAll();
?>
<h2>Document Verification Queue</h2>

<table>
  <tr>
    <th>User</th>
    <th>Document Type</th>
    <th>Document Number</th>
    <th>Uploaded</th>
    <th>Actions</th>
  </tr>
  
  <?php foreach ($pendingDocs as $doc): ?>
  <tr>
    <td><?= htmlspecialchars($doc['name']) ?><br><small><?= htmlspecialchars($doc['email']) ?></small></td>
    <td><?= ucfirst(str_replace('_', ' ', $doc['document_type'])) ?></td>
    <td><?= htmlspecialchars($doc['document_number']) ?></td>
    <td><?= date('M j, Y', strtotime($doc['uploaded_at'])) ?></td>
    <td>
      <a href="/documents/<?= basename($doc['file_path']) ?>" target="_blank">View Document</a>
      <form method="POST" style="display:inline;">
        <input type="hidden" name="doc_id" value="<?= $doc['id'] ?>">
        <select name="action">
          <option value="verify">Verify</option>
          <option value="reject">Reject</option>
        </select>
        <input type="text" name="notes" placeholder="Rejection reason (if rejecting)">
        <button type="submit">Submit</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
</table>

<?php
// Handle verification actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $docId = intval($_POST['doc_id']);
    $action = $_POST['action'];
    $notes = trim($_POST['notes'] ?? '');
    
    if ($action === 'verify') {
        $pdo->prepare("
            UPDATE document_requirements 
            SET verification_status = 'verified', verified_at = NOW(), verified_by = ?
            WHERE id = ?
        ")->execute([$_SESSION['user_id'], $docId]);
    } elseif ($action === 'reject') {
        if (empty($notes)) {
            $error = "Please provide a rejection reason.";
        } else {
            $pdo->prepare("
                UPDATE document_requirements 
                SET verification_status = 'rejected', verification_notes = ?, verified_by = ?
                WHERE id = ?
            ")->execute([$notes, $_SESSION['user_id'], $docId]);
        }
    }
    
    if (!$error) {
        // Auto-generate certificate if all documents verified
        $stmt = $pdo->prepare("SELECT user_id FROM document_requirements WHERE id = ?");
        $stmt->execute([$docId]);
        $userId = $stmt->fetchColumn();
        
        if (canIssueCertificate($userId, $pdo)) {
            // Get application ID
            $appStmt = $pdo->prepare("SELECT application_id FROM users WHERE id = ?");
            $appStmt->execute([$userId]);
            $appId = $appStmt->fetchColumn();
            
            generateCertificate($appId, $userId, $pdo);
        }
    }
}
?>