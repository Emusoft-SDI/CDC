<?php
declare(strict_types=1);

/**
 * Generic admin action registry (Phase 1, additive).
 *
 * Existing pages keep their inline POST handlers. New/migrated handlers register
 * here and are dispatched by the generic endpoint admin/action.php with CSRF and
 * capability enforcement. Nothing changes for unregistered actions.
 */

function admin_register_action(string $key, callable $handler, ?string $capability = null): void
{
    $GLOBALS['__admin_actions'][$key] = ['handler' => $handler, 'capability' => $capability];
}

/** @return array<string,array{handler:callable,capability:?string}> */
function admin_action_registry(): array
{
    return $GLOBALS['__admin_actions'] ?? [];
}

/** @return array{handler:callable,capability:?string}|null */
function admin_action(string $key): ?array
{
    return admin_action_registry()[$key] ?? null;
}

/**
 * Dispatch a registered action with capability enforcement and error isolation.
 *
 * @param array<string,mixed> $input
 * @return array{ok:bool,status?:int,error?:string}
 */
function admin_dispatch_action(PDO $pdo, string $key, array $input = []): array
{
    $action = admin_action($key);
    if ($action === null) {
        return ['ok' => false, 'status' => 404, 'error' => 'unknown_action'];
    }
    if ($action['capability'] !== null && !admin_can($pdo, (string) $action['capability'])) {
        return ['ok' => false, 'status' => 403, 'error' => 'forbidden'];
    }

    try {
        $result = ($action['handler'])($pdo, $input);
        return is_array($result) ? $result : ['ok' => true];
    } catch (Throwable $e) {
        error_log('admin action ' . $key . ' failed: ' . $e->getMessage());
        return ['ok' => false, 'status' => 500, 'error' => 'action_failed'];
    }
}
