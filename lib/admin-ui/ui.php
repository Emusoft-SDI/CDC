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
