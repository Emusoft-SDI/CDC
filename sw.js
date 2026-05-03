// sw.js - Enhanced for offline sync
const CACHE_NAME = 'natcodev-mobile-v2';
const OFFLINE_URLS = [
  '/api/mobile/marketplace',
  '/api/mobile/webinars',
  '/api/mobile/healthcare-form'
];
// sw.js - Cache critical assets
const CACHE_NAME = 'natcodev-v1';
const urlsToCache = [
  '/field-agent/',
  '/field-agent/style.css',
  '/field-agent/app.js',
  '/logo.png'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => cache.addAll(urlsToCache))
  );
});

self.addEventListener('fetch', (event) => {
  // Only cache GET requests
  if (event.request.method !== 'GET') return;
  
  // Skip API calls
  if (event.request.url.includes('/api/')) {
    return fetch(event.request);
  }
  
  event.respondWith(
    caches.match(event.request)
      .then((response) => response || fetch(event.request))
  );
});
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      // Cache static assets
      return cache.addAll([
        '/mobile/',
        '/mobile/style.css',
        '/mobile/app.js',
        '/logo.png'
      ]);
    })
  );
});

// Background sync for pending actions
self.addEventListener('sync', (event) => {
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
      console.warn('Sync failed for action:', action.id);
    }
  }
}

// Store pending actions in IndexedDB
async function getPendingActions() {
  return new Promise((resolve) => {
    const request = indexedDB.open('NATCODEV_Mobile', 1);
    request.onsuccess = (event) => {
      const db = event.target.result;
      const transaction = db.transaction(['pending_actions'], 'readonly');
      const store = transaction.objectStore('pending_actions');
      const getAll = store.getAll();
      
      getAll.onsuccess = () => resolve(getAll.result);
    };
  });
}

async function removePendingAction(id) {
  return new Promise((resolve) => {
    const request = indexedDB.open('NATCODEV_Mobile', 1);
    request.onsuccess = (event) => {
      const db = event.target.result;
      const transaction = db.transaction(['pending_actions'], 'readwrite');
      const store = transaction.objectStore('pending_actions');
      store.delete(id);
      resolve();
    };
  });
}

// Add to urlsToCache array
const urlsToCache = [
  // ... existing assets ...
  '/resources/farming_guide.pdf',
  '/resources/pest_control.pdf',
  '/resources/market_prices.xlsx'
];

// Cache all resources
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => {
        // Cache static assets
        cache.addAll(urlsToCache);
        
        // Also cache all resources from API
        return fetch('/api/resources.php')
          .then(response => response.json())
          .then(resources => {
            const resourceUrls = resources.map(r => `/resources/${r.file_path}`);
            return cache.addAll(resourceUrls);
          })
          .catch(() => console.log('Resource caching skipped'));
      })
  );
});