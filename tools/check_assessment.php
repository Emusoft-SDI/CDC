<?php
require_once __DIR__ . '/../config.php';
$pdo = db();

$courseId = 137;
$stmt = $pdo->prepare("SELECT id, title FROM webinars WHERE id = ?");
$stmt->execute([$courseId]);
$course = $stmt->fetch();

if (!$course) {
    echo "Course ID $courseId not found.\n";
    exit;
}

echo "Found Course: " . $course['title'] . "\n";

$stmt = $pdo->prepare("SELECT id, title FROM academy_assessments WHERE webinar_id = ?");
$stmt->execute([$courseId]);
$assessment = $stmt->fetch();

if ($assessment) {
    echo "Found Assessment: " . $assessment['title'] . " (ID: " . $assessment['id'] . ")\n";
} else {
    echo "No assessment found for course $courseId. Please create one first.\n";
}
