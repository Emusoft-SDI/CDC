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
    if (!app_check_rate_limit('support_ticket_login', 20, 900)) {
        $error = 'Too many ticket access attempts. Please try again in 15 minutes.';
    } elseif (!verify_csrf($_POST['_csrf'] ?? null)) {
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
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root {
      --green: #075f2a;
      --deep: #053b1c;
      --green-light: #edf7ef;
      --green-gradient: linear-gradient(135deg, #075f2a 0%, #053b1c 100%);
      --line: #e2e8f0;
      --bg: #f8faf6;
      --panel: #ffffff;
      --ink: #0f172a;
      --muted: #64748b;
      --red: #dc2626;
      --red-light: #fee2e2;
      --shadow-sm: 0 1px 3px rgba(16,24,40,0.05);
      --shadow-md: 0 4px 20px -2px rgba(16,24,40,0.08);
      --shadow-lg: 0 12px 30px -4px rgba(16,24,40,0.12);
      --radius-sm: 8px;
      --radius-md: 12px;
      --radius-lg: 18px;
      --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    * {
      box-sizing: border-box;
      outline: none;
    }
    
    body {
      margin: 0;
      min-height: 100vh;
      background: var(--bg);
      color: var(--ink);
      font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      -webkit-font-smoothing: antialiased;
    }
    
    a {
      color: inherit;
      text-decoration: none;
      transition: var(--transition);
    }
    
    .page {
      min-height: 100vh;
      display: grid;
      grid-template-rows: auto 1fr;
    }
    
    /* Navigation Bar */
    .top {
      background: rgba(255, 255, 255, 0.85);
      backdrop-filter: blur(16px);
      -webkit-backdrop-filter: blur(16px);
      border-bottom: 1px solid rgba(226, 232, 240, 0.8);
      box-shadow: var(--shadow-sm);
    }
    
    .bar {
      max-width: 1180px;
      margin: 0 auto;
      padding: 14px 24px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
    }
    
    .brand {
      display: flex;
      align-items: center;
      gap: 14px;
      color: var(--green);
      font-weight: 800;
      font-size: 1.15rem;
      letter-spacing: -0.02em;
    }
    
    .brand img {
      width: 48px;
      height: 48px;
      border-radius: 50%;
      border: 2px solid #fff;
      background: #fff;
      object-fit: contain;
      box-shadow: 0 4px 10px rgba(7, 95, 42, 0.15);
    }
    
    .brand span {
      display: block;
      color: var(--muted);
      font-size: 0.8rem;
      margin-top: 2px;
      font-weight: 500;
    }
    
    .nav {
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
    }
    
    .nav a {
      border: 1px solid var(--line);
      border-radius: var(--radius-sm);
      background: #fff;
      padding: 9px 16px;
      font-weight: 600;
      font-size: 0.9rem;
      color: #475569;
      box-shadow: var(--shadow-sm);
    }
    
    .nav a.primary {
      background: var(--green-gradient);
      border-color: transparent;
      color: #fff;
      box-shadow: 0 4px 12px rgba(7, 95, 42, 0.2);
    }
    
    .nav a:hover {
      background: var(--green-light);
      color: var(--green);
      border-color: rgba(7, 95, 42, 0.2);
    }
    
    /* Login Layout Shell */
    .shell {
      max-width: 1180px;
      width: 100%;
      margin: 0 auto;
      padding: 40px 24px;
      display: grid;
      grid-template-columns: minmax(0, 1.1fr) minmax(360px, 0.9fr);
      gap: 32px;
      align-items: center;
    }
    
    .hero {
      min-height: 590px;
      border-radius: var(--radius-lg);
      overflow: hidden;
      position: relative;
      background: linear-gradient(135deg, rgba(5, 59, 28, 0.96), rgba(7, 95, 42, 0.85)), url("../images/26.jpg") center/cover no-repeat;
      color: #fff;
      padding: 40px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      box-shadow: var(--shadow-lg);
    }
    
    .hero h1 {
      font-size: clamp(2rem, 4vw, 3rem);
      line-height: 1.15;
      margin: 0 0 16px;
      font-weight: 800;
      letter-spacing: -0.03em;
    }
    
    .hero p {
      max-width: 620px;
      line-height: 1.6;
      color: #e2f5e8;
      font-size: 1.05rem;
      margin-bottom: 24px;
    }
    
    .hero-badges {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      margin-bottom: 24px;
    }
    
    .hero-badges span {
      background: rgba(255, 255, 255, 0.12);
      border: 1px solid rgba(255, 255, 255, 0.2);
      border-radius: 9999px;
      padding: 8px 14px;
      font-weight: 700;
      font-size: 0.8rem;
      letter-spacing: 0.02em;
    }
    
    .hero-grid {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 12px;
    }
    
    .hero-stat {
      background: rgba(255, 255, 255, 0.08);
      border: 1px solid rgba(255, 255, 255, 0.15);
      border-radius: var(--radius-md);
      padding: 16px;
    }
    
    .hero-stat strong {
      display: block;
      font-size: 1.6rem;
      font-weight: 800;
    }
    
    .hero-stat span {
      display: block;
      color: #c9ebd3;
      font-size: 0.8rem;
      margin-top: 4px;
      font-weight: 500;
    }
    
    /* Login Card Panel */
    .login-card {
      background: var(--panel);
      border: 1px solid var(--line);
      border-radius: var(--radius-lg);
      padding: 32px;
      box-shadow: var(--shadow-md);
    }
    
    .login-card h2 {
      margin: 0;
      color: var(--deep);
      font-size: 1.85rem;
      font-weight: 800;
      letter-spacing: -0.03em;
    }
    
    .lead {
      margin: 8px 0 24px;
      color: var(--muted);
      line-height: 1.55;
      font-size: 0.95rem;
    }
    
    .alert {
      border-radius: var(--radius-md);
      padding: 14px 16px;
      margin-bottom: 20px;
      font-weight: 600;
      font-size: 0.9rem;
    }
    
    .alert.error {
      background: #fef2f2;
      color: var(--red);
      border: 1px solid #ffd2d2;
    }
    
    label {
      display: block;
      font-weight: 600;
      color: #334155;
      margin: 16px 0 6px;
      font-size: 0.85rem;
    }
    
    input {
      width: 100%;
      border: 1px solid var(--line);
      border-radius: var(--radius-sm);
      padding: 12px 14px;
      font-size: 0.9rem;
      background: #fff;
      color: var(--ink);
      transition: var(--transition);
    }
    
    input:focus {
      outline: 0;
      border-color: var(--green);
      box-shadow: 0 0 0 3px rgba(7, 95, 42, 0.12);
    }
    
    .submit {
      width: 100%;
      margin-top: 24px;
      border: 0;
      border-radius: var(--radius-md);
      background: var(--green-gradient);
      color: #fff;
      padding: 14px;
      font-size: 0.95rem;
      font-weight: 700;
      cursor: pointer;
      box-shadow: 0 4px 14px rgba(7, 95, 42, 0.2);
      transition: var(--transition);
    }
    
    .submit:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(7, 95, 42, 0.3);
    }
    
    .submit:active {
      transform: translateY(0);
    }
    
    .quick {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 10px;
      margin-top: 20px;
    }
    
    .quick a {
      border: 1px solid var(--line);
      border-radius: var(--radius-sm);
      padding: 12px 8px;
      text-align: center;
      font-weight: 700;
      font-size: 0.8rem;
      color: #475569;
      background: #fbfdf9;
      box-shadow: var(--shadow-sm);
    }
    
    .quick a:hover {
      background: var(--green-light);
      color: var(--green);
      border-color: rgba(7, 95, 42, 0.2);
    }
    
    .note {
      margin-top: 20px;
      border-top: 1px solid var(--line);
      padding-top: 18px;
      color: var(--muted);
      line-height: 1.55;
      font-size: 0.85rem;
    }
    
    .note strong {
      color: var(--green);
      font-weight: 700;
    }
    
    @media (max-width: 920px) {
      .shell {
        grid-template-columns: 1fr;
        padding: 24px 16px;
      }
      .hero {
        min-height: 380px;
        padding: 24px;
      }
      .hero-grid, .quick {
        grid-template-columns: 1fr;
      }
      .bar {
        align-items: center;
        flex-direction: column;
        gap: 12px;
        text-align: center;
      }
      .nav {
        justify-content: center;
      }
    }
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

