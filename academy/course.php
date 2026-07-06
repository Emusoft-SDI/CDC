<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
$query = ['screen' => 'course'];
if (!empty($_GET['course_id'])) {
    $query['course_id'] = (int) $_GET['course_id'];
}
redirect_to('index.php?' . http_build_query(['course_id' => $query['course_id'] ?? 0]) . '#catalog');
