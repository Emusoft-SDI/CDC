<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../lib/admin-layout.php';

// If we reach here, user is already authenticated
if (isset($_GET['logout'])) {
    admin_logout();
    redirect_to('admin.php');
}

$pdo = db();
admin_ensure_schema($pdo);


admin_require_feature($pdo, 'applications');

$adminRole = admin_current_platform_role($pdo) ?? 'admin';
$isSuperAdmin = $adminRole === 'super_admin';

$pdo->exec("
    CREATE TABLE IF NOT EXISTS application_delete_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        application_id INT NOT NULL,
        requested_by INT NULL,
        reason TEXT NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'pending',
        reviewed_by INT NULL,
        reviewed_at DATETIME NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_app_delete_request_status (status),
        INDEX idx_app_delete_request_application (application_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
app_ensure_primary_auto_increment($pdo, 'application_delete_requests');

if (isset($_GET['export'])) {
    $stmt = $pdo->query("SELECT * FROM applications ORDER BY created_at DESC");
    $rows = (function () use ($stmt): Generator {
        while ($row = $stmt->fetch()) {
            yield [
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
            ];
        }
    })();
    app_export_csv('natcodev_applications.csv', ['Application ID', 'Reference', 'Name', 'Location', 'Farm Size', 'Phone', 'Email', 'Confirmed', 'Review Status', 'Applied', 'Confirmed At'], $rows);
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

function admin_application_payload(array $source): array
{
    $name = trim((string) ($source['name'] ?? ''));
    $location = trim((string) ($source['location'] ?? ''));
    $farmSize = filter_var($source['farm_size'] ?? null, FILTER_VALIDATE_FLOAT);
    $phone = preg_replace('/[^0-9]/', '', (string) ($source['phone'] ?? ''));
    $whatsapp = preg_replace('/[^0-9]/', '', (string) ($source['whatsapp'] ?? $phone));
    $email = filter_var(trim((string) ($source['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    $commitments = trim((string) ($source['commitments'] ?? 'Admin registration'));

    if ($name === '' || $location === '' || $farmSize === false || $farmSize <= 0 || $phone === '' || !$email) {
        throw new RuntimeException('Name, location, farm size, phone, and a valid email are required.');
    }

    return [
        'name' => $name,
        'location' => $location,
        'farm_size' => $farmSize,
        'phone' => $phone,
        'whatsapp' => $whatsapp !== '' ? $whatsapp : null,
        'email' => (string) $email,
        'commitments' => $commitments !== '' ? $commitments : 'Admin registration',
    ];
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

        $loginUrl = app_base_url() . '/login.php';
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
        } elseif ($bulk === 'delete' && $isSuperAdmin) {
            $stmt = $pdo->prepare("UPDATE users SET application_id = NULL WHERE application_id IN ({$placeholders})");
            $stmt->execute($ids);
            if (app_table_exists($pdo, 'certificates')) {
                $stmt = $pdo->prepare("DELETE FROM certificates WHERE application_id IN ({$placeholders})");
                $stmt->execute($ids);
            }
            $stmt = $pdo->prepare("DELETE FROM applications WHERE id IN ({$placeholders}) AND confirmed = 0");
            $stmt->execute($ids);
        } elseif ($bulk === 'request_delete' && !$isSuperAdmin) {
            $currentUser = current_user($pdo);
            $stmt = $pdo->prepare("
                INSERT INTO application_delete_requests (application_id, requested_by, reason)
                VALUES (?, ?, ?)
            ");
            foreach ($ids as $id) {
                $stmt->execute([(int) $id, $currentUser['id'] ?? null, 'Bulk delete request from admin applications page.']);
            }
        }
    }

    redirect_to('admin.php?' . admin_application_return_query($_POST));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_application') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        http_response_code(419);
        exit('Invalid security token.');
    }

    try {
        $data = admin_application_payload($_POST);
        $token = bin2hex(random_bytes(32));
        $pdo->prepare("
            INSERT INTO applications
                (app_ref, name, location, farm_size, phone, whatsapp, email, commitments, confirmation_token, submission_source, review_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Admin Console', 'active')
        ")->execute([
            generate_application_ref(),
            $data['name'],
            $data['location'],
            $data['farm_size'],
            $data['phone'],
            $data['whatsapp'],
            $data['email'],
            $data['commitments'],
            $token,
        ]);
    } catch (Throwable $e) {
        redirect_to('admin.php?' . http_build_query(['error' => $e->getMessage()] + $_POST));
    }

    redirect_to('admin.php?' . admin_application_return_query($_POST));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_application') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        http_response_code(419);
        exit('Invalid security token.');
    }

    try {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            throw new RuntimeException('Application not found.');
        }
        $data = admin_application_payload($_POST);
        $reviewStatus = in_array((string) ($_POST['review_status'] ?? 'active'), ['active', 'archived_no_response'], true)
            ? (string) $_POST['review_status']
            : 'active';
        $pdo->prepare("
            UPDATE applications
            SET name = ?, location = ?, farm_size = ?, phone = ?, whatsapp = ?, email = ?, commitments = ?, review_status = ?
            WHERE id = ?
        ")->execute([
            $data['name'],
            $data['location'],
            $data['farm_size'],
            $data['phone'],
            $data['whatsapp'],
            $data['email'],
            $data['commitments'],
            $reviewStatus,
            $id,
        ]);
    } catch (Throwable $e) {
        redirect_to('admin.php?' . http_build_query(['error' => $e->getMessage()] + $_POST));
    }

    redirect_to('admin.php?' . admin_application_return_query($_POST));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_application') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        http_response_code(419);
        exit('Invalid security token.');
    }
    if (!$isSuperAdmin) {
        http_response_code(403);
        exit('Forbidden: only Super Admin can delete applications.');
    }
    if ((string) ($_POST['confirm_delete'] ?? '') !== 'DELETE') {
        http_response_code(422);
        exit('Type DELETE to permanently delete this application.');
    }

    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE users SET application_id = NULL WHERE application_id = ?")->execute([$id]);
            if (app_table_exists($pdo, 'certificates')) {
                $pdo->prepare("DELETE FROM certificates WHERE application_id = ?")->execute([$id]);
            }
            $pdo->prepare("DELETE FROM applications WHERE id = ?")->execute([$id]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    redirect_to('admin.php?' . admin_application_return_query($_POST));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'request_delete_application') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        http_response_code(419);
        exit('Invalid security token.');
    }
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        $currentUser = current_user($pdo);
        $reason = trim((string) ($_POST['delete_reason'] ?? ''));
        $pdo->prepare("
            INSERT INTO application_delete_requests (application_id, requested_by, reason)
            VALUES (?, ?, ?)
        ")->execute([$id, $currentUser['id'] ?? null, $reason !== '' ? $reason : 'Admin requested deletion.']);
    }
    redirect_to('admin.php?' . admin_application_return_query($_POST));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'review_delete_request') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        http_response_code(419);
        exit('Invalid security token.');
    }
    if (!$isSuperAdmin) {
        http_response_code(403);
        exit('Forbidden: only Super Admin can review delete requests.');
    }

    $requestId = (int) ($_POST['request_id'] ?? 0);
    $decision = (string) ($_POST['decision'] ?? '');
    $stmt = $pdo->prepare("SELECT * FROM application_delete_requests WHERE id = ? AND status = 'pending' LIMIT 1");
    $stmt->execute([$requestId]);
    $request = $stmt->fetch();
    if ($request) {
        if ($decision === 'approve') {
            $appId = (int) $request['application_id'];
            $pdo->beginTransaction();
            try {
                $pdo->prepare("UPDATE users SET application_id = NULL WHERE application_id = ?")->execute([$appId]);
                if (app_table_exists($pdo, 'certificates')) {
                    $pdo->prepare("DELETE FROM certificates WHERE application_id = ?")->execute([$appId]);
                }
                $pdo->prepare("DELETE FROM applications WHERE id = ?")->execute([$appId]);
                $pdo->prepare("UPDATE application_delete_requests SET status = 'approved', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?")
                    ->execute([current_user($pdo)['id'] ?? null, $requestId]);
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }
        } elseif ($decision === 'reject') {
            $pdo->prepare("UPDATE application_delete_requests SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?")
                ->execute([current_user($pdo)['id'] ?? null, $requestId]);
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
$pageError = trim((string) ($_GET['error'] ?? ''));
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
$deleteRequests = [];
if ($isSuperAdmin) {
    $deleteRequests = $pdo->query("
        SELECT adr.*, a.app_ref, a.name, a.email, u.name requested_by_name
        FROM application_delete_requests adr
        LEFT JOIN applications a ON a.id = adr.application_id
        LEFT JOIN users u ON u.id = adr.requested_by
        WHERE adr.status = 'pending'
        ORDER BY adr.created_at DESC
        LIMIT 20
    ")->fetchAll();
}
admin_page_start('Applications', [
    'active' => 'admin.php',
    'description' => 'Review incoming grower applications, confirm approved records, and export registry data.',
    'wide' => true,
    'action_html' => '<a class="button" href="?export=1">Export CSV</a>',
]);
?>
    <?php if ($pageError !== ''): ?><div class="notice error"><?= e($pageError) ?></div><?php endif; ?>
    <section class="stats">
      <div class="stat"><span>Total</span><div class="metric"><?= (int) $counts['total'] ?></div></div>
      <div class="stat"><span>Confirmed</span><div class="metric"><?= (int) $counts['confirmed'] ?></div></div>
      <div class="stat"><span>Pending</span><div class="metric"><?= (int) $counts['pending'] ?></div></div>
      <div class="stat"><span>Archived</span><div class="metric"><?= (int) $counts['archived'] ?></div></div>
    </section>

    <details class="panel">
      <summary><strong>Create Application</strong></summary>
      <form method="post" class="grid" style="margin-top:14px;">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="create_application">
        <input type="hidden" name="search" value="<?= e($search) ?>">
        <input type="hidden" name="status" value="<?= e($status) ?>">
        <input type="hidden" name="page" value="<?= (int) $page ?>">
        <input type="hidden" name="per_page" value="<?= (int) $perPage ?>">
        <label>Name<input name="name" required></label>
        <label>Email<input type="email" name="email" required></label>
        <label>Phone<input name="phone" required></label>
        <label>WhatsApp<input name="whatsapp"></label>
        <label>Location<input name="location" required></label>
        <label>Farm Size (ha)<input name="farm_size" inputmode="decimal" required></label>
        <label>Commitments<textarea name="commitments">Admin registration</textarea></label>
        <div><button type="submit">Create Application</button></div>
      </form>
    </details>

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
          <?php if ($isSuperAdmin): ?><option value="delete">Delete selected pending</option><?php else: ?><option value="request_delete">Request delete selected</option><?php endif; ?>
        </select>
        <button type="submit" onclick="if (this.form.bulk.value === 'confirm') return confirm('Admin confirm selected applications? Use only after verification.'); if (this.form.bulk.value === 'delete') return confirm('Super Admin delete selected pending applications permanently?'); return true;">Apply to Selected</button>
      </div>
    </form>
    <?php if ($isSuperAdmin && $deleteRequests): ?>
      <section class="panel">
        <h2>Delete Requests</h2>
        <table>
          <thead><tr><th>Application</th><th>Requested By</th><th>Reason</th><th>Action</th></tr></thead>
          <tbody>
            <?php foreach ($deleteRequests as $request): ?>
              <tr>
                <td><strong><?= e($request['app_ref'] ?? 'Missing application') ?></strong><br><span class="muted"><?= e($request['name'] ?? '') ?> <?= e($request['email'] ?? '') ?></span></td>
                <td><?= e($request['requested_by_name'] ?? 'Admin') ?><br><span class="muted"><?= e(date('Y-m-d H:i', strtotime((string) $request['created_at']))) ?></span></td>
                <td><?= nl2br(e((string) $request['reason'])) ?></td>
                <td>
                  <form method="post" class="toolbar">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="review_delete_request">
                    <input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>">
                    <button class="danger" name="decision" value="approve" onclick="return confirm('Approve delete request and permanently delete this application?')">Approve Delete</button>
                    <button class="secondary" name="decision" value="reject">Reject</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </section>
    <?php endif; ?>
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
              <details class="row-review">
                <summary>View / Edit</summary>
                <div class="row-actions">
                  <p><strong>Commitments</strong><br><?= nl2br(e((string) $row['commitments'])) ?></p>
                  <p><strong>Submission</strong><br><?= e((string) ($row['submission_source'] ?? 'Website Form')) ?> / <?= e((string) ($row['ip_address'] ?? 'No IP')) ?></p>
                  <form method="post" class="inline-edit">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="update_application">
                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                    <input type="hidden" name="search" value="<?= e($search) ?>">
                    <input type="hidden" name="status" value="<?= e($status) ?>">
                    <input type="hidden" name="page" value="<?= (int) $page ?>">
                    <input type="hidden" name="per_page" value="<?= (int) $perPage ?>">
                    <label>Name<input name="name" value="<?= e($row['name']) ?>" required></label>
                    <label>Email<input type="email" name="email" value="<?= e($row['email']) ?>" required></label>
                    <label>Phone<input name="phone" value="<?= e($row['phone']) ?>" required></label>
                    <label>WhatsApp<input name="whatsapp" value="<?= e($row['whatsapp'] ?? '') ?>"></label>
                    <label>Location<input name="location" value="<?= e($row['location']) ?>" required></label>
                    <label>Farm Size<input name="farm_size" inputmode="decimal" value="<?= e((string) $row['farm_size']) ?>" required></label>
                    <label>Review Status
                      <select name="review_status">
                        <option value="active" <?= (string) ($row['review_status'] ?? 'active') !== 'archived_no_response' ? 'selected' : '' ?>>Active</option>
                        <option value="archived_no_response" <?= (string) ($row['review_status'] ?? '') === 'archived_no_response' ? 'selected' : '' ?>>Archived</option>
                      </select>
                    </label>
                    <label>Commitments<textarea name="commitments"><?= e($row['commitments']) ?></textarea></label>
                    <button type="submit">Save Changes</button>
                  </form>
                </div>
              </details>
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
              <?php if ($isSuperAdmin): ?>
                <details class="row-review" style="margin-top:8px;">
                  <summary class="danger-text">Delete</summary>
                  <form method="post" class="mini-form" onsubmit="return confirm('Permanently delete this application record? This cannot be undone.');">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="delete_application">
                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                    <input type="hidden" name="search" value="<?= e($search) ?>">
                    <input type="hidden" name="status" value="<?= e($status) ?>">
                    <input type="hidden" name="page" value="<?= (int) $page ?>">
                    <input type="hidden" name="per_page" value="<?= (int) $perPage ?>">
                    <label>Type DELETE<input name="confirm_delete" required></label>
                    <button class="danger" type="submit">Delete Permanently</button>
                  </form>
                </details>
              <?php else: ?>
                <details class="row-review" style="margin-top:8px;">
                  <summary>Request Delete</summary>
                  <form method="post" class="mini-form">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="request_delete_application">
                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                    <input type="hidden" name="search" value="<?= e($search) ?>">
                    <input type="hidden" name="status" value="<?= e($status) ?>">
                    <input type="hidden" name="page" value="<?= (int) $page ?>">
                    <input type="hidden" name="per_page" value="<?= (int) $perPage ?>">
                    <label>Reason<textarea name="delete_reason" required></textarea></label>
                    <button class="secondary" type="submit">Send Delete Request</button>
                  </form>
                </details>
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
