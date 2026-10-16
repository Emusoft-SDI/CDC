<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../lib/admin-layout.php';
require_once __DIR__ . '/../lib/enrollment-applications.php';

$pdo = db();
admin_ensure_schema($pdo);
enrollment_ensure_schema($pdo);
admin_require($pdo, 'user_management');

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid security token.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        $applicationId = (int) ($_POST['application_id'] ?? 0);
        $note = trim((string) ($_POST['note'] ?? ''));

        if (!in_array($action, ['approve', 'reject'], true)) {
            $error = 'Choose a valid review action.';
        } elseif ($applicationId <= 0) {
            $error = 'Unknown enrollment application.';
        } elseif ($action === 'reject' && $note === '') {
            $error = 'A note is required to reject an application.';
        } else {
            $adminUserId = (int) (admin_current_user_id($pdo) ?? 0);
            $result = $action === 'approve'
                ? enrollment_application_approve($pdo, $applicationId, $adminUserId, $note)
                : enrollment_application_reject($pdo, $applicationId, $adminUserId, $note);
            if (!empty($result['ok'])) {
                $message = (string) ($result['message'] ?? 'Application reviewed.');
            } else {
                $error = (string) ($result['message'] ?? 'Unable to review this application.');
            }
        }
    }
}

$applicantType = (string) ($_GET['applicant_type'] ?? '');
if (!in_array($applicantType, ['coordinator', 'cooperative', 'all'], true)) {
    $applicantType = 'all';
}
$status = (string) ($_GET['status'] ?? 'pending');
if (!in_array($status, ['pending', 'approved', 'rejected', 'all'], true)) {
    $status = 'pending';
}
$search = trim((string) ($_GET['search'] ?? ''));
$typeParam = $applicantType === 'all' ? '' : $applicantType;
$statusParam = $status === 'all' ? '' : $status;

$pendingCoordinatorCount = enrollment_application_count($pdo, 'coordinator', 'pending');
$pendingCooperativeCount = enrollment_application_count($pdo, 'cooperative', 'pending');

$page = admin_current_page();
$perPage = admin_per_page(50);
$offset = admin_pagination_offset($page, $perPage);
$total = enrollment_application_count($pdo, $typeParam, $statusParam, $search);
$applications = enrollment_application_list($pdo, $typeParam, $statusParam, $search, $perPage, $offset);

function enrollment_enclave_summary(array $row): string
{
    $parts = array_values(array_filter(array_map('trim', [
        (string) ($row['state'] ?? ''),
        (string) ($row['lga'] ?? ''),
        (string) ($row['district'] ?? ''),
        (string) ($row['village'] ?? ''),
    ]), static fn (string $part): bool => $part !== ''));
    return $parts === [] ? '—' : implode(' / ', $parts);
}

admin_page_start('Enrollment Applications', [
    'active' => 'coordinator-applications.php',
    'description' => 'Review coordinator and cooperative enrollment applications. Approving activates the account and emails a claim link.',
    'wide' => true,
]);
?>
<?php if ($message): ?><div class="notice ok"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>

<section class="stats">
  <div class="stat"><span>Pending Coordinator Applications</span><div class="metric"><?= (int) $pendingCoordinatorCount ?></div></div>
  <div class="stat"><span>Pending Cooperative Applications</span><div class="metric"><?= (int) $pendingCooperativeCount ?></div></div>
</section>

<section class="layout">
  <section>
    <form class="panel" method="get">
      <h2>Find Enrollment Applications</h2>
      <input type="hidden" name="page" value="1">
      <label>Applicant Type</label>
      <select name="applicant_type">
        <option value="all" <?= $applicantType === 'all' ? 'selected' : '' ?>>All Applicants</option>
        <option value="coordinator" <?= $applicantType === 'coordinator' ? 'selected' : '' ?>>Coordinators</option>
        <option value="cooperative" <?= $applicantType === 'cooperative' ? 'selected' : '' ?>>Cooperatives</option>
      </select>
      <label>Status</label>
      <select name="status">
        <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All Statuses</option>
        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
        <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Approved</option>
        <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
      </select>
      <label>Search</label>
      <input type="search" name="search" value="<?= e($search) ?>" placeholder="name, email, cooperative">
      <input type="hidden" name="per_page" value="<?= (int) $perPage ?>">
      <div class="actions">
        <button type="submit">Filter Applications</button>
        <a class="button secondary" href="coordinator-applications.php">Clear</a>
      </div>
      <p class="meta">New applications are pending until approved. Approval activates the account, assigns the coordinator tier or cooperative, and emails an account claim link.</p>
    </form>

    <?= admin_pagination_controls($total, $page, $perPage) ?>
    <table>
      <thead><tr><th>Applicant</th><th>Coordinator Tier</th><th>Enclave</th><th>Cooperative</th><th>Reason</th><th>Submitted</th><th>Status</th><th>Review</th></tr></thead>
      <tbody>
        <?php foreach ($applications as $row): ?>
          <?php
            $isCoordinator = (string) ($row['applicant_type'] ?? '') === 'coordinator';
            $isPending = (string) ($row['status'] ?? '') === 'pending';
            $tierLabel = $isCoordinator && ((string) ($row['platform_role'] ?? '') !== '')
                ? ucwords(str_replace('_', ' ', (string) $row['platform_role']))
                : '—';
            $reason = (string) ($row['reason'] ?? '');
            $reasonSummary = $reason === '' ? '' : (strlen($reason) > 140 ? substr($reason, 0, 140) . '…' : $reason);
          ?>
          <tr>
            <td>
              <span class="badge <?= $isCoordinator ? 'text-bg-info' : 'text-bg-warning' ?>"><?= e($isCoordinator ? 'Coordinator' : 'Cooperative') ?></span><br>
              <strong><?= e((string) ($row['name'] ?? '')) ?></strong><br>
              <small><?= e((string) ($row['email'] ?? '')) ?></small>
              <?php if (!empty($row['phone'])): ?><br><small><?= e((string) $row['phone']) ?></small><?php endif; ?>
            </td>
            <td><?= e($tierLabel) ?></td>
            <td><small><?= e(enrollment_enclave_summary($row)) ?></small></td>
            <td>
              <?php if (!$isCoordinator): ?>
                <strong><?= e((string) ($row['cooperative_name'] ?? '')) ?></strong>
                <?php if (!empty($row['cooperative_registration_number'])): ?><br><small>CAC: <?= e((string) $row['cooperative_registration_number']) ?></small><?php endif; ?>
                <?php if (!empty($row['cooperative_members_count'])): ?><br><small>Members: <?= (int) $row['cooperative_members_count'] ?></small><?php endif; ?>
              <?php else: ?>
                <span class="muted">—</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($reason !== ''): ?>
                <small title="<?= e($reason) ?>"><?= e($reasonSummary) ?></small>
              <?php else: ?>
                <span class="muted">—</span>
              <?php endif; ?>
            </td>
            <td><small><?= e(date('Y-m-d H:i', (int) strtotime((string) ($row['created_at'] ?? 'now')))) ?></small></td>
            <td>
              <span class="badge <?= (string) $row['status'] === 'approved' ? 'verified' : ((string) $row['status'] === 'rejected' ? 'danger' : 'pending') ?>"><?= e(ucfirst((string) $row['status'])) ?></span>
              <?php if (!$isPending && !empty($row['review_note'])): ?><br><small>Note: <?= e((string) $row['review_note']) ?></small><?php endif; ?>
            </td>
            <td>
              <?php if ($isPending): ?>
                <div class="toolbar" style="margin:0;">
                  <form method="post">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="application_id" value="<?= (int) $row['id'] ?>">
                    <textarea name="note" rows="1" placeholder="Approval note (optional)"></textarea>
                    <div class="actions"><button type="submit" data-busy-text="Approving application...">Approve</button></div>
                  </form>
                  <form method="post" onsubmit="return confirm('Reject this enrollment application? A note is required.');">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="application_id" value="<?= (int) $row['id'] ?>">
                    <textarea name="note" rows="1" placeholder="Rejection note (required)" required></textarea>
                    <div class="actions"><button class="danger" type="submit" data-busy-text="Rejecting application...">Reject</button></div>
                  </form>
                </div>
              <?php else: ?>
                <span class="muted">Reviewed</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$applications): ?><tr><td colspan="8">No enrollment applications match the current filters.</td></tr><?php endif; ?>
      </tbody>
    </table>
    <?= admin_pagination_controls($total, $page, $perPage) ?>
  </section>
</section>
<?php admin_page_end(); ?>
