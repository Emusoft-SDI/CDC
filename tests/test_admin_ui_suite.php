<?php
declare(strict_types=1);

/**
 * NATCODEV Admin UI (v2) — feature-flag gating + S1 shell verification suite.
 * Verifies the parallel Admin shell is OFF by default and only turns on via the
 * env/setting switch, with optional per-area allow-listing, and proves by rendering
 * the shared layout through output buffering that the flag really swaps the legacy
 * chrome for the v2 shell (and back).
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
    $envV2Array = $_ENV['ADMIN_UI_V2'] ?? null;
    $envAreasArray = $_ENV['ADMIN_UI_V2_AREAS'] ?? null;

    // app_env() reads getenv() first, then falls back to $_ENV (which .env populates),
    // so clearing only getenv() leaves an ADMIN_UI_V2 / ADMIN_UI_V2_AREAS value from
    // .env in force. This helper neutralises/restores BOTH layers.
    $setEnv = static function (string $key, ?string $value): void {
        if ($value === null) {
            putenv($key);
            unset($_ENV[$key]);
        } else {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    };

    // Baseline: no env, no setting -> OFF (legacy chrome is the production default).
    $setEnv('ADMIN_UI_V2', null);
    $setEnv('ADMIN_UI_V2_AREAS', null);
    $pdo->prepare("DELETE FROM settings WHERE key_name = 'admin_ui_v2'")->execute();
    TestHarness::assert(admin_ui_enabled('registry') === false, 'AdminUI: default is OFF (legacy chrome unchanged)');
    TestHarness::assert(admin_ui_enabled(null) === false, 'AdminUI: default OFF for any area');

    // S1 shell verification: render the shared layout head through output buffering
    // and confirm the flag OFF path emits the legacy chrome, not the v2 shell.
    ob_start();
    admin_page_start('Test', ['area' => 'x']);
    $legacyHead = (string) ob_get_clean();
    TestHarness::assert(str_contains($legacyHead, 'admin-header'), 'AdminUI: flag OFF renders the legacy admin-header chrome');
    TestHarness::assert(!str_contains($legacyHead, 'data-admin-ui'), 'AdminUI: flag OFF does not emit the v2 shell marker (data-admin-ui)');

    // DB setting on -> ON for all areas when no allow-list.
    $pdo->prepare("INSERT INTO settings (key_name, value) VALUES ('admin_ui_v2', '1') ON DUPLICATE KEY UPDATE value = '1'")->execute();
    TestHarness::assert(admin_ui_enabled('registry') === true, 'AdminUI: DB setting admin_ui_v2=1 enables the shell');
    TestHarness::assert(admin_ui_enabled(null) === true, 'AdminUI: setting enables all areas when no allow-list');

    // Flag ON must render the v2 shell chrome (data-admin-ui + a-sidebar) and drop
    // the legacy admin-header chrome.
    ob_start();
    admin_page_start('Test', ['area' => 'x']);
    $v2Head = (string) ob_get_clean();
    TestHarness::assert(str_contains($v2Head, 'data-admin-ui'), 'AdminUI: flag ON renders the v2 shell marker (data-admin-ui)');
    TestHarness::assert(str_contains($v2Head, 'a-sidebar'), 'AdminUI: flag ON renders the v2 sidebar (a-sidebar)');
    TestHarness::assert(!str_contains($v2Head, 'admin-header'), 'AdminUI: flag ON omits the legacy admin-header chrome');
    $GLOBALS['admin_ui_active'] = false;

    // Per-area allow-list restricts migration.
    $setEnv('ADMIN_UI_V2_AREAS', 'registry');
    TestHarness::assert(admin_ui_enabled('registry') === true, 'AdminUI: allow-list admits a listed area');
    TestHarness::assert(admin_ui_enabled('wallet') === false, 'AdminUI: allow-list rejects a non-listed area');
    $setEnv('ADMIN_UI_V2_AREAS', null);

    // Env switch alone is enough.
    $pdo->prepare("DELETE FROM settings WHERE key_name = 'admin_ui_v2'")->execute();
    $setEnv('ADMIN_UI_V2', '1');
    TestHarness::assert(admin_ui_enabled('anything') === true, 'AdminUI: env ADMIN_UI_V2=1 enables the shell');
    $setEnv('ADMIN_UI_V2', null);

    // Cleanup: restore the original env (both layers) and remove the DB setting.
    $pdo->prepare("DELETE FROM settings WHERE key_name = 'admin_ui_v2'")->execute();
    $setEnv('ADMIN_UI_V2', $envV2 === false ? null : $envV2);
    $setEnv('ADMIN_UI_V2_AREAS', $envAreas === false ? null : $envAreas);
    if ($envV2Array !== null) { $_ENV['ADMIN_UI_V2'] = $envV2Array; }
    if ($envAreasArray !== null) { $_ENV['ADMIN_UI_V2_AREAS'] = $envAreasArray; }
}
