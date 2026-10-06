<?php
declare(strict_types=1);

/**
 * Job queue dashboard (Phase 5, complete).
 * View queued/running/done/failed jobs, enqueue real work, and drain the queue.
 */

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../lib/admin-layout.php';
require_once __DIR__ . '/../lib/admin-queue.php';
require_once __DIR__ . '/../lib/admin-search.php';
require_once __DIR__ . '/../lib/admin-audit-v2.php';
require_once __DIR__ . '/../lib/admin-jobs.php';

$pdo = db();
admin_ensure_schema($pdo);
admin_ensure_queue_schema($pdo);
admin_require($pdo, 'settings');

$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid security token.';
    } else {
        $task = (string) ($_POST['task'] ?? '');
        try {
            if ($task === 'run') {
                $processed = admin_run_jobs($pdo, 25);
                admin_cron_heartbeat($pdo, 'manual');
                $notice = "Worker processed {$processed} job(s).";
            } elseif ($task === 'reindex') {
                $id = admin_enqueue($pdo, 'search.reindex', ['limit' => 5000]);
                admin_audit_event($pdo, 'job_enqueued', 'admin_jobs', $id, [], ['type' => 'search.reindex'], 'success', 'Search reindex queued from dashboard');
                $notice = "Search reindex queued (job #{$id}).";
            } elseif ($task === 'export') {
                $type = (string) ($_POST['type'] ?? '');
                if (!isset(admin_export_tables()[$type])) {
                    $error = 'Choose a valid export.';
                } else {
                    $id = admin_enqueue($pdo, 'export.table', ['type' => $type]);
                    admin_audit_event($pdo, 'job_enqueued', 'admin_jobs', $id, [], ['type' => 'export.table', 'table' => $type], 'success', 'Export queued from dashboard');
                    $notice = 'Export queued (job #' . $id . '). Run the worker to generate it.';
                }
            } else {
                $error = 'Unknown task.';
            }
        } catch (Throwable $e) {
            $error = 'Task failed: ' . $e->getMessage();
        }
    }
}

$status = (string) ($_GET['status'] ?? 'all');
$where = '1=1';
$params = [];
if (in_array($status, ['queued', 'running', 'done', 'failed'], true)) {
    $where = 'status = ?';
    $params[] = $status;
} else {
    $status = 'all';
}

$page = admin_current_page();
$perPage = admin_per_page(50);
$offset = admin_pagination_offset($page, $perPage);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM admin_jobs WHERE {$where}");
$countStmt->execute($params);
$totalJobs = (int) $countStmt->fetchColumn();

$stmt = $pdo->prepare("SELECT * FROM admin_jobs WHERE {$where} ORDER BY id DESC LIMIT {$perPage} OFFSET {$offset}");
$stmt->execute($params);
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$depth = admin_queue_depth($pdo);
$failedCount = (int) $pdo->query("SELECT COUNT(*) FROM admin_jobs WHERE status = 'failed'")->fetchColumn();

admin_page_start('Job Queue', [
    'active' => 'jobs.php',
    'description' => 'Background work: exports, broadcasts and search reindexing. Durable, retried and audited.',
    'wide' => true,
]);
?>
<?php if ($notice): ?><div class="notice ok"><?= e($notice) ?></div><?php endif; ?>
<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>

<?= admin_kpi_grid([
    ['Active jobs', number_format($depth), 'Queued + running', 'fa-list-check', ''],
    ['Failed', number_format($failedCount), 'Needs review', 'fa-triangle-exclamation', $failedCount > 0 ? 'red' : ''],
    ['Total logged', number_format($totalJobs), 'All statuses', 'fa-clock-rotate-left', 'blue'],
]) ?>

<section class="panel">
  <form method="post" class="toolbar" style="margin:0;flex-wrap:wrap;gap:10px">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <button type="submit" name="task" value="run"><i class="fas fa-play"></i> Run worker now</button>
    <button type="submit" name="task" value="reindex"><i class="fas fa-magnifying-glass"></i> Reindex search</button>
    <label style="margin:0">Export
      <select name="type">
        <?php foreach (admin_export_tables() as $key => $label): ?>
          <option value="<?= e($key) ?>"><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <button type="submit" name="task" value="export"><i class="fas fa-file-csv"></i> Queue export</button>
  </form>

  <form method="get" class="toolbar" style="margin:12px 0 0">
    <label style="margin:0">Status
      <select name="status" onchange="this.form.submit()">
        <?php foreach (['all' => 'All', 'queued' => 'Queued', 'running' => 'Running', 'done' => 'Done', 'failed' => 'Failed'] as $key => $label): ?>
          <option value="<?= e($key) ?>" <?= $status === $key ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
  </form>

  <?= admin_pagination_controls($totalJobs, $page, $perPage) ?>

  <div class="record-list">
    <?php foreach ($jobs as $job): ?>
      <?php
        $jobStatus = (string) $job['status'];
        $tone = $jobStatus === 'failed' ? 'bad' : ($jobStatus === 'done' ? 'ok' : ($jobStatus === 'running' ? 'info' : 'warn'));
        $payload = admin_job_payload($job);
      ?>
      <article class="record-row">
        <span class="record-avatar log"><i class="fas fa-gears"></i></span>
        <div class="record-main">
          <div class="record-title">
            #<?= (int) $job['id'] ?> · <?= e((string) $job['job_type']) ?>
            <span class="tag <?= e($tone) ?>"><?= e(ucfirst($jobStatus)) ?></span>
            <span class="tag muted">attempts <?= (int) $job['attempts'] ?>/<?= (int) $job['max_attempts'] ?></span>
          </div>
          <?php if ($payload): ?>
            <div class="record-excerpt"><strong>payload</strong> <?= e(mb_strimwidth(json_encode($payload, JSON_UNESCAPED_SLASHES) ?: '', 0, 160, '...')) ?></div>
          <?php endif; ?>
          <?php if (!empty($job['result_json'])): ?>
            <div class="record-excerpt"><strong>result</strong> <?= e(mb_strimwidth((string) $job['result_json'], 0, 160, '...')) ?></div>
          <?php endif; ?>
          <?php if (!empty($job['last_error'])): ?>
            <div class="record-excerpt" style="color:#b42318"><i class="fas fa-triangle-exclamation"></i> <?= e(mb_strimwidth((string) $job['last_error'], 0, 180, '...')) ?></div>
          <?php endif; ?>
        </div>
        <div class="record-actions">
          <span class="ref-pill"><i class="far fa-clock"></i> <?= e(date('M j, H:i', strtotime((string) $job['created_at']))) ?></span>
        </div>
      </article>
    <?php endforeach; ?>
    <?php if (!$jobs): ?><div class="record-empty">No jobs recorded yet. Queue an export or reindex above.</div><?php endif; ?>
  </div>
  <?= admin_pagination_controls($totalJobs, $page, $perPage) ?>
</section>
<?php admin_page_end(); ?>
