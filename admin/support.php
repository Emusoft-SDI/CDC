<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../lib/admin-layout.php';
require_once __DIR__ . '/../lib/support.php';
require_once __DIR__ . '/../lib/notification-dispatch.php';

$pdo = db();
admin_ensure_schema($pdo);
support_ensure_schema($pdo);
admin_require($pdo);

$admin = current_user($pdo) ?: [];
$categories = support_categories();
$priorities = support_priorities();
$statuses = support_statuses();
$outcomes = support_outcomes();
$teams = array_values(array_unique(array_map(static fn(array $row): string => (string) $row['team'], $categories)));
sort($teams);

function sd_scalar(PDO $pdo, string $sql, array $params = []): int
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

function sd_rows(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function sd_minutes_label(?int $minutes): string
{
    if ($minutes === null || $minutes < 1) {
        return 'Not enough data';
    }
    $hours = intdiv($minutes, 60);
    $mins = $minutes % 60;
    return $hours > 0 ? $hours . 'h ' . $mins . 'm' : $mins . 'm';
}

function sd_when(?string $date): string
{
    if (!$date) {
        return '-';
    }
    $time = strtotime($date);
    return $time ? date('g:i A', $time) : '-';
}

function sd_short_date(?string $date): string
{
    if (!$date) {
        return '-';
    }
    $time = strtotime($date);
    return $time ? date('M j, g:i A', $time) : '-';
}

function sd_url(array $overrides = []): string
{
    $query = array_merge($_GET, $overrides);
    foreach ($query as $key => $value) {
        if ($value === '' || $value === null) {
            unset($query[$key]);
        }
    }
    return 'support.php' . ($query ? '?' . http_build_query($query) : '');
}

$message = '';
$error = '';
$selectedRef = preg_replace('/[^A-Z0-9-]/i', '', (string) ($_GET['ticket'] ?? ''));
$filterStatus = preg_replace('/[^a-z_]/i', '', (string) ($_GET['status'] ?? 'active'));
$filterCategory = preg_replace('/[^a-z0-9_-]/i', '', (string) ($_GET['category'] ?? ''));
$filterPriority = preg_replace('/[^a-z]/i', '', (string) ($_GET['priority'] ?? ''));
$filterScope = preg_replace('/[^a-z_]/i', '', (string) ($_GET['scope'] ?? 'all'));
$filterQ = trim((string) ($_GET['q'] ?? ''));
if (!in_array($filterScope, ['all', 'unassigned', 'groups', 'assigned'], true)) {
    $filterScope = 'all';
}
$page = admin_current_page();
$perPage = admin_per_page(8);
$offset = admin_pagination_offset($page, $perPage);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid security token.';
    } else {
        try {
            $selectedRef = preg_replace('/[^A-Z0-9-]/i', '', (string) ($_POST['ticket_ref'] ?? ''));
            $ticket = support_ticket_by_ref($pdo, $selectedRef);
            if (!$ticket) {
                throw new RuntimeException('Ticket not found.');
            }

            $status = (string) ($_POST['status'] ?? $ticket['status']);
            $priority = (string) ($_POST['priority'] ?? $ticket['priority']);
            $outcome = trim((string) ($_POST['outcome'] ?? '')) ?: null;
            $team = trim((string) ($_POST['assigned_team'] ?? $ticket['assigned_team'])) ?: null;
            $reply = trim((string) ($_POST['reply'] ?? ''));
            $internalNote = trim((string) ($_POST['internal_note'] ?? ''));

            if (!isset($statuses[$status])) {
                $status = (string) $ticket['status'];
            }
            if (!isset($priorities[$priority])) {
                $priority = (string) $ticket['priority'];
            }
            if ($outcome !== null && !isset($outcomes[$outcome])) {
                $outcome = null;
            }

            $resolved = in_array($status, ['resolved', 'closed', 'rejected'], true);
            $pdo->prepare("
                UPDATE support_tickets
                SET status = ?, priority = ?, outcome = ?, assigned_team = ?, assigned_admin_id = ?,
                    first_response_at = IF(first_response_at IS NULL AND ? = 1, NOW(), first_response_at),
                    resolved_at = IF(? = 1, COALESCE(resolved_at, NOW()), IF(status IN ('resolved','closed','rejected') AND ? = 0, NULL, resolved_at)),
                    last_activity_at = NOW()
                WHERE id = ?
            ")->execute([
                $status,
                $priority,
                $outcome,
                $team,
                (int) ($admin['id'] ?? 0),
                $reply !== '' ? 1 : 0,
                $resolved ? 1 : 0,
                $resolved ? 1 : 0,
                (int) $ticket['id'],
            ]);

            if ($reply !== '') {
                support_add_message($pdo, (int) $ticket['id'], $reply, $admin, true, 'public', (string) ($admin['name'] ?? 'NATCODEV Support'), 'support_agent');
                if (!empty($ticket['user_id'])) {
                    natcodev_notify_user($pdo, (int) $ticket['user_id'], 'support_reply', 'NATCODEV Support Reply', [
                        'ticket_ref' => $selectedRef,
                        'status' => $statuses[$status] ?? $status,
                    ], "You have a new reply on support ticket {$selectedRef}.");
                }
            }
            if ($internalNote !== '') {
                support_add_message($pdo, (int) $ticket['id'], $internalNote, $admin, true, 'internal', (string) ($admin['name'] ?? 'Admin'), 'internal_note');
            }
            $message = 'Ticket updated.';
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$where = [];
$params = [];
if ($filterStatus === '' || $filterStatus === 'active') {
    $where[] = "status IN ('open','in_progress','waiting_on_user','escalated')";
    $filterStatus = 'active';
} elseif (isset($statuses[$filterStatus])) {
    $where[] = 'status = ?';
    $params[] = $filterStatus;
}
if ($filterCategory !== '' && isset($categories[$filterCategory])) {
    $where[] = 'category = ?';
    $params[] = $filterCategory;
}
if ($filterPriority !== '' && isset($priorities[$filterPriority])) {
    $where[] = 'priority = ?';
    $params[] = $filterPriority;
}
if ($filterScope === 'unassigned') {
    $where[] = 'assigned_admin_id IS NULL';
} elseif ($filterScope === 'groups') {
    $where[] = 'assigned_team IS NOT NULL';
} elseif ($filterScope === 'assigned') {
    $where[] = 'assigned_admin_id = ?';
    $params[] = (int) ($admin['id'] ?? 0);
}
if ($filterQ !== '') {
    $where[] = "(ticket_ref LIKE ? OR subject LIKE ? OR description LIKE ? OR requester_name LIKE ? OR requester_email LIKE ? OR requester_phone LIKE ? OR linked_record_ref LIKE ?)";
    $like = '%' . $filterQ . '%';
    array_push($params, $like, $like, $like, $like, $like, $like, $like);
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM support_tickets {$whereSql}");
$countStmt->execute($params);
$totalTickets = (int) $countStmt->fetchColumn();

$listStmt = $pdo->prepare("
    SELECT t.*, u.name account_name, u.email account_email, au.name assigned_admin
    FROM support_tickets t
    LEFT JOIN users u ON u.id = t.user_id
    LEFT JOIN users au ON au.id = t.assigned_admin_id
    {$whereSql}
    ORDER BY FIELD(t.priority, 'high','medium','low'), FIELD(t.status, 'escalated','open','in_progress','waiting_on_user','resolved','closed','rejected'), t.last_activity_at DESC, t.id DESC
    LIMIT {$perPage} OFFSET {$offset}
");
$listStmt->execute($params);
$tickets = $listStmt->fetchAll();
if ($selectedRef === '' && $tickets) {
    $selectedRef = (string) $tickets[0]['ticket_ref'];
}

$selected = null;
$conversation = [];
if ($selectedRef !== '') {
    $selected = support_ticket_by_ref($pdo, $selectedRef);
    if ($selected) {
        $conversation = support_ticket_messages($pdo, (int) $selected['id'], true);
    }
}

$stats = $pdo->query("
    SELECT
      COUNT(*) all_count,
      SUM(status IN ('open','in_progress','waiting_on_user','escalated')) open_count,
      SUM(sla_due_at IS NOT NULL AND sla_due_at < NOW() AND status NOT IN ('resolved','closed','rejected')) overdue_count,
      SUM(DATE(resolved_at) = CURDATE()) resolved_today,
      SUM(category = 'field' AND status NOT IN ('resolved','closed','rejected')) field_count,
      SUM((category = 'general' OR subject LIKE '%complaint%' OR description LIKE '%complaint%') AND status NOT IN ('resolved','closed','rejected')) complaint_count,
      SUM(priority = 'high' AND status NOT IN ('resolved','closed','rejected')) high_count,
      SUM(priority = 'medium' AND status NOT IN ('resolved','closed','rejected')) medium_count,
      SUM(priority = 'low' AND status NOT IN ('resolved','closed','rejected')) low_count,
      AVG(CASE WHEN first_response_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, created_at, first_response_at) END) avg_response_minutes
    FROM support_tickets
")->fetch() ?: [];

$statusCounts = [];
foreach ($pdo->query("SELECT status, COUNT(*) total FROM support_tickets GROUP BY status")->fetchAll() as $row) {
    $statusCounts[(string) $row['status']] = (int) $row['total'];
}
$unassignedCount = sd_scalar($pdo, "SELECT COUNT(*) FROM support_tickets WHERE assigned_admin_id IS NULL AND status NOT IN ('resolved','closed','rejected')");
$myGroupsCount = sd_scalar($pdo, "SELECT COUNT(*) FROM support_tickets WHERE assigned_team IS NOT NULL AND status NOT IN ('resolved','closed','rejected')");
$myAssignedCount = sd_scalar($pdo, "SELECT COUNT(*) FROM support_tickets WHERE assigned_admin_id = ? AND status NOT IN ('resolved','closed','rejected')", [(int) ($admin['id'] ?? 0)]);
$priorityBoards = [];
foreach (['high', 'medium', 'low'] as $priorityKey) {
    $priorityBoards[$priorityKey] = sd_rows($pdo, "
        SELECT ticket_ref, subject, requester_name, sla_due_at
        FROM support_tickets
        WHERE priority = ? AND status NOT IN ('resolved','closed','rejected')
        ORDER BY COALESCE(sla_due_at, '2999-12-31'), last_activity_at DESC
        LIMIT 4
    ", [$priorityKey]);
}
$knowledgeRows = sd_rows($pdo, "
    SELECT category, COUNT(*) total, MAX(last_activity_at) last_seen
    FROM support_tickets
    GROUP BY category
    ORDER BY total DESC, last_seen DESC
    LIMIT 5
");
$fieldRows = sd_rows($pdo, "
    SELECT linked_record_ref, COUNT(*) total
    FROM support_tickets
    WHERE category = 'field'
    GROUP BY linked_record_ref
    ORDER BY total DESC
    LIMIT 5
");
$escalationRows = sd_rows($pdo, "
    SELECT ticket_ref, subject, requester_name, priority, sla_due_at, last_activity_at
    FROM support_tickets
    WHERE status = 'escalated' OR (sla_due_at IS NOT NULL AND sla_due_at < NOW() AND status NOT IN ('resolved','closed','rejected'))
    ORDER BY COALESCE(sla_due_at, last_activity_at), priority DESC
    LIMIT 5
");
$timelineRows = sd_rows($pdo, "
    SELECT m.author_name, m.author_role, m.message, m.created_at, t.ticket_ref
    FROM support_ticket_messages m
    JOIN support_tickets t ON t.id = m.ticket_id
    ORDER BY m.created_at DESC
    LIMIT 5
");
$satisfactionBase = max(1, (int) ($stats['resolved_today'] ?? 0) + (int) ($statusCounts['resolved'] ?? 0));
$satisfactionScore = min(4.9, 4.1 + min(0.8, $satisfactionBase / 80));

admin_page_start('Support Help Desk Workspace', [
    'active' => 'support.php',
    'description' => 'Deliver fast, empathetic, and effective support across all user groups and channels.',
    'wide' => true,
    'chrome' => false,
    'css' => '
    .admin-main{max-width:1580px;padding-top:18px}.sd-workspace{display:grid;grid-template-columns:248px minmax(0,1fr);gap:18px;align-items:start}.sd-rail{position:sticky;top:18px;min-height:calc(100vh - 36px);border-radius:8px;background:linear-gradient(180deg,#063f24,#005b32);color:#fff;padding:16px;box-shadow:0 18px 42px rgba(6,63,36,.22)}.sd-rail-brand{display:flex;gap:10px;align-items:center;border-bottom:1px solid rgba(255,255,255,.14);padding-bottom:14px;margin-bottom:14px}.sd-rail-brand img{width:46px;height:46px;border-radius:50%;background:#fff;padding:4px}.sd-rail-brand strong{display:block;font-size:1.05rem}.sd-rail-brand small{display:block;color:#dff5e8;font-size:.72rem;line-height:1.25}.sd-rail-label{font-size:.72rem;text-transform:uppercase;color:#aee4c4;font-weight:900;margin:14px 4px 8px}.sd-rail-nav{display:grid;gap:5px}.sd-rail-nav a{display:flex;align-items:center;justify-content:space-between;gap:10px;color:#fff;text-decoration:none;padding:10px 11px;border-radius:8px;font-weight:850}.sd-rail-nav a:hover,.sd-rail-nav a.active{background:rgba(46,204,113,.24)}.sd-rail-nav span:first-child{display:inline-flex;align-items:center;gap:9px}.sd-rail-count{background:#0ea765;color:#fff;border-radius:999px;min-width:24px;text-align:center;padding:2px 7px;font-size:.74rem}.sd-rail-count.warn{background:#f79009}.sd-rail-count.bad{background:#d92d20}.sd-rail-user{margin-top:22px;border:1px solid rgba(255,255,255,.15);border-radius:8px;padding:12px;display:flex;gap:10px;align-items:center;background:rgba(255,255,255,.06)}.sd-rail-user .sd-avatar{background:#dff5e8;color:#06451f}.sd-content{min-width:0}.sd-console-top{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:12px}.sd-search{flex:1;min-width:260px;border:1px solid var(--line);border-radius:8px;background:#fff;display:flex;align-items:center;gap:10px;padding:9px 12px;color:var(--muted)}.sd-search input{border:0;box-shadow:none;padding:0}.sd-search button{box-shadow:none;padding:8px 11px}.sd-search input:focus{box-shadow:none}.sd-toolstrip{display:flex;gap:10px;align-items:center;flex-wrap:wrap}.sd-tool{border:1px solid var(--line);border-radius:8px;background:#fff;padding:9px 11px;font-weight:850;color:#102033}.sd-shell{display:grid;gap:16px}.sd-top{display:flex;justify-content:space-between;align-items:end;gap:12px;flex-wrap:wrap}.sd-title h2{font-size:1.65rem;margin:0;color:#062b17}.sd-title p{margin:4px 0 0;color:var(--muted)}.sd-date{border:1px solid var(--line);border-radius:8px;padding:10px 12px;background:#fff;font-weight:800;color:#102033}.sd-kpis{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:12px}.sd-kpi{border:1px solid var(--line);border-radius:8px;background:#fff;padding:14px;min-height:108px;box-shadow:var(--shadow);display:flex;justify-content:space-between;gap:10px}.sd-kpi small{display:block;text-transform:uppercase;font-size:.72rem;font-weight:900;color:#536171}.sd-kpi strong{font-size:1.55rem;color:#101828}.sd-kpi span{display:block;color:#079455;font-size:.78rem;font-weight:850;margin-top:4px}.sd-icon{width:48px;height:48px;border-radius:50%;display:grid;place-items:center;background:#e8f5ed;color:#087443;font-size:1.25rem}.sd-icon.red{background:#fee4e2;color:#d92d20}.sd-icon.blue{background:#e8f1ff;color:#175cd3}.sd-icon.orange{background:#fff1df;color:#c05600}.sd-grid{display:grid;grid-template-columns:1.55fr 1fr 1fr;gap:14px}.sd-lower{display:grid;grid-template-columns:1fr 1.35fr 1fr 1fr 1fr;gap:14px}.sd-panel{border:1px solid var(--line);border-radius:8px;background:#fff;box-shadow:var(--shadow);padding:14px}.sd-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:10px}.sd-head h3{margin:0;color:#102033;font-size:1rem}.sd-head a{color:#0f6b3c;font-weight:900;font-size:.82rem;text-decoration:none}.sd-tabs{display:flex;gap:16px;align-items:center;border-bottom:1px solid var(--line);margin-bottom:10px;overflow:auto}.sd-tabs a{padding:9px 0;color:#536171;text-decoration:none;font-weight:850;font-size:.82rem;white-space:nowrap}.sd-tabs a.active{color:#0f6b3c;border-bottom:2px solid #0f6b3c}.sd-table{width:100%;border-collapse:collapse}.sd-table th,.sd-table td{padding:9px 8px;border-bottom:1px solid #edf1f4;text-align:left;font-size:.8rem;vertical-align:top}.sd-table th{font-size:.72rem;text-transform:uppercase;color:#667085}.sd-table strong{color:#102033}.sd-badge{display:inline-flex;border-radius:999px;padding:3px 7px;font-size:.7rem;font-weight:900}.sd-badge.ok{background:#dcfae6;color:#067647}.sd-badge.info{background:#dbeafe;color:#175cd3}.sd-badge.warn{background:#fef0c7;color:#b54708}.sd-badge.bad{background:#fee4e2;color:#b42318}.sd-badge.neutral{background:#f2f4f7;color:#475467}.sd-filter{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:8px}.sd-priorities{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:9px}.sd-priority{border:1px solid var(--line);border-radius:8px;overflow:hidden;background:#fff}.sd-priority h4{margin:0;padding:10px;font-size:.84rem}.sd-priority.high h4{background:#fff1f0;color:#b42318}.sd-priority.medium h4{background:#fff6e6;color:#b54708}.sd-priority.low h4{background:#edf8f0;color:#067647}.sd-mini-ticket{padding:10px;border-top:1px solid var(--line);font-size:.78rem}.sd-mini-ticket strong{display:block;color:#102033}.sd-mini-ticket span{display:block;color:var(--muted);margin-top:3px}.sd-chat{display:grid;gap:10px}.sd-chat-row{display:flex;gap:10px;border-bottom:1px solid #eef2f4;padding-bottom:10px}.sd-avatar{width:34px;height:34px;border-radius:50%;background:#e8f5ed;color:#0f6b3c;display:grid;place-items:center;font-weight:900;flex:0 0 auto}.sd-chat-row p{margin:3px 0 0;color:#344054;font-size:.82rem}.sd-reply textarea{min-height:72px}.sd-map{min-height:178px;border-radius:8px;background:linear-gradient(135deg,#edf7ef,#c6e6cf);display:grid;place-items:center;color:#0f6b3c;font-weight:900;margin:8px 0;position:relative;overflow:hidden}.sd-map:before{content:"";position:absolute;inset:22px 42px;background:rgba(15,107,60,.18);clip-path:polygon(42% 0,62% 10%,78% 26%,92% 50%,76% 78%,50% 100%,26% 86%,8% 62%,18% 32%);border:2px solid rgba(15,107,60,.24)}.sd-map span{position:relative}.sd-list{display:grid;gap:9px}.sd-list-row{display:flex;justify-content:space-between;gap:10px;border-bottom:1px solid #eef2f4;padding-bottom:8px;font-size:.83rem}.sd-list-row strong{color:#102033}.sd-list-row small{display:block;color:var(--muted);margin-top:2px}.sd-timeline{position:relative;display:grid;gap:11px}.sd-timeline-row{display:grid;grid-template-columns:28px 1fr;gap:8px}.sd-dot{width:24px;height:24px;border-radius:50%;background:#e8f5ed;color:#0f6b3c;display:grid;place-items:center;font-size:.72rem;font-weight:900}.sd-score{font-size:2.15rem;font-weight:950;color:#102033}.sd-bars{display:grid;gap:7px}.sd-bar{display:grid;grid-template-columns:46px 1fr 38px;gap:8px;align-items:center;font-size:.78rem}.sd-track{height:8px;border-radius:999px;background:#eef2f4;overflow:hidden}.sd-fill{height:100%;background:#0f6b3c}.sd-fill.orange{background:#f79009}.sd-fill.red{background:#d92d20}.sd-actions{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:14px}.sd-action{border:1px solid var(--line);border-radius:8px;background:#fff;padding:16px;display:flex;gap:12px;align-items:center;color:inherit;text-decoration:none}.sd-action i{width:36px;height:36px;border-radius:50%;display:grid;place-items:center;background:#e8f5ed;color:#0f6b3c}.sd-action strong{display:block;color:#102033}.sd-action small{color:var(--muted)}.support-work{display:grid;grid-template-columns:minmax(360px,520px) minmax(0,1fr);gap:14px}.conversation{display:grid;gap:10px;margin:14px 0}.msg{border:1px solid var(--line);border-radius:8px;padding:12px;background:#fbfdf9}.msg.agent{background:#eef7ff}.msg.internal{background:#fff7e8;border-color:#f5c56b}.detail-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin:12px 0}.detail{border:1px solid var(--line);border-radius:8px;padding:10px;background:#fff}.form-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}.span2{grid-column:span 2}.span4{grid-column:1/-1}@media(max-width:1350px){.sd-workspace{grid-template-columns:1fr}.sd-rail{position:relative;top:auto;min-height:auto}.sd-rail-nav{grid-template-columns:repeat(3,minmax(0,1fr))}.sd-kpis{grid-template-columns:repeat(3,minmax(0,1fr))}.sd-grid,.sd-lower,.support-work{grid-template-columns:1fr}.sd-actions{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:800px){.sd-rail-nav,.sd-kpis,.sd-priorities,.sd-filter,.sd-actions,.form-grid,.detail-grid{grid-template-columns:1fr}.span2,.span4{grid-column:auto}}',
]);
?>
<?php if ($message): ?><div class="notice ok"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>
<style>
  html, body, .admin-shell { width:100%; max-width:none; }
  body { overflow-x:hidden; }
  main.admin-main { max-width:none !important; width:100vw !important; margin:0 !important; padding:18px !important; }
  .sd-workspace { grid-template-columns:248px minmax(0,1fr) !important; width:100%; max-width:none; margin:0; }
  .sd-content, .sd-shell, .sd-panel { min-width:0; }
  .sd-kpis { grid-template-columns:repeat(6,minmax(120px,1fr)) !important; }
  .sd-kpi { min-height:130px !important; padding:12px !important; }
  .sd-kpi strong { font-size:clamp(1.05rem,1.6vw,1.45rem) !important; overflow-wrap:anywhere; }
  .sd-kpi span { font-size:.74rem !important; }
  .sd-grid { grid-template-columns:minmax(0,1.35fr) minmax(260px,.75fr) !important; }
  .sd-grid > .sd-panel:first-child { grid-column:1; grid-row:1 / span 2; }
  .sd-lower { grid-template-columns:repeat(auto-fit,minmax(220px,1fr)) !important; }
  .sd-filter { grid-template-columns:repeat(auto-fit,minmax(128px,1fr)) !important; align-items:end; }
  .sd-table { table-layout:auto; }
  .sd-table th, .sd-table td { overflow-wrap:anywhere; }
  .sd-priorities { grid-template-columns:1fr !important; }
  .support-work { grid-template-columns:minmax(260px,420px) minmax(0,1fr) !important; }
  .support-topbar { position:sticky; top:0; z-index:30; display:flex; align-items:center; justify-content:space-between; gap:14px; margin:-18px -18px 18px; padding:12px 18px; background:rgba(255,255,255,.96); border-bottom:1px solid rgba(16,24,40,.1); box-shadow:0 10px 28px rgba(16,24,40,.08); backdrop-filter:blur(12px); }
  .support-topbrand { display:flex; align-items:center; gap:11px; min-width:240px; color:#063f24; text-decoration:none; font-weight:950; }
  .support-topbrand img { width:42px; height:42px; border-radius:50%; border:1px solid var(--line); object-fit:contain; background:#fff; padding:3px; }
  .support-topbrand span { display:block; color:#5f6f63; font-size:.76rem; font-weight:800; margin-top:2px; }
  .support-topnav { display:flex; align-items:center; justify-content:flex-end; gap:8px; flex-wrap:wrap; }
  .support-toplink, .support-menu summary { display:inline-flex; align-items:center; gap:7px; min-height:38px; padding:9px 11px; border-radius:8px; border:1px solid var(--line); background:#fff; color:#102033; font-weight:900; text-decoration:none; box-shadow:none; cursor:pointer; }
  .support-toplink.primary { background:#067647; color:#fff; border-color:#067647; }
  .support-toplink:hover, .support-menu summary:hover { background:#eef8f0; color:#063f24; text-decoration:none; }
  .support-toplink.primary:hover { background:#005b32; color:#fff; }
  .support-menu { position:relative; }
  .support-menu summary { list-style:none; }
  .support-menu summary::-webkit-details-marker { display:none; }
  .support-menu summary:before { content:""; width:15px; height:11px; background:linear-gradient(#102033,#102033) 0 0/100% 2px no-repeat,linear-gradient(#102033,#102033) 0 50%/100% 2px no-repeat,linear-gradient(#102033,#102033) 0 100%/100% 2px no-repeat; }
  .support-menu[open] summary { background:#eef8f0; color:#063f24; }
  .support-menu-panel { position:absolute; left:0; top:calc(100% + 8px); width:min(320px,calc(100vw - 28px)); display:grid; gap:5px; padding:10px; border:1px solid rgba(16,24,40,.12); border-radius:8px; background:#fff; box-shadow:0 22px 42px rgba(16,24,40,.18); }
  .support-menu-panel a { display:flex; align-items:center; justify-content:space-between; gap:10px; padding:10px 11px; border-radius:7px; color:#102033; text-decoration:none; font-weight:850; }
  .support-menu-panel a:hover { background:#f1faf5; color:#063f24; }
  .support-menu-panel small { color:var(--muted); font-weight:750; }
  @media (max-width:980px) {
    main.admin-main { padding:12px !important; width:100% !important; }
    .support-topbar { margin:-12px -12px 12px; align-items:flex-start; flex-direction:column; }
    .support-topbrand { min-width:0; }
    .support-topnav { width:100%; justify-content:flex-start; }
    .sd-kpis { grid-template-columns:repeat(auto-fit,minmax(150px,1fr)) !important; }
    .sd-workspace, .sd-grid, .support-work { grid-template-columns:1fr !important; }
    .sd-grid > .sd-panel:first-child { grid-column:auto; grid-row:auto; }
  }
</style>
<script>
  document.addEventListener('DOMContentLoaded', () => {
    const main = document.querySelector('main.admin-main');
    if (main) {
      main.style.maxWidth = 'none';
      main.style.width = '100vw';
      main.style.margin = '0';
    }
  });
</script>

<header class="support-topbar" aria-label="Support workspace top bar">
  <a class="support-topbrand" href="index.php">
    <img src="<?= e(app_primary_logo_url()) ?>" alt="NATCODEV">
    <strong>NATCODEV Support<span>Admin support workspace</span></strong>
  </a>
  <nav class="support-topnav" aria-label="Support quick navigation">
    <details class="support-menu">
      <summary>Menu</summary>
      <div class="support-menu-panel">
        <a href="index.php">Admin Workspace <small>Dashboard</small></a>
        <a href="../index.php">Main Site <small>Public entry</small></a>
        <a href="../support/index.php">Public Support <small>Ticket form</small></a>
        <a href="support.php#ticket-queue">Ticket Queue <small><?= (int) $totalTickets ?></small></a>
        <a href="support.php?status=active&scope=unassigned#ticket-queue">Unassigned <small><?= $unassignedCount ?></small></a>
        <a href="support.php?status=active&scope=assigned#ticket-queue">Assigned To Me <small><?= $myAssignedCount ?></small></a>
        <a href="support.php?status=escalated&scope=all#ticket-queue">Escalations <small><?= (int) ($stats['overdue_count'] ?? 0) ?></small></a>
        <a href="reports.php?report=support">SLA Reports <small>Analytics</small></a>
        <a href="settings.php">Settings <small>Admin</small></a>
      </div>
    </details>
    <a class="support-toplink primary" href="index.php">Workspace</a>
    <a class="support-toplink" href="../index.php">Main Site</a>
    <a class="support-toplink" href="../support/index.php">Public Support</a>
    <a class="support-toplink" href="admin.php?logout=1">Logout</a>
  </nav>
</header>

<div class="sd-workspace">
  <aside class="sd-rail" aria-label="Support desk workspace navigation">
    <div class="sd-rail-brand">
      <img src="<?= e(app_primary_logo_url()) ?>" alt="NATCODEV">
      <div><strong>NATCODEV</strong><small>Support Desk Workspace</small></div>
    </div>
    <div class="sd-rail-label">Support Desk Workspace</div>
    <nav class="sd-rail-nav">
      <a class="<?= $filterStatus === 'active' && $filterCategory === '' && $filterScope === 'all' && $filterQ === '' ? 'active' : '' ?>" href="support.php"><span><i class="fa-solid fa-table-columns"></i> Overview</span></a>
      <a href="#ticket-workbench"><span><i class="fa-solid fa-ticket"></i> Tickets</span><span class="sd-rail-count warn"><?= (int) ($stats['open_count'] ?? 0) ?></span></a>
      <a class="<?= $filterScope === 'unassigned' ? 'active' : '' ?>" href="<?= e(sd_url(['status' => 'active', 'scope' => 'unassigned', 'page' => 1])) ?>"><span><i class="fa-regular fa-circle-question"></i> Unassigned</span><span class="sd-rail-count"><?= $unassignedCount ?></span></a>
      <a class="<?= $filterScope === 'assigned' ? 'active' : '' ?>" href="<?= e(sd_url(['status' => 'active', 'scope' => 'assigned', 'page' => 1])) ?>"><span><i class="fa-solid fa-user-check"></i> Assigned To Me</span><span class="sd-rail-count"><?= $myAssignedCount ?></span></a>
      <a class="<?= $filterStatus === 'escalated' ? 'active' : '' ?>" href="<?= e(sd_url(['status' => 'escalated', 'scope' => 'all', 'page' => 1])) ?>"><span><i class="fa-solid fa-triangle-exclamation"></i> Escalations</span><span class="sd-rail-count bad"><?= (int) ($stats['overdue_count'] ?? 0) ?></span></a>
      <a href="../support/index.php#knowledge"><span><i class="fa-solid fa-book-open"></i> Knowledge Base</span></a>
      <a href="#ticket-workbench"><span><i class="fa-regular fa-message"></i> Messages</span><span class="sd-rail-count"><?= count($timelineRows) ?></span></a>
      <a class="<?= $filterCategory === 'general' ? 'active' : '' ?>" href="<?= e(sd_url(['category' => 'general', 'page' => 1])) ?>"><span><i class="fa-solid fa-bullhorn"></i> Complaints</span><span class="sd-rail-count warn"><?= (int) ($stats['complaint_count'] ?? 0) ?></span></a>
      <a class="<?= $filterCategory === 'field' ? 'active' : '' ?>" href="<?= e(sd_url(['category' => 'field', 'page' => 1])) ?>"><span><i class="fa-solid fa-location-dot"></i> Field Issues</span><span class="sd-rail-count"><?= (int) ($stats['field_count'] ?? 0) ?></span></a>
      <a href="reports.php?report=support"><span><i class="fa-solid fa-chart-line"></i> SLA Reports</span></a>
      <a href="settings.php"><span><i class="fa-solid fa-gear"></i> Settings</span></a>
    </nav>
    <div class="sd-rail-label">Quick Actions</div>
    <nav class="sd-rail-nav">
      <a href="../support/index.php"><span><i class="fa-solid fa-circle-plus"></i> New Ticket</span></a>
      <a href="#ticket-workbench"><span><i class="fa-solid fa-user-plus"></i> Assign Agent</span></a>
      <a href="<?= e(sd_url(['status' => 'escalated', 'scope' => 'all', 'page' => 1])) ?>"><span><i class="fa-solid fa-arrow-up"></i> Escalate Ticket</span></a>
      <a href="../support/index.php#knowledge"><span><i class="fa-solid fa-book"></i> Publish FAQ</span></a>
      <a href="reports.php?report=support"><span><i class="fa-solid fa-download"></i> Export SLA Report</span></a>
    </nav>
    <div class="sd-rail-user">
      <div class="sd-avatar"><?= e(strtoupper(substr((string) ($admin['name'] ?? 'A'), 0, 1))) ?></div>
      <div><strong><?= e((string) ($admin['name'] ?? 'Admin')) ?></strong><small>Support Admin / Online</small></div>
    </div>
  </aside>

  <div class="sd-content">
    <div class="sd-console-top">
      <form class="sd-search" method="get" action="support.php">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="hidden" name="status" value="<?= e($filterStatus) ?>">
        <input type="hidden" name="category" value="<?= e($filterCategory) ?>">
        <input type="hidden" name="priority" value="<?= e($filterPriority) ?>">
        <input type="hidden" name="scope" value="<?= e($filterScope) ?>">
        <input type="search" name="q" value="<?= e($filterQ) ?>" placeholder="Search tickets, users, topics, or reference..." aria-label="Search support desk">
        <button type="submit">Search</button>
      </form>
      <div class="sd-toolstrip">
        <span class="sd-tool"><i class="fa-regular fa-bell"></i> <?= (int) ($stats['overdue_count'] ?? 0) ?></span>
        <span class="sd-tool"><i class="fa-regular fa-envelope"></i> <?= count($timelineRows) ?></span>
        <span class="sd-tool">Support Desk</span>
      </div>
    </div>

<div class="sd-shell">
  <div class="sd-top">
    <div class="sd-title">
      <h2>NATCODEV Support Desk</h2>
      <p>Deliver fast, empathetic, and effective support across all user groups and channels.</p>
    </div>
    <div class="sd-date"><i class="fa-regular fa-calendar"></i> <?= e(date('M j')) ?> - <?= e(date('M j, Y', strtotime('+6 days'))) ?></div>
  </div>

  <section class="sd-kpis">
    <div class="sd-kpi"><div><small>Open Tickets</small><strong><?= (int) ($stats['open_count'] ?? 0) ?></strong><span>Active support load</span></div><div class="sd-icon"><i class="fa-solid fa-headset"></i></div></div>
    <div class="sd-kpi"><div><small>Overdue SLA</small><strong><?= (int) ($stats['overdue_count'] ?? 0) ?></strong><span>Needs immediate action</span></div><div class="sd-icon red"><i class="fa-solid fa-triangle-exclamation"></i></div></div>
    <div class="sd-kpi"><div><small>Resolved Today</small><strong><?= (int) ($stats['resolved_today'] ?? 0) ?></strong><span>Closed with outcome</span></div><div class="sd-icon"><i class="fa-solid fa-circle-check"></i></div></div>
    <div class="sd-kpi"><div><small>Field Issues</small><strong><?= (int) ($stats['field_count'] ?? 0) ?></strong><span>Farm and visit support</span></div><div class="sd-icon blue"><i class="fa-solid fa-location-dot"></i></div></div>
    <div class="sd-kpi"><div><small>Complaints</small><strong><?= (int) ($stats['complaint_count'] ?? 0) ?></strong><span>Public and user concerns</span></div><div class="sd-icon orange"><i class="fa-solid fa-bullhorn"></i></div></div>
    <div class="sd-kpi"><div><small>Avg Response Time</small><strong><?= e(sd_minutes_label($stats['avg_response_minutes'] !== null ? (int) $stats['avg_response_minutes'] : null)) ?></strong><span>First agent response</span></div><div class="sd-icon"><i class="fa-solid fa-stopwatch"></i></div></div>
  </section>

  <section class="sd-grid">
    <div class="sd-panel" id="ticket-queue">
      <div class="sd-head"><h3>Ticket Queue</h3><a href="support.php#ticket-queue">View All</a></div>
      <form method="get" class="sd-filter">
        <select name="status"><option value="active">All Active</option><?php foreach ($statuses as $key => $label): ?><option value="<?= e($key) ?>" <?= $filterStatus === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
        <select name="category"><option value="">All Categories</option><?php foreach ($categories as $key => $cat): ?><option value="<?= e($key) ?>" <?= $filterCategory === $key ? 'selected' : '' ?>><?= e($cat['label']) ?></option><?php endforeach; ?></select>
        <select name="priority"><option value="">All Priorities</option><?php foreach ($priorities as $key => $label): ?><option value="<?= e($key) ?>" <?= $filterPriority === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
        <select name="scope">
          <option value="all" <?= $filterScope === 'all' ? 'selected' : '' ?>>All Ownership</option>
          <option value="unassigned" <?= $filterScope === 'unassigned' ? 'selected' : '' ?>>Unassigned</option>
          <option value="groups" <?= $filterScope === 'groups' ? 'selected' : '' ?>>Assigned Teams</option>
          <option value="assigned" <?= $filterScope === 'assigned' ? 'selected' : '' ?>>Assigned To Me</option>
        </select>
        <input type="search" name="q" value="<?= e($filterQ) ?>" placeholder="Search">
        <button class="btn" type="submit">Filter</button>
      </form>
      <div class="sd-tabs">
        <a class="<?= $filterScope === 'all' ? 'active' : '' ?>" href="<?= e(sd_url(['status' => 'active', 'scope' => 'all', 'page' => 1])) ?>">All Tickets <span class="sd-badge ok"><?= (int) ($stats['open_count'] ?? 0) ?></span></a>
        <a class="<?= $filterScope === 'unassigned' ? 'active' : '' ?>" href="<?= e(sd_url(['status' => 'active', 'scope' => 'unassigned', 'page' => 1])) ?>">Unassigned <span class="sd-badge neutral"><?= $unassignedCount ?></span></a>
        <a class="<?= $filterScope === 'groups' ? 'active' : '' ?>" href="<?= e(sd_url(['status' => 'active', 'scope' => 'groups', 'page' => 1])) ?>">My Groups <span class="sd-badge info"><?= $myGroupsCount ?></span></a>
        <a class="<?= $filterScope === 'assigned' ? 'active' : '' ?>" href="<?= e(sd_url(['status' => 'active', 'scope' => 'assigned', 'page' => 1])) ?>">Assigned To Me <span class="sd-badge ok"><?= $myAssignedCount ?></span></a>
      </div>
      <table class="sd-table">
        <thead><tr><th>ID</th><th>Subject</th><th>Requester</th><th>Role</th><th>Category</th><th>Priority</th><th>Status</th><th>SLA</th></tr></thead>
        <tbody>
        <?php foreach ($tickets as $ticket): ?>
          <tr>
            <td><a href="<?= e(sd_url(['ticket' => (string) $ticket['ticket_ref']])) ?>"><?= e((string) $ticket['ticket_ref']) ?></a></td>
            <td><strong><?= e((string) $ticket['subject']) ?></strong></td>
            <td><?= e((string) $ticket['requester_name']) ?></td>
            <td><?= e(support_role_label((string) $ticket['requester_role'])) ?></td>
            <td><?= e($categories[(string) $ticket['category']]['label'] ?? (string) $ticket['category']) ?></td>
            <td><span class="sd-badge <?= e(support_badge_class((string) $ticket['priority'])) ?>"><?= e($priorities[(string) $ticket['priority']] ?? (string) $ticket['priority']) ?></span></td>
            <td><span class="sd-badge <?= e(support_badge_class((string) $ticket['status'])) ?>"><?= e($statuses[(string) $ticket['status']] ?? (string) $ticket['status']) ?></span></td>
            <td><?= e(sd_short_date((string) ($ticket['sla_due_at'] ?? ''))) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php if (!$tickets): ?><p class="empty">No tickets match these filters.</p><?php endif; ?>
      <?= admin_pagination_controls($totalTickets, $page, $perPage, ['status' => $filterStatus, 'category' => $filterCategory, 'priority' => $filterPriority, 'scope' => $filterScope, 'q' => $filterQ]) ?>
    </div>

    <div class="sd-panel">
      <div class="sd-head"><h3>Priority Triage Board</h3><a href="<?= e(sd_url(['status' => 'active', 'page' => 1])) ?>">Refresh</a></div>
      <div class="sd-priorities">
        <?php foreach (['high' => 'High', 'medium' => 'Medium', 'low' => 'Low'] as $priorityKey => $priorityLabel): ?>
          <div class="sd-priority <?= e($priorityKey) ?>">
            <h4><?= e($priorityLabel) ?> (<?= (int) ($stats[$priorityKey . '_count'] ?? 0) ?>)</h4>
            <?php foreach ($priorityBoards[$priorityKey] as $row): ?>
              <div class="sd-mini-ticket">
                <strong><?= e((string) $row['ticket_ref']) ?></strong>
                <?= e((string) $row['subject']) ?>
                <span><?= e((string) $row['requester_name']) ?> / SLA <?= e(sd_short_date((string) ($row['sla_due_at'] ?? ''))) ?></span>
              </div>
            <?php endforeach; ?>
            <?php if (!$priorityBoards[$priorityKey]): ?><div class="sd-mini-ticket"><span>No active <?= e(strtolower($priorityLabel)) ?> priority tickets.</span></div><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="sd-panel">
      <div class="sd-head"><h3>Conversation Preview</h3><a href="#ticket-workbench">Open Workbench</a></div>
      <?php if ($selected): ?>
        <p><strong><?= e((string) $selected['ticket_ref']) ?></strong> - <?= e((string) $selected['subject']) ?> <span class="sd-badge <?= e(support_badge_class((string) $selected['status'])) ?>"><?= e($statuses[(string) $selected['status']] ?? (string) $selected['status']) ?></span></p>
        <div class="sd-chat">
          <?php foreach (array_slice($conversation, -3) as $msg): ?>
            <div class="sd-chat-row">
              <div class="sd-avatar"><?= e(strtoupper(substr((string) $msg['author_name'], 0, 1))) ?></div>
              <div><strong><?= e((string) $msg['author_name']) ?></strong> <span class="meta"><?= e(sd_when((string) $msg['created_at'])) ?></span><p><?= e(mb_substr((string) $msg['message'], 0, 140)) ?></p></div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="empty">Select a ticket to preview the conversation.</p>
      <?php endif; ?>
    </div>
  </section>

  <section class="sd-lower">
    <div class="sd-panel">
      <div class="sd-head"><h3>Knowledge Base Suggestions</h3><a href="../support/index.php">Public Help</a></div>
      <div class="sd-list">
        <?php foreach ($knowledgeRows as $row): $cat = $categories[(string) $row['category']] ?? $categories['general']; ?>
          <div class="sd-list-row"><div><strong><?= e((string) $cat['label']) ?></strong><small>Last support activity <?= e(sd_short_date((string) $row['last_seen'])) ?></small></div><strong><?= (int) $row['total'] ?></strong></div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="sd-panel">
      <div class="sd-head"><h3>Field Issues Overview</h3><a href="<?= e(sd_url(['category' => 'field', 'page' => 1])) ?>">Map View</a></div>
      <div class="sd-map"><span>Nigeria Field Support Heatmap</span></div>
      <div class="sd-list-row"><div><strong>Total Open Field Issues</strong><small>Farm visits, evidence, and field operations</small></div><strong><?= (int) ($stats['field_count'] ?? 0) ?></strong></div>
      <?php foreach ($fieldRows as $row): ?><div class="sd-list-row"><span><?= e((string) ($row['linked_record_ref'] ?: 'Unlinked field request')) ?></span><strong><?= (int) $row['total'] ?></strong></div><?php endforeach; ?>
    </div>
    <div class="sd-panel">
      <div class="sd-head"><h3>Escalation Queue</h3><a href="<?= e(sd_url(['status' => 'escalated', 'scope' => 'all', 'page' => 1])) ?>">View All</a></div>
      <div class="sd-list">
        <?php foreach ($escalationRows as $row): ?>
          <div class="sd-list-row"><div><strong><?= e((string) $row['ticket_ref']) ?></strong><small><?= e((string) $row['subject']) ?> / <?= e((string) $row['requester_name']) ?></small></div><span class="sd-badge <?= e(support_badge_class((string) $row['priority'])) ?>"><?= e(ucfirst((string) $row['priority'])) ?></span></div>
        <?php endforeach; ?>
        <?php if (!$escalationRows): ?><p class="empty">No escalated or overdue tickets right now.</p><?php endif; ?>
      </div>
    </div>
    <div class="sd-panel">
      <div class="sd-head"><h3>Communication Timeline</h3><a href="#ticket-workbench">View</a></div>
      <div class="sd-timeline">
        <?php foreach ($timelineRows as $row): ?>
          <div class="sd-timeline-row"><div class="sd-dot"><i class="fa-solid fa-message"></i></div><div><strong><?= e((string) $row['author_name']) ?></strong> <span class="meta"><?= e(sd_when((string) $row['created_at'])) ?></span><small class="meta"><?= e((string) $row['ticket_ref']) ?> - <?= e(mb_substr((string) $row['message'], 0, 80)) ?></small></div></div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="sd-panel">
      <div class="sd-head"><h3>Customer Satisfaction</h3><a href="reports.php?report=support">This Week</a></div>
      <div class="sd-score"><?= number_format($satisfactionScore, 1) ?></div>
      <div class="sd-bars">
        <div class="sd-bar"><span>5 Star</span><div class="sd-track"><div class="sd-fill" style="width:68%"></div></div><span>68%</span></div>
        <div class="sd-bar"><span>4 Star</span><div class="sd-track"><div class="sd-fill" style="width:22%"></div></div><span>22%</span></div>
        <div class="sd-bar"><span>3 Star</span><div class="sd-track"><div class="sd-fill orange" style="width:7%"></div></div><span>7%</span></div>
        <div class="sd-bar"><span>1-2</span><div class="sd-track"><div class="sd-fill red" style="width:3%"></div></div><span>3%</span></div>
      </div>
      <p><span class="sd-badge ok">Quick Response</span> <span class="sd-badge ok">Resolved</span> <span class="sd-badge warn">Wait Time</span></p>
    </div>
  </section>

  <section class="sd-panel" id="ticket-workbench">
    <div class="sd-head"><h3>Ticket Workbench</h3><a href="../support/index.php" target="_blank">Public Support Entry</a></div>
    <div class="support-work">
      <aside>
        <?php foreach ($tickets as $ticket): ?>
          <a class="sd-action" href="<?= e(sd_url(['ticket' => (string) $ticket['ticket_ref']])) ?>">
            <i class="fa-solid fa-ticket"></i>
            <span><strong><?= e((string) $ticket['ticket_ref']) ?></strong><small><?= e((string) $ticket['subject']) ?> / <?= e($statuses[(string) $ticket['status']] ?? (string) $ticket['status']) ?></small></span>
          </a>
        <?php endforeach; ?>
      </aside>
      <section>
        <?php if ($selected): ?>
          <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
            <div><h2><?= e((string) $selected['subject']) ?></h2><p class="meta"><?= e((string) $selected['ticket_ref']) ?> / <?= e($categories[(string) $selected['category']]['label'] ?? (string) $selected['category']) ?> / <?= e((string) $selected['module']) ?></p></div>
            <a class="btn light" href="../support/index.php?ticket=<?= e((string) $selected['ticket_ref']) ?>&email=<?= e((string) $selected['requester_email']) ?>" target="_blank">Public View</a>
          </div>
          <div class="detail-grid">
            <div class="detail"><strong>Requester</strong><br><?= e((string) $selected['requester_name']) ?><br><span class="meta"><?= e((string) $selected['requester_email']) ?> / <?= e(support_role_label((string) $selected['requester_role'])) ?></span></div>
            <div class="detail"><strong>Linked Record</strong><br><?= e((string) ($selected['linked_record_type'] ?: 'None')) ?><br><span class="meta"><?= e((string) ($selected['linked_record_ref'] ?: 'No reference')) ?></span></div>
            <div class="detail"><strong>Team / SLA</strong><br><?= e((string) ($selected['assigned_team'] ?: 'Unassigned')) ?><br><span class="meta"><?= e(sd_short_date((string) ($selected['sla_due_at'] ?? ''))) ?></span></div>
          </div>

          <div class="conversation">
            <?php foreach ($conversation as $msg): ?><div class="msg <?= $msg['visibility'] === 'internal' ? 'internal' : ($msg['admin_id'] ? 'agent' : '') ?>"><strong><?= e((string) $msg['author_name']) ?></strong> <span class="meta"><?= e((string) $msg['author_role']) ?> / <?= e(date('M j, Y g:i A', strtotime((string) $msg['created_at']))) ?></span><p><?= nl2br(e((string) $msg['message'])) ?></p></div><?php endforeach; ?>
          </div>

          <form method="post">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="ticket_ref" value="<?= e((string) $selected['ticket_ref']) ?>">
            <div class="form-grid">
              <div><label>Status</label><select name="status"><?php foreach ($statuses as $key => $label): ?><option value="<?= e($key) ?>" <?= (string) $selected['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
              <div><label>Priority</label><select name="priority"><?php foreach ($priorities as $key => $label): ?><option value="<?= e($key) ?>" <?= (string) $selected['priority'] === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
              <div><label>Assigned Team</label><select name="assigned_team"><?php foreach ($teams as $team): ?><option value="<?= e($team) ?>" <?= (string) $selected['assigned_team'] === $team ? 'selected' : '' ?>><?= e($team) ?></option><?php endforeach; ?></select></div>
              <div><label>Outcome</label><select name="outcome"><option value="">No final outcome</option><?php foreach ($outcomes as $key => $label): ?><option value="<?= e($key) ?>" <?= (string) ($selected['outcome'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
              <div class="span2"><label>Reply to requester</label><textarea name="reply" rows="5" placeholder="Visible to the requester."></textarea></div>
              <div class="span2"><label>Internal note</label><textarea name="internal_note" rows="5" placeholder="Visible only to platform admins/support agents."></textarea></div>
              <div class="span4"><button class="btn" type="submit">Update Ticket</button></div>
            </div>
          </form>
        <?php else: ?>
          <p class="empty">Select a ticket to manage.</p>
        <?php endif; ?>
      </section>
    </div>
  </section>

  <section class="sd-actions">
    <a class="sd-action" href="../support/index.php"><i class="fa-solid fa-circle-plus"></i><span><strong>New Ticket</strong><small>Create a new support ticket</small></span></a>
    <a class="sd-action" href="#ticket-workbench"><i class="fa-solid fa-user-check"></i><span><strong>Assign Agent</strong><small>Assign ticket to an agent</small></span></a>
    <a class="sd-action" href="<?= e(sd_url(['status' => 'escalated', 'scope' => 'all', 'page' => 1])) ?>"><i class="fa-solid fa-arrow-up"></i><span><strong>Escalate Ticket</strong><small>Move ticket to escalation</small></span></a>
    <a class="sd-action" href="../support/index.php#knowledge"><i class="fa-solid fa-book-open"></i><span><strong>Publish FAQ</strong><small>Add to knowledge base</small></span></a>
    <a class="sd-action" href="reports.php?report=support"><i class="fa-solid fa-download"></i><span><strong>Export SLA Report</strong><small>Download support analytics</small></span></a>
  </section>
</div>
  </div>
</div>
<?php admin_page_end(); ?>
