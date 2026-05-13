<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

session_start();
$pdo = db();
require_user_role($pdo, ['grower', 'field_agent', 'admin']);

json_response(['success' => true, 'items' => []]);
