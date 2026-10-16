<?php
declare(strict_types=1);
require_once __DIR__ . '/_cooperative.php';

$pdo = coop_pdo();
$user = coop_require($pdo);
$cooperative = cooperative_current($pdo);
$cooperativeId = (int) ($cooperative['id'] ?? 0);
$cooperativeName = (string) ($cooperative['name'] ?? 'Your Cooperative');

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf((string) ($_POST['_csrf'] ?? null))) {
        $error = 'Security session expired. Refresh and try again.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        $targetUserId = (int) ($_POST['user_id'] ?? 0);
        if ($targetUserId <= 0) {
            $error = 'Select a member before applying an action.';
        } elseif ($action === 'remove') {
            if (cooperative_member_remove($pdo, $cooperativeId, $targetUserId)) {
                $message = 'Member removed from the cooperative.';
                if (function_exists('admin_audit_event')) {
                    admin_audit_event($pdo, 'cooperative_member_removed', 'cooperative_member', $targetUserId, [], ['cooperative_id' => $cooperativeId], 'success', 'Member removed from cooperative ' . $cooperativeName);
                }
            } else {
                $error = 'Member could not be removed; they are not an active member of this cooperative.';
            }
        } elseif ($action === 'reinstate') {
            if (cooperative_member_reinstate($pdo, $cooperativeId, $targetUserId)) {
                $message = 'Member reinstated to the cooperative.';
                if (function_exists('admin_audit_event')) {
                    admin_audit_event($pdo, 'cooperative_member_reinstated', 'cooperative_member', $targetUserId, [], ['cooperative_id' => $cooperativeId], 'success', 'Member reinstated to cooperative ' . $cooperativeName);
                }
            } else {
                $error = 'Member could not be reinstated.';
            }
        } else {
            $error = 'Unknown member action.';
        }
    }
}

$search = trim((string) ($_GET['q'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
if (!in_array($status, ['active', 'removed', 'all'], true)) {
    $status = 'all';
}
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 25;
$offset = ($page - 1) * $limit;

$listStatus = $status === 'all' ? '' : $status;
$total = coop_member_total($pdo, $cooperativeId, $status);
$pages = max(1, (int) ceil($total / $limit));
$members = cooperative_member_list($pdo, $cooperativeId, $search, $listStatus, $limit, $offset);

$query = http_build_query(array_filter(
    ['q' => $search, 'status' => $status === 'all' ? '' : $status],
    static fn (string $value): bool => $value !== ''
));
$listUrl = 'members.php' . ($query !== '' ? '?' . $query : '');
$pageParam = ($query !== '' ? '&' : '?') . 'page=';

coop_header(
    'Cooperative Members',
    'Search, filter, and manage every member linked to ' . $cooperativeName . '.',
    $user,
    'members'
);
?>
<?php if ($message): ?><div class="card" style="margin-bottom:16px;border-color:#abefc6;background:#ecfdf3"><h2 style="color:#067647"><?= e($message) ?></h2></div><?php endif; ?>
<?php if ($error): ?><div class="card" style="margin-bottom:16px;border-color:#fda29b;background:#fff1f2"><h2 style="color:#b42318"><?= e($error) ?></h2></div><?php endif; ?>

<section class="card">
  <form method="get" class="filters">
    <div class="field"><label for="q">Search</label><input type="search" id="q" name="q" value="<?= e($search) ?>" placeholder="Name, email, or phone"></div>
    <div class="field"><label for="status">Status</label>
      <select id="status" name="status">
        <option value="all"<?= $status === 'all' ? ' selected' : '' ?>>All statuses</option>
        <option value="active"<?= $status === 'active' ? ' selected' : '' ?>>Active</option>
        <option value="removed"<?= $status === 'removed' ? ' selected' : '' ?>>Removed</option>
      </select>
    </div>
    <div class="field"><label>&nbsp;</label><button class="btn" type="submit"><i class="fas fa-filter"></i> Apply</button></div>
  </form>
  <p style="color:var(--muted);margin:12px 0 0"><?= number_format($total) ?> member<?= $total === 1 ? '' : 's' ?><?= $status !== 'all' ? ' (' . e($status) . ')' : '' ?><?= $search !== '' ? ' matching "' . e($search) . '"' : '' ?>.</p>
</section>

<section class="card" style="margin-top:16px">
  <h2>Members</h2>
  <table class="table">
    <thead>
      <tr>
        <th>Member</th>
        <th>Farm / Application</th>
        <th>Member since</th>
        <th>Role</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($members as $row): ?>
        <?php
        $rowStatus = (string) ($row['status'] ?? 'active');
        $memberState = coop_state_name($pdo, (int) ($row['state_id'] ?? 0));
        $memberLga = coop_lga_name($pdo, (int) ($row['lga_id'] ?? 0));
        $locationBits = array_filter([$memberState, $memberLga, (string) ($row['district'] ?? ''), (string) ($row['village'] ?? '')], static fn (string $v): bool => $v !== '');
        ?>
        <tr>
          <td>
            <strong><?= e((string) ($row['name'] ?? '')) ?></strong><br>
            <small><?= e((string) ($row['email'] ?? '')) ?><?= (string) ($row['phone'] ?? '') !== '' ? '<br>' . e((string) $row['phone']) : '' ?><?= (string) ($row['last_login_at'] ?? '') !== '' ? '<br>Last login: ' . e(date('M j, Y', strtotime((string) $row['last_login_at']))) : '' ?></small>
          </td>
          <td>
            <small>
              <?= (string) ($row['app_ref'] ?? '') !== '' ? '<strong>' . e((string) $row['app_ref']) . '</strong><br>' : '' ?>
              <?= (string) ($row['member_type'] ?? '') !== '' ? 'Type: ' . e((string) $row['member_type']) . '<br>' : '' ?>
              <?= $locationBits !== [] ? e(implode(' / ', $locationBits)) . '<br>' : '' ?>
              <?= (string) ($row['account_status'] ?? '') !== '' ? 'Account: ' . e((string) $row['account_status']) : '' ?>
            </small>
          </td>
          <td><small><?= e($row['joined_at'] ? date('M j, Y', strtotime((string) $row['joined_at'])) : '-') ?></small></td>
          <td><span class="badge"><?= e((string) ($row['role_in_cooperative'] ?? 'member')) ?></span></td>
          <td><span class="badge <?= $rowStatus === 'active' ? '' : 'bad' ?>"><?= e($rowStatus) ?></span></td>
          <td>
            <?php if ($rowStatus === 'active'): ?>
              <form method="post" onsubmit="return confirm('Remove this member from the cooperative?');">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="remove">
                <input type="hidden" name="user_id" value="<?= e((int) ($row['user_id'] ?? 0)) ?>">
                <button class="btn light" type="submit" style="padding:6px 10px;font-size:.78rem"><i class="fas fa-user-minus"></i> Remove</button>
              </form>
            <?php else: ?>
              <form method="post" onsubmit="return confirm('Reinstate this member to the cooperative?');">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="reinstate">
                <input type="hidden" name="user_id" value="<?= e((int) ($row['user_id'] ?? 0)) ?>">
                <button class="btn light" type="submit" style="padding:6px 10px;font-size:.78rem"><i class="fas fa-user-check"></i> Reinstate</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$members): ?>
        <tr><td colspan="6">No members found<?= $search !== '' || $status !== 'all' ? ' for this filter' : '' ?>. Import members to get started.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
  <?php if ($pages > 1): ?>
    <div class="pagination">
      <?php if ($page > 1): ?>
        <a href="<?= e($listUrl) ?>"><i class="fas fa-angles-left"></i> First</a>
        <a href="<?= e($listUrl . $pageParam . ($page - 1)) ?>"><i class="fas fa-angle-left"></i> Prev</a>
      <?php endif; ?>
      <span class="current">Page <?= e($page) ?> of <?= e($pages) ?></span>
      <?php if ($page < $pages): ?>
        <a href="<?= e($listUrl . $pageParam . ($page + 1)) ?>">Next <i class="fas fa-angle-right"></i></a>
        <a href="<?= e($listUrl . $pageParam . $pages) ?>">Last <i class="fas fa-angles-right"></i></a>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</section>
<?php coop_footer(); ?>
