<?php
declare(strict_types=1);

$page = preg_replace('/[^a-z-]/', '', (string) ($_GET['page'] ?? 'overview')) ?: 'overview';
header('Location: academy/?' . http_build_query(['page' => $page]), true, 302);
exit;
