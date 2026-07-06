// field-agent/db.js - IndexedDB cache for offline assignments, visits, and reference data.
class FieldAgentDatabase {
  constructor() {
    this.dbName = 'NATCODEV_FieldAgent';
    this.version = 4;
    this.db = null;
  }

  async init() {
    if (this.db) return this.db;

    this.db = await new Promise((resolve, reject) => {
      const request = indexedDB.open(this.dbName, this.version);

      request.onupgradeneeded = event => {
        const db = event.target.result;
        const tx = event.target.transaction;
        this.ensureStore(db, tx, 'states', { keyPath: 'id' }, [['name', 'state_name']]);
        this.ensureStore(db, tx, 'lgas', { keyPath: 'id' }, [['state_id', 'state_id'], ['name', 'lga_name']]);
        this.ensureStore(db, tx, 'streets', { keyPath: 'id' }, [['lga_id', 'lga_id'], ['name', 'street_name']]);
        this.ensureStore(db, tx, 'tasks', { keyPath: 'id' }, [['status', 'status'], ['priority', 'priority']]);
        this.ensureStore(db, tx, 'growers', { keyPath: 'id' }, [['name', 'name']]);
        this.ensureStore(db, tx, 'visits', { keyPath: 'local_id' }, [['sync_status', 'sync_status'], ['task_id', 'task_id']]);
        this.ensureStore(db, tx, 'resources', { keyPath: 'id' }, [['category', 'category']]);
        this.ensureStore(db, tx, 'certificates', { keyPath: 'certificate_ref' }, [['name', 'name']]);
        this.ensureStore(db, tx, 'meta', { keyPath: 'key' });
      };

      request.onsuccess = () => resolve(request.result);
      request.onerror = () => reject(request.error);
    });

    return this.db;
  }

  ensureStore(db, tx, name, options, indexes = []) {
    let store;
    if (db.objectStoreNames.contains(name)) {
      store = tx.objectStore(name);
    } else {
      store = db.createObjectStore(name, options);
    }

    if (!store) return;
    indexes.forEach(([indexName, keyPath]) => {
      if (!store.indexNames.contains(indexName)) {
        store.createIndex(indexName, keyPath, { unique: false });
      }
    });
  }

  async transaction(storeName, mode, callback) {
    const db = await this.init();
    return new Promise((resolve, reject) => {
      const tx = db.transaction([storeName], mode);
      const store = tx.objectStore(storeName);
      const result = callback(store);
      tx.oncomplete = () => resolve(result);
      tx.onerror = () => reject(tx.error);
      tx.onabort = () => reject(tx.error);
    });
  }

  async putMany(storeName, items) {
    if (!Array.isArray(items)) return;
    await this.transaction(storeName, 'readwrite', store => {
      items.forEach(item => store.put(item));
    });
  }

  async getAll(storeName) {
    const db = await this.init();
    return new Promise((resolve, reject) => {
      const tx = db.transaction([storeName], 'readonly');
      const request = tx.objectStore(storeName).getAll();
      request.onsuccess = () => resolve(request.result || []);
      request.onerror = () => reject(request.error);
    });
  }

  async getByIndex(storeName, indexName, value) {
    const db = await this.init();
    return new Promise((resolve, reject) => {
      const tx = db.transaction([storeName], 'readonly');
      const request = tx.objectStore(storeName).index(indexName).getAll(value);
      request.onsuccess = () => resolve(request.result || []);
      request.onerror = () => reject(request.error);
    });
  }

  async put(storeName, item) {
    await this.transaction(storeName, 'readwrite', store => store.put(item));
  }

  async delete(storeName, key) {
    await this.transaction(storeName, 'readwrite', store => store.delete(key));
  }

  async setMeta(key, value) {
    await this.put('meta', { key, value, updated_at: new Date().toISOString() });
  }

  async getMeta(key) {
    const db = await this.init();
    return new Promise(resolve => {
      const tx = db.transaction(['meta'], 'readonly');
      const request = tx.objectStore('meta').get(key);
      request.onsuccess = () => resolve(request.result?.value ?? null);
      request.onerror = () => resolve(null);
    });
  }

  async saveStates(states) { await this.putMany('states', states); }
  async saveLGAs(lgas) { await this.putMany('lgas', lgas); }
  async saveStreets(streets) { await this.putMany('streets', streets); }
  async getStates() { return this.getAll('states'); }
  async getLGAsByState(stateId) { return this.getByIndex('lgas', 'state_id', stateId); }
  async getStreetsByLGA(lgaId) { return this.getByIndex('streets', 'lga_id', lgaId); }

  async cacheTasks(tasks) {
    await this.putMany('tasks', (tasks || []).map(task => ({ ...task, cached_at: new Date().toISOString() })));
    await this.setMeta('tasks_cached_at', new Date().toISOString());
  }

  async cacheGrowers(growers) {
    await this.putMany('growers', growers || []);
    await this.setMeta('growers_cached_at', new Date().toISOString());
  }

  async cacheResources(resources) {
    await this.putMany('resources', resources || []);
    await this.setMeta('resources_cached_at', new Date().toISOString());
  }

  async queueVisit(visit) {
    const localId = visit.local_id || `visit_${Date.now()}_${Math.random().toString(16).slice(2)}`;
    await this.put('visits', {
      ...visit,
      local_id: localId,
      client_visit_id: localId,
      sync_status: visit.sync_status || 'pending',
      attempts: Number(visit.attempts || 0),
      queued_at: visit.queued_at || new Date().toISOString(),
      updated_at: new Date().toISOString()
    });
    return localId;
  }

  async getQueuedVisits() {
    const visits = await this.getAll('visits');
    return visits.filter(visit => ['pending', 'failed'].includes(visit.sync_status));
  }

  async markVisitSynced(localId, serverVisitId) {
    const visits = await this.getAll('visits');
    const visit = visits.find(item => item.local_id === localId);
    if (!visit) return;
    await this.put('visits', {
      ...visit,
      sync_status: 'synced',
      server_visit_id: serverVisitId || visit.server_visit_id || null,
      synced_at: new Date().toISOString(),
      updated_at: new Date().toISOString()
    });
  }

  async markVisitFailed(localId, error) {
    const visits = await this.getAll('visits');
    const visit = visits.find(item => item.local_id === localId);
    if (!visit) return;
    await this.put('visits', {
      ...visit,
      sync_status: 'failed',
      attempts: Number(visit.attempts || 0) + 1,
      last_error: error || 'Sync failed',
      updated_at: new Date().toISOString()
    });
  }
}

class LocationDatabase extends FieldAgentDatabase {}

window.FieldAgentDatabase = FieldAgentDatabase;
window.LocationDatabase = LocationDatabase;
