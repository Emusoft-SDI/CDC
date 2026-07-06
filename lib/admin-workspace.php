<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/admin-layout.php';
require_once __DIR__ . '/admin-operator-strip.php';

/**
 * Resolve a workspace page from a fixed route map.
 *
 * Keeping this in one place prevents folder entry points from accepting
 * arbitrary include paths while legacy query-string links remain supported.
 */
function admin_workspace_page(array $routes, string $default = 'overview'): string
{
    $requested = strtolower(trim((string) ($_GET['page'] ?? $default)));
    return array_key_exists($requested, $routes) ? $requested : $default;
}

function admin_workspace_require_feature(PDO $pdo, string $feature): void
{
    admin_require($pdo, $feature);
}

function admin_workspace_legacy_redirect(string $workspace, ?string $page = null): never
{
    $target = rtrim($workspace, '/') . '/';
    if ($page !== null && $page !== '' && $page !== 'overview') {
        $target .= '?' . http_build_query(['page' => $page]);
    }
    header('Location: ' . $target, true, 302);
    exit;
}

function admin_workspace_render(string $controller, string $workspaceKey): void
{
    if (!is_file($controller)) {
        http_response_code(500);
        exit('Workspace controller is unavailable.');
    }

    ob_start();
    require $controller;
    $html = (string) ob_get_clean();
    if (stripos($html, '<head') === false) {
        echo $html;
        return;
    }

    if ($workspaceKey === 'support') {
        $assets = '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">'
            . '<link rel="stylesheet" href="../../assets/css/admin-workspaces.css">'
            . '<meta name="admin-workspace" content="' . e($workspaceKey) . '">';
    } else {
        $assets = '<base href="../">'
            . '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">'
            . '<link rel="stylesheet" href="../assets/css/admin-workspaces.css">'
            . '<meta name="admin-workspace" content="' . e($workspaceKey) . '">';
    }
    $html = preg_replace('/(<head[^>]*>)/i', '$1' . $assets, $html, 1) ?? $html;
    if ($workspaceKey === 'support') {
        $strip = admin_workspace_operator_strip(db(), ['asset_prefix' => '../../', 'profile_href' => '../profile.php', 'password_href' => '../profile.php#password', 'logout_action' => '../admin.php', 'title' => 'Support workspace', 'placeholder' => 'Search tickets, users, references...']);
        $html = preg_replace('/<body([^>]*)>/i', '<body$1>' . $strip, $html, 1) ?? $html;
    }
    $script = '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>';
    $html = preg_replace('/<\/body>/i', $script . '</body>', $html, 1) ?? $html;
    echo $html;
}
