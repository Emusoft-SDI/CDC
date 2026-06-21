<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/admin-layout.php';

session_start();
$pdo = db();
admin_ensure_schema($pdo);
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
if (!verify_csrf(is_string($input['_csrf'] ?? null) ? (string) $input['_csrf'] : csrf_token_from_request())) {
    json_response(['success' => false, 'error' => 'Invalid request token'], 403);
}

$templateName = trim((string) ($input['template_name'] ?? ''));
if ($templateName === '') {
    json_response(['success' => false, 'error' => 'Template name required'], 422);
}

try {
    if (app_table_exists($pdo, 'notification_templates')) {
        if (admin_current_user_is_super_admin($pdo)) {
            $pdo->prepare("DELETE FROM notification_templates WHERE template_name = ?")->execute([$templateName]);
        } else {
            admin_queue_verified_delete_request($pdo, 'notification_templates', null, $templateName, 'Notification template delete requested by admin.', [
                'target_key' => $templateName,
                'template_name' => $templateName,
            ]);
            json_response(['success' => true, 'pending_approval' => true, 'message' => 'Delete request sent to Super Admin for approval.']);
        }
    }

    json_response(['success' => true]);
} catch (Throwable $e) {
    error_log('Delete template API error: ' . $e->getMessage());
    json_response(['success' => false, 'error' => 'Unable to delete template'], 500);
}
