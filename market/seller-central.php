<?php
declare(strict_types=1);
require_once __DIR__ . '/_seller.php';
require_once __DIR__ . '/../lib/support.php';

$pdo = market_boot();
wallet_ensure_schema($pdo);
support_ensure_schema($pdo);
$user = market_require_user($pdo);
seller_access_or_message($pdo, $user);
$centralMessage = '';
$centralError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['_csrf'] ?? null)) {
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'central_withdrawal') {
            $withdrawal = wallet_request_withdrawal($pdo, $user, [
                'amount' => $_POST['withdraw_amount'] ?? 0,
                'provider' => $_POST['withdraw_provider'] ?? 'monnify',
                'bank_code' => $_POST['bank_code'] ?? '',
                'bank_name' => $_POST['bank_name'] ?? '',
                'account_number' => $_POST['account_number'] ?? '',
                'account_name' => $_POST['account_name'] ?? '',
                'note' => $_POST['withdraw_note'] ?? 'Seller Central withdrawal',
            ]);
            if (empty($withdrawal['success'])) {
                throw new RuntimeException((string) ($withdrawal['error'] ?? 'Unable to submit withdrawal.'));
            }
            $centralMessage = 'Withdrawal request submitted. Reference: ' . (string) $withdrawal['reference'] . '. ' . (string) ($withdrawal['review_reason'] ?? '');
        }
        if ($action === 'central_support') {
            $ref = support_create_ticket($pdo, [
                'category' => (string) ($_POST['category'] ?? 'marketplace'),
                'priority' => (string) ($_POST['priority'] ?? 'medium'),
                'name' => (string) ($user['name'] ?? ''),
                'email' => (string) ($user['email'] ?? ''),
                'phone' => (string) ($user['phone'] ?? ''),
                'subject' => trim((string) ($_POST['subject'] ?? 'Seller Central support request')),
                'description' => trim((string) ($_POST['description'] ?? '')),
                'linked_record_type' => trim((string) ($_POST['linked_record_type'] ?? 'marketplace_seller')),
                'linked_record_ref' => trim((string) ($_POST['linked_record_ref'] ?? 'Seller Central')),
            ], $user);
            $centralMessage = 'Support ticket ' . $ref . ' has been opened.';
        }
    } catch (Throwable $e) {
        $centralError = $e instanceof RuntimeException ? $e->getMessage() : 'Unable to complete Seller Central action.';
    }
}
$ctx = seller_query_context($pdo, $user, true);
$seller = $ctx['seller'];
$userId = (int) $user['id'];
$wallet = wallet_get_or_create($pdo, $userId);
$wdStmt = $pdo->prepare("SELECT * FROM wallet_withdrawals WHERE wallet_id = ? ORDER BY requested_at DESC LIMIT 5");
$wdStmt->execute([(int) $wallet['id']]);
$sellerWithdrawals = $wdStmt->fetchAll();
$txStmt = $pdo->prepare("SELECT * FROM wallet_transactions WHERE wallet_id = ? ORDER BY created_at DESC LIMIT 5");
$txStmt->execute([(int) $wallet['id']]);
$sellerWalletRows = $txStmt->fetchAll();
$supportTickets = support_user_tickets($pdo, $userId);
$openSupport = count(array_filter($supportTickets, static fn(array $ticket): bool => !in_array((string) $ticket['status'], ['resolved', 'closed', 'rejected'], true)));
$paidUnsettledOrders = array_filter($ctx['orders'], static fn(array $row): bool => (string) ($row['payment_status'] ?? '') === 'paid' && empty($row['settled_at']));
$paidUnsettled = array_sum(array_map(static fn(array $row): float => (float) $row['total_amount'], $paidUnsettledOrders));
$lowStockCount = count(array_filter($ctx['listings'], static fn(array $row): bool => isset($row['quantity_available']) && (float) $row['quantity_available'] <= 5));
$totalRevenue = array_sum(array_map(static fn(array $row): float => in_array((string) $row['status'], ['cancelled'], true) ? 0.0 : (float) $row['total_amount'], $ctx['orders']));
$completedRevenue = array_sum(array_map(static fn(array $row): float => (string) $row['status'] === 'completed' ? (float) $row['total_amount'] : 0.0, $ctx['orders']));
$totalUnitsSold = array_sum(array_map(static fn(array $row): float => in_array((string) $row['status'], ['cancelled'], true) ? 0.0 : (float) ($row['quantity'] ?? 0), $ctx['orders']));
$completedOrders = count(array_filter($ctx['orders'], static fn(array $row): bool => (string) $row['status'] === 'completed'));
$cancelledOrders = count(array_filter($ctx['orders'], static fn(array $row): bool => (string) $row['status'] === 'cancelled'));
$disputedOrders = count(array_filter($ctx['orders'], static fn(array $row): bool => (string) $row['status'] === 'disputed'));
$paidOrders = count(array_filter($ctx['orders'], static fn(array $row): bool => (string) ($row['payment_status'] ?? '') === 'paid'));
$unpaidOrders = count(array_filter($ctx['orders'], static fn(array $row): bool => !in_array((string) ($row['payment_status'] ?? ''), ['paid', 'successful'], true)));
$readyOrders = count(array_filter($ctx['orders'], static fn(array $row): bool => in_array((string) ($row['delivery_status'] ?? ''), ['ready_for_pickup', 'ready'], true) || in_array((string) $row['status'], ['ready', 'scheduled'], true)));
$inTransitOrders = count(array_filter($ctx['orders'], static fn(array $row): bool => in_array((string) ($row['delivery_status'] ?? ''), ['in_transit'], true) || (string) $row['status'] === 'in_transit'));
$uniqueBuyers = count(array_unique(array_filter(array_map(static fn(array $row): string => strtolower(trim((string) ($row['buyer_email'] ?: $row['buyer_name']))), $ctx['orders']))));
$inquiryCount = count($ctx['inquiries']);
$orderCount = count($ctx['orders']);
$conversionRate = $inquiryCount > 0 ? round(($orderCount / max(1, $inquiryCount)) * 100, 1) : ($orderCount > 0 ? 100.0 : 0.0);
$approvedListings = count(array_filter($ctx['listings'], static fn(array $row): bool => (string) $row['approval_status'] === 'approved'));
$pendingListings = count(array_filter($ctx['listings'], static fn(array $row): bool => (string) $row['approval_status'] === 'pending'));
$outOfStockListings = count(array_filter($ctx['listings'], static fn(array $row): bool => (string) $row['availability_status'] === 'out_of_stock'));
$adminStatus = $seller ? marketplace_status_label((string) $seller['approval_status']) : 'Store setup needed';
$verificationStatus = $seller ? marketplace_status_label((string) ($seller['verification_status'] ?? 'pending')) : 'Pending';
$healthScore = 45 + ($seller ? 15 : 0) + ($approvedListings > 0 ? 15 : 0) + ($lowStockCount === 0 ? 10 : 0) + ($openSupport === 0 ? 10 : 0) + ($disputedOrders === 0 ? 5 : 0);
$healthScore = max(0, min(100, $healthScore));
$productPerformance = [];
foreach ($ctx['listings'] as $listing) {
    $productPerformance[(int) $listing['id']] = ['listing' => $listing, 'orders' => 0, 'units' => 0.0, 'revenue' => 0.0, 'pending_payout' => 0.0];
}
foreach ($ctx['orders'] as $order) {
    $listingId = (int) ($order['listing_id'] ?? 0);
    if (!isset($productPerformance[$listingId])) {
        continue;
    }
    if ((string) $order['status'] !== 'cancelled') {
        $productPerformance[$listingId]['orders']++;
        $productPerformance[$listingId]['units'] += (float) ($order['quantity'] ?? 0);
        $productPerformance[$listingId]['revenue'] += (float) $order['total_amount'];
    }
    if ((string) ($order['payment_status'] ?? '') === 'paid' && empty($order['settled_at'])) {
        $productPerformance[$listingId]['pending_payout'] += (float) $order['total_amount'];
    }
}
usort($productPerformance, static fn(array $a, array $b): int => $b['revenue'] <=> $a['revenue']);
$sellerPromotions = [];
if ($seller) {
    $promoStmt = $pdo->prepare("SELECT p.*,l.title listing_title FROM marketplace_promotions p LEFT JOIN marketplace_listings l ON l.id=p.listing_id WHERE p.seller_id = ? ORDER BY p.created_at DESC LIMIT 20");
    $promoStmt->execute([(int) $seller['id']]);
    $sellerPromotions = $promoStmt->fetchAll();
}
$pendingPromotionCount = count(array_filter($sellerPromotions, static fn(array $row): bool => in_array((string) $row['status'], ['pending_admin_review', 'pending_payment'], true)));
$activePromotionCount = count(array_filter($sellerPromotions, static fn(array $row): bool => (string) $row['status'] === 'active'));
$pausedPromotionCount = count(array_filter($sellerPromotions, static fn(array $row): bool => (string) $row['status'] === 'paused'));
$rejectedPromotionCount = count(array_filter($sellerPromotions, static fn(array $row): bool => (string) $row['status'] === 'rejected'));
$pendingPromotionSpend = array_sum(array_map(static fn(array $row): float => in_array((string) $row['status'], ['pending_admin_review', 'pending_payment'], true) ? (float) $row['amount'] : 0.0, $sellerPromotions));
$activePromotionSpend = array_sum(array_map(static fn(array $row): float => (string) $row['status'] === 'active' ? (float) $row['amount'] : 0.0, $sellerPromotions));
$promotionImpressions = array_sum(array_map(static fn(array $row): int => (int) ($row['impressions'] ?? 0), $sellerPromotions));
$promotionClicks = array_sum(array_map(static fn(array $row): int => (int) ($row['clicks'] ?? 0), $sellerPromotions));
$promotionCtr = $promotionImpressions > 0 ? round(($promotionClicks / max(1, $promotionImpressions)) * 100, 2) : 0.0;
$governanceActions = [];
if (!$seller) { $governanceActions[] = ['warn', 'Store profile not created', 'Create seller settings so administrators can approve your marketplace store.']; }
if ($seller && (string) $seller['approval_status'] !== 'approved') { $governanceActions[] = ['warn', 'Store awaiting admin approval', 'Administrators control marketplace visibility and can approve or request corrections.']; }
if ($pendingListings > 0) { $governanceActions[] = ['warn', 'Product approvals pending', $pendingListings . ' product(s) need administrator review before full public visibility.']; }
if ($pendingPromotionCount > 0) { $governanceActions[] = ['warn', 'Promotion approvals pending', $pendingPromotionCount . ' paid promotion request(s) are waiting for administrator approval before going live.']; }
if ($paidUnsettled > 0) { $governanceActions[] = ['good', 'Payout pending release', marketplace_money((float) $paidUnsettled) . ' is waiting for settlement or withdrawal.']; }
if ($disputedOrders > 0) { $governanceActions[] = ['danger', 'Disputes require attention', $disputedOrders . ' disputed order(s) need seller/admin handling.']; }
if (!$governanceActions) { $governanceActions[] = ['good', 'Store governance clear', 'No urgent admin-controlled seller issue is currently visible.']; }

seller_header('Seller Central', 'overview', $user, $seller);
if ($ctx['message']): ?><div class="alert ok"><?= e($ctx['message']) ?></div><?php endif;
if ($centralMessage): ?><div class="alert ok"><?= e($centralMessage) ?></div><?php endif;
if ($ctx['error']): ?><div class="alert err"><?= e($ctx['error']) ?></div><?php endif;
if ($centralError): ?><div class="alert err"><?= e($centralError) ?></div><?php endif;
seller_kpis($ctx);
$listings = $ctx['listings'];
$orders = $ctx['orders'];
$inquiries = $ctx['inquiries'];
?>
<section class="sc-grid">
  <article class="sc-card sc-panel span-5">
    <div class="sc-panel-head"><h2>Sales Overview</h2><a class="sc-link" href="seller-reports.php">View report</a></div>
    <div style="height:210px;display:grid;align-items:end;grid-template-columns:repeat(7,1fr);gap:10px;padding:20px 8px 4px">
      <?php foreach ([58,36,45,72,51,62,78] as $i => $height): ?><div title="Day <?= $i + 1 ?>" style="height:<?= $height ?>%;border-radius:8px 8px 0 0;background:linear-gradient(180deg,#0f8f4b,#dff7e8)"></div><?php endforeach; ?>
    </div>
  </article>
  <article class="sc-card sc-panel span-5">
    <div class="sc-panel-head"><h2>Latest Orders</h2><a class="sc-link" href="seller-orders.php">View All Orders</a></div>
    <table class="sc-table"><thead><tr><th>Order</th><th>Buyer</th><th>Amount</th><th>Status</th></tr></thead><tbody>
    <?php foreach (array_slice($orders, 0, 5) as $order): ?><tr><td><?= e((string) $order['order_ref']) ?></td><td><?= e((string) $order['buyer_name']) ?></td><td><?= e(marketplace_money((float) $order['total_amount'])) ?></td><td><span class="badge <?= (string) $order['status'] === 'completed' ? 'good' : 'blue' ?>"><?= e(marketplace_status_label((string) $order['status'])) ?></span></td></tr><?php endforeach; ?>
    <?php if (!$orders): ?><tr><td colspan="4">No orders yet.</td></tr><?php endif; ?>
    </tbody></table>
  </article>
  <aside class="sc-card sc-panel span-2">
    <div class="sc-panel-head"><h2>Storefront Status</h2><a class="sc-link" href="<?= $seller ? 'store.php?seller=' . e((string) $seller['slug']) : 'seller-settings.php' ?>">View</a></div>
    <img class="thumb" style="width:86px;height:86px;border-radius:14px" src="<?= e(seller_avatar($seller, $user)) ?>" alt="">
    <p><strong><?= e((string) ($seller['store_name'] ?? 'Store setup needed')) ?></strong><br><span class="muted"><?= $seller ? e(marketplace_status_label((string) $seller['approval_status'])) : 'Not created' ?></span></p>
    <p><span class="badge <?= $healthScore >= 75 ? 'good' : ($healthScore >= 55 ? 'warn' : 'danger') ?>">Store Health <?= (int) $healthScore ?>%</span></p>
    <p class="muted">Admin status: <?= e($adminStatus) ?><br>Verification: <?= e($verificationStatus) ?></p>
  </aside>

  <article class="sc-card sc-panel span-3">
    <div class="sc-panel-head"><h2>Product Listing Health</h2><a class="sc-link" href="seller-products.php">View Products</a></div>
    <div class="sc-list">
      <div class="sc-row"><span class="sc-icon"><i data-lucide="check"></i></span><div>Active Listings</div><b><?= count(array_filter($listings, static fn(array $r): bool => (string) $r['approval_status'] === 'approved')) ?></b></div>
      <div class="sc-row"><span class="sc-icon orange"><i data-lucide="clock"></i></span><div>Pending Approval</div><b><?= count(array_filter($listings, static fn(array $r): bool => (string) $r['approval_status'] === 'pending')) ?></b></div>
      <div class="sc-row"><span class="sc-icon red"><i data-lucide="circle-alert"></i></span><div>Out of Stock</div><b><?= count(array_filter($listings, static fn(array $r): bool => (string) $r['availability_status'] === 'out_of_stock')) ?></b></div>
    </div>
  </article>
  <article class="sc-card sc-panel span-3">
    <div class="sc-panel-head"><h2>Low Stock Alerts</h2><a class="sc-link" href="seller-inventory.php">View All</a></div>
    <div class="sc-list"><?php foreach (array_slice($listings, 0, 5) as $item): ?><div class="sc-row"><img class="thumb" src="<?= e(market_listing_image_url($item)) ?>" alt=""><div><strong><?= e((string) $item['title']) ?></strong><br><span class="muted"><?= e((string) ($item['unit'] ?: 'units')) ?></span></div><span class="badge warn"><?= e((string) ($item['quantity_available'] ?? 'Ask')) ?></span></div><?php endforeach; ?><?php if (!$listings): ?><div class="empty">No product yet.</div><?php endif; ?></div>
  </article>
  <article class="sc-card sc-panel span-3">
    <div class="sc-panel-head"><h2>Top Selling Items</h2><a class="sc-link" href="seller-reports.php">View Report</a></div>
    <div class="sc-list"><?php foreach (array_slice($productPerformance, 0, 5) as $row): $item=$row['listing']; ?><div class="sc-row"><img class="thumb" src="<?= e(market_listing_image_url($item)) ?>" alt=""><div><strong><?= e((string) $item['title']) ?></strong><br><span class="muted"><?= e(number_format((float) $row['units'], 0)) ?> sold / <?= (int) $row['orders'] ?> order(s)</span></div><b><?= e(marketplace_money((float) $row['revenue'])) ?></b></div><?php endforeach; ?><?php if (!$productPerformance): ?><div class="empty">No selling history yet.</div><?php endif; ?></div>
  </article>
  <article class="sc-card sc-panel span-3">
    <div class="sc-panel-head"><h2>Payout Summary</h2><a class="sc-link" href="seller-payouts.php">View Payouts</a></div>
    <h2 style="color:var(--green)"><?= e(marketplace_money((float) $paidUnsettled)) ?></h2>
    <p class="muted">Paid marketplace money waiting for settlement or withdrawal. Wallet balance: <?= e(marketplace_money((float) $wallet['balance'])) ?>.</p>
    <a class="sc-btn" href="seller-payouts.php">Request Payout</a>
  </article>

  <article class="sc-card sc-panel span-4">
    <div class="sc-panel-head"><h2>Seller Wallet</h2><a class="sc-link" href="seller-payouts.php">Open finance</a></div>
    <div class="sc-list">
      <div class="sc-row"><span class="sc-icon gold"><i data-lucide="wallet"></i></span><div><strong>Available balance</strong><br><span class="muted">Funds ready for seller use or withdrawal</span></div><b><?= e(marketplace_money((float) $wallet['balance'])) ?></b></div>
      <div class="sc-row"><span class="sc-icon orange"><i data-lucide="lock"></i></span><div><strong>Held withdrawals</strong><br><span class="muted">Pending operator payout processing</span></div><b><?= e(marketplace_money((float) ($wallet['hold_balance'] ?? 0))) ?></b></div>
      <div class="sc-row"><span class="sc-icon blue"><i data-lucide="timer"></i></span><div><strong>Paid not settled</strong><br><span class="muted">Orders awaiting release to seller</span></div><b><?= e(marketplace_money((float) $paidUnsettled)) ?></b></div>
    </div>
  </article>
  <article class="sc-card sc-panel span-4">
    <div class="sc-panel-head"><h2>Quick Withdrawal</h2><span class="badge good">Monnify / Paystack</span></div>
    <form method="post" class="sc-form" data-seller-withdrawal-form data-bank-url="../api/wallet-banks.php" data-resolve-url="../api/resolve-bank-account.php">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="central_withdrawal">
      <input type="hidden" name="bank_name" data-bank-name>
      <input type="hidden" name="bank_code" data-bank-code>
      <label>Amount</label><input type="number" name="withdraw_amount" min="<?= e((string) wallet_withdrawal_min_amount()) ?>" step="50" required>
      <label>Provider</label><select name="withdraw_provider" data-provider><option value="monnify">Monnify withdrawal</option><option value="paystack">Paystack withdrawal</option></select>
      <label>Receiving Bank</label><select data-bank-select required><option value="">Loading banks...</option></select>
      <label>Account Number</label><input name="account_number" data-account-number inputmode="numeric" maxlength="10" required>
      <label>Verified Account Name</label><input name="account_name" data-account-name readonly required>
      <div class="alert ok" data-resolve-status>Verify bank account before withdrawal.</div>
      <label>Note</label><textarea name="withdraw_note" placeholder="Seller earnings withdrawal"></textarea>
      <button class="sc-btn" type="submit" data-submit-withdrawal disabled>Submit Verified Withdrawal</button>
    </form>
    <p class="muted">First withdrawals follow the 24-hour operator approval rule.</p>
  </article>
  <article class="sc-card sc-panel span-4">
    <div class="sc-panel-head"><h2>Support & Intelligence</h2><a class="sc-link" href="seller-support.php">Help desk</a></div>
    <div class="sc-list">
      <div class="sc-row"><span class="sc-icon"><i data-lucide="headphones"></i></span><div><strong>Open support</strong><br><span class="muted">Seller tickets needing follow-up</span></div><b><?= (int) $openSupport ?></b></div>
      <div class="sc-row"><span class="sc-icon red"><i data-lucide="triangle-alert"></i></span><div><strong>Low stock</strong><br><span class="muted">Products at 5 units or below</span></div><b><?= (int) $lowStockCount ?></b></div>
      <div class="sc-row"><span class="sc-icon blue"><i data-lucide="download"></i></span><div><strong>Download reports</strong><br><span class="muted">Orders, listings, inquiries, wallet</span></div><a class="sc-link" href="seller-reports.php?export=summary">CSV</a></div>
    </div>
  </article>
  <article class="sc-card sc-panel span-6">
    <div class="sc-panel-head"><h2>Open Seller Support Ticket</h2><span class="badge">Admin queue</span></div>
    <form method="post" class="sc-form sc-form-grid">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="central_support">
      <input type="hidden" name="linked_record_type" value="marketplace_seller">
      <input type="hidden" name="linked_record_ref" value="<?= e((string) ($seller['store_name'] ?? 'Seller Central')) ?>">
      <div><label>Category</label><select name="category"><option value="marketplace">Marketplace</option><option value="payments">Wallet / Payout</option><option value="provider">Store verification</option><option value="technical">Technical</option><option value="general">General</option></select></div>
      <div><label>Priority</label><select name="priority"><option value="medium">Normal</option><option value="high">Urgent</option><option value="low">Low</option></select></div>
      <div class="wide"><label>Subject</label><input name="subject" required placeholder="What should the seller support team check?"></div>
      <div class="wide"><label>Message</label><textarea name="description" required placeholder="Include order, listing, wallet, payout, or buyer reference where possible."></textarea></div>
      <div class="wide"><button class="sc-btn" type="submit">Create Support Ticket</button></div>
    </form>
  </article>
  <article class="sc-card sc-panel span-6">
    <div class="sc-panel-head"><h2>Recent Finance & Support</h2><a class="sc-link" href="seller-payouts.php">Wallet ledger</a></div>
    <div class="sc-list">
      <?php foreach ($sellerWithdrawals as $wd): ?><div class="sc-row"><span class="sc-icon gold"><i data-lucide="banknote"></i></span><div><strong><?= e((string) $wd['reference']) ?></strong><br><span class="muted"><?= e((string) $wd['provider']) ?> withdrawal / <?= e((string) $wd['requested_at']) ?></span></div><span class="badge"><?= e((string) $wd['status']) ?></span></div><?php endforeach; ?>
      <?php foreach (array_slice($supportTickets, 0, 3) as $ticket): ?><div class="sc-row"><span class="sc-icon"><i data-lucide="ticket"></i></span><div><strong><?= e((string) $ticket['ticket_ref']) ?></strong><br><span class="muted"><?= e((string) $ticket['subject']) ?></span></div><span class="badge"><?= e((string) $ticket['status']) ?></span></div><?php endforeach; ?>
      <?php if (!$sellerWithdrawals && !$supportTickets): ?><div class="empty">No withdrawal or support records yet.</div><?php endif; ?>
    </div>
  </article>
  <article class="sc-card sc-panel span-12">
    <div class="sc-panel-head"><h2>CEO Marketplace Command Center</h2><div class="sc-actions"><a class="sc-btn secondary" href="seller-reports.php?export=summary">Export Summary</a><a class="sc-btn" href="seller-reports.php?export=orders">Export Orders</a></div></div>
    <section class="sc-kpis" style="margin:0">
      <div class="sc-card sc-kpi"><span class="sc-icon"><i data-lucide="line-chart"></i></span><div><small>Total Revenue</small><b><?= e(marketplace_money((float) $totalRevenue)) ?></b><span><?= (int) $orderCount ?> order(s)</span></div></div>
      <div class="sc-card sc-kpi"><span class="sc-icon blue"><i data-lucide="package-check"></i></span><div><small>Products Sold</small><b><?= e(number_format((float) $totalUnitsSold, 0)) ?></b><span><?= (int) $completedOrders ?> completed</span></div></div>
      <div class="sc-card sc-kpi"><span class="sc-icon gold"><i data-lucide="banknote"></i></span><div><small>Waiting Payout</small><b><?= e(marketplace_money((float) $paidUnsettled)) ?></b><span><?= count($paidUnsettledOrders) ?> paid order(s)</span></div></div>
      <div class="sc-card sc-kpi"><span class="sc-icon orange"><i data-lucide="truck"></i></span><div><small>Fulfillment</small><b><?= (int) ($readyOrders + $inTransitOrders) ?></b><span>Ready/in transit</span></div></div>
      <div class="sc-card sc-kpi"><span class="sc-icon purple"><i data-lucide="users"></i></span><div><small>Buyer Accounts</small><b><?= (int) $uniqueBuyers ?></b><span><?= e((string) $conversionRate) ?>% inquiry conversion</span></div></div>
      <div class="sc-card sc-kpi"><span class="sc-icon orange"><i data-lucide="megaphone"></i></span><div><small>Promotions</small><b><?= (int) $activePromotionCount ?> active</b><span><?= (int) $pendingPromotionCount ?> awaiting admin</span></div></div>
      <div class="sc-card sc-kpi"><span class="sc-icon red"><i data-lucide="shield-alert"></i></span><div><small>Store Health</small><b><?= (int) $healthScore ?>%</b><span><?= e($adminStatus) ?></span></div></div>
    </section>
  </article>
  <article class="sc-card sc-panel span-7">
    <div class="sc-panel-head"><h2>Product Performance Drilldown</h2><a class="sc-link" href="seller-products.php">Manage products</a></div>
    <table class="sc-table"><thead><tr><th>Product</th><th>Sold</th><th>Revenue</th><th>Waiting Payout</th><th>Status</th></tr></thead><tbody>
      <?php foreach (array_slice($productPerformance, 0, 8) as $row): $item=$row['listing']; ?><tr><td><strong><?= e((string) $item['title']) ?></strong><br><small class="muted"><?= e((string) ($item['availability_status'] ?? 'available')) ?></small></td><td><?= e(number_format((float) $row['units'], 0)) ?></td><td><?= e(marketplace_money((float) $row['revenue'])) ?></td><td><?= e(marketplace_money((float) $row['pending_payout'])) ?></td><td><span class="badge <?= (string)$item['approval_status']==='approved'?'good':'warn' ?>"><?= e(marketplace_status_label((string) $item['approval_status'])) ?></span></td></tr><?php endforeach; ?>
      <?php if (!$productPerformance): ?><tr><td colspan="5">No product performance yet.</td></tr><?php endif; ?>
    </tbody></table>
  </article>
  <article class="sc-card sc-panel span-5">
    <div class="sc-panel-head"><h2>Payout & Fulfillment Pipeline</h2><a class="sc-link" href="seller-payouts.php">Finance</a></div>
    <div class="sc-list">
      <div class="sc-row"><span class="sc-icon gold"><i data-lucide="wallet"></i></span><div><strong>Paid, waiting payout</strong><br><span class="muted">Admin settlement or withdrawal queue</span></div><b><?= e(marketplace_money((float) $paidUnsettled)) ?></b></div>
      <div class="sc-row"><span class="sc-icon"><i data-lucide="check-circle"></i></span><div><strong>Completed revenue</strong><br><span class="muted">Fulfilled seller orders</span></div><b><?= e(marketplace_money((float) $completedRevenue)) ?></b></div>
      <div class="sc-row"><span class="sc-icon blue"><i data-lucide="truck"></i></span><div><strong>Ready / in transit</strong><br><span class="muted">Operational fulfillment queue</span></div><b><?= (int) ($readyOrders + $inTransitOrders) ?></b></div>
      <div class="sc-row"><span class="sc-icon red"><i data-lucide="rotate-ccw"></i></span><div><strong>Cancelled / disputed</strong><br><span class="muted">Requires seller/admin oversight</span></div><b><?= (int) ($cancelledOrders + $disputedOrders) ?></b></div>
      <div class="sc-row"><span class="sc-icon orange"><i data-lucide="credit-card"></i></span><div><strong>Unpaid orders</strong><br><span class="muted">Payment not yet confirmed</span></div><b><?= (int) $unpaidOrders ?></b></div>
    </div>
  </article>
  <article class="sc-card sc-panel span-6">
    <div class="sc-panel-head"><h2>Admin Governance & Store Risk</h2><a class="sc-link" href="seller-support.php">Ask admin</a></div>
    <div class="sc-list">
      <?php foreach ($governanceActions as $item): ?><div class="sc-row"><span class="sc-icon <?= $item[0] === 'danger' ? 'red' : ($item[0] === 'warn' ? 'orange' : '') ?>"><i data-lucide="<?= $item[0] === 'good' ? 'shield-check' : 'shield-alert' ?>"></i></span><div><strong><?= e($item[1]) ?></strong><br><span class="muted"><?= e($item[2]) ?></span></div><span class="badge <?= $item[0] === 'danger' ? 'danger' : ($item[0] === 'warn' ? 'warn' : 'good') ?>"><?= e(strtoupper($item[0])) ?></span></div><?php endforeach; ?>
    </div>
  </article>
  <article class="sc-card sc-panel span-6">
    <div class="sc-panel-head"><h2>Promotion Governance</h2><a class="sc-link" href="seller-promotions.php">Manage promotions</a></div>
    <div class="sc-list">
      <div class="sc-row"><span class="sc-icon orange"><i data-lucide="clock"></i></span><div><strong>Awaiting admin approval</strong><br><span class="muted">Paid promotions not public yet</span></div><b><?= (int) $pendingPromotionCount ?></b></div>
      <div class="sc-row"><span class="sc-icon"><i data-lucide="megaphone"></i></span><div><strong>Active campaigns</strong><br><span class="muted">Approved placements currently live</span></div><b><?= (int) $activePromotionCount ?></b></div>
      <div class="sc-row"><span class="sc-icon gold"><i data-lucide="coins"></i></span><div><strong>Budget waiting approval</strong><br><span class="muted">Admin-governed promotion spend</span></div><b><?= e(marketplace_money((float) $pendingPromotionSpend)) ?></b></div>
      <div class="sc-row"><span class="sc-icon blue"><i data-lucide="mouse-pointer-click"></i></span><div><strong>Promotion performance</strong><br><span class="muted"><?= number_format((int) $promotionImpressions) ?> impressions / <?= number_format((int) $promotionClicks) ?> clicks</span></div><b><?= e(number_format((float) $promotionCtr, 2)) ?>%</b></div>
      <div class="sc-row"><span class="sc-icon red"><i data-lucide="pause-circle"></i></span><div><strong>Paused / rejected</strong><br><span class="muted">Admin moderation outcomes</span></div><b><?= (int) ($pausedPromotionCount + $rejectedPromotionCount) ?></b></div>
    </div>
  </article>  <article class="sc-card sc-panel span-6">
    <div class="sc-panel-head"><h2>Buyer & Conversion Oversight</h2><a class="sc-link" href="seller-buyers.php">Buyer list</a></div>
    <div class="sc-list">
      <div class="sc-row"><span class="sc-icon purple"><i data-lucide="users"></i></span><div><strong>Unique buyers</strong><br><span class="muted">Known buyer names/emails from orders</span></div><b><?= (int) $uniqueBuyers ?></b></div>
      <div class="sc-row"><span class="sc-icon blue"><i data-lucide="message-square"></i></span><div><strong>Buyer inquiries</strong><br><span class="muted">Messages and quote requests</span></div><b><?= (int) $inquiryCount ?></b></div>
      <div class="sc-row"><span class="sc-icon"><i data-lucide="shopping-bag"></i></span><div><strong>Inquiry to order conversion</strong><br><span class="muted">Orders compared to inquiries</span></div><b><?= e((string) $conversionRate) ?>%</b></div>
      <div class="sc-row"><span class="sc-icon orange"><i data-lucide="message-circle-warning"></i></span><div><strong>Open support tickets</strong><br><span class="muted">Seller help and admin follow-up</span></div><b><?= (int) $openSupport ?></b></div>
    </div>
  </article>  <article class="sc-card sc-panel span-3">
    <div class="sc-panel-head"><h2>Buyer Messages</h2><a class="sc-link" href="seller-messages.php">View All</a></div>
    <div class="sc-list"><?php foreach (array_slice($inquiries, 0, 3) as $inq): ?><div class="sc-row"><span class="sc-icon"><i data-lucide="message-circle"></i></span><div><strong><?= e((string) $inq['buyer_name']) ?></strong><br><span class="muted"><?= e((string) $inq['listing_title']) ?></span></div><span class="badge good">New</span></div><?php endforeach; ?><?php if (!$inquiries): ?><div class="empty">No buyer message yet.</div><?php endif; ?></div>
  </article>
  <article class="sc-card sc-panel span-3">
    <div class="sc-panel-head"><h2>Disputes / Refunds</h2><a class="sc-link" href="seller-disputes.php">View All</a></div>
    <div class="sc-row"><span class="sc-icon red"><i data-lucide="badge-alert"></i></span><div><strong>Open disputes</strong><br><span class="muted">Refunds and buyer issues</span></div><b>0</b></div>
  </article>
  <article class="sc-card sc-panel span-3">
    <div class="sc-panel-head"><h2>Academy Compliance</h2><a class="sc-link" href="../academy/my-learning.php">View Courses</a></div>
    <div style="display:grid;place-items:center;min-height:150px"><div style="width:112px;height:112px;border-radius:50%;background:conic-gradient(var(--green) 75%,#e2e8f0 0);display:grid;place-items:center"><div style="width:74px;height:74px;border-radius:50%;background:#fff;display:grid;place-items:center;text-align:center"><b>75%</b><small>Complete</small></div></div></div>
  </article>
  <article class="sc-card sc-panel span-3">
    <div class="sc-panel-head"><h2>Document Expiry Alerts</h2><a class="sc-link" href="seller-settings.php">View All</a></div>
    <div class="sc-list"><div class="sc-row"><span class="sc-icon red"><i data-lucide="file-warning"></i></span><div><strong>Input Provider Certificate</strong><br><span class="muted">Expires in 28 days</span></div></div><div class="sc-row"><span class="sc-icon gold"><i data-lucide="file-text"></i></span><div><strong>Tax Clearance Certificate</strong><br><span class="muted">Expires in 45 days</span></div></div></div>
  </article>
  <article class="sc-card sc-panel span-12">
    <div class="sc-panel-head"><h2>Quick Actions</h2></div>
    <div class="quick-actions">
      <a href="seller-add-product.php"><span class="sc-icon"><i data-lucide="plus"></i></span>Add Product<small>List a new product</small></a>
      <a href="seller-products.php"><span class="sc-icon blue"><i data-lucide="file-up"></i></span>Import CSV<small>Bulk upload products</small></a>
      <a href="seller-promotions.php"><span class="sc-icon orange"><i data-lucide="megaphone"></i></span>Create Promotion<small>Boost sales</small></a>
      <a href="<?= $seller ? 'store.php?seller=' . e((string) $seller['slug']) : 'seller-settings.php' ?>"><span class="sc-icon purple"><i data-lucide="store"></i></span>View Storefront<small>See public store</small></a>
      <a href="seller-orders.php"><span class="sc-icon"><i data-lucide="download"></i></span>Export Orders<small>Download order list</small></a>
      <a href="seller-payouts.php"><span class="sc-icon gold"><i data-lucide="wallet"></i></span>Request Payout<small>Withdraw earnings</small></a>
    </div>
  </article>
</section>
<script>
document.querySelectorAll('[data-seller-withdrawal-form]').forEach(function(form){
  const provider=form.querySelector('[data-provider]');
  const bankSelect=form.querySelector('[data-bank-select]');
  const bankName=form.querySelector('[data-bank-name]');
  const bankCode=form.querySelector('[data-bank-code]');
  const accountNumber=form.querySelector('[data-account-number]');
  const accountName=form.querySelector('[data-account-name]');
  const status=form.querySelector('[data-resolve-status]');
  const submit=form.querySelector('[data-submit-withdrawal]');
  let timer=null;
  function setStatus(message, ok){ status.className='alert '+(ok?'ok':'err'); status.textContent=message; }
  function reset(){ accountName.value=''; submit.disabled=true; setStatus('Verify bank account before withdrawal.', true); }
  async function loadBanks(){
    reset(); bankSelect.innerHTML='<option value="">Loading banks...</option>';
    try{
      const response=await fetch(form.dataset.bankUrl+'?provider='+encodeURIComponent(provider.value),{credentials:'same-origin'});
      const payload=await response.json();
      if(!response.ok||!payload.success) throw new Error(payload.error||'Unable to load banks.');
      bankSelect.innerHTML='<option value="">Select receiving bank</option>'+payload.banks.map(function(bank){return '<option value="'+String(bank.code).replace(/"/g,'&quot;')+'" data-name="'+String(bank.name).replace(/"/g,'&quot;')+'">'+bank.name+'</option>';}).join('');
    }catch(error){ bankSelect.innerHTML='<option value="">Bank lookup unavailable</option>'; setStatus(error.message||'Bank lookup unavailable.', false); }
  }
  async function resolveAccount(){
    const option=bankSelect.options[bankSelect.selectedIndex];
    bankCode.value=bankSelect.value||''; bankName.value=option?(option.dataset.name||''):'';
    accountName.value=''; submit.disabled=true;
    const digits=accountNumber.value.replace(/\D/g,'').slice(0,10); accountNumber.value=digits;
    if(!bankCode.value||digits.length!==10){ reset(); return; }
    setStatus('Verifying account name...', true);
    const data=new FormData(); data.append('_csrf',form.querySelector('[name="_csrf"]').value); data.append('provider',provider.value); data.append('bank_code',bankCode.value); data.append('account_number',digits);
    try{
      const response=await fetch(form.dataset.resolveUrl,{method:'POST',body:data,credentials:'same-origin'});
      const payload=await response.json();
      if(!response.ok||!payload.success||!payload.account_name) throw new Error(payload.error||'Account could not be verified.');
      accountName.value=payload.account_name; submit.disabled=false; setStatus('Verified: '+payload.account_name, true);
    }catch(error){ setStatus(error.message||'Account could not be verified.', false); }
  }
  provider.addEventListener('change', loadBanks);
  bankSelect.addEventListener('change', resolveAccount);
  accountNumber.addEventListener('input', function(){ clearTimeout(timer); reset(); timer=setTimeout(resolveAccount,450); });
  form.addEventListener('submit', function(event){ if(submit.disabled||!accountName.value){ event.preventDefault(); setStatus('Verify the bank account before submitting withdrawal.', false); } });
  loadBanks();
});
</script>
<?php seller_footer(); ?>
