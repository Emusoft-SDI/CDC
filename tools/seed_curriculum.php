<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/academy.php';

$pdo = db();

$curriculum = [
    'Nursery Establishment' => [
        'title' => 'Coconut Nursery Masterclass',
        'lessons' => [
            'Seed Selection' => 'Criteria for selecting mature parent palms and seed nuts.',
            'Germination Techniques' => 'Optimal soil media and moisture control.',
            'Nursery Care' => 'Routine monitoring and disease prevention.'
        ],
        'assessment' => 'Nursery Best Practices Quiz'
    ],
    'Good Agricultural Practices' => [
        'title' => 'Coconut GAP Fundamentals',
        'lessons' => [
            'Land Preparation' => 'Clearing, pegging, and hole preparation.',
            'Planting' => 'Proper planting depth and root management.',
            'Mulching and Irrigation' => 'Maintaining moisture for young palms.'
        ],
        'assessment' => 'GAP Core Principles Quiz'
    ]
];

foreach ($curriculum as $programName => $data) {
    // 1. Create Program
    $stmt = $pdo->prepare("INSERT IGNORE INTO academy_programs (title, status) VALUES (?, 'active')");
    $stmt->execute([$programName]);
    $programId = $pdo->lastInsertId() ?: ($pdo->query("SELECT id FROM academy_programs WHERE title='$programName'")->fetchColumn() ?: 0);

    // 2. Create Course (Webinar)
    $stmt = $pdo->prepare("INSERT IGNORE INTO webinars (program_id, title, start_time, status) VALUES (?, ?, NOW(), 'active')");
    $stmt->execute([$programId, $data['title']]);
    $courseId = $pdo->lastInsertId() ?: ($pdo->query("SELECT id FROM webinars WHERE title='{$data['title']}'")->fetchColumn() ?: 0);

    // 3. Add Lessons
    foreach ($data['lessons'] as $title => $summary) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO academy_lessons (webinar_id, title, summary, status) VALUES (?, ?, ?, 'active')");
        $stmt->execute([$courseId, $title, $summary]);
    }

    // 4. Create Assessment
    $stmt = $pdo->prepare("INSERT IGNORE INTO academy_assessments (webinar_id, title, status) VALUES (?, ?, 'active')");
    $stmt->execute([$courseId, $data['assessment']]);
}

echo "Curriculum seeding completed.";
