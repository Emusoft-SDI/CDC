<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../lib/admin-layout.php';
require_once __DIR__ . '/../lib/enrollment-applications.php';
require_once __DIR__ . '/../lib/user-workspaces.php';
require_once __DIR__ . '/../coordination/_coordination.php';

$pdo = db();
admin_ensure_schema($pdo);
enrollment_ensure_schema($pdo);
coord_ensure_schema($pdo);
admin_require($pdo, 'user_management');

$coordinatorTiers = coord_tier_roles();
$coordinatorTierPlaceholders = implode(',', array_fill(0, count($coordinatorTiers), '?'));

// Coordinator KPIs (Phase 3 metrics — displayed here instead of on operations page)
$activeCoordinatorsCount = 0;
if (app_table_exists($pdo, 'user_role_assignments')) {
    try {
        $acStmt = $pdo->prepare("SELECT COUNT(*) FROM user_role_assignments WHERE status = 'active' AND role_key IN ({$coordinatorTierPlaceholders})");
        $acStmt->execute($coordinatorTiers);
        $activeCoordinatorsCount = (int) $acStmt->fetchColumn();
    } catch (Throwable $e) {
    }
}

$pendingCoordinatorEnrollments = 0;
if (app_table_exists($pdo, 'enrollment_applications')) {
    try {
        $pendStmt = $pdo->prepare("SELECT COUNT(*) FROM enrollment_applications WHERE status = 'pending' AND applicant_type = 'coordinator'");
        $pendStmt->execute();
        $pendingCoordinatorEnrollments = (int) $pendStmt->fetchColumn();
    } catch (Throwable $e) {
    }
}

$enrollmentPipeline7d = 0;
if (app_table_exists($pdo, 'enrollment_applications')) {
    try {
        $pipeStmt = $pdo->prepare("SELECT COUNT(*) FROM enrollment_applications WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $pipeStmt->execute();
        $enrollmentPipeline7d = (int) $pipeStmt->fetchColumn();
    } catch (Throwable $e) {
    }
}

$uncoveredStates = 0;
if (app_table_exists($pdo, 'nigeria_states') && app_table_exists($pdo, 'user_role_assignments')) {
    try {
        $uncovStmt = $pdo->prepare("SELECT COUNT(*) FROM nigeria_states WHERE state_name NOT IN (SELECT DISTINCT ura.scope_value FROM user_role_assignments ura WHERE ura.role_key = 'state_coordinator' AND ura.status = 'active' AND ura.scope_type = 'state' AND COALESCE(ura.scope_value, '') <> '')");
        $uncovStmt->execute();
        $uncoveredStates = (int) $uncovStmt->fetchColumn();
    } catch (Throwable $e) {
    }
}

$coordinatorTiers = coord_tier_roles();
$coordinatorTierPlaceholders = implode(',', array_fill(0, count($coordinatorTiers), '?'));

$selectedTierFilter = preg_replace('/[^a-z_]/i', '', (string) ($_GET['tier_filter'] ?? ''));
if ($selectedTierFilter !== '' && !in_array($selectedTierFilter, $coordinatorTiers, true)) {
    $selectedTierFilter = '';
}

$stateFilter = trim((string) ($_GET['state_filter'] ?? ''));

$whereClause = '1=1';
$params = [];
if ($selectedTierFilter !== '' && in_array($selectedTierFilter, $coordinatorTiers, true)) {
    $whereClause .= " AND EXISTS (
        SELECT 1 FROM user_role_assignments ura
        WHERE ura.user_id = u.id
          AND ura.status = 'active'
          AND ura.role_key = ?
    )";
    $params[] = $selectedTierFilter;
}

if ($stateFilter !== '' && app_table_exists($pdo, 'nigeria_states')) {
    $whereClause .= " AND LOWER(ura.scope_value) = LOWER(?)";
    $params[] = $stateFilter;
}

$coordinatorTierList = implode(',', array_map(fn($t) => "'$t'", $coordinatorTiers));
$coordinatorsStmt = $pdo->prepare("
    SELECT u.id, u.name, u.email, u.phone, u.platform_role, u.account_status, u.last_login_at, u.created_at,
           GROUP_CONCAT(DISTINCT ura.role_key SEPARATOR ', ') AS coordinator_tiers,
           GROUP_CONCAT(DISTINCT CONCAT(ura.scope_type, ':', COALESCE(ura.scope_value, '')) SEPARATOR ', ') AS assigned_scopes
    FROM users u
    INNER JOIN user_role_assignments ura ON ura.user_id = u.id
        AND ura.status = 'active'
        AND ura.role_key IN ({$coordinatorTierList})
    WHERE {$whereClause}
    GROUP BY u.id
    ORDER BY u.created_at DESC, u.id DESC
");
$coordinatorsStmt->execute($params);
$coordinators = $coordinatorsStmt->fetchAll();

$activeCount = 0;
foreach ($coordinators as $c) {
    if (strtolower((string) ($c['account_status'] ?? '')) !== 'suspended' && strtolower((string) ($c['account_status'] ?? '')) !== 'deactivated') {
        $activeCount++;
    }
}

admin_page_start('Coordinators & Enclaves', [
    'active' => 'coordinators.php',
    'description' => 'Every account holding an active coordinator-tier assignment (national, regional, state, LGA, district, village) with their enclave scopes and the farmer registrations their coverage includes. Coordinators are read-only here — edit accounts via User Governance.',
    'wide' => true,
]);
?>

<section class="stats">
    <div class="stat"><span>Active Coordinators</span><div class="metric"><?= number_format($activeCount) ?></div></div>
    <div class="stat"><span>Total With Active Assignments</span><div class="metric"><?= number_format(count($coordinators)) ?></div></div>
</section>

<section class="panel">
    <div class="section-head">
        <div>
            <h2>Coordinators &amp; Enclaves</h2>
            <p>Filter by coordinator tier and geographic enclave scope. The "Farmers in Enclave" column counts unique grower registrations that fall within each coordinator's assigned scope.</p>
        </div>
        <div class="actions">
            <form method="get" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap">
                <label for="tier_filter" style="font-size:12px;font-weight:600">Filter by tier:</label>
                <select id="tier_filter" name="tier_filter" onchange="this.form.submit()">
                    <option value="">All coordinators</option>
                    <?php foreach ($coordinatorTiers as $tierKey): ?>
                        <option value="<?= e($tierKey) ?>" <?= $selectedTierFilter === $tierKey ? 'selected' : '' ?>>
                            <?= e(ucfirst(str_replace('_', ' ', $tierKey))) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (app_table_exists($pdo, 'nigeria_states')): ?>
                    <label for="state_filter" style="font-size:12px;font-weight:600">Filter by state:</label>
                    <select id="state_filter" name="state_filter" onchange="this.form.submit()">
                        <option value="">All states</option>
                        <?php
                        $stateRows = [];
                        if (app_table_exists($pdo, 'nigeria_states') && app_column_exists($pdo, 'nigeria_states', 'state_name')) {
                            try {
                                $stateRows = $pdo->query("SELECT state_name FROM nigeria_states ORDER BY state_name")->fetchAll();
                            } catch (Throwable $e) {
                                $stateRows = [];
                            }
                        }
                        foreach ($stateRows as $s):
                            $sv = (string) ($s['state_name'] ?? '');
                        ?>
                            <option value="<?= e($sv) ?>" <?= $stateFilter === $sv ? 'selected' : '' ?>>
                                <?= e($sv) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </form>
            <a class="button secondary" href="coordinators.php">Clear Filters</a>
        </div>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Coordinator</th>
                    <th>Tier</th>
                    <th>Assigned Scopes</th>
                    <th>Farmers in Enclave</th>
                    <th>Activity</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($coordinators as $coordinator): ?>
                <?php
                    $tiers = array_values(array_unique(array_filter(array_map('trim',
                        explode(',', (string) ($coordinator['coordinator_tiers'] ?? ''))))));
                    $scopes = array_values(array_unique(array_filter(array_map('trim',
                        explode(',', (string) ($coordinator['assigned_scopes'] ?? ''))))));
                    $accountStatus = strtolower((string) ($coordinator['account_status'] ?? 'active'));
                    $farmerCount = coord_enclave_farmer_count($pdo, $coordinator);
                    $isUncovered = ($farmerCount === 0);
                ?>
                <tr<?= $isUncovered ? ' data-state="uncovered"' : '' ?>>
                    <td>
                        <strong><?= e((string) $coordinator['name']) ?></strong><br>
                        <small><?= e((string) $coordinator['email']) ?></small>
                        <?php if (!empty($coordinator['phone'])): ?><br><small><?= e((string) $coordinator['phone']) ?></small><?php endif; ?>
                    </td>
                    <td>
                        <?php foreach ($tiers as $tier): ?>
                            <span class="role-pill"><?= e(ucfirst(str_replace('_', ' ', $tier))) ?></span>
                        <?php endforeach; ?>
                        <?php if (!$tiers): ?><small>Coordinator</small><?php endif; ?>
                    </td>
                    <td>
                        <?php if ($scopes): ?>
                            <?php foreach ($scopes as $scope): ?><small><?= e($scope) ?></small><?php endforeach; ?>
                        <?php else: ?><small>No enclave assigned</small><?php endif; ?>
                    </td>
                    <td>
                        <strong><?= number_format($farmerCount) ?></strong> registrations
                        <?php if ($isUncovered): ?><span class="badge warning" style="margin-left:6px">Coverage gap</span><?php endif; ?>
                    </td>
                    <td>
                        <span class="badge <?= $accountStatus !== 'suspended' && $accountStatus !== 'deactivated' ? 'ok-badge' : 'warning' ?>">
                            <?= e($accountStatus) ?>
                        </span>
                        <?php if (!empty($coordinator['last_login_at'])): ?>
                            <small>Last login <?= e(date('M j, Y', strtotime((string) $coordinator['last_login_at']))) ?></small>
                        <?php else: ?>
                            <small>Never signed in</small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a class="button secondary" href="users.php?q=<?= e(urlencode((string) $coordinator['email'])) ?>">Open in User Governance</a>
                        <a class="button secondary" href="reports/?page=coordinator_coverage">Coverage Report</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$coordinators): ?>
                <tr><td colspan="6" class="empty">No coordinator assignments found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php admin_page_end(); ?>
