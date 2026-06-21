<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../lib/admin-layout.php';

if (!defined('NATCODEV_SETTINGS_LEGACY')) {
    require __DIR__ . '/acad/admin-settings-workspace.php';
    return;
}

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
    'wide' => true,
    'css' => '
      .settings-layout{display:grid;grid-template-columns:minmax(0,1fr) 330px;gap:18px;align-items:start}
      .settings-links{display:grid;gap:10px}
      .settings-links a{display:block;padding:12px;border:1px solid var(--line);border-radius:7px;background:#fbfdfb;color:var(--ink)}
      .settings-links a:hover{background:#f1faf5;text-decoration:none;border-color:#cfe6d8}
      .settings-links strong,.settings-links span{display:block}
      .settings-links span{margin-top:4px;color:var(--muted);font-size:.9rem;font-weight:650}
      .setting-section{border:1px solid var(--line);border-radius:8px;padding:14px;background:#fbfdfb;margin-bottom:14px}
      .setting-section h2{margin-top:0}
      @media(max-width:920px){.settings-layout{grid-template-columns:1fr}}
    ',
]);
?>
<?php if ($message): ?><div class="notice <?= str_starts_with($message, 'Invalid') ? 'error' : 'ok' ?>"><?= e($message) ?></div><?php endif; ?>
<section class="settings-layout">
  <form class="panel" method="post">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <div class="setting-section">
      <h2>SMS Validation</h2>
      <p class="muted">Controls phone verification behavior used by certificate readiness and identity workflows.</p>
      <label><input type="checkbox" name="sms_phone_validation_required" <?= $settings['sms_phone_validation_required'] === '1' ? 'checked' : '' ?>> Require phone validation before certificate issuance</label>
      <label><input type="checkbox" name="sms_validation_notifications" <?= $settings['sms_validation_notifications'] === '1' ? 'checked' : '' ?>> Send SMS validation notifications</label>
      <label>Verification Code Timeout (seconds)</label>
      <input type="number" name="sms_verification_timeout" min="60" max="3600" value="<?= e($settings['sms_verification_timeout']) ?>">
    </div>

    <div class="setting-section">
      <h2>Optional Modules</h2>
      <p class="muted">Enable or pause operational modules that require extra hardware, setup, or integrations.</p>
      <label><input type="checkbox" name="iot_module_enabled" <?= $settings['iot_module_enabled'] === '1' ? 'checked' : '' ?>> Enable IoT module controls</label>
    </div>

    <div class="actions"><button type="submit">Save Settings</button></div>
  </form>

  <aside class="panel">
    <h2>Related Configuration</h2>
    <p class="muted">Settings is now separate. Use these links for adjacent admin configuration instead of crowding this page.</p>
    <div class="settings-links">
      <a href="templates.php"><strong>Message Templates</strong><span>Edit email, SMS, and notification templates.</span></a>
      <a href="notifications.php"><strong>Notification Log</strong><span>Review delivery status and failed messages.</span></a>
      <a href="communications.php"><strong>Communication Hub</strong><span>Manage broadcasts and stakeholder messages.</span></a>
      <a href="governance.php"><strong>Policies & Governance</strong><span>Review platform policy and compliance controls.</span></a>
      <a href="resources.php"><strong>Learning Resources</strong><span>Upload and manage resource materials used occasionally.</span></a>
      <a href="import-users.php"><strong>Import & Engagement</strong><span>Bulk user import and engagement tools.</span></a>
      <a href="production-readiness.php"><strong>Production Readiness</strong><span>Review less frequent launch and operational readiness checks.</span></a>
      <a href="monitoring.php"><strong>System Health</strong><span>Check production health and integration status.</span></a>
      <a href="../super-admin/index.php?view=modules"><strong>Super Admin Module Setup</strong><span>Advanced module owners, modes, and rollout notes.</span></a>
    </div>
  </aside>
</section>
<?php admin_page_end(); ?>
