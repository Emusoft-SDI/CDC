<?php
declare(strict_types=1);

require_once __DIR__ . '/_provider.php';
require_once __DIR__ . '/../lib/support.php';

$pdo = provider_boot();
support_ensure_schema($pdo);
$user = provider_full_user($pdo, provider_require($pdo));
$provider = provider_active($pdo, $user);
$counts = provider_counts($pdo, $provider, $user);

$categories = array_intersect_key(support_categories(), array_flip(['provider', 'marketplace', 'academy', 'payments', 'verification', 'technical', 'general']));
$priorities = support_priorities();
$statuses = support_statuses();
$message = trim((string) ($_GET['message'] ?? ''));
$error = '';
$selectedRef = preg_replace('/[^A-Z0-9-]/i', '', (string) ($_GET['ticket'] ?? ''));

$guide = [
    ['Accreditation review', 'Upload the current document in Accreditation first, then open a verification ticket with the document name or certificate reference.', 'verification'],
    ['Storefront and listings', 'Copy the product, service, or seller reference and choose Marketplace & Orders so the marketplace team can review it quickly.', 'marketplace'],
    ['Orders and buyer disputes', 'Use the order reference, buyer name, and expected resolution. High priority should be used for blocked fulfillment or refund risk.', 'marketplace'],
    ['Academy and certificates', 'Include the course title, certificate reference, and the change requested by the reviewer.', 'academy'],
    ['Wallet and payments', 'Include the wallet, checkout, transfer, or Monnify reference. Do not include full card details.', 'payments'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid security token.';
    } else {
        try {
            $action = (string) ($_POST['action'] ?? '');
            if ($action === 'create_ticket') {
                $category = (string) ($_POST['category'] ?? 'provider');
                if (!isset($categories[$category])) {
                    $category = 'provider';
                }
                $priority = (string) ($_POST['priority'] ?? 'medium');
                if (!isset($priorities[$priority])) {
                    $priority = 'medium';
                }
                $linkedType = trim((string) ($_POST['linked_record_type'] ?? 'provider_profile'));
                $linkedRef = trim((string) ($_POST['linked_record_ref'] ?? ''));
                $ref = support_create_ticket($pdo, [
                    'category' => $category,
                    'priority' => $priority,
                    'name' => (string) ($user['name'] ?? ''),
                    'email' => (string) ($user['email'] ?? ''),
                    'phone' => (string) ($user['phone'] ?? ''),
                    'subject' => trim((string) ($_POST['subject'] ?? 'Provider support request')),
                    'description' => trim((string) ($_POST['description'] ?? '')),
                    'linked_record_type' => $linkedType,
                    'linked_record_ref' => $linkedRef,
                ], $user);
                redirect_to('support.php?message=' . rawurlencode('Support ticket ' . $ref . ' has been opened.') . '&ticket=' . rawurlencode($ref));
            }
            if ($action === 'reply_ticket') {
                $ref = preg_replace('/[^A-Z0-9-]/i', '', (string) ($_POST['ticket_ref'] ?? ''));
                $ticket = support_ticket_by_ref($pdo, $ref);
                if (!$ticket || (int) ($ticket['user_id'] ?? 0) !== (int) $user['id']) {
                    throw new RuntimeException('Ticket not found for this provider account.');
                }
                $reply = trim((string) ($_POST['reply'] ?? ''));
                if ($reply === '') {
                    throw new RuntimeException('Enter a reply before sending.');
                }
                $msgId = support_add_message($pdo, (int) $ticket['id'], $reply, $user, false, 'public', (string) ($user['name'] ?? 'Provider'), support_role_key($user));
                if (!empty($_FILES['attachments']) || !empty($_FILES['attachment'])) {
                    support_process_uploaded_files($pdo, (int) $ticket['id'], $msgId > 0 ? $msgId : null, $_FILES['attachments'] ?? $_FILES['attachment'], (int) $user['id']);
                }
                $pdo->prepare("UPDATE support_tickets SET status = IF(status IN ('resolved','closed','rejected'), 'open', status), last_activity_at = NOW() WHERE id = ?")->execute([(int) $ticket['id']]);
                redirect_to('support.php?message=' . rawurlencode('Reply added to ticket ' . $ref . '.') . '&ticket=' . rawurlencode($ref));
            }
        } catch (Throwable $e) {
            $error = $e instanceof RuntimeException ? $e->getMessage() : 'Unable to update support ticket.';
        }
    }
}

$tickets = support_user_tickets($pdo, (int) $user['id']);
if ($selectedRef === '' && $tickets) {
    $selectedRef = (string) $tickets[0]['ticket_ref'];
}
$selected = null;
$conversation = [];
if ($selectedRef !== '') {
    $candidate = support_ticket_by_ref($pdo, $selectedRef);
    if ($candidate && (int) ($candidate['user_id'] ?? 0) === (int) $user['id']) {
        $selected = $candidate;
        $conversation = support_messages_with_attachments($pdo, (int) $selected['id']);
    }
}
$openCount = count(array_filter($tickets, static fn(array $ticket): bool => !in_array((string) $ticket['status'], ['resolved', 'closed', 'rejected'], true)));

provider_page_start('Provider Support Desk', 'support', $user, $provider, $counts);
?>
<div class="page-head">
  <div>
    <h1>Provider Support Desk</h1>
    <p>Registration, accreditation, marketplace, Academy, wallet, and order help in one provider workspace.</p>
  </div>
  <a class="btn light" href="../admin/support.php" title="Admins and Super Admins manage the support queue"><i class="fas fa-user-shield"></i> Admin Managed</a>
</div>
<?php if ($message): ?><div class="notice ok"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="notice err"><?= e($error) ?></div><?php endif; ?>

<div class="kpis">
  <div class="kpi"><i class="fas fa-headset"></i><span><b><?= count($tickets) ?></b><br>All Tickets</span></div>
  <div class="kpi"><i class="fas fa-circle-exclamation"></i><span><b><?= (int) $openCount ?></b><br>Open Issues</span></div>
  <div class="kpi"><i class="fas fa-store"></i><span><b><?= (int) $counts['activeListings'] ?></b><br>Listings</span></div>
  <div class="kpi"><i class="fas fa-cart-shopping"></i><span><b><?= (int) $counts['orders'] ?></b><br>Orders</span></div>
  <div class="kpi"><i class="fas fa-graduation-cap"></i><span><b><?= (int) $counts['academy'] ?></b><br>Courses</span></div>
  <div class="kpi"><i class="fas fa-wallet"></i><span><b><?= e(marketplace_money((float) $counts['wallet'])) ?></b><br>Wallet</span></div>
</div>

<div class="grid">
  <section class="card span-6">
    <div class="card-head"><h2>Open Provider Ticket</h2><span class="badge">Admin/Super Admin queue</span></div>
    <form method="post" enctype="multipart/form-data" class="form-grid">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="create_ticket">
      <label>Category<select name="category"><?php foreach ($categories as $key => $cat): ?><option value="<?= e($key) ?>" <?= $key === 'provider' ? 'selected' : '' ?>><?= e((string) $cat['label']) ?></option><?php endforeach; ?></select></label>
      <label>Priority<select name="priority"><?php foreach ($priorities as $key => $label): ?><option value="<?= e($key) ?>" <?= $key === 'medium' ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
      <label>Related Area<select name="linked_record_type"><option value="provider_profile">Provider Profile</option><option value="accreditation">Accreditation</option><option value="marketplace_listing">Marketplace Listing</option><option value="marketplace_order">Marketplace Order</option><option value="academy_certificate">Academy Certificate</option><option value="wallet_transaction">Wallet Transaction</option></select></label>
      <label>Reference<input name="linked_record_ref" placeholder="Order, product, wallet, or certificate ref"></label>
      <label class="wide">Subject<input name="subject" required placeholder="Short summary of the issue"></label>
      <label class="wide">Message<textarea name="description" required placeholder="Explain what happened, what you expected, and any reference numbers admins need."></textarea></label>
      <label class="wide">Attach File(s) (Optional)<input type="file" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.txt,.csv,.xls,.xlsx"></label>
      <div class="wide"><button class="btn" type="submit"><i class="fas fa-paper-plane"></i> Send To Support</button></div>
    </form>
  </section>

  <section class="card span-6">
    <div class="card-head"><h2>Provider Help Guide</h2><a class="view" href="../support/index.php?category=provider">Public Help</a></div>
    <div class="list">
      <?php foreach ($guide as $item): ?>
        <div class="row"><div><strong><?= e($item[0]) ?></strong><br><small><?= e($item[1]) ?></small></div><a class="badge" href="support.php?category=<?= e($item[2]) ?>"><?= e($categories[$item[2]]['label'] ?? 'Support') ?></a></div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="card span-5">
    <div class="card-head"><h2>My Tickets</h2><span class="badge"><?= count($tickets) ?></span></div>
    <div class="list">
      <?php foreach ($tickets as $ticket): ?>
        <a class="row" href="support.php?ticket=<?= e((string) $ticket['ticket_ref']) ?>">
          <div><strong><?= e((string) $ticket['ticket_ref']) ?></strong><br><small><?= e((string) $ticket['subject']) ?></small></div>
          <span class="badge <?= e(support_badge_class((string) $ticket['status'])) ?>"><?= e($statuses[(string) $ticket['status']] ?? (string) $ticket['status']) ?></span>
        </a>
      <?php endforeach; ?>
      <?php if (!$tickets): ?><div class="row"><span>No support tickets yet.</span></div><?php endif; ?>
    </div>
  </section>

  <section class="card span-7">
    <div class="card-head"><h2>Ticket Conversation</h2><?php if ($selected): ?><span class="badge <?= e(support_badge_class((string) $selected['priority'])) ?>"><?= e($priorities[(string) $selected['priority']] ?? (string) $selected['priority']) ?></span><?php endif; ?></div>
    <?php if ($selected): ?>
      <p><strong><?= e((string) $selected['ticket_ref']) ?></strong> - <?= e((string) $selected['subject']) ?><br><small><?= e($categories[(string) $selected['category']]['label'] ?? (string) $selected['category']) ?> / <?= e($statuses[(string) $selected['status']] ?? (string) $selected['status']) ?></small></p>
      <div class="list">
        <?php foreach ($conversation as $msg): ?>
          <div class="row"><div><strong><?= e((string) $msg['author_name']) ?></strong> <small><?= e(support_role_label((string) $msg['author_role'])) ?></small><br><?= nl2br(e((string) $msg['message'])) ?><?php if (!empty($msg['attachments'])): ?><div style="margin-top:6px;padding-top:4px;display:flex;flex-wrap:wrap;gap:6px;"><?php foreach ($msg['attachments'] as $att): ?><a href="../support/attachment.php?id=<?= (int) $att['id'] ?>" target="_blank" class="badge" style="text-decoration:none;"><i class="fas fa-paperclip"></i> <?= e((string) $att['original_name']) ?> (<?= e(support_format_bytes((int) ($att['file_size'] ?? 0))) ?>)</a><?php endforeach; ?></div><?php endif; ?></div><small><?= e(date('M j, g:i A', strtotime((string) $msg['created_at']))) ?></small></div>
        <?php endforeach; ?>
      </div>
      <form method="post" enctype="multipart/form-data" class="form-grid" style="margin-top:14px">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="reply_ticket">
        <input type="hidden" name="ticket_ref" value="<?= e((string) $selected['ticket_ref']) ?>">
        <label class="wide">Reply<textarea name="reply" required placeholder="Add more details, corrections, or confirmation for support."></textarea></label>
        <label class="wide">Attach File(s) (Optional)<input type="file" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.txt,.csv,.xls,.xlsx"></label>
        <div class="wide"><button class="btn" type="submit"><i class="fas fa-reply"></i> Add Reply</button></div>
      </form>
    <?php else: ?>
      <p>Select a ticket to see the conversation, or create a new support ticket.</p>
    <?php endif; ?>
  </section>
</div>
<?php provider_page_end(); ?>
