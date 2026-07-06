<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$pdo = db();
$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$next = trim(str_replace(["\0", '\\'], ['', '/'], (string) ($_GET['next'] ?? $_POST['next'] ?? 'login.php')));
if ($next === '' || preg_match('/^[a-z][a-z0-9+.-]*:/i', $next) || str_starts_with($next, '//') || str_starts_with($next, '/')) {
    $next = 'login.php';
}
while (str_starts_with($next, '../')) {
    $next = substr($next, 3);
}

if ($token === '' || !preg_match('/^[a-f0-9]{64}$/i', $token)) {
    http_response_code(422);
    $invalid = true;
} else {
    app_add_column_if_missing($pdo, 'users', 'password_reset_token', 'VARCHAR(64) NULL');
    app_add_column_if_missing($pdo, 'users', 'password_reset_expires', 'DATETIME NULL');
    $stmt = $pdo->prepare('SELECT id, email FROM users WHERE password_reset_token = ? AND password_reset_expires > NOW() LIMIT 1');
    $stmt->execute([$token]);
    $resetUser = $stmt->fetch() ?: null;
    $invalid = !$resetUser;
    if ($invalid) {
        http_response_code(422);
    }
}

$error = '';
if (!$invalid && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Please refresh and try again.';
    } else {
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['confirm'] ?? '');
        if ($password !== $confirm || strlen($password) < 8) {
            $error = 'Passwords must match and be at least 8 characters.';
        } else {
            $pdo->prepare('UPDATE users SET password = ?, password_reset_token = NULL, password_reset_expires = NULL WHERE id = ?')->execute([
                password_hash($password, PASSWORD_DEFAULT),
                $resetUser['id'],
            ]);
            redirect_to($next . (str_contains($next, '?') ? '&' : '?') . 'reset=success');
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
  <title>Reset Password - NATCODEV</title>
  <style>
    :root{--green:#075f2a;--leaf:#0b7a3b;--ink:#17231d;--muted:#667085;--line:#dfe8d8;--red:#b42318}*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;background:linear-gradient(180deg,#fffdf8,#f5faf4);font-family:"Segoe UI",Arial,sans-serif;color:var(--ink);padding:24px}.card{width:min(430px,94vw);background:#fff;border:1px solid rgba(16,24,40,.08);border-radius:8px;padding:30px;box-shadow:0 18px 44px rgba(16,24,40,.1)}.brand{display:flex;gap:12px;align-items:center;color:var(--green);font-weight:950;margin-bottom:22px;text-decoration:none}.brand img{width:54px;height:54px;border-radius:50%;object-fit:contain;border:1px solid var(--line);background:#fff}.brand small{display:block;color:var(--muted);font-weight:750}h1{margin:0 0 8px;color:var(--green);font-size:2rem}.lead{margin:0 0 20px;color:var(--muted);line-height:1.55}.err{background:#fff1f2;color:var(--red);border:1px solid #fecdd3;padding:11px 12px;border-radius:7px;margin:12px 0;font-weight:800}label{display:block;font-weight:900;margin:14px 0 7px}.password-field{position:relative;display:block}.password-field input{padding-right:76px}input{width:100%;border:1px solid var(--line);border-radius:7px;padding:13px;font:inherit}input:focus{outline:3px solid rgba(11,122,59,.14);border-color:var(--leaf)}.password-toggle{position:absolute;right:8px;top:50%;transform:translateY(-50%);width:auto;border:0;background:#eef8f0;color:var(--green);border-radius:6px;padding:7px 10px;font-weight:850;cursor:pointer}button.submit{width:100%;border:0;border-radius:7px;background:var(--green);color:#fff;padding:13px;margin-top:16px;font-weight:950;font-size:1rem;cursor:pointer}.links{display:flex;justify-content:space-between;gap:12px;margin-top:18px}.links a{color:var(--green);font-weight:900;text-decoration:none}
  </style>
</head>
<body>
  <main class="card">
    <a class="brand" href="index.php"><img src="<?= e($logo) ?>" alt="NATCODEV"><span>NATCODEV<small>Account Recovery</small></span></a>
    <?php if ($invalid): ?>
      <h1>Link expired</h1>
      <p class="lead">This password reset link is invalid or has expired. Request a new one to continue.</p>
      <div class="links"><a href="forgot-password.php">Request new link</a><a href="support/index.php">Support</a></div>
    <?php else: ?>
      <h1>Create new password</h1>
      <p class="lead">Choose a new password for your NATCODEV account.</p>
      <?php if ($error): ?><div class="err"><?= e($error) ?></div><?php endif; ?>
      <form method="post">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <input type="hidden" name="next" value="<?= e($next) ?>">
        <label>New Password<span class="password-field"><input id="reset_password" type="password" name="password" required minlength="8"><button class="password-toggle" type="button" data-target="reset_password">Show</button></span></label>
        <label>Confirm Password<span class="password-field"><input id="reset_confirm" type="password" name="confirm" required minlength="8"><button class="password-toggle" type="button" data-target="reset_confirm">Show</button></span></label>
        <button class="submit" type="submit">Update password</button>
      </form>
    <?php endif; ?>
  </main>
  <script>
    document.querySelectorAll('.password-toggle').forEach(function(button){button.addEventListener('click',function(){var input=document.getElementById(button.dataset.target);if(!input)return;var show=input.type==='password';input.type=show?'text':'password';button.textContent=show?'Hide':'Show';});});
  </script>
</body>
</html>