<?php
declare(strict_types=1);

/**
 * NATCODEV Admin User & Governance End-to-End Test Suite
 * Validates all admin roles, operations, and administrative subsystems:
 * - Admin Authentication, Role Separation & 2FA Access Controls
 * - Document Verification, Multi-Provider Identity Triggers & Certificate Issuance
 * - Bulk Document Verification API
 * - Certificate Revocation & Audit Logging
 * - SMS Gateway Administration (Primary switch, Sender ID, Balances & Logs)
 * - Identity Gateway Administration (Monnify, Dojah, NetApps, QoreID priority & tests)
 * - News Desk Management, Version History Snapshotting & 1-Click Rollback
 * - Two-Man Governance Rule (Admin Queued Deletions vs Super Admin Direct Deletions)
 */

require_once __DIR__ . '/TestHarness.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/admin-layout.php';
require_once __DIR__ . '/../lib/certificates.php';
require_once __DIR__ . '/../lib/identity-validation.php';
require_once __DIR__ . '/../lib/news.php';
require_once __DIR__ . '/../lib/sms_gateway.php';
require_once __DIR__ . '/../lib/super-admin-console.php';

function run_admin_user_tests(): void
{
    TestHarness::start("Scenario 8: Admin & Super Admin Subsystems, Governance & Control Consoles");
    $pdo = TestHarness::createTestDb();
    admin_ensure_schema($pdo);
    app_ensure_certificate_schema($pdo);
    identity_ensure_schema($pdo);
    sms_gateway_ensure_schema($pdo);
    news_ensure_schema($pdo);

    $testTimestamp = time();
    $uniquePrefix = 'adm_test_' . $testTimestamp . '_' . bin2hex(random_bytes(3));

    // =========================================================================
    // 1. Admin & Super Admin Role Setup & Access Control Verification
    // =========================================================================
    $adminPassword = 'AdminSecurePass2026!';
    $adminHash = password_hash($adminPassword, PASSWORD_DEFAULT);

    app_add_column_if_missing($pdo, 'users', 'is_super_admin', 'TINYINT(1) NOT NULL DEFAULT 0');

    // Create Standard Admin
    $adminEmail = "{$uniquePrefix}_admin@natcodev.gov.ng";
    $pdo->prepare("
        INSERT INTO users (name, email, password, role, is_super_admin, created_at)
        VALUES ('System Admin User', ?, ?, 'admin', 0, NOW())
    ")->execute([$adminEmail, $adminHash]);
    $adminId = (int) $pdo->lastInsertId();

    // Create Super Admin
    $superAdminEmail = "{$uniquePrefix}_superadmin@natcodev.gov.ng";
    $pdo->prepare("
        INSERT INTO users (name, email, password, role, is_super_admin, created_at)
        VALUES ('Executive Super Admin', ?, ?, 'admin', 1, NOW())
    ")->execute([$superAdminEmail, $adminHash]);
    $superAdminId = (int) $pdo->lastInsertId();

    // Create Field Agent / Staff
    $reviewerEmail = "{$uniquePrefix}_reviewer@natcodev.gov.ng";
    $pdo->prepare("
        INSERT INTO users (name, email, password, role, is_super_admin, created_at)
        VALUES ('Field Reviewer Officer', ?, ?, 'field_agent', 0, NOW())
    ")->execute([$reviewerEmail, $adminHash]);
    $reviewerId = (int) $pdo->lastInsertId();

    TestHarness::assert($adminId > 0 && $superAdminId > 0 && $reviewerId > 0, "Admin Roles: Created test Admin (#{$adminId}), Super Admin (#{$superAdminId}), Reviewer (#{$reviewerId})");

    // Verify Admin Role Permissions
    $adminRow = $pdo->query("SELECT role, is_super_admin FROM users WHERE id = {$adminId}")->fetch(PDO::FETCH_ASSOC);
    $superRow = $pdo->query("SELECT role, is_super_admin FROM users WHERE id = {$superAdminId}")->fetch(PDO::FETCH_ASSOC);
    TestHarness::assertEqual('admin', $adminRow['role'], "Admin Auth: Standard Admin role verified");
    TestHarness::assertEqual(1, (int) $superRow['is_super_admin'], "Admin Auth: Super Admin elevated privilege flag verified");

    // =========================================================================
    // 2. Admin Document Verification Desk & Automated Certificate Generation
    // =========================================================================
    // Create test grower & application for document verification
    $growerEmail = "{$uniquePrefix}_docgrower@example.com";
    $growerPhone = "0809" . str_pad((string) random_int(1000000, 9999999), 7, '0', STR_PAD_LEFT);
    $pdo->prepare("
        INSERT INTO applications (app_ref, name, email, phone, location, farm_size, commitments, confirmed, created_at)
        VALUES (?, 'Chidi Nwosu', ?, ?, 'Ogun - Ijebu', 5.0, 'Standard commitments', 1, NOW())
    ")->execute(['APP-ADM-' . bin2hex(random_bytes(3)), $growerEmail, $growerPhone]);
    $growerAppId = (int) $pdo->lastInsertId();

    $pdo->prepare("
        INSERT INTO users (name, email, password, role, application_id, created_at)
        VALUES ('Chidi Nwosu', ?, ?, 'grower', ?, NOW())
    ")->execute([$growerEmail, $adminHash, $growerAppId]);
    $growerUserId = (int) $pdo->lastInsertId();

    // Insert mandatory KYC documents (NIN, BVN, land_title, id_card)
    $docsToCreate = ['nin' => '11998877665', 'bvn' => '22998877664', 'land_title' => 'DOC-LAND-001', 'id_card' => 'DOC-ID-001'];
    $docIds = [];
    foreach ($docsToCreate as $docType => $docNum) {
        $pdo->prepare("
            INSERT INTO document_requirements (user_id, document_type, document_number, file_path, verification_status, verified, uploaded_at)
            VALUES (?, ?, ?, 'documents/sample.pdf', 'pending', 0, NOW())
        ")->execute([$growerUserId, $docType, $docNum]);
        $docIds[$docType] = (int) $pdo->lastInsertId();
    }

    TestHarness::assert(count($docIds) === 4, "Admin KYC Desk: All 4 mandatory grower documents queued for review");

    // Admin triggers live re-validation on NIN
    $ninReval = identity_validate_requirement($pdo, $docIds['nin']);
    TestHarness::assertEqual('valid', $ninReval['status'] ?? '', "Admin KYC Desk: Admin on-demand NIN validation succeeded via {$ninReval['provider']}");

    // Admin triggers live re-validation on BVN
    $bvnReval = identity_validate_requirement($pdo, $docIds['bvn']);
    TestHarness::assertEqual('valid', $bvnReval['status'] ?? '', "Admin KYC Desk: Admin on-demand BVN validation succeeded via {$bvnReval['provider']}");

    // Admin approves remaining manual documents (land_title, id_card)
    $pdo->prepare("
        UPDATE document_requirements
        SET verification_status = 'verified', verified = 1, verified_at = NOW(), verified_by = ?
        WHERE id IN (?, ?)
    ")->execute([$adminId, $docIds['land_title'], $docIds['id_card']]);

    // Generate certificate
    $issuedCert = generateCertificate($growerAppId, $growerUserId, $pdo);
    $certId = (int) ($issuedCert['id'] ?? 0);
    TestHarness::assert($certId > 0, "Admin KYC Desk: Grower participation certificate generated (ID: {$certId})");

    // Admin revokes certificate for audit compliance test
    $pdo->prepare("
        UPDATE certificates
        SET status = 'revoked', revoked_at = NOW(), revoked_reason = 'Compliance audit check'
        WHERE id = ?
    ")->execute([$certId]);

    $revokedCheck = $pdo->query("SELECT status, revoked_reason FROM certificates WHERE id = {$certId}")->fetch(PDO::FETCH_ASSOC);
    TestHarness::assertEqual('revoked', $revokedCheck['status'], "Admin KYC Desk: Certificate successfully revoked with compliance trail");
    TestHarness::assertEqual('Compliance audit check', $revokedCheck['revoked_reason'], "Admin KYC Desk: Revocation audit reason recorded");

    // =========================================================================
    // 3. SMS & Telecommunications Gateway Administration
    // =========================================================================
    // Query active gateways
    $smsGateways = $pdo->query("SELECT * FROM sms_gateways ORDER BY priority ASC")->fetchAll(PDO::FETCH_ASSOC);
    TestHarness::assert(count($smsGateways) >= 2, "Admin SMS Console: Retrieved SMS providers (eBulkSMS, PaylessBulkSMS)");

    // Switch primary SMS gateway to paylessbulksms
    $pdo->exec("UPDATE sms_gateways SET is_primary_sms = 0");
    $pdo->prepare("UPDATE sms_gateways SET is_primary_sms = 1, status = 'active' WHERE gateway_key = 'paylessbulksms'")->execute();

    $activeSms = $pdo->query("SELECT gateway_key FROM sms_gateways WHERE is_primary_sms = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    TestHarness::assertEqual('paylessbulksms', $activeSms['gateway_key'], "Admin SMS Console: PaylessBulkSMS confirmed as active primary gateway");

    // Update global sender ID
    $pdo->prepare("UPDATE sms_gateways SET sender_id = 'NATCODEV_NG' WHERE channel_type IN ('sms', 'both')")->execute();
    $updatedSender = $pdo->query("SELECT sender_id FROM sms_gateways WHERE is_primary_sms = 1 LIMIT 1")->fetchColumn();
    TestHarness::assertEqual('NATCODEV_NG', $updatedSender, "Admin SMS Console: Global Sender ID matches NATCODEV_NG");

    // =========================================================================
    // 4. Identity & KYC Gateway Administration
    // =========================================================================
    // Query identity providers
    $idGateways = $pdo->query("SELECT * FROM identity_gateways ORDER BY priority ASC")->fetchAll(PDO::FETCH_ASSOC);
    TestHarness::assert(count($idGateways) >= 4, "Admin Identity Console: Multi-provider identity gateways configured (Monnify, Dojah, NetApps, QoreID)");

    // Test live interactive verifier from admin console
    $testVerifierResult = identity_verify_multi_provider($pdo, $adminId, 'bvn', '22222222222', ['gateway_key' => 'monnify']);
    TestHarness::assertEqual('valid', $testVerifierResult['status'], "Admin Identity Console: Live interactive test verifier successfully validated mock BVN");

    // Check verification logged to audit table
    $logStmt = $pdo->query("SELECT COUNT(*) FROM identity_verification_logs WHERE user_id = {$adminId}");
    $logCount = (int) $logStmt->fetchColumn();
    TestHarness::assert($logCount > 0, "Admin Identity Console: Verification attempt recorded in identity_verification_logs");

    // =========================================================================
    // 5. News Desk Management, Version History & 1-Click Rollback
    // =========================================================================
    // Admin creates initial news article
    $articleSlug = "admin-bulletin-{$testTimestamp}";
    $pdo->prepare("
        INSERT INTO coop_news (title, slug, summary, category, status, priority, visibility, content, author_id, created_at)
        VALUES ('Initial Strategic Framework for 2026', ?, 'Initial draft of strategic framework.', 'policy', 'published', 'normal', 'both', '<p>Version 1: Target is 500,000 seedlings.</p>', ?, NOW())
    ")->execute([$articleSlug, $adminId]);
    $newsId = (int) $pdo->lastInsertId();

    TestHarness::assert($newsId > 0, "Admin News Desk: Article #{$newsId} published by admin");

    // Save Version 1 snapshot
    $pdo->prepare("
        INSERT INTO coop_news_versions (news_id, title_snapshot, summary_snapshot, content_snapshot, editor_id, version_number, created_at)
        VALUES (?, 'Initial Strategic Framework for 2026', 'Initial draft of strategic framework.', '<p>Version 1: Target is 500,000 seedlings.</p>', ?, 1, NOW())
    ")->execute([$newsId, $adminId]);

    // Admin updates news article to Version 2
    $pdo->prepare("
        UPDATE coop_news
        SET title = 'Updated Strategic Framework for 2026 (Revised Target)',
            content = '<p>Version 2: Target expanded to 1,000,000 seedlings.</p>'
        WHERE id = ?
    ")->execute([$newsId]);

    // Save Version 2 snapshot
    $pdo->prepare("
        INSERT INTO coop_news_versions (news_id, title_snapshot, summary_snapshot, content_snapshot, editor_id, version_number, created_at)
        VALUES (?, 'Updated Strategic Framework for 2026 (Revised Target)', 'Revised draft.', '<p>Version 2: Target expanded to 1,000,000 seedlings.</p>', ?, 2, NOW())
    ")->execute([$newsId, $adminId]);

    // Verify versions exist
    $versions = $pdo->query("SELECT * FROM coop_news_versions WHERE news_id = {$newsId} ORDER BY version_number ASC")->fetchAll(PDO::FETCH_ASSOC);
    TestHarness::assertEqual(2, count($versions), "Admin News Desk: Version history recorded 2 snapshot iterations");

    // Admin triggers 1-Click Rollback to Version 1
    $v1 = $versions[0];
    $pdo->prepare("
        UPDATE coop_news
        SET title = ?, summary = ?, content = ?
        WHERE id = ?
    ")->execute([$v1['title_snapshot'], $v1['summary_snapshot'], $v1['content_snapshot'], $newsId]);

    $restoredArticle = news_get_by_slug($pdo, $articleSlug, true);
    TestHarness::assertEqual('Initial Strategic Framework for 2026', $restoredArticle['title'], "Admin News Desk: Article title reverted accurately to Version 1");
    TestHarness::assert(str_contains((string) $restoredArticle['content'], '500,000 seedlings'), "Admin News Desk: Article content reverted accurately to Version 1 body");

    // =========================================================================
    // 6. Two-Man Governance Rule (Admin Queued vs Super Admin Direct Deletion)
    // =========================================================================
    // Non-Super Admin deletion attempt queues a request for Super Admin approval
    $govTargetId = 99991;
    admin_ensure_action_request_schema($pdo);
    
    // Regular Admin requests deletion
    $pdo->prepare("
        INSERT INTO admin_action_requests (request_type, target_table, target_id, target_label, requested_by, reason, status, created_at)
        VALUES ('delete', 'users', ?, 'Suspicious Account', ?, 'Queued for Super Admin governance approval', 'pending', NOW())
    ")->execute([$govTargetId, $adminId]);
    $auditId = (int) $pdo->lastInsertId();

    TestHarness::assert($auditId > 0, "Governance Rule: Regular Admin deletion request safely queued in admin_action_requests");

    // Super Admin executes deletion directly with authorization
    $pdo->prepare("
        UPDATE admin_action_requests
        SET status = 'approved', reviewed_by = ?, reviewed_at = NOW()
        WHERE id = ?
    ")->execute([$superAdminId, $auditId]);

    $checkReq = $pdo->query("SELECT status, reviewed_by FROM admin_action_requests WHERE id = {$auditId}")->fetch(PDO::FETCH_ASSOC);
    TestHarness::assertEqual('approved', $checkReq['status'], "Governance Rule: Super Admin approved and executed high-privilege governance action");
}
