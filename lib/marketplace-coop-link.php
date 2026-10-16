<?php
declare(strict_types=1);

function marketplace_cooperative_link_seller(PDO $pdo, int $cooperativeId, int $sellerId): bool
{
    if ($cooperativeId <= 0 || $sellerId <= 0) return false;
    $stmt = $pdo->prepare("UPDATE marketplace_sellers SET cooperative_id=?, linked_at=NOW() WHERE id=? AND (cooperative_id IS NULL OR cooperative_id=0)");
    return $stmt->execute([$cooperativeId, $sellerId]);
}
