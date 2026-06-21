<?php
require_once __DIR__ . '/../config.php';
$pdo = db();

$courses = ['Coconut Nursery Masterclass', 'Coconut GAP Fundamentals'];

echo "Checking for seeded courses...\n";

foreach ($courses as $title) {
    $stmt = $pdo->prepare("SELECT id, title FROM webinars WHERE title = ?");
    $stmt->execute([$title]);
    $course = $stmt->fetch();
    
    if ($course) {
        echo "FOUND: ID {$course['id']} - {$course['title']}\n";
    } else {
        echo "NOT FOUND: {$title}\n";
    }
}
