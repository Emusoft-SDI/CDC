<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
$query = ['screen' => 'checkout'];
if (!empty($_GET['course_id'])) {
    $query['course_id'] = (int) $_GET['course_id'];
}
redirect_to(!empty($query['course_id']) ? 'register.php?course_id=' . (int) $query['course_id'] : 'register.php');
