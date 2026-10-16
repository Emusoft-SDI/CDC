<?php
declare(strict_types=1);

/**
 * One-time backfill: link each course's one-minute video brief to
 * every lesson that does not already have a video_url.
 *
 * Mirrors the behaviour now built into academy_seed_course_content,
 * but runs directly against the live database so the fix is visible
 * immediately without re-running the full seeder.
 *
 * Usage: php tools/backfill-lesson-videos.php
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/academy/seeds/programs.php';

/** @var PDO $pdo */
$pdo = db();

$root = dirname(__DIR__);

$courses = $pdo->query("SELECT id, title FROM webinars WHERE status = 'active' ORDER BY id")->fetchAll();
$coursesChecked = 0;
$coursesWithBrief = 0;
$lessonsLinked = 0;

foreach ($courses as $course) {
    $coursesChecked++;
    $courseId = (int) $course['id'];
    $title = (string) $course['title'];

    $slug = academy_slug($title);
    $rel = 'academy_uploads/videos/' . $slug . '-one-minute-brief.html';
    $abs = $root . '/' . $rel;

    if (!is_file($abs)) {
        continue;
    }

    $coursesWithBrief++;
    $videoUrl = '../' . $rel;

    $stmt = $pdo->prepare("
        UPDATE academy_lessons
        SET video_url = ?
        WHERE webinar_id = ? AND (video_url IS NULL OR video_url = '')
    ");
    $stmt->execute([$videoUrl, $courseId]);
    $count = (int) $stmt->rowCount();
    if ($count > 0) {
        $lessonsLinked += $count;
        echo "Course #{$courseId} [{$title}]: linked brief to {$count} lesson(s)\n";
    }
}

echo "\nBackfill complete.\n";
echo "  Courses checked:      {$coursesChecked}\n";
echo "  Courses with brief:   {$coursesWithBrief}\n";
echo "  Lessons linked:       {$lessonsLinked}\n";
