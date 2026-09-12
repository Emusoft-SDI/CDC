<?php
declare(strict_types=1);

function market_ensure_role_assignment_schema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_role_assignments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            role_key VARCHAR(60) NOT NULL,
            scope_type VARCHAR(40) NOT NULL DEFAULT 'global',
            scope_value VARCHAR(160) NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'active',
            notes TEXT NULL,
            assigned_by INT NULL,
            assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            revoked_at DATETIME NULL,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_user_role_scope (user_id, role_key, scope_type, scope_value),
            INDEX idx_user_role_active (user_id, role_key, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'user_role_assignments');
}

function market_activate_seller_access(PDO $pdo, array $user): void
{
    app_add_column_if_missing($pdo, 'users', 'platform_role', 'VARCHAR(60) NULL');
    market_ensure_role_assignment_schema($pdo);
    $pdo->prepare("
        INSERT INTO user_role_assignments (user_id, role_key, scope_type, scope_value, status, notes)
        VALUES (?, 'seller', 'global', '', 'active', 'Self-activated marketplace seller access')
        ON DUPLICATE KEY UPDATE status = 'active', revoked_at = NULL, notes = VALUES(notes)
    ")->execute([(int) $user['id']]);
    $pdo->prepare("
        UPDATE users
        SET platform_role = CASE
            WHEN COALESCE(NULLIF(platform_role, ''), role) IN ('provider','input_provider','service_provider','admin','super_admin','field_agent','agronomist','agric_extensionist','state_coordinator','national_coordinator') THEN platform_role
            ELSE 'seller'
        END
        WHERE id = ?
    ")->execute([(int) $user['id']]);
}

function market_boot(): PDO
{
    $pdo = db();
    marketplace_ensure_schema($pdo);
    market_ensure_role_assignment_schema($pdo);
    revenue_ensure_schema($pdo);
    return $pdo;
}

function market_asset_logo(): string
{
    return app_primary_logo_url();
}

function market_user(PDO $pdo): ?array
{
    return current_user($pdo);
}

function market_initials(string $name): string
{
    $name = trim($name);
    if ($name === '') {
        return 'NT';
    }
    $parts = preg_split('/\s+/', $name) ?: [];
    $letters = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $letters .= strtoupper(substr((string) $part, 0, 1));
    }
    return $letters !== '' ? $letters : 'NT';
}

function market_require_user(PDO $pdo): array
{
    $user = market_user($pdo);
    if (!$user) {
        redirect_to('seller-login.php');
    }
    if (app_user_needs_email_verification($user)) {
        unset($_SESSION['user_id']);
        redirect_to('seller-login.php?email=' . urlencode((string) ($user['email'] ?? '')));
    }
    // Seller marketplace accounts must confirm email before workspace access.

    return $user;
}

function market_user_can_sell(PDO $pdo, array $user): bool
{
    if (!admin_feature_is_allowed($pdo, 'marketplace')) {
        return false;
    }
    $roles = market_user_role_keys($pdo, $user);
    return (bool) array_intersect(['seller', 'marketplace_seller', 'admin', 'super_admin'], $roles);
}

function market_user_is_buyer(array $user): bool
{
    $role = strtolower((string) ($user['platform_role'] ?? $user['role'] ?? ''));
    return in_array($role, ['buyer', 'consumer', 'user'], true); // Add more buyer roles if needed
}

function market_user_is_seller(array $user): bool
{
    $role = strtolower((string) ($user['platform_role'] ?? $user['role'] ?? ''));
    return in_array($role, ['seller', 'marketplace_seller'], true);
}

function market_stakeholder_label(array $user): string
{
    $role = (string) ($user['platform_role'] ?? $user['role'] ?? 'stakeholder');
    return marketplace_status_label($role);
}

function market_url(string $path = 'index.php'): string
{
    return $path;
}

function market_user_role_keys(PDO $pdo, array $user): array
{
    $userId = (int) ($user['id'] ?? 0);
    $roles = [];
    $platformRole = strtolower(trim((string) ($user['platform_role'] ?? '')));
    $baseRole = strtolower(trim((string) ($user['role'] ?? '')));
    if ($platformRole !== '') {
        $roles[] = $platformRole;
    }
    if ($baseRole !== '' && ($baseRole !== 'grower' || $platformRole === '' || $platformRole === 'grower')) {
        $roles[] = $baseRole;
    }
    if ($userId > 0 && app_table_exists($pdo, 'user_role_assignments')) {
        $stmt = $pdo->prepare("SELECT role_key FROM user_role_assignments WHERE user_id=? AND status='active'");
        $stmt->execute([$userId]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $role) {
            $roles[] = strtolower(trim((string) $role));
        }
    }
    if ($userId > 0 && app_table_exists($pdo, 'marketplace_sellers')) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM marketplace_sellers WHERE user_id=?");
        $stmt->execute([$userId]);
        if ((int) $stmt->fetchColumn() > 0) {
            $roles[] = 'seller';
        }
    }
    if ($userId > 0 && app_table_exists($pdo, 'provider_registry') && app_column_exists($pdo, 'provider_registry', 'user_id')) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM provider_registry WHERE user_id=?");
        $stmt->execute([$userId]);
        if ((int) $stmt->fetchColumn() > 0) {
            $roles[] = 'provider';
        }
    }
    if ($userId > 0 && app_table_exists($pdo, 'grower_farms')) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM grower_farms WHERE user_id=?");
        $stmt->execute([$userId]);
        if ((int) $stmt->fetchColumn() > 0) {
            $roles[] = 'grower';
        }
    }
    if ((int) ($user['is_super_admin'] ?? 0) === 1 || $baseRole === 'admin' || $platformRole === 'super_admin') {
        $roles[] = 'admin';
        $roles[] = 'super_admin';
    }
    return array_values(array_unique(array_filter($roles)));
}

function market_user_dashboard_links(PDO $pdo, array $user, string $current = ''): array
{
    $roles = market_user_role_keys($pdo, $user);
    $has = static fn(array $need): bool => (bool) array_intersect($need, $roles);
    $links = [];
    $add = static function (string $key, string $label, string $href, string $icon) use (&$links, $current): void {
        $links[$key] = ['key' => $key, 'label' => $label, 'href' => $href, 'icon' => $icon, 'active' => $current === $key];
    };
    if ($has(['seller', 'marketplace_seller'])) {
        $add('seller', 'Seller Central', '../market/seller-central.php', 'store');
    }
    if ($has(['provider', 'input_provider', 'service_provider'])) {
        $add('provider', 'Provider Console', '../provider/dashboard.php', 'briefcase-business');
    }
    if ($has(['grower', 'farmer'])) {
        $add('grower', 'Grower Dashboard', '../dashboard/index.php', 'sprout');
    }
    if ($has(['learner', 'student'])) {
        $add('academy', 'Learner Dashboard', '../academy/dashboard.php', 'graduation-cap');
    }
    if ($has(['field_agent', 'agronomist', 'agric_extensionist', 'extensionist', 'state_coordinator', 'national_coordinator'])) {
        $add('field', 'Field Workspace', '../field-agent/index.php', 'clipboard-check');
    }
    if ($has(['admin', 'super_admin', 'national_coordinator', 'state_coordinator'])) {
        $add('admin', 'Admin Workspace', '../admin/index.php', 'shield-check');
    }
    if (!$links) {
        $add('marketplace', 'Marketplace', '../market/index.php', 'shopping-bag');
    }
    return array_values($links);
}

function market_dashboard_url_for_user(PDO $pdo, array $user, string $fallback = '../market/index.php'): string
{
    $links = market_user_dashboard_links($pdo, $user);
    return $links[0]['href'] ?? $fallback;
}

function market_dashboard_url(): string
{
    $pdo = db();
    $user = current_user($pdo);
    return $user ? market_dashboard_url_for_user($pdo, $user, '../dashboard/index.php') : '../dashboard/index.php';
}
