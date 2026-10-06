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

<?= admin_kpi_grid([
    ['Readiness', (int) $readiness . '%', 'Production level', 'fa-shield-halved', $readiness >= 80 ? '' : 'orange'],
    ['Policies', number_format((int) $policyTotal), 'In the register', 'fa-book', ''],
    ['Approved', number_format((int) $approved), 'Active policies', 'fa-circle-check', 'blue'],
    ['Due Soon', number_format((int) $due), 'Review within 30 days', 'fa-clock', 'red'],
    ['Backup Manifests', number_format((int) $backupCount), 'Disaster recovery', 'fa-database', 'purple'],
]) ?>

<details class="collapse-card"<?= $error !== '' ? ' open' : '' ?>>
  <summary>
    <span class="cc-icon"><i class="fas fa-book"></i></span>
    <span class="collapse-title">Policy Register<small>Create or update a governance policy</small></span>
    <span class="caret"><i class="fas fa-chevron-down"></i></span>
  </summary>
  <div class="collapse-body">
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="save_policy">
      <div class="field-grid">
        <label class="field"><span>Policy Key</span><input name="policy_key" placeholder="e.g. emergency_access"></label>
        <label class="field"><span>Title</span><input name="title" required></label>
        <label class="field"><span>Category</span><input name="category" value="security"></label>
        <label class="field"><span>Status</span><select name="status"><option value="draft">Draft</option><option value="approved">Approved</option><option value="expired">Expired</option></select></label>
        <label class="field"><span>Owner Role</span><input name="owner_role" value="Super Admin"></label>
        <label class="field"><span>Review Frequency (days)</span><input name="review_frequency_days" value="180" inputmode="numeric"></label>
        <label class="field"><span>Summary</span><textarea name="summary"></textarea></label>
      </div>
      <div class="actions"><button type="submit"><i class="fas fa-floppy-disk"></i> Save Policy</button></div>
    </form>
  </div>
</details>

<section class="panel">
  <div class="user-toolbar">
    <h2 style="margin:0">Security, Compliance &amp; DR Controls</h2>
    <span class="meta"><?= count($policies) ?> policy(ies)</span>
  </div>
  <div class="record-list">
    <?php foreach ($policies as $policy): ?>
      <?php
        $policyTone = ['approved' => 'ok', 'draft' => 'warn', 'expired' => 'bad'][(string) $policy['status']] ?? 'muted';
        $reviewDue = !empty($policy['next_review_at']) && strtotime((string) $policy['next_review_at']) <= strtotime('+30 days');
      ?>
      <article class="record-row">
        <span class="record-avatar doc"><i class="fas fa-file-lines"></i></span>
        <div class="record-main">
          <div class="record-title">
            <?= e($policy['title']) ?>
            <span class="tag <?= e($policyTone) ?>"><?= e(ucwords((string) $policy['status'])) ?></span>
            <span class="tag info"><?= e($policy['category']) ?></span>
          </div>
          <div class="record-excerpt"><?= e(mb_strimwidth((string) $policy['summary'], 0, 160, '...')) ?></div>
        </div>
        <div class="record-meta">
          <?php if (!empty($policy['next_review_at'])): ?>
            <span class="record-sub"><i class="far fa-calendar-check"></i> Next review <?= e(date('M j, Y', strtotime((string) $policy['next_review_at']))) ?></span>
            <?php if ($reviewDue): ?><span class="tag warn">Due soon</span><?php endif; ?>
          <?php else: ?>
            <span class="record-sub">No review scheduled</span>
          <?php endif; ?>
        </div>
        <div class="record-actions">
          <?php if (!empty($policy['policy_key'])): ?><span class="ref-pill"><?= e((string) $policy['policy_key']) ?></span><?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
    <?php if (!$policies): ?><div class="record-empty">No policies in the register yet.</div><?php endif; ?>
  </div>
</section>
<?php admin_page_end(); ?>
