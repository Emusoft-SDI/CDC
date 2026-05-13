<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/auth-layout.php';
require_once __DIR__ . '/../lib/twilio.php';

session_start();
$pdo = db();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    $phone = preg_replace('/[^0-9]/', '', (string) ($_POST['phone'] ?? ''));
    $phoneColumn = app_column_exists($pdo, 'users', 'phone') ? 'phone' : 'email';
    $stmt = $pdo->prepare("SELECT id, {$phoneColumn} AS phone FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && ($phoneColumn === 'email' || $phone === preg_replace('/[^0-9]/', '', (string) $user['phone']))) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS otp_sessions (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, otp_code VARCHAR(10) NOT NULL, expires_at DATETIME NOT NULL, used TINYINT(1) NOT NULL DEFAULT 0, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_otp_sessions_user (user_id, expires_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $otp = rand(100000, 999999);
        $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        $pdo->prepare("INSERT INTO otp_sessions (user_id, otp_code, expires_at) VALUES (?, ?, ?)")->execute([$user['id'], $otp, $expires]);
        if ($phone !== '') {
            sendWhatsAppMessage($phone, "Your NATCODEV login code: $otp");
        }
        $_SESSION['otp_user_id'] = $user['id'];
        $_SESSION['otp_verified'] = false;
        redirect_to('verify-otp.php');
    } else {
        $error = 'Invalid email or phone number.';
    }
}
?>
<?php auth_page_start('OTP Login'); ?>
  <form method="POST">
    <h1>Login with OTP</h1>
    <p class="lead">Use your email and phone number to receive a one-time login code.</p>
    <?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>
    <input type="email" name="email" placeholder="Email" required>
    <input type="tel" name="phone" placeholder="Phone Number" required>
    <button type="submit">Send OTP</button>
    <div class="links"><a href="login.php">Use password instead</a></div>
  </form>
<?php auth_page_end(); ?>
