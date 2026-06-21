<?php
declare(strict_types=1);
require_once __DIR__ . '/_seller.php';
$pdo = market_boot(); $user = market_require_user($pdo); seller_access_or_message($pdo, $user);
$ctx = seller_query_context($pdo, $user, false); seller_header('Seller Reports', 'reports', $user, $ctx['seller']); seller_kpis($ctx);
?>
<section class="sc-grid"><article class="sc-card sc-panel span-7"><div class="sc-panel-head"><h2>Marketplace Performance</h2></div><div style="height:260px;display:grid;align-items:end;grid-template-columns:repeat(8,1fr);gap:12px;padding:20px 10px"><?php foreach ([34,58,46,72,64,78,84,91] as $h): ?><div style="height:<?= $h ?>%;border-radius:8px 8px 0 0;background:linear-gradient(180deg,#0f8f4b,#dff7e8)"></div><?php endforeach; ?></div></article><article class="sc-card sc-panel span-5"><div class="sc-panel-head"><h2>Reportable Intelligence</h2></div><div class="sc-list"><div class="sc-row"><span class="sc-icon"><i data-lucide="package"></i></span><div>Listing approval and stock health</div><b><?= count($ctx['listings']) ?></b></div><div class="sc-row"><span class="sc-icon blue"><i data-lucide="shopping-bag"></i></span><div>Order conversion and fulfillment</div><b><?= count($ctx['orders']) ?></b></div><div class="sc-row"><span class="sc-icon gold"><i data-lucide="wallet"></i></span><div>Payout and settlement readiness</div><b><?= e(marketplace_money((float) $ctx['pendingPayout'])) ?></b></div></div></article></section>
<?php seller_footer(); ?>
