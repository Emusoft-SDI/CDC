<!-- dashboard/verify-phone.php -->
<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['send_code'])) {
        // Generate verification code
        $code = rand(100000, 999999);
        $expires = date('Y-m-d H:i:s', strtotime('+5 minutes'));
        
        // Save to database
        $pdo->prepare("
            UPDATE users SET 
                phone_verification_code = ?, 
                phone_verification_expires = ?
            WHERE id = ?
        ")->execute([$code, $expires, $_SESSION['user_id']]);
        
        // Send SMS via Twilio
        sendSMSMessage($user['phone'], "Your NATCODEV verification code is: {$code}. Valid for 5 minutes.");
        
        $message = "Verification code sent to your phone.";
        
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
<!DOCTYPE html>
<html>
<head>
  <title>Verify Phone Number - NATCODEV</title>
</head>
<body>
  <h2>Phone Number Verification</h2>
  
  <?php if (!empty($success)): ?>
    <p style="color:green;"><?= $success ?></p>
  <?php elseif (!empty($message)): ?>
    <p><?= $message ?></p>
    <form method="POST">
      <input type="text" name="verification_code" placeholder="Enter 6-digit code" maxlength="6" required>
      <button type="submit" name="verify_code">Verify Code</button>
    </form>
  <?php elseif (!empty($error)): ?>
    <p style="color:red;"><?= $error ?></p>
    <form method="POST">
      <button type="submit" name="send_code">Resend Code</button>
    </form>
  <?php else: ?>
    <p>We need to verify your phone number before issuing your certificate.</p>
    <form method="POST">
      <button type="submit" name="send_code">Send Verification Code</button>
    </form>
  <?php endif; ?>
</body>
</html>