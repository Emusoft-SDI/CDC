<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/support.php';

session_start();

function support_login_next(string $next): string
{
    $next = trim(str_replace(["\0", '\\'], ['', '/'], $next));
    if ($next === '' || preg_match('/^[a-z][a-z0-9+.-]*:/i', $next) || str_starts_with($next, '//')) {
        return 'index.php';
    }
    if (str_contains($next, 'support/index.php')) {
        return 'index.php';
    }
    if ($next[0] === '/') {
        return '../index.php';
    }
    return $next;
}

$next = support_login_next((string) ($_GET['next'] ?? $_POST['next'] ?? 'index.php'));
$error = '';

if (!empty($_SESSION['user_id'])) {
    redirect_to($next);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Please refresh the page and try again.';
    } else {
        $email = trim((string) ($_POST['email'] ?? ''));
        $ticketRef = preg_replace('/[^A-Z0-9-]/i', '', (string) ($_POST['ticket_ref'] ?? ''));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) && $ticketRef !== '') {
            $pdo = db();
            support_ensure_schema($pdo);
            $ticket = support_ticket_by_ref($pdo, $ticketRef);

            if ($ticket && strcasecmp((string) $ticket['requester_email'], $email) === 0) {
                redirect_to('index.php?ticket=' . urlencode((string) $ticket['ticket_ref']) . '&email=' . urlencode($email));
            }
        }

        $error = 'Ticket reference and email do not match.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Support Login - NATCODEV</title>
  <style>
    :root{--green:#075f2a;--deep:#053b1c;--leaf:#0b7a3b;--line:#dce7dd;--ink:#101828;--muted:#667085;--bg:#f4f8f1;--panel:#fff;--gold:#c69320;--red:#b42318}
    *{box-sizing:border-box}body{margin:0;min-height:100vh;background:var(--bg);color:var(--ink);font-family:"Segoe UI",Arial,sans-serif}
    a{color:inherit;text-decoration:none}.page{min-height:100vh;display:grid;grid-template-rows:auto 1fr}
    .top{background:rgba(255,255,255,.96);border-bottom:1px solid rgba(16,24,40,.08);box-shadow:0 10px 28px rgba(16,24,40,.06)}
    .bar{max-width:1180px;margin:0 auto;padding:14px 22px;display:flex;align-items:center;justify-content:space-between;gap:16px}
    .brand{display:flex;align-items:center;gap:12px;color:var(--green);font-weight:950}.brand img{width:50px;height:50px;border-radius:50%;border:1px solid var(--line);background:#fff;object-fit:contain;padding:3px}.brand span{display:block;color:#5b6b60;font-size:.8rem;margin-top:2px}
    .nav{display:flex;align-items:center;gap:8px;flex-wrap:wrap}.nav a{border:1px solid var(--line);border-radius:8px;background:#fff;padding:9px 12px;font-weight:850;color:#243329}.nav a.primary{background:var(--green);border-color:var(--green);color:#fff}.nav a:hover{background:#eef8f0;color:var(--green)}
    .shell{max-width:1180px;width:100%;margin:0 auto;padding:34px 22px;display:grid;grid-template-columns:minmax(0,1.05fr) minmax(360px,.78fr);gap:26px;align-items:center}
    .hero{min-height:590px;border-radius:8px;overflow:hidden;position:relative;background:linear-gradient(135deg,rgba(5,59,28,.94),rgba(7,95,42,.8)),url("../images/26.jpg") center/cover no-repeat;color:#fff;padding:34px;display:flex;flex-direction:column;justify-content:space-between;box-shadow:0 24px 60px rgba(16,24,40,.14)}
    .hero h1{font-size:clamp(2.35rem,5vw,4.6rem);line-height:.98;margin:0;letter-spacing:0}.hero p{max-width:620px;line-height:1.6;color:#e6f7eb;font-size:1.02rem}.hero-badges{display:flex;gap:10px;flex-wrap:wrap}.hero-badges span{background:rgba(255,255,255,.13);border:1px solid rgba(255,255,255,.22);border-radius:999px;padding:8px 11px;font-weight:850}
    .hero-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}.hero-stat{background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.18);border-radius:8px;padding:13px}.hero-stat strong{display:block;font-size:1.45rem}.hero-stat span{display:block;color:#d9f3e1;font-size:.82rem;margin-top:4px}
    .login-card{background:var(--panel);border:1px solid rgba(16,24,40,.09);border-radius:8px;padding:26px;box-shadow:0 22px 54px rgba(16,24,40,.12)}.login-card h2{margin:0;color:var(--green);font-size:2rem}.lead{margin:8px 0 22px;color:var(--muted);line-height:1.55}
    .alert{border-radius:8px;padding:12px 13px;margin-bottom:14px;font-weight:850}.alert.error{background:#fff3f3;color:var(--red);border:1px solid #ffd2d2}
    label{display:block;font-weight:850;color:#243329;margin:14px 0 7px}input{width:100%;border:1px solid var(--line);border-radius:7px;padding:13px 13px;font:inherit;background:#fff}input:focus{outline:0;border-color:var(--leaf);box-shadow:0 0 0 3px rgba(11,122,59,.14)}
    .submit{width:100%;margin-top:20px;border:0;border-radius:7px;background:var(--green);color:#fff;padding:13px 15px;font:inherit;font-weight:950;cursor:pointer;box-shadow:0 14px 28px rgba(7,95,42,.2)}.submit:hover{background:var(--deep)}
    .quick{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:9px;margin-top:16px}.quick a{border:1px solid var(--line);border-radius:7px;padding:10px;text-align:center;font-weight:850;color:#344054;background:#fbfdf9}.quick a:hover{background:#eef8f0;color:var(--green)}
    .note{margin-top:18px;border-top:1px solid var(--line);padding-top:15px;color:var(--muted);line-height:1.5;font-size:.92rem}.note strong{color:var(--green)}
    @media(max-width:920px){.shell{grid-template-columns:1fr}.hero{min-height:420px}.hero-grid,.quick{grid-template-columns:1fr}.bar{align-items:flex-start;flex-direction:column}.nav{width:100%}}
  </style>
</head>
<body>
<div class="page">
  <header class="top">
    <div class="bar">
      <a class="brand" href="../index.php"><img src="<?= e(app_primary_logo_url()) ?>" alt="NATCODEV"><strong>NATCODEV<span>Public Support Portal</span></strong></a>
      <nav class="nav" aria-label="Support login navigation">
        <a class="primary" href="index.php">Support Home</a>
        <a href="../index.php">Main Site</a>
        <a href="../academy/index.php">Academy</a>
        <a href="../market/index.php">Marketplace</a>
      </nav>
    </div>
  </header>

  <main class="shell">
    <section class="hero" aria-label="Support portal welcome">
      <div>
        <div class="hero-badges"><span>Ticket tracking</span><span>Public access</span><span>No dashboard entry</span></div>
        <h1>Support access only.</h1>
        <p>Open an existing ticket with your reference and email. This portal does not create a platform account or sign you into the NATCODEV dashboard.</p>
      </div>
      <div class="hero-grid">
        <div class="hero-stat"><strong>1</strong><span>Enter ticket ref</span></div>
        <div class="hero-stat"><strong>2</strong><span>Match email</span></div>
        <div class="hero-stat"><strong>3</strong><span>Continue support</span></div>
      </div>
    </section>

    <section class="login-card" aria-label="Support login form">
      <h2>Track Support Ticket</h2>
      <p class="lead">Use the ticket reference sent after submission. This is a support-only check, separate from stakeholder dashboard login.</p>
      <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
      <form method="post">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="next" value="<?= e($next) ?>">
        <label>Ticket Reference</label>
        <input name="ticket_ref" placeholder="TKT-260611-ABC123" required autocomplete="off">
        <label>Email</label>
        <input type="email" name="email" placeholder="you@example.com" required autocomplete="email">
        <button class="submit" type="submit">Open Ticket</button>
      </form>
      <div class="quick">
        <a href="index.php#new-ticket">New ticket</a>
        <a href="index.php#lookup">Track another</a>
        <a href="../index.php">Main site</a>
      </div>
      <p class="note"><strong>Support only.</strong> Visitors can track and reply to support tickets here, but this page never signs them into the main system.</p>
    </section>
  </main>
</div>
</body>
</html>
