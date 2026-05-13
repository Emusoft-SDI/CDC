<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/auth-layout.php';

session_start();
$pdo = db();
if (!isset($_SESSION['otp_user_id'])) {
    redirect_to('otp-login.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = (string) ($_POST['otp'] ?? '');
    $stmt = $pdo->prepare("SELECT id FROM otp_sessions WHERE user_id = ? AND otp_code = ? AND expires_at > NOW() AND used = 0");
    $stmt->execute([$_SESSION['otp_user_id'], $otp]);

    if ($sessionId = $stmt->fetchColumn()) {
        $pdo->prepare("UPDATE otp_sessions SET used = 1 WHERE id = ?")->execute([$sessionId]);
        $_SESSION['user_id'] = $_SESSION['otp_user_id'];
        unset($_SESSION['otp_user_id']);
        redirect_to('index.php');
    } else {
        $error = 'Invalid or expired OTP.';
    }
}
?>
<?php auth_page_start('Verify OTP'); ?>
  <form method="POST">
    <h1>Enter OTP</h1>
    <p class="lead">Enter the 6-digit code sent to your phone.</p>
    <?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>
    <input type="text" name="otp" placeholder="6-digit code" required maxlength="6">
    <button type="submit">Verify</button>
  </form>
<?php auth_page_end(); ?>
