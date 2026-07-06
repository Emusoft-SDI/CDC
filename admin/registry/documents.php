<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/common.php';

$pageTitle = 'Document Verification - NATCODEV Registry';
$activeNav = 'documents';
$page = max(1, (int) ($_GET['p'] ?? 1));
$limit = rx_per_page();
$offset = ($page - 1) * $limit;
$search = trim((string) ($_GET['search'] ?? ''));
$documentType = trim((string) ($_GET['document_type'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$where = "1=1";
$params = [];
if ($search !== '') {
    $where .= ' AND (u.name LIKE ? OR u.email LIKE ? OR dr.document_number LIKE ? OR dr.document_type LIKE ?)';
    $term = '%' . $search . '%';
    $params = [$term, $term, $term, $term];
}
if ($documentType !== '') {
    $where .= ' AND dr.document_type = ?';
    $params[] = $documentType;
}
if ($statusFilter !== '') {
    $where .= ' AND dr.verification_status = ?';
    $params[] = $statusFilter;
}

$totalDocs = rx_scalar($pdo, "SELECT COUNT(*) FROM document_requirements dr JOIN users u ON dr.user_id=u.id WHERE {$where}", $params);

$pendingDocs = rx_rows($pdo, "
    SELECT dr.*, u.name, u.email
    FROM document_requirements dr
    JOIN users u ON dr.user_id = u.id
    WHERE {$where}
    ORDER BY dr.uploaded_at DESC
    LIMIT {$limit} OFFSET {$offset}
", $params);

$statusCounts = rx_rows($pdo, "SELECT verification_status, COUNT(*) count FROM document_requirements GROUP BY verification_status");

require __DIR__ . '/layout/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Document Verification</h1>
    <p class="page-subtitle">Global registry truth covering identity, land, and document checks for grower onboarding.</p>
  </div>
</div>

<div class="stats-grid" style="margin-bottom:24px;">
  <div class="stat-card"><div class="stat-card-label">Total documents</div><div class="stat-card-value"><?= number_format(array_sum(array_column($statusCounts, 'count'))) ?></div></div>
  <?php foreach ($statusCounts as $count): ?>
    <div class="stat-card"><div class="stat-card-label"><?= rx_e(ucwords(str_replace('_', ' ', $count['verification_status']))) ?></div><div class="stat-card-value"><?= number_format($count['count']) ?></div></div>
  <?php endforeach; ?>
</div>

<div class="card"><div class="card-header"><form method="get" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center"><input class="form-input" name="search" value="<?= rx_e($search) ?>" placeholder="Search grower, email, number, type" style="max-width:320px"><select class="form-select" name="document_type" style="width:auto"><option value="">All types</option><?php foreach(['nin','bvn','land_title','id_card','farm_photo'] as $type): ?><option value="<?= $type ?>" <?= $documentType === $type ? 'selected' : '' ?>><?= rx_e(ucwords(str_replace('_', ' ', $type))) ?></option><?php endforeach; ?></select><select class="form-select" name="status" style="width:auto"><option value="">All statuses</option><option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option><option value="verified" <?= $statusFilter === 'verified' ? 'selected' : '' ?>>Verified</option><option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option><option value="needs_review" <?= $statusFilter === 'needs_review' ? 'selected' : '' ?>>Needs Review</option></select><input type="hidden" name="per_page" value="<?= $limit ?>"><button class="btn btn-secondary">Filter</button><a class="btn btn-secondary" href="documents.php">Reset</a></form></div></div>

<form action="inc/actions.php" method="post" id="bulkDocForm">
  <input type="hidden" name="_csrf" value="<?= rx_e(csrf_token()) ?>">
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
            <th>Status</th>
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
              <td><span class="status-badge <?= rx_status_class($doc['verification_status']) ?>"><?= rx_e(ucfirst(str_replace('_', ' ', $doc['verification_status']))) ?></span></td>
              <td>
                <div style="display:flex; gap:5px; flex-wrap:wrap">
                  <?php if ($doc['file_path']): ?>
                    <a href="../../<?= rx_e(ltrim((string) $doc['file_path'], '/')) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-secondary">View</a>
                  <?php endif; ?>
                  <button type="button" class="btn btn-sm btn-primary" onclick='openDocReviewModal(<?= (int) $doc['id'] ?>, <?= json_encode((string) $doc['name'], JSON_HEX_APOS | JSON_HEX_QUOT) ?>, <?= json_encode((string) $doc['document_type'], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Review</button>
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
<?=rx_pagination_links($totalDocs,$limit,$page,'documents.php')?>

<!-- DOC REVIEW MODAL -->
<div class="modal-overlay" id="docReviewModal">
  <div class="modal">
    <div class="modal-header">
      <h3 class="card-title" style="margin:20px">Verify Document: <span id="docReviewType"></span></h3>
      <button class="btn-icon" onclick="closeModal('docReviewModal')" style="margin:15px">✕</button>
    </div>
    <form action="inc/actions.php" method="post">
      <input type="hidden" name="_csrf" value="<?= rx_e(csrf_token()) ?>">
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
    document.getElementById('docReviewType').textContent = type.replace(/_/g, ' ').toUpperCase();
    openModal('docReviewModal');
}
</script>

<?php require __DIR__ . '/layout/footer.php'; ?>
