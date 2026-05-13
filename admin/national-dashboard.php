<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/platform-governance.php';

session_start();
$pdo = db();
admin_ensure_schema($pdo);
admin_require($pdo);
pg_ensure_schema($pdo);

$overview = $pdo->query("
    SELECT
      (SELECT COUNT(*) FROM users WHERE role = 'grower') farmers,
      (SELECT COUNT(*) FROM grower_farms) farms,
      (SELECT COALESCE(SUM(farm_size),0) FROM grower_farms) hectares,
      (SELECT COUNT(*) FROM users WHERE platform_role = 'state_coordinator') state_coordinators,
      (SELECT COUNT(*) FROM provider_registry WHERE status IN ('approved','verified')) providers,
      (SELECT COUNT(*) FROM agronomy_cases WHERE status NOT IN ('resolved','closed')) open_cases
")->fetch() ?: [];

$stateRows = $pdo->query("
    SELECT COALESCE(ns.state_name, 'Unassigned') state_name,
           COUNT(DISTINCT u.id) farmers,
           COUNT(DISTINCT gf.id) farms,
           COALESCE(SUM(gf.farm_size), 0) hectares,
           SUM(CASE WHEN COALESCE(u.accreditation_status, 'not_accredited') = 'accredited' THEN 1 ELSE 0 END) accredited,
           SUM(CASE WHEN COALESCE(fv.status, 'pending') = 'verified' THEN 1 ELSE 0 END) verified_farms
    FROM users u
    LEFT JOIN applications a ON a.id = u.application_id
    LEFT JOIN grower_farms gf ON gf.user_id = u.id
    LEFT JOIN farm_verifications fv ON fv.farm_id = gf.id
    LEFT JOIN nigeria_states ns ON ns.id = gf.state_id OR ns.id = a.state_id
    WHERE u.role = 'grower'
    GROUP BY COALESCE(ns.state_name, 'Unassigned')
    ORDER BY farmers DESC, state_name
    LIMIT 40
")->fetchAll();

$cropRows = $pdo->query("
    SELECT COALESCE(NULLIF(coconut_variety, ''), 'Unspecified') crop_type, COUNT(*) total
    FROM grower_farms
    GROUP BY COALESCE(NULLIF(coconut_variety, ''), 'Unspecified')
    ORDER BY total DESC
    LIMIT 8
")->fetchAll();

$providerRows = $pdo->query("
    SELECT provider_type, status, COUNT(*) total
    FROM provider_registry
    GROUP BY provider_type, status
    ORDER BY provider_type, status
")->fetchAll();

$broadcastRows = $pdo->query("
    SELECT scope, state_name, audience, title, priority, status, created_at
    FROM platform_broadcasts
    ORDER BY created_at DESC
    LIMIT 6
")->fetchAll();

$policyRows = $pdo->query("
    SELECT title, category, status, next_review_at
    FROM platform_governance_policies
    ORDER BY FIELD(status, 'expired','draft','approved'), next_review_at
    LIMIT 6
")->fetchAll();

admin_page_start('National Coordinator Dashboard', [
    'active' => 'national-dashboard.php',
    'description' => 'National oversight for state comparison, farmer distribution, accreditation, investors, finance, communication, compliance, expansion, and project decision support.',
    'wide' => true,
    'css' => '
      :root{--primary:#6d28d9;--green:#2563eb;--green-dark:#1d4ed8;--bg:#f8f7ff;}
      .national-hero{background:linear-gradient(135deg,#f5f3ff,#fff);border-left:5px solid #6d28d9}
      .national-grid{grid-template-columns:repeat(auto-fit,minmax(260px,1fr))}
    ',
]);
?>
<section class="panel national-hero">
  <h2>National Program Command</h2>
  <p class="muted">Compare states, supervise state coordinators, monitor accreditation, track resources, support investors, and export national reports.</p>
  <div class="actions">
    <a class="button" href="analytics.php">Analytics</a>
    <a class="button secondary" href="reports.php">Reports</a>
    <a class="button secondary" href="communications.php">National Broadcast</a>
    <a class="button secondary" href="providers.php">Providers</a>
  </div>
</section>

<section class="stats">
  <div class="stat"><div class="metric"><?= (int) ($overview['farmers'] ?? 0) ?></div><strong>Registered Farmers</strong></div>
  <div class="stat"><div class="metric"><?= (int) ($overview['farms'] ?? 0) ?></div><strong>Farms</strong></div>
  <div class="stat"><div class="metric"><?= number_format((float) ($overview['hectares'] ?? 0), 1) ?></div><strong>Cultivated Hectares</strong></div>
  <div class="stat"><div class="metric"><?= (int) ($overview['state_coordinators'] ?? 0) ?></div><strong>State Coordinators</strong></div>
  <div class="stat"><div class="metric"><?= (int) ($overview['providers'] ?? 0) ?></div><strong>Verified Providers</strong></div>
  <div class="stat"><div class="metric"><?= (int) ($overview['open_cases'] ?? 0) ?></div><strong>Open Agronomy Cases</strong></div>
</section>

<section class="panel">
  <h2>State-wise Analytics</h2>
  <table>
    <thead><tr><th>State</th><th>Farmers</th><th>Farms</th><th>Hectares</th><th>Accredited</th><th>Verified Farms</th><th>Action</th></tr></thead>
    <tbody>
      <?php foreach ($stateRows as $row): ?>
        <tr>
          <td><strong><?= e($row['state_name']) ?></strong></td>
          <td><?= (int) $row['farmers'] ?></td>
          <td><?= (int) $row['farms'] ?></td>
          <td><?= number_format((float) $row['hectares'], 1) ?></td>
          <td><?= (int) $row['accredited'] ?></td>
          <td><?= (int) $row['verified_farms'] ?></td>
          <td><a class="button secondary" href="state-dashboard.php?state=<?= urlencode((string) $row['state_name']) ?>">Open State</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$stateRows): ?><tr><td colspan="7">No state data yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</section>

<section class="grid national-grid">
  <article class="card">
    <h2>Crop & Intercropping Trends</h2>
    <?php foreach ($cropRows as $row): ?><p><strong><?= e($row['crop_type']) ?></strong><br><span class="muted"><?= (int) $row['total'] ?> farm(s)</span></p><?php endforeach; ?>
    <?php if (!$cropRows): ?><p class="empty">Crop variety data has not been captured yet.</p><?php endif; ?>
  </article>
  <article class="card">
    <h2>Investor Engagement</h2>
    <?php foreach ($providerRows as $row): ?><p><strong><?= e(ucwords(str_replace('_', ' ', (string) $row['provider_type']))) ?></strong><br><span class="muted"><?= e(ucwords(str_replace('_', ' ', (string) $row['status']))) ?>: <?= (int) $row['total'] ?></span></p><?php endforeach; ?>
    <a class="button secondary" href="providers.php">Provider Registry</a>
  </article>
  <article class="card">
    <h2>Communication Center</h2>
    <?php foreach ($broadcastRows as $row): ?><p><strong><?= e($row['title']) ?></strong><br><span class="muted"><?= e($row['scope']) ?> <?= e((string) $row['state_name']) ?> / <?= e($row['priority']) ?></span></p><?php endforeach; ?>
    <?php if (!$broadcastRows): ?><p class="empty">No broadcasts yet.</p><?php endif; ?>
  </article>
  <article class="card">
    <h2>Security & Compliance</h2>
    <?php foreach ($policyRows as $row): ?><p><strong><?= e($row['title']) ?></strong><br><span class="muted"><?= e($row['category']) ?> / <?= e($row['status']) ?></span></p><?php endforeach; ?>
    <a class="button secondary" href="governance.php">Governance</a>
  </article>
  <article class="card">
    <h2>Expansion Planning</h2>
    <p class="muted">Use state comparison, provider coverage, and accreditation gaps to identify new regions and feasibility risks.</p>
    <a class="button secondary" href="reports.php">Generate Reports</a>
  </article>
  <article class="card">
    <h2>System Health</h2>
    <p class="muted">Monitor downtime, audit health, notifications, and production readiness signals.</p>
    <a class="button secondary" href="monitoring.php">Open Health</a>
  </article>
</section>
<?php admin_page_end(); ?>
