<?php
declare(strict_types=1);
require_once __DIR__ . '/_coordination.php';

$pdo = coord_pdo();
$user = coord_require($pdo);
$scope = coord_scope_filter($pdo, $user);
$where = $scope['where'];
$params = $scope['params'];
$stateLabel = (string) ($scope['label'] ?: 'National');

// Scoping mechanism: wallets, transactions, and withdrawals are tied to the
// coordinator's state through the wallet owner's application record (alias `a`),
// so the single coord_state_filter() fragment scopes every wallet query.
$userScopeJoin = 'LEFT JOIN applications a ON a.id = u.application_id LEFT JOIN nigeria_states ns ON ns.id = a.state_id';

$wallet = wallet_get_or_create($pdo, (int) $user['id']);

$walletsInScope = coord_scalar($pdo, "SELECT COUNT(*) FROM wallets w LEFT JOIN users u ON u.id = w.user_id {$userScopeJoin} WHERE {$where}", $params);
$balanceRow = coord_rows($pdo, "SELECT COALESCE(SUM(w.balance), 0) AS total, COALESCE(SUM(w.hold_balance), 0) AS held FROM wallets w LEFT JOIN users u ON u.id = w.user_id {$userScopeJoin} WHERE {$where}", $params);
$balanceInScope = (float) ($balanceRow[0]['total'] ?? 0);
$holdInScope = (float) ($balanceRow[0]['held'] ?? 0);
$transactionsCount = coord_scalar($pdo, "SELECT COUNT(*) FROM wallet_transactions wt LEFT JOIN users u ON u.id = wt.user_id {$userScopeJoin} WHERE {$where}", $params);
$transactions = coord_rows($pdo, "SELECT wt.id, wt.type, wt.direction, wt.amount, wt.status, wt.reference, wt.description, wt.created_at, u.name user_name, u.email user_email FROM wallet_transactions wt LEFT JOIN users u ON u.id = wt.user_id {$userScopeJoin} WHERE {$where} ORDER BY wt.created_at DESC, wt.id DESC LIMIT 50", $params);
$withdrawalCount = coord_scalar($pdo, "SELECT COUNT(*) FROM wallet_withdrawals wd LEFT JOIN users u ON u.id = wd.user_id {$userScopeJoin} WHERE {$where} AND wd.status IN ('pending','processing')", $params);
$withdrawalAmountRow = coord_rows($pdo, "SELECT COALESCE(SUM(wd.amount), 0) AS total FROM wallet_withdrawals wd LEFT JOIN users u ON u.id = wd.user_id {$userScopeJoin} WHERE {$where} AND wd.status IN ('pending','processing')", $params);
$withdrawalAmount = (float) ($withdrawalAmountRow[0]['total'] ?? 0);
$withdrawals = coord_rows($pdo, "SELECT wd.id, wd.reference, wd.amount, wd.charge, wd.final_amount, wd.provider, wd.bank_name, wd.account_name, wd.account_number, wd.status, wd.payout_status, wd.requested_at, u.name user_name, u.email user_email FROM wallet_withdrawals wd LEFT JOIN users u ON u.id = wd.user_id {$userScopeJoin} WHERE {$where} AND wd.status IN ('pending','processing') ORDER BY wd.requested_at DESC, wd.id DESC LIMIT 50", $params);

function coord_mask_account(string $account): string
{
    if ($account === '') {
        return '-';
    }
    $length = strlen($account);
    if ($length <= 4) {
        return str_repeat('*', $length);
    }
    return str_repeat('*', $length - 4) . substr($account, -4);
}

coord_header('Coordination Wallet', 'Manage coordinator allowance, wallet activity, withdrawal status, and finance support.', $user, 'wallet');
?>
<style>
.coord-table{width:100%;border-collapse:collapse}
.coord-table th,.coord-table td{padding:10px 8px;border-bottom:1px solid var(--line);text-align:left;font-size:.86rem;vertical-align:top}
.coord-table th{font-size:.72rem;text-transform:uppercase;color:var(--muted)}
.coord-empty{color:var(--muted);padding:14px;border:1px dashed var(--line);border-radius:8px}
</style>
<?php if (!empty($scope['missing'])): ?><div class="card" style="margin-bottom:16px;border-color:#facc15;background:#fffbeb"><h2><?= e(ucfirst((string) ($scope['tier'] ?? 'state'))) ?> Assignment Required</h2><p>This coordinator has access, but no <?= e((string) ($scope['tier'] ?? 'state')) ?> scope is assigned. An operator must assign the enclave in RBAC before wallet data can appear here.</p></div><?php endif; ?>
<div class="kpis"><div class="kpi"><i class="fas fa-wallet"></i><b>NGN <?= e(number_format((float) ($wallet['balance'] ?? 0), 2)) ?></b><span>My wallet balance</span></div><div class="kpi"><i class="fas fa-users"></i><b><?= number_format($walletsInScope) ?></b><span>Wallets in scope</span></div><div class="kpi"><i class="fas fa-coins"></i><b>NGN <?= e(number_format($balanceInScope, 2)) ?></b><span>Balance in scope</span></div><div class="kpi"><i class="fas fa-exchange-alt"></i><b><?= number_format($transactionsCount) ?></b><span>Transactions in scope</span></div><div class="kpi"><i class="fas fa-file-invoice-dollar"></i><b><?= number_format($withdrawalCount) ?></b><span>Pending withdrawals</span></div><div class="kpi"><i class="fas fa-map-location-dot"></i><b><?= e($stateLabel) ?></b><span>Operating scope</span></div></div>
<div class="grid">
<section class="card span-8"><h2>Recent Wallet Transactions — <?= e($stateLabel) ?> (latest 50)</h2><table class="coord-table"><thead><tr><th>Date</th><th>User</th><th>Type / Direction</th><th>Amount</th><th>Status</th><th>Reference</th></tr></thead><tbody><?php foreach ($transactions as $row): ?><tr><td><?= e($row['created_at'] ? date('M j, Y h:i A', strtotime((string) $row['created_at'])) : '-') ?></td><td><strong><?= e((string) ($row['user_name'] ?: 'Unknown user')) ?></strong><br><small><?= e((string) ($row['user_email'] ?: '')) ?></small></td><td><?= e(ucwords(str_replace('_', ' ', (string) ($row['type'] ?? '')))) ?><br><small><?= e((string) ($row['direction'] ?: '-')) ?><?php if ((string) ($row['description'] ?? '') !== ''): ?> — <?= e((string) $row['description']) ?><?php endif; ?></small></td><td><strong>NGN <?= e(number_format((float) ($row['amount'] ?? 0), 2)) ?></strong></td><td><span class="badge"><?= e((string) ($row['status'] ?? '')) ?></span></td><td><small><?= e((string) ($row['reference'] ?: '-')) ?></small></td></tr><?php endforeach; ?><?php if (!$transactions): ?><tr><td colspan="6"><div class="coord-empty">No wallet transactions recorded for users in this scope yet.</div></td></tr><?php endif; ?></tbody></table></section>
<section class="card span-4"><h2>Wallet Oversight Notes</h2><div class="list"><div class="row"><span><strong>Held funds in scope</strong><br><small>Amounts locked by pending withdrawal requests</small></span><span class="badge">NGN <?= e(number_format($holdInScope, 2)) ?></span></div><div class="row"><span><strong>Pending withdrawal value</strong><br><small>Requested but not yet paid out</small></span><span class="badge">NGN <?= e(number_format($withdrawalAmount, 2)) ?></span></div></div><p style="color:var(--muted);margin-top:12px">Coordinator wallet access is read-only. Money movement, withdrawal approvals, and payouts remain with platform operators — use the support queue to escalate wallet issues.</p><div class="list"><a class="row" href="operations.php"><strong>Operations queue</strong><span class="badge">Lead</span></a><a class="row" href="reports.php"><strong>Reports</strong><span class="badge">Report</span></a><a class="row" href="support.php"><strong>Support queue</strong><span class="badge">Support</span></a><a class="row" href="<?= e(coord_home(coord_role_key($user))) ?>"><strong>Coordinator home</strong><span class="badge">Home</span></a></div></section>
<section class="card span-12"><h2>Pending Withdrawal Requests In Scope</h2><table class="coord-table"><thead><tr><th>Requested</th><th>User</th><th>Reference</th><th>Amount</th><th>Charge</th><th>Final Amount</th><th>Provider / Bank</th><th>Account</th><th>Status</th></tr></thead><tbody><?php foreach ($withdrawals as $row): ?><tr><td><?= e($row['requested_at'] ? date('M j, Y h:i A', strtotime((string) $row['requested_at'])) : '-') ?></td><td><strong><?= e((string) ($row['user_name'] ?: 'Unknown user')) ?></strong><br><small><?= e((string) ($row['user_email'] ?: '')) ?></small></td><td><small><?= e((string) ($row['reference'] ?: '-')) ?></small></td><td><strong>NGN <?= e(number_format((float) ($row['amount'] ?? 0), 2)) ?></strong></td><td>NGN <?= e(number_format((float) ($row['charge'] ?? 0), 2)) ?></td><td>NGN <?= e(number_format((float) ($row['final_amount'] ?? 0), 2)) ?></td><td><?= e(ucwords(str_replace('_', ' ', (string) ($row['provider'] ?: 'manual')))) ?><br><small><?= e((string) ($row['bank_name'] ?: '-')) ?></small></td><td><?= e((string) ($row['account_name'] ?: '-')) ?><br><small><?= e(coord_mask_account((string) ($row['account_number'] ?? ''))) ?></small></td><td><span class="badge"><?= e((string) ($row['status'] ?? '')) ?></span><?php if ((string) ($row['payout_status'] ?? '') !== ''): ?> <small><?= e((string) $row['payout_status']) ?></small><?php endif; ?></td></tr><?php endforeach; ?><?php if (!$withdrawals): ?><tr><td colspan="9"><div class="coord-empty">No pending or processing withdrawal requests for users in this scope.</div></td></tr><?php endif; ?></tbody></table></section>
</div>
<?php coord_footer(); ?>
