// mobile/app.js - Main app logic
const db = new MobileDatabase();

// Initialize app
async function initApp() {
  // Load cached data
  const marketplace = await db.getData('marketplace');
  renderMarketplace(marketplace);
  
  // Try to sync with server
  if (navigator.onLine) {
    await syncWithServer();
  }
}

// Sync with server when online
async function syncWithServer() {
  try {
    // Fetch latest data
    const [marketplace, webinars] = await Promise.all([
      fetch('/api/mobile/marketplace').then(r => r.json()),
      fetch('/api/mobile/webinars').then(r => r.json())
    ]);
    
    // Save to local DB
    await db.saveData('marketplace', marketplace.items);
    await db.saveData('webinars', webinars.items);
    
    // Update UI
    renderMarketplace(marketplace.items);
    renderWebinars(webinars.items);
    
  } catch (err) {
    console.warn('Sync failed, using cached data');
  }
}

// Handle offline actions
async function purchaseProduct(productId, amount) {
  if (navigator.onLine) {
    // Online purchase
    const response = await fetch('/api/mobile/purchase', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ product_id: productId, amount })
    });
    
    if (response.ok) {
      showSuccess('Purchase completed!');
    }
  } else {
    // Queue for later
    await db.queueAction('/api/mobile/purchase', 'POST', { product_id: productId, amount });
    showSuccess('Purchase queued! Will complete when online.');
  }
}

// Listen for network changes
window.addEventListener('online', syncWithServer);

// Initialize
document.addEventListener('DOMContentLoaded', initApp);