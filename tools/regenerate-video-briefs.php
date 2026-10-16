<?php
declare(strict_types=1);

/**
 * CLI runner: regenerates every Academy one-minute video brief
 * with the current media-player template inside academy_write_video_brief().
 *
 * Usage: php tools/regenerate-video-briefs.php
 * Requires config.php bootstrap and CLI SAPI.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/academy/seeds/programs.php';

if (PHP_SAPI !== 'cli' && app_is_production()) {
    http_response_code(403);
    exit('Forbidden: CLI only.');
}

/** @var PDO $pdo */
$pdo = db();

// Union operator (+) preserves the integer course-ID keys that
// array_merge would renumber (same as tools/seed-academy-content.php).
$free1 = (array) require __DIR__ . '/../lib/academy/seeds/content-free-1.php';
$free2 = (array) require __DIR__ . '/../lib/academy/seeds/content-free-2.php';
$paid1 = (array) require __DIR__ . '/../lib/academy/seeds/content-paid-1.php';
$paid2 = (array) require __DIR__ . '/../lib/academy/seeds/content-paid-2.php';
$paid3 = (array) require __DIR__ . '/../lib/academy/seeds/content-paid-3.php';
$content = $free1 + $free2 + $paid1 + $paid2 + $paid3;

// The content batches describe lessons, not the course itself, so the
// authoritative course title is read from the webinars row (this keeps
// the video-brief slug identical to the one the seeder ships).
$titleStmt = $pdo->prepare("SELECT title FROM webinars WHERE id = ? AND status = 'active' LIMIT 1");

$regenerated = 0;
foreach ($content as $courseId => $course) {
    $courseId = (int) $courseId;
    $title = (string) ($course['title'] ?? '');
    if ($title === '') {
        $titleStmt->execute([$courseId]);
        $title = (string) ($titleStmt->fetchColumn() ?: '');
    }
    if ($title === '') {
        echo "Skipped course {$courseId}: no active course title found.\n";
        continue;
    }
    $slug = academy_slug($title);
    $rel = 'academy_uploads/videos/' . $slug . '-one-minute-brief.html';
    academy_write_video_brief($rel, [
        'title' => $title,
        'objectives' => (array) ($course['objectives'] ?? []),
    ], true);
    $regenerated++;
    echo "Regenerated: {$title} -> {$rel}\n";
}

echo "Done: regenerated {$regenerated} one-minute video briefs.\n";
