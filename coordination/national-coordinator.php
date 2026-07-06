<?php
declare(strict_types=1);
require_once __DIR__ . '/_coordination.php';

$pdo = coord_pdo();
$user = coord_require($pdo, 'national_coordinator');

$totalApps = coord_scalar($pdo, "SELECT COUNT(*) FROM applications");
$mappedApps = coord_scalar($pdo, "SELECT COUNT(*) FROM applications WHERE state_id IS NOT NULL");
$unmappedApps = max(0, $totalApps - $mappedApps);
$lgaMappedApps = coord_scalar($pdo, "SELECT COUNT(*) FROM applications WHERE lga_id IS NOT NULL");
$confirmedApps = coord_scalar($pdo, "SELECT COUNT(*) FROM applications WHERE confirmed = 1");
$activeApps = coord_scalar($pdo, "SELECT COUNT(*) FROM applications WHERE review_status IN ('active','verified','approved','confirmed') OR confirmed = 1");
$openSupport = coord_scalar($pdo, "SELECT COUNT(*) FROM support_tickets WHERE status IN ('open','in_progress','waiting_on_user','escalated')");
$escalatedSupport = coord_scalar($pdo, "SELECT COUNT(*) FROM support_tickets WHERE status = 'escalated'");
$fieldTasks = app_table_exists($pdo, 'field_tasks') ? coord_scalar($pdo, "SELECT COUNT(*) FROM field_tasks WHERE status IN ('pending','assigned','in_progress')") : 0;
$fieldVisits = app_table_exists($pdo, 'farm_visits') ? coord_scalar($pdo, "SELECT COUNT(*) FROM farm_visits WHERE visited_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)") : 0;
$integrityPct = $totalApps > 0 ? round(($mappedApps / $totalApps) * 100, 1) : 100;

$stateRows = coord_rows($pdo, "
    SELECT ns.state_name,
           COUNT(*) total,
           SUM(a.confirmed = 1 OR a.review_status IN ('active','verified','approved','confirmed')) active_total,
           SUM(a.lga_id IS NOT NULL) lga_mapped_total,
           COUNT(DISTINCT a.lga_id) lga_total,
           MAX(a.created_at) last_seen
    FROM applications a
    JOIN nigeria_states ns ON ns.id = a.state_id
    GROUP BY ns.state_name
    ORDER BY total DESC, state_name ASC
");

$farmRows = coord_rows($pdo, "
    SELECT COALESCE(ns.state_name, 'Unmapped Farm State') state_name,
           COUNT(*) farms,
           SUM(gf.latitude IS NOT NULL AND gf.longitude IS NOT NULL) gps_ready
    FROM grower_farms gf
    LEFT JOIN nigeria_states ns ON ns.id = gf.state_id
    GROUP BY COALESCE(ns.state_name, 'Unmapped Farm State')
    ORDER BY farms DESC
");

$unmappedRows = coord_rows($pdo, "
    SELECT COALESCE(NULLIF(location, ''), 'Blank location') location_hint,
           COUNT(*) total,
           MAX(created_at) last_seen
    FROM applications
    WHERE state_id IS NULL
    GROUP BY COALESCE(NULLIF(location, ''), 'Blank location')
    ORDER BY total DESC, location_hint ASC
    LIMIT 12
");

$statusRows = coord_rows($pdo, "SELECT COALESCE(NULLIF(review_status, ''), 'blank') review_status, COUNT(*) total FROM applications GROUP BY COALESCE(NULLIF(review_status, ''), 'blank') ORDER BY total DESC");
$recentActivity = coord_rows($pdo, "
    SELECT a.app_ref, a.name, COALESCE(ns.state_name, 'Unmapped / Legacy Location') state_name,
           COALESCE(nl.lga_name, 'Unmapped LGA') lga, COALESCE(NULLIF(a.review_status, ''), IF(a.confirmed=1,'active','pending')) review_status,
           a.location, a.created_at
    FROM applications a
    LEFT JOIN nigeria_states ns ON ns.id = a.state_id
    LEFT JOIN nigeria_lgas nl ON nl.id = a.lga_id
    ORDER BY a.created_at DESC
    LIMIT 10
");
$escalations = coord_rows($pdo, "SELECT ticket_ref, requester_name, requester_role, category, subject, priority, status, last_activity_at FROM support_tickets WHERE status IN ('escalated','open','in_progress','waiting_on_user') ORDER BY FIELD(status,'escalated','open','in_progress','waiting_on_user'), FIELD(priority,'high','medium','low'), last_activity_at DESC LIMIT 8");

coord_header('National Coordination Command', 'National oversight must separate true mapped state performance from legacy records that still need state/LGA cleanup.', $user, 'home');
?>
<style>
.nc-command{display:grid;gap:16px}.truth{border-left:5px solid #b7791f;background:#fffbeb}.truth.good{border-left-color:#087443;background:#f0fdf4}.nc-band{display:grid;grid-template-columns:1.2fr .8fr;gap:16px}.nc-table{width:100%;border-collapse:collapse}.nc-table th,.nc-table td{padding:10px 8px;border-bottom:1px solid var(--line);text-align:left;font-size:.86rem;vertical-align:top}.nc-table th{font-size:.72rem;text-transform:uppercase;color:var(--muted)}.nc-list{display:grid;gap:10px}.nc-item{display:flex;justify-content:space-between;gap:12px;border:1px solid var(--line);border-radius:8px;padding:11px;background:#fff}.nc-item small{display:block;color:var(--muted);margin-top:3px}.nc-progress{height:8px;border-radius:999px;background:#eef2f4;overflow:hidden}.nc-progress span{display:block;height:100%;background:var(--green)}.nc-progress.warn span{background:#b7791f}.nc-actions{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.nc-actions a{min-height:92px;display:grid;align-content:center;gap:8px;border:1px solid var(--line);border-radius:8px;background:#fff;padding:14px;text-decoration:none;color:var(--ink);font-weight:950}.nc-actions i{color:var(--green);font-size:1.25rem}@media(max-width:1100px){.nc-band,.nc-actions{grid-template-columns:1fr}}
</style>
<section class="nc-command">
  <section class="card truth <?= $integrityPct >= 80 ? 'good' : '' ?>">
    <h2>Data Integrity & Governance Truth</h2>
    <p>National coordination can only compare states using records with valid `state_id` and `lga_id`. Legacy records are preserved, but they are not counted as state performance until mapped.</p>
    <div class="kpis"><div class="kpi"><i class="fas fa-database"></i><b><?= number_format($totalApps) ?></b><span>Total applications</span></div><div class="kpi"><i class="fas fa-map-location-dot"></i><b><?= number_format($mappedApps) ?></b><span>Mapped to state</span></div><div class="kpi"><i class="fas fa-triangle-exclamation"></i><b><?= number_format($unmappedApps) ?></b><span>Need state mapping</span></div><div class="kpi"><i class="fas fa-location-dot"></i><b><?= number_format($lgaMappedApps) ?></b><span>Mapped to LGA</span></div><div class="kpi"><i class="fas fa-gauge"></i><b><?= e((string) $integrityPct) ?>%</b><span>State mapping integrity</span></div></div>
  </section>

  <div class="kpis"><div class="kpi"><i class="fas fa-map"></i><b><?= number_format(count($stateRows)) ?></b><span>True states reporting</span></div><div class="kpi"><i class="fas fa-circle-check"></i><b><?= number_format($activeApps) ?></b><span>Active / verified status</span></div><div class="kpi"><i class="fas fa-user-check"></i><b><?= number_format($confirmedApps) ?></b><span>Email-confirmed records</span></div><div class="kpi"><i class="fas fa-headset"></i><b><?= number_format($openSupport) ?></b><span>Open support load</span></div><div class="kpi"><i class="fas fa-clipboard-list"></i><b><?= number_format($fieldTasks) ?></b><span>Open field tasks</span></div></div>

  <div class="nc-band"><section class="card"><h2>Mapped State Performance</h2><table class="nc-table"><thead><tr><th>State</th><th>Applications</th><th>Active</th><th>LGA Mapped</th><th>LGAs</th><th>Integrity</th></tr></thead><tbody><?php foreach ($stateRows as $row): $total=max(1,(int)$row['total']); $pct=round(((int)$row['lga_mapped_total']/$total)*100); ?><tr><td><strong><?= e((string)$row['state_name']) ?></strong><br><small>Last activity <?= e($row['last_seen'] ? date('M j, Y', strtotime((string)$row['last_seen'])) : '-') ?></small></td><td><?= number_format((int)$row['total']) ?></td><td><?= number_format((int)$row['active_total']) ?></td><td><?= number_format((int)$row['lga_mapped_total']) ?></td><td><?= number_format((int)$row['lga_total']) ?></td><td><div class="nc-progress <?= $pct < 70 ? 'warn' : '' ?>"><span style="width:<?= $pct ?>%"></span></div><small><?= $pct ?>%</small></td></tr><?php endforeach; ?><?php if (!$stateRows): ?><tr><td colspan="6">No applications are mapped to states yet.</td></tr><?php endif; ?></tbody></table></section><section class="card"><h2>Legacy Records Needing Cleanup</h2><div class="nc-list"><?php foreach ($unmappedRows as $row): ?><div class="nc-item"><span><strong><?= e((string)$row['location_hint']) ?></strong><small>Last seen <?= e($row['last_seen'] ? date('M j, Y', strtotime((string)$row['last_seen'])) : '-') ?></small></span><span class="badge"><?= number_format((int)$row['total']) ?></span></div><?php endforeach; ?><?php if (!$unmappedRows): ?><div class="empty">No unmapped legacy applications.</div><?php endif; ?></div></section></div>

  <div class="grid"><section class="card span-6"><h2>Farm Footprint By State</h2><div class="nc-list"><?php foreach ($farmRows as $row): ?><div class="nc-item"><span><strong><?= e((string)$row['state_name']) ?></strong><small><?= number_format((int)$row['gps_ready']) ?> farms have GPS</small></span><span class="badge"><?= number_format((int)$row['farms']) ?></span></div><?php endforeach; ?></div></section><section class="card span-6"><h2>Support & Escalation Watch</h2><div class="nc-list"><?php foreach ($escalations as $ticket): ?><div class="nc-item"><span><strong><?= e((string)$ticket['ticket_ref']) ?> / <?= e((string)$ticket['subject']) ?></strong><small><?= e((string)$ticket['requester_role']) ?> / <?= e((string)$ticket['category']) ?> / <?= e((string)$ticket['priority']) ?></small></span><span class="badge"><?= e((string)$ticket['status']) ?></span></div><?php endforeach; ?><?php if (!$escalations): ?><div class="empty">No active escalations or open support tickets.</div><?php endif; ?></div></section></div>

  <section class="card"><h2>Recent Registry Activity With Mapping Evidence</h2><table class="nc-table"><thead><tr><th>Ref</th><th>Applicant</th><th>Mapped State</th><th>Mapped LGA</th><th>Legacy Location</th><th>Status</th><th>Submitted</th></tr></thead><tbody><?php foreach ($recentActivity as $row): ?><tr><td><?= e((string)$row['app_ref']) ?></td><td><?= e((string)$row['name']) ?></td><td><?= e((string)$row['state_name']) ?></td><td><?= e((string)$row['lga']) ?></td><td><?= e((string)$row['location']) ?></td><td><span class="badge"><?= e((string)$row['review_status']) ?></span></td><td><?= e($row['created_at'] ? date('M j, Y', strtotime((string)$row['created_at'])) : '-') ?></td></tr><?php endforeach; ?></tbody></table></section>

  <section class="nc-actions"><a href="operations.php"><i class="fas fa-list-check"></i><span>National Operations</span></a><a href="reports.php"><i class="fas fa-chart-line"></i><span>Reports & Intelligence</span></a><a href="support.php"><i class="fas fa-headset"></i><span>Support Oversight</span></a><a href="academy.php"><i class="fas fa-graduation-cap"></i><span>Training Readiness</span></a></section>
</section>
<?php coord_footer(); ?>