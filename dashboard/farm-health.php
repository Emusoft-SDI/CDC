
<!-- dashboard/farm-health.php -->
<?php
// Check if user has IoT/satellite data
$sensors = $pdo->prepare("SELECT * FROM iot_sensors WHERE farm_id = ?");
$sensors->execute([$_SESSION['application_id']]);
$hasSensors = $sensors->rowCount() > 0;

$imagery = $pdo->prepare("SELECT * FROM farm_imagery WHERE farm_id = ? ORDER BY capture_date DESC LIMIT 5");
$imagery->execute([$_SESSION['application_id']]);
$recentImagery = $imagery->fetchAll();
?>
<div class="dashboard-section">
  <h2>Farm Health Monitoring</h2>
  
  <?php if ($hasSensors): ?>
    <div class="sensor-dashboard">
      <h3>Real-Time Sensor Data</h3>
      <div id="sensorChart" style="height: 300px;"></div>
    </div>
  <?php else: ?>
    <div class="upgrade-notice">
      <p>🚀 Upgrade to Premium to unlock IoT sensor monitoring!</p>
      <button onclick="upgradeToPremium()">Upgrade Now</button>
    </div>
  <?php endif; ?>
  
  <?php if ($recentImagery): ?>
    <div class="imagery-gallery">
      <h3>Recent Satellite/Drone Imagery</h3>
      <?php foreach ($recentImagery as $img): ?>
        <div class="imagery-item">
          <img src="<?= htmlspecialchars($img['thumbnail_url'] ?? $img['image_url']) ?>" 
               onclick="openImageryModal('<?= htmlspecialchars($img['image_url']) ?>')">
          <p><?= ucfirst($img['imagery_type']) ?> - <?= date('M j, Y', strtotime($img['capture_date'])) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Load sensor data via AJAX
async function loadSensorData() {
  const response = await fetch('/api/iot/readings.php?farm_id=<?= $_SESSION['application_id'] ?>');
  const data = await response.json();
  
  // Render chart (implementation depends on your sensor types)
  renderSensorChart(data);
}

// Only load if sensors exist
<?php if ($hasSensors): ?>
  loadSensorData();
<?php endif; ?>
</script>
<!-- In dashboard/farm-health.php -->
<?php
// Check feature flags and user plan
$iotEnabled = $pdo->query("SELECT value FROM settings WHERE key_name = 'iot_module_enabled'")->fetchColumn() === '1';
$satelliteEnabled = $pdo->query("SELECT value FROM settings WHERE key_name = 'satellite_module_enabled'")->fetchColumn() === '1';
$analyticsEnabled = $pdo->query("SELECT value FROM settings WHERE key_name = 'analytics_module_enabled'")->fetchColumn() === '1';
$isPremium = $userPlan === 'premium';
?>

<?php if ($iotEnabled && $isPremium): ?>
  <div class="premium-feature">
    <h3>IoT Sensor Dashboard</h3>
    <!-- Sensor charts -->
  </div>
<?php elseif ($iotEnabled): ?>
  <div class="upgrade-notice">
    <p>🔒 IoT monitoring available for Premium users</p>
    <button onclick="upgradeToPremium()">Upgrade Now</button>
  </div>
<?php endif; ?>

<?php if ($satelliteEnabled && $isPremium): ?>
  <div class="premium-feature">
    <h3>Satellite Imagery</h3>
    <!-- Imagery gallery -->
  </div>
<?php endif; ?>

<?php if ($analyticsEnabled && $isPremium): ?>
  <div class="premium-feature">
    <h3>Advanced Analytics</h3>
    <button onclick="generateYieldPrediction()">Predict Yield</button>
    <button onclick="checkDiseaseRisk()">Check Disease Risk</button>
  </div>
<?php endif; ?>