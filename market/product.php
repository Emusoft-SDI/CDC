<?php
declare(strict_types=1);

require_once __DIR__ . '/_market.php';

$pdo = market_boot();
$user = market_user($pdo);
$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare("
    SELECT l.*, c.name category_name, s.store_name, s.slug seller_slug, s.seller_type, s.verification_status, s.location_label seller_location,
           s.contact_person, s.phone seller_phone, s.email seller_email, s.whatsapp seller_whatsapp, s.coverage_area, s.fulfillment_options
    FROM marketplace_listings l
    JOIN marketplace_sellers s ON s.id = l.seller_id
    LEFT JOIN marketplace_categories c ON c.id = l.category_id
    WHERE l.id = ? AND l.approval_status = 'approved' AND s.approval_status = 'approved'
    LIMIT 1
");
$stmt->execute([$id]);
$item = $stmt->fetch();
$message = '';
$error = '';

if (!$item) {
    http_response_code(404);
    market_header('Listing Not Found', 'marketplace', $pdo);
    echo '<section class="mk-section"><div class="mk-empty">This listing is not available or has not been approved.</div></section>';
    market_footer();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!app_check_rate_limit('marketplace_inquiry', 10, 3600)) {
        $error = 'Too many inquiry attempts. Please try again in an hour.';
    } elseif (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        $action = (string) ($_POST['action'] ?? 'request_quote');
        if ($action === 'add_to_cart' || $action === 'buy_now') {
            market_cart_add((int) $item['id'], (int) ($_POST['quantity'] ?? 1));
            if ($action === 'buy_now') {
                redirect_to('checkout.php');
            }
            $message = 'Item added to cart.';
        } elseif ($action === 'add_bundle_to_cart') {
            $bundleItems = (array) ($_POST['bundle_items'] ?? []);
            if ($bundleItems) {
                foreach ($bundleItems as $listingId => $quantity) {
                    market_cart_add((int) $listingId, (int) $quantity);
                }
                $message = 'Bundle added to cart!';
                redirect_to('cart.php');
            } else {
                $error = 'No items in bundle to add to cart.';
            }
        } else {
        $buyerName = trim((string) ($_POST['buyer_name'] ?? ''));
        $buyerEmail = trim((string) ($_POST['buyer_email'] ?? ''));
        $buyerPhone = trim((string) ($_POST['buyer_phone'] ?? ''));
        if ($buyerName === '' || $buyerPhone === '') {
            $error = 'Your name and phone number are required so the seller can respond.';
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO marketplace_inquiries
                    (inquiry_ref, listing_id, seller_id, buyer_user_id, buyer_name, buyer_email, buyer_phone, quantity, preferred_date, message)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                marketplace_inquiry_ref(),
                (int) $item['id'],
                (int) $item['seller_id'],
                $user ? (int) $user['id'] : null,
                $buyerName,
                $buyerEmail,
                $buyerPhone,
                $_POST['quantity'] === '' ? null : max(1, (float) $_POST['quantity']),
                $_POST['preferred_date'] ?: null,
                trim((string) ($_POST['message'] ?? '')),
            ]);
            $message = 'Request sent. The seller will review it from the NATCODEV seller workspace.';
        }
        }
    }
}

$relatedStmt = $pdo->prepare("
    SELECT l.*, c.name category_name, s.store_name, s.slug seller_slug, s.seller_type, s.verification_status, s.location_label seller_location
    FROM marketplace_listings l
    JOIN marketplace_sellers s ON s.id = l.seller_id
    LEFT JOIN marketplace_categories c ON c.id = l.category_id
    WHERE l.id <> ? AND l.approval_status = 'approved' AND s.approval_status = 'approved'
      AND (l.category_id = ? OR l.seller_id = ?)
    ORDER BY l.is_featured DESC, l.created_at DESC
    LIMIT 4
");
$relatedStmt->execute([(int) $item['id'], (int) ($item['category_id'] ?? 0), (int) $item['seller_id']]);
$related = $relatedStmt->fetchAll();

market_header((string) $item['title'], 'marketplace', $pdo);
?>
<?php if ($message): ?><div class="mk-alert ok"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="mk-alert err"><?= e($error) ?></div><?php endif; ?>
<style>
.pd-breadcrumb{display:flex;gap:9px;align-items:center;color:#667085;font-weight:750;margin:0 0 16px}.pd-wrap{display:grid;grid-template-columns:minmax(0,1.2fr) minmax(420px,.8fr);gap:36px;align-items:start}.pd-gallery{position:relative}.pd-main-img{height:540px;border-radius:14px;overflow:hidden;border:1px solid var(--mk-line);box-shadow:var(--mk-shadow);background:#eef8ef}.pd-main-img img{width:100%;height:100%;object-fit:cover}.pd-stamp{position:absolute;left:18px;top:18px;background:rgba(7,100,45,.96);color:#fff;border-radius:12px;padding:14px 16px;font-weight:950;display:flex;gap:10px;align-items:center}.pd-reviewed{position:absolute;left:18px;bottom:112px;background:rgba(112,89,8,.9);color:#fff;border-radius:12px;padding:12px 14px;font-weight:900}.pd-thumbs{display:grid;grid-template-columns:repeat(6,1fr);gap:10px;margin-top:12px}.pd-thumbs img,.pd-more{height:92px;border-radius:10px;border:2px solid #dbe8d8;object-fit:cover;width:100%}.pd-thumbs img:first-child{border-color:#0b6b33}.pd-more{background:#0b3d1e;color:#fff;display:grid;place-items:center;font-weight:950}.pd-info h1{font-size:2rem;line-height:1.12;margin:10px 0}.pd-rating{display:flex;gap:12px;align-items:center;color:#667085;font-weight:800}.pd-rating i{color:#f0a000}.pd-price{font-size:2.2rem;color:#0b6b33;font-weight:950;margin:18px 0 4px}.pd-buy{border:1px solid var(--mk-line);border-radius:14px;padding:18px;margin:16px 0;background:#fff;box-shadow:var(--mk-shadow)}.pd-qty{display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:center}.qtybox{display:grid;grid-template-columns:44px 1fr 44px;border:1px solid var(--mk-line);border-radius:10px;overflow:hidden;height:48px}.qtybox button{border:0;background:#fff;font-weight:950;font-size:1.1rem}.qtybox input{text-align:center;border:0;font-weight:950}.pd-actions{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:14px}.pd-trust{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin:14px 0}.pd-trust div{border:1px solid var(--mk-line);border-radius:10px;padding:12px;background:#f8fcf7;font-weight:850}.pd-seller{display:flex;justify-content:space-between;gap:16px;align-items:center;border:1px solid var(--mk-line);border-radius:14px;padding:16px;background:#fff}.pd-tabs{display:grid;grid-template-columns:1.2fr .42fr .55fr .55fr;border:1px solid var(--mk-line);border-radius:12px;overflow:hidden;margin-top:22px;background:#fff}.pd-tab{padding:20px;border-left:1px solid var(--mk-line)}.pd-tab:first-child{border-left:0}.pd-tab h3{margin:0 0 12px;color:#0b6b33}.pd-tab li{margin:10px 0}.bundle{display:grid;grid-template-columns:1fr auto;gap:18px;align-items:center;background:#fff;border:1px solid var(--mk-line);border-radius:14px;padding:16px}.bundle-items{display:flex;gap:14px;align-items:center;overflow:auto}.bundle-item{display:flex;gap:10px;align-items:center;min-width:190px}.bundle-item img{width:76px;height:70px;border-radius:10px;object-fit:cover}.product-strip{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}.mini-product{border:1px solid var(--mk-line);border-radius:12px;background:#fff;overflow:hidden}.mini-product img{height:150px;width:100%;object-fit:cover}.mini-product div{padding:12px}.market-assurance{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;border:1px solid var(--mk-line);border-radius:14px;background:#fffdf5;padding:20px;margin-top:22px}.market-assurance div{display:flex;gap:12px;align-items:center;font-weight:900}.market-assurance i{color:#0b6b33;font-size:1.6rem}@media(max-width:1100px){.pd-wrap,.pd-tabs,.product-strip,.market-assurance{grid-template-columns:1fr}.pd-main-img{height:360px}.pd-actions,.pd-qty,.pd-trust{grid-template-columns:1fr}}
</style>
<div class="pd-breadcrumb">Home <i class="fas fa-chevron-right"></i> <?= e((string) ($item['category_name'] ?: 'Marketplace')) ?> <i class="fas fa-chevron-right"></i> <?= e((string) $item['title']) ?></div>
<section class="pd-wrap">
  <article class="pd-gallery">
    <div class="pd-main-img"><img src="<?= e(market_listing_image_url($item)) ?>" alt="<?= e((string) $item['title']) ?>"></div>
    <div class="pd-stamp"><i class="fas fa-shield-alt"></i> NATCODEV<br>VERIFIED</div>
    <div class="pd-reviewed"><i class="fas fa-medal"></i> NATCODEV REVIEWED<br><small>Quality • Authentic • Trusted</small></div>
    <div class="pd-thumbs">
      <img src="<?= e(market_listing_image_url($item)) ?>" alt="">
      <img src="../assets/market/dwarf-coconut-seedlings.png" alt="">
      <img src="../assets/market/marketplace-trust-seedlings.png" alt="">
      <img src="../assets/market/organic-compost.png" alt="">
      <img src="../assets/market/farm-tools-pruning.png" alt="">
      <div class="pd-more"><i class="fas fa-images"></i> +6</div>
    </div>
  </article>
  <aside class="pd-info">
    <div class="mk-badges"><span class="mk-badge gold">Best Seller</span><span class="mk-badge">Premium Quality</span></div>
    <h1><?= e((string) $item['title']) ?></h1>
    <p class="mk-meta"><?= e((string) ($item['summary'] ?: 'High yielding marketplace listing from a verified seller.')) ?></p>
    <div class="pd-rating"><span><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star-half-alt"></i></span> 4.8 (124 reviews) <span>256+ bought this month</span></div>
    <div class="pd-price"><?= e(marketplace_money((float) $item['price'])) ?> <small>/ <?= e((string) ($item['unit'] ?: $item['price_unit'] ?: 'unit')) ?></small></div>
    <p><span class="mk-badge"><i class="fas fa-check-circle"></i> In Stock</span> <?= e((string) ($item['quantity_available'] ?: '5,000+')) ?> <?= e((string) ($item['unit'] ?: 'available')) ?></p>
    <form method="post" class="pd-buy">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <div class="pd-qty">
        <label><strong>Quantity</strong><div class="qtybox"><button type="button" onclick="this.nextElementSibling.stepDown()">-</button><input type="number" min="1" name="quantity" value="<?= e((string) ($item['min_order_quantity'] ?: 10)) ?>"><button type="button" onclick="this.previousElementSibling.stepUp()">+</button></div></label>
        <div><strong>Total</strong><h2 style="color:#0b6b33;margin:6px 0"><?= e(marketplace_money((float) $item['price'] * (float) ($item['min_order_quantity'] ?: 10))) ?></h2></div>
      </div>
      <div class="pd-actions"><button class="mk-btn secondary" type="submit" name="action" value="add_to_cart"><i class="fas fa-cart-plus"></i> Add to Cart</button><button class="mk-btn" type="submit" name="action" value="buy_now"><i class="fas fa-bolt"></i> Buy Now</button></div>
    </form>
    <div class="pd-trust"><div><i class="fas fa-shield-alt"></i> Secure Payment</div><div><i class="fas fa-truck"></i> Delivery Tracking</div><div><i class="fas fa-check-square"></i> Quality Guarantee</div></div>
    <div class="pd-seller"><div><strong>Sold by<br><?= e((string) $item['store_name']) ?></strong> <span class="mk-badge">Verified Seller</span><br><small>4.9 (87) • 98% Positive Feedback</small></div><a class="mk-btn secondary" href="store.php?seller=<?= e((string) $item['seller_slug']) ?>">View Store</a></div>
  </aside>
</section>

<section class="mk-section" id="request">
  <details>
    <summary style="cursor:pointer;font-weight:950;color:#103d1b;font-size:1.1rem">Ask seller before buying</summary>
    <p class="mk-meta" style="margin:8px 0 14px">Use this only for bulk pricing, custom delivery, service scheduling, or clarification before checkout.</p>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="request_quote">
      <div class="mk-form-grid">
      <div class="mk-field"><label>Name *</label><input name="buyer_name" value="<?= e((string) ($user['name'] ?? '')) ?>" required></div>
      <div class="mk-field"><label>Email</label><input type="email" name="buyer_email" value="<?= e((string) ($user['email'] ?? '')) ?>"></div>
      <div class="mk-field"><label>Phone *</label><input name="buyer_phone" required></div>
      <div class="mk-field"><label>Quantity</label><input type="number" step="0.01" min="1" name="quantity" value="<?= e((string) ($item['min_order_quantity'] ?: 1)) ?>"></div>
      <div class="mk-field"><label>Preferred Date</label><input type="date" name="preferred_date"></div>
      <div class="mk-field wide"><label>Message</label><textarea name="message" placeholder="Tell the seller what you need, delivery location, timing, and any special instruction."></textarea></div>
      </div>
      <button class="mk-btn" type="submit">Send Request</button>
    </form>
  </details>
</section>

<section class="pd-tabs">
  <article class="pd-tab"><h3>Product Description</h3><p><?= nl2br(e((string) ($item['description'] ?: $item['summary'] ?: 'This NATCODEV marketplace listing is supplied by a verified seller and can be purchased through secure checkout with delivery tracking.'))) ?></p><ul><li>High quality verified marketplace listing</li><li>Seller reviewed for platform trust</li><li>Trackable checkout and delivery</li></ul></article>
  <article class="pd-tab"><h3>Specifications</h3><p><strong>Type</strong><br><?= e(marketplace_status_label((string) $item['listing_type'])) ?></p><p><strong>Unit</strong><br><?= e((string) ($item['unit'] ?: $item['price_unit'] ?: 'unit')) ?></p><p><strong>Availability</strong><br><?= e(marketplace_status_label((string) $item['availability_status'])) ?></p></article>
  <article class="pd-tab"><h3>Delivery & Coverage</h3><ul><li><?= e((string) ($item['location_label'] ?: $item['seller_location'] ?: 'Nigeria coverage')) ?></li><li><?= e((string) ($item['fulfillment_method'] ?: 'Seller confirms fulfillment after checkout')) ?></li><li>Nationwide delivery available on request</li></ul><a class="mk-btn secondary" href="orders.php">Track Orders</a></article>
  <article class="pd-tab"><h3>Seller Policies</h3><ul><li>7-day return policy for eligible items</li><li>Replacement guarantee where quality issues occur</li><li>Secure packaging and buyer support</li></ul><a class="mk-btn secondary" href="store.php?seller=<?= e((string) $item['seller_slug']) ?>">Store Policies</a></article>
</section>

<section class="mk-section">
  <div class="mk-section-head">
    <div><h2>Related Products</h2></div>
    <a class="back" href="index.php">View all</a>
  </div>
  <?php if ($related): ?><div class="product-strip"><?php foreach ($related as $rel): ?><article class="mini-product"><img src="<?= e(market_listing_image_url($rel)) ?>" alt="<?= e((string) $rel['title']) ?>"><div><strong><?= e((string) $rel['title']) ?></strong><br><small><i class="fas fa-star" style="color:#f0a000"></i> 4.7</small><p style="color:#0b6b33;font-weight:950"><?= e(marketplace_money((float) $rel['price'])) ?></p><a class="mk-btn secondary" style="width:100%" href="product.php?id=<?= (int) $rel['id'] ?>"><i class="fas fa-cart-plus"></i> Add to Cart</a></div></article><?php endforeach; ?></div><?php else: ?><div class="mk-empty">Related listings will appear as the marketplace grows.</div><?php endif; ?>
</section>

<?php if ($related): ?>
<section class="mk-section">
  <div class="mk-section-head"><div><h2>Frequently Bought Together</h2></div><a class="back" href="cart.php">View cart</a></div>
  <div class="bundle"><div class="bundle-items"><div class="bundle-item"><img src="<?= e(market_listing_image_url($item)) ?>" alt=""><strong><?= e((string) $item['title']) ?><br><?= e(marketplace_money((float) $item['price'])) ?></strong></div><?php foreach (array_slice($related, 0, 3) as $rel): ?><span>+</span><div class="bundle-item"><img src="<?= e(market_listing_image_url($rel)) ?>" alt=""><strong><?= e((string) $rel['title']) ?><br><?= e(marketplace_money((float) $rel['price'])) ?></strong></div><?php endforeach; ?></div><form method="post"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="add_bundle_to_cart"><input type="hidden" name="bundle_item_id[]" value="<?= (int) $item['id'] ?>"><input type="hidden" name="bundle_item_qty[]" value="1"><?php foreach (array_slice($related, 0, 3) as $rel): ?><input type="hidden" name="bundle_item_id[]" value="<?= (int) $rel['id'] ?>"><input type="hidden" name="bundle_item_qty[]" value="1"><?php endforeach; ?><button class="mk-btn" type="submit"><i class="fas fa-cart-plus"></i> Add All to Cart</button></form></div>
</section>
<?php endif; ?>

<section class="market-assurance"><div><i class="fas fa-award"></i> 100% Genuine Products</div><div><i class="fas fa-wallet"></i> Secure Payments</div><div><i class="fas fa-truck"></i> Fast & Reliable Delivery</div><div><i class="fas fa-hands-helping"></i> Support Farmers</div></section>
</section>
<?php market_footer(); ?>
