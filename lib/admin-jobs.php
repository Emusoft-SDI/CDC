<?php
declare(strict_types=1);

/**
 * Admin job handlers (Phase 5, complete).
 *
 * Registers concrete, real workloads on the queue plus the admin actions that
 * enqueue them. Loaded by cron/admin-jobs.php (worker) and admin/jobs.php
 * (dashboard). Safe to require multiple times (registrations are idempotent).
 */

require_once __DIR__ . '/admin-queue.php';
require_once __DIR__ . '/admin-search.php';
if (is_file(__DIR__ . '/admin-actions.php')) {
    require_once __DIR__ . '/admin-actions.php';
}
if (is_file(__DIR__ . '/admin-capabilities.php')) {
    require_once __DIR__ . '/admin-capabilities.php';
}
if (is_file(__DIR__ . '/admin-audit-v2.php')) {
    require_once __DIR__ . '/admin-audit-v2.php';
}

/** Whitelisted exportable tables. */
function admin_export_tables(): array
{
    return [
        'users' => 'Users',
        'applications' => 'Applications',
        'provider_registry' => 'Providers',
        'audit_log' => 'Audit log',
        'notification_logs' => 'Notification log',
        'marketplace_orders' => 'Marketplace orders',
    ];
}

function admin_export_dir(): string
{
    $dir = dirname(__DIR__) . '/win-private/exports';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

/**
 * Escape a CSV cell, guarding against spreadsheet formula injection.
 */
function admin_export_csv_cell($value): string
{
    $value = (string) ($value ?? '');
    if ($value !== '' && preg_match('/^[=+\-@\t\r]/', $value)) {
        $value = "'" . $value;
    }
    return '"' . str_replace('"', '""', $value) . '"';
}

// ---------------------------------------------------------------------------
// Handlers
// ---------------------------------------------------------------------------

admin_register_job_handler('search.reindex', static function (PDO $pdo, array $payload): array {
    $indexed = admin_search_reindex($pdo, (int) ($payload['limit'] ?? 3000));
    return ['indexed' => $indexed];
});

admin_register_job_handler('export.table', static function (PDO $pdo, array $payload): array {
    $type = (string) ($payload['type'] ?? '');
    $allowed = admin_export_tables();
    if (!isset($allowed[$type])) {
        throw new RuntimeException('Unknown export type: ' . $type);
    }
    if (!app_table_exists($pdo, $type)) {
        throw new RuntimeException('Table not available: ' . $type);
    }

    $limit = max(1, min(50000, (int) ($payload['limit'] ?? 20000)));
    $rows = $pdo->query("SELECT * FROM {$type} LIMIT {$limit}")->fetchAll(PDO::FETCH_ASSOC);

    $filename = $type . '-' . date('Ymd-His') . '.csv';
    $path = admin_export_dir() . '/' . $filename;
    $handle = fopen($path, 'wb');
    if ($handle === false) {
        throw new RuntimeException('Could not open export file for writing.');
    }
    if ($rows) {
        fputcsv($handle, array_keys($rows[0]));
        foreach ($rows as $row) {
            fputcsv($handle, array_map('admin_export_csv_cell', array_map(static fn($v): string => is_scalar($v) || $v === null ? (string) $v : json_encode($v), $row)));
        }
    }
    fclose($handle);

    return ['type' => $type, 'filename' => $filename, 'path' => $path, 'rows' => count($rows)];
});

admin_register_job_handler('notification.broadcast', static function (PDO $pdo, array $payload): array {
    $recipients = array_values(array_filter(array_map('trim', (array) ($payload['recipients'] ?? []))));
    $subject = (string) ($payload['subject'] ?? 'NATCODEV notice');
    $body = (string) ($payload['body'] ?? '');
    $channel = (string) ($payload['channel'] ?? 'email');
    $sent = 0;
    $failed = 0;

    foreach ($recipients as $recipient) {
        try {
            if ($channel === 'sms' && function_exists('sendSMSMessage')) {
                $ok = sendSMSMessage($recipient, $body);
            } elseif ($channel === 'whatsapp' && function_exists('sendWhatsAppMessage')) {
                $ok = sendWhatsAppMessage($recipient, $body);
            } else {
                $ok = function_exists('app_send_mail') ? app_send_mail($recipient, $subject, $body) : false;
            }
            $ok ? $sent++ : $failed++;
        } catch (Throwable $e) {
            $failed++;
        }
    }

    return ['channel' => $channel, 'sent' => $sent, 'failed' => $failed];
});

// ---------------------------------------------------------------------------
// Admin actions that enqueue work (used by admin/jobs.php and the palette)
// ---------------------------------------------------------------------------

if (function_exists('admin_register_action')) {
    admin_register_action('jobs.reindex', static function (PDO $pdo, array $input): array {
        $id = admin_enqueue($pdo, 'search.reindex', ['limit' => 5000]);
        if (function_exists('admin_audit_event')) {
            admin_audit_event($pdo, 'job_enqueued', 'admin_jobs', $id, [], ['type' => 'search.reindex'], 'success', 'Search reindex queued');
        }
        return ['ok' => true, 'job_id' => $id, 'queued' => 'search.reindex'];
    }, 'settings.manage');

    admin_register_action('jobs.export', static function (PDO $pdo, array $input): array {
        $type = (string) ($input['type'] ?? '');
        if (!isset(admin_export_tables()[$type])) {
            return ['ok' => false, 'status' => 422, 'error' => 'unknown_export_type'];
        }
        $id = admin_enqueue($pdo, 'export.table', ['type' => $type, 'limit' => (int) ($input['limit'] ?? 20000)]);
        if (function_exists('admin_audit_event')) {
            admin_audit_event($pdo, 'job_enqueued', 'admin_jobs', $id, [], ['type' => 'export.table', 'table' => $type], 'success', 'Export queued: ' . $type);
        }
        return ['ok' => true, 'job_id' => $id, 'queued' => 'export.table', 'type' => $type];
    }, 'reports.view');

    admin_register_action('jobs.run', static function (PDO $pdo, array $input): array {
        $processed = admin_run_jobs($pdo, (int) ($input['limit'] ?? 25));
        admin_cron_heartbeat($pdo, 'manual');
        return ['ok' => true, 'processed' => $processed, 'depth' => admin_queue_depth($pdo)];
    }, 'settings.manage');
}
