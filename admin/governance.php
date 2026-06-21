<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../lib/platform-governance.php';
require_once __DIR__ . '/../lib/disaster-recovery.php';

$pdo = db();
admin_ensure_schema($pdo);
admin_require($pdo);
pg_ensure_schema($pdo);
dr_ensure_schema($pdo);

$message = '';
$error = '';
$user = current_user($pdo) ?: [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid security token.';
    } else {
        try {
            $action = (string) ($_POST['action'] ?? '');
            if ($action === 'save_policy') {
                $pdo->prepare("
                    INSERT INTO platform_governance_policies
                        (policy_key, title, category, status, review_frequency_days, owner_role, summary, last_reviewed_at, next_review_at, updated_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? DAY), ?)
                    ON DUPLICATE KEY UPDATE
                        title = VALUES(title), category = VALUES(category), status = VALUES(status),
                        review_frequency_days = VALUES(review_frequency_days), owner_role = VALUES(owner_role),
                        summary = VALUES(summary), last_reviewed_at = NOW(), next_review_at = VALUES(next_review_at),
                        updated_by = VALUES(updated_by)
                ")->execute([
                    strtolower(preg_replace('/[^a-z0-9_]+/', '_', trim((string) ($_POST['policy_key'] ?? 'custom_policy')))),
                    trim((string) ($_POST['title'] ?? '')),
                    trim((string) ($_POST['category'] ?? 'governance')),
                    trim((string) ($_POST['status'] ?? 'draft')),
                    max(30, (int) ($_POST['review_frequency_days'] ?? 180)),
                    trim((string) ($_POST['owner_role'] ?? 'Super Admin')),
                    trim((string) ($_POST['summary'] ?? '')),
                    max(30, (int) ($_POST['review_frequency_days'] ?? 180)),
                    (int) ($user['id'] ?? 0),
                ]);
                $message = 'Governance policy saved and review date refreshed.';
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$policies = $pdo->query("SELECT * FROM platform_governance_policies ORDER BY category, title")->fetchAll();
$policyTotal = count($policies);
$approved = count(array_filter($policies, static fn($p): bool => (string) $p['status'] === 'approved'));
$due = count(array_filter($policies, static fn($p): bool => !empty($p['next_review_at']) && strtotime((string) $p['next_review_at']) <= strtotime('+30 days')));
$backupCount = app_table_exists($pdo, 'dr_backups') ? (int) $pdo->query("SELECT COUNT(*) FROM dr_backups")->fetchColumn() : 0;
$readiness = min(100, 35 + ($approved * 8) + ($backupCount > 0 ? 15 : 0) - ($due * 4));

admin_page_start('Governance & Production Readiness', [
    'active' => 'governance.php',
    'description' => 'Policy control for access, passwords, data retention, disaster recovery, notifications, compliance, and production readiness.',
    'wide' => true,
    'css' => '
      :root{--primary:#334155;--green:#475569;--green-dark:#1e293b;--bg:#f8fafc;}
      .governance-hero{background:linear-gradient(135deg,#f8fafc,#fff);border-left:5px solid #334155}
      .policy-status.approved{color:#0f6b3c}.policy-status.draft{color:#8a5a00}.policy-status.expired{color:#a32020}
    ',
]);
?>
<?php if ($message): ?><div class="notice ok"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>

<section class="panel governance-hero">
  <h2>Level 5 Production Readiness</h2>
  <p class="muted">This score tracks whether governance policies, disaster recovery, access controls, communication rules, and review cycles are in place.</p>
  <div class="progress"><div style="width:<?= (int) $readiness ?>%;"></div></div>
  <strong><?= (int) $readiness ?>% readiness</strong>
</section>

<section class="stats">
  <div class="stat"><div class="metric"><?= (int) $policyTotal ?></div><strong>Policies</strong></div>
  <div class="stat"><div class="metric"><?= (int) $approved ?></div><strong>Approved</strong></div>
  <div class="stat"><div class="metric"><?= (int) $due ?></div><strong>Due Soon</strong></div>
  <div class="stat"><div class="metric"><?= (int) $backupCount ?></div><strong>Backup Manifests</strong></div>
</section>

<section class="layout">
  <form class="panel" method="post">
    <h2>Policy Register</h2>
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="save_policy">
    <label>Policy Key<input name="policy_key" placeholder="e.g. emergency_access"></label>
    <label>Title<input name="title" required></label>
    <label>Category<input name="category" value="security"></label>
    <label>Status<select name="status"><option value="draft">Draft</option><option value="approved">Approved</option><option value="expired">Expired</option></select></label>
    <label>Owner Role<input name="owner_role" value="Super Admin"></label>
    <label>Review Frequency Days<input name="review_frequency_days" value="180" inputmode="numeric"></label>
    <label>Summary<textarea name="summary"></textarea></label>
    <button type="submit">Save Policy</button>
  </form>

  <section class="panel">
    <h2>Security, Compliance, and DR Controls</h2>
    <table>
      <thead><tr><th>Policy</th><th>Category</th><th>Status</th><th>Next Review</th></tr></thead>
      <tbody>
        <?php foreach ($policies as $policy): ?>
          <tr>
            <td><strong><?= e($policy['title']) ?></strong><br><span class="muted"><?= e($policy['summary']) ?></span></td>
            <td><?= e($policy['category']) ?></td>
            <td><span class="policy-status <?= e($policy['status']) ?>"><?= e(ucwords((string) $policy['status'])) ?></span></td>
            <td><?= $policy['next_review_at'] ? e(date('M j, Y', strtotime((string) $policy['next_review_at']))) : 'Not scheduled' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </section>
</section>
<?php admin_page_end(); ?>
