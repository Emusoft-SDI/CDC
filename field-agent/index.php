<!-- field-agent/index.php -->
<?php
session_start();
// Role check: must be field_agent or admin
if (!isset($_SESSION['user_id'])) {
    header('Location: /dashboard/login.php');
    exit;
}
// ... role verification ...
?>
<!DOCTYPE html>
<html manifest="/manifest.appcache">
<head>
  <title>NATCODEV Field Agent</title>
  <link rel="manifest" href="/manifest.json">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    /* ... your styles ... */
    .offline-badge { background: #d32f2f; color: white; padding: 5px; position: fixed; top: 0; width: 100%; text-align: center; z-index: 1000; }
  </style>

<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
  #map { height: 400px; border-radius: 8px; margin: 20px 0; }
  .visit-popup { max-width: 300px; }
</style>
</head>

<!-- Hero banner -->
<div style="background: url('/assets/hero/field-agent-hero.jpg') center/cover; 
            height: 150px; border-radius: 8px; margin: 20px 0; display: flex; 
            align-items: center; justify-content: center; color: white; text-shadow: 0 2px 4px rgba(0,0,0,0.5);">
  <h2 style="margin: 0; font-size: 24px;">Field Agent Operations</h2>
</div>

<!-- Logo in header -->
<div style="text-align: center; margin-bottom: 20px;">
  <img src="/assets/logo/natcodev-logo-white.png" alt="NATCODEV" style="height: 35px;">
</div>
<!-- field-agent/index.php -->
<?php
session_start();
// Role check: must be field_agent or admin
if (!isset($_SESSION['user_id'])) {
    header('Location: /dashboard/login.php');
    exit;
}
// ... role verification ...
?>

<body>
  <div id="offlineBadge" class="offline-badge" style="display:none;">Offline Mode</div>
  
  <h1>Field Agent Dashboard</h1>
  
  <!-- Growers List (will be populated by JS) -->
  <div id="growersList"></div>
  
  <!-- New Visit Form -->
  <form id="visitForm" style="display:none;">
    <input type="hidden" id="growerId">
    <textarea id="notes" placeholder="Visit notes..."></textarea>
    <button type="submit">Save Visit</button>
  </form>

  <script src="app.js"></script>
  <script>
    // Register service worker
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.register('/sw.js');
    }
    
    // Check online status
    window.addEventListener('online', () => {
      document.getElementById('offlineBadge').style.display = 'none';
      syncPendingVisits();
    });
    
    window.addEventListener('offline', () => {
      document.getElementById('offlineBadge').style.display = 'block';
    });
  </script>

<!-- Offline Location Selection -->
<div class="form-group">
  <label for="offlineState">State *</label>
  <select id="offlineState" name="state" required onchange="loadOfflineLGAs()">
    <option value="">Select State</option>
  </select>
</div>

<div class="form-group">
  <label for="offlineLGA">Local Government Area (LGA) *</label>
  <select id="offlineLGA" name="lga" required onchange="loadOfflineStreets()">
    <option value="">Select LGA</option>
  </select>
</div>

<div class="form-group" id="streetSection" style="display: none;">
  <label for="offlineStreet">Street/Area (Optional)</label>
  <select id="offlineStreet" name="street">
    <option value="">Select Street/Area</option>
  </select>
  <input type="text" id="customStreet" name="custom_street" placeholder="Or enter custom address" style="margin-top: 5px;">
</div>
<div class="card">
  <h2>Certificate Verification</h2>
  <input type="text" id="certRef" placeholder="Enter certificate reference (e.g., NAT-240515-ABC123)" style="width:100%; padding:10px; margin:10px 0;">
  <button onclick="verifyCertificate()">🔍 Verify Certificate</button>
  <div id="verificationResult" style="margin-top:15px;"></div>
</div>
<div class="card">
  <h2>Offline Resources</h2>
  <div id="offlineResources">
    <p>Loading resources...</p>
  </div>
  <button onclick="downloadAllResources()">📥 Download All for Offline Use</button>
</div>

<script>
// Load resources
async function loadResources() {
  try {
    const res = await fetch('/api/resources.php');
    const resources = await res.json();
    
    const container = document.getElementById('offlineResources');
    container.innerHTML = resources.map(r => `
      <div style="margin:10px 0; padding:10px; border-left:3px solid #2d5016;">
        <strong>${r.title}</strong> (${r.category})<br>
        <a href="/resources/${encodeURIComponent(r.file_path)}" download>📥 Download</a>
        <span id="status-${r.id}"></span>
      </div>
    `).join('');
    
    // Check which are cached
    resources.forEach(r => {
      caches.match(`/resources/${r.file_path}`).then(response => {
        if (response) {
          document.getElementById(`status-${r.id}`).innerHTML = ' ✅ Cached';
        }
      });
    });
  } catch (err) {
    document.getElementById('offlineResources').innerHTML = '<p>Offline mode: showing cached resources</p>';
  }
}

// Download all resources
async function downloadAllResources() {
  const res = await fetch('/api/resources.php');
  const resources = await res.json();
  
  for (const r of resources) {
    try {
      await fetch(`/resources/${r.file_path}`);
      document.getElementById(`status-${r.id}`).innerHTML = ' ✅ Downloaded';
    } catch (err) {
      console.warn('Failed to cache:', r.file_path);
    }
  }
  alert('All resources downloaded for offline use!');
}

// Initialize
loadResources();
</script>
<script>
async function verifyCertificate() {
  const ref = document.getElementById('certRef').value.trim();
  if (!ref) return;
  
  const resultDiv = document.getElementById('verificationResult');
  resultDiv.innerHTML = 'Verifying...';
  
  // Try online first
  if (navigator.onLine) {
    try {
      const res = await fetch(`/verify-certificate?ref=${encodeURIComponent(ref)}`);
      const html = await res.text();
      
      if (html.includes('VALID CERTIFICATE')) {
        resultDiv.innerHTML = '<div style="color:green;">✅ VALID CERTIFICATE (Online)</div>';
      } else {
        resultDiv.innerHTML = '<div style="color:red;">❌ Invalid Certificate</div>';
      }
      return;
    } catch (err) {
      console.warn('Online verification failed, trying offline');
    }
  }
  
  // Fallback to offline
  const offlineResult = await verifyCertificateOffline(ref);
  if (offlineResult.valid) {
    resultDiv.innerHTML = `
      <div style="color:green;">✅ VALID CERTIFICATE (Offline)</div>
      <div>Name: ${offlineResult.name}</div>
      <div>Issued: ${new Date(offlineResult.issued_at).toLocaleDateString()}</div>
    `;
  } else {
    resultDiv.innerHTML = `<div style="color:red;">❌ ${offlineResult.error || 'Invalid Certificate'}</div>`;
  }
}

// Auto-load certificates when online
window.addEventListener('online', loadCertificatesForOffline);
</script>
<script>
const locationDB = new LocationDatabase();

// Load location data when page loads
async function initLocationData() {
  try {
    // Try to sync with server first
    if (navigator.onLine) {
      await syncLocationData();
    }
    
    // Load from local DB
    const states = await locationDB.getStates();
    const stateSelect = document.getElementById('offlineState');
    states.forEach(state => {
      const option = document.createElement('option');
      option.value = state.id;
      option.textContent = state.state_name;
      stateSelect.appendChild(option);
    });
    
  } catch (error) {
    console.warn('Failed to load location ', error);
  }
}

async function syncLocationData() {
  try {
    // Fetch states
    const statesRes = await fetch('/api/offline-locations.php?type=states');
    const states = await statesRes.json();
    await locationDB.saveStates(states);
    
    // Fetch LGAs
    const lgasRes = await fetch('/api/offline-locations.php?type=lgas');
    const lgas = await lgasRes.json();
    await locationDB.saveLGAs(lgas);
    
    // Fetch streets for major cities only (to save space)
    const streetsRes = await fetch('/api/offline-locations.php?type=streets');
    const streets = await streetsRes.json();
    await locationDB.saveStreets(streets);
    
  } catch (error) {
    console.warn('Failed to sync location ', error);
  }
}

async function loadOfflineLGAs() {
  const stateId = document.getElementById('offlineState').value;
  const lgaSelect = document.getElementById('offlineLGA');
  lgaSelect.innerHTML = '<option value="">Select LGA</option>';
  
  if (!stateId) return;
  
  try {
    const lgas = await locationDB.getLGAsByState(parseInt(stateId));
    lgas.forEach(lga => {
      const option = document.createElement('option');
      option.value = lga.id;
      option.textContent = lga.lga_name;
      lgaSelect.appendChild(option);
    });
  } catch (error) {
    console.warn('Failed to load LGAs:', error);
  }
}

async function loadOfflineStreets() {
  const lgaId = document.getElementById('offlineLGA').value;
  const streetSection = document.getElementById('streetSection');
  const streetSelect = document.getElementById('offlineStreet');
  
  if (!lgaId) {
    streetSection.style.display = 'none';
    return;
  }
  
  try {
    const streets = await locationDB.getStreetsByLGA(parseInt(lgaId));
    if (streets.length > 0) {
      streetSection.style.display = 'block';
      streetSelect.innerHTML = '<option value="">Select Street/Area</option>';
      streets.forEach(street => {
        const option = document.createElement('option');
        option.value = street.id;
        option.textContent = street.area_name ? `${street.street_name} (${street.area_name})` : street.street_name;
        streetSelect.appendChild(option);
      });
    } else {
      streetSection.style.display = 'none';
    }
  } catch (error) {
    console.warn('Failed to load streets:', error);
    streetSection.style.display = 'none';
  }
}

// Initialize when page loads
document.addEventListener('DOMContentLoaded', initLocationData);
</script>
<div id="trackingConsent" style="position:fixed; bottom:20px; right:20px; background:white; padding:15px; border-radius:8px; box-shadow:0 2px 10px rgba(0,0,0,0.2);">
  <p>📍 Share your location with NATCODEV?</p>
  <button onclick="acceptTracking()">Allow</button>
  <button onclick="denyTracking()" style="margin-left:10px;">Deny</button>
</div>

<script>
function acceptTracking() {
  localStorage.setItem('locationConsent', 'true');
  document.getElementById('trackingConsent').style.display = 'none';
  startAgentTracking();
}

function denyTracking() {
  localStorage.setItem('locationConsent', 'false');
  document.getElementById('trackingConsent').style.display = 'none';
}

// Check consent on load
if (localStorage.getItem('locationConsent') === 'true') {
  startAgentTracking();
} else if (localStorage.getItem('locationConsent') === null) {
  // Show consent banner
} else {
  // Tracking denied
}
</script>
<script>
// Real-time agent tracking
let trackingInterval = null;
let lastLocation = null;

async function startAgentTracking() {
  if (trackingInterval) return; // Already running
  
  // Send location every 2 minutes
  trackingInterval = setInterval(async () => {
    if (!navigator.onLine) return;
    
    try {
      const position = await new Promise((resolve, reject) => {
        navigator.geolocation.getCurrentPosition(resolve, reject, {
          enableHighAccuracy: false, // Save battery
          timeout: 10000,
          maximumAge: 60000 // Use cached if <1 min old
        });
      });
      
      // Get battery level (if supported)
      let batteryLevel = null;
      if ('getBattery' in navigator) {
        const battery = await navigator.getBattery();
        batteryLevel = Math.round(battery.level * 100);
      }
      
      // Only send if location changed significantly (>50m)
      if (lastLocation) {
        const distance = calculateDistance(
          lastLocation.latitude, 
          lastLocation.longitude,
          position.coords.latitude,
          position.coords.longitude
        );
        if (distance < 0.05) return; // <50 meters
      }
      
      lastLocation = {
        latitude: position.coords.latitude,
        longitude: position.coords.longitude
      };
      
      // Send to server
      await fetch('/api/track-location.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          latitude: position.coords.latitude,
          longitude: position.coords.longitude,
          accuracy: position.coords.accuracy,
          battery_level: batteryLevel
        })
      });
      
    } catch (err) {
      console.warn('Location tracking error:', err);
    }
  }, 120000); // Every 2 minutes
}

// Calculate distance between two points (km)
function calculateDistance(lat1, lon1, lat2, lon2) {
  const R = 6371; // Earth radius in km
  const dLat = (lat2 - lat1) * Math.PI / 180;
  const dLon = (lon2 - lon1) * Math.PI / 180;
  const a = 
    Math.sin(dLat/2) * Math.sin(dLat/2) +
    Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * 
    Math.sin(dLon/2) * Math.sin(dLon/2);
  const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
  return R * c;
}

// Start tracking when dashboard loads
if (navigator.geolocation) {
  startAgentTracking();
}
</script>
<script>
// Create local certificate database
async function initOfflineVerification() {
  if (!('indexedDB' in window)) return;
  
  const request = indexedDB.open('NATCODEVCerts', 1);
  
  request.onupgradeneeded = (event) => {
    const db = event.target.result;
    if (!db.objectStoreNames.contains('certificates')) {
      const store = db.createObjectStore('certificates', { keyPath: 'app_ref' });
      store.createIndex('name', 'name', { unique: false });
    }
  };
  
  request.onsuccess = (event) => {
    window.certDB = event.target.result;
    loadCertificatesForOffline();
  };
}

// Load certificates from server for offline use
async function loadCertificatesForOffline() {
  if (!window.certDB || !navigator.onLine) return;
  
  try {
    const res = await fetch('/api/certificates.php');
    const certs = await res.json();
    
    const transaction = window.certDB.transaction(['certificates'], 'readwrite');
    const store = transaction.objectStore('certificates');
    
    certs.forEach(cert => {
      store.put({
        app_ref: cert.app_ref,
        name: cert.name,
        issued_at: cert.issued_at,
        verified: true
      });
    });
  } catch (err) {
    console.warn('Failed to load certificates for offline use');
  }
}

// Verify certificate offline
function verifyCertificateOffline(ref) {
  return new Promise((resolve) => {
    if (!window.certDB) {
      resolve({ error: 'Offline verification not supported' });
      return;
    }
    
    const transaction = window.certDB.transaction(['certificates'], 'readonly');
    const store = transaction.objectStore('certificates');
    const request = store.get(ref);
    
    request.onsuccess = () => {
      if (request.result) {
        resolve({
          valid: true,
          name: request.result.name,
          issued_at: request.result.issued_at
        });
      } else {
        resolve({ valid: false, error: 'Certificate not found in offline database' });
      }
    };
    
    request.onerror = () => {
      resolve({ error: 'Verification failed' });
    };
  });
}

// Initialize
initOfflineVerification();
</script>
<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<div id="map"></div>
<script>
// Initialize map
const map = L.map('map').setView([9.0820, 8.6753], 6); // Center on Nigeria

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
}).addTo(map);

// Load visits from local storage or API
async function loadVisits() {
  let visits = [];
  
  // Try online first
  if (navigator.onLine) {
    try {
      const res = await fetch('/api/visits.php');
      if (res.ok) {
        visits = await res.json();
        localStorage.setItem('offlineVisits', JSON.stringify(visits));
      }
    } catch (err) {
      console.warn('Online fetch failed, using offline data');
    }
  }
  
  // Fallback to offline
  if (visits.length === 0) {
    const cached = localStorage.getItem('offlineVisits');
    visits = cached ? JSON.parse(cached) : [];
  }
  
  // Add markers
  visits.forEach(visit => {
    if (visit.latitude && visit.longitude) {
      const marker = L.marker([visit.latitude, visit.longitude]).addTo(map);
      marker.bindPopup(`
        <div class="visit-popup">
          <strong>${visit.grower_name}</strong><br>
          ${visit.location}<br>
          ${new Date(visit.visited_at).toLocaleDateString()}<br>
          <em>${visit.notes.substring(0, 50)}...</em>
        </div>
      `);
    }
  });
}

loadVisits();
</script>

<script>
// Request location permission on first load
if ('geolocation' in navigator) {
  navigator.permissions.query({name:'geolocation'}).then(function(result) {
    if (result.state === 'prompt') {
      // Show user-friendly prompt
      if (confirm('Allow NATCODEV to access your location for accurate farm visits?')) {
        getCurrentLocation().catch(() => {});
      }
    }
  });
}
</script>


</body>
</html>