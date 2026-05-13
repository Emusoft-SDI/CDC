<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/auth-layout.php';

$pdo = db();
$token = trim((string) ($_GET['token'] ?? ''));
if ($token === '' || !preg_match('/^[a-f0-9]{64}$/i', $token)) {
    http_response_code(422);
    exit('Invalid reset token.');
}

$stmt = $pdo->prepare("SELECT id FROM users WHERE password_reset_token = ? AND password_reset_expires > NOW()");
$stmt->execute([$token]);
$userId = $stmt->fetchColumn();

if (!$userId) {
    http_response_code(422);
    exit('Token expired or invalid.');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm'] ?? '');

    if ($password !== $confirm || strlen($password) < 8) {
        $error = 'Passwords must match and be at least 8 characters.';
    } else {
        $pdo->prepare("UPDATE users SET password = ?, password_reset_token = NULL, password_reset_expires = NULL WHERE id = ?")->execute([
            password_hash($password, PASSWORD_DEFAULT),
            $userId,
        ]);
        redirect_to('login.php?reset=success');
    }
}
?>
<?php auth_page_start('Reset Password'); ?>
  <form method="POST">
    <h1>Create New Password</h1>
    <p class="lead">Choose a strong password for your grower dashboard account.</p>
    <?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>
    <input type="password" name="password" placeholder="New password" required minlength="8">
    <input type="password" name="confirm" placeholder="Confirm password" required minlength="8">
    <button type="submit">Reset Password</button>
  </form>
<?php auth_page_end(); ?>
