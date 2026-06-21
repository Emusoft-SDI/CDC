<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

function support_ensure_schema(PDO $pdo): void
{
    app_ensure_core_schema($pdo);

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS support_tickets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ticket_ref VARCHAR(60) NOT NULL UNIQUE,
            user_id INT NULL,
            requester_name VARCHAR(160) NOT NULL,
            requester_email VARCHAR(190) NOT NULL,
            requester_phone VARCHAR(40) NULL,
            requester_role VARCHAR(80) NOT NULL DEFAULT 'public',
            source VARCHAR(40) NOT NULL DEFAULT 'web',
            category VARCHAR(80) NOT NULL DEFAULT 'general',
            module VARCHAR(80) NOT NULL DEFAULT 'general',
            subject VARCHAR(190) NOT NULL,
            description TEXT NOT NULL,
            priority VARCHAR(20) NOT NULL DEFAULT 'medium',
            status VARCHAR(30) NOT NULL DEFAULT 'open',
            outcome VARCHAR(30) NULL,
            linked_record_type VARCHAR(60) NULL,
            linked_record_ref VARCHAR(120) NULL,
            assigned_team VARCHAR(80) NULL,
            assigned_admin_id INT NULL,
            sla_due_at DATETIME NULL,
            first_response_at DATETIME NULL,
            resolved_at DATETIME NULL,
            last_activity_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_support_user (user_id, last_activity_at),
            INDEX idx_support_status (status, priority, last_activity_at),
            INDEX idx_support_category (category, module)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'support_tickets');
    foreach ([
        'user_id' => 'INT NULL',
        'requester_phone' => 'VARCHAR(40) NULL',
        'requester_role' => "VARCHAR(80) NOT NULL DEFAULT 'public'",
        'source' => "VARCHAR(40) NOT NULL DEFAULT 'web'",
        'module' => "VARCHAR(80) NOT NULL DEFAULT 'general'",
        'outcome' => 'VARCHAR(30) NULL',
        'linked_record_type' => 'VARCHAR(60) NULL',
        'linked_record_ref' => 'VARCHAR(120) NULL',
        'assigned_team' => 'VARCHAR(80) NULL',
        'assigned_admin_id' => 'INT NULL',
        'sla_due_at' => 'DATETIME NULL',
        'first_response_at' => 'DATETIME NULL',
        'resolved_at' => 'DATETIME NULL',
        'last_activity_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
    ] as $column => $definition) {
        app_add_column_if_missing($pdo, 'support_tickets', $column, $definition);
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS support_ticket_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ticket_id INT NOT NULL,
            user_id INT NULL,
            admin_id INT NULL,
            author_name VARCHAR(160) NOT NULL,
            author_role VARCHAR(80) NOT NULL DEFAULT 'public',
            message TEXT NOT NULL,
            visibility VARCHAR(30) NOT NULL DEFAULT 'public',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_support_messages_ticket (ticket_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'support_ticket_messages');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS support_ticket_attachments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ticket_id INT NOT NULL,
            message_id INT NULL,
            original_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            mime_type VARCHAR(120) NULL,
            file_size INT NULL,
            uploaded_by INT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_support_attachments_ticket (ticket_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'support_ticket_attachments');
}

function support_categories(): array
{
    return [
        'payments' => ['label' => 'Payments & Refunds', 'module' => 'wallet', 'team' => 'Payments Team', 'icon' => 'fa-wallet'],
        'verification' => ['label' => 'Verification & Certificates', 'module' => 'certificates', 'team' => 'Verification Team', 'icon' => 'fa-id-card'],
        'account' => ['label' => 'Account & Access', 'module' => 'profile', 'team' => 'Account Team', 'icon' => 'fa-user-lock'],
        'academy' => ['label' => 'Academy & Learning', 'module' => 'academy', 'team' => 'Academy Team', 'icon' => 'fa-graduation-cap'],
        'marketplace' => ['label' => 'Marketplace & Orders', 'module' => 'marketplace', 'team' => 'Marketplace Team', 'icon' => 'fa-store'],
        'field' => ['label' => 'Field Visits & Farm Help', 'module' => 'field', 'team' => 'Field Operations', 'icon' => 'fa-map-location-dot'],
        'provider' => ['label' => 'Provider / Seller Support', 'module' => 'provider', 'team' => 'Provider Desk', 'icon' => 'fa-handshake-angle'],
        'technical' => ['label' => 'Technical & Bugs', 'module' => 'technical', 'team' => 'Technical Team', 'icon' => 'fa-gears'],
        'general' => ['label' => 'General Support', 'module' => 'general', 'team' => 'Support Desk', 'icon' => 'fa-headset'],
    ];
}

function support_priorities(): array
{
    return ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High'];
}

function support_statuses(): array
{
    return ['open' => 'Open', 'in_progress' => 'In Progress', 'waiting_on_user' => 'Waiting on User', 'escalated' => 'Escalated', 'resolved' => 'Resolved', 'rejected' => 'Rejected', 'closed' => 'Closed'];
}

function support_outcomes(): array
{
    return ['resolved' => 'Resolved', 'waiting_on_user' => 'Waiting on User', 'escalated' => 'Escalated', 'rejected' => 'Rejected'];
}

function support_role_key(?array $user): string
{
    if (!$user) {
        return 'public';
    }
    $platformRole = strtolower(trim((string) ($user['platform_role'] ?? '')));
    if ($platformRole !== '') {
        return $platformRole;
    }
    return strtolower(trim((string) ($user['role'] ?? 'user'))) ?: 'user';
}

function support_role_label(string $role): string
{
    return [
        'public' => 'Public Visitor',
        'learner' => 'Learner',
        'grower' => 'Grower',
        'buyer' => 'Buyer',
        'seller' => 'Seller',
        'provider' => 'Provider',
        'input_provider' => 'Input Provider',
        'service_provider' => 'Service Provider',
        'field_agent' => 'Field Agent',
        'agronomist' => 'Agronomist',
        'agric_extensionist' => 'Agric Extensionist',
        'state_coordinator' => 'State Coordinator',
        'national_coordinator' => 'National Coordinator',
        'admin' => 'Admin',
        'super_admin' => 'Super Admin',
    ][$role] ?? ucwords(str_replace('_', ' ', $role));
}

function support_ref(): string
{
    return 'TKT-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

function support_sla_due(string $priority): string
{
    $hours = $priority === 'high' ? 4 : ($priority === 'low' ? 72 : 24);
    return date('Y-m-d H:i:s', strtotime('+' . $hours . ' hours'));
}

function support_create_ticket(PDO $pdo, array $data, ?array $user = null): string
{
    support_ensure_schema($pdo);
    $categories = support_categories();
    $category = preg_replace('/[^a-z0-9_-]/i', '', (string) ($data['category'] ?? 'general')) ?: 'general';
    if (!isset($categories[$category])) {
        $category = 'general';
    }
    $priority = in_array((string) ($data['priority'] ?? 'medium'), array_keys(support_priorities()), true) ? (string) $data['priority'] : 'medium';
    $role = support_role_key($user);
    $name = trim((string) ($data['name'] ?? ($user['name'] ?? 'Public visitor')));
    $email = trim((string) ($data['email'] ?? ($user['email'] ?? 'support-request@natcodev.local')));
    $phone = trim((string) ($data['phone'] ?? '')) ?: null;
    $subject = trim((string) ($data['subject'] ?? 'Support request'));
    $description = trim((string) ($data['description'] ?? ''));
    if ($name === '' || $email === '' || $subject === '' || $description === '') {
        throw new RuntimeException('Name, email, subject, and description are required.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Enter a valid email address.');
    }
    $ref = support_ref();
    $module = (string) ($data['module'] ?? $categories[$category]['module']);
    $team = (string) $categories[$category]['team'];
    $linkedType = trim((string) ($data['linked_record_type'] ?? '')) ?: null;
    $linkedRef = trim((string) ($data['linked_record_ref'] ?? '')) ?: null;

    $stmt = $pdo->prepare("
        INSERT INTO support_tickets
            (ticket_ref, user_id, requester_name, requester_email, requester_phone, requester_role, source, category, module,
             subject, description, priority, status, linked_record_type, linked_record_ref, assigned_team, sla_due_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'open', ?, ?, ?, ?)
    ");
    $stmt->execute([
        $ref,
        $user ? (int) $user['id'] : null,
        $name,
        $email,
        $phone,
        $role,
        $user ? 'authenticated' : 'public',
        $category,
        $module,
        $subject,
        $description,
        $priority,
        $linkedType,
        $linkedRef,
        $team,
        support_sla_due($priority),
    ]);
    $ticketId = (int) $pdo->lastInsertId();
    support_add_message($pdo, $ticketId, $description, $user, false, 'public', $name, $role);
    return $ref;
}

function support_add_message(PDO $pdo, int $ticketId, string $message, ?array $actor = null, bool $admin = false, string $visibility = 'public', ?string $authorName = null, ?string $authorRole = null): void
{
    $message = trim($message);
    if ($message === '') {
        return;
    }
    $role = $authorRole ?? ($admin ? 'support_agent' : support_role_key($actor));
    $name = $authorName ?? (string) ($actor['name'] ?? ($admin ? 'NATCODEV Support' : 'Requester'));
    $stmt = $pdo->prepare("
        INSERT INTO support_ticket_messages (ticket_id, user_id, admin_id, author_name, author_role, message, visibility)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $ticketId,
        (!$admin && $actor) ? (int) $actor['id'] : null,
        ($admin && $actor) ? (int) $actor['id'] : null,
        $name,
        $role,
        $message,
        $visibility,
    ]);
    $pdo->prepare("UPDATE support_tickets SET last_activity_at = NOW() WHERE id = ?")->execute([$ticketId]);
}

function support_ticket_by_ref(PDO $pdo, string $ref): ?array
{
    support_ensure_schema($pdo);
    $stmt = $pdo->prepare("SELECT * FROM support_tickets WHERE ticket_ref = ? LIMIT 1");
    $stmt->execute([$ref]);
    $ticket = $stmt->fetch();
    return $ticket ?: null;
}

function support_ticket_messages(PDO $pdo, int $ticketId, bool $includeInternal = false): array
{
    $where = $includeInternal ? '' : "AND visibility <> 'internal'";
    $stmt = $pdo->prepare("SELECT * FROM support_ticket_messages WHERE ticket_id = ? {$where} ORDER BY created_at ASC, id ASC");
    $stmt->execute([$ticketId]);
    return $stmt->fetchAll();
}

function support_user_tickets(PDO $pdo, int $userId): array
{
    support_ensure_schema($pdo);
    $stmt = $pdo->prepare("SELECT * FROM support_tickets WHERE user_id = ? ORDER BY last_activity_at DESC LIMIT 100");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function support_badge_class(string $value): string
{
    return match ($value) {
        'resolved', 'closed', 'low' => 'ok',
        'in_progress', 'medium', 'waiting_on_user' => 'info',
        'high', 'escalated' => 'warn',
        'rejected' => 'bad',
        default => 'neutral',
    };
}
