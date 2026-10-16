<?php
declare(strict_types=1);

function wallet_ensure_entity_schema(PDO $pdo): void
{
    app_add_column_if_missing($pdo, 'wallets', 'entity_type', "ENUM('user','cooperative') NOT NULL DEFAULT 'user'");
    app_add_column_if_missing($pdo, 'wallets', 'entity_id', 'INT NOT NULL DEFAULT 0');
    app_add_index_if_missing($pdo, 'wallets', 'idx_wallets_entity', 'entity_type, entity_id');
    try {
        // Migrate existing wallets where entity_id is 0 and user_id matches
        $pdo->exec("UPDATE wallets SET entity_type='user', entity_id=user_id WHERE entity_id = 0 AND user_id > 0");
    } catch (Throwable $e) {}
}
