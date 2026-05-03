// field-agent/app.js
let growers = [];
let pendingVisits = JSON.parse(localStorage.getItem('pendingVisits') || '[]');

// Load growers from local storage or API
async function loadGrowers() {
  const cached = localStorage.getItem('growers');
  if (cached && !navigator.onLine) {
    growers = JSON.parse(cached);
    renderGrowers();
    return;
  }
  
  try {
    const res = await fetch('/api/growers.php');
    if (res.ok) {
      growers = await res.json();
      localStorage.setItem('growers', JSON.stringify(growers));
      renderGrowers();
    }
  } catch (err) {
    if (cached) {
      growers = JSON.parse(cached);
      renderGrowers();
    }
  }
}

function renderGrowers() {
  const container = document.getElementById('growersList');
  container.innerHTML = growers.map(g => `
    <div onclick="showVisitForm(${g.id})">
      <strong>${g.name}</strong><br>
      ${g.location} | ${g.farm_size} ha
    </div>
  `).join('');
}

function showVisitForm(growerId) {
  document.getElementById('growerId').value = growerId;
  document.getElementById('visitForm').style.display = 'block';
}

// Save visit (online or offline)
document.getElementById('visitForm').addEventListener('submit', (e) => {
  e.preventDefault();
  const visit = {
    grower_id: document.getElementById('growerId').value,
    notes: document.getElementById('notes').value,
    timestamp: new Date().toISOString(),
    synced: false
  };
  
  pendingVisits.push(visit);
  localStorage.setItem('pendingVisits', JSON.stringify(pendingVisits));
  
  alert('Visit saved ' + (navigator.onLine ? 'online' : 'offline'));
  document.getElementById('visitForm').reset();
  document.getElementById('visitForm').style.display = 'none';
});

// Sync when back online
async function syncPendingVisits() {
  if (!navigator.onLine || pendingVisits.length === 0) return;
  
  const toSync = pendingVisits.filter(v => !v.synced);
  if (toSync.length === 0) return;
  
  try {
    const res = await fetch('/api/sync-visits.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify(toSync)
    });
    
    if (res.ok) {
      // Mark as synced
      toSync.forEach(v => v.synced = true);
      localStorage.setItem('pendingVisits', JSON.stringify(pendingVisits));
      
      // Clear fully synced visits
      pendingVisits = pendingVisits.filter(v => !v.synced);
      localStorage.setItem('pendingVisits', JSON.stringify(pendingVisits));
    }
  } catch (err) {
    console.error('Sync failed:', err);
  }
}

// Initialize
loadGrowers();
if (navigator.onLine) syncPendingVisits();// After successful sync
// Get current location (with timeout)
function getCurrentLocation() {
  return new Promise((resolve, reject) => {
    if (!navigator.geolocation) {
      reject({ error: 'Geolocation not supported' });
      return;
    }

    const timeoutId = setTimeout(() => {
      reject({ error: 'Location timeout' });
    }, 10000); // 10 seconds

    navigator.geolocation.getCurrentPosition(
      (position) => {
        clearTimeout(timeoutId);
        resolve({
          latitude: position.coords.latitude,
          longitude: position.coords.longitude,
          accuracy: position.coords.accuracy,
          source: 'gps'
        });
      },
      (error) => {
        clearTimeout(timeoutId);
        // Fallback to last known location or manual entry
        const last = JSON.parse(localStorage.getItem('lastLocation') || '{}');
        if (last.latitude) {
          resolve({ ...last, source: 'cached' });
        } else {
          reject({ error: 'Location access denied', code: error.code });
        }
      },
      {
        enableHighAccuracy: true,
        timeout: 10000,
        maximumAge: 300000 // 5 minutes
      }
    );
  });
}

// Enhanced visit saving with location
async function saveVisit(growerId, notes) {
  let locationData = {};
  
  try {
    locationData = await getCurrentLocation();
    // Cache location
    localStorage.setItem('lastLocation', JSON.stringify(locationData));
  } catch (err) {
    console.warn('Location error:', err);
    // Continue without location if offline/denied
  }

  const visit = {
    grower_id: growerId,
    notes: notes,
    timestamp: new Date().toISOString(),
    synced: false,
    ...locationData
  };

  pendingVisits.push(visit);
  localStorage.setItem('pendingVisits', JSON.stringify(pendingVisits));
  
  return visit;
}

// Update form submission
document.getElementById('visitForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const growerId = document.getElementById('growerId').value;
  const notes = document.getElementById('notes').value;
  
  await saveVisit(growerId, notes);
  
  alert('Visit saved with location!');
  document.getElementById('visitForm').reset();
  document.getElementById('visitForm').style.display = 'none';
});

async function syncPendingVisits() {
  // ... existing sync logic ...
  
  // Refresh cached visits
  if (navigator.onLine) {
    try {
      const res = await fetch('/api/visits.php');
      const visits = await res.json();
      localStorage.setItem('offlineVisits', JSON.stringify(visits));
    } catch (err) {
      console.warn('Failed to refresh visits cache');
    }
  }
}