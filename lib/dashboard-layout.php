<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/admin-layout.php';
require_once __DIR__ . '/ui-foundation.php';

function dashboard_nav_items(): array
{
    return [
        ['href' => 'index.php', 'label' => 'Overview'],
        ['href' => 'wallet.php', 'label' => 'Wallet'],
        ['href' => 'farm-health.php', 'label' => 'Farm Performance'],
        ['href' => 'farm-operations.php', 'label' => 'Farm Operations'],
        ['href' => '../market/index.php', 'label' => 'Marketplace'],
        ['href' => '../market/seller-central.php', 'label' => 'Seller Central'],
        ['href' => 'profile.php', 'label' => 'Profile'],
        ['href' => 'documents.php', 'label' => 'Verification'],
        ['href' => 'certificates.php', 'label' => 'Certificates'],
        ['href' => '../academy/index.php', 'label' => 'NATCODEV Academy'],
        ['href' => 'reports.php', 'label' => 'Reports'],
        ['href' => 'healthcare.php', 'label' => 'Healthcare'],
        ['href' => 'inbox.php', 'label' => 'Support Desk'],
        ['href' => 'pricing.php', 'label' => 'Account Upgrade'],
    ];
}

function dashboard_more_items(): array
{
    return [
        ['href' => 'fields.php', 'label' => 'Fields Management'],
        ['href' => 'agronomist.php', 'label' => 'Agronomy Advisory'],
        ['href' => 'verify-phone.php', 'label' => 'Phone Verification'],
        ['href' => 'change-password.php', 'label' => 'Password'],
    ];
}

function dashboard_nav_groups(): array
{
    return [
        ['label' => 'Main', 'items' => [
            ['href' => 'index.php', 'label' => 'Overview', 'feature' => 'dashboard', 'icon' => 'fas fa-home'],
            ['href' => 'wallet.php', 'label' => 'Wallet', 'feature' => 'wallet', 'icon' => 'fas fa-wallet'],
            ['href' => 'farm-health.php', 'label' => 'Farm Performance', 'feature' => 'farm_health', 'icon' => 'fas fa-chart-line'],
            ['href' => 'farm-operations.php', 'label' => 'Farm Operations', 'feature' => 'farm_health', 'icon' => 'fas fa-tractor'],
        ]],
        ['label' => 'Commerce', 'items' => [
            ['href' => '../market/index.php', 'label' => 'Marketplace', 'feature' => 'marketplace', 'icon' => 'fas fa-store'],
            ['href' => '../market/seller-central.php', 'label' => 'Seller Central', 'feature' => 'marketplace', 'icon' => 'fas fa-shop'],
        ]],
        ['label' => 'Grower', 'items' => [
            ['href' => 'profile.php', 'label' => 'My Profile', 'feature' => 'profile', 'icon' => 'fas fa-user-circle'],
            ['href' => 'farm-profile.php', 'label' => 'Farm Profile', 'feature' => 'profile', 'icon' => 'fas fa-tractor'],
            ['href' => 'documents.php', 'label' => 'Verification', 'feature' => 'documents', 'icon' => 'fas fa-shield-alt'],
            ['href' => 'certificates.php', 'label' => 'Certificates', 'feature' => 'certificates', 'icon' => 'fas fa-award'],
            ['href' => 'fields.php', 'label' => 'Fields Management', 'feature' => 'field_management', 'icon' => 'fas fa-map-marked-alt'],
            ['href' => 'agronomist.php', 'label' => 'Agronomy Advisory', 'feature' => 'agronomy_advisory', 'icon' => 'fas fa-seedling'],
        ]],
        ['label' => 'Learning', 'items' => [
            ['href' => '../academy/index.php?screen=learning', 'label' => 'My Learning', 'feature' => 'training', 'icon' => 'fas fa-graduation-cap'],
            ['href' => '../academy/index.php?screen=catalog', 'label' => 'NATCODEV Academy', 'feature' => 'training', 'icon' => 'fas fa-university'],
        ]],
        ['label' => 'Account', 'items' => [
            ['href' => 'healthcare.php', 'label' => 'Healthcare', 'feature' => 'healthcare', 'icon' => 'fas fa-heartbeat'],
            ['href' => 'reports.php', 'label' => 'Reports', 'feature' => 'reports', 'icon' => 'fas fa-file-alt'],
            ['href' => 'inbox.php', 'label' => 'Support & Requests', 'feature' => 'support', 'icon' => 'fas fa-headset'],
            ['href' => 'pricing.php', 'label' => 'Account Upgrade', 'feature' => 'pricing', 'icon' => 'fas fa-arrow-up'],
            ['href' => 'logout.php', 'label' => 'Logout', 'feature' => 'dashboard', 'icon' => 'fas fa-sign-out-alt'],
        ]],
        ['label' => 'Admin', 'items' => [
            ['href' => '../admin/admin.php', 'label' => 'Admin Console', 'feature' => 'applications', 'icon' => 'fas fa-user-shield', 'admin_only' => true],
            ['href' => '../super-admin/index.php', 'label' => 'Super Admin', 'feature' => 'audit', 'icon' => 'fas fa-crown', 'super_only' => true],
        ]],
    ];
}

function dashboard_feature_for_script(?string $script = null): string
{
    $script = $script ?? basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
    $path = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if (str_contains($path, '/academy/')) {
        return 'training';
    }
    return [
        'index.php' => 'dashboard',
        'reports.php' => 'reports',
        'profile.php' => 'profile',
        'account-settings.php' => 'profile',
        'farm-profile.php' => 'profile',
        'search.php' => 'dashboard',
        'documents.php' => 'documents',
        'certificates.php' => 'certificates',
        'download-certificate.php' => 'certificates',
        'download-academy-certificate.php' => 'certificates',
        'verify-phone.php' => 'profile',
        'change-password.php' => 'profile',
        'inbox.php' => 'support',
        'farm-health.php' => 'farm_health',
        'farm-operations.php' => 'farm_health',
        'fields.php' => 'field_management',
        'agronomist.php' => 'agronomy_advisory',
        'wallet.php' => 'wallet',
        'marketplace.php' => 'marketplace',
        'marketplace-seller.php' => 'marketplace',
        'webinars.php' => 'training',
        'academy.php' => 'training',
        'healthcare.php' => 'healthcare',
        'pricing.php' => 'pricing',
    ][$script] ?? 'dashboard';
}

function dashboard_allowed_nav_groups(PDO $pdo): array
{
    $groups = [];
    $user = current_user($pdo);
    $isAdminUser = $user && (($user['role'] ?? '') === 'admin' || (int) ($user['is_super_admin'] ?? 0) === 1 || (function_exists('admin_user_has_admin_access') && admin_user_has_admin_access($pdo, (int) $user['id'])));
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
        $hrefScript = strtok((string) $item['href'], '?#') ?: (string) $item['href'];
        if ($active === $hrefScript) {
            return true;
        }
    }
    return false;
}

function dashboard_initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $initials = '';
    foreach (array_slice(array_filter($parts), 0, 2) as $part) {
        $initials .= strtoupper(substr($part, 0, 1));
    }
    return $initials !== '' ? $initials : 'U';
}

function dashboard_user_is_learner_only(PDO $pdo, ?array $user = null): bool
{
    $user = $user ?: current_user($pdo);
    if (!$user || strtolower((string) ($user['platform_role'] ?? '')) !== 'learner') {
        return false;
    }

    $stmt = $pdo->prepare("SELECT COALESCE(application_id, 0) FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([(int) $user['id']]);
    if ((int) $stmt->fetchColumn() > 0) {
        return false;
    }

    if (app_table_exists($pdo, 'user_role_assignments')) {
        $roleStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM user_role_assignments
            WHERE user_id = ?
              AND role_key IN ('grower', 'seller', 'provider', 'input_provider', 'service_provider')
              AND status = 'active'
        ");
        $roleStmt->execute([(int) $user['id']]);
        if ((int) $roleStmt->fetchColumn() > 0) {
            return false;
        }
    }

    return true;
}

function dashboard_redirect_learner_only(PDO $pdo, ?array $user = null): void
{
    $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $allowed = ['wallet.php', 'logout.php', 'change-password.php'];
    if (in_array($script, $allowed, true)) {
        return;
    }
    if (dashboard_user_is_learner_only($pdo, $user)) {
        redirect_to('../academy/dashboard.php?screen=learning');
    }
}

function dashboard_page_start(string $title, array $options = []): void
{
    if (!headers_sent()) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
    }

    $pdo = db();
    if (session_status() === PHP_SESSION_ACTIVE) {
        dashboard_redirect_learner_only($pdo);
    }
    if (session_status() === PHP_SESSION_ACTIVE && (!isset($options['user_name']) || !array_key_exists('unread', $options) || !array_key_exists('profile_picture', $options) || !array_key_exists('notices', $options) || !array_key_exists('location', $options))) {
        try {
            $current = current_user($pdo);
            if ($current && !isset($options['user_name'])) {
                $options['user_name'] = $current['name'] ?? 'Grower';
            }
            if ($current && !isset($options['email'])) {
                $options['email'] = $current['email'] ?? '';
            }
            if ($current && !array_key_exists('profile_picture', $options)) {
                $options['profile_picture'] = $current['profile_picture'] ?? '';
            }
            if ($current && !array_key_exists('unread', $options) && app_table_exists($pdo, 'messages')) {
                $unreadStmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE user_id = ? AND is_from_admin = 1 AND is_read = 0");
                $unreadStmt->execute([(int) $current['id']]);
                $options['unread'] = (int) $unreadStmt->fetchColumn();
            }
            if ($current && !array_key_exists('location', $options)) {
                $options['location'] = 'Location pending';
                if (app_table_exists($pdo, 'grower_farms')) {
                    $locStmt = $pdo->prepare("
                        SELECT COALESCE(nl.lga_name, '') lga_name, COALESCE(ns.state_name, '') state_name
                        FROM grower_farms gf
                        LEFT JOIN nigeria_states ns ON ns.id = gf.state_id
                        LEFT JOIN nigeria_lgas nl ON nl.id = gf.lga_id
                        WHERE gf.user_id = ?
                        ORDER BY gf.is_primary DESC, gf.created_at ASC
                        LIMIT 1
                    ");
                    $locStmt->execute([(int) $current['id']]);
                    $loc = $locStmt->fetch();
                    if ($loc) {
                        $location = trim((string) ($loc['lga_name'] ?? '') . ', ' . (string) ($loc['state_name'] ?? ''), ' ,');
                        if ($location !== '') {
                            $options['location'] = $location;
                        }
                    }
                }
            }
            if ($current && !array_key_exists('notices', $options)) {
                $options['notices'] = [];
                if (app_table_exists($pdo, 'messages')) {
                    $noticeStmt = $pdo->prepare("
                        SELECT id, ticket_id, message, created_at
                        FROM messages
                        WHERE user_id = ? AND is_from_admin = 1
                        ORDER BY created_at DESC
                        LIMIT 6
                    ");
                    $noticeStmt->execute([(int) $current['id']]);
                    foreach ($noticeStmt->fetchAll() as $notice) {
                        $ticketId = trim((string) ($notice['ticket_id'] ?? ''));
                        $messageId = (int) ($notice['id'] ?? 0);
                        $createdAt = strtotime((string) $notice['created_at']) ?: time();
                        $options['notices'][] = [
                            'label' => $ticketId !== '' ? 'Support update' : 'Notification',
                            'text' => mb_substr(trim((string) $notice['message']), 0, 90),
                            'href' => $ticketId !== '' ? 'inbox.php?ticket=' . urlencode($ticketId) : 'inbox.php?view=notifications#message-' . $messageId,
                            'time' => date('M j, g:i A', strtotime((string) $notice['created_at'])),
                            'sort_time' => $createdAt,
                        ];
                    }
                }
                usort($options['notices'], static fn (array $a, array $b): int => ($b['sort_time'] ?? 0) <=> ($a['sort_time'] ?? 0));
                $options['notices'] = array_slice($options['notices'], 0, 6);
            }
        } catch (Throwable $e) {
            error_log('Dashboard layout context error: ' . $e->getMessage());
        }
    }

    $active = $options['active'] ?? basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
    if (empty($options['skip_feature_gate'])) {
        try {
            if (!admin_feature_is_allowed($pdo, dashboard_feature_for_script((string) $active))) {
                http_response_code(403);
                exit('Forbidden: this feature is not enabled for your role.');
            }
        } catch (Throwable $e) {
            error_log('Dashboard feature gate error: ' . $e->getMessage());
        }
    }

    $navGroups = dashboard_allowed_nav_groups($pdo);
    $subtitle = $options['subtitle'] ?? 'National Coconut Development & Propagation Initiative';
    $userName = $options['user_name'] ?? 'Grower';
    $userEmail = (string) ($options['email'] ?? '');
    $appRef = $options['app_ref'] ?? null;
    $unread = (int) ($options['unread'] ?? 0);
    $logo = app_primary_logo_url();
    $profilePicture = ltrim((string) ($options['profile_picture'] ?? ''), '/');
    $profilePictureUrl = $profilePicture !== '' ? '../' . $profilePicture : '';
    $initials = dashboard_initials((string) $userName);
    $notices = is_array($options['notices'] ?? null) ? $options['notices'] : [];
    $location = (string) ($options['location'] ?? 'Location pending');
    $pageTitle = $title . ' - NATCODEV';
    $showTitle = array_key_exists('show_title', $options) ? (bool) $options['show_title'] : true;
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#1B5E20">
  <title><?= e($pageTitle) ?></title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/natcodev-ui.css?v=20260602">
  <style>
    :root {
      --primary-green:#1B5E20; --primary-green-light:#2E7D32; --primary-green-dark:#0d3310;
      --accent-teal:#26A69A; --bg-page:#F5F7FA; --card-bg:#FFFFFF; --text-primary:#1A1A2E;
      --text-secondary:#6B7280; --text-muted:#9CA3AF; --border-color:#E5E7EB; --border-light:#F3F4F6;
      --red:#EF4444; --orange:#F59E0B; --blue:#3B82F6; --purple:#8B5CF6;
      --green:#1B5E20; --leaf:#2E7D32; --gold:#c9a227; --ink:#1A1A2E; --muted:#6B7280;
      --line:#E5E7EB; --bg:#F5F7FA; --panel:#fff; --danger:#a32020; --warn:#9b6500;
      --sidebar-width:260px; --topbar-height:72px; --radius-sm:8px; --radius-md:12px; --radius-lg:16px;
      --shadow-sm:0 1px 2px rgba(0,0,0,.05); --shadow-md:0 4px 6px rgba(0,0,0,.08);
      --shadow-lg:0 10px 15px rgba(0,0,0,.1); --shadow:0 10px 28px rgba(16,24,40,.08);
      --transition:all .3s cubic-bezier(.4,0,.2,1);
    }
    *{box-sizing:border-box}
    html{scroll-behavior:smooth;-webkit-text-size-adjust:100%}
    body.grower-shell-body{margin:0;background:linear-gradient(135deg,#eef7ef 0%,#f8fbf4 48%,#edf8f5 100%);color:var(--text-primary);font-family:Inter,"Segoe UI",Arial,sans-serif;font-size:15px;line-height:1.6;overflow-x:hidden;-webkit-font-smoothing:antialiased}
    a{color:var(--primary-green);font-weight:700;text-decoration:none}
    a:hover{text-decoration:none;color:var(--primary-green-light)}
    .sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000}
    body.grower-shell-body .sidebar{position:fixed;left:0!important;top:0!important;bottom:0!important;width:var(--sidebar-width)!important;background:linear-gradient(180deg,var(--primary-green) 0%,var(--primary-green-dark) 100%);color:white;padding:24px 0;z-index:1001;overflow-y:auto;overflow-x:hidden;transition:var(--transition);box-shadow:var(--shadow-lg);display:flex;flex-direction:column}
    .sb-brand{display:flex;align-items:center;gap:12px;padding:0 22px 20px;border-bottom:1px solid rgba(255,255,255,.12);color:white}
    .sb-brand:hover{color:white}
    .sb-logo{width:42px;height:42px;background:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:var(--shadow-sm);overflow:hidden}
    .sb-logo img{width:100%;height:100%;object-fit:contain}
    .sb-brand-text h1{font-size:17px;font-weight:900;letter-spacing:.02em;margin:0;line-height:1}
    .sb-brand-text small{font-size:11px;color:rgba(255,255,255,.78);display:block;line-height:1.25;margin-top:4px}
    .sb-user{margin:18px 16px;padding:16px;background:rgba(255,255,255,.1);backdrop-filter:blur(10px);border-radius:var(--radius-md);border:1px solid rgba(255,255,255,.1)}
    .sb-user-top{display:flex;align-items:center;gap:12px;margin-bottom:10px}
    .sb-avatar{width:48px;height:48px;border-radius:50%;background:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:16px;color:var(--primary-green);flex-shrink:0;border:3px solid rgba(255,255,255,.3);object-fit:cover}
    .sb-user-info .n{font-size:14px;font-weight:700;line-height:1.2}
    .sb-user-info .s{font-size:11px;color:#4ade80;font-weight:600;margin-top:2px}
    .sb-user-id{font-size:10px;color:rgba(255,255,255,.75);font-family:Consolas,monospace;background:rgba(0,0,0,.2);border-radius:6px;padding:6px 8px;overflow-wrap:anywhere}
    .sb-online{margin-top:8px;font-size:11px;color:#bff0c9;font-weight:700}
    .sb-nav{padding:0 12px 18px;display:grid;gap:2px}
    .sb-nav-label{font-size:10px;text-transform:uppercase;letter-spacing:.08em;color:rgba(255,255,255,.48);font-weight:800;margin:16px 12px 6px}
    .sb-item{display:flex;align-items:center;gap:12px;min-height:44px;padding:10px 12px;border-radius:var(--radius-sm);color:rgba(255,255,255,.9);font-weight:750;font-size:14px;transition:var(--transition);position:relative}
    .sb-item:hover,.sb-item.active{background:rgba(255,255,255,.14);color:#fff}
    .sb-item.active::before{content:"";position:absolute;left:-12px;top:9px;bottom:9px;width:4px;background:#4ade80;border-radius:0 4px 4px 0}
    .sb-ic{width:24px;height:24px;border-radius:7px;display:grid;place-items:center;background:rgba(255,255,255,.12);font-size:14px;font-weight:900;flex:0 0 auto}
    .sb-badge{margin-left:auto;min-width:22px;height:22px;border-radius:999px;background:var(--red);color:#fff;display:grid;place-items:center;font-size:11px;font-weight:800;padding:0 6px}
    .sb-footer{margin:auto 16px 0;padding:16px;background:rgba(255,255,255,.08);border-radius:var(--radius-md);border:1px solid rgba(255,255,255,.12)}
    .sb-footer h4{margin:0 0 5px;font-size:14px}.sb-footer p{margin:0 0 12px;color:rgba(255,255,255,.72);font-size:12px;line-height:1.4}
    .btn-outline-sb{display:inline-flex;align-items:center;justify-content:center;border:1px solid rgba(255,255,255,.25);border-radius:8px;color:#fff;padding:8px 10px;font-size:12px;font-weight:800}
    body.grower-shell-body .topbar{position:fixed;top:0!important;left:var(--sidebar-width)!important;right:0!important;height:var(--topbar-height);background:#fff;border-bottom:1px solid var(--border-color);display:flex;align-items:center;justify-content:space-between;padding:0 24px;z-index:999;box-shadow:var(--shadow-sm)}
    .tb-left,.tb-right{display:flex;align-items:center;gap:16px;min-width:0}.tb-left{flex:1}
    .mobile-menu-btn{display:none;width:42px;height:42px;border:0;background:#F3F4F6;color:var(--text-primary);border-radius:var(--radius-sm);font-size:18px;cursor:pointer;box-shadow:none}
    .tb-search{flex:1;max-width:560px;position:relative}
    .tb-search input{width:100%;height:44px;padding:0 88px 0 18px;border:1px solid var(--border-color);border-radius:var(--radius-md);background:#F9FAFB;font:inherit;font-size:14px;transition:var(--transition)}
    .tb-search input:focus{outline:none;border-color:var(--primary-green);background:#fff;box-shadow:0 0 0 3px rgba(27,94,32,.1)}
    .tb-search-shortcut{position:absolute;right:10px;top:50%;transform:translateY(-50%);background:#fff;border:1px solid var(--border-color);border-radius:6px;padding:3px 7px;font-size:11px;color:var(--text-muted);font-weight:700}
    .tb-icon{width:42px;height:42px;border:1px solid var(--border-color);border-radius:var(--radius-sm);background:#fff;color:var(--text-secondary);display:flex;align-items:center;justify-content:center;position:relative;font-weight:900;font-size:16px}
    .tb-icon-fallback{font-size:0}
    .tb-icon-fallback::before{font-size:18px;line-height:1}
    .tb-icon-bell::before{content:"\25cb"}
    .tb-icon-mail::before{content:"\2709"}
    .tb-icon:hover{background:var(--border-light);color:var(--primary-green)}
    .tb-badge-num{position:absolute;top:-5px;right:-5px;min-width:18px;height:18px;border-radius:9px;background:var(--red);color:#fff;font-size:10px;font-weight:800;display:flex;align-items:center;justify-content:center;padding:0 5px}
    .tb-loc{display:flex;align-items:center;gap:8px;padding:0 14px;border-left:1px solid var(--border-color);border-right:1px solid var(--border-color);height:42px;max-width:260px;color:var(--text-secondary);font-size:13px}
    .tb-loc i{color:var(--primary-green)}
    .tb-loc span{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .tb-user-menu{position:relative}
    .tb-user{display:flex;align-items:center;gap:10px;min-width:0;max-width:260px;padding:4px 8px 4px 4px;border-radius:var(--radius-md);color:inherit;background:transparent;border:0;cursor:pointer;box-shadow:none}
    .tb-user:hover{background:#F9FAFB}
    .tb-user-av{width:42px;height:42px;border-radius:50%;background:var(--primary-green);color:white;display:flex;align-items:center;justify-content:center;font-weight:800;object-fit:cover;flex:0 0 42px}
    .tb-user-info{display:flex;min-width:0;max-width:160px;flex-direction:column;align-items:flex-start;line-height:1.15;text-align:left}
    .tb-user-info .nm,.tb-user-info .st{display:block;max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .tb-user-info .nm{font-size:13px;font-weight:800;color:#102033}.tb-user-info .st{margin-top:2px;font-size:11px;color:var(--text-muted);font-weight:700}
    .tb-user .fa-chevron-down{flex:0 0 auto}
    .tb-dropdown{position:absolute;right:0;top:calc(100% + 10px);width:260px;background:#fff;border:1px solid var(--border-color);border-radius:12px;box-shadow:0 18px 38px rgba(16,24,40,.14);padding:8px;display:none;z-index:1200}
    .tb-user-menu.open .tb-dropdown{display:grid;gap:4px}
    .tb-drop-head{padding:10px 12px;border-bottom:1px solid var(--border-light);margin-bottom:4px}
    .tb-drop-head strong{display:block;color:#111827}.tb-drop-head span{display:block;color:var(--text-secondary);font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .tb-drop-link{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:9px;color:#111827;font-weight:800}
    .tb-drop-link:hover{background:#F3F8F1;color:var(--primary-green)}
    .tb-drop-link.danger{color:#991B1B}.tb-drop-link.danger:hover{background:#FEE2E2;color:#991B1B}
    body.grower-shell-body .main{margin-left:var(--sidebar-width)!important;padding-top:var(--topbar-height);min-height:100vh;width:auto!important;max-width:none!important}
    body.grower-shell-body .dash-main{padding:32px!important;max-width:none!important;margin:0!important;width:100%!important}
    .page-title{display:flex;justify-content:space-between;gap:18px;align-items:flex-start;margin-bottom:22px}
    .page-title h1{margin:0;color:#111827;font-size:clamp(1.7rem,3vw,2.35rem);line-height:1.12;letter-spacing:0}
    .page-title p{margin:7px 0 0;color:var(--text-secondary);max-width:820px;line-height:1.55}
    .page-kicker{color:var(--primary-green);font-weight:900;letter-spacing:.08em;text-transform:uppercase;font-size:.72rem;margin-bottom:6px}
    .card,.panel,.doc-card{background:var(--card-bg);border:1px solid rgba(16,24,40,.08);border-radius:var(--radius-md);box-shadow:0 12px 30px rgba(16,24,40,.08);overflow:visible}
    .card::before,.panel::before,.doc-card::before{display:none!important}
    .card,.panel{padding:22px}
    .card-h,.ntv-section-head{display:flex;align-items:center;justify-content:space-between;gap:16px;margin:-22px -22px 18px;padding:15px 18px;background:linear-gradient(135deg,#FFFBEA 0%,#F2FBEF 100%);border-bottom:1px solid #D8EADF;border-radius:var(--radius-md) var(--radius-md) 0 0}
    .card h2,.panel h2,.doc-card h2,.card-h h3,.ntv-section-head h2{margin:0;color:#0F3D1B;font-size:1.12rem;font-weight:950;line-height:1.2;letter-spacing:.01em}
    .card-h .link,.ntv-section-head .link{font-size:.78rem;font-weight:950;background:#FACC15;color:#173B12;border:1px solid #EAB308;border-radius:999px;padding:5px 10px;text-decoration:none;white-space:nowrap}
    .card-h .link:hover,.ntv-section-head .link:hover{background:#FDE68A;color:#173B12}
    .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:18px}
    .layout{display:grid;grid-template-columns:320px 1fr;gap:18px;align-items:start}
    .summary{display:grid;grid-template-columns:2fr 1fr 1fr;gap:16px;margin:24px 0}
    .doc-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}
    .hero{display:grid;grid-template-columns:minmax(0,1.4fr) minmax(280px,.6fr);gap:18px;margin-bottom:18px}
    .metric{font-size:2rem;color:var(--primary-green);font-weight:900;line-height:1}
    .actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:14px}
    .progress{height:10px;background:#E5E7EB;border-radius:999px;overflow:hidden;margin:14px 0}.progress span,.progress div{display:block;height:100%;background:var(--primary-green)}
    .badge,.badge-pill,.ntv-badge{display:inline-flex;align-items:center;border-radius:999px;padding:5px 10px;font-size:.78rem;font-weight:800;white-space:nowrap;background:#E8F5E9;color:#1B5E20}
    .bp-green,.verified,.resolved,.success,.ok{background:#E8F5E9!important;color:#166534!important}.bp-orange,.pending,.in_progress,.warn{background:#FEF3C7!important;color:#92400E!important}.bp-blue{background:#DBEAFE!important;color:#1D4ED8!important}.bp-gray,.not-submitted,.closed{background:#F3F4F6!important;color:#4B5563!important}.rejected,.danger,.error{background:#FEE2E2!important;color:#991B1B!important}
    .empty,.ntv-empty{border:1px dashed var(--border-color);border-radius:var(--radius-md);padding:18px;background:#fff;color:var(--text-secondary)}
    table{width:100%;border-collapse:collapse}th,td{padding:12px 10px;border-bottom:1px solid var(--border-light);text-align:left;vertical-align:top}th{color:#4B5563;font-size:.82rem;text-transform:uppercase;letter-spacing:.03em}
    label{display:block;font-weight:800;margin:12px 0 6px}form{margin:0}input,select,textarea{border:1px solid #D1D5DB;border-radius:8px;padding:11px 12px;font:inherit}input:focus,select:focus,textarea:focus{border-color:var(--primary-green);box-shadow:0 0 0 3px rgba(27,94,32,.12);outline:none}
    .button,button,.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;background:var(--primary-green);color:#fff;border:0;border-radius:8px;padding:10px 14px;font-weight:800;cursor:pointer;text-decoration:none;box-shadow:none}
    .button:hover,button:hover,.btn:hover{background:var(--primary-green-light);color:#fff}
    .button.secondary,.btn-o{background:#fff;color:var(--primary-green);border:1px solid var(--border-color)}
    .notice{padding:13px 15px;border-radius:8px;margin:16px 0}.muted,.note,.lead{color:var(--text-secondary)}
    .msg{max-width:78%;padding:12px 14px;border-radius:8px;margin:12px 0;background:#EEF7F1}.msg.you,.you{margin-left:auto;background:#F3F4F6}
    .dash-footer{display:none}
    @media(max-width:1200px){.tb-loc span{display:none}}
    @media(max-width:980px){
      .sidebar{transform:translateX(-100%)}body.sidebar-open .sidebar{transform:translateX(0)}body.sidebar-open .sidebar-overlay{display:block}
      .topbar{left:0}.main{margin-left:0}.mobile-menu-btn{display:flex;align-items:center;justify-content:center}.tb-search{max-width:none}.tb-user-info,.tb-loc{display:none}
      .dash-main{padding:18px}.layout,.summary,.doc-grid,.hero{grid-template-columns:1fr}.page-title{display:grid}.msg{max-width:100%}
    }
    @media(max-width:640px){.topbar{padding:0 14px}.tb-search-shortcut{display:none}.tb-search input{padding-right:14px}.tb-right{gap:8px}.dash-main{padding:14px}.grid{grid-template-columns:1fr}}
    <?= $options['css'] ?? '' ?>
  </style>
</head>
<body class="grower-shell-body">
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<aside class="sidebar" id="sidebar">
  <a class="sb-brand" href="index.php" aria-label="NATCODEV grower dashboard home">
    <span class="sb-logo"><img src="<?= e($logo) ?>" alt="NATCODEV"></span>
    <span class="sb-brand-text"><h1>NATCODEV</h1><small><?= e((string) $subtitle) ?></small></span>
  </a>
  <div class="sb-user">
    <div class="sb-user-top">
      <?php if ($profilePictureUrl !== ''): ?><img class="sb-avatar" src="<?= e($profilePictureUrl) ?>" alt="<?= e((string) $userName) ?> profile picture"><?php else: ?><div class="sb-avatar"><?= e($initials) ?></div><?php endif; ?>
      <div class="sb-user-info"><div class="n"><?= e((string) $userName) ?></div><div class="s">Active Grower</div></div>
    </div>
    <div class="sb-user-id">ID: <?= e($appRef ? (string) $appRef : 'Not linked') ?></div>
    <div class="sb-online">Online</div>
  </div>
  <nav class="sb-nav" aria-label="Grower navigation">
    <?php foreach ($navGroups as $group): ?>
      <div class="sb-nav-label"><?= e($group['label']) ?></div>
      <?php foreach ($group['items'] as $item): ?>
        <?php $hrefScript = strtok((string) $item['href'], '?#') ?: (string) $item['href']; $isActive = $active === $hrefScript; ?>
        <a class="sb-item <?= $isActive ? 'active' : '' ?>" href="<?= e($item['href']) ?>">
          <span class="sb-ic"><i class="<?= e((string) ($item['icon'] ?? 'fas fa-circle')) ?>"></i></span>
          <span><?= e((string) $item['label']) ?></span>
          <?php if ($item['href'] === 'inbox.php' && $unread > 0): ?><span class="sb-badge"><?= $unread ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </nav>
  <div class="sb-footer">
    <h4>Grow more. Earn more.</h4>
    <p>Use farm operations, Academy, marketplace, wallet, and support as your farm grows.</p>
    <a class="btn-outline-sb" href="../academy/index.php?screen=catalog">Explore Programs</a>
    <a class="btn-outline-sb" href="logout.php" style="margin-top:8px;">Logout</a>
  </div>
</aside>
<header class="topbar">
  <div class="tb-left">
    <button class="mobile-menu-btn" id="mobileMenuBtn" type="button" aria-label="Toggle menu"><i class="fas fa-bars"></i></button>
    <form class="tb-search" action="search.php" method="get">
      <input name="q" type="text" placeholder="Search anything (courses, advisory, marketplace, support)..." aria-label="Search dashboard">
      <span class="tb-search-shortcut">Ctrl + K</span>
    </form>
  </div>
  <div class="tb-right">
    <a class="tb-icon tb-icon-fallback tb-icon-bell" href="inbox.php?view=notifications" aria-label="Notifications"><i class="far fa-bell"></i><?php if ($unread > 0): ?><span class="tb-badge-num"><?= $unread ?></span><?php endif; ?></a>
    <a class="tb-icon tb-icon-fallback tb-icon-mail" href="inbox.php" aria-label="Messages"><i class="far fa-envelope"></i><?php if ($unread > 0): ?><span class="tb-badge-num"><?= $unread ?></span><?php endif; ?></a>
    <div class="tb-loc"><i class="fas fa-map-marker-alt"></i><span><?= e($location) ?></span><i class="fas fa-chevron-down" style="font-size:10px"></i></div>
    <div class="tb-user-menu" id="topUserMenu">
      <button class="tb-user" type="button" id="topUserMenuButton" aria-haspopup="true" aria-expanded="false">
        <?php if ($profilePictureUrl !== ''): ?><img class="tb-user-av" src="<?= e($profilePictureUrl) ?>" alt=""><?php else: ?><span class="tb-user-av"><?= e($initials) ?></span><?php endif; ?>
        <span class="tb-user-info"><span class="nm"><?= e((string) $userName) ?></span><span class="st">Grower Profile</span></span>
        <i class="fas fa-chevron-down" style="font-size:10px;color:var(--text-muted)"></i>
      </button>
      <div class="tb-dropdown" role="menu" aria-label="Account menu">
        <div class="tb-drop-head"><strong><?= e((string) $userName) ?></strong><span><?= e($userEmail) ?></span></div>
        <a class="tb-drop-link" href="inbox.php"><i class="fas fa-envelope"></i> Messages</a>
        <a class="tb-drop-link" href="profile.php"><i class="fas fa-user-edit"></i> Edit Profile</a>
        <a class="tb-drop-link" href="account-settings.php"><i class="fas fa-cog"></i> Account Settings</a>
        <a class="tb-drop-link danger" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
      </div>
    </div>
  </div>
</header>
<main class="main">
  <div class="dash-main">
    <?php if ($showTitle): ?>
      <section class="page-title">
        <div>
          <div class="page-kicker">NATCODEV Grower Hub</div>
          <h1><?= e($title) ?></h1>
          <?php if (!empty($options['description'])): ?><p><?= e((string) $options['description']) ?></p><?php endif; ?>
        </div>
        <?php if (!empty($options['action_html'])): ?><div><?= $options['action_html'] ?></div><?php endif; ?>
      </section>
    <?php endif; ?>
<?php
}

function dashboard_page_end(): void
{
    ?>
</div>
</main>
<script src="../lib/location-picker.js"></script>
<script>
(function () {
  const button = document.getElementById('mobileMenuBtn');
  const overlay = document.getElementById('sidebarOverlay');
  function closeSidebar() { document.body.classList.remove('sidebar-open'); }
  if (button) {
    button.addEventListener('click', function () {
      document.body.classList.toggle('sidebar-open');
    });
  }
  if (overlay) overlay.addEventListener('click', closeSidebar);
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') closeSidebar();
  });
  document.querySelectorAll('.sidebar a').forEach(function (link) {
    link.addEventListener('click', closeSidebar);
  });
  const topUserMenu = document.getElementById('topUserMenu');
  const topUserMenuButton = document.getElementById('topUserMenuButton');
  if (topUserMenu && topUserMenuButton) {
    topUserMenuButton.addEventListener('click', function (event) {
      event.stopPropagation();
      const isOpen = topUserMenu.classList.toggle('open');
      topUserMenuButton.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
    document.addEventListener('click', function (event) {
      if (!topUserMenu.contains(event.target)) {
        topUserMenu.classList.remove('open');
        topUserMenuButton.setAttribute('aria-expanded', 'false');
      }
    });
  }
  document.querySelectorAll('.password-toggle').forEach(function (button) {
    button.addEventListener('click', function () {
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
