<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/admin-layout.php';
require_once __DIR__ . '/../lib/monnify.php';
require_once __DIR__ . '/../lib/support.php';
require_once __DIR__ . '/../lib/user-workspaces.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function coord_ensure_schema(PDO $pdo): void
{
    // Enclave columns captured at registration and farm
    // creation, so below-state coordinators can scope to
    // their district or village.
    app_add_column_if_missing($pdo, 'applications', 'district', 'VARCHAR(160) NULL');
    app_add_column_if_missing($pdo, 'applications', 'village', 'VARCHAR(160) NULL');
    app_add_column_if_missing($pdo, 'grower_farms', 'district', 'VARCHAR(160) NULL');
    app_add_column_if_missing($pdo, 'grower_farms', 'village', 'VARCHAR(160) NULL');
}

function coord_pdo(): PDO
{
    static $pdo = null;
    static $booted = false;
    if (!$pdo instanceof PDO) {
        $pdo = db();
    }
    if (!$booted) {
        app_ensure_core_schema($pdo);
        admin_ensure_schema($pdo);
        wallet_ensure_schema($pdo);
        support_ensure_schema($pdo);
        enrollment_ensure_schema($pdo);
        cooperative_ensure_schema($pdo);
        coord_ensure_schema($pdo);
        $booted = true;
    }
    return $pdo;
}

function coord_role_key(array $user): string
{
    return app_role_normalize((string) (($user['platform_role'] ?? '') ?: ($user['role'] ?? 'state_coordinator')));
}

/**
 * Coordinator tiers, ordered from widest to narrowest
 * enclave. Regional coordinators hold one state-scope
 * assignment per state in their region; LGA, district,
 * and village coordinators hold a single enclave each.
 *
 * @return array<string, array{label: string, scope: string, home: string}>
 */
function coord_tiers(): array
{
    return [
        'national_coordinator' => ['label' => 'National Coordinator', 'scope' => 'national', 'home' => 'national-coordinator.php'],
        'regional_coordinator' => ['label' => 'Regional Coordinator', 'scope' => 'state', 'home' => 'state-coordinator.php'],
        'state_coordinator' => ['label' => 'State Coordinator', 'scope' => 'state', 'home' => 'state-coordinator.php'],
        'lga_coordinator' => ['label' => 'LGA Coordinator', 'scope' => 'lga', 'home' => 'enclave-coordinator.php'],
        'district_coordinator' => ['label' => 'District Coordinator', 'scope' => 'district', 'home' => 'enclave-coordinator.php'],
        'village_coordinator' => ['label' => 'Village Coordinator', 'scope' => 'village', 'home' => 'enclave-coordinator.php'],
    ];
}

function coord_tier_roles(): array
{
    return array_keys(coord_tiers());
}

function coord_role_label(string $role): string
{
    return coord_tiers()[$role]['label'] ?? 'Coordinator';
}

function coord_home(string $role): string
{
    return coord_tiers()[$role]['home'] ?? 'state-coordinator.php';
}

function coord_scope_type(string $role): string
{
    return coord_tiers()[$role]['scope'] ?? 'state';
}

function coord_user_has_role(PDO $pdo, array $user, string $role): bool
{
    return app_user_has_any_role($pdo, $user, ['admin', 'super_admin', $role]);
}

function coord_primary_workspace_role(PDO $pdo, array $user): string
{
    $roles = app_user_role_keys($pdo, $user);
    foreach (coord_tier_roles() as $tier) {
        if (in_array($tier, $roles, true)) {
            return $tier;
        }
    }
    if (in_array('admin', $roles, true) || in_array('super_admin', $roles, true)) {
        return 'national_coordinator';
    }
    return coord_role_key($user);
}

function coord_current_user(PDO $pdo): ?array
{
    $user = current_user($pdo);
    if (!$user) {
        return null;
    }
    return app_user_has_any_role($pdo, $user, array_merge(['admin', 'super_admin'], coord_tier_roles())) ? $user : null;
}

function coord_require(PDO $pdo, string $workspaceRole = ''): array
{
    $user = coord_current_user($pdo);
    if (!$user) {
        redirect_to('login.php');
    }
    $role = coord_primary_workspace_role($pdo, $user);
    if ($workspaceRole !== '' && !coord_user_has_role($pdo, $user, $workspaceRole) && !coord_user_has_role($pdo, $user, 'national_coordinator')) {
        redirect_to(coord_home($role));
    }
    // National and regional coordinators read the national
    // dashboard feature; every narrower tier reads the
    // state dashboard feature (their enclave is a slice of it).
    $wideTiers = ['national_coordinator', 'regional_coordinator'];
    $effectiveRole = in_array($workspaceRole, $wideTiers, true) || in_array($role, $wideTiers, true) ? 'national_coordinator' : 'state_coordinator';
    $coordFeature = $effectiveRole === 'national_coordinator' ? 'national_dashboard' : 'state_dashboard';
    if (!admin_feature_is_allowed($pdo, $coordFeature)) {
        app_render_error_page(403, 'This coordination module is disabled for your role.');
    }
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $_SESSION['coord_workspace_role'] = app_role_normalize($workspaceRole !== '' ? $workspaceRole : $role);
    return $user;
}

function coord_login_home(array $user): string
{
    return coord_home(coord_primary_workspace_role(coord_pdo(), $user));
}

function coord_scalar(PDO $pdo, string $sql, array $params = []): int
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int) ($stmt->fetchColumn() ?: 0);
    } catch (Throwable $e) {
        return 0;
    }
}

function coord_rows(PDO $pdo, string $sql, array $params = []): array
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function coord_current_workspace_role(PDO $pdo, array $user): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $sessionRole = app_role_normalize((string) ($_SESSION['coord_workspace_role'] ?? ''));
    if ($sessionRole !== '' && app_user_has_role($pdo, $user, $sessionRole)) {
        return $sessionRole;
    }
    return coord_primary_workspace_role($pdo, $user);
}

function coord_assigned_states(PDO $pdo, array $user): array
{
    $states = [];
    $userId = (int) ($user['id'] ?? 0);
    if ($userId > 0 && app_table_exists($pdo, 'user_role_assignments')) {
        $stmt = $pdo->prepare("SELECT scope_value FROM user_role_assignments WHERE user_id = ? AND role_key = 'state_coordinator' AND scope_type = 'state' AND status = 'active' AND COALESCE(scope_value, '') <> '' ORDER BY scope_value ASC");
        $stmt->execute([$userId]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $state) {
            $state = trim((string) $state);
            if ($state !== '') {
                $states[] = $state;
            }
        }
    }
    if (!$states && $userId > 0 && app_table_exists($pdo, 'staff_profiles')) {
        $spSoft = app_column_exists($pdo, 'staff_profiles', 'deleted_at') ? ' AND deleted_at IS NULL' : '';
        $stmt = $pdo->prepare("SELECT state FROM staff_profiles WHERE user_id = ? AND COALESCE(state, '') <> ''{$spSoft} LIMIT 1");
        $stmt->execute([$userId]);
        $state = trim((string) ($stmt->fetchColumn() ?: ''));
        if ($state !== '') {
            $states[] = $state;
        }
    }
    if (!$states) {
        $location = trim((string) ($user['location'] ?? ''));
        if ($location !== '') {
            $states[] = $location;
        }
    }
    return array_values(array_unique(array_filter($states)));
}

/**
 * Every enclave value assigned to a coordinator for a
 * scope type (state, lga, district, village). Mirrors
 * coord_assigned_states() but generic: RBAC scoped
 * assignments win, then the matching staff_profiles
 * column, then the free-text user location.
 *
 * @return array<int, string>
 */
function coord_assigned_scopes(PDO $pdo, array $user, string $scopeType): array
{
    $scopes = [];
    $userId = (int) ($user['id'] ?? 0);
    if ($userId > 0 && app_table_exists($pdo, 'user_role_assignments')) {
        $stmt = $pdo->prepare("SELECT scope_value FROM user_role_assignments WHERE user_id = ? AND COALESCE(scope_type, '') = ? AND status = 'active' AND COALESCE(scope_value, '') <> '' ORDER BY scope_value ASC");
        $stmt->execute([$userId, $scopeType]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $scope) {
            $scope = trim((string) $scope);
            if ($scope !== '') {
                $scopes[] = $scope;
            }
        }
    }
    if (!$scopes && $userId > 0 && app_table_exists($pdo, 'staff_profiles') && in_array($scopeType, ['state', 'lga', 'district', 'village'], true)) {
        $spSoft = app_column_exists($pdo, 'staff_profiles', 'deleted_at') ? ' AND deleted_at IS NULL' : '';
        $stmt = $pdo->prepare("SELECT {$scopeType} FROM staff_profiles WHERE user_id = ? AND COALESCE({$scopeType}, '') <> ''{$spSoft} LIMIT 1");
        $stmt->execute([$userId]);
        $scope = trim((string) ($stmt->fetchColumn() ?: ''));
        if ($scope !== '') {
            $scopes[] = $scope;
        }
    }
    if (!$scopes && in_array($scopeType, ['state', 'district', 'village'], true)) {
        $location = trim((string) ($user['location'] ?? ''));
        if ($location !== '') {
            $scopes[] = $location;
        }
    }
    return array_values(array_unique(array_filter($scopes)));
}

function coord_state_aliases(string $state): array
{
    $state = trim($state);
    $aliases = [$state];
    $key = strtolower($state);
    if (in_array($key, ['fct', 'fc', 'abuja', 'federal capital territory', 'federal capital territory, abuja'], true)) {
        $aliases = array_merge($aliases, ['Federal Capital Territory', 'FCT', 'FC', 'Abuja']);
    }
    return array_values(array_unique(array_filter(array_map('trim', $aliases))));
}

function coord_resolve_state(PDO $pdo, string $state): array
{
    $state = trim($state);
    $aliases = coord_state_aliases($state);
    foreach ($aliases as $alias) {
        $stmt = $pdo->prepare('SELECT id, state_name, state_code FROM nigeria_states WHERE LOWER(state_name) = LOWER(?) OR LOWER(state_code) = LOWER(?) LIMIT 1');
        $stmt->execute([$alias, $alias]);
        $row = $stmt->fetch();
        if ($row) {
            return [
                'assigned_label' => $state,
                'id' => (int) $row['id'],
                'state_name' => (string) $row['state_name'],
                'state_code' => (string) ($row['state_code'] ?? ''),
                'aliases' => $aliases,
                'resolved' => true,
                'alias_used' => strcasecmp($state, (string) $row['state_name']) !== 0,
            ];
        }
    }
    return [
        'assigned_label' => $state,
        'id' => 0,
        'state_name' => $state,
        'state_code' => '',
        'aliases' => $aliases,
        'resolved' => false,
        'alias_used' => false,
    ];
}

function coord_state_application_count(PDO $pdo, string $state): int
{
    $resolved = coord_resolve_state($pdo, $state);
    if ((int) $resolved['id'] <= 0) {
        return 0;
    }
    return coord_scalar($pdo, 'SELECT COUNT(*) FROM applications WHERE state_id = ?', [(int) $resolved['id']]);
}

function coord_state_scope_options(PDO $pdo, array $user): array
{
    $options = [];
    foreach (coord_assigned_states($pdo, $user) as $state) {
        $resolved = coord_resolve_state($pdo, (string) $state);
        $resolved['mapped_applications'] = (int) $resolved['id'] > 0
            ? coord_scalar($pdo, 'SELECT COUNT(*) FROM applications WHERE state_id = ?', [(int) $resolved['id']])
            : 0;
        $options[] = $resolved;
    }
    return $options;
}
function coord_selected_state(PDO $pdo, array $user): string
{
    $states = coord_assigned_states($pdo, $user);
    if (!$states) {
        return '';
    }
    $requested = trim((string) ($_GET['state'] ?? $_SESSION['coord_selected_state'] ?? ''));
    if ($requested !== '') {
        foreach ($states as $state) {
            $resolved = coord_resolve_state($pdo, (string) $state);
            $matches = array_merge([(string) $state, (string) $resolved['state_name'], (string) $resolved['state_code']], (array) $resolved['aliases']);
            foreach ($matches as $candidate) {
                if ($candidate !== '' && strcasecmp($requested, (string) $candidate) === 0) {
                    $_SESSION['coord_selected_state'] = $state;
                    return $state;
                }
            }
        }
    }
    foreach ($states as $state) {
        if (coord_state_application_count($pdo, (string) $state) > 0) {
            $_SESSION['coord_selected_state'] = $state;
            return $state;
        }
    }
    $_SESSION['coord_selected_state'] = $states[0];
    return $states[0];
}

function coord_app_from(): string
{
    return 'applications a LEFT JOIN nigeria_states ns ON ns.id = a.state_id LEFT JOIN nigeria_lgas nl ON nl.id = a.lga_id';
}

function coord_app_state_expr(): string
{
    return "COALESCE(NULLIF(ns.state_name, ''), 'Unmapped / Legacy Location')";
}

function coord_app_lga_expr(): string
{
    return "COALESCE(NULLIF(nl.lga_name, ''), 'Unspecified LGA')";
}

function coord_app_status_expr(): string
{
    return "COALESCE(NULLIF(a.review_status, ''), CASE WHEN a.confirmed = 1 THEN 'active' ELSE 'pending' END)";
}
function coord_state_filter(PDO $pdo, array $user): array
{
    if (coord_current_workspace_role($pdo, $user) !== 'state_coordinator') {
        return ['where' => '1=1', 'params' => [], 'label' => 'National', 'states' => [], 'missing' => false];
    }
    $states = coord_assigned_states($pdo, $user);
    $state = coord_selected_state($pdo, $user);
    if ($state === '') {
        return ['where' => '1=0', 'params' => [], 'label' => 'Unassigned State', 'states' => [], 'missing' => true];
    }
    $resolved = coord_resolve_state($pdo, $state);
    if ((int) $resolved['id'] > 0) {
        return [
            'where' => 'a.state_id = ?',
            'params' => [(int) $resolved['id']],
            'label' => (string) $resolved['state_name'],
            'states' => $states,
            'missing' => false,
            'resolved' => $resolved,
        ];
    }
    return [
        'where' => '(' . coord_app_state_expr() . ' = ? OR ' . coord_app_state_expr() . ' LIKE ? OR a.location LIKE ?)',
        'params' => [$state, '%' . $state . '%', '%' . $state . '%'],
        'label' => $state,
        'states' => $states,
        'missing' => false,
        'resolved' => $resolved,
    ];
}

function coord_state_query(string $href, string $state): string
{
    return $href . (str_contains($href, '?') ? '&' : '?') . 'state=' . urlencode($state);
}

/**
 * Sub-enclave breakdown for a coordinator's reports page.
 * Given the scope filter from coord_scope_filter(), breaks
 * scoped applications down by the next enclave tier (LGA →
 * district, district → village, state → LGA). National and
 * regional coordinators get a state breakdown.
 *
 * @return array<int, array{label: string, total: int, active: int, pending: int}>
 */
function coord_sub_enclave_query(PDO $pdo, array $scope, string $breakdownColumn): array
{
    if (!in_array($breakdownColumn, ['lga', 'district', 'village'], true)) {
        return [];
    }
    if (!app_column_exists($pdo, 'applications', $breakdownColumn)) {
        return [];
    }

    $where = (string) ($scope['where'] ?? '1=1');
    $params = array_values($scope['params'] ?? []);
    $statusExpr = coord_app_status_expr();
    $appFrom = coord_app_from();

    $sql = "SELECT a.{$breakdownColumn} AS label, COUNT(*) AS total, COALESCE(SUM(CASE WHEN {$statusExpr} IN ('active','verified','approved','confirmed') THEN 1 ELSE 0 END), 0) AS active, COALESCE(SUM(CASE WHEN {$statusExpr} IN ('pending','submitted','under_review','new') THEN 1 ELSE 0 END), 0) AS pending FROM {$appFrom} WHERE {$where} AND COALESCE(a.{$breakdownColumn}, '') <> '' GROUP BY a.{$breakdownColumn} ORDER BY total DESC";

    return coord_rows($pdo, $sql, $params);
}

/**
 * Count farmer registrations covered by a coordinator's assigned
 * enclave scopes. Accepts a coordinator user row (e.g. from the
 * super-admin users table) and builds scope clauses by querying
 * their active role assignments — the same matching logic that
 * coord_scope_filter() applies inside the coordination workspace.
 *
 * State tiers resolve each assigned state name to its nigeria_states
 * id; LGA matches resolved lga ids or the LGA name expression;
 * district and village match case-insensitively on the columns.
 *
 * National coordinators cover all registrations; regional
 * coordinators span every assigned state.
 *
 * Results are cached per-user-id for the request lifetime.
 */
function coord_enclave_farmer_count(PDO $pdo, array $user): int
{
    static $cache = [];
    $key = (int) ($user['id'] ?? 0);
    if ($key <= 0) {
        return 0;
    }
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $tierRoles = coord_tier_roles();
    if (!$tierRoles || !app_table_exists($pdo, 'user_role_assignments')) {
        $cache[$key] = 0;
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($tierRoles), '?'));
    $assignStmt = $pdo->prepare(
        "SELECT role_key, scope_type, COALESCE(scope_value, '') AS scope_value
         FROM user_role_assignments
         WHERE user_id = ? AND status = 'active' AND role_key IN ({$placeholders})"
    );
    $assignStmt->execute(array_merge([$key], $tierRoles));
    $assignments = $assignStmt->fetchAll();

    if (!$assignments) {
        $cache[$key] = 0;
        return 0;
    }

    $clauses = [];
    $params = [];
    $isNational = false;

    foreach ($assignments as $a) {
        $roleKey = (string) ($a['role_key'] ?? '');
        $scopeType = (string) ($a['scope_type'] ?? '');
        $scopeValue = trim((string) ($a['scope_value'] ?? ''));

        if ($scopeType === 'national' || $roleKey === 'national_coordinator') {
            $isNational = true;
            continue;
        }

        if ($scopeValue === '') {
            continue;
        }

        if ($scopeType === 'state') {
            $resolved = coord_resolve_state($pdo, $scopeValue);
            if ((int) ($resolved['id'] ?? 0) > 0) {
                $clauses[] = 'a.state_id = ?';
                $params[] = (int) $resolved['id'];
            }
        } elseif ($scopeType === 'lga') {
            if (app_column_exists($pdo, 'nigeria_lgas', 'lga_name')) {
                $lgaStmt = $pdo->prepare('SELECT id FROM nigeria_lgas WHERE LOWER(lga_name) = LOWER(?) LIMIT 1');
                $lgaStmt->execute([$scopeValue]);
                $lgaId = (int) ($lgaStmt->fetchColumn() ?: 0);
                if ($lgaId > 0) {
                    $clauses[] = 'a.lga_id = ?';
                    $params[] = $lgaId;
                }
            }
            $lgaExpr = coord_app_lga_expr();
            $clauses[] = "LOWER({$lgaExpr}) = LOWER(?)";
            $params[] = $scopeValue;
        } elseif ($scopeType === 'district' && app_column_exists($pdo, 'applications', 'district')) {
            $clauses[] = 'LOWER(a.district) = LOWER(?)';
            $params[] = $scopeValue;
        } elseif ($scopeType === 'village' && app_column_exists($pdo, 'applications', 'village')) {
            $clauses[] = 'LOWER(a.village) = LOWER(?)';
            $params[] = $scopeValue;
        }
    }

    if ($isNational) {
        $count = coord_scalar($pdo, "SELECT COUNT(*) FROM " . coord_app_from());
    } elseif ($clauses === []) {
        $count = 0;
    } else {
        $sql = "SELECT COUNT(*) FROM " . coord_app_from() . " WHERE " . implode(' OR ', $clauses);
        $count = coord_scalar($pdo, $sql, $params);
    }

    $cache[$key] = (int) $count;
    return $cache[$key];
}

/**
 * The data scope for the current coordinator, whatever
 * their tier. Returns the same contract as
 * coord_state_filter(): a where clause (with ?
 * placeholders) scoped onto the applications join, the
 * bound parameters, a human label, and the assigned
 * enclave list. National is unscoped; regional spans
 * every assigned state; LGA, district, and village
 * coordinators see only their exact enclave.
 *
 * @return array{where: string, params: array<int, mixed>, label: string, missing: bool, tier: string, scopes: array<int, string>, states: array<int, string>}
 */
function coord_scope_filter(PDO $pdo, array $user): array
{
    $role = coord_current_workspace_role($pdo, $user);
    $tierScope = coord_scope_type($role);

    // National (and admin/super_admin operating the
    // coordination channel): unscoped.
    if ($role === 'national_coordinator' || $tierScope === 'national') {
        return ['where' => '1=1', 'params' => [], 'label' => 'National', 'missing' => false, 'tier' => 'national', 'scopes' => [], 'states' => []];
    }

    // Regional coordinator: every assigned state at once.
    if ($role === 'regional_coordinator') {
        $states = coord_assigned_states($pdo, $user);
        if (!$states) {
            return ['where' => '1=0', 'params' => [], 'label' => 'Unassigned Region', 'missing' => true, 'tier' => 'state', 'scopes' => [], 'states' => []];
        }
        $ids = [];
        $names = [];
        foreach ($states as $state) {
            $resolved = coord_resolve_state($pdo, (string) $state);
            if ((int) $resolved['id'] > 0) {
                $ids[] = (int) $resolved['id'];
            }
            foreach ($resolved['aliases'] as $alias) {
                $names[] = (string) $alias;
            }
        }
        $ids = array_values(array_unique($ids));
        $names = array_values(array_unique($names));
        $clauses = [];
        $params = [];
        if ($ids !== []) {
            $clauses[] = 'a.state_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
            $params = $ids;
        }
        if ($names !== []) {
            $clauses[] = coord_app_state_expr() . ' IN (' . implode(',', array_fill(0, count($names), '?')) . ')';
            $params = array_merge($params, $names);
        }
        if (!$clauses) {
            return ['where' => '1=0', 'params' => [], 'label' => 'Unassigned Region', 'missing' => true, 'tier' => 'state', 'scopes' => $states, 'states' => $states];
        }
        return [
            'where' => '(' . implode(' OR ', $clauses) . ')',
            'params' => $params,
            'label' => 'Region (' . count($states) . ' state' . (count($states) > 1 ? 's' : '') . ')',
            'missing' => false,
            'tier' => 'state',
            'scopes' => $states,
            'states' => $states,
        ];
    }

    // State coordinator: one selected state (existing behavior).
    if ($role === 'state_coordinator') {
        $filter = coord_state_filter($pdo, $user);
        $filter['tier'] = 'state';
        $filter['scopes'] = (array) ($filter['states'] ?? []);
        return $filter;
    }

    // Below-state tiers: exact match on the declared
    // enclave column (district / village) or the LGA
    // reference table.
    $enclaveColumns = ['lga' => null, 'district' => 'district', 'village' => 'village'];
    if (isset($enclaveColumns[$tierScope])) {
        $scopes = coord_assigned_scopes($pdo, $user, $tierScope);
        $missingLabel = 'Unassigned ' . ucfirst($tierScope);
        if (!$scopes) {
            return ['where' => '1=0', 'params' => [], 'label' => $missingLabel, 'missing' => true, 'tier' => $tierScope, 'scopes' => [], 'states' => []];
        }

        if ($tierScope === 'lga') {
            $ids = [];
            $names = [];
            foreach ($scopes as $lga) {
                $stmt = $pdo->prepare('SELECT id FROM nigeria_lgas WHERE LOWER(lga_name) = LOWER(?) LIMIT 1');
                $stmt->execute([(string) $lga]);
                $id = (int) ($stmt->fetchColumn() ?: 0);
                if ($id > 0) {
                    $ids[] = $id;
                }
                $names[] = (string) $lga;
            }
            $clauses = [];
            $params = [];
            if ($ids !== []) {
                $clauses[] = 'a.lga_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
                $params = $ids;
            }
            if ($names !== []) {
                $clauses[] = coord_app_lga_expr() . ' IN (' . implode(',', array_fill(0, count($names), '?')) . ')';
                $params = array_merge($params, $names);
            }
            if (!$clauses) {
                return ['where' => '1=0', 'params' => [], 'label' => $missingLabel, 'missing' => true, 'tier' => 'lga', 'scopes' => $scopes, 'states' => []];
            }
            return [
                'where' => '(' . implode(' OR ', $clauses) . ')',
                'params' => $params,
                'label' => implode(', ', $scopes),
                'missing' => false,
                'tier' => 'lga',
                'scopes' => $scopes,
                'states' => [],
            ];
        }

        // district / village: case-insensitive exact match
        // on the self-declared enclave column.
        $column = $enclaveColumns[$tierScope];
        $clauses = array_fill(0, count($scopes), 'LOWER(a.' . $column . ') = LOWER(?)');
        return [
            'where' => '(' . implode(' OR ', $clauses) . ')',
            'params' => array_values($scopes),
            'label' => implode(', ', $scopes),
            'missing' => false,
            'tier' => $tierScope,
            'scopes' => $scopes,
            'states' => [],
        ];
    }

    // Unknown tier in the coordination channel: treat as
    // national rather than silently hiding everything.
    return ['where' => '1=1', 'params' => [], 'label' => 'National', 'missing' => false, 'tier' => 'national', 'scopes' => [], 'states' => []];
}

function coord_header(string $title, string $subtitle, array $user, string $active): void
{
    $sessionRole = app_role_normalize((string) ($_SESSION['coord_workspace_role'] ?? ''));
    $role = ($sessionRole !== '' && app_user_has_role(coord_pdo(), $user, $sessionRole)) ? $sessionRole : coord_primary_workspace_role(coord_pdo(), $user);
    $label = coord_role_label($role);
    $home = coord_home($role);
    $scope = coord_scope_filter(coord_pdo(), $user);
    $selectedState = (string) ($scope['label'] ?? '');
    $scopeTitle = [
        'state' => 'State Scope',
        'lga' => 'LGA Scope',
        'district' => 'District Scope',
        'village' => 'Village Scope',
    ][$scope['tier'] ?? 'national'] ?? 'Scope';
    $menu = [
        'home' => [$label . ' Home', $home, 'fa-gauge-high'],
        'operations' => ['Operations', 'operations.php', 'fa-list-check'],
        'reports' => ['Reports', 'reports.php', 'fa-chart-line'],
        'support' => ['Support', 'support.php', 'fa-headset'],
        'academy' => ['Academy', 'academy.php', 'fa-graduation-cap'],
        'wallet' => ['Wallet', 'wallet.php', 'fa-wallet'],
        'profile' => ['Profile', 'profile.php', 'fa-user'],
    ];
    if (($scope['tier'] ?? '') === 'state' && $selectedState !== '' && $selectedState !== 'Unassigned State') {
        foreach ($menu as $key => $item) {
            $menu[$key][1] = coord_state_query((string) $item[1], $selectedState);
        }
    }
    ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= e($title) ?> - NATCODEV <?= e($label) ?></title><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"><style>
:root{--green:#075f2a;--deep:#053b1c;--line:#dfe8d8;--bg:#f6faf4;--ink:#101828;--muted:#667085;--gold:#c69320;--red:#b42318;--blue:#175cd3}*{box-sizing:border-box}body{margin:0;background:var(--bg);font-family:"Segoe UI",Arial,sans-serif;color:var(--ink)}a{text-decoration:none;color:inherit}.shell{display:grid;grid-template-columns:286px 1fr;min-height:100vh}.side{background:linear-gradient(180deg,#06451f,#092f22);color:#fff;padding:22px 18px;position:sticky;top:0;height:100vh;overflow:auto}.brand{display:flex;gap:12px;align-items:center;padding-bottom:18px;border-bottom:1px solid rgba(255,255,255,.16)}.brand img{width:56px;height:56px;border-radius:50%;background:#fff}.brand strong{font-size:1.35rem}.brand small{display:block;color:#bff0ce}.person{margin:18px 0;padding:14px;border:1px solid rgba(255,255,255,.16);border-radius:8px;background:rgba(255,255,255,.08)}.scope-box{display:grid;gap:7px;margin:12px 0 16px;padding:12px;border:1px solid rgba(255,255,255,.16);border-radius:8px;background:rgba(255,255,255,.08)}.scope-box strong{font-size:.78rem;color:#bff0ce;text-transform:uppercase}.scope-link{display:block;color:#eef8ef;padding:8px 10px;border-radius:7px;font-weight:900}.scope-link.active,.scope-link:hover{background:#118b42}.scope-warning{color:#ffe8a3;font-size:.84rem;line-height:1.45}.workspace-switcher{display:grid;gap:7px;margin:12px 0 16px;padding:12px;border:1px solid rgba(255,255,255,.16);border-radius:8px;background:rgba(255,255,255,.06)}.workspace-switcher strong{font-size:.78rem;color:#bff0ce;text-transform:uppercase}.workspace-link{display:block;color:#eef8ef;padding:9px 10px;border-radius:7px;font-weight:900}.workspace-link.active,.workspace-link:hover{background:#118b42}.nav{display:grid;gap:8px}.nav a{display:flex;gap:10px;align-items:center;padding:12px;border-radius:8px;color:#eef8ef;font-weight:900}.nav a.active,.nav a:hover{background:#118b42}.main{min-width:0}.top{height:72px;background:#fff;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;align-items:center;padding:0 26px;gap:14px;position:sticky;top:0;z-index:4}.chip{border:1px solid var(--line);background:#fff;border-radius:8px;padding:9px 11px;font-weight:850}.content{padding:26px}.page-head{display:flex;justify-content:space-between;gap:16px;align-items:flex-end;margin-bottom:18px}.page-head h1{margin:0;color:#082f19}.page-head p{margin:5px 0 0;color:var(--muted)}.kpis{display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:16px}.kpi{background:#fff;border:1px solid var(--line);border-radius:8px;padding:16px;box-shadow:0 12px 32px rgba(16,24,40,.06)}.kpi i{color:var(--green);font-size:1.25rem}.kpi b{display:block;font-size:1.55rem;margin-top:6px}.grid{display:grid;grid-template-columns:repeat(12,1fr);gap:16px}.card{background:#fff;border:1px solid var(--line);border-radius:8px;padding:16px;box-shadow:0 12px 32px rgba(16,24,40,.06)}.span-4{grid-column:span 4}.span-6{grid-column:span 6}.span-8{grid-column:span 8}.span-12{grid-column:span 12}.list{display:grid;gap:10px}.row{display:flex;justify-content:space-between;gap:12px;border-top:1px solid var(--line);padding-top:10px}.row:first-child{border-top:0;padding-top:0}.badge{display:inline-flex;border-radius:999px;padding:5px 9px;background:#eef8ef;color:var(--green);font-weight:900;font-size:.78rem}.btn{display:inline-flex;gap:8px;align-items:center;justify-content:center;border:1px solid var(--green);background:var(--green);color:#fff;border-radius:8px;padding:10px 14px;font-weight:950}.btn.light{background:#fff;color:var(--green)}.footer{padding:18px 26px;color:var(--muted);border-top:1px solid var(--line);background:#fff}@media(max-width:980px){.shell{grid-template-columns:1fr}.side{position:relative;height:auto}.kpis{grid-template-columns:1fr 1fr}.span-4,.span-6,.span-8{grid-column:span 12}}@media(max-width:640px){.kpis{grid-template-columns:1fr}.top,.page-head{align-items:flex-start;flex-direction:column}.content{padding:16px}}
</style></head><body><div class="shell"><aside class="side"><a class="brand" href="<?= e($home) ?>"><img src="<?= e(app_primary_logo_url()) ?>" alt="NATCODEV"><span><strong>NATCODEV</strong><small><?= e($label) ?> Channel</small></span></a><div class="person"><strong><?= e((string) ($user['name'] ?? 'Coordinator')) ?></strong><small><?= e($label) ?></small><br><span class="badge">Leadership stakeholder</span></div><?php if (($scope['tier'] ?? 'national') !== 'national'): ?><div class="scope-box"><strong><?= e($scopeTitle) ?></strong><?php if (!empty($scope['missing'])): ?><span class="scope-warning">No <?= e((string) ($scope['tier'] ?? 'state')) ?> is assigned. An operator must assign this coordinator to their enclave before work can appear here.</span><?php elseif (($scope['tier'] ?? '') === 'state'): ?><?php foreach ((array) ($scope['states'] ?? []) as $stateName): ?><a class="scope-link <?= strcasecmp((string) $stateName, $selectedState) === 0 ? 'active' : '' ?>" href="<?= e(coord_state_query($home, (string) $stateName)) ?>"><?= e((string) $stateName) ?></a><?php endforeach; ?><?php else: ?><?php foreach ((array) ($scope['scopes'] ?? []) as $scopeName): ?><span class="scope-link active"><?= e((string) $scopeName) ?></span><?php endforeach; ?><?php endif; ?></div><?php endif; ?><?php app_render_internal_public_workspace_switcher(coord_pdo(), $user, $role); ?><nav class="nav"><?php foreach ($menu as $key => $item): ?><a class="<?= $active === $key ? 'active' : '' ?>" href="<?= e($item[1]) ?>"><i class="fas <?= e($item[2]) ?>"></i><?= e($item[0]) ?></a><?php endforeach; ?><a href="logout.php?_csrf=<?= e(csrf_token()) ?>"><i class="fas fa-right-from-bracket"></i>Logout</a></nav></aside><main class="main"><header class="top"><span class="chip"><i class="fas fa-crown"></i> <?= e($label) ?></span><?php if (($scope['tier'] ?? 'national') !== 'national'): ?><span class="chip"><i class="fas fa-map-location-dot"></i> <?= e($selectedState) ?></span><?php endif; ?><span class="chip"><?= e(date('M j, Y h:i A')) ?></span><a class="chip" href="../index.php"><i class="fas fa-house"></i> NATCODEV</a><a class="chip" href="profile.php"><i class="fas fa-user"></i> Profile</a><a class="chip" href="logout.php?_csrf=<?= e(csrf_token()) ?>"><i class="fas fa-right-from-bracket"></i> Logout</a></header><?= page_breadcrumbs(auto_breadcrumbs($menu, $active, $label . ' Channel', $home), 'NATCODEV', '../index.php') ?><section class="content"><div class="page-head"><div><h1><?= e($title) ?></h1><p><?= e($subtitle) ?></p></div><a class="btn light" href="logout.php?_csrf=<?= e(csrf_token()) ?>"><i class="fas fa-right-from-bracket"></i> Logout</a></div>
<?php
}

function coord_footer(): void
{
    ?>
</section><footer class="footer">NATCODEV Coordination Channel / leadership operations, reports, support, training, wallet, and safe exit.</footer></main></div></body></html>
<?php
}

function coord_render_home(PDO $pdo, array $user, string $workspaceRole): void
{
    $role = coord_role_key($user);
    $scope = coord_scope_filter($pdo, $user);
    $where = $scope['where'];
    $params = $scope['params'];
    $apps = coord_scalar($pdo, "SELECT COUNT(*) FROM " . coord_app_from() . " WHERE {$where}", $params);
    $active = coord_scalar($pdo, "SELECT COUNT(*) FROM " . coord_app_from() . " WHERE {$where} AND " . coord_app_status_expr() . " IN ('active','verified','approved','confirmed')", $params);
    $pending = coord_scalar($pdo, "SELECT COUNT(*) FROM " . coord_app_from() . " WHERE {$where} AND " . coord_app_status_expr() . " IN ('pending','submitted','under_review','new')", $params);
    $lgaCount = coord_scalar($pdo, "SELECT COUNT(DISTINCT " . coord_app_lga_expr() . ") FROM " . coord_app_from() . " WHERE {$where}", $params);
    $tickets = coord_scalar($pdo, "SELECT COUNT(*) FROM support_tickets WHERE status IN ('open','in_progress','waiting_on_user','escalated')");
    $wallet = wallet_get_or_create($pdo, (int) $user['id']);
    $recent = coord_rows($pdo, "SELECT a.app_ref, a.name, " . coord_app_state_expr() . " state, " . coord_app_lga_expr() . " lga, " . coord_app_status_expr() . " status, a.created_at FROM " . coord_app_from() . " WHERE {$where} ORDER BY a.created_at DESC LIMIT 8", $params);
    $stateLabel = (string) ($scope['label'] ?? '');
    $tier = (string) ($scope['tier'] ?? 'national');
    if ($workspaceRole === 'national_coordinator' || $tier === 'national') {
        $title = 'National Coordinator Workspace';
        $subtitle = 'Lead national coordination, state oversight, reports, support escalations, training, and governance.';
    } elseif ($tier === 'state') {
        $title = $stateLabel . ' Coordinator Workspace';
        $subtitle = 'Manage state registrations, LGAs, field teams, support, learning, wallet signals, and state reports.';
    } else {
        $title = $stateLabel . ' ' . ucfirst($tier) . ' Coordinator Workspace';
        $subtitle = 'Manage registrations, field teams, support, learning, wallet signals, and reports for your assigned ' . $tier . ' enclave.';
    }
    coord_header($title, $subtitle, $user, 'home');
    ?>
<?php if (!empty($scope['missing'])): ?><div class="card" style="margin-bottom:16px;border-color:#facc15;background:#fffbeb"><h2><?= e(ucfirst($tier)) ?> Assignment Required</h2><p>This coordinator has access, but no <?= e($tier) ?> scope is assigned. An operator must assign the enclave in RBAC before work appears here.</p></div><?php endif; ?>
<div class="kpis"><div class="kpi"><i class="fas fa-map-location-dot"></i><b><?= e($stateLabel) ?></b><span>Active scope</span></div><div class="kpi"><i class="fas fa-users"></i><b><?= number_format($apps) ?></b><span>Registrations</span></div><div class="kpi"><i class="fas fa-circle-check"></i><b><?= number_format($active) ?></b><span>Active / verified</span></div><div class="kpi"><i class="fas fa-hourglass-half"></i><b><?= number_format($pending) ?></b><span>Pending review</span></div><div class="kpi"><i class="fas fa-location-dot"></i><b><?= number_format($lgaCount) ?></b><span>LGAs represented</span></div></div>
<?php if (!empty($scope['states'])): ?><div class="card" style="margin-bottom:16px"><h2>Assigned States</h2><p style="color:var(--muted)">Each state is managed separately. Select a state before comparing or aggregating results nationally.</p><?php foreach ((array) $scope['states'] as $stateName): ?><a class="btn <?= strcasecmp((string) $stateName, $stateLabel) === 0 ? '' : 'light' ?>" href="<?= e(coord_state_query('state-coordinator.php', (string) $stateName)) ?>" style="margin:4px 6px 4px 0"><?= e((string) $stateName) ?></a><?php endforeach; ?></div><?php endif; ?>
<div class="grid"><section class="card span-8"><h2>Recent Registry Activity</h2><div class="list"><?php foreach ($recent as $row): ?><div class="row"><span><strong><?= e((string) $row['name']) ?></strong><br><small><?= e((string) $row['app_ref']) ?> / <?= e((string) $row['state']) ?> / <?= e((string) $row['lga']) ?></small></span><span class="badge"><?= e((string) $row['status']) ?></span></div><?php endforeach; ?><?php if (!$recent): ?><div class="row">No registration activity found for this scope yet.</div><?php endif; ?></div></section><section class="card span-4"><h2>Leadership Flow</h2><div class="list"><a class="row" href="operations.php"><strong>1. Review operations</strong><span class="badge">Lead</span></a><a class="row" href="reports.php"><strong>2. Read intelligence</strong><span class="badge">Report</span></a><a class="row" href="support.php"><strong>3. Resolve escalations</strong><span class="badge">Support</span></a><a class="row" href="logout.php?_csrf=<?= e(csrf_token()) ?>"><strong>4. Exit safely</strong><span class="badge">Logout</span></a></div></section><section class="card span-12"><h2>Workspace Tools</h2><p><a class="btn" href="operations.php">Operations</a> <a class="btn light" href="reports.php">Reports</a> <a class="btn light" href="support.php">Support</a> <a class="btn light" href="academy.php">Academy</a> <a class="btn light" href="wallet.php">Wallet</a> <a class="btn light" href="import.php">Import Members</a></p></section></div>
<?php coord_footer(); }
