<?php
declare(strict_types=1);

/**
 * CLI runner: seeds full Academy course content.
 *
 * Usage: php tools/seed-academy-content.php
 * Requires config.php bootstrap and CLI SAPI.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/academy/content-seeder.php';
require_once __DIR__ . '/../lib/academy/seeds/content-free-1.php';
require_once __DIR__ . '/../lib/academy/seeds/content-free-2.php';
require_once __DIR__ . '/../lib/academy/seeds/content-paid-1.php';
require_once __DIR__ . '/../lib/academy/seeds/content-paid-2.php';
require_once __DIR__ . '/../lib/academy/seeds/content-paid-3.php';

if (PHP_SAPI !== 'cli' && app_environment_is_production()) {
    http_response_code(403);
    exit('Forbidden: CLI only.');
}

/** @var PDO $pdo */
$pdo = db();

// Union operator (+) preserves the integer course-ID keys that
// array_merge would renumber, which previously cross-assigned
// course content to the wrong courses.
$free1 = (array) require __DIR__ . '/../lib/academy/seeds/content-free-1.php';
$free2 = (array) require __DIR__ . '/../lib/academy/seeds/content-free-2.php';
$paid1 = (array) require __DIR__ . '/../lib/academy/seeds/content-paid-1.php';
$paid2 = (array) require __DIR__ . '/../lib/academy/seeds/content-paid-2.php';
$paid3 = (array) require __DIR__ . '/../lib/academy/seeds/content-paid-3.php';
$content = $free1 + $free2 + $paid1 + $paid2 + $paid3;

// Expansion batches add deep-dive lessons to reach the
// 2,000-word floor (free, 10-15 min) and the 4,500-word
// target (paid, 20-30 min).
$exp1 = (array) require __DIR__ . '/../lib/academy/seeds/content-paid-extra-1.php';
$exp2 = (array) require __DIR__ . '/../lib/academy/seeds/content-paid-extra-2.php';
$exp3 = (array) require __DIR__ . '/../lib/academy/seeds/content-paid-extra-3.php';
$exp4 = (array) require __DIR__ . '/../lib/academy/seeds/content-paid-extra-4.php';
$exp5 = (array) require __DIR__ . '/../lib/academy/seeds/content-paid-extra-5.php';
$exp6 = (array) require __DIR__ . '/../lib/academy/seeds/content-paid-extra-6.php';
$exp7 = (array) require __DIR__ . '/../lib/academy/seeds/content-paid-extra-7.php';
$freeExp = (array) require __DIR__ . '/../lib/academy/seeds/content-free-extra.php';
$freeExp2 = (array) require __DIR__ . '/../lib/academy/seeds/content-free-extra-2.php';
$freeRich = (array) require __DIR__ . '/../lib/academy/seeds/content-richness-free.php';
$freeRich2 = (array) require __DIR__ . '/../lib/academy/seeds/content-richness-free-2.php';
$paidRich = (array) require __DIR__ . '/../lib/academy/seeds/content-richness-paid.php';

// Combine every expansion batch per course; the union
// operator (+) would drop later batches' lessons for
// courses that appear in more than one batch.
$expansion = [];
foreach ([$exp1, $exp2, $exp3, $exp4, $exp5, $exp6, $exp7, $freeExp, $freeExp2, $freeRich, $freeRich2, $paidRich] as $batch) {
    foreach ($batch as $batchCourseId => $extra) {
        $batchCourseId = (int) $batchCourseId;
        // Expansion files use either ['lessons' => [...]]
        // or a bare [lesson, lesson, ...] shape; handle both.
        $lessons = [];
        if (isset($extra['lessons']) && is_array($extra['lessons'])) {
            $lessons = $extra['lessons'];
        } elseif (is_array($extra)) {
            $lessons = $extra;
        }
        $expansion[$batchCourseId]['lessons'] = array_merge(
            $expansion[$batchCourseId]['lessons'] ?? [],
            $lessons
        );
    }
}
foreach ($expansion as $courseId => $extra) {
    $courseId = (int) $courseId;
    if (!isset($content[$courseId])) {
        continue;
    }
    $extraLessons = $extra['lessons'] ?? [];
    if ($extraLessons === []) {
        continue;
    }
    $content[$courseId]['lessons'] = array_merge($content[$courseId]['lessons'], $extraLessons);
    foreach ($extraLessons as $lesson) {
        $content[$courseId]['duration'] = ((int) ($content[$courseId]['duration'] ?? 0)) + ((int) ($lesson['duration'] ?? 0));
    }
}

// Remove assessments that an earlier broken seed run misassigned
// onto these free courses (they belong to paid courses).
$strays = [
    [16, 'Field Agent Verification and GPS Evidence Assessment'],
    [17, 'Agronomy Advisory Case Management Assessment'],
    [20, 'Marketplace Seller Central Professional Assessment'],
];
foreach ($strays as [$strayCourseId, $strayTitle]) {
    $strayStmt = $pdo->prepare("SELECT id FROM academy_assessments WHERE webinar_id = ? AND title = ?");
    $strayStmt->execute([(int) $strayCourseId, $strayTitle]);
    $strayId = (int) ($strayStmt->fetchColumn() ?: 0);
    if ($strayId > 0) {
        $pdo->prepare("DELETE FROM academy_questions WHERE assessment_id = ?")->execute([$strayId]);
        $pdo->prepare("DELETE FROM academy_assessments WHERE id = ?")->execute([$strayId]);
    }
}

academy_seed_course_content($pdo, $content);

// Clean up progress rows that point at lessons that are no
// longer active (a replaced curriculum), so a learner's
// completed count can never exceed the live lesson list.
$pdo->exec("
    DELETE ap FROM academy_progress ap
    LEFT JOIN academy_lessons l
      ON l.id = ap.lesson_id
     AND l.webinar_id = ap.webinar_id
     AND l.status = 'active'
    WHERE l.id IS NULL
");

require_once __DIR__ . '/../lib/academy/assessments.php';
$invariantResult = academy_ensure_quiz_question_invariants($pdo);

$courseCount = count($content);
$lessonCount = 0;
foreach ($content as $course) {
    $lessonCount += count($course['lessons']);
}

echo "Seeded {$courseCount} courses with {$lessonCount} lessons. Question invariants enforced across {$invariantResult['checked']} courses ({$invariantResult['updated']} updated).\n";
