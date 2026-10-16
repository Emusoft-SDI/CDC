<?php
if (!function_exists('coord_state_aliases')) {
    require_once __DIR__ . '/../../../coordination/_coordination.php';
}
/**
 * Coordinator Coverage report module.
 *
 * Queries tier distribution, enrollment pipeline, and scope coverage
 * gaps for the Coordinator Coverage report subpage.
 */

$tierLabelMap = [
    'national_coordinator' => 'National',
    'regional_coordinator' => 'Regional',
    'state_coordinator' => 'State',
    'lga_coordinator' => 'LGA',
    'district_coordinator' => 'District',
    'village_coordinator' => 'Village',
];

// 1. Tier distribution — active coordinators by tier
$coordTierDistribution = report_rows($pdo, "
    SELECT role_key, COUNT(*) cnt
    FROM user_role_assignments
    WHERE status = 'active' AND role_key IN ('national_coordinator','regional_coordinator','state_coordinator','lga_coordinator','district_coordinator','village_coordinator')
    GROUP BY role_key
    ORDER BY FIELD(role_key, 'national_coordinator','regional_coordinator','state_coordinator','lga_coordinator','district_coordinator','village_coordinator')
");
$coordTierLabels = [];
$coordTierCounts = [];
foreach ($coordTierDistribution as $row) {
    $tier = (string) ($row['role_key'] ?? '');
    $coordTierLabels[] = $tierLabelMap[$tier] ?? ucfirst(str_replace('_', ' ', $tier));
    $coordTierCounts[] = (int) ($row['cnt'] ?? 0);
}

// 2. Enrollment pipeline — by applicant type and status
$coordEnrollmentPipeline = report_rows($pdo, "
    SELECT applicant_type, status, COUNT(*) cnt
    FROM enrollment_applications
    WHERE applicant_type IN ('coordinator','cooperative')
    GROUP BY applicant_type, status
    ORDER BY applicant_type, status
");
$coordPipelineRows = [];
$coordPipelineTotals = [];
foreach ($coordEnrollmentPipeline as $row) {
    $type = (string) ($row['applicant_type'] ?? '');
    $status = (string) ($row['status'] ?? '');
    $count = (int) ($row['cnt'] ?? 0);
    if (!isset($coordPipelineRows[$type])) {
        $coordPipelineRows[$type] = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'total' => 0];
    }
    $coordPipelineRows[$type][$status] = $count;
    $coordPipelineTotals[$type] = ($coordPipelineTotals[$type] ?? 0) + $count;
}

// 3. Scope coverage matrix — states with/without state_coordinator assignments
$allStates = report_rows($pdo, "SELECT id, state_name FROM nigeria_states ORDER BY state_name");
$coveredStates = report_rows($pdo, "
    SELECT DISTINCT ura.scope_value
    FROM user_role_assignments ura
    WHERE ura.role_key IN ('state_coordinator','regional_coordinator')
      AND ura.scope_type = 'state'
      AND ura.status = 'active'
      AND COALESCE(ura.scope_value, '') <> ''
");
$coveredStateNames = [];
foreach ($coveredStates as $s) {
    $coveredStateNames[] = strtolower(trim((string) ($s['scope_value'] ?? '')));
}
$coveredStateNames = array_unique($coveredStateNames);

$coordCoverageStates = [];
$uncoveredStatesCount = 0;
foreach ($allStates as $state) {
    $stateName = (string) ($state['state_name'] ?? '');
    // Resolve state name aliases (e.g., 'Federal Capital Territory' => ['Abuja','FCT'])
    $stateAliases = function_exists('coord_state_aliases') ? coord_state_aliases($stateName) : [$stateName];
    $isCovered = false;
    foreach ($stateAliases as $alias) {
        if (in_array(strtolower($alias), $coveredStateNames, true)) {
            $isCovered = true;
            break;
        }
    }
    if (!$isCovered) {
        $uncoveredStatesCount++;
    }
    $coordCoverageStates[] = [
        'state_name' => $stateName,
        'covered' => $isCovered,
    ];
}

// 4. Coordinator assignments detail for the table
$coordAssignmentRows = report_rows($pdo, "
    SELECT u.id, u.name, u.email,
           GROUP_CONCAT(DISTINCT ura.role_key SEPARATOR ',') AS tiers,
           GROUP_CONCAT(DISTINCT CONCAT(ura.scope_type, ':', COALESCE(ura.scope_value, '')) SEPARATOR ',') AS scopes
    FROM users u
    INNER JOIN user_role_assignments ura ON ura.user_id = u.id
        AND ura.status = 'active'
        AND ura.role_key IN ('national_coordinator','regional_coordinator','state_coordinator','lga_coordinator','district_coordinator','village_coordinator')
    GROUP BY u.id, u.name, u.email
    ORDER BY u.created_at DESC, u.id DESC
    LIMIT 200
");

// CSV export data
$exportRows = [];
foreach ($coordTierLabels as $i => $label) {
    $exportRows[] = ['section' => 'Tier Distribution', 'metric' => $label, 'value' => (string) $coordTierCounts[$i], 'detail' => 'Active coordinator assignments'];
}
foreach ($coordPipelineRows as $type => $counts) {
    $exportRows[] = [
        'section' => 'Enrollment Pipeline',
        'metric' => $type,
        'value' => (string) ($coordPipelineTotals[$type] ?? 0),
        'detail' => 'Pending: ' . ($counts['pending'] ?? 0) . ' / Approved: ' . ($counts['approved'] ?? 0) . ' / Rejected: ' . ($counts['rejected'] ?? 0),
    ];
}
$exportRows[] = ['section' => 'Scope Coverage', 'metric' => 'Uncovered states', 'value' => (string) $uncoveredStatesCount, 'detail' => 'States without a state_coordinator assignment'];
foreach ($coordAssignmentRows as $r) {
    $exportRows[] = [
        'section' => 'Coordinator Assignments',
        'metric' => (string) ($r['name'] ?? ''),
        'value' => (string) ($r['email'] ?? ''),
        'detail' => 'Tiers: ' . (string) ($r['tiers'] ?? '') . ' / Scopes: ' . (string) ($r['scopes'] ?? ''),
    ];
}
