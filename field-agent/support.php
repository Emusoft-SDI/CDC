<?php
declare(strict_types=1);
require_once __DIR__ . '/_field.php';

$pdo = fa_pdo();
$user = fa_require_user($pdo);
$userId = (int) $user['id'];
$notice = '';
$error = '';
$categories = [
    'field-visit' => 'Field Visit',
    'offline-sync' => 'Offline Sync',
    'evidence' => 'Evidence Upload',
    'verification' => 'Grower Verification',
    'farm-health' => 'Farm Health',
    'payments' => 'Wallet & Allowance',
    'general' => 'General Support',
];
$priorities = ['low' => 'Low', 'medium' => 'Normal', 'high' => 'Urgent'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid security token.';
    } else {
        $action = (string) ($_POST['action'] ?? 'create');
        $body = trim((string) ($_POST['message'] ?? ''));
        if ($body === '') {
            $error = 'Please enter the support message.';
        } elseif ($action === 'reply') {
            $ticketId = preg_replace('/[^A-Z0-9-]/i', '', (string) ($_POST['ticket_id'] ?? ''));
            $stmt = $pdo->prepare('SELECT ticket_id, category, priority, status FROM messages WHERE user_id = ? AND ticket_id = ? LIMIT 1');
            $stmt->execute([$userId, $ticketId]);
            $ticket = $stmt->fetch();
            if (!$ticket) {
                $error = 'Ticket was not found.';
            } elseif (in_array((string) $ticket['status'], ['resolved', 'closed'], true)) {
                $error = 'This ticket is closed. Open a new support ticket for a new issue.';
            } else {
                $insert = $pdo->prepare("INSERT INTO messages (user_id, ticket_id, category, message, is_from_admin, priority, status) VALUES (?, ?, ?, ?, 0, ?, 'open')");
                $insert->execute([$userId, $ticketId, $ticket['category'], $body, $ticket['priority']]);
                natcodev_notify_admins($pdo, 'Field support reply', "Field user replied to {$ticketId}.\n\n{$body}");
                $notice = 'Reply added to ticket ' . $ticketId . '.';
            }
        } else {
            $category = preg_replace('/[^a-z0-9_-]/i', '', (string) ($_POST['category'] ?? 'general')) ?: 'general';
            if (!isset($categories[$category])) { $category = 'general'; }
            $priority = in_array($_POST['priority'] ?? 'medium', array_keys($priorities), true) ? (string) $_POST['priority'] : 'medium';
            $ticketId = 'FLD-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $insert = $pdo->prepare("INSERT INTO messages (user_id, ticket_id, category, message, is_from_admin, priority, status) VALUES (?, ?, ?, ?, 0, ?, 'open')");
            $insert->execute([$userId, $ticketId, $category, $body, $priority]);
            natcodev_notify_admins($pdo, 'New field support ticket', "Ticket: {$ticketId}\nCategory: " . ($categories[$category] ?? $category) . "\nPriority: {$priority}\n\n{$body}");
            $notice = 'Support ticket opened: ' . $ticketId . '.';
        }
    }
}

$ticketStmt = $pdo->prepare("SELECT ticket_id, MAX(category) category, MAX(priority) priority, SUBSTRING_INDEX(GROUP_CONCAT(status ORDER BY created_at DESC SEPARATOR ','), ',', 1) status, MIN(created_at) opened_at, MAX(created_at) last_message_at, COUNT(*) messages FROM messages WHERE user_id = ? AND ticket_id IS NOT NULL GROUP BY ticket_id ORDER BY last_message_at DESC LIMIT 25");
$ticketStmt->execute([$userId]);
$tickets = $ticketStmt->fetchAll();
$selectedTicket = preg_replace('/[^A-Z0-9-]/i', '', (string) ($_GET['ticket'] ?? ($tickets[0]['ticket_id'] ?? '')));
$conversation = [];
if ($selectedTicket !== '') {
    $msgStmt = $pdo->prepare('SELECT * FROM messages WHERE user_id = ? AND ticket_id = ? ORDER BY created_at ASC');
    $msgStmt->execute([$userId, $selectedTicket]);
    $conversation = $msgStmt->fetchAll();
}
$openCount = 0;
$urgentCount = 0;
foreach ($tickets as $ticket) {
    if (in_array((string) $ticket['status'], ['open', 'in_progress'], true)) { $openCount++; }
    if ((string) $ticket['priority'] === 'high') { $urgentCount++; }
}

fa_header('Support Desk', 'Self-contained help for assignments, GPS, sync, evidence, verification, wallet, and field escalation.', $user, 'support');
?>
<?php if ($notice): ?><div class="fa-card fa-panel" style="border-left:5px solid var(--green);margin-bottom:16px"><?= e($notice) ?></div><?php endif; ?>
<?php if ($error): ?><div class="fa-card fa-panel" style="border-left:5px solid var(--red);margin-bottom:16px"><?= e($error) ?></div><?php endif; ?>
<section class="fa-kpis">
  <article class="fa-card fa-kpi"><span class="fa-icon"><i data-lucide="life-buoy"></i></span><div><small>Total Tickets</small><b><?= count($tickets) ?></b><span>Field desk</span></div></article>
  <article class="fa-card fa-kpi"><span class="fa-icon orange"><i data-lucide="timer"></i></span><div><small>Open</small><b><?= $openCount ?></b><span>Need action</span></div></article>
  <article class="fa-card fa-kpi"><span class="fa-icon red"><i data-lucide="alert-triangle"></i></span><div><small>Urgent</small><b><?= $urgentCount ?></b><span>High priority</span></div></article>
</section>
<section class="fa-grid">
  <article class="fa-card fa-panel span-5">
    <div class="fa-panel-head"><h2>Open New Ticket</h2><span class="badge good">Admin visible</span></div>
    <form method="post" class="field-form">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="create">
      <div class="field-grid"><label>Category<select name="category"><?php foreach ($categories as $key => $label): ?><option value="<?= e($key) ?>"><?= e($label) ?></option><?php endforeach; ?></select></label><label>Priority<select name="priority"><?php foreach ($priorities as $key => $label): ?><option value="<?= e($key) ?>"><?= e($label) ?></option><?php endforeach; ?></select></label></div>
      <label>Issue<textarea name="message" placeholder="Include task ID, grower name, farm location, error message, payment reference, or upload blocker." required></textarea></label>
      <button class="btn"><i data-lucide="send"></i>Submit Ticket</button>
    </form>
  </article>
  <article class="fa-card fa-panel span-7">
    <div class="fa-panel-head"><h2>Tickets</h2><span class="badge neutral"><?= count($tickets) ?> record(s)</span></div>
    <div class="fa-list"><?php foreach ($tickets as $ticket): ?><a class="fa-row" style="text-decoration:none;color:inherit" href="?ticket=<?= e((string) $ticket['ticket_id']) ?>"><span class="fa-icon <?= $ticket['priority']==='high'?'red':'blue' ?>"><i data-lucide="message-square"></i></span><div><strong><?= e((string) $ticket['ticket_id']) ?> / <?= e($categories[(string) $ticket['category']] ?? (string) $ticket['category']) ?></strong><br><span class="muted"><?= e((string) $ticket['messages']) ?> message(s). Last update <?= e((string) $ticket['last_message_at']) ?></span></div><span class="badge <?= in_array((string)$ticket['status'],['resolved','closed'],true)?'good':'warn' ?>"><?= e((string) $ticket['status']) ?></span></a><?php endforeach; ?><?php if (!$tickets): ?><div class="empty">No field support tickets yet.</div><?php endif; ?></div>
  </article>
  <article class="fa-card fa-panel span-12">
    <div class="fa-panel-head"><h2><?= $selectedTicket ? 'Conversation ' . e($selectedTicket) : 'Conversation' ?></h2><a class="btn soft" href="../admin/support/" target="_blank"><i data-lucide="shield"></i>Admin Console</a></div>
    <div class="fa-list"><?php foreach ($conversation as $msg): ?><div class="fa-row"><span class="fa-icon <?= !empty($msg['is_from_admin']) ? 'gold' : '' ?>"><i data-lucide="<?= !empty($msg['is_from_admin']) ? 'shield-check' : 'user' ?>"></i></span><div><strong><?= !empty($msg['is_from_admin']) ? 'NATCODEV Support' : 'You' ?></strong><br><span class="muted"><?= nl2br(e((string) $msg['message'])) ?></span></div><small><?= e((string) $msg['created_at']) ?></small></div><?php endforeach; ?><?php if (!$conversation): ?><div class="empty">Select a ticket to view the conversation.</div><?php endif; ?></div>
    <?php if ($selectedTicket && $conversation): ?><form method="post" class="field-form" style="margin-top:14px"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="reply"><input type="hidden" name="ticket_id" value="<?= e($selectedTicket) ?>"><label>Reply<textarea name="message" required></textarea></label><button class="btn"><i data-lucide="reply"></i>Reply</button></form><?php endif; ?>
  </article>
</section>
<?php fa_footer(); ?>