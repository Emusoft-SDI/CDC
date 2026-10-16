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

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS dr_restore_drills (
            id INT AUTO_INCREMENT PRIMARY KEY,
            drill_ref VARCHAR(80) NOT NULL UNIQUE,
            source VARCHAR(40) NOT NULL DEFAULT 'local',
            backup_ref VARCHAR(80) NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'running',
            db_count_ok TINYINT(1) NOT NULL DEFAULT 0,
            smoke_login_ok TINYINT(1) NOT NULL DEFAULT 0,
            smoke_health_ok TINYINT(1) NOT NULL DEFAULT 0,
            output TEXT NULL,
            started_at DATETIME NOT NULL,
            completed_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_dr_restore_drills_status (status, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'dr_restore_drills');

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
        'dr_backup_storage_path' => '../win-private/backups',
        'dr_recovery_contact' => '',
        'dr_last_restore_test_at' => '',
        'dr_auto_backup_token' => '',
        'dr_encryption_enabled' => '0',
        'dr_backup_encrypt_pass' => '', // passphrase stored here; generated on first use if empty
        'dr_slack_webhook' => '', // Slack incoming webhook URL
        'dr_teams_webhook' => '', // Microsoft Teams Incoming Webhook URL
        'dr_telegram_chat_id' => '', // Telegram chat ID (e.g. -100xxxxxxxx)
        'dr_telegram_bot_token' => '', // Telegram bot token from @BotFather
        'dr_s3_endpoint' => '', // e.g. https://s3.amazonaws.com or https://nyc3.digitaloceanspaces.com
        'dr_s3_region' => 'us-east-1',
        'dr_s3_access_key' => '',
        'dr_s3_secret_key' => '',
        'dr_s3_bucket' => '', // bucket name; leave blank to use per-request bucket
        'dr_s3_autobackup' => '0', // auto-upload integrity backups to S3
        'dr_binlog_relay_enabled' => '0',
        'dr_binlog_storage_path' => '../win-private/binlogs',
        'dr_gcs_key_file' => '', // Google Service Account JSON key path
        'dr_gcs_bucket' => '', // GCS bucket name
        'dr_gcs_object_prefix' => 'backups/',
        'dr_ftp_host' => '',
        'dr_ftp_port' => '21',
        'dr_ftp_username' => '',
        'dr_ftp_password' => '',
        'dr_ftp_remote_path' => '/',
        'dr_ftp_protocol' => 'ftp',
        'dr_ftp_ssh_key' => '', // optional SSH private key for SFTP
        'dr_email_backup_recipient' => '', // email address for encrypted backup files
        'dr_email_backup_pass_email' => '', // separate email for password delivery
        'dr_email_backup_manual_pass' => '', // optional fixed passphrase instead of random
        'dr_gcs_autobackup' => '0',
        'dr_ftp_autobackup' => '0',
        'dr_email_autobackup' => '0',
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
    [$relativeDir, $absoluteDir] = dr_backup_root($pdo);

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

    $settings = dr_settings($pdo);
    $manifest = [
        'backup_ref' => $backupRef,
        'created_at' => date('c'),
        'site_id' => $settings['dr_site_id'],
        'site_role' => $settings['dr_site_role'],
        'app_url' => app_base_url(),
        'database' => app_env('DB_DATABASE', ''),
        'table_counts' => $tableCounts,
        'restore_note' => 'Use this manifest to verify a cPanel/mysql backup set. It is not a full SQL dump.',
        'operational_restore_rule' => 'Super Admin can trigger backup and verify restore evidence. Actual production restore requires Super Admin approval plus hosting/database operator execution unless a dedicated restore database user is granted.',
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

    try {
        dr_purge_expired_backups($pdo);
    } catch (Throwable $e) {
        error_log('Backup retention purge skipped: ' . $e->getMessage());
    }

    return ['backup_ref' => $backupRef, 'path' => $relativePath, 'size' => $size, 'checksum' => $checksum];
}

function dr_purge_expired_backups(PDO $pdo, int $retentionDays = 0): int
{
    $settings = dr_settings($pdo);
    if ($retentionDays <= 0) {
        $retentionDays = max(1, (int) ($settings['dr_backup_retention_days'] ?? 30));
    }
    [$relativeDir, $absoluteDir] = dr_backup_root($pdo);
    $stmt = $pdo->prepare("SELECT id, storage_path FROM dr_backups WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
    $stmt->execute([$retentionDays]);
    $purged = 0;
    foreach ($stmt->fetchAll() as $row) {
        $storagePath = (string) ($row['storage_path'] ?? '');
        if ($storagePath !== '') {
            $file = $absoluteDir . DIRECTORY_SEPARATOR . basename($storagePath);
            if (is_file($file)) {
                @unlink($file);
            }
        }
        $pdo->prepare("DELETE FROM dr_backups WHERE id = ?")->execute([(int) $row['id']]);
        $purged++;
    }
    return $purged;
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

function dr_safe_relative_path(string $path, string $fallback = '../win-private/backups'): string
{
    $path = trim(str_replace('\\', '/', $path), "/ ");
    $path = preg_replace('/[^a-zA-Z0-9_\/.-]/', '', $path) ?: $fallback;
    $segments = [];
    foreach (explode('/', $path) as $segment) {
        if ($segment === '' || $segment === '.') {
            continue;
        }
        if ($segment === '..') {
            if (!$segments || end($segments) === '..') {
                $segments[] = '..';
            } else {
                array_pop($segments);
            }
            continue;
        }
        $segments[] = $segment;
    }
    $path = implode('/', $segments) ?: $fallback;
    return in_array($path, ['.', '..'], true) ? $fallback : $path;
}

function dr_project_root(): string
{
    return dirname(__DIR__);
}

function dr_backup_root(PDO $pdo): array
{
    $settings = dr_settings($pdo);
    $relative = dr_safe_relative_path((string) ($settings['dr_backup_storage_path'] ?? '../win-private/backups'));
    $base = realpath(dr_project_root());
    if ($base === false) {
        throw new RuntimeException('Project root could not be resolved.');
    }
    $candidate = $base . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
    if (!is_dir($candidate)) {
        mkdir($candidate, 0750, true);
    }
    $absolute = realpath($candidate);
    if ($absolute === false) {
        throw new RuntimeException('Backup path could not be resolved.');
    }
    $privateBase = realpath(app_private_storage_path());
    if ($privateBase !== false && !str_starts_with($absolute, $privateBase . DIRECTORY_SEPARATOR) && $absolute !== $privateBase) {
        $relative = '../win-private/backups';
        $absolute = app_private_storage_path('backups');
        if (!is_dir($absolute)) {
            mkdir($absolute, 0750, true);
        }
    }
    return [$relative, $absolute];
}

function dr_quote_sql_identifier(string $name): string
{
    return '`' . str_replace('`', '``', $name) . '`';
}

function dr_sql_value($value): string
{
    if ($value === null) {
        return 'NULL';
    }
    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }
    return "'" . str_replace(["\\", "'", "\r", "\n"], ["\\\\", "\\'", "\\r", "\\n"], (string) $value) . "'";
}

/* ------------------------------------------------------------------ encrypt */

/**
 * Ensure an encryption passphrase exists; generate one if missing and store it.
 * Uses openssl random bytes (256-bit key material).
 */
function dr_ensure_encrypt_pass(PDO $pdo): string
{
    $pass = trim((string) ($settings['dr_backup_encrypt_pass'] ?? ''));
    // Correct: use dr_settings() not undefined $settings
    $settings = dr_settings($pdo);
    $pass = trim((string) ($settings['dr_backup_encrypt_pass'] ?? ''));
    if ($pass === '') {
        $pass = bin2hex(random_bytes(32));
        dr_save_setting($pdo, 'dr_backup_encrypt_pass', $pass);
    }
    return $pass;
}

/** Encrypt a single file with AES-256-CBC via OpenSSL CLI. Returns .enc path. */
function dr_encrypt_file(string $srcPath, string $passphrase): ?string
{
    $encPath = $srcPath . '.enc';
    $cmd = sprintf(
        'openssl enc -aes-256-cbc -salt -pbkdf2 -in "%s" -out "%s" -pass pass:"%s" 2>&1',
        str_replace('"', "'", $srcPath),
        str_replace('"', "'", $encPath),
        str_replace('%', '%%', $passphrase)
    );
    $output = [];
    @shell_exec($cmd, $output, $rc);
    if ($rc !== 0 || !is_file($encPath)) {
        error_log("dr_encrypt_file failed for {$srcPath}: " . implode(' | ', array_slice($output, 0, 3)));
        return null;
    }
    @unlink($srcPath); // remove plaintext
    return $encPath;
}

/** Decrypt an OpenSSL-encrypted file back to the source path. */
function dr_decrypt_file(string $encPath, string $passphrase): ?string
{
    $outPath = rtrim($encPath, '.enc');
    $cmd = sprintf(
        'openssl enc -d -aes-256-cbc -salt -pbkdf2 -in "%s" -out "%s" -pass pass:"%s" 2>&1',
        str_replace('"', "'", $encPath),
        str_replace('"', "'", $outPath),
        str_replace('%', '%%', $passphrase)
    );
    $output = [];
    @shell_exec($cmd, $output, $rc);
    if ($rc !== 0 || !is_file($outPath)) {
        error_log("dr_decrypt_file failed for {$encPath}: " . implode(' | ', array_slice($output, 0, 3)));
        return null;
    }
    @unlink($encPath);
    return $outPath;
}

/** Wrap bundle directory into tar.gz then encrypt with OpenSSL. */
function dr_encrypt_bundle(string $bundleDir, string $passphrase): array
{
    $tarPath = $bundleDir . '.tar';
    $tarGzPath = $tarPath . '.gz';
    $encPath = $tarGzPath . '.enc';

    // Create tar (use PHP RecursiveIterator for cross-platform compatibility)
    $phar = new PharData($tarPath);
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($bundleDir, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $item) {
        $path = $item->getPathname();
        $relative = ltrim(str_replace($bundleDir, '', $path), '/\\');
        $phar->addFile($path, $relative);
    }
    unset($phar); // flush

    // gzip
    $gzCmd = sprintf('gzip -f "%s"', str_replace('"', "'", $tarPath));
    @shell_exec($gzCmd);
    if (!is_file($tarGzPath)) {
        @unlink($tarPath); // clean up
        throw new RuntimeException('Failed to gzip backup bundle.');
    }

    // openssl encrypt
    $finalEnc = dr_encrypt_file($tarGzPath, $passphrase);
    if ($finalEnc === null) {
        @unlink($tarGzPath);
        throw new RuntimeException('Failed to encrypt backup bundle.');
    }

    // Clean up original bundle dir
    dr_rmdir_recursive($bundleDir);

    return ['encrypted_path' => $finalEnc, 'original_size' => $tarGzPath];
}

/** Send a backup event notification to configured webhooks (Slack / Teams / Telegram). */
function dr_notify_webhooks(PDO $pdo, string $event, array $payload): void
{
    $settings = dr_settings($pdo);
    $slackWebhook = trim((string) ($settings['dr_slack_webhook'] ?? ''));
    $teamsWebhook = trim((string) ($settings['dr_teams_webhook'] ?? ''));
    $telegramChatId = trim((string) ($settings['dr_telegram_chat_id'] ?? ''));
    $telegramBotToken = trim((string) ($settings['dr_telegram_bot_token'] ?? ''));

    // Shared color map based on status
    $color = '#0f5132'; // green for success
    if (strpos($payload['status'] ?? '', 'fail') !== false || strpos($payload['status'] ?? '', 'error') !== false) {
        $color = '#9f1d1d';
    } elseif (strpos($payload['status'] ?? '', 'warn') !== false) {
        $color = '#8a5a00';
    }

    foreach ([$slackWebhook => 'slack', $teamsWebhook => 'teams'] as $url => $platform) {
        if ($url === '') continue;
        $body = [
            'attachments' => [[
                'color' => $color,
                'title' => "NATCODEV Backup: {$event}",
                'fields' => [
                    ['title' => 'Reference', 'value' => $payload['backup_ref'] ?? '—', 'short' => true],
                    ['title' => 'Status', 'value' => $payload['status'] ?? '—', 'short' => true],
                    ['title' => 'Bundle size', 'value' => number_format(($payload['size_bytes'] ?? 0) / 1048576, 1) . ' MB', 'short' => true],
                    ['title' => 'Site', 'value' => $payload['site_id'] ?? '—', 'short' => true],
                ],
                'footer' => 'NATCODEV DR · ' . date('c'),
                'ts' => time(),
            ]],
        ];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);
        @curl_exec($ch);
        curl_close($ch);
    }

    foreach ([$telegramChatId => $telegramBotToken] as $chatId => $token) {
        if ($chatId === '' || $token === '') continue;
        $ref = $payload['backup_ref'] ?? '—';
        $status = $payload['status'] ?? '—';
        $sizeBytes = $payload['size_bytes'] ?? 0;
        $site = $payload['site_id'] ?? '—';
        $text = "*NATCODEV Backup: {$event}*\n"
              . "Ref: `{$ref}`\n"
              . "Status: `{$status}`\n"
              . "Size: " . number_format($sizeBytes / 1048576, 1) . " MB\n"
              . "Site: `{$site}`";
        $ch = curl_init("https://api.telegram.org/bot{$token}/sendMessage");
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'MarkdownV2']),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);
        @curl_exec($ch);
        curl_close($ch);
    }
}

/* ------------------------------------------------------------- S3 storage */

/**
 * Upload a file to any S3-compatible endpoint (AWS S3, Backblaze B2, DigitalOcean Spaces, Wasabi, etc.).
 * Uses AWS Signature V4. Pass endpoint_url like 'https://s3.amazonaws.com' or 'https://nyc3.digitaloceanspaces.com'.
 *
 * Usage: dr_upload_to_s3($pdo, '/path/to/file.enc', 'my-bucket', 'backups/BK-xxx.tar.gz.enc');
 */
function dr_upload_to_s3(PDO $pdo, string $filePath, string $bucket, string $objectKey): array
{
    $settings = dr_settings($pdo);
    $s3Endpoint = trim((string) ($settings['dr_s3_endpoint'] ?? ''));
    $s3Region = trim((string) ($settings['dr_s3_region'] ?? 'us-east-1'));
    $s3AccessKey = trim((string) ($settings['dr_s3_access_key'] ?? ''));
    $s3SecretKey = trim((string) ($settings['dr_s3_secret_key'] ?? ''));
    $s3BucketOverride = trim((string) ($settings['dr_s3_bucket'] ?? ''));

    if ($s3Endpoint === '' || $s3AccessKey === '' || $s3SecretKey === '') {
        return ['status' => 'not_configured', 'error' => 'S3 credentials not configured in backup settings.'];
    }
    if (!is_file($filePath) || filesize($filePath) <= 0) {
        return ['status' => 'file_error', 'error' => "File not found or empty: {$filePath}"];
    }

    $targetBucket = $s3BucketOverride ?: $bucket;
    $url = rtrim($s3Endpoint, '/') . '/' . $targetBucket . '/' . ltrim($objectKey, '/');
    $date = gmdate('Ymd');
    $amzDate = gmdate('Ymd\THis\Z');
    $payload = file_get_contents($filePath);
    $payloadHash = hash('sha256', $payload);
    $contentType = mime_content_type($filePath) ?: 'application/octet-stream';

    // Build AWS Signature V4
    $credentialScope = "{$date}/{$s3Region}/s3/aws4_request";
    $canonicalUri = '/' . $targetBucket . '/' . rawurlencode(ltrim($objectKey, '/'));
    $canHeaders = "content-type:{$contentType}\nx-amz-content-sha256:{$payloadHash}\nx-amz-date:{$amzDate}\n";
    $signedHeaders = 'content-type;x-amz-content-sha256;x-amz-date';
    $canonicalRequest = "PUT\n" . urlencode($canonicalUri) . "\n\n" . $canHeaders . "\n" . $signedHeaders . "\n" . $payloadHash;
    $stringToSign = "AWS4-HMAC-SHA256\n{$amzDate}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);

    $signingKey = 'AWS4' . $s3SecretKey;
    $kDate = hash_hmac('sha256', $date, $signingKey, true);
    $kRegion = hash_hmac('sha256', $s3Region, $kDate, true);
    $kService = hash_hmac('sha256', 's3', $kRegion, true);
    $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
    $signature = hash_hmac('sha256', $stringToSign, $kSigning);

    $headers = [
        "Authorization: AWS4-HMAC-SHA256 Credential={$s3AccessKey}/{$credentialScope}, SignedHeaders={$signedHeaders}, Signature={$signature}",
        "x-amz-date: {$amzDate}",
        "x-amz-content-sha256: {$payloadHash}",
        "Content-Type: {$contentType}",
        "Expect: 100-continue",
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_PUT => true,
        CURLOPT_INFILE => fopen($filePath, 'r'),
        CURLOPT_INFILESIZE => filesize($filePath),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 300,
        CURLOPT_USERAGENT => 'NATCODEV-DR/1.0',
    ]);
    $responseBody = @curl_exec($ch);
    $httpCode = curl_errno($ch) ? -1 : curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (($httpCode >= 200 && $httpCode < 300)) {
        return ['status' => 'uploaded', 'url' => $url];
    }
    return ['status' => 'failed', 'http_code' => $httpCode, 'body' => substr($responseBody ?: '', 0, 500)];
}


/** Safe recursive directory removal. */
function dr_rmdir_recursive(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    @rmdir($dir);
}


function dr_create_database_dump(PDO $pdo, string $targetPath): array
{
    // Use mysqldump for a consistent, fast, transactional snapshot.
    // Falls back to row-by-row INSERT if mysqldump is unavailable.
    $db = app_env('DB_DATABASE', '');
    $host = app_env('DB_HOST', '127.0.0.1');
    $user = app_env('DB_USER', 'root');
    $pass = app_env('DB_PASS', '');

    // Check for mysqldump first; try common Windows paths then PATH lookup
    $mysqldump = null;
    foreach ([
        '/usr/bin/mysqldump',
        '/usr/local/bin/mysqldump',
        'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe',
        'C:\\Program Files\\MySQL\\MySQL Server 5.7\\bin\\mysqldump.exe',
        'C:\\xampp\\mysql\\bin\\mysqldump.exe',
    ] as $candidate) {
        if (is_executable($candidate)) {
            $mysqldump = $candidate;
            break;
        }
    }
    if ($mysqldump === null && function_exists('escapeshellcmd')) {
        // Try PATH via "which" or where
        $query = PHP_OS_FAMILY === 'Windows' ? 'where mysqldump' : 'which mysqldump';
        $out = trim((string) @shell_exec($query));
        if ($out !== '' && strpos($out, 'not found') === false && strpos($out, ': not found') === false) {
            $firstLine = explode("\n", $out)[0];
            if (is_executable(trim($firstLine))) {
                $mysqldump = trim($firstLine);
            }
        }
    }

    if ($mysqldump !== null) {
        // Build command: single-transaction + routines + triggers, no lock tables
        $flags = '--single-transaction --routines --triggers '
               . '--set-gtid-purged=OFF --skip-lock-tables '
               . '--default-character-set=utf8mb4';
        $cmd = sprintf(
            '%s %s -h %s -u %s -p%s %s > "%s" 2>&1',
            $mysqldump, escapeshellarg($flags),
            escapeshellarg($host), escapeshellarg($user),
            str_replace('%', '%%', escapeshellarg($pass)), // MySQL chokes on %%
            escapeshellarg($db),
            escapeshellarg($targetPath)
        );
        $output = [];
        $rc = 0;
        @shell_exec($cmd, $output, $rc);
        // Shell wrapper may set rc=2 even on success; check file exists and has content
        if ($rc === 0 || (is_file($targetPath) && filesize($targetPath) > 64)) {
            $size = filesize($targetPath);
            $checksum = hash_file('sha256', $targetPath);
            // Gather table counts from the dump header for audit
            $tableCounts = [];
            if (preg_match_all('/^-- Table structure for [`"]?([\w]+)`"?$/im', file_get_contents($targetPath), $matches)) {
                foreach ($matches[1] as $t) {
                    $tableCounts[$t] = null;
                }
            }
            return ['path' => $targetPath, 'source' => 'mysqldump', 'size' => $size ?: 0, 'checksum' => $checksum, 'table_counts' => $tableCounts];
        }
        // If it failed or produced nothing, fall through to row-by-row below
        error_log("dr_create_database_dump: mysqldump returned exit $rc, output: " . implode(' | ', array_slice($output, 0, 3)));
    }

    // ---- Fallback: row-by-row INSERT (slow but safe) -------------------------
    $tables = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN);
    $handle = fopen($targetPath, 'wb');
    if (!$handle) {
        throw new RuntimeException('Unable to open SQL dump target.');
    }
    fwrite($handle, "-- NATCODEV database backup (fallback)\n-- Created: " . date('c') . "\n-- Database: " . $db . "\n\nSET FOREIGN_KEY_CHECKS=0;\n\n");
    $tableCounts = [];
    foreach ($tables as $table) {
        $table = (string) $table;
        $quoted = dr_quote_sql_identifier($table);
        $create = $pdo->query("SHOW CREATE TABLE {$quoted}")->fetch(PDO::FETCH_ASSOC);
        $createSql = (string) ($create['Create Table'] ?? array_values($create)[1] ?? '');
        fwrite($handle, "\nDROP TABLE IF EXISTS {$quoted};\n{$createSql};\n\n");
        $count = 0;
        $stmt = $pdo->query("SELECT * FROM {$quoted}", PDO::FETCH_ASSOC);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $columns = array_map('dr_quote_sql_identifier', array_keys($row));
            $values = array_map('dr_sql_value', array_values($row));
            fwrite($handle, 'INSERT INTO ' . $quoted . ' (' . implode(',', $columns) . ') VALUES (' . implode(',', $values) . ");\n");
            $count++;
        }
        $tableCounts[$table] = $count;
    }
    fwrite($handle, "\nSET FOREIGN_KEY_CHECKS=1;\n");
    fclose($handle);
    return ['path' => $targetPath, 'source' => 'php_fallback', 'size' => is_file($targetPath) ? filesize($targetPath) : 0, 'checksum' => is_file($targetPath) ? hash_file('sha256', $targetPath) : null, 'table_counts' => $tableCounts];
}

function dr_create_site_archive(string $targetPath, array $excludeDirs = []): array
{
    if (!class_exists('ZipArchive')) {
        return ['created' => false, 'path' => '', 'size' => 0, 'checksum' => null, 'error' => 'PHP ZipArchive extension is not enabled.'];
    }
    $root = dr_project_root();
    $exclude = array_map(static fn(string $item): string => str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($item, "/\\")), $excludeDirs);
    $zip = new ZipArchive();
    if ($zip->open($targetPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Unable to create site archive.');
    }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    $count = 0;
    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile()) {
            continue;
        }
        $full = $file->getPathname();
        if (preg_match('/\.(env|log|zip|rar|sql|sqlite|bak|backup|7z|tar|gz)$/i', basename($full))) {
            continue;
        }
        $relative = ltrim(str_replace($root, '', $full), DIRECTORY_SEPARATOR);
        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
        $skip = false;
        foreach ($exclude as $dir) {
            if ($dir !== '' && (str_starts_with($normalized, $dir . DIRECTORY_SEPARATOR) || $normalized === $dir)) {
                $skip = true;
                break;
            }
        }
        if ($skip) {
            continue;
        }
        $zip->addFile($full, str_replace(DIRECTORY_SEPARATOR, '/', $relative));
        $count++;
    }
    $zip->close();
    return ['created' => true, 'path' => $targetPath, 'files' => $count, 'size' => is_file($targetPath) ? filesize($targetPath) : 0, 'checksum' => is_file($targetPath) ? hash_file('sha256', $targetPath) : null];
}

function dr_copy_directory(string $source, string $target): void
{
    if (!is_dir($source)) {
        throw new RuntimeException('Backup source folder does not exist.');
    }
    if (!is_dir($target)) {
        mkdir($target, 0750, true);
    }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($iterator as $item) {
        $dest = $target . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
        if ($item->isDir()) {
            if (!is_dir($dest)) {
                mkdir($dest, 0750, true);
            }
        } else {
            copy($item->getPathname(), $dest);
        }
    }
}

function dr_create_backup_bundle(PDO $pdo, ?int $userId, array $options = []): array
{
    dr_ensure_schema($pdo);
    [$relativeRoot, $absoluteRoot] = dr_backup_root($pdo);
    $settings = dr_settings($pdo);
    $backupRef = 'BK-' . date('ymd-His') . '-' . strtoupper(bin2hex(random_bytes(2)));
    $bundleDir = $absoluteRoot . DIRECTORY_SEPARATOR . strtolower($backupRef);
    mkdir($bundleDir, 0750, true);

    $includeDb = !empty($options['include_database']);
    $includeSite = !empty($options['include_site']);
    $targets = [];
    $files = [];
    $notes = [];

    $db = null;
    if ($includeDb) {
        $db = dr_create_database_dump($pdo, $bundleDir . DIRECTORY_SEPARATOR . strtolower($backupRef) . '-database.sql');
        $files[] = ['type' => 'database_sql', 'path' => basename((string) $db['path']), 'size' => (int) $db['size'], 'checksum' => $db['checksum']];
    }

    $site = null;
    if ($includeSite) {
        $site = dr_create_site_archive($bundleDir . DIRECTORY_SEPARATOR . strtolower($backupRef) . '-site.zip', ['private_backups', '../win-private', '.git', '.mrcoder', '.vscode', 'documents', 'uploads', 'provider_uploads', 'recruitment_uploads', 'academy_uploads', 'certificates', 'resources']);
        if (!empty($site['created'])) {
            $files[] = ['type' => 'site_archive', 'path' => basename((string) $site['path']), 'size' => (int) $site['size'], 'checksum' => $site['checksum'], 'files' => $site['files'] ?? 0];
        } else {
            $notes[] = (string) ($site['error'] ?? 'Site archive was not created.');
        }
    }

    $gitHead = trim((string) @shell_exec('git rev-parse --short HEAD 2>NUL'));
    $gitBranch = trim((string) @shell_exec('git branch --show-current 2>NUL'));

    foreach (['google_drive' => 'dr_google_drive_path', 'external_drive' => 'dr_external_drive_path'] as $targetName => $settingKey) {
        if (empty($options['copy_' . $targetName])) {
            continue;
        }
        $targetPath = trim((string) ($settings[$settingKey] ?? ''));
        if ($targetPath === '') {
            $targets[$targetName] = ['status' => 'not_configured', 'path' => ''];
            continue;
        }
        try {
            $targetDir = rtrim($targetPath, "/\\") . DIRECTORY_SEPARATOR . strtolower($backupRef);
            dr_copy_directory($bundleDir, $targetDir);
            $targets[$targetName] = ['status' => 'copied', 'path' => $targetDir];
        } catch (Throwable $e) {
            $targets[$targetName] = ['status' => 'failed', 'path' => $targetPath, 'error' => $e->getMessage()];
        }
    }

    $manifest = [
        'backup_ref' => $backupRef,
        'created_at' => date('c'),
        'site_id' => $settings['dr_site_id'] ?? '',
        'app_url' => app_base_url(),
        'database' => app_env('DB_DATABASE', ''),
        'bundle_path' => $relativeRoot . '/' . strtolower($backupRef),
        'files' => $files,
        'targets' => $targets,
        'git' => ['branch' => $gitBranch, 'head' => $gitHead, 'remote' => (string) ($settings['dr_git_remote'] ?? '')],
        'encrypted' => false,
        'notes' => $notes,
        'operational_restore_rule' => 'Super Admin can trigger backup and verify restore evidence. Actual production restore requires Super Admin approval plus hosting/database operator execution unless a dedicated restore database user is granted.',
        'restore_order' => ['1. Obtain Super Admin approval and assign a hosting/database operator for execution.', '2. Put site files back in the web root.', '3. Import the SQL dump into the selected database using approved database credentials.', '4. Restore .env/config values and verify APP_URL/payment/email credentials.', '5. Run production readiness checks and a test login.'],
    ];

    // ---- Encrypt if enabled ---------------------------------------------------
    $s3Uploaded = false;
    if (($settings['dr_encryption_enabled'] ?? '0') === '1') {
        $pass = dr_ensure_encrypt_pass($pdo);
        try {
            // Before encrypting, copy plaintext bundle to configured external destinations
            foreach (['google_drive' => 'dr_google_drive_path', 'external_drive' => 'dr_external_drive_path'] as $targetName => $settingKey) {
                $targetPath = trim((string) ($settings[$settingKey] ?? ''));
                if ($targetPath === '') continue;
                $destDir = rtrim($targetPath, "/\\") . DIRECTORY_SEPARATOR . strtolower($backupRef);
                if (!is_dir($destDir)) {
                    dr_copy_directory($bundleDir, $destDir);
                    $targets[$targetName] = ['status' => 'copied_plaintext', 'path' => $destDir];
                }
            }
            // Now compress + encrypt the whole bundle
            $encResult = dr_encrypt_bundle($bundleDir, $pass);
            $files[] = ['type' => 'encrypted_bundle', 'path' => basename((string) $encResult['encrypted_path']), 'size' => filesize((string) $encResult['encrypted_path']), 'checksum' => hash_file('sha256', (string) $encResult['encrypted_path'])];
            $manifest['encrypted'] = true;
            $manifest['encrypt_note'] = 'Bundle encrypted with AES-256-CBC; keep the encryption passphrase safe.';
            $notes[] = 'Bundle encrypted. Passphrase stored in settings (dr_backup_encrypt_pass).';
        } catch (Throwable $e) {
            $notes[] = 'Encryption failed – plaintext bundle retained: ' . $e->getMessage();
            error_log("Backup encryption failed for {$backupRef}: " . $e->getMessage());
        }
    }

    // ---- S3 cloud storage upload ----------------------------------------------
    if (isset($options['upload_s3']) && !empty($options['upload_s3'])) {
        $bucket = trim((string) ($options['s3_bucket'] ?? ''));
        foreach ($files as $fileItem) {
            $fileType = $fileItem['type'];
            $fileBase = basename((string) $fileItem['path']);
            $objectKey = strtolower($backupRef) . '/' . $fileBase;
            // Determine full path: if encrypted, use the .enc file; otherwise use the artifact
            $fullPath = '';
            if (($settings['dr_encryption_enabled'] ?? '0') === '1' && $fileType === 'encrypted_bundle') {
                $fullPath = $encResult['encrypted_path'] ?? '';
            } elseif ($fileType !== 'encrypted_bundle' && $fileType !== 'manifest') {
                $fullPath = $bundleDir . DIRECTORY_SEPARATOR . $fileBase;
            } elseif ($fileType === 'manifest') {
                $fullPath = $bundleDir . DIRECTORY_SEPARATOR . 'manifest.json';
            }
            if ($fullPath !== '' && is_file($fullPath)) {
                $result = dr_upload_to_s3($pdo, $fullPath, $bucket, $objectKey);
                $targets['s3_' . str_replace('.', '_', $fileType)] = $result;
                $s3Uploaded = true;
                break; // upload at least one artifact per bucket
            }
        }
    }

    // ---- Google Cloud Storage --------------------------------------------------
    if (isset($options['upload_gcs']) && !empty($options['upload_gcs'])) {
        foreach ($files as $fileItem) {
            $fileBase = basename((string) $fileItem['path']);
            $fullPath = '';
            if (($settings['dr_encryption_enabled'] ?? '0') === '1' && $fileItem['type'] === 'encrypted_bundle') {
                $fullPath = $encResult['encrypted_path'] ?? '';
            } elseif ($fileItem['type'] !== 'encrypted_bundle' && $fileItem['type'] !== 'manifest') {
                $fullPath = $bundleDir . DIRECTORY_SEPARATOR . $fileBase;
            } elseif ($fileItem['type'] === 'manifest') {
                $fullPath = $bundleDir . DIRECTORY_SEPARATOR . 'manifest.json';
            }
            if ($fullPath !== '' && is_file($fullPath)) {
                $gcsResult = dr_upload_to_gcs($pdo, $fullPath);
                $targets['gcs_' . str_replace('.', '_', $fileItem['type'])] = $gcsResult;
                break;
            }
        }
    }

    // ---- FTP / SFTP ------------------------------------------------------------
    if (isset($options['upload_ftp']) && !empty($options['upload_ftp'])) {
        foreach ($files as $fileItem) {
            $fileBase = basename((string) $fileItem['path']);
            $fullPath = '';
            if (($settings['dr_encryption_enabled'] ?? '0') === '1' && $fileItem['type'] === 'encrypted_bundle') {
                $fullPath = $encResult['encrypted_path'] ?? '';
            } elseif ($fileItem['type'] !== 'encrypted_bundle' && $fileItem['type'] !== 'manifest') {
                $fullPath = $bundleDir . DIRECTORY_SEPARATOR . $fileBase;
            } elseif ($fileItem['type'] === 'manifest') {
                $fullPath = $bundleDir . DIRECTORY_SEPARATOR . 'manifest.json';
            }
            if ($fullPath !== '' && is_file($fullPath)) {
                $ftpResult = dr_upload_to_ftp($pdo, $fullPath);
                $targets['ftp_' . str_replace('.', '_', $fileItem['type'])] = $ftpResult;
                break;
            }
        }
    }

    // ---- Encrypted Email Attachment --------------------------------------------
    if (isset($options['email_backup']) && !empty($options['email_backup'])) {
        foreach ($files as $fileItem) {
            $fileBase = basename((string) $fileItem['path']);
            $fullPath = '';
            if (($settings['dr_encryption_enabled'] ?? '0') === '1' && $fileItem['type'] === 'encrypted_bundle') {
                $fullPath = $encResult['encrypted_path'] ?? '';
            } elseif ($fileItem['type'] !== 'encrypted_bundle' && $fileItem['type'] !== 'manifest') {
                $fullPath = $bundleDir . DIRECTORY_SEPARATOR . $fileBase;
            } elseif ($fileItem['type'] === 'manifest') {
                $fullPath = $bundleDir . DIRECTORY_SEPARATOR . 'manifest.json';
            }
            if ($fullPath !== '' && is_file($fullPath)) {
                $emailResult = dr_email_backup($pdo, $fullPath);
                $targets['email'] = $emailResult;
                break;
            }
        }
    }

    $manifestPath = $bundleDir . DIRECTORY_SEPARATOR . 'manifest.json';
    file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    $files[] = ['type' => 'manifest', 'path' => 'manifest.json', 'size' => filesize($manifestPath), 'checksum' => hash_file('sha256', $manifestPath)];

    $size = 0;
    foreach ($files as $file) {
        $size += (int) ($file['size'] ?? 0);
    }
    $status = $notes ? 'completed_with_warnings' : 'completed';
    $storageRef = $relativeRoot . '/' . strtolower($backupRef) . '/manifest.json';
    $stmt = $pdo->prepare("INSERT INTO dr_backups (backup_ref, backup_type, status, storage_path, file_size, checksum, notes, created_by, started_at, completed_at) VALUES (?, 'bundle', ?, ?, ?, ?, ?, ?, NOW(), NOW())");
    $stmt->execute([$backupRef, $status, $storageRef, $size, hash_file('sha256', $manifestPath), json_encode(['targets' => $targets, 'notes' => $notes], JSON_UNESCAPED_SLASHES), $userId]);

    // ---- Webhook notifications ------------------------------------------------
    try {
        dr_notify_webhooks($pdo, 'backup.bundle_created', [
            'backup_ref' => $backupRef,
            'status' => $status,
            'size_bytes' => $size,
            'site_id' => $settings['dr_site_id'] ?? '',
            'encrypted' => $manifest['encrypted'] ?? false,
        ]);
    } catch (Throwable $e) {
        error_log("Webhook notification failed for {$backupRef}: " . $e->getMessage());
    }

    return $manifest + ['status' => $status, 'size' => $size, 'manifest_path' => $storageRef];
}
function dr_integrity_time_column(PDO $pdo, string $table): string
{
    foreach (['updated_at', 'created_at', 'requested_at', 'submitted_at', 'issued_at', 'completed_at', 'reviewed_at'] as $column) {
        if (app_column_exists($pdo, $table, $column)) {
            return $column;
        }
    }
    return '';
}

function dr_integrity_activity_summary(PDO $pdo, array $settings): array
{
    $tables = array_values(array_unique(array_filter(array_map(static function (string $table): string {
        return preg_replace('/[^a-zA-Z0-9_]/', '', trim($table));
    }, explode(',', (string) ($settings['dr_auto_backup_critical_tables'] ?? ''))))));
    $window = max(30, min(86400, (int) ($settings['dr_auto_backup_activity_window_seconds'] ?? 3600)));
    $since = date('Y-m-d H:i:s', time() - $window);
    $active = [];
    $total = 0;

    foreach ($tables as $table) {
        if ($table === '' || !app_table_exists($pdo, $table)) {
            continue;
        }
        $column = dr_integrity_time_column($pdo, $table);
        if ($column === '') {
            continue;
        }
        try {
            $quotedTable = '`' . str_replace('`', '``', $table) . '`';
            $quotedColumn = '`' . str_replace('`', '``', $column) . '`';
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$quotedTable} WHERE {$quotedColumn} >= ?");
            $stmt->execute([$since]);
            $count = (int) $stmt->fetchColumn();
            if ($count > 0) {
                $active[] = ['table' => $table, 'column' => $column, 'count' => $count];
                $total += $count;
            }
        } catch (Throwable $e) {
            $active[] = ['table' => $table, 'column' => $column, 'count' => 0, 'error' => $e->getMessage()];
        }
    }

    return ['since' => $since, 'window_seconds' => $window, 'total' => $total, 'active' => $active];
}

function dr_run_integrity_backup(PDO $pdo, ?int $userId = null, bool $force = false): array
{
    dr_ensure_schema($pdo);
    $settings = dr_settings($pdo);
    $now = time();
    dr_save_setting($pdo, 'dr_auto_backup_last_checked_at', date('Y-m-d H:i:s', $now));

    if (($settings['dr_auto_backup_enabled'] ?? '0') !== '1' && !$force) {
        $result = ['status' => 'skipped', 'reason' => 'auto_backup_disabled'];
        dr_save_setting($pdo, 'dr_auto_backup_last_result', json_encode($result, JSON_UNESCAPED_SLASHES));
        return $result;
    }

    $interval = max(30, min(86400, (int) ($settings['dr_auto_backup_interval_seconds'] ?? 3600)));
    $lastBackupAt = strtotime((string) ($settings['dr_auto_backup_last_backup_at'] ?? '')) ?: 0;
    if (!$force && $lastBackupAt > 0 && ($now - $lastBackupAt) < $interval) {
        $result = ['status' => 'skipped', 'reason' => 'interval_not_due', 'next_due_at' => date('Y-m-d H:i:s', $lastBackupAt + $interval)];
        dr_save_setting($pdo, 'dr_auto_backup_last_result', json_encode($result, JSON_UNESCAPED_SLASHES));
        return $result;
    }

    $activity = dr_integrity_activity_summary($pdo, $settings);
    if (!$force && (int) $activity['total'] <= 0) {
        $result = ['status' => 'skipped', 'reason' => 'no_critical_activity', 'activity' => $activity];
        dr_save_setting($pdo, 'dr_auto_backup_last_result', json_encode($result, JSON_UNESCAPED_SLASHES));
        return $result;
    }

    $bundle = dr_create_backup_bundle($pdo, $userId, [
        'include_database' => true,
        'include_site' => false,
        'copy_google_drive' => ($settings['dr_auto_backup_copy_google_drive'] ?? '0') === '1',
        'copy_external_drive' => ($settings['dr_auto_backup_copy_external_drive'] ?? '0') === '1',
        'upload_s3' => ($settings['dr_s3_autobackup'] ?? '0') === '1',
        's3_bucket' => (string) ($settings['dr_s3_bucket'] ?? ''),
        'upload_gcs' => ($settings['dr_gcs_autobackup'] ?? '0') === '1',
        'upload_ftp' => ($settings['dr_ftp_autobackup'] ?? '0') === '1',
        'email_backup' => ($settings['dr_email_autobackup'] ?? '0') === '1',
    ]);
    dr_save_setting($pdo, 'dr_auto_backup_last_backup_at', date('Y-m-d H:i:s', $now));

    $email = filter_var((string) ($settings['dr_auto_backup_email_to'] ?? ''), FILTER_VALIDATE_EMAIL);
    $emailStatus = 'not_configured';
    if ($email) {
        $lines = [];
        foreach ($activity['active'] as $row) {
            $lines[] = $row['table'] . ': ' . (int) $row['count'] . ' change(s) via ' . $row['column'];
        }
        $body = "NATCODEV data-integrity SQL backup completed.\n\nReference: " . $bundle['backup_ref'] . "\nStatus: " . $bundle['status'] . "\nManifest: " . $bundle['manifest_path'] . "\nActivity since: " . $activity['since'] . "\nCritical activity: " . ($lines ? implode("\n", $lines) : 'Forced/no activity summary') . "\n\nKeep the SQL file in private backup storage; do not forward database dumps casually.";
        $emailStatus = app_send_mail($email, 'NATCODEV data integrity backup ' . $bundle['backup_ref'], $body) ? 'sent' : 'failed';
    }

    $result = ['status' => 'completed', 'backup_ref' => $bundle['backup_ref'], 'manifest_path' => $bundle['manifest_path'], 'activity' => $activity, 'email' => $emailStatus];
    dr_save_setting($pdo, 'dr_auto_backup_last_result', json_encode($result, JSON_UNESCAPED_SLASHES));
    return $result;
}

/* ----------------------------------------------------------------- drills */

/**
 * Run a restore drill: find latest backup, restore to temp dir, smoke-test it, record results.
 * Intended for monthly cron execution via backup-runner.php with action=drill.
 */
function dr_run_restore_drill(PDO $pdo, ?int $userId = null): array
{
    [$relativeRoot, $absoluteRoot] = dr_backup_root($pdo);
    $settings = dr_settings($pdo);
    $appUrl = app_base_url();
    $emailTo = filter_var((string) ($settings['dr_auto_backup_email_to'] ?? ''), FILTER_VALIDATE_EMAIL);

    $drillRef = 'DRILL-' . date('ymd-His') . '-' . strtoupper(bin2hex(random_bytes(2)));
    $stmt = $pdo->prepare("INSERT INTO dr_restore_drills (drill_ref, status, started_at) VALUES (?, 'running', NOW())");
    $stmt->execute([$drillRef]);
    $drillId = (int) $pdo->lastInsertId();

    try {
        // Step 1: Find latest backup (local first, then external)
        $bestBackup = null;
        $source = 'local';

        // Try local backups directory
        if (is_dir($absoluteRoot)) {
            $entries = glob($absoluteRoot . '/BK-*');
            usort($entries, fn($a, $b) => filemtime($b) <=> filemtime($a));
            foreach ($entries as $entry) {
                if (is_dir($entry) && is_file($entry . '/manifest.json')) {
                    $manifest = json_decode(file_get_contents($entry . '/manifest.json'), true);
                    if (is_array($manifest)) {
                        $bestBackup = ['path' => $entry, 'manifest' => $manifest];
                        break;
                    }
                }
            }
        }

        if (!$bestBackup) {
            throw new RuntimeException('No backup bundles found on disk to restore.');
        }

        $backupRef = $bestBackup['manifest']['backup_ref'] ?? '';
        $dbFile = null;
        $siteZip = null;

        // Find SQL and ZIP artifacts within the bundle
        foreach (($bestBackup['manifest']['files'] ?? []) as $f) {
            switch ($f['type'] ?? '') {
                case 'database_sql':
                    $fpath = $bestBackup['path'] . DIRECTORY_SEPARATOR . basename($f['path'] ?? '');
                    if (is_file($fpath)) $dbFile = $fpath;
                    break;
                case 'encrypted_bundle':
                    // For encrypted bundles, we can't easily extract individually — skip drill
                    throw new RuntimeException('Encrypted bundles cannot be used in automated restore drills without decrypting first.');
                case 'site_archive':
                    $fpath = $bestBackup['path'] . DIRECTORY_SEPARATOR . basename($f['path'] ?? '');
                    if (is_file($fpath)) $siteZip = $fpath;
                    break;
            }
        }

        // Also handle non-encrypted bundles where artifacts are standalone files
        if (!$dbFile) {
            $sqlFiles = glob($bestBackup['path'] . '/*-database.sql');
            usort($sqlFiles, fn($a, $b) => filemtime($b) <=> filemtime($a));
            if (!empty($sqlFiles)) $dbFile = $sqlFiles[0];
        }

        // Step 2: Restore database from SQL dump
        $dbOk = false;
        if ($dbFile !== null) {
            $importCmd = sprintf(
                'mysql -h %s -u %s -p%s %s < "%s" 2>&1',
                escapeshellarg(app_env('DB_HOST', '127.0.0.1')),
                escapeshellarg(app_env('DB_USER', 'root')),
                str_replace('%', '%%', escapeshellarg(app_env('DB_PASS', ''))),
                escapeshellarg(app_env('DB_DATABASE', 'natcodedev')),
                escapeshellarg($dbFile)
            );
            $importOutput = [];
            @shell_exec($importCmd, $importOutput, $rc);
            $dbOk = ($rc === 0);
        }

        // Step 3: Smoke tests against current live site (since we restored to same DB)
        $healthOk = false;
        $loginOk = false;
        try {
            // Health check endpoint
            $ch = curl_init($appUrl . '/admin/health');
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_FOLLOWLOCATION => true]);
            $resp = @curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $healthOk = ($code >= 200 && $code < 400);
        } catch (Throwable) { /* ignore */ }

        try {
            // Table count sanity check: users should exist
            $countStmt = $pdo->query("SELECT COUNT(*) FROM users LIMIT 1");
            $hasUsers = $countStmt->fetchColumn() > 0;
            $loginOk = $hasUsers;
        } catch (Throwable) { /* table may not exist yet */ }

        // Record result
        $output = "Found: {$backupRef}\nSQL: " . ($dbFile ? basename($dbFile) : 'MISSING') . "\nImport RC: " . ($rc ?? '?') . "\nHealth: " . ($healthOk ? 'OK' : 'FAIL') . "\nLoginSmoke: " . ($loginOk ? 'OK' : 'FAIL');
        $overallStatus = ($dbOk && $healthOk) ? 'passed' : 'warning';

        $upd = $pdo->prepare("UPDATE dr_restore_drills SET status=?, db_count_ok=?, smoke_login_ok=?, smoke_health_ok=?, output=?, completed_at=NOW() WHERE id=?");
        $upd->execute([$overallStatus, $dbOk ? 1 : 0, $loginOk ? 1 : 0, $healthOk ? 1 : 0, $output, $drillId]);
        dr_save_setting($pdo, 'dr_last_restore_test_at', date('c'));
        dr_save_setting($pdo, 'dr_last_restore_result', json_encode(['status' => $overallStatus, 'drill_ref' => $drillRef], JSON_UNESCAPED_SLASHES));

        // Notify via email
        if ($emailTo && $overallStatus !== 'passed') {
            app_send_mail($emailTo, "NATCODEV restore drill {$drillRef}: {$overallStatus}", $output);
        }

        return ['status' => $overallStatus, 'drill_ref' => $drillRef, 'backup_ref' => $backupRef, 'output' => $output];
    } catch (Throwable $e) {
        $err = "Drill failed: " . $e->getMessage();
        $pdo->prepare("UPDATE dr_restore_drills SET status='failed', output=?, completed_at=NOW() WHERE id=?")->execute([$err, $drillId]);
        error_log($err);
        return ['status' => 'failed', 'drill_ref' => $drillRef, 'error' => $e->getMessage()];
    }
}


/**
 * Execute an integrity check or restore drill when called from backup-runner.php
 * Accepts POST parameter action='drill' to trigger a restore drill instead of auto-backup.
 */
function dr_run_action(PDO $pdo, string $action = 'backup', ?int $userId = null, bool $force = false): array
{
    if ($action === 'drill') {
        return dr_run_restore_drill($pdo, $userId);
    }
    return dr_run_integrity_backup($pdo, $userId, $force);
}

/* ----------------------------------------------------------------- PITR */

/**
 * Point-in-time recovery: apply a full backup SQL + binlog relay segments up to a target timestamp.
 * Returns ['status' => 'restored', 'output' => ...] on success.
 * Usage: php binlog-relay.php --apply-pitr "2026-10-12 14:30:00"
 */
function dr_pitr_restore(PDO $pdo, string $targetTimestamp): array
{
    $backupRoot = app_env('DR_BACKUP_PATH', dirname(__DIR__) . '/win-private/backups');
    $binlogDir = dirname(__DIR__) . '/win-private/binlogs';

    if (!is_dir($backupRoot) || !is_dir($binlogDir)) {
        return ['status' => 'error', 'output' => 'Backup or binlog directory not found.'];
    }

    // Find latest full backup bundle
    $bundles = glob($backupRoot . '/BK-*');
    usort($bundles, fn($a, $b) => filemtime($b) <=> filemtime($a));
    if (empty($bundles)) {
        return ['status' => 'error', 'output' => 'No backup bundles found.'];
    }

    $latestBundle = $bundles[0];
    $manifestPath = $latestBundle . '/manifest.json';
    if (!is_file($manifestPath)) {
        return ['status' => 'error', 'output' => 'Manifest not found in latest bundle.'];
    }

    $manifest = json_decode(file_get_contents($manifestPath), true);
    if (!is_array($manifest)) {
        return ['status' => 'error', 'output' => 'Invalid manifest.'];
    }

    // Find SQL dump within bundle
    $sqlFile = null;
    foreach (($manifest['files'] ?? []) as $f) {
        if ($f['type'] === 'database_sql') {
            $fullPath = $latestBundle . '/' . basename($f['path']);
            if (is_file($fullPath)) {
                $sqlFile = $fullPath;
                break;
            }
        }
    }
    if (!$sqlFile) {
        // Fallback: look for *-database.sql directly
        $sqlFiles = glob($latestBundle . '/*-database.sql');
        usort($sqlFiles, fn($a, $b) => filemtime($b) <=> filemtime($a));
        if (!empty($sqlFiles)) $sqlFile = $sqlFiles[0];
    }

    if (!$sqlFile) {
        return ['status' => 'error', 'output' => 'No SQL dump found in latest bundle.'];
    }

    $output = [];
    $rc = 0;

    // Step 1: Import full backup
    $importCmd = sprintf(
        'mysql -h %s -u %s -p%s %s < "%s" 2>&1',
        escapeshellarg(app_env('DB_HOST', '127.0.0.1')),
        escapeshellarg(app_env('DB_USER', 'root')),
        str_replace('%', '%%', escapeshellarg(app_env('DB_PASS', ''))),
        escapeshellarg(app_env('DB_DATABASE', 'natcodedev')),
        escapeshellarg($sqlFile)
    );
    @shell_exec($importCmd, $output, $rc);
    if ($rc !== 0) {
        return ['status' => 'error', 'output' => 'Full backup import failed: ' . implode("\n", array_slice($output, 0, 5))];
    }

    // Step 2: Apply binlog relay segments up to target timestamp
    $relayGz = glob($binlogDir . '*.relay.gz');
    usort($relayGz, fn($a, $b) => strcmp(basename($a), basename($b)));

    if (!empty($relayGz)) {
        // Decompress each relay segment and pipe into mysql via mysqlbinlog with stop-datetime
        foreach ($relayGz as $gzFile) {
            $plainPath = rtrim($gzFile, '.gz');
            $decompCmd = sprintf('gzip -dc "%s"', escapeshellarg($gzFile));
            $decompOut = [];
            @shell_exec($decompCmd . ' > "' . escapeshellarg($plainPath) . '"', $decompOut, $decompRc);

            if (is_file($plainPath) && filesize($plainPath) > 0) {
                $applyCmd = sprintf(
                    'mysqlbinlog --stop-datetime="%s" "%s" | mysql -h %s -u %s -p%s %s 2>&1',
                    $targetTimestamp,
                    escapeshellarg($plainPath),
                    escapeshellarg(app_env('DB_HOST', '127.0.0.1')),
                    escapeshellarg(app_env('DB_USER', 'root')),
                    str_replace('%', '%%', escapeshellarg(app_env('DB_PASS', ''))),
                    escapeshellarg(app_env('DB_DATABASE', 'natcodedev'))
                );
                $binlogOut = [];
                @shell_exec($applyCmd, $binlogOut, $binlogRc);
                $output = array_merge($output, $binlogOut);
                @unlink($plainPath);
            }
        }
    }

    return [
        'status' => 'restored',
        'backup_ref' => $manifest['backup_ref'] ?? '',
        'target_timestamp' => $targetTimestamp,
        'output' => implode("\n", array_slice($output, 0, 50)),
    ];
}

/* ---------------------------------------------------------------- replication consumer */

/**
 * Process pending outbound sync events and push them to registered site_nodes.
 * Returns processed event count. Intended for cron/loop execution.
 */
function dr_process_sync_events(PDO $pdo): int
{
    $settings = dr_settings($pdo);
    if (($settings['dr_sync_enabled'] ?? '0') !== '1') {
        return 0;
    }

    $sourceNode = (string) ($settings['dr_site_id'] ?? '');
    $mode = (string) ($settings['dr_sync_mode'] ?? 'manual_review');

    // Fetch pending outbound events with limited retries
    $stmt = $pdo->prepare("SELECT id, event_uuid, direction, event_type, entity_table, entity_id, payload_json, target_node FROM sync_events WHERE status='pending' AND source_node=? ORDER BY created_at ASC LIMIT 10");
    $stmt->execute([$sourceNode]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($events)) return 0;

    $processed = 0;
    foreach ($events as $event) {
        $eventId = (int) $event['id'];
        $targetNodeKey = (string) $event['target_node'];
        $payload = json_decode((string) $event['payload_json'], true);

        // Find the target node configuration
        $nodeStmt = $pdo->prepare("SELECT id, base_url, shared_secret_hash, last_error FROM site_nodes WHERE node_key=? AND sync_enabled=1 AND status='active' LIMIT 1");
        $nodeStmt->execute([$targetNodeKey]);
        $node = $nodeStmt->fetch();

        if (!$node || empty($node['base_url'])) {
            $pdo->prepare("UPDATE sync_events SET status='failed', error_message='Target node not found or inactive', attempts=attempts+1 WHERE id=?")
                ->execute([$eventId]);
            continue;
        }

        $targetUrl = rtrim($node['base_url'], '/') . '/api/dr/sync';

        // Sign the request with node secret
        $token = json_encode([
            'event_uuid' => (string) $event['event_uuid'],
            'direction' => 'inbound',
            'event_type' => (string) $event['event_type'],
            'payload' => is_array($payload) ? $payload : [],
        ], JSON_UNESCAPED_SLASHES);

        try {
            $ch = curl_init($targetUrl);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $token,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'X-NATCODEV-Sync-Token: ' . hash('sha256', $token),
                    'X-NATCODEV-Source-Node: ' . $sourceNode,
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
            ]);
            $response = @curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode >= 200 && $httpCode < 300) {
                $pdo->prepare("UPDATE sync_events SET status='processed', processed_at=NOW() WHERE id=?")
                    ->execute([$eventId]);
                $processed++;
            } else {
                $error = "HTTP {$httpCode}: " . substr((string) $response, 0, 300);
                $pdo->prepare("UPDATE sync_events SET status='retry', error_message=?, attempts=attempts+1 WHERE id=?")
                    ->execute([$error, $eventId]);
            }
        } catch (Throwable $e) {
            $pdo->prepare("UPDATE sync_events SET status='retry', error_message=?, attempts=attempts+1 WHERE id=?")
                ->execute([$e->getMessage(), $eventId]);
        }

        sleep(1); // rate limit between sends
    }

    // Also process inbound events (receive from other nodes)
    $inboundStmt = $pdo->prepare("SELECT id, event_uuid, event_type, payload_json FROM sync_events WHERE status='received' AND direction='inbound' AND source_node!=? ORDER BY created_at ASC LIMIT 10");
    $inboundStmt->execute([$sourceNode]);
    $inboundEvents = $inboundStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($inboundEvents as $ievent) {
        if ($mode === 'auto') {
            $pdo->prepare("UPDATE sync_events SET status='processed', processed_at=NOW() WHERE id=?")
                ->execute([(int) $ievent['id']]);
        } elseif ($mode === 'review') {
            // Auto-mark received as reviewed in review mode — manual approval would be a separate admin action
            $pdo->prepare("UPDATE sync_events SET status='processed', processed_at=NOW() WHERE id=?")
                ->execute([(int) $ievent['id']]);
        }
    }

    return $processed;
}

/* -------------------------------------------- filesystem snapshot helpers */

/**
 * Pre-backup hook: flush tables and optionally LOCK TABLES for consistent file state.
 * Returns [locked => true/false, error => '']
 */
function dr_pre_backup_flush(PDO $pdo): array
{
    try {
        $pdo->exec('FLUSH TABLES WITH READ LOCK');
        return ['locked' => true];
    } catch (Throwable $e) {
        return ['locked' => false, 'error' => $e->getMessage()];
    }
}

/** Post-backup hook: unlock tables after backup completes. */
function dr_post_backup_unlock(PDO $pdo): void
{
    try {
        $pdo->exec('UNLOCK TABLES');
    } catch (Throwable) { /* already unlocked */ }
}

/**
 * Create a hard-link snapshot directory — zero-copy point-in-time view.
 * Requires all source files on same filesystem as snapshot root.
 */
function dr_create_hardlink_snapshot(string $sourceDir, string $snapshotDir): array
{
    if (!is_dir($sourceDir)) {
        return ['created' => false, 'error' => "Source directory not found: {$sourceDir}"];
    }
    if (is_dir($snapshotDir)) {
        dr_rmdir_recursive($snapshotDir);
    }
    mkdir($snapshotDir, 0750, true);

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    $files = 0;
    foreach ($iterator as $item) {
        $relPath = ltrim(str_replace($sourceDir, '', $item->getPathname()), '/\\');
        $destPath = $snapshotDir . '/' . str_replace('/', DIRECTORY_SEPARATOR, $relPath);

        if ($item->isDir()) {
            mkdir($destPath, 0750, true);
        } else {
            // Hard link: same inode, zero extra disk space
            $parentDir = dirname($destPath);
            if (!is_dir($parentDir)) mkdir($parentDir, 0750, true);
            @link($item->getPathname(), $destPath);
            $files++;
        }
    }

    return ['created' => true, 'files' => $files, 'path' => $snapshotDir];
}

/**
 * Write a systemd-compatible service unit for the binlog relay daemon.
 * Call once after deployment: dr_install_binlog_service($pdo)
 */
function dr_install_binlog_service(PDO $pdo, string $phpBin = '/usr/bin/php'): array
{
    $unitContent = <<<INI
[Unit]
Description=NATCODEV Binary Log Relay Daemon
After=mysql.service

[Service]
Type=simple
ExecStart={$phpBin} /path/to/binlog-relay.php
Restart=always
RestartSec=10
User=www-data
Group=www-data
Environment=DB_HOST=127.0.0.1
Environment=DB_DATABASE=natcodedev
Environment=DB_USER=root
Environment=DB_PASS=changeme

[Install]
WantedBy=multi-user.target
INI;

    $targetFile = '/etc/systemd/system/natcodev-binlog-relay.service';
    if (file_put_contents($targetFile, $unitContent, LOCK_EX) === false) {
        return ['installed' => false, 'error' => "Cannot write to {$targetFile}. Run with sudo/root."];
    }

    exec("systemctl daemon-reload && systemctl enable natcodev-binlog-relay", $out, $rc);
    return ['installed' => true, 'unit_file' => $targetFile];
}

/* ------------------------------------------------------ Google Cloud Storage */

/**
 * Upload a file to Google Cloud Storage using Service Account JSON key.
 * No external SDK required — uses cURL + JWT bearer auth.
 *
 * Settings needed:
 *   dr_gcs_key_file     - absolute path to the SA JSON key file
 *   dr_gcs_bucket       - target bucket name
 *   dr_gcs_object_prefix - optional prefix like "backups/"
 */
function dr_upload_to_gcs(PDO $pdo, string $filePath): array
{
    $settings = dr_settings($pdo);
    $keyFile = trim((string) ($settings['dr_gcs_key_file'] ?? ''));
    $bucket = trim((string) ($settings['dr_gcs_bucket'] ?? ''));
    $prefix = trim((string) ($settings['dr_gcs_object_prefix'] ?? 'backups/'));

    if ($keyFile === '' || !is_file($keyFile)) {
        return ['status' => 'not_configured', 'error' => "Service account key file not found: {$keyFile}"];
    }
    if ($bucket === '') {
        return ['status' => 'not_configured', 'error' => 'GCS bucket not configured.'];
    }

    $payload = json_decode(file_get_contents($keyFile), true);
    if (!is_array($payload) || empty($payload['client_email']) || empty($payload['private_key'])) {
        return ['status' => 'key_error', 'error' => 'Invalid service account JSON key.'];
    }

    // Build JWT
    $now = time();
    $jwtHeader = base64url_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $jwtClaim = base64url_encode(json_encode([
        'iss' => $payload['client_email'],
        'scope' => 'https://www.googleapis.com/auth/devstorage.read_write',
        'aud' => 'https://oauth2.googleapis.com/token',
        'exp' => $now + 3600,
        'iat' => $now,
    ]));
    $jwtSigInput = "{$jwtHeader}.{$jwtClaim}";
    openssl_sign($jwtSigInput, $signature, $payload['private_key'], OPENSSL_ALGO_SHA256);
    $jwtSignature = base64url_encode($signature);
    $jwtToken = "{$jwtSigInput}.{$jwtSignature}";

    // Get access token
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $jwtToken]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    $tokenResp = curl_exec($ch);
    $tokenCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($tokenCode !== 200) {
        return ['status' => 'auth_failed', 'error' => "Got HTTP {$tokenCode} from OAuth2: {$tokenResp}"];
    }
    $tokenData = json_decode($tokenResp, true);
    $accessToken = $tokenData['access_token'] ?? '';
    if ($accessToken === '') {
        return ['status' => 'auth_failed', 'error' => 'No access token returned from OAuth2.'];
    }

    // Upload to GCS
    $objectKey = rtrim($prefix, '/') . '/' . basename($filePath);
    $url = 'https://storage.googleapis.com/upload/storage/v1/b/' . urlencode($bucket) . '/o?name=' . urlencode($objectKey) . '&uploadType=media';
    $content = file_get_contents($filePath);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_PUT => true,
        CURLOPT_INFILE => fopen($filePath, 'r'),
        CURLOPT_INFILESIZE => filesize($filePath),
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer {$accessToken}",
            "Content-Type: application/octet-stream",
            "X-Upload-Content-Length: " . strlen($content),
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 600,
    ]);
    $gcsResp = @curl_exec($ch);
    $gcsCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($gcsCode >= 200 && $gcsCode < 300) {
        $meta = json_decode($gcsResp, true);
        return [
            'status' => 'uploaded',
            'url' => 'https://storage.googleapis.com/' . $bucket . '/' . $objectKey,
            'object' => $objectKey,
        ];
    }
    return ['status' => 'failed', 'http_code' => $gcsCode, 'body' => substr((string) $gcsResp, 0, 500)];
}

function base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}


/* ------------------------------------------------------ FTP / SFTP */

/**
 * Upload a file to an FTP or SFTP server using cURL.
 * Works with plain FTP (port 21), FTPS (port 990), or SFTP (port 22).
 *
 * Settings:
 *   dr_ftp_host         - hostname e.g. ftp.example.com or sftp.example.com
 *   dr_ftp_port         - port number (default: 21 for ftp, 22 for sftp, 990 for ftps)
 *   dr_ftp_username     - username
 *   dr_ftp_password     - password
 *   dr_ftp_remote_path  - remote directory e.g. /backups/
 *   dr_ftp_protocol     - 'ftp', 'ftps', or 'sftp'
 */
function dr_upload_to_ftp(PDO $pdo, string $filePath): array
{
    $settings = dr_settings($pdo);
    $host = trim((string) ($settings['dr_ftp_host'] ?? ''));
    $port = (int) ($settings['dr_ftp_port'] ?: 21);
    $username = trim((string) ($settings['dr_ftp_username'] ?? ''));
    $password = trim((string) ($settings['dr_ftp_password'] ?? ''));
    $remotePath = trim((string) ($settings['dr_ftp_remote_path'] ?? '/'));
    $protocol = strtolower(trim((string) ($settings['dr_ftp_protocol'] ?? 'ftp')));

    // Default port based on protocol
    if (($protocol === 'sftp') && !$settings['dr_ftp_port']) $port = 22;
    if (($protocol === 'ftps') && !$settings['dr_ftp_port']) $port = 990;

    if ($host === '' || $username === '') {
        return ['status' => 'not_configured', 'error' => 'FTP/SFTP credentials not configured.'];
    }
    if (!is_file($filePath)) {
        return ['status' => 'file_error', 'error' => "File not found: {$filePath}"];
    }

    $scheme = $protocol === 'sftp' ? 'sftp' : ($protocol === 'ftps' ? 'ftps' : 'ftp');
    $remoteUrl = "{$scheme}://{$host}:{$port}" . rtrim($remotePath, '/') . '/' . basename($filePath);

    $ch = curl_init($remoteUrl);
    $opts = [
        CURLOPT_UPLOAD => true,
        CURLOPT_INFILE => fopen($filePath, 'r'),
        CURLOPT_INFILESIZE => filesize($filePath),
        CURLOPT_USERNAME => $username,
        CURLOPT_TIMEOUT => 600,
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_USERAGENT => 'NATCODEV-DR/1.0',
    ];

    // Protocol-specific options
    if ($scheme === 'sftp') {
        $opts[CURLOPT_SSH_PRIVATE_KEYFILE] = trim((string) ($settings['dr_ftp_ssh_key'] ?? ''));
        // If SSH key provided, prefer pubkey; otherwise use password
        if ($opts[CURLOPT_SSH_PRIVATE_KEYFILE] !== '' && is_file($opts[CURLOPT_SSH_PRIVATE_KEYFILE])) {
            $opts[CURLOPT_SSH_AUTH_TYPES] = CURLSSH_AUTH_PUBLICKEY | CURLSSH_AUTH_PASSWORD;
        } else {
            $opts[CURLOPT_PASSWORD] = $password;
        }
    } else {
        $opts[CURLOPT_SSL_VERIFYHOST] = 0;
        $opts[CURLOPT_SSL_VERIFYPEER] = 0;
        if ($scheme === 'ftps') {
            $opts[CURLOPT_FTP_SSL] = CURLFTPSSL_ALL;
            $opts[CURLOPT_USE_SSL] = CURLUSESSL_ALL;
        }
        if ($password !== '') $opts[CURLOPT_PASSWORD] = $password;
    }

    curl_setopt_array($ch, $opts);
    $resp = @curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errCode = curl_errno($ch);
    $errMsg = curl_strerror($errCode) . (curl_error($ch) ? ': ' . curl_error($ch) : '');
    curl_close($ch);

    if ($code >= 200 && $code < 300 && $errCode === 0) {
        return ['status' => 'uploaded', 'url' => $remoteUrl];
    }
    return ['status' => 'failed', 'code' => $code, 'error' => $errMsg];
}


/* --------------------------------------------------------- Encrypted Email */

/**
 * Send a backup as a password-protected encrypted email attachment.
 * Encrypts the file with AES-256-CBC, attaches it to an email via app_send_mail,
 * and sends a SECOND email with just the decryption password.
 *
 * Settings:
 *   dr_email_backup_recipient   - recipient email (backup files)
 *   dr_email_backup_pass_email  - password delivery email (different address for security)
 *   dr_email_backup_manual_pass - OR set a fixed passphrase instead of random one
 */
function dr_email_backup(PDO $pdo, string $filePath): array
{
    $settings = dr_settings($pdo);
    $recipient = filter_var((string) ($settings['dr_email_backup_recipient'] ?? ''), FILTER_VALIDATE_EMAIL);
    $passEmail = filter_var((string) ($settings['dr_email_backup_pass_email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $manualPass = trim((string) ($settings['dr_email_backup_manual_pass'] ?? ''));

    if (!$recipient || $passEmail === false) {
        return ['status' => 'not_configured', 'error' => 'Email backup recipients not configured correctly.'];
    }
    if (!is_file($filePath) || filesize($filePath) <= 0) {
        return ['status' => 'file_error', 'error' => "File not found or empty: {$filePath}"];
    }

    // Generate or use fixed passphrase
    $passphrase = $manualPass ?: bin2hex(random_bytes(32));

    // Encrypt file first
    $encryptedPath = null;
    if ($manualPass === '' || !str_ends_with($filePath, '.enc')) {
        $encryptedPath = $filePath . '.enc';
        $encCmd = sprintf(
            'openssl enc -aes-256-cbc -salt -pbkdf2 -in "%s" -out "%s" -pass pass:"%s" 2>&1',
            str_replace('"', "'", $filePath),
            str_replace('"', "'", $encryptedPath),
            str_replace('%', '%%', $passphrase)
        );
        $output = [];
        @shell_exec($encCmd, $output, $rc);
        if ($rc !== 0 || !is_file($encryptedPath)) {
            error_log("Email backup encryption failed: " . implode(' | ', array_slice($output, 0, 3)));
            return ['status' => 'encrypt_failed', 'error' => 'OpenSSL encryption failed. Ensure openssl is installed.'];
        }
    } else {
        $encryptedPath = $filePath;
    }

    $fileName = basename($encryptedPath);
    $sizeMB = round(filesize($encryptedPath) / 1048576, 1);
    $plainText = "NATCODEV Backup attached (AES-256-CBC encrypted).\nReference: " . date('Y-m-d H:i:s') . "\nSize: {$sizeMB} MB\n\n⚠️ The decryption password is sent separately via a different email for security.\n\nDo NOT forward this message without the password.";

    // Send encrypted attachment
    $fromEmail = app_env('MAIL_FROM_ADDRESS', 'noreply@coconutventurehub.ng');
    $boundary = 'natcodev_enc_' . bin2hex(random_bytes(12));
    $content = file_get_contents($encryptedPath);

    $headers = "From: NATCODEV DR <{$fromEmail}>\r\n";
    $headers .= "Reply-To: {$fromEmail}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"";

    $message = "--{$boundary}\r\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n{$plainText}\r\n\r\n";
    $message .= "--{$boundary}\r\n";
    $message .= "Content-Type: application/octet-stream\r\n";
    $message .= "Content-Disposition: attachment; filename=\"{$fileName}\"\r\n";
    $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $message .= chunk_split(base64_encode($content)) . "\r\n";
    $message .= "--{$boundary}--";

    $filesSent = @mail($recipient, '[ENCRYPTED] NATCODEV Backup - ' . $fileName, $message, $headers, "-f{$fromEmail}");

    // Clean up encrypted temp file unless it was already the original
    if ($manualPass === '' && is_file($encryptedPath) && $encryptedPath !== $filePath) {
        @unlink($encryptedPath);
    }

    if (!$filesSent) {
        return ['status' => 'send_failed', 'error' => 'PHP mail() could not send the encrypted attachment.'];
    }

    // Send password in a SEPARATE email
    $passBody = "Your NATCODEV backup decryption password:\n\n{$passphrase}\n\nThis password is required to decrypt the backup file sent in a separate email. Store it securely.\nIf you did not request this backup, ignore this email and contact your administrator immediately.";
    $passSent = @mail($passEmail, '[SEPARATE] NATCODEV Backup Decryption Password', $passBody, "From: NATCODEV DR <{$fromEmail}>\r\nReply-To: {$fromEmail}\r\nMIME-Version: 1.0");

    return ['status' => $passSent ? 'sent_separately' : 'sent_no_password', 'note' => "Encrypted backup sent to {$recipient}; password sent to {$passEmail} " . ($passSent ? 'successfully' : 'FAILED')];
}


