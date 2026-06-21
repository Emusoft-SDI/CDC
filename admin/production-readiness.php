<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../lib/admin-layout.php';
require_once __DIR__ . '/../lib/production-readiness.php';

$pdo = db();
admin_ensure_schema($pdo);
admin_require($pdo);

$message = '';
$error = '';
$liveResult = null;

try {
    pg_ensure_schema($pdo);
    dr_ensure_schema($pdo);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verify_csrf($_POST['_csrf'] ?? null)) {
            $error = 'Invalid security token.';
        } else {
            $action = (string) ($_POST['action'] ?? '');
            if ($action === 'live_test') {
                $liveResult = pr_run_live_test($pdo, (string) ($_POST['channel'] ?? ''), (string) ($_POST['recipient'] ?? ''));
                $message = $liveResult['label'] . ': ' . $liveResult['detail'];
            } elseif ($action === 'backup_drill') {
                $backup = dr_create_backup_manifest($pdo, (int) ((current_user($pdo)['id'] ?? 0)));
                $message = 'Backup drill completed: ' . $backup['backup_ref'] . ' at ' . $backup['path'];
            }
        }
    }
    $checks = pr_run_checks($pdo);
} catch (Throwable $e) {
    $checks = [];
    $error = $e->getMessage();
}

$score = pr_score($checks);
$flat = pr_flatten_checks($checks);
$failed = array_values(array_filter($flat, static fn(array $check): bool => !(bool) $check['ok']));

admin_page_start('Production Readiness', [
    'active' => 'production-readiness.php',
    'description' => 'Authenticated checks for real roles, live communications, payments, backup drills, database readiness, and governance controls.',
    'wide' => true,
    'css' => '
      :root{--primary:#111827;--green:#0f766e;--green-dark:#115e59;--bg:#f8fafc;}
      .prod-hero{background:linear-gradient(135deg,#ecfeff,#fff);border-left:5px solid #0f766e}
      .check-ok{color:#0f6b3c;font-weight:850}.check-fail{color:#a32020;font-weight:850}
      .check-grid{grid-template-columns:380px minmax(0,1fr)}
      @media(max-width:960px){.check-grid{grid-template-columns:1fr}}
    ',
]);
?>
<?php if ($message): ?><div class="notice ok"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>

<section class="panel prod-hero">
  <h2>Production Proof Score</h2>
  <p class="muted">This page does not guess. It checks the platform configuration and flags what is truly live versus still local/log/simulated.</p>
  <div class="progress"><div style="width:<?= (int) $score ?>%;"></div></div>
  <strong><?= (int) $score ?>% ready</strong>
</section>

<section class="stats">
  <div class="stat"><div class="metric"><?= count($flat) ?></div><strong>Total Checks</strong></div>
  <div class="stat"><div class="metric"><?= count($flat) - count($failed) ?></div><strong>Passed</strong></div>
  <div class="stat"><div class="metric"><?= count($failed) ?></div><strong>Needs Work</strong></div>
  <div class="stat"><div class="metric"><?= date('H:i') ?></div><strong>Checked</strong></div>
</section>

<section class="layout check-grid">
  <aside class="panel">
    <h2>Live Service Tests</h2>
    <p class="muted">Use real test recipients only. If a channel is still in log mode, the test will be logged instead of delivered.</p>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="live_test">
      <label>Channel<select name="channel"><option value="email">Email</option><option value="sms">SMS</option><option value="whatsapp">WhatsApp</option></select></label>
      <label>Recipient<input name="recipient" placeholder="email or phone number" required></label>
      <button type="submit">Run Live Test</button>
    </form>

    <h2>Backup Drill</h2>
    <p class="muted">Creates a DR manifest and records restore evidence. It is not a full SQL dump.</p>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="backup_drill">
      <button type="submit">Create Backup Manifest</button>
    </form>
  </aside>

  <section class="panel">
    <h2>Readiness Checks</h2>
    <?php foreach ($checks as $group => $items): ?>
      <h3><?= e(ucwords(str_replace('_', ' ', (string) $group))) ?></h3>
      <table>
        <thead><tr><th>Status</th><th>Check</th><th>Detail</th></tr></thead>
        <tbody>
          <?php foreach ($items as $item): ?>
            <tr>
              <td class="<?= $item['ok'] ? 'check-ok' : 'check-fail' ?>"><?= $item['ok'] ? 'PASS' : 'FAIL' ?></td>
              <td><?= e($item['label']) ?></td>
              <td class="muted"><?= e((string) $item['detail']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endforeach; ?>
  </section>
</section>
<?php admin_page_end(); ?>
