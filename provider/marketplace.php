<?php
declare(strict_types=1);
require_once __DIR__ . '/_provider.php';

provider_simple_page('marketplace', 'Marketplace', 'Open marketplace storefront, seller central, listings, checkout, and order tracking.', function(PDO $pdo, array $user): void {
    $links = market_user_dashboard_links($pdo, $user, 'provider');
    $sellerLink = null;
    foreach ($links as $link) {
        if (($link['key'] ?? '') === 'seller') {
            $sellerLink = $link;
            break;
        }
    }
    echo '<div class="grid"><a class="card span-4" href="../market/index.php"><h2>Public Marketplace</h2><p>View the buyer-facing marketplace.</p></a>';
    if ($sellerLink) {
        echo '<a class="card span-4" href="' . e((string) $sellerLink['href']) . '"><h2>' . e((string) $sellerLink['label']) . '</h2><p>Manage products, orders, listings, and marketplace settings within your approved seller access.</p></a>';
    } else {
        echo '<a class="card span-4" href="support.php"><h2>Request Seller Store</h2><p>Provider accreditation is separate. Ask NATCODEV to enable marketplace seller access when you are ready to sell.</p></a>';
    }
    echo '<a class="card span-4" href="../market/orders.php"><h2>Order Tracking</h2><p>Track fulfillment and delivery status.</p></a></div>';
});