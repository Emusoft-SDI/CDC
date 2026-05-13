<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/admin-layout.php';

session_start();
$pdo = db();
admin_ensure_schema($pdo);
admin_require($pdo);

$message = '';
$settings = [
    'sms_phone_validation_required' => '0',
    'sms_validation_notifications' => '1',
    'sms_verification_timeout' => '300',
    'iot_module_enabled' => '0',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $message = 'Invalid security token.';
    } else {
        $settings['sms_phone_validation_required'] = isset($_POST['sms_phone_validation_required']) ? '1' : '0';
        $settings['sms_validation_notifications'] = isset($_POST['sms_validation_notifications']) ? '1' : '0';
        $settings['iot_module_enabled'] = isset($_POST['iot_module_enabled']) ? '1' : '0';
        $settings['sms_verification_timeout'] = (string) max(60, min(3600, (int) ($_POST['sms_verification_timeout'] ?? 300)));

        $stmt = $pdo->prepare("
            INSERT INTO settings (key_name, value) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE value = VALUES(value)
        ");
        foreach ($settings as $key => $value) {
            $stmt->execute([$key, $value]);
        }
        $message = 'Settings updated.';
    }
}

foreach ($settings as $key => $default) {
    $settings[$key] = admin_setting($pdo, $key, $default);
}

admin_page_start('Settings', [
    'active' => 'settings.php',
    'description' => 'Manage operational controls for SMS validation, notifications, and optional modules.',
]);
?>
<?php if ($message): ?><div class="notice <?= str_starts_with($message, 'Invalid') ? 'error' : 'ok' ?>"><?= e($message) ?></div><?php endif; ?>
<form class="panel" method="post">
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
  <h2>SMS Validation</h2>
  <label><input type="checkbox" name="sms_phone_validation_required" <?= $settings['sms_phone_validation_required'] === '1' ? 'checked' : '' ?>> Require phone validation before certificate issuance</label>
  <label><input type="checkbox" name="sms_validation_notifications" <?= $settings['sms_validation_notifications'] === '1' ? 'checked' : '' ?>> Send SMS validation notifications</label>
  <label>Verification Code Timeout (seconds)</label>
  <input type="number" name="sms_verification_timeout" min="60" max="3600" value="<?= e($settings['sms_verification_timeout']) ?>">
  <h2>Modules</h2>
  <label><input type="checkbox" name="iot_module_enabled" <?= $settings['iot_module_enabled'] === '1' ? 'checked' : '' ?>> Enable IoT module controls</label>
  <div class="actions"><button type="submit">Save Settings</button></div>
</form>
<?php admin_page_end(); ?>
