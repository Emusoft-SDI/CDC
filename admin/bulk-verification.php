<!-- admin/bulk-verification.php -->
<?php
// Get documents for bulk verification
$filters = [
    'status' => $_GET['status'] ?? 'pending',
    'role' => $_GET['role'] ?? 'all',
    'state' => $_GET['state'] ?? null
];

$sql = "
    SELECT 
        dr.id, dr.document_type, dr.document_number, dr.file_path,
        dr.verification_status, dr.api_validation_status,
        u.name, u.email, u.role, u.id as user_id,
        s.state_name
    FROM document_requirements dr
    JOIN users u ON dr.user_id = u.id
    LEFT JOIN nigeria_states s ON u.state_id = s.id
    WHERE 1=1
";

$params = [];
if ($filters['status'] !== 'all') {
    $sql .= " AND dr.verification_status = ?";
    $params[] = $filters['status'];
}
if ($filters['role'] !== 'all') {
    $sql .= " AND u.role = ?";
    $params[] = $filters['role'];
}
if ($filters['state']) {
    $sql .= " AND s.state_name = ?";
    $params[] = $filters['state'];
}

$sql .= " ORDER BY dr.uploaded_at DESC LIMIT 100";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$documents = $stmt->fetchAll();
?>
<h2>Bulk Document Verification</h2>

<!-- Filters -->
<form method="GET" style="margin-bottom: 20px;">
  <select name="status">
    <option value="all" <?= $filters['status']==='all'?'selected':'' ?>>All Statuses</option>
    <option value="pending" <?= $filters['status']==='pending'?'selected':'' ?>>Pending</option>
    <option value="verified" <?= $filters['status']==='verified'?'selected':'' ?>>Verified</option>
    <option value="rejected" <?= $filters['status']==='rejected'?'selected':'' ?>>Rejected</option>
  </select>
  
  <select name="role">
    <option value="all" <?= $filters['role']==='all'?'selected':'' ?>>All Roles</option>
    <option value="grower" <?= $filters['role']==='grower'?'selected':'' ?>>Growers</option>
    <option value="field_agent" <?= $filters['role']==='field_agent'?'selected':'' ?>>Field Agents</option>
  </select>
  
  <input type="text" name="state" placeholder="State" value="<?= htmlspecialchars($filters['state'] ?? '') ?>">
  <button type="submit">Filter</button>
</form>

<!-- Bulk Verification Form -->
<form method="POST" id="bulkVerificationForm">
  <table>
    <thead>
      <tr>
        <th><input type="checkbox" id="selectAll"></th>
        <th>User</th>
        <th>Document</th>
        <th>Number</th>
        <th>API Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($documents as $doc): ?>
      <tr>
        <td><input type="checkbox" name="doc_ids[]" value="<?= $doc['id'] ?>" class="doc-checkbox"></td>
        <td><?= htmlspecialchars($doc['name']) ?><br><small><?= htmlspecialchars($doc['email']) ?></small></td>
        <td><?= ucfirst(str_replace('_', ' ', $doc['document_type'])) ?></td>
        <td><?= htmlspecialchars($doc['document_number']) ?></td>
        <td>
          <?php if ($doc['api_validation_status'] === 'valid'): ?>
            <span style="color:green;">✅ Valid</span>
          <?php elseif ($doc['api_validation_status'] === 'invalid'): ?>
            <span style="color:red;">❌ Invalid</span>
          <?php elseif ($doc['api_validation_status'] === 'pending'): ?>
            <span style="color:orange;">⏳ Pending</span>
          <?php endif; ?>
        </td>
        <td>
          <a href="/documents/<?= basename($doc['file_path']) ?>" target="_blank">View</a>
          <button type="button" onclick="quickVerify(<?= $doc['id'] ?>)">Quick Verify</button>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  
  <div style="margin-top: 20px;">
    <select name="bulk_action" required>
      <option value="">Select Bulk Action</option>
      <option value="verify">Verify Selected</option>
      <option value="reject">Reject Selected</option>
    </select>
    <input type="text" name="rejection_reason" placeholder="Rejection reason (if rejecting)" style="margin-left: 10px;">
    <button type="submit">Apply Bulk Action</button>
  </div>
</form>

<script>
// Select all checkboxes
document.getElementById('selectAll').addEventListener('change', function() {
  const checkboxes = document.querySelectorAll('.doc-checkbox');
  checkboxes.forEach(cb => cb.checked = this.checked);
});

// Quick verify single document
function quickVerify(docId) {
  if (confirm('Verify this document?')) {
    fetch('/api/quick-verify.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ doc_id: docId })
    }).then(response => {
      if (response.ok) {
        location.reload();
      }
    });
  }
}

// Handle bulk form submission
document.getElementById('bulkVerificationForm').addEventListener('submit', function(e) {
  const action = this.querySelector('[name="bulk_action"]').value;
  if (action === 'reject' && !this.querySelector('[name="rejection_reason"]').value) {
    e.preventDefault();
    alert('Please provide a rejection reason.');
  }
});
</script>