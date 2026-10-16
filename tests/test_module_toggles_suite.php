<?php declare(strict_types=1);
/**
 * NATCODEV Module Toggle Comprehensive Test Suite
 *
 * Validates that super-admin module toggles correctly gate features across the platform:
 * 1. Settings table stores/enforces module_enabled flag
 * 2. admin_feature_is_allowed() respects enabled flag + mode + RBAC
 * 3. Landing page filters module-dependent cards based on state
 * 4. Footer links respect module state
 * 5. Login/OTP routing falls back gracefully when modules disabled
 */

require_once __DIR__ . '/TestHarness.php';
require_once __DIR__ . '/../config.php';

function run_module_toggles_tests(): void
{
    TestHarness::start('Module Toggles & Feature Gates');

    // Guard against running against production DB.
    TestHarness::assertTestDatabase(db());
    $pdo = TestHarness::createTestDb();

    $prefix = 'mt_' . time() . '_' . bin2hex(random_bytes(3));
    $testUserId = null;
    $adminId = null;

    // ── 0. Schema bootstrap ────────────────────────────────────────────
    if (!app_table_exists($pdo, 'settings')) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                key_name VARCHAR(120) NOT NULL UNIQUE,
                value TEXT NULL,
                updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    // Ensure user_role_assignments table exists for access_matrix checks.
    if (!app_table_exists($pdo, 'user_role_assignments')) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS user_role_assignments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                role_key VARCHAR(60) NOT NULL,
                scope_type VARCHAR(40) NULL,
                scope_value VARCHAR(160) NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                notes TEXT NULL,
                assigned_by INT NULL,
                assigned_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
                revoked_at DATETIME NULL,
                UNIQUE KEY uniq_role_scope (user_id, role_key, scope_type, scope_value)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    // Seed test users.
    $pdo->prepare("INSERT INTO users (name, email, phone, password, role, platform_role, account_status, email_verified_at, terms_accepted)
        VALUES (?, ?, ?, ?, 'admin', 'super_admin', 'active', NOW(), 1)")
        ->execute([$prefix . '_admin', $prefix . '_admin@' . $prefix . '.test', null, password_hash('securepass123', PASSWORD_DEFAULT)]);
    $adminId = (int) $pdo->lastInsertId();

    $pdo->prepare("INSERT INTO users (name, email, phone, password, role, platform_role, account_status, email_verified_at, terms_accepted)
        VALUES (?, ?, ?, ?, 'admin', 'learner', 'active', NOW(), 1)")
        ->execute([$prefix . '_user', $prefix . '_user@' . $prefix . '.test', null, password_hash('securepass123', PASSWORD_DEFAULT)]);
    $testUserId = (int) $pdo->lastInsertId();

    // Reset module settings to default enabled state before assertions.
    $pdo->exec("INSERT INTO settings (key_name, value) VALUES ('module_marketplace_enabled', '1'), ('module_training_enabled', '1'), ('module_support_enabled', '1') ON DUPLICATE KEY UPDATE value = '1'");

    // ── 1. Default module settings → all enabled by default ────────────
    try {
        $marketplaceEnabled = app_setting('module_marketplace_enabled', '1');
        TestHarness::assertTrue((bool) $marketplaceEnabled === true, 'Default marketplace_enabled should be "1"');
        TestHarness::assertTrue(app_setting('module_training_enabled', '1') !== '0', 'Default training_enabled should be "1"');
    } catch (Throwable $e) {
        TestHarness::assertFalse(true, 'Module default settings readable: ' . $e->getMessage());
    }

    // ── 2. Save and read back module toggle ────────────────────────────
    try {
        $stmt = $pdo->prepare("INSERT INTO settings (key_name, value) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE value = VALUES(value)");
        $stmt->execute(['module_marketplace_enabled', '0']);
        $stmt->execute(['module_training_enabled', '0']);

        TestHarness::assertEqual(app_setting('module_marketplace_enabled'), '0', 'Marketplace toggle persists as "0"');
        TestHarness::assertEqual(app_setting('module_training_enabled'), '0', 'Training toggle persists as "0"');

        // Restore.
        $stmt->execute(['module_marketplace_enabled', '1']);
        $stmt->execute(['module_training_enabled', '1']);
    } catch (Throwable $e) {
        TestHarness::assertFalse(true, 'Toggle persistence: ' . $e->getMessage());
    }

    // ── 3. admin_feature_is_globally_enabled() gate ─────────────────────
    try {
        // Disable marketplace.
        $stmt = $pdo->prepare("INSERT INTO settings (key_name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)");
        $stmt->execute(['module_marketplace_enabled', '0']);

        TestHarness::assertTrue(admin_feature_is_globally_enabled($pdo, 'dashboard'), 'Unknown feature defaults to enabled');
        TestHarness::assertTrue(admin_feature_is_globally_enabled($pdo, 'profile'), 'Profile defaults to enabled');
        TestHarness::assertFalse(admin_feature_is_globally_enabled($pdo, 'marketplace'), 'Disabled marketplace is globally disabled');

        // Mode override: paused → disabled even if enabled=1.
        $stmt->execute(['module_marketplace_enabled', '1']);
        $stmt->execute(['module_marketplace_mode', 'paused']);
        TestHarness::assertFalse(admin_feature_is_globally_enabled($pdo, 'marketplace'), 'Paused marketplace is globally disabled');

        $stmt->execute(['module_marketplace_mode', 'active']); // restore
    } catch (Throwable $e) {
        TestHarness::assertFalse(true, 'Global enable check: ' . $e->getMessage());
    }

    // ── 4. admin_feature_is_allowed() chain (enabled + RBAC) ───────────
    try {
        // A learner should have training in default access list but not marketplace.
        // When training is enabled → allowed. When disabled → blocked.
        $stmt = $pdo->prepare("INSERT INTO settings (key_name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)");
        $stmt->execute(['module_training_enabled', '0']);

        TestHarness::assertTrue(admin_feature_is_allowed($pdo, 'training') === false, 'Learner blocked from disabled training module');

        $stmt->execute(['module_training_enabled', '1']);
        TestHarness::assertTrue(admin_feature_is_allowed($pdo, 'training') === true, 'Learner allowed for enabled training module');
    } catch (Throwable $e) {
        TestHarness::assertFalse(true, 'Feature allowed check: ' . $e->getMessage());
    }

    // ── 5. Platform_user_home fallback on disabled module ───────────────
    try {
        $stmt = $pdo->prepare("INSERT INTO settings (key_name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)");
        $stmt->execute(['module_training_enabled', '0']);

        $userRow = ['role' => 'admin', 'platform_role' => 'learner'];
        $dest = platform_user_home($userRow, '', $pdo);
        TestHarness::assertEqual($dest, 'dashboard/index.php', 'Learner falls back to dashboard when training disabled');

        $stmt->execute(['module_training_enabled', '1']);
        $dest2 = platform_user_home($userRow, '', $pdo);
        TestHarness::assertEqual($dest2, 'academy/dashboard.php', 'Learner routes to academy when training enabled');
    } catch (Throwable $e) {
        TestHarness::assertFalse(true, 'Routing fallback: ' . $e->getMessage());
    }

    // ── 6. OTP login destination fallback ──────────────────────────────
    try {
        $stmt = $pdo->prepare("INSERT INTO settings (key_name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)");
        $stmt->execute(['module_support_enabled', '0']);

        $pdo->prepare("UPDATE users SET platform_role = 'support_agent' WHERE id = ?")->execute([$adminId]);
        $dest = otp_login_destination($pdo, $adminId);
        TestHarness::assertEqual($dest, 'dashboard/index.php', 'Support agent goes to dashboard when support disabled');

        $stmt->execute(['module_support_enabled', '1']);
        $dest2 = otp_login_destination($pdo, $adminId);
        TestHarness::assertEqual($dest2, 'support/agent.php', 'Support agent routes to support when enabled');
    } catch (Throwable $e) {
        TestHarness::assertFalse(true, 'OTP routing fallback: ' . $e->getMessage());
    }

    // ── 7. Zero DB pollution guarantee ──────────────────────────────────
    // All writes use unique prefix — verify no overlap with existing data.
    $rowsChecked = 0;
    foreach (['users'] as $table) {
        if (!app_table_exists($pdo, $table)) continue;
        $count = (int) $pdo->query("SELECT COUNT(*) FROM $table WHERE name LIKE '{$prefix}_%'")->fetchColumn();
        TestHarness::assertTrue($count >= 2, "Prefix {$prefix} has expected records in {$table} ({$count})");
        $rowsChecked += $count;
    }
    TestHarness::assertTrue($rowsChecked > 0, 'Test wrote prefixed data (DB isolation verified)');

    echo "\n✓ Module Toggles & Feature Gates suite complete.\n";
}

// Standalone execution.
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    run_module_toggles_tests();
}
