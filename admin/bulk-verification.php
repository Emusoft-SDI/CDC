<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../lib/admin-layout.php';

$pdo = db();
admin_ensure_schema($pdo);
admin_require($pdo);

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid security token.';
    } else {
        $ids = array_values(array_filter(array_map('intval', $_POST['doc_ids'] ?? [])));
        $action = (string) ($_POST['bulk_action'] ?? '');
        $reason = trim((string) ($_POST['rejection_reason'] ?? ''));

        if (!$ids) {
            $error = 'Select at least one document.';
        } elseif ($action === 'reject' && $reason === '') {
            $error = 'Provide a rejection reason.';
        } elseif (in_array($action, ['verify', 'reject'], true)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            if ($action === 'verify') {
                $params = array_merge([$_SESSION['user_id'] ?? null], $ids);
                $pdo->prepare("UPDATE document_requirements SET verification_status = 'verified', verified = 1, verified_at = NOW(), verified_by = ? WHERE id IN ({$placeholders})")->execute($params);
                $message = count($ids) . ' document(s) verified.';
            } else {
                $params = array_merge([$reason, $_SESSION['user_id'] ?? null], $ids);
                $pdo->prepare("UPDATE document_requirements SET verification_status = 'rejected', verified = 0, verification_notes = ?, verified_by = ? WHERE id IN ({$placeholders})")->execute($params);
                $message = count($ids) . ' document(s) rejected.';
            }
        }
    }
}

$filters = [
    'status' => preg_replace('/[^a-z_]/i', '', (string) ($_GET['status'] ?? 'pending')),
    'role' => preg_replace('/[^a-z_]/i', '', (string) ($_GET['role'] ?? 'all')),
];
$params = [];
$where = ['dr.deleted_at IS NULL'];
if ($filters['status'] !== 'all') {
    $where[] = 'dr.verification_status = ?';
    $params[] = $filters['status'];
}
if ($filters['role'] !== 'all') {
    $where[] = 'u.role = ?';
    $params[] = $filters['role'];
}

$stmt = $pdo->prepare("
    SELECT dr.*, u.name, u.email, u.role
    FROM document_requirements dr
    JOIN users u ON dr.user_id = u.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY dr.uploaded_at DESC
    LIMIT 150
");
$stmt->execute($params);
$documents = $stmt->fetchAll();

$bulkTotal = count($documents);
$bulkPending = count(array_filter($documents, static fn($d): bool => strtolower((string) $d['verification_status']) === 'pending'));
$bulkVerified = count(array_filter($documents, static fn($d): bool => strtolower((string) $d['verification_status']) === 'verified'));
$bulkRejected = count(array_filter($documents, static fn($d): bool => strtolower((string) $d['verification_status']) === 'rejected'));
$bulkInvalid = count(array_filter($documents, static fn($d): bool => strtolower((string) ($d['api_validation_status'] ?? '')) === 'invalid'));

admin_page_start('Bulk Review', [
    'active' => 'bulk-verification.php',
    'description' => 'Filter and verify multiple document requirements in a controlled batch.',
    'wide' => true,
]);
?>
<?php if ($message): ?><div class="notice ok"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>

<?= admin_kpi_grid([
    ['In Review', number_format($bulkTotal), 'Matching documents', 'fa-file-circle-check', ''],
    ['Pending', number_format($bulkPending), 'Awaiting decision', 'fa-clock', 'orange'],
    ['Verified', number_format($bulkVerified), 'Approved', 'fa-circle-check', 'blue'],
    ['Rejected', number_format($bulkRejected), 'Declined', 'fa-circle-xmark', 'red'],
    ['API Invalid', number_format($bulkInvalid), 'Failed validation', 'fa-triangle-exclamation', 'purple'],
]) ?>

<section class="panel">
  <div class="user-toolbar">
    <form class="toolbar" method="get" style="margin:0">
      <select name="status">
        <?php foreach (['all' => 'All Statuses', 'pending' => 'Pending', 'verified' => 'Verified', 'rejected' => 'Rejected'] as $value => $label): ?>
          <option value="<?= e($value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="role">
        <?php foreach (['all' => 'All Roles', 'grower' => 'Growers', 'field_agent' => 'Field Agents', 'admin' => 'Admins'] as $value => $label): ?>
          <option value="<?= e($value) ?>" <?= $filters['role'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit"><i class="fas fa-filter"></i> Filter</button>
    </form>
    <span class="meta"><?= number_format($bulkTotal) ?> document(s)</span>
  </div>

  <form method="post">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <div class="record-list">
      <?php foreach ($documents as $doc): ?>
        <?php
          $verifyStatus = strtolower((string) $doc['verification_status']);
          $verifyTone = ['verified' => 'ok', 'rejected' => 'bad', 'pending' => 'warn'][$verifyStatus] ?? 'muted';
          $apiStatus = strtolower((string) ($doc['api_validation_status'] ?? 'pending'));
          $apiTone = ['valid' => 'ok', 'invalid' => 'bad', 'pending' => 'warn'][$apiStatus] ?? 'muted';
        ?>
        <label class="record-row">
          <span class="record-avatar file"><input type="checkbox" class="record-check doc-checkbox" name="doc_ids[]" value="<?= (int) $doc['id'] ?>"></span>
          <div class="record-main">
            <div class="record-title">
              <?= e($doc['name']) ?>
              <span class="tag <?= e($verifyTone) ?>"><?= e(ucwords($verifyStatus)) ?></span>
              <span class="tag <?= e($apiTone) ?>"><i class="fas fa-shield-halved"></i> API: <?= e($apiStatus) ?></span>
            </div>
            <div class="record-contact">
              <span><i class="far fa-envelope"></i><?= e($doc['email']) ?></span>
              <span><i class="fas fa-id-card"></i><?= e(ucfirst(str_replace('_', ' ', (string) $doc['document_type']))) ?></span>
              <?php if (!empty($doc['document_number'])): ?><span><i class="fas fa-hashtag"></i><?= e($doc['document_number']) ?></span><?php endif; ?>
            </div>
          </div>
          <div class="record-actions">
            <?php if (!empty($doc['file_path'])): ?>
              <a class="button secondary sm" href="../<?= e(ltrim((string) $doc['file_path'], '/')) ?>" target="_blank" rel="noopener"><i class="fas fa-up-right-from-square"></i> View</a>
            <?php endif; ?>
          </div>
        </label>
      <?php endforeach; ?>
      <?php if (!$documents): ?><div class="record-empty">No documents match this filter.</div><?php endif; ?>
    </div>

    <div class="actions" style="margin-top:16px;border-top:1px solid #eef2f4;padding-top:16px">
      <label style="display:inline-flex;align-items:center;gap:8px;font-weight:800"><input type="checkbox" id="selectAll"> Select all</label>
      <select name="bulk_action" required>
        <option value="">Select Action</option>
        <option value="verify">Verify Selected</option>
        <option value="reject">Reject Selected</option>
      </select>
      <input type="text" name="rejection_reason" placeholder="Rejection reason">
      <button type="submit"><i class="fas fa-check-double"></i> Apply to selected</button>
    </div>
  </form>
</section>
<script>
document.getElementById('selectAll').addEventListener('change', event => {
  document.querySelectorAll('.doc-checkbox').forEach(box => { box.checked = event.target.checked; });
});
</script>
<?php admin_page_end(); ?>
