<!-- admin/agent-map.php -->
<?php
session_start();
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Live Agent Tracking - NATCODEV</title>
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <style>
    body { font-family: Arial; margin: 0; padding: 20px; }
    #map { height: 80vh; border-radius: 8px; }
    .legend { background: white; padding: 10px; border-radius: 5px; box-shadow: 0 1px 5px rgba(0,0,0,0.4); }
    .agent-marker { font-weight: bold; }
  </style>
</head>
<body>
  <h1>Live Field Agent Tracking</h1>
  <div class="legend">
    <strong>Agents Online:</strong> <span id="agentCount">0</span> | 
    Last Updated: <span id="lastUpdate">Never</span>
  </div>
  <div id="map"></div>

  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script>
    // Initialize map centered on Nigeria
    const map = L.map('map').setView([9.0820, 8.6753], 6);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap'
    }).addTo(map);
    
    const markers = {};
    let agentCount = 0;
    
    // Load agents every 30 seconds
    async function loadAgents() {
      try {
        const res = await fetch('/api/live-agents.php');
        const agents = await res.json();
        
        // Remove stale markers
        Object.keys(markers).forEach(id => {
          if (!agents.find(a => a.id == id)) {
            map.removeLayer(markers[id]);
            delete markers[id];
          }
        });
        
        // Update/add markers
        agents.forEach(agent => {
          const key = agent.id;
          const popupContent = `
            <div class="agent-marker">
              <strong>${agent.name}</strong><br>
              Battery: ${agent.battery_level || '?'}%<br>
              Last seen: ${new Date(agent.timestamp).toLocaleTimeString()}
            </div>
          `;
          
          if (markers[key]) {
            markers[key].setLatLng([agent.latitude, agent.longitude]);
            markers[key].setPopupContent(popupContent);
          } else {
            const color = agent.battery_level < 20 ? 'red' : 'green';
            const marker = L.circleMarker(
              [agent.latitude, agent.longitude],
              { 
                radius: 10,
                color: color,
                fillColor: color,
                fillOpacity: 0.8
              }
            ).addTo(map);
            marker.bindPopup(popupContent);
            markers[key] = marker;
          }
        });
        
        agentCount = agents.length;
        document.getElementById('agentCount').textContent = agentCount;
        document.getElementById('lastUpdate').textContent = new Date().toLocaleTimeString();
        
      } catch (err) {
        console.error('Failed to load agents:', err);
      }
    }
    
    // Initial load + refresh every 30s
    loadAgents();
    setInterval(loadAgents, 30000);
  </script>
</body>
</html>