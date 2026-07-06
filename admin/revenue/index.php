<?php
declare(strict_types=1);

require_once __DIR__ . '/../_auth.php';
require_once __DIR__ . '/../../lib/admin-layout.php';
require_once __DIR__ . '/../../lib/admin-operator-strip.php';
require_once __DIR__ . '/../../lib/marketplace.php';
require_once __DIR__ . '/../../lib/platform-revenue.php';
require_once __DIR__ . '/../../lib/certificates.php';

$pdo = db();
admin_ensure_schema($pdo);
marketplace_ensure_schema($pdo);
revenue_ensure_schema($pdo);
grower_certificate_access_ensure_schema($pdo);
provider_accreditation_access_ensure_schema($pdo);
admin_require($pdo, 'revenue');

$page = (string) ($_GET['page'] ?? 'overview');
$allowed = ['overview','rules','ledger','marketplace','plans','promotions','registry','provider-fees'];
if (!in_array($page, $allowed, true)) { $page = 'overview'; }
$search = trim((string) ($_GET['search'] ?? ''));
$perPage = max(10, min(100, (int) ($_GET['per_page'] ?? 25)));
$currentPage = max(1, (int) ($_GET['p'] ?? 1));
$offset = ($currentPage - 1) * $perPage;
$message = '';
$error = '';

function rev_e($value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function rev_money(float $amount): string { return 'NGN ' . number_format($amount, 2); }
function rev_scalar(PDO $pdo, string $sql, array $params = []): float { try { $stmt = $pdo->prepare($sql); $stmt->execute($params); return (float) ($stmt->fetchColumn() ?: 0); } catch (Throwable $e) { return 0.0; } }
function rev_rows(PDO $pdo, string $sql, array $params = []): array { try { $stmt = $pdo->prepare($sql); $stmt->execute($params); return $stmt->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) { return []; } }
function rev_pages(int $total, int $size, int $page): string { $pages = max(1, (int) ceil($total / max(1, $size))); $base = $_GET; unset($base['p']); ob_start(); ?><div class="d-flex justify-content-between align-items-center mt-3"><span class="text-secondary"><?= number_format($total) ?> result(s)</span><div class="btn-group"><a class="btn btn-sm btn-outline-success" href="?<?= rev_e(http_build_query($base + ['p'=>max(1,$page-1)])) ?>">Previous</a><span class="btn btn-sm btn-light">Page <?= $page ?> / <?= $pages ?></span><a class="btn btn-sm btn-outline-success" href="?<?= rev_e(http_build_query($base + ['p'=>min($pages,$page+1)])) ?>">Next</a></div></div><?php return (string) ob_get_clean(); }

if (isset($_GET['export']) && $_GET['export'] === 'ledger') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="natcodev-revenue-ledger.csv"');
    $out = fopen('php://output', 'wb');
    fputcsv($out, ['Date','Reference','Module','Type','Gross','Revenue','Seller Net','Rule','Status','Description']);
    foreach (rev_rows($pdo, "SELECT * FROM platform_revenue_ledger ORDER BY created_at DESC, id DESC LIMIT 5000") as $row) {
        fputcsv($out, [$row['created_at'],$row['revenue_ref'],$row['source_module'],$row['source_type'],$row['gross_amount'],$row['revenue_amount'],$row['net_payable_amount'] ?? '',$row['rule_key'],$row['status'],$row['description']]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf($_POST['_csrf'] ?? null)) { throw new RuntimeException('Invalid security token.'); }
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'save_rule') {
            $pdo->prepare("UPDATE platform_fee_rules SET title=?, fee_type=?, percentage_rate=?, fixed_amount=?, minimum_fee=?, maximum_fee=?, is_active=?, notes=? WHERE id=?")
                ->execute([trim((string) $_POST['title']), in_array((string) $_POST['fee_type'], ['percentage','fixed'], true) ? (string) $_POST['fee_type'] : 'percentage', (float) $_POST['percentage_rate'], (float) $_POST['fixed_amount'], (float) $_POST['minimum_fee'], $_POST['maximum_fee'] === '' ? null : (float) $_POST['maximum_fee'], !empty($_POST['is_active']) ? 1 : 0, trim((string) ($_POST['notes'] ?? '')), (int) $_POST['id']]);
            $message = 'Fee rule saved.'; $page = 'rules';
        } elseif ($action === 'save_plan') {
            $pdo->prepare("UPDATE seller_subscription_plans SET title=?, monthly_fee=?, annual_fee=?, product_limit=?, promotion_credits=?, is_active=? WHERE id=?")
                ->execute([trim((string) $_POST['title']), (float) $_POST['monthly_fee'], (float) $_POST['annual_fee'], max(0, (int) $_POST['product_limit']), max(0, (int) $_POST['promotion_credits']), !empty($_POST['is_active']) ? 1 : 0, (int) $_POST['id']]);
            $message = 'Seller plan saved.'; $page = 'plans';
        } elseif ($action === 'save_grower_certificate_fees') {
            grower_certificate_save_setting($pdo, 'grower_certificate_access_fee_enabled', !empty($_POST['enabled']) ? '1' : '0');
            foreach (array_keys(grower_member_types()) as $type) {
                grower_certificate_save_setting($pdo, 'grower_certificate_fee_' . $type, (string) max(0, (float) ($_POST[$type . '_fee'] ?? 0)));
                grower_certificate_save_setting($pdo, 'grower_certificate_validity_' . $type, (string) max(1, min(120, (int) ($_POST[$type . '_validity_months'] ?? 12))));
            }
            provider_accreditation_save_setting($pdo, 'provider_accreditation_access_fee_enabled', !empty($_POST['provider_enabled']) ? '1' : '0');
            provider_accreditation_save_setting($pdo, 'provider_accreditation_access_fee_amount', (string) max(0, (float) ($_POST['provider_amount'] ?? 0)));
            provider_accreditation_save_setting($pdo, 'provider_accreditation_access_validity_months', (string) max(1, min(120, (int) ($_POST['provider_validity_months'] ?? 12))));
            $message = 'Certificate access, grower charging, and provider accreditation fees saved.';
            $returnPage = (string) ($_POST['return_page'] ?? 'registry');
            $page = in_array($returnPage, ['registry', 'provider-fees'], true) ? $returnPage : 'registry';
        } elseif ($action === 'sync_promotions') {
            revenue_sync_active_marketplace_promotions($pdo);
            $message = 'Promotion revenue sync completed.'; $page = 'promotions';
        } elseif ($action === 'review_promotion') {
            $promotionId = (int) ($_POST['id'] ?? 0);
            $decision = in_array((string) ($_POST['status'] ?? ''), ['pending_admin_review','active','paused','rejected'], true) ? (string) $_POST['status'] : 'pending_admin_review';
            $adminId = (int) (current_user($pdo)['id'] ?? 0);
            if ($decision === 'active') {
                $pdo->prepare("UPDATE marketplace_promotions SET status='active', approved_by=?, approved_at=NOW(), starts_at=COALESCE(starts_at,NOW()), ends_at=COALESCE(ends_at,DATE_ADD(NOW(), INTERVAL duration_days DAY)) WHERE id=?")->execute([$adminId ?: null, $promotionId]);
                $stmt = $pdo->prepare("SELECT p.*, s.user_id seller_user_id FROM marketplace_promotions p LEFT JOIN marketplace_sellers s ON s.id=p.seller_id WHERE p.id=? LIMIT 1");
                $stmt->execute([$promotionId]);
                $promo = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
                if ($promo) { revenue_apply_marketplace_promotion($pdo, $promo); }
            } else {
                $pdo->prepare("UPDATE marketplace_promotions SET status=?, approved_by=?, approved_at=CASE WHEN ?='rejected' THEN NOW() ELSE approved_at END, updated_at=NOW() WHERE id=?")->execute([$decision, $adminId ?: null, $decision, $promotionId]);
            }
            $message = 'Marketplace promotion decision saved and synchronized.'; $page = 'promotions';
        }
    } catch (Throwable $e) { $error = $e->getMessage(); }
}

$totalRevenue = rev_scalar($pdo, "SELECT COALESCE(SUM(revenue_amount),0) FROM platform_revenue_ledger WHERE status IN ('earned','collected')");
$monthRevenue = rev_scalar($pdo, "SELECT COALESCE(SUM(revenue_amount),0) FROM platform_revenue_ledger WHERE created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND status IN ('earned','collected')");
$marketGross = rev_scalar($pdo, "SELECT COALESCE(SUM(gross_amount),0) FROM platform_revenue_ledger WHERE source_module='marketplace'");
$sellerNet = rev_scalar($pdo, "SELECT COALESCE(SUM(seller_net_amount),0) FROM marketplace_orders WHERE payment_status='paid'");
$pendingPromotions = (int) rev_scalar($pdo, "SELECT COUNT(*) FROM marketplace_promotions WHERE status IN ('pending_admin_review','pending_payment')");
$activeRules = (int) rev_scalar($pdo, "SELECT COUNT(*) FROM platform_fee_rules WHERE is_active=1");
$certificateFeeConfig = grower_certificate_fee_config($pdo);
$providerCertificateFeeConfig = provider_accreditation_fee_config($pdo);
$certificateAccessRevenue = rev_scalar($pdo, "SELECT COALESCE(SUM(revenue_amount),0) FROM platform_revenue_ledger WHERE source_module='registry' AND source_type='certificate_access_fee'");
$providerCertificateAccessRevenue = rev_scalar($pdo, "SELECT COALESCE(SUM(revenue_amount),0) FROM platform_revenue_ledger WHERE source_module='registry' AND source_type='provider_accreditation_fee'");
$certificateAccessCount = (int) (app_table_exists($pdo, 'certificate_access_payments') ? rev_scalar($pdo, "SELECT COUNT(*) FROM certificate_access_payments WHERE status='paid'") : 0);
$certificateAccessValidCount = (int) (app_table_exists($pdo, 'certificate_access_payments') ? rev_scalar($pdo, "SELECT COUNT(*) FROM certificate_access_payments WHERE status='paid' AND (valid_until IS NULL OR valid_until >= NOW())") : 0);
$certificateAccessExpiredCount = max(0, $certificateAccessCount - $certificateAccessValidCount);
$providerCertificateAccessCount = (int) (app_table_exists($pdo, 'provider_accreditation_access_payments') ? rev_scalar($pdo, "SELECT COUNT(*) FROM provider_accreditation_access_payments WHERE status='paid'") : 0);
$providerCertificateAccessValidCount = (int) (app_table_exists($pdo, 'provider_accreditation_access_payments') ? rev_scalar($pdo, "SELECT COUNT(*) FROM provider_accreditation_access_payments WHERE status='paid' AND (valid_until IS NULL OR valid_until >= NOW())") : 0);
$providerCertificateAccessExpiredCount = max(0, $providerCertificateAccessCount - $providerCertificateAccessValidCount);
$platformCertificateEarnings = $certificateAccessRevenue + $providerCertificateAccessRevenue;
$showPlatformEarningsSummary = in_array(admin_current_platform_role($pdo), ['super_admin','admin','field_agent','support_agent','national_coordinator','state_coordinator','agronomist','agric_extensionist','extensionist'], true);
$certificateAccessPayments = app_table_exists($pdo, 'certificate_access_payments') ? rev_rows($pdo, "SELECT cap.*, u.name AS user_name, u.email AS user_email, a.name AS application_name FROM certificate_access_payments cap LEFT JOIN users u ON u.id=cap.user_id LEFT JOIN applications a ON a.id=cap.application_id ORDER BY cap.paid_at DESC LIMIT 20") : [];
$providerAccPayments = app_table_exists($pdo, 'provider_accreditation_access_payments') ? rev_rows($pdo, "SELECT pap.*, pr.company_name AS provider_name, u.name AS user_name, u.email AS user_email FROM provider_accreditation_access_payments pap LEFT JOIN provider_registry pr ON pr.id=pap.provider_id LEFT JOIN users u ON u.id=pap.user_id ORDER BY pap.paid_at DESC LIMIT 20") : [];

$ledgerWhere = '1=1'; $ledgerParams = [];
if ($search !== '') { $ledgerWhere .= " AND (revenue_ref LIKE ? OR description LIKE ? OR source_module LIKE ? OR rule_key LIKE ?)"; $term = '%' . $search . '%'; array_push($ledgerParams, $term, $term, $term, $term); }
$ledgerTotal = (int) rev_scalar($pdo, "SELECT COUNT(*) FROM platform_revenue_ledger WHERE {$ledgerWhere}", $ledgerParams);
$ledgerRows = rev_rows($pdo, "SELECT * FROM platform_revenue_ledger WHERE {$ledgerWhere} ORDER BY created_at DESC, id DESC LIMIT {$perPage} OFFSET {$offset}", $ledgerParams);
$rules = rev_rows($pdo, "SELECT * FROM platform_fee_rules ORDER BY revenue_stream, applies_to, id");
$plans = rev_rows($pdo, "SELECT * FROM seller_subscription_plans ORDER BY monthly_fee, id");
$promotions = rev_rows($pdo, "SELECT p.*, s.store_name, s.email seller_email, l.title listing_title FROM marketplace_promotions p LEFT JOIN marketplace_sellers s ON s.id=p.seller_id LEFT JOIN marketplace_listings l ON l.id=p.listing_id ORDER BY p.created_at DESC LIMIT {$perPage} OFFSET {$offset}");
$promotionTotal = (int) rev_scalar($pdo, "SELECT COUNT(*) FROM marketplace_promotions");
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>NATCODEV Revenue Workspace</title><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"><link rel="stylesheet" href="../../assets/css/admin-workspaces.css"><style>body{background:#f4f8f2;color:#162119}.rev-shell{display:grid;grid-template-columns:280px 1fr;min-height:100vh}.rev-side{background:#07351f;color:#fff;padding:24px 18px}.rev-side a{color:#dbeee2;text-decoration:none;padding:11px 12px;border-radius:8px;font-weight:800}.rev-side a.active,.rev-side a:hover{background:#16834a;color:#fff}.rev-main{padding:28px}.money{font-variant-numeric:tabular-nums}.card{border:1px solid #dfe9dc;border-radius:10px}.table td{vertical-align:middle}@media(max-width:920px){.rev-shell{grid-template-columns:1fr}.rev-main{padding:16px}}</style></head><body><div class="rev-shell"><aside class="rev-side"><h4>NATCODEV</h4><p class="small text-white-50">Revenue Control Room</p><nav class="d-grid gap-1 mt-4"><?php foreach (['overview'=>'Overview','rules'=>'Fee Rules','ledger'=>'Revenue Ledger','marketplace'=>'Marketplace Economics','registry'=>'Grower Certificate Fees','provider-fees'=>'Provider Accreditation','plans'=>'Seller Plans','promotions'=>'Promotions'] as $key=>$label): ?><a class="<?= $page === $key ? 'active' : '' ?>" href="?page=<?= rev_e($key) ?>"><?= rev_e($label) ?></a><?php endforeach; ?><hr class="border-light opacity-25"><a href="../index.php">Workspace Hub</a></nav></aside><main class="rev-main">
<?= admin_workspace_operator_strip($pdo, ['asset_prefix'=>'../../','profile_href'=>'../profile.php','password_href'=>'../profile.php#password','logout_action'=>'../admin.php','title'=>'Revenue workspace','placeholder'=>'Search revenue, fees, promotions...']) ?>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4"><div><span class="small text-success fw-bold text-uppercase">Platform operator revenue</span><h1 class="h3 mb-1">Revenue Workspace</h1><p class="text-secondary mb-0">Manage fees, promotion revenue, subscriptions, certificate access charges, and revenue ledger.</p></div><a class="btn btn-outline-success" href="?export=ledger">Export Ledger</a></div>
<?php if ($message): ?><div class="alert alert-success"><?= rev_e($message) ?></div><?php endif; ?><?php if ($error): ?><div class="alert alert-danger"><?= rev_e($error) ?></div><?php endif; ?>
<div class="row g-3 mb-4"><?php $overviewKpis = [['Total Revenue',$totalRevenue],['This Month',$monthRevenue],['Marketplace Gross',$marketGross],['Seller Net',$sellerNet],['Grower Certificate Fees',$certificateAccessRevenue],['Provider Accreditation Fees',$providerCertificateAccessRevenue]]; if ($showPlatformEarningsSummary) { $overviewKpis[] = ['Platform Earnings',$platformCertificateEarnings]; } foreach ($overviewKpis as $kpi): ?><div class="col-md"><div class="card h-100"><div class="card-body"><small class="text-secondary"><?= rev_e($kpi[0]) ?></small><div class="h4 money"><?= rev_e(rev_money((float) $kpi[1])) ?></div></div></div></div><?php endforeach; ?><div class="col-md"><div class="card h-100"><div class="card-body"><small class="text-secondary">Pending Promotions</small><div class="h4"><?= number_format($pendingPromotions) ?></div><small><?= number_format($activeRules) ?> active rule(s)</small></div></div></div></div>
<?php if ($page === 'overview'): ?><div class="card"><div class="card-body"><h2 class="h5">Revenue Health</h2><p class="text-secondary">Use this workspace to govern how the platform earns money without hiding funds from sellers, growers, providers, or buyers.</p><div class="row g-3"><div class="col-md-4"><a class="btn btn-success w-100" href="?page=rules">Manage Fee Rules</a></div><div class="col-md-4"><a class="btn btn-outline-success w-100" href="?page=promotions">Review Promotions</a></div><div class="col-md-4"><a class="btn btn-outline-success w-100" href="?page=registry">Certificate Fees</a></div></div></div></div><?php if ($showPlatformEarningsSummary): ?><div class="card mt-3"><div class="card-body"><h2 class="h5">Platform Earnings</h2><p class="text-secondary">Provider accreditation fees and grower certificate access/renewal fees are retained in the NATCODEV platform revenue ledger and separated from participant wallet balances. Use this summary when reviewing withdrawal controls and platform cash flow.</p><div class="row g-3"><div class="col-md-4"><div class="border rounded p-3 h-100"><small class="text-secondary">Grower Certificate Access / Renewal</small><div class="h4 money"><?= rev_e(rev_money((float) $certificateAccessRevenue)) ?></div></div></div><div class="col-md-4"><div class="border rounded p-3 h-100"><small class="text-secondary">Provider Accreditation Fees</small><div class="h4 money"><?= rev_e(rev_money((float) $providerCertificateAccessRevenue)) ?></div></div></div><div class="col-md-4"><div class="border rounded p-3 h-100"><small class="text-secondary">Combined Platform Earnings</small><div class="h4 money"><?= rev_e(rev_money((float) $platformCertificateEarnings)) ?></div></div></div></div></div></div><?php endif; ?><?php endif; ?>
<?php if ($page === 'ledger'): ?><form class="card card-body mb-3"><input type="hidden" name="page" value="ledger"><div class="row g-2"><div class="col-md-8"><input class="form-control" name="search" value="<?= rev_e($search) ?>" placeholder="Search reference, module, rule, description"></div><div class="col-md-2"><select class="form-select" name="per_page"><option><?= (int) $perPage ?></option><option>25</option><option>50</option><option>100</option></select></div><div class="col-md-2"><button class="btn btn-success w-100">Filter</button></div></div></form><div class="card"><div class="table-responsive"><table class="table mb-0"><tr><th>Date</th><th>Reference</th><th>Module</th><th>Gross</th><th>Revenue</th><th>Status</th><th>Description</th></tr><?php foreach ($ledgerRows as $row): ?><tr><td><?= rev_e($row['created_at']) ?></td><td><?= rev_e($row['revenue_ref']) ?></td><td><?= rev_e($row['source_module']) ?></td><td><?= rev_e(rev_money((float) $row['gross_amount'])) ?></td><td><?= rev_e(rev_money((float) $row['revenue_amount'])) ?></td><td><?= rev_e($row['status']) ?></td><td><?= rev_e($row['description']) ?></td></tr><?php endforeach; ?></table></div></div><?= rev_pages($ledgerTotal, $perPage, $currentPage) ?><?php endif; ?>
<?php if ($page === 'rules'): ?><div class="card mb-3"><div class="card-body"><div class="d-flex justify-content-between align-items-start flex-wrap gap-3"><div><h2 class="h5 mb-1">Fee Rules & Setup</h2><p class="text-secondary mb-0">Configure transparent platform earning rules for marketplace orders, buyer checkout service fees, wallet processing, seller plans, and certificate-related platform earnings. These rules feed the revenue ledger and keep participant balances separate from NATCODEV platform earnings.</p></div><span class="badge text-bg-success"><?= number_format(count($rules)) ?> rule(s)</span></div></div></div><?php if (!$rules): ?><div class="alert alert-warning"><strong>No fee rules are available.</strong> The revenue schema is present, but no default rules were loaded. Refresh this page once more or ask Super Admin to reseed revenue defaults.</div><?php endif; ?><div class="row g-3"><?php foreach ($rules as $rule): ?><div class="col-lg-6"><form method="post" class="card card-body h-100"><input type="hidden" name="_csrf" value="<?= rev_e(csrf_token()) ?>"><input type="hidden" name="action" value="save_rule"><input type="hidden" name="id" value="<?= (int) $rule['id'] ?>"><div class="d-flex justify-content-between align-items-start gap-2 mb-2"><div><small class="text-success fw-bold text-uppercase"><?= rev_e($rule['revenue_stream'] ?? 'revenue') ?></small><h3 class="h6 mb-0"><?= rev_e($rule['rule_key'] ?? ('Rule #' . (int) $rule['id'])) ?></h3><small class="text-secondary">Applies to: <?= rev_e($rule['applies_to'] ?? 'all') ?></small></div><span class="badge <?= !empty($rule['is_active']) ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= !empty($rule['is_active']) ? 'Active' : 'Inactive' ?></span></div><label class="form-label">Title<input class="form-control" name="title" value="<?= rev_e($rule['title']) ?>"></label><div class="row g-2"><div class="col"><label class="form-label">Type<select class="form-select" name="fee_type"><option value="percentage" <?= $rule['fee_type']==='percentage'?'selected':'' ?>>Percentage</option><option value="fixed" <?= $rule['fee_type']==='fixed'?'selected':'' ?>>Fixed</option></select></label></div><div class="col"><label class="form-label">Percent<input class="form-control" name="percentage_rate" value="<?= rev_e($rule['percentage_rate']) ?>"></label></div><div class="col"><label class="form-label">Fixed<input class="form-control" name="fixed_amount" value="<?= rev_e($rule['fixed_amount']) ?>"></label></div></div><div class="row g-2"><div class="col"><label class="form-label">Minimum<input class="form-control" name="minimum_fee" value="<?= rev_e($rule['minimum_fee']) ?>"></label></div><div class="col"><label class="form-label">Maximum<input class="form-control" name="maximum_fee" value="<?= rev_e($rule['maximum_fee']) ?>"></label></div></div><label class="form-label">Notes<textarea class="form-control" name="notes"><?= rev_e($rule['notes'] ?? '') ?></textarea></label><label class="form-check"><input class="form-check-input" type="checkbox" name="is_active" <?= !empty($rule['is_active']) ? 'checked' : '' ?>> Active</label><button class="btn btn-success mt-2">Save Rule</button></form></div><?php endforeach; ?></div><?php endif; ?>
<?php if ($page === 'plans'): ?><div class="row g-3"><?php foreach ($plans as $plan): ?><div class="col-lg-4"><form method="post" class="card card-body h-100"><input type="hidden" name="_csrf" value="<?= rev_e(csrf_token()) ?>"><input type="hidden" name="action" value="save_plan"><input type="hidden" name="id" value="<?= (int) $plan['id'] ?>"><label class="form-label">Plan<input class="form-control" name="title" value="<?= rev_e($plan['title']) ?>"></label><label class="form-label">Monthly<input class="form-control" name="monthly_fee" value="<?= rev_e($plan['monthly_fee']) ?>"></label><label class="form-label">Annual<input class="form-control" name="annual_fee" value="<?= rev_e($plan['annual_fee']) ?>"></label><label class="form-label">Product limit<input class="form-control" name="product_limit" value="<?= (int) $plan['product_limit'] ?>"></label><label class="form-label">Promotion credits<input class="form-control" name="promotion_credits" value="<?= (int) $plan['promotion_credits'] ?>"></label><label class="form-check"><input class="form-check-input" type="checkbox" name="is_active" <?= !empty($plan['is_active']) ? 'checked' : '' ?>> Active</label><button class="btn btn-success mt-2">Save Plan</button></form></div><?php endforeach; ?></div><?php endif; ?>
<?php if ($page === 'promotions' || $page === 'marketplace'): ?><div class="alert alert-info d-flex justify-content-between align-items-center gap-3 flex-wrap"><span>Promotions sync with marketplace seller requests and the platform revenue ledger.</span><form method="post"><input type="hidden" name="_csrf" value="<?= rev_e(csrf_token()) ?>"><input type="hidden" name="action" value="sync_promotions"><button class="btn btn-sm btn-success">Sync Active Promotion Revenue</button></form></div><div class="card"><div class="table-responsive"><table class="table mb-0"><tr><th>Campaign</th><th>Store</th><th>Placement</th><th>Budget</th><th>Status</th><th>Decision</th></tr><?php foreach ($promotions as $row): ?><tr><td><strong><?= rev_e($row['title']) ?></strong><br><small><?= rev_e($row['promo_ref'] ?? '#' . $row['id']) ?></small></td><td><?= rev_e($row['store_name']) ?><br><small><?= rev_e($row['seller_email'] ?? '') ?></small></td><td><?= rev_e(marketplace_status_label((string) ($row['placement'] ?? 'marketplace'))) ?></td><td><?= rev_e(rev_money((float) ($row['amount'] ?? 0))) ?></td><td><?= rev_e(marketplace_status_label((string) $row['status'])) ?></td><td><form method="post" class="d-flex gap-2"><input type="hidden" name="_csrf" value="<?= rev_e(csrf_token()) ?>"><input type="hidden" name="action" value="review_promotion"><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><select class="form-select form-select-sm" name="status"><?php foreach (['pending_admin_review'=>'Pending review','active'=>'Approve & activate','paused'=>'Pause','rejected'=>'Reject'] as $s=>$label): ?><option value="<?= rev_e($s) ?>" <?= $row['status'] === $s ? 'selected' : '' ?>><?= rev_e($label) ?></option><?php endforeach; ?></select><button class="btn btn-sm btn-success">Save</button></form></td></tr><?php endforeach; ?><?php if (!$promotions): ?><tr><td colspan="6" class="text-center text-secondary py-4">No promotion request yet.</td></tr><?php endif; ?></table></div></div><?= rev_pages($promotionTotal, $perPage, $currentPage) ?><?php endif; ?>
<?php if ($page === 'registry'): ?>
<div class="row g-3">
  <div class="col-12">
    <div class="card mb-3">
      <div class="card-body">
        <h2 class="h5">Certificate Registry</h2>
        <p class="text-secondary mb-0">This page is built from live database records: certificate access payments, provider accreditation payments, provider registry, application records, users, and the platform revenue ledger.</p>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="row g-3">
      <div class="col-md-6">
        <div class="card h-100">
          <div class="card-body">
            <small class="text-secondary">Grower Certificate Fee</small>
            <div class="h4 mt-2"><?= $certificateFeeConfig['enabled'] ? 'Enabled' : 'Disabled' ?></div>
            <div class="small text-muted">Individual: <?= rev_e(rev_money($certificateFeeConfig['individual_fee'])) ?> / <?= (int) $certificateFeeConfig['individual_validity_months'] ?> mo</div>
            <div class="small text-muted">Corporate: <?= rev_e(rev_money($certificateFeeConfig['corporate_fee'])) ?> / <?= (int) $certificateFeeConfig['corporate_validity_months'] ?> mo</div>
            <div class="small text-muted">Cooperative: <?= rev_e(rev_money($certificateFeeConfig['cooperative_fee'])) ?> / <?= (int) $certificateFeeConfig['cooperative_validity_months'] ?> mo</div>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card h-100">
          <div class="card-body">
            <small class="text-secondary">Provider Accreditation Fee</small>
            <div class="h4 mt-2"><?= $providerCertificateFeeConfig['enabled'] ? 'Enabled' : 'Disabled' ?></div>
            <div class="small text-muted">Fee: <?= rev_e(rev_money((float) $providerCertificateFeeConfig['amount'])) ?></div>
            <div class="small text-muted">Validity: <?= (int) $providerCertificateFeeConfig['validity_months'] ?> months</div>
          </div>
        </div>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-body">
        <h3 class="h6">Fee Configuration</h3>
        <form method="post">
          <input type="hidden" name="_csrf" value="<?= rev_e(csrf_token()) ?>">
          <input type="hidden" name="action" value="save_grower_certificate_fees">
          <div class="row g-3">
            <div class="col-12">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="growerFeeEnabled" name="enabled" <?= $certificateFeeConfig['enabled'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="growerFeeEnabled">Grower certificate access fees enabled</label>
              </div>
            </div>
            <?php foreach (grower_member_types() as $type => $label): $fee = (float) ($certificateFeeConfig[$type . '_fee'] ?? 0); $validity = (int) ($certificateFeeConfig[$type . '_validity_months'] ?? 12); ?>
            <div class="col-md-6">
              <div class="mb-3"><label class="form-label"><?= rev_e($label) ?> Fee</label><input class="form-control" name="<?= rev_e($type) ?>_fee" value="<?= rev_e(number_format($fee, 2, '.', '')) ?>"></div>
            </div>
            <div class="col-md-6">
              <div class="mb-3"><label class="form-label">Validity Months</label><input class="form-control" name="<?= rev_e($type) ?>_validity_months" value="<?= rev_e($validity) ?>"></div>
            </div>
            <?php endforeach; ?>
          </div>
          <div class="border-top pt-3 mt-3">
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" id="providerFeeEnabled" name="provider_enabled" <?= !empty($providerCertificateFeeConfig['enabled']) ? 'checked' : '' ?>>
              <label class="form-check-label" for="providerFeeEnabled">Provider accreditation access fee enabled</label>
            </div>
            <div class="row g-3">
              <div class="col-md-6"><label class="form-label">Provider access fee</label><input class="form-control" name="provider_amount" value="<?= rev_e(number_format((float) $providerCertificateFeeConfig['amount'], 2, '.', '')) ?>"></div>
              <div class="col-md-6"><label class="form-label">Validity months</label><input class="form-control" name="provider_validity_months" value="<?= rev_e((int) $providerCertificateFeeConfig['validity_months']) ?>"></div>
            </div>
          </div>
          <button class="btn btn-success mt-3">Save Fee Rules</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="row g-3">
      <div class="col-md-6">
        <div class="card h-100">
          <div class="card-body">
            <small class="text-secondary">Grower Certificate Paid Access</small>
            <div class="h3 mt-2"><?= number_format($certificateAccessCount) ?></div>
            <div class="small text-muted">Active: <?= number_format($certificateAccessValidCount) ?>, Expired: <?= number_format($certificateAccessExpiredCount) ?></div>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card h-100">
          <div class="card-body">
            <small class="text-secondary">Provider Accreditation Paid Access</small>
            <div class="h3 mt-2"><?= number_format($providerCertificateAccessCount) ?></div>
            <div class="small text-muted">Active: <?= number_format($providerCertificateAccessValidCount) ?>, Expired: <?= number_format($providerCertificateAccessExpiredCount) ?></div>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card h-100">
          <div class="card-body">
            <small class="text-secondary">Revenue from grower fees</small>
            <div class="h3 mt-2"><?= rev_e(rev_money($certificateAccessRevenue)) ?></div>
            <div class="small text-muted">Ledger-sourced total</div>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card h-100">
          <div class="card-body">
            <small class="text-secondary">Revenue from provider fees</small>
            <div class="h3 mt-2"><?= rev_e(rev_money($providerCertificateAccessRevenue)) ?></div>
            <div class="small text-muted">Ledger-sourced total</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card mb-3">
      <div class="card-body">
        <h3 class="h6">Recent Grower Certificate Access Payments</h3>
        <div class="table-responsive mt-3">
          <table class="table table-sm table-striped mb-0">
            <thead><tr><th>Date</th><th>User</th><th>Application</th><th>Member Type</th><th>Amount</th><th>Status</th><th>Valid Until</th><th>Reference</th></tr></thead>
            <tbody>
              <?php foreach ($certificateAccessPayments as $payment): ?>
              <tr>
                <td><?= rev_e($payment['paid_at'] ?? '-') ?></td>
                <td><?= rev_e($payment['user_name'] ?: ($payment['user_email'] ?? 'Unknown')) ?></td>
                <td><?= rev_e($payment['application_name'] ?? 'Unknown') ?></td>
                <td><?= rev_e($payment['member_type'] ?? '-') ?></td>
                <td><?= rev_e(rev_money((float) ($payment['amount'] ?? 0))) ?></td>
                <td><?= rev_e($payment['status'] ?? '-') ?></td>
                <td><?= rev_e($payment['valid_until'] ?? '-') ?></td>
                <td><?= rev_e($payment['reference'] ?? '') ?></td>
              </tr>
              <?php endforeach; ?>
              <?php if (!$certificateAccessPayments): ?><tr><td colspan="8" class="text-center text-secondary py-3">No grower certificate payment records found.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-body">
        <h3 class="h6">Recent Provider Accreditation Payments</h3>
        <div class="table-responsive mt-3">
          <table class="table table-sm table-striped mb-0">
            <thead><tr><th>Date</th><th>User</th><th>Provider</th><th>Amount</th><th>Status</th><th>Valid Until</th><th>Reference</th></tr></thead>
            <tbody>
              <?php foreach ($providerAccPayments as $payment): ?>
              <tr>
                <td><?= rev_e($payment['paid_at'] ?? '-') ?></td>
                <td><?= rev_e($payment['user_name'] ?: ($payment['user_email'] ?? 'Unknown')) ?></td>
                <td><?= rev_e($payment['provider_name'] ?? ('ID ' . ((int) ($payment['provider_id'] ?? 0)))) ?></td>
                <td><?= rev_e(rev_money((float) ($payment['amount'] ?? 0))) ?></td>
                <td><?= rev_e($payment['status'] ?? '-') ?></td>
                <td><?= rev_e($payment['valid_until'] ?? '-') ?></td>
                <td><?= rev_e($payment['reference'] ?? '') ?></td>
              </tr>
              <?php endforeach; ?>
              <?php if (!$providerAccPayments): ?><tr><td colspan="7" class="text-center text-secondary py-3">No provider accreditation payment records found.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
<?php if ($page === 'provider-fees'): ?>
<div class="card mb-3">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
      <div>
        <h2 class="h5 mb-1">Provider Accreditation & Grower Charging</h2>
        <p class="text-secondary mb-0">Set the accreditation charges that unlock provider certificate access and grower certificate access or renewals. All collections are posted to the NATCODEV platform revenue ledger.</p>
      </div>
      <span class="badge text-bg-success">RBAC Revenue Control</span>
    </div>
    <div class="row g-3 mt-3">
      <div class="col-md-3"><div class="border rounded p-3 h-100"><small class="text-secondary">Grower Charging</small><div class="h4 mt-1"><?= $certificateFeeConfig['enabled'] ? 'Enabled' : 'Disabled' ?></div></div></div>
      <div class="col-md-3"><div class="border rounded p-3 h-100"><small class="text-secondary">Grower Revenue</small><div class="h4 money mt-1"><?= rev_e(rev_money((float) $certificateAccessRevenue)) ?></div><small><?= number_format($certificateAccessCount) ?> paid access</small></div></div>
      <div class="col-md-3"><div class="border rounded p-3 h-100"><small class="text-secondary">Provider Charging</small><div class="h4 mt-1"><?= $providerCertificateFeeConfig['enabled'] ? 'Enabled' : 'Disabled' ?></div></div></div>
      <div class="col-md-3"><div class="border rounded p-3 h-100"><small class="text-secondary">Provider Revenue</small><div class="h4 money mt-1"><?= rev_e(rev_money((float) $providerCertificateAccessRevenue)) ?></div><small><?= number_format($providerCertificateAccessCount) ?> paid access</small></div></div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <form method="post" class="card card-body h-100">
      <input type="hidden" name="_csrf" value="<?= rev_e(csrf_token()) ?>">
      <input type="hidden" name="action" value="save_grower_certificate_fees">
      <input type="hidden" name="return_page" value="provider-fees">
      <h3 class="h6">Grower Certificate Charging Setup</h3>
      <p class="text-secondary small">These prices control what growers pay for certificate access and accreditation renewal by membership type.</p>
      <div class="form-check form-switch mb-3">
        <input class="form-check-input" type="checkbox" id="providerPageGrowerFeeEnabled" name="enabled" <?= $certificateFeeConfig['enabled'] ? 'checked' : '' ?>>
        <label class="form-check-label" for="providerPageGrowerFeeEnabled">Charge growers for certificate access and renewal</label>
      </div>
      <div class="row g-3">
        <?php foreach (grower_member_types() as $type => $label): $fee = (float) ($certificateFeeConfig[$type . '_fee'] ?? 0); $validity = (int) ($certificateFeeConfig[$type . '_validity_months'] ?? 12); ?>
        <div class="col-md-6">
          <label class="form-label"><?= rev_e($label) ?> fee</label>
          <input class="form-control" name="<?= rev_e($type) ?>_fee" value="<?= rev_e(number_format($fee, 2, '.', '')) ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label"><?= rev_e($label) ?> validity months</label>
          <input class="form-control" name="<?= rev_e($type) ?>_validity_months" value="<?= rev_e($validity) ?>">
        </div>
        <?php endforeach; ?>
      </div>
      <div class="border-top pt-3 mt-4">
        <h3 class="h6">Provider Accreditation Charging Setup</h3>
        <p class="text-secondary small">These prices control what providers pay before accessing accreditation certificate details.</p>
        <div class="form-check form-switch mb-3">
          <input class="form-check-input" type="checkbox" id="providerPageProviderFeeEnabled" name="provider_enabled" <?= !empty($providerCertificateFeeConfig['enabled']) ? 'checked' : '' ?>>
          <label class="form-check-label" for="providerPageProviderFeeEnabled">Charge providers for accreditation access</label>
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Provider access fee</label>
            <input class="form-control" name="provider_amount" value="<?= rev_e(number_format((float) $providerCertificateFeeConfig['amount'], 2, '.', '')) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Validity months</label>
            <input class="form-control" name="provider_validity_months" value="<?= rev_e((int) $providerCertificateFeeConfig['validity_months']) ?>">
          </div>
        </div>
      </div>
      <button class="btn btn-success mt-4">Save Accreditation Charges</button>
    </form>
  </div>

  <div class="col-lg-5">
    <div class="card card-body mb-3">
      <h3 class="h6">Current Grower Fee Matrix</h3>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead><tr><th>Type</th><th>Fee</th><th>Validity</th></tr></thead>
          <tbody>
            <?php foreach (grower_member_types() as $type => $label): ?>
            <tr>
              <td><?= rev_e($label) ?></td>
              <td><?= rev_e(rev_money((float) ($certificateFeeConfig[$type . '_fee'] ?? 0))) ?></td>
              <td><?= (int) ($certificateFeeConfig[$type . '_validity_months'] ?? 12) ?> months</td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <div class="card card-body">
      <h3 class="h6">Current Provider Fee</h3>
      <div class="row g-3">
        <div class="col-6"><small class="text-secondary">Access Fee</small><div class="h4 money"><?= rev_e(rev_money((float) $providerCertificateFeeConfig['amount'])) ?></div></div>
        <div class="col-6"><small class="text-secondary">Validity</small><div class="h4"><?= (int) $providerCertificateFeeConfig['validity_months'] ?> mo</div></div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mt-1">
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-body">
        <h3 class="h6">Recent Grower Certificate Access Payments</h3>
        <div class="table-responsive mt-3">
          <table class="table table-sm table-striped mb-0">
            <thead><tr><th>Date</th><th>User</th><th>Type</th><th>Amount</th><th>Valid Until</th></tr></thead>
            <tbody>
              <?php foreach ($certificateAccessPayments as $payment): ?>
              <tr>
                <td><?= rev_e($payment['paid_at'] ?? '-') ?></td>
                <td><?= rev_e($payment['user_name'] ?: ($payment['user_email'] ?? 'Unknown')) ?></td>
                <td><?= rev_e($payment['member_type'] ?? '-') ?></td>
                <td><?= rev_e(rev_money((float) ($payment['amount'] ?? 0))) ?></td>
                <td><?= rev_e($payment['valid_until'] ?? '-') ?></td>
              </tr>
              <?php endforeach; ?>
              <?php if (!$certificateAccessPayments): ?><tr><td colspan="5" class="text-center text-secondary py-3">No grower certificate payment records found.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-body">
        <h3 class="h6">Recent Provider Accreditation Payments</h3>
        <div class="table-responsive mt-3">
          <table class="table table-sm table-striped mb-0">
            <thead><tr><th>Date</th><th>User</th><th>Provider</th><th>Amount</th><th>Valid Until</th></tr></thead>
            <tbody>
              <?php foreach ($providerAccPayments as $payment): ?>
              <tr>
                <td><?= rev_e($payment['paid_at'] ?? '-') ?></td>
                <td><?= rev_e($payment['user_name'] ?: ($payment['user_email'] ?? 'Unknown')) ?></td>
                <td><?= rev_e($payment['provider_name'] ?? ('ID ' . ((int) ($payment['provider_id'] ?? 0)))) ?></td>
                <td><?= rev_e(rev_money((float) ($payment['amount'] ?? 0))) ?></td>
                <td><?= rev_e($payment['valid_until'] ?? '-') ?></td>
              </tr>
              <?php endforeach; ?>
              <?php if (!$providerAccPayments): ?><tr><td colspan="5" class="text-center text-secondary py-3">No provider accreditation payments found yet.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
</main></div><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script></body></html>
