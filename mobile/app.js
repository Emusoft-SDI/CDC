// mobile/app.js - Main app logic
const db = new MobileDatabase();
const APP_BASE = location.pathname.includes('/mobile/')
  ? location.pathname.split('/mobile/')[0]
  : '';
const mobileApi = path => `${APP_BASE}/api/mobile/${path}`;

function authHeaders(extra = {}) {
  const token = localStorage.getItem('natcodev_token');
  return token ? { ...extra, Authorization: `Bearer ${token}` } : extra;
}

function escapeHtml(value) {
  return String(value || '').replace(/[&<>"']/g, char => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;'
  }[char]));
}

function renderMarketplace(items) {
  const container = document.querySelector('[data-marketplace]');
  if (!container) return;
  container.innerHTML = items.map(item => `
    <article>
      <strong>${escapeHtml(item.title || 'Marketplace item')}</strong>
      <p>${escapeHtml(item.description)}</p>
      <small>${escapeHtml(item.category)}</small>
    </article>
  `).join('');
}

function renderWebinars(items) {
  const container = document.querySelector('[data-webinars]');
  if (!container) return;
  container.innerHTML = items.map(item => `
    <article>
      <strong>${escapeHtml(item.title || 'Webinar')}</strong>
      <p>${escapeHtml(item.description)}</p>
      <small>${escapeHtml(item.start_time)}</small>
    </article>
  `).join('');
}

function showSuccess(message) {
  const container = document.querySelector('[data-alert]');
  if (container) {
    container.textContent = message;
  } else {
    alert(message);
  }
}

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
      fetch(mobileApi('marketplace.php'), { headers: authHeaders() }).then(r => r.json()),
      fetch(mobileApi('webinars.php'), { headers: authHeaders() }).then(r => r.json())
    ]);
    
    // Save to local DB
    await db.saveData('marketplace', marketplace.items || []);
    await db.saveData('webinars', webinars.items || []);
    
    // Update UI
    renderMarketplace(marketplace.items || []);
    renderWebinars(webinars.items || []);
    
  } catch (err) {
    console.warn('Sync failed, using cached data');
  }
}

// Handle offline actions
async function purchaseProduct(productId, amount) {
  if (navigator.onLine) {
    // Online purchase
    const response = await fetch(mobileApi('purchase.php'), {
      method: 'POST',
      headers: authHeaders({ 'Content-Type': 'application/json' }),
      body: JSON.stringify({ product_id: productId, amount })
    });
    
    if (response.ok) {
      showSuccess('Purchase completed!');
    }
  } else {
    // Queue for later
    await db.queueAction(mobileApi('purchase.php'), 'POST', { product_id: productId, amount });
    showSuccess('Purchase queued! Will complete when online.');
  }
}

// Listen for network changes
window.addEventListener('online', syncWithServer);

// Initialize
document.addEventListener('DOMContentLoaded', initApp);
