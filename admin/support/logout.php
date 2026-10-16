<?php
declare(strict_types=1);

require_once __DIR__ . '/../_auth.php';
require_once __DIR__ . '/../../lib/admin-layout.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!verify_csrf((string) ($_GET['_csrf'] ?? ''))) {
    app_render_error_page(403, 'Invalid security token.');
}

admin_logout();
redirect_to('../login.php');