<?php
declare(strict_types=1);

require_once __DIR__ . '/_seller.php';
require_once __DIR__ . '/../lib/monnify.php';

$pdo = market_boot();
wallet_ensure_schema($pdo);
$user = market_require_user($pdo);
seller_access_or_message($pdo, $user);
$ctx = seller_query_context($pdo, $user, false);
$seller = $ctx['seller'];
$sellerId = (int) ($seller['id'] ?? 0);
$startDate = (string) ($_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days')));
$endDate = (string) ($_GET['end_date'] ?? date('Y-m-d'));
$periodStart = $startDate . ' 00:00:00';
$periodEnd = $endDate . ' 23:59:59';
$export = (string) ($_GET['export'] ?? '');

function sr_rows(PDO $pdo, string $sql, array $params = []): array
{
    try { $stmt = $pdo->prepare($sql); $stmt->execute($params); return $stmt->fetchAll(); }
    catch (Throwable $e) { error_log('Seller report query failed: ' . $e->getMessage()); return []; }
}

$orderRows = $sellerId > 0 ? sr_rows($pdo, 'SELECT o.order_ref, o.buyer_name, o.buyer_email, o.total_amount, o.status, o.payment_status, o.delivery_status, o.settled_at, o.created_at, l.title listing_title FROM marketplace_orders o LEFT JOIN marketplace_listings l ON l.id=o.listing_id WHERE o.seller_id=? AND o.created_at BETWEEN ? AND ? ORDER BY o.created_at DESC LIMIT 160', [$sellerId, $periodStart, $periodEnd]) : [];
$listingRows = $sellerId > 0 ? sr_rows($pdo, 'SELECT title, listing_type, approval_status, availability_status, price, quantity_available, unit, created_at FROM marketplace_listings WHERE seller_id=? ORDER BY created_at DESC LIMIT 160', [$sellerId]) : [];
$inquiryRows = $sellerId > 0 ? sr_rows($pdo, 'SELECT i.created_at, i.buyer_name, i.quantity, i.status, i.quoted_amount, l.title listing_title FROM marketplace_inquiries i LEFT JOIN marketplace_listings l ON l.id=i.listing_id WHERE i.seller_id=? AND i.created_at BETWEEN ? AND ? ORDER BY i.created_at DESC LIMIT 120', [$sellerId, $periodStart, $periodEnd]) : [];
$wallet = wallet_get_or_create($pdo, (int) $user['id']);
$walletRows = sr_rows($pdo, 'SELECT created_at, description, reference, amount, status FROM wallet_transactions WHERE wallet_id=? AND created_at BETWEEN ? AND ? ORDER BY created_at DESC LIMIT 100', [(int) $wallet['id'], $periodStart, $periodEnd]);
$monthly = $sellerId > 0 ? sr_rows($pdo, "SELECT DATE_FORMAT(created_at, '%b') month_label, COALESCE(SUM(total_amount),0) revenue, COUNT(*) orders FROM marketplace_orders WHERE seller_id=? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY DATE_FORMAT(created_at, '%Y-%m'), DATE_FORMAT(created_at, '%b') ORDER BY MIN(created_at)", [$sellerId]) : [];
$productRows = $sellerId > 0 ? sr_rows($pdo, 'SELECT l.title, COUNT(o.id) orders, COALESCE(SUM(o.total_amount),0) revenue FROM marketplace_listings l LEFT JOIN marketplace_orders o ON o.listing_id=l.id AND o.created_at BETWEEN ? AND ? WHERE l.seller_id=? GROUP BY l.id ORDER BY revenue DESC, orders DESC LIMIT 8', [$periodStart, $periodEnd, $sellerId]) : [];

$revenue = array_sum(array_map(static fn(array $r): float => (float) $r['total_amount'], array_filter($orderRows, static fn(array $r): bool => (string) $r['status'] !== 'cancelled')));
$paid = count(array_filter($orderRows, static fn(array $r): bool => (string) ($r['payment_status'] ?? '') === 'paid'));
$completed = count(array_filter($orderRows, static fn(array $r): bool => (string) $r['status'] === 'completed'));
$pendingSettlement = array_sum(array_map(static fn(array $r): float => empty($r['settled_at']) && (string) ($r['payment_status'] ?? '') === 'paid' ? (float) $r['total_amount'] : 0.0, $orderRows));
$lowStock = count(array_filter($listingRows, static fn(array $r): bool => $r['quantity_available'] !== null && (float) $r['quantity_available'] <= 5));
$approvalPending = count(array_filter($listingRows, static fn(array $r): bool => (string) $r['approval_status'] !== 'approved'));
$conversion = count($inquiryRows) > 0 ? round((count($orderRows) / count($inquiryRows)) * 100, 1) : 0.0;
$maxRevenue = max(1.0, ...array_map(static fn(array $r): float => (float) $r['revenue'], $monthly ?: [['revenue'=>1]]));
$insights = [
    $approvalPending > 0 ? ['warn','Listing approval bottleneck', $approvalPending . ' listing(s) need approval before buyers can see them.'] : ['ok','Catalog approval healthy','No approval backlog was detected.'],
    $lowStock > 0 ? ['warn','Inventory attention', $lowStock . ' listing(s) are low in stock.'] : ['ok','Inventory stable','No low-stock signal was detected.'],
    $pendingSettlement > 0 ? ['warn','Settlement pending', marketplace_money($pendingSettlement) . ' is paid but not settled yet.'] : ['ok','Settlement clear','No paid unsettled amount in this period.'],
];
if ($export !== '') {
    $rows = match ($export) { 'orders' => $orderRows, 'listings' => $listingRows, 'inquiries' => $inquiryRows, 'wallet' => $walletRows, default => [['metric'=>'Revenue','value'=>marketplace_money($revenue)],['metric'=>'Conversion','value'=>$conversion . '%'],['metric'=>'Pending settlement','value'=>marketplace_money($pendingSettlement)]] };
    app_export_csv('natcodev-seller-' . $export . '-' . date('Ymd-His') . '.csv', $rows ? array_keys($rows[0]) : [], $rows);
}

seller_header('Seller Intelligence Reports', 'reports', $user, $seller);
seller_kpis($ctx);
?>
<section class="sc-card sc-panel" style="margin-bottom:16px"><div class="sc-panel-head"><h2>Seller Intelligence Reports</h2><div class="sc-actions"><a class="sc-btn secondary" href="seller-reports.php?export=summary&start_date=<?= e($startDate) ?>&end_date=<?= e($endDate) ?>">Download Summary</a><a class="sc-btn" href="seller-reports.php?export=orders&start_date=<?= e($startDate) ?>&end_date=<?= e($endDate) ?>">Export Orders</a></div></div><form method="get" class="sc-form sc-form-grid"><div><label>Start</label><input type="date" name="start_date" value="<?= e($startDate) ?>"></div><div><label>End</label><input type="date" name="end_date" value="<?= e($endDate) ?>"></div><div class="wide"><button class="sc-btn" type="submit">Refresh Intelligence</button> <a class="sc-btn secondary" href="seller-reports.php?export=listings">Listings CSV</a> <a class="sc-btn secondary" href="seller-reports.php?export=inquiries&start_date=<?= e($startDate) ?>&end_date=<?= e($endDate) ?>">Inquiries CSV</a></div></form></section>
<section class="sc-kpis"><div class="sc-card sc-kpi"><span class="sc-icon"><i data-lucide="line-chart"></i></span><div><small>Revenue</small><b><?= e(marketplace_money($revenue)) ?></b><span>Selected period</span></div></div><div class="sc-card sc-kpi"><span class="sc-icon blue"><i data-lucide="shopping-bag"></i></span><div><small>Orders</small><b><?= count($orderRows) ?></b><span><?= $paid ?> paid</span></div></div><div class="sc-card sc-kpi"><span class="sc-icon"><i data-lucide="check-circle"></i></span><div><small>Completed</small><b><?= $completed ?></b><span>Fulfilled orders</span></div></div><div class="sc-card sc-kpi"><span class="sc-icon orange"><i data-lucide="repeat"></i></span><div><small>Inquiry Conversion</small><b><?= e((string) $conversion) ?>%</b><span><?= count($inquiryRows) ?> inquiries</span></div></div><div class="sc-card sc-kpi"><span class="sc-icon gold"><i data-lucide="wallet"></i></span><div><small>Pending Settlement</small><b><?= e(marketplace_money($pendingSettlement)) ?></b><span>Paid not settled</span></div></div><div class="sc-card sc-kpi"><span class="sc-icon red"><i data-lucide="triangle-alert"></i></span><div><small>Stock Alerts</small><b><?= $lowStock ?></b><span>Low quantity</span></div></div></section>
<section class="sc-grid"><article class="sc-card sc-panel span-7"><div class="sc-panel-head"><h2>Revenue Graph</h2><span class="badge good">6 months</span></div><div style="height:270px;display:grid;grid-template-columns:repeat(<?= max(1, count($monthly)) ?>,1fr);gap:12px;align-items:end;padding:16px 8px 0;border-bottom:1px solid var(--line)"><?php foreach ($monthly ?: [['month_label'=>'No data','revenue'=>0,'orders'=>0]] as $m): ?><div style="height:100%;display:grid;align-items:end;text-align:center"><div style="height:<?= max(4, (int) round(((float) $m['revenue'] / $maxRevenue) * 100)) ?>%;background:linear-gradient(180deg,#0f8f4b,#dff7e8);border-radius:8px 8px 0 0"></div><small><?= e((string) $m['month_label']) ?><br><?= (int) $m['orders'] ?> order(s)</small></div><?php endforeach; ?></div></article><article class="sc-card sc-panel span-5"><div class="sc-panel-head"><h2>Seller Actions</h2></div><div class="sc-list"><?php foreach ($insights as $i): ?><div class="sc-row"><span class="sc-icon <?= $i[0] === 'warn' ? 'orange' : '' ?>"><i data-lucide="<?= $i[0] === 'warn' ? 'alert-triangle' : 'check' ?>"></i></span><div><strong><?= e($i[1]) ?></strong><br><small class="muted"><?= e($i[2]) ?></small></div><span class="badge <?= $i[0] === 'warn' ? 'warn' : 'good' ?>"><?= e(strtoupper($i[0])) ?></span></div><?php endforeach; ?></div></article><article class="sc-card sc-panel span-6"><div class="sc-panel-head"><h2>Top Products</h2><a class="sc-link" href="seller-reports.php?export=listings">CSV</a></div><table class="sc-table"><tr><th>Product</th><th>Orders</th><th>Revenue</th></tr><?php foreach ($productRows as $row): ?><tr><td><?= e((string) $row['title']) ?></td><td><?= (int) $row['orders'] ?></td><td><?= e(marketplace_money((float) $row['revenue'])) ?></td></tr><?php endforeach; ?><?php if (!$productRows): ?><tr><td colspan="3">No product performance yet.</td></tr><?php endif; ?></table></article><article class="sc-card sc-panel span-6"><div class="sc-panel-head"><h2>Recent Orders</h2><a class="sc-link" href="seller-reports.php?export=orders&start_date=<?= e($startDate) ?>&end_date=<?= e($endDate) ?>">CSV</a></div><table class="sc-table"><tr><th>Order</th><th>Buyer</th><th>Amount</th><th>Status</th></tr><?php foreach (array_slice($orderRows,0,8) as $row): ?><tr><td><?= e((string) $row['order_ref']) ?></td><td><?= e((string) $row['buyer_name']) ?></td><td><?= e(marketplace_money((float) $row['total_amount'])) ?></td><td><?= e(marketplace_status_label((string) $row['status'])) ?></td></tr><?php endforeach; ?><?php if (!$orderRows): ?><tr><td colspan="4">No orders in selected period.</td></tr><?php endif; ?></table></article></section>
<?php seller_footer(); ?>