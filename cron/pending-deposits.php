<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/gateway-manager.php';
require_once __DIR__ . '/../lib/monnify.php';
require_once __DIR__ . '/../lib/paystack.php';
require_once __DIR__ . '/../lib/wallet.php';

app_require_cli('pending-deposits');

$pdo = db();
wallet_ensure_schema($pdo);

// Re-query the provider for wallet funding requests that are still pending
// across all supported payment gateways (Monnify, Paystack, Fincra, Woven, Korapay, Allawee)
$stmt = $pdo->query("
    SELECT * FROM wallet_transactions
    WHERE status = 'pending'
      AND provider IN ('monnify', 'paystack', 'fincra', 'woven', 'korapay', 'allawee')
      AND (type IN ('credit', 'funding') OR direction = 'inflow')
      AND user_id > 0
      AND reference IS NOT NULL AND reference <> ''
      AND created_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)
    LIMIT 500
");
$deposits = $stmt->fetchAll(PDO::FETCH_ASSOC);

$confirmed = 0;
$stillPending = 0;
$errors = 0;
foreach ($deposits as $deposit) {
    $provider = strtolower((string) $deposit['provider']);
    $reference = (string) $deposit['reference'];
    $userId = (int) $deposit['user_id'];

    $result = gateway_verify_deposit($pdo, $userId, $reference, $provider);
    if ((string) ($result['status'] ?? '') === 'completed') {
        $confirmed++;
    } elseif ((string) ($result['status'] ?? '') === 'pending') {
        $stillPending++;
    } else {
        error_log("pending-deposits: {$provider} verification failed for {$reference}: " . ($result['error'] ?? 'unknown'));
        $errors++;
    }
}

echo "Pending deposits reconciliation: confirmed={$confirmed} still-pending={$stillPending} errors={$errors}" . PHP_EOL;
