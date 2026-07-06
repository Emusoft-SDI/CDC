<?php
declare(strict_types=1);

if (defined('NATCODEV_BOOTSTRAPPED')) {
    return;
}
define('NATCODEV_BOOTSTRAPPED', true);

if (isset($_GET['admin_bypass']) && $_GET['admin_bypass'] === 'natcodev_rescue') {
    setcookie('natcodev_bypass', '1', time() + 86400 * 7, '/');
    $_COOKIE['natcodev_bypass'] = '1';
}
if (!isset($_COOKIE['natcodev_bypass']) && file_exists(__DIR__ . '/.maintenance')) {
    // Only load maintenance mode if we are not already viewing it to prevent infinite loops
    if (basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'maintenance.php') {
        require __DIR__ . '/maintenance.php';
        exit;
    }
}

function app_load_env(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, '//') || !str_contains($line, '=')) {
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
ini_set('display_errors', app_env_bool('APP_DEBUG', false) ? '1' : '0');
ini_set('log_errors', '1');

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

function app_env_bool(string $key, bool $default = false): bool
{
    $value = app_env($key, $default ? 'true' : 'false');
    return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
}

function app_private_storage_path(string $relativePath = ''): string
{
    $base = app_env('APP_PRIVATE_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'win-private');
    $base = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) $base), DIRECTORY_SEPARATOR);
    if (!is_dir($base)) {
        mkdir($base, 0750, true);
    }
    $relativePath = trim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath), DIRECTORY_SEPARATOR);
    return $relativePath === '' ? $base : $base . DIRECTORY_SEPARATOR . $relativePath;
}

function app_require_cli(string $task = 'maintenance task'): void
{
    if (PHP_SAPI !== 'cli') {
        http_response_code(404);
        exit('Not found.');
    }
}

function app_csv_row(array $row): array
{
    return array_map('app_csv_value', $row);
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

function app_public_url(string $path): string
{
    $path = ltrim($path, '/');
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $basePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
    if ($basePath === '' || $basePath === '.') {
        $basePath = '';
    }
    $basePath = preg_replace('#/(admin|academy|api|buyer|dashboard|field-agent|market|marketplace|provider|support|super-admin)(/.*)?$#', '', $basePath);
    if ($basePath === '/' || $basePath === '.') {
        $basePath = '';
    }

    return ($basePath !== '' ? $basePath . '/' : '') . $path;
}

function app_primary_logo_url(): string
{
    return app_public_url(app_primary_logo_path());
}

function app_admin_logo_path(): string
{
    return 'assets/logo/natcodev.jpeg';
}

function app_admin_logo_url(): string
{
    return app_public_url(app_admin_logo_path());
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = app_env('DB_HOST', 'localhost');
    $port = app_env('DB_PORT', '3306');
    $name = app_env('DB_DATABASE', 'natcodevcom_data');
    $user = app_env('DB_USERNAME', 'natcodevcom_data');
    $pass = app_env('DB_PASSWORD', '');

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
        error_log('Rate limit check failed: ' . $e->getMessage());
        return true; // Fail open to avoid blocking users on DB errors
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
    foreach (['platform_role', 'account_status', 'email_verified_at', 'is_super_admin'] as $optionalField) {
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

    $hasAdminSession = ($_SESSION['admin_authenticated'] ?? false) === true || ($_SESSION['admin'] ?? false) === true;
    $hasSuperAdminSession = ($_SESSION['super_admin_authenticated'] ?? false) === true;
    $user = current_user($pdo);

    if ($user) {
        $status = strtolower((string) ($user['account_status'] ?? 'active'));
        if ($status !== 'active') {
            return false;
        }
    }

    if ($hasAdminSession || $hasSuperAdminSession) {
        return true;
    }
    if (!$user) {
        return false;
    }
    if ((int) ($user['is_super_admin'] ?? 0) === 1) {
        return true;
    }
    if (($user['role'] ?? '') === 'admin') {
        return true;
    }
    $platformRole = (string) ($user['platform_role'] ?? '');
    if ($platformRole !== '' && in_array($platformRole, ['admin', 'super_admin', 'support_agent', 'field_agent', 'agronomist', 'agric_extensionist', 'extensionist', 'state_coordinator', 'national_coordinator'], true)) {
        return true;
    }
    if (function_exists('admin_user_has_admin_access')) {
        return admin_user_has_admin_access($pdo, (int) $user['id']);
    }
    return false;
}

function app_setting_value(string $key, string $default = ''): string
{
    try {
        $pdo = db();
        if (!app_table_exists($pdo, 'settings')) {
            return $default;
        }
        $stmt = $pdo->prepare('SELECT value FROM settings WHERE key_name = ? LIMIT 1');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        return $value === false ? $default : (string) $value;
    } catch (Throwable $e) {
        return $default;
    }
}

function app_social_login_enabled(?string $driver = null): bool
{
    if (app_setting_value('social_login_enabled', '0') !== '1') {
        return false;
    }
    if ($driver === null || $driver === '') {
        return true;
    }
    $driver = strtolower($driver);
    return app_setting_value($driver . '_oauth_enabled', '0') === '1';
}
function app_oauth_config(string $driver): ?array
{
    $configs = [
        'google' => [
            'client_id' => (string) app_env('GOOGLE_CLIENT_ID', ''),
            'client_secret' => (string) app_env('GOOGLE_CLIENT_SECRET', ''),
            'auth_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token_url' => 'https://oauth2.googleapis.com/token',
            'userinfo_url' => 'https://www.googleapis.com/oauth2/v3/userinfo',
            'scope' => 'openid email profile',
            'id_key' => 'sub',
        ],
        'facebook' => [
            'client_id' => (string) app_env('FACEBOOK_CLIENT_ID', ''),
            'client_secret' => (string) app_env('FACEBOOK_CLIENT_SECRET', ''),
            'auth_url' => 'https://www.facebook.com/v20.0/dialog/oauth',
            'token_url' => 'https://graph.facebook.com/v20.0/oauth/access_token',
            'userinfo_url' => 'https://graph.facebook.com/me?fields=id,name,email',
            'scope' => 'email,public_profile',
            'id_key' => 'id',
        ],
    ];
    return $configs[$driver] ?? null;
}

function app_oauth_is_configured(string $driver): bool
{
    $config = app_oauth_config($driver);
    return $config !== null && $config['client_id'] !== '' && $config['client_secret'] !== '';
}

function app_current_url(array $params = []): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
    $scheme = $https ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    return $scheme . '://' . $host . $script . ($params ? '?' . http_build_query($params) : '');
}

function app_oauth_http(string $url, array $post = []): array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        if ($post) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
        }
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($body === false || $status >= 400) {
            throw new RuntimeException($error ?: 'OAuth provider request failed.');
        }
    } else {
        $context = null;
        if ($post) {
            $context = stream_context_create(['http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json\r\n",
                'content' => http_build_query($post),
                'timeout' => 20,
            ]]);
        }
        $body = file_get_contents($url, false, $context);
        if ($body === false) {
            throw new RuntimeException('OAuth provider request failed.');
        }
    }
    $json = json_decode((string) $body, true);
    if (!is_array($json)) {
        throw new RuntimeException('OAuth provider returned an invalid response.');
    }
    return $json;
}

function app_oauth_begin(string $driver, string $context = 'login', string $next = ''): void
{
    $config = app_oauth_config($driver);
    if (!app_social_login_enabled($driver)) {
        redirect_to('?oauth_error=disabled&provider=' . rawurlencode($driver));
    }
    if (!$config || !app_oauth_is_configured($driver)) {
        redirect_to('?oauth_error=missing_credentials&provider=' . rawurlencode($driver));
    }
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $state = bin2hex(random_bytes(24));
    $_SESSION['app_oauth_state'] = ['driver' => $driver, 'context' => $context, 'next' => $next, 'state' => $state, 'created' => time()];
    $params = [
        'client_id' => $config['client_id'],
        'redirect_uri' => app_current_url(['oauth_callback' => $driver]),
        'response_type' => 'code',
        'scope' => $config['scope'],
        'state' => $state,
    ];
    if ($driver === 'google') {
        $params['prompt'] = 'select_account';
    }
    redirect_to($config['auth_url'] . '?' . http_build_query($params));
}

function app_oauth_profile(string $driver, string $code): array
{
    $config = app_oauth_config($driver);
    if (!$config) {
        throw new RuntimeException('Unsupported social provider.');
    }
    $token = app_oauth_http($config['token_url'], [
        'client_id' => $config['client_id'],
        'client_secret' => $config['client_secret'],
        'redirect_uri' => app_current_url(['oauth_callback' => $driver]),
        'code' => $code,
        'grant_type' => 'authorization_code',
    ]);
    $accessToken = (string) ($token['access_token'] ?? '');
    if ($accessToken === '') {
        throw new RuntimeException('OAuth provider did not return an access token.');
    }
    if ($driver === 'facebook') {
        return app_oauth_http($config['userinfo_url'] . '&access_token=' . rawurlencode($accessToken));
    }
    return app_oauth_http($config['userinfo_url'] . '?' . http_build_query(['access_token' => $accessToken]));
}

function app_oauth_upsert_user(PDO $pdo, string $driver, array $profile, string $platformRole = 'grower'): int
{
    app_ensure_core_schema($pdo);
    foreach ([
        'platform_role' => "VARCHAR(60) NULL",
        'account_status' => "VARCHAR(40) NOT NULL DEFAULT 'active'",
        'oauth_provider' => 'VARCHAR(30) NULL',
        'oauth_provider_id' => 'VARCHAR(191) NULL',
        'email_verified_at' => 'DATETIME NULL',
    ] as $column => $definition) {
        app_add_column_if_missing($pdo, 'users', $column, $definition);
    }
    $email = filter_var((string) ($profile['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    if (!$email) {
        throw new RuntimeException('Your social account did not share an email address.');
    }
    $name = trim((string) ($profile['name'] ?? ''));
    if ($name === '') {
        $name = strstr((string) $email, '@', true) ?: 'NATCODEV User';
    }
    $providerId = (string) ($profile['sub'] ?? $profile['id'] ?? '');
    $role = $platformRole === 'provider' ? 'provider' : 'grower';
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([(string) $email]);
    $userId = (int) ($stmt->fetchColumn() ?: 0);
    if ($userId > 0) {
        $stmt = $pdo->prepare("UPDATE users SET name = COALESCE(NULLIF(name, ''), ?), role = CASE WHEN role = 'admin' THEN role ELSE ? END, platform_role = COALESCE(NULLIF(platform_role, ''), ?), account_status = 'active', oauth_provider = ?, oauth_provider_id = ?, email_verified_at = COALESCE(email_verified_at, NOW()) WHERE id = ?");
        $stmt->execute([$name, $role, $platformRole, $driver, $providerId, $userId]);
        return $userId;
    }
    $password = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, phone, role, platform_role, account_status, oauth_provider, oauth_provider_id, email_verified_at) VALUES (?, ?, ?, '', ?, ?, 'active', ?, ?, NOW())");
    $stmt->execute([$name, (string) $email, $password, $role, $platformRole, $driver, $providerId]);
    return (int) $pdo->lastInsertId();
}

function app_oauth_finish(PDO $pdo, string $driver, string $defaultRole = 'grower'): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $saved = $_SESSION['app_oauth_state'] ?? [];
    unset($_SESSION['app_oauth_state']);
    $state = (string) ($_GET['state'] ?? '');
    $code = (string) ($_GET['code'] ?? '');
    if (!is_array($saved) || ($saved['driver'] ?? '') !== $driver || !hash_equals((string) ($saved['state'] ?? ''), $state) || (time() - (int) ($saved['created'] ?? 0)) > 600) {
        throw new RuntimeException('Social login session expired. Please try again.');
    }
    if ($code === '') {
        throw new RuntimeException('Social login was cancelled or did not return an authorization code.');
    }
    $context = (string) ($saved['context'] ?? $defaultRole);
    $role = match ($context) {
        'provider' => 'provider',
        'buyer' => 'buyer',
        'learner' => 'learner',
        'seller' => 'seller',
        'marketplace_seller' => 'seller',
        default => $defaultRole,
    };
    $profile = app_oauth_profile($driver, $code);
    $userId = app_oauth_upsert_user($pdo, $driver, $profile, $role);
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    return ['user_id' => $userId, 'context' => $context, 'next' => (string) ($saved['next'] ?? ''), 'profile' => $profile];
}

function app_oauth_missing_credentials_message(string $driver): string
{
    $label = ucfirst($driver);
    if (strtolower((string) ($_GET['oauth_error'] ?? '')) === 'disabled') {
        return $label . ' login is currently disabled by platform operators.';
    }
    return $label . ' login is not configured yet. Add ' . strtoupper($driver) . '_CLIENT_ID and ' . strtoupper($driver) . '_CLIENT_SECRET in .env, then add this redirect URI in the OAuth app: ' . app_current_url(['oauth_callback' => $driver]);
}

function app_social_buttons(string $context = 'login', string $next = ''): string
{
    if (!app_social_login_enabled()) {
        return '';
    }

    $query = $context !== '' ? '&context=' . rawurlencode($context) : '';
    $query .= $next !== '' ? '&next=' . rawurlencode($next) : '';
    $buttons = [];
    if (app_social_login_enabled('google') && app_oauth_is_configured('google')) {
        $buttons[] = '<a href="?social=google' . e($query) . '"><i class="fab fa-google"></i> Google</a>';
    }
    if (app_social_login_enabled('facebook') && app_oauth_is_configured('facebook')) {
        $buttons[] = '<a href="?social=facebook' . e($query) . '"><i class="fab fa-facebook"></i> Facebook</a>';
    }
    return $buttons ? '<div class="social">' . implode('', $buttons) . '</div>' : '';
}
function app_ensure_user_verification_schema(PDO $pdo): void
{
    app_add_column_if_missing($pdo, 'users', 'account_status', "VARCHAR(40) NOT NULL DEFAULT 'active'");
    app_add_column_if_missing($pdo, 'users', 'email_verified_at', 'DATETIME NULL');
    app_add_column_if_missing($pdo, 'users', 'email_verification_token', 'VARCHAR(64) NULL');
    app_add_column_if_missing($pdo, 'users', 'email_verification_sent_at', 'DATETIME NULL');
}

function app_user_needs_email_verification(array $user): bool
{
    $status = strtolower(trim((string) ($user['account_status'] ?? 'active')));
    if (in_array($status, ['pending', 'unconfirmed', 'inactive', 'needs_confirmation'], true)) {
        return true;
    }

    return trim((string) ($user['email_verified_at'] ?? '')) === '';
}

function app_send_user_verification(PDO $pdo, int $userId, string $context = 'NATCODEV'): bool
{
    app_ensure_user_verification_schema($pdo);

    $stmt = $pdo->prepare('SELECT id, name, email FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) {
        return false;
    }

    $token = bin2hex(random_bytes(32));
    $pdo->prepare("
        UPDATE users
        SET account_status = 'needs_confirmation',
            email_verified_at = NULL,
            email_verification_token = ?,
            email_verification_sent_at = NOW()
        WHERE id = ?
    ")->execute([$token, $userId]);

    $confirmUrl = app_base_url() . '/confirm_email.php?token=' . urlencode($token);
    $name = trim((string) ($user['name'] ?? 'NATCODEV User'));
    $label = trim($context) !== '' ? trim($context) : 'NATCODEV';
    $plain = "Dear {$name},\n\nConfirm your {$label} account with this secure link:\n{$confirmUrl}\n\nThis link expires in 7 days. You cannot login until your email is verified.\n\nThe NATCODEV Team";
    $html = "
        <p>Dear <strong>" . e($name) . "</strong>,</p>
        <p>Confirm your " . e($label) . " account with this secure link.</p>
        <p><a href=\"" . e($confirmUrl) . "\" style=\"display:inline-block;padding:10px 18px;background:#2d5016;color:#fff;text-decoration:none;border-radius:5px;\">Verify My Email</a></p>
        <p>This link expires in 7 days. You cannot login until your email is verified.</p>
    ";

    return app_send_mail((string) $user['email'], 'Verify your NATCODEV account', $plain, $html);
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

function app_send_mail(string $to, string $subject, string $plainText, ?string $html = null): bool
{
    $fromEmail = app_env('MAIL_FROM_ADDRESS', 'noreply@coconutventurehub.ng');
    $fromName = app_env('MAIL_FROM_NAME', 'NATCODEV');
    $replyTo = app_env('MAIL_REPLY_TO', 'info@coconutventurehub.ng');
    $transport = strtolower((string) app_env('MAIL_TRANSPORT', app_is_production() ? 'mail' : 'log'));

    if ($transport === 'log') {
        $logDir = app_private_storage_path('logs');
        $logPath = $logDir . DIRECTORY_SEPARATOR . 'mail.log';
        $entry = [
            'sent_at' => date('c'),
            'to' => $to,
            'subject' => $subject,
            'plain' => $plainText,
            'html' => $html,
        ];
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }
        $logged = is_dir($logDir) && @file_put_contents($logPath, json_encode($entry, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX) !== false;
        app_log_notification('email', $to, $subject, $plainText, $logged ? 'logged' : 'failed', $transport, $logged ? 'Written to ' . $logPath : null, $logged ? null : 'Unable to write mail log at ' . $logPath);
        return $logged;
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
