<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/twilio.php';

function natcodev_render_template(string $template, array $variables): string
{
    foreach ($variables as $key => $value) {
        $template = str_replace('{' . $key . '}', (string) $value, $template);
    }
    return $template;
}

function natcodev_template(PDO $pdo, string $name, string $type, string $fallback): string
{
    if (!app_table_exists($pdo, 'notification_templates')) {
        return $fallback;
    }

    $stmt = $pdo->prepare("
        SELECT message_template
        FROM notification_templates
        WHERE template_name = ? AND template_type = ? AND is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$name, $type]);
    $template = $stmt->fetchColumn();

    return $template ? (string) $template : $fallback;
}

function natcodev_notify_user(PDO $pdo, int $userId, string $templateName, string $subject, array $variables = [], string $fallback = ''): void
{
    $stmt = $pdo->prepare("SELECT name, email, phone FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) {
        return;
    }

    $variables += [
        'name' => (string) ($user['name'] ?? ''),
        'login_url' => app_base_url() . '/login.php',
        'field_agent_url' => app_base_url() . '/field-agent/',
    ];

    $emailBody = natcodev_render_template(
        natcodev_template($pdo, $templateName, 'email', $fallback ?: $subject),
        $variables
    );
    $smsBody = natcodev_render_template(
        natcodev_template($pdo, $templateName, 'sms', $fallback ?: $subject),
        $variables
    );

    if (!empty($user['email'])) {
        app_send_mail((string) $user['email'], $subject, $emailBody);
    }
    if (!empty($user['phone'])) {
        sendSMSMessage((string) $user['phone'], $smsBody);
    }
}

function natcodev_notify_admins(PDO $pdo, string $subject, string $body): void
{
    $admins = $pdo->query("SELECT email FROM users WHERE role = 'admin' AND email <> ''")->fetchAll();
    foreach ($admins as $admin) {
        app_send_mail((string) $admin['email'], $subject, $body);
    }
}
