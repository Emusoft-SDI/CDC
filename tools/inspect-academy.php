<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden: CLI only.');
}

$pdo = db();

foreach ([1, 6, 16, 134] as $courseId) {
    $stmt = $pdo->prepare("SELECT title, CHAR_LENGTH(content) AS chars FROM academy_lessons WHERE webinar_id = ? AND status = 'active' ORDER BY sort_order");
    $stmt->execute([$courseId]);
    $lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $webinar = $pdo->prepare("SELECT title, duration_minutes, CHAR_LENGTH(description) AS desc_chars FROM webinars WHERE id = ?");
    $webinar->execute([$courseId]);
    $w = $webinar->fetch(PDO::FETCH_ASSOC);

    echo "=== Course {$courseId}: " . ($w['title'] ?? '?') . " ({$w['duration_minutes']} min, desc {$w['desc_chars']} chars) ===\n";
    foreach ($lessons as $l) {
        echo "  - {$l['title']} ({$l['chars']} chars)\n";
    }
    echo "\n";
}
