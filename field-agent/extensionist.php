<?php
declare(strict_types=1);
require_once __DIR__ . '/_field.php';

$pdo = fa_pdo();
$user = fa_require_workspace_role($pdo, 'extensionist');
fa_render_role_workspace($pdo, $user, 'extensionist');
