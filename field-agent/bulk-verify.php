<!-- field-agent/bulk-verify.php -->
<?php
// Field agents can only verify their assigned growers
$assignedGrowers = $pdo->prepare("
    SELECT u.id FROM users u
    JOIN agronomist_assignments aa ON u.id = aa.grower_id
    WHERE aa.agronomist_id = ? AND aa.status = 'active'
");
$assignedGrowers->execute([$_SESSION['user_id']]);
$growerIds = $assignedGrowers->fetchAll(PDO::FETCH_COLUMN);

if (empty($growerIds)) {
    die("No assigned growers found.");
}

// Get documents for assigned growers only
$placeholders = str_repeat('?,', count($growerIds) - 1) . '?';
$stmt = $pdo->prepare("
    SELECT dr.*, u.name, u.email
    FROM document_requirements dr
    JOIN users u ON dr.user_id = u.id
    WHERE u.id IN ($placeholders) AND dr.verification_status = 'pending'
    ORDER BY dr.uploaded_at DESC
");
$stmt->execute($growerIds);
$documents = $stmt->fetchAll();
?>
<h2>Bulk Verification - Assigned Growers</h2>
<p>You can verify documents for your assigned growers below.</p>

<!-- Same table structure as admin, but limited to assigned growers -->
<table>
  <!-- ... same columns as admin version ... -->
</table>

<!-- Field agents can only verify, not reject -->
<form method="POST">
  <?php foreach ($documents as $doc): ?>
    <input type="hidden" name="doc_ids[]" value="<?= $doc['id'] ?>">
  <?php endforeach; ?>
  <input type="hidden" name="bulk_action" value="verify">
  <button type="submit">Verify All Documents</button>
</form>