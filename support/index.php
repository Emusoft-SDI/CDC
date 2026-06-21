<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/support.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$pdo = db();
support_ensure_schema($pdo);
$user = current_user($pdo);
$categories = support_categories();
$priorities = support_priorities();
$message = '';
$error = '';
$createdRef = preg_replace('/[^A-Z0-9-]/i', '', (string) ($_GET['created'] ?? ''));
$lookupRef = preg_replace('/[^A-Z0-9-]/i', '', (string) ($_GET['ticket'] ?? ''));
$lookupEmail = trim((string) ($_GET['email'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Security token expired. Refresh and try again.';
    } else {
        try {
            $action = (string) ($_POST['action'] ?? 'create');
            if ($action === 'reply') {
                $ref = preg_replace('/[^A-Z0-9-]/i', '', (string) ($_POST['ticket_ref'] ?? ''));
                $ticket = support_ticket_by_ref($pdo, $ref);
                if (!$ticket) {
                    throw new RuntimeException('Ticket not found.');
                }
                if (!$user && strcasecmp((string) $ticket['requester_email'], trim((string) ($_POST['email'] ?? ''))) !== 0) {
                    throw new RuntimeException('Enter the same email used to open this public ticket.');
                }
                if ($user && (int) ($ticket['user_id'] ?? 0) > 0 && (int) $ticket['user_id'] !== (int) $user['id']) {
                    throw new RuntimeException('You do not have access to this ticket.');
                }
                if (in_array((string) $ticket['status'], ['resolved', 'closed', 'rejected'], true)) {
                    throw new RuntimeException('This ticket is closed. Open a new request if you need more help.');
                }
                support_add_message($pdo, (int) $ticket['id'], (string) ($_POST['reply'] ?? ''), $user, false, 'public', $user['name'] ?? $ticket['requester_name'], support_role_key($user));
                $pdo->prepare("UPDATE support_tickets SET status = IF(status = 'waiting_on_user', 'open', status), last_activity_at = NOW() WHERE id = ?")->execute([(int) $ticket['id']]);
                redirect_to('index.php?ticket=' . urlencode($ref) . '&email=' . urlencode((string) $ticket['requester_email']));
            }

            $ref = support_create_ticket($pdo, [
                'name' => $_POST['name'] ?? null,
                'email' => $_POST['email'] ?? null,
                'phone' => $_POST['phone'] ?? null,
                'category' => $_POST['category'] ?? 'general',
                'module' => $_POST['module'] ?? null,
                'priority' => $_POST['priority'] ?? 'medium',
                'subject' => $_POST['subject'] ?? '',
                'description' => $_POST['description'] ?? '',
                'linked_record_type' => $_POST['linked_record_type'] ?? '',
                'linked_record_ref' => $_POST['linked_record_ref'] ?? '',
            ], $user);
            redirect_to('index.php?created=' . urlencode($ref) . '&ticket=' . urlencode($ref) . '&email=' . urlencode((string) ($_POST['email'] ?? ($user['email'] ?? ''))));
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$selectedTicket = null;
$conversation = [];
if ($lookupRef !== '') {
    $candidate = support_ticket_by_ref($pdo, $lookupRef);
    if ($candidate) {
        $allowed = false;
        if ($user && (int) ($candidate['user_id'] ?? 0) === (int) $user['id']) {
            $allowed = true;
        } elseif (!$user && $lookupEmail !== '' && strcasecmp((string) $candidate['requester_email'], $lookupEmail) === 0) {
            $allowed = true;
        } elseif ($user && (int) ($candidate['user_id'] ?? 0) === 0 && strcasecmp((string) $candidate['requester_email'], (string) $user['email']) === 0) {
            $allowed = true;
        }
        if ($allowed) {
            $selectedTicket = $candidate;
            $conversation = support_ticket_messages($pdo, (int) $candidate['id'], false);
        } else {
            $error = 'Ticket found, but the email or account does not match the requester.';
        }
    } else {
        $error = 'Ticket reference not found.';
    }
}

$myTickets = $user ? support_user_tickets($pdo, (int) $user['id']) : [];
$stats = ['open' => 0, 'waiting_on_user' => 0, 'in_progress' => 0, 'resolved' => 0, 'all' => count($myTickets)];
foreach ($myTickets as $ticket) {
    $status = (string) $ticket['status'];
    $stats[$status] = ($stats[$status] ?? 0) + 1;
}
$logo = app_primary_logo_url();
$prefillCategory = preg_replace('/[^a-z0-9_-]/i', '', (string) ($_GET['category'] ?? $_GET['topic'] ?? ''));
if (!isset($categories[$prefillCategory])) {
    $prefillCategory = 'general';
}
$upgradePaths = [
    [
        'icon' => 'fa-seedling',
        'title' => 'Register as Grower',
        'text' => 'Join the NATCODEV grower registry, manage farm records, documents, verification, certificates, and wallet tools.',
        'href' => '../apply.php',
        'label' => 'Start grower registration',
    ],
    [
        'icon' => 'fa-graduation-cap',
        'title' => 'Join Academy',
        'text' => 'Create a learner account, enroll in courses, complete assessments, and request verifiable certificates.',
        'href' => '../academy/register.php',
        'label' => 'Register as learner',
    ],
    [
        'icon' => 'fa-store',
        'title' => 'Use Marketplace',
        'text' => 'Browse coconut value-chain products, services, orders, sellers, and verified marketplace offers.',
        'href' => '../marketplace/index.php',
        'label' => 'Open marketplace',
    ],
    [
        'icon' => 'fa-cart-shopping',
        'title' => 'Become a Buyer',
        'text' => 'Create a buyer profile for quotes, orders, messages, purchase history, and marketplace support.',
        'href' => '../buyer/register.php',
        'label' => 'Register as buyer',
    ],
    [
        'icon' => 'fa-handshake-angle',
        'title' => 'Provider / Seller Access',
        'text' => 'Prepare provider accreditation, service listings, product catalog, coverage areas, and fulfillment operations.',
        'href' => '../provider/accreditation.php',
        'label' => 'Start provider path',
    ],
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>NATCODEV Support Desk</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    :root{--green:#075f2a;--deep:#053b1c;--line:#dfe8d8;--bg:#f7faf5;--ink:#152019;--muted:#667085;--gold:#c69320;--red:#b42318;--blue:#175cd3}
    *{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font-family:"Segoe UI",Arial,sans-serif}a{text-decoration:none;color:inherit}.top{background:#fff;border-bottom:1px solid var(--line);position:sticky;top:0;z-index:20}.bar{max-width:1520px;margin:auto;padding:14px 22px;display:flex;align-items:center;justify-content:space-between;gap:16px}.brand{display:flex;align-items:center;gap:12px;color:var(--green);font-weight:950}.brand img{width:52px;height:52px;border-radius:50%;object-fit:contain}.nav{display:flex;gap:8px;flex-wrap:wrap}.nav a{padding:9px 12px;border-radius:8px;font-weight:850;color:#344054}.nav a:hover,.btn{background:var(--green);color:#fff}.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;border:1px solid var(--green);border-radius:8px;padding:10px 14px;font-weight:950;cursor:pointer}.btn.light{background:#fff;color:var(--green);border-color:var(--line)}button{font-family:inherit}.wrap{max-width:1520px;margin:auto;padding:22px}.hero{display:grid;grid-template-columns:minmax(0,1.05fr) minmax(360px,.95fr);gap:16px;align-items:stretch}.panel{background:#fff;border:1px solid var(--line);border-radius:8px;padding:18px;box-shadow:0 10px 30px rgba(16,24,40,.05)}.head{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;margin-bottom:14px}.head h1,.head h2{margin:0;color:var(--green)}.muted{color:var(--muted);line-height:1.55}.grid{display:grid;gap:14px}.g2{grid-template-columns:repeat(2,minmax(0,1fr))}.g3{grid-template-columns:repeat(3,minmax(0,1fr))}.g4{grid-template-columns:repeat(4,minmax(0,1fr))}.stat{border:1px solid var(--line);border-radius:8px;padding:12px;background:#fbfdf9}.stat b{display:block;color:var(--green);font-size:1.6rem}.cat{border:1px solid var(--line);border-radius:8px;padding:13px;background:#fff;display:flex;gap:12px;align-items:flex-start}.cat i{width:34px;height:34px;border-radius:8px;background:#edf7ef;color:var(--green);display:grid;place-items:center}.cat strong{display:block;color:#15391f}.cat span{display:block;font-size:.82rem;color:var(--muted);margin-top:4px}.upgrade{margin-top:16px;background:linear-gradient(135deg,#fff,#f5fbf2);border:1px solid var(--line);border-radius:8px;padding:18px}.upgrade-card{border:1px solid var(--line);border-radius:8px;background:#fff;padding:14px;display:flex;flex-direction:column;gap:10px}.upgrade-card i{width:42px;height:42px;border-radius:8px;background:#edf7ef;color:var(--green);display:grid;place-items:center;font-size:1.1rem}.upgrade-card h3{margin:0;color:#15391f}.upgrade-card p{margin:0}.upgrade-card a{margin-top:auto}.form-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.field-full{grid-column:1/-1}label{display:block;font-weight:850;font-size:.86rem;margin-bottom:5px}input,select,textarea{width:100%;border:1px solid var(--line);border-radius:7px;padding:10px 11px;font-family:inherit;background:#fff}textarea{min-height:120px;resize:vertical}.notice{border-radius:8px;padding:12px 14px;margin-bottom:14px;font-weight:850}.ok{background:#dcfae6;color:#067647}.err{background:#fee4e2;color:#b42318}.badge{display:inline-flex;align-items:center;border-radius:999px;padding:4px 9px;font-size:.78rem;font-weight:900}.badge.ok{background:#dcfae6;color:#067647}.badge.info{background:#dbeafe;color:#175cd3}.badge.warn{background:#fef0c7;color:#b54708}.badge.bad{background:#fee4e2;color:#b42318}.badge.neutral{background:#f2f4f7;color:#475467}.ticket{border:1px solid var(--line);border-radius:8px;padding:12px;background:#fff}.ticket.active{border-color:var(--green);background:#f2fbf5}.conversation{display:grid;gap:10px}.msg{border:1px solid var(--line);border-radius:8px;padding:12px;background:#fbfdf9;max-width:760px}.msg.agent{margin-left:auto;background:#eef7ff}.support-flow{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:10px;margin-top:18px}.flow{border:1px solid var(--line);border-radius:8px;padding:12px;background:#fff;text-align:center}.flow i{color:var(--green);font-size:1.2rem}.footer{background:var(--deep);color:#fff;margin-top:20px}.footer .bar{align-items:center}.footer a{color:#f6df85}@media(max-width:1100px){.hero,.g4,.support-flow{grid-template-columns:1fr 1fr}.form-grid{grid-template-columns:1fr 1fr}}@media(max-width:760px){.bar,.head{flex-direction:column;align-items:flex-start}.hero,.g2,.g3,.g4,.support-flow,.form-grid{grid-template-columns:1fr}.wrap{padding:14px}.msg.agent{margin-left:0}}
  </style>
</head>
<body>
<header class="top"><div class="bar"><a class="brand" href="../index.php"><img src="<?= e($logo) ?>" alt="NATCODEV"><span>NATCODEV<br><small>Support Desk</small></span></a><nav class="nav"><a href="#new-ticket">New Ticket</a><a href="#lookup">Track Ticket</a><a href="#upgrade">Upgrade Services</a><a href="../academy/index.php">Academy</a><a href="../marketplace/index.php">Marketplace</a><?php if ($user): ?><a class="btn light" href="../dashboard/index.php">Dashboard</a><?php else: ?><a class="btn light" href="login.php?next=index.php">Track Existing Ticket</a><?php endif; ?></nav></div></header>

<main class="wrap">
  <?php if ($createdRef): ?><div class="notice ok">Ticket <?= e($createdRef) ?> has been submitted. Keep this reference for tracking.</div><?php endif; ?>
  <?php if ($message): ?><div class="notice ok"><?= e($message) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="notice err"><?= e($error) ?></div><?php endif; ?>

  <section class="hero">
    <article class="panel">
      <div class="head"><div><h1>Support Desk, Requests & Resolution Flows</h1><p class="muted">Fast help, clear updates, and resolved issues for public visitors and registered NATCODEV users.</p></div><span class="badge ok">Available 24/7</span></div>
      <div class="grid g4">
        <div class="stat"><span>Open</span><b><?= (int) ($stats['open'] ?? 0) ?></b></div>
        <div class="stat"><span>Waiting on You</span><b><?= (int) ($stats['waiting_on_user'] ?? 0) ?></b></div>
        <div class="stat"><span>In Progress</span><b><?= (int) ($stats['in_progress'] ?? 0) ?></b></div>
        <div class="stat"><span>Resolved</span><b><?= (int) ($stats['resolved'] ?? 0) ?></b></div>
      </div>
      <h2 style="margin-top:18px">Popular Categories</h2>
      <div class="grid g3">
        <?php foreach ($categories as $key => $cat): ?>
          <a class="cat" href="#new-ticket" onclick="document.getElementById('category').value='<?= e($key) ?>'"><i class="fas <?= e($cat['icon']) ?>"></i><span><strong><?= e($cat['label']) ?></strong><span><?= e($cat['team']) ?> / <?= e(ucwords(str_replace('_', ' ', $cat['module']))) ?></span></span></a>
        <?php endforeach; ?>
      </div>
    </article>

    <article class="panel" id="new-ticket">
      <div class="head"><h2>New Ticket</h2><span class="badge info"><?= e(support_role_label(support_role_key($user))) ?></span></div>
      <form method="post">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="create">
        <div class="form-grid">
          <div><label>Name</label><input name="name" value="<?= e((string) ($user['name'] ?? '')) ?>" required></div>
          <div><label>Email</label><input type="email" name="email" value="<?= e((string) ($user['email'] ?? '')) ?>" required></div>
          <div><label>Phone</label><input name="phone" value=""></div>
          <div><label>Category</label><select id="category" name="category"><?php foreach ($categories as $key => $cat): ?><option value="<?= e($key) ?>" <?= $prefillCategory === $key ? 'selected' : '' ?>><?= e($cat['label']) ?></option><?php endforeach; ?></select></div>
          <div><label>Priority</label><select name="priority"><?php foreach ($priorities as $key => $label): ?><option value="<?= e($key) ?>" <?= $key === 'medium' ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
          <div><label>Linked Record Type</label><select name="linked_record_type"><option value="">None</option><option value="wallet_transaction">Wallet Transaction</option><option value="course_enrollment">Course Enrollment</option><option value="order">Order</option><option value="certificate">Certificate</option><option value="application">Application</option></select></div>
          <div class="field-full"><label>Issue Title</label><input name="subject" placeholder="Example: Refund not received for course payment" required></div>
          <div class="field-full"><label>Description</label><textarea name="description" placeholder="Tell us what happened, the date, amount/reference if any, and the outcome you need." required></textarea></div>
          <div class="field-full"><label>Linked Record Reference</label><input name="linked_record_ref" placeholder="Transaction ID, certificate ref, order ref, application ref, or course name"></div>
        </div>
        <p><button class="btn" type="submit"><i class="fas fa-paper-plane"></i> Submit Ticket</button></p>
      </form>
    </article>
  </section>

  <section class="upgrade" id="upgrade">
    <div class="head">
      <div>
        <h2>Need More Than Support?</h2>
        <p class="muted">A public support ticket does not make someone a platform stakeholder. If the person wants NATCODEV services, send them through the correct registration or onboarding path below.</p>
      </div>
      <span class="badge info">Support-to-service upgrade</span>
    </div>
    <div class="grid g3">
      <?php foreach ($upgradePaths as $path): ?>
        <article class="upgrade-card">
          <i class="fas <?= e($path['icon']) ?>"></i>
          <h3><?= e($path['title']) ?></h3>
          <p class="muted"><?= e($path['text']) ?></p>
          <a class="btn light" href="<?= e($path['href']) ?>"><?= e($path['label']) ?></a>
        </article>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="hero" style="margin-top:16px">
    <article class="panel" id="lookup">
      <div class="head"><h2><?= $user ? 'My Tickets' : 'Track Public Ticket' ?></h2><span class="badge neutral"><?= (int) count($myTickets) ?> ticket(s)</span></div>
      <?php if (!$user): ?>
        <form method="get" class="form-grid">
          <div><label>Ticket Reference</label><input name="ticket" value="<?= e($lookupRef) ?>" required></div>
          <div><label>Email Used</label><input type="email" name="email" value="<?= e($lookupEmail) ?>" required></div>
          <div style="display:flex;align-items:end"><button class="btn light" type="submit">Track Ticket</button></div>
        </form>
      <?php endif; ?>
      <div class="grid" style="margin-top:14px">
        <?php foreach ($myTickets as $ticket): ?><a class="ticket <?= $selectedTicket && (int) $selectedTicket['id'] === (int) $ticket['id'] ? 'active' : '' ?>" href="index.php?ticket=<?= e((string) $ticket['ticket_ref']) ?>&email=<?= e((string) $ticket['requester_email']) ?>"><strong><?= e((string) $ticket['ticket_ref']) ?></strong><br><span class="muted"><?= e((string) $ticket['subject']) ?></span><br><span class="badge <?= e(support_badge_class((string) $ticket['status'])) ?>"><?= e(support_statuses()[(string) $ticket['status']] ?? (string) $ticket['status']) ?></span></a><?php endforeach; ?>
        <?php if ($user && !$myTickets): ?><div class="ticket">No ticket yet. Submit a request above.</div><?php endif; ?>
      </div>
    </article>

    <article class="panel">
      <div class="head"><h2>Ticket Detail</h2><?php if ($selectedTicket): ?><span class="badge <?= e(support_badge_class((string) $selectedTicket['priority'])) ?>"><?= e(ucfirst((string) $selectedTicket['priority'])) ?> Priority</span><?php endif; ?></div>
      <?php if ($selectedTicket): ?>
        <h3><?= e((string) $selectedTicket['subject']) ?></h3>
        <p class="muted"><?= e((string) $selectedTicket['ticket_ref']) ?> / <?= e($categories[(string) $selectedTicket['category']]['label'] ?? (string) $selectedTicket['category']) ?> / <?= e((string) $selectedTicket['assigned_team']) ?></p>
        <div class="conversation">
          <?php foreach ($conversation as $msg): ?><div class="msg <?= $msg['admin_id'] ? 'agent' : '' ?>"><strong><?= e((string) $msg['author_name']) ?></strong><p><?= nl2br(e((string) $msg['message'])) ?></p><small class="muted"><?= e(date('M j, Y g:i A', strtotime((string) $msg['created_at']))) ?></small></div><?php endforeach; ?>
        </div>
        <?php if (!in_array((string) $selectedTicket['status'], ['resolved', 'closed', 'rejected'], true)): ?>
          <form method="post">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="reply">
            <input type="hidden" name="ticket_ref" value="<?= e((string) $selectedTicket['ticket_ref']) ?>">
            <input type="hidden" name="email" value="<?= e((string) $selectedTicket['requester_email']) ?>">
            <label>Reply</label><textarea name="reply" required></textarea>
            <p><button class="btn" type="submit">Send Reply</button></p>
          </form>
        <?php endif; ?>
      <?php else: ?>
        <p class="muted">Select a ticket or use the lookup form to view the conversation and admin updates.</p>
      <?php endif; ?>
    </article>
  </section>

  <section class="support-flow" aria-label="Support resolution flow">
    <?php foreach ([['fa-comments','Open Support'],['fa-plus','Submit Issue'],['fa-people-arrows','Routed to Team'],['fa-reply','Response / Action'],['fa-circle-check','Resolve'],['fa-star','Rate & Feedback'],['fa-chart-simple','Report & Improve']] as $step): ?><div class="flow"><i class="fas <?= e($step[0]) ?>"></i><br><strong><?= e($step[1]) ?></strong></div><?php endforeach; ?>
  </section>
</main>

<footer class="footer"><div class="bar"><span><i class="fas fa-shield-halved"></i> Your data is secure and confidential.</span><span><a href="mailto:support@natcodev.org">support@natcodev.org</a> / Available 24/7</span></div></footer>
</body>
</html>
