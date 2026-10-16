<?php
declare(strict_types=1);

/**
 * Enrollment applications.
 *
 * Village, district, LGA, and regional heads apply for coordinator
 * roles, and cooperatives apply for partnership, through the
 * registration wizard. Applications land here as `pending` rows;
 * operations reviews and approves or rejects them. Approval
 * activates the account, assigns the scoped role, and emails a
 * staff claim link (no password is ever emailed).
 *
 * Loaded from config.php so the wizard, the approval console, and
 * the coordination bootstrap share the same helpers.
 */

function enrollment_ensure_schema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS enrollment_applications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            applicant_type VARCHAR(20) NOT NULL,
            platform_role VARCHAR(60) NULL,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            phone VARCHAR(30) NULL,
            state VARCHAR(160) NULL,
            lga VARCHAR(160) NULL,
            district VARCHAR(160) NULL,
            village VARCHAR(160) NULL,
            cooperative_name VARCHAR(255) NULL,
            cooperative_registration_number VARCHAR(160) NULL,
            cooperative_members_count INT NULL,
            reason TEXT NULL,
            user_id INT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            reviewed_by INT NULL,
            reviewed_at DATETIME NULL,
            review_note TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_enrollment_status (status),
            INDEX idx_enrollment_email (email),
            INDEX idx_enrollment_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Coordinators scoped below state level keep their enclave on the
    // staff profile as well, mirroring how state coordinators use state.
    app_add_column_if_missing($pdo, 'staff_profiles', 'district', 'VARCHAR(160) NULL');
    app_add_column_if_missing($pdo, 'staff_profiles', 'village', 'VARCHAR(160) NULL');
}

/**
 * The RBAC scope a coordinator application carries: which
 * user_role_assignments row (role_key, scope_type, scope_value)
 * grants the applicant their enclave.
 *
 * @return array{scope_type: string, scope_value: string}|null
 */
function enrollment_coordinator_scope(array $application): ?array
{
    $tier = strtolower((string) ($application['platform_role'] ?? ''));
    $state = trim((string) ($application['state'] ?? ''));
    $lga = trim((string) ($application['lga'] ?? ''));
    $district = trim((string) ($application['district'] ?? ''));
    $village = trim((string) ($application['village'] ?? ''));

    return match ($tier) {
        'regional_coordinator' => $state !== '' ? ['scope_type' => 'state', 'scope_value' => $state] : null,
        'state_coordinator' => $state !== '' ? ['scope_type' => 'state', 'scope_value' => $state] : null,
        'lga_coordinator' => ($lga !== '' && $state !== '') ? ['scope_type' => 'lga', 'scope_value' => $lga] : null,
        'district_coordinator' => ($district !== '' && $lga !== '' && $state !== '') ? ['scope_type' => 'district', 'scope_value' => $district] : null,
        'village_coordinator' => ($village !== '' && $district !== '' && $lga !== '' && $state !== '') ? ['scope_type' => 'village', 'scope_value' => $village] : null,
        default => null,
    };
}

/**
 * @return array<string, mixed>|null
 */
function enrollment_application_find(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM enrollment_applications WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

/**
 * @return array<int, array<string, mixed>>
 */
function enrollment_application_list(PDO $pdo, string $applicantType = '', string $status = 'pending', string $search = '', int $limit = 50, int $offset = 0): array
{
    $where = [];
    $params = [];
    if ($applicantType !== '') {
        $where[] = 'applicant_type = ?';
        $params[] = $applicantType;
    }
    if ($status !== '') {
        $where[] = 'status = ?';
        $params[] = $status;
    }
    if ($search !== '') {
        $where[] = "(name LIKE ? OR email LIKE ? OR cooperative_name LIKE ?)";
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }
    $sql = 'SELECT * FROM enrollment_applications';
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY created_at DESC LIMIT ? OFFSET ?';
    $params[] = $limit;
    $params[] = $offset;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function enrollment_application_count(PDO $pdo, string $applicantType = '', string $status = 'pending', string $search = ''): int
{
    $where = [];
    $params = [];
    if ($applicantType !== '') {
        $where[] = 'applicant_type = ?';
        $params[] = $applicantType;
    }
    if ($status !== '') {
        $where[] = 'status = ?';
        $params[] = $status;
    }
    if ($search !== '') {
        $where[] = "(name LIKE ? OR email LIKE ? OR cooperative_name LIKE ?)";
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }
    $sql = 'SELECT COUNT(*) FROM enrollment_applications';
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

/**
 * Activate a coordinator application: activate the user, assign the
 * scoped role, write the staff profile, and email the claim link.
 *
 * @return array{ok: bool, message: string, claim_url: string}
 */
function enrollment_application_approve(PDO $pdo, int $id, int $adminUserId, string $note = ''): array
{
    if (!function_exists('admin_upsert_staff_profile')) {
        require_once __DIR__ . '/layout/roles.php';
    }
    if (!function_exists('staff_account_claim_begin')) {
        require_once __DIR__ . '/staff-claim.php';
    }

    $application = enrollment_application_find($pdo, $id);
    if ($application === null) {
        return ['ok' => false, 'message' => 'Enrollment application not found.', 'claim_url' => ''];
    }
    if ((string) ($application['status'] ?? '') !== 'pending') {
        return ['ok' => false, 'message' => 'This application has already been reviewed.', 'claim_url' => ''];
    }

    $applicantType = (string) $application['applicant_type'];
    $email = (string) $application['email'];

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $userId = (int) ($stmt->fetchColumn() ?: 0);

        if ($applicantType === 'coordinator') {
            $tier = (string) ($application['platform_role'] ?? '');
            $scope = enrollment_coordinator_scope($application);
            if ($tier === '' || $scope === null) {
                $pdo->rollBack();
                return ['ok' => false, 'message' => 'Application is missing the coordinator tier or its enclave assignment.', 'claim_url' => ''];
            }

            if ($userId > 0) {
                $pdo->prepare("UPDATE users SET platform_role = ?, account_status = 'active', email_verified_at = COALESCE(email_verified_at, NOW()) WHERE id = ?")
                    ->execute([$tier, $userId]);
            } else {
                $pdo->prepare("INSERT INTO users (name, email, phone, password, role, platform_role, account_status, email_verified_at, terms_accepted)
                    VALUES (?, ?, ?, ?, 'admin', ?, 'active', NOW(), 1)")
                    ->execute([
                        (string) $application['name'],
                        $email,
                        (string) ($application['phone'] ?? '') ?: null,
                        password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
                        $tier,
                    ]);
                $userId = (int) $pdo->lastInsertId();
            }

            $pdo->prepare("INSERT INTO user_role_assignments (user_id, role_key, scope_type, scope_value, status, notes, assigned_by)
                VALUES (?, ?, ?, ?, 'active', 'Enrollment application approved', ?)
                ON DUPLICATE KEY UPDATE status = 'active', revoked_at = NULL, scope_value = VALUES(scope_value), assigned_by = VALUES(assigned_by)")
                ->execute([$userId, $tier, $scope['scope_type'], $scope['scope_value'], $adminUserId]);

            admin_upsert_staff_profile($pdo, $userId, 'coordinator', [
                'state' => (string) ($application['state'] ?? '') ?: null,
                'lga' => (string) ($application['lga'] ?? '') ?: null,
                'district' => (string) ($application['district'] ?? '') ?: null,
                'village' => (string) ($application['village'] ?? '') ?: null,
            ]);
        } elseif ($applicantType === 'cooperative') {
            if ($userId > 0) {
                $pdo->prepare("UPDATE users SET platform_role = 'cooperative_admin', account_status = 'active', email_verified_at = COALESCE(email_verified_at, NOW()) WHERE id = ?")
                    ->execute([$userId]);
            } else {
                $pdo->prepare("INSERT INTO users (name, email, phone, password, role, platform_role, account_status, email_verified_at, terms_accepted)
                    VALUES (?, ?, ?, ?, 'grower', 'cooperative_admin', 'active', NOW(), 1)")
                    ->execute([
                        (string) $application['name'],
                        $email,
                        (string) ($application['phone'] ?? '') ?: null,
                        password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
                    ]);
                $userId = (int) $pdo->lastInsertId();
            }

            if (!function_exists('cooperative_ensure_schema')) {
                require_once __DIR__ . '/cooperatives.php';
            }
            cooperative_ensure_schema($pdo);

            $registration = trim((string) ($application['cooperative_registration_number'] ?? ''));
            $existing = $pdo->prepare('SELECT id FROM cooperatives WHERE registration_number = ? LIMIT 1');
            $existing->execute([$registration]);
            $cooperativeId = (int) ($existing->fetchColumn() ?: 0);
            if ($cooperativeId <= 0) {
                $pdo->prepare("INSERT INTO cooperatives (name, registration_number, state_id, lga, contact_name, contact_phone, contact_email, status, created_by)
                    VALUES (?, ?, NULL, ?, ?, ?, ?, 'active', ?)")
                    ->execute([
                        (string) ($application['cooperative_name'] ?? $application['name']),
                        $registration,
                        (string) ($application['lga'] ?? '') ?: null,
                        (string) $application['name'],
                        (string) ($application['phone'] ?? '') ?: null,
                        $email,
                        $adminUserId,
                    ]);
                $cooperativeId = (int) $pdo->lastInsertId();
            } else {
                $pdo->prepare("UPDATE cooperatives SET status = 'active' WHERE id = ?")->execute([$cooperativeId]);
            }

            $pdo->prepare("INSERT INTO cooperative_members (cooperative_id, user_id, role_in_cooperative, status, joined_at)
                VALUES (?, ?, 'admin', 'active', NOW())
                ON DUPLICATE KEY UPDATE role_in_cooperative = 'admin', status = 'active', removed_at = NULL")
                ->execute([$cooperativeId, $userId]);

            $pdo->prepare('UPDATE users SET cooperative_id = ? WHERE id = ?')->execute([$cooperativeId, $userId]);
        } else {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'Unknown applicant type.', 'claim_url' => ''];
        }

        $pdo->prepare('UPDATE enrollment_applications SET status = \'approved\', reviewed_by = ?, reviewed_at = NOW(), review_note = ? WHERE id = ?')
            ->execute([$adminUserId, $note !== '' ? $note : null, $id]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Enrollment approval failed: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'Approval failed: ' . $e->getMessage(), 'claim_url' => ''];
    }

    $claim = staff_account_claim_begin($pdo, $userId);
    if (function_exists('admin_audit_event')) {
        admin_audit_event($pdo, 'enrollment_application_approved', 'enrollment_application', $id, null, ['status' => 'approved', 'applicant_type' => $applicantType], 'ok', 'Enrollment application approved for ' . $email . ' (' . $applicantType . ')');
    }
    return [
        'ok' => true,
        'message' => $claim['ok'] ? 'Approved. Account claim link sent to ' . $email . '.' : 'Approved, but the claim email could not be delivered: ' . $claim['message'],
        'claim_url' => $claim['claim_url'],
    ];
}

function enrollment_application_reject(PDO $pdo, int $id, int $adminUserId, string $note = ''): array
{
    $application = enrollment_application_find($pdo, $id);
    if ($application === null) {
        return ['ok' => false, 'message' => 'Enrollment application not found.'];
    }
    if ((string) ($application['status'] ?? '') !== 'pending') {
        return ['ok' => false, 'message' => 'This application has already been reviewed.'];
    }

    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([(string) $application['email']]);
    $userId = (int) ($stmt->fetchColumn() ?: 0);

    $pdo->prepare('UPDATE enrollment_applications SET status = \'rejected\', reviewed_by = ?, reviewed_at = NOW(), review_note = ? WHERE id = ?')
        ->execute([$adminUserId, $note !== '' ? $note : null, $id]);

    if ($userId > 0) {
        // An unactivated applicant must not be able to sign in through
        // any other surface while their application is rejected.
        $pdo->prepare("UPDATE users SET account_status = 'deactivated', deactivated_at = NOW() WHERE id = ? AND account_status NOT IN ('active')")
            ->execute([$userId]);
    }

    if (function_exists('admin_audit_event')) {
        admin_audit_event($pdo, 'enrollment_application_rejected', 'enrollment_application', $id, null, ['status' => 'rejected'], 'ok', 'Enrollment application rejected for ' . $application['email']);
    }
    return ['ok' => true, 'message' => 'Application rejected and the applicant has been notified by their application status.'];
}

/**
 * Activate a coordinator/cooperative user from an enrollment application row.
 *
 * Used by auto-approve (wizard finalize) where there is no admin reviewer.
 * Callers are responsible for ensuring the application data is valid.
 *
 * @param array<string,mixed> $row the enrollment_applications row
 * @return array{ok: bool, message: string, claim_url: string}
 */
function enrollment_activate_user(PDO $pdo, array $row): array
{
    if (!function_exists('admin_upsert_staff_profile')) {
        require_once __DIR__ . '/layout/roles.php';
    }
    if (!function_exists('staff_account_claim_begin')) {
        require_once __DIR__ . '/staff-claim.php';
    }

    $applicantType = (string) $row['applicant_type'];
    $email = (string) $row['email'];

    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $userId = (int) ($stmt->fetchColumn() ?: 0);

    $pdo->beginTransaction();
    try {
        if ($applicantType === 'coordinator') {
            $tier = (string) ($row['platform_role'] ?? '');
            // Build a minimal scope array from the row's enclave data.
            $state = trim((string) ($row['state'] ?? ''));
            $lga = trim((string) ($row['lga'] ?? ''));
            $district = trim((string) ($row['district'] ?? ''));
            $village = trim((string) ($row['village'] ?? ''));

            $scopeType = match ($tier) {
                'regional_coordinator', 'state_coordinator' => $state !== '' ? 'state' : null,
                'lga_coordinator' => ($lga !== '' && $state !== '') ? 'lga' : null,
                'district_coordinator' => ($district !== '' && $lga !== '' && $state !== '') ? 'district' : null,
                'village_coordinator' => ($village !== '' && $district !== '' && $lga !== '' && $state !== '') ? 'village' : null,
                default => null,
            };
            $scopeValue = $scopeType === 'state' ? $state : ($scopeType === 'lga' ? $lga : ($scopeType === 'district' ? $district : $village));

            if ($tier === '' || $scopeType === null) {
                $pdo->rollBack();
                return ['ok' => false, 'message' => 'Coordinator application missing tier or enclave.', 'claim_url' => ''];
            }

            if ($userId > 0) {
                $pdo->prepare("UPDATE users SET platform_role = ?, account_status = 'active', email_verified_at = COALESCE(email_verified_at, NOW()) WHERE id = ?")
                    ->execute([$tier, $userId]);
            } else {
                $pdo->prepare("INSERT INTO users (name, email, phone, password, role, platform_role, account_status, email_verified_at, terms_accepted)
                    VALUES (?, ?, ?, ?, 'admin', ?, 'active', NOW(), 1)")
                    ->execute([
                        (string) $row['name'],
                        $email,
                        (string) ($row['phone'] ?? '') ?: null,
                        password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
                        $tier,
                    ]);
                $userId = (int) $pdo->lastInsertId();
            }

            // Upsert scoped role assignment (auto-approved).
            $pdo->prepare("INSERT INTO user_role_assignments (user_id, role_key, scope_type, scope_value, status, notes, assigned_by)
                VALUES (?, ?, ?, ?, 'active', 'Auto-approved enrollment', NULL)
                ON DUPLICATE KEY UPDATE status = 'active', revoked_at = NULL, scope_value = VALUES(scope_value)")
                ->execute([$userId, $tier, $scopeType, $scopeValue]);

            admin_upsert_staff_profile($pdo, $userId, 'coordinator', [
                'state' => $state ?: null,
                'lga' => $lga ?: null,
                'district' => $district ?: null,
                'village' => $village ?: null,
            ]);

        } elseif ($applicantType === 'cooperative') {
            if ($userId > 0) {
                $pdo->prepare("UPDATE users SET platform_role = 'cooperative_admin', account_status = 'active', email_verified_at = COALESCE(email_verified_at, NOW()) WHERE id = ?")
                    ->execute([$userId]);
            } else {
                $pdo->prepare("INSERT INTO users (name, email, phone, password, role, platform_role, account_status, email_verified_at, terms_accepted)
                    VALUES (?, ?, ?, ?, 'grower', 'cooperative_admin', 'active', NOW(), 1)")
                    ->execute([
                        (string) $row['name'],
                        $email,
                        (string) ($row['phone'] ?? '') ?: null,
                        password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
                    ]);
                $userId = (int) $pdo->lastInsertId();
            }

            if (!function_exists('cooperative_ensure_schema')) {
                require_once __DIR__ . '/cooperatives.php';
            }
            cooperative_ensure_schema($pdo);

            $registration = trim((string) ($row['cooperative_registration_number'] ?? ''));
            $existing = $pdo->prepare('SELECT id FROM cooperatives WHERE registration_number = ? LIMIT 1');
            $existing->execute([$registration]);
            $cooperativeId = (int) ($existing->fetchColumn() ?: 0);
            if ($cooperativeId <= 0) {
                $pdo->prepare("INSERT INTO cooperatives (name, registration_number, state_id, lga, contact_name, contact_phone, contact_email, status, created_by)
                    VALUES (?, ?, NULL, ?, ?, ?, ?, 'active', ?)")
                    ->execute([
                        (string) ($row['cooperative_name'] ?? $row['name']),
                        $registration,
                        (string) ($row['lga'] ?? '') ?: null,
                        (string) $row['name'],
                        (string) ($row['phone'] ?? '') ?: null,
                        $email,
                        null,
                    ]);
                $cooperativeId = (int) $pdo->lastInsertId();
            } else {
                $pdo->prepare("UPDATE cooperatives SET status = 'active' WHERE id = ?")->execute([$cooperativeId]);
            }

            $pdo->prepare("INSERT INTO cooperative_members (cooperative_id, user_id, role_in_cooperative, status, joined_at)
                VALUES (?, ?, 'admin', 'active', NOW())
                ON DUPLICATE KEY UPDATE role_in_cooperative = 'admin', status = 'active', removed_at = NULL")
                ->execute([$cooperativeId, $userId]);

            $pdo->prepare('UPDATE users SET cooperative_id = ? WHERE id = ?')->execute([$cooperativeId, $userId]);
        } else {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'Unknown applicant type.', 'claim_url' => ''];
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Enrollment auto-activation failed: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'Activation failed: ' . $e->getMessage(), 'claim_url' => ''];
    }

    $claim = staff_account_claim_begin($pdo, $userId);
    if (function_exists('admin_audit_event')) {
        admin_audit_event($pdo, 'enrollment_application_auto_approved', 'enrollment_application', $row['id'] ?? 0, null, ['status' => 'approved', 'method' => 'auto_approve', 'applicant_type' => $applicantType], 'ok', 'Auto-approved enrollment for ' . $email . ' (' . $applicantType . ')');
    }
    return [
        'ok' => true,
        'message' => $claim['ok'] ? 'Account activated. Claim link sent to ' . $email . '.' : 'Account activated, but the claim email could not be delivered: ' . $claim['message'],
        'claim_url' => $claim['claim_url'],
    ];
}
