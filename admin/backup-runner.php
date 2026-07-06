<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/admin-layout.php';
require_once __DIR__ . '/../lib/disaster-recovery.php';

$pdo = db();
admin_ensure_schema($pdo);
dr_ensure_schema($pdo);

$settings = dr_settings($pdo);
$expected = (string) ($settings['dr_auto_backup_token'] ?? '');
$authHeader = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
$provided = '';
if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
    $provided = trim((string) $matches[1]);
}
if ($provided === '') {
    $provided = trim((string) ($_SERVER['HTTP_X_NATCODEV_BACKUP_TOKEN'] ?? ''));
}
if (!app_is_production() && $provided === '') {
    $provided = trim((string) ($_GET['token'] ?? ''));
}
$force = !empty($_POST['force']) || (!app_is_production() && !empty($_GET['force']));

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'invalid_token']);
    exit;
}

try {
    $result = dr_run_integrity_backup($pdo, null, $force);
    echo json_encode(['ok' => true] + $result, JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('Backup runner failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'backup_failed']);
}