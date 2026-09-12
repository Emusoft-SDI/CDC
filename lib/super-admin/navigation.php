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
        'field_agent' => 'Field Agent',
        'agronomist' => 'Agronomist',
        'agric_extensionist' => 'Agric Extensionist',
        'grower' => 'Grower',
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
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Super Administrator - NATCODEV</title>
  <style>
    body{margin:0;min-height:100vh;display:grid;place-items:center;background:#eef5f1;color:#1f2937;font-family:"Segoe UI",Tahoma,sans-serif}
    .box{width:min(460px,calc(100vw - 32px));background:#fff;border:1px solid #d8e2dc;border-radius:8px;box-shadow:0 18px 38px rgba(16,24,40,.14);padding:28px}
    img{width:64px;height:64px;border-radius:50%;object-fit:contain;border:1px solid #d8e2dc}
    h1{margin:14px 0 6px;color:#1a5276} p{color:#667085;line-height:1.55}
    label{display:block;font-weight:800;margin:14px 0 6px} input{width:100%;box-sizing:border-box;padding:12px;border:1px solid #d8e2dc;border-radius:6px}
    button{margin-top:16px;width:100%;border:0;border-radius:6px;background:#1f8a55;color:#fff;padding:12px 14px;font-weight:850;cursor:pointer}
    .notice{padding:12px;border-radius:6px;background:#fff3f3;color:#a32020;border:1px solid #ffd2d2}
    a{color:#166b41;font-weight:800;text-decoration:none}
  </style>
</head>
<body>
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
</body>
</html>
    <?php
}

function super_admin_page_start(string $title, string $description = '', string $activeView = 'overview'): void
{
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($title) ?> - NATCODEV</title>
  <style>
    :root{--primary:#1a5276;--green:#1f8a55;--green-dark:#166b41;--ink:#1f2937;--muted:#667085;--line:#d8e2dc;--bg:#f5f8f6;--panel:#fff;--danger:#a32020;--shadow:0 14px 34px rgba(16,24,40,.08)}
    *{box-sizing:border-box} body{margin:0;background:var(--bg);color:var(--ink);font-family:"Segoe UI",Tahoma,sans-serif}
    a{color:var(--green-dark);font-weight:800;text-decoration:none}.super-header{position:sticky;top:0;z-index:10;background:#fff;border-bottom:1px solid rgba(16,24,40,.08);box-shadow:0 8px 24px rgba(16,24,40,.06)}
    .bar{max-width:1320px;margin:0 auto;padding:12px 22px;display:flex;align-items:center;justify-content:space-between;gap:18px}.brand{display:flex;align-items:center;gap:11px;color:var(--primary);font-weight:900;white-space:nowrap}.brand img{width:46px;height:46px;border-radius:50%;object-fit:contain;border:1px solid var(--line)}
    .super-nav{display:flex;align-items:center;justify-content:center;gap:8px;flex:1}.super-nav details{position:relative}.super-nav summary{display:inline-flex;align-items:center;gap:7px;min-height:39px;list-style:none;cursor:pointer;border:1px solid transparent;border-radius:7px;padding:9px 12px;color:var(--ink);font-weight:850}.super-nav summary::-webkit-details-marker{display:none}.super-nav summary::after{content:"";width:7px;height:7px;border-right:2px solid currentColor;border-bottom:2px solid currentColor;transform:rotate(45deg) translateY(-2px);opacity:.7}.super-nav details.active:not([open])>summary{background:var(--green-dark);border-color:var(--green-dark);color:#fff;box-shadow:0 10px 22px rgba(6,63,36,.16)}.super-nav details[open]>summary{background:var(--green-dark);border-color:var(--green-dark);color:#fff;text-decoration:none}.super-nav summary:hover{background:#e1f3e8;border-color:#b8dec7;color:var(--green-dark);text-decoration:none}.super-nav details[open] summary::after{transform:rotate(225deg) translate(-2px,-1px)}.super-menu{position:absolute;top:calc(100% + 10px);left:0;z-index:40;display:grid;gap:6px;width:300px;padding:9px;background:#fff;border:1px solid rgba(16,24,40,.12);border-radius:8px;box-shadow:0 20px 42px rgba(16,24,40,.16)}.super-menu a{display:block;padding:10px 11px;border-radius:7px;color:var(--ink);font-weight:850}.super-menu a:hover,.super-menu a.active{background:#f1faf5;color:var(--green-dark)}.super-menu a:focus-visible,.super-nav summary:focus-visible{outline:3px solid rgba(31,138,85,.22);outline-offset:2px}.super-menu small{display:block;margin-top:3px;color:var(--muted);font-weight:650;line-height:1.35}.header-actions{display:flex;align-items:center;gap:10px;white-space:nowrap}    main{max-width:1320px;margin:0 auto;padding:22px 22px 42px}.hero{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;margin-bottom:14px;padding-bottom:12px;border-bottom:1px solid rgba(16,24,40,.08)}.hero h1{margin:0;color:var(--primary);font-size:clamp(1.55rem,2.4vw,2.15rem);line-height:1.08}.hero p{margin:6px 0 0;color:var(--muted);line-height:1.5;max-width:780px}.hero-kicker{color:var(--green-dark);font-size:.75rem;font-weight:900;letter-spacing:.12em;text-transform:uppercase;margin-bottom:5px}
    .panel,.stat,table{background:var(--panel);border:1px solid rgba(16,24,40,.08);border-radius:8px;box-shadow:var(--shadow)}.panel,.stat{padding:18px}.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:14px;margin:18px 0}.stat span{color:var(--muted)}.stat strong{display:block;margin-top:8px;color:var(--primary);font-size:2rem;line-height:1}.super-dashboard{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin:18px 0}.command-card{display:block;min-height:162px;padding:17px;border:1px solid rgba(16,24,40,.1);border-radius:8px;background:#fff;color:var(--ink);box-shadow:var(--shadow)}.command-card:hover{text-decoration:none;border-color:#b7dac5;background:#f8fcfa}.command-card span,.readiness-grid span{display:block;color:var(--green-dark);font-size:.78rem;font-weight:900;text-transform:uppercase;letter-spacing:.08em}.command-card strong{display:block;margin:12px 0 8px;color:var(--primary);font-size:1.15rem;line-height:1.22}.command-card small,.readiness-grid small{display:block;color:var(--muted);line-height:1.45}.command-card.operations{background:#f6fafc;border-color:#cfe0ea}.readiness-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin:18px 0}.readiness-grid article{padding:15px;background:#fff;border:1px solid rgba(16,24,40,.08);border-left:4px solid var(--green);border-radius:8px;box-shadow:var(--shadow)}.readiness-grid strong{display:block;margin:8px 0;color:var(--primary)}
    .console-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px;margin:18px 0}.section-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px}.section-head.compact{align-items:center}.actions,.filters,.check-row{display:flex;align-items:center;gap:10px;flex-wrap:wrap}.filters{margin:14px 0}.filters input{min-width:240px;flex:1}.settings-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.module-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(310px,1fr));gap:14px;margin:16px 0}.module-card{border:1px solid var(--line);border-radius:8px;background:#fbfdfb;padding:14px}.module-card h3{margin:0 0 6px;color:var(--primary)}.module-card p{margin:0 0 10px;color:var(--muted);line-height:1.45}.module-card textarea{min-height:84px}
    label{display:block;font-weight:800;margin:10px 0 6px} input,select,textarea{padding:11px 12px;border:1px solid var(--line);border-radius:6px;font:inherit;max-width:100%} input:not([type=checkbox]),select,textarea{width:100%} textarea{min-height:110px}.check-row label{font-weight:700}
    button,.button{display:inline-flex;align-items:center;justify-content:center;gap:8px;background:var(--green);color:#fff;border:0;border-radius:6px;padding:11px 14px;font-weight:850;cursor:pointer;text-decoration:none;box-shadow:0 10px 24px rgba(31,138,85,.18)}button:hover,.button:hover{background:var(--green-dark);color:#fff}.secondary{background:#eef7f1!important;color:var(--green-dark)!important;border:1px solid var(--line)!important;box-shadow:none!important}.danger{background:var(--danger)!important;color:#fff!important}.create-user-panel{position:relative}.create-user-panel summary{list-style:none}.create-user-panel summary::-webkit-details-marker{display:none}.create-user-panel form{position:absolute;right:0;top:calc(100% + 10px);z-index:30;width:min(430px,calc(100vw - 44px));padding:16px;background:#fff;border:1px solid rgba(16,24,40,.12);border-radius:8px;box-shadow:0 18px 38px rgba(16,24,40,.16)}.create-user-panel h3{margin:0 0 8px;color:var(--primary)}.compact-checks{align-items:flex-start}.compact-checks label{margin:4px 0}
    .notice{padding:13px 15px;border-radius:8px;margin:16px 0;border:1px solid transparent}.notice.ok{background:#eaf8f0;color:#0f6b3c;border-color:#bfe8cf}.notice.error{background:#fff3f3;color:var(--danger);border-color:#ffd2d2}.badge{display:inline-flex;margin-top:8px;border-radius:999px;padding:5px 9px;font-size:.78rem;font-weight:850}.warning{background:#fff7df;color:#8a5a00}.muted-badge{background:#eef2f6;color:#475467}.ok-badge{background:#eaf8f0;color:#0f6b3c}.root-badge{background:#eef4ff;color:#174ea6}.role-pill{display:inline-flex;align-items:center;border:1px solid #cfe6d8;background:#f1faf5;color:var(--green-dark);border-radius:999px;padding:6px 10px;font-weight:900;font-size:.82rem}
    .table-wrap{overflow:auto} table{width:100%;border-collapse:collapse} th,td{padding:11px;border-bottom:1px solid #edf1ea;text-align:left;vertical-align:top} th{background:#eef6e9;color:#243b1d}td small{display:block;margin-top:4px}.inline-edit{display:grid;gap:8px;min-width:260px}.mini-form{margin-top:8px}.danger-zone{display:grid;gap:7px}.row-review summary{cursor:pointer;color:var(--green-dark);font-weight:900}.row-review[open]{min-width:300px}.row-actions{border-top:1px solid #edf1ea;margin-top:12px;padding-top:10px}.pagination{margin:14px 0;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:12px;background:#fff;border:1px solid rgba(16,24,40,.08);border-radius:8px}.pagination-links{display:flex;gap:10px;align-items:center}.meta,small{color:var(--muted)}
    .dr-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin-top:14px}.dr-card{border:1px solid #edf1ea;border-radius:8px;padding:14px;background:#fbfdfb}.dr-card h3{margin:0 0 10px;color:var(--primary)}.compact-list{display:grid;gap:10px;max-height:360px;overflow:auto}.compact-list article{border:1px solid var(--line);border-radius:7px;background:#fff;padding:10px}.compact-list span,.compact-list small{display:block;margin-top:4px;color:var(--muted)}.danger-text{color:var(--danger)!important}.node-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:8px}.node-actions select{width:auto;min-width:110px}.node-actions label{margin:0;font-weight:700}
    .role-summary{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;margin-top:16px}.role-summary a{display:block;padding:14px;border:1px solid var(--line);border-radius:8px;background:#f8fbf9;color:var(--ink)}.role-summary a:hover{text-decoration:none;border-color:#b7dac5;background:#f1faf5}.role-summary span{display:block;color:var(--muted);font-weight:800}.role-summary strong{display:block;margin-top:8px;color:var(--primary);font-size:1.65rem;line-height:1}
    fieldset{border:1px solid var(--line);border-radius:8px;padding:12px;margin:0}legend{font-weight:900;color:var(--primary);padding:0 6px}.access-matrix{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;max-height:560px;overflow:auto;padding-right:4px}.access-matrix label{font-weight:650;margin:7px 0}.announcement-list,.audit-list{display:grid;gap:10px;max-height:390px;overflow:auto}.announcement-list article,.audit-list div{padding:11px;border:1px solid #edf1ea;border-radius:7px}.announcement-list p{margin:8px 0 0;color:var(--muted);line-height:1.5}.audit-list span,.audit-list small,.announcement-list small{display:block;margin-top:4px}
    @media(max-width:1100px){.super-dashboard{grid-template-columns:repeat(2,minmax(0,1fr))}.readiness-grid{grid-template-columns:1fr}}
    @media(max-width:900px){.console-grid,.dr-grid{grid-template-columns:1fr}.hero,.section-head,.bar{flex-direction:column;align-items:stretch}.super-nav{justify-content:flex-start;overflow-x:auto;padding-bottom:4px}.super-nav details,.super-nav summary{width:100%}.super-nav summary{justify-content:center}.super-nav details{position:static}.super-menu{left:22px;right:22px;width:auto}.settings-grid,.access-matrix{grid-template-columns:1fr}}
    @media(max-width:620px){.super-dashboard{grid-template-columns:1fr}}
  </style>
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
  </script></body>
</html>
    <?php
}
