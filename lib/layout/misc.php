<?php

function admin_audit(PDO $pdo, string $action, string $description, array $context = []): void
{
    if (!app_table_exists($pdo, 'audit_log')) {
        return;
    }
    $actorId = admin_current_user_id($pdo);
    $actorName = admin_current_user_name($pdo);
    if ($actorName === null && !empty($_SESSION['admin_authenticated'])) {
        $actorName = 'legacy_operator';
    }
    if ($context) {
        $description .= ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    try {
        $pdo->prepare('INSERT INTO audit_log (action, description, ip_address, actor_id, actor_name) VALUES (?, ?, ?, ?, ?)')
            ->execute([$action, $description, $_SERVER['REMOTE_ADDR'] ?? null, $actorId, $actorName]);
    } catch (Throwable $e) {
        $pdo->prepare('INSERT INTO audit_log (action, description, ip_address) VALUES (?, ?, ?)')
            ->execute([$action, $description, $_SERVER['REMOTE_ADDR'] ?? null]);
    }
}


function admin_record_authenticity_status(PDO $pdo, string $targetTable, ?int $targetId = null, ?string $targetKey = null): array
{
    $targetTable = preg_replace('/[^a-zA-Z0-9_]/', '', $targetTable);
    if ($targetTable === '' || !app_table_exists($pdo, $targetTable)) {
        return ['requires_approval' => false, 'status' => '', 'label' => ''];
    }

    $approvedStatuses = ['approved', 'verified', 'issued', 'confirmed', 'active', 'accredited', 'completed', 'valid'];
    $statusColumns = [
        'applications' => ['confirmed', 'review_status'],
        'document_requirements' => ['verification_status', 'verified'],
        'grower_farms' => [],
        'farm_verifications' => ['status'],
        'marketplace_sellers' => ['approval_status', 'verification_status'],
        'marketplace_listings' => ['approval_status'],
        'provider_registry' => ['status'],
        'staff_profiles' => ['status'],
        'certificates' => ['status'],
        'academy_certificates' => ['status'],
        'academy_enrollments' => ['status'],
        'provider_offerings' => ['status'],
    ][$targetTable] ?? [];

    if ($targetTable === 'grower_farms' && $targetId !== null && app_table_exists($pdo, 'farm_verifications')) {
        $stmt = $pdo->prepare("SELECT status FROM farm_verifications WHERE farm_id = ? ORDER BY verified_at DESC, id DESC LIMIT 1");
        $stmt->execute([$targetId]);
        $status = strtolower((string) ($stmt->fetchColumn() ?: ''));
        return ['requires_approval' => in_array($status, $approvedStatuses, true), 'status' => $status, 'label' => 'farm verification'];
    }

    if (!$statusColumns) {
        return ['requires_approval' => false, 'status' => '', 'label' => ''];
    }

    $where = '';
    $params = [];
    if ($targetId !== null && app_column_exists($pdo, $targetTable, 'id')) {
        $where = 'id = ?';
        $params[] = $targetId;
    } elseif ($targetKey !== null && $targetTable === 'notification_templates' && app_column_exists($pdo, $targetTable, 'template_name')) {
        $where = 'template_name = ?';
        $params[] = $targetKey;
    } else {
        return ['requires_approval' => false, 'status' => '', 'label' => ''];
    }

    $existingColumns = array_values(array_filter($statusColumns, static fn(string $column): bool => app_column_exists($pdo, $targetTable, $column)));
    if (!$existingColumns) {
        return ['requires_approval' => false, 'status' => '', 'label' => ''];
    }

    $stmt = $pdo->prepare('SELECT ' . implode(', ', $existingColumns) . " FROM {$targetTable} WHERE {$where} LIMIT 1");
    $stmt->execute($params);
    $row = $stmt->fetch();
    if (!$row) {
        return ['requires_approval' => false, 'status' => '', 'label' => ''];
    }

    foreach ($existingColumns as $column) {
        $value = strtolower(trim((string) ($row[$column] ?? '')));
        if ($column === 'confirmed' || $column === 'verified') {
            $isAuthentic = (int) ($row[$column] ?? 0) === 1;
            if ($isAuthentic) {
                return ['requires_approval' => true, 'status' => $column, 'label' => $column];
            }
            continue;
        }
        if (in_array($value, $approvedStatuses, true)) {
            return ['requires_approval' => true, 'status' => $value, 'label' => $column];
        }
    }

    return ['requires_approval' => false, 'status' => '', 'label' => ''];
}


function admin_review_action_request(PDO $pdo, int $requestId, string $decision, string $note = ''): void
{
    if (!admin_current_user_is_super_admin($pdo)) {
        http_response_code(403);
        exit('Forbidden: only Super Admin can review delete requests.');
    }
    admin_ensure_action_request_schema($pdo);
    $stmt = $pdo->prepare("SELECT * FROM admin_action_requests WHERE id = ? AND status = 'pending' LIMIT 1");
    $stmt->execute([$requestId]);
    $request = $stmt->fetch();
    if (!$request) {
        throw new RuntimeException('Delete request not found or already reviewed.');
    }

    $decision = $decision === 'approve' ? 'approved' : 'rejected';
    $pdo->beginTransaction();
    try {
        if ($decision === 'approved') {
            admin_execute_approved_delete($pdo, $request);
        }
        $pdo->prepare("
            UPDATE admin_action_requests
            SET status = ?, reviewed_by = ?, reviewed_at = NOW(), review_note = ?
            WHERE id = ?
        ")->execute([$decision, admin_current_user_id($pdo), $note !== '' ? $note : null, $requestId]);
        if (app_table_exists($pdo, 'audit_log')) {
            $pdo->prepare("INSERT INTO audit_log (action, description, ip_address) VALUES (?, ?, ?)")
                ->execute(['delete_request_' . $decision, 'Reviewed delete request #' . $requestId . ' for ' . (string) $request['target_table'] . '.', $_SERVER['REMOTE_ADDR'] ?? null]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}


function admin_user_has_admin_access(PDO $pdo, int $userId): bool
{
    if ($userId <= 0) {
        return false;
    }
    if (!app_table_exists($pdo, 'user_role_assignments')) {
        return false;
    }
    $adminRoles = ['admin', 'support_agent', 'national_coordinator', 'state_coordinator', 'agronomist', 'agric_extensionist', 'field_agent'];
    $placeholders = implode(',', array_fill(0, count($adminRoles), '?'));
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM user_role_assignments
        WHERE user_id = ? AND status = 'active' AND role_key IN ({$placeholders})
    ");
    $stmt->execute(array_merge([$userId], $adminRoles));
    return (int) $stmt->fetchColumn() > 0;
}


function admin_setting(PDO $pdo, string $key, string $default = ''): string
{
    if (!app_table_exists($pdo, 'settings')) {
        return $default;
    }
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE key_name = ? LIMIT 1");
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    return $value === false ? $default : (string) $value;
}


function admin_feature_catalog(): array
{
    return [
        'dashboard' => 'Dashboard',
        'state_dashboard' => 'State Dashboard',
        'national_dashboard' => 'National Dashboard',
        'governance' => 'Governance & Policy',
        'production_readiness' => 'Production Readiness',
        'profile' => 'Profile',
        'applications' => 'Applications',
        'documents' => 'Document Review',
        'certificates' => 'Certificates',
        'field_network' => 'Field Network',
        'support' => 'Support Desk',
        'farm_health' => 'Farm Health',
        'field_management' => 'Field Management',
        'agronomy_advisory' => 'Agronomy Advisory',
        'marketplace' => 'Marketplace',
        'providers' => 'Service & Input Providers',
        'resource_allocation' => 'Resource Allocation',
        'communications' => 'Communication Hub',
        'wallet' => 'Wallet',
        'revenue' => 'Revenue & Monetization',
        'training' => 'NATCODEV Academy',
        'healthcare' => 'Healthcare',
        'pricing' => 'Plans & Pricing',
        'resources' => 'Resources',
        'templates' => 'Templates',
        'notifications' => 'Notifications',
        'reports' => 'Reports',
        'analytics' => 'Analytics',
        'monitoring' => 'System Health',
        'user_management' => 'User Management',
        'imports' => 'Bulk Import',
        'settings' => 'Settings',
                'backups' => 'Backup & Disaster Recovery',
        'audit' => 'Audit Trail',
        'integrations' => 'Integrations',
    ];
}


function admin_feature_is_globally_enabled(PDO $pdo, string $feature): bool
{
    if ($feature === '' || !array_key_exists($feature, admin_feature_catalog())) {
        return true;
    }

    return admin_setting($pdo, 'module_' . $feature . '_enabled', '1') === '1';
}


function admin_default_access(string $role): array
{
    return match ($role) {
        'super_admin' => array_keys(admin_feature_catalog()),
        'admin' => array_values(array_diff(array_keys(admin_feature_catalog()), ['backups'])),
        'national_coordinator' => array_values(array_diff(array_keys(admin_feature_catalog()), ['backups'])),
        'state_coordinator' => ['dashboard', 'state_dashboard', 'profile', 'applications', 'documents', 'certificates', 'field_network', 'field_management', 'agronomy_advisory', 'support', 'farm_health', 'providers', 'resource_allocation', 'communications', 'resources', 'training', 'wallet', 'notifications', 'reports', 'analytics'],
        'support_agent' => ['dashboard', 'profile', 'support', 'communications', 'notifications', 'reports'],
        'field_agent', 'agronomist', 'agric_extensionist' => ['dashboard', 'profile', 'applications', 'field_network', 'field_management', 'agronomy_advisory', 'support', 'farm_health', 'resources', 'training', 'wallet', 'notifications', 'reports'],
        'investor' => ['dashboard', 'profile', 'marketplace', 'wallet', 'reports', 'analytics', 'notifications'],
        'learner' => ['dashboard', 'profile', 'support', 'wallet', 'training', 'notifications', 'reports'],
        default => ['dashboard', 'profile', 'applications', 'documents', 'certificates', 'support', 'farm_health', 'marketplace', 'wallet', 'training', 'notifications', 'reports'],
    };
}


function admin_current_scope_state(PDO $pdo): string
{
    if (admin_current_platform_role($pdo) !== 'state_coordinator') {
        return '';
    }
    $user = current_user($pdo);
    if (!$user) {
        return '';
    }
    if (app_table_exists($pdo, 'user_role_assignments')) {
        $stmt = $pdo->prepare("
            SELECT scope_value
            FROM user_role_assignments
            WHERE user_id = ? AND role_key = 'state_coordinator' AND scope_type = 'state' AND status = 'active'
            ORDER BY assigned_at DESC, id DESC
            LIMIT 1
        ");
        $stmt->execute([(int) $user['id']]);
        $assignedState = trim((string) ($stmt->fetchColumn() ?: ''));
        if ($assignedState !== '') {
            return $assignedState;
        }
    }
    $stmt = $pdo->prepare("SELECT state FROM staff_profiles WHERE user_id = ? LIMIT 1");
    $stmt->execute([(int) $user['id']]);
    $state = trim((string) ($stmt->fetchColumn() ?: ''));
    return $state !== '' ? $state : trim((string) ($user['location'] ?? ''));
}


function admin_feature_for_script(?string $script = null): string
{
    $script = $script ?? basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'admin.php'));
    return [
        'index.php' => 'dashboard',
        'admin.php' => 'applications',
        'registry.php' => 'applications',
        'coordination.php' => 'dashboard',
        'state-dashboard.php' => 'state_dashboard',
        'national-dashboard.php' => 'national_dashboard',
        'governance.php' => 'governance',
        'production-readiness.php' => 'production_readiness',
        'providers.php' => 'providers',
        'resource-allocation.php' => 'resource_allocation',
        'communications.php' => 'communications',
        'document-verification.php' => 'documents',
        'bulk-verification.php' => 'documents',
        'certificate-batch-verification.php' => 'certificates',
        'support.php' => 'support',
        'recruitment.php' => 'field_network',
        'agent-map.php' => 'field_network',
        'reports.php' => 'reports',
        'assign-growers.php' => 'field_network',
        'fields-management.php' => 'field_management',
        'agronomy.php' => 'agronomy_advisory',
        'analytics.php' => 'analytics',
        'demographics.php' => 'analytics',
        'validation-stats.php' => 'analytics',
        'monitoring.php' => 'monitoring',
        'marketplace.php' => 'marketplace',
        'wallet.php' => 'wallet',
        'revenue.php' => 'revenue',
        'resources.php' => 'resources',
        'academy.php' => 'training',
        'templates.php' => 'templates',
        'notifications.php' => 'notifications',
        'settings.php' => 'settings',
                'backups.php' => 'backups',
        'users.php' => 'user_management',
        'import-users.php' => 'imports',
        'profile.php' => 'profile',
    ][$script] ?? 'dashboard';
}


function admin_feature_is_allowed(PDO $pdo, string $feature): bool
{
    $feature = trim($feature);
    if ($feature === '' || !array_key_exists($feature, admin_feature_catalog())) {
        return false;
    }

    $role = admin_current_platform_role($pdo);
    if ($role === null) {
        return false;
    }
    if ($role === 'super_admin') {
        return true;
    }
    if (!admin_feature_is_globally_enabled($pdo, $feature)) {
        return false;
    }

    $roleKeys = [$role];
    $user = current_user($pdo);
    if ($user && (int) ($user['id'] ?? 0) > 0) {
        foreach (admin_active_role_assignments($pdo, (int) $user['id']) as $assignment) {
            $roleKeys[] = (string) $assignment['role_key'];
        }
    }

    foreach (array_unique($roleKeys) as $roleKey) {
        $default = implode(',', admin_default_access($roleKey));
        $allowed = array_values(array_filter(array_map('trim', explode(',', admin_setting($pdo, 'access_matrix_' . $roleKey, $default)))));
        if (admin_setting($pdo, 'access_matrix_catalog_version', '') !== ADMIN_ACCESS_CATALOG_VERSION) {
            $allowed = array_values(array_unique(array_merge($allowed, admin_default_access($roleKey))));
        }
        if (in_array($feature, $allowed, true)) {
            return true;
        }
    }
    return false;
}


function admin_per_page(int $default = 50): int
{
    $allowed = [10, 25, 50, 100, 200, 500];
    $perPage = (int) ($_GET['per_page'] ?? $default);
    return in_array($perPage, $allowed, true) ? $perPage : $default;
}


function admin_current_page(): int
{
    return max(1, (int) ($_GET['page'] ?? 1));
}

