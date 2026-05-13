<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

session_start();
$pdo = db();
if (!admin_session_is_authenticated($pdo)) {
    json_response(['success' => false, 'error' => 'Forbidden'], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'POST method required'], 405);
}

$input = json_decode(file_get_contents('php://input') ?: '{}', true);
if (!is_array($input)) {
    json_response(['success' => false, 'error' => 'Invalid JSON'], 400);
}

$templateName = trim((string) ($input['template_name'] ?? ''));
if ($templateName === '') {
    json_response(['success' => false, 'error' => 'Template name required'], 422);
}

try {
    if (app_table_exists($pdo, 'notification_templates')) {
        $pdo->prepare("DELETE FROM notification_templates WHERE template_name = ?")->execute([$templateName]);
    }

    json_response(['success' => true]);
} catch (Throwable $e) {
    error_log('Delete template API error: ' . $e->getMessage());
    json_response(['success' => false, 'error' => 'Unable to delete template'], 500);
}
