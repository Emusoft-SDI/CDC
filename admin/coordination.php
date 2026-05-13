<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/admin-layout.php';
require_once __DIR__ . '/../lib/field-management.php';
require_once __DIR__ . '/../lib/agronomy.php';

session_start();
$pdo = db();
admin_ensure_schema($pdo);
fm_ensure_schema($pdo);
agronomy_ensure_schema($pdo);
admin_require($pdo);

$user = current_user($pdo) ?: [];
$platformRole = admin_current_platform_role($pdo) ?: 'admin';
$roleLabels = [
    'super_admin' => 'Super Administrator',
    'national_coordinator' => 'National Coordinator',
    'state_coordinator' => 'State Coordinator',
    'investor' => 'Investor',
    'admin' => 'Administrator',
    'field_agent' => 'Field Agent',
    'agronomist' => 'Agronomist',
    'agric_extensionist' => 'Agric Extensionist',
    'grower' => 'Grower',
];

$staffStmt = $pdo->prepare("SELECT * FROM staff_profiles WHERE user_id = ? LIMIT 1");
$staffStmt->execute([(int) ($user['id'] ?? 0)]);
$staffProfile = $staffStmt->fetch() ?: [];
$scopeState = trim((string) ($staffProfile['state'] ?? $user['location'] ?? ''));
$isStateScope = $platformRole === 'state_coordinator';
$scopeLabel = $isStateScope ? ($scopeState !== '' ? $scopeState : 'State not assigned') : 'National';
$scopeWarning = $isStateScope && $scopeState === '';

$stateSql = '';
$stateParams = [];
if ($isStateScope && $scopeState !== '') {
    $stateSql = " AND (
        ns.state_name = ?
        OR a.location LIKE ?
        OR u.location LIKE ?
        OR sp.state = ?
    )";
    $stateParams = [$scopeState, '%' . $scopeState . '%', '%' . $scopeState . '%', $scopeState];
}

$countScoped = static function (PDO $pdo, string $where, array $params = []) use ($stateSql, $stateParams): int {
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT u.id)
        FROM users u
        LEFT JOIN applications a ON a.id = u.application_id
        LEFT JOIN grower_farms gf ON gf.user_id = u.id
        LEFT JOIN nigeria_states ns ON ns.id = gf.state_id OR ns.id = a.state_id
        LEFT JOIN staff_profiles sp ON sp.user_id = u.id
        WHERE {$where}{$stateSql}
    ");
    $stmt->execute(array_merge($params, $stateParams));
    return (int) $stmt->fetchColumn();
};

$stats = [
    'growers' => $countScoped($pdo, "u.role = 'grower'"),
    'field_agents' => $countScoped($pdo, "u.role = 'field_agent' AND COALESCE(sp.staff_type, 'field_agent') = 'field_agent'"),
    'agronomists' => $countScoped($pdo, "(u.platform_role = 'agronomist' OR u.is_agronomist = 1 OR sp.staff_type = 'agronomist')"),
    'extensionists' => $countScoped($pdo, "(u.platform_role = 'agric_extensionist' OR u.is_extensionist = 1 OR sp.staff_type IN ('extensionist','agric_extensionist'))"),
];

$farmWhere = $isStateScope && $scopeState !== '' ? 'WHERE ns.state_name = ?' : '';
$farmStmt = $pdo->prepare("
    SELECT COUNT(DISTINCT gf.id)
    FROM grower_farms gf
    LEFT JOIN nigeria_states ns ON ns.id = gf.state_id
    {$farmWhere}
");
$farmStmt->execute($farmWhere ? [$scopeState] : []);
$stats['farms'] = (int) $farmStmt->fetchColumn();

$caseWhere = $isStateScope && $scopeState !== '' ? "AND (ns.state_name = ? OR u.location LIKE ?)" : '';
$caseStmt = $pdo->prepare("
    SELECT COUNT(DISTINCT ac.id)
    FROM agronomy_cases ac
    JOIN users u ON u.id = ac.grower_id
    LEFT JOIN grower_farms gf ON gf.id = ac.farm_id
    LEFT JOIN nigeria_states ns ON ns.id = gf.state_id
    WHERE ac.status NOT IN ('resolved','closed') {$caseWhere}
");
$caseStmt->execute($caseWhere ? [$scopeState, '%' . $scopeState . '%'] : []);
$stats['agronomy_cases'] = (int) $caseStmt->fetchColumn();

$peopleStmt = $pdo->prepare("
    SELECT u.id, u.name, u.email, u.role, u.platform_role, u.location, COALESCE(sp.staff_type, u.platform_role, u.role) staff_type,
           sp.state, sp.lga
    FROM users u
    LEFT JOIN applications a ON a.id = u.application_id
    LEFT JOIN grower_farms gf ON gf.user_id = u.id
    LEFT JOIN nigeria_states ns ON ns.id = gf.state_id OR ns.id = a.state_id
    LEFT JOIN staff_profiles sp ON sp.user_id = u.id
    WHERE u.role IN ('grower','field_agent','admin') {$stateSql}
    GROUP BY u.id
    ORDER BY FIELD(u.role, 'admin','field_agent','grower'), u.name
    LIMIT 12
");
$peopleStmt->execute($stateParams);
$people = $peopleStmt->fetchAll();

$entryCatalog = [
    'super_admin' => [
        ['label' => 'Access & Module Setup', 'href' => '../super-admin/index.php?view=controls', 'body' => 'Define role boundaries, feature flags, module owners, and operating modes.'],
        ['label' => 'User Management', 'href' => 'users.php', 'body' => 'Create, edit, import, activate, or suspend system users.'],
        ['label' => 'System Health', 'href' => 'monitoring.php', 'body' => 'Review operational status and production-readiness signals.'],
    ],
    'national_coordinator' => [
        ['label' => 'National Dashboard', 'href' => 'national-dashboard.php', 'body' => 'Compare states, supervise national metrics, and drive strategic project oversight.'],
        ['label' => 'National Applications', 'href' => 'admin.php', 'body' => 'Review applications and registry activity across all states.'],
        ['label' => 'Field Network', 'href' => 'fields-management.php', 'body' => 'Coordinate farm verification and field activity nationally.'],
        ['label' => 'Reports & Analytics', 'href' => 'analytics.php', 'body' => 'Monitor adoption, verification, support, and performance indicators.'],
    ],
    'state_coordinator' => [
        ['label' => 'State Operations Dashboard', 'href' => 'state-dashboard.php', 'body' => 'Manage farmers, accreditation, resources, communication, and field performance inside your state.'],
        ['label' => 'State Farmers', 'href' => 'users.php?role=grower', 'body' => 'Manage growers attached to your state.'],
        ['label' => 'State Field Network', 'href' => 'assign-growers.php', 'body' => 'Coordinate field agents, agronomists, and extension support in your state.'],
        ['label' => 'State Farm Cases', 'href' => 'fields-management.php', 'body' => 'Track farms, verification, visits, and GPS confidence for your territory.'],
        ['label' => 'Agronomy Advisory', 'href' => 'agronomy.php', 'body' => 'Review agronomy cases raised by growers or field observations.'],
    ],
    'admin' => [
        ['label' => 'Applications', 'href' => 'admin.php', 'body' => 'Operate application review and grower onboarding.'],
        ['label' => 'Documents', 'href' => 'document-verification.php', 'body' => 'Verify submitted identity and farm records.'],
        ['label' => 'Support Desk', 'href' => 'support.php', 'body' => 'Respond to user requests and operational issues.'],
    ],
    'field_agent' => [
        ['label' => 'Field Agent Console', 'href' => '../field-agent/index.php', 'body' => 'Complete assigned visits, capture GPS, submit farm observations.'],
        ['label' => 'Assignments', 'href' => 'assign-growers.php', 'body' => 'Review assigned growers and field workload.'],
    ],
    'agronomist' => [
        ['label' => 'Agronomy Cases', 'href' => 'agronomy.php', 'body' => 'Review crop, soil, pest, disease, and water-related cases.'],
        ['label' => 'Field Observations', 'href' => 'fields-management.php', 'body' => 'Use farm visit evidence before issuing recommendations.'],
    ],
    'agric_extensionist' => [
        ['label' => 'Grower Support', 'href' => 'support.php', 'body' => 'Handle grower education and advisory follow-up.'],
        ['label' => 'Field Network', 'href' => 'fields-management.php', 'body' => 'Support farm verification and extension visit workflows.'],
    ],
    'investor' => [
        ['label' => 'Analytics', 'href' => 'analytics.php', 'body' => 'Review aggregate performance and program signals.'],
        ['label' => 'Marketplace', 'href' => 'marketplace.php', 'body' => 'View marketplace activities and opportunities.'],
    ],
    'grower' => [
        ['label' => 'Grower Dashboard', 'href' => '../dashboard/index.php', 'body' => 'Track farm health, verification, agronomy support, wallet, and services.'],
        ['label' => 'Farm Health', 'href' => '../dashboard/farm-health.php', 'body' => 'Request farm review and agronomy support.'],
    ],
];

$roleEntries = $entryCatalog[$platformRole] ?? $entryCatalog['admin'];
$roleCoverage = [
    ['role' => 'Super Administrator', 'entry' => '../super-admin/index.php', 'dashboard' => 'Super Admin control plane', 'scope' => 'Whole platform', 'manages' => 'Modules, access controls, users, audit, settings, integrations.'],
    ['role' => 'National Coordinator', 'entry' => 'national-dashboard.php', 'dashboard' => 'National coordinator dashboard', 'scope' => 'All states', 'manages' => 'State coordinators, farmers, field network, reports, national operations.'],
    ['role' => 'State Coordinator', 'entry' => 'state-dashboard.php', 'dashboard' => 'State coordinator dashboard', 'scope' => 'Assigned state', 'manages' => 'Farmers, field agents, agronomists, extensionists, farms, resources, communication, cases in their state.'],
    ['role' => 'Administrator', 'entry' => 'coordination.php', 'dashboard' => 'Operations dashboard', 'scope' => 'Operational modules granted by Super Admin', 'manages' => 'Applications, documents, support, field workflows, content.'],
    ['role' => 'Field Agent', 'entry' => '../field-agent/index.php', 'dashboard' => 'Field agent console', 'scope' => 'Assigned visits and growers', 'manages' => 'Farm visits, GPS capture, checklists, field observations.'],
    ['role' => 'Agronomist', 'entry' => 'agronomy.php', 'dashboard' => 'Agronomy advisory workbench', 'scope' => 'Assigned or visible agronomy cases', 'manages' => 'Crop/soil recommendations, advisory templates, follow-up notes.'],
    ['role' => 'Agric Extensionist', 'entry' => 'support.php', 'dashboard' => 'Extension support workbench', 'scope' => 'Assigned growers or territory', 'manages' => 'Grower education, support follow-up, field adoption guidance.'],
    ['role' => 'Investor', 'entry' => '../dashboard/index.php', 'dashboard' => 'Investor-facing dashboard', 'scope' => 'Investment/reporting view', 'manages' => 'Marketplace, wallet, reports, analytics access.'],
    ['role' => 'Grower', 'entry' => '../dashboard/index.php', 'dashboard' => 'Grower dashboard', 'scope' => 'Own profile and farms', 'manages' => 'Farm profile, farm health, agronomy requests, wallet, services.'],
];

admin_page_start($roleLabels[$platformRole] ?? 'Role Dashboard', [
    'active' => 'coordination.php',
    'description' => 'Role-specific command center with the right entry points, operational scope, and people view.',
    'wide' => true,
    'css' => '
      .scope-band { display:flex; justify-content:space-between; gap:16px; align-items:center; }
      .role-entry-grid { grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); }
      .people-table td:first-child { width:28%; }
      @media (max-width:760px) { .scope-band { display:block; } }
    ',
]);
?>
<?php if ($scopeWarning): ?>
  <div class="notice error">This State Coordinator has no state assigned yet. Set the staff profile state so their dashboard and management scope can be restricted correctly.</div>
<?php endif; ?>

<section class="panel scope-band">
  <div>
    <h2><?= e($roleLabels[$platformRole] ?? $platformRole) ?> Scope</h2>
    <p class="muted">Operational scope: <strong><?= e($scopeLabel) ?></strong></p>
  </div>
  <div class="actions">
    <a class="button secondary" href="users.php">Manage People</a>
    <a class="button secondary" href="fields-management.php">Manage Fields</a>
  </div>
</section>

<section class="stats">
  <div class="stat"><div class="metric"><?= (int) $stats['growers'] ?></div><strong>Growers</strong></div>
  <div class="stat"><div class="metric"><?= (int) $stats['farms'] ?></div><strong>Farms</strong></div>
  <div class="stat"><div class="metric"><?= (int) $stats['field_agents'] ?></div><strong>Field Agents</strong></div>
  <div class="stat"><div class="metric"><?= (int) $stats['agronomists'] ?></div><strong>Agronomists</strong></div>
  <div class="stat"><div class="metric"><?= (int) $stats['extensionists'] ?></div><strong>Extensionists</strong></div>
  <div class="stat"><div class="metric"><?= (int) $stats['agronomy_cases'] ?></div><strong>Open Agronomy Cases</strong></div>
</section>

<section class="panel">
  <h2>Entry Points for This Role</h2>
  <div class="grid role-entry-grid">
    <?php foreach ($roleEntries as $entry): ?>
      <article class="card">
        <h3><?= e($entry['label']) ?></h3>
        <p class="muted"><?= e($entry['body']) ?></p>
        <a class="button secondary" href="<?= e($entry['href']) ?>">Open</a>
      </article>
    <?php endforeach; ?>
  </div>
</section>

<section class="panel">
  <h2><?= $isStateScope ? 'People in State Scope' : 'Recent People in Network' ?></h2>
  <table class="people-table">
    <thead><tr><th>Name</th><th>Role</th><th>Email</th><th>State / LGA</th></tr></thead>
    <tbody>
      <?php foreach ($people as $person): ?>
        <tr>
          <td><?= e($person['name']) ?></td>
          <td><?= e(ucwords(str_replace('_', ' ', (string) ($person['platform_role'] ?: $person['staff_type'])))) ?></td>
          <td><?= e($person['email']) ?></td>
          <td><?= e($person['state'] ?: $person['location'] ?: 'Not assigned') ?><?= $person['lga'] ? ' / ' . e($person['lga']) : '' ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$people): ?><tr><td colspan="4">No people found for this scope yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</section>

<section class="panel">
  <h2>Platform Role Coverage</h2>
  <table>
    <thead><tr><th>Role</th><th>Entry Point</th><th>Dashboard</th><th>Scope</th><th>Manages</th></tr></thead>
    <tbody>
      <?php foreach ($roleCoverage as $coverage): ?>
        <tr>
          <td><strong><?= e($coverage['role']) ?></strong></td>
          <td><a href="<?= e($coverage['entry']) ?>">Open</a></td>
          <td><?= e($coverage['dashboard']) ?></td>
          <td><?= e($coverage['scope']) ?></td>
          <td><?= e($coverage['manages']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php admin_page_end(); ?>
