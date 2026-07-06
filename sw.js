// sw.js - NATCODEV offline cache and background sync
const CACHE_NAME = 'natcodev-cache-v4';
const BASE_PATH = new URL(self.registration.scope).pathname.replace(/\/$/, '');
const scoped = path => `${BASE_PATH}${path}`;
const AUTH_PATHS = [
  scoped('/dashboard/'),
  scoped('/admin/'),
  scoped('/super-admin/'),
  scoped('/field-agent/'),
  scoped('/provider/dashboard.php')
];
const CORE_ASSETS = [
  scoped('/'),
  scoped('/field-agent/db.js'),
  scoped('/field-agent/app.js'),
  scoped('/mobile/index.html'),
  scoped('/mobile/app.js'),
  scoped('/mobile/db.js'),
  scoped('/assets/css/style.css'),
  scoped('/assets/logo/natcodev-logo.png')
];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll(CORE_ASSETS))
      .then(() => self.skipWaiting())
      .catch(err => console.warn('Core caching skipped:', err))
  );
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys()
      .then(keys => Promise.all(keys.filter(key => key !== CACHE_NAME).map(key => caches.delete(key))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', event => {
  if (event.request.method !== 'GET') return;

  const url = new URL(event.request.url);
  const isAuthPath = AUTH_PATHS.some(path => url.pathname.startsWith(path));
  if (event.request.mode === 'navigate' || isAuthPath) {
    event.respondWith(fetch(event.request, { cache: 'no-store' }));
    return;
  }
  if (url.pathname.startsWith(scoped('/api/'))) return;

  event.respondWith(
    caches.match(event.request).then(cached => {
      if (cached) return cached;

      return fetch(event.request).then(response => {
        if (!response || response.status !== 200 || response.type === 'opaque') {
          return response;
        }

        const clone = response.clone();
        caches.open(CACHE_NAME).then(cache => cache.put(event.request, clone));
        return response;
      });
    })
  );
});

self.addEventListener('sync', event => {
  if (event.tag === 'pending-actions') {
    event.waitUntil(syncPendingActions());
  }
  if (event.tag === 'field-agent-visits') {
    event.waitUntil(syncFieldAgentVisits());
  }
});

async function syncPendingActions() {
  const actions = await getPendingActions();
  for (const action of actions) {
    try {
      const response = await fetch(action.url, {
        method: action.method,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(action.data)
      });

      if (response.ok) {
        await removePendingAction(action.id);
      }
    } catch (err) {
      console.warn('Sync failed for action:', action.id, err);
    }
  }
}

function openMobileDb() {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open('NATCODEV_Mobile', 1);
    request.onerror = () => reject(request.error);
    request.onsuccess = event => resolve(event.target.result);
  });
}

async function getPendingActions() {
  try {
    const db = await openMobileDb();
    if (!db.objectStoreNames.contains('pending_actions')) return [];

    return await new Promise(resolve => {
      const transaction = db.transaction(['pending_actions'], 'readonly');
      const store = transaction.objectStore('pending_actions');
      const request = store.getAll();
      request.onsuccess = () => resolve(request.result || []);
      request.onerror = () => resolve([]);
    });
  } catch (err) {
    return [];
  }
}

async function removePendingAction(id) {
  try {
    const db = await openMobileDb();
    if (!db.objectStoreNames.contains('pending_actions')) return;

    await new Promise(resolve => {
      const transaction = db.transaction(['pending_actions'], 'readwrite');
      const store = transaction.objectStore('pending_actions');
      store.delete(id);
      transaction.oncomplete = () => resolve();
      transaction.onerror = () => resolve();
    });
  } catch (err) {
    console.warn('Unable to remove pending action:', id, err);
  }
}

function openFieldAgentDb() {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open('NATCODEV_FieldAgent', 4);
    request.onerror = () => reject(request.error);
    request.onsuccess = event => resolve(event.target.result);
  });
}

async function fieldAgentGetAll(storeName) {
  try {
    const db = await openFieldAgentDb();
    return await new Promise(resolve => {
      const transaction = db.transaction([storeName], 'readonly');
      const request = transaction.objectStore(storeName).getAll();
      request.onsuccess = () => resolve(request.result || []);
      request.onerror = () => resolve([]);
    });
  } catch (err) {
    return [];
  }
}

async function fieldAgentPut(storeName, item) {
  try {
    const db = await openFieldAgentDb();
    await new Promise(resolve => {
      const transaction = db.transaction([storeName], 'readwrite');
      transaction.objectStore(storeName).put(item);
      transaction.oncomplete = () => resolve();
      transaction.onerror = () => resolve();
    });
  } catch (err) {
    console.warn('Unable to update field-agent store:', err);
  }
}

async function fieldAgentMeta(key) {
  const items = await fieldAgentGetAll('meta');
  const item = items.find(row => row.key === key);
  return item ? item.value : null;
}

async function syncFieldAgentVisits() {
  const visits = (await fieldAgentGetAll('visits')).filter(visit => ['pending', 'failed'].includes(visit.sync_status));
  if (!visits.length) return;

  try {
    const token = await fieldAgentMeta('sync_token');
    const response = await fetch(scoped('/api/sync-visits.php'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ _csrf: token || '', visits })
    });
    const payload = await response.json();
    if (!response.ok || !payload.success) throw new Error(payload.error || 'Field sync failed');

    const results = payload.results || [];
    for (const result of results) {
      const visit = visits.find(item => item.local_id === result.local_id);
      if (!visit) continue;
      if (result.success) {
        await fieldAgentPut('visits', {
          ...visit,
          sync_status: 'synced',
          server_visit_id: result.server_visit_id || null,
          synced_at: new Date().toISOString(),
          updated_at: new Date().toISOString()
        });
      } else {
        await fieldAgentPut('visits', {
          ...visit,
          sync_status: 'failed',
          attempts: Number(visit.attempts || 0) + 1,
          last_error: result.error || 'Field sync failed',
          updated_at: new Date().toISOString()
        });
      }
    }
  } catch (err) {
    for (const visit of visits) {
      await fieldAgentPut('visits', {
        ...visit,
        sync_status: 'failed',
        attempts: Number(visit.attempts || 0) + 1,
        last_error: err.message || 'Field sync failed',
        updated_at: new Date().toISOString()
      });
    }
  }
}
