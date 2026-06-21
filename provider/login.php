<?php
declare(strict_types=1);
require_once __DIR__ . '/_provider.php';

$pdo = provider_boot();
$error = '';
$notice = isset($_GET['social']) ? 'Social provider login is ready for OAuth credentials. Email login is active now.' : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!app_check_rate_limit('provider_login', 10, 600)) {
        $error = 'Too many login attempts. Please try again in 10 minutes.';
    } elseif (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Security session expired. Please refresh and try again.';
    } else {
        $email = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    $password = (string) ($_POST['password'] ?? '');
    if ($email && $password !== '') {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([(string) $email]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, (string) $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['id'];
            redirect_to('dashboard.php');
        }
    }
    $error = 'Invalid provider login details.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Provider Login - NATCODEV</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    *{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;font-family:"Segoe UI",Arial,sans-serif;background:linear-gradient(90deg,rgba(5,45,20,.82),rgba(5,45,20,.22)),url("../assets/public/provider-commerce-hero.png") center/cover}.panel{width:min(480px,92vw);background:#fff;border-radius:18px;padding:34px;box-shadow:0 26px 70px rgba(0,0,0,.25)}.brand{display:flex;gap:12px;align-items:center;text-decoration:none;color:#06451f}.brand img{width:58px;height:58px;border-radius:50%}.brand strong{font-size:1.5rem}label{display:block;font-weight:850;margin-top:12px}input{width:100%;border:1px solid #dfe8d8;border-radius:9px;padding:13px;margin-top:6px}.btn{width:100%;border:0;border-radius:10px;background:#08753a;color:#fff;padding:14px;margin-top:16px;font-weight:950}.social{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:14px 0}.social a{border:1px solid #dfe8d8;border-radius:10px;padding:12px;text-align:center;color:#06451f;font-weight:900;text-decoration:none}.alert{padding:12px;border-radius:10px;margin:10px 0;font-weight:850}.err{background:#fff1f2;color:#b42318}.ok{background:#e8f6ec;color:#06451f}.links{display:flex;justify-content:space-between;margin-top:16px}a{color:#06451f;font-weight:900;text-decoration:none}.pass{position:relative}.pass button{position:absolute;right:9px;top:34px;border:0;background:#eef8ef;color:#06451f;border-radius:8px;padding:8px 10px;font-weight:900;cursor:pointer}
  </style>
</head>
<body>
<section class="panel">
  <a class="brand" href="../index.php"><img src="<?= e(app_primary_logo_url()) ?>" alt=""><strong>NATCODEV Provider</strong></a>
  <h1>Provider Sign In</h1>
  <p>Manage accreditation, listings, orders, Academy training, wallet, and support.</p>
  <?php if ($error): ?><div class="alert err"><?= e($error) ?></div><?php endif; ?>
  <?php if ($notice): ?><div class="alert ok"><?= e($notice) ?></div><?php endif; ?>
  <div class="social"><a href="?social=google"><i class="fab fa-google"></i> Google</a><a href="?social=facebook"><i class="fab fa-facebook"></i> Facebook</a></div>
  <form method="post">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <label>Email<input type="email" name="email" required></label>
    <label class="pass">Password<input id="provider-login-password" type="password" name="password" required><button type="button" data-toggle-password="provider-login-password">Show</button></label>
    <button class="btn">Sign In</button>
  </form>
  <div class="links"><a href="index.php">Register provider</a><a href="../market/index.php">Marketplace</a></div>
</section>
<script>document.querySelectorAll('[data-toggle-password]').forEach(function(button){button.addEventListener('click',function(){var input=document.getElementById(button.getAttribute('data-toggle-password'));var visible=input.type==='text';input.type=visible?'password':'text';button.textContent=visible?'Show':'Hide';});});</script>
</body>
</html>
