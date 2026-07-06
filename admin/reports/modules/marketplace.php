<?php
$marketRows = report_rows($pdo, "
    SELECT ms.store_name, ms.seller_type, COUNT(ml.id) listings, COALESCE(SUM(mo.total_amount), 0) order_value
    FROM marketplace_sellers ms
    LEFT JOIN marketplace_listings ml ON ml.seller_id = ms.id
    LEFT JOIN marketplace_orders mo ON mo.seller_id = ms.id AND mo.created_at BETWEEN ? AND ?
    GROUP BY ms.id, ms.store_name, ms.seller_type
    ORDER BY order_value DESC, listings DESC
    LIMIT 12
", $dateParams);
$exportRows = $marketRows;
function render_module_table() {
    global $marketRows, $walletVolume, $spent, $budgeted;
    ?>
    <table><thead><tr><th>Seller</th><th>Type</th><th>Listings</th><th>Order Value</th></tr></thead><tbody>
      <?php foreach ($marketRows as $row): ?><tr><td><strong><?= e($row['store_name']) ?></strong></td><td><?= e(ucwords(str_replace('_', ' ', (string) $row['seller_type']))) ?></td><td><?= (int) $row['listings'] ?></td><td><?= report_money((float) $row['order_value']) ?></td></tr><?php endforeach; ?>
      <?php if (!$marketRows): ?><tr><td colspan="4">No marketplace records yet.</td></tr><?php endif; ?>
    </tbody></table>
    <p class="muted">Wallet funding/spend in this period: <strong><?= e(report_money($walletVolume)) ?></strong>. Budget spent: <strong><?= e(report_money($spent)) ?></strong> of <?= e(report_money($budgeted)) ?>.</p>
    <?php
}
