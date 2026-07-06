<?php
declare(strict_types=1);
require_once __DIR__ . '/_provider.php';

provider_simple_page('orders', 'Orders & Requests', 'Track marketplace orders, quote requests, and service requests connected to your provider account.', function(PDO $pdo, array $user): void {
    $message = '';
    $error = '';
    $orders = [];
    $seller = marketplace_current_seller($pdo, (int) $user['id']);

    if (!$seller) {
        echo '<section class="card"><div class="card-head"><h2>Orders & Requests</h2></div><div class="list"><p>Your provider account is not linked to a marketplace seller record yet.</p><p>To manage marketplace orders, please activate seller access or contact NATCODEV support.</p></div></section>';
        return;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['_csrf'] ?? null)) {
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'update_order') {
            try {
                $orderId = (int) ($_POST['order_id'] ?? 0);
                $status = (string) ($_POST['status'] ?? 'preparing');
                if (!in_array($status, ['quoted', 'accepted', 'preparing', 'ready', 'scheduled', 'in_transit', 'completed', 'cancelled', 'disputed'], true)) {
                    $status = 'preparing';
                }
                $deliveryStatus = (string) ($_POST['delivery_status'] ?? 'awaiting_seller');
                if (!in_array($deliveryStatus, ['not_started', 'awaiting_seller', 'packing', 'ready_for_pickup', 'scheduled', 'in_transit', 'delivered', 'failed', 'returned'], true)) {
                    $deliveryStatus = 'awaiting_seller';
                }
                $trackingRef = trim((string) ($_POST['tracking_ref'] ?? ''));
                $fulfillmentNote = trim((string) ($_POST['fulfillment_note'] ?? ''));

                $stmt = $pdo->prepare("UPDATE marketplace_orders SET status = ?, delivery_status = ?, tracking_ref = NULLIF(?, ''), fulfillment_note = ? WHERE id = ? AND seller_id = ?");
                $stmt->execute([$status, $deliveryStatus, $trackingRef, $fulfillmentNote, $orderId, (int) $seller['id']]);
                $message = 'Order fulfillment details updated successfully.';
            } catch (Throwable $e) {
                $error = 'Unable to update the order. Please try again.';
            }
        }
    }

    if (app_table_exists($pdo, 'marketplace_orders')) {
        $stmt = $pdo->prepare("SELECT o.*, l.title AS listing_title FROM marketplace_orders o JOIN marketplace_listings l ON l.id = o.listing_id WHERE o.seller_id = ? ORDER BY o.created_at DESC LIMIT 40");
        $stmt->execute([(int) $seller['id']]);
        $orders = $stmt->fetchAll();
    }

    echo '<section class="card"><div class="card-head"><h2>Latest Orders</h2><a class="view" href="../market/orders.php">Public Tracking</a></div>';
    if ($message) {
        echo '<div class="notice ok">' . e($message) . '</div>';
    }
    if ($error) {
        echo '<div class="notice err">' . e($error) . '</div>';
    }
    echo '<div class="list">';

    foreach ($orders as $order) {
        $orderRef = e((string) ($order['checkout_ref'] ?: $order['order_ref']));
        echo '<div class="row"><span><strong>' . $orderRef . '</strong><br><small>' . e((string) $order['listing_title']) . '</small><br><small>Buyer: ' . e((string) $order['buyer_name']) . ' / ' . e((string) $order['buyer_email']) . '</small><br><small>Phone: ' . e((string) $order['buyer_phone']) . '</small></span><span class="badge">' . e(marketplace_status_label((string) $order['status'])) . '</span></div>';
        echo '<div class="row"><span><strong>Delivery</strong> ' . e(marketplace_status_label((string) $order['delivery_status'])) . '</span><span>' . e(marketplace_money((float) ($order['total_amount'] ?? 0))) . '</span></div>';
        echo '<div class="row"><span><strong>Address</strong> ' . e((string) $order['delivery_address']) . '</span><span><strong>Tracking</strong> ' . e((string) $order['tracking_ref']) . '</span></div>';
        echo '<form method="post" class="row" style="gap:0.75rem;padding:0.75rem 0;background:#f7f9fa;border-top:1px solid #e5e7eb">';
        echo '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
        echo '<input type="hidden" name="action" value="update_order">';
        echo '<input type="hidden" name="order_id" value="' . (int) $order['id'] . '">';
        echo '<label>Order status<select name="status"><option value="quoted"' . (((string) $order['status'] === 'quoted') ? ' selected' : '') . '>Quoted</option><option value="accepted"' . (((string) $order['status'] === 'accepted') ? ' selected' : '') . '>Accepted</option><option value="preparing"' . (((string) $order['status'] === 'preparing') ? ' selected' : '') . '>Preparing</option><option value="ready"' . (((string) $order['status'] === 'ready') ? ' selected' : '') . '>Ready</option><option value="scheduled"' . (((string) $order['status'] === 'scheduled') ? ' selected' : '') . '>Scheduled</option><option value="in_transit"' . (((string) $order['status'] === 'in_transit') ? ' selected' : '') . '>In Transit</option><option value="completed"' . (((string) $order['status'] === 'completed') ? ' selected' : '') . '>Completed</option><option value="cancelled"' . (((string) $order['status'] === 'cancelled') ? ' selected' : '') . '>Cancelled</option><option value="disputed"' . (((string) $order['status'] === 'disputed') ? ' selected' : '') . '>Disputed</option></select></label>';
        echo '<label>Delivery status<select name="delivery_status"><option value="not_started"' . (((string) $order['delivery_status'] === 'not_started') ? ' selected' : '') . '>Not started</option><option value="awaiting_seller"' . (((string) $order['delivery_status'] === 'awaiting_seller') ? ' selected' : '') . '>Awaiting seller</option><option value="packing"' . (((string) $order['delivery_status'] === 'packing') ? ' selected' : '') . '>Packing</option><option value="ready_for_pickup"' . (((string) $order['delivery_status'] === 'ready_for_pickup') ? ' selected' : '') . '>Ready for pickup</option><option value="scheduled"' . (((string) $order['delivery_status'] === 'scheduled') ? ' selected' : '') . '>Scheduled</option><option value="in_transit"' . (((string) $order['delivery_status'] === 'in_transit') ? ' selected' : '') . '>In transit</option><option value="delivered"' . (((string) $order['delivery_status'] === 'delivered') ? ' selected' : '') . '>Delivered</option><option value="failed"' . (((string) $order['delivery_status'] === 'failed') ? ' selected' : '') . '>Failed</option><option value="returned"' . (((string) $order['delivery_status'] === 'returned') ? ' selected' : '') . '>Returned</option></select></label>';
        echo '<label class="wide">Tracking reference<input name="tracking_ref" value="' . e((string) $order['tracking_ref']) . '"></label>';
        echo '<label class="wide">Seller fulfillment note<textarea name="fulfillment_note" rows="2">' . e((string) $order['fulfillment_note']) . '</textarea></label>';
        echo '<div class="wide"><button class="btn">Save order update</button></div>';
        echo '</form>';
    }

    if (!$orders) {
        echo '<p>No marketplace orders yet. Buyer requests and marketplace orders will appear here once available.</p>';
    }
    echo '</div></section>';
});
