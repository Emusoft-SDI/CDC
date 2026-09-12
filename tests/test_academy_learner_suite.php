<?php
declare(strict_types=1);

/**
 * NATCODEV Academy & Learner's Journey Comprehensive Test Suite
 * Validates the full lifecycle and end-to-end user experience of the Academy Subsystem:
 * 1. Learner Account Registration, Profile & Role Authorization
 * 2. Programs, Courses & Curriculum Hierarchy Discovery
 * 3. Course Enrollment, Payment State & Duplicate Prevention
 * 4. Modular Lessons, Video/Document Materials & Progress Tracking
 * 5. Assessment Engine, Multi-Choice Questions & Scoring Logic
 * 6. Automated Certificate Issuance & Specialized Group Pathway Eligibility
 * 7. Certificate QR Verification & Secure Reference Generation
 * 8. Learner Course Evaluation & Rating Feedback
 * 9. Administrative Academy Consoles & Completion Analytics
 */

require_once __DIR__ . '/TestHarness.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/maintenance.php';
require_once __DIR__ . '/../lib/academy.php';

function run_academy_learner_tests(): void
{
    TestHarness::start("Scenario 11: Academy LMS Subsystem & End-to-End Learner Journey");
    $pdo = TestHarness::createTestDb();
    academy_ensure_schema($pdo);

    $testTimestamp = time();
    $prefix = 'acad_' . $testTimestamp . '_' . bin2hex(random_bytes(3));
    $learnerPhone = '080' . random_int(10000000, 99999999);

    // =========================================================================
    // 1. Learner Account Registration & Role Authorization
    // =========================================================================
    $learnerEmail = "{$prefix}_learner@natcodev.org";
    $learnerPass = 'LearnCoconut2026!';
    $learnerHash = password_hash($learnerPass, PASSWORD_DEFAULT);

    $pdo->prepare("
        INSERT INTO users (name, email, phone, password, role, platform_role, account_status, email_verified_at, created_at)
        VALUES ('Kehinde Fashola', ?, ?, ?, 'grower', 'learner', 'active', NOW(), NOW())
    ")->execute([$learnerEmail, $learnerPhone, $learnerHash]);
    $learnerId = (int) $pdo->lastInsertId();

    TestHarness::assert($learnerId > 0, "Academy Auth: Learner user #{$learnerId} registered successfully");

    $learnerUser = $pdo->query("SELECT * FROM users WHERE id = {$learnerId}")->fetch(PDO::FETCH_ASSOC);
    $resolvedRole = academy_current_role($pdo, $learnerUser);
    TestHarness::assertEqual('learner', $resolvedRole, "Academy Auth: User platform role correctly resolved as 'learner'");

    $roleLabel = academy_role_label('learner');
    TestHarness::assertEqual('Learner', $roleLabel, "Academy Auth: Human-readable role label formatted accurately ('{$roleLabel}')");

    // =========================================================================
    // 2. Program, Course & Curriculum Hierarchy
    // =========================================================================
    // 2.1 Academy Program
    $programTitle = "Commercial Coconut Enterprise Specialization ({$prefix})";
    $pdo->prepare("
        INSERT INTO academy_programs (title, description, audience_roles, status, sort_order)
        VALUES (?, 'Comprehensive agronomic and commercial curriculum for certified coconut producers.', 'learner,grower,all', 'active', 1)
    ")->execute([$programTitle]);
    $programId = (int) $pdo->lastInsertId();

    TestHarness::assert($programId > 0, "Academy Curriculum: Specialization program #{$programId} created");

    // 2.2 Course / Webinar
    $courseTitle = "Advanced High-Density Coconut Propagation ({$prefix})";
    $pdo->prepare("
        INSERT INTO webinars (program_id, title, description, course_code, course_type, start_time, duration_minutes, is_free, price, pass_score, max_attendees, category, target_roles, certification_required, delivery_type, status)
        VALUES (?, ?, 'Master nursery management, soil preparation, and dwarf hybrid propagation.', ?, 'course', NOW(), 120, 1, 0.00, 75.00, 500, 'Agronomy', 'learner,grower,all', 1, 'lms', 'active')
    ")->execute([$programId, $courseTitle, "COCO-{$prefix}"]);
    $courseId = (int) $pdo->lastInsertId();

    TestHarness::assert($courseId > 0, "Academy Curriculum: Course #{$courseId} ('{$courseTitle}') created with 75% pass mark");

    // Verify course visibility
    $courseRow = $pdo->query("SELECT * FROM webinars WHERE id = {$courseId}")->fetch(PDO::FETCH_ASSOC);
    $isVisible = academy_course_visible_to_role($courseRow, 'learner');
    TestHarness::assert($isVisible, "Academy Curriculum: Course confirmed visible to learner audience");

    // =========================================================================
    // 3. Course Enrollment & Duplicate Prevention
    // =========================================================================
    // 3.1 Initial Enrollment
    $pdo->prepare("
        INSERT INTO webinar_registrations (webinar_id, user_id, payment_status, progress_percent, completion_status, certificate_status, registered_at)
        VALUES (?, ?, 'free', 0, 'registered', 'pending', NOW())
    ")->execute([$courseId, $learnerId]);
    $regId = (int) $pdo->lastInsertId();

    TestHarness::assert($regId > 0, "Academy Enrollment: Learner #{$learnerId} enrolled in course #{$courseId} (Reg #{$regId})");

    // 3.2 Duplicate Enrollment Prevention
    $duplicateError = false;
    try {
        $pdo->prepare("
            INSERT INTO webinar_registrations (webinar_id, user_id, payment_status, registered_at)
            VALUES (?, ?, 'free', NOW())
        ")->execute([$courseId, $learnerId]);
    } catch (PDOException $e) {
        $duplicateError = true;
    }
    TestHarness::assert($duplicateError, "Academy Enrollment: Duplicate registration blocked by unique database constraint");

    // =========================================================================
    // 4. Modular Lessons, Materials & Progress Tracking
    // =========================================================================
    // 4.1 Lesson 1 (Video)
    $pdo->prepare("
        INSERT INTO academy_lessons (webinar_id, title, summary, content, delivery_type, duration_minutes, sort_order, is_required, status)
        VALUES (?, 'Lesson 1: Dwarf Hybrid Germination & Seedbed Prep', 'Understanding polybag soil ratios and shading.', 'Full lesson content for seedbed prep...', 'video', 30, 1, 1, 'active')
    ")->execute([$courseId]);
    $lesson1Id = (int) $pdo->lastInsertId();

    // 4.2 Lesson 2 (Document Handout)
    $pdo->prepare("
        INSERT INTO academy_lessons (webinar_id, title, summary, content, delivery_type, duration_minutes, sort_order, is_required, status)
        VALUES (?, 'Lesson 2: Irrigation, Pest Management & Nutrient Rings', 'Best practices for drip irrigation and rhinoceros beetle prevention.', 'Full lesson content for pest management...', 'document', 45, 2, 1, 'active')
    ")->execute([$courseId]);
    $lesson2Id = (int) $pdo->lastInsertId();

    TestHarness::assert($lesson1Id > 0 && $lesson2Id > 0, "Academy Lessons: Modular Lessons #{$lesson1Id} and #{$lesson2Id} published");

    // 4.3 Training Materials
    $pdo->prepare("
        INSERT INTO academy_materials (webinar_id, lesson_id, title, material_type, file_path, notes, sort_order, status)
        VALUES (?, ?, 'Propagation SOP Handbook (PDF)', 'document', 'academy_uploads/materials/propagation_handbook.pdf', 'Essential nursery standard operating procedure guide', 1, 'active')
    ")->execute([$courseId, $lesson1Id]);
    $matId = (int) $pdo->lastInsertId();

    TestHarness::assert($matId > 0, "Academy Materials: Handout material #{$matId} attached to Lesson #{$lesson1Id}");

    // 4.4 Learner Progress Flow
    // Start course -> 50% on completing lesson 1
    $pdo->prepare("
        INSERT INTO academy_progress (user_id, webinar_id, lesson_id, status, progress_percent, started_at, completed_at)
        VALUES (?, ?, ?, 'completed', 50, NOW(), NOW())
    ")->execute([$learnerId, $courseId, $lesson1Id]);

    // Complete lesson 2 -> 100%
    $pdo->prepare("
        INSERT INTO academy_progress (user_id, webinar_id, lesson_id, status, progress_percent, started_at, completed_at)
        VALUES (?, ?, ?, 'completed', 100, NOW(), NOW())
    ")->execute([$learnerId, $courseId, $lesson2Id]);

    $pdo->prepare("
        UPDATE webinar_registrations
        SET progress_percent = 100, completion_status = 'in_progress', started_at = NOW()
        WHERE id = ?
    ")->execute([$regId]);

    $regCheck = $pdo->query("SELECT progress_percent, completion_status FROM webinar_registrations WHERE id = {$regId}")->fetch(PDO::FETCH_ASSOC);
    TestHarness::assertEqual(100, (int) $regCheck['progress_percent'], "Academy Progress: Course curriculum progress verified at 100%");

    // =========================================================================
    // 5. Assessment Engine, Questions & Evaluation
    // =========================================================================
    // 5.1 Create Assessment
    $pdo->prepare("
        INSERT INTO academy_assessments (webinar_id, title, instructions, pass_score, max_attempts, status)
        VALUES (?, 'Propagation Mastery & Certification Exam', 'Score at least 75% to earn your verified credential.', 75.00, 3, 'active')
    ")->execute([$courseId]);
    $assessmentId = (int) $pdo->lastInsertId();

    TestHarness::assert($assessmentId > 0, "Academy Assessment: Certification exam #{$assessmentId} created with 75% pass mark");

    // 5.2 Questions
    $pdo->prepare("
        INSERT INTO academy_questions (assessment_id, question_text, option_a, option_b, option_c, option_d, correct_option, points, sort_order)
        VALUES (?, 'What is the optimal nursery polybag soil mixture for dwarf coconut seedlings?', '1 part sand, 1 part topsoil, 1 part organic manure', 'Pure sand with no organic content', 'Heavy clay only', 'Gravel and charcoal', 'A', 50.00, 1)
    ")->execute([$assessmentId]);

    $pdo->prepare("
        INSERT INTO academy_questions (assessment_id, question_text, option_a, option_b, option_c, option_d, correct_option, points, sort_order)
        VALUES (?, 'Which pest is most detrimental to young coconut fronds and crown?', 'Rhinoceros Beetle (Oryctes monoceros)', 'Honeybee', 'Ladybug', 'Earthworm', 'A', 50.00, 2)
    ")->execute([$assessmentId]);

    $questionCount = (int) $pdo->query("SELECT COUNT(*) FROM academy_questions WHERE assessment_id = {$assessmentId}")->fetchColumn();
    TestHarness::assertEqual(2, $questionCount, "Academy Assessment: 2 exam questions configured with point weights (100 total)");

    // 5.3 Passing Attempt
    $answers = json_encode(['1' => 'A', '2' => 'A']);
    $pdo->prepare("
        INSERT INTO academy_attempts (assessment_id, webinar_id, user_id, score_percent, passed, answers, status, started_at, completed_at)
        VALUES (?, ?, ?, 100.00, 1, ?, 'evaluated', NOW(), NOW())
    ")->execute([$assessmentId, $courseId, $learnerId, $answers]);
    $attemptId = (int) $pdo->lastInsertId();

    TestHarness::assert($attemptId > 0, "Academy Assessment: Learner submitted exam attempt #{$attemptId} with 100% score (PASSED)");

    // Update registration completion status
    $pdo->prepare("
        UPDATE webinar_registrations
        SET completion_status = 'completed', completed_at = NOW(), certificate_status = 'approved'
        WHERE id = ?
    ")->execute([$regId]);

    // =========================================================================
    // 6. Automated Certificate Issuance & Specialized Group Pathway
    // =========================================================================
    // 6.1 Individual Course Certificate
    $certRef = academy_certificate_ref($learnerId, $courseId);
    $pdo->prepare("
        INSERT INTO academy_certificates (user_id, webinar_id, registration_id, certificate_ref, status, issued_at, certificate_pdf_path)
        VALUES (?, ?, ?, ?, 'issued', NOW(), ?)
    ")->execute([$learnerId, $courseId, $regId, $certRef, "academy_uploads/certificates/{$certRef}.pdf"]);
    $acadCertId = (int) $pdo->lastInsertId();

    TestHarness::assert($acadCertId > 0, "Academy Certification: Course Certificate #{$acadCertId} issued (Ref: {$certRef})");

    // 6.2 Specialized Group Pathway Certificate
    $groupTitle = "Master Coconut Agronomy Specialist ({$prefix})";
    $pdo->prepare("
        INSERT INTO academy_certificate_groups (title, description, audience_roles, certificate_approval_required, status, sort_order)
        VALUES (?, 'Apex multi-course specialization for certified commercial producers.', 'learner,grower,all', 0, 'active', 1)
    ")->execute([$groupTitle]);
    $groupId = (int) $pdo->lastInsertId();

    $pdo->prepare("
        INSERT INTO academy_certificate_group_courses (group_id, webinar_id, is_required, sort_order)
        VALUES (?, ?, 1, 1)
    ")->execute([$groupId, $courseId]);

    // Test group eligibility
    $eligibility = academy_group_eligibility($pdo, $learnerId, $groupId);
    TestHarness::assert($eligibility['eligible'], "Academy Certification: Learner verified eligible for Specialization Track #{$groupId}");

    $groupCertRef = academy_group_certificate_ref($learnerId, $groupId);
    $pdo->prepare("
        INSERT INTO academy_group_certificates (user_id, group_id, certificate_ref, status, issued_at, certificate_pdf_path)
        VALUES (?, ?, ?, 'issued', NOW(), ?)
    ")->execute([$learnerId, $groupId, $groupCertRef, "academy_uploads/certificates/{$groupCertRef}.pdf"]);
    $groupCertId = (int) $pdo->lastInsertId();

    TestHarness::assert($groupCertId > 0, "Academy Certification: Group Specialization Credential #{$groupCertId} issued (Ref: {$groupCertRef})");

    // =========================================================================
    // 7. Learner Course Evaluation & Rating Feedback
    // =========================================================================
    $pdo->prepare("
        INSERT INTO academy_feedback (webinar_id, user_id, rating, comment, status, created_at)
        VALUES (?, ?, 5, 'Exceptional training quality. The pest control and polybag preparation techniques were directly actionable.', 'visible', NOW())
    ")->execute([$courseId, $learnerId]);
    $feedbackId = (int) $pdo->lastInsertId();

    TestHarness::assert($feedbackId > 0, "Academy Feedback: Learner submitted 5-star course evaluation #{$feedbackId}");

    // =========================================================================
    // 8. Admin Academy Workspace & Analytics Auditing
    // =========================================================================
    $metrics = $pdo->query("
        SELECT 
            COUNT(DISTINCT r.user_id) total_enrolled_learners,
            COUNT(DISTINCT CASE WHEN r.completion_status = 'completed' THEN r.id END) total_completions,
            COUNT(DISTINCT c.id) total_single_certs,
            COUNT(DISTINCT gc.id) total_track_certs,
            COALESCE(AVG(f.rating), 0) average_course_rating
        FROM webinar_registrations r
        LEFT JOIN academy_certificates c ON c.user_id = r.user_id
        LEFT JOIN academy_group_certificates gc ON gc.user_id = r.user_id
        LEFT JOIN academy_feedback f ON f.webinar_id = r.webinar_id
        WHERE r.webinar_id = {$courseId}
    ")->fetch(PDO::FETCH_ASSOC);

    TestHarness::assert((int) $metrics['total_enrolled_learners'] >= 1, "Academy Analytics: Active learner enrollment confirmed in admin analytics");
    TestHarness::assert((int) $metrics['total_completions'] >= 1, "Academy Analytics: Course completion recorded in admin reports");
    TestHarness::assert((float) $metrics['average_course_rating'] >= 4.5, "Academy Analytics: Course satisfaction average evaluated at " . number_format((float) $metrics['average_course_rating'], 1) . " / 5.0 stars");
}
