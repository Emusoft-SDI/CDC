<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

function auth_page_start(string $title, string $description = ''): void
{
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($title . ' - NATCODEV') ?></title>
  <style>
    :root { --green:#2d5016; --leaf:#14733a; --gold:#c9a227; --ink:#172211; --muted:#66715f; --line:#dfe8d8; --bg:#f5f8f3; }
    * { box-sizing:border-box; }
    body { margin:0; min-height:100vh; display:grid; place-items:center; padding:24px; background:linear-gradient(135deg, rgba(45,80,22,.08), rgba(201,162,39,.10)), var(--bg); color:var(--ink); font-family:"Segoe UI", Arial, sans-serif; }
    main { width:100%; max-width:460px; }
    .brand { text-align:center; margin-bottom:18px; }
    .brand img { width:118px; height:118px; object-fit:contain; border-radius:50%; background:#fff; border:1px solid var(--line); box-shadow:0 12px 30px rgba(24,43,18,.10); }
    .brand strong { display:block; color:var(--green); font-size:1.2rem; margin-top:10px; letter-spacing:.04em; }
    form, .auth-card { background:#fff; border:1px solid rgba(24,43,18,.10); border-radius:8px; padding:28px; box-shadow:0 18px 44px rgba(24,43,18,.12); }
    h1 { margin:0 0 8px; color:var(--green); font-size:1.8rem; }
    .lead { margin:0 0 20px; color:var(--muted); line-height:1.55; }
    input, button { width:100%; box-sizing:border-box; padding:13px; margin-top:12px; border-radius:6px; border:1px solid var(--line); font:inherit; }
    input:focus { border-color:var(--leaf); box-shadow:0 0 0 3px rgba(20,115,58,.14); outline:none; }
    button { background:var(--green); color:#fff; border:0; font-weight:800; cursor:pointer; box-shadow:0 10px 24px rgba(45,80,22,.18); }
    button:hover { background:var(--leaf); }
    .error { color:#a32020; background:#fff3f3; border:1px solid #ffd2d2; padding:10px 12px; border-radius:6px; }
    .success { color:#0f6b3c; background:#eaf8f0; border:1px solid #bfe8cf; padding:10px 12px; border-radius:6px; }
    .links { display:flex; justify-content:space-between; gap:12px; margin-top:18px; font-size:.95rem; }
    a { color:var(--leaf); font-weight:800; text-decoration:none; }
    a:hover { text-decoration:underline; }
  </style>
</head>
<body>
<main>
  <div class="brand">
    <img src="<?= e(app_primary_logo_url()) ?>" alt="NATCODEV">
    <strong>NATCODEV</strong>
  </div>
<?php
}

function auth_page_end(): void
{
    ?>
</main>
</body>
</html>
<?php
}
