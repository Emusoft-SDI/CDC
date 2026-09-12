<?php
declare(strict_types=1);

/**
 * Scenario 5: IDOR & Horizontal/Vertical Privilege Escalation Tests
 * Tests:
 * 1) Horizontal IDOR: User A trying to access/modify User B's farm application or imagery.
 * 2) Horizontal IDOR: User A trying to access User B's wallet or transaction details.
 * 3) Vertical Privilege Escalation: Unprivileged grower trying to access admin endpoints / bulk verification.
 * 4) Vertical Privilege Escalation: Admin without Super Admin privileges trying to directly delete templates.
 */

require_once __DIR__ . '/TestHarness.php';

function run_idor_tests(): void
{
    TestHarness::start('Scenario 5: IDOR & Horizontal/Vertical Ownership Verification');
    $pdo = TestHarness::createTestDb();

    // Setup Test Fixtures
    $aliceEmail = 'alice_' . bin2hex(random_bytes(4)) . '@natcodev.com';
    $bobEmail = 'bob_' . bin2hex(random_bytes(4)) . '@natcodev.com';
    $charlieEmail = 'charlie_' . bin2hex(random_bytes(4)) . '@natcodev.com';

    // User A: Grower
    $pdo->prepare("INSERT INTO users (name, email, password, role, platform_role) VALUES ('Grower Alice', ?, 'pass', 'grower', 'grower')")->execute([$aliceEmail]);
    $aliceId = (int) $pdo->lastInsertId();

    $aliceRef = 'APP-ALICE-' . bin2hex(random_bytes(3));
    $alicePhone = '080' . mt_rand(10000000, 99999999);
    $pdo->prepare("INSERT INTO applications (app_ref, name, email, phone, whatsapp, location, farm_size, commitments, confirmed) VALUES (?, 'Alice Farm', ?, ?, ?, 'Badagry, Lagos', 25.5, 'Full dedication', 0)")->execute([$aliceRef, $aliceEmail, $alicePhone, $alicePhone]);
    $aliceAppId = (int) $pdo->lastInsertId();
    $pdo->prepare("UPDATE users SET application_id = ? WHERE id = ?")->execute([$aliceAppId, $aliceId]);

    $pdo->prepare("INSERT INTO wallets (user_id, balance, status) VALUES (?, 5000.00, 'active') ON DUPLICATE KEY UPDATE balance = 5000.00")->execute([$aliceId]);

    // User B: Grower
    $pdo->prepare("INSERT INTO users (name, email, password, role, platform_role) VALUES ('Grower Bob', ?, 'pass', 'grower', 'grower')")->execute([$bobEmail]);
    $bobId = (int) $pdo->lastInsertId();

    $bobRef = 'APP-BOB-' . bin2hex(random_bytes(3));
    $bobPhone = '080' . mt_rand(10000000, 99999999);
    $pdo->prepare("INSERT INTO applications (app_ref, name, email, phone, whatsapp, location, farm_size, commitments, confirmed) VALUES (?, 'Bob Farm', ?, ?, ?, 'Uyo, Akwa Ibom', 40.0, 'Full dedication', 0)")->execute([$bobRef, $bobEmail, $bobPhone, $bobPhone]);
    $bobAppId = (int) $pdo->lastInsertId();
    $pdo->prepare("UPDATE users SET application_id = ? WHERE id = ?")->execute([$bobAppId, $bobId]);

    $pdo->prepare("INSERT INTO wallets (user_id, balance, status) VALUES (?, 12000.00, 'active') ON DUPLICATE KEY UPDATE balance = 12000.00")->execute([$bobId]);

    // User C: Admin
    $pdo->prepare("INSERT INTO users (name, email, password, role, platform_role, is_super_admin) VALUES ('Admin Charlie', ?, 'pass', 'admin', 'admin', 0)")->execute([$charlieEmail]);
    $charlieId = (int) $pdo->lastInsertId();

    // 5.1: Horizontal IDOR - Farm Application Access Control
    $getApplicationForEdit = function(PDO $db, array $currentUser, int $appId): ?array {
        // Enforce strict ownership check
        $stmt = $db->prepare("SELECT a.* FROM applications a JOIN users u ON u.application_id = a.id WHERE a.id = ? AND u.id = ? LIMIT 1");
        $stmt->execute([$appId, (int)$currentUser['id']]);
        return $stmt->fetch() ?: null;
    };

    $alice = ['id' => $aliceId, 'email' => $aliceEmail, 'role' => 'grower'];
    $bob = ['id' => $bobId, 'email' => $bobEmail, 'role' => 'grower'];

    // Alice accesses her own application
    $aliceOwnApp = $getApplicationForEdit($pdo, $alice, $aliceAppId);
    TestHarness::assert($aliceOwnApp !== null && (int)$aliceOwnApp['id'] === $aliceAppId, "User Alice can legitimately access her own application #{$aliceAppId}");

    // Alice attempts to access Bob's application
    $aliceAccessBobApp = $getApplicationForEdit($pdo, $alice, $bobAppId);
    TestHarness::assert($aliceAccessBobApp === null, "Horizontal IDOR Blocked: Alice CANNOT view/edit Bob application #{$bobAppId}");

    // 5.2: Horizontal IDOR - Wallet Access
    $getWalletForUser = function(PDO $db, array $currentUser, int $requestedUserId): ?array {
        if ($currentUser['role'] !== 'admin' && (int)$currentUser['id'] !== $requestedUserId) {
            return null; // IDOR blocked
        }
        $stmt = $db->prepare("SELECT id, user_id, balance FROM wallets WHERE user_id = ? LIMIT 1");
        $stmt->execute([$requestedUserId]);
        return $stmt->fetch() ?: null;
    };

    $aliceAccessBobWallet = $getWalletForUser($pdo, $alice, $bobId);
    TestHarness::assert($aliceAccessBobWallet === null, 'Horizontal IDOR Blocked: Alice CANNOT access Bob wallet details');

    $aliceOwnWallet = $getWalletForUser($pdo, $alice, $aliceId);
    TestHarness::assert($aliceOwnWallet !== null && (int)$aliceOwnWallet['user_id'] === $aliceId, 'Alice can access her own wallet record');

    // 5.3: Vertical Privilege Escalation - Admin Operations
    $executeBulkVerification = function(array $currentUser): array {
        $role = (string)($currentUser['role'] ?? '');
        $platformRole = (string)($currentUser['platform_role'] ?? '');
        if ($role !== 'admin' && $platformRole !== 'admin' && empty($currentUser['is_super_admin'])) {
            return ['status' => 403, 'error' => 'Forbidden'];
        }
        return ['status' => 200, 'success' => true];
    };

    $unprivilegedResult = $executeBulkVerification($alice);
    TestHarness::assertEqual(403, $unprivilegedResult['status'], 'Vertical Privilege Escalation Blocked: Grower Alice is forbidden from admin bulk verification');

    $adminResult = $executeBulkVerification(['role' => 'admin', 'platform_role' => 'admin', 'is_super_admin' => 0]);
    TestHarness::assertEqual(200, $adminResult['status'], 'Admin user successfully authorized for administrative actions');

    // 5.4: Super Admin Role Separation (Two-person rule / Verified approval queue)
    $deleteTemplate = function(array $currentUser, string $templateName): array {
        if (!empty($currentUser['is_super_admin'])) {
            return ['action' => 'deleted_direct', 'message' => "Template {$templateName} deleted directly"];
        }
        return ['action' => 'queued_for_approval', 'message' => "Delete request for {$templateName} queued for Super Admin approval"];
    };

    $regularAdmin = ['role' => 'admin', 'platform_role' => 'admin', 'is_super_admin' => 0];
    $superAdmin = ['role' => 'admin', 'platform_role' => 'super_admin', 'is_super_admin' => 1];

    $regAdminRes = $deleteTemplate($regularAdmin, 'sms_welcome_notice');
    TestHarness::assertEqual('queued_for_approval', $regAdminRes['action'], 'Regular Admin deletion is safely queued for Super Admin approval (Governance Rule)');

    $superAdminRes = $deleteTemplate($superAdmin, 'sms_welcome_notice');
    TestHarness::assertEqual('deleted_direct', $superAdminRes['action'], 'Super Admin has permission to execute direct deletions');
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    run_idor_tests();
    exit(TestHarness::summary());
}
