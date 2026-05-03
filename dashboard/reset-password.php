<!-- dashboard/reset-password.php -->
<?php
$token = $_GET['token'] ?? '';
if (!$token) {
    die("Invalid reset token.");
}

// Verify token
$stmt = $pdo->prepare("
    SELECT id FROM users 
    WHERE password_reset_token = ? AND password_reset_expires > NOW()
");
$stmt->execute([$token]);
$userId = $stmt->fetchColumn();

if (!$userId) {
    die("Token expired or invalid.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';
    
    if ($password !== $confirm || strlen($password) < 6) {
        $error = "Passwords must match and be at least 6 characters.";
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare("
            UPDATE users SET password = ?, password_reset_token = NULL, password_reset_expires = NULL WHERE id = ?
        ")->execute([$hashed, $userId]);
        
        header('Location: login.php?reset=success');
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head><title>Reset Password</title></head>
<body>
  <h2>Create New Password</h2>
  <?php if (!empty($error)): ?><p style="color:red;"><?= $error ?></p><?php endif; ?>
  
  <form method="POST">
    <input type="password" name="password" placeholder="New password" required minlength="6">
    <input type="password" name="confirm" placeholder="Confirm password" required>
    <button type="submit">Reset Password</button>
  </form>
</body>
</html>