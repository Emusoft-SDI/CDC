<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../lib/admin-layout.php';
require_once __DIR__ . '/../lib/workspace-account.php';
require_once __DIR__ . '/../lib/admin-operator-strip.php';

$pdo = db();
admin_ensure_schema($pdo);
admin_require($pdo);

$message = '';
$error = '';

foreach ([
    'phone' => "VARCHAR(30) NULL",
    'location' => "VARCHAR(255) NULL",
    'profile_picture' => "VARCHAR(255) NULL",
    'notify_email' => "TINYINT(1) NOT NULL DEFAULT 1",
    'notify_whatsapp' => "TINYINT(1) NOT NULL DEFAULT 0",
    'notify_sms' => "TINYINT(1) NOT NULL DEFAULT 0",
] as $column => $definition) {
    app_add_column_if_missing($pdo, 'users', $column, $definition);
}

function admin_profile_setting_save(PDO $pdo, string $key, string $value): void
{
    $stmt = $pdo->prepare("
        INSERT INTO settings (key_name, value)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE value = VALUES(value)
    ");
    $stmt->execute([$key, $value]);
}

function admin_profile_upload(string $field, string $prefix): ?string
{
    if (empty($_FILES[$field]['name'])) {
        return null;
    }

    $upload = app_uploaded_file_info((array) ($_FILES[$field] ?? []), ['jpg', 'jpeg', 'png', 'webp'], 2 * 1024 * 1024, 'Profile picture');

    $uploadDir = dirname(__DIR__) . '/uploads/profile-pictures';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $fileName = app_safe_upload_name($prefix, $upload['name'], $upload['extension']);
    $target = $uploadDir . '/' . $fileName;
    if (!move_uploaded_file($upload['tmp_name'], $target)) {
        throw new RuntimeException('Unable to upload profile picture.');
    }

    return 'uploads/profile-pictures/' . $fileName;
}

$adminUser = null;
$currentRole = admin_current_platform_role($pdo) ?? 'admin';
$workspaceDefault = preg_replace('/[^a-z0-9_-]/i', '', $currentRole) ?: 'admin';
$workspaceKey = preg_replace('/[^a-z0-9_-]/i', '', (string) ($_GET['workspace'] ?? $workspaceDefault)) ?: $workspaceDefault;
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['workspace'])) {
    redirect_to('profile.php?workspace=' . rawurlencode($workspaceKey));
}
$workspaceLabel = ucwords(str_replace(['_', '-'], ' ', $workspaceKey));
if (!empty($_SESSION['user_id'])) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $_SESSION['user_id']]);
    $candidate = $stmt->fetch() ?: null;
    if ($candidate && admin_session_is_authenticated($pdo)) {
        $adminUser = $candidate;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Please refresh and try again.';
    } else {
        try {
            $action = (string) ($_POST['action'] ?? 'profile');
            if ($action === 'account_password') {
                if (!$adminUser) {
                    throw new RuntimeException('Password changes require a user-backed admin account. Update ADMIN_PASSWORD in environment settings for password-only admin mode.');
                }
                workspace_account_change_password($pdo, (int) $adminUser['id'], (string) ($_POST['current_password'] ?? ''), (string) ($_POST['new_password'] ?? ''), (string) ($_POST['confirm_password'] ?? ''));
                $message = 'Admin password changed.';
                throw new RuntimeException('__ADMIN_PASSWORD_DONE__');
            }

            $name = trim((string) ($_POST['name'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $phone = trim((string) ($_POST['phone'] ?? ''));
            $location = trim((string) ($_POST['location'] ?? ''));
            $notifyEmail = isset($_POST['notify_email']) ? 1 : 0;
            $notifyWhatsapp = isset($_POST['notify_whatsapp']) ? 1 : 0;
            $notifySms = isset($_POST['notify_sms']) ? 1 : 0;

            if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Enter a valid name and email address.');
            }

            $profilePicture = admin_profile_upload('profile_picture', 'admin_profile');

            if ($adminUser) {
                $pictureSql = $profilePicture ? ', profile_picture = ?' : '';
                $stmt = $pdo->prepare("
                    UPDATE users
                    SET name = ?, email = ?, phone = ?, location = ?,
                        notify_email = ?, notify_whatsapp = ?, notify_sms = ?
                        {$pictureSql}
                    WHERE id = ?
                ");
                $params = [$name, $email, $phone, $location, $notifyEmail, $notifyWhatsapp, $notifySms];
                if ($profilePicture) {
                    $params[] = $profilePicture;
                }
                $params[] = (int) $adminUser['id'];
                $stmt->execute($params);
            }

            admin_profile_setting_save($pdo, 'admin_profile_name_' . $workspaceKey, $name);
            admin_profile_setting_save($pdo, 'admin_profile_email_' . $workspaceKey, $email);
            admin_profile_setting_save($pdo, 'admin_profile_phone_' . $workspaceKey, $phone);
            admin_profile_setting_save($pdo, 'admin_profile_location_' . $workspaceKey, $location);
            admin_profile_setting_save($pdo, 'admin_notify_email_enabled', (string) $notifyEmail);
            admin_profile_setting_save($pdo, 'admin_notify_whatsapp_enabled', (string) $notifyWhatsapp);
            admin_profile_setting_save($pdo, 'admin_notify_sms_enabled', (string) $notifySms);
            if ($profilePicture) {
                admin_profile_setting_save($pdo, 'admin_profile_picture_' . $workspaceKey, $profilePicture);
            }

            $message = $workspaceLabel . ' profile updated.';
            if ($adminUser) {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
                $stmt->execute([(int) $adminUser['id']]);
                $adminUser = $stmt->fetch() ?: $adminUser;
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$profile = [
    'name' => $adminUser['name'] ?? admin_setting($pdo, 'admin_profile_name_' . $workspaceKey, admin_setting($pdo, 'admin_profile_name', 'NATCODEV Admin')),
    'email' => $adminUser['email'] ?? admin_setting($pdo, 'admin_profile_email_' . $workspaceKey, admin_setting($pdo, 'admin_profile_email', app_env('ADMIN_NOTIFY_EMAIL', 'info@natcodev.com.ng'))),
    'phone' => $adminUser['phone'] ?? admin_setting($pdo, 'admin_profile_phone_' . $workspaceKey, admin_setting($pdo, 'admin_profile_phone', '')),
    'location' => $adminUser['location'] ?? admin_setting($pdo, 'admin_profile_location_' . $workspaceKey, admin_setting($pdo, 'admin_profile_location', '')),
    'profile_picture' => $adminUser['profile_picture'] ?? admin_setting($pdo, 'admin_profile_picture_' . $workspaceKey, admin_setting($pdo, 'admin_profile_picture', '')),
    'notify_email' => (int) ($adminUser['notify_email'] ?? admin_setting($pdo, 'admin_notify_email_enabled', '1')),
    'notify_whatsapp' => (int) ($adminUser['notify_whatsapp'] ?? admin_setting($pdo, 'admin_notify_whatsapp_enabled', '0')),
    'notify_sms' => (int) ($adminUser['notify_sms'] ?? admin_setting($pdo, 'admin_notify_sms_enabled', '0')),
];

admin_page_start($workspaceLabel . ' Profile', [
    'active' => 'index.php',
    'description' => 'Manage your RBAC-scoped ' . $workspaceLabel . ' operator profile, picture, password, and notification preferences inside the active workspace shell.',
    'wide' => true,
]);
?>
    <link rel="stylesheet" href="../assets/css/admin-workspaces.css">
    <style>
      .workspace-profile-head { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; flex-wrap:wrap; margin-bottom:18px; }
      .workspace-profile-kicker { margin:0 0 6px; color:#12633f; font-weight:900; text-transform:uppercase; letter-spacing:.04em; font-size:.78rem; }
      .workspace-profile-head h2 { margin:0; color:#0f172a; font-size:1.35rem; }
      .workspace-profile-head p { margin:7px 0 0; color:#475569; max-width:760px; line-height:1.55; }
      .workspace-profile-actions { display:flex; gap:8px; flex-wrap:wrap; }
      .workspace-profile-actions a { display:inline-flex; align-items:center; justify-content:center; min-height:38px; border:1px solid #cfe2d6; border-radius:8px; padding:8px 11px; background:#fff; color:#075c34; font-weight:850; text-decoration:none; }
      .workspace-profile-actions a:hover { background:#e8f6ec; color:#06451f; }
      .workspace-profile-grid { display:grid; grid-template-columns:minmax(0, 1.45fr) minmax(320px, .75fr); gap:18px; align-items:start; }
      .workspace-profile-card { background:#fff; border:1px solid rgba(16,24,40,.09); border-radius:8px; box-shadow:0 10px 28px rgba(16,24,40,.06); padding:20px; }
      .workspace-profile-card h2 { margin-top:0; color:#0f172a; font-size:1.08rem; }
      .workspace-profile-card .meta { color:#475569; line-height:1.55; }
      .workspace-profile-card input[type=text], .workspace-profile-card input[type=email], .workspace-profile-card input[type=tel], .workspace-profile-card input[type=password], .workspace-profile-card input[type=file] { width:100%; border:1px solid #dce8e1; border-radius:8px; padding:10px 12px; }
      .workspace-profile-card label { font-weight:800; color:#334155; }
      .workspace-profile-card button { border:0; border-radius:8px; background:#12633f; color:#fff; padding:10px 14px; font-weight:900; cursor:pointer; }
      .workspace-profile-card button:hover { background:#0b4f31; }
      @media(max-width:960px){ .workspace-profile-grid{grid-template-columns:1fr} }
    </style>
    <?= admin_workspace_operator_strip($pdo, [
        'asset_prefix' => '../',
        'profile_href' => 'profile.php?workspace=' . rawurlencode($workspaceKey),
        'password_href' => 'profile.php?workspace=' . rawurlencode($workspaceKey) . '#password',
        'logout_action' => 'admin.php',
        'fallback_logout_action' => 'index.php',
        'title' => $workspaceLabel . ' profile workspace',
        'profile_key' => $workspaceKey,
        'placeholder' => 'Search ' . $workspaceLabel . ' workspace...',
    ]) ?>
    <?php if ($message): ?><div class="notice ok"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>

    <section class="workspace-profile-head">
      <div>
        <p class="workspace-profile-kicker">RBAC workspace profile</p>
        <h2><?= e($workspaceLabel) ?> Operator Profile</h2>
        <p>This profile belongs to the current signed-in operator context and stays inside the same admin workspace experience as the rest of the dashboard.</p>
      </div>
      <div class="workspace-profile-actions">
        <a href="index.php">Workspace Hub</a>
        <a href="javascript:history.back()">Back</a>
      </div>
    </section>

    <section class="workspace-profile-grid">
      <form class="workspace-profile-card" method="post" enctype="multipart/form-data">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="profile">
        <h2><?= e($workspaceLabel) ?> Profile Details</h2>
        <?php if (!empty($profile['profile_picture'])): ?>
          <p><img src="../<?= e((string) $profile['profile_picture']) ?>" alt="Admin profile picture" style="width:116px;height:116px;object-fit:cover;border-radius:50%;border:1px solid var(--line);"></p>
        <?php endif; ?>
        <div class="grid">
          <div><label>Name</label><input type="text" name="name" value="<?= e((string) $profile['name']) ?>" required></div>
          <div><label>Email</label><input type="email" name="email" value="<?= e((string) $profile['email']) ?>" required></div>
          <div><label>Phone</label><input type="tel" name="phone" value="<?= e((string) $profile['phone']) ?>"></div>
          <div><label>Location</label><input type="text" name="location" value="<?= e((string) $profile['location']) ?>"></div>
          <div><label>Profile Picture</label><input type="file" name="profile_picture" accept=".jpg,.jpeg,.png,.webp"></div>
        </div>
        <label class="check"><input type="checkbox" name="notify_email" <?= (int) $profile['notify_email'] === 1 ? 'checked' : '' ?>> Email notifications</label><br>
        <label class="check"><input type="checkbox" name="notify_whatsapp" <?= (int) $profile['notify_whatsapp'] === 1 ? 'checked' : '' ?>> WhatsApp notifications</label><br>
        <label class="check"><input type="checkbox" name="notify_sms" <?= (int) $profile['notify_sms'] === 1 ? 'checked' : '' ?>> SMS notifications</label><br><br>
        <button type="submit">Save Profile</button>
      </form>

      <aside class="workspace-profile-card" id="password">
        <h2>Account Mode</h2>
        <?php if ($adminUser): ?>
          <p><strong>User-backed <?= e($workspaceLabel) ?> operator</strong></p>
          <p class="meta">This RBAC profile is linked to user #<?= (int) $adminUser['id'] ?> as <?= e(status_label($currentRole)) ?> and updates only that signed-in user record.</p>
        <?php else: ?>
          <p><strong>Password-only admin</strong></p>
          <p class="meta">This local/password-only operator profile is stored in workspace settings until a dedicated user-backed operator signs in.</p>
        <?php endif; ?>
        <p class="meta">Staff, field-agent, agronomist, agric-extensionist, and farm-hand profile records are managed from Users and Recruitment.</p>        <hr>
        <h2>Change Password</h2>
        <?php if ($adminUser): ?>
        <form method="post" class="grid">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="account_password">
          <div><label>Current Password</label><input type="password" name="current_password" autocomplete="current-password" required></div>
          <div><label>New Password</label><input type="password" name="new_password" autocomplete="new-password" minlength="8" required></div>
          <div><label>Confirm Password</label><input type="password" name="confirm_password" autocomplete="new-password" minlength="8" required></div>
          <div><button type="submit">Update Password</button></div>
        </form>
        <?php else: ?>
          <p class="meta">This admin is using environment password mode. Change `ADMIN_PASSWORD` or create a user-backed admin before changing password here.</p>
        <?php endif; ?>
      </aside>
    </section>
<?php admin_page_end(); ?>
