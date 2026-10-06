<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../lib/identity-validation.php';

$pdo = db();
admin_ensure_schema($pdo);
admin_require($pdo);
identity_ensure_schema($pdo);

$message = '';
$error = '';
$testOutput = null;

// Handle Gateway Management Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        $action = trim((string) ($_POST['action'] ?? ''));

        // 1. 1-Click Set Primary Gateway
        if ($action === 'set_primary') {
            $gwId = (int) ($_POST['gateway_id'] ?? 0);
            try {
                $pdo->beginTransaction();
                $pdo->exec("UPDATE identity_gateways SET is_primary = 0");
                $stmt = $pdo->prepare("UPDATE identity_gateways SET is_primary = 1, status = 'active' WHERE id = ?");
                $stmt->execute([$gwId]);
                $pdo->commit();
                admin_audit($pdo, 'identity_primary_updated', 'Set primary identity provider to gateway #' . $gwId . '.');
                $message = 'Primary identity verification provider updated. Auto-failover cascade will start here.';
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = 'Failed to set primary provider: ' . $e->getMessage();
            }
        }

        // 2. Update Gateway Credentials & Mode
        elseif ($action === 'update_gateway') {
            $gwId = (int) ($_POST['gateway_id'] ?? 0);
            $name = trim((string) ($_POST['name'] ?? ''));
            $baseUrl = trim((string) ($_POST['base_url'] ?? ''));
            $apiKey = trim((string) ($_POST['api_key'] ?? ''));
            $apiSecret = trim((string) ($_POST['api_secret'] ?? ''));
            $appId = trim((string) ($_POST['app_id'] ?? ''));
            $contractCode = trim((string) ($_POST['contract_code'] ?? ''));
            $env = trim((string) ($_POST['environment'] ?? 'live'));
            $status = trim((string) ($_POST['status'] ?? 'active'));
            $priority = (int) ($_POST['priority'] ?? 1);

            try {
                $stmt = $pdo->prepare("
                    UPDATE identity_gateways 
                    SET name = ?, base_url = ?, api_key = ?, api_secret = ?, app_id = ?, contract_code = ?, environment = ?, status = ?, priority = ?
                    WHERE id = ?
                ");
                $stmt->execute([$name, $baseUrl, $apiKey, $apiSecret, $appId, $contractCode, $env, $status, $priority, $gwId]);
                admin_audit($pdo, 'identity_gateway_updated', 'Updated identity provider "' . $name . '" (#' . $gwId . ').');
                $message = "Provider '{$name}' configuration saved successfully.";
            } catch (Throwable $e) {
                $error = 'Failed to update provider: ' . $e->getMessage();
            }
        }

        // 3. Register New Identity Provider
        elseif ($action === 'create_gateway') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $gwKey = strtolower((string) preg_replace('/[^a-z0-9_]/i', '', trim((string) ($_POST['gateway_key'] ?? ''))));
            $driver = trim((string) ($_POST['provider_driver'] ?? 'custom'));
            $baseUrl = trim((string) ($_POST['base_url'] ?? ''));
            $apiKey = trim((string) ($_POST['api_key'] ?? ''));
            $apiSecret = trim((string) ($_POST['api_secret'] ?? ''));
            $appId = trim((string) ($_POST['app_id'] ?? ''));
            $contractCode = trim((string) ($_POST['contract_code'] ?? ''));
            $env = trim((string) ($_POST['environment'] ?? 'live'));
            $priority = (int) ($_POST['priority'] ?? 5);

            if (empty($name) || empty($gwKey) || empty($baseUrl)) {
                $error = 'Provider name, unique key, and API base URL are required.';
            } else {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO identity_gateways (gateway_key, name, provider_driver, base_url, api_key, api_secret, app_id, contract_code, environment, status, priority, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, NOW())
                    ");
                    $stmt->execute([$gwKey, $name, $driver, $baseUrl, $apiKey, $apiSecret, $appId, $contractCode, $env, $priority]);
                    admin_audit($pdo, 'identity_gateway_created', 'Registered identity provider "' . $name . '" (' . $gwKey . ').');
                    $message = "New identity verification provider '{$name}' registered successfully!";
                } catch (Throwable $e) {
                    $error = 'Failed to register provider: ' . $e->getMessage();
                }
            }
        }

        // 4. Interactive Live Test Verification
        elseif ($action === 'test_verification') {
            $testType = strtolower(trim((string) ($_POST['test_type'] ?? 'bvn')));
            $testNumber = trim((string) ($_POST['test_number'] ?? ''));
            $testGw = trim((string) ($_POST['test_gateway'] ?? 'auto'));

            if (empty($testNumber) || strlen(preg_replace('/\D+/', '', $testNumber)) !== 11) {
                $error = 'Please enter a valid 11-digit ' . strtoupper($testType) . ' number for testing.';
            } else {
                $adminId = (int) ($_SESSION['admin_user']['id'] ?? 1);
                $opts = [];
                if ($testGw !== 'auto' && is_numeric($testGw) && (int)$testGw > 0) {
                    $opts['gateway_id'] = (int) $testGw;
                }

                $testOutput = identity_verify_multi_provider($pdo, $adminId, $testType, $testNumber, $opts);
                if (!empty($testOutput['status']) && $testOutput['status'] === 'valid') {
                    $message = "Test " . strtoupper($testType) . " verification succeeded via " . strtoupper((string)($testOutput['provider'] ?? 'provider')) . "! Match Status: " . ($testOutput['match_status'] ?? 'FULL_MATCH');
                } elseif (!empty($testOutput['status']) && $testOutput['status'] === 'invalid') {
                    $error = "Verification completed: " . strtoupper($testType) . " could not be matched by provider.";
                } else {
                    $error = "Verification failed: " . ($testOutput['message'] ?? 'Check provider credentials or failover logs.');
                }
            }
        }
    }
}

// Fetch all gateways
$gateways = $pdo->query("SELECT * FROM identity_gateways ORDER BY is_primary DESC, priority ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch recent verification logs
$logs = $pdo->query("SELECT * FROM identity_verification_logs ORDER BY id DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);

admin_page_start('Identity & KYC Gateways', [
    'active' => 'identity_gateways.php',
    'description' => 'Multi-provider BVN and NIN identity verification gateway router with intelligent auto-failover across Monnify, Dojah, NetApps, VerifyMe / QoreID, and custom APIs.',
    'wide' => true,
]);
?>

<?php if ($message): ?><div class="notice ok"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>

<?= admin_kpi_grid([
    ['Gateways', number_format(count($gateways)), 'Configured providers', 'fa-server', ''],
    ['Active', number_format(count(array_filter($gateways, static fn($g): bool => strtolower((string) $g['status']) === 'active'))), 'Enabled routes', 'fa-circle-check', 'blue'],
    ['Live Mode', number_format(count(array_filter($gateways, static fn($g): bool => strtolower((string) $g['environment']) === 'live'))), 'Production credentials', 'fa-bolt', 'purple'],
    ['Recent Checks', number_format(count($logs)), 'Last 50 verifications', 'fa-list-check', 'orange'],
    ['Failed', number_format(count(array_filter($logs, static fn($l): bool => in_array(strtolower((string) $l['status']), ['invalid', 'failed', 'error'], true)))), 'Needs attention', 'fa-triangle-exclamation', 'red'],
]) ?>

<!-- Top Row: Interactive Live Test & Gateway Priority Info -->
<div style="display:grid;grid-template-columns:1fr 1.2fr;gap:20px;margin-bottom:24px;">
  <!-- Live Test Verifier -->
  <section class="panel">
    <h2><i class="fas fa-shield-halved"></i> Live Identity Test Verifier</h2>
    <p class="muted">Test live or simulated BVN and NIN verification calls with intelligent multi-provider failover routing.</p>

    <form method="post" style="display:grid;gap:14px;">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="test_verification">

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <label>
          <strong>Document Type</strong>
          <select name="test_type" style="width:100%;padding:8px;margin-top:4px;">
            <option value="bvn">Bank Verification Number (BVN)</option>
            <option value="nin">National Identity Number (NIN)</option>
          </select>
        </label>
        <label>
          <strong>Routing Provider</strong>
          <select name="test_gateway" style="width:100%;padding:8px;margin-top:4px;">
            <option value="auto">Auto-Failover Multi-Provider (Recommended)</option>
            <?php foreach ($gateways as $gw): ?>
              <option value="<?= (int)$gw['id'] ?>"><?= e($gw['name']) ?> (<?= e($gw['gateway_key']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>

      <label>
        <strong>11-Digit BVN / NIN Number</strong>
        <input type="text" name="test_number" maxlength="11" placeholder="e.g. 22222222222" style="width:100%;padding:8px;margin-top:4px;" required>
      </label>

      <button type="submit" class="button" style="background:#08753a;color:#fff;justify-self:start;">
        <i class="fas fa-magnifying-glass"></i> Dispatch Test Verification
      </button>
    </form>
  </section>

  <!-- Auto-Failover Strategy Card -->
  <section class="panel" style="background:linear-gradient(135deg,#f0fdf4,#fff);border-color:#bbf7d0;">
    <h2><i class="fas fa-network-wired"></i> Auto-Failover Cascade Architecture</h2>
    <div style="font-size:0.92rem;line-height:1.6;color:#1e293b;">
      <p style="margin:0 0 10px;">When a grower or applicant uploads an identity document (BVN / NIN), the system initiates the verification cascade:</p>
      <ol style="margin:0 0 12px;padding-left:20px;">
        <li><strong>Primary Provider (Rank 1)</strong>: Dispatches verification request to designated primary gateway (e.g. Monnify).</li>
        <li><strong>Dynamic Fallback (Rank 2–4)</strong>: If the primary returns a network timeout, insufficient float, or 503 outage, traffic seamlessly cascades to <strong>Dojah</strong>, <strong>NetApps</strong>, or <strong>VerifyMe / QoreID</strong> without user interruption.</li>
        <li><strong>Simulated Staging</strong>: Gateways set to <em>Simulation Mode</em> validate formats and mock NIBSS/NIMC responses locally for testing without billing live credentials.</li>
      </ol>
    </div>
  </section>
</div>

<!-- Configured Gateways List -->
<section class="panel" style="margin-bottom:24px;">
  <div class="user-toolbar">
    <h2 style="margin:0"><i class="fas fa-server"></i> Configured KYC &amp; Identity Providers</h2>
    <span class="meta"><?= number_format(count($gateways)) ?> gateway(s)</span>
  </div>
  <div class="record-list">
    <?php foreach ($gateways as $gw): ?>
      <?php
        $gwActive = strtolower((string) $gw['status']) === 'active';
        $gwLive = strtolower((string) $gw['environment']) === 'live';
      ?>
      <article class="record-row">
        <span class="record-avatar <?= $gwActive ? 'file' : 'log' ?>"><i class="fas fa-server"></i></span>
        <div class="record-main">
          <div class="record-title">
            <?= e($gw['name']) ?>
            <?php if (!empty($gw['is_primary'])): ?><span class="tag ok"><i class="fas fa-star"></i> Primary</span><?php endif; ?>
            <span class="tag <?= $gwActive ? 'ok' : 'bad' ?>"><?= e(strtoupper((string) $gw['status'])) ?></span>
          </div>
          <div class="record-contact">
            <span><i class="fas fa-key"></i><?= e($gw['gateway_key']) ?></span>
            <span><i class="fas fa-plug"></i><?= e($gw['provider_driver']) ?></span>
            <span><i class="fas fa-id-card"></i><?= e(strtoupper((string) $gw['supported_types'])) ?></span>
          </div>
        </div>
        <div class="record-meta">
          <span class="tag info">Rank #<?= (int) $gw['priority'] ?></span>
          <span class="tag <?= $gwLive ? 'ok' : 'warn' ?>"><?= e(strtoupper((string) $gw['environment'])) ?></span>
        </div>
        <div class="record-actions">
          <?php if (empty($gw['is_primary'])): ?>
            <form method="post" style="display:inline;margin:0">
              <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="set_primary">
              <input type="hidden" name="gateway_id" value="<?= (int) $gw['id'] ?>">
              <button type="submit" class="button secondary sm"><i class="fas fa-star"></i> Set Primary</button>
            </form>
          <?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
    <?php if (!$gateways): ?><div class="record-empty">No gateways configured yet.</div><?php endif; ?>
  </div>
</section>

<!-- Recent Identity Verification Logs -->
<section class="panel">
  <div class="user-toolbar">
    <h2 style="margin:0"><i class="fas fa-list-check"></i> Identity Verification Audit Trail</h2>
    <span class="meta"><?= number_format(count($logs)) ?> recent check(s)</span>
  </div>
  <div class="record-list">
    <?php foreach ($logs as $log):
      $masked = substr((string) $log['document_number'], 0, 3) . '*****' . substr((string) $log['document_number'], -3);
      $logStatus = strtolower((string) $log['status']);
      $logTone = in_array($logStatus, ['valid', 'simulated'], true) ? 'ok' : ($logStatus === 'invalid' ? 'warn' : 'bad');
    ?>
      <article class="record-row">
        <span class="record-avatar log"><i class="fas fa-fingerprint"></i></span>
        <div class="record-main">
          <div class="record-title">
            <?= e($log['provider_name'] ?: $log['gateway_key']) ?>
            <span class="tag"><?= e(strtoupper((string) $log['document_type'])) ?></span>
            <span class="tag <?= e($logTone) ?>"><?= e(strtoupper((string) $log['status'])) ?></span>
          </div>
          <div class="record-contact">
            <span><i class="fas fa-hashtag"></i><?= e($masked) ?></span>
            <span><i class="fas fa-check-double"></i>Match: <?= e($log['match_status'] ?: '-') ?></span>
            <span><i class="fas fa-barcode"></i><?= e($log['provider_reference'] ?: '-') ?></span>
          </div>
        </div>
        <div class="record-actions">
          <span class="ref-pill"><i class="far fa-clock"></i><?= e(date('M d, Y H:i', strtotime((string) $log['created_at']))) ?></span>
        </div>
      </article>
    <?php endforeach; ?>
    <?php if (empty($logs)): ?><div class="record-empty">No verification logs recorded yet.</div><?php endif; ?>
  </div>
</section>

<?php admin_page_end(); ?>
