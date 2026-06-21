<?php
declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../lib/admin-layout.php';
require_once __DIR__ . '/../lib/marketplace.php';

$pdo = db();
admin_ensure_schema($pdo);
marketplace_ensure_schema($pdo);
admin_require($pdo);

$message = '';
$error = '';
$sellerTypes = marketplace_seller_types();
$listingTypes = marketplace_listing_types();
$categories = marketplace_categories($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid security token.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'review_seller') {
            $sellerId = (int) ($_POST['seller_id'] ?? 0);
            $approval = (string) ($_POST['approval_status'] ?? 'pending');
            $verification = (string) ($_POST['verification_status'] ?? 'unverified');
            if (!in_array($approval, ['pending', 'approved', 'rejected', 'suspended'], true)) {
                $approval = 'pending';
            }
            if (!in_array($verification, ['unverified', 'verified', 'flagged'], true)) {
                $verification = 'unverified';
            }
            $stmt = $pdo->prepare("UPDATE marketplace_sellers SET approval_status = ?, verification_status = ?, is_featured = ?, admin_notes = ? WHERE id = ?");
            $stmt->execute([$approval, $verification, (int) ($_POST['is_featured'] ?? 0), trim((string) ($_POST['admin_notes'] ?? '')), $sellerId]);
            $message = 'Seller review updated.';
        } elseif ($action === 'review_listing') {
            $listingId = (int) ($_POST['listing_id'] ?? 0);
            $approval = (string) ($_POST['approval_status'] ?? 'pending');
            if (!in_array($approval, ['pending', 'approved', 'rejected', 'suspended'], true)) {
                $approval = 'pending';
            }
            $availability = (string) ($_POST['availability_status'] ?? 'available');
            if (!in_array($availability, ['available', 'limited', 'out_of_stock', 'paused'], true)) {
                $availability = 'available';
            }
            $stmt = $pdo->prepare("UPDATE marketplace_listings SET approval_status = ?, availability_status = ?, is_featured = ? WHERE id = ?");
            $stmt->execute([$approval, $availability, (int) ($_POST['is_featured'] ?? 0), $listingId]);
            $message = 'Listing review updated.';
        } elseif ($action === 'save_category') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $listingType = (string) ($_POST['listing_type'] ?? 'product');
            if (!isset($listingTypes[$listingType])) {
                $listingType = 'product';
            }
            if ($name === '') {
                $error = 'Category name is required.';
            } else {
                $slug = marketplace_unique_slug($pdo, 'marketplace_categories', $name);
                $stmt = $pdo->prepare("INSERT INTO marketplace_categories (name, slug, listing_type, description, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $slug, $listingType, trim((string) ($_POST['description'] ?? '')), (int) ($_POST['sort_order'] ?? 100), (int) ($_POST['is_active'] ?? 1)]);
                $message = 'Category added.';
                $categories = marketplace_categories($pdo);
            }
        } elseif ($action === 'admin_listing') {
            $sellerId = (int) ($_POST['seller_id'] ?? marketplace_official_seller_id($pdo));
            $title = trim((string) ($_POST['title'] ?? ''));
            $listingType = (string) ($_POST['listing_type'] ?? 'product');
            if (!isset($listingTypes[$listingType])) {
                $listingType = 'product';
            }
            if ($title === '') {
                $error = 'Listing title is required.';
            } else {
                $slug = marketplace_unique_slug($pdo, 'marketplace_listings', $title);
                $stmt = $pdo->prepare("
                    INSERT INTO marketplace_listings
                        (seller_id, category_id, listing_type, title, slug, summary, description, price, price_unit, quantity_available, unit, location_label, fulfillment_method, approval_status, availability_status, is_featured)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved', 'available', ?)
                ");
                $stmt->execute([
                    $sellerId,
                    (int) ($_POST['category_id'] ?? 0) ?: marketplace_category_id_for_type($pdo, $listingType),
                    $listingType,
                    $title,
                    $slug,
                    trim((string) ($_POST['summary'] ?? '')),
                    trim((string) ($_POST['description'] ?? '')),
                    max(0, (float) ($_POST['price'] ?? 0)),
                    trim((string) ($_POST['price_unit'] ?? '')),
                    $_POST['quantity_available'] === '' ? null : max(0, (float) $_POST['quantity_available']),
                    trim((string) ($_POST['unit'] ?? '')),
                    trim((string) ($_POST['location_label'] ?? '')),
                    trim((string) ($_POST['fulfillment_method'] ?? '')),
                    (int) ($_POST['is_featured'] ?? 0),
                ]);
                $message = 'Admin listing published.';
            }
        } elseif ($action === 'update_order') {
            $orderId = (int) ($_POST['order_id'] ?? 0);
            $status = (string) ($_POST['status'] ?? 'preparing');
            if (!in_array($status, ['quoted', 'accepted', 'preparing', 'ready', 'scheduled', 'in_transit', 'completed', 'cancelled', 'disputed'], true)) {
                $status = 'preparing';
            }
            $stmt = $pdo->prepare("UPDATE marketplace_orders SET status = ?, fulfillment_note = ? WHERE id = ?");
            $stmt->execute([$status, trim((string) ($_POST['fulfillment_note'] ?? '')), $orderId]);
            $message = 'Order status updated.';
        }
    }
}

$stats = [
    'sellers' => (int) $pdo->query("SELECT COUNT(*) FROM marketplace_sellers")->fetchColumn(),
    'pending_sellers' => (int) $pdo->query("SELECT COUNT(*) FROM marketplace_sellers WHERE approval_status = 'pending'")->fetchColumn(),
    'listings' => (int) $pdo->query("SELECT COUNT(*) FROM marketplace_listings")->fetchColumn(),
    'pending_listings' => (int) $pdo->query("SELECT COUNT(*) FROM marketplace_listings WHERE approval_status = 'pending'")->fetchColumn(),
    'inquiries' => (int) $pdo->query("SELECT COUNT(*) FROM marketplace_inquiries")->fetchColumn(),
    'orders' => (int) $pdo->query("SELECT COUNT(*) FROM marketplace_orders")->fetchColumn(),
];

$sellers = $pdo->query("
    SELECT s.*, u.name user_name, u.email user_email,
        (SELECT COUNT(*) FROM marketplace_listings l WHERE l.seller_id = s.id) listings
    FROM marketplace_sellers s
    LEFT JOIN users u ON u.id = s.user_id
    ORDER BY FIELD(s.approval_status,'pending','approved','rejected','suspended'), s.created_at DESC
    LIMIT 120
")->fetchAll();

$sellerOptions = $pdo->query("SELECT id, store_name, approval_status FROM marketplace_sellers ORDER BY store_name LIMIT 300")->fetchAll();

$listings = $pdo->query("
    SELECT l.*, s.store_name, c.name category_name
    FROM marketplace_listings l
    JOIN marketplace_sellers s ON s.id = l.seller_id
    LEFT JOIN marketplace_categories c ON c.id = l.category_id
    ORDER BY FIELD(l.approval_status,'pending','approved','rejected','suspended'), l.created_at DESC
    LIMIT 160
")->fetchAll();

$inquiries = $pdo->query("
    SELECT i.*, l.title listing_title, s.store_name
    FROM marketplace_inquiries i
    JOIN marketplace_listings l ON l.id = i.listing_id
    JOIN marketplace_sellers s ON s.id = i.seller_id
    ORDER BY i.created_at DESC
    LIMIT 80
")->fetchAll();

$orders = $pdo->query("
    SELECT o.*, l.title listing_title, s.store_name
    FROM marketplace_orders o
    JOIN marketplace_listings l ON l.id = o.listing_id
    JOIN marketplace_sellers s ON s.id = o.seller_id
    ORDER BY o.created_at DESC
    LIMIT 80
")->fetchAll();

$marketplaceExport = preg_replace('/[^a-z0-9_-]/', '', strtolower((string) ($_GET['export'] ?? '')));
if ($marketplaceExport !== '') {
    $rows = match ($marketplaceExport) {
        'sellers' => $sellers,
        'listings', 'products', 'inventory' => $listings,
        'inquiries', 'customers', 'disputes' => $inquiries,
        'orders', 'sales' => $orders,
        default => array_merge($orders, $listings),
    };
    app_export_csv('natcodev-marketplace-' . $marketplaceExport . '-' . date('Ymd') . '.csv', $rows ? array_keys($rows[0]) : [], $rows);
}

admin_page_start('Marketplace', [
    'active' => 'marketplace.php',
    'description' => 'Approve sellers, moderate listings, manage categories, review inquiries, and supervise NATCODEV marketplace orders.',
    'wide' => true,
    'action_html' => '<a class="button secondary" href="marketplace.php?export=sellers">Export Sellers</a> <a class="button secondary" href="marketplace.php?export=orders">Export Orders</a> <a class="button secondary" href="../market/index.php">Public Marketplace</a>',
]);
?>
<?php if ($message): ?><div class="notice ok"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>

<section class="stats">
  <div class="stat"><div class="metric"><?= (int) $stats['sellers'] ?></div><strong>Sellers</strong><p class="meta"><?= (int) $stats['pending_sellers'] ?> pending</p></div>
  <div class="stat"><div class="metric"><?= (int) $stats['listings'] ?></div><strong>Listings</strong><p class="meta"><?= (int) $stats['pending_listings'] ?> pending</p></div>
  <div class="stat"><div class="metric"><?= (int) $stats['inquiries'] ?></div><strong>Inquiries</strong></div>
  <div class="stat"><div class="metric"><?= (int) $stats['orders'] ?></div><strong>Orders</strong></div>
</section>

<section id="sellers" class="layout">
  <aside class="panel">
    <h2>Publish Admin Listing</h2>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="admin_listing">
      <label>Seller
        <select name="seller_id">
          <?php foreach ($sellerOptions as $seller): ?>
            <option value="<?= (int) $seller['id'] ?>"><?= e((string) $seller['store_name']) ?> (<?= e((string) $seller['approval_status']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Type<select name="listing_type"><?php foreach ($listingTypes as $key => $label): ?><option value="<?= e($key) ?>"><?= e($label) ?></option><?php endforeach; ?></select></label>
      <label>Category<select name="category_id"><option value="0">Auto match</option><?php foreach ($categories as $category): ?><option value="<?= (int) $category['id'] ?>"><?= e((string) $category['name']) ?></option><?php endforeach; ?></select></label>
      <label>Title<input name="title" required></label>
      <label>Summary<input name="summary"></label>
      <label>Description<textarea name="description"></textarea></label>
      <label>Price<input type="number" name="price" min="0" step="0.01" value="0"></label>
      <label>Price Unit<input name="price_unit" placeholder="seedling, day, hectare, tonne"></label>
      <label>Quantity<input type="number" name="quantity_available" min="0" step="0.01"></label>
      <label>Unit<input name="unit"></label>
      <label>Location<input name="location_label"></label>
      <label>Fulfillment<input name="fulfillment_method"></label>
      <label><input type="checkbox" name="is_featured" value="1" style="width:auto;"> Featured</label>
      <button type="submit">Publish Listing</button>
    </form>

    <h2 style="margin-top:20px;">Add Category</h2>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="save_category">
      <label>Name<input name="name" required></label>
      <label>Listing Type<select name="listing_type"><?php foreach ($listingTypes as $key => $label): ?><option value="<?= e($key) ?>"><?= e($label) ?></option><?php endforeach; ?></select></label>
      <label>Description<textarea name="description"></textarea></label>
      <label>Sort Order<input type="number" name="sort_order" value="100"></label>
      <label><input type="checkbox" name="is_active" value="1" checked style="width:auto;"> Active</label>
      <button class="secondary" type="submit">Add Category</button>
    </form>
  </aside>

  <section>
    <section class="panel">
      <h2>Seller Review</h2>
      <table>
        <thead><tr><th>Seller</th><th>Owner</th><th>Type</th><th>Status</th><th>Review</th></tr></thead>
        <tbody>
          <?php foreach ($sellers as $seller): ?>
            <tr>
              <td><strong><?= e((string) $seller['store_name']) ?></strong><br><small><?= (int) $seller['listings'] ?> listing(s) / <?= e((string) $seller['created_at']) ?></small></td>
              <td><?= e((string) ($seller['user_name'] ?: $seller['contact_person'])) ?><br><small><?= e((string) ($seller['user_email'] ?: $seller['email'])) ?></small></td>
              <td><?= e($sellerTypes[(string) $seller['seller_type']] ?? marketplace_status_label((string) $seller['seller_type'])) ?></td>
              <td><span class="badge <?= e((string) $seller['approval_status']) ?>"><?= e(marketplace_status_label((string) $seller['approval_status'])) ?></span><br><span class="badge"><?= e(marketplace_status_label((string) $seller['verification_status'])) ?></span></td>
              <td>
                <form method="post">
                  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="review_seller">
                  <input type="hidden" name="seller_id" value="<?= (int) $seller['id'] ?>">
                  <select name="approval_status"><?php foreach (['pending', 'approved', 'rejected', 'suspended'] as $status): ?><option value="<?= e($status) ?>" <?= (string) $seller['approval_status'] === $status ? 'selected' : '' ?>><?= e(marketplace_status_label($status)) ?></option><?php endforeach; ?></select>
                  <select name="verification_status"><?php foreach (['unverified', 'verified', 'flagged'] as $status): ?><option value="<?= e($status) ?>" <?= (string) $seller['verification_status'] === $status ? 'selected' : '' ?>><?= e(marketplace_status_label($status)) ?></option><?php endforeach; ?></select>
                  <label><input type="checkbox" name="is_featured" value="1" <?= (int) $seller['is_featured'] === 1 ? 'checked' : '' ?> style="width:auto;"> Featured</label>
                  <textarea name="admin_notes"><?= e((string) $seller['admin_notes']) ?></textarea>
                  <button class="secondary" type="submit">Save</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$sellers): ?><tr><td colspan="5">No sellers yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </section>

    <section id="listings" class="panel" style="margin-top:18px;">
      <h2>Listing Moderation</h2>
      <table>
        <thead><tr><th>Listing</th><th>Seller</th><th>Type</th><th>Price</th><th>Status</th><th>Review</th></tr></thead>
        <tbody>
          <?php foreach ($listings as $listing): ?>
            <tr>
              <td><strong><?= e((string) $listing['title']) ?></strong><br><small><?= e((string) ($listing['category_name'] ?: 'Marketplace')) ?></small></td>
              <td><?= e((string) $listing['store_name']) ?></td>
              <td><?= e($listingTypes[(string) $listing['listing_type']] ?? marketplace_status_label((string) $listing['listing_type'])) ?></td>
              <td><?= marketplace_money((float) $listing['price']) ?></td>
              <td><span class="badge <?= e((string) $listing['approval_status']) ?>"><?= e(marketplace_status_label((string) $listing['approval_status'])) ?></span><br><span class="badge"><?= e(marketplace_status_label((string) $listing['availability_status'])) ?></span></td>
              <td>
                <form method="post">
                  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="action" value="review_listing">
                  <input type="hidden" name="listing_id" value="<?= (int) $listing['id'] ?>">
                  <select name="approval_status"><?php foreach (['pending', 'approved', 'rejected', 'suspended'] as $status): ?><option value="<?= e($status) ?>" <?= (string) $listing['approval_status'] === $status ? 'selected' : '' ?>><?= e(marketplace_status_label($status)) ?></option><?php endforeach; ?></select>
                  <select name="availability_status"><?php foreach (['available', 'limited', 'out_of_stock', 'paused'] as $status): ?><option value="<?= e($status) ?>" <?= (string) $listing['availability_status'] === $status ? 'selected' : '' ?>><?= e(marketplace_status_label($status)) ?></option><?php endforeach; ?></select>
                  <label><input type="checkbox" name="is_featured" value="1" <?= (int) $listing['is_featured'] === 1 ? 'checked' : '' ?> style="width:auto;"> Featured</label>
                  <button class="secondary" type="submit">Save</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$listings): ?><tr><td colspan="6">No listings yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </section>
  </section>
</section>

<section id="inquiries" class="panel" style="margin-top:18px;">
  <h2>Inquiries</h2>
  <table>
    <thead><tr><th>Reference</th><th>Listing</th><th>Seller</th><th>Buyer</th><th>Status</th><th>Quote</th></tr></thead>
    <tbody>
      <?php foreach ($inquiries as $inquiry): ?>
        <tr>
          <td><?= e((string) $inquiry['inquiry_ref']) ?><br><small><?= e((string) $inquiry['created_at']) ?></small></td>
          <td><?= e((string) $inquiry['listing_title']) ?></td>
          <td><?= e((string) $inquiry['store_name']) ?></td>
          <td><?= e((string) $inquiry['buyer_name']) ?><br><small><?= e((string) $inquiry['buyer_email']) ?> <?= e((string) $inquiry['buyer_phone']) ?></small></td>
          <td><span class="badge <?= e((string) $inquiry['status']) ?>"><?= e(marketplace_status_label((string) $inquiry['status'])) ?></span></td>
          <td><?= $inquiry['quoted_amount'] !== null ? marketplace_money((float) $inquiry['quoted_amount']) : '<span class="meta">Pending</span>' ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$inquiries): ?><tr><td colspan="6">No inquiries yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</section>

<section id="orders" class="panel" style="margin-top:18px;">
  <h2>Orders</h2>
  <table>
    <thead><tr><th>Order</th><th>Listing</th><th>Seller</th><th>Buyer</th><th>Total</th><th>Status</th><th>Update</th></tr></thead>
    <tbody>
      <?php foreach ($orders as $order): ?>
        <tr>
          <td><?= e((string) $order['order_ref']) ?><br><small><?= e((string) $order['created_at']) ?></small></td>
          <td><?= e((string) $order['listing_title']) ?></td>
          <td><?= e((string) $order['store_name']) ?></td>
          <td><?= e((string) $order['buyer_name']) ?><br><small><?= e((string) $order['buyer_phone']) ?></small></td>
          <td><?= marketplace_money((float) $order['total_amount']) ?></td>
          <td><span class="badge <?= e((string) $order['status']) ?>"><?= e(marketplace_status_label((string) $order['status'])) ?></span></td>
          <td>
            <form method="post">
              <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="update_order">
              <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
              <select name="status"><?php foreach (['quoted', 'accepted', 'preparing', 'ready', 'scheduled', 'in_transit', 'completed', 'cancelled', 'disputed'] as $status): ?><option value="<?= e($status) ?>" <?= (string) $order['status'] === $status ? 'selected' : '' ?>><?= e(marketplace_status_label($status)) ?></option><?php endforeach; ?></select>
              <textarea name="fulfillment_note"><?= e((string) $order['fulfillment_note']) ?></textarea>
              <button class="secondary" type="submit">Update</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$orders): ?><tr><td colspan="7">No marketplace orders yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</section>
<script>
(function(){
  const pageSize = 25;
  document.querySelectorAll('section.panel table').forEach((table) => {
    const body = table.querySelector('tbody');
    if (!body) return;
    const rows = Array.from(body.querySelectorAll('tr'));
    if (rows.length <= pageSize) return;
    let page = 1;
    const total = Math.ceil(rows.length / pageSize);
    const nav = document.createElement('div');
    nav.className = 'pagination';
    nav.innerHTML = '<div class="meta"></div><div class="pagination-links"><button class="secondary" type="button">Previous</button><span class="meta"></span><button class="secondary" type="button">Next</button></div>';
    table.insertAdjacentElement('afterend', nav);
    const [prev, next] = nav.querySelectorAll('button');
    const [range, current] = nav.querySelectorAll('.meta');
    function render(){
      const start = (page - 1) * pageSize;
      const end = start + pageSize;
      rows.forEach((row, index) => { row.style.display = index >= start && index < end ? '' : 'none'; });
      range.textContent = `Showing ${start + 1}-${Math.min(end, rows.length)} of ${rows.length}`;
      current.textContent = `Page ${page} of ${total}`;
      prev.disabled = page <= 1;
      next.disabled = page >= total;
    }
    prev.addEventListener('click', () => { page = Math.max(1, page - 1); render(); });
    next.addEventListener('click', () => { page = Math.min(total, page + 1); render(); });
    render();
  });
})();
</script>
<?php admin_page_end(); ?>
