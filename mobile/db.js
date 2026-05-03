// mobile/db.js - Local database
class MobileDatabase {
  constructor() {
    this.dbName = 'NATCODEV_Mobile';
    this.version = 1;
  }
  
  async init() {
    return new Promise((resolve, reject) => {
      const request = indexedDB.open(this.dbName, this.version);
      
      request.onupgradeneeded = (event) => {
        const db = event.target.result;
        
        if (!db.objectStoreNames.contains('marketplace')) {
          db.createObjectStore('marketplace', { keyPath: 'id' });
        }
        if (!db.objectStoreNames.contains('webinars')) {
          db.createObjectStore('webinars', { keyPath: 'id' });
        }
        if (!db.objectStoreNames.contains('pending_actions')) {
          const store = db.createObjectStore('pending_actions', { keyPath: 'id', autoIncrement: true });
          store.createIndex('timestamp', 'timestamp', { unique: false });
        }
        if (!db.objectStoreNames.contains('user_data')) {
          db.createObjectStore('user_data', { keyPath: 'key' });
        }
      };
      
      request.onsuccess = () => resolve(request.result);
      request.onerror = () => reject(request.error);
    });
  }
  
  async saveData(storeName, data) {
    const db = await this.init();
    const transaction = db.transaction([storeName], 'readwrite');
    const store = transaction.objectStore(storeName);
    
    if (Array.isArray(data)) {
      data.forEach(item => store.put(item));
    } else {
      store.put(data);
    }
  }
  
  async getData(storeName, key = null) {
    const db = await this.init();
    const transaction = db.transaction([storeName], 'readonly');
    const store = transaction.objectStore(storeName);
    
    if (key) {
      return new Promise((resolve) => {
        const request = store.get(key);
        request.onsuccess = () => resolve(request.result);
      });
    } else {
      return new Promise((resolve) => {
        const request = store.getAll();
        request.onsuccess = () => resolve(request.result);
      });
    }
  }
  
  async queueAction(url, method, data) {
    const db = await this.init();
    const transaction = db.transaction(['pending_actions'], 'readwrite');
    const store = transaction.objectStore('pending_actions');
    
    await store.add({
      url,
      method,
      data,
      timestamp: Date.now()
    });
    
    // Register background sync
    if ('serviceWorker' in navigator && 'sync' in registration) {
      registration.sync.register('pending-actions');
    }
  }
}