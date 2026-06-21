<?php
declare(strict_types=1);
require_once __DIR__ . '/_provider.php';
require_once __DIR__ . '/../lib/admin-layout.php';

provider_simple_page('products', 'Products & Services', 'Add and manage approved products, services, and provider training programs.', function(PDO $pdo, array $user, array $provider): void {
    $msg = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['_csrf'] ?? null)) {
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'add') {
            $type = in_array((string) ($_POST['offering_type'] ?? 'product'), ['product', 'service', 'training'], true) ? (string) $_POST['offering_type'] : 'product';
            $stmt = $pdo->prepare("INSERT INTO provider_offerings (provider_id, offering_type, category, name, description, price, availability, unit, minimum_order, lead_time, coverage_area, stock_status, requires_quote) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([(int) $provider['id'], $type, trim((string) $_POST['category']), trim((string) $_POST['name']), trim((string) $_POST['description']), ($_POST['price'] ?? '') === '' ? null : (float) $_POST['price'], trim((string) $_POST['availability']), trim((string) $_POST['unit']), trim((string) $_POST['minimum_order']), trim((string) $_POST['lead_time']), trim((string) $_POST['coverage_area']), trim((string) $_POST['stock_status']), isset($_POST['requires_quote']) ? 1 : 0]);
            $msg = 'Offering added. NATCODEV approval controls marketplace visibility.';
        } elseif ($action === 'remove') {
            $offeringId = (int) ($_POST['offering_id'] ?? 0);
            $stmt = $pdo->prepare("SELECT id, name FROM provider_offerings WHERE id = ? AND provider_id = ? LIMIT 1");
            $stmt->execute([$offeringId, (int) $provider['id']]);
            $offering = $stmt->fetch();
            if ($offering) {
                admin_queue_verified_delete_request($pdo, 'provider_offerings', $offeringId, (string) $offering['name'], 'Provider requested offering removal.');
                $msg = 'Offering removal request sent to Super Admin for approval.';
            } else {
                $msg = 'Offering not found for this provider.';
            }
        }
    }
    $offerings = provider_offerings($pdo, (int) $provider['id'], 40);
    if ($msg) {
        echo '<div class="notice ok">' . e($msg) . '</div>';
    }
    ?>
    <div class="grid">
      <form method="post" class="card span-5 form-grid">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="add">
        <label>Type<select name="offering_type"><option value="product">Product/Input</option><option value="service">Service</option><option value="training">Training Program</option></select></label>
        <label>Category<input name="category" placeholder="Seedlings, Fertilizer, Agronomy, Training"></label>
        <label class="wide">Name<input name="name" required></label>
        <label>Price<input type="number" min="0" step="0.01" name="price"></label>
        <label>Unit<input name="unit" placeholder="bag, seedling, hectare, seat"></label>
        <label>Minimum Order<input name="minimum_order"></label>
        <label>Lead Time<input name="lead_time"></label>
        <label>Stock Status<select name="stock_status"><option value="available">Available</option><option value="limited">Limited</option><option value="out_of_stock">Out of Stock</option><option value="scheduled">Scheduled</option></select></label>
        <label class="wide">Availability<input name="availability" placeholder="Available on request"></label>
        <label class="wide">Coverage Area<textarea name="coverage_area"><?= e((string) $provider['states_served']) ?></textarea></label>
        <label class="wide">Description<textarea name="description"></textarea></label>
        <label class="wide"><input type="checkbox" name="requires_quote" checked style="width:auto"> Requires quote before fulfillment</label>
        <div class="wide"><button class="btn">Add Offering</button></div>
      </form>
      <section class="card span-7"><div class="card-head"><h2>Current Listings</h2><a class="view" href="../market/seller-central.php">Seller Central</a></div><div class="list">
        <?php foreach ($offerings as $offering): ?><div class="row"><span><strong><?= e((string) $offering['name']) ?></strong><br><small><?= e(provider_status_label((string) $offering['offering_type'])) ?> / <?= e((string) $offering['category']) ?></small></span><span><span class="badge"><?= e(provider_status_label((string) $offering['stock_status'])) ?></span><form method="post" style="display:inline"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="remove"><input type="hidden" name="offering_id" value="<?= (int) $offering['id'] ?>"><button class="btn light" style="padding:6px 9px">Remove</button></form></span></div><?php endforeach; ?>
        <?php if (!$offerings): ?><p>No products, services, or training programs yet.</p><?php endif; ?>
      </div></section>
    </div>
<?php });
