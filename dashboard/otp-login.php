<!-- dashboard/otp-login.php -->
<?php
session_start();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    
    // Find user
    $stmt = $pdo->prepare("SELECT id, phone FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user && $phone === $user['phone']) {
        // Generate OTP
        $otp = rand(100000, 999999);
        $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        
        $pdo->prepare("
            INSERT INTO otp_sessions (user_id, otp_code, expires_at) VALUES (?, ?, ?)
        ")->execute([$user['id'], $otp, $expires]);
        
        // Send OTP via SMS/WhatsApp
        sendWhatsAppMessage($phone, "Your NATCODEV login code: $otp");
        
        $_SESSION['otp_user_id'] = $user['id'];
        $_SESSION['otp_verified'] = false;
        header('Location: verify-otp.php');
        exit;
    } else {
        $error = "Invalid email or phone number.";
    }
}
?>
<!DOCTYPE html>
<html>
<head><title>OTP Login</title></head>
<body>
  <h2>Login with OTP</h2>
  <?php if (!empty($error)): ?><p style="color:red;"><?= $error ?></p><?php endif; ?>
  
  <form method="POST">
    <input type="email" name="email" placeholder="Email" required>
    <input type="tel" name="phone" placeholder="Phone Number" required>
    <button type="submit">Send OTP</button>
  </form>
</body>
</html>