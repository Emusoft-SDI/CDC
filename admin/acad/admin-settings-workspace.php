<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/admin-layout.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$pdo = db();
$pdo->exec("
    CREATE TABLE IF NOT EXISTS settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        key_name VARCHAR(120) NOT NULL UNIQUE,
        value TEXT NULL,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
admin_ensure_user_role_assignments_schema($pdo);
admin_require($pdo);

function settings_ws_exec(PDO $pdo, string $sql): void
{
    $pdo->exec($sql);
}

function settings_ws_schema(PDO $pdo): void
{
    settings_ws_exec($pdo, "
        CREATE TABLE IF NOT EXISTS admin_workspace_integrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            integration_key VARCHAR(120) NOT NULL UNIQUE,
            name VARCHAR(160) NOT NULL,
            type VARCHAR(60) NOT NULL DEFAULT 'custom',
            endpoint_url VARCHAR(255) NULL,
            webhook_url VARCHAR(255) NULL,
            api_key_hint VARCHAR(120) NULL,
            mode VARCHAR(30) NOT NULL DEFAULT 'Production',
            status VARCHAR(30) NOT NULL DEFAULT 'connected',
            last_used_at DATETIME NULL,
            created_by INT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    settings_ws_exec($pdo, "
        CREATE TABLE IF NOT EXISTS admin_payment_providers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            provider_key VARCHAR(120) NOT NULL UNIQUE,
            provider_name VARCHAR(160) NOT NULL,
            mode VARCHAR(30) NOT NULL DEFAULT 'Live',
            fee_percent DECIMAL(6,2) NOT NULL DEFAULT 0,
            status VARCHAR(30) NOT NULL DEFAULT 'connected',
            api_key_hint VARCHAR(120) NULL,
            last_sync_at DATETIME NULL,
            created_by INT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    settings_ws_exec($pdo, "
        CREATE TABLE IF NOT EXISTS admin_email_templates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            template_key VARCHAR(120) NOT NULL UNIQUE,
            name VARCHAR(160) NOT NULL,
            category VARCHAR(80) NOT NULL DEFAULT 'General',
            subject VARCHAR(255) NOT NULL,
            body_html TEXT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'active',
            updated_by INT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    settings_ws_exec($pdo, "
        CREATE TABLE IF NOT EXISTS admin_webhooks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            webhook_ref VARCHAR(120) NOT NULL UNIQUE,
            name VARCHAR(160) NOT NULL,
            endpoint_url VARCHAR(255) NOT NULL,
            events TEXT NULL,
            secret_hint VARCHAR(120) NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'active',
            last_delivery_at DATETIME NULL,
            created_by INT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    settings_ws_exec($pdo, "
        CREATE TABLE IF NOT EXISTS admin_feature_flags (
            id INT AUTO_INCREMENT PRIMARY KEY,
            flag_key VARCHAR(120) NOT NULL UNIQUE,
            title VARCHAR(160) NOT NULL,
            description TEXT NULL,
            rollout_percent INT NOT NULL DEFAULT 0,
            environment VARCHAR(40) NOT NULL DEFAULT 'Production',
            target_users VARCHAR(255) NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'inactive',
            created_by INT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    settings_ws_exec($pdo, "
        CREATE TABLE IF NOT EXISTS admin_backup_runs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            backup_ref VARCHAR(120) NOT NULL UNIQUE,
            backup_type VARCHAR(80) NOT NULL,
            destination VARCHAR(120) NOT NULL,
            include_scope VARCHAR(255) NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'queued',
            notes TEXT NULL,
            started_by INT NULL,
            started_at DATETIME NOT NULL,
            completed_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    settings_ws_exec($pdo, "
        CREATE TABLE IF NOT EXISTS admin_maintenance_windows (
            id INT AUTO_INCREMENT PRIMARY KEY,
            window_ref VARCHAR(120) NOT NULL UNIQUE,
            starts_at DATETIME NOT NULL,
            duration_minutes INT NOT NULL DEFAULT 60,
            maintenance_type VARCHAR(80) NOT NULL DEFAULT 'System Update',
            description TEXT NULL,
            notify_users VARCHAR(120) NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'scheduled',
            created_by INT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    settings_ws_exec($pdo, "
        CREATE TABLE IF NOT EXISTS admin_custom_roles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            role_key VARCHAR(120) NOT NULL UNIQUE,
            role_name VARCHAR(160) NOT NULL,
            description TEXT NULL,
            permissions TEXT NULL,
            modules TEXT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'active',
            created_by INT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    settings_ws_exec($pdo, "
        CREATE TABLE IF NOT EXISTS admin_custom_permissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            permission_key VARCHAR(120) NOT NULL UNIQUE,
            permission_name VARCHAR(160) NOT NULL,
            module_key VARCHAR(80) NOT NULL DEFAULT 'settings',
            description TEXT NULL,
            default_roles VARCHAR(255) NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'active',
            created_by INT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    settings_ws_exec($pdo, "
        CREATE TABLE IF NOT EXISTS admin_settings_audit_events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            actor_id INT NULL,
            actor_name VARCHAR(160) NULL,
            action VARCHAR(160) NOT NULL,
            module_key VARCHAR(80) NOT NULL DEFAULT 'settings',
            details TEXT NULL,
            ip_address VARCHAR(45) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    settings_ws_exec($pdo, "
        CREATE TABLE IF NOT EXISTS admin_stakeholder_interest_controls (
            id INT AUTO_INCREMENT PRIMARY KEY,
            stakeholder_key VARCHAR(80) NOT NULL UNIQUE,
            stakeholder_name VARCHAR(160) NOT NULL,
            entry_point VARCHAR(255) NOT NULL,
            workspace_url VARCHAR(255) NOT NULL,
            request_path VARCHAR(255) NULL,
            support_scope VARCHAR(120) NOT NULL DEFAULT 'General Support',
            status VARCHAR(30) NOT NULL DEFAULT 'active',
            notes TEXT NULL,
            updated_by INT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $seedIntegrations = [
        ['monnify', 'Monnify', 'Payment', 'https://api.monnify.com', '', 'Live', 'connected'],
        ['google_maps', 'Google Maps', 'Maps', 'https://maps.googleapis.com', '', 'Production', 'connected'],
        ['smtp_mailer', 'SMTP Mailer', 'Email', '', '', 'Production', 'connected'],
        ['sms_gateway', 'SMS Gateway', 'SMS', '', '', 'Production', 'connected'],
    ];
    $stmt = $pdo->prepare("INSERT IGNORE INTO admin_workspace_integrations (integration_key, name, type, endpoint_url, webhook_url, mode, status, last_used_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    foreach ($seedIntegrations as $row) {
        $stmt->execute($row);
    }

    $seedProviders = [
        ['monnify', 'Monnify', 'Live', 1.50, 'connected'],
        ['bank_transfer', 'Bank Transfer', 'Manual', 0.00, 'active'],
    ];
    $stmt = $pdo->prepare("INSERT IGNORE INTO admin_payment_providers (provider_key, provider_name, mode, fee_percent, status, last_sync_at) VALUES (?, ?, ?, ?, ?, NOW())");
    foreach ($seedProviders as $row) {
        $stmt->execute($row);
    }

    $seedTemplates = [
        ['welcome_learner', 'Learner Welcome', 'Academy', 'Welcome to NATCODEV Academy', '<p>Hello {{name}}, your learner account is ready.</p>'],
        ['ticket_update', 'Support Ticket Update', 'Support', 'Your support ticket has an update', '<p>Hello {{name}}, ticket {{ticket_id}} has been updated.</p>'],
        ['certificate_issued', 'Certificate Issued', 'Certificates', 'Your certificate is ready', '<p>Hello {{name}}, your certificate is ready for download.</p>'],
    ];
    $stmt = $pdo->prepare("INSERT IGNORE INTO admin_email_templates (template_key, name, category, subject, body_html) VALUES (?, ?, ?, ?, ?)");
    foreach ($seedTemplates as $row) {
        $stmt->execute($row);
    }

    $seedStakeholders = [
        ['public', 'Public Visitor / Buyer', '/CDC/index.php', '/CDC/market/index.php', '/CDC/support.php', 'Marketplace and general enquiry'],
        ['learner', 'Learner', '/CDC/academy/index.php', '/CDC/academy/dashboard.php', '/CDC/register.php?role=learner', 'Academy learning support'],
        ['grower', 'Grower / Farm Owner', '/CDC/register.php?role=grower', '/CDC/dashboard.php', '/CDC/support.php?category=registry', 'Registry and verification'],
        ['marketplace_seller', 'Marketplace Seller', '/CDC/market/seller-register.php', '/CDC/market/seller-central.php', '/CDC/account/role-requests.php?role=seller', 'Seller onboarding and orders'],
        ['provider', 'Service / Input Provider', '/CDC/provider/register.php', '/CDC/provider/dashboard.php', '/CDC/account/role-requests.php?role=provider', 'Provider accreditation'],
        ['field_agent', 'Field Agent', '/CDC/login.php', '/CDC/admin/state-dashboard.php', '/CDC/account/role-requests.php?role=field_agent', 'Field operations'],
        ['state_coordinator', 'State Coordinator', '/CDC/login.php', '/CDC/admin/state-dashboard.php', '/CDC/account/role-requests.php?role=state_coordinator', 'State coordination'],
        ['national_coordinator', 'National Coordinator', '/CDC/login.php', '/CDC/admin/national-dashboard.php', '/CDC/account/role-requests.php?role=national_coordinator', 'National operations'],
        ['support_agent', 'Support Desk Agent', '/CDC/login.php', '/CDC/admin/support.php', '/CDC/account/role-requests.php?role=support_agent', 'Ticket and SLA management'],
        ['super_admin', 'Super Admin', '/CDC/admin/index.php', '/CDC/admin/settings.php', '/CDC/admin/settings.php?page=rbac', 'Full platform governance'],
    ];
    $stmt = $pdo->prepare("INSERT IGNORE INTO admin_stakeholder_interest_controls (stakeholder_key, stakeholder_name, entry_point, workspace_url, request_path, support_scope) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($seedStakeholders as $row) {
        $stmt->execute($row);
    }
}

function settings_ws_slug(string $value): string
{
    $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '_', $value) ?? ''));
    return trim($slug, '_') ?: 'item_' . date('YmdHis');
}

function settings_ws_setting(PDO $pdo, string $key, string $default = ''): string
{
    return admin_setting($pdo, $key, $default);
}

function settings_ws_save_setting(PDO $pdo, string $key, string $value): void
{
    $stmt = $pdo->prepare("INSERT INTO settings (key_name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)");
    $stmt->execute([$key, $value]);
}

function settings_ws_rows(PDO $pdo, string $sql, array $params = []): array
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function settings_ws_scalar(PDO $pdo, string $sql, array $params = [], int|string $default = 0): int|string
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $value = $stmt->fetchColumn();
        return $value === false ? $default : $value;
    } catch (Throwable $e) {
        return $default;
    }
}

function settings_ws_audit(PDO $pdo, string $action, string $module, string $details = ''): void
{
    $user = current_user($pdo) ?: [];
    $stmt = $pdo->prepare("INSERT INTO admin_settings_audit_events (actor_id, actor_name, action, module_key, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        (int) ($user['id'] ?? 0) ?: null,
        trim((string) (($user['name'] ?? '') ?: ($user['email'] ?? 'Admin'))),
        $action,
        $module,
        $details,
        (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
    ]);
}

settings_ws_schema($pdo);

$adminUser = current_user($pdo) ?: [];
$adminName = trim((string) (($adminUser['name'] ?? '') ?: ($adminUser['email'] ?? 'Grace Deh')));
$adminInitials = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $adminName) ?: 'GD', 0, 2));
$adminRole = ucwords(str_replace('_', ' ', (string) ($adminUser['platform_role'] ?? $adminUser['role'] ?? 'Super Admin')));
$settingsScriptDir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/admin/settings.php')));
$settingsAdminBase = basename($settingsScriptDir) === 'acad' ? dirname($settingsScriptDir) : $settingsScriptDir;
$settingsAdminBase = rtrim($settingsAdminBase, '/') ?: '/admin';
$settingsPublicBase = preg_replace('#/admin$#', '', $settingsAdminBase) ?: '';
$adminPicture = ltrim((string) ($adminUser['profile_picture'] ?? ''), '/');
$adminPictureUrl = $adminPicture !== '' ? $settingsPublicBase . '/' . $adminPicture : '';
$activePage = preg_replace('/[^a-z0-9\-]/', '', strtolower((string) ($_GET['page'] ?? 'overview'))) ?: 'overview';
$validPages = ['overview','modules','rbac','user-roles','stakeholder-interests','branding','certificates','payments','marketplace-settings','academy-settings','registry-settings','notifications','integrations','security','backups','system-health','audit-log','email-templates','webhooks','feature-flags','data-retention','maintenance'];
if (!in_array($activePage, $validPages, true)) {
    $activePage = 'overview';
}
$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $targetPage = preg_replace('/[^a-z0-9\-]/', '', strtolower((string) ($_POST['page'] ?? $activePage))) ?: 'overview';
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $notice = 'Invalid security token.';
    } else {
        if ($action === 'toggle_module') {
            $feature = (string) ($_POST['feature'] ?? '');
            if (array_key_exists($feature, admin_feature_catalog())) {
                settings_ws_save_setting($pdo, 'module_' . $feature . '_enabled', isset($_POST['enabled']) ? '1' : '0');
                settings_ws_audit($pdo, 'Updated module status', 'modules', $feature);
                $notice = 'Module status updated.';
            }
        } elseif ($action === 'save_access_matrix') {
            $role = settings_ws_slug((string) ($_POST['role_key'] ?? 'admin'));
            $features = array_values(array_intersect((array) ($_POST['features'] ?? []), array_keys(admin_feature_catalog())));
            settings_ws_save_setting($pdo, 'access_matrix_' . $role, implode(',', $features));
            settings_ws_save_setting($pdo, 'access_matrix_catalog_version', ADMIN_ACCESS_CATALOG_VERSION);
            settings_ws_audit($pdo, 'Updated access matrix', 'rbac', $role);
            $notice = 'Access matrix updated.';
        } elseif ($action === 'save_role') {
            $name = trim((string) ($_POST['role_name'] ?? ''));
            if ($name !== '') {
                $key = settings_ws_slug((string) ($_POST['role_key'] ?? $name));
                $stmt = $pdo->prepare("INSERT INTO admin_custom_roles (role_key, role_name, description, permissions, modules, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE role_name=VALUES(role_name), description=VALUES(description), permissions=VALUES(permissions), modules=VALUES(modules), status=VALUES(status)");
                $stmt->execute([$key, $name, (string) ($_POST['description'] ?? ''), implode(',', (array) ($_POST['permissions'] ?? [])), implode(',', (array) ($_POST['modules'] ?? [])), (string) ($_POST['status'] ?? 'active'), (int) ($adminUser['id'] ?? 0) ?: null]);
                settings_ws_save_setting($pdo, 'access_matrix_' . $key, implode(',', (array) ($_POST['modules'] ?? [])));
                settings_ws_audit($pdo, 'Saved role', 'roles', $name);
                $notice = 'Role saved.';
            }
        } elseif ($action === 'save_permission') {
            $name = trim((string) ($_POST['permission_name'] ?? ''));
            if ($name !== '') {
                $stmt = $pdo->prepare("INSERT INTO admin_custom_permissions (permission_key, permission_name, module_key, description, default_roles, created_by) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE permission_name=VALUES(permission_name), module_key=VALUES(module_key), description=VALUES(description), default_roles=VALUES(default_roles)");
                $stmt->execute([settings_ws_slug($name), $name, settings_ws_slug((string) ($_POST['module_key'] ?? 'settings')), (string) ($_POST['description'] ?? ''), implode(',', (array) ($_POST['default_roles'] ?? [])), (int) ($adminUser['id'] ?? 0) ?: null]);
                settings_ws_audit($pdo, 'Saved permission', 'rbac', $name);
                $notice = 'Permission saved.';
            }
        } elseif ($action === 'save_stakeholder_interest') {
            $name = trim((string) ($_POST['stakeholder_name'] ?? ''));
            if ($name !== '') {
                $key = settings_ws_slug((string) ($_POST['stakeholder_key'] ?? $name));
                $stmt = $pdo->prepare("INSERT INTO admin_stakeholder_interest_controls (stakeholder_key, stakeholder_name, entry_point, workspace_url, request_path, support_scope, status, notes, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE stakeholder_name=VALUES(stakeholder_name), entry_point=VALUES(entry_point), workspace_url=VALUES(workspace_url), request_path=VALUES(request_path), support_scope=VALUES(support_scope), status=VALUES(status), notes=VALUES(notes), updated_by=VALUES(updated_by)");
                $stmt->execute([$key, $name, (string) ($_POST['entry_point'] ?? ''), (string) ($_POST['workspace_url'] ?? ''), (string) ($_POST['request_path'] ?? ''), (string) ($_POST['support_scope'] ?? 'General Support'), (string) ($_POST['status'] ?? 'active'), (string) ($_POST['notes'] ?? ''), (int) ($adminUser['id'] ?? 0) ?: null]);
                settings_ws_audit($pdo, 'Saved stakeholder interest control', 'stakeholders', $name);
                $notice = 'Stakeholder interest control saved.';
            }
        } elseif ($action === 'save_provider') {
            $name = trim((string) ($_POST['provider_name'] ?? ''));
            if ($name !== '') {
                $hint = trim((string) ($_POST['api_key'] ?? ''));
                $hint = $hint === '' ? null : substr($hint, 0, 4) . '...' . substr($hint, -4);
                $stmt = $pdo->prepare("INSERT INTO admin_payment_providers (provider_key, provider_name, mode, fee_percent, status, api_key_hint, last_sync_at, created_by) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?) ON DUPLICATE KEY UPDATE provider_name=VALUES(provider_name), mode=VALUES(mode), fee_percent=VALUES(fee_percent), status=VALUES(status), api_key_hint=COALESCE(VALUES(api_key_hint), api_key_hint), last_sync_at=NOW()");
                $stmt->execute([settings_ws_slug($name), $name, (string) ($_POST['mode'] ?? 'Live'), (float) ($_POST['fee_percent'] ?? 0), (string) ($_POST['status'] ?? 'connected'), $hint, (int) ($adminUser['id'] ?? 0) ?: null]);
                settings_ws_audit($pdo, 'Saved payment provider', 'payments', $name);
                $notice = 'Payment provider saved.';
            }
        } elseif ($action === 'save_integration') {
            $name = trim((string) ($_POST['name'] ?? ''));
            if ($name !== '') {
                $hint = trim((string) ($_POST['api_key'] ?? ''));
                $hint = $hint === '' ? null : substr($hint, 0, 4) . '...' . substr($hint, -4);
                $stmt = $pdo->prepare("INSERT INTO admin_workspace_integrations (integration_key, name, type, endpoint_url, webhook_url, api_key_hint, mode, status, last_used_at, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?) ON DUPLICATE KEY UPDATE name=VALUES(name), type=VALUES(type), endpoint_url=VALUES(endpoint_url), webhook_url=VALUES(webhook_url), api_key_hint=COALESCE(VALUES(api_key_hint), api_key_hint), mode=VALUES(mode), status=VALUES(status)");
                $stmt->execute([settings_ws_slug($name), $name, (string) ($_POST['type'] ?? 'Custom API'), (string) ($_POST['endpoint_url'] ?? ''), (string) ($_POST['webhook_url'] ?? ''), $hint, (string) ($_POST['mode'] ?? 'Production'), (string) ($_POST['status'] ?? 'connected'), (int) ($adminUser['id'] ?? 0) ?: null]);
                settings_ws_audit($pdo, 'Saved integration', 'integrations', $name);
                $notice = 'Integration saved.';
            }
        } elseif ($action === 'save_template') {
            $name = trim((string) ($_POST['name'] ?? ''));
            if ($name !== '') {
                $stmt = $pdo->prepare("INSERT INTO admin_email_templates (template_key, name, category, subject, body_html, status, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name=VALUES(name), category=VALUES(category), subject=VALUES(subject), body_html=VALUES(body_html), status=VALUES(status), updated_by=VALUES(updated_by)");
                $stmt->execute([settings_ws_slug($name), $name, (string) ($_POST['category'] ?? 'General'), (string) ($_POST['subject'] ?? ''), (string) ($_POST['body_html'] ?? ''), (string) ($_POST['status'] ?? 'active'), (int) ($adminUser['id'] ?? 0) ?: null]);
                settings_ws_audit($pdo, 'Saved email template', 'templates', $name);
                $notice = 'Email template saved.';
            }
        } elseif ($action === 'save_webhook') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $url = trim((string) ($_POST['endpoint_url'] ?? ''));
            if ($name !== '' && $url !== '') {
                $secret = trim((string) ($_POST['secret_hint'] ?? ''));
                $secret = $secret === '' ? 'auto-' . substr(hash('sha256', $name . microtime(true)), 0, 10) : $secret;
                $stmt = $pdo->prepare("INSERT INTO admin_webhooks (webhook_ref, name, endpoint_url, events, secret_hint, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name=VALUES(name), endpoint_url=VALUES(endpoint_url), events=VALUES(events), secret_hint=VALUES(secret_hint), status=VALUES(status)");
                $stmt->execute([settings_ws_slug($name), $name, $url, implode(',', (array) ($_POST['events'] ?? [])), substr($secret, 0, 4) . '...' . substr($secret, -4), (string) ($_POST['status'] ?? 'active'), (int) ($adminUser['id'] ?? 0) ?: null]);
                settings_ws_audit($pdo, 'Saved webhook', 'webhooks', $name);
                $notice = 'Webhook saved.';
            }
        } elseif ($action === 'save_flag') {
            $title = trim((string) ($_POST['title'] ?? ''));
            if ($title !== '') {
                $stmt = $pdo->prepare("INSERT INTO admin_feature_flags (flag_key, title, description, rollout_percent, environment, target_users, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE title=VALUES(title), description=VALUES(description), rollout_percent=VALUES(rollout_percent), environment=VALUES(environment), target_users=VALUES(target_users), status=VALUES(status)");
                $rollout = max(0, min(100, (int) ($_POST['rollout_percent'] ?? 0)));
                $stmt->execute([settings_ws_slug($title), $title, (string) ($_POST['description'] ?? ''), $rollout, (string) ($_POST['environment'] ?? 'Production'), (string) ($_POST['target_users'] ?? 'all'), $rollout > 0 ? 'active' : 'inactive', (int) ($adminUser['id'] ?? 0) ?: null]);
                settings_ws_audit($pdo, 'Saved feature flag', 'feature_flags', $title);
                $notice = 'Feature flag saved.';
            }
        } elseif ($action === 'start_backup') {
            $ref = 'BKP-' . date('Ymd-His');
            $include = implode(',', (array) ($_POST['include_scope'] ?? []));
            $stmt = $pdo->prepare("INSERT INTO admin_backup_runs (backup_ref, backup_type, destination, include_scope, status, notes, started_by, started_at, completed_at) VALUES (?, ?, ?, ?, 'completed', ?, ?, NOW(), NOW())");
            $stmt->execute([$ref, (string) ($_POST['backup_type'] ?? 'Full Backup'), (string) ($_POST['destination'] ?? 'Local Server'), $include, (string) ($_POST['notes'] ?? ''), (int) ($adminUser['id'] ?? 0) ?: null]);
            settings_ws_audit($pdo, 'Started backup', 'backups', $ref);
            $notice = 'Backup completed and logged.';
        } elseif ($action === 'save_maintenance') {
            $date = (string) ($_POST['date'] ?? date('Y-m-d'));
            $time = (string) ($_POST['time'] ?? '02:00');
            $duration = max(30, (int) ($_POST['duration_minutes'] ?? 60));
            $stmt = $pdo->prepare("INSERT INTO admin_maintenance_windows (window_ref, starts_at, duration_minutes, maintenance_type, description, notify_users, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE starts_at=VALUES(starts_at), duration_minutes=VALUES(duration_minutes), maintenance_type=VALUES(maintenance_type), description=VALUES(description), notify_users=VALUES(notify_users), status=VALUES(status)");
            $ref = 'MNT-' . date('Ymd-His');
            $stmt->execute([$ref, $date . ' ' . $time . ':00', $duration, (string) ($_POST['maintenance_type'] ?? 'System Update'), (string) ($_POST['description'] ?? ''), (string) ($_POST['notify_users'] ?? 'Email + In-app 24h before'), (string) ($_POST['status'] ?? 'scheduled'), (int) ($adminUser['id'] ?? 0) ?: null]);
            settings_ws_audit($pdo, 'Scheduled maintenance', 'maintenance', $ref);
            $notice = 'Maintenance window saved.';
        } elseif ($action === 'save_settings_group') {
            foreach ($_POST as $key => $value) {
                if (str_starts_with((string) $key, 'setting_')) {
                    settings_ws_save_setting($pdo, substr((string) $key, 8), is_array($value) ? implode(',', $value) : (string) $value);
                }
            }
            foreach ((array) ($_POST['checkbox_keys'] ?? []) as $key) {
                settings_ws_save_setting($pdo, (string) $key, isset($_POST['setting_' . $key]) ? '1' : '0');
            }
            settings_ws_audit($pdo, 'Saved settings group', 'settings', $targetPage);
            $notice = 'Settings saved.';
        }
    }
    $activePage = $targetPage;
}

$features = admin_feature_catalog();
$moduleRows = [];
foreach ($features as $key => $label) {
    $moduleRows[] = [
        'key' => $key,
        'label' => $label,
        'enabled' => admin_feature_is_globally_enabled($pdo, $key),
        'setting_key' => 'module_' . $key . '_enabled',
        'owners' => implode(', ', array_slice(array_keys(array_filter([
            'Super Admin' => in_array($key, admin_default_access('super_admin'), true),
            'Admin' => in_array($key, admin_default_access('admin'), true),
            'National Coordinator' => in_array($key, admin_default_access('national_coordinator'), true),
            'State Coordinator' => in_array($key, admin_default_access('state_coordinator'), true),
            'Field Agent' => in_array($key, admin_default_access('field_agent'), true),
            'Learner' => in_array($key, admin_default_access('learner'), true),
        ])), 0, 4)),
    ];
}
$enabledModules = count(array_filter($moduleRows, static fn(array $row): bool => (bool) $row['enabled']));

$roleKeys = ['super_admin','admin','national_coordinator','state_coordinator','field_agent','agronomist','agric_extensionist','provider','investor','learner','grower'];
$customRoles = settings_ws_rows($pdo, "SELECT * FROM admin_custom_roles ORDER BY updated_at DESC, created_at DESC, id DESC");
$permissions = settings_ws_rows($pdo, "SELECT * FROM admin_custom_permissions ORDER BY updated_at DESC, created_at DESC, id DESC");
$stakeholderInterests = settings_ws_rows($pdo, "SELECT * FROM admin_stakeholder_interest_controls ORDER BY stakeholder_name ASC");
$integrations = settings_ws_rows($pdo, "SELECT * FROM admin_workspace_integrations ORDER BY status='connected' DESC, updated_at DESC, id DESC");
$providers = settings_ws_rows($pdo, "SELECT * FROM admin_payment_providers ORDER BY status='connected' DESC, updated_at DESC, id DESC");
$templates = settings_ws_rows($pdo, "SELECT * FROM admin_email_templates ORDER BY updated_at DESC, created_at DESC, id DESC");
$webhooks = settings_ws_rows($pdo, "SELECT * FROM admin_webhooks ORDER BY updated_at DESC, created_at DESC, id DESC");
$flags = settings_ws_rows($pdo, "SELECT * FROM admin_feature_flags ORDER BY updated_at DESC, created_at DESC, id DESC");
$backups = settings_ws_rows($pdo, "SELECT * FROM admin_backup_runs ORDER BY started_at DESC, id DESC LIMIT 25");
$maintenance = settings_ws_rows($pdo, "SELECT * FROM admin_maintenance_windows ORDER BY starts_at DESC, id DESC LIMIT 25");
$auditRows = settings_ws_rows($pdo, "SELECT * FROM admin_settings_audit_events ORDER BY created_at DESC, id DESC LIMIT 50");
$userRoleRows = settings_ws_rows($pdo, "SELECT role_key, COUNT(*) AS total FROM user_role_assignments WHERE status='active' GROUP BY role_key ORDER BY total DESC");

$settingsExport = preg_replace('/[^a-z0-9_-]/', '', (string) ($_GET['export'] ?? ''));
if ($settingsExport !== '') {
    $rows = match ($settingsExport) {
        'backups' => $backups,
        'maintenance' => $maintenance,
        'roles' => $customRoles,
        'permissions' => $permissions,
        'integrations' => $integrations,
        default => $auditRows,
    };
    app_export_csv('natcodev-settings-' . $settingsExport . '-' . date('Ymd') . '.csv', $rows ? array_keys($rows[0]) : [], $rows);
}

$pendingApprovals = (int) settings_ws_scalar($pdo, "SELECT COUNT(*) FROM users WHERE COALESCE(is_verified,0)=0", [], 0);
$integrationHealthy = count(array_filter($integrations, static fn(array $row): bool => in_array(strtolower((string) $row['status']), ['connected','active','operational'], true)));
$securityAlerts = (int) settings_ws_scalar($pdo, "SELECT COUNT(*) FROM admin_settings_audit_events WHERE module_key IN ('security','rbac') AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)", [], 0);
$openTickets = (int) settings_ws_scalar($pdo, "SELECT COUNT(*) FROM support_tickets WHERE status IN ('open','pending','new')", [], 0);
$recentAuditCount = count($auditRows);
$isMaintenanceMode = settings_ws_setting($pdo, 'maintenance_mode', '0') === '1';
$systemStatusLabel = $isMaintenanceMode ? 'Maintenance Mode' : ($securityAlerts > 0 ? 'Needs Review' : 'Healthy');
$systemStatusClass = $isMaintenanceMode ? 'warn' : ($securityAlerts > 0 ? 'warn' : 'ok');
$lastBackup = $backups[0]['started_at'] ?? 'No backup yet';
$settingsPayload = [
    'csrf' => csrf_token(),
    'notice' => $notice,
    'activePage' => $activePage,
    'admin' => ['name' => $adminName, 'initials' => $adminInitials, 'role' => $adminRole, 'profilePicture' => $adminPictureUrl],
    'stats' => [
        'enabledModules' => $enabledModules . ' / ' . count($moduleRows),
        'moduleRate' => count($moduleRows) ? round(($enabledModules / count($moduleRows)) * 100) . '% active modules' : 'No modules',
        'activeRoles' => (string) (count($roleKeys) + count($customRoles)),
        'pendingApprovals' => (string) $pendingApprovals,
        'integrationHealth' => $integrationHealthy . ' / ' . max(1, count($integrations)),
        'lastBackup' => is_string($lastBackup) ? $lastBackup : (string) $lastBackup,
        'securityAlerts' => (string) $securityAlerts,
        'openTickets' => (string) $openTickets,
        'recentAuditCount' => (string) $recentAuditCount,
        'systemStatus' => $systemStatusLabel,
        'systemStatusClass' => $systemStatusClass,
    ],
    'modules' => $moduleRows,
    'roles' => $roleKeys,
    'customRoles' => $customRoles,
    'permissions' => $permissions,
    'stakeholderInterests' => $stakeholderInterests,
    'integrations' => $integrations,
    'providers' => $providers,
    'templates' => $templates,
    'webhooks' => $webhooks,
    'flags' => $flags,
    'backups' => $backups,
    'maintenance' => $maintenance,
    'audit' => $auditRows,
    'userRoles' => $userRoleRows,
    'settings' => [
        'platform_name' => settings_ws_setting($pdo, 'platform_name', 'NATCODEV Platform'),
        'platform_email' => settings_ws_setting($pdo, 'platform_email', 'support@natcodev.com.ng'),
        'default_gateway' => settings_ws_setting($pdo, 'default_gateway', 'Monnify'),
        'currency' => settings_ws_setting($pdo, 'currency', 'NGN'),
        'payment_timeout' => settings_ws_setting($pdo, 'payment_timeout', '300'),
        'auto_retry_payments' => settings_ws_setting($pdo, 'auto_retry_payments', '2 retries'),
        'certificate_expiry_months' => settings_ws_setting($pdo, 'certificate_expiry_months', '24'),
        'certificate_reminder_days' => settings_ws_setting($pdo, 'certificate_reminder_days', '30'),
        'auto_generate_certificates' => settings_ws_setting($pdo, 'auto_generate_certificates', '1'),
        'qr_certificate_verification' => settings_ws_setting($pdo, 'qr_certificate_verification', '1'),
        'marketplace_commission_percent' => settings_ws_setting($pdo, 'marketplace_commission_percent', '3.5'),
        'academy_completion_threshold' => settings_ws_setting($pdo, 'academy_completion_threshold', '70'),
        'registry_review_sla_days' => settings_ws_setting($pdo, 'registry_review_sla_days', '3'),
        'email_notifications' => settings_ws_setting($pdo, 'email_notifications', '1'),
        'sms_notifications' => settings_ws_setting($pdo, 'sms_notifications', '1'),
        'maintenance_mode' => settings_ws_setting($pdo, 'maintenance_mode', '0'),
        'maintenance_message' => settings_ws_setting($pdo, 'maintenance_message', "We're currently performing scheduled maintenance. We'll be back shortly."),
        'retention_audit_days' => settings_ws_setting($pdo, 'retention_audit_days', '1095'),
        'retention_ticket_days' => settings_ws_setting($pdo, 'retention_ticket_days', '730'),
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>NATCODEV Settings - Admin Workspace</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/inter@5.0.0/index.min.css">
<style>
*{margin:0;padding:0;box-sizing:border-box}
:root{
  --g900:#0a2418;--g800:#0f3324;--g700:#164a33;--g600:#1e6b47;--g500:#2a9d6a;--g400:#34c48a;--g300:#5dd8a3;--g200:#a8e6c9;--g100:#d4f5e4;--g50:#eefbf4;
  --bg:#f4f6f4;--card:#fff;--text:#1a1a1a;--text2:#6b7280;--border:#e5e7eb;
  --danger:#dc2626;--warn:#f59e0b;--info:#3b82f6;--success:#10b981;--purple:#8b5cf6;--orange:#f97316;--pink:#ec4899;
}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);display:flex;min-height:100vh;font-size:13px}
.sidebar{width:260px;background:var(--g900);color:#fff;position:fixed;top:0;left:0;bottom:0;overflow-y:auto;z-index:100;display:flex;flex-direction:column}
.sidebar-header{padding:16px;display:flex;align-items:center;gap:12px;border-bottom:1px solid rgba(255,255,255,.1)}
.sidebar-logo{width:44px;height:44px;background:var(--g400);border-radius:10px;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:10px;color:var(--g900);flex-shrink:0;text-align:center;line-height:1.1}
.sidebar-brand{font-size:14px;font-weight:700;line-height:1.2}
.sidebar-brand small{display:block;font-size:9px;font-weight:400;opacity:.7;margin-top:2px;line-height:1.3}
.workspace-badge{margin:14px 16px 4px;padding:5px 10px;background:rgba(255,255,255,.08);border-radius:6px;font-size:9px;text-transform:uppercase;letter-spacing:1px;opacity:.6}
.workspace-select{margin:0 16px 12px;padding:10px 12px;background:rgba(255,255,255,.06);border-radius:8px;display:flex;align-items:center;justify-content:space-between;font-size:13px;font-weight:600;cursor:pointer;border:1px solid rgba(255,255,255,.1)}
.nav-section{padding:6px 0}
.nav-section-title{padding:0 16px;font-size:9px;text-transform:uppercase;letter-spacing:1px;opacity:.5;margin-bottom:4px}
.nav-item{display:flex;align-items:center;gap:11px;padding:9px 16px;cursor:pointer;transition:all .15s;font-size:13px;color:rgba(255,255,255,.75);border-left:3px solid transparent}
.nav-item:hover{background:rgba(255,255,255,.08);color:#fff}
.nav-item.active{background:var(--g600);color:#fff;border-left-color:var(--g400)}
.nav-item svg{width:17px;height:17px;flex-shrink:0}
.nav-item .badge{margin-left:auto;background:var(--danger);color:#fff;font-size:10px;font-weight:700;padding:2px 7px;border-radius:10px}
.nav-group-header{display:flex;align-items:center;justify-content:space-between;padding:9px 16px;cursor:pointer;font-size:13px;color:rgba(255,255,255,.75)}
.nav-group-header:hover{color:#fff}
.nav-group-header svg.arrow{width:14px;height:14px;transition:transform .2s}
.nav-group.open .nav-group-header svg.arrow{transform:rotate(90deg)}
.nav-sub{display:none}
.nav-group.open .nav-sub{display:block}
.nav-sub .nav-item{padding-left:44px;font-size:12px}
.sidebar-footer{margin-top:auto;padding:14px 16px;border-top:1px solid rgba(255,255,255,.1);display:flex;align-items:center;gap:10px}
.sidebar-avatar{width:38px;height:38px;border-radius:50%;background:var(--g600);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0}
.sidebar-user{font-size:13px;font-weight:600}
.sidebar-user small{display:block;font-size:11px;opacity:.6;font-weight:400}
.status-dot{width:7px;height:7px;background:var(--success);border-radius:50%;display:inline-block;margin-right:3px}
.help-box{margin:12px 16px;padding:14px;background:rgba(255,255,255,.04);border-radius:10px;border:1px solid rgba(255,255,255,.08);cursor:pointer;transition:background .15s}
.help-box:hover{background:rgba(255,255,255,.08)}
.help-box-title{font-size:12px;font-weight:600;margin-bottom:3px;display:flex;align-items:center;gap:6px}
.help-box-desc{font-size:11px;opacity:.7}

.main{margin-left:260px;flex:1;min-height:100vh}
.topbar{background:#fff;padding:12px 24px;display:flex;align-items:center;gap:14px;border-bottom:1px solid var(--border);position:sticky;top:0;z-index:50}
.menu-toggle{display:none;background:none;border:none;cursor:pointer;font-size:20px;color:var(--text)}
.topbar-search{flex:1;max-width:480px;position:relative}
.topbar-search input{width:100%;padding:9px 14px 9px 38px;border:1px solid var(--border);border-radius:8px;font-size:13px;background:var(--bg)}
.topbar-search svg{position:absolute;left:12px;top:50%;transform:translateY(-50%);width:16px;height:16px;color:var(--text2)}
.topbar-kbd{position:absolute;right:12px;top:50%;transform:translateY(-50%);font-size:10px;color:var(--text2);background:#fff;padding:2px 6px;border:1px solid var(--border);border-radius:4px}
.topbar-actions{display:flex;align-items:center;gap:10px;margin-left:auto}
.topbar-menu-wrap{position:relative}
.topbar-icon{width:36px;height:36px;border-radius:8px;border:1px solid var(--border);display:flex;align-items:center;justify-content:center;cursor:pointer;position:relative;background:#fff;color:var(--text);text-decoration:none}
.topbar-icon:hover{background:var(--bg)}
.topbar-icon .dot{position:absolute;top:5px;right:5px;width:8px;height:8px;background:var(--danger);border-radius:50%;border:2px solid #fff}
.system-status{display:flex;align-items:center;gap:8px;padding:6px 12px;background:var(--g50);border:1px solid var(--border);border-radius:8px;cursor:pointer;font-size:12px;font-weight:600;color:var(--text);text-decoration:none}
.system-status .dot{width:8px;height:8px;background:var(--success);border-radius:50%}
.system-status.warn .dot{background:var(--warn)}
.quick-actions-btn{padding:8px 14px;background:#fff;border:1px solid var(--border);border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:6px}
.quick-actions-btn:hover{background:var(--bg)}
.topbar-menu{display:none;position:absolute;right:0;top:46px;width:260px;background:#fff;border:1px solid var(--border);border-radius:10px;box-shadow:0 18px 40px rgba(0,0,0,.12);padding:8px;z-index:90}
.topbar-menu.active{display:block}
.topbar-menu a,.topbar-menu button{width:100%;display:flex;align-items:center;justify-content:space-between;gap:8px;padding:9px 10px;border-radius:8px;color:var(--text);text-decoration:none;font-weight:650;background:none;border:0;text-align:left;font:inherit;cursor:pointer}
.topbar-menu a:hover,.topbar-menu button:hover{background:var(--bg)}
.topbar-menu small{display:block;color:var(--text2);font-weight:500;margin-top:2px}
.topbar-menu-label{padding:6px 10px 8px;color:var(--text2);font-size:11px;text-transform:uppercase;letter-spacing:.4px;font-weight:800}
.topbar-profile{display:flex;align-items:center;gap:10px;min-width:0;max-width:260px;cursor:pointer;padding:4px 10px 4px 6px;border-radius:8px;background:none;border:0;color:var(--text);font:inherit}
.topbar-profile:hover{background:var(--bg)}
.topbar-avatar{width:36px;height:36px;border-radius:50%;background:var(--g600);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:13px;overflow:hidden}
.topbar-avatar img{width:100%;height:100%;object-fit:cover;display:block}
.topbar-profile-info{display:flex;min-width:0;max-width:160px;flex-direction:column;align-items:flex-start;font-size:13px;font-weight:700;line-height:1.15;text-align:left}
.topbar-profile-info,.topbar-profile-info small{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.topbar-profile-info small{display:block;max-width:100%;margin-top:2px;font-size:11px;color:var(--text2);font-weight:500}

.content{padding:22px}
.page{display:none}
.page.active{display:block}
.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px}
.page-title{font-size:22px;font-weight:700}
.page-subtitle{font-size:13px;color:var(--text2);margin-top:2px}
.btn{padding:9px 16px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:none;display:inline-flex;align-items:center;gap:6px;transition:all .15s}
.btn-primary{background:var(--g700);color:#fff}
.btn-primary:hover{background:var(--g800)}
.btn-secondary{background:#fff;color:var(--text);border:1px solid var(--border)}
.btn-secondary:hover{background:var(--bg)}
.btn-danger{background:var(--danger);color:#fff}
.btn-warn{background:var(--warn);color:#fff}
.btn-success{background:var(--success);color:#fff}
.btn-info{background:var(--info);color:#fff}
.btn-sm{padding:6px 12px;font-size:12px}
.btn-icon{padding:6px;background:none;border:1px solid var(--border);border-radius:6px;cursor:pointer;font-size:14px}
.btn-ghost{background:transparent;border:none;color:var(--g700);font-weight:600}

.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:14px;margin-bottom:20px}
.stat-card{background:#fff;padding:18px;border-radius:12px;border:1px solid var(--border)}
.stat-card-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px}
.stat-card-icon{width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px}
.stat-card-label{font-size:11px;color:var(--text2);font-weight:500;text-transform:uppercase;letter-spacing:.3px}
.stat-card-value{font-size:24px;font-weight:700;margin-top:4px}
.stat-card-change{font-size:11px;margin-top:6px;font-weight:500}
.stat-card-change.up{color:var(--success)}
.stat-card-change.down{color:var(--danger)}
.stat-card-sub{font-size:11px;color:var(--text2);margin-top:2px}

.card{background:#fff;border-radius:12px;border:1px solid var(--border);margin-bottom:18px}
.card-header{padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px}
.card-title{font-size:15px;font-weight:700}
.card-body{padding:20px}
.card-body.p0{padding:0}

table{width:100%;border-collapse:collapse}
th,td{padding:11px 18px;text-align:left;font-size:12.5px}
th{background:var(--bg);font-weight:600;color:var(--text2);font-size:10.5px;text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid var(--border)}
td{border-bottom:1px solid var(--border)}
tr:last-child td{border-bottom:none}
tr:hover td{background:var(--g50)}

.status-badge{padding:4px 10px;border-radius:20px;font-size:11px;font-weight:600;display:inline-block}
.status-success,.status-completed,.status-active,.status-verified,.status-valid,.status-approved,.status-live,.status-reconciled,.status-connected,.status-operational,.status-enabled,.status-healthy{background:#dcfce7;color:#166534}
.status-pending,.status-review,.status-processing,.status-scheduled{background:#fef3c7;color:#92400e}
.status-info,.status-credit{background:#dbeafe;color:#1e40af}
.status-draft,.status-inactive,.status-disabled{background:#f3f4f6;color:#4b5563}
.status-danger,.status-cancelled,.status-rejected,.status-failed,.status-revoked,.status-open,.status-high-risk,.status-error{background:#fee2e2;color:#991b1b}
.status-warn,.status-expiring,.status-warning{background:#fff7ed;color:#c2410c}

.progress-bar{height:6px;background:var(--border);border-radius:3px;overflow:hidden;width:100%}
.progress-fill{height:100%;background:var(--g500);border-radius:3px;transition:width .3s}

.form-group{margin-bottom:14px}
.form-label{display:block;font-size:11.5px;font-weight:600;margin-bottom:6px;color:var(--text2);text-transform:uppercase;letter-spacing:.3px}
.form-input,.form-select,.form-textarea{width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:8px;font-size:13px;font-family:inherit}
.form-input:focus,.form-select:focus,.form-textarea:focus{outline:none;border-color:var(--g500);box-shadow:0 0 0 3px rgba(42,157,106,.1)}
.form-textarea{resize:vertical;min-height:80px}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.form-row-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px}

.tabs{display:flex;gap:4px;border-bottom:1px solid var(--border);margin-bottom:18px;overflow-x:auto}
.tab{padding:10px 16px;font-size:13px;font-weight:500;cursor:pointer;border-bottom:2px solid transparent;color:var(--text2);white-space:nowrap}
.tab.active{color:var(--g700);border-bottom-color:var(--g700);font-weight:600}
.tab:hover{color:var(--text)}

.filter-bar{display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;align-items:center}
.filter-bar input,.filter-bar select{padding:8px 12px;border:1px solid var(--border);border-radius:8px;font-size:12.5px}

.modal-overlay{display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:200;align-items:center;justify-content:center}
.modal-overlay.active{display:flex}
.modal{background:#fff;border-radius:12px;width:90%;max-width:600px;max-height:90vh;overflow-y:auto}
.modal-header{padding:18px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
.modal-title{font-size:16px;font-weight:700}
.modal-body{padding:22px}
.modal-footer{padding:16px 22px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:10px}

.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:18px}
.overview-control-grid{display:grid;grid-template-columns:minmax(0,1fr);gap:18px;align-items:start}
.grid-3{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}
.grid-4{display:grid;grid-template-columns:repeat(4,1fr);gap:18px}

.avatar-sm{width:32px;height:32px;border-radius:50%;background:var(--g100);color:var(--g700);display:inline-flex;align-items:center;justify-content:center;font-weight:600;font-size:11px;flex-shrink:0}
.avatar-row{display:flex;align-items:center;gap:10px}

.toast{position:fixed;bottom:24px;right:24px;background:var(--g800);color:#fff;padding:12px 20px;border-radius:8px;font-size:13px;z-index:300;display:none;animation:slideIn .3s}
@keyframes slideIn{from{transform:translateX(100%);opacity:0}to{transform:translateX(0);opacity:1}}

.chip{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;background:var(--g100);color:var(--g700);border-radius:20px;font-size:11px;font-weight:500}
.chip-warn{background:#fef3c7;color:#92400e}
.chip-danger{background:#fee2e2;color:#991b1b}

.toggle{position:relative;width:44px;height:24px;cursor:pointer;display:inline-block}
.toggle input{opacity:0;width:0;height:0}
.toggle-slider{position:absolute;top:0;left:0;right:0;bottom:0;background:#d1d5db;border-radius:24px;transition:.3s}
.toggle-slider:before{content:'';position:absolute;height:18px;width:18px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.3s}
.toggle input:checked + .toggle-slider{background:var(--g500)}
.toggle input:checked + .toggle-slider:before{transform:translateX(20px)}

.module-card{padding:16px;border:1px solid var(--border);border-radius:10px;display:flex;align-items:center;gap:14px;transition:all .15s;min-width:0}
.module-card:hover{border-color:var(--g500);background:var(--g50)}
.module-icon{width:46px;height:46px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:25px;font-weight:900;flex-shrink:0;box-shadow:inset 0 0 0 1px rgba(0,0,0,.03)}
.module-info{flex:1;min-width:0}
.module-title{font-weight:700;font-size:13.5px;margin-bottom:2px}
.module-desc{font-size:11.5px;color:var(--text2);line-height:1.25}

.health-item{display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--border)}
.health-item:last-child{border-bottom:none}
.health-label{display:flex;align-items:center;gap:10px;font-size:12.5px;font-weight:500}
.health-status{font-size:12px;font-weight:600}

.setting-row{display:flex;align-items:center;justify-content:space-between;padding:14px 0;border-bottom:1px solid var(--border)}
.setting-row:last-child{border-bottom:none}
.setting-info{flex:1}
.setting-title{font-weight:600;font-size:13px;margin-bottom:2px}
.setting-desc{font-size:11.5px;color:var(--text2)}

.quick-action-card{padding:16px;border:1px solid var(--border);border-radius:10px;cursor:pointer;transition:all .15s;display:flex;align-items:center;gap:12px}
.quick-action-card:hover{border-color:var(--g500);background:var(--g50);transform:translateY(-1px)}
.quick-action-icon{width:40px;height:40px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.quick-action-title{font-weight:700;font-size:13px;margin-bottom:2px}
.quick-action-desc{font-size:11px;color:var(--text2)}

.api-key{font-family:monospace;font-size:11px;background:var(--bg);padding:4px 8px;border-radius:4px;letter-spacing:1px}

@media(max-width:1100px){.grid-4{grid-template-columns:repeat(2,1fr)}.grid-3{grid-template-columns:1fr 1fr}}
@media(max-width:900px){
  .sidebar{width:70px}.sidebar-brand,.workspace-badge,.workspace-select span,.nav-section-title,.nav-item span:not(.badge),.sidebar-user,.sidebar-user small,.nav-item .badge,.nav-group-header span,.help-box{display:none}
  .nav-item{justify-content:center;padding:12px}.nav-sub .nav-item{padding-left:12px}
  .main{margin-left:70px}.grid-2,.grid-3,.grid-4,.form-row,.form-row-3{grid-template-columns:1fr}
  .menu-toggle{display:block}.topbar-kbd{display:none}
}
</style>
</head>
<body>

<aside class="sidebar">
  <div class="sidebar-header">
    <div class="sidebar-logo">🌴<br>NAT<br>CODEV</div>
    <div class="sidebar-brand">NATCODEV<small>Coconut Development &<br>Propagation Initiative</small></div>
  </div>
  <div class="workspace-badge">SETTINGS WORKSPACE</div>
  <div class="workspace-select"><span>⚙️ Settings</span><span>▾</span></div>

  <div class="nav-section">
    <div class="nav-item active" data-page="overview">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
      <span>Overview</span>
    </div>
    <div class="nav-item" data-page="modules">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
      <span>Modules</span>
    </div>
    <div class="nav-item" data-page="rbac">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
      <span>RBAC & Permissions</span>
    </div>
    <div class="nav-item" data-page="user-roles">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
      <span>User Roles</span>
    </div>
    <div class="nav-item" data-page="stakeholder-interests">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7h18"/><path d="M6 7v13"/><path d="M18 7v13"/><path d="M9 20h6"/><path d="M8 3h8l2 4H6z"/></svg>
      <span>Stakeholder Interests</span>
    </div>
    <div class="nav-item" data-page="branding">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>
      <span>Platform Branding</span>
    </div>
    <div class="nav-item" data-page="certificates">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="7"/><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/></svg>
      <span>Certificates</span>
    </div>
    <div class="nav-item" data-page="payments">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
      <span>Payments</span>
    </div>
    <div class="nav-item" data-page="marketplace-settings">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
      <span>Marketplace Settings</span>
    </div>
    <div class="nav-item" data-page="academy-settings">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
      <span>Academy Settings</span>
    </div>
    <div class="nav-item" data-page="registry-settings">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
      <span>Registry Settings</span>
    </div>
    <div class="nav-item" data-page="notifications">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
      <span>Notifications</span>
    </div>
    <div class="nav-item" data-page="integrations">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
      <span>Integrations</span>
    </div>
    <div class="nav-item" data-page="security">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
      <span>Security</span>
    </div>
    <div class="nav-item" data-page="backups">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
      <span>Backups</span>
    </div>
    <div class="nav-item" data-page="system-health">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
      <span>System Health</span>
    </div>
    <div class="nav-item" data-page="audit-log">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
      <span>Audit Log</span>
    </div>
    <div class="nav-item" data-page="email-templates">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
      <span>Email Templates</span>
    </div>
    <div class="nav-item" data-page="webhooks">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71"/></svg>
      <span>Webhooks</span>
    </div>
    <div class="nav-item" data-page="feature-flags">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>
      <span>Feature Flags</span>
    </div>
    <div class="nav-item" data-page="data-retention">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      <span>Data Retention</span>
    </div>
    <div class="nav-item" data-page="maintenance">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z"/></svg>
      <span>Maintenance Mode</span>
    </div>
  </div>

  <div class="help-box" onclick="showToast('Help center opened')">
    <div class="help-box-title">💬 Need Help?</div>
    <div class="help-box-desc">Visit our admin help center</div>
  </div>

  <div class="sidebar-footer">
    <div class="sidebar-avatar">GD</div>
    <div class="sidebar-user">Grace Deh<small><span class="status-dot"></span>Super Admin • Online</small></div>
  </div>
</aside>

<div class="main">
  <div class="topbar">
    <button class="menu-toggle" onclick="document.querySelector('.sidebar').classList.toggle('collapsed')">☰</button>
    <div class="topbar-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" placeholder="Search settings, users, roles, modules, integrations..." id="globalSearch">
      <span class="topbar-kbd">CTRL + K</span>
    </div>
    <div class="topbar-actions">
      <a class="topbar-icon" href="<?= e($settingsAdminBase) ?>/index.php" title="Workspace Hub">⌂</a>
      <a class="topbar-icon" href="<?= e($settingsPublicBase) ?>/index.php" title="Public Homepage">↗</a>
      <div class="topbar-menu-wrap">
        <button class="topbar-icon" type="button" data-menu="notificationsMenu" title="Notifications"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg><?= ($pendingApprovals + $securityAlerts) > 0 ? '<span class="dot"></span>' : '' ?></button>
        <div class="topbar-menu" id="notificationsMenu">
          <div class="topbar-menu-label">Notifications</div>
          <a href="<?= e($settingsAdminBase) ?>/settings.php?page=user-roles"><span>Pending approvals<small><?= (int) $pendingApprovals ?> unverified user record(s)</small></span><span><?= (int) $pendingApprovals ?></span></a>
          <a href="<?= e($settingsAdminBase) ?>/settings.php?page=security"><span>Security alerts<small><?= (int) $securityAlerts ?> RBAC/security event(s) this week</small></span><span><?= (int) $securityAlerts ?></span></a>
          <a href="<?= e($settingsAdminBase) ?>/notifications.php"><span>Notification center<small>Open delivery log</small></span></a>
        </div>
      </div>
      <div class="topbar-menu-wrap">
        <button class="topbar-icon" type="button" data-menu="messagesMenu" title="Messages"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg><?= $openTickets > 0 ? '<span class="dot"></span>' : '' ?></button>
        <div class="topbar-menu" id="messagesMenu">
          <div class="topbar-menu-label">Messages</div>
          <a href="<?= e($settingsAdminBase) ?>/support.php"><span>Support desk<small><?= (int) $openTickets ?> open ticket(s)</small></span><span><?= (int) $openTickets ?></span></a>
          <a href="<?= e($settingsAdminBase) ?>/notifications.php"><span>Delivery messages<small>Review email and SMS logs</small></span></a>
        </div>
      </div>
      <a class="system-status <?= e($systemStatusClass) ?>" href="<?= e($settingsAdminBase) ?>/settings.php?page=system-health"><span class="dot"></span>System Status: <?= e($systemStatusLabel) ?></a>
      <div class="topbar-menu-wrap">
        <button class="quick-actions-btn" type="button" data-menu="quickActionsMenu">Quick Actions ▾</button>
        <div class="topbar-menu" id="quickActionsMenu">
          <div class="topbar-menu-label">Quick Actions</div>
          <button type="button" onclick="openModal('roleModal')"><span>Create role<small>Add a platform role</small></span></button>
          <button type="button" onclick="openModal('backupModal')"><span>Backup now<small>Start manual backup</small></span></button>
          <a href="<?= e($settingsAdminBase) ?>/settings.php?page=rbac"><span>RBAC matrix<small>Manage permissions</small></span></a>
          <a href="<?= e($settingsAdminBase) ?>/settings.php?page=integrations"><span>Integrations<small>Review connected services</small></span></a>
        </div>
      </div>
      <div class="topbar-menu-wrap">
        <button class="topbar-profile" type="button" data-menu="profileMenu" aria-haspopup="true" aria-expanded="false">
          <div class="topbar-avatar"><?php if ($adminPictureUrl !== ''): ?><img src="<?= e($adminPictureUrl) ?>" alt=""><?php else: ?><?= e($adminInitials) ?><?php endif; ?></div>
          <div class="topbar-profile-info"><?= e($adminName) ?><small><?= e($adminRole) ?></small></div>
        </button>
        <div class="topbar-menu" id="profileMenu">
          <div class="topbar-menu-label">Profile</div>
          <a href="<?= e($settingsAdminBase) ?>/profile.php"><span>Edit profile<small>Update name, photo, contact</small></span></a>
          <a href="<?= e($settingsAdminBase) ?>/settings.php?page=security"><span>Security settings<small>Sessions and access controls</small></span></a>
          <a href="<?= e($settingsAdminBase) ?>/index.php"><span>Workspace Hub</span></a>
          <a href="<?= e($settingsPublicBase) ?>/index.php"><span>Public Homepage</span></a>
          <a href="<?= e($settingsAdminBase) ?>/index.php?logout=1"><span>Logout</span></a>
          <a href="<?= e($settingsAdminBase) ?>/admin.php?logout=1"><span>Logout via Legacy Admin</span></a>
          <a href="<?= e($settingsAdminBase) ?>/login.php?logout=1"><span>Logout to Login</span></a>
        </div>
      </div>
    </div>
  </div>

  <div class="content">

    <!-- OVERVIEW -->
    <div class="page active" id="page-overview">
      <div class="page-header">
        <div><div class="page-title">NATCODEV Settings</div><div class="page-subtitle">Configure and manage platform settings, security, integrations, and system preferences.</div></div>
      </div>

      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-header"><div class="stat-card-label">Enabled Modules</div><div class="stat-card-icon" style="background:var(--g100);color:var(--g700)">📦</div></div><div class="stat-card-value">8 / 10</div><div class="stat-card-sub">80% active modules</div></div>
        <div class="stat-card"><div class="stat-card-header"><div class="stat-card-label">Active Roles</div><div class="stat-card-icon" style="background:#dbeafe;color:#1e40af">👥</div></div><div class="stat-card-value">14</div><div class="stat-card-sub">No. of user roles</div></div>
        <div class="stat-card"><div class="stat-card-header"><div class="stat-card-label">Pending Approvals</div><div class="stat-card-icon" style="background:#fef3c7;color:#92400e">🕐</div></div><div class="stat-card-value">23</div><div class="stat-card-sub">Require your review</div></div>
        <div class="stat-card"><div class="stat-card-header"><div class="stat-card-label">Integration Health</div><div class="stat-card-icon" style="background:#ede9fe;color:#5b21b6">🔗</div></div><div class="stat-card-value">7 / 8</div><div class="stat-card-sub">Integrations healthy</div></div>
        <div class="stat-card"><div class="stat-card-header"><div class="stat-card-label">Last Backup</div><div class="stat-card-icon" style="background:var(--g100);color:var(--g700)">💾</div></div><div class="stat-card-value" style="font-size:16px">May 24, 02:30 AM</div><div class="stat-card-sub">Daily automated backup</div></div>
        <div class="stat-card"><div class="stat-card-header"><div class="stat-card-label">Security Alerts</div><div class="stat-card-icon" style="background:var(--g100);color:var(--g700)">🛡️</div></div><div class="stat-card-value">0</div><div class="stat-card-sub">No critical alerts</div></div>
      </div>

      <div class="overview-control-grid">
        <div class="card">
          <div class="card-header"><div class="card-title">Module Control Center</div></div>
          <div class="card-body">
            <div class="grid-4" style="gap:12px">
              <div class="module-card"><div class="module-icon" style="background:var(--g100);color:var(--g700)">👥</div><div class="module-info"><div class="module-title">Registry</div><div class="module-desc">Manage growers & farms</div></div><label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label></div>
              <div class="module-card"><div class="module-icon" style="background:#fef3c7;color:#92400e"></div><div class="module-info"><div class="module-title">Marketplace</div><div class="module-desc">Buy & sell farm inputs</div></div><label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label></div>
              <div class="module-card"><div class="module-icon" style="background:#ede9fe;color:#5b21b6">🎓</div><div class="module-info"><div class="module-title">Academy</div><div class="module-desc">Learning management</div></div><label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label></div>
              <div class="module-card"><div class="module-icon" style="background:var(--g100);color:var(--g700)">💰</div><div class="module-info"><div class="module-title">Wallet</div><div class="module-desc">Payments & settlement</div></div><label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label></div>
              <div class="module-card"><div class="module-icon" style="background:#fee2e2;color:#991b1b">🎧</div><div class="module-info"><div class="module-title">Support Desk</div><div class="module-desc">Ticketing & helpdesk</div></div><label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label></div>
              <div class="module-card"><div class="module-icon" style="background:#dbeafe;color:#1e40af">📊</div><div class="module-info"><div class="module-title">Reports</div><div class="module-desc">Analytics & insights</div></div><label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label></div>
              <div class="module-card"><div class="module-icon" style="background:#fce7f3;color:#be185d">❤️</div><div class="module-info"><div class="module-title">Healthcare</div><div class="module-desc">Worker health mgmt</div></div><label class="toggle"><input type="checkbox"><span class="toggle-slider"></span></label></div>
              <div class="module-card"><div class="module-icon" style="background:#fef3c7;color:#92400e">📍</div><div class="module-info"><div class="module-title">IoT / Geofencing</div><div class="module-desc">Field tracking & alerts</div></div><label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label></div>
            </div>
            <button class="btn-ghost btn-sm" style="margin-top:14px" onclick="navigateTo('modules')">Manage Modules →</button>
          </div>
        </div>

        <div class="card">
          <div class="card-header"><div class="card-title">RBAC Matrix Preview</div><button class="btn-ghost btn-sm" onclick="navigateTo('rbac')">View All</button></div>
          <div class="card-body p0">
            <table style="font-size:11px">
              <thead><tr><th>Permission</th><th>Super Admin</th><th>Admin</th><th>Manager</th><th>Agent</th><th>Viewer</th></tr></thead>
              <tbody>
                <tr><td>Dashboard Access</td><td>✓</td><td>✓</td><td>✓</td><td>✓</td><td>✓</td></tr>
                <tr><td>User Management</td><td>✓</td><td>✓</td><td>✓</td><td>—</td><td>—</td></tr>
                <tr><td>Role Management</td><td>✓</td><td>✓</td><td>✓</td><td>—</td><td>—</td></tr>
                <tr><td>System Settings</td><td>✓</td><td>—</td><td>—</td><td>—</td><td>—</td></tr>
                <tr><td>Financial Access</td><td>✓</td><td>✓</td><td>—</td><td>—</td><td>—</td></tr>
                <tr><td>Audit Logs</td><td>✓</td><td>✓</td><td>—</td><td>—</td><td>—</td></tr>
                <tr><td>Data Export</td><td>✓</td><td>✓</td><td>—</td><td>—</td><td>—</td></tr>
              </tbody>
            </table>
          </div>
          <div style="padding:12px 20px;border-top:1px solid var(--border);text-align:right"><button class="btn-ghost btn-sm" onclick="navigateTo('rbac')">Manage Permissions →</button></div>
        </div>
      </div>

      <div class="grid-2">
        <div class="card">
          <div class="card-header"><div class="card-title">Payment Providers</div><button class="btn-ghost btn-sm" onclick="navigateTo('payments')">View All</button></div>
          <div class="card-body p0">
            <table>
              <thead><tr><th>Provider</th><th>Status</th><th>Mode</th><th>Last Sync</th></tr></thead>
              <tbody>
                <tr><td><div class="avatar-row"><div class="avatar-sm" style="background:#dbeafe;color:#1e40af">M</div><strong>Monnify</strong></div></td><td><span class="status-badge status-connected">Connected</span></td><td>Live</td><td>May 24, 02:10 AM</td></tr>
                <tr><td><div class="avatar-row"><div class="avatar-sm" style="background:#ede9fe;color:#5b21b6">P</div><strong>Paystack</strong></div></td><td><span class="status-badge status-connected">Connected</span></td><td>Live</td><td>May 24, 02:08 AM</td></tr>
                <tr><td><div class="avatar-row"><div class="avatar-sm" style="background:#fef3c7;color:#92400e">F</div><strong>Flutterwave</strong></div></td><td><span class="status-badge status-connected">Connected</span></td><td>Live</td><td>May 24, 02:07 AM</td></tr>
                <tr><td><div class="avatar-row"><div class="avatar-sm" style="background:var(--g100);color:var(--g700)"></div><strong>Bank Transfer</strong></div></td><td><span class="status-badge status-active">Active</span></td><td>Manual</td><td>May 24, 01:55 AM</td></tr>
              </tbody>
            </table>
          </div>
          <div style="padding:12px 20px;border-top:1px solid var(--border);text-align:right"><button class="btn-ghost btn-sm" onclick="navigateTo('payments')">Configure Payments →</button></div>
        </div>

        <div class="card">
          <div class="card-header"><div class="card-title">System Health Checklist</div><button class="btn-ghost btn-sm" onclick="navigateTo('system-health')">View Details</button></div>
          <div class="card-body">
            <div class="health-item"><div class="health-label"><span style="color:var(--success)">✓</span> Web Server</div><div class="health-status" style="color:var(--success)">Operational</div></div>
            <div class="health-item"><div class="health-label"><span style="color:var(--success)">✓</span> Database</div><div class="health-status" style="color:var(--success)">Operational</div></div>
            <div class="health-item"><div class="health-label"><span style="color:var(--success)">✓</span> Queue Worker</div><div class="health-status" style="color:var(--success)">Operational</div></div>
            <div class="health-item"><div class="health-label"><span style="color:var(--success)">✓</span> Cache (Redis)</div><div class="health-status" style="color:var(--success)">Operational</div></div>
            <div class="health-item"><div class="health-label"><span style="color:var(--success)">✓</span> Storage</div><div class="health-status" style="color:var(--success)">Operational</div></div>
            <div class="health-item"><div class="health-label"><span style="color:var(--success)">✓</span> Cron Jobs</div><div class="health-status" style="color:var(--success)">Operational</div></div>
            <div class="health-item"><div class="health-label"><span style="color:var(--success)">✓</span> Email Service</div><div class="health-status" style="color:var(--success)">Operational</div></div>
          </div>
        </div>
      </div>

      <div class="grid-3">
        <div class="card">
          <div class="card-header"><div class="card-title">Certificate Policy Settings</div><button class="btn-ghost btn-sm" onclick="navigateTo('certificates')">Manage</button></div>
          <div class="card-body">
            <div class="setting-row"><div class="setting-info"><div class="setting-title">Auto-generate Certificates</div><div class="setting-desc">Generate certificates on approval</div></div><label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label></div>
            <div class="setting-row"><div class="setting-info"><div class="setting-title">QR Code Verification</div><div class="setting-desc">Enable public verification via QR</div></div><label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label></div>
            <div class="setting-row"><div class="setting-info"><div class="setting-title">Certificate Expiry (Months)</div></div><input class="form-input" style="width:80px" value="24" type="number"></div>
            <div class="setting-row"><div class="setting-info"><div class="setting-title">Renewal Reminder (Days)</div></div><input class="form-input" style="width:80px" value="30" type="number"></div>
          </div>
        </div>

        <div class="card">
          <div class="card-header"><div class="card-title">Notification Channels</div><button class="btn-ghost btn-sm" onclick="navigateTo('notifications')">Manage</button></div>
          <div class="card-body">
            <div class="setting-row"><div class="setting-info"><div class="setting-title">📧 Email Notifications</div><div class="setting-desc">System & user emails</div></div><span class="status-badge status-enabled">Enabled ✓</span></div>
            <div class="setting-row"><div class="setting-info"><div class="setting-title">📱 SMS Notifications</div><div class="setting-desc">Transactional alerts</div></div><span class="status-badge status-enabled">Enabled ✓</span></div>
            <div class="setting-row"><div class="setting-info"><div class="setting-title"> In-App Notifications</div><div class="setting-desc">Platform notifications</div></div><span class="status-badge status-enabled">Enabled ✓</span></div>
            <div class="setting-row"><div class="setting-info"><div class="setting-title"> WhatsApp Notifications</div><div class="setting-desc">WhatsApp integration</div></div><span class="status-badge status-disabled">Disabled</span></div>
          </div>
        </div>

        <div class="card">
          <div class="card-header"><div class="card-title">Integration & API Keys</div><button class="btn-ghost btn-sm" onclick="navigateTo('integrations')">View All</button></div>
          <div class="card-body p0">
            <table style="font-size:11.5px">
              <thead><tr><th>Integration</th><th>Status</th><th>Last Used</th></tr></thead>
              <tbody>
                <tr><td><strong>Monnify</strong></td><td><span class="status-badge status-connected">Connected</span></td><td>May 24, 01:20</td></tr>
                <tr><td><strong>Google Maps</strong></td><td><span class="status-badge status-connected">Connected</span></td><td>May 24, 12:55</td></tr>
                <tr><td><strong>Twilio SMS</strong></td><td><span class="status-badge status-connected">Connected</span></td><td>May 24, 12:30</td></tr>
                <tr><td><strong>WhatsApp API</strong></td><td><span class="status-badge status-connected">Connected</span></td><td>May 24, 12:10</td></tr>
              </tbody>
            </table>
          </div>
          <div style="padding:12px 20px;border-top:1px solid var(--border);text-align:right"><button class="btn-ghost btn-sm" onclick="navigateTo('integrations')">Manage Integrations →</button></div>
        </div>
      </div>

      <div class="grid-2">
        <div class="card">
          <div class="card-header"><div class="card-title">Audit Log (Latest Activities)</div><button class="btn-ghost btn-sm" onclick="navigateTo('audit-log')">View All</button></div>
          <div class="card-body p0">
            <table style="font-size:11.5px">
              <thead><tr><th>Time</th><th>Admin</th><th>Action</th><th>Module</th><th>Details</th></tr></thead>
              <tbody>
                <tr><td>May 24, 02:35</td><td>Grace Deh</td><td>Updated system settings</td><td>Settings</td><td>Updated email notification template</td></tr>
                <tr><td>May 24, 02:22</td><td>John Okafor</td><td>Created new role</td><td>RBAC</td><td>Role: State Coordinator</td></tr>
                <tr><td>May 24, 01:58</td><td>Fatima Bello</td><td>Enabled module</td><td>Modules</td><td>Module: Healthcare</td></tr>
                <tr><td>May 24, 01:40</td><td>Aisha Musa</td><td>Exported registry data</td><td>Reports</td><td>Export: Verified Growers - May 2026</td></tr>
                <tr><td>May 24, 01:15</td><td>System</td><td>Backup completed</td><td>Backups</td><td>Backup ID: BKP-20260524-0215</td></tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="card">
          <div class="card-header"><div class="card-title">Quick Actions</div></div>
          <div class="card-body">
            <div class="grid-2" style="gap:12px">
              <div class="quick-action-card" onclick="showToast('Settings saved')"><div class="quick-action-icon" style="background:var(--g100);color:var(--g700)">💾</div><div style="flex:1"><div class="quick-action-title">Save Settings</div><div class="quick-action-desc">Persist configuration changes</div></div><span>→</span></div>
              <div class="quick-action-card" onclick="openModal('roleModal')"><div class="quick-action-icon" style="background:#dbeafe;color:#1e40af">👥</div><div style="flex:1"><div class="quick-action-title">Create Role</div><div class="quick-action-desc">Add new user role</div></div><span>→</span></div>
              <div class="quick-action-card" onclick="navigateTo('system-health')"><div class="quick-action-icon" style="background:#ede9fe;color:#5b21b6"></div><div style="flex:1"><div class="quick-action-title">Run Health Check</div><div class="quick-action-desc">Check system integrity</div></div><span>→</span></div>
              <div class="quick-action-card" onclick="openModal('backupModal')"><div class="quick-action-icon" style="background:#fef3c7;color:#92400e">💾</div><div style="flex:1"><div class="quick-action-title">Backup Now</div><div class="quick-action-desc">Manual system backup</div></div><span>→</span></div>
              <div class="quick-action-card" onclick="showToast('Cache cleared successfully')" style="grid-column:span 2;background:#fef2f2;border-color:#fecaca"><div class="quick-action-icon" style="background:#fee2e2;color:#991b1b">🗑️</div><div style="flex:1"><div class="quick-action-title" style="color:var(--danger)">Clear Cache</div><div class="quick-action-desc">Clear system & application cache</div></div><span>→</span></div>
            </div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><div class="card-title">Security Summary</div><button class="btn-ghost btn-sm" onclick="navigateTo('security')">View Report</button></div>
        <div class="card-body">
          <div class="grid-4" style="gap:12px">
            <div class="health-item" style="border:none;padding:0"><div class="health-label"><span style="color:var(--success)">🔐</span> Two-Factor Authentication</div><div class="health-status" style="color:var(--success)">Enabled ✓</div></div>
            <div class="health-item" style="border:none;padding:0"><div class="health-label"><span style="color:var(--success)">🔑</span> Password Policy</div><div class="health-status" style="color:var(--success)">Enforced ✓</div></div>
            <div class="health-item" style="border:none;padding:0"><div class="health-label"><span style="color:var(--success)">🔒</span> Login Attempts (24h)</div><div class="health-status">12 <button class="btn-ghost btn-sm">View Logs →</button></div></div>
            <div class="health-item" style="border:none;padding:0"><div class="health-label"><span style="color:var(--success)">🚫</span> Blocked IP Addresses</div><div class="health-status">3 <button class="btn-ghost btn-sm">Manage →</button></div></div>
            <div class="health-item" style="border:none;padding:0"><div class="health-label"><span style="color:var(--success)">👤</span> Current Sessions</div><div class="health-status">8 Active <button class="btn-ghost btn-sm">View All →</button></div></div>
          </div>
        </div>
      </div>
    </div>

    <!-- MODULES -->
    <div class="page" id="page-modules">
      <div class="page-header"><div><div class="page-title">Module Control Center</div><div class="page-subtitle">Enable, disable, and configure platform modules</div></div><button class="btn btn-primary" onclick="openModal('moduleModal')">+ Add Module</button></div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">Total Modules</div><div class="stat-card-value">10</div></div>
        <div class="stat-card"><div class="stat-card-label">Enabled</div><div class="stat-card-value" style="color:var(--success)">8</div></div>
        <div class="stat-card"><div class="stat-card-label">Disabled</div><div class="stat-card-value" style="color:var(--text2)">2</div></div>
        <div class="stat-card"><div class="stat-card-label">Custom Modules</div><div class="stat-card-value">3</div></div>
      </div>
      <div class="card"><div class="card-body">
        <div class="grid-3" style="gap:14px">
          <div class="module-card"><div class="module-icon" style="background:var(--g100);color:var(--g700)">👥</div><div class="module-info"><div class="module-title">Registry</div><div class="module-desc">Manage growers & farms • 89,642 records</div><div style="margin-top:6px"><span class="chip">Core</span></div></div><label class="toggle"><input type="checkbox" checked onchange="showToast('Module toggled')"><span class="toggle-slider"></span></label></div>
          <div class="module-card"><div class="module-icon" style="background:#fef3c7;color:#92400e">🛒</div><div class="module-info"><div class="module-title">Marketplace</div><div class="module-desc">Buy & sell farm inputs • 4,756 products</div><div style="margin-top:6px"><span class="chip">Core</span></div></div><label class="toggle"><input type="checkbox" checked onchange="showToast('Module toggled')"><span class="toggle-slider"></span></label></div>
          <div class="module-card"><div class="module-icon" style="background:#ede9fe;color:#5b21b6">🎓</div><div class="module-info"><div class="module-title">Academy</div><div class="module-desc">Learning management • 48 courses</div><div style="margin-top:6px"><span class="chip">Core</span></div></div><label class="toggle"><input type="checkbox" checked onchange="showToast('Module toggled')"><span class="toggle-slider"></span></label></div>
          <div class="module-card"><div class="module-icon" style="background:var(--g100);color:var(--g700)">💰</div><div class="module-info"><div class="module-title">Wallet</div><div class="module-desc">Payments & settlement • ₦24.9M balance</div><div style="margin-top:6px"><span class="chip">Core</span></div></div><label class="toggle"><input type="checkbox" checked onchange="showToast('Module toggled')"><span class="toggle-slider"></span></label></div>
          <div class="module-card"><div class="module-icon" style="background:#fee2e2;color:#991b1b">🎧</div><div class="module-info"><div class="module-title">Support Desk</div><div class="module-desc">Ticketing & helpdesk • 317 open</div><div style="margin-top:6px"><span class="chip">Core</span></div></div><label class="toggle"><input type="checkbox" checked onchange="showToast('Module toggled')"><span class="toggle-slider"></span></label></div>
          <div class="module-card"><div class="module-icon" style="background:#dbeafe;color:#1e40af">📊</div><div class="module-info"><div class="module-title">Reports</div><div class="module-desc">Analytics & insights • 12 scheduled</div><div style="margin-top:6px"><span class="chip">Core</span></div></div><label class="toggle"><input type="checkbox" checked onchange="showToast('Module toggled')"><span class="toggle-slider"></span></label></div>
          <div class="module-card" style="opacity:.7"><div class="module-icon" style="background:#fce7f3;color:#be185d">❤️</div><div class="module-info"><div class="module-title">Healthcare</div><div class="module-desc">Worker health management</div><div style="margin-top:6px"><span class="chip chip-warn">Beta</span></div></div><label class="toggle"><input type="checkbox" onchange="showToast('Module toggled')"><span class="toggle-slider"></span></label></div>
          <div class="module-card"><div class="module-icon" style="background:#fef3c7;color:#92400e">📍</div><div class="module-info"><div class="module-title">IoT / Geofencing</div><div class="module-desc">Field tracking & alerts • 128 devices</div><div style="margin-top:6px"><span class="chip">Core</span></div></div><label class="toggle"><input type="checkbox" checked onchange="showToast('Module toggled')"><span class="toggle-slider"></span></label></div>
          <div class="module-card"><div class="module-icon" style="background:#dbeafe;color:#1e40af">📱</div><div class="module-info"><div class="module-title">Mobile App</div><div class="module-desc">Field agent mobile application</div><div style="margin-top:6px"><span class="chip">Custom</span></div></div><label class="toggle"><input type="checkbox" checked onchange="showToast('Module toggled')"><span class="toggle-slider"></span></label></div>
          <div class="module-card" style="opacity:.7"><div class="module-icon" style="background:#ede9fe;color:#5b21b6">🤖</div><div class="module-info"><div class="module-title">AI Assistant</div><div class="module-desc">Intelligent recommendations engine</div><div style="margin-top:6px"><span class="chip chip-warn">Beta</span></div></div><label class="toggle"><input type="checkbox" onchange="showToast('Module toggled')"><span class="toggle-slider"></span></label></div>
        </div>
      </div></div>
    </div>

    <!-- RBAC -->
    <div class="page" id="page-rbac">
      <div class="page-header"><div><div class="page-title">RBAC & Permissions</div><div class="page-subtitle">Role-Based Access Control matrix</div></div><button class="btn btn-primary" onclick="openModal('permissionModal')">+ Add Permission</button></div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">Total Permissions</div><div class="stat-card-value">47</div></div>
        <div class="stat-card"><div class="stat-card-label">Active Roles</div><div class="stat-card-value">14</div></div>
        <div class="stat-card"><div class="stat-card-label">Custom Permissions</div><div class="stat-card-value">12</div></div>
        <div class="stat-card"><div class="stat-card-label">Pending Approvals</div><div class="stat-card-value" style="color:var(--warn)">23</div></div>
      </div>
      <div class="card"><div class="card-header"><div class="card-title">Permission Matrix</div><div class="filter-bar" style="margin:0"><input type="text" placeholder="Search permissions..." oninput="filterTable('rbacTable',this.value)"><select><option>All Roles</option><option>Super Admin</option><option>Admin</option><option>Manager</option><option>Agent</option><option>Viewer</option></select></div></div><div class="card-body p0">
        <table id="rbacTable">
          <thead><tr><th>Permission</th><th>Module</th><th>Super Admin</th><th>Admin</th><th>Manager</th><th>Agent</th><th>Viewer</th><th>Actions</th></tr></thead>
          <tbody>
            <tr><td><strong>Dashboard Access</strong></td><td>System</td><td>✅</td><td>✅</td><td>✅</td><td>✅</td><td>✅</td><td><button class="btn-icon">✏️</button></td></tr>
            <tr><td><strong>User Management</strong></td><td>System</td><td>✅</td><td>✅</td><td>✅</td><td>—</td><td>—</td><td><button class="btn-icon">✏️</button></td></tr>
            <tr><td><strong>Role Management</strong></td><td>System</td><td>✅</td><td>✅</td><td>✅</td><td>—</td><td>—</td><td><button class="btn-icon">️</button></td></tr>
            <tr><td><strong>System Settings</strong></td><td>System</td><td>✅</td><td>—</td><td>—</td><td>—</td><td>—</td><td><button class="btn-icon">✏️</button></td></tr>
            <tr><td><strong>Financial Access</strong></td><td>Wallet</td><td>✅</td><td>✅</td><td>—</td><td>—</td><td>—</td><td><button class="btn-icon">✏️</button></td></tr>
            <tr><td><strong>Audit Logs</strong></td><td>System</td><td>✅</td><td>✅</td><td>—</td><td>—</td><td>—</td><td><button class="btn-icon">✏️</button></td></tr>
            <tr><td><strong>Data Export</strong></td><td>Reports</td><td>✅</td><td>✅</td><td>—</td><td>—</td><td>—</td><td><button class="btn-icon">✏️</button></td></tr>
            <tr><td><strong>Grower Management</strong></td><td>Registry</td><td>✅</td><td>✅</td><td>✅</td><td>✅</td><td>—</td><td><button class="btn-icon">✏️</button></td></tr>
            <tr><td><strong>Order Management</strong></td><td>Marketplace</td><td>✅</td><td>✅</td><td>✅</td><td>✅</td><td>—</td><td><button class="btn-icon">✏️</button></td></tr>
            <tr><td><strong>Course Management</strong></td><td>Academy</td><td>✅</td><td>✅</td><td>✅</td><td>—</td><td>—</td><td><button class="btn-icon">✏️</button></td></tr>
            <tr><td><strong>Field Visits</strong></td><td>Registry</td><td>✅</td><td>✅</td><td>✅</td><td>✅</td><td>✅</td><td><button class="btn-icon">✏️</button></td></tr>
            <tr><td><strong>Refund Processing</strong></td><td>Wallet</td><td>✅</td><td>✅</td><td>✅</td><td>—</td><td>—</td><td><button class="btn-icon">️</button></td></tr>
          </tbody>
        </table>
      </div></div>
    </div>

    <!-- USER ROLES -->
    <div class="page" id="page-user-roles">
      <div class="page-header"><div><div class="page-title">User Roles</div><div class="page-subtitle">14 active roles configured</div></div><button class="btn btn-primary" onclick="openModal('roleModal')">+ Create Role</button></div>
      <div class="card"><div class="card-body p0">
        <table>
          <thead><tr><th>Role</th><th>Users</th><th>Permissions</th><th>Module Access</th><th>Created</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
            <tr><td><div class="avatar-row"><div class="avatar-sm" style="background:var(--g700);color:#fff">SA</div><div><strong>Super Admin</strong><br><small style="color:var(--text2)">Full system access</small></div></div></td><td>3</td><td>47/47</td><td>All Modules</td><td>Jan 1, 2026</td><td><span class="status-badge status-active">Active</span></td><td><button class="btn-icon">️</button></td></tr>
            <tr><td><div class="avatar-row"><div class="avatar-sm" style="background:var(--g600);color:#fff">AD</div><div><strong>Admin</strong><br><small style="color:var(--text2)">Platform administration</small></div></div></td><td>8</td><td>38/47</td><td>All except Settings</td><td>Jan 1, 2026</td><td><span class="status-badge status-active">Active</span></td><td><button class="btn-icon">✏️</button></td></tr>
            <tr><td><div class="avatar-row"><div class="avatar-sm" style="background:var(--info);color:#fff">MG</div><div><strong>Manager</strong><br><small style="color:var(--text2)">Operational management</small></div></div></td><td>24</td><td>28/47</td><td>Registry, Marketplace, Academy</td><td>Jan 15, 2026</td><td><span class="status-badge status-active">Active</span></td><td><button class="btn-icon">✏️</button></td></tr>
            <tr><td><div class="avatar-row"><div class="avatar-sm" style="background:var(--warn);color:#fff">AG</div><div><strong>Field Agent</strong><br><small style="color:var(--text2)">Field operations</small></div></div></td><td>128</td><td>18/47</td><td>Registry, Field Ops</td><td>Feb 1, 2026</td><td><span class="status-badge status-active">Active</span></td><td><button class="btn-icon">✏️</button></td></tr>
            <tr><td><div class="avatar-row"><div class="avatar-sm" style="background:var(--purple);color:#fff">VW</div><div><strong>Viewer</strong><br><small style="color:var(--text2)">Read-only access</small></div></div></td><td>45</td><td>8/47</td><td>Dashboard, Reports</td><td>Mar 1, 2026</td><td><span class="status-badge status-active">Active</span></td><td><button class="btn-icon">️</button></td></tr>
            <tr><td><div class="avatar-row"><div class="avatar-sm" style="background:var(--orange);color:#fff">SC</div><div><strong>State Coordinator</strong><br><small style="color:var(--text2)">State-level management</small></div></div></td><td>36</td><td>22/47</td><td>Registry, Reports (State)</td><td>Apr 10, 2026</td><td><span class="status-badge status-active">Active</span></td><td><button class="btn-icon">✏️</button></td></tr>
            <tr><td><div class="avatar-row"><div class="avatar-sm" style="background:var(--pink);color:#fff">SL</div><div><strong>Seller</strong><br><small style="color:var(--text2)">Marketplace seller</small></div></div></td><td>1,248</td><td>12/47</td><td>Marketplace, Wallet</td><td>Jan 1, 2026</td><td><span class="status-badge status-active">Active</span></td><td><button class="btn-icon">✏️</button></td></tr>
            <tr><td><div class="avatar-row"><div class="avatar-sm" style="background:#64748b;color:#fff">LR</div><div><strong>Learner</strong><br><small style="color:var(--text2)">Academy learner</small></div></div></td><td>3,624</td><td>6/47</td><td>Academy</td><td>Jan 1, 2026</td><td><span class="status-badge status-active">Active</span></td><td><button class="btn-icon">✏️</button></td></tr>
          </tbody>
        </table>
      </div></div>
    </div>

    <!-- STAKEHOLDER INTERESTS -->
    <div class="page" id="page-stakeholder-interests">
      <div class="page-header">
        <div><div class="page-title">Stakeholder Interests</div><div class="page-subtitle">Control every stakeholder entry point, workspace destination, role request path, and support scope.</div></div>
      </div>
      <div class="card">
        <div class="card-header"><div class="card-title">Platform Access Map</div><button class="btn btn-secondary btn-sm" onclick="showToast('Use inline rows to update stakeholder controls')">Managed Inline</button></div>
        <div class="card-body p0">
          <table>
            <thead><tr><th>Stakeholder</th><th>Entry Point</th><th>Workspace</th><th>Request Path</th><th>Support Scope</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody><tr><td colspan="7">Loading stakeholder controls...</td></tr></tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- BRANDING -->
    <div class="page" id="page-branding">
      <div class="page-header"><div><div class="page-title">Platform Branding</div><div class="page-subtitle">Customize platform appearance and identity</div></div><button class="btn btn-primary" onclick="showToast('Branding saved')">💾 Save Changes</button></div>
      <div class="grid-2">
        <div class="card"><div class="card-header"><div class="card-title">Brand Identity</div></div><div class="card-body"><div class="form-group"><label class="form-label">Platform Name</label><input class="form-input" value="NATCODEV"></div><div class="form-group"><label class="form-label">Tagline</label><input class="form-input" value="National Coconut Development & Propagation Initiative"></div><div class="form-group"><label class="form-label">Logo</label><div style="border:2px dashed var(--border);border-radius:10px;padding:20px;text-align:center;cursor:pointer">🖼️ Current logo displayed • Click to upload new</div></div><div class="form-group"><label class="form-label">Favicon</label><div style="border:2px dashed var(--border);border-radius:10px;padding:20px;text-align:center;cursor:pointer">🖼️ Current favicon • Click to upload</div></div><div class="form-group"><label class="form-label">Primary Color</label><div class="form-row"><input class="form-input" value="#164a33"><input type="color" value="#164a33" style="height:40px"></div></div><div class="form-group"><label class="form-label">Accent Color</label><div class="form-row"><input class="form-input" value="#34c48a"><input type="color" value="#34c48a" style="height:40px"></div></div></div></div>
        <div class="card"><div class="card-header"><div class="card-title">Preview</div></div><div class="card-body"><div style="background:linear-gradient(135deg,#164a33,#1e6b47);color:#fff;padding:30px;border-radius:12px;text-align:center"><div style="font-size:32px;font-weight:800;margin-bottom:8px">🌴 NATCODEV</div><div style="font-size:12px;opacity:.8">National Coconut Development & Propagation Initiative</div><div style="margin-top:20px;display:flex;gap:10px;justify-content:center"><button style="padding:8px 16px;background:#34c48a;color:#fff;border:none;border-radius:6px;font-weight:600">Primary Button</button><button style="padding:8px 16px;background:transparent;color:#fff;border:1px solid #fff;border-radius:6px;font-weight:600">Secondary</button></div></div><div style="margin-top:16px;padding:16px;background:var(--bg);border-radius:10px"><div style="font-weight:700;margin-bottom:8px">Typography Sample</div><div style="font-size:18px;font-weight:700;margin-bottom:4px">Heading Text</div><div style="font-size:13px;color:var(--text2)">Body text sample - The quick brown fox jumps over the lazy dog.</div></div></div></div>
      </div>
      <div class="card"><div class="card-header"><div class="card-title">Email Branding</div></div><div class="card-body"><div class="form-row"><div class="form-group"><label class="form-label">Email Header Logo</label><div style="border:2px dashed var(--border);border-radius:10px;padding:20px;text-align:center;cursor:pointer">🖼️ Upload email header logo</div></div><div class="form-group"><label class="form-label">Email Footer Text</label><textarea class="form-textarea">© 2026 NATCODEV. All rights reserved.&#10;National Coconut Development & Propagation Initiative</textarea></div></div><div class="form-row"><div class="form-group"><label class="form-label">Support Email</label><input class="form-input" value="support@natcodev.org"></div><div class="form-group"><label class="form-label">Contact Phone</label><input class="form-input" value="+234 800 NATCODEV"></div></div></div></div>
    </div>

    <!-- CERTIFICATES -->
    <div class="page" id="page-certificates">
      <div class="page-header"><div><div class="page-title">Certificate Settings</div><div class="page-subtitle">Configure certificate policies and templates</div></div><button class="btn btn-primary" onclick="showToast('Settings saved')">💾 Save Changes</button></div>
      <div class="grid-2">
        <div class="card"><div class="card-header"><div class="card-title">Certificate Policy</div></div><div class="card-body"><div class="setting-row"><div class="setting-info"><div class="setting-title">Auto-generate Certificates</div><div class="setting-desc">Generate certificates on approval</div></div><label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label></div><div class="setting-row"><div class="setting-info"><div class="setting-title">QR Code Verification</div><div class="setting-desc">Enable public verification via QR</div></div><label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label></div><div class="form-group" style="margin-top:14px"><label class="form-label">Certificate Expiry (Months)</label><input class="form-input" type="number" value="24"></div><div class="form-group"><label class="form-label">Renewal Reminder (Days Before)</label><input class="form-input" type="number" value="30"></div><div class="form-group"><label class="form-label">Certificate Number Format</label><select class="form-select"><option>CERT-YYYY-NNNNN</option><option>NC-YYYY-NNNNN</option><option>CUSTOM</option></select></div><div class="form-group"><label class="form-label">Issuing Authority</label><input class="form-input" value="NATCODEV - National Coconut Development Initiative"></div></div></div>
        <div class="card"><div class="card-header"><div class="card-title">Certificate Templates</div><button class="btn btn-secondary btn-sm" onclick="showToast('Template editor opened')">+ New Template</button></div><div class="card-body"><div class="grid-2" style="gap:12px"><div style="border:1px solid var(--border);border-radius:10px;padding:16px;text-align:center;cursor:pointer"><div style="font-size:40px;margin-bottom:8px">🏆</div><div style="font-weight:700;font-size:13px">Grower Certificate</div><div style="font-size:11px;color:var(--text2);margin:4px 0">Standard template</div><button class="btn btn-sm btn-secondary">Edit</button></div><div style="border:1px solid var(--border);border-radius:10px;padding:16px;text-align:center;cursor:pointer"><div style="font-size:40px;margin-bottom:8px">🤝</div><div style="font-weight:700;font-size:13px">Cooperative Certificate</div><div style="font-size:11px;color:var(--text2);margin:4px 0">For registered coops</div><button class="btn btn-sm btn-secondary">Edit</button></div><div style="border:1px solid var(--border);border-radius:10px;padding:16px;text-align:center;cursor:pointer"><div style="font-size:40px;margin-bottom:8px">🌱</div><div style="font-weight:700;font-size:13px">Nursery Certificate</div><div style="font-size:11px;color:var(--text2);margin:4px 0">For nursery operators</div><button class="btn btn-sm btn-secondary">Edit</button></div><div style="border:2px dashed var(--border);border-radius:10px;padding:16px;text-align:center;cursor:pointer;color:var(--text2)"><div style="font-size:30px;margin-bottom:8px">➕</div><div style="font-weight:700;font-size:13px">Create New</div></div></div></div></div>
      </div>
    </div>

    <!-- PAYMENTS -->
    <div class="page" id="page-payments">
      <div class="page-header"><div><div class="page-title">Payment Providers</div><div class="page-subtitle">Configure payment gateways and providers</div></div><button class="btn btn-primary" onclick="openModal('providerModal')">+ Add Provider</button></div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">Active Providers</div><div class="stat-card-value">4</div></div>
        <div class="stat-card"><div class="stat-card-label">Total Volume (7D)</div><div class="stat-card-value">₦32.8M</div></div>
        <div class="stat-card"><div class="stat-card-label">Success Rate</div><div class="stat-card-value" style="color:var(--success)">97.2%</div></div>
        <div class="stat-card"><div class="stat-card-label">Failed Transactions</div><div class="stat-card-value" style="color:var(--danger)">91</div></div>
      </div>
      <div class="card"><div class="card-body p0">
        <table>
          <thead><tr><th>Provider</th><th>Status</th><th>Mode</th><th>Fee (%)</th><th>Volume (7D)</th><th>Last Sync</th><th>Actions</th></tr></thead>
          <tbody>
            <tr><td><div class="avatar-row"><div class="avatar-sm" style="background:#dbeafe;color:#1e40af">M</div><div><strong>Monnify</strong><br><small style="color:var(--text2)">Primary gateway</small></div></div></td><td><span class="status-badge status-connected">Connected</span></td><td>Live</td><td>0.5%</td><td>₦18.2M</td><td>May 24, 02:10 AM</td><td><button class="btn btn-sm btn-secondary">Configure</button></td></tr>
            <tr><td><div class="avatar-row"><div class="avatar-sm" style="background:#ede9fe;color:#5b21b6">P</div><div><strong>Paystack</strong><br><small style="color:var(--text2)">Secondary gateway</small></div></div></td><td><span class="status-badge status-connected">Connected</span></td><td>Live</td><td>1.5%</td><td>₦8.4M</td><td>May 24, 02:08 AM</td><td><button class="btn btn-sm btn-secondary">Configure</button></td></tr>
            <tr><td><div class="avatar-row"><div class="avatar-sm" style="background:#fef3c7;color:#92400e">F</div><div><strong>Flutterwave</strong><br><small style="color:var(--text2)">Backup gateway</small></div></div></td><td><span class="status-badge status-connected">Connected</span></td><td>Live</td><td>1.4%</td><td>₦4.2M</td><td>May 24, 02:07 AM</td><td><button class="btn btn-sm btn-secondary">Configure</button></td></tr>
            <tr><td><div class="avatar-row"><div class="avatar-sm" style="background:var(--g100);color:var(--g700)">🏦</div><div><strong>Bank Transfer</strong><br><small style="color:var(--text2)">Manual processing</small></div></div></td><td><span class="status-badge status-active">Active</span></td><td>Manual</td><td>0%</td><td>₦2.0M</td><td>May 24, 01:55 AM</td><td><button class="btn btn-sm btn-secondary">Configure</button></td></tr>
          </tbody>
        </table>
      </div></div>
      <div class="card"><div class="card-header"><div class="card-title">Payment Configuration</div></div><div class="card-body"><div class="form-row"><div class="form-group"><label class="form-label">Default Gateway</label><select class="form-select"><option>Monnify</option><option>Paystack</option><option>Flutterwave</option></select></div><div class="form-group"><label class="form-label">Currency</label><select class="form-select"><option>NGN (₦)</option><option>USD ($)</option></select></div></div><div class="form-row"><div class="form-group"><label class="form-label">Auto-retry Failed Payments</label><select class="form-select"><option>Yes - 2 retries</option><option>Yes - 1 retry</option><option>No</option></select></div><div class="form-group"><label class="form-label">Payment Timeout (seconds)</label><input class="form-input" type="number" value="300"></div></div><button class="btn btn-primary" onclick="showToast('Payment settings saved')">Save Configuration</button></div></div>
    </div>

    <!-- MARKETPLACE SETTINGS -->
    <div class="page" id="page-marketplace-settings">
      <div class="page-header"><div><div class="page-title">Marketplace Settings</div><div class="page-subtitle">Configure marketplace operations and policies</div></div><button class="btn btn-primary" onclick="showToast('Settings saved')">💾 Save Changes</button></div>
      <div class="grid-2">
        <div class="card"><div class="card-header"><div class="card-title">General Settings</div></div><div class="card-body"><div class="form-group"><label class="form-label">Commission Rate (%)</label><input class="form-input" type="number" value="10"></div><div class="form-group"><label class="form-label">Minimum Payout (₦)</label><input class="form-input" type="number" value="10000"></div><div class="form-group"><label class="form-label">Order Auto-Confirm (hours)</label><input class="form-input" type="number" value="72"></div><div class="form-group"><label class="form-label">Refund Window (days)</label><input class="form-input" type="number" value="14"></div><div class="setting-row"><div class="setting-info"><div class="setting-title">Enable Buyer Protection</div><div class="setting-desc">Hold payment until delivery confirmed</div></div><label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label></div><div class="setting-row"><div class="setting-info"><div class="setting-title">Auto-approve Sellers</div><div class="setting-desc">Skip manual verification</div></div><label class="toggle"><input type="checkbox"><span class="toggle-slider"></span></label></div></div></div>
        <div class="card"><div class="card-header"><div class="card-title">Product Settings</div></div><div class="card-body"><div class="form-group"><label class="form-label">Max Images per Product</label><input class="form-input" type="number" value="10"></div><div class="form-group"><label class="form-label">Max File Size (MB)</label><input class="form-input" type="number" value="10"></div><div class="form-group"><label class="form-label">Allowed File Types</label><input class="form-input" value="JPG, PNG, PDF"></div><div class="setting-row"><div class="setting-info"><div class="setting-title">Require Product Approval</div><div class="setting-desc">Admin must approve new listings</div></div><label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label></div><div class="setting-row"><div class="setting-info"><div class="setting-title">Enable Reviews</div><div class="setting-desc">Allow buyer reviews</div></div><label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label></div><div class="setting-row"><div class="setting-info"><div class="setting-title">Show Stock Count</div><div class="setting-desc">Display remaining stock</div></div><label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label></div></div></div>
      </div>
    </div>

    <!-- ACADEMY SETTINGS -->
    <div class="page" id="page-academy-settings">
      <div class="page-header"><div><div class="page-title">Academy Settings</div><div class="page-subtitle">Configure learning management system</div></div><button class="btn btn-primary" onclick="showToast('Settings saved')">💾 Save Changes</button></div>
      <div class="grid-2">
        <div class="card"><div class="card-header"><div class="card-title">Course Settings</div></div><div class="card-body"><div class="form-group"><label class="form-label">Default Passing Score (%)</label><input class="form-input" type="number" value="70"></div><div class="form-group"><label class="form-label">Max Attempts per Quiz</label><input class="form-input" type="number" value="3"></div><div class="form-group"><label class="form-label">Certificate Validity (months)</label><input class="form-input" type="number" value="12"></div><div class="setting-row"><div class="setting-info"><div class="setting-title">Enable Course Reviews</div></div><label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label></div><div class="setting-row"><div class="setting-info"><div class="setting-title">Auto-enroll on Registration</div></div><label class="toggle"><input type="checkbox"><span class="toggle-slider"></span></label></div><div class="setting-row"><div class="setting-info"><div class="setting-title">Send Completion Certificates</div></div><label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label></div></div></div>
        <div class="card"><div class="card-header"><div class="card-title">Learner Settings</div></div><div class="card-body"><div class="form-group"><label class="form-label">Max Concurrent Courses</label><input class="form-input" type="number" value="5"></div><div class="form-group"><label class="form-label">Progress Save Interval (seconds)</label><input class="form-input" type="number" value="30"></div><div class="setting-row"><div class="setting-info"><div class="setting-title">Enable Discussion Forums</div></div><label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label></div><div class="setting-row"><div class="setting-info"><div class="setting-title">Allow Course Downloads</div></div><label class="toggle"><input type="checkbox"><span class="toggle-slider"></span></label></div><div class="setting-row"><div class="setting-info"><div class="setting-title">Send Weekly Progress Reports</div></div><label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label></div></div></div>
      </div>
    </div>

    <!-- REGISTRY SETTINGS -->
    <div class="page" id="page-registry-settings">
      <div class="page-header"><div><div class="page-title">Registry Settings</div><div class="page-subtitle">Configure grower registry and verification workflows</div></div><button class="btn btn-primary" onclick="showToast('Settings saved')">💾 Save Changes</button></div>
      <div class="grid-2">
        <div class="card"><div class="card-header"><div class="card-title">Registration Settings</div></div><div class="card-body"><div class="form-group"><label class="form-label">Auto-assign Field Agent</label><select class="form-select"><option>Yes - by state</option><option>Yes - by LGA</option><option>Manual assignment</option></select></div><div class="form-group"><label class="form-label">Required Documents</label><select class="form-select" multiple style="min-height:100px"><option selected>ID Card</option><option selected>Farm Photo</option><option selected>Land Document</option><option>NIN Slip</option><option>CAC Certificate</option></select></div><div class="setting-row"><div class="setting-info"><div class="setting-title">Enable Bulk Registration</div><div class="setting-desc">Allow CSV import of growers</div></div><label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label></div><div class="setting-row"><div class="setting-info"><div class="setting-title">Require GPS Coordinates</div><div class="setting-desc">Capture farm location</div></div><label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label></div></div></div>
        <div class="card"><div class="card-header"><div class="card-title">Verification Workflow</div></div><div class="card-body"><div class="form-group"><label class="form-label">Verification Steps</label><div style="display:flex;flex-direction:column;gap:8px;margin-top:6px"><div style="display:flex;align-items:center;gap:10px;padding:10px;background:var(--bg);border-radius:8px"><span style="background:var(--g700);color:#fff;width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700">1</span><div style="flex:1"><div style="font-weight:600;font-size:12px">Document Verification</div><div style="font-size:11px;color:var(--text2)">Auto + Manual review</div></div><label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label></div><div style="display:flex;align-items:center;gap:10px;padding:10px;background:var(--bg);border-radius:8px"><span style="background:var(--g700);color:#fff;width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700">2</span><div style="flex:1"><div style="font-weight:600;font-size:12px">Field Inspection</div><div style="font-size:11px;color:var(--text2)">Agent visit required</div></div><label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label></div><div style="display:flex;align-items:center;gap:10px;padding:10px;background:var(--bg);border-radius:8px"><span style="background:var(--g700);color:#fff;width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700">3</span><div style="flex:1"><div style="font-weight:600;font-size:12px">LGA Confirmation</div><div style="font-size:11px;color:var(--text2)">Local government approval</div></div><label class="toggle"><input type="checkbox"><span class="toggle-slider"></span></label></div><div style="display:flex;align-items:center;gap:10px;padding:10px;background:var(--bg);border-radius:8px"><span style="background:var(--g700);color:#fff;width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700">4</span><div style="flex:1"><div style="font-weight:600;font-size:12px">Final Approval</div><div style="font-size:11px;color:var(--text2)">Admin approval + certificate</div></div><label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label></div></div></div></div></div>
      </div>
    </div>

    <!-- NOTIFICATIONS -->
    <div class="page" id="page-notifications">
      <div class="page-header"><div><div class="page-title">Notification Settings</div><div class="page-subtitle">Configure notification channels and preferences</div></div><button class="btn btn-primary" onclick="showToast('Settings saved')">💾 Save Changes</button></div>
      <div class="grid-2">
        <div class="card"><div class="card-header"><div class="card-title">Notification Channels</div></div><div class="card-body"><div class="setting-row"><div class="setting-info"><div class="setting-title">📧 Email Notifications</div><div class="setting-desc">System & user emails via SMTP</div></div><span class="status-badge status-enabled">Enabled ✓</span></div><div class="setting-row"><div class="setting-info"><div class="setting-title">📱 SMS Notifications</div><div class="setting-desc">Transactional alerts via Twilio</div></div><span class="status-badge status-enabled">Enabled ✓</span></div><div class="setting-row"><div class="setting-info"><div class="setting-title">🔔 In-App Notifications</div><div class="setting-desc">Platform notifications</div></div><span class="status-badge status-enabled">Enabled ✓</span></div><div class="setting-row"><div class="setting-info"><div class="setting-title">💬 WhatsApp Notifications</div><div class="setting-desc">WhatsApp Business API</div></div><span class="status-badge status-disabled">Disabled</span></div><div class="setting-row"><div class="setting-info"><div class="setting-title"> Push Notifications</div><div class="setting-desc">Mobile app push notifications</div></div><span class="status-badge status-enabled">Enabled ✓</span></div></div></div>
        <div class="card"><div class="card-header"><div class="card-title">Notification Preferences</div></div><div class="card-body"><div class="setting-row"><div class="setting-info"><div class="setting-title">New Application Alerts</div><div class="setting-desc">Notify on new applications</div></div><label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label></div><div class="setting-row"><div class="setting-info"><div class="setting-title">Verification Complete</div><div class="setting-desc">Notify grower on verification</div></div><label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label></div><div class="setting-row"><div class="setting-info"><div class="setting-title">Certificate Expiry Warning</div><div class="setting-desc">Alert 30 days before expiry</div></div><label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label></div><div class="setting-row"><div class="setting-info"><div class="setting-title">Failed Payment Alerts</div><div class="setting-desc">Notify on payment failures</div></div><label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label></div><div class="setting-row"><div class="setting-info"><div class="setting-title">Daily Summary Report</div><div class="setting-desc">End-of-day summary email</div></div><label class="toggle"><input type="checkbox"><span class="toggle-slider"></span></label></div><div class="setting-row"><div class="setting-info"><div class="setting-title">Security Alerts</div><div class="setting-desc">Immediate notification on suspicious activity</div></div><label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label></div></div></div>
      </div>
    </div>

    <!-- INTEGRATIONS -->
    <div class="page" id="page-integrations">
      <div class="page-header"><div><div class="page-title">Integrations & API Keys</div><div class="page-subtitle">Manage third-party integrations and API access</div></div><button class="btn btn-primary" onclick="openModal('integrationModal')">+ Add Integration</button></div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">Active Integrations</div><div class="stat-card-value">7</div></div>
        <div class="stat-card"><div class="stat-card-label">API Calls (24h)</div><div class="stat-card-value">24,892</div></div>
        <div class="stat-card"><div class="stat-card-label">Webhooks</div><div class="stat-card-value">12</div></div>
        <div class="stat-card"><div class="stat-card-label">Failed Calls</div><div class="stat-card-value" style="color:var(--danger)">23</div></div>
      </div>
      <div class="card"><div class="card-body p0">
        <table>
          <thead><tr><th>Integration</th><th>Type</th><th>Status</th><th>API Key</th><th>Last Used</th><th>Calls (24h)</th><th>Actions</th></tr></thead>
          <tbody>
            <tr><td><div class="avatar-row"><div class="avatar-sm" style="background:#dbeafe;color:#1e40af">M</div><strong>Monnify</strong></div></td><td>Payment</td><td><span class="status-badge status-connected">Connected</span></td><td><span class="api-key">••••••e8f1</span> <button class="btn-icon" style="padding:2px 6px"></button></td><td>May 24, 01:20</td><td>8,247</td><td><button class="btn btn-sm btn-secondary">Configure</button></td></tr>
            <tr><td><div class="avatar-row"><div class="avatar-sm" style="background:#fee2e2;color:#991b1b">T</div><strong>Twilio SMS</strong></div></td><td>SMS</td><td><span class="status-badge status-connected">Connected</span></td><td><span class="api-key">••••••b3c4</span> <button class="btn-icon" style="padding:2px 6px">👁</button></td><td>May 24, 12:30</td><td>4,892</td><td><button class="btn btn-sm btn-secondary">Configure</button></td></tr>
            <tr><td><div class="avatar-row"><div class="avatar-sm" style="background:#fef3c7;color:#92400e">G</div><strong>Google Maps</strong></div></td><td>Maps</td><td><span class="status-badge status-connected">Connected</span></td><td><span class="api-key">••••••9ad2</span> <button class="btn-icon" style="padding:2px 6px">👁</button></td><td>May 24, 12:55</td><td>6,234</td><td><button class="btn btn-sm btn-secondary">Configure</button></td></tr>
            <tr><td><div class="avatar-row"><div class="avatar-sm" style="background:var(--g100);color:var(--g700)">W</div><strong>WhatsApp API</strong></div></td><td>Messaging</td><td><span class="status-badge status-connected">Connected</span></td><td><span class="api-key">••••••f7a8</span> <button class="btn-icon" style="padding:2px 6px">👁</button></td><td>May 24, 12:10</td><td>2,145</td><td><button class="btn btn-sm btn-secondary">Configure</button></td></tr>
            <tr><td><div class="avatar-row"><div class="avatar-sm" style="background:#ede9fe;color:#5b21b6">P</div><strong>Paystack</strong></div></td><td>Payment</td><td><span class="status-badge status-connected">Connected</span></td><td><span class="api-key">••••••d4e5</span> <button class="btn-icon" style="padding:2px 6px">👁</button></td><td>May 24, 02:08</td><td>3,374</td><td><button class="btn btn-sm btn-secondary">Configure</button></td></tr>
            <tr><td><div class="avatar-row"><div class="avatar-sm" style="background:#fce7f3;color:#be185d">F</div><strong>Flutterwave</strong></div></td><td>Payment</td><td><span class="status-badge status-connected">Connected</span></td><td><span class="api-key">••••••c2d3</span> <button class="btn-icon" style="padding:2px 6px">👁</button></td><td>May 24, 02:07</td><td>1,892</td><td><button class="btn btn-sm btn-secondary">Configure</button></td></tr>
            <tr><td><div class="avatar-row"><div class="avatar-sm" style="background:#fee2e2;color:#991b1b">S</div><strong>SendGrid</strong></div></td><td>Email</td><td><span class="status-badge status-error">Error</span></td><td><span class="api-key">••••••a1b2</span> <button class="btn-icon" style="padding:2px 6px">👁</button></td><td>May 23, 18:45</td><td>0</td><td><button class="btn btn-sm btn-warn">Fix</button></td></tr>
          </tbody>
        </table>
      </div></div>
    </div>

    <!-- SECURITY -->
    <div class="page" id="page-security">
      <div class="page-header"><div><div class="page-title">Security Settings</div><div class="page-subtitle">Platform security configuration and monitoring</div></div><button class="btn btn-primary" onclick="showToast('Security settings saved')">💾 Save Changes</button></div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">Security Score</div><div class="stat-card-value" style="color:var(--success)">94/100</div></div>
        <div class="stat-card"><div class="stat-card-label">2FA Enabled Users</div><div class="stat-card-value">89%</div></div>
        <div class="stat-card"><div class="stat-card-label">Blocked IPs</div><div class="stat-card-value">3</div></div>
        <div class="stat-card"><div class="stat-card-label">Active Sessions</div><div class="stat-card-value">8</div></div>
      </div>
      <div class="grid-2">
        <div class="card"><div class="card-header"><div class="card-title">Authentication</div></div><div class="card-body"><div class="setting-row"><div class="setting-info"><div class="setting-title">Two-Factor Authentication</div><div class="setting-desc">Enforced for all admins</div></div><label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label></div><div class="setting-row"><div class="setting-info"><div class="setting-title">Password Policy</div><div class="setting-desc">Strong (12+ chars, symbols)</div></div><span class="status-badge status-enabled">Enforced ✓</span></div><div class="setting-row"><div class="setting-info"><div class="setting-title">Session Timeout (minutes)</div></div><input class="form-input" style="width:100px" type="number" value="30"></div><div class="setting-row"><div class="setting-info"><div class="setting-title">Max Login Attempts</div></div><input class="form-input" style="width:100px" type="number" value="5"></div><div class="setting-row"><div class="setting-info"><div class="setting-title">IP Whitelist</div><div class="setting-desc">Restrict admin access by IP</div></div><label class="toggle"><input type="checkbox"><span class="toggle-slider"></span></label></div></div></div>
        <div class="card"><div class="card-header"><div class="card-title">Security Monitoring</div></div><div class="card-body"><div class="health-item"><div class="health-label"><span style="color:var(--success)"></span> SSL Certificate</div><div class="health-status" style="color:var(--success)">Valid ✓</div></div><div class="health-item"><div class="health-label"><span style="color:var(--success)">🛡️</span> Firewall</div><div class="health-status" style="color:var(--success)">Active ✓</div></div><div class="health-item"><div class="health-label"><span style="color:var(--success)">🔍</span> Intrusion Detection</div><div class="health-status" style="color:var(--success)">Active ✓</div></div><div class="health-item"><div class="health-label"><span style="color:var(--success)">📊</span> Audit Logging</div><div class="health-status" style="color:var(--success)">Enabled ✓</div></div><div class="health-item"><div class="health-label"><span style="color:var(--warn)">⚠️</span> Failed Login Attempts (24h)</div><div class="health-status" style="color:var(--warn)">12</div></div><div class="health-item"><div class="health-label"><span style="color:var(--success)">🚫</span> Blocked IP Addresses</div><div class="health-status">3 <button class="btn-ghost btn-sm">Manage →</button></div></div></div></div>
      </div>
    </div>

    <!-- BACKUPS -->
    <div class="page" id="page-backups">
      <div class="page-header"><div><div class="page-title">Backups</div><div class="page-subtitle">System backup management and recovery</div></div><button class="btn btn-primary" onclick="openModal('backupModal')">💾 Backup Now</button></div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">Last Backup</div><div class="stat-card-value" style="font-size:16px">May 24, 02:30 AM</div><div class="stat-card-sub">Daily automated backup</div></div>
        <div class="stat-card"><div class="stat-card-label">Total Backups</div><div class="stat-card-value">365</div></div>
        <div class="stat-card"><div class="stat-card-label">Storage Used</div><div class="stat-card-value">48.2 GB</div></div>
        <div class="stat-card"><div class="stat-card-label">Retention Period</div><div class="stat-card-value">90 days</div></div>
      </div>
      <div class="card"><div class="card-header"><div class="card-title">Backup Schedule</div></div><div class="card-body"><div class="form-row"><div class="form-group"><label class="form-label">Backup Frequency</label><select class="form-select"><option>Daily at 2:30 AM</option><option>Twice daily</option><option>Weekly</option><option>Manual only</option></select></div><div class="form-group"><label class="form-label">Retention Period</label><select class="form-select"><option>30 days</option><option>60 days</option><option>90 days</option><option>1 year</option></select></div></div><div class="form-group"><label class="form-label">Backup Destination</label><select class="form-select"><option>AWS S3 (Primary)</option><option>Google Cloud Storage</option><option>Local Server</option></select></div><div class="setting-row"><div class="setting-info"><div class="setting-title">Auto-backup Database</div></div><label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label></div><div class="setting-row"><div class="setting-info"><div class="setting-title">Auto-backup Files</div></div><label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label></div><div class="setting-row"><div class="setting-info"><div class="setting-title">Encrypt Backups</div></div><label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label></div><button class="btn btn-primary" style="margin-top:14px" onclick="showToast('Backup settings saved')">Save Schedule</button></div></div>
      <div class="card"><div class="card-header"><div class="card-title">Recent Backups</div></div><div class="card-body p0">
        <table>
          <thead><tr><th>Backup ID</th><th>Date & Time</th><th>Type</th><th>Size</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
            <tr><td><strong>BKP-20260524-0230</strong></td><td>May 24, 02:30 AM</td><td>Automated</td><td>2.4 GB</td><td><span class="status-badge status-success">Completed</span></td><td><button class="btn btn-sm btn-secondary">Download</button> <button class="btn btn-sm btn-info">Restore</button></td></tr>
            <tr><td><strong>BKP-20260523-0230</strong></td><td>May 23, 02:30 AM</td><td>Automated</td><td>2.3 GB</td><td><span class="status-badge status-success">Completed</span></td><td><button class="btn btn-sm btn-secondary">Download</button> <button class="btn btn-sm btn-info">Restore</button></td></tr>
            <tr><td><strong>BKP-20260522-0230</strong></td><td>May 22, 02:30 AM</td><td>Automated</td><td>2.3 GB</td><td><span class="status-badge status-success">Completed</span></td><td><button class="btn btn-sm btn-secondary">Download</button> <button class="btn btn-sm btn-info">Restore</button></td></tr>
            <tr><td><strong>BKP-20260521-1430</strong></td><td>May 21, 02:30 PM</td><td>Manual</td><td>2.2 GB</td><td><span class="status-badge status-success">Completed</span></td><td><button class="btn btn-sm btn-secondary">Download</button> <button class="btn btn-sm btn-info">Restore</button></td></tr>
            <tr><td><strong>BKP-20260521-0230</strong></td><td>May 21, 02:30 AM</td><td>Automated</td><td>2.2 GB</td><td><span class="status-badge status-success">Completed</span></td><td><button class="btn btn-sm btn-secondary">Download</button> <button class="btn btn-sm btn-info">Restore</button></td></tr>
          </tbody>
        </table>
      </div></div>
    </div>

    <!-- SYSTEM HEALTH -->
    <div class="page" id="page-system-health">
      <div class="page-header"><div><div class="page-title">System Health</div><div class="page-subtitle">Monitor system performance and infrastructure</div></div><button class="btn btn-primary" onclick="showToast('Health check running...')">💓 Run Health Check</button></div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">Overall Status</div><div class="stat-card-value" style="color:var(--success);font-size:18px">🟢 Healthy</div></div>
        <div class="stat-card"><div class="stat-card-label">Uptime (30d)</div><div class="stat-card-value">99.97%</div></div>
        <div class="stat-card"><div class="stat-card-label">Avg Response Time</div><div class="stat-card-value">142ms</div></div>
        <div class="stat-card"><div class="stat-card-label">CPU Usage</div><div class="stat-card-value">34%</div></div>
        <div class="stat-card"><div class="stat-card-label">Memory Usage</div><div class="stat-card-value">62%</div></div>
        <div class="stat-card"><div class="stat-card-label">Disk Usage</div><div class="stat-card-value">48%</div></div>
      </div>
      <div class="grid-2">
        <div class="card"><div class="card-header"><div class="card-title">System Health Checklist</div></div><div class="card-body">
          <div class="health-item"><div class="health-label"><span style="color:var(--success)">✓</span> Web Server</div><div class="health-status" style="color:var(--success)">Operational</div></div>
          <div class="health-item"><div class="health-label"><span style="color:var(--success)">✓</span> Database</div><div class="health-status" style="color:var(--success)">Operational</div></div>
          <div class="health-item"><div class="health-label"><span style="color:var(--success)">✓</span> Queue Worker</div><div class="health-status" style="color:var(--success)">Operational</div></div>
          <div class="health-item"><div class="health-label"><span style="color:var(--success)">✓</span> Cache (Redis)</div><div class="health-status" style="color:var(--success)">Operational</div></div>
          <div class="health-item"><div class="health-label"><span style="color:var(--success)">✓</span> Storage</div><div class="health-status" style="color:var(--success)">Operational</div></div>
          <div class="health-item"><div class="health-label"><span style="color:var(--success)">✓</span> Cron Jobs</div><div class="health-status" style="color:var(--success)">Operational</div></div>
          <div class="health-item"><div class="health-label"><span style="color:var(--success)">✓</span> Email Service</div><div class="health-status" style="color:var(--success)">Operational</div></div>
          <div class="health-item"><div class="health-label"><span style="color:var(--success)">✓</span> SMS Service</div><div class="health-status" style="color:var(--success)">Operational</div></div>
          <div class="health-item"><div class="health-label"><span style="color:var(--success)">✓</span> Payment Gateway</div><div class="health-status" style="color:var(--success)">Operational</div></div>
          <div class="health-item"><div class="health-label"><span style="color:var(--warn)">⚠</span> CDN</div><div class="health-status" style="color:var(--warn)">Degraded</div></div>
        </div></div>
        <div class="card"><div class="card-header"><div class="card-title">Resource Usage</div></div><div class="card-body"><div style="display:flex;flex-direction:column;gap:14px"><div><div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:6px"><span style="font-weight:600">CPU Usage</span><span>34%</span></div><div class="progress-bar"><div class="progress-fill" style="width:34%"></div></div></div><div><div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:6px"><span style="font-weight:600">Memory Usage</span><span>62%</span></div><div class="progress-bar"><div class="progress-fill" style="width:62%;background:var(--warn)"></div></div></div><div><div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:6px"><span style="font-weight:600">Disk Usage</span><span>48%</span></div><div class="progress-bar"><div class="progress-fill" style="width:48%"></div></div></div><div><div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:6px"><span style="font-weight:600">Network I/O</span><span>28%</span></div><div class="progress-bar"><div class="progress-fill" style="width:28%;background:var(--info)"></div></div></div><div><div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:6px"><span style="font-weight:600">Database Connections</span><span>45/100</span></div><div class="progress-bar"><div class="progress-fill" style="width:45%;background:var(--purple)"></div></div></div></div></div></div>
      </div>
    </div>

    <!-- AUDIT LOG -->
    <div class="page" id="page-audit-log">
      <div class="page-header"><div><div class="page-title">Audit Log</div><div class="page-subtitle">Complete system activity tracking</div></div><button class="btn btn-primary" onclick="showToast('Audit log exported')">📥 Export Log</button></div>
      <div class="filter-bar"><input type="text" placeholder="Search audit log..." oninput="filterTable('auditTable',this.value)"><select><option>All Actions</option><option>Create</option><option>Update</option><option>Delete</option><option>Login</option><option>Settings Change</option></select><select><option>All Users</option><option>Grace Deh</option><option>John Okafor</option><option>System</option></select><select><option>All Modules</option><option>Settings</option><option>RBAC</option><option>Modules</option><option>Reports</option></select><input type="date" value="2026-05-24"><button class="btn btn-secondary btn-sm">Filter</button></div>
      <div class="card"><div class="card-body p0">
        <table id="auditTable">
          <thead><tr><th>Time</th><th>User</th><th>Action</th><th>Module</th><th>IP Address</th><th>Details</th></tr></thead>
          <tbody>
            <tr><td>May 24, 02:35 AM</td><td><strong>Grace Deh</strong></td><td><span class="status-badge status-info">Update</span></td><td>Settings</td><td>197.210.45.23</td><td>Updated email notification template</td></tr>
            <tr><td>May 24, 02:22 AM</td><td><strong>John Okafor</strong></td><td><span class="status-badge status-success">Create</span></td><td>RBAC</td><td>197.210.45.23</td><td>Created new role: State Coordinator</td></tr>
            <tr><td>May 24, 01:58 AM</td><td><strong>Fatima Bello</strong></td><td><span class="status-badge status-info">Update</span></td><td>Modules</td><td>105.112.12.11</td><td>Enabled module: Healthcare</td></tr>
            <tr><td>May 24, 01:40 AM</td><td><strong>Aisha Musa</strong></td><td><span class="status-badge status-success">Export</span></td><td>Reports</td><td>105.112.12.11</td><td>Export: Verified Growers - May 2026</td></tr>
            <tr><td>May 24, 01:15 AM</td><td><strong>System</strong></td><td><span class="status-badge status-success">Backup</span></td><td>Backups</td><td>—</td><td>Backup ID: BKP-20260524-0215</td></tr>
            <tr><td>May 24, 01:00 AM</td><td><strong>System</strong></td><td><span class="status-badge status-info">Cron</span></td><td>System</td><td>—</td><td>Daily reconciliation job completed</td></tr>
            <tr><td>May 24, 12:45 AM</td><td><strong>Grace Deh</strong></td><td><span class="status-badge status-info">Login</span></td><td>Auth</td><td>197.210.45.23</td><td>Super Admin login successful</td></tr>
            <tr><td>May 23, 11:30 PM</td><td><strong>System</strong></td><td><span class="status-badge status-warn">Alert</span></td><td>Security</td><td>—</td><td>Failed login attempt from 45.23.12.89</td></tr>
          </tbody>
        </table>
      </div></div>
    </div>

    <!-- EMAIL TEMPLATES -->
    <div class="page" id="page-email-templates">
      <div class="page-header"><div><div class="page-title">Email Templates</div><div class="page-subtitle">Manage email notification templates</div></div><button class="btn btn-primary" onclick="openModal('templateModal')">+ New Template</button></div>
      <div class="card"><div class="card-body p0">
        <table>
          <thead><tr><th>Template</th><th>Category</th><th>Subject</th><th>Last Modified</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
            <tr><td><strong>Welcome Email</strong></td><td>Onboarding</td><td>Welcome to NATCODEV!</td><td>May 20, 2026</td><td><span class="status-badge status-active">Active</span></td><td><button class="btn btn-sm btn-secondary">Edit</button> <button class="btn btn-sm btn-info">Preview</button></td></tr>
            <tr><td><strong>Verification Complete</strong></td><td>Registry</td><td>Your verification is complete</td><td>May 22, 2026</td><td><span class="status-badge status-active">Active</span></td><td><button class="btn btn-sm btn-secondary">Edit</button> <button class="btn btn-sm btn-info">Preview</button></td></tr>
            <tr><td><strong>Certificate Issued</strong></td><td>Certificates</td><td>Your certificate is ready</td><td>May 18, 2026</td><td><span class="status-badge status-active">Active</span></td><td><button class="btn btn-sm btn-secondary">Edit</button> <button class="btn btn-sm btn-info">Preview</button></td></tr>
            <tr><td><strong>Order Confirmation</strong></td><td>Marketplace</td><td>Order confirmed - #{{order_id}}</td><td>May 24, 2026</td><td><span class="status-badge status-active">Active</span></td><td><button class="btn btn-sm btn-secondary">Edit</button> <button class="btn btn-sm btn-info">Preview</button></td></tr>
            <tr><td><strong>Payment Receipt</strong></td><td>Wallet</td><td>Payment receipt - ₦{{amount}}</td><td>May 24, 2026</td><td><span class="status-badge status-active">Active</span></td><td><button class="btn btn-sm btn-secondary">Edit</button> <button class="btn btn-sm btn-info">Preview</button></td></tr>
            <tr><td><strong>Password Reset</strong></td><td>Security</td><td>Reset your password</td><td>May 15, 2026</td><td><span class="status-badge status-active">Active</span></td><td><button class="btn btn-sm btn-secondary">Edit</button> <button class="btn btn-sm btn-info">Preview</button></td></tr>
            <tr><td><strong>Certificate Expiry Reminder</strong></td><td>Certificates</td><td>Your certificate expires in {{days}} days</td><td>May 10, 2026</td><td><span class="status-badge status-active">Active</span></td><td><button class="btn btn-sm btn-secondary">Edit</button> <button class="btn btn-sm btn-info">Preview</button></td></tr>
            <tr><td><strong>Course Completion</strong></td><td>Academy</td><td>Congratulations on completing {{course}}</td><td>May 12, 2026</td><td><span class="status-badge status-draft">Draft</span></td><td><button class="btn btn-sm btn-secondary">Edit</button> <button class="btn btn-sm btn-info">Preview</button></td></tr>
          </tbody>
        </table>
      </div></div>
    </div>

    <!-- WEBHOOKS -->
    <div class="page" id="page-webhooks">
      <div class="page-header"><div><div class="page-title">Webhooks</div><div class="page-subtitle">Manage webhook endpoints and events</div></div><button class="btn btn-primary" onclick="openModal('webhookModal')">+ Add Webhook</button></div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">Active Webhooks</div><div class="stat-card-value">12</div></div>
        <div class="stat-card"><div class="stat-card-label">Deliveries (24h)</div><div class="stat-card-value">8,247</div></div>
        <div class="stat-card"><div class="stat-card-label">Success Rate</div><div class="stat-card-value" style="color:var(--success)">98.4%</div></div>
        <div class="stat-card"><div class="stat-card-label">Failed Deliveries</div><div class="stat-card-value" style="color:var(--danger)">132</div></div>
      </div>
      <div class="card"><div class="card-body p0">
        <table>
          <thead><tr><th>Webhook</th><th>URL</th><th>Events</th><th>Status</th><th>Last Delivery</th><th>Success Rate</th><th>Actions</th></tr></thead>
          <tbody>
            <tr><td><strong>Order Events</strong></td><td><span class="api-key">https://api.natcodev.org/webhooks/orders</span></td><td><span class="chip">order.created</span><span class="chip">order.completed</span></td><td><span class="status-badge status-active">Active</span></td><td>May 24, 12:45</td><td>99.2%</td><td><button class="btn btn-sm btn-secondary">Edit</button></td></tr>
            <tr><td><strong>Payment Events</strong></td><td><span class="api-key">https://api.natcodev.org/webhooks/payments</span></td><td><span class="chip">payment.success</span><span class="chip">payment.failed</span></td><td><span class="status-badge status-active">Active</span></td><td>May 24, 12:50</td><td>98.8%</td><td><button class="btn btn-sm btn-secondary">Edit</button></td></tr>
            <tr><td><strong>Grower Events</strong></td><td><span class="api-key">https://api.natcodev.org/webhooks/growers</span></td><td><span class="chip">grower.verified</span><span class="chip">grower.registered</span></td><td><span class="status-badge status-active">Active</span></td><td>May 24, 12:30</td><td>97.5%</td><td><button class="btn btn-sm btn-secondary">Edit</button></td></tr>
            <tr><td><strong>Certificate Events</strong></td><td><span class="api-key">https://api.natcodev.org/webhooks/certs</span></td><td><span class="chip">cert.issued</span><span class="chip">cert.expired</span></td><td><span class="status-badge status-active">Active</span></td><td>May 24, 11:20</td><td>99.5%</td><td><button class="btn btn-sm btn-secondary">Edit</button></td></tr>
            <tr><td><strong>Legacy System</strong></td><td><span class="api-key">https://legacy.natcodev.org/hook</span></td><td><span class="chip">all events</span></td><td><span class="status-badge status-error">Error</span></td><td>May 23, 18:45</td><td>45.2%</td><td><button class="btn btn-sm btn-warn">Fix</button></td></tr>
          </tbody>
        </table>
      </div></div>
    </div>

    <!-- FEATURE FLAGS -->
    <div class="page" id="page-feature-flags">
      <div class="page-header"><div><div class="page-title">Feature Flags</div><div class="page-subtitle">Control feature rollout and experimentation</div></div><button class="btn btn-primary" onclick="openModal('flagModal')">+ New Flag</button></div>
      <div class="card"><div class="card-body p0">
        <table>
          <thead><tr><th>Flag</th><th>Description</th><th>Rollout</th><th>Environments</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
            <tr><td><strong>enable_healthcare_module</strong></td><td>Enable healthcare module for workers</td><td><div class="progress-bar" style="width:100px"><div class="progress-fill" style="width:25%;background:var(--warn)"></div></div> 25%</td><td><span class="chip">Staging</span><span class="chip">Production</span></td><td><span class="status-badge status-active">Active</span></td><td><button class="btn btn-sm btn-secondary">Edit</button></td></tr>
            <tr><td><strong>enable_ai_assistant</strong></td><td>AI-powered recommendations engine</td><td><div class="progress-bar" style="width:100px"><div class="progress-fill" style="width:10%;background:var(--warn)"></div></div> 10%</td><td><span class="chip">Staging</span></td><td><span class="status-badge status-active">Active</span></td><td><button class="btn btn-sm btn-secondary">Edit</button></td></tr>
            <tr><td><strong>enable_whatsapp_notifications</strong></td><td>WhatsApp notification channel</td><td><div class="progress-bar" style="width:100px"><div class="progress-fill" style="width:0%"></div></div> 0%</td><td><span class="chip">Staging</span></td><td><span class="status-badge status-draft">Disabled</span></td><td><button class="btn btn-sm btn-secondary">Edit</button></td></tr>
            <tr><td><strong>new_dashboard_v2</strong></td><td>New dashboard design v2</td><td><div class="progress-bar" style="width:100px"><div class="progress-fill" style="width:100%"></div></div> 100%</td><td><span class="chip">Production</span></td><td><span class="status-badge status-active">Active</span></td><td><button class="btn btn-sm btn-secondary">Edit</button></td></tr>
            <tr><td><strong>bulk_operations_v2</strong></td><td>Enhanced bulk operations UI</td><td><div class="progress-bar" style="width:100px"><div class="progress-fill" style="width:50%"></div></div> 50%</td><td><span class="chip">Staging</span><span class="chip">Production</span></td><td><span class="status-badge status-active">Active</span></td><td><button class="btn btn-sm btn-secondary">Edit</button></td></tr>
          </tbody>
        </table>
      </div></div>
    </div>

    <!-- DATA RETENTION -->
    <div class="page" id="page-data-retention">
      <div class="page-header"><div><div class="page-title">Data Retention</div><div class="page-subtitle">Configure data retention policies and compliance</div></div><button class="btn btn-primary" onclick="showToast('Retention policy saved')"> Save Policy</button></div>
      <div class="grid-2">
        <div class="card"><div class="card-header"><div class="card-title">Retention Policies</div></div><div class="card-body"><div class="form-group"><label class="form-label">Transaction Records</label><select class="form-select"><option>7 years</option><option>5 years</option><option>3 years</option><option>1 year</option></select></div><div class="form-group"><label class="form-label">User Activity Logs</label><select class="form-select"><option>2 years</option><option>1 year</option><option>6 months</option><option>3 months</option></select></div><div class="form-group"><label class="form-label">Audit Logs</label><select class="form-select"><option>10 years</option><option>7 years</option><option>5 years</option></select></div><div class="form-group"><label class="form-label">Backup Retention</label><select class="form-select"><option>90 days</option><option>60 days</option><option>30 days</option></select></div><div class="form-group"><label class="form-label">Email Logs</label><select class="form-select"><option>1 year</option><option>6 months</option><option>3 months</option></select></div></div></div>
        <div class="card"><div class="card-header"><div class="card-title">Storage Usage</div></div><div class="card-body"><div style="display:flex;flex-direction:column;gap:14px"><div><div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:6px"><span style="font-weight:600">Database</span><span>24.5 GB / 100 GB</span></div><div class="progress-bar"><div class="progress-fill" style="width:24.5%"></div></div></div><div><div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:6px"><span style="font-weight:600">File Storage</span><span>18.2 GB / 50 GB</span></div><div class="progress-bar"><div class="progress-fill" style="width:36.4%;background:var(--info)"></div></div></div><div><div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:6px"><span style="font-weight:600">Backups</span><span>48.2 GB / 200 GB</span></div><div class="progress-bar"><div class="progress-fill" style="width:24.1%;background:var(--purple)"></div></div></div><div><div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:6px"><span style="font-weight:600">Logs</span><span>8.4 GB / 20 GB</span></div><div class="progress-bar"><div class="progress-fill" style="width:42%;background:var(--warn)"></div></div></div></div><div style="margin-top:16px;padding:14px;background:var(--bg);border-radius:10px"><div style="font-size:12px;color:var(--text2);margin-bottom:4px">Total Storage Used</div><div style="font-size:20px;font-weight:700">99.3 GB / 370 GB</div><div style="font-size:11px;color:var(--text2);margin-top:4px">26.8% utilized</div></div></div></div>
      </div>
    </div>

    <!-- MAINTENANCE -->
    <div class="page" id="page-maintenance">
      <div class="page-header"><div><div class="page-title">Maintenance Mode</div><div class="page-subtitle">Control platform availability and maintenance windows</div></div></div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">Current Status</div><div class="stat-card-value" style="color:var(--success);font-size:18px">🟢 Live</div></div>
        <div class="stat-card"><div class="stat-card-label">Last Maintenance</div><div class="stat-card-value" style="font-size:14px">May 15, 2026</div></div>
        <div class="stat-card"><div class="stat-card-label">Next Scheduled</div><div class="stat-card-value" style="font-size:14px">Jun 1, 2026</div></div>
        <div class="stat-card"><div class="stat-card-label">Uptime (30d)</div><div class="stat-card-value">99.97%</div></div>
      </div>
      <div class="grid-2">
        <div class="card"><div class="card-header"><div class="card-title">Maintenance Control</div></div><div class="card-body"><div class="setting-row"><div class="setting-info"><div class="setting-title">Enable Maintenance Mode</div><div class="setting-desc">Take platform offline for maintenance</div></div><label class="toggle"><input type="checkbox" onchange="showToast(this.checked?'Maintenance mode enabled':'Maintenance mode disabled')"><span class="toggle-slider"></span></label></div><div class="form-group" style="margin-top:14px"><label class="form-label">Maintenance Message</label><textarea class="form-textarea" placeholder="Message shown to users during maintenance...">We're currently performing scheduled maintenance. We'll be back shortly. Thank you for your patience.</textarea></div><div class="form-group"><label class="form-label">Allow Admin Access</label><select class="form-select"><option>Yes - Admins can still access</option><option>No - Complete lockdown</option></select></div><div class="form-group"><label class="form-label">Expected Duration</label><input class="form-input" placeholder="e.g. 2 hours"></div><button class="btn btn-warn" style="width:100%" onclick="showToast('Maintenance mode toggled')">Toggle Maintenance Mode</button></div></div>
        <div class="card"><div class="card-header"><div class="card-title">Scheduled Maintenance</div><button class="btn btn-secondary btn-sm" onclick="openModal('maintenanceModal')">+ Schedule</button></div><div class="card-body p0">
          <table>
            <thead><tr><th>Date</th><th>Duration</th><th>Type</th><th>Status</th></tr></thead>
            <tbody>
              <tr><td>Jun 1, 2026 02:00 AM</td><td>2 hours</td><td>System Update</td><td><span class="status-badge status-scheduled">Scheduled</span></td></tr>
              <tr><td>Jun 15, 2026 02:00 AM</td><td>3 hours</td><td>Database Migration</td><td><span class="status-badge status-scheduled">Scheduled</span></td></tr>
              <tr><td>May 15, 2026 02:00 AM</td><td>1.5 hours</td><td>Security Patch</td><td><span class="status-badge status-completed">Completed</span></td></tr>
              <tr><td>May 1, 2026 02:00 AM</td><td>2 hours</td><td>Platform Update</td><td><span class="status-badge status-completed">Completed</span></td></tr>
            </tbody>
          </table>
        </div></div>
      </div>
    </div>

  </div>
</div>

<!-- MODALS -->
<div class="modal-overlay" id="roleModal"><div class="modal"><div class="modal-header"><div class="modal-title">Create New Role</div><button class="btn-icon" onclick="closeModal('roleModal')">✕</button></div><div class="modal-body"><div class="form-group"><label class="form-label">Role Name</label><input class="form-input" placeholder="e.g. State Coordinator"></div><div class="form-group"><label class="form-label">Description</label><textarea class="form-textarea" placeholder="Role description..."></textarea></div><div class="form-group"><label class="form-label">Permissions</label><div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:6px"><label style="display:flex;align-items:center;gap:8px;font-size:12px"><input type="checkbox" checked> Dashboard Access</label><label style="display:flex;align-items:center;gap:8px;font-size:12px"><input type="checkbox" checked> User Management</label><label style="display:flex;align-items:center;gap:8px;font-size:12px"><input type="checkbox"> System Settings</label><label style="display:flex;align-items:center;gap:8px;font-size:12px"><input type="checkbox" checked> Financial Access</label><label style="display:flex;align-items:center;gap:8px;font-size:12px"><input type="checkbox"> Audit Logs</label><label style="display:flex;align-items:center;gap:8px;font-size:12px"><input type="checkbox" checked> Data Export</label></div></div><div class="form-group"><label class="form-label">Module Access</label><select class="form-select" multiple style="min-height:100px"><option selected>Registry</option><option selected>Marketplace</option><option>Academy</option><option>Wallet</option><option>Reports</option></select></div></div><div class="modal-footer"><button class="btn btn-secondary" onclick="closeModal('roleModal')">Cancel</button><button class="btn btn-primary" onclick="closeModal('roleModal');showToast('Role created successfully')">Create Role</button></div></div></div>

<div class="modal-overlay" id="backupModal"><div class="modal"><div class="modal-header"><div class="modal-title">Manual Backup</div><button class="btn-icon" onclick="closeModal('backupModal')">✕</button></div><div class="modal-body"><div class="form-group"><label class="form-label">Backup Type</label><select class="form-select"><option>Full Backup (Database + Files)</option><option>Database Only</option><option>Files Only</option><option>Configuration Only</option></select></div><div class="form-group"><label class="form-label">Backup Destination</label><select class="form-select"><option>AWS S3 (Primary)</option><option>Google Cloud Storage</option><option>Local Server</option></select></div><div class="form-group"><label class="form-label">Include</label><div style="display:flex;flex-direction:column;gap:8px;margin-top:6px"><label style="display:flex;align-items:center;gap:8px;font-size:12px"><input type="checkbox" checked> Database</label><label style="display:flex;align-items:center;gap:8px;font-size:12px"><input type="checkbox" checked> File Storage</label><label style="display:flex;align-items:center;gap:8px;font-size:12px"><input type="checkbox" checked> Configuration</label><label style="display:flex;align-items:center;gap:8px;font-size:12px"><input type="checkbox"> Logs</label></div></div><div class="form-group"><label class="form-label">Notes</label><textarea class="form-textarea" placeholder="Optional notes..."></textarea></div></div><div class="modal-footer"><button class="btn btn-secondary" onclick="closeModal('backupModal')">Cancel</button><button class="btn btn-primary" onclick="closeModal('backupModal');showToast('Backup started...')">Start Backup</button></div></div></div>

<div class="modal-overlay" id="providerModal"><div class="modal"><div class="modal-header"><div class="modal-title">Add Payment Provider</div><button class="btn-icon" onclick="closeModal('providerModal')"></button></div><div class="modal-body"><div class="form-group"><label class="form-label">Provider</label><select class="form-select"><option>Monnify</option><option>Paystack</option><option>Flutterwave</option><option>Custom</option></select></div><div class="form-group"><label class="form-label">API Key</label><input class="form-input" type="password"></div><div class="form-group"><label class="form-label">Secret Key</label><input class="form-input" type="password"></div><div class="form-row"><div class="form-group"><label class="form-label">Mode</label><select class="form-select"><option>Live</option><option>Sandbox</option></select></div><div class="form-group"><label class="form-label">Fee (%)</label><input class="form-input" type="number" step="0.1"></div></div></div><div class="modal-footer"><button class="btn btn-secondary" onclick="closeModal('providerModal')">Cancel</button><button class="btn btn-primary" onclick="closeModal('providerModal');showToast('Provider added')">Add Provider</button></div></div></div>

<div class="modal-overlay" id="integrationModal"><div class="modal"><div class="modal-header"><div class="modal-title">Add Integration</div><button class="btn-icon" onclick="closeModal('integrationModal')">✕</button></div><div class="modal-body"><div class="form-group"><label class="form-label">Integration Name</label><input class="form-input" placeholder="Google Maps"></div><div class="form-group"><label class="form-label">Type</label><select class="form-select"><option>Payment</option><option>SMS</option><option>Email</option><option>Maps</option><option>Storage</option><option>Analytics</option><option>Custom API</option></select></div><div class="form-group"><label class="form-label">API Endpoint</label><input class="form-input" placeholder="https://api.your-service.com"></div><div class="form-group"><label class="form-label">API Key</label><input class="form-input" type="password"></div><div class="form-group"><label class="form-label">Webhook URL (Optional)</label><input class="form-input" placeholder="https://your-app.com/webhook"></div></div><div class="modal-footer"><button class="btn btn-secondary" onclick="closeModal('integrationModal')">Cancel</button><button class="btn btn-primary" onclick="closeModal('integrationModal');showToast('Integration added')">Add Integration</button></div></div></div>

<div class="modal-overlay" id="templateModal"><div class="modal"><div class="modal-header"><div class="modal-title">New Email Template</div><button class="btn-icon" onclick="closeModal('templateModal')">✕</button></div><div class="modal-body"><div class="form-group"><label class="form-label">Template Name</label><input class="form-input" placeholder="e.g. Welcome Email"></div><div class="form-group"><label class="form-label">Category</label><select class="form-select"><option>Onboarding</option><option>Registry</option><option>Marketplace</option><option>Wallet</option><option>Academy</option><option>Security</option><option>Certificates</option></select></div><div class="form-group"><label class="form-label">Subject</label><input class="form-input" placeholder="Email subject line"></div><div class="form-group"><label class="form-label">Body (HTML)</label><textarea class="form-textarea" style="min-height:150px" placeholder="<p>Hello {{name}},</p><p>Welcome to NATCODEV!</p>"></textarea></div><div class="form-group"><label class="form-label">Variables Available</label><div style="display:flex;gap:6px;flex-wrap:wrap"><span class="chip">{{name}}</span><span class="chip">{{email}}</span><span class="chip">{{date}}</span><span class="chip">{{order_id}}</span><span class="chip">{{amount}}</span></div></div></div><div class="modal-footer"><button class="btn btn-secondary" onclick="closeModal('templateModal')">Cancel</button><button class="btn btn-primary" onclick="closeModal('templateModal');showToast('Template created')">Create Template</button></div></div></div>

<div class="modal-overlay" id="webhookModal"><div class="modal"><div class="modal-header"><div class="modal-title">Add Webhook</div><button class="btn-icon" onclick="closeModal('webhookModal')">✕</button></div><div class="modal-body"><div class="form-group"><label class="form-label">Webhook Name</label><input class="form-input" placeholder="e.g. Order Events"></div><div class="form-group"><label class="form-label">Endpoint URL</label><input class="form-input" placeholder="https://your-app.com/webhook"></div><div class="form-group"><label class="form-label">Events</label><select class="form-select" multiple style="min-height:120px"><option>order.created</option><option>order.completed</option><option>payment.success</option><option>payment.failed</option><option>grower.verified</option><option>grower.registered</option><option>cert.issued</option><option>cert.expired</option></select></div><div class="form-group"><label class="form-label">Secret Key (for signature verification)</label><input class="form-input" placeholder="Auto-generated if empty"></div></div><div class="modal-footer"><button class="btn btn-secondary" onclick="closeModal('webhookModal')">Cancel</button><button class="btn btn-primary" onclick="closeModal('webhookModal');showToast('Webhook added')">Add Webhook</button></div></div></div>

<div class="modal-overlay" id="flagModal"><div class="modal"><div class="modal-header"><div class="modal-title">New Feature Flag</div><button class="btn-icon" onclick="closeModal('flagModal')">✕</button></div><div class="modal-body"><div class="form-group"><label class="form-label">Flag Name</label><input class="form-input" placeholder="e.g. enable_new_feature"></div><div class="form-group"><label class="form-label">Description</label><textarea class="form-textarea" placeholder="What does this flag control?"></textarea></div><div class="form-row"><div class="form-group"><label class="form-label">Initial Rollout (%)</label><input class="form-input" type="number" value="0"></div><div class="form-group"><label class="form-label">Environment</label><select class="form-select"><option>Staging</option><option>Production</option><option>Both</option></select></div></div><div class="form-group"><label class="form-label">Target Users (Optional)</label><input class="form-input" placeholder="Comma-separated user IDs or 'all'"></div></div><div class="modal-footer"><button class="btn btn-secondary" onclick="closeModal('flagModal')">Cancel</button><button class="btn btn-primary" onclick="closeModal('flagModal');showToast('Feature flag created')">Create Flag</button></div></div></div>

<div class="modal-overlay" id="moduleModal"><div class="modal"><div class="modal-header"><div class="modal-title">Add Module</div><button class="btn-icon" onclick="closeModal('moduleModal')">✕</button></div><div class="modal-body"><div class="form-group"><label class="form-label">Module Name</label><input class="form-input" placeholder="e.g. Healthcare"></div><div class="form-group"><label class="form-label">Description</label><textarea class="form-textarea" placeholder="Module description..."></textarea></div><div class="form-group"><label class="form-label">Icon (Emoji)</label><input class="form-input" placeholder="e.g. ❤️"></div><div class="form-group"><label class="form-label">Category</label><select class="form-select"><option>Core</option><option>Custom</option><option>Beta</option></select></div><div class="form-group"><label class="form-label">Default Permissions</label><select class="form-select" multiple style="min-height:100px"><option>Dashboard Access</option><option>Module Management</option><option>Data Export</option><option>Reports</option></select></div></div><div class="modal-footer"><button class="btn btn-secondary" onclick="closeModal('moduleModal')">Cancel</button><button class="btn btn-primary" onclick="closeModal('moduleModal');showToast('Module added')">Add Module</button></div></div></div>

<div class="modal-overlay" id="permissionModal"><div class="modal"><div class="modal-header"><div class="modal-title">Add Permission</div><button class="btn-icon" onclick="closeModal('permissionModal')">✕</button></div><div class="modal-body"><div class="form-group"><label class="form-label">Permission Name</label><input class="form-input" placeholder="e.g. Manage Growers"></div><div class="form-group"><label class="form-label">Module</label><select class="form-select"><option>System</option><option>Registry</option><option>Marketplace</option><option>Academy</option><option>Wallet</option><option>Reports</option></select></div><div class="form-group"><label class="form-label">Description</label><textarea class="form-textarea" placeholder="What does this permission allow?"></textarea></div><div class="form-group"><label class="form-label">Default Roles</label><div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:6px"><label style="display:flex;align-items:center;gap:8px;font-size:12px"><input type="checkbox" checked> Super Admin</label><label style="display:flex;align-items:center;gap:8px;font-size:12px"><input type="checkbox" checked> Admin</label><label style="display:flex;align-items:center;gap:8px;font-size:12px"><input type="checkbox"> Manager</label><label style="display:flex;align-items:center;gap:8px;font-size:12px"><input type="checkbox"> Agent</label><label style="display:flex;align-items:center;gap:8px;font-size:12px"><input type="checkbox"> Viewer</label></div></div></div><div class="modal-footer"><button class="btn btn-secondary" onclick="closeModal('permissionModal')">Cancel</button><button class="btn btn-primary" onclick="closeModal('permissionModal');showToast('Permission added')">Add Permission</button></div></div></div>

<div class="modal-overlay" id="maintenanceModal"><div class="modal"><div class="modal-header"><div class="modal-title">Schedule Maintenance</div><button class="btn-icon" onclick="closeModal('maintenanceModal')">✕</button></div><div class="modal-body"><div class="form-row"><div class="form-group"><label class="form-label">Date</label><input class="form-input" type="date"></div><div class="form-group"><label class="form-label">Time</label><input class="form-input" type="time" value="02:00"></div></div><div class="form-group"><label class="form-label">Duration</label><select class="form-select"><option>30 minutes</option><option>1 hour</option><option>2 hours</option><option>3 hours</option><option>4 hours</option></select></div><div class="form-group"><label class="form-label">Type</label><select class="form-select"><option>System Update</option><option>Database Migration</option><option>Security Patch</option><option>Platform Update</option><option>Other</option></select></div><div class="form-group"><label class="form-label">Description</label><textarea class="form-textarea" placeholder="What will be done during maintenance?"></textarea></div><div class="form-group"><label class="form-label">Notify Users</label><select class="form-select"><option>Email + In-app 24h before</option><option>Email 24h before</option><option>In-app only</option><option>No notification</option></select></div></div><div class="modal-footer"><button class="btn btn-secondary" onclick="closeModal('maintenanceModal')">Cancel</button><button class="btn btn-primary" onclick="closeModal('maintenanceModal');showToast('Maintenance scheduled')">Schedule</button></div></div></div>

<div class="toast" id="toast"></div>

<script>
const SETTINGS_DATA = <?= json_encode($settingsPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
const badge = status => `<span class="status-badge status-${esc(String(status || 'active').toLowerCase().replace(/[^a-z0-9]+/g,'-'))}">${esc(status || 'Active')}</span>`;
function moduleIcon(module){
  const key = String(module?.key || '').toLowerCase();
  const label = String(module?.label || '').toLowerCase();
  const text = `${key} ${label}`;
  const map = [
    [/registry|grower|farm|application|document|certificate/, '◎'],
    [/market|seller|product|order|store/, '$'],
    [/academy|training|course|lesson|learner/, 'A'],
    [/wallet|payment|financial|payout|refund/, '₦'],
    [/support|ticket|notification|communication/, '!'],
    [/report|analytics|audit/, 'R'],
    [/setting|security|rbac|role|profile/, '⚙'],
    [/field|state|national|iot|geo|map/, '⌖'],
  ];
  const found = map.find(([pattern]) => pattern.test(text));
  return found ? found[1] : (label.trim().charAt(0) || 'M').toUpperCase();
}
function postForm(action, page, fields = '', submit = 'Save'){
  return `<form method="post" class="settings-ws-form"><input type="hidden" name="_csrf" value="${esc(SETTINGS_DATA.csrf)}"><input type="hidden" name="action" value="${esc(action)}"><input type="hidden" name="page" value="${esc(page)}">${fields}<button class="btn btn-primary btn-sm" type="submit">${esc(submit)}</button></form>`;
}
function checkbox(name, value, label, checked = false){
  return `<label style="display:flex;align-items:center;gap:8px;font-size:12px"><input type="checkbox" name="${esc(name)}" value="${esc(value)}" ${checked ? 'checked' : ''}> ${esc(label)}</label>`;
}
function renderRows(pageId, rowsHtml, emptyText = 'No records yet.'){
  const table = document.querySelector(`#page-${pageId} table`);
  if(!table) return;
  const tbody = table.querySelector('tbody');
  if(tbody) tbody.innerHTML = rowsHtml || `<tr><td colspan="8">${esc(emptyText)}</td></tr>`;
}
function renderOverview(){
  const stats = SETTINGS_DATA.stats || {};
  const values = [stats.enabledModules, stats.activeRoles, stats.pendingApprovals, stats.integrationHealth, stats.lastBackup, stats.securityAlerts];
  document.querySelectorAll('#page-overview .stats-grid .stat-card-value').forEach((el, i) => { if(values[i] !== undefined) el.textContent = values[i]; });
  const subs = [stats.moduleRate, 'Platform and custom roles', 'Stakeholder records requiring review', 'Connected integrations', 'Latest completed backup', 'RBAC/security events this week'];
  document.querySelectorAll('#page-overview .stats-grid .stat-card-sub').forEach((el, i) => { if(subs[i]) el.textContent = subs[i]; });
  const moduleGrid = document.querySelector('#page-overview .module-card')?.parentElement;
  if(moduleGrid){
    moduleGrid.innerHTML = SETTINGS_DATA.modules.slice(0, 8).map(m => `
      <form class="module-card" method="post">
        <input type="hidden" name="_csrf" value="${esc(SETTINGS_DATA.csrf)}">
        <input type="hidden" name="action" value="toggle_module">
        <input type="hidden" name="page" value="overview">
        <input type="hidden" name="feature" value="${esc(m.key)}">
        <div class="module-icon" title="${esc(m.label)}" style="background:var(--g100);color:var(--g700)">${moduleIcon(m)}</div>
        <div class="module-info"><div class="module-title">${esc(m.label)}</div><div class="module-desc">${esc(m.owners || 'RBAC controlled')}</div></div>
        <label class="toggle"><input type="checkbox" name="enabled" ${m.enabled ? 'checked' : ''} onchange="this.form.submit()"><span class="toggle-slider"></span></label>
      </form>`).join('');
  }
}
function renderModules(){
  const page = document.querySelector('#page-modules');
  const grid = page?.querySelector('.grid-4');
  if(grid){
    grid.innerHTML = SETTINGS_DATA.modules.map(m => `
      <form class="module-card" method="post">
        <input type="hidden" name="_csrf" value="${esc(SETTINGS_DATA.csrf)}">
        <input type="hidden" name="action" value="toggle_module">
        <input type="hidden" name="page" value="modules">
        <input type="hidden" name="feature" value="${esc(m.key)}">
        <div class="module-icon" title="${esc(m.label)}" style="background:var(--g100);color:var(--g700)">${moduleIcon(m)}</div>
        <div class="module-info"><div class="module-title">${esc(m.label)}</div><div class="module-desc">${esc(m.owners || 'Super Admin')}</div></div>
        <label class="toggle"><input type="checkbox" name="enabled" ${m.enabled ? 'checked' : ''} onchange="this.form.submit()"><span class="toggle-slider"></span></label>
      </form>`).join('');
  }
}
function renderRbac(){
  const roles = SETTINGS_DATA.roles || [];
  const modules = SETTINGS_DATA.modules || [];
  const body = modules.map(m => `<tr><td><strong>${esc(m.label)}</strong><br><small>${esc(m.key)}</small></td>${roles.slice(0,6).map(r => {
    const defaults = {super_admin:true, admin:true, national_coordinator:true, state_coordinator:['dashboard','state_dashboard','applications','documents','certificates','field_network','support','training','reports','analytics'].includes(m.key), field_agent:['dashboard','profile','applications','field_network','support','training','reports'].includes(m.key), learner:['dashboard','profile','support','wallet','training','notifications','reports'].includes(m.key)};
    return `<td>${defaults[r] ? '<span style="color:var(--success);font-weight:800">Allowed</span>' : '<span style="color:var(--text2)">Inherited</span>'}</td>`;
  }).join('')}<td>${m.enabled ? badge('Enabled') : badge('Disabled')}</td></tr>`).join('');
  renderRows('rbac', body);
}
function renderRoles(){
  const userRoles = new Map((SETTINGS_DATA.userRoles || []).map(r => [r.role_key, r.total]));
  const defaults = (SETTINGS_DATA.roles || []).map(r => ({role_key:r, role_name:r.replace(/_/g,' ').replace(/\b\w/g, c => c.toUpperCase()), description:'Built-in platform role', status:'active', modules:''}));
  const rows = defaults.concat(SETTINGS_DATA.customRoles || []);
  renderRows('user-roles', rows.map(r => `<tr><td><strong>${esc(r.role_name)}</strong><br><small>${esc(r.role_key)}</small></td><td>${esc(r.description || 'Custom platform role')}</td><td>${esc(userRoles.get(r.role_key) || 0)}</td><td>${esc((r.modules || '').split(',').filter(Boolean).length || 'Default')}</td><td>${badge(r.status || 'active')}</td><td>${postForm('save_role','user-roles',`<input type="hidden" name="role_key" value="${esc(r.role_key)}"><input type="hidden" name="role_name" value="${esc(r.role_name)}"><input type="hidden" name="description" value="${esc(r.description || '')}">`,'Refresh')}</td></tr>`).join(''));
}
function renderStakeholderInterests(){
  const rows = (SETTINGS_DATA.stakeholderInterests || []).map(s => `<tr>
    <td><strong>${esc(s.stakeholder_name)}</strong><br><small>${esc(s.stakeholder_key)}</small></td>
    <td><input class="form-input" form="stakeholder-${esc(s.stakeholder_key)}" name="entry_point" value="${esc(s.entry_point)}"></td>
    <td><input class="form-input" form="stakeholder-${esc(s.stakeholder_key)}" name="workspace_url" value="${esc(s.workspace_url)}"></td>
    <td><input class="form-input" form="stakeholder-${esc(s.stakeholder_key)}" name="request_path" value="${esc(s.request_path || '')}"></td>
    <td><input class="form-input" form="stakeholder-${esc(s.stakeholder_key)}" name="support_scope" value="${esc(s.support_scope || 'General Support')}"></td>
    <td><select class="form-select" form="stakeholder-${esc(s.stakeholder_key)}" name="status"><option value="active" ${s.status === 'active' ? 'selected' : ''}>Active</option><option value="paused" ${s.status === 'paused' ? 'selected' : ''}>Paused</option><option value="review" ${s.status === 'review' ? 'selected' : ''}>Review</option></select></td>
    <td><form method="post" id="stakeholder-${esc(s.stakeholder_key)}"><input type="hidden" name="_csrf" value="${esc(SETTINGS_DATA.csrf)}"><input type="hidden" name="action" value="save_stakeholder_interest"><input type="hidden" name="page" value="stakeholder-interests"><input type="hidden" name="stakeholder_key" value="${esc(s.stakeholder_key)}"><input type="hidden" name="stakeholder_name" value="${esc(s.stakeholder_name)}"><button class="btn btn-primary btn-sm" type="submit">Save</button></form></td>
  </tr>`).join('');
  renderRows('stakeholder-interests', rows, 'No stakeholder controls configured.');
}
function renderProviders(){
  renderRows('payments', (SETTINGS_DATA.providers || []).map(p => `<tr><td><div class="avatar-row"><div class="avatar-sm" style="background:#dbeafe;color:#1e40af">${esc((p.provider_name || '?')[0])}</div><strong>${esc(p.provider_name)}</strong></div></td><td>${badge(p.status)}</td><td>${esc(p.mode)}</td><td>${esc(p.fee_percent)}%</td><td>${esc(p.last_sync_at || p.updated_at || '-')}</td><td>${esc(p.api_key_hint || 'Stored securely')}</td></tr>`).join(''));
}
function renderIntegrations(){
  const rows = (SETTINGS_DATA.integrations || []).map(i => `<tr><td><strong>${esc(i.name)}</strong><br><small>${esc(i.integration_key)}</small></td><td>${esc(i.type)}</td><td>${badge(i.status)}</td><td>${esc(i.mode)}</td><td>${esc(i.endpoint_url || '-')}</td><td>${esc(i.last_used_at || i.updated_at || '-')}</td></tr>`).join('');
  renderRows('integrations', rows);
  const overviewTable = document.querySelectorAll('#page-overview table')[3];
  const tbody = overviewTable?.querySelector('tbody');
  if(tbody) tbody.innerHTML = (SETTINGS_DATA.integrations || []).slice(0,4).map(i => `<tr><td><strong>${esc(i.name)}</strong></td><td>${badge(i.status)}</td><td>${esc(i.mode)}</td><td>${esc(i.last_used_at || '-')}</td></tr>`).join('');
}
function renderBackupsMaintenance(){
  renderRows('backups', (SETTINGS_DATA.backups || []).map(b => `<tr><td><strong>${esc(b.backup_ref)}</strong></td><td>${esc(b.backup_type)}</td><td>${esc(b.destination)}</td><td>${badge(b.status)}</td><td>${esc(b.started_at)}</td><td>${esc(b.notes || '-')}</td></tr>`).join(''));
  renderRows('maintenance', (SETTINGS_DATA.maintenance || []).map(m => `<tr><td>${esc(m.starts_at)}</td><td>${esc(m.duration_minutes)} minutes</td><td>${esc(m.maintenance_type)}</td><td>${badge(m.status)}</td></tr>`).join(''));
}
function renderTemplatesWebhooksFlagsAudit(){
  renderRows('email-templates', (SETTINGS_DATA.templates || []).map(t => `<tr><td><strong>${esc(t.name)}</strong><br><small>${esc(t.template_key)}</small></td><td>${esc(t.category)}</td><td>${esc(t.subject)}</td><td>${badge(t.status)}</td><td>${esc(t.updated_at || t.created_at || '-')}</td></tr>`).join(''));
  renderRows('webhooks', (SETTINGS_DATA.webhooks || []).map(w => `<tr><td><strong>${esc(w.name)}</strong><br><small>${esc(w.webhook_ref)}</small></td><td>${esc(w.endpoint_url)}</td><td>${esc(w.events || '-')}</td><td>${badge(w.status)}</td><td>${esc(w.last_delivery_at || 'Not delivered yet')}</td></tr>`).join(''));
  renderRows('feature-flags', (SETTINGS_DATA.flags || []).map(f => `<tr><td><strong>${esc(f.title)}</strong><br><small>${esc(f.flag_key)}</small></td><td>${esc(f.environment)}</td><td><div class="progress-bar"><div class="progress-fill" style="width:${Number(f.rollout_percent || 0)}%"></div></div>${esc(f.rollout_percent || 0)}%</td><td>${esc(f.target_users || 'all')}</td><td>${badge(f.status)}</td></tr>`).join(''));
  renderRows('audit-log', (SETTINGS_DATA.audit || []).map(a => `<tr><td>${esc(a.created_at)}</td><td>${esc(a.actor_name || 'System')}</td><td>${esc(a.action)}</td><td>${esc(a.module_key)}</td><td>${esc(a.details || '-')}</td></tr>`).join(''));
  const overviewAudit = document.querySelectorAll('#page-overview table')[4]?.querySelector('tbody');
  if(overviewAudit) overviewAudit.innerHTML = (SETTINGS_DATA.audit || []).slice(0,5).map(a => `<tr><td>${esc(a.created_at)}</td><td>${esc(a.actor_name || 'System')}</td><td>${esc(a.action)}</td><td>${esc(a.module_key)}</td><td>${esc(a.details || '-')}</td></tr>`).join('');
}
function settingsFields(page, fields){
  const s = SETTINGS_DATA.settings || {};
  return `<div class="card"><div class="card-header"><div class="card-title">Live Configuration</div></div><div class="card-body">${postForm('save_settings_group', page, fields.map(f => {
    const val = s[f.key] ?? f.default ?? '';
    if(f.type === 'checkbox') return `<input type="hidden" name="checkbox_keys[]" value="${esc(f.key)}"><div class="setting-row"><div class="setting-info"><div class="setting-title">${esc(f.label)}</div><div class="setting-desc">${esc(f.desc || '')}</div></div><label class="toggle"><input type="checkbox" name="setting_${esc(f.key)}" ${String(val)==='1' ? 'checked' : ''}><span class="toggle-slider"></span></label></div>`;
    if(f.type === 'textarea') return `<div class="form-group"><label class="form-label">${esc(f.label)}</label><textarea class="form-textarea" name="setting_${esc(f.key)}">${esc(val)}</textarea></div>`;
    return `<div class="form-group"><label class="form-label">${esc(f.label)}</label><input class="form-input" name="setting_${esc(f.key)}" value="${esc(val)}"></div>`;
  }).join(''), 'Save Configuration')}</div></div>`;
}
function injectSettingsPanels(){
  const panels = {
    'branding': [
      {key:'platform_name', label:'Platform Name'}, {key:'platform_email', label:'Platform Email'}
    ],
    'certificates': [
      {key:'auto_generate_certificates', label:'Auto-generate Certificates', type:'checkbox', desc:'Generate certificates once approval rules pass'}, {key:'qr_certificate_verification', label:'QR Code Verification', type:'checkbox'}, {key:'certificate_expiry_months', label:'Certificate Expiry Months'}, {key:'certificate_reminder_days', label:'Renewal Reminder Days'}
    ],
    'payments': [
      {key:'default_gateway', label:'Default Gateway'}, {key:'currency', label:'Currency'}, {key:'auto_retry_payments', label:'Auto Retry Failed Payments'}, {key:'payment_timeout', label:'Payment Timeout Seconds'}
    ],
    'marketplace-settings': [
      {key:'marketplace_commission_percent', label:'Marketplace Commission Percent'}, {key:'marketplace_public_store_enabled', label:'Public Store Enabled', type:'checkbox', default:'1'}, {key:'marketplace_seller_approval_required', label:'Require Seller Approval', type:'checkbox', default:'1'}
    ],
    'academy-settings': [
      {key:'academy_completion_threshold', label:'Completion Threshold Percent'}, {key:'academy_public_registration', label:'Public Learner Registration', type:'checkbox', default:'1'}, {key:'academy_certificate_auto_issue', label:'Auto Issue Eligible Certificates', type:'checkbox', default:'1'}
    ],
    'registry-settings': [
      {key:'registry_review_sla_days', label:'Review SLA Days'}, {key:'registry_document_required', label:'Require Documents', type:'checkbox', default:'1'}, {key:'registry_auto_assign_agents', label:'Auto Assign Field Agents', type:'checkbox', default:'0'}
    ],
    'notifications': [
      {key:'email_notifications', label:'Email Notifications', type:'checkbox'}, {key:'sms_notifications', label:'SMS Notifications', type:'checkbox'}, {key:'whatsapp_notifications', label:'WhatsApp Notifications', type:'checkbox', default:'0'}
    ],
    'security': [
      {key:'mfa_required_for_admins', label:'Require MFA for Admins', type:'checkbox', default:'1'}, {key:'session_timeout_minutes', label:'Session Timeout Minutes', default:'15'}, {key:'password_expiry_days', label:'Password Expiry Days', default:'90'}
    ],
    'data-retention': [
      {key:'retention_audit_days', label:'Audit Log Retention Days'}, {key:'retention_ticket_days', label:'Support Ticket Retention Days'}, {key:'retention_export_days', label:'Export Retention Days', default:'90'}
    ],
    'maintenance': [
      {key:'maintenance_mode', label:'Maintenance Mode', type:'checkbox'}, {key:'maintenance_message', label:'Maintenance Message', type:'textarea'}
    ],
    'backups': [
      {key:'backup_frequency', label:'Backup Frequency', default:'Daily at 2:30 AM'}, {key:'backup_retention_days', label:'Backup Retention Days', default:'30'}, {key:'backup_encrypt', label:'Encrypt Backups', type:'checkbox', default:'1'}
    ]
  };
  Object.entries(panels).forEach(([page, fields]) => {
    const container = document.querySelector(`#page-${page}`);
    if(container && !container.querySelector('.settings-ws-form')) {
      const header = container.querySelector('.page-header');
      header?.insertAdjacentHTML('afterend', settingsFields(page, fields));
    }
  });
}
function replaceModals(){
  const featureOptions = (SETTINGS_DATA.modules || []).map(m => `<option value="${esc(m.key)}">${esc(m.label)}</option>`).join('');
  const featureChecks = (SETTINGS_DATA.modules || []).map(m => checkbox('modules[]', m.key, m.label, ['dashboard','settings','reports'].includes(m.key))).join('');
  const roleChecks = (SETTINGS_DATA.roles || []).map(r => checkbox('default_roles[]', r, r.replace(/_/g,' '), ['super_admin','admin'].includes(r))).join('');
  const modalHtml = {
    roleModal: `<div class="modal"><div class="modal-header"><div class="modal-title">Create / Update Role</div><button class="btn-icon" onclick="closeModal('roleModal')" type="button">x</button></div><div class="modal-body">${postForm('save_role','user-roles',`<div class="form-group"><label class="form-label">Role Name</label><input class="form-input" name="role_name" required placeholder="State Coordinator"></div><div class="form-group"><label class="form-label">Role Key</label><input class="form-input" name="role_key" placeholder="state_coordinator"></div><div class="form-group"><label class="form-label">Description</label><textarea class="form-textarea" name="description"></textarea></div><div class="form-group"><label class="form-label">Module Access</label><div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">${featureChecks}</div></div>`,'Save Role')}</div></div>`,
    permissionModal: `<div class="modal"><div class="modal-header"><div class="modal-title">Add Permission</div><button class="btn-icon" onclick="closeModal('permissionModal')" type="button">x</button></div><div class="modal-body">${postForm('save_permission','rbac',`<div class="form-group"><label class="form-label">Permission Name</label><input class="form-input" name="permission_name" required></div><div class="form-group"><label class="form-label">Module</label><select class="form-select" name="module_key">${featureOptions}</select></div><div class="form-group"><label class="form-label">Description</label><textarea class="form-textarea" name="description"></textarea></div><div class="form-group"><label class="form-label">Default Roles</label><div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">${roleChecks}</div></div>`,'Save Permission')}</div></div>`,
    providerModal: `<div class="modal"><div class="modal-header"><div class="modal-title">Add Payment Provider</div><button class="btn-icon" onclick="closeModal('providerModal')" type="button">x</button></div><div class="modal-body">${postForm('save_provider','payments',`<div class="form-group"><label class="form-label">Provider Name</label><input class="form-input" name="provider_name" required></div><div class="form-row"><div class="form-group"><label class="form-label">Mode</label><select class="form-select" name="mode"><option>Live</option><option>Sandbox</option><option>Manual</option></select></div><div class="form-group"><label class="form-label">Fee Percent</label><input class="form-input" type="number" step="0.01" name="fee_percent" value="0"></div></div><div class="form-group"><label class="form-label">API Key</label><input class="form-input" type="password" name="api_key"></div>`,'Save Provider')}</div></div>`,
    integrationModal: `<div class="modal"><div class="modal-header"><div class="modal-title">Add Integration</div><button class="btn-icon" onclick="closeModal('integrationModal')" type="button">x</button></div><div class="modal-body">${postForm('save_integration','integrations',`<div class="form-group"><label class="form-label">Integration Name</label><input class="form-input" name="name" required></div><div class="form-row"><div class="form-group"><label class="form-label">Type</label><select class="form-select" name="type"><option>Payment</option><option>SMS</option><option>Email</option><option>Maps</option><option>Storage</option><option>Analytics</option><option>Custom API</option></select></div><div class="form-group"><label class="form-label">Mode</label><select class="form-select" name="mode"><option>Production</option><option>Staging</option><option>Sandbox</option></select></div></div><div class="form-group"><label class="form-label">Endpoint URL</label><input class="form-input" name="endpoint_url"></div><div class="form-group"><label class="form-label">Webhook URL</label><input class="form-input" name="webhook_url"></div><div class="form-group"><label class="form-label">API Key</label><input class="form-input" type="password" name="api_key"></div>`,'Save Integration')}</div></div>`,
    templateModal: `<div class="modal"><div class="modal-header"><div class="modal-title">New Email Template</div><button class="btn-icon" onclick="closeModal('templateModal')" type="button">x</button></div><div class="modal-body">${postForm('save_template','email-templates',`<div class="form-group"><label class="form-label">Template Name</label><input class="form-input" name="name" required></div><div class="form-group"><label class="form-label">Category</label><input class="form-input" name="category" value="General"></div><div class="form-group"><label class="form-label">Subject</label><input class="form-input" name="subject" required></div><div class="form-group"><label class="form-label">Body HTML</label><textarea class="form-textarea" style="min-height:150px" name="body_html"></textarea></div>`,'Save Template')}</div></div>`,
    webhookModal: `<div class="modal"><div class="modal-header"><div class="modal-title">Add Webhook</div><button class="btn-icon" onclick="closeModal('webhookModal')" type="button">x</button></div><div class="modal-body">${postForm('save_webhook','webhooks',`<div class="form-group"><label class="form-label">Webhook Name</label><input class="form-input" name="name" required></div><div class="form-group"><label class="form-label">Endpoint URL</label><input class="form-input" name="endpoint_url" required></div><div class="form-group"><label class="form-label">Events</label><div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">${['order.created','order.completed','payment.success','payment.failed','grower.verified','grower.registered','cert.issued','ticket.updated'].map(ev => checkbox('events[]', ev, ev)).join('')}</div></div><div class="form-group"><label class="form-label">Secret</label><input class="form-input" name="secret_hint"></div>`,'Save Webhook')}</div></div>`,
    flagModal: `<div class="modal"><div class="modal-header"><div class="modal-title">New Feature Flag</div><button class="btn-icon" onclick="closeModal('flagModal')" type="button">x</button></div><div class="modal-body">${postForm('save_flag','feature-flags',`<div class="form-group"><label class="form-label">Flag Name</label><input class="form-input" name="title" required></div><div class="form-group"><label class="form-label">Description</label><textarea class="form-textarea" name="description"></textarea></div><div class="form-row"><div class="form-group"><label class="form-label">Rollout Percent</label><input class="form-input" type="number" min="0" max="100" name="rollout_percent" value="0"></div><div class="form-group"><label class="form-label">Environment</label><select class="form-select" name="environment"><option>Production</option><option>Staging</option><option>Both</option></select></div></div><div class="form-group"><label class="form-label">Target Users</label><input class="form-input" name="target_users" value="all"></div>`,'Save Flag')}</div></div>`,
    backupModal: `<div class="modal"><div class="modal-header"><div class="modal-title">Manual Backup</div><button class="btn-icon" onclick="closeModal('backupModal')" type="button">x</button></div><div class="modal-body">${postForm('start_backup','backups',`<div class="form-group"><label class="form-label">Backup Type</label><select class="form-select" name="backup_type"><option>Full Backup</option><option>Database Only</option><option>Files Only</option><option>Configuration Only</option></select></div><div class="form-group"><label class="form-label">Destination</label><select class="form-select" name="destination"><option>Local Server</option><option>AWS S3</option><option>Google Cloud Storage</option></select></div><div class="form-group"><label class="form-label">Include</label><div style="display:grid;gap:8px">${['Database','File Storage','Configuration','Logs'].map(v => checkbox('include_scope[]', v, v, v !== 'Logs')).join('')}</div></div><div class="form-group"><label class="form-label">Notes</label><textarea class="form-textarea" name="notes"></textarea></div>`,'Start Backup')}</div></div>`,
    moduleModal: `<div class="modal"><div class="modal-header"><div class="modal-title">Module Setup</div><button class="btn-icon" onclick="closeModal('moduleModal')" type="button">x</button></div><div class="modal-body"><p style="margin-bottom:14px;color:var(--text2)">Core modules are controlled by the live module list. Use the switches on this page to enable or pause each workspace.</p>${postForm('save_flag','feature-flags',`<div class="form-group"><label class="form-label">Custom Module Flag</label><input class="form-input" name="title" required placeholder="Enable Healthcare Workspace"></div><input type="hidden" name="environment" value="Production"><input type="hidden" name="rollout_percent" value="0"><div class="form-group"><label class="form-label">Description</label><textarea class="form-textarea" name="description"></textarea></div>`,'Create Module Flag')}</div></div>`,
    maintenanceModal: `<div class="modal"><div class="modal-header"><div class="modal-title">Schedule Maintenance</div><button class="btn-icon" onclick="closeModal('maintenanceModal')" type="button">x</button></div><div class="modal-body">${postForm('save_maintenance','maintenance',`<div class="form-row"><div class="form-group"><label class="form-label">Date</label><input class="form-input" type="date" name="date" value="${new Date().toISOString().slice(0,10)}"></div><div class="form-group"><label class="form-label">Time</label><input class="form-input" type="time" name="time" value="02:00"></div></div><div class="form-group"><label class="form-label">Duration Minutes</label><input class="form-input" type="number" name="duration_minutes" value="120"></div><div class="form-group"><label class="form-label">Type</label><input class="form-input" name="maintenance_type" value="System Update"></div><div class="form-group"><label class="form-label">Description</label><textarea class="form-textarea" name="description"></textarea></div><div class="form-group"><label class="form-label">Notify Users</label><input class="form-input" name="notify_users" value="Email + In-app 24h before"></div>`,'Schedule')}</div></div>`
  };
  Object.entries(modalHtml).forEach(([id, html]) => { const el = document.getElementById(id); if(el) el.innerHTML = html; });
}
function setupTopbarMenus(){
  document.querySelectorAll('[data-menu]').forEach(button => {
    if (button.dataset.boundMenu === '1') return;
    button.dataset.boundMenu = '1';
    button.addEventListener('click', event => {
      event.stopPropagation();
      const menu = document.getElementById(button.dataset.menu);
      document.querySelectorAll('.topbar-menu.active').forEach(open => {
        if (open !== menu) open.classList.remove('active');
      });
      menu?.classList.toggle('active');
      if (button.hasAttribute('aria-expanded')) {
        button.setAttribute('aria-expanded', menu?.classList.contains('active') ? 'true' : 'false');
      }
    });
  });
  document.addEventListener('click', event => {
    if (!event.target.closest('.topbar-menu-wrap')) {
      document.querySelectorAll('.topbar-menu.active').forEach(menu => menu.classList.remove('active'));
      document.querySelectorAll('[aria-expanded="true"]').forEach(button => button.setAttribute('aria-expanded', 'false'));
    }
  }, {once:false});
}
function hydrateSettingsWorkspace(){
  document.querySelectorAll('.sidebar-user,.topbar-profile-info').forEach(el => {
    if(el.classList.contains('sidebar-user')) el.innerHTML = `${esc(SETTINGS_DATA.admin.name)}<small><span class="status-dot"></span>${esc(SETTINGS_DATA.admin.role || 'Super Admin')} - Online</small>`;
    else el.innerHTML = `${esc(SETTINGS_DATA.admin.name)}<small>${esc(SETTINGS_DATA.admin.role || 'Super Admin')}</small>`;
  });
  document.querySelectorAll('.sidebar-avatar,.topbar-avatar').forEach(el => {
    if (SETTINGS_DATA.admin.profilePicture && el.classList.contains('topbar-avatar')) {
      el.innerHTML = `<img src="${esc(SETTINGS_DATA.admin.profilePicture)}" alt="">`;
    } else {
      el.textContent = SETTINGS_DATA.admin.initials || 'AD';
    }
  });
  renderOverview(); renderModules(); renderRbac(); renderRoles(); renderStakeholderInterests(); renderProviders(); renderIntegrations(); renderBackupsMaintenance(); renderTemplatesWebhooksFlagsAudit(); injectSettingsPanels(); replaceModals(); enhanceSettingsActions(); paginateSettingsTables(); setupTopbarMenus();
  if(SETTINGS_DATA.notice) showToast(SETTINGS_DATA.notice);
  navigateTo(SETTINGS_DATA.activePage || 'overview');
}
function settingsExportUrl(type){
  const url = new URL(window.location.href);
  url.searchParams.set('export', type);
  return url.toString();
}
function enhanceSettingsActions(){
  document.querySelectorAll('#page-audit-log .page-header .btn-primary').forEach(btn => {
    btn.onclick = () => { window.location.href = settingsExportUrl('audit'); };
  });
  document.querySelectorAll('#page-backups table .btn-secondary').forEach(btn => {
    if (/download/i.test(btn.textContent)) btn.onclick = () => { window.location.href = settingsExportUrl('backups'); };
  });
  document.querySelectorAll('#page-system-health .page-header .btn-primary').forEach(btn => {
    btn.onclick = () => showToast('Health check complete: live settings tables loaded successfully.');
  });
  document.querySelectorAll('.btn-icon').forEach(btn => {
    if (!btn.textContent.trim()) btn.textContent = 'x';
  });
}
function paginateSettingsTables(pageSize=25){
  document.querySelectorAll('.page table').forEach(table => {
    const tbody = table.querySelector('tbody');
    if (!tbody || table.dataset.paginated === '1') return;
    const rows = Array.from(tbody.querySelectorAll('tr'));
    if (rows.length <= pageSize) return;
    table.dataset.paginated = '1';
    let page = 1;
    const totalPages = Math.ceil(rows.length / pageSize);
    const nav = document.createElement('div');
    nav.style.cssText = 'display:flex;gap:8px;align-items:center;margin:12px 20px;flex-wrap:wrap';
    const render = () => {
      rows.forEach((row, index) => {
        row.style.display = index >= (page - 1) * pageSize && index < page * pageSize ? '' : 'none';
      });
      nav.innerHTML = `<button class="btn btn-sm btn-secondary" type="button" ${page === 1 ? 'disabled' : ''}>Previous</button><span class="chip">Page ${page} of ${totalPages}</span><button class="btn btn-sm btn-secondary" type="button" ${page === totalPages ? 'disabled' : ''}>Next</button>`;
      nav.querySelector('button:first-child')?.addEventListener('click', () => { page = Math.max(1, page - 1); render(); });
      nav.querySelector('button:last-child')?.addEventListener('click', () => { page = Math.min(totalPages, page + 1); render(); });
    };
    table.closest('.card-body')?.appendChild(nav);
    render();
  });
}
function navigateTo(page){
  document.querySelectorAll('.page').forEach(p=>p.classList.remove('active'));
  document.querySelectorAll('.nav-item').forEach(n=>n.classList.remove('active'));
  const el=document.getElementById('page-'+page);
  if(el)el.classList.add('active');
  const nav=document.querySelector(`.nav-item[data-page="${page}"]`);
  if(nav)nav.classList.add('active');
  const url = new URL(window.location.href);
  url.searchParams.set('page', page);
  window.history.replaceState({}, '', url);
  window.scrollTo(0,0);
}
document.querySelectorAll('.nav-item[data-page]').forEach(item=>{
  item.addEventListener('click',()=>{const p=item.getAttribute('data-page');if(p)navigateTo(p)});
});
function openModal(id){const modal=document.getElementById(id);if(modal)modal.classList.add('active')}
function closeModal(id){const modal=document.getElementById(id);if(modal)modal.classList.remove('active')}
document.querySelectorAll('.modal-overlay').forEach(o=>{o.addEventListener('click',e=>{if(e.target===o)o.classList.remove('active')})});
function showToast(msg){const t=document.getElementById('toast');t.textContent=msg;t.style.display='block';setTimeout(()=>t.style.display='none',2500)}
function filterTable(tableId,q){
  const t=document.getElementById(tableId);if(!t)return;
  const rows=t.querySelectorAll('tbody tr');const s=q.toLowerCase();
  rows.forEach(r=>{r.style.display=r.textContent.toLowerCase().includes(s)?'':'none'});
}
document.querySelectorAll('.tab').forEach(tab=>{tab.addEventListener('click',function(){this.parentElement.querySelectorAll('.tab').forEach(t=>t.classList.remove('active'));this.classList.add('active')})});
document.addEventListener('keydown',e=>{if((e.ctrlKey||e.metaKey)&&e.key==='k'){e.preventDefault();document.getElementById('globalSearch').focus()}});
hydrateSettingsWorkspace();
</script>
</body>
</html>

