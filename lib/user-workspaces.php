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
