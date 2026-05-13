<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/admin-layout.php';

session_start();
$pdo = db();
admin_ensure_schema($pdo);
admin_require($pdo);
?>
<?php admin_page_start('Live Agent Tracking', [
    'active' => 'agent-map.php',
    'description' => 'Monitor active field agents, battery levels, and last reported locations.',
    'wide' => true,
    'css' => '#map{height:72vh;border-radius:8px;border:1px solid var(--line)}.legend{background:#fff;padding:12px 14px;border-radius:8px;border:1px solid var(--line);box-shadow:var(--shadow);margin-bottom:12px;}',
]); ?>
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <div class="legend">
    <strong>Agents Online:</strong> <span id="agentCount">0</span> |
    Last Updated: <span id="lastUpdate">Never</span>
  </div>
  <div id="map"></div>

  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script>
    const map = L.map('map').setView([9.0820, 8.6753], 6);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(map);
    const markers = {};

    function escapeHtml(value) {
      return String(value || '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
    }

    async function loadAgents() {
      try {
        const res = await fetch('../api/live-agents.php');
        const payload = await res.json();
        const agents = payload.items || payload;

        Object.keys(markers).forEach(id => {
          if (!agents.find(a => String(a.id) === id)) {
            map.removeLayer(markers[id]);
            delete markers[id];
          }
        });

        agents.forEach(agent => {
          const key = String(agent.id);
          const popup = `<strong>${escapeHtml(agent.name)}</strong><br>Battery: ${escapeHtml(agent.battery_level || '?')}%<br>Last seen: ${escapeHtml(agent.timestamp)}`;
          if (markers[key]) {
            markers[key].setLatLng([agent.latitude, agent.longitude]);
            markers[key].setPopupContent(popup);
          } else {
            const lowBattery = Number(agent.battery_level || 100) < 20;
            markers[key] = L.circleMarker([agent.latitude, agent.longitude], {
              radius: 10,
              color: lowBattery ? '#a32020' : '#14733a',
              fillColor: lowBattery ? '#a32020' : '#14733a',
              fillOpacity: 0.8
            }).addTo(map).bindPopup(popup);
          }
        });

        document.getElementById('agentCount').textContent = agents.length;
        document.getElementById('lastUpdate').textContent = new Date().toLocaleTimeString();
      } catch (err) {
        console.error('Failed to load agents:', err);
      }
    }

    loadAgents();
    setInterval(loadAgents, 30000);
  </script>
<?php admin_page_end(); ?>
