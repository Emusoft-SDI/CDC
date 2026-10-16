<?php
declare(strict_types=1);
require_once __DIR__ . '/_field.php';

$pdo = fa_pdo();
$user = fa_require_user($pdo);
$user = fa_require_workspace_role($pdo, 'field_agent');

$hasSoftDelete = app_column_exists($pdo, 'document_requirements', 'deleted_at');
$docSoft = $hasSoftDelete ? ' AND dr.deleted_at IS NULL' : '';
$updateSoft = $hasSoftDelete ? ' AND deleted_at IS NULL' : '';

$growerIds = [];
if (app_table_exists($pdo, 'agronomist_assignments')) {
    $assignedGrowers = $pdo->prepare("
        SELECT u.id FROM users u
        JOIN agronomist_assignments aa ON u.id = aa.grower_id
        WHERE aa.agronomist_id = ? AND aa.status = 'active'
    ");
    $assignedGrowers->execute([(int) $user['id']]);
    $growerIds = array_map('intval', $assignedGrowers->fetchAll(PDO::FETCH_COLUMN));
}
if (app_table_exists($pdo, 'agent_assignments')) {
    $directGrowers = $pdo->prepare('SELECT grower_id FROM agent_assignments WHERE agent_id = ?');
    $directGrowers->execute([(int) $user['id']]);
    $growerIds = array_values(array_unique(array_merge($growerIds, array_map('intval', $directGrowers->fetchAll(PDO::FETCH_COLUMN)))));
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        http_response_code(403);
        exit('Invalid security token.');
    }
    if ((string) ($_POST['bulk_action'] ?? '') !== 'verify') {
        http_response_code(400);
        exit('Invalid bulk action.');
    }
    $docIds = array_values(array_filter(array_map('intval', (array) ($_POST['doc_ids'] ?? []))));
    if (!$docIds) {
        http_response_code(400);
        exit('Select at least one document.');
    }
    if ($growerIds !== []) {
        $growerPlaceholders = implode(',', array_fill(0, count($growerIds), '?'));
        $update = $pdo->prepare("
            UPDATE document_requirements
            SET verification_status = 'verified', verified = 1, verified_by = ?, verified_at = NOW()
            WHERE id = ? AND user_id IN ({$growerPlaceholders}) AND verification_status = 'pending'{$updateSoft}
        ");
        if (is_file(__DIR__ . '/../lib/admin-audit-v2.php')) {
            require_once __DIR__ . '/../lib/admin-audit-v2.php';
        }
        $verified = 0;
        foreach ($docIds as $docId) {
            $update->execute(array_merge([(int) $user['id'], $docId], $growerIds));
            $verified += $update->rowCount();
            if (function_exists('admin_audit_event')) {
                admin_audit_event($pdo, 'bulk_verify_documents', 'document_requirements', $docId, [], ['verified' => true], 'success', 'Field agent bulk verified document ' . $docId);
            }
        }
        $message = $verified . ' document(s) verified.';
    }
}

$documents = [];
if ($growerIds !== []) {
    $placeholders = str_repeat('?,', count($growerIds) - 1) . '?';
    $stmt = $pdo->prepare("
        SELECT dr.*, u.name, u.email
        FROM document_requirements dr
        JOIN users u ON dr.user_id = u.id
        WHERE u.id IN ({$placeholders}) AND dr.verification_status = 'pending'{$docSoft}
        ORDER BY dr.uploaded_at DESC
        LIMIT 150
    ");
    $stmt->execute($growerIds);
    $documents = $stmt->fetchAll();
}

fa_header('Bulk Verification', 'Verify pending documents for your assigned growers.', $user, 'verification');
?>
<section class="fa-card fa-panel span-12">
  <div class="fa-panel-head"><h2>Bulk Verification - Assigned Growers</h2><span class="badge good"><?= count($documents) ?> pending</span></div>
  <?php if ($message): ?><div class="fa-card fa-panel" style="border-color:#bfe8cf;color:#0f6b3c"><?= e($message) ?></div><?php endif; ?>
  <?php if ($growerIds === []): ?>
    <div class="empty">No assigned growers found. Contact your administrator to assign growers to your account.</div>
  <?php elseif (!$documents): ?>
    <div class="empty">No pending documents for your assigned growers.</div>
  <?php else: ?>
  <form method="post">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="bulk_action" value="verify">
    <table>
      <thead>
        <tr><th></th><th>Grower</th><th>Email</th><th>Document</th><th>Number</th><th>Status</th></tr>
      </thead>
      <tbody>
        <?php foreach ($documents as $doc): ?>
        <tr>
          <td><input type="checkbox" name="doc_ids[]" value="<?= (int) $doc['id'] ?>"></td>
          <td><?= e((string) $doc['name']) ?></td>
          <td><?= e((string) $doc['email']) ?></td>
          <td><?= e(ucfirst(str_replace('_', ' ', (string) $doc['document_type']))) ?></td>
          <td><?= e((string) ($doc['document_number'] ?? '')) ?></td>
          <td><span class="badge warn"><?= e(ucwords(strtolower((string) $doc['verification_status']))) ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <div class="field-grid">
      <label style="display:inline-flex;align-items:center;gap:8px;font-weight:800"><input type="checkbox" id="selectAll"> Select all</label>
      <button class="btn" type="submit"><i data-lucide="check-circle"></i> Verify Selected</button>
    </div>
  </form>
  <script>document.getElementById('selectAll').addEventListener('change', event => { document.querySelectorAll('input[name="doc_ids[]"]').forEach(box => { box.checked = event.target.checked; }); });</script>
  <?php endif; ?>
</section>
<?php fa_footer(); ?>
