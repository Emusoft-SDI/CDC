<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

$query = [];
if (isset($_GET['email'])) {
    $query['email'] = (string) $_GET['email'];
}
if (isset($_GET['next'])) {
    $query['next'] = (string) $_GET['next'];
}
$target = '../forgot-password.php' . ($query ? '?' . http_build_query($query) : '');
redirect_to($target);