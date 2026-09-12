<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../lib/sms_gateway.php';

$pdo = db();
admin_ensure_schema($pdo);
admin_require($pdo);
sms_gateway_ensure_schema($pdo);

$message = '';
$error = '';
$testOutput = null;

// Handle Gateway Management Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        $action = trim((string) ($_POST['action'] ?? ''));

        // 1. Update Global Routing & Sender ID
        if ($action === 'update_routing') {
            $primarySms = (int) ($_POST['primary_sms_gateway'] ?? 0);
            $primaryWa = (int) ($_POST['primary_whatsapp_gateway'] ?? 0);
            $rawSenderId = trim((string) ($_POST['default_sender_id'] ?? 'NATCODEV'));
            $defaultSenderId = substr(preg_replace('/[^a-zA-Z0-9]/', '', $rawSenderId), 0, 11);
            if (empty($defaultSenderId)) {
                $defaultSenderId = 'NATCODEV';
            }
            $globalEnv = trim((string) ($_POST['global_environment'] ?? 'live'));

            try {
                $pdo->beginTransaction();
                $pdo->exec("UPDATE sms_gateways SET is_primary_sms = 0, is_primary_whatsapp = 0");

                if ($primarySms > 0) {
                    $stmt = $pdo->prepare("UPDATE sms_gateways SET is_primary_sms = 1, status = 'active' WHERE id = ?");
                    $stmt->execute([$primarySms]);
                }
                if ($primaryWa > 0) {
                    $stmt = $pdo->prepare("UPDATE sms_gateways SET is_primary_whatsapp = 1, status = 'active' WHERE id = ?");
                    $stmt->execute([$primaryWa]);
                }

                $stmt = $pdo->prepare("UPDATE sms_gateways SET sender_id = ? WHERE channel_type IN ('sms', 'both')");
                $stmt->execute([$defaultSenderId]);

                if (in_array($globalEnv, ['live', 'simulation'], true)) {
                    $stmt = $pdo->prepare("UPDATE sms_gateways SET environment = ?");
                    $stmt->execute([$globalEnv]);
                }

                $pdo->commit();
                $message = "Routing configuration and Global Alphanumeric Sender ID ('{$defaultSenderId}') saved successfully.";
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Failed to update routing preferences: ' . $e->getMessage();
            }
        }

        // 2. 1-Click Set Default SMS Gateway
        elseif ($action === 'set_default_sms') {
            $gwId = (int) ($_POST['gateway_id'] ?? 0);
            try {
                $pdo->beginTransaction();
                $pdo->exec("UPDATE sms_gateways SET is_primary_sms = 0");
                $stmt = $pdo->prepare("UPDATE sms_gateways SET is_primary_sms = 1, status = 'active' WHERE id = ?");
                $stmt->execute([$gwId]);
                $pdo->commit();
                $message = 'Primary SMS Gateway set successfully. Auto-failover will route here first.';
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Failed to set default SMS gateway: ' . $e->getMessage();
            }
        }

        // 3. 1-Click Set Default WhatsApp Gateway
        elseif ($action === 'set_default_whatsapp') {
            $gwId = (int) ($_POST['gateway_id'] ?? 0);
            try {
                $pdo->beginTransaction();
                $pdo->exec("UPDATE sms_gateways SET is_primary_whatsapp = 0");
                $stmt = $pdo->prepare("UPDATE sms_gateways SET is_primary_whatsapp = 1, status = 'active' WHERE id = ?");
                $stmt->execute([$gwId]);
                $pdo->commit();
                $message = 'Primary WhatsApp Gateway updated successfully.';
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Failed to set default WhatsApp gateway: ' . $e->getMessage();
            }
        }

        // 4. Refresh Live Balance
        elseif ($action === 'check_balance') {
            $gwId = (int) ($_POST['gateway_id'] ?? 0);
            $stmt = $pdo->prepare("SELECT * FROM sms_gateways WHERE id = ? LIMIT 1");
            $stmt->execute([$gwId]);
            $gw = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($gw) {
                $balResult = sms_gateway_check_balance($pdo, $gw);
                if ($balResult['success']) {
                    $message = 'Live balance check succeeded for ' . e((string)$gw['name']) . ': ' . e((string)$balResult['balance_formatted']);
                } else {
                    $error = 'Balance check notice for ' . e((string)$gw['name']) . ': ' . e((string)$balResult['balance_formatted']);
                }
            }
        }

        // 5. Register New Gateway
        elseif ($action === 'create_gateway') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $gatewayKey = strtolower((string) preg_replace('/[^a-z0-9_]/i', '', trim((string) ($_POST['gateway_key'] ?? ''))));
            $channelType = trim((string) ($_POST['channel_type'] ?? 'sms'));
            $providerDriver = trim((string) ($_POST['provider_driver'] ?? 'custom'));
            $baseUrl = trim((string) ($_POST['base_url'] ?? ''));
            $authType = trim((string) ($_POST['auth_type'] ?? 'bearer'));
            $apiKey = trim((string) ($_POST['api_key'] ?? ''));
            $apiSecret = trim((string) ($_POST['api_secret'] ?? ''));
            $rawSenderId = trim((string) ($_POST['sender_id'] ?? 'NATCODEV'));
            $senderId = substr(preg_replace('/[^a-zA-Z0-9]/', '', $rawSenderId), 0, 11);
            if (empty($senderId)) {
                $senderId = 'NATCODEV';
            }
            $environment = trim((string) ($_POST['environment'] ?? 'live'));
            $priority = (int) ($_POST['priority'] ?? 5);

            if (empty($name) || empty($gatewayKey) || empty($baseUrl)) {
                $error = 'Gateway name, unique key, and base URL endpoint are required.';
            } else {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO sms_gateways (gateway_key, name, channel_type, provider_driver, base_url, auth_type, api_key, api_secret, sender_id, environment, status, priority, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, NOW())
                    ");
                    $stmt->execute([
                        $gatewayKey,
                        $name,
                        $channelType,
                        $providerDriver,
                        $baseUrl,
                        $authType,
                        $apiKey,
                        $apiSecret,
                        $senderId,
                        $environment,
                        $priority
                    ]);
                    $message = "Gateway '{$name}' registered successfully!";
                } catch (Throwable $e) {
                    $error = 'Failed to register gateway: ' . $e->getMessage();
                }
            }
        }

        // 6. Update Existing Gateway Details
        elseif ($action === 'update_gateway') {
            $gwId = (int) ($_POST['gateway_id'] ?? 0);
            $name = trim((string) ($_POST['name'] ?? ''));
            $baseUrl = trim((string) ($_POST['base_url'] ?? ''));
            $apiKey = trim((string) ($_POST['api_key'] ?? ''));
            $apiSecret = trim((string) ($_POST['api_secret'] ?? ''));
            $rawSenderId = trim((string) ($_POST['sender_id'] ?? 'NATCODEV'));
            $senderId = substr(preg_replace('/[^a-zA-Z0-9]/', '', $rawSenderId), 0, 11);
            if (empty($senderId)) {
                $senderId = 'NATCODEV';
            }
            $status = trim((string) ($_POST['status'] ?? 'active'));
            $environment = trim((string) ($_POST['environment'] ?? 'live'));

            try {
                $stmt = $pdo->prepare("
                    UPDATE sms_gateways 
                    SET name = ?, base_url = ?, api_key = ?, api_secret = ?, sender_id = ?, status = ?, environment = ?
                    WHERE id = ?
                ");
                $stmt->execute([$name, $baseUrl, $apiKey, $apiSecret, $senderId, $status, $environment, $gwId]);
                $message = "Gateway '{$name}' updated successfully.";
            } catch (Throwable $e) {
                $error = 'Failed to update gateway: ' . $e->getMessage();
            }
        }

        // 7. Delete Gateway
        elseif ($action === 'delete_gateway') {
            $gwId = (int) ($_POST['gateway_id'] ?? 0);
            try {
                $stmt = $pdo->prepare("DELETE FROM sms_gateways WHERE id = ? AND gateway_key NOT IN ('ebulksms', 'paylessbulksms')");
                $stmt->execute([$gwId]);
                $message = "Gateway removed.";
            } catch (Throwable $e) {
                $error = 'Failed to delete gateway: ' . $e->getMessage();
            }
        }

        // 8. Interactive Live Test Dispatcher
        elseif ($action === 'send_test_message') {
            $testRecipient = trim((string) ($_POST['test_recipient'] ?? ''));
            $testChannel = trim((string) ($_POST['test_channel'] ?? 'sms'));
            $testMsg = trim((string) ($_POST['test_message'] ?? ''));
            $testGatewayId = trim((string) ($_POST['test_gateway'] ?? 'auto'));

            if (empty($testRecipient) || empty($testMsg)) {
                $error = 'Recipient phone number and test message are required.';
            } else {
                $options = ['user_id' => $_SESSION['admin_user']['id'] ?? null];
                if ($testGatewayId !== 'auto' && is_numeric($testGatewayId) && (int)$testGatewayId > 0) {
                    $options['gateway_id'] = (int)$testGatewayId;
                }

                if ($testChannel === 'whatsapp') {
                    $testOutput = app_send_whatsapp($testRecipient, $testMsg, $options);
                } else {
                    $testOutput = app_send_sms($testRecipient, $testMsg, $options);
                }

                if (!empty($testOutput['success'])) {
                    $refStr = $testOutput['reference'] ?? 'OK';
                    $statusStr = ucfirst((string) ($testOutput['status'] ?? 'delivered'));
                    $message = "Test " . strtoupper($testChannel) . " dispatched successfully! Status: {$statusStr}, Ref: {$refStr}";
                } else {
                    $error = "Test dispatch notice: " . ($testOutput['error'] ?? 'Check logs below.');
                }
            }
        }
    }
}

// Fetch Gateways & Logs
$stmt = $pdo->query("SELECT * FROM sms_gateways ORDER BY is_primary_sms DESC, is_primary_whatsapp DESC, priority ASC, id ASC");
$gateways = $stmt->fetchAll(PDO::FETCH_ASSOC);

$currentPrimarySms = null;
$currentPrimaryWa = null;
$currentSenderId = sms_get_global_sender_id($pdo);

foreach ($gateways as $gw) {
    if (!empty($gw['is_primary_sms'])) {
        $currentPrimarySms = $gw;
    }
    if (!empty($gw['is_primary_whatsapp'])) {
        $currentPrimaryWa = $gw;
    }
}

// Fetch recent SMS logs
$logsStmt = $pdo->query("SELECT * FROM sms_logs ORDER BY id DESC LIMIT 50");
$smsLogs = $logsStmt->fetchAll(PDO::FETCH_ASSOC);

admin_page_start('SMS & WhatsApp Gateways', [
    'active' => 'sms_gateways.php',
    'description' => 'Manage telecommunication gateways, live balances, smart routing failover, alphanumeric sender IDs, and delivery audit logs.',
    'wide' => true,
]);
?>

<?php if ($message): ?><div class="notice ok"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px;">
  <!-- Global Routing & Sender ID -->
  <section class="panel">
    <h2><i class="fas fa-sliders"></i> Global Routing & Sender Configuration</h2>
    <form method="post" style="display:grid;gap:14px;">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="update_routing">

      <label>
        <strong>Primary SMS Gateway</strong>
        <select name="primary_sms_gateway" style="width:100%;padding:8px;margin-top:4px;">
          <option value="0">-- Select Primary SMS Gateway --</option>
          <?php foreach ($gateways as $gw): if (in_array($gw['channel_type'], ['sms', 'both'], true)): ?>
            <option value="<?= (int) $gw['id'] ?>" <?= !empty($gw['is_primary_sms']) ? 'selected' : '' ?>>
              <?= e($gw['name']) ?> (<?= e($gw['gateway_key']) ?>) <?= !empty($gw['is_primary_sms']) ? '★ [Active Primary]' : '' ?>
            </option>
          <?php endif; endforeach; ?>
        </select>
      </label>

      <label>
        <strong>Primary WhatsApp Gateway</strong>
        <select name="primary_whatsapp_gateway" style="width:100%;padding:8px;margin-top:4px;">
          <option value="0">-- Select Primary WhatsApp Gateway --</option>
          <?php foreach ($gateways as $gw): if (in_array($gw['channel_type'], ['whatsapp', 'both'], true)): ?>
            <option value="<?= (int) $gw['id'] ?>" <?= !empty($gw['is_primary_whatsapp']) ? 'selected' : '' ?>>
              <?= e($gw['name']) ?> (<?= e($gw['gateway_key']) ?>) <?= !empty($gw['is_primary_whatsapp']) ? '★ [Active Primary]' : '' ?>
            </option>
          <?php endif; endforeach; ?>
        </select>
      </label>

      <label>
        <strong>Global Alphanumeric Sender ID (Max 11 Alphanumeric Chars)</strong>
        <input type="text" name="default_sender_id" maxlength="11" value="<?= e($currentSenderId) ?>" style="width:100%;padding:8px;margin-top:4px;" placeholder="NATCODEV" required>
        <small class="muted">Applied across SMS gateways (eBulkSMS, Payless, Termii) for instant brand recognition.</small>
      </label>

      <label>
        <strong>Global Dispatch Environment</strong>
        <select name="global_environment" style="width:100%;padding:8px;margin-top:4px;">
          <option value="live">Live (Deliver actual SMS/WhatsApp to telecom carriers)</option>
          <option value="simulation">Simulation Mode (Mock dispatches without telecom charges)</option>
        </select>
      </label>

      <button type="submit" class="button primary" style="justify-self:start;">Save Routing Configuration</button>
    </form>
  </section>

  <!-- Interactive Live Test Dispatcher -->
  <section class="panel">
    <h2><i class="fas fa-paper-plane"></i> Live Test Dispatcher</h2>
    <form method="post" style="display:grid;gap:14px;">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="send_test_message">

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <label>
          <strong>Channel</strong>
          <select name="test_channel" style="width:100%;padding:8px;margin-top:4px;">
            <option value="sms">SMS Text Message</option>
            <option value="whatsapp">WhatsApp Message</option>
          </select>
        </label>
        <label>
          <strong>Target Gateway</strong>
          <select name="test_gateway" style="width:100%;padding:8px;margin-top:4px;">
            <option value="auto">Auto-Failover Smart Routing (Recommended)</option>
            <?php foreach ($gateways as $gw): ?>
              <option value="<?= (int)$gw['id'] ?>"><?= e($gw['name']) ?> (<?= e($gw['channel_type']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>

      <label>
        <strong>Recipient Phone Number</strong>
        <input type="text" name="test_recipient" placeholder="e.g. 08012345678 or 2348012345678" style="width:100%;padding:8px;margin-top:4px;" required>
      </label>

      <label>
        <strong>Test Message</strong>
        <textarea name="test_message" rows="3" style="width:100%;padding:8px;margin-top:4px;" required>NATCODEV Telecom Gateway Test: System operational at <?= date('Y-m-d H:i:s') ?>.</textarea>
      </label>

      <button type="submit" class="button" style="background:#08753a;color:#fff;justify-self:start;"><i class="fas fa-paper-plane"></i> Dispatch Live Test Message</button>
    </form>
  </section>
</div>

<!-- Configured Gateways Overview -->
<section class="panel" style="margin-bottom:24px;">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
    <h2 style="margin:0;"><i class="fas fa-tower-broadcast"></i> Configured Telecommunication Gateways</h2>
  </div>

  <table class="data-table" style="width:100%;">
    <thead>
      <tr>
        <th>Gateway & Driver</th>
        <th>Channels</th>
        <th>Sender ID</th>
        <th>Last Balance Check</th>
        <th>Mode</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($gateways as $gw): ?>
        <tr>
          <td>
            <strong><?= e($gw['name']) ?></strong>
            <?php if (!empty($gw['is_primary_sms'])): ?><span class="tag green" style="font-size:0.75rem;margin-left:4px;">Primary SMS</span><?php endif; ?>
            <?php if (!empty($gw['is_primary_whatsapp'])): ?><span class="tag blue" style="font-size:0.75rem;margin-left:4px;">Primary WhatsApp</span><?php endif; ?>
            <br><small class="muted">Key: <?= e($gw['gateway_key']) ?> | Driver: <?= e($gw['provider_driver']) ?></small>
          </td>
          <td><span class="tag"><?= strtoupper(e($gw['channel_type'])) ?></span></td>
          <td><code><?= e($gw['sender_id'] ?: 'NATCODEV') ?></code></td>
          <td>
            <?= e($gw['last_balance_check'] ?: 'Not checked yet') ?>
            <?php if (!empty($gw['last_balance_at'])): ?>
              <br><small class="muted"><?= e(date('M d, H:i', strtotime($gw['last_balance_at']))) ?></small>
            <?php endif; ?>
          </td>
          <td><span class="tag <?= $gw['environment'] === 'live' ? 'green' : 'amber' ?>"><?= strtoupper(e($gw['environment'])) ?></span></td>
          <td><span class="tag <?= $gw['status'] === 'active' ? 'green' : 'red' ?>"><?= strtoupper(e($gw['status'])) ?></span></td>
          <td>
            <div style="display:flex;gap:6px;flex-wrap:wrap;">
              <!-- Refresh Balance Form -->
              <form method="post" style="display:inline;">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="check_balance">
                <input type="hidden" name="gateway_id" value="<?= (int) $gw['id'] ?>">
                <button type="submit" class="button secondary" style="padding:4px 8px;font-size:0.8rem;" title="Check Live Balance"><i class="fas fa-rotate"></i> Balance</button>
              </form>

              <!-- Set SMS Primary -->
              <?php if (in_array($gw['channel_type'], ['sms', 'both'], true) && empty($gw['is_primary_sms'])): ?>
                <form method="post" style="display:inline;">
                  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="set_default_sms">
                  <input type="hidden" name="gateway_id" value="<?= (int) $gw['id'] ?>">
                  <button type="submit" class="button secondary" style="padding:4px 8px;font-size:0.8rem;" title="Make Primary SMS"><i class="fas fa-check"></i> Set SMS</button>
                </form>
              <?php endif; ?>

              <!-- Set WhatsApp Primary -->
              <?php if (in_array($gw['channel_type'], ['whatsapp', 'both'], true) && empty($gw['is_primary_whatsapp'])): ?>
                <form method="post" style="display:inline;">
                  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="set_default_whatsapp">
                  <input type="hidden" name="gateway_id" value="<?= (int) $gw['id'] ?>">
                  <button type="submit" class="button secondary" style="padding:4px 8px;font-size:0.8rem;" title="Make Primary WhatsApp"><i class="fas fa-comment-dots"></i> Set WA</button>
                </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>

<!-- Recent Delivery Logs -->
<section class="panel">
  <h2><i class="fas fa-list-check"></i> Recent Gateway Delivery Logs</h2>
  <div style="overflow-x:auto;">
    <table class="data-table" style="width:100%;">
      <thead>
        <tr>
          <th>Date/Time</th>
          <th>Recipient</th>
          <th>Channel</th>
          <th>Gateway</th>
          <th>Sender</th>
          <th>Message</th>
          <th>Status</th>
          <th>Reference</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($smsLogs as $log): ?>
          <tr>
            <td><small><?= e(date('M d, Y H:i:s', strtotime($log['created_at']))) ?></small></td>
            <td><strong><?= e($log['recipient']) ?></strong></td>
            <td><span class="tag <?= $log['channel'] === 'whatsapp' ? 'blue' : 'green' ?>"><?= strtoupper(e($log['channel'])) ?></span></td>
            <td><?= e($log['gateway_key'] ?: '-') ?></td>
            <td><code><?= e($log['sender_id'] ?: 'NATCODEV') ?></code></td>
            <td><small><?= e(mb_strimwidth((string)$log['message'], 0, 60, '...')) ?></small></td>
            <td>
              <span class="tag <?= in_array($log['status'], ['delivered', 'sent'], true) ? 'green' : ($log['status'] === 'simulated' ? 'blue' : 'red') ?>">
                <?= strtoupper(e($log['status'])) ?>
              </span>
            </td>
            <td><code><?= e($log['provider_reference'] ?: '-') ?></code></td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($smsLogs)): ?>
          <tr><td colspan="8" style="text-align:center;" class="muted">No SMS or WhatsApp logs recorded yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?php admin_page_end(); ?>
