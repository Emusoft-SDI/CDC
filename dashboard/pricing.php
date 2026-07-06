<?php
declare(strict_types=1);

require_once __DIR__ . '/_user_auth.php';
require_once __DIR__ . '/../lib/dashboard-layout.php';

$pdo = db();
if (!current_user($pdo)) {
    redirect_to('login.php');
}
?>
<?php dashboard_page_start('Account Upgrade', ['active' => 'pricing.php', 'description' => 'Upgrade access for premium platform features such as IoT monitoring, geofencing, advanced field intelligence, and expanded marketplace tools.', 'wide' => true]); ?>
  <section class="grid">
    <article class="card">
      <h2>IoT Farm Monitoring</h2>
      <p class="muted">Sensor-based monitoring for soil, water, weather, and farm condition signals when devices are activated for your account.</p>
      <span class="badge pending">Upgrade feature</span>
    </article>
    <article class="card">
      <h2>Geofencing & GPS Intelligence</h2>
      <p class="muted">Farm boundary validation, visit evidence, field activity monitoring, and location-based operational controls.</p>
      <span class="badge pending">Upgrade feature</span>
    </article>
    <article class="card">
      <h2>Advanced Reports</h2>
      <p class="muted">More detailed production, field, finance, marketplace, and compliance reporting for serious farm operations.</p>
      <span class="badge pending">Upgrade feature</span>
    </article>
    <article class="card">
      <h2>Marketplace Growth Tools</h2>
      <p class="muted">Expanded seller visibility, storefront tools, inventory intelligence, and stronger commercial reporting.</p>
      <span class="badge pending">Upgrade feature</span>
    </article>
  </section>
  <section class="card" style="margin-top:16px;">
    <h2>Wallet-Ready Upgrade Flow</h2>
    <p class="muted">Paid upgrades will be activated through wallet or direct payment when NATCODEV enables the selected feature package.</p>
    <div class="actions">
      <a class="button secondary" href="wallet.php">Open Wallet</a>
      <a class="button secondary" href="inbox.php">Ask Support</a>
    </div>
  </section>
<?php dashboard_page_end(); ?>
