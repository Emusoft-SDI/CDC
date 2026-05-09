<!-- dashboard/change-password.php -->
<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$pdo = new PDO("mysql:host=localhost;dbname=natcodevcom_data;charset=utf8mb4", 
               "natcodevcom_data", "XC^#3)[;*xTcm&V9");

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current'] ?? '';
    $new = $_POST['new'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    // Validate
    if (!$current || !$new || !$confirm) {
        $error = "All fields required.";
    } elseif ($new !== $confirm) {
        $error = "New passwords do not match.";
    } elseif (strlen($new) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        // Verify current password
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        if ($user && password_verify($current, $user['password'])) {
            // Update password
            $hashed = password_hash($new, PASSWORD_DEFAULT);
            $upd = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $upd->execute([$hashed, $_SESSION['user_id']]);
            $message = "✅ Password updated successfully!";
        } else {
            $error = "Current password is incorrect.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Change Password - NATCODEV</title>
  <style>
    body { font-family: Arial; max-width: 600px; margin: 30px auto; }
    .form-group { margin: 15px 0; }
    label { display: block; margin-bottom: 5px; font-weight: bold; }
    input[type="password"] { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; }
    button { background: #2d5016; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
    .alert { padding: 10px; margin: 15px 0; border-radius: 4px; }
    .success { background: #e8f5e9; color: #2d5016; }
    .error { background: #ffebee; color: #c62828; }
  </style>
</head>
<body>
  <h2>Change Password</h2>

  <?php if ($message): ?>
    <div class="alert success"><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST">
    <div class="form-group">
      <label>Current Password</label>
      <input type="password" name="current" required>
    </div>
    <div class="form-group">
      <label>New Password</label>
      <input type="password" name="new" minlength="6" required>
    </div>
    <div class="form-group">
      <label>Confirm New Password</label>
      <input type="password" name="confirm" required>
    </div>
    <button type="submit">Update Password</button>
    <a href="index.php" style="margin-left: 15px;">← Back to Dashboard</a>
  </form>
</body>
</html>