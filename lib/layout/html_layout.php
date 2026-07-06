<?php

function status_label(string $status): string
{
    return ucwords(str_replace('_', ' ', $status));
}


function admin_page_start(string $title, array $options = []): void
{
    $active = $options['active'] ?? basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'admin.php'));
    $description = (string) ($options['description'] ?? '');
    $wide = !empty($options['wide']);
    $chrome = (bool) ($options['chrome'] ?? true);
    $GLOBALS['admin_page_chrome'] = $chrome;
    $max = $wide ? '1320px' : '1180px';
    $navGroups = admin_allowed_nav_groups(db());
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($title) ?> - NATCODEV Admin</title>
  <style>
    :root { --primary:#1a5276; --green:#1f8a55; --green-dark:#166b41; --ink:#1f2937; --muted:#667085; --line:#d8e2dc; --bg:#f5f8f6; --panel:#fff; --danger:#a32020; --warn:#9b6500; --shadow:0 14px 34px rgba(16,24,40,.08); }
    * { box-sizing:border-box; }
    body { margin:0; background:var(--bg); color:var(--ink); font-family:"Segoe UI", Tahoma, Geneva, Verdana, sans-serif; }
    a { color:var(--green-dark); font-weight:750; text-decoration:none; }
    a:hover { text-decoration:underline; }
    .admin-shell { min-height:100vh; display:flex; flex-direction:column; }
    .admin-header { background:#fff; border-bottom:1px solid rgba(16,24,40,.08); box-shadow:0 8px 24px rgba(16,24,40,.06); position:sticky; top:0; z-index:20; }
    .admin-bar { max-width:<?= $max ?>; margin:0 auto; padding:14px 22px; display:flex; align-items:center; justify-content:space-between; gap:18px; }
    .admin-brand { display:flex; align-items:center; gap:11px; color:var(--primary); font-weight:900; min-width:220px; }
    .admin-brand img { width:46px; height:46px; object-fit:contain; border-radius:50%; border:1px solid var(--line); background:#fff; }
    .admin-brand span { display:block; color:var(--muted); font-size:.82rem; font-weight:650; margin-top:3px; }
    .admin-nav { display:flex; flex-wrap:wrap; justify-content:center; gap:8px; }
    .admin-nav details { position:relative; }
    .admin-nav summary, .admin-nav .nav-link { display:inline-flex; align-items:center; gap:7px; min-height:39px; padding:9px 11px; border-radius:7px; border:1px solid transparent; color:#344054; font-size:.92rem; font-weight:800; cursor:pointer; list-style:none; }
    .admin-nav summary::-webkit-details-marker { display:none; }
    .admin-nav summary::after { content:""; width:7px; height:7px; border-right:2px solid currentColor; border-bottom:2px solid currentColor; transform:rotate(45deg) translateY(-2px); opacity:.7; }
    .admin-nav details.active:not([open]) > summary, .admin-nav .nav-link.active { background:var(--green-dark); border-color:var(--green-dark); color:#fff; box-shadow:0 10px 22px rgba(6,63,36,.16); }
    .admin-nav details[open] > summary { background:var(--green-dark); border-color:var(--green-dark); color:#fff; text-decoration:none; }
    .admin-nav summary:hover, .admin-nav .nav-link:hover { background:#e1f3e8; border-color:#b8dec7; color:var(--green-dark); text-decoration:none; }
    .admin-nav details[open] summary::after { transform:rotate(225deg) translate(-2px,-1px); }
    .admin-menu { position:absolute; right:0; top:calc(100% + 8px); width:min(280px, calc(100vw - 44px)); padding:8px; background:#fff; border:1px solid rgba(16,24,40,.11); border-radius:8px; box-shadow:0 18px 38px rgba(16,24,40,.16); display:grid; gap:4px; z-index:100; }
    .admin-menu a { color:#344054; padding:10px 11px; border-radius:6px; font-size:.92rem; }
    .admin-menu a:focus-visible, .admin-nav summary:focus-visible, .admin-nav .nav-link:focus-visible { outline:3px solid rgba(31,138,85,.22); outline-offset:2px; }
    .admin-menu a.active { background:var(--green-dark); color:#fff; text-decoration:none; }
    .admin-menu a:hover { background:#e1f3e8; color:var(--green-dark); text-decoration:none; }
    .admin-user { min-width:120px; text-align:right; }
    .admin-main { width:100%; max-width:<?= $max ?>; margin:0 auto; padding:28px 22px 38px; flex:1; }
    .page-title { margin-bottom:20px; display:flex; align-items:flex-start; justify-content:space-between; gap:18px; }
    .page-title h1 { color:var(--primary); margin:0; font-size:clamp(2rem,4vw,3rem); line-height:1.06; }
    .page-title p { color:var(--muted); margin:8px 0 0; max-width:780px; line-height:1.6; }
    .panel, .card, .stat, table { background:var(--panel); border:1px solid rgba(16,24,40,.08); border-radius:8px; box-shadow:var(--shadow); }
    .panel, .card, .stat { padding:18px; }
    .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:16px; }
    .layout { display:grid; grid-template-columns:340px 1fr; gap:18px; align-items:start; }
    .stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:14px; margin:18px 0; }
    .metric { color:var(--primary); font-size:2rem; font-weight:900; line-height:1; }
    .toolbar, .actions { display:flex; flex-wrap:wrap; align-items:center; gap:10px; margin:14px 0; }
    .pagination { margin:14px 0; display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; padding:12px; background:#fff; border:1px solid rgba(16,24,40,.08); border-radius:8px; }
    .pagination-links { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
    .pagination-size { display:flex; align-items:center; gap:8px; margin:0; }
    .pagination-size select { width:auto; min-width:86px; }
    table { width:100%; border-collapse:collapse; overflow:hidden; }
    th, td { padding:11px; border-bottom:1px solid #edf1ea; text-align:left; vertical-align:top; }
    th { background:#eef6e9; color:#243b1d; }
    label { display:block; font-weight:800; margin:10px 0 6px; }
    input, select, textarea { padding:11px 12px; border:1px solid var(--line); border-radius:6px; font:inherit; max-width:100%; }
    input:not([type="checkbox"]), select, textarea { width:100%; }
    textarea { min-height:110px; }
    input:focus, select:focus, textarea:focus { border-color:var(--green); box-shadow:0 0 0 3px rgba(31,138,85,.14); outline:none; }
    .password-field { position:relative; }
    .password-field input { padding-right:76px; }
    .password-toggle { position:absolute; right:8px; top:50%; transform:translateY(-50%); width:auto; margin:0; padding:7px 9px; border:0; background:#eef7f1; color:var(--green-dark); font-size:.82rem; box-shadow:none; }
    button, .button { display:inline-flex; align-items:center; justify-content:center; gap:8px; background:var(--green); color:#fff; border:0; border-radius:6px; padding:11px 14px; font-weight:850; cursor:pointer; text-decoration:none; box-shadow:0 10px 24px rgba(31,138,85,.18); }
    button:hover, .button:hover { background:var(--green-dark); color:#fff; text-decoration:none; }
    button[disabled], button.is-busy, .button[aria-disabled="true"] { opacity:.82; cursor:wait; pointer-events:none; }
    button.is-busy::before { content:""; width:14px; height:14px; border:2px solid rgba(255,255,255,.5); border-top-color:#fff; border-radius:50%; animation:admin-spin .7s linear infinite; }
    button.is-busy { background:var(--green-dark); color:#fff; box-shadow:0 10px 24px rgba(31,138,85,.24); }
    .button.secondary, button.secondary { background:#eef7f1; color:var(--green-dark); border:1px solid var(--line); box-shadow:none; }
    button.secondary.is-busy::before { border-color:rgba(22,107,65,.25); border-top-color:var(--green-dark); }
    .button.danger, button.danger { background:var(--danger); }
    .badge { display:inline-flex; align-items:center; border-radius:999px; padding:5px 9px; font-size:.78rem; font-weight:850; white-space:nowrap; }
    .ok, .success, .verified, .resolved { background:#eaf8f0; color:#0f6b3c; }
    .pending, .in_progress, .warning { background:#fff7df; color:#8a5a00; }
    .error, .rejected, .danger { background:#fff3f3; color:var(--danger); }
    .open, .closed, .muted-badge { background:#eef2f6; color:#475467; }
    .notice { padding:13px 15px; border-radius:8px; margin:16px 0; border:1px solid transparent; }
    .notice.ok { border-color:#bfe8cf; }
    .notice.error { border-color:#ffd2d2; }
    .muted, .meta, small { color:var(--muted); }
    .empty { color:var(--muted); border:1px dashed var(--line); border-radius:8px; padding:18px; }
    .admin-footer { background:#12344a; color:#e6f0f5; margin-top:auto; }
    .admin-footer-inner { max-width:<?= $max ?>; margin:0 auto; padding:18px 22px; display:flex; align-items:center; justify-content:space-between; gap:22px; flex-wrap:wrap; }
    .footer-links { display:flex; align-items:center; justify-content:flex-end; flex-wrap:wrap; gap:10px; }
    .footer-links a, .footer-logout { color:#f6fff2; font-size:.9rem; font-weight:750; padding:7px 10px; border:1px solid rgba(255,255,255,.16); border-radius:6px; background:transparent; font:inherit; cursor:pointer; }
    .footer-links a:hover, .footer-logout:hover { background:rgba(255,255,255,.1); text-decoration:none; }
    .admin-action-overlay { position:fixed; left:0; right:0; top:0; height:4px; background:linear-gradient(90deg, var(--green), #c9a227, var(--primary), var(--green)); background-size:220% 100%; z-index:90; display:none; pointer-events:none; animation:admin-progress 1s linear infinite; }
    .admin-working-toast { position:fixed; right:18px; bottom:18px; z-index:91; display:none; align-items:center; gap:10px; padding:12px 14px; border-radius:8px; background:#12344a; color:#fff; box-shadow:0 14px 30px rgba(16,24,40,.2); font-weight:850; }
    .admin-working-toast::before { content:""; width:16px; height:16px; border:2px solid rgba(255,255,255,.45); border-top-color:#fff; border-radius:50%; animation:admin-spin .7s linear infinite; }
    body.admin-submitting .admin-action-overlay, body.admin-submitting .admin-working-toast { display:flex; }
    @keyframes admin-spin { to { transform:rotate(360deg); } }
    @keyframes admin-progress { to { background-position:-220% 0; } }
    @media (max-width:960px) {
      .admin-bar { align-items:flex-start; flex-direction:column; }
      .admin-nav { justify-content:flex-start; }
      .admin-menu { left:0; right:auto; }
      .admin-user { text-align:left; }
      .layout { grid-template-columns:1fr; }
      .page-title { flex-direction:column; }
      .admin-footer-inner { align-items:flex-start; flex-direction:column; }
    }
    @media (max-width:560px) {
      .admin-bar, .admin-main, .admin-footer-inner { padding-left:16px; padding-right:16px; }
      .admin-nav { width:100%; display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); }
      .admin-nav details, .admin-nav summary, .admin-nav .nav-link { width:100%; }
      .admin-nav summary, .admin-nav .nav-link { justify-content:center; }
      .admin-menu { width:calc(100vw - 32px); }
      .footer-links { justify-content:flex-start; }
    }
    <?= $options['css'] ?? '' ?>
  </style>
  <link rel="stylesheet" href="../assets/css/natcodev-ui.css?v=20260530">
</head>
<body>
<div class="admin-shell">
  <div class="admin-action-overlay" aria-hidden="true"></div>
  <div class="admin-working-toast" role="status" aria-live="polite">Processing request...</div>
  <?php if ($chrome): ?>
  <header class="admin-header">
    <div class="admin-bar">
      <a class="admin-brand" href="index.php">
        <img src="<?= e(app_admin_logo_url()) ?>" alt="NATCODEV">
        <span><strong>NATCODEV Admin</strong><span>Workspace operations hub</span></span>
      </a>
      <nav class="admin-nav" aria-label="Admin navigation">
        <?php foreach ($navGroups as $groupLabel => $items): ?>
          <?php $groupActive = in_array($active, array_column($items, 'href'), true); ?>
          <details class="<?= $groupActive ? 'active' : '' ?>">
            <summary><?= e((string) $groupLabel) ?></summary>
            <div class="admin-menu">
              <?php foreach ($items as $item): ?>
                <a class="<?= $active === $item['href'] ? 'active' : '' ?>" href="<?= e($item['href']) ?>"><?= e($item['label']) ?></a>
              <?php endforeach; ?>
            </div>
          </details>
        <?php endforeach; ?>
      </nav>
      <div class="admin-user"><form method="post" action="<?= e(admin_logout_action_path()) ?>"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="logout" value="1"><button class="secondary" type="submit">Logout</button></form></div>
    </div>
  </header>
  <?php endif; ?>
  <main class="admin-main">
    <?php if ($chrome): ?>
    <section class="page-title">
      <div>
        <h1><?= e($title) ?></h1>
        <?php if ($description !== ''): ?><p><?= e($description) ?></p><?php endif; ?>
      </div>
      <?php if (!empty($options['action_html'])): ?><div><?= $options['action_html'] ?></div><?php endif; ?>
    </section>
    <?php endif; ?>
<?php
}


function admin_page_end(): void
{
    $footerItems = admin_footer_nav_items(db());
    ?>
  </main>
  <?php if (!empty($GLOBALS['admin_page_chrome'])): ?>
  <footer class="admin-footer">
    <div class="admin-footer-inner">
      <div>
        <strong>NATCODEV Admin Console</strong>
        <div class="meta" style="margin-top:6px;color:#c9d8df;">Dashboards, registry work, HR, field operations, reporting, governance, and settings now have separate homes.</div>
      </div>
      <nav class="footer-links" aria-label="Admin quick links">
        <?php foreach ($footerItems as $item): ?>
          <a href="<?= e($item['href']) ?>"><?= e($item['label']) ?></a>
        <?php endforeach; ?>
        <form method="post" action="<?= e(admin_logout_action_path()) ?>" style="margin:0"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="logout" value="1"><button class="footer-logout" type="submit">Logout</button></form>
      </nav>
    </div>
  </footer>
  <?php endif; ?>
</div>
<script src="../lib/location-picker.js"></script>
<script>
(function () {
  const nav = document.querySelector('.admin-nav');
  const details = nav ? Array.from(nav.querySelectorAll('details')) : [];

  function closeMenus(except) {
    details.forEach((item) => {
      if (item !== except) item.removeAttribute('open');
    });
  }

  details.forEach((item) => {
    const summary = item.querySelector('summary');
    if (!summary) return;
    summary.addEventListener('click', () => {
      window.setTimeout(() => {
        if (item.open) closeMenus(item);
      }, 0);
    });
  });

  document.addEventListener('click', (event) => {
    if (!nav || nav.contains(event.target)) return;
    closeMenus(null);
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') closeMenus(null);
  });

  window.addEventListener('scroll', () => closeMenus(null), { passive:true });
  window.addEventListener('resize', () => closeMenus(null));
  document.addEventListener('touchmove', () => closeMenus(null), { passive:true });

  document.querySelectorAll('.admin-menu a').forEach((link) => {
    link.addEventListener('click', () => closeMenus(null));
  });

  document.querySelectorAll('form').forEach((form) => {
    form.addEventListener('submit', (event) => {
      if (event.defaultPrevented) {
        return;
      }
      if (form.dataset.submitting === '1') {
        event.preventDefault();
        return;
      }

      form.dataset.submitting = '1';
      const submitter = event.submitter || form.querySelector('button[type="submit"], button:not([type]), input[type="submit"]');
      if (submitter && submitter.name) {
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = submitter.name;
        hidden.value = submitter.value || '';
        form.appendChild(hidden);
      }
      if (submitter && submitter.tagName === 'BUTTON') {
        submitter.dataset.originalText = submitter.textContent.trim();
        submitter.classList.add('is-busy');
        submitter.disabled = true;
        const busyText = submitter.dataset.busyText || 'Processing...';
        submitter.textContent = busyText;
        const toast = document.querySelector('.admin-working-toast');
        if (toast) toast.lastChild.textContent = busyText;
      }

      form.querySelectorAll('button[type="submit"], button:not([type]), input[type="submit"]').forEach((button) => {
        if (button !== submitter) button.disabled = true;
      });
      document.body.classList.add('admin-submitting');
    });
  });

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
})();
</script>
</body>
</html>
<?php
}
