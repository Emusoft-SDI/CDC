<!-- Show all products -->
<?php
$stmt = $pdo->query("
    SELECT m.*, u.name as seller_name 
    FROM marketplace_items m
    LEFT JOIN users u ON m.seller_id = u.id
    WHERE m.is_active = 1
    ORDER BY m.created_at DESC
");
while ($item = $stmt->fetch()):
?>
<div class="product-card">
  <h3><?= htmlspecialchars($item['title']) ?></h3>
  <p><?= htmlspecialchars(substr($item['description'], 0, 100)) ?>...</p>
  <p><strong>₦<?= number_format($item['price'], 2) ?></strong></p>
  <p>Seller: <?= $item['seller_id'] == 0 ? 'NATCODEV' : htmlspecialchars($item['seller_name']) ?></p>
  
  <?php if ($item['seller_id'] == 0 || $userPlan === 'premium'): ?>
    <button onclick="buyProduct(<?= $item['id'] ?>)">Buy Now</button>
  <?php endif; ?>
</div>
<?php endwhile; ?>

<!-- Upgrade Plan Button -->
<?php if ($userPlan === 'basic'): ?>
  <div class="upgrade-banner">
    <h3>🚀 Upgrade to Premium!</h3>
    <p>List your own products and services</p>
    <button onclick="upgradePlan()">Upgrade Now - ₦5,000/year</button>
  </div>
<?php endif; ?>