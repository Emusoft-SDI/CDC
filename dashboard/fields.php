<?php
declare(strict_types=1);

require_once __DIR__ . '/_user_auth.php';
require_once __DIR__ . '/../lib/dashboard-layout.php';
require_once __DIR__ . '/../lib/field-management.php';

$pdo = db();
fm_ensure_schema($pdo);

$userId = (int) $_SESSION['user_id'];
$currentUser = current_user($pdo);
if (!$currentUser) {
    session_destroy();
    redirect_to('login.php');
}
dashboard_redirect_learner_only($pdo, $currentUser);
$stmt = $pdo->prepare("
    SELECT gf.*, s.state_name, l.lga_name,
           COALESCE(fv.status, 'pending') verification_status,
           fv.system_confidence_score, fv.system_notes, fv.rejection_reason
    FROM grower_farms gf
    LEFT JOIN nigeria_states s ON s.id = gf.state_id
    LEFT JOIN nigeria_lgas l ON l.id = gf.lga_id
    LEFT JOIN farm_verifications fv ON fv.farm_id = gf.id
    WHERE gf.user_id = ?
    ORDER BY gf.is_primary DESC, gf.created_at ASC
");
$stmt->execute([$userId]);
$farms = $stmt->fetchAll();

$mapItems = [];
foreach ($farms as $farm) {
    if ($farm['latitude'] !== null && $farm['longitude'] !== null) {
        $mapItems[] = [
            'name' => (string) $farm['farm_name'],
            'lat' => (float) $farm['latitude'],
            'lng' => (float) $farm['longitude'],
            'status' => (string) $farm['verification_status'],
        ];
    }
}
?>
<?php dashboard_page_start('Fields Management', [
    'active' => 'fields.php',
    'description' => 'Monitor your registered farms, verification status, GPS details, and weather signals.',
    'wide' => true,
    'css' => '#fieldsMap{height:380px;border:1px solid var(--line);border-radius:8px}.status-pill{display:inline-flex;border-radius:999px;padding:5px 9px;font-weight:850;font-size:.82rem;background:#eef2f6;color:#475467}.status-pill.verified{background:#eaf8f0;color:#0f6b3c}.status-pill.rejected{background:#fff3f3;color:#a32020}.status-pill.assigned,.status-pill.pending{background:#fff7df;color:#8a5a00}',
]); ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<section class="card">
  <div id="fieldsMap"></div>
</section>

<section class="grid">
  <?php foreach ($farms as $farm): ?>
    <?php
      $weather = fm_weather_estimate($pdo, (int) $farm['id'], $farm['latitude'] !== null ? (float) $farm['latitude'] : null, $farm['longitude'] !== null ? (float) $farm['longitude'] : null);
      $statusClass = preg_replace('/[^a-z0-9_-]/i', '', (string) $farm['verification_status']);
    ?>
    <article class="card">
      <h2><?= e($farm['farm_name']) ?></h2>
      <span class="status-pill <?= e($statusClass) ?>"><?= e(ucwords(str_replace('_', ' ', (string) $farm['verification_status']))) ?></span>
      <p><?= e((string) ($farm['street_address'] ?? '')) ?><br><span class="muted"><?= e((string) ($farm['lga_name'] ?? '')) ?> <?= e((string) ($farm['state_name'] ?? '')) ?></span></p>
      <p class="muted">GPS: <?= e((string) ($farm['latitude'] ?? 'missing')) ?>, <?= e((string) ($farm['longitude'] ?? 'missing')) ?></p>
      <?php if ($farm['system_notes']): ?><p class="muted"><?= e((string) $farm['system_notes']) ?></p><?php endif; ?>
      <?php if ($farm['rejection_reason']): ?><p class="error"><?= e((string) $farm['rejection_reason']) ?></p><?php endif; ?>
      <h3>Weather</h3>
      <p><strong><?= e((string) $weather['temperature_c']) ?>°C</strong> / Rain <?= e((string) $weather['rainfall_mm']) ?>mm / Humidity <?= e((string) $weather['humidity_percent']) ?>%</p>
      <p class="muted"><?= e((string) $weather['summary']) ?></p>
      <div class="actions">
        <a class="button secondary" href="profile.php#locations">Edit Farm</a>
        <a class="button secondary" href="inbox.php?topic=farm-health">Request Review</a>
      </div>
    </article>
  <?php endforeach; ?>
  <?php if (!$farms): ?><p class="empty">No farms are registered yet. Add your first farm from Profile > Farm Locations.</p><?php endif; ?>
</section>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
  const farmItems = <?= json_encode($mapItems, JSON_UNESCAPED_SLASHES) ?>;
  const map = L.map('fieldsMap').setView([9.0820, 8.6753], 6);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(map);
  farmItems.forEach((farm) => {
    const color = farm.status === 'verified' ? '#14733a' : farm.status === 'rejected' ? '#a32020' : '#9b6500';
    L.circleMarker([farm.lat, farm.lng], { radius: 9, color, fillColor: color, fillOpacity: .82 })
      .addTo(map)
      .bindPopup(`<strong>${farm.name}</strong><br>${farm.status}`);
  });
</script>
<?php dashboard_page_end(); ?>
