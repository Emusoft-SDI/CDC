<?php
declare(strict_types=1);

if (!defined('NATCODEV_SUPPORT_WORKSPACE') && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $query = $_GET;
    $query['page'] = preg_replace('/[^a-z-]/', '', (string) ($query['view'] ?? 'overview')) ?: 'overview';
    unset($query['view']);
    header('Location: support/?' . http_build_query($query), true, 302);
    exit;
}

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
$defaultTeams = array_values(array_unique(array_map(static fn(array $row): string => (string) $row['team'], $categories)));
$customTeamRows = app_table_exists($pdo, 'support_teams') ? $pdo->query("SELECT st.*, u.name lead_name FROM support_teams st LEFT JOIN users u ON u.id=st.lead_admin_id ORDER BY st.status='active' DESC, st.team_name ASC")->fetchAll() : [];
$teams = array_values(array_unique(array_merge($defaultTeams, array_map(static fn(array $row): string => (string) $row['team_name'], $customTeamRows))));
sort($teams);
$supportAdmins = app_table_exists($pdo, 'users') ? $pdo->query("SELECT id, name, email, role, platform_role FROM users WHERE role IN ('admin','support','field_agent') OR platform_role IN ('admin','support_agent','national_coordinator','state_coordinator') OR COALESCE(is_super_admin,0)=1 ORDER BY name ASC LIMIT 200")->fetchAll() : [];
$supportAdminIds = array_map(static fn(array $agent): int => (int) $agent['id'], $supportAdmins);

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

function sd_agent_label(array $agents, ?int $agentId): string
{
    if (!$agentId) {
        return 'Unassigned';
    }
    foreach ($agents as $agent) {
        if ((int) ($agent['id'] ?? 0) === $agentId) {
            return (string) (($agent['name'] ?? '') ?: ($agent['email'] ?? 'Support agent'));
        }
    }
    return 'Unassigned';
}

function sd_url(array $params = []): string
{
    $query = [];
    foreach ($params as $key => $value) {
        if ($value === '' || $value === null) {
            continue;
        }
        $query[$key] = $value;
    }
    $base = defined('NATCODEV_SUPPORT_WORKSPACE') ? 'index.php' : 'support/index.php';
    return $base . ($query ? '?' . http_build_query($query) : '');
}

function sd_ticket_url(string $ticketRef): string
{
    $ticketRef = preg_replace('/[^A-Z0-9-]/i', '', $ticketRef);
    return sd_url(['ticket' => $ticketRef]);
}
function sd_pagination_controls(int $total, int $page, int $perPage, array $extra = []): string
{
    $pages = max(1, (int) ceil($total / max(1, $perPage)));
    $page = min(max(1, $page), $pages);
    $base = $extra;
    unset($base['page'], $base['per_page']);
    $from = $total === 0 ? 0 : (($page - 1) * $perPage) + 1;
    $to = min($total, $page * $perPage);
    $prev = sd_url($base + ['page' => max(1, $page - 1), 'per_page' => $perPage]);
    $next = sd_url($base + ['page' => min($pages, $page + 1), 'per_page' => $perPage]);
    $html = '<form class="pagination" method="get" action="' . e(sd_url([])) . '">';
    foreach ($base as $key => $value) {
        if (is_scalar($value)) {
            $html .= '<input type="hidden" name="' . e((string) $key) . '" value="' . e((string) $value) . '">';
        }
    }
    $html .= '<div class="meta">Showing ' . (int) $from . '-' . (int) $to . ' of ' . (int) $total . '</div>';
    $html .= '<div class="pagination-links"><a class="button secondary" href="' . e($prev) . '" aria-disabled="' . ($page <= 1 ? 'true' : 'false') . '">Previous</a><span class="meta">Page ' . (int) $page . ' of ' . (int) $pages . '</span><a class="button secondary" href="' . e($next) . '" aria-disabled="' . ($page >= $pages ? 'true' : 'false') . '">Next</a></div>';
    $html .= '<label class="pagination-size">Rows <select name="per_page" onchange="this.form.page.value=\'1\'; this.form.submit()">';
    foreach ([10, 25, 50, 100, 200, 500] as $size) {
        $html .= '<option value="' . $size . '"' . ($perPage === $size ? ' selected' : '') . '>' . $size . '</option>';
    }
    $html .= '</select></label><input type="hidden" name="page" value="' . (int) $page . '"></form>';
    return $html;
}

$message = '';
$error = '';
$selectedRef = preg_replace('/[^A-Z0-9-]/i', '', (string) ($_GET['ticket'] ?? ''));
$filterStatus = preg_replace('/[^a-z_]/i', '', (string) ($_GET['status'] ?? 'active'));
$filterCategory = preg_replace('/[^a-z0-9_-]/i', '', (string) ($_GET['category'] ?? ''));
$filterPriority = preg_replace('/[^a-z]/i', '', (string) ($_GET['priority'] ?? ''));
$filterScope = preg_replace('/[^a-z_]/i', '', (string) ($_GET['scope'] ?? 'all'));
$filterQ = trim((string) ($_GET['q'] ?? ''));
$workspaceView = preg_replace('/[^a-z_-]/', '', (string) ($_GET['view'] ?? $_GET['page_view'] ?? 'overview')) ?: 'overview';
if (!in_array($filterScope, ['all', 'unassigned', 'groups', 'assigned'], true)) {
    $filterScope = 'all';
}
if ($workspaceView === 'assigned') {
    $filterScope = 'assigned';
    $filterStatus = 'active';
} elseif ($workspaceView === 'escalations') {
    $filterStatus = 'escalated';
} elseif ($workspaceView === 'complaints') {
    $filterCategory = 'general';
    $filterStatus = 'active';
} elseif ($workspaceView === 'field') {
    $filterCategory = 'field';
    $filterStatus = 'active';
}
$page = admin_current_page();
$perPage = admin_per_page(8);
$offset = admin_pagination_offset($page, $perPage);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid security token.';
    } else {
        try {
            $action = (string) ($_POST['action'] ?? 'update_ticket');
            if ($action === 'create_team') {
                $teamName = trim((string) ($_POST['team_name'] ?? ''));
                if ($teamName === '') {
                    throw new RuntimeException('Support team name is required.');
                }
                $teamStatus = in_array((string) ($_POST['status'] ?? 'active'), ['active', 'paused'], true) ? (string) $_POST['status'] : 'active';
                $leadAdminId = ((int) ($_POST['lead_admin_id'] ?? 0)) ?: null;
                $pdo->prepare("
                    INSERT INTO support_teams (team_name, module, description, lead_admin_id, status, created_by)
                    VALUES (?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE module=VALUES(module), description=VALUES(description), lead_admin_id=VALUES(lead_admin_id), status=VALUES(status)
                ")->execute([
                    $teamName,
                    trim((string) ($_POST['module'] ?? 'general')) ?: 'general',
                    trim((string) ($_POST['description'] ?? '')),
                    $leadAdminId,
                    $teamStatus,
                    (int) ($admin['id'] ?? 0),
                ]);
                $message = 'Support team saved.';
                $workspaceView = 'teams';
            } else {
            $selectedRef = preg_replace('/[^A-Z0-9-]/i', '', (string) ($_POST['ticket_ref'] ?? ''));
            $ticket = support_ticket_by_ref($pdo, $selectedRef);
            if (!$ticket) {
                throw new RuntimeException('Ticket not found.');
            }

            $status = (string) ($_POST['status'] ?? $ticket['status']);
            $priority = (string) ($_POST['priority'] ?? $ticket['priority']);
            $outcome = trim((string) ($_POST['outcome'] ?? '')) ?: null;
            $team = trim((string) ($_POST['assigned_team'] ?? $ticket['assigned_team'])) ?: null;
            $assignedAdminId = array_key_exists('assigned_admin_id', $_POST)
                ? (((int) $_POST['assigned_admin_id']) ?: null)
                : (((int) ($ticket['assigned_admin_id'] ?? 0)) ?: null);
            if ($assignedAdminId !== null && !in_array($assignedAdminId, $supportAdminIds, true)) {
                throw new RuntimeException('Select a valid support agent.');
            }
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
                $assignedAdminId,
                $reply !== '' ? 1 : 0,
                $resolved ? 1 : 0,
                $resolved ? 1 : 0,
                (int) $ticket['id'],
            ]);

            if ($status === 'resolved' && (string) $ticket['status'] !== 'resolved') {
                $feedbackUrl = app_base_url() . '/support/index.php?ticket=' . urlencode($selectedRef) . '&email=' . urlencode((string) $ticket['requester_email']);
                $subject = "Your NATCODEV Support Ticket {$selectedRef} has been resolved";
                $plain = "Dear " . $ticket['requester_name'] . ",\n\nYour support ticket (Ref: " . $selectedRef . ") has been marked as resolved.\n\nPlease rate your experience with our support representative by clicking the link below:\n" . $feedbackUrl . "\n\nThank you for choosing NATCODEV.";
                $html = "<h3>Your support ticket has been resolved</h3><p>Dear " . e($ticket['requester_name']) . ",</p><p>Your support ticket (Ref: <strong>" . e($selectedRef) . "</strong>) has been marked as resolved.</p><p>Please take a moment to rate your experience with our support representative by clicking the link below:</p><p><a href=\"" . e($feedbackUrl) . "\" style=\"display:inline-block;padding:10px 20px;background:#075f2a;color:#fff;text-decoration:none;border-radius:5px;\">Rate Support Representative</a></p><p>Thank you for choosing NATCODEV.</p>";
                app_send_mail((string) $ticket['requester_email'], $subject, $plain, $html);
            }

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
            }
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
define('NATCODEV_SUPPORT_WORKSPACE_VIEW', true);
require __DIR__ . '/support/views/workspace.php';
