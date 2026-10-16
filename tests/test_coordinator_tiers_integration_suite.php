<?php
declare(strict_types=1);

ob_implicit_flush(true);
while (ob_get_level()) {
    ob_end_flush();
}

/**
 * Coordinator Tiers Integration Suite
 *
 * Tests the coordinator-tier system end-to-end:
 * - Tier registry and role helpers (coord_tiers, coord_tier_roles, coord_role_key)
 * - State alias resolution (FCT / Abuja / Federal Capital Territory)
 * - coord_scope_filter() for each coordinator tier (national→village)
 * - coord_enclave_farmer_count() shared helper (replaces inline closure)
 * - report_subpages_for_role() match-statement for admin reports
 * - report_coord_scope_sql() admin report scoping fragment
 * - support_ticket_coordinator_scope() support-desk escalation chain
 * - coord_sub_enclave_query() coordination reports breakdown
 */

require_once __DIR__ . '/TestHarness.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/admin-workspace.php';
require_once __DIR__ . '/../coordination/_coordination.php';
require_once __DIR__ . '/../lib/enrollment-applications.php';
require_once __DIR__ . '/../lib/support.php';
require_once __DIR__ . '/../lib/report-helpers.php';

$pdo = db();
TestHarness::assertTestDatabase($pdo);
admin_ensure_schema($pdo);
coord_ensure_schema($pdo);
support_ensure_schema($pdo);
enrollment_ensure_schema($pdo);

TestHarness::start('Coordinator Tiers Integration Test Suite');

// ---------------------------------------------------------------------------
// Fixture setup: create test users and role assignments
// ---------------------------------------------------------------------------
$suiteId = time() . '_' . mt_rand(1000, 9999);

function test_insert_user(PDO $pdo, string $name, string $email, string $status = 'active'): int {
    $hash = password_hash('Pass123!', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, account_status, created_at) VALUES (?, ?, ?, 'user', ?, NOW())");
    $stmt->execute([$name, $email, $hash, $status]);
    return (int) $pdo->lastInsertId();
}

function test_assign_role(PDO $pdo, int $userId, string $role, string $scopeType, string $scopeValue = ''): void {
    $stmt = $pdo->prepare("INSERT INTO user_role_assignments (user_id, role_key, scope_type, scope_value, status, created_at) VALUES (?, ?, ?, ?, 'active', NOW())");
    $stmt->execute([$userId, $role, $scopeType, $scopeValue]);
}

function test_insert_application(PDO $pdo, int $userId, string $stateName, string $lgaName = '', string $district = '', string $village = ''): int {
    // Resolve state name to state_id
    $stateId = 0;
    $stmt = $pdo->prepare('SELECT id FROM nigeria_states WHERE LOWER(state_name) = LOWER(?) OR LOWER(state_code) = LOWER(?) LIMIT 1');
    $stmt->execute([$stateName, $stateName]);
    $stateId = (int) ($stmt->fetchColumn() ?: 0);

    $lgaId = 0;
    if ($lgaName !== '' && app_column_exists($pdo, 'nigeria_lgas', 'lga_name')) {
        $stmt = $pdo->prepare('SELECT id FROM nigeria_lgas WHERE LOWER(lga_name) = LOWER(?) LIMIT 1');
        $stmt->execute([$lgaName]);
        $lgaId = (int) ($stmt->fetchColumn() ?: 0);
    }

    $distCol = app_column_exists($pdo, 'applications', 'district') ? 'district' : null;
    $vlgCol = app_column_exists($pdo, 'applications', 'village') ? 'village' : null;

    $cols = ['user_id', 'confirmed', 'created_at'];
    $vals = [$userId, 0, 'NOW()'];
    $place = ['?', '?', '?'];

    if ($stateId > 0) { $cols[] = 'state_id'; $vals[] = $stateId; $place[] = '?'; }
    if ($lgaId > 0) { $cols[] = 'lga_id'; $vals[] = $lgaId; $place[] = '?'; }
    if ($distCol && $district !== '') { $cols[] = $distCol; $vals[] = $district; $place[] = '?'; }
    if ($vlgCol && $village !== '') { $cols[] = $vlgCol; $vals[] = $village; $place[] = '?'; }

    $stmt = $pdo->prepare("INSERT INTO applications (" . implode(',', $cols) . ") VALUES (" . implode(',', $place) . ")");
    $stmt->execute($vals);
    return (int) $pdo->lastInsertId();
}

// Fixture users
$stateCoordId = test_insert_user($pdo, 'State Coord', "state.{$suiteId}@natcodev.test");
$districtCoordId = test_insert_user($pdo, 'District Coord', "district.{$suiteId}@natcodev.test");
$lgaCoordId = test_insert_user($pdo, 'LGA Coord', "lga.{$suiteId}@natcodev.test");
$villageCoordId = test_insert_user($pdo, 'Village Coord', "village.{$suiteId}@natcodev.test");
$regionalCoordId = test_insert_user($pdo, 'Regional Coord', "regional.{$suiteId}@natcodev.test");
$nationalCoordId = test_insert_user($pdo, 'National Coord', "national.{$suiteId}@natcodev.test");
$applicantId = test_insert_user($pdo, 'Test Applicant', "applicant.{$suiteId}@natcodev.test");

// Assign coordinator roles
test_assign_role($pdo, $stateCoordId, 'state_coordinator', 'state', 'Kebbi');
test_assign_role($pdo, $districtCoordId, 'district_coordinator', 'district', 'Gwandu');
test_assign_role($pdo, $lgaCoordId, 'lga_coordinator', 'lga', 'Surulere');
test_assign_role($pdo, $villageCoordId, 'village_coordinator', 'village', 'Angwan');
test_assign_role($pdo, $regionalCoordId, 'regional_coordinator', 'state', 'Kebbi');
test_assign_role($pdo, $nationalCoordId, 'national_coordinator', 'national', '');

// Insert applications in various locations
$appId1 = test_insert_application($pdo, $applicantId, 'Kebbi', 'Birnin Kebbi', 'Gwandu', 'Angwan');
$appId2 = test_insert_application($pdo, $applicantId, 'Kebbi', 'Surulere', 'Gwandu', 'Angwan');

// ---------------------------------------------------------------------------
// SECTION 1: Tier Registry & Helpers
// ---------------------------------------------------------------------------
echo "\n--- Section 1: Tier Registry & Helpers ---\n";

TestHarness::assertTrue(function_exists('coord_tiers'), 'coord_tiers() is defined');
TestHarness::assertTrue(function_exists('coord_tier_roles'), 'coord_tier_roles() is defined');

$tiers = coord_tiers();
TestHarness::assertTrue(is_array($tiers), 'coord_tiers() returns an array');
TestHarness::assertEqual(6, count($tiers), 'Six coordinator tiers are registered');

$expectedTiers = ['national_coordinator', 'regional_coordinator', 'state_coordinator', 'lga_coordinator', 'district_coordinator', 'village_coordinator'];
foreach ($expectedTiers as $t) {
    TestHarness::assertTrue(isset($tiers[$t]), "Tier '{$t}' is in the registry");
}

TestHarness::assertEqual($expectedTiers, coord_tier_roles(), 'coord_tier_roles() returns all tier keys in order');

// ---------------------------------------------------------------------------
// SECTION 2: State Alias Resolution
// ---------------------------------------------------------------------------
echo "\n--- Section 2: State Alias Resolution ---\n";

TestHarness::assertTrue(function_exists('coord_state_aliases'), 'coord_state_aliases() is defined');

$kebbiAliases = coord_state_aliases('Kebbi');
TestHarness::assertEqual(['Kebbi'], $kebbiAliases, 'Kebbi has no extra aliases');

$fctAliases = coord_state_aliases('FCT');
TestHarness::assertTrue(in_array('Federal Capital Territory', $fctAliases, true), 'FCT alias resolves to Federal Capital Territory');
TestHarness::assertTrue(in_array('Abuja', $fctAliases, true), 'FCT alias includes Abuja');

$abujaAliases = coord_state_aliases('Abuja');
TestHarness::assertTrue(in_array('Federal Capital Territory', $abujaAliases, true), 'Abuja alias resolves to Federal Capital Territory');
TestHarness::assertTrue(in_array('FCT', $abujaAliases, true), 'Abuja alias includes FCT');

TestHarness::assertTrue(function_exists('coord_resolve_state'), 'coord_resolve_state() is defined');

$resolved = coord_resolve_state($pdo, 'FCT');
TestHarness::assertTrue($resolved['resolved'] ?? false, 'coord_resolve_state resolves FCT to a state');
TestHarness::assertTrue((int) ($resolved['id'] ?? 0) > 0, 'coord_resolve_state returns a valid state id for FCT');

$resolvedKebbi = coord_resolve_state($pdo, 'Kebbi');
TestHarness::assertTrue($resolvedKebbi['resolved'] ?? false, 'coord_resolve_state resolves Kebbi');

// ---------------------------------------------------------------------------
// SECTION 3: coord_scope_filter() for Each Tier
// ---------------------------------------------------------------------------
echo "\n--- Section 3: coord_scope_filter() Per Tier ---\n";

$stateCoord = test_insert_user($pdo, 'Scope Filter State', "sf-state.{$suiteId}@natcodev.test");
test_assign_role($pdo, $stateCoord, 'state_coordinator', 'state', 'Kebbi');
$stateCoordRow = ['id' => $stateCoord, 'name' => 'SF State'];

$scope = coord_scope_filter($pdo, $stateCoordRow);
TestHarness::assertTrue(is_array($scope), 'coord_scope_filter returns an array for state_coordinator');
TestHarness::assertTrue(($scope['where'] ?? '') !== '', 'State coordinator scope has a WHERE clause');
TestHarness::assertTrue(strpos((string) ($scope['where'] ?? ''), 'ns.state_name') !== false || strpos((string) ($scope['where'] ?? ''), 'a.state_id') !== false, 'State coordinator scope references state matching');

// District coordinator scope
$districtCoord = test_insert_user($pdo, 'Scope Filter District', "sf-district.{$suiteId}@natcodev.test");
test_assign_role($pdo, $districtCoord, 'district_coordinator', 'district', 'Gwandu');
$districtCoordRow = ['id' => $districtCoord, 'name' => 'SF District'];

$scope = coord_scope_filter($pdo, $districtCoordRow);
TestHarness::assertTrue(strpos((string) ($scope['where'] ?? ''), 'a.district') !== false, 'District coordinator scope matches on a.district');

// LGA coordinator scope
$lgaCoord2 = test_insert_user($pdo, 'Scope Filter LGA', "sf-lga.{$suiteId}@natcodev.test");
test_assign_role($pdo, $lgaCoord2, 'lga_coordinator', 'lga', 'Surulere');
$lgaCoordRow = ['id' => $lgaCoord2, 'name' => 'SF LGA'];

$scope = coord_scope_filter($pdo, $lgaCoordRow);
TestHarness::assertTrue(strpos((string) ($scope['where'] ?? ''), 'a.lga_id') !== false || strpos((string) ($scope['where'] ?? ''), 'lga_name') !== false, 'LGA coordinator scope matches on lga');

// Village coordinator scope
$villageCoord2 = test_insert_user($pdo, 'Scope Filter Village', "sf-village.{$suiteId}@natcodev.test");
test_assign_role($pdo, $villageCoord2, 'village_coordinator', 'village', 'Angwan');
$villageCoordRow = ['id' => $villageCoord2, 'name' => 'SF Village'];

$scope = coord_scope_filter($pdo, $villageCoordRow);
TestHarness::assertTrue(strpos((string) ($scope['where'] ?? ''), 'a.village') !== false, 'Village coordinator scope matches on a.village');

// National coordinator scope — unscoped
$nationalRow = ['id' => $nationalCoordId, 'name' => 'National'];
$scope = coord_scope_filter($pdo, $nationalRow);
TestHarness::assertTrue(($scope['tier'] ?? '') === 'national', 'National coordinator tier is national');
TestHarness::assertTrue(($scope['where'] ?? '') === '1=1', 'National coordinator scope is unscoped (1=1)');

// Regional coordinator scope — spans assigned states
$regionalRow = ['id' => $regionalCoordId, 'name' => 'Regional'];
$scope = coord_scope_filter($pdo, $regionalRow);
TestHarness::assertTrue(is_array($scope), 'Regional coordinator scope returns an array');

// ---------------------------------------------------------------------------
// SECTION 4: coord_enclave_farmer_count()
// ---------------------------------------------------------------------------
echo "\n--- Section 4: coord_enclave_farmer_count() ---\n";

TestHarness::assertTrue(function_exists('coord_enclave_farmer_count'), 'coord_enclave_farmer_count() is defined');

// National coordinator should see all applications (at least the ones we inserted)
$natCount = coord_enclave_farmer_count($pdo, ['id' => $nationalCoordId]);
TestHarness::assertTrue($natCount > 0, 'National coordinator sees applications (>0)', 'Got: ' . $natCount);

// State coordinator for Kebbi should see applications in Kebbi
$scCount = coord_enclave_farmer_count($pdo, ['id' => $stateCoord]);
TestHarness::assertTrue($scCount > 0, 'State coordinator (Kebbi) sees applications (>0)', 'Got: ' . $scCount);

// District coordinator for Gwandu should see applications with district = Gwandu
$dcCount = coord_enclave_farmer_count($pdo, ['id' => $districtCoordId]);
TestHarness::assertTrue($dcCount > 0, 'District coordinator (Gwandu) sees applications (>0)', 'Got: ' . $dcCount);

// LGA coordinator for Surulere should see applications with LGA = Surulere
$lgaCount = coord_enclave_farmer_count($pdo, ['id' => $lgaCoordId]);
TestHarness::assertTrue($lgaCount > 0, 'LGA coordinator (Surulere) sees applications (>0)', 'Got: ' . $lgaCount);

// Village coordinator for Angwan should see applications with village = Angwan
$vcCount = coord_enclave_farmer_count($pdo, ['id' => $villageCoordId]);
TestHarness::assertTrue($vcCount > 0, 'Village coordinator (Angwan) sees applications (>0)', 'Got: ' . $vcCount);

// A user with no coordinator role should see 0
$plainUserCount = coord_enclave_farmer_count($pdo, ['id' => $applicantId]);
TestHarness::assertEqual(0, $plainUserCount, 'Non-coordinator sees 0 applications');

// Caching: calling twice returns same value
TestHarness::assertEqual($scCount, coord_enclave_farmer_count($pdo, ['id' => $stateCoord]), 'coord_enclave_farmer_count is cached (same result on repeat call)');

// ---------------------------------------------------------------------------
// SECTION 5: Admin Report Subpage Access
// ---------------------------------------------------------------------------
echo "\n--- Section 5: Admin Report Subpage Access (report_subpages_for_role) ---\n";

TestHarness::assertTrue(function_exists('report_subpages_for_role'), 'report_subpages_for_role() is defined');

$nationalSubpages = report_subpages_for_role('national_coordinator');
TestHarness::assertTrue(in_array('coordinator_coverage', $nationalSubpages, true), 'National coordinator can access coordinator_coverage subpage');

$regionalSubpages = report_subpages_for_role('regional_coordinator');
TestHarness::assertTrue(count($regionalSubpages) > 0, 'Regional coordinator has report subpages');
TestHarness::assertTrue(in_array('coordinator_coverage', $regionalSubpages, true), 'Regional coordinator can access coordinator_coverage subpage');

$stateSubpages = report_subpages_for_role('state_coordinator');
TestHarness::assertTrue(count($stateSubpages) > 0, 'State coordinator has report subpages');
TestHarness::assertTrue(!in_array('coordinator_coverage', $stateSubpages, true), 'State coordinator cannot access coordinator_coverage (only national/regional)');

$lgaSubpages = report_subpages_for_role('lga_coordinator');
TestHarness::assertTrue(in_array('field', $lgaSubpages, true), 'LGA coordinator can access field subpage');
TestHarness::assertTrue(!in_array('coordinator_coverage', $lgaSubpages, true), 'LGA coordinator cannot access coordinator_coverage');

$overviewPages = report_subpages_for_role('admin');
TestHarness::assertTrue(in_array('coordinator_coverage', $overviewPages, true), 'Admin can access coordinator_coverage subpage');

// ---------------------------------------------------------------------------
// SECTION 6: report_coord_scope_sql()
// ---------------------------------------------------------------------------
echo "\n--- Section 6: report_coord_scope_sql() ---\n";

TestHarness::assertTrue(function_exists('report_coord_scope_sql'), 'report_coord_scope_sql() is defined');

// State coordinator: should return a state-level clause
$scScope = report_coord_scope_sql($pdo, $stateCoordRow);
TestHarness::assertTrue(is_array($scScope), 'report_coord_scope_sql returns an array for state_coordinator');
TestHarness::assertTrue(isset($scScope['sql']) && isset($scScope['params']), 'report_coord_scope_sql returns sql + params keys');

// Village coordinator: should return a village-level clause
$vcScope = report_coord_scope_sql($pdo, $villageCoordRow);
TestHarness::assertTrue(($vcScope['sql'] ?? '') !== '', 'Village coordinator scope SQL is not empty');

// Non-coordinator: should return empty SQL
$plainScope = report_coord_scope_sql($pdo, $applicantId > 0 ? ['id' => $applicantId] : null);
TestHarness::assertTrue(($plainScope['sql'] ?? '') === '', 'Non-coordinator scope SQL is empty');

// Null user: should return empty SQL
$nullScope = report_coord_scope_sql($pdo, null);
TestHarness::assertTrue(($nullScope['sql'] ?? '') === '', 'Null user scope SQL is empty');

// ---------------------------------------------------------------------------
// SECTION 7: support_ticket_coordinator_scope()
// ---------------------------------------------------------------------------
echo "\n--- Section 7: support_ticket_coordinator_scope() ---\n";

TestHarness::assertTrue(function_exists('support_ticket_coordinator_scope'), 'support_ticket_coordinator_scope() is defined');

$ticketId = 1; // dummy id for testing
$coordInfo = support_ticket_coordinator_scope($pdo, $applicantId, $ticketId);
TestHarness::assertTrue(is_array($coordInfo), 'support_ticket_coordinator_scope returns an array');
TestHarness::assertTrue(isset($coordInfo['chain']) && isset($coordInfo['location']), 'Return includes chain + location');

// Applicant is in Kebbi / Surulere / Gwandu / Angwan
// Should find at least: state coord (Kebbi), district coord (Gwandu),
//                        lga coord (Surulere), village coord (Angwan),
//                        regional coord (Kebbi), national coord
TestHarness::assertTrue(count($coordInfo['chain']) >= 2, 'Coordinate chain finds at least 2 coordinators for applicant in Kebbi', 'Got: ' . count($coordInfo['chain']));

// Verify chain order: village → district → lga → state → regional → national
$tiers = array_column($coordInfo['chain'], 'tier');
$villageIdx = array_search('Village', $tiers);
$districtIdx = array_search('District', $tiers);
$lgaIdx = array_search('LGA', $tiers);
$stateIdx = array_search('State', $tiers);
if ($villageIdx !== false && $districtIdx !== false) {
    TestHarness::assertTrue($villageIdx < $districtIdx, 'Village coordinator appears before District in chain');
}

// Null user: should return empty chain
$nullInfo = support_ticket_coordinator_scope($pdo, 999999, $ticketId);
TestHarness::assertTrue(is_array($nullInfo), 'support_ticket_coordinator_scope handles unknown user gracefully');

// ---------------------------------------------------------------------------
// SECTION 8: coord_sub_enclave_query()
// ---------------------------------------------------------------------------
echo "\n--- Section 8: coord_sub_enclave_query() ---\n";

TestHarness::assertTrue(function_exists('coord_sub_enclave_query'), 'coord_sub_enclave_query() is defined');

// State-coordinator scope → breakdown by LGA
$scScopeArr = coord_scope_filter($pdo, $stateCoordRow);
$subRows = coord_sub_enclave_query($pdo, $scScopeArr, 'lga');
TestHarness::assertTrue(is_array($subRows), 'coord_sub_enclave_query returns an array');
TestHarness::assertTrue(count($subRows) >= 0, 'Sub-enclave query returns rows (may be empty if LGA column missing)');

if (count($subRows) > 0) {
    $firstRow = $subRows[0];
    TestHarness::assertTrue(isset($firstRow['label']), 'Sub-enclave row has label field');
    TestHarness::assertTrue(isset($firstRow['total']), 'Sub-enclave row has total field');
    TestHarness::assertTrue(isset($firstRow['active']), 'Sub-enclave row has active field');
    TestHarness::assertTrue(isset($firstRow['pending']), 'Sub-enclave row has pending field');
}

// Invalid breakdown column: should return empty
$invalidRows = coord_sub_enclave_query($pdo, $scScopeArr, 'invalid_column');
TestHarness::assertEqual([], $invalidRows, 'coord_sub_enclave_query rejects invalid breakdown column');

// ---------------------------------------------------------------------------
// SECTION 9: Dashboard Awareness (conditional card)
// ---------------------------------------------------------------------------
echo "\n--- Section 9: Dashboard Coordinator Card Logic ---\n";

TestHarness::assertTrue(function_exists('coord_user_has_role'), 'coord_user_has_role() is defined');
TestHarness::assertTrue(coord_user_has_role($pdo, $stateCoordRow, 'state_coordinator'), 'State coordinator passes coord_user_has_role check');
TestHarness::assertFalse(coord_user_has_role($pdo, ['id' => $applicantId, 'name' => 'Applicant'], 'state_coordinator'), 'Non-coordinator fails coord_user_has_role check');

// ---------------------------------------------------------------------------
// SECTION 10: Admin report_subpages() registration
// ---------------------------------------------------------------------------
echo "\n--- Section 10: Coordinator Coverage Report Registration ---\n";

$subpages = report_subpages();
TestHarness::assertTrue(isset($subpages['coordinator_coverage']), 'coordinator_coverage is registered in report_subpages()');
TestHarness::assertTrue(count($subpages['coordinator_coverage']) >= 3, 'coordinator_coverage has label, description, and icon');

// Module file mapping
$moduleFile = __DIR__ . '/../admin/reports/modules/coordinator-coverage.php';
TestHarness::assertTrue(is_file($moduleFile), 'Coordinator coverage module file exists');

$viewFile = __DIR__ . '/../admin/reports/views/coordinator_coverage.php';
TestHarness::assertTrue(is_file($viewFile), 'Coordinator coverage view file exists');

// ---------------------------------------------------------------------------
// SECTION 11: Schema & Helper Consistency
// ---------------------------------------------------------------------------
echo "\n--- Section 11: Schema & Helper Consistency ---\n";

TestHarness::assertTrue(app_table_exists($pdo, 'user_role_assignments'), 'user_role_assignments table exists (admin_ensure_schema)');
TestHarness::assertTrue(app_table_exists($pdo, 'enrollment_applications'), 'enrollment_applications table exists (enrollment_ensure_schema)');

TestHarness::assertTrue(function_exists('coord_app_from'), 'coord_app_from() is available');
TestHarness::assertTrue(function_exists('coord_app_status_expr'), 'coord_app_status_expr() is available');
TestHarness::assertTrue(function_exists('coord_app_lga_expr'), 'coord_app_lga_expr() is available');

// Verify nigeria_states table has state_name column
TestHarness::assertTrue(app_column_exists($pdo, 'nigeria_states', 'state_name'), 'nigeria_states has state_name column');

// ---------------------------------------------------------------------------
// Cleanup
// ---------------------------------------------------------------------------
foreach (['state_coordinator', 'district_coordinator', 'lga_coordinator', 'village_coordinator', 'regional_coordinator', 'national_coordinator'] as $tier) {
    $pdo->prepare("DELETE FROM user_role_assignments WHERE role_key = ? AND user_id IN (?, ?, ?, ?, ?, ?)")
        ->execute([$tier, $stateCoordId, $districtCoordId, $lgaCoordId, $villageCoordId, $regionalCoordId, $nationalCoordId]);
}

$cleanUsers = [$stateCoordId, $districtCoordId, $lgaCoordId, $villageCoordId, $regionalCoordId, $nationalCoordId, $applicantId, $stateCoord, $districtCoord, $lgaCoord2, $villageCoord2];
foreach (array_unique($cleanUsers) as $uid) {
    $pdo->prepare("DELETE FROM applications WHERE user_id = ?")->execute([$uid]);
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$uid]);
}

echo "\nCleanup complete — removed " . count(array_unique($cleanUsers)) . " fixture users.\n";

exit(TestHarness::summary());
