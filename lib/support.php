<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

function support_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done || app_schema_flag_is_set($pdo, 'support_schema_ready', '20260628-fast')) {
        $done = true;
        return;
    }

    app_ensure_core_schema($pdo);

    try {
        $existing = $pdo->query("
            SELECT COUNT(*)
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME IN ('support_tickets','support_teams','support_ticket_messages','support_ticket_attachments')
        ")->fetchColumn();
        if ((int) $existing === 4) {
            app_schema_flag_set($pdo, 'support_schema_ready', '20260628-fast');
            $done = true;
            return;
        }
    } catch (Throwable $e) {
        // Fall through to the normal create path.
    }

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
            rating INT NULL,
            feedback_comment TEXT NULL,
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
        'rating' => 'INT NULL',
        'feedback_comment' => 'TEXT NULL',
        'last_activity_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
    ] as $column => $definition) {
        app_add_column_if_missing($pdo, 'support_tickets', $column, $definition);
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS support_teams (
            id INT AUTO_INCREMENT PRIMARY KEY,
            team_name VARCHAR(120) NOT NULL UNIQUE,
            module VARCHAR(80) NOT NULL DEFAULT 'general',
            description TEXT NULL,
            lead_admin_id INT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'active',
            created_by INT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_support_teams_status (status, module)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'support_teams');
    foreach ([
        'module' => "VARCHAR(80) NOT NULL DEFAULT 'general'",
        'description' => 'TEXT NULL',
        'lead_admin_id' => 'INT NULL',
        'status' => "VARCHAR(30) NOT NULL DEFAULT 'active'",
        'created_by' => 'INT NULL',
    ] as $column => $definition) {
        app_add_column_if_missing($pdo, 'support_teams', $column, $definition);
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

    // Idempotency check: deduplicate accidental double-clicks within 15 seconds
    $dedup = $pdo->prepare("
        SELECT ticket_ref 
        FROM support_tickets 
        WHERE requester_email = ? AND subject = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 15 SECOND)
        ORDER BY id DESC LIMIT 1
    ");
    $dedup->execute([$email, $subject]);
    $existingRef = $dedup->fetchColumn();
    if ($existingRef) {
        return (string) $existingRef;
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
    $messageId = support_add_message($pdo, $ticketId, $description, $user, false, 'public', $name, $role);

    // Process initial ticket attachments if provided
    $filesToProcess = $data['attachments'] ?? $data['files'] ?? ($_FILES['attachments'] ?? ($_FILES['attachment'] ?? null));
    if ($filesToProcess) {
        support_process_uploaded_files($pdo, $ticketId, $messageId > 0 ? $messageId : null, $filesToProcess, $user ? (int) $user['id'] : null);
    }

    // Dispatch ticket confirmation email
    $trackingUrl = app_base_url() . '/support/index.php?ticket=' . urlencode($ref) . '&email=' . urlencode($email);
    $mailSubject = "Support Ticket Received - {$ref}";
    $plain = "Dear {$name},\n\nWe have received your support ticket.\n\nTicket Reference: {$ref}\nSubject: {$subject}\nCategory: " . ($categories[$category]['label'] ?? $category) . "\nPriority: " . ucfirst($priority) . "\n\nYou can track updates and respond by visiting the following link:\n{$trackingUrl}\n\nThank you for reaching out.";
    $html = "<h3>Support Ticket Received</h3><p>Dear " . e($name) . ",</p><p>We have received your support ticket and our team is reviewing it.</p><table><tr><td><strong>Reference:</strong></td><td>" . e($ref) . "</td></tr><tr><td><strong>Subject:</strong></td><td>" . e($subject) . "</td></tr><tr><td><strong>Category:</strong></td><td>" . e($categories[$category]['label'] ?? $category) . "</td></tr><tr><td><strong>Priority:</strong></td><td>" . e(ucfirst($priority)) . "</td></tr></table><p>To track updates, add messages, or view response details, click the link below:</p><p><a href=\"" . e($trackingUrl) . "\" style=\"display:inline-block;padding:10px 20px;background:#075f2a;color:#fff;text-decoration:none;border-radius:5px;\">Track Ticket Progress</a></p><p>Thank you for choosing NATCODEV.</p>";
    
    app_send_mail($email, $mailSubject, $plain, $html);

    return $ref;
}

function support_add_message(PDO $pdo, int $ticketId, string $message, ?array $actor = null, bool $admin = false, string $visibility = 'public', ?string $authorName = null, ?string $authorRole = null): int
{
    $message = trim($message);
    if ($message === '') {
        return 0;
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
    $messageId = (int) $pdo->lastInsertId();
    $pdo->prepare("UPDATE support_tickets SET last_activity_at = NOW() WHERE id = ?")->execute([$ticketId]);
    return $messageId;
}

function support_upload_dir(): string
{
    $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'support';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $htaccess = $dir . DIRECTORY_SEPARATOR . '.htaccess';
    if (!file_exists($htaccess)) {
        $rule = "# Prevent direct PHP/script execution in support uploads\n"
              . "<FilesMatch \"(?i)\\.(php|php[0-9]?|phtml|phar|pl|py|jsp|asp|sh|cgi|exe|bat|cmd)$\">\n"
              . "    Order Deny,Allow\n"
              . "    Deny from all\n"
              . "</FilesMatch>\n"
              . "Options -Indexes -ExecCGI\n";
        @file_put_contents($htaccess, $rule);
    }
    return $dir;
}

function support_allowed_attachment_extensions(): array
{
    return ['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf', 'doc', 'docx', 'txt', 'csv', 'xls', 'xlsx'];
}

function support_format_bytes(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1048576) {
        return round($bytes / 1024, 1) . ' KB';
    }
    return round($bytes / 1048576, 2) . ' MB';
}

function support_save_attachment(PDO $pdo, int $ticketId, ?int $messageId, array $file, ?int $userId = null): array
{
    support_ensure_schema($pdo);
    $error = (int) ($file['error'] ?? UPLOAD_ERR_OK);
    if ($error !== UPLOAD_ERR_OK) {
        if ($error === UPLOAD_ERR_NO_FILE) {
            return [];
        }
        $msg = match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File exceeds maximum upload size (10 MB).',
            UPLOAD_ERR_PARTIAL => 'File upload was incomplete.',
            default => 'File upload failed (code ' . $error . ').',
        };
        throw new RuntimeException($msg);
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !file_exists($tmp)) {
        throw new RuntimeException('Temporary upload file missing.');
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0) {
        throw new RuntimeException('Attached file is empty.');
    }
    $maxBytes = 10 * 1024 * 1024; // 10MB
    if ($size > $maxBytes) {
        throw new RuntimeException('Attached file exceeds 10 MB limit.');
    }

    $originalName = trim(basename((string) ($file['name'] ?? 'attachment')));
    if ($originalName === '') {
        $originalName = 'attachment';
    }
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    $prohibited = ['php', 'phtml', 'phar', 'sh', 'bat', 'cmd', 'exe', 'cgi', 'pl', 'py', 'js', 'jar', 'vbs', 'com'];
    if (in_array($ext, $prohibited, true)) {
        throw new RuntimeException("Security violation: Files with extension .{$ext} are not allowed.");
    }

    $allowed = support_allowed_attachment_extensions();
    if (!in_array($ext, $allowed, true)) {
        throw new RuntimeException("Unsupported file type (.{$ext}). Allowed types: " . implode(', ', $allowed));
    }

    // Binary / header verification
    if ($ext === 'pdf') {
        $handle = @fopen($tmp, 'rb');
        $header = $handle ? (string) fread($handle, 4) : '';
        if ($handle) {
            fclose($handle);
        }
        if ($header !== '%PDF') {
            throw new RuntimeException('Invalid PDF file format.');
        }
    } elseif (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
        if (@getimagesize($tmp) === false) {
            throw new RuntimeException('File contains invalid or corrupted image data.');
        }
    }

    $mime = (string) ($file['type'] ?? '');
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $detected = finfo_file($finfo, $tmp);
            finfo_close($finfo);
            if ($detected) {
                $mime = (string) $detected;
            }
        }
    }

    $uploadDir = support_upload_dir();
    $safeName = 'att_' . $ticketId . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $destination = $uploadDir . DIRECTORY_SEPARATOR . $safeName;

    if (is_uploaded_file($tmp)) {
        if (!move_uploaded_file($tmp, $destination)) {
            throw new RuntimeException('Failed to save uploaded attachment.');
        }
    } else {
        if (!copy($tmp, $destination)) {
            throw new RuntimeException('Failed to save attachment file.');
        }
    }

    $relativePath = 'uploads/support/' . $safeName;
    $stmt = $pdo->prepare("
        INSERT INTO support_ticket_attachments
            (ticket_id, message_id, original_name, file_path, mime_type, file_size, uploaded_by)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $ticketId,
        $messageId,
        $originalName,
        $relativePath,
        $mime ?: 'application/octet-stream',
        $size,
        $userId,
    ]);

    $id = (int) $pdo->lastInsertId();
    return [
        'id' => $id,
        'ticket_id' => $ticketId,
        'message_id' => $messageId,
        'original_name' => $originalName,
        'file_path' => $relativePath,
        'mime_type' => $mime,
        'file_size' => $size,
        'uploaded_by' => $userId,
    ];
}

function support_process_uploaded_files(PDO $pdo, int $ticketId, ?int $messageId, array|string $fileField = 'attachments', ?int $userId = null): array
{
    $raw = is_string($fileField) ? ($_FILES[$fileField] ?? null) : $fileField;
    if (!$raw || !is_array($raw)) {
        return [];
    }

    $saved = [];

    // Case 1: Multi-file array from $_FILES (name => [...], tmp_name => [...])
    if (isset($raw['name']) && is_array($raw['name'])) {
        $count = count($raw['name']);
        for ($i = 0; $i < $count; $i++) {
            $err = (int) ($raw['error'][$i] ?? UPLOAD_ERR_NO_FILE);
            if ($err === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $fileItem = [
                'name' => $raw['name'][$i] ?? '',
                'type' => $raw['type'][$i] ?? '',
                'tmp_name' => $raw['tmp_name'][$i] ?? '',
                'error' => $err,
                'size' => (int) ($raw['size'][$i] ?? 0),
            ];
            $saved[] = support_save_attachment($pdo, $ticketId, $messageId, $fileItem, $userId);
        }
    }
    // Case 2: Single-file array from $_FILES (name => string, tmp_name => string)
    elseif (isset($raw['name']) && is_string($raw['name'])) {
        $err = (int) ($raw['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err !== UPLOAD_ERR_NO_FILE) {
            $saved[] = support_save_attachment($pdo, $ticketId, $messageId, $raw, $userId);
        }
    }
    // Case 3: List of file arrays [['name' => ..., 'tmp_name' => ...], ...]
    else {
        foreach ($raw as $item) {
            if (is_array($item) && isset($item['tmp_name'])) {
                $err = (int) ($item['error'] ?? UPLOAD_ERR_OK);
                if ($err !== UPLOAD_ERR_NO_FILE) {
                    $saved[] = support_save_attachment($pdo, $ticketId, $messageId, $item, $userId);
                }
            }
        }
    }

    return array_values(array_filter($saved));
}

function support_ticket_attachments(PDO $pdo, int $ticketId, ?int $messageId = null): array
{
    support_ensure_schema($pdo);
    if ($messageId !== null) {
        $stmt = $pdo->prepare("SELECT * FROM support_ticket_attachments WHERE ticket_id = ? AND message_id = ? ORDER BY id ASC");
        $stmt->execute([$ticketId, $messageId]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM support_ticket_attachments WHERE ticket_id = ? ORDER BY id ASC");
        $stmt->execute([$ticketId]);
    }
    return $stmt->fetchAll();
}

function support_messages_with_attachments(PDO $pdo, int $ticketId, bool $includeInternal = false): array
{
    $messages = support_ticket_messages($pdo, $ticketId, $includeInternal);
    $allAttachments = support_ticket_attachments($pdo, $ticketId);

    // Group attachments by message_id
    $byMessage = [];
    $unassignedAttachments = [];
    foreach ($allAttachments as $att) {
        $mId = (int) ($att['message_id'] ?? 0);
        if ($mId > 0) {
            $byMessage[$mId][] = $att;
        } else {
            $unassignedAttachments[] = $att;
        }
    }

    // Attach to each message
    foreach ($messages as $idx => &$msg) {
        $id = (int) $msg['id'];
        $atts = $byMessage[$id] ?? [];
        // Associate unassigned attachments with the first message
        if ($idx === 0 && !empty($unassignedAttachments)) {
            $atts = array_merge($unassignedAttachments, $atts);
        }
        $msg['attachments'] = $atts;
    }
    unset($msg);

    return $messages;
}

function support_attachment_by_id(PDO $pdo, int $id): ?array
{
    support_ensure_schema($pdo);
    $stmt = $pdo->prepare("
        SELECT a.*, t.ticket_ref, t.requester_name, t.requester_email, t.user_id as ticket_user_id
        FROM support_ticket_attachments a
        JOIN support_tickets t ON t.id = a.ticket_id
        WHERE a.id = ?
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function support_can_access_ticket(PDO $pdo, array $ticket, ?array $user, ?string $email = null, bool $isAdmin = false): bool
{
    if ($isAdmin) {
        return true;
    }
    if ($user) {
        if ((int) ($ticket['user_id'] ?? 0) === (int) $user['id']) {
            return true;
        }
        if (strcasecmp((string) ($ticket['requester_email'] ?? ''), (string) ($user['email'] ?? '')) === 0) {
            return true;
        }
        $role = strtolower((string) ($user['role'] ?? ''));
        $platformRole = strtolower((string) ($user['platform_role'] ?? ''));
        if (in_array($role, ['admin', 'super_admin', 'support'], true) || in_array($platformRole, ['admin', 'super_admin', 'support_agent'], true)) {
            return true;
        }
    }
    if ($email !== null && $email !== '' && strcasecmp((string) ($ticket['requester_email'] ?? ''), trim($email)) === 0) {
        return true;
    }
    return false;
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
