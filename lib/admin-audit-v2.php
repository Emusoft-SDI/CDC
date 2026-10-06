<?php
declare(strict_types=1);

/**
 * Audit v2 (Phase 2, additive).
 *
 * Adds structured columns to the existing audit_log table and a richer writer.
 * admin_audit() keeps working unchanged; admin_audit_event() writes the extra
 * entity/request context when callers provide it, and falls back to the legacy
 * writer if anything goes wrong so auditing can never break a request.
 */

function admin_ensure_audit_v2_schema(PDO $pdo): void
{
    if (!app_table_exists($pdo, 'audit_log')) {
        return;
    }
    foreach ([
        'entity_type' => 'VARCHAR(120) NULL',
        'entity_id' => 'INT NULL',
        'before_json' => 'MEDIUMTEXT NULL',
        'after_json' => 'MEDIUMTEXT NULL',
        'request_id' => 'VARCHAR(64) NULL',
        'actor_role' => 'VARCHAR(60) NULL',
        'outcome' => 'VARCHAR(30) NULL',
    ] as $column => $definition) {
        app_add_column_if_missing($pdo, 'audit_log', $column, $definition);
    }

    foreach ([
        'idx_audit_entity' => 'ALTER TABLE audit_log ADD INDEX idx_audit_entity (entity_type, entity_id)',
        'idx_audit_request' => 'ALTER TABLE audit_log ADD INDEX idx_audit_request (request_id)',
    ] as $label => $ddl) {
        try {
            $pdo->exec($ddl);
        } catch (Throwable $e) {
            // Index already exists or the engine refused it — non-fatal.
        }
    }
}

/**
 * @param array<string,mixed> $before
 * @param array<string,mixed> $after
 */
function admin_audit_event(
    PDO $pdo,
    string $action,
    string $entityType = '',
    ?int $entityId = null,
    array $before = [],
    array $after = [],
    string $outcome = 'success',
    string $description = ''
): void {
    admin_ensure_audit_v2_schema($pdo);

    $requestId = function_exists('admin_request_id') ? admin_request_id() : null;
    $role = function_exists('admin_current_platform_role') ? admin_current_platform_role($pdo) : null;
    $actorId = function_exists('admin_current_user_id') ? admin_current_user_id($pdo) : null;
    $actorName = function_exists('admin_current_user_name') ? admin_current_user_name($pdo) : null;

    if ($description === '') {
        $description = $action . ($entityType !== '' ? ' ' . $entityType . ($entityId ? '#' . $entityId : '') : '');
    }

    try {
        $pdo->prepare(
            'INSERT INTO audit_log (action, description, ip_address, actor_id, actor_name, entity_type, entity_id, before_json, after_json, request_id, actor_role, outcome)'
            . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $action,
            $description,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $actorId,
            $actorName,
            $entityType !== '' ? $entityType : null,
            $entityId ?: null,
            $before ? json_encode($before, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
            $after ? json_encode($after, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
            $requestId,
            $role,
            $outcome,
        ]);
    } catch (Throwable $e) {
        if (function_exists('admin_audit')) {
            admin_audit($pdo, $action, $description);
        }
    }
}
