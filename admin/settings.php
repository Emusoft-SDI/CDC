<?php
declare(strict_types=1);

if (!defined('NATCODEV_SETTINGS_LEGACY')) {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        $query = $_GET;
        $query['page'] = preg_replace('/[^a-z-]/', '', (string) ($query['page'] ?? 'overview')) ?: 'overview';
        header('Location: settings/?' . http_build_query($query), true, 302);
        exit;
    }
    define('NATCODEV_SETTINGS_LEGACY', true);
}

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../lib/admin-layout.php';

$pdo = db();
admin_ensure_schema($pdo);
admin_require($pdo);

$message = '';
$settings = [
    'sms_phone_validation_required' => '0',
    'sms_validation_notifications' => '1',
    'sms_verification_timeout' => '300',
    'iot_module_enabled' => '0',
    'social_login_enabled' => '0',
    'google_oauth_enabled' => '0',
    'facebook_oauth_enabled' => '0',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $message = 'Invalid security token.';
    } else {
        $settings['sms_phone_validation_required'] = isset($_POST['sms_phone_validation_required']) ? '1' : '0';
        $settings['sms_validation_notifications'] = isset($_POST['sms_validation_notifications']) ? '1' : '0';
        $settings['iot_module_enabled'] = isset($_POST['iot_module_enabled']) ? '1' : '0';
        $settings['social_login_enabled'] = isset($_POST['social_login_enabled']) ? '1' : '0';
        $settings['google_oauth_enabled'] = isset($_POST['google_oauth_enabled']) ? '1' : '0';
        $settings['facebook_oauth_enabled'] = isset($_POST['facebook_oauth_enabled']) ? '1' : '0';
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
    'description' => 'Manage operational controls for SMS validation, notifications, sign-in, and optional modules.',
    'wide' => true,
    'topbar_only' => true,
    'breadcrumbs' => [['label' => 'System Settings'], ['label' => 'Operational Settings']],
    'css' => '
      .settings-layout{display:grid;grid-template-columns:minmax(0,1fr) 330px;gap:18px;align-items:start}
      .settings-links{display:grid;gap:10px}
      .settings-links a{display:block;padding:12px;border:1px solid var(--line);border-radius:7px;background:#fbfdfb;color:var(--ink)}
      .settings-links a:hover{background:#f1faf5;text-decoration:none;border-color:#cfe6d8}
      .settings-links strong,.settings-links span{display:block}
      .settings-links span{margin-top:4px;color:var(--muted);font-size:.9rem;font-weight:650}
      .setting-section{border:1px solid var(--line);border-radius:8px;padding:14px;background:#fbfdfb;margin-bottom:14px}
      .setting-section h2{margin-top:0}
      .setting-section code{background:#eef7f1;border:1px solid #d8e2dc;border-radius:5px;padding:2px 5px}
      .set-brand{display:flex;align-items:center;gap:10px;padding-bottom:14px;margin-bottom:12px;border-bottom:1px solid rgba(255,255,255,.14);color:#fff}
      .set-brand img{width:44px;height:44px;border-radius:50%;background:#fff;object-fit:contain;padding:4px}
      .set-brand strong{display:block;line-height:1.1}
      .set-brand span{display:block;margin-top:3px;color:#dff5e8;font-size:.72rem;font-weight:750}
      .set-label{margin:14px 4px 7px;color:#aee4c4;font-size:.7rem;font-weight:950;text-transform:uppercase;letter-spacing:.03em}
      .set-rail nav{display:grid;gap:5px}
      @media(max-width:920px){.settings-layout{grid-template-columns:1fr}}
    ',
]);
?>
<div class="set-shell">
  <aside class="set-rail" aria-label="Settings workspace navigation">
    <div class="set-brand"><img src="<?= e(app_admin_logo_url()) ?>" alt="NATCODEV"><div><strong>NATCODEV</strong><span>System Settings</span></div></div>
    <div class="set-label">Related Configuration</div>
    <nav aria-label="Related configuration">
      <a href="<?= e(admin_chrome_url('templates.php')) ?>"><i class="fas fa-file-lines"></i><span>Message Templates</span></a>
      <a href="<?= e(admin_chrome_url('notifications.php')) ?>"><i class="fas fa-bell"></i><span>Notification Log</span></a>
      <a href="<?= e(admin_chrome_url('communications.php')) ?>"><i class="fas fa-bullhorn"></i><span>Communication Hub</span></a>
      <a href="<?= e(admin_chrome_url('governance.php')) ?>"><i class="fas fa-shield-halved"></i><span>Policies &amp; Governance</span></a>
      <a href="<?= e(admin_chrome_url('resources.php')) ?>"><i class="fas fa-book"></i><span>Learning Resources</span></a>
      <a href="<?= e(admin_chrome_url('import-users.php')) ?>"><i class="fas fa-file-import"></i><span>Import &amp; Engagement</span></a>
      <a href="<?= e(admin_chrome_url('production-readiness.php')) ?>"><i class="fas fa-clipboard-check"></i><span>Production Readiness</span></a>
      <a href="<?= e(admin_chrome_url('monitoring.php')) ?>"><i class="fas fa-heart-pulse"></i><span>System Health</span></a>
      <a href="<?= e(admin_chrome_url('../super-admin/index.php?view=modules')) ?>"><i class="fas fa-puzzle-piece"></i><span>Super Admin Module Setup</span></a>
    </nav>
    <div class="set-label">Exits</div>
    <nav aria-label="Settings exits">
      <a href="<?= e(admin_chrome_url('settings/')) ?>"><i class="fas fa-sliders"></i><span>Operational Settings</span></a>
      <a href="<?= e(admin_chrome_url('index.php')) ?>"><i class="fas fa-gauge-high"></i><span>Workspace Hub</span></a>
    </nav>
  </aside>
  <div class="set-main">
    <div class="page-title"><div><h1>Operational Settings</h1><p>Manage operational controls for SMS validation, notifications, sign-in, and optional modules.</p></div></div>
    <?php if ($message): ?><div class="notice <?= str_starts_with($message, 'Invalid') ? 'error' : 'ok' ?>"><?= e($message) ?></div><?php endif; ?>
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
      <h2>Social Login</h2>
      <p class="muted">Operator-controlled display and access for Google/Facebook login buttons. Disabled means the buttons are hidden and direct social-login URLs are blocked.</p>
      <label><input type="checkbox" name="social_login_enabled" <?= $settings['social_login_enabled'] === '1' ? 'checked' : '' ?>> Enable social login platform-wide</label>
      <label><input type="checkbox" name="google_oauth_enabled" <?= $settings['google_oauth_enabled'] === '1' ? 'checked' : '' ?>> Allow Google login button and callback</label>
      <label><input type="checkbox" name="facebook_oauth_enabled" <?= $settings['facebook_oauth_enabled'] === '1' ? 'checked' : '' ?>> Allow Facebook login button and callback</label>
      <p class="muted">Credentials must still be configured in <code>.env</code>: <code>GOOGLE_CLIENT_ID</code>, <code>GOOGLE_CLIENT_SECRET</code>, <code>FACEBOOK_CLIENT_ID</code>, and <code>FACEBOOK_CLIENT_SECRET</code>.</p>
    </div>

    <div class="setting-section">
      <h2>Optional Modules</h2>
      <p class="muted">Enable or pause operational modules that require extra hardware, setup, or integrations.</p>
      <label><input type="checkbox" name="iot_module_enabled" <?= $settings['iot_module_enabled'] === '1' ? 'checked' : '' ?>> Enable IoT module controls</label>
    </div>

    <div class="actions"><button type="submit">Save Settings</button></div>
  </form>
  </div>
</div>
<?php admin_page_end(); ?>