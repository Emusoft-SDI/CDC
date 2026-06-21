<?php
declare(strict_types=1);

require_once __DIR__ . '/_market.php';

$pdo = market_boot();
$user = market_user($pdo); // Get the current user
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['_csrf'] ?? null)) {
    $action = (string) ($_POST['action'] ?? '');
    $listingId = (int) ($_POST['listing_id'] ?? 0);
    if ($action === 'remove') {
        market_cart_remove($listingId);
        $message = 'Item removed from cart.';
    } elseif ($action === 'update') {
        market_cart_remove($listingId);
        market_cart_add($listingId, (int) ($_POST['quantity'] ?? 1));
        $message = 'Cart updated.';
    } elseif ($action === 'clear') {
        market_cart_clear();
        $message = 'Cart cleared.';
    } elseif ($action === 'save_for_later') {
        if (!$user) {
            redirect_to('../dashboard/login.php?next=' . rawurlencode('market/cart.php'));
        }
        if (market_cart_save_for_later($pdo, $user)) {
            $message = 'Cart saved for later!';
        } else {
            $message = 'No items to save or failed to save cart.';
        }
    }
}
$rows = market_cart_rows($pdo);
$total = array_sum(array_map(static fn($row) => (float) $row['cart_total'], $rows));

$featuredInspiration = [];
$suggestedCategories = [];
if (!$rows) {
    $featuredInspiration = market_listing_query($pdo, ['listing_type' => 'product'], 8);
    $suggestedCategories = array_slice(marketplace_categories($pdo), 0, 6);
}

market_header('Cart', 'marketplace', $pdo);
?>
<?php if ($message): ?><div class="mk-alert ok"><?= e($message) ?></div><?php endif; ?>
<style>
.cart-hero{margin:-26px -26px 22px;padding:46px 32px;background:linear-gradient(90deg,rgba(255,255,255,.94),rgba(255,255,255,.78)),url("../assets/market/checkout-coconut-bg.png") center/cover no-repeat;border-bottom:1px solid var(--mk-line);display:flex;justify-content:space-between;gap:20px;align-items:center}.cart-hero h1{font-size:2.4rem;margin:0;color:#0b2414}.cart-shell{display:grid;grid-template-columns:minmax(0,1fr) 380px;gap:24px}.seller-block{background:#fff;border:1px solid var(--mk-line);border-radius:14px;margin-bottom:18px;box-shadow:var(--mk-shadow);overflow:hidden}.seller-head{display:flex;justify-content:space-between;gap:16px;align-items:center;padding:16px 18px;border-bottom:1px solid var(--mk-line);font-weight:900}.cart-item{display:grid;grid-template-columns:28px 210px 1fr 120px 150px 120px 42px;gap:18px;align-items:center;padding:18px;border-bottom:1px solid var(--mk-line)}.cart-item:last-child{border-bottom:0}.cart-item img{width:210px;height:118px;border-radius:10px;object-fit:cover}.cart-check{width:22px;height:22px;accent-color:#0b6b33}.qty-mini{display:grid;grid-template-columns:36px 1fr 36px;border:1px solid var(--mk-line);border-radius:8px;overflow:hidden;height:42px}.qty-mini button{border:0;background:#fff;font-weight:950}.qty-mini input{border:0;text-align:center;font-weight:950}.cart-summary{position:sticky;top:112px}.summary-total{font-size:1.8rem;color:#0b6b33;font-weight:950}.coupon{border:1px solid #f3d391;background:#fffdf3;border-radius:12px;padding:14px;margin-top:14px}.protection li{display:flex;gap:10px;align-items:flex-start;margin:12px 0}.wallet-note{border:1px solid #f3d391;background:#fff8e6;border-radius:12px;padding:16px;margin-top:14px}.cart-assurance{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;background:#fff;border:1px solid var(--mk-line);border-radius:14px;padding:18px;margin-top:16px}.cart-assurance div{display:flex;gap:12px;align-items:center;font-weight:900}.cart-assurance i,.seller-head i,.protection i{color:#0b6b33}
.empty-hero{text-align:center;padding:60px 20px;background:#fff;border:1px solid var(--mk-line);border-radius:18px;margin-bottom:24px;box-shadow:var(--mk-shadow)}.empty-icon{font-size:5rem;color:#e2e8f0;margin-bottom:20px;display:inline-block;padding:30px;background:#f8fafc;border-radius:50%}.empty-hero h2{font-size:2rem;color:#1e293b;margin-bottom:10px}.empty-hero p{color:#64748b;font-size:1.1rem;max-width:500px;margin:0 auto 24px}.inspiration-section{margin-top:40px}.inspiration-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px}.inspiration-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px}.insp-card{background:#fff;border:1px solid var(--mk-line);border-radius:12px;padding:12px;transition:0.2s;text-align:center;display:block;color:inherit}.insp-card:hover{transform:translateY(-3px);box-shadow:var(--mk-shadow);text-decoration:none}.insp-card img{width:100%;height:120px;border-radius:8px;object-fit:cover;margin-bottom:10px}.insp-card h4{font-size:0.95rem;margin:0 0 5px;color:#1e293b}.cat-pills{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px}.cat-pill{padding:8px 16px;background:#fff;border:1px solid var(--mk-line);border-radius:999px;font-weight:800;font-size:0.9rem;color:#475569;transition:0.2s}.cat-pill:hover{background:#f0fdf4;color:#0b6b33;border-color:#bbf7d0;text-decoration:none}
@media(max-width:1200px){.cart-shell,.cart-assurance{grid-template-columns:1fr}.cart-summary{position:static}.cart-item{grid-template-columns:28px 140px 1fr}.cart-item img{width:140px;height:100px}.cart-item>*:nth-child(n+5){grid-column:auto}}@media(max-width:700px){.cart-item{grid-template-columns:1fr}.cart-item img{width:100%;height:190px}}
</style>
<section class="cart-hero">
  <div><h1><i class="fas fa-shopping-cart" style="color:#0b6b33"></i> Your Cart</h1><p>Review your items, check delivery, and proceed to secure checkout.</p></div>
  <div class="mk-badge"><i class="fas fa-shield-alt"></i> 100% Secure Checkout</div>
</section>
<section class="cart-shell">
  <div>
  <?php if ($rows): ?>
    <?php foreach ($rows as $row): ?>
      <article class="seller-block">
        <div class="seller-head"><span>Seller: <?= e((string) $row['store_name']) ?> <span class="mk-badge"><i class="fas fa-check-circle"></i> Verified Seller</span></span><a href="store.php?seller=<?= e((string) $row['seller_slug']) ?>">Add more from this seller <i class="fas fa-arrow-right"></i></a></div>
        <div class="cart-item">
          <input class="cart-check" type="checkbox" checked>
          <img src="<?= e(market_listing_image_url($row)) ?>" alt="<?= e((string) $row['title']) ?>">
          <div><h3><?= e((string) $row['title']) ?></h3><p class="mk-meta"><?= e((string) ($row['summary'] ?? $row['category_name'] ?? 'Marketplace item')) ?></p><span class="mk-badge"><?= e((string) ($row['unit'] ?: $row['price_unit'] ?: 'unit')) ?></span></div>
          <div><strong><?= e(marketplace_money((float) $row['price'])) ?></strong><br><small>/ <?= e((string) ($row['unit'] ?: 'unit')) ?></small></div>
          <form method="post">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="listing_id" value="<?= (int) $row['id'] ?>">
                <div class="qty-mini"><button type="button" onclick="this.nextElementSibling.stepDown()">-</button><input type="number" min="1" name="quantity" value="<?= (int) $row['cart_quantity'] ?>"><button type="button" onclick="this.previousElementSibling.stepUp()">+</button></div>
                <button class="mk-btn secondary" style="margin-top:6px;width:100%" type="submit">Update</button>
          </form>
          <strong style="color:#0b6b33;font-size:1.25rem"><?= e(marketplace_money((float) $row['cart_total'])) ?></strong>
          <form method="post">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="remove">
                <input type="hidden" name="listing_id" value="<?= (int) $row['id'] ?>">
                <button class="mk-btn secondary" title="Remove" type="submit"><i class="fas fa-trash"></i></button>
          </form>
        </div>
      </article>
    <?php endforeach; ?>
    <div style="display:flex;justify-content:space-between;gap:12px"><a class="mk-btn secondary" href="index.php"><i class="fas fa-arrow-left"></i> Continue Shopping</a><form method="post"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="clear"><button class="mk-btn secondary"><i class="fas fa-trash"></i> Clear Cart</button></form></div>
  <?php else: ?>
    <div class="empty-hero">
        <div class="empty-icon"><i class="fas fa-shopping-basket"></i></div>
        <h2>Your cart is feeling a bit lonely</h2>
        <p>It looks like you haven't added anything yet. Explore our marketplace for premium seedlings, tools, and services.</p>
        <a href="index.php" class="mk-btn" style="padding:14px 40px; font-size:1.1rem">Start Shopping Now</a>
        
        <div class="inspiration-section">
            <div class="inspiration-head">
                <h3>Discover Featured Products</h3>
                <a href="featured.php" class="view-link">View all featured</a>
            </div>
            <div class="inspiration-grid">
                <?php foreach ($featuredInspiration as $feat): ?>
                    <a href="product.php?id=<?= (int) $feat['id'] ?>" class="insp-card">
                        <img src="<?= e(market_listing_image_url($feat)) ?>" alt="<?= e((string) $feat['title']) ?>">
                        <h4><?= e((string) $feat['title']) ?></h4>
                        <strong style="color:#0b6b33"><?= e(marketplace_money((float) $feat['price'])) ?></strong>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="inspiration-section" style="text-align:left; border-top:1px solid var(--mk-line); padding-top:30px">
            <h3>Browse by Category</h3>
            <div class="cat-pills">
                <?php foreach ($suggestedCategories as $cat): ?>
                    <a href="index.php?category_id=<?= (int) $cat['id'] ?>" class="cat-pill">
                        <i class="fas fa-tag" style="margin-right:5px; font-size:0.8rem"></i>
                        <?= e((string) $cat['name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
  <?php endif; ?>
  </div>
  <aside class="mk-section cart-summary">
    <?php if ($rows): ?>
        <h2>Order Summary <small style="float:right"><?= number_format(count($rows)) ?> items</small></h2>
        <p>Subtotal <strong style="float:right"><?= e(marketplace_money((float) $total)) ?></strong></p>
        <p>Estimated Delivery <strong style="float:right"><?= e(marketplace_money(8500)) ?></strong></p>
        <hr>
        <p>Order Total <span class="summary-total" style="float:right"><?= e(marketplace_money((float) $total + 8500)) ?></span></p>
        <a class="mk-btn" style="width:100%;margin-top:18px;font-size:1.1rem" href="checkout.php"><i class="fas fa-lock"></i> Proceed to Checkout</a>
        <form method="post" style="width:100%;margin-top:10px">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="save_for_later">
            <button class="mk-btn secondary" style="width:100%" type="submit"><i class="far fa-heart"></i> Save Cart for Later</button>
        </form>
        <div class="coupon"><strong>Have a discount code?</strong><div style="display:grid;grid-template-columns:1fr auto;gap:8px;margin-top:10px"><input placeholder="Enter code"><button class="mk-btn secondary">Apply</button></div></div>
    <?php else: ?>
        <h2>Marketplace Trust</h2>
        <div class="protection" style="margin-top:0">
            <p style="color:#64748b; font-size:0.9rem; margin-bottom:15px">Shop with confidence on Nigeria's leading coconut development marketplace.</p>
            <ul style="list-style:none; padding:0">
                <li><i class="fas fa-shield-alt"></i> <strong>Buyer Protection</strong><br><small>Your payments are secure and held until delivery.</small></li>
                <li><i class="fas fa-user-check"></i> <strong>Verified Sellers</strong><br><small>We vet every vendor for quality and reliability.</small></li>
                <li><i class="fas fa-truck"></i> <strong>Traceable Delivery</strong><br><small>Monitor your seedlings and inputs from farm to field.</small></li>
                <li><i class="fas fa-headset"></i> <strong>Dedicated Support</strong><br><small>Our team is here to help with any order issues.</small></li>
            </ul>
        </div>
        <div class="wallet-note"><strong><i class="fas fa-info-circle"></i> Quick Tip</strong><p>Register as a grower to access seedling subsidies and technical support.</p></div>
    <?php endif; ?>
    <div class="protection"><h3>Payment Options</h3><div style="display:flex; gap:10px; font-size:1.5rem; color:#64748b; margin-top:10px"><i class="fab fa-cc-visa"></i> <i class="fab fa-cc-mastercard"></i> <i class="fas fa-mobile-alt"></i> <i class="fas fa-university"></i></div></div>
  </aside>
</section>
<section class="cart-assurance"><div><i class="fas fa-shield-alt"></i> Quality Products</div><div><i class="fas fa-wallet"></i> Secure Payments</div><div><i class="fas fa-sync"></i> 7-Day Return Policy</div><div><i class="fas fa-hands-helping"></i> Farmers First</div></section>
<?php market_footer(); ?>
