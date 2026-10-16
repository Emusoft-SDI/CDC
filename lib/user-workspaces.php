<?php
declare(strict_types=1);

function app_role_normalize(string $role): string
{
    $role = strtolower(trim(str_replace(' ', '_', $role)));
    return match ($role) {
        'agric_extensionist', 'agricultural_extensionist' => 'extensionist',
        default => $role,
    };
}

function app_user_role_keys(PDO $pdo, array $user): array
{
    $roles = [];
    foreach (['platform_role', 'role'] as $column) {
        $role = app_role_normalize((string) ($user[$column] ?? ''));
        if ($role !== '') {
            $roles[] = $role;
        }
    }
    $userId = (int) ($user['id'] ?? 0);
    if ($userId > 0 && app_table_exists($pdo, 'user_role_assignments')) {
        $stmt = $pdo->prepare("SELECT role_key FROM user_role_assignments WHERE user_id = ? AND status = 'active'");
        $stmt->execute([$userId]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $role) {
            $role = app_role_normalize((string) $role);
            if ($role !== '') {
                $roles[] = $role;
            }
        }
    }
    if ((int) ($user['is_super_admin'] ?? 0) === 1) {
        $roles[] = 'super_admin';
        $roles[] = 'admin';
    }
    return array_values(array_unique($roles));
}

function app_user_has_any_role(PDO $pdo, array $user, array $roles): bool
{
    $allowed = array_map('app_role_normalize', $roles);
    return (bool) array_intersect(app_user_role_keys($pdo, $user), $allowed);
}

function app_user_has_role(PDO $pdo, array $user, string $role): bool
{
    return app_user_has_any_role($pdo, $user, [$role]);
}

function app_internal_public_workspace_catalog(): array
{
    $base = rtrim(app_base_url(), '/');
    return [
        'field_agent' => ['Field Agent', $base . '/field-agent/field-agent.php', 'field'],
        'farm_hand' => ['Farm Hand', $base . '/field-agent/farm-hand.php', 'field'],
        'agronomist' => ['Agronomist', $base . '/field-agent/agronomist.php', 'field'],
        'extensionist' => ['Agric Extensionist', $base . '/field-agent/extensionist.php', 'field'],
        'state_coordinator' => ['State Coordinator', $base . '/coordination/state-coordinator.php', 'coordination'],
        'national_coordinator' => ['National Coordinator', $base . '/coordination/national-coordinator.php', 'coordination'],
        'regional_coordinator' => ['Regional Coordinator', $base . '/coordination/state-coordinator.php', 'coordination'],
        'lga_coordinator' => ['LGA Coordinator', $base . '/coordination/enclave-coordinator.php', 'coordination'],
        'district_coordinator' => ['District Coordinator', $base . '/coordination/enclave-coordinator.php', 'coordination'],
        'village_coordinator' => ['Village Coordinator', $base . '/coordination/enclave-coordinator.php', 'coordination'],
        'cooperative_admin' => ['Cooperative Admin', $base . '/cooperatives/', 'cooperative'],
        'support_agent' => ['Support Agent Desk', $base . '/support/agent.php', 'support'],
    ];
}

function app_internal_public_workspace_links(PDO $pdo, array $user, string $current = ''): array
{
    $roles = app_user_role_keys($pdo, $user);
    $links = [];
    foreach (app_internal_public_workspace_catalog() as $role => [$label, $href, $channel]) {
        if (in_array($role, $roles, true) || in_array('admin', $roles, true) || in_array('super_admin', $roles, true)) {
            $links[] = [
                'role' => $role,
                'label' => $label,
                'href' => $href,
                'channel' => $channel,
                'active' => $current === $role,
            ];
        }
    }
    return $links;
}

function app_render_internal_public_workspace_switcher(PDO $pdo, array $user, string $current = ''): void
{
    $links = app_internal_public_workspace_links($pdo, $user, $current);
    if (!$links) {
        return;
    }
    echo '<div class="workspace-switcher"><strong>My Workspaces</strong>';
    foreach ($links as $link) {
        $class = !empty($link['active']) ? ' active' : '';
        echo '<a class="workspace-link' . e($class) . '" href="' . e((string) $link['href']) . '">' . e((string) $link['label']) . '</a>';
    }
    echo '</div>';
}

if (!function_exists('platform_safe_next')) {
    function platform_safe_next(string $next): string
    {
        $next = trim(str_replace(["\0", '\\'], ['', '/'], $next));
        if ($next === '' || preg_match('/^[a-z][a-z0-9+.-]*:/i', $next) || str_starts_with($next, '//') || str_starts_with($next, '/')) {
            return '';
        }
        while (str_starts_with($next, '../')) {
            $next = substr($next, 3);
        }
        return $next;
    }
}

if (!function_exists('platform_user_home')) {
    function platform_user_home(array $user, string $requestedNext = '', ?PDO $pdo = null): string
    {
        $safeNext = platform_safe_next($requestedNext);
        if ($safeNext !== '') {
            return $safeNext;
        }

        if ($pdo === null) {
            try { $pdo = db(); } catch (Throwable) {}
        }

        $role = strtolower((string) ($user['role'] ?? ''));
        $platformRole = strtolower((string) ($user['platform_role'] ?? ''));

        if ((int) ($user['is_super_admin'] ?? 0) === 1 || $platformRole === 'super_admin') {
            return 'super-admin/index.php';
        }
        if ($platformRole === 'support_agent') {
            return ($pdo && admin_feature_is_allowed($pdo, 'support')) ? 'support/agent.php' : 'dashboard/index.php';
        }
        if (in_array($platformRole, ['national_coordinator', 'regional_coordinator', 'state_coordinator', 'lga_coordinator', 'district_coordinator', 'village_coordinator'], true)) {
            return 'coordination/index.php';
        }
        if ($platformRole === 'cooperative_admin') {
            return 'cooperatives/index.php';
        }
        if (in_array($platformRole, ['field_agent', 'agronomist', 'agric_extensionist', 'extensionist', 'farm_hand'], true) || $role === 'field_agent') {
            return 'field-agent/index.php';
        }
        if (in_array($platformRole, ['seller', 'marketplace_seller'], true) || $role === 'seller') {
            return ($pdo && admin_feature_is_allowed($pdo, 'marketplace')) ? 'market/seller-central.php' : 'dashboard/index.php';
        }
        if ($platformRole === 'buyer' || $role === 'buyer') {
            return ($pdo && admin_feature_is_allowed($pdo, 'marketplace')) ? 'buyer/index.php' : 'dashboard/index.php';
        }
        if (in_array($platformRole, ['provider', 'input_provider', 'service_provider'], true) || $role === 'provider') {
            return ($pdo && admin_feature_is_allowed($pdo, 'providers')) ? 'provider/dashboard.php' : 'dashboard/index.php';
        }
        if ($platformRole === 'learner' || $role === 'learner') {
            return ($pdo && admin_feature_is_allowed($pdo, 'training')) ? 'academy/dashboard.php' : 'dashboard/index.php';
        }
        if ($role === 'admin' || $platformRole === 'admin') {
            return 'admin/index.php';
        }

        return 'dashboard/index.php';
    }
}

if (!function_exists('otp_login_destination')) {
    function otp_login_destination(PDO $pdo, int $userId): string
    {
        $fields = ['role'];
        if (app_column_exists($pdo, 'users', 'platform_role')) {
            $fields[] = 'platform_role';
        }
        if (app_column_exists($pdo, 'users', 'is_super_admin')) {
            $fields[] = 'is_super_admin';
        }

        $stmt = $pdo->prepare('SELECT ' . implode(', ', $fields) . ' FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $user = $stmt->fetch() ?: [];
        $role = strtolower((string) ($user['role'] ?? ''));
        $platformRole = strtolower((string) ($user['platform_role'] ?? ''));

        if ((int) ($user['is_super_admin'] ?? 0) === 1 || $platformRole === 'super_admin') {
            return 'super-admin/index.php';
        }
        if (in_array($platformRole, ['national_coordinator', 'regional_coordinator', 'state_coordinator', 'lga_coordinator', 'district_coordinator', 'village_coordinator'], true)) {
            return 'coordination/index.php';
        }
        if ($platformRole === 'cooperative_admin') {
            return 'cooperatives/index.php';
        }
        if ($platformRole === 'support_agent') {
            return (admin_feature_is_allowed($pdo, 'support')) ? 'support/agent.php' : 'dashboard/index.php';
        }
        if ($platformRole === 'admin' || $role === 'admin') {
            return 'admin/index.php';
        }
        if (in_array($platformRole, ['field_agent', 'agronomist', 'agric_extensionist', 'extensionist', 'farm_hand'], true) || $role === 'field_agent') {
            return 'field-agent/index.php';
        }
        if (in_array($platformRole, ['provider', 'input_provider', 'service_provider', 'seller'], true) || in_array($role, ['provider', 'seller'], true)) {
            return (admin_feature_is_allowed($pdo, 'providers') && admin_feature_is_allowed($pdo, 'marketplace')) ? 'provider/dashboard.php' : 'dashboard/index.php';
        }
        if ($platformRole === 'buyer' || $role === 'buyer') {
            return (admin_feature_is_allowed($pdo, 'marketplace')) ? 'buyer/index.php' : 'dashboard/index.php';
        }
        if ($platformRole === 'learner' || $role === 'learner') {
            return (admin_feature_is_allowed($pdo, 'training')) ? 'academy/dashboard.php' : 'dashboard/index.php';
        }

        return 'dashboard/index.php';
    }
}
