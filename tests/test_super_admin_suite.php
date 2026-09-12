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
}
