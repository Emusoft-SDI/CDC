<?php
declare(strict_types=1);

require_once __DIR__ . '/_user_auth.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/dashboard-layout.php';

$pdo = db();
app_ensure_core_schema($pdo);

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Please refresh and try again.';
    } else {
        $current = (string) ($_POST['current'] ?? '');
        $new = (string) ($_POST['new'] ?? '');
        $confirm = (string) ($_POST['confirm'] ?? '');

        if ($current === '' || $new === '' || $confirm === '') {
            $error = 'All fields are required.';
        } elseif ($new !== $confirm) {
            $error = 'New passwords do not match.';
        } elseif (strlen($new) < 8) {
            $error = 'Password must be at least 8 characters.';
        } else {
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([(int) $_SESSION['user_id']]);
            $user = $stmt->fetch();

            if ($user && password_verify($current, (string) $user['password'])) {
                $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([
                    password_hash($new, PASSWORD_DEFAULT),
                    (int) $_SESSION['user_id'],
                ]);
                $message = 'Password updated successfully.';
            } else {
                $error = 'Current password is incorrect.';
            }
        }
    }
}
?>
<?php dashboard_page_start('Change Password', ['active' => 'change-password.php', 'description' => 'Update your account password securely.', 'wide' => true]); ?>
<h1>Change Password</h1>
    <?php if ($message): ?><p class="success"><?= e($message) ?></p><?php endif; ?>
    <?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <label>Current Password</label>
      <span class="password-field"><input id="change_current" type="password" name="current" required autocomplete="current-password"><button class="password-toggle" type="button" data-target="change_current" aria-pressed="false">Show</button></span>
      <label>New Password</label>
      <span class="password-field"><input id="change_new" type="password" name="new" minlength="8" required autocomplete="new-password"><button class="password-toggle" type="button" data-target="change_new" aria-pressed="false">Show</button></span>
      <label>Confirm New Password</label>
      <span class="password-field"><input id="change_confirm" type="password" name="confirm" minlength="8" required autocomplete="new-password"><button class="password-toggle" type="button" data-target="change_confirm" aria-pressed="false">Show</button></span>
      <button type="submit">Update Password</button>
    </form>
  <?php dashboard_page_end(); ?>
