<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/admin-layout.php';
require_once __DIR__ . '/../lib/marketplace.php';
require_once __DIR__ . '/../lib/monnify.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function market_boot(): PDO
{
    $pdo = db();
    marketplace_ensure_schema($pdo);
    return $pdo;
}

function market_asset_logo(): string
{
    return app_primary_logo_url();
}

function market_user(PDO $pdo): ?array
{
    return current_user($pdo);
}

function market_initials(string $name): string
{
    $name = trim($name);
    if ($name === '') {
        return 'NT';
    }
    $parts = preg_split('/\s+/', $name) ?: [];
    $letters = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $letters .= strtoupper(substr((string) $part, 0, 1));
    }
    return $letters !== '' ? $letters : 'NT';
}

function market_require_user(PDO $pdo): array
{
    $user = market_user($pdo);
    if (!$user) {
        redirect_to('../dashboard/login.php?next=' . rawurlencode('../market/seller-central.php'));
    }
    return $user;
}

function market_user_can_sell(PDO $pdo, array $user): bool
{
    if (!admin_feature_is_allowed($pdo, 'marketplace')) {
        return false;
    }
    if ((int) ($user['is_super_admin'] ?? 0) === 1) {
        return true;
    }
    $role = strtolower((string) ($user['platform_role'] ?? $user['role'] ?? ''));
    return in_array($role, [
        'grower',
        'farmer',
        'provider',
        'input_provider',
        'service_provider',
        'seller',
        'farm_hand',
        'agronomist',
        'agric_extensionist',
        'extensionist',
        'cooperative',
        'processor',
        'logistics',
        'investor',
        'admin',
    ], true);
}

function market_user_is_buyer(array $user): bool
{
    $role = strtolower((string) ($user['platform_role'] ?? $user['role'] ?? ''));
    return in_array($role, ['buyer', 'consumer', 'user'], true); // Add more buyer roles if needed
}

function market_user_is_seller(array $user): bool
{
    $role = strtolower((string) ($user['platform_role'] ?? $user['role'] ?? ''));
    return in_array($role, ['provider', 'input_provider', 'service_provider', 'seller', 'grower', 'farm_hand', 'processor', 'logistics'], true); // Add more seller roles if needed
}

function market_stakeholder_label(array $user): string
{
    $role = (string) ($user['platform_role'] ?? $user['role'] ?? 'stakeholder');
    return marketplace_status_label($role);
}

function market_url(string $path = 'index.php'): string
{
    return $path;
}

function market_dashboard_url(): string
{
    return '../dashboard/index.php';
}

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

function market_insert_checkout_orders(PDO $pdo, array $rows, ?array $user, array $buyer, array $payment): array
{
    $totals = market_checkout_totals($rows);
    $checkoutRef = (string) ($payment['checkout_ref'] ?? market_checkout_ref());
    $paid = (bool) ($payment['paid'] ?? false);
    $paymentMethod = (string) ($payment['method'] ?? 'bank_transfer');
    $providerPayload = isset($payment['provider_payload']) ? json_encode($payment['provider_payload'], JSON_UNESCAPED_SLASHES) : null;
    $createdOrders = [];
    $settlements = [];

    $orderStmt = $pdo->prepare("
        INSERT INTO marketplace_orders
            (order_ref, checkout_ref, listing_id, seller_id, buyer_user_id, buyer_name, buyer_email, buyer_phone, quantity, unit_price, total_amount, status, payment_status, payment_method, delivery_status, delivery_address, delivery_contact, tracking_ref, payment_reference, payment_provider_reference, payment_provider_payload, delivery_fee, service_fee, checkout_total, paid_at, settled_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'awaiting_seller', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
            (string) $buyer['address'],
            (string) $buyer['phone'],
            $trackingRef,
            (string) ($payment['reference'] ?? ''),
            (string) ($payment['provider_reference'] ?? ''),
            $providerPayload,
            (float) $totals['delivery_fee'],
            (float) $totals['service_fee'],
            (float) $totals['total'],
            $paid ? date('Y-m-d H:i:s') : null,
            $paid ? date('Y-m-d H:i:s') : null,
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
        market_settle_seller_wallet(
            $pdo,
            (int) ($order['seller_user_id'] ?? 0),
            (float) $order['total_amount'],
            'SETTLE-' . (string) $order['order_ref'],
            'Marketplace seller settlement ' . (string) $order['order_ref']
        );
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
                paid_at = COALESCE(paid_at, NOW()), settled_at = COALESCE(settled_at, NOW())
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

function market_upload_listing_image(string $field): ?string
{
    if (empty($_FILES[$field]) || (int) ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $upload = app_uploaded_file_info((array) $_FILES[$field], ['jpg', 'jpeg', 'png', 'webp'], 5 * 1024 * 1024, 'Marketplace product image');
    $dir = dirname(__DIR__) . '/uploads/marketplace';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $fileName = app_safe_upload_name('market_listing', $upload['name'], $upload['extension']);
    $target = $dir . '/' . $fileName;
    if (!move_uploaded_file($upload['tmp_name'], $target)) {
        throw new RuntimeException('Unable to upload marketplace image. Check upload folder permissions.');
    }
    return 'uploads/marketplace/' . $fileName;
}

function market_listing_image_url(array $item): string
{
    $path = trim((string) ($item['image_path'] ?? ''));
    if ($path !== '') {
        return '../' . ltrim($path, '/');
    }
    $text = strtolower((string) (($item['title'] ?? '') . ' ' . ($item['category_name'] ?? '') . ' ' . ($item['listing_type'] ?? '') . ' ' . ($item['summary'] ?? '')));
    if (str_contains($text, 'compost') || str_contains($text, 'fertilizer') || str_contains($text, 'mulch') || str_contains($text, 'soil')) {
        return '../assets/market/organic-compost.png';
    }
    if (str_contains($text, 'pruning') || str_contains($text, 'shear') || str_contains($text, 'tool') || str_contains($text, 'equipment') || str_contains($text, 'brush cutter')) {
        return '../assets/market/farm-tools-pruning.png';
    }
    if (str_contains($text, 'crew') || str_contains($text, 'labor') || str_contains($text, 'farm hand') || str_contains($text, 'planting')) {
        return '../assets/market/planting-crew-service.png';
    }
    if (str_contains($text, 'seedling') || str_contains($text, 'nursery') || str_contains($text, 'coconut')) {
        return '../assets/market/dwarf-coconut-seedlings.png';
    }
    return 'image.php?id=' . (int) ($item['id'] ?? 0);
}

function market_css(): string
{
    return '
    :root{--mk-green:#0f5b2c;--mk-green-2:#178448;--mk-deep:#0c2f1d;--mk-mint:#eef8ef;--mk-gold:#d6a928;--mk-teal:#20a69a;--mk-blue:#2374c6;--mk-orange:#f59e0b;--mk-red:#dc2626;--mk-ink:#101828;--mk-muted:#667085;--mk-line:#dce8dc;--mk-bg:#f4f9f1;--mk-panel:#fff;--mk-shadow:0 18px 45px rgba(16,24,40,.08)}
    *{box-sizing:border-box} body{margin:0;background:linear-gradient(135deg,#f4f9f1 0%,#edf7f1 52%,#f9fbf4 100%);color:var(--mk-ink);font-family:"Segoe UI",Arial,sans-serif} a{color:var(--mk-green);font-weight:850;text-decoration:none} a:hover{text-decoration:none;color:var(--mk-green-2)}
    .mk-brand{display:flex;gap:12px;align-items:center}.mk-brand img{width:48px;height:48px;border-radius:50%;background:#fff;border:1px solid var(--mk-line);object-fit:contain}.mk-brand strong{display:block;font-size:1.15rem;line-height:1}.mk-brand span{display:block;color:var(--mk-muted);font-size:.78rem;line-height:1.25;margin-top:3px}
    .mk-main{min-width:0}.mk-top{position:sticky;top:0;z-index:20;background:rgba(255,255,255,.92);backdrop-filter:blur(14px);border-bottom:1px solid rgba(16,24,40,.08);padding:14px 26px;display:flex;gap:16px;align-items:center;justify-content:space-between}.mk-search{flex:1;max-width:680px;position:relative}.mk-search input{width:100%;border:1px solid var(--mk-line);border-radius:10px;background:#fff;padding:13px 46px 13px 15px;font:inherit}.mk-search button{position:absolute;right:6px;top:6px;border:0;background:var(--mk-green);color:#fff;border-radius:8px;height:34px;min-width:38px;font-weight:900}.mk-top-actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap}.mk-pill{border:1px solid var(--mk-line);background:#fff;border-radius:10px;padding:10px 12px;font-weight:900;color:#283827}.mk-pill.primary{background:linear-gradient(135deg,var(--mk-green),var(--mk-teal));border:0;color:#fff}.mk-content{padding:26px;max-width:1520px;margin:0 auto}
    .mk-hero{display:grid;grid-template-columns:minmax(0,1.3fr) minmax(320px,.7fr);gap:18px;margin-bottom:18px}.mk-hero-card{border-radius:14px;padding:26px;background:linear-gradient(135deg,rgba(15,91,44,.96),rgba(32,166,154,.88)),url("../assets/hero/hero-banner.jpg") center/cover;color:#fff;box-shadow:var(--mk-shadow);min-height:260px;display:grid;align-content:end}.mk-hero-card h1{margin:0 0 10px;font-size:clamp(2rem,4vw,4rem);line-height:1}.mk-hero-card p{margin:0;max-width:760px;color:#eaf8ee;line-height:1.55}.mk-kpis{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.mk-kpi{background:#fff;border:1px solid rgba(16,24,40,.08);border-radius:12px;padding:16px;box-shadow:var(--mk-shadow)}.mk-kpi b{display:block;font-size:1.7rem;color:var(--mk-green);line-height:1}.mk-kpi span{display:block;color:var(--mk-muted);font-weight:800;font-size:.82rem;margin-top:6px}
    .mk-section{background:#fff;border:1px solid rgba(16,24,40,.08);border-radius:12px;box-shadow:var(--mk-shadow);padding:18px;margin-bottom:18px}.mk-section-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px}.mk-section-head h2{margin:0;color:#103d1b;font-size:1.2rem}.mk-section-head p{margin:4px 0 0;color:var(--mk-muted)}.mk-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.mk-card{border:1px solid var(--mk-line);border-radius:12px;background:#fff;overflow:hidden;display:flex;flex-direction:column;min-height:100%;transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease}.mk-card:hover{transform:translateY(-2px);border-color:#b8dbbc;box-shadow:0 14px 28px rgba(16,24,40,.1)}.mk-img{height:150px;background:linear-gradient(135deg,#dff4e2,#fff7d7);display:grid;place-items:center;color:var(--mk-green);font-size:2.3rem;font-weight:950}.mk-card-body{padding:13px;display:grid;gap:8px}.mk-card h3{margin:0;color:#132719;font-size:1rem;line-height:1.3}.mk-meta{color:var(--mk-muted);font-size:.84rem;line-height:1.35}.mk-price{font-size:1.18rem;font-weight:950;color:#0b3b1d}.mk-badges{display:flex;gap:6px;flex-wrap:wrap}.mk-badge{display:inline-flex;align-items:center;gap:5px;border-radius:999px;background:#edf8ee;color:#12622f;padding:5px 8px;font-size:.72rem;font-weight:950}.mk-badge.gold{background:#fff6d7;color:#8a6100}.mk-badge.blue{background:#eaf3ff;color:#175eaa}.mk-badge.red{background:#fff1f2;color:#b91c1c}.mk-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:auto}.mk-btn,button.mk-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;border:0;border-radius:9px;padding:10px 12px;background:var(--mk-green);color:#fff;font-weight:950;cursor:pointer;text-decoration:none}.mk-btn:hover{background:var(--mk-green-2);color:#fff}.mk-btn.secondary{background:#eef8ef;color:var(--mk-green);border:1px solid var(--mk-line)}.mk-btn.gold{background:var(--mk-gold);color:#102514}
    .mk-filter{display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:10px;align-items:end}.mk-field label{display:block;font-size:.78rem;font-weight:950;color:#344232;margin:0 0 6px}.mk-field input,.mk-field select,.mk-field textarea{width:100%;border:1px solid var(--mk-line);border-radius:9px;padding:11px 12px;font:inherit;background:#fff}.mk-field textarea{min-height:110px}.mk-table{width:100%;border-collapse:separate;border-spacing:0}.mk-table th,.mk-table td{text-align:left;padding:11px;border-bottom:1px solid #edf3ec;vertical-align:top}.mk-table th{background:#f1faef;color:#173c20;font-size:.82rem;text-transform:uppercase;letter-spacing:.04em}.mk-alert{padding:12px 14px;border-radius:10px;margin-bottom:14px;font-weight:800}.mk-alert.ok{background:#eaf8ef;color:#11602f;border:1px solid #bce6c8}.mk-alert.err{background:#fff1f2;color:#b91c1c;border:1px solid #fecdd3}.mk-two{display:grid;grid-template-columns:minmax(0,1.4fr) minmax(330px,.6fr);gap:18px}.mk-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.mk-form-grid .wide{grid-column:1/-1}.mk-store-head{display:flex;gap:16px;align-items:center}.mk-store-avatar{width:74px;height:74px;border-radius:18px;background:linear-gradient(135deg,#dcfce7,#fff7d7);display:grid;place-items:center;color:var(--mk-green);font-size:2rem;font-weight:950;flex:0 0 auto}.mk-empty{border:1px dashed var(--mk-line);border-radius:10px;padding:18px;color:var(--mk-muted);background:#fbfdf9}.mk-tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px}.mk-tabs a{padding:10px 12px;border-radius:9px;background:#fff;border:1px solid var(--mk-line);color:#29462c}.mk-tabs a.active{background:var(--mk-green);color:#fff;border-color:var(--mk-green)}
    .public-market{min-height:100vh}.mk-public-top{position:sticky;top:0;z-index:50;background:rgba(255,255,255,.96);backdrop-filter:blur(14px);border-bottom:1px solid rgba(16,24,40,.08);box-shadow:0 8px 24px rgba(16,24,40,.04)}.mk-public-bar{max-width:1480px;margin:0 auto;padding:12px 26px;display:flex;align-items:center;justify-content:space-between;gap:18px}.public-brand{color:var(--mk-green)}.mk-public-nav{display:flex;align-items:center;gap:6px;flex-wrap:wrap}.mk-public-nav a{padding:9px 10px;border-radius:8px;color:#344232;font-weight:900}.mk-public-nav a.active,.mk-public-nav a:hover{background:#eef8ef;color:var(--mk-green)}.mk-public-search{max-width:1480px;margin:0 auto;padding:0 26px 12px}.mk-public-search .mk-search{max-width:760px}.public-market .mk-content{max-width:1480px}.public-market .mk-section{box-shadow:0 10px 30px rgba(16,24,40,.07)}
    @media(max-width:1180px){.mk-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.mk-hero,.mk-two{grid-template-columns:1fr}.mk-filter{grid-template-columns:1fr 1fr}.mk-form-grid{grid-template-columns:1fr}.mk-public-bar{align-items:flex-start;flex-direction:column}.mk-public-nav{width:100%}}
    @media(max-width:720px){.mk-content{padding:16px}.mk-grid,.mk-kpis,.mk-filter{grid-template-columns:1fr}.mk-public-bar,.mk-public-search{padding-left:16px;padding-right:16px}.mk-top-actions{width:100%}.mk-public-nav{overflow:auto;flex-wrap:nowrap}}
    ';
}

function market_icon(string $name): string
{
    $icons = [
        'home' => '⌂', 'store' => '▣', 'seller' => '▤', 'cart' => '🛒', 'orders' => '↗',
        'catalog' => '☰', 'leaf' => '☘', 'shield' => '✓', 'search' => '⌕', 'logout' => '⇥',
        'dash' => '▦', 'plus' => '+', 'chat' => '◉', 'truck' => '▱',
    ];
    return $icons[$name] ?? '•';
}

function market_header(string $title, string $active = 'marketplace', ?PDO $pdo = null): void
{
    $pdo = $pdo ?: market_boot();
    $user = market_user($pdo);
    $logo = market_asset_logo();
    $q = trim((string) ($_GET['q'] ?? ''));
    $cartCount = market_cart_count();
    $initials = $user ? market_initials((string) ($user['name'] ?? 'User')) : 'NT';
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($title) ?> - NATCODEV Marketplace</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root{--primary:#1a5f2a;--primary-dark:#144a21;--primary-light:#2d8041;--secondary:#f5f5f5;--accent:#ffc107;--text-primary:#1f2937;--text-secondary:#6b7280;--border:#e5e7eb;--success:#10b981;--warning:#f59e0b;--danger:#ef4444;--white:#fff;--shadow:0 1px 3px rgba(0,0,0,.1),0 1px 2px rgba(0,0,0,.06);--shadow-lg:0 10px 15px -3px rgba(0,0,0,.1),0 4px 6px -2px rgba(0,0,0,.05);--mk-line:var(--border)}
    *{margin:0;padding:0;box-sizing:border-box}body{font-family:Inter,"Segoe UI",Arial,sans-serif;background:#f9fafb;color:var(--text-primary);line-height:1.5}a{text-decoration:none;color:inherit}
    .header{background:var(--white);border-bottom:1px solid var(--border);position:sticky;top:0;z-index:100}.header-container{max-width:1480px;margin:0 auto;padding:0 1.5rem;height:70px;display:flex;align-items:center;justify-content:space-between;gap:1.5rem}
    .logo{display:flex;align-items:center;gap:.75rem;color:var(--primary);min-width:210px}.logo-icon{width:42px;height:42px;background:#fff;border:1px solid var(--border);border-radius:9px;display:grid;place-items:center;overflow:hidden}.logo-icon img{width:100%;height:100%;object-fit:contain}.logo-text{font-size:1.25rem;font-weight:800;line-height:1.1}.logo-text span{display:block;font-size:.72rem;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.05em}
    .nav-menu{display:flex;gap:1.4rem;list-style:none}.nav-menu a{color:var(--text-secondary);font-weight:700;font-size:.94rem;padding:.5rem 0;border-bottom:2px solid transparent}.nav-menu a:hover,.nav-menu a.active{color:var(--primary);border-bottom-color:var(--primary)}
    .header-actions{display:flex;align-items:center;gap:.8rem}.notification-btn{position:relative;background:none;border:0;font-size:1.2rem;color:var(--text-secondary);cursor:pointer;padding:.5rem}.badge{position:absolute;top:0;right:0;background:var(--danger);color:#fff;font-size:.62rem;font-weight:700;padding:.12rem .36rem;border-radius:999px}.user-menu{display:flex;align-items:center;gap:.55rem;min-width:0;max-width:220px;padding:.45rem .7rem;background:var(--secondary);border-radius:.5rem;font-weight:700;font-size:.9rem}.user-avatar{width:32px;height:32px;flex:0 0 32px;background:var(--primary);color:#fff;border-radius:50%;display:grid;place-items:center;font-weight:800;font-size:.82rem}.user-menu span{display:block;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .mk-section{max-width:1480px;margin:2rem auto;padding:0 1.5rem}.mk-section-head{display:flex;justify-content:space-between;align-items:center;gap:1.5rem;margin-bottom:1.5rem}.mk-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1.5rem}
    .mk-card{background:var(--white);border-radius:.75rem;box-shadow:var(--shadow);border:1px solid var(--border);overflow:hidden;transition:.2s}.mk-card:hover{transform:translateY(-2px);box-shadow:var(--shadow-lg)}
    .mk-img{display:block;height:200px;background:#f3f4f6;position:relative}.mk-img img{width:100%;height:100%;object-fit:cover}.mk-badge{position:absolute;top:.75rem;left:.75rem;background:rgba(26,95,42,.94);color:#fff;padding:.32rem .55rem;border-radius:999px;font-size:.75rem;font-weight:850}
    .mk-card-body{padding:1rem}.mk-card-body h3{font-size:1rem;font-weight:850;margin-bottom:.5rem}.mk-meta{color:var(--text-secondary);font-size:.84rem;margin-bottom:.45rem;display:flex;align-items:center;gap:.4rem}
    .mk-price{font-size:1.2rem;font-weight:900;color:var(--primary);margin:.75rem 0}.mk-actions{display:grid;grid-template-columns:1fr 1fr;gap:.65rem;margin-top:1rem}
    .mk-btn,.btn{display:inline-flex;align-items:center;justify-content:center;gap:.5rem;padding:.65rem 1rem;border-radius:.5rem;font-weight:800;cursor:pointer;border:1px solid transparent;transition:.2s}
    .mk-btn.primary,.btn-primary{background:var(--primary);color:#fff}.mk-btn.primary:hover{background:var(--primary-dark)}
    .mk-btn.secondary,.btn-outline{background:#fff;color:var(--primary);border-color:var(--border)}.mk-btn.secondary:hover{background:#f9fafb}
    .mk-empty{background:var(--white);border:1px dashed var(--border);padding:3rem;text-align:center;border-radius:1rem;color:var(--text-secondary);font-weight:700}
    .mk-alert{padding:1rem;border-radius:.5rem;margin-bottom:1.5rem;font-weight:700}.mk-alert.ok{background:#f0fdf4;color:var(--primary-dark);border:1px solid #bbf7d0}.mk-alert.err{background:#fef2f2;color:var(--danger);border:1px solid #fecaca}
    .mk-store-head{display:flex;gap:1.5rem;align-items:center}.mk-store-avatar{width:90px;height:90px;border-radius:1rem;background:linear-gradient(135deg,#e0ffe0,#fff7e0);display:grid;place-items:center;color:var(--primary);font-size:3rem;flex:0 0 auto}.mk-store-head .mk-badges{margin-bottom:.5rem}.mk-store-head .mk-badge{font-size:.85rem;padding:.4rem .7rem}
    <?= market_css() ?>
  </style>
</head>
<body>
  <header class="header">
    <div class="header-container">
      <a href="index.php" class="logo">
        <div class="logo-icon"><img src="<?= e($logo) ?>" alt="NATCODEV"></div>
        <div class="logo-text">NATCODEV<span>Marketplace</span></div>
      </a>
      <nav>
        <ul class="nav-menu">
          <li><a href="index.php" class="<?= $active === 'marketplace' ? 'active' : '' ?>">Marketplace</a></li>
          <li><a href="stores.php" class="<?= $active === 'stores' ? 'active' : '' ?>">Seller Directory</a></li>
          <li><a href="featured.php" class="<?= $active === 'featured' ? 'active' : '' ?>">Featured</a></li>
          <li><a href="seller-central.php" class="<?= $active === 'seller' ? 'active' : '' ?>">Sell on NATCODEV</a></li>
          <li><a href="orders.php" class="<?= $active === 'orders' ? 'active' : '' ?>">Orders</a></li>
        </ul>
      </nav>
      <div class="header-actions">
        <a class="notification-btn" href="cart.php" aria-label="Cart"><i class="fas fa-shopping-cart"></i><?php if ($cartCount > 0): ?><span class="badge"><?= $cartCount ?></span><?php endif; ?></a>
        <a class="user-menu" href="<?= $user ? '../buyer/index.php' : '../buyer/login.php' ?>">
          <div class="user-avatar"><?= e($initials) ?></div>
          <span><?= e((string) ($user['name'] ?? 'Guest')) ?></span>
        </a>
      </div>
    </div>
  </header>
  <main>

<?php
}

function market_footer(): void
{
    ?>
  </main>
  <footer style="background:#fff;border-top:1px solid #e5e7eb;padding:3rem 1.5rem;margin-top:4rem">
    <div style="max-width:1480px;margin:0 auto;display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:2rem">
      <div>
        <div style="color:var(--primary);font-weight:800;font-size:1.2rem;margin-bottom:1rem">NATCODEV Marketplace</div>
        <p style="color:var(--text-secondary);font-size:.9rem;line-height:1.6">The official hub for coconut business transactions, connecting growers, providers, and offtakers across Nigeria.</p>
      </div>
      <div>
        <h4 style="margin-bottom:1rem">Quick Links</h4>
        <ul style="list-style:none;color:var(--text-secondary);font-size:.9rem;display:grid;gap:.5rem">
          <li><a href="index.php">Marketplace Home</a></li>
          <li><a href="stores.php">Seller Directory</a></li>
          <li><a href="seller-central.php">Become a Seller</a></li>
          <li><a href="orders.php">Track Orders</a></li>
        </ul>
      </div>
      <div>
        <h4 style="margin-bottom:1rem">Support</h4>
        <ul style="list-style:none;color:var(--text-secondary);font-size:.9rem;display:grid;gap:.5rem">
          <li><a href="../dashboard/inbox.php">Help Center</a></li>
          <li><a href="../dashboard/wallet.php">Wallet & Payments</a></li>
          <li><a href="../contact.php">Contact Us</a></li>
          <li><a href="../terms.php">Terms of Service</a></li>
        </ul>
      </div>
    </div>
    <div style="max-width:1480px;margin:2rem auto 0;padding-top:2rem;border-top:1px solid #e5e7eb;display:flex;justify-content:space-between;color:var(--text-secondary);font-size:.85rem">
      <div>&copy; 2026 NATCODEV. All rights reserved.</div>
      <div style="display:flex;gap:1.5rem"><a href="../privacy.php">Privacy</a><a href="../cookies.php">Cookies</a></div>
    </div>
  </footer>
  <script src="../lib/location-picker.js"></script>
</body>
</html>
<?php
}

function market_listing_query(PDO $pdo, array $filters = [], int $limit = 24): array
{
    $where = ["l.approval_status = 'approved'", "s.approval_status = 'approved'"];
    $params = [];
    if (!empty($filters['q'])) {
        $where[] = "(l.title LIKE ? OR l.summary LIKE ? OR l.description LIKE ? OR l.location_label LIKE ? OR s.store_name LIKE ? OR s.location_label LIKE ? OR s.coverage_area LIKE ? OR c.name LIKE ?)";
        $like = '%' . $filters['q'] . '%';
        array_push($params, $like, $like, $like, $like, $like, $like, $like, $like);
    }
    if (!empty($filters['category_id'])) {
        $where[] = 'l.category_id = ?';
        $params[] = (int) $filters['category_id'];
    }
    if (!empty($filters['listing_type'])) {
        $where[] = 'l.listing_type = ?';
        $params[] = (string) $filters['listing_type'];
    }
    if (!empty($filters['state'])) {
        $where[] = "(l.location_label LIKE ? OR s.location_label LIKE ? OR s.coverage_area LIKE ?)";
        $like = '%' . (string) $filters['state'] . '%';
        array_push($params, $like, $like, $like);
    }
    if (!empty($filters['lga'])) {
        $where[] = "(l.location_label LIKE ? OR s.location_label LIKE ? OR s.coverage_area LIKE ?)";
        $like = '%' . (string) $filters['lga'] . '%';
        array_push($params, $like, $like, $like);
    }
    $sql = "
        SELECT l.*, c.name category_name, s.store_name, s.slug seller_slug, s.seller_type, s.verification_status, s.location_label seller_location
        FROM marketplace_listings l
        JOIN marketplace_sellers s ON s.id = l.seller_id
        LEFT JOIN marketplace_categories c ON c.id = l.category_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY l.is_featured DESC, l.created_at DESC
        LIMIT " . max(1, min(80, $limit));
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function market_render_listing_card(array $item): void
{
    $id = (int) $item['id'];
    $unit = trim((string) ($item['unit'] ?: $item['price_unit'] ?: 'unit'));
    ?>
    <article class="mk-card">
      <a class="mk-img" href="product.php?id=<?= $id ?>" style="overflow:hidden"><img src="<?= e(market_listing_image_url($item)) ?>" alt="<?= e((string) $item['title']) ?>" style="width:100%;height:100%;object-fit:cover"></a>
      <div class="mk-card-body">
        <div class="mk-badges">
          <span class="mk-badge"><?= e((string) ($item['category_name'] ?: marketplace_status_label((string) $item['listing_type']))) ?></span>
          <?php if ((string) ($item['verification_status'] ?? '') === 'verified'): ?><span class="mk-badge gold">Verified seller</span><?php endif; ?>
        </div>
        <h3><a href="product.php?id=<?= $id ?>"><?= e((string) $item['title']) ?></a></h3>
        <div class="mk-meta"><?= e((string) ($item['summary'] ?: $item['store_name'])) ?></div>
        <div class="mk-price"><?= e(marketplace_money((float) $item['price'])) ?> <small>/ <?= e($unit) ?></small></div>
        <div class="mk-meta"><?= e((string) $item['store_name']) ?> · <?= e((string) ($item['location_label'] ?: $item['seller_location'] ?: 'Coverage available')) ?></div>
        <div class="mk-actions">
          <a class="mk-btn" href="product.php?id=<?= $id ?>">View / Request</a>
          <a class="mk-btn secondary" href="store.php?seller=<?= e((string) $item['seller_slug']) ?>">Store</a>
        </div>
      </div>
    </article>
<?php
}
