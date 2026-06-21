<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/admin-layout.php';
require_once __DIR__ . '/../../lib/marketplace.php';

session_start();
$pdo = db();
admin_ensure_schema($pdo);
marketplace_ensure_schema($pdo);
admin_require($pdo);

function mx_e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$mxAdmin = current_user($pdo) ?: [];
$mxAdminName = trim((string) (($mxAdmin['name'] ?? '') ?: ($mxAdmin['email'] ?? 'Admin User')));
$mxAdminRole = ucwords(str_replace('_', ' ', (string) ($mxAdmin['platform_role'] ?? $mxAdmin['role'] ?? 'Admin')));
$mxAdminInitials = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $mxAdminName) ?: 'AD', 0, 2));
$mxScriptDir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/admin/marketplace.php')));
$mxAdminBase = basename($mxScriptDir) === 'acad' ? dirname($mxScriptDir) : $mxScriptDir;
$mxAdminBase = rtrim($mxAdminBase, '/') ?: '/admin';
$mxPublicBase = preg_replace('#/admin$#', '', $mxAdminBase) ?: '';
$mxAdminPicture = ltrim((string) ($mxAdmin['profile_picture'] ?? ''), '/');
$mxAdminPictureUrl = $mxAdminPicture !== '' ? $mxPublicBase . '/' . $mxAdminPicture : '';

function mx_rows(PDO $pdo, string $sql, array $params = []): array
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log('Marketplace workspace rows failed: ' . $e->getMessage());
        return [];
    }
}

function mx_scalar(PDO $pdo, string $sql, array $params = []): float
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (float) ($stmt->fetchColumn() ?: 0);
    } catch (Throwable $e) {
        error_log('Marketplace workspace scalar failed: ' . $e->getMessage());
        return 0.0;
    }
}

$mxSellers = mx_rows($pdo, "
    SELECT s.id, s.store_name, s.seller_type, s.approval_status, s.verification_status, s.created_at,
           COALESCE(u.name, s.contact_person) owner_name, COALESCE(u.email, s.email) owner_email,
           (SELECT COUNT(*) FROM marketplace_listings l WHERE l.seller_id = s.id) listings,
           (SELECT COALESCE(SUM(o.total_amount), 0) FROM marketplace_orders o WHERE o.seller_id = s.id AND o.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) sales_7d
    FROM marketplace_sellers s
    LEFT JOIN users u ON u.id = s.user_id
    ORDER BY FIELD(s.approval_status,'pending','approved','rejected','suspended'), s.created_at DESC
    LIMIT 200
");
$mxProducts = mx_rows($pdo, "
    SELECT l.id, l.title, l.listing_type, l.price, l.quantity_available, l.unit, l.availability_status, l.approval_status, l.created_at,
           s.store_name, c.name category_name
    FROM marketplace_listings l
    JOIN marketplace_sellers s ON s.id = l.seller_id
    LEFT JOIN marketplace_categories c ON c.id = l.category_id
    ORDER BY FIELD(l.approval_status,'pending','approved','rejected','suspended'), l.created_at DESC
    LIMIT 250
");
$mxOrders = mx_rows($pdo, "
    SELECT o.id, o.order_ref, o.buyer_name, o.total_amount, o.payment_status, o.status, o.created_at,
           l.title listing_title, s.store_name
    FROM marketplace_orders o
    JOIN marketplace_listings l ON l.id = o.listing_id
    JOIN marketplace_sellers s ON s.id = o.seller_id
    ORDER BY o.created_at DESC
    LIMIT 200
");
$mxInquiries = mx_rows($pdo, "
    SELECT i.id, i.buyer_name, i.buyer_email, i.status, i.quoted_amount, i.created_at, l.title listing_title, s.store_name
    FROM marketplace_inquiries i
    JOIN marketplace_listings l ON l.id = i.listing_id
    JOIN marketplace_sellers s ON s.id = i.seller_id
    ORDER BY i.created_at DESC
    LIMIT 160
");
$mxTransactions = app_table_exists($pdo, 'wallet_transactions') ? mx_rows($pdo, "
    SELECT wt.id, wt.type, wt.amount, wt.status, wt.reference, wt.created_at, u.name user_name
    FROM wallet_transactions wt
    LEFT JOIN wallets w ON w.id = wt.wallet_id
    LEFT JOIN users u ON u.id = w.user_id
    ORDER BY wt.created_at DESC
    LIMIT 160
") : [];

$mxExport = preg_replace('/[^a-z0-9_-]/', '', strtolower((string) ($_GET['export'] ?? '')));
if ($mxExport !== '') {
    $rows = match ($mxExport) {
        'sellers', 'seller-report' => $mxSellers,
        'products', 'inventory' => $mxProducts,
        'orders', 'sales', 'sales-report' => $mxOrders,
        'customers', 'disputes' => $mxInquiries,
        'financial', 'payments' => $mxTransactions,
        default => array_merge($mxOrders, $mxProducts),
    };
    app_export_csv('natcodev-marketplace-' . $mxExport . '-' . date('Ymd') . '.csv', $rows ? array_keys($rows[0]) : [], $rows);
}

$mxData = [
    'sellers' => $mxSellers,
    'products' => $mxProducts,
    'orders' => $mxOrders,
    'inquiries' => $mxInquiries,
    'transactions' => $mxTransactions,
    'stats' => [
        'sellers' => count($mxSellers),
        'pending_sellers' => count(array_filter($mxSellers, static fn(array $row): bool => (string) $row['approval_status'] === 'pending')),
        'products' => count($mxProducts),
        'pending_products' => count(array_filter($mxProducts, static fn(array $row): bool => (string) $row['approval_status'] === 'pending')),
        'orders' => count($mxOrders),
        'order_value' => array_sum(array_map(static fn(array $row): float => (float) ($row['total_amount'] ?? 0), $mxOrders)),
        'wallet_volume' => array_sum(array_map(static fn(array $row): float => (float) ($row['amount'] ?? 0), $mxTransactions)),
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>NATCODEV Marketplace - Admin Workspace</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/inter@5.0.0/index.min.css">
<style>
*{margin:0;padding:0;box-sizing:border-box}
:root{
  --g900:#0a2418;--g800:#0f3324;--g700:#164a33;--g600:#1e6b47;--g500:#2a9d6a;--g400:#34c48a;--g100:#e6f7ef;--g50:#f0faf5;
  --bg:#f4f6f4;--card:#fff;--text:#1a1a1a;--text2:#6b7280;--border:#e5e7eb;
  --danger:#dc2626;--warn:#f59e0b;--info:#3b82f6;--success:#10b981;--purple:#8b5cf6;
}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);display:flex;min-height:100vh}
.sidebar{width:260px;background:var(--g900);color:#fff;position:fixed;top:0;left:0;bottom:0;overflow-y:auto;z-index:100;display:flex;flex-direction:column}
.sidebar-header{padding:18px;display:flex;align-items:center;gap:12px;border-bottom:1px solid rgba(255,255,255,.1)}
.sidebar-logo{width:42px;height:42px;background:var(--g400);border-radius:10px;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:11px;color:var(--g900);flex-shrink:0}
.sidebar-brand{font-size:14px;font-weight:700;line-height:1.2}
.sidebar-brand small{display:block;font-size:10px;font-weight:400;opacity:.7;margin-top:2px}
.workspace-badge{margin:16px 16px 4px;padding:6px 10px;background:rgba(255,255,255,.08);border-radius:8px;font-size:10px;text-transform:uppercase;letter-spacing:1px;opacity:.7}
.workspace-select{margin:0 16px 16px;padding:10px 12px;background:rgba(255,255,255,.06);border-radius:8px;display:flex;align-items:center;justify-content:space-between;font-size:13px;font-weight:600;cursor:pointer}
.nav-section{padding:8px 0}
.nav-section-title{padding:0 16px;font-size:10px;text-transform:uppercase;letter-spacing:1px;opacity:.5;margin-bottom:6px}
.nav-item{display:flex;align-items:center;gap:12px;padding:10px 16px;cursor:pointer;transition:all .2s;font-size:13px;color:rgba(255,255,255,.75);border-left:3px solid transparent}
.nav-item:hover{background:rgba(255,255,255,.08);color:#fff}
.nav-item.active{background:var(--g600);color:#fff;border-left-color:var(--g400)}
.nav-item svg{width:18px;height:18px;flex-shrink:0}
.nav-item .badge{margin-left:auto;background:var(--danger);color:#fff;font-size:10px;font-weight:700;padding:2px 7px;border-radius:10px}
.nav-group{padding:0}
.nav-group-header{display:flex;align-items:center;justify-content:space-between;padding:10px 16px;cursor:pointer;font-size:13px;color:rgba(255,255,255,.75)}
.nav-group-header:hover{color:#fff}
.nav-group-header svg{width:16px;height:16px;transition:transform .2s}
.nav-group.open .nav-group-header svg{transform:rotate(90deg)}
.nav-sub{display:none}
.nav-group.open .nav-sub{display:block}
.nav-sub .nav-item{padding-left:48px;font-size:12px}
.sidebar-footer{margin-top:auto;padding:16px;border-top:1px solid rgba(255,255,255,.1);display:flex;align-items:center;gap:10px}
.sidebar-avatar{width:38px;height:38px;border-radius:50%;background:var(--g600);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0}
.sidebar-user{font-size:13px;font-weight:600}
.sidebar-user small{display:block;font-size:11px;opacity:.6;font-weight:400}
.status-dot{width:8px;height:8px;background:var(--success);border-radius:50%;display:inline-block;margin-right:4px}

.main{margin-left:260px;flex:1;min-height:100vh}
.topbar{background:#fff;padding:12px 28px;display:flex;align-items:center;gap:16px;border-bottom:1px solid var(--border);position:sticky;top:0;z-index:50}
.menu-toggle{display:none;background:none;border:none;cursor:pointer;font-size:20px}
.topbar-search{flex:1;max-width:480px;position:relative}
.topbar-search input{width:100%;padding:9px 14px 9px 38px;border:1px solid var(--border);border-radius:8px;font-size:13px;background:var(--bg)}
.topbar-search svg{position:absolute;left:12px;top:50%;transform:translateY(-50%);width:16px;height:16px;color:var(--text2)}
.topbar-kbd{position:absolute;right:12px;top:50%;transform:translateY(-50%);font-size:10px;color:var(--text2);background:#fff;padding:2px 6px;border:1px solid var(--border);border-radius:4px}
.topbar-actions{display:flex;align-items:center;gap:10px;margin-left:auto}
.topbar-icon{width:38px;height:38px;border-radius:8px;border:1px solid var(--border);display:flex;align-items:center;justify-content:center;cursor:pointer;position:relative;background:#fff}
.topbar-icon .dot{position:absolute;top:6px;right:6px;width:8px;height:8px;background:var(--danger);border-radius:50%;border:2px solid #fff}
.wallet-balance{display:flex;align-items:center;gap:8px;padding:6px 12px;background:var(--g50);border:1px solid var(--border);border-radius:8px;cursor:pointer}
.wallet-balance strong{font-size:13px}
.wallet-balance small{font-size:10px;color:var(--text2);display:block}
.topbar-profile{display:flex;align-items:center;gap:10px;min-width:0;max-width:260px;cursor:pointer;padding:4px 10px 4px 6px;border-radius:8px}
.topbar-profile:hover{background:var(--bg)}
.topbar-avatar{width:36px;height:36px;border-radius:50%;background:var(--g600);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:13px}
.topbar-profile-info{display:flex;min-width:0;max-width:160px;flex-direction:column;align-items:flex-start;font-size:13px;font-weight:700;line-height:1.15;text-align:left}
.topbar-profile-info,.topbar-profile-info small{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.topbar-profile-info small{display:block;max-width:100%;margin-top:2px;font-size:11px;color:var(--text2);font-weight:500}
.topbar-menu-wrap{position:relative}.topbar-icon{color:var(--text);text-decoration:none}.topbar-menu{display:none;position:absolute;right:0;top:48px;width:270px;background:#fff;border:1px solid var(--border);border-radius:10px;box-shadow:0 18px 40px rgba(0,0,0,.12);padding:8px;z-index:90}.topbar-menu.active{display:block}.topbar-menu a{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:9px 10px;border-radius:8px;color:var(--text);text-decoration:none;font-weight:650}.topbar-menu a:hover{background:var(--bg)}.topbar-menu small{display:block;color:var(--text2);font-weight:500;margin-top:2px}.topbar-menu-label{padding:6px 10px 8px;color:var(--text2);font-size:11px;text-transform:uppercase;letter-spacing:.4px;font-weight:800}.topbar-profile{background:none;border:0;color:var(--text);font:inherit}.topbar-avatar{overflow:hidden}.topbar-avatar img{width:100%;height:100%;object-fit:cover;display:block}

.content{padding:24px}
.page{display:none}
.page.active{display:block}
.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px;flex-wrap:wrap;gap:12px}
.page-title{font-size:22px;font-weight:700}
.page-subtitle{font-size:13px;color:var(--text2);margin-top:2px}
.btn{padding:9px 18px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:none;display:inline-flex;align-items:center;gap:6px;transition:all .2s}
.btn-primary{background:var(--g700);color:#fff}
.btn-primary:hover{background:var(--g800)}
.btn-secondary{background:#fff;color:var(--text);border:1px solid var(--border)}
.btn-secondary:hover{background:var(--bg)}
.btn-danger{background:var(--danger);color:#fff}
.btn-warn{background:var(--warn);color:#fff}
.btn-sm{padding:6px 12px;font-size:12px}
.btn-icon{padding:6px;background:none;border:1px solid var(--border);border-radius:6px;cursor:pointer;font-size:14px}

.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;margin-bottom:22px}
.stat-card{background:#fff;padding:18px;border-radius:12px;border:1px solid var(--border)}
.stat-card-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px}
.stat-card-icon{width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center}
.stat-card-icon svg{width:20px;height:20px}
.stat-card-label{font-size:12px;color:var(--text2);font-weight:500}
.stat-card-value{font-size:24px;font-weight:700;margin-top:4px}
.stat-card-change{font-size:11px;margin-top:6px;font-weight:500}
.stat-card-change.up{color:var(--success)}
.stat-card-change.down{color:var(--danger)}

.card{background:#fff;border-radius:12px;border:1px solid var(--border);margin-bottom:18px}
.card-header{padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px}
.card-title{font-size:15px;font-weight:700}
.card-body{padding:20px}
.card-body.p0{padding:0}

table{width:100%;border-collapse:collapse}
th,td{padding:12px 20px;text-align:left;font-size:13px}
th{background:var(--bg);font-weight:600;color:var(--text2);font-size:11px;text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid var(--border)}
td{border-bottom:1px solid var(--border)}
tr:last-child td{border-bottom:none}
tr:hover td{background:var(--g50)}

.status-badge{padding:4px 10px;border-radius:20px;font-size:11px;font-weight:600;display:inline-block}
.status-active,.status-live,.status-delivered,.status-approved{background:#dcfce7;color:#166534}
.status-pending,.status-processing,.status-review{background:#fef3c7;color:#92400e}
.status-completed,.status-shipped,.status-resolved{background:#dbeafe;color:#1e40af}
.status-draft,.status-inactive,.status-expired{background:#f3f4f6;color:#4b5563}
.status-cancelled,.status-outofstock,.status-rejected,.status-open{background:#fee2e2;color:#991b1b}
.status-lowstock{background:#fff7ed;color:#c2410c}

.progress-bar{height:6px;background:var(--border);border-radius:3px;overflow:hidden;width:100%}
.progress-fill{height:100%;background:var(--g500);border-radius:3px;transition:width .3s}

.form-group{margin-bottom:14px}
.form-label{display:block;font-size:12px;font-weight:600;margin-bottom:6px;color:var(--text2)}
.form-input,.form-select,.form-textarea{width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:8px;font-size:13px;font-family:inherit}
.form-input:focus,.form-select:focus,.form-textarea:focus{outline:none;border-color:var(--g500);box-shadow:0 0 0 3px rgba(42,157,106,.1)}
.form-textarea{resize:vertical;min-height:80px}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:14px}

.tabs{display:flex;gap:4px;border-bottom:1px solid var(--border);margin-bottom:18px}
.tab{padding:10px 16px;font-size:13px;font-weight:500;cursor:pointer;border-bottom:2px solid transparent;color:var(--text2)}
.tab.active{color:var(--g700);border-bottom-color:var(--g700);font-weight:600}
.tab:hover{color:var(--text)}

.filter-bar{display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;align-items:center}
.filter-bar input,.filter-bar select{padding:8px 12px;border:1px solid var(--border);border-radius:8px;font-size:13px}

.modal-overlay{display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:200;align-items:center;justify-content:center}
.modal-overlay.active{display:flex}
.modal{background:#fff;border-radius:12px;width:90%;max-width:560px;max-height:90vh;overflow-y:auto}
.modal-header{padding:18px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
.modal-title{font-size:16px;font-weight:700}
.modal-body{padding:22px}
.modal-footer{padding:16px 22px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:10px}

.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:18px}
.grid-3{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}
.grid-4{display:grid;grid-template-columns:repeat(4,1fr);gap:18px}

.avatar-sm{width:32px;height:32px;border-radius:50%;background:var(--g100);color:var(--g700);display:inline-flex;align-items:center;justify-content:center;font-weight:600;font-size:12px;flex-shrink:0}
.avatar-row{display:flex;align-items:center;gap:10px}

.toast{position:fixed;bottom:24px;right:24px;background:var(--g800);color:#fff;padding:12px 20px;border-radius:8px;font-size:13px;z-index:300;display:none;animation:slideIn .3s}
@keyframes slideIn{from{transform:translateX(100%);opacity:0}to{transform:translateX(0);opacity:1}}

.chip{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;background:var(--g100);color:var(--g700);border-radius:20px;font-size:11px;font-weight:500}
.quick-action{padding:18px;border:1px solid var(--border);border-radius:12px;cursor:pointer;transition:all .2s;background:#fff}
.quick-action:hover{border-color:var(--g500);box-shadow:0 4px 12px rgba(0,0,0,.05)}
.quick-action-icon{width:44px;height:44px;border-radius:10px;display:flex;align-items:center;justify-content:center;margin-bottom:10px;font-size:20px}
.quick-action-title{font-weight:700;font-size:14px;margin-bottom:2px}
.quick-action-desc{font-size:12px;color:var(--text2)}

.chart-container{position:relative;height:260px;padding:20px}
.chart-bars{display:flex;align-items:end;gap:12px;height:200px;padding:0 10px}
.chart-bar-group{flex:1;display:flex;flex-direction:column;align-items:center;gap:4px}
.chart-bar{width:100%;border-radius:4px 4px 0 0;position:relative;min-height:4px}
.chart-label{font-size:10px;color:var(--text2)}
.chart-legend{display:flex;gap:16px;justify-content:center;margin-top:12px;font-size:11px}
.chart-legend-item{display:flex;align-items:center;gap:6px}
.chart-legend-dot{width:10px;height:10px;border-radius:2px}

.product-thumb{width:36px;height:36px;border-radius:8px;background:var(--bg);display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}

@media(max-width:1100px){.grid-4{grid-template-columns:repeat(2,1fr)}.grid-3{grid-template-columns:1fr 1fr}}
@media(max-width:900px){
  .sidebar{width:70px}.sidebar-brand,.workspace-badge,.workspace-select span,.nav-section-title,.nav-item span,.sidebar-user,.sidebar-user small,.nav-item .badge,.nav-group-header span{display:none}
  .nav-item{justify-content:center;padding:12px}.nav-sub .nav-item{padding-left:12px}
  .main{margin-left:70px}.grid-2,.grid-3,.grid-4,.form-row{grid-template-columns:1fr}
  .menu-toggle{display:block}.topbar-kbd{display:none}
}
</style>
</head>
<body>

<aside class="sidebar">
  <div class="sidebar-header">
    <div class="sidebar-logo">🌴<br>NC</div>
    <div class="sidebar-brand">NATCODEV<small>Coconut Development & Propagation</small></div>
  </div>
  <div class="workspace-badge">WORKSPACE</div>
  <div class="workspace-select"><span> Marketplace</span><span>▾</span></div>

  <div class="nav-section">
    <div class="nav-section-title">Marketplace Navigation</div>
    <div class="nav-item active" data-page="overview">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
      <span>Overview</span>
    </div>
    <div class="nav-item" data-page="sellers">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
      <span>Sellers</span>
    </div>
    <div class="nav-item" data-page="products">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/></svg>
      <span>Products</span>
    </div>
    <div class="nav-group open">
      <div class="nav-group-header" onclick="this.parentElement.classList.toggle('open')">
        <span style="display:flex;align-items:center;gap:12px"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg><span>Orders</span></span>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
      </div>
      <div class="nav-sub">
        <div class="nav-item" data-page="orders"><span>All Orders</span></div>
        <div class="nav-item" data-page="order-processing"><span>Processing</span></div>
        <div class="nav-item" data-page="order-fulfillment"><span>Fulfillment</span></div>
      </div>
    </div>
    <div class="nav-item" data-page="inventory">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 7h-7m7 10h-7M4 7h7m-7 10h7M12 3v18"/></svg>
      <span>Inventory</span>
    </div>
    <div class="nav-item" data-page="storefronts">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l1-5h16l1 5M3 9v11a1 1 0 001 1h16a1 1 0 001-1V9M3 9h18M9 21V12h6v9"/></svg>
      <span>Storefronts</span>
    </div>
    <div class="nav-item" data-page="payments">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
      <span>Payments</span>
    </div>
    <div class="nav-item" data-page="disputes">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <span>Disputes</span>
      <span class="badge">3</span>
    </div>
    <div class="nav-item" data-page="promotions">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 12 20 22 4 22 4 12"/><rect x="2" y="7" width="20" height="5"/><line x1="12" y1="22" x2="12" y2="7"/><path d="M12 7H7.5a2.5 2.5 0 010-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 000-5C13 2 12 7 12 7z"/></svg>
      <span>Promotions</span>
    </div>
    <div class="nav-group">
      <div class="nav-group-header" onclick="this.parentElement.classList.toggle('open')">
        <span style="display:flex;align-items:center;gap:12px"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg><span>Reports</span></span>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
      </div>
      <div class="nav-sub">
        <div class="nav-item" data-page="reports"><span>Analytics</span></div>
        <div class="nav-item" data-page="sales-report"><span>Sales Report</span></div>
        <div class="nav-item" data-page="seller-report"><span>Seller Report</span></div>
      </div>
    </div>
    <div class="nav-group">
      <div class="nav-group-header" onclick="this.parentElement.classList.toggle('open')">
        <span style="display:flex;align-items:center;gap:12px"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg><span>Settings</span></span>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
      </div>
      <div class="nav-sub">
        <div class="nav-item" data-page="settings"><span>General</span></div>
        <div class="nav-item" data-page="commission"><span>Commission</span></div>
        <div class="nav-item" data-page="notifications-settings"><span>Notifications</span></div>
      </div>
    </div>
  </div>

  <div class="nav-section">
    <div class="nav-section-title">Quick Links</div>
    <div class="nav-item" data-page="add-product">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
      <span>Add Product</span>
    </div>
    <div class="nav-item" data-page="verify-seller">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
      <span>Verify Seller</span>
    </div>
    <div class="nav-item" data-page="create-promotion">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 12 20 22 4 22 4 12"/><rect x="2" y="7" width="20" height="5"/></svg>
      <span>Create Promotion</span>
    </div>
    <div class="nav-item" data-page="export-report">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
      <span>Export Report</span>
    </div>
  </div>

  <div class="sidebar-footer">
    <div class="sidebar-avatar">GD</div>
    <div class="sidebar-user">Grace Deh<small><span class="status-dot"></span>Online</small></div>
  </div>
</aside>

<div class="main">
  <div class="topbar">
    <button class="menu-toggle" onclick="document.querySelector('.sidebar').classList.toggle('collapsed')">☰</button>
    <div class="topbar-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" placeholder="Search sellers, products, orders, SKUs..." id="globalSearch">
      <span class="topbar-kbd">CTRL + K</span>
    </div>
    <div class="topbar-actions">
      <a class="topbar-icon" href="<?= mx_e($mxAdminBase) ?>/index.php" title="Workspace Hub">⌂</a>
      <a class="topbar-icon" href="<?= mx_e($mxPublicBase) ?>/index.php" title="Public Homepage">↗</a>
      <a class="topbar-icon" href="<?= mx_e($mxAdminBase) ?>/notifications.php" title="Notifications"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg><span class="dot"></span></a>
      <a class="topbar-icon" href="<?= mx_e($mxAdminBase) ?>/support.php" title="Messages"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg><span class="dot"></span></a>
      <a class="wallet-balance" href="<?= mx_e($mxAdminBase) ?>/wallet.php">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--g700)" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
        <div><small>Wallet Balance</small><strong>₦4,977,388.45</strong></div>
      </a>
      <div class="topbar-menu-wrap">
        <button class="topbar-profile" type="button" data-topbar-menu="profileMenu" aria-haspopup="true" aria-expanded="false">
          <div class="topbar-avatar"><?php if ($mxAdminPictureUrl !== ''): ?><img src="<?= mx_e($mxAdminPictureUrl) ?>" alt=""><?php else: ?><?= mx_e($mxAdminInitials) ?><?php endif; ?></div>
          <div class="topbar-profile-info"><?= mx_e($mxAdminName) ?><small><?= mx_e($mxAdminRole) ?></small></div>
        </button>
        <div class="topbar-menu" id="profileMenu">
          <div class="topbar-menu-label">Profile</div>
          <a href="<?= mx_e($mxAdminBase) ?>/profile.php"><span>Edit Profile<small>Photo, name, contact</small></span></a>
          <a href="<?= mx_e($mxAdminBase) ?>/index.php"><span>Workspace Hub</span></a>
          <a href="<?= mx_e($mxPublicBase) ?>/index.php"><span>Public Homepage</span></a>
          <a href="<?= mx_e($mxAdminBase) ?>/index.php?logout=1"><span>Logout from workspace</span></a>
          <a href="<?= mx_e($mxAdminBase) ?>/admin.php?logout=1"><span>Logout via legacy admin</span></a>
          <a href="<?= mx_e($mxAdminBase) ?>/login.php?logout=1"><span>Logout to login</span></a>
        </div>
      </div>
    </div>
  </div>

  <div class="content">

    <!-- OVERVIEW -->
    <div class="page active" id="page-overview">
      <div class="page-header">
        <div><div class="page-title">NATCODEV Marketplace</div><div class="page-subtitle">Overview of marketplace performance and operations.</div></div>
        <div class="filter-bar" style="margin:0"><button class="btn btn-secondary btn-sm">📅 May 18 – May 24, 2026 ▾</button></div>
      </div>

      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-header"><div class="stat-card-label">Active Sellers</div><div class="stat-card-icon" style="background:#e6f7ef;color:var(--g700)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg></div></div><div class="stat-card-value">1,248</div><div class="stat-card-change up">↑ 12.4% vs last 7 days</div></div>
        <div class="stat-card"><div class="stat-card-header"><div class="stat-card-label">Listed Products</div><div class="stat-card-icon" style="background:#dbeafe;color:#1e40af"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/></svg></div></div><div class="stat-card-value">4,756</div><div class="stat-card-change up">↑ 8.7% vs last 7 days</div></div>
        <div class="stat-card"><div class="stat-card-header"><div class="stat-card-label">Orders Today</div><div class="stat-card-icon" style="background:#fef3c7;color:#92400e"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/></svg></div></div><div class="stat-card-value">182</div><div class="stat-card-change up">↑ 23.6% vs yesterday</div></div>
        <div class="stat-card"><div class="stat-card-header"><div class="stat-card-label">Gross Sales (7D)</div><div class="stat-card-icon" style="background:#dcfce7;color:#166534"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg></div></div><div class="stat-card-value">₦3,513,240</div><div class="stat-card-change up">↑ 18.9% vs last 7 days</div></div>
        <div class="stat-card"><div class="stat-card-header"><div class="stat-card-label">Pending Payouts</div><div class="stat-card-icon" style="background:#fef3c7;color:#92400e"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg></div></div><div class="stat-card-value">₦1,245,789</div><div class="stat-card-change down">↓ 6.3% vs last 7 days</div></div>
        <div class="stat-card"><div class="stat-card-header"><div class="stat-card-label">Disputes</div><div class="stat-card-icon" style="background:#fee2e2;color:#991b1b"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div></div><div class="stat-card-value">3</div><div class="stat-card-change down">↓ 25% vs last 7 days</div></div>
      </div>

      <div class="grid-2" style="grid-template-columns:2fr 1fr">
        <div class="card">
          <div class="card-header">
            <div class="card-title">Sales Overview</div>
            <div style="display:flex;gap:8px;align-items:center">
              <select class="form-select" style="width:auto;padding:6px 10px;font-size:12px"><option>Last 7 Days</option><option>Last 30 Days</option><option>This Year</option></select>
              <div style="display:flex;gap:4px;background:var(--bg);padding:3px;border-radius:6px"><button class="btn btn-sm" style="background:var(--g700);color:#fff">Daily</button><button class="btn btn-sm btn-secondary">Weekly</button><button class="btn btn-sm btn-secondary">Monthly</button></div>
            </div>
          </div>
          <div class="chart-container">
            <div class="chart-bars">
              <div class="chart-bar-group"><div class="chart-bar" style="height:60px;background:var(--g400)"></div><div class="chart-bar" style="height:40px;background:var(--info);margin-top:-60px;opacity:.6"></div><div class="chart-label">May 18</div></div>
              <div class="chart-bar-group"><div class="chart-bar" style="height:90px;background:var(--g400)"></div><div class="chart-bar" style="height:60px;background:var(--info);margin-top:-90px;opacity:.6"></div><div class="chart-label">May 19</div></div>
              <div class="chart-bar-group"><div class="chart-bar" style="height:110px;background:var(--g400)"></div><div class="chart-bar" style="height:80px;background:var(--info);margin-top:-110px;opacity:.6"></div><div class="chart-label">May 20</div></div>
              <div class="chart-bar-group"><div class="chart-bar" style="height:160px;background:var(--g400)"></div><div class="chart-bar" style="height:120px;background:var(--info);margin-top:-160px;opacity:.6"></div><div class="chart-label">May 21</div></div>
              <div class="chart-bar-group"><div class="chart-bar" style="height:180px;background:var(--g400)"></div><div class="chart-bar" style="height:140px;background:var(--info);margin-top:-180px;opacity:.6"></div><div class="chart-label">May 22</div></div>
              <div class="chart-bar-group"><div class="chart-bar" style="height:140px;background:var(--g400)"></div><div class="chart-bar" style="height:160px;background:var(--info);margin-top:-140px;opacity:.6"></div><div class="chart-label">May 23</div></div>
              <div class="chart-bar-group"><div class="chart-bar" style="height:100px;background:var(--g400)"></div><div class="chart-bar" style="height:130px;background:var(--info);margin-top:-100px;opacity:.6"></div><div class="chart-label">May 24</div></div>
            </div>
            <div class="chart-legend"><div class="chart-legend-item"><div class="chart-legend-dot" style="background:var(--g400)"></div>Gross Sales (₦)</div><div class="chart-legend-item"><div class="chart-legend-dot" style="background:var(--info)"></div>Orders</div></div>
          </div>
        </div>

        <div class="card">
          <div class="card-header"><div class="card-title">Marketplace / Storefront</div></div>
          <div class="card-body">
            <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid var(--border)"><div><div style="font-size:12px;color:var(--text2)">Public Store Status</div></div><span class="status-badge status-live">● Live</span></div>
            <div style="padding:12px 0;border-bottom:1px solid var(--border)"><div style="font-size:12px;color:var(--text2);margin-bottom:6px">Featured Sellers</div><div style="display:flex;align-items:center;gap:6px"><div class="avatar-sm">FF</div><div class="avatar-sm">AF</div><div class="avatar-sm">IH</div><div class="avatar-sm" style="background:var(--g100);font-size:10px">+3</div></div></div>
            <div style="padding:12px 0;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;cursor:pointer"><div><div style="font-size:12px;color:var(--text2);margin-bottom:2px">Pending Reviews</div><div style="font-weight:700;font-size:18px">12</div></div><span>›</span></div>
            <div style="padding:12px 0"><div style="font-size:12px;color:var(--text2);margin-bottom:4px">Buyer Traffic (7D)</div><div style="display:flex;align-items:end;gap:14px"><div><div style="font-weight:700;font-size:20px">2,845</div><div style="font-size:11px;color:var(--success)">↑ 14.7%</div></div><div style="display:flex;gap:3px;align-items:end;height:40px"><div style="width:6px;height:15px;background:var(--g400);border-radius:2px"></div><div style="width:6px;height:22px;background:var(--g400);border-radius:2px"></div><div style="width:6px;height:18px;background:var(--g400);border-radius:2px"></div><div style="width:6px;height:28px;background:var(--g400);border-radius:2px"></div><div style="width:6px;height:35px;background:var(--g400);border-radius:2px"></div><div style="width:6px;height:30px;background:var(--g400);border-radius:2px"></div><div style="width:6px;height:40px;background:var(--g400);border-radius:2px"></div></div></div></div>
            <button class="btn btn-secondary" style="width:100%;margin-top:8px" onclick="navigateTo('storefronts')"> View Storefront Analytics</button>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><div class="card-title">Latest Orders</div><button class="btn btn-secondary btn-sm" onclick="navigateTo('orders')">View All Orders</button></div>
        <div class="card-body p0">
          <table>
            <thead><tr><th>Order ID</th><th>Customer</th><th>Seller</th><th>Amount</th><th>Status</th><th>Time</th></tr></thead>
            <tbody>
              <tr><td><strong>ORD-260524-00182</strong></td><td>John Okafor</td><td>Green Farms Ltd</td><td>₦243,500.00</td><td><span class="status-badge status-delivered">Delivered</span></td><td>10:24 AM</td></tr>
              <tr><td><strong>ORD-260524-00181</strong></td><td>Mary Abiodun</td><td>Palmbest Agro</td><td>₦87,000.00</td><td><span class="status-badge status-shipped">Shipped</span></td><td>09:48 AM</td></tr>
              <tr><td><strong>ORD-260524-00180</strong></td><td>Tunde Adewale</td><td>Coconut Hub</td><td>₦156,750.00</td><td><span class="status-badge status-processing">Processing</span></td><td>09:15 AM</td></tr>
              <tr><td><strong>ORD-260524-00179</strong></td><td>Ifeoma Nwosu</td><td>AgriPlus Stores</td><td>₦75,000.00</td><td><span class="status-badge status-pending">Pending</span></td><td>08:50 AM</td></tr>
              <tr><td><strong>ORD-260524-00178</strong></td><td>Chinedu Uzor</td><td>Green Farms Ltd</td><td>₦320,000.00</td><td><span class="status-badge status-delivered">Delivered</span></td><td>08:30 AM</td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="grid-4">
        <div class="card">
          <div class="card-header"><div class="card-title">Product Listing Health</div><button class="btn btn-secondary btn-sm" onclick="navigateTo('products')">View All</button></div>
          <div class="card-body p0">
            <table>
              <thead><tr><th>Status</th><th>Products</th><th>%</th><th>Change</th></tr></thead>
              <tbody>
                <tr><td><span class="status-dot" style="background:var(--success)"></span> Active</td><td>4,102</td><td>86.3%</td><td style="color:var(--success)">↑ 6.2%</td></tr>
                <tr><td><span class="status-dot" style="background:var(--danger)"></span> Out of Stock</td><td>328</td><td>6.9%</td><td style="color:var(--danger)">↓ 2.1%</td></tr>
                <tr><td><span class="status-dot" style="background:var(--warn)"></span> Low Stock</td><td>214</td><td>4.5%</td><td style="color:var(--success)">↑ 0.8%</td></tr>
                <tr><td><span class="status-dot" style="background:#9ca3af"></span> Inactive</td><td>112</td><td>2.3%</td><td style="color:var(--danger)">↓ 1.0%</td></tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="card">
          <div class="card-header"><div class="card-title">Low Stock Alerts</div><button class="btn btn-secondary btn-sm" onclick="navigateTo('inventory')">View All</button></div>
          <div class="card-body p0">
            <table>
              <thead><tr><th>Product</th><th>SKU</th><th>Stock</th></tr></thead>
              <tbody>
                <tr><td><div class="avatar-row"><div class="product-thumb"></div>Coco Peat (5kg)</div></td><td>CPF-5KG</td><td style="color:var(--danger);font-weight:700">8</td></tr>
                <tr><td><div class="avatar-row"><div class="product-thumb">🌱</div>Organic Fertilizer</div></td><td>ORG-50KG</td><td style="color:var(--danger);font-weight:700">6</td></tr>
                <tr><td><div class="avatar-row"><div class="product-thumb">🫙</div>Neem Oil (1L)</div></td><td>NEEM-1L</td><td style="color:var(--danger);font-weight:700">4</td></tr>
                <tr><td><div class="avatar-row"><div class="product-thumb">🌴</div>Coconut Seedlings</div></td><td>SEED-001</td><td style="color:var(--danger);font-weight:700">7</td></tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="card">
          <div class="card-header"><div class="card-title">Seller Verification Queue</div><button class="btn btn-secondary btn-sm" onclick="navigateTo('verify-seller')">View All</button></div>
          <div class="card-body p0">
            <table>
              <thead><tr><th>Seller</th><th>Submitted</th><th>Action</th></tr></thead>
              <tbody>
                <tr><td><strong>FreshField Farms</strong></td><td>May 24</td><td><button class="btn btn-sm btn-warn" data-action-note="Review opened">Review</button></td></tr>
                <tr><td><strong>AgroFuture Nigeria</strong></td><td>May 24</td><td><button class="btn btn-sm btn-warn" data-action-note="Review opened">Review</button></td></tr>
                <tr><td><strong>Island Harvest</strong></td><td>May 23</td><td><button class="btn btn-sm btn-warn" data-action-note="Review opened">Review</button></td></tr>
                <tr><td><strong>CocoNation Supplies</strong></td><td>May 23</td><td><button class="btn btn-sm btn-warn" data-action-note="Review opened">Review</button></td></tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="card">
          <div class="card-header"><div class="card-title">Top Selling Products (7D)</div><button class="btn btn-secondary btn-sm" onclick="navigateTo('sales-report')">View Report</button></div>
          <div class="card-body p0">
            <table>
              <thead><tr><th>Product</th><th>Sold</th><th>Revenue</th></tr></thead>
              <tbody>
                <tr><td><div class="avatar-row"><div class="product-thumb">🥥</div>Coco Peat (5kg)</div></td><td>256</td><td>₦614,400</td></tr>
                <tr><td><div class="avatar-row"><div class="product-thumb">🌱</div>Organic Fertilizer</div></td><td>189</td><td>₦945,000</td></tr>
                <tr><td><div class="avatar-row"><div class="product-thumb">🌴</div>Coconut Seedlings</div></td><td>142</td><td>₦710,000</td></tr>
                <tr><td><div class="avatar-row"><div class="product-thumb">🫙</div>Neem Oil (1L)</div></td><td>118</td><td>₦165,200</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="grid-2">
        <div class="card">
          <div class="card-header"><div class="card-title">Quick Actions</div></div>
          <div class="card-body">
            <div class="grid-2">
              <div class="quick-action" onclick="navigateTo('add-product')"><div class="quick-action-icon" style="background:var(--g100);color:var(--g700)">➕</div><div class="quick-action-title">Add Product</div><div class="quick-action-desc">List a new product</div></div>
              <div class="quick-action" onclick="navigateTo('verify-seller')"><div class="quick-action-icon" style="background:#dbeafe;color:#1e40af">✓</div><div class="quick-action-title">Verify Seller</div><div class="quick-action-desc">Review seller application</div></div>
              <div class="quick-action" onclick="navigateTo('create-promotion')"><div class="quick-action-icon" style="background:#fef3c7;color:#92400e">🏷</div><div class="quick-action-title">Create Promotion</div><div class="quick-action-desc">Run a new campaign</div></div>
              <div class="quick-action" onclick="navigateTo('export-report')"><div class="quick-action-icon" style="background:#f3e8ff;color:#6b21a8">⬇</div><div class="quick-action-title">Export Report</div><div class="quick-action-desc">Download marketplace report</div></div>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-header"><div class="card-title">Dispute Summary</div><button class="btn btn-secondary btn-sm" onclick="navigateTo('disputes')">View All Disputes</button></div>
          <div class="card-body">
            <div class="grid-4" style="gap:12px">
              <div style="text-align:center;padding:14px;background:var(--bg);border-radius:10px"><div style="font-size:11px;color:var(--text2);margin-bottom:4px">Open Disputes</div><div style="font-size:24px;font-weight:700;color:var(--danger)">3</div></div>
              <div style="text-align:center;padding:14px;background:var(--bg);border-radius:10px"><div style="font-size:11px;color:var(--text2);margin-bottom:4px">In Review</div><div style="font-size:24px;font-weight:700;color:var(--warn)">2</div></div>
              <div style="text-align:center;padding:14px;background:var(--bg);border-radius:10px"><div style="font-size:11px;color:var(--text2);margin-bottom:4px">Resolved (7D)</div><div style="font-size:24px;font-weight:700;color:var(--success)">7</div></div>
              <div style="text-align:center;padding:14px;background:var(--bg);border-radius:10px"><div style="font-size:11px;color:var(--text2);margin-bottom:4px">Resolution Rate</div><div style="font-size:24px;font-weight:700;color:var(--info)">87.5%</div></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- SELLERS -->
    <div class="page" id="page-sellers">
      <div class="page-header"><div><div class="page-title">Sellers</div><div class="page-subtitle">1,248 registered sellers on the marketplace</div></div><button class="btn btn-primary" onclick="openModal('sellerModal')">+ Add Seller</button></div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">Total Sellers</div><div class="stat-card-value">1,248</div><div class="stat-card-change up">↑ 12.4% this month</div></div>
        <div class="stat-card"><div class="stat-card-label">Verified</div><div class="stat-card-value">1,089</div></div>
        <div class="stat-card"><div class="stat-card-label">Pending Verification</div><div class="stat-card-value">127</div></div>
        <div class="stat-card"><div class="stat-card-label">Suspended</div><div class="stat-card-value">32</div></div>
      </div>
      <div class="card">
        <div class="card-header"><div class="card-title">Seller Directory</div><div class="filter-bar" style="margin:0"><input type="text" placeholder="Search sellers..." oninput="filterTable('sellersTable',this.value)"><select><option>All Status</option><option>Verified</option><option>Pending</option><option>Suspended</option></select></div></div>
        <div class="card-body p0">
          <table id="sellersTable">
            <thead><tr><th>Seller</th><th>Business</th><th>Products</th><th>Sales (7D)</th><th>Rating</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <tr><td><div class="avatar-row"><div class="avatar-sm">GF</div><div><strong>Green Farms Ltd</strong><br><small style="color:var(--text2)">greenfarms@email.com</small></div></div></td><td>Green Farms Ltd</td><td>142</td><td>₦892,400</td><td>⭐ 4.9</td><td><span class="status-badge status-approved">Verified</span></td><td><button class="btn-icon">⋮</button></td></tr>
              <tr><td><div class="avatar-row"><div class="avatar-sm">PA</div><div><strong>Palmbest Agro</strong><br><small style="color:var(--text2)">palmbest@email.com</small></div></div></td><td>Palmbest Agro Ltd</td><td>98</td><td>₦456,200</td><td>⭐ 4.7</td><td><span class="status-badge status-approved">Verified</span></td><td><button class="btn-icon">⋮</button></td></tr>
              <tr><td><div class="avatar-row"><div class="avatar-sm">CH</div><div><strong>Coconut Hub</strong><br><small style="color:var(--text2)">coconuthub@email.com</small></div></div></td><td>Coconut Hub Nigeria</td><td>76</td><td>₦312,800</td><td>⭐ 4.8</td><td><span class="status-badge status-approved">Verified</span></td><td><button class="btn-icon"></button></td></tr>
              <tr><td><div class="avatar-row"><div class="avatar-sm">FF</div><div><strong>FreshField Farms</strong><br><small style="color:var(--text2)">freshfield@email.com</small></div></div></td><td>FreshField Farms Ltd</td><td>54</td><td>₦198,500</td><td>—</td><td><span class="status-badge status-review">Pending</span></td><td><button class="btn btn-sm btn-warn" data-action-note="Review opened">Review</button></td></tr>
              <tr><td><div class="avatar-row"><div class="avatar-sm">AF</div><div><strong>AgroFuture Nigeria</strong><br><small style="color:var(--text2)">agrofuture@email.com</small></div></div></td><td>AgroFuture Ltd</td><td>41</td><td>₦156,300</td><td>—</td><td><span class="status-badge status-review">Pending</span></td><td><button class="btn btn-sm btn-warn" data-action-note="Review opened">Review</button></td></tr>
              <tr><td><div class="avatar-row"><div class="avatar-sm">IH</div><div><strong>Island Harvest</strong><br><small style="color:var(--text2)">islandharvest@email.com</small></div></div></td><td>Island Harvest Co.</td><td>38</td><td>₦142,100</td><td>⭐ 4.5</td><td><span class="status-badge status-approved">Verified</span></td><td><button class="btn-icon">⋮</button></td></tr>
              <tr><td><div class="avatar-row"><div class="avatar-sm">CN</div><div><strong>CocoNation Supplies</strong><br><small style="color:var(--text2)">coconation@email.com</small></div></div></td><td>CocoNation Ltd</td><td>29</td><td>₦98,700</td><td>⭐ 4.3</td><td><span class="status-badge status-cancelled">Suspended</span></td><td><button class="btn-icon">⋮</button></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- PRODUCTS -->
    <div class="page" id="page-products">
      <div class="page-header"><div><div class="page-title">Products</div><div class="page-subtitle">4,756 products listed across the marketplace</div></div><button class="btn btn-primary" onclick="navigateTo('add-product')">+ Add Product</button></div>
      <div class="tabs"><div class="tab active">All Products</div><div class="tab">Active (4,102)</div><div class="tab">Low Stock (214)</div><div class="tab">Out of Stock (328)</div><div class="tab">Inactive (112)</div></div>
      <div class="filter-bar"><input type="text" placeholder="Search products, SKUs..." oninput="filterTable('productsTable',this.value)"><select><option>All Categories</option><option>Coconut Products</option><option>Fertilizers</option><option>Seedlings</option><option>Tools & Equipment</option></select><select><option>All Sellers</option><option>Green Farms</option><option>Palmbest Agro</option></select><button class="btn btn-secondary btn-sm">Apply</button></div>
      <div class="card">
        <div class="card-body p0">
          <table id="productsTable">
            <thead><tr><th>Product</th><th>SKU</th><th>Seller</th><th>Price</th><th>Stock</th><th>Sold</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <tr><td><div class="avatar-row"><div class="product-thumb">🥥</div><div><strong>Coco Peat (5kg)</strong><br><small style="color:var(--text2)">Coconut Products</small></div></div></td><td>CPF-5KG</td><td>Green Farms Ltd</td><td>2,400</td><td style="color:var(--danger);font-weight:700">8</td><td>256</td><td><span class="status-badge status-lowstock">Low Stock</span></td><td><button class="btn-icon">✏️</button></td></tr>
              <tr><td><div class="avatar-row"><div class="product-thumb">🌱</div><div><strong>Organic Fertilizer (50kg)</strong><br><small style="color:var(--text2)">Fertilizers</small></div></div></td><td>ORG-50KG</td><td>Palmbest Agro</td><td>₦5,000</td><td style="color:var(--danger);font-weight:700">6</td><td>189</td><td><span class="status-badge status-lowstock">Low Stock</span></td><td><button class="btn-icon">✏️</button></td></tr>
              <tr><td><div class="avatar-row"><div class="product-thumb">🌴</div><div><strong>Coconut Seedlings (Hybrid)</strong><br><small style="color:var(--text2)">Seedlings</small></div></div></td><td>SEED-001</td><td>Coconut Hub</td><td>₦5,000</td><td style="color:var(--danger);font-weight:700">7</td><td>142</td><td><span class="status-badge status-lowstock">Low Stock</span></td><td><button class="btn-icon">✏️</button></td></tr>
              <tr><td><div class="avatar-row"><div class="product-thumb"></div><div><strong>Neem Oil (1L)</strong><br><small style="color:var(--text2)">Organic Products</small></div></div></td><td>NEEM-1L</td><td>AgroFuture Nigeria</td><td>₦1,400</td><td style="color:var(--danger);font-weight:700">4</td><td>118</td><td><span class="status-badge status-lowstock">Low Stock</span></td><td><button class="btn-icon">✏️</button></td></tr>
              <tr><td><div class="avatar-row"><div class="product-thumb">🌿</div><div><strong>NPK 15-15-15 (50kg)</strong><br><small style="color:var(--text2)">Fertilizers</small></div></div></td><td>NPK-1515</td><td>Green Farms Ltd</td><td>₦4,500</td><td>96</td><td>96</td><td><span class="status-badge status-active">Active</span></td><td><button class="btn-icon">✏️</button></td></tr>
              <tr><td><div class="avatar-row"><div class="product-thumb">💧</div><div><strong>Drip Irrigation Kit</strong><br><small style="color:var(--text2)">Tools & Equipment</small></div></div></td><td>DRIP-KIT</td><td>Island Harvest</td><td>₦18,500</td><td style="color:var(--danger);font-weight:700">3</td><td>45</td><td><span class="status-badge status-lowstock">Low Stock</span></td><td><button class="btn-icon">✏️</button></td></tr>
              <tr><td><div class="avatar-row"><div class="product-thumb">🥥</div><div><strong>Coconut Oil (500ml)</strong><br><small style="color:var(--text2)">Coconut Products</small></div></div></td><td>CO-500</td><td>CocoNation Supplies</td><td>₦3,200</td><td>0</td><td>234</td><td><span class="status-badge status-outofstock">Out of Stock</span></td><td><button class="btn-icon">✏️</button></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ORDERS -->
    <div class="page" id="page-orders">
      <div class="page-header"><div><div class="page-title">All Orders</div><div class="page-subtitle">Manage all marketplace orders</div></div><button class="btn btn-primary" data-action-note="Report exported">📥 Export</button></div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">Today's Orders</div><div class="stat-card-value">182</div><div class="stat-card-change up">↑ 23.6%</div></div>
        <div class="stat-card"><div class="stat-card-label">Processing</div><div class="stat-card-value">47</div></div>
        <div class="stat-card"><div class="stat-card-label">Shipped</div><div class="stat-card-value">28</div></div>
        <div class="stat-card"><div class="stat-card-label">Delivered (7D)</div><div class="stat-card-value">1,247</div></div>
      </div>
      <div class="tabs"><div class="tab active">All Orders</div><div class="tab">Pending</div><div class="tab">Processing</div><div class="tab">Shipped</div><div class="tab">Delivered</div><div class="tab">Cancelled</div></div>
      <div class="filter-bar"><input type="text" placeholder="Search by Order ID, customer..." oninput="filterTable('ordersTable',this.value)"><input type="date" value="2026-05-24"><select><option>All Sellers</option><option>Green Farms</option><option>Palmbest Agro</option></select><button class="btn btn-secondary btn-sm">Filter</button></div>
      <div class="card">
        <div class="card-body p0">
          <table id="ordersTable">
            <thead><tr><th>Order ID</th><th>Customer</th><th>Seller</th><th>Items</th><th>Amount</th><th>Date</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <tr><td><strong>ORD-260524-00182</strong></td><td>John Okafor</td><td>Green Farms Ltd</td><td>3</td><td>₦243,500</td><td>May 24, 10:24</td><td><span class="status-badge status-delivered">Delivered</span></td><td><button class="btn-icon">👁</button></td></tr>
              <tr><td><strong>ORD-260524-00181</strong></td><td>Mary Abiodun</td><td>Palmbest Agro</td><td>2</td><td>₦87,000</td><td>May 24, 09:48</td><td><span class="status-badge status-shipped">Shipped</span></td><td><button class="btn-icon">👁</button></td></tr>
              <tr><td><strong>ORD-260524-00180</strong></td><td>Tunde Adewale</td><td>Coconut Hub</td><td>5</td><td>₦156,750</td><td>May 24, 09:15</td><td><span class="status-badge status-processing">Processing</span></td><td><button class="btn btn-sm btn-primary" data-action-note="Order marked as shipped">Mark Shipped</button></td></tr>
              <tr><td><strong>ORD-260524-00179</strong></td><td>Ifeoma Nwosu</td><td>AgriPlus Stores</td><td>1</td><td>₦75,000</td><td>May 24, 08:50</td><td><span class="status-badge status-pending">Pending</span></td><td><button class="btn btn-sm btn-warn" data-action-note="Order processing started">Process</button></td></tr>
              <tr><td><strong>ORD-260524-00178</strong></td><td>Chinedu Uzor</td><td>Green Farms Ltd</td><td>4</td><td>₦320,000</td><td>May 24, 08:30</td><td><span class="status-badge status-delivered">Delivered</span></td><td><button class="btn-icon">👁</button></td></tr>
              <tr><td><strong>ORD-260523-00177</strong></td><td>Amina Yusuf</td><td>Island Harvest</td><td>2</td><td>₦112,400</td><td>May 23, 16:20</td><td><span class="status-badge status-delivered">Delivered</span></td><td><button class="btn-icon">👁</button></td></tr>
              <tr><td><strong>ORD-260523-00176</strong></td><td>Emeka Obi</td><td>FreshField Farms</td><td>1</td><td>45,000</td><td>May 23, 14:15</td><td><span class="status-badge status-cancelled">Cancelled</span></td><td><button class="btn-icon">👁</button></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ORDER PROCESSING -->
    <div class="page" id="page-order-processing">
      <div class="page-header"><div><div class="page-title">Order Processing</div><div class="page-subtitle">47 orders awaiting processing</div></div></div>
      <div class="card">
        <div class="card-header"><div class="card-title">Processing Queue</div></div>
        <div class="card-body p0">
          <table>
            <thead><tr><th>Order ID</th><th>Customer</th><th>Seller</th><th>Amount</th><th>Waiting Since</th><th>Action</th></tr></thead>
            <tbody>
              <tr><td><strong>ORD-260524-00180</strong></td><td>Tunde Adewale</td><td>Coconut Hub</td><td>₦156,750</td><td>2 hours</td><td><button class="btn btn-sm btn-primary" data-action-note="Processing started">Start Processing</button></td></tr>
              <tr><td><strong>ORD-260524-00175</strong></td><td>Blessing Eze</td><td>Green Farms Ltd</td><td>₦89,200</td><td>3 hours</td><td><button class="btn btn-sm btn-primary" data-action-note="Processing started">Start Processing</button></td></tr>
              <tr><td><strong>ORD-260524-00173</strong></td><td>Samuel Ojo</td><td>Palmbest Agro</td><td>₦234,500</td><td>5 hours</td><td><button class="btn btn-sm btn-primary" data-action-note="Processing started">Start Processing</button></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ORDER FULFILLMENT -->
    <div class="page" id="page-order-fulfillment">
      <div class="page-header"><div><div class="page-title">Order Fulfillment</div><div class="page-subtitle">Track shipping and delivery</div></div></div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">Ready to Ship</div><div class="stat-card-value">23</div></div>
        <div class="stat-card"><div class="stat-card-label">In Transit</div><div class="stat-card-value">28</div></div>
        <div class="stat-card"><div class="stat-card-label">Out for Delivery</div><div class="stat-card-value">12</div></div>
        <div class="stat-card"><div class="stat-card-label">Delivered Today</div><div class="stat-card-value">89</div></div>
      </div>
      <div class="card">
        <div class="card-header"><div class="card-title">Fulfillment Pipeline</div></div>
        <div class="card-body p0">
          <table>
            <thead><tr><th>Order ID</th><th>Customer</th><th>Shipping Address</th><th>Courier</th><th>Tracking</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
              <tr><td><strong>ORD-260524-00181</strong></td><td>Mary Abiodun</td><td>Lagos, Nigeria</td><td>GIG Logistics</td><td>GGL-2847561</td><td><span class="status-badge status-shipped">In Transit</span></td><td><button class="btn btn-sm btn-primary" data-action-note="Marked as delivered">Mark Delivered</button></td></tr>
              <tr><td><strong>ORD-260523-00170</strong></td><td>David Mensah</td><td>Abuja, Nigeria</td><td>DHL Express</td><td>DHL-9928374</td><td><span class="status-badge status-shipped">Out for Delivery</span></td><td><button class="btn btn-sm btn-primary" data-action-note="Marked as delivered">Mark Delivered</button></td></tr>
              <tr><td><strong>ORD-260523-00168</strong></td><td>Fatima Ndiaye</td><td>Port Harcourt</td><td>Red Star Express</td><td>RSE-4451289</td><td><span class="status-badge status-pending">Ready to Ship</span></td><td><button class="btn btn-sm btn-warn" data-action-note="Shipping label generated">Generate Label</button></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- INVENTORY -->
    <div class="page" id="page-inventory">
      <div class="page-header"><div><div class="page-title">Inventory</div><div class="page-subtitle">Stock management across all sellers</div></div><button class="btn btn-primary" onclick="openModal('stockModal')">+ Update Stock</button></div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">Total SKUs</div><div class="stat-card-value">4,756</div></div>
        <div class="stat-card"><div class="stat-card-label">Low Stock Alerts</div><div class="stat-card-value" style="color:var(--warn)">214</div></div>
        <div class="stat-card"><div class="stat-card-label">Out of Stock</div><div class="stat-card-value" style="color:var(--danger)">328</div></div>
        <div class="stat-card"><div class="stat-card-label">Inventory Value</div><div class="stat-card-value">₦18.4M</div></div>
      </div>
      <div class="card">
        <div class="card-header"><div class="card-title">Stock Levels</div><div class="filter-bar" style="margin:0"><input type="text" placeholder="Search SKU or product..." oninput="filterTable('inventoryTable',this.value)"><select><option>All Status</option><option>In Stock</option><option>Low Stock</option><option>Out of Stock</option></select></div></div>
        <div class="card-body p0">
          <table id="inventoryTable">
            <thead><tr><th>Product</th><th>SKU</th><th>Seller</th><th>Current Stock</th><th>Threshold</th><th>Status</th><th>Last Restocked</th><th>Actions</th></tr></thead>
            <tbody>
              <tr><td><div class="avatar-row"><div class="product-thumb">🥥</div>Coco Peat (5kg)</div></td><td>CPF-5KG</td><td>Green Farms Ltd</td><td style="color:var(--danger);font-weight:700">8</td><td>20</td><td><span class="status-badge status-lowstock">Low Stock</span></td><td>May 10, 2026</td><td><button class="btn btn-sm btn-primary" data-action-note="Restock request sent">Restock</button></td></tr>
              <tr><td><div class="avatar-row"><div class="product-thumb">🌱</div>Organic Fertilizer (50kg)</div></td><td>ORG-50KG</td><td>Palmbest Agro</td><td style="color:var(--danger);font-weight:700">6</td><td>15</td><td><span class="status-badge status-lowstock">Low Stock</span></td><td>May 8, 2026</td><td><button class="btn btn-sm btn-primary" data-action-note="Restock request sent">Restock</button></td></tr>
              <tr><td><div class="avatar-row"><div class="product-thumb">🫙</div>Neem Oil (1L)</div></td><td>NEEM-1L</td><td>AgroFuture Nigeria</td><td style="color:var(--danger);font-weight:700">4</td><td>10</td><td><span class="status-badge status-lowstock">Low Stock</span></td><td>May 5, 2026</td><td><button class="btn btn-sm btn-primary" data-action-note="Restock request sent">Restock</button></td></tr>
              <tr><td><div class="avatar-row"><div class="product-thumb">🌴</div>Coconut Seedlings</div></td><td>SEED-001</td><td>Coconut Hub</td><td style="color:var(--danger);font-weight:700">7</td><td>20</td><td><span class="status-badge status-lowstock">Low Stock</span></td><td>May 12, 2026</td><td><button class="btn btn-sm btn-primary" data-action-note="Restock request sent">Restock</button></td></tr>
              <tr><td><div class="avatar-row"><div class="product-thumb">💧</div>Drip Irrigation Kit</div></td><td>DRIP-KIT</td><td>Island Harvest</td><td style="color:var(--danger);font-weight:700">3</td><td>10</td><td><span class="status-badge status-lowstock">Low Stock</span></td><td>Apr 28, 2026</td><td><button class="btn btn-sm btn-primary" data-action-note="Restock request sent">Restock</button></td></tr>
              <tr><td><div class="avatar-row"><div class="product-thumb">🥥</div>Coconut Oil (500ml)</div></td><td>CO-500</td><td>CocoNation Supplies</td><td style="color:var(--danger);font-weight:700">0</td><td>15</td><td><span class="status-badge status-outofstock">Out of Stock</span></td><td>Apr 15, 2026</td><td><button class="btn btn-sm btn-primary" data-action-note="Restock request sent">Restock</button></td></tr>
              <tr><td><div class="avatar-row"><div class="product-thumb"></div>NPK 15-15-15 (50kg)</div></td><td>NPK-1515</td><td>Green Farms Ltd</td><td>96</td><td>20</td><td><span class="status-badge status-active">In Stock</span></td><td>May 20, 2026</td><td><button class="btn-icon">✏️</button></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- STOREFRONTS -->
    <div class="page" id="page-storefronts">
      <div class="page-header"><div><div class="page-title">Storefronts</div><div class="page-subtitle">Manage seller storefronts and public presence</div></div><button class="btn btn-primary" data-action-note="Storefront analytics opened">📊 Analytics</button></div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">Live Storefronts</div><div class="stat-card-value">1,089</div></div>
        <div class="stat-card"><div class="stat-card-label">Featured Sellers</div><div class="stat-card-value">8</div></div>
        <div class="stat-card"><div class="stat-card-label">Buyer Traffic (7D)</div><div class="stat-card-value">2,845</div><div class="stat-card-change up">↑ 14.7%</div></div>
        <div class="stat-card"><div class="stat-card-label">Avg Conversion</div><div class="stat-card-value">3.8%</div></div>
      </div>
      <div class="card">
        <div class="card-header"><div class="card-title">Featured Storefronts</div><button class="btn btn-secondary btn-sm">Manage Featured</button></div>
        <div class="card-body">
          <div class="grid-3">
            <div style="border:1px solid var(--border);border-radius:12px;padding:18px;text-align:center"><div style="width:60px;height:60px;background:var(--g100);border-radius:12px;margin:0 auto 12px;display:flex;align-items:center;justify-content:center;font-size:28px">🌾</div><div style="font-weight:700">Green Farms Ltd</div><div style="font-size:12px;color:var(--text2);margin:4px 0">142 products • ⭐ 4.9</div><div style="font-size:12px;color:var(--success)">● Live</div><button class="btn btn-sm btn-secondary" style="margin-top:10px;width:100%">View Storefront</button></div>
            <div style="border:1px solid var(--border);border-radius:12px;padding:18px;text-align:center"><div style="width:60px;height:60px;background:#fef3c7;border-radius:12px;margin:0 auto 12px;display:flex;align-items:center;justify-content:center;font-size:28px">🌴</div><div style="font-weight:700">Palmbest Agro</div><div style="font-size:12px;color:var(--text2);margin:4px 0">98 products • ⭐ 4.7</div><div style="font-size:12px;color:var(--success)">● Live</div><button class="btn btn-sm btn-secondary" style="margin-top:10px;width:100%">View Storefront</button></div>
            <div style="border:1px solid var(--border);border-radius:12px;padding:18px;text-align:center"><div style="width:60px;height:60px;background:#dbeafe;border-radius:12px;margin:0 auto 12px;display:flex;align-items:center;justify-content:center;font-size:28px">🥥</div><div style="font-weight:700">Coconut Hub</div><div style="font-size:12px;color:var(--text2);margin:4px 0">76 products • ⭐ 4.8</div><div style="font-size:12px;color:var(--success)">● Live</div><button class="btn btn-sm btn-secondary" style="margin-top:10px;width:100%">View Storefront</button></div>
          </div>
        </div>
      </div>
      <div class="card">
        <div class="card-header"><div class="card-title">All Storefronts</div><div class="filter-bar" style="margin:0"><input type="text" placeholder="Search storefronts..."><select><option>All Status</option><option>Live</option><option>Draft</option><option>Suspended</option></select></div></div>
        <div class="card-body p0">
          <table>
            <thead><tr><th>Storefront</th><th>Seller</th><th>Products</th><th>Visits (7D)</th><th>Conversion</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <tr><td><strong>Green Farms Store</strong></td><td>Green Farms Ltd</td><td>142</td><td>1,245</td><td>4.2%</td><td><span class="status-badge status-live">Live</span></td><td><button class="btn-icon">⋮</button></td></tr>
              <tr><td><strong>Palmbest Shop</strong></td><td>Palmbest Agro</td><td>98</td><td>892</td><td>3.8%</td><td><span class="status-badge status-live">Live</span></td><td><button class="btn-icon">⋮</button></td></tr>
              <tr><td><strong>Coconut Hub Market</strong></td><td>Coconut Hub</td><td>76</td><td>654</td><td>3.5%</td><td><span class="status-badge status-live">Live</span></td><td><button class="btn-icon"></button></td></tr>
              <tr><td><strong>AgroFuture Store</strong></td><td>AgroFuture Nigeria</td><td>41</td><td>312</td><td>2.9%</td><td><span class="status-badge status-draft">Draft</span></td><td><button class="btn-icon">⋮</button></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- PAYMENTS -->
    <div class="page" id="page-payments">
      <div class="page-header"><div><div class="page-title">Payments</div><div class="page-subtitle">Manage transactions, payouts, and wallet</div></div><button class="btn btn-primary" onclick="openModal('payoutModal')"> Process Payouts</button></div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">Wallet Balance</div><div class="stat-card-value">₦4,977,388</div></div>
        <div class="stat-card"><div class="stat-card-label">Pending Payouts</div><div class="stat-card-value">₦1,245,789</div></div>
        <div class="stat-card"><div class="stat-card-label">Processed (7D)</div><div class="stat-card-value">₦3,513,240</div><div class="stat-card-change up">↑ 18.9%</div></div>
        <div class="stat-card"><div class="stat-card-label">Commission Earned</div><div class="stat-card-value">₦351,324</div></div>
      </div>
      <div class="tabs"><div class="tab active">All Transactions</div><div class="tab">Payouts</div><div class="tab">Commissions</div><div class="tab">Refunds</div></div>
      <div class="card">
        <div class="card-header"><div class="card-title">Recent Transactions</div><div class="filter-bar" style="margin:0"><input type="text" placeholder="Search transactions..."><select><option>All Types</option><option>Sale</option><option>Payout</option><option>Commission</option><option>Refund</option></select></div></div>
        <div class="card-body p0">
          <table>
            <thead><tr><th>Transaction ID</th><th>Type</th><th>Seller</th><th>Amount</th><th>Fee</th><th>Date</th><th>Status</th></tr></thead>
            <tbody>
              <tr><td><strong>TXN-260524-00482</strong></td><td>Sale</td><td>Green Farms Ltd</td><td style="color:var(--success)">+₦243,500</td><td>₦24,350</td><td>May 24, 10:24</td><td><span class="status-badge status-completed">Completed</span></td></tr>
              <tr><td><strong>TXN-260524-00481</strong></td><td>Payout</td><td>Palmbest Agro</td><td style="color:var(--danger)">-₦87,000</td><td>—</td><td>May 24, 09:48</td><td><span class="status-badge status-completed">Completed</span></td></tr>
              <tr><td><strong>TXN-260524-00480</strong></td><td>Sale</td><td>Coconut Hub</td><td style="color:var(--success)">+₦156,750</td><td>₦15,675</td><td>May 24, 09:15</td><td><span class="status-badge status-pending">Pending</span></td></tr>
              <tr><td><strong>TXN-260524-00479</strong></td><td>Commission</td><td>NATCODEV</td><td style="color:var(--success)">+₦15,675</td><td>—</td><td>May 24, 09:15</td><td><span class="status-badge status-completed">Completed</span></td></tr>
              <tr><td><strong>TXN-260523-00478</strong></td><td>Refund</td><td>AgriPlus Stores</td><td style="color:var(--danger)">-₦75,000</td><td>—</td><td>May 23, 16:20</td><td><span class="status-badge status-completed">Completed</span></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- DISPUTES -->
    <div class="page" id="page-disputes">
      <div class="page-header"><div><div class="page-title">Disputes</div><div class="page-subtitle">3 open disputes requiring attention</div></div><button class="btn btn-primary" onclick="openModal('disputeModal')">+ New Dispute</button></div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">Open Disputes</div><div class="stat-card-value" style="color:var(--danger)">3</div></div>
        <div class="stat-card"><div class="stat-card-label">In Review</div><div class="stat-card-value" style="color:var(--warn)">2</div></div>
        <div class="stat-card"><div class="stat-card-label">Resolved (7D)</div><div class="stat-card-value" style="color:var(--success)">7</div></div>
        <div class="stat-card"><div class="stat-card-label">Resolution Rate</div><div class="stat-card-value" style="color:var(--info)">87.5%</div></div>
      </div>
      <div class="tabs"><div class="tab active">Open (3)</div><div class="tab">In Review (2)</div><div class="tab">Resolved</div><div class="tab">All Disputes</div></div>
      <div class="card">
        <div class="card-body p0">
          <table>
            <thead><tr><th>Dispute ID</th><th>Order</th><th>Buyer</th><th>Seller</th><th>Amount</th><th>Reason</th><th>Opened</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <tr><td><strong>DIS-260524-0012</strong></td><td>ORD-260520-00145</td><td>John Okafor</td><td>Green Farms Ltd</td><td>₦45,000</td><td>Product not as described</td><td>May 24</td><td><span class="status-badge status-open">Open</span></td><td><button class="btn btn-sm btn-primary" data-action-note="Dispute review opened">Review</button></td></tr>
              <tr><td><strong>DIS-260523-0011</strong></td><td>ORD-260518-00132</td><td>Mary Abiodun</td><td>Palmbest Agro</td><td>₦87,000</td><td>Item not received</td><td>May 23</td><td><span class="status-badge status-open">Open</span></td><td><button class="btn btn-sm btn-primary" data-action-note="Dispute review opened">Review</button></td></tr>
              <tr><td><strong>DIS-260522-0010</strong></td><td>ORD-260515-00118</td><td>Tunde Adewale</td><td>Coconut Hub</td><td>₦32,500</td><td>Damaged product</td><td>May 22</td><td><span class="status-badge status-open">Open</span></td><td><button class="btn btn-sm btn-primary" data-action-note="Dispute review opened">Review</button></td></tr>
              <tr><td><strong>DIS-260520-0009</strong></td><td>ORD-260510-00098</td><td>Ifeoma Nwosu</td><td>AgriPlus Stores</td><td>28,000</td><td>Wrong item shipped</td><td>May 20</td><td><span class="status-badge status-review">In Review</span></td><td><button class="btn btn-sm btn-warn" data-action-note="Resolution submitted">Resolve</button></td></tr>
              <tr><td><strong>DIS-260518-0008</strong></td><td>ORD-260508-00085</td><td>Chinedu Uzor</td><td>Island Harvest</td><td>₦56,000</td><td>Late delivery</td><td>May 18</td><td><span class="status-badge status-review">In Review</span></td><td><button class="btn btn-sm btn-warn" data-action-note="Resolution submitted">Resolve</button></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- PROMOTIONS -->
    <div class="page" id="page-promotions">
      <div class="page-header"><div><div class="page-title">Promotions</div><div class="page-subtitle">Manage campaigns, discounts, and featured listings</div></div><button class="btn btn-primary" onclick="navigateTo('create-promotion')">+ Create Promotion</button></div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">Active Campaigns</div><div class="stat-card-value">8</div></div>
        <div class="stat-card"><div class="stat-card-label">Total Discounts Given</div><div class="stat-card-value">₦284,500</div></div>
        <div class="stat-card"><div class="stat-card-label">Promo-driven Sales</div><div class="stat-card-value">₦1.2M</div><div class="stat-card-change up">↑ 34%</div></div>
        <div class="stat-card"><div class="stat-card-label">Redemption Rate</div><div class="stat-card-value">42.3%</div></div>
      </div>
      <div class="tabs"><div class="tab active">Active</div><div class="tab">Scheduled</div><div class="tab">Ended</div><div class="tab">Drafts</div></div>
      <div class="card">
        <div class="card-body p0">
          <table>
            <thead><tr><th>Promotion</th><th>Type</th><th>Discount</th><th>Products</th><th>Start</th><th>End</th><th>Redemptions</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <tr><td><strong>🌴 Coconut Season Sale</strong></td><td>Site-wide</td><td>15% OFF</td><td>All</td><td>May 20</td><td>Jun 5</td><td>284</td><td><span class="status-badge status-active">Active</span></td><td><button class="btn-icon">✏️</button></td></tr>
              <tr><td><strong>🌱 Fertilizer Bundle Deal</strong></td><td>Bundle</td><td>Buy 2 Get 1</td><td>12</td><td>May 15</td><td>May 30</td><td>156</td><td><span class="status-badge status-active">Active</span></td><td><button class="btn-icon">✏️</button></td></tr>
              <tr><td><strong> New Seller Welcome</strong></td><td>Coupon</td><td>₦5,000 OFF</td><td>All</td><td>May 1</td><td>Jun 30</td><td>89</td><td><span class="status-badge status-active">Active</span></td><td><button class="btn-icon">✏️</button></td></tr>
              <tr><td><strong> Irrigation Equipment Sale</strong></td><td>Category</td><td>20% OFF</td><td>24</td><td>Jun 1</td><td>Jun 15</td><td>—</td><td><span class="status-badge status-pending">Scheduled</span></td><td><button class="btn-icon">✏️</button></td></tr>
              <tr><td><strong>🎉 May Madness</strong></td><td>Site-wide</td><td>10% OFF</td><td>All</td><td>May 1</td><td>May 20</td><td>412</td><td><span class="status-badge status-completed">Ended</span></td><td><button class="btn-icon">👁</button></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- REPORTS -->
    <div class="page" id="page-reports">
      <div class="page-header"><div><div class="page-title">Analytics & Reports</div><div class="page-subtitle">Comprehensive marketplace insights</div></div><button class="btn btn-primary" data-action-note="Report generated">📊 Generate Report</button></div>
      <div class="grid-2">
        <div class="card"><div class="card-header"><div class="card-title">Revenue Trends</div></div><div class="chart-container"><div class="chart-bars"><div class="chart-bar-group"><div class="chart-bar" style="height:80px;background:var(--g400)"></div><div class="chart-label">Week 1</div></div><div class="chart-bar-group"><div class="chart-bar" style="height:120px;background:var(--g400)"></div><div class="chart-label">Week 2</div></div><div class="chart-bar-group"><div class="chart-bar" style="height:100px;background:var(--g400)"></div><div class="chart-label">Week 3</div></div><div class="chart-bar-group"><div class="chart-bar" style="height:160px;background:var(--g400)"></div><div class="chart-label">Week 4</div></div></div></div></div>
        <div class="card"><div class="card-header"><div class="card-title">Sales by Category</div></div><div class="card-body"><div style="display:flex;flex-direction:column;gap:12px"><div><div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px"><span>Coconut Products</span><span style="font-weight:600">38%</span></div><div class="progress-bar"><div class="progress-fill" style="width:38%"></div></div></div><div><div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px"><span>Fertilizers</span><span style="font-weight:600">28%</span></div><div class="progress-bar"><div class="progress-fill" style="width:28%;background:var(--info)"></div></div></div><div><div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px"><span>Seedlings</span><span style="font-weight:600">22%</span></div><div class="progress-bar"><div class="progress-fill" style="width:22%;background:var(--warn)"></div></div></div><div><div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px"><span>Tools & Equipment</span><span style="font-weight:600">12%</span></div><div class="progress-bar"><div class="progress-fill" style="width:12%;background:var(--purple)"></div></div></div></div></div></div>
      </div>
      <div class="card">
        <div class="card-header"><div class="card-title">Available Reports</div></div>
        <div class="card-body p0">
          <table>
            <thead><tr><th>Report</th><th>Description</th><th>Last Generated</th><th>Actions</th></tr></thead>
            <tbody>
              <tr><td><strong>Sales Report</strong></td><td>Complete sales breakdown by period</td><td>May 24, 2026</td><td><button class="btn btn-sm btn-primary" data-action-note="Report downloaded"> Download</button></td></tr>
              <tr><td><strong>Seller Performance</strong></td><td>Seller metrics and rankings</td><td>May 24, 2026</td><td><button class="btn btn-sm btn-primary" data-action-note="Report downloaded">⬇ Download</button></td></tr>
              <tr><td><strong>Inventory Report</strong></td><td>Stock levels and alerts</td><td>May 23, 2026</td><td><button class="btn btn-sm btn-primary" data-action-note="Report downloaded">⬇ Download</button></td></tr>
              <tr><td><strong>Financial Summary</strong></td><td>Revenue, payouts, commissions</td><td>May 22, 2026</td><td><button class="btn btn-sm btn-primary" data-action-note="Report downloaded">⬇ Download</button></td></tr>
              <tr><td><strong>Customer Analytics</strong></td><td>Buyer behavior and trends</td><td>May 20, 2026</td><td><button class="btn btn-sm btn-primary" data-action-note="Report downloaded">⬇ Download</button></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- SALES REPORT -->
    <div class="page" id="page-sales-report">
      <div class="page-header"><div><div class="page-title">Sales Report</div><div class="page-subtitle">Detailed sales analytics</div></div><button class="btn btn-primary" data-action-note="Exporting...">📥 Export CSV</button></div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">Total Sales (7D)</div><div class="stat-card-value">₦3,513,240</div><div class="stat-card-change up">↑ 18.9%</div></div>
        <div class="stat-card"><div class="stat-card-label">Avg Order Value</div><div class="stat-card-value">₦19,304</div></div>
        <div class="stat-card"><div class="stat-card-label">Total Orders</div><div class="stat-card-value">1,247</div></div>
        <div class="stat-card"><div class="stat-card-label">Top Product</div><div class="stat-card-value" style="font-size:16px">Coco Peat (5kg)</div></div>
      </div>
      <div class="card"><div class="card-header"><div class="card-title">Top Selling Products (7D)</div></div><div class="card-body p0"><table><thead><tr><th>Product</th><th>Seller</th><th>Units Sold</th><th>Revenue</th><th>Growth</th></tr></thead><tbody><tr><td><div class="avatar-row"><div class="product-thumb"></div>Coco Peat (5kg)</div></td><td>Green Farms Ltd</td><td>256</td><td>₦614,400</td><td style="color:var(--success)">↑ 24%</td></tr><tr><td><div class="avatar-row"><div class="product-thumb">🌱</div>Organic Fertilizer (50kg)</div></td><td>Palmbest Agro</td><td>189</td><td>₦945,000</td><td style="color:var(--success)">↑ 18%</td></tr><tr><td><div class="avatar-row"><div class="product-thumb"></div>Coconut Seedlings (Hybrid)</div></td><td>Coconut Hub</td><td>142</td><td>₦710,000</td><td style="color:var(--success)">↑ 32%</td></tr><tr><td><div class="avatar-row"><div class="product-thumb"></div>Neem Oil (1L)</div></td><td>AgroFuture Nigeria</td><td>118</td><td>₦165,200</td><td style="color:var(--danger)">↓ 5%</td></tr><tr><td><div class="avatar-row"><div class="product-thumb">🌿</div>NPK 15-15-15 (50kg)</div></td><td>Green Farms Ltd</td><td>96</td><td>₦432,000</td><td style="color:var(--success)">↑ 12%</td></tr></tbody></table></div></div>
    </div>

    <!-- SELLER REPORT -->
    <div class="page" id="page-seller-report">
      <div class="page-header"><div><div class="page-title">Seller Performance Report</div><div class="page-subtitle">Rankings and metrics</div></div><button class="btn btn-primary" data-action-note="Exporting...">📥 Export</button></div>
      <div class="card"><div class="card-body p0"><table><thead><tr><th>Rank</th><th>Seller</th><th>Products</th><th>Sales (7D)</th><th>Revenue</th><th>Rating</th><th>Fulfillment</th></tr></thead><tbody><tr><td><strong>🥇 1</strong></td><td><div class="avatar-row"><div class="avatar-sm">GF</div>Green Farms Ltd</div></td><td>142</td><td>892</td><td>892,400</td><td>⭐ 4.9</td><td>98%</td></tr><tr><td><strong>🥈 2</strong></td><td><div class="avatar-row"><div class="avatar-sm">PA</div>Palmbest Agro</div></td><td>98</td><td>654</td><td>₦456,200</td><td>⭐ 4.7</td><td>95%</td></tr><tr><td><strong>🥉 3</strong></td><td><div class="avatar-row"><div class="avatar-sm">CH</div>Coconut Hub</div></td><td>76</td><td>487</td><td>₦312,800</td><td>⭐ 4.8</td><td>96%</td></tr><tr><td><strong>4</strong></td><td><div class="avatar-row"><div class="avatar-sm">IH</div>Island Harvest</div></td><td>38</td><td>312</td><td>₦142,100</td><td>⭐ 4.5</td><td>92%</td></tr><tr><td><strong>5</strong></td><td><div class="avatar-row"><div class="avatar-sm">AF</div>AgroFuture Nigeria</div></td><td>41</td><td>289</td><td>₦156,300</td><td>⭐ 4.6</td><td>94%</td></tr></tbody></table></div></div>
    </div>

    <!-- SETTINGS -->
    <div class="page" id="page-settings">
      <div class="page-header"><div><div class="page-title">Settings</div><div class="page-subtitle">Configure your marketplace</div></div></div>
      <div class="tabs"><div class="tab active">General</div><div class="tab">Branding</div><div class="tab">Payment</div><div class="tab">Shipping</div><div class="tab">Tax</div><div class="tab">API</div></div>
      <div class="card"><div class="card-body"><div class="form-row"><div class="form-group"><label class="form-label">Marketplace Name</label><input class="form-input" value="NATCODEV Marketplace"></div><div class="form-group"><label class="form-label">Support Email</label><input class="form-input" value="support@natcodev.org"></div></div><div class="form-row"><div class="form-group"><label class="form-label">Currency</label><select class="form-select"><option>Nigerian Naira (₦)</option><option>USD ($)</option></select></div><div class="form-group"><label class="form-label">Timezone</label><select class="form-select"><option>Africa/Lagos (WAT)</option></select></div></div><div class="form-group"><label class="form-label">Marketplace Description</label><textarea class="form-textarea">NATCODEV Marketplace - Coconut Development & Propagation Initiative. Connecting farmers, suppliers, and buyers across Nigeria.</textarea></div><div style="display:flex;gap:10px"><button class="btn btn-primary" data-action-note="Settings saved">Save Changes</button><button class="btn btn-secondary">Cancel</button></div></div></div>
    </div>

    <!-- COMMISSION -->
    <div class="page" id="page-commission">
      <div class="page-header"><div><div class="page-title">Commission Settings</div><div class="page-subtitle">Configure seller commission rates</div></div></div>
      <div class="card"><div class="card-body"><div class="form-row"><div class="form-group"><label class="form-label">Default Commission Rate (%)</label><input class="form-input" type="number" value="10"></div><div class="form-group"><label class="form-label">Minimum Payout (₦)</label><input class="form-input" type="number" value="10000"></div></div><div class="form-group"><label class="form-label">Commission by Category</label><table style="margin-top:8px"><thead><tr><th>Category</th><th>Rate (%)</th></tr></thead><tbody><tr><td>Coconut Products</td><td><input class="form-input" value="10" style="width:100px"></td></tr><tr><td>Fertilizers</td><td><input class="form-input" value="8" style="width:100px"></td></tr><tr><td>Seedlings</td><td><input class="form-input" value="12" style="width:100px"></td></tr><tr><td>Tools & Equipment</td><td><input class="form-input" value="15" style="width:100px"></td></tr></tbody></table></div><button class="btn btn-primary" data-action-note="Commission settings saved">Save Settings</button></div></div>
    </div>

    <!-- NOTIFICATIONS SETTINGS -->
    <div class="page" id="page-notifications-settings">
      <div class="page-header"><div><div class="page-title">Notification Settings</div><div class="page-subtitle">Configure email and push notifications</div></div></div>
      <div class="card"><div class="card-body"><div style="display:flex;flex-direction:column;gap:16px"><div style="display:flex;justify-content:space-between;align-items:center;padding:14px;background:var(--bg);border-radius:10px"><div><div style="font-weight:600">New Order Notifications</div><div style="font-size:12px;color:var(--text2)">Get notified when new orders are placed</div></div><label style="position:relative;width:44px;height:24px"><input type="checkbox" checked style="opacity:0;width:0"><span style="position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background:var(--g500);border-radius:24px;transition:.3s"></span></label></div><div style="display:flex;justify-content:space-between;align-items:center;padding:14px;background:var(--bg);border-radius:10px"><div><div style="font-weight:600">Low Stock Alerts</div><div style="font-size:12px;color:var(--text2)">Alert when products reach low stock threshold</div></div><label style="position:relative;width:44px;height:24px"><input type="checkbox" checked style="opacity:0;width:0"><span style="position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background:var(--g500);border-radius:24px"></span></label></div><div style="display:flex;justify-content:space-between;align-items:center;padding:14px;background:var(--bg);border-radius:10px"><div><div style="font-weight:600">Dispute Notifications</div><div style="font-size:12px;color:var(--text2)">Alert on new disputes</div></div><label style="position:relative;width:44px;height:24px"><input type="checkbox" checked style="opacity:0;width:0"><span style="position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background:var(--g500);border-radius:24px"></span></label></div><div style="display:flex;justify-content:space-between;align-items:center;padding:14px;background:var(--bg);border-radius:10px"><div><div style="font-weight:600">Weekly Summary Report</div><div style="font-size:12px;color:var(--text2)">Receive weekly performance summary</div></div><label style="position:relative;width:44px;height:24px"><input type="checkbox" style="opacity:0;width:0"><span style="position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background:#ccc;border-radius:24px"></span></label></div></div><button class="btn btn-primary" style="margin-top:16px" data-action-note="Notification settings saved">Save Settings</button></div></div>
    </div>

    <!-- ADD PRODUCT -->
    <div class="page" id="page-add-product">
      <div class="page-header"><div><div class="page-title">Add New Product</div><div class="page-subtitle">List a new product on the marketplace</div></div></div>
      <div class="card"><div class="card-body"><div class="form-row"><div class="form-group"><label class="form-label">Product Name</label><input class="form-input" placeholder="e.g. Coco Peat (5kg)"></div><div class="form-group"><label class="form-label">SKU</label><input class="form-input" placeholder="e.g. CPF-5KG"></div></div><div class="form-row"><div class="form-group"><label class="form-label">Category</label><select class="form-select"><option>Coconut Products</option><option>Fertilizers</option><option>Seedlings</option><option>Tools & Equipment</option></select></div><div class="form-group"><label class="form-label">Seller</label><select class="form-select"><option>Green Farms Ltd</option><option>Palmbest Agro</option><option>Coconut Hub</option></select></div></div><div class="form-row"><div class="form-group"><label class="form-label">Price (₦)</label><input class="form-input" type="number" placeholder="2400"></div><div class="form-group"><label class="form-label">Stock Quantity</label><input class="form-input" type="number" placeholder="100"></div></div><div class="form-group"><label class="form-label">Description</label><textarea class="form-textarea" placeholder="Product description..."></textarea></div><div class="form-group"><label class="form-label">Product Images</label><div style="border:2px dashed var(--border);border-radius:10px;padding:30px;text-align:center;color:var(--text2);cursor:pointer">📷 Click to upload or drag images here</div></div><div style="display:flex;gap:10px"><button class="btn btn-primary" data-action-note="Product listed successfully">List Product</button><button class="btn btn-secondary">Save as Draft</button></div></div></div>
    </div>

    <!-- VERIFY SELLER -->
    <div class="page" id="page-verify-seller">
      <div class="page-header"><div><div class="page-title">Seller Verification</div><div class="page-subtitle">127 sellers pending verification</div></div></div>
      <div class="card"><div class="card-header"><div class="card-title">Verification Queue</div></div><div class="card-body p0"><table><thead><tr><th>Seller</th><th>Business Name</th><th>Documents</th><th>Submitted</th><th>Action</th></tr></thead><tbody><tr><td><div class="avatar-row"><div class="avatar-sm">FF</div><div><strong>FreshField Farms</strong><br><small style="color:var(--text2)">freshfield@email.com</small></div></div></td><td>FreshField Farms Ltd</td><td><span class="chip">RC Doc</span><span class="chip">Tax ID</span><span class="chip">ID Card</span></td><td>May 24, 2026</td><td><button class="btn btn-sm btn-primary" data-action-note="Seller verified">✓ Approve</button> <button class="btn btn-sm btn-danger">✗ Reject</button></td></tr><tr><td><div class="avatar-row"><div class="avatar-sm">AF</div><div><strong>AgroFuture Nigeria</strong><br><small style="color:var(--text2)">agrofuture@email.com</small></div></div></td><td>AgroFuture Ltd</td><td><span class="chip">RC Doc</span><span class="chip">Tax ID</span></td><td>May 24, 2026</td><td><button class="btn btn-sm btn-primary" data-action-note="Seller verified">✓ Approve</button> <button class="btn btn-sm btn-danger"> Reject</button></td></tr><tr><td><div class="avatar-row"><div class="avatar-sm">IH</div><div><strong>Island Harvest</strong><br><small style="color:var(--text2)">islandharvest@email.com</small></div></div></td><td>Island Harvest Co.</td><td><span class="chip">RC Doc</span><span class="chip">Tax ID</span><span class="chip">ID Card</span><span class="chip">Address</span></td><td>May 23, 2026</td><td><button class="btn btn-sm btn-primary" data-action-note="Seller verified">✓ Approve</button> <button class="btn btn-sm btn-danger">✗ Reject</button></td></tr><tr><td><div class="avatar-row"><div class="avatar-sm">CN</div><div><strong>CocoNation Supplies</strong><br><small style="color:var(--text2)">coconation@email.com</small></div></div></td><td>CocoNation Ltd</td><td><span class="chip">RC Doc</span></td><td>May 23, 2026</td><td><button class="btn btn-sm btn-warn" data-action-note="Request sent for more docs">Request Docs</button></td></tr></tbody></table></div></div>
    </div>

    <!-- CREATE PROMOTION -->
    <div class="page" id="page-create-promotion">
      <div class="page-header"><div><div class="page-title">Create Promotion</div><div class="page-subtitle">Launch a new marketing campaign</div></div></div>
      <div class="card"><div class="card-body"><div class="form-group"><label class="form-label">Promotion Name</label><input class="form-input" placeholder="e.g. Coconut Season Sale"></div><div class="form-row"><div class="form-group"><label class="form-label">Type</label><select class="form-select"><option>Site-wide Discount</option><option>Category Discount</option><option>Bundle Deal</option><option>Coupon Code</option><option>Free Shipping</option></select></div><div class="form-group"><label class="form-label">Discount Value</label><input class="form-input" placeholder="e.g. 15% or 5,000"></div></div><div class="form-row"><div class="form-group"><label class="form-label">Start Date</label><input class="form-input" type="date"></div><div class="form-group"><label class="form-label">End Date</label><input class="form-input" type="date"></div></div><div class="form-group"><label class="form-label">Applicable Products</label><select class="form-select" multiple style="min-height:100px"><option>All Products</option><option>Coconut Products</option><option>Fertilizers</option><option>Seedlings</option><option>Tools & Equipment</option></select></div><div class="form-group"><label class="form-label">Description</label><textarea class="form-textarea" placeholder="Promotion details..."></textarea></div><div style="display:flex;gap:10px"><button class="btn btn-primary" data-action-note="Promotion created">Create Promotion</button><button class="btn btn-secondary">Save as Draft</button></div></div></div>
    </div>

    <!-- EXPORT REPORT -->
    <div class="page" id="page-export-report">
      <div class="page-header"><div><div class="page-title">Export Report</div><div class="page-subtitle">Download marketplace data</div></div></div>
      <div class="grid-3">
        <div class="card" style="cursor:pointer" data-action-note="Sales report downloading..."><div class="card-body" style="text-align:center;padding:30px"><div style="font-size:40px;margin-bottom:12px">📊</div><div style="font-weight:700;font-size:15px">Sales Report</div><div style="font-size:12px;color:var(--text2);margin:6px 0">Complete sales breakdown</div><button class="btn btn-sm btn-primary" style="margin-top:10px">Download CSV</button></div></div>
        <div class="card" style="cursor:pointer" data-action-note="Seller report downloading..."><div class="card-body" style="text-align:center;padding:30px"><div style="font-size:40px;margin-bottom:12px">👥</div><div style="font-weight:700;font-size:15px">Seller Report</div><div style="font-size:12px;color:var(--text2);margin:6px 0">Seller performance metrics</div><button class="btn btn-sm btn-primary" style="margin-top:10px">Download CSV</button></div></div>
        <div class="card" style="cursor:pointer" data-action-note="Inventory report downloading..."><div class="card-body" style="text-align:center;padding:30px"><div style="font-size:40px;margin-bottom:12px">📦</div><div style="font-weight:700;font-size:15px">Inventory Report</div><div style="font-size:12px;color:var(--text2);margin:6px 0">Stock levels and alerts</div><button class="btn btn-sm btn-primary" style="margin-top:10px">Download CSV</button></div></div>
        <div class="card" style="cursor:pointer" data-action-note="Financial report downloading..."><div class="card-body" style="text-align:center;padding:30px"><div style="font-size:40px;margin-bottom:12px">💰</div><div style="font-weight:700;font-size:15px">Financial Summary</div><div style="font-size:12px;color:var(--text2);margin:6px 0">Revenue and payouts</div><button class="btn btn-sm btn-primary" style="margin-top:10px">Download CSV</button></div></div>
        <div class="card" style="cursor:pointer" data-action-note="Customer report downloading..."><div class="card-body" style="text-align:center;padding:30px"><div style="font-size:40px;margin-bottom:12px">🛒</div><div style="font-weight:700;font-size:15px">Customer Analytics</div><div style="font-size:12px;color:var(--text2);margin:6px 0">Buyer behavior data</div><button class="btn btn-sm btn-primary" style="margin-top:10px">Download CSV</button></div></div>
        <div class="card" style="cursor:pointer" data-action-note="Dispute report downloading..."><div class="card-body" style="text-align:center;padding:30px"><div style="font-size:40px;margin-bottom:12px">⚠️</div><div style="font-weight:700;font-size:15px">Dispute Report</div><div style="font-size:12px;color:var(--text2);margin:6px 0">All dispute records</div><button class="btn btn-sm btn-primary" style="margin-top:10px">Download CSV</button></div></div>
      </div>
    </div>

  </div>
</div>

<!-- MODALS -->
<div class="modal-overlay" id="sellerModal"><div class="modal"><div class="modal-header"><div class="modal-title">Add New Seller</div><button class="btn-icon" onclick="closeModal('sellerModal')">✕</button></div><div class="modal-body"><div class="form-row"><div class="form-group"><label class="form-label">Business Name</label><input class="form-input"></div><div class="form-group"><label class="form-label">Contact Person</label><input class="form-input"></div></div><div class="form-group"><label class="form-label">Email</label><input class="form-input" type="email"></div><div class="form-group"><label class="form-label">Phone</label><input class="form-input"></div><div class="form-group"><label class="form-label">Business Category</label><select class="form-select"><option>Coconut Products</option><option>Fertilizers</option><option>Seedlings</option><option>Tools & Equipment</option></select></div><div class="form-group"><label class="form-label">Address</label><textarea class="form-textarea"></textarea></div></div><div class="modal-footer"><button class="btn btn-secondary" onclick="closeModal('sellerModal')">Cancel</button><button class="btn btn-primary" onclick="closeModal('sellerModal')">Add Seller</button></div></div></div>

<div class="modal-overlay" id="stockModal"><div class="modal"><div class="modal-header"><div class="modal-title">Update Stock</div><button class="btn-icon" onclick="closeModal('stockModal')">✕</button></div><div class="modal-body"><div class="form-group"><label class="form-label">Product</label><select class="form-select"><option>Coco Peat (5kg) - CPF-5KG</option><option>Organic Fertilizer (50kg) - ORG-50KG</option><option>Neem Oil (1L) - NEEM-1L</option></select></div><div class="form-row"><div class="form-group"><label class="form-label">New Stock Quantity</label><input class="form-input" type="number"></div><div class="form-group"><label class="form-label">Low Stock Threshold</label><input class="form-input" type="number" value="20"></div></div><div class="form-group"><label class="form-label">Notes</label><textarea class="form-textarea" placeholder="Restock notes..."></textarea></div></div><div class="modal-footer"><button class="btn btn-secondary" onclick="closeModal('stockModal')">Cancel</button><button class="btn btn-primary" onclick="closeModal('stockModal')">Update Stock</button></div></div></div>

<div class="modal-overlay" id="payoutModal"><div class="modal"><div class="modal-header"><div class="modal-title">Process Payouts</div><button class="btn-icon" onclick="closeModal('payoutModal')">✕</button></div><div class="modal-body"><div style="padding:14px;background:var(--g50);border-radius:10px;margin-bottom:16px"><div style="font-size:12px;color:var(--text2)">Total Pending Payouts</div><div style="font-size:24px;font-weight:700;color:var(--g700)">₦1,245,789.00</div><div style="font-size:12px;color:var(--text2);margin-top:4px">18 sellers awaiting payout</div></div><div class="form-group"><label class="form-label">Payout Method</label><select class="form-select"><option>Bank Transfer</option><option>Mobile Money</option></select></div><div class="form-group"><label class="form-label">Notes</label><textarea class="form-textarea" placeholder="Payout notes..."></textarea></div></div><div class="modal-footer"><button class="btn btn-secondary" onclick="closeModal('payoutModal')">Cancel</button><button class="btn btn-primary" onclick="closeModal('payoutModal')">Process All Payouts</button></div></div></div>

<div class="modal-overlay" id="disputeModal"><div class="modal"><div class="modal-header"><div class="modal-title">New Dispute</div><button class="btn-icon" onclick="closeModal('disputeModal')">✕</button></div><div class="modal-body"><div class="form-group"><label class="form-label">Order ID</label><input class="form-input" placeholder="ORD-..."></div><div class="form-row"><div class="form-group"><label class="form-label">Buyer</label><input class="form-input"></div><div class="form-group"><label class="form-label">Seller</label><input class="form-input"></div></div><div class="form-group"><label class="form-label">Dispute Reason</label><select class="form-select"><option>Product not as described</option><option>Item not received</option><option>Damaged product</option><option>Wrong item shipped</option><option>Late delivery</option></select></div><div class="form-group"><label class="form-label">Amount (₦)</label><input class="form-input" type="number"></div><div class="form-group"><label class="form-label">Description</label><textarea class="form-textarea"></textarea></div></div><div class="modal-footer"><button class="btn btn-secondary" onclick="closeModal('disputeModal')">Cancel</button><button class="btn btn-primary" onclick="closeModal('disputeModal')">Create Dispute</button></div></div></div>

<div class="toast" id="toast"></div>

<script>
const MARKETPLACE_DATA = <?= json_encode($mxData, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
function navigateTo(page){
  document.querySelectorAll('.page').forEach(p=>p.classList.remove('active'));
  document.querySelectorAll('.nav-item').forEach(n=>n.classList.remove('active'));
  const el=document.getElementById('page-'+page);
  if(el)el.classList.add('active');
  const nav=document.querySelector(`.nav-item[data-page="${page}"]`);
  if(nav)nav.classList.add('active');
  window.scrollTo(0,0);
}
document.querySelectorAll('.nav-item[data-page]').forEach(item=>{
  item.addEventListener('click',()=>{const p=item.getAttribute('data-page');if(p)navigateTo(p)});
});
function openModal(id){document.getElementById(id).classList.add('active')}
function closeModal(id){document.getElementById(id).classList.remove('active')}
document.querySelectorAll('.modal-overlay').forEach(o=>{o.addEventListener('click',e=>{if(e.target===o)o.classList.remove('active')})});
function showToast(msg){const t=document.getElementById('toast');t.textContent=msg;t.style.display='block';setTimeout(()=>t.style.display='none',2500)}
function filterTable(tableId,q){
  const t=document.getElementById(tableId);if(!t)return;
  const rows=t.querySelectorAll('tbody tr');const s=q.toLowerCase();
  rows.forEach(r=>{r.style.display=r.textContent.toLowerCase().includes(s)?'':'none'});
}
document.querySelectorAll('.tab').forEach(tab=>{tab.addEventListener('click',function(){this.parentElement.querySelectorAll('.tab').forEach(t=>t.classList.remove('active'));this.classList.add('active')})});
document.querySelectorAll('[data-topbar-menu]').forEach(button => {
  button.addEventListener('click', event => {
    event.stopPropagation();
    const menu = document.getElementById(button.dataset.topbarMenu);
    document.querySelectorAll('.topbar-menu.active').forEach(open => { if (open !== menu) open.classList.remove('active'); });
    menu?.classList.toggle('active');
    button.setAttribute('aria-expanded', menu?.classList.contains('active') ? 'true' : 'false');
  });
});
document.addEventListener('click', event => {
  if (!event.target.closest('.topbar-menu-wrap')) {
    document.querySelectorAll('.topbar-menu.active').forEach(menu => menu.classList.remove('active'));
    document.querySelectorAll('[aria-expanded="true"]').forEach(button => button.setAttribute('aria-expanded', 'false'));
  }
});
document.addEventListener('keydown',e=>{if((e.ctrlKey||e.metaKey)&&e.key==='k'){e.preventDefault();document.getElementById('globalSearch').focus()}});
function esc(v){return String(v ?? '').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]))}
function money(v){return 'NGN '+Number(v||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})}
function statusClass(v){const s=String(v||'pending').toLowerCase();if(['approved','active','completed','paid','success'].includes(s))return 'status-approved';if(['rejected','suspended','cancelled','failed','disputed'].includes(s))return 'status-rejected';if(['ready','in_transit','quoted','accepted'].includes(s))return 'status-completed';return 'status-pending'}
function statusBadge(v){return `<span class="status-badge ${statusClass(v)}">${esc(String(v||'pending').replaceAll('_',' '))}</span>`}
function firstTable(page){return document.querySelector(`#page-${page} table tbody`)}
function setRows(page, rows, emptyText='No records found.'){const body=firstTable(page);if(!body)return;body.innerHTML=rows.length?rows.join(''):`<tr><td colspan="8">${esc(emptyText)}</td></tr>`}
function hydrateMarketplaceTables(){
  const d=MARKETPLACE_DATA||{};
  setRows('sellers',(d.sellers||[]).map(s=>`<tr><td><div class="avatar-row"><div class="avatar-sm">${esc((s.store_name||'NA').slice(0,2).toUpperCase())}</div><div><strong>${esc(s.store_name)}</strong><br><small>${esc(s.owner_email||'')}</small></div></div></td><td>${esc(s.seller_type||'seller')}</td><td>${Number(s.listings||0)}</td><td>${money(s.sales_7d)}</td><td>-</td><td>${statusBadge(s.approval_status)}</td><td>${statusBadge(s.verification_status)}</td></tr>`),'No marketplace sellers yet.');
  setRows('products',(d.products||[]).map(p=>`<tr><td><div class="avatar-row"><div class="product-thumb">NC</div><div><strong>${esc(p.title)}</strong><br><small>${esc(p.category_name||p.listing_type||'Marketplace')}</small></div></div></td><td>MP-${Number(p.id||0).toString().padStart(5,'0')}</td><td>${esc(p.store_name)}</td><td>${money(p.price)}</td><td>${esc(p.quantity_available??'Ask')} ${esc(p.unit||'')}</td><td>-</td><td>${statusBadge(p.approval_status)}</td></tr>`),'No marketplace products yet.');
  setRows('orders',(d.orders||[]).map(o=>`<tr><td><strong>${esc(o.order_ref||('ORDER-'+o.id))}</strong></td><td>${esc(o.buyer_name||'Buyer')}</td><td>${esc(o.store_name)}</td><td>${esc(o.listing_title)}</td><td>${money(o.total_amount)}</td><td>${statusBadge(o.payment_status)}</td><td>${statusBadge(o.status)}</td></tr>`),'No marketplace orders yet.');
  setRows('inventory',(d.products||[]).map(p=>`<tr><td><div class="avatar-row"><div class="product-thumb">NC</div>${esc(p.title)}</div></td><td>MP-${Number(p.id||0).toString().padStart(5,'0')}</td><td>${esc(p.store_name)}</td><td>${esc(p.quantity_available??'Ask')}</td><td>Review</td><td>${statusBadge(p.availability_status)}</td></tr>`),'No inventory records yet.');
  setRows('payments',(d.transactions||[]).map(t=>`<tr><td><strong>${esc(t.reference||('TX-'+t.id))}</strong></td><td>${esc(t.type||'wallet')}</td><td>${esc(t.user_name||'Wallet')}</td><td>${money(t.amount)}</td><td>-</td><td>${statusBadge(t.status)}</td></tr>`),'No wallet transactions yet.');
}
function exportUrl(type){return `${window.location.pathname}?export=${encodeURIComponent(type)}`}
function enhanceMarketplaceActions(){
  const map={reports:'sales-report','sales-report':'sales-report','seller-report':'seller-report','export-report':'sales-report',orders:'orders',payments:'financial',inventory:'inventory',products:'products',sellers:'sellers'};
  document.querySelectorAll('.page-header .btn-primary').forEach(btn=>{const page=btn.closest('.page')?.id?.replace('page-','')||'sales-report';if(/export|generate|download/i.test(btn.textContent)){btn.onclick=null;btn.addEventListener('click',()=>{window.location.href=exportUrl(map[page]||page)});}});
  document.querySelectorAll('#page-reports table button').forEach((btn,i)=>{const types=['sales-report','seller-report','inventory','financial','customers'];btn.onclick=null;btn.addEventListener('click',()=>{window.location.href=exportUrl(types[i]||'sales-report')});});
  document.querySelectorAll('#page-export-report .card').forEach((card,i)=>{const types=['sales-report','seller-report','inventory','financial','customers','disputes'];card.onclick=null;card.addEventListener('click',()=>{window.location.href=exportUrl(types[i]||'sales-report')});});
  document.querySelectorAll('button').forEach(btn=>{if(/Generate Label/i.test(btn.textContent)){btn.onclick=null;btn.addEventListener('click',()=>showToast('Shipping labels need courier integration before generation.'))}});
  document.querySelectorAll('[data-action-note]').forEach(el=>{
    const note=(el.dataset.actionNote||'').toLowerCase();
    el.onclick=null;
    el.addEventListener('click',()=>{
      if(note.includes('review')) window.location.href='../marketplace.php';
      else if(note.includes('export')||note.includes('download')) window.location.href=exportUrl('sales-report');
      else showToast(el.dataset.actionNote || 'Action noted');
    });
  });
  document.querySelectorAll('.modal-footer .btn-primary').forEach(btn=>{
    const text=btn.textContent.trim().toLowerCase();
    btn.onclick=null;
    btn.addEventListener('click',()=>{
      if(text.includes('seller')) window.location.href='../marketplace.php#sellers';
      else if(text.includes('stock')) window.location.href='../marketplace.php#listings';
      else if(text.includes('payout')) window.location.href='../wallet.php';
      else if(text.includes('dispute')) window.location.href='../support.php?category=marketplace';
    });
  });
}
function paginateMarketplaceTables(pageSize=25){
  document.querySelectorAll('.page table').forEach((table,idx)=>{
    const body=table.querySelector('tbody'); if(!body) return;
    const rows=[...body.querySelectorAll('tr')]; if(rows.length<=pageSize) return;
    let page=1; const total=Math.ceil(rows.length/pageSize);
    const nav=document.createElement('div'); nav.className='filter-bar'; nav.style.justifyContent='flex-end';
    const info=document.createElement('span'); info.style.fontSize='12px'; info.style.color='var(--text2)';
    const prev=document.createElement('button'); prev.className='btn btn-secondary btn-sm'; prev.type='button'; prev.textContent='Previous';
    const next=document.createElement('button'); next.className='btn btn-secondary btn-sm'; next.type='button'; next.textContent='Next';
    nav.append(prev,info,next); table.closest('.card-body')?.appendChild(nav);
    const render=()=>{rows.forEach((r,i)=>r.style.display=i>=(page-1)*pageSize&&i<page*pageSize?'':'none');info.textContent=`Page ${page} of ${total}`;prev.disabled=page<=1;next.disabled=page>=total};
    prev.addEventListener('click',()=>{page=Math.max(1,page-1);render()}); next.addEventListener('click',()=>{page=Math.min(total,page+1);render()}); render();
  });
}
hydrateMarketplaceTables();
enhanceMarketplaceActions();
paginateMarketplaceTables();
</script>
</body>
</html>

