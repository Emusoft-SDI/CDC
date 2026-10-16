<?php defined('NATCODEV_SUPER_ADMIN') || exit;

/**
 * Cooperative Oversight (plan §6).
 *
 * Super admin view of every cooperative that partners with
 * NATCODEV: member counts, enclave, contact, and partnership
 * status. Suspend / reactivate are CSRF-protected POST actions
 * dispatched from super-admin/index.php and audited via
 * admin_audit_event (entity "cooperative").
 *
 * The panel sits behind the user_management feature gate so the
 * role access matrix controls who can administer cooperatives;
 * roles without the feature get the branded 403 page.
 */

if (!admin_feature_is_allowed($pdo, 'user_management')) {
    app_render_error_page(403, 'Cooperative oversight is disabled for your role.');
}

cooperative_ensure_schema($pdo);

$search = trim((string) ($_GET['q'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? ''));
if (!in_array($statusFilter, ['', 'active', 'pending', 'suspended'], true)) {
    $statusFilter = '';
}
$perPage = super_admin_per_page(25);
$page = admin_current_page();
$offset = admin_pagination_offset($page, $perPage);

$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(c.name LIKE ? OR c.registration_number LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
if ($statusFilter !== '') {
    $where[] = 'c.status = ?';
    $params[] = $statusFilter;
}
$whereSql = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

$cooperativeTotals = $pdo->query("
    SELECT
      COUNT(*) AS total,
      SUM(status = 'active') AS active_total,
      SUM(status = 'pending') AS pending_total,
      SUM(status = 'suspended') AS suspended_total
    FROM cooperatives
")->fetch() ?: [];
$activeMemberTotal = (int) $pdo->query("SELECT COUNT(*) FROM cooperative_members WHERE status = 'active'")->fetchColumn();

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM cooperatives c {$whereSql}");
$countStmt->execute($params);
$totalCooperatives = (int) $countStmt->fetchColumn();

$cooperativesStmt = $pdo->prepare("
    SELECT c.*, ns.state_name,
        (SELECT COUNT(*) FROM cooperative_members cm WHERE cm.cooperative_id = c.id AND cm.status = 'active') AS member_count,
        (SELECT COUNT(*) FROM cooperative_members cm WHERE cm.cooperative_id = c.id AND cm.status = 'removed') AS removed_count,
        u.name AS creator_name
    FROM cooperatives c
    LEFT JOIN nigeria_states ns ON ns.id = c.state_id
    LEFT JOIN users u ON u.id = c.created_by
    {$whereSql}
    ORDER BY c.created_at DESC, c.id DESC
    LIMIT {$perPage} OFFSET {$offset}
");
$cooperativesStmt->execute($params);
$cooperatives = $cooperativesStmt->fetchAll();
?>
<section class="panel">
  <div class="section-head">
    <div>
      <h2>Cooperative Oversight</h2>
      <p>Every cooperative partnered with NATCODEV, its member count, enclave, and partnership status. Suspend a partnership while its dispute or review is resolved; reactivate when it is cleared.</p>
    </div>
    <div class="actions">
      <a class="button secondary" href="index.php">Back to Snapshot</a>
    </div>
  </div>

  <section class="stats">
    <div class="stat"><span>Cooperatives</span><strong><?= (int) ($cooperativeTotals['total'] ?? 0) ?></strong></div>
    <div class="stat"><span>Active</span><strong><?= (int) ($cooperativeTotals['active_total'] ?? 0) ?></strong></div>
    <div class="stat"><span>Pending</span><strong><?= (int) ($cooperativeTotals['pending_total'] ?? 0) ?></strong></div>
    <div class="stat"><span>Suspended</span><strong><?= (int) ($cooperativeTotals['suspended_total'] ?? 0) ?></strong></div>
    <div class="stat"><span>Active Members</span><strong><?= $activeMemberTotal ?></strong></div>
  </section>

  <form class="filters" method="get">
    <input type="hidden" name="view" value="cooperatives">
    <input name="q" value="<?= e($search) ?>" placeholder="Search name, registration number">
    <select name="status">
      <option value="">All statuses</option>
      <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
      <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
      <option value="suspended" <?= $statusFilter === 'suspended' ? 'selected' : '' ?>>Suspended</option>
    </select>
    <select name="per_page">
      <?php foreach (super_admin_per_page_options() as $size): ?>
        <option value="<?= $size ?>" <?= $perPage === $size ? 'selected' : '' ?>><?= $size ?> rows</option>
      <?php endforeach; ?>
    </select>
    <button type="submit" data-busy-text="Filtering...">Filter</button>
  </form>

  <?= super_admin_pagination_controls($totalCooperatives, $page, $perPage, ['view' => 'cooperatives', 'q' => $search, 'status' => $statusFilter]) ?>

  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Cooperative</th><th>State / LGA</th><th>Contact</th><th>Members</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
      <?php foreach ($cooperatives as $cooperative): ?>
        <?php
          $status = (string) ($cooperative['status'] ?: 'pending');
          $location = trim((string) ($cooperative['state_name'] ?: '') . ' / ' . (string) ($cooperative['lga'] ?: ''), ' /');
        ?>
        <tr>
          <td>
            <strong><?= e((string) $cooperative['name']) ?></strong>
            <?php if (!empty($cooperative['registration_number'])): ?><small>CAC <?= e((string) $cooperative['registration_number']) ?></small><?php endif; ?>
            <?php if (!empty($cooperative['creator_name'])): ?><small>Created by <?= e((string) $cooperative['creator_name']) ?></small><?php endif; ?>
          </td>
          <td>
            <?php if ($location !== ''): ?><?= e($location) ?><?php else: ?><small>No enclave recorded</small><?php endif; ?>
          </td>
          <td>
            <?php if (!empty($cooperative['contact_name'])): ?><strong><?= e((string) $cooperative['contact_name']) ?></strong><?php endif; ?>
            <?php if (!empty($cooperative['contact_phone'])): ?><small><?= e((string) $cooperative['contact_phone']) ?></small><?php endif; ?>
            <?php if (!empty($cooperative['contact_email'])): ?><small><?= e((string) $cooperative['contact_email']) ?></small><?php endif; ?>
            <?php if (empty($cooperative['contact_name']) && empty($cooperative['contact_phone']) && empty($cooperative['contact_email'])): ?><small>No contact recorded</small><?php endif; ?>
          </td>
          <td>
            <strong><?= (int) $cooperative['member_count'] ?></strong> active
            <?php if ((int) $cooperative['removed_count'] > 0): ?><small><?= (int) $cooperative['removed_count'] ?> removed</small><?php endif; ?>
          </td>
          <td>
            <span class="badge <?= $status === 'active' ? 'ok-badge' : ($status === 'suspended' ? 'warning' : 'muted-badge') ?>"><?= e(ucfirst($status)) ?></span>
            <small>Created <?= e(date('M j, Y', strtotime((string) $cooperative['created_at']))) ?></small>
          </td>
          <td>
            <?php if ($status === 'suspended'): ?>
              <form method="post" class="mini-form">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="cooperative_reactivate">
                <input type="hidden" name="cooperative_id" value="<?= (int) $cooperative['id'] ?>">
                <button type="submit" class="secondary" data-busy-text="Reactivating...">Reactivate</button>
              </form>
            <?php else: ?>
              <form method="post" class="mini-form danger-zone">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="cooperative_suspend">
                <input type="hidden" name="cooperative_id" value="<?= (int) $cooperative['id'] ?>">
                <button type="submit" class="danger" data-busy-text="Suspending..." onclick="return confirm('Suspend this cooperative partnership?')">Suspend</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$cooperatives): ?><tr><td colspan="6" class="empty">No cooperatives match this review.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

  <?= super_admin_pagination_controls($totalCooperatives, $page, $perPage, ['view' => 'cooperatives', 'q' => $search, 'status' => $statusFilter]) ?>
</section>
