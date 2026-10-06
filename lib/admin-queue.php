<?php
declare(strict_types=1);

/**
 * Durable job queue + idempotency primitive (Phase 5, additive).
 *
 * Long-running work (exports, bulk imports, broadcasts) can be queued instead of
 * running inline. Nothing existing is rewired; callers opt in. Run workers via
 * `php cron/admin-jobs.php`.
 */

function admin_ensure_queue_schema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admin_jobs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            job_type VARCHAR(100) NOT NULL,
            payload_json MEDIUMTEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'queued',
            attempts INT NOT NULL DEFAULT 0,
            max_attempts INT NOT NULL DEFAULT 3,
            run_at INT NOT NULL,
            locked_at INT NULL,
            last_error TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_jobs_status (status, run_at),
            INDEX idx_jobs_type (job_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'admin_jobs');

    foreach ([
        'queue_name' => "VARCHAR(40) NOT NULL DEFAULT 'default'",
        'result_json' => 'MEDIUMTEXT NULL',
        'progress' => 'INT NOT NULL DEFAULT 0',
    ] as $column => $definition) {
        app_add_column_if_missing($pdo, 'admin_jobs', $column, $definition);
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admin_idempotency (
            idem_key VARCHAR(191) PRIMARY KEY,
            result_json MEDIUMTEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

/**
 * Record a worker heartbeat so the health page can detect stalled cron.
 */
function admin_cron_heartbeat(PDO $pdo, string $worker = 'admin-jobs'): void
{
    if (!function_exists('admin_ensure_metrics_schema')) {
        $lib = __DIR__ . '/admin-metrics.php';
        if (is_file($lib)) {
            require_once $lib;
        }
    }
    if (!function_exists('admin_ensure_metrics_schema')) {
        return;
    }
    admin_ensure_metrics_schema($pdo);
    try {
        $pdo->prepare('INSERT INTO admin_metrics (metric_key, value_json, computed_at, ttl) VALUES (?, ?, ?, ?)'
            . ' ON DUPLICATE KEY UPDATE value_json = VALUES(value_json), computed_at = VALUES(computed_at), ttl = VALUES(ttl)')
            ->execute(['cron:heartbeat', json_encode(['worker' => $worker]), time(), 7200]);
    } catch (Throwable $e) {
        // Non-fatal.
    }
}

/** Decode a stored job payload. @return array<string,mixed> */
function admin_job_payload(array $job): array
{
    $payload = json_decode((string) ($job['payload_json'] ?? ''), true);
    return is_array($payload) ? $payload : [];
}

/** @param array<string,mixed> $payload */
function admin_enqueue(PDO $pdo, string $jobType, array $payload = [], int $delaySeconds = 0): int
{
    admin_ensure_queue_schema($pdo);
    $pdo->prepare('INSERT INTO admin_jobs (job_type, payload_json, status, run_at) VALUES (?, ?, ?, ?)')
        ->execute([$jobType, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 'queued', time() + max(0, $delaySeconds)]);
    return (int) $pdo->lastInsertId();
}

function admin_register_job_handler(string $jobType, callable $handler): void
{
    $GLOBALS['__admin_job_handlers'][$jobType] = $handler;
}

/** @return array<string,callable> */
function admin_job_handlers(): array
{
    return $GLOBALS['__admin_job_handlers'] ?? [];
}

function admin_queue_depth(PDO $pdo): int
{
    if (!app_table_exists($pdo, 'admin_jobs')) {
        return 0;
    }
    try {
        return (int) $pdo->query("SELECT COUNT(*) FROM admin_jobs WHERE status IN ('queued','running')")->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * Process up to $limit due jobs. Returns the number processed.
 */
function admin_run_jobs(PDO $pdo, int $limit = 20): int
{
    admin_ensure_queue_schema($pdo);
    $handlers = admin_job_handlers();
    $limit = max(1, min(200, $limit));

    $rows = $pdo->query(
        "SELECT * FROM admin_jobs WHERE status = 'queued' AND run_at <= " . time()
        . " ORDER BY run_at ASC, id ASC LIMIT {$limit}"
    )->fetchAll(PDO::FETCH_ASSOC);

    $processed = 0;
    foreach ($rows as $job) {
        $id = (int) $job['id'];
        $lock = $pdo->prepare("UPDATE admin_jobs SET status='running', locked_at=?, attempts=attempts+1 WHERE id=? AND status='queued'");
        $lock->execute([time(), $id]);
        if ($lock->rowCount() !== 1) {
            continue; // Another worker grabbed it.
        }

        $type = (string) $job['job_type'];
        $payload = json_decode((string) ($job['payload_json'] ?? ''), true);
        $payload = is_array($payload) ? $payload : [];

        try {
            if (!isset($handlers[$type])) {
                throw new RuntimeException('No handler registered for job type ' . $type);
            }
            $result = ($handlers[$type])($pdo, $payload);
            $pdo->prepare("UPDATE admin_jobs SET status='done', progress=100, result_json=?, last_error=NULL, locked_at=NULL WHERE id=?")
                ->execute([is_array($result) ? json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null, $id]);
        } catch (Throwable $e) {
            $attempts = (int) $job['attempts'] + 1;
            $maxAttempts = max(1, (int) $job['max_attempts']);
            $failed = $attempts >= $maxAttempts;
            $pdo->prepare('UPDATE admin_jobs SET status=?, run_at=?, last_error=?, locked_at=NULL WHERE id=?')
                ->execute([$failed ? 'failed' : 'queued', time() + min(3600, 30 * $attempts), mb_substr($e->getMessage(), 0, 1000), $id]);
        }
        $processed++;
    }

    return $processed;
}

/**
 * Run $fn once for a given key. Repeated calls with the same key return the cached
 * result without re-running. Generalizes the wallet idempotency pattern.
 *
 * @param callable():mixed $fn
 * @return mixed
 */
function admin_idempotent(PDO $pdo, string $key, callable $fn)
{
    admin_ensure_queue_schema($pdo);
    $key = mb_substr($key, 0, 191);

    try {
        $stmt = $pdo->prepare('SELECT result_json FROM admin_idempotency WHERE idem_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $cached = $stmt->fetchColumn();
        if ($cached !== false) {
            return json_decode((string) $cached, true);
        }
    } catch (Throwable $e) {
        // Fall through and run.
    }

    $result = $fn();

    try {
        $pdo->prepare('INSERT IGNORE INTO admin_idempotency (idem_key, result_json) VALUES (?, ?)')
            ->execute([$key, json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
    } catch (Throwable $e) {
        // Non-fatal.
    }

    return $result;
}
