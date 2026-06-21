<?php
declare(strict_types=1);
require_once __DIR__ . '/_buyer.php';

$pdo = buyer_boot();
$currentUser = buyer_user($pdo);
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['_csrf'] ?? null)) {
    if ($currentUser && (string) ($_POST['action'] ?? '') === 'activate_buyer') {
        buyer_activate_access($pdo, $currentUser);
        redirect_to('index.php');
    }
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    if ($name === '' || !$email || strlen($password) < 6) {
        $error = 'Enter your name, valid email, and a password of at least 6 characters.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, phone, role, platform_role, account_status) VALUES (?, ?, ?, ?, 'grower', 'buyer', 'active')");
            $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $phone]);
            $userId = (int) $pdo->lastInsertId();
            buyer_activate_access($pdo, ['id' => $userId]);
            session_regenerate_id(true);
            $_SESSION['user_id'] = $userId;
            redirect_to('index.php');
        } catch (Throwable $e) {
            $error = 'Unable to create buyer account now. If this email is already registered, please log in and activate buyer access.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Buyer Registration - NATCODEV</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    *{box-sizing:border-box}body{margin:0;font-family:"Segoe UI",Arial,sans-serif;background:#f4faf2;color:#101828}.auth{min-height:100vh;display:grid;grid-template-columns:minmax(0,1fr) 520px}.visual{background:linear-gradient(90deg,rgba(5,45,20,.72),rgba(5,45,20,.2)),url("../assets/public/buyer-marketplace-entry.png") center/cover;color:#fff;padding:50px;display:flex;align-items:flex-end}.visual h1{font-size:3.5rem;line-height:1;margin:0 0 14px}.visual p{font-size:1.25rem;max-width:680px}.panel{background:#fff;padding:42px;display:grid;align-content:center}.brand{display:flex;gap:12px;align-items:center;margin-bottom:24px}.brand img{width:58px;height:58px;border-radius:50%}.brand strong{font-size:1.6rem;color:#06451f}label{display:block;font-weight:850;margin-top:12px}input{width:100%;border:1px solid #dfe8d8;border-radius:9px;padding:13px;margin-top:6px}.btn{width:100%;border:0;border-radius:10px;background:#08753a;color:#fff;padding:14px;margin-top:16px;font-weight:950;font-size:1rem}.alert{padding:12px;border-radius:10px;margin:10px 0;font-weight:850}.err{background:#fff1f2;color:#b42318}.ok{background:#e8f6ec;color:#06451f}.links{display:flex;justify-content:space-between;margin-top:16px}a{color:#06451f;font-weight:900;text-decoration:none}@media(max-width:900px){.auth{grid-template-columns:1fr}.visual{min-height:360px}.panel{padding:24px}}
    .pass{position:relative}.pass button{position:absolute;right:9px;top:34px;border:0;background:#eef8ef;color:#06451f;border-radius:8px;padding:8px 10px;font-weight:900;cursor:pointer}.fineprint{font-size:.88rem;color:#667085;line-height:1.45}
  </style>
</head>
<body>
<main class="auth">
  <section class="visual"><div><h1>Buyer access made easy.</h1><p>Shop the NATCODEV marketplace, track orders, join Academy courses, and complete your profile only when you need more services.</p></div></section>
  <section class="panel">
    <a class="brand" href="../index.php"><img src="<?= e(app_primary_logo_url()) ?>" alt="NATCODEV"><strong>NATCODEV Buyer</strong></a>
    <?php if ($currentUser && !buyer_has_access($pdo, $currentUser)): ?>
      <h2>Activate buyer access</h2>
      <p>You are logged in as <?= e((string) $currentUser['name']) ?>. Add Buyer access to this account so you can shop, track orders, use wallet finance, and contact buyer support.</p>
      <?php if ($error): ?><div class="alert err"><?= e($error) ?></div><?php endif; ?>
      <form method="post">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="activate_buyer">
        <button class="btn">Activate Buyer Workspace</button>
      </form>
      <div class="links"><a href="../academy/dashboard.php">Back to Academy</a><a href="../dashboard/logout.php">Logout</a></div>
    <?php elseif ($currentUser && buyer_has_access($pdo, $currentUser)): ?>
      <h2>Buyer access active</h2>
      <p>Your account already has buyer workspace access.</p>
      <a class="btn" href="index.php">Open Buyer Dashboard</a>
    <?php else: ?>
    <h2>Create buyer account</h2>
    <p>Quick registration. More profile details can be added later.</p>
    <?php if ($error): ?><div class="alert err"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <label>Name<input name="name" required></label>
      <label>Email<input type="email" name="email" required></label>
      <label>Phone<input name="phone" placeholder="+234..."></label>
      <label class="pass">Password<input id="buyer-password" type="password" name="password" minlength="6" required><button type="button" data-toggle-password="buyer-password">Show</button></label>
      <button class="btn">Register as Buyer</button>
    </form>
    <p class="fineprint">Your buyer account unlocks order tracking, wallet finance, private buyer support, and profile-managed delivery details.</p>
    <div class="links"><a href="login.php">Already have account?</a><a href="../market/index.php">Browse first</a></div>
    <?php endif; ?>
  </section>
</main>
<script>
document.querySelectorAll('[data-toggle-password]').forEach(function(button){
  button.addEventListener('click', function(){
    var input = document.getElementById(button.getAttribute('data-toggle-password'));
    var visible = input.type === 'text';
    input.type = visible ? 'password' : 'text';
    button.textContent = visible ? 'Show' : 'Hide';
  });
});
</script>
</body>
</html>
