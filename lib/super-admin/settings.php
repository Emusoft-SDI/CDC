<?php
declare(strict_types=1);

function super_admin_ensure_schema(PDO $pdo): void
{
    app_ensure_core_schema($pdo);
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
        CREATE TABLE IF NOT EXISTS audit_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            action VARCHAR(120) NOT NULL,
            description TEXT NULL,
            ip_address VARCHAR(45) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'audit_log');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS system_announcements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(180) NOT NULL,
            body TEXT NOT NULL,
            audience_role VARCHAR(60) NOT NULL DEFAULT 'all',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_by INT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_system_announcements_active (is_active, audience_role, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'system_announcements');

    app_add_column_if_missing($pdo, 'users', 'platform_role', "VARCHAR(60) NULL");
    app_add_column_if_missing($pdo, 'users', 'account_status', "VARCHAR(30) NOT NULL DEFAULT 'active'");
    app_add_column_if_missing($pdo, 'users', 'is_super_admin', "TINYINT(1) NOT NULL DEFAULT 0");
    app_add_column_if_missing($pdo, 'users', 'two_factor_required', "TINYINT(1) NOT NULL DEFAULT 0");
    app_add_column_if_missing($pdo, 'users', 'profile_verified', "TINYINT(1) NOT NULL DEFAULT 0");
    app_add_column_if_missing($pdo, 'users', 'suspended_until', "DATETIME NULL");
    app_add_column_if_missing($pdo, 'users', 'deactivated_at', "DATETIME NULL");
    app_add_column_if_missing($pdo, 'users', 'archived_at', "DATETIME NULL");
    app_add_column_if_missing($pdo, 'users', 'last_login_at', "DATETIME NULL");
    app_add_column_if_missing($pdo, 'users', 'admin_notes', "TEXT NULL");
    app_add_column_if_missing($pdo, 'users', 'location', "VARCHAR(255) NULL");

    foreach (super_admin_control_settings() as $key => $default) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO settings (key_name, value) VALUES (?, ?)");
        $stmt->execute([$key, $default]);
    }
    foreach (super_admin_training_settings() as $key => $default) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO settings (key_name, value) VALUES (?, ?)");
        $stmt->execute([$key, $default]);
    }
    $accessCatalogVersion = ADMIN_ACCESS_CATALOG_VERSION;
    $currentCatalogVersion = admin_setting($pdo, 'access_matrix_catalog_version', '');
    foreach (super_admin_roles() as $role => $_label) {
        $key = 'access_matrix_' . $role;
        $defaultAccess = super_admin_default_access($role);
        if ($currentCatalogVersion !== $accessCatalogVersion) {
            $existing = admin_setting($pdo, $key, '');
            $mergedAccess = $existing === ''
                ? $defaultAccess
                : array_values(array_unique(array_merge(array_filter(array_map('trim', explode(',', $existing))), $defaultAccess)));
            $stmt = $pdo->prepare("
                INSERT INTO settings (key_name, value) VALUES (?, ?)
                ON DUPLICATE KEY UPDATE value = VALUES(value)
            ");
            $stmt->execute([$key, implode(',', $mergedAccess)]);
        } else {
            $stmt = $pdo->prepare("INSERT IGNORE INTO settings (key_name, value) VALUES (?, ?)");
            $stmt->execute([$key, implode(',', $defaultAccess)]);
        }
    }
    $stmt = $pdo->prepare("
        INSERT INTO settings (key_name, value) VALUES ('access_matrix_catalog_version', ?)
        ON DUPLICATE KEY UPDATE value = VALUES(value)
    ");
    $stmt->execute([$accessCatalogVersion]);
}

function super_admin_control_settings(?PDO $pdo = null): array
{
    $defaults = [
        'dashboard_system_notice' => '',
        'security_require_profile_otp' => '1',
        'security_require_2fa_admins' => '0',
        'access_investor_dashboard_enabled' => '0',
        'access_user_export_enabled' => '1',
    ];

    if (!$pdo instanceof PDO) {
        return $defaults;
    }

    foreach ($defaults as $key => $default) {
        $defaults[$key] = admin_setting($pdo, $key, $default);
    }
    return $defaults;
}

function super_admin_feature_catalog(): array
{
    return function_exists('admin_feature_catalog') ? admin_feature_catalog() : [];
}

function super_admin_module_catalog(): array
{
    $labels = super_admin_feature_catalog();
    $modules = [
        'dashboard' => ['entry' => '../dashboard/index.php', 'surface' => 'Grower dashboard', 'owner' => 'Operations', 'purpose' => 'Main user home for account, farm, support, and service summaries.', 'setup' => 'Keep dashboard cards aligned with enabled modules and active user journeys.'],
        'state_dashboard' => ['entry' => '../admin/state-dashboard.php', 'surface' => 'State Coordinator', 'owner' => 'State Operations', 'purpose' => 'State-level farmers, accreditation, resources, field network, communication, finance, and reporting.', 'setup' => 'Assign states to coordinators and confirm state-scope data quality.'],
        'national_dashboard' => ['entry' => '../admin/national-dashboard.php', 'surface' => 'National Coordinator', 'owner' => 'National Operations', 'purpose' => 'National comparison, state performance, investor engagement, compliance, and strategic decision support.', 'setup' => 'Define national KPIs, state ranking rules, and reporting cadence.'],
        'governance' => ['entry' => '../admin/governance.php', 'surface' => 'Super Admin/Admin', 'owner' => 'Governance', 'purpose' => 'Policies for access, password, DR, retention, notifications, compliance, and production readiness.', 'setup' => 'Approve policies and maintain review cadence.'],
        'production_readiness' => ['entry' => '../admin/production-readiness.php', 'surface' => 'Admin/Super Admin', 'owner' => 'Technical Operations', 'purpose' => 'Authenticated production checks for live mail, SMS, WhatsApp, payment, backup, roles, and environment readiness.', 'setup' => 'Run before launch and after any infrastructure or credential change.'],
        'profile' => ['entry' => '../dashboard/profile.php', 'surface' => 'Grower dashboard', 'owner' => 'User Operations', 'purpose' => 'Account settings, security, password, notifications, and farm profile data.', 'setup' => 'Review OTP policy, required profile fields, and notification channels.'],
        'applications' => ['entry' => '../admin/registry/index.php', 'surface' => 'Admin console', 'owner' => 'Registry Operations', 'purpose' => 'Application review, confirmation, exports, and grower onboarding.', 'setup' => 'Define review statuses, confirmation workflow, and export policy.'],
        'documents' => ['entry' => '../admin/document-verification.php', 'surface' => 'Admin console', 'owner' => 'Verification Team', 'purpose' => 'Identity and farm document verification.', 'setup' => 'Maintain document requirements and review SLA.'],
        'certificates' => ['entry' => '../dashboard/documents.php', 'surface' => 'Dashboard/Admin', 'owner' => 'Certification Team', 'purpose' => 'Certificate readiness, verification, and downloads.', 'setup' => 'Define certificate eligibility and verification evidence.'],
        'field_network' => ['entry' => '../admin/agent-map.php', 'surface' => 'Admin console', 'owner' => 'Field Operations', 'purpose' => 'Field agent tracking, assignments, and field network operations.', 'setup' => 'Confirm agent onboarding, GPS tracking policy, and assignment coverage.'],
        'field_management' => ['entry' => '../admin/fields-management.php', 'surface' => 'Admin/Field/Grower', 'owner' => 'Field Operations', 'purpose' => 'Farm maps, farm verification, field tasks, GPS evidence, and weather snapshots.', 'setup' => 'Set verification tolerance, task routing, and farm approval workflow.'],
        'agronomy_advisory' => ['entry' => '../admin/agronomy.php', 'surface' => 'Admin/Agronomist/Grower', 'owner' => 'Agronomy Team', 'purpose' => 'Agronomy cases, soil and crop records, recommendations, and advisory templates.', 'setup' => 'Define case categories, recommendation templates, follow-up policy, and escalation rules.'],
        'support' => ['entry' => '../admin/support.php', 'surface' => 'Admin/Grower', 'owner' => 'Support Team', 'purpose' => 'Support tickets, grower issues, and operational replies.', 'setup' => 'Define categories, priorities, response expectations, and notification routing.'],
        'farm_health' => ['entry' => '../dashboard/farm-health.php', 'surface' => 'Grower dashboard', 'owner' => 'Agronomy Team', 'purpose' => 'Farm health requests, weather, imagery, and assessment entry point.', 'setup' => 'Define when farm health requests become support, field, or agronomy cases.'],
        'marketplace' => ['entry' => '../admin/marketplace.php', 'surface' => 'Admin/Grower', 'owner' => 'Marketplace Team', 'purpose' => 'Inputs, services, and marketplace offers.', 'setup' => 'Define seller policy, active listings, and purchase workflow.'],
        'providers' => ['entry' => '../admin/providers.php', 'surface' => 'Admin/National/State', 'owner' => 'Marketplace Team', 'purpose' => 'Agricultural input and service provider registration, verification, products, and services.', 'setup' => 'Define provider accreditation, certifications, coverage, and listing rules.'],
        'resource_allocation' => ['entry' => '../admin/resource-allocation.php', 'surface' => 'National/State', 'owner' => 'Program Operations', 'purpose' => 'Input inventory, farmer allocation, distribution status, and effectiveness tracking.', 'setup' => 'Define inventory units, resource categories, and beneficiary reporting.'],
        'communications' => ['entry' => '../admin/communications.php', 'surface' => 'National/State/Admin', 'owner' => 'Communications', 'purpose' => 'Statewide and national broadcasts, weather alerts, training announcements, and stakeholder messaging.', 'setup' => 'Define channel routing, approval rules, and priority alert policy.'],
        'wallet' => ['entry' => '../dashboard/wallet.php', 'surface' => 'Grower dashboard', 'owner' => 'Finance', 'purpose' => 'Wallet balance and transaction history.', 'setup' => 'Review payment provider settings and transaction audit.'],
        'training' => ['entry' => '../dashboard/webinars.php', 'surface' => 'Dashboard/Admin', 'owner' => 'Training Team', 'purpose' => 'Training sessions, webinars, onboarding, and certification learning.', 'setup' => 'Maintain curriculum, certification requirements, and paid/free training policy.'],
        'healthcare' => ['entry' => '../dashboard/healthcare.php', 'surface' => 'Grower dashboard', 'owner' => 'Services Team', 'purpose' => 'Health-related grower services when enabled.', 'setup' => 'Define partner/service availability before public rollout.'],
        'pricing' => ['entry' => '../dashboard/pricing.php', 'surface' => 'Grower dashboard', 'owner' => 'Commercial Team', 'purpose' => 'Plans, pricing, upgrades, and premium service visibility.', 'setup' => 'Confirm available plans, payment rules, and messaging.'],
        'resources' => ['entry' => '../admin/resources.php', 'surface' => 'Admin/Field', 'owner' => 'Content Team', 'purpose' => 'Offline resources, guides, and extension content.', 'setup' => 'Keep resources categorized and offline-ready for field agents.'],
        'templates' => ['entry' => '../admin/templates.php', 'surface' => 'Admin console', 'owner' => 'Communications', 'purpose' => 'Notification and communication templates.', 'setup' => 'Review template content, channels, and approved tone.'],
        'notifications' => ['entry' => '../admin/notifications.php', 'surface' => 'Admin console', 'owner' => 'Communications', 'purpose' => 'Notification logs and delivery visibility.', 'setup' => 'Confirm mail/SMS/WhatsApp transports are production-ready.'],
        'reports' => ['entry' => '../admin/reports.php', 'surface' => 'Admin console', 'owner' => 'Operations', 'purpose' => 'Agent and operational reports.', 'setup' => 'Define reporting cadence and required indicators.'],
        'analytics' => ['entry' => '../admin/analytics.php', 'surface' => 'Admin console', 'owner' => 'Data Team', 'purpose' => 'Registry, application, and operational analytics.', 'setup' => 'Review metric definitions and data quality assumptions.'],
        'monitoring' => ['entry' => '../admin/monitoring.php', 'surface' => 'Admin console', 'owner' => 'Technical Operations', 'purpose' => 'System health and integration readiness checks.', 'setup' => 'Track payment, mail, SMS, and system dependencies.'],
        'user_management' => ['entry' => '../admin/users.php', 'surface' => 'Admin console', 'owner' => 'User Operations', 'purpose' => 'Standard user and staff account management.', 'setup' => 'Use Super Admin User Governance for privileged root access.'],
        'imports' => ['entry' => '../admin/import-users.php', 'surface' => 'Admin console', 'owner' => 'Data Operations', 'purpose' => 'Bulk import, engagement, and legacy grower onboarding.', 'setup' => 'Validate CSV structure, confirmation messaging, and duplicate handling.'],
        'settings' => ['entry' => '../admin/settings.php', 'surface' => 'Admin console', 'owner' => 'Technical Operations', 'purpose' => 'Operational settings for integrations and platform behavior.', 'setup' => 'Keep environment-sensitive secrets out of browser-visible pages.'],
        'audit' => ['entry' => 'index.php?view=controls', 'surface' => 'Super Admin', 'owner' => 'Governance', 'purpose' => 'Privileged activity trail and governance evidence.', 'setup' => 'Review audit records regularly and investigate privileged changes.'],
        'integrations' => ['entry' => '../admin/monitoring.php', 'surface' => 'Admin console', 'owner' => 'Technical Operations', 'purpose' => 'External systems such as mail, SMS, WhatsApp, payment, maps, and weather.', 'setup' => 'Confirm provider credentials, transport modes, and failure logs.'],
    ];

    $ordered = [];
    foreach ($labels as $feature => $label) {
        $ordered[$feature] = array_merge([
            'label' => $label,
            'entry' => 'index.php?view=controls',
            'surface' => 'Platform',
            'owner' => 'Super Admin',
            'purpose' => 'Platform module.',
            'setup' => 'Define module owner, entry point, and operating rules.',
            'mode' => 'active',
        ], $modules[$feature] ?? [], ['label' => $label]);
    }

    return $ordered;
}

function super_admin_module_settings(PDO $pdo): array
{
    $settings = [];
    foreach (super_admin_module_catalog() as $feature => $module) {
        $settings[$feature] = [
            'mode' => admin_setting($pdo, 'module_' . $feature . '_mode', (string) $module['mode']),
            'owner' => admin_setting($pdo, 'module_' . $feature . '_owner', (string) $module['owner']),
            'notes' => admin_setting($pdo, 'module_' . $feature . '_notes', (string) $module['setup']),
        ];
    }
    return $settings;
}

function super_admin_default_access(string $role): array
{
    return function_exists('admin_default_access') ? admin_default_access($role) : [];
}

function super_admin_access_matrix(PDO $pdo, array $roles): array
{
    $matrix = [];
    foreach ($roles as $role => $_label) {
        $value = admin_setting($pdo, 'access_matrix_' . $role, implode(',', super_admin_default_access($role)));
        $matrix[$role] = array_values(array_filter(array_map('trim', explode(',', $value))));
    }
    return $matrix;
}

function super_admin_training_settings(?PDO $pdo = null): array
{
    $defaults = [
        'onboarding_default_message' => 'Welcome to NATCODEV. Please complete your profile, verify your contact details, and review your assigned onboarding materials.',
        'training_curriculum' => 'Platform orientation, data integrity, grower engagement, field reporting, privacy, support workflow, certificate verification.',
        'training_certification_required' => '1',
        'training_paid_certification_enabled' => '1',
    ];

    if (!$pdo instanceof PDO) {
        return $defaults;
    }

    foreach ($defaults as $key => $default) {
        $defaults[$key] = admin_setting($pdo, $key, $default);
    }
    return $defaults;
}
