<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/dashboard-layout.php';
require_once __DIR__ . '/../lib/otp-delivery.php';

session_start();
$pdo = db();
$user = current_user($pdo);
if (!$user) {
    redirect_to('login.php');
}
dashboard_redirect_learner_only($pdo, $user);
$profilePhone = '';
if (app_column_exists($pdo, 'users', 'phone')) {
    $phoneStmt = $pdo->prepare("SELECT phone FROM users WHERE id = ? LIMIT 1");
    $phoneStmt->execute([(int) $user['id']]);
    $profilePhone = (string) $phoneStmt->fetchColumn();
}

app_add_column_if_missing($pdo, 'users', 'phone_verified', 'TINYINT(1) NOT NULL DEFAULT 0');
app_add_column_if_missing($pdo, 'users', 'phone_verification_code', 'VARCHAR(10) NULL');
app_add_column_if_missing($pdo, 'users', 'phone_verification_expires', 'DATETIME NULL');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['send_code'])) {
        $code = (string) random_int(100000, 999999);
        $expires = date('Y-m-d H:i:s', strtotime('+5 minutes'));

        if ($profilePhone === '') {
            $error = 'Add a phone number on your profile before requesting a code.';
        } else {
            $delivery = otp_send_code($pdo, (int) $user['id'], $code, 'phone_verification', $profilePhone, (string) ($user['email'] ?? ''));
            if (!$delivery['ok']) {
                $error = 'Verification code could not be delivered. ' . implode(' ', $delivery['errors'] ?: ['Check WhatsApp/SMS settings and try again.']);
            } else {
                $pdo->prepare("
                    UPDATE users SET
                        phone_verification_code = ?,
                        phone_verification_expires = ?
                    WHERE id = ?
                ")->execute([$code, $expires, $_SESSION['user_id']]);
                $message = 'Verification code ' . otp_delivery_message($delivery) . '.';
                if (!app_is_production()) {
                    $message .= ' Local test code: ' . $code;
                }
            }
        }
    } elseif (isset($_POST['verify_code'])) {
        $enteredCode = $_POST['verification_code'] ?? '';
        
        // Verify code
        $stmt = $pdo->prepare("
            SELECT phone_verification_code, phone_verification_expires 
            FROM users 
            WHERE id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $verification = $stmt->fetch();
        
        if ($verification && 
            $verification['phone_verification_code'] === $enteredCode && 
            strtotime($verification['phone_verification_expires']) > time()) {
            
            // Mark phone as verified
            $pdo->prepare("
                UPDATE users SET 
                    phone_verified = 1, 
                    phone_verification_code = NULL, 
                    phone_verification_expires = NULL 
                WHERE id = ?
            ")->execute([$_SESSION['user_id']]);
            
            $success = "Phone number verified successfully!";
            header('Location: profile.php?verified=1');
            exit;
            
        } else {
            $error = "Invalid or expired verification code.";
        }
    }
}
?>
<?php dashboard_page_start('Phone Verification', ['active' => 'verify-phone.php', 'description' => 'Verify your phone number for certificate and support workflows.', 'wide' => true]); ?>
  <section class="card" style="max-width:680px;">
  
  <?php if (!empty($success)): ?>
    <div class="notice success"><?= e($success) ?></div>
  <?php elseif (!empty($message)): ?>
    <div class="notice success"><?= e($message) ?></div>
    <form method="POST">
      <label>Verification Code</label>
      <input type="text" name="verification_code" placeholder="Enter 6-digit code" maxlength="6" required>
      <button type="submit" name="verify_code">Verify Code</button>
    </form>
  <?php elseif (!empty($error)): ?>
    <div class="notice error"><?= e($error) ?></div>
    <form method="POST">
      <button type="submit" name="send_code">Resend Code</button>
    </form>
  <?php else: ?>
    <p class="muted">We need to verify your phone number before issuing your certificate.</p>
    <?php if ($profilePhone === ''): ?>
      <div class="notice error">Add a phone number on your profile before requesting a code.</div>
      <a class="button secondary" href="profile.php">Update Profile</a>
    <?php else: ?>
    <form method="POST">
      <button type="submit" name="send_code">Send Verification Code</button>
    </form>
    <?php endif; ?>
  <?php endif; ?>
  </section>
<?php dashboard_page_end(); ?>
