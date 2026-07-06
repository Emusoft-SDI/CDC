<?php
declare(strict_types=1);

require_once __DIR__ . '/_provider.php';
require_once __DIR__ . '/../lib/monnify.php';
require_once __DIR__ . '/../lib/support.php';

$pdo = provider_boot();
wallet_ensure_schema($pdo);
support_ensure_schema($pdo);
$user = provider_full_user($pdo, provider_require($pdo));
$provider = provider_active($pdo, $user);
$counts = provider_counts($pdo, $provider, $user);
$userId = (int) $user['id'];
$startDate = (string) ($_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days')));
$endDate = (string) ($_GET['end_date'] ?? date('Y-m-d'));
$periodStart = $startDate . ' 00:00:00';
$periodEnd = $endDate . ' 23:59:59';
$export = (string) ($_GET['export'] ?? '');

function pr_rows(PDO $pdo, string $sql, array $params = []): array
{
    try { $stmt = $pdo->prepare($sql); $stmt->execute($params); return $stmt->fetchAll(); }
    catch (Throwable $e) { error_log('Provider report query failed: ' . $e->getMessage()); return []; }
}
function pr_scalar(PDO $pdo, string $sql, array $params = [], bool $float = false): int|float
{
    try { $stmt = $pdo->prepare($sql); $stmt->execute($params); $v = $stmt->fetchColumn(); return $float ? (float) ($v ?: 0) : (int) ($v ?: 0); }
    catch (Throwable $e) { error_log('Provider report scalar failed: ' . $e->getMessage()); return $float ? 0.0 : 0; }
}

$sellerIds = [];
if (app_table_exists($pdo, 'marketplace_sellers')) {
    $stmt = $pdo->prepare('SELECT id FROM marketplace_sellers WHERE user_id = ?');
    $stmt->execute([$userId]);
    $sellerIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}
$sellerWhere = $sellerIds ? 'seller_id IN (' . implode(',', array_fill(0, count($sellerIds), '?')) . ')' : '1=0';
$orderRows = $sellerIds ? pr_rows($pdo, "SELECT order_ref, buyer_name, total_amount, status, payment_status, delivery_status, created_at FROM marketplace_orders WHERE {$sellerWhere} AND created_at BETWEEN ? AND ? ORDER BY created_at DESC LIMIT 120", [...$sellerIds, $periodStart, $periodEnd]) : [];
$listingRows = $sellerIds ? pr_rows($pdo, "SELECT title, listing_type, approval_status, availability_status, price, quantity_available, created_at FROM marketplace_listings WHERE {$sellerWhere} ORDER BY created_at DESC LIMIT 120", $sellerIds) : [];
$wallet = wallet_get_or_create($pdo, $userId);
$walletRows = pr_rows($pdo, 'SELECT created_at, type, direction, description, reference, amount, status FROM wallet_transactions WHERE wallet_id = ? AND created_at BETWEEN ? AND ? ORDER BY created_at DESC LIMIT 120', [(int) $wallet['id'], $periodStart, $periodEnd]);
$supportRows = pr_rows($pdo, 'SELECT ticket_ref, category, priority, status, subject, last_activity_at FROM support_tickets WHERE user_id = ? ORDER BY last_activity_at DESC LIMIT 80', [$userId]);
$academyRows = app_table_exists($pdo, 'webinar_registrations') ? pr_rows($pdo, 'SELECT w.title, r.payment_status, r.completion_status, r.progress_percent, r.registered_at FROM webinar_registrations r JOIN webinars w ON w.id = r.webinar_id WHERE r.user_id = ? ORDER BY r.registered_at DESC LIMIT 80', [$userId]) : [];
$monthly = $sellerIds ? pr_rows($pdo, "SELECT DATE_FORMAT(created_at, '%b') month_label, COALESCE(SUM(total_amount),0) revenue, COUNT(*) orders FROM marketplace_orders WHERE {$sellerWhere} AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY DATE_FORMAT(created_at, '%Y-%m'), DATE_FORMAT(created_at, '%b') ORDER BY MIN(created_at)", $sellerIds) : [];

$revenue = array_sum(array_map(static fn(array $r): float => (float) $r['total_amount'], $orderRows));
$paidOrders = count(array_filter($orderRows, static fn(array $r): bool => (string) ($r['payment_status'] ?? '') === 'paid'));
$openSupport = count(array_filter($supportRows, static fn(array $r): bool => !in_array((string) $r['status'], ['resolved', 'closed', 'rejected'], true)));
$approvedListings = count(array_filter($listingRows, static fn(array $r): bool => (string) $r['approval_status'] === 'approved'));
$lowStock = count(array_filter($listingRows, static fn(array $r): bool => $r['quantity_available'] !== null && (float) $r['quantity_available'] <= 5));
$maxRevenue = max(1.0, ...array_map(static fn(array $r): float => (float) $r['revenue'], $monthly ?: [['revenue' => 1]]));
$insights = [];
$insights[] = $openSupport > 0 ? ['warn', 'Support follow-up required', $openSupport . ' active provider ticket(s) need attention.'] : ['ok', 'Support clear', 'No active provider support risk.'];
$insights[] = $lowStock > 0 ? ['warn', 'Stock risk', $lowStock . ' listing(s) are at low stock levels.'] : ['ok', 'Stock stable', 'No low-stock listing was detected from available data.'];
$insights[] = $approvedListings < count($listingRows) ? ['warn', 'Approval queue', (count($listingRows) - $approvedListings) . ' listing(s) still need marketplace approval.'] : ['ok', 'Listings public', 'All current listings are approved or no pending listing exists.'];

if ($export !== '') {
    $rows = match ($export) {
        'orders' => $orderRows,
        'listings' => $listingRows,
        'wallet' => $walletRows,
        'support' => $supportRows,
        'academy' => $academyRows,
        default => [['metric' => 'Revenue', 'value' => marketplace_money($revenue)], ['metric' => 'Orders', 'value' => count($orderRows)], ['metric' => 'Wallet balance', 'value' => marketplace_money((float) $wallet['balance'])]],
    };
    app_export_csv('natcodev-provider-' . $export . '-' . date('Ymd-His') . '.csv', $rows ? array_keys($rows[0]) : [], $rows);
}

provider_page_start('Provider Intelligence Reports', 'reports', $user, $provider, $counts);
?>
<div class="page-head"><div><h1>Provider Intelligence Reports</h1><p>Live analysis of provider listings, seller revenue, wallet movement, Academy activity, and support risk.</p></div><a class="btn" href="reports.php?<?= e(http_build_query(['start_date'=>$startDate,'end_date'=>$endDate,'export'=>'summary'])) ?>"><i class="fas fa-download"></i> Download Summary</a></div>
<form class="card form-grid" method="get" style="margin-bottom:16px"><label>Start<input type="date" name="start_date" value="<?= e($startDate) ?>"></label><label>End<input type="date" name="end_date" value="<?= e($endDate) ?>"></label><div class="wide"><button class="btn" type="submit">Refresh Report</button> <a class="btn light" href="reports.php?export=orders&start_date=<?= e($startDate) ?>&end_date=<?= e($endDate) ?>">Orders CSV</a> <a class="btn light" href="reports.php?export=listings&start_date=<?= e($startDate) ?>&end_date=<?= e($endDate) ?>">Listings CSV</a> <a class="btn light" href="reports.php?export=wallet&start_date=<?= e($startDate) ?>&end_date=<?= e($endDate) ?>">Wallet CSV</a></div></form>
<div class="kpis"><div class="kpi"><i class="fas fa-chart-line"></i><span><b><?= e(marketplace_money($revenue)) ?></b><br>Period Revenue</span></div><div class="kpi"><i class="fas fa-cart-shopping"></i><span><b><?= count($orderRows) ?></b><br>Orders</span></div><div class="kpi"><i class="fas fa-credit-card"></i><span><b><?= $paidOrders ?></b><br>Paid Orders</span></div><div class="kpi"><i class="fas fa-box"></i><span><b><?= $approvedListings ?>/<?= count($listingRows) ?></b><br>Approved Listings</span></div><div class="kpi"><i class="fas fa-wallet"></i><span><b><?= e(marketplace_money((float) $wallet['balance'])) ?></b><br>Wallet</span></div><div class="kpi"><i class="fas fa-headset"></i><span><b><?= $openSupport ?></b><br>Open Support</span></div></div>
<div class="grid">
  <section class="card span-7"><div class="card-head"><h2>Revenue Trend</h2><span class="badge">6 months</span></div><div style="height:260px;display:grid;grid-template-columns:repeat(<?= max(1, count($monthly)) ?>,1fr);gap:12px;align-items:end;border-bottom:1px solid var(--line);padding-top:18px"><?php foreach ($monthly ?: [['month_label'=>'No data','revenue'=>0,'orders'=>0]] as $m): ?><div style="display:grid;align-items:end;text-align:center;height:100%"><div title="<?= e(marketplace_money((float) $m['revenue'])) ?>" style="height:<?= max(4, (int) round(((float) $m['revenue'] / $maxRevenue) * 100)) ?>%;background:linear-gradient(180deg,#0b7a3b,#cfeedd);border-radius:8px 8px 0 0"></div><small><?= e((string) $m['month_label']) ?><br><?= (int) $m['orders'] ?></small></div><?php endforeach; ?></div></section>
  <section class="card span-5"><div class="card-head"><h2>Action Intelligence</h2><span class="badge">Priority</span></div><div class="list"><?php foreach ($insights as $i): ?><div class="row"><span><strong><?= e($i[1]) ?></strong><br><small><?= e($i[2]) ?></small></span><span class="badge <?= $i[0] === 'warn' ? 'warn' : '' ?>"><?= e(strtoupper($i[0])) ?></span></div><?php endforeach; ?></div></section>
  <section class="card span-6"><div class="card-head"><h2>Top Recent Orders</h2><a class="view" href="reports.php?export=orders&start_date=<?= e($startDate) ?>&end_date=<?= e($endDate) ?>">CSV</a></div><div class="list"><?php foreach (array_slice($orderRows,0,8) as $row): ?><div class="row"><span><strong><?= e((string) $row['order_ref']) ?></strong><br><small><?= e((string) $row['buyer_name']) ?> / <?= e((string) $row['status']) ?></small></span><strong><?= e(marketplace_money((float) $row['total_amount'])) ?></strong></div><?php endforeach; ?><?php if (!$orderRows): ?><div class="notice ok">No orders in selected period.</div><?php endif; ?></div></section>
  <section class="card span-6"><div class="card-head"><h2>Wallet & Payout Signals</h2><a class="view" href="reports.php?export=wallet&start_date=<?= e($startDate) ?>&end_date=<?= e($endDate) ?>">CSV</a></div><div class="list"><?php foreach (array_slice($walletRows,0,8) as $row): ?><div class="row"><span><strong><?= e((string) $row['description']) ?></strong><br><small><?= e((string) $row['reference']) ?> / <?= e((string) $row['status']) ?></small></span><strong><?= e(marketplace_money((float) $row['amount'])) ?></strong></div><?php endforeach; ?><?php if (!$walletRows): ?><div class="notice ok">No wallet movement in selected period.</div><?php endif; ?></div></section>
</div>
<?php provider_page_end(); ?>