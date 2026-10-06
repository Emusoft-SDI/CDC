<?php
declare(strict_types=1);

/**
 * Admin health (Phase 6, complete).
 *
 *   /admin/health.php                 -> human-readable dashboard (master shell)
 *   /admin/health.php?format=json     -> JSON probe (200 healthy / 503 degraded)
 */

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../lib/admin-layout.php';
require_once __DIR__ . '/../lib/admin-queue.php';
require_once __DIR__ . '/../lib/admin-metrics.php';
require_once __DIR__ . '/../lib/admin-health.php';

$pdo = db();
admin_ensure_schema($pdo);
admin_require($pdo, 'dashboard');

$report = admin_health_report($pdo);

$wantsJson = strtolower((string) ($_GET['format'] ?? '')) === 'json'
    || str_contains(strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json');

if ($wantsJson) {
    if (function_exists('admin_request_id')) {
        admin_request_id();
    }
    http_response_code($report['ok'] ? 200 : 503);
    header('Content-Type: application/json');
    echo json_encode($report, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$failed = array_values(array_filter($report['checks'], static fn(array $c): bool => empty($c['ok'])));

admin_page_start('System Health', [
    'active' => 'health.php',
    'description' => 'Live status of the database, storage, queue, cron workers, mail transport and schema.',
    'wide' => true,
    'css' => '.health-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px}'
        . '.health-card{border:1px solid var(--line);border-radius:12px;background:#fff;padding:16px;box-shadow:0 12px 28px rgba(16,24,40,.06)}'
        . '.health-card h3{margin:0 0 6px;font-size:1rem;display:flex;align-items:center;gap:8px}'
        . '.health-card p{margin:0;color:var(--muted);font-size:.86rem}'
        . '.health-dot{width:10px;height:10px;border-radius:50%;display:inline-block}'
        . '.health-dot.ok{background:#0f6b3c}.health-dot.bad{background:#d92d20}',
]);
?>
<div class="notice <?= $report['ok'] ? 'ok' : 'error' ?>">
  <?= $report['ok']
      ? 'All systems healthy. Request ' . e((string) $report['request_id']) . ' at ' . e((string) $report['timestamp']) . '.'
      : count($failed) . ' check(s) need attention. Request ' . e((string) $report['request_id']) . '.' ?>
</div>
<section class="health-grid">
  <?php foreach ($report['checks'] as $key => $check): ?>
    <article class="health-card">
      <h3>
        <span class="health-dot <?= !empty($check['ok']) ? 'ok' : 'bad' ?>"></span>
        <?= e((string) $check['label']) ?>
      </h3>
      <p><?= e((string) $check['detail']) ?></p>
    </article>
  <?php endforeach; ?>
</section>
<p class="meta" style="margin-top:16px">
  Machine-readable probe: <a href="health.php?format=json">health.php?format=json</a> (HTTP 200 healthy / 503 degraded).
</p>
<?php admin_page_end(); ?>
