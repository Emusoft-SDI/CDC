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
$nigeriaStates = app_table_exists($pdo, 'nigeria_states') ? $pdo->query("SELECT id, state_name FROM nigeria_states ORDER BY state_name")->fetchAll() : [];

$form = [
    'buyer_name' => (string) ($user['name'] ?? ''),
    'buyer_email' => (string) ($user['email'] ?? ''),
    'buyer_phone' => (string) ($user['phone'] ?? ''),
    'delivery_address' => '',
    'delivery_state' => '',
    'delivery_lga' => '',
    'delivery_latitude' => '',
    'delivery_longitude' => '',
    'delivery_method' => 'Standard Delivery',
    'payment_method' => 'monnify',
    'create_account' => false,
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
    $checkoutToken = trim((string) ($_POST['checkout_token'] ?? ''));
    if ($checkoutToken !== '' && !empty($_SESSION['completed_checkouts'][$checkoutToken])) {
        $prev = $_SESSION['completed_checkouts'][$checkoutToken];
        redirect_to('orders.php?checkout_ref=' . rawurlencode((string) $prev['checkout_ref']) . '&phone=' . rawurlencode((string) $prev['phone']) . (!empty($prev['paid']) ? '&paid=1' : '&order_created=1'));
    }

    $form = [
        'buyer_name' => trim((string) ($_POST['buyer_name'] ?? '')),
        'buyer_email' => trim((string) ($_POST['buyer_email'] ?? '')),
        'buyer_phone' => trim((string) ($_POST['buyer_phone'] ?? '')),
        'delivery_address' => trim((string) ($_POST['delivery_address'] ?? '')),
        'delivery_state' => trim((string) ($_POST['delivery_state'] ?? '')),
        'delivery_lga' => trim((string) ($_POST['delivery_lga'] ?? '')),
        'delivery_latitude' => trim((string) ($_POST['delivery_latitude'] ?? '')),
        'delivery_longitude' => trim((string) ($_POST['delivery_longitude'] ?? '')),
        'delivery_method' => trim((string) ($_POST['delivery_method'] ?? 'Standard Delivery')),
        'payment_method' => (string) ($_POST['payment_method'] ?? 'monnify'),
        'create_account' => !empty($_POST['create_account']),
    ];

    if (!$rows) {
        $recent = $_SESSION['last_successful_checkout'] ?? null;
        if ($recent && (time() - (int) ($recent['time'] ?? 0)) < 120) {
            redirect_to('orders.php?checkout_ref=' . rawurlencode((string) $recent['checkout_ref']) . '&phone=' . rawurlencode((string) $recent['phone']) . (!empty($recent['paid']) ? '&paid=1' : '&order_created=1'));
        }
        $error = 'Your cart is empty.';
    } else {
        $buyerName = $form['buyer_name'];
        $buyerEmail = $form['buyer_email'];
        $buyerPhone = $form['buyer_phone'];
        $address = $form['delivery_address'];
        $state = $form['delivery_state'];
        $lga = $form['delivery_lga'];
        $paymentMethod = $form['payment_method'];
        $createAccount = !$user && (bool) $form['create_account'];
        $accountPassword = (string) ($_POST['account_password'] ?? '');
        $accountPasswordConfirm = (string) ($_POST['account_password_confirm'] ?? '');

        if ($buyerName === '' || $buyerPhone === '' || $address === '') {
            $error = 'Name, phone, and delivery address are required.';
        } elseif ($state === '') {
            $error = 'Please select a delivery State.';
        } elseif ($paymentMethod === 'wallet' && !$user) {
            $error = 'Please sign in to pay with your NATCODEV wallet.';
        } elseif ($paymentMethod === 'monnify' && ($buyerEmail === '' || !filter_var($buyerEmail, FILTER_VALIDATE_EMAIL))) {
            $error = 'A valid email address is required for Monnify direct payment.';
        } elseif ($createAccount && ($buyerEmail === '' || !filter_var($buyerEmail, FILTER_VALIDATE_EMAIL))) {
            $error = 'A valid email address is required to create a buyer account.';
        } elseif ($createAccount && strlen($accountPassword) < 6) {
            $error = 'Choose a password of at least 6 characters for your buyer account.';
        } elseif ($createAccount && $accountPassword !== $accountPasswordConfirm) {
            $error = 'Your buyer account passwords do not match.';
        } elseif (!in_array($paymentMethod, ['wallet', 'monnify', 'bank_transfer'], true)) {
            $error = 'Select a valid payment method.';
        } else {
            $checkoutRef = market_checkout_ref();
            $createdRef = $checkoutRef;
            $paid = false;
            $settlements = [];
            try {
                $buyer = [
                    'name' => $buyerName,
                    'email' => $buyerEmail,
                    'phone' => $buyerPhone,
                    'address' => $address,
                    'state' => $state,
                    'lga' => $lga,
                    'latitude' => $form['delivery_latitude'],
                    'longitude' => $form['delivery_longitude'],
                    'delivery_method' => $form['delivery_method'],
                ];
                $checkoutUser = $user;
                if ($createAccount) {
                    $checkoutUser = market_checkout_create_optional_buyer_account($pdo, $buyer, $accountPassword);
                    $_SESSION['marketplace_checkout_account_flash'] = [
                        'name' => (string) ($checkoutUser['name'] ?? $buyerName),
                        'email' => (string) ($checkoutUser['email'] ?? $buyerEmail),
                    ];
                }
                if ($paymentMethod === 'monnify') {
                    $checkout = market_initialize_monnify_checkout($pdo, $rows, $checkoutUser, $buyer);
                    if (!($checkout['success'] ?? false)) {
                        throw new RuntimeException((string) ($checkout['error'] ?? 'Unable to initialize Monnify payment.'));
                    }
                    // Keep cart intact until payment verification so double-clicks or cancellations do not wipe it
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

                $created = market_insert_checkout_orders($pdo, $rows, $checkoutUser, $buyer, [
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

                $checkoutRecord = [
                    'checkout_ref' => $checkoutRef,
                    'phone' => $buyerPhone,
                    'paid' => $paid,
                    'time' => time(),
                ];
                $_SESSION['last_successful_checkout'] = $checkoutRecord;
                if ($checkoutToken !== '') {
                    $_SESSION['completed_checkouts'][$checkoutToken] = $checkoutRecord;
                }

                if ($paid) {
                    redirect_to('orders.php?checkout_ref=' . rawurlencode($checkoutRef) . '&phone=' . rawurlencode($buyerPhone) . '&paid=1');
                } else {
                    redirect_to('orders.php?checkout_ref=' . rawurlencode($checkoutRef) . '&phone=' . rawurlencode($buyerPhone) . '&order_created=1');
                }
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
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<?php if ($message): ?><div class="mk-alert ok"><?= e($message) ?> <?php if ($createdRef): ?><strong><?= e($createdRef) ?></strong><?php endif; ?></div><?php endif; ?>
<?php if ($error): ?><div class="mk-alert err"><?= e($error) ?></div><?php endif; ?>
<style>
.co-hero{margin:-26px -26px 22px;padding:44px 32px;background:linear-gradient(90deg,rgba(255,255,255,.96),rgba(255,255,255,.82)),url("../assets/market/checkout-coconut-bg.png") center/cover no-repeat;border-bottom:1px solid var(--mk-line)}.co-hero h1{font-size:2.35rem;margin:0;color:#0b2414}.steps{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin:20px 0}.stepx{display:flex;align-items:center;gap:12px;font-weight:900}.stepx b{width:42px;height:42px;border-radius:50%;display:grid;place-items:center;background:#0b6b33;color:#fff}.stepx.muted b{background:#667085}.co-shell{display:grid;grid-template-columns:minmax(0,1fr) 420px;gap:24px}.co-card{border:1px solid var(--mk-line);border-radius:14px;padding:18px;margin-bottom:14px;background:#fff}.acct-card{border-style:dashed;background:#fbfdf9}.acct-toggle{display:flex;gap:12px;align-items:flex-start;font-weight:900;cursor:pointer}.acct-toggle input{width:auto;margin-top:4px}.acct-note{margin:8px 0 0;color:#667085;font-size:.92rem;line-height:1.45}.acct-fields{margin-top:14px}.pay-options{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}.pay-option{border:1px solid var(--mk-line);border-radius:12px;padding:16px}.pay-option.active{border-color:#0b6b33;background:#f4fbf2}.summary-item{display:grid;grid-template-columns:92px 1fr auto;gap:12px;align-items:center;border-bottom:1px solid var(--mk-line);padding:13px 0}.summary-item img{width:92px;height:70px;border-radius:10px;object-fit:cover}.settle{border:1px solid #bde4c5;background:#f0fbf2;border-radius:12px;padding:14px;margin:12px 0;color:#0b6b33}.co-assurance{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;background:#fff;border:1px solid var(--mk-line);border-radius:14px;padding:20px;margin-top:16px}.co-assurance div{display:flex;gap:12px;align-items:center;font-weight:900}.co-assurance i,.co-card h3 i{color:#0b6b33}@media(max-width:1100px){.co-shell,.steps,.pay-options,.co-assurance{grid-template-columns:1fr}}
.map-btn{display:inline-flex;align-items:center;gap:6px;background:#eef7f0;color:#0b6b33;border:1px solid #bde4c5;padding:6px 12px;border-radius:8px;font-size:.85rem;font-weight:700;cursor:pointer;transition:.2s}.map-btn:hover{background:#0b6b33;color:#fff}
</style>
<section class="co-hero">
  <h1><i class="fas fa-shield-alt" style="color:#0b6b33"></i> Secure Checkout</h1>
  <p>Complete your order safely and securely with verified Nigerian delivery.</p>
</section>
<section class="co-shell">
  <article class="mk-section">
    <div class="steps"><div class="stepx"><b>1</b> Cart</div><div class="stepx"><b>2</b> Delivery</div><div class="stepx muted"><b>3</b> Payment</div><div class="stepx muted"><b>4</b> Confirmation</div></div>
    <?php if ($rows): ?>
    <form method="post" class="mk-form-grid" id="checkoutForm">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="checkout_token" value="<?= e(bin2hex(random_bytes(16))) ?>">
      <input type="hidden" id="delivery_latitude" name="delivery_latitude" value="<?= e($form['delivery_latitude']) ?>">
      <input type="hidden" id="delivery_longitude" name="delivery_longitude" value="<?= e($form['delivery_longitude']) ?>">

      <section class="co-card wide">
        <h3><i class="fas fa-user"></i> Buyer Information</h3>
        <div class="mk-form-grid">
          <div class="mk-field"><label>Full Name *</label><input name="buyer_name" value="<?= e($form['buyer_name']) ?>" required></div>
          <div class="mk-field"><label>Email Address</label><input type="email" name="buyer_email" value="<?= e($form['buyer_email']) ?>"></div>
          <div class="mk-field"><label>Phone Number *</label><input name="buyer_phone" value="<?= e($form['buyer_phone']) ?>" required placeholder="08012345678"></div>
        </div>
      </section>

      <section class="co-card wide">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:12px">
          <h3 style="margin:0"><i class="fas fa-map-marker-alt"></i> Delivery & Location</h3>
          <div style="display:flex;gap:8px">
            <button type="button" class="map-btn" id="btnToggleMap"><i class="fas fa-map-marked-alt"></i> Pick on Map</button>
            <button type="button" class="map-btn" id="btnGps"><i class="fas fa-crosshairs"></i> Use GPS</button>
          </div>
        </div>

        <div id="checkoutMapWrap" style="display:none;margin-bottom:16px">
          <div style="background:#f8fafc;border:1px solid var(--mk-line);border-radius:12px;padding:12px">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
              <small style="color:#64748b"><i class="fas fa-info-circle"></i> Click anywhere on the map or drag the pin to set your exact farm or delivery destination.</small>
              <button type="button" id="btnCloseMap" style="background:none;border:none;color:#64748b;font-weight:bold;cursor:pointer"><i class="fas fa-times"></i> Close</button>
            </div>
            <div id="checkoutMap" style="height:280px;border-radius:8px;border:1px solid #cbd5e1"></div>
            <div id="pinStatus" style="font-size:0.85rem;color:#0b6b33;font-weight:700;margin-top:8px;display:none"></div>
          </div>
        </div>

        <div class="mk-form-grid">
          <div class="mk-field wide">
            <label>Street Address / Landmark *</label>
            <input id="delivery_address" name="delivery_address" value="<?= e($form['delivery_address']) ?>" required placeholder="House/Farm number, street or community, nearest landmark">
          </div>

          <div class="mk-field">
            <label for="delivery_state">Delivery State *</label>
            <select id="delivery_state" name="delivery_state" data-state-mode="name" data-lga-target="delivery_lga" required>
              <option value="">Select State</option>
              <?php foreach ($nigeriaStates as $st): ?>
                <option value="<?= e((string) $st['state_name']) ?>" data-state-id="<?= (int) $st['id'] ?>" <?= ($form['delivery_state'] === $st['state_name']) ? 'selected' : '' ?>><?= e((string) $st['state_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mk-field">
            <label for="delivery_lga">Local Government Area (LGA) *</label>
            <select id="delivery_lga" name="delivery_lga" data-placeholder="Select LGA" data-selected="<?= e($form['delivery_lga']) ?>" required>
              <option value="">Select State first</option>
              <?php if (!empty($form['delivery_lga'])): ?>
                <option value="<?= e($form['delivery_lga']) ?>" selected><?= e($form['delivery_lga']) ?></option>
              <?php endif; ?>
            </select>
          </div>

          <div class="mk-field">
            <label for="delivery_method">Delivery Method</label>
            <select id="delivery_method" name="delivery_method">
              <option value="Standard Delivery" <?= ($form['delivery_method'] === 'Standard Delivery') ? 'selected' : '' ?>>Standard Delivery (2-4 business days)</option>
              <option value="Express Delivery" <?= ($form['delivery_method'] === 'Express Delivery') ? 'selected' : '' ?>>Express Courier Delivery</option>
              <option value="Pickup Station" <?= ($form['delivery_method'] === 'Pickup Station') ? 'selected' : '' ?>>Direct Pickup / Farm Gate</option>
            </select>
          </div>
        </div>
      </section>

      <?php if (!$user): ?>
      <section class="co-card wide acct-card">
        <label class="acct-toggle">
          <input id="createBuyerAccount" type="checkbox" name="create_account" value="1" <?= !empty($form['create_account']) ? 'checked' : '' ?>>
          <span>
            <strong>Create a buyer account for me</strong><br>
            <small>Optional. Leave this off for a guest checkout. We will only create the account if you tick this box.</small>
          </span>
        </label>
        <div id="buyerAccountFields" class="acct-fields" hidden>
          <div class="mk-form-grid">
            <div class="mk-field"><label>Account Password *</label><input id="buyerAccountPassword" type="password" name="account_password" minlength="6" autocomplete="new-password" placeholder="Create a password"></div>
            <div class="mk-field"><label>Confirm Password *</label><input id="buyerAccountPasswordConfirm" type="password" name="account_password_confirm" minlength="6" autocomplete="new-password" placeholder="Confirm your password"></div>
          </div>
          <p class="acct-note">A verification email will be sent after you create the account. You can still complete checkout without creating one.</p>
        </div>
      </section>
      <?php endif; ?>

      <section class="co-card wide">
        <h3><i class="fas fa-lock"></i> Choose Payment Method</h3>
        <div class="pay-options">
          <label class="pay-option"><input type="radio" name="payment_method" value="wallet" <?= $form['payment_method'] === 'wallet' ? 'checked' : '' ?> <?= $user ? '' : 'disabled' ?>> <strong>NATCODEV Wallet</strong><br><small>Signed-in users only</small></label>
          <label class="pay-option active"><input type="radio" name="payment_method" value="monnify" <?= $form['payment_method'] === 'monnify' ? 'checked' : '' ?>> <strong>Monnify Direct Payment</strong><br><small>Card, bank transfer, USSD or mobile money</small></label>
          <label class="pay-option"><input type="radio" name="payment_method" value="bank_transfer" <?= $form['payment_method'] === 'bank_transfer' ? 'checked' : '' ?>> <strong>Manual Bank Transfer</strong><br><small>Create order and confirm with support</small></label>
        </div>
        <p class="mk-alert ok" style="margin-top:14px">Your payment is protected by NATCODEV Buyer Protection. Seller settlement is recorded after successful payment.</p>
      </section>

      <div class="wide">
        <button id="checkoutSubmitBtn" class="mk-btn" style="width:100%;font-size:1.1rem" type="submit">
          <i class="fas fa-lock"></i> Pay Now <?= e(marketplace_money((float) $grandTotal)) ?>
        </button>
      </div>
    </form>
    <?php else: ?><div class="mk-empty">Your cart is empty. <a href="index.php" style="color:#0b6b33;font-weight:bold;margin-left:8px">Continue Shopping</a></div><?php endif; ?>
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

<section class="co-assurance">
  <div><i class="fas fa-shield-alt"></i> Secure Payment</div>
  <div><i class="fas fa-wallet"></i> Seller Wallet Settlement</div>
  <div><i class="fas fa-truck"></i> Trackable Delivery</div>
  <div><i class="fas fa-user-shield"></i> Buyer Protection</div>
</section>

<script>
(function(){
  // Buyer account toggle
  var checkbox = document.getElementById('createBuyerAccount');
  var fields = document.getElementById('buyerAccountFields');
  if (checkbox && fields) {
    var sync = function() { fields.hidden = !checkbox.checked; };
    checkbox.addEventListener('change', sync);
    sync();
  }

  // Payment option styling
  var payOptions = document.querySelectorAll('.pay-option');
  payOptions.forEach(function(opt) {
    var radio = opt.querySelector('input[type="radio"]');
    if (!radio) return;
    radio.addEventListener('change', function() {
      payOptions.forEach(function(o) { o.classList.remove('active'); });
      if (radio.checked) opt.classList.add('active');
    });
  });

  // Double-Click and Multiple Submission Shield
  var form = document.getElementById('checkoutForm');
  var submitBtn = document.getElementById('checkoutSubmitBtn');
  var isSubmitting = false;
  if (form && submitBtn) {
    form.addEventListener('submit', function(e) {
      if (isSubmitting) {
        e.preventDefault();
        return false;
      }
      isSubmitting = true;
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing Secure Payment...';
      submitBtn.style.opacity = '0.75';
      submitBtn.style.cursor = 'not-allowed';
    });
  }

  // State and LGA auto-population
  var stateSelect = document.getElementById('delivery_state');
  var lgaSelect = document.getElementById('delivery_lga');
  async function populateLgas(stateName, selectedLga) {
    if (!lgaSelect || !stateName) return;
    lgaSelect.disabled = true;
    lgaSelect.innerHTML = '<option value="">Loading LGAs...</option>';
    try {
      var res = await fetch('../api/get-lgas-by-state.php?state=' + encodeURIComponent(stateName));
      var data = await res.json();
      var items = Array.isArray(data) ? data : (data.items || []);
      lgaSelect.innerHTML = '<option value="">Select LGA</option>';
      items.forEach(function(item) {
        var name = item.lga_name || item.name || item;
        var opt = document.createElement('option');
        opt.value = name;
        opt.textContent = name;
        if (selectedLga && (name.toLowerCase() === selectedLga.toLowerCase())) {
          opt.selected = true;
        }
        lgaSelect.appendChild(opt);
      });
    } catch(e) {
      lgaSelect.innerHTML = '<option value="">Select LGA</option>';
    } finally {
      lgaSelect.disabled = false;
    }
  }

  if (stateSelect) {
    stateSelect.addEventListener('change', function() {
      populateLgas(this.value, '');
    });
    if (stateSelect.value) {
      populateLgas(stateSelect.value, lgaSelect ? lgaSelect.getAttribute('data-selected') : '');
    }
  }

  // Leaflet Map & Location Picker
  var mapWrap = document.getElementById('checkoutMapWrap');
  var btnToggleMap = document.getElementById('btnToggleMap');
  var btnCloseMap = document.getElementById('btnCloseMap');
  var btnGps = document.getElementById('btnGps');
  var addressInput = document.getElementById('delivery_address');
  var latInput = document.getElementById('delivery_latitude');
  var lngInput = document.getElementById('delivery_longitude');
  var pinStatus = document.getElementById('pinStatus');
  var map = null;
  var marker = null;

  function initMap(lat, lng) {
    if (!window.L) return;
    lat = lat || 9.0820;
    lng = lng || 8.6753;
    var zoom = (lat === 9.0820 && lng === 8.6753) ? 6 : 14;

    if (!map) {
      map = L.map('checkoutMap').setView([lat, lng], zoom);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap'
      }).addTo(map);

      map.on('click', function(e) {
        setPin(e.latlng.lat, e.latlng.lng, true);
      });
    } else {
      map.setView([lat, lng], zoom);
      setTimeout(function() { map.invalidateSize(); }, 200);
    }

    if (lat !== 9.0820 || lng !== 8.6753) {
      setPin(lat, lng, false);
    }
  }

  function setPin(lat, lng, doReverseGeocode) {
    if (!marker) {
      marker = L.marker([lat, lng], { draggable: true }).addTo(map);
      marker.on('dragend', function() {
        var pos = marker.getLatLng();
        setPin(pos.lat, pos.lng, true);
      });
    } else {
      marker.setLatLng([lat, lng]);
    }

    if (latInput) latInput.value = lat.toFixed(7);
    if (lngInput) lngInput.value = lng.toFixed(7);
    if (pinStatus) {
      pinStatus.style.display = 'block';
      pinStatus.innerHTML = '<i class="fas fa-check-circle"></i> Destination pinned: ' + lat.toFixed(5) + ', ' + lng.toFixed(5);
    }

    if (doReverseGeocode) {
      reverseGeocode(lat, lng);
    }
  }

  async function reverseGeocode(lat, lng) {
    try {
      var url = 'https://nominatim.openstreetmap.org/reverse?format=json&lat=' + lat + '&lon=' + lng + '&zoom=18&addressdetails=1';
      var res = await fetch(url, { headers: { 'Accept': 'application/json' } });
      if (!res.ok) return;
      var data = await res.json();
      var addr = data.address || {};

      var street = addr.road || addr.suburb || addr.neighbourhood || addr.amenity || '';
      var city = addr.city || addr.town || addr.village || '';
      var lga = addr.county || addr.city_district || addr.state_district || '';
      var state = addr.state || '';

      if (street || city) {
        var full = [street, city].filter(Boolean).join(', ');
        if (addressInput && !addressInput.value) {
          addressInput.value = full;
        }
      }

      // Auto-match state
      if (state && stateSelect) {
        var cleanState = state.replace(/State$/i, '').trim().toLowerCase();
        for (var i = 0; i < stateSelect.options.length; i++) {
          var optVal = stateSelect.options[i].value.replace(/State$/i, '').trim().toLowerCase();
          if (optVal && (optVal === cleanState || cleanState.indexOf(optVal) !== -1 || optVal.indexOf(cleanState) !== -1)) {
            stateSelect.selectedIndex = i;
            populateLgas(stateSelect.options[i].value, lga);
            break;
          }
        }
      }
    } catch(err) {}
  }

  if (btnToggleMap && mapWrap) {
    btnToggleMap.addEventListener('click', function() {
      var isHidden = mapWrap.style.display === 'none';
      mapWrap.style.display = isHidden ? 'block' : 'none';
      if (isHidden) {
        var lat = parseFloat(latInput ? latInput.value : 0) || 9.0820;
        var lng = parseFloat(lngInput ? lngInput.value : 0) || 8.6753;
        initMap(lat, lng);
      }
    });
  }

  if (btnCloseMap && mapWrap) {
    btnCloseMap.addEventListener('click', function() {
      mapWrap.style.display = 'none';
    });
  }

  if (btnGps) {
    btnGps.addEventListener('click', function() {
      if (!navigator.geolocation) {
        alert('Geolocation is not supported by your browser.');
        return;
      }
      btnGps.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Locating...';
      navigator.geolocation.getCurrentPosition(function(pos) {
        btnGps.innerHTML = '<i class="fas fa-check"></i> Located';
        if (mapWrap) mapWrap.style.display = 'block';
        initMap(pos.coords.latitude, pos.coords.longitude);
        setPin(pos.coords.latitude, pos.coords.longitude, true);
        setTimeout(function() { btnGps.innerHTML = '<i class="fas fa-crosshairs"></i> Use GPS'; }, 3000);
      }, function(err) {
        btnGps.innerHTML = '<i class="fas fa-crosshairs"></i> Use GPS';
        alert('Could not determine current location. Please select on the map or enter your address.');
      }, { enableHighAccuracy: true, timeout: 8000 });
    });
  }
})();
</script>
<?php market_footer(); ?>

