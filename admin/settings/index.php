<?php
declare(strict_types=1);
require_once __DIR__ . '/../../lib/admin-workspace.php';
$routes = array_fill_keys(['overview', 'modules', 'rbac', 'user-roles', 'stakeholder-interests', 'integrations', 'feature-flags', 'security', 'backups', 'maintenance', 'audit-log'], 'settings.php');
$page = admin_workspace_page($routes);
admin_workspace_require_feature(db(), 'settings');
$_GET['page'] = $page;
define('NATCODEV_SETTINGS_LEGACY', true);
admin_workspace_render(__DIR__ . '/../settings.php', 'settings');
