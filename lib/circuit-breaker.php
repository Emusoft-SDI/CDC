<?php
declare(strict_types=1);

/**
 * Payment Gateway Circuit Breaker & Automatic Cooldown Recovery Subsystem
 *
 * Implements a state-machine circuit breaker (CLOSED -> OPEN -> HALF-OPEN -> CLOSED)
 * for payout and collection gateways. Consecutive provider failures trip a temporary
 * cooldown (OPEN) rather than stranding users or disabling platform automation permanently.
 * Once the cooldown expires, the breaker transitions to HALF-OPEN to permit a trial probe;
 * upon probe success, the breaker automatically recovers to CLOSED.
 */

require_once __DIR__ . '/../config.php';

function circuit_breaker_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS gateway_circuit_breakers (
            provider VARCHAR(40) PRIMARY KEY,
            state VARCHAR(20) NOT NULL DEFAULT 'closed',
            failure_count INT NOT NULL DEFAULT 0,
            success_count INT NOT NULL DEFAULT 0,
            failure_threshold INT NOT NULL DEFAULT 3,
            cooldown_seconds INT NOT NULL DEFAULT 300,
            tripped_at DATETIME NULL,
            cooldown_until DATETIME NULL,
            last_failure_at DATETIME NULL,
            last_error TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
}

function circuit_breaker_settings(PDO $pdo): array
{
    $threshold = (int) (function_exists('admin_setting') ? admin_setting($pdo, 'circuit_breaker_failure_threshold', '3') : '3');
    $cooldown = (int) (function_exists('admin_setting') ? admin_setting($pdo, 'circuit_breaker_cooldown_seconds', '300') : '300');

    return [
        'failure_threshold' => max(1, min(20, $threshold > 0 ? $threshold : 3)),
        'cooldown_seconds' => max(10, min(86400, $cooldown > 0 ? $cooldown : 300)),
    ];
}

function circuit_breaker_get_state(PDO $pdo, string $provider): array
{
    circuit_breaker_ensure_schema($pdo);
    $provider = strtolower(trim($provider));
    $settings = circuit_breaker_settings($pdo);

    $stmt = $pdo->prepare("SELECT * FROM gateway_circuit_breakers WHERE provider = ? LIMIT 1");
    $stmt->execute([$provider]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        $insert = $pdo->prepare("
            INSERT INTO gateway_circuit_breakers
                (provider, state, failure_count, success_count, failure_threshold, cooldown_seconds)
            VALUES (?, 'closed', 0, 0, ?, ?)
            ON DUPLICATE KEY UPDATE provider = VALUES(provider)
        ");
        $insert->execute([$provider, $settings['failure_threshold'], $settings['cooldown_seconds']]);

        return [
            'provider' => $provider,
            'state' => 'closed',
            'failure_count' => 0,
            'success_count' => 0,
            'failure_threshold' => $settings['failure_threshold'],
            'cooldown_seconds' => $settings['cooldown_seconds'],
            'tripped_at' => null,
            'cooldown_until' => null,
            'last_failure_at' => null,
            'last_error' => null,
            'is_available' => true,
        ];
    }

    $state = (string) ($row['state'] ?? 'closed');
    $cooldownUntil = $row['cooldown_until'] ? (string) $row['cooldown_until'] : null;

    // Transition from OPEN -> HALF_OPEN when cooldown expires
    if ($state === 'open') {
        $expires = $cooldownUntil !== null ? strtotime($cooldownUntil) : 0;
        if ($expires === false || $expires <= time()) {
            $update = $pdo->prepare("
                UPDATE gateway_circuit_breakers
                SET state = 'half_open', updated_at = NOW()
                WHERE provider = ?
            ");
            $update->execute([$provider]);
            $row['state'] = 'half_open';
            $state = 'half_open';
        }
    }

    $row['is_available'] = ($state === 'closed' || $state === 'half_open');

    return $row;
}

function circuit_breaker_is_available(PDO $pdo, string $provider): bool
{
    $state = circuit_breaker_get_state($pdo, $provider);
    return !empty($state['is_available']);
}

function circuit_breaker_record_failure(PDO $pdo, string $provider, string $error = ''): array
{
    circuit_breaker_ensure_schema($pdo);
    $provider = strtolower(trim($provider));
    $current = circuit_breaker_get_state($pdo, $provider);
    $settings = circuit_breaker_settings($pdo);

    $state = $current['state'];
    $failures = ((int) $current['failure_count']) + 1;
    $threshold = $settings['failure_threshold'];
    $cooldownSecs = $settings['cooldown_seconds'];

    $newState = $state;
    $trippedAt = $current['tripped_at'];
    $cooldownUntil = $current['cooldown_until'];

    if ($state === 'half_open') {
        // Probe failed in half-open state -> re-trip circuit to OPEN with fresh cooldown
        $newState = 'open';
        $trippedAt = date('Y-m-d H:i:s');
        $cooldownUntil = date('Y-m-d H:i:s', time() + $cooldownSecs);
        if (function_exists('admin_audit')) {
            admin_audit($pdo, 'circuit_breaker_reopened', "Circuit breaker probe failed for provider [{$provider}]: re-tripped to OPEN for {$cooldownSecs}s cooldown.");
        }
    } elseif ($state === 'closed' && $failures >= $threshold) {
        // Consecutive failure threshold reached -> trip circuit to OPEN
        $newState = 'open';
        $trippedAt = date('Y-m-d H:i:s');
        $cooldownUntil = date('Y-m-d H:i:s', time() + $cooldownSecs);
        if (function_exists('admin_audit')) {
            admin_audit($pdo, 'circuit_breaker_tripped', "Circuit breaker tripped for provider [{$provider}] after {$failures} consecutive failures. Cooldown until {$cooldownUntil}.");
        }
    }

    $stmt = $pdo->prepare("
        INSERT INTO gateway_circuit_breakers
            (provider, state, failure_count, failure_threshold, cooldown_seconds, tripped_at, cooldown_until, last_failure_at, last_error)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)
        ON DUPLICATE KEY UPDATE
            state = VALUES(state),
            failure_count = VALUES(failure_count),
            failure_threshold = VALUES(failure_threshold),
            cooldown_seconds = VALUES(cooldown_seconds),
            tripped_at = VALUES(tripped_at),
            cooldown_until = VALUES(cooldown_until),
            last_failure_at = NOW(),
            last_error = VALUES(last_error),
            updated_at = NOW()
    ");
    $stmt->execute([
        $provider,
        $newState,
        $failures,
        $threshold,
        $cooldownSecs,
        $trippedAt,
        $cooldownUntil,
        mb_substr(trim($error), 0, 500),
    ]);

    return [
        'provider' => $provider,
        'state' => $newState,
        'failure_count' => $failures,
        'cooldown_until' => $cooldownUntil,
        'is_available' => ($newState === 'closed' || $newState === 'half_open'),
        'tripped' => ($newState === 'open' && $state !== 'open'),
    ];
}

function circuit_breaker_record_success(PDO $pdo, string $provider): void
{
    circuit_breaker_ensure_schema($pdo);
    $provider = strtolower(trim($provider));
    $current = circuit_breaker_get_state($pdo, $provider);

    $state = $current['state'];
    $wasHalfOpen = ($state === 'half_open');

    $stmt = $pdo->prepare("
        UPDATE gateway_circuit_breakers
        SET state = 'closed',
            failure_count = 0,
            success_count = success_count + 1,
            tripped_at = NULL,
            cooldown_until = NULL,
            updated_at = NOW()
        WHERE provider = ?
    ");
    $stmt->execute([$provider]);

    if ($wasHalfOpen && function_exists('admin_audit')) {
        admin_audit($pdo, 'circuit_breaker_recovered', "Circuit breaker for provider [{$provider}] recovered to CLOSED after successful test transaction.");
    }
}

function circuit_breaker_reset(PDO $pdo, string $provider, int $adminId = 0): void
{
    circuit_breaker_ensure_schema($pdo);
    $provider = strtolower(trim($provider));
    circuit_breaker_get_state($pdo, $provider);

    $stmt = $pdo->prepare("
        UPDATE gateway_circuit_breakers
        SET state = 'closed',
            failure_count = 0,
            tripped_at = NULL,
            cooldown_until = NULL,
            last_error = NULL,
            updated_at = NOW()
        WHERE provider = ?
    ");
    $stmt->execute([$provider]);

    if (function_exists('admin_audit')) {
        $actor = $adminId > 0 ? "Super Admin #{$adminId}" : 'System';
        admin_audit($pdo, 'circuit_breaker_manual_reset', "{$actor} manually reset the circuit breaker for provider [{$provider}].");
    }
}

function circuit_breaker_reset_all(PDO $pdo, int $adminId = 0): void
{
    circuit_breaker_ensure_schema($pdo);
    $pdo->exec("
        UPDATE gateway_circuit_breakers
        SET state = 'closed',
            failure_count = 0,
            tripped_at = NULL,
            cooldown_until = NULL,
            last_error = NULL,
            updated_at = NOW()
    ");

    if (function_exists('admin_audit')) {
        $actor = $adminId > 0 ? "Super Admin #{$adminId}" : 'System';
        admin_audit($pdo, 'circuit_breaker_manual_reset_all', "{$actor} manually reset all gateway circuit breakers.");
    }
}

function circuit_breaker_all_statuses(PDO $pdo): array
{
    circuit_breaker_ensure_schema($pdo);
    $supported = ['monnify', 'paystack', 'fincra', 'woven', 'korapay', 'allawee'];
    $statuses = [];

    foreach ($supported as $p) {
        $statuses[$p] = circuit_breaker_get_state($pdo, $p);
    }

    return $statuses;
}
