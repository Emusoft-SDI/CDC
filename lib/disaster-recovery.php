<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

function dr_ensure_schema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS site_nodes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            node_key VARCHAR(80) NOT NULL UNIQUE,
            name VARCHAR(160) NOT NULL,
            base_url VARCHAR(255) NOT NULL,
            node_role VARCHAR(40) NOT NULL DEFAULT 'replica',
            status VARCHAR(30) NOT NULL DEFAULT 'active',
            sync_enabled TINYINT(1) NOT NULL DEFAULT 1,
            shared_secret_hash VARCHAR(255) NULL,
            last_seen_at DATETIME NULL,
            last_error TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_site_nodes_status (status, sync_enabled)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'site_nodes');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS dr_backups (
            id INT AUTO_INCREMENT PRIMARY KEY,
            backup_ref VARCHAR(80) NOT NULL UNIQUE,
            backup_type VARCHAR(40) NOT NULL DEFAULT 'manifest',
            status VARCHAR(30) NOT NULL DEFAULT 'completed',
            storage_path VARCHAR(255) NULL,
            file_size BIGINT NOT NULL DEFAULT 0,
            checksum VARCHAR(128) NULL,
            notes TEXT NULL,
            created_by INT NULL,
            started_at DATETIME NOT NULL,
            completed_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_dr_backups_status (status, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'dr_backups');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sync_events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_uuid VARCHAR(80) NOT NULL UNIQUE,
            direction VARCHAR(20) NOT NULL DEFAULT 'outbound',
            event_type VARCHAR(80) NOT NULL,
            entity_table VARCHAR(80) NULL,
            entity_id VARCHAR(80) NULL,
            payload_json LONGTEXT NULL,
            source_node VARCHAR(80) NULL,
            target_node VARCHAR(80) NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'pending',
            attempts INT NOT NULL DEFAULT 0,
            error_message TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            processed_at DATETIME NULL,
            INDEX idx_sync_events_status (status, direction, created_at),
            INDEX idx_sync_events_type (event_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'sync_events');

    foreach (dr_default_settings() as $key => $value) {
        dr_save_setting($pdo, $key, $value, true);
    }
}

function dr_default_settings(): array
{
    return [
        'dr_site_id' => 'primary-' . strtolower(bin2hex(random_bytes(3))),
        'dr_site_role' => 'primary',
        'dr_sync_enabled' => '0',
        'dr_sync_mode' => 'manual_review',
        'dr_backup_frequency' => 'daily',
        'dr_backup_retention_days' => '30',
        'dr_backup_storage_path' => 'private_backups',
        'dr_recovery_contact' => '',
        'dr_last_restore_test_at' => '',
    ];
}

function dr_settings(PDO $pdo): array
{
    $settings = dr_default_settings();
    foreach ($settings as $key => $default) {
        $settings[$key] = function_exists('admin_setting') ? admin_setting($pdo, $key, $default) : $default;
    }
    return $settings;
}

function dr_save_setting(PDO $pdo, string $key, string $value, bool $insertOnly = false): void
{
    $sql = $insertOnly
        ? "INSERT IGNORE INTO settings (key_name, value) VALUES (?, ?)"
        : "INSERT INTO settings (key_name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$key, $value]);
}

function dr_generate_shared_secret(): string
{
    return 'NAT-SYNC-' . strtoupper(bin2hex(random_bytes(12)));
}

function dr_create_backup_manifest(PDO $pdo, ?int $userId = null): array
{
    $settings = dr_settings($pdo);
    $relativeDir = trim($settings['dr_backup_storage_path'] ?: 'private_backups', "/\\");
    $relativeDir = preg_replace('/[^a-zA-Z0-9_\/.-]/', '', $relativeDir) ?: 'private_backups';
    $absoluteDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativeDir);
    if (!is_dir($absoluteDir)) {
        mkdir($absoluteDir, 0750, true);
    }

    $backupRef = 'DR-' . date('ymd-His') . '-' . strtoupper(bin2hex(random_bytes(2)));
    $tables = $pdo->query("
        SELECT TABLE_NAME
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
        ORDER BY TABLE_NAME
    ")->fetchAll(PDO::FETCH_COLUMN);

    $tableCounts = [];
    foreach ($tables as $table) {
        $quoted = '`' . str_replace('`', '``', (string) $table) . '`';
        try {
            $tableCounts[(string) $table] = (int) $pdo->query("SELECT COUNT(*) FROM {$quoted}")->fetchColumn();
        } catch (Throwable $e) {
            $tableCounts[(string) $table] = null;
        }
    }

    $manifest = [
        'backup_ref' => $backupRef,
        'created_at' => date('c'),
        'site_id' => $settings['dr_site_id'],
        'site_role' => $settings['dr_site_role'],
        'app_url' => app_base_url(),
        'database' => app_env('DB_DATABASE', ''),
        'table_counts' => $tableCounts,
        'restore_note' => 'Use this manifest to verify a cPanel/mysql backup set. It is not a full SQL dump.',
    ];

    $path = $absoluteDir . DIRECTORY_SEPARATOR . strtolower($backupRef) . '.json';
    file_put_contents($path, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    $relativePath = $relativeDir . '/' . basename($path);
    $size = is_file($path) ? (int) filesize($path) : 0;
    $checksum = is_file($path) ? hash_file('sha256', $path) : null;

    $stmt = $pdo->prepare("
        INSERT INTO dr_backups
            (backup_ref, backup_type, status, storage_path, file_size, checksum, notes, created_by, started_at, completed_at)
        VALUES (?, 'manifest', 'completed', ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    $stmt->execute([$backupRef, $relativePath, $size, $checksum, 'Backup manifest created from Super Admin console.', $userId]);

    return ['backup_ref' => $backupRef, 'path' => $relativePath, 'size' => $size, 'checksum' => $checksum];
}

function dr_queue_sync_event(PDO $pdo, string $eventType, array $payload, ?string $targetNode = null): string
{
    $uuid = 'SYNC-' . date('ymd-His') . '-' . strtoupper(bin2hex(random_bytes(4)));
    $stmt = $pdo->prepare("
        INSERT INTO sync_events
            (event_uuid, direction, event_type, payload_json, source_node, target_node, status)
        VALUES (?, 'outbound', ?, ?, ?, ?, 'pending')
    ");
    $stmt->execute([
        $uuid,
        $eventType,
        json_encode($payload, JSON_UNESCAPED_SLASHES),
        dr_settings($pdo)['dr_site_id'],
        $targetNode,
    ]);
    return $uuid;
}

function dr_verify_node_token(PDO $pdo, string $nodeKey, string $token): bool
{
    $stmt = $pdo->prepare("SELECT shared_secret_hash, sync_enabled, status FROM site_nodes WHERE node_key = ? LIMIT 1");
    $stmt->execute([$nodeKey]);
    $node = $stmt->fetch();
    return $node
        && (int) $node['sync_enabled'] === 1
        && (string) $node['status'] === 'active'
        && is_string($node['shared_secret_hash'])
        && password_verify($token, (string) $node['shared_secret_hash']);
}
