<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/admin-layout.php';

session_start();

$pdo = db();
admin_ensure_schema($pdo);

if (isset($_GET['logout'])) {
    admin_logout();
}

$error = '';
if (!admin_session_is_authenticated($pdo) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (admin_password_is_valid((string) ($_POST['password'] ?? ''))) {
        session_regenerate_id(true);
        $_SESSION['admin_authenticated'] = true;
        $_SESSION['admin'] = true;
        redirect_to('admin.php');
    }
    $error = 'Invalid admin password.';
}

if (!admin_session_is_authenticated($pdo)) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>NATCODEV Admin Login</title>
      <style>
        :root { --primary:#1a5276; --green:#1f8a55; --green-dark:#166b41; --ink:#1f2937; --muted:#667085; --line:#d8e2dc; }
        * { box-sizing:border-box; }
        body { font-family:"Segoe UI", Tahoma, Geneva, Verdana, sans-serif; background:linear-gradient(135deg, rgba(26,82,118,.09), rgba(31,138,85,.12)), #f5f8f6; display:flex; min-height:100vh; align-items:center; justify-content:center; margin:0; padding:24px; color:var(--ink); }
        .login-shell { width:100%; max-width:420px; }
        .brand { color:var(--primary); font-weight:800; text-align:center; margin-bottom:18px; letter-spacing:.02em; }
        form { width:100%; background:#fff; padding:34px; border-radius:8px; border:1px solid rgba(16,24,40,.08); box-shadow:0 18px 44px rgba(16,24,40,.12); }
        h1 { margin:0 0 8px; color:var(--primary); font-size:28px; line-height:1.15; }
        .lead { margin:0 0 22px; color:var(--muted); }
        input, button { width:100%; box-sizing:border-box; padding:13px; margin-top:12px; border-radius:5px; border:1px solid var(--line); font-size:1rem; }
        input:focus { border-color:var(--green); box-shadow:0 0 0 3px rgba(31,138,85,.14); outline:none; }
        button { background:var(--green); color:#fff; border:0; font-weight:800; cursor:pointer; box-shadow:0 10px 24px rgba(31,138,85,.22); }
        button:hover { background:var(--green-dark); }
        .error { color:#a32020; background:#fff3f3; border:1px solid #ffd2d2; padding:10px 12px; border-radius:5px; }
        .home-link { display:inline-block; margin-top:18px; color:var(--green-dark); text-decoration:none; font-weight:800; }
        @media (max-width:520px) { form { padding:26px 18px; } }
      </style>
    </head>
    <body>
      <main class="login-shell">
      <div class="brand">NATCODEV Registry</div>
      <form method="post">
        <h1>Admin Login</h1>
        <p class="lead">Access application review, exports, reporting, and registry operations.</p>
        <?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>
        <input type="password" name="password" placeholder="Admin password" required autofocus>
        <button type="submit">Login</button>
        <a class="home-link" href="../index.php">Back to home</a>
      </form>
      </main>
    </body>
    </html>
    <?php
    exit;
}

admin_require_feature($pdo, 'applications');

if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="natcodev_applications.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'Reference', 'Name', 'Location', 'Farm Size', 'Phone', 'Email', 'Confirmed', 'Review Status', 'Applied', 'Confirmed At']);

    $stmt = $pdo->query("SELECT * FROM applications ORDER BY created_at DESC");
    while ($row = $stmt->fetch()) {
        fputcsv($out, [
            $row['id'],
            $row['app_ref'],
            $row['name'],
            $row['location'],
            $row['farm_size'],
            $row['phone'],
            $row['email'],
            (int) $row['confirmed'] === 1 ? 'Yes' : 'No',
            $row['review_status'] ?? 'active',
            $row['created_at'],
            $row['confirmed_at'],
        ]);
    }
    fclose($out);
    exit;
}

function admin_send_application_confirmation(array $app): bool
{
    $token = (string) ($app['confirmation_token'] ?? '');
    if ($token === '') {
        $token = bin2hex(random_bytes(32));
        db()->prepare("UPDATE applications SET confirmation_token = ? WHERE id = ?")->execute([$token, (int) $app['id']]);
    }

    $confirmUrl = app_base_url() . '/confirm_email.php?token=' . urlencode($token);
    $plain = "Dear {$app['name']},\n\nPlease confirm your NATCODEV application {$app['app_ref']} using this link:\n{$confirmUrl}\n\nIf you did not submit this application, please ignore this email.\n\nThe NATCODEV Team";
    $html = '<p>Dear <strong>' . e($app['name']) . '</strong>,</p>'
        . '<p>Please confirm your NATCODEV application <strong>' . e($app['app_ref']) . '</strong>.</p>'
        . '<p><a href="' . e($confirmUrl) . '" style="display:inline-block;padding:10px 18px;background:#2d5016;color:#fff;text-decoration:none;border-radius:5px;">Confirm My Application</a></p>'
        . '<p>If you did not submit this application, please ignore this email.</p>';

    $sent = app_send_mail((string) $app['email'], 'Confirm Your NATCODEV Application', $plain, $html);
    if ($sent) {
        db()->prepare("UPDATE applications SET email_sent = 1 WHERE id = ?")->execute([(int) $app['id']]);
    }

    return $sent;
}

function admin_application_return_query(array $source): string
{
    return http_build_query([
        'search' => (string) ($source['search'] ?? ''),
        'status' => (string) ($source['status'] ?? 'all'),
        'page' => (string) ($source['page'] ?? '1'),
        'per_page' => (string) ($source['per_page'] ?? '50'),
    ]);
}

function admin_confirm_application(PDO $pdo, array $app): bool
{
    try {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE applications SET confirmed = 1, confirmed_at = COALESCE(confirmed_at, NOW()), review_status = 'active' WHERE id = ?")
            ->execute([(int) $app['id']]);

        $temporaryPassword = bin2hex(random_bytes(4));
        $pdo->prepare("
            INSERT INTO users (email, password, application_id, name, phone, role)
            VALUES (?, ?, ?, ?, ?, 'grower')
            ON DUPLICATE KEY UPDATE application_id = VALUES(application_id), name = VALUES(name), phone = VALUES(phone)
        ")->execute([
            $app['email'],
            password_hash($temporaryPassword, PASSWORD_DEFAULT),
            (int) $app['id'],
            $app['name'],
            $app['phone'] ?? null,
        ]);
        $pdo->commit();

        $loginUrl = app_base_url() . '/dashboard/login.php';
        app_send_mail(
            (string) $app['email'],
            'Your NATCODEV Dashboard Access',
            "Dear {$app['name']},\n\nYour application {$app['app_ref']} has been confirmed.\nDashboard: {$loginUrl}\nTemporary password: {$temporaryPassword}\n\nPlease change this password after login."
        );
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Admin confirmation error: ' . $e->getMessage());
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_action') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        http_response_code(419);
        exit('Invalid security token.');
    }

    $ids = array_values(array_filter(array_map('intval', $_POST['selected_ids'] ?? [])));
    $bulk = (string) ($_POST['bulk'] ?? '');
    if ($ids) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        if ($bulk === 'resend') {
            $stmt = $pdo->prepare("SELECT id, app_ref, name, email, confirmation_token FROM applications WHERE id IN ({$placeholders}) AND confirmed = 0");
            $stmt->execute($ids);
            foreach ($stmt->fetchAll() as $app) {
                admin_send_application_confirmation($app);
            }
        } elseif ($bulk === 'confirm') {
            $stmt = $pdo->prepare("SELECT id, app_ref, name, email, phone FROM applications WHERE id IN ({$placeholders}) AND confirmed = 0");
            $stmt->execute($ids);
            foreach ($stmt->fetchAll() as $app) {
                admin_confirm_application($pdo, $app);
            }
        } elseif ($bulk === 'archive') {
            $stmt = $pdo->prepare("UPDATE applications SET review_status = 'archived_no_response' WHERE id IN ({$placeholders}) AND confirmed = 0");
            $stmt->execute($ids);
        }
    }

    redirect_to('admin.php?' . admin_application_return_query($_POST));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'resend_confirmation') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        http_response_code(419);
        exit('Invalid security token.');
    }

    $id = (int) ($_POST['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT id, app_ref, name, email, confirmation_token FROM applications WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $app = $stmt->fetch();
    if ($app) {
        admin_send_application_confirmation($app);
    }

    redirect_to('admin.php?' . admin_application_return_query($_POST));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'confirm') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        http_response_code(419);
        exit('Invalid security token.');
    }

    $id = (int) ($_POST['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT id, app_ref, name, email, phone FROM applications WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $app = $stmt->fetch();

    if ($app) {
        admin_confirm_application($pdo, $app);
    }

    redirect_to('admin.php?' . admin_application_return_query($_POST));
}

$search = trim((string) ($_GET['search'] ?? ''));
$status = (string) ($_GET['status'] ?? 'all');
$page = admin_current_page();
$perPage = admin_per_page(50);
$offset = admin_pagination_offset($page, $perPage);
$params = [];
$where = ['1=1'];

if ($search !== '') {
    $where[] = '(name LIKE ? OR email LIKE ? OR phone LIKE ? OR app_ref LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like);
}
if ($status === 'confirmed') {
    $where[] = 'confirmed = 1';
    $where[] = "review_status <> 'archived_no_response'";
} elseif ($status === 'pending') {
    $where[] = 'confirmed = 0';
    $where[] = "review_status <> 'archived_no_response'";
} elseif ($status === 'archived') {
    $where[] = "review_status = 'archived_no_response'";
} else {
    $status = 'all';
    $where[] = "review_status <> 'archived_no_response'";
}

$countStmt = $pdo->prepare('SELECT COUNT(*) FROM applications WHERE ' . implode(' AND ', $where));
$countStmt->execute($params);
$totalApplications = (int) $countStmt->fetchColumn();

$stmt = $pdo->prepare('SELECT * FROM applications WHERE ' . implode(' AND ', $where) . " ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}");
$stmt->execute($params);
$applications = $stmt->fetchAll();

$counts = $pdo->query("
    SELECT
      COUNT(*) total,
      SUM(CASE WHEN confirmed = 1 THEN 1 ELSE 0 END) confirmed,
      SUM(CASE WHEN confirmed = 0 AND review_status <> 'archived_no_response' THEN 1 ELSE 0 END) pending,
      SUM(CASE WHEN review_status = 'archived_no_response' THEN 1 ELSE 0 END) archived
    FROM applications
")->fetch() ?: ['total' => 0, 'confirmed' => 0, 'pending' => 0, 'archived' => 0];
admin_page_start('Applications', [
    'active' => 'admin.php',
    'description' => 'Review incoming grower applications, confirm approved records, and export registry data.',
    'wide' => true,
    'action_html' => '<a class="button" href="?export=1">Export CSV</a>',
]);
?>
    <section class="stats">
      <div class="stat"><span>Total</span><div class="metric"><?= (int) $counts['total'] ?></div></div>
      <div class="stat"><span>Confirmed</span><div class="metric"><?= (int) $counts['confirmed'] ?></div></div>
      <div class="stat"><span>Pending</span><div class="metric"><?= (int) $counts['pending'] ?></div></div>
      <div class="stat"><span>Archived</span><div class="metric"><?= (int) $counts['archived'] ?></div></div>
    </section>

    <form class="toolbar panel" method="get">
      <input type="search" name="search" placeholder="Search ref, name, email, phone" value="<?= e($search) ?>">
      <input type="hidden" name="page" value="1">
      <select name="status">
        <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All</option>
        <option value="confirmed" <?= $status === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
        <option value="archived" <?= $status === 'archived' ? 'selected' : '' ?>>Archived</option>
      </select>
      <button type="submit">Filter</button>
      <a class="button" href="?export=1">Export CSV</a>
    </form>

    <?= admin_pagination_controls($totalApplications, $page, $perPage) ?>
    <form class="panel" method="post" id="bulkApplicationsForm">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="bulk_action">
      <input type="hidden" name="search" value="<?= e($search) ?>">
      <input type="hidden" name="status" value="<?= e($status) ?>">
      <input type="hidden" name="page" value="<?= (int) $page ?>">
      <input type="hidden" name="per_page" value="<?= (int) $perPage ?>">
      <div class="toolbar">
        <select name="bulk" required>
          <option value="">Bulk action</option>
          <option value="resend">Resend selected confirmations</option>
          <option value="confirm">Admin confirm selected</option>
          <option value="archive">Archive selected pending</option>
        </select>
        <button type="submit" onclick="return this.form.bulk.value !== 'confirm' || confirm('Admin confirm selected applications? Use only after verification.')">Apply to Selected</button>
      </div>
    </form>
    <table>
      <thead>
        <tr>
          <th><input type="checkbox" onclick="document.querySelectorAll('[name=&quot;selected_ids[]&quot;]').forEach(cb => cb.checked = this.checked)"></th>
          <th>Reference</th>
          <th>Grower</th>
          <th>Location</th>
          <th>Farm</th>
          <th>Status</th>
          <th>Applied</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($applications as $row): ?>
          <tr>
            <td><input type="checkbox" name="selected_ids[]" value="<?= (int) $row['id'] ?>" form="bulkApplicationsForm" <?= (int) $row['confirmed'] === 1 ? 'disabled' : '' ?>></td>
            <td><?= e($row['app_ref']) ?></td>
            <td>
              <strong><?= e($row['name']) ?></strong><br>
              <span class="muted"><?= e($row['email']) ?><br><?= e($row['phone']) ?></span>
            </td>
            <td><?= e($row['location']) ?></td>
            <td><?= e((string) $row['farm_size']) ?> ha</td>
            <td>
              <?php
                $isArchived = (string) ($row['review_status'] ?? 'active') === 'archived_no_response';
                $statusClass = (int) $row['confirmed'] === 1 ? 'verified' : ($isArchived ? 'muted-badge' : 'pending');
                $statusLabel = (int) $row['confirmed'] === 1 ? 'Confirmed' : ($isArchived ? 'Archived' : 'Pending');
              ?>
              <span class="badge <?= e($statusClass) ?>"><?= e($statusLabel) ?></span>
            </td>
            <td><?= e(date('Y-m-d H:i', strtotime((string) $row['created_at']))) ?></td>
            <td>
              <?php if ((int) $row['confirmed'] !== 1): ?>
                <form method="post">
                  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="resend_confirmation">
                  <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                  <input type="hidden" name="search" value="<?= e($search) ?>">
                  <input type="hidden" name="status" value="<?= e($status) ?>">
                  <input type="hidden" name="page" value="<?= (int) $page ?>">
                  <input type="hidden" name="per_page" value="<?= (int) $perPage ?>">
                  <button type="submit">Resend Confirmation</button>
                </form>
                <form method="post" onsubmit="return confirm('Admin override should be used only after direct verification. Confirm this application now?')" style="margin-top:8px;">
                  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="confirm">
                  <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                  <input type="hidden" name="search" value="<?= e($search) ?>">
                  <input type="hidden" name="status" value="<?= e($status) ?>">
                  <input type="hidden" name="page" value="<?= (int) $page ?>">
                  <input type="hidden" name="per_page" value="<?= (int) $perPage ?>">
                  <button class="secondary" type="submit">Admin Confirm</button>
                </form>
              <?php else: ?>
                <span class="muted">No action</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$applications): ?>
          <tr><td colspan="8">No applications found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
    <?= admin_pagination_controls($totalApplications, $page, $perPage) ?>
<?php admin_page_end(); ?>
