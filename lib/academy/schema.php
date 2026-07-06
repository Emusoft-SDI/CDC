<?php
declare(strict_types=1);

function academy_index_exists(PDO $pdo, string $table, string $index): bool
{
    static $cache = [];
    $key = $table . '.' . $index;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?
    ");
    $stmt->execute([$table, $index]);
    $cache[$key] = (int) $stmt->fetchColumn() > 0;
    return $cache[$key];
}
function academy_ensure_schema(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }
    $ensured = true;

    app_ensure_farmer_engagement_schema($pdo);

    $pdo->exec("
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'academy_programs');

    $pdo->exec("
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'webinars');
    if (!academy_index_exists($pdo, 'webinars', 'uniq_webinar_title')) {
        try {
            $pdo->exec("ALTER TABLE webinars ADD UNIQUE KEY uniq_webinar_title (title)");
        } catch (Throwable $e) {
        }
    }

    foreach ([
        'program_id' => 'INT NULL',
        'course_code' => 'VARCHAR(80) NULL',
        'course_type' => "VARCHAR(40) NOT NULL DEFAULT 'course'",
        'prerequisites' => 'TEXT NULL',
        'pass_score' => 'DECIMAL(5,2) NOT NULL DEFAULT 70',
        'certificate_approval_required' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'instructor_name' => 'VARCHAR(160) NULL',
        'delivery_type' => "VARCHAR(40) NOT NULL DEFAULT 'live_zoom'",
        'delivery_url' => 'VARCHAR(500) NULL',
        'delivery_instructions' => 'TEXT NULL',
        'max_attendees' => 'INT NOT NULL DEFAULT 100',
        'category' => "VARCHAR(80) NOT NULL DEFAULT 'Training'",
        'target_roles' => 'VARCHAR(500) NULL',
        'certification_required' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'status' => "VARCHAR(30) NOT NULL DEFAULT 'active'",
        'updated_at' => 'TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP',
    ] as $column => $definition) {
        app_add_column_if_missing($pdo, 'webinars', $column, $definition);
    }
    try {
        $pdo->exec("UPDATE webinars SET delivery_url = zoom_link WHERE (delivery_url IS NULL OR delivery_url = '') AND zoom_link IS NOT NULL AND zoom_link <> ''");
    } catch (Throwable $e) {
    }

    $pdo->exec("
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'webinar_registrations');
    foreach ([
        'payment_status' => "VARCHAR(30) NOT NULL DEFAULT 'free'",
        'progress_percent' => 'TINYINT UNSIGNED NOT NULL DEFAULT 0',
        'completion_status' => "VARCHAR(30) NOT NULL DEFAULT 'registered'",
        'started_at' => 'DATETIME NULL',
        'completed_at' => 'DATETIME NULL',
        'certificate_status' => "VARCHAR(30) NOT NULL DEFAULT 'not_required'",
    ] as $column => $definition) {
        app_add_column_if_missing($pdo, 'webinar_registrations', $column, $definition);
    }
    academy_dedupe_webinar_registrations($pdo);
    if (!academy_index_exists($pdo, 'webinar_registrations', 'uniq_webinar_user')) {
        try {
            $pdo->exec("ALTER TABLE webinar_registrations ADD UNIQUE KEY uniq_webinar_user (webinar_id, user_id)");
        } catch (Throwable $e) {
        }
    }

    $pdo->exec("
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'academy_lessons');
    if (!academy_index_exists($pdo, 'academy_lessons', 'uniq_academy_lesson_course_title')) {
        try {
            $pdo->exec("ALTER TABLE academy_lessons ADD UNIQUE KEY uniq_academy_lesson_course_title (webinar_id, title)");
        } catch (Throwable $e) {
        }
    }

    $pdo->exec("
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'academy_materials');
    if (!academy_index_exists($pdo, 'academy_materials', 'uniq_academy_material_course_title')) {
        try {
            $pdo->exec("ALTER TABLE academy_materials ADD UNIQUE KEY uniq_academy_material_course_title (webinar_id, title)");
        } catch (Throwable $e) {
        }
    }

    $pdo->exec("
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'academy_progress');

    $pdo->exec("
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'academy_assessments');
    if (!academy_index_exists($pdo, 'academy_assessments', 'uniq_academy_assessment_course_title')) {
        try {
            $pdo->exec("ALTER TABLE academy_assessments ADD UNIQUE KEY uniq_academy_assessment_course_title (webinar_id, title)");
        } catch (Throwable $e) {
        }
    }

    $pdo->exec("
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'academy_questions');
    if (!academy_index_exists($pdo, 'academy_questions', 'uniq_academy_question_assessment_text')) {
        try {
            $pdo->exec("ALTER TABLE academy_questions ADD UNIQUE KEY uniq_academy_question_assessment_text (assessment_id, question_text(180))");
        } catch (Throwable $e) {
        }
    }

    $pdo->exec("
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'academy_attempts');

    $pdo->exec("
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'academy_certificates');
    app_add_column_if_missing($pdo, 'academy_certificates', 'certificate_pdf_path', 'VARCHAR(255) NULL');
    app_add_column_if_missing($pdo, 'academy_certificates', 'resubmission_notes', 'TEXT NULL');
    app_add_column_if_missing($pdo, 'academy_certificates', 'resubmitted_at', 'DATETIME NULL');

    $pdo->exec("
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'academy_certificate_groups');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS academy_certificate_group_courses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            group_id INT NOT NULL,
            webinar_id INT NOT NULL,
            is_required TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            UNIQUE KEY uniq_academy_group_course (group_id, webinar_id),
            INDEX idx_academy_group_courses_group (group_id, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'academy_certificate_group_courses');

    $pdo->exec("
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'academy_group_certificates');
    app_add_column_if_missing($pdo, 'academy_group_certificates', 'resubmission_notes', 'TEXT NULL');
    app_add_column_if_missing($pdo, 'academy_group_certificates', 'resubmitted_at', 'DATETIME NULL');

    $pdo->exec("
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'academy_refund_requests');

    $pdo->exec("
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'academy_instructors');

    $pdo->exec("
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'academy_cohorts');

    $pdo->exec("
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'academy_attendance');

    $pdo->exec("
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'academy_reminders');

    $pdo->exec("
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'academy_feedback');

    academy_ensure_seed_data($pdo);
}
function academy_ensure_seed_data(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $seedVersion = '20260627-1';
    $hasSettings = app_table_exists($pdo, 'settings');
    if ($hasSettings) {
        $stmt = $pdo->prepare("SELECT value FROM settings WHERE key_name = 'academy_seed_version' LIMIT 1");
        $stmt->execute();
        if ((string) $stmt->fetchColumn() === $seedVersion) {
            return;
        }
    }

    require_once __DIR__ . '/seeds.php';

    academy_seed_programs($pdo);
    academy_seed_starter_program_content($pdo);
    academy_seed_course_twenty_fixture($pdo);
    academy_seed_course_137_assignment($pdo);
    academy_normalize_course_categories($pdo);
    academy_seed_assignment_packs($pdo);
    academy_seed_certificate_pathways($pdo);
    academy_assign_programs_to_courses($pdo);

    if ($hasSettings) {
        try {
            $stmt = $pdo->prepare("INSERT INTO settings (key_name, value) VALUES ('academy_seed_version', ?) ON DUPLICATE KEY UPDATE value = VALUES(value)");
            $stmt->execute([$seedVersion]);
        } catch (Throwable $e) {
        }
    }
}
function academy_dedupe_webinar_registrations(PDO $pdo): void
{
    if (!app_table_exists($pdo, 'webinar_registrations')) {
        return;
    }
    try {
        if (app_table_exists($pdo, 'academy_certificates')) {
            $pdo->exec("
                UPDATE academy_certificates c
                JOIN webinar_registrations r ON r.id = c.registration_id
                JOIN (
                    SELECT webinar_id, user_id, MIN(id) keep_id
                    FROM webinar_registrations
                    GROUP BY webinar_id, user_id
                    HAVING COUNT(*) > 1
                ) d ON d.webinar_id = r.webinar_id AND d.user_id = r.user_id
                SET c.registration_id = d.keep_id
            ");
        }
        $pdo->exec("
            UPDATE webinar_registrations keep_r
            JOIN (
                SELECT webinar_id, user_id,
                       MIN(id) keep_id,
                       MAX(progress_percent) progress_percent,
                       MAX(CASE completion_status WHEN 'completed' THEN 3 WHEN 'in_progress' THEN 2 WHEN 'registered' THEN 1 ELSE 0 END) completion_rank,
                       MAX(CASE certificate_status WHEN 'issued' THEN 5 WHEN 'pending' THEN 4 WHEN 'eligible' THEN 3 WHEN 'not_started' THEN 2 WHEN 'not_required' THEN 1 ELSE 0 END) certificate_rank,
                       MIN(started_at) started_at,
                       MAX(completed_at) completed_at
                FROM webinar_registrations
                GROUP BY webinar_id, user_id
                HAVING COUNT(*) > 1
            ) d ON d.keep_id = keep_r.id
            SET keep_r.progress_percent = GREATEST(keep_r.progress_percent, d.progress_percent),
                keep_r.completion_status = CASE d.completion_rank WHEN 3 THEN 'completed' WHEN 2 THEN 'in_progress' WHEN 1 THEN 'registered' ELSE keep_r.completion_status END,
                keep_r.certificate_status = CASE d.certificate_rank WHEN 5 THEN 'issued' WHEN 4 THEN 'pending' WHEN 3 THEN 'eligible' WHEN 2 THEN 'not_started' WHEN 1 THEN 'not_required' ELSE keep_r.certificate_status END,
                keep_r.started_at = COALESCE(keep_r.started_at, d.started_at),
                keep_r.completed_at = COALESCE(keep_r.completed_at, d.completed_at)
        ");
        $pdo->exec("
            DELETE r
            FROM webinar_registrations r
            JOIN (
                SELECT webinar_id, user_id, MIN(id) keep_id
                FROM webinar_registrations
                GROUP BY webinar_id, user_id
                HAVING COUNT(*) > 1
            ) d ON d.webinar_id = r.webinar_id AND d.user_id = r.user_id
            WHERE r.id <> d.keep_id
        ");
    } catch (Throwable $e) {
        error_log('Unable to dedupe webinar registrations: ' . $e->getMessage());
    }
}
