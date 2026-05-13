<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/dashboard-layout.php';
require_once __DIR__ . '/../lib/notification-dispatch.php';

session_start();
$pdo = db();
app_ensure_farmer_engagement_schema($pdo);

if (empty($_SESSION['user_id'])) {
    redirect_to('login.php');
}

$userId = (int) $_SESSION['user_id'];
$categories = [
    'general' => 'General Support',
    'verification' => 'Identity & Documents',
    'farm-health' => 'Farm Health',
    'payments' => 'Payments & Wallet',
    'marketplace' => 'Marketplace',
    'certificate' => 'Certificate',
    'field-visit' => 'Field Visit',
];
$priorities = ['low' => 'Low', 'medium' => 'Normal', 'high' => 'Urgent'];
$statuses = ['open' => 'Open', 'in_progress' => 'In Progress', 'resolved' => 'Resolved', 'closed' => 'Closed'];
$topic = preg_replace('/[^a-z0-9_-]/i', '', (string) ($_GET['topic'] ?? ''));
$selectedTicket = preg_replace('/[^A-Z0-9-]/i', '', (string) ($_GET['ticket'] ?? ''));
$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Please refresh and try again.';
    } else {
        $action = (string) ($_POST['action'] ?? 'create');
        $body = trim((string) ($_POST['message'] ?? ''));

        if ($body === '') {
            $error = 'Please enter a message.';
        } elseif ($action === 'reply') {
            $ticketId = preg_replace('/[^A-Z0-9-]/i', '', (string) ($_POST['ticket_id'] ?? ''));
            $ticketStmt = $pdo->prepare("SELECT ticket_id, category, priority, status FROM messages WHERE user_id = ? AND ticket_id = ? LIMIT 1");
            $ticketStmt->execute([$userId, $ticketId]);
            $ticket = $ticketStmt->fetch();

            if (!$ticket) {
                $error = 'Ticket not found.';
            } elseif (in_array((string) $ticket['status'], ['resolved', 'closed'], true)) {
                $error = 'This ticket is already closed. Please open a new ticket.';
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO messages (user_id, ticket_id, category, message, is_from_admin, priority, status)
                    VALUES (?, ?, ?, ?, 0, ?, 'open')
                ");
                $stmt->execute([$userId, $ticketId, $ticket['category'], $body, $ticket['priority']]);
                natcodev_notify_admins($pdo, 'NATCODEV Ticket Reply', "A grower replied to ticket {$ticketId}.\n\nMessage:\n{$body}");
                redirect_to('inbox.php?ticket=' . urlencode($ticketId));
            }
        } else {
            $category = preg_replace('/[^a-z0-9_-]/i', '', (string) ($_POST['category'] ?? 'general')) ?: 'general';
            $priority = in_array($_POST['priority'] ?? 'medium', array_keys($priorities), true) ? (string) $_POST['priority'] : 'medium';
            if (!isset($categories[$category])) {
                $category = 'general';
            }

            $ticketId = 'TKT-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $stmt = $pdo->prepare("
                INSERT INTO messages (user_id, ticket_id, category, message, is_from_admin, priority, status)
                VALUES (?, ?, ?, ?, 0, ?, 'open')
            ");
            $stmt->execute([$userId, $ticketId, $category, $body, $priority]);
            natcodev_notify_user($pdo, $userId, 'support_ticket_opened', 'NATCODEV Support Ticket Opened', [
                'ticket_id' => $ticketId,
                'category' => $categories[$category] ?? $category,
            ], "Support ticket {$ticketId} has been opened.");
            natcodev_notify_admins($pdo, 'New NATCODEV Support Ticket', "A new {$priority} priority ticket has been opened.\n\nTicket: {$ticketId}\nCategory: " . ($categories[$category] ?? $category) . "\nMessage:\n{$body}");
            redirect_to('inbox.php?ticket=' . urlencode($ticketId));
        }
    }
}

$pdo->prepare("UPDATE messages SET is_read = 1 WHERE user_id = ? AND is_from_admin = 1")->execute([$userId]);

$ticketStmt = $pdo->prepare("
    SELECT ticket_id,
           MAX(category) category,
           MAX(priority) priority,
           SUBSTRING_INDEX(GROUP_CONCAT(status ORDER BY created_at DESC SEPARATOR ','), ',', 1) status,
           MIN(created_at) opened_at,
           MAX(created_at) last_message_at,
           COUNT(*) total_messages,
           SUM(CASE WHEN is_from_admin = 1 AND is_read = 0 THEN 1 ELSE 0 END) unread_admin_messages
    FROM messages
    WHERE user_id = ? AND ticket_id IS NOT NULL AND ticket_id <> ''
    GROUP BY ticket_id
    ORDER BY last_message_at DESC
");
$ticketStmt->execute([$userId]);
$tickets = $ticketStmt->fetchAll();

if ($selectedTicket === '' && $tickets) {
    $selectedTicket = (string) $tickets[0]['ticket_id'];
}

$conversation = [];
$selectedMeta = null;
if ($selectedTicket !== '') {
    $msgStmt = $pdo->prepare("SELECT * FROM messages WHERE user_id = ? AND ticket_id = ? ORDER BY created_at ASC");
    $msgStmt->execute([$userId, $selectedTicket]);
    $conversation = $msgStmt->fetchAll();
    if ($conversation) {
        $last = end($conversation);
        $selectedMeta = [
            'ticket_id' => $selectedTicket,
            'category' => (string) $last['category'],
            'priority' => (string) $last['priority'],
            'status' => (string) $last['status'],
        ];
    }
}
?>
<?php dashboard_page_start('Support Desk', [
    'active' => 'inbox.php',
    'description' => 'Open tickets, follow replies, and keep all support conversations organized.',
    'wide' => true,
    'css' => '
      .support-layout { grid-template-columns:minmax(260px,340px) minmax(0,1fr); align-items:stretch; }
      .support-panel { min-width:0; }
      .ticket-list { max-height:540px; overflow:auto; padding-right:4px; }
      .ticket-link { display:block; width:100%; }
      .conversation { display:flex; flex-direction:column; gap:12px; margin:18px 0 20px; }
      .msg { max-width:min(760px,82%); }
      .msg.you { align-self:flex-end; }
      .support-form { width:100%; max-width:none; }
      .support-form .field-grid { display:grid; grid-template-columns:minmax(220px,1fr) minmax(160px,.45fr); gap:16px; align-items:end; }
      .support-form input, .support-form select, .support-form textarea { width:100%; display:block; }
      .support-form textarea { min-height:132px; resize:vertical; }
      .support-form button { width:auto; min-width:150px; }
      .reply-actions { display:flex; justify-content:flex-end; margin-top:10px; }
      .new-ticket-panel { margin-top:18px; }
      @media(max-width:920px){ .support-layout { grid-template-columns:1fr; } .ticket-list { max-height:none; } .msg { max-width:100%; } }
      @media(max-width:640px){ .support-form .field-grid { grid-template-columns:1fr; } .support-form button { width:100%; } .reply-actions { justify-content:stretch; } }
    ',
]); ?>

    <h1>Support Desk</h1>
    <p class="lead">Open a ticket for document review, farm visits, payments, certificates, marketplace issues, or any question the NATCODEV team should handle.</p>
    <?php if ($message): ?><div class="notice ok"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>

    <div class="layout support-layout">
      <aside class="panel support-panel">
        <h2>Your Tickets</h2>
        <div class="ticket-list">
        <?php foreach ($tickets as $ticket): ?>
          <?php $status = (string) ($ticket['status'] ?: 'open'); ?>
          <a class="ticket-link <?= (string) $ticket['ticket_id'] === $selectedTicket ? 'active' : '' ?>" href="?ticket=<?= e((string) $ticket['ticket_id']) ?>">
            <strong><?= e((string) $ticket['ticket_id']) ?></strong>
            <span class="badge <?= e($status) ?>"><?= e($statuses[$status] ?? status_label($status)) ?></span>
            <div class="ticket-meta">
              <?= e($categories[(string) $ticket['category']] ?? (string) $ticket['category']) ?> /
              <?= e($priorities[(string) $ticket['priority']] ?? (string) $ticket['priority']) ?><br>
              Last update: <?= e(date('M j, g:i A', strtotime((string) $ticket['last_message_at']))) ?>
            </div>
          </a>
        <?php endforeach; ?>
        <?php if (!$tickets): ?><p class="empty">No tickets yet. Create your first request below.</p><?php endif; ?>
        </div>
      </aside>

      <section class="panel support-panel">
        <?php if ($selectedMeta): ?>
          <h2><?= e($selectedMeta['ticket_id']) ?></h2>
          <p class="ticket-meta">
            <?= e($categories[$selectedMeta['category']] ?? $selectedMeta['category']) ?> /
            <?= e($priorities[$selectedMeta['priority']] ?? $selectedMeta['priority']) ?> /
            <span class="badge <?= e($selectedMeta['status']) ?>"><?= e($statuses[$selectedMeta['status']] ?? status_label($selectedMeta['status'])) ?></span>
          </p>
          <div class="conversation">
            <?php foreach ($conversation as $msg): ?>
              <div class="msg <?= (int) $msg['is_from_admin'] === 1 ? '' : 'you' ?>">
                <strong><?= (int) $msg['is_from_admin'] === 1 ? 'NATCODEV Support' : 'You' ?></strong>
                <p><?= nl2br(e($msg['message'])) ?></p>
                <small><?= e(date('M j, Y g:i A', strtotime((string) $msg['created_at']))) ?></small>
              </div>
            <?php endforeach; ?>
          </div>

          <form method="post" class="support-form">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="reply">
            <input type="hidden" name="ticket_id" value="<?= e($selectedMeta['ticket_id']) ?>">
            <label>Reply</label>
            <textarea name="message" rows="5" required placeholder="Add a reply to this ticket..." <?= in_array($selectedMeta['status'], ['resolved', 'closed'], true) ? 'disabled' : '' ?>></textarea>
            <div class="reply-actions"><button type="submit" <?= in_array($selectedMeta['status'], ['resolved', 'closed'], true) ? 'disabled' : '' ?>>Send Reply</button></div>
          </form>
        <?php else: ?>
          <p class="empty">Select a ticket or create a new support request.</p>
        <?php endif; ?>
      </section>
    </div>

    <section class="panel new-ticket-panel">
      <h2>Open New Ticket</h2>
      <form method="post" class="support-form">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="create">
        <div class="field-grid">
          <div>
            <label>Category</label>
            <select name="category">
              <?php foreach ($categories as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= $topic === $value ? 'selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label>Priority</label>
            <select name="priority">
              <?php foreach ($priorities as $value => $label): ?>
                <option value="<?= e($value) ?>"><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <label>Message</label>
        <textarea name="message" rows="5" required placeholder="Tell us what happened, what page you were on, and what you need help with."></textarea>
        <div class="reply-actions"><button type="submit">Create Ticket</button></div>
      </form>
    </section>
  <?php dashboard_page_end(); ?>
