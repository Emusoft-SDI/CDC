<?php
declare(strict_types=1);

/**
 * NATCODEV Admin Platform Suite (Phases 0-6)
 *
 * Covers the additive admin platform infrastructure and the shell-conflict fix:
 * - Single-chrome guarantee: the legacy operator strip is suppressed under the
 *   master shell, and admin_workspace_render() passes already-shelled output through.
 * - Feature->script map completeness (no silent 'dashboard' fallbacks).
 * - Capability façade, action registry/dispatch, audit v2, approvals registry,
 *   metrics cache, idempotency, queue processing/retry, search index.
 */

require_once __DIR__ . '/TestHarness.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/admin-layout.php';
require_once __DIR__ . '/../lib/admin-workspace.php';
require_once __DIR__ . '/../lib/admin-operator-strip.php';
require_once __DIR__ . '/../lib/admin-kernel.php';
require_once __DIR__ . '/../lib/admin-capabilities.php';
require_once __DIR__ . '/../lib/admin-actions.php';
require_once __DIR__ . '/../lib/admin-audit-v2.php';
require_once __DIR__ . '/../lib/admin-approvals.php';
require_once __DIR__ . '/../lib/admin-metrics.php';
require_once __DIR__ . '/../lib/admin-search.php';
require_once __DIR__ . '/../lib/admin-queue.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

function run_admin_platform_tests(): void
{
    TestHarness::start('Scenario 16: Admin Platform (shell fix, kernel, actions, audit, metrics, queue, search)');
    $pdo = TestHarness::createTestDb();
    admin_ensure_schema($pdo);
    admin_ensure_action_request_schema($pdo);

    // --- Phase 0: shell conflict fix -------------------------------------
    $GLOBALS['admin_page_chrome'] = true;
    TestHarness::assertEqual('', admin_workspace_operator_strip($pdo, []), 'Operator strip is suppressed while the master shell is active');
    $GLOBALS['admin_page_chrome'] = false;

    $tmpController = __DIR__ . '/.tmp_nc_shell_controller.php';
    file_put_contents($tmpController, "<?php echo '<!DOCTYPE html><html><head></head><body><div class=\"nc-hub\">master</div></body></html>';");
    ob_start();
    admin_workspace_render($tmpController, 'reports');
    $rendered = (string) ob_get_clean();
    @unlink($tmpController);
    TestHarness::assert(str_contains($rendered, 'nc-hub'), 'Render wrapper passes master-shell output through');
    TestHarness::assert(!str_contains($rendered, '<base'), 'Render wrapper does not inject <base> into already-shelled pages');

    // --- Feature map completeness ----------------------------------------
    $featureCatalog = admin_feature_catalog();
    foreach (['manage_certificate.php' => 'certificates', 'search.php' => 'dashboard', 'certificates.php' => 'certificates', 'identity_gateways.php' => 'documents'] as $script => $feature) {
        TestHarness::assertEqual($feature, admin_feature_for_script($script), "Feature map resolves {$script} -> {$feature}");
    }

    // --- Capability façade ------------------------------------------------
    $capabilities = admin_capability_catalog();
    $allKnown = true;
    foreach ($capabilities as $capability => $feature) {
        if (!array_key_exists($feature, $featureCatalog)) {
            $allKnown = false;
        }
    }
    TestHarness::assert($allKnown, 'Every capability maps to a real feature (count=' . count($capabilities) . ')');
    TestHarness::assert(admin_capability_feature('wallet.manage') === 'wallet', 'Capability resolves to its feature');

    // --- Action registry ---------------------------------------------------
    admin_register_action('test.noop', static fn(PDO $p, array $in): array => ['ok' => true, 'echo' => $in['value'] ?? null], null);
    $ok = admin_dispatch_action($pdo, 'test.noop', ['value' => 'x']);
    TestHarness::assert(!empty($ok['ok']) && ($ok['echo'] ?? null) === 'x', 'Registered action dispatches and returns handler result');
    $missing = admin_dispatch_action($pdo, 'does.not.exist');
    TestHarness::assertEqual(404, (int) ($missing['status'] ?? 0), 'Unknown action returns 404');

    admin_register_action('test.gated', static fn(PDO $p, array $in): array => ['ok' => true], 'users.manage');
    $gated = admin_dispatch_action($pdo, 'test.gated');
    TestHarness::assertEqual(403, (int) ($gated['status'] ?? 0), 'Action with unmet capability returns 403');

    // --- Audit v2 ----------------------------------------------------------
    admin_audit_event($pdo, 'unit_test_event', 'unit_entity', 42, ['a' => 1], ['b' => 2], 'success', 'unit test');
    $row = $pdo->query("SELECT entity_type, entity_id, outcome FROM audit_log WHERE action = 'unit_test_event' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    TestHarness::assert(($row['entity_type'] ?? '') === 'unit_entity' && (int) ($row['entity_id'] ?? 0) === 42, 'Audit v2 writes entity context');

    // --- Approvals registry ------------------------------------------------
    $hit = false;
    admin_register_approval_handler('unit_type', static function (PDO $p, array $r, string $d, string $n) use (&$hit): void { $hit = true; });
    admin_apply_approval($pdo, ['id' => 1, 'request_type' => 'unit_type'], 'approve', 'ok');
    TestHarness::assert($hit, 'Approval handler is invoked for its request type');
    TestHarness::assertThrows(static fn() => admin_apply_approval($pdo, ['request_type' => 'unregistered_type'], 'approve'), RuntimeException::class, 'Unknown approval type throws');

    // --- Metrics cache -----------------------------------------------------
    $calls = 0;
    $compute = static function () use (&$calls): int { $calls++; return 7; };
    $first = admin_metric($pdo, 'unit_metric', 300, $compute);
    $second = admin_metric($pdo, 'unit_metric', 300, $compute);
    TestHarness::assert($first === 7 && $second === 7, 'Metric returns computed value');
    TestHarness::assertEqual(1, $calls, 'Metric compute runs once and is served from cache');
    admin_metric_invalidate($pdo, 'unit_metric');
    admin_metric($pdo, 'unit_metric', 300, $compute);
    TestHarness::assertEqual(2, $calls, 'Metric invalidation forces recompute');

    // --- Idempotency -------------------------------------------------------
    $runs = 0;
    $idem = static function () use (&$runs): array { $runs++; return ['n' => $runs]; };
    $a = admin_idempotent($pdo, 'unit_idem_' . uniqid('', true), $idem);
    $b = admin_idempotent($pdo, 'unit_idem_' . uniqid('', true), $idem);
    TestHarness::assertEqual(2, $runs, 'Idempotent runs once per unique key');

    // --- Queue + retry -----------------------------------------------------
    $processedKeys = [];
    admin_register_job_handler('unit_job_ok', static function (PDO $p, array $payload) use (&$processedKeys): void { $processedKeys[] = $payload['k'] ?? ''; });
    admin_enqueue($pdo, 'unit_job_ok', ['k' => 'one']);
    $processed = admin_run_jobs($pdo, 10);
    TestHarness::assert($processed >= 1 && in_array('one', $processedKeys, true), 'Queue processes a registered job');

    $flaky = 0;
    admin_register_job_handler('unit_job_flaky', static function (PDO $p, array $payload) use (&$flaky): void {
        $flaky++;
        if ($flaky < 2) {
            throw new RuntimeException('transient');
        }
    });
    $flakyId = admin_enqueue($pdo, 'unit_job_flaky', ['x' => 1]);
    admin_run_jobs($pdo, 10);
    $pdo->prepare("UPDATE admin_jobs SET run_at = 0 WHERE id = ?")->execute([$flakyId]);
    admin_run_jobs($pdo, 10);
    $status = (string) $pdo->query("SELECT status FROM admin_jobs WHERE id = {$flakyId}")->fetchColumn();
    TestHarness::assertEqual('done', $status, 'Queue retries a transient failure and eventually succeeds');

    // --- Search index ------------------------------------------------------
    admin_search_upsert($pdo, 'unit_entity', 1, 'Coconut Nursery', 'Test record', 'providers.php', 'nursery coconut');
    $hits = admin_search_query($pdo, 'Coconut Nursery', 5);
    $found = false;
    foreach ($hits as $hit) {
        if (($hit['entity_type'] ?? '') === 'unit_entity') {
            $found = true;
        }
    }
    TestHarness::assert($found, 'Search index returns an inserted record');

    // --- Phase 5: real handlers + actions registered -----------------------
    require_once __DIR__ . '/../lib/admin-jobs.php';
    $handlers = admin_job_handlers();
    foreach (['search.reindex', 'export.table', 'notification.broadcast'] as $type) {
        TestHarness::assert(isset($handlers[$type]), "Job handler registered: {$type}");
    }
    $actions = admin_action_registry();
    foreach (['jobs.reindex', 'jobs.export', 'jobs.run'] as $key) {
        TestHarness::assert(isset($actions[$key]), "Admin action registered: {$key}");
    }
    TestHarness::assert(isset(admin_export_tables()['users']), 'Export whitelist includes users');
    TestHarness::assert(str_contains(admin_export_csv_cell('=cmd'), "'=cmd"), 'CSV export neutralises formula injection');

    admin_enqueue($pdo, 'search.reindex', ['limit' => 10]);
    admin_run_jobs($pdo, 10);
    $reindexStatus = (string) $pdo->query("SELECT status FROM admin_jobs WHERE job_type = 'search.reindex' ORDER BY id DESC LIMIT 1")->fetchColumn();
    TestHarness::assertEqual('done', $reindexStatus, 'search.reindex job runs to completion');

    admin_cron_heartbeat($pdo, 'unit-test');
    $hb = (int) $pdo->query("SELECT computed_at FROM admin_metrics WHERE metric_key = 'cron:heartbeat'")->fetchColumn();
    TestHarness::assert($hb > 0, 'Cron heartbeat is recorded');

    // --- Phase 6: health report -------------------------------------------
    require_once __DIR__ . '/../lib/admin-health.php';
    $report = admin_health_report($pdo);
    TestHarness::assert(isset($report['checks']['database'], $report['checks']['queue'], $report['checks']['cron']), 'Health report exposes database, queue and cron checks');
    TestHarness::assert(array_key_exists('ok', $report), 'Health report has an overall ok flag');

    // --- Phase 9: CSP hardening -------------------------------------------
    require_once __DIR__ . '/../lib/admin-csp.php';
    $policy = admin_csp_policy();
    TestHarness::assert(str_contains($policy, 'nonce-'), 'CSP policy carries a nonce');
    TestHarness::assert(!preg_match("/script-src[^;]*'unsafe-inline'/", $policy), 'CSP script-src no longer allows unsafe-inline');
    TestHarness::assert(preg_match('/^[A-Za-z0-9+\/]+=*$/', admin_csp_nonce()) === 1, 'CSP nonce is base64');

    $sample = '<button onclick="return confirm(\'Sure?\')">x</button>'
        . '<a onclick=\'openModal("m")\'>o</a>'
        . "<select onchange=\"this.form.submit()\"></select>"
        . '<script>var a=1;</script><style>.a{color:red}</style>';
    $hardened = admin_csp_harden($sample);
    TestHarness::assert(!str_contains($hardened, 'onclick='), 'CSP hardening removes inline onclick');
    TestHarness::assert(!str_contains($hardened, 'onchange='), 'CSP hardening removes inline onchange (single quotes)');
    TestHarness::assert(str_contains($hardened, 'data-nc-confirm="Sure?"'), 'CSP hardening maps confirm to a data attribute');
    TestHarness::assert(str_contains($hardened, 'data-nc-call="openModal"'), 'CSP hardening maps function calls');
    TestHarness::assert(str_contains($hardened, 'data-nc-autosubmit'), 'CSP hardening maps autosubmit');
    TestHarness::assert(substr_count($hardened, 'nonce="') >= 2, 'CSP hardening nonces script and style tags');
}
