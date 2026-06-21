<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

session_start();

$nextParam = (string) ($_GET['next'] ?? $_POST['next'] ?? '');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && str_contains(str_replace('\\', '/', $nextParam), 'support/index.php')) {
    redirect_to('support/login.php?next=index.php');
}

function dashboard_login_next(string $next): string
{
    $next = trim(str_replace(["\0", '\\'], ['', '/'], $next));
    if ($next === '' || preg_match('/^[a-z][a-z0-9+.-]*:/i', $next) || str_starts_with($next, '//')) {
        return '';
    }
    if ($next[0] === '/') {
        return '';
    }
    while (str_starts_with($next, '../')) {
        $next = substr($next, 3);
    }
    return $next;
}

$error = '';
$next = dashboard_login_next($nextParam);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!app_check_rate_limit('login', 10, 600)) {
        $error = 'Too many login attempts. Please try again in 10 minutes.';
    } elseif (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Please refresh the page and try again.';
    } else {
        $email = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
        $password = (string) ($_POST['password'] ?? '');

        if ($email && $password !== '') {
            try {
                $pdo = db();
                app_ensure_core_schema($pdo);
                $stmt = $pdo->prepare("SELECT id, password, role, platform_role, application_id, is_agronomist, is_extensionist FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, (string) $user['password'])) {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = (int) $user['id'];
                    $isLearnerOnly = (string) ($user['platform_role'] ?? '') === 'learner' && empty($user['application_id']);
                    if ($isLearnerOnly && ($next === '' || !str_contains(str_replace('\\', '/', $next), 'academy/'))) {
                        redirect_to('academy/dashboard.php?screen=learning');
                    }
                    if ($next !== '') {
                        redirect_to($next);
                    }
                    $isStaffUser = ($user['role'] ?? '') === 'field_agent'
                        && (
                            empty($user['application_id'])
                            || (int) ($user['is_agronomist'] ?? 0) === 1
                            || (int) ($user['is_extensionist'] ?? 0) === 1
                        );
                    if ($isStaffUser) {
                        redirect_to('field-agent/');
                    }
                    if (($user['role'] ?? '') === 'admin' || in_array($user['platform_role'] ?? '', ['national_coordinator', 'state_coordinator'], true)) {
                        redirect_to('admin/index.php');
                    }
                    redirect_to('dashboard/index.php');
                }
            } catch (Throwable $e) {
                error_log('Dashboard login error: ' . $e->getMessage());
            }
        }

        $error = 'Invalid credentials.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>NATCODEV Platform Login</title>
  <style>
    :root{--green:#075f2a;--deep:#043719;--leaf:#0b7a3b;--mint:#ecf8ef;--line:#dde7df;--ink:#101828;--muted:#667085;--panel:#fff;--gold:#c69320;--red:#b42318}
    *{box-sizing:border-box}body{margin:0;min-height:100vh;background:#f5f8f2;color:var(--ink);font-family:"Segoe UI",Arial,sans-serif}a{text-decoration:none;color:inherit}
    .page{min-height:100vh;display:grid;grid-template-rows:auto 1fr}.top{background:rgba(255,255,255,.96);border-bottom:1px solid rgba(16,24,40,.08);box-shadow:0 10px 30px rgba(16,24,40,.06)}
    .bar{max-width:1180px;margin:0 auto;padding:14px 22px;display:flex;align-items:center;justify-content:space-between;gap:16px}.brand{display:flex;align-items:center;gap:12px;color:var(--green);font-weight:950}.brand img{width:52px;height:52px;border-radius:50%;border:1px solid var(--line);object-fit:contain;background:#fff;padding:3px}.brand small{display:block;color:#5d6c62;font-size:.78rem;margin-top:2px}
    .nav{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.nav a{border:1px solid var(--line);border-radius:8px;background:#fff;padding:9px 12px;font-weight:850;color:#28342d}.nav a.primary{background:var(--green);border-color:var(--green);color:#fff}.nav a:hover{background:var(--mint);color:var(--green)}
    .shell{max-width:1180px;width:100%;margin:0 auto;padding:34px 22px;display:grid;grid-template-columns:minmax(0,1.06fr) minmax(360px,.74fr);gap:26px;align-items:center}
    .story{min-height:610px;border-radius:8px;overflow:hidden;position:relative;background:linear-gradient(135deg,rgba(4,55,25,.96),rgba(7,95,42,.78)),url("images/26.jpg") center/cover no-repeat;color:#fff;padding:34px;display:flex;flex-direction:column;justify-content:space-between;box-shadow:0 24px 60px rgba(16,24,40,.15)}
    .badges{display:flex;gap:10px;flex-wrap:wrap}.badges span{border:1px solid rgba(255,255,255,.24);background:rgba(255,255,255,.13);border-radius:999px;padding:8px 11px;font-weight:850}.story h1{font-size:clamp(2.4rem,5vw,4.65rem);line-height:.98;letter-spacing:0;margin:18px 0 12px;max-width:780px}.story p{max-width:660px;margin:0;color:#e7f8ec;line-height:1.62;font-size:1.03rem}
    .role-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}.role{border:1px solid rgba(255,255,255,.18);background:rgba(255,255,255,.12);border-radius:8px;padding:13px}.role strong{display:block;font-size:1.02rem}.role span{display:block;color:#d8f2df;font-size:.83rem;line-height:1.4;margin-top:5px}
    .card{background:var(--panel);border:1px solid rgba(16,24,40,.09);border-radius:8px;padding:26px;box-shadow:0 22px 54px rgba(16,24,40,.12)}.card h2{margin:0;color:var(--green);font-size:2rem;line-height:1.1}.lead{margin:8px 0 22px;color:var(--muted);line-height:1.55}
    .alert{background:#fff3f3;border:1px solid #ffd2d2;color:var(--red);border-radius:8px;padding:12px 13px;margin-bottom:14px;font-weight:850}label{display:block;font-weight:850;color:#243329;margin:14px 0 7px}input{width:100%;border:1px solid var(--line);border-radius:7px;padding:13px;font:inherit;background:#fff}input:focus{outline:0;border-color:var(--leaf);box-shadow:0 0 0 3px rgba(11,122,59,.14)}
    .password-field{position:relative;display:block}.password-field input{padding-right:78px}.password-toggle{position:absolute;right:8px;top:50%;transform:translateY(-50%);border:0;border-radius:6px;background:#eef8f0;color:var(--green);padding:7px 10px;font-weight:850;cursor:pointer}.password-toggle:hover{background:#dff1e4}
    .submit{width:100%;margin-top:20px;border:0;border-radius:7px;background:var(--green);color:#fff;padding:13px 15px;font:inherit;font-weight:950;cursor:pointer;box-shadow:0 14px 28px rgba(7,95,42,.2)}.submit:hover{background:var(--deep)}
    .links{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:9px;margin-top:16px}.links a{border:1px solid var(--line);border-radius:7px;padding:10px;text-align:center;font-weight:850;color:#344054;background:#fbfdf9}.links a:hover{background:#eef8f0;color:var(--green)}
    .support-note{margin-top:18px;border-top:1px solid var(--line);padding-top:15px;color:var(--muted);line-height:1.5;font-size:.92rem}.support-note a{color:var(--green);font-weight:900}
    @media(max-width:930px){.shell{grid-template-columns:1fr}.story{min-height:430px}.role-grid,.links{grid-template-columns:1fr}.bar{align-items:flex-start;flex-direction:column}.nav{width:100%}}
  </style>
</head>
<body>
<div class="page">
  <header class="top">
    <div class="bar">
      <a class="brand" href="index.php"><img src="<?= e(app_primary_logo_url()) ?>" alt="NATCODEV"><strong>NATCODEV<small>Official Platform Access</small></strong></a>
      <nav class="nav" aria-label="Login navigation">
        <a href="index.php">Main Site</a>
        <a href="support/index.php">Support</a>
        <a href="academy/index.php">Academy</a>
        <a class="primary" href="apply.php">Join NATCODEV</a>
      </nav>
    </div>
  </header>

  <main class="shell">
    <section class="story" aria-label="NATCODEV platform access">
      <div>
        <div class="badges"><span>Grower services</span><span>Academy</span><span>Marketplace</span><span>Operations</span></div>
        <h1>Enter the NATCODEV workspace.</h1>
        <p>Use this official login for approved platform accounts: growers, learners, field teams, coordinators, providers, sellers, and administrators.</p>
      </div>
      <div class="role-grid">
        <div class="role"><strong>Farm & Wallet</strong><span>Profiles, documents, certificates, wallet, and farm records.</span></div>
        <div class="role"><strong>Learning</strong><span>Academy courses, assessments, attendance, and certificates.</span></div>
        <div class="role"><strong>Operations</strong><span>Field work, support desk, marketplace, and coordination tools.</span></div>
      </div>
    </section>

    <section class="card" aria-label="Dashboard login form">
      <h2>Platform Login</h2>
      <p class="lead">Sign in with your registered NATCODEV account. Staff accounts are routed automatically to the right workspace.</p>
      <?php if ($error): ?><div class="alert"><?= e($error) ?></div><?php endif; ?>
      <form method="post">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="next" value="<?= e($next) ?>">
        <label>Email</label>
        <input type="email" name="email" placeholder="you@example.com" required autocomplete="email">
        <label>Password</label>
        <span class="password-field">
          <input id="login_password" type="password" name="password" placeholder="Enter your password" required autocomplete="current-password">
          <button class="password-toggle" type="button" data-target="login_password" aria-pressed="false">Show</button>
        </span>
        <button class="submit" type="submit">Login to Workspace</button>
      </form>
      <div class="links">
        <a href="dashboard/forgot-password.php">Forgot password</a>
        <a href="otp-login.php">Use OTP login</a>
        <a href="index.php">Back to home</a>
        <a href="support/login.php?next=index.php">Track support ticket</a>
      </div>
      <p class="support-note">Support ticket users do not need dashboard access. They can use <a href="support/login.php?next=index.php">support-only ticket tracking</a> without entering the platform.</p>
    </section>
  </main>
</div>
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
