<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
$query = ['screen' => 'quiz'];
if (!empty($_GET['course_id'])) {
    $query['course_id'] = (int) $_GET['course_id'];
}
if (!empty($_GET['attempt_id'])) {
    $query['attempt_id'] = (int) $_GET['attempt_id'];
}
if (isset($_GET['retake'])) {
    $query['retake'] = (int) $_GET['retake'];
}
if (!empty($_GET['message'])) {
    $query['message'] = (string) $_GET['message'];
}
if (!empty($_GET['error'])) {
    $query['error'] = (string) $_GET['error'];
}
redirect_to('dashboard.php?' . http_build_query($query));
