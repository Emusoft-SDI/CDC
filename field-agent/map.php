<?php
declare(strict_types=1);
require_once __DIR__ . '/_field.php';
$pdo = fa_pdo(); $user = fa_require_user($pdo); $tasks = fa_task_rows($pdo, $user); $payload = fa_bootstrap_payload($user, $tasks);
fa_header('Farm Map', 'See assigned farms and plan efficient field movement.', $user, 'map');
?>
<section class="fa-grid"><article class="fa-card fa-panel span-12"><div class="fa-panel-head"><h2>Assigned Farm Locations</h2><span class="badge good"><?= count($tasks) ?> locations</span></div><div id="map" style="height:620px"></div></article></section>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script><script>const map=L.map('map').setView([9.0820,8.6753],6);L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{attribution:'&copy; OpenStreetMap'}).addTo(map);const tasks=<?= json_encode($payload['tasks'], JSON_UNESCAPED_SLASHES) ?>;tasks.forEach(t=>{if(t.latitude&&t.longitude){L.marker([t.latitude,t.longitude]).addTo(map).bindPopup(`<strong>${t.farm_name}</strong><br>${t.grower_name}<br>${t.lga_name} ${t.state_name}`)}});</script>
<?php fa_footer(); ?>
