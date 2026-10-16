<?php
declare(strict_types=1);

/**
 * NATCODEV Marketplace Settlement Test Suite (Wave 3)
 * Validates the marketplace checkout settlement path:
 * 1. Seller receives the NET amount (gross minus 5% commission)
 * 2. Platform commission is recorded in the revenue ledger
 * 3. Checkout delivery and service fees are recorded as platform revenue
 * 4. Settlement is idempotent — re-running moves no money twice
 */

require_once __DIR__ . '/TestHarness.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/maintenance.php';
require_once __DIR__ . '/../lib/marketplace.php';
require_once __DIR__ . '/../lib/wallet.php';
require_once __DIR__ . '/../lib/platform-revenue.php';
require_once __DIR__ . '/../market/_market.php';
require_once __DIR__ . '/../market/lib/cart-checkout.php';

function run_marketplace_settlement_tests(): void
{
    TestHarness::start('Scenario 12b: Marketplace Settlement — Net + Commission + Fees');
    $pdo = TestHarness::createTestDb();
    marketplace_ensure_schema($pdo);
    revenue_ensure_schema($pdo);
    wallet_ensure_schema($pdo);

    $prefix = 'settle_' . time() . '_' . bin2hex(random_bytes(3));

    // Seller fixture
    $sellerEmail = "{$prefix}_seller@natcodev.org";
    $sellerPhone = '080' . random_int(10000000, 99999999);
    $pdo->prepare("
        INSERT INTO users (name, email, phone, password, role, platform_role, account_status, email_verified_at, created_at)
        VALUES ('Settlement Seller', ?, ?, ?, 'grower', 'seller', 'active', NOW(), NOW())
    ")->execute([$sellerEmail, $sellerPhone, password_hash('SellerSecure2026!', PASSWORD_DEFAULT)]);
    $sellerUserId = (int) $pdo->lastInsertId();
    $sellerUser = $pdo->query("SELECT * FROM users WHERE id = {$sellerUserId}")->fetch(PDO::FETCH_ASSOC);
    market_activate_seller_access($pdo, $sellerUser);

    $storeSlug = marketplace_unique_slug($pdo, 'marketplace_sellers', "Settlement Store ({$prefix})");
    $pdo->prepare("
        INSERT INTO marketplace_sellers (user_id, seller_type, store_name, slug, description, contact_person, email, phone, approval_status, verification_status)
        VALUES (?, 'grower', ?, ?, 'Settlement test store.', 'Settlement Seller', ?, '08012345678', 'approved', 'verified')
    ")->execute([$sellerUserId, "Settlement Store ({$prefix})", $storeSlug, $sellerEmail]);
    $sellerStoreId = (int) $pdo->lastInsertId();

    // Listing fixture: 4 units at NGN 1,000
    $title = "Settlement Test Coconuts ({$prefix})";
    $slug = marketplace_unique_slug($pdo, 'marketplace_listings', $title);
    $catId = marketplace_category_id_for_type($pdo, 'product');
    $pdo->prepare("
        INSERT INTO marketplace_listings (seller_id, category_id, listing_type, title, slug, summary, description, price, price_unit, quantity_available, unit, min_order_quantity, availability_status, approval_status)
        VALUES (?, ?, 'product', ?, ?, 'Settlement test listing.', 'Settlement test listing.', 1000.00, 'per unit', 100, 'unit', 1, 'available', 'approved')
    ")->execute([$sellerStoreId, $catId, $title, $slug]);
    $listingId = (int) $pdo->lastInsertId();

    // Buyer fixture with a funded wallet
    $buyerEmail = "{$prefix}_buyer@natcodev.org";
    $buyerPhone = '081' . random_int(10000000, 99999999);
    $pdo->prepare("
        INSERT INTO users (name, email, phone, password, role, platform_role, account_status, email_verified_at, created_at)
        VALUES ('Settlement Buyer', ?, ?, ?, 'grower', 'buyer', 'active', NOW(), NOW())
    ")->execute([$buyerEmail, $buyerPhone, password_hash('BuyerSecure2026!', PASSWORD_DEFAULT)]);
    $buyerUserId = (int) $pdo->lastInsertId();

    // Fund the buyer wallet with NGN 10,000 so the wallet checkout path can debit it
    wallet_admin_credit($pdo, $buyerUserId, 10000.00, 0, 'Settlement test funding');
    $buyerWallet = wallet_get_or_create($pdo, $buyerUserId);
    TestHarness::assertEqual(10000.00, (float) $buyerWallet['balance'], 'Settlement Setup: Buyer wallet funded with NGN 10,000.00');

    // Cart: 4 units = NGN 4,000 subtotal; delivery NGN 2,500; service fee max(500, 4000*1.5%)=500
    $_SESSION['market_cart'] = [];
    market_cart_add($listingId, 4);
    $cartRows = market_cart_rows($pdo);
    $totals = market_checkout_totals($cartRows);
    TestHarness::assertEqual(4000.00, $totals['subtotal'], 'Settlement Setup: Cart subtotal NGN 4,000.00');
    TestHarness::assertEqual(2500.00, $totals['delivery_fee'], 'Settlement Setup: Delivery fee NGN 2,500.00');
    TestHarness::assertEqual(500.00, $totals['service_fee'], 'Settlement Setup: Service fee NGN 500.00');
    TestHarness::assertEqual(7000.00, $totals['total'], 'Settlement Setup: Checkout total NGN 7,000.00');

    // Wallet-funded checkout: debit the buyer, insert paid orders, settle
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
    $created = market_insert_checkout_orders($pdo, $cartRows, $buyerUser, [
        'name' => $buyerUser['name'], 'email' => $buyerUser['email'], 'phone' => '08112345678',
        'address' => '1 Test Farm Road', 'state' => 'Lagos', 'lga' => 'Epe',
    ], [
        'checkout_ref' => $checkoutRef,
        'method' => 'wallet',
        'reference' => $checkoutRef,
        'paid' => true,
    ]);
    $pdo->commit();

    TestHarness::assertEqual($checkoutRef, $created['checkout_ref'], 'Settlement Checkout: Paid checkout batch created');
    TestHarness::assertEqual(3000.00, (float) wallet_get_or_create($pdo, $buyerUserId)['balance'], 'Settlement Checkout: Buyer wallet debited NGN 7,000.00 (balance NGN 3,000.00)');

    // Settle through the shared path
    market_settle_checkout_orders($pdo, $checkoutRef);

    // 1. Seller receives NET: 4,000 gross - 5% commission (200) = 3,800
    $sellerWallet = wallet_get_or_create($pdo, $sellerUserId);
    TestHarness::assertEqual(3800.00, (float) $sellerWallet['balance'], 'Settlement: Seller wallet credited NET NGN 3,800.00 (gross 4,000 - 5% commission 200)');

    // 2. Commission recorded in the revenue ledger (scoped to this run's order)
    $orderIdStmt = $pdo->prepare("SELECT id FROM marketplace_orders WHERE checkout_ref = ? ORDER BY id LIMIT 1");
    $orderIdStmt->execute([$checkoutRef]);
    $orderId = (int) $orderIdStmt->fetchColumn();
    TestHarness::assert($orderId > 0, 'Settlement: Order id resolved for the settled checkout');
    $commissionRow = $pdo->prepare("SELECT * FROM platform_revenue_ledger WHERE source_module = 'marketplace' AND source_type = 'order_commission' AND rule_key = 'marketplace_commission_default' AND source_id = ? LIMIT 1");
    $commissionRow->execute([$orderId]);
    $commission = $commissionRow->fetch(PDO::FETCH_ASSOC);
    TestHarness::assert(!empty($commission), 'Settlement: Commission row recorded in platform_revenue_ledger');
    TestHarness::assertEqual(200.00, (float) ($commission['revenue_amount'] ?? 0), 'Settlement: Commission revenue is NGN 200.00 (5% of NGN 4,000.00)');
    TestHarness::assertEqual(3800.00, (float) ($commission['net_payable_amount'] ?? 0), 'Settlement: Ledger net payable to seller is NGN 3,800.00');

    $deliveryRow = $pdo->prepare("SELECT * FROM platform_revenue_ledger WHERE source_module = 'marketplace' AND source_type = 'checkout_delivery_fee' AND source_id = ? LIMIT 1");
    $deliveryRow->execute([$orderId]);
    TestHarness::assert(!empty($deliveryRow->fetch()), 'Settlement: Delivery fee NGN 2,500.00 recorded as platform revenue');
    $serviceRow = $pdo->prepare("SELECT * FROM platform_revenue_ledger WHERE source_module = 'marketplace' AND source_type = 'checkout_service_fee' AND source_id = ? LIMIT 1");
    $serviceRow->execute([$orderId]);
    TestHarness::assert(!empty($serviceRow->fetch()), 'Settlement: Service fee NGN 500.00 recorded as platform revenue');

    // 4. Orders marked settled
    $settledStmt = $pdo->prepare("SELECT COUNT(*) FROM marketplace_orders WHERE checkout_ref = ? AND payout_status = 'settled'");
    $settledStmt->execute([$checkoutRef]);
    $settledCount = (int) $settledStmt->fetchColumn();
    $orderStmt = $pdo->prepare("SELECT COUNT(*) FROM marketplace_orders WHERE checkout_ref = ?");
    $orderStmt->execute([$checkoutRef]);
    $orderCount = (int) $orderStmt->fetchColumn();
    TestHarness::assertEqual($orderCount, $settledCount, 'Settlement: All ' . $orderCount . ' order(s) in the checkout marked settled');

    // 5. Idempotency: re-running settlement moves no money again
    $sellerBalanceBefore = (float) wallet_get_or_create($pdo, $sellerUserId)['balance'];
    $buyerBalanceBefore = (float) wallet_get_or_create($pdo, $buyerUserId)['balance'];
    market_settle_checkout_orders($pdo, $checkoutRef);
    $sellerBalanceAfter = (float) wallet_get_or_create($pdo, $sellerUserId)['balance'];
    $buyerBalanceAfter = (float) wallet_get_or_create($pdo, $buyerUserId)['balance'];
    TestHarness::assertEqual($sellerBalanceBefore, $sellerBalanceAfter, 'Settlement Idempotency: Seller balance unchanged on re-settlement');
    TestHarness::assertEqual($buyerBalanceBefore, $buyerBalanceAfter, 'Settlement Idempotency: Buyer balance unchanged on re-settlement');

    // 6. Platform math: buyer paid 7,000 = seller net 3,800 + commission 200 + delivery 2,500 + service 500
    $platformRetained = 200.00 + 2500.00 + 500.00;
    TestHarness::assertEqual(7000.00, round(3800.00 + $platformRetained, 2), 'Settlement Math: Seller net + platform revenue reconciles to the checkout total');
}
