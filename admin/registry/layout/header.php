<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $pageTitle ?? 'NATCODEV Registry' ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/inter@5.0.0/index.min.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<style>
/* Core Registry Workspace Styles */
*{margin:0;padding:0;box-sizing:border-box}
:root {
  --green-900:#0f2e1f; --green-800:#1a4731; --green-700:#235c3f; --green-600:#2d7a52;
  --green-500:#3a9d6a; --green-400:#4fc48a; --green-100:#e8f5ee; --green-50:#f0faf4;
  --bg:#f5f7f5; --card:#fff; --text:#1a1a1a; --text-secondary:#6b7280;
  --border:#e5e7eb; --danger:#dc2626; --warning:#f59e0b; --info:#3b82f6;
  --success:#10b981; --purple:#8b5cf6; --orange:#f97316;
}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);display:flex;min-height:100vh;font-size:13px;}
.sidebar { width:260px; background:var(--green-900); color:#fff; position:fixed; top:0; left:0; bottom:0; overflow-y:auto; z-index:100; display:flex; flex-direction:column; }
.sidebar-header { padding:20px; display:flex; align-items:center; gap:12px; border-bottom:1px solid rgba(255,255,255,0.1); }
.sidebar-logo { width:40px; height:40px; background:var(--green-400); border-radius:10px; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:18px; color:var(--green-900); flex-shrink:0; }
.sidebar-brand { font-size:15px; font-weight:700; }
.sidebar-brand small { display:block; font-size:10px; font-weight:400; opacity:0.7; margin-top:2px; }
.workspace-badge { margin:16px 20px 4px; padding:5px 10px; background:rgba(255,255,255,0.08); border-radius:6px; font-size:10px; text-transform:uppercase; letter-spacing:1px; opacity:0.6; }
.nav-section { padding:12px 0; }
.nav-section-title { padding:0 20px; font-size:10px; text-transform:uppercase; letter-spacing:1px; opacity:0.5; margin-bottom:8px; }
.nav-item { display:flex; align-items:center; gap:12px; padding:10px 20px; cursor:pointer; transition:all 0.2s; font-size:14px; color:rgba(255,255,255,0.75); border-left:3px solid transparent; text-decoration:none; }
.nav-item:hover { background:rgba(255,255,255,0.08); color:#fff; }
.nav-item.active { background:rgba(255,255,255,0.12); color:#fff; border-left-color:var(--green-400); }
.nav-item svg { width:18px; height:18px; flex-shrink:0; }
.nav-item .badge { margin-left:auto; background:var(--orange); color:#fff; font-size:10px; font-weight:700; padding:2px 7px; border-radius:10px; }
.sidebar-footer { padding:16px 20px; border-top:1px solid rgba(255,255,255,0.1); display:flex; align-items:center; gap:10px; margin-top:auto; }
.sidebar-avatar { width:36px; height:36px; border-radius:50%; background:var(--green-600); display:flex; align-items:center; justify-content:center; font-weight:600; font-size:13px; color:#fff; }
.sidebar-user { font-size:13px; font-weight:600; }
.sidebar-user small { display:block; font-size:11px; opacity:0.6; font-weight:400; }
.status-dot { width:8px; height:8px; background:var(--success); border-radius:50%; display:inline-block; margin-right:4px; }
.registry-summary { margin:12px 20px; padding:16px; background:rgba(255,255,255,0.05); border-radius:10px; border:1px solid rgba(255,255,255,0.08); }
.registry-summary-title { font-size:10px; text-transform:uppercase; letter-spacing:1px; opacity:0.6; margin-bottom:12px; }
.summary-row { display:flex; justify-content:space-between; padding:4px 0; font-size:12px; }
.summary-row .label { opacity:0.7; }
.summary-row .value { font-weight:600; }

.main { margin-left:260px; flex:1; min-height:100vh; }
.topbar { background:#fff; padding:14px 28px; display:flex; align-items:center; gap:16px; border-bottom:1px solid var(--border); position:sticky; top:0; z-index:50; }
.topbar-search { flex:1; max-width:480px; position:relative; }
.topbar-search input { width:100%; padding:9px 14px 9px 38px; border:1px solid var(--border); border-radius:8px; font-size:13px; background:var(--bg); }
.topbar-search svg { position:absolute; left:12px; top:50%; transform:translateY(-50%); width:16px; height:16px; color:var(--text-secondary); }
.topbar-actions { display:flex; align-items:center; gap:12px; margin-left:auto; }
.topbar-icon { width:36px; height:36px; border-radius:8px; border:1px solid var(--border); display:flex; align-items:center; justify-content:center; cursor:pointer; position:relative; background:#fff; }
.topbar-icon .dot { position:absolute; top:6px; right:6px; width:8px; height:8px; background:var(--danger); border-radius:50%; border:2px solid #fff; }
.topbar-profile { display:flex; align-items:center; gap:10px; cursor:pointer; padding:4px 8px; border-radius:8px; background:none; border:none; color:inherit; font:inherit; }
.topbar-profile:hover { background:var(--bg); }
.topbar-avatar { width:34px; height:34px; border-radius:50%; background:var(--green-600); display:flex; align-items:center; justify-content:center; color:#fff; font-weight:600; font-size:13px; }
.topbar-profile-info { font-size:13px; font-weight:600; text-align:left; }
.topbar-profile-info small { display:block; font-size:11px; color:var(--text-secondary); font-weight:400; }
.topbar-menu { display:none; position:absolute; right:0; top:48px; width:220px; background:#fff; border:1px solid var(--border); border-radius:10px; box-shadow:0 10px 25px rgba(0,0,0,0.1); padding:8px; z-index:90; }
.topbar-menu.active { display:block; }
.topbar-menu a { display:block; padding:8px 12px; border-radius:6px; color:var(--text); text-decoration:none; font-size:13px; }
.topbar-menu a:hover { background:var(--bg); }

.content { padding:28px; }
.page-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
.page-title { font-size:22px; font-weight:700; }
.page-subtitle { font-size:13px; color:var(--text-secondary); margin-top:2px; }
.btn { padding:9px 18px; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; border:none; display:inline-flex; align-items:center; gap:6px; transition:all 0.2s; text-decoration:none; }
.btn-primary { background:var(--green-700); color:#fff; }
.btn-primary:hover { background:var(--green-800); }
.btn-secondary { background:#fff; color:var(--text); border:1px solid var(--border); }
.btn-secondary:hover { background:var(--bg); }
.btn-danger { background:var(--danger); color:#fff; }
.btn-sm { padding:6px 12px; font-size:12px; }
.btn-icon { padding:6px; background:none; border:1px solid var(--border); border-radius:6px; cursor:pointer; }

.stats-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:16px; margin-bottom:24px; }
.stat-card { background:#fff; padding:20px; border-radius:12px; border:1px solid var(--border); }
.stat-card-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:12px; }
.stat-card-label { font-size:12px; color:var(--text-secondary); }
.stat-card-value { font-size:26px; font-weight:700; margin-top:4px; }

.card { background:#fff; border-radius:12px; border:1px solid var(--border); margin-bottom:20px; }
.card-header { padding:18px 22px; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; }
.card-title { font-size:15px; font-weight:700; }
.card-body { padding:22px; }
.card-body.p0 { padding:0; }

table { width:100%; border-collapse:collapse; }
th, td { padding:12px 22px; text-align:left; font-size:13px; }
th { background:var(--bg); font-weight:600; color:var(--text-secondary); font-size:11px; text-transform:uppercase; letter-spacing:0.5px; border-bottom:1px solid var(--border); }
td { border-bottom:1px solid var(--border); }
tr:last-child td { border-bottom:none; }
tr:hover td { background:var(--green-50); }

.status-badge { padding:4px 10px; border-radius:20px; font-size:11px; font-weight:600; display:inline-block; }
.status-verified,.status-approved,.status-active { background:#dcfce7; color:#166534; }
.status-pending-review,.status-under-review { background:#fef3c7; color:#92400e; }
.status-rejected,.status-revoked { background:#fee2e2; color:#991b1b; }

.form-group { margin-bottom:16px; }
.form-label { display:block; font-size:12px; font-weight:600; margin-bottom:6px; color:var(--text-secondary); }
.form-input,.form-select,.form-textarea { width:100%; padding:9px 12px; border:1px solid var(--border); border-radius:8px; font-size:13px; font-family:inherit; }

.modal-overlay { display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:200; align-items:center; justify-content:center; }
.modal-overlay.active { display:flex; }
.modal { background:#fff; border-radius:12px; width:90%; max-width:560px; max-height:90vh; overflow-y:auto; }

.avatar-sm { width:32px; height:32px; border-radius:50%; background:var(--green-100); color:var(--green-700); display:inline-flex; align-items:center; justify-content:center; font-weight:600; font-size:12px; }
.avatar-row { display:flex; align-items:center; gap:10px; }

.toast { position:fixed; bottom:24px; right:24px; background:var(--green-800); color:#fff; padding:12px 20px; border-radius:8px; font-size:13px; z-index:300; display:none; animation:slideIn 0.3s; }
@keyframes slideIn { from{transform:translateX(100%);opacity:0} to{transform:translateX(0);opacity:1} }

.grid-2 { display:grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
.grid-3 { display:grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
.grid-4 { display:grid; grid-template-columns: repeat(4, 1fr); gap: 20px; }

@media(max-width:900px){
  .sidebar{width:70px}
  .sidebar-brand,.workspace-badge,.nav-section-title,.nav-item span:not(.badge),.sidebar-user,.sidebar-user small,.registry-summary{display:none}
  .nav-item{justify-content:center;padding:12px}
  .main{margin-left:70px}
}
</style>
</head>
<body>
<aside class="sidebar">
  <div class="sidebar-header">
    <div class="sidebar-logo">🌴</div>
    <div class="sidebar-brand">NATCODEV<small>National Coconut Registry</small></div>
  </div>
  <div class="workspace-badge">REGISTRY WORKSPACE</div>
  
  <div class="nav-section">
    <div class="nav-section-title">Operations</div>
    <a class="nav-item <?= $activeNav === 'overview' ? 'active' : '' ?>" href="index.php">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
      <span>Overview</span>
    </a>
    <a class="nav-item <?= $activeNav === 'growers' ? 'active' : '' ?>" href="growers.php">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
      <span>Growers</span>
    </a>
    <a class="nav-item <?= $activeNav === 'applications' ? 'active' : '' ?>" href="applications.php">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
      <span>Applications</span>
    </a>
  </div>

  <div class="nav-section">
    <div class="nav-section-title">Verification</div>
    <a class="nav-item <?= $activeNav === 'documents' ? 'active' : '' ?>" href="documents.php">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
      <span>Documents</span>
    </a>
    <a class="nav-item <?= $activeNav === 'certificates' ? 'active' : '' ?>" href="certificates.php">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="7"/><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/></svg>
      <span>Certificates</span>
    </a>
  </div>

  <div class="nav-section">
    <div class="nav-section-title">Field & Data</div>
    <a class="nav-item <?= $activeNav === 'field' ? 'active' : '' ?>" href="field.php">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      <span>Field Network</span>
    </a>
    <a class="nav-item <?= $activeNav === 'import' ? 'active' : '' ?>" href="import.php">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
      <span>Batch Import</span>
    </a>
  </div>

  <div class="sidebar-footer">
    <div class="sidebar-avatar"><?= rx_user_initials($registryUser['name'] ?? 'Admin') ?></div>
    <div class="sidebar-user"><?= rx_e($registryUser['name'] ?? 'Admin') ?><small><span class="status-dot"></span>Online</small></div>
  </div>
</aside>

<div class="main">
  <div class="topbar">
    <div class="topbar-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" placeholder="Global registry search..." id="globalSearch">
    </div>
    <div class="topbar-actions">
      <button class="topbar-profile" type="button">
        <div class="topbar-avatar"><?= rx_user_initials($registryUser['name'] ?? 'Admin') ?></div>
        <div class="topbar-profile-info"><?= rx_e($registryUser['name'] ?? 'Admin') ?><small>Administrator</small></div>
      </button>
      <a href="../admin.php?logout=1" class="btn btn-secondary btn-sm">Logout</a>
    </div>
  </div>
  <div class="content">
