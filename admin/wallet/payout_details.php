<?php
require_once __DIR__ . '/../_auth.php';
require_once __DIR__ . '/../../lib/admin-layout.php';
require_once __DIR__ . '/../../lib/admin-approvals.php';
require_once __DIR__ . '/../../lib/wallet-dual-control.php';
if (!function_exists('admin_audit_event')) {
    require_once __DIR__ . '/../../lib/admin-audit-v2.php';
}
$pdo = db();
admin_require($pdo, 'wallet');
$admin = current_user($pdo) ?: [];

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header("Location: index.php?page=payouts"); exit; }

// Handle Approval/Rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        app_render_error_page(403, 'Invalid security token.');
    }
    $action = (string) $_POST['action'];
    if (!in_array($action, ['approve', 'reject'], true)) {
        http_response_code(422);
        exit('Invalid payout action.');
    }
    $newStatus = ($action === 'approve') ? 'approved' : 'rejected';
    $reason = trim((string) ($_POST['admin_note'] ?? ''));

    // Dual control: when the acting admin is not a Super Admin and the amount
    // hits the review threshold, queue the decision instead of applying it.
    if (!admin_current_user_is_super_admin($pdo)) {
        $orderStmt = $pdo->prepare("SELECT order_ref, total_amount FROM marketplace_orders WHERE id = ? LIMIT 1");
        $orderStmt->execute([$id]);
        $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
        if ($order && wallet_dual_control_required($pdo, (float) $order['total_amount'])) {
            admin_ensure_action_request_schema($pdo);
            // One pending request per payout: a second operator clicking the
            // same action reuses the queued request instead of duplicating it.
            $dup = $pdo->prepare("SELECT id FROM admin_action_requests WHERE request_type = 'payout_approval' AND target_table = 'marketplace_orders' AND target_id = ? AND status = 'pending' ORDER BY id DESC LIMIT 1");
            $dup->execute([$id]);
            $requestId = (int) ($dup->fetchColumn() ?: 0);
            if ($requestId <= 0) {
                $orderRef = (string) ($order['order_ref'] !== '' ? $order['order_ref'] : ('Order #' . $id));
                $insert = $pdo->prepare("
                    INSERT INTO admin_action_requests
                        (request_type, target_table, target_id, target_key, target_label, requested_by, reason, payload_json)
                    VALUES ('payout_approval', 'marketplace_orders', ?, ?, ?, ?, ?, ?)
                ");
                $insert->execute([
                    $id,
                    $orderRef,
                    $orderRef,
                    (int) ($admin['id'] ?? 0),
                    $reason !== '' ? $reason : 'Super Admin approval required for this marketplace payout.',
                    json_encode([
                        'action' => $action,
                        'payout_status' => $newStatus,
                        'admin_id' => (int) ($admin['id'] ?? 0),
                        'reason' => mb_substr($reason, 0, 500),
                    ], JSON_UNESCAPED_SLASHES),
                ]);
                $requestId = (int) $pdo->lastInsertId();
            }
            header("Location: payout_details.php?id=$id&msg=queued&request=$requestId");
            exit;
        }
    }

    $pdo->beginTransaction();
    try {
        $statusStmt = $pdo->prepare("SELECT payout_status FROM marketplace_orders WHERE id = ?");
        $statusStmt->execute([$id]);
        $oldStatus = (string) ($statusStmt->fetchColumn() ?: 'pending');

        $pdo->prepare("UPDATE marketplace_orders SET payout_status = ? WHERE id = ?")
            ->execute([$newStatus, $id]);

        admin_audit_event($pdo, 'payout_' . $newStatus, 'marketplace_orders', $id, ['payout_status' => $oldStatus], ['payout_status' => $newStatus], 'success', 'Admin ' . $action . ' marketplace payout #' . $id);

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
    app_render_error_page(404, 'This payout could not be found.');
}
?>
<?php admin_page_start('Payout Details: ' . e($payout['order_ref'] ?? 'N/A'), [
    'active' => 'wallet/',
    'breadcrumbs' => [['label' => 'Marketplace & Providers'], ['label' => 'Payouts'], ['label' => 'Payout Details']],
]); ?>

<div class="card">
    <?php if (isset($_GET['msg'])): ?><p style="color:#166534;"><?= e((string) ($_GET['msg']) === 'queued' ? 'Queued for Super Admin approval (request #' . (int) ($_GET['request'] ?? 0) . '). Payout status was not changed.' : 'Payout updated.') ?></p><?php endif; ?>
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
