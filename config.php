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

function app_ensure_core_schema(PDO $pdo): void
{
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
}

function app_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
    ");
    $stmt->execute([$table]);
    return (int) $stmt->fetchColumn() > 0;
}

function app_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
    ");
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function app_add_column_if_missing(PDO $pdo, string $table, string $column, string $definition): void
{
    if (!app_column_exists($pdo, $table, $column)) {
        $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
    }
}

function app_column_extra(PDO $pdo, string $table, string $column): string
{
    $stmt = $pdo->prepare("
        SELECT EXTRA
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
        LIMIT 1
    ");
    $stmt->execute([$table, $column]);
    return strtolower((string) $stmt->fetchColumn());
}

function app_primary_key_columns(PDO $pdo, string $table): array
{
    $stmt = $pdo->prepare("
        SELECT COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND CONSTRAINT_NAME = 'PRIMARY'
        ORDER BY ORDINAL_POSITION
    ");
    $stmt->execute([$table]);
    return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function app_ensure_primary_auto_increment(PDO $pdo, string $table): void
{
    if (!app_column_exists($pdo, $table, 'id')) {
        return;
    }

    $primary = app_primary_key_columns($pdo, $table);
    if ($primary === []) {
        app_resequence_id_column($pdo, $table);
        $pdo->exec("ALTER TABLE `{$table}` ADD PRIMARY KEY (`id`)");
    }

    if (!str_contains(app_column_extra($pdo, $table, 'id'), 'auto_increment')) {
        $pdo->exec("ALTER TABLE `{$table}` MODIFY `id` INT NOT NULL AUTO_INCREMENT");
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
    app_add_column_if_missing($pdo, 'certificates', 'verified_at', "DATETIME NULL");
    app_add_column_if_missing($pdo, 'certificates', 'qr_code_hash', "VARCHAR(64) NULL");
    app_add_column_if_missing($pdo, 'certificates', 'verification_url', "VARCHAR(255) NULL");
    app_add_column_if_missing($pdo, 'certificates', 'revoked_at', "DATETIME NULL");
    app_add_column_if_missing($pdo, 'certificates', 'revoked_reason', "TEXT NULL");
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

    $user = current_user($pdo);
    return $user && ($user['role'] ?? '') === 'admin';
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

function generate_application_ref(): string
{
    return 'NAT-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}
