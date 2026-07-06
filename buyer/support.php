<?php
declare(strict_types=1);
require_once __DIR__ . '/_buyer.php';

$pdo = buyer_boot();
$user = buyer_require($pdo);
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Security token expired. Refresh and try again.';
    } else {
        try {
            $action = (string) ($_POST['action'] ?? '');
            if ($action === 'create') {
                $ref = support_create_ticket($pdo, [
                    'name' => $user['name'] ?? '',
                    'email' => $user['email'] ?? '',
                    'phone' => $user['phone'] ?? '',
                    'category' => $_POST['category'] ?? 'marketplace',
                    'priority' => $_POST['priority'] ?? 'medium',
                    'subject' => $_POST['subject'] ?? '',
                    'description' => $_POST['description'] ?? '',
                    'linked_record_type' => $_POST['linked_record_type'] ?? 'order',
                    'linked_record_ref' => $_POST['linked_record_ref'] ?? '',
                ], $user);
                redirect_to('support.php?ticket=' . urlencode($ref) . '&message=' . urlencode('Support ticket opened.'));
            }
            if ($action === 'reply') {
                $ref = preg_replace('/[^A-Z0-9-]/i', '', (string) ($_POST['ticket_ref'] ?? ''));
                $ticket = support_ticket_by_ref($pdo, $ref);
                if (!$ticket || (int) ($ticket['user_id'] ?? 0) !== (int) $user['id']) {
                    throw new RuntimeException('This ticket does not belong to your buyer account.');
                }
                if (in_array((string) $ticket['status'], ['resolved', 'closed', 'rejected'], true)) {
                    throw new RuntimeException('This ticket is closed. Open a new ticket if you need more help.');
                }
                support_add_message($pdo, (int) $ticket['id'], (string) ($_POST['reply'] ?? ''), $user, false, 'public', (string) $user['name'], 'buyer');
                $pdo->prepare("UPDATE support_tickets SET status = IF(status = 'waiting_on_user', 'open', status), last_activity_at = NOW() WHERE id = ?")->execute([(int) $ticket['id']]);
                redirect_to('support.php?ticket=' . urlencode($ref) . '&message=' . urlencode('Reply added.'));
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$message = (string) ($_GET['message'] ?? $message);
$tickets = support_user_tickets($pdo, (int) $user['id']);
$selectedRef = preg_replace('/[^A-Z0-9-]/i', '', (string) ($_GET['ticket'] ?? ''));
if ($selectedRef === '' && $tickets) {
    $selectedRef = (string) $tickets[0]['ticket_ref'];
}
$selected = null;
$conversation = [];
if ($selectedRef !== '') {
    $candidate = support_ticket_by_ref($pdo, $selectedRef);
    if ($candidate && (int) ($candidate['user_id'] ?? 0) === (int) $user['id']) {
        $selected = $candidate;
        $conversation = support_ticket_messages($pdo, (int) $candidate['id'], false);
    }
}
$categories = buyer_support_categories();
$priorities = support_priorities();
$statuses = support_statuses();
$knowledge = buyer_knowledge_articles($pdo);

buyer_page_start('Buyer Help & Support', 'support', $user, buyer_counts($pdo, $user));
?>
<div class="page-head"><div><h1>Buyer Help & Support</h1><p>Private support for your buyer account, orders, payments, refunds, delivery, and marketplace access.</p></div></div>
<?php if ($message): ?><div class="alert ok"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert err"><?= e($error) ?></div><?php endif; ?>
<div class="grid">
  <form class="card span-6 form-grid" method="post">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="create">
    <div class="wide card-head"><h2>New Buyer Ticket</h2><span class="badge">Buyer-only</span></div>
    <label>Category<select name="category"><?php foreach ($categories as $key => $cat): ?><option value="<?= e($key) ?>"><?= e((string) $cat['label']) ?></option><?php endforeach; ?></select></label>
    <label>Priority<select name="priority"><?php foreach ($priorities as $key => $label): ?><option value="<?= e($key) ?>" <?= $key === 'medium' ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
    <label>Linked Type<select name="linked_record_type"><option value="order">Order</option><option value="wallet_transaction">Wallet / Payment</option><option value="refund">Refund</option><option value="quote">Quote</option><option value="profile">Profile</option></select></label>
    <label>Reference<input name="linked_record_ref" placeholder="Checkout ref, order ref, transaction ref"></label>
    <label class="wide">Subject<input name="subject" required maxlength="190"></label>
    <label class="wide">Description<textarea name="description" required></textarea></label>
    <div class="wide"><button class="btn">Send To Buyer Support</button></div>
  </form>
  <section class="card span-6">
    <div class="card-head"><h2>Buyer Knowledge Base</h2><span class="badge"><?= count($knowledge) ?> articles</span></div>
    <div class="list"><?php foreach ($knowledge as $item): ?><div class="row"><span><strong><?= e($item['title']) ?></strong><br><small><?= e($item['body']) ?></small></span></div><?php endforeach; ?></div>
  </section>
  <section class="card span-5">
    <div class="card-head"><h2>My Tickets</h2><span class="badge"><?= count($tickets) ?></span></div>
    <div class="list"><?php foreach ($tickets as $ticket): ?><a class="row" href="support.php?ticket=<?= urlencode((string) $ticket['ticket_ref']) ?>"><span><strong><?= e((string) $ticket['ticket_ref']) ?></strong><br><small><?= e((string) $ticket['subject']) ?></small></span><span class="badge"><?= e($statuses[(string) $ticket['status']] ?? marketplace_status_label((string) $ticket['status'])) ?></span></a><?php endforeach; ?><?php if (!$tickets): ?><div class="alert ok">No support tickets yet.</div><?php endif; ?></div>
  </section>
  <section class="card span-7">
    <div class="card-head"><h2>Conversation</h2><?php if ($selected): ?><span class="badge"><?= e((string) $selected['ticket_ref']) ?></span><?php endif; ?></div>
    <?php if ($selected): ?>
      <p><strong><?= e((string) $selected['subject']) ?></strong><br><span class="badge"><?= e($statuses[(string) $selected['status']] ?? marketplace_status_label((string) $selected['status'])) ?></span></p>
      <div class="list"><?php foreach ($conversation as $chat): ?><div class="row"><span><strong><?= e((string) $chat['author_name']) ?></strong><br><small><?= nl2br(e((string) $chat['message'])) ?></small></span><small><?= e((string) $chat['created_at']) ?></small></div><?php endforeach; ?></div>
      <?php if (!in_array((string) $selected['status'], ['resolved', 'closed', 'rejected'], true)): ?>
        <form method="post" style="margin-top:14px"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="reply"><input type="hidden" name="ticket_ref" value="<?= e((string) $selected['ticket_ref']) ?>"><label>Reply<textarea name="reply" required></textarea></label><button class="btn">Add Reply</button></form>
      <?php endif; ?>
    <?php else: ?>
      <div class="alert ok">Select a ticket to view support replies.</div>
    <?php endif; ?>
  </section>
</div>
<?php buyer_page_end(); ?>
