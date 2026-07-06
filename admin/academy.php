<?php
declare(strict_types=1);

$page = str_replace('_', '-', preg_replace('/[^a-z_-]/', '', (string) ($_GET['page'] ?? $_GET['tab'] ?? 'overview')) ?: 'overview');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: academy/?' . http_build_query(['page' => $page]), true, 302);
    exit;
}

defined('ACADEMY_ADMIN_ROUTE_BASE') || define('ACADEMY_ADMIN_ROUTE_BASE', 'academy/index.php');
$_GET['page'] = str_replace('-', '_', $page);
require __DIR__ . '/academy/index.php';