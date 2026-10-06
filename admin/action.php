<?php
declare(strict_types=1);

/**
 * Generic admin action endpoint (Phase 1, additive).
 *
 * POST { action: "<registered.key>", _csrf: "..." } -> JSON result.
 * Existing page-specific endpoints are untouched; this only serves handlers that
 * opt in via admin_register_action().
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/admin-layout.php';
require_once __DIR__ . '/../lib/admin-kernel.php';
require_once __DIR__ . '/../lib/admin-capabilities.php';
require_once __DIR__ . '/../lib/admin-actions.php';

$pdo = db();
admin_boot($pdo, ['feature' => 'dashboard', 'json' => true]);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    admin_json(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

$csrf = (string) ($_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
if (!verify_csrf($csrf)) {
    admin_json(['ok' => false, 'error' => 'invalid_csrf'], 419);
}

$key = trim((string) ($_POST['action'] ?? ''));
if ($key === '') {
    admin_json(['ok' => false, 'error' => 'missing_action'], 400);
}

$result = admin_dispatch_action($pdo, $key, $_POST);
admin_json($result + ['request_id' => admin_request_id()], (int) ($result['status'] ?? 200));
