<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';

$pdo = db();

json_response(['success' => false, 'error' => 'Settings updates are handled by admin/settings.php'], 410);
