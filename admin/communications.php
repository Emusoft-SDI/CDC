<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/platform-governance.php';

session_start();
$pdo = db();
admin_ensure_schema($pdo);
admin_require($pdo);
pg_ensure_schema($pdo);

$message = '';
$error = '';
$user = current_user($pdo) ?: [];
$scopeState = pg_scope_state($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid security token.';
    } else {
        try {
            $stateName = $scopeState !== '' ? $scopeState : trim((string) ($_POST['state_name'] ?? ''));
            $scope = $stateName !== '' ? 'state' : 'national';
            $pdo->prepare("
                INSERT INTO platform_broadcasts (scope, state_name, audience, title, message, channel, priority, status, created_by, published_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, IF(? = 'published', NOW(), NULL))
            ")->execute([
                $scope,
                $stateName ?: null,
                trim((string) ($_POST['audience'] ?? 'grower')),
                trim((string) ($_POST['title'] ?? '')),
                trim((string) ($_POST['message'] ?? '')),
                trim((string) ($_POST['channel'] ?? 'in_app')),
                trim((string) ($_POST['priority'] ?? 'normal')),
                trim((string) ($_POST['status'] ?? 'draft')),
                (int) ($user['id'] ?? 0),
                trim((string) ($_POST['status'] ?? 'draft')),
            ]);
            $message = 'Broadcast saved.';
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$where = $scopeState !== '' ? 'WHERE state_name = ? OR scope = "national"' : '';
$stmt = $pdo->prepare("SELECT * FROM platform_broadcasts {$where} ORDER BY created_at DESC LIMIT 80");
$stmt->execute($scopeState !== '' ? [$scopeState] : []);
$broadcasts = $stmt->fetchAll();

$supportCount = app_table_exists($pdo, 'messages') ? (int) $pdo->query("SELECT COUNT(*) FROM messages WHERE is_from_admin = 0")->fetchColumn() : 0;

admin_page_start('Communication Hub', [
    'active' => 'communications.php',
    'description' => 'Create national or state broadcasts, announcements, alerts, and two-way communication entry points.',
    'wide' => true,
    'css' => ':root{--primary:#be123c;--green:#e11d48;--green-dark:#9f1239;--bg:#fff5f7}.comm-hero{background:linear-gradient(135deg,#fff1f2,#fff);border-left:5px solid #e11d48}',
]);
?>
<?php if ($message): ?><div class="notice ok"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>

<section class="panel comm-hero">
  <h2><?= $scopeState !== '' ? e($scopeState) . ' Communication' : 'National Communication' ?></h2>
  <p class="muted">Send announcements by audience, coordinate alerts, and keep query resolution connected to the support desk.</p>
  <div class="actions"><a class="button secondary" href="support.php">Open Two-way Support (<?= (int) $supportCount ?>)</a></div>
</section>

<section class="layout">
  <form class="panel" method="post">
    <h2>Create Broadcast</h2>
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <?php if ($scopeState === ''): ?><label>State<input name="state_name" placeholder="Leave blank for national"></label><?php endif; ?>
    <label>Audience<select name="audience"><option value="grower">Farmers/Growers</option><option value="state_coordinator">State Coordinators</option><option value="field_agent">Field Agents</option><option value="agronomist">Agronomists</option><option value="provider">Providers</option><option value="all">All Stakeholders</option></select></label>
    <label>Channel<select name="channel"><option value="in_app">In-app</option><option value="email">Email</option><option value="sms">SMS</option><option value="whatsapp">WhatsApp</option><option value="all">All Channels</option></select></label>
    <label>Priority<select name="priority"><option value="normal">Normal</option><option value="weather">Weather Alert</option><option value="training">Training</option><option value="urgent">Urgent</option><option value="security">Security</option></select></label>
    <label>Status<select name="status"><option value="draft">Draft</option><option value="published">Published</option></select></label>
    <label>Title<input name="title" required></label>
    <label>Message<textarea name="message" required></textarea></label>
    <button type="submit">Save Broadcast</button>
  </form>

  <section class="panel">
    <h2>Broadcast Register</h2>
    <table>
      <thead><tr><th>Title</th><th>Scope</th><th>Audience</th><th>Priority</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($broadcasts as $broadcast): ?>
          <tr>
            <td><strong><?= e($broadcast['title']) ?></strong><br><span class="muted"><?= e(mb_strimwidth((string) $broadcast['message'], 0, 110, '...')) ?></span></td>
            <td><?= e($broadcast['scope']) ?><?= $broadcast['state_name'] ? ' / ' . e($broadcast['state_name']) : '' ?></td>
            <td><?= e($broadcast['audience']) ?></td>
            <td><?= e($broadcast['priority']) ?></td>
            <td><?= e($broadcast['status']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$broadcasts): ?><tr><td colspan="5">No broadcasts yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </section>
</section>
<?php admin_page_end(); ?>
