<?php
declare(strict_types=1);

function super_admin_create_user(PDO $pdo, array $roles): void
{
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $platformRole = (string) ($_POST['platform_role'] ?? 'admin');
    $password = trim((string) ($_POST['password'] ?? ''));
    $location = trim((string) ($_POST['location'] ?? ''));

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        throw new RuntimeException('Name, valid email, and temporary password are required.');
    }
    if (!isset($roles[$platformRole])) {
        throw new RuntimeException('Invalid platform role.');
    }

    $stmt = $pdo->prepare("
        INSERT INTO users
            (email, password, name, phone, location, role, platform_role, account_status, is_super_admin, is_agronomist, is_extensionist, two_factor_required, profile_verified)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'active', ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $email,
        password_hash($password, PASSWORD_DEFAULT),
        $name,
        $phone !== '' ? $phone : null,
        $location !== '' ? $location : null,
        super_admin_auth_role($platformRole),
        $platformRole,
        $platformRole === 'super_admin' ? 1 : 0,
        $platformRole === 'agronomist' ? 1 : 0,
        $platformRole === 'agric_extensionist' ? 1 : 0,
        isset($_POST['two_factor_required']) ? 1 : 0,
        isset($_POST['profile_verified']) ? 1 : 0,
    ]);

    $loginUrl = app_base_url() . '/dashboard/login.php';
    app_send_mail($email, 'NATCODEV account created', "Hello {$name},\n\nYour NATCODEV {$roles[$platformRole]} account is ready.\n\nLogin: {$loginUrl}\nUsername: {$email}\nTemporary password: {$password}\n\nPlease sign in and update your password/profile immediately.");
    super_admin_audit($pdo, 'user_created', "Created {$roles[$platformRole]} account for {$email}.");
}

function super_admin_update_user(PDO $pdo, array $roles, array $statuses): void
{
    $userId = (int) ($_POST['user_id'] ?? 0);
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $platformRole = (string) ($_POST['platform_role'] ?? 'admin');
    $status = (string) ($_POST['account_status'] ?? 'active');

    if ($userId <= 0 || $name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('A valid user, name, and email are required.');
    }
    if (!isset($roles[$platformRole]) || !isset($statuses[$status])) {
        throw new RuntimeException('Invalid role or status.');
    }

    $suspendedUntil = $status === 'suspended' ? date('Y-m-d H:i:s', strtotime('+30 days')) : null;
    $deactivatedAt = $status === 'deactivated' ? date('Y-m-d H:i:s') : null;
    $archivedAt = $status === 'archived' ? date('Y-m-d H:i:s') : null;

    $stmt = $pdo->prepare("
        UPDATE users
        SET name = ?, email = ?, phone = ?, role = ?, platform_role = ?, account_status = ?,
            is_super_admin = ?, is_agronomist = ?, is_extensionist = ?, two_factor_required = ?, profile_verified = ?,
            suspended_until = ?, deactivated_at = ?, archived_at = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $name,
        $email,
        $phone !== '' ? $phone : null,
        super_admin_auth_role($platformRole),
        $platformRole,
        $status,
        $platformRole === 'super_admin' ? 1 : 0,
        $platformRole === 'agronomist' ? 1 : 0,
        $platformRole === 'agric_extensionist' ? 1 : 0,
        isset($_POST['two_factor_required']) ? 1 : 0,
        isset($_POST['profile_verified']) ? 1 : 0,
        $suspendedUntil,
        $deactivatedAt,
        $archivedAt,
        $userId,
    ]);
    super_admin_audit($pdo, 'user_updated', "Updated profile/access for {$email}.");
}

function super_admin_reset_password(PDO $pdo): void
{
    $userId = (int) ($_POST['user_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) {
        throw new RuntimeException('User not found.');
    }

    $password = super_admin_temp_password();
    $update = $pdo->prepare("UPDATE users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $update->execute([password_hash($password, PASSWORD_DEFAULT), $userId]);
    app_send_mail((string) $user['email'], 'NATCODEV password reset', "Hello {$user['name']},\n\nA temporary NATCODEV password has been issued by Super Administration.\n\nTemporary password: {$password}\nLogin: " . app_base_url() . "/dashboard/login.php\n\nPlease sign in and change it immediately.");
    super_admin_audit($pdo, 'password_reset', 'Password reset initiated for ' . $user['email'] . '.');
}

function super_admin_delete_user(PDO $pdo): void
{
    $userId = (int) ($_POST['user_id'] ?? 0);
    if ((string) ($_POST['confirm_delete'] ?? '') !== 'DELETE') {
        throw new RuntimeException('Type DELETE before archiving a user profile.');
    }
    if ($userId <= 0 || $userId === (int) ($_SESSION['user_id'] ?? 0)) {
        throw new RuntimeException('This user profile cannot be archived from the active session.');
    }

    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $email = (string) $stmt->fetchColumn();
    if ($email === '') {
        throw new RuntimeException('User not found.');
    }

    $pdo->prepare("
        UPDATE users
        SET account_status = 'archived',
            archived_at = COALESCE(archived_at, CURRENT_TIMESTAMP),
            deactivated_at = COALESCE(deactivated_at, CURRENT_TIMESTAMP),
            suspended_until = NULL,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ")->execute([$userId]);
    super_admin_audit($pdo, 'user_archived', "Archived user profile {$email}.");
}

function super_admin_restore_user(PDO $pdo): void
{
    $userId = (int) ($_POST['user_id'] ?? 0);
    if ($userId <= 0) {
        throw new RuntimeException('User not found.');
    }

    $stmt = $pdo->prepare("SELECT email, account_status FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) {
        throw new RuntimeException('User not found.');
    }
    if ((string) ($user['account_status'] ?? '') !== 'archived') {
        throw new RuntimeException('Only archived users can be restored.');
    }

    $pdo->prepare("
        UPDATE users
        SET account_status = 'active',
            archived_at = NULL,
            deactivated_at = NULL,
            suspended_until = NULL,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ")->execute([$userId]);
    super_admin_audit($pdo, 'user_restored', 'Restored archived user profile ' . $user['email'] . '.');
}

function super_admin_save_controls(PDO $pdo): void
{
    $allowed = array_keys(super_admin_control_settings());
    $stmt = $pdo->prepare("
        INSERT INTO settings (key_name, value) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE value = VALUES(value)
    ");
    foreach ($allowed as $key) {
        $stmt->execute([$key, trim((string) ($_POST[$key] ?? ''))]);
    }
    super_admin_audit($pdo, 'system_controls_updated', 'Updated system announcement/security/access controls.');
}

function super_admin_review_certificate_revocation(PDO $pdo): string
{
    admin_ensure_action_request_schema($pdo);
    $requestId = (int) ($_POST['request_id'] ?? 0);
    $decision = (string) ($_POST['decision'] ?? '');
    $note = trim((string) ($_POST['review_note'] ?? ''));
    if ($requestId <= 0 || !in_array($decision, ['approve', 'reject'], true)) {
        throw new RuntimeException('Select a valid revocation request and decision.');
    }

    $stmt = $pdo->prepare("SELECT ar.*, c.certificate_ref, c.status certificate_status FROM admin_action_requests ar LEFT JOIN certificates c ON c.id = ar.target_id WHERE ar.id = ? AND ar.request_type = 'revoke_certificate' AND ar.target_table = 'certificates' AND ar.status = 'pending' LIMIT 1");
    $stmt->execute([$requestId]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$request) {
        throw new RuntimeException('Revocation request was not found or has already been reviewed.');
    }

    $reviewedBy = $_SESSION['super_admin_user_id'] ?? admin_current_user_id($pdo);
    if ($decision === 'reject') {
        $pdo->prepare("UPDATE admin_action_requests SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW(), review_note = ? WHERE id = ?")
            ->execute([$reviewedBy, $note !== '' ? $note : 'Rejected by Super Admin.', $requestId]);
        super_admin_audit($pdo, 'certificate_revocation_rejected', 'Rejected certificate revocation request for ' . (string) ($request['certificate_ref'] ?? ('#' . $request['target_id'])) . '.');
        return 'Certificate revocation request rejected.';
    }

    $payload = json_decode((string) ($request['payload_json'] ?? ''), true);
    $reason = trim((string) ($payload['reason'] ?? $request['reason'] ?? 'Super Admin approved certificate revocation.'));

    $pdo->beginTransaction();
    try {
        $update = $pdo->prepare("UPDATE certificates SET status = 'revoked', revoked_at = NOW(), revoked_reason = ? WHERE id = ? AND status = 'issued'");
        $update->execute([$reason, (int) $request['target_id']]);
        if ($update->rowCount() !== 1) {
            throw new RuntimeException('Certificate is no longer issued or could not be revoked.');
        }
        $pdo->prepare("UPDATE admin_action_requests SET status = 'approved', reviewed_by = ?, reviewed_at = NOW(), review_note = ? WHERE id = ?")
            ->execute([$reviewedBy, $note !== '' ? $note : 'Approved by Super Admin.', $requestId]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    super_admin_audit($pdo, 'certificate_revoked', 'Approved and revoked certificate ' . (string) ($request['certificate_ref'] ?? ('#' . $request['target_id'])) . '.');
    return 'Certificate revoked after Super Admin approval.';
}

function super_admin_save_access_controls(PDO $pdo, array $roles): void
{
    $features = array_keys(super_admin_feature_catalog());
    $submitted = $_POST['access'] ?? [];
    $submittedRoles = $_POST['access_roles'] ?? [];
    if (!is_array($submitted)) {
        $submitted = [];
    }
    if (!is_array($submittedRoles)) {
        $submittedRoles = [];
    }
    $submittedRoles = array_values(array_intersect(array_keys($roles), array_map('strval', $submittedRoles)));
    if (!$submittedRoles) {
        $submittedRoles = array_keys($roles);
    }

    $stmt = $pdo->prepare("
        INSERT INTO settings (key_name, value) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE value = VALUES(value)
    ");

    foreach ($submittedRoles as $role) {
        $selected = $submitted[$role] ?? [];
        if (!is_array($selected)) {
            $selected = [];
        }
        $selected = array_values(array_intersect($features, array_map('strval', $selected)));
        $stmt->execute(['access_matrix_' . $role, implode(',', $selected)]);
    }

    $stmt->execute(['access_matrix_catalog_version', ADMIN_ACCESS_CATALOG_VERSION]);
    super_admin_audit($pdo, 'access_controls_updated', 'Updated role feature access matrix.');
}

function super_admin_save_module_settings(PDO $pdo): void
{
    $catalog = super_admin_module_catalog();
    $submitted = $_POST['modules'] ?? [];
    if (!is_array($submitted)) {
        $submitted = [];
    }

    $stmt = $pdo->prepare("
        INSERT INTO settings (key_name, value) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE value = VALUES(value)
    ");

    foreach ($catalog as $feature => $module) {
        $data = is_array($submitted[$feature] ?? null) ? $submitted[$feature] : [];
        $mode = in_array((string) ($data['mode'] ?? $module['mode']), ['active', 'pilot', 'setup', 'paused'], true)
            ? (string) ($data['mode'] ?? $module['mode'])
            : (string) $module['mode'];
        $owner = trim((string) ($data['owner'] ?? $module['owner']));
        $notes = trim((string) ($data['notes'] ?? $module['setup']));

        $stmt->execute(['module_' . $feature . '_mode', $mode]);
        $stmt->execute(['module_' . $feature . '_owner', $owner !== '' ? $owner : (string) $module['owner']]);
        $stmt->execute(['module_' . $feature . '_notes', $notes !== '' ? $notes : (string) $module['setup']]);
    }

    super_admin_audit($pdo, 'module_settings_updated', 'Updated module setup, owners, modes, and operating notes.');
}

function super_admin_save_training_onboarding(PDO $pdo): void
{
    $allowed = array_keys(super_admin_training_settings());
    $stmt = $pdo->prepare("
        INSERT INTO settings (key_name, value) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE value = VALUES(value)
    ");
    foreach ($allowed as $key) {
        $stmt->execute([$key, trim((string) ($_POST[$key] ?? ''))]);
    }
    super_admin_audit($pdo, 'training_onboarding_updated', 'Updated training and onboarding governance settings.');
}

function super_admin_create_announcement(PDO $pdo, array $roles): void
{
    $title = trim((string) ($_POST['title'] ?? ''));
    $body = trim((string) ($_POST['body'] ?? ''));
    $audience = trim((string) ($_POST['audience_role'] ?? 'all'));
    $validAudiences = array_merge(['all'], array_keys($roles));

    if ($title === '' || $body === '') {
        throw new RuntimeException('Announcement title and message are required.');
    }
    if (!in_array($audience, $validAudiences, true)) {
        throw new RuntimeException('Invalid announcement audience.');
    }

    $stmt = $pdo->prepare("
        INSERT INTO system_announcements (title, body, audience_role, is_active, created_by)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$title, $body, $audience, isset($_POST['is_active']) ? 1 : 0, $_SESSION['super_admin_user_id'] ?? null]);
    super_admin_audit($pdo, 'announcement_created', "Created announcement: {$title}.");
}

function super_admin_toggle_announcement(PDO $pdo): void
{
    $id = (int) ($_POST['announcement_id'] ?? 0);
    if ($id <= 0) {
        throw new RuntimeException('Announcement not found.');
    }
    $pdo->prepare("UPDATE system_announcements SET is_active = 1 - is_active WHERE id = ?")->execute([$id]);
    super_admin_audit($pdo, 'announcement_toggled', 'Toggled announcement #' . $id . '.');
}

function super_admin_save_dr_settings(PDO $pdo): void
{
    $allowed = array_keys(dr_default_settings());
    foreach ($allowed as $key) {
        if ($key === 'dr_site_id') {
            $value = preg_replace('/[^a-zA-Z0-9_.-]/', '', (string) ($_POST[$key] ?? ''));
        } elseif ($key === 'dr_backup_retention_days') {
            $value = (string) max(1, min(3650, (int) ($_POST[$key] ?? 30)));
        } else {
            $value = trim((string) ($_POST[$key] ?? ''));
        }
        dr_save_setting($pdo, $key, $value);
    }
    super_admin_audit($pdo, 'dr_settings_updated', 'Updated disaster recovery and multisite settings.');
}

function super_admin_add_site_node(PDO $pdo): string
{
    $nodeKey = preg_replace('/[^a-zA-Z0-9_.-]/', '', (string) ($_POST['node_key'] ?? ''));
    $name = trim((string) ($_POST['name'] ?? ''));
    $baseUrl = rtrim(trim((string) ($_POST['base_url'] ?? '')), '/');
    $role = in_array((string) ($_POST['node_role'] ?? 'replica'), ['primary', 'replica', 'standby', 'reporting'], true)
        ? (string) $_POST['node_role']
        : 'replica';

    if ($nodeKey === '' || $name === '' || !filter_var($baseUrl, FILTER_VALIDATE_URL)) {
        throw new RuntimeException('Node key, name, and valid base URL are required.');
    }

    $secret = dr_generate_shared_secret();
    $stmt = $pdo->prepare("
        INSERT INTO site_nodes (node_key, name, base_url, node_role, status, sync_enabled, shared_secret_hash)
        VALUES (?, ?, ?, ?, 'active', 1, ?)
        ON DUPLICATE KEY UPDATE
            name = VALUES(name),
            base_url = VALUES(base_url),
            node_role = VALUES(node_role),
            status = 'active',
            sync_enabled = 1,
            shared_secret_hash = VALUES(shared_secret_hash)
    ");
    $stmt->execute([$nodeKey, $name, $baseUrl, $role, password_hash($secret, PASSWORD_DEFAULT)]);
    super_admin_audit($pdo, 'site_node_saved', "Added or rotated sync token for site node {$nodeKey}.");
    return $secret;
}

function super_admin_update_site_node(PDO $pdo): void
{
    $id = (int) ($_POST['node_id'] ?? 0);
    $status = in_array((string) ($_POST['status'] ?? 'active'), ['active', 'paused', 'disabled'], true)
        ? (string) $_POST['status']
        : 'active';
    $syncEnabled = isset($_POST['sync_enabled']) ? 1 : 0;
    if ($id <= 0) {
        throw new RuntimeException('Site node not found.');
    }
    $pdo->prepare("UPDATE site_nodes SET status = ?, sync_enabled = ? WHERE id = ?")->execute([$status, $syncEnabled, $id]);
    super_admin_audit($pdo, 'site_node_updated', 'Updated site node #' . $id . '.');
}

function super_admin_user_filters(string $search, string $roleFilter, string $statusFilter): array
{
    $where = [];
    $params = [];
    if ($search !== '') {
        $where[] = '(name LIKE ? OR email LIKE ? OR phone LIKE ?)';
        $needle = '%' . $search . '%';
        array_push($params, $needle, $needle, $needle);
    }
    if ($roleFilter !== '') {
        if ($roleFilter === 'super_admin') {
            $where[] = 'is_super_admin = 1';
        } elseif (in_array($roleFilter, ['grower', 'field_agent', 'admin', 'investor'], true)) {
            $where[] = "(platform_role = ? OR ((platform_role IS NULL OR platform_role = '') AND role = ?))";
            array_push($params, $roleFilter, $roleFilter);
        } else {
            $where[] = 'platform_role = ?';
            $params[] = $roleFilter;
        }
    }
    if ($statusFilter !== '') {
        $where[] = 'account_status = ?';
        $params[] = $statusFilter;
    }

    return [$where ? 'WHERE ' . implode(' AND ', $where) : '', $params];
}

function super_admin_stats(PDO $pdo): array
{
    $total = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $privileged = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' OR is_super_admin = 1 OR platform_role IN ('super_admin','national_coordinator','state_coordinator','investor','admin')")->fetchColumn();
    $super = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE is_super_admin = 1")->fetchColumn();
    $suspended = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE account_status = 'suspended'")->fetchColumn();
    $archived = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE account_status = 'archived'")->fetchColumn();

    return ['total_users' => $total, 'privileged' => $privileged, 'super_admins' => $super, 'suspended' => $suspended, 'archived' => $archived];
}

function super_admin_role_summary(PDO $pdo, array $roles): array
{
    $summary = [];
    foreach ($roles as $role => $label) {
        $summary[$role] = ['label' => $label, 'total' => 0];
    }

    $rows = $pdo->query("
        SELECT role, platform_role, is_super_admin, is_agronomist, is_extensionist, COUNT(*) AS total
        FROM users
        GROUP BY role, platform_role, is_super_admin, is_agronomist, is_extensionist
    ")->fetchAll();

    foreach ($rows as $row) {
        $key = super_admin_platform_role_from_user($row);
        if (!isset($summary[$key])) {
            $summary[$key] = ['label' => ucwords(str_replace('_', ' ', $key)), 'total' => 0];
        }
        $summary[$key]['total'] += (int) $row['total'];
    }

    uasort($summary, static fn (array $a, array $b): int => $b['total'] <=> $a['total']);
    return $summary;
}

function super_admin_audit(PDO $pdo, string $action, string $description): void
{
    if (function_exists('admin_audit')) {
        admin_audit($pdo, $action, $description);
        return;
    }
    if (!app_table_exists($pdo, 'audit_log')) {
        return;
    }
    $stmt = $pdo->prepare("INSERT INTO audit_log (action, description, ip_address) VALUES (?, ?, ?)");
    $stmt->execute([$action, $description, $_SERVER['REMOTE_ADDR'] ?? null]);
}

function super_admin_export_users(PDO $pdo): void
{
    if (admin_setting($pdo, 'access_user_export_enabled', '1') !== '1') {
        http_response_code(403);
        echo 'User export is disabled.';
        exit;
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="natcodev-users-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, app_csv_row(['id', 'name', 'email', 'phone', 'role', 'platform_role', 'account_status', 'is_super_admin', 'profile_verified', 'two_factor_required', 'created_at']));
    $rows = $pdo->query("SELECT id, name, email, phone, role, platform_role, account_status, is_super_admin, profile_verified, two_factor_required, created_at FROM users ORDER BY id");
    foreach ($rows ?: [] as $row) {
        fputcsv($out, app_csv_row(array_values($row)));
    }
    super_admin_audit($pdo, 'users_exported', 'Exported users CSV from Super Admin console.');
    fclose($out);
    exit;
}
