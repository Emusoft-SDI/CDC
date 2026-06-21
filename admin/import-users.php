<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../lib/admin-layout.php';
require_once __DIR__ . '/../lib/notification-dispatch.php';
require_once __DIR__ . '/../lib/admin-user-import.php';

$pdo = db();
admin_ensure_schema($pdo);
admin_ensure_import_schema($pdo);
admin_import_mark_expired($pdo);
admin_require($pdo);

$roles = admin_import_roles();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid security token.';
    } else {
        $action = (string) ($_POST['action'] ?? 'upload');

        if ($action === 'bulk_action') {
            $selectedIds = array_values(array_filter(array_map('intval', $_POST['selected_ids'] ?? [])));
            $bulk = (string) ($_POST['bulk'] ?? '');
            if (!$selectedIds) {
                $error = 'Select at least one row.';
            } elseif (!in_array($bulk, ['resend', 'archive', 'delete'], true)) {
                $error = 'Choose a valid bulk action.';
            } else {
                $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
                if ($bulk === 'delete') {
                    if (admin_current_user_is_super_admin($pdo)) {
                        $pdo->prepare("DELETE FROM user_import_records WHERE id IN ({$placeholders})")->execute($selectedIds);
                        $message = count($selectedIds) . ' row(s) deleted.';
                    } else {
                        $stmt = $pdo->prepare("SELECT id, COALESCE(name, email, phone, CONCAT('Import row #', id)) label FROM user_import_records WHERE id IN ({$placeholders})");
                        $stmt->execute($selectedIds);
                        $queued = 0;
                        foreach ($stmt->fetchAll() as $row) {
                            admin_queue_verified_delete_request($pdo, 'user_import_records', (int) $row['id'], (string) $row['label'], 'Bulk import row delete requested by admin.');
                            $queued++;
                        }
                        $message = "{$queued} delete request(s) sent to Super Admin for approval.";
                    }
                } elseif ($bulk === 'archive') {
                    $stmt = $pdo->prepare("UPDATE user_import_records SET status = 'archived_no_response', status_note = 'Archived by admin after no engagement.' WHERE id IN ({$placeholders})");
                    $stmt->execute($selectedIds);
                    $message = $stmt->rowCount() . ' row(s) archived.';
                } else {
                    $stmt = $pdo->prepare("SELECT * FROM user_import_records WHERE id IN ({$placeholders})");
                    $stmt->execute($selectedIds);
                    $sent = 0;
                    foreach ($stmt->fetchAll() as $record) {
                        $token = (string) ($record['engagement_token'] ?: bin2hex(random_bytes(32)));
                        if (!empty($record['application_id']) && admin_import_send_confirmation($pdo, (int) $record['application_id'], true, true, true)) {
                            $sent++;
                        } elseif (!empty($record['phone_e164']) && admin_import_send_phone_engagement($record, $token)) {
                            $sent++;
                        }
                        if ($sent > 0) {
                            $pdo->prepare("UPDATE user_import_records SET engagement_token = ?, engagement_deadline = DATE_ADD(NOW(), INTERVAL 14 DAY), status_note = 'Bulk engagement reminder sent.' WHERE id = ?")
                                ->execute([$token, (int) $record['id']]);
                        }
                    }
                    $message = "{$sent} engagement reminder(s) sent.";
                }
            }
        } elseif ($action === 'delete_record') {
            $recordId = (int) ($_POST['record_id'] ?? 0);
            if (admin_current_user_is_super_admin($pdo)) {
                $pdo->prepare('DELETE FROM user_import_records WHERE id = ?')->execute([$recordId]);
                $message = 'Import row deleted.';
            } else {
                $stmt = $pdo->prepare("SELECT COALESCE(name, email, phone, CONCAT('Import row #', id)) FROM user_import_records WHERE id = ? LIMIT 1");
                $stmt->execute([$recordId]);
                $label = (string) ($stmt->fetchColumn() ?: 'Import row #' . $recordId);
                admin_queue_verified_delete_request($pdo, 'user_import_records', $recordId, $label, 'Import row delete requested by admin.');
                $message = 'Delete request sent to Super Admin for approval.';
            }
        } elseif ($action === 'update_record') {
            $recordId = (int) ($_POST['record_id'] ?? 0);
            $name = trim((string) ($_POST['name'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $alternateEmail = trim((string) ($_POST['alternate_email'] ?? ''));
            $phone = trim((string) ($_POST['phone'] ?? ''));
            $alternatePhone = trim((string) ($_POST['alternate_phone'] ?? ''));
            $address = trim((string) ($_POST['address'] ?? ''));

            $emails = admin_import_emails($email . ' ' . $alternateEmail);
            $phones = admin_import_phones($phone . ' ' . $alternatePhone);
            $primaryEmail = $emails[0] ?? '';
            $primaryPhone = $phones[0] ?? '';
            $phoneE164 = $primaryPhone !== '' ? $primaryPhone : admin_import_phone_e164($phone);
            $status = ($name !== '' && ($primaryEmail !== '' || $phoneE164 !== '')) ? 'needs_review' : 'needs_contact';
            $note = $status === 'needs_review' ? 'Corrected by admin. Ready to retry engagement.' : 'Still missing valid name and contact details.';

            $pdo->prepare("
                UPDATE user_import_records
                SET name = ?, email = ?, alternate_email = ?, phone = ?, phone_e164 = ?, alternate_phone = ?, address = ?, status = ?, status_note = ?
                WHERE id = ?
            ")->execute([
                $name ?: null,
                $primaryEmail ?: null,
                implode(', ', array_slice($emails, 1)) ?: ($alternateEmail ?: null),
                $phone ?: ($primaryPhone ?: null),
                $phoneE164 ?: null,
                implode(', ', array_slice($phones, 1)) ?: ($alternatePhone ?: null),
                $address ?: null,
                $status,
                $note,
                $recordId,
            ]);
            $message = 'Import row updated.';
        } elseif ($action === 'retry_record') {
            $recordId = (int) ($_POST['record_id'] ?? 0);
            $stmt = $pdo->prepare('SELECT * FROM user_import_records WHERE id = ? LIMIT 1');
            $stmt->execute([$recordId]);
            $record = $stmt->fetch();
            if (!$record) {
                $error = 'Import row not found.';
            } else {
                $token = (string) ($record['engagement_token'] ?: bin2hex(random_bytes(32)));
                $record['phone_e164'] = $record['phone_e164'] ?: admin_import_phone_e164((string) $record['phone']);
                try {
                    if (!empty($record['email']) && filter_var($record['email'], FILTER_VALIDATE_EMAIL)) {
                        $appId = admin_import_insert_application($pdo, $record, $token);
                        if ($appId && admin_import_send_confirmation($pdo, $appId, true, !empty($record['phone_e164']), !empty($record['phone_e164']))) {
                            $pdo->prepare("UPDATE user_import_records SET status = 'pending_engagement', status_note = 'Corrected and engagement confirmation resent.', application_id = ?, engagement_token = ? WHERE id = ?")
                                ->execute([$appId, $token, $recordId]);
                            $message = 'Engagement confirmation resent.';
                        } else {
                            $error = 'Unable to send confirmation. Check notification log.';
                        }
                    } elseif (!empty($record['phone_e164']) && admin_import_send_phone_engagement($record, $token)) {
                        $pdo->prepare("UPDATE user_import_records SET status = 'pending_phone_engagement', status_note = 'Corrected and phone engagement link resent.', engagement_token = ? WHERE id = ?")
                            ->execute([$token, $recordId]);
                        $message = 'Phone engagement link resent.';
                    } else {
                        $error = 'Add a valid email or phone before retrying.';
                    }
                } catch (Throwable $e) {
                    $error = 'Retry failed: ' . $e->getMessage();
                }
            }
        } elseif ($action === 'resend_confirmation') {
            $recordId = (int) ($_POST['record_id'] ?? 0);
            $stmt = $pdo->prepare('SELECT * FROM user_import_records WHERE id = ? LIMIT 1');
            $stmt->execute([$recordId]);
            $record = $stmt->fetch();
            $applicationId = (int) ($record['application_id'] ?? 0);
            if ($applicationId > 0 && admin_import_send_confirmation($pdo, $applicationId, true, true, true)) {
                $message = 'Engagement confirmation resent.';
            } elseif ($record && !empty($record['phone_e164']) && !empty($record['engagement_token']) && admin_import_send_phone_engagement($record, (string) $record['engagement_token'])) {
                $message = 'Phone engagement link resent by SMS/WhatsApp.';
            } else {
                $error = 'Unable to resend confirmation for that record.';
            }
        } else {
            $defaultRole = (string) ($_POST['default_role'] ?? 'grower');
            $sendNotifications = isset($_POST['send_notifications']);

            if (!isset($roles[$defaultRole])) {
                $error = 'Choose a valid default role.';
            } else {
                try {
                    $upload = app_uploaded_file_info((array) ($_FILES['user_upload'] ?? []), ['csv', 'xlsx'], 15 * 1024 * 1024, 'Import spreadsheet');
                    $summary = admin_import_process($pdo, $upload['tmp_name'], $upload['name'], $defaultRole, $sendNotifications);
                    $message = "Import {$summary['batch']} captured {$summary['total']} rows: {$summary['staff']} staff onboarded, {$summary['pending']} growers pending engagement, {$summary['needs_contact']} need contact cleanup, {$summary['skipped']} skipped, {$summary['failed']} failed.";
                } catch (Throwable $e) {
                    $error = $e->getMessage();
                }
            }
        }
    }
}

$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT) ?: 0;
$editRecord = null;
if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM user_import_records WHERE id = ? LIMIT 1');
    $stmt->execute([$editId]);
    $editRecord = $stmt->fetch() ?: null;
}

$filterStatus = (string) ($_GET['status'] ?? 'all');
$filterContact = (string) ($_GET['contact'] ?? 'all');
$filterBatch = trim((string) ($_GET['batch'] ?? ''));
$search = trim((string) ($_GET['search'] ?? ''));
$where = ['1=1'];
$params = [];

if ($filterStatus !== 'all') {
    $where[] = 'r.status = ?';
    $params[] = $filterStatus;
}
if ($filterContact === 'email') {
    $where[] = "COALESCE(r.email, '') <> ''";
} elseif ($filterContact === 'phone') {
    $where[] = "COALESCE(r.phone_e164, '') <> ''";
} elseif ($filterContact === 'no_email') {
    $where[] = "COALESCE(r.email, '') = ''";
} elseif ($filterContact === 'no_phone') {
    $where[] = "COALESCE(r.phone_e164, '') = ''";
} elseif ($filterContact === 'alternate') {
    $where[] = "(COALESCE(r.alternate_email, '') <> '' OR COALESCE(r.alternate_phone, '') <> '')";
} else {
    $filterContact = 'all';
}
if ($filterBatch !== '') {
    $where[] = 'r.batch_ref = ?';
    $params[] = $filterBatch;
}
if ($search !== '') {
    $where[] = '(r.name LIKE ? OR r.email LIKE ? OR r.alternate_email LIKE ? OR r.phone LIKE ? OR r.phone_e164 LIKE ? OR r.alternate_phone LIKE ? OR r.status_note LIKE ? OR r.batch_ref LIKE ?)';
    $term = '%' . $search . '%';
    array_push($params, $term, $term, $term, $term, $term, $term, $term, $term);
}

$latestImportRows = "SELECT MAX(id) id FROM user_import_records GROUP BY source_file, source_row";

$counts = $pdo->query("
    SELECT
      COUNT(*) total,
      SUM(CASE WHEN status IN ('pending_engagement', 'pending_phone_engagement') THEN 1 ELSE 0 END) pending,
      SUM(CASE WHEN status = 'needs_contact' THEN 1 ELSE 0 END) needs_contact,
      SUM(CASE WHEN status = 'staff_onboarded' THEN 1 ELSE 0 END) staff,
      SUM(CASE WHEN status = 'skipped' THEN 1 ELSE 0 END) skipped,
      SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) failed,
      SUM(CASE WHEN status IN ('pending_engagement', 'pending_phone_engagement', 'engagement_confirmed', 'staff_onboarded') THEN 1 ELSE 0 END) contacted,
      SUM(CASE WHEN status = 'engagement_confirmed' THEN 1 ELSE 0 END) engaged,
      SUM(CASE WHEN status IN ('expired_no_engagement', 'archived_no_response') THEN 1 ELSE 0 END) no_response,
      SUM(CASE WHEN status IN ('needs_contact', 'skipped', 'failed') THEN 1 ELSE 0 END) invalid_contact
    FROM user_import_records r
    JOIN ({$latestImportRows}) latest ON latest.id = r.id
")->fetch() ?: ['total' => 0, 'pending' => 0, 'needs_contact' => 0, 'staff' => 0, 'skipped' => 0, 'failed' => 0, 'contacted' => 0, 'engaged' => 0, 'no_response' => 0, 'invalid_contact' => 0];

$page = admin_current_page();
$perPage = admin_per_page(50);
$offset = admin_pagination_offset($page, $perPage);
$countStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM user_import_records r
    JOIN ({$latestImportRows}) latest ON latest.id = r.id
    WHERE " . implode(' AND ', $where)
);
$countStmt->execute($params);
$totalRecords = (int) $countStmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT r.*, a.app_ref, a.confirmed, a.email_sent
    FROM user_import_records r
    JOIN ({$latestImportRows}) latest ON latest.id = r.id
    LEFT JOIN applications a ON a.id = r.application_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY r.created_at DESC, r.id DESC
    LIMIT {$perPage} OFFSET {$offset}
");
$stmt->execute($params);
$records = $stmt->fetchAll();

$batches = $pdo->query("
    SELECT batch_ref, MAX(created_at) created_at
    FROM user_import_records
    GROUP BY batch_ref
    ORDER BY created_at DESC
    LIMIT 50
")->fetchAll();

admin_page_start('Import & Engagement', [
    'active' => 'import-users.php',
    'description' => 'Upload legacy farmers, growers, staff, and admins with row-by-row audit. Growers remain pending until they confirm their engagement.',
    'wide' => true,
]);
?>
<?php if ($message): ?><div class="notice ok"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>

<section class="stats">
  <div class="stat"><span>Rows Captured</span><div class="metric"><?= (int) $counts['total'] ?></div></div>
  <div class="stat"><span>Pending Engagement</span><div class="metric"><?= (int) $counts['pending'] ?></div></div>
  <div class="stat"><span>Need Contact</span><div class="metric"><?= (int) $counts['needs_contact'] ?></div></div>
  <div class="stat"><span>Staff Onboarded</span><div class="metric"><?= (int) $counts['staff'] ?></div></div>
  <div class="stat"><span>Skipped</span><div class="metric"><?= (int) $counts['skipped'] ?></div></div>
  <div class="stat"><span>Failed</span><div class="metric"><?= (int) $counts['failed'] ?></div></div>
  <div class="stat"><span>Contacted</span><div class="metric"><?= (int) $counts['contacted'] ?></div></div>
  <div class="stat"><span>Engaged</span><div class="metric"><?= (int) $counts['engaged'] ?></div></div>
  <div class="stat"><span>No Response</span><div class="metric"><?= (int) $counts['no_response'] ?></div></div>
  <div class="stat"><span>Invalid Contact</span><div class="metric"><?= (int) $counts['invalid_contact'] ?></div></div>
</section>

<section class="layout">
  <form class="panel" method="post" enctype="multipart/form-data">
    <h2>Upload Spreadsheet</h2>
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="upload">
    <label>CSV or XLSX File</label>
    <input type="file" name="user_upload" accept=".csv,.xlsx" required>
    <label>Default Role</label>
    <select name="default_role" required>
      <?php foreach ($roles as $value => $label): ?>
        <option value="<?= e($value) ?>"><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <label><input type="checkbox" name="send_notifications" checked> Send onboarding or engagement messages now</label>
    <div class="actions"><button type="submit" data-busy-text="Importing spreadsheet...">Import Rows</button></div>
    <p class="meta">For growers, import creates a pending application and sends an engagement confirmation link. The user account is created only when they confirm.</p>
    <p class="meta">Expected headings include NAME, PHONE NUMBER, ADDRESS, NUMBER OF ACRES/HECTARES, EMAIL ADDRESS, Role, State, LGA, and Qualification.</p>
  </form>

  <?php if ($editRecord): ?>
    <form class="panel" method="post" style="margin-top:18px;">
      <h2>Edit Import Row</h2>
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="update_record">
      <input type="hidden" name="record_id" value="<?= (int) $editRecord['id'] ?>">
      <label>Name</label>
      <input type="text" name="name" value="<?= e($editRecord['name'] ?? '') ?>" required>
      <label>Email</label>
      <input type="text" name="email" value="<?= e($editRecord['email'] ?? '') ?>">
      <label>Alternate Email</label>
      <input type="text" name="alternate_email" value="<?= e($editRecord['alternate_email'] ?? '') ?>">
      <label>Phone</label>
      <input type="text" name="phone" value="<?= e($editRecord['phone'] ?? '') ?>">
      <label>Alternate Phone</label>
      <input type="text" name="alternate_phone" value="<?= e($editRecord['alternate_phone'] ?? '') ?>">
      <label>Address</label>
      <textarea name="address"><?= e($editRecord['address'] ?? '') ?></textarea>
      <div class="actions">
        <button type="submit">Save Correction</button>
        <a class="button secondary" href="import-users.php">Cancel</a>
      </div>
      <p class="meta">After saving, use Retry on the row to send the engagement link again.</p>
    </form>
  <?php endif; ?>

  <section>
    <form class="panel" method="get">
      <h2>Find Import Rows</h2>
      <input type="hidden" name="page" value="1">
      <label>Status</label>
      <select name="status">
        <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>All Statuses</option>
        <?php foreach (['pending_engagement', 'pending_phone_engagement', 'needs_contact', 'needs_review', 'expired_no_engagement', 'archived_no_response', 'skipped', 'failed', 'staff_onboarded', 'engagement_confirmed'] as $statusOption): ?>
          <option value="<?= e($statusOption) ?>" <?= $filterStatus === $statusOption ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $statusOption))) ?></option>
        <?php endforeach; ?>
      </select>
      <label>Contact</label>
      <select name="contact">
        <option value="all" <?= $filterContact === 'all' ? 'selected' : '' ?>>All Contacts</option>
        <option value="email" <?= $filterContact === 'email' ? 'selected' : '' ?>>Has Email</option>
        <option value="phone" <?= $filterContact === 'phone' ? 'selected' : '' ?>>Has Valid Phone</option>
        <option value="no_email" <?= $filterContact === 'no_email' ? 'selected' : '' ?>>Missing Email</option>
        <option value="no_phone" <?= $filterContact === 'no_phone' ? 'selected' : '' ?>>Missing Valid Phone</option>
        <option value="alternate" <?= $filterContact === 'alternate' ? 'selected' : '' ?>>Has Alternate Contact</option>
      </select>
      <label>Batch</label>
      <select name="batch">
        <option value="">All Batches</option>
        <?php foreach ($batches as $batch): ?>
          <option value="<?= e($batch['batch_ref']) ?>" <?= $filterBatch === $batch['batch_ref'] ? 'selected' : '' ?>><?= e($batch['batch_ref']) ?></option>
        <?php endforeach; ?>
      </select>
      <label>Search</label>
      <input type="search" name="search" value="<?= e($search) ?>" placeholder="name, email, phone, note, batch">
      <input type="hidden" name="per_page" value="<?= (int) $perPage ?>">
      <div class="actions">
        <button type="submit">Filter Rows</button>
        <a class="button secondary" href="import-users.php">Clear</a>
      </div>
    </form>
    <?= admin_pagination_controls($totalRecords, $page, $perPage) ?>
    <form class="panel" method="post" id="bulkImportForm">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="bulk_action">
      <div class="toolbar">
        <select name="bulk" required>
          <option value="">Bulk action</option>
          <option value="resend">Resend selected</option>
          <option value="archive">Archive selected</option>
          <option value="delete">Delete selected</option>
        </select>
        <button type="submit">Apply to Selected</button>
      </div>
    </form>
    <table>
      <thead><tr><th><input type="checkbox" onclick="document.querySelectorAll('[name=&quot;selected_ids[]&quot;]').forEach(cb => cb.checked = this.checked)"></th><th>Batch / Row</th><th>Person</th><th>Role</th><th>Status</th><th>Engagement</th><th>Action</th></tr></thead>
      <tbody>
        <?php foreach ($records as $record): ?>
          <tr>
            <td><input type="checkbox" name="selected_ids[]" value="<?= (int) $record['id'] ?>" form="bulkImportForm"></td>
            <td><?= e($record['batch_ref']) ?><br><small>Row <?= (int) ($record['source_row'] ?? 0) ?></small></td>
            <td>
              <strong><?= e($record['name'] ?? '') ?></strong><br>
              <small><?= e($record['email'] ?? '') ?></small>
              <?php if (!empty($record['alternate_email'])): ?><br><small>Alt: <?= e($record['alternate_email']) ?></small><?php endif; ?>
              <?php if (!empty($record['phone'])): ?><br><small><?= e($record['phone']) ?></small><?php endif; ?>
              <?php if (!empty($record['phone_e164'])): ?><br><small><?= e($record['phone_e164']) ?></small><?php endif; ?>
              <?php if (!empty($record['alternate_phone'])): ?><br><small>Alt: <?= e($record['alternate_phone']) ?></small><?php endif; ?>
            </td>
            <td><?= e($roles[$record['role']] ?? $record['role']) ?></td>
            <td>
              <span class="badge <?= $record['status'] === 'failed' ? 'danger' : ($record['status'] === 'skipped' || $record['status'] === 'needs_contact' ? 'pending' : 'verified') ?>"><?= e(str_replace('_', ' ', $record['status'])) ?></span>
              <?php if (!empty($record['status_note'])): ?><br><small><?= e($record['status_note']) ?></small><?php endif; ?>
            </td>
            <td>
              <?php if (!empty($record['application_id'])): ?>
                <?= e($record['app_ref'] ?? '') ?><br>
                <small><?= (int) ($record['confirmed'] ?? 0) === 1 ? 'Confirmed by user' : 'Awaiting user confirmation' ?></small>
                <?php if (!empty($record['engagement_deadline'])): ?><br><small>Deadline: <?= e(date('Y-m-d', strtotime((string) $record['engagement_deadline']))) ?></small><?php endif; ?>
              <?php else: ?>
                <span class="muted">Not applicable</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="toolbar" style="margin:0;">
              <?php if ((!empty($record['application_id']) && (int) ($record['confirmed'] ?? 0) !== 1) || $record['status'] === 'pending_phone_engagement'): ?>
                <form method="post">
                  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="resend_confirmation">
                  <input type="hidden" name="record_id" value="<?= (int) $record['id'] ?>">
                  <button type="submit">Resend</button>
                </form>
              <?php endif; ?>
              <?php if (in_array($record['status'], ['skipped', 'needs_contact', 'failed', 'needs_review'], true)): ?>
                <a class="button secondary" href="import-users.php?<?= e(http_build_query(array_merge($_GET, ['edit' => (int) $record['id'], 'page' => $page, 'per_page' => $perPage]))) ?>">Edit</a>
                <?php if ($record['status'] === 'needs_review'): ?>
                  <form method="post">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="retry_record">
                    <input type="hidden" name="record_id" value="<?= (int) $record['id'] ?>">
                    <button type="submit">Retry</button>
                  </form>
                <?php endif; ?>
                <form method="post" onsubmit="return confirm('Delete this import row? This does not delete any confirmed application or user account.')">
                  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="delete_record">
                  <input type="hidden" name="record_id" value="<?= (int) $record['id'] ?>">
                  <button class="danger" type="submit">Delete</button>
                </form>
              <?php endif; ?>
              <?php if (empty($record['application_id']) && !in_array($record['status'], ['skipped', 'needs_contact', 'failed', 'needs_review', 'pending_phone_engagement'], true)): ?>
                <span class="muted">-</span>
              <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$records): ?><tr><td colspan="7">No imports yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
    <?= admin_pagination_controls($totalRecords, $page, $perPage) ?>
  </section>
</section>
<?php admin_page_end(); ?>
