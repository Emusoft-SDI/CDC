<?php
declare(strict_types=1);

require_once __DIR__ . '/_buyer.php';

$pdo = buyer_boot();
$user = buyer_require($pdo);
$userId = (int) $user['id'];
$startDate = (string) ($_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days')));
$endDate = (string) ($_GET['end_date'] ?? date('Y-m-d'));
$periodStart = $startDate . ' 00:00:00';
$periodEnd = $endDate . ' 23:59:59';
$export = (string) ($_GET['export'] ?? '');

function br_rows(PDO $pdo, string $sql, array $params = []): array
{
    try { $stmt = $pdo->prepare($sql); $stmt->execute($params); return $stmt->fetchAll(); }
    catch (Throwable $e) { error_log('Buyer report query failed: ' . $e->getMessage()); return []; }
}
$orderRows = br_rows($pdo, 'SELECT o.checkout_ref, o.order_ref, o.created_at, o.buyer_name, o.total_amount, o.delivery_fee, o.service_fee, o.status, o.payment_status, o.delivery_status, l.title listing_title, s.store_name FROM marketplace_orders o JOIN marketplace_listings l ON l.id=o.listing_id JOIN marketplace_sellers s ON s.id=o.seller_id WHERE o.buyer_user_id=? AND o.created_at BETWEEN ? AND ? ORDER BY o.created_at DESC LIMIT 160', [$userId, $periodStart, $periodEnd]);
$quoteRows = br_rows($pdo, 'SELECT i.created_at, i.buyer_name, i.quantity, i.status, i.quoted_amount, l.title listing_title, s.store_name FROM marketplace_inquiries i JOIN marketplace_listings l ON l.id=i.listing_id JOIN marketplace_sellers s ON s.id=i.seller_id WHERE i.buyer_user_id=? AND i.created_at BETWEEN ? AND ? ORDER BY i.created_at DESC LIMIT 120', [$userId, $periodStart, $periodEnd]);
$wallet = wallet_get_or_create($pdo, $userId);
$walletRows = br_rows($pdo, 'SELECT created_at, type, direction, description, reference, amount, status FROM wallet_transactions WHERE wallet_id=? AND created_at BETWEEN ? AND ? ORDER BY created_at DESC LIMIT 120', [(int) $wallet['id'], $periodStart, $periodEnd]);
$supportRows = br_rows($pdo, 'SELECT ticket_ref, category, priority, status, subject, last_activity_at FROM support_tickets WHERE user_id=? ORDER BY last_activity_at DESC LIMIT 80', [$userId]);
$monthly = br_rows($pdo, "SELECT DATE_FORMAT(created_at, '%b') month_label, COALESCE(SUM(total_amount + COALESCE(delivery_fee,0) + COALESCE(service_fee,0)),0) spend, COUNT(*) orders FROM marketplace_orders WHERE buyer_user_id=? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY DATE_FORMAT(created_at, '%Y-%m'), DATE_FORMAT(created_at, '%b') ORDER BY MIN(created_at)", [$userId]);
$categoryRows = br_rows($pdo, 'SELECT COALESCE(c.name, l.listing_type) category_name, COUNT(o.id) orders, COALESCE(SUM(o.total_amount),0) spend FROM marketplace_orders o JOIN marketplace_listings l ON l.id=o.listing_id LEFT JOIN marketplace_categories c ON c.id=l.category_id WHERE o.buyer_user_id=? AND o.created_at BETWEEN ? AND ? GROUP BY COALESCE(c.name, l.listing_type) ORDER BY spend DESC LIMIT 8', [$userId, $periodStart, $periodEnd]);

$spend = array_sum(array_map(static fn(array $r): float => (float) $r['total_amount'] + (float) ($r['delivery_fee'] ?? 0) + (float) ($r['service_fee'] ?? 0), $orderRows));
$paid = count(array_filter($orderRows, static fn(array $r): bool => (string) ($r['payment_status'] ?? '') === 'paid'));
$delivered = count(array_filter($orderRows, static fn(array $r): bool => in_array((string) ($r['delivery_status'] ?? ''), ['delivered'], true) || (string) $r['status'] === 'completed'));
$refundCount = count(array_filter($walletRows, static fn(array $r): bool => stripos((string) ($r['description'] ?? ''), 'refund') !== false));
$openSupport = count(array_filter($supportRows, static fn(array $r): bool => !in_array((string) $r['status'], ['resolved','closed','rejected'], true)));
$maxSpend = max(1.0, ...array_map(static fn(array $r): float => (float) $r['spend'], $monthly ?: [['spend'=>1]]));
$insights = [
    $openSupport > 0 ? ['warn','Support follow-up', $openSupport . ' buyer support ticket(s) are still active.'] : ['ok','Support clear','No active buyer support risk.'],
    $paid < count($orderRows) ? ['warn','Payment attention', (count($orderRows) - $paid) . ' order(s) are not marked paid.'] : ['ok','Payments reconciled','Orders in this period are paid or no orders exist.'],
    count($quoteRows) > count($orderRows) ? ['warn','Quote conversion gap', 'You have more quote requests than completed orders. Follow up with sellers.'] : ['ok','Quote activity balanced','Quote requests are aligned with purchasing activity.'],
];
if ($export !== '') {
    $rows = match ($export) { 'orders' => $orderRows, 'quotes' => $quoteRows, 'wallet' => $walletRows, 'support' => $supportRows, default => [['metric'=>'Spend','value'=>buyer_money($spend)],['metric'=>'Orders','value'=>count($orderRows)],['metric'=>'Refund entries','value'=>$refundCount],['metric'=>'Wallet balance','value'=>buyer_money((float) $wallet['balance'])]] };
    app_export_csv('natcodev-buyer-' . $export . '-' . date('Ymd-His') . '.csv', $rows ? array_keys($rows[0]) : [], $rows);
}

buyer_page_start('Buyer Intelligence Reports', 'reports', $user, buyer_counts($pdo, $user));
?>
<div class="page-head"><div><h1>Buyer Intelligence Reports</h1><p>Track buying activity, marketplace spend, refunds, wallet movement, support issues, and seller response patterns.</p></div><a class="btn" href="reports.php?export=summary&start_date=<?= e($startDate) ?>&end_date=<?= e($endDate) ?>"><i class="fas fa-download"></i> Download Summary</a></div>
<form class="card form-grid" method="get" style="margin-bottom:16px"><label>Start<input type="date" name="start_date" value="<?= e($startDate) ?>"></label><label>End<input type="date" name="end_date" value="<?= e($endDate) ?>"></label><div class="wide"><button class="btn" type="submit">Refresh Report</button> <a class="btn light" href="reports.php?export=orders&start_date=<?= e($startDate) ?>&end_date=<?= e($endDate) ?>">Orders CSV</a> <a class="btn light" href="reports.php?export=wallet&start_date=<?= e($startDate) ?>&end_date=<?= e($endDate) ?>">Wallet CSV</a> <a class="btn light" href="reports.php?export=quotes&start_date=<?= e($startDate) ?>&end_date=<?= e($endDate) ?>">Quotes CSV</a></div></form>
<div class="kpis"><div class="kpi"><i class="fas fa-cart-shopping"></i><span><b><?= count($orderRows) ?></b><br>Orders</span></div><div class="kpi"><i class="fas fa-naira-sign"></i><span><b><?= e(buyer_money($spend)) ?></b><br>Spend</span></div><div class="kpi"><i class="fas fa-credit-card"></i><span><b><?= $paid ?></b><br>Paid Orders</span></div><div class="kpi"><i class="fas fa-truck"></i><span><b><?= $delivered ?></b><br>Delivered</span></div><div class="kpi"><i class="fas fa-rotate-left"></i><span><b><?= $refundCount ?></b><br>Refund Entries</span></div><div class="kpi"><i class="fas fa-wallet"></i><span><b><?= e(buyer_money((float) $wallet['balance'])) ?></b><br>Wallet</span></div></div>
<div class="grid"><section class="card span-7"><div class="card-head"><h2>Spend Graph</h2><span class="badge">6 months</span></div><div style="height:260px;display:grid;grid-template-columns:repeat(<?= max(1,count($monthly)) ?>,1fr);gap:12px;align-items:end;border-bottom:1px solid var(--line);padding-top:16px"><?php foreach ($monthly ?: [['month_label'=>'No data','spend'=>0,'orders'=>0]] as $m): ?><div style="height:100%;display:grid;align-items:end;text-align:center"><div style="height:<?= max(4,(int) round(((float)$m['spend']/$maxSpend)*100)) ?>%;background:linear-gradient(180deg,#0b7a3b,#d8efdf);border-radius:8px 8px 0 0"></div><small><?= e((string)$m['month_label']) ?><br><?= (int)$m['orders'] ?></small></div><?php endforeach; ?></div></section><section class="card span-5"><div class="card-head"><h2>Buyer Intelligence</h2><span class="badge">Actions</span></div><div class="list"><?php foreach ($insights as $i): ?><div class="row"><span><strong><?= e($i[1]) ?></strong><br><small><?= e($i[2]) ?></small></span><span class="badge <?= $i[0] === 'warn' ? 'gold' : '' ?>"><?= e(strtoupper($i[0])) ?></span></div><?php endforeach; ?></div></section><section class="card span-6"><div class="card-head"><h2>Category Spend</h2></div><div class="list"><?php foreach ($categoryRows as $row): ?><div class="row"><span><strong><?= e((string)$row['category_name']) ?></strong><br><small><?= (int)$row['orders'] ?> order(s)</small></span><strong><?= e(buyer_money((float)$row['spend'])) ?></strong></div><?php endforeach; ?><?php if (!$categoryRows): ?><div class="alert ok">No category spend in selected period.</div><?php endif; ?></div></section><section class="card span-6"><div class="card-head"><h2>Recent Orders</h2><a class="view" href="reports.php?export=orders&start_date=<?= e($startDate) ?>&end_date=<?= e($endDate) ?>">CSV</a></div><div class="list"><?php foreach (array_slice($orderRows,0,8) as $row): ?><div class="row"><span><strong><?= e((string)$row['order_ref']) ?></strong><br><small><?= e((string)$row['listing_title']) ?> / <?= e((string)$row['store_name']) ?></small></span><span><strong><?= e(buyer_money((float)$row['total_amount'])) ?></strong><br><?= buyer_status_badge((string)$row['status']) ?></span></div><?php endforeach; ?><?php if (!$orderRows): ?><div class="alert ok">No orders in selected period.</div><?php endif; ?></div></section></div>
<?php buyer_page_end(); ?>