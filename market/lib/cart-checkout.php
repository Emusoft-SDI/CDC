<?php
declare(strict_types=1);

function market_cart_items(): array
{
    return $_SESSION['market_cart'] ?? [];
}

function market_cart_count(): int
{
    return array_sum(array_map('intval', market_cart_items()));
}

function market_cart_add(int $listingId, int $quantity): void
{
    $quantity = max(1, $quantity);
    $_SESSION['market_cart'] = $_SESSION['market_cart'] ?? [];
    $_SESSION['market_cart'][$listingId] = max(1, (int) ($_SESSION['market_cart'][$listingId] ?? 0) + $quantity);
}

function market_cart_remove(int $listingId): void
{
    if (isset($_SESSION['market_cart'][$listingId])) {
        unset($_SESSION['market_cart'][$listingId]);
    }
}

function market_cart_clear(): void
{
    unset($_SESSION['market_cart']);
}

function market_checkout_ensure_optional_buyer_schema(PDO $pdo): void
{
    app_ensure_user_verification_schema($pdo);
    foreach ([
        'platform_role' => "VARCHAR(60) NULL",
        'account_status' => "VARCHAR(40) NOT NULL DEFAULT 'active'",
        'phone' => "VARCHAR(30) NULL",
        'location' => "VARCHAR(255) NULL",
        'profile_picture' => "VARCHAR(255) NULL",
    ] as $column => $definition) {
        app_add_column_if_missing($pdo, 'users', $column, $definition);
    }
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS buyer_profiles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL UNIQUE,
            preferred_state VARCHAR(120) NULL,
            preferred_lga VARCHAR(120) NULL,
            delivery_address TEXT NULL,
            buyer_type VARCHAR(60) NOT NULL DEFAULT 'individual',
            interests TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_buyer_profiles_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    app_ensure_primary_auto_increment($pdo, 'buyer_profiles');
}

function market_checkout_activate_buyer_access(PDO $pdo, int $userId): void
{
    if ($userId <= 0) {
        return;
    }
    market_checkout_ensure_optional_buyer_schema($pdo);
    market_ensure_role_assignment_schema($pdo);
    $pdo->prepare("
        INSERT INTO user_role_assignments (user_id, role_key, scope_type, scope_value, status, notes)
        VALUES (?, 'buyer', 'global', '', 'active', 'Self-activated buyer workspace access')
        ON DUPLICATE KEY UPDATE status = 'active', revoked_at = NULL, notes = VALUES(notes)
    ")->execute([$userId]);
    $pdo->prepare("
        INSERT INTO buyer_profiles (user_id, buyer_type)
        VALUES (?, 'individual')
        ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP
    ")->execute([$userId]);
}

function market_checkout_create_optional_buyer_account(PDO $pdo, array $buyer, string $password): array
{
    $name = trim((string) ($buyer['name'] ?? ''));
    $email = filter_var(trim((string) ($buyer['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    $phone = trim((string) ($buyer['phone'] ?? ''));
    if ($name === '' || !$email) {
        throw new RuntimeException('A valid name and email are required to create a buyer account.');
    }
    if (strlen($password) < 6) {
        throw new RuntimeException('Your buyer account password must be at least 6 characters long.');
    }

    market_checkout_ensure_optional_buyer_schema($pdo);
    $existing = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $existing->execute([(string) $email]);
    if ((int) $existing->fetchColumn() > 0) {
        throw new RuntimeException('An account already exists for this email. Please log in instead of creating a new one.');
    }

    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, phone, role, platform_role, account_status) VALUES (?, ?, ?, ?, 'grower', 'buyer', 'needs_confirmation')");
    $stmt->execute([$name, (string) $email, password_hash($password, PASSWORD_DEFAULT), $phone]);
    $userId = (int) $pdo->lastInsertId();
    market_checkout_activate_buyer_access($pdo, $userId);
    app_send_user_verification($pdo, $userId, 'NATCODEV Buyer');

    return [
        'id' => $userId,
        'name' => $name,
        'email' => (string) $email,
        'phone' => $phone,
    ];
}

function market_checkout_ref(): string
{
    return 'MKT-CHK-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

function market_tracking_ref(): string
{
    return 'MKT-TRK-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

function market_cart_rows(PDO $pdo): array
{
    $cart = market_cart_items();
    if (!$cart) {
        return [];
    }
    $ids = array_map('intval', array_keys($cart));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("
        SELECT l.*, c.name category_name, s.store_name, s.slug seller_slug, s.user_id seller_user_id
        FROM marketplace_listings l
        JOIN marketplace_sellers s ON s.id = l.seller_id
        LEFT JOIN marketplace_categories c ON c.id = l.category_id
        WHERE l.id IN ($placeholders) AND l.approval_status = 'approved' AND s.approval_status = 'approved'
        ORDER BY s.store_name, l.title
    ");
    $stmt->execute($ids);
    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $qty = max(1, (int) ($cart[(int) $row['id']] ?? 1));
        $row['cart_quantity'] = $qty;
        $row['cart_total'] = $qty * (float) $row['price'];
        $rows[] = $row;
    }
    return $rows;
}

function market_cart_save_for_later(PDO $pdo, array $user): bool
{
    $cart = market_cart_items();
    if (empty($cart) || empty($user['id'])) {
        return false;
    }

    $userId = (int) $user['id'];
    $stmt = $pdo->prepare("
        INSERT INTO marketplace_saved_carts (user_id, listing_id, quantity)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE quantity = VALUES(quantity), updated_at = NOW()
    ");

    foreach ($cart as $listingId => $quantity) {
        $stmt->execute([$userId, $listingId, $quantity]);
    }

    market_cart_clear(); // Clear the session cart after saving
    return true;
}

function market_settle_seller_wallet(PDO $pdo, int $sellerUserId, float $amount, string $reference, string $description): void
{
    if ($sellerUserId <= 0 || $amount <= 0) {
        return;
    }
    wallet_credit_once($pdo, $sellerUserId, $amount, $reference, $description, 'marketplace', $reference, ['source' => 'marketplace_checkout']);
}

function market_checkout_totals(array $rows): array
{
    $subtotal = array_sum(array_map(static fn(array $row): float => (float) $row['cart_total'], $rows));
    $deliveryFee = $rows ? 2500.0 : 0.0;
    $serviceFee = $rows ? max(500.0, $subtotal * 0.015) : 0.0;
    return [
        'subtotal' => $subtotal,
        'delivery_fee' => $deliveryFee,
        'service_fee' => $serviceFee,
        'total' => $subtotal + $deliveryFee + $serviceFee,
    ];
}

function market_ensure_orders_delivery_schema(PDO $pdo): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }
    foreach ([
        'delivery_state' => "VARCHAR(120) NULL",
        'delivery_lga' => "VARCHAR(120) NULL",
        'delivery_latitude' => "DECIMAL(10,7) NULL",
        'delivery_longitude' => "DECIMAL(10,7) NULL",
        'delivery_method' => "VARCHAR(60) NULL DEFAULT 'Standard Delivery'",
    ] as $col => $def) {
        app_add_column_if_missing($pdo, 'marketplace_orders', $col, $def);
    }
    $ensured = true;
}

function market_insert_checkout_orders(PDO $pdo, array $rows, ?array $user, array $buyer, array $payment): array
{
    market_ensure_orders_delivery_schema($pdo);
    $totals = market_checkout_totals($rows);
    $checkoutRef = (string) ($payment['checkout_ref'] ?? market_checkout_ref());
    $paid = (bool) ($payment['paid'] ?? false);
    $paymentMethod = (string) ($payment['method'] ?? 'bank_transfer');
    $providerPayload = isset($payment['provider_payload']) ? json_encode($payment['provider_payload'], JSON_UNESCAPED_SLASHES) : null;
    $createdOrders = [];
    $settlements = [];

    $deliveryState = trim((string) ($buyer['state'] ?? ''));
    $deliveryLga = trim((string) ($buyer['lga'] ?? ''));
    $deliveryLat = isset($buyer['latitude']) && is_numeric($buyer['latitude']) ? (float) $buyer['latitude'] : null;
    $deliveryLng = isset($buyer['longitude']) && is_numeric($buyer['longitude']) ? (float) $buyer['longitude'] : null;
    $deliveryMethod = trim((string) ($buyer['delivery_method'] ?? 'Standard Delivery'));

    $fullAddress = trim((string) ($buyer['address'] ?? ''));
    if ($deliveryLga !== '' && !str_contains(strtolower($fullAddress), strtolower($deliveryLga))) {
        $fullAddress .= ', ' . $deliveryLga;
    }
    if ($deliveryState !== '' && !str_contains(strtolower($fullAddress), strtolower($deliveryState))) {
        $fullAddress .= ', ' . $deliveryState;
    }

    $orderStmt = $pdo->prepare("
        INSERT INTO marketplace_orders
            (order_ref, checkout_ref, listing_id, seller_id, buyer_user_id, buyer_name, buyer_email, buyer_phone, quantity, unit_price, total_amount, status, payment_status, payment_method, delivery_status, delivery_address, delivery_state, delivery_lga, delivery_latitude, delivery_longitude, delivery_method, delivery_contact, tracking_ref, payment_reference, payment_provider_reference, payment_provider_payload, delivery_fee, service_fee, checkout_total, paid_at, settled_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'awaiting_seller', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($rows as $row) {
        $orderRef = marketplace_order_ref();
        $trackingRef = market_tracking_ref();
        $orderStmt->execute([
            $orderRef,
            $checkoutRef,
            (int) $row['id'],
            (int) $row['seller_id'],
            $user ? (int) $user['id'] : null,
            (string) $buyer['name'],
            (string) ($buyer['email'] ?? ''),
            (string) $buyer['phone'],
            (float) $row['cart_quantity'],
            (float) $row['price'],
            (float) $row['cart_total'],
            $paid ? 'paid' : 'pending_payment',
            $paid ? 'paid' : 'unpaid',
            $paymentMethod,
            $fullAddress,
            $deliveryState ?: null,
            $deliveryLga ?: null,
            $deliveryLat,
            $deliveryLng,
            $deliveryMethod,
            (string) $buyer['phone'],
            $trackingRef,
            (string) ($payment['reference'] ?? ''),
            (string) ($payment['provider_reference'] ?? ''),
            $providerPayload,
            (float) $totals['delivery_fee'],
            (float) $totals['service_fee'],
            (float) $totals['total'],
            $paid ? date('Y-m-d H:i:s') : null,
            null,
        ]);
        $createdOrders[] = ['order_ref' => $orderRef, 'tracking_ref' => $trackingRef];
        if ($paid) {
            $settlements[] = [
                'seller_user_id' => (int) ($row['seller_user_id'] ?? 0),
                'amount' => (float) $row['cart_total'],
                'reference' => 'SETTLE-' . $orderRef,
                'description' => 'Marketplace seller settlement ' . $orderRef,
            ];
        }
    }

    return [
        'checkout_ref' => $checkoutRef,
        'orders' => $createdOrders,
        'settlements' => $settlements,
        'totals' => $totals,
    ];
}

function market_settle_checkout_orders(PDO $pdo, string $checkoutRef): void
{
    $stmt = $pdo->prepare("
        SELECT o.*, s.user_id seller_user_id
        FROM marketplace_orders o
        JOIN marketplace_sellers s ON s.id = o.seller_id
        WHERE o.checkout_ref = ?
    ");
    $stmt->execute([$checkoutRef]);
    foreach ($stmt->fetchAll() as $order) {
        if ((string) ($order['payment_status'] ?? '') !== 'paid') {
            continue;
        }
        $amounts = revenue_apply_marketplace_order($pdo, $order);
        market_settle_seller_wallet(
            $pdo,
            (int) ($order['seller_user_id'] ?? 0),
            (float) $amounts['net'],
            'SETTLE-' . (string) $order['order_ref'],
            'Marketplace seller net settlement ' . (string) $order['order_ref']
        );
        $pdo->prepare("
            UPDATE marketplace_orders
            SET payout_status = 'settled', settled_at = COALESCE(settled_at, NOW())
            WHERE id = ?
        ")->execute([(int) $order['id']]);
    }
}

function market_initialize_monnify_checkout(PDO $pdo, array $rows, ?array $user, array $buyer): array
{
    if (!monnify_is_configured()) {
        return ['success' => false, 'error' => monnify_configuration_error()];
    }

    $totals = market_checkout_totals($rows);
    $checkoutRef = market_checkout_ref();
    $reference = 'NAT-MKT-' . date('ymdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
    $redirectUrl = app_base_url() . '/market/checkout.php?verify_monnify=' . urlencode($reference)
        . '&checkout_ref=' . urlencode($checkoutRef)
        . '&phone=' . urlencode((string) $buyer['phone']);

    $payload = [
        'amount' => round((float) $totals['total'], 2),
        'customerName' => (string) $buyer['name'],
        'customerEmail' => (string) $buyer['email'],
        'paymentReference' => $reference,
        'paymentDescription' => 'NATCODEV marketplace checkout ' . $checkoutRef,
        'currencyCode' => 'NGN',
        'contractCode' => monnify_env('MONNIFY_CONTRACT_CODE'),
        'redirectUrl' => $redirectUrl,
        'paymentMethods' => monnify_payment_methods(),
    ];

    $pdo->beginTransaction();
    try {
        $created = market_insert_checkout_orders($pdo, $rows, $user, $buyer, [
            'checkout_ref' => $checkoutRef,
            'method' => 'monnify',
            'reference' => $reference,
            'paid' => false,
        ]);
        $res = monnify_request('POST', '/api/v1/merchant/transactions/init-transaction', $payload);
        if (!$res['success']) {
            throw new RuntimeException((string) ($res['error'] ?? 'Unable to initialize Monnify payment.'));
        }
        $body = $res['data']['responseBody'] ?? [];
        $checkoutUrl = (string) ($body['checkoutUrl'] ?? '');
        if ($checkoutUrl === '') {
            throw new RuntimeException('Monnify did not return a checkout URL.');
        }
        $pdo->prepare("
            UPDATE marketplace_orders
            SET payment_provider_reference = ?, payment_provider_payload = ?
            WHERE checkout_ref = ?
        ")->execute([
            (string) ($body['transactionReference'] ?? ''),
            json_encode($body, JSON_UNESCAPED_SLASHES),
            $checkoutRef,
        ]);
        $pdo->commit();
        return $created + [
            'success' => true,
            'payment_url' => $checkoutUrl,
            'reference' => $reference,
            'provider_reference' => (string) ($body['transactionReference'] ?? ''),
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function market_verify_monnify_checkout(PDO $pdo, string $reference): array
{
    $reference = trim(preg_split('/[?&]/', $reference, 2)[0] ?? $reference);
    if ($reference === '') {
        return ['success' => false, 'error' => 'Missing marketplace payment reference.'];
    }

    $stmt = $pdo->prepare("SELECT * FROM marketplace_orders WHERE payment_reference = ? ORDER BY id LIMIT 1");
    $stmt->execute([$reference]);
    $order = $stmt->fetch();
    if (!$order) {
        return ['success' => false, 'error' => 'Marketplace payment reference was not found.'];
    }
    $checkoutRef = (string) $order['checkout_ref'];
    if ((string) ($order['payment_status'] ?? '') === 'paid') {
        return ['success' => true, 'status' => 'completed', 'checkout_ref' => $checkoutRef, 'phone' => (string) $order['buyer_phone'], 'duplicate' => true];
    }

    $providerReference = (string) ($order['payment_provider_reference'] ?: $reference);
    $res = monnify_request('GET', '/api/v2/transactions/' . rawurlencode($providerReference));
    if (!$res['success']) {
        return ['success' => false, 'error' => $res['error'] ?? 'Unable to verify Monnify payment.'];
    }

    $body = $res['data']['responseBody'] ?? [];
    $status = strtoupper((string) ($body['paymentStatus'] ?? ''));
    if ($status !== 'PAID') {
        return ['success' => true, 'status' => strtolower($status ?: 'pending'), 'checkout_ref' => $checkoutRef, 'phone' => (string) $order['buyer_phone'], 'paid' => false];
    }

    $expectedAmount = (float) ($order['checkout_total'] ?: $order['total_amount']);
    $amountPaid = (float) ($body['amountPaid'] ?? 0);
    if ($amountPaid <= 0 || $amountPaid + 0.01 < $expectedAmount) {
        return ['success' => false, 'error' => 'Marketplace payment amount is incomplete.'];
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare("
            UPDATE marketplace_orders
            SET payment_status = 'paid', payment_method = 'monnify', status = 'paid',
                payment_provider_reference = ?, payment_provider_payload = ?,
                paid_at = COALESCE(paid_at, NOW())
            WHERE checkout_ref = ?
        ")->execute([
            (string) ($body['transactionReference'] ?? $providerReference),
            json_encode($body, JSON_UNESCAPED_SLASHES),
            $checkoutRef,
        ]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
    market_settle_checkout_orders($pdo, $checkoutRef);

    return ['success' => true, 'status' => 'completed', 'checkout_ref' => $checkoutRef, 'phone' => (string) $order['buyer_phone'], 'paid' => true];
}
