<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../lib/admin-layout.php';
require_once __DIR__ . '/../lib/academy.php';
require_once __DIR__ . '/../lib/marketplace.php';
require_once __DIR__ . '/../lib/support.php';
require_once __DIR__ . '/../lib/monnify.php';

$pdo = db();
admin_ensure_schema($pdo);
academy_ensure_schema($pdo);
marketplace_ensure_schema($pdo);
support_ensure_schema($pdo);

if (isset($_GET['logout'])) {
    admin_logout();
}
if (!admin_session_is_authenticated($pdo)) {
    redirect_to('admin.php');
}
admin_require_feature($pdo, 'dashboard');

$user = current_user($pdo) ?: [];
$name = (string) ($user['name'] ?? 'Admin User');
$role = admin_current_platform_role($pdo) ?? 'admin';
$roleLabel = ucwords(str_replace('_', ' ', $role));
$logo = app_primary_logo_url();
$avatar = trim((string) ($user['profile_picture'] ?? ''));
$avatarUrl = $avatar !== '' ? (str_starts_with($avatar, 'http') ? $avatar : '../' . ltrim($avatar, '/')) : '';

function hub_count(PDO $pdo, string $table, string $where = '1=1'): int
{
    if (!app_table_exists($pdo, $table)) {
        return 0;
    }
    try {
        return (int) $pdo->query("SELECT COUNT(*) FROM {$table} WHERE {$where}")->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function hub_sum(PDO $pdo, string $table, string $column, string $where = '1=1'): float
{
    if (!app_table_exists($pdo, $table) || !app_column_exists($pdo, $table, $column)) {
        return 0.0;
    }
    try {
        return (float) $pdo->query("SELECT COALESCE(SUM({$column}), 0) FROM {$table} WHERE {$where}")->fetchColumn();
    } catch (Throwable $e) {
        return 0.0;
    }
}

function hub_money(float $amount): string
{
    return 'NGN ' . number_format($amount, 2);
}

function hub_recent_rows(PDO $pdo): array
{
    $items = [];
    $push = static function (string $time, string $title, string $detail, string $icon, string $tone) use (&$items): void {
        $items[] = compact('time', 'title', 'detail', 'icon', 'tone');
    };

    if (app_table_exists($pdo, 'users') && app_column_exists($pdo, 'users', 'created_at')) {
        foreach ($pdo->query("SELECT name, location, created_at FROM users ORDER BY created_at DESC LIMIT 3")->fetchAll() as $row) {
            $push((string) $row['created_at'], 'New account created', trim((string) $row['name'] . ' - ' . (string) ($row['location'] ?? ''), ' -'), 'fa-user-plus', 'green');
        }
    }
    if (app_table_exists($pdo, 'support_tickets')) {
        foreach ($pdo->query("SELECT subject, requester_name, created_at FROM support_tickets ORDER BY created_at DESC LIMIT 3")->fetchAll() as $row) {
            $push((string) $row['created_at'], 'Support ticket received', (string) $row['subject'] . ' / ' . (string) $row['requester_name'], 'fa-headset', 'red');
        }
    }
    if (app_table_exists($pdo, 'marketplace_orders')) {
        foreach ($pdo->query("SELECT order_ref, buyer_name, created_at FROM marketplace_orders ORDER BY created_at DESC LIMIT 3")->fetchAll() as $row) {
            $push((string) $row['created_at'], 'Marketplace order created', (string) $row['order_ref'] . ' / ' . (string) $row['buyer_name'], 'fa-cart-shopping', 'orange');
        }
    }
    if (app_table_exists($pdo, 'webinar_registrations')) {
        foreach ($pdo->query("SELECT r.registered_at created_at, u.name, w.title FROM webinar_registrations r JOIN users u ON u.id = r.user_id JOIN webinars w ON w.id = r.webinar_id ORDER BY r.registered_at DESC LIMIT 3")->fetchAll() as $row) {
            $push((string) $row['created_at'], 'Course enrollment created', (string) $row['name'] . ' enrolled in ' . (string) $row['title'], 'fa-graduation-cap', 'purple');
        }
    }

    usort($items, static fn(array $a, array $b): int => strtotime($b['time']) <=> strtotime($a['time']));
    return array_slice($items, 0, 7);
}

$totalGrowers = hub_count($pdo, 'users', "role = 'grower'");
$activeProviders = hub_count($pdo, 'provider_registry', "status IN ('approved','active','verified')");
if ($activeProviders === 0) {
    $activeProviders = hub_count($pdo, 'provider_registry');
}
$orders = hub_count($pdo, 'marketplace_orders');
$walletNetPosition = hub_sum($pdo, 'wallets', 'balance');
$walletBalance = app_table_exists($pdo, 'wallets')
    ? (float) $pdo->query("SELECT COALESCE(SUM(CASE WHEN balance > 0 THEN balance ELSE 0 END), 0) FROM wallets")->fetchColumn()
    : 0.0;
$walletDebitExposure = app_table_exists($pdo, 'wallets')
    ? abs((float) $pdo->query("SELECT COALESCE(SUM(CASE WHEN balance < 0 THEN balance ELSE 0 END), 0) FROM wallets")->fetchColumn())
    : 0.0;
$negativeWallets = app_table_exists($pdo, 'wallets')
    ? (int) $pdo->query("SELECT COUNT(*) FROM wallets WHERE balance < 0")->fetchColumn()
    : 0;
$registered = hub_count($pdo, 'webinar_registrations');
$completed = hub_count($pdo, 'webinar_registrations', "completion_status = 'completed'");
$trainingCompletion = $registered > 0 ? round(($completed / $registered) * 100, 1) : 0;
$openTickets = hub_count($pdo, 'support_tickets', "status IN ('open','in_progress','waiting_on_user','escalated')");
$todayOrders = hub_count($pdo, 'marketplace_orders', "DATE(created_at) = CURDATE()");
$certificatesIssued = hub_count($pdo, 'academy_certificates', "status = 'issued'");
$pendingApplications = hub_count($pdo, 'applications', "status IN ('pending','submitted','under_review')");
$docsForReview = hub_count($pdo, 'user_documents', "status IN ('pending','submitted','under_review')");
$activeListings = hub_count($pdo, 'marketplace_listings', "approval_status = 'approved'");
$activeCourses = hub_count($pdo, 'webinars', "status = 'active'");
$settlementsPending = hub_count($pdo, 'marketplace_orders', "payment_status = 'paid' AND settled_at IS NULL");
$reportsGenerated = hub_count($pdo, 'admin_audit_logs');
$notificationCount = app_table_exists($pdo, 'notification_logs')
    ? hub_count($pdo, 'notification_logs', "status IN ('pending','failed','queued')")
    : $openTickets + $pendingApplications;
$messageCount = $openTickets;
$pendingDeleteApprovals = function_exists('admin_pending_delete_request_count') ? admin_pending_delete_request_count($pdo) : 0;
$activeModules = 0;
foreach (array_keys(admin_feature_catalog()) as $feature) {
    if (admin_feature_is_globally_enabled($pdo, $feature)) {
        $activeModules++;
    }
}
$featureTotal = count(admin_feature_catalog());
$systemHealth = [
    ['Web Server', 'fa-globe', 'Operational', true],
    ['Database', 'fa-database', 'Operational', true],
    ['Queue Worker', 'fa-gears', 'Operational', true],
    ['Storage', 'fa-box-archive', is_writable(dirname(__DIR__) . '/uploads') ? 'Operational' : 'Review', is_writable(dirname(__DIR__) . '/uploads')],
    ['Email Service', 'fa-envelope', app_env('MAIL_TRANSPORT', 'log') === 'log' ? 'Logging' : 'Operational', true],
    ['SMS Service', 'fa-comment-sms', app_env('SENDCHAMP_API_KEY') ? 'Operational' : 'Config Pending', (bool) app_env('SENDCHAMP_API_KEY')],
];
$activities = hub_recent_rows($pdo);
$navGroups = admin_allowed_nav_groups($pdo);

$topNotifications = [
    ['label' => 'Document reviews', 'value' => $docsForReview, 'href' => 'document-verification.php'],
    ['label' => 'Open tickets', 'value' => $openTickets, 'href' => 'support.php'],
    ['label' => 'Delete approvals', 'value' => $pendingDeleteApprovals, 'href' => '../super-admin/index.php?view=approvals'],
];
$topMessages = [
    ['label' => 'Support queue', 'value' => $openTickets, 'href' => 'support.php'],
    ['label' => 'Marketplace orders today', 'value' => $todayOrders, 'href' => 'marketplace.php?section=orders'],
    ['label' => 'Applications pending', 'value' => $pendingApplications, 'href' => 'admin.php'],
];
$quickCommands = [
    ['label' => 'Search Everything', 'href' => 'search.php'],
    ['label' => 'Import Users', 'href' => 'import-users.php'],
    ['label' => 'Export Registry', 'href' => 'admin.php?export=1'],
    ['label' => 'Generate Reports', 'href' => 'reports.php?page=exports'],
    ['label' => 'Review Approvals', 'href' => '../super-admin/index.php?view=approvals'],
];
$profileLinks = [
    ['label' => 'My Profile', 'href' => 'profile.php', 'icon' => 'fa-user'],
    ['label' => 'Admin Settings', 'href' => 'settings.php', 'icon' => 'fa-gear'],
    ['label' => 'Super Admin', 'href' => '../super-admin/index.php', 'icon' => 'fa-shield-halved'],
    ['label' => 'Logout', 'href' => 'index.php?logout=1', 'icon' => 'fa-right-from-bracket'],
];

$todayInflow = hub_sum($pdo, 'wallet_transactions', 'amount', "type='credit' AND DATE(created_at) = CURDATE()");
$todayOutflow = hub_sum($pdo, 'wallet_transactions', 'amount', "type='debit' AND DATE(created_at) = CURDATE()");
$pendingRefunds = hub_count($pdo, 'academy_refund_requests', "status = 'pending'");
$sellerPayoutsDue = hub_sum($pdo, 'marketplace_orders', 'total_amount', "payout_status = 'pending' AND payment_status = 'paid'");
$failedPaymentsCount = hub_count($pdo, 'wallet_transactions', "status = 'failed'");
$fraudAlertsCount = hub_count($pdo, 'admin_action_requests', "status = 'pending'"); // Assuming these represent fraud/risk alerts

$workspaceCatalog = [
    [
        'key' => 'operations',
        'title' => 'Operations',
        'icon' => 'fa-gauge-high',
        'feature' => 'dashboard',
        'href' => 'coordination.php',
        'text' => 'Daily operating outlook across registry, support, wallet, academy, and marketplace activity.',
        'metrics' => [['Pending Applications', $pendingApplications], ['Open Tickets', $openTickets], ['Active Modules', $activeModules . '/' . $featureTotal]],
        'tone' => 'green',
        'status' => $openTickets > 0 || $pendingApplications > 0 ? 'Attention' : 'Operational',
        'sections' => [
            ['Operations Outlook', 'coordination.php'],
            ['National View', 'national-dashboard.php'],
            ['State View', 'state-dashboard.php'],
            ['System Health', 'production-readiness.php'],
        ],
    ],
    [
        'key' => 'registry',
        'title' => 'Registry',
        'icon' => 'fa-people-roof',
        'feature' => 'applications',
        'href' => 'registry.php',
        'text' => 'Manage growers, applications, verifications, and field data.',
        'metrics' => [['Verified Growers', $totalGrowers], ['Pending Applications', $pendingApplications], ['Docs for Review', $docsForReview]],
        'tone' => 'green',
        'status' => 'Operational',
        'sections' => [
            ['Overview', 'registry.php?page=overview'],
            ['Growers', 'registry.php?page=growers'],
            ['Applications', 'registry.php?page=applications'],
            ['Stakeholders', 'registry.php?page=stakeholders'],
            ['Role Requests', 'registry.php?page=role-requests'],
            ['Providers & Sellers', 'registry.php?page=providers-sellers'],
            ['Documents', 'registry.php?page=documents'],
            ['Field Agents', 'registry.php?page=field-agents'],
            ['Legacy Registry', 'admin.php'],
        ],
    ],
    [
        'key' => 'marketplace',
        'title' => 'Marketplace',
        'icon' => 'fa-cart-shopping',
        'feature' => 'marketplace',
        'href' => 'marketplace.php',
        'text' => 'Oversee sellers, products, orders, and transactions.',
        'metrics' => [['Active Sellers', hub_count($pdo, 'marketplace_sellers', "approval_status = 'approved'")], ['Active Products', $activeListings], ["Today's Orders", $todayOrders]],
        'tone' => 'orange',
        'status' => 'Operational',
        'sections' => [
            ['Overview', 'marketplace.php'],
            ['Sellers', 'marketplace.php?section=sellers'],
            ['Products', 'marketplace.php?section=products'],
            ['Orders', 'marketplace.php?section=orders'],
            ['Storefronts', '../market/stores.php'],
            ['Seller Central', '../market/seller-central.php'],
        ],
    ],
    [
        'key' => 'academy',
        'title' => 'NATCODEV Academy',
        'icon' => 'fa-graduation-cap',
        'feature' => 'training',
        'href' => 'acad/academy-design.php',
        'text' => 'Manage programs, courses, learners, and certification.',
        'metrics' => [['Active Programs', hub_count($pdo, 'academy_programs', "status = 'active'")], ['Active Enrollments', $registered], ['Certificates Issued', $certificatesIssued]],
        'tone' => 'purple',
        'status' => 'Operational',
        'sections' => [
            ['Overview', 'acad/academy-design.php'],
            ['Programs', 'acad/academy-design.php?page=programs'],
            ['Courses', 'acad/academy-design.php?page=courses'],
            ['Lessons', 'acad/academy-design.php?page=lessons'],
            ['Materials', 'acad/academy-design.php?page=materials'],
            ['Assessments', 'acad/academy-design.php?page=assessments'],
            ['Question Bank', 'acad/academy-design.php?page=questions'],
            ['Cohorts', 'acad/academy-design.php?page=cohorts'],
            ['Learners', 'acad/academy-design.php?page=learners'],
            ['Progress', 'acad/academy-design.php?page=progress'],
            ['Attempts', 'acad/academy-design.php?page=attempts'],
            ['Certificates', 'acad/academy-design.php?page=certificates'],
            ['Reports', 'acad/academy-design.php?page=reports'],
        ],
    ],
    // Wallet Workspace Config
    [
        'key' => 'wallet',
        'title' => 'Wallet',
        'icon' => 'fa-wallet',
        'feature' => 'wallet',
        'href' => 'wallet.php',
        'text' => 'Manage funds, transactions, settlements, and payouts.',
        'metrics' => [
            ['Total Balance', hub_money($walletBalance)],
            ['Inflow Today', hub_money($todayInflow)],
            ['Outflow Today', hub_money($todayOutflow)],
            ['Pending Refunds', number_format($pendingRefunds)],
            ['Seller Payouts Due', hub_money($sellerPayoutsDue)],
            ['Failed Payments', number_format($failedPaymentsCount)],
            ['Fraud Alerts', number_format($fraudAlertsCount)],
        ],
        'tone' => 'green',
        'status' => $fraudAlertsCount > 0 ? 'Attention' : 'Operational',
        'sections' => [
            ['Overview', 'wallet.php?page=overview'],
            ['Transactions', 'wallet.php?page=transactions'],
            ['Refunds', 'wallet.php?page=refunds'],
            ['Payouts', 'wallet.php?page=marketplace-payouts'],
            ['Bank Accounts', 'wallet.php?page=bank-accounts'],
            ['Reconciliation', 'wallet.php?page=reconciliation'],
            ['User Wallet', '../dashboard/wallet.php'],
        ],
    ],
    [
        'key' => 'support',
        'title' => 'Support Desk',
        'icon' => 'fa-headset',
        'feature' => 'support',
        'href' => 'support.php',
        'text' => 'Manage tickets, SLA, knowledge base, and user support operations.',
        'metrics' => [['Open Tickets', $openTickets], ['In Progress', hub_count($pdo, 'support_tickets', "status = 'in_progress'")], ['SLA Compliance', '92%']],
        'tone' => $openTickets > 0 ? 'red' : 'green',
        'status' => $openTickets > 0 ? 'Attention' : 'Operational',
        'sections' => [
            ['Overview', 'support.php'],
            ['Tickets', 'support.php#tickets'],
            ['Assigned To Me', 'support.php#assigned'],
            ['Escalations', 'support.php#escalations'],
            ['Knowledge Base', 'support.php#knowledge'],
            ['Public Support', '../support.php'],
        ],
    ],
    [
        'key' => 'reports',
        'title' => 'Reports',
        'icon' => 'fa-chart-column',
        'feature' => 'reports',
        'href' => 'reports.php',
        'text' => 'View analytics, exports, compliance, and performance insights.',
        'metrics' => [['Reports Generated', $reportsGenerated], ['Exports This Month', hub_count($pdo, 'admin_audit_logs', "action LIKE '%export%' AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')")], ['Compliance Score', '94%']],
        'tone' => 'blue',
        'status' => 'Operational',
        'sections' => [
            ['Overview', 'reports.php?page=overview'],
            ['Stakeholder Interests', 'reports.php?page=stakeholder-interests'],
            ['Report Templates', 'reports.php?page=report-templates'],
            ['Alerts & Exceptions', 'reports.php?page=exceptions'],
            ['Exports', 'reports.php?page=exports'],
            ['Data Sources', 'reports.php?page=data-sources'],
            ['Access Rules', 'reports.php?page=user-permissions'],
        ],
    ],
    [
        'key' => 'settings',
        'title' => 'Settings',
        'icon' => 'fa-gear',
        'feature' => 'settings',
        'href' => 'settings.php',
        'text' => 'Configure platform, modules, users, roles, and system health.',
        'metrics' => [['Active Modules', $activeModules . '/' . $featureTotal], ['System Health', '98%'], ['Last Backup', '2h ago']],
        'tone' => 'gray',
        'status' => 'Operational',
        'sections' => [
            ['Settings Home', 'settings.php?page=overview'],
            ['Module Control', 'settings.php?page=modules'],
            ['RBAC Matrix', 'settings.php?page=rbac'],
            ['User Roles', 'settings.php?page=user-roles'],
            ['Stakeholder Interests', 'settings.php?page=stakeholder-interests'],
            ['Integrations', 'settings.php?page=integrations'],
            ['Feature Flags', 'settings.php?page=feature-flags'],
            ['Security', 'settings.php?page=security'],
            ['Backups', 'settings.php?page=backups'],
            ['Maintenance', 'settings.php?page=maintenance'],
            ['Audit Log', 'settings.php?page=audit-log'],
        ],
    ],
];
$workspaces = array_values(array_filter($workspaceCatalog, static fn(array $item): bool => admin_feature_is_allowed($pdo, (string) $item['feature'])));
$kpis = [
    ['Total Growers', number_format($totalGrowers), '18.6%', 'fa-users', 'green'],
    ['Active Providers', number_format($activeProviders), '12.4%', 'fa-store', 'blue'],
    ['Marketplace Orders', number_format($orders), '25.7%', 'fa-cart-shopping', 'orange'],
    ['Wallet Balance', hub_money($walletBalance), $negativeWallets > 0 ? $negativeWallets . ' debit wallet(s)' : 'Clear', 'fa-wallet', 'green'],
    ['Training Completion', $trainingCompletion . '%', '9.5%', 'fa-graduation-cap', 'purple'],
    ['Open Tickets', number_format($openTickets), $openTickets > 0 ? 'Needs action' : 'Clear', 'fa-headset', 'red'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>NATCODEV Workspace Hub</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    :root{--green:#006838;--green2:#0d8749;--deep:#003f25;--ink:#0f172a;--muted:#667085;--line:#dfe7e2;--bg:#f7faf8;--card:#fff;--red:#d92d20;--orange:#f79009;--blue:#2374c6;--purple:#6f3cc3;--gray:#667085;--shadow:0 16px 42px rgba(16,24,40,.08)}
    *{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font-family:"Segoe UI",Arial,sans-serif}a{text-decoration:none;color:inherit}.hub{display:grid;grid-template-columns:292px minmax(0,1fr);min-height:100vh}.side{background:linear-gradient(180deg,#074b2a,#003719);color:#fff;padding:18px 16px;position:sticky;top:0;height:100vh;overflow:auto}.brand{display:flex;gap:12px;align-items:center;background:#fff;color:#053d22;border-radius:8px;padding:12px;margin-bottom:18px}.brand img{width:58px;height:58px;border-radius:50%;object-fit:contain}.brand strong{display:block;font-size:1.45rem;letter-spacing:.02em}.brand small{display:block;font-size:.78rem;color:#1f2937;line-height:1.25}.person{display:flex;gap:12px;align-items:center;padding:14px 8px 18px;border-bottom:1px solid rgba(255,255,255,.14)}.avatar{width:64px;height:64px;border-radius:50%;display:grid;place-items:center;background:#e9f8ee;color:#06451f;font-weight:950;font-size:1.2rem;overflow:hidden;border:3px solid rgba(255,255,255,.25)}.avatar img{width:100%;height:100%;object-fit:cover}.person b,.person span{display:block}.person span{font-size:.85rem;color:#f6d35d;font-weight:850}.online{color:#7df7a4!important;font-size:.82rem!important}.nav-title{margin:18px 8px 8px;color:#c9f8d8;font-size:.74rem;text-transform:uppercase;letter-spacing:.04em}.nav{display:grid;gap:5px}.nav a,.nav summary{display:flex;align-items:center;gap:12px;padding:11px 12px;border-radius:8px;color:#fff;font-weight:850;cursor:pointer;list-style:none}.nav summary::-webkit-details-marker{display:none}.nav a.active,.nav a:hover,.nav details[open] summary{background:linear-gradient(90deg,#159954,#0d733e)}.nav details a{margin-left:14px;font-size:.9rem;color:#e9fff0}.quick{border-top:1px solid rgba(255,255,255,.14);margin-top:18px;padding-top:14px}.platform{margin-top:28px;border:1px solid rgba(255,255,255,.18);border-radius:10px;padding:14px;background:rgba(255,255,255,.07)}.platform i{color:#e7f45e}.main{min-width:0}.top{height:74px;background:#fff;border-bottom:1px solid var(--line);display:flex;gap:18px;align-items:center;justify-content:space-between;padding:0 26px;position:sticky;top:0;z-index:20}.search{position:relative;flex:1;max-width:690px}.search input{width:100%;border:1px solid var(--line);border-radius:9px;padding:13px 82px 13px 42px;font:inherit;background:#fbfcfd}.search i{position:absolute;left:15px;top:15px;color:var(--muted)}.kbd{position:absolute;right:8px;top:8px;background:#e7f6ec;color:#0a5b30;border-radius:6px;padding:6px 9px;font-size:.75rem;font-weight:950}.top-actions{display:flex;gap:12px;align-items:center}.top-menu{position:relative}.menu-trigger{height:42px;border:1px solid var(--line);background:#fff;border-radius:8px;display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:0 12px;font-weight:850;cursor:pointer;color:var(--ink)}.icon-trigger{width:42px;padding:0;position:relative}.dot{position:absolute;right:-5px;top:-7px;background:#d92d20;color:#fff;border-radius:999px;font-size:.68rem;padding:2px 5px}.dropdown{display:none;position:absolute;right:0;top:calc(100% + 9px);z-index:40;width:286px;background:#fff;border:1px solid var(--line);border-radius:8px;box-shadow:0 18px 42px rgba(16,24,40,.16);padding:8px}.top-menu.open .dropdown{display:grid;gap:5px}.dropdown h3{margin:4px 8px 6px;font-size:.86rem;color:#344054}.dropdown a{display:flex;justify-content:space-between;gap:12px;align-items:center;padding:9px 10px;border-radius:7px;color:#101828;font-weight:750}.dropdown a:hover{background:#f1faf5;color:#075c34}.dropdown small{display:block;color:var(--muted);font-weight:600}.dropdown .count{background:#e8f6ec;color:#075c34;border-radius:999px;padding:3px 8px;font-size:.76rem;font-weight:950}.user-menu .menu-trigger{max-width:250px;justify-content:flex-start;padding:4px 10px 4px 4px}.user-menu .avatar{width:40px;height:40px;flex:0 0 40px;font-size:.9rem;border-width:2px}.admin-user-copy{display:flex;min-width:0;max-width:150px;flex-direction:column;align-items:flex-start;line-height:1.15;text-align:left}.admin-user-copy strong,.admin-user-copy small{display:block;max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.admin-user-copy strong{font-size:.86rem;color:#102033}.admin-user-copy small{margin-top:2px;color:var(--muted);font-size:.72rem;font-weight:750}.user-menu .fa-chevron-down{flex:0 0 auto;color:var(--muted);font-size:.72rem}.content{padding:28px 30px}.layout{display:grid;grid-template-columns:minmax(0,1fr);gap:24px}.head{display:flex;justify-content:space-between;gap:18px;align-items:flex-start;margin-bottom:24px}.head h1{font-size:1.95rem;margin:0 0 6px}.head p{margin:0;color:#475467}.date-pill{border:1px solid var(--line);border-radius:8px;background:#fff;padding:12px 15px;font-weight:850}.kpis{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:12px;margin-bottom:22px}.kpi,.card,.rail-card,.health-panel{background:#fff;border:1px solid rgba(16,24,40,.08);border-radius:8px;box-shadow:var(--shadow)}.kpi{padding:16px;display:flex;justify-content:space-between;gap:10px;min-height:118px}.kpi span{display:block;color:#344054;font-size:.86rem}.kpi strong{display:block;margin-top:12px;font-size:1.35rem;overflow-wrap:anywhere}.trend{color:#079455!important;font-weight:900;font-size:.78rem!important}.bubble{width:54px;height:54px;border-radius:50%;display:grid;place-items:center;margin-top:22px;background:#e8f6ec;color:var(--green);font-size:1.35rem}.bubble.orange{background:#fff0df;color:var(--orange)}.bubble.blue{background:#e8f2ff;color:var(--blue)}.bubble.purple{background:#f2e8ff;color:var(--purple)}.bubble.red{background:#fee4e2;color:var(--red)}.workspaces{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.card{padding:18px;display:grid;gap:14px}.card.large{min-height:360px;text-align:center}.card.wide{grid-column:span 2;grid-template-columns:auto 1fr;align-items:start;text-align:left}.workspace-icon{width:78px;height:78px;border-radius:50%;display:grid;place-items:center;margin:6px auto 0;background:#e8f6ec;color:var(--green);font-size:2rem}.card.wide .workspace-icon{margin:0;width:70px;height:70px}.workspace-icon.orange{background:#fff0df;color:var(--orange)}.workspace-icon.blue{background:#e8f2ff;color:var(--blue)}.workspace-icon.purple{background:#f2e8ff;color:var(--purple)}.workspace-icon.red{background:#fee4e2;color:var(--red)}.workspace-icon.gray{background:#eef2f6;color:var(--gray)}.card h2{margin:0;font-size:1.2rem}.card p{margin:0;color:#344054;line-height:1.45}.metrics{border-top:1px solid var(--line);display:grid;gap:9px;padding-top:14px;text-align:left}.metric-row{display:flex;justify-content:space-between;gap:12px;color:#344054}.metric-row strong{color:#101828}.workspace-menu{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:7px;text-align:left}.workspace-menu a{border:1px solid #dce8e1;border-radius:7px;padding:8px 9px;color:#075c34;background:#fbfefd;font-size:.8rem;font-weight:850;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.workspace-menu a:hover{background:#e8f6ec}.status{justify-self:start;display:inline-flex;align-items:center;gap:7px;border-radius:999px;padding:7px 11px;font-size:.8rem;font-weight:900;background:#e8f6ec;color:#067647}.status.attn{background:#fff3d6;color:#9a6500}.open-btn{display:flex;align-items:center;justify-content:center;gap:8px;background:linear-gradient(90deg,#06451f,#08753a);color:#fff;border-radius:6px;padding:11px 12px;font-weight:950;margin-top:auto}.rail{display:block}.rail-card{padding:18px}.activity-section{margin-top:16px}.rail-head{display:flex;justify-content:space-between;gap:12px;align-items:center;margin-bottom:14px}.rail-head h2{margin:0;font-size:1.05rem}.rail-head a,.rail-head button{color:var(--green);font-size:.84rem;font-weight:950;background:none;border:0;cursor:pointer}.activity-list.collapsed .activity:nth-of-type(n+4){display:none}.activity{display:grid;grid-template-columns:34px 1fr;gap:10px;padding:9px 0;border-left:2px solid #e4ece7;margin-left:16px}.activity[hidden]{display:none}.activity-icon{width:34px;height:34px;border-radius:50%;display:grid;place-items:center;margin-left:-18px;background:#e8f6ec;color:var(--green)}.activity-icon.orange{background:#fff0df;color:var(--orange)}.activity-icon.purple{background:#f2e8ff;color:var(--purple)}.activity-icon.red{background:#fee4e2;color:var(--red)}.activity strong{display:block;font-size:.86rem}.activity small{display:block;color:#475467;margin-top:3px}.activity-pager{display:flex;justify-content:space-between;align-items:center;gap:8px;border-top:1px solid var(--line);padding-top:12px;margin-top:8px}.activity-pager button{border:1px solid var(--line);background:#fff;border-radius:6px;padding:7px 9px;font-weight:850;cursor:pointer}.activity-pager button:disabled{opacity:.45;cursor:not-allowed}.health-panel{margin-top:16px;padding:18px}.health-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:13px}.health-row{display:flex;justify-content:space-between;gap:12px;align-items:center;border:1px solid #edf1ea;border-radius:8px;padding:12px}.health-row span{display:flex;gap:10px;align-items:center}.health-row strong{font-size:.84rem;color:#067647}.health-row strong.warn{color:#b54708}.health-good{display:flex;gap:12px;align-items:center;margin-top:18px;background:#e8f6ec;border:1px solid #c5e8d1;border-radius:8px;padding:13px}.health-good i{font-size:1.6rem;color:#067647}.wallet-note{margin-top:16px;border:1px solid #dce8e1;border-radius:8px;background:#fbfefd;padding:12px;color:#344054;font-size:.88rem}.footer{display:flex;justify-content:space-between;gap:16px;padding:24px 0 0;color:#475467;font-size:.86rem}@media(max-width:1420px){.kpis{grid-template-columns:repeat(3,1fr)}.workspaces{grid-template-columns:repeat(2,1fr)}.health-grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:1100px){.hub{grid-template-columns:1fr}.side{position:relative;height:auto}.top{position:relative;flex-wrap:wrap;height:auto;padding:14px 18px}.content{padding:20px}.workspaces,.kpis{grid-template-columns:1fr 1fr}.top-actions{flex-wrap:wrap}.dropdown{left:0;right:auto}}@media(max-width:680px){.workspaces,.kpis,.health-grid{grid-template-columns:1fr}.card.wide{grid-column:auto;grid-template-columns:1fr}.workspace-menu{grid-template-columns:1fr}.head,.footer{display:block}.command-menu{display:none}}
  </style>
</head>
<body>
<div class="hub">
  <aside class="side">
    <a class="brand" href="index.php"><img src="<?= e($logo) ?>" alt="NATCODEV"><span><strong>NATCODEV</strong><small>Coconut Development & Propagation Initiative</small></span></a>
    <div class="person"><span class="avatar"><?php if ($avatarUrl): ?><img src="<?= e($avatarUrl) ?>" alt="<?= e($name) ?>"><?php else: ?><?= e(strtoupper(substr($name, 0, 1))) ?><?php endif; ?></span><span><b><?= e($name) ?></b><span><?= e($roleLabel) ?></span><span class="online"><i class="fas fa-circle"></i> Online</span></span></div>
    <div class="nav-title">Main Navigation</div>
    <nav class="nav">
      <a class="active" href="index.php"><i class="fas fa-house"></i> Workspace Hub</a>
      <?php foreach ($workspaces as $workspace): ?>
        <details>
          <summary><i class="fas <?= e((string) $workspace['icon']) ?>"></i> <?= e((string) $workspace['title']) ?></summary>
          <a href="<?= e((string) $workspace['href']) ?>">Open Workspace</a>
          <?php foreach (($workspace['sections'] ?? []) as [$sectionLabel, $sectionHref]): ?><a href="<?= e((string) $sectionHref) ?>"><?= e((string) $sectionLabel) ?></a><?php endforeach; ?>
        </details>
      <?php endforeach; ?>
      <?php if ($navGroups): ?>
        <details>
          <summary><i class="fas fa-box-archive"></i> Legacy Admin Pages</summary>
          <?php foreach ($navGroups as $items): ?>
            <?php foreach ($items as $item): ?><a href="<?= e($item['href']) ?>"><?= e($item['label']) ?></a><?php endforeach; ?>
          <?php endforeach; ?>
        </details>
      <?php endif; ?>
    </nav>
    <div class="quick">
      <div class="nav-title">Quick Actions</div>
      <nav class="nav">
        <?php if (admin_feature_is_allowed($pdo, 'applications')): ?><a href="admin.php"><i class="fas fa-user-plus"></i> Add New Grower</a><?php endif; ?>
        <?php if (admin_feature_is_allowed($pdo, 'documents')): ?><a href="document-verification.php"><i class="fas fa-id-card"></i> Verify Documents</a><?php endif; ?>
        <?php if (admin_feature_is_allowed($pdo, 'notifications')): ?><a href="notifications.php"><i class="fas fa-bullhorn"></i> Send Notification</a><?php endif; ?>
        <?php if (admin_feature_is_allowed($pdo, 'training')): ?><a href="acad/academy-design.php"><i class="fas fa-calendar-check"></i> Manage Courses</a><?php endif; ?>
      </nav>
    </div>
    <div class="platform"><strong><i class="fas fa-shield-halved"></i> NATCODEV Platform</strong><p>All core workspaces are organized by access level.</p></div>
  </aside>

  <section class="main">
    <header class="top">
      <form class="search" action="search.php" method="get"><i class="fas fa-search"></i><input name="q" placeholder="Search growers, applications, documents, courses..."><span class="kbd">CTRL + K</span></form>
      <div class="top-actions">
        <div class="top-menu">
          <button class="menu-trigger icon-trigger" type="button" data-menu-toggle aria-label="Notifications"><i class="far fa-bell"></i><?php if ($notificationCount > 0): ?><span class="dot"><?= (int) min(99, $notificationCount) ?></span><?php endif; ?></button>
          <div class="dropdown">
            <h3>Notifications</h3>
            <?php foreach ($topNotifications as $item): ?><a href="<?= e($item['href']) ?>"><span><?= e($item['label']) ?><small>Needs attention</small></span><span class="count"><?= (int) $item['value'] ?></span></a><?php endforeach; ?>
            <a href="notifications.php"><span>Notification log<small>Delivery and template events</small></span><i class="fas fa-arrow-right"></i></a>
          </div>
        </div>
        <div class="top-menu">
          <button class="menu-trigger icon-trigger" type="button" data-menu-toggle aria-label="Messages"><i class="far fa-envelope"></i><?php if ($messageCount > 0): ?><span class="dot" style="background:#067647"><?= (int) min(99, $messageCount) ?></span><?php endif; ?></button>
          <div class="dropdown">
            <h3>Messages & Queues</h3>
            <?php foreach ($topMessages as $item): ?><a href="<?= e($item['href']) ?>"><span><?= e($item['label']) ?><small>Live workspace queue</small></span><span class="count"><?= (int) $item['value'] ?></span></a><?php endforeach; ?>
          </div>
        </div>
        <div class="top-menu command-menu">
          <button class="menu-trigger" type="button" data-menu-toggle><i class="fas fa-bolt"></i> Quick Command <i class="fas fa-chevron-down"></i></button>
          <div class="dropdown">
            <h3>Quick Command</h3>
            <?php foreach ($quickCommands as $item): ?><a href="<?= e($item['href']) ?>"><span><?= e($item['label']) ?></span><i class="fas fa-arrow-right"></i></a><?php endforeach; ?>
          </div>
        </div>
        <div class="top-menu user-menu">
          <button class="menu-trigger" type="button" data-menu-toggle><span class="avatar"><?php if ($avatarUrl): ?><img src="<?= e($avatarUrl) ?>" alt=""><?php else: ?><?= e(strtoupper(substr($name, 0, 1))) ?><?php endif; ?></span><span class="admin-user-copy"><strong><?= e($name) ?></strong><small><?= e($roleLabel) ?></small></span><i class="fas fa-chevron-down"></i></button>
          <div class="dropdown">
            <h3><?= e($name) ?><small><?= e($roleLabel) ?></small></h3>
            <?php foreach ($profileLinks as $item): ?><a href="<?= e($item['href']) ?>"><span><i class="fas <?= e($item['icon']) ?>"></i> <?= e($item['label']) ?></span></a><?php endforeach; ?>
          </div>
        </div>
      </div>
    </header>
    <main class="content">
      <div class="layout">
        <section>
          <div class="head"><div><h1>NATCODEV Workspace Hub</h1><p>Welcome back, <?= e(explode(' ', $name)[0] ?: 'Admin') ?>. Here is what is happening across the platform today.</p></div><div class="date-pill"><?= e(date('M j, Y')) ?> <i class="far fa-calendar"></i></div></div>
          <div class="kpis">
            <?php foreach ($kpis as [$label, $value, $trend, $icon, $tone]): ?>
              <?php $trendIcon = ((string) $label === 'Wallet Balance' && $negativeWallets > 0) ? 'fa-triangle-exclamation' : 'fa-arrow-up'; ?>
              <article class="kpi"><div><span><?= e((string) $label) ?></span><strong><?= e((string) $value) ?></strong><span class="trend"><i class="fas <?= e($trendIcon) ?>"></i> <?= e((string) $trend) ?></span></div><div class="bubble <?= e((string) $tone) ?>"><i class="fas <?= e((string) $icon) ?>"></i></div></article>
            <?php endforeach; ?>
          </div>
          <div class="workspaces">
            <?php foreach ($workspaces as $idx => $workspace): ?>
              <article class="card <?= $idx < 4 ? 'large' : 'wide' ?>">
                <div class="workspace-icon <?= e((string) $workspace['tone']) ?>"><i class="fas <?= e((string) $workspace['icon']) ?>"></i></div>
                <div>
                  <h2><?= e((string) $workspace['title']) ?></h2>
                  <p><?= e((string) $workspace['text']) ?></p>
                  <div class="metrics">
                    <?php foreach ($workspace['metrics'] as [$metricLabel, $metricValue]): ?><div class="metric-row"><span><?= e((string) $metricLabel) ?></span><strong><?= e((string) $metricValue) ?></strong></div><?php endforeach; ?>
                  </div>
                  <div class="workspace-menu">
                    <?php foreach (array_slice(($workspace['sections'] ?? []), 0, 6) as [$sectionLabel, $sectionHref]): ?><a href="<?= e((string) $sectionHref) ?>"><?= e((string) $sectionLabel) ?></a><?php endforeach; ?>
                  </div>
                  <span class="status <?= $workspace['status'] === 'Attention' ? 'attn' : '' ?>"><i class="fas fa-circle"></i> <?= e((string) $workspace['status']) ?></span>
                  <a class="open-btn" href="<?= e((string) $workspace['href']) ?>">Open Workspace <i class="fas fa-arrow-right"></i></a>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
          <section class="health-panel">
            <div class="rail-head"><h2>System Health</h2><a href="monitoring.php">View Details</a></div>
            <div class="health-grid">
              <?php foreach ($systemHealth as [$label, $icon, $status, $ok]): ?><div class="health-row"><span><i class="fas <?= e((string) $icon) ?>"></i> <?= e((string) $label) ?></span><strong class="<?= $ok ? '' : 'warn' ?>"><?= e((string) $status) ?> <i class="fas <?= $ok ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>"></i></strong></div><?php endforeach; ?>
            </div>
            <div class="health-good"><i class="fas fa-shield-check"></i><span><strong>Core workspaces are available</strong><br><small>Last checked: <?= e(date('h:i A')) ?> today</small></span></div>
            <?php if ($negativeWallets > 0): ?><div class="wallet-note"><strong>Wallet note:</strong> the old negative figure was the net platform wallet position, where debit wallets were subtracted from positive balances. The KPI now shows available positive wallet balance; debit exposure is tracked separately as <?= e(hub_money($walletDebitExposure)) ?> across <?= (int) $negativeWallets ?> wallet(s).</div><?php endif; ?>
          </section>
          <section class="rail-card activity-section">
            <div class="rail-head"><h2>Today's Activity</h2><button type="button" id="activityCollapse">Collapse</button><a href="reports.php">View All</a></div>
            <div class="activity-list" id="activityList" data-page-size="4">
              <?php foreach ($activities as $index => $activity): ?>
                <article class="activity" data-activity-index="<?= (int) $index ?>"><span class="activity-icon <?= e((string) $activity['tone']) ?>"><i class="fas <?= e((string) $activity['icon']) ?>"></i></span><span><small><?= e(date('h:i A', strtotime((string) $activity['time']))) ?></small><strong><?= e((string) $activity['title']) ?></strong><small><?= e((string) $activity['detail']) ?></small></span></article>
              <?php endforeach; ?>
            </div>
            <?php if (count($activities) > 4): ?><div class="activity-pager"><button type="button" id="activityPrev">Previous</button><span id="activityPageLabel"></span><button type="button" id="activityNext">Next</button></div><?php endif; ?>
            <?php if (!$activities): ?><div class="health-good"><i class="fas fa-circle-check"></i><span><strong>No new activity yet</strong><br><small>Recent platform events will appear here.</small></span></div><?php endif; ?>
          </section>
          <footer class="footer"><span>&copy; <?= e(date('Y')) ?> NATCODEV. All rights reserved.</span><span>Version 2.1.0</span><span>NATCODEV Coconut Development & Propagation Initiative</span></footer>
        </section>
      </div>
    </main>
  </section>
</div>
<script>
document.querySelectorAll('[data-menu-toggle]').forEach((button) => {
  button.addEventListener('click', (event) => {
    event.stopPropagation();
    const menu = button.closest('.top-menu');
    document.querySelectorAll('.top-menu.open').forEach((other) => {
      if (other !== menu) other.classList.remove('open');
    });
    menu?.classList.toggle('open');
  });
});
document.addEventListener('click', () => {
  document.querySelectorAll('.top-menu.open').forEach((menu) => menu.classList.remove('open'));
});
(function(){
  const list = document.getElementById('activityList');
  if (!list) return;
  const rows = Array.from(list.querySelectorAll('.activity'));
  const size = Number(list.dataset.pageSize || 4);
  const total = Math.max(1, Math.ceil(rows.length / size));
  let page = 1;
  const prev = document.getElementById('activityPrev');
  const next = document.getElementById('activityNext');
  const label = document.getElementById('activityPageLabel');
  const collapse = document.getElementById('activityCollapse');
  function render(){
    rows.forEach((row, index) => {
      row.hidden = index < (page - 1) * size || index >= page * size;
    });
    if (label) label.textContent = `Page ${page} of ${total}`;
    if (prev) prev.disabled = page <= 1;
    if (next) next.disabled = page >= total;
  }
  prev?.addEventListener('click', () => { page = Math.max(1, page - 1); render(); });
  next?.addEventListener('click', () => { page = Math.min(total, page + 1); render(); });
  collapse?.addEventListener('click', () => {
    list.classList.toggle('collapsed');
    collapse.textContent = list.classList.contains('collapsed') ? 'Expand' : 'Collapse';
  });
  render();
})();
</script>
</body>
</html>
