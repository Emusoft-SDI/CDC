// sw.js - NATCODEV offline cache and background sync
const CACHE_NAME = 'natcodev-cache-v3';
const BASE_PATH = new URL(self.registration.scope).pathname.replace(/\/$/, '');
const scoped = path => `${BASE_PATH}${path}`;
const CORE_ASSETS = [
  scoped('/'),
  scoped('/field-agent/'),
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
