<?php
declare(strict_types=1);

function super_admin_logout(): void
{
    unset(
        $_SESSION['super_admin_authenticated'],
        $_SESSION['super_admin_user_id'],
        $_SESSION['super_admin_login_audited'],
        $_SESSION['super_admin_schema_version'],
        $_SESSION['login_otp_pending'],
        $_SESSION['login_otp_user_id'],
        $_SESSION['login_otp_email'],
        $_SESSION['otp_next_destination']
    );
    redirect_to('index.php');
}

function super_admin_current_user(PDO $pdo): ?array
{
    $userId = (int) ($_SESSION['super_admin_user_id'] ?? $_SESSION['user_id'] ?? 0);
    if ($userId <= 0) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? AND is_super_admin = 1 LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    return is_array($user) ? $user : null;
}

function super_admin_save_self_profile(PDO $pdo): void
{
    $user = super_admin_current_user($pdo);
    if (!$user) {
        throw new RuntimeException('A user-backed super admin account is required to update this profile.');
    }
    workspace_account_update_profile($pdo, (int) $user['id'], $_POST);
    super_admin_audit($pdo, 'super_admin_profile_updated', 'Updated own super admin profile.');
}

function super_admin_change_self_password(PDO $pdo): void
{
    $user = super_admin_current_user($pdo);
    if (!$user) {
        throw new RuntimeException('A user-backed super admin account is required to change this password.');
    }
    workspace_account_change_password(
        $pdo,
        (int) $user['id'],
        (string) ($_POST['current_password'] ?? ''),
        (string) ($_POST['new_password'] ?? ''),
        (string) ($_POST['confirm_password'] ?? '')
    );
    super_admin_audit($pdo, 'super_admin_password_changed', 'Changed own super admin password.');
}

function super_admin_password_is_valid(string $password): bool
{
    $hash = app_env('SUPER_ADMIN_PASSWORD_HASH');
    if ($hash) {
        return password_verify($password, $hash);
    }

    if (app_is_production()) {
        error_log('SUPER_ADMIN_PASSWORD_HASH is required for production super admin login.');
        return false;
    }

    $plain = app_env('SUPER_ADMIN_PASSWORD', app_env('ADMIN_PASSWORD', ''));
    return $plain !== null && $plain !== '' && hash_equals($plain, $password);
}

function super_admin_is_authorized(PDO $pdo): bool
{
    if (!app_column_exists($pdo, 'users', 'is_super_admin')) {
        unset($_SESSION['super_admin_authenticated'], $_SESSION['super_admin_user_id']);
        return false;
    }

    $userId = (int) ($_SESSION['super_admin_user_id'] ?? $_SESSION['user_id'] ?? 0);
    if ($userId <= 0) {
        unset($_SESSION['super_admin_authenticated'], $_SESSION['super_admin_user_id']);
        return false;
    }

    $stmt = $pdo->prepare("SELECT id, is_super_admin, account_status FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if ($user && (int) $user['is_super_admin'] === 1 && strtolower((string) ($user['account_status'] ?? 'active')) === 'active') {
        $_SESSION['user_id'] = $userId;
        $_SESSION['super_admin_authenticated'] = true;
        $_SESSION['super_admin_user_id'] = $userId;
        return true;
    }

    unset($_SESSION['super_admin_authenticated'], $_SESSION['super_admin_user_id']);
    return false;
}

function super_admin_auth_role(string $platformRole): string
{
    if ($platformRole === 'investor') {
        return 'investor';
    }
    if ($platformRole === 'grower') {
        return 'grower';
    }
    if (in_array($platformRole, ['field_agent', 'agronomist', 'agric_extensionist'], true)) {
        return 'field_agent';
    }

    return 'admin';
}

function super_admin_platform_role_from_user(array $user): string
{
    if ((int) ($user['is_super_admin'] ?? 0) === 1) {
        return 'super_admin';
    }
    if (!empty($user['platform_role'])) {
        return (string) $user['platform_role'];
    }
    if (($user['role'] ?? '') === 'field_agent' && (int) ($user['is_agronomist'] ?? 0) === 1) {
        return 'agronomist';
    }
    if (($user['role'] ?? '') === 'field_agent' && (int) ($user['is_extensionist'] ?? 0) === 1) {
        return 'agric_extensionist';
    }

    return (string) ($user['role'] ?? 'grower');
}

function super_admin_temp_password(): string
{
    return 'NAT-' . strtoupper(bin2hex(random_bytes(3))) . '-' . random_int(100, 999);
}
