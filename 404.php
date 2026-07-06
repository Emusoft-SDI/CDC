<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$requestedCode = (int) ($_GET['code'] ?? 404);
$allowedCodes = [403, 404, 500, 503];
$statusCode = in_array($requestedCode, $allowedCodes, true) ? $requestedCode : 404;
http_response_code($statusCode);

$errorCopy = [
    403 => [
        'eyebrow' => 'Access blocked',
        'title' => 'This area is not open to you yet',
        'message' => 'The page exists, but your account does not have permission to view it. Sign in with the right role or ask support to check your access.',
        'primary' => 'Sign in',
        'primary_href' => 'login.php',
    ],
    404 => [
        'eyebrow' => 'Page not found',
        'title' => 'This coconut has gone bad',
        'message' => 'The page may have moved, expired, or never existed. Use the links below to get back to a working part of NATCODEV.',
        'primary' => 'Go home',
        'primary_href' => 'index.php',
    ],
    500 => [
        'eyebrow' => 'Temporary problem',
        'title' => 'Something failed gracefully',
        'message' => 'The request could not be completed right now. Try again, or contact support if the issue continues.',
        'primary' => 'Try home',
        'primary_href' => 'index.php',
    ],
    503 => [
        'eyebrow' => 'Service warming up',
        'title' => 'This page is not ready yet',
        'message' => 'The service is temporarily unavailable. Please try again shortly, or contact support if you need help now.',
        'primary' => 'Try home',
        'primary_href' => 'index.php',
    ],
];
$page = $errorCopy[$statusCode];
$assetPath = app_public_url('assets/public/rotten-coconut-error.png');
$logoPath = app_primary_logo_url();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e((string) $statusCode) ?> - <?= e($page['eyebrow']) ?> - NATCODEV</title>
  <style>
    :root{--green:#075f2a;--leaf:#0a7a3d;--mint:#edf8ef;--cream:#fffdf8;--soft:#f7faf6;--ink:#17231d;--muted:#66756d;--line:#dfe8d8;--gold:#d6a437}
    *{box-sizing:border-box}html{min-height:100%}body{margin:0;min-height:100vh;background:linear-gradient(180deg,#fffdf8 0%,#f5faf4 100%);color:var(--ink);font-family:"Segoe UI",Arial,sans-serif}
    .page{min-height:100vh;display:grid;grid-template-rows:auto 1fr}.top{height:76px;display:flex;align-items:center;justify-content:space-between;gap:16px;padding:0 clamp(18px,5vw,68px);border-bottom:1px solid rgba(7,95,42,.1);background:rgba(255,255,255,.76);backdrop-filter:blur(10px)}
    .brand{display:flex;align-items:center;gap:12px;color:var(--green);text-decoration:none;font-weight:950}.brand img{width:48px;height:48px;border-radius:50%;object-fit:contain;border:1px solid var(--line);background:#fff}.brand small{display:block;color:var(--muted);font-weight:750;font-size:.78rem;margin-top:1px}.support{display:inline-flex;align-items:center;justify-content:center;border:1px solid var(--line);border-radius:8px;background:#fff;color:var(--green);padding:10px 13px;text-decoration:none;font-weight:900}
    main{display:grid;place-items:center;padding:clamp(24px,5vw,72px) clamp(18px,5vw,68px)}.shell{width:min(1080px,100%);display:grid;grid-template-columns:minmax(0,1fr) minmax(280px,430px);gap:clamp(28px,6vw,74px);align-items:center}.copy{max-width:590px}.code{display:inline-flex;align-items:center;gap:8px;border:1px solid #dcebdc;background:#fff;border-radius:999px;color:var(--green);font-weight:950;padding:8px 12px;margin-bottom:18px}.dot{width:8px;height:8px;border-radius:50%;background:var(--gold)}
    h1{font-size:clamp(2.35rem,6vw,5.4rem);line-height:.98;letter-spacing:0;margin:0 0 18px;color:#122018}p{font-size:clamp(1rem,2vw,1.14rem);line-height:1.65;color:var(--muted);margin:0 0 26px;max-width:540px}.actions{display:flex;flex-wrap:wrap;gap:12px}.btn{display:inline-flex;align-items:center;justify-content:center;min-height:46px;border-radius:8px;padding:12px 16px;text-decoration:none;font-weight:950;border:1px solid var(--line);background:#fff;color:var(--green)}.btn.primary{background:var(--green);border-color:var(--green);color:#fff}.btn:hover{transform:translateY(-1px)}
    .art{position:relative;min-height:330px;display:grid;place-items:center}.art::before{content:"";position:absolute;inset:6% 0 0 8%;border-radius:50%;background:radial-gradient(circle,rgba(7,95,42,.08),rgba(7,95,42,0) 68%)}.art img{position:relative;z-index:1;width:min(430px,100%);height:auto;display:block;filter:drop-shadow(0 28px 42px rgba(47,64,46,.14))}.hint{margin-top:18px;color:#7a877f;font-size:.92rem}
    @media(max-width:820px){.top{height:auto;padding:14px 18px}.brand small{display:none}.shell{grid-template-columns:1fr;text-align:center}.copy{margin:auto}.actions{justify-content:center}.art{order:-1;min-height:auto}.art img{width:min(300px,78vw)}p{margin-left:auto;margin-right:auto}}
  </style>
</head>
<body>
<div class="page">
  <header class="top">
    <a class="brand" href="index.php"><img src="<?= e($logoPath) ?>" alt="NATCODEV"><span>NATCODEV<small>National Coconut Development</small></span></a>
    <a class="support" href="support/index.php">Support</a>
  </header>
  <main>
    <section class="shell" aria-labelledby="error-title">
      <div class="copy">
        <div class="code"><span class="dot"></span><?= e((string) $statusCode) ?> <?= e($page['eyebrow']) ?></div>
        <h1 id="error-title"><?= e($page['title']) ?></h1>
        <p><?= e($page['message']) ?></p>
        <div class="actions">
          <a class="btn primary" href="<?= e($page['primary_href']) ?>"><?= e($page['primary']) ?></a>
          <a class="btn" href="javascript:history.back()">Go back</a>
          <a class="btn" href="support/index.php">Get help</a>
        </div>
        <div class="hint">No worries. Nothing has been changed on your account.</div>
      </div>
      <figure class="art" aria-hidden="true"><img src="<?= e($assetPath) ?>" alt=""></figure>
    </section>
  </main>
</div>
</body>
</html>