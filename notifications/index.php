<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$pdo = db();

// ─── Ensure required schema tables and columns exist ──────────────────────────
app_ensure_core_schema($pdo);

// messages table may already exist from farmer-engagement bootstrap; ensure extras.
foreach ([
    'subject' => "VARCHAR(255) NOT NULL DEFAULT ''",
    'body'    => "TEXT NOT NULL",
    'type'    => "ENUM('system','support','marketplace','academy','field_task') NOT NULL DEFAULT 'system'",
] as $col => $def) {
    app_add_column_if_missing($pdo, 'messages', $col, $def);
}

// notification_preferences table
$pdo->exec("
    CREATE TABLE IF NOT EXISTS notification_preferences (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL UNIQUE,
        email_enabled TINYINT(1) NOT NULL DEFAULT 1,
        sms_enabled TINYINT(1) NOT NULL DEFAULT 0,
        in_app_enabled TINYINT(1) NOT NULL DEFAULT 1,
        categories TEXT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_prefs_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ─── Auth gate ────────────────────────────────────────────────────────────────
$current = current_user($pdo);
if (!$current) {
    session_destroy();
    redirect_to('../login.php?next=' . urlencode('/win/notifications/index.php'));
}

$userId = (int) $current['id'];

// ─── Feature gate ─────────────────────────────────────────────────────────────
if (!admin_feature_is_allowed($pdo, 'notifications')) {
    // Render inline disabled message instead of a generic error page.
    ?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Notifications - NATCODEV</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root{--green:#075f2a;--line:#dfe8d8;--bg:#f6faf4;--ink:#101828;--muted:#667085}
*{box-sizing:border-box}body{margin:0;background:var(--bg);font-family:"Segoe UI",Arial,sans-serif;color:var(--ink)}
.wrap{max-width:640px;margin:80px auto;padding:0 20px;text-align:center}
.card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:48px 32px}
.card h1{font-size:22px;margin:0 0 8px}.card p{color:var(--muted);margin:0 0 20px;line-height:1.6}
.btn-o{display:inline-block;background:#fff;color:var(--green);border:1px solid var(--green);border-radius:10px;padding:12px 28px;font-weight:800;text-decoration:none}
</style></head>
<body>
<div class="wrap"><div class="card">
<h1><i class="fas fa-bell" style="color:#F59E0B"></i> Notifications Module Disabled</h1>
<p>The notifications module has not been enabled for your account role yet. Please contact an administrator to request access.</p>
<a class="btn-o" href="../index.php"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
</div></div>
</body></html>
    <?php
    exit;
}

// ─── Action handlers (POST / redirect) ────────────────────────────────────────
$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_action'])) {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $message = 'Security verification failed. Please try again.';
        $messageType = 'error';
    } else {
        switch ($_POST['_action']) {
            case 'mark_all_read': {
                $stmt = $pdo->prepare("UPDATE messages SET status = 'read' WHERE recipient_id = ? AND status = 'unread'");
                $stmt->execute([$userId]);
                $message = 'All notifications marked as read.';
                break;
            }
            case 'mark_read': {
                if (is_numeric($_POST['msg_id'] ?? null) && (int) $_POST['msg_id'] > 0) {
                    $stmt = $pdo->prepare("UPDATE messages SET status = 'read' WHERE id = ? AND recipient_id = ? AND status = 'unread'");
                    $stmt->execute([(int) $_POST['msg_id'], $userId]);
                    $message = 'Notification marked as read.';
                }
                break;
            }
            case 'archive': {
                if (is_numeric($_POST['msg_id'] ?? null) && (int) $_POST['msg_id'] > 0) {
                    $stmt = $pdo->prepare("UPDATE messages SET status = 'archived' WHERE id = ? AND recipient_id = ?");
                    $stmt->execute([(int) $_POST['msg_id'], $userId]);
                    $message = 'Notification archived.';
                }
                break;
            }
            case 'delete': {
                if (is_numeric($_POST['msg_id'] ?? null) && (int) $_POST['msg_id'] > 0) {
                    $stmt = $pdo->prepare("DELETE FROM messages WHERE id = ? AND recipient_id = ?");
                    $stmt->execute([(int) $_POST['msg_id'], $userId]);
                    $message = 'Notification deleted.';
                }
                break;
            }
            case 'save_prefs': {
                $emailEnabled   = isset($_POST['pref_email']) ? 1 : 0;
                $smsEnabled     = isset($_POST['pref_sms']) ? 1 : 0;
                $inAppEnabled   = isset($_POST['pref_inapp']) ? 1 : 0;
                $categoriesRaw  = $_POST['pref_categories'] ?? [];
                if (!is_array($categoriesRaw)) {
                    $categoriesRaw = [];
                }
                $categoriesJson = json_encode(array_values(array_unique($categoriesRaw)));

                $stmt = $pdo->prepare("
                    INSERT INTO notification_preferences (user_id, email_enabled, sms_enabled, in_app_enabled, categories, updated_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE
                        email_enabled = VALUES(email_enabled),
                        sms_enabled = VALUES(sms_enabled),
                        in_app_enabled = VALUES(in_app_enabled),
                        categories = VALUES(categories),
                        updated_at = NOW()
                ");
                $stmt->execute([$userId, $emailEnabled, $smsEnabled, $inAppEnabled, $categoriesJson]);
                $message = 'Notification preferences saved successfully.';
                $messageType = 'success';
                break;
            }
        }
    }
}

// ─── Filter / pagination params ───────────────────────────────────────────────
$filterTab = $_GET['filter'] ?? 'all';
$perPageChoices = [25, 50, 100];
$perPage = max(1, min(100, (int) ($_GET['per_page'] ?? 50)));
if (!in_array($perPage, $perPageChoices, true)) {
    $perPage = 50;
}
$page = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

// ─── Counts per filter (for badges) ───────────────────────────────────────────
$counts = [
    'all'       => 0,
    'unread'    => 0,
    'system'    => 0,
    'support'   => 0,
    'marketplace'  => 0,
    'academy'   => 0,
    'field_tasks' => 0,
];

try {
    foreach ($counts as $key => $val) {
        $cSql = "SELECT COUNT(*) FROM messages WHERE recipient_id = ?";
        $cParams = [$userId];
        if ($key === 'all') {
            $cSql .= " AND status != 'archived'";
        } elseif ($key === 'unread') {
            $cSql .= " AND status = 'unread'";
        } else {
            $cSql .= " AND type = ?";
            $cParams[] = $key;
        }
        $cStmt = $pdo->prepare($cSql);
        $cStmt->execute($cParams);
        $counts[$key] = (int) $cStmt->fetchColumn();
    }
} catch (Throwable $e) {
    error_log('Notification count error: ' . $e->getMessage());
}

$totalItems = $counts[$filterTab];
$totalPages = max(1, (int) ceil($totalItems / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

// ─── Fetch notifications ─────────────────────────────────────────────────────
$notifications = [];
try {
    // Build per-tab WHERE + params for fetching
    $fetchSql = "SELECT id, sender_id, subject, body, status, type, created_at
                 FROM messages WHERE recipient_id = ?";
    $fetchParams = [$userId];
    if ($filterTab === 'unread') {
        $fetchSql .= " AND status = 'unread'";
    } elseif ($filterTab !== 'all') {
        $fetchSql .= " AND type = ?";
        $fetchParams[] = $filterTab;
    } else {
        $fetchSql .= " AND status != 'archived'";
    }
    $fetchSql .= " ORDER BY created_at DESC LIMIT " . ((int)$perPage) . " OFFSET " . ((int)$offset);
    $stmt = $pdo->prepare($fetchSql);
    $stmt->execute($fetchParams);
    $notifications = $stmt->fetchAll();
} catch (Throwable $e) {
    error_log('Notification fetch error: ' . $e->getMessage());
}

// ─── User notification preferences ────────────────────────────────────────────
$preferences = [
    'email_enabled'   => 1,
    'sms_enabled'     => 0,
    'in_app_enabled'  => 1,
    'categories'      => ['system', 'support', 'marketplace', 'academy', 'field_task'],
];
try {
    $pStmt = $pdo->prepare("SELECT * FROM notification_preferences WHERE user_id = ? LIMIT 1");
    $pStmt->execute([$userId]);
    $prefRow = $pStmt->fetch();
    if ($prefRow) {
        $preferences['email_enabled']   = (int) ($prefRow['email_enabled'] ?? 1);
        $preferences['sms_enabled']     = (int) ($prefRow['sms_enabled'] ?? 0);
        $preferences['in_app_enabled']  = (int) ($prefRow['in_app_enabled'] ?? 1);
        $prefsCategories = trim((string) ($prefRow['categories'] ?? ''));
        if ($prefsCategories !== '') {
            $decoded = json_decode($prefsCategories, true);
            if (is_array($decoded) && !empty($decoded)) {
                $preferences['categories'] = $decoded;
            }
        }
    }
} catch (Throwable $e) {
    error_log('Preferences fetch error: ' . $e->getMessage());
}

// ─── Helper: time-ago ────────────────────────────────────────────────────────
function relative_time(string $datetime): string
{
    $timestamp = strtotime($datetime);
    if (!$timestamp) {
        return date('M j', $timestamp);
    }
    $now = time();
    $diff = $now - $timestamp;
    if ($diff < 60) {
        return 'Just now';
    }
    if ($diff < 3600) {
        $units = (int) floor($diff / 60);
        return $units === 1 ? '1 minute ago' : $units . ' minutes ago';
    }
    if ($diff < 86400) {
        $units = (int) floor($diff / 3600);
        return $units === 1 ? '1 hour ago' : $units . ' hours ago';
    }
    if ($diff < 2592000) {
        $units = (int) floor($diff / 86400);
        return $units === 1 ? 'Yesterday' : $units . ' days ago';
    }
    return date('M j', $timestamp);
}

// ─── Helper: date group label ────────────────────────────────────────────────
function date_group_label(string $datetime): string
{
    $timestamp = strtotime($datetime);
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $datePart = date('Y-m-d', $timestamp);
    if ($datePart === $today) {
        return 'Today';
    }
    if ($datePart === $yesterday) {
        return 'Yesterday';
    }
    return date('F j, Y', $timestamp);
}

// ─── Group notifications by date ─────────────────────────────────────────────
$grouped = [];
$dateOrder = [];
foreach ($notifications as $n) {
    $label = date_group_label((string) $n['created_at']);
    if (!isset($grouped[$label])) {
        $grouped[$label] = [];
        $dateOrder[] = $label;
    }
    $grouped[$label][] = $n;
}

// ─── Category labels map ─────────────────────────────────────────────────────
$categoryLabels = [
    'system'      => 'System',
    'support'     => 'Support',
    'marketplace' => 'Marketplace',
    'academy'     => 'Academy',
    'field_task'  => 'Field Task',
];

// ─── Icon SVG helper ────────────────────────────────────────────────────────
function notification_icon(string $type): string
{
    $icons = [
        'system'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" width="18" height="18"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>',
        'support'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" width="18" height="18"><path d="M18 18.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z"/><path d="M18 13v1a3 3 0 0 1-3 3H5a3 3 0 0 1-3-3v-1a3 3 0 0 1 3-3h11a3 3 0 0 1 3 3v1Z"/><path d="M6.2 9.5 7.8 7l1.6 2.5"/><path d="M12 5v4"/><circle cx="12" cy="12" r="9"/></svg>',
        'marketplace' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" width="18" height="18"><path d="M4 5h2l2 10h9l2-7H7"/><circle cx="10" cy="19" r="1.5"/><circle cx="17" cy="19" r="1.5"/><path d="M12 10v4"/><path d="M10 12h4"/></svg>',
        'academy'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" width="18" height="18"><path d="m3 8 9-4 9 4-9 4-9-4Z"/><path d="M7 10v5c0 1.7 2.2 3 5 3s5-1.3 5-3v-5"/><path d="M21 8v6"/></svg>',
        'field_task'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" width="18" height="18"><path d="M9 12l2 2 4-4"/><rect x="3" y="3" width="18" height="18" rx="3"/></svg>',
    ];
    return $icons[$type] ?? $icons['system'];
}

// ─── Icon color per type ────────────────────────────────────────────────────
function icon_color(string $type): string
{
    $colors = [
        'system'      => '#6366F1',
        'support'     => '#F59E0B',
        'marketplace' => '#10B981',
        'academy'     => '#8B5CF6',
        'field_task'  => '#3B82F6',
    ];
    return $colors[$type] ?? '#6366F1';
}

// ═══════════════════ RENDER PAGE ══════════════════════════════════════════════

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Notifications - NATCODEV</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
    --primary-green:#1B5E20; --primary-green-light:#2E7D32; --primary-green-dark:#0d3310;
    --accent-teal:#26A69A;
    --bg-page:#F5F7FA; --card-bg:#FFFFFF; --text-primary:#1A1A2E;
    --text-secondary:#6B7280; --text-muted:#9CA3AF;
    --border-color:#E5E7EB; --border-light:#F3F4F6;
    --red:#EF4444; --orange:#F59E0B; --blue:#3B82F6; --purple:#8B5CF6;
    --green:#1B5E20; --line:#E5E7EB; --bg:#F5F7FA; --ink:#1A1A2E; --muted:#6B7280;
    --radius-sm:8px; --radius-md:12px; --radius-lg:16px;
    --shadow-sm:0 1px 2px rgba(0,0,0,.05);
    --shadow-md:0 4px 6px rgba(0,0,0,.08);
    --shadow-lg:0 10px 15px rgba(0,0,0,.1);
}

* { box-sizing: border-box; margin: 0; padding: 0; }

body {
    background: linear-gradient(135deg, #eef7ef 0%, #f8fbf4 48%, #edf8f5 100%);
    font-family: Inter, "Segoe UI", Arial, sans-serif;
    color: var(--ink);
    line-height: 1.6;
    min-height: 100vh;
}

/* ── Top bar ─────────────────────────────────────────────────────────────── */
.nt-topbar {
    position: sticky; top: 0; z-index: 100;
    background: #fff; border-bottom: 1px solid var(--line);
    height: 64px; display: flex; align-items: center; justify-content: space-between;
    padding: 0 24px; box-shadow: var(--shadow-sm);
}
.nt-topbar-brand { display: flex; align-items: center; gap: 12px; }
.nt-topbar-logo {
    width: 36px; height: 36px; border-radius: 50%;
    background: var(--primary-green); color: #fff;
    display: grid; place-items: center; font-weight: 900; font-size: 14px;
}
.nt-topbar-title { font-size: 16px; font-weight: 900; color: var(--primary-green); }
.nt-topbar-nav { display: flex; align-items: center; gap: 10px; }
.nt-topbar-nav a {
    font-size: 13px; font-weight: 800; color: var(--text-secondary);
    text-decoration: none; display: flex; align-items: center; gap: 6px;
    padding: 6px 12px; border-radius: var(--radius-sm);
    transition: background .15s, color .15s;
}
.nt-topbar-nav a:hover { background: #F0FDF4; color: var(--primary-green); }
.nt-user-chip {
    display: flex; align-items: center; gap: 8px;
    padding: 6px 14px; border-radius: 999px;
    background: #F0FDF4; font-size: 12px; font-weight: 800; color: var(--primary-green);
}
.nt-user-avatar {
    width: 28px; height: 28px; border-radius: 50%;
    background: var(--primary-green); color: #fff;
    display: grid; place-items: center; font-size: 11px; font-weight: 900;
}

/* ── Main container ──────────────────────────────────────────────────────── */
.nt-container {
    max-width: 800px; margin: 0 auto; padding: 24px 20px 60px;
}

/* ── Breadcrumb ──────────────────────────────────────────────────────────── */
.nt-breadcrumb {
    display: flex; align-items: center; gap: 8px;
    font-size: 13px; color: var(--muted); margin-bottom: 16px;
}
.nt-breadcrumb a {
    color: var(--primary-green); font-weight: 800; text-decoration: none;
}
.nt-breadcrumb a:hover { text-decoration: underline; }
.nt-breadcrumb .sep { opacity: .5; }

/* ── Page header ─────────────────────────────────────────────────────────── */
.nt-header {
    display: flex; align-items: flex-start; justify-content: space-between;
    gap: 16px; flex-wrap: wrap; margin-bottom: 24px;
}
.nt-header h1 {
    font-size: clamp(1.6rem, 3vw, 2rem); font-weight: 950; color: #111827;
    display: flex; align-items: center; gap: 10px;
}
.nt-header h1 i { color: var(--primary-green); }
.nt-header-actions { display: flex; gap: 8px; flex-wrap: wrap; }

/* ── Buttons ─────────────────────────────────────────────────────────────── */
.btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 9px 16px; border-radius: var(--radius-sm);
    font-size: 13px; font-weight: 800; cursor: pointer;
    text-decoration: none; border: 1px solid var(--border-color);
    background: #fff; color: var(--ink); transition: all .15s;
    white-space: nowrap;
}
.btn:hover { background: #F9FAFB; border-color: #D1D5DB; }
.btn-primary { background: var(--primary-green); color: #fff; border-color: var(--primary-green); }
.btn-primary:hover { background: var(--primary-green-light); border-color: var(--primary-green-light); }
.btn-danger { color: var(--red); }
.btn-danger:hover { background: #FEF2F2; border-color: var(--red); }
.btn-sm { padding: 5px 10px; font-size: 12px; }
.btn-icon {
    width: 32px; height: 32px; padding: 0; display: grid;
    place-items: center; border-radius: 8px; border: 1px solid transparent;
    background: transparent; color: var(--muted); cursor: pointer;
    transition: all .15s;
}
.btn-icon:hover { background: #F9FAFB; color: var(--ink); border-color: var(--border-color); }
.btn-icon.danger:hover { background: #FEF2F2; color: var(--red); }

/* ── Alert banner ────────────────────────────────────────────────────────── */
.nt-alert {
    padding: 12px 16px; border-radius: var(--radius-md);
    font-size: 13px; font-weight: 700; margin-bottom: 20px;
    display: flex; align-items: center; gap: 8px;
}
.nt-alert.success { background: #ECFDF5; color: #065F46; border: 1px solid #A7F3D0; }
.nt-alert.error   { background: #FEF2F2; color: #991B1B; border: 1px solid #FECACA; }

/* ── Filter tabs ─────────────────────────────────────────────────────────── */
.nt-tabs {
    display: flex; gap: 4px; overflow-x: auto; padding-bottom: 0;
    border-bottom: 2px solid var(--border-color); margin-bottom: 20px;
    scrollbar-width: none;
}
.nt-tabs::-webkit-scrollbar { display: none; }
.nt-tab {
    padding: 10px 16px; font-size: 13px; font-weight: 800;
    color: var(--text-secondary); cursor: pointer;
    border: none; background: transparent; white-space: nowrap;
    border-bottom: 2px solid transparent; margin-bottom: -2px;
    transition: color .15s, border-color .15s;
    display: flex; align-items: center; gap: 6px;
}
.nt-tab:hover { color: var(--ink); }
.nt-tab.active { color: var(--primary-green); border-bottom-color: var(--primary-green); }
.nt-tab-count {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 20px; height: 20px; border-radius: 999px;
    font-size: 11px; font-weight: 800; background: var(--border-color); color: var(--muted);
    padding: 0 6px;
}
.nt-tab.active .nt-tab-count { background: #DCFCE7; color: var(--primary-green); }
.nt-tab.unread-active .nt-tab-count { background: #DC2626; color: #fff; }

/* ── Notification card ───────────────────────────────────────────────────── */
.nt-date-group { margin-bottom: 24px; }
.nt-date-label {
    font-size: 12px; font-weight: 900; text-transform: uppercase;
    letter-spacing: .06em; color: var(--text-muted); margin-bottom: 8px;
    padding-left: 4px;
}
.nt-card {
    background: #fff; border: 1px solid var(--border-light);
    border-radius: var(--radius-md); padding: 16px 18px;
    margin-bottom: 8px; display: flex; align-items: flex-start;
    gap: 14px; transition: box-shadow .15s, border-color .15s;
}
.nt-card:hover { box-shadow: var(--shadow-md); border-color: var(--border-color); }
.nt-card.unread { border-left: 3px solid var(--primary-green); }

.nt-card-icon {
    width: 42px; height: 42px; border-radius: var(--radius-sm);
    display: grid; place-items: center; flex-shrink: 0;
    font-size: 16px;
}
.nt-card-body { flex: 1; min-width: 0; }
.nt-card-top { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 4px; }
.nt-card-subject {
    font-size: 14px; font-weight: 800; color: var(--ink);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.nt-card-preview {
    font-size: 13px; color: var(--text-secondary);
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    margin-bottom: 6px;
}
.nt-card-meta {
    display: flex; align-items: center; gap: 10px; font-size: 12px;
    font-weight: 700; color: var(--muted);
}
.nt-badge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 3px 8px; border-radius: 999px; font-size: 11px; font-weight: 800;
}
.nt-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
.nt-dot.green { background: #22C55E; }
.nt-dot.grey { background: #D1D5DB; }
.nt-card-actions { display: flex; gap: 4px; flex-shrink: 0; align-items: flex-start; }

/* ── Pagination ──────────────────────────────────────────────────────────── */
.nt-pagination {
    display: flex; align-items: center; justify-content: center;
    gap: 12px; margin-top: 24px; flex-wrap: wrap;
}
.nt-pages-info { font-size: 13px; color: var(--muted); font-weight: 700; }
.nt-pag-links { display: flex; gap: 4px; }
.nt-pag-btn {
    min-width: 36px; height: 36px; display: grid; place-items: center;
    border-radius: var(--radius-sm); font-size: 13px; font-weight: 800;
    text-decoration: none; color: var(--ink);
    border: 1px solid var(--border-color); background: #fff;
    transition: all .15s; padding: 0 4px;
}
.nt-pag-btn:hover { background: #F0FDF4; color: var(--primary-green); border-color: var(--primary-green); }
.nt-pag-btn.active { background: var(--primary-green); color: #fff; border-color: var(--primary-green); }
.nt-pag-btn.disabled { opacity: .4; pointer-events: none; }
.nt-per-page-selector select {
    padding: 6px 10px; border: 1px solid var(--border-color);
    border-radius: var(--radius-sm); font-size: 13px; font-weight: 700;
    background: #fff; color: var(--ink); cursor: pointer;
}

/* ── Empty state ─────────────────────────────────────────────────────────── */
.nt-empty {
    background: #fff; border: 2px dashed var(--border-color);
    border-radius: var(--radius-lg); padding: 60px 32px;
    text-align: center; margin-top: 20px;
}
.nt-empty-icon {
    width: 80px; height: 80px; border-radius: 50%;
    background: #F0FDF4; margin: 0 auto 20px;
    display: grid; place-items: center; font-size: 32px; color: var(--primary-green);
}
.nt-empty h3 { font-size: 18px; font-weight: 900; color: var(--ink); margin-bottom: 6px; }
.nt-empty p { font-size: 14px; color: var(--muted); max-width: 360px; margin: 0 auto; line-height: 1.6; }

/* ── Modal ───────────────────────────────────────────────────────────────── */
.nt-modal-overlay {
    display: none; position: fixed; inset: 0; z-index: 999;
    background: rgba(0,0,0,.4); backdrop-filter: blur(4px);
    place-items: center; padding: 20px;
}
.nt-modal-overlay.open { display: grid; }
.nt-modal {
    background: #fff; border-radius: var(--radius-lg);
    width: 100%; max-width: 520px; max-height: 90vh; overflow-y: auto;
    box-shadow: var(--shadow-lg);
}
.nt-modal-head {
    display: flex; align-items: center; justify-content: space-between;
    padding: 18px 22px; border-bottom: 1px solid var(--border-color);
    position: sticky; top: 0; background: #fff; z-index: 1;
}
.nt-modal-head h2 { font-size: 17px; font-weight: 900; color: var(--ink); margin: 0; }
.nt-modal-body { padding: 22px; }
.nt-modal-footer {
    padding: 14px 22px; border-top: 1px solid var(--border-color);
    display: flex; justify-content: flex-end; gap: 8px;
}

.nt-pref-section { margin-bottom: 22px; }
.nt-pref-section h4 {
    font-size: 13px; font-weight: 900; text-transform: uppercase;
    letter-spacing: .05em; color: var(--text-muted); margin-bottom: 10px;
}
.nt-pref-row {
    display: flex; align-items: center; justify-content: space-between;
    padding: 10px 0; border-bottom: 1px solid var(--border-light);
}
.nt-pref-row:last-child { border-bottom: none; }
.nt-pref-label { font-size: 14px; font-weight: 700; color: var(--ink); }
.nt-pref-desc { font-size: 12px; color: var(--muted); }

/* Toggle switch */
.nt-toggle { position: relative; width: 44px; height: 24px; flex-shrink: 0; }
.nt-toggle input { opacity: 0; width: 0; height: 0; position: absolute; }
.nt-toggle-slider {
    position: absolute; inset: 0; background: #D1D5DB;
    border-radius: 999px; cursor: pointer; transition: background .2s;
}
.nt-toggle-slider::before {
    content: ""; position: absolute; width: 18px; height: 18px;
    left: 3px; top: 3px; background: #fff; border-radius: 50%;
    transition: transform .2s;
}
.nt-toggle input:checked + .nt-toggle-slider { background: var(--primary-green); }
.nt-toggle input:checked + .nt-toggle-slider::before { transform: translateX(20px); }

/* Checkbox list */
.nt-cat-list { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }
.nt-cat-check {
    display: flex; align-items: center; gap: 8px;
    padding: 8px 10px; border-radius: var(--radius-sm);
    border: 1px solid var(--border-color); cursor: pointer;
    transition: all .15s; font-size: 13px; font-weight: 700;
}
.nt-cat-check:hover { background: #F0FDF4; border-color: var(--primary-green); }
.nt-cat-check.checked { background: #F0FDF4; border-color: var(--primary-green); }
.nt-cat-check input { accent-color: var(--primary-green); }

/* ── Form helpers ────────────────────────────────────────────────────────── */
.nt-form-action { display: none; }

/* ── Mobile responsive ───────────────────────────────────────────────────── */
@media (max-width: 768px) {
    .nt-topbar { padding: 0 14px; height: 56px; }
    .nt-topbar-title { font-size: 14px; }
    .nt-user-chip span:not(.nt-user-avatar) { display: none; }
    .nt-container { padding: 16px 12px 40px; }
    .nt-header { flex-direction: column; align-items: stretch; gap: 12px; }
    .nt-header-actions { justify-content: flex-end; }
    .nt-card { flex-direction: column; gap: 10px; padding: 14px; }
    .nt-card-actions { align-self: flex-end; }
    .nt-tabs { gap: 0; }
    .nt-tab { padding: 8px 12px; font-size: 12px; }
    .nt-cat-list { grid-template-columns: 1fr; }
    .nt-pagination { flex-direction: column; gap: 8px; }
}

@media (max-width: 1024px) {
    .nt-topbar-title { font-size: 14px; }
    .nt-topbar-nav { gap: 6px; }
    .nt-topbar-nav a { padding: 6px 8px; font-size: 12px; }
}
</style>
</head>
<body>

<!-- ═══ TOP BAR ═══ -->
<header class="nt-topbar">
    <div class="nt-topbar-brand">
        <div class="nt-topbar-logo"><i class="fas fa-bell"></i></div>
        <span class="nt-topbar-title">NATCODEV</span>
    </div>
    <div class="nt-topbar-nav">
        <a href="../index.php"><i class="fas fa-home"></i> Dashboard</a>
        <div class="nt-user-chip">
            <div class="nt-user-avatar"><?= e(strtoupper(substr($current['name'] ?? 'U', 0, 1))) ?></div>
            <span><?= e((string) $current['name']) ?></span>
        </div>
    </div>
</header>

<!-- ═══ MAIN CONTENT ═══ -->
<div class="nt-container">

    <!-- Breadcrumb -->
    <nav class="nt-breadcrumb">
        <a href="../index.php"><i class="fas fa-th-large"></i> Workspace</a>
        <span class="sep"><i class="fas fa-chevron-right" style="font-size:10px"></i></span>
        <span>Notifications</span>
    </nav>

    <!-- Page Header -->
    <section class="nt-header">
        <h1><i class="fas fa-bell"></i> Notifications</h1>
        <div class="nt-header-actions">
            <button class="btn btn-primary" onclick="document.getElementById('action-mark-all-read').click()">
                <i class="fas fa-check-double"></i> Mark All Read
            </button>
            <button class="btn" onclick="openModal()">
                <i class="fas fa-sliders-h"></i> Settings
            </button>
        </div>
    </section>

    <!-- Alert Banner -->
    <?php if ($message !== ''): ?>
    <div class="nt-alert <?= $messageType ?>">
        <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
        <?= e($message) ?>
    </div>
    <?php endif; ?>

    <!-- ═══ FILTER TABS ═══ -->
    <div class="nt-tabs">
        <a class="nt-tab<?= $filterTab === 'all' ? ' active' : '' ?>" href="?filter=all&per_page=<?= $perPage ?>&page=1">
            <i class="fas fa-inbox"></i> All <span class="nt-tab-count"><?= (int) $counts['all'] ?></span>
        </a>
        <a class="nt-tab<?= $filterTab === 'unread' ? ' active unread-active' : '' ?>" href="?filter=unread&per_page=<?= $perPage ?>&page=1">
            <i class="fas fa-envelope-open-text"></i> Unread <span class="nt-tab-count"><?= (int) $counts['unread'] ?></span>
        </a>
        <a class="nt-tab<?= $filterTab === 'system' ? ' active' : '' ?>" href="?filter=system&per_page=<?= $perPage ?>&page=1">
            <i class="fas fa-server"></i> System <span class="nt-tab-count"><?= (int) $counts['system'] ?></span>
        </a>
        <a class="nt-tab<?= $filterTab === 'support' ? ' active' : '' ?>" href="?filter=support&per_page=<?= $perPage ?>&page=1">
            <i class="fas fa-headset"></i> Support <span class="nt-tab-count"><?= (int) $counts['support'] ?></span>
        </a>
        <a class="nt-tab<?= $filterTab === 'marketplace' ? ' active' : '' ?>" href="?filter=marketplace&per_page=<?= $perPage ?>&page=1">
            <i class="fas fa-store"></i> Marketplace <span class="nt-tab-count"><?= (int) $counts['marketplace'] ?></span>
        </a>
        <a class="nt-tab<?= $filterTab === 'academy' ? ' active' : '' ?>" href="?filter=academy&per_page=<?= $perPage ?>&page=1">
            <i class="fas fa-graduation-cap"></i> Academy <span class="nt-tab-count"><?= (int) $counts['academy'] ?></span>
        </a>
        <a class="nt-tab<?= $filterTab === 'field_tasks' ? ' active' : '' ?>" href="?filter=field_tasks&per_page=<?= $perPage ?>&page=1">
            <i class="fas fa-tasks"></i> Field Tasks <span class="nt-tab-count"><?= (int) $counts['field_tasks'] ?></span>
        </a>
    </div>

    <!-- ═══ NOTIFICATION LIST ═══ -->
    <?php if (count($grouped) > 0 || (count($notifications) > 0 && empty($grouped))): ?>
        <?php if (empty($grouped)): ?>
            <!-- Edge case: fetched but didn't group (shouldn't happen, fallback render) -->
            <div class="nt-date-group">
                <div class="nt-date-label">Recent</div>
        <?php endif; ?>

        <?php foreach ($grouped as $dateLabel => $items): ?>
        <div class="nt-date-group">
            <div class="nt-date-label"><?= e($dateLabel) ?></div>
            <?php foreach ($items as $n):
                $isUnread = strtolower((string) $n['status']) === 'unread';
                $nType = (string) $n['type'];
                $nSubject = trim((string) $n['subject']);
                $nBody = trim((string) $n['body']);
                $preview = mb_substr($nBody, 0, 120);
                if (mb_strlen($nBody) > 120) {
                    $preview .= '…';
                }
                if ($nSubject === '' && $preview === '') {
                    $preview = '(No content)';
                }
            ?>
            <article class="nt-card<?= $isUnread ? ' unread' : '' ?>" data-msg-id="<?= (int) $n['id'] ?>">
                <!-- Type icon -->
                <div class="nt-card-icon" style="background: <?= e(icon_color($nType)) ?>15; color: <?= e(icon_color($nType)) ?>">
                    <?= notification_icon($nType) ?>
                </div>
                <!-- Body -->
                <div class="nt-card-body">
                    <div class="nt-card-top">
                        <span class="nt-card-subject"><?= e($nSubject !== '' ? $nSubject : ($categoryLabels[$nType] ?: 'Notification')) ?></span>
                        <span class="nt-card-meta">
                            <?= $isUnread ? '<span class="nt-dot green"></span>' : '<span class="nt-dot grey"></span>' ?>
                            <span title="<?= e(date('M j, Y g:i A', strtotime((string) $n['created_at']))) ?>">
                                <?= e(relative_time((string) $n['created_at'])) ?>
                            </span>
                        </span>
                    </div>
                    <div class="nt-card-preview"><?= e($preview) ?></div>
                    <div class="nt-card-meta">
                        <span class="nt-badge" style="background:<?= e(icon_color($nType)) ?>12; color:<?= e(icon_color($nType)) ?>">
                            <?= e($categoryLabels[$nType] ?? $nType) ?>
                        </span>
                        <?php if ($isUnread): ?><span class="nt-badge" style="background:#FEF3C7;color:#92400E">Unread</span><?php endif; ?>
                    </div>
                </div>
                <!-- Actions -->
                <div class="nt-card-actions">
                    <?php if ($isUnread): ?>
                    <form method="post" class="nt-form-action">
                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="_action" value="mark_read">
                        <input type="hidden" name="msg_id" value="<?= (int) $n['id'] ?>">
                        <button type="submit" class="btn-icon" title="Mark as read" aria-label="Mark as read">
                            <i class="fas fa-check"></i>
                        </button>
                    </form>
                    <?php else: ?>
                    <button class="btn-icon disabled" title="Already read" aria-label="Already read">
                        <i class="fas fa-check" style="opacity:.3"></i>
                    </button>
                    <?php endif; ?>
                    <form method="post" class="nt-form-action">
                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="_action" value="archive">
                        <input type="hidden" name="msg_id" value="<?= (int) $n['id'] ?>">
                        <button type="submit" class="btn-icon" title="Archive" aria-label="Archive">
                            <i class="fas fa-archive"></i>
                        </button>
                    </form>
                    <form method="post" class="nt-form-action" onsubmit="return confirm('Delete this notification permanently?')">
                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="_action" value="delete">
                        <input type="hidden" name="msg_id" value="<?= (int) $n['id'] ?>">
                        <button type="submit" class="btn-icon danger" title="Delete" aria-label="Delete">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </form>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>

        <?php if (empty($grouped)): ?>
            </div>
        <?php endif; ?>

    <?php elseif ($totalItems === 0): ?>
        <!-- ═══ EMPTY STATE ═══ -->
        <div class="nt-empty">
            <div class="nt-empty-icon">
                <i class="far fa-bell"></i>
            </div>
            <h3>No notifications yet</h3>
            <p>When you receive updates from system alerts, support tickets, marketplace activity, Academy courses, or field tasks, they will appear here.</p>
        </div>
    <?php endif; ?>

    <!-- ═══ PAGINATION ═══ -->
    <?php if ($totalItems > 0): ?>
    <div class="nt-pagination">
        <div class="nt-per-page-selector">
            <select name="per_page" onchange="this.form.submit()">
                <?php foreach ($perPageChoices as $size): ?>
                    <option value="<?= $size ?>" <?= $perPage === $size ? 'selected' : '' ?>><?= $size ?> per page</option>
                <?php endforeach; ?>
            </select>
        </div>
        <span class="nt-pages-info">
            Showing <?= (int) min($offset + 1, $totalItems) ?>–<?= (int) min($offset + $perPage, $totalItems) ?> of <?= (int) $totalItems ?>
        </span>
        <div class="nt-pag-links">
            <?php if ($page > 1): ?>
                <a class="nt-pag-btn" href="?filter=<?= e($filterTab) ?>&per_page=<?= $perPage ?>&page=<?= $page - 1 ?>"><i class="fas fa-chevron-left"></i></a>
            <?php else: ?>
                <span class="nt-pag-btn disabled"><i class="fas fa-chevron-left"></i></span>
            <?php endif; ?>

            <?php
            // Smart page rendering: always show first, last, current, and neighbors
            $startPage = 1;
            $endPage = $totalPages;
            if ($totalPages > 7) {
                $startPage = min(3, $totalPages);
                $endPage = max($totalPages - 2, $page + 2);
            }
            for ($pg = $startPage; $pg <= $endPage; $pg++):
                if ($pg === 1 || $pg === $totalPages || abs($pg - $page) <= 1):
            ?>
                <a class="nt-pag-btn<?= $pg === $page ? ' active' : '' ?>"
                   href="?filter=<?= e($filterTab) ?>&per_page=<?= $perPage ?>&page=<?= $pg ?>">
                    <?= $pg ?>
                </a>
            <?php
                elseif ($pg === $startPage + 1 || $pg === $endPage - 1):
            ?>
                <span class="nt-pag-btn disabled">…</span>
            <?php endif; ?>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <a class="nt-pag-btn" href="?filter=<?= e($filterTab) ?>&per_page=<?= $perPage ?>&page=<?= $page + 1 ?>"><i class="fas fa-chevron-right"></i></a>
            <?php else: ?>
                <span class="nt-pag-btn disabled"><i class="fas fa-chevron-right"></i></span>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ═══ SETTINGS MODAL ═══ -->
<div class="nt-modal-overlay" id="settingsModal">
    <form method="post" class="nt-modal" onsubmit="closeModal()">
        <div class="nt-modal-head">
            <h2><i class="fas fa-cog"></i> Edit Notification Settings</h2>
            <button type="button" class="btn-icon" onclick="closeModal()" aria-label="Close">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="nt-modal-body">
            <div class="nt-pref-section">
                <h4>Delivery Channels</h4>
                <div class="nt-pref-row">
                    <div>
                        <div class="nt-pref-label">Email Notifications</div>
                        <div class="nt-pref-desc">Receive updates via email</div>
                    </div>
                    <label class="nt-toggle">
                        <input type="checkbox" name="pref_email" value="1"
                            <?= $preferences['email_enabled'] ? 'checked' : '' ?>>
                        <span class="nt-toggle-slider"></span>
                    </label>
                </div>
                <div class="nt-pref-row">
                    <div>
                        <div class="nt-pref-label">SMS Notifications</div>
                        <div class="nt-pref-desc">Receive text message alerts</div>
                    </div>
                    <label class="nt-toggle">
                        <input type="checkbox" name="pref_sms" value="1"
                            <?= $preferences['sms_enabled'] ? 'checked' : '' ?>>
                        <span class="nt-toggle-slider"></span>
                    </label>
                </div>
                <div class="nt-pref-row">
                    <div>
                        <div class="nt-pref-label">In-App Notifications</div>
                        <div class="nt-pref-desc">See bell badge & notification feed</div>
                    </div>
                    <label class="nt-toggle">
                        <input type="checkbox" name="pref_inapp" value="1" checked disabled>
                        <span class="nt-toggle-slider"></span>
                    </label>
                    <small style="color:var(--muted);font-size:11px;font-weight:700">Cannot be disabled</small>
                </div>
            </div>

            <div class="nt-pref-section">
                <h4>Notification Categories</h4>
                <div class="nt-cat-list">
                    <?php
                    $allCategories = ['system', 'support', 'marketplace', 'academy', 'field_task'];
                    foreach ($allCategories as $cat):
                        $checked = in_array($cat, $preferences['categories'], true) ? 'checked' : '';
                        $label = $categoryLabels[$cat] ?? $cat;
                    ?>
                    <label class="nt-cat-check <?= $checked ? ' checked' : '' ?>">
                        <input type="checkbox" name="pref_categories[]" value="<?= e($cat) ?>" <?= $checked ?>>
                        <span><?= e($label) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="nt-modal-footer">
            <button type="button" class="btn" onclick="closeModal()">Cancel</button>
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="_action" value="save_prefs">
            <button type="submit" class="btn btn-primary">Save Preferences</button>
        </div>
    </form>
</div>

<!-- Hidden form for Mark All Read action -->
<form method="post" class="nt-form-action" id="action-mark-all-read">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="_action" value="mark_all_read">
</form>

<script>
(function () {
    /* Modal open/close */
    window.openModal = function () {
        document.getElementById('settingsModal').classList.add('open');
        document.body.style.overflow = 'hidden';
    };
    window.closeModal = function () {
        document.getElementById('settingsModal').classList.remove('open');
        document.body.style.overflow = '';
    };
    /* Close modal on overlay click */
    document.getElementById('settingsModal').addEventListener('click', function (e) {
        if (e.target === this) closeModal();
    });
    /* Close modal on Escape */
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeModal();
    });
    /* Highlight checkbox rows when toggled */
    document.querySelectorAll('.nt-cat-check input[type="checkbox"]').forEach(function (cb) {
        cb.addEventListener('change', function () {
            this.closest('.nt-cat-check').classList.toggle('checked', this.checked);
        });
    });
})();
</script>
</body>
</html>
