<?php
declare(strict_types=1);

/**
 * Admin job worker (Phase 5). CLI only.
 * Usage: php cron/admin-jobs.php [limit]
 *
 * Handlers are registered in lib/admin-jobs.php. Records a heartbeat so the
 * health page can detect a stalled cron.
 */

require_once __DIR__ . '/../config.php';
app_require_cli('admin job worker');

require_once __DIR__ . '/../lib/admin-queue.php';

$pdo = db();

$handlersFile = __DIR__ . '/../lib/admin-jobs.php';
if (is_file($handlersFile)) {
    require_once $handlersFile;
}

$limit = max(1, min(200, (int) ($argv[1] ?? 25)));
$processed = admin_run_jobs($pdo, $limit);
admin_cron_heartbeat($pdo, 'admin-jobs');

echo '[' . date('Y-m-d H:i:s') . "] admin-jobs: processed {$processed} job(s), depth=" . admin_queue_depth($pdo) . "\n";
