<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/admin-layout.php';
require_once __DIR__ . '/lib/layout/misc.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$pdo = db();
$q = trim((string) ($_GET['q'] ?? ''));

$results = [
    'growers'      => [],
    'farms'        => [],
    'orders'       => [],
    'tickets'      => [],
    'certificates' => [],
];

$currentUserId = (int) ($_SESSION['user_id'] ?? 0);

$isAdmin = false;
$isFieldAgent = isset($_SESSION['field_workspace_role']) && $_SESSION['field_workspace_role'] === 'field_agent';

try {
    if (app_table_exists($pdo, 'users')) {
        $roleCheck = $pdo->prepare("SELECT COALESCE(role, platform_role, '') AS r FROM users WHERE id = ? LIMIT 1");
        $roleCheck->execute([$currentUserId]);
        $roleRow = $roleCheck->fetch();
        if (in_array(strtolower((string) ($roleRow['r'] ?? '')), ['admin', 'super_admin'], true)) {
            $isAdmin = true;
        }
        if (!$isAdmin && app_table_exists($pdo, 'user_role_assignments')) {
            $ra = $pdo->prepare("SELECT COUNT(*) FROM user_role_assignments WHERE user_id = ? AND role_key IN ('admin','super_admin') AND status = 'active'");
            $ra->execute([$currentUserId]);
            $isAdmin = (int) $ra->fetchColumn() > 0;
        }
    }
} catch (Throwable $e) { /* ignore */ }

if ($q !== '') {
    $like = '%' . str_replace(' ', '%', $q) . '%';

    // Growers
    if (app_table_exists($pdo, 'users')) {
        $s = $pdo->prepare("SELECT id,name,email,platform_role FROM users WHERE (name LIKE ? OR email LIKE ?) AND deleted_at IS NULL ORDER BY name ASC LIMIT 50");
        $s->execute([$like, $like]);
        $results['growers'] = $s->fetchAll();
    }

    // Farms (scoped to current user)
    if (app_table_exists($pdo, 'grower_farms')) {
        $fSoft = app_column_exists($pdo, 'grower_farms', 'deleted_at') ? ' AND gf.deleted_at IS NULL' : '';
        $s = $pdo->prepare("
            SELECT gf.id, gf.farm_name, gf.street_address, ns.state_name, nl.lga_name, u.name grower_name
            FROM grower_farms gf
            LEFT JOIN nigeria_states ns ON ns.id = gf.state_id
            LEFT JOIN nigeria_lgas nl ON nl.id = gf.lga_id
            LEFT JOIN users u ON u.id = gf.user_id
            WHERE gf.user_id = ? AND (gf.farm_name LIKE ? OR gf.street_address LIKE ? OR ns.state_name LIKE ? OR nl.lga_name LIKE ?) {$fSoft}
            ORDER BY gf.farm_name ASC LIMIT 50
        ");
        $s->execute([$currentUserId, $like, $like, $like, $like]);
        $results['farms'] = $s->fetchAll();
    }

    // Orders
    if (app_table_exists($pdo, 'marketplace_orders')) {
        $s = $pdo->prepare("SELECT id,checkout_ref,payment_status,delivery_status,created_at,total_amount FROM marketplace_orders WHERE buyer_user_id = ? AND (checkout_ref LIKE ? OR CAST(total_amount AS CHAR) LIKE ?) ORDER BY created_at DESC LIMIT 50");
        $s->execute([$currentUserId, $like, $like]);
        $results['orders'] = $s->fetchAll();
    }

    // Support tickets
    if (app_table_exists($pdo, 'support_tickets')) {
        $s = $pdo->prepare("SELECT id,ticket_id,subject,status,created_at FROM support_tickets WHERE user_id = ? AND (ticket_id LIKE ? OR subject LIKE ? OR status LIKE ?) ORDER BY created_at DESC LIMIT 50");
        $s->execute([$currentUserId, $like, $like, $like]);
        $results['tickets'] = $s->fetchAll();
    } elseif (app_table_exists($pdo, 'messages')) {
        $rows = [];
        $s = $pdo->prepare("SELECT id,message,created_at,is_read FROM messages WHERE user_id = ? AND message LIKE ? ORDER BY created_at DESC LIMIT 50");
        $s->execute([$currentUserId, $like]);
        foreach ($s->fetchAll() as $row) {
            $rows[] = [
                'id'        => (int) $row['id'],
                'ticket_id' => '',
                'subject'   => mb_substr((string) $row['message'], 0, 80),
                'status'    => (int) ($row['is_read'] ?? 0) === 0 ? 'unanswered' : 'read',
                'created_at' => (string) $row['created_at'],
            ];
        }
        $results['tickets'] = $rows;
    }

    // Certificates
    if (app_table_exists($pdo, 'certificates')) {
        $cSoft = app_column_exists($pdo, 'certificates', 'deleted_at') ? ' AND c.deleted_at IS NULL' : '';
        $s = $pdo->prepare("
            SELECT c.id, COALESCE(c.certificate_ref,c.qr_code_hash) display_ref, c.status, c.issued_at, a.app_ref
            FROM certificates c JOIN applications a ON a.id = c.application_id
            WHERE a.user_id = ? AND (c.certificate_ref LIKE ? OR c.qr_code_hash LIKE ? OR a.app_ref LIKE ?) {$cSoft}
            ORDER BY c.issued_at DESC LIMIT 50
        ");
        $s->execute([$currentUserId, $like, $like, $like]);
        $results['certificates'] = $s->fetchAll();
    }
}

$totalResults = array_sum(array_map('count', $results));
?>

<?php if ($isAdmin): ?>
<?php admin_page_start('Search Results', ['active' => 'search-results.php']); ?>

<div class="card" style="padding:20px;margin-bottom:18px">
  <form method="get" style="display:flex;gap:10px">
    <input name="q" value="<?= e($q) ?>" placeholder="Growers, farms, orders, tickets, certificates..." style="flex:1;padding:11px 14px;border:1px solid var(--border-color);border-radius:8px;font-size:14px">
    <button type="submit" class="button"><i class="fas fa-search"></i> Search</button>
  </form>
</div>

<?php if ($q !== ''): ?>
  <?php if ($totalResults === 0): ?>
    <div class="empty">No results found for "<strong><?= e($q) ?></strong>". Try a different keyword.</div>
  <?php else: ?>
    <div class="kpi-grid">
      <div class="kpi-card"><span class="kpi-label">Growers</span><span class="kpi-value"><?= count($results['growers']) ?></span></div>
      <div class="kpi-card"><span class="kpi-label">Farms</span><span class="kpi-value"><?= count($results['farms']) ?></span></div>
      <div class="kpi-card"><span class="kpi-label">Orders</span><span class="kpi-value"><?= count($results['orders']) ?></span></div>
      <div class="kpi-card"><span class="kpi-label">Tickets</span><span class="kpi-value"><?= count($results['tickets']) ?></span></div>
      <div class="kpi-card"><span class="kpi-label">Certificates</span><span class="kpi-value"><?= count($results['certificates']) ?></span></div>
    </div>
  <?php endif; ?>
<?php else: ?>
  <div class="empty" style="padding:60px;text-align:center">
    <i class="fas fa-search" style="font-size:2.5rem;color:var(--border-color);margin-bottom:12px;display:block"></i>
    <p style="font-weight:900;color:var(--text-primary)">Start typing above to search across your workspace</p>
    <p style="color:var(--muted)">Search growers, farms, orders, tickets, and certificates.</p>
  </div>
<?php endif; ?>

<?php if (!empty($results['growers'])): ?>
<article class="collapse-card">
  <summary><i class="fas fa-users"></i> Growers / Farmers (<?= count($results['growers']) ?>)</summary>
  <div class="collapse-body">
    <table>
      <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Action</th></tr></thead>
      <tbody>
        <?php foreach ($results['growers'] as $row): ?>
        <tr>
          <td><strong><?= e((string) $row['name']) ?></strong></td>
          <td><?= e((string) $row['email']) ?></td>
          <td><span class="badge"><?= e(status_label((string) $row['platform_role'])) ?></span></td>
          <td><a href="document-verification.php?user=<?= (int) $row['id'] ?>">View</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</article>
<?php endif; ?>

<?php if (!empty($results['farms'])): ?>
<article class="collapse-card">
  <summary><i class="fas fa-tractor"></i> Farms (<?= count($results['farms']) ?>)</summary>
  <div class="collapse-body">
    <table>
      <thead><tr><th>Farm Name</th><th>Location</th><th>Growing</th><th>Action</th></tr></thead>
      <tbody>
        <?php foreach ($results['farms'] as $row): ?>
        <tr>
          <td><strong><?= e((string) $row['farm_name']) ?></strong></td>
          <td><?= e(trim((string) (($row['street_address'] ?? '') . ', ' . ($row['lga_name'] ?? '') . ', ' . ($row['state_name'] ?? '')), ', ')) ?></td>
          <td><?= e((string) $row['grower_name']) ?></td>
          <td><a href="../dashboard/fields.php#farm-<?= (int) $row['id'] ?>">Open</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</article>
<?php endif; ?>

<?php if (!empty($results['orders'])): ?>
<article class="collapse-card">
  <summary><i class="fas fa-truck"></i> Orders (<?= count($results['orders']) ?>)</summary>
  <div class="collapse-body">
    <table>
      <thead><tr><th>Checkout Ref</th><th>Total</th><th>Date</th><th>Status</th><th>Action</th></tr></thead>
      <tbody>
        <?php foreach ($results['orders'] as $row): ?>
        <tr>
          <td><strong><?= e((string) $row['checkout_ref']) ?></strong></td>
          <td>NGN <?= number_format((float) $row['total_amount'], 2) ?></td>
          <td><?= e(date('M j, Y', strtotime((string) $row['created_at']))) ?></td>
          <td><span class="badge <?= $row['payment_status'] === 'paid' ? 'verified' : 'warn' ?>"><?= e((string) $row['payment_status']) ?></span></td>
          <td><a href="../marketplace/orders.php?checkout_ref=<?= urlencode((string) $row['checkout_ref']) ?>">View</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</article>
<?php endif; ?>

<?php if (!empty($results['tickets'])): ?>
<article class="collapse-card">
  <summary><i class="fas fa-headset"></i> Support Tickets (<?= count($results['tickets']) ?>)</summary>
  <div class="collapse-body">
    <table>
      <thead><tr><th>Ticket ID</th><th>Subject</th><th>Status</th><th>Date</th><th>Action</th></tr></thead>
      <tbody>
        <?php foreach ($results['tickets'] as $row): ?>
        <tr>
          <td><strong><?= e((string) ($row['ticket_id'] ?: '#' . (int) $row['id'])) ?></strong></td>
          <td><?= e((string) ($row['subject'] ?? '')) ?></td>
          <td><span class="badge <?= in_array((string) $row['status'], ['resolved','closed'], true) ? 'verified' : 'warn' ?>"><?= e(status_label((string) $row['status'])) ?></span></td>
          <td><?= e(date('M j, Y', strtotime((string) $row['created_at']))) ?></td>
          <td><a href="../admin/support/">View</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</article>
<?php endif; ?>

<?php if (!empty($results['certificates'])): ?>
<article class="collapse-card">
  <summary><i class="fas fa-award"></i> Certificates (<?= count($results['certificates']) ?>)</summary>
  <div class="collapse-body">
    <table>
      <thead><tr><th>Ref</th><th>Status</th><th>Issued</th><th>Action</th></tr></thead>
      <tbody>
        <?php foreach ($results['certificates'] as $row): ?>
        <tr>
          <td><strong><?= e((string) $row['display_ref']) ?></strong></td>
          <td><span class="badge <?= (string) $row['status'] === 'issued' ? 'verified' : 'warn' ?>"><?= e((string) $row['status']) ?></span></td>
          <td><?= e(date('M j, Y', strtotime((string) $row['issued_at']))) ?></td>
          <td><a href="certificate-batch-verification.php">Verify</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</article>
<?php endif; ?>

<?php else: ?>
<?php
dashboard_page_start('Search Results', ['active' => 'search-results.php']);
?>

<div class="page-title">
  <div>
    <div class="page-kicker">NATCODEV Grower Hub</div>
    <h1>Search Results</h1>
    <?php if ($q !== ''): ?>
      <p style="color:var(--text-secondary);margin-top:6px">Showing results for "<strong><?= e($q) ?></strong>" — <?= (int) $totalResults ?> matches found</p>
    <?php else: ?>
      <p style="color:var(--text-secondary);margin-top:6px">Enter a search term above to find growers, farms, orders, tickets, and certificates.</p>
    <?php endif; ?>
  </div>
  <?php if ($q !== ''): ?><a class="btn light" href="search-results.php" style="background:#f3f4f6;color:#6b7280;border-color:#e5e7eb">Clear Search</a><?php endif; ?>
</div>

<?php if ($q !== ''): ?>
  <?php if ($totalResults === 0): ?>
  <div class="empty" style="padding:40px;text-align:center">
    <i class="fas fa-search" style="font-size:2rem;margin-bottom:12px;display:block;opacity:.5"></i>
    <p>No results found for "<strong><?= e($q) ?></strong>". Try a different keyword.</p>
  </div>
  <?php else: ?>
  <div class="summary" style="margin-bottom:20px">
    <div class="card" style="text-align:center"><div class="metric"><?= count($results['growers']) ?></div><div>Growers</div></div>
    <div class="card" style="text-align:center"><div class="metric"><?= count($results['farms']) ?></div><div>Farms</div></div>
    <div class="card" style="text-align:center"><div class="metric"><?= count($results['orders']) ?></div><div>Orders</div></div>
    <div class="card" style="text-align:center"><div class="metric"><?= count($results['tickets']) ?></div><div>Tickets</div></div>
    <div class="card" style="text-align:center"><div class="metric"><?= count($results['certificates']) ?></div><div>Certificates</div></div>
  </div>

  <?php if (!empty($results['growers'])): ?>
  <article class="card">
    <div class="card-h"><h3><i class="fas fa-users"></i> Growers / Farmers</h3><span class="badge"><?= count($results['growers']) ?> found</span></div>
    <?php foreach ($results['growers'] as $row): ?>
    <div class="table-lite-row">
      <div class="task-main">
        <span class="row-icon"><i class="fas fa-user" style="color:var(--primary-green)"></i></span>
        <div class="act-info">
          <div class="nm"><?= e((string) $row['name']) ?></div>
          <small><?= e((string) $row['email']) ?> · <?= e((string) $row['platform_role']) ?></small>
        </div>
      </div>
      <a class="link" href="profile.php?id=<?= (int) $row['id'] ?>">View</a>
    </div>
    <?php endforeach; ?>
  </article>
  <?php endif; ?>

  <?php if (!empty($results['farms'])): ?>
  <article class="card">
    <div class="card-h"><h3><i class="fas fa-tractor"></i> Farms</h3><a class="link" href="fields.php">View all</a></div>
    <?php foreach ($results['farms'] as $row): ?>
    <div class="table-lite-row">
      <div class="task-main">
        <span class="row-icon" style="color:#D97706"><i class="fas fa-tree"></i></span>
        <div class="act-info">
          <div class="nm"><?= e((string) $row['farm_name']) ?></div>
          <small><?= e(trim((string) (($row['street_address'] ?? '') . ', ' . ($row['lga_name'] ?? '') . ', ' . ($row['state_name'] ?? '')), ', ')) ?><?php if (!empty($row['grower_name'])): ?> · <?= e((string) $row['grower_name']) ?><?php endif; ?></small>
        </div>
      </div>
      <a class="link" href="fields.php#farm-<?= (int) $row['id'] ?>">Open</a>
    </div>
    <?php endforeach; ?>
  </article>
  <?php endif; ?>

  <?php if (!empty($results['orders'])): ?>
  <article class="card">
    <div class="card-h"><h3><i class="fas fa-truck"></i> Orders</h3><a class="link" href="wallet.php">Wallet</a></div>
    <?php foreach ($results['orders'] as $row): ?>
    <div class="table-lite-row">
      <div class="task-main">
        <span class="row-icon orange"><i class="fas fa-box"></i></span>
        <div class="act-info">
          <div class="nm"><?= e((string) $row['checkout_ref']) ?></div>
          <small>NGN <?= number_format((float) $row['total_amount'], 2) ?> · <?= e(date('M j, Y', strtotime((string) $row['created_at']))) ?></small>
        </div>
      </div>
      <span class="badge <?= $row['payment_status'] === 'paid' ? 'bp-green' : 'bp-orange' ?>"><?= e((string) $row['payment_status']) ?></span>
    </div>
    <?php endforeach; ?>
  </article>
  <?php endif; ?>

  <?php if (!empty($results['tickets'])): ?>
  <article class="card">
    <div class="card-h"><h3><i class="fas fa-headset"></i> Support Tickets</h3><a class="link" href="inbox.php">View all</a></div>
    <?php foreach ($results['tickets'] as $row): ?>
    <div class="table-lite-row">
      <div class="task-main">
        <span class="row-icon blue"><i class="fas fa-ticket"></i></span>
        <div class="act-info">
          <div class="nm"><?= e((string) ($row['ticket_id'] ?: $row['subject'] ?: '#' . (int) $row['id'])) ?></div>
          <small><?= e((string) ($row['subject'] ?? '')) ?> · <?= e(date('M j, Y', strtotime((string) $row['created_at']))) ?></small>
        </div>
      </div>
      <span class="badge <?= in_array((string) $row['status'], ['resolved','closed'], true) ? 'bp-green' : 'bp-orange' ?>"><?= e(status_label((string) $row['status'])) ?></span>
    </div>
    <?php endforeach; ?>
  </article>
  <?php endif; ?>

  <?php if (!empty($results['certificates'])): ?>
  <article class="card">
    <div class="card-h"><h3><i class="fas fa-award"></i> Certificates</h3><a class="link" href="certificates.php">View all</a></div>
    <?php foreach ($results['certificates'] as $row): ?>
    <div class="table-lite-row">
      <div class="task-main">
        <span class="row-icon" style="color:#8B5CF6"><i class="fas fa-award"></i></span>
        <div class="act-info">
          <div class="nm"><?= e((string) $row['display_ref']) ?></div>
          <small><?= e((string) $row['display_ref']) ?> · <?= e(date('M j, Y', strtotime((string) $row['issued_at']))) ?></small>
        </div>
      </div>
      <span class="badge bp-blue"><?= e((string) $row['status']) ?></span>
    </div>
    <?php endforeach; ?>
  </article>
  <?php endif; ?>

  <?php endif; ?>
<?php else: ?>
  <div class="card" style="text-align:center;padding:60px 20px">
    <i class="fas fa-search" style="font-size:2.5rem;color:var(--border-color);margin-bottom:16px;display:block"></i>
    <p style="font-size:1.1rem;font-weight:900;color:var(--text-primary);margin-bottom:8px">Start searching across your workspace</p>
    <p style="color:var(--text-secondary);font-size:.92rem">Find growers, farms, orders, tickets, certificates, and more — all from one box.</p>
  </div>
<?php endif; ?>

<?php dashboard_page_end(); ?>
<?php endif; ?>
