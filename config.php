<?php
declare(strict_types=1);

if (defined('NATCODEV_BOOTSTRAPPED')) {
    return;
}
define('NATCODEV_BOOTSTRAPPED', true);

function app_load_env(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, "\"'");
        if ($key !== '' && (getenv($key) === false || getenv($key) === '')) {
            putenv($key . '=' . $value);
        }
        if ($key !== '' && (!isset($_ENV[$key]) || $_ENV[$key] === '')) {
            $_ENV[$key] = $value;
        }
    }
}

app_load_env(__DIR__ . '/.env');

// Harden session cookie security
ini_set('session.cookie_httponly', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_samesite', 'Lax');
if (str_starts_with(app_base_url(), 'https://')) {
    ini_set('session.cookie_secure', '1');
}

date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Africa/Lagos');

function app_env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    if ($value !== false && $value !== '') {
        return $value;
    }
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
        return (string) $_ENV[$key];
    }
    return $default;
}

function app_is_production(): bool
{
    return strtolower((string) app_env('APP_ENV', 'production')) === 'production';
}

function app_base_url(): string
{
    return rtrim((string) app_env('APP_URL', 'https://natcodev.com.ng'), '/');
}

function app_primary_logo_path(): string
{
    return 'assets/logo/natcodev.jpeg';
}

function app_primary_logo_url(): string
{
    return app_base_url() . '/' . app_primary_logo_path();
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = app_env('DB_HOST', '127.0.0.1');
    $port = app_env('DB_PORT', '3306');
    $name = app_env('DB_DATABASE', '');
    $user = app_env('DB_USERNAME', '');
    $pass = app_env('DB_PASSWORD', '');

    if ($name === '' || $user === '') {
        throw new RuntimeException('Database configuration is incomplete.');
    }

    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    return $pdo;
}

function app_schema_flag_is_set(PDO $pdo, string $key, string $version): bool
{
    static $cache = [];
    $cacheKey = $key . ':' . $version;
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS app_schema_flags (
                flag_key VARCHAR(120) PRIMARY KEY,
                flag_value VARCHAR(120) NOT NULL,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $stmt = $pdo->prepare("SELECT flag_value FROM app_schema_flags WHERE flag_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $cache[$cacheKey] = (string) ($stmt->fetchColumn() ?: '') === $version;
        return $cache[$cacheKey];
    } catch (Throwable $e) {
        return false;
    }
}

function app_schema_flag_set(PDO $pdo, string $key, string $version): void
{
    try {
        $stmt = $pdo->prepare("INSERT INTO app_schema_flags (flag_key, flag_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE flag_value = VALUES(flag_value)");
        $stmt->execute([$key, $version]);
    } catch (Throwable $e) {
        error_log("Unable to set schema flag {$key}: " . $e->getMessage());
    }
}

function app_ensure_core_schema(PDO $pdo): void
{
    static $done = false;
    if ($done || app_schema_flag_is_set($pdo, 'core_schema_ready', '20260606-fast')) {
        $done = true;
        return;
    }

    try {
        $existing = $pdo->query("
            SELECT COUNT(*)
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME IN ('applications','users','notification_logs')
        ")->fetchColumn();
        if ((int) $existing === 3) {
            app_schema_flag_set($pdo, 'core_schema_ready', '20260606-fast');
            $done = true;
            return;
        }
    } catch (Throwable $e) {
        // Fall through to the normal create path.
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS applications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            app_ref VARCHAR(50) NOT NULL UNIQUE,
            name VARCHAR(255) NOT NULL,
            location VARCHAR(255) NOT NULL,
            farm_size DECIMAL(10,2) NOT NULL,
            phone VARCHAR(20) NOT NULL UNIQUE,
            whatsapp VARCHAR(20) NULL,
            email VARCHAR(255) NOT NULL UNIQUE,
            commitments TEXT NOT NULL,
            confirmed TINYINT(1) NOT NULL DEFAULT 0,
            confirmation_token VARCHAR(64) NULL UNIQUE,
            confirmed_at DATETIME NULL,
            email_sent TINYINT(1) NOT NULL DEFAULT 0,
            team_notified TINYINT(1) NOT NULL DEFAULT 0,
            ip_address VARCHAR(45) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_applications_confirmed (confirmed),
            INDEX idx_applications_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            application_id INT NULL,
            name VARCHAR(255) NOT NULL,
            role VARCHAR(50) NOT NULL DEFAULT 'grower',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_users_application_id (application_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    app_ensure_primary_auto_increment($pdo, 'applications');
    app_ensure_primary_auto_increment($pdo, 'users');
    foreach ([
        'state_id' => "INT NULL",
        'lga_id' => "INT NULL",
        'street_address' => "VARCHAR(255) NULL",
        'alternate_email' => "VARCHAR(255) NULL",
        'alternate_phone' => "VARCHAR(50) NULL",
        'review_status' => "VARCHAR(40) NOT NULL DEFAULT 'active'",
        'latitude' => "DECIMAL(10,7) NULL",
        'longitude' => "DECIMAL(10,7) NULL",
    ] as $column => $definition) {
        app_add_column_if_missing($pdo, 'applications', $column, $definition);
    }
    foreach ([
        'phone' => "VARCHAR(30) NULL",
        'profile_picture' => "VARCHAR(255) NULL",
        'location' => "VARCHAR(255) NULL",
        'notify_email' => "TINYINT(1) NOT NULL DEFAULT 1",
        'notify_whatsapp' => "TINYINT(1) NOT NULL DEFAULT 0",
        'notify_sms' => "TINYINT(1) NOT NULL DEFAULT 0",
    ] as $column => $definition) {
        app_add_column_if_missing($pdo, 'users', $column, $definition);
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notification_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            channel VARCHAR(30) NOT NULL,
            recipient VARCHAR(255) NOT NULL,
            subject VARCHAR(255) NULL,
            message_preview TEXT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'pending',
            transport VARCHAR(60) NULL,
            provider_response TEXT NULL,
            error_message TEXT NULL,
            context VARCHAR(120) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_notification_logs_channel (channel),
            INDEX idx_notification_logs_status (status),
            INDEX idx_notification_logs_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'notification_logs');
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS app_rate_limits (
            limit_key VARCHAR(180) PRIMARY KEY,
            attempts INT NOT NULL DEFAULT 1,
            last_attempt_at INT NOT NULL,
            expires_at INT NOT NULL,
            INDEX idx_rate_limit_expiry (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    app_schema_flag_set($pdo, 'core_schema_ready', '20260606-fast');
    $done = true;
}

/**
 * Basic database-backed rate limiting.
 * Returns true if the action is allowed, false if blocked.
 */
function app_check_rate_limit(string $action, int $maxAttempts, int $decaySeconds): bool
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = $action . ':' . $ip;
    $now = time();
    $pdo = db();

    try {
        $stmt = $pdo->prepare("SELECT attempts, last_attempt_at FROM app_rate_limits WHERE limit_key = ? AND expires_at > ? LIMIT 1");
        $stmt->execute([$key, $now]);
        $record = $stmt->fetch();

        if (!$record) {
            $stmt = $pdo->prepare("INSERT INTO app_rate_limits (limit_key, attempts, last_attempt_at, expires_at) VALUES (?, 1, ?, ?)");
            $stmt->execute([$key, $now, $now + $decaySeconds]);
            return true;
        }

        if ((int)$record['attempts'] >= $maxAttempts) {
            return false;
        }

        $stmt = $pdo->prepare("UPDATE app_rate_limits SET attempts = attempts + 1, last_attempt_at = ? WHERE limit_key = ?");
        $stmt->execute([$now, $key]);
        return true;
    } catch (Throwable $e) {
        error_log('Rate limit database check failed: ' . $e->getMessage());
        return app_check_file_rate_limit($key, $maxAttempts, $decaySeconds);
    }
}

function app_check_file_rate_limit(string $key, int $maxAttempts, int $decaySeconds): bool
{
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'natcodev-rate-limits';
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        error_log('Rate limit fallback directory unavailable.');
        return false;
    }

    $file = $dir . DIRECTORY_SEPARATOR . hash('sha256', $key) . '.json';
    $now = time();
    $record = ['attempts' => 0, 'expires_at' => 0];
    $handle = fopen($file, 'c+');
    if (!$handle) {
        error_log('Rate limit fallback file unavailable.');
        return false;
    }

    try {
        if (!flock($handle, LOCK_EX)) {
            fclose($handle);
            return false;
        }
        $raw = stream_get_contents($handle);
        $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
        if (is_array($decoded)) {
            $record = $decoded;
        }
        if ((int) ($record['expires_at'] ?? 0) <= $now) {
            $record = ['attempts' => 0, 'expires_at' => $now + $decaySeconds];
        }
        if ((int) ($record['attempts'] ?? 0) >= $maxAttempts) {
            flock($handle, LOCK_UN);
            fclose($handle);
            return false;
        }
        $record['attempts'] = (int) ($record['attempts'] ?? 0) + 1;
        $record['expires_at'] = (int) ($record['expires_at'] ?? ($now + $decaySeconds));
        rewind($handle);
        ftruncate($handle, 0);
        fwrite($handle, json_encode($record, JSON_UNESCAPED_SLASHES) ?: '{}');
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);
        return true;
    } catch (Throwable $e) {
        error_log('Rate limit fallback failed: ' . $e->getMessage());
        fclose($handle);
        return false;
    }
}

function app_table_exists(PDO $pdo, string $table): bool
{
    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
    ");
    $stmt->execute([$table]);
    $cache[$table] = (int) $stmt->fetchColumn() > 0;
    return $cache[$table];
}

function app_column_exists(PDO $pdo, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
    ");
    $stmt->execute([$table, $column]);
    $cache[$key] = (int) $stmt->fetchColumn() > 0;
    return $cache[$key];
}

function app_add_column_if_missing(PDO $pdo, string $table, string $column, string $definition): void
{
    if (!app_column_exists($pdo, $table, $column)) {
        $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
    }
}

function app_column_extra(PDO $pdo, string $table, string $column): string
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $stmt = $pdo->prepare("
        SELECT EXTRA
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
        LIMIT 1
    ");
    $stmt->execute([$table, $column]);
    $cache[$key] = strtolower((string) $stmt->fetchColumn());
    return $cache[$key];
}

function app_primary_key_columns(PDO $pdo, string $table): array
{
    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

    $stmt = $pdo->prepare("
        SELECT COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND CONSTRAINT_NAME = 'PRIMARY'
        ORDER BY ORDINAL_POSITION
    ");
    $stmt->execute([$table]);
    $cache[$table] = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    return $cache[$table];
}

function app_ensure_primary_auto_increment(PDO $pdo, string $table): void
{
    static $checked = null;
    if ($checked === null) {
        $checked = [];
    }
    
    if (isset($checked[$table])) {
        return;
    }
    
    // Only check if not already checked for this table in this request
    $checked[$table] = true;

    if (!app_column_exists($pdo, $table, 'id')) {
        return;
    }
    
    $primary = app_primary_key_columns($pdo, $table);
    if ($primary === []) {
        try {
            $pdo->exec("ALTER TABLE `{$table}` ADD PRIMARY KEY (`id`)");
        } catch (Throwable $e) {
            error_log("Unable to add primary key to {$table}.id: " . $e->getMessage());
        }
    }

    if (!str_contains(app_column_extra($pdo, $table, 'id'), 'auto_increment')) {
        try {
            $pdo->exec("ALTER TABLE `{$table}` MODIFY `id` INT NOT NULL AUTO_INCREMENT");
        } catch (Throwable $e) {
            error_log("Unable to enable auto_increment on {$table}.id: " . $e->getMessage());
        }
    }
}

function app_resequence_id_column(PDO $pdo, string $table): void
{
    $quotedTable = '`' . str_replace('`', '``', $table) . '`';
    $pdo->exec('SET @natcodev_row_number := 0');
    $pdo->exec("UPDATE {$quotedTable} SET `id` = (@natcodev_row_number := @natcodev_row_number + 1) ORDER BY `id`");
}

function app_ensure_certificate_schema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS certificates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            certificate_ref VARCHAR(80) NOT NULL UNIQUE,
            application_id INT NOT NULL,
            user_id INT NULL,
            certificate_path VARCHAR(255) NULL,
            certificate_pdf_path VARCHAR(255) NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'issued',
            issued_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NULL,
            verified_at DATETIME NULL,
            qr_code_hash VARCHAR(64) NULL,
            verification_url VARCHAR(255) NULL,
            revoked_at DATETIME NULL,
            revoked_reason TEXT NULL,
            INDEX idx_certificates_application_id (application_id),
            INDEX idx_certificates_user_id (user_id),
            INDEX idx_certificates_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    app_add_column_if_missing($pdo, 'certificates', 'certificate_ref', "VARCHAR(80) NULL UNIQUE");
    app_add_column_if_missing($pdo, 'certificates', 'user_id', "INT NULL");
    app_add_column_if_missing($pdo, 'certificates', 'certificate_path', "VARCHAR(255) NULL");
    app_add_column_if_missing($pdo, 'certificates', 'certificate_pdf_path', "VARCHAR(255) NULL");
    app_add_column_if_missing($pdo, 'certificates', 'status', "VARCHAR(30) NOT NULL DEFAULT 'issued'");
    app_add_column_if_missing($pdo, 'certificates', 'expires_at', "DATETIME NULL");
    app_add_column_if_missing($pdo, 'certificates', 'verified_at', "DATETIME NULL");
    app_add_column_if_missing($pdo, 'certificates', 'qr_code_hash', "VARCHAR(64) NULL");
    app_add_column_if_missing($pdo, 'certificates', 'verification_url', "VARCHAR(255) NULL");
    app_add_column_if_missing($pdo, 'certificates', 'revoked_at', "DATETIME NULL");
    app_add_column_if_missing($pdo, 'certificates', 'revoked_reason', "TEXT NULL");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS certificate_types (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type_key VARCHAR(80) NOT NULL UNIQUE,
            title VARCHAR(180) NOT NULL,
            description TEXT NULL,
            validity_months INT NULL,
            revocable TINYINT(1) NOT NULL DEFAULT 1,
            permanent TINYINT(1) NOT NULL DEFAULT 0,
            status VARCHAR(30) NOT NULL DEFAULT 'active',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_certificate_types_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'certificate_types');
    try {
        $stmt = $pdo->prepare("
            INSERT INTO certificate_types (type_key, title, description, validity_months, revocable, permanent, status)
            VALUES (?, ?, ?, ?, ?, ?, 'active')
            ON DUPLICATE KEY UPDATE title = VALUES(title), description = VALUES(description), validity_months = VALUES(validity_months), revocable = VALUES(revocable), permanent = VALUES(permanent)
        ");
        $stmt->execute(['grower_participation', 'Verified Grower Participation Certificate', 'Time-bound grower, participation, farmer-credit, seller-accreditation, and related operational credentials.', 36, 1, 0]);
        $stmt->execute(['academy_course', 'Academy Course Certificate', 'Permanent certificate issued after completing an Academy course.', null, 0, 1]);
        $stmt->execute(['academy_group', 'Academy Grouped Certificate', 'Permanent certificate issued after completing a grouped Academy certificate pathway.', null, 0, 1]);
    } catch (Throwable $e) {
    }
    try {
        $pdo->exec("UPDATE certificates SET expires_at = DATE_ADD(issued_at, INTERVAL 36 MONTH) WHERE status = 'issued' AND issued_at IS NOT NULL");
    } catch (Throwable $e) {
    }
    app_ensure_primary_auto_increment($pdo, 'certificates');
}

function app_ensure_farmer_engagement_schema(PDO $pdo): void
{
    app_ensure_core_schema($pdo);

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            admin_id INT NULL DEFAULT 1,
            message TEXT NOT NULL,
            is_from_admin TINYINT(1) NOT NULL DEFAULT 0,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            ticket_id VARCHAR(50) NULL,
            category VARCHAR(50) DEFAULT 'general',
            priority ENUM('low','medium','high') DEFAULT 'medium',
            status ENUM('open','in_progress','resolved','closed') DEFAULT 'open',
            INDEX idx_messages_user_created (user_id, created_at),
            INDEX idx_messages_read (is_read)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_add_column_if_missing($pdo, 'messages', 'admin_id', "INT NULL DEFAULT 1");
    app_add_column_if_missing($pdo, 'messages', 'ticket_id', "VARCHAR(50) NULL");
    app_add_column_if_missing($pdo, 'messages', 'category', "VARCHAR(50) DEFAULT 'general'");
    app_add_column_if_missing($pdo, 'messages', 'priority', "ENUM('low','medium','high') DEFAULT 'medium'");
    app_add_column_if_missing($pdo, 'messages', 'status', "ENUM('open','in_progress','resolved','closed') DEFAULT 'open'");
    app_ensure_primary_auto_increment($pdo, 'messages');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS document_requirements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            document_type ENUM('nin','bvn','land_title','id_card','farm_photo') NOT NULL,
            document_number VARCHAR(120) NOT NULL,
            file_path VARCHAR(255) NULL,
            verified TINYINT(1) NOT NULL DEFAULT 0,
            verification_status VARCHAR(30) NOT NULL DEFAULT 'pending',
            verification_notes TEXT NULL,
            verified_by INT NULL,
            verified_at DATETIME NULL,
            api_validation_status VARCHAR(30) DEFAULT 'pending',
            api_validation_response TEXT NULL,
            api_validation_timestamp TIMESTAMP NULL DEFAULT NULL,
            retry_count INT NOT NULL DEFAULT 0,
            last_retry_at TIMESTAMP NULL DEFAULT NULL,
            uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_user_document_type (user_id, document_type),
            INDEX idx_document_status (verification_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_add_column_if_missing($pdo, 'document_requirements', 'verified', "TINYINT(1) NOT NULL DEFAULT 0");
    app_add_column_if_missing($pdo, 'document_requirements', 'api_validation_status', "VARCHAR(30) DEFAULT 'pending'");
    app_add_column_if_missing($pdo, 'document_requirements', 'api_validation_response', "TEXT NULL");
    app_add_column_if_missing($pdo, 'document_requirements', 'api_validation_timestamp', "TIMESTAMP NULL DEFAULT NULL");
    app_add_column_if_missing($pdo, 'document_requirements', 'retry_count', "INT NOT NULL DEFAULT 0");
    app_add_column_if_missing($pdo, 'document_requirements', 'last_retry_at', "TIMESTAMP NULL DEFAULT NULL");
    app_ensure_primary_auto_increment($pdo, 'document_requirements');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS document_files (
            id INT AUTO_INCREMENT PRIMARY KEY,
            requirement_id INT NULL,
            user_id INT NOT NULL,
            document_type ENUM('nin','bvn','land_title','id_card','farm_photo') NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            original_name VARCHAR(255) NULL,
            mime_type VARCHAR(120) NULL,
            file_size INT NULL,
            uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_document_files_requirement (requirement_id),
            INDEX idx_document_files_user_type (user_id, document_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'document_files');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS wallets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL UNIQUE,
            balance DECIMAL(12,2) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'wallets');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS wallet_transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            wallet_id INT NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            type VARCHAR(20) NOT NULL,
            description VARCHAR(255) NULL,
            reference VARCHAR(100) NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'pending',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_wallet_transactions_wallet (wallet_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'wallet_transactions');
}

function current_user(PDO $pdo): ?array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $fields = ['id', 'name', 'email', 'role', 'profile_picture'];
    foreach (['platform_role', 'account_status', 'is_super_admin'] as $optionalField) {
        if (app_column_exists($pdo, 'users', $optionalField)) {
            $fields[] = $optionalField;
        }
    }
    $stmt = $pdo->prepare("SELECT " . implode(', ', $fields) . " FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([(int) $_SESSION['user_id']]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function require_user_role(PDO $pdo, array $roles): array
{
    $user = current_user($pdo);
    if (!$user || !in_array($user['role'], $roles, true)) {
        http_response_code(403);
        exit('Forbidden');
    }
    return $user;
}

function admin_session_is_authenticated(PDO $pdo): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (($_SESSION['admin_authenticated'] ?? false) === true || ($_SESSION['admin'] ?? false) === true) {
        return true;
    }
    if (($_SESSION['super_admin_authenticated'] ?? false) === true) {
        return true;
    }

    $user = current_user($pdo);
    if (!$user) {
        return false;
    }
    if (($user['role'] ?? '') === 'admin') {
        return true;
    }
    if (function_exists('admin_user_has_admin_access')) {
        return admin_user_has_admin_access($pdo, (int) $user['id']);
    }
    return false;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function redirect_to(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function verify_csrf(?string $token): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    return is_string($token) && hash_equals($_SESSION['_csrf'] ?? '', $token);
}

function csrf_token_from_request(): ?string
{
    $header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_SERVER['HTTP_X_CSRF'] ?? '';
    if (is_string($header) && $header !== '') {
        return $header;
    }
    if (isset($_POST['_csrf'])) {
        return (string) $_POST['_csrf'];
    }
    return null;
}

function require_csrf_request(?string $message = null): void
{
    if (!verify_csrf(csrf_token_from_request())) {
        http_response_code(403);
        exit($message ?: 'Invalid security token.');
    }
}

function require_csrf_json(): void
{
    if (!verify_csrf(csrf_token_from_request())) {
        json_response(['success' => false, 'error' => 'Invalid request token'], 403);
    }
}

function app_send_mail(string $to, string $subject, string $plainText, ?string $html = null): bool
{
    $fromEmail = app_env('MAIL_FROM_ADDRESS', 'noreply@coconutventurehub.ng');
    $fromName = app_env('MAIL_FROM_NAME', 'NATCODEV');
    $replyTo = app_env('MAIL_REPLY_TO', 'info@coconutventurehub.ng');
    $transport = strtolower((string) app_env('MAIL_TRANSPORT', app_is_production() ? 'mail' : 'log'));

    if ($transport === 'log') {
        $logPath = __DIR__ . '/mail.log';
        $entry = [
            'sent_at' => date('c'),
            'to' => $to,
            'subject' => $subject,
            'plain' => $plainText,
            'html' => $html,
        ];
        file_put_contents($logPath, json_encode($entry, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
        app_log_notification('email', $to, $subject, $plainText, 'logged', $transport, 'Written to ' . $logPath);
        return true;
    }

    if ($html === null) {
        $headers = "From: {$fromName} <{$fromEmail}>\r\n";
        $headers .= "Reply-To: {$replyTo}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8";
        $sent = mail($to, $subject, $plainText, $headers);
        app_log_notification('email', $to, $subject, $plainText, $sent ? 'sent' : 'failed', $transport, $sent ? 'mail() accepted message' : null, $sent ? null : 'mail() returned false');
        return $sent;
    }

    $boundary = 'natcodev_' . bin2hex(random_bytes(12));
    $headers = "From: {$fromName} <{$fromEmail}>\r\n";
    $headers .= "Reply-To: {$replyTo}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";

    $message = "--{$boundary}\r\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
    $message .= trim($plainText) . "\r\n\r\n";
    $message .= "--{$boundary}\r\n";
    $message .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
    $message .= $html . "\r\n\r\n";
    $message .= "--{$boundary}--";

    $sent = mail($to, $subject, $message, $headers);
    app_log_notification('email', $to, $subject, $plainText, $sent ? 'sent' : 'failed', $transport, $sent ? 'mail() accepted message' : null, $sent ? null : 'mail() returned false');
    return $sent;
}

function app_log_notification(
    string $channel,
    string $recipient,
    ?string $subject,
    string $message,
    string $status,
    ?string $transport = null,
    ?string $providerResponse = null,
    ?string $errorMessage = null,
    ?string $context = null
): void {
    try {
        $pdo = db();
        if (!app_table_exists($pdo, 'notification_logs')) {
            return;
        }
        $preview = mb_substr(trim(preg_replace('/\s+/', ' ', $message) ?? $message), 0, 600);
        $stmt = $pdo->prepare("
            INSERT INTO notification_logs
                (channel, recipient, subject, message_preview, status, transport, provider_response, error_message, context)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$channel, $recipient, $subject, $preview, $status, $transport, $providerResponse, $errorMessage, $context]);
    } catch (Throwable $e) {
        error_log('Notification audit log failed: ' . $e->getMessage());
    }
}

function app_csv_value(mixed $value): string
{
    if ($value === null) {
        return '';
    }

    if (is_bool($value)) {
        return $value ? '1' : '0';
    }

    $text = (string) $value;
    $text = str_replace(["\r\n", "\r"], "\n", $text);

    if ($text !== '' && in_array($text[0], ['=', '+', '-', '@'], true)) {
        return "'" . $text;
    }

    return $text;
}

function app_export_csv(string $filename, array $headers, iterable $rows): void
{
    if (!str_ends_with(strtolower($filename), '.csv')) {
        $filename .= '.csv';
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', basename($filename)) . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    $out = fopen('php://output', 'w');
    if (!$out) {
        exit;
    }

    fwrite($out, "\xEF\xBB\xBF");
    if ($headers) {
        $headers[0] = preg_match('/^id\b/i', (string) $headers[0]) ? 'Record ' . (string) $headers[0] : (string) $headers[0];
        fputcsv($out, array_map('app_csv_value', $headers));
    }
    foreach ($rows as $row) {
        if ($row instanceof Traversable) {
            $row = iterator_to_array($row);
        }
        if (!is_array($row)) {
            $row = [$row];
        }
        fputcsv($out, array_map('app_csv_value', array_values($row)));
    }
    fclose($out);
    exit;
}

function app_csv_import_rows(string $path, int $maxRows = 20000): array
{
    $handle = fopen($path, 'rb');
    if (!$handle) {
        throw new RuntimeException('Unable to open CSV file.');
    }

    $sample = (string) fgets($handle);
    $delimiters = [',', ';', "\t"];
    $delimiter = ',';
    $bestCount = 0;
    foreach ($delimiters as $candidate) {
        $count = count(str_getcsv($sample, $candidate));
        if ($count > $bestCount) {
            $bestCount = $count;
            $delimiter = $candidate;
        }
    }
    rewind($handle);

    $rows = [];
    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
        if (count($rows) >= $maxRows) {
            fclose($handle);
            throw new RuntimeException('CSV exceeds the maximum allowed row count of ' . $maxRows . '.');
        }
        if ($rows === [] && isset($row[0])) {
            $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $row[0]) ?? (string) $row[0];
        }
        $rows[] = array_map(static fn($value): string => trim(str_replace("\0", '', (string) $value)), $row);
    }
    fclose($handle);
    return $rows;
}

function app_uploaded_file_info(array $file, array $allowedExtensions, int $maxBytes, string $label = 'File', array $allowedMimes = []): array
{
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'exceeds the server upload limit.',
            UPLOAD_ERR_FORM_SIZE => 'exceeds the form upload limit.',
            UPLOAD_ERR_PARTIAL => 'was only partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'was not selected.',
            UPLOAD_ERR_NO_TMP_DIR => 'cannot be uploaded because the temporary folder is missing.',
            UPLOAD_ERR_CANT_WRITE => 'could not be written to disk.',
            UPLOAD_ERR_EXTENSION => 'was blocked by a server extension.',
        ];
        throw new RuntimeException($label . ' ' . ($messages[$error] ?? 'could not be uploaded.'));
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new RuntimeException($label . ' upload is invalid.');
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0) {
        throw new RuntimeException($label . ' is empty.');
    }
    if ($size > $maxBytes) {
        throw new RuntimeException($label . ' exceeds the allowed file size.');
    }

    $original = (string) ($file['name'] ?? 'upload');
    $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    $allowed = array_map('strtolower', $allowedExtensions);
    if (!in_array($extension, $allowed, true)) {
        throw new RuntimeException($label . ' has an unsupported file type.');
    }

    $detectedMime = '';
    if ($allowedMimes !== []) {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $detectedMime = (string) finfo_file($finfo, $tmp);
                finfo_close($finfo);
            }
        }
        if ($detectedMime === '' && function_exists('mime_content_type')) {
            $detectedMime = (string) mime_content_type($tmp);
        }

        $allowedMimeMap = array_fill_keys(array_map('strtolower', $allowedMimes), true);
        if ($extension === 'pdf') {
            $handle = fopen($tmp, 'rb');
            $signature = $handle ? (string) fread($handle, 4) : '';
            if ($handle) {
                fclose($handle);
            }
            if ($signature !== '%PDF') {
                throw new RuntimeException($label . ' is not a valid PDF file.');
            }
        } elseif ($detectedMime !== '' && !isset($allowedMimeMap[strtolower($detectedMime)])) {
            throw new RuntimeException($label . ' content does not match the selected file type.');
        } elseif (in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $imageInfo = @getimagesize($tmp);
            if ($imageInfo === false) {
                throw new RuntimeException($label . ' is not a valid image file.');
            }
        }
    }

    return [
        'tmp_name' => $tmp,
        'name' => $original,
        'extension' => $extension,
        'size' => $size,
        'type' => $detectedMime !== '' ? $detectedMime : (string) ($file['type'] ?? ''),
    ];
}

function app_safe_upload_name(string $prefix, string $originalName, string $extension): string
{
    $prefix = preg_replace('/[^a-z0-9_-]/i', '_', $prefix) ?: 'upload';
    $base = pathinfo($originalName, PATHINFO_FILENAME);
    $base = preg_replace('/[^a-z0-9._-]/i', '_', $base) ?: 'file';
    $base = trim($base, '._-');
    return $prefix . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(5)) . '_' . substr($base, 0, 80) . '.' . strtolower($extension);
}

function generate_application_ref(): string
{
    return 'NAT-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}
