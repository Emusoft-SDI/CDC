<?php
declare(strict_types=1);

/**
 * Standardise Academy courses so every one of them carries the full required set of
 * learning material:
 *
 *   - "Course PDF Handout", "One-Minute Video Brief" and "Assessment Readiness" lessons
 *   - downloadable PDF handout + browser-playable 60-second video brief
 *   - at least one active assessment with at least one active question
 *
 * Dry-run by default.
 *   --apply                   write rows and files
 *   --overwrite               regenerate existing PDF/HTML files
 *   --create-program-courses  also create a starter course for any programme
 *                             that currently has none
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/academy.php';
require_once __DIR__ . '/../lib/academy/seeds/course-material-backfill.php';

$args = $argv ?? [];
$apply = in_array('--apply', $args, true);
$overwrite = in_array('--overwrite', $args, true);
$createProgramCourses = in_array('--create-program-courses', $args, true);

$pdo = db();
academy_ensure_schema($pdo);

$out = static function (string $line): void {
    fwrite(STDOUT, $line . "\n");
};

$dbName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
$out('Academy course-material backfill');
$out('Database : ' . $dbName);
$out('Mode     : ' . ($apply ? 'APPLY (will write rows and files)' : 'DRY RUN (no changes)'));
$out(str_repeat('-', 78));

$rows = $pdo->query("
    SELECT w.id, w.title,
        (SELECT COUNT(*) FROM academy_lessons l WHERE l.webinar_id = w.id AND l.status = 'active') lessons,
        (SELECT COUNT(*) FROM academy_materials m WHERE m.webinar_id = w.id AND m.status = 'active') materials,
        (SELECT COUNT(*) FROM academy_assessments a WHERE a.webinar_id = w.id AND a.status = 'active') assessments,
        (SELECT COUNT(*) FROM academy_questions q WHERE q.assessment_id IN (SELECT id FROM academy_assessments a2 WHERE a2.webinar_id = w.id AND a2.status = 'active') AND q.status = 'active') questions
    FROM webinars w
    ORDER BY w.id ASC
")->fetchAll();

if ($rows) {
    printf("%-5s %-46s %-7s %-9s %-6s %-6s\n", 'ID', 'Course', 'Lessons', 'Material', 'Assess', 'Quest');
    foreach ($rows as $row) {
        printf(
            "%-5d %-46s %-7d %-9d %-6d %-6d %s\n",
            (int) $row['id'],
            substr((string) $row['title'], 0, 46),
            (int) $row['lessons'],
            (int) $row['materials'],
            (int) $row['assessments'],
            (int) $row['questions'],
            ((int) $row['lessons'] >= 3 && (int) $row['materials'] >= 2 && (int) $row['assessments'] >= 1 && (int) $row['questions'] >= 1) ? 'OK' : 'INCOMPLETE'
        );
    }
}

$result = academy_backfill_course_material($pdo, $apply, $overwrite, $createProgramCourses);
$totals = $result['totals'];

$out(str_repeat('-', 78));
if ($result['created_courses']) {
    foreach ($result['created_courses'] as $programId => $courseTitle) {
        $out('Programme #' . $programId . ($apply ? ' starter course created: ' : ' would get starter course: ') . $courseTitle);
    }
}
$out('Courses processed        : ' . (int) $totals['courses']);
$out('Courses updated          : ' . (int) $totals['courses_updated']);
$out('Lessons created/updated  : ' . (int) $totals['lessons_created'] . ' / ' . (int) $totals['lessons_updated']);
$out('Materials created/updated: ' . (int) $totals['materials_created'] . ' / ' . (int) $totals['materials_updated']);
$out('Assessments created      : ' . (int) $totals['assessments_created']);
$out('Questions created        : ' . (int) $totals['questions_created']);
$out('Generated files present  : ' . (int) $totals['files_written']);

if (!$apply) {
    $out('');
    $out('Dry run complete. Re-run with --apply to write the content.');
}
