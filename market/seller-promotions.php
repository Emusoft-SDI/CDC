<?php
declare(strict_types=1);
require_once __DIR__ . '/_seller.php';
$pdo = market_boot(); $user = market_require_user($pdo); seller_access_or_message($pdo, $user);
$ctx = seller_query_context($pdo, $user, false);
$seller = $ctx['seller'];
$message = '';
$error = '';
$packages = [
    'homepage_banner' => ['Homepage Banner', 35000.0, 30],
    'homepage_ad' => ['Sponsored Vendor Ad', 20000.0, 30],
    'featured_product' => ['Featured Product Boost', 15000.0, 14],
    'featured_seller' => ['Featured Seller Placement', 50000.0, 30],
    'checkout_upsell' => ['Checkout Upsell Placement', 25000.0, 21],
];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['_csrf'] ?? null) && $seller) {
    $placement = (string) ($_POST['placement'] ?? 'homepage_ad');
    $package = $packages[$placement] ?? $packages['homepage_ad'];
    $amount = max(1000, (float) ($_POST['amount'] ?? $package[1]));
    $duration = max(1, min(365, (int) ($_POST['duration_days'] ?? $package[2])));
    $listingId = (int) ($_POST['listing_id'] ?? 0) ?: null;
    $title = trim((string) ($_POST['title'] ?? $package[0]));
    $subtitle = trim((string) ($_POST['subtitle'] ?? 'Sponsored NATCODEV marketplace placement.'));
    $paymentMethod = (string) ($_POST['payment_method'] ?? 'wallet');
    try {
        $wallet = wallet_get_or_create($pdo, (int) $user['id']);
        $promoRef = marketplace_promo_ref();
        $imageIndex = random_int(1, 10);
        $imagePath = 'assets/market/featured/vendor-ad-' . str_pad((string) $imageIndex, 2, '0', STR_PAD_LEFT) . '.png';
        $targetUrl = $listingId ? 'product.php?id=' . $listingId : 'store.php?seller=' . rawurlencode((string) $seller['slug']);
        if ($paymentMethod === 'wallet') {
            $pdo->beginTransaction();
            $lock = $pdo->prepare("SELECT * FROM wallets WHERE id = ? FOR UPDATE");
            $lock->execute([(int) $wallet['id']]);
            $wallet = $lock->fetch();
            $before = (float) ($wallet['balance'] ?? 0);
            if ($before + 0.01 < $amount) {
                throw new RuntimeException('Insufficient wallet balance. Fund your wallet with Monnify, then retry this promotion.');
            }
            $after = $before - $amount;
            $pdo->prepare("UPDATE wallets SET balance = ? WHERE id = ?")->execute([$after, (int) $wallet['id']]);
            $pdo->prepare("
                INSERT INTO wallet_transactions
                    (wallet_id, user_id, amount, type, direction, description, reference, provider, provider_reference, provider_payload, status, balance_before, balance_after, completed_at)
                VALUES (?, ?, ?, 'debit', 'outflow', ?, ?, 'marketplace_ads', ?, ?, 'completed', ?, ?, NOW())
            ")->execute([(int) $wallet['id'], (int) $user['id'], $amount, 'Marketplace promotion purchase: ' . $package[0], $promoRef, $promoRef, json_encode(['placement' => $placement, 'duration_days' => $duration], JSON_UNESCAPED_SLASHES), $before, $after]);
            $pdo->prepare("
                INSERT INTO marketplace_promotions
                    (promo_ref, seller_id, listing_id, title, subtitle, placement, image_path, target_url, amount, duration_days, payment_method, payment_reference, status, starts_at, ends_at, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'wallet', ?, 'active', NOW(), DATE_ADD(NOW(), INTERVAL {$duration} DAY), ?)
            ")->execute([$promoRef, (int) $seller['id'], $listingId, $title, $subtitle, $placement, $imagePath, $targetUrl, $amount, $duration, $promoRef, (int) $user['id']]);
            $pdo->commit();
            $message = 'Promotion activated. Wallet debited and placement is live.';
        } else {
            $pdo->prepare("
                INSERT INTO marketplace_promotions
                    (promo_ref, seller_id, listing_id, title, subtitle, placement, image_path, target_url, amount, duration_days, payment_method, payment_reference, status, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'monnify_wallet_funding', ?, 'pending_payment', ?)
            ")->execute([$promoRef, (int) $seller['id'], $listingId, $title, $subtitle, $placement, $imagePath, $targetUrl, $amount, $duration, $promoRef, (int) $user['id']]);
            redirect_to('../dashboard/wallet.php?amount=' . rawurlencode((string) $amount));
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e instanceof RuntimeException ? $e->getMessage() : 'Unable to create promotion.';
    }
}
$ctx = seller_query_context($pdo, $user, false);
$seller = $ctx['seller'];
$listings = $ctx['listings'];
$promoStmt = $seller ? $pdo->prepare("SELECT * FROM marketplace_promotions WHERE seller_id = ? ORDER BY created_at DESC LIMIT 30") : null;
$promotions = [];
if ($promoStmt) {
    $promoStmt->execute([(int) $seller['id']]);
    $promotions = $promoStmt->fetchAll();
}
seller_header('Promotions', 'promotions', $user, $seller);
if ($message): ?><div class="alert ok"><?= e($message) ?></div><?php endif; if ($error): ?><div class="alert err"><?= e($error) ?></div><?php endif;
?>
<section class="sc-grid">
  <article class="sc-card sc-panel span-7"><div class="sc-panel-head"><h2>Promotion Builder</h2><span class="badge good">Wallet activation supported</span></div><form method="post" class="sc-form sc-form-grid"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><div><label>Promotion Name</label><input name="title" placeholder="Rainy season seedling boost" required></div><div><label>Placement</label><select name="placement" id="placementSelect"><?php foreach ($packages as $key => $package): ?><option value="<?= e($key) ?>" data-amount="<?= e((string) $package[1]) ?>" data-days="<?= (int) $package[2] ?>"><?= e($package[0]) ?> - <?= e(marketplace_money((float) $package[1])) ?></option><?php endforeach; ?></select></div><div><label>Duration Days</label><input id="durationInput" name="duration_days" type="number" value="30" min="1" max="365"></div><div><label>Budget</label><input id="amountInput" name="amount" type="number" step="0.01" value="20000"></div><div class="wide"><label>Product / Service</label><select name="listing_id"><option value="">Promote my storefront</option><?php foreach ($listings as $item): ?><option value="<?= (int) $item['id'] ?>"><?= e((string) $item['title']) ?></option><?php endforeach; ?></select></div><div class="wide"><label>Message</label><input name="subtitle" placeholder="Short message buyers will see on the ad"></div><div><label>Payment</label><select name="payment_method"><option value="wallet">Pay from wallet now</option><option value="monnify">Fund wallet with Monnify first</option></select></div><div class="wide"><button class="sc-btn" type="submit">Activate / Request Promotion</button></div></form></article>
  <aside class="sc-card sc-panel span-5"><div class="sc-panel-head"><h2>Available Placements</h2></div><div class="sc-list"><div class="sc-row"><span class="sc-icon orange"><i data-lucide="sparkles"></i></span><div><strong>Featured Product</strong><br><span class="muted">Public marketplace product tiles.</span></div></div><div class="sc-row"><span class="sc-icon"><i data-lucide="store"></i></span><div><strong>Featured Seller</strong><br><span class="muted">Promoted seller placement.</span></div></div><div class="sc-row"><span class="sc-icon blue"><i data-lucide="shopping-cart"></i></span><div><strong>Checkout Upsell</strong><br><span class="muted">Relevant product recommendations.</span></div></div></div></aside>
</section>
<section class="sc-card sc-panel">
  <div class="sc-panel-head"><h2>My Ad Performance</h2><a class="sc-link" href="seller-reports.php">View seller reports</a></div>
  <table class="sc-table"><thead><tr><th>Ad</th><th>Placement</th><th>Status</th><th>Dates</th><th>Impressions</th><th>Clicks</th><th>CTR</th></tr></thead><tbody>
    <?php foreach ($promotions as $promo): $ctr = (int) $promo['impressions'] > 0 ? ((int) $promo['clicks'] / (int) $promo['impressions']) * 100 : 0; ?>
      <tr><td><strong><?= e((string) $promo['title']) ?></strong><br><span class="muted"><?= e((string) $promo['promo_ref']) ?></span></td><td><?= e(marketplace_status_label((string) $promo['placement'])) ?></td><td><span class="badge <?= (string) $promo['status'] === 'active' ? 'good' : 'warn' ?>"><?= e(marketplace_status_label((string) $promo['status'])) ?></span></td><td><?= e((string) ($promo['starts_at'] ?? 'Pending')) ?><br><?= e((string) ($promo['ends_at'] ?? 'Pending')) ?></td><td><?= number_format((int) $promo['impressions']) ?></td><td><?= number_format((int) $promo['clicks']) ?></td><td><?= e(number_format($ctr, 2)) ?>%</td></tr>
    <?php endforeach; ?>
    <?php if (!$promotions): ?><tr><td colspan="7">No seller promotion yet.</td></tr><?php endif; ?>
  </tbody></table>
</section>
<script>
const placementSelect = document.getElementById('placementSelect');
placementSelect?.addEventListener('change', () => {
  const option = placementSelect.options[placementSelect.selectedIndex];
  document.getElementById('amountInput').value = option.dataset.amount || '20000';
  document.getElementById('durationInput').value = option.dataset.days || '30';
});
placementSelect?.dispatchEvent(new Event('change'));
</script>
<?php seller_footer(); ?>
