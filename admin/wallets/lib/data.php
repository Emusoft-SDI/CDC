<?php
declare(strict_types=1);

function wallets_db_query(PDO $pdo, string $sql, array $params = []): array {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function wallets_db_scalar(PDO $pdo, string $sql, array $params = []): float {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (float) ($stmt->fetchColumn() ?: 0);
}

function wallets_db_execute(PDO $pdo, string $sql, array $params = []): bool {
    return $pdo->prepare($sql)->execute($params);
}
