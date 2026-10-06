<?php
declare(strict_types=1);

/**
 * Admin search indexer (Phase 4/5). CLI only.
 * Usage: php cron/admin-search-index.php
 *
 * Kept as a direct cron entry point; the same work is available as the
 * 'search.reindex' queue job (see admin/jobs.php).
 */

require_once __DIR__ . '/../config.php';
app_require_cli('admin search indexer');

require_once __DIR__ . '/../lib/admin-search.php';
require_once __DIR__ . '/../lib/admin-queue.php';

$pdo = db();
$count = admin_search_reindex($pdo, 5000);
admin_cron_heartbeat($pdo, 'admin-search-index');

echo '[' . date('Y-m-d H:i:s') . "] admin-search-index: indexed {$count} record(s)\n";
