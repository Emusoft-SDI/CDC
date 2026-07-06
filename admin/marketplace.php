<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../lib/admin-layout.php';
require_once __DIR__ . '/../lib/marketplace.php';

$pdo = db();
admin_ensure_schema($pdo);
marketplace_ensure_schema($pdo);
admin_require($pdo, 'marketplace');

function marketplace_admin_redirect(string $page, string $type, string $message): void
{
    $page = preg_replace('/[^a-z-]/', '', $page) ?: 'overview';
    header('Location: marketplace/?' . http_build_query(['page' => $page, $type => $message]));
    exit;
}

if (($_GET['export'] ?? '') === 'sellers') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="natcodev-marketplace-sellers.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Seller ID', 'Store', 'Type', 'Owner Email', 'Approval', 'Verification', 'Listings', 'Created']);
    $rows = $pdo->query("SELECT s.*,u.email user_email,(SELECT COUNT(*) FROM marketplace_listings l WHERE l.seller_id=s.id) listing_count FROM marketplace_sellers s LEFT JOIN users u ON u.id=s.user_id ORDER BY s.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        fputcsv($out, [(int) $row['id'], (string) $row['store_name'], (string) $row['seller_type'], (string) ($row['user_email'] ?? $row['email'] ?? ''), (string) $row['approval_status'], (string) $row['verification_status'], (int) $row['listing_count'], (string) $row['created_at']]);
    }
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    $query = $_GET;
    $query['page'] = preg_replace('/[^a-z-]/', '', (string) ($query['section'] ?? $query['page'] ?? 'overview')) ?: 'overview';
    unset($query['section'], $query['legacy']);
    header('Location: marketplace/?' . http_build_query($query), true, 302);
    exit;
}

$returnPage = preg_replace('/[^a-z-]/', '', (string) ($_POST['workspace_return'] ?? 'overview')) ?: 'overview';
if (!verify_csrf($_POST['_csrf'] ?? null)) {
    marketplace_admin_redirect($returnPage, 'error', 'Invalid security token.');
}

try {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'review_seller') {
        $sellerId = (int) ($_POST['seller_id'] ?? 0);
        $approval = (string) ($_POST['approval_status'] ?? 'pending');
        $verification = (string) ($_POST['verification_status'] ?? 'unverified');
        if (!in_array($approval, ['pending', 'approved', 'rejected', 'suspended'], true)) { $approval = 'pending'; }
        if (!in_array($verification, ['unverified', 'verified', 'flagged'], true)) { $verification = 'unverified'; }
        $stmt = $pdo->prepare("UPDATE marketplace_sellers SET approval_status = ?, verification_status = ?, is_featured = ?, admin_notes = ? WHERE id = ?");
        $stmt->execute([$approval, $verification, (int) ($_POST['is_featured'] ?? 0), trim((string) ($_POST['admin_notes'] ?? '')), $sellerId]);
        marketplace_admin_redirect($returnPage, 'message', 'Seller review updated.');
    }

    if ($action === 'review_listing') {
        $listingId = (int) ($_POST['listing_id'] ?? 0);
        $approval = (string) ($_POST['approval_status'] ?? 'pending');
        $availability = (string) ($_POST['availability_status'] ?? 'available');
        if (!in_array($approval, ['pending', 'approved', 'rejected', 'suspended'], true)) { $approval = 'pending'; }
        if (!in_array($availability, ['available', 'limited', 'out_of_stock', 'paused'], true)) { $availability = 'available'; }
        $stmt = $pdo->prepare("UPDATE marketplace_listings SET approval_status = ?, availability_status = ?, is_featured = ? WHERE id = ?");
        $stmt->execute([$approval, $availability, (int) ($_POST['is_featured'] ?? 0), $listingId]);
        marketplace_admin_redirect($returnPage, 'message', 'Listing review updated.');
    }

    if ($action === 'admin_listing') {
        $sellerId = (int) ($_POST['seller_id'] ?? marketplace_official_seller_id($pdo));
        $title = trim((string) ($_POST['title'] ?? ''));
        $listingType = (string) ($_POST['listing_type'] ?? 'product');
        $listingTypes = marketplace_listing_types();
        if (!isset($listingTypes[$listingType])) { $listingType = 'product'; }
        if ($title === '') { throw new RuntimeException('Listing title is required.'); }
        $slug = marketplace_unique_slug($pdo, 'marketplace_listings', $title);
        $stmt = $pdo->prepare("INSERT INTO marketplace_listings (seller_id, category_id, listing_type, title, slug, summary, description, price, price_unit, quantity_available, unit, location_label, fulfillment_method, approval_status, availability_status, is_featured) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved', 'available', ?)");
        $stmt->execute([$sellerId, (int) ($_POST['category_id'] ?? 0) ?: marketplace_category_id_for_type($pdo, $listingType), $listingType, $title, $slug, trim((string) ($_POST['summary'] ?? '')), trim((string) ($_POST['description'] ?? '')), max(0, (float) ($_POST['price'] ?? 0)), trim((string) ($_POST['price_unit'] ?? '')), $_POST['quantity_available'] === '' ? null : max(0, (float) $_POST['quantity_available']), trim((string) ($_POST['unit'] ?? '')), trim((string) ($_POST['location_label'] ?? '')), trim((string) ($_POST['fulfillment_method'] ?? '')), (int) ($_POST['is_featured'] ?? 0)]);
        marketplace_admin_redirect($returnPage ?: 'products', 'message', 'Admin listing published.');
    }

    if ($action === 'update_order') {
        $orderId = (int) ($_POST['order_id'] ?? 0);
        $status = (string) ($_POST['status'] ?? 'preparing');
        if (!in_array($status, ['quoted', 'accepted', 'paid', 'preparing', 'ready', 'scheduled', 'in_transit', 'completed', 'cancelled', 'disputed'], true)) { $status = 'preparing'; }
        $deliveryStatus = (string) ($_POST['delivery_status'] ?? 'awaiting_seller');
        if (!in_array($deliveryStatus, ['not_started', 'awaiting_seller', 'packing', 'ready_for_pickup', 'scheduled', 'in_transit', 'delivered', 'failed', 'returned'], true)) { $deliveryStatus = 'awaiting_seller'; }
        $trackingRef = preg_replace('/[^A-Z0-9_.\-\/]/i', '', (string) ($_POST['tracking_ref'] ?? ''));
        $stmt = $pdo->prepare("UPDATE marketplace_orders SET status = ?, delivery_status = ?, tracking_ref = NULLIF(?, ''), fulfillment_note = ?, operator_delivery_note = ?, operator_reviewed_at = NOW() WHERE id = ?");
        $stmt->execute([$status, $deliveryStatus, $trackingRef, trim((string) ($_POST['fulfillment_note'] ?? '')), trim((string) ($_POST['operator_delivery_note'] ?? '')), $orderId]);
        marketplace_admin_redirect($returnPage, 'message', 'Order delivery control updated.');
    }

    marketplace_admin_redirect($returnPage, 'error', 'Unknown marketplace action.');
} catch (Throwable $e) {
    marketplace_admin_redirect($returnPage, 'error', $e instanceof RuntimeException ? $e->getMessage() : 'Marketplace action failed.');
}
