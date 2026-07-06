<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/support.php';
require_once __DIR__ . '/../lib/user-workspaces.php';
require_once __DIR__ . '/../lib/workspace-account.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$pdo = db();
support_ensure_schema($pdo);
$user = current_user($pdo);
if (!$user || !app_user_has_any_role($pdo, $user, ['support_agent', 'admin', 'super_admin'])) {
    redirect_to('../login.php?next=' . urlencode('support/profile.php'));
}

$msg = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Security session expired. Refresh and try again.';
    } else {
        try {
            $action = (string) ($_POST['action'] ?? 'account_profile');
            if ($action === 'account_password') {
                workspace_account_change_password($pdo, (int) $user['id'], (string) ($_POST['current_password'] ?? ''), (string) ($_POST['new_password'] ?? ''), (string) ($_POST['confirm_password'] ?? ''));
                $msg = 'Password changed.';
            } else {
                workspace_account_update_profile($pdo, (int) $user['id'], $_POST);
                $msg = 'Profile updated.';
            }
            $user = current_user($pdo) ?: $user;
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$logo = app_primary_logo_url();
$picture = trim((string) ($user['profile_picture'] ?? ''));
$avatar = $picture !== '' ? '../' . ltrim($picture, '/') : $logo;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Support Agent Profile - NATCODEV</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    :root{--green:#075f2a;--deep:#053b1c;--line:#dfe8d8;--bg:#f6faf4;--ink:#101828;--muted:#667085;--red:#b42318}*{box-sizing:border-box}body{margin:0;background:var(--bg);font-family:"Segoe UI",Arial,sans-serif;color:var(--ink)}a{text-decoration:none;color:inherit}.shell{display:grid;grid-template-columns:278px 1fr;min-height:100vh}.side{background:linear-gradient(180deg,#06451f,#092f22);color:#fff;padding:20px;position:sticky;top:0;height:100vh;overflow:auto}.brand{display:flex;gap:12px;align-items:center;padding-bottom:16px;border-bottom:1px solid rgba(255,255,255,.16)}.brand img{width:52px;height:52px;border-radius:50%;background:#fff}.brand strong{display:block;font-size:1.15rem}.brand small{color:#cdeed9}.agent{margin:18px 0;padding:13px;border:1px solid rgba(255,255,255,.16);border-radius:8px;background:rgba(255,255,255,.08);display:flex;gap:10px;align-items:center}.agent img{width:48px;height:48px;border-radius:50%;object-fit:cover;background:#fff}.nav{display:grid;gap:8px}.nav a{display:flex;align-items:center;gap:10px;padding:11px;border-radius:8px;color:#fff;font-weight:850}.nav a.active,.nav a:hover{background:#118b42}.main{min-width:0}.top{height:72px;background:#fff;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;gap:12px;padding:0 24px;position:sticky;top:0;z-index:5}.chip{border:1px solid var(--line);border-radius:8px;background:#fff;padding:9px 11px;font-weight:850}.content{padding:24px}.page-head{display:flex;justify-content:space-between;gap:16px;align-items:flex-end;margin-bottom:16px}.page-head h1{margin:0;color:#062b17}.page-head p{margin:5px 0 0;color:var(--muted)}.grid{display:grid;grid-template-columns:repeat(12,1fr);gap:16px}.card{background:#fff;border:1px solid var(--line);border-radius:8px;box-shadow:0 12px 32px rgba(16,24,40,.06);padding:16px}.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.wide{grid-column:1/-1}label{font-weight:850}input{width:100%;border:1px solid var(--line);border-radius:8px;padding:11px;margin-top:6px}.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;border:1px solid var(--green);background:var(--green);color:#fff;border-radius:8px;padding:10px 14px;font-weight:900;cursor:pointer}.btn.light{background:#fff;color:var(--green)}.alert{padding:11px;border-radius:8px;margin-bottom:12px}.alert.ok{background:#ecfdf3;color:#067647}.alert.bad{background:#fff1f2;color:#b42318}@media(max-width:980px){.shell{grid-template-columns:1fr}.side{position:relative;height:auto}.grid{grid-template-columns:1fr}.top,.page-head{align-items:flex-start;flex-direction:column;height:auto;padding:16px}}
  </style>
</head>
<body>
<div class="shell">
  <aside class="side">
    <a class="brand" href="agent.php"><img src="<?= e($logo) ?>" alt="NATCODEV"><span><strong>NATCODEV</strong><small>Support Agent Desk</small></span></a>
    <div class="agent"><img src="<?= e($avatar) ?>" alt=""><span><strong><?= e((string) ($user['name'] ?? 'Support Agent')) ?></strong><br><small><?= e((string) ($user['email'] ?? '')) ?></small></span></div>
    <nav class="nav"><a href="agent.php"><i class="fa-solid fa-ticket"></i> My Tickets</a><a class="active" href="profile.php"><i class="fa-solid fa-user"></i> Profile</a><a href="profile.php#account"><i class="fa-solid fa-lock"></i> Account</a><a href="../index.php"><i class="fa-solid fa-house"></i> NATCODEV Home</a><a href="../logout.php?next=support%2Fagent.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></nav>
  </aside>
  <main class="main">
    <header class="top"><span class="chip"><i class="fa-solid fa-user-shield"></i> Support Agent Profile</span><a class="chip" href="agent.php">Tickets</a><a class="chip" href="../logout.php?next=support%2Fagent.php">Logout</a></header>
    <section class="content">
      <div class="page-head"><div><h1>Profile & Security</h1><p>Manage your support identity, profile picture, account details, password, and safe exit.</p></div><a class="btn light" href="agent.php">Back To Tickets</a></div>
      <?php if ($msg): ?><div class="alert ok"><?= e($msg) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert bad"><?= e($error) ?></div><?php endif; ?>
      <div class="grid">
        <?php workspace_account_render_profile_forms($user, 'support', 'Support Agent Profile', 'Change Password'); ?>
        <section class="card wide"><h2>Workspace Exit</h2><p><a class="btn" href="agent.php">Back to Support Desk</a> <a class="btn light" href="../index.php">NATCODEV Home</a> <a class="btn light" href="../logout.php?next=support%2Fagent.php">Logout</a></p></section>
      </div>
    </section>
  </main>
</div>
</body>
</html>