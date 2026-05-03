<!-- dashboard/login.php -->
<?php
session_start();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    $pdo = new PDO("mysql:host=localhost;dbname=coconutventure_growers;charset=utf8mb4", 
                   "coconutventure_growers", "1^v1V&Ak{DIPL~Y.");
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        header('Location: index.php');
        exit;
    } else {
        $error = "Invalid credentials";
    }
}
?>
<!DOCTYPE html>
<html>
<head><title>NATCODEV Dashboard Login</title></head>
<body>
  <h2>Grower Dashboard</h2>
  <?php if (!empty($error)): ?><p style="color:red;"><?= $error ?></p><?php endif; ?>
  <form method="POST">
    <input type="email" name="email" placeholder="Email" required><br><br>
    <input type="password" name="password" placeholder="Password" required><br><br>
    <button type="submit">Login</button>
  </form>
</body>
</html>