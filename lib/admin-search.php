<?php
declare(strict_types=1);

/**
 * Unified admin search index + saved views (Phase 4, additive).
 *
 * Populate admin_search_index from cron (or incremental hooks) and query it from
 * admin/search.php. The existing static catalog search keeps working as a fallback.
 */

function admin_ensure_search_schema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admin_search_index (
            id INT AUTO_INCREMENT PRIMARY KEY,
            entity_type VARCHAR(60) NOT NULL,
            entity_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            subtitle VARCHAR(255) NULL,
            url VARCHAR(255) NOT NULL,
            keywords TEXT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_search_entity (entity_type, entity_id),
            INDEX idx_search_title (title)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'admin_search_index');

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admin_saved_views (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            workspace VARCHAR(60) NOT NULL,
            name VARCHAR(120) NOT NULL,
            query_json TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_saved_view (user_id, workspace, name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'admin_saved_views');
}

function admin_search_upsert(PDO $pdo, string $type, int $id, string $title, string $subtitle, string $url, string $keywords = ''): void
{
    admin_ensure_search_schema($pdo);
    $pdo->prepare(
        'INSERT INTO admin_search_index (entity_type, entity_id, title, subtitle, url, keywords) VALUES (?, ?, ?, ?, ?, ?)'
        . ' ON DUPLICATE KEY UPDATE title = VALUES(title), subtitle = VALUES(subtitle), url = VALUES(url), keywords = VALUES(keywords)'
    )->execute([$type, $id, mb_substr($title, 0, 255), mb_substr($subtitle, 0, 255), $url, $keywords]);
}

/**
 * @return array<int,array<string,mixed>>
 */
function admin_search_query(PDO $pdo, string $query, int $limit = 20): array
{
    $query = trim($query);
    if ($query === '') {
        return [];
    }
    if (!app_table_exists($pdo, 'admin_search_index')) {
        return [];
    }
    $term = '%' . $query . '%';
    try {
        $stmt = $pdo->prepare(
            'SELECT entity_type, entity_id, title, subtitle, url FROM admin_search_index'
            . ' WHERE title LIKE ? OR subtitle LIKE ? OR keywords LIKE ?'
            . ' ORDER BY (title LIKE ?) DESC, updated_at DESC LIMIT ' . max(1, min(100, $limit))
        );
        $stmt->execute([$term, $term, $term, $query . '%']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}

/** @param array<string,mixed> $query */
function admin_save_view(PDO $pdo, int $userId, string $workspace, string $name, array $query): void
{
    admin_ensure_search_schema($pdo);
    $pdo->prepare(
        'INSERT INTO admin_saved_views (user_id, workspace, name, query_json) VALUES (?, ?, ?, ?)'
        . ' ON DUPLICATE KEY UPDATE query_json = VALUES(query_json)'
    )->execute([$userId, $workspace, mb_substr($name, 0, 120), json_encode($query, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
}

/** @return array<int,array<string,mixed>> */
function admin_saved_views(PDO $pdo, int $userId, string $workspace): array
{
    if (!app_table_exists($pdo, 'admin_saved_views')) {
        return [];
    }
    $stmt = $pdo->prepare('SELECT id, name, query_json FROM admin_saved_views WHERE user_id = ? AND workspace = ? ORDER BY name');
    $stmt->execute([$userId, $workspace]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Rebuild the search index for high-value entities. Shared by the cron worker and
 * the queue job handler (Phase 4/5). Returns the number of records indexed.
 */
function admin_search_reindex(PDO $pdo, int $limit = 3000): int
{
    admin_ensure_search_schema($pdo);
    $count = 0;

    if (app_table_exists($pdo, 'users')) {
        $rows = $pdo->query("SELECT id, name, email, role FROM users ORDER BY id DESC LIMIT " . max(1, $limit))->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            admin_search_upsert($pdo, 'user', (int) $row['id'], (string) $row['name'], (string) $row['email'], 'users.php?search=' . urlencode((string) $row['email']), (string) $row['role']);
            $count++;
        }
    }

    if (app_table_exists($pdo, 'applications')) {
        $cols = array_map(static fn(array $c): string => (string) ($c['Field'] ?? ''), $pdo->query('SHOW COLUMNS FROM applications')->fetchAll(PDO::FETCH_ASSOC));
        $refCol = in_array('app_ref', $cols, true) ? 'app_ref' : (in_array('reference', $cols, true) ? 'reference' : 'id');
        $nameCol = in_array('name', $cols, true) ? 'name' : (in_array('applicant_name', $cols, true) ? 'applicant_name' : $refCol);
        foreach ($pdo->query("SELECT id, {$refCol} AS ref, {$nameCol} AS nm FROM applications ORDER BY id DESC LIMIT " . max(1, $limit))->fetchAll(PDO::FETCH_ASSOC) as $row) {
            admin_search_upsert($pdo, 'application', (int) $row['id'], (string) $row['ref'], (string) $row['nm'], 'admin.php?search=' . urlencode((string) $row['ref']), (string) $row['nm']);
            $count++;
        }
    }

    if (app_table_exists($pdo, 'provider_registry')) {
        foreach ($pdo->query("SELECT id, company_name, email FROM provider_registry ORDER BY id DESC LIMIT " . max(1, $limit))->fetchAll(PDO::FETCH_ASSOC) as $row) {
            admin_search_upsert($pdo, 'provider', (int) $row['id'], (string) $row['company_name'], (string) ($row['email'] ?? ''), 'providers.php', 'provider ' . (string) $row['company_name']);
            $count++;
        }
    }

    if (app_table_exists($pdo, 'support_tickets')) {
        foreach ($pdo->query("SELECT id, subject FROM support_tickets ORDER BY id DESC LIMIT " . max(1, $limit))->fetchAll(PDO::FETCH_ASSOC) as $row) {
            admin_search_upsert($pdo, 'ticket', (int) $row['id'], (string) $row['subject'], '', 'support/?ticket=' . (int) $row['id'], 'ticket support');
            $count++;
        }
    }

    return $count;
}

