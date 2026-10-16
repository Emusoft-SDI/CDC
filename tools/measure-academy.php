<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden: CLI only.');
}

$pdo = db();

$stmt = $pdo->prepare("
    SELECT w.id, w.title, w.is_free,
           (SELECT COALESCE(SUM(CHAR_LENGTH(l.content)), 0) FROM academy_lessons l WHERE l.webinar_id = w.id AND l.status = 'active') AS chars,
           (SELECT COUNT(*) FROM academy_lessons l WHERE l.webinar_id = w.id AND l.status = 'active') AS lessons
    FROM webinars w WHERE w.status = 'active' ORDER BY w.is_free, w.id
");
$stmt->execute();

$freeUnder = 0;
$paidUnder = 0;
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    // Precise word count: strip tags, count words.
    $words = (int) str_word_count(strip_tags((string) $row['chars']) === '' ? '' : '');
    // str_word_count needs the actual text; fetch content words via a second query.
    $wstmt = $pdo->prepare("SELECT content FROM academy_lessons WHERE webinar_id = ? AND status = 'active'");
    $wstmt->execute([(int) $row['id']]);
    $total = 0;
    foreach ($wstmt->fetchAll(PDO::FETCH_COLUMN) as $content) {
        $total += (int) str_word_count((string) $content);
    }
    $isFree = (int) $row['is_free'] === 1;
    $target = $isFree ? 2000 : 4000;
    $ok = $total >= $target;
    if (!$ok) {
        if ($isFree) {
            $freeUnder++;
        } else {
            $paidUnder++;
        }
    }
    printf("%-5d %-5s %-6d %-7d %-9s %s %s\n", (int) $row['id'], $isFree ? 'FREE' : 'PAID', $total, (int) $row['lessons'], number_format((int) $row['chars']), $ok ? 'OK' : 'UNDER', substr((string) $row['title'], 0, 40));
}
echo "\nFree under 2000: {$freeUnder} | Paid under 4000: {$paidUnder}\n";
