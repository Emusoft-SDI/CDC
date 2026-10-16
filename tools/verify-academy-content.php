<?php
declare(strict_types=1);

/**
 * CLI verification: word counts and completeness
 * for every seeded Academy course.
 */

require_once __DIR__ . '/../config.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden: CLI only.');
}

$pdo = db();

$stmt = $pdo->prepare("
    SELECT w.id, w.title, w.is_free, w.price, w.duration_minutes,
           (SELECT COUNT(*) FROM academy_lessons l WHERE l.webinar_id = w.id AND l.status = 'active') AS lessons,
           (SELECT COUNT(*) FROM academy_materials m WHERE m.webinar_id = w.id AND m.status = 'active') AS materials,
           (SELECT COUNT(*) FROM academy_assessments a WHERE a.webinar_id = w.id AND a.status = 'active') AS assessments,
           (SELECT COALESCE(SUM(CHAR_LENGTH(l.content)), 0) FROM academy_lessons l WHERE l.webinar_id = w.id AND l.status = 'active') AS content_chars
    FROM webinars w
    WHERE w.status = 'active'
    ORDER BY w.is_free ASC, w.id ASC
");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

printf("%-5s %-6s %-6s %-7s %-9s %-10s %-9s %-8s %-9s %s\n", 'ID', 'FREE', 'PRICE', 'LESSONS', 'MATERIALS', 'ASSESS', 'MINUTES', 'WORDS', 'WORDS/LESSON', 'TITLE');
echo str_repeat('-', 130) . "\n";

$freeUnder = 0;
$paidUnder = 0;
foreach ($rows as $row) {
    $words = (int) round((int) $row['content_chars'] / 6);
    $lessons = (int) $row['lessons'];
    $perLesson = $lessons > 0 ? (int) round($words / $lessons) : 0;

    $isFree = (int) $row['is_free'] === 1;
    $target = $isFree ? 2000 : 4000;
    $ok = $words >= $target;
    if (!$ok) {
        if ($isFree) {
            $freeUnder++;
        } else {
            $paidUnder++;
        }
    }

    printf(
        "%-5d %-6s %-6s %-7d %-9d %-10d %-9d %-8d %-10d %s %s\n",
        (int) $row['id'],
        $isFree ? 'FREE' : 'PAID',
        $row['price'],
        $lessons,
        (int) $row['materials'],
        (int) $row['assessments'],
        (int) $row['duration_minutes'],
        $words,
        $perLesson,
        substr((string) $row['title'], 0, 42),
        $ok ? '' : '  <-- BELOW TARGET (' . $target . ')'
    );
}

echo str_repeat('-', 130) . "\n";
echo "Free courses below 2000 words: {$freeUnder}\n";
echo "Paid courses below 4000 words: {$paidUnder}\n";
