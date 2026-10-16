<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$requestedCode = (int) ($_GET['code'] ?? 404);
$statusCode = in_array($requestedCode, [403, 404, 500, 503], true) ? $requestedCode : 404;

app_render_error_page($statusCode);
