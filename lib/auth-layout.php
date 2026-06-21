<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

function auth_page_start(string $title, string $description = ''): void
{
    $lowerTitle = strtolower($title);
    $isOtp = str_contains($lowerTitle, 'otp') || str_contains($lowerTitle, 'access code');
    $image = str_contains($lowerTitle, 'forgot')
        ? 'https://natcodev.com.ng/images/nuts.jpg?auto=format&fit=crop&w=1100&q=85'
        : ($isOtp
            ? app_base_url() . '/assets/public/grower-registration-hero.png'
            : 'https://natcodev.com.ng/images/26.jpg?auto=format&fit=crop&w=1100&q=85');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($title . ' - NATCODEV') ?></title>
  <style>
    :root { --green:#06451f; --green-dark:#043718; --mint:#eef8ef; --ink:#101828; --muted:#667085; --line:#dfe8d8; --bg:#fbfcf8; }
    * { box-sizing:border-box; }
    body { margin:0; min-height:100vh; background:var(--bg); color:var(--ink); font-family:"Inter","Segoe UI",Arial,sans-serif; }
    main { min-height:100vh; display:grid; grid-template-columns:minmax(360px, 46%) minmax(0, 54%); }
    .auth-panel { display:flex; flex-direction:column; justify-content:flex-start; min-height:100vh; padding:132px clamp(28px, 7vw, 86px) 72px; position:relative; }
    .brand { position:absolute; top:34px; left:clamp(28px, 7vw, 86px); display:flex; align-items:center; gap:10px; }
    .brand img { width:66px; height:66px; border-radius:50%; object-fit:contain; background:#fff; }
    form, .auth-card { width:100%; max-width:430px; background:transparent; border:0; border-radius:0; padding:0; box-shadow:none; }
    .auth-eyebrow { display:inline-flex; align-items:center; gap:8px; margin-bottom:12px; color:var(--green); font-weight:900; text-transform:uppercase; letter-spacing:.04em; font-size:.78rem; }
    .auth-eyebrow:before { content:""; width:9px; height:9px; border-radius:50%; background:var(--green); }
    h1 { margin:0 0 12px; color:#101828; font-size:clamp(2rem, 4vw, 3rem); line-height:1.05; letter-spacing:0; }
    .lead { margin:0 0 32px; color:var(--muted); line-height:1.55; max-width:420px; }
    label { display:block; color:#343a37; font-size:.92rem; font-weight:700; margin:14px 0 7px; }
    input, button { width:100%; box-sizing:border-box; padding:13px 14px; border-radius:8px; border:1px solid var(--line); font:inherit; background:#fff; }
    input:focus { border-color:var(--green); box-shadow:0 0 0 3px rgba(7,131,38,.12); outline:none; }
    button { margin-top:28px; background:var(--green); color:#fff; border:0; font-weight:800; cursor:pointer; }
    button:hover { background:var(--green-dark); }
    .password-field { position:relative; }
    .password-field input { padding-right:76px; }
    .password-toggle { position:absolute; right:8px; top:50%; transform:translateY(-50%); width:auto; margin:0; padding:7px 9px; border:0; background:#f1faf5; color:var(--green); font-size:.82rem; box-shadow:none; }
    .password-toggle:hover { background:#e4f4e8; color:var(--green-dark); }
    .error { color:#a32020; background:#fff3f3; border:1px solid #ffd2d2; padding:10px 12px; border-radius:6px; }
    .success { color:#0f6b3c; background:#eaf8f0; border:1px solid #bfe8cf; padding:10px 12px; border-radius:6px; }
    .links { display:flex; justify-content:flex-start; gap:12px; margin-top:28px; font-size:.95rem; color:var(--muted); }
    a { color:var(--green); font-weight:800; text-decoration:none; }
    a:hover { text-decoration:underline; }
    .stakeholder-note { margin-top:18px; padding:14px; border:1px solid var(--line); border-radius:10px; background:#f7fbf4; color:#475467; font-size:.92rem; }
    .stakeholder-note strong { display:block; color:#06451f; margin-bottom:8px; }
    .stakeholder-note ul { margin:0; padding-left:18px; display:grid; gap:5px; }
    .auth-media { min-height:100vh; background:linear-gradient(90deg,rgba(4,69,31,.82),rgba(4,69,31,.36)),url("<?= e($image) ?>") center/cover no-repeat; position:relative; }
    .auth-media:after { content:"<?= $isOtp ? 'Secure access for verified NATCODEV stakeholders' : '' ?>"; position:absolute; left:42px; right:42px; bottom:42px; color:#fff; font-weight:900; font-size:clamp(1.6rem, 3vw, 2.7rem); line-height:1.08; text-shadow:0 10px 30px rgba(0,0,0,.35); }
    .otp-grid { display:grid; grid-template-columns:repeat(6, 1fr); gap:14px; margin:18px 0 0; max-width:380px; }
    .otp-grid input { height:58px; text-align:center; font-size:1.4rem; margin:0; border-radius:9px; }
    .copyright { position:absolute; left:clamp(28px, 7vw, 86px); bottom:34px; color:#777; font-size:.9rem; }
    @media (max-width:820px) {
      main { grid-template-columns:1fr; }
      .auth-media { display:none; }
      .auth-panel { padding-top:128px; }
    }
  </style>
  <link rel="stylesheet" href="<?= e(app_base_url()) ?>/assets/css/natcodev-ui.css?v=20260530">
</head>
<body>
<main>
  <div class="brand">
    <img src="<?= e(app_primary_logo_url()) ?>" alt="NATCODEV">
  </div>
  <section class="auth-panel">
<?php
}

function auth_page_end(): void
{
    ?>
  <div class="copyright">&copy; <?= e(date('Y')) ?> NATCODEV All Rights Reserved</div>
  </section>
  <aside class="auth-media" aria-hidden="true"></aside>
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
<?php
}
