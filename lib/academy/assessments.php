<?php
declare(strict_types=1);

function academy_assessment_for_course(PDO $pdo, int $courseId): ?array
{
    academy_ensure_schema($pdo);
    $stmt = $pdo->prepare("SELECT * FROM academy_assessments WHERE webinar_id = ? AND status = 'active' ORDER BY id ASC LIMIT 1");
    $stmt->execute([$courseId]);
    $assessment = $stmt->fetch();
    return $assessment ?: null;
}
