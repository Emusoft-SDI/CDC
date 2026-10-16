<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden: CLI only.');
}

$pdo = db();

foreach ([1, 134] as $courseId) {
    $stmt = $pdo->prepare("SELECT id, title FROM academy_assessments WHERE webinar_id = ? LIMIT 1");
    $stmt->execute([$courseId]);
    $assessment = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$assessment) {
        continue;
    }
    echo "=== Course {$courseId}: {$assessment['title']} ===\n";
    $q = $pdo->prepare("SELECT question_text, option_a, option_b, correct_option FROM academy_questions WHERE assessment_id = ? ORDER BY sort_order LIMIT 4");
    $q->execute([(int) $assessment['id']]);
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
        echo "  Q: " . substr((string) $row['question_text'], 0, 90) . "\n";
        echo "     A: " . substr((string) $row['option_a'], 0, 60) . "\n";
        echo "     B: " . substr((string) $row['option_b'], 0, 60) . "  [correct: {$row['correct_option']}]\n";
    }
    echo "\n";
}
