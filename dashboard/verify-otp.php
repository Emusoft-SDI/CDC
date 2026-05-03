<!-- dashboard/verify-otp.php -->
<?php
session_start();
if (!isset($_SESSION['otp_user_id'])) {
    header('Location: otp-login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = $_POST['otp'] ?? '';
    
    $stmt = $pdo->prepare("
        SELECT id FROM otp_sessions 
        WHERE user_id = ? AND otp_code = ? AND expires_at > NOW() AND used = 0
    ");
    $stmt->execute([$_SESSION['otp_user_id'], $otp]);
    
    if ($sessionId = $stmt->fetchColumn()) {
        // Mark OTP as used
        $pdo->prepare("UPDATE otp_sessions SET used = 1 WHERE id = ?")->execute([$sessionId]);
        
        // Login user
        $_SESSION['user_id'] = $_SESSION['otp_user_id'];
        unset($_SESSION['otp_user_id']);
        header('Location: index.php');
        exit;
    } else {
        $error = "Invalid or expired OTP.";
    }
}
?>
<!DOCTYPE html>
<html>
<head><title>Verify OTP</title></head>
<body>
  <h2>Enter OTP</h2>
  <?php if (!empty($error)): ?><p style="color:red;"><?= $error ?></p><?php endif; ?>
  
  <form method="POST">
    <input type="text" name="otp" placeholder="6-digit code" required maxlength="6">
    <button type="submit">Verify</button>
  </form>
</body>
</html>