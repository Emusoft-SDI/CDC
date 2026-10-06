<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../lib/admin-layout.php';
require_once __DIR__ . '/../lib/twilio.php';

$pdo = db();
admin_ensure_schema($pdo);
admin_require($pdo);

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid security token.';
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
]) ?>

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
      ?>
      <article class="record-row">
        <span class="record-avatar <?= $channelKey === 'email' ? 'mail' : 'log' ?>"><i class="<?= e($channelIcon) ?>"></i></span>
        <div class="record-main">
          <div class="record-title">
            <?= e($log['recipient']) ?>
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
