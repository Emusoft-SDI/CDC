<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/support.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$pdo = db();
support_ensure_schema($pdo);
$user = current_user($pdo);
$categories = support_categories();
$priorities = support_priorities();
$office = app_contact_office();
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

            if ($action === 'rate_ticket') {
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
                $rating = (int) ($_POST['rating'] ?? 0);
                if ($rating < 1 || $rating > 5) {
                    throw new RuntimeException('Invalid rating.');
                }
                $comment = trim((string) ($_POST['feedback_comment'] ?? ''));
                $stmt = $pdo->prepare("UPDATE support_tickets SET rating = ?, feedback_comment = ? WHERE id = ?");
                $stmt->execute([$rating, $comment, (int) $ticket['id']]);
                
                $message = 'Thank you for rating our support experience!';
                redirect_to('index.php?ticket=' . urlencode($ref) . '&email=' . urlencode((string) $ticket['requester_email']));
            }

            $rateAction = $action === 'reply' ? 'support_public_reply' : 'support_public_create';
            if (!app_check_rate_limit($rateAction, $action === 'reply' ? 12 : 5, 900)) {
                throw new RuntimeException($action === 'reply' ? 'Too many support replies. Please try again in 15 minutes.' : 'Too many support tickets submitted. Please try again in 15 minutes.');
            }
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
    if (!$user && !app_check_rate_limit('support_public_lookup', 20, 900)) {
        $error = 'Too many ticket lookup attempts. Please try again in 15 minutes.';
    } else {
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
        'href' => '../market/index.php',
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
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/support-public.css">
</head>
<body>
<?php require __DIR__ . '/../lib/layout-components/support-header.php'; ?>

<main class="wrap">
  <div class="support-center">
<?php require __DIR__ . '/../lib/layout-components/support-sidebar.php'; ?>
    <section class="support-main">
  <?php if ($createdRef): ?><div class="notice ok">Ticket <?= e($createdRef) ?> has been submitted. Keep this reference for tracking.</div><?php endif; ?>
  <?php if ($message): ?><div class="notice ok"><?= e($message) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="notice err"><?= e($error) ?></div><?php endif; ?>

    <?php
    $view = $_GET['view'] ?? 'new-ticket';
    $allowed = ['new-ticket', 'upgrade', 'lookup', 'knowledge', 'support-flow'];
    if (!in_array($view, $allowed, true)) {
        $view = 'new-ticket';
    }
    require __DIR__ . '/modules/' . $view . '.php';
    ?>
    </section>
  </div>
</main>

<footer class="footer"><div class="bar"><span><i class="fas fa-shield-halved"></i> Your data is secure and confidential.</span><span><i class="fas fa-location-dot"></i> <?= e(implode(', ', $office['address_lines'])) ?></span><span><a href="tel:<?= e($office['phone_tel']) ?>"><?= e($office['phone_display']) ?></a> / <a href="mailto:support@natcodev.com.ng">support@natcodev.com.ng</a></span></div></footer>
<script src="../assets/js/support-public.js"></script></body>
</html>

