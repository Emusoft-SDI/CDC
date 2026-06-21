-- NATCODEV Database Schema Snapshot
-- Generated on June 10, 2026
-- This file contains a snapshot of all table definitions extracted from the JIT schema management logic.
-- NOTE: This is a read-only snapshot and should not be used to initialize a new database without modification.

-- --- ACADEMY SCHEMA ---
-- (Extracted from lib/academy.php)
CREATE TABLE IF NOT EXISTS academy_programs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(180) NOT NULL,
    description TEXT NULL,
    audience_roles VARCHAR(500) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_academy_program_title (title),
    INDEX idx_academy_program_status (status, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS webinars (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    start_time DATETIME NOT NULL,
    duration_minutes INT NOT NULL DEFAULT 60,
    is_free TINYINT(1) NOT NULL DEFAULT 1,
    price DECIMAL(12,2) NOT NULL DEFAULT 0,
    zoom_link VARCHAR(500) NULL,
    max_attendees INT NOT NULL DEFAULT 100,
    category VARCHAR(80) NOT NULL DEFAULT 'Training',
    target_roles VARCHAR(500) NULL,
    certification_required TINYINT(1) NOT NULL DEFAULT 0,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_webinars_status_time (status, start_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE webinars ADD UNIQUE KEY uniq_webinar_title (title);

CREATE TABLE IF NOT EXISTS webinar_registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    webinar_id INT NOT NULL,
    user_id INT NOT NULL,
    payment_status VARCHAR(30) NOT NULL DEFAULT 'free',
    progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
    completion_status VARCHAR(30) NOT NULL DEFAULT 'registered',
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    certificate_status VARCHAR(30) NOT NULL DEFAULT 'not_required',
    registered_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_webinar_user (webinar_id, user_id),
    INDEX idx_webinar_registrations_user (user_id),
    INDEX idx_webinar_registrations_webinar (webinar_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS academy_lessons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    webinar_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    summary TEXT NULL,
    content LONGTEXT NULL,
    delivery_type VARCHAR(40) NOT NULL DEFAULT 'document',
    material_url VARCHAR(500) NULL,
    duration_minutes INT NOT NULL DEFAULT 20,
    sort_order INT NOT NULL DEFAULT 0,
    is_required TINYINT(1) NOT NULL DEFAULT 1,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_academy_lessons_course (webinar_id, sort_order, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE academy_lessons ADD UNIQUE KEY uniq_academy_lesson_course_title (webinar_id, title);

CREATE TABLE IF NOT EXISTS academy_materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    webinar_id INT NOT NULL,
    lesson_id INT NULL,
    title VARCHAR(255) NOT NULL,
    material_type VARCHAR(40) NOT NULL DEFAULT 'link',
    material_url VARCHAR(500) NULL,
    file_path VARCHAR(255) NULL,
    notes TEXT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_academy_materials_course (webinar_id, lesson_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE academy_materials ADD UNIQUE KEY uniq_academy_material_course_title (webinar_id, title);

CREATE TABLE IF NOT EXISTS academy_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    webinar_id INT NOT NULL,
    lesson_id INT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'not_started',
    progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_academy_progress (user_id, webinar_id, lesson_id),
    INDEX idx_academy_progress_user (user_id, webinar_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS academy_assessments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    webinar_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    instructions TEXT NULL,
    pass_score DECIMAL(5,2) NOT NULL DEFAULT 70,
    max_attempts INT NOT NULL DEFAULT 3,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_academy_assessments_course (webinar_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE academy_assessments ADD UNIQUE KEY uniq_academy_assessment_course_title (webinar_id, title);

CREATE TABLE IF NOT EXISTS academy_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assessment_id INT NOT NULL,
    question_text TEXT NOT NULL,
    option_a VARCHAR(500) NOT NULL,
    option_b VARCHAR(500) NOT NULL,
    option_c VARCHAR(500) NULL,
    option_d VARCHAR(500) NULL,
    correct_option CHAR(1) NOT NULL DEFAULT 'A',
    points DECIMAL(6,2) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_academy_questions_assessment (assessment_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE academy_questions ADD UNIQUE KEY uniq_academy_question_assessment_text (assessment_id, question_text(180));

CREATE TABLE IF NOT EXISTS academy_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assessment_id INT NOT NULL,
    webinar_id INT NOT NULL,
    user_id INT NOT NULL,
    score_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
    passed TINYINT(1) NOT NULL DEFAULT 0,
    answers LONGTEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'submitted',
    started_at DATETIME NULL,
    completed_at DATETIME NOT NULL,
    INDEX idx_academy_attempts_user (user_id, webinar_id, completed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS academy_certificates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    webinar_id INT NOT NULL,
    registration_id INT NULL,
    certificate_ref VARCHAR(90) NOT NULL UNIQUE,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    issued_at DATETIME NULL,
    approved_by INT NULL,
    notes TEXT NULL,
    UNIQUE KEY uniq_academy_certificate_user_course (user_id, webinar_id),
    INDEX idx_academy_certificates_status (status, requested_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE academy_certificates ADD COLUMN IF NOT EXISTS certificate_pdf_path VARCHAR(255) NULL;

CREATE TABLE IF NOT EXISTS academy_certificate_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(180) NOT NULL,
    description TEXT NULL,
    audience_roles VARCHAR(500) NULL,
    certificate_approval_required TINYINT(1) NOT NULL DEFAULT 0,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_academy_certificate_group_title (title),
    INDEX idx_academy_certificate_group_status (status, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS academy_certificate_group_courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    webinar_id INT NOT NULL,
    is_required TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    UNIQUE KEY uniq_academy_group_course (group_id, webinar_id),
    INDEX idx_academy_group_courses_group (group_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS academy_group_certificates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    group_id INT NOT NULL,
    certificate_ref VARCHAR(90) NOT NULL UNIQUE,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    issued_at DATETIME NULL,
    approved_by INT NULL,
    notes TEXT NULL,
    certificate_pdf_path VARCHAR(255) NULL,
    UNIQUE KEY uniq_academy_group_certificate_user_group (user_id, group_id),
    INDEX idx_academy_group_certificates_status (status, requested_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS academy_refund_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    webinar_id INT NOT NULL,
    transaction_id INT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    reason TEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    admin_notes TEXT NULL,
    requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_by INT NULL,
    reviewed_at DATETIME NULL,
    INDEX idx_academy_refunds_status (status, requested_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS academy_instructors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(180) NOT NULL,
    email VARCHAR(180) NULL,
    phone VARCHAR(60) NULL,
    specialty VARCHAR(180) NULL,
    bio TEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_academy_instructors_status (status, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS academy_cohorts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    webinar_id INT NOT NULL,
    instructor_id INT NULL,
    title VARCHAR(180) NOT NULL,
    start_at DATETIME NOT NULL,
    end_at DATETIME NULL,
    venue VARCHAR(255) NULL,
    meeting_url VARCHAR(500) NULL,
    capacity INT NOT NULL DEFAULT 100,
    status VARCHAR(30) NOT NULL DEFAULT 'scheduled',
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_academy_cohorts_course (webinar_id, start_at),
    INDEX idx_academy_cohorts_status (status, start_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS academy_attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cohort_id INT NOT NULL,
    webinar_id INT NOT NULL,
    user_id INT NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'present',
    marked_by INT NULL,
    marked_at DATETIME NOT NULL,
    notes TEXT NULL,
    UNIQUE KEY uniq_academy_attendance_user_cohort (cohort_id, user_id),
    INDEX idx_academy_attendance_course (webinar_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS academy_reminders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    webinar_id INT NULL,
    cohort_id INT NULL,
    audience_roles VARCHAR(500) NULL,
    title VARCHAR(180) NOT NULL,
    message TEXT NOT NULL,
    channel VARCHAR(30) NOT NULL DEFAULT 'dashboard',
    send_at DATETIME NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'draft',
    created_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at DATETIME NULL,
    INDEX idx_academy_reminders_status (status, send_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS academy_feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    webinar_id INT NOT NULL,
    user_id INT NOT NULL,
    rating TINYINT UNSIGNED NOT NULL DEFAULT 5,
    comment TEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'visible',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_academy_feedback_user_course (webinar_id, user_id),
    INDEX idx_academy_feedback_course (webinar_id, rating)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- ADMIN LAYOUT SCHEMA ---
-- (Extracted from lib/admin-layout.php)
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS resources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    file_path VARCHAR(255) NOT NULL,
    category VARCHAR(80) NOT NULL DEFAULT 'Guides',
    offline_available TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_resources_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    key_name VARCHAR(120) NOT NULL UNIQUE,
    value TEXT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_name VARCHAR(100) NOT NULL,
    template_type VARCHAR(40) NOT NULL,
    message_template TEXT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_template_channel (template_name, template_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    action VARCHAR(120) NOT NULL,
    description TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- AGRONOMY SCHEMA ---
-- (Extracted from lib/agronomy.php)
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- DISASTER RECOVERY SCHEMA ---
-- (Extracted from lib/disaster-recovery.php)
CREATE TABLE IF NOT EXISTS site_nodes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    node_key VARCHAR(80) NOT NULL UNIQUE,
    name VARCHAR(160) NOT NULL,
    base_url VARCHAR(255) NOT NULL,
    node_role VARCHAR(40) NOT NULL DEFAULT 'replica',
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    sync_enabled TINYINT(1) NOT NULL DEFAULT 1,
    shared_secret_hash VARCHAR(255) NULL,
    last_seen_at DATETIME NULL,
    last_error TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_site_nodes_status (status, sync_enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dr_backups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    backup_ref VARCHAR(80) NOT NULL UNIQUE,
    backup_type VARCHAR(40) NOT NULL DEFAULT 'manifest',
    status VARCHAR(30) NOT NULL DEFAULT 'completed',
    storage_path VARCHAR(255) NULL,
    file_size BIGINT NOT NULL DEFAULT 0,
    checksum VARCHAR(128) NULL,
    notes TEXT NULL,
    created_by INT NULL,
    started_at DATETIME NOT NULL,
    completed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_dr_backups_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sync_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_uuid VARCHAR(80) NOT NULL UNIQUE,
    direction VARCHAR(20) NOT NULL DEFAULT 'outbound',
    event_type VARCHAR(80) NOT NULL,
    entity_table VARCHAR(80) NULL,
    entity_id VARCHAR(80) NULL,
    payload_json LONGTEXT NULL,
    source_node VARCHAR(80) NULL,
    target_node VARCHAR(80) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    attempts INT NOT NULL DEFAULT 0,
    error_message TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME NULL,
    INDEX idx_sync_events_status (status, direction, created_at),
    INDEX idx_sync_events_type (event_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- FIELD MANAGEMENT SCHEMA ---
-- (Extracted from lib/field-management.php)
CREATE TABLE IF NOT EXISTS nigeria_states (
    id INT AUTO_INCREMENT PRIMARY KEY,
    state_name VARCHAR(100) NOT NULL UNIQUE,
    state_code VARCHAR(10) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS nigeria_lgas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lga_name VARCHAR(100) NOT NULL,
    state_id INT NOT NULL,
    UNIQUE KEY uniq_lga_state (lga_name, state_id),
    INDEX idx_lgas_state (state_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS grower_farms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    application_id INT NULL,
    farm_name VARCHAR(160) NOT NULL,
    farm_size DECIMAL(10,2) NULL,
    state_id INT NULL,
    lga_id INT NULL,
    street_address VARCHAR(255) NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_grower_farms_user (user_id),
    INDEX idx_grower_farms_application (application_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS farm_verifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    farm_id INT NOT NULL,
    requested_by INT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    system_confidence_score DECIMAL(5,2) NULL,
    system_notes TEXT NULL,
    admin_decision VARCHAR(30) NULL,
    reviewed_by INT NULL,
    reviewed_at DATETIME NULL,
    rejection_reason TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_farm_verification_farm (farm_id),
    INDEX idx_farm_verifications_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS field_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    farm_id INT NOT NULL,
    assigned_to INT NULL,
    task_type VARCHAR(40) NOT NULL DEFAULT 'verification',
    priority VARCHAR(20) NOT NULL DEFAULT 'normal',
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    due_date DATE NULL,
    notes TEXT NULL,
    created_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_field_tasks_agent (assigned_to, status),
    INDEX idx_field_tasks_farm (farm_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS farm_visits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    farm_id INT NOT NULL,
    task_id INT NULL,
    agent_id INT NOT NULL,
    visit_latitude DECIMAL(10,7) NULL,
    visit_longitude DECIMAL(10,7) NULL,
    distance_from_submitted_location_m DECIMAL(12,2) NULL,
    client_visit_id VARCHAR(100) NULL,
    sync_source VARCHAR(30) NOT NULL DEFAULT 'online_form',
    photos TEXT NULL,
    notes TEXT NULL,
    result VARCHAR(30) NOT NULL DEFAULT 'submitted',
    visited_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_farm_visits_farm (farm_id),
    INDEX idx_farm_visits_agent (agent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS farm_weather_snapshots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    farm_id INT NOT NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    temperature_c DECIMAL(5,2) NULL,
    rainfall_mm DECIMAL(8,2) NULL,
    humidity_percent DECIMAL(5,2) NULL,
    wind_kph DECIMAL(6,2) NULL,
    provider VARCHAR(60) NOT NULL DEFAULT 'local_estimate',
    summary VARCHAR(255) NULL,
    captured_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_weather_farm_time (farm_id, captured_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS farm_boundaries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    farm_id INT NOT NULL UNIQUE,
    polygon_geojson LONGTEXT NULL,
    created_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- MARKETPLACE SCHEMA ---
-- (Extracted from lib/marketplace.php)
CREATE TABLE IF NOT EXISTS marketplace_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    slug VARCHAR(180) NOT NULL UNIQUE,
    listing_type VARCHAR(40) NOT NULL DEFAULT 'product',
    description TEXT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_marketplace_categories_type (listing_type, is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS marketplace_sellers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    seller_type VARCHAR(60) NOT NULL DEFAULT 'grower',
    store_name VARCHAR(180) NOT NULL,
    slug VARCHAR(200) NOT NULL UNIQUE,
    description TEXT NULL,
    contact_person VARCHAR(160) NULL,
    email VARCHAR(255) NULL,
    phone VARCHAR(60) NULL,
    whatsapp VARCHAR(60) NULL,
    location_label VARCHAR(255) NULL,
    coverage_area TEXT NULL,
    fulfillment_options TEXT NULL,
    approval_status VARCHAR(40) NOT NULL DEFAULT 'pending',
    verification_status VARCHAR(40) NOT NULL DEFAULT 'unverified',
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    admin_notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_marketplace_sellers_user (user_id),
    INDEX idx_marketplace_sellers_status (approval_status, verification_status, is_featured)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS marketplace_listings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id INT NOT NULL,
    category_id INT NULL,
    source_item_id INT NULL,
    listing_type VARCHAR(40) NOT NULL DEFAULT 'product',
    title VARCHAR(220) NOT NULL,
    slug VARCHAR(240) NOT NULL UNIQUE,
    summary VARCHAR(500) NULL,
    description TEXT NULL,
    price DECIMAL(14,2) NOT NULL DEFAULT 0,
    price_unit VARCHAR(60) NULL,
    quantity_available DECIMAL(14,2) NULL,
    unit VARCHAR(60) NULL,
    min_order_quantity DECIMAL(14,2) NULL,
    location_label VARCHAR(255) NULL,
    fulfillment_method VARCHAR(180) NULL,
    availability_status VARCHAR(40) NOT NULL DEFAULT 'available',
    approval_status VARCHAR(40) NOT NULL DEFAULT 'pending',
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    image_path VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_marketplace_listings_seller (seller_id, approval_status, availability_status),
    INDEX idx_marketplace_listings_category (category_id, listing_type),
    INDEX idx_marketplace_listings_source (source_item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS marketplace_inquiries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inquiry_ref VARCHAR(80) NOT NULL UNIQUE,
    listing_id INT NOT NULL,
    seller_id INT NOT NULL,
    buyer_user_id INT NULL,
    buyer_name VARCHAR(180) NOT NULL,
    buyer_email VARCHAR(255) NULL,
    buyer_phone VARCHAR(80) NULL,
    quantity DECIMAL(14,2) NULL,
    preferred_date DATE NULL,
    message TEXT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'new',
    seller_reply TEXT NULL,
    quoted_amount DECIMAL(14,2) NULL,
    quoted_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_marketplace_inquiries_seller (seller_id, status, created_at),
    INDEX idx_marketplace_inquiries_buyer (buyer_user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS marketplace_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_ref VARCHAR(80) NOT NULL UNIQUE,
    inquiry_id INT NULL,
    listing_id INT NOT NULL,
    seller_id INT NOT NULL,
    buyer_user_id INT NULL,
    buyer_name VARCHAR(180) NOT NULL,
    buyer_email VARCHAR(255) NULL,
    buyer_phone VARCHAR(80) NULL,
    quantity DECIMAL(14,2) NOT NULL DEFAULT 1,
    unit_price DECIMAL(14,2) NOT NULL DEFAULT 0,
    total_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    status VARCHAR(40) NOT NULL DEFAULT 'requested',
    fulfillment_note TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_marketplace_orders_seller (seller_id, status, created_at),
    INDEX idx_marketplace_orders_buyer (buyer_user_id, status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS marketplace_favorites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    listing_id INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_marketplace_favorite (user_id, listing_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS marketplace_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    listing_id INT NOT NULL,
    seller_id INT NOT NULL,
    user_id INT NULL,
    rating TINYINT NOT NULL DEFAULT 5,
    review_text TEXT NULL,
    approval_status VARCHAR(40) NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_marketplace_reviews_listing (listing_id, approval_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS marketplace_promotions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    promo_ref VARCHAR(80) NOT NULL UNIQUE,
    seller_id INT NULL,
    listing_id INT NULL,
    category_id INT NULL,
    title VARCHAR(180) NOT NULL,
    subtitle VARCHAR(255) NULL,
    placement VARCHAR(60) NOT NULL DEFAULT 'homepage_banner',
    image_path VARCHAR(255) NULL,
    target_url VARCHAR(255) NULL,
    amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    duration_days INT NOT NULL DEFAULT 30,
    payment_method VARCHAR(40) NULL,
    payment_reference VARCHAR(120) NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'draft',
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    impressions INT NOT NULL DEFAULT 0,
    clicks INT NOT NULL DEFAULT 0,
    created_by INT NULL,
    approved_by INT NULL,
    approved_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_marketplace_promotions_active (placement, status, starts_at, ends_at),
    INDEX idx_marketplace_promotions_seller (seller_id, status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- SUPPORT SCHEMA ---
-- (Extracted from lib/support.php)
CREATE TABLE IF NOT EXISTS support_tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_ref VARCHAR(60) NOT NULL UNIQUE,
    user_id INT NULL,
    requester_name VARCHAR(160) NOT NULL,
    requester_email VARCHAR(190) NOT NULL,
    requester_phone VARCHAR(40) NULL,
    requester_role VARCHAR(80) NOT NULL DEFAULT 'public',
    source VARCHAR(40) NOT NULL DEFAULT 'web',
    category VARCHAR(80) NOT NULL DEFAULT 'general',
    module VARCHAR(80) NOT NULL DEFAULT 'general',
    subject VARCHAR(190) NOT NULL,
    description TEXT NOT NULL,
    priority VARCHAR(20) NOT NULL DEFAULT 'medium',
    status VARCHAR(30) NOT NULL DEFAULT 'open',
    outcome VARCHAR(30) NULL,
    linked_record_type VARCHAR(60) NULL,
    linked_record_ref VARCHAR(120) NULL,
    assigned_team VARCHAR(80) NULL,
    assigned_admin_id INT NULL,
    sla_due_at DATETIME NULL,
    first_response_at DATETIME NULL,
    resolved_at DATETIME NULL,
    last_activity_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_support_user (user_id, last_activity_at),
    INDEX idx_support_status (status, priority, last_activity_at),
    INDEX idx_support_category (category, module)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS support_ticket_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    user_id INT NULL,
    admin_id INT NULL,
    author_name VARCHAR(160) NOT NULL,
    author_role VARCHAR(80) NOT NULL DEFAULT 'public',
    message TEXT NOT NULL,
    visibility VARCHAR(30) NOT NULL DEFAULT 'public',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_support_messages_ticket (ticket_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS support_ticket_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    message_id INT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    mime_type VARCHAR(120) NULL,
    file_size INT NULL,
    uploaded_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_support_attachments_ticket (ticket_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
