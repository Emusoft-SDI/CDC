<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/academy.php';
require_once __DIR__ . '/../lib/academy/courses.php';

$pdo = db();
$courses = $pdo->query('SELECT id, title, is_free, price FROM webinars WHERE id BETWEEN 1 AND 8 ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);

foreach ($courses as $course) {
    $thumb = academy_course_thumbnail_src($course, '');
    $lStmt = $pdo->prepare('SELECT COUNT(*), COUNT(video_url), COUNT(audio_tracks), COUNT(infographic_url) FROM academy_lessons WHERE webinar_id = ?');
    $lStmt->execute([$course['id']]);
    $lStats = $lStmt->fetch(PDO::FETCH_NUM);

    $mStmt = $pdo->prepare('SELECT COUNT(*), COUNT(material_url) FROM academy_materials WHERE webinar_id = ?');
    $mStmt->execute([$course['id']]);
    $mStats = $mStmt->fetch(PDO::FETCH_NUM);

    printf(
        "Course #%d [%s] %-48s | Thumb: %-35s | Lessons: %d (vid:%d, aud:%d, inf:%d) | Materials: %d (urls:%d)\n",
        (int) $course['id'],
        (int) $course['is_free'] === 1 ? 'FREE' : 'PAID',
        substr((string) $course['title'], 0, 48),
        $thumb ?: 'NONE',
        (int) $lStats[0],
        (int) $lStats[1],
        (int) $lStats[2],
        (int) $lStats[3],
        (int) $mStats[0],
        (int) $mStats[1]
    );
}
