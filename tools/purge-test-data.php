<?php
declare(strict_types=1);

/**
 * One-time purge of test-suite fixture rows from the application database.
 * Dry-run by default. Requires --apply --confirm=<dbname> to delete, and takes a
 * mysqldump backup first (unless --no-backup).
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config.php';

$args = $argv ?? [];
$apply = in_array('--apply', $args, true);
$noBackup = in_array('--no-backup', $args, true);
$confirm = '';
foreach ($args as $arg) {
    if (str_starts_with($arg, '--confirm=')) {
        $confirm = substr($arg, strlen('--confirm='));
    }
}

$pdo = db();
$dbName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();

$out = static function (string $line): void {
    fwrite(STDOUT, $line . "\n");
};

$out('Database : ' . $dbName);
$out('Mode     : ' . ($apply ? 'APPLY (will DELETE)' : 'DRY RUN (no changes)'));

if ($apply && $confirm !== $dbName) {
    fwrite(STDERR, "Refusing to delete: re-run with --apply --confirm={$dbName}\n");
    exit(2);
}

// --- Test-only identifiers -------------------------------------------------
$testEmail = "("
    . "email LIKE '%@example.com' OR email LIKE '%@natcodev.org' "
    . "OR email LIKE '%@natcodev.gov.ng' OR email LIKE '%@natcodev.test' "
    . "OR email LIKE 'alice\\_%@natcodev.com' OR email LIKE 'bob\\_%@natcodev.com' "
    . "OR email LIKE 'charlie\\_%@natcodev.com' OR email LIKE 'legacy\\_%@natcodev.com' "
    . "OR email LIKE 'victim\\_%@natcodev.com'"
    . ")";

$testTicketEmail = "requester_email LIKE '%@example.com' OR requester_email LIKE '%@natcodev.org' "
    . "OR requester_email LIKE '%@natcodev.gov.ng' OR requester_email LIKE '%@natcodev.test'";

// Snapshot the id sets first (all reads), so no DELETE self-references its own table.
$fetchIds = static function (PDO $pdo, string $sql): string {
    $ids = $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN) ?: [];
    $ids = array_map('intval', $ids);
    return $ids ? implode(',', $ids) : '0';
};

$userIds = $fetchIds($pdo, "SELECT id FROM users WHERE {$testEmail}");
$sellerIds = $fetchIds($pdo, "SELECT id FROM marketplace_sellers WHERE user_id IN ({$userIds})");
$listingIds = $fetchIds($pdo, "SELECT id FROM marketplace_listings WHERE seller_id IN ({$sellerIds})");
$newsIds = $fetchIds($pdo, "SELECT id FROM coop_news WHERE slug LIKE 'national-coconut-summit-%' "
    . "OR slug LIKE 'automated-test-announcement-%' OR slug LIKE 'natcodev-q3-expansion-%' "
    . "OR slug LIKE 'admin-bulletin-%' OR author_id IN ({$userIds})");
$farmIds = $fetchIds($pdo, "SELECT id FROM grower_farms WHERE user_id IN ({$userIds})");
$caseIds = $fetchIds($pdo, "SELECT id FROM agronomy_cases WHERE grower_id IN ({$userIds}) "
    . "OR created_by IN ({$userIds}) OR assigned_to IN ({$userIds})");
$ticketIds = $fetchIds($pdo, "SELECT id FROM support_tickets WHERE user_id IN ({$userIds}) OR {$testTicketEmail}");

$steps = [
    ['coop_news_feedback', "news_id IN ({$newsIds}) OR user_id IN ({$userIds})"],
    ['coop_news_analytics', "news_id IN ({$newsIds})"],
    ['coop_news_versions', "news_id IN ({$newsIds})"],
    ['coop_news', "id IN ({$newsIds})"],
    ['marketplace_disputes', "buyer_user_id IN ({$userIds}) OR seller_id IN ({$sellerIds})"],
    ['marketplace_reviews', "user_id IN ({$userIds}) OR seller_id IN ({$sellerIds}) OR listing_id IN ({$listingIds})"],
    ['marketplace_favorites', "user_id IN ({$userIds}) OR listing_id IN ({$listingIds})"],
    ['marketplace_promotions', "seller_id IN ({$sellerIds})"],
    ['marketplace_orders', "buyer_user_id IN ({$userIds}) OR seller_id IN ({$sellerIds}) OR listing_id IN ({$listingIds}) OR buyer_email LIKE '%@example.com' OR buyer_email LIKE '%@doe.test'"],
    ['marketplace_inquiries', "seller_id IN ({$sellerIds}) OR listing_id IN ({$listingIds}) OR buyer_email LIKE '%@example.com' OR buyer_email LIKE '%@doe.test'"],
    ['marketplace_listings', "id IN ({$listingIds})"],
    ['marketplace_sellers', "id IN ({$sellerIds})"],
    ['wallet_transactions', "user_id IN ({$userIds})"],
    ['wallet_withdrawals', "user_id IN ({$userIds})"],
    ['wallets', "user_id IN ({$userIds})"],
    ['agronomy_recommendations', "case_id IN ({$caseIds}) OR author_id IN ({$userIds})"],
    ['agronomy_cases', "id IN ({$caseIds})"],
    ['agronomy_soil_crop_records', "farm_id IN ({$farmIds}) OR recorded_by IN ({$userIds})"],
    ['farm_weather_snapshots', "farm_id IN ({$farmIds})"],
    ['farm_visits', "farm_id IN ({$farmIds}) OR agent_id IN ({$userIds})"],
    ['field_tasks', "farm_id IN ({$farmIds}) OR assigned_to IN ({$userIds})"],
    ['grower_farms', "id IN ({$farmIds})"],
    ['academy_feedback', "user_id IN ({$userIds})"],
    ['academy_group_certificates', "user_id IN ({$userIds})"],
    ['academy_certificates', "user_id IN ({$userIds})"],
    ['academy_attempts', "user_id IN ({$userIds})"],
    ['academy_progress', "user_id IN ({$userIds})"],
    ['webinar_registrations', "user_id IN ({$userIds})"],
    ['certificate_access_payments', "user_id IN ({$userIds})"],
    ['certificates', "user_id IN ({$userIds})"],
    ['support_ticket_attachments', "ticket_id IN ({$ticketIds})"],
    ['support_ticket_messages', "ticket_id IN ({$ticketIds}) OR admin_id IN ({$userIds})"],
    ['support_tickets', "id IN ({$ticketIds})"],
    ['support_inquiries', "user_id IN ({$userIds}) OR email LIKE '%@example.com' OR email LIKE '%@natcodev.org' OR email LIKE '%@natcodev.gov.ng' OR email LIKE '%@natcodev.test'"],
    ['document_requirements', "user_id IN ({$userIds})"],
    ['admin_action_requests', "requested_by IN ({$userIds})"],
    ['governance_deletion_requests', "requested_by_id IN ({$userIds})"],
    ['otp_sessions', "user_id IN ({$userIds})"],
    ['registration_drafts', "user_id IN ({$userIds})"],
    ['support_teams', "created_by IN ({$userIds})"],
    ['applications', $testEmail],
    ['users', $testEmail],
];

// --- Backup ----------------------------------------------------------------
if ($apply && !$noBackup) {
    $dump = null;
    $candidates = [
        'C:/Users/user/Downloads/UniServerZ/core/mysql/bin/mysqldump.exe',
        'mysqldump',
    ];
    foreach ($candidates as $candidate) {
        if (str_contains($candidate, '/') || str_contains($candidate, '\\')) {
            if (is_file($candidate)) {
                $dump = $candidate;
                break;
            }
        } else {
            $dump = $candidate;
            break;
        }
    }
    if ($dump === null) {
        fwrite(STDERR, "No mysqldump found; refusing to delete without a backup (use --no-backup only on a throwaway DB).\n");
        exit(3);
    }
    $backupDir = __DIR__ . '/../private_backups';
    if (!is_dir($backupDir)) {
        @mkdir($backupDir, 0775, true);
    }
    $backupFile = $backupDir . '/test-purge-' . date('Ymd-His') . '.sql';
    putenv('MYSQL_PWD=' . (string) app_env('DB_PASSWORD', ''));
    $cmd = escapeshellarg($dump)
        . ' --host=' . escapeshellarg((string) app_env('DB_HOST', '127.0.0.1'))
        . ' --port=' . escapeshellarg((string) app_env('DB_PORT', '3306'))
        . ' --user=' . escapeshellarg((string) app_env('DB_USERNAME', ''))
        . ' --single-transaction --skip-lock-tables --routines --events '
        . escapeshellarg($dbName)
        . ' > ' . escapeshellarg($backupFile) . ' 2>&1';
    exec($cmd, $dumpOut, $dumpCode);
    if ($dumpCode !== 0 || !is_file($backupFile) || filesize($backupFile) < 1024) {
        fwrite(STDERR, "Backup failed (exit {$dumpCode}); aborting.\n" . implode("\n", $dumpOut) . "\n");
        exit(4);
    }
    $out('Backup   : ' . $backupFile . ' (' . filesize($backupFile) . " bytes)");
}

// --- Run -------------------------------------------------------------------
if ($apply) {
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    $pdo->beginTransaction();
}

$total = 0;
$errors = 0;
foreach ($steps as [$table, $where]) {
    if (!app_table_exists($pdo, $table)) {
        continue;
    }
    try {
        if ($apply) {
            $count = $pdo->exec("DELETE FROM `{$table}` WHERE {$where}");
        } else {
            $count = (int) $pdo->query("SELECT COUNT(*) FROM `{$table}` WHERE {$where}")->fetchColumn();
        }
    } catch (Throwable $e) {
        $errors++;
        $out(sprintf('  %-32s ERROR: %s', $table, $e->getMessage()));
        if ($apply) {
            $pdo->rollBack();
            fwrite(STDERR, "Aborted and rolled back after error on {$table}.\n");
            exit(5);
        }
        continue;
    }
    if ($count > 0) {
        $total += $count;
        $out(sprintf('  %-32s %d', $table, $count));
    }
}

if ($apply) {
    if (app_table_exists($pdo, 'test_rate_limits')) {
        $pdo->exec('DROP TABLE `test_rate_limits`');
        $out(sprintf('  %-32s dropped', 'test_rate_limits'));
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    if ($pdo->inTransaction()) { $pdo->commit(); }
    $out('COMMITTED. Rows deleted: ' . $total);
} else {
    $out('Matched rows (dry run): ' . $total . ($errors ? " ({$errors} table(s) skipped)" : ''));
    $out('Re-run with --apply --confirm=' . $dbName . ' to delete.');
}
