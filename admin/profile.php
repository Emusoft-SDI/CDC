<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/admin-layout.php';

session_start();
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

    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    $original = (string) $_FILES[$field]['name'];
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) {
        throw new RuntimeException('Profile picture must be JPG, PNG, or WebP.');
    }
    if ((int) ($_FILES[$field]['size'] ?? 0) > 2 * 1024 * 1024) {
        throw new RuntimeException('Profile picture must be 2MB or smaller.');
    }

    $uploadDir = dirname(__DIR__) . '/uploads/profile-pictures';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $fileName = preg_replace('/[^a-z0-9_-]/i', '', $prefix) . '_' . time() . '.' . $ext;
    $target = $uploadDir . '/' . $fileName;
    if (!move_uploaded_file((string) $_FILES[$field]['tmp_name'], $target)) {
        throw new RuntimeException('Unable to upload profile picture.');
    }

    return 'uploads/profile-pictures/' . $fileName;
}

$adminUser = null;
if (!empty($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'admin' LIMIT 1");
    $stmt->execute([(int) $_SESSION['user_id']]);
    $adminUser = $stmt->fetch() ?: null;
}
if (!$adminUser) {
    $stmt = $pdo->query("SELECT * FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
    $adminUser = $stmt->fetch() ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Please refresh and try again.';
    } else {
        try {
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

            admin_profile_setting_save($pdo, 'admin_profile_name', $name);
            admin_profile_setting_save($pdo, 'admin_profile_email', $email);
            admin_profile_setting_save($pdo, 'admin_profile_phone', $phone);
            admin_profile_setting_save($pdo, 'admin_profile_location', $location);
            admin_profile_setting_save($pdo, 'admin_notify_email_enabled', (string) $notifyEmail);
            admin_profile_setting_save($pdo, 'admin_notify_whatsapp_enabled', (string) $notifyWhatsapp);
            admin_profile_setting_save($pdo, 'admin_notify_sms_enabled', (string) $notifySms);
            if ($profilePicture) {
                admin_profile_setting_save($pdo, 'admin_profile_picture', $profilePicture);
            }

            $message = 'Admin profile updated.';
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
    'name' => $adminUser['name'] ?? admin_setting($pdo, 'admin_profile_name', 'NATCODEV Admin'),
    'email' => $adminUser['email'] ?? admin_setting($pdo, 'admin_profile_email', app_env('ADMIN_NOTIFY_EMAIL', 'info@natcodev.com.ng')),
    'phone' => $adminUser['phone'] ?? admin_setting($pdo, 'admin_profile_phone', ''),
    'location' => $adminUser['location'] ?? admin_setting($pdo, 'admin_profile_location', ''),
    'profile_picture' => $adminUser['profile_picture'] ?? admin_setting($pdo, 'admin_profile_picture', ''),
    'notify_email' => (int) ($adminUser['notify_email'] ?? admin_setting($pdo, 'admin_notify_email_enabled', '1')),
    'notify_whatsapp' => (int) ($adminUser['notify_whatsapp'] ?? admin_setting($pdo, 'admin_notify_whatsapp_enabled', '0')),
    'notify_sms' => (int) ($adminUser['notify_sms'] ?? admin_setting($pdo, 'admin_notify_sms_enabled', '0')),
];

admin_page_start('Admin Profile', [
    'active' => 'profile.php',
    'description' => 'Manage the admin contact identity, profile picture, and notification preferences used by the operations console.',
    'wide' => true,
]);
?>
    <?php if ($message): ?><div class="notice ok"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>

    <section class="grid">
      <form class="panel" method="post" enctype="multipart/form-data">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <h2>Profile Details</h2>
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

      <aside class="panel">
        <h2>Account Mode</h2>
        <?php if ($adminUser): ?>
          <p><strong>User-backed admin</strong></p>
          <p class="meta">This profile is linked to user #<?= (int) $adminUser['id'] ?> and updates the admin user record.</p>
        <?php else: ?>
          <p><strong>Password-only admin</strong></p>
          <p class="meta">This staging login uses `ADMIN_PASSWORD`, so the profile is stored in system settings until a dedicated admin user signs in.</p>
        <?php endif; ?>
        <p class="meta">Staff, field-agent, agronomist, and agric-extensionist profile records are managed from Users and Recruitment.</p>
      </aside>
    </section>
<?php admin_page_end(); ?>
