<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../lib/admin-layout.php';
$pdo = db();
admin_require($pdo);

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header("Location: marketplace_payouts.php"); exit; }

// Handle Approval/Rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        http_response_code(403);
        exit('Invalid security token.');
    }
    $action = (string) $_POST['action'];
    if (!in_array($action, ['approve', 'reject'], true)) {
        http_response_code(422);
        exit('Invalid payout action.');
    }
    $newStatus = ($action === 'approve') ? 'approved' : 'rejected';
    
    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE marketplace_orders SET payout_status = ? WHERE id = ?")
            ->execute([$newStatus, $id]);
            
        $pdo->prepare("INSERT INTO audit_log (action, description) VALUES (?, ?)")
            ->execute(["payout_$newStatus", "Admin $action marketplace payout #$id"]);
            
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        die("Action failed: " . $e->getMessage());
    }
    header("Location: payout_details.php?id=$id&msg=updated");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM marketplace_orders WHERE id = ?");
$stmt->execute([$id]);
$payout = $stmt->fetch();
if (!$payout) {
    http_response_code(404);
    exit('Payout not found.');
}
?>
<?php admin_page_start('Payout Details: ' . e($payout['order_ref'] ?? 'N/A'), ['active' => 'marketplace_payouts.php']); ?>

<div class="card">
    <div style="margin-bottom:20px">
        <div class="label">Order Ref</div><div class="value"><?= e($payout['order_ref']) ?></div>
        <div class="label">Amount</div><div class="value">₦<?= number_format($payout['total_amount'], 2) ?></div>
        <div class="label">Status</div><div class="value"><?= e($payout['payout_status']) ?></div>
    </div>
    
    <?php if($payout['payout_status'] === 'pending'): ?>
        <form method="POST">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <button type="submit" name="action" value="approve" class="button" style="background:#dcfce7; color:#166534">Approve Payout</button>
            <button type="submit" name="action" value="reject" class="button danger">Reject Payout</button>
        </form>
    <?php endif; ?>
</div>

<?php admin_page_end(); ?>
