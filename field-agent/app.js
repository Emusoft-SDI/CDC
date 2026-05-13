// field-agent/app.js
let growers = [];
let pendingVisits = JSON.parse(localStorage.getItem('pendingVisits') || '[]');
const API_BASE = '../api';

function escapeHtml(value) {
  return String(value || '').replace(/[&<>"']/g, char => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;'
  }[char]));
}

async function loadGrowers() {
  const cached = localStorage.getItem('growers');
  if (cached && !navigator.onLine) {
    growers = JSON.parse(cached);
    renderGrowers();
    return;
  }

  try {
    const res = await fetch(`${API_BASE}/growers.php`);
    const payload = await res.json();
    growers = payload.items || payload || [];
    localStorage.setItem('growers', JSON.stringify(growers));
    renderGrowers();
  } catch (err) {
    if (cached) {
      growers = JSON.parse(cached);
      renderGrowers();
    }
  }
}

function renderGrowers() {
  const container = document.getElementById('growersList');
  if (!container) return;

  container.innerHTML = growers.map(g => `
    <button type="button" class="grower-row" data-grower-id="${Number(g.id)}">
      <strong>${escapeHtml(g.name)}</strong><br>
      ${escapeHtml(g.location)} | ${escapeHtml(g.farm_size)} ha
    </button>
  `).join('');

  container.querySelectorAll('[data-grower-id]').forEach(row => {
    row.addEventListener('click', () => showVisitForm(row.dataset.growerId));
  });
}

function showVisitForm(growerId) {
  const growerInput = document.getElementById('growerId');
  const visitForm = document.getElementById('visitForm');
  if (!growerInput || !visitForm) return;

  growerInput.value = growerId;
  visitForm.style.display = 'block';
}

function getCurrentLocation() {
  return new Promise((resolve, reject) => {
    if (!navigator.geolocation) {
      reject({ error: 'Geolocation not supported' });
      return;
    }

    const timeoutId = setTimeout(() => reject({ error: 'Location timeout' }), 10000);
    navigator.geolocation.getCurrentPosition(
      position => {
        clearTimeout(timeoutId);
        resolve({
          latitude: position.coords.latitude,
          longitude: position.coords.longitude,
          accuracy: position.coords.accuracy,
          source: 'gps'
        });
      },
      error => {
        clearTimeout(timeoutId);
        const last = JSON.parse(localStorage.getItem('lastLocation') || '{}');
        if (last.latitude && last.longitude) {
          resolve({ ...last, source: 'cached' });
        } else {
          reject({ error: 'Location access denied', code: error.code });
        }
      },
      { enableHighAccuracy: true, timeout: 10000, maximumAge: 300000 }
    );
  });
}

async function saveVisit(growerId, notes) {
  let locationData = {};

  try {
    locationData = await getCurrentLocation();
    localStorage.setItem('lastLocation', JSON.stringify(locationData));
  } catch (err) {
    console.warn('Location unavailable; saving visit without coordinates.', err);
  }

  const visit = {
    grower_id: growerId,
    notes,
    timestamp: new Date().toISOString(),
    synced: false,
    ...locationData
  };

  pendingVisits.push(visit);
  localStorage.setItem('pendingVisits', JSON.stringify(pendingVisits));
  return visit;
}

async function syncPendingVisits() {
  if (!navigator.onLine) return;

  const toSync = pendingVisits.filter(v => !v.synced);
  if (toSync.length > 0) {
    try {
      const res = await fetch(`${API_BASE}/sync-visits.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(toSync)
      });
      const payload = await res.json();

      if (res.ok && payload.success) {
        pendingVisits = pendingVisits.filter(v => !toSync.includes(v));
        localStorage.setItem('pendingVisits', JSON.stringify(pendingVisits));
      }
    } catch (err) {
      console.error('Visit sync failed:', err);
    }
  }

  try {
    const res = await fetch(`${API_BASE}/visits.php`);
    const payload = await res.json();
    localStorage.setItem('offlineVisits', JSON.stringify(payload.items || []));
  } catch (err) {
    console.warn('Failed to refresh visits cache:', err);
  }
}

function bindVisitForm() {
  const visitForm = document.getElementById('visitForm');
  if (!visitForm) return;

  visitForm.addEventListener('submit', async event => {
    event.preventDefault();
    const growerId = document.getElementById('growerId')?.value;
    const notes = document.getElementById('notes')?.value || '';
    if (!growerId) return;

    await saveVisit(growerId, notes);
    if (navigator.onLine) await syncPendingVisits();

    alert('Visit saved' + (navigator.onLine ? ' and synced.' : ' for offline sync.'));
    visitForm.reset();
    visitForm.style.display = 'none';
  });
}

document.addEventListener('DOMContentLoaded', () => {
  bindVisitForm();
  loadGrowers();
  syncPendingVisits();
});

window.addEventListener('online', () => {
  loadGrowers();
  syncPendingVisits();
});
