<!-- List webinars -->
<?php
$webinars = $pdo->query("SELECT * FROM webinars WHERE start_time > NOW() ORDER BY start_time")->fetchAll();
foreach ($webinars as $webinar):
?>
<div class="webinar-card">
  <h3><?= htmlspecialchars($webinar['title']) ?></h3>
  <p><?= date('M j, Y g:i A', strtotime($webinar['start_time'])) ?> (<?= $webinar['duration_minutes'] ?> mins)</p>
  <p><?= htmlspecialchars(substr($webinar['description'], 0, 150)) ?>...</p>
  
  <?php if ($webinar['is_free']): ?>
    <button onclick="registerWebinar(<?= $webinar['id'] ?>)">Register (Free)</button>
  <?php else: ?>
    <p>Fee: ₦<?= number_format($webinar['price'], 2) ?></p>
    <button onclick="registerWebinar(<?= $webinar['id'] ?>)">Register (Paid)</button>
  <?php endif; ?>
</div>
<?php endforeach; ?>