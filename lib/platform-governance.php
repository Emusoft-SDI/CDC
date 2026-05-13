<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/admin-layout.php';
require_once __DIR__ . '/field-management.php';
require_once __DIR__ . '/agronomy.php';

function pg_ensure_schema(PDO $pdo): void
{
    admin_ensure_schema($pdo);
    fm_ensure_schema($pdo);
    agronomy_ensure_schema($pdo);

    foreach ([
        'profile_verified' => "TINYINT(1) NOT NULL DEFAULT 0",
        'accreditation_status' => "VARCHAR(40) NOT NULL DEFAULT 'not_accredited'",
        'accreditation_program' => "VARCHAR(120) NULL",
        'accredited_at' => "DATETIME NULL",
        'account_status' => "VARCHAR(40) NOT NULL DEFAULT 'active'",
        'two_factor_required' => "TINYINT(1) NOT NULL DEFAULT 0",
    ] as $column => $definition) {
        app_add_column_if_missing($pdo, 'users', $column, $definition);
    }
    foreach ([
        'coconut_variety' => "VARCHAR(180) NULL",
        'intercrops' => "TEXT NULL",
        'annual_yield_estimate' => "VARCHAR(120) NULL",
    ] as $column => $definition) {
        app_add_column_if_missing($pdo, 'grower_farms', $column, $definition);
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS state_resource_inventory (
            id INT AUTO_INCREMENT PRIMARY KEY,
            state_name VARCHAR(120) NOT NULL,
            resource_name VARCHAR(180) NOT NULL,
            resource_category VARCHAR(80) NOT NULL DEFAULT 'input',
            quantity_available DECIMAL(12,2) NOT NULL DEFAULT 0,
            unit VARCHAR(40) NULL,
            reorder_level DECIMAL(12,2) NOT NULL DEFAULT 0,
            notes TEXT NULL,
            updated_by INT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_resource_state (state_name, resource_category)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'state_resource_inventory');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS state_resource_allocations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            state_name VARCHAR(120) NOT NULL,
            farmer_id INT NULL,
            resource_name VARCHAR(180) NOT NULL,
            resource_category VARCHAR(80) NOT NULL DEFAULT 'input',
            quantity_allocated DECIMAL(12,2) NOT NULL DEFAULT 0,
            unit VARCHAR(40) NULL,
            distribution_status VARCHAR(40) NOT NULL DEFAULT 'planned',
            distributed_at DATETIME NULL,
            effectiveness_note TEXT NULL,
            created_by INT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_alloc_state_status (state_name, distribution_status),
            INDEX idx_alloc_farmer (farmer_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'state_resource_allocations');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS platform_broadcasts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            scope VARCHAR(40) NOT NULL DEFAULT 'state',
            state_name VARCHAR(120) NULL,
            audience VARCHAR(80) NOT NULL DEFAULT 'grower',
            title VARCHAR(180) NOT NULL,
            message TEXT NOT NULL,
            channel VARCHAR(80) NOT NULL DEFAULT 'in_app',
            priority VARCHAR(20) NOT NULL DEFAULT 'normal',
            status VARCHAR(30) NOT NULL DEFAULT 'draft',
            created_by INT NULL,
            published_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_broadcast_scope (scope, state_name, audience, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'platform_broadcasts');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS provider_registry (
            id INT AUTO_INCREMENT PRIMARY KEY,
            provider_type VARCHAR(40) NOT NULL DEFAULT 'service',
            company_name VARCHAR(180) NOT NULL,
            company_description TEXT NULL,
            contact_person VARCHAR(160) NULL,
            email VARCHAR(255) NULL,
            phone VARCHAR(60) NULL,
            business_address VARCHAR(255) NULL,
            coverage_scope VARCHAR(40) NOT NULL DEFAULT 'state',
            states_served TEXT NULL,
            years_in_business DECIMAL(5,2) NULL,
            certifications TEXT NULL,
            website VARCHAR(255) NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'pending_review',
            verified_by INT NULL,
            verified_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_provider_type_status (provider_type, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'provider_registry');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS provider_offerings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            provider_id INT NOT NULL,
            offering_type VARCHAR(40) NOT NULL DEFAULT 'service',
            category VARCHAR(120) NOT NULL,
            name VARCHAR(180) NOT NULL,
            description TEXT NULL,
            price DECIMAL(12,2) NULL,
            availability VARCHAR(120) NULL,
            certifications TEXT NULL,
            media_url VARCHAR(255) NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'active',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_offering_provider (provider_id, offering_type, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'provider_offerings');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS platform_governance_policies (
            id INT AUTO_INCREMENT PRIMARY KEY,
            policy_key VARCHAR(80) NOT NULL UNIQUE,
            title VARCHAR(180) NOT NULL,
            category VARCHAR(80) NOT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'draft',
            review_frequency_days INT NOT NULL DEFAULT 180,
            owner_role VARCHAR(80) NULL,
            summary TEXT NULL,
            last_reviewed_at DATETIME NULL,
            next_review_at DATETIME NULL,
            updated_by INT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_policy_category (category, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'platform_governance_policies');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS state_budget_records (
            id INT AUTO_INCREMENT PRIMARY KEY,
            state_name VARCHAR(120) NOT NULL,
            budget_line VARCHAR(180) NOT NULL,
            amount_budgeted DECIMAL(14,2) NOT NULL DEFAULT 0,
            amount_spent DECIMAL(14,2) NOT NULL DEFAULT 0,
            fiscal_period VARCHAR(40) NULL,
            notes TEXT NULL,
            created_by INT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_budget_state (state_name, fiscal_period)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'state_budget_records');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS stakeholder_partnerships (
            id INT AUTO_INCREMENT PRIMARY KEY,
            state_name VARCHAR(120) NULL,
            partner_name VARCHAR(180) NOT NULL,
            partner_type VARCHAR(80) NOT NULL DEFAULT 'local_business',
            opportunity VARCHAR(180) NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'prospect',
            impact_metric VARCHAR(180) NULL,
            notes TEXT NULL,
            created_by INT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_partner_scope (state_name, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'stakeholder_partnerships');

    pg_seed_policies($pdo);
}

function pg_seed_policies(PDO $pdo): void
{
    $policies = [
        ['password_policy', 'Password and MFA Policy', 'security', 'approved', 'Super Admin', 'Password complexity, lockout, history, MFA, recovery, and audit requirements.'],
        ['data_retention', 'Data Retention, Archival, and Deletion', 'data_governance', 'approved', 'Super Admin', 'Retention periods, archival rules, legal holds, secure deletion, and audit evidence.'],
        ['disaster_recovery', 'Disaster Recovery and Continuity', 'resilience', 'approved', 'Super Admin', 'RTO/RPO, backup drills, recovery procedures, emergency roles, and post-incident review.'],
        ['notification_policy', 'Notification and Communication Policy', 'communications', 'approved', 'Super Admin', 'Email, SMS, WhatsApp, in-app alerts, critical priority levels, and user preferences.'],
        ['access_control', 'Role Access Control Policy', 'security', 'approved', 'Super Admin', 'Role-based access controls, profile approval, deprovisioning, emergency access, and review cadence.'],
        ['provider_accreditation', 'Provider Accreditation Policy', 'marketplace', 'draft', 'National Coordinator', 'Verification requirements for service and input providers before marketplace exposure.'],
    ];
    $stmt = $pdo->prepare("
        INSERT INTO platform_governance_policies
            (policy_key, title, category, status, owner_role, summary, next_review_at)
        VALUES (?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 180 DAY))
        ON DUPLICATE KEY UPDATE title = VALUES(title), category = VALUES(category), owner_role = VALUES(owner_role)
    ");
    foreach ($policies as $policy) {
        $stmt->execute($policy);
    }
}

function pg_scope_state(PDO $pdo): string
{
    return admin_current_scope_state($pdo);
}

function pg_state_where(string $alias = 'ns', ?string $state = null): array
{
    $state = trim((string) $state);
    if ($state === '') {
        return ['', []];
    }
    return [" AND {$alias}.state_name = ?", [$state]];
}

function pg_currency(float $amount): string
{
    return 'NGN ' . number_format($amount, 2);
}
