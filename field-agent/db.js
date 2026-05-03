// field-agent/db.js - Enhanced for offline location
class LocationDatabase {
    constructor() {
        this.dbName = 'NATCODEV_Location';
        this.version = 2; // Incremented for new tables
    }
    
    async init() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(this.dbName, this.version);
            
            request.onupgradeneeded = (event) => {
                const db = event.target.result;
                
                // States table
                if (!db.objectStoreNames.contains('states')) {
                    const states = db.createObjectStore('states', { keyPath: 'id' });
                    states.createIndex('name', 'state_name', { unique: false });
                }
                
                // LGAs table
                if (!db.objectStoreNames.contains('lgas')) {
                    const lgas = db.createObjectStore('lgas', { keyPath: 'id' });
                    lgas.createIndex('state_id', 'state_id', { unique: false });
                    lgas.createIndex('name', 'lga_name', { unique: false });
                }
                
                // Streets table
                if (!db.objectStoreNames.contains('streets')) {
                    const streets = db.createObjectStore('streets', { keyPath: 'id' });
                    streets.createIndex('lga_id', 'lga_id', { unique: false });
                    streets.createIndex('name', 'street_name', { unique: false });
                }
            };
            
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }
    
    async saveStates(states) {
        const db = await this.init();
        const transaction = db.transaction(['states'], 'readwrite');
        const store = transaction.objectStore('states');
        states.forEach(state => store.put(state));
    }
    
    async saveLGAs(lgas) {
        const db = await this.init();
        const transaction = db.transaction(['lgas'], 'readwrite');
        const store = transaction.objectStore('lgas');
        lgas.forEach(lga => store.put(lga));
    }
    
    async saveStreets(streets) {
        const db = await this.init();
        const transaction = db.transaction(['streets'], 'readwrite');
        const store = transaction.objectStore('streets');
        streets.forEach(street => store.put(street));
    }
    
    async getStates() {
        const db = await this.init();
        const transaction = db.transaction(['states'], 'readonly');
        const store = transaction.objectStore('states');
        return new Promise((resolve) => {
            const request = store.getAll();
            request.onsuccess = () => resolve(request.result);
        });
    }
    
    async getLGAsByState(stateId) {
        const db = await this.init();
        const transaction = db.transaction(['lgas'], 'readonly');
        const store = transaction.objectStore('lgas');
        const index = store.index('state_id');
        return new Promise((resolve) => {
            const request = index.getAll(stateId);
            request.onsuccess = () => resolve(request.result);
        });
    }
    
    async getStreetsByLGA(lgaId) {
        const db = await this.init();
        const transaction = db.transaction(['streets'], 'readonly');
        const store = transaction.objectStore('streets');
        const index = store.index('lga_id');
        return new Promise((resolve) => {
            const request = index.getAll(lgaId);
            request.onsuccess = () => resolve(request.result);
        });
    }
}