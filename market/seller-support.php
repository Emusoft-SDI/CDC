<?php
declare(strict_types=1);

require_once __DIR__ . '/_seller.php';
require_once __DIR__ . '/../lib/support.php';

$pdo = market_boot();
support_ensure_schema($pdo);
$user = market_require_user($pdo);
seller_access_or_message($pdo, $user);
$ctx = seller_query_context($pdo, $user, false);
$seller = $ctx['seller'];

$categories = array_intersect_key(support_categories(), array_flip(['marketplace', 'provider', 'payments', 'technical', 'general']));
$priorities = support_priorities();
$statuses = support_statuses();
$message = trim((string) ($_GET['message'] ?? ''));
$error = '';
$selectedRef = preg_replace('/[^A-Z0-9-]/i', '', (string) ($_GET['ticket'] ?? ''));

$guide = [
    ['Listing approval', 'Share the product title or listing ID and explain what needs review or correction.', 'marketplace'],
    ['Buyer inquiry or order', 'Include the checkout, inquiry, or order reference and the buyer name if available.', 'marketplace'],
    ['Refund or dispute', 'Explain the issue, desired outcome, delivery state, and any evidence the admin team should review.', 'marketplace'],
    ['Payout and wallet', 'Include wallet, settlement, payment, or transaction references. Never share full card details.', 'payments'],
    ['Store verification', 'Use Provider / Seller Support for seller profile, accreditation, or approval questions.', 'provider'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid security token.';
    } else {
        try {
            $action = (string) ($_POST['action'] ?? '');
            if ($action === 'create_ticket') {
                $category = (string) ($_POST['category'] ?? 'marketplace');
                if (!isset($categories[$category])) {
                    $category = 'marketplace';
                }
                $priority = (string) ($_POST['priority'] ?? 'medium');
                if (!isset($priorities[$priority])) {
                    $priority = 'medium';
                }
                $ref = support_create_ticket($pdo, [
                    'category' => $category,
                    'priority' => $priority,
                    'name' => (string) ($user['name'] ?? ''),
                    'email' => (string) ($user['email'] ?? ''),
                    'phone' => (string) ($user['phone'] ?? ''),
                    'subject' => trim((string) ($_POST['subject'] ?? 'Marketplace seller support request')),
                    'description' => trim((string) ($_POST['description'] ?? '')),
                    'linked_record_type' => trim((string) ($_POST['linked_record_type'] ?? 'marketplace_seller')),
                    'linked_record_ref' => trim((string) ($_POST['linked_record_ref'] ?? ($seller['store_name'] ?? ''))),
                ], $user);
                redirect_to('seller-support.php?message=' . rawurlencode('Support ticket ' . $ref . ' has been opened.') . '&ticket=' . rawurlencode($ref));
            }
            if ($action === 'reply_ticket') {
                $ref = preg_replace('/[^A-Z0-9-]/i', '', (string) ($_POST['ticket_ref'] ?? ''));
                $ticket = support_ticket_by_ref($pdo, $ref);
                if (!$ticket || (int) ($ticket['user_id'] ?? 0) !== (int) $user['id']) {
                    throw new RuntimeException('Ticket not found for this seller account.');
                }
                $reply = trim((string) ($_POST['reply'] ?? ''));
                if ($reply === '') {
                    throw new RuntimeException('Enter a reply before sending.');
                }
                support_add_message($pdo, (int) $ticket['id'], $reply, $user, false, 'public', (string) ($user['name'] ?? 'Seller'), support_role_key($user));
                $pdo->prepare("UPDATE support_tickets SET status = IF(status IN ('resolved','closed','rejected'), 'open', status), last_activity_at = NOW() WHERE id = ?")->execute([(int) $ticket['id']]);
                redirect_to('seller-support.php?message=' . rawurlencode('Reply added to ticket ' . $ref . '.') . '&ticket=' . rawurlencode($ref));
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
        $conversation = support_ticket_messages($pdo, (int) $selected['id']);
    }
}
$openCount = count(array_filter($tickets, static fn(array $ticket): bool => !in_array((string) $ticket['status'], ['resolved', 'closed', 'rejected'], true)));

seller_header('Seller Support Desk', 'support', $user, $seller);
?>
<?php if ($message): ?><div class="alert ok"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert err"><?= e($error) ?></div><?php endif; ?>

<section class="sc-kpis">
  <div class="sc-card sc-kpi"><span class="sc-icon"><i data-lucide="headphones"></i></span><div><small>All Tickets</small><b><?= count($tickets) ?></b><span>Seller support history</span></div></div>
  <div class="sc-card sc-kpi"><span class="sc-icon orange"><i data-lucide="circle-alert"></i></span><div><small>Open Issues</small><b><?= (int) $openCount ?></b><span>Needs follow-up</span></div></div>
  <div class="sc-card sc-kpi"><span class="sc-icon blue"><i data-lucide="package"></i></span><div><small>Products</small><b><?= count($ctx['listings']) ?></b><span>Store catalog</span></div></div>
  <div class="sc-card sc-kpi"><span class="sc-icon gold"><i data-lucide="shopping-bag"></i></span><div><small>Orders</small><b><?= count($ctx['orders']) ?></b><span>Fulfillment queue</span></div></div>
  <div class="sc-card sc-kpi"><span class="sc-icon"><i data-lucide="message-square"></i></span><div><small>Inquiries</small><b><?= count($ctx['inquiries']) ?></b><span>Buyer messages</span></div></div>
  <div class="sc-card sc-kpi"><span class="sc-icon purple"><i data-lucide="shield-check"></i></span><div><small>Managed By</small><b>Admin</b><span>Super Admin oversight</span></div></div>
</section>

<div class="sc-grid">
  <section class="sc-card sc-panel span-6">
    <div class="sc-panel-head"><h2>Open Seller Ticket</h2><span class="badge good">Admin/Super Admin queue</span></div>
    <form method="post" class="sc-form sc-form-grid">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="create_ticket">
      <div><label>Category</label><select name="category"><?php foreach ($categories as $key => $cat): ?><option value="<?= e($key) ?>" <?= $key === 'marketplace' ? 'selected' : '' ?>><?= e((string) $cat['label']) ?></option><?php endforeach; ?></select></div>
      <div><label>Priority</label><select name="priority"><?php foreach ($priorities as $key => $label): ?><option value="<?= e($key) ?>" <?= $key === 'medium' ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
      <div><label>Related Area</label><select name="linked_record_type"><option value="marketplace_seller">Seller Store</option><option value="marketplace_listing">Product / Listing</option><option value="marketplace_order">Order</option><option value="marketplace_inquiry">Buyer Inquiry</option><option value="wallet_transaction">Wallet / Payout</option><option value="dispute">Dispute / Refund</option></select></div>
      <div><label>Reference</label><input name="linked_record_ref" value="<?= e((string) ($seller['store_name'] ?? '')) ?>" placeholder="Order, listing, inquiry, or payout ref"></div>
      <div class="wide"><label>Subject</label><input name="subject" required placeholder="Short summary of the seller issue"></div>
      <div class="wide"><label>Message</label><textarea name="description" required placeholder="Explain what happened and what admin support should check."></textarea></div>
      <div class="wide"><button class="sc-btn" type="submit">Send To Support</button></div>
    </form>
  </section>

  <section class="sc-card sc-panel span-6">
    <div class="sc-panel-head"><h2>Seller Help Guide</h2><a class="sc-link" href="../support/index.php?category=marketplace">Public Help</a></div>
    <div class="sc-list">
      <?php foreach ($guide as $item): ?>
        <div class="sc-row"><span class="sc-icon"><i data-lucide="life-buoy"></i></span><div><strong><?= e($item[0]) ?></strong><br><small class="muted"><?= e($item[1]) ?></small></div><span class="badge"><?= e($categories[$item[2]]['label'] ?? 'Support') ?></span></div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="sc-card sc-panel span-5">
    <div class="sc-panel-head"><h2>My Tickets</h2><span class="badge"><?= count($tickets) ?></span></div>
    <div class="sc-list">
      <?php foreach ($tickets as $ticket): ?>
        <a class="sc-row" href="seller-support.php?ticket=<?= e((string) $ticket['ticket_ref']) ?>"><span class="sc-icon"><i data-lucide="ticket"></i></span><div><strong><?= e((string) $ticket['ticket_ref']) ?></strong><br><small class="muted"><?= e((string) $ticket['subject']) ?></small></div><span class="badge"><?= e($statuses[(string) $ticket['status']] ?? (string) $ticket['status']) ?></span></a>
      <?php endforeach; ?>
      <?php if (!$tickets): ?><div class="empty">No seller support tickets yet.</div><?php endif; ?>
    </div>
  </section>

  <section class="sc-card sc-panel span-7">
    <div class="sc-panel-head"><h2>Ticket Conversation</h2><?php if ($selected): ?><span class="badge warn"><?= e($priorities[(string) $selected['priority']] ?? (string) $selected['priority']) ?></span><?php endif; ?></div>
    <?php if ($selected): ?>
      <p><strong><?= e((string) $selected['ticket_ref']) ?></strong> - <?= e((string) $selected['subject']) ?><br><small class="muted"><?= e($categories[(string) $selected['category']]['label'] ?? (string) $selected['category']) ?> / <?= e($statuses[(string) $selected['status']] ?? (string) $selected['status']) ?></small></p>
      <div class="sc-list">
        <?php foreach ($conversation as $msg): ?>
          <div class="sc-row"><span class="sc-icon"><i data-lucide="message-circle"></i></span><div><strong><?= e((string) $msg['author_name']) ?></strong> <small class="muted"><?= e(support_role_label((string) $msg['author_role'])) ?></small><br><?= nl2br(e((string) $msg['message'])) ?></div><small class="muted"><?= e(date('M j, g:i A', strtotime((string) $msg['created_at']))) ?></small></div>
        <?php endforeach; ?>
      </div>
      <form method="post" class="sc-form" style="margin-top:14px">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="reply_ticket">
        <input type="hidden" name="ticket_ref" value="<?= e((string) $selected['ticket_ref']) ?>">
        <label>Reply</label><textarea name="reply" required placeholder="Add more details or confirm the issue is fixed."></textarea>
        <button class="sc-btn" type="submit">Add Reply</button>
      </form>
    <?php else: ?>
      <div class="empty">Select a ticket to see the conversation, or create a new seller support ticket.</div>
    <?php endif; ?>
  </section>
</div>
<?php seller_footer(); ?>
