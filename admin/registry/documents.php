<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/common.php';

$pageTitle = 'Document Verification - NATCODEV Registry';
$activeNav = 'documents';

$pendingDocs = rx_rows($pdo, "
    SELECT dr.*, u.name, u.email
    FROM document_requirements dr
    JOIN users u ON dr.user_id = u.id
    WHERE dr.verification_status = 'pending'
    ORDER BY dr.uploaded_at DESC
");

require __DIR__ . '/layout/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Document Verification</h1>
    <p class="page-subtitle"><?= count($pendingDocs) ?> documents awaiting manual review.</p>
  </div>
</div>

<form action="inc/actions.php" method="post" id="bulkDocForm">
  <input type="hidden" name="action" value="bulk_verify_documents">
  <input type="hidden" name="page" value="../documents.php">
  
  <div class="card">
    <div class="card-header">
      <div style="display:flex; gap:10px; align-items:center">
        <select name="bulk_status" class="form-select" style="width:auto">
          <option value="verified">Mark Selected as Verified</option>
          <option value="rejected">Mark Selected as Rejected</option>
        </select>
        <button type="submit" class="btn btn-primary btn-sm">Apply Bulk Action</button>
      </div>
    </div>
    <div class="card-body p0">
      <table>
        <thead>
          <tr>
            <th><input type="checkbox" onclick="document.querySelectorAll('.doc-check').forEach(c=>c.checked=this.checked)"></th>
            <th>Grower</th>
            <th>Document Type</th>
            <th>Reference #</th>
            <th>Uploaded</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pendingDocs as $doc): ?>
            <tr>
              <td><input type="checkbox" name="doc_ids[]" value="<?= $doc['id'] ?>" class="doc-check"></td>
              <td>
                <strong><?= rx_e($doc['name']) ?></strong><br>
                <small><?= rx_e($doc['email']) ?></small>
              </td>
              <td><?= rx_e(ucfirst(str_replace('_', ' ', $doc['document_type']))) ?></td>
              <td><?= rx_e($doc['document_number'] ?: '-') ?></td>
              <td><?= date('M j, Y', strtotime($doc['uploaded_at'])) ?></td>
              <td>
                <div style="display:flex; gap:5px">
                  <?php if($doc['file_path']): ?>
                    <a href="../<?= rx_e($doc['file_path']) ?>" target="_blank" class="btn btn-sm btn-secondary">View</a>
                  <?php endif; ?>
                  <button type="button" class="btn btn-sm btn-primary" onclick="openDocReviewModal(<?= $doc['id'] ?>, '<?= rx_e($doc['name']) ?>', '<?= rx_e($doc['document_type']) ?>')">Review</button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$pendingDocs): ?><tr><td colspan="6" style="text-align:center; padding:40px">No pending documents for review.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</form>

<!-- DOC REVIEW MODAL -->
<div class="modal-overlay" id="docReviewModal">
  <div class="modal">
    <div class="modal-header">
      <h3 class="card-title" style="margin:20px">Verify Document: <span id="docReviewType"></span></h3>
      <button class="btn-icon" onclick="closeModal('docReviewModal')" style="margin:15px">✕</button>
    </div>
    <form action="inc/actions.php" method="post">
      <input type="hidden" name="action" value="verify_document">
      <input type="hidden" name="document_id" id="docReviewId">
      <input type="hidden" name="page" value="../documents.php">
      <div class="card-body">
        <p>Reviewing document for: <strong id="docReviewName"></strong></p>
        <div class="form-group" style="margin-top:15px">
          <label class="form-label">Decision</label>
          <select name="status" class="form-select">
            <option value="verified">Verified - Document is valid</option>
            <option value="rejected">Rejected - Document is invalid/unclear</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Rejection Reason (if applicable)</label>
          <textarea name="notes" class="form-textarea" placeholder="Explain why the document was rejected..."></textarea>
        </div>
      </div>
      <div class="card-header" style="justify-content:flex-end">
        <button type="button" class="btn btn-secondary" onclick="closeModal('docReviewModal')">Cancel</button>
        <button type="submit" class="btn btn-primary" style="margin-left:10px">Save Decision</button>
      </div>
    </form>
  </div>
</div>

<script>
function openDocReviewModal(id, name, type) {
    document.getElementById('docReviewId').value = id;
    document.getElementById('docReviewName').textContent = name;
    document.getElementById('docReviewType').textContent = type.replace('_', ' ').toUpperCase();
    openModal('docReviewModal');
}
</script>

<?php require __DIR__ . '/layout/footer.php'; ?>
