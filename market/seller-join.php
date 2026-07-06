<?php
declare(strict_types=1);
require_once __DIR__ . '/_market.php';
$pdo = market_boot();
market_header('Become a Seller', 'seller', $pdo);
?>
<section class="mk-section">
  <div class="mk-section-head">
    <div>
      <h2>Sell on NATCODEV Marketplace</h2>
      <p>Choose the correct onboarding path. Marketplace sellers are public commerce users; providers are NATCODEV network users who may seek accreditation.</p>
    </div>
    <a class="mk-btn secondary" href="stores.php">View Sellers</a>
  </div>
  <div class="mk-grid">
    <article class="mk-card">
      <div class="mk-card-body">
        <div class="mk-store-avatar">SP</div>
        <h3>Input / Service Provider Registration</h3>
        <p class="mk-meta">For input suppliers, agronomists, service providers, logistics providers, processors, and accredited NATCODEV network providers.</p>
        <div class="mk-badges"><span class="mk-badge gold">Provider onboarding</span><span class="mk-badge">Accreditation path</span></div>
        <div class="mk-actions"><a class="mk-btn" href="../provider/index.php">Register as Provider</a></div>
      </div>
    </article>
    <article class="mk-card">
      <div class="mk-card-body">
        <div class="mk-store-avatar">SC</div>
        <h3>Marketplace Seller Central</h3>
        <p class="mk-meta">For public commerce users who want to create a store, add products, manage orders, payouts, promotions, and buyer support.</p>
        <div class="mk-badges"><span class="mk-badge">Seller store</span><span class="mk-badge blue">Products and payouts</span></div>
        <div class="mk-actions"><a class="mk-btn" href="seller-register.php">Register as Seller</a><a class="mk-btn secondary" href="seller-login.php">Login first</a></div>
      </div>
    </article>
    <article class="mk-card">
      <div class="mk-card-body">
        <div class="mk-store-avatar">GR</div>
        <h3>Grower or Cooperative Seller</h3>
        <p class="mk-meta">Growers and cooperatives should join the registry first, then request seller access from their enabled marketplace dashboard.</p>
        <div class="mk-badges"><span class="mk-badge">Registry first</span></div>
        <div class="mk-actions"><a class="mk-btn" href="../apply.php?type=farmer">Join Registry</a><a class="mk-btn secondary" href="../apply.php?type=cooperative">Cooperative</a></div>
      </div>
    </article>
  </div>
</section>
<?php market_footer(); ?>