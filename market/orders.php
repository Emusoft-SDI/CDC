<?php
declare(strict_types=1);

require_once __DIR__ . '/_market.php';

$pdo = market_boot();
$user = market_user($pdo);
$checkoutRef = trim((string) ($_GET['checkout_ref'] ?? ''));
$phone = trim((string) ($_GET['phone'] ?? ''));
$orders = [];
$lookupAttempted = $checkoutRef !== '' || $phone !== '';

if ($user && $checkoutRef === '' && $phone === '') {
    // ... existing query ...
} elseif ($checkoutRef !== '' && $phone !== '') {
    if (!app_check_rate_limit('order_lookup', 15, 600)) {
        $error = 'Too many lookup attempts. Please try again in 10 minutes.';
        $orders = [];
    } else {
        $stmt = $pdo->prepare("
            SELECT o.*, l.id listing_id, l.title listing_title, l.summary listing_summary, l.unit, l.image_path, s.store_name, s.slug seller_slug, s.verification_status, s.user_id seller_user_id
            FROM marketplace_orders o
            JOIN marketplace_listings l ON l.id = o.listing_id
            JOIN marketplace_sellers s ON s.id = o.seller_id
            WHERE o.checkout_ref = ? AND o.buyer_phone = ?
            ORDER BY o.created_at DESC
            LIMIT 50
        ");
        $stmt->execute([$checkoutRef, $phone]);
        $orders = $stmt->fetchAll();
        if (!$orders) {
            $error = 'No order found with the provided reference and phone number.';
        }
    }
}

$primary = $orders[0] ?? null;
$orderSubtotal = array_sum(array_map(static fn(array $row): float => (float) $row['total_amount'], $orders));
$orderTotal = $primary && (float) ($primary['checkout_total'] ?? 0) > 0 ? (float) $primary['checkout_total'] : $orderSubtotal;
$deliveryFee = $primary ? (float) ($primary['delivery_fee'] ?? 0) : 0.0;
$serviceFee = $primary ? (float) ($primary['service_fee'] ?? 0) : 0.0;
$orderQty = array_sum(array_map(static fn(array $row): float => (float) $row['quantity'], $orders));
$displayCheckoutRef = (string) ($primary['checkout_ref'] ?? $checkoutRef);
$trackingRef = (string) ($primary['tracking_ref'] ?? '');
$paymentStatus = (string) ($primary['payment_status'] ?? 'pending');
$deliveryStatus = (string) ($primary['delivery_status'] ?? 'not_started');
$orderStatus = (string) ($primary['status'] ?? 'pending');
$buyerName = (string) ($primary['buyer_name'] ?? ($user['name'] ?? 'Marketplace buyer'));
$buyerPhone = (string) ($primary['buyer_phone'] ?? $phone);
$deliveryAddress = (string) ($primary['delivery_address'] ?? 'Delivery address appears after checkout.');
$sellerName = (string) ($primary['store_name'] ?? 'Seller');
$sellerMessage = trim((string) ($primary['fulfillment_note'] ?? ''));
if ($sellerMessage === '') {
    $sellerMessage = $orders ? 'Thank you for your order. The seller will keep this order updated as fulfillment progresses.' : 'Seller messages appear after an order is found.';
}
$notice = '';
$error = '';
$orderedListingIds = array_values(array_unique(array_map(static fn(array $row): int => (int) $row['listing_id'], $orders)));
$sponsoredListings = [];
$featuredSellers = [];
try {
    $excludeSql = $orderedListingIds ? ('AND l.id NOT IN (' . implode(',', array_fill(0, count($orderedListingIds), '?')) . ')') : '';
    $sponsoredSql = "
        SELECT l.*, c.name category_name, s.store_name, s.slug seller_slug, s.seller_type, s.verification_status, s.location_label seller_location
        FROM marketplace_listings l
        JOIN marketplace_sellers s ON s.id = l.seller_id
        LEFT JOIN marketplace_categories c ON c.id = l.category_id
        WHERE l.approval_status = 'approved' AND s.approval_status = 'approved' {$excludeSql}
        ORDER BY l.is_featured DESC, l.created_at DESC
        LIMIT 4
    ";
    $stmt = $pdo->prepare($sponsoredSql);
    $stmt->execute($orderedListingIds);
    $sponsoredListings = $stmt->fetchAll();

    $stmt = $pdo->query("
        SELECT s.*, COUNT(l.id) listing_count
        FROM marketplace_sellers s
        LEFT JOIN marketplace_listings l ON l.seller_id = s.id AND l.approval_status = 'approved'
        WHERE s.approval_status = 'approved'
        GROUP BY s.id
        ORDER BY s.is_featured DESC, s.verification_status = 'verified' DESC, listing_count DESC, s.created_at DESC
        LIMIT 3
    ");
    $featuredSellers = $stmt->fetchAll();
} catch (Throwable $e) {
    $sponsoredListings = [];
    $featuredSellers = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'pay_wallet' && verify_csrf($_POST['_csrf'] ?? null) && $orders) {
    if (!$user) {
        $error = 'Sign in to pay this marketplace order with your NATCODEV wallet.';
    } elseif ($paymentStatus === 'paid') {
        $notice = 'This marketplace order is already paid.';
    } else {
        try {
            wallet_ensure_schema($pdo);
            $pdo->beginTransaction();
            $wallet = wallet_get_or_create($pdo, (int) $user['id']);
            $lock = $pdo->prepare("SELECT * FROM wallets WHERE id = ? FOR UPDATE");
            $lock->execute([(int) $wallet['id']]);
            $wallet = $lock->fetch();
            $before = (float) ($wallet['balance'] ?? 0);
            if ($before < $orderTotal) {
                throw new RuntimeException('Insufficient wallet balance. Fund your wallet, then return to complete this order.');
            }
            $after = $before - $orderTotal;
            $pdo->prepare("UPDATE wallets SET balance = ? WHERE id = ?")->execute([$after, (int) $wallet['id']]);
            $pdo->prepare("
                INSERT INTO wallet_transactions
                    (wallet_id, user_id, amount, type, direction, description, reference, provider, status, balance_before, balance_after, completed_at)
                VALUES (?, ?, ?, 'debit', 'outflow', ?, ?, 'marketplace', 'completed', ?, ?, NOW())
            ")->execute([(int) $wallet['id'], (int) $user['id'], $orderTotal, 'Marketplace tracked order payment ' . $displayCheckoutRef, $displayCheckoutRef, $before, $after]);
            $ids = array_map(static fn(array $row): int => (int) $row['id'], $orders);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $pdo->prepare("
                UPDATE marketplace_orders
                SET payment_status = 'paid', payment_method = 'wallet', status = 'paid', paid_at = COALESCE(paid_at, NOW()), settled_at = COALESCE(settled_at, NOW())
                WHERE id IN ($placeholders)
            ")->execute($ids);
            $pdo->commit();
            foreach ($orders as $order) {
                market_settle_seller_wallet(
                    $pdo,
                    (int) ($order['seller_user_id'] ?? 0),
                    (float) $order['total_amount'],
                    'SETTLE-' . (string) $order['order_ref'],
                    'Marketplace seller settlement ' . (string) $order['order_ref']
                );
            }
            redirect_to('orders.php?checkout_ref=' . rawurlencode($displayCheckoutRef) . '&phone=' . rawurlencode($buyerPhone) . '&paid=1');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e->getMessage();
        }
    }
}
if (isset($_GET['paid'])) {
    $notice = 'Payment completed. Seller settlement has been recorded and delivery tracking continues.';
}
if (isset($_GET['payment_pending'])) {
    $notice = 'Payment is still pending. Complete the Monnify payment, then refresh this order.';
}

if (isset($_GET['proof']) && $orders) {
    $fileRef = $trackingRef !== '' ? $trackingRef : ($displayCheckoutRef !== '' ? $displayCheckoutRef : 'marketplace-order');
    if ((string) $_GET['proof'] === 'print') {
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>NATCODEV Marketplace Proof - <?= e($fileRef) ?></title>
  <style>
    body{font-family:Arial,sans-serif;color:#122317;margin:0;background:#f6f8f3}.proof{max-width:820px;margin:30px auto;background:#fff;border:1px solid #dfe8d8;border-radius:14px;padding:34px}.head{display:flex;justify-content:space-between;gap:20px;border-bottom:3px solid #0b6b33;padding-bottom:16px}.brand{font-size:24px;font-weight:900;color:#0b6b33}.brand span{display:block;font-size:13px;color:#d5a929;letter-spacing:3px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin:22px 0}.box{border:1px solid #e4eadf;border-radius:10px;padding:12px}.box small{display:block;color:#667085;font-weight:700}.box strong{color:#0b6b33}.items{width:100%;border-collapse:collapse;margin-top:18px}th,td{border-bottom:1px solid #e4eadf;text-align:left;padding:10px}th{background:#eef8ef}.actions{text-align:center;margin:18px}@media print{.actions{display:none}.proof{margin:0;border:0}}
  </style>
</head>
<body>
  <div class="actions"><button onclick="window.print()">Print / Save as PDF</button></div>
  <section class="proof">
    <div class="head"><div class="brand">NATCODEV <span>MARKETPLACE</span></div><div><strong>Order Proof</strong><br><?= e(date('Y-m-d H:i:s')) ?></div></div>
    <div class="grid">
      <div class="box"><small>Checkout Reference</small><strong><?= e($displayCheckoutRef) ?></strong></div>
      <div class="box"><small>Tracking Reference</small><strong><?= e($trackingRef ?: 'Pending') ?></strong></div>
      <div class="box"><small>Buyer</small><strong><?= e($buyerName) ?></strong><br><?= e($buyerPhone) ?></div>
      <div class="box"><small>Total</small><strong><?= e(marketplace_money((float) $orderTotal)) ?></strong></div>
      <div class="box"><small>Payment</small><strong><?= e(marketplace_status_label($paymentStatus)) ?></strong></div>
      <div class="box"><small>Delivery</small><strong><?= e(marketplace_status_label($deliveryStatus)) ?></strong></div>
    </div>
    <h3>Items</h3>
    <table class="items"><thead><tr><th>Item</th><th>Qty</th><th>Seller</th><th>Amount</th></tr></thead><tbody>
      <?php foreach ($orders as $order): ?><tr><td><?= e((string) $order['listing_title']) ?></td><td><?= e((string) $order['quantity']) ?></td><td><?= e((string) $order['store_name']) ?></td><td><?= e(marketplace_money((float) $order['total_amount'])) ?></td></tr><?php endforeach; ?>
    </tbody></table>
    <p><strong>Delivery Address:</strong><br><?= nl2br(e($deliveryAddress)) ?></p>
  </section>
</body>
</html>
<?php
        exit;
    }
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="natcodev-marketplace-proof-' . preg_replace('/[^A-Za-z0-9_-]+/', '-', $fileRef) . '.txt"');
    echo "NATCODEV Marketplace Order Proof\n";
    echo "Checkout Reference: {$displayCheckoutRef}\n";
    echo "Tracking Reference: " . ($trackingRef !== '' ? $trackingRef : 'Pending') . "\n";
    echo "Buyer: {$buyerName}\n";
    echo "Phone: {$buyerPhone}\n";
    echo "Payment Status: " . marketplace_status_label($paymentStatus) . "\n";
    echo "Delivery Status: " . marketplace_status_label($deliveryStatus) . "\n";
    echo "Total Amount: " . marketplace_money((float) $orderTotal) . "\n";
    echo "Generated: " . date('Y-m-d H:i:s') . "\n\n";
    echo "Items\n";
    foreach ($orders as $order) {
        echo "- " . (string) $order['listing_title'] . " | Qty: " . (string) $order['quantity'] . " | Amount: " . marketplace_money((float) $order['total_amount']) . " | Seller: " . (string) $order['store_name'] . "\n";
    }
    exit;
}

function track_is_done(?array $primary, string $stage): bool
{
    if (!$primary) {
        return false;
    }
    $payment = (string) ($primary['payment_status'] ?? '');
    $delivery = (string) ($primary['delivery_status'] ?? '');
    $status = (string) ($primary['status'] ?? '');
    return match ($stage) {
        'created' => true,
        'paid' => $payment === 'paid',
        'accepted' => in_array($status, ['accepted', 'preparing', 'ready', 'scheduled', 'in_transit', 'completed', 'paid'], true),
        'packed' => in_array($delivery, ['packing', 'ready_for_pickup', 'scheduled', 'in_transit', 'delivered'], true),
        'transit' => in_array($delivery, ['in_transit', 'delivered'], true),
        'delivered' => $delivery === 'delivered' || $status === 'completed',
        default => false,
    };
}

function track_date(?array $row): string
{
    if (!$row || empty($row['created_at'])) {
        return 'Pending update';
    }
    return date('M j, Y - h:i A', strtotime((string) $row['created_at']));
}

$logo = app_primary_logo_url();
$initials = $user ? market_initials((string) ($user['name'] ?? 'User')) : 'NT';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Track Marketplace Order - NATCODEV Marketplace</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root{--green:#0b6b33;--green-dark:#06451f;--mint:#eef8ef;--gold:#d5a929;--teal:#1f9d8a;--ink:#101828;--muted:#667085;--line:#e4eadf;--bg:#fbfcf8;--shadow:0 18px 48px rgba(16,24,40,.08)}
    *{box-sizing:border-box}body{margin:0;background:var(--bg);font-family:"Segoe UI",Arial,sans-serif;color:var(--ink)}a{text-decoration:none;color:inherit}.top{height:82px;background:#fff;border-bottom:1px solid var(--line);display:grid;grid-template-columns:auto auto minmax(280px,460px) auto;gap:24px;align-items:center;padding:0 32px;position:sticky;top:0;z-index:20}.brand{display:flex;align-items:center;gap:12px;color:var(--green);min-width:245px}.brand img{width:58px;height:58px;border-radius:50%;object-fit:contain}.brand strong{font-size:1.48rem;letter-spacing:.02em;line-height:.92}.brand span{display:block;font-size:.82rem;color:var(--gold);font-weight:950;letter-spacing:.22em}.brand small{font-size:.72rem;color:#5a6d55;font-weight:800}.nav{display:flex;align-items:center;gap:24px;font-weight:900;white-space:nowrap}.nav a{color:#0d1b12}.nav a:hover{color:var(--green)}.nav a.active,.top-actions a.active{color:var(--green);border-bottom:3px solid var(--green);padding-bottom:25px}.search{display:flex;align-items:center;border:1px solid var(--line);border-radius:10px;overflow:hidden;background:#fff;box-shadow:0 8px 24px rgba(16,24,40,.04)}.search input{border:0;padding:15px 16px;width:100%;font:inherit}.search button{width:60px;border:0;background:var(--green);color:#fff;font-size:1.1rem;align-self:stretch}.top-actions{display:flex;align-items:center;gap:18px;font-weight:900;justify-content:flex-end;white-space:nowrap}.cart{position:relative}.cart b{position:absolute;right:-12px;top:-14px;background:var(--green);color:#fff;width:20px;height:20px;border-radius:50%;display:grid;place-items:center;font-size:.7rem}.acct{display:flex;align-items:center;gap:8px}.avatar{width:31px;height:31px;border-radius:50%;background:var(--mint);display:grid;place-items:center;color:var(--green);font-weight:950}
    .hero{min-height:330px;background:linear-gradient(90deg,rgba(4,35,14,.66) 0%,rgba(4,35,14,.34) 46%,rgba(4,35,14,.05) 72%),url("../assets/market/order-tracking-hero.png") center/cover no-repeat;position:relative;color:#fff;display:grid;grid-template-columns:minmax(0,820px) 1fr;gap:28px;align-items:center;padding:34px 42px;overflow:hidden}.hero h1{font-size:clamp(2.2rem,4vw,4rem);line-height:1;margin:0 0 10px;text-shadow:0 2px 20px rgba(0,0,0,.35)}.hero p{font-size:1.1rem;font-weight:750;margin:0 0 18px;color:#eefbea;text-shadow:0 2px 14px rgba(0,0,0,.35)}.track-card{position:relative;z-index:1;background:rgba(255,255,255,.96);color:var(--ink);border-radius:10px;padding:22px;box-shadow:var(--shadow);max-width:820px}.track-form{display:grid;grid-template-columns:1fr 1fr 220px;gap:16px;align-items:end}.field label{display:block;font-weight:900;margin-bottom:8px}.field input{width:100%;border:1px solid #d7ded2;border-radius:8px;padding:14px 15px;font:inherit}.btn{border:0;border-radius:8px;background:var(--green);color:#fff;padding:14px 18px;font-weight:950;display:inline-flex;gap:9px;align-items:center;justify-content:center;cursor:pointer}.secure{display:flex;gap:8px;align-items:center;color:#355a37;font-size:.9rem;font-weight:700;margin-top:14px}.hero-art{display:none}
    .wrap{padding:24px 36px 42px;max-width:1540px;margin:0 auto}.grid{display:grid;grid-template-columns:minmax(0,1fr) 400px;gap:24px;align-items:start}.panel{background:#fff;border:1px solid var(--line);border-radius:14px;padding:22px;box-shadow:var(--shadow)}.notice{border-radius:12px;padding:13px 16px;margin:0 0 14px;font-weight:900}.notice.ok{background:#e8f6ec;color:var(--green);border:1px solid #b9e3c3}.notice.err{background:#fff1f2;color:#b42318;border:1px solid #fecdd3}.summary-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px}.back{color:var(--green);font-weight:900}.badge{display:inline-flex;align-items:center;gap:8px;border-radius:999px;padding:7px 13px;background:#e8f6ec;color:var(--green);font-weight:950}.badge.warn{background:#fff7d7;color:#8a6100}.summary-meta{color:var(--muted);font-weight:700;border-bottom:1px solid var(--line);padding-bottom:14px}.facts{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:14px;margin:18px 0}.fact{border-right:1px solid var(--line);min-height:52px}.fact:last-child{border-right:0}.fact small{display:block;color:var(--muted);font-weight:800;margin-bottom:5px}.fact strong{color:var(--green);font-size:1.05rem}.items{border:1px solid var(--line);border-radius:12px;padding:14px}.items-title{display:flex;justify-content:space-between;gap:10px;align-items:center}.item{display:grid;grid-template-columns:118px 1fr auto;gap:16px;align-items:center;border-bottom:1px solid var(--line);padding:12px 0}.item:last-child{border-bottom:0}.item img{width:118px;height:82px;object-fit:cover;border-radius:10px}.item h3{margin:0 0 5px;font-size:1rem}.seller{display:flex;gap:8px;flex-wrap:wrap;color:var(--green);font-weight:800;font-size:.84rem}.price{text-align:right;font-weight:950}.more-items{margin-top:8px}.more-items summary{cursor:pointer;color:var(--green);font-weight:950;text-align:center;padding:12px;border-top:1px solid var(--line)}.mini-item{display:flex;justify-content:space-between;gap:12px;border-top:1px solid var(--line);padding:10px 0;font-weight:800}.commerce{margin-top:18px;display:grid;grid-template-columns:minmax(0,1.35fr) minmax(260px,.65fr);gap:16px;align-items:start}.commerce-head{display:flex;justify-content:space-between;gap:14px;align-items:flex-start;margin-bottom:12px}.commerce-head h3{margin:0;color:#0d3d1e}.sponsored-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.sponsored-card{display:grid;grid-template-columns:88px 1fr;gap:11px;border:1px solid var(--line);border-radius:12px;padding:10px;background:#fff;transition:.18s ease}.sponsored-card:hover{transform:translateY(-2px);box-shadow:0 12px 26px rgba(16,24,40,.08)}.sponsored-card img{width:88px;height:72px;border-radius:10px;object-fit:cover}.sponsored-card h4{margin:0 0 4px;font-size:.92rem;line-height:1.25}.sponsored-card small{color:var(--muted);font-weight:800}.sponsored-card strong{display:block;color:var(--green);margin-top:6px}.seller-spotlight{background:linear-gradient(135deg,#fffdf3,#eef8ef)}.seller-row{display:flex;align-items:center;justify-content:space-between;gap:10px;border-top:1px solid var(--line);padding:11px 0}.seller-row:first-of-type{border-top:0}.seller-avatar{width:42px;height:42px;border-radius:12px;background:linear-gradient(135deg,#0b6b33,#1f9d8a);color:#fff;display:grid;place-items:center;font-weight:950;flex:0 0 auto}.seller-row strong{font-size:.92rem}.order-info{margin-top:12px;border:1px solid rgba(11,107,51,.16);border-radius:12px;background:#fff;overflow:hidden}.order-info summary{cursor:pointer;list-style:none;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:13px 14px;color:#0d3d1e;font-weight:950}.order-info summary::-webkit-details-marker{display:none}.order-info summary::after{content:"+";width:24px;height:24px;border-radius:50%;background:#e8f6ec;color:var(--green);display:grid;place-items:center}.order-info[open] summary::after{content:"-"}.order-info-body{padding:0 14px 14px;color:#334155;line-height:1.5}.order-info ul{margin:8px 0 0;padding-left:18px}.order-info li{margin:5px 0}.timeline{position:relative;padding-left:36px}.timeline::before{content:"";position:absolute;left:14px;top:10px;bottom:12px;width:4px;background:#d8eadb;border-radius:99px}.step{position:relative;display:grid;grid-template-columns:42px 1fr;gap:12px;margin:0 0 18px}.dot{position:absolute;left:-32px;top:8px;width:18px;height:18px;border-radius:50%;background:#cbd5c8;border:4px solid #fff;box-shadow:0 0 0 2px #cbd5c8}.step.done .dot{background:var(--green);box-shadow:0 0 0 2px var(--green)}.ic{width:42px;height:42px;border-radius:50%;background:var(--mint);display:grid;place-items:center;color:var(--green)}.step strong{display:block;margin-bottom:3px}.step p{margin:0;color:var(--muted);font-size:.9rem;line-height:1.45}.side{display:grid;gap:16px}.contact-row{display:flex;gap:12px;align-items:center;margin:12px 0;color:#334155}.paybox{background:linear-gradient(135deg,#fff7d7,#eef8ef);border-color:#ead99a}.pay-actions{display:grid;grid-template-columns:1fr 1fr;gap:9px}.address{background:linear-gradient(135deg,#fff,#faf8ef)}.proof-actions{display:grid;grid-template-columns:1fr 1fr;gap:9px;margin-top:10px}.why{display:grid;grid-template-columns:140px repeat(4,1fr);gap:18px;align-items:center;margin-top:24px}.why-img{height:132px;border-radius:12px;background:url("../assets/market/marketplace-trust-seedlings.png") center/cover no-repeat}.why h2{margin:0}.why-item{display:flex;gap:12px;align-items:flex-start}.why-ic{width:48px;height:48px;border-radius:50%;background:#e8f6ec;display:grid;place-items:center;color:var(--green);font-size:1.2rem;flex:0 0 auto}.empty{padding:24px;border:1px dashed var(--line);border-radius:12px;color:var(--muted);font-weight:800;background:#fff}
    @media(max-width:1280px){.top{height:auto;grid-template-columns:1fr;gap:14px;padding:14px 18px}.nav{overflow:auto}.search{width:100%}.hero,.grid,.commerce{grid-template-columns:1fr}.hero-art{display:none}.facts{grid-template-columns:repeat(2,1fr)}.why{grid-template-columns:1fr 1fr}.track-form{grid-template-columns:1fr}.top-actions{justify-content:flex-start;overflow:auto}}@media(max-width:700px){.nav{overflow:auto;width:100%}.wrap{padding:16px}.facts,.why,.item,.sponsored-grid,.sponsored-card{grid-template-columns:1fr}.item img,.sponsored-card img{width:100%;height:180px}.hero{padding:24px 18px}.track-card{padding:16px}}
  </style>
</head>
<body>
  <header class="top">
    <a class="brand" href="index.php"><img src="<?= e($logo) ?>" alt="NATCODEV"><div><strong>NATCODEV</strong><span>MARKETPLACE</span><small>Empowering Coconut Communities</small></div></a>
    <nav class="nav"><a href="index.php">Marketplace</a><a href="index.php#categories">Categories</a><a href="stores.php">Sellers</a><a href="index.php?listing_type=service">Services</a><a href="../academy/index.php">Academy</a></nav>
    <form class="search" action="index.php"><input name="q" placeholder="Search products, services, or sellers..."><button><i class="fas fa-search"></i></button></form>
    <div class="top-actions"><a class="cart" href="cart.php"><i class="fas fa-shopping-cart"></i><?php if (market_cart_count() > 0): ?><b><?= market_cart_count() ?></b><?php endif; ?> Cart</a><a class="active" href="orders.php"><i class="fas fa-map-marker-alt"></i> Track Order</a><a class="acct" href="<?= $user ? '../buyer/index.php' : '../buyer/login.php' ?>"><span class="avatar"><?= e($initials) ?></span> <?= $user ? 'My Account' : 'Sign In' ?></a></div>
  </header>

  <section class="hero">
    <div>
      <h1>Track Your Marketplace Order</h1>
      <p>Enter your details below to get real-time updates on your order.</p>
      <div class="track-card">
        <form class="track-form" method="get">
          <div class="field"><label>Checkout Reference *</label><input name="checkout_ref" value="<?= e($checkoutRef) ?>" placeholder="e.g. MKT-CHK-260603-8F3K7L" required></div>
          <div class="field"><label>Phone Number *</label><input name="phone" value="<?= e($phone) ?>" placeholder="+234 803 123 4567" required></div>
          <button class="btn" type="submit"><i class="fas fa-search"></i> Track Order</button>
        </form>
        <div class="secure"><i class="fas fa-shield-alt"></i> Your information is secure and only used to track your order.</div>
      </div>
    </div>
    <div class="hero-art"><div class="seedlings"></div><div class="truck"></div></div>
  </section>

  <main class="wrap">
    <?php if ($notice): ?><div class="notice ok"><?= e($notice) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="notice err"><?= e($error) ?></div><?php endif; ?>
    <?php if ($orders): ?>
    <div class="grid">
      <section class="panel">
        <div class="summary-head"><a class="back" href="index.php"><i class="fas fa-arrow-left"></i> Back to Marketplace</a><span class="badge"><i class="fas fa-check-circle"></i> <?= e(marketplace_status_label($deliveryStatus === 'delivered' ? 'delivered' : $orderStatus)) ?></span></div>
        <h2>Order Summary</h2>
        <div class="summary-meta">Order placed on <?= e(date('M j, Y', strtotime((string) $primary['created_at']))) ?> &bull; <?= count($orders) ?> item(s) &bull; Total: <?= e(marketplace_money((float) $orderTotal)) ?></div>
        <div class="facts">
          <div class="fact"><small>Checkout Reference</small><strong><?= e($displayCheckoutRef) ?></strong></div>
          <div class="fact"><small>Tracking Reference</small><strong><?= e($trackingRef ?: 'Pending') ?></strong></div>
          <div class="fact"><small>Payment Status</small><span class="badge"><?= e(marketplace_status_label($paymentStatus)) ?></span></div>
          <div class="fact"><small>Payment Method</small><strong><?= e(marketplace_status_label((string) ($primary['payment_method'] ?? 'pending'))) ?></strong></div>
          <div class="fact"><small>Total Amount</small><strong><?= e(marketplace_money((float) $orderTotal)) ?></strong></div>
        </div>
        <div class="items">
          <div class="items-title">
            <strong>Items in this order</strong>
            <?php if (count($orders) > 3): ?><span class="badge"><?= count($orders) ?> total items</span><?php endif; ?>
          </div>
          <?php foreach (array_slice($orders, 0, 3) as $order): ?>
            <div class="item">
              <?php $imageItem = ['id' => (int) $order['listing_id'], 'image_path' => (string) ($order['image_path'] ?? '')]; ?>
              <img src="<?= e(market_listing_image_url($imageItem)) ?>" alt="<?= e((string) $order['listing_title']) ?>">
              <div><h3><?= e((string) $order['listing_title']) ?></h3><p style="margin:0 0 7px;color:var(--muted)"><?= e((string) ($order['listing_summary'] ?: 'Marketplace order item')) ?></p><div class="seller"><span><i class="fas fa-check-circle"></i> Sold by <?= e((string) $order['store_name']) ?></span><span class="badge">Verified Seller</span></div></div>
              <div class="price"><?= e(marketplace_money((float) $order['total_amount'])) ?><br><small>Qty: <?= e((string) $order['quantity']) ?></small></div>
            </div>
          <?php endforeach; ?>
          <?php if (count($orders) > 3): ?>
            <details class="more-items">
              <summary>View all <?= count($orders) ?> order items</summary>
              <?php foreach (array_slice($orders, 3) as $order): ?>
                <div class="mini-item"><span><?= e((string) $order['listing_title']) ?><br><small><?= e((string) $order['store_name']) ?> - Qty: <?= e((string) $order['quantity']) ?></small></span><strong><?= e(marketplace_money((float) $order['total_amount'])) ?></strong></div>
              <?php endforeach; ?>
            </details>
          <?php endif; ?>
        </div>
      </section>

      <aside class="side">
        <?php if ($paymentStatus !== 'paid'): ?>
        <section class="panel paybox">
          <h3><i class="fas fa-credit-card" style="color:var(--green)"></i> Complete Payment</h3>
          <p>This order is waiting for payment confirmation before seller settlement and fulfillment can complete.</p>
          <h2><?= e(marketplace_money((float) $orderTotal)) ?></h2>
          <div class="pay-actions">
            <?php if ($user): ?>
              <form method="post">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="pay_wallet">
                <button class="btn" type="submit" style="width:100%"><i class="fas fa-wallet"></i> Pay Wallet</button>
              </form>
              <a class="btn" style="background:#fff;color:var(--green);border:1px solid var(--green)" href="../dashboard/wallet.php"><i class="fas fa-plus-circle"></i> Fund Wallet</a>
            <?php else: ?>
              <?php if ((string) ($primary['payment_method'] ?? '') === 'monnify' && !empty($primary['payment_reference'])): ?>
                <a class="btn" href="checkout.php?verify_monnify=<?= urlencode((string) $primary['payment_reference']) ?>&checkout_ref=<?= urlencode($displayCheckoutRef) ?>&phone=<?= urlencode($buyerPhone) ?>"><i class="fas fa-rotate"></i> Check Payment</a>
              <?php else: ?>
                <a class="btn" href="../buyer/login.php"><i class="fas fa-user"></i> Sign In To Pay</a>
              <?php endif; ?>
              <a class="btn" style="background:#fff;color:var(--green);border:1px solid var(--green)" href="../buyer/register.php"><i class="fas fa-lock"></i> Create Buyer Account</a>
            <?php endif; ?>
          </div>
        </section>
        <?php endif; ?>
        <section class="panel address"><h3><i class="fas fa-map-marker-alt" style="color:var(--green)"></i> Delivery Address</h3><strong><?= e($buyerName) ?></strong><p><?= nl2br(e($deliveryAddress)) ?><br><?= e($buyerPhone) ?></p></section>
        <details class="order-info" open>
          <summary><span><i class="fas fa-store" style="color:var(--green)"></i> Message from Seller</span><span class="badge">Verified</span></summary>
          <div class="order-info-body">
            <p><?= e($sellerMessage) ?></p>
            <strong>- <?= e($sellerName) ?></strong>
          </div>
        </details>
        <details class="order-info">
          <summary><span><i class="fas fa-route" style="color:var(--green)"></i> Delivery Progress</span><span class="badge"><?= e(marketplace_status_label($deliveryStatus)) ?></span></summary>
          <div class="order-info-body">
            <div class="timeline">
              <?php foreach ([['created','Order Created','Your order has been created successfully.','fa-receipt'],['paid','Payment Confirmed','Payment confirmation or seller follow-up is recorded.','fa-credit-card'],['accepted','Seller Accepted','The seller accepted your order.','fa-user-check'],['packed','Packed','Your items are being prepared.','fa-box'],['transit','In Transit','Your order is on the way.','fa-truck'],['delivered','Delivered','Your order has been delivered.','fa-check']] as $step): ?>
                <div class="step <?= track_is_done($primary, $step[0]) ? 'done' : '' ?>"><span class="dot"></span><span class="ic"><i class="fas <?= e($step[3]) ?>"></i></span><p><strong><?= e($step[1]) ?></strong><?= e(track_date($primary)) ?><br><?= e($step[2]) ?></p></div>
              <?php endforeach; ?>
            </div>
            <div class="proof-actions">
              <a class="btn" style="background:#eef8ef;color:var(--green)" href="orders.php?checkout_ref=<?= urlencode($displayCheckoutRef) ?>&phone=<?= urlencode($buyerPhone) ?>&proof=txt"><i class="fas fa-download"></i> TXT Proof</a>
              <a class="btn" style="background:#fff;color:var(--green);border:1px solid var(--green)" href="orders.php?checkout_ref=<?= urlencode($displayCheckoutRef) ?>&phone=<?= urlencode($buyerPhone) ?>&proof=print" target="_blank"><i class="fas fa-print"></i> Print / PDF</a>
            </div>
          </div>
        </details>
      </aside>
    </div>

    <section class="commerce">
      <article class="panel">
        <div class="commerce-head">
          <div>
            <h3>Recommended for this order</h3>
          </div>
          <span class="badge warn"><i class="fas fa-bullhorn"></i> Sponsored-ready</span>
        </div>
        <?php if ($sponsoredListings): ?>
          <div class="sponsored-grid">
            <?php foreach ($sponsoredListings as $item): ?>
              <a class="sponsored-card" href="product.php?id=<?= (int) $item['id'] ?>">
                <img src="<?= e(market_listing_image_url($item)) ?>" alt="<?= e((string) $item['title']) ?>">
                <span>
                  <h4><?= e((string) $item['title']) ?></h4>
                  <small><?= e((string) ($item['category_name'] ?: marketplace_status_label((string) $item['listing_type']))) ?> by <?= e((string) $item['store_name']) ?></small>
                  <strong><?= e(marketplace_money((float) $item['price'])) ?> / <?= e((string) ($item['unit'] ?: $item['price_unit'] ?: 'unit')) ?></strong>
                </span>
              </a>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="empty">Featured products and paid placements will appear here as sellers promote their listings.</div>
        <?php endif; ?>
      </article>
      <aside class="panel seller-spotlight">
        <div class="commerce-head">
          <div>
            <h3>Featured Sellers</h3>
          </div>
          <a class="back" href="stores.php">View all</a>
        </div>
        <?php foreach ($featuredSellers as $seller): ?>
          <a class="seller-row" href="store.php?seller=<?= e((string) $seller['slug']) ?>">
            <span style="display:flex;align-items:center;gap:10px">
              <span class="seller-avatar"><?= e(market_initials((string) $seller['store_name'])) ?></span>
              <span><strong><?= e((string) $seller['store_name']) ?></strong><br><small><?= e(marketplace_status_label((string) $seller['seller_type'])) ?> &bull; <?= number_format((int) $seller['listing_count']) ?> listing(s)</small></span>
            </span>
            <i class="fas fa-chevron-right" style="color:var(--green)"></i>
          </a>
        <?php endforeach; ?>
        <?php if (!$featuredSellers): ?><div class="empty">Featured sellers will appear here.</div><?php endif; ?>
        <details class="order-info">
          <summary><span><i class="fas fa-shield-alt" style="color:var(--green)"></i> Buyer Protection</span></summary>
          <div class="order-info-body">
            <p>Your order is protected by NATCODEV Buyer Protection.</p>
            <ul><li>Safe payments</li><li>Verified sellers</li><li>Quality assurance</li></ul>
          </div>
        </details>
      </aside>
    </section>

    <div class="grid" style="margin-top:16px">
      <section class="panel why">
        <div class="why-img"></div><div><h2>Why shop on NATCODEV Marketplace?</h2></div>
        <div class="why-item"><span class="why-ic"><i class="fas fa-shield-alt"></i></span><div><strong>Verified Sellers</strong><br><small>All sellers are vetted for quality and reliability.</small></div></div>
        <div class="why-item"><span class="why-ic"><i class="fas fa-credit-card"></i></span><div><strong>Secure Payments</strong><br><small>Wallet and settlement records keep commerce traceable.</small></div></div>
        <div class="why-item"><span class="why-ic"><i class="fas fa-truck"></i></span><div><strong>Reliable Delivery</strong><br><small>Trackable delivery from checkout to completion.</small></div></div>
      </section>

      <aside class="side">
        <section class="panel"><h3><i class="fas fa-headset" style="color:var(--green)"></i> Need Help?</h3><p>Our support team is here to help you.</p><div class="contact-row"><i class="fas fa-phone"></i> 0800 123 4567</div><div class="contact-row"><i class="fab fa-whatsapp"></i> 0901 234 5678</div><div class="contact-row"><i class="fas fa-envelope"></i> support@natcodev.com</div><a class="btn" style="width:100%;background:#fff;color:var(--green);border:1px solid var(--green)" href="../dashboard/inbox.php"><i class="fas fa-comments"></i> Chat with Support</a></section>
      </aside>
    </div>
    <?php else: ?>
      <section class="panel">
        <h2><?= $lookupAttempted ? 'No order found' : 'Track a public marketplace order' ?></h2>
        <p class="summary-meta"><?= $lookupAttempted ? 'Check the checkout reference and phone number, then try again.' : 'Enter a checkout reference and phone number above. Signed-in users can also open their account to see all marketplace orders.' ?></p>
        <div class="empty">Order details, delivery progress, buyer protection, seller message, and address will appear here after a successful lookup.</div>
      </section>
    <?php endif; ?>
  </main>
</body>
</html>
