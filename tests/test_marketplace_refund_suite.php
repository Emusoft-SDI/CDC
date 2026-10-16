<?php
declare(strict_types=1);

/**
 * NATCODEV Marketplace Refund Handler Test Suite (Wave 4)
 * Validates the shared refund handler:
 * 1. Wallet-funded checkout: buyer is credited back in full
 * 2. Seller settlement is reversed (seller wallet debited the net)
 * 3. Platform commission is reversed in the revenue ledger
 * 4. Orders move to refunded status
 * 5. Refund is idempotent — a second call moves no money
 * 6. External (Monnify) payment: refund is recorded as a
 *    provider-refund obligation, no wallet credit issued
 */

require_once __DIR__ . '/TestHarness.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/maintenance.php';
require_once __DIR__ . '/../lib/marketplace.php';
require_once __DIR__ . '/../lib/wallet.php';
require_once __DIR__ . '/../lib/platform-revenue.php';
require_once __DIR__ . '/../lib/marketplace-refunds.php';
require_once __DIR__ . '/../market/_market.php';
require_once __DIR__ . '/../market/lib/cart-checkout.php';

function run_marketplace_refund_tests(): void
{
    TestHarness::start('Scenario 12c: Marketplace Shared Refund Handler');
    $pdo = TestHarness::createTestDb();
    marketplace_ensure_schema($pdo);
    revenue_ensure_schema($pdo);
    wallet_ensure_schema($pdo);
    market_refund_ensure_schema($pdo);

    $prefix = 'refund_' . time() . '_' . bin2hex(random_bytes(3));

    // Seller fixture
    $sellerEmail = "{$prefix}_seller@natcodev.org";
    $sellerPhone = '080' . random_int(10000000, 99999999);
    $pdo->prepare("
        INSERT INTO users (name, email, phone, password, role, platform_role, account_status, email_verified_at, created_at)
        VALUES ('Refund Seller', ?, ?, ?, 'grower', 'seller', 'active', NOW(), NOW())
    ")->execute([$sellerEmail, $sellerPhone, password_hash('SellerSecure2026!', PASSWORD_DEFAULT)]);
    $sellerUserId = (int) $pdo->lastInsertId();
    $sellerUser = $pdo->query("SELECT * FROM users WHERE id = {$sellerUserId}")->fetch(PDO::FETCH_ASSOC);
    market_activate_seller_access($pdo, $sellerUser);

    $storeSlug = marketplace_unique_slug($pdo, 'marketplace_sellers', "Refund Store ({$prefix})");
    $pdo->prepare("
        INSERT INTO marketplace_sellers (user_id, seller_type, store_name, slug, description, contact_person, email, phone, approval_status, verification_status)
        VALUES (?, 'grower', ?, ?, 'Refund test store.', 'Refund Seller', ?, '08012345678', 'approved', 'verified')
    ")->execute([$sellerUserId, "Refund Store ({$prefix})", $storeSlug, $sellerEmail]);
    $sellerStoreId = (int) $pdo->lastInsertId();

    // Listing fixture: 2 units at NGN 1,000
    $title = "Refund Test Coconuts ({$prefix})";
    $slug = marketplace_unique_slug($pdo, 'marketplace_listings', $title);
    $catId = marketplace_category_id_for_type($pdo, 'product');
    $pdo->prepare("
        INSERT INTO marketplace_listings (seller_id, category_id, listing_type, title, slug, summary, description, price, price_unit, quantity_available, unit, min_order_quantity, availability_status, approval_status)
        VALUES (?, ?, 'product', ?, ?, 'Refund test listing.', 'Refund test listing.', 1000.00, 'per unit', 100, 'unit', 1, 'available', 'approved')
    ")->execute([$sellerStoreId, $catId, $title, $slug]);
    $listingId = (int) $pdo->lastInsertId();

    // Buyer fixture with a funded wallet
    $buyerEmail = "{$prefix}_buyer@natcodev.org";
    $buyerPhone = '081' . random_int(10000000, 99999999);
    $pdo->prepare("
        INSERT INTO users (name, email, phone, password, role, platform_role, account_status, email_verified_at, created_at)
        VALUES ('Refund Buyer', ?, ?, ?, 'grower', 'buyer', 'active', NOW(), NOW())
    ")->execute([$buyerEmail, $buyerPhone, password_hash('BuyerSecure2026!', PASSWORD_DEFAULT)]);
    $buyerUserId = (int) $pdo->lastInsertId();
    wallet_admin_credit($pdo, $buyerUserId, 10000.00, 0, 'Refund test funding');

    // Wallet checkout: 2 units = 2,000 subtotal + 2,500 delivery + 500 service = 5,000 total
    $_SESSION['market_cart'] = [];
    market_cart_add($listingId, 2);
    $cartRows = market_cart_rows($pdo);
    $totals = market_checkout_totals($cartRows);
    TestHarness::assertEqual(5000.00, $totals['total'], 'Refund Setup: Checkout total NGN 5,000.00');

    $buyerUser = $pdo->query("SELECT * FROM users WHERE id = {$buyerUserId}")->fetch(PDO::FETCH_ASSOC);
    $checkoutRef = market_checkout_ref();
    $pdo->beginTransaction();
    $wallet = wallet_get_or_create($pdo, $buyerUserId);
    $lock = $pdo->prepare('SELECT * FROM wallets WHERE id = ? FOR UPDATE');
    $lock->execute([(int) $wallet['id']]);
    $wallet = $lock->fetch();
    $before = (float) $wallet['balance'];
    $after = $before - $totals['total'];
    $pdo->prepare('UPDATE wallets SET balance = ? WHERE id = ?')->execute([$after, (int) $wallet['id']]);
    $pdo->prepare("
        INSERT INTO wallet_transactions (wallet_id, user_id, amount, type, direction, description, reference, provider, status, balance_before, balance_after, completed_at)
        VALUES (?, ?, ?, 'debit', 'outflow', ?, ?, 'marketplace', 'completed', ?, ?, NOW())
    ")->execute([(int) $wallet['id'], $buyerUserId, $totals['total'], 'Marketplace checkout ' . $checkoutRef, $checkoutRef, $before, $after]);
    market_insert_checkout_orders($pdo, $cartRows, $buyerUser, [
        'name' => $buyerUser['name'], 'email' => $buyerUser['email'], 'phone' => '08112345678',
        'address' => '1 Test Farm Road', 'state' => 'Lagos', 'lga' => 'Epe',
    ], [
        'checkout_ref' => $checkoutRef,
        'method' => 'wallet',
        'reference' => $checkoutRef,
        'paid' => true,
    ]);
    $pdo->commit();

    // Settle so the seller holds the net and the commission is earned
    market_settle_checkout_orders($pdo, $checkoutRef);
    $sellerBalanceAfterSettlement = (float) wallet_get_or_create($pdo, $sellerUserId)['balance'];
    TestHarness::assertEqual(1900.00, $sellerBalanceAfterSettlement, 'Refund Setup: Seller settled NGN 1,900.00 net (2,000 - 5%)');
    $buyerBalanceAfterPayment = (float) wallet_get_or_create($pdo, $buyerUserId)['balance'];
    TestHarness::assertEqual(5000.00, $buyerBalanceAfterPayment, 'Refund Setup: Buyer balance NGN 5,000.00 after paying NGN 5,000.00');

    // =========================================================================
    // Execute the refund through the shared handler
    // =========================================================================
    $result = market_refund_checkout_once($pdo, $checkoutRef, [
        'reason' => 'Buyer cancelled the order',
        'admin_id' => 0,
    ]);
    TestHarness::assert(!empty($result['success']), 'Refund: Shared handler completed the refund');
    TestHarness::assert(!empty($result['refund_ref']), 'Refund: Refund reference issued (' . ($result['refund_ref'] ?? '') . ')');
    TestHarness::assertEqual('completed', (string) ($result['status'] ?? ''), 'Refund: Wallet-funded refund completes immediately');
    TestHarness::assertEqual(5000.00, (float) ($result['refund_amount'] ?? 0), 'Refund: Full checkout total NGN 5,000.00 refunded');
    TestHarness::assertEqual(1900.00, (float) ($result['settlement_reversal_amount'] ?? 0), 'Refund: Seller settlement reversal of NGN 1,900.00');
    TestHarness::assertEqual(100.00, (float) ($result['commission_reversal_amount'] ?? 0), 'Refund: Commission reversal of NGN 100.00');
    TestHarness::assert(!empty($result['wallet_credited']), 'Refund: Buyer wallet was credited');

    // 1. Buyer made whole: 5,000 + 5,000 refund credit = 10,000
    $buyerWalletAfterRefund = wallet_get_or_create($pdo, $buyerUserId);
    TestHarness::assertEqual(10000.00, (float) $buyerWalletAfterRefund['balance'], 'Refund: Buyer wallet credited back to NGN 10,000.00');
    $buyerRefundTx = $pdo->prepare("SELECT * FROM wallet_transactions WHERE reference = ? AND type = 'credit'");
    $buyerRefundTx->execute(['REFUND-' . $checkoutRef]);
    TestHarness::assert(!empty($buyerRefundTx->fetch()), 'Refund: Buyer wallet ledger carries the refund credit entry');

    // 2. Seller settlement reversed: 1,900 - 1,900 = 0
    $sellerWalletAfterRefund = wallet_get_or_create($pdo, $sellerUserId);
    TestHarness::assertEqual(0.00, (float) $sellerWalletAfterRefund['balance'], 'Refund: Seller wallet debited back to NGN 0.00');
    $firstOrderRefStmt = $pdo->prepare("SELECT order_ref FROM marketplace_orders WHERE checkout_ref = ? ORDER BY id LIMIT 1");
    $firstOrderRefStmt->execute([$checkoutRef]);
    $firstOrderRef = (string) $firstOrderRefStmt->fetchColumn();
    $sellerReversalTx = $pdo->prepare("SELECT * FROM wallet_transactions WHERE reference = ? AND type = 'refund_reversal'");
    $sellerReversalTx->execute(['REFUND-REV-' . $firstOrderRef]);
    TestHarness::assert(!empty($sellerReversalTx->fetch()), 'Refund: Seller wallet ledger carries the reversal entry');

    // 3. Commission reversed in the revenue ledger
    $reversalRow = $pdo->prepare("SELECT * FROM platform_revenue_ledger WHERE source_module = 'marketplace' AND source_type = 'order_commission_refund' AND status = 'reversed' LIMIT 1");
    $reversalRow->execute();
    TestHarness::assert(!empty($reversalRow->fetch()), 'Refund: Commission reversal recorded in the revenue ledger');

    // 4. Orders refunded
    $refundedStmt = $pdo->prepare("SELECT COUNT(*) FROM marketplace_orders WHERE checkout_ref = ? AND payment_status = 'refunded' AND status = 'refunded' AND refunded_at IS NOT NULL");
    $refundedStmt->execute([$checkoutRef]);
    $refundedCount = (int) $refundedStmt->fetchColumn();
    $orderStmt = $pdo->prepare("SELECT COUNT(*) FROM marketplace_orders WHERE checkout_ref = ?");
    $orderStmt->execute([$checkoutRef]);
    $orderCount = (int) $orderStmt->fetchColumn();
    TestHarness::assertEqual($orderCount, $refundedCount, 'Refund: All ' . $orderCount . ' order(s) marked refunded with refunded_at set');

    // 5. Refund record persisted
    $refundRecord = $pdo->prepare("SELECT * FROM marketplace_refunds WHERE checkout_ref = ?");
    $refundRecord->execute([$checkoutRef]);
    $recorded = $refundRecord->fetch(PDO::FETCH_ASSOC);
    TestHarness::assert(!empty($recorded), 'Refund: marketplace_refunds row persisted');
    TestHarness::assertEqual(5000.00, (float) ($recorded['refund_amount'] ?? 0), 'Refund: Recorded refund amount NGN 5,000.00');
    TestHarness::assertEqual(1900.00, (float) ($recorded['settlement_reversal_amount'] ?? 0), 'Refund: Recorded settlement reversal NGN 1,900.00');
    TestHarness::assertEqual(100.00, (float) ($recorded['commission_reversal_amount'] ?? 0), 'Refund: Recorded commission reversal NGN 100.00');

    // =========================================================================
    // Idempotency: a second refund call moves no money
    // =========================================================================
    $second = market_refund_checkout_once($pdo, $checkoutRef, ['reason' => 'Duplicate attempt']);
    TestHarness::assert(!empty($second['success']) && !empty($second['duplicate']), 'Refund Idempotency: Second call returns the recorded refund');
    TestHarness::assertEqual(10000.00, (float) wallet_get_or_create($pdo, $buyerUserId)['balance'], 'Refund Idempotency: Buyer balance unchanged');
    TestHarness::assertEqual(0.00, (float) wallet_get_or_create($pdo, $sellerUserId)['balance'], 'Refund Idempotency: Seller balance unchanged');

    // =========================================================================
    // External payment (Monnify): refund is a provider obligation, no wallet credit
    // =========================================================================
    $extCheckoutRef = market_checkout_ref();
    $pdo->beginTransaction();
    $extCreated = market_insert_checkout_orders($pdo, $cartRows, $buyerUser, [
        'name' => $buyerUser['name'], 'email' => $buyerUser['email'], 'phone' => '08112345678',
        'address' => '1 Test Farm Road', 'state' => 'Lagos', 'lga' => 'Epe',
    ], [
        'checkout_ref' => $extCheckoutRef,
        'method' => 'monnify',
        'reference' => 'NAT-MKT-EXT-' . $prefix,
        'paid' => true,
    ]);
    $pdo->commit();
    market_settle_checkout_orders($pdo, $extCheckoutRef);

    $extResult = market_refund_checkout_once($pdo, $extCheckoutRef, [
        'reason' => 'Item out of stock',
        'admin_id' => 0,
    ]);
    TestHarness::assert(!empty($extResult['success']), 'External Refund: Handler completed for Monnify-funded checkout');
    TestHarness::assertEqual('provider_refund_pending', (string) ($extResult['status'] ?? ''), 'External Refund: Monnify payment recorded as provider-refund obligation');
    TestHarness::assert(empty($extResult['wallet_credited']), 'External Refund: No wallet credit issued for an external payment');
    $extBuyerBalance = (float) wallet_get_or_create($pdo, $buyerUserId)['balance'];
    TestHarness::assertEqual(10000.00, $extBuyerBalance, 'External Refund: Buyer wallet untouched (payment lives at the gateway)');
    $extSellerBalance = (float) wallet_get_or_create($pdo, $sellerUserId)['balance'];
    TestHarness::assertEqual(0.00, $extSellerBalance, 'External Refund: Seller settlement reversed again (balance back to NGN 0.00)');

    // Guard rails: unpaid checkouts and unknown references are refused
    $unpaidRef = market_checkout_ref();
    $pdo->beginTransaction();
    market_insert_checkout_orders($pdo, $cartRows, $buyerUser, [
        'name' => $buyerUser['name'], 'email' => $buyerUser['email'], 'phone' => '08112345678',
        'address' => '1 Test Farm Road', 'state' => 'Lagos', 'lga' => 'Epe',
    ], [
        'checkout_ref' => $unpaidRef,
        'method' => 'wallet',
        'reference' => '',
        'paid' => false,
    ]);
    $pdo->commit();
    $refused = market_refund_checkout_once($pdo, $unpaidRef, ['reason' => 'Should not refund unpaid']);
    TestHarness::assert(empty($refused['success']), 'Guard: Refund of an unpaid checkout is refused');
    $missing = market_refund_checkout_once($pdo, 'SETTLE-NO-SUCH-CHECKOUT', ['reason' => 'Unknown']);
    TestHarness::assert(empty($missing['success']), 'Guard: Refund of an unknown checkout reference is refused');
}
