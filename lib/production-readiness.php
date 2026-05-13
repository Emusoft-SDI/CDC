<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/platform-governance.php';
require_once __DIR__ . '/disaster-recovery.php';
require_once __DIR__ . '/twilio.php';

function pr_mask(?string $value): string
{
    $value = (string) $value;
    if ($value === '') {
        return 'missing';
    }
    return strlen($value) <= 6 ? str_repeat('*', strlen($value)) : substr($value, 0, 3) . str_repeat('*', max(3, strlen($value) - 6)) . substr($value, -3);
}

function pr_status(bool $ok, string $label, string $detail = '', string $severity = 'required'): array
{
    return ['ok' => $ok, 'label' => $label, 'detail' => $detail, 'severity' => $severity];
}

function pr_setting(PDO $pdo, string $key): string
{
    return function_exists('admin_setting') ? admin_setting($pdo, $key, '') : '';
}

function pr_run_checks(PDO $pdo): array
{
    pg_ensure_schema($pdo);
    dr_ensure_schema($pdo);

    $checks = [];
    $appEnv = strtolower((string) app_env('APP_ENV', 'production'));
    $baseUrl = app_base_url();
    $mailTransport = strtolower((string) app_env('MAIL_TRANSPORT', app_is_production() ? 'mail' : 'log'));
    $smsTransport = strtolower((string) app_env('SMS_TRANSPORT', app_is_production() ? 'twilio' : 'log'));
    $whatsappTransport = strtolower((string) app_env('WHATSAPP_TRANSPORT', app_is_production() ? 'twilio' : 'log'));

    $checks['environment'][] = pr_status($appEnv === 'production', 'APP_ENV is production', "Current: {$appEnv}");
    $checks['environment'][] = pr_status(str_starts_with($baseUrl, 'https://'), 'APP_URL uses HTTPS', "Current: {$baseUrl}");
    $checks['environment'][] = pr_status(app_env('APP_KEY', '') !== '' && app_env('APP_KEY', 'change-me-in-env') !== 'change-me-in-env', 'APP_KEY is configured', pr_mask(app_env('APP_KEY', '')));
    $checks['environment'][] = pr_status(app_env('JWT_SECRET', '') !== '', 'JWT secret configured', pr_mask(app_env('JWT_SECRET', '')));

    $checks['database'][] = pr_status((bool) $pdo->query('SELECT 1')->fetchColumn(), 'Database connection works');
    foreach (['users', 'applications', 'grower_farms', 'farm_verifications', 'agronomy_cases', 'provider_registry', 'platform_governance_policies', 'dr_backups'] as $table) {
        $checks['database'][] = pr_status(app_table_exists($pdo, $table), "Table exists: {$table}");
    }

    $checks['authenticated_roles'][] = pr_status((int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' OR is_super_admin = 1")->fetchColumn() > 0, 'At least one admin/super admin user exists');
    $checks['authenticated_roles'][] = pr_status((int) $pdo->query("SELECT COUNT(*) FROM users WHERE platform_role = 'national_coordinator'")->fetchColumn() > 0, 'National coordinator user exists');
    $checks['authenticated_roles'][] = pr_status((int) $pdo->query("SELECT COUNT(*) FROM users WHERE platform_role = 'state_coordinator'")->fetchColumn() > 0, 'State coordinator user exists');
    $checks['authenticated_roles'][] = pr_status((int) $pdo->query("SELECT COUNT(*) FROM staff_profiles sp JOIN users u ON u.id = sp.user_id WHERE u.platform_role = 'state_coordinator' AND COALESCE(sp.state, '') <> ''")->fetchColumn() > 0, 'State coordinator has assigned state');

    $checks['mail'][] = pr_status($mailTransport !== 'log', 'Email transport is live', "Current: {$mailTransport}");
    $checks['mail'][] = pr_status(app_env('MAIL_FROM_ADDRESS', '') !== '', 'Mail from address configured', (string) app_env('MAIL_FROM_ADDRESS', ''));

    $twilioSid = pr_setting($pdo, 'twilio_sid');
    $twilioToken = pr_setting($pdo, 'twilio_token');
    $checks['sms_whatsapp'][] = pr_status($smsTransport !== 'log', 'SMS transport is live', "Current: {$smsTransport}");
    $checks['sms_whatsapp'][] = pr_status($whatsappTransport !== 'log', 'WhatsApp transport is live', "Current: {$whatsappTransport}");
    $checks['sms_whatsapp'][] = pr_status($twilioSid !== '' && $twilioToken !== '', 'Twilio credentials configured', 'SID ' . pr_mask($twilioSid));
    $checks['sms_whatsapp'][] = pr_status(class_exists('Twilio\\Rest\\Client'), 'Twilio SDK installed', class_exists('Twilio\\Rest\\Client') ? 'autoload ok' : 'vendor/autoload missing or SDK absent');

    $paystack = pr_setting($pdo, 'paystack_secret_key') ?: app_env('PAYSTACK_SECRET_KEY', '');
    $flutterwave = pr_setting($pdo, 'flutterwave_secret_key') ?: app_env('FLUTTERWAVE_SECRET_KEY', '');
    $checks['payment'][] = pr_status($paystack !== '' && !str_contains($paystack, 'YOUR_'), 'Paystack secret configured', pr_mask($paystack));
    $checks['payment'][] = pr_status($flutterwave !== '' && !str_contains($flutterwave, 'YOUR_'), 'Flutterwave secret configured', pr_mask($flutterwave));
    $checks['payment'][] = pr_status(function_exists('curl_init'), 'cURL available for payment verification');

    $dr = dr_settings($pdo);
    $backupPath = trim((string) ($dr['dr_backup_storage_path'] ?? 'private_backups'));
    $absoluteBackup = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($backupPath, "/\\"));
    $checks['backup'][] = pr_status($backupPath !== '', 'Backup storage path configured', $backupPath);
    $checks['backup'][] = pr_status(is_dir($absoluteBackup) || is_writable(dirname($absoluteBackup)), 'Backup path writable or creatable', $absoluteBackup);
    $checks['backup'][] = pr_status((int) $pdo->query("SELECT COUNT(*) FROM dr_backups")->fetchColumn() > 0, 'At least one backup manifest exists');

    $checks['security'][] = pr_status(admin_setting($pdo, 'password_min_length', '8') !== '', 'Password policy configured', 'Min length ' . admin_setting($pdo, 'password_min_length', '8'));
    $checks['security'][] = pr_status((int) $pdo->query("SELECT COUNT(*) FROM platform_governance_policies WHERE status = 'approved'")->fetchColumn() >= 4, 'Core governance policies approved');
    $checks['security'][] = pr_status((int) $pdo->query("SELECT COUNT(*) FROM audit_log")->fetchColumn() >= 0, 'Audit table readable');

    return $checks;
}

function pr_flatten_checks(array $checks): array
{
    $flat = [];
    foreach ($checks as $group => $items) {
        foreach ($items as $item) {
            $item['group'] = $group;
            $flat[] = $item;
        }
    }
    return $flat;
}

function pr_score(array $checks): int
{
    $flat = pr_flatten_checks($checks);
    if (!$flat) {
        return 0;
    }
    $passed = count(array_filter($flat, static fn(array $check): bool => (bool) $check['ok']));
    return (int) round(($passed / count($flat)) * 100);
}

function pr_run_live_test(PDO $pdo, string $channel, string $recipient): array
{
    $recipient = trim($recipient);
    if ($recipient === '') {
        return pr_status(false, strtoupper($channel) . ' live test', 'Recipient is required.');
    }
    $body = 'NATCODEV production readiness test at ' . date('Y-m-d H:i:s');
    if ($channel === 'email') {
        $ok = app_send_mail($recipient, 'NATCODEV production readiness test', $body);
        return pr_status($ok, 'Email live test', $ok ? 'Provider accepted/logged test.' : 'Provider rejected test.');
    }
    if ($channel === 'sms') {
        $ok = sendSMSMessage($recipient, $body);
        return pr_status($ok, 'SMS live test', $ok ? 'Provider accepted/logged test.' : 'Provider rejected test.');
    }
    if ($channel === 'whatsapp') {
        $ok = sendWhatsAppMessage($recipient, $body);
        return pr_status($ok, 'WhatsApp live test', $ok ? 'Provider accepted/logged test.' : 'Provider rejected test.');
    }
    return pr_status(false, 'Live test', 'Unsupported channel.');
}
