<?php
declare(strict_types=1);
require_once __DIR__ . '/_provider.php';

$pdo = provider_boot();
$user = provider_full_user($pdo, provider_require($pdo));
$provider = provider_active($pdo, $user);
$counts = provider_counts($pdo, $provider, $user);
$offerings = provider_offerings($pdo, (int) ($provider['id'] ?? 0), 7);

provider_page_start('Provider Dashboard', 'overview', $user, $provider, $counts);
?>
<div class="page-head">
  <div><h1>Provider Dashboard</h1><p>Manage your business, serve farmers, and grow your impact.</p></div>
  <div><a class="btn light" href="products.php">Input Provider</a> <a class="btn light" href="products.php?type=service">Service Provider</a></div>
</div>
<?php if (!$provider): ?>
  <section class="hero-card"><h2>Complete your provider registration.</h2><p>Your account is active, but no provider profile is linked yet.</p><a class="btn" href="index.php">Register Provider Profile</a></section>
<?php else: ?>
<div class="kpis">
  <div class="kpi"><i class="fas fa-shield-halved"></i><span><b><?= e(provider_status_label((string) $provider['status'])) ?></b><br>Accreditation Status</span></div>
  <div class="kpi"><i class="fas fa-store"></i><span><b><?= (int) $counts['activeListings'] ?></b><br>Active Listings</span></div>
  <div class="kpi"><i class="fas fa-users"></i><span><b><?= (int) $counts['requests'] ?></b><br>Service Requests</span></div>
  <div class="kpi"><i class="fas fa-cart-shopping"></i><span><b><?= (int) $counts['orders'] ?></b><br>Orders This Month</span></div>
  <div class="kpi"><i class="fas fa-wallet"></i><span><b><?= e(marketplace_money((float) $counts['wallet'])) ?></b><br>Wallet Balance</span></div>
  <div class="kpi"><i class="fas fa-location-dot"></i><span><b><?= (int) $counts['coverageStates'] ?> / 36</b><br>Coverage States</span></div>
</div>
<section class="hero-card"><h2><?= e((string) $provider['company_name']) ?></h2><p>Approved providers can list products, services, and training programs for NATCODEV marketplace and Academy audiences. Keep documents, coverage, settlement, and quality information current.</p><a class="btn" href="products.php"><i class="fas fa-plus"></i> Add Product or Service</a> <a class="btn light" href="academy.php"><i class="fas fa-graduation-cap"></i> Manage Training</a></section>
<div class="grid" style="margin-top:16px">
  <section class="card span-4"><div class="card-head"><h2>Accreditation Checklist</h2><a class="view" href="accreditation.php">Details</a></div><div class="list">
    <?php foreach (['Business Registration (CAC)', 'Tax Clearance Certificate', 'Input/Service Certificate', 'Good Agricultural Practice', 'Warehouse & Storage Inspection', 'Product Quality Compliance'] as $i => $item): ?>
      <div class="row"><span><i class="fas fa-lock" style="color:#08753a"></i> <?= e($item) ?></span><span class="badge <?= $i > 4 ? 'warn' : '' ?>"><?= $i > 4 ? 'Pending' : 'Verified' ?></span></div>
    <?php endforeach; ?>
  </div></section>
  <section class="card span-4"><div class="card-head"><h2>Product & Service Listing Health</h2><a class="view" href="products.php">View all</a></div><div class="list">
    <?php foreach ($offerings as $offering): ?><div class="row"><span><strong><?= e((string) $offering['name']) ?></strong><br><small><?= e((string) $offering['category']) ?></small></span><span class="badge"><?= e(provider_status_label((string) $offering['stock_status'])) ?></span></div><?php endforeach; ?>
    <?php if (!$offerings): ?><p>No listings yet.</p><?php endif; ?>
  </div></section>
  <section class="card span-4"><div class="card-head"><h2>Coverage Areas</h2><a class="view" href="coverage.php">Manage</a></div><p><strong>States Covered:</strong> <?= (int) $counts['coverageStates'] ?></p><p><strong>LGAs Covered:</strong> <?= (int) $counts['coverageLgas'] ?></p><p class="badge">Coverage visible to marketplace buyers after review.</p></section>
  <section class="card span-4"><div class="card-head"><h2>Latest Orders & Requests</h2><a class="view" href="orders.php">View all</a></div><div class="list"><div class="row"><span>Recent buyer requests and orders appear here.</span><span class="badge warn"><?= (int) $counts['requests'] ?></span></div></div></section>
  <section class="card span-4"><div class="card-head"><h2>Academy Compliance</h2><a class="view" href="academy.php">Courses</a></div><p style="font-size:2.2rem;font-weight:950;color:#06451f;margin:0"><?= (int) $counts['academy'] ?></p><p>Provider enrollments or training programs linked to your account.</p><a class="btn light" href="../academy/index.php?screen=catalog">Continue Learning</a></section>
  <section class="card span-4"><div class="card-head"><h2>Support Ticket Status</h2><a class="view" href="support.php">View all</a></div><p><strong>Open Tickets:</strong> <?= (int) $counts['support'] ?></p><p><strong>SLA Compliance:</strong> 92%</p></section>
  <section class="card span-12"><div class="card-head"><h2>Quick Actions</h2></div><div class="action-grid"><a class="quick" href="products.php"><i class="fas fa-box-open"></i>Add Product</a><a class="quick" href="products.php?type=service"><i class="fas fa-screwdriver-wrench"></i>Add Service</a><a class="quick" href="coverage.php"><i class="fas fa-location-dot"></i>Update Coverage</a><a class="quick" href="accreditation.php"><i class="fas fa-cloud-arrow-up"></i>Upload Document</a><a class="quick" href="orders.php"><i class="fas fa-cart-shopping"></i>View Orders</a><a class="quick" href="wallet.php"><i class="fas fa-wallet"></i>Fund Wallet</a><a class="quick" href="support.php"><i class="fas fa-headset"></i>Contact Support</a></div></section>
</div>
<?php endif; ?>
<?php provider_page_end(); ?>
