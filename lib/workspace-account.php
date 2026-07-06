<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/otp-delivery.php';

function workspace_account_update_profile(PDO $pdo, int $userId, array $data, ?array $file = null): void
{
    if ($file === null && isset($_FILES['profile_picture']) && is_array($_FILES['profile_picture'])) {
        $file = $_FILES['profile_picture'];
    }
    app_add_column_if_missing($pdo, 'users', 'profile_picture', 'VARCHAR(255) NULL');
    $fields = [];
    $params = [];
    foreach (['name', 'phone', 'location'] as $field) {
        if (array_key_exists($field, $data) && app_column_exists($pdo, 'users', $field)) {
            $fields[] = $field . ' = ?';
            $params[] = trim((string) $data[$field]);
        }
    }
    if ($file && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $info = app_uploaded_file_info($file, ['jpg', 'jpeg', 'png', 'webp'], 2 * 1024 * 1024, 'Profile picture', ['image/jpeg', 'image/png', 'image/webp']);
        $dir = dirname(__DIR__) . '/uploads/profile-pictures';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $name = 'profile-' . $userId . '-' . bin2hex(random_bytes(5)) . '.' . $info['extension'];
        $target = $dir . '/' . $name;
        if (!move_uploaded_file($info['tmp_name'], $target)) {
            throw new RuntimeException('Unable to upload profile picture.');
        }
        $fields[] = 'profile_picture = ?';
        $params[] = 'uploads/profile-pictures/' . $name;
    }
    if (!$fields) {
        return;
    }
    $params[] = $userId;
    $pdo->prepare('UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($params);
}

function workspace_account_change_password(PDO $pdo, int $userId, string $current, string $new, string $confirm): void
{
    $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $hash = (string) $stmt->fetchColumn();
    if ($hash === '' || !password_verify($current, $hash)) {
        throw new RuntimeException('Current password is incorrect.');
    }
    if (strlen($new) < 8 || $new !== $confirm) {
        throw new RuntimeException('New password must be at least 8 characters and match confirmation.');
    }
    $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $userId]);
}

function workspace_account_send_password_change_otp(PDO $pdo, int $userId): array
{
    otp_ensure_schema($pdo);
    $stmt = $pdo->prepare('SELECT email, phone FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $email = filter_var((string) ($user['email'] ?? ''), FILTER_VALIDATE_EMAIL) ?: null;
    $phone = trim((string) ($user['phone'] ?? '')) ?: null;
    if (!$email && !$phone) {
        return ['ok' => false, 'message' => 'No email or phone is available for OTP delivery.'];
    }

    $code = (string) random_int(100000, 999999);
    $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    $delivery = otp_send_code($pdo, $userId, $code, 'password_change', $phone, $email);
    if (!$delivery['ok']) {
        return ['ok' => false, 'message' => 'OTP could not be delivered. ' . implode(' ', $delivery['errors'] ?: ['Check notification settings and try again.'])];
    }

    $pdo->prepare('INSERT INTO otp_sessions (user_id, otp_code, expires_at, purpose) VALUES (?, ?, ?, \'password_change\')')
        ->execute([$userId, $code, $expires]);

    return ['ok' => true, 'message' => 'Security code ' . otp_delivery_message($delivery) . '.'];
}

function workspace_account_change_password_with_otp(PDO $pdo, int $userId, string $otp, string $new, string $confirm): void
{
    if ($otp === '') {
        throw new RuntimeException('One-time code is required to change password.');
    }
    if (strlen($new) < 8 || $new !== $confirm) {
        throw new RuntimeException('New password must be at least 8 characters and match confirmation.');
    }
    if (!otp_verify_code($pdo, $userId, $otp, 'password_change')) {
        throw new RuntimeException('Invalid or expired OTP.');
    }
    $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $userId]);
}

function workspace_account_render_profile_forms(array $user, string $prefix, string $profileTitle = 'Account Profile', string $passwordTitle = 'Change Password', string $assetPrefix = '../', string $passwordMode = 'standard'): void
{
    $picture = trim((string) ($user['profile_picture'] ?? ''));
    $pictureUrl = $picture !== '' ? $assetPrefix . ltrim($picture, '/') : app_primary_logo_url();
    ?>
    <style>
      .account-password-field { position: relative; display: block; }
      .account-password-field input { width: 100%; padding-right: 100px; }
      .account-password-toggle { position: absolute; right: 8px; top: 50%; transform: translateY(-50%); border: 0; border-radius: 8px; background: #eef8ef; color: #06451f; padding: 8px 10px; font-weight: 700; cursor: pointer; }
      .account-picture { display: grid; grid-template-columns: auto 1fr; gap: 16px; align-items: center; }
      .account-picture img { width: 72px; height: 72px; border-radius: 18px; object-fit: cover; border: 1px solid #dfe8d8; background: #fff; }
      .account-picture label { display: block; margin: 0; font-weight: 700; color: #0f172a; }
      .account-picture input[type="file"] { margin-top: 8px; }
      .form-grid { display: grid; grid-template-columns: repeat(12, minmax(0, 1fr)); gap: 20px; }
      .field-form { padding: 24px; }
      .form-group { display: flex; flex-direction: column; gap: 8px; }
      .form-group label { font-weight: 700; color: #0f172a; }
      .form-group input[type="text"], .form-group input[type="password"], .form-group input[type="file"], .form-group select, .form-group textarea { width: 100%; padding: 12px 14px; border: 1px solid #d1d5db; border-radius: 12px; background: #fff; color: #0f172a; }
      .form-group textarea { resize: vertical; min-height: 120px; }
      .wide { grid-column: span 12; }
      .span-7 { grid-column: span 7; }
      .span-5 { grid-column: span 5; }
      .form-actions { display: flex; justify-content: flex-start; gap: 12px; margin-top: 16px; }
      .otp-note { color: #475569; font-size: .92rem; margin: 0 0 18px; max-width: 100%; line-height: 1.6; }
      .account-otp-panel { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px; margin-bottom: 18px; }
      .account-otp-panel .form-group { margin-bottom: 14px; }
      .account-otp-panel .form-actions { margin-top: 12px; }
      @media (max-width: 900px) { .form-grid { grid-template-columns: 1fr; } .span-7, .span-5, .wide { grid-column: auto; } }
    </style>
    <form id="account" method="post" enctype="multipart/form-data" class="card form-grid field-form span-7">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="account_profile">
      <div class="wide card-head fa-panel-head sc-panel-head"><h2><?= e($profileTitle) ?></h2></div>
      <div class="wide account-picture">
        <img src="<?= e($pictureUrl) ?>" alt="<?= e((string) ($user['name'] ?? 'Profile')) ?> profile picture">
        <label>
          Profile Picture
          <input type="file" name="profile_picture" accept="image/jpeg,image/png,image/webp">
        </label>
      </div>
      <div class="form-group wide">
        <label for="<?= e($prefix) ?>-name">Name</label>
        <input id="<?= e($prefix) ?>-name" name="name" value="<?= e((string) ($user['name'] ?? '')) ?>" required>
      </div>
      <div class="form-group wide">
        <label for="<?= e($prefix) ?>-phone">Phone</label>
        <input id="<?= e($prefix) ?>-phone" name="phone" value="<?= e((string) ($user['phone'] ?? '')) ?>">
      </div>
      <div class="form-group wide">
        <label for="<?= e($prefix) ?>-location">Location</label>
        <input id="<?= e($prefix) ?>-location" name="location" value="<?= e((string) ($user['location'] ?? '')) ?>">
      </div>
      <div class="form-actions wide"><button class="btn btn-primary" type="submit">Save Account Profile</button></div>
    </form>
    <form method="post" class="card form-grid field-form span-5">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" id="<?= e($prefix) ?>-password-action" value="send_password_change_otp">
      <div class="wide card-head fa-panel-head sc-panel-head"><h2><?= e($passwordTitle) ?></h2></div>
      <?php if ($passwordMode === 'otp'): ?>
        <div class="wide card-body account-otp-panel">
          <p class="otp-note">Send a one-time code to your confirmed email or phone, then use it to update your password securely.</p>
          <div class="form-actions">
            <button type="button" class="btn btn-secondary" onclick="document.getElementById('<?= e($prefix) ?>-password-action').value='send_password_change_otp'; this.form.submit();">Send OTP</button>
          </div>
          <div class="form-group">
            <label for="<?= e($prefix) ?>-otp-code">OTP Code</label>
            <input id="<?= e($prefix) ?>-otp-code" name="otp_code" inputmode="numeric" pattern="[0-9]*" maxlength="6" placeholder="Enter 6-digit code">
          </div>
        </div>
      <?php else: ?>
        <div class="form-group wide">
          <label for="<?= e($prefix) ?>-current-password">Current Password</label>
          <span class="account-password-field">
            <input id="<?= e($prefix) ?>-current-password" type="password" name="current_password" autocomplete="current-password" required>
            <button class="account-password-toggle" type="button" data-toggle-password="<?= e($prefix) ?>-current-password">Show</button>
          </span>
        </div>
      <?php endif; ?>
      <div class="form-group wide">
        <label for="<?= e($prefix) ?>-new-password">New Password</label>
        <span class="account-password-field">
          <input id="<?= e($prefix) ?>-new-password" type="password" name="new_password" autocomplete="new-password" minlength="8" required>
          <button class="account-password-toggle" type="button" data-toggle-password="<?= e($prefix) ?>-new-password">Show</button>
        </span>
      </div>
      <div class="form-group wide">
        <label for="<?= e($prefix) ?>-confirm-password">Confirm Password</label>
        <span class="account-password-field">
          <input id="<?= e($prefix) ?>-confirm-password" type="password" name="confirm_password" autocomplete="new-password" minlength="8" required>
          <button class="account-password-toggle" type="button" data-toggle-password="<?= e($prefix) ?>-confirm-password">Show</button>
        </span>
      </div>
      <div class="form-actions wide"><button class="btn btn-primary" type="submit" onclick="document.getElementById('<?= e($prefix) ?>-password-action').value='account_password';">Update Password</button></div>
    </form>
    <script>
      document.querySelectorAll('[data-toggle-password]').forEach(function(button) {
        button.addEventListener('click', function() {
          var input = document.getElementById(button.getAttribute('data-toggle-password'));
          if (!input) return;
          var show = input.type === 'password';
          input.type = show ? 'text' : 'password';
          button.textContent = show ? 'Hide' : 'Show';
        });
      });
    </script>
    <?php
}
