<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/support.php';
require_once __DIR__ . '/../lib/user-workspaces.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$pdo = db();
support_ensure_schema($pdo);
$user = current_user($pdo);
if (!$user || !app_user_has_any_role($pdo, $user, ['support_agent', 'admin', 'super_admin'])) {
    redirect_to('../login.php?next=' . urlencode('support/agent.php'));
}

$agentId = (int) $user['id'];
$statuses = support_statuses();
$categories = support_categories();
$error = '';
$message = '';
$selectedId = max(0, (int) ($_GET['ticket_id'] ?? $_POST['ticket_id'] ?? 0));

function sa_rows(PDO $pdo, string $sql, array $params = []): array
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function sa_scalar(PDO $pdo, string $sql, array $params = []): int
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int) ($stmt->fetchColumn() ?: 0);
    } catch (Throwable $e) {
        return 0;
    }
}

function sa_ticket(PDO $pdo, int $ticketId, int $agentId): ?array
{
    if ($ticketId <= 0) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM support_tickets WHERE id = ? AND assigned_admin_id = ? LIMIT 1');
    $stmt->execute([$ticketId, $agentId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Security token expired. Refresh and try again.';
    } elseif (!app_check_rate_limit('support_agent_action_' . $agentId, 30, 900)) {
        $error = 'Too many support actions. Please slow down and try again shortly.';
    } else {
        try {
            $ticket = sa_ticket($pdo, $selectedId, $agentId);
            if (!$ticket) {
                throw new RuntimeException('This ticket is not assigned to you. Ask an operator to assign it first.');
            }
            $action = (string) ($_POST['action'] ?? 'reply');
            $reply = trim((string) ($_POST['reply'] ?? ''));
            $internalNote = trim((string) ($_POST['internal_note'] ?? ''));
            $nextStatus = (string) ($_POST['status'] ?? $ticket['status']);
            $allowedStatuses = ['in_progress', 'waiting_on_user', 'resolved', 'escalated'];
            if (!in_array($nextStatus, $allowedStatuses, true)) {
                $nextStatus = (string) $ticket['status'];
            }
            if ($action === 'escalate') {
                $nextStatus = 'escalated';
                $internalNote = $internalNote !== '' ? $internalNote : 'Support agent escalated this ticket for operator review.';
            }
            if ($reply !== '') {
                $msgId = support_add_message($pdo, (int) $ticket['id'], $reply, $user, true, 'public', (string) ($user['name'] ?? 'Support Agent'), 'support_agent');
                if (!empty($_FILES['attachments']) || !empty($_FILES['attachment'])) {
                    support_process_uploaded_files($pdo, (int) $ticket['id'], $msgId > 0 ? $msgId : null, $_FILES['attachments'] ?? $_FILES['attachment'], $agentId);
                }
            }
            if ($internalNote !== '') {
                support_add_message($pdo, (int) $ticket['id'], $internalNote, $user, true, 'internal', (string) ($user['name'] ?? 'Support Agent'), 'support_agent');
            }
            $resolved = $nextStatus === 'resolved' ? 1 : 0;
            $stmt = $pdo->prepare("UPDATE support_tickets SET status = ?, outcome = IF(? = 'resolved', 'resolved', IF(? = 'escalated', 'escalated', outcome)), first_response_at = COALESCE(first_response_at, NOW()), resolved_at = IF(? = 1, COALESCE(resolved_at, NOW()), resolved_at), last_activity_at = NOW() WHERE id = ? AND assigned_admin_id = ?");
            $stmt->execute([$nextStatus, $nextStatus, $nextStatus, $resolved, (int) $ticket['id'], $agentId]);
            redirect_to('agent.php?ticket_id=' . (int) $ticket['id'] . '&saved=1');
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

if (isset($_GET['saved'])) {
    $message = 'Ticket update saved.';
}

$assigned = sa_rows($pdo, "SELECT * FROM support_tickets WHERE assigned_admin_id = ? AND status NOT IN ('resolved','closed','rejected') ORDER BY FIELD(priority, 'high','medium','low'), FIELD(status, 'escalated','open','in_progress','waiting_on_user'), last_activity_at DESC, id DESC LIMIT 100", [$agentId]);
if ($selectedId <= 0 && $assigned) {
    $selectedId = (int) $assigned[0]['id'];
}
$selected = sa_ticket($pdo, $selectedId, $agentId);
$conversation = $selected ? support_messages_with_attachments($pdo, (int) $selected['id'], true) : [];
$openCount = sa_scalar($pdo, "SELECT COUNT(*) FROM support_tickets WHERE assigned_admin_id = ? AND status NOT IN ('resolved','closed','rejected')", [$agentId]);
$waitingCount = sa_scalar($pdo, "SELECT COUNT(*) FROM support_tickets WHERE assigned_admin_id = ? AND status = 'waiting_on_user'", [$agentId]);
$escalatedCount = sa_scalar($pdo, "SELECT COUNT(*) FROM support_tickets WHERE assigned_admin_id = ? AND status = 'escalated'", [$agentId]);
$resolvedToday = sa_scalar($pdo, "SELECT COUNT(*) FROM support_tickets WHERE assigned_admin_id = ? AND status = 'resolved' AND DATE(resolved_at) = CURDATE()", [$agentId]);
$logo = app_primary_logo_url();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Support Agent Desk - NATCODEV</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    :root{--green:#075f2a;--deep:#053b1c;--line:#dfe8d8;--bg:#f6faf4;--ink:#101828;--muted:#667085;--red:#b42318;--gold:#b7791f}*{box-sizing:border-box}body{margin:0;background:var(--bg);font-family:"Segoe UI",Arial,sans-serif;color:var(--ink)}a{text-decoration:none;color:inherit}.shell{display:grid;grid-template-columns:278px 1fr;min-height:100vh}.side{background:linear-gradient(180deg,#06451f,#092f22);color:#fff;padding:20px;position:sticky;top:0;height:100vh;overflow:auto}.brand{display:flex;gap:12px;align-items:center;padding-bottom:16px;border-bottom:1px solid rgba(255,255,255,.16)}.brand img{width:52px;height:52px;border-radius:50%;background:#fff}.brand strong{display:block;font-size:1.15rem}.brand small{color:#cdeed9}.agent{margin:18px 0;padding:13px;border:1px solid rgba(255,255,255,.16);border-radius:8px;background:rgba(255,255,255,.08)}.nav{display:grid;gap:8px}.nav a{display:flex;align-items:center;gap:10px;padding:11px;border-radius:8px;color:#fff;font-weight:850}.nav a.active,.nav a:hover{background:#118b42}.main{min-width:0}.top{height:72px;background:#fff;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;gap:12px;padding:0 24px;position:sticky;top:0;z-index:5}.chip{border:1px solid var(--line);border-radius:8px;background:#fff;padding:9px 11px;font-weight:850}.content{padding:24px}.hero{display:flex;justify-content:space-between;gap:16px;align-items:flex-end;margin-bottom:16px}.hero h1{margin:0;color:#062b17}.hero p{margin:5px 0 0;color:var(--muted)}.kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:16px}.kpi{background:#fff;border:1px solid var(--line);border-radius:8px;padding:15px;box-shadow:0 12px 32px rgba(16,24,40,.06)}.kpi small{display:block;color:var(--muted);font-weight:850;text-transform:uppercase}.kpi b{display:block;font-size:1.55rem;margin-top:6px}.grid{display:grid;grid-template-columns:380px minmax(0,1fr);gap:16px}.panel{background:#fff;border:1px solid var(--line);border-radius:8px;box-shadow:0 12px 32px rgba(16,24,40,.06);padding:15px}.tickets{display:grid;gap:9px}.ticket{display:block;border:1px solid var(--line);border-radius:8px;padding:11px;background:#fff}.ticket.active{border-color:#087443;background:#f0fbf4}.ticket strong{display:block}.muted{color:var(--muted)}.badge{display:inline-flex;border-radius:999px;padding:4px 8px;font-size:.72rem;font-weight:900;background:#eef8ef;color:#087443}.badge.high{background:#fee4e2;color:#b42318}.badge.medium{background:#fff1df;color:#b7791f}.badge.escalated{background:#fee4e2;color:#b42318}.messages{display:grid;gap:10px;margin:14px 0}.msg{border:1px solid var(--line);border-radius:8px;padding:11px;background:#fbfdf9}.msg.internal{background:#fff7e8;border-color:#f5c56b}.msg small{display:block;color:var(--muted);font-weight:850;margin-bottom:4px}.form{display:grid;gap:10px}.form textarea,.form select{width:100%;border:1px solid var(--line);border-radius:8px;padding:11px;font:inherit}.actions{display:flex;gap:10px;flex-wrap:wrap}.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;border:1px solid var(--green);background:var(--green);color:#fff;border-radius:8px;padding:10px 14px;font-weight:900;cursor:pointer}.btn.light{background:#fff;color:var(--green)}.btn.danger{background:#b42318;border-color:#b42318}.alert{padding:11px;border-radius:8px;margin-bottom:12px}.alert.ok{background:#ecfdf3;color:#067647}.alert.bad{background:#fff1f2;color:#b42318}.empty{padding:18px;border:1px dashed var(--line);border-radius:8px;color:var(--muted)}@media(max-width:980px){.shell{grid-template-columns:1fr}.side{position:relative;height:auto}.grid,.kpis{grid-template-columns:1fr}.top,.hero{align-items:flex-start;flex-direction:column;height:auto;padding:16px}}
  </style>
</head>
<body>
<div class="shell">
  <aside class="side">
    <a class="brand" href="agent.php"><img src="<?= e($logo) ?>" alt="NATCODEV"><span><strong>NATCODEV</strong><small>Support Agent Desk</small></span></a>
    <div class="agent"><strong><?= e((string) ($user['name'] ?? 'Support Agent')) ?></strong><br><small><?= e((string) ($user['email'] ?? '')) ?></small><br><span class="badge">support_agent</span></div>
    <nav class="nav">
      <a class="active" href="agent.php"><i class="fa-solid fa-ticket"></i> My Tickets</a>
      <a href="agent.php#escalate"><i class="fa-solid fa-arrow-up"></i> Escalate</a>
      <a href="profile.php"><i class="fa-solid fa-user"></i> Profile</a>
      <a href="profile.php#account"><i class="fa-solid fa-lock"></i> Account</a>
      <a href="../support/index.php"><i class="fa-solid fa-life-ring"></i> Public Support</a>
      <a href="../index.php"><i class="fa-solid fa-house"></i> NATCODEV Home</a>
      <a href="../logout.php?next=support%2Fagent.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </nav>
  </aside>
  <main class="main">
    <header class="top"><span class="chip"><i class="fa-solid fa-headset"></i> Assigned support work only</span><span class="chip"><?= e(date('M j, Y h:i A')) ?></span><a class="chip" href="profile.php">Profile</a><a class="chip" href="profile.php#account">Account</a><a class="chip" href="../logout.php?next=support%2Fagent.php">Logout</a></header>
    <section class="content">
      <div class="hero"><div><h1>Support Agent Desk</h1><p>Handle tickets assigned to you, reply to customers, resolve simple issues, or escalate to operators.</p></div><a class="btn light" href="../login.php?next=support%2Fagent.php">Switch Account</a></div>
      <?php if ($message): ?><div class="alert ok"><?= e($message) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert bad"><?= e($error) ?></div><?php endif; ?>
      <div class="kpis"><div class="kpi"><small>Open Assigned</small><b><?= number_format($openCount) ?></b></div><div class="kpi"><small>Waiting On User</small><b><?= number_format($waitingCount) ?></b></div><div class="kpi"><small>Escalated By Me</small><b><?= number_format($escalatedCount) ?></b></div><div class="kpi"><small>Resolved Today</small><b><?= number_format($resolvedToday) ?></b></div></div>
      <div class="grid">
        <section class="panel"><h2>My Assigned Tickets</h2><div class="tickets"><?php foreach ($assigned as $ticket): ?><a class="ticket <?= $selected && (int) $selected['id'] === (int) $ticket['id'] ? 'active' : '' ?>" href="agent.php?ticket_id=<?= (int) $ticket['id'] ?>"><strong><?= e((string) $ticket['ticket_ref']) ?></strong><span><?= e((string) $ticket['subject']) ?></span><br><span class="badge <?= e((string) $ticket['priority']) ?>"><?= e(ucfirst((string) $ticket['priority'])) ?></span> <span class="badge <?= e((string) $ticket['status']) ?>"><?= e($statuses[(string) $ticket['status']] ?? (string) $ticket['status']) ?></span></a><?php endforeach; ?><?php if (!$assigned): ?><div class="empty">No ticket is assigned to you yet. Operators assign tickets from the admin support workspace.</div><?php endif; ?></div></section>
        <section class="panel">
          <?php if ($selected): ?>
            <h2><?= e((string) $selected['ticket_ref']) ?> - <?= e((string) $selected['subject']) ?></h2>
            <p class="muted"><?= e((string) $selected['requester_name']) ?> / <?= e((string) $selected['requester_email']) ?> / <?= e((string) ($categories[(string) $selected['category']]['label'] ?? $selected['category'])) ?></p>
            <p><?= nl2br(e((string) $selected['description'])) ?></p>
            <div class="messages"><?php foreach ($conversation as $msg): ?><div class="msg <?= (string) $msg['visibility'] === 'internal' ? 'internal' : '' ?>"><small><?= e((string) $msg['author_name']) ?> / <?= e((string) $msg['author_role']) ?> / <?= e((string) $msg['visibility']) ?></small><?= nl2br(e((string) $msg['message'])) ?><?php if (!empty($msg['attachments'])): ?><div style="margin-top:8px;padding-top:6px;border-top:1px solid rgba(0,0,0,.08);display:flex;flex-wrap:wrap;gap:6px;"><small style="display:block;width:100%;color:#64748b;">Attachments (<?= count($msg['attachments']) ?>):</small><?php foreach ($msg['attachments'] as $att): ?><a href="attachment.php?id=<?= (int) $att['id'] ?>" target="_blank" style="display:inline-flex;align-items:center;gap:5px;padding:4px 8px;background:#fff;border:1px solid #cbd5e1;border-radius:5px;font-size:.78rem;text-decoration:none;color:#0f172a;"><i class="fas fa-paperclip"></i> <?= e((string) $att['original_name']) ?> (<?= e(support_format_bytes((int) ($att['file_size'] ?? 0))) ?>)</a><?php endforeach; ?></div><?php endif; ?></div><?php endforeach; ?></div>
            <form class="form" method="post" enctype="multipart/form-data">
              <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="ticket_id" value="<?= (int) $selected['id'] ?>">
              <label>Public Reply<textarea name="reply" placeholder="Write a clear reply to the requester..."></textarea></label>
              <label style="font-size:.82rem;color:#475569;">Attach File(s)<input type="file" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.txt,.csv,.xls,.xlsx" style="font-size:.82rem;"></label>
              <label>Internal Note<textarea name="internal_note" placeholder="Internal note for operators or future agents..."></textarea></label>
              <label>Status<select name="status"><option value="in_progress">In Progress</option><option value="waiting_on_user">Waiting On User</option><option value="resolved">Resolved</option><option value="escalated">Escalated</option></select></label>
              <div class="actions"><button class="btn" name="action" value="reply">Save Update</button><button class="btn danger" id="escalate" name="action" value="escalate">Escalate To Operator</button></div>
            </form>
          <?php else: ?>
            <div class="empty">Select an assigned ticket to begin. Support agents cannot create teams, change settings, or browse unrelated admin queues.</div>
          <?php endif; ?>
        </section>
      </div>
    </section>
  </main>
</div>
</body>
</html>