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
  <style>
    :root {
      --green: #075f2a;
      --deep: #053b1c;
      --green-light: #edf7ef;
      --green-gradient: linear-gradient(135deg, #075f2a 0%, #053b1c 100%);
      --line: #e2e8f0;
      --bg: #f8faf6;
      --card-bg: #ffffff;
      --ink: #0f172a;
      --muted: #64748b;
      --gold: #d97706;
      --gold-light: #fef3c7;
      --red: #dc2626;
      --red-light: #fee2e2;
      --blue: #2563eb;
      --blue-light: #dbeafe;
      --shadow-sm: 0 1px 3px rgba(16,24,40,0.05);
      --shadow-md: 0 4px 20px -2px rgba(16,24,40,0.08);
      --shadow-lg: 0 12px 30px -4px rgba(16,24,40,0.12);
      --radius-sm: 8px;
      --radius-md: 12px;
      --radius-lg: 18px;
      --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    * {
      box-sizing: border-box;
      outline: none;
    }
    
    body {
      margin: 0;
      background: var(--bg);
      color: var(--ink);
      font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      -webkit-font-smoothing: antialiased;
    }
    
    a {
      text-decoration: none;
      color: inherit;
      transition: var(--transition);
    }
    
    /* Navigation Bar */
    .top {
      background: rgba(255, 255, 255, 0.85);
      backdrop-filter: blur(16px);
      -webkit-backdrop-filter: blur(16px);
      border-bottom: 1px solid rgba(226, 232, 240, 0.8);
      position: sticky;
      top: 0;
      z-index: 100;
      box-shadow: var(--shadow-sm);
    }
    
    .bar {
      max-width: 1520px;
      margin: auto;
      padding: 14px 24px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
    }
    
    .brand {
      display: flex;
      align-items: center;
      gap: 14px;
      color: var(--green);
      font-weight: 800;
      font-size: 1.15rem;
      letter-spacing: -0.02em;
    }
    
    .brand img {
      width: 48px;
      height: 48px;
      border-radius: 50%;
      object-fit: contain;
      box-shadow: 0 4px 10px rgba(7, 95, 42, 0.15);
      border: 2px solid #fff;
    }
    
    .brand span {
      line-height: 1.25;
    }
    
    .brand small {
      font-weight: 500;
      color: var(--muted);
      font-size: 0.8rem;
    }
    
    .nav {
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
    }
    
    .nav a {
      padding: 9px 15px;
      border-radius: var(--radius-sm);
      font-weight: 600;
      font-size: 0.9rem;
      color: #475569;
    }
    
    .nav a:hover {
      background: var(--green-light);
      color: var(--green);
    }
    
    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      background: var(--green-gradient);
      color: #fff;
      border: none;
      border-radius: var(--radius-md);
      padding: 12px 20px;
      font-weight: 700;
      font-size: 0.9rem;
      cursor: pointer;
      box-shadow: 0 4px 14px rgba(7, 95, 42, 0.2);
      transition: var(--transition);
    }
    
    .btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(7, 95, 42, 0.3);
    }
    
    .btn:active {
      transform: translateY(0);
    }
    
    .btn.light {
      background: #fff;
      color: var(--green);
      border: 1px solid var(--line);
      box-shadow: var(--shadow-sm);
    }
    
    .btn.light:hover {
      background: var(--green-light);
      border-color: rgba(7, 95, 42, 0.2);
    }
    
    button {
      font-family: inherit;
    }
    
    /* Main Layout */
    .wrap {
      max-width: 1520px;
      margin: auto;
      padding: 32px 24px;
    }
    
    .hero {
      display: grid;
      grid-template-columns: minmax(0, 1.15fr) minmax(380px, 0.85fr);
      gap: 24px;
      align-items: stretch;
    }
    
    .panel {
      background: var(--card-bg);
      border: 1px solid var(--line);
      border-radius: var(--radius-lg);
      padding: 28px;
      box-shadow: var(--shadow-md);
      transition: var(--transition);
    }
    
    .panel:hover {
      box-shadow: var(--shadow-lg);
    }
    
    .head {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 16px;
      margin-bottom: 24px;
    }
    
    .head h1, .head h2 {
      margin: 0;
      color: var(--deep);
      font-weight: 800;
      letter-spacing: -0.03em;
    }
    
    .head h1 {
      font-size: 1.85rem;
      line-height: 1.25;
    }
    
    .head h2 {
      font-size: 1.45rem;
    }
    
    .muted {
      color: var(--muted);
      line-height: 1.6;
      font-size: 0.95rem;
    }
    
    .grid {
      display: grid;
      gap: 16px;
    }
    
    .g2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .g3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .g4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    
    /* Statistics */
    .stat {
      border: 1px solid var(--line);
      border-radius: var(--radius-md);
      padding: 18px;
      background: #fbfdfa;
      transition: var(--transition);
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      gap: 8px;
    }
    
    .stat:hover {
      transform: translateY(-2px);
      border-color: rgba(7, 95, 42, 0.25);
      background: #fff;
    }
    
    .stat span {
      font-size: 0.85rem;
      font-weight: 600;
      color: var(--muted);
    }
    
    .stat b {
      display: block;
      color: var(--green);
      font-size: 2rem;
      font-weight: 800;
      line-height: 1;
    }
    
    /* Category Cards */
    .cat {
      border: 1px solid var(--line);
      border-radius: var(--radius-md);
      padding: 16px;
      background: #fff;
      display: flex;
      gap: 14px;
      align-items: flex-start;
      transition: var(--transition);
    }
    
    .cat:hover {
      transform: translateY(-3px);
      border-color: rgba(7, 95, 42, 0.3);
      box-shadow: var(--shadow-md);
    }
    
    .cat i {
      width: 40px;
      height: 40px;
      border-radius: var(--radius-sm);
      background: var(--green-light);
      color: var(--green);
      display: grid;
      place-items: center;
      font-size: 1.15rem;
      flex-shrink: 0;
      transition: var(--transition);
    }
    
    .cat:hover i {
      background: var(--green);
      color: #fff;
    }
    
    .cat strong {
      display: block;
      color: #1e293b;
      font-weight: 700;
      font-size: 0.95rem;
    }
    
    .cat span {
      display: block;
      font-size: 0.8rem;
      color: var(--muted);
      margin-top: 4px;
    }
    
    /* Form Inputs */
    .form-grid {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 16px;
    }
    
    .field-full {
      grid-column: 1 / -1;
    }
    
    label {
      display: block;
      font-weight: 600;
      font-size: 0.85rem;
      margin-bottom: 6px;
      color: #334155;
    }
    
    input, select, textarea {
      width: 100%;
      border: 1px solid var(--line);
      border-radius: var(--radius-sm);
      padding: 12px 14px;
      font-family: inherit;
      font-size: 0.9rem;
      color: var(--ink);
      background: #fff;
      transition: var(--transition);
    }
    
    input:focus, select:focus, textarea:focus {
      border-color: var(--green);
      box-shadow: 0 0 0 3px rgba(7, 95, 42, 0.12);
    }
    
    textarea {
      min-height: 130px;
      resize: vertical;
    }
    
    /* Badges & Notices */
    .notice {
      border-radius: var(--radius-md);
      padding: 16px 20px;
      margin-bottom: 24px;
      font-weight: 600;
      font-size: 0.95rem;
      display: flex;
      align-items: center;
      gap: 12px;
      box-shadow: var(--shadow-sm);
    }
    
    .notice.ok {
      background: #f0fdf4;
      color: #166534;
      border: 1px solid #bbf7d0;
    }
    
    .notice.err {
      background: #fef2f2;
      color: #991b1b;
      border: 1px solid #fecaca;
    }
    
    .badge {
      display: inline-flex;
      align-items: center;
      border-radius: 9999px;
      padding: 4px 12px;
      font-size: 0.75rem;
      font-weight: 700;
      letter-spacing: 0.02em;
    }
    
    .badge.ok {
      background: #dcfce7;
      color: #15803d;
    }
    
    .badge.info {
      background: var(--blue-light);
      color: var(--blue);
    }
    
    .badge.warn {
      background: var(--gold-light);
      color: var(--gold);
    }
    
    .badge.bad {
      background: var(--red-light);
      color: var(--red);
    }
    
    .badge.neutral {
      background: #f1f5f9;
      color: #475569;
    }
    
    /* Upgrade Banner */
    .upgrade {
      margin-top: 24px;
      background: linear-gradient(135deg, #ffffff 0%, #f4fbf1 100%);
      border: 1px solid var(--line);
      border-radius: var(--radius-lg);
      padding: 32px;
      box-shadow: var(--shadow-md);
    }
    
    .upgrade-card {
      border: 1px solid var(--line);
      border-radius: var(--radius-md);
      background: #fff;
      padding: 20px;
      display: flex;
      flex-direction: column;
      gap: 14px;
      transition: var(--transition);
    }
    
    .upgrade-card:hover {
      transform: translateY(-4px);
      box-shadow: var(--shadow-lg);
      border-color: rgba(7, 95, 42, 0.25);
    }
    
    .upgrade-card i {
      width: 46px;
      height: 46px;
      border-radius: var(--radius-md);
      background: var(--green-light);
      color: var(--green);
      display: grid;
      place-items: center;
      font-size: 1.25rem;
      transition: var(--transition);
    }
    
    .upgrade-card:hover i {
      background: var(--green-gradient);
      color: #fff;
    }
    
    .upgrade-card h3 {
      margin: 0;
      color: var(--deep);
      font-size: 1.1rem;
      font-weight: 700;
    }
    
    .upgrade-card p {
      margin: 0;
    }
    
    .upgrade-card a {
      margin-top: auto;
    }
    
    /* Ticket Center & Conversation */
    .ticket {
      border: 1px solid var(--line);
      border-radius: var(--radius-md);
      padding: 16px;
      background: #fff;
      display: block;
      transition: var(--transition);
    }
    
    .ticket:hover {
      border-color: rgba(7, 95, 42, 0.2);
      background: #fafdfb;
    }
    
    .ticket.active {
      border-color: var(--green);
      background: #f2fbf4;
      box-shadow: 0 0 0 1px var(--green);
    }
    
    .conversation {
      display: grid;
      gap: 16px;
      margin-bottom: 24px;
    }
    
    .msg {
      border: 1px solid var(--line);
      border-radius: var(--radius-md);
      padding: 16px;
      background: #fdfefe;
      max-width: 85%;
      box-shadow: var(--shadow-sm);
    }
    
    .msg strong {
      display: block;
      color: #1e293b;
      margin-bottom: 6px;
      font-size: 0.9rem;
    }
    
    .msg p {
      margin: 0;
      line-height: 1.55;
      font-size: 0.95rem;
    }
    
    .msg small {
      display: block;
      margin-top: 8px;
      font-size: 0.78rem;
    }
    
    .msg.agent {
      margin-left: auto;
      background: #f0fdfa;
      border-color: #bbf7d0;
    }
    
    /* Process Steps Flow */
    .support-flow {
      display: grid;
      grid-template-columns: repeat(7, minmax(0, 1fr));
      gap: 12px;
      margin-top: 24px;
    }
    
    .flow {
      display: block;
      border: 1px solid var(--line);
      border-radius: var(--radius-md);
      padding: 18px 12px;
      background: #fff;
      text-align: center;
      transition: var(--transition);
    }
    
    .flow:hover {
      transform: translateY(-4px);
      border-color: var(--green);
      background: var(--green-light);
      box-shadow: var(--shadow-md);
    }
    
    .flow i {
      color: var(--green);
      font-size: 1.35rem;
      margin-bottom: 8px;
    }
    
    .flow strong {
      font-size: 0.8rem;
      color: #334155;
    }
    
    /* Footer */
    .footer {
      background: var(--deep);
      color: rgba(255, 255, 255, 0.8);
      margin-top: 32px;
      border-top: 4px solid var(--green);
      font-size: 0.9rem;
    }
    
    .footer .bar {
      padding: 24px;
    }
    
    .footer a {
      color: #fcd34d;
      font-weight: 600;
    }
    
    .footer a:hover {
      color: #fff;
    }
    
    /* Responsive Adjustments */
    @media (max-width: 1100px) {
      .hero {
        grid-template-columns: 1fr;
      }
      .g3, .support-flow {
        grid-template-columns: repeat(3, minmax(0, 1fr));
      }
      .form-grid {
        grid-template-columns: 1fr 1fr;
      }
    }
    
    @media (max-width: 760px) {
      .bar {
        flex-direction: column;
        align-items: center;
        text-align: center;
        gap: 12px;
      }
      .brand {
        flex-direction: column;
      }
      .nav {
        justify-content: center;
      }
      .wrap {
        padding: 16px;
      }
      .g3, .g4, .support-flow, .form-grid {
        grid-template-columns: 1fr;
      }
      .msg {
        max-width: 100%;
      }
      .msg.agent {
        margin-left: 0;
      }
    }
    .support-center { display:grid; grid-template-columns:260px minmax(0,1fr); gap:18px; align-items:start; margin-top:16px; }
    .support-rail { position:sticky; top:92px; background:var(--green-gradient); color:#fff; border-radius:var(--radius-md); padding:16px; box-shadow:var(--shadow-md); }
    .support-rail h2 { margin:0 0 4px; font-size:1rem; }
    .support-rail p { margin:0 0 14px; color:#dff5e8; font-size:.82rem; line-height:1.45; }
    .support-rail nav { display:grid; gap:7px; }
    .support-rail a { display:flex; align-items:center; gap:9px; justify-content:space-between; padding:10px 11px; border-radius:var(--radius-sm); color:#fff; font-weight:800; }
    .support-rail a:hover { background:rgba(255,255,255,.13); text-decoration:none; }
    .support-rail a.primary { background:#fff; color:var(--green); }
    .support-rail small { display:block; color:#dff5e8; font-weight:650; margin-top:14px; line-height:1.45; }
    .support-main { min-width:0; display:grid; gap:16px; }
    .support-panel { background:#fff; border:1px solid var(--line); border-radius:var(--radius-md); box-shadow:var(--shadow-md); overflow:hidden; }
    .support-panel > summary { list-style:none; cursor:pointer; padding:17px 18px; display:flex; align-items:center; justify-content:space-between; gap:12px; font-weight:900; color:var(--ink); }
    .support-panel > summary::-webkit-details-marker { display:none; }
    .support-panel > summary:after { content:""; width:9px; height:9px; border-right:2px solid currentColor; border-bottom:2px solid currentColor; transform:rotate(45deg); transition:var(--transition); }
    .support-panel[open] > summary { border-bottom:1px solid var(--line); color:var(--green); }
    .support-panel[open] > summary:after { transform:rotate(225deg); }
    .support-panel-body { padding:18px; }
    .support-local-note { border:1px solid #bbf7d0; background:#f0fdf4; color:#166534; border-radius:var(--radius-sm); padding:10px 12px; font-weight:750; }
    @media (max-width: 1100px) { .support-center { grid-template-columns:1fr; } .support-rail { position:relative; top:auto; } .support-rail nav { grid-template-columns:repeat(2,minmax(0,1fr)); } }
    @media (max-width: 760px) { .support-rail nav { grid-template-columns:1fr; } }
  </style>
</head>
<body>
<header class="top">
  <div class="bar">
    <a class="brand" href="../index.php"><img src="<?= e($logo) ?>" alt="NATCODEV"><span>NATCODEV<br><small>Support Desk</small></span></a>
    <nav class="nav" aria-label="Public support navigation">
      <a href="index.php">Support Home</a>
      <a href="#new-ticket">New Ticket</a>
      <a href="#lookup">Track Ticket</a>
      <a href="#knowledge">Knowledge Base</a>
      <a href="#upgrade">Registration Paths</a>
      <?php if ($user): ?><a class="btn light" href="../dashboard/index.php">Dashboard</a><?php else: ?><a class="btn light" href="login.php?next=index.php">Track Existing Ticket</a><?php endif; ?>
    </nav>
  </div>
</header>

<main class="wrap">
  <div class="support-center">
    <aside class="support-rail" aria-label="Public support navigation">
      <h2>Support Center</h2>
      <p>Open a case, track a ticket, read help notes, or choose the correct registration path without leaving support.</p>
      <nav>
        <a class="primary" href="#new-ticket"><span><i class="fas fa-plus-circle"></i> New Ticket</span></a>
        <a href="#lookup"><span><i class="fas fa-magnifying-glass"></i> Track Ticket</span></a>
        <a href="#knowledge"><span><i class="fas fa-book-open"></i> Knowledge Base</span></a>
        <a href="#upgrade"><span><i class="fas fa-route"></i> Registration Paths</span></a>
        <a href="#support-flow"><span><i class="fas fa-list-check"></i> Resolution Flow</span></a>
        <?php if ($user): ?><a href="../dashboard/index.php"><span><i class="fas fa-table-columns"></i> My Dashboard</span></a><?php else: ?><a href="login.php?next=index.php"><span><i class="fas fa-ticket"></i> Ticket Access</span></a><?php endif; ?>
      </nav>
      <small>Support remains self-contained here. Service registration links are kept in the registration paths section only.</small>
    </aside>
    <section class="support-main">
  <?php if ($createdRef): ?><div class="notice ok">Ticket <?= e($createdRef) ?> has been submitted. Keep this reference for tracking.</div><?php endif; ?>
  <?php if ($message): ?><div class="notice ok"><?= e($message) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="notice err"><?= e($error) ?></div><?php endif; ?>

    <details class="support-panel" open><summary>New Support Request</summary><div class="support-panel-body">
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

  </div></details>

  <details class="support-panel" id="upgrade-panel"><summary>Registration Paths</summary><div class="support-panel-body">
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

  </div></details>

  <details class="support-panel" open><summary>Track Ticket And Replies</summary><div class="support-panel-body">
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

        <?php if ((string) $selectedTicket['status'] === 'resolved' && empty($selectedTicket['rating'])): ?>
          <div class="rating-box" style="margin-top:20px;padding:20px;background:var(--green-light);border:1px solid rgba(7,95,42,0.2);border-radius:var(--radius-md);">
            <h4 style="margin:0 0 10px 0;color:var(--deep);font-weight:700;">Rate Your Support Experience</h4>
            <p class="muted" style="font-size:0.85rem;margin:0 0 16px 0;">Please help us improve our service by rating your representative.</p>
            <form method="post">
              <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="rate_ticket">
              <input type="hidden" name="ticket_ref" value="<?= e((string) $selectedTicket['ticket_ref']) ?>">
              <input type="hidden" name="email" value="<?= e((string) $selectedTicket['requester_email']) ?>">
              <div style="margin-bottom:12px;">
                <label>Rating Score</label>
                <select name="rating" required style="max-width:240px;">
                  <option value="5">5 - Excellent</option>
                  <option value="4">4 - Good</option>
                  <option value="3">3 - Average</option>
                  <option value="2">2 - Poor</option>
                  <option value="1">1 - Terrible</option>
                </select>
              </div>
              <div style="margin-bottom:16px;">
                <label>Review Comments (Optional)</label>
                <textarea name="feedback_comment" placeholder="Tell us how we did..."></textarea>
              </div>
              <button class="btn" type="submit">Submit Feedback</button>
            </form>
          </div>
        <?php elseif (!empty($selectedTicket['rating'])): ?>
          <div class="rating-box" style="margin-top:20px;padding:20px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:var(--radius-md);color:#166534;">
            <strong style="display:block;font-size:1rem;margin-bottom:6px;"><i class="fas fa-circle-check"></i> Thank you!</strong>
            <p style="margin:0;font-size:0.9rem;line-height:1.5;">You rated this support experience: <strong><?= (int) $selectedTicket['rating'] ?> / 5</strong></p>
            <?php if (!empty($selectedTicket['feedback_comment'])): ?>
              <p style="margin:8px 0 0 0;font-size:0.88rem;font-style:italic;color:#15803d;padding-left:10px;border-left:3px solid #bbf7d0;">"<?= e($selectedTicket['feedback_comment']) ?>"</p>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      <?php else: ?>
        <p class="muted">Select a ticket or use the lookup form to view the conversation and admin updates.</p>
      <?php endif; ?>
    </article>
  </section>

  </div></details>

  <details class="support-panel" id="knowledge-panel"><summary>Knowledge Base</summary><div class="support-panel-body">
  <section class="panel" id="knowledge" aria-label="Support knowledge base">
    <div class="head"><h2>Knowledge Base</h2><span class="badge info">Self-service help</span></div>
    <div class="grid g3">
      <article class="ticket"><strong><i class="fas fa-ticket"></i> Ticket Access</strong><p class="muted">Use your ticket reference and requester email to track replies, add updates, or rate a resolved case.</p><a class="btn light" href="#lookup">Track a ticket</a></article>
      <article class="ticket"><strong><i class="fas fa-user-check"></i> Account & Registration</strong><p class="muted">Choose the correct path for growers, learners, buyers, sellers, providers, and field stakeholders before opening a support issue.</p><a class="btn light" href="#upgrade">View paths</a></article>
      <article class="ticket"><strong><i class="fas fa-shield-halved"></i> Payments & Certificates</strong><p class="muted">For wallet, certificate access, refunds, or marketplace order questions, include your reference number so support can verify faster.</p><a class="btn light" href="#new-ticket">Open a case</a></article>
    </div>
  </section>
  </div></details>

  <details class="support-panel"><summary>Resolution Flow</summary><div class="support-panel-body">
  <section class="support-flow" aria-label="Support resolution flow">
    <?php
    $flowSteps = [
        ['fa-comments', 'Open Support', '#lookup', 'document.getElementById("lookup").scrollIntoView({behavior: "smooth"});'],
        ['fa-plus', 'Submit Issue', '#new-ticket', 'document.getElementById("new-ticket").scrollIntoView({behavior: "smooth"});'],
        ['fa-people-arrows', 'Routed to Team', 'javascript:void(0)', 'alert("Tickets are instantly routed to target departments (Academy, Payments, Registry) based on category. Standard review takes less than 2 hours.");'],
        ['fa-reply', 'Response / Action', '#lookup', 'document.getElementById("lookup").scrollIntoView({behavior: "smooth"});'],
        ['fa-circle-check', 'Resolve', '#lookup', 'document.getElementById("lookup").scrollIntoView({behavior: "smooth"});'],
        ['fa-star', 'Rate & Feedback', 'javascript:void(0)', 'alert("Customer satisfaction and resolution rates are monitored directly inside the coordinator panel to audit representative performance.");'],
        ['fa-chart-simple', 'Report & Improve', 'javascript:void(0)', 'alert("Support metrics and ticket resolution feedback are audited weekly to improve NATCODEV services.");']
    ];
    foreach ($flowSteps as $step):
    ?>
      <a class="flow" href="<?= e($step[2]) ?>" onclick="<?= e($step[3]) ?>">
        <i class="fas <?= e($step[0]) ?>"></i><br>
        <strong><?= e($step[1]) ?></strong>
      </a>
    <?php endforeach; ?>
  </section>
    </section>
  </div></details>
  <!-- End collapsible support panels -->
    </section>
  </div>
</main>

<footer class="footer"><div class="bar"><span><i class="fas fa-shield-halved"></i> Your data is secure and confidential.</span><span><a href="mailto:support@natcodev.com.ng">support@natcodev.com.ng</a> / Available 24/7</span></div></footer>
<script>
(function () {
  function openHashPanel() {
    if (!location.hash) return;
    const target = document.querySelector(location.hash);
    if (!target) return;
    const panel = target.closest('details.support-panel');
    if (panel) panel.open = true;
  }
  window.addEventListener('hashchange', openHashPanel);
  document.addEventListener('DOMContentLoaded', openHashPanel);
})();
</script></body>
</html>


