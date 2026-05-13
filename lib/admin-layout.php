<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

const ADMIN_ACCESS_CATALOG_VERSION = '20260513-7';

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
        redirect_to('admin.php');
    }
    admin_require_feature($pdo, admin_feature_for_script());
}

function admin_logout(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    session_regenerate_id(true);
    unset($_SESSION['admin_authenticated'], $_SESSION['admin'], $_SESSION['_csrf']);
    redirect_to('admin.php');
}

function admin_ensure_schema(PDO $pdo): void
{
    app_ensure_core_schema($pdo);
    app_ensure_farmer_engagement_schema($pdo);
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
        'training' => 'Training & Webinars',
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

function admin_default_access(string $role): array
{
    return match ($role) {
        'super_admin' => array_keys(admin_feature_catalog()),
        'admin' => ['dashboard', 'state_dashboard', 'national_dashboard', 'profile', 'applications', 'documents', 'certificates', 'field_network', 'field_management', 'agronomy_advisory', 'support', 'farm_health', 'marketplace', 'providers', 'resource_allocation', 'communications', 'wallet', 'training', 'resources', 'templates', 'notifications', 'reports', 'analytics', 'monitoring', 'production_readiness', 'user_management', 'imports'],
        'national_coordinator' => ['dashboard', 'state_dashboard', 'national_dashboard', 'profile', 'applications', 'documents', 'certificates', 'field_network', 'field_management', 'agronomy_advisory', 'support', 'farm_health', 'marketplace', 'providers', 'resource_allocation', 'communications', 'wallet', 'training', 'resources', 'templates', 'notifications', 'reports', 'analytics', 'monitoring', 'production_readiness', 'user_management', 'imports'],
        'state_coordinator' => ['dashboard', 'state_dashboard', 'profile', 'applications', 'documents', 'certificates', 'field_network', 'field_management', 'agronomy_advisory', 'support', 'farm_health', 'providers', 'resource_allocation', 'communications', 'resources', 'training', 'notifications', 'reports', 'analytics'],
        'field_agent', 'agronomist', 'agric_extensionist' => ['dashboard', 'profile', 'applications', 'field_network', 'field_management', 'agronomy_advisory', 'support', 'farm_health', 'resources', 'training', 'notifications', 'reports'],
        'investor' => ['dashboard', 'profile', 'marketplace', 'wallet', 'reports', 'analytics', 'notifications'],
        default => ['dashboard', 'profile', 'applications', 'documents', 'certificates', 'support', 'farm_health', 'marketplace', 'wallet', 'training', 'notifications'],
    };
}

function admin_current_platform_role(PDO $pdo): ?string
{
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
    if (!empty($user['platform_role'])) {
        return (string) $user['platform_role'];
    }

    return (string) ($user['role'] ?? 'grower');
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
    $stmt = $pdo->prepare("SELECT state FROM staff_profiles WHERE user_id = ? LIMIT 1");
    $stmt->execute([(int) $user['id']]);
    $state = trim((string) ($stmt->fetchColumn() ?: ''));
    return $state !== '' ? $state : trim((string) ($user['location'] ?? ''));
}

function admin_feature_for_script(?string $script = null): string
{
    $script = $script ?? basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'admin.php'));
    return [
        'admin.php' => 'applications',
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
        'support.php' => 'support',
        'recruitment.php' => 'field_network',
        'agent-map.php' => 'field_network',
        'reports.php' => 'field_network',
        'assign-growers.php' => 'field_network',
        'fields-management.php' => 'field_management',
        'agronomy.php' => 'agronomy_advisory',
        'analytics.php' => 'analytics',
        'demographics.php' => 'analytics',
        'validation-stats.php' => 'analytics',
        'monitoring.php' => 'monitoring',
        'marketplace.php' => 'marketplace',
        'resources.php' => 'resources',
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

    $default = implode(',', admin_default_access($role));
    $allowed = array_values(array_filter(array_map('trim', explode(',', admin_setting($pdo, 'access_matrix_' . $role, $default)))));
    if (admin_setting($pdo, 'access_matrix_catalog_version', '') !== ADMIN_ACCESS_CATALOG_VERSION) {
        $allowed = array_values(array_unique(array_merge($allowed, admin_default_access($role))));
    }
    return in_array($feature, $allowed, true);
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
        'Operations' => [
            ['href' => 'coordination.php', 'label' => 'Role Dashboard', 'feature' => 'dashboard'],
            ['href' => 'state-dashboard.php', 'label' => 'State Dashboard', 'feature' => 'state_dashboard'],
            ['href' => 'national-dashboard.php', 'label' => 'National Dashboard', 'feature' => 'national_dashboard'],
            ['href' => 'admin.php', 'label' => 'Applications', 'feature' => 'applications'],
            ['href' => 'document-verification.php', 'label' => 'Documents', 'feature' => 'documents'],
            ['href' => 'bulk-verification.php', 'label' => 'Bulk Review', 'feature' => 'documents'],
            ['href' => 'support.php', 'label' => 'Support Desk', 'feature' => 'support'],
        ],
        'Field Network' => [
            ['href' => 'recruitment.php', 'label' => 'Recruitment', 'feature' => 'field_network'],
            ['href' => 'agent-map.php', 'label' => 'Agent Map', 'feature' => 'field_network'],
            ['href' => 'fields-management.php', 'label' => 'Fields Management', 'feature' => 'field_management'],
            ['href' => 'agronomy.php', 'label' => 'Agronomy Advisory', 'feature' => 'agronomy_advisory'],
            ['href' => 'reports.php', 'label' => 'Agent Reports', 'feature' => 'field_network'],
            ['href' => 'assign-growers.php', 'label' => 'Assignments', 'feature' => 'field_network'],
        ],
        'Insights' => [
            ['href' => 'analytics.php', 'label' => 'Analytics', 'feature' => 'analytics'],
            ['href' => 'demographics.php', 'label' => 'Demographics', 'feature' => 'analytics'],
            ['href' => 'validation-stats.php', 'label' => 'Validation Stats', 'feature' => 'analytics'],
            ['href' => 'monitoring.php', 'label' => 'System Health', 'feature' => 'monitoring'],
            ['href' => 'production-readiness.php', 'label' => 'Production Readiness', 'feature' => 'production_readiness'],
        ],
        'Content & Admin' => [
            ['href' => 'marketplace.php', 'label' => 'Marketplace', 'feature' => 'marketplace'],
            ['href' => 'providers.php', 'label' => 'Providers', 'feature' => 'providers'],
            ['href' => 'resource-allocation.php', 'label' => 'Resource Allocation', 'feature' => 'resource_allocation'],
            ['href' => 'communications.php', 'label' => 'Communication Hub', 'feature' => 'communications'],
            ['href' => 'resources.php', 'label' => 'Resources', 'feature' => 'resources'],
            ['href' => 'templates.php', 'label' => 'Templates', 'feature' => 'templates'],
            ['href' => 'notifications.php', 'label' => 'Notification Log', 'feature' => 'notifications'],
            ['href' => 'settings.php', 'label' => 'Settings', 'feature' => 'settings'],
            ['href' => 'users.php', 'label' => 'Users', 'feature' => 'user_management'],
            ['href' => 'import-users.php', 'label' => 'Import & Engagement', 'feature' => 'imports'],
            ['href' => 'governance.php', 'label' => 'Governance', 'feature' => 'governance'],
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

function admin_page_start(string $title, array $options = []): void
{
    $active = $options['active'] ?? basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'admin.php'));
    $description = (string) ($options['description'] ?? '');
    $wide = !empty($options['wide']);
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
    .admin-footer-inner { max-width:<?= $max ?>; margin:0 auto; padding:22px; display:grid; grid-template-columns:minmax(220px,1fr) minmax(0,2.5fr); gap:28px; }
    .footer-groups { display:grid; grid-template-columns:repeat(4,minmax(130px,1fr)); gap:18px; }
    .footer-group strong { display:block; color:#fff; margin-bottom:8px; font-size:.9rem; }
    .footer-links { display:grid; gap:7px; }
    .footer-links a { color:#f6fff2; font-size:.9rem; font-weight:650; }
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
      .admin-footer-inner { grid-template-columns:1fr; }
      .footer-groups { grid-template-columns:repeat(2,minmax(140px,1fr)); }
    }
    @media (max-width:560px) {
      .admin-bar, .admin-main, .admin-footer-inner { padding-left:16px; padding-right:16px; }
      .admin-nav { width:100%; display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); }
      .admin-nav details, .admin-nav summary, .admin-nav .nav-link { width:100%; }
      .admin-nav summary, .admin-nav .nav-link { justify-content:center; }
      .admin-menu { width:calc(100vw - 32px); }
      .footer-groups { grid-template-columns:1fr; }
    }
    <?= $options['css'] ?? '' ?>
  </style>
</head>
<body>
<div class="admin-shell">
  <div class="admin-action-overlay" aria-hidden="true"></div>
  <div class="admin-working-toast" role="status" aria-live="polite">Processing request...</div>
  <header class="admin-header">
    <div class="admin-bar">
      <a class="admin-brand" href="admin.php">
        <img src="<?= e(app_primary_logo_url()) ?>" alt="NATCODEV">
        <span><strong>NATCODEV Admin</strong><span>Registry operations console</span></span>
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
  <main class="admin-main">
    <section class="page-title">
      <div>
        <h1><?= e($title) ?></h1>
        <?php if ($description !== ''): ?><p><?= e($description) ?></p><?php endif; ?>
      </div>
      <?php if (!empty($options['action_html'])): ?><div><?= $options['action_html'] ?></div><?php endif; ?>
    </section>
<?php
}

function admin_page_end(): void
{
    $navGroups = admin_allowed_nav_groups(db());
    ?>
  </main>
  <footer class="admin-footer">
    <div class="admin-footer-inner">
      <div>
        <strong>NATCODEV Registry Operations</strong>
        <div class="meta" style="margin-top:6px;color:#c9d8df;">Applications, verification, field network, support, and content controls.</div>
      </div>
      <nav class="footer-groups" aria-label="Admin secondary navigation">
        <?php foreach ($navGroups as $groupLabel => $items): ?>
          <div class="footer-group">
            <strong><?= e((string) $groupLabel) ?></strong>
            <div class="footer-links">
              <?php foreach ($items as $item): ?>
                <a href="<?= e($item['href']) ?>"><?= e($item['label']) ?></a>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </nav>
    </div>
  </footer>
</div>
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
})();
</script>
</body>
</html>
<?php
}
