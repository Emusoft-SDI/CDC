<?php
declare(strict_types=1);
require_once __DIR__ . '/_buyer.php';
buyer_simple_page('cart', 'Buyer Cart', 'Review your marketplace cart and proceed to secure checkout.', function(PDO $pdo): void {
    $rows = market_cart_rows($pdo);
    echo '<div class="card"><div class="card-head"><h2>Cart Items</h2><a class="btn" href="../market/cart.php">Open Cart</a></div>';
    if (!$rows) { echo '<p>Your cart is empty.</p><a class="btn light" href="../market/index.php">Start Shopping</a>'; }
    foreach ($rows as $row) {
        echo '<div class="row"><span style="display:flex;gap:10px;align-items:center"><img class="thumb" src="' . e(market_listing_image_url($row)) . '" alt=""><span><strong>' . e((string) $row['title']) . '</strong><br><small>Qty: ' . (int) $row['cart_quantity'] . '</small></span></span><strong>' . e(marketplace_money((float) $row['cart_total'])) . '</strong></div>';
    }
    echo '</div>';
});
?>
