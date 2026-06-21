<?php
declare(strict_types=1);
require_once __DIR__ . '/_provider.php';

provider_simple_page('orders', 'Orders & Requests', 'Track marketplace orders, quote requests, and service requests connected to your provider account.', function(PDO $pdo, array $user): void {
    $orders = [];
    if (app_table_exists($pdo, 'marketplace_orders')) {
        $stmt = $pdo->prepare("
            SELECT o.*
            FROM marketplace_orders o
            JOIN marketplace_sellers s ON s.id = o.seller_id
            WHERE s.user_id = ?
            ORDER BY o.created_at DESC
            LIMIT 20
        ");
        $stmt->execute([(int) $user['id']]);
        $orders = $stmt->fetchAll();
    }
    echo '<section class="card"><div class="card-head"><h2>Latest Orders</h2><a class="view" href="../market/orders.php">Public Tracking</a></div><div class="list">';
    foreach ($orders as $order) {
        echo '<div class="row"><span><strong>' . e((string) $order['checkout_ref']) . '</strong><br><small>' . e((string) $order['buyer_name']) . '</small></span><span class="badge">' . e(provider_status_label((string) $order['delivery_status'])) . '</span></div>';
    }
    if (!$orders) {
        echo '<p>Orders and service requests will appear after marketplace buyers purchase or contact you.</p>';
    }
    echo '</div></section>';
});
