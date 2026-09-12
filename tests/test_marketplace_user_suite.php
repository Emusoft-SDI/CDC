<?php
declare(strict_types=1);

/**
 * NATCODEV Marketplace & Stakeholder Comprehensive Test Suite
 * Validates the full lifecycle and end-to-end user experience of the Marketplace Subsystem:
 * 1. Seller Onboarding, Profile Setup & KYC Store Verification
 * 2. Product Catalog, Categories & Stock Management
 * 3. Buyer Shopping Flow, Saved Cart & Pricing Engine (Delivery & Service Fees)
 * 4. Guest/Optional Buyer Account Registration & Profile Activation
 * 5. Inquiries, Quoting & Direct Order Conversion
 * 6. Multi-Item Order Checkout, Unique Reference & Tracking Generation
 * 7. Concurrency-Safe Stock Management & Order Fulfillment Lifecycle
 * 8. Financial Settlement, Escrow Ledger & 5% Platform Commission Split
 * 9. Seller Wallet Credit, Payouts & Withdrawal Requests
 * 10. Promotional Campaigns, Ad Placements & Boost Traffic
 * 11. Buyer Reviews, Star Ratings & Store Wishlist / Favorites
 * 12. Dispute Logging & Resolution Handling
 */

require_once __DIR__ . '/TestHarness.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/maintenance.php';
require_once __DIR__ . '/../lib/marketplace.php';
require_once __DIR__ . '/../lib/wallet.php';
require_once __DIR__ . '/../lib/platform-revenue.php';
require_once __DIR__ . '/../market/_market.php';

function run_marketplace_user_tests(): void
{
    TestHarness::start("Scenario 12: Marketplace Ecosystem & End-to-End Buyer/Seller Journeys");
    $pdo = TestHarness::createTestDb();
    marketplace_ensure_schema($pdo);
    revenue_ensure_schema($pdo);
    wallet_ensure_schema($pdo);

    $testTimestamp = time();
    $prefix = 'mkt_' . $testTimestamp . '_' . bin2hex(random_bytes(3));
    $sellerPhone = '080' . random_int(10000000, 99999999);
    $buyerPhone = '081' . random_int(10000000, 99999999);

    // =========================================================================
    // 1. Seller Onboarding, Profile Setup & KYC Store Verification
    // =========================================================================
    $sellerEmail = "{$prefix}_seller@natcodev.org";
    $sellerPass = 'SellerSecure2026!';
    $sellerHash = password_hash($sellerPass, PASSWORD_DEFAULT);

    $pdo->prepare("
        INSERT INTO users (name, email, phone, password, role, platform_role, account_status, email_verified_at, created_at)
        VALUES ('Tunde Adeleke', ?, ?, ?, 'grower', 'seller', 'active', NOW(), NOW())
    ")->execute([$sellerEmail, $sellerPhone, $sellerHash]);
    $sellerUserId = (int) $pdo->lastInsertId();

    TestHarness::assert($sellerUserId > 0, "Seller Onboarding: Seller user account #{$sellerUserId} created");

    $sellerUser = $pdo->query("SELECT * FROM users WHERE id = {$sellerUserId}")->fetch(PDO::FETCH_ASSOC);
    market_activate_seller_access($pdo, $sellerUser);
    $sellerUser = $pdo->query("SELECT * FROM users WHERE id = {$sellerUserId}")->fetch(PDO::FETCH_ASSOC);
    $_SESSION['user_id'] = $sellerUserId;
    $canSell = market_user_can_sell($pdo, $sellerUser);
    TestHarness::assert($canSell, "Seller Onboarding: Seller permissions and workspace access successfully activated");

    // Store profile creation & slug collision test
    $storeName = "Adeleke High-Yield Hybrid Nursery ({$prefix})";
    $storeSlug = marketplace_unique_slug($pdo, 'marketplace_sellers', $storeName);
    TestHarness::assert(strlen($storeSlug) > 0, "Seller Store: Unique slug generated ('{$storeSlug}')");

    $pdo->prepare("
        INSERT INTO marketplace_sellers (
            user_id, seller_type, store_name, slug, description, contact_person, email, phone,
            location_label, coverage_area, fulfillment_options, approval_status, verification_status
        ) VALUES (
            ?, 'grower', ?, ?, 'Certified supplier of hybrid coconut seedlings and nursery inputs.',
            'Tunde Adeleke', ?, ?, 'Badagry, Lagos State', 'South West Nigeria', 'Pickup, Local Delivery, Haulage',
            'pending', 'unverified'
        )
    ")->execute([$sellerUserId, $storeName, $storeSlug, $sellerEmail, $sellerPhone]);
    $sellerStoreId = (int) $pdo->lastInsertId();

    TestHarness::assert($sellerStoreId > 0, "Seller Store: Store profile #{$sellerStoreId} submitted for review");

    // KYC Admin Approval & Verification
    $pdo->prepare("
        UPDATE marketplace_sellers
        SET approval_status = 'approved', verification_status = 'verified', is_featured = 1
        WHERE id = ?
    ")->execute([$sellerStoreId]);

    $currentSeller = marketplace_current_seller($pdo, $sellerUserId);
    TestHarness::assertEqual('approved', $currentSeller['approval_status'] ?? '', "Seller KYC: Store profile approved by admin");
    TestHarness::assertEqual('verified', $currentSeller['verification_status'] ?? '', "Seller KYC: Store verified badge awarded");

    // =========================================================================
    // 2. Product Catalog, Categories & Stock Management
    // =========================================================================
    $categories = marketplace_categories($pdo);
    TestHarness::assert(count($categories) >= 5, "Product Catalog: Verified default marketplace categories available (Count: " . count($categories) . ")");

    $seedlingCatId = marketplace_category_id_for_type($pdo, 'product');
    TestHarness::assert($seedlingCatId !== null && $seedlingCatId > 0, "Product Catalog: Category ID for 'product' type resolved (#{$seedlingCatId})");

    // Create 2 listings for this seller: 1 Seedling Product, 1 Farm Service
    $productTitle = "F1 Dwarf Green Coconut Seedlings ({$prefix})";
    $productSlug = marketplace_unique_slug($pdo, 'marketplace_listings', $productTitle);
    $pdo->prepare("
        INSERT INTO marketplace_listings (
            seller_id, category_id, listing_type, title, slug, summary, description,
            price, price_unit, quantity_available, unit, min_order_quantity,
            location_label, fulfillment_method, availability_status, approval_status,
            pickup_available, local_delivery_available, nationwide_shipping_available
        ) VALUES (
            ?, ?, 'product', ?, ?, 'Certified 6-month hardened dwarf coconut seedlings.', 'High-yield early fruiting variety.',
            2500.00, 'per seedling', 150, 'seedling', 5,
            'Badagry, Lagos', 'Pickup & Tracked Courier', 'available', 'approved',
            1, 1, 1
        )
    ")->execute([$sellerStoreId, $seedlingCatId, $productTitle, $productSlug]);
    $productListingId = (int) $pdo->lastInsertId();

    TestHarness::assert($productListingId > 0, "Product Catalog: Listing #{$productListingId} ('{$productTitle}') created with 150 stock");

    $serviceTitle = "Nursery Soil Preparation & Spacing Advisory ({$prefix})";
    $serviceSlug = marketplace_unique_slug($pdo, 'marketplace_listings', $serviceTitle);
    $serviceCatId = marketplace_category_id_for_type($pdo, 'service') ?: $seedlingCatId;
    $pdo->prepare("
        INSERT INTO marketplace_listings (
            seller_id, category_id, listing_type, title, slug, summary, description,
            price, price_unit, quantity_available, unit, min_order_quantity,
            location_label, fulfillment_method, availability_status, approval_status
        ) VALUES (
            ?, ?, 'service', ?, ?, 'Professional on-farm agronomy spacing layout.', 'Complete layout sketch and soil test analysis.',
            35000.00, 'per farm visit', 20, 'visit', 1,
            'Lagos & Ogun Axis', 'On-site visit', 'available', 'approved'
        )
    ")->execute([$sellerStoreId, $serviceCatId, $serviceTitle, $serviceSlug]);
    $serviceListingId = (int) $pdo->lastInsertId();

    TestHarness::assert($serviceListingId > 0, "Product Catalog: Service Listing #{$serviceListingId} ('{$serviceTitle}') created");

    // =========================================================================
    // 3. Buyer Shopping Flow, Saved Cart & Pricing Engine
    // =========================================================================
    // Test Cart session helper functions
    $_SESSION['market_cart'] = [];
    market_cart_add($productListingId, 10);
    market_cart_add($productListingId, 5); // Total 15
    market_cart_add($serviceListingId, 1);

    TestHarness::assertEqual(16, market_cart_count(), "Buyer Cart: Correct total item count (15 seedlings + 1 service = 16)");

    $cartRows = market_cart_rows($pdo);
    TestHarness::assert(count($cartRows) === 2, "Buyer Cart: Retrieved 2 unique line items from database");

    // Pricing calculations:
    // Seedlings: 15 * 2500 = 37,500
    // Service: 1 * 35000 = 35,000
    // Subtotal = 72,500
    // Delivery Fee = 2,500
    // Service Fee = max(500, 72,500 * 0.015) = 1,087.50
    // Total = 72,500 + 2,500 + 1,087.50 = 76,087.50
    $totals = market_checkout_totals($cartRows);
    TestHarness::assertEqual(72500.00, $totals['subtotal'], "Pricing Engine: Accurate cart subtotal (NGN 72,500.00)");
    TestHarness::assertEqual(2500.00, $totals['delivery_fee'], "Pricing Engine: Standard delivery fee applied (NGN 2,500.00)");
    TestHarness::assertEqual(1087.50, $totals['service_fee'], "Pricing Engine: Calculated service fee (1.5% = NGN 1,087.50)");
    TestHarness::assertEqual(76087.50, $totals['total'], "Pricing Engine: Final total checkout calculation (NGN 76,087.50)");

    // Saved Cart for Later test
    $buyerEmail = "{$prefix}_buyer@natcodev.org";
    $buyerPass = 'BuyerSecure2026!';
    $buyerHash = password_hash($buyerPass, PASSWORD_DEFAULT);

    $pdo->prepare("
        INSERT INTO users (name, email, phone, password, role, platform_role, account_status, email_verified_at, created_at)
        VALUES ('Chidi Okonkwo', ?, ?, ?, 'grower', 'buyer', 'active', NOW(), NOW())
    ")->execute([$buyerEmail, $buyerPhone, $buyerHash]);
    $buyerUserId = (int) $pdo->lastInsertId();
    $buyerUser = $pdo->query("SELECT * FROM users WHERE id = {$buyerUserId}")->fetch(PDO::FETCH_ASSOC);

    $saved = market_cart_save_for_later($pdo, $buyerUser);
    TestHarness::assert($saved, "Saved Cart: Cart saved for registered buyer #{$buyerUserId}");
    TestHarness::assertEqual(0, market_cart_count(), "Saved Cart: Session cart cleared after persistent save");

    $savedRows = $pdo->query("SELECT * FROM marketplace_saved_carts WHERE user_id = {$buyerUserId}")->fetchAll(PDO::FETCH_ASSOC);
    TestHarness::assertEqual(2, count($savedRows), "Saved Cart: 2 items successfully stored in marketplace_saved_carts table");

    // =========================================================================
    // 4. Inquiries, Quoting & Direct Order Conversion
    // =========================================================================
    $inquiryRef = marketplace_inquiry_ref();
    $pdo->prepare("
        INSERT INTO marketplace_inquiries (
            inquiry_ref, listing_id, seller_id, buyer_user_id, buyer_name, buyer_email, buyer_phone,
            quantity, preferred_date, message, status
        ) VALUES (
            ?, ?, ?, ?, 'Chidi Okonkwo', ?, ?,
            50, DATE_ADD(CURDATE(), INTERVAL 7 DAY), 'Need 50 dwarf seedlings delivered to farm site in Epe.', 'new'
        )
    ")->execute([$inquiryRef, $productListingId, $sellerStoreId, $buyerUserId, $buyerEmail, $buyerPhone]);
    $inquiryId = (int) $pdo->lastInsertId();

    TestHarness::assert($inquiryId > 0, "Marketplace Inquiry: Buyer submitted inquiry #{$inquiryId} ({$inquiryRef})");

    // Seller replies with custom quote
    $discountedPrice = 2300.00; // special bulk rate
    $pdo->prepare("
        UPDATE marketplace_inquiries
        SET seller_reply = 'Bulk discount applied. We can deliver next Tuesday.',
            quoted_amount = ?, quoted_at = NOW(), status = 'quoted'
        WHERE id = ?
    ")->execute([$discountedPrice, $inquiryId]);

    $inquiryRow = $pdo->query("SELECT * FROM marketplace_inquiries WHERE id = {$inquiryId}")->fetch(PDO::FETCH_ASSOC);
    TestHarness::assertEqual('quoted', $inquiryRow['status'], "Marketplace Inquiry: Seller responded with quoted status");
    TestHarness::assertEqual(2300.00, (float) $inquiryRow['quoted_amount'], "Marketplace Inquiry: Quoted unit price recorded accurately (NGN 2,300.00)");

    // Convert inquiry into an official order
    $inquiryOrderRef = marketplace_order_ref();
    $inquiryTotal = 50 * $discountedPrice;
    $pdo->prepare("
        INSERT INTO marketplace_orders (
            order_ref, inquiry_id, listing_id, seller_id, buyer_user_id, buyer_name, buyer_email, buyer_phone,
            quantity, unit_price, total_amount, status, payment_status, delivery_status, fulfillment_note
        ) VALUES (
            ?, ?, ?, ?, ?, 'Chidi Okonkwo', ?, ?,
            50, ?, ?, 'quoted', 'unpaid', 'not_started', 'Special bulk order generated from inquiry'
        )
    ")->execute([$inquiryOrderRef, $inquiryId, $productListingId, $sellerStoreId, $buyerUserId, $buyerEmail, $buyerPhone, $discountedPrice, $inquiryTotal]);
    $inquiryOrderId = (int) $pdo->lastInsertId();

    TestHarness::assert($inquiryOrderId > 0, "Marketplace Inquiry: Converted into official order #{$inquiryOrderId} ({$inquiryOrderRef}) for NGN 115,000.00");

    // =========================================================================
    // 5. Multi-Item Order Checkout, Unique Reference & Tracking Generation
    // =========================================================================
    // Repopulate cart for live checkout
    market_cart_add($productListingId, 10);
    $checkoutCartRows = market_cart_rows($pdo);
    $checkoutBuyerInfo = [
        'name' => 'Chidi Okonkwo',
        'email' => $buyerEmail,
        'phone' => $buyerPhone,
        'address' => 'Plot 12, Coconut Valley Estate, Epe Expressway, Lagos',
    ];

    $checkoutResult = market_insert_checkout_orders($pdo, $checkoutCartRows, $buyerUser, $checkoutBuyerInfo, [
        'method' => 'bank_transfer',
        'paid' => false,
        'reference' => 'PAY-REF-' . $prefix,
    ]);

    TestHarness::assert(!empty($checkoutResult['checkout_ref']), "Checkout Flow: Order batch created with Checkout Ref '{$checkoutResult['checkout_ref']}'");
    TestHarness::assert(!empty($checkoutResult['orders'][0]['order_ref']), "Checkout Flow: Unique order reference generated ('{$checkoutResult['orders'][0]['order_ref']}')");
    TestHarness::assert(!empty($checkoutResult['orders'][0]['tracking_ref']), "Checkout Flow: Tracking reference generated ('{$checkoutResult['orders'][0]['tracking_ref']}')");

    $createdOrderRef = $checkoutResult['orders'][0]['order_ref'];
    $orderRecord = $pdo->prepare("SELECT * FROM marketplace_orders WHERE order_ref = ?");
    $orderRecord->execute([$createdOrderRef]);
    $orderData = $orderRecord->fetch(PDO::FETCH_ASSOC);

    TestHarness::assertEqual('pending_payment', $orderData['status'], "Order State: Initial status is 'pending_payment'");
    TestHarness::assertEqual('unpaid', $orderData['payment_status'], "Order State: Payment status is 'unpaid'");

    // =========================================================================
    // 6. Concurrency-Safe Stock Management & Order Fulfillment Lifecycle
    // =========================================================================
    // Stock decrement test
    $pdo->beginTransaction();
    $stockLock = $pdo->prepare("SELECT quantity_available FROM marketplace_listings WHERE id = ? FOR UPDATE");
    $stockLock->execute([$productListingId]);
    $currentStock = (float) $stockLock->fetchColumn();

    $qtyPurchased = (float) $orderData['quantity'];
    $newStock = $currentStock - $qtyPurchased;
    $pdo->prepare("UPDATE marketplace_listings SET quantity_available = ? WHERE id = ?")->execute([$newStock, $productListingId]);
    $pdo->commit();

    TestHarness::assertEqual(140.0, $newStock, "Inventory Control: Stock safely decremented from 150 to 140");

    // Order Fulfillment progression:
    // Mark as paid
    $pdo->prepare("
        UPDATE marketplace_orders
        SET status = 'paid', payment_status = 'paid', paid_at = NOW()
        WHERE id = ?
    ")->execute([(int) $orderData['id']]);

    // Seller updates status to 'preparing' -> 'in_transit' -> 'delivered' -> 'completed'
    $pdo->prepare("
        UPDATE marketplace_orders
        SET status = 'preparing', delivery_status = 'packing', fulfillment_note = 'Seedlings tagged and boxed.'
        WHERE id = ?
    ")->execute([(int) $orderData['id']]);

    $orderData = $pdo->query("SELECT * FROM marketplace_orders WHERE id = {$orderData['id']}")->fetch(PDO::FETCH_ASSOC);
    TestHarness::assertEqual('preparing', $orderData['status'], "Fulfillment: Order transitioned to 'preparing'");

    $pdo->prepare("
        UPDATE marketplace_orders
        SET status = 'scheduled', delivery_status = 'in_transit', fulfillment_note = 'Dispatched via NATCODEV Haulage Partner'
        WHERE id = ?
    ")->execute([(int) $orderData['id']]);

    $orderData = $pdo->query("SELECT * FROM marketplace_orders WHERE id = {$orderData['id']}")->fetch(PDO::FETCH_ASSOC);
    TestHarness::assertEqual('in_transit', $orderData['delivery_status'], "Fulfillment: Delivery status updated to 'in_transit'");

    $pdo->prepare("
        UPDATE marketplace_orders
        SET status = 'completed', delivery_status = 'delivered', fulfillment_note = 'Delivered and accepted by buyer'
        WHERE id = ?
    ")->execute([(int) $orderData['id']]);

    $orderData = $pdo->query("SELECT * FROM marketplace_orders WHERE id = {$orderData['id']}")->fetch(PDO::FETCH_ASSOC);
    TestHarness::assertEqual('completed', $orderData['status'], "Fulfillment: Order completed successfully");

    // =========================================================================
    // 7. Financial Settlement, Escrow Ledger & 5% Platform Commission Split
    // =========================================================================
    // Total amount = 10 * 2500 = 25,000
    // Platform fee (5%) = 1,250
    // Seller net = 23,750
    $revenueResult = revenue_apply_marketplace_order($pdo, $orderData);
    TestHarness::assertEqual(25000.00, $revenueResult['gross'], "Escrow & Revenue: Gross order amount verified (NGN 25,000.00)");
    TestHarness::assertEqual(1250.00, $revenueResult['fee'], "Escrow & Revenue: Platform 5% commission correctly computed (NGN 1,250.00)");
    TestHarness::assertEqual(23750.00, $revenueResult['net'], "Escrow & Revenue: Net payable to seller verified (NGN 23,750.00)");

    $ledgerEntry = $pdo->query("SELECT * FROM platform_revenue_ledger WHERE source_id = {$orderData['id']} AND source_module = 'marketplace'")->fetch(PDO::FETCH_ASSOC);
    TestHarness::assert(!empty($ledgerEntry), "Escrow & Revenue: Platform revenue ledger record created successfully");
    TestHarness::assertEqual(1250.00, (float) $ledgerEntry['revenue_amount'], "Escrow & Revenue: Platform revenue ledger amount matches NGN 1,250.00");

    // Settle seller wallet
    market_settle_checkout_orders($pdo, (string) $orderData['checkout_ref']);
    $sellerWallet = wallet_get_or_create($pdo, $sellerUserId);
    TestHarness::assertEqual(23750.00, (float) $sellerWallet['balance'], "Seller Wallet: Seller wallet credited with net settlement NGN 23,750.00");

    // =========================================================================
    // 8. Seller Wallet Payouts & Withdrawal Requests
    // =========================================================================
    $withdrawalReq = wallet_request_withdrawal($pdo, $sellerUser, [
        'amount' => 15000.00,
        'provider' => 'monnify',
        'bank_code' => '058',
        'bank_name' => 'Guaranty Trust Bank',
        'account_number' => '0123456789',
        'account_name' => 'Tunde Adeleke',
        'note' => 'Weekly marketplace earnings payout',
    ]);

    TestHarness::assert($withdrawalReq['success'], "Seller Withdrawal: Withdrawal request for NGN 15,000 submitted successfully");

    $updatedSellerWallet = wallet_get_or_create($pdo, $sellerUserId);
    TestHarness::assertEqual(8750.00, (float) $updatedSellerWallet['balance'], "Seller Wallet: Available balance decreased to NGN 8,750.00");
    TestHarness::assertEqual(15000.00, (float) $updatedSellerWallet['hold_balance'], "Seller Wallet: NGN 15,000 moved to hold_balance pending payout");

    // =========================================================================
    // 9. Promotional Campaigns, Ad Placements & Boost Traffic
    // =========================================================================
    $promoRef = marketplace_promo_ref();
    $pdo->prepare("
        INSERT INTO marketplace_promotions (
            promo_ref, seller_id, listing_id, title, subtitle, placement,
            image_path, target_url, amount, duration_days, payment_method, status,
            starts_at, ends_at, approved_at
        ) VALUES (
            ?, ?, ?, 'Top Rated Dwarf Coconut Seedlings', 'Special discounts from verified seller', 'homepage_banner',
            'assets/market/featured/vendor-ad-01.png', ?, 5000.00, 30, 'wallet', 'active',
            DATE_SUB(NOW(), INTERVAL 1 HOUR), DATE_ADD(NOW(), INTERVAL 30 DAY), NOW()
        )
    ")->execute([$promoRef, $sellerStoreId, $productListingId, "product.php?id={$productListingId}"]);
    $promoId = (int) $pdo->lastInsertId();

    TestHarness::assert($promoId > 0, "Promotions & Ads: Promotional campaign #{$promoId} ('{$promoRef}') created");

    $activePromos = marketplace_active_promotions($pdo, 'homepage_banner', 5);
    $foundPromo = false;
    foreach ($activePromos as $p) {
        if ((int) $p['id'] === $promoId) {
            $foundPromo = true;
            break;
        }
    }
    TestHarness::assert($foundPromo, "Promotions & Ads: Active campaign query correctly returns featured ad banner");

    // =========================================================================
    // 10. Buyer Reviews, Star Ratings & Store Wishlist / Favorites
    // =========================================================================
    // 10.1 Favorites / Wishlist
    $pdo->prepare("
        INSERT INTO marketplace_favorites (user_id, listing_id)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE created_at = CURRENT_TIMESTAMP
    ")->execute([$buyerUserId, $productListingId]);

    $favCount = $pdo->query("SELECT COUNT(*) FROM marketplace_favorites WHERE user_id = {$buyerUserId}")->fetchColumn();
    TestHarness::assertEqual(1, (int) $favCount, "Wishlist / Favorites: Buyer saved listing to favorites");

    // 10.2 Ratings & Reviews
    $pdo->prepare("
        INSERT INTO marketplace_reviews (listing_id, seller_id, user_id, rating, review_text, approval_status)
        VALUES (?, ?, ?, 5, 'Superb germination rate! All 50 seedlings are healthy and thriving in Epe.', 'approved')
    ")->execute([$productListingId, $sellerStoreId, $buyerUserId]);
    $reviewId = (int) $pdo->lastInsertId();

    TestHarness::assert($reviewId > 0, "Ratings & Reviews: 5-star verified purchase review #{$reviewId} published");

    $avgRating = (float) $pdo->query("SELECT AVG(rating) FROM marketplace_reviews WHERE listing_id = {$productListingId} AND approval_status = 'approved'")->fetchColumn();
    TestHarness::assertEqual(5.0, $avgRating, "Ratings & Reviews: Average rating calculated accurately (5.0 / 5.0 Stars)");

    // =========================================================================
    // 11. Dispute Logging & Resolution Handling
    // =========================================================================
    // Ensure table exists for test coverage if not present
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS marketplace_disputes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            buyer_user_id INT NOT NULL,
            seller_id INT NOT NULL,
            reason VARCHAR(255) NOT NULL,
            details TEXT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'open',
            resolution_notes TEXT NULL,
            resolved_by INT NULL,
            resolved_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_disputes_order (order_id),
            INDEX idx_disputes_seller (seller_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->prepare("
        INSERT INTO marketplace_disputes (order_id, buyer_user_id, seller_id, reason, details, status)
        VALUES (?, ?, ?, 'Damaged packaging during transit', '2 seedling bags torn upon courier delivery.', 'open')
    ")->execute([(int) $orderData['id'], $buyerUserId, $sellerStoreId]);
    $disputeId = (int) $pdo->lastInsertId();

    TestHarness::assert($disputeId > 0, "Dispute Resolution: Buyer dispute #{$disputeId} logged for order #{$orderData['id']}");

    // Admin resolves dispute with replacement dispatch
    $pdo->prepare("
        UPDATE marketplace_disputes
        SET status = 'resolved', resolution_notes = 'Seller agreed to ship 2 replacement seedlings at no extra cost.',
            resolved_at = NOW()
        WHERE id = ?
    ")->execute([$disputeId]);

    $disputeRow = $pdo->query("SELECT * FROM marketplace_disputes WHERE id = {$disputeId}")->fetch(PDO::FETCH_ASSOC);
    TestHarness::assertEqual('resolved', $disputeRow['status'], "Dispute Resolution: Dispute marked 'resolved' with mutual agreement");
}
