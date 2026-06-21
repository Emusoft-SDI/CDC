<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

http_response_code(404);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Page not found - NATCODEV</title>
  <style>
    :root { --green:#078326; --ink:#262626; --muted:#6f7472; --line:#edf1ee; }
    * { box-sizing:border-box; }
    body { margin:0; min-height:100vh; background:#fff; color:var(--ink); font-family:"Inter","Segoe UI",Arial,sans-serif; }
    header { height:82px; display:flex; align-items:center; justify-content:space-between; gap:22px; padding:0 clamp(22px,6vw,76px); border-bottom:1px solid var(--line); }
    .brand img { width:112px; height:auto; display:block; }
    nav { display:flex; align-items:center; gap:28px; font-size:.9rem; }
    nav a { color:#303532; text-decoration:none; font-weight:700; }
    .login { min-width:112px; text-align:center; padding:10px 18px; background:#f3f8f5; color:var(--green); border-radius:3px; }
    main { min-height:calc(100vh - 82px); display:grid; place-items:center; padding:42px 18px; position:relative; overflow:hidden; text-align:center; }
    main::before { content:""; position:absolute; width:min(650px,90vw); aspect-ratio:1.8/1; background:url("https://natcodev.com.ng/images/nuts.jpg?auto=format&fit=crop&w=1200&q=85") center/contain no-repeat; opacity:.18; transform:translateY(-12px); }
    .content { position:relative; z-index:1; max-width:520px; }
    h1 { margin:0 0 14px; font-size:clamp(2rem,5vw,3.2rem); line-height:1.05; letter-spacing:0; }
    p { margin:0 auto 28px; color:var(--muted); line-height:1.55; max-width:380px; }
    .actions { display:flex; justify-content:center; gap:12px; flex-wrap:wrap; }
    .button { display:inline-flex; align-items:center; justify-content:center; min-width:112px; padding:12px 18px; border-radius:3px; text-decoration:none; font-weight:800; color:#2d332f; background:#f6f7f6; }
    .button.primary { background:var(--green); color:#fff; }
    @media(max-width:720px){ header{height:auto;align-items:flex-start;flex-direction:column;padding:18px 22px} nav{gap:14px;flex-wrap:wrap} }
  </style>
</head>
<body>
  <header>
    <a class="brand" href="index.php"><img src="<?= e(app_primary_logo_url()) ?>" alt="NATCODEV"></a>
    <nav>
      <a href="index.php">Home</a>
      <a href="index.php#registration">Farmers</a>
      <a href="index.php#registration">Investors</a>
      <a href="provider/index.php">Service providers</a>
      <a href="index.php#contact">Contact Us</a>
      <a class="login" href="login.php">LOGIN</a>
    </nav>
  </header>
  <main>
    <section class="content">
      <h1>Page not found</h1>
      <p>Sorry, the page you are looking for doesn’t exist or has been moved. Here are some helpful links.</p>
      <div class="actions">
        <a class="button" href="javascript:history.back()">Go back</a>
        <a class="button primary" href="index.php">Take me home</a>
      </div>
    </section>
  </main>
</body>
</html>
