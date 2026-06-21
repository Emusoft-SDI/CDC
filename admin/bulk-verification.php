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
$where = ['1=1'];
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

admin_page_start('Bulk Review', [
    'active' => 'bulk-verification.php',
    'description' => 'Filter and verify multiple document requirements in a controlled batch.',
    'wide' => true,
]);
?>
<?php if ($message): ?><div class="notice ok"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>

<form class="toolbar panel" method="get">
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
  <button type="submit">Filter</button>
</form>

<form method="post" class="panel">
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
  <table>
    <thead><tr><th><input type="checkbox" id="selectAll"></th><th>User</th><th>Document</th><th>Number</th><th>API Status</th><th>Status</th><th>File</th></tr></thead>
    <tbody>
      <?php foreach ($documents as $doc): ?>
        <tr>
          <td><input type="checkbox" name="doc_ids[]" value="<?= (int) $doc['id'] ?>" class="doc-checkbox"></td>
          <td><?= e($doc['name']) ?><br><small><?= e($doc['email']) ?></small></td>
          <td><?= e(ucfirst(str_replace('_', ' ', (string) $doc['document_type']))) ?></td>
          <td><?= e($doc['document_number']) ?></td>
          <td><span class="badge <?= $doc['api_validation_status'] === 'valid' ? 'verified' : ($doc['api_validation_status'] === 'invalid' ? 'rejected' : 'pending') ?>"><?= e($doc['api_validation_status'] ?? 'pending') ?></span></td>
          <td><span class="badge <?= e((string) $doc['verification_status']) ?>"><?= e((string) $doc['verification_status']) ?></span></td>
          <td><?php if (!empty($doc['file_path'])): ?><a href="../<?= e(ltrim((string) $doc['file_path'], '/')) ?>" target="_blank">View</a><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$documents): ?><tr><td colspan="7">No documents match this filter.</td></tr><?php endif; ?>
    </tbody>
  </table>
  <div class="toolbar">
    <select name="bulk_action" required>
      <option value="">Select Action</option>
      <option value="verify">Verify Selected</option>
      <option value="reject">Reject Selected</option>
    </select>
    <input type="text" name="rejection_reason" placeholder="Rejection reason">
    <button type="submit">Apply</button>
  </div>
</form>
<script>
document.getElementById('selectAll').addEventListener('change', event => {
  document.querySelectorAll('.doc-checkbox').forEach(box => { box.checked = event.target.checked; });
});
</script>
<?php admin_page_end(); ?>
