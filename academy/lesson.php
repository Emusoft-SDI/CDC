<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
$query = ['screen' => 'lesson'];
if (!empty($_GET['course_id'])) {
    $query['course_id'] = (int) $_GET['course_id'];
}
redirect_to('dashboard.php?' . http_build_query($query));
