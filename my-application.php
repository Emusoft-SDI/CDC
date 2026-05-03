<!-- my-application.php -->
<?php
session_start();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    if ($email) {
        $_SESSION['edit_email'] = $email;
        header('Location: edit-application.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>My NATCODEV Application</title>
  <style>
    body { font-family: Arial; max-width: 500px; margin: 40px auto; }
    .form-group { margin: 15px 0; }
    input { width: 100%; padding: 10px; }
    button { background: #2d5016; color: white; padding: 12px; border: none; width: 100%; }
  </style>
</head>
<body>
  <h2>View or Update Your Application</h2>
  <p>Enter the email you used to apply:</p>
  <form method="POST">
    <div class="form-group">
      <input type="email" name="email" placeholder="your.email@example.com" required>
    </div>
    <button type="submit">Continue</button>
  </form>
</body>
</html>