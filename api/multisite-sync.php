<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/disaster-recovery.php';

$pdo = db();
dr_ensure_schema($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'POST required'], 405);
}

$nodeKey = trim((string) ($_SERVER['HTTP_X_NATCODEV_NODE'] ?? ''));
$token = trim((string) ($_SERVER['HTTP_X_NATCODEV_SYNC_TOKEN'] ?? ''));
if ($nodeKey === '' || $token === '' || !dr_verify_node_token($pdo, $nodeKey, $token)) {
    json_response(['success' => false, 'error' => 'Unauthorized sync node'], 401);
}

$raw = file_get_contents('php://input') ?: '';
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    json_response(['success' => false, 'error' => 'Invalid JSON payload'], 400);
}

$eventUuid = trim((string) ($payload['event_uuid'] ?? ''));
if ($eventUuid === '') {
    $eventUuid = 'IN-' . date('ymd-His') . '-' . strtoupper(bin2hex(random_bytes(4)));
}
$eventType = preg_replace('/[^a-zA-Z0-9_.-]/', '', (string) ($payload['event_type'] ?? 'health_ping')) ?: 'health_ping';

try {
    $stmt = $pdo->prepare("
        INSERT INTO sync_events
            (event_uuid, direction, event_type, entity_table, entity_id, payload_json, source_node, target_node, status, processed_at)
        VALUES (?, 'inbound', ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            attempts = attempts + 1,
            payload_json = VALUES(payload_json),
            status = VALUES(status),
            processed_at = VALUES(processed_at)
    ");
    $status = $eventType === 'health_ping' ? 'processed' : 'pending';
    $processedAt = $eventType === 'health_ping' ? date('Y-m-d H:i:s') : null;
    $stmt->execute([
        $eventUuid,
        $eventType,
        isset($payload['entity_table']) ? (string) $payload['entity_table'] : null,
        isset($payload['entity_id']) ? (string) $payload['entity_id'] : null,
        json_encode($payload, JSON_UNESCAPED_SLASHES),
        $nodeKey,
        dr_settings($pdo)['dr_site_id'],
        $status,
        $processedAt,
    ]);

    $update = $pdo->prepare("UPDATE site_nodes SET last_seen_at = NOW(), last_error = NULL WHERE node_key = ?");
    $update->execute([$nodeKey]);
    json_response(['success' => true, 'event_uuid' => $eventUuid, 'status' => $status]);
} catch (Throwable $e) {
    $pdo->prepare("UPDATE site_nodes SET last_error = ? WHERE node_key = ?")->execute([$e->getMessage(), $nodeKey]);
    json_response(['success' => false, 'error' => 'Sync event rejected'], 500);
}
