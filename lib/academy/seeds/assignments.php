<?php
declare(strict_types=1);

function academy_seed_assignment_packs(PDO $pdo): void
{
    if (!app_table_exists($pdo, 'webinars') || !app_table_exists($pdo, 'academy_assessments') || !app_table_exists($pdo, 'academy_questions')) {
        return;
    }

    $courses = $pdo->query("
        SELECT w.id, w.title, w.description, w.target_roles, w.category, w.pass_score, p.title program_title
        FROM webinars w
        LEFT JOIN academy_programs p ON p.id = w.program_id
        WHERE COALESCE(w.status, 'active') = 'active'
        ORDER BY w.id ASC
    ")->fetchAll();
    $assessmentInsert = $pdo->prepare("
        INSERT INTO academy_assessments (webinar_id, title, instructions, pass_score, max_attempts, status)
        VALUES (?, ?, ?, ?, 3, 'active')
        ON DUPLICATE KEY UPDATE instructions = VALUES(instructions), pass_score = VALUES(pass_score), max_attempts = VALUES(max_attempts), status = 'active'
    ");
    $assessmentLookup = $pdo->prepare("
        SELECT id
        FROM academy_assessments
        WHERE webinar_id = ? AND status = 'active'
        ORDER BY id ASC
        LIMIT 1
    ");
    $questionCount = $pdo->prepare("SELECT COUNT(*) FROM academy_questions WHERE assessment_id = ? AND status = 'active'");
    $questionInsert = $pdo->prepare("
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

    foreach ($courses as $course) {
        $courseId = (int) $course['id'];
        $category = (string) ($course['category'] ?: academy_inferred_course_category($course));
        $title = trim((string) ($course['title'] ?? 'Academy Course'));
        $assessmentLookup->execute([$courseId]);
        $assessmentId = (int) $assessmentLookup->fetchColumn();
        if ($assessmentId <= 0) {
            $assessmentInsert->execute([
                $courseId,
                $title . ' Assignment Pack',
                'Complete the assignment pack for this course. The questions are generated from the course category and must be passed before certificate eligibility.',
                max(70, (float) ($course['pass_score'] ?? 70)),
            ]);
            $assessmentLookup->execute([$courseId]);
            $assessmentId = (int) $assessmentLookup->fetchColumn();
        }
        if ($assessmentId <= 0) {
            continue;
        }

        $questionCount->execute([$assessmentId]);
        $existing = (int) $questionCount->fetchColumn();
        if ($existing >= 10) {
            continue;
        }

        $questions = academy_assignment_pack_questions($course, 10);
        $sort = 10;
        foreach ($questions as $question) {
            $questionInsert->execute([
                $assessmentId,
                $question['question'],
                $question['a'],
                $question['b'],
                $question['c'],
                $question['d'],
                $question['correct'],
                $sort,
            ]);
            $sort += 10;
        }
    }
}

function academy_assignment_pack_questions(array $course, int $limit = 10): array
{
    $title = trim((string) ($course['title'] ?? 'this course')) ?: 'this course';
    $category = trim((string) ($course['category'] ?? academy_inferred_course_category($course))) ?: 'Professional Skills';
    $roles = academy_role_labels((string) ($course['target_roles'] ?? 'all'));
    $templates = [
        ['question' => 'What is the main professional outcome expected from {course}?', 'a' => 'Apply the course skills accurately in the NATCODEV platform workflow', 'b' => 'Skip the course and request a certificate immediately', 'c' => 'Use another learner account for evidence', 'd' => 'Ignore assessment instructions', 'correct' => 'A'],
        ['question' => 'Which record best proves readiness in {category}?', 'a' => 'Completed lessons, submitted assessment, and traceable platform activity', 'b' => 'A blank profile', 'c' => 'A copied certificate screenshot', 'd' => 'An unrelated public comment', 'correct' => 'A'],
        ['question' => 'Who is the intended audience for {course}?', 'a' => '{roles}', 'b' => 'Only anonymous visitors', 'c' => 'Deleted users only', 'd' => 'No platform user', 'correct' => 'A'],
        ['question' => 'What should a learner do before requesting a certificate for {course}?', 'a' => 'Complete required lessons and pass the assessment', 'b' => 'Open the certificate page without studying', 'c' => 'Submit empty answers', 'd' => 'Use a different course result', 'correct' => 'A'],
        ['question' => 'Why are assessment attempts recorded for {course}?', 'a' => 'To make score, pass status, and certificate decisions auditable', 'b' => 'To hide failed submissions', 'c' => 'To replace course enrollment', 'd' => 'To grant admin access', 'correct' => 'A'],
        ['question' => 'Which action supports good practice in {category}?', 'a' => 'Use accurate data, follow workflow steps, and keep evidence clear', 'b' => 'Use false information', 'c' => 'Ignore all support guidance', 'd' => 'Delete progress records', 'correct' => 'A'],
        ['question' => 'If a learner fails the {course} assessment, what is the best next step?', 'a' => 'Review the course material and retake within allowed attempts', 'b' => 'Demand an issued certificate', 'c' => 'Change another user result', 'd' => 'Bypass the quiz page', 'correct' => 'A'],
        ['question' => 'What makes a {category} course useful inside NATCODEV?', 'a' => 'It connects learning to real platform tasks, records, and decisions', 'b' => 'It hides every practical workflow', 'c' => 'It removes accountability', 'd' => 'It avoids all data capture', 'correct' => 'A'],
        ['question' => 'Which support path should be used when {course} instructions are unclear?', 'a' => 'The logged-in Academy Help and Support channel', 'b' => 'A public unrelated complaint', 'c' => 'Another learner wallet', 'd' => 'The browser address bar only', 'correct' => 'A'],
        ['question' => 'What should the platform show after a valid {course} submission?', 'a' => 'Score, pass/fail status, and updated learning progress', 'b' => 'A blank page', 'c' => 'A different learner profile', 'd' => 'No attempt record', 'correct' => 'A'],
        ['question' => 'Why should questions for {course} remain tied to the database?', 'a' => 'So attempts, scores, and certificate eligibility use reliable records', 'b' => 'So answers disappear after refresh', 'c' => 'So every learner gets random certificates', 'd' => 'So course categories are ignored', 'correct' => 'A'],
        ['question' => 'What does successful completion of {course} demonstrate?', 'a' => 'Readiness to follow the relevant NATCODEV process responsibly', 'b' => 'Permission to skip all platform rules', 'c' => 'Ownership of every course', 'd' => 'Automatic admin approval', 'correct' => 'A'],
    ];

    $seed = abs((int) crc32((string) ($course['id'] ?? '') . '|' . $title . '|' . $category));
    usort($templates, static function (array $a, array $b) use ($seed): int {
        return (crc32($a['question'] . $seed) <=> crc32($b['question'] . $seed));
    });

    $questions = [];
    foreach (array_slice($templates, 0, $limit) as $template) {
        $questions[] = array_map(static function ($value) use ($title, $category, $roles) {
            return str_replace(['{course}', '{category}', '{roles}'], [$title, $category, $roles], (string) $value);
        }, $template);
    }
    return $questions;
}
