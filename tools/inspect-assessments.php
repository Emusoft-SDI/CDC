<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden: CLI only.');
}

$pdo = db();

$stmt = $pdo->prepare("
    SELECT a.webinar_id, a.title AS assessment_title, COUNT(q.id) AS questions
    FROM academy_assessments a
    LEFT JOIN academy_questions q ON q.assessment_id = a.id
    WHERE a.webinar_id IN (1, 16, 17, 20, 134, 147)
    GROUP BY a.id
    ORDER BY a.webinar_id
");
$stmt->execute();
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    printf("Course %-4d | %-55s | %d questions\n", (int) $row['webinar_id'], substr((string) $row['assessment_title'], 0, 55), (int) $row['questions']);
}
