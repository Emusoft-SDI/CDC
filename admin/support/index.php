<?php
declare(strict_types=1);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && !empty($_GET['ticket']) && (isset($_GET['page']) || isset($_GET['view']) || isset($_GET['per_page']))) {
    $ticketRef = preg_replace('/[^A-Z0-9-]/i', '', (string) $_GET['ticket']);
    header('Location: index.php?' . http_build_query(['ticket' => $ticketRef]), true, 302);
    exit;
}

require_once __DIR__ . '/../../lib/admin-workspace.php';
$routes = array_fill_keys(['overview', 'tickets', 'assigned', 'escalations', 'knowledge', 'teams', 'messages', 'complaints', 'field', 'sla', 'settings', 'public-entry'], 'support.php');
if (isset($_GET['page']) && !ctype_digit((string) $_GET['page']) && empty($_GET['view'])) {
    $_GET['view'] = (string) $_GET['page'];
    unset($_GET['page']);
}
$view = strtolower(trim((string) ($_GET['view'] ?? 'overview')));
if (!array_key_exists($view, $routes)) {
    $view = 'overview';
}
admin_workspace_require_feature(db(), 'support');
if (function_exists('admin_current_platform_role') && admin_current_platform_role(db()) === 'support_agent') {
    header('Location: ../../support/agent.php', true, 302);
    exit;
}
$_GET['view'] = $view;
define('NATCODEV_SUPPORT_WORKSPACE', true);
admin_workspace_render(__DIR__ . '/../support.php', 'support');