<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/academy.php';

$pdo = db();
$rows = $pdo->query("
    SELECT w.id, w.title, w.is_free,
           (SELECT COALESCE(SUM(CHAR_LENGTH(l.content)),0) FROM academy_lessons l WHERE l.webinar_id = w.id AND l.status='active') chars,
           (SELECT COUNT(*) FROM academy_lessons l WHERE l.webinar_id = w.id AND l.status='active') lessons
    FROM webinars w
    WHERE w.status='active' AND w.id IN (1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,134,136,137,138,147,148,149,150,151,152,203)
    ORDER BY w.is_free DESC, w.id ASC
")->fetchAll(PDO::FETCH_ASSOC);

printf("%-5s %-4s %-7s %-8s %-8s %s\n", 'id', 'free', 'words', 'lessons', 'target', 'title');
foreach ($rows as $r) {
    $words = (int) round((int) $r['chars'] / 6);
    $target = (int) $r['is_free'] === 1 ? '2000+' : '4000+';
    printf("%-5s %-4s %-7s %-8s %-8s %s\n", $r['id'], $r['is_free'] ? 'yes' : 'no', $words, $r['lessons'], $target, $r['title']);
}
