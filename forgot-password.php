<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$pdo = db();
$message = '';
$error = '';
$prefillEmail = filter_var(trim((string) ($_GET['email'] ?? '')), FILTER_VALIDATE_EMAIL) ?: '';
$next = trim(str_replace(["\0", '\\'], ['', '/'], (string) ($_GET['next'] ?? $_POST['next'] ?? '')));
if ($next === '' || preg_match('/^[a-z][a-z0-9+.-]*:/i', $next) || str_starts_with($next, '//') || str_starts_with($next, '/')) {
    $next = 'login.php';
}
while (str_starts_with($next, '../')) {
    $next = substr($next, 3);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Please refresh and try again.';
    } elseif (!app_check_rate_limit('forgot_password', 5, 900)) {
        $error = 'Too many reset requests. Please try again later.';
    } else {
        $email = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
        if (!$email) {
            $error = 'Enter a valid email address.';
        } else {
            app_add_column_if_missing($pdo, 'users', 'password_reset_token', 'VARCHAR(64) NULL');
            app_add_column_if_missing($pdo, 'users', 'password_reset_expires', 'DATETIME NULL');

            $stmt = $pdo->prepare('SELECT id, name FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([(string) $email]);
            $user = $stmt->fetch();

            if ($user) {
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                $pdo->prepare('UPDATE users SET password_reset_token = ?, password_reset_expires = ? WHERE id = ?')->execute([$token, $expires, $user['id']]);
                $resetUrl = app_base_url() . '/reset-password.php?token=' . urlencode($token) . '&next=' . urlencode($next);
                $name = trim((string) ($user['name'] ?? 'NATCODEV User'));
                $plain = "Dear {$name},\n\nUse this secure link to reset your NATCODEV password:\n{$resetUrl}\n\nThis link expires in 1 hour. If you did not request this, ignore this email.\n\nThe NATCODEV Team";
                $html = '<p>Dear <strong>' . e($name) . '</strong>,</p><p>Use this secure link to reset your NATCODEV password.</p><p><a href="' . e($resetUrl) . '" style="display:inline-block;padding:10px 18px;background:#075f2a;color:#fff;text-decoration:none;border-radius:6px;">Reset Password</a></p><p>This link expires in 1 hour.</p>';
                app_send_mail((string) $email, 'NATCODEV Password Reset', $plain, $html);
            }
            $message = 'If that email is registered, a password reset link has been sent.';
            $prefillEmail = (string) $email;
        }
    }
}
$logo = app_primary_logo_url();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Forgot Password - NATCODEV</title>
  <style>
    :root{--green:#075f2a;--leaf:#0b7a3b;--ink:#17231d;--muted:#667085;--line:#dfe8d8;--soft:#f6faf4;--red:#b42318}*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;background:linear-gradient(180deg,#fffdf8,#f5faf4);font-family:"Segoe UI",Arial,sans-serif;color:var(--ink);padding:24px}.card{width:min(430px,94vw);background:#fff;border:1px solid rgba(16,24,40,.08);border-radius:8px;padding:30px;box-shadow:0 18px 44px rgba(16,24,40,.1)}.brand{display:flex;gap:12px;align-items:center;color:var(--green);font-weight:950;margin-bottom:22px;text-decoration:none}.brand img{width:54px;height:54px;border-radius:50%;object-fit:contain;border:1px solid var(--line);background:#fff}.brand small{display:block;color:var(--muted);font-weight:750}h1{margin:0 0 8px;color:var(--green);font-size:2rem}.lead{margin:0 0 20px;color:var(--muted);line-height:1.55}.notice{padding:11px 12px;border-radius:7px;margin:12px 0;font-weight:800}.ok{background:#e8f6ec;color:var(--green);border:1px solid #bfe5c8}.err{background:#fff1f2;color:var(--red);border:1px solid #fecdd3}label{display:block;font-weight:900;margin:14px 0 7px}input{width:100%;border:1px solid var(--line);border-radius:7px;padding:13px;font:inherit}input:focus{outline:3px solid rgba(11,122,59,.14);border-color:var(--leaf)}button{width:100%;border:0;border-radius:7px;background:var(--green);color:#fff;padding:13px;margin-top:16px;font-weight:950;font-size:1rem;cursor:pointer}.links{display:flex;justify-content:space-between;gap:12px;margin-top:18px}.links a{color:var(--green);font-weight:900;text-decoration:none}
  </style>
</head>
<body>
  <main class="card">
    <a class="brand" href="index.php"><img src="<?= e($logo) ?>" alt="NATCODEV"><span>NATCODEV<small>Account Recovery</small></span></a>
    <h1>Reset your password</h1>
    <p class="lead">Enter your registered email. We will send a secure reset link that works for Academy, marketplace, provider, grower, and staff accounts.</p>
    <?php if ($message): ?><div class="notice ok"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="notice err"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="next" value="<?= e($next) ?>">
      <label>Email<input type="email" name="email" value="<?= e($prefillEmail) ?>" placeholder="you@example.com" required></label>
      <button type="submit">Send reset link</button>
    </form>
    <div class="links"><a href="<?= e($next ?: 'login.php') ?>">Back to login</a><a href="support/index.php">Support</a></div>
  </main>
</body>
</html>