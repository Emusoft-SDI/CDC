<?php
declare(strict_types=1);

require_once __DIR__ . '/_user_auth.php';
require_once __DIR__ . '/../lib/dashboard-layout.php';
require_once __DIR__ . '/../lib/notification-dispatch.php';

$pdo = db();
app_ensure_farmer_engagement_schema($pdo);

$userId = (int) $_SESSION['user_id'];
$currentUser = current_user($pdo);
if (!$currentUser) {
    session_destroy();
    redirect_to('login.php');
}
dashboard_redirect_learner_only($pdo, $currentUser);
$categories = [
    'general' => 'General Support',
    'verification' => 'Identity & Documents',
    'farm-health' => 'Farm Health',
    'payments' => 'Payments & Wallet',
    'marketplace' => 'Marketplace',
    'academy' => 'NATCODEV Academy',
    'certificate' => 'Certificate',
    'field-visit' => 'Field Visit',
    'profile-account' => 'Profile & Account',
];
$priorities = ['low' => 'Low', 'medium' => 'Normal', 'high' => 'Urgent'];
$statuses = ['open' => 'Open', 'in_progress' => 'In Progress', 'resolved' => 'Resolved', 'closed' => 'Closed'];

function support_profile_key(array $user): string
{
    $platformRole = strtolower(trim((string) ($user['platform_role'] ?? '')));
    $role = strtolower(trim((string) ($user['role'] ?? 'grower')));

    if (in_array($platformRole, ['national_coordinator', 'state_coordinator', 'field_agent', 'agronomist', 'agric_extensionist', 'extensionist', 'investor', 'provider'], true)) {
        return $platformRole === 'extensionist' ? 'agric_extensionist' : $platformRole;
    }

    if (in_array($role, ['admin', 'field_agent', 'investor', 'provider'], true)) {
        return $role;
    }

    return 'grower';
}

function support_profile_label(string $profileKey): string
{
    return [
        'grower' => 'Grower',
        'investor' => 'Investor',
        'field_agent' => 'Field Agent',
        'agronomist' => 'Agronomist',
        'agric_extensionist' => 'Agric Extensionist',
        'state_coordinator' => 'State Coordinator',
        'national_coordinator' => 'National Coordinator',
        'provider' => 'Provider',
        'admin' => 'Admin',
    ][$profileKey] ?? 'User';
}

function support_faq_items(string $profileKey): array
{
    $shared = [
        [
            'question' => 'How do I open a support ticket?',
            'answer' => 'Use the Open New Ticket form, choose the closest category, set the priority, and describe what happened. Include the page name, reference number, document type, payment reference, or farm name when relevant.',
        ],
        [
            'question' => 'Where do I see replies from NATCODEV Support?',
            'answer' => 'Replies appear under Tickets. Direct notices appear under Notifications, while platform-wide messages appear under Announcements.',
        ],
        [
            'question' => 'When should I mark a ticket urgent?',
            'answer' => 'Use Urgent for blocked verification, payment issues, time-sensitive field visits, certificate access problems, or anything that stops required program work.',
        ],
    ];

    $byProfile = [
        'grower' => [
            [
                'question' => 'How do I update my farm or personal profile?',
                'answer' => 'Open Profile, update Account Settings or Farm Locations, then save. Keep farm size, State, LGA, address, and GPS details current before requesting visits or agronomy help.',
            ],
            [
                'question' => 'How do I request farm health or agronomy support?',
                'answer' => 'Open Farm Health or Agronomy Advisory from the Support menu. Add symptoms, crop stage, photos or visit notes, and the farm affected so the team can route it correctly.',
            ],
            [
                'question' => 'What should I do if my document verification fails?',
                'answer' => 'Check that your profile name, date of birth, phone, and document number match the submitted file, then upload a clearer document from Identity & Farm Verification.',
            ],
        ],
        'investor' => [
            [
                'question' => 'Where do I review investment or marketplace opportunities?',
                'answer' => 'Use Marketplace, Reports, Analytics, and Wallet from the dashboard. Open a ticket under Marketplace or Payments & Wallet when an opportunity or transaction needs review.',
            ],
            [
                'question' => 'How do I get support for wallet funding or payment confirmation?',
                'answer' => 'Open a Payments & Wallet ticket and include the payment provider, amount, date, transaction reference, and the account email used.',
            ],
            [
                'question' => 'Can investors receive program announcements?',
                'answer' => 'Yes. Investor-specific and general platform announcements appear in the Announcements tab when published by the operations team.',
            ],
        ],
        'field_agent' => [
            [
                'question' => 'Where do I manage assigned grower visits?',
                'answer' => 'Use the Field Agent console and Fields Management links for assigned growers, GPS capture, checklist completion, photos, and visit notes.',
            ],
            [
                'question' => 'What should I include when reporting field issues?',
                'answer' => 'Include the grower name or reference, farm location, visit date, GPS status, photos collected, and the blocker preventing completion.',
            ],
            [
                'question' => 'How do I escalate a grower problem?',
                'answer' => 'Open a Field Visit or Farm Health ticket, then include enough details for admins, agronomists, or extensionists to follow up without repeating the field visit.',
            ],
        ],
        'provider' => [
            [
                'question' => 'Where do providers manage service visibility?',
                'answer' => 'Use the provider dashboard and Marketplace-related pages to review offers, requests, and service information visible to growers or investors.',
            ],
            [
                'question' => 'How should I report a marketplace service issue?',
                'answer' => 'Open a Marketplace ticket and include the service name, affected user or order reference, expected outcome, and any payment or delivery details.',
            ],
            [
                'question' => 'How do providers receive platform updates?',
                'answer' => 'Provider-specific and general updates appear under Announcements when published by NATCODEV operations.',
            ],
        ],
        'agronomist' => [
            [
                'question' => 'Where do agronomy cases come from?',
                'answer' => 'Cases can be created by growers, field agents, admins, or agronomists. Use Agronomy Advisory to review symptoms, farm data, recommendations, and follow-up notes.',
            ],
            [
                'question' => 'How do I publish advice to a grower?',
                'answer' => 'Add a recommendation to the agronomy case and mark it visible to the grower when the advice is ready to appear in their dashboard.',
            ],
            [
                'question' => 'What information should support collect for agronomy issues?',
                'answer' => 'Ask for crop stage, symptoms, affected area, recent weather or input use, photos, farm location, and urgency.',
            ],
        ],
        'agric_extensionist' => [
            [
                'question' => 'What support should extensionists prioritize?',
                'answer' => 'Prioritize grower education, adoption follow-up, field guidance, training questions, and practical issues that prevent growers from following recommended practices.',
            ],
            [
                'question' => 'How do I handle training or webinar questions?',
                'answer' => 'Direct growers to NATCODEV Academy when available, and open a General Support ticket when registration, attendance, certificate, or material access needs admin help.',
            ],
            [
                'question' => 'When should I involve an agronomist?',
                'answer' => 'Escalate cases involving disease, pests, soil, water stress, yield decline, or recommendations that require specialist technical review.',
            ],
        ],
        'state_coordinator' => [
            [
                'question' => 'Where do I manage state-level grower support?',
                'answer' => 'Use State Dashboard, Support Desk, Users, Field Network, Resources, Communications, and Reports to monitor growers and teams within the assigned state.',
            ],
            [
                'question' => 'Why is my state dashboard not scoped correctly?',
                'answer' => 'Your staff profile must have a state assigned. Ask a national coordinator or super admin to update the staff profile if state data looks incomplete.',
            ],
            [
                'question' => 'How do I coordinate field agents and extension teams?',
                'answer' => 'Use Assign Growers and Fields Management to review assignments, field work, follow-up needs, and state-specific performance.',
            ],
        ],
        'national_coordinator' => [
            [
                'question' => 'Where do I monitor national program activity?',
                'answer' => 'Use National Dashboard, Governance, Reports, Analytics, Communications, and State Dashboard to compare states and supervise operations.',
            ],
            [
                'question' => 'How do I publish announcements to a specific audience?',
                'answer' => 'Use Communications to select the audience, compose the update, and publish it so users see it under Announcements.',
            ],
            [
                'question' => 'How do I review support performance?',
                'answer' => 'Use the Support Console filters, ticket status counts, reports, and analytics to identify open, in-progress, resolved, and closed issues.',
            ],
        ],
        'admin' => [
            [
                'question' => 'How do admins manage support tickets?',
                'answer' => 'Open the admin Support Console, select a ticket, update priority or status, write a reply, and close it only after the issue is resolved.',
            ],
            [
                'question' => 'How do I help a user with account access?',
                'answer' => 'Confirm the user identity, check their profile and role, then guide them through login, phone verification, password change, or document update as needed.',
            ],
            [
                'question' => 'How do I route a ticket to the right team?',
                'answer' => 'Use the ticket category and message details. Verification and certificate issues go to operations, field visit issues to coordinators, and technical farm issues to agronomy or extension staff.',
            ],
        ],
    ];

    return array_merge($shared, $byProfile[$profileKey] ?? $byProfile['grower']);
}

$topic = preg_replace('/[^a-z0-9_-]/i', '', (string) ($_GET['topic'] ?? ''));
$selectedTicket = preg_replace('/[^A-Z0-9-]/i', '', (string) ($_GET['ticket'] ?? ''));
$view = preg_replace('/[^a-z]/i', '', (string) ($_GET['view'] ?? 'tickets'));
if ($selectedTicket !== '') {
    $view = 'tickets';
}
if (!in_array($view, ['tickets', 'notifications', 'announcements', 'faq'], true)) {
    $view = 'tickets';
}
$error = '';
$message = '';

$userStmt = $pdo->prepare("SELECT role, platform_role FROM users WHERE id = ? LIMIT 1");
$userStmt->execute([$userId]);
$currentUser = $userStmt->fetch() ?: [];
$profileKey = support_profile_key($currentUser);
$profileLabel = support_profile_label($profileKey);
$faqItems = support_faq_items($profileKey);

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

if ($view === 'tickets' && $selectedTicket === '' && $tickets) {
    $selectedTicket = (string) $tickets[0]['ticket_id'];
}

$directStmt = $pdo->prepare("
    SELECT *
    FROM messages
    WHERE user_id = ?
      AND is_from_admin = 1
      AND (ticket_id IS NULL OR ticket_id = '')
    ORDER BY created_at DESC
    LIMIT 100
");
$directStmt->execute([$userId]);
$directMessages = $directStmt->fetchAll();

$announcements = [];
$systemNotice = '';
if (function_exists('admin_setting')) {
    $systemNotice = trim(admin_setting($pdo, 'dashboard_system_notice', ''));
}
if (app_table_exists($pdo, 'system_announcements')) {
    $audience = [$currentUser['role'] ?? 'grower', 'all'];
    if (!empty($currentUser['platform_role'])) {
        $audience[] = (string) $currentUser['platform_role'];
    }
    $audience = array_values(array_unique($audience));
    $placeholders = implode(',', array_fill(0, count($audience), '?'));
    $announcementStmt = $pdo->prepare("
        SELECT id, title, body, audience_role, created_at
        FROM system_announcements
        WHERE is_active = 1 AND audience_role IN ({$placeholders})
        ORDER BY created_at DESC
        LIMIT 100
    ");
    $announcementStmt->execute($audience);
    $announcements = $announcementStmt->fetchAll();
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

$ticketCounts = ['open' => 0, 'in_progress' => 0, 'resolved' => 0, 'closed' => 0];
$urgentTickets = 0;
$unreadTicketReplies = 0;
foreach ($tickets as $ticket) {
    $statusKey = (string) ($ticket['status'] ?: 'open');
    $ticketCounts[$statusKey] = ($ticketCounts[$statusKey] ?? 0) + 1;
    if ((string) ($ticket['priority'] ?? '') === 'high' && !in_array($statusKey, ['resolved', 'closed'], true)) {
        $urgentTickets++;
    }
    $unreadTicketReplies += (int) ($ticket['unread_admin_messages'] ?? 0);
}
$latestTicket = $tickets[0] ?? null;
$supportQuickRequests = [
    ['label' => 'Verification issue', 'category' => 'verification', 'description' => 'Documents, NIN/BVN, farm proof, or rejected evidence.'],
    ['label' => 'Certificate issue', 'category' => 'certificate', 'description' => 'Certificate view, download, validity, payment, or verification.'],
    ['label' => 'Academy issue', 'category' => 'academy', 'description' => 'Course access, paid training, materials, quiz, certificate, or refund.'],
    ['label' => 'Farm visit issue', 'category' => 'field-visit', 'description' => 'Field assignment, visit schedule, evidence, GPS, or agent follow-up.'],
    ['label' => 'Wallet/payment issue', 'category' => 'payments', 'description' => 'Funding, duplicate debit, transaction history, or Monnify reference.'],
    ['label' => 'Farm health issue', 'category' => 'farm-health', 'description' => 'Pests, disease, soil, water, seedlings, intercrops, or livestock.'],
];
?>
<?php dashboard_page_start('Support Desk', [
    'active' => 'inbox.php',
    'description' => 'Open tickets, follow replies, and keep all support conversations organized.',
    'wide' => true,
    'css' => '
      .support-layout { grid-template-columns:minmax(260px,340px) minmax(0,1fr); align-items:stretch; }
      .support-panel { min-width:0; }
      .inbox-tabs { display:flex; gap:8px; flex-wrap:wrap; margin:18px 0; }
      .inbox-tabs a { border:1px solid var(--line); border-radius:8px; padding:10px 14px; text-decoration:none; color:var(--ink); background:#fff; font-weight:800; }
      .inbox-tabs a.active { background:var(--green); border-color:var(--green); color:#fff; }
      .ticket-list { max-height:540px; overflow:auto; padding-right:4px; }
      .ticket-link { display:block; width:100%; }
      .notice-card { border:1px solid var(--line); border-radius:8px; padding:14px; background:#fff; margin-bottom:10px; }
      .notice-card:target { border-color:var(--green); box-shadow:0 0 0 3px rgba(31,138,85,.14); }
      .notice-card h3 { margin:0 0 6px; color:var(--green); }
      .faq-list { display:grid; gap:10px; margin-top:16px; }
      .faq-item { border:1px solid var(--line); border-radius:8px; background:#fff; overflow:hidden; }
      .faq-item summary { cursor:pointer; padding:14px 16px; font-weight:900; color:var(--green); list-style:none; display:flex; justify-content:space-between; gap:12px; }
      .faq-item summary::-webkit-details-marker { display:none; }
      .faq-item summary::after { content:"+"; color:var(--gold); font-size:1.25rem; line-height:1; }
      .faq-item[open] summary::after { content:"-"; }
      .faq-item p { margin:0; padding:0 16px 16px; color:var(--muted); line-height:1.6; }
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
      .support-workspace { display:grid; gap:18px; margin-bottom:18px; }
      .support-hero { display:grid; grid-template-columns:minmax(0,1.15fr) minmax(320px,.85fr); gap:18px; align-items:stretch; }
      .support-summary { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px; }
      .support-stat { border:1px solid var(--line); border-radius:8px; background:#fbfcfa; padding:14px; }
      .support-stat span { display:block; color:var(--muted); font-size:.84rem; font-weight:800; }
      .support-stat strong { display:block; color:var(--green); font-size:1.8rem; line-height:1.05; margin-top:7px; }
      .support-route-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:12px; }
      .support-route { border:1px solid var(--line); border-radius:8px; background:#fff; padding:13px; color:inherit; display:grid; gap:6px; }
      .support-route:hover { background:#f1faf5; text-decoration:none; }
      .support-route strong { color:var(--green); }
      .support-route span { color:var(--muted); font-size:.9rem; line-height:1.45; }
      .support-next { display:flex; align-items:flex-start; gap:14px; padding:15px; border:1px solid var(--line); border-radius:8px; background:linear-gradient(135deg,#fffdf5,#f7fbf4); }
      .support-next-icon { width:58px; height:58px; border-radius:8px; display:grid; place-items:center; background:#14733a; color:#fff; font-weight:900; flex:0 0 auto; }
      .support-next h2 { margin:0; color:var(--green); font-size:1.35rem; }
      .support-next p { margin:7px 0 0; color:var(--muted); line-height:1.5; }
      .support-outcomes { display:grid; gap:9px; margin-top:14px; }
      .support-outcome { display:flex; justify-content:space-between; gap:12px; align-items:center; border:1px solid var(--line); border-radius:7px; padding:10px; background:#fbfcfa; }
      .support-outcome strong { color:#26351f; }
      @media(max-width:920px){ .support-layout { grid-template-columns:1fr; } .ticket-list { max-height:none; } .msg { max-width:100%; } }
      @media(max-width:980px){ .support-hero, .support-summary, .support-route-grid { grid-template-columns:1fr; } }
      @media(max-width:640px){ .support-form .field-grid { grid-template-columns:1fr; } .support-form button { width:100%; } .reply-actions { justify-content:stretch; } }
    ',
]); ?>

    <?php if ($message): ?><div class="notice ok"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>

    <section class="support-workspace" aria-label="Support and requests workspace">
      <div class="support-hero">
        <article class="panel support-panel">
          <div class="ntv-section-head">
            <div>
              <h2>Support & Requests</h2>
              <p>Track help requests for verification, certificates, Academy, wallet, field visits, marketplace, and farm operations.</p>
            </div>
            <?= ntv_badge($tickets ? 'active' : 'pending', $tickets ? count($tickets) . ' ticket(s)' : 'No ticket yet') ?>
          </div>
          <div class="support-summary">
            <div class="support-stat"><span>Open</span><strong><?= (int) ($ticketCounts['open'] ?? 0) ?></strong></div>
            <div class="support-stat"><span>In Progress</span><strong><?= (int) ($ticketCounts['in_progress'] ?? 0) ?></strong></div>
            <div class="support-stat"><span>Resolved</span><strong><?= (int) ($ticketCounts['resolved'] ?? 0) ?></strong></div>
            <div class="support-stat"><span>Urgent</span><strong><?= (int) $urgentTickets ?></strong></div>
          </div>
          <div class="support-route-grid" style="margin-top:14px;">
            <?php foreach ($supportQuickRequests as $quick): ?>
              <a class="support-route" href="inbox.php?topic=<?= e($quick['category']) ?>#new-ticket">
                <strong><?= e($quick['label']) ?></strong>
                <span><?= e($quick['description']) ?></span>
              </a>
            <?php endforeach; ?>
          </div>
        </article>

        <article class="panel support-panel">
          <div class="support-next">
            <div class="support-next-icon">SUP</div>
            <div>
              <h2><?= $latestTicket ? e((string) $latestTicket['ticket_id']) : 'Open A Request' ?></h2>
              <p><?= $latestTicket ? 'Latest request: ' . e($categories[(string) $latestTicket['category']] ?? (string) $latestTicket['category']) . ' / last updated ' . e(date('M j, g:i A', strtotime((string) $latestTicket['last_message_at']))) : 'Choose the right request type below and describe the result you need.' ?></p>
            </div>
          </div>
          <div class="support-outcomes">
            <div class="support-outcome"><strong>Unread replies</strong><?= ntv_badge($unreadTicketReplies > 0 ? 'active' : 'closed', (string) $unreadTicketReplies) ?></div>
            <div class="support-outcome"><strong>Notifications</strong><?= ntv_badge($directMessages ? 'active' : 'closed', (string) count($directMessages)) ?></div>
            <div class="support-outcome"><strong>Announcements</strong><?= ntv_badge(($announcements || $systemNotice !== '') ? 'active' : 'closed', (string) (count($announcements) + ($systemNotice !== '' ? 1 : 0))) ?></div>
            <div class="support-outcome"><strong>Profile FAQ</strong><?= ntv_badge('active', $profileLabel) ?></div>
          </div>
        </article>
      </div>
    </section>

    <nav class="inbox-tabs" aria-label="Inbox sections">
      <a class="<?= $view === 'tickets' ? 'active' : '' ?>" href="inbox.php">Tickets <?= $tickets ? '(' . count($tickets) . ')' : '' ?></a>
      <a class="<?= $view === 'notifications' ? 'active' : '' ?>" href="inbox.php?view=notifications">Notifications <?= $directMessages ? '(' . count($directMessages) . ')' : '' ?></a>
      <a class="<?= $view === 'announcements' ? 'active' : '' ?>" href="inbox.php?view=announcements">Announcements <?= ($announcements || $systemNotice !== '') ? '(' . (count($announcements) + ($systemNotice !== '' ? 1 : 0)) . ')' : '' ?></a>
      <a class="<?= $view === 'faq' ? 'active' : '' ?>" href="inbox.php?view=faq">FAQ</a>
    </nav>

    <?php if ($view === 'tickets'): ?>
    <div class="layout support-layout">
      <aside class="panel support-panel">
        <h2>Your Tickets</h2>
        <div class="ticket-list">
        <?php foreach ($tickets as $ticket): ?>
          <?php $status = (string) ($ticket['status'] ?: 'open'); ?>
          <a class="ticket-link <?= (string) $ticket['ticket_id'] === $selectedTicket ? 'active' : '' ?>" href="?ticket=<?= e((string) $ticket['ticket_id']) ?>">
            <strong><?= e((string) $ticket['ticket_id']) ?></strong>
            <span class="badge <?= e($status) ?>"><?= e($statuses[$status] ?? ntv_status_label($status)) ?></span>
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
            <span class="badge <?= e($selectedMeta['status']) ?>"><?= e($statuses[$selectedMeta['status']] ?? ntv_status_label($selectedMeta['status'])) ?></span>
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
    <?php elseif ($view === 'notifications'): ?>
      <section class="panel support-panel">
        <h2>Admin Notifications</h2>
        <p class="muted">Direct notices from admin and super admin that are not support tickets.</p>
        <?php foreach ($directMessages as $notice): ?>
          <article class="notice-card" id="message-<?= (int) $notice['id'] ?>">
            <h3><?= e((string) ($notice['category'] ? ntv_status_label((string) $notice['category']) : 'Notification')) ?></h3>
            <p><?= nl2br(e((string) $notice['message'])) ?></p>
            <small class="muted"><?= e(date('M j, Y g:i A', strtotime((string) $notice['created_at']))) ?></small>
          </article>
        <?php endforeach; ?>
        <?php if (!$directMessages): ?><p class="empty">No direct admin notifications yet.</p><?php endif; ?>
      </section>
    <?php elseif ($view === 'announcements'): ?>
      <section class="panel support-panel">
        <h2>Announcements</h2>
        <p class="muted">System-wide notices and audience announcements from platform governance.</p>
        <?php if ($systemNotice !== ''): ?>
          <article class="notice-card" id="system-notice">
            <h3>System Notice</h3>
            <p><?= nl2br(e($systemNotice)) ?></p>
            <small class="muted">Current platform notice</small>
          </article>
        <?php endif; ?>
        <?php foreach ($announcements as $announcement): ?>
          <article class="notice-card" id="announcement-<?= (int) $announcement['id'] ?>">
            <h3><?= e((string) $announcement['title']) ?></h3>
            <p><?= nl2br(e((string) $announcement['body'])) ?></p>
            <small class="muted"><?= e(date('M j, Y g:i A', strtotime((string) $announcement['created_at']))) ?> / <?= e((string) $announcement['audience_role']) ?></small>
          </article>
        <?php endforeach; ?>
        <?php if (!$announcements && $systemNotice === ''): ?><p class="empty">No announcements yet.</p><?php endif; ?>
      </section>
    <?php else: ?>
      <section class="panel support-panel">
        <h2><?= e($profileLabel) ?> FAQ</h2>
        <p class="muted">Quick answers matched to your current profile, plus the support basics every user needs.</p>
        <div class="faq-list">
          <?php foreach ($faqItems as $index => $item): ?>
            <details class="faq-item" <?= $index === 0 ? 'open' : '' ?>>
              <summary><?= e((string) $item['question']) ?></summary>
              <p><?= e((string) $item['answer']) ?></p>
            </details>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

    <?php if ($view === 'tickets'): ?>
    <section id="new-ticket" class="panel new-ticket-panel">
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
    <?php endif; ?>
  <?php dashboard_page_end(); ?>
