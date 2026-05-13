<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/auth-layout.php';

session_start();
$pdo = db();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    if (!$email) {
        $error = 'Enter a valid email address.';
    } else {
        app_add_column_if_missing($pdo, 'users', 'password_reset_token', 'VARCHAR(64) NULL');
        app_add_column_if_missing($pdo, 'users', 'password_reset_expires', 'DATETIME NULL');

        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);

        if ($userId = $stmt->fetchColumn()) {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $pdo->prepare("UPDATE users SET password_reset_token = ?, password_reset_expires = ? WHERE id = ?")->execute([$token, $expires, $userId]);

            $resetUrl = app_base_url() . "/dashboard/reset-password.php?token=$token";
            app_send_mail((string) $email, 'NATCODEV Password Reset', "Click to reset your password: $resetUrl");
            $message = 'Password reset link sent to your email.';
        } else {
            $error = 'Email not found in our system.';
        }
    }
}
?>
<?php auth_page_start('Forgot Password'); ?>
  <form method="POST">
    <h1>Reset Your Password</h1>
    <p class="lead">Enter your account email and we will send a secure reset link.</p>
    <?php if ($message): ?><p class="success"><?= e($message) ?></p><?php endif; ?>
    <?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>
    <input type="email" name="email" placeholder="Your email" required>
    <button type="submit">Send Reset Link</button>
    <div class="links"><a href="login.php">Back to login</a></div>
  </form>
<?php auth_page_end(); ?>
