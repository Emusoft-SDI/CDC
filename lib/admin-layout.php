<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

const ADMIN_ACCESS_CATALOG_VERSION = '20260606-super-delete-approval-1';

function admin_password_is_valid(string $password): bool
{
    $hash = app_env('ADMIN_PASSWORD_HASH');
    if ($hash) {
        return password_verify($password, $hash);
    }

    $plain = app_env('ADMIN_PASSWORD');
    return $plain !== null && $plain !== '' && hash_equals($plain, $password);
}

function admin_require(PDO $pdo): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (!admin_session_is_authenticated($pdo)) {
        redirect_to('login.php');
    }
    admin_require_feature($pdo, admin_feature_for_script());
}

function admin_logout(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
    }
    session_destroy();
    redirect_to('admin.php');
}

function admin_ensure_payments_table(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            reference VARCHAR(100) NOT NULL UNIQUE,
            status VARCHAR(50) DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'payments');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS bank_statement_imports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            filename VARCHAR(255) NOT NULL,
            bank_name VARCHAR(100) NOT NULL,
            upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            uploaded_by INT NOT NULL,
            status ENUM('pending', 'processed', 'failed') DEFAULT 'pending',
            total_records INT DEFAULT 0,
            matched_records INT DEFAULT 0,
            FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'bank_statement_imports');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS reconciliation_matches (
            id INT AUTO_INCREMENT PRIMARY KEY,
            import_id INT NOT NULL,
            transaction_id INT NULL,
            external_reference VARCHAR(100),
            amount DECIMAL(12,2) NOT NULL,
            match_status ENUM('matched', 'mismatch', 'unmatched') DEFAULT 'unmatched',
            match_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`import_id`) REFERENCES `bank_statement_imports`(`id`),
            FOREIGN KEY (`transaction_id`) REFERENCES `wallet_transactions`(`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_add_column_if_missing($pdo, 'marketplace_orders', 'payout_status', "VARCHAR(50) DEFAULT 'pending'");
}

function admin_ensure_schema(PDO $pdo): void
{
    admin_ensure_payments_table($pdo);
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

function admin_current_user_id(PDO $pdo): ?int
{
    $user = current_user($pdo);
    if ($user && (int) ($user['id'] ?? 0) > 0) {
        return (int) $user['id'];
    }
    return $_SESSION['super_admin_user_id'] ?? null;
}

function admin_current_user_is_super_admin(PDO $pdo): bool
{
    return admin_current_platform_role($pdo) === 'super_admin';
}

function admin_queue_delete_request(PDO $pdo, string $targetTable, ?int $targetId, string $targetLabel, string $reason = '', array $payload = []): int
{
    admin_ensure_action_request_schema($pdo);
    $targetTable = preg_replace('/[^a-zA-Z0-9_]/', '', $targetTable);
    if ($targetTable === '') {
        throw new RuntimeException('Delete request target is invalid.');
    }
    $targetKey = isset($payload['target_key']) ? trim((string) $payload['target_key']) : null;
    $stmt = $pdo->prepare("
        INSERT INTO admin_action_requests
            (request_type, target_table, target_id, target_key, target_label, requested_by, reason, payload_json)
        VALUES ('delete', ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $targetTable,
        $targetId,
        $targetKey !== '' ? $targetKey : null,
        $targetLabel,
        admin_current_user_id($pdo),
        $reason !== '' ? $reason : 'Admin requested delete approval.',
        $payload ? json_encode($payload, JSON_UNESCAPED_SLASHES) : null,
    ]);
    return (int) $pdo->lastInsertId();
}

function admin_record_authenticity_status(PDO $pdo, string $targetTable, ?int $targetId = null, ?string $targetKey = null): array
{
    $targetTable = preg_replace('/[^a-zA-Z0-9_]/', '', $targetTable);
    if ($targetTable === '' || !app_table_exists($pdo, $targetTable)) {
        return ['requires_approval' => false, 'status' => '', 'label' => ''];
    }

    $approvedStatuses = ['approved', 'verified', 'issued', 'confirmed', 'active', 'accredited', 'completed', 'valid'];
    $statusColumns = [
        'applications' => ['confirmed', 'review_status'],
        'document_requirements' => ['verification_status', 'verified'],
        'grower_farms' => [],
        'farm_verifications' => ['status'],
        'marketplace_sellers' => ['approval_status', 'verification_status'],
        'marketplace_listings' => ['approval_status'],
        'provider_registry' => ['status'],
        'staff_profiles' => ['status'],
        'certificates' => ['status'],
        'academy_certificates' => ['status'],
        'academy_enrollments' => ['status'],
        'provider_offerings' => ['status'],
    ][$targetTable] ?? [];

    if ($targetTable === 'grower_farms' && $targetId !== null && app_table_exists($pdo, 'farm_verifications')) {
        $stmt = $pdo->prepare("SELECT status FROM farm_verifications WHERE farm_id = ? ORDER BY verified_at DESC, id DESC LIMIT 1");
        $stmt->execute([$targetId]);
        $status = strtolower((string) ($stmt->fetchColumn() ?: ''));
        return ['requires_approval' => in_array($status, $approvedStatuses, true), 'status' => $status, 'label' => 'farm verification'];
    }

    if (!$statusColumns) {
        return ['requires_approval' => false, 'status' => '', 'label' => ''];
    }

    $where = '';
    $params = [];
    if ($targetId !== null && app_column_exists($pdo, $targetTable, 'id')) {
        $where = 'id = ?';
        $params[] = $targetId;
    } elseif ($targetKey !== null && $targetTable === 'notification_templates' && app_column_exists($pdo, $targetTable, 'template_name')) {
        $where = 'template_name = ?';
        $params[] = $targetKey;
    } else {
        return ['requires_approval' => false, 'status' => '', 'label' => ''];
    }

    $existingColumns = array_values(array_filter($statusColumns, static fn(string $column): bool => app_column_exists($pdo, $targetTable, $column)));
    if (!$existingColumns) {
        return ['requires_approval' => false, 'status' => '', 'label' => ''];
    }

    $stmt = $pdo->prepare('SELECT ' . implode(', ', $existingColumns) . " FROM {$targetTable} WHERE {$where} LIMIT 1");
    $stmt->execute($params);
    $row = $stmt->fetch();
    if (!$row) {
        return ['requires_approval' => false, 'status' => '', 'label' => ''];
    }

    foreach ($existingColumns as $column) {
        $value = strtolower(trim((string) ($row[$column] ?? '')));
        if ($column === 'confirmed' || $column === 'verified') {
            $isAuthentic = (int) ($row[$column] ?? 0) === 1;
            if ($isAuthentic) {
                return ['requires_approval' => true, 'status' => $column, 'label' => $column];
            }
            continue;
        }
        if (in_array($value, $approvedStatuses, true)) {
            return ['requires_approval' => true, 'status' => $value, 'label' => $column];
        }
    }

    return ['requires_approval' => false, 'status' => '', 'label' => ''];
}

function admin_verified_delete_requires_super_approval(PDO $pdo, string $targetTable, ?int $targetId = null, ?string $targetKey = null): bool
{
    return (bool) admin_record_authenticity_status($pdo, $targetTable, $targetId, $targetKey)['requires_approval'];
}

function admin_queue_verified_delete_request(PDO $pdo, string $targetTable, ?int $targetId, string $targetLabel, string $reason = '', array $payload = []): int
{
    $targetKey = isset($payload['target_key']) ? (string) $payload['target_key'] : null;
    $auth = admin_record_authenticity_status($pdo, $targetTable, $targetId, $targetKey);
    if ($auth['requires_approval']) {
        $reason = trim($reason . ' Authenticity lock: ' . $auth['label'] . '=' . $auth['status'] . '.');
        $payload['authenticity_lock'] = $auth;
    }
    return admin_queue_delete_request($pdo, $targetTable, $targetId, $targetLabel, $reason, $payload);
}

function admin_pending_delete_request_count(PDO $pdo): int
{
    admin_ensure_action_request_schema($pdo);
    $generic = (int) $pdo->query("SELECT COUNT(*) FROM admin_action_requests WHERE request_type = 'delete' AND status = 'pending'")->fetchColumn();
    if (!app_table_exists($pdo, 'application_delete_requests')) {
        return $generic;
    }
    $applications = (int) $pdo->query("SELECT COUNT(*) FROM application_delete_requests WHERE status = 'pending'")->fetchColumn();
    return $generic + $applications;
}

function admin_execute_approved_delete(PDO $pdo, array $request): void
{
    $table = (string) ($request['target_table'] ?? '');
    $id = (int) ($request['target_id'] ?? 0);
    $payload = json_decode((string) ($request['payload_json'] ?? ''), true);
    $payload = is_array($payload) ? $payload : [];

    if ($table === 'user_import_records') {
        $pdo->prepare('DELETE FROM user_import_records WHERE id = ?')->execute([$id]);
        return;
    }

    if ($table === 'notification_templates') {
        $templateName = (string) ($request['target_key'] ?? $payload['template_name'] ?? '');
        if ($templateName !== '') {
            $pdo->prepare('DELETE FROM notification_templates WHERE template_name = ?')->execute([$templateName]);
        }
        return;
    }

    if ($table === 'provider_offerings') {
        $pdo->prepare("UPDATE provider_offerings SET status = 'removed' WHERE id = ?")->execute([$id]);
        return;
    }

    if ($table === 'marketplace_listings') {
        $pdo->prepare('DELETE FROM marketplace_listings WHERE id = ?')->execute([$id]);
        return;
    }

    if ($table === 'marketplace_sellers') {
        $pdo->prepare('DELETE FROM marketplace_sellers WHERE id = ?')->execute([$id]);
        return;
    }

    if ($table === 'provider_registry') {
        $pdo->prepare('DELETE FROM provider_registry WHERE id = ?')->execute([$id]);
        return;
    }

    if ($table === 'staff_profiles') {
        $pdo->prepare('DELETE FROM staff_profiles WHERE id = ?')->execute([$id]);
        return;
    }

    if ($table === 'document_requirements') {
        $pdo->prepare('DELETE FROM document_requirements WHERE id = ?')->execute([$id]);
        return;
    }

    if ($table === 'grower_farms') {
        $pdo->prepare('DELETE FROM grower_farms WHERE id = ?')->execute([$id]);
        return;
    }

    if ($table === 'farm_verifications') {
        $pdo->prepare('DELETE FROM farm_verifications WHERE id = ?')->execute([$id]);
        return;
    }

    if ($table === 'certificates') {
        $pdo->prepare('DELETE FROM certificates WHERE id = ?')->execute([$id]);
        return;
    }

    if ($table === 'academy_certificates') {
        $pdo->prepare('DELETE FROM academy_certificates WHERE id = ?')->execute([$id]);
        return;
    }

    throw new RuntimeException('No approved delete handler is registered for ' . $table . '.');
}

function admin_review_action_request(PDO $pdo, int $requestId, string $decision, string $note = ''): void
{
    if (!admin_current_user_is_super_admin($pdo)) {
        http_response_code(403);
        exit('Forbidden: only Super Admin can review delete requests.');
    }
    admin_ensure_action_request_schema($pdo);
    $stmt = $pdo->prepare("SELECT * FROM admin_action_requests WHERE id = ? AND status = 'pending' LIMIT 1");
    $stmt->execute([$requestId]);
    $request = $stmt->fetch();
    if (!$request) {
        throw new RuntimeException('Delete request not found or already reviewed.');
    }

    $decision = $decision === 'approve' ? 'approved' : 'rejected';
    $pdo->beginTransaction();
    try {
        if ($decision === 'approved') {
            admin_execute_approved_delete($pdo, $request);
        }
        $pdo->prepare("
            UPDATE admin_action_requests
            SET status = ?, reviewed_by = ?, reviewed_at = NOW(), review_note = ?
            WHERE id = ?
        ")->execute([$decision, admin_current_user_id($pdo), $note !== '' ? $note : null, $requestId]);
        if (app_table_exists($pdo, 'audit_log')) {
            $pdo->prepare("INSERT INTO audit_log (action, description, ip_address) VALUES (?, ?, ?)")
                ->execute(['delete_request_' . $decision, 'Reviewed delete request #' . $requestId . ' for ' . (string) $request['target_table'] . '.', $_SERVER['REMOTE_ADDR'] ?? null]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
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

function admin_staff_role_to_auth_role(string $staffType): string
{
    if ($staffType === 'admin') {
        return 'admin';
    }
    if ($staffType === 'grower') {
        return 'grower';
    }

    return 'field_agent';
}

function admin_upsert_staff_profile(PDO $pdo, int $userId, string $staffType, array $data = []): void
{
    if ($staffType === 'grower' || $userId <= 0) {
        return;
    }

    $stmt = $pdo->prepare("
        INSERT INTO staff_profiles
            (user_id, staff_type, state, lga, qualification, license_number, experience_years, certification_status, training_program, availability, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            staff_type = VALUES(staff_type),
            state = VALUES(state),
            lga = VALUES(lga),
            qualification = VALUES(qualification),
            license_number = VALUES(license_number),
            experience_years = VALUES(experience_years),
            certification_status = VALUES(certification_status),
            training_program = VALUES(training_program),
            availability = VALUES(availability),
            status = VALUES(status)
    ");
    $stmt->execute([
        $userId,
        $staffType,
        $data['state'] ?? null,
        $data['lga'] ?? null,
        $data['qualification'] ?? null,
        $data['license_number'] ?? null,
        (float) ($data['experience_years'] ?? 0),
        $data['certification_status'] ?? 'not_started',
        $data['training_program'] ?? null,
        $data['availability'] ?? null,
        $data['status'] ?? 'active',
    ]);
}

function admin_display_staff_type(array $user): string
{
    if (!empty($user['staff_type'])) {
        return (string) $user['staff_type'];
    }
    if (($user['role'] ?? '') === 'field_agent' && (int) ($user['is_agronomist'] ?? 0) === 1) {
        return 'agronomist';
    }
    if (($user['role'] ?? '') === 'field_agent' && (int) ($user['is_extensionist'] ?? 0) === 1) {
        return 'extensionist';
    }
    return (string) ($user['role'] ?? 'grower');
}

function admin_active_role_assignments(PDO $pdo, int $userId): array
{
    if ($userId <= 0 || !app_table_exists($pdo, 'user_role_assignments')) {
        return [];
    }
    $stmt = $pdo->prepare("
        SELECT *
        FROM user_role_assignments
        WHERE user_id = ? AND status = 'active'
        ORDER BY assigned_at DESC, id DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function admin_user_has_assigned_role(PDO $pdo, int $userId, string $roleKey): bool
{
    if ($userId <= 0 || $roleKey === '' || !app_table_exists($pdo, 'user_role_assignments')) {
        return false;
    }
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM user_role_assignments
        WHERE user_id = ? AND role_key = ? AND status = 'active'
    ");
    $stmt->execute([$userId, $roleKey]);
    return (int) $stmt->fetchColumn() > 0;
}

function admin_user_has_admin_access(PDO $pdo, int $userId): bool
{
    if ($userId <= 0) {
        return false;
    }
    if (!app_table_exists($pdo, 'user_role_assignments')) {
        return false;
    }
    $adminRoles = ['admin', 'national_coordinator', 'state_coordinator', 'agronomist', 'agric_extensionist', 'field_agent'];
    $placeholders = implode(',', array_fill(0, count($adminRoles), '?'));
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM user_role_assignments
        WHERE user_id = ? AND status = 'active' AND role_key IN ({$placeholders})
    ");
    $stmt->execute(array_merge([$userId], $adminRoles));
    return (int) $stmt->fetchColumn() > 0;
}

function admin_highest_assigned_platform_role(PDO $pdo, int $userId): ?string
{
    $assignments = admin_active_role_assignments($pdo, $userId);
    if (!$assignments) {
        return null;
    }
    $priority = [
        'super_admin' => 100,
        'national_coordinator' => 90,
        'state_coordinator' => 80,
        'admin' => 70,
        'agronomist' => 60,
        'agric_extensionist' => 55,
        'field_agent' => 50,
        'investor' => 40,
        'provider' => 35,
        'grower' => 10,
    ];
    usort($assignments, static fn (array $a, array $b): int => ($priority[(string) $b['role_key']] ?? 0) <=> ($priority[(string) $a['role_key']] ?? 0));
    return (string) $assignments[0]['role_key'];
}

function admin_setting(PDO $pdo, string $key, string $default = ''): string
{
    if (!app_table_exists($pdo, 'settings')) {
        return $default;
    }
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE key_name = ? LIMIT 1");
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    return $value === false ? $default : (string) $value;
}

function admin_feature_catalog(): array
{
    return [
        'dashboard' => 'Dashboard',
        'state_dashboard' => 'State Dashboard',
        'national_dashboard' => 'National Dashboard',
        'governance' => 'Governance & Policy',
        'production_readiness' => 'Production Readiness',
        'profile' => 'Profile',
        'applications' => 'Applications',
        'documents' => 'Document Review',
        'certificates' => 'Certificates',
        'field_network' => 'Field Network',
        'support' => 'Support Desk',
        'farm_health' => 'Farm Health',
        'field_management' => 'Field Management',
        'agronomy_advisory' => 'Agronomy Advisory',
        'marketplace' => 'Marketplace',
        'providers' => 'Service & Input Providers',
        'resource_allocation' => 'Resource Allocation',
        'communications' => 'Communication Hub',
        'wallet' => 'Wallet',
        'training' => 'NATCODEV Academy',
        'healthcare' => 'Healthcare',
        'pricing' => 'Plans & Pricing',
        'resources' => 'Resources',
        'templates' => 'Templates',
        'notifications' => 'Notifications',
        'reports' => 'Reports',
        'analytics' => 'Analytics',
        'monitoring' => 'System Health',
        'user_management' => 'User Management',
        'imports' => 'Bulk Import',
        'settings' => 'Settings',
        'audit' => 'Audit Trail',
        'integrations' => 'Integrations',
    ];
}

function admin_feature_is_globally_enabled(PDO $pdo, string $feature): bool
{
    if ($feature === '' || !array_key_exists($feature, admin_feature_catalog())) {
        return true;
    }

    return admin_setting($pdo, 'module_' . $feature . '_enabled', '1') === '1';
}

function admin_default_access(string $role): array
{
    return match ($role) {
        'super_admin' => array_keys(admin_feature_catalog()),
        'admin' => array_keys(admin_feature_catalog()),
        'national_coordinator' => array_keys(admin_feature_catalog()),
        'state_coordinator' => ['dashboard', 'state_dashboard', 'profile', 'applications', 'documents', 'certificates', 'field_network', 'field_management', 'agronomy_advisory', 'support', 'farm_health', 'providers', 'resource_allocation', 'communications', 'resources', 'training', 'notifications', 'reports', 'analytics'],
        'field_agent', 'agronomist', 'agric_extensionist' => ['dashboard', 'profile', 'applications', 'field_network', 'field_management', 'agronomy_advisory', 'support', 'farm_health', 'resources', 'training', 'notifications', 'reports'],
        'investor' => ['dashboard', 'profile', 'marketplace', 'wallet', 'reports', 'analytics', 'notifications'],
        'learner' => ['dashboard', 'profile', 'support', 'wallet', 'training', 'notifications', 'reports'],
        default => ['dashboard', 'profile', 'applications', 'documents', 'certificates', 'support', 'farm_health', 'marketplace', 'wallet', 'training', 'notifications', 'reports'],
    };
}

function admin_current_platform_role(PDO $pdo): ?string
{
    if (($_SESSION['super_admin_authenticated'] ?? false) === true) {
        return 'super_admin';
    }

    $user = current_user($pdo);
    if (($_SESSION['admin_authenticated'] ?? false) === true || ($_SESSION['admin'] ?? false) === true) {
        if ($user && (int) ($user['is_super_admin'] ?? 0) === 1) {
            return 'super_admin';
        }
        if (!$user || !in_array((string) ($user['role'] ?? ''), ['admin', 'field_agent'], true)) {
            return 'admin';
        }
    }
    if (!$user) {
        return null;
    }
    if ((int) ($user['is_super_admin'] ?? 0) === 1) {
        return 'super_admin';
    }
    if (!empty($user['platform_role']) && (string) $user['platform_role'] !== 'grower') {
        return (string) $user['platform_role'];
    }
    $assignedRole = admin_highest_assigned_platform_role($pdo, (int) $user['id']);
    if ($assignedRole !== null && $assignedRole !== 'grower') {
        return $assignedRole;
    }

    return (string) ($user['role'] ?? 'grower');
}

function status_label(string $status): string
{
    return ucwords(str_replace('_', ' ', $status));
}

function admin_current_scope_state(PDO $pdo): string
{
    if (admin_current_platform_role($pdo) !== 'state_coordinator') {
        return '';
    }
    $user = current_user($pdo);
    if (!$user) {
        return '';
    }
    if (app_table_exists($pdo, 'user_role_assignments')) {
        $stmt = $pdo->prepare("
            SELECT scope_value
            FROM user_role_assignments
            WHERE user_id = ? AND role_key = 'state_coordinator' AND scope_type = 'state' AND status = 'active'
            ORDER BY assigned_at DESC, id DESC
            LIMIT 1
        ");
        $stmt->execute([(int) $user['id']]);
        $assignedState = trim((string) ($stmt->fetchColumn() ?: ''));
        if ($assignedState !== '') {
            return $assignedState;
        }
    }
    $stmt = $pdo->prepare("SELECT state FROM staff_profiles WHERE user_id = ? LIMIT 1");
    $stmt->execute([(int) $user['id']]);
    $state = trim((string) ($stmt->fetchColumn() ?: ''));
    return $state !== '' ? $state : trim((string) ($user['location'] ?? ''));
}

function admin_feature_for_script(?string $script = null): string
{
    $script = $script ?? basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'admin.php'));
    return [
        'index.php' => 'dashboard',
        'admin.php' => 'applications',
        'registry.php' => 'applications',
        'coordination.php' => 'dashboard',
        'state-dashboard.php' => 'state_dashboard',
        'national-dashboard.php' => 'national_dashboard',
        'governance.php' => 'governance',
        'production-readiness.php' => 'production_readiness',
        'providers.php' => 'providers',
        'resource-allocation.php' => 'resource_allocation',
        'communications.php' => 'communications',
        'document-verification.php' => 'documents',
        'bulk-verification.php' => 'documents',
        'certificate-batch-verification.php' => 'certificates',
        'support.php' => 'support',
        'recruitment.php' => 'field_network',
        'agent-map.php' => 'field_network',
        'reports.php' => 'reports',
        'assign-growers.php' => 'field_network',
        'fields-management.php' => 'field_management',
        'agronomy.php' => 'agronomy_advisory',
        'analytics.php' => 'analytics',
        'demographics.php' => 'analytics',
        'validation-stats.php' => 'analytics',
        'monitoring.php' => 'monitoring',
        'marketplace.php' => 'marketplace',
        'wallet.php' => 'wallet',
        'resources.php' => 'resources',
        'academy.php' => 'training',
        'templates.php' => 'templates',
        'notifications.php' => 'notifications',
        'settings.php' => 'settings',
        'users.php' => 'user_management',
        'import-users.php' => 'imports',
        'profile.php' => 'profile',
    ][$script] ?? 'dashboard';
}

function admin_feature_is_allowed(PDO $pdo, string $feature): bool
{
    $role = admin_current_platform_role($pdo);
    if ($role === null) {
        return true;
    }
    if ($role === 'super_admin') {
        return true;
    }
    if (!admin_feature_is_globally_enabled($pdo, $feature)) {
        return false;
    }

    $roles = [$role];
    $user = current_user($pdo);
    if ($user) {
        $baseRole = (string) ($user['role'] ?? '');
        if (in_array($baseRole, ['grower', 'investor'], true)) {
            $roles[] = $baseRole;
        }
        foreach (admin_active_role_assignments($pdo, (int) $user['id']) as $assignment) {
            $roles[] = (string) $assignment['role_key'];
        }
    }
    $roles = array_values(array_unique(array_filter($roles)));

    foreach ($roles as $roleKey) {
        $default = implode(',', admin_default_access($roleKey));
        $allowed = array_values(array_filter(array_map('trim', explode(',', admin_setting($pdo, 'access_matrix_' . $roleKey, $default)))));
        if (admin_setting($pdo, 'access_matrix_catalog_version', '') !== ADMIN_ACCESS_CATALOG_VERSION) {
            $allowed = array_values(array_unique(array_merge($allowed, admin_default_access($roleKey))));
        }
        if (in_array($feature, $allowed, true)) {
            return true;
        }
    }
    return false;
}

function admin_require_feature(PDO $pdo, string $feature): void
{
    if (!admin_feature_is_allowed($pdo, $feature)) {
        http_response_code(403);
        exit('Forbidden: this admin role does not have access to this feature.');
    }
}

function admin_per_page(int $default = 50): int
{
    $allowed = [10, 25, 50, 100, 200, 500];
    $perPage = (int) ($_GET['per_page'] ?? $default);
    return in_array($perPage, $allowed, true) ? $perPage : $default;
}

function admin_current_page(): int
{
    return max(1, (int) ($_GET['page'] ?? 1));
}

function admin_pagination_offset(int $page, int $perPage): int
{
    return max(0, ($page - 1) * $perPage);
}

function admin_pagination_controls(int $total, int $page, int $perPage, array $extra = []): string
{
    $pages = max(1, (int) ceil($total / max(1, $perPage)));
    $page = min(max(1, $page), $pages);
    $base = array_merge($_GET, $extra);
    unset($base['page'], $base['per_page']);
    $from = $total === 0 ? 0 : (($page - 1) * $perPage) + 1;
    $to = min($total, $page * $perPage);

    $url = static function (int $targetPage, int $targetPerPage) use ($base): string {
        return '?' . http_build_query($base + ['page' => $targetPage, 'per_page' => $targetPerPage]);
    };

    ob_start();
    ?>
    <form class="pagination" method="get">
      <?php foreach ($base as $key => $value): ?>
        <?php if (is_scalar($value)): ?><input type="hidden" name="<?= e((string) $key) ?>" value="<?= e((string) $value) ?>"><?php endif; ?>
      <?php endforeach; ?>
      <div class="meta">Showing <?= (int) $from ?>-<?= (int) $to ?> of <?= (int) $total ?></div>
      <div class="pagination-links">
        <a class="button secondary" href="<?= e($url(max(1, $page - 1), $perPage)) ?>" aria-disabled="<?= $page <= 1 ? 'true' : 'false' ?>">Previous</a>
        <span class="meta">Page <?= (int) $page ?> of <?= (int) $pages ?></span>
        <a class="button secondary" href="<?= e($url(min($pages, $page + 1), $perPage)) ?>" aria-disabled="<?= $page >= $pages ? 'true' : 'false' ?>">Next</a>
      </div>
      <label class="pagination-size">Rows
        <select name="per_page" onchange="this.form.page.value='1'; this.form.submit()">
          <?php foreach ([10, 25, 50, 100, 200, 500] as $size): ?>
            <option value="<?= $size ?>" <?= $perPage === $size ? 'selected' : '' ?>><?= $size ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <input type="hidden" name="page" value="<?= (int) $page ?>">
    </form>
    <?php
    return (string) ob_get_clean();
}

function admin_nav_groups(): array
{
    return [
        'Dashboards' => [
            ['href' => 'index.php', 'label' => 'Workspace Hub', 'feature' => 'dashboard'],
            ['href' => 'coordination.php', 'label' => 'Role Dashboard', 'feature' => 'dashboard'],
            ['href' => 'state-dashboard.php', 'label' => 'State Dashboard', 'feature' => 'state_dashboard'],
            ['href' => 'national-dashboard.php', 'label' => 'National Dashboard', 'feature' => 'national_dashboard'],
        ],
        'Registry Operations' => [
            ['href' => 'registry.php', 'label' => 'Registry Workspace', 'feature' => 'applications'],
            ['href' => 'admin.php', 'label' => 'Applications', 'feature' => 'applications'],
            ['href' => 'document-verification.php', 'label' => 'Documents', 'feature' => 'documents'],
            ['href' => 'bulk-verification.php', 'label' => 'Bulk Review', 'feature' => 'documents'],
            ['href' => 'certificate-batch-verification.php', 'label' => 'Batch Certificate Verify', 'feature' => 'certificates'],
        ],
        'Support Desk' => [
            ['href' => 'support.php', 'label' => 'Support Console', 'feature' => 'support'],
        ],
        'HR & People' => [
            ['href' => 'users.php', 'label' => 'Users & Roles', 'feature' => 'user_management'],
            ['href' => 'recruitment.php', 'label' => 'Recruitment', 'feature' => 'field_network'],
            ['href' => 'import-users.php', 'label' => 'Import & Engagement', 'feature' => 'imports'],
        ],
        'Field Network' => [
            ['href' => 'agent-map.php', 'label' => 'Agent Map', 'feature' => 'field_network'],
            ['href' => 'fields-management.php', 'label' => 'Fields Management', 'feature' => 'field_management'],
            ['href' => 'agronomy.php', 'label' => 'Agronomy Advisory', 'feature' => 'agronomy_advisory'],
            ['href' => 'assign-growers.php', 'label' => 'Assignments', 'feature' => 'field_network'],
        ],
        'Insights & Reports' => [
            ['href' => 'analytics.php', 'label' => 'Analytics', 'feature' => 'analytics'],
            ['href' => 'reports.php', 'label' => 'Reporting Intelligence', 'feature' => 'reports'],
            ['href' => 'demographics.php', 'label' => 'Demographics', 'feature' => 'analytics'],
            ['href' => 'validation-stats.php', 'label' => 'Validation Stats', 'feature' => 'analytics'],
        ],
        'Marketplace & Providers' => [
            ['href' => 'marketplace.php', 'label' => 'Marketplace', 'feature' => 'marketplace'],
            ['href' => 'providers.php', 'label' => 'Input & Service Providers', 'feature' => 'providers'],
            ['href' => 'resource-allocation.php', 'label' => 'Resource Allocation', 'feature' => 'resource_allocation'],
        ],
        'Wallet & Payments' => [
            ['href' => 'wallet.php', 'label' => 'Wallet Workspace', 'feature' => 'wallet'],
            ['href' => 'reports.php?report=finance', 'label' => 'Finance Reports', 'feature' => 'reports'],
        ],
        'Communication & Content' => [
            ['href' => 'communications.php', 'label' => 'Communication Hub', 'feature' => 'communications'],
            ['href' => 'notifications.php', 'label' => 'Notification Log', 'feature' => 'notifications'],
        ],
        'Learning & Training' => [
            ['href' => 'resources.php', 'label' => 'Learning Resources', 'feature' => 'resources'],
            ['href' => 'academy.php', 'label' => 'NATCODEV Academy', 'feature' => 'training'],
            ['href' => '../super-admin/index.php?view=training', 'label' => 'Training Governance Policy', 'feature' => 'training'],
        ],
        'Governance & Compliance' => [
            ['href' => 'governance.php', 'label' => 'Policies & Governance', 'feature' => 'governance'],
            ['href' => 'production-readiness.php', 'label' => 'Production Readiness', 'feature' => 'production_readiness'],
            ['href' => 'monitoring.php', 'label' => 'System Health', 'feature' => 'monitoring'],
        ],
        'System Settings' => [
            ['href' => 'settings.php', 'label' => 'Operational Settings', 'feature' => 'settings'],
            ['href' => 'templates.php', 'label' => 'Message Templates', 'feature' => 'templates'],
            ['href' => 'notifications.php', 'label' => 'Notification Delivery Log', 'feature' => 'notifications'],
            ['href' => '../super-admin/index.php?view=modules', 'label' => 'Module Setup', 'feature' => 'integrations'],
        ],
    ];
}

function admin_allowed_nav_groups(PDO $pdo): array
{
    $groups = [];
    foreach (admin_nav_groups() as $groupLabel => $items) {
        $allowedItems = array_values(array_filter($items, static fn (array $item): bool => admin_feature_is_allowed($pdo, (string) ($item['feature'] ?? 'dashboard'))));
        if ($allowedItems) {
            $groups[$groupLabel] = $allowedItems;
        }
    }

    return $groups;
}

function admin_footer_nav_items(PDO $pdo): array
{
    $items = [
        ['href' => 'admin.php', 'label' => 'Applications', 'feature' => 'applications'],
        ['href' => 'document-verification.php', 'label' => 'Documents', 'feature' => 'documents'],
        ['href' => 'support.php', 'label' => 'Support Desk', 'feature' => 'support'],
        ['href' => 'reports.php', 'label' => 'Reports', 'feature' => 'reports'],
        ['href' => 'settings.php', 'label' => 'Settings', 'feature' => 'settings'],
    ];

    return array_values(array_filter($items, static fn (array $item): bool => admin_feature_is_allowed($pdo, (string) ($item['feature'] ?? 'dashboard'))));
}

function admin_page_start(string $title, array $options = []): void
{
    $active = $options['active'] ?? basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'admin.php'));
    $description = (string) ($options['description'] ?? '');
    $wide = !empty($options['wide']);
    $chrome = (bool) ($options['chrome'] ?? true);
    $GLOBALS['admin_page_chrome'] = $chrome;
    $max = $wide ? '1320px' : '1180px';
    $navGroups = admin_allowed_nav_groups(db());
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($title) ?> - NATCODEV Admin</title>
  <style>
    :root { --primary:#1a5276; --green:#1f8a55; --green-dark:#166b41; --ink:#1f2937; --muted:#667085; --line:#d8e2dc; --bg:#f5f8f6; --panel:#fff; --danger:#a32020; --warn:#9b6500; --shadow:0 14px 34px rgba(16,24,40,.08); }
    * { box-sizing:border-box; }
    body { margin:0; background:var(--bg); color:var(--ink); font-family:"Segoe UI", Tahoma, Geneva, Verdana, sans-serif; }
    a { color:var(--green-dark); font-weight:750; text-decoration:none; }
    a:hover { text-decoration:underline; }
    .admin-shell { min-height:100vh; display:flex; flex-direction:column; }
    .admin-header { background:#fff; border-bottom:1px solid rgba(16,24,40,.08); box-shadow:0 8px 24px rgba(16,24,40,.06); position:sticky; top:0; z-index:20; }
    .admin-bar { max-width:<?= $max ?>; margin:0 auto; padding:14px 22px; display:flex; align-items:center; justify-content:space-between; gap:18px; }
    .admin-brand { display:flex; align-items:center; gap:11px; color:var(--primary); font-weight:900; min-width:220px; }
    .admin-brand img { width:46px; height:46px; object-fit:contain; border-radius:50%; border:1px solid var(--line); background:#fff; }
    .admin-brand span { display:block; color:var(--muted); font-size:.82rem; font-weight:650; margin-top:3px; }
    .admin-nav { display:flex; flex-wrap:wrap; justify-content:center; gap:8px; }
    .admin-nav details { position:relative; }
    .admin-nav summary, .admin-nav .nav-link { display:inline-flex; align-items:center; gap:7px; min-height:39px; padding:9px 11px; border-radius:7px; border:1px solid transparent; color:#344054; font-size:.92rem; font-weight:800; cursor:pointer; list-style:none; }
    .admin-nav summary::-webkit-details-marker { display:none; }
    .admin-nav summary::after { content:""; width:7px; height:7px; border-right:2px solid currentColor; border-bottom:2px solid currentColor; transform:rotate(45deg) translateY(-2px); opacity:.7; }
    .admin-nav details.active:not([open]) > summary { background:#f5faf7; border-color:#d7eadf; color:var(--green-dark); }
    .admin-nav details[open] > summary, .admin-nav summary:hover, .admin-nav .nav-link.active, .admin-nav .nav-link:hover { background:#eef7f1; border-color:#cfe6d8; color:var(--green-dark); text-decoration:none; }
    .admin-nav details[open] summary::after { transform:rotate(225deg) translate(-2px,-1px); }
    .admin-menu { position:absolute; right:0; top:calc(100% + 8px); width:min(280px, calc(100vw - 44px)); padding:8px; background:#fff; border:1px solid rgba(16,24,40,.11); border-radius:8px; box-shadow:0 18px 38px rgba(16,24,40,.16); display:grid; gap:4px; z-index:100; }
    .admin-menu a { color:#344054; padding:10px 11px; border-radius:6px; font-size:.92rem; }
    .admin-menu a:focus-visible, .admin-nav summary:focus-visible, .admin-nav .nav-link:focus-visible { outline:3px solid rgba(31,138,85,.22); outline-offset:2px; }
    .admin-menu a.active, .admin-menu a:hover { background:#f1faf5; color:var(--green-dark); text-decoration:none; }
    .admin-user { min-width:120px; text-align:right; }
    .admin-main { width:100%; max-width:<?= $max ?>; margin:0 auto; padding:28px 22px 38px; flex:1; }
    .page-title { margin-bottom:20px; display:flex; align-items:flex-start; justify-content:space-between; gap:18px; }
    .page-title h1 { color:var(--primary); margin:0; font-size:clamp(2rem,4vw,3rem); line-height:1.06; }
    .page-title p { color:var(--muted); margin:8px 0 0; max-width:780px; line-height:1.6; }
    .panel, .card, .stat, table { background:var(--panel); border:1px solid rgba(16,24,40,.08); border-radius:8px; box-shadow:var(--shadow); }
    .panel, .card, .stat { padding:18px; }
    .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:16px; }
    .layout { display:grid; grid-template-columns:340px 1fr; gap:18px; align-items:start; }
    .stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:14px; margin:18px 0; }
    .metric { color:var(--primary); font-size:2rem; font-weight:900; line-height:1; }
    .toolbar, .actions { display:flex; flex-wrap:wrap; align-items:center; gap:10px; margin:14px 0; }
    .pagination { margin:14px 0; display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; padding:12px; background:#fff; border:1px solid rgba(16,24,40,.08); border-radius:8px; }
    .pagination-links { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
    .pagination-size { display:flex; align-items:center; gap:8px; margin:0; }
    .pagination-size select { width:auto; min-width:86px; }
    table { width:100%; border-collapse:collapse; overflow:hidden; }
    th, td { padding:11px; border-bottom:1px solid #edf1ea; text-align:left; vertical-align:top; }
    th { background:#eef6e9; color:#243b1d; }
    label { display:block; font-weight:800; margin:10px 0 6px; }
    input, select, textarea { padding:11px 12px; border:1px solid var(--line); border-radius:6px; font:inherit; max-width:100%; }
    input:not([type="checkbox"]), select, textarea { width:100%; }
    textarea { min-height:110px; }
    input:focus, select:focus, textarea:focus { border-color:var(--green); box-shadow:0 0 0 3px rgba(31,138,85,.14); outline:none; }
    .password-field { position:relative; }
    .password-field input { padding-right:76px; }
    .password-toggle { position:absolute; right:8px; top:50%; transform:translateY(-50%); width:auto; margin:0; padding:7px 9px; border:0; background:#eef7f1; color:var(--green-dark); font-size:.82rem; box-shadow:none; }
    button, .button { display:inline-flex; align-items:center; justify-content:center; gap:8px; background:var(--green); color:#fff; border:0; border-radius:6px; padding:11px 14px; font-weight:850; cursor:pointer; text-decoration:none; box-shadow:0 10px 24px rgba(31,138,85,.18); }
    button:hover, .button:hover { background:var(--green-dark); color:#fff; text-decoration:none; }
    button[disabled], button.is-busy, .button[aria-disabled="true"] { opacity:.82; cursor:wait; pointer-events:none; }
    button.is-busy::before { content:""; width:14px; height:14px; border:2px solid rgba(255,255,255,.5); border-top-color:#fff; border-radius:50%; animation:admin-spin .7s linear infinite; }
    button.is-busy { background:var(--green-dark); color:#fff; box-shadow:0 10px 24px rgba(31,138,85,.24); }
    .button.secondary, button.secondary { background:#eef7f1; color:var(--green-dark); border:1px solid var(--line); box-shadow:none; }
    button.secondary.is-busy::before { border-color:rgba(22,107,65,.25); border-top-color:var(--green-dark); }
    .button.danger, button.danger { background:var(--danger); }
    .badge { display:inline-flex; align-items:center; border-radius:999px; padding:5px 9px; font-size:.78rem; font-weight:850; white-space:nowrap; }
    .ok, .success, .verified, .resolved { background:#eaf8f0; color:#0f6b3c; }
    .pending, .in_progress, .warning { background:#fff7df; color:#8a5a00; }
    .error, .rejected, .danger { background:#fff3f3; color:var(--danger); }
    .open, .closed, .muted-badge { background:#eef2f6; color:#475467; }
    .notice { padding:13px 15px; border-radius:8px; margin:16px 0; border:1px solid transparent; }
    .notice.ok { border-color:#bfe8cf; }
    .notice.error { border-color:#ffd2d2; }
    .muted, .meta, small { color:var(--muted); }
    .empty { color:var(--muted); border:1px dashed var(--line); border-radius:8px; padding:18px; }
    .admin-footer { background:#12344a; color:#e6f0f5; margin-top:auto; }
    .admin-footer-inner { max-width:<?= $max ?>; margin:0 auto; padding:18px 22px; display:flex; align-items:center; justify-content:space-between; gap:22px; flex-wrap:wrap; }
    .footer-links { display:flex; align-items:center; justify-content:flex-end; flex-wrap:wrap; gap:10px; }
    .footer-links a { color:#f6fff2; font-size:.9rem; font-weight:750; padding:7px 10px; border:1px solid rgba(255,255,255,.16); border-radius:6px; }
    .footer-links a:hover { background:rgba(255,255,255,.1); text-decoration:none; }
    .admin-action-overlay { position:fixed; left:0; right:0; top:0; height:4px; background:linear-gradient(90deg, var(--green), #c9a227, var(--primary), var(--green)); background-size:220% 100%; z-index:90; display:none; pointer-events:none; animation:admin-progress 1s linear infinite; }
    .admin-working-toast { position:fixed; right:18px; bottom:18px; z-index:91; display:none; align-items:center; gap:10px; padding:12px 14px; border-radius:8px; background:#12344a; color:#fff; box-shadow:0 14px 30px rgba(16,24,40,.2); font-weight:850; }
    .admin-working-toast::before { content:""; width:16px; height:16px; border:2px solid rgba(255,255,255,.45); border-top-color:#fff; border-radius:50%; animation:admin-spin .7s linear infinite; }
    body.admin-submitting .admin-action-overlay, body.admin-submitting .admin-working-toast { display:flex; }
    @keyframes admin-spin { to { transform:rotate(360deg); } }
    @keyframes admin-progress { to { background-position:-220% 0; } }
    @media (max-width:960px) {
      .admin-bar { align-items:flex-start; flex-direction:column; }
      .admin-nav { justify-content:flex-start; }
      .admin-menu { left:0; right:auto; }
      .admin-user { text-align:left; }
      .layout { grid-template-columns:1fr; }
      .page-title { flex-direction:column; }
      .admin-footer-inner { align-items:flex-start; flex-direction:column; }
    }
    @media (max-width:560px) {
      .admin-bar, .admin-main, .admin-footer-inner { padding-left:16px; padding-right:16px; }
      .admin-nav { width:100%; display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); }
      .admin-nav details, .admin-nav summary, .admin-nav .nav-link { width:100%; }
      .admin-nav summary, .admin-nav .nav-link { justify-content:center; }
      .admin-menu { width:calc(100vw - 32px); }
      .footer-links { justify-content:flex-start; }
    }
    <?= $options['css'] ?? '' ?>
  </style>
  <link rel="stylesheet" href="../assets/css/natcodev-ui.css?v=20260530">
</head>
<body>
<div class="admin-shell">
  <div class="admin-action-overlay" aria-hidden="true"></div>
  <div class="admin-working-toast" role="status" aria-live="polite">Processing request...</div>
  <?php if ($chrome): ?>
  <header class="admin-header">
    <div class="admin-bar">
      <a class="admin-brand" href="index.php">
        <img src="<?= e(app_primary_logo_url()) ?>" alt="NATCODEV">
        <span><strong>NATCODEV Admin</strong><span>Workspace operations hub</span></span>
      </a>
      <nav class="admin-nav" aria-label="Admin navigation">
        <?php foreach ($navGroups as $groupLabel => $items): ?>
          <?php $groupActive = in_array($active, array_column($items, 'href'), true); ?>
          <details class="<?= $groupActive ? 'active' : '' ?>">
            <summary><?= e((string) $groupLabel) ?></summary>
            <div class="admin-menu">
              <?php foreach ($items as $item): ?>
                <a class="<?= $active === $item['href'] ? 'active' : '' ?>" href="<?= e($item['href']) ?>"><?= e($item['label']) ?></a>
              <?php endforeach; ?>
            </div>
          </details>
        <?php endforeach; ?>
      </nav>
      <div class="admin-user"><a href="admin.php?logout=1">Logout</a></div>
    </div>
  </header>
  <?php endif; ?>
  <main class="admin-main">
    <?php if ($chrome): ?>
    <section class="page-title">
      <div>
        <h1><?= e($title) ?></h1>
        <?php if ($description !== ''): ?><p><?= e($description) ?></p><?php endif; ?>
      </div>
      <?php if (!empty($options['action_html'])): ?><div><?= $options['action_html'] ?></div><?php endif; ?>
    </section>
    <?php endif; ?>
<?php
}

function admin_page_end(): void
{
    $footerItems = admin_footer_nav_items(db());
    ?>
  </main>
  <?php if (!empty($GLOBALS['admin_page_chrome'])): ?>
  <footer class="admin-footer">
    <div class="admin-footer-inner">
      <div>
        <strong>NATCODEV Admin Console</strong>
        <div class="meta" style="margin-top:6px;color:#c9d8df;">Dashboards, registry work, HR, field operations, reporting, governance, and settings now have separate homes.</div>
      </div>
      <nav class="footer-links" aria-label="Admin quick links">
        <?php foreach ($footerItems as $item): ?>
          <a href="<?= e($item['href']) ?>"><?= e($item['label']) ?></a>
        <?php endforeach; ?>
      </nav>
    </div>
  </footer>
  <?php endif; ?>
</div>
<script src="../lib/location-picker.js"></script>
<script>
(function () {
  const nav = document.querySelector('.admin-nav');
  const details = nav ? Array.from(nav.querySelectorAll('details')) : [];

  function closeMenus(except) {
    details.forEach((item) => {
      if (item !== except) item.removeAttribute('open');
    });
  }

  details.forEach((item) => {
    const summary = item.querySelector('summary');
    if (!summary) return;
    summary.addEventListener('click', () => {
      window.setTimeout(() => {
        if (item.open) closeMenus(item);
      }, 0);
    });
  });

  document.addEventListener('click', (event) => {
    if (!nav || nav.contains(event.target)) return;
    closeMenus(null);
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') closeMenus(null);
  });

  window.addEventListener('scroll', () => closeMenus(null), { passive:true });
  window.addEventListener('resize', () => closeMenus(null));
  document.addEventListener('touchmove', () => closeMenus(null), { passive:true });

  document.querySelectorAll('.admin-menu a').forEach((link) => {
    link.addEventListener('click', () => closeMenus(null));
  });

  document.querySelectorAll('form').forEach((form) => {
    form.addEventListener('submit', (event) => {
      if (event.defaultPrevented) {
        return;
      }
      if (form.dataset.submitting === '1') {
        event.preventDefault();
        return;
      }

      form.dataset.submitting = '1';
      const submitter = event.submitter || form.querySelector('button[type="submit"], button:not([type]), input[type="submit"]');
      if (submitter && submitter.name) {
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = submitter.name;
        hidden.value = submitter.value || '';
        form.appendChild(hidden);
      }
      if (submitter && submitter.tagName === 'BUTTON') {
        submitter.dataset.originalText = submitter.textContent.trim();
        submitter.classList.add('is-busy');
        submitter.disabled = true;
        const busyText = submitter.dataset.busyText || 'Processing...';
        submitter.textContent = busyText;
        const toast = document.querySelector('.admin-working-toast');
        if (toast) toast.lastChild.textContent = busyText;
      }

      form.querySelectorAll('button[type="submit"], button:not([type]), input[type="submit"]').forEach((button) => {
        if (button !== submitter) button.disabled = true;
      });
      document.body.classList.add('admin-submitting');
    });
  });

  document.querySelectorAll('.password-toggle').forEach((button) => {
    button.addEventListener('click', () => {
      const input = document.getElementById(button.dataset.target || '');
      if (!input) return;
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      button.textContent = show ? 'Hide' : 'Show';
      button.setAttribute('aria-pressed', show ? 'true' : 'false');
    });
  });
})();
</script>
</body>
</html>
<?php
}
