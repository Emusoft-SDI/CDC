<?php
declare(strict_types=1);

/**
 * Cooperative partnership workspace.
 *
 * A cooperative that partners with NATCODEV is managed by a
 * cooperative_admin account. Its members are ordinary platform
 * grower accounts linked to the cooperative through
 * cooperative_members (and denormalized onto users.cooperative_id
 * for fast queries). Members activate through the standard
 * grower engagement flow (confirmation link + temporary password).
 *
 * Loaded from config.php so the wizard, the import console, and
 * the cooperative workspace share the same helpers.
 */

function cooperative_ensure_schema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS cooperatives (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            registration_number VARCHAR(160) NULL,
            state_id INT NULL,
            lga VARCHAR(160) NULL,
            address TEXT NULL,
            contact_name VARCHAR(255) NULL,
            contact_phone VARCHAR(30) NULL,
            contact_email VARCHAR(255) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            created_by INT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_cooperative_registration (registration_number),
            INDEX idx_cooperative_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS cooperative_members (
            id INT AUTO_INCREMENT PRIMARY KEY,
            cooperative_id INT NOT NULL,
            user_id INT NOT NULL,
            role_in_cooperative VARCHAR(20) NOT NULL DEFAULT 'member',
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            joined_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            removed_at DATETIME NULL,
            UNIQUE KEY uniq_cooperative_user (cooperative_id, user_id),
            INDEX idx_cooperative_member_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    app_add_column_if_missing($pdo, 'users', 'cooperative_id', 'INT NULL');
    $indexes = $pdo->query("SHOW INDEXES FROM users WHERE Key_name = 'idx_users_cooperative'")->fetchAll();
    if (!$indexes) {
        try {
            $pdo->exec('ALTER TABLE users ADD INDEX idx_users_cooperative (cooperative_id)');
        } catch (Throwable $e) {
            error_log('Cooperative index creation skipped: ' . $e->getMessage());
        }
    }
}

/**
 * The cooperative the current user administers, if any.
 *
 * @return array<string, mixed>|null
 */
function cooperative_current(PDO $pdo): ?array
{
    $user = current_user($pdo);
    if (!$user) {
        return null;
    }
    $userId = (int) ($user['id'] ?? 0);
    if ($userId <= 0) {
        return null;
    }

    // A cooperative_admin whose account carries the link...
    $cooperativeId = (int) ($user['cooperative_id'] ?? 0);
    if ((string) ($user['platform_role'] ?? '') === 'cooperative_admin' && $cooperativeId > 0) {
        $stmt = $pdo->prepare('SELECT * FROM cooperatives WHERE id = ? LIMIT 1');
        $stmt->execute([$cooperativeId]);
        $row = $stmt->fetch();
        if (is_array($row)) {
            return $row;
        }
    }

    // ...or an explicit admin membership row.
    $stmt = $pdo->prepare("SELECT c.* FROM cooperative_members cm JOIN cooperatives c ON c.id = cm.cooperative_id
        WHERE cm.user_id = ? AND cm.role_in_cooperative = 'admin' AND cm.status = 'active' AND c.status = 'active' LIMIT 1");
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

/**
 * @return array<string, mixed>|null
 */
function cooperative_find(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM cooperatives WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function cooperative_find_by_registration(PDO $pdo, string $registrationNumber): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM cooperatives WHERE registration_number = ? LIMIT 1');
    $stmt->execute([trim($registrationNumber)]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

/**
 * Match or create the cooperative a member belongs to. Used by
 * imports that carry a cooperative name + registration number.
 *
 * @return array{cooperative: array<string, mixed>, created: bool}
 */
function cooperative_match_or_create(PDO $pdo, string $name, string $registrationNumber, string $state = '', string $lga = '', ?int $createdBy = null): array
{
    $registration = trim($registrationNumber);
    if ($registration !== '') {
        $existing = cooperative_find_by_registration($pdo, $registration);
        if ($existing !== null) {
            return ['cooperative' => $existing, 'created' => false];
        }
    }
    $byName = $pdo->prepare('SELECT * FROM cooperatives WHERE name = ? LIMIT 1');
    $byName->execute([trim($name)]);
    $row = $byName->fetch();
    if (is_array($row)) {
        return ['cooperative' => $row, 'created' => false];
    }

    $pdo->prepare("INSERT INTO cooperatives (name, registration_number, lga, contact_name, status, created_by)
        VALUES (?, ?, ?, ?, 'active', ?)")
        ->execute([trim($name), $registration !== '' ? $registration : null, $lga !== '' ? $lga : null, null, $createdBy]);
    $created = cooperative_find($pdo, (int) $pdo->lastInsertId());
    return ['cooperative' => $created ?: ['id' => 0, 'name' => $name], 'created' => true];
}

/**
 * @return array<int, array<string, mixed>>
 */
function cooperative_member_list(PDO $pdo, int $cooperativeId, string $search = '', string $status = '', int $limit = 50, int $offset = 0): array
{
    $where = ['cm.cooperative_id = ?'];
    $params = [$cooperativeId];
    if ($search !== '') {
        $where[] = "(u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }
    if ($status !== '') {
        $where[] = 'cm.status = ?';
        $params[] = $status;
    }
    $sql = "SELECT cm.*, u.name, u.email, u.phone, u.account_status, u.last_login_at,
                a.app_ref, a.member_type, a.state_id, a.lga_id, a.district, a.village
            FROM cooperative_members cm
            JOIN users u ON u.id = cm.user_id
            LEFT JOIN applications a ON a.id = u.application_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY cm.joined_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function cooperative_member_count(PDO $pdo, int $cooperativeId, string $status = 'active'): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM cooperative_members WHERE cooperative_id = ? AND status = ?');
    $stmt->execute([$cooperativeId, $status]);
    return (int) $stmt->fetchColumn();
}

/**
 * Link an existing (or newly engaged) member to the cooperative.
 * Returns the membership row.
 *
 * @return array{ok: bool, message: string, user_id: int}
 */
function cooperative_link_member(PDO $pdo, int $cooperativeId, int $userId, string $role = 'member'): array
{
    $cooperative = cooperative_find($pdo, $cooperativeId);
    if ($cooperative === null) {
        return ['ok' => false, 'message' => 'Cooperative not found.', 'user_id' => 0];
    }
    if ($userId <= 0) {
        return ['ok' => false, 'message' => 'Member account not found.', 'user_id' => 0];
    }

    $pdo->prepare("INSERT INTO cooperative_members (cooperative_id, user_id, role_in_cooperative, status, joined_at)
        VALUES (?, ?, ?, 'active', NOW())
        ON DUPLICATE KEY UPDATE status = 'active', role_in_cooperative = VALUES(role_in_cooperative), removed_at = NULL")
        ->execute([$cooperativeId, $userId, $role]);

    $pdo->prepare('UPDATE users SET cooperative_id = ? WHERE id = ?')->execute([$cooperativeId, $userId]);

    if (function_exists('admin_audit_event')) {
        admin_audit_event($pdo, 'cooperative_member_linked', 'cooperative_member', $userId, null, ['cooperative_id' => $cooperativeId], 'ok', 'Member linked to cooperative ' . $cooperative['name']);
    }
    return ['ok' => true, 'message' => 'Member linked to the cooperative.', 'user_id' => $userId];
}

function cooperative_member_remove(PDO $pdo, int $cooperativeId, int $userId): bool
{
    $stmt = $pdo->prepare("UPDATE cooperative_members SET status = 'removed', removed_at = NOW() WHERE cooperative_id = ? AND user_id = ? AND status = 'active'");
    $stmt->execute([$cooperativeId, $userId]);
    return $stmt->rowCount() > 0;
}

function cooperative_member_reinstate(PDO $pdo, int $cooperativeId, int $userId): bool
{
    $stmt = $pdo->prepare("UPDATE cooperative_members SET status = 'active', removed_at = NULL WHERE cooperative_id = ? AND user_id = ?");
    $stmt->execute([$cooperativeId, $userId]);
    return $stmt->rowCount() > 0;
}

/**
 * @return array<string, int>
 */
function cooperative_stats(PDO $pdo, int $cooperativeId): array
{
    $stats = [
        'members' => cooperative_member_count($pdo, $cooperativeId),
        'removed' => cooperative_member_count($pdo, $cooperativeId, 'removed'),
        'applications' => 0,
        'farms' => 0,
        'verified_farms' => 0,
    ];
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users u JOIN applications a ON a.id = u.application_id
        WHERE u.cooperative_id = ? AND u.account_status = 'active'");
    $stmt->execute([$cooperativeId]);
    $stats['applications'] = (int) $stmt->fetchColumn();

    $farms = $pdo->prepare("SELECT COUNT(*) FROM grower_farms gf JOIN users u ON u.id = gf.user_id
        WHERE u.cooperative_id = ? AND gf.deleted_at IS NULL");
    $farms->execute([$cooperativeId]);
    $stats['farms'] = (int) $farms->fetchColumn();

    return $stats;
}
