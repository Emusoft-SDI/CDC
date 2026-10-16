<?php
declare(strict_types=1);
require_once __DIR__ . '/_coordination.php';

$pdo = coord_pdo();
$user = coord_require($pdo);
$scope = coord_scope_filter($pdo, $user);
$where = $scope['where'];
$params = $scope['params'];
$stateLabel = (string) ($scope['label'] ?: 'National');

// Scoping mechanism: tickets are tied to the coordinator's state through the
// requester's application record (alias `a`). State coordinators only see
// tickets raised by users in their state; national coordinators see all.
$userScopeJoin = 'LEFT JOIN applications a ON a.id = u.application_id LEFT JOIN nigeria_states ns ON ns.id = a.state_id';
$ticketFrom = 'support_tickets st LEFT JOIN users u ON u.id = st.user_id ' . $userScopeJoin;

$openQueue = coord_scalar($pdo, "SELECT COUNT(*) FROM {$ticketFrom} WHERE {$where} AND st.status IN ('open','in_progress','waiting_on_user','escalated')", $params);
$overdueQueue = coord_scalar($pdo, "SELECT COUNT(*) FROM {$ticketFrom} WHERE {$where} AND st.status IN ('open','in_progress','waiting_on_user','escalated') AND st.sla_due_at IS NOT NULL AND st.sla_due_at < NOW()", $params);
$escalatedQueue = coord_scalar($pdo, "SELECT COUNT(*) FROM {$ticketFrom} WHERE {$where} AND st.status = 'escalated'", $params);
$resolved30 = coord_scalar($pdo, "SELECT COUNT(*) FROM {$ticketFrom} WHERE {$where} AND st.status IN ('resolved','closed') AND st.resolved_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)", $params);
$tickets = coord_rows($pdo, "SELECT st.id, st.ticket_ref, st.subject, st.category, st.priority, st.status, st.sla_due_at, st.created_at, st.last_activity_at, st.requester_name, st.requester_email, st.assigned_team, u.name user_name, u.email user_email FROM {$ticketFrom} WHERE {$where} ORDER BY FIELD(st.status,'escalated','open','in_progress','waiting_on_user','rejected','resolved','closed'), st.last_activity_at DESC, st.id DESC LIMIT 50", $params);

$categories = support_categories();

function coord_ticket_age(string $createdAt): string
{
    $timestamp = strtotime($createdAt);
    if (!$timestamp) {
        return '-';
    }
    $hours = max(0, (int) floor((time() - $timestamp) / 3600));
    if ($hours < 1) {
        return '<1h';
    }
    if ($hours < 48) {
        return $hours . 'h';
    }
    return (int) floor($hours / 24) . 'd';
}

coord_header('Coordination Support', 'Track escalations and support issues that require coordinator attention.', $user, 'support');
?>
<style>
.coord-table{width:100%;border-collapse:collapse}
.coord-table th,.coord-table td{padding:10px 8px;border-bottom:1px solid var(--line);text-align:left;font-size:.86rem;vertical-align:top}
.coord-table th{font-size:.72rem;text-transform:uppercase;color:var(--muted)}
.coord-empty{color:var(--muted);padding:14px;border:1px dashed var(--line);border-radius:8px}
</style>
<?php if (!empty($scope['missing'])): ?><div class="card" style="margin-bottom:16px;border-color:#facc15;background:#fffbeb"><h2><?= e(ucfirst((string) ($scope['tier'] ?? 'state'))) ?> Assignment Required</h2><p>This coordinator has access, but no <?= e((string) ($scope['tier'] ?? 'state')) ?> scope is assigned. An operator must assign the enclave in RBAC before support tickets can appear here.</p></div><?php endif; ?>
<div class="kpis"><div class="kpi"><i class="fas fa-headset"></i><b><?= number_format($openQueue) ?></b><span>Open queue in scope</span></div><div class="kpi"><i class="fas fa-triangle-exclamation"></i><b><?= number_format($overdueQueue) ?></b><span>SLA overdue</span></div><div class="kpi"><i class="fas fa-bullhorn"></i><b><?= number_format($escalatedQueue) ?></b><span>Escalated</span></div><div class="kpi"><i class="fas fa-circle-check"></i><b><?= number_format($resolved30) ?></b><span>Resolved (30 days)</span></div><div class="kpi"><i class="fas fa-map-location-dot"></i><b><?= e($stateLabel) ?></b><span>Ticket scope</span></div></div>
<div class="grid">
<section class="card span-12"><h2>Support Ticket Queue — <?= e($stateLabel) ?></h2><p style="color:var(--muted)">Read-only queue. State coordinators see only tickets raised by users in their assigned state; national coordinators see every ticket. Select a ticket reference to open it in the support desk.</p><table class="coord-table"><thead><tr><th>Ticket</th><th>Subject</th><th>Requester</th><th>Category</th><th>Priority</th><th>Status</th><th>SLA Due</th><th>Age</th><th>Last Activity</th></tr></thead><tbody><?php foreach ($tickets as $row): ?><?php $openish = in_array((string) ($row['status'] ?? ''), ['open','in_progress','waiting_on_user','escalated'], true); $slaDue = (string) ($row['sla_due_at'] ?? ''); $slaOverdue = $openish && $slaDue !== '' && strtotime($slaDue) < time(); ?><tr><td><a href="../support/index.php?ticket=<?= e(urlencode((string) ($row['ticket_ref'] ?? ''))) ?>&email=<?= e(urlencode((string) ($row['requester_email'] ?? ''))) ?>"><strong><?= e((string) ($row['ticket_ref'] ?? '')) ?></strong></a><br><small><?= e((string) ($row['assigned_team'] ?: '-')) ?></small></td><td><?= e((string) ($row['subject'] ?? '')) ?></td><td><strong><?= e((string) (($row['user_name'] ?: $row['requester_name'] ?: 'Unknown'))) ?></strong><br><small><?= e((string) (($row['user_email'] ?: $row['requester_email'] ?: ''))) ?></small></td><td><?= e((string) (($categories[(string) ($row['category'] ?? '')]['label'] ?? ucwords(str_replace('_', ' ', (string) ($row['category'] ?? '')))))) ?></td><td><span class="badge <?= e(support_badge_class((string) ($row['priority'] ?? ''))) ?>"><?= e(ucfirst((string) ($row['priority'] ?? ''))) ?></span></td><td><span class="badge <?= e(support_badge_class((string) ($row['status'] ?? ''))) ?>"><?= e(ucwords(str_replace('_', ' ', (string) ($row['status'] ?? '')))) ?></span></td><td><?php if ($slaDue !== ''): ?><span class="badge <?= $slaOverdue ? 'bad' : 'neutral' ?>"><?= e($slaOverdue ? 'Overdue ' : '') ?><?= e(date('M j, H:i', strtotime($slaDue))) ?></span><?php else: ?>-<?php endif; ?></td><td><?= e(coord_ticket_age((string) ($row['created_at'] ?? ''))) ?></td><td><?= e($row['last_activity_at'] ? date('M j, H:i', strtotime((string) $row['last_activity_at'])) : '-') ?></td></tr><?php endforeach; ?><?php if (!$tickets): ?><tr><td colspan="9"><div class="coord-empty">No support tickets found for this scope yet.</div></td></tr><?php endif; ?></tbody></table></section>
<section class="card span-12"><div class="list"><a class="row" href="operations.php"><strong>Operations queue</strong><span class="badge">Lead</span></a><a class="row" href="reports.php"><strong>Reports</strong><span class="badge">Report</span></a><a class="row" href="wallet.php"><strong>Wallet oversight</strong><span class="badge">Wallet</span></a><a class="row" href="<?= e(coord_home(coord_role_key($user))) ?>"><strong>Coordinator home</strong><span class="badge">Home</span></a></div></section>
</div>
<?php coord_footer(); ?>
