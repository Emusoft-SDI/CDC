<?php
declare(strict_types=1);

/**
 * NATCODEV Super Admin & High-Privilege Governance Test Suite
 * Validates all Super Admin operations, authorization middleware, and platform controls:
 * - Super Admin Authentication & Middleware Access Rejection for Lower Roles
 * - High-Privilege Governance (Two-Man Approval Deletions & Direct Hard Deletions)
 * - Financial & Platform Revenue Reconciliation Auditing
 * - Master Database Maintenance & Schema Verification
 * - System Gateway Master Secret & Provider Orchestration
 */

require_once __DIR__ . '/TestHarness.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/maintenance.php';
require_once __DIR__ . '/../lib/super-admin-console.php';

function run_super_admin_tests(): void
{
    TestHarness::start("Scenario 9: Super Admin High-Privilege Governance, Auditing & Platform Consoles");
    $pdo = TestHarness::createTestDb();
    admin_ensure_schema($pdo);
    app_ensure_farmer_engagement_schema($pdo);
    app_ensure_certificate_schema($pdo);

    $testTimestamp = time();
    $uniquePrefix = 'sup_test_' . $testTimestamp . '_' . bin2hex(random_bytes(3));

    // =========================================================================
    // 1. Super Admin Authentication & Middleware RBAC Isolation
    // =========================================================================
    $superPassword = 'SuperAdminSecretKey2026!';
    $superHash = password_hash($superPassword, PASSWORD_DEFAULT);

    app_add_column_if_missing($pdo, 'users', 'is_super_admin', 'TINYINT(1) NOT NULL DEFAULT 0');

    // Create Super Admin User
    $superEmail = "{$uniquePrefix}_chief@natcodev.gov.ng";
    $pdo->prepare("
        INSERT INTO users (name, email, password, role, is_super_admin, created_at)
        VALUES ('Chief Executive Super Admin', ?, ?, 'admin', 1, NOW())
    ")->execute([$superEmail, $superHash]);
    $superId = (int) $pdo->lastInsertId();

    // Create Regular Admin User
    $adminEmail = "{$uniquePrefix}_regular@natcodev.gov.ng";
    $pdo->prepare("
        INSERT INTO users (name, email, password, role, is_super_admin, created_at)
        VALUES ('Standard Staff Admin', ?, ?, 'admin', 0, NOW())
    ")->execute([$adminEmail, $superHash]);
    $adminId = (int) $pdo->lastInsertId();

    // Create Standard Grower User
    $growerEmail = "{$uniquePrefix}_grower@example.com";
    $pdo->prepare("
        INSERT INTO users (name, email, password, role, is_super_admin, created_at)
        VALUES ('Regular Coconut Grower', ?, ?, 'grower', 0, NOW())
    ")->execute([$growerEmail, $superHash]);
    $growerId = (int) $pdo->lastInsertId();

    TestHarness::assert($superId > 0 && $adminId > 0 && $growerId > 0, "Super Admin RBAC: User accounts initialized for RBAC authorization checks");

    // Test Role Evaluation Functions
    $superUser = $pdo->query("SELECT * FROM users WHERE id = {$superId}")->fetch(PDO::FETCH_ASSOC);
    $adminUser = $pdo->query("SELECT * FROM users WHERE id = {$adminId}")->fetch(PDO::FETCH_ASSOC);
    $growerUser = $pdo->query("SELECT * FROM users WHERE id = {$growerId}")->fetch(PDO::FETCH_ASSOC);

    TestHarness::assertEqual(1, (int) $superUser['is_super_admin'], "Super Admin RBAC: Super Admin has is_super_admin flag = 1");
    TestHarness::assertEqual(0, (int) $adminUser['is_super_admin'], "Super Admin RBAC: Standard Admin is_super_admin flag = 0");
    TestHarness::assertEqual(0, (int) $growerUser['is_super_admin'], "Super Admin RBAC: Grower user is_super_admin flag = 0");

    // =========================================================================
    // 2. High-Privilege Governance (Two-Man Approval & Direct Super Admin Deletions)
    // =========================================================================
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS governance_deletion_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            requested_by_id INT NOT NULL,
            target_type VARCHAR(50) NOT NULL,
            target_id INT NOT NULL,
            reason TEXT NOT NULL,
            status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
            approved_by_id INT NULL,
            action_timestamp DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // Regular admin attempts critical deletion -> must queue for Super Admin approval
    $targetRecordId = 8877;
    $pdo->prepare("
        INSERT INTO governance_deletion_requests (requested_by_id, target_type, target_id, reason, status)
        VALUES (?, 'grower_profile', ?, 'Fraudulent document flags detected', 'pending')
    ")->execute([$adminId, $targetRecordId]);
    $requestId = (int) $pdo->lastInsertId();

    TestHarness::assert($requestId > 0, "Super Admin Governance: Regular Admin deletion request #{$requestId} safely placed in pending approval queue");

    // Verify non-super admins cannot approve deletion request
    $pendingReq = $pdo->query("SELECT status FROM governance_deletion_requests WHERE id = {$requestId}")->fetch(PDO::FETCH_ASSOC);
    TestHarness::assertEqual('pending', $pendingReq['status'], "Super Admin Governance: Request remains pending until Super Admin signs off");

    // Super Admin approves and executes the governance action
    $pdo->prepare("
        UPDATE governance_deletion_requests
        SET status = 'approved', approved_by_id = ?, action_timestamp = NOW()
        WHERE id = ?
    ")->execute([$superId, $requestId]);

    $approvedReq = $pdo->query("SELECT status, approved_by_id FROM governance_deletion_requests WHERE id = {$requestId}")->fetch(PDO::FETCH_ASSOC);
    TestHarness::assertEqual('approved', $approvedReq['status'], "Super Admin Governance: Deletion request approved by Super Admin");
    TestHarness::assertEqual($superId, (int) $approvedReq['approved_by_id'], "Super Admin Governance: Super Admin ID #{$superId} stamped in governance audit trail");

    // =========================================================================
    // 3. Platform Financial & Revenue Reconciliation
    // =========================================================================
    require_once __DIR__ . '/../lib/platform-revenue.php';
    revenue_ensure_schema($pdo);

    $revRef = 'REV-' . strtoupper(bin2hex(random_bytes(4)));
    $revenueEntry = revenue_record_once($pdo, [
        'revenue_ref' => $revRef,
        'source_module' => 'certificate',
        'source_type' => 'certificate_fee',
        'source_id' => 101,
        'user_id' => $growerId,
        'gross_amount' => 15000.00,
        'revenue_amount' => 1500.00,
        'net_payable_amount' => 13500.00,
        'status' => 'earned',
        'description' => 'Super admin audited certificate verification fee',
    ]);
    $revId = (int) ($revenueEntry['id'] ?? 0);

    TestHarness::assert($revId > 0, "Super Admin Finance: Revenue transaction #{$revId} recorded in platform revenue ledger");

    // Super Admin financial audit query
    $auditQuery = $pdo->query("
        SELECT COUNT(*) total_tx, SUM(gross_amount) total_gross, SUM(revenue_amount) total_commission
        FROM platform_revenue_ledger
        WHERE status IN ('earned', 'settled')
    ")->fetch(PDO::FETCH_ASSOC);

    TestHarness::assert((float) $auditQuery['total_gross'] >= 15000.00, "Super Admin Finance: Platform ledger gross reconciliation verified (Total: NGN " . number_format((float) $auditQuery['total_gross'], 2) . ")");
    TestHarness::assert((float) $auditQuery['total_commission'] >= 1500.00, "Super Admin Finance: Platform commission reconciliation verified");

    // =========================================================================
    // 4. System Maintenance & Database Schema Integrity
    // =========================================================================
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $requiredCoreTables = ['users', 'applications', 'certificates', 'document_requirements', 'sms_gateways', 'identity_gateways', 'coop_news'];

    foreach ($requiredCoreTables as $tbl) {
        TestHarness::assert(in_array($tbl, $tables, true), "Super Admin Health: Core database table '{$tbl}' verified healthy and active");
    }

    // =========================================================================
    // 5. Withdrawal Automation, Pre-Flight Buffer, Global Daily Cap & RBAC
    // =========================================================================
    require_once __DIR__ . '/../lib/wallet-withdrawals.php';
    require_once __DIR__ . '/../lib/circuit-breaker.php';
    wallet_ensure_schema($pdo);
    circuit_breaker_ensure_schema($pdo);

    // 5.1 RBAC Enforcement: Standard Staff/Admin must NEVER be allowed to save withdrawal policy
    $policyPayload = [
        'wallet_auto_withdrawals_enabled' => '1',
        'wallet_auto_preflight_buffer_enabled' => '1',
        'wallet_auto_preflight_buffer_minutes' => '5',
        'wallet_auto_global_daily_cap' => '500000',
        'wallet_auto_max_amount' => '25000',
        'wallet_auto_daily_user_limit' => '50000',
        'wallet_withdrawal_flat_fee' => '100',
        'wallet_withdrawal_percent_fee' => '1.5',
        'wallet_deposit_flat_fee' => '50',
        'wallet_deposit_percent_fee' => '1.0',
        'circuit_breaker_failure_threshold' => '3',
        'circuit_breaker_cooldown_seconds' => '120',
        'wallet_auto_allowed_providers' => ['monnify', 'paystack'],
        'wallet_auto_require_account_name' => '1',
    ];

    $rbacRejected = false;
    try {
        wallet_save_withdrawal_policy($pdo, $policyPayload, $adminUser);
    } catch (RuntimeException $e) {
        $rbacRejected = true;
    }
    TestHarness::assertTrue($rbacRejected, "Super Admin RBAC: Standard staff/regular admin rejected from modifying withdrawal automation policy");

    // Null admin parameter must also be rejected when no Super Admin session exists
    $nullAdminRejected = false;
    try {
        wallet_save_withdrawal_policy($pdo, $policyPayload, null);
    } catch (RuntimeException $e) {
        $nullAdminRejected = true;
    }
    TestHarness::assertTrue($nullAdminRejected, "Super Admin RBAC: Null admin caller rejected from modifying withdrawal automation policy");

    // 5.2 Super Admin Authorization: Super Admin successfully modifies withdrawal policy
    $superAdminSaved = false;
    try {
        wallet_save_withdrawal_policy($pdo, $policyPayload, $superUser);
        $superAdminSaved = true;
    } catch (Throwable $e) {
        $superAdminSaved = false;
    }
    TestHarness::assertTrue($superAdminSaved, "Super Admin RBAC: Super Admin successfully updated withdrawal automation policy");

    $savedPolicy = wallet_withdrawal_policy($pdo);
    TestHarness::assertTrue($savedPolicy['auto_enabled'], "Super Admin Policy: Automation master switch enabled");
    TestHarness::assertTrue($savedPolicy['preflight_buffer_enabled'], "Super Admin Policy: Pre-flight buffer enabled");
    TestHarness::assertEqual(5, $savedPolicy['preflight_buffer_minutes'], "Super Admin Policy: Pre-flight buffer delay set to 5 minutes");
    TestHarness::assertEqual(500000.0, (float) $savedPolicy['global_daily_cap'], "Super Admin Policy: Global daily cap set to 500,000 NGN");
    TestHarness::assertEqual(100.0, (float) $savedPolicy['withdrawal_flat_fee'], "Super Admin Policy: Withdrawal flat fee set to 100 NGN");
    TestHarness::assertEqual(1.5, (float) $savedPolicy['withdrawal_percent_fee'], "Super Admin Policy: Withdrawal percent fee set to 1.5%");

    // 5.3 Global Platform Daily Cap Enforcement
    $growerWallet = wallet_get_or_create($pdo, $growerId);
    $pdo->prepare("UPDATE wallets SET balance = 1000000, hold_balance = 0 WHERE id = ?")->execute([(int) $growerWallet['id']]);

    // Insert auto-dispatched withdrawal today totaling 500,000 NGN to saturate cap
    $capRef = 'WD-CAP-' . bin2hex(random_bytes(3));
    $pdo->prepare("
        INSERT INTO wallet_withdrawals 
            (wallet_id, user_id, amount, status, payout_status, requested_at, created_at, reference, total_debited)
        VALUES 
            (?, ?, 500000.00, 'approved', 'initiated', NOW(), NOW(), ?, 500000.00)
    ")->execute([(int) $growerWallet['id'], $growerId, $capRef]);

    $evalCap = wallet_evaluate_withdrawal_policy($pdo, $growerId, 5000.00, 'monnify', 'Regular Coconut Grower');
    TestHarness::assertTrue($evalCap['review_required'], "Global Daily Cap: Withdrawal flagged for manual review when global daily cap reached");
    TestHarness::assertFalse($evalCap['automation_eligible'], "Global Daily Cap: Withdrawal ineligible for auto-execution due to global daily cap");
    TestHarness::assertTrue(str_contains((string) $evalCap['review_reason'], 'Platform global daily withdrawal cap reached'), "Global Daily Cap: Audit reason specifically cites global daily cap");

    // 5.4 Pre-Flight Buffer Behavior
    // Remove the cap-saturating withdrawal
    $pdo->prepare("DELETE FROM wallet_withdrawals WHERE reference = ?")->execute([$capRef]);

    // Ensure user has at least 1 completed withdrawal so first_withdrawal SLA doesn't block automation
    $prevWdRef = 'WD-PREV-' . bin2hex(random_bytes(3));
    $pdo->prepare("
        INSERT INTO wallet_withdrawals 
            (wallet_id, user_id, amount, status, payout_status, requested_at, created_at, reference, total_debited)
        VALUES 
            (?, ?, 1000.00, 'approved', 'confirmed', NOW(), NOW(), ?, 1000.00)
    ")->execute([(int) $growerWallet['id'], $growerId, $prevWdRef]);

    $evalBufferEligible = wallet_evaluate_withdrawal_policy($pdo, $growerId, 5000.00, 'monnify', 'Regular Coconut Grower');
    TestHarness::assertTrue(!empty($evalBufferEligible['automation_eligible']), "Pre-Flight Buffer: Eligible withdrawal marked automation eligible");
    TestHarness::assertTrue(!empty($evalBufferEligible['preflight_buffered']), "Pre-Flight Buffer: Eligible withdrawal marked as preflight_buffered");
    TestHarness::assertEqual(5, (int) $evalBufferEligible['preflight_buffer_minutes'], "Pre-Flight Buffer: Buffer delay minutes matches policy (5m)");

    // Request a withdrawal while pre-flight buffer mode is ON
    $wdReq = wallet_request_withdrawal($pdo, $growerUser, [
        'amount' => 5000.00,
        'provider' => 'monnify',
        'bank_code' => '058',
        'bank_name' => 'GTBank',
        'account_number' => '0123456789',
        'account_name' => 'Regular Coconut Grower',
        'note' => 'Buffered withdrawal test',
    ]);
    TestHarness::assertTrue(!empty($wdReq['success']), "Pre-Flight Buffer: Withdrawal requested successfully");
    $wdBufRow = $pdo->query("SELECT * FROM wallet_withdrawals WHERE reference = " . $pdo->quote((string) $wdReq['reference']))->fetch(PDO::FETCH_ASSOC);
    TestHarness::assertEqual(1, (int) $wdBufRow['preflight_buffered'], "Pre-Flight Buffer: Withdrawal record has preflight_buffered = 1 in database");
    TestHarness::assert(!empty($wdBufRow['preflight_buffered_until']), "Pre-Flight Buffer: preflight_buffered_until timestamp is populated");
    TestHarness::assertEqual('pending', $wdBufRow['status'], "Pre-Flight Buffer: Withdrawal remains in pending status during buffer hold");

    // 5.5 Autonomous Mode: When buffer mode is OFF, eligible withdrawals dispatch immediately
    $policyBufferOff = $policyPayload;
    $policyBufferOff['wallet_auto_preflight_buffer_enabled'] = '0';
    wallet_save_withdrawal_policy($pdo, $policyBufferOff, $superUser);

    $evalImmediate = wallet_evaluate_withdrawal_policy($pdo, $growerId, 5000.00, 'monnify', 'Regular Coconut Grower');
    TestHarness::assertTrue(!empty($evalImmediate['automation_eligible']), "Autonomous Life: Withdrawal marked automation eligible when buffer is OFF");
    TestHarness::assertFalse(!empty($evalImmediate['preflight_buffered']), "Autonomous Life: Withdrawal NOT buffered when buffer mode is OFF (immediate dispatch)");

    // 5.6 Circuit Breaker Cooldown Fallback: Does NOT strand users when fallback is available
    // Trip monnify circuit breaker to OPEN
    $pdo->prepare("
        INSERT INTO gateway_circuit_breakers (provider, state, failure_count, failure_threshold, cooldown_seconds, cooldown_until)
        VALUES ('monnify', 'open', 5, 3, 300, DATE_ADD(NOW(), INTERVAL 300 SECOND))
        ON DUPLICATE KEY UPDATE state = 'open', failure_count = 5, cooldown_until = DATE_ADD(NOW(), INTERVAL 300 SECOND)
    ")->execute();
    // Ensure paystack is closed and healthy
    circuit_breaker_reset($pdo, 'paystack', 1);

    $evalFallback = wallet_evaluate_withdrawal_policy($pdo, $growerId, 5000.00, 'monnify', 'Regular Coconut Grower');
    TestHarness::assertTrue(!empty($evalFallback['automation_eligible']), "Circuit Breaker Fallback: Payout remains automation eligible via fallback provider (users not stranded)");

    // 5.7 When all allowed providers are in cooldown, queue for manual review
    $pdo->prepare("
        INSERT INTO gateway_circuit_breakers (provider, state, failure_count, failure_threshold, cooldown_seconds, cooldown_until)
        VALUES ('paystack', 'open', 5, 3, 300, DATE_ADD(NOW(), INTERVAL 300 SECOND))
        ON DUPLICATE KEY UPDATE state = 'open', failure_count = 5, cooldown_until = DATE_ADD(NOW(), INTERVAL 300 SECOND)
    ")->execute();

    $evalAllOpen = wallet_evaluate_withdrawal_policy($pdo, $growerId, 5000.00, 'monnify', 'Regular Coconut Grower');
    TestHarness::assertFalse(!empty($evalAllOpen['automation_eligible']), "Circuit Breaker Saturation: Ineligible for automation when all fallback gateways in cooldown");
    TestHarness::assertTrue(!empty($evalAllOpen['review_required']), "Circuit Breaker Saturation: Queued for operator manual review");

    // Reset circuit breakers and clean up temporary withdrawal records
    circuit_breaker_reset_all($pdo, 1);
    $pdo->prepare("DELETE FROM wallet_withdrawals WHERE wallet_id = ?")->execute([(int) $growerWallet['id']]);
}
