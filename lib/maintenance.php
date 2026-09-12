<?php

declare(strict_types=1);

if (!function_exists('app_schema_flag_is_set')) {
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
}

if (!function_exists('app_schema_flag_set')) {
    function app_schema_flag_set(PDO $pdo, string $key, string $version): void
    {
        try {
            $stmt = $pdo->prepare("INSERT INTO app_schema_flags (flag_key, flag_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE flag_value = VALUES(flag_value)");
            $stmt->execute([$key, $version]);
        } catch (Throwable $e) {
            error_log("Unable to set schema flag {$key}: " . $e->getMessage());
        }
    }
}

if (!function_exists('app_ensure_core_schema')) {
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
}

/**
 * Basic database-backed rate limiting.
 * Returns true if the action is allowed, false if blocked.
 */
if (!function_exists('app_check_rate_limit')) {
    function app_check_rate_limit(string $action, int $maxAttempts, int $decaySeconds): bool
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = $action . ':' . $ip;
        $now = time();
        $pdo = db();

        try {
            // Probabilistic cleanup of expired entries (1 in 200 requests) to keep table fast & compact under high concurrency
            if (mt_rand(1, 200) === 1) {
                $pdo->prepare("DELETE FROM app_rate_limits WHERE expires_at < ?")->execute([$now]);
            }

            $stmt = $pdo->prepare("SELECT attempts, last_attempt_at, expires_at FROM app_rate_limits WHERE limit_key = ? LIMIT 1");
            $stmt->execute([$key]);
            $record = $stmt->fetch();

            if (!$record || (int)$record['expires_at'] <= $now) {
                $stmt = $pdo->prepare("
                    INSERT INTO app_rate_limits (limit_key, attempts, last_attempt_at, expires_at)
                    VALUES (?, 1, ?, ?)
                    ON DUPLICATE KEY UPDATE attempts = 1, last_attempt_at = VALUES(last_attempt_at), expires_at = VALUES(expires_at)
                ");
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
}

if (!function_exists('app_table_exists')) {
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
}

if (!function_exists('app_column_exists')) {
    function app_column_exists(PDO $pdo, string $table, string $column): bool
    {
        static $cache = [];
        $key = $table . '.' . $column;
        if (array_key_exists($key, $cache) && $cache[$key] === true) {
            return true;
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
}

if (!function_exists('app_add_column_if_missing')) {
    function app_add_column_if_missing(PDO $pdo, string $table, string $column, string $definition): void
    {
        if (!app_column_exists($pdo, $table, $column)) {
            try {
                $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
            } catch (Throwable $e) {
                if (stripos($e->getMessage(), 'Duplicate column') === false && stripos($e->getMessage(), '1060') === false) {
                    throw $e;
                }
            }
        }
    }
}

if (!function_exists('app_column_extra')) {
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
}

if (!function_exists('app_primary_key_columns')) {
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
}

if (!function_exists('app_ensure_primary_auto_increment')) {
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
}

if (!function_exists('app_resequence_id_column')) {
    function app_resequence_id_column(PDO $pdo, string $table): void
    {
        $quotedTable = '`' . str_replace('`', '``', $table) . '`';
        $pdo->exec('SET @natcodev_row_number := 0');
        $pdo->exec("UPDATE {$quotedTable} SET `id` = (@natcodev_row_number := @natcodev_row_number + 1) ORDER BY `id`");
    }
}

if (!function_exists('app_ensure_certificate_schema')) {
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
}

if (!function_exists('app_ensure_farmer_engagement_schema')) {
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
}

if (!function_exists('app_ensure_user_verification_schema')) {
    function app_ensure_user_verification_schema(PDO $pdo): void
    {
        app_add_column_if_missing($pdo, 'users', 'account_status', "VARCHAR(40) NOT NULL DEFAULT 'active'");
        app_add_column_if_missing($pdo, 'users', 'email_verified_at', 'DATETIME NULL');
        app_add_column_if_missing($pdo, 'users', 'email_verification_token', 'VARCHAR(64) NULL');
        app_add_column_if_missing($pdo, 'users', 'email_verification_sent_at', 'DATETIME NULL');
    }
}

if (!function_exists('app_user_needs_email_verification')) {
    function app_user_needs_email_verification(array $user): bool
    {
        $status = strtolower(trim((string) ($user['account_status'] ?? 'active')));
        if (in_array($status, ['pending', 'unconfirmed', 'inactive', 'needs_confirmation'], true)) {
            return true;
        }

        return trim((string) ($user['email_verified_at'] ?? '')) === '';
    }
}

if (!function_exists('app_send_user_verification')) {
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
}
