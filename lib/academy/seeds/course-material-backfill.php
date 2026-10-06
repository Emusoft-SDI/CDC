<?php
declare(strict_types=1);

/**
 * Academy course-material backfill.
 *
 * Every Academy course must ship the full required set of learning material:
 *
 *   lessons    - "Course PDF Handout" (document), "One-Minute Video Brief" (video),
 *                "Assessment Readiness" (lms)
 *   materials  - "<course> PDF Handout" (pdf), "<course> One-Minute Video Brief" (video)
 *   files      - a downloadable PDF handout and a browser-playable 60-second video brief
 *   assessment - at least one active assessment with at least one active question
 *
 * Courses created before the starter programme seeder existed only had an assessment
 * and no lessons/material. This module re-standardises every course to one template
 * and is safe to run repeatedly.
 */

require_once __DIR__ . '/programs.php';

function academy_material_backfill_version(): string
{
    return '20261005-1';
}

/**
 * Build the material profile used to write the handout, the video brief and the
 * assessment-readiness lesson. Courses that match a curated starter programme keep
 * their rich definition; everything else gets a sensible profile derived from the row.
 */
function academy_course_material_profile(array $course): array
{
    $title = trim((string) ($course['title'] ?? 'Academy Course'));
    foreach (academy_starter_programs() as $starter) {
        if ((string) ($starter['title'] ?? '') === $title) {
            return $starter;
        }
    }
    foreach (academy_known_program_course_blueprints() as $blueprint) {
        if ((string) ($blueprint['title'] ?? '') === $title) {
            return $blueprint + ['roles' => (string) ($course['target_roles'] ?? 'all')];
        }
    }

    $description = trim((string) ($course['description'] ?? ''));
    if ($description === '') {
        $description = 'Practical NATCODEV Academy course covering ' . $title . '.';
    }
    $category = trim((string) ($course['category'] ?? 'Academy'));
    if ($category === '') {
        $category = 'Academy';
    }

    $objectives = [
        'Understand the purpose, scope and expected outcomes of ' . $title . '.',
        'Apply the ' . $category . ' workflow steps required by NATCODEV standards.',
        'Keep accurate records, meet compliance checks and escalate issues correctly.',
    ];

    $checklist = [];
    foreach (preg_split('/[.;]+/', $description) ?: [] as $sentence) {
        $sentence = trim($sentence);
        if ($sentence !== '') {
            $checklist[] = $sentence . '.';
        }
        if (count($checklist) >= 3) {
            break;
        }
    }
    $checklist[] = 'Read the course handout before attempting the assessment.';
    $checklist[] = 'Complete every required lesson in order.';
    $checklist[] = 'Pass the course assessment with the required score.';

    return [
        'program' => (string) ($course['program_title'] ?? ''),
        'title' => $title,
        'code' => (string) ($course['course_code'] ?? ''),
        'description' => $description,
        'roles' => (string) ($course['target_roles'] ?? 'all'),
        'pass_score' => (float) ($course['pass_score'] ?? 70),
        'certification_required' => (int) ($course['certification_required'] ?? 0),
        'objectives' => $objectives,
        'checklist' => array_values($checklist),
    ];
}

/**
 * Build a question bank from a profile so a course with no assessment still gets one.
 */
function academy_profile_questions(array $profile, int $limit = 10): array
{
    $statements = array_values(array_merge(
        array_slice((array) ($profile['objectives'] ?? []), 0, 4),
        array_slice((array) ($profile['checklist'] ?? []), 0, 6)
    ));
    $statements = array_values(array_unique(array_filter(
        array_map('trim', $statements),
        static fn (string $value): bool => $value !== ''
    )));
    if (!$statements) {
        $statements = ['Complete the required course material before the assessment.'];
    }

    $distractors = [
        'Skip the requirement and continue.',
        'Ignore the documented process.',
        'Bypass the approved NATCODEV workflow.',
    ];
    $letters = ['A', 'B', 'C', 'D'];
    $questions = [];

    foreach ($statements as $statement) {
        if (count($questions) >= $limit) {
            break;
        }
        $options = $distractors;
        $correctIndex = count($questions) % 4;
        array_splice($options, $correctIndex, 0, [$statement]);
        $options = array_slice($options, 0, 4);
        $questions[] = [
            'question' => 'Which statement matches the required ' . (string) ($profile['title'] ?? 'course') . ' practice? (' . (count($questions) + 1) . ')',
            'a' => $options[0] ?? '',
            'b' => $options[1] ?? '',
            'c' => $options[2] ?? '',
            'd' => $options[3] ?? '',
            'correct' => $letters[$correctIndex] ?? 'A',
        ];
    }

    return $questions;
}

/**
 * Curated starter-course blueprints keyed by programme title.
 *
 * @return array<string,array<string,mixed>>
 */
function academy_known_program_course_blueprints(): array
{
    return [
        'Input & Service Provider Academy' => [
            'title' => 'Input & Service Provider Fundamentals',
            'code' => 'NAT-INPUTPROV-001',
            'category' => 'Provider',
            'description' => 'Practical readiness course for input and service providers: accreditation evidence, coverage areas, product and service standards, pricing conduct, order handling, delivery discipline and marketplace compliance.',
            'objectives' => [
                'Prepare complete accreditation and compliance evidence for review.',
                'Publish accurate products and services with clear coverage and pricing.',
                'Handle orders, delivery promises and buyer communication to NATCODEV standards.',
            ],
            'checklist' => [
                'Confirm your provider profile, coverage states/LGAs and contact details.',
                'Upload the required compliance and accreditation documents.',
                'List products or services with honest specification, quantity and price.',
                'Respond to buyer orders and escalations through the official workflow.',
                'Keep settlement, delivery and dispute records up to date.',
            ],
            'assessment_title' => 'Input & Service Provider Readiness Assessment',
            'questions' => [
                ['question' => 'What must a provider keep complete and current?', 'a' => 'Accreditation, coverage and compliance evidence', 'b' => 'Only a phone number', 'c' => 'A private password', 'd' => 'Nothing', 'correct' => 'A'],
                ['question' => 'What should a provider confirm before listing a product or service?', 'a' => 'Specification, quantity, price and coverage', 'b' => 'A guess at the quality', 'c' => 'A hidden location', 'd' => 'A copied competitor listing', 'correct' => 'A'],
                ['question' => 'How should providers price their offerings?', 'a' => 'Honestly and consistently with the published price', 'b' => 'Randomly for each buyer', 'c' => 'With hidden extra charges', 'd' => 'Above the agreed contract value', 'correct' => 'A'],
                ['question' => 'What should happen when an order is received?', 'a' => 'Acknowledge and fulfil it through the official workflow', 'b' => 'Ignore the order', 'c' => 'Delete the order record', 'd' => 'Ask the buyer to pay off-platform', 'correct' => 'A'],
                ['question' => 'Which evidence is required for accreditation review?', 'a' => 'Valid compliance and accreditation documents', 'b' => 'A blank page', 'c' => 'Another person\'s identity card', 'd' => 'No document at all', 'correct' => 'A'],
                ['question' => 'What should support a completed delivery?', 'a' => 'Delivery and settlement records', 'b' => 'A verbal promise only', 'c' => 'An unsigned note', 'd' => 'Nothing', 'correct' => 'A'],
                ['question' => 'How are marketplace disputes handled?', 'a' => 'Through the official support workflow with evidence', 'b' => 'By abusing the buyer', 'c' => 'By deleting the records', 'd' => 'By ignoring the buyer', 'correct' => 'A'],
                ['question' => 'Why must coverage states and LGAs be accurate?', 'a' => 'So buyers only find services they can actually receive', 'b' => 'To hide delivery limits', 'c' => 'To avoid compliance', 'd' => 'It is optional', 'correct' => 'A'],
                ['question' => 'What happens to unverified or non-compliant providers?', 'a' => 'They may be rejected or suspended from the marketplace', 'b' => 'They are approved automatically', 'c' => 'They receive a bonus', 'd' => 'Nothing changes', 'correct' => 'A'],
                ['question' => 'What keeps a provider profile trustworthy?', 'a' => 'Verified details, honest listings and reliable fulfilment', 'b' => 'Fake reviews', 'c' => 'Hidden fees', 'd' => 'Unclear contact details', 'correct' => 'A'],
            ],
        ],
    ];
}

/**
 * Blueprint for the starter course of a programme that has no course yet.
 */
function academy_program_course_blueprint(array $program): array
{
    $programTitle = trim((string) ($program['title'] ?? 'Academy Programme'));
    $roles = trim((string) ($program['audience_roles'] ?? 'all')) ?: 'all';

    $known = academy_known_program_course_blueprints();
    if (isset($known[$programTitle])) {
        return $known[$programTitle] + ['roles' => $roles];
    }

    return [
        'title' => $programTitle . ' Essentials',
        'code' => 'NAT-ACAD-' . strtoupper(substr(sha1($programTitle), 0, 6)),
        'category' => 'Academy',
        'description' => 'Practical NATCODEV Academy foundation course for the ' . $programTitle . '.',
        'roles' => $roles,
        'objectives' => [
            'Understand the purpose and outcomes of the ' . $programTitle . '.',
            'Apply the required NATCODEV workflow and compliance steps.',
            'Complete records, assessments and certification requirements.',
        ],
        'checklist' => [
            'Read the course handout.',
            'Complete every required lesson in order.',
            'Pass the course assessment with the required score.',
        ],
    ];
}

/**
 * Create one starter course for any active programme that has no course yet.
 *
 * @return array<int,string> created course titles keyed by programme id
 */
function academy_backfill_create_missing_program_courses(PDO $pdo, bool $apply): array
{
    $created = [];
    $programs = $pdo->query("SELECT id, title, audience_roles FROM academy_programs WHERE COALESCE(status, 'active') = 'active' ORDER BY sort_order ASC, id ASC")->fetchAll();
    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM webinars WHERE program_id = ?');
    $existsStmt = $pdo->prepare('SELECT id FROM webinars WHERE title = ? LIMIT 1');

    foreach ($programs as $program) {
        $countStmt->execute([(int) $program['id']]);
        if ((int) $countStmt->fetchColumn() > 0) {
            continue;
        }

        $blueprint = academy_program_course_blueprint($program);
        $existsStmt->execute([(string) $blueprint['title']]);
        if ($existsStmt->fetchColumn() !== false) {
            continue;
        }

        $created[(int) $program['id']] = (string) $blueprint['title'];
        if (!$apply) {
            continue;
        }

        $stmt = $pdo->prepare("
            INSERT INTO webinars
                (program_id, course_code, course_type, title, description, start_time, duration_minutes, is_free, price,
                 delivery_type, delivery_url, zoom_link, delivery_instructions, max_attendees, category, target_roles,
                 certification_required, prerequisites, pass_score, certificate_approval_required, instructor_name, status)
            VALUES (?, ?, 'certification', ?, ?, ?, 90, 1, 0.00, 'mixed', NULL, NULL,
                    'Self-paced course. Start with the PDF handout, watch the 1-minute video brief, then complete the quiz/exam.',
                    1000, ?, ?, 1, 'No prerequisite. Open to all registered stakeholders.', 70.00, 0, 'NATCODEV Academy Faculty', 'active')
        ");
        $stmt->execute([
            (int) $program['id'],
            (string) $blueprint['code'],
            (string) $blueprint['title'],
            (string) $blueprint['description'],
            date('Y-m-d H:i:s', strtotime('+7 days')),
            (string) ($blueprint['category'] ?? 'Academy'),
            (string) ($blueprint['roles'] ?? 'all'),
        ]);
    }

    return $created;
}

/**
 * Standardise a single course to the Academy material template.
 *
 * @return array<string,int> per-course counters
 */
function academy_backfill_one_course(PDO $pdo, array $course, bool $apply, bool $overwrite): array
{
    $stats = [
        'lessons_created' => 0,
        'lessons_updated' => 0,
        'materials_created' => 0,
        'materials_updated' => 0,
        'assessments_created' => 0,
        'questions_created' => 0,
        'files_written' => 0,
        'courses_updated' => 0,
    ];

    $courseId = (int) $course['id'];
    $profile = academy_course_material_profile($course);
    $slug = academy_slug((string) $course['title']);
    $pdfPath = "academy_uploads/materials/{$slug}-course-handout.pdf";
    $videoPath = "academy_uploads/videos/{$slug}-one-minute-brief.html";

    if ($apply) {
        academy_write_course_pdf($pdfPath, $profile, $overwrite);
        academy_write_video_brief($videoPath, $profile, $overwrite);
    }
    $stats['files_written'] = (is_file(academy_material_path($pdfPath)) ? 1 : 0)
        + (is_file(academy_material_path($videoPath)) ? 1 : 0);

    $instructions = 'Self-paced course. Start with the PDF handout, watch the 1-minute video brief, then complete the quiz/exam. '
        . 'Certificate eligibility requires lesson completion and a passing assessment score.';

    if ($apply) {
        $stmt = $pdo->prepare("
            UPDATE webinars
            SET delivery_type = 'mixed', delivery_url = ?, zoom_link = ?, delivery_instructions = ?
            WHERE id = ?
        ");
        $stmt->execute(['../' . $videoPath, '../' . $videoPath, $instructions, $courseId]);
        $stats['courses_updated'] = $stmt->rowCount() > 0 ? 1 : 0;
    }

    $lessons = [
        ['Course PDF Handout', 'Short practical PDF course material for offline reading.', 'Read the handout and note the readiness checklist before attempting the assessment.', 'document', '../' . $pdfPath, 20, 10],
        ['One-Minute Video Brief', 'Timed visual introduction to the course workflow.', 'Watch the 60-second brief to understand the most important actions expected from this profile.', 'video', '../' . $videoPath, 1, 20],
        ['Assessment Readiness', 'Review the key operating rules before the quiz/exam.', implode("\n", (array) $profile['checklist']), 'lms', '../academy/index.php?screen=learning', 10, 30],
    ];

    $lessonExists = $pdo->prepare('SELECT id FROM academy_lessons WHERE webinar_id = ? AND title = ? LIMIT 1');
    $lessonUpsert = $pdo->prepare("
        INSERT INTO academy_lessons
            (webinar_id, title, summary, content, delivery_type, material_url, duration_minutes, sort_order, is_required, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 'active')
        ON DUPLICATE KEY UPDATE
            summary = VALUES(summary),
            content = VALUES(content),
            delivery_type = VALUES(delivery_type),
            material_url = VALUES(material_url),
            duration_minutes = VALUES(duration_minutes),
            sort_order = VALUES(sort_order),
            is_required = 1,
            status = 'active'
    ");
    foreach ($lessons as $lesson) {
        $lessonExists->execute([$courseId, (string) $lesson[0]]);
        $exists = $lessonExists->fetchColumn() !== false;
        $stats[$exists ? 'lessons_updated' : 'lessons_created']++;
        if ($apply) {
            $lessonUpsert->execute(array_merge([$courseId], $lesson));
        }
    }

    $materials = [
        [(string) $course['title'] . ' PDF Handout', 'pdf', '../' . $pdfPath, $pdfPath, 'Short downloadable Academy course PDF.', 10],
        [(string) $course['title'] . ' One-Minute Video Brief', 'video', '../' . $videoPath, $videoPath, 'Browser-playable 60-second Academy video brief.', 20],
    ];
    $materialExists = $pdo->prepare('SELECT id FROM academy_materials WHERE webinar_id = ? AND title = ? LIMIT 1');
    $materialUpsert = $pdo->prepare("
        INSERT INTO academy_materials
            (webinar_id, lesson_id, title, material_type, material_url, file_path, notes, sort_order, status)
        VALUES (?, NULL, ?, ?, ?, ?, ?, ?, 'active')
        ON DUPLICATE KEY UPDATE
            material_type = VALUES(material_type),
            material_url = VALUES(material_url),
            file_path = VALUES(file_path),
            notes = VALUES(notes),
            sort_order = VALUES(sort_order),
            status = 'active'
    ");
    foreach ($materials as $material) {
        $materialExists->execute([$courseId, (string) $material[0]]);
        $exists = $materialExists->fetchColumn() !== false;
        $stats[$exists ? 'materials_updated' : 'materials_created']++;
        if ($apply) {
            $materialUpsert->execute(array_merge([$courseId], $material));
        }
    }

    $assessmentStmt = $pdo->prepare("SELECT id FROM academy_assessments WHERE webinar_id = ? AND status = 'active' ORDER BY id ASC LIMIT 1");
    $assessmentStmt->execute([$courseId]);
    $assessmentId = (int) ($assessmentStmt->fetchColumn() ?: 0);
    $questionCountStmt = $pdo->prepare('SELECT COUNT(*) FROM academy_questions WHERE assessment_id = ? AND status = ?');
    $questionInsert = $pdo->prepare("
        INSERT INTO academy_questions
            (assessment_id, question_text, option_a, option_b, option_c, option_d, correct_option, points, sort_order, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, 'active')
        ON DUPLICATE KEY UPDATE
            option_a = VALUES(option_a), option_b = VALUES(option_b),
            option_c = VALUES(option_c), option_d = VALUES(option_d),
            correct_option = VALUES(correct_option), sort_order = VALUES(sort_order), status = 'active'
    ");

    $profileQuestions = array_values((array) ($profile['questions'] ?? []));
    $needsAssessment = $assessmentId <= 0;
    $existingQuestions = 0;
    if (!$needsAssessment) {
        $questionCountStmt->execute([$assessmentId, 'active']);
        $existingQuestions = (int) $questionCountStmt->fetchColumn();
    }
    // Top up when there is no question yet, or when a curated bank exists and the
    // course still holds fewer questions than that bank.
    $needsQuestions = !$needsAssessment
        && ($existingQuestions === 0 || ($profileQuestions && $existingQuestions < count($profileQuestions)));

    if ($needsAssessment || $needsQuestions) {
        $questions = $profileQuestions ?: academy_profile_questions($profile);
        $stats['questions_created'] += count($questions);

        if ($needsAssessment) {
            $stats['assessments_created']++;
            if ($apply) {
                $stmt = $pdo->prepare("INSERT INTO academy_assessments (webinar_id, title, instructions, pass_score, max_attempts, status) VALUES (?, ?, ?, ?, 3, 'active')");
                $stmt->execute([
                    $courseId,
                    (string) ($profile['assessment_title'] ?? ($course['title'] . ' Assessment')),
                    'Answer all questions. The assessment checks practical readiness, compliance judgement, and workflow discipline.',
                    (float) ($profile['pass_score'] ?? $course['pass_score'] ?? 70),
                ]);
                $assessmentId = (int) $pdo->lastInsertId();
            }
        }

        if ($apply && $assessmentId > 0) {
            foreach ($questions as $index => $question) {
                $questionInsert->execute([
                    $assessmentId,
                    (string) $question['question'],
                    (string) $question['a'],
                    (string) $question['b'],
                    (string) $question['c'],
                    (string) $question['d'],
                    (string) $question['correct'],
                    ($index + 1) * 10,
                ]);
            }
        }
    }

    return $stats;
}

/**
 * Re-standardise every Academy course.
 *
 * @return array{courses:int,totals:array<string,int>,incomplete:array<int>,created_courses:array<int,string>}
 */
function academy_backfill_course_material(PDO $pdo, bool $apply = true, bool $overwrite = false, bool $createMissingCourses = false): array
{
    $createdCourses = $createMissingCourses
        ? academy_backfill_create_missing_program_courses($pdo, $apply)
        : [];

    $courses = $pdo->query("
        SELECT w.*, p.title AS program_title
        FROM webinars w
        LEFT JOIN academy_programs p ON p.id = w.program_id
        ORDER BY w.id ASC
    ")->fetchAll();

    $totals = [
        'courses' => 0,
        'lessons_created' => 0,
        'lessons_updated' => 0,
        'materials_created' => 0,
        'materials_updated' => 0,
        'assessments_created' => 0,
        'questions_created' => 0,
        'files_written' => 0,
        'courses_updated' => 0,
    ];
    $incomplete = [];

    foreach ($courses as $course) {
        $stats = academy_backfill_one_course($pdo, $course, $apply, $overwrite);
        foreach ($stats as $key => $value) {
            $totals[$key] = ($totals[$key] ?? 0) + $value;
        }
        $totals['courses']++;

        if (!$apply && ($stats['lessons_created'] > 0 || $stats['materials_created'] > 0 || $stats['files_written'] < 2)) {
            $incomplete[] = (int) $course['id'];
        }
    }

    return [
        'courses' => $totals['courses'],
        'totals' => $totals,
        'incomplete' => $incomplete,
        'created_courses' => $createdCourses,
    ];
}
