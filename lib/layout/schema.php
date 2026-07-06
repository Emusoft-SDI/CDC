<?php

function admin_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done || app_schema_flag_is_set($pdo, 'admin_schema_ready', '20260606-fast')) {
        admin_ensure_action_request_schema($pdo);
        $done = true;
        return;
    }

    try {
        $existing = $pdo->query("
            SELECT COUNT(*)
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME IN ('settings','user_role_assignments','staff_profiles','recruitment_applications','resources')
        ")->fetchColumn();
        if ((int) $existing === 5) {
            app_schema_flag_set($pdo, 'admin_schema_ready', '20260606-fast');
            $done = true;
            return;
        }
    } catch (Throwable $e) {
        // Fall through to normal schema creation.
    }

    app_ensure_core_schema($pdo);
    app_ensure_farmer_engagement_schema($pdo);
    admin_ensure_user_role_assignments_schema($pdo);
    admin_ensure_action_request_schema($pdo);
    if (app_column_exists($pdo, 'users', 'application_id')) {
        try {
            $pdo->exec("ALTER TABLE users MODIFY application_id INT NULL");
        } catch (Throwable $e) {
            error_log('Unable to relax users.application_id for staff accounts: ' . $e->getMessage());
        }
    }
    app_add_column_if_missing($pdo, 'users', 'phone', "VARCHAR(30) NULL");
    app_add_column_if_missing($pdo, 'users', 'location', "VARCHAR(255) NULL");
    app_add_column_if_missing($pdo, 'users', 'platform_role', "VARCHAR(60) NULL");
    app_add_column_if_missing($pdo, 'users', 'is_agronomist', "TINYINT(1) NOT NULL DEFAULT 0");
    app_add_column_if_missing($pdo, 'users', 'is_extensionist', "TINYINT(1) NOT NULL DEFAULT 0");
    app_add_column_if_missing($pdo, 'users', 'agronomist_license', "VARCHAR(255) NULL");
    app_add_column_if_missing($pdo, 'users', 'staff_specialty', "VARCHAR(80) NULL");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS staff_profiles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL UNIQUE,
            staff_type VARCHAR(40) NOT NULL,
            state VARCHAR(120) NULL,
            lga VARCHAR(120) NULL,
            qualification VARCHAR(255) NULL,
            license_number VARCHAR(255) NULL,
            experience_years DECIMAL(5,2) NOT NULL DEFAULT 0,
            certification_status VARCHAR(40) NOT NULL DEFAULT 'not_started',
            training_program VARCHAR(120) NULL,
            availability VARCHAR(120) NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'active',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_staff_profiles_type (staff_type),
            INDEX idx_staff_profiles_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'staff_profiles');
    $pdo->exec("
        INSERT IGNORE INTO staff_profiles (user_id, staff_type, license_number, status)
        SELECT
            id,
            CASE
                WHEN is_agronomist = 1 THEN 'agronomist'
                WHEN is_extensionist = 1 THEN 'extensionist'
                WHEN role = 'admin' THEN 'admin'
                ELSE 'field_agent'
            END,
            agronomist_license,
            'active'
        FROM users
        WHERE role IN ('field_agent', 'admin')
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS recruitment_applications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            app_ref VARCHAR(60) NOT NULL UNIQUE,
            role_applied VARCHAR(40) NOT NULL,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            phone VARCHAR(40) NOT NULL,
            state VARCHAR(120) NULL,
            lga VARCHAR(120) NULL,
            qualification VARCHAR(255) NULL,
            license_number VARCHAR(255) NULL,
            experience_years DECIMAL(5,2) NOT NULL DEFAULT 0,
            availability VARCHAR(120) NULL,
            cover_note TEXT NULL,
            certification_interest TINYINT(1) NOT NULL DEFAULT 0,
            certification_program VARCHAR(120) NULL,
            cv_path VARCHAR(255) NULL,
            id_path VARCHAR(255) NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'pending',
            review_notes TEXT NULL,
            reviewed_by INT NULL,
            reviewed_at DATETIME NULL,
            user_id INT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_recruitment_status (status),
            INDEX idx_recruitment_role (role_applied)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    foreach ([
        'app_ref' => "VARCHAR(60) NOT NULL DEFAULT ''",
        'role_applied' => "VARCHAR(40) NOT NULL DEFAULT 'field_agent'",
        'name' => "VARCHAR(255) NOT NULL DEFAULT ''",
        'email' => "VARCHAR(255) NOT NULL DEFAULT ''",
        'phone' => "VARCHAR(40) NOT NULL DEFAULT ''",
        'status' => "VARCHAR(30) NOT NULL DEFAULT 'pending'",
        'certification_interest' => "TINYINT(1) NOT NULL DEFAULT 0",
        'certification_program' => "VARCHAR(120) NULL",
        'user_id' => "INT NULL",
    ] as $column => $definition) {
        app_add_column_if_missing($pdo, 'recruitment_applications', $column, $definition);
    }
    app_ensure_primary_auto_increment($pdo, 'recruitment_applications');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS resources (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT NULL,
            file_path VARCHAR(255) NOT NULL,
            category VARCHAR(80) NOT NULL DEFAULT 'Guides',
            offline_available TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_resources_category (category)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_add_column_if_missing($pdo, 'resources', 'title', "VARCHAR(255) NOT NULL DEFAULT ''");
    app_add_column_if_missing($pdo, 'resources', 'description', "TEXT NULL");
    app_add_column_if_missing($pdo, 'resources', 'file_path', "VARCHAR(255) NOT NULL DEFAULT ''");
    app_add_column_if_missing($pdo, 'resources', 'category', "VARCHAR(80) NOT NULL DEFAULT 'Guides'");
    app_add_column_if_missing($pdo, 'resources', 'offline_available', "TINYINT(1) NOT NULL DEFAULT 1");
    app_add_column_if_missing($pdo, 'resources', 'created_at', "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");
    app_ensure_primary_auto_increment($pdo, 'resources');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS marketplace_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            seller_id INT NOT NULL DEFAULT 0,
            title VARCHAR(255) NOT NULL,
            description TEXT NULL,
            price DECIMAL(12,2) NOT NULL DEFAULT 0,
            category VARCHAR(80) NOT NULL DEFAULT 'input',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_marketplace_active (is_active, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_add_column_if_missing($pdo, 'marketplace_items', 'seller_id', "INT NOT NULL DEFAULT 0");
    app_add_column_if_missing($pdo, 'marketplace_items', 'title', "VARCHAR(255) NOT NULL DEFAULT ''");
    app_add_column_if_missing($pdo, 'marketplace_items', 'description', "TEXT NULL");
    app_add_column_if_missing($pdo, 'marketplace_items', 'price', "DECIMAL(12,2) NOT NULL DEFAULT 0");
    app_add_column_if_missing($pdo, 'marketplace_items', 'category', "VARCHAR(80) NOT NULL DEFAULT 'input'");
    app_add_column_if_missing($pdo, 'marketplace_items', 'is_active', "TINYINT(1) NOT NULL DEFAULT 1");
    app_add_column_if_missing($pdo, 'marketplace_items', 'created_at', "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");
    app_ensure_primary_auto_increment($pdo, 'marketplace_items');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            key_name VARCHAR(120) NOT NULL UNIQUE,
            value TEXT NULL,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'settings');
    admin_ensure_settings_unique($pdo);

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notification_templates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            template_name VARCHAR(100) NOT NULL,
            template_type VARCHAR(40) NOT NULL,
            message_template TEXT NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_template_channel (template_name, template_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'notification_templates');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS audit_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            action VARCHAR(120) NOT NULL,
            description TEXT NULL,
            ip_address VARCHAR(45) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'audit_log');
    // add optional actor information to audit log
    app_add_column_if_missing($pdo, 'audit_log', 'actor_id', "INT NULL");
    app_add_column_if_missing($pdo, 'audit_log', 'actor_name', "VARCHAR(255) NULL");

    foreach ([
        'sms_phone_validation_required' => '0',
        'sms_validation_notifications' => '1',
        'sms_verification_timeout' => '300',
        'iot_module_enabled' => '0',
    ] as $key => $value) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO settings (key_name, value) VALUES (?, ?)");
        $stmt->execute([$key, $value]);
    }
    app_schema_flag_set($pdo, 'admin_schema_ready', '20260606-fast');
    $done = true;
}


function admin_ensure_user_role_assignments_schema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_role_assignments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            role_key VARCHAR(60) NOT NULL,
            scope_type VARCHAR(40) NOT NULL DEFAULT 'global',
            scope_value VARCHAR(160) NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'active',
            notes TEXT NULL,
            assigned_by INT NULL,
            assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            revoked_at DATETIME NULL,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_user_role_scope (user_id, role_key, scope_type, scope_value),
            INDEX idx_user_role_active (user_id, role_key, status),
            INDEX idx_user_role_scope (role_key, scope_type, scope_value, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'user_role_assignments');
}


function admin_ensure_action_request_schema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admin_action_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            request_type VARCHAR(40) NOT NULL DEFAULT 'delete',
            target_table VARCHAR(120) NOT NULL,
            target_id INT NULL,
            target_key VARCHAR(190) NULL,
            target_label VARCHAR(255) NULL,
            requested_by INT NULL,
            reason TEXT NULL,
            payload_json TEXT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'pending',
            reviewed_by INT NULL,
            reviewed_at DATETIME NULL,
            review_note TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_admin_action_status (status, request_type),
            INDEX idx_admin_action_target (target_table, target_id),
            INDEX idx_admin_action_requested_by (requested_by)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'admin_action_requests');
}


function admin_ensure_settings_unique(PDO $pdo): void
{
    if (!app_table_exists($pdo, 'settings') || !app_column_exists($pdo, 'settings', 'id') || !app_column_exists($pdo, 'settings', 'key_name')) {
        return;
    }

    $duplicates = $pdo->query("
        SELECT key_name, MAX(id) AS keep_id, COUNT(*) AS total
        FROM settings
        GROUP BY key_name
        HAVING COUNT(*) > 1
    ")->fetchAll();

    if ($duplicates) {
        $delete = $pdo->prepare("DELETE FROM settings WHERE key_name = ? AND id <> ?");
        foreach ($duplicates as $row) {
            $delete->execute([(string) $row['key_name'], (int) $row['keep_id']]);
        }
    }

    try {
        $pdo->exec("ALTER TABLE settings ADD UNIQUE KEY uniq_settings_key_name (key_name)");
    } catch (Throwable $e) {
        if (!str_contains($e->getMessage(), 'Duplicate key name')) {
            throw $e;
        }
    }
}

