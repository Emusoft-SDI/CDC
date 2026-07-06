<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/market/_market.php';

$pdo = db();
$q = trim((string) ($_GET['q'] ?? ''));
$results = [];
$static = [
    ['Register as Grower', 'Start grower registration and verification.', 'apply.php?type=farmer', 'Registration'],
    ['Marketplace', 'Browse products, inputs, services, sellers, and orders.', 'market/index.php', 'Marketplace'],
    ['NATCODEV Academy', 'Courses, certificates, accreditation, and role readiness.', 'academy/index.php', 'Academy'],
    ['Provider Registry', 'Provider accreditation, services, documents, and NATCODEV network relationship.', 'provider/index.php', 'Providers'],
    ['Support Center', 'Get help for account, marketplace, Academy, wallet, provider, or field issues.', 'support/index.php', 'Support'],
    ['Verify Certificate', 'Check a NATCODEV certificate reference.', 'verify-certificate.php', 'Certificates'],
];
foreach ($static as $item) {
    if ($q === '' || stripos(implode(' ', $item), $q) !== false) {
        $results[] = $item;
    }
}
if ($q !== '' && app_table_exists($pdo, 'marketplace_listings')) {
    $stmt = $pdo->prepare("SELECT l.id, l.title, l.summary, s.store_name FROM marketplace_listings l JOIN marketplace_sellers s ON s.id=l.seller_id WHERE l.approval_status='approved' AND (l.title LIKE ? OR l.summary LIKE ? OR s.store_name LIKE ?) ORDER BY l.updated_at DESC, l.id DESC LIMIT 12");
    $like = '%' . $q . '%';
    $stmt->execute([$like, $like, $like]);
    foreach ($stmt->fetchAll() as $row) {
        $results[] = [(string) $row['title'], (string) ($row['summary'] ?: 'Marketplace listing from ' . $row['store_name']), 'market/product.php?id=' . (int) $row['id'], 'Marketplace'];
    }
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Search NATCODEV</title><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"><style>body{margin:0;font-family:"Segoe UI",Arial,sans-serif;background:#f6faf7;color:#101828}.wrap{max-width:980px;margin:0 auto;padding:34px 18px}.brand{display:flex;gap:12px;align-items:center;color:#06451f;text-decoration:none;font-weight:950}.brand img{width:54px;height:54px;border-radius:50%;object-fit:contain;background:#fff}.panel{margin-top:22px;background:#fff;border:1px solid #dfe8d8;border-radius:12px;padding:20px;box-shadow:0 18px 42px rgba(16,24,40,.08)}form{display:flex;gap:10px}input{flex:1;border:1px solid #dfe8d8;border-radius:10px;padding:14px;font:inherit}button,.btn{border:1px solid #06451f;background:#06451f;color:#fff;border-radius:10px;padding:12px 16px;font-weight:900;text-decoration:none}.result{display:flex;justify-content:space-between;gap:16px;border-top:1px solid #eef2f4;padding:14px 0}.result:first-child{border-top:0}.tag{color:#0a7a3a;font-weight:900;font-size:.82rem}.muted{color:#667085}@media(max-width:700px){form,.result{display:grid}}</style></head><body><main class="wrap"><a class="brand" href="index.php"><img src="<?= e(app_primary_logo_url()) ?>" alt="NATCODEV"><span>NATCODEV Search</span></a><section class="panel"><h1>Search NATCODEV</h1><form><input name="q" value="<?= e($q) ?>" placeholder="Search marketplace, academy, support, providers..."><button><i class="fas fa-search"></i> Search</button></form></section><section class="panel"><?php foreach ($results as $row): ?><div class="result"><div><span class="tag"><?= e($row[3]) ?></span><h3><?= e($row[0]) ?></h3><p class="muted"><?= e($row[1]) ?></p></div><a class="btn" href="<?= e($row[2]) ?>">Open</a></div><?php endforeach; ?><?php if (!$results): ?><p class="muted">No matching result yet. Try marketplace, academy, support, provider, certificate, wallet, or registration.</p><?php endif; ?></section></main></body></html>