<?php
declare(strict_types=1);

function super_admin_roles(): array
{
    return [
        'super_admin' => 'Super Administrator',
        'national_coordinator' => 'National Coordinator',
        'state_coordinator' => 'State Coordinator',
        'investor' => 'Investor',
        'admin' => 'Administrator',
        'support_agent' => 'Support Agent',
        'field_agent' => 'Field Agent',
        'agronomist' => 'Agronomist',
        'agric_extensionist' => 'Agric Extensionist',
        'grower' => 'Grower',
        'seller' => 'Marketplace Seller',
        'buyer' => 'Buyer',
        'provider' => 'Input / Service Provider',
        'learner' => 'Academy Learner',
    ];
}

function super_admin_views(): array
{
    return [
        'overview' => [
            'label' => 'Overview',
            'hint' => 'Role counts and governance snapshot',
        ],
        'users' => [
            'label' => 'User Governance',
            'hint' => 'Create, review, reset, and recover privileged access',
        ],
        'finance' => [
            'label' => 'Finance Oversight',
            'hint' => 'Wallets, withdrawals, revenue, and settlements',
        ],
        'profile' => [
            'label' => 'My Profile',
            'hint' => 'Own profile, password, and secure exit',
        ],
        'disaster' => [
            'label' => 'Disaster Recovery',
            'hint' => 'Backups, site nodes, and sync evidence',
        ],
        'controls' => [
            'label' => 'Access & Policy',
            'hint' => 'Permissions, onboarding, announcements, and audit',
        ],
    ];
}

function super_admin_nav_groups(): array
{
    return [
        'Command' => [
            [
                'label' => 'Overview',
                'hint' => 'Snapshot, role counts, and privileged access signals',
                'href' => 'index.php?view=overview',
                'view' => 'overview',
            ],
        ],
        'People & Access' => [
            [
                'label' => 'User Governance',
                'hint' => 'Accounts, roles, password resets, and recovery',
                'href' => 'index.php?view=users',
                'view' => 'users',
            ],
            [
                'label' => 'My Profile',
                'hint' => 'Profile, password, and secure logout',
                'href' => 'index.php?view=profile',
                'view' => 'profile',
            ],
        ],
        'Governance' => [
            [
                'label' => 'Access & Policy',
                'hint' => 'Permissions, onboarding, announcements, and audit',
                'href' => 'index.php?view=controls',
                'view' => 'controls',
            ],
            [
                'label' => 'Finance Oversight',
                'hint' => 'Wallets, withdrawals, revenue, and settlements',
                'href' => 'index.php?view=finance',
                'view' => 'finance',
            ],
        ],
        'Recovery & Insights' => [
            [
                'label' => 'Recovery',
                'hint' => 'Backups, site nodes, sync events, and evidence',
                'href' => 'index.php?view=disaster',
                'view' => 'disaster',
            ],
        ],
    ];
}

function super_admin_nav_group_is_active(array $items, string $activeView): bool
{
    foreach ($items as $item) {
        if (($item['view'] ?? '') === $activeView) {
            return true;
        }
    }

    return false;
}

function super_admin_active_tab(array $keys, string $default = '', string $param = 'tab'): string
{
    $requested = (string) ($_POST[$param] ?? $_GET[$param] ?? '');
    if (in_array($requested, $keys, true)) {
        return $requested;
    }

    return $default !== '' ? $default : (string) ($keys[0] ?? '');
}

function super_admin_tab_bar(array $tabs, string $active, string $param = 'tab'): string
{
    $base = $_GET;
    unset($base[$param]);
    ob_start();
    ?>
    <div class="tab-list" role="tablist">
      <?php foreach ($tabs as $key => $tab): ?>
        <?php
          $label = is_array($tab) ? (string) ($tab['label'] ?? $key) : (string) $tab;
          $count = is_array($tab) ? ($tab['count'] ?? null) : null;
          $isActive = $key === $active;
        ?>
        <a class="tab <?= $isActive ? 'active' : '' ?>" role="tab" href="?<?= e(http_build_query($base + [$param => $key])) ?>" data-tab="<?= e((string) $key) ?>" aria-selected="<?= $isActive ? 'true' : 'false' ?>"><?= e($label) ?><?php if ($count !== null): ?><span class="tab-count"><?= (int) $count ?></span><?php endif; ?></a>
      <?php endforeach; ?>
    </div>
    <?php
    return (string) ob_get_clean();
}

function super_admin_page_meta(string $view): array
{
    return match ($view) {
        'users' => [
            'title' => 'User Governance',
            'description' => 'Manage privileged accounts, role assignments, access status, password resets, and recovery actions.',
        ],
        'profile' => [
            'title' => 'My Profile',
            'description' => 'Manage your own super admin profile, password, and secure exit.',
        ],
        'disaster' => [
            'title' => 'Recovery',
            'description' => 'Manage backups, site nodes, sync events, and restore evidence.',
        ],
        'controls' => [
            'title' => 'Access & Policy',
            'description' => 'Define feature permissions, onboarding policy, announcements, and audit visibility.',
        ],
        'finance' => [
            'title' => 'Finance Oversight',
            'description' => 'Monitor wallet balances, withdrawals, platform revenue, and settlements across the site.',
        ],
        default => [
            'title' => 'Overview',
            'description' => 'Monitor account governance, privileged access, and system health at a glance.',
        ],
    };
}

function super_admin_per_page_options(): array
{
    return [10, 25, 50, 100];
}

function super_admin_per_page(int $default = 25): int
{
    $perPage = (int) ($_GET['per_page'] ?? $default);
    return in_array($perPage, super_admin_per_page_options(), true) ? $perPage : $default;
}

function super_admin_pagination_controls(int $total, int $page, int $perPage, array $extra = []): string
{
    $pages = max(1, (int) ceil($total / max(1, $perPage)));
    $page = min(max(1, $page), $pages);
    $base = array_merge($_GET, $extra);
    unset($base['page'], $base['per_page']);
    $from = $total === 0 ? 0 : (($page - 1) * $perPage) + 1;
    $to = min($total, $page * $perPage);

    $url = static function (int $targetPage, int $targetPerPage) use ($base): string {
        return '?' . http_build_query($base + ['page' => $targetPage, 'per_page' => $targetPerPage]);
    };

    ob_start();
    ?>
    <form class="pagination" method="get">
      <?php foreach ($base as $key => $value): ?>
        <?php if (is_scalar($value)): ?><input type="hidden" name="<?= e((string) $key) ?>" value="<?= e((string) $value) ?>"><?php endif; ?>
      <?php endforeach; ?>
      <div class="meta">Showing <?= (int) $from ?>-<?= (int) $to ?> of <?= (int) $total ?></div>
      <div class="pagination-links">
        <a class="button secondary" href="<?= e($url(max(1, $page - 1), $perPage)) ?>" aria-disabled="<?= $page <= 1 ? 'true' : 'false' ?>">Previous</a>
        <span class="meta">Page <?= (int) $page ?> of <?= (int) $pages ?></span>
        <a class="button secondary" href="<?= e($url(min($pages, $page + 1), $perPage)) ?>" aria-disabled="<?= $page >= $pages ? 'true' : 'false' ?>">Next</a>
      </div>
      <label class="pagination-size">Rows
        <select name="per_page" onchange="this.form.page.value='1'; this.form.submit()">
          <?php foreach (super_admin_per_page_options() as $size): ?>
            <option value="<?= $size ?>" <?= $perPage === $size ? 'selected' : '' ?>><?= $size ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <input type="hidden" name="page" value="<?= (int) $page ?>">
    </form>
    <?php
    return (string) ob_get_clean();
}

function super_admin_statuses(): array
{
    return [
        'active' => 'Active',
        'inactive' => 'Inactive',
        'suspended' => 'Suspended',
        'deactivated' => 'Deactivated',
        'archived' => 'Archived',
    ];
}

function super_admin_login_screen(string $error): void
{
    if (!headers_sent()) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
    }
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Super Administrator - NATCODEV</title>
  <link rel="stylesheet" href="../assets/css/super-admin-login.css?v=20261005">
</head>
<body>
  <div class="login-wrap">
  <form class="box" method="post">
    <img src="<?= e(app_admin_logo_url()) ?>" alt="NATCODEV">
    <h1>Super Administrator</h1>
    <p>Privileged access for account governance, security controls, audit review, and system-wide configuration.</p>
    <?php if ($error): ?><div class="notice"><?= e($error) ?></div><?php endif; ?>
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="login">
    <label>Super Admin Password</label>
    <input type="password" name="password" required autofocus>
    <button type="submit">Enter Secure Console</button>
    <p><a href="../admin/admin.php">Return to admin</a></p>
  </form>
    <footer class="login-footer">
      <span>&copy; <?= e(date('Y')) ?> NATCODEV</span>
      <span>Super Admin Console</span>
    </footer>
  </div>
</body>
</html>
    <?php
}

function super_admin_page_start(string $title, string $description = '', string $activeView = 'overview'): void
{
    if (!headers_sent()) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
    }
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($title) ?> - NATCODEV</title>
  <link rel="stylesheet" href="../assets/css/super-admin.css?v=20261005">
</head>
<body>
    <?php require __DIR__ . '/../layout-components/super-admin-header.php'; ?>
  <main>
    <section class="hero">
      <div>
        <div class="hero-kicker">Super Admin</div>
        <h1><?= e($title) ?></h1>
        <p><?= e($description !== '' ? $description : 'Privileged governance for user roles, security access, onboarding, announcements, audit trails, exports, and system level controls.') ?></p>
      </div>
    </section>
    <?php
}

function super_admin_page_end(): void
{
    ?>
  </main>
  <footer class="super-footer">
    <div class="super-footer-inner">
      <div class="super-footer-brand">
        <strong>NATCODEV Super Admin</strong>
        <span>Privileged governance for privileged accounts, access policy, audit review, and disaster recovery.</span>
      </div>
      <nav class="super-footer-links" aria-label="Super Admin quick links">
        <a href="index.php?view=overview">Overview</a>
        <a href="index.php?view=users">User Governance</a>
        <a href="index.php?view=controls">Access &amp; Policy</a>
        <a href="index.php?view=disaster">Recovery</a>
        <a href="../admin/admin.php">Admin Console</a>
      </nav>
      <div class="super-footer-meta">
        <span>&copy; <?= e(date('Y')) ?> NATCODEV</span>
        <span>Super Admin Console</span>
      </div>
    </div>
  </footer>
  <script>
    (function () {
      const nav = document.querySelector('.super-nav');
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

      window.addEventListener('scroll', () => closeMenus(null), { passive: true });
      window.addEventListener('resize', () => closeMenus(null));
      document.addEventListener('touchmove', () => closeMenus(null), { passive: true });

      document.querySelectorAll('.super-menu a').forEach((link) => {
        link.addEventListener('click', () => closeMenus(null));
      });
    })();

    (function () {
      document.querySelectorAll('[data-super-tabs]').forEach(function (root) {
        const inGroup = function (selector) {
          return Array.from(root.querySelectorAll(selector)).filter(function (el) {
            return el.closest('[data-super-tabs]') === root;
          });
        };
        const tabs = inGroup('[role="tab"]');
        const panels = inGroup('[data-panel]');
        if (!tabs.length) return;

        function activate(key) {
          tabs.forEach(function (tab) {
            const on = tab.dataset.tab === key;
            tab.classList.toggle('active', on);
            tab.setAttribute('aria-selected', on ? 'true' : 'false');
          });
          panels.forEach(function (panel) {
            const on = panel.dataset.panel === key;
            panel.classList.toggle('active', on);
            panel.style.display = on ? '' : 'none';
          });
        }

        tabs.forEach(function (tab) {
          tab.addEventListener('click', function (event) {
            event.preventDefault();
            activate(tab.dataset.tab);
            if (window.history && window.history.replaceState && tab.getAttribute('href')) {
              window.history.replaceState(null, '', tab.getAttribute('href'));
            }
          });
          tab.addEventListener('keydown', function (event) {
            const index = tabs.indexOf(tab);
            let next = null;
            if (event.key === 'ArrowRight') next = tabs[(index + 1) % tabs.length];
            else if (event.key === 'ArrowLeft') next = tabs[(index - 1 + tabs.length) % tabs.length];
            if (next) {
              event.preventDefault();
              next.focus();
              activate(next.dataset.tab);
            }
          });
        });

        activate(root.dataset.active || tabs[0].dataset.tab);
      });
    })();
  </script></body>
</html>
    <?php
}
