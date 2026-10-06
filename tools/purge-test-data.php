<?php
declare(strict_types=1);

/**
 * Purge of test-suite fixture rows from the application database.
 *
 * The security suites exercise a real MySQL database and insert users, news,
 * listings, orders, payments, academy courses, support tickets and rate-limit
 * rows. When a suite was ever pointed at the live database those fixtures
 * stayed behind (their parent users may have been deleted, leaving orphans in
 * the child tables and noise in the log tables).
 *
 * Dry-run by default. Requires --apply --confirm=<dbname> to delete, and takes a
 * mysqldump backup first (unless --no-backup). Pass --no-orphans to skip the
 * referential-integrity sweep if the database legitimately contains rows whose
 * owner has been hard-deleted.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config.php';

$args = $argv ?? [];
$apply = in_array('--apply', $args, true);
$noBackup = in_array('--no-backup', $args, true);
$noOrphans = in_array('--no-orphans', $args, true);
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

$q = static fn (string $value): string => $pdo->quote($value);

// --- Test-only identifiers -------------------------------------------------
// Every local-part / domain below is generated exclusively by tests/ suites.
$emailPatterns = [
    'test@example.com',
    '%@example.com',
    '%@example.test',
    '%@example.org',
    '%@natcodev.test',
    '%@natcodev.local',
    '%@natcodev.org',
    '%natcodev.gov.ng',
    '%@doe.test',
    '%@lagosagrobuyers.ng',
    'adm_test_%',
    'pub_test_%',
    'sup_test_%',
    'cart_test_%',
    'mkt_test_%',
    'stk_%',
    'idempotency_test_%',
    'upg_test_%',
    'test_farmer_%',
    'gov_staff_%',
    'gov_doc_%',
    'gov_seller_%',
    'governance-requester-%',
    'acad\\_%@natcodev.org',
    'victim\\_%@natcodev.com',
    'alice\\_%@natcodev.com',
    'bob\\_%@natcodev.com',
    'charlie\\_%@natcodev.com',
    'legacy\\_%@natcodev.com',
];

$likeOr = static function (array $patterns, string $column) use ($q): string {
    $parts = [];
    foreach ($patterns as $pattern) {
        $parts[] = "{$column} LIKE " . $q($pattern);
    }
    return '(' . implode(' OR ', $parts) . ')';
};

$testEmail = $likeOr($emailPatterns, 'email');
$testTicketEmail = $likeOr($emailPatterns, 'requester_email');

// Snapshot the id sets first (all reads), so no DELETE self-references its own table.
$fetchIds = static function (PDO $pdo, string $sql): string {
    $ids = $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN) ?: [];
    $ids = array_map('intval', $ids);
    return $ids ? implode(',', $ids) : '0';
};

$userIds = $fetchIds($pdo, "SELECT id FROM users WHERE {$testEmail}");
$sellerIds = $fetchIds($pdo, "SELECT id FROM marketplace_sellers WHERE user_id IN ({$userIds})");
$listingIds = $fetchIds($pdo, "SELECT id FROM marketplace_listings WHERE seller_id IN ({$sellerIds}) OR title LIKE 'Test %' OR slug LIKE 'test-%'");
$newsIds = $fetchIds($pdo, "SELECT id FROM coop_news WHERE author_id IN ({$userIds}) "
    . "OR slug LIKE 'national-coconut-summit-%' OR slug LIKE 'natcodev-q3-expansion-%' "
    . "OR slug LIKE 'automated-test-announcement-%' OR slug LIKE 'admin-bulletin-%' "
    . "OR title IN ('Initial Strategic Framework for 2026', 'NATCODEV Unveils Q3 Coconut Expansion Plan')");
$farmIds = $fetchIds($pdo, "SELECT id FROM grower_farms WHERE user_id IN ({$userIds})");
$caseIds = $fetchIds($pdo, "SELECT id FROM agronomy_cases WHERE grower_id IN ({$userIds}) "
    . "OR created_by IN ({$userIds}) OR assigned_to IN ({$userIds})");
$ticketIds = $fetchIds($pdo, "SELECT id FROM support_tickets WHERE user_id IN ({$userIds}) OR {$testTicketEmail}");

// Academy fixtures are identified by the acad_<timestamp>_<hex> token the suites generate.
// The underscore must be escaped so the pattern does not also match the word "academy".
$webinarIds = $fetchIds($pdo, "SELECT id FROM webinars WHERE title LIKE '%acad\\_%' "
    . "OR course_code LIKE 'COCO-acad\\_%' "
    . "OR title LIKE 'Best Practices in Coconut Propagation%'");
$programIds = $fetchIds($pdo, "SELECT id FROM academy_programs WHERE title LIKE '%acad\\_%' "
    . "OR title LIKE 'Commercial Coconut Enterprise Specialization%'");
$groupIds = $fetchIds($pdo, "SELECT id FROM academy_certificate_groups WHERE title LIKE '%acad\\_%' "
    . "OR title LIKE 'Master Coconut Agronomy Specialist%'");
$assessmentIds = $fetchIds($pdo, "SELECT id FROM academy_assessments WHERE webinar_id IN ({$webinarIds})");
$lessonIds = $fetchIds($pdo, "SELECT id FROM academy_lessons WHERE webinar_id IN ({$webinarIds})");
$registrationIds = $fetchIds($pdo, "SELECT id FROM webinar_registrations WHERE webinar_id IN ({$webinarIds}) OR user_id IN ({$userIds})");

$notificationPattern = $likeOr($emailPatterns, 'recipient');
$subscriberPattern = $likeOr($emailPatterns, 'email');

$orphan = static function (string $column): string {
    return "({$column} IS NOT NULL AND {$column} <> 0 AND {$column} NOT IN (SELECT id FROM users))";
};

$steps = [
    // --- Academy LMS fixtures (children first) -----------------------------
    ['academy_questions', "assessment_id IN ({$assessmentIds})"],
    ['academy_attempts', "assessment_id IN ({$assessmentIds}) OR webinar_id IN ({$webinarIds}) OR user_id IN ({$userIds})"],
    ['academy_assessments', "id IN ({$assessmentIds}) OR webinar_id IN ({$webinarIds})"],
    ['academy_progress', "webinar_id IN ({$webinarIds}) OR lesson_id IN ({$lessonIds}) OR user_id IN ({$userIds})"],
    ['academy_materials', "webinar_id IN ({$webinarIds}) OR lesson_id IN ({$lessonIds})"],
    ['academy_lessons', "id IN ({$lessonIds}) OR webinar_id IN ({$webinarIds})"],
    ['academy_feedback', "webinar_id IN ({$webinarIds}) OR user_id IN ({$userIds})"],
    ['academy_group_certificates', "group_id IN ({$groupIds}) OR user_id IN ({$userIds})"],
    ['academy_certificate_group_courses', "group_id IN ({$groupIds}) OR webinar_id IN ({$webinarIds})"],
    ['academy_certificates', "webinar_id IN ({$webinarIds}) OR registration_id IN ({$registrationIds}) OR user_id IN ({$userIds})"],
    ['webinar_registrations', "id IN ({$registrationIds}) OR webinar_id IN ({$webinarIds}) OR user_id IN ({$userIds})"],
    ['webinars', "id IN ({$webinarIds})"],
    ['academy_certificate_groups', "id IN ({$groupIds})"],
    ['academy_programs', "id IN ({$programIds})"],

    // --- News fixtures ------------------------------------------------------
    ['coop_news_feedback', "news_id IN ({$newsIds}) OR user_id IN ({$userIds})"],
    ['coop_news_analytics', "news_id IN ({$newsIds})"],
    ['coop_news_versions', "news_id IN ({$newsIds})" . ($noOrphans ? '' : " OR " . $orphan('editor_id'))],
    ['coop_news', "id IN ({$newsIds})"],

    // --- Marketplace fixtures ----------------------------------------------
    ['marketplace_disputes', "buyer_user_id IN ({$userIds}) OR seller_id IN ({$sellerIds})"],
    ['marketplace_reviews', "user_id IN ({$userIds}) OR seller_id IN ({$sellerIds}) OR listing_id IN ({$listingIds})"],
    ['marketplace_favorites', "user_id IN ({$userIds}) OR listing_id IN ({$listingIds})"],
    ['marketplace_promotions', "seller_id IN ({$sellerIds}) OR created_by IN ({$userIds})"],
    ['marketplace_orders', "buyer_user_id IN ({$userIds}) OR seller_id IN ({$sellerIds}) OR listing_id IN ({$listingIds}) OR buyer_email LIKE '%@example.com' OR buyer_email LIKE '%@doe.test'"],
    ['marketplace_inquiries', "seller_id IN ({$sellerIds}) OR listing_id IN ({$listingIds}) OR buyer_email LIKE '%@example.com' OR buyer_email LIKE '%@doe.test'"],
    ['marketplace_listings', "id IN ({$listingIds})"],
    ['marketplace_sellers', "id IN ({$sellerIds})"],

    // --- Wallet / payments --------------------------------------------------
    ['wallet_transactions', "user_id IN ({$userIds})"],
    ['wallet_withdrawals', "user_id IN ({$userIds})"],
    ['wallets', "user_id IN ({$userIds})"],

    // --- Field operations / agronomy ---------------------------------------
    ['agronomy_recommendations', "case_id IN ({$caseIds}) OR author_id IN ({$userIds})" . ($noOrphans ? '' : " OR " . $orphan('author_id'))],
    ['agronomy_cases', "id IN ({$caseIds})" . ($noOrphans ? '' : " OR " . $orphan('grower_id') . " OR " . $orphan('assigned_to') . " OR " . $orphan('created_by'))],
    ['agronomy_soil_crop_records', "farm_id IN ({$farmIds}) OR recorded_by IN ({$userIds})"],
    ['farm_weather_snapshots', "farm_id IN ({$farmIds})"],
    ['farm_visits', "farm_id IN ({$farmIds}) OR agent_id IN ({$userIds})"],
    ['field_tasks', "farm_id IN ({$farmIds}) OR assigned_to IN ({$userIds})"],
    ['agent_assignments', "agent_id IN ({$userIds}) OR grower_id IN ({$userIds})" . ($noOrphans ? '' : " OR " . $orphan('agent_id') . " OR " . $orphan('grower_id'))],
    ['grower_farms', "id IN ({$farmIds})"],

    // --- Certificates -------------------------------------------------------
    ['certificate_access_payments', "user_id IN ({$userIds})"],
    ['certificates', "user_id IN ({$userIds})"],

    // --- Support desk -------------------------------------------------------
    ['support_ticket_attachments', "ticket_id IN ({$ticketIds})"],
    ['support_ticket_messages', "ticket_id IN ({$ticketIds}) OR admin_id IN ({$userIds})"],
    ['support_tickets', "id IN ({$ticketIds})"],
    ['support_inquiries', "user_id IN ({$userIds}) OR " . $likeOr($emailPatterns, 'email')],
    ['support_teams', "created_by IN ({$userIds}) OR team_name LIKE 'Escrow Arbitration Team%'"
        . ($noOrphans ? '' : " OR " . $orphan('lead_admin_id') . " OR " . $orphan('created_by'))],

    // --- Governance / identity ---------------------------------------------
    ['governance_deletion_requests', "requested_by_id IN ({$userIds})"
        . ($noOrphans ? '' : " OR " . $orphan('requested_by_id') . " OR " . $orphan('approved_by_id'))],
    ['document_requirements', "user_id IN ({$userIds})"],
    ['admin_action_requests', "requested_by IN ({$userIds})"],
    ['user_import_records', "user_id IN ({$userIds})"],
    ['user_role_assignments', "user_id IN ({$userIds})"],
    ['otp_sessions', "user_id IN ({$userIds})"],
    ['registration_drafts', "user_id IN ({$userIds})"],

    // --- Logs / settings / misc test residue -------------------------------
    ['notification_logs', $notificationPattern],
    ['subscribers', $subscriberPattern],
    ['audit_log', "action = 'gov_test_event' OR description LIKE '%gov_test_event%' "
        . "OR description LIKE '%superadmin.test@natcodev.local%' "
        . "OR description LIKE '%Testing reinstate via CLI%'"],
    ['settings', "(key_name IN ('admin_profile_name', 'admin_profile_email') AND " . $likeOr($emailPatterns, 'value') . ")"
        . " OR (key_name = 'admin_profile_name' AND value LIKE 'Test %')"
        . " OR key_name LIKE 'acad\\_%'"
        . " OR (key_name = 'paystack_secret_key' AND value LIKE '%GOVCHECK%')"],
    ['app_rate_limits', "limit_key LIKE 'login\\_attempt\\_%' AND attempts >= 10 AND expires_at < UNIX_TIMESTAMP()"],

    // --- Applications / users ----------------------------------------------
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
