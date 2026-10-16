<?php
declare(strict_types=1);

require_once __DIR__ . '/../coordination/_coordination.php';

function academy_sponsorship_ensure_schema(PDO $pdo): void
{
    // Tables are created in academy schema; this is a no-op guard
}

function academy_sponsorship_create(PDO $pdo, array $data): int
{
    $stmt = $pdo->prepare("
        INSERT INTO academy_sponsorships (
            sponsor_type, sponsor_cooperative_id, sponsor_user_id, sponsor_role_key,
            webinar_id, sponsor_scope_type, sponsor_state_id, sponsor_lga,
            sponsor_district, sponsor_village, target_cooperative_id, note, slots,
            status, created_by, expires_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?)
    ");
    $stmt->execute([
        $data['sponsor_type'] ?? 'coordinator',
        $data['sponsor_cooperative_id'] ?? null,
        $data['sponsor_user_id'] ?? null,
        $data['sponsor_role_key'] ?? null,
        $data['webinar_id'] ?? null,
        $data['sponsor_scope_type'] ?? 'cooperative',
        $data['sponsor_state_id'] ?? null,
        $data['sponsor_lga'] ?? null,
        $data['sponsor_district'] ?? null,
        $data['sponsor_village'] ?? null,
        $data['target_cooperative_id'] ?? null,
        $data['note'] ?? null,
        $data['slots'] ?? null,
        $data['created_by'] ?? 0,
        $data['expires_at'] ?? null,
    ]);
    return (int) $pdo->lastInsertId();
}

function academy_recommend_course(PDO $pdo, array $data): int
{
    $stmt = $pdo->prepare("
        INSERT INTO academy_sponsorship_recommendations (
            recommender_type, recommender_user_id, recommender_cooperative_id, recommender_role_key,
            webinar_id, recipient_scope_type, recipient_state_id, recipient_lga,
            recipient_district, recipient_village, recipient_cooperative_id, message, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
    ");
    $stmt->execute([
        $data['recommender_type'] ?? 'coordinator',
        $data['recommender_user_id'] ?? 0,
        $data['recommender_cooperative_id'] ?? null,
        $data['recommender_role_key'] ?? null,
        $data['webinar_id'] ?? 0,
        $data['recipient_scope_type'] ?? 'cooperative',
        $data['recipient_state_id'] ?? null,
        $data['recipient_lga'] ?? null,
        $data['recipient_district'] ?? null,
        $data['recipient_village'] ?? null,
        $data['recipient_cooperative_id'] ?? null,
        $data['message'] ?? null,
    ]);
    return (int) $pdo->lastInsertId();
}

function academy_course_list(PDO $pdo, array $filters = []): array
{
    $where = '1=1';
    $params = [];
    if (!empty($filters['status'])) {
        $where .= ' AND w.status = ?';
        $params[] = $filters['status'];
    }
    $sql = "SELECT w.id, w.title, w.description, w.category, w.status, w.delivery_type, w.price
            FROM webinars w
            WHERE $where
            ORDER BY w.status DESC, w.title ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
