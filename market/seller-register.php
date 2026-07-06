<?php
declare(strict_types=1);
require_once __DIR__ . '/_market.php';
require_once __DIR__ . '/../lib/otp-delivery.php';

$pdo = market_boot();
$currentUser = market_user($pdo);
$error = '';
$message = '';

if (isset($_GET['social'])) {
    app_oauth_begin((string) $_GET['social'], 'seller', 'market/seller-central.php');
}
if (isset($_GET['oauth_callback'])) {
    try {
        $oauth = app_oauth_finish($pdo, (string) $_GET['oauth_callback'], 'seller');
        $stmt = $pdo->prepare('SELECT id, email, name FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([(int) $oauth['user_id']]);
        $oauthUser = $stmt->fetch() ?: ['id' => (int) $oauth['user_id'], 'email' => '', 'name' => 'Seller'];
        market_activate_seller_access($pdo, $oauthUser);
        unset($_SESSION['user_id']);
        $otpStart = otp_begin_email_login_challenge($pdo, $oauthUser, 'market/seller-central.php');
        if ($otpStart['ok']) {
            redirect_to('../verify-otp.php');
        }
        $error = (string) $otpStart['message'];
    } catch (Throwable $e) {
        error_log('Seller registration OAuth error: ' . $e->getMessage());
        $error = $e->getMessage();
    }
}
if (isset($_GET['oauth_error'])) {
    $error = app_oauth_missing_credentials_message((string) ($_GET['provider'] ?? 'google'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!app_check_rate_limit('seller_registration', 12, 3600)) {
        $error = 'Too many seller registration attempts. Please try again in an hour.';
    } elseif (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid security token. Refresh and try again.';
    } elseif ($currentUser && (string) ($_POST['action'] ?? '') === 'activate_seller') {
        if (app_user_needs_email_verification($currentUser)) {
            $error = 'Please confirm your email before activating Seller Central.';
        } else {
            market_activate_seller_access($pdo, $currentUser);
            redirect_to('seller-central.php');
        }
    } else {
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        if ($name === '' || !$email || strlen($password) < 6) {
            $error = 'Enter your name, valid email, and a password of at least 6 characters.';
        } else {
            try {
                app_ensure_user_verification_schema($pdo);
                $stmt = $pdo->prepare("INSERT INTO users (name, email, password, phone, role, platform_role, account_status) VALUES (?, ?, ?, ?, 'grower', 'seller', 'needs_confirmation')");
                $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $phone]);
                $userId = (int) $pdo->lastInsertId();
                market_activate_seller_access($pdo, ['id' => $userId]);
                app_send_user_verification($pdo, $userId, 'NATCODEV Marketplace Seller');
                $message = 'Seller account created. Check your email and verify before logging in to Seller Central.';
            } catch (Throwable $e) {
                $error = 'Unable to create seller account now. If this email already exists, login and activate seller access.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Seller Registration - NATCODEV Marketplace</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    *{box-sizing:border-box}body{margin:0;font-family:"Segoe UI",Arial,sans-serif;background:#f5faf2;color:#101828}.auth{min-height:100vh;display:grid;grid-template-columns:minmax(0,1fr) 520px}.visual{background:linear-gradient(90deg,rgba(5,45,20,.78),rgba(5,45,20,.24)),url("../assets/public/buyer-marketplace-entry.png") center/cover;color:#fff;padding:52px;display:flex;align-items:flex-end}.visual h1{font-size:3.35rem;line-height:1;margin:0 0 14px}.visual p{font-size:1.16rem;max-width:720px}.panel{background:#fff;padding:42px;display:grid;align-content:center}.brand{display:flex;gap:12px;align-items:center;margin-bottom:24px}.brand img{width:58px;height:58px;border-radius:50%;object-fit:contain}.brand strong{font-size:1.45rem;color:#06451f}label{display:block;font-weight:850;margin-top:12px}input{width:100%;border:1px solid #dfe8d8;border-radius:9px;padding:13px;margin-top:6px}.btn{width:100%;border:0;border-radius:10px;background:#08753a;color:#fff;padding:14px;margin-top:16px;font-weight:950;font-size:1rem;text-align:center;display:inline-flex;justify-content:center;text-decoration:none}.alert{padding:12px;border-radius:10px;margin:10px 0;font-weight:850}.err{background:#fff1f2;color:#b42318}.ok{background:#e8f6ec;color:#06451f}.links{display:flex;justify-content:space-between;gap:12px;margin-top:16px;flex-wrap:wrap}a{color:#06451f;font-weight:900;text-decoration:none}.social{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:12px 0}.social a{border:1px solid #dfe8d8;border-radius:10px;padding:12px;text-align:center}.pass{position:relative}.pass button{position:absolute;right:9px;top:34px;border:0;background:#eef8ef;color:#06451f;border-radius:8px;padding:8px 10px;font-weight:900;cursor:pointer}.fineprint{font-size:.88rem;color:#667085;line-height:1.45}.choice{border:1px solid #dfe8d8;border-radius:10px;padding:12px;margin:10px 0;background:#fbfdf9}.choice strong{display:block;color:#06451f}@media(max-width:900px){.auth{grid-template-columns:1fr}.visual{min-height:340px}.panel{padding:24px}}
  </style>
</head>
<body>
<main class="auth">
  <section class="visual"><div><h1>Open your marketplace store.</h1><p>Seller accounts are for public commerce users who want to list products, manage orders, receive payouts, run promotions, and support buyers. Provider accreditation remains a separate NATCODEV network pathway.</p></div></section>
  <section class="panel">
    <a class="brand" href="index.php"><img src="<?= e(app_primary_logo_url()) ?>" alt="NATCODEV"><strong>NATCODEV Seller</strong></a>
    <?php if ($error): ?><div class="alert err"><?= e($error) ?></div><?php endif; ?>
    <?php if ($message): ?><div class="alert ok"><?= e($message) ?></div><?php endif; ?>

    <?php if ($currentUser): ?>
      <h2>Activate Seller Central</h2>
      <p>You are logged in as <?= e((string) ($currentUser['name'] ?? 'NATCODEV user')) ?>. Add seller access to this account to open a marketplace store.</p>
      <div class="choice"><strong>Marketplace Seller</strong><span class="fineprint">For product sellers, traders, processors, cooperatives, growers selling produce, and general commerce users.</span></div>
      <div class="choice"><strong>Provider Accreditation</strong><span class="fineprint">For input/service providers seeking NATCODEV accreditation and network recognition.</span></div>
      <form method="post"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="activate_seller"><button class="btn">Activate Seller Central</button></form>
      <div class="links"><a href="seller-central.php">Open Seller Central</a><a href="../provider/index.php">Provider accreditation instead</a><a href="logout.php">Logout</a></div>
    <?php else: ?>
      <h2>Create seller account</h2>
      <p>Use this for a public seller account. You can create your store profile after email verification.</p>
      <?= app_social_buttons('seller', 'market/seller-central.php') ?>
      <form method="post">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <label>Name<input name="name" required></label>
        <label>Email<input type="email" name="email" required></label>
        <label>Phone<input name="phone" placeholder="+234..."></label>
        <label class="pass">Password<input id="seller-password" type="password" name="password" minlength="6" required><button type="button" data-toggle-password="seller-password">Show</button></label>
        <button class="btn">Register as Seller</button>
      </form>
      <p class="fineprint">Your seller account unlocks Seller Central, store setup, products, orders, wallet payouts, promotions, and seller support after email verification.</p>
      <div class="links"><a href="seller-login.php">Already have account?</a><a href="seller-join.php">Compare seller/provider</a><a href="index.php">Browse marketplace</a></div>
    <?php endif; ?>
  </section>
</main>
<script>
document.querySelectorAll('[data-toggle-password]').forEach(function(button){button.addEventListener('click',function(){var input=document.getElementById(button.getAttribute('data-toggle-password'));var visible=input.type==='text';input.type=visible?'password':'text';button.textContent=visible?'Show':'Hide';});});
</script>
</body>
</html>