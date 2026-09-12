<?php
declare(strict_types=1);

function academy_seed_course_twenty_fixture(PDO $pdo): void
{
    $programId = null;
    $stmt = $pdo->prepare("SELECT id FROM academy_programs WHERE title = ? LIMIT 1");
    $stmt->execute(['Grower Onboarding Program']);
    $foundProgram = $stmt->fetchColumn();
    if ($foundProgram !== false) {
        $programId = (int) $foundProgram;
    }

    $course = [
        'program' => 'Grower Onboarding Program',
        'title' => 'NATCODEV Academy Full Flow Test Course',
        'description' => 'A seeded end-to-end Academy course for testing public listing, learner registration, free enrollment, lesson completion, assessment submission, certificate eligibility, and paginated catalog discovery.',
        'roles' => 'all',
        'is_free' => 1,
        'price' => 0,
        'certification_required' => 1,
        'certificate_approval_required' => 0,
        'duration_minutes' => 95,
        'pass_score' => 70,
        'prerequisites' => 'Learner account access. This course is intentionally visible to every Academy stakeholder for QA.',
        'objectives' => ['Confirm catalog and pagination visibility.', 'Test learner enrollment and dashboard handoff.', 'Complete lessons, submit assessment, and unlock certificate eligibility.'],
        'checklist' => ['Find course ID 20 in the public catalog.', 'Open the learner dashboard course detail.', 'Enroll, mark lessons complete, submit the assessment, and request a certificate.'],
    ];
    $slug = 'natcodev-academy-full-flow-test-course';
    $pdfPath = "academy_uploads/materials/{$slug}-course-handout.pdf";
    $videoPath = "academy_uploads/videos/{$slug}-one-minute-brief.html";
    academy_write_course_pdf($pdfPath, $course);
    academy_write_video_brief($videoPath, $course);

    $pdo->prepare("
        INSERT INTO webinars
            (id, program_id, course_code, course_type, title, description, start_time, duration_minutes, is_free, price,
             delivery_type, delivery_url, zoom_link, delivery_instructions, max_attendees, category, target_roles,
             certification_required, prerequisites, pass_score, certificate_approval_required, instructor_name, status)
        VALUES
            (20, ?, 'NAT-ACAD-QA-020', 'certification', ?, ?, '2026-06-20 09:00:00', ?, ?, ?, 'mixed', ?, ?, ?, 1000,
             'NATCODEV Academy QA', ?, ?, ?, ?, ?, 'NATCODEV Academy Faculty', 'active')
        ON DUPLICATE KEY UPDATE
            program_id = VALUES(program_id),
            course_code = VALUES(course_code),
            course_type = VALUES(course_type),
            title = VALUES(title),
            description = VALUES(description),
            duration_minutes = VALUES(duration_minutes),
            is_free = VALUES(is_free),
            price = VALUES(price),
            delivery_type = VALUES(delivery_type),
            delivery_url = VALUES(delivery_url),
            zoom_link = VALUES(zoom_link),
            delivery_instructions = VALUES(delivery_instructions),
            category = VALUES(category),
            target_roles = VALUES(target_roles),
            certification_required = VALUES(certification_required),
            prerequisites = VALUES(prerequisites),
            pass_score = VALUES(pass_score),
            certificate_approval_required = VALUES(certificate_approval_required),
            instructor_name = VALUES(instructor_name),
            status = 'active'
    ")->execute([
        $programId,
        $course['title'],
        $course['description'],
        $course['duration_minutes'],
        $course['is_free'],
        $course['price'],
        '../' . $videoPath,
        '../' . $videoPath,
        "QA course for full-flow testing. Use this record to validate public listing, learner registration, enrollment, lessons, quiz, certificate, support, and transaction screens.",
        $course['roles'],
        $course['certification_required'],
        $course['prerequisites'],
        $course['pass_score'],
        $course['certificate_approval_required'],
    ]);

    $lessonStmt = $pdo->prepare("
        INSERT INTO academy_lessons
            (webinar_id, title, summary, content, delivery_type, material_url, duration_minutes, sort_order, is_required, status)
        VALUES (20, ?, ?, ?, ?, ?, ?, ?, 1, 'active')
        ON DUPLICATE KEY UPDATE
            summary = VALUES(summary),
            content = VALUES(content),
            delivery_type = VALUES(delivery_type),
            material_url = VALUES(material_url),
            duration_minutes = VALUES(duration_minutes),
            sort_order = VALUES(sort_order),
            status = 'active'
    ");
    foreach ([
        ['Public Catalog Discovery', 'Confirms the course can be found from the public Academy entry page.', 'Open the public Academy catalog, move through pagination, and confirm course ID 20 remains discoverable.', 'lms', '../academy/index.php?page=1#catalog', 15, 10],
        ['Learner Enrollment Flow', 'Tests the protected learner dashboard handoff and free enrollment.', 'Register or sign in as a learner, open the course detail, enroll free, and confirm the My Learning redirect.', 'lms', '../academy/dashboard.php?screen=course&course_id=20', 20, 20],
        ['Lesson Completion Flow', 'Exercises lesson progress and completion percentage updates.', 'Mark each seeded lesson complete, then confirm the course progress card updates in My Learning.', 'document', '../' . $pdfPath, 25, 30],
        ['Assessment And Certificate Flow', 'Validates quiz scoring and certificate eligibility.', 'Submit the assessment, pass with at least 70 percent, then request the course certificate.', 'video', '../' . $videoPath, 35, 40],
    ] as $lesson) {
        $lessonStmt->execute($lesson);
    }

    $materialStmt = $pdo->prepare("
        INSERT INTO academy_materials
            (webinar_id, lesson_id, title, material_type, material_url, file_path, notes, sort_order, status)
        VALUES (20, NULL, ?, ?, ?, ?, ?, ?, 'active')
        ON DUPLICATE KEY UPDATE
            material_type = VALUES(material_type),
            material_url = VALUES(material_url),
            file_path = VALUES(file_path),
            notes = VALUES(notes),
            sort_order = VALUES(sort_order),
            status = 'active'
    ");
    $materialStmt->execute(['Course 20 QA Handout', 'pdf', '../' . $pdfPath, $pdfPath, 'Seeded handout for Academy course ID 20 QA.', 10]);
    $materialStmt->execute(['Course 20 QA Video Brief', 'video', '../' . $videoPath, $videoPath, 'Seeded browser-playable video brief for course ID 20 QA.', 20]);

    $pdo->prepare("
        INSERT INTO academy_assessments (webinar_id, title, instructions, pass_score, max_attempts, status)
        VALUES (20, 'Course 20 Full Flow Assessment', 'Answer all questions to validate the Academy QA course. Passing unlocks certificate eligibility.', 70, 3, 'active')
        ON DUPLICATE KEY UPDATE instructions = VALUES(instructions), pass_score = VALUES(pass_score), max_attempts = VALUES(max_attempts), status = 'active'
    ")->execute();
    $assessmentStmt = $pdo->prepare("SELECT id FROM academy_assessments WHERE webinar_id = 20 AND title = 'Course 20 Full Flow Assessment' LIMIT 1");
    $assessmentStmt->execute();
    $assessmentId = (int) $assessmentStmt->fetchColumn();
    if ($assessmentId <= 0) {
        return;
    }

    $questionStmt = $pdo->prepare("
        INSERT INTO academy_questions
            (assessment_id, question_text, option_a, option_b, option_c, option_d, correct_option, points, sort_order, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, 'active')
        ON DUPLICATE KEY UPDATE
            option_a = VALUES(option_a),
            option_b = VALUES(option_b),
            option_c = VALUES(option_c),
            option_d = VALUES(option_d),
            correct_option = VALUES(correct_option),
            sort_order = VALUES(sort_order),
            status = 'active'
    ");
    foreach ([
        ['What is course ID 20 used to validate?', 'Full Academy public listing and learner flow', 'Only password reset', 'Only product deletion', 'Only provider payout', 'A'],
        ['Who can see the seeded QA course?', 'All Academy stakeholder roles', 'Only deleted users', 'Only anonymous orders', 'Only expired sessions', 'A'],
        ['What happens after a learner enrolls in the free QA course?', 'The course appears in My Learning', 'The cart is emptied', 'The role is auto-approved as vendor', 'The account is deleted', 'A'],
        ['What must happen before certificate eligibility?', 'Complete learning and pass the assessment', 'Skip all lessons', 'Use another learner reference', 'Close the browser only', 'A'],
        ['Why is pagination required for Academy listings?', 'So courses remain discoverable as the catalog grows', 'To hide active courses', 'To remove search', 'To block enrollment', 'A'],
    ] as $index => $question) {
        $questionStmt->execute(array_merge([$assessmentId], $question, [($index + 1) * 10]));
    }
}

function academy_seed_course_137_assignment(PDO $pdo): void
{
    $courseId = 137;
    $courseStmt = $pdo->prepare("SELECT id FROM webinars WHERE id = ? LIMIT 1");
    $courseStmt->execute([$courseId]);
    if (!(int) $courseStmt->fetchColumn()) {
        return;
    }

    $title = 'Course 137 Professional Readiness Assignment';
    $pdo->prepare("
        INSERT INTO academy_assessments (webinar_id, title, instructions, pass_score, max_attempts, status)
        VALUES (?, ?, 'Complete all questions. A score of 70 percent or higher is required before certificate eligibility.', 70, 3, 'active')
        ON DUPLICATE KEY UPDATE instructions = VALUES(instructions), pass_score = VALUES(pass_score), max_attempts = VALUES(max_attempts), status = 'active'
    ")->execute([$courseId, $title]);

    $assessmentStmt = $pdo->prepare("SELECT id FROM academy_assessments WHERE webinar_id = ? AND title = ? LIMIT 1");
    $assessmentStmt->execute([$courseId, $title]);
    $assessmentId = (int) $assessmentStmt->fetchColumn();
    if ($assessmentId <= 0) {
        return;
    }

    $questionStmt = $pdo->prepare("
        INSERT INTO academy_questions
            (assessment_id, question_text, option_a, option_b, option_c, option_d, correct_option, points, sort_order, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, 'active')
        ON DUPLICATE KEY UPDATE
            option_a = VALUES(option_a),
            option_b = VALUES(option_b),
            option_c = VALUES(option_c),
            option_d = VALUES(option_d),
            correct_option = VALUES(correct_option),
            sort_order = VALUES(sort_order),
            status = 'active'
    ");

    foreach ([
        ['What should a learner confirm before requesting a NATCODEV course certificate?', 'All required lessons are completed and the assessment is passed', 'Only the course title is visible', 'The browser tab has been closed', 'A wallet balance is hidden', 'A'],
        ['Which record best proves practical learning progress?', 'Completed lessons, submitted assessment, and recorded score', 'A screenshot with no course data', 'A blank profile page', 'A public support message only', 'A'],
        ['When a course includes paid enrollment, what should happen before full access is granted?', 'Payment or approved sponsorship should be confirmed in the system', 'The learner should skip payment', 'The admin menu should be opened', 'The certificate should be issued first', 'A'],
        ['What is the correct action if a learner does not meet the pass mark?', 'Review the course material and retake within the allowed attempts', 'Request a certificate immediately', 'Delete the course record', 'Change another user profile', 'A'],
        ['Why should assignments use database questions instead of page placeholders?', 'So learner scores, attempts, and certificates are traceable', 'So every learner gets automatic full marks', 'So the quiz can ignore submissions', 'So support tickets become public', 'A'],
        ['Which behavior protects certificate integrity?', 'Issue certificates only after completion and a passing assessment result', 'Issue certificates before enrollment', 'Approve certificates with no score', 'Reuse another learner certificate', 'A'],
        ['What should a learner do when course instructions are unclear?', 'Use the logged-in Academy Help and Support channel', 'Post private account data publicly', 'Open the grower dashboard', 'Ignore the assessment', 'A'],
        ['Which option describes a professional course assessment question?', 'It checks a real learning outcome with one correct answer', 'It has no connection to the lesson', 'It has no answer options', 'It awards marks randomly', 'A'],
        ['Why are retake limits important in Academy assessments?', 'They keep assessment attempts controlled and auditable', 'They remove the need for lessons', 'They make every user an admin', 'They hide failed attempts', 'A'],
        ['What should the platform show after a valid quiz submission?', 'Score, pass status, and updated certificate eligibility where applicable', 'A blank dashboard only', 'A public login page only', 'Another learner wallet', 'A'],
    ] as $index => $question) {
        $questionStmt->execute(array_merge([$assessmentId], $question, [($index + 1) * 10]));
    }
}

function academy_inferred_course_category(array $course): string
{
    $haystack = strtolower(
        (string) ($course['title'] ?? '') . ' ' .
        (string) ($course['description'] ?? '') . ' ' .
        (string) ($course['target_roles'] ?? '') . ' ' .
        (string) ($course['program_title'] ?? '')
    );

    if (str_contains($haystack, 'wallet') || str_contains($haystack, 'payment') || str_contains($haystack, 'refund') || str_contains($haystack, 'finance')) {
        return 'Finance & Platform Skills';
    }
    if (str_contains($haystack, 'market') || str_contains($haystack, 'seller') || str_contains($haystack, 'buyer') || str_contains($haystack, 'order')) {
        return 'Marketplace & Commerce';
    }
    if (str_contains($haystack, 'provider') || str_contains($haystack, 'input') || str_contains($haystack, 'service') || str_contains($haystack, 'accreditation')) {
        return 'Provider Accreditation';
    }
    if (str_contains($haystack, 'field') || str_contains($haystack, 'advisory') || str_contains($haystack, 'agent') || str_contains($haystack, 'agronom') || str_contains($haystack, 'extension')) {
        return 'Field & Advisory';
    }
    if (str_contains($haystack, 'coordinator') || str_contains($haystack, 'governance') || str_contains($haystack, 'admin') || str_contains($haystack, 'state') || str_contains($haystack, 'national')) {
        return 'Coordination & Governance';
    }
    if (str_contains($haystack, 'nursery') || str_contains($haystack, 'seedling') || str_contains($haystack, 'farm') || str_contains($haystack, 'grower') || str_contains($haystack, 'yield') || str_contains($haystack, 'coconut')) {
        return 'Grower & Farm Production';
    }
    return 'Professional Skills';
}

function academy_normalize_course_categories(PDO $pdo): void
{
    if (!app_table_exists($pdo, 'webinars')) {
        return;
    }

    $rows = $pdo->query("
        SELECT w.id, w.title, w.description, w.target_roles, w.category, p.title program_title
        FROM webinars w
        LEFT JOIN academy_programs p ON p.id = w.program_id
        WHERE COALESCE(w.status, 'active') = 'active'
    ")->fetchAll();
    $update = $pdo->prepare("UPDATE webinars SET category = ? WHERE id = ?");
    foreach ($rows as $row) {
        $category = academy_inferred_course_category($row);
        if ((string) ($row['category'] ?? '') !== $category) {
            $update->execute([$category, (int) $row['id']]);
        }
    }
}

function academy_assign_programs_to_courses(PDO $pdo): void
{
    if (!app_table_exists($pdo, 'webinars')) {
        return;
    }
    $programRows = $pdo->query("SELECT id, title, audience_roles FROM academy_programs WHERE status = 'active'")->fetchAll();
    $programs = [];
    foreach ($programRows as $program) {
        $programs[(string) $program['title']] = $program;
    }
    $courses = $pdo->query("SELECT id, title, target_roles, program_id FROM webinars WHERE program_id IS NULL OR program_id = 0")->fetchAll();
    $update = $pdo->prepare("UPDATE webinars SET program_id = ? WHERE id = ?");
    foreach ($courses as $course) {
        $roles = array_values(array_filter(array_map('trim', explode(',', (string) ($course['target_roles'] ?? '')))));
        $title = strtolower((string) ($course['title'] ?? ''));
        $programTitle = 'Grower & Farm Workforce Academy';
        if (array_intersect($roles, ['provider', 'input_provider', 'service_provider', 'seller']) || str_contains($title, 'provider') || str_contains($title, 'seller') || str_contains($title, 'marketplace')) {
            $programTitle = 'Input & Service Provider Academy';
        }
        if (array_intersect($roles, ['field_agent', 'agronomist', 'agric_extensionist']) || str_contains($title, 'field') || str_contains($title, 'agronomy')) {
            $programTitle = 'Field & Advisory Academy';
        }
        if (array_intersect($roles, ['state_coordinator', 'national_coordinator', 'admin', 'super_admin']) || str_contains($title, 'coordinator') || str_contains($title, 'admin') || str_contains($title, 'governance')) {
            $programTitle = 'Coordination & Governance Academy';
        }
        if (array_intersect($roles, ['investor'])) {
            $programTitle = 'Investor & Marketplace Buyer Academy';
        }
        if (!empty($programs[$programTitle]['id'])) {
            $update->execute([(int) $programs[$programTitle]['id'], (int) $course['id']]);
        }
    }
}
