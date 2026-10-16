<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../lib/admin-layout.php';
require_once __DIR__ . '/../lib/twilio.php';
require_once __DIR__ . '/../lib/admin-jobs.php';
if (!function_exists('admin_audit_event')) {
    require_once __DIR__ . '/../lib/admin-audit-v2.php';
}

$pdo = db();
admin_ensure_schema($pdo);
admin_require($pdo);

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid security token.';
    } elseif (!app_check_rate_limit('admin_notification_test', 5, 3600)) {
        $error = 'Too many test notifications. Try again later.';
    } elseif (isset($_POST['channels'])) {
        // Schedule broadcast: validate at the boundary and enqueue the
        // notification.broadcast job (drained by cron/admin-jobs.php).
        if (!admin_can($pdo, 'communications.manage')) {
            $error = 'You do not have permission to send broadcasts.';
        } else {
            $validated = admin_broadcast_payloads($_POST);
            if (empty($validated['ok'])) {
                $error = $validated['error'] ?? 'Invalid broadcast.';
            } else {
                foreach ($validated['payloads'] as $payload) {
                    admin_enqueue($pdo, 'notification.broadcast', $payload);
                }
                $message = 'Broadcast queued — the worker will send it shortly.';
                admin_audit_event($pdo, 'notification_broadcast', 'notifications', null, [], ['channels' => implode(',', array_column($validated['payloads'], 'channel')), 'recipients' => count($validated['payloads'][0]['recipients'] ?? [])], 'success', 'Admin broadcast queued');
            }
        }
    } else {
        $recipient = trim((string) ($_POST['recipient'] ?? ''));
        $channel = (string) ($_POST['channel'] ?? 'email');
        $body = 'NATCODEV staging notification test sent at ' . date('Y-m-d H:i:s') . '. If you can see this in the log, the audit trail is working.';

        if ($recipient === '') {
            $error = 'Enter a recipient email or phone number.';
        } elseif ($channel === 'email') {
            $ok = app_send_mail($recipient, 'NATCODEV Notification Test', $body);
            $message = $ok ? 'Email test recorded/sent.' : 'Email test failed. Check the log below.';
        } elseif ($channel === 'sms') {
            $ok = sendSMSMessage($recipient, $body);
            $message = $ok ? 'SMS test recorded/sent.' : 'SMS test failed. Check the log below.';
        } elseif ($channel === 'whatsapp') {
            $ok = sendWhatsAppMessage($recipient, $body);
            $message = $ok ? 'WhatsApp test recorded/sent.' : 'WhatsApp test failed. Check the log below.';
        } else {
            $error = 'Choose a valid channel.';
        }

        if (!empty($ok)) {
            admin_audit_event($pdo, 'notification_test', 'notifications', null, [], ['channel' => $channel, 'recipient' => $recipient], 'success', 'Admin test notification sent');
        }
    }
}

$status = (string) ($_GET['status'] ?? 'all');
$channel = (string) ($_GET['channel'] ?? 'all');
$search = trim((string) ($_GET['search'] ?? ''));
$where = ['1=1'];
$params = [];

if (in_array($status, ['logged', 'sent', 'failed'], true)) {
    $where[] = 'status = ?';
    $params[] = $status;
} else {
    $status = 'all';
}

if (in_array($channel, ['email', 'sms', 'whatsapp'], true)) {
    $where[] = 'channel = ?';
    $params[] = $channel;
} else {
    $channel = 'all';
}

if ($search !== '') {
    $where[] = '(recipient LIKE ? OR subject LIKE ? OR message_preview LIKE ? OR error_message LIKE ?)';
    $term = '%' . $search . '%';
    array_push($params, $term, $term, $term, $term);
}

$counts = $pdo->query("
    SELECT
      COUNT(*) total,
      SUM(CASE WHEN status = 'logged' THEN 1 ELSE 0 END) logged,
      SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) sent,
      SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) failed
    FROM notification_logs
")->fetch() ?: ['total' => 0, 'logged' => 0, 'sent' => 0, 'failed' => 0];

$page = admin_current_page();
$perPage = admin_per_page(50);
$offset = admin_pagination_offset($page, $perPage);

$countStmt = $pdo->prepare('SELECT COUNT(*) FROM notification_logs WHERE ' . implode(' AND ', $where));
$countStmt->execute($params);
$totalLogs = (int) $countStmt->fetchColumn();

$stmt = $pdo->prepare('SELECT * FROM notification_logs WHERE ' . implode(' AND ', $where) . " ORDER BY created_at DESC, id DESC LIMIT {$perPage} OFFSET {$offset}");
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Single-record detail view (?view=ID) so operators can open a
// notification and inspect its full payload, transport and errors.
$detail = null;
$viewId = (int) ($_GET['view'] ?? 0);
if ($viewId > 0) {
    $detailStmt = $pdo->prepare('SELECT * FROM notification_logs WHERE id = ? LIMIT 1');
    $detailStmt->execute([$viewId]);
    $detail = $detailStmt->fetch() ?: null;
}

$queueDepth = function_exists('admin_queue_depth') ? admin_queue_depth($pdo) : 0;

admin_page_start('Notification Log', [
    'active' => 'notifications.php',
    'description' => 'Prove notification behavior across email, SMS, and WhatsApp. Every attempt is recorded with status, transport, and failure reason.',
    'wide' => true,
    'wide' => true,
    'breadcrumbs' => [['label' => 'Communication & Content'], ['label' => 'Notification Log']],
]);
?>
<?php if ($message): ?><div class="notice ok"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>

<?= admin_kpi_grid([
    ['Total Attempts', number_format((int) $counts['total']), 'All channels', 'fa-bell', ''],
    ['Logged', number_format((int) $counts['logged']), 'Staging / log mode', 'fa-file-lines', 'blue'],
    ['Sent', number_format((int) $counts['sent']), 'Provider accepted', 'fa-paper-plane', 'purple'],
    ['Failed', number_format((int) $counts['failed']), 'Needs fixing', 'fa-triangle-exclamation', 'red'],
    ['Queue Depth', number_format($queueDepth), 'Pending jobs', 'fa-list-check', ''],
]) ?>

<?php if ($detail): ?>
<section class="panel" style="margin-bottom:18px">
  <div class="panel-head" style="display:flex;justify-content:space-between;align-items:center">
    <h2>Notification #<?= (int) $detail['id'] ?> Details</h2>
    <a href="notifications.php<?= $status !== 'all' || $channel !== 'all' || $search !== '' ? '?' . http_build_query(array_filter(['status' => $status !== 'all' ? $status : null, 'channel' => $channel !== 'all' ? $channel : null, 'search' => $search !== '' ? $search : null])) : '' ?>"><i class="fas fa-arrow-left"></i> Back to list</a>
  </div>
  <div class="record-row" style="border:1px solid #e4e7ec;border-radius:10px;padding:14px;margin:8px 0">
    <div class="record-main">
      <div class="record-title">
        <?= e((string) $detail['recipient']) ?>
        <span class="tag <?= $detail['status'] === 'failed' ? 'bad' : ($detail['status'] === 'sent' ? 'ok' : 'warn') ?>"><?= e(ucfirst((string) $detail['status'])) ?></span>
        <span class="tag muted"><?= e(strtoupper((string) $detail['channel'])) ?><?= !empty($detail['transport']) ? ' · ' . e((string) $detail['transport']) : '' ?></span>
      </div>
      <?php if (!empty($detail['subject'])): ?>
        <div class="record-excerpt"><strong>Subject:</strong> <?= e((string) $detail['subject']) ?></div>
      <?php endif; ?>
      <?php if (!empty($detail['message_preview'])): ?>
        <div class="record-excerpt"><strong>Message:</strong><br><?= nl2br(e((string) $detail['message_preview'])) ?></div>
      <?php endif; ?>
      <?php if (!empty($detail['provider_response'])): ?>
        <div class="record-excerpt"><strong>Provider response:</strong> <?= nl2br(e((string) $detail['provider_response'])) ?></div>
      <?php endif; ?>
      <?php if (!empty($detail['error_message'])): ?>
        <div class="record-excerpt" style="color:#b42318"><strong>Error:</strong> <?= nl2br(e((string) $detail['error_message'])) ?></div>
      <?php endif; ?>
      <?php if (!empty($detail['context'])): ?>
        <div class="record-excerpt"><strong>Context:</strong> <?= e((string) $detail['context']) ?></div>
      <?php endif; ?>
    </div>
    <div class="record-meta">
      <span class="record-sub"><i class="far fa-clock"></i> <?= e(date('M j, Y H:i:s', strtotime((string) $detail['created_at']))) ?></span>
    </div>
  </div>
</section>
<?php endif; ?>

<details class="collapse-card"<?= $error !== '' ? ' open' : '' ?>>
  <summary>
    <span class="cc-icon"><i class="fas fa-paper-plane"></i></span>
    <span class="collapse-title">Test Notification<small>Send a test email, SMS or WhatsApp message</small></span>
    <span class="caret"><i class="fas fa-chevron-down"></i></span>
  </summary>
  <div class="collapse-body">
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <div class="field-grid">
        <label class="field"><span>Channel</span>
          <select name="channel" required>
            <option value="email">Email</option>
            <option value="sms">SMS</option>
            <option value="whatsapp">WhatsApp</option>
          </select>
        </label>
        <label class="field"><span>Recipient</span><input type="text" name="recipient" placeholder="email@example.com or 080..." required></label>
      </div>
      <div class="actions"><button type="submit"><i class="fas fa-paper-plane"></i> Send Test</button></div>
      <p class="meta">In log mode this records proof without sending externally. In live mode it also records provider response or failure.</p>
    </form>
  </div>
</details>

<details class="collapse-card"<?= $error !== '' ? ' open' : '' ?>>
  <summary>
    <span class="cc-icon"><i class="fas fa-clock"></i></span>
    <span class="collapse-title">Schedule Broadcast<small>Queue a bulk message for the worker to send</small></span>
    <span class="caret"><i class="fas fa-chevron-down"></i></span>
  </summary>
  <div class="collapse-body">
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <label class="field"><span>Message</span>
        <textarea name="message" rows="4" maxlength="500" required placeholder="Your message (max 500 characters)"></textarea>
      </label>
      <label class="field"><span>Channels</span>
        <span style="display:flex;gap:16px;flex-wrap:wrap">
          <label style="display:inline-flex;align-items:center;gap:6px;font-weight:400;cursor:pointer"><input type="checkbox" name="channels[]" value="email" checked> Email</label>
          <label style="display:inline-flex;align-items:center;gap:6px;font-weight:400;cursor:pointer"><input type="checkbox" name="channels[]" value="sms"> SMS</label>
          <label style="display:inline-flex;align-items:center;gap:6px;font-weight:400;cursor:pointer"><input type="checkbox" name="channels[]" value="whatsapp"> WhatsApp</label>
        </span>
      </label>
      <label class="field"><span>Recipients</span>
        <textarea name="recipients" rows="5" required placeholder="One recipient per line — email addresses for Email, phone numbers for SMS/WhatsApp"></textarea>
      </label>
      <div class="actions"><button type="submit"><i class="fas fa-clock"></i> Schedule Broadcast</button></div>
      <p class="meta">Queued for the job worker (cron/admin-jobs.php) — the worker sends it shortly. Recipients must match every selected channel.</p>
    </form>
  </div>
</details>

<section class="panel">
  <form class="toolbar" method="get" style="margin:0">
    <label style="margin:0">Status
      <select name="status">
        <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All</option>
        <option value="logged" <?= $status === 'logged' ? 'selected' : '' ?>>Logged</option>
        <option value="sent" <?= $status === 'sent' ? 'selected' : '' ?>>Sent</option>
        <option value="failed" <?= $status === 'failed' ? 'selected' : '' ?>>Failed</option>
      </select>
    </label>
    <label style="margin:0">Channel
      <select name="channel">
        <option value="all" <?= $channel === 'all' ? 'selected' : '' ?>>All</option>
        <option value="email" <?= $channel === 'email' ? 'selected' : '' ?>>Email</option>
        <option value="sms" <?= $channel === 'sms' ? 'selected' : '' ?>>SMS</option>
        <option value="whatsapp" <?= $channel === 'whatsapp' ? 'selected' : '' ?>>WhatsApp</option>
      </select>
    </label>
    <label style="margin:0">Search<input type="search" name="search" value="<?= e($search) ?>" placeholder="recipient, subject, error"></label>
    <button type="submit"><i class="fas fa-filter"></i> Apply</button>
  </form>
  <?= admin_pagination_controls($totalLogs, $page, $perPage) ?>
  <div class="record-list">
    <?php foreach ($logs as $log): ?>
      <?php
        $logTone = $log['status'] === 'failed' ? 'bad' : ($log['status'] === 'sent' ? 'ok' : 'warn');
        $channelKey = strtolower((string) $log['channel']);
        $channelIcon = ['email' => 'fas fa-envelope', 'sms' => 'fas fa-comment-sms', 'whatsapp' => 'fab fa-whatsapp'][$channelKey] ?? 'fas fa-bell';
        $rowHref = 'notifications.php?view=' . (int) $log['id'] . '&' . http_build_query(array_filter(['status' => $status !== 'all' ? $status : null, 'channel' => $channel !== 'all' ? $channel : null, 'search' => $search !== '' ? $search : null]));
      ?>
      <article class="record-row">
        <span class="record-avatar <?= $channelKey === 'email' ? 'mail' : 'log' ?>"><i class="<?= e($channelIcon) ?>"></i></span>
        <div class="record-main">
          <div class="record-title">
            <a href="<?= e($rowHref) ?>" style="color:inherit;text-decoration:none"><?= e($log['recipient']) ?></a>
            <span class="tag <?= e($logTone) ?>"><?= e(ucfirst((string) $log['status'])) ?></span>
            <span class="tag muted"><?= e(strtoupper($channelKey)) ?><?= !empty($log['transport']) ? ' · ' . e((string) $log['transport']) : '' ?></span>
          </div>
          <?php if (!empty($log['subject']) || !empty($log['message_preview'])): ?>
            <div class="record-excerpt">
              <?php if (!empty($log['subject'])): ?><strong><?= e($log['subject']) ?></strong> — <?php endif; ?>
              <?= e(mb_strimwidth((string) ($log['message_preview'] ?? ''), 0, 150, '...')) ?>
            </div>
          <?php endif; ?>
          <?php if (!empty($log['error_message'])): ?>
            <div class="record-excerpt" style="color:#b42318"><i class="fas fa-triangle-exclamation"></i> <?= e($log['error_message']) ?></div>
          <?php endif; ?>
        </div>
        <div class="record-meta">
          <?php if (!empty($log['provider_response'])): ?>
            <span class="record-sub"><i class="fas fa-server"></i> <?= e(mb_strimwidth((string) $log['provider_response'], 0, 70, '...')) ?></span>
          <?php endif; ?>
        </div>
        <div class="record-actions">
          <span class="ref-pill"><i class="far fa-clock"></i> <?= e(date('M j, H:i', strtotime((string) $log['created_at']))) ?></span>
        </div>
      </article>
    <?php endforeach; ?>
    <?php if (!$logs): ?><div class="record-empty">No notification attempts found.</div><?php endif; ?>
  </div>
  <?= admin_pagination_controls($totalLogs, $page, $perPage) ?>
</section>
<?php admin_page_end(); ?>
