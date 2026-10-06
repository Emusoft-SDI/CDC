<?php
declare(strict_types=1);

/**
 * Admin health report (Phase 6, additive).
 *
 * Returns a structured, machine-readable snapshot. Shared by admin/health.php
 * (HTML + JSON) and any probe/monitoring consumer.
 */

function admin_health_report(PDO $pdo): array
{
    $checks = [];

    // Database reachability + version.
    try {
        $version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
        $checks['database'] = ['ok' => true, 'label' => 'Database', 'detail' => 'MySQL ' . $version];
    } catch (Throwable $e) {
        $checks['database'] = ['ok' => false, 'label' => 'Database', 'detail' => 'Unreachable'];
    }

    // PHP runtime.
    $checks['php'] = [
        'ok' => version_compare(PHP_VERSION, '8.1.0', '>='),
        'label' => 'PHP runtime',
        'detail' => PHP_VERSION,
    ];

    // Disk space on the project volume.
    $free = @disk_free_space(dirname(__DIR__));
    $freeGb = $free !== false ? round($free / 1073741824, 1) : null;
    $checks['disk'] = [
        'ok' => $free === false ? true : $free > 268435456,
        'label' => 'Disk space',
        'detail' => $freeGb === null ? 'unknown' : $freeGb . ' GB free',
    ];

    // Writable storage.
    foreach (['uploads' => dirname(__DIR__) . '/uploads', 'exports' => dirname(__DIR__) . '/win-private/exports'] as $key => $dir) {
        $exists = is_dir($dir);
        $writable = $exists ? is_writable($dir) : is_writable(dirname($dir));
        $checks['storage_' . $key] = [
            'ok' => $writable,
            'label' => ucfirst($key) . ' storage',
            'detail' => ($exists ? 'present' : 'missing') . ', ' . ($writable ? 'writable' : 'NOT writable'),
        ];
    }

    // Mail transport.
    $transport = (string) app_env('MAIL_TRANSPORT', 'log');
    $checks['mail'] = ['ok' => $transport !== '', 'label' => 'Mail transport', 'detail' => $transport];

    // Queue health.
    $queueDepth = function_exists('admin_queue_depth') ? admin_queue_depth($pdo) : 0;
    $oldest = 0;
    $failed = 0;
    if (app_table_exists($pdo, 'admin_jobs')) {
        try {
            $oldest = (int) $pdo->query("SELECT COALESCE(MAX(0, UNIX_TIMESTAMP() - MIN(run_at)), 0) FROM admin_jobs WHERE status = 'queued'")->fetchColumn();
            $failed = (int) $pdo->query("SELECT COUNT(*) FROM admin_jobs WHERE status = 'failed'")->fetchColumn();
        } catch (Throwable $e) {
            // ignore
        }
    }
    $checks['queue'] = [
        'ok' => $oldest < 900,
        'label' => 'Job queue',
        'detail' => 'depth=' . $queueDepth . ', oldest=' . $oldest . 's, failed=' . $failed,
    ];

    // Cron heartbeat (written by cron workers).
    $heartbeat = 0;
    if (app_table_exists($pdo, 'admin_metrics')) {
        try {
            $stmt = $pdo->prepare('SELECT computed_at FROM admin_metrics WHERE metric_key = ? LIMIT 1');
            $stmt->execute(['cron:heartbeat']);
            $heartbeat = (int) $stmt->fetchColumn();
        } catch (Throwable $e) {
            // ignore
        }
    }
    $checks['cron'] = [
        'ok' => $heartbeat > 0 && (time() - $heartbeat) < 3600,
        'label' => 'Cron heartbeat',
        'detail' => $heartbeat > 0 ? (time() - $heartbeat) . 's ago' : 'never recorded',
    ];

    // Schema flags: additive columns/tables present.
    $requiredTables = ['users', 'applications', 'audit_log'];
    $missingTables = array_values(array_filter($requiredTables, static fn(string $t): bool => !app_table_exists($pdo, $t)));
    $auditV2 = app_table_exists($pdo, 'audit_log') && app_column_exists($pdo, 'audit_log', 'request_id');
    $checks['schema'] = [
        'ok' => $missingTables === [] && $auditV2,
        'label' => 'Schema',
        'detail' => $missingTables !== [] ? 'missing: ' . implode(',', $missingTables) : ($auditV2 ? 'core tables + audit v2' : 'audit v2 columns pending'),
    ];

    // Search index + metrics cache size.
    $searchRows = 0;
    if (app_table_exists($pdo, 'admin_search_index')) {
        try { $searchRows = (int) $pdo->query('SELECT COUNT(*) FROM admin_search_index')->fetchColumn(); } catch (Throwable $e) {}
    }
    $metricRows = 0;
    if (app_table_exists($pdo, 'admin_metrics')) {
        try { $metricRows = (int) $pdo->query('SELECT COUNT(*) FROM admin_metrics')->fetchColumn(); } catch (Throwable $e) {}
    }
    $checks['indexes'] = [
        'ok' => true,
        'label' => 'Indexes & cache',
        'detail' => 'search rows=' . $searchRows . ', cached metrics=' . $metricRows,
    ];

    $healthy = true;
    foreach ($checks as $check) {
        if (empty($check['ok'])) {
            $healthy = false;
            break;
        }
    }

    return [
        'ok' => $healthy,
        'request_id' => function_exists('admin_request_id') ? admin_request_id() : null,
        'timestamp' => date(DATE_ATOM),
        'checks' => $checks,
    ];
}
