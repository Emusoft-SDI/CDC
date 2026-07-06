<?php

function admin_staff_role_to_auth_role(string $staffType): string
{
    if ($staffType === 'admin') {
        return 'admin';
    }
    if ($staffType === 'grower') {
        return 'grower';
    }

    return 'field_agent';
}


function admin_upsert_staff_profile(PDO $pdo, int $userId, string $staffType, array $data = []): void
{
    if ($staffType === 'grower' || $userId <= 0) {
        return;
    }

    $stmt = $pdo->prepare("
        INSERT INTO staff_profiles
            (user_id, staff_type, state, lga, qualification, license_number, experience_years, certification_status, training_program, availability, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            staff_type = VALUES(staff_type),
            state = VALUES(state),
            lga = VALUES(lga),
            qualification = VALUES(qualification),
            license_number = VALUES(license_number),
            experience_years = VALUES(experience_years),
            certification_status = VALUES(certification_status),
            training_program = VALUES(training_program),
            availability = VALUES(availability),
            status = VALUES(status)
    ");
    $stmt->execute([
        $userId,
        $staffType,
        $data['state'] ?? null,
        $data['lga'] ?? null,
        $data['qualification'] ?? null,
        $data['license_number'] ?? null,
        (float) ($data['experience_years'] ?? 0),
        $data['certification_status'] ?? 'not_started',
        $data['training_program'] ?? null,
        $data['availability'] ?? null,
        $data['status'] ?? 'active',
    ]);
}


function admin_display_staff_type(array $user): string
{
    if (!empty($user['staff_type'])) {
        return (string) $user['staff_type'];
    }
    if (($user['role'] ?? '') === 'field_agent' && (int) ($user['is_agronomist'] ?? 0) === 1) {
        return 'agronomist';
    }
    if (($user['role'] ?? '') === 'field_agent' && (int) ($user['is_extensionist'] ?? 0) === 1) {
        return 'extensionist';
    }
    return (string) ($user['role'] ?? 'grower');
}


function admin_active_role_assignments(PDO $pdo, int $userId): array
{
    if ($userId <= 0 || !app_table_exists($pdo, 'user_role_assignments')) {
        return [];
    }
    $stmt = $pdo->prepare("
        SELECT *
        FROM user_role_assignments
        WHERE user_id = ? AND status = 'active'
        ORDER BY assigned_at DESC, id DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}


function admin_user_has_assigned_role(PDO $pdo, int $userId, string $roleKey): bool
{
    if ($userId <= 0 || $roleKey === '' || !app_table_exists($pdo, 'user_role_assignments')) {
        return false;
    }
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM user_role_assignments
        WHERE user_id = ? AND role_key = ? AND status = 'active'
    ");
    $stmt->execute([$userId, $roleKey]);
    return (int) $stmt->fetchColumn() > 0;
}


function admin_highest_assigned_platform_role(PDO $pdo, int $userId): ?string
{
    $assignments = admin_active_role_assignments($pdo, $userId);
    if (!$assignments) {
        return null;
    }
    $priority = [
        'super_admin' => 100,
        'national_coordinator' => 90,
        'state_coordinator' => 80,
        'admin' => 70,
        'support_agent' => 65,
        'agronomist' => 60,
        'agric_extensionist' => 55,
        'field_agent' => 50,
        'investor' => 40,
        'provider' => 35,
        'grower' => 10,
    ];
    usort($assignments, static fn (array $a, array $b): int => ($priority[(string) $b['role_key']] ?? 0) <=> ($priority[(string) $a['role_key']] ?? 0));
    return (string) $assignments[0]['role_key'];
}


function admin_current_platform_role(PDO $pdo): ?string
{
    if (($_SESSION['super_admin_authenticated'] ?? false) === true) {
        return 'super_admin';
    }

    $user = current_user($pdo);
    if (($_SESSION['admin_authenticated'] ?? false) === true || ($_SESSION['admin'] ?? false) === true) {
        if ($user && (int) ($user['is_super_admin'] ?? 0) === 1) {
            return 'super_admin';
        }
        if (!$user || !in_array((string) ($user['role'] ?? ''), ['admin', 'field_agent'], true)) {
            return 'admin';
        }
    }
    if (!$user) {
        return null;
    }
    if ((int) ($user['is_super_admin'] ?? 0) === 1) {
        return 'super_admin';
    }
    if (!empty($user['platform_role']) && (string) $user['platform_role'] !== 'grower') {
        return (string) $user['platform_role'];
    }
    $assignedRole = admin_highest_assigned_platform_role($pdo, (int) $user['id']);
    if ($assignedRole !== null && $assignedRole !== 'grower') {
        return $assignedRole;
    }

    return (string) ($user['role'] ?? 'grower');
}

