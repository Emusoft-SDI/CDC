<?php
declare(strict_types=1);
require_once __DIR__ . '/_provider.php';

provider_simple_page('marketplace', 'Marketplace', 'Open marketplace storefront, seller central, listings, checkout, and order tracking.', function(): void {
    echo '<div class="grid"><a class="card span-4" href="../market/index.php"><h2>Public Marketplace</h2><p>View the buyer-facing marketplace.</p></a><a class="card span-4" href="../market/seller-central.php"><h2>Seller Central</h2><p>Manage products, orders, listings, and marketplace settings.</p></a><a class="card span-4" href="../market/orders.php"><h2>Order Tracking</h2><p>Track fulfillment and delivery status.</p></a></div>';
});
