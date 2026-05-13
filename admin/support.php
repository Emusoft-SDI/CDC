<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/admin-layout.php';
require_once __DIR__ . '/../lib/notification-dispatch.php';

session_start();
$pdo = db();
admin_ensure_schema($pdo);

admin_require($pdo);

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

$selectedTicket = preg_replace('/[^A-Z0-9-]/i', '', (string) ($_GET['ticket'] ?? ''));
$filterStatus = preg_replace('/[^a-z_]/i', '', (string) ($_GET['status'] ?? 'active'));
$page = admin_current_page();
$perPage = admin_per_page(25);
$offset = admin_pagination_offset($page, $perPage);
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid security token.';
    } else {
        $selectedTicket = preg_replace('/[^A-Z0-9-]/i', '', (string) ($_POST['ticket_id'] ?? ''));
        $reply = trim((string) ($_POST['reply'] ?? ''));
        $newStatus = (string) ($_POST['status'] ?? 'in_progress');
        $newPriority = (string) ($_POST['priority'] ?? 'medium');

        if (!isset($statuses[$newStatus])) {
            $newStatus = 'in_progress';
        }
        if (!isset($priorities[$newPriority])) {
            $newPriority = 'medium';
        }

        $ticketStmt = $pdo->prepare("SELECT user_id, category FROM messages WHERE ticket_id = ? ORDER BY created_at DESC LIMIT 1");
        $ticketStmt->execute([$selectedTicket]);
        $ticket = $ticketStmt->fetch();

        if (!$ticket) {
            $error = 'Ticket not found.';
        } else {
            $pdo->prepare("UPDATE messages SET status = ?, priority = ? WHERE ticket_id = ?")->execute([$newStatus, $newPriority, $selectedTicket]);

            if ($reply !== '') {
                $stmt = $pdo->prepare("
                    INSERT INTO messages (user_id, admin_id, ticket_id, category, message, is_from_admin, is_read, priority, status)
                    VALUES (?, ?, ?, ?, ?, 1, 0, ?, ?)
                ");
                $stmt->execute([
                    (int) $ticket['user_id'],
                    $_SESSION['user_id'] ?? null,
                    $selectedTicket,
                    (string) $ticket['category'],
                    $reply,
                    $newPriority,
                    $newStatus,
                ]);
                natcodev_notify_user($pdo, (int) $ticket['user_id'], 'support_reply', 'NATCODEV Support Reply', [
                    'ticket_id' => $selectedTicket,
                    'status' => $statuses[$newStatus] ?? $newStatus,
                ], "You have a new reply on support ticket {$selectedTicket}. Login to view it.");
            }
            if (in_array($newStatus, ['resolved', 'closed'], true)) {
                natcodev_notify_user($pdo, (int) $ticket['user_id'], 'support_ticket_closed', 'NATCODEV Support Ticket Updated', [
                    'ticket_id' => $selectedTicket,
                    'status' => $statuses[$newStatus] ?? $newStatus,
                ], "Support ticket {$selectedTicket} has been marked " . ($statuses[$newStatus] ?? $newStatus) . '.');
            }

            $message = 'Ticket updated.';
        }
    }
}

$where = "m.ticket_id IS NOT NULL AND m.ticket_id <> ''";
$params = [];
if ($filterStatus === 'active') {
    $where .= " AND m.status IN ('open', 'in_progress')";
} elseif (isset($statuses[$filterStatus])) {
    $where .= " AND m.status = ?";
    $params[] = $filterStatus;
}

$countStmt = $pdo->prepare("
    SELECT COUNT(*) FROM (
        SELECT m.ticket_id, u.id user_id
        FROM messages m
        JOIN users u ON u.id = m.user_id
        WHERE {$where}
        GROUP BY m.ticket_id, u.id
    ) ticket_threads
");
$countStmt->execute($params);
$totalThreads = (int) $countStmt->fetchColumn();

$threadsStmt = $pdo->prepare("
    SELECT m.ticket_id,
           MAX(m.category) category,
           SUBSTRING_INDEX(GROUP_CONCAT(m.priority ORDER BY m.created_at DESC SEPARATOR ','), ',', 1) priority,
           SUBSTRING_INDEX(GROUP_CONCAT(m.status ORDER BY m.created_at DESC SEPARATOR ','), ',', 1) status,
           MIN(m.created_at) opened_at,
           MAX(m.created_at) last_message_at,
           COUNT(m.id) total_messages,
           SUM(CASE WHEN m.is_from_admin = 0 AND m.is_read = 0 THEN 1 ELSE 0 END) unread_farmer_messages,
           u.id user_id,
           u.name,
           u.email,
           u.role
    FROM messages m
    JOIN users u ON u.id = m.user_id
    WHERE {$where}
    GROUP BY m.ticket_id, u.id, u.name, u.email, u.role
    ORDER BY
      FIELD(SUBSTRING_INDEX(GROUP_CONCAT(m.priority ORDER BY m.created_at DESC SEPARATOR ','), ',', 1), 'high', 'medium', 'low'),
      last_message_at DESC
    LIMIT {$perPage} OFFSET {$offset}
");
$threadsStmt->execute($params);
$threads = $threadsStmt->fetchAll();

if ($selectedTicket === '' && $threads) {
    $selectedTicket = (string) $threads[0]['ticket_id'];
}

$conversation = [];
$selectedMeta = null;
if ($selectedTicket !== '') {
    $metaStmt = $pdo->prepare("
        SELECT m.ticket_id, m.user_id, u.name, u.email, u.role,
               SUBSTRING_INDEX(GROUP_CONCAT(m.category ORDER BY m.created_at DESC SEPARATOR ','), ',', 1) category,
               SUBSTRING_INDEX(GROUP_CONCAT(m.priority ORDER BY m.created_at DESC SEPARATOR ','), ',', 1) priority,
               SUBSTRING_INDEX(GROUP_CONCAT(m.status ORDER BY m.created_at DESC SEPARATOR ','), ',', 1) status
        FROM messages m
        JOIN users u ON u.id = m.user_id
        WHERE m.ticket_id = ?
        GROUP BY m.ticket_id, m.user_id, u.name, u.email, u.role
        LIMIT 1
    ");
    $metaStmt->execute([$selectedTicket]);
    $selectedMeta = $metaStmt->fetch() ?: null;

    $msgStmt = $pdo->prepare("SELECT * FROM messages WHERE ticket_id = ? ORDER BY created_at ASC");
    $msgStmt->execute([$selectedTicket]);
    $conversation = $msgStmt->fetchAll();

    $pdo->prepare("UPDATE messages SET is_read = 1 WHERE ticket_id = ? AND is_from_admin = 0")->execute([$selectedTicket]);
}

$stats = $pdo->query("
    SELECT
      SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) open_count,
      SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) progress_count,
      SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) resolved_count,
      SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) closed_count
    FROM (
      SELECT ticket_id, SUBSTRING_INDEX(GROUP_CONCAT(status ORDER BY created_at DESC SEPARATOR ','), ',', 1) status
      FROM messages
      WHERE ticket_id IS NOT NULL AND ticket_id <> ''
      GROUP BY ticket_id
    ) ticket_status
")->fetch() ?: [];
admin_page_start('Support Console', [
    'active' => 'support.php',
    'description' => 'Manage grower tickets, respond to requests, update priorities, and close resolved issues.',
    'wide' => true,
    'css' => '.filters{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px}.filter{border:1px solid var(--line);border-radius:999px;padding:7px 11px;color:var(--ink);background:#fff}.filter.active{background:#eaf8f0;color:#0f6b3c;border-color:#bfe8cf}.thread-link{display:block;padding:12px;border:1px solid var(--line);border-radius:8px;color:inherit;margin-bottom:10px}.thread-link.active{border-color:rgba(31,138,85,.55);background:#f1faf5}.unread{background:#a32020;color:#fff;border-radius:999px;padding:2px 7px;font-size:.75rem}.msg{max-width:78%;padding:12px 14px;border-radius:8px;margin:12px 0;background:#f2f4f7}.admin-msg{margin-left:auto;background:#eef7f1}.msg p{margin:8px 0;white-space:pre-wrap}.row{display:grid;grid-template-columns:1fr 1fr;gap:12px}@media(max-width:920px){.row{grid-template-columns:1fr}.msg{max-width:100%}}',
]);
?>
    <?php if ($message): ?><div class="notice ok"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>

    <section class="stats">
      <div class="stat"><div class="metric"><?= (int) ($stats['open_count'] ?? 0) ?></div><div class="meta">Open</div></div>
      <div class="stat"><div class="metric"><?= (int) ($stats['progress_count'] ?? 0) ?></div><div class="meta">In Progress</div></div>
      <div class="stat"><div class="metric"><?= (int) ($stats['resolved_count'] ?? 0) ?></div><div class="meta">Resolved</div></div>
      <div class="stat"><div class="metric"><?= (int) ($stats['closed_count'] ?? 0) ?></div><div class="meta">Closed</div></div>
    </section>

    <div class="layout">
      <aside class="panel">
        <h2>Tickets</h2>
        <div class="filters">
          <?php foreach (['active' => 'Active'] + $statuses as $value => $label): ?>
            <a class="filter <?= $filterStatus === $value ? 'active' : '' ?>" href="?status=<?= e($value) ?>"><?= e($label) ?></a>
          <?php endforeach; ?>
        </div>
        <?= admin_pagination_controls($totalThreads, $page, $perPage, ['status' => $filterStatus]) ?>
        <?php foreach ($threads as $thread): ?>
          <?php $status = (string) ($thread['status'] ?: 'open'); ?>
          <a class="thread-link <?= (string) $thread['ticket_id'] === $selectedTicket ? 'active' : '' ?>" href="?ticket=<?= e((string) $thread['ticket_id']) ?>&status=<?= e($filterStatus) ?>">
            <strong><?= e((string) $thread['ticket_id']) ?></strong>
            <?php if ((int) $thread['unread_farmer_messages'] > 0): ?><span class="unread"><?= (int) $thread['unread_farmer_messages'] ?></span><?php endif; ?>
            <div class="meta">
              <?= e($thread['name']) ?> / <?= e($categories[(string) $thread['category']] ?? (string) $thread['category']) ?><br>
              <?= e($priorities[(string) $thread['priority']] ?? (string) $thread['priority']) ?> /
              <span class="badge <?= e($status) ?>"><?= e($statuses[$status] ?? status_label($status)) ?></span><br>
              <?= e(date('M j, g:i A', strtotime((string) $thread['last_message_at']))) ?>
            </div>
          </a>
        <?php endforeach; ?>
        <?php if (!$threads): ?><p class="empty">No tickets match this filter.</p><?php endif; ?>
        <?= admin_pagination_controls($totalThreads, $page, $perPage, ['status' => $filterStatus]) ?>
      </aside>

      <section class="panel">
        <?php if ($selectedMeta): ?>
          <h2><?= e((string) $selectedMeta['ticket_id']) ?></h2>
          <p class="meta"><?= e($selectedMeta['name']) ?> / <?= e($selectedMeta['email']) ?> / <?= e($selectedMeta['role']) ?></p>

          <?php foreach ($conversation as $msg): ?>
            <div class="msg <?= (int) $msg['is_from_admin'] === 1 ? 'admin-msg' : '' ?>">
              <strong><?= (int) $msg['is_from_admin'] === 1 ? 'Admin' : 'Grower' ?></strong>
              <p><?= nl2br(e($msg['message'])) ?></p>
              <small><?= e(date('M j, Y g:i A', strtotime((string) $msg['created_at']))) ?></small>
            </div>
          <?php endforeach; ?>

          <form method="post">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="ticket_id" value="<?= e((string) $selectedMeta['ticket_id']) ?>">
            <div class="row">
              <div>
                <label>Status</label>
                <select name="status">
                  <?php foreach ($statuses as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= (string) $selectedMeta['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div>
                <label>Priority</label>
                <select name="priority">
                  <?php foreach ($priorities as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= (string) $selectedMeta['priority'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <label>Admin Reply</label>
            <textarea name="reply" rows="5" placeholder="Write a reply, or leave empty to only update status/priority."></textarea>
            <button type="submit">Update Ticket</button>
          </form>
        <?php else: ?>
          <p class="empty">Select a ticket to manage.</p>
        <?php endif; ?>
      </section>
    </div>
<?php admin_page_end(); ?>
