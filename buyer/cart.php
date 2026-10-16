<?php
declare(strict_types=1);
require_once __DIR__ . '/_buyer.php';

$pdo = buyer_boot();
$user = buyer_user($pdo);
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user && verify_csrf($_POST['_csrf'] ?? null)) {
    $action = (string) ($_POST['action'] ?? '');
    $listingId = (int) ($_POST['listing_id'] ?? 0);
    if ($action === 'restore_saved') {
        $restored = market_saved_restore($pdo, $user, $listingId > 0 ? $listingId : null);
        $message = $restored > 0 ? ($listingId > 0 ? 'Item moved to cart.' : $restored . ' saved item(s) moved to cart.') : 'No saved items could be restored.';
    } elseif ($action === 'remove_saved') {
        market_saved_remove($pdo, $user, $listingId);
        $message = 'Item removed from saved list.';
    } elseif ($action === 'clear_saved') {
        market_saved_clear($pdo, $user);
        $message = 'Saved items cleared.';
    }
}

buyer_simple_page('cart', 'Buyer Cart', 'Review your marketplace cart and saved items, then proceed to secure checkout.', function (PDO $pdo, ?array $user) use ($message): void {
    $rows = market_cart_rows($pdo);
    $savedRows = $user ? market_saved_rows($pdo, $user) : [];
    $savedAvailable = array_values(array_filter($savedRows, static fn(array $row): bool => $row['purchasable']));
    $savedUnavailable = array_values(array_filter($savedRows, static fn(array $row): bool => !$row['purchasable']));
    $savedValue = array_sum(array_map(static fn(array $row): float => (float) $row['saved_total'], $savedAvailable));
    $csrf = e(csrf_token());
    ?>
    <?php if ($message): ?>
        <div class="mk-alert ok" style="margin-bottom:16px"><?= e($message) ?></div>
    <?php endif; ?>
    <div class="card">
        <div class="card-head"><h2>Cart Items</h2><a class="btn" href="../market/cart.php">Open Cart</a></div>
        <?php if (!$rows): ?>
            <p>Your cart is empty.</p>
            <a class="btn light" href="../market/index.php">Start Shopping</a>
        <?php endif; ?>
        <?php foreach ($rows as $row): ?>
            <div class="row"><span style="display:flex;gap:10px;align-items:center"><img class="thumb" src="<?= e(market_listing_image_url($row)) ?>" alt=""><span><strong><?= e((string) $row['title']) ?></strong><br><small>Qty: <?= (int) $row['cart_quantity'] ?></small></span></span><strong><?= e(marketplace_money((float) $row['cart_total'])) ?></strong></div>
        <?php endforeach; ?>
        <?php if ($rows && $user): ?>
            <form method="post" style="margin-top:12px">
                <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                <input type="hidden" name="action" value="save_for_later">
                <button class="btn secondary" type="submit"><i class="far fa-heart"></i> Save Cart for Later</button>
            </form>
        <?php endif; ?>
    </div>
    <div class="card" id="saved" style="margin-top:16px">
        <div class="card-head">
            <h2><i class="far fa-heart" style="color:#dc2626"></i> Saved for Later<?php if ($savedRows): ?> (<?= count($savedRows) ?>)<?php endif; ?></h2>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <?php if ($savedAvailable && $user): ?>
                    <form method="post"><input type="hidden" name="_csrf" value="<?= $csrf ?>"><input type="hidden" name="action" value="restore_saved"><button class="btn" type="submit"><i class="fas fa-shopping-cart"></i> Move All to Cart</button></form>
                <?php endif; ?>
                <?php if ($savedRows && $user): ?>
                    <form method="post"><input type="hidden" name="_csrf" value="<?= $csrf ?>"><input type="hidden" name="action" value="clear_saved"><button class="btn secondary" type="submit"><i class="fas fa-trash"></i> Clear Saved</button></form>
                <?php endif; ?>
            </div>
        </div>
        <?php if (!$savedRows): ?>
            <p>No saved items yet. Save items from your cart to buy them later.</p>
        <?php endif; ?>
        <?php foreach ($savedAvailable as $srow): ?>
            <div class="row">
                <span style="display:flex;gap:10px;align-items:center">
                    <a href="../market/product.php?id=<?= (int) $srow['listing_id'] ?>"><img class="thumb" src="<?= e(market_listing_image_url($srow)) ?>" alt=""></a>
                    <span><strong><?= e((string) $srow['title']) ?></strong><br><small>Qty: <?= (int) $srow['saved_quantity'] ?> &middot; Seller: <?= e((string) $srow['store_name']) ?></small></span>
                </span>
                <span style="display:flex;gap:8px;align-items:center">
                    <strong><?= e(marketplace_money((float) $srow['saved_total'])) ?></strong>
                    <?php if ($user): ?>
                        <form method="post"><input type="hidden" name="_csrf" value="<?= $csrf ?>"><input type="hidden" name="action" value="restore_saved"><input type="hidden" name="listing_id" value="<?= (int) $srow['listing_id'] ?>"><button class="btn light" type="submit">Move to Cart</button></form>
                        <form method="post"><input type="hidden" name="_csrf" value="<?= $csrf ?>"><input type="hidden" name="action" value="remove_saved"><input type="hidden" name="listing_id" value="<?= (int) $srow['listing_id'] ?>"><button class="btn light" type="submit" title="Remove"><i class="fas fa-trash"></i></button></form>
                    <?php endif; ?>
                </span>
            </div>
        <?php endforeach; ?>
        <?php foreach ($savedUnavailable as $srow): ?>
            <div class="row" style="opacity:.6">
                <span style="display:flex;gap:10px;align-items:center">
                    <span><strong><?= e((string) ($srow['title'] ?: 'Listing #' . $srow['listing_id'])) ?></strong><br><small>No longer available</small></span>
                </span>
                <span>
                    <?php if ($user): ?>
                        <form method="post"><input type="hidden" name="_csrf" value="<?= $csrf ?>"><input type="hidden" name="action" value="remove_saved"><input type="hidden" name="listing_id" value="<?= (int) $srow['listing_id'] ?>"><button class="btn light" type="submit"><i class="fas fa-trash"></i> Remove</button></form>
                    <?php endif; ?>
                </span>
            </div>
        <?php endforeach; ?>
        <?php if ($savedAvailable): ?>
            <p style="margin-top:12px"><strong>Estimated value: <?= e(marketplace_money($savedValue)) ?></strong></p>
        <?php endif; ?>
    </div>
<?php });
?>
