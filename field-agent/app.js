// field-agent/app.js - offline-first assignment cache and visit sync.
const API_BASE = '../api';
const fieldDb = new window.FieldAgentDatabase();
let growers = [];

function faEscapeHtml(value) {
  return String(value || '').replace(/[&<>"']/g, char => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;'
  }[char]));
}

function updateNetworkBadge() {
  const badge = document.getElementById('offlineBadge');
  if (badge) badge.style.display = navigator.onLine ? 'none' : 'block';

  const state = document.getElementById('syncNetworkState');
  if (state) {
    state.textContent = navigator.onLine ? 'Online' : 'Offline';
    state.className = navigator.onLine ? 'sync-state online' : 'sync-state offline';
  }
}

function formToVisit(form) {
  const data = new FormData(form);
  const visit = {};
  data.forEach((value, key) => {
    if (key !== '_csrf') visit[key] = String(value).trim();
  });
  visit.local_id = `task_${visit.task_id || 'visit'}_${Date.now()}_${Math.random().toString(16).slice(2)}`;
  visit.client_visit_id = visit.local_id;
  visit.timestamp = new Date().toISOString();
  visit.sync_source = 'offline_sync';
  return visit;
}

async function cacheBootstrap() {
  const boot = window.FIELD_AGENT_BOOTSTRAP || {};
  await fieldDb.init();
  await fieldDb.cacheTasks(boot.tasks || []);
  await fieldDb.setMeta('field_agent', boot.user || {});
  await fieldDb.setMeta('sync_token', boot.csrf || '');
  await refreshSyncPanel();
}

async function loadGrowers() {
  try {
    if (navigator.onLine) {
      const res = await fetch(`${API_BASE}/growers.php`);
      const payload = await res.json();
      growers = payload.items || payload || [];
      await fieldDb.cacheGrowers(growers);
      localStorage.setItem('growers', JSON.stringify(growers));
    } else {
      growers = await fieldDb.getAll('growers');
      if (!growers.length) growers = JSON.parse(localStorage.getItem('growers') || '[]');
    }
  } catch (err) {
    growers = await fieldDb.getAll('growers');
    if (!growers.length) growers = JSON.parse(localStorage.getItem('growers') || '[]');
  }
  renderGrowers();
}

function renderGrowers() {
  const container = document.getElementById('growersList');
  if (!container) return;

  container.innerHTML = growers.map(g => `
    <button type="button" class="grower-row" data-grower-id="${Number(g.id)}">
      <strong>${faEscapeHtml(g.name)}</strong><br>
      ${faEscapeHtml(g.location)} | ${faEscapeHtml(g.farm_size)} ha
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
        const locationData = {
          latitude: position.coords.latitude,
          longitude: position.coords.longitude,
          accuracy: position.coords.accuracy,
          source: 'gps'
        };
        localStorage.setItem('lastLocation', JSON.stringify(locationData));
        resolve(locationData);
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
  } catch (err) {
    console.warn('Location unavailable; saving visit without coordinates.', err);
  }

  const visit = {
    grower_id: growerId,
    notes,
    timestamp: new Date().toISOString(),
    ...locationData
  };
  await fieldDb.queueVisit(visit);
  await registerBackgroundSync();
  await refreshSyncPanel();
  return visit;
}

async function saveTaskVisitOffline(form) {
  const visit = formToVisit(form);
  await fieldDb.queueVisit(visit);
  await registerBackgroundSync();
  form.reset();
  form.dataset.syncStatus = 'queued';
  await refreshSyncPanel();
  return visit;
}

async function registerBackgroundSync() {
  if (!('serviceWorker' in navigator) || !('SyncManager' in window)) return;
  try {
    const registration = await navigator.serviceWorker.ready;
    await registration.sync.register('field-agent-visits');
  } catch (err) {
    console.warn('Background sync registration skipped:', err);
  }
}

async function syncPendingVisits() {
  await fieldDb.init();
  if (!navigator.onLine) {
    await refreshSyncPanel();
    return;
  }

  const visits = await fieldDb.getQueuedVisits();
  if (!visits.length) {
    await refreshSyncPanel();
    return;
  }

  const boot = window.FIELD_AGENT_BOOTSTRAP || {};
  try {
    const res = await fetch(`${API_BASE}/sync-visits.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ _csrf: boot.csrf || '', visits })
    });
    const payload = await res.json();

    if (!res.ok || !payload.success) {
      throw new Error(payload.error || 'Sync endpoint rejected the request.');
    }

    const results = payload.results || [];
    const handled = new Set();
    for (const result of results) {
      if (!result.local_id) continue;
      handled.add(result.local_id);
      if (result.success) {
        await fieldDb.markVisitSynced(result.local_id, result.server_visit_id);
      } else {
        await fieldDb.markVisitFailed(result.local_id, result.error);
      }
    }

    for (const visit of visits) {
      if (!handled.has(visit.local_id)) {
        await fieldDb.markVisitFailed(visit.local_id, 'No result returned by server.');
      }
    }
  } catch (err) {
    for (const visit of visits) {
      await fieldDb.markVisitFailed(visit.local_id, err.message);
    }
    console.error('Visit sync failed:', err);
  }

  await refreshVisitsCache();
  await refreshSyncPanel();
}

async function refreshVisitsCache() {
  if (!navigator.onLine) return;
  try {
    const res = await fetch(`${API_BASE}/visits.php`);
    const payload = await res.json();
    localStorage.setItem('offlineVisits', JSON.stringify(payload.items || []));
  } catch (err) {
    console.warn('Failed to refresh visits cache:', err);
  }
}

async function refreshSyncPanel() {
  const visits = await fieldDb.getAll('visits');
  const tasks = await fieldDb.getAll('tasks');
  const queued = visits.filter(visit => ['pending', 'failed'].includes(visit.sync_status));
  const synced = visits.filter(visit => visit.sync_status === 'synced');
  const tasksCachedAt = await fieldDb.getMeta('tasks_cached_at');

  const queuedCount = document.getElementById('queuedVisitCount');
  const syncedCount = document.getElementById('syncedVisitCount');
  const cachedTaskCount = document.getElementById('cachedTaskCount');
  const cacheAge = document.getElementById('taskCacheAge');
  const list = document.getElementById('syncQueueList');

  if (queuedCount) queuedCount.textContent = String(queued.length);
  if (syncedCount) syncedCount.textContent = String(synced.length);
  if (cachedTaskCount) cachedTaskCount.textContent = String(tasks.length);
  if (cacheAge) cacheAge.textContent = tasksCachedAt ? new Date(tasksCachedAt).toLocaleString() : 'Not cached yet';

  if (list) {
    list.innerHTML = queued.length ? queued.map(visit => `
      <div class="queue-row ${visit.sync_status}">
        <strong>${visit.task_id ? `Task #${faEscapeHtml(visit.task_id)}` : `Grower #${faEscapeHtml(visit.grower_id)}`}</strong>
        <span>${faEscapeHtml(visit.sync_status)}${visit.attempts ? ` / ${Number(visit.attempts)} attempt(s)` : ''}</span>
        ${visit.last_error ? `<small>${faEscapeHtml(visit.last_error)}</small>` : ''}
      </div>
    `).join('') : '<p>No pending offline visits.</p>';
  }
  updateNetworkBadge();
}

function bindTaskForms() {
  document.querySelectorAll('form.task-form').forEach(form => {
    form.addEventListener('submit', async event => {
      if (navigator.onLine) return;
      event.preventDefault();
      await saveTaskVisitOffline(form);
      alert('Visit saved offline. It will sync when this device is online.');
    });
  });
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

async function refreshOfflineCache() {
  await cacheBootstrap();
  await loadGrowers();
  await refreshVisitsCache();
  await refreshSyncPanel();
}

document.addEventListener('DOMContentLoaded', async () => {
  await cacheBootstrap();
  bindVisitForm();
  bindTaskForms();
  loadGrowers();
  syncPendingVisits();

  document.getElementById('syncNowButton')?.addEventListener('click', syncPendingVisits);
  document.getElementById('refreshCacheButton')?.addEventListener('click', refreshOfflineCache);
});

window.addEventListener('online', () => {
  updateNetworkBadge();
  loadGrowers();
  syncPendingVisits();
});
window.addEventListener('offline', updateNetworkBadge);

window.fieldDb = fieldDb;
window.syncPendingVisits = syncPendingVisits;
window.refreshSyncPanel = refreshSyncPanel;
