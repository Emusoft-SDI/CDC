<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$pdo = db();
app_ensure_core_schema($pdo);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Please refresh and try again.';
    } else {
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $fields = ['id', 'name', 'email', 'password', 'role'];
        if (app_column_exists($pdo, 'users', 'platform_role')) {
            $fields[] = 'platform_role';
        }
        $stmt = $pdo->prepare("SELECT " . implode(', ', $fields) . " FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        $role = (string) ($user['role'] ?? '');
        $platformRole = (string) ($user['platform_role'] ?? '');
        if ($user && password_verify($password, (string) $user['password']) && (in_array($role, ['field_agent', 'admin'], true) || in_array($platformRole, ['field_agent', 'admin'], true))) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['id'];
            header('Location: index.php');
            exit;
        }
        $error = 'Invalid field-agent email, password, or access role.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Field Agent Login - NATCODEV</title>
  <script src="https://unpkg.com/lucide@latest"></script>
  <style>
    :root{--green:#007a3d;--deep:#003f25;--ink:#07162f;--muted:#64748b;--line:#e2e8f0;--gold:#d49400}
    *{box-sizing:border-box}body{margin:0;font-family:Inter,"Segoe UI",Arial,sans-serif;color:var(--ink);background:#f6fbf7}.login-shell{min-height:100vh;display:grid;grid-template-columns:1.12fr .88fr}.visual{position:relative;background:linear-gradient(90deg,rgba(0,63,37,.9),rgba(0,63,37,.35)),url('../assets/public/field-agent-operations-hero.png') center/cover;display:flex;align-items:end;padding:56px;color:#fff}.visual h1{font-size:clamp(2.4rem,5vw,4.6rem);line-height:1.02;margin:0 0 14px}.visual p{font-size:1.15rem;max-width:680px}.chips{display:flex;gap:12px;flex-wrap:wrap;margin-top:24px}.chip{display:inline-flex;gap:8px;align-items:center;background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.25);border-radius:999px;padding:10px 14px;font-weight:800}.panel{display:grid;align-content:center;padding:48px;position:relative}.brand{display:flex;align-items:center;gap:12px;text-decoration:none;color:var(--green);font-weight:950;margin-bottom:38px}.brand img{width:62px;height:62px;border-radius:50%;object-fit:contain}.card{background:#fff;border:1px solid var(--line);border-radius:18px;box-shadow:0 24px 64px rgba(15,23,42,.12);padding:32px}.card h2{font-size:2rem;margin:0 0 8px}.muted{color:var(--muted)}label{display:block;font-weight:900;margin:18px 0 8px}input{width:100%;padding:14px;border:1px solid var(--line);border-radius:10px;font:inherit}.field{position:relative}.toggle{position:absolute;right:10px;bottom:8px;border:0;background:#f1f5f9;border-radius:8px;padding:8px;color:var(--ink)}.btn{width:100%;display:flex;align-items:center;justify-content:center;gap:10px;margin-top:20px;padding:14px;border-radius:10px;border:1px solid var(--green);background:var(--green);color:#fff;font-weight:950;font-size:1rem;text-decoration:none}.social{display:grid;grid-template-columns:repeat(2,1fr);gap:10px;margin-top:14px}.social button{padding:12px;border-radius:10px;border:1px solid var(--line);background:#fff;font-weight:900}.alert{padding:12px;border-radius:10px;background:#fff3f3;color:#a32020;border:1px solid #ffd2d2}.links{display:flex;justify-content:space-between;gap:14px;margin-top:18px}.links a{color:var(--green);font-weight:900;text-decoration:none}@media(max-width:900px){.login-shell{grid-template-columns:1fr}.visual{min-height:420px}.panel{padding:24px}.social{grid-template-columns:1fr}}
  </style>
</head>
<body>
<main class="login-shell">
  <section class="visual">
    <div>
      <h1>Field Operations Login</h1>
      <p>Verify growers, collect evidence, sync offline records, and keep the coconut registry trusted from the field.</p>
      <div class="chips"><span class="chip"><i data-lucide="wifi-off"></i> Offline-ready</span><span class="chip"><i data-lucide="map-pin"></i> GPS visits</span><span class="chip"><i data-lucide="shield-check"></i> Verification work</span></div>
    </div>
  </section>
  <section class="panel">
    <a class="brand" href="../index.php"><img src="<?= e(app_primary_logo_url()) ?>" alt="NATCODEV"><span>NATCODEV<br><small>National Coconut Development & Propagation Initiative</small></span></a>
    <div class="card">
      <h2>Welcome back</h2>
      <p class="muted">Use your approved field-agent account. New applicants should apply through recruitment first.</p>
      <?php if ($error): ?><div class="alert"><?= e($error) ?></div><?php endif; ?>
      <form method="post">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <label>Email Address</label>
        <input type="email" name="email" autocomplete="email" required>
        <label>Password</label>
        <div class="field">
          <input id="password" type="password" name="password" autocomplete="current-password" required>
          <button class="toggle" type="button" onclick="const p=document.getElementById('password');p.type=p.type==='password'?'text':'password'"><i data-lucide="eye"></i></button>
        </div>
        <button class="btn" type="submit"><i data-lucide="log-in"></i> Sign In</button>
      </form>
      <div class="social">
        <button type="button"><i data-lucide="mail"></i> Google-ready</button>
        <button type="button"><i data-lucide="badge-check"></i> Staff SSO-ready</button>
      </div>
      <div class="links"><a href="../recruitment.php">Apply as Field Agent</a><a href="../dashboard/forgot-password.php">Forgot password?</a></div>
    </div>
  </section>
</main>
<script>if(window.lucide){lucide.createIcons();}</script>
</body>
</html>
