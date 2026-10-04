<?php
declare(strict_types=1);

/**
 * NATCODEV Admin UI (v2) — feature-flag gating test suite.
 * Verifies the parallel Admin shell is OFF by default and only turns on via the
 * env/setting switch, with optional per-area allow-listing. No UI snapshots.
 */

require_once __DIR__ . '/TestHarness.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/admin-layout.php';

function run_admin_ui_tests(): void
{
    TestHarness::start('Scenario 14: Admin UI v2 parallel shell — feature-flag gating');
    $pdo = TestHarness::createTestDb();
    admin_ensure_schema($pdo);

    $envV2 = getenv('ADMIN_UI_V2');
    $envAreas = getenv('ADMIN_UI_V2_AREAS');

    // Baseline: no env, no setting -> OFF (legacy chrome is the production default).
    putenv('ADMIN_UI_V2');
    putenv('ADMIN_UI_V2_AREAS');
    $pdo->prepare("DELETE FROM settings WHERE key_name = 'admin_ui_v2'")->execute();
    TestHarness::assert(admin_ui_enabled('registry') === false, 'AdminUI: default is OFF (legacy chrome unchanged)');
    TestHarness::assert(admin_ui_enabled(null) === false, 'AdminUI: default OFF for any area');

    // DB setting on -> ON for all areas when no allow-list.
    $pdo->prepare("INSERT INTO settings (key_name, value) VALUES ('admin_ui_v2', '1') ON DUPLICATE KEY UPDATE value = '1'")->execute();
    TestHarness::assert(admin_ui_enabled('registry') === true, 'AdminUI: DB setting admin_ui_v2=1 enables the shell');
    TestHarness::assert(admin_ui_enabled(null) === true, 'AdminUI: setting enables all areas when no allow-list');

    // Per-area allow-list restricts migration.
    putenv('ADMIN_UI_V2_AREAS=registry');
    TestHarness::assert(admin_ui_enabled('registry') === true, 'AdminUI: allow-list admits a listed area');
    TestHarness::assert(admin_ui_enabled('wallet') === false, 'AdminUI: allow-list rejects a non-listed area');
    putenv('ADMIN_UI_V2_AREAS');

    // Env switch alone is enough.
    $pdo->prepare("DELETE FROM settings WHERE key_name = 'admin_ui_v2'")->execute();
    putenv('ADMIN_UI_V2=1');
    TestHarness::assert(admin_ui_enabled('anything') === true, 'AdminUI: env ADMIN_UI_V2=1 enables the shell');
    putenv('ADMIN_UI_V2');

    // Cleanup.
    $pdo->prepare("DELETE FROM settings WHERE key_name = 'admin_ui_v2'")->execute();
    if ($envV2 === false) { putenv('ADMIN_UI_V2'); } else { putenv('ADMIN_UI_V2=' . $envV2); }
    if ($envAreas === false) { putenv('ADMIN_UI_V2_AREAS'); } else { putenv('ADMIN_UI_V2_AREAS=' . $envAreas); }
}
