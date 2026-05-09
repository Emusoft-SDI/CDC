<!-- dashboard/forgot-password.php -->
<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    
    if ($userId = $stmt->fetchColumn()) {
        // Generate reset token
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        $pdo->prepare("
            UPDATE users SET password_reset_token = ?, password_reset_expires = ? WHERE id = ?
        ")->execute([$token, $expires, $userId]);
        
        // Send reset email
        $resetUrl = "https://cfc.natcodev.com.ng/dashboard/reset-password.php?token=$token";
        mail($email, "NATCODEV Password Reset", 
             "Click to reset your password: $resetUrl", 
             "From: noreply@coconutventurehub.ng");
        
        $message = "Password reset link sent to your email.";
    } else {
        $error = "Email not found in our system.";
    }
}
?>
<!DOCTYPE html>
<html>
<head><title>Forgot Password</title></head>
<body>
  <h2>Reset Your Password</h2>
  <?php if (!empty($message)): ?><p style="color:green;"><?= $message ?></p><?php endif; ?>
  <?php if (!empty($error)): ?><p style="color:red;"><?= $error ?></p><?php endif; ?>
  
  <form method="POST">
    <input type="email" name="email" placeholder="Your email" required>
    <button type="submit">Send Reset Link</button>
  </form>
</body>
</html>