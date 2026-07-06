<?php
declare(strict_types=1);

require_once __DIR__ . '/_market.php';

$pdo = market_boot();
$user = market_user($pdo);
$rows = market_cart_rows($pdo);
$totals = market_checkout_totals($rows);
$total = (float) $totals['subtotal'];
$grandTotal = (float) $totals['total'];
$message = '';
$error = '';
$createdRef = '';
$form = [
    'buyer_name' => (string) ($user['name'] ?? ''),
    'buyer_email' => (string) ($user['email'] ?? ''),
    'buyer_phone' => (string) ($user['phone'] ?? ''),
    'delivery_address' => '',
    'payment_method' => 'monnify',
];

if (isset($_GET['verify_monnify'])) {
    if (!app_check_rate_limit('checkout_verify', 10, 600)) {
        $error = 'Too many verification attempts. Please wait 10 minutes.';
    } else {
        $result = market_verify_monnify_checkout($pdo, (string) $_GET['verify_monnify']);
        $checkoutRef = (string) ($result['checkout_ref'] ?? ($_GET['checkout_ref'] ?? ''));
        $phone = (string) ($result['phone'] ?? ($_GET['phone'] ?? ''));
        if (($result['success'] ?? false) && (($result['paid'] ?? false) || (string) ($result['status'] ?? '') === 'completed')) {
            market_cart_clear();
            redirect_to('orders.php?checkout_ref=' . rawurlencode($checkoutRef) . '&phone=' . rawurlencode($phone) . '&paid=1');
        }
        if (($result['success'] ?? false) && (string) ($result['status'] ?? '') !== 'completed') {
            redirect_to('orders.php?checkout_ref=' . rawurlencode($checkoutRef) . '&phone=' . rawurlencode($phone) . '&payment_pending=1');
        }
        $error = (string) ($result['error'] ?? 'Unable to verify marketplace payment.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['_csrf'] ?? null)) {
    $form = [
        'buyer_name' => trim((string) ($_POST['buyer_name'] ?? '')),
        'buyer_email' => trim((string) ($_POST['buyer_email'] ?? '')),
        'buyer_phone' => trim((string) ($_POST['buyer_phone'] ?? '')),
        'delivery_address' => trim((string) ($_POST['delivery_address'] ?? '')),
        'payment_method' => (string) ($_POST['payment_method'] ?? 'monnify'),
    ];
    if (!$rows) {
        $error = 'Your cart is empty.';
    } else {
        $buyerName = $form['buyer_name'];
        $buyerEmail = $form['buyer_email'];
        $buyerPhone = $form['buyer_phone'];
        $address = $form['delivery_address'];
        $paymentMethod = $form['payment_method'];
        if ($buyerName === '' || $buyerPhone === '' || $address === '') {
            $error = 'Name, phone, and delivery address are required.';
        } elseif ($paymentMethod === 'monnify' && ($buyerEmail === '' || !filter_var($buyerEmail, FILTER_VALIDATE_EMAIL))) {
            $error = 'A valid email address is required for Monnify direct payment.';
        } elseif (!in_array($paymentMethod, ['wallet', 'monnify', 'bank_transfer'], true)) {
            $error = 'Select a valid payment method.';
        } else {
            $checkoutRef = market_checkout_ref();
            $createdRef = $checkoutRef;
            $paid = false;
            $settlements = [];
            try {
                $buyer = ['name' => $buyerName, 'email' => $buyerEmail, 'phone' => $buyerPhone, 'address' => $address];
                if ($paymentMethod === 'monnify') {
                    $checkout = market_initialize_monnify_checkout($pdo, $rows, $user, $buyer);
                    if (!($checkout['success'] ?? false)) {
                        throw new RuntimeException((string) ($checkout['error'] ?? 'Unable to initialize Monnify payment.'));
                    }
                    market_cart_clear();
                    redirect_to((string) $checkout['payment_url']);
                }

                $pdo->beginTransaction();
                if ($paymentMethod === 'wallet') {
                    if (!$user) {
                        throw new RuntimeException('Sign in to pay with wallet.');
                    }
                    wallet_ensure_schema($pdo);
                    $wallet = wallet_get_or_create($pdo, (int) $user['id']);
                    $lock = $pdo->prepare("SELECT * FROM wallets WHERE id = ? FOR UPDATE");
                    $lock->execute([(int) $wallet['id']]);
                    $wallet = $lock->fetch();
                    if ((float) $wallet['balance'] < $grandTotal) {
                        throw new RuntimeException('Insufficient wallet balance. Fund your wallet or use Monnify direct payment.');
                    }
                    $before = (float) $wallet['balance'];
                    $after = $before - $grandTotal;
                    $pdo->prepare("UPDATE wallets SET balance = ? WHERE id = ?")->execute([$after, (int) $wallet['id']]);
                    $pdo->prepare("
                        INSERT INTO wallet_transactions
                            (wallet_id, user_id, amount, type, direction, description, reference, provider, status, balance_before, balance_after, completed_at)
                        VALUES (?, ?, ?, 'debit', 'outflow', ?, ?, 'marketplace', 'completed', ?, ?, NOW())
                    ")->execute([(int) $wallet['id'], (int) $user['id'], $grandTotal, 'Marketplace checkout ' . $checkoutRef, $checkoutRef, $before, $after]);
                    $paid = true;
                }

                $created = market_insert_checkout_orders($pdo, $rows, $user, $buyer, [
                    'checkout_ref' => $checkoutRef,
                    'method' => $paymentMethod,
                    'reference' => $paymentMethod === 'wallet' ? $checkoutRef : '',
                    'paid' => $paid,
                ]);
                $settlements = $created['settlements'];
                $pdo->commit();
                foreach ($settlements as $settlement) {
                    market_settle_seller_wallet($pdo, (int) $settlement['seller_user_id'], (float) $settlement['amount'], (string) $settlement['reference'], (string) $settlement['description']);
                }
                market_cart_clear();
                $message = $paid
                    ? 'Payment completed. Seller wallets have been credited and delivery tracking has started.'
                    : 'Order created for bank transfer. Use your checkout reference and phone number to track payment and delivery status.';
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = $e->getMessage();
            }
        }
    }
}

market_header('Checkout', 'marketplace', $pdo);
?>
<?php if ($message): ?><div class="mk-alert ok"><?= e($message) ?> <?php if ($createdRef): ?><strong><?= e($createdRef) ?></strong><?php endif; ?></div><?php endif; ?>
<?php if ($error): ?><div class="mk-alert err"><?= e($error) ?></div><?php endif; ?>
<style>
.co-hero{margin:-26px -26px 22px;padding:44px 32px;background:linear-gradient(90deg,rgba(255,255,255,.96),rgba(255,255,255,.82)),url("../assets/market/checkout-coconut-bg.png") center/cover no-repeat;border-bottom:1px solid var(--mk-line)}.co-hero h1{font-size:2.35rem;margin:0;color:#0b2414}.steps{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin:20px 0}.stepx{display:flex;align-items:center;gap:12px;font-weight:900}.stepx b{width:42px;height:42px;border-radius:50%;display:grid;place-items:center;background:#0b6b33;color:#fff}.stepx.muted b{background:#667085}.co-shell{display:grid;grid-template-columns:minmax(0,1fr) 420px;gap:24px}.co-card{border:1px solid var(--mk-line);border-radius:14px;padding:18px;margin-bottom:14px;background:#fff}.pay-options{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}.pay-option{border:1px solid var(--mk-line);border-radius:12px;padding:16px}.pay-option.active{border-color:#0b6b33;background:#f4fbf2}.summary-item{display:grid;grid-template-columns:92px 1fr auto;gap:12px;align-items:center;border-bottom:1px solid var(--mk-line);padding:13px 0}.summary-item img{width:92px;height:70px;border-radius:10px;object-fit:cover}.settle{border:1px solid #bde4c5;background:#f0fbf2;border-radius:12px;padding:14px;margin:12px 0;color:#0b6b33}.co-assurance{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;background:#fff;border:1px solid var(--mk-line);border-radius:14px;padding:20px;margin-top:16px}.co-assurance div{display:flex;gap:12px;align-items:center;font-weight:900}.co-assurance i,.co-card h3 i{color:#0b6b33}@media(max-width:1100px){.co-shell,.steps,.pay-options,.co-assurance{grid-template-columns:1fr}}
</style>
<section class="co-hero">
  <h1><i class="fas fa-shield-alt" style="color:#0b6b33"></i> Secure Checkout</h1>
  <p>Complete your order safely and securely.</p>
</section>
<section class="co-shell">
  <article class="mk-section">
    <div class="steps"><div class="stepx"><b>1</b> Cart</div><div class="stepx"><b>2</b> Delivery</div><div class="stepx muted"><b>3</b> Payment</div><div class="stepx muted"><b>4</b> Confirmation</div></div>
    <?php if ($rows): ?>
    <form method="post" class="mk-form-grid">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <section class="co-card wide"><h3><i class="fas fa-user"></i> Buyer Information</h3><div class="mk-form-grid"><div class="mk-field"><label>Full Name *</label><input name="buyer_name" value="<?= e($form['buyer_name']) ?>" required></div><div class="mk-field"><label>Email Address</label><input type="email" name="buyer_email" value="<?= e($form['buyer_email']) ?>"></div><div class="mk-field"><label>Phone Number *</label><input name="buyer_phone" value="<?= e($form['buyer_phone']) ?>" required></div></div></section>
      <section class="co-card wide"><h3><i class="fas fa-map-marker-alt"></i> Delivery Address</h3><div class="mk-form-grid"><div class="mk-field wide"><label>Address *</label><input name="delivery_address" value="<?= e($form['delivery_address']) ?>" required placeholder="State, LGA, town/community, address, nearest landmark"></div><div class="mk-field"><label>State</label><input name="delivery_state" placeholder="Lagos State"></div><div class="mk-field"><label>LGA</label><input name="delivery_lga" placeholder="Ikeja"></div><div class="mk-field"><label>Delivery Method</label><select name="delivery_method"><option>Standard Delivery (2-4 days)</option><option>Express Delivery</option></select></div></div></section>
      <section class="co-card wide"><h3><i class="fas fa-lock"></i> Choose Payment Method</h3><div class="pay-options"><label class="pay-option"><input type="radio" name="payment_method" value="wallet" <?= $form['payment_method'] === 'wallet' ? 'checked' : '' ?> <?= $user ? '' : 'disabled' ?>> <strong>NATCODEV Wallet</strong><br><small>Signed-in users only</small></label><label class="pay-option active"><input type="radio" name="payment_method" value="monnify" <?= $form['payment_method'] === 'monnify' ? 'checked' : '' ?>> <strong>Monnify Direct Payment</strong><br><small>Card, bank transfer, USSD or mobile money</small></label><label class="pay-option"><input type="radio" name="payment_method" value="bank_transfer" <?= $form['payment_method'] === 'bank_transfer' ? 'checked' : '' ?>> <strong>Manual Bank Transfer</strong><br><small>Create order and confirm with support</small></label></div><p class="mk-alert ok" style="margin-top:14px">Your payment is protected by NATCODEV Buyer Protection. Seller settlement is recorded after successful payment.</p></section>
      <div class="wide"><button class="mk-btn" style="width:100%;font-size:1.1rem" type="submit"><i class="fas fa-lock"></i> Pay Now <?= e(marketplace_money((float) $grandTotal)) ?></button></div>
    </form>
    <?php else: ?><div class="mk-empty">Your cart is empty.</div><?php endif; ?>
  </article>
  <aside class="mk-section">
    <div class="mk-section-head"><div><h2>Order Summary</h2></div><span><?= number_format(count($rows)) ?> items</span></div>
    <?php foreach ($rows as $row): ?>
      <div class="summary-item">
        <img src="<?= e(market_listing_image_url($row)) ?>" alt="<?= e((string) $row['title']) ?>">
        <div><strong><?= e((string) $row['title']) ?></strong><br><small>Qty: <?= (int) $row['cart_quantity'] ?> • <?= e((string) $row['store_name']) ?></small></div>
        <strong><?= e(marketplace_money((float) $row['cart_total'])) ?></strong>
      </div>
    <?php endforeach; ?>
    <div class="settle"><i class="fas fa-shield-alt"></i> Seller Settlement<br><small>Payments are recorded and settled to sellers' wallets after successful checkout.</small></div>
    <p>Subtotal <strong style="float:right"><?= e(marketplace_money((float) $total)) ?></strong></p>
    <p>Delivery Fee <strong style="float:right"><?= e(marketplace_money((float) $totals['delivery_fee'])) ?></strong></p>
    <p>Service Fee <strong style="float:right"><?= e(marketplace_money((float) $totals['service_fee'])) ?></strong></p>
    <hr>
    <h2>Order Total <span style="float:right;color:#0b6b33"><?= e(marketplace_money((float) $grandTotal)) ?></span></h2>
  </aside>
</section>
<section class="co-assurance"><div><i class="fas fa-shield-alt"></i> Secure Payment</div><div><i class="fas fa-wallet"></i> Seller Wallet Settlement</div><div><i class="fas fa-truck"></i> Trackable Delivery</div><div><i class="fas fa-user-shield"></i> Buyer Protection</div></section>
<?php market_footer(); ?>
