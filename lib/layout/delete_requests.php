<?php

function admin_queue_delete_request(PDO $pdo, string $targetTable, ?int $targetId, string $targetLabel, string $reason = '', array $payload = []): int
{
    admin_ensure_action_request_schema($pdo);
    $targetTable = preg_replace('/[^a-zA-Z0-9_]/', '', $targetTable);
    if ($targetTable === '') {
        throw new RuntimeException('Delete request target is invalid.');
    }
    $targetKey = isset($payload['target_key']) ? trim((string) $payload['target_key']) : null;
    $stmt = $pdo->prepare("
        INSERT INTO admin_action_requests
            (request_type, target_table, target_id, target_key, target_label, requested_by, reason, payload_json)
        VALUES ('delete', ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $targetTable,
        $targetId,
        $targetKey !== '' ? $targetKey : null,
        $targetLabel,
        admin_current_user_id($pdo),
        $reason !== '' ? $reason : 'Admin requested delete approval.',
        $payload ? json_encode($payload, JSON_UNESCAPED_SLASHES) : null,
    ]);
    return (int) $pdo->lastInsertId();
}


function admin_verified_delete_requires_super_approval(PDO $pdo, string $targetTable, ?int $targetId = null, ?string $targetKey = null): bool
{
    return (bool) admin_record_authenticity_status($pdo, $targetTable, $targetId, $targetKey)['requires_approval'];
}


function admin_queue_verified_delete_request(PDO $pdo, string $targetTable, ?int $targetId, string $targetLabel, string $reason = '', array $payload = []): int
{
    $targetKey = isset($payload['target_key']) ? (string) $payload['target_key'] : null;
    $auth = admin_record_authenticity_status($pdo, $targetTable, $targetId, $targetKey);
    if ($auth['requires_approval']) {
        $reason = trim($reason . ' Authenticity lock: ' . $auth['label'] . '=' . $auth['status'] . '.');
        $payload['authenticity_lock'] = $auth;
    }
    return admin_queue_delete_request($pdo, $targetTable, $targetId, $targetLabel, $reason, $payload);
}


function admin_pending_delete_request_count(PDO $pdo): int
{
    admin_ensure_action_request_schema($pdo);
    $generic = (int) $pdo->query("SELECT COUNT(*) FROM admin_action_requests WHERE request_type = 'delete' AND status = 'pending'")->fetchColumn();
    if (!app_table_exists($pdo, 'application_delete_requests')) {
        return $generic;
    }
    $applications = (int) $pdo->query("SELECT COUNT(*) FROM application_delete_requests WHERE status = 'pending'")->fetchColumn();
    return $generic + $applications;
}


function admin_snapshot_deleted_record(PDO $pdo, string $table, ?int $id, ?string $key, ?int $requestId = null): void
{
    try {
        if (!app_table_exists($pdo, 'admin_deleted_records') || !app_table_exists($pdo, $table)) {
            return;
        }
        $quoted = '`' . str_replace('`', '', $table) . '`';
        $row = null;
        if ($table === 'notification_templates' && $key !== null && $key !== '') {
            $stmt = $pdo->prepare("SELECT * FROM notification_templates WHERE template_name = ? LIMIT 1");
            $stmt->execute([$key]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } elseif ($id !== null && $id > 0) {
            $stmt = $pdo->prepare("SELECT * FROM {$quoted} WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        if (!$row) {
            return;
        }
        $pdo->prepare("
            INSERT INTO admin_deleted_records (target_table, target_id, target_key, record_json, deleted_by, delete_request_id)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([
            $table,
            $id ?: null,
            $key !== '' ? $key : null,
            json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            admin_current_user_id($pdo),
            $requestId ?: null,
        ]);
    } catch (Throwable $e) {
        error_log('Delete snapshot skipped: ' . $e->getMessage());
    }
}


function admin_execute_approved_delete(PDO $pdo, array $request): void
{
    $table = (string) ($request['target_table'] ?? '');
    $id = (int) ($request['target_id'] ?? 0);
    $payload = json_decode((string) ($request['payload_json'] ?? ''), true);
    $payload = is_array($payload) ? $payload : [];

    $requestId = (int) ($request['id'] ?? 0);
    if ($table !== 'provider_offerings') {
        // Keep a recoverable copy before any destructive delete.
        admin_snapshot_deleted_record($pdo, $table, $id ?: null, ($request['target_key'] ?? null) ?: null, $requestId ?: null);
    }

    if ($table === 'user_import_records') {
        $pdo->prepare('DELETE FROM user_import_records WHERE id = ?')->execute([$id]);
        return;
    }

    if ($table === 'notification_templates') {
        $templateName = (string) ($request['target_key'] ?? $payload['template_name'] ?? '');
        if ($templateName !== '') {
            $pdo->prepare('DELETE FROM notification_templates WHERE template_name = ?')->execute([$templateName]);
        }
        return;
    }

    if ($table === 'provider_offerings') {
        $pdo->prepare("UPDATE provider_offerings SET status = 'removed' WHERE id = ?")->execute([$id]);
        return;
    }

    if ($table === 'marketplace_listings') {
        $pdo->prepare('DELETE FROM marketplace_listings WHERE id = ?')->execute([$id]);
        return;
    }

    if ($table === 'marketplace_sellers') {
        $pdo->prepare('DELETE FROM marketplace_sellers WHERE id = ?')->execute([$id]);
        return;
    }

    if ($table === 'provider_registry') {
        $pdo->prepare('DELETE FROM provider_registry WHERE id = ?')->execute([$id]);
        return;
    }

    if ($table === 'staff_profiles') {
        $pdo->prepare('DELETE FROM staff_profiles WHERE id = ?')->execute([$id]);
        return;
    }

    if ($table === 'document_requirements') {
        $pdo->prepare('DELETE FROM document_requirements WHERE id = ?')->execute([$id]);
        return;
    }

    if ($table === 'grower_farms') {
        $pdo->prepare('DELETE FROM grower_farms WHERE id = ?')->execute([$id]);
        return;
    }

    if ($table === 'farm_verifications') {
        $pdo->prepare('DELETE FROM farm_verifications WHERE id = ?')->execute([$id]);
        return;
    }

    if ($table === 'certificates') {
        $pdo->prepare('DELETE FROM certificates WHERE id = ?')->execute([$id]);
        return;
    }

    if ($table === 'academy_certificates') {
        $pdo->prepare('DELETE FROM academy_certificates WHERE id = ?')->execute([$id]);
        return;
    }

    throw new RuntimeException('No approved delete handler is registered for ' . $table . '.');
}

