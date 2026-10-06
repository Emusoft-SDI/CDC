<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../lib/platform-governance.php';

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

$commStats = $pdo->query("SELECT COUNT(*) AS total, COALESCE(SUM(status = 'published'), 0) AS published, COALESCE(SUM(status = 'draft'), 0) AS drafts, COALESCE(SUM(priority IN ('urgent','weather','security')), 0) AS alerts FROM platform_broadcasts")->fetch() ?: [];

admin_page_start('Communication Hub', [
    'active' => 'communications.php',
    'description' => 'Create national or state broadcasts, announcements, alerts, and two-way communication entry points.',
    'wide' => true,
    'css' => ':root{--primary:#be123c;--green:#e11d48;--green-dark:#9f1239;--bg:#fff5f7}.comm-hero{background:linear-gradient(135deg,#fff1f2,#fff);border-left:5px solid #e11d48}',
]);
?>
<?php if ($message): ?><div class="notice ok"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>

<?= admin_kpi_grid([
    ['Broadcasts', number_format((int) ($commStats['total'] ?? 0)), 'All announcements', 'fa-bullhorn', ''],
    ['Published', number_format((int) ($commStats['published'] ?? 0)), 'Live to audience', 'fa-paper-plane', 'blue'],
    ['Drafts', number_format((int) ($commStats['drafts'] ?? 0)), 'Awaiting publish', 'fa-pen-to-square', 'orange'],
    ['Alerts', number_format((int) ($commStats['alerts'] ?? 0)), 'Urgent / weather / security', 'fa-triangle-exclamation', 'red'],
    ['Support Messages', number_format($supportCount), 'Two-way inbox', 'fa-headset', 'purple'],
]) ?>

<section class="panel comm-hero">
  <h2><?= $scopeState !== '' ? e($scopeState) . ' Communication' : 'National Communication' ?></h2>
  <p class="muted">Send announcements by audience, coordinate alerts, and keep query resolution connected to the support desk.</p>
  <div class="actions"><a class="button secondary" href="support.php">Open Two-way Support (<?= (int) $supportCount ?>)</a></div>
</section>

<details class="collapse-card"<?= $error !== '' ? ' open' : '' ?>>
  <summary>
    <span class="cc-icon"><i class="fas fa-paper-plane"></i></span>
    <span class="collapse-title">Create Broadcast<small>Send an announcement by audience, channel and priority</small></span>
    <span class="caret"><i class="fas fa-chevron-down"></i></span>
  </summary>
  <div class="collapse-body">
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <div class="field-grid">
        <?php if ($scopeState === ''): ?><label class="field"><span>State</span><input name="state_name" placeholder="Leave blank for national"></label><?php endif; ?>
        <label class="field"><span>Audience</span><select name="audience"><option value="grower">Farmers/Growers</option><option value="state_coordinator">State Coordinators</option><option value="field_agent">Field Agents</option><option value="agronomist">Agronomists</option><option value="provider">Providers</option><option value="all">All Stakeholders</option></select></label>
        <label class="field"><span>Channel</span><select name="channel"><option value="in_app">In-app</option><option value="email">Email</option><option value="sms">SMS</option><option value="whatsapp">WhatsApp</option><option value="all">All Channels</option></select></label>
        <label class="field"><span>Priority</span><select name="priority"><option value="normal">Normal</option><option value="weather">Weather Alert</option><option value="training">Training</option><option value="urgent">Urgent</option><option value="security">Security</option></select></label>
        <label class="field"><span>Status</span><select name="status"><option value="draft">Draft</option><option value="published">Published</option></select></label>
        <label class="field"><span>Title</span><input name="title" required></label>
        <label class="field"><span>Message</span><textarea name="message" required></textarea></label>
      </div>
      <div class="actions"><button type="submit"><i class="fas fa-paper-plane"></i> Save Broadcast</button></div>
    </form>
  </div>
</details>

<section class="panel">
  <div class="user-toolbar">
    <h2 style="margin:0">Broadcast Register</h2>
    <span class="meta"><?= count($broadcasts) ?> shown</span>
  </div>
  <div class="record-list">
    <?php foreach ($broadcasts as $broadcast): ?>
      <?php
        $priorityTone = ['urgent' => 'bad', 'security' => 'bad', 'weather' => 'warn', 'training' => 'info', 'normal' => 'muted'][(string) $broadcast['priority']] ?? 'muted';
        $statusTone = (string) $broadcast['status'] === 'published' ? 'ok' : 'warn';
      ?>
      <article class="record-row">
        <span class="record-avatar mail"><i class="fas fa-bullhorn"></i></span>
        <div class="record-main">
          <div class="record-title">
            <?= e($broadcast['title']) ?>
            <span class="tag <?= e($statusTone) ?>"><?= e(ucfirst((string) $broadcast['status'])) ?></span>
            <span class="tag <?= e($priorityTone) ?>"><?= e(ucfirst((string) $broadcast['priority'])) ?></span>
          </div>
          <div class="record-excerpt"><?= e(mb_strimwidth((string) $broadcast['message'], 0, 160, '...')) ?></div>
        </div>
        <div class="record-meta">
          <span class="tag"><i class="fas fa-location-dot"></i> <?= e($broadcast['scope']) ?><?= $broadcast['state_name'] ? ' / ' . e($broadcast['state_name']) : '' ?></span>
          <span class="record-sub"><i class="fas fa-users"></i> <?= e((string) $broadcast['audience']) ?></span>
        </div>
        <div class="record-actions">
          <span class="ref-pill"><i class="far fa-clock"></i> <?= e(date('M j, Y', strtotime((string) $broadcast['created_at']))) ?></span>
        </div>
      </article>
    <?php endforeach; ?>
    <?php if (!$broadcasts): ?><div class="record-empty">No broadcasts yet.</div><?php endif; ?>
  </div>
</section>
<?php admin_page_end(); ?>
