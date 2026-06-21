<?php
declare(strict_types=1);
require_once __DIR__ . '/_seller.php';
$pdo = market_boot(); $user = market_require_user($pdo); seller_access_or_message($pdo, $user);
$ctx = seller_query_context($pdo, $user, true); $seller = $ctx['seller']; $edit = $ctx['editListing']; $categories = $ctx['categories']; $listingTypes = $ctx['listingTypes'];
seller_header($edit ? 'Edit Product' : 'Add Product', 'add', $user, $seller);
if ($ctx['message']): ?><div class="alert ok"><?= e($ctx['message']) ?></div><?php endif; if ($ctx['error']): ?><div class="alert err"><?= e($ctx['error']) ?></div><?php endif; ?>
<section class="sc-card sc-panel">
  <div class="sc-panel-head"><h2><?= $edit ? 'Edit Marketplace Listing' : 'Create Marketplace Listing' ?></h2><a class="sc-btn secondary" href="seller-products.php">Back to Products</a></div>
  <?php if (!$seller): ?><div class="empty">Create your seller profile before adding products. <a href="seller-settings.php">Open settings</a></div><?php else: ?>
  <form method="post" enctype="multipart/form-data" class="sc-form sc-form-grid">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save_listing"><input type="hidden" name="listing_id" value="<?= (int) ($edit['id'] ?? 0) ?>">
    <div class="wide"><label>Listing Name *</label><input name="title" value="<?= e((string) ($edit['title'] ?? '')) ?>" required placeholder="Dwarf coconut seedlings, organic fertilizer, planting service..."></div>
    <div><label>Status</label><select name="availability_status"><?php foreach (['available'=>'Available','limited'=>'Limited','out_of_stock'=>'Out of stock','paused'=>'Paused'] as $value=>$label): ?><option value="<?= e($value) ?>" <?= (string) ($edit['availability_status'] ?? 'available') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
    <div><label>Type</label><select name="listing_type"><?php foreach ($listingTypes as $value => $label): ?><option value="<?= e($value) ?>" <?= (string) ($edit['listing_type'] ?? 'product') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
    <div><label>Category</label><select name="category_id"><option value="">Auto by type</option><?php foreach ($categories as $category): ?><option value="<?= (int) $category['id'] ?>" <?= (int) ($edit['category_id'] ?? 0) === (int) $category['id'] ? 'selected' : '' ?>><?= e((string) $category['name']) ?></option><?php endforeach; ?></select></div>
    <div><label>Price</label><input type="number" step="0.01" name="price" value="<?= e((string) ($edit['price'] ?? '0')) ?>"></div>
    <div><label>Price Unit</label><input name="price_unit" value="<?= e((string) ($edit['price_unit'] ?? 'per unit')) ?>"></div>
    <div><label>Quantity Available</label><input type="number" step="0.01" name="quantity_available" value="<?= e((string) ($edit['quantity_available'] ?? '')) ?>"></div>
    <div><label>Unit</label><input name="unit" value="<?= e((string) ($edit['unit'] ?? '')) ?>" placeholder="bag, piece, hectare, day"></div>
    <div><label>Minimum Order</label><input type="number" step="0.01" name="min_order_quantity" value="<?= e((string) ($edit['min_order_quantity'] ?? '')) ?>"></div>
    <div><label>Location</label><input name="location_label" value="<?= e((string) ($edit['location_label'] ?? '')) ?>"></div>
    <div class="wide"><label>Buyer Summary</label><input name="summary" value="<?= e((string) ($edit['summary'] ?? '')) ?>" placeholder="Short marketplace card description"></div>
    <div class="wide"><label>Full Description</label><textarea name="description"><?= e((string) ($edit['description'] ?? '')) ?></textarea></div>
    <div class="wide"><label>Product/Service Image</label><?php if (!empty($edit['image_path'])): ?><p><img src="../<?= e((string) $edit['image_path']) ?>" style="max-width:260px;border-radius:12px;border:1px solid var(--line)" alt=""></p><?php endif; ?><input type="file" name="listing_image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"></div>
    <div><label>GTIN</label><input name="gtin" value="<?= e((string) ($edit['gtin'] ?? '')) ?>"></div><div><label>GTIN Type</label><input name="gtin_type" value="<?= e((string) ($edit['gtin_type'] ?? '')) ?>"></div>
    <div><label>MPN</label><input name="mpn" value="<?= e((string) ($edit['mpn'] ?? '')) ?>"></div><div><label>Origin Country</label><input name="origin_country" value="<?= e((string) ($edit['origin_country'] ?? 'Nigeria')) ?>"></div>
    <div><label>Manufacturer</label><input name="manufacturer" value="<?= e((string) ($edit['manufacturer'] ?? '')) ?>"></div><div><label>Brand</label><input name="brand" value="<?= e((string) ($edit['brand'] ?? '')) ?>"></div>
    <div><label>Model Number</label><input name="model_number" value="<?= e((string) ($edit['model_number'] ?? '')) ?>"></div><div><label>Tags</label><input name="tags" value="<?= e((string) ($edit['tags'] ?? '')) ?>"></div>
    <div class="wide"><label>Fulfillment Method</label><input name="fulfillment_method" value="<?= e((string) ($edit['fulfillment_method'] ?? '')) ?>"></div>
    <label><input type="checkbox" name="requires_shipping" <?= (int) ($edit['requires_shipping'] ?? 1) === 1 ? 'checked' : '' ?>> Requires shipping</label><label><input type="checkbox" name="downloadable" <?= (int) ($edit['downloadable'] ?? 0) === 1 ? 'checked' : '' ?>> Downloadable</label>
    <div class="wide"><button class="sc-btn" type="submit"><?= $edit ? 'Save Listing for Review' : 'Submit Listing for Review' ?></button></div>
  </form>
  <?php endif; ?>
</section>
<?php seller_footer(); ?>
