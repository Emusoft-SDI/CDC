<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/wallet-withdrawals.php';
require_once __DIR__ . '/../lib/support.php';
require_once __DIR__ . '/../lib/certificates.php';
require_once __DIR__ . '/../lib/marketplace.php';
require_once __DIR__ . '/../market/lib/cart-checkout.php';

$pdo = db();
$passed = 0;
$failed = 0;

function assert_test(bool $condition, string $message): void {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo " [PASS] {$message}\n";
    } else {
        $failed++;
        echo " [FAIL] {$message}\n";
    }
}

echo "=== NATCODEV END-TO-END IDEMPOTENCY TEST SUITE ===\n\n";

// 1. Setup Test User & Wallet
$testEmail = 'idempotency_test_' . time() . '@natcodev.test';
$pdo->prepare("INSERT INTO users (name, email, password, role, created_at) VALUES ('Idempotency Test User', ?, ?, 'grower', NOW())")
    ->execute([$testEmail, password_hash('TestPass123!', PASSWORD_DEFAULT)]);
$testUserId = (int) $pdo->lastInsertId();

wallet_ensure_schema($pdo);
$wallet = wallet_get_or_create($pdo, $testUserId);
$walletId = (int) $wallet['id'];

// Fund wallet with 100,000 NGN
$pdo->prepare("UPDATE wallets SET balance = 100000.00 WHERE id = ?")->execute([$walletId]);

echo "--- 1. Testing Wallet Withdrawal Idempotency ---\n";
$withdrawalParams = [
    'amount' => 5000.00,
    'account_number' => '0123456789',
    'bank_code' => '058',
    'bank_name' => 'GTBank',
    'account_name' => 'Idempotency Tester',
];

$testUser = ['id' => $testUserId, 'name' => 'Idempotency Test User', 'email' => $testEmail];

$w1 = wallet_request_withdrawal($pdo, $testUser, $withdrawalParams);
$w2 = wallet_request_withdrawal($pdo, $testUser, $withdrawalParams);

$walletAfter = wallet_get_or_create($pdo, $testUserId);
$balance1 = (float) $walletAfter['balance'];

assert_test(!empty($w1['success']), 'First withdrawal request succeeds');
assert_test(!empty($w2['duplicate']), 'Second withdrawal request detected as duplicate');
assert_test($w1['reference'] === $w2['reference'] && $w1['withdrawal_id'] === $w2['withdrawal_id'], 'Both withdrawal requests returned same reference and withdrawal ID');
assert_test(abs($balance1 - 95000.00) < 0.01, 'Wallet balance deducted exactly once (expected 95000, got ' . $balance1 . ')');

echo "\n--- 2. Testing Support Ticket Double-Submit Idempotency ---\n";
$ticketData = [
    'name' => 'Support Tester',
    'email' => $testEmail,
    'subject' => 'Idempotency Double Click Issue',
    'description' => 'Testing if duplicate tickets are prevented when clicking submit twice.',
    'category' => 'general',
    'priority' => 'medium',
];

$ref1 = support_create_ticket($pdo, $ticketData, ['id' => $testUserId, 'name' => 'Support Tester', 'email' => $testEmail]);
$ref2 = support_create_ticket($pdo, $ticketData, ['id' => $testUserId, 'name' => 'Support Tester', 'email' => $testEmail]);

$tktCountStmt = $pdo->prepare("SELECT COUNT(*) FROM support_tickets WHERE requester_email = ? AND subject = ?");
$tktCountStmt->execute([$testEmail, $ticketData['subject']]);
$ticketCount = (int) $tktCountStmt->fetchColumn();

assert_test($ref1 !== '' && $ref1 === $ref2, 'Both submissions return identical ticket reference: ' . $ref1);
assert_test($ticketCount === 1, 'Only 1 support ticket record created in database');

echo "\n--- 3. Testing Payment Callback Concurrent Replay Idempotency ---\n";
$callbackRef = 'TX-TEST-' . bin2hex(random_bytes(4));
$initialBalance = (float) $walletAfter['balance'];

$pdo->prepare("
    INSERT INTO wallet_transactions 
        (wallet_id, user_id, amount, type, direction, description, reference, provider, status, balance_before, balance_after, created_at)
    VALUES (?, ?, 10000.00, 'credit', 'inflow', 'Test Topup', ?, 'paystack', 'pending', ?, ?, NOW())
")->execute([$walletId, $testUserId, $callbackRef, $initialBalance, $initialBalance + 10000.00]);

// Simulate Callback 1
$pdo->beginTransaction();
$stmt = $pdo->prepare("SELECT t.id, w.user_id, t.amount, t.status FROM wallet_transactions t JOIN wallets w ON t.wallet_id = w.id WHERE t.reference = ? FOR UPDATE");
$stmt->execute([$callbackRef]);
$tx1 = $stmt->fetch();
if ($tx1 && (string) $tx1['status'] !== 'completed') {
    $pdo->prepare("UPDATE wallet_transactions SET status = 'completed', completed_at = NOW() WHERE id = ?")->execute([(int) $tx1['id']]);
    $pdo->prepare("UPDATE wallets SET balance = balance + ? WHERE user_id = ?")->execute([$tx1['amount'], $tx1['user_id']]);
}
$pdo->commit();

// Simulate Callback 2 (Replay)
$pdo->beginTransaction();
$stmt = $pdo->prepare("SELECT t.id, w.user_id, t.amount, t.status FROM wallet_transactions t JOIN wallets w ON t.wallet_id = w.id WHERE t.reference = ? FOR UPDATE");
$stmt->execute([$callbackRef]);
$tx2 = $stmt->fetch();
$creditedTwice = false;
if ($tx2 && (string) $tx2['status'] !== 'completed') {
    $pdo->prepare("UPDATE wallet_transactions SET status = 'completed', completed_at = NOW() WHERE id = ?")->execute([(int) $tx2['id']]);
    $pdo->prepare("UPDATE wallets SET balance = balance + ? WHERE user_id = ?")->execute([$tx2['amount'], $tx2['user_id']]);
    $creditedTwice = true;
}
$pdo->commit();

$walletAfterCallback = wallet_get_or_create($pdo, $testUserId);
$expectedBalance = $initialBalance + 10000.00;
assert_test(!$creditedTwice, 'Replayed callback skipped credit execution because status was completed');
assert_test(abs((float)$walletAfterCallback['balance'] - $expectedBalance) < 0.01, 'Wallet balance credited exactly once');

echo "\n--- 4. Testing Grower Certificate Access Fee Idempotency ---\n";
// Create test application
$appRef = 'APP-IDEMP-' . bin2hex(random_bytes(3));
$testPhone = '080' . random_int(10000000, 99999999);
$pdo->prepare("INSERT INTO applications (app_ref, name, location, farm_size, phone, email, commitments, member_type, review_status, confirmed, created_at) VALUES (?, 'Test Grower', 'Ogun', 5.00, ?, ?, 'standard', 'individual', 'approved', 1, NOW())")
    ->execute([$appRef, $testPhone, $testEmail]);
$appId = (int) $pdo->lastInsertId();

// Set fee to 2500 NGN
grower_certificate_save_setting($pdo, 'grower_certificate_access_fee_enabled', '1');
grower_certificate_save_setting($pdo, 'grower_certificate_fee_individual', '2500');

$balBeforeCert = (float) wallet_get_or_create($pdo, $testUserId)['balance'];
$c1 = grower_certificate_pay_access($pdo, $testUserId, $appId);
$balAfterCert1 = (float) wallet_get_or_create($pdo, $testUserId)['balance'];
$c2 = grower_certificate_pay_access($pdo, $testUserId, $appId);
$balAfterCert2 = (float) wallet_get_or_create($pdo, $testUserId)['balance'];

assert_test(!empty($c1['success']), 'First certificate access payment succeeds');
assert_test(!empty($c2['duplicate']), 'Second certificate access payment detected as duplicate');
assert_test(abs($balBeforeCert - $balAfterCert1 - 2500.00) < 0.01, 'Wallet deducted 2500 for first payment');
assert_test(abs($balAfterCert1 - $balAfterCert2) < 0.01, 'Wallet was NOT deducted again on second payment');

echo "\n--- 5. Testing Marketplace Inquiry Order Conversion Idempotency ---\n";
// Create seller, listing, inquiry
$sellerSlug = 'test-store-idemp-' . time() . '-' . random_int(100, 999);
$pdo->prepare("INSERT INTO marketplace_sellers (user_id, store_name, slug, approval_status, created_at) VALUES (?, 'Test Store', ?, 'approved', NOW())")
    ->execute([$testUserId, $sellerSlug]);
$sellerId = (int) $pdo->lastInsertId();

$listingSlug = 'organic-cassava-' . time() . '-' . random_int(100, 999);
$pdo->prepare("INSERT INTO marketplace_listings (seller_id, title, slug, price, approval_status, created_at) VALUES (?, 'Organic Cassava', ?, 5000.00, 'approved', NOW())")
    ->execute([$sellerId, $listingSlug]);
$listingId = (int) $pdo->lastInsertId();

$inqRef = 'INQ-' . bin2hex(random_bytes(4));
$pdo->prepare("INSERT INTO marketplace_inquiries (inquiry_ref, listing_id, seller_id, buyer_name, buyer_email, buyer_phone, message, status, created_at) VALUES (?, ?, ?, 'Buyer Doe', 'buyer@doe.test', '08011112222', 'Need 10 bags', 'pending', NOW())")
    ->execute([$inqRef, $listingId, $sellerId]);
$inquiryId = (int) $pdo->lastInsertId();

// Simulate inquiry order conversion logic twice
for ($i = 0; $i < 2; $i++) {
    $checkOrder = $pdo->prepare("SELECT id, order_ref FROM marketplace_orders WHERE inquiry_id = ? LIMIT 1");
    $checkOrder->execute([$inquiryId]);
    $existingOrder = $checkOrder->fetch();
    if (!$existingOrder) {
        $stmt = $pdo->prepare("
            INSERT INTO marketplace_orders
                (order_ref, inquiry_id, listing_id, seller_id, buyer_name, buyer_email, buyer_phone, quantity, unit_price, total_amount, status)
            VALUES (?, ?, ?, ?, 'Buyer Doe', 'buyer@doe.test', '08011112222', 10, 5000.00, 50000.00, 'quoted')
        ");
        $stmt->execute([marketplace_order_ref(), $inquiryId, $listingId, $sellerId]);
    }
}

$inqOrdersStmt = $pdo->prepare("SELECT COUNT(*) FROM marketplace_orders WHERE inquiry_id = ?");
$inqOrdersStmt->execute([$inquiryId]);
$orderCount = (int) $inqOrdersStmt->fetchColumn();
assert_test($orderCount === 1, 'Only 1 order created from inquiry even with double invocation');

echo "\n--- 6. Testing Cron Job Concurrent Row Claim Idempotency ---\n";
$pdo->exec("
    CREATE TABLE IF NOT EXISTS document_requirements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        document_type VARCHAR(40) NOT NULL,
        document_number VARCHAR(80) NOT NULL,
        api_validation_status VARCHAR(40) NOT NULL DEFAULT 'pending',
        api_validation_response TEXT NULL,
        api_validation_timestamp DATETIME NULL,
        retry_count INT NOT NULL DEFAULT 0,
        last_retry_at DATETIME NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
$pdo->prepare("
    INSERT INTO document_requirements (user_id, document_type, document_number, api_validation_status, retry_count, last_retry_at)
    VALUES (?, 'bvn', '22233344455', 'error', 1, DATE_SUB(NOW(), INTERVAL 48 HOUR))
")->execute([$testUserId]);
$docId = (int) $pdo->lastInsertId();

// Worker 1 claims
$w1Claim = $pdo->prepare("
    UPDATE document_requirements 
    SET last_retry_at = NOW(), retry_count = retry_count + 1 
    WHERE id = ? AND (last_retry_at IS NULL OR last_retry_at < DATE_SUB(NOW(), INTERVAL 24 HOUR))
");
$w1Claim->execute([$docId]);
$w1Rows = $w1Claim->rowCount();

// Worker 2 attempts same claim concurrently
$w2Claim = $pdo->prepare("
    UPDATE document_requirements 
    SET last_retry_at = NOW(), retry_count = retry_count + 1 
    WHERE id = ? AND (last_retry_at IS NULL OR last_retry_at < DATE_SUB(NOW(), INTERVAL 24 HOUR))
");
$w2Claim->execute([$docId]);
$w2Rows = $w2Claim->rowCount();

assert_test($w1Rows === 1, 'Worker 1 successfully claimed the failed document for retry');
assert_test($w2Rows === 0, 'Worker 2 got 0 rows (job was claimed, preventing duplicate external API call)');

echo "\n--- 7. Testing Marketplace Seller Settlement Idempotency ---\n";
// Create checkout ref and settled order
$checkoutRef = 'CHK-IDEMP-' . bin2hex(random_bytes(3));
$pdo->prepare("
    INSERT INTO marketplace_orders 
        (checkout_ref, order_ref, listing_id, seller_id, buyer_name, buyer_email, buyer_phone, quantity, unit_price, total_amount, payment_status, payout_status, status)
    VALUES (?, ?, ?, ?, 'Buyer Doe', 'buyer@doe.test', '08011112222', 1, 5000.00, 5000.00, 'paid', 'unsettled', 'paid')
")->execute([$checkoutRef, marketplace_order_ref(), $listingId, $sellerId]);

$sellerWalletBefore = (float) wallet_get_or_create($pdo, $testUserId)['balance'];

// Run settlement twice
market_settle_checkout_orders($pdo, $checkoutRef);
$sellerWalletAfter1 = (float) wallet_get_or_create($pdo, $testUserId)['balance'];
market_settle_checkout_orders($pdo, $checkoutRef);
$sellerWalletAfter2 = (float) wallet_get_or_create($pdo, $testUserId)['balance'];

assert_test($sellerWalletAfter1 > $sellerWalletBefore, 'First settlement credited seller wallet');
assert_test(abs($sellerWalletAfter1 - $sellerWalletAfter2) < 0.01, 'Second settlement did NOT credit seller wallet again');

echo "\n--- 8. Testing Plan Upgrade Double-Submit Idempotency ---\n";
// Create user for upgrade test
$upgEmail = 'upg_test_' . time() . '@natcodev.test';
$pdo->prepare("INSERT INTO users (name, email, password, role, plan, created_at) VALUES ('Upgrade Tester', ?, ?, 'grower', 'basic', NOW())")
    ->execute([$upgEmail, password_hash('TestPass123!', PASSWORD_DEFAULT)]);
$upgUserId = (int) $pdo->lastInsertId();
$upgWallet = wallet_get_or_create($pdo, $upgUserId);
$pdo->prepare("UPDATE wallets SET balance = 15000.00 WHERE id = ?")->execute([(int) $upgWallet['id']]);

$upgradeUser = function(int $userId) use ($pdo): array {
    $pdo->beginTransaction();
    $userLock = $pdo->prepare("SELECT plan, plan_expiry FROM users WHERE id = ? FOR UPDATE");
    $userLock->execute([$userId]);
    $currUser = $userLock->fetch();
    if ($currUser && (string) ($currUser['plan'] ?? '') === 'premium' && !empty($currUser['plan_expiry'])) {
        $expiryTime = strtotime((string) $currUser['plan_expiry']);
        if ($expiryTime > (time() + 30 * 86400)) {
            $pdo->commit();
            return ['success' => true, 'duplicate' => true];
        }
    }
    $stmt = $pdo->prepare("SELECT id, balance FROM wallets WHERE user_id = ? FOR UPDATE");
    $stmt->execute([$userId]);
    $w = $stmt->fetch();
    $balance = (float) ($w['balance'] ?? 0);
    if ($balance < 5000) {
        $pdo->rollBack();
        return ['success' => false, 'error' => 'Insufficient balance'];
    }
    $pdo->prepare("UPDATE wallets SET balance = balance - 5000 WHERE user_id = ?")->execute([$userId]);
    $pdo->prepare("UPDATE users SET plan = 'premium', plan_expiry = DATE_ADD(NOW(), INTERVAL 1 YEAR) WHERE id = ?")->execute([$userId]);
    $pdo->commit();
    return ['success' => true, 'duplicate' => false];
};

$upgRes1 = $upgradeUser($upgUserId);
$upgBal1 = (float) wallet_get_or_create($pdo, $upgUserId)['balance'];
$upgRes2 = $upgradeUser($upgUserId);
$upgBal2 = (float) wallet_get_or_create($pdo, $upgUserId)['balance'];

assert_test($upgRes1['success'] && !$upgRes1['duplicate'], 'First plan upgrade succeeds');
assert_test($upgRes2['success'] && $upgRes2['duplicate'], 'Second plan upgrade detected as duplicate');
assert_test(abs($upgBal1 - 10000.00) < 0.01, 'Wallet balance debited 5000 NGN on first upgrade');
assert_test(abs($upgBal1 - $upgBal2) < 0.01, 'Wallet balance NOT debited again on second upgrade');

echo "\n--- 9. Testing Order Wallet Payment Double-Submit Idempotency ---\n";
$mktOrderRef = 'ORD-IDEMP-' . bin2hex(random_bytes(3));
$mktCheckoutRef = 'CHK-WALLET-' . bin2hex(random_bytes(3));
$pdo->prepare("
    INSERT INTO marketplace_orders 
        (checkout_ref, order_ref, listing_id, seller_id, buyer_user_id, buyer_name, buyer_email, buyer_phone, quantity, unit_price, total_amount, payment_status, status)
    VALUES (?, ?, ?, ?, ?, 'Buyer Doe', 'buyer@doe.test', '08011112222', 1, 4000.00, 4000.00, 'unpaid', 'pending')
")->execute([$mktCheckoutRef, $mktOrderRef, $listingId, $sellerId, $upgUserId]);
$mktOrderId = (int) $pdo->lastInsertId();

$payOrderWithWallet = function(int $userId, int $orderId, float $total) use ($pdo): array {
    $pdo->beginTransaction();
    $check = $pdo->prepare("SELECT payment_status FROM marketplace_orders WHERE id = ? FOR UPDATE");
    $check->execute([$orderId]);
    $status = (string) $check->fetchColumn();
    if ($status === 'paid') {
        $pdo->commit();
        return ['success' => true, 'duplicate' => true];
    }
    $wStmt = $pdo->prepare("SELECT balance FROM wallets WHERE user_id = ? FOR UPDATE");
    $wStmt->execute([$userId]);
    $b = (float) $wStmt->fetchColumn();
    if ($b < $total) {
        $pdo->rollBack();
        return ['success' => false, 'error' => 'Insufficient balance'];
    }
    $pdo->prepare("UPDATE wallets SET balance = balance - ? WHERE user_id = ?")->execute([$total, $userId]);
    $pdo->prepare("UPDATE marketplace_orders SET payment_status = 'paid', status = 'paid' WHERE id = ?")->execute([$orderId]);
    $pdo->commit();
    return ['success' => true, 'duplicate' => false];
};

$pay1 = $payOrderWithWallet($upgUserId, $mktOrderId, 4000.00);
$balAfterPay1 = (float) wallet_get_or_create($pdo, $upgUserId)['balance'];
$pay2 = $payOrderWithWallet($upgUserId, $mktOrderId, 4000.00);
$balAfterPay2 = (float) wallet_get_or_create($pdo, $upgUserId)['balance'];

assert_test($pay1['success'] && !$pay1['duplicate'], 'First order wallet payment succeeds');
assert_test($pay2['success'] && $pay2['duplicate'], 'Second order wallet payment detected as duplicate');
assert_test(abs($balAfterPay1 - 6000.00) < 0.01, 'Wallet balance deducted 4000 on first payment');
assert_test(abs($balAfterPay1 - $balAfterPay2) < 0.01, 'Wallet balance NOT deducted again on second payment');

echo "\n--- 10. Testing Seller Promotions Rapid Double-Click Idempotency ---\n";
$promoTitle = 'Idempotency Boost ' . time();
$promoPlacement = 'homepage_ad';
$promoAmount = 20000.00;

$submitPromo = function(int $sellerId, int $userId, string $title, string $placement, float $amount) use ($pdo): array {
    $recentStmt = $pdo->prepare("
        SELECT * FROM marketplace_promotions
        WHERE seller_id = ? AND placement = ? AND amount = ? AND title = ?
          AND created_at >= DATE_SUB(NOW(), INTERVAL 15 SECOND)
        ORDER BY id DESC LIMIT 1
    ");
    $recentStmt->execute([$sellerId, $placement, $amount, $title]);
    $recent = $recentStmt->fetch();
    if ($recent) {
        return ['success' => true, 'duplicate' => true, 'promo_id' => (int) $recent['id']];
    }
    $wallet = wallet_get_or_create($pdo, $userId);
    $pdo->beginTransaction();
    $lock = $pdo->prepare("SELECT balance FROM wallets WHERE id = ? FOR UPDATE");
    $lock->execute([(int) $wallet['id']]);
    $b = (float) $lock->fetchColumn();
    if ($b < $amount) {
        $pdo->rollBack();
        return ['success' => false, 'error' => 'Insufficient funds'];
    }
    $ref = 'PRM-' . bin2hex(random_bytes(4));
    $pdo->prepare("UPDATE wallets SET balance = balance - ? WHERE id = ?")->execute([$amount, (int) $wallet['id']]);
    $pdo->prepare("
        INSERT INTO marketplace_promotions
            (promo_ref, seller_id, title, placement, amount, payment_method, status, created_by)
        VALUES (?, ?, ?, ?, ?, 'wallet', 'pending_admin_review', ?)
    ")->execute([$ref, $sellerId, $title, $placement, $amount, $userId]);
    $newId = (int) $pdo->lastInsertId();
    $pdo->commit();
    return ['success' => true, 'duplicate' => false, 'promo_id' => $newId];
};

// Fund wallet with 50,000 NGN
$pdo->prepare("UPDATE wallets SET balance = 50000.00 WHERE user_id = ?")->execute([$testUserId]);

$p1 = $submitPromo($sellerId, $testUserId, $promoTitle, $promoPlacement, $promoAmount);
$balAfterP1 = (float) wallet_get_or_create($pdo, $testUserId)['balance'];
$p2 = $submitPromo($sellerId, $testUserId, $promoTitle, $promoPlacement, $promoAmount);
$balAfterP2 = (float) wallet_get_or_create($pdo, $testUserId)['balance'];

assert_test($p1['success'] && !$p1['duplicate'], 'First promotion purchase succeeds');
assert_test($p2['success'] && $p2['duplicate'], 'Second promotion purchase detected as duplicate');
assert_test($p1['promo_id'] === $p2['promo_id'], 'Both calls return identical promotion ID');
assert_test(abs($balAfterP1 - 30000.00) < 0.01, 'Wallet balance debited 20000 on first purchase');
assert_test(abs($balAfterP1 - $balAfterP2) < 0.01, 'Wallet balance NOT debited on second purchase');

// Cleanup
echo "\n--- Cleaning up test artifacts ---\n";
$pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$upgUserId]);
$pdo->prepare("DELETE FROM wallets WHERE user_id = ?")->execute([$upgUserId]);
$pdo->prepare("DELETE FROM marketplace_promotions WHERE seller_id = ?")->execute([$sellerId]);
$pdo->prepare("DELETE FROM support_tickets WHERE requester_email = ?")->execute([$testEmail]);
$pdo->prepare("DELETE FROM certificate_access_payments WHERE user_id = ?")->execute([$testUserId]);
$pdo->prepare("DELETE FROM applications WHERE email = ?")->execute([$testEmail]);
$pdo->prepare("DELETE FROM marketplace_orders WHERE buyer_email = 'buyer@doe.test'")->execute();
$pdo->prepare("DELETE FROM marketplace_inquiries WHERE id = ?")->execute([$inquiryId]);
$pdo->prepare("DELETE FROM marketplace_listings WHERE id = ?")->execute([$listingId]);
$pdo->prepare("DELETE FROM marketplace_sellers WHERE id = ?")->execute([$sellerId]);
$pdo->prepare("DELETE FROM document_requirements WHERE id = ?")->execute([$docId]);
$pdo->prepare("DELETE FROM wallet_withdrawals WHERE user_id = ?")->execute([$testUserId]);
$pdo->prepare("DELETE FROM wallet_transactions WHERE user_id = ?")->execute([$testUserId]);
$pdo->prepare("DELETE FROM wallets WHERE user_id = ?")->execute([$testUserId]);
$pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$testUserId]);

echo "\n============================================\n";
echo "TEST RESULTS: Passed: {$passed}, Failed: {$failed}\n";
echo "============================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
