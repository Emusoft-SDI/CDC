<?php
$buyerOrders = array_sum(array_map(static fn(array $r): int => (int) ($r['order_count'] ?? 0), $buyers));
$buyerPaidValue = array_sum(array_map(static fn(array $r): float => (float) ($r['paid_value'] ?? 0), $buyers));
$buyerDeliveryIssues = array_sum(array_map(static fn(array $r): int => (int) ($r['open_delivery_count'] ?? 0), $buyers));
$buyerRefundExposure = array_sum(array_map(static fn(array $r): int => (int) ($r['refund_exposure_count'] ?? 0), $buyers));
?>
<div class="row g-3 mb-3">
  <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-secondary small">Buyer Profiles</div><div class="h2 mb-0"><?= number_format(count($buyers)) ?></div></div></div></div>
  <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-secondary small">Buyer Orders</div><div class="h2 mb-0"><?= number_format($buyerOrders) ?></div></div></div></div>
  <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-secondary small">Paid Value</div><div class="h2 mb-0"><?= e(mx_money($buyerPaidValue)) ?></div></div></div></div>
  <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-secondary small">Delivery / Refund Exposure</div><div class="h2 mb-0"><?= number_format($buyerDeliveryIssues) ?> / <?= number_format($buyerRefundExposure) ?></div></div></div></div>
</div>
<div class="card">
  <div class="card-header bg-white fw-bold">Marketplace Buyer Operations</div>
  <div class="table-responsive"><table class="table mb-0">
    <thead><tr><th>Buyer Profile</th><th>Orders</th><th>Paid Value</th><th>Delivery Issues</th><th>Refund Exposure</th><th>Support</th><th>Quick Actions</th></tr></thead>
    <tbody>
      <?php foreach($buyers as $row): ?>
        <tr>
          <td><strong><?= e((string)($row['name'] ?: 'Unnamed buyer')) ?></strong><br><small><?= e((string)$row['email']) ?><?= !empty($row['phone']) ? ' / ' . e((string)$row['phone']) : '' ?></small><br><span class="badge text-bg-light"><?= e((string)($row['account_status'] ?? 'active')) ?></span><br><small><?= e(marketplace_status_label((string)($row['buyer_type'] ?? 'individual'))) ?><?= !empty($row['preferred_state']) ? ' / ' . e((string)$row['preferred_state']) : '' ?></small></td>
          <td><strong><?= number_format((int)($row['order_count'] ?? 0)) ?></strong><br><small>Last: <?= e((string)($row['last_order_at'] ?: 'No order yet')) ?></small></td>
          <td><?= e(mx_money((float)($row['paid_value'] ?? 0))) ?></td>
          <td><span class="badge <?= (int)($row['open_delivery_count'] ?? 0) > 0 ? 'text-bg-warning' : 'text-bg-success' ?>"><?= number_format((int)($row['open_delivery_count'] ?? 0)) ?></span><br><small>Paid orders not delivered/closed</small></td>
          <td><span class="badge <?= (int)($row['refund_exposure_count'] ?? 0) > 0 ? 'text-bg-danger' : 'text-bg-success' ?>"><?= number_format((int)($row['refund_exposure_count'] ?? 0)) ?></span><br><small><?= e(mx_money((float)($row['refund_exposure_value'] ?? 0))) ?></small></td>
          <td><?= number_format((int)($row['ticket_count'] ?? 0)) ?> ticket(s)</td>
          <td><div class="d-flex gap-2 flex-wrap"><a class="btn btn-sm btn-outline-success" href="?page=orders&search=<?= e(urlencode((string)$row['email'])) ?>">Orders</a><a class="btn btn-sm btn-outline-success" href="?page=deliveries&search=<?= e(urlencode((string)$row['email'])) ?>">Delivery</a><a class="btn btn-sm btn-outline-success" href="../wallet/index.php?page=wallets&search=<?= e(urlencode((string)$row['email'])) ?>">Wallet</a><a class="btn btn-sm btn-outline-success" href="../support/index.php?search=<?= e(urlencode((string)$row['email'])) ?>">Support</a><a class="btn btn-sm btn-outline-success" href="../users.php?search=<?= e(urlencode((string)$row['email'])) ?>">User</a></div></td>
        </tr>
      <?php endforeach; ?>
      <?php if(!$buyers): ?><tr><td colspan="7" class="text-secondary">No buyer profiles yet.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>