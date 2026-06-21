<?php
declare(strict_types=1);

require_once __DIR__ . '/_user_auth.php';
require_once __DIR__ . '/../lib/dashboard-layout.php';
require_once __DIR__ . '/../lib/field-management.php';
require_once __DIR__ . '/../lib/certificates.php';
require_once __DIR__ . '/../lib/marketplace.php';

$pdo = db();
app_ensure_farmer_engagement_schema($pdo);
app_ensure_certificate_schema($pdo);
fm_ensure_schema($pdo);
marketplace_ensure_schema($pdo);

$user = current_user($pdo);
if (!$user) {
    session_destroy();
    redirect_to('login.php');
}
dashboard_redirect_learner_only($pdo, $user);
$userId = (int) $user['id'];

function stakeholder_scalar(PDO $pdo, string $sql, array $params = [], bool $float = false): int|float
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $value = $stmt->fetchColumn();
        return $float ? (float) ($value ?: 0) : (int) ($value ?: 0);
    } catch (Throwable $e) {
        error_log('Stakeholder report scalar failed: ' . $e->getMessage());
        return $float ? 0.0 : 0;
    }
}

function stakeholder_rows(PDO $pdo, string $sql, array $params = []): array
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log('Stakeholder report rows failed: ' . $e->getMessage());
        return [];
    }
}

function stakeholder_money(float $amount): string
{
    return 'NGN ' . number_format($amount, 2);
}

function stakeholder_csv(string $filename, array $rows): void
{
    app_export_csv($filename, $rows ? array_keys($rows[0]) : [], $rows);
}

$report = (string) ($_GET['report'] ?? 'overview');
$allowedReports = ['overview', 'farm', 'marketplace_buyer', 'marketplace_seller', 'finance', 'support', 'compliance'];
if (!in_array($report, $allowedReports, true)) {
    $report = 'overview';
}

$startDate = (string) ($_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days')));
$endDate = (string) ($_GET['end_date'] ?? date('Y-m-d'));
$periodStart = $startDate . ' 00:00:00';
$periodEnd = $endDate . ' 23:59:59';

$seller = marketplace_current_seller($pdo, $userId);
$sellerId = $seller ? (int) $seller['id'] : 0;

$farmCount = stakeholder_scalar($pdo, "SELECT COUNT(*) FROM grower_farms WHERE user_id = ?", [$userId]);
$farmHectares = stakeholder_scalar($pdo, "SELECT COALESCE(SUM(farm_size), 0) FROM grower_farms WHERE user_id = ?", [$userId], true);
$verifiedFarms = stakeholder_scalar($pdo, "
    SELECT COUNT(*)
    FROM farm_verifications fv
    JOIN grower_farms gf ON gf.id = fv.farm_id
    WHERE gf.user_id = ? AND fv.status = 'verified'
", [$userId]);
$openFieldTasks = stakeholder_scalar($pdo, "
    SELECT COUNT(*)
    FROM field_tasks ft
    JOIN grower_farms gf ON gf.id = ft.farm_id
    WHERE gf.user_id = ? AND ft.status NOT IN ('completed','cancelled')
", [$userId]);
$fieldVisits = stakeholder_scalar($pdo, "
    SELECT COUNT(*)
    FROM farm_visits fv
    JOIN grower_farms gf ON gf.id = fv.farm_id
    WHERE gf.user_id = ? AND fv.visited_at BETWEEN ? AND ?
", [$userId, $periodStart, $periodEnd]);
$documentsTotal = stakeholder_scalar($pdo, "SELECT COUNT(*) FROM document_requirements WHERE user_id = ?", [$userId]);
$documentsVerified = stakeholder_scalar($pdo, "SELECT COUNT(*) FROM document_requirements WHERE user_id = ? AND verification_status = 'verified'", [$userId]);
$documentsPending = stakeholder_scalar($pdo, "SELECT COUNT(*) FROM document_requirements WHERE user_id = ? AND verification_status IN ('pending','needs_review')", [$userId]);
$certificateCount = stakeholder_scalar($pdo, "SELECT COUNT(*) FROM certificates WHERE user_id = ? OR application_id = (SELECT application_id FROM users WHERE id = ? LIMIT 1)", [$userId, $userId]);
$walletBalance = stakeholder_scalar($pdo, "SELECT COALESCE(balance, 0) FROM wallets WHERE user_id = ?", [$userId], true);
$walletVolume = stakeholder_scalar($pdo, "
    SELECT COALESCE(SUM(wt.amount), 0)
    FROM wallet_transactions wt
    JOIN wallets w ON w.id = wt.wallet_id
    WHERE w.user_id = ? AND wt.created_at BETWEEN ? AND ?
", [$userId, $periodStart, $periodEnd], true);
$buyerOrders = stakeholder_scalar($pdo, "SELECT COUNT(*) FROM marketplace_orders WHERE buyer_user_id = ? AND created_at BETWEEN ? AND ?", [$userId, $periodStart, $periodEnd]);
$buyerSpend = stakeholder_scalar($pdo, "SELECT COALESCE(SUM(total_amount), 0) FROM marketplace_orders WHERE buyer_user_id = ? AND created_at BETWEEN ? AND ?", [$userId, $periodStart, $periodEnd], true);
$buyerInquiries = stakeholder_scalar($pdo, "SELECT COUNT(*) FROM marketplace_inquiries WHERE buyer_user_id = ? AND created_at BETWEEN ? AND ?", [$userId, $periodStart, $periodEnd]);
$savedListings = stakeholder_scalar($pdo, "SELECT COUNT(*) FROM marketplace_favorites WHERE user_id = ?", [$userId]);
$sellerListings = $sellerId > 0 ? stakeholder_scalar($pdo, "SELECT COUNT(*) FROM marketplace_listings WHERE seller_id = ?", [$sellerId]) : 0;
$sellerPending = $sellerId > 0 ? stakeholder_scalar($pdo, "SELECT COUNT(*) FROM marketplace_listings WHERE seller_id = ? AND approval_status <> 'approved'", [$sellerId]) : 0;
$sellerOrders = $sellerId > 0 ? stakeholder_scalar($pdo, "SELECT COUNT(*) FROM marketplace_orders WHERE seller_id = ? AND created_at BETWEEN ? AND ?", [$sellerId, $periodStart, $periodEnd]) : 0;
$sellerRevenue = $sellerId > 0 ? stakeholder_scalar($pdo, "SELECT COALESCE(SUM(total_amount), 0) FROM marketplace_orders WHERE seller_id = ? AND status <> 'cancelled' AND created_at BETWEEN ? AND ?", [$sellerId, $periodStart, $periodEnd], true) : 0.0;
$supportOpen = stakeholder_scalar($pdo, "SELECT COUNT(*) FROM messages WHERE user_id = ? AND status IN ('open','in_progress')", [$userId]);
$supportTotal = stakeholder_scalar($pdo, "SELECT COUNT(*) FROM messages WHERE user_id = ?", [$userId]);

$farmRows = stakeholder_rows($pdo, "
    SELECT gf.farm_name, COALESCE(ns.state_name, '') state_name, COALESCE(nl.lga_name, '') lga_name,
           gf.farm_size, COALESCE(fv.status, 'pending') verification_status, fv.system_confidence_score
    FROM grower_farms gf
    LEFT JOIN nigeria_states ns ON ns.id = gf.state_id
    LEFT JOIN nigeria_lgas nl ON nl.id = gf.lga_id
    LEFT JOIN farm_verifications fv ON fv.farm_id = gf.id
    WHERE gf.user_id = ?
    ORDER BY gf.is_primary DESC, gf.created_at DESC
", [$userId]);
$buyerRows = stakeholder_rows($pdo, "
    SELECT o.order_ref, o.created_at, l.title listing_title, s.store_name, o.total_amount, o.status
    FROM marketplace_orders o
    JOIN marketplace_listings l ON l.id = o.listing_id
    JOIN marketplace_sellers s ON s.id = o.seller_id
    WHERE o.buyer_user_id = ? AND o.created_at BETWEEN ? AND ?
    ORDER BY o.created_at DESC
    LIMIT 100
", [$userId, $periodStart, $periodEnd]);
$sellerRows = $sellerId > 0 ? stakeholder_rows($pdo, "
    SELECT o.order_ref, o.created_at, l.title listing_title, o.buyer_name, o.total_amount, o.status
    FROM marketplace_orders o
    JOIN marketplace_listings l ON l.id = o.listing_id
    WHERE o.seller_id = ? AND o.created_at BETWEEN ? AND ?
    ORDER BY o.created_at DESC
    LIMIT 100
", [$sellerId, $periodStart, $periodEnd]) : [];
$walletRows = stakeholder_rows($pdo, "
    SELECT wt.created_at, wt.type, wt.amount, wt.status, wt.reference, wt.description
    FROM wallet_transactions wt
    JOIN wallets w ON w.id = wt.wallet_id
    WHERE w.user_id = ? AND wt.created_at BETWEEN ? AND ?
    ORDER BY wt.created_at DESC
    LIMIT 100
", [$userId, $periodStart, $periodEnd]);
$supportRows = stakeholder_rows($pdo, "
    SELECT ticket_id, category, priority, status, is_from_admin, created_at, message
    FROM messages
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 100
", [$userId]);

$exportRows = match ($report) {
    'farm' => $farmRows,
    'marketplace_buyer' => $buyerRows,
    'marketplace_seller' => $sellerRows,
    'finance' => $walletRows,
    'support' => $supportRows,
    'compliance' => stakeholder_rows($pdo, "SELECT document_type, document_number, verification_status, verified, uploaded_at FROM document_requirements WHERE user_id = ? ORDER BY uploaded_at DESC", [$userId]),
    default => [
        ['metric' => 'Farms', 'value' => $farmCount],
        ['metric' => 'Verified farms', 'value' => $verifiedFarms],
        ['metric' => 'Wallet balance', 'value' => stakeholder_money($walletBalance)],
        ['metric' => 'Buyer orders in period', 'value' => $buyerOrders],
        ['metric' => 'Seller orders in period', 'value' => $sellerOrders],
        ['metric' => 'Open support tickets', 'value' => $supportOpen],
    ],
};

if (($_GET['format'] ?? '') === 'csv') {
    stakeholder_csv('natcodev-my-' . $report . '-report-' . date('Ymd') . '.csv', $exportRows);
}

$insights = [];
if ($farmCount > 0 && $verifiedFarms < $farmCount) {
    $insights[] = ['warning', 'Farm verification remains open', ($farmCount - $verifiedFarms) . ' farm(s) still need verification or review.'];
}
if ($documentsPending > 0) {
    $insights[] = ['warning', 'Document action needed', $documentsPending . ' document(s) are still pending or need review.'];
}
if ($sellerId > 0 && $sellerPending > 0) {
    $insights[] = ['warning', 'Seller listings awaiting approval', $sellerPending . ' listing(s) are not yet public.'];
}
if ($supportOpen > 0) {
    $insights[] = ['warning', 'Support conversations still open', $supportOpen . ' support ticket(s) need closure or follow-up.'];
}
if (!$insights) {
    $insights[] = ['ok', 'No major stakeholder risks', 'Your reports show no urgent action based on available data.'];
}

dashboard_page_start('My Reports', [
    'active' => 'reports.php',
    'description' => 'Personal reporting for your farms, verification, wallet, marketplace buying, seller activity, support, and compliance.',
    'wide' => true,
    'css' => '
      .report-tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px}
      .report-tabs a{padding:9px 11px;border:1px solid var(--line);border-radius:6px;background:#fff;color:var(--green)}
      .report-tabs a.active,.report-tabs a:hover{background:#edf6e8;text-decoration:none}
      .signal{border-left:5px solid var(--leaf)}
      .signal.warning{border-left-color:var(--warn)}
      .report-kpis{grid-template-columns:repeat(auto-fit,minmax(190px,1fr))}
    ',
]);
?>
<form class="panel actions" method="get">
  <input type="hidden" name="report" value="<?= e($report) ?>">
  <label>Start<input type="date" name="start_date" value="<?= e($startDate) ?>"></label>
  <label>End<input type="date" name="end_date" value="<?= e($endDate) ?>"></label>
  <button type="submit">Refresh</button>
  <a class="button secondary" href="?<?= e(http_build_query(array_merge($_GET, ['report' => $report, 'format' => 'csv']))) ?>">Export CSV</a>
</form>

<nav class="report-tabs">
  <?php foreach ([
      'overview' => 'Overview',
      'farm' => 'Farm Report',
      'marketplace_buyer' => 'Buyer Report',
      'marketplace_seller' => 'Seller Report',
      'finance' => 'Finance',
      'support' => 'Support',
      'compliance' => 'Compliance',
  ] as $key => $label): ?>
    <a class="<?= $report === $key ? 'active' : '' ?>" href="?<?= e(http_build_query(array_merge($_GET, ['report' => $key, 'format' => null]))) ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</nav>

<section class="grid report-kpis">
  <article class="card"><div class="metric"><?= (int) $farmCount ?></div><strong>Farms</strong><p class="muted"><?= number_format((float) $farmHectares, 1) ?> hectares</p></article>
  <article class="card"><div class="metric"><?= (int) $verifiedFarms ?></div><strong>Verified Farms</strong><p class="muted"><?= (int) $openFieldTasks ?> open field task(s)</p></article>
  <article class="card"><div class="metric"><?= (int) $documentsVerified ?>/<?= (int) $documentsTotal ?></div><strong>Documents</strong><p class="muted"><?= (int) $certificateCount ?> certificate record(s)</p></article>
  <article class="card"><div class="metric"><?= stakeholder_money((float) $walletBalance) ?></div><strong>Wallet Balance</strong><p class="muted"><?= stakeholder_money((float) $walletVolume) ?> period movement</p></article>
  <article class="card"><div class="metric"><?= (int) $buyerOrders ?></div><strong>Buyer Orders</strong><p class="muted"><?= stakeholder_money((float) $buyerSpend) ?> spend</p></article>
  <article class="card"><div class="metric"><?= (int) $sellerOrders ?></div><strong>Seller Orders</strong><p class="muted"><?= stakeholder_money((float) $sellerRevenue) ?> revenue</p></article>
</section>

<section class="grid" style="margin-top:18px;">
  <?php foreach ($insights as $insight): ?>
    <article class="card signal <?= e($insight[0]) ?>"><h2><?= e($insight[1]) ?></h2><p class="muted"><?= e($insight[2]) ?></p></article>
  <?php endforeach; ?>
</section>

<section class="panel" style="margin-top:18px;">
  <h2><?= e(ucwords(str_replace('_', ' ', $report))) ?> Detail</h2>
  <?php if ($report === 'farm'): ?>
    <table><thead><tr><th>Farm</th><th>Location</th><th>Size</th><th>Verification</th><th>Confidence</th></tr></thead><tbody>
      <?php foreach ($farmRows as $row): ?><tr><td><?= e((string) $row['farm_name']) ?></td><td><?= e(trim((string) $row['lga_name'] . ' ' . (string) $row['state_name'])) ?></td><td><?= e((string) $row['farm_size']) ?> ha</td><td><?= e(ucwords(str_replace('_', ' ', (string) $row['verification_status']))) ?></td><td><?= $row['system_confidence_score'] !== null ? e((string) $row['system_confidence_score']) . '%' : 'Pending' ?></td></tr><?php endforeach; ?>
      <?php if (!$farmRows): ?><tr><td colspan="5">No farm records yet.</td></tr><?php endif; ?>
    </tbody></table>
  <?php elseif ($report === 'marketplace_buyer'): ?>
    <table><thead><tr><th>Order</th><th>Listing</th><th>Seller</th><th>Amount</th><th>Status</th></tr></thead><tbody>
      <?php foreach ($buyerRows as $row): ?><tr><td><?= e((string) $row['order_ref']) ?><br><small><?= e((string) $row['created_at']) ?></small></td><td><?= e((string) $row['listing_title']) ?></td><td><?= e((string) $row['store_name']) ?></td><td><?= stakeholder_money((float) $row['total_amount']) ?></td><td><?= e(ucwords(str_replace('_', ' ', (string) $row['status']))) ?></td></tr><?php endforeach; ?>
      <?php if (!$buyerRows): ?><tr><td colspan="5">No buyer orders in this period. You have <?= (int) $buyerInquiries ?> inquiry record(s) and <?= (int) $savedListings ?> saved listing(s).</td></tr><?php endif; ?>
    </tbody></table>
  <?php elseif ($report === 'marketplace_seller'): ?>
    <table><thead><tr><th>Order</th><th>Listing</th><th>Buyer</th><th>Amount</th><th>Status</th></tr></thead><tbody>
      <?php foreach ($sellerRows as $row): ?><tr><td><?= e((string) $row['order_ref']) ?><br><small><?= e((string) $row['created_at']) ?></small></td><td><?= e((string) $row['listing_title']) ?></td><td><?= e((string) $row['buyer_name']) ?></td><td><?= stakeholder_money((float) $row['total_amount']) ?></td><td><?= e(ucwords(str_replace('_', ' ', (string) $row['status']))) ?></td></tr><?php endforeach; ?>
      <?php if (!$sellerRows): ?><tr><td colspan="5"><?= $sellerId > 0 ? 'No seller orders in this period.' : 'Create a Seller Central profile before seller reports become available.' ?></td></tr><?php endif; ?>
    </tbody></table>
    <p class="muted">Seller listing count: <strong><?= (int) $sellerListings ?></strong>. Listings awaiting approval: <strong><?= (int) $sellerPending ?></strong>.</p>
  <?php elseif ($report === 'finance'): ?>
    <table><thead><tr><th>Date</th><th>Type</th><th>Amount</th><th>Status</th><th>Reference</th></tr></thead><tbody>
      <?php foreach ($walletRows as $row): ?><tr><td><?= e((string) $row['created_at']) ?></td><td><?= e((string) $row['type']) ?></td><td><?= stakeholder_money((float) $row['amount']) ?></td><td><?= e((string) $row['status']) ?></td><td><?= e((string) $row['reference']) ?></td></tr><?php endforeach; ?>
      <?php if (!$walletRows): ?><tr><td colspan="5">No wallet transactions in this period.</td></tr><?php endif; ?>
    </tbody></table>
  <?php elseif ($report === 'support'): ?>
    <table><thead><tr><th>Ticket</th><th>Category</th><th>Priority</th><th>Status</th><th>Message</th></tr></thead><tbody>
      <?php foreach ($supportRows as $row): ?><tr><td><?= e((string) ($row['ticket_id'] ?: 'Message')) ?><br><small><?= e((string) $row['created_at']) ?></small></td><td><?= e((string) $row['category']) ?></td><td><?= e((string) $row['priority']) ?></td><td><?= e((string) $row['status']) ?></td><td><?= e(mb_substr((string) $row['message'], 0, 120)) ?></td></tr><?php endforeach; ?>
      <?php if (!$supportRows): ?><tr><td colspan="5">No support records yet.</td></tr><?php endif; ?>
    </tbody></table>
  <?php elseif ($report === 'compliance'): ?>
    <table><thead><tr><th>Area</th><th>Status</th><th>Meaning</th></tr></thead><tbody>
      <tr><td>Documents</td><td><?= (int) $documentsVerified ?>/<?= (int) $documentsTotal ?> verified</td><td>Identity and farm verification readiness.</td></tr>
      <tr><td>Certificates</td><td><?= (int) $certificateCount ?></td><td>Issued or linked certificate records.</td></tr>
      <tr><td>Farm verification</td><td><?= (int) $verifiedFarms ?>/<?= (int) $farmCount ?></td><td>Ground verification progress.</td></tr>
      <tr><td>Support closure</td><td><?= (int) $supportOpen ?> open / <?= (int) $supportTotal ?> total</td><td>Unresolved operational communication.</td></tr>
    </tbody></table>
  <?php else: ?>
    <table><thead><tr><th>Reportable</th><th>Current Reading</th><th>Why It Matters</th></tr></thead><tbody>
      <tr><td>Farm readiness</td><td><?= (int) $verifiedFarms ?>/<?= (int) $farmCount ?> verified</td><td>Determines confidence for support, accreditation, and field programs.</td></tr>
      <tr><td>Marketplace buying</td><td><?= (int) $buyerOrders ?> orders / <?= stakeholder_money((float) $buyerSpend) ?></td><td>Shows demand for inputs, services, labor, and procurement.</td></tr>
      <tr><td>Marketplace selling</td><td><?= (int) $sellerOrders ?> orders / <?= stakeholder_money((float) $sellerRevenue) ?></td><td>Shows seller performance and commercial activity.</td></tr>
      <tr><td>Finance</td><td><?= stakeholder_money((float) $walletBalance) ?> wallet balance</td><td>Tracks funding available for paid services and transactions.</td></tr>
      <tr><td>Support</td><td><?= (int) $supportOpen ?> open ticket(s)</td><td>Shows unresolved issues that need follow-up.</td></tr>
    </tbody></table>
  <?php endif; ?>
</section>
<?php dashboard_page_end(); ?>
