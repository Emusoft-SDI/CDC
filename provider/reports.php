<?php
declare(strict_types=1);
require_once __DIR__ . '/_provider.php';

provider_simple_page('reports', 'Reports', 'Provider intelligence across accreditation, listings, coverage, orders, wallet, Academy, and support.', function(PDO $pdo, array $user, array $provider, array $counts): void {
    echo '<div class="kpis"><div class="kpi"><i class="fas fa-store"></i><span><b>' . (int) $counts['activeListings'] . '</b><br>Listings</span></div><div class="kpi"><i class="fas fa-cart-shopping"></i><span><b>' . (int) $counts['orders'] . '</b><br>Orders</span></div><div class="kpi"><i class="fas fa-location-dot"></i><span><b>' . (int) $counts['coverageLgas'] . '</b><br>LGAs</span></div><div class="kpi"><i class="fas fa-graduation-cap"></i><span><b>' . (int) $counts['academy'] . '</b><br>Courses</span></div><div class="kpi"><i class="fas fa-wallet"></i><span><b>' . e(marketplace_money((float) $counts['wallet'])) . '</b><br>Wallet</span></div><div class="kpi"><i class="fas fa-headset"></i><span><b>' . (int) $counts['support'] . '</b><br>Support</span></div></div><section class="card"><h2>Provider Intelligence</h2><p>Reports use live provider registry, provider offerings, marketplace orders, wallet, Academy, and support data where those tables exist. Export and deeper charts can be extended from this page.</p></section>';
});
