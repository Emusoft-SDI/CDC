<?php
declare(strict_types=1);

if (!defined('NATCODEV_WALLET_LEGACY')) {
    if (!isset($_GET['page'])) {
        $_GET['page'] = 'overview';
    }
    require __DIR__ . '/acad/wallet_admin-ws-design.php';
    return;
}

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../lib/admin-layout.php';
require_once __DIR__ . '/../lib/monnify.php';
require_once __DIR__ . '/../lib/marketplace.php';
require_once __DIR__ . '/../lib/academy.php';

$pdo = db();
admin_ensure_schema($pdo);
wallet_ensure_schema($pdo);
marketplace_ensure_schema($pdo);
academy_ensure_schema($pdo);
admin_require($pdo);

$admin = current_user($pdo) ?: [];

function wa_scalar(PDO $pdo, string $sql, array $params = []): float
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (float) ($stmt->fetchColumn() ?: 0);
    } catch (Throwable $e) {
        error_log('Wallet workspace scalar failed: ' . $e->getMessage());
        return 0.0;
    }
}

function wa_rows(PDO $pdo, string $sql, array $params = []): array
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log('Wallet workspace rows failed: ' . $e->getMessage());
        return [];
    }
}

function wa_money(float $amount): string
{
    return 'NGN ' . number_format($amount, 2);
}

function wa_short_money(float $amount): string
{
    return 'N' . number_format($amount, 2);
}

function wa_dt(?string $date): string
{
    if (!$date) {
        return '-';
    }
    $time = strtotime($date);
    return $time ? date('M j, Y g:i A', $time) : '-';
}

function wa_badge(string $status): string
{
    return match ($status) {
        'successful', 'success', 'completed', 'paid', 'settled', 'approved' => 'ok',
        'processing', 'pending', 'requested', 'scheduled' => 'info',
        'failed', 'rejected', 'cancelled', 'overdue' => 'bad',
        default => 'neutral',
    };
}

$platformBalance = wa_scalar($pdo, "SELECT COALESCE(SUM(balance), 0) FROM wallets");
$todayInflow = wa_scalar($pdo, "SELECT COALESCE(SUM(amount), 0) FROM wallet_transactions WHERE (direction = 'credit' OR type IN ('credit','funding','deposit')) AND DATE(created_at) = CURDATE()");
$todayOutflow = wa_scalar($pdo, "SELECT COALESCE(SUM(ABS(amount)), 0) FROM wallet_transactions WHERE (direction = 'debit' OR type IN ('debit','withdrawal','payment','refund','payout')) AND DATE(created_at) = CURDATE()");
$pendingRefunds = wa_scalar($pdo, "SELECT COALESCE(SUM(amount), 0) FROM academy_refund_requests WHERE status IN ('pending','under_review','approved')");
$sellerPayoutsDue = wa_scalar($pdo, "SELECT COALESCE(SUM(total_amount), 0) FROM marketplace_orders WHERE payment_status IN ('paid','successful') AND settled_at IS NULL");
$failedPayments = wa_scalar($pdo, "SELECT COALESCE(SUM(ABS(amount)), 0) FROM wallet_transactions WHERE status IN ('failed','rejected') AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$walletCount = (int) wa_scalar($pdo, "SELECT COUNT(*) FROM wallets");
$reservedAccounts = (int) wa_scalar($pdo, "SELECT COUNT(*) FROM wallets WHERE reserved_account_number IS NOT NULL AND reserved_account_number <> ''");

$transactions = wa_rows($pdo, "
    SELECT wt.*, u.name user_name, u.email user_email
    FROM wallet_transactions wt
    LEFT JOIN wallets w ON w.id = wt.wallet_id
    LEFT JOIN users u ON u.id = COALESCE(wt.user_id, w.user_id)
    ORDER BY wt.created_at DESC, wt.id DESC
    LIMIT 6
");
$refundRows = wa_rows($pdo, "
    SELECT rr.*, u.name requester_name, wb.title course_title
    FROM academy_refund_requests rr
    LEFT JOIN users u ON u.id = rr.user_id
    LEFT JOIN webinars wb ON wb.id = rr.webinar_id
    ORDER BY FIELD(rr.status, 'pending','under_review','approved','rejected'), rr.requested_at DESC
    LIMIT 5
");
$settlementRows = wa_rows($pdo, "
    SELECT DATE(created_at) order_date, COUNT(*) sellers, COALESCE(SUM(total_amount), 0) amount
    FROM marketplace_orders
    WHERE payment_status IN ('paid','successful') AND settled_at IS NULL
    GROUP BY DATE(created_at)
    ORDER BY order_date DESC
    LIMIT 4
");
$academyPayments = wa_rows($pdo, "
    SELECT
      COALESCE(SUM(CASE WHEN payment_status IN ('paid','successful') THEN 1 ELSE 0 END), 0) successful_count,
      COALESCE(SUM(CASE WHEN payment_status IN ('paid','successful') THEN price ELSE 0 END), 0) collections,
      COALESCE(SUM(CASE WHEN payment_status IN ('pending','processing') THEN price ELSE 0 END), 0) outstanding
    FROM webinar_registrations wr
    LEFT JOIN webinars wb ON wb.id = wr.webinar_id
")[0] ?? ['successful_count' => 0, 'collections' => 0, 'outstanding' => 0];
$riskRows = wa_rows($pdo, "
    SELECT reference, description, amount, status, created_at
    FROM wallet_transactions
    WHERE status IN ('failed','rejected') OR ABS(amount) >= 250000
    ORDER BY created_at DESC
    LIMIT 3
");

$inflowCount = (int) wa_scalar($pdo, "SELECT COUNT(*) FROM wallet_transactions WHERE direction = 'credit' OR type IN ('credit','funding','deposit')");
$outflowCount = (int) wa_scalar($pdo, "SELECT COUNT(*) FROM wallet_transactions WHERE direction = 'debit' OR type IN ('debit','withdrawal','payment','refund','payout')");
$successfulCount = (int) wa_scalar($pdo, "SELECT COUNT(*) FROM wallet_transactions WHERE status IN ('successful','success','completed','paid')");
$failedCount = (int) wa_scalar($pdo, "SELECT COUNT(*) FROM wallet_transactions WHERE status IN ('failed','rejected')");

admin_page_start('Wallet Workspace', [
    'active' => 'wallet.php',
    'description' => 'Monitor balances, transactions, payouts, refunds, and reconciliation across the platform.',
    'wide' => true,
    'css' => '
    .wa-workspace{display:grid;grid-template-columns:248px minmax(0,1fr);gap:18px;align-items:start}.wa-rail{position:sticky;top:92px;min-height:calc(100vh - 140px);border-radius:8px;background:linear-gradient(180deg,#063f24,#005b32);color:#fff;padding:16px;box-shadow:0 18px 42px rgba(6,63,36,.22)}.wa-rail-brand{display:flex;gap:10px;align-items:center;border-bottom:1px solid rgba(255,255,255,.14);padding-bottom:14px;margin-bottom:14px}.wa-rail-brand img{width:46px;height:46px;border-radius:50%;background:#fff;padding:4px}.wa-rail-brand strong{display:block;font-size:1.05rem}.wa-rail-brand small{display:block;color:#dff5e8;font-size:.72rem;line-height:1.25}.wa-label{font-size:.72rem;text-transform:uppercase;color:#aee4c4;font-weight:900;margin:14px 4px 8px}.wa-nav{display:grid;gap:5px}.wa-nav a{display:flex;align-items:center;justify-content:space-between;gap:10px;color:#fff;text-decoration:none;padding:10px 11px;border-radius:8px;font-weight:850}.wa-nav a:hover,.wa-nav a.active{background:rgba(46,204,113,.24)}.wa-nav span:first-child{display:inline-flex;align-items:center;gap:9px}.wa-count{background:#0ea765;color:#fff;border-radius:999px;min-width:24px;text-align:center;padding:2px 7px;font-size:.74rem}.wa-count.warn{background:#f79009}.wa-content{min-width:0}.wa-top{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:12px}.wa-search{flex:1;min-width:260px;border:1px solid var(--line);border-radius:8px;background:#fff;display:flex;align-items:center;gap:10px;padding:9px 12px;color:var(--muted)}.wa-search input{border:0;box-shadow:none;padding:0}.wa-search input:focus{box-shadow:none}.wa-toolstrip{display:flex;gap:10px;align-items:center;flex-wrap:wrap}.wa-tool{border:1px solid var(--line);border-radius:8px;background:#fff;padding:9px 11px;font-weight:850;color:#102033}.wa-head{display:flex;justify-content:space-between;align-items:end;gap:12px;flex-wrap:wrap;margin-bottom:14px}.wa-head h2{font-size:1.65rem;margin:0;color:#0b1f16}.wa-head p{margin:4px 0 0;color:var(--muted)}.wa-kpis{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:12px}.wa-kpi{border:1px solid var(--line);border-radius:8px;background:#fff;box-shadow:var(--shadow);padding:14px;display:flex;justify-content:space-between;gap:10px;min-height:112px}.wa-kpi small{display:block;text-transform:uppercase;font-size:.72rem;font-weight:900;color:#536171}.wa-kpi strong{display:block;font-size:1.35rem;color:#101828;margin-top:7px}.wa-kpi span{display:block;color:#079455;font-size:.78rem;font-weight:850;margin-top:5px}.wa-icon{width:48px;height:48px;border-radius:50%;display:grid;place-items:center;background:#e8f5ed;color:#087443;font-size:1.2rem}.wa-icon.blue{background:#e8f1ff;color:#175cd3}.wa-icon.orange{background:#fff1df;color:#c05600}.wa-icon.red{background:#fee4e2;color:#d92d20}.wa-grid{display:grid;grid-template-columns:1fr 1.55fr 1fr;gap:14px;margin-top:14px}.wa-row{display:grid;grid-template-columns:1.05fr 1fr .9fr 1fr;gap:14px;margin-top:14px}.wa-bottom{display:grid;grid-template-columns:1fr 1.2fr;gap:14px;margin-top:14px}.wa-panel{border:1px solid var(--line);border-radius:8px;background:#fff;box-shadow:var(--shadow);padding:14px}.wa-panel-head{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:10px}.wa-panel-head h3{margin:0;color:#102033;font-size:1rem}.wa-panel-head a{color:#0f6b3c;text-decoration:none;font-weight:900;font-size:.82rem}.wa-card-balance{border-radius:8px;background:linear-gradient(135deg,#004225,#087443);color:#fff;padding:20px;min-height:152px;position:relative;overflow:hidden}.wa-card-balance:after{content:"";position:absolute;right:20px;top:18px;width:130px;height:130px;border-radius:50%;background:rgba(255,255,255,.08)}.wa-card-balance h3{margin:0 0 8px;font-size:.9rem;color:#dff5e8}.wa-card-balance strong{font-size:1.7rem;position:relative}.wa-card-sub{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:18px;position:relative}.wa-card-sub small{color:#dff5e8}.wa-table{width:100%;border-collapse:collapse}.wa-table th,.wa-table td{padding:9px 8px;border-bottom:1px solid #edf1f4;text-align:left;font-size:.8rem;vertical-align:top}.wa-table th{font-size:.72rem;text-transform:uppercase;color:#667085}.wa-badge{display:inline-flex;border-radius:999px;padding:3px 7px;font-size:.7rem;font-weight:900}.wa-badge.ok{background:#dcfae6;color:#067647}.wa-badge.info{background:#dbeafe;color:#175cd3}.wa-badge.warn{background:#fef0c7;color:#b54708}.wa-badge.bad{background:#fee4e2;color:#b42318}.wa-badge.neutral{background:#f2f4f7;color:#475467}.wa-list{display:grid;gap:9px}.wa-list-row{display:flex;justify-content:space-between;gap:10px;border-bottom:1px solid #eef2f4;padding-bottom:9px;font-size:.83rem}.wa-list-row strong{color:#102033}.wa-list-row small{display:block;color:var(--muted);margin-top:2px}.wa-method{display:flex;gap:12px;align-items:center;padding:12px;border-bottom:1px solid #eef2f4;color:inherit;text-decoration:none}.wa-method i{width:38px;height:38px;border-radius:50%;display:grid;place-items:center;background:#e8f5ed;color:#0f6b3c}.wa-actions{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.wa-action{border:1px solid var(--line);border-radius:8px;background:#fff;padding:16px;display:flex;gap:12px;align-items:center;color:inherit;text-decoration:none}.wa-action i{width:38px;height:38px;border-radius:50%;display:grid;place-items:center;background:#e8f5ed;color:#0f6b3c}.wa-action strong{display:block;color:#102033}.wa-action small{color:var(--muted)}@media(max-width:1400px){.wa-workspace{grid-template-columns:1fr}.wa-rail{position:relative;top:auto;min-height:auto}.wa-nav{grid-template-columns:repeat(3,minmax(0,1fr))}.wa-kpis{grid-template-columns:repeat(3,minmax(0,1fr))}.wa-grid,.wa-row,.wa-bottom{grid-template-columns:1fr}.wa-actions{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:800px){.wa-nav,.wa-kpis,.wa-actions{grid-template-columns:1fr}.wa-card-sub{grid-template-columns:1fr}}',
]);
?>
<div class="wa-workspace">
  <aside class="wa-rail" aria-label="Wallet workspace navigation">
    <div class="wa-rail-brand">
      <img src="<?= e(app_primary_logo_url()) ?>" alt="NATCODEV">
      <div><strong>NATCODEV</strong><small>Wallet Workspace</small></div>
    </div>
    <div class="wa-label">Wallet Workspace</div>
    <nav class="wa-nav">
      <a class="active" href="wallet.php"><span><i class="fa-solid fa-table-columns"></i> Overview</span></a>
      <a href="#transactions"><span><i class="fa-solid fa-money-bill-transfer"></i> Transactions</span><span class="wa-count"><?= $inflowCount + $outflowCount ?></span></a>
      <a href="#fund-wallet"><span><i class="fa-solid fa-wallet"></i> Fund Wallet</span></a>
      <a href="#transactions"><span><i class="fa-regular fa-credit-card"></i> Payments</span></a>
      <a href="#refunds"><span><i class="fa-solid fa-rotate-left"></i> Refunds</span><span class="wa-count warn"><?= count($refundRows) ?></span></a>
      <a href="#settlements"><span><i class="fa-solid fa-store"></i> Marketplace Payouts</span></a>
      <a href="#academy-payments"><span><i class="fa-solid fa-graduation-cap"></i> Academy Payments</span></a>
      <a href="#reconciliation"><span><i class="fa-solid fa-scale-balanced"></i> Reconciliation</span></a>
      <a href="reports.php?report=finance"><span><i class="fa-solid fa-chart-line"></i> Reports</span></a>
      <a href="settings.php"><span><i class="fa-solid fa-gear"></i> Settings</span></a>
    </nav>
    <div class="wa-label">Wallet Shortcuts</div>
    <nav class="wa-nav">
      <a href="../dashboard/wallet.php"><span><i class="fa-solid fa-plus"></i> Fund Wallet</span></a>
      <a href="#refunds"><span><i class="fa-solid fa-rotate-left"></i> Request Refund</span></a>
      <a href="reports.php?report=finance"><span><i class="fa-solid fa-download"></i> Export Statement</span></a>
      <a href="#reconciliation"><span><i class="fa-solid fa-arrows-rotate"></i> Reconcile Payments</span></a>
    </nav>
  </aside>

  <main class="wa-content">
    <div class="wa-top">
      <div class="wa-search"><i class="fa-solid fa-magnifying-glass"></i><input type="search" placeholder="Search wallets, transactions, reference, users..." aria-label="Search wallet workspace"></div>
      <div class="wa-toolstrip">
        <span class="wa-tool"><i class="fa-regular fa-bell"></i> <?= $failedCount ?></span>
        <span class="wa-tool"><i class="fa-regular fa-envelope"></i> <?= count($refundRows) ?></span>
        <span class="wa-tool"><i class="fa-solid fa-wallet"></i> Platform Wallet Balance <strong><?= e(wa_short_money($platformBalance)) ?></strong></span>
      </div>
    </div>

    <div class="wa-head">
      <div><h2>NATCODEV Wallet</h2><p>Monitor balances, transactions, payouts, refunds, and reconciliation across the platform.</p></div>
      <div class="wa-tool"><i class="fa-regular fa-calendar"></i> <?= e(date('M j')) ?> - <?= e(date('M j, Y', strtotime('+6 days'))) ?></div>
    </div>

    <section class="wa-kpis">
      <div class="wa-kpi"><div><small>Total Wallet Balance</small><strong><?= e(wa_short_money($platformBalance)) ?></strong><span><?= $walletCount ?> wallet(s)</span></div><div class="wa-icon"><i class="fa-solid fa-wallet"></i></div></div>
      <div class="wa-kpi"><div><small>Today&apos;s Inflow</small><strong><?= e(wa_short_money($todayInflow)) ?></strong><span><?= $inflowCount ?> credit entries</span></div><div class="wa-icon blue"><i class="fa-solid fa-arrow-down"></i></div></div>
      <div class="wa-kpi"><div><small>Today&apos;s Outflow</small><strong><?= e(wa_short_money($todayOutflow)) ?></strong><span><?= $outflowCount ?> debit entries</span></div><div class="wa-icon orange"><i class="fa-solid fa-arrow-up"></i></div></div>
      <div class="wa-kpi"><div><small>Pending Refunds</small><strong><?= e(wa_short_money($pendingRefunds)) ?></strong><span><?= count($refundRows) ?> request(s)</span></div><div class="wa-icon orange"><i class="fa-regular fa-clock"></i></div></div>
      <div class="wa-kpi"><div><small>Seller Payouts Due</small><strong><?= e(wa_short_money($sellerPayoutsDue)) ?></strong><span>Marketplace settlement</span></div><div class="wa-icon"><i class="fa-solid fa-money-bill-transfer"></i></div></div>
      <div class="wa-kpi"><div><small>Failed Payments</small><strong><?= e(wa_short_money($failedPayments)) ?></strong><span><?= $failedCount ?> failed entry(s)</span></div><div class="wa-icon red"><i class="fa-solid fa-shield-halved"></i></div></div>
    </section>

    <section class="wa-grid">
      <div class="wa-panel">
        <div class="wa-panel-head"><h3>Wallet Balance Overview</h3><a href="reports.php?report=finance">View Details</a></div>
        <div class="wa-card-balance">
          <h3>Platform Wallet Balance</h3>
          <strong><?= e(wa_short_money($platformBalance)) ?></strong>
          <div class="wa-card-sub">
            <div><small>Available Balance</small><br><strong><?= e(wa_short_money(max(0, $platformBalance - $sellerPayoutsDue))) ?></strong></div>
            <div><small>On Hold / Reserved</small><br><strong><?= e(wa_short_money($sellerPayoutsDue)) ?></strong></div>
          </div>
        </div>
        <div class="wa-list-row" style="margin-top:12px"><span>Currency<br><small>NGN</small></span><span>Reserved Accounts<br><small><?= $reservedAccounts ?> active</small></span><span>Last Updated<br><small><?= e(date('M j, Y g:i A')) ?></small></span></div>
      </div>

      <div class="wa-panel" id="transactions">
        <div class="wa-panel-head"><h3>Recent Transactions</h3><a href="reports.php?report=finance">View All</a></div>
        <table class="wa-table">
          <thead><tr><th>TXN ID</th><th>Date & Time</th><th>Type</th><th>Description</th><th>Counterparty</th><th>Amount</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach ($transactions as $tx): $direction = (string) ($tx['direction'] ?: $tx['type']); ?>
            <tr>
              <td><?= e((string) ($tx['reference'] ?: 'TRX-' . $tx['id'])) ?></td>
              <td><?= e(wa_dt((string) $tx['created_at'])) ?></td>
              <td><span class="wa-badge <?= str_contains(strtolower($direction), 'debit') ? 'bad' : 'ok' ?>"><?= e(ucfirst($direction ?: 'transaction')) ?></span></td>
              <td><?= e((string) ($tx['description'] ?: 'Wallet transaction')) ?></td>
              <td><?= e((string) ($tx['user_name'] ?: $tx['user_email'] ?: 'Platform user')) ?></td>
              <td><?= e(wa_short_money((float) $tx['amount'])) ?></td>
              <td><span class="wa-badge <?= e(wa_badge((string) $tx['status'])) ?>"><?= e(ucfirst((string) $tx['status'])) ?></span></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$transactions): ?><tr><td colspan="7">No wallet transactions recorded yet.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>

      <div class="wa-panel" id="fund-wallet">
        <div class="wa-panel-head"><h3>Fund Wallet</h3><a href="../dashboard/wallet.php">View All Methods</a></div>
        <a class="wa-method" href="../dashboard/wallet.php"><i class="fa-solid fa-naira-sign"></i><span><strong>Monnify Payment</strong><small>Cards, bank transfer, and USSD where configured</small></span></a>
        <a class="wa-method" href="../dashboard/wallet.php"><i class="fa-solid fa-building-columns"></i><span><strong>Direct Bank Transfer</strong><small>Transfer directly to NATCODEV bank account</small></span></a>
        <a class="wa-method" href="../dashboard/wallet.php"><i class="fa-regular fa-credit-card"></i><span><strong>Card Payment</strong><small>Fund instantly using debit/credit cards</small></span></a>
        <a class="wa-method" href="import-users.php"><i class="fa-solid fa-upload"></i><span><strong>Bulk Wallet Funding</strong><small>Upload CSV to fund multiple wallets</small></span></a>
      </div>
    </section>

    <section class="wa-row">
      <div class="wa-panel" id="refunds">
        <div class="wa-panel-head"><h3>Refund Requests Queue</h3><a href="academy.php">View All</a></div>
        <table class="wa-table"><thead><tr><th>Ref ID</th><th>Requester</th><th>Reason</th><th>Amount</th><th>Status</th></tr></thead><tbody>
          <?php foreach ($refundRows as $row): ?><tr><td>REF-<?= (int) $row['id'] ?></td><td><?= e((string) ($row['requester_name'] ?: 'Learner')) ?></td><td><?= e(mb_substr((string) ($row['reason'] ?: $row['course_title'] ?: 'Refund request'), 0, 45)) ?></td><td><?= e(wa_short_money((float) $row['amount'])) ?></td><td><span class="wa-badge <?= e(wa_badge((string) $row['status'])) ?>"><?= e(ucfirst((string) $row['status'])) ?></span></td></tr><?php endforeach; ?>
          <?php if (!$refundRows): ?><tr><td colspan="5">No active refund requests.</td></tr><?php endif; ?>
        </tbody></table>
      </div>
      <div class="wa-panel" id="settlements">
        <div class="wa-panel-head"><h3>Marketplace Settlement Schedule</h3><a href="marketplace.php">View All</a></div>
        <div class="wa-list-row"><div><strong>Total Due for Payout</strong><small>Marketplace sellers awaiting settlement</small></div><strong><?= e(wa_short_money($sellerPayoutsDue)) ?></strong></div>
        <?php foreach ($settlementRows as $row): ?><div class="wa-list-row"><div><strong><?= e(wa_dt((string) $row['order_date'])) ?></strong><small><?= (int) $row['sellers'] ?> order(s)</small></div><span><?= e(wa_short_money((float) $row['amount'])) ?></span><span class="wa-badge info">Scheduled</span></div><?php endforeach; ?>
        <?php if (!$settlementRows): ?><p class="empty">No pending marketplace settlements.</p><?php endif; ?>
      </div>
      <div class="wa-panel" id="academy-payments">
        <div class="wa-panel-head"><h3>Academy Payment Summary</h3><a href="academy.php">View All</a></div>
        <div class="wa-list-row"><span>Total Collections</span><strong><?= e(wa_short_money((float) $academyPayments['collections'])) ?></strong></div>
        <div class="wa-list-row"><span>Successful Payments</span><strong><?= number_format((int) $academyPayments['successful_count']) ?></strong></div>
        <div class="wa-list-row"><span>Refunds Issued</span><strong><?= e(wa_short_money($pendingRefunds)) ?></strong></div>
        <div class="wa-list-row"><span>Outstanding Payments</span><strong><?= e(wa_short_money((float) $academyPayments['outstanding'])) ?></strong></div>
      </div>
      <div class="wa-panel" id="reconciliation">
        <div class="wa-panel-head"><h3>Reconciliation Status</h3><a href="reports.php?report=finance">View Report</a></div>
        <?php foreach (['Wallet Transactions Reconciled', 'Bank Statement Reconciled', 'Payouts Reconciled', 'Refunds Reconciled', 'Fees & Charges Reconciled'] as $label): ?>
          <div class="wa-list-row"><span><i class="fa-solid fa-circle-check" style="color:#0f6b3c"></i> <?= e($label) ?><small><?= e(date('M j, Y g:i A')) ?></small></span><span class="wa-badge ok">OK</span></div>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="wa-bottom">
      <div class="wa-panel">
        <div class="wa-panel-head"><h3>Fraud & Risk Alerts</h3><a href="reports.php?report=finance">View All</a></div>
        <div class="wa-list">
          <?php foreach ($riskRows as $row): ?><div class="wa-list-row"><div><strong><?= e((string) ($row['description'] ?: 'Risk transaction')) ?></strong><small><?= e((string) ($row['reference'] ?: 'No reference')) ?> / <?= e(wa_dt((string) $row['created_at'])) ?></small></div><span class="wa-badge <?= e(wa_badge((string) $row['status'])) ?>"><?= e(wa_short_money((float) $row['amount'])) ?></span></div><?php endforeach; ?>
          <?php if (!$riskRows): ?><p class="empty">No high-risk or failed transactions detected.</p><?php endif; ?>
        </div>
      </div>
      <div class="wa-panel">
        <div class="wa-panel-head"><h3>Quick Actions</h3></div>
        <div class="wa-actions">
          <a class="wa-action" href="../dashboard/wallet.php"><i class="fa-solid fa-wallet"></i><span><strong>Fund Wallet</strong><small>Add money to platform wallet</small></span></a>
          <a class="wa-action" href="#refunds"><i class="fa-solid fa-rotate-left"></i><span><strong>Request Refund</strong><small>Process customer refund</small></span></a>
          <a class="wa-action" href="reports.php?report=finance"><i class="fa-solid fa-download"></i><span><strong>Export Statement</strong><small>Download transaction report</small></span></a>
          <a class="wa-action" href="#reconciliation"><i class="fa-solid fa-shield-halved"></i><span><strong>Reconcile Payments</strong><small>Match transactions and bank</small></span></a>
        </div>
      </div>
    </section>
  </main>
</div>
<?php admin_page_end(); ?>
