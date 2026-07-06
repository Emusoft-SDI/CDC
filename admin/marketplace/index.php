<?php
declare(strict_types=1);

require_once __DIR__ . '/../_auth.php';
require_once __DIR__ . '/../../lib/admin-layout.php';
require_once __DIR__ . '/../../lib/admin-operator-strip.php';
require_once __DIR__ . '/../../lib/marketplace.php';
require_once __DIR__ . '/../../lib/monnify.php';
require_once __DIR__ . '/../../lib/platform-revenue.php';

$pdo = db();
admin_ensure_schema($pdo);
marketplace_ensure_schema($pdo);
wallet_ensure_schema($pdo);
revenue_ensure_schema($pdo);
admin_require($pdo, 'marketplace');

$allowedPages = ['overview','buyers','sellers','products','orders','deliveries','inquiries','payouts','promotions','create'];
$page = in_array((string) ($_GET['page'] ?? 'overview'), $allowedPages, true) ? (string) ($_GET['page'] ?? 'overview') : 'overview';
$message = trim((string) ($_GET['message'] ?? ''));
$error = trim((string) ($_GET['error'] ?? ''));
$search = trim((string) ($_GET['search'] ?? ''));
$perPageCandidate = (int) ($_GET['per_page'] ?? 50);
$perPage = in_array($perPageCandidate, [10,25,50,100,200,500], true) ? $perPageCandidate : 50;
$listPage = max(1, (int) ($_GET['p'] ?? 1));

function mx_rows(PDO $pdo, string $sql): array { try { return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) { return []; } }
function mx_count(PDO $pdo, string $table, string $where = '1=1'): int { try { return app_table_exists($pdo, $table) ? (int) $pdo->query("SELECT COUNT(*) FROM `{$table}` WHERE {$where}")->fetchColumn() : 0; } catch (Throwable $e) { return 0; } }
function mx_money(float $amount): string { return 'NGN ' . number_format($amount, 2); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['_csrf'] ?? null)) {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'review_promotion') {
        $promotionId = (int) ($_POST['promotion_id'] ?? 0);
        $decision = (string) ($_POST['decision'] ?? 'pending_admin_review');
        $adminId = (int) (current_user($pdo)['id'] ?? 0);
        if (!in_array($decision, ['active', 'rejected', 'paused', 'pending_admin_review'], true)) { $decision = 'pending_admin_review'; }
        if ($decision === 'active') {
            $pdo->prepare("UPDATE marketplace_promotions SET status='active', approved_by=?, approved_at=NOW(), starts_at=COALESCE(starts_at,NOW()), ends_at=COALESCE(ends_at,DATE_ADD(NOW(), INTERVAL duration_days DAY)) WHERE id=?")->execute([$adminId ?: null, $promotionId]);
            $promoStmt = $pdo->prepare("SELECT p.*,s.user_id seller_user_id FROM marketplace_promotions p JOIN marketplace_sellers s ON s.id=p.seller_id WHERE p.id=? LIMIT 1");
            $promoStmt->execute([$promotionId]);
            revenue_apply_marketplace_promotion($pdo, $promoStmt->fetch(PDO::FETCH_ASSOC) ?: []);
            $message = 'Promotion approved, activated, and recorded as platform revenue.';
        } elseif ($decision === 'rejected') {
            $promoStmt = $pdo->prepare("SELECT p.*,s.user_id seller_user_id FROM marketplace_promotions p JOIN marketplace_sellers s ON s.id=p.seller_id WHERE p.id=? LIMIT 1");
            $promoStmt->execute([$promotionId]);
            $promo = $promoStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $shouldRefund = $promo && in_array((string) ($promo['status'] ?? ''), ['pending_admin_review', 'pending_payment'], true) && (string) ($promo['payment_method'] ?? '') === 'wallet' && (float) ($promo['amount'] ?? 0) > 0;
            if ($shouldRefund) {
                $refundRef = (string) ($promo['promo_ref'] ?? ('PROMO-' . $promotionId)) . '-REFUND';
                $exists = $pdo->prepare("SELECT id FROM wallet_transactions WHERE reference=? LIMIT 1");
                $exists->execute([$refundRef]);
                if (!$exists->fetchColumn()) {
                    $wallet = wallet_get_or_create($pdo, (int) $promo['seller_user_id']);
                    $amount = round((float) $promo['amount'], 2);
                    $before = (float) $wallet['balance'];
                    $after = $before + $amount;
                    $pdo->prepare("UPDATE wallets SET balance=?, updated_at=NOW() WHERE id=?")->execute([$after, (int) $wallet['id']]);
                    $pdo->prepare("INSERT INTO wallet_transactions (wallet_id,user_id,amount,type,direction,description,reference,provider,provider_reference,status,balance_before,balance_after,provider_payload,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())")->execute([(int) $wallet['id'], (int) $promo['seller_user_id'], $amount, 'credit', 'credit', 'Rejected marketplace promotion refund', $refundRef, 'marketplace_promotion', (string) ($promo['promo_ref'] ?? ''), 'completed', $before, $after, json_encode(['promotion_id' => $promotionId, 'admin_id' => $adminId], JSON_UNESCAPED_SLASHES)]);
                }
            }
            $pdo->prepare("UPDATE marketplace_promotions SET status='rejected', approved_by=?, approved_at=NOW(), starts_at=NULL, ends_at=NULL WHERE id=?")->execute([$adminId ?: null, $promotionId]);
            $message = $shouldRefund ? 'Promotion rejected and wallet payment refunded.' : 'Promotion rejected.';
        } elseif ($decision === 'paused') {
            $pdo->prepare("UPDATE marketplace_promotions SET status='paused', updated_at=NOW() WHERE id=?")->execute([$promotionId]);
            $message = 'Promotion paused.';
        } else {
            $pdo->prepare("UPDATE marketplace_promotions SET status='pending_admin_review', starts_at=NULL, ends_at=NULL WHERE id=?")->execute([$promotionId]);
            $message = 'Promotion returned to admin review.';
        }
        $page = 'promotions';
    }
}

$stats = [
    ['Buyers', mx_count($pdo, 'buyer_profiles'), 'buyers'],
    ['Sellers', mx_count($pdo, 'marketplace_sellers'), 'sellers'],
    ['Pending sellers', mx_count($pdo, 'marketplace_sellers', "approval_status='pending'"), 'sellers'],
    ['Products', mx_count($pdo, 'marketplace_listings'), 'products'],
    ['Pending products', mx_count($pdo, 'marketplace_listings', "approval_status='pending'"), 'products'],
    ['Orders', mx_count($pdo, 'marketplace_orders'), 'orders'],
    ['Delivery attention', mx_count($pdo, 'marketplace_orders', "payment_status='paid' AND (delivery_status NOT IN ('delivered','returned','failed') OR delivery_status IS NULL)"), 'deliveries'],
    ['Open inquiries', mx_count($pdo, 'marketplace_inquiries', "status NOT IN ('closed','resolved')"), 'inquiries'],
    ['Pending promotions', mx_count($pdo, 'marketplace_promotions', "status IN ('pending_admin_review','pending_payment')"), 'promotions'],
];

$buyers = mx_rows($pdo, "
    SELECT bp.*, u.name, u.email, u.phone, u.account_status, u.created_at user_created_at,
        (SELECT COUNT(*) FROM marketplace_orders o WHERE o.buyer_user_id = u.id) order_count,
        (SELECT COALESCE(SUM(CASE WHEN o.payment_status IN ('paid','successful') THEN COALESCE(NULLIF(o.checkout_total,0), o.total_amount) ELSE 0 END),0) FROM marketplace_orders o WHERE o.buyer_user_id = u.id) paid_value,
        (SELECT COUNT(*) FROM marketplace_orders o WHERE o.buyer_user_id = u.id AND o.payment_status IN ('paid','successful') AND o.delivery_status NOT IN ('delivered','returned','failed')) open_delivery_count,
        (SELECT COUNT(*) FROM marketplace_orders o WHERE o.buyer_user_id = u.id AND (o.status IN ('cancelled','disputed') OR o.delivery_status IN ('failed','returned') OR o.payment_status IN ('refunded','refund_pending','reversed'))) refund_exposure_count,
        (SELECT COALESCE(SUM(CASE WHEN o.status IN ('cancelled','disputed') OR o.delivery_status IN ('failed','returned') OR o.payment_status IN ('refunded','refund_pending','reversed') THEN COALESCE(NULLIF(o.checkout_total,0), o.total_amount) ELSE 0 END),0) FROM marketplace_orders o WHERE o.buyer_user_id = u.id) refund_exposure_value,
        (SELECT MAX(o.created_at) FROM marketplace_orders o WHERE o.buyer_user_id = u.id) last_order_at,
        (SELECT COUNT(*) FROM support_tickets st WHERE st.user_id = u.id OR st.requester_email = u.email) ticket_count
    FROM buyer_profiles bp
    JOIN users u ON u.id = bp.user_id
    ORDER BY last_order_at IS NULL, last_order_at DESC, bp.created_at DESC
    LIMIT 500
");
$sellers = mx_rows($pdo, "SELECT s.*,u.name user_name,u.email user_email,(SELECT COUNT(*) FROM marketplace_listings l WHERE l.seller_id=s.id) listing_count FROM marketplace_sellers s LEFT JOIN users u ON u.id=s.user_id ORDER BY FIELD(s.approval_status,'pending','approved','rejected','suspended'),s.created_at DESC LIMIT 500");
$listings = mx_rows($pdo, "SELECT l.*,s.store_name,c.name category_name FROM marketplace_listings l JOIN marketplace_sellers s ON s.id=l.seller_id LEFT JOIN marketplace_categories c ON c.id=l.category_id ORDER BY FIELD(l.approval_status,'pending','approved','rejected','suspended'),l.created_at DESC LIMIT 500");
$orders = mx_rows($pdo, "SELECT o.*,l.title listing_title,s.store_name,u.email buyer_account_email FROM marketplace_orders o JOIN marketplace_listings l ON l.id=o.listing_id JOIN marketplace_sellers s ON s.id=o.seller_id LEFT JOIN users u ON u.id=o.buyer_user_id ORDER BY o.created_at DESC LIMIT 500");
$inquiries = mx_rows($pdo, "SELECT i.*,l.title listing_title,s.store_name FROM marketplace_inquiries i JOIN marketplace_listings l ON l.id=i.listing_id JOIN marketplace_sellers s ON s.id=i.seller_id ORDER BY i.created_at DESC LIMIT 500");
$promotions = mx_rows($pdo, "SELECT p.*,s.store_name,l.title listing_title,u.email seller_email FROM marketplace_promotions p JOIN marketplace_sellers s ON s.id=p.seller_id LEFT JOIN marketplace_listings l ON l.id=p.listing_id LEFT JOIN users u ON u.id=s.user_id ORDER BY FIELD(p.status,'pending_admin_review','pending_payment','active','paused','rejected'),p.created_at DESC LIMIT 500");

$marketCollections = ['buyers'=>'buyers','sellers'=>'sellers','products'=>'listings','orders'=>'orders','deliveries'=>'orders','payouts'=>'orders','inquiries'=>'inquiries','promotions'=>'promotions'];
$marketTotal = 0;
if (isset($marketCollections[$page])) {
    $name = $marketCollections[$page];
    $rows = $$name;
    if ($search !== '') {
        $rows = array_values(array_filter($rows, static function (array $row) use ($search): bool {
            foreach ($row as $value) { if (is_scalar($value) && stripos((string) $value, $search) !== false) { return true; } }
            return false;
        }));
    }
    $marketTotal = count($rows);
    $$name = array_slice($rows, ($listPage - 1) * $perPage, $perPage);
}

$categories = marketplace_categories($pdo);
$listingTypes = marketplace_listing_types();
$nav = ['overview'=>'Overview','buyers'=>'Buyer Ops','sellers'=>'Sellers','products'=>'Products & Inventory','orders'=>'Orders','deliveries'=>'Delivery Ops','inquiries'=>'Inquiries & Disputes','payouts'=>'Payouts','promotions'=>'Promotions','create'=>'Create Listing'];
$orderStatuses = ['quoted','accepted','paid','preparing','ready','scheduled','in_transit','completed','cancelled','disputed'];
$deliveryStatuses = ['not_started','awaiting_seller','packing','ready_for_pickup','scheduled','in_transit','delivered','failed','returned'];
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Marketplace Admin Workspace</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"><link rel="stylesheet" href="../../assets/css/admin-workspaces.css">
<style>body{background:#f6f7f5}.mx-shell{display:grid;grid-template-columns:270px 1fr;min-height:100vh}.mx-side{background:#172f28;color:#fff;padding:24px 18px}.mx-side a{color:#d9e9e2;text-decoration:none;padding:11px 12px;border-radius:8px}.mx-side a.active,.mx-side a:hover{background:#28644d;color:#fff}.mx-main{padding:28px}.table td{vertical-align:middle}.inline-control{min-width:145px}.ops-note{min-width:240px}.delivery-warn{background:#fff8e1!important}@media(max-width:900px){.mx-shell{grid-template-columns:1fr}.mx-main{padding:16px}}</style></head><body>
<div class="mx-shell"><aside class="mx-side"><h4 class="mb-1">NATCODEV</h4><p class="small text-white-50">Marketplace Admin</p><nav class="d-grid gap-1 mt-4">
<?php foreach($nav as $key=>$label): ?><a class="<?= $page===$key?'active':'' ?>" href="?page=<?= e($key) ?>"><?= e($label) ?></a><?php endforeach; ?>
<hr class="border-light opacity-25"><a href="../../market/index.php">Public Marketplace</a><a href="../index.php">Workspace Hub</a><?php if(admin_current_user_is_super_admin($pdo)): ?><a href="../marketplace.php?legacy=1">Legacy Super Admin Console</a><?php endif; ?></nav></aside>
<main class="mx-main"><?= admin_workspace_operator_strip($pdo, ['asset_prefix' => '../../', 'profile_href' => '../profile.php', 'password_href' => '../profile.php#password', 'logout_action' => '../admin.php', 'title' => 'Marketplace workspace', 'placeholder' => 'Search buyers, sellers, orders, products...']) ?><div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4"><div><span class="small text-success fw-bold text-uppercase">Live marketplace database</span><h1 class="h3 mb-1"><?= e($nav[$page] ?? marketplace_status_label($page)) ?></h1><p class="text-secondary mb-0">Manage sellers, listings, orders, delivery operations, inquiries and settlements.</p></div><div class="d-flex gap-2"><a class="btn btn-outline-success" href="../marketplace.php?export=sellers">Export Sellers</a><a class="btn btn-success" href="?page=create">New Listing</a></div></div>
<?php if($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?><?php if($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<?php if(isset($marketCollections[$page])): ?><form class="card card-body mb-3" method="get"><input type="hidden" name="page" value="<?= e($page) ?>"><div class="row g-2"><div class="col-md-8"><input class="form-control" name="search" value="<?= e($search) ?>" placeholder="Search this Marketplace module"></div><div class="col-md-2"><select class="form-select" name="per_page"><?php foreach([10,25,50,100,200,500] as $n): ?><option value="<?= $n ?>" <?= $perPage===$n?'selected':'' ?>><?= $n ?> rows</option><?php endforeach; ?></select></div><div class="col-md-2"><button class="btn btn-success w-100">Search</button></div></div></form><?php endif; ?>

<?php if($page==='overview'): ?><div class="row g-3 mb-4"><?php foreach($stats as [$label,$value,$target]): ?><div class="col-sm-6 col-xl-3"><a class="card h-100 text-decoration-none" href="?page=<?= e($target) ?>"><div class="card-body"><div class="text-secondary small"><?= e($label) ?></div><div class="display-6 fw-bold text-dark"><?= number_format($value) ?></div></div></a></div><?php endforeach; ?></div><div class="row g-4"><div class="col-xl-7"><div class="card"><div class="card-header bg-white fw-bold">Latest Orders</div><div class="table-responsive"><table class="table mb-0"><tr><th>Order</th><th>Store</th><th>Payment</th><th>Delivery</th></tr><?php foreach(array_slice($orders,0,8) as $row): ?><tr><td><?= e((string)($row['order_ref']??'#'.$row['id'])) ?></td><td><?= e((string)$row['store_name']) ?></td><td><?= e(marketplace_status_label((string)($row['payment_status']??'pending'))) ?></td><td><?= e(marketplace_status_label((string)($row['delivery_status']??'not_started'))) ?></td></tr><?php endforeach; ?></table></div></div></div><div class="col-xl-5"><div class="card"><div class="card-header bg-white fw-bold">Pending Seller Reviews</div><div class="list-group list-group-flush"><?php foreach(array_slice(array_values(array_filter($sellers,fn($s)=>$s['approval_status']==='pending')),0,8) as $row): ?><a class="list-group-item list-group-item-action" href="?page=sellers"><strong><?= e((string)$row['store_name']) ?></strong><br><small><?= e((string)$row['user_email']) ?></small></a><?php endforeach; ?></div></div></div></div><?php endif; ?>

<?php if($page==='buyers'): require __DIR__ . '/buyers.php'; endif; ?>
<?php if($page==='sellers'): ?><div class="card"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Seller</th><th>Type</th><th>Listings</th><th>Review</th></tr></thead><tbody><?php foreach($sellers as $row): ?><tr><td><strong><?= e((string)$row['store_name']) ?></strong><br><small><?= e((string)$row['user_email']) ?></small></td><td><?= e(marketplace_status_label((string)$row['seller_type'])) ?></td><td><?= (int)$row['listing_count'] ?></td><td><form class="d-flex gap-2 flex-wrap" method="post" action="../marketplace.php"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="review_seller"><input type="hidden" name="workspace_return" value="sellers"><input type="hidden" name="seller_id" value="<?= (int)$row['id'] ?>"><select class="form-select form-select-sm inline-control" name="approval_status"><?php foreach(['pending','approved','rejected','suspended'] as $v): ?><option <?= $row['approval_status']===$v?'selected':'' ?>><?= $v ?></option><?php endforeach; ?></select><select class="form-select form-select-sm inline-control" name="verification_status"><?php foreach(['unverified','verified','flagged'] as $v): ?><option <?= $row['verification_status']===$v?'selected':'' ?>><?= $v ?></option><?php endforeach; ?></select><input class="form-control form-control-sm inline-control" name="admin_notes" value="<?= e((string)$row['admin_notes']) ?>" placeholder="Review note"><button class="btn btn-sm btn-success">Save</button></form></td></tr><?php endforeach; ?></tbody></table></div></div><?php endif; ?>

<?php if($page==='products'): ?><div class="card"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Listing</th><th>Store</th><th>Price</th><th>Inventory</th><th>Review</th></tr></thead><tbody><?php foreach($listings as $row): ?><tr><td><strong><?= e((string)$row['title']) ?></strong><br><small><?= e((string)$row['category_name']) ?></small></td><td><?= e((string)$row['store_name']) ?></td><td><?= e(mx_money((float)$row['price'])) ?></td><td><?= e((string)$row['quantity_available'].' '.$row['unit']) ?></td><td><form class="d-flex gap-2" method="post" action="../marketplace.php"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="review_listing"><input type="hidden" name="workspace_return" value="products"><input type="hidden" name="listing_id" value="<?= (int)$row['id'] ?>"><select class="form-select form-select-sm" name="approval_status"><?php foreach(['pending','approved','rejected','suspended'] as $v): ?><option <?= $row['approval_status']===$v?'selected':'' ?>><?= $v ?></option><?php endforeach; ?></select><select class="form-select form-select-sm" name="availability_status"><?php foreach(['available','limited','out_of_stock','paused'] as $v): ?><option <?= $row['availability_status']===$v?'selected':'' ?>><?= $v ?></option><?php endforeach; ?></select><button class="btn btn-sm btn-success">Save</button></form></td></tr><?php endforeach; ?></tbody></table></div></div><?php endif; ?>

<?php if($page==='orders' || $page==='payouts'): ?><div class="card"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Order</th><th>Listing / Store</th><th>Amount</th><th>Payment</th><th>Status & Fulfillment</th></tr></thead><tbody><?php foreach($orders as $row): ?><tr><td><?= e((string)($row['order_ref']??'#'.$row['id'])) ?><br><small><?= e((string)$row['created_at']) ?></small><br><small><?= e((string)($row['checkout_ref']??'')) ?></small></td><td><strong><?= e((string)$row['listing_title']) ?></strong><br><small><?= e((string)$row['store_name']) ?></small></td><td><?= e(mx_money((float)($row['total_amount']??0))) ?></td><td><?= e(marketplace_status_label((string)($row['payment_status']??'pending'))) ?><br><small><?= empty($row['settled_at'])?'Payout pending':'Settled' ?></small></td><td><form class="d-flex gap-2 flex-wrap" method="post" action="../marketplace.php"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="update_order"><input type="hidden" name="workspace_return" value="<?= e($page) ?>"><input type="hidden" name="order_id" value="<?= (int)$row['id'] ?>"><select class="form-select form-select-sm inline-control" name="status"><?php foreach($orderStatuses as $v): ?><option value="<?= e($v) ?>" <?= $row['status']===$v?'selected':'' ?>><?= e(marketplace_status_label($v)) ?></option><?php endforeach; ?></select><select class="form-select form-select-sm inline-control" name="delivery_status"><?php foreach($deliveryStatuses as $v): ?><option value="<?= e($v) ?>" <?= (string)($row['delivery_status']??'')===$v?'selected':'' ?>><?= e(marketplace_status_label($v)) ?></option><?php endforeach; ?></select><input class="form-control form-control-sm inline-control" name="tracking_ref" value="<?= e((string)($row['tracking_ref']??'')) ?>" placeholder="Tracking ref"><input class="form-control form-control-sm inline-control" name="fulfillment_note" value="<?= e((string)($row['fulfillment_note']??'')) ?>" placeholder="Fulfillment note"><button class="btn btn-sm btn-success">Update</button></form></td></tr><?php endforeach; ?></tbody></table></div></div><?php endif; ?>

<?php if($page==='deliveries'): ?>
<?php $paidAwaiting=count(array_filter($orders,static fn($r)=>in_array((string)($r['payment_status']??''),['paid','successful'],true)&&!in_array((string)($r['delivery_status']??''),['delivered','returned','failed'],true))); $inTransit=count(array_filter($orders,static fn($r)=>(string)($r['delivery_status']??'')==='in_transit')); $deliveryIssues=count(array_filter($orders,static fn($r)=>in_array((string)($r['delivery_status']??''),['failed','returned'],true)||(string)($r['status']??'')==='disputed')); $aged=count(array_filter($orders,static fn($r)=>in_array((string)($r['payment_status']??''),['paid','successful'],true)&&!in_array((string)($r['delivery_status']??''),['delivered','returned','failed'],true)&&strtotime((string)($r['created_at']??'now'))<strtotime('-48 hours'))); ?>
<div class="row g-3 mb-3"><?php foreach([['Paid Awaiting Delivery',$paidAwaiting],['In Transit',$inTransit],['Failed / Returned / Disputed',$deliveryIssues],['Older Than 48h Open',$aged]] as [$label,$value]): ?><div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-secondary small"><?= e($label) ?></div><div class="h2 mb-0"><?= number_format($value) ?></div></div></div></div><?php endforeach; ?></div>
<div class="card"><div class="card-header bg-white fw-bold">Marketplace Delivery Control Room</div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Order / Tracking</th><th>Buyer</th><th>Seller / Item</th><th>Money</th><th>Delivery Control</th></tr></thead><tbody><?php foreach($orders as $row): $created=strtotime((string)($row['created_at']??'now')); $ageHours=max(0,(int)floor((time()-$created)/3600)); $needsAttention=in_array((string)($row['payment_status']??''),['paid','successful'],true)&&!in_array((string)($row['delivery_status']??''),['delivered','returned','failed'],true)&&$ageHours>=48; ?><tr class="<?= $needsAttention?'delivery-warn':'' ?>"><td><strong><?= e((string)($row['order_ref']??'#'.$row['id'])) ?></strong><br><small><?= e((string)($row['checkout_ref']??'')) ?></small><br><small>Tracking: <?= e((string)($row['tracking_ref']?:'Pending')) ?></small><br><small><?= $ageHours ?>h old</small></td><td><?= e((string)($row['buyer_name']??'')) ?><br><small><?= e((string)($row['buyer_phone']??'')) ?></small><br><small><?= e((string)($row['buyer_account_email']??'')) ?></small></td><td><strong><?= e((string)$row['store_name']) ?></strong><br><small><?= e((string)$row['listing_title']) ?></small><br><small><?= e((string)($row['delivery_address']??'')) ?></small></td><td><?= e(mx_money((float)($row['total_amount']??0))) ?><br><small><?= e(marketplace_status_label((string)($row['payment_status']??'pending'))) ?></small><br><small>Payout: <?= empty($row['settled_at'])?'Pending':'Settled' ?></small></td><td><form class="d-grid gap-2" method="post" action="../marketplace.php"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="update_order"><input type="hidden" name="workspace_return" value="deliveries"><input type="hidden" name="order_id" value="<?= (int)$row['id'] ?>"><div class="d-flex gap-2 flex-wrap"><select class="form-select form-select-sm inline-control" name="status"><?php foreach($orderStatuses as $v): ?><option value="<?= e($v) ?>" <?= $row['status']===$v?'selected':'' ?>><?= e(marketplace_status_label($v)) ?></option><?php endforeach; ?></select><select class="form-select form-select-sm inline-control" name="delivery_status"><?php foreach($deliveryStatuses as $v): ?><option value="<?= e($v) ?>" <?= (string)($row['delivery_status']??'')===$v?'selected':'' ?>><?= e(marketplace_status_label($v)) ?></option><?php endforeach; ?></select></div><input class="form-control form-control-sm" name="tracking_ref" value="<?= e((string)($row['tracking_ref']??'')) ?>" placeholder="Tracking reference"><input class="form-control form-control-sm" name="fulfillment_note" value="<?= e((string)($row['fulfillment_note']??'')) ?>" placeholder="Seller-visible fulfillment note"><textarea class="form-control form-control-sm ops-note" name="operator_delivery_note" rows="2" placeholder="Internal operator note"><?= e((string)($row['operator_delivery_note']??'')) ?></textarea><button class="btn btn-sm btn-success">Save Delivery Review</button></form></td></tr><?php endforeach; ?><?php if(!$orders): ?><tr><td colspan="5" class="text-secondary">No marketplace orders yet.</td></tr><?php endif; ?></tbody></table></div></div><?php endif; ?>

<?php if($page==='promotions'): ?><div class="card"><div class="card-header bg-white fw-bold">Admin-Governed Store & Product Promotions</div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Promotion</th><th>Seller / Product</th><th>Budget & Placement</th><th>Performance</th><th>Admin Review</th></tr></thead><tbody><?php foreach($promotions as $row): $impressions=(int)($row['impressions']??0); $clicks=(int)($row['clicks']??0); $ctr=$impressions>0?($clicks/$impressions)*100:0; ?><tr><td><strong><?= e((string)$row['title']) ?></strong><br><small><?= e((string)($row['promo_ref']??'#'.$row['id'])) ?></small><br><span class="badge text-bg-<?= $row['status']==='active'?'success':($row['status']==='rejected'?'danger':($row['status']==='paused'?'secondary':'warning')) ?>"><?= e(marketplace_status_label((string)$row['status'])) ?></span></td><td><?= e((string)$row['store_name']) ?><br><small><?= e((string)($row['listing_title'] ?: 'Storefront promotion')) ?></small><br><small class="text-secondary"><?= e((string)($row['seller_email']??'')) ?></small></td><td><?= e(mx_money((float)($row['amount']??0))) ?><br><small><?= e(marketplace_status_label((string)($row['placement']??'marketplace'))) ?> / <?= (int)($row['duration_days']??0) ?> day(s)</small></td><td><?= number_format($impressions) ?> impressions<br><?= number_format($clicks) ?> clicks<br><small><?= number_format($ctr,2) ?>% CTR</small></td><td><form class="d-flex gap-2 flex-wrap" method="post"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="review_promotion"><input type="hidden" name="promotion_id" value="<?= (int)$row['id'] ?>"><select class="form-select form-select-sm inline-control" name="decision"><?php foreach(['pending_admin_review'=>'Pending review','active'=>'Approve & activate','paused'=>'Pause','rejected'=>'Reject'] as $value=>$label): ?><option value="<?= e($value) ?>" <?= $row['status']===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select><button class="btn btn-sm btn-success">Save</button></form><small class="text-secondary">Starts: <?= e((string)($row['starts_at'] ?: 'After approval')) ?><br>Ends: <?= e((string)($row['ends_at'] ?: 'After approval')) ?></small></td></tr><?php endforeach; ?><?php if(!$promotions): ?><tr><td colspan="5" class="text-secondary">No promotion requests yet.</td></tr><?php endif; ?></tbody></table></div></div><?php endif; ?>

<?php if($page==='inquiries'): ?><div class="card"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Listing</th><th>Store</th><th>Requester</th><th>Message</th><th>Status</th></tr></thead><tbody><?php foreach($inquiries as $row): ?><tr><td><?= e((string)$row['listing_title']) ?></td><td><?= e((string)$row['store_name']) ?></td><td><?= e((string)($row['requester_name']??$row['buyer_name']??'')) ?></td><td><?= e((string)($row['message']??$row['subject']??'')) ?></td><td><?= e(marketplace_status_label((string)$row['status'])) ?></td></tr><?php endforeach; ?></tbody></table></div></div><?php endif; ?>

<?php if($page==='create'): ?><div class="row g-4"><div class="col-xl-8"><div class="card"><div class="card-body"><h2 class="h5">Publish Admin Listing</h2><form method="post" action="../marketplace.php" class="row g-3"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="admin_listing"><input type="hidden" name="workspace_return" value="products"><div class="col-md-6"><label class="form-label">Seller</label><select class="form-select" name="seller_id"><?php foreach($sellers as $seller): ?><option value="<?= (int)$seller['id'] ?>"><?= e((string)$seller['store_name']) ?></option><?php endforeach; ?></select></div><div class="col-md-6"><label class="form-label">Type</label><select class="form-select" name="listing_type"><?php foreach($listingTypes as $key=>$label): ?><option value="<?= e($key) ?>"><?= e($label) ?></option><?php endforeach; ?></select></div><div class="col-12"><label class="form-label">Title</label><input class="form-control" name="title" required></div><div class="col-12"><label class="form-label">Summary</label><input class="form-control" name="summary"></div><div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="description"></textarea></div><div class="col-md-4"><label class="form-label">Price</label><input class="form-control" type="number" min="0" step=".01" name="price"></div><div class="col-md-4"><label class="form-label">Quantity</label><input class="form-control" type="number" min="0" step=".01" name="quantity_available"></div><div class="col-md-4"><label class="form-label">Unit</label><input class="form-control" name="unit"></div><div class="col-12"><button class="btn btn-success">Publish Listing</button></div></form></div></div></div></div><?php endif; ?>

<?php if(isset($marketCollections[$page])):$pages=max(1,(int)ceil($marketTotal/max(1,$perPage)));?><div class="d-flex justify-content-between align-items-center mt-3"><span class="text-secondary"><?= number_format($marketTotal) ?> result(s)</span><div class="btn-group"><a class="btn btn-outline-success" href="?<?= e(http_build_query(['page'=>$page,'search'=>$search,'per_page'=>$perPage,'p'=>max(1,$listPage-1)])) ?>">Previous</a><span class="btn btn-light">Page <?= $listPage ?> / <?= $pages ?></span><a class="btn btn-outline-success" href="?<?= e(http_build_query(['page'=>$page,'search'=>$search,'per_page'=>$perPage,'p'=>min($pages,$listPage+1)])) ?>">Next</a></div></div><?php endif; ?>
</main></div><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script></body></html>
