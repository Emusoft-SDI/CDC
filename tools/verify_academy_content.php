<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

/** @var PDO $pdo */
$pdo = db();

$stmt = $pdo->query("
    SELECT w.id, w.title, w.is_free, w.price,
           (SELECT COUNT(*) FROM academy_lessons l WHERE l.webinar_id = w.id AND l.status = 'active') AS lessons,
           (SELECT COALESCE(SUM(CHAR_LENGTH(l.content)),0) FROM academy_lessons l WHERE l.webinar_id = w.id AND l.status = 'active') AS content_chars,
           (SELECT COUNT(*) FROM academy_materials m WHERE m.webinar_id = w.id AND m.status = 'active') AS materials,
           (SELECT COUNT(*) FROM academy_assessments a WHERE a.webinar_id = w.id AND a.status = 'active') AS assessments
    FROM webinars w
    WHERE w.status = 'active'
    ORDER BY w.is_free ASC, w.price DESC, w.id ASC
");

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$freeBelow = 0;
$paidBelow = 0;

foreach ($rows as $row) {
    $words = (int) (str_word_count(strip_tags((string) $row['content_chars'])) ?: 0);
    // str_word_count on a numeric string won't work; compute words from content directly.
    $contentStmt = $pdo->prepare("SELECT content FROM academy_lessons WHERE webinar_id = ? AND status = 'active'");
    $contentStmt->execute([(int) $row['id']]);
    $allContent = '';
    foreach ($contentStmt->fetchAll(PDO::FETCH_COLUMN) as $c) {
        $allContent .= ' ' . (string) $c;
    }
    $words = (int) str_word_count(strip_tags($allContent));

    $isFree = ((int) $row['is_free']) === 1;
    $target = $isFree ? 2000 : 4000;
    $ok = $words >= $target;
    if ($isFree && !$ok) { $freeBelow++; }
    if (!$isFree && !$ok) { $paidBelow++; }

    $flag = $ok ? 'OK ' : 'LOW';
    $kind = $isFree ? 'FREE' : 'PAID';
    printf(
        "[%s] #%-4d %-6s %-52s lessons=%-3d words=%-5d target=%-5d materials=%d assessments=%d\n",
        $flag,
        (int) $row['id'],
        $kind,
        substr((string) $row['title'], 0, 52),
        (int) $row['lessons'],
        $words,
        $target,
        (int) $row['materials'],
        (int) $row['assessments']
    );
}

echo "\nFree courses below 2000 words: {$freeBelow}\n";
echo "Paid courses below 4000 words: {$paidBelow}\n";
