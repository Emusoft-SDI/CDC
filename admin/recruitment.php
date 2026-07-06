<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../lib/admin-layout.php';
require_once __DIR__ . '/../lib/notification-dispatch.php';

$pdo = db();
admin_ensure_schema($pdo);
admin_require($pdo);

$roles = [
    'field_agent' => 'Field Agent',
    'agronomist' => 'Agronomist',
    'extensionist' => 'Agric Extensionist',
];
$statuses = [
    'pending' => 'Pending',
    'shortlisted' => 'Shortlisted',
    'more_info' => 'More Info Needed',
    'approved' => 'Approved',
    'rejected' => 'Rejected',
];
$message = '';
$error = '';

function recruitment_role_storage(string $role): array
{
    return [admin_staff_role_to_auth_role($role), $role === 'agronomist' ? 1 : 0, $role === 'extensionist' ? 1 : 0, $role];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid security token.';
    } else {
        $id = (int) ($_POST['id'] ?? 0);
        $action = (string) ($_POST['action'] ?? '');
        $notes = trim((string) ($_POST['review_notes'] ?? ''));

        $stmt = $pdo->prepare("SELECT * FROM recruitment_applications WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $app = $stmt->fetch();

        if (!$app) {
            $error = 'Recruitment application not found.';
        } elseif ($action === 'approve') {
            [$storedRole, $isAgronomist, $isExtensionist, $specialty] = recruitment_role_storage((string) $app['role_applied']);
            $temporaryPassword = bin2hex(random_bytes(4));
            try {
                $pdo->beginTransaction();
                $userStmt = $pdo->prepare("
                    INSERT INTO users (name, email, phone, application_id, password, role, is_agronomist, is_extensionist, agronomist_license, staff_specialty)
                    VALUES (?, ?, ?, NULL, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        name = VALUES(name),
                        phone = VALUES(phone),
                        role = VALUES(role),
                        is_agronomist = VALUES(is_agronomist),
                        is_extensionist = VALUES(is_extensionist),
                        agronomist_license = VALUES(agronomist_license),
                        staff_specialty = VALUES(staff_specialty)
                ");
                $userStmt->execute([
                    $app['name'],
                    $app['email'],
                    $app['phone'],
                    password_hash($temporaryPassword, PASSWORD_DEFAULT),
                    $storedRole,
                    $isAgronomist,
                    $isExtensionist,
                    $isAgronomist ? ($app['license_number'] ?: null) : null,
                    $specialty,
                ]);
                $userId = (int) $pdo->lastInsertId();
                if ($userId === 0) {
                    $existing = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                    $existing->execute([$app['email']]);
                    $userId = (int) $existing->fetchColumn();
                }
                admin_upsert_staff_profile($pdo, $userId, (string) $app['role_applied'], [
                    'state' => $app['state'] ?? null,
                    'lga' => $app['lga'] ?? null,
                    'qualification' => $app['qualification'] ?? null,
                    'license_number' => $app['license_number'] ?? null,
                    'experience_years' => $app['experience_years'] ?? 0,
                    'training_program' => $app['certification_program'] ?? null,
                    'certification_status' => ((int) ($app['certification_interest'] ?? 0) === 1) ? 'interested' : 'not_started',
                    'availability' => $app['availability'] ?? null,
                ]);
                $pdo->prepare("
                    UPDATE recruitment_applications
                    SET status = 'approved', review_notes = ?, reviewed_by = ?, reviewed_at = NOW(), user_id = ?
                    WHERE id = ?
                ")->execute([$notes, $_SESSION['user_id'] ?? null, $userId, $id]);
                $pdo->commit();

                natcodev_notify_user($pdo, $userId, 'recruitment_approved', 'NATCODEV Recruitment Approved', [
                    'role' => $roles[$app['role_applied']] ?? $app['role_applied'],
                    'app_ref' => $app['app_ref'],
                    'login_url' => app_base_url() . '/login.php',
                    'field_agent_url' => app_base_url() . '/field-agent/',
                    'temporary_password' => $temporaryPassword,
                ], "Your NATCODEV recruitment application {$app['app_ref']} has been approved. Login: " . app_base_url() . "/login.php Temporary password: {$temporaryPassword}. Field agent console: " . app_base_url() . "/field-agent/");
                $message = 'Application approved and staff user created.';
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }
        } elseif (isset($statuses[$action])) {
            $pdo->prepare("
                UPDATE recruitment_applications
                SET status = ?, review_notes = ?, reviewed_by = ?, reviewed_at = NOW()
                WHERE id = ?
            ")->execute([$action, $notes, $_SESSION['user_id'] ?? null, $id]);
            $message = 'Recruitment status updated.';
        }
    }
}

$filter = (string) ($_GET['status'] ?? 'pending');
if ($filter !== 'all' && !isset($statuses[$filter])) {
    $filter = 'pending';
}
$page = admin_current_page();
$perPage = admin_per_page(50);
$offset = admin_pagination_offset($page, $perPage);
$params = [];
$where = '1=1';
if ($filter !== 'all') {
    $where .= ' AND status = ?';
    $params[] = $filter;
}
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM recruitment_applications WHERE {$where}");
$countStmt->execute($params);
$totalApplications = (int) $countStmt->fetchColumn();

$stmt = $pdo->prepare("SELECT * FROM recruitment_applications WHERE {$where} ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}");
$stmt->execute($params);
$applications = $stmt->fetchAll();

admin_page_start('Recruitment', [
    'active' => 'recruitment.php',
    'description' => 'Review Field Agent, Agronomist, and Agric Extensionist applications before creating staff access.',
    'wide' => true,
    'action_html' => '<a class="button secondary" href="../recruitment.php" target="_blank">Public Recruitment Form</a>',
]);
?>
<?php if ($message): ?><div class="notice ok"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>

<form class="toolbar panel" method="get">
  <input type="hidden" name="page" value="1">
  <select name="status">
    <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All Statuses</option>
    <?php foreach ($statuses as $value => $label): ?>
      <option value="<?= e($value) ?>" <?= $filter === $value ? 'selected' : '' ?>><?= e($label) ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit">Filter</button>
</form>

<?= admin_pagination_controls($totalApplications, $page, $perPage) ?>
<table>
  <thead><tr><th>Applicant</th><th>Role</th><th>Location</th><th>Experience</th><th>Training</th><th>Status</th><th>Documents</th><th>Review</th></tr></thead>
  <tbody>
    <?php foreach ($applications as $app): ?>
      <tr>
        <td>
          <strong><?= e($app['name']) ?></strong><br>
          <small><?= e($app['app_ref']) ?><br><?= e($app['email']) ?><br><?= e($app['phone']) ?></small>
        </td>
        <td><?= e($roles[$app['role_applied']] ?? $app['role_applied']) ?><br><small><?= e($app['qualification'] ?? '') ?></small></td>
        <td><?= e($app['state']) ?><?= $app['lga'] ? ', ' . e($app['lga']) : '' ?></td>
        <td><?= e((string) $app['experience_years']) ?> years<br><small><?= e($app['availability'] ?? '') ?></small></td>
        <td>
          <?php if ((int) ($app['certification_interest'] ?? 0) === 1): ?>
            <span class="badge pending">Interested</span><br><small><?= e($app['certification_program'] ?? 'Training certification') ?></small>
          <?php else: ?>
            <span class="muted">No interest marked</span>
          <?php endif; ?>
        </td>
        <td><span class="badge <?= e((string) $app['status']) ?>"><?= e($statuses[$app['status']] ?? $app['status']) ?></span></td>
        <td>
          <?php if (!empty($app['cv_path'])): ?><a href="../<?= e($app['cv_path']) ?>" target="_blank">CV</a><br><?php endif; ?>
          <?php if (!empty($app['id_path'])): ?><a href="../<?= e($app['id_path']) ?>" target="_blank">ID/License</a><?php endif; ?>
        </td>
        <td>
          <?php if (!empty($app['cover_note'])): ?><small><?= e($app['cover_note']) ?></small><?php endif; ?>
          <form method="post" class="toolbar">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= (int) $app['id'] ?>">
            <select name="action">
              <option value="shortlisted">Shortlist</option>
              <option value="more_info">More Info</option>
              <option value="approve">Approve & Create User</option>
              <option value="rejected">Reject</option>
            </select>
            <input type="text" name="review_notes" placeholder="Review notes">
            <button type="submit">Apply</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$applications): ?><tr><td colspan="8">No recruitment applications match this filter.</td></tr><?php endif; ?>
  </tbody>
</table>
<?= admin_pagination_controls($totalApplications, $page, $perPage) ?>
<?php admin_page_end(); ?>
