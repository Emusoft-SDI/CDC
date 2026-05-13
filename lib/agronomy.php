<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/field-management.php';

function agronomy_ensure_schema(PDO $pdo): void
{
    fm_ensure_schema($pdo);

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS agronomy_cases (
            id INT AUTO_INCREMENT PRIMARY KEY,
            case_ref VARCHAR(60) NOT NULL UNIQUE,
            grower_id INT NOT NULL,
            farm_id INT NULL,
            assigned_to INT NULL,
            source VARCHAR(40) NOT NULL DEFAULT 'grower',
            category VARCHAR(60) NOT NULL DEFAULT 'general',
            priority VARCHAR(20) NOT NULL DEFAULT 'normal',
            status VARCHAR(30) NOT NULL DEFAULT 'open',
            title VARCHAR(180) NOT NULL,
            description TEXT NULL,
            symptoms TEXT NULL,
            crop_stage VARCHAR(80) NULL,
            created_by INT NULL,
            follow_up_at DATE NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_agronomy_cases_grower (grower_id, status),
            INDEX idx_agronomy_cases_assignee (assigned_to, status),
            INDEX idx_agronomy_cases_farm (farm_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'agronomy_cases');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS agronomy_recommendations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            case_id INT NOT NULL,
            author_id INT NULL,
            problem_observed TEXT NULL,
            likely_cause TEXT NULL,
            recommended_action TEXT NOT NULL,
            urgency VARCHAR(20) NOT NULL DEFAULT 'normal',
            inputs_needed TEXT NULL,
            safety_note TEXT NULL,
            follow_up_at DATE NULL,
            is_visible_to_grower TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_recommendations_case (case_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'agronomy_recommendations');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS agronomy_soil_crop_records (
            id INT AUTO_INCREMENT PRIMARY KEY,
            farm_id INT NOT NULL,
            case_id INT NULL,
            recorded_by INT NULL,
            soil_ph DECIMAL(4,2) NULL,
            nitrogen VARCHAR(60) NULL,
            phosphorus VARCHAR(60) NULL,
            potassium VARCHAR(60) NULL,
            organic_matter VARCHAR(80) NULL,
            salinity VARCHAR(80) NULL,
            moisture_condition VARCHAR(80) NULL,
            crop_variety VARCHAR(180) NULL,
            tree_age_years DECIMAL(5,2) NULL,
            production_stage VARCHAR(80) NULL,
            yield_estimate VARCHAR(120) NULL,
            notes TEXT NULL,
            recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_soil_crop_farm (farm_id, recorded_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'agronomy_soil_crop_records');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS agronomy_field_checklists (
            id INT AUTO_INCREMENT PRIMARY KEY,
            case_id INT NULL,
            farm_id INT NOT NULL,
            visit_id INT NULL,
            agent_id INT NULL,
            crop_symptoms TEXT NULL,
            pest_signs TEXT NULL,
            weed_pressure VARCHAR(40) NULL,
            water_stress VARCHAR(40) NULL,
            soil_condition TEXT NULL,
            farmer_notes TEXT NULL,
            photos TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_agronomy_checklists_farm (farm_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'agronomy_field_checklists');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS agronomy_advisory_templates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(180) NOT NULL,
            category VARCHAR(60) NOT NULL DEFAULT 'general',
            crop_stage VARCHAR(80) NULL,
            body TEXT NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_by INT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_advisory_templates_active (is_active, category)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'agronomy_advisory_templates');
}

function agronomy_case_ref(): string
{
    return 'AGR-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

function agronomy_categories(): array
{
    return [
        'general' => 'General Agronomy',
        'soil_nutrients' => 'Soil & Nutrients',
        'pest_disease' => 'Pest & Disease',
        'water_irrigation' => 'Water & Irrigation',
        'crop_management' => 'Crop Management',
        'environment' => 'Environmental Stewardship',
        'technology' => 'Technology Integration',
    ];
}

function agronomy_statuses(): array
{
    return [
        'open' => 'Open',
        'assigned' => 'Assigned',
        'under_review' => 'Under Review',
        'recommendation_issued' => 'Recommendation Issued',
        'follow_up_needed' => 'Follow-up Needed',
        'resolved' => 'Resolved',
        'closed' => 'Closed',
    ];
}

function agronomy_priorities(): array
{
    return ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'];
}
