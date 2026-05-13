<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

session_start();
$pdo = db();
if (!admin_session_is_authenticated($pdo)) {
    json_response(['success' => false, 'error' => 'Forbidden'], 403);
}

$templateName = trim((string) ($_GET['name'] ?? ''));
if ($templateName === '') {
    json_response(['success' => false, 'error' => 'Template name required'], 422);
}

try {
    if (!app_table_exists($pdo, 'notification_templates')) {
        json_response(['success' => true]);
    }

    $stmt = $pdo->prepare("
        SELECT template_type, message_template, is_active
        FROM notification_templates
        WHERE template_name = ?
    ");
    $stmt->execute([$templateName]);

    $result = ['success' => true];
    foreach ($stmt->fetchAll() as $template) {
        $result[$template['template_type']] = $template;
    }

    json_response($result);
} catch (Throwable $e) {
    error_log('Get template API error: ' . $e->getMessage());
    json_response(['success' => false, 'error' => 'Unable to load template'], 500);
}
