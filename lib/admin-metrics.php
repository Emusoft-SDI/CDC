<?php
declare(strict_types=1);

/**
 * Metrics cache (Phase 3, additive).
 *
 * A tiny DB-backed cache for expensive aggregate counts/sums. Callers compute on a
 * miss and the value is reused until the TTL lapses. Nothing existing is rewired;
 * this is opt-in for dashboards and the shell topbar.
 */

function admin_ensure_metrics_schema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admin_metrics (
            metric_key VARCHAR(140) PRIMARY KEY,
            value_json MEDIUMTEXT NULL,
            computed_at INT NOT NULL,
            ttl INT NOT NULL DEFAULT 300
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

/**
 * @param callable():mixed $compute
 * @return mixed
 */
function admin_metric(PDO $pdo, string $key, int $ttl, callable $compute)
{
    admin_ensure_metrics_schema($pdo);
    $now = time();

    try {
        $stmt = $pdo->prepare('SELECT value_json, computed_at, ttl FROM admin_metrics WHERE metric_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && ((int) $row['computed_at'] + max(0, (int) $row['ttl'])) > $now) {
            return json_decode((string) $row['value_json'], true);
        }
    } catch (Throwable $e) {
        // Cache read failed; fall through to compute.
    }

    $value = $compute();

    try {
        $pdo->prepare(
            'INSERT INTO admin_metrics (metric_key, value_json, computed_at, ttl) VALUES (?, ?, ?, ?)'
            . ' ON DUPLICATE KEY UPDATE value_json = VALUES(value_json), computed_at = VALUES(computed_at), ttl = VALUES(ttl)'
        )->execute([$key, json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $now, $ttl]);
    } catch (Throwable $e) {
        // Non-fatal: serve the freshly computed value anyway.
    }

    return $value;
}

function admin_metric_invalidate(PDO $pdo, string $prefix = ''): void
{
    admin_ensure_metrics_schema($pdo);
    try {
        if ($prefix === '') {
            $pdo->exec('DELETE FROM admin_metrics');
            return;
        }
        $stmt = $pdo->prepare('DELETE FROM admin_metrics WHERE metric_key LIKE ?');
        $stmt->execute([$prefix . '%']);
    } catch (Throwable $e) {
        // Ignore.
    }
}

/**
 * Convenience: cached COUNT(*) with a guarded table/where.
 */
function admin_metric_count(PDO $pdo, string $table, string $where = '1=1', int $ttl = 120): int
{
    return (int) admin_metric($pdo, 'count:' . $table . ':' . md5($where), $ttl, static function () use ($pdo, $table, $where): int {
        if (!app_table_exists($pdo, $table)) {
            return 0;
        }
        try {
            return (int) $pdo->query("SELECT COUNT(*) FROM {$table} WHERE {$where}")->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    });
}
