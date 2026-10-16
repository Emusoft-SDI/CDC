<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
$query = ['screen' => 'lesson'];
if (!empty($_GET['course_id'])) {
    $query['course_id'] = (int) $_GET['course_id'];
}
if (!empty($_GET['lesson_id'])) {
    $query['lesson_id'] = (int) $_GET['lesson_id'];
    if (empty($query['course_id'])) {
        try {
            $pdo = db();
            $stmt = $pdo->prepare("SELECT webinar_id FROM academy_lessons WHERE id = ? LIMIT 1");
            $stmt->execute([(int) $_GET['lesson_id']]);
            $foundCourseId = (int) ($stmt->fetchColumn() ?: 0);
            if ($foundCourseId > 0) {
                $query['course_id'] = $foundCourseId;
            }
        } catch (Throwable $e) {
        }
    }
}
redirect_to('dashboard.php?' . http_build_query($query));
