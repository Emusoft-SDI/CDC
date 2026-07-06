<?php
declare(strict_types=1);
require_once __DIR__ . '/../../lib/admin-workspace.php';
$routes = array_fill_keys(['overview', 'stakeholder-interests', 'report-templates', 'exceptions', 'exports', 'data-sources', 'user-permissions'], 'reports.php');
$page = admin_workspace_page($routes);
admin_workspace_require_feature(db(), 'reports');
$_GET['page'] = $page;
define('NATCODEV_REPORTS_LEGACY', true);
admin_workspace_render(__DIR__ . '/../reports.php', 'reports');
