<?php
declare(strict_types=1);
require_once __DIR__ . '/_buyer.php';
buyer_simple_page('marketplace', 'Buyer Marketplace', 'Browse verified coconut products, inputs, tools, services, and seller storefronts.', function(PDO $pdo): void {
    $items = market_listing_query($pdo, [], 12);
    echo '<div class="grid"><section class="card span-12"><div class="card-head"><h2>Marketplace Picks</h2><a class="btn" href="../market/index.php">Open Full Marketplace</a></div><div class="grid">';
    foreach ($items as $item) {
        echo '<a class="card span-3" href="../market/product.php?id=' . (int) $item['id'] . '"><img class="thumb" style="width:100%;height:145px" src="' . e(market_listing_image_url($item)) . '" alt=""><h3>' . e((string) $item['title']) . '</h3><p>' . e((string) $item['store_name']) . '</p><strong>' . e(marketplace_money((float) $item['price'])) . '</strong></a>';
    }
    echo '</div></section></div>';
});
?>
