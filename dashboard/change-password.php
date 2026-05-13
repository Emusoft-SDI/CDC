<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/dashboard-layout.php';

session_start();
$pdo = db();
app_ensure_core_schema($pdo);

if (empty($_SESSION['user_id'])) {
    redirect_to('login.php');
}

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
      <input type="password" name="current" required autocomplete="current-password">
      <label>New Password</label>
      <input type="password" name="new" minlength="8" required autocomplete="new-password">
      <label>Confirm New Password</label>
      <input type="password" name="confirm" minlength="8" required autocomplete="new-password">
      <button type="submit">Update Password</button>
    </form>
  <?php dashboard_page_end(); ?>
