<?php
declare(strict_types=1);

ob_implicit_flush(true);
while (ob_get_level()) {
    ob_end_flush();
}

/**
 * Multi-Gateway Payment Suite
 * Tests Fincra, Woven, Korapay, Allawee, Monnify, and Paystack integration:
 * - Driver configuration and detection
 * - Bank listing and account resolution across all gateways
 * - Deposit checkout initialization and fallback routing
 * - Cryptographic webhook signature verification and tamper detection
 * - Idempotent wallet crediting (strict double-credit prevention)
 * - Withdrawal payout initiation and reconciliation queries
 * - Admin connectivity diagnostic probes
 */

require_once __DIR__ . '/TestHarness.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/wallet.php';
require_once __DIR__ . '/../lib/wallet-withdrawals.php';
require_once __DIR__ . '/../lib/gateway-manager.php';
require_once __DIR__ . '/../lib/gateways/fincra.php';
require_once __DIR__ . '/../lib/gateways/woven.php';
require_once __DIR__ . '/../lib/gateways/korapay.php';
require_once __DIR__ . '/../lib/gateways/allawee.php';

$pdo = db();
TestHarness::assertTestDatabase($pdo);
wallet_ensure_schema($pdo);

TestHarness::start('Payment Gateways & Failover Routing Test Suite');

// Setup fixture user
$suiteRunId = time() . '_' . mt_rand(1000, 9999);
$testEmail = "gateway_suite_{$suiteRunId}@natcodev.test";
$stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, created_at) VALUES ('Gateway Test User', ?, ?, 'grower', NOW())");
$stmt->execute([$testEmail, password_hash('Pass123!', PASSWORD_DEFAULT)]);
$testUserId = (int) $pdo->lastInsertId();

$wallet = wallet_get_or_create($pdo, $testUserId);
$walletId = (int) $wallet['id'];

// --------------------------------------------------------------------------
// SECTION 1: Gateway Configuration & Detection
// --------------------------------------------------------------------------
echo "\n--- Section 1: Gateway Configuration & Detection ---\n";

$configured = gateway_get_configured_gateways();
TestHarness::assertTrue(is_array($configured), 'gateway_get_configured_gateways returns an array');
TestHarness::assertTrue(count($configured) >= 4, 'Multiple gateways are actively configured in .env', 'Configured: ' . implode(', ', $configured));

foreach (['monnify', 'paystack', 'fincra', 'woven', 'korapay', 'allawee'] as $provider) {
    $isCfg = gateway_is_configured($provider);
    TestHarness::assertTrue($isCfg, "Gateway [{$provider}] is configured");
}

TestHarness::assertFalse(gateway_is_configured('invalid_gateway_xyz'), 'Invalid gateway is correctly reported as not configured');

// --------------------------------------------------------------------------
// SECTION 2: Bank Listing Across Gateways
// --------------------------------------------------------------------------
echo "\n--- Section 2: Bank Listing Across Gateways ---\n";

foreach (['fincra', 'woven', 'korapay', 'allawee', 'paystack', 'monnify'] as $provider) {
    $banks = gateway_list_banks($provider);
    TestHarness::assertTrue(!empty($banks), "Bank list retrieved for [{$provider}]");
    TestHarness::assertTrue(isset($banks[0]['name']) && isset($banks[0]['code']), "Bank list items for [{$provider}] have 'name' and 'code'");
}

// --------------------------------------------------------------------------
// SECTION 3: Account Name Resolution Across Gateways
// --------------------------------------------------------------------------
echo "\n--- Section 3: Account Name Resolution Across Gateways ---\n";

foreach (['fincra', 'woven', 'korapay', 'allawee', 'paystack', 'monnify'] as $provider) {
    $resolution = gateway_resolve_bank_account('058', '0123456789', $provider);
    TestHarness::assertTrue(!empty($resolution['success']), "Account resolution succeeded for [{$provider}]");
    TestHarness::assertTrue(!empty($resolution['account_name']), "Account name resolved for [{$provider}]: " . ($resolution['account_name'] ?? 'none'));
}

// Automated provider fallback resolution (provider = '')
$autoResolution = gateway_resolve_bank_account('058', '0123456789', '');
TestHarness::assertTrue(!empty($autoResolution['success']), 'Auto account resolution succeeded using priority chain');

// --------------------------------------------------------------------------
// SECTION 4: Deposit Checkout Initialization & Fallback Routing
// --------------------------------------------------------------------------
echo "\n--- Section 4: Deposit Checkout Initialization & Fallback Routing ---\n";

$returnUrl = 'http://localhost/win/dashboard/wallet.php';

// Test explicit gateway initialization for each provider
foreach (['fincra', 'woven', 'korapay', 'allawee'] as $provider) {
    $deposit = gateway_initialize_deposit($pdo, $testUserId, 2500.00, $provider, $returnUrl);
    TestHarness::assertTrue(!empty($deposit['success']), "Deposit checkout initialized for [{$provider}]");
    TestHarness::assertTrue(!empty($deposit['payment_url']), "Deposit checkout URL returned for [{$provider}]");
    TestHarness::assertTrue(!empty($deposit['reference']), "Deposit reference created for [{$provider}]: " . ($deposit['reference'] ?? ''));

    // Check transaction row in database
    $tx = $pdo->prepare("SELECT * FROM wallet_transactions WHERE reference = ?");
    $tx->execute([$deposit['reference']]);
    $txRow = $tx->fetch(PDO::FETCH_ASSOC);
    TestHarness::assertTrue(!empty($txRow), "Transaction record exists in wallet_transactions for [{$provider}]");
    TestHarness::assertEqual('funding', $txRow['type'], "Transaction type is 'funding'");
    TestHarness::assertEqual('pending', $txRow['status'], "Initial transaction status is 'pending'");
    TestHarness::assertEqual(2500.00, (float) $txRow['amount'], "Transaction amount matches requested amount");
}

// Test automatic fallback when an invalid or failed provider is requested
$fallbackDeposit = gateway_initialize_deposit($pdo, $testUserId, 1500.00, 'auto', $returnUrl);
TestHarness::assertTrue(!empty($fallbackDeposit['success']), "Automatic provider deposit initialization succeeded");
TestHarness::assertTrue(!empty($fallbackDeposit['payment_url']), "Automatic provider returned payment URL");
TestHarness::assertTrue(!empty($fallbackDeposit['provider']), "Resolved provider: " . ($fallbackDeposit['provider'] ?? ''));

// --------------------------------------------------------------------------
// SECTION 5: Cryptographic Webhook Signature Verification
// --------------------------------------------------------------------------
echo "\n--- Section 5: Cryptographic Webhook Signature Verification ---\n";

// 5a. Fincra Webhook Signature (HMAC-SHA512)
$fincraSecret = (string) app_env('FINCRA_WEBHOOK_KEY', '5f717263583340f3b1bc7489f3b2d47b');
$fincraPayload = json_encode(['event' => 'charge.successful', 'data' => ['reference' => 'FINCRA_TEST_123', 'amount' => 5000]]);
$validFincraSig = hash_hmac('sha512', $fincraPayload, $fincraSecret);
$invalidFincraSig = hash_hmac('sha512', $fincraPayload, 'wrong_secret_key');

TestHarness::assertTrue(fincra_webhook_is_valid($fincraPayload, $validFincraSig, $fincraSecret), 'Fincra webhook signature matches valid HMAC-SHA512');
TestHarness::assertFalse(fincra_webhook_is_valid($fincraPayload, $invalidFincraSig, $fincraSecret), 'Fincra webhook rejects invalid signature');
TestHarness::assertFalse(fincra_webhook_is_valid($fincraPayload . 'tampered', $validFincraSig, $fincraSecret), 'Fincra webhook rejects tampered payload');

// 5b. Woven Webhook Signature (HMAC-SHA512)
$wovenSecret = (string) app_env('WOVEN_API_SECRET', 'vb_ts_a143e315321d2364a935c7e9883c340e43290f015685');
$wovenPayload = json_encode(['event' => 'collection.successful', 'data' => ['payment_reference' => 'WOV_TEST_123', 'amount' => 5000]]);
$validWovenSig = hash_hmac('sha512', $wovenPayload, $wovenSecret);
$invalidWovenSig = 'bad_sig_woven';

TestHarness::assertTrue(woven_webhook_is_valid($wovenPayload, $validWovenSig, $wovenSecret), 'Woven webhook signature matches valid HMAC-SHA512');
TestHarness::assertFalse(woven_webhook_is_valid($wovenPayload, $invalidWovenSig, $wovenSecret), 'Woven webhook rejects invalid signature');

// 5c. Korapay Webhook Signature (HMAC-SHA256)
$korapaySecret = (string) app_env('KORAPAY_SECRET_KEY', 'sk_test_k1MXqDCmsmS3nMZ52PFivCD4Xyoew1TAZu9kstGW');
$korapayPayload = json_encode(['event' => 'charge.success', 'data' => ['reference' => 'KORA_TEST_123', 'amount' => 5000]]);
$validKorapaySig = hash_hmac('sha256', $korapayPayload, $korapaySecret);
$invalidKorapaySig = 'tampered_korapay_sig';

TestHarness::assertTrue(korapay_webhook_is_valid($korapayPayload, $validKorapaySig, $korapaySecret), 'Korapay webhook signature matches valid HMAC-SHA256');
TestHarness::assertFalse(korapay_webhook_is_valid($korapayPayload, $invalidKorapaySig, $korapaySecret), 'Korapay webhook rejects invalid signature');

// 5d. Allawee Webhook Signature (HMAC-SHA256)
$allaweeSecret = (string) app_env('ALLAWEE_WEBHOOK_SECRET', 'wh.31ue9qHdQAgPLzhQe');
$allaweePayload = json_encode(['event' => 'payment.successful', 'data' => ['reference' => 'ALLA_TEST_123', 'amount' => 5000]]);
$validAllaweeSig = hash_hmac('sha256', $allaweePayload, $allaweeSecret);
$invalidAllaweeSig = 'tampered_allawee_sig';

TestHarness::assertTrue(allawee_webhook_is_valid($allaweePayload, $validAllaweeSig, $allaweeSecret), 'Allawee webhook signature matches valid HMAC-SHA256');
TestHarness::assertFalse(allawee_webhook_is_valid($allaweePayload, $invalidAllaweeSig, $allaweeSecret), 'Allawee webhook rejects invalid signature');

// --------------------------------------------------------------------------
// SECTION 6: Idempotent Wallet Crediting (Double-Credit Prevention)
// --------------------------------------------------------------------------
echo "\n--- Section 6: Idempotent Wallet Crediting ---\n";

$walletBefore = wallet_get_or_create($pdo, $testUserId);
$initialBalance = (float) $walletBefore['balance'];

$depositRef = 'IDEMPOTENT_DEPOSIT_' . $suiteRunId;
$creditAmount = 10000.00;

// First credit attempt
$c1 = wallet_credit_once($pdo, $testUserId, $creditAmount, 'Fincra funding test', $depositRef, 'fincra', $depositRef, ['test' => true]);
TestHarness::assertTrue(!empty($c1['success']), 'First credit attempt succeeds');
TestHarness::assertFalse(!empty($c1['duplicate']), 'First credit is not flagged as duplicate');
TestHarness::assertTrue(!empty($c1['credited']), 'First credit successfully moved funds');

$walletMid = wallet_get_or_create($pdo, $testUserId);
$midBalance = (float) $walletMid['balance'];
TestHarness::assertEqual($initialBalance + $creditAmount, $midBalance, 'Wallet balance increased exactly once by credit amount');

// Second credit attempt with identical reference (simulating webhook replay or double webhook delivery)
$c2 = wallet_credit_once($pdo, $testUserId, $creditAmount, 'Fincra funding test repeat', $depositRef, 'fincra', $depositRef, ['test' => true]);
TestHarness::assertTrue(!empty($c2['success']), 'Second credit attempt returns success response');
TestHarness::assertTrue(!empty($c2['duplicate']), 'Second credit attempt is identified as DUPLICATE');
TestHarness::assertFalse(!empty($c2['credited']), 'Second credit attempt did NOT add funds again');

$walletAfter = wallet_get_or_create($pdo, $testUserId);
$finalBalance = (float) $walletAfter['balance'];
TestHarness::assertEqual($midBalance, $finalBalance, 'Wallet balance unchanged after duplicate webhook delivery (zero double-credit)');

// Third credit attempt with different reference but same transaction should work
$depositRef2 = 'IDEMPOTENT_DEPOSIT_2_' . $suiteRunId;
$c3 = wallet_credit_once($pdo, $testUserId, 3000.00, 'Korapay funding test', $depositRef2, 'korapay', $depositRef2, ['test' => true]);
TestHarness::assertTrue(!empty($c3['success']), 'Distinct reference credit succeeds');
$walletEnd = wallet_get_or_create($pdo, $testUserId);
TestHarness::assertEqual($finalBalance + 3000.00, (float) $walletEnd['balance'], 'Balance updated correctly for separate reference');

// --------------------------------------------------------------------------
// SECTION 7: Withdrawal Payout Initiation & Status Queries
// --------------------------------------------------------------------------
echo "\n--- Section 7: Withdrawal Payout Initiation & Status Queries ---\n";

$testUser = ['id' => $testUserId, 'name' => 'Gateway Test User', 'email' => $testEmail];

foreach (['fincra', 'woven', 'korapay', 'allawee'] as $payoutProvider) {
    $req = wallet_request_withdrawal($pdo, $testUser, [
        'amount' => 1000.00,
        'provider' => $payoutProvider,
        'bank_code' => '058',
        'bank_name' => 'GTBank',
        'account_number' => '0123456789',
        'account_name' => 'Gateway Test User',
        'note' => "Automated withdrawal via {$payoutProvider}",
    ]);

    TestHarness::assertTrue(!empty($req['success']), "Withdrawal request submitted for [{$payoutProvider}]");
    $wdRef = (string) ($req['reference'] ?? '');
    TestHarness::assertTrue($wdRef !== '', "Withdrawal reference generated for [{$payoutProvider}]");

    // Query withdrawal row
    $wdStmt = $pdo->prepare("SELECT * FROM wallet_withdrawals WHERE reference = ?");
    $wdStmt->execute([$wdRef]);
    $wdRow = $wdStmt->fetch(PDO::FETCH_ASSOC);
    TestHarness::assertTrue(!empty($wdRow), "Withdrawal record created in database for [{$payoutProvider}]");

    // Query payout status
    $statusQuery = gateway_query_payout_status($wdRow);
    TestHarness::assertTrue(isset($statusQuery['state']), "gateway_query_payout_status returns state for [{$payoutProvider}]");

    // Admin process withdrawal
    $adminProcess = wallet_admin_process_withdrawal($pdo, (int) $wdRow['id'], (int) $testUserId, 'approve', 'Test approval note');
    TestHarness::assertTrue(!empty($adminProcess['success']), "Admin payout processing succeeded for [{$payoutProvider}]");

    // Verify hold balance was released upon finalization
    $wdStmt->execute([$wdRef]);
    $updatedWd = $wdStmt->fetch(PDO::FETCH_ASSOC);
    TestHarness::assertEqual(1, (int) $updatedWd['funds_released'], "Hold balance released on withdrawal for [{$payoutProvider}]");
    TestHarness::assertEqual('paid', $updatedWd['status'], "Withdrawal status updated to 'paid' for [{$payoutProvider}]");
}

// --------------------------------------------------------------------------
// SECTION 8: Gateway Connectivity Diagnostic Probes
// --------------------------------------------------------------------------
echo "\n--- Section 8: Gateway Connectivity Diagnostic Probes ---\n";

foreach (['monnify', 'paystack', 'fincra', 'woven', 'korapay', 'allawee'] as $provider) {
    $probe = gateway_test_connection($provider);
    TestHarness::assertTrue(isset($probe['success']), "Connectivity probe returned structured result for [{$provider}]");
    TestHarness::assertTrue(isset($probe['latency_ms']), "Connectivity probe measured latency for [{$provider}]: " . ($probe['latency_ms'] ?? 0) . 'ms');
    TestHarness::assertTrue(!empty($probe['success']), "Connectivity probe succeeded for [{$provider}]");
}

// --------------------------------------------------------------------------
// SECTION 9: Circuit Breaker State Machine & Automatic Cooldown Recovery
// --------------------------------------------------------------------------
echo "\n--- Section 9: Circuit Breaker State Machine & Cooldown Recovery ---\n";

require_once __DIR__ . '/../lib/circuit-breaker.php';
circuit_breaker_ensure_schema($pdo);

$testProvider = 'fincra';

// 9.1 Initial state must be CLOSED
circuit_breaker_reset($pdo, $testProvider, 1);
$initialState = circuit_breaker_get_state($pdo, $testProvider);
TestHarness::assertEqual('closed', $initialState['state'], "Circuit Breaker: Initial state is CLOSED for [{$testProvider}]");
TestHarness::assertTrue(circuit_breaker_is_available($pdo, $testProvider), "Circuit Breaker: Provider is available in CLOSED state");
TestHarness::assertEqual(0, (int) $initialState['failure_count'], "Circuit Breaker: Failure count starts at 0");

// 9.2 Record failures below threshold (threshold is 3 by default)
circuit_breaker_record_failure($pdo, $testProvider, 'Temporary network timeout 1');
circuit_breaker_record_failure($pdo, $testProvider, 'Temporary network timeout 2');
$midState = circuit_breaker_get_state($pdo, $testProvider);
TestHarness::assertEqual('closed', $midState['state'], "Circuit Breaker: State remains CLOSED below threshold");
TestHarness::assertEqual(2, (int) $midState['failure_count'], "Circuit Breaker: Failure count increments to 2");
TestHarness::assertTrue(circuit_breaker_is_available($pdo, $testProvider), "Circuit Breaker: Provider remains available below threshold");

// 9.3 3rd consecutive failure trips breaker to OPEN with cooldown
$tripRes = circuit_breaker_record_failure($pdo, $testProvider, 'Third network failure - tripping');
TestHarness::assertEqual('open', $tripRes['state'], "Circuit Breaker: Breaker trips to OPEN on threshold reached");
TestHarness::assertFalse(circuit_breaker_is_available($pdo, $testProvider), "Circuit Breaker: Provider is UNAVAILABLE in OPEN state");
TestHarness::assert(!empty($tripRes['cooldown_until']), "Circuit Breaker: Cooldown expiration timestamp set");

// 9.4 Cooldown Transition: When cooldown expires, transitions to HALF-OPEN
// Simulate cooldown expiration by backdating cooldown_until to 10 seconds ago
$pdo->prepare("UPDATE gateway_circuit_breakers SET cooldown_until = DATE_SUB(NOW(), INTERVAL 10 SECOND) WHERE provider = ?")
    ->execute([$testProvider]);

$halfOpenState = circuit_breaker_get_state($pdo, $testProvider);
TestHarness::assertEqual('half_open', $halfOpenState['state'], "Circuit Breaker: Transitioned from OPEN to HALF-OPEN upon cooldown expiration");
TestHarness::assertTrue(circuit_breaker_is_available($pdo, $testProvider), "Circuit Breaker: Provider is available for trial probe in HALF-OPEN state");

// 9.5 Half-Open Probe Success: Recovers to CLOSED and resets failures
circuit_breaker_record_success($pdo, $testProvider);
$recoveredState = circuit_breaker_get_state($pdo, $testProvider);
TestHarness::assertEqual('closed', $recoveredState['state'], "Circuit Breaker: Recovers to CLOSED upon successful trial probe");
TestHarness::assertEqual(0, (int) $recoveredState['failure_count'], "Circuit Breaker: Failure count reset to 0 after recovery");
TestHarness::assertTrue(circuit_breaker_is_available($pdo, $testProvider), "Circuit Breaker: Fully available after CLOSED recovery");

// 9.6 Half-Open Probe Failure: If trial probe fails, re-trips back to OPEN with fresh cooldown
circuit_breaker_record_failure($pdo, $testProvider, 'Error 1');
circuit_breaker_record_failure($pdo, $testProvider, 'Error 2');
circuit_breaker_record_failure($pdo, $testProvider, 'Error 3');
$pdo->prepare("UPDATE gateway_circuit_breakers SET cooldown_until = DATE_SUB(NOW(), INTERVAL 10 SECOND) WHERE provider = ?")
    ->execute([$testProvider]);
$checkHalfOpen = circuit_breaker_get_state($pdo, $testProvider);
TestHarness::assertEqual('half_open', $checkHalfOpen['state'], "Circuit Breaker: Back in HALF-OPEN state");

// Trial probe fails!
$reTrip = circuit_breaker_record_failure($pdo, $testProvider, 'Trial probe failed during half-open');
TestHarness::assertEqual('open', $reTrip['state'], "Circuit Breaker: Re-trips to OPEN when HALF-OPEN trial probe fails");
TestHarness::assertFalse(circuit_breaker_is_available($pdo, $testProvider), "Circuit Breaker: Unavailable after re-trip to OPEN");

// 9.7 Manual Reset by Super Admin
circuit_breaker_reset($pdo, $testProvider, 1);
$resetState = circuit_breaker_get_state($pdo, $testProvider);
TestHarness::assertEqual('closed', $resetState['state'], "Circuit Breaker: Manually reset by Super Admin to CLOSED");
TestHarness::assertTrue(circuit_breaker_is_available($pdo, $testProvider), "Circuit Breaker: Available after manual reset");

// --------------------------------------------------------------------------
// SECTION 10: Deposit & Withdrawal Fee Accounting Invariant (Added, Not Deducted)
// --------------------------------------------------------------------------
echo "\n--- Section 10: Deposit & Withdrawal Fee Accounting Invariant ---\n";

// Configure fee schedule in settings
$pdo->prepare("INSERT INTO settings (key_name, value) VALUES ('wallet_deposit_flat_fee', '150'), ('wallet_deposit_percent_fee', '2.0'), ('wallet_withdrawal_flat_fee', '100'), ('wallet_withdrawal_percent_fee', '1.5') ON DUPLICATE KEY UPDATE value = VALUES(value)")->execute();

// 10.1 Deposit Charges Added to Depositor Checkout
$depositAmount = 5000.00;
// Charge = 150 + (5000 * 0.02) = 150 + 100 = 250 NGN
$expectedDepCharge = 250.00;
$depCharge = wallet_deposit_charge($pdo, $depositAmount);
TestHarness::assertEqual($expectedDepCharge, $depCharge, "Fee Calculation: Deposit fee calculated correctly (NGN 250.00)");

$depInit = gateway_initialize_deposit($pdo, $testUserId, $depositAmount, 'korapay', $returnUrl);
TestHarness::assertTrue(!empty($depInit['success']), "Fee Accounting: Deposit checkout initialized with fee");
TestHarness::assertEqual($depositAmount, (float) ($depInit['amount'] ?? 0), "Fee Accounting: Stored wallet credit amount is full requested NGN 5000");
TestHarness::assertEqual($expectedDepCharge, (float) ($depInit['charge'] ?? 0), "Fee Accounting: Stored deposit charge is NGN 250");
TestHarness::assertEqual(5250.00, (float) ($depInit['total_payable'] ?? 0), "Fee Accounting: Gateway checkout payable is amount + charge (NGN 5250)");

// Verify wallet_transactions row records deposit amount
$depTxStmt = $pdo->prepare("SELECT * FROM wallet_transactions WHERE reference = ? LIMIT 1");
$depTxStmt->execute([$depInit['reference']]);
$depTxRow = $depTxStmt->fetch(PDO::FETCH_ASSOC);
TestHarness::assertEqual($depositAmount, (float) $depTxRow['amount'], "Fee Accounting: wallet_transactions records full credit amount (NGN 5000), NOT inflated with fee");

// 10.2 Withdrawal Charges Added to Wallet Debit
// Fund user wallet so they have enough balance for withdrawal + fee
$pdo->prepare("UPDATE wallets SET balance = 50000.00, hold_balance = 0.00 WHERE id = ?")->execute([$walletId]);

$wdAmount = 10000.00;
// Charge = 100 + (10000 * 0.015) = 100 + 150 = 250 NGN
$expectedWdCharge = 250.00;
$expectedTotalDebited = 10250.00;
$calculatedWdCharge = wallet_withdrawal_charge($wdAmount, $pdo);
TestHarness::assertEqual($expectedWdCharge, $calculatedWdCharge, "Fee Calculation: Withdrawal fee calculated correctly (NGN 250.00)");

$wdReq = wallet_request_withdrawal($pdo, $testUser, [
    'amount' => $wdAmount,
    'provider' => 'monnify',
    'bank_code' => '058',
    'bank_name' => 'GTBank',
    'account_number' => '0123456789',
    'account_name' => 'Gateway Test User',
    'note' => 'Fee invariant test withdrawal',
]);

TestHarness::assertTrue(!empty($wdReq['success']), "Fee Accounting: Withdrawal requested with charge");
TestHarness::assertEqual($wdAmount, (float) ($wdReq['amount'] ?? 0), "Fee Accounting: Requested amount is NGN 10000");
TestHarness::assertEqual($wdAmount, (float) ($wdReq['final_amount'] ?? 0), "Fee Accounting: Final payout amount is full NGN 10000 (NOT deducted)");
TestHarness::assertEqual($expectedWdCharge, (float) ($wdReq['charge'] ?? 0), "Fee Accounting: Withdrawal charge is NGN 250");
TestHarness::assertEqual($expectedTotalDebited, (float) ($wdReq['total_debited'] ?? 0), "Fee Accounting: Total debited from wallet is amount + charge (NGN 10250)");

// Verify wallet balances after hold
$walletRow = $pdo->query("SELECT balance, hold_balance FROM wallets WHERE id = {$walletId}")->fetch(PDO::FETCH_ASSOC);
TestHarness::assertEqual(39750.00, (float) $walletRow['balance'], "Fee Accounting: Wallet available balance debited exactly NGN 10250 (amount + fee)");
TestHarness::assertEqual(10250.00, (float) $walletRow['hold_balance'], "Fee Accounting: Wallet hold balance holds exactly NGN 10250 (amount + fee)");

// Verify wallet_transactions records total debited outflow to ensure ledger consistency
$wdTxStmt = $pdo->prepare("SELECT * FROM wallet_transactions WHERE reference = ? LIMIT 1");
$wdTxStmt->execute([$wdReq['reference']]);
$wdTxRow = $wdTxStmt->fetch(PDO::FETCH_ASSOC);
TestHarness::assertEqual($expectedTotalDebited, (float) $wdTxRow['amount'], "Fee Accounting: wallet_transactions records full debited outflow (NGN 10250) matching wallet hold");

// 10.3 Hold Release / Refund Integrity: Cancelling/rejecting withdrawal restores the full total_debited
$wdId = (int) $wdReq['withdrawal_id'];
$rejectRes = wallet_release_withdrawal_funds($pdo, $wdId, 'cancelled', 'Fee refund test cancellation', 1);
TestHarness::assertTrue(!empty($rejectRes['success']), "Fee Accounting: Withdrawal cancellation hold release succeeded");
TestHarness::assertEqual($expectedTotalDebited, (float) ($rejectRes['amount'] ?? 0), "Fee Accounting: Exactly NGN 10250 released back to wallet");

$walletAfterRefund = $pdo->query("SELECT balance, hold_balance FROM wallets WHERE id = {$walletId}")->fetch(PDO::FETCH_ASSOC);
TestHarness::assertEqual(50000.00, (float) $walletAfterRefund['balance'], "Fee Accounting: Wallet available balance completely restored to NGN 50000.00");
TestHarness::assertEqual(0.00, (float) $walletAfterRefund['hold_balance'], "Fee Accounting: Wallet hold balance returned to 0.00");

// --------------------------------------------------------------------------
// Summary & Cleanup
// --------------------------------------------------------------------------
// Clean test user data
$pdo->prepare("DELETE FROM wallet_transactions WHERE user_id = ?")->execute([$testUserId]);
$pdo->prepare("DELETE FROM wallet_withdrawals WHERE user_id = ?")->execute([$testUserId]);
$pdo->prepare("DELETE FROM wallets WHERE user_id = ?")->execute([$testUserId]);
$pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$testUserId]);

exit(TestHarness::summary());
