<?php
declare(strict_types=1);
require_once __DIR__ . '/_coordination.php';
require_once __DIR__ . '/../lib/otp-delivery.php';

$pdo = coord_pdo();
$error = '';
$notice = isset($_GET['message']) ? 'You have logged out of the coordination channel. Login again or return to NATCODEV.' : '';

if (coord_current_user($pdo)) {
    redirect_to(coord_login_home(coord_current_user($pdo)));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!app_check_rate_limit('coordination_login', 8, 900)) {
        $error = 'Too many coordination login attempts. Please try again in 15 minutes.';
    } elseif (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Please refresh and try again.';
    } else {
        $email = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
        $password = (string) ($_POST['password'] ?? '');
        if ($email && $password !== '') {
            $fields = ['id','name','email','password','role','platform_role','account_status'];
            if (app_column_exists($pdo, 'users', 'email_verified_at')) { $fields[] = 'email_verified_at'; }
            $stmt = $pdo->prepare('SELECT ' . implode(',', $fields) . ' FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch() ?: null;
            $isAllowedRole = $user ? app_user_has_any_role($pdo, $user, ['state_coordinator', 'national_coordinator', 'admin', 'super_admin']) : false;
            if ($user && $isAllowedRole && password_verify($password, (string) $user['password'])) {
                if (app_user_needs_email_verification($user)) {
                    $error = 'Confirm this coordinator email before entering the coordination channel.';
                } elseif (!in_array((string) ($user['account_status'] ?? 'active'), ['active','verified'], true)) {
                    $error = 'This coordination account is not active.';
                } else {
                    $otpStart = otp_begin_email_login_challenge($pdo, $user, 'coordination/' . coord_login_home($user));
                    if ($otpStart['ok']) {
                        redirect_to('../verify-otp.php');
                    }
                    $error = (string) $otpStart['message'];
                }
            }
        }
        if ($error === '') { $error = 'Invalid coordinator email, password, or access role.'; }
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Coordination Login - NATCODEV</title><style>:root{--green:#075f2a;--line:#dfe8d8;--red:#b42318}*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f4faf2;font-family:"Segoe UI",Arial,sans-serif}.card{width:min(460px,94vw);background:#fff;border:1px solid var(--line);border-radius:8px;padding:30px;box-shadow:0 20px 54px rgba(16,24,40,.12)}.brand{display:flex;gap:12px;align-items:center;color:var(--green);font-weight:950;text-decoration:none}.brand img{width:58px;height:58px;border-radius:50%}label{display:block;font-weight:900;margin-top:14px}.pass{position:relative}.pass input{padding-right:76px}input{width:100%;border:1px solid var(--line);border-radius:8px;padding:13px;margin-top:6px}.toggle{position:absolute;right:8px;bottom:7px;border:0;border-radius:7px;background:#eef8ef;color:var(--green);padding:8px 10px;font-weight:900}.btn{width:100%;border:0;border-radius:8px;background:var(--green);color:#fff;padding:14px;margin-top:16px;font-weight:950}.err{background:#fff1f2;color:var(--red);padding:11px;border-radius:8px;margin:12px 0;font-weight:850}.ok{background:#ecfdf3;color:#067647;padding:11px;border-radius:8px;margin:12px 0;font-weight:850}.links{display:flex;justify-content:space-between;margin-top:16px}.links a{color:var(--green);font-weight:900;text-decoration:none}</style></head><body><main class="card"><a class="brand" href="../index.php"><img src="<?= e(app_primary_logo_url()) ?>" alt="NATCODEV"><span>NATCODEV<br><small>Coordination Channel</small></span></a><h1>Coordinator Login</h1><p>State and national coordinators enter here as leadership stakeholders.</p><?php if ($notice): ?><div class="ok"><?= e($notice) ?></div><?php endif; ?><?php if ($error): ?><div class="err"><?= e($error) ?></div><?php endif; ?><form method="post"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><label>Email<input type="email" name="email" required></label><label class="pass">Password<input id="coord-password" type="password" name="password" required><button class="toggle" type="button" data-toggle-password="coord-password">Show</button></label><button class="btn">Enter Coordination</button></form><div class="links"><a href="../forgot-password.php?next=coordination/login.php">Forgot password?</a><a href="../index.php">Main site</a></div><div class="links"><a href="../index.php">NATCODEV Home</a><a href="../support/index.php">Support</a></div></main><script>document.querySelectorAll('[data-toggle-password]').forEach(function(button){button.addEventListener('click',function(){var input=document.getElementById(button.getAttribute('data-toggle-password'));var show=input.type==='password';input.type=show?'text':'password';button.textContent=show?'Hide':'Show';});});</script></body></html>
