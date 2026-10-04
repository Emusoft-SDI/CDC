<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

/**
 * NATCODEV Admin UI (v2) — shell renderer.
 * Used only when admin_ui_enabled() returns true; otherwise the legacy chrome runs.
 */

function admin_ui_assets(): void
{
    $css = function_exists('admin_public_url')
        ? admin_public_url('assets/css/admin-ui/admin-ui.css?v=' . admin_ui_version())
        : '../assets/css/admin-ui/admin-ui.css?v=' . admin_ui_version();
    $js = function_exists('admin_public_url')
        ? admin_public_url('assets/js/admin-ui/shell.js?v=' . admin_ui_version())
        : '../assets/js/admin-ui/shell.js?v=' . admin_ui_version();
    echo '  <link rel="stylesheet" href="' . e($css) . '">' . "\n";
    echo '  <script defer src="' . e($js) . '"></script>' . "\n";
}

function admin_ui_flat_nav(): array
{
    $groups = function_exists('admin_allowed_nav_groups') ? admin_allowed_nav_groups(db()) : [];
    $flat = [];
    foreach ($groups as $items) {
        foreach ($items as $item) {
            $flat[] = $item;
        }
    }
    return $flat;
}

function admin_ui_page_start(string $title, array $options = []): void
{
    $GLOBALS['admin_ui_active'] = true;
    if (!headers_sent()) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    }

    $activeKey = function_exists('admin_active_key') ? admin_active_key($options['active'] ?? null) : '';
    $description = (string) ($options['description'] ?? '');
    $groups = function_exists('admin_allowed_nav_groups') ? admin_allowed_nav_groups(db()) : [];
    $flat = admin_ui_flat_nav();
    $user = function_exists('current_user') ? current_user(db()) : null;
    $userName = (string) ($options['user_name'] ?? ($user['name'] ?? 'Administrator'));
    $logo = function_exists('app_admin_logo_url') ? app_admin_logo_url() : '';
    $logoutAction = function_exists('admin_logout_action_path') ? admin_logout_action_path() : 'admin.php';
    $isActive = static function (string $href) use ($activeKey): bool {
        return function_exists('admin_nav_item_is_active') && admin_nav_item_is_active($activeKey, $href);
    };
    ?>
<!DOCTYPE html>
<html lang="en" data-admin-ui>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#146B3A">
  <title><?= e($title) ?> - NATCODEV Admin</title>
  <?php admin_ui_assets(); ?>
</head>
<body>
  <a class="a-skip" href="#a-main">Skip to content</a>
  <div class="a-shell">
    <aside class="a-sidebar" id="a-sidebar" aria-label="Admin navigation">
      <a class="a-sidebar__brand" href="<?= e(function_exists('admin_chrome_url') ? admin_chrome_url('index.php') : 'index.php') ?>">
        <?php if ($logo !== ''): ?><img src="<?= e($logo) ?>" alt="NATCODEV"><?php endif; ?>
        <span><strong>NATCODEV</strong><small>Admin Console</small></span>
      </a>
      <nav class="a-nav">
        <?php foreach ($groups as $groupLabel => $items): ?>
          <div class="a-nav__label"><?= e((string) $groupLabel) ?></div>
          <?php foreach ($items as $item): ?>
            <?php $href = (string) ($item['href'] ?? '#'); ?>
            <a href="<?= e(function_exists('admin_chrome_url') ? admin_chrome_url($href) : $href) ?>" <?= $isActive($href) ? 'aria-current="page"' : '' ?>><?= e((string) ($item['label'] ?? '')) ?></a>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </nav>
    </aside>
    <div class="a-scrim" aria-hidden="true"></div>
    <div class="a-content">
      <header class="a-topbar">
        <button class="a-iconbtn a-menu-toggle" type="button" aria-controls="a-sidebar" aria-expanded="false" aria-label="Toggle navigation">&#9776;</button>
        <span class="a-topbar__title"><?= e($title) ?></span>
        <span class="a-topbar__spacer"></span>
        <span class="a-user"><?= e($userName) ?></span>
        <form method="post" action="<?= e($logoutAction) ?>">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="logout" value="1">
          <button class="a-btn a-btn--secondary a-btn--sm" type="submit">Logout</button>
        </form>
      </header>
      <main class="a-main" id="a-main">
        <section class="a-page-title">
          <span class="a-kicker">Admin Console</span>
          <h1><?= e($title) ?></h1>
          <?php if ($description !== ''): ?><p><?= e($description) ?></p><?php endif; ?>
          <?php if (!empty($options['action_html'])): ?><div><?= $options['action_html'] ?></div><?php endif; ?>
        </section>
    <?php
}

function admin_ui_page_end(): void
{
    $flat = admin_ui_flat_nav();
    $bottom = array_slice($flat, 0, 5);
    $activeKey = function_exists('admin_active_key') ? admin_active_key() : '';
    ?>
      </main>
    </div>
  </div>
  <nav class="a-bottombar" aria-label="Quick navigation">
    <?php foreach ($bottom as $item): ?>
      <?php $href = (string) ($item['href'] ?? '#'); ?>
      <a href="<?= e(function_exists('admin_chrome_url') ? admin_chrome_url($href) : $href) ?>" <?= (function_exists('admin_nav_item_is_active') && admin_nav_item_is_active($activeKey, $href)) ? 'aria-current="page"' : '' ?>><?= e((string) ($item['label'] ?? '')) ?></a>
    <?php endforeach; ?>
  </nav>
</body>
</html>
    <?php
}

/**
 * Auth layout (v2): standalone centered card for the login / OTP screens.
 * Same v2 shell markers and assets as the full page, but no sidebar/topbar -
 * a single centered card with a brand lockup. Used only when the area is enabled.
 */
function admin_ui_auth_page_start(string $title, array $options = []): void
{
    $GLOBALS['admin_ui_active'] = true;
    if (!headers_sent()) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    }

    $brand = (string) ($options['brand'] ?? 'Admin Console');
    $logo = function_exists('app_admin_logo_url') ? app_admin_logo_url() : '';
    ?>
<!DOCTYPE html>
<html lang="en" data-admin-ui>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#146B3A">
  <title><?= e($title) ?> - NATCODEV Admin</title>
  <?php admin_ui_assets(); ?>
  <style>
    [data-admin-ui] body { display:flex; align-items:center; justify-content:center; min-height:100vh; padding:var(--a-sp-6) var(--a-page-x); background:linear-gradient(135deg, rgba(20,107,58,.10), rgba(27,58,87,.12)), var(--a-bg); }
    [data-admin-ui] .a-auth { width:100%; max-width:28rem; }
    [data-admin-ui] .a-auth__brand { display:flex; align-items:center; justify-content:center; gap:var(--a-sp-3); margin-bottom:var(--a-sp-5); }
    [data-admin-ui] .a-auth__brand img { width:3.5rem; height:3.5rem; border-radius:50%; background:#fff; object-fit:contain; border:1px solid var(--a-border); }
    [data-admin-ui] .a-auth__brand strong { display:block; font-size:1.15rem; letter-spacing:.02em; color:var(--a-primary); line-height:1.2; }
    [data-admin-ui] .a-auth__brand small { display:block; color:var(--a-ink-2); font-size:var(--a-fs-micro); text-transform:uppercase; letter-spacing:.08em; }
    [data-admin-ui] .a-auth__card { padding:var(--a-sp-8); }
    [data-admin-ui] .password-field { position:relative; }
    [data-admin-ui] .password-field input { padding-right:4.5rem; }
    [data-admin-ui] .password-toggle { position:absolute; right:var(--a-sp-2); top:50%; transform:translateY(-50%); width:auto; min-height:2rem; margin:0; padding:0 var(--a-sp-3); border:0; border-radius:6px; background:var(--a-primary-soft); color:var(--a-primary); font-weight:700; cursor:pointer; }
    [data-admin-ui] .error, [data-admin-ui] .err { color:var(--a-danger); background:var(--a-danger-bg); border:1px solid var(--a-danger); padding:var(--a-sp-2) var(--a-sp-3); border-radius:var(--a-radius); }
    [data-admin-ui] .ok { color:var(--a-success); background:var(--a-success-bg); border:1px solid var(--a-success); padding:var(--a-sp-2) var(--a-sp-3); border-radius:var(--a-radius); }
    [data-admin-ui] .home-link, [data-admin-ui] .restart-link { display:inline-block; margin-top:var(--a-sp-4); color:var(--a-primary); font-weight:700; }
    [data-admin-ui] .otp-grid { display:grid; grid-template-columns:repeat(6, minmax(0,1fr)); gap:var(--a-sp-2); margin:var(--a-sp-4) 0; }
    [data-admin-ui] .otp-grid input { text-align:center; font-weight:800; padding:var(--a-sp-2) 2px; }
    [data-admin-ui] .links { margin-top:var(--a-sp-4); display:flex; justify-content:space-between; gap:var(--a-sp-3); }
    [data-admin-ui] .resend-form { margin-top:var(--a-sp-3); }
    [data-admin-ui] .resend-form button { background:var(--a-surface); color:var(--a-primary); border:1px solid var(--a-border-strong); }
    @media (max-width: 30rem) { [data-admin-ui] .a-auth__card { padding:var(--a-sp-5); } }
  </style>
</head>
<body>
  <main class="a-main a-auth" style="max-width:28rem">
    <div class="a-auth__brand">
      <?php if ($logo !== ''): ?><img src="<?= e($logo) ?>" alt="NATCODEV"><?php endif; ?>
      <span><strong>NATCODEV</strong><small><?= e($brand) ?></small></span>
    </div>
    <div class="a-card a-auth__card">
    <?php
}

function admin_ui_auth_page_end(): void
{
    ?>
    </div>
  </main>
</body>
</html>
    <?php
}
