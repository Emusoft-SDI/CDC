<?php

function status_label(string $status): string
{
    return ucwords(str_replace('_', ' ', $status));
}



if (!function_exists('admin_csp_nonce')) {
    /**
     * Per-request CSP nonce (Phase 9). Emitted on the shell's own inline <style>
     * and <script> tags so the Content-Security-Policy can move off
     * 'unsafe-inline' once every page's inline scripts carry the nonce.
     */
    function admin_csp_nonce(): string
    {
        static $nonce = null;
        if ($nonce === null) {
            try {
                $nonce = base64_encode(random_bytes(16));
            } catch (Throwable $e) {
                $nonce = 'nc' . bin2hex(random_bytes(8));
            }
        }
        return $nonce;
    }
}

function admin_chrome_base_path(): string
{
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/admin/index.php'));
    $marker = '/admin/';
    $pos = strpos($scriptName, $marker);
    if ($pos === false) {
        return 'admin/';
    }

    return substr($scriptName, 0, $pos + strlen($marker));
}

function admin_public_base_path(): string
{
    return rtrim(dirname(rtrim(admin_chrome_base_path(), '/')), '/');
}

function admin_chrome_url(string $href): string
{
    $href = trim($href);
    if ($href === '' || $href[0] === '#' || preg_match('/^[a-z][a-z0-9+.-]*:/i', $href) || str_starts_with($href, '//')) {
        return $href;
    }
    if (str_starts_with($href, '/')) {
        return $href;
    }

    if (str_starts_with($href, '../')) {
        $publicBase = admin_public_base_path();
        while (str_starts_with($href, '../')) {
            $href = substr($href, 3);
        }
        return ($publicBase === '' ? '' : $publicBase) . '/' . ltrim($href, '/');
    }

    return admin_chrome_base_path() . ltrim($href, '/');
}

function admin_public_url(string $href): string
{
    $href = trim($href);
    if ($href === '' || $href[0] === '#' || preg_match('/^[a-z][a-z0-9+.-]*:/i', $href) || str_starts_with($href, '//')) {
        return $href;
    }
    if (str_starts_with($href, '/')) {
        return $href;
    }

    return admin_public_base_path() . '/' . ltrim($href, '/');
}

function admin_active_key(?string $active = null): string
{
    $active = trim((string) $active);
    if ($active !== '') {
        return ltrim(str_replace('\\', '/', $active), './');
    }

    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/admin/index.php'));
    $marker = '/admin/';
    $pos = strpos($scriptName, $marker);
    if ($pos === false) {
        return basename($scriptName);
    }

    return ltrim(substr($scriptName, $pos + strlen($marker)), '/');
}

function admin_nav_item_is_active(string $active, string $href): bool
{
    $activePath = ltrim(str_replace('\\', '/', $active), './');
    $hrefPath = ltrim(str_replace('\\', '/', $href), './');
    $activePathOnly = strtok($activePath, '?') ?: $activePath;
    $hrefPathOnly = strtok($hrefPath, '?') ?: $hrefPath;

    if ($activePath === $hrefPath || $activePathOnly === $hrefPathOnly) {
        return true;
    }

    if (str_ends_with($hrefPathOnly, '/') && str_starts_with($activePathOnly, $hrefPathOnly)) {
        return true;
    }

    return false;
}

/**
 * Lightweight "needs attention" counts for the shared admin topbar.
 *
 * Every lookup is guarded by a table/column existence check and wrapped so a
 * missing module can never break the chrome.
 *
 * @return array<string,int>
 */
function admin_shell_attention_counts(PDO $pdo): array
{
    // Phase 3 wiring: these counters run several COUNT(*) queries on every admin
    // page load. Cache them in the metrics store with a short TTL; fall back to a
    // direct compute when the metrics library is unavailable.
    if (!function_exists('admin_metric')) {
        $metricsLib = __DIR__ . '/../admin-metrics.php';
        if (is_file($metricsLib)) {
            require_once $metricsLib;
        }
    }
    if (function_exists('admin_metric')) {
        return admin_metric($pdo, 'shell:attention_counts', 90, static fn(): array => admin_shell_attention_counts_uncached($pdo));
    }

    return admin_shell_attention_counts_uncached($pdo);
}

/**
 * Uncached computation behind admin_shell_attention_counts().
 *
 * @return array<string,int>
 */
function admin_shell_attention_counts_uncached(PDO $pdo): array
{
    $count = static function (string $table, string $where = '1=1') use ($pdo): int {
        if (!app_table_exists($pdo, $table)) {
            return 0;
        }
        try {
            return (int) $pdo->query("SELECT COUNT(*) FROM {$table} WHERE {$where}")->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    };
    $soft = static fn(string $table): string => app_column_exists($pdo, $table, 'deleted_at') ? ' AND deleted_at IS NULL' : '';

    $documents = $count('document_requirements', "verification_status IN ('pending','submitted','under_review','needs_review')" . $soft('document_requirements'));
    if ($documents === 0 && app_table_exists($pdo, 'user_documents')) {
        $documents = $count('user_documents', "status IN ('pending','submitted','under_review')" . $soft('user_documents'));
    }
    $tickets = $count('support_tickets', "status IN ('open','in_progress','waiting_on_user','escalated')");
    $applications = $count('applications', "status IN ('pending','submitted','under_review')" . $soft('applications'));
    $ordersToday = $count('marketplace_orders', 'DATE(created_at) = CURDATE()');
    $notifications = app_table_exists($pdo, 'notification_logs')
        ? $count('notification_logs', "status IN ('pending','failed','queued')")
        : ($tickets + $applications);
    $deleteApprovals = function_exists('admin_pending_delete_request_count') ? admin_pending_delete_request_count($pdo) : 0;

    return [
        'documents' => $documents,
        'tickets' => $tickets,
        'applications' => $applications,
        'ordersToday' => $ordersToday,
        'notifications' => $notifications,
        'deleteApprovals' => $deleteApprovals,
    ];
}

/**
 * Shared admin chrome. Renders the NATCODEV Workspace Hub master design
 * (admin/index.php) so every admin page shares one uniform shell.
 *
 * Options (unchanged API):
 *   active          - nav key used to highlight the current item
 *   description     - subtitle shown under the page title
 *   wide            - kept for back-compat (content is already fluid)
 *   chrome          - false renders just the html shell + <main> wrapper
 *   action_html     - extra markup placed beside the page title
 *   css             - extra CSS injected into the page <style>
 *   location_picker - loads lib/location-picker.js in the footer
 */
function admin_page_start(string $title, array $options = []): void
{
    // Phase 9: harden this page's output for a nonce-based CSP. Every script/style
    // tag gets a nonce and legacy inline handlers are rewritten to data attributes
    // (behaviour implemented by assets/js/nc-csp.js). The buffer is flushed by
    // admin_page_end().
    $cspLib = __DIR__ . '/../admin-csp.php';
    if (!function_exists('admin_csp_harden') && is_file($cspLib)) {
        require_once $cspLib;
    }
    if (function_exists('admin_csp_harden') && !isset($GLOBALS['nc_csp_ob_base'])) {
        if (function_exists('admin_csp_send_header')) {
            admin_csp_send_header();
        }
        $GLOBALS['nc_csp_ob_base'] = ob_get_level();
        ob_start('admin_csp_harden');
    }

    $active = admin_active_key($options['active'] ?? null);
    $description = (string) ($options['description'] ?? '');
    $wide = !empty($options['wide']);
    $chrome = (bool) ($options['chrome'] ?? true);
    $GLOBALS['admin_page_chrome'] = $chrome;
    $GLOBALS['admin_page_uses_location_picker'] = !empty($options['location_picker']);

    $pdo = db();
    $navGroups = function_exists('admin_allowed_nav_groups') ? admin_allowed_nav_groups($pdo) : [];
    $user = function_exists('current_user') ? (current_user($pdo) ?: []) : [];
    $userName = trim((string) ($user['name'] ?? '')) ?: 'Administrator';
    $roleRaw = (string) ($user['platform_role'] ?? $user['role'] ?? 'admin');
    $roleLabel = function_exists('status_label') ? status_label($roleRaw === '' ? 'admin' : $roleRaw) : 'Administrator';
    $avatarRaw = trim((string) ($user['avatar'] ?? $user['photo_path'] ?? ''));
    $avatarUrl = $avatarRaw !== '' ? admin_public_url($avatarRaw) : '';
    $initial = strtoupper(substr($userName, 0, 1));
    $isSuperAdmin = function_exists('admin_current_user_is_super_admin') ? admin_current_user_is_super_admin($pdo) : false;
    $homeUrl = admin_public_url('index.php');
    $hubUrl = admin_chrome_url('index.php');

    if ($chrome) {
        $attention = admin_shell_attention_counts($pdo);
        $featureOk = static fn(?string $feature): bool => $feature === null || (function_exists('admin_feature_is_allowed') && admin_feature_is_allowed($pdo, $feature));

        $notifyItems = array_values(array_filter([
            ['label' => 'Document reviews', 'hint' => 'Awaiting verification', 'value' => $attention['documents'], 'href' => 'document-verification.php', 'feature' => 'documents'],
            ['label' => 'Open support tickets', 'hint' => 'Needs a response', 'value' => $attention['tickets'], 'href' => 'support.php', 'feature' => 'support'],
            ['label' => 'Pending applications', 'hint' => 'In the registry queue', 'value' => $attention['applications'], 'href' => 'admin.php', 'feature' => 'applications'],
            ['label' => 'Delete approvals', 'hint' => 'Super-admin sign-off', 'value' => $attention['deleteApprovals'], 'href' => '../super-admin/index.php?view=approvals', 'feature' => $isSuperAdmin ? null : '__deny__'],
        ], static fn(array $item): bool => $featureOk($item['feature'])));

        $messageItems = array_values(array_filter([
            ['label' => 'Support queue', 'hint' => 'Open & escalated tickets', 'value' => $attention['tickets'], 'href' => 'support.php', 'feature' => 'support'],
            ['label' => 'Marketplace orders', 'hint' => 'Received today', 'value' => $attention['ordersToday'], 'href' => 'marketplace.php?section=orders', 'feature' => 'marketplace'],
            ['label' => 'Notification log', 'hint' => 'Delivery & template events', 'value' => $attention['notifications'], 'href' => 'notifications.php', 'feature' => 'notifications'],
        ], static fn(array $item): bool => $featureOk($item['feature'])));

        $quickItems = array_values(array_filter([
            ['label' => 'Search everything', 'icon' => 'fa-magnifying-glass', 'href' => 'search.php', 'feature' => 'dashboard'],
            ['label' => 'Generate reports', 'icon' => 'fa-chart-line', 'href' => 'reports.php', 'feature' => 'reports'],
            ['label' => 'Send notification', 'icon' => 'fa-bullhorn', 'href' => 'notifications.php', 'feature' => 'notifications'],
            ['label' => 'Import users', 'icon' => 'fa-file-import', 'href' => 'import-users.php', 'feature' => 'imports'],
            ['label' => 'Create backup', 'icon' => 'fa-box-archive', 'href' => 'backups.php', 'feature' => 'backups'],
            ['label' => 'Review approvals', 'icon' => 'fa-shield-halved', 'href' => '../super-admin/index.php?view=approvals', 'feature' => $isSuperAdmin ? null : '__deny__'],
        ], static fn(array $item): bool => $featureOk($item['feature'])));

        $notificationTotal = 0;
        foreach ($notifyItems as $item) {
            $notificationTotal += (int) $item['value'];
        }
        $messageTotal = 0;
        foreach ($messageItems as $item) {
            $messageTotal += (int) $item['value'];
        }
    }
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($title) ?> - NATCODEV Admin</title>
  <style id="nc-critical" nonce="<?= e(admin_csp_nonce()) ?>">
/* Critical shell fallback: keeps the hub grid, KPI cards and collapse cards
   correct even if admin-hub.css is stale, truncated by a proxy or 404s.
   admin-hub.css is linked afterwards and overrides these when it loads. */
.nc-hub{display:grid;grid-template-columns:292px minmax(0,1fr);min-height:100vh;background:#f7faf8}
.nc-side{background:linear-gradient(180deg,#074b2a,#003719);color:#fff;padding:18px 16px}
.nc-content{min-width:0}
.kpi-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin:4px 0 22px}
.kpi-card{position:relative;display:flex;justify-content:space-between;gap:14px;align-items:flex-start;background:#fff;border:1px solid rgba(16,24,40,.08);border-radius:14px;box-shadow:0 14px 34px rgba(16,24,40,.07);padding:18px 18px 18px 22px;min-height:128px;overflow:hidden}
.kpi-card .kpi-label{display:block;text-transform:uppercase;letter-spacing:.05em;font-size:.72rem;font-weight:900;color:#667085}
.kpi-card .kpi-value{display:block;font-size:1.95rem;font-weight:900;color:#0b1f16;margin-top:10px;line-height:1.02;letter-spacing:-.02em}
.kpi-card .kpi-sub{display:flex;align-items:center;gap:6px;color:#079455;font-size:.78rem;font-weight:850;margin-top:8px}
.kpi-card .kpi-icon{width:58px;height:58px;border-radius:50%;display:grid;place-items:center;background:#e8f6ec;color:#006838;font-size:1.5rem;flex:none;margin-top:18px}
.collapse-card{border:1px solid #dfe7e2;border-radius:14px;background:#fff;margin:0 0 20px;box-shadow:0 10px 26px rgba(16,24,40,.06);overflow:hidden}
.collapse-card>summary{display:flex;align-items:center;gap:12px;padding:16px 18px;cursor:pointer;list-style:none;font-weight:900}
.collapse-card>summary::-webkit-details-marker{display:none}
.collapse-card .collapse-body{padding:0 18px 18px}
@media(max-width:1024px){.nc-hub{grid-template-columns:1fr}.nc-side{position:relative;height:auto}}
  </style>
  <?= $options['head_pre'] ?? '' ?>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <?php $ncHubCssVersion = @filemtime(__DIR__ . '/../../assets/css/admin-hub.css') ?: '20261005'; ?>
  <link rel="stylesheet" href="<?= e(admin_public_url('assets/css/admin-hub.css?v=' . $ncHubCssVersion)) ?>">
  <?= $options['head_html'] ?? '' ?>
  <style><?= $options['css'] ?? '' ?></style>
</head>
<body>
<div class="admin-action-overlay" aria-hidden="true"></div>
<div class="admin-working-toast" role="status" aria-live="polite">Processing request...</div>
<?php
$topbarOnly = !empty($options['topbar_only']);
$GLOBALS['admin_page_topbar_only'] = $topbarOnly;
?>
<?php if ($chrome): ?>
<div class="<?= $topbarOnly ? 'nc-topbar-page' : 'nc-hub' ?>">
<?php if (!$topbarOnly): ?>
  <aside class="nc-side">
    <a class="nc-brand" href="<?= e(admin_chrome_url('index.php')) ?>">
      <img src="<?= e(app_admin_logo_url()) ?>" alt="NATCODEV">
      <span><strong>NATCODEV</strong><small>Admin Workspace Hub</small></span>
    </a>
    <div class="nc-person">
      <span class="nc-avatar"><?php if ($avatarUrl !== ''): ?><img src="<?= e($avatarUrl) ?>" alt=""><?php else: ?><?= e($initial) ?><?php endif; ?></span>
      <span><b><?= e($userName) ?></b><span><?= e($roleLabel) ?></span><span class="nc-online"><i class="fas fa-circle"></i> Online</span></span>
    </div>
    <div class="nc-nav-title">Main Navigation</div>
    <nav class="nc-nav" aria-label="Admin navigation">
      <a class="<?= $active === 'index.php' ? 'nc-active' : '' ?>" href="<?= e(admin_chrome_url('index.php')) ?>"><i class="fas fa-house"></i> Workspace Hub</a>
      <?php foreach ($navGroups as $groupLabel => $items): ?>
        <?php $groupActive = array_reduce($items, static fn(bool $carry, array $item): bool => $carry || admin_nav_item_is_active($active, (string) $item['href']), false); ?>
        <details<?= $groupActive ? ' open' : '' ?>>
          <summary><i class="fas fa-folder"></i> <?= e((string) $groupLabel) ?></summary>
          <?php foreach ($items as $item): ?>
            <a class="<?= admin_nav_item_is_active($active, (string) $item['href']) ? 'nc-active' : '' ?>" href="<?= e(admin_chrome_url((string) $item['href'])) ?>"><?= e((string) $item['label']) ?></a>
          <?php endforeach; ?>
        </details>
      <?php endforeach; ?>
    </nav>
    <div class="nc-quick">
      <div class="nc-nav-title">Quick Actions</div>
      <nav class="nc-nav">
        <a href="<?= e(admin_chrome_url('index.php')) ?>"><i class="fas fa-gauge-high"></i> Dashboard</a>
        <a href="<?= e(admin_chrome_url('profile.php')) ?>"><i class="fas fa-user-gear"></i> My Profile</a>
        <a href="<?= e(admin_chrome_url('notifications.php')) ?>"><i class="fas fa-bullhorn"></i> Notifications</a>
        <a href="<?= e(admin_chrome_url('reports.php')) ?>"><i class="fas fa-chart-line"></i> Reports</a>
      </nav>
    </div>
    <div class="nc-platform"><strong><i class="fas fa-shield-halved"></i> NATCODEV Platform</strong><p>Every admin workspace shares one uniform, access-controlled design.</p></div>
  </aside>
  <section class="nc-main">
<?php else: ?>
  <section class="nc-topbar-body">
<?php endif; ?>
    <header class="nc-top">
      <div class="nc-topbar-left">
        <a class="nc-quicklink primary" href="<?= e($homeUrl) ?>" title="Back to the NATCODEV home page"><i class="fas fa-house-chimney"></i><span>Home</span></a>
        <a class="nc-quicklink" href="<?= e($hubUrl) ?>" title="Admin Workspace Hub"><i class="fas fa-gauge-high"></i><span>Workspace Hub</span></a>
        <form class="nc-search" action="<?= e(admin_chrome_url('search.php')) ?>" method="get"><i class="fas fa-search"></i><input name="q" placeholder="Search growers, applications, documents, courses..."><span class="nc-kbd">CTRL + K</span></form>
      </div>
      <div class="nc-top-actions">
        <div class="nc-top-menu">
          <button class="nc-menu-trigger nc-icon-trigger" type="button" data-nc-menu-toggle aria-haspopup="true" aria-label="Notifications"><i class="far fa-bell"></i><?php if ($notificationTotal > 0): ?><span class="nc-dot"><?= (int) min(99, $notificationTotal) ?></span><?php endif; ?></button>
          <div class="nc-dropdown">
            <h3>Notifications<small>Items that need your attention</small></h3>
            <?php foreach ($notifyItems as $item): ?>
              <a href="<?= e(admin_chrome_url((string) $item['href'])) ?>"><span><?= e((string) $item['label']) ?><small><?= e((string) $item['hint']) ?></small></span><span class="nc-count<?= (int) $item['value'] > 0 ? ' alert' : '' ?>"><?= (int) $item['value'] ?></span></a>
            <?php endforeach; ?>
            <?php if (!$notifyItems): ?><span class="nc-empty">You are all caught up.</span><?php endif; ?>
            <a class="nc-more" href="<?= e(admin_chrome_url('notifications.php')) ?>"><span>Notification log</span><i class="fas fa-arrow-right"></i></a>
          </div>
        </div>
        <div class="nc-top-menu">
          <button class="nc-menu-trigger nc-icon-trigger" type="button" data-nc-menu-toggle aria-haspopup="true" aria-label="Messages and queues"><i class="far fa-envelope"></i><?php if ($messageTotal > 0): ?><span class="nc-dot"><?= (int) min(99, $messageTotal) ?></span><?php endif; ?></button>
          <div class="nc-dropdown">
            <h3>Messages &amp; Queues<small>Live workspace workload</small></h3>
            <?php foreach ($messageItems as $item): ?>
              <a href="<?= e(admin_chrome_url((string) $item['href'])) ?>"><span><?= e((string) $item['label']) ?><small><?= e((string) $item['hint']) ?></small></span><span class="nc-count"><?= (int) $item['value'] ?></span></a>
            <?php endforeach; ?>
            <?php if (!$messageItems): ?><span class="nc-empty">No queued items.</span><?php endif; ?>
          </div>
        </div>
        <div class="nc-top-menu">
          <button class="nc-menu-trigger" type="button" data-nc-menu-toggle aria-haspopup="true"><i class="fas fa-bolt"></i> Quick Command <i class="fas fa-chevron-down"></i></button>
          <div class="nc-dropdown">
            <h3>Quick Command<small>Jump straight to a task</small></h3>
            <?php foreach ($quickItems as $item): ?>
              <a href="<?= e(admin_chrome_url((string) $item['href'])) ?>"><span><i class="fas <?= e((string) $item['icon']) ?>"></i> <?= e((string) $item['label']) ?></span><i class="fas fa-arrow-right"></i></a>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="nc-top-menu">
          <button class="nc-user" type="button" data-nc-menu-toggle aria-haspopup="true"><span class="nc-avatar"><?php if ($avatarUrl !== ''): ?><img src="<?= e($avatarUrl) ?>" alt=""><?php else: ?><?= e($initial) ?><?php endif; ?></span><?= e($userName) ?> <i class="fas fa-chevron-down"></i></button>
          <div class="nc-dropdown">
            <h3><?= e($userName) ?><small><?= e($roleLabel) ?></small></h3>
            <a href="<?= e(admin_chrome_url('profile.php')) ?>"><span><i class="fas fa-user-gear"></i> My Profile</span></a>
            <a href="<?= e($hubUrl) ?>"><span><i class="fas fa-gauge-high"></i> Workspace Hub</span></a>
            <a href="<?= e($homeUrl) ?>"><span><i class="fas fa-house-chimney"></i> Public Home Page</span></a>
            <?php if (function_exists('admin_feature_is_allowed') && admin_feature_is_allowed($pdo, 'settings')): ?><a href="<?= e(admin_chrome_url('settings.php')) ?>"><span><i class="fas fa-gear"></i> Admin Settings</span></a><?php endif; ?>
            <?php if ($isSuperAdmin): ?><a href="<?= e(admin_chrome_url('../super-admin/index.php')) ?>"><span><i class="fas fa-shield-halved"></i> Super Admin</span></a><?php endif; ?>
            <form method="post" action="<?= e(admin_logout_action_path()) ?>" style="margin:0"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="logout" value="1"><button type="submit"><span><i class="fas fa-right-from-bracket"></i> Logout</span></button></form>
          </div>
        </div>
      </div>
    </header>
    <main class="admin-main <?= $topbarOnly ? 'nc-topbar-content' : 'nc-content' ?>">
      <?php if (!empty($options['breadcrumbs']) && is_array($options['breadcrumbs'])): ?>
        <?= admin_breadcrumbs($options['breadcrumbs']) ?>
      <?php endif; ?>
      <?php if (!$topbarOnly): ?>
      <section class="nc-head">
        <div><h1><?= e($title) ?></h1><?php if ($description !== ''): ?><p><?= e($description) ?></p><?php endif; ?></div>
        <?php if (!empty($options['action_html'])): ?><div><?= $options['action_html'] ?></div><?php endif; ?>
      </section>
      <?php endif; ?>
<?php else: ?>
<div class="nc-bare">
  <main class="admin-main">
<?php endif; ?>
<?php
}


function admin_page_end(): void
{
    $footerItems = function_exists('admin_footer_nav_items') ? admin_footer_nav_items(db()) : [];
    $chrome = !empty($GLOBALS['admin_page_chrome']);
    $topbarOnly = !empty($GLOBALS['admin_page_topbar_only']);
    ?>
<?php if ($chrome): ?>
    </main>
<?php if (!$topbarOnly): ?>
    <footer class="nc-footer">
      <div>
        <strong>NATCODEV Admin Console</strong>
        <div class="meta">Unified workspace hub for registry, finance, field operations, learning and support.</div>
      </div>
      <nav class="footer-links" aria-label="Admin quick links" style="display:flex;gap:10px;flex-wrap:wrap">
        <?php foreach ($footerItems as $item): ?>
          <a href="<?= e(admin_chrome_url((string) $item['href'])) ?>"><?= e((string) $item['label']) ?></a>
        <?php endforeach; ?>
      </nav>
    </footer>
<?php endif; ?>
  </section>
</div>
<?php else: ?>
  </main>
</div>
<?php endif; ?>
<?php if (!empty($GLOBALS['admin_page_uses_location_picker'])): ?>
<script src="<?= e(admin_public_url('lib/location-picker.js')) ?>"></script>
<?php endif; ?>
<script nonce="<?= e(admin_csp_nonce()) ?>">
(function () {
  const menus = Array.from(document.querySelectorAll('.nc-top-menu'));
  function closeMenus(except) {
    menus.forEach((menu) => {
      if (menu !== except) menu.classList.remove('nc-open');
    });
  }
  document.querySelectorAll('[data-nc-menu-toggle]').forEach((button) => {
    button.addEventListener('click', (event) => {
      event.stopPropagation();
      const menu = button.closest('.nc-top-menu');
      const willOpen = menu && !menu.classList.contains('nc-open');
      closeMenus(menu);
      if (menu && willOpen) menu.classList.add('nc-open');
    });
  });
  document.addEventListener('click', () => closeMenus(null));
  document.addEventListener('keydown', (event) => { if (event.key === 'Escape') closeMenus(null); });

  const nav = document.querySelector('.nc-nav');
  const details = nav ? Array.from(nav.querySelectorAll('details')) : [];
  details.forEach((item) => {
    const summary = item.querySelector('summary');
    if (!summary) return;
    summary.addEventListener('click', () => {
      window.setTimeout(() => {
        if (item.open) details.forEach((other) => { if (other !== item) other.removeAttribute('open'); });
      }, 0);
    });
  });

  document.querySelectorAll('form').forEach((form) => {
    form.addEventListener('submit', (event) => {
      if (event.defaultPrevented) return;
      if (form.dataset.submitting === '1') { event.preventDefault(); return; }
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
<div class="nc-palette" id="nc-palette" hidden>
  <div class="nc-palette-box" role="dialog" aria-modal="true" aria-label="Command palette">
    <input type="text" id="nc-palette-input" placeholder="Jump to a module or record…  (Esc to close)" autocomplete="off">
    <div class="nc-palette-results" id="nc-palette-results"></div>
  </div>
</div>
<script nonce="<?= e(admin_csp_nonce()) ?>">
(function () {
  var palette = document.getElementById('nc-palette');
  if (!palette) return;
  var input = document.getElementById('nc-palette-input');
  var results = document.getElementById('nc-palette-results');
  var endpoint = <?= json_encode(admin_chrome_url('search.php'), JSON_UNESCAPED_SLASHES) ?>;

  function openPalette() { palette.hidden = false; results.innerHTML = ''; input.focus(); }
  function closePalette() { palette.hidden = true; }
  function render(items) {
    results.innerHTML = '';
    if (!items.length) { results.textContent = 'No matches.'; return; }
    items.forEach(function (it) {
      var a = document.createElement('a');
      a.href = it.href;
      a.className = 'nc-palette-item';
      var strong = document.createElement('strong');
      strong.textContent = it.title;
      var small = document.createElement('small');
      small.textContent = it.kind || 'module';
      a.appendChild(strong);
      a.appendChild(small);
      results.appendChild(a);
    });
  }

  var timer = null;
  input.addEventListener('input', function () {
    window.clearTimeout(timer);
    var q = input.value.trim();
    if (q.length < 2) { results.innerHTML = ''; return; }
    timer = window.setTimeout(function () {
      fetch(endpoint + '?format=json&q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          var items = (data.modules || []).map(function (m) { return { title: m.title, href: m.href, kind: 'module' }; })
            .concat((data.records || []).map(function (r) { return { title: r.title, href: r.href, kind: r.kind }; }));
          render(items.slice(0, 12));
        })
        .catch(function () {});
    }, 180);
  });

  document.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) { e.preventDefault(); openPalette(); }
    if (e.key === 'Escape' && !palette.hidden) { closePalette(); }
  });
  palette.addEventListener('click', function (e) { if (e.target === palette) closePalette(); });
  document.querySelectorAll('[data-nc-palette-open]').forEach(function (b) { b.addEventListener('click', openPalette); });
})();
</script>
<script src="<?= e(admin_public_url('assets/js/nc-csp.js')) ?>" nonce="<?= e(admin_csp_nonce()) ?>"></script>
</body>
</html>
<?php
    // Phase 9: flush the CSP hardening buffer (and any buffers a page opened).
    if (isset($GLOBALS['nc_csp_ob_base'])) {
        $base = (int) $GLOBALS['nc_csp_ob_base'];
        while (ob_get_level() > $base) {
            ob_end_flush();
        }
        unset($GLOBALS['nc_csp_ob_base']);
    }
}
