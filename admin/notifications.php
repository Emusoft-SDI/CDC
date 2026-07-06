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
]);
?>
<?php if ($message): ?><div class="notice ok"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>

<section class="stats">
  <div class="stat"><span>Total Attempts</span><div class="metric"><?= (int) $counts['total'] ?></div></div>
  <div class="stat"><span>Logged</span><div class="metric"><?= (int) $counts['logged'] ?></div><p class="meta">Staging/log mode</p></div>
  <div class="stat"><span>Sent</span><div class="metric"><?= (int) $counts['sent'] ?></div><p class="meta">Provider accepted</p></div>
  <div class="stat"><span>Failed</span><div class="metric"><?= (int) $counts['failed'] ?></div><p class="meta">Needs fixing</p></div>
</section>

<section class="layout">
  <aside>
    <form class="panel" method="post">
      <h2>Test Notification</h2>
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <label>Channel</label>
      <select name="channel" required>
        <option value="email">Email</option>
        <option value="sms">SMS</option>
        <option value="whatsapp">WhatsApp</option>
      </select>
      <label>Recipient</label>
      <input type="text" name="recipient" placeholder="email@example.com or 080..." required>
      <div class="actions"><button type="submit">Send Test</button></div>
      <p class="meta">In log mode this records proof without sending externally. In live mode it also records provider response or failure.</p>
    </form>

    <form class="panel" method="get" style="margin-top:18px;">
      <h2>Filter</h2>
      <label>Status</label>
      <select name="status">
        <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All</option>
        <option value="logged" <?= $status === 'logged' ? 'selected' : '' ?>>Logged</option>
        <option value="sent" <?= $status === 'sent' ? 'selected' : '' ?>>Sent</option>
        <option value="failed" <?= $status === 'failed' ? 'selected' : '' ?>>Failed</option>
      </select>
      <label>Channel</label>
      <select name="channel">
        <option value="all" <?= $channel === 'all' ? 'selected' : '' ?>>All</option>
        <option value="email" <?= $channel === 'email' ? 'selected' : '' ?>>Email</option>
        <option value="sms" <?= $channel === 'sms' ? 'selected' : '' ?>>SMS</option>
        <option value="whatsapp" <?= $channel === 'whatsapp' ? 'selected' : '' ?>>WhatsApp</option>
      </select>
      <label>Search</label>
      <input type="search" name="search" value="<?= e($search) ?>" placeholder="recipient, subject, error">
      <div class="actions"><button type="submit">Apply</button></div>
    </form>
  </aside>

  <section>
    <?= admin_pagination_controls($totalLogs, $page, $perPage) ?>
    <table>
      <thead><tr><th>Time</th><th>Channel</th><th>Recipient</th><th>Status</th><th>Message</th><th>Provider / Error</th></tr></thead>
      <tbody>
        <?php foreach ($logs as $log): ?>
          <tr>
            <td><?= e(date('Y-m-d H:i', strtotime((string) $log['created_at']))) ?></td>
            <td><?= e(strtoupper((string) $log['channel'])) ?><br><small><?= e($log['transport'] ?? '') ?></small></td>
            <td><?= e($log['recipient']) ?></td>
            <td><span class="badge <?= $log['status'] === 'failed' ? 'closed' : ($log['status'] === 'sent' ? 'verified' : 'pending') ?>"><?= e($log['status']) ?></span></td>
            <td>
              <?php if (!empty($log['subject'])): ?><strong><?= e($log['subject']) ?></strong><br><?php endif; ?>
              <small><?= e($log['message_preview'] ?? '') ?></small>
            </td>
            <td>
              <?php if (!empty($log['provider_response'])): ?><small><?= e($log['provider_response']) ?></small><?php endif; ?>
              <?php if (!empty($log['error_message'])): ?><small class="error-text"><?= e($log['error_message']) ?></small><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$logs): ?><tr><td colspan="6">No notification attempts found.</td></tr><?php endif; ?>
      </tbody>
    </table>
    <?= admin_pagination_controls($totalLogs, $page, $perPage) ?>
  </section>
</section>
<?php admin_page_end(); ?>
