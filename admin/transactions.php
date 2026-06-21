<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../lib/admin-layout.php';

$pdo = db();
admin_require($pdo);

// Filters
$type = $_GET['type'] ?? '';
$status = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$where = [];
$params = [];

if ($type) { $where[] = "wt.type = ?"; $params[] = $type; }
if ($status) { $where[] = "wt.status = ?"; $params[] = $status; }
if ($date_from) { $where[] = "DATE(wt.created_at) >= ?"; $params[] = $date_from; }
if ($date_to) { $where[] = "DATE(wt.created_at) <= ?"; $params[] = $date_to; }

$whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

// Export to CSV
if (isset($_GET['export'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="transactions_export.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['User', 'Amount', 'Type', 'Status', 'Date', 'Reference']);
    
    $stmt = $pdo->prepare("SELECT u.name as user_name, wt.amount, wt.type, wt.status, wt.created_at, wt.reference FROM wallet_transactions wt LEFT JOIN wallets w ON w.id = wt.wallet_id LEFT JOIN users u ON u.id = w.user_id $whereSql ORDER BY wt.created_at DESC");
    $stmt->execute($params);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { fputcsv($output, $row); }
    fclose($output);
    exit;
}

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Fetch Transactions
$transactions = $pdo->prepare("
    SELECT wt.*, u.name as user_name 
    FROM wallet_transactions wt 
    LEFT JOIN wallets w ON w.id = wt.wallet_id 
    LEFT JOIN users u ON u.id = w.user_id 
    $whereSql 
    ORDER BY wt.created_at DESC LIMIT $limit OFFSET $offset
");
$transactions->execute($params);
$transactions = $transactions->fetchAll();

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM wallet_transactions wt $whereSql");
$countStmt->execute($params);
$totalCount = (int)$countStmt->fetchColumn();
$totalPages = ceil($totalCount / $limit);

function wx_e($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function wx_badge($status) {
    $s = strtolower(trim($status));
    if ($s === 'completed') return 'success';
    if ($s === 'pending') return 'pending';
    return 'danger';
}
?>
<?php admin_page_start('Transactions', ['active' => 'transactions.php']); ?>
    <div class="card" style="margin-bottom:20px;">
        <form method="GET" style="display:flex; gap:10px; align-items:flex-end;">
            <div><label>Type</label><select name="type"><option value="">All</option><option value="credit"<?= $type === 'credit' ? ' selected' : '' ?>>Credit</option><option value="debit"<?= $type === 'debit' ? ' selected' : '' ?>>Debit</option></select></div>
            <div><label>Status</label><select name="status"><option value="">All</option><option value="completed"<?= $status === 'completed' ? ' selected' : '' ?>>Completed</option><option value="pending"<?= $status === 'pending' ? ' selected' : '' ?>>Pending</option><option value="failed"<?= $status === 'failed' ? ' selected' : '' ?>>Failed</option></select></div>
            <div><label>From</label><input type="date" name="date_from" value="<?= e($date_from) ?>"></div>
            <div><label>To</label><input type="date" name="date_to" value="<?= e($date_to) ?>"></div>
            <button type="submit" class="button secondary">Filter</button>
            <a href="?export=1&type=<?= e($type) ?>&status=<?= e($status) ?>" class="button">Export CSV</a>
        </form>
    </div>
    <div class="card">
        <table>
            <thead><tr><th>User</th><th>Amount</th><th>Type</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
                <?php foreach($transactions as $tx): ?>
                <tr onclick="window.location='transaction_details.php?id=<?= $tx['id'] ?>'">
                    <td><?= wx_e($tx['user_name'] ?? 'N/A') ?></td>
                    <td>₦<?= number_format($tx['amount'], 2) ?></td>
                    <td><?= wx_e($tx['type']) ?></td>
                    <td><span class="badge badge-<?= wx_badge($tx['status']) ?>"><?= wx_e($tx['status']) ?></span></td>
                    <td><?= wx_e($tx['created_at']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="pagination">
            <?php for($i=1; $i<=$totalPages; $i++): ?>
                <a href="?page=<?= $i ?>&type=<?= e($type) ?>" style="padding:5px 10px; border:1px solid #ddd; text-decoration:none; color:black; <?= $i==$page ? 'background:#eee':'' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    </div>
<?php admin_page_end(); ?>
