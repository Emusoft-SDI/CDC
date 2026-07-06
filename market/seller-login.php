<?php
declare(strict_types=1);
require_once __DIR__ . '/_market.php';
require_once __DIR__ . '/../lib/otp-delivery.php';

$pdo = market_boot();
app_ensure_user_verification_schema($pdo);
$error = '';
$notice = isset($_GET['message']) ? 'You have logged out of Seller Central. Login again or browse the marketplace.' : '';
$lastEmail = filter_var(trim((string) ($_GET['email'] ?? '')), FILTER_VALIDATE_EMAIL) ?: '';

if (isset($_GET['social'])) {
    app_oauth_begin((string) $_GET['social'], 'seller', 'market/seller-central.php');
}
if (isset($_GET['oauth_callback'])) {
    try {
        $oauth = app_oauth_finish($pdo, (string) $_GET['oauth_callback'], 'seller');
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([(int) $oauth['user_id']]);
        $oauthUser = $stmt->fetch() ?: ['id' => (int) $oauth['user_id'], 'email' => ''];
        market_activate_seller_access($pdo, $oauthUser);
        unset($_SESSION['user_id']);
        $otpStart = otp_begin_email_login_challenge($pdo, $oauthUser, 'market/seller-central.php');
        if ($otpStart['ok']) {
            redirect_to('../verify-otp.php');
        }
        $error = (string) $otpStart['message'];
    } catch (Throwable $e) {
        error_log('Seller OAuth login error: ' . $e->getMessage());
        $error = $e->getMessage();
    }
}
if (isset($_GET['oauth_error'])) {
    $error = app_oauth_missing_credentials_message((string) ($_GET['provider'] ?? 'google'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!app_check_rate_limit('seller_login', 8, 900)) {
        $error = 'Too many seller login attempts. Try again later.';
    } elseif (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid security token. Refresh and try again.';
    } else {
        $email = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
        $password = (string) ($_POST['password'] ?? '');
        $lastEmail = $email ?: '';
        if ($email && $password !== '') {
            $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            if ($user && password_verify($password, (string) $user['password'])) {
                if (app_user_needs_email_verification($user)) {
                    $error = 'Your seller account needs email verification before login.';
                } else {
                    market_activate_seller_access($pdo, $user);
                    $otpStart = otp_begin_email_login_challenge($pdo, $user, 'market/seller-central.php');
                    if ($otpStart['ok']) {
                        redirect_to('../verify-otp.php');
                    }
                    $error = (string) $otpStart['message'];
                }
            }
        }
        if ($error === '') {
            $error = 'Invalid seller login details.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Seller Login - NATCODEV Marketplace</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    *{box-sizing:border-box}body{margin:0;font-family:"Segoe UI",Arial,sans-serif;background:linear-gradient(90deg,rgba(5,45,20,.78),rgba(5,45,20,.24)),url("../assets/public/buyer-marketplace-entry.png") center/cover;min-height:100vh;display:grid;place-items:center;padding:24px}.panel{width:min(470px,94vw);background:#fff;border-radius:18px;padding:34px;box-shadow:0 24px 70px rgba(0,0,0,.24)}.brand{display:flex;gap:12px;align-items:center}.brand img{width:56px;height:56px;border-radius:50%;object-fit:contain}.brand strong{font-size:1.45rem;color:#06451f}h1{margin:22px 0 8px;color:#101828}p{color:#667085;line-height:1.5}label{display:block;font-weight:850;margin-top:12px}input{width:100%;border:1px solid #dfe8d8;border-radius:9px;padding:13px;margin-top:6px}.btn{width:100%;border:0;border-radius:10px;background:#08753a;color:#fff;padding:14px;margin-top:16px;font-weight:950;font-size:1rem}.alert{padding:12px;border-radius:10px;margin:10px 0;font-weight:850}.err{background:#fff1f2;color:#b42318}.social{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:12px 0}.social a{border:1px solid #dfe8d8;border-radius:10px;padding:12px;text-align:center;color:#06451f;font-weight:900;text-decoration:none}.links{display:flex;justify-content:space-between;gap:12px;margin-top:16px;flex-wrap:wrap}a{color:#06451f;font-weight:900;text-decoration:none}.pass{position:relative}.pass button{position:absolute;right:9px;top:34px;border:0;background:#eef8ef;color:#06451f;border-radius:8px;padding:8px 10px;font-weight:900;cursor:pointer}.note{font-size:.88rem;color:#667085;margin-top:14px}
  </style>
</head>
<body>
<section class="panel">
  <a class="brand" href="index.php"><img src="<?= e(app_primary_logo_url()) ?>" alt="NATCODEV"><strong>NATCODEV Seller</strong></a>
  <h1>Seller Central login</h1>
  <p>Manage your store, products, orders, payouts, promotions, and buyer support.</p>
  <?php if ($notice): ?><div class="alert ok"><?= e($notice) ?></div><?php endif; ?><?php if ($error): ?><div class="alert err"><?= e($error) ?></div><?php endif; ?>
  <?= app_social_buttons('seller', 'market/seller-central.php') ?>
  <form method="post">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <label>Email<input type="email" name="email" value="<?= e($lastEmail) ?>" required></label>
    <label class="pass">Password<input id="seller-login-password" type="password" name="password" required><button type="button" data-toggle-password="seller-login-password">Show</button></label>
    <button class="btn">Login to Seller Central</button>
  </form>
  <p class="note">This does not create a provider accreditation profile. It only activates marketplace seller access for commerce.</p>
  <div class="links"><a href="seller-register.php">Create seller account</a><a href="../forgot-password.php<?= $lastEmail ? '?email=' . e(urlencode($lastEmail)) . '&next=market/seller-login.php' : '?next=market/seller-login.php' ?>">Forgot password?</a><a href="index.php">Back to Marketplace</a></div><div class="links"><a href="index.php">Marketplace</a><a href="../index.php">NATCODEV Home</a><a href="../support/index.php">Support</a></div>
</section>
<script>document.querySelectorAll('[data-toggle-password]').forEach(function(button){button.addEventListener('click',function(){var input=document.getElementById(button.getAttribute('data-toggle-password'));var visible=input.type==='text';input.type=visible?'password':'text';button.textContent=visible?'Show':'Hide';});});</script>
</body>
</html>