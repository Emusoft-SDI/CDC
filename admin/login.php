<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/admin-layout.php';

if (admin_session_is_authenticated(db())) {
    redirect_to('admin.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid security token.';
    } elseif (!app_check_rate_limit('admin_login', 5, 900)) {
        $error = 'Too many login attempts. Try again later.';
    } elseif (admin_password_is_valid((string) ($_POST['password'] ?? ''))) {
        session_start();
        session_regenerate_id(true);
        $_SESSION['admin_authenticated'] = true;
        $_SESSION['admin'] = true;
        redirect_to('admin.php');
    } else {
        $error = 'Invalid admin password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NATCODEV Admin Login</title>
    <style>
        :root { --primary:#1a5276; --green:#1f8a55; --green-dark:#166b41; --ink:#1f2937; --muted:#667085; --line:#d8e2dc; }
        * { box-sizing:border-box; }
        body { font-family:"Segoe UI", Tahoma, Geneva, Verdana, sans-serif; background:linear-gradient(135deg, rgba(26,82,118,.09), rgba(31,138,85,.12)), #f5f8f6; display:flex; min-height:100vh; align-items:center; justify-content:center; margin:0; padding:24px; color:var(--ink); }
        .login-shell { width:100%; max-width:420px; }
        .brand { color:var(--primary); font-weight:800; text-align:center; margin-bottom:18px; letter-spacing:.02em; display:flex; align-items:center; justify-content:center; gap:10px; }
        .brand img { width:58px; height:58px; object-fit:contain; border-radius:50%; border:1px solid var(--line); background:#fff; }
        form { width:100%; background:#fff; padding:34px; border-radius:8px; border:1px solid rgba(16,24,40,.08); box-shadow:0 18px 44px rgba(16,24,40,.12); }
        h1 { margin:0 0 8px; color:var(--primary); font-size:28px; line-height:1.15; }
        .lead { margin:0 0 22px; color:var(--muted); }
        input, button { width:100%; box-sizing:border-box; padding:13px; margin-top:12px; border-radius:5px; border:1px solid var(--line); font-size:1rem; }
        input:focus { border-color:var(--green); box-shadow:0 0 0 3px rgba(31,138,85,.14); outline:none; }
        button { background:var(--green); color:#fff; border:0; font-weight:800; cursor:pointer; box-shadow:0 10px 24px rgba(31,138,85,.22); }
        button:hover { background:var(--green-dark); }
        .error { color:#a32020; background:#fff3f3; border:1px solid #ffd2d2; padding:10px 12px; border-radius:5px; }
        .home-link { display:inline-block; margin-top:18px; color:var(--green-dark); text-decoration:none; font-weight:800; }
        .password-field { position:relative; }
        .password-field input { padding-right:76px; }
        .password-toggle { position:absolute; right:8px; top:50%; transform:translateY(-50%); width:auto; margin:0; padding:7px 9px; border:0; background:#eef7f1; color:var(--green-dark); font-size:.82rem; box-shadow:none; }
        @media (max-width:520px) { form { padding:26px 18px; } }
    </style>
    <link rel="stylesheet" href="../assets/css/natcodev-ui.css?v=20260530">
</head>
<body>
    <main class="login-shell">
      <div class="brand"><img src="<?= e(app_primary_logo_url()) ?>" alt="NATCODEV"><span>NATCODEV Registry</span></div>
      <form method="post">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <h1>Admin Login</h1>
        <p class="lead">Access application review, exports, reporting, and registry operations.</p>
        <div style="background:#eef7f1; border:1px solid #d8e2dc; padding:12px; border-radius:6px; margin-bottom:18px; font-size:.92rem; color:#166b41;">
          <strong>Staff & Coordinators:</strong> Sign in via the <a href="../login.php" style="color:#1f8a55; text-decoration:underline;">Main Platform Login</a> using your registered account.
        </div>
        <?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>
        <div class="password-field">
          <input id="admin_password" type="password" name="password" placeholder="Admin password" required autofocus>
          <button class="password-toggle" type="button" data-target="admin_password" aria-pressed="false">Show</button>
        </div>
        <button type="submit">Login</button>
        <a class="home-link" href="../index.php">Back to home</a>
      </form>
    </main>
    <script>
        document.querySelectorAll('.password-toggle').forEach((button) => {
          button.addEventListener('click', () => {
            const input = document.getElementById(button.dataset.target || '');
            if (!input) return;
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.textContent = show ? 'Hide' : 'Show';
            button.setAttribute('aria-pressed', show ? 'true' : 'false');
          });
        });
    </script>
</body>
</html>
