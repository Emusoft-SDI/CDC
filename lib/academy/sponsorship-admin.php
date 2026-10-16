<?php
declare(strict_types=1);

require_once __DIR__ . '/sponsorships.php';

function academy_admin_sponsorships_list(PDO $pdo, int $limit = 100): array
{
    $stmt = $pdo->prepare("SELECT * FROM academy_sponsorships ORDER BY id DESC LIMIT ?");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function academy_admin_recommendations_list(PDO $pdo, int $limit = 100): array
{
    $stmt = $pdo->prepare("SELECT * FROM academy_sponsorship_recommendations ORDER BY id DESC LIMIT ?");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function academy_admin_recommendation_update_status(PDO $pdo, int $id, string $status, int $reviewerId): bool
{
    $stmt = $pdo->prepare("UPDATE academy_sponsorship_recommendations SET status=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?");
    return $stmt->execute([$status, $reviewerId, $id]);
}

function academy_admin_sponsorship_update_status(PDO $pdo, int $id, string $status, int $updatedBy): bool
{
    $stmt = $pdo->prepare("UPDATE academy_sponsorships SET status=?, updated_at=NOW() WHERE id=?");
    return $stmt->execute([$status, $id]);
}
