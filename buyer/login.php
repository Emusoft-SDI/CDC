<?php
declare(strict_types=1);
require_once __DIR__ . '/_buyer.php';

$pdo = buyer_boot();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['_csrf'] ?? null)) {
    $email = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    $password = (string) ($_POST['password'] ?? '');
    if ($email && $password !== '') {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, (string) $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['id'];
            redirect_to('index.php');
        }
    }
    $error = 'Invalid buyer login details.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Buyer Login - NATCODEV</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    *{box-sizing:border-box}body{margin:0;font-family:"Segoe UI",Arial,sans-serif;background:linear-gradient(90deg,rgba(5,45,20,.78),rgba(5,45,20,.25)),url("../assets/public/buyer-marketplace-entry.png") center/cover;min-height:100vh;display:grid;place-items:center}.panel{width:min(460px,92vw);background:#fff;border-radius:18px;padding:34px;box-shadow:0 24px 70px rgba(0,0,0,.24)}.brand{display:flex;gap:12px;align-items:center}.brand img{width:56px;height:56px;border-radius:50%}.brand strong{font-size:1.5rem;color:#06451f}label{display:block;font-weight:850;margin-top:12px}input{width:100%;border:1px solid #dfe8d8;border-radius:9px;padding:13px;margin-top:6px}.btn{width:100%;border:0;border-radius:10px;background:#08753a;color:#fff;padding:14px;margin-top:16px;font-weight:950}.alert{padding:12px;border-radius:10px;margin:10px 0;font-weight:850}.err{background:#fff1f2;color:#b42318}.ok{background:#e8f6ec;color:#06451f}.links{display:flex;justify-content:space-between;margin-top:16px}a{color:#06451f;font-weight:900;text-decoration:none}.pass{position:relative}.pass button{position:absolute;right:9px;top:34px;border:0;background:#eef8ef;color:#06451f;border-radius:8px;padding:8px 10px;font-weight:900;cursor:pointer}
  </style>
</head>
<body><section class="panel"><a class="brand" href="../index.php"><img src="<?= e(app_primary_logo_url()) ?>" alt=""><strong>NATCODEV Buyer</strong></a><h1>Login</h1><p>Continue shopping, learning, and tracking your orders.</p><?php if ($error): ?><div class="alert err"><?= e($error) ?></div><?php endif; ?><form method="post"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><label>Email<input type="email" name="email" required></label><label class="pass">Password<input id="buyer-login-password" type="password" name="password" required><button type="button" data-toggle-password="buyer-login-password">Show</button></label><button class="btn">Login</button></form><div class="links"><a href="register.php">Create account</a><a href="../market/index.php">Browse marketplace</a></div></section><script>document.querySelectorAll('[data-toggle-password]').forEach(function(button){button.addEventListener('click',function(){var input=document.getElementById(button.getAttribute('data-toggle-password'));var visible=input.type==='text';input.type=visible?'password':'text';button.textContent=visible?'Show':'Hide';});});</script></body></html>
