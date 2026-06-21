<?php
declare(strict_types=1);

require_once __DIR__ . '/_user_auth.php';
require_once __DIR__ . '/../config.php';

$tab = preg_replace('/[^a-z]/', '', (string) ($_GET['tab'] ?? 'catalog')) ?: 'catalog';
$screen = [
    'learn' => 'learning',
    'calendar' => 'learning',
    'catalog' => 'catalog',
    'certificates' => 'certificates',
    'payments' => 'transactions',
    'feedback' => 'settings',
][$tab] ?? 'catalog';

$query = ['screen' => $screen];
if (!empty($_GET['course_id'])) {
    $query['course_id'] = (int) $_GET['course_id'];
}
foreach (['message', 'registered', 'error'] as $key) {
    if (isset($_GET[$key])) {
        $query[$key] = (string) $_GET[$key];
    }
}

redirect_to('../academy/index.php?' . http_build_query($query));
