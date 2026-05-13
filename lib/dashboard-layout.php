<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/admin-layout.php';

function dashboard_nav_items(): array
{
    return [
        ['href' => 'index.php', 'label' => 'Overview'],
        ['href' => 'profile.php', 'label' => 'Profile'],
        ['href' => 'inbox.php', 'label' => 'Support'],
        ['href' => 'wallet.php', 'label' => 'Wallet'],
    ];
}

function dashboard_more_items(): array
{
    return [
        ['href' => 'agronomist.php', 'label' => 'Agronomist'],
        ['href' => 'healthcare.php', 'label' => 'Healthcare'],
        ['href' => 'pricing.php', 'label' => 'Pricing'],
        ['href' => 'verify-phone.php', 'label' => 'Phone Verification'],
        ['href' => 'change-password.php', 'label' => 'Password'],
    ];
}

function dashboard_nav_groups(): array
{
    return [
        [
            'label' => 'Home',
            'items' => [
                ['href' => 'index.php', 'label' => 'Overview', 'feature' => 'dashboard'],
            ],
        ],
        [
            'label' => 'Profile',
            'items' => [
                ['href' => 'profile.php#personal', 'label' => 'Account Settings', 'feature' => 'profile'],
                ['href' => 'profile.php#security', 'label' => 'Security', 'feature' => 'profile'],
                ['href' => 'profile.php#password', 'label' => 'Password', 'feature' => 'profile'],
                ['href' => 'profile.php#notifications', 'label' => 'Notification Preferences', 'feature' => 'profile'],
                ['href' => 'documents.php', 'label' => 'Identity & Farm Verification', 'feature' => 'documents'],
            ],
        ],
        [
            'label' => 'Support',
            'items' => [
                ['href' => 'inbox.php', 'label' => 'Support Desk', 'feature' => 'support'],
                ['href' => 'farm-health.php', 'label' => 'Farm Health', 'feature' => 'farm_health'],
                ['href' => 'fields.php', 'label' => 'Fields Management', 'feature' => 'field_management'],
                ['href' => 'agronomist.php', 'label' => 'Agronomy Advisory', 'feature' => 'agronomy_advisory'],
            ],
        ],
        [
            'label' => 'Services',
            'items' => [
                ['href' => 'wallet.php', 'label' => 'Wallet', 'feature' => 'wallet'],
                ['href' => 'marketplace.php', 'label' => 'Marketplace', 'feature' => 'marketplace'],
                ['href' => 'webinars.php', 'label' => 'Training & Webinars', 'feature' => 'training'],
                ['href' => 'healthcare.php', 'label' => 'Healthcare', 'feature' => 'healthcare'],
                ['href' => 'pricing.php', 'label' => 'Pricing', 'feature' => 'pricing'],
            ],
        ],
        [
            'label' => 'Operations',
            'items' => [
                ['href' => '../admin/admin.php', 'label' => 'Admin Console', 'feature' => 'applications', 'admin_only' => true],
                ['href' => '../super-admin/index.php', 'label' => 'Super Admin', 'feature' => 'audit', 'super_only' => true],
            ],
        ],
    ];
}

function dashboard_feature_for_script(?string $script = null): string
{
    $script = $script ?? basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
    return [
        'index.php' => 'dashboard',
        'profile.php' => 'profile',
        'documents.php' => 'documents',
        'download-certificate.php' => 'certificates',
        'verify-phone.php' => 'profile',
        'change-password.php' => 'profile',
        'inbox.php' => 'support',
        'farm-health.php' => 'farm_health',
        'fields.php' => 'field_management',
        'agronomist.php' => 'agronomy_advisory',
        'wallet.php' => 'wallet',
        'marketplace.php' => 'marketplace',
        'webinars.php' => 'training',
        'healthcare.php' => 'healthcare',
        'pricing.php' => 'pricing',
    ][$script] ?? 'dashboard';
}

function dashboard_allowed_nav_groups(PDO $pdo): array
{
    $groups = [];
    $user = current_user($pdo);
    $isAdminUser = $user && (($user['role'] ?? '') === 'admin' || (int) ($user['is_super_admin'] ?? 0) === 1);
    $isSuperUser = $user && (int) ($user['is_super_admin'] ?? 0) === 1;

    foreach (dashboard_nav_groups() as $group) {
        $items = array_values(array_filter($group['items'], static function (array $item) use ($pdo, $isAdminUser, $isSuperUser): bool {
            if (!empty($item['admin_only']) && !$isAdminUser) {
                return false;
            }
            if (!empty($item['super_only']) && !$isSuperUser) {
                return false;
            }

            return admin_feature_is_allowed($pdo, (string) ($item['feature'] ?? 'dashboard'));
        }));
        if ($items) {
            $group['items'] = $items;
            $groups[] = $group;
        }
    }

    return $groups;
}

function dashboard_group_is_active(array $group, string $active): bool
{
    foreach ($group['items'] as $item) {
        $hrefScript = strtok((string) $item['href'], '#') ?: (string) $item['href'];
        if ($active === $hrefScript) {
            return true;
        }
    }

    return false;
}

function dashboard_page_start(string $title, array $options = []): void
{
    if (session_status() === PHP_SESSION_ACTIVE && (!isset($options['user_name']) || !array_key_exists('unread', $options) || !array_key_exists('profile_picture', $options) || !array_key_exists('notices', $options))) {
        try {
            $pdo = db();
            $current = current_user($pdo);
            if ($current && !isset($options['user_name'])) {
                $options['user_name'] = $current['name'] ?? 'Grower';
            }
            if ($current && !array_key_exists('profile_picture', $options)) {
                $options['profile_picture'] = $current['profile_picture'] ?? '';
            }
            if ($current && !array_key_exists('unread', $options) && app_table_exists($pdo, 'messages')) {
                $unreadStmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE user_id = ? AND is_from_admin = 1 AND is_read = 0");
                $unreadStmt->execute([(int) $current['id']]);
                $options['unread'] = (int) $unreadStmt->fetchColumn();
            }
            if ($current && !array_key_exists('notices', $options)) {
                $options['notices'] = [];
                if (app_table_exists($pdo, 'messages')) {
                    $noticeStmt = $pdo->prepare("
                        SELECT ticket_id, message, created_at
                        FROM messages
                        WHERE user_id = ? AND is_from_admin = 1
                        ORDER BY created_at DESC
                        LIMIT 3
                    ");
                    $noticeStmt->execute([(int) $current['id']]);
                    foreach ($noticeStmt->fetchAll() as $notice) {
                        $options['notices'][] = [
                            'label' => 'Support update',
                            'text' => mb_substr(trim((string) $notice['message']), 0, 90),
                            'href' => 'inbox.php?ticket=' . urlencode((string) $notice['ticket_id']),
                            'time' => date('M j, g:i A', strtotime((string) $notice['created_at'])),
                        ];
                    }
                }
                if (function_exists('admin_setting')) {
                    $systemNotice = trim(admin_setting($pdo, 'dashboard_system_notice', ''));
                    if ($systemNotice !== '') {
                        array_unshift($options['notices'], [
                            'label' => 'System notice',
                            'text' => mb_substr($systemNotice, 0, 120),
                            'href' => 'index.php',
                            'time' => 'Now',
                        ]);
                    }
                }
                if (app_table_exists($pdo, 'system_announcements')) {
                    $audience = [$current['role'] ?? 'grower', 'all'];
                    if (!empty($current['platform_role'])) {
                        $audience[] = (string) $current['platform_role'];
                    }
                    $audience = array_values(array_unique($audience));
                    $placeholders = implode(',', array_fill(0, count($audience), '?'));
                    $announcementStmt = $pdo->prepare("
                        SELECT title, body, created_at
                        FROM system_announcements
                        WHERE is_active = 1 AND audience_role IN ({$placeholders})
                        ORDER BY created_at DESC
                        LIMIT 3
                    ");
                    $announcementStmt->execute($audience);
                    foreach (array_reverse($announcementStmt->fetchAll()) as $announcement) {
                        array_unshift($options['notices'], [
                            'label' => (string) $announcement['title'],
                            'text' => mb_substr(trim((string) $announcement['body']), 0, 120),
                            'href' => 'index.php',
                            'time' => date('M j, g:i A', strtotime((string) $announcement['created_at'])),
                        ]);
                    }
                }
            }
        } catch (Throwable $e) {
            error_log('Dashboard layout context error: ' . $e->getMessage());
        }
    }

    $active = $options['active'] ?? basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
    if (empty($options['skip_feature_gate'])) {
        try {
            $pdo = $pdo ?? db();
            if (!admin_feature_is_allowed($pdo, dashboard_feature_for_script((string) $active))) {
                http_response_code(403);
                exit('Forbidden: this feature is not enabled for your role.');
            }
        } catch (Throwable $e) {
            error_log('Dashboard feature gate error: ' . $e->getMessage());
        }
    }
    $navGroups = dashboard_allowed_nav_groups($pdo ?? db());
    $subtitle = $options['subtitle'] ?? 'Grower Dashboard';
    $userName = $options['user_name'] ?? 'Grower';
    $appRef = $options['app_ref'] ?? null;
    $unread = (int) ($options['unread'] ?? 0);
    $wide = !empty($options['wide']);
    $logo = app_primary_logo_url();
    $profilePicture = ltrim((string) ($options['profile_picture'] ?? ''), '/');
    $profilePictureUrl = $profilePicture !== '' ? '../' . $profilePicture : '';
    $initials = strtoupper(substr(trim((string) $userName), 0, 1) ?: 'U');
    $notices = is_array($options['notices'] ?? null) ? $options['notices'] : [];
    $pageTitle = $title . ' - NATCODEV';
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle) ?></title>
  <style>
    :root {
      --green:#2d5016; --leaf:#14733a; --gold:#c9a227; --ink:#172211; --muted:#66715f;
      --line:#dfe8d8; --bg:#f5f8f3; --panel:#fff; --danger:#a32020; --warn:#9b6500;
      --shadow:0 14px 34px rgba(24,43,18,.08);
    }
    * { box-sizing:border-box; }
    body { margin:0; background:var(--bg); color:var(--ink); font-family:"Segoe UI", Arial, sans-serif; }
    a { color:var(--leaf); font-weight:700; text-decoration:none; }
    a:hover { text-decoration:underline; }
    .dash-shell { min-height:100vh; display:flex; flex-direction:column; }
    .dash-header { background:#fff; border-bottom:1px solid rgba(24,43,18,.08); box-shadow:0 8px 24px rgba(24,43,18,.06); position:sticky; top:0; z-index:20; }
    .dash-top { max-width:<?= $wide ? '1280px' : '1180px' ?>; margin:0 auto; padding:14px 22px; display:flex; align-items:center; justify-content:space-between; gap:18px; }
    .dash-brand { display:flex; align-items:center; gap:12px; min-width:230px; color:var(--green); }
    .dash-brand img { width:54px; height:54px; object-fit:contain; border-radius:50%; background:#fff; border:1px solid var(--line); }
    .brand-name { font-weight:900; letter-spacing:.03em; line-height:1; }
    .brand-sub { color:var(--muted); font-size:.82rem; margin-top:4px; }
    .dash-nav { display:flex; flex-wrap:wrap; align-items:center; justify-content:center; gap:8px; }
    .dash-nav details { position:relative; }
    .dash-nav summary, .dash-nav .nav-link { display:inline-flex; align-items:center; gap:7px; min-height:40px; color:#33412d; border:1px solid transparent; padding:9px 11px; border-radius:7px; font-size:.93rem; font-weight:800; cursor:pointer; list-style:none; }
    .dash-nav summary::-webkit-details-marker { display:none; }
    .dash-nav summary::after { content:""; width:7px; height:7px; border-right:2px solid currentColor; border-bottom:2px solid currentColor; transform:rotate(45deg) translateY(-2px); opacity:.7; }
    .dash-nav .nav-link.active, .dash-nav .nav-link:hover, .dash-nav details.active > summary, .dash-nav details[open] > summary, .dash-nav summary:hover { background:#edf6e8; border-color:#d6e8cd; color:var(--green); text-decoration:none; }
    .dash-menu { position:absolute; right:0; top:calc(100% + 8px); width:min(280px, calc(100vw - 44px)); background:#fff; border:1px solid rgba(24,43,18,.11); border-radius:8px; box-shadow:0 18px 38px rgba(24,43,18,.16); padding:8px; display:grid; gap:4px; z-index:30; }
    .dash-menu a { display:flex; align-items:center; justify-content:space-between; gap:12px; color:#33412d; padding:10px 11px; border-radius:6px; font-size:.92rem; font-weight:750; }
    .dash-menu a.active, .dash-menu a:hover { background:#f1faf5; color:var(--green); text-decoration:none; }
    .dash-menu a:focus-visible, .dash-nav summary:focus-visible, .dash-nav .nav-link:focus-visible, .dash-user:focus-visible { outline:3px solid rgba(20,115,58,.22); outline-offset:2px; }
    .nav-count { min-width:22px; border-radius:999px; padding:2px 7px; background:var(--gold); color:#172211; font-size:.78rem; text-align:center; }
    .dash-account { position:relative; min-width:190px; display:flex; justify-content:flex-end; }
    .dash-user { width:100%; display:flex; align-items:center; justify-content:flex-end; gap:10px; font-size:.92rem; color:var(--muted); border:0; background:transparent; box-shadow:none; padding:4px; cursor:pointer; }
    .dash-user:hover { background:#f1faf5; color:var(--muted); box-shadow:none; }
    .dash-user strong { display:block; color:var(--green); }
    .dash-user-photo { width:44px; height:44px; border-radius:50%; object-fit:cover; border:2px solid #dfe8d8; background:#f5f8f3; flex:0 0 auto; }
    .dash-user-fallback { width:44px; height:44px; border-radius:50%; display:grid; place-items:center; border:2px solid #dfe8d8; background:#edf6e8; color:var(--green); font-weight:900; flex:0 0 auto; }
    .dash-user-meta { text-align:right; min-width:0; }
    .dash-account summary { list-style:none; }
    .dash-account summary::-webkit-details-marker { display:none; }
    .dash-account-menu { position:absolute; right:0; top:calc(100% + 10px); width:min(360px, calc(100vw - 44px)); background:#fff; border:1px solid rgba(24,43,18,.11); border-radius:8px; box-shadow:0 18px 38px rgba(24,43,18,.16); padding:12px; z-index:40; }
    .account-menu-head { display:flex; gap:10px; align-items:center; padding:4px 4px 12px; border-bottom:1px solid var(--line); margin-bottom:8px; }
    .account-actions { display:grid; grid-template-columns:1fr 1fr; gap:8px; margin:10px 0 12px; }
    .account-actions a { display:flex; align-items:center; justify-content:center; min-height:38px; border-radius:6px; background:#edf6e8; border:1px solid var(--line); color:var(--green); font-weight:850; }
    .account-actions a.logout-link { background:#fff3f3; color:var(--danger); border-color:#ffd2d2; }
    .notice-list { display:grid; gap:8px; }
    .notice-item { display:block; color:inherit; padding:10px; border:1px solid var(--line); border-radius:7px; background:#fbfcfb; }
    .notice-item:hover { text-decoration:none; background:#f1faf5; }
    .notice-item strong { display:flex; justify-content:space-between; gap:8px; color:var(--green); font-size:.88rem; }
    .notice-item span { display:block; color:var(--muted); font-size:.86rem; margin-top:4px; line-height:1.35; }
    .notice-empty { color:var(--muted); padding:10px; border:1px dashed var(--line); border-radius:7px; }
    .dash-main { width:100%; max-width:<?= $wide ? '1280px' : '1180px' ?>; margin:0 auto; padding:26px 22px 34px; flex:1; }
    .page-kicker { color:var(--gold); font-weight:900; letter-spacing:.13em; text-transform:uppercase; font-size:.76rem; margin-bottom:8px; }
    .page-title { display:flex; align-items:flex-start; justify-content:space-between; gap:18px; margin-bottom:22px; }
    .page-title h1 { margin:0; color:var(--green); font-size:clamp(2rem,4vw,3.25rem); line-height:1.02; }
    .page-title p { margin:8px 0 0; color:var(--muted); max-width:760px; line-height:1.6; }
    .card, .panel, .doc-card { background:var(--panel); border:1px solid rgba(24,43,18,.09); border-radius:8px; box-shadow:var(--shadow); }
    .card, .panel { padding:18px; }
    .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:16px; }
    .hero { display:grid; grid-template-columns:minmax(0,1.4fr) minmax(280px,.6fr); gap:18px; margin-bottom:18px; }
    .span-8 { grid-column:span 8; } .span-6 { grid-column:span 6; } .span-4 { grid-column:span 4; }
    .metric { font-size:2rem; color:var(--green); font-weight:900; line-height:1; }
    .actions { display:flex; flex-wrap:wrap; gap:10px; margin-top:14px; }
    .progress { height:10px; background:#e8eee3; border-radius:999px; overflow:hidden; margin:14px 0; }
    .progress span, .progress div { display:block; height:100%; background:var(--leaf); }
    .steps { display:grid; gap:8px; }
    .step { display:flex; justify-content:space-between; gap:12px; padding:10px; background:#f9fbf7; border:1px solid var(--line); border-radius:6px; color:inherit; }
    .done { color:var(--leaf); font-weight:800; } .todo { color:var(--warn); font-weight:800; }
    .layout { display:grid; grid-template-columns:320px 1fr; gap:18px; align-items:start; }
    .summary { display:grid; grid-template-columns:2fr 1fr 1fr; gap:16px; margin:24px 0; }
    .doc-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:18px; }
    .doc-card { padding:18px; }
    .doc-head { display:flex; justify-content:space-between; gap:14px; align-items:flex-start; margin-bottom:12px; }
    .reference { background:#f7f9f5; border:1px solid var(--line); border-radius:6px; padding:10px 12px; font-family:Consolas, monospace; font-size:.92rem; margin:12px 0; }
    .file-list { margin:12px 0 0; padding:0; list-style:none; display:grid; gap:8px; }
    .file-list li { display:flex; justify-content:space-between; gap:12px; align-items:center; border:1px solid var(--line); border-radius:6px; padding:9px 10px; background:#fbfcfb; }
    .badge { display:inline-flex; align-items:center; border-radius:999px; padding:6px 10px; font-size:.82rem; font-weight:800; white-space:nowrap; }
    .not-submitted, .open { background:#eef2f6; color:#475467; }
    .pending, .in_progress { background:#fff7df; color:#8a5a00; }
    .verified, .resolved { background:#eaf8f0; color:#0f6b3c; }
    .rejected { background:#fff3f3; color:var(--danger); }
    .closed { background:#f2f4f7; color:#344054; }
    .ticket-link { display:block; padding:12px; border:1px solid var(--line); border-radius:8px; color:inherit; margin-bottom:10px; }
    .ticket-link.active { border-color:rgba(20,115,58,.55); background:#f1faf5; }
    .ticket-meta { color:var(--muted); font-size:.9rem; margin-top:5px; }
    .msg { max-width:78%; padding:12px 14px; border-radius:8px; margin:12px 0; background:#eef7f1; }
    .msg.you, .you { margin-left:auto; background:#f2f4f7; }
    .msg p { margin:8px 0; white-space:pre-wrap; }
    .empty { color:var(--muted); border:1px dashed var(--line); padding:18px; border-radius:8px; }
    table { width:100%; border-collapse:collapse; }
    th, td { padding:10px; border-bottom:1px solid #edf1ea; text-align:left; vertical-align:top; }
    label { display:block; font-weight:800; margin:12px 0 6px; }
    form { margin:0; }
    .button, button { display:inline-flex; align-items:center; justify-content:center; gap:8px; background:var(--green); color:#fff; border:0; border-radius:6px; padding:11px 14px; font-weight:800; cursor:pointer; text-decoration:none; box-shadow:0 10px 24px rgba(45,80,22,.18); }
    .button:hover, button:hover { background:var(--leaf); color:#fff; text-decoration:none; }
    .button.secondary { background:#edf6e8; color:var(--green); border:1px solid var(--line); box-shadow:none; }
    input, select, textarea { border:1px solid #cbd8c4; border-radius:6px; padding:11px 12px; font:inherit; }
    input:focus, select:focus, textarea:focus { border-color:var(--leaf); box-shadow:0 0 0 3px rgba(20,115,58,.14); outline:none; }
    .notice { padding:13px 15px; border-radius:8px; margin:16px 0; }
    .success, .ok { background:#eaf8f0; color:#0f6b3c; border:1px solid #bfe8cf; }
    .error { background:#fff3f3; color:var(--danger); border:1px solid #ffd2d2; }
    .muted, .note, .lead { color:var(--muted); }
    .dash-footer { background:#18300f; color:#dfead9; margin-top:auto; }
    .dash-footer-inner { max-width:<?= $wide ? '1280px' : '1180px' ?>; margin:0 auto; padding:24px 22px; display:grid; grid-template-columns:minmax(220px,1fr) minmax(0,2.4fr); gap:28px; align-items:start; }
    .footer-groups { display:grid; grid-template-columns:repeat(4,minmax(130px,1fr)); gap:18px; }
    .footer-group strong { display:block; color:#fff; font-size:.9rem; margin-bottom:8px; }
    .footer-links { display:grid; gap:7px; }
    .footer-links a { color:#f6fff2; font-weight:650; font-size:.9rem; }
    @media (max-width: 960px) {
      .dash-top { align-items:flex-start; flex-direction:column; }
      .dash-nav { justify-content:flex-start; }
      .dash-menu { left:0; right:auto; }
      .dash-account { justify-content:flex-start; min-width:0; }
      .dash-user { justify-content:flex-start; min-width:0; }
      .dash-user-meta { text-align:left; }
      .dash-account-menu { left:0; right:auto; }
      .page-title { flex-direction:column; }
      .hero { grid-template-columns:1fr; }
      .span-8, .span-6, .span-4 { grid-column:1 / -1; }
      .layout, .summary, .doc-grid { grid-template-columns:1fr; }
      .msg { max-width:100%; }
      .dash-footer-inner { grid-template-columns:1fr; }
      .footer-groups { grid-template-columns:repeat(2,minmax(140px,1fr)); }
    }
    @media (max-width: 560px) {
      .dash-top, .dash-main, .dash-footer-inner { padding-left:16px; padding-right:16px; }
      .dash-brand { min-width:0; }
      .dash-nav { width:100%; display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); }
      .dash-nav details, .dash-nav .nav-link, .dash-nav summary { width:100%; }
      .dash-nav summary, .dash-nav .nav-link { justify-content:center; }
      .dash-menu { width:calc(100vw - 32px); }
      .footer-groups { grid-template-columns:1fr; }
    }
    <?= $options['css'] ?? '' ?>
  </style>
</head>
<body>
<div class="dash-shell">
  <header class="dash-header">
    <div class="dash-top">
      <a class="dash-brand" href="index.php" aria-label="NATCODEV dashboard home">
        <img src="<?= e($logo) ?>" alt="NATCODEV">
        <span>
          <span class="brand-name">NATCODEV</span>
          <span class="brand-sub"><?= e((string) $subtitle) ?></span>
        </span>
      </a>
      <nav class="dash-nav" aria-label="Dashboard navigation">
        <?php foreach ($navGroups as $group): ?>
          <?php $groupActive = dashboard_group_is_active($group, (string) $active); ?>
          <?php if (count($group['items']) === 1): ?>
            <?php $item = $group['items'][0]; ?>
            <a class="nav-link <?= $groupActive ? 'active' : '' ?>" href="<?= e($item['href']) ?>"><?= e($item['label']) ?></a>
          <?php else: ?>
            <details class="<?= $groupActive ? 'active' : '' ?>">
              <summary><?= e($group['label']) ?><?= $group['label'] === 'Support' && $unread > 0 ? ' (' . $unread . ')' : '' ?></summary>
              <div class="dash-menu">
                <?php foreach ($group['items'] as $item): ?>
                  <?php $hrefScript = strtok((string) $item['href'], '#') ?: (string) $item['href']; ?>
                  <?php $isActive = $active === $hrefScript; ?>
                  <a class="<?= $isActive ? 'active' : '' ?>" href="<?= e($item['href']) ?>">
                    <span><?= e($item['label']) ?></span>
                    <?php if ($item['href'] === 'inbox.php' && $unread > 0): ?><span class="nav-count"><?= $unread ?></span><?php endif; ?>
                  </a>
                <?php endforeach; ?>
              </div>
            </details>
          <?php endif; ?>
        <?php endforeach; ?>
      </nav>
      <details class="dash-account">
        <summary class="dash-user" aria-label="Open account menu">
          <?php if ($profilePictureUrl !== ''): ?>
            <img class="dash-user-photo" src="<?= e($profilePictureUrl) ?>" alt="<?= e((string) $userName) ?> profile picture">
          <?php else: ?>
            <span class="dash-user-fallback" aria-hidden="true"><?= e($initials) ?></span>
          <?php endif; ?>
          <span class="dash-user-meta">
            <strong><?= e((string) $userName) ?></strong>
            <?= $appRef ? e((string) $appRef) : 'Account menu' ?>
          </span>
        </summary>
        <div class="dash-account-menu">
          <div class="account-menu-head">
            <?php if ($profilePictureUrl !== ''): ?>
              <img class="dash-user-photo" src="<?= e($profilePictureUrl) ?>" alt="">
            <?php else: ?>
              <span class="dash-user-fallback" aria-hidden="true"><?= e($initials) ?></span>
            <?php endif; ?>
            <div>
              <strong><?= e((string) $userName) ?></strong><br>
              <span class="muted"><?= $unread > 0 ? (int) $unread . ' unread support update(s)' : 'No unread support updates' ?></span>
            </div>
          </div>
          <div class="account-actions">
            <a href="profile.php">Edit Profile</a>
            <a class="logout-link" href="logout.php">Logout</a>
          </div>
          <h3 style="margin:8px 0 8px;font-size:.95rem;color:var(--green);">Notifications</h3>
          <div class="notice-list">
            <?php foreach (array_slice($notices, 0, 4) as $notice): ?>
              <a class="notice-item" href="<?= e((string) ($notice['href'] ?? 'inbox.php')) ?>">
                <strong><span><?= e((string) ($notice['label'] ?? 'Update')) ?></span><span><?= e((string) ($notice['time'] ?? '')) ?></span></strong>
                <span><?= e((string) ($notice['text'] ?? '')) ?></span>
              </a>
            <?php endforeach; ?>
            <?php if (!$notices): ?><div class="notice-empty">No new activity or system notices.</div><?php endif; ?>
          </div>
        </div>
      </details>
    </div>
  </header>
  <main class="dash-main">
    <section class="page-title">
      <div>
        <div class="page-kicker">NATCODEV Grower Hub</div>
        <h1><?= e($title) ?></h1>
        <?php if (!empty($options['description'])): ?><p><?= e((string) $options['description']) ?></p><?php endif; ?>
      </div>
      <?php if (!empty($options['action_html'])): ?><div><?= $options['action_html'] ?></div><?php endif; ?>
    </section>
<?php
}

function dashboard_page_end(): void
{
    $navGroups = dashboard_allowed_nav_groups(db());
    ?>
  </main>
  <footer class="dash-footer">
    <div class="dash-footer-inner">
      <div>
        <strong>NATCODEV Coconut Outgrowers Program</strong>
        <div style="margin-top:6px;color:#b9cdb1;">Primary platform brand: National Coconut Development & Propagation Initiative.</div>
      </div>
      <nav class="footer-groups" aria-label="Dashboard secondary navigation">
        <?php foreach ($navGroups as $group): ?>
          <div class="footer-group">
            <strong><?= e($group['label']) ?></strong>
            <div class="footer-links">
              <?php foreach ($group['items'] as $item): ?>
                <a href="<?= e($item['href']) ?>"><?= e($item['label']) ?></a>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </nav>
    </div>
  </footer>
</div>
<script>
(function () {
  const nav = document.querySelector('.dash-nav');
  const account = document.querySelector('.dash-account');
  const details = [];
  if (nav) details.push(...Array.from(nav.querySelectorAll('details')));
  if (account) details.push(account);

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
    const target = event.target;
    if ((nav && nav.contains(target)) || (account && account.contains(target))) return;
    closeMenus(null);
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') closeMenus(null);
  });
  window.addEventListener('scroll', () => closeMenus(null), { passive:true });
  window.addEventListener('resize', () => closeMenus(null));
  document.addEventListener('touchmove', () => closeMenus(null), { passive:true });

  document.querySelectorAll('.dash-menu a, .dash-account-menu a').forEach((link) => {
    link.addEventListener('click', () => closeMenus(null));
  });
})();
</script>
</body>
</html>
<?php
}
