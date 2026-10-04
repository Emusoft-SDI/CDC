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

    // P4: the admin WORKSPACE pages (marketplace/operations/revenue/wallet) opt in through
    // the same allow-list, and each must gate its chrome on its area key.
    $setEnv('ADMIN_UI_V2_AREAS', 'marketplace,operations,revenue,wallet');
    TestHarness::assert(admin_ui_enabled('wallet') === true, 'AdminUI: allow-list admits the wallet workspace area');
    $setEnv('ADMIN_UI_V2_AREAS', null);

    $p4WorkspacePages = [
        'marketplace' => ['admin/marketplace/index.php'],
        'operations' => ['admin/operations/index.php'],
        'revenue' => ['admin/revenue/index.php'],
        'wallet' => ['admin/wallet/index.php', 'admin/wallet/reports.php', 'admin/wallet/withdrawal_details.php', 'admin/wallet/wal.php', 'admin/wallet/fund_wallet.php'],
    ];
    $p4Gated = true;
    foreach ($p4WorkspacePages as $area => $paths) {
        foreach ($paths as $path) {
            $workspaceSource = (string) file_get_contents(__DIR__ . '/../' . $path);
            $p4Gated = $p4Gated
                && str_contains($workspaceSource, "admin_ui_enabled('" . $area . "')")
                && str_contains($workspaceSource, 'admin_ui_page_end()');
        }
    }
    TestHarness::assert($p4Gated, 'AdminUI P4: every admin workspace page gates its chrome on admin_ui_enabled(<area>) and closes via admin_ui_page_end()');

    // P5: the Workspace Hub and the auth screens (admin + wallet login, operator OTP)
    // opt in through the same allow-list, each gating its chrome on its area key.
    $setEnv('ADMIN_UI_V2_AREAS', 'hub,login');
    TestHarness::assert(admin_ui_enabled('hub') === true, 'AdminUI: allow-list admits the hub area');
    TestHarness::assert(admin_ui_enabled('login') === true, 'AdminUI: allow-list admits the login area');
    $setEnv('ADMIN_UI_V2_AREAS', null);

    $p5GatedPages = [
        'admin/index.php' => ['hub', 'admin_ui_page_start(', 'admin_ui_page_end()'],
        'admin/login.php' => ['login', 'admin_ui_auth_page_start(', 'admin_ui_auth_page_end()'],
        'admin/verify-otp.php' => ['login', 'admin_ui_auth_page_start(', 'admin_ui_auth_page_end()'],
    ];
    $p5Gated = true;
    foreach ($p5GatedPages as $path => [$area, $startNeedle, $endNeedle]) {
        $p5Source = (string) file_get_contents(__DIR__ . '/../' . $path);
        $p5Gated = $p5Gated
            && str_contains($p5Source, "admin_ui_enabled('" . $area . "')")
            && str_contains($p5Source, $startNeedle)
            && str_contains($p5Source, $endNeedle);
    }
    // P6 dedupe: admin/wallet/login.php is now a thin wrapper that reuses the shared admin
    // login page (admin/login.php — checked above) with the wallet post-login target, instead
    // of duplicating the gated auth chrome.
    $walletLoginSource = (string) file_get_contents(__DIR__ . '/../admin/wallet/login.php');
    $p5Gated = $p5Gated
        && str_contains($walletLoginSource, "require __DIR__ . '/../login.php'")
        && str_contains($walletLoginSource, "\$__loginTarget = 'wallet'");
    TestHarness::assert($p5Gated, 'AdminUI P5: the hub and auth pages gate their chrome on admin_ui_enabled(<area>)');

    // The new auth layout renders the shared v2 head (data-admin-ui + assets) with a
    // centered card and NO sidebar/topbar.
    ob_start();
    admin_ui_auth_page_start('Sign in', ['area' => 'login', 'brand' => 'Registry']);
    $authHead = (string) ob_get_clean();
    TestHarness::assert(str_contains($authHead, 'data-admin-ui'), 'AdminUI P5: auth layout emits the v2 shell marker (data-admin-ui)');
    TestHarness::assert(str_contains($authHead, 'a-auth') && str_contains($authHead, 'max-width:28rem'), 'AdminUI P5: auth layout renders the centered card');
    TestHarness::assert(!str_contains($authHead, 'a-sidebar') && !str_contains($authHead, 'a-topbar'), 'AdminUI P5: auth layout has no sidebar/topbar');
    $GLOBALS['admin_ui_active'] = false;

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
