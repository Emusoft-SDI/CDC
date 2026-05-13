<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/dashboard-layout.php';
require_once __DIR__ . '/../lib/field-management.php';

session_start();
$pdo = db();
if (empty($_SESSION['user_id'])) {
    redirect_to('login.php');
}
fm_ensure_schema($pdo);

$stmt = $pdo->prepare("
    SELECT a.id application_id, a.location, a.farm_size, a.latitude, a.longitude
    FROM users u
    LEFT JOIN applications a ON a.id = u.application_id
    WHERE u.id = ?
");
$stmt->execute([(int) $_SESSION['user_id']]);
$farm = $stmt->fetch();

$imagery = [];
if ($farm && app_table_exists($pdo, 'farm_imagery')) {
    $imgStmt = $pdo->prepare("SELECT * FROM farm_imagery WHERE farm_id = ? ORDER BY capture_date DESC LIMIT 6");
    $imgStmt->execute([(int) $farm['application_id']]);
    $imagery = $imgStmt->fetchAll();
}
?>
<?php dashboard_page_start('Farm Health', ['active' => 'farm-health.php', 'description' => 'Request agronomy support, field review, and imagery assessment.', 'wide' => true]); ?>
<section class="card">
      <h1>Farm Health</h1>
      <p>Location: <?= e($farm['location'] ?? 'Not available') ?> · Size: <?= e((string) ($farm['farm_size'] ?? '')) ?> ha</p>
      <?php if ($farm): ?>
        <?php $weather = fm_weather_estimate($pdo, (int) $farm['application_id'], $farm['latitude'] !== null ? (float) $farm['latitude'] : null, $farm['longitude'] !== null ? (float) $farm['longitude'] : null); ?>
        <p><strong><?= e((string) $weather['temperature_c']) ?>°C</strong> / Rain <?= e((string) $weather['rainfall_mm']) ?>mm / Humidity <?= e((string) $weather['humidity_percent']) ?>%</p>
        <p class="muted"><?= e((string) $weather['summary']) ?></p>
      <?php endif; ?>
      <p>Request an agronomist review, farm visit, disease-risk check, or satellite/drone imagery assessment.</p>
      <a class="button" href="inbox.php?topic=farm-health">Request Farm Review</a>
    </section>
    <section class="card">
      <h2>Recent Imagery</h2>
      <?php foreach ($imagery as $img): ?>
        <p><a href="<?= e($img['image_url']) ?>" target="_blank"><?= e(ucfirst((string) $img['imagery_type'])) ?> - <?= e(date('M j, Y', strtotime((string) $img['capture_date']))) ?></a></p>
      <?php endforeach; ?>
      <?php if (!$imagery): ?><p>No imagery has been attached to this farm yet.</p><?php endif; ?>
    </section>
  <?php dashboard_page_end(); ?>
