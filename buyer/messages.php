<?php
declare(strict_types=1);
require_once __DIR__ . '/_buyer.php';

$pdo = buyer_boot();
$user = buyer_require($pdo);
$inquiryStmt = $pdo->prepare("
    SELECT i.*, l.title listing_title
    FROM marketplace_inquiries i
    JOIN marketplace_listings l ON l.id = i.listing_id
    WHERE i.buyer_user_id = ?
    ORDER BY i.created_at DESC
    LIMIT 30
");
$inquiryStmt->execute([(int) $user['id']]);
$inquiries = $inquiryStmt->fetchAll();
$ticketStmt = $pdo->prepare("
    SELECT t.ticket_ref, t.subject, t.status, m.author_name, m.author_role, m.message, m.created_at
    FROM support_tickets t
    JOIN support_ticket_messages m ON m.ticket_id = t.id
    WHERE t.user_id = ? AND m.visibility <> 'internal'
    ORDER BY m.created_at DESC
    LIMIT 30
");
$ticketStmt->execute([(int) $user['id']]);
$supportMessages = $ticketStmt->fetchAll();

buyer_page_start('Buyer Messages', 'messages', $user, buyer_counts($pdo, $user));
?>
<div class="page-head"><div><h1>Buyer Messages</h1><p>Seller inquiries and buyer support conversations tied to your account.</p></div><a class="btn" href="support.php"><i class="fas fa-headset"></i> Support</a></div>
<div class="grid">
  <section class="card span-6">
    <div class="card-head"><h2>Seller & Quote Messages</h2><a class="view" href="quotes.php">Quote requests</a></div>
    <div class="list">
      <?php foreach ($inquiries as $row): ?>
        <div class="row"><span><strong><?= e((string) $row['listing_title']) ?></strong><br><small><?= e((string) $row['inquiry_ref']) ?> / <?= e((string) $row['created_at']) ?></small></span><?= buyer_status_badge((string) $row['status']) ?></div>
      <?php endforeach; ?>
      <?php if (!$inquiries): ?><div class="alert ok">No seller or quote messages yet.</div><?php endif; ?>
    </div>
  </section>
  <section class="card span-6">
    <div class="card-head"><h2>Support Replies</h2><a class="view" href="support.php">All tickets</a></div>
    <div class="list">
      <?php foreach ($supportMessages as $msg): ?>
        <a class="row" href="support.php?ticket=<?= urlencode((string) $msg['ticket_ref']) ?>"><span><strong><?= e((string) $msg['subject']) ?></strong><br><small><?= e((string) $msg['author_name']) ?>: <?= e(substr((string) $msg['message'], 0, 120)) ?></small></span><?= buyer_status_badge((string) $msg['status']) ?></a>
      <?php endforeach; ?>
      <?php if (!$supportMessages): ?><div class="alert ok">No buyer support replies yet.</div><?php endif; ?>
    </div>
  </section>
</div>
<?php buyer_page_end(); ?>
