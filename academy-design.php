<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>NATCODEV Academy - Workspace</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/inter@5.0.0/index.min.css">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
:root {
  --green-900:#0f2e1f; --green-800:#1a4731; --green-700:#235c3f; --green-600:#2d7a52;
  --green-500:#3a9d6a; --green-400:#4fc48a; --green-100:#e8f5ee; --green-50:#f0faf4;
  --bg:#f5f7f5; --card:#fff; --text:#1a1a1a; --text-secondary:#6b7280;
  --border:#e5e7eb; --danger:#dc2626; --warning:#f59e0b; --info:#3b82f6;
  --success:#10b981; --purple:#8b5cf6;
}
body { font-family:'Inter',sans-serif; background:var(--bg); color:var(--text); display:flex; min-height:100vh; }
.sidebar { width:260px; background:var(--green-900); color:#fff; position:fixed; top:0; left:0; bottom:0; overflow-y:auto; z-index:100; }
.sidebar-header { padding:20px; display:flex; align-items:center; gap:12px; border-bottom:1px solid rgba(255,255,255,0.1); }
.sidebar-logo { width:40px; height:40px; background:var(--green-400); border-radius:10px; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:14px; color:var(--green-900); }
.sidebar-brand { font-size:15px; font-weight:700; }
.sidebar-brand small { display:block; font-size:10px; font-weight:400; opacity:0.7; margin-top:2px; }
.nav-section { padding:16px 0; }
.nav-section-title { padding:0 20px; font-size:10px; text-transform:uppercase; letter-spacing:1px; opacity:0.5; margin-bottom:8px; }
.nav-item { display:flex; align-items:center; gap:12px; padding:10px 20px; cursor:pointer; transition:all 0.2s; font-size:14px; color:rgba(255,255,255,0.75); border-left:3px solid transparent; }
.nav-item:hover { background:rgba(255,255,255,0.08); color:#fff; }
.nav-item.active { background:rgba(255,255,255,0.12); color:#fff; border-left-color:var(--green-400); }
.nav-item svg { width:18px; height:18px; flex-shrink:0; }
.nav-item .badge { margin-left:auto; background:var(--green-400); color:var(--green-900); font-size:10px; font-weight:700; padding:2px 7px; border-radius:10px; }
.sidebar-footer { padding:16px 20px; border-top:1px solid rgba(255,255,255,0.1); display:flex; align-items:center; gap:10px; }
.sidebar-avatar { width:36px; height:36px; border-radius:50%; background:var(--green-600); display:flex; align-items:center; justify-content:center; font-weight:600; font-size:13px; }
.sidebar-user { font-size:13px; font-weight:600; }
.sidebar-user small { display:block; font-size:11px; opacity:0.6; font-weight:400; }

.main { margin-left:260px; flex:1; min-height:100vh; }
.topbar { background:#fff; padding:14px 28px; display:flex; align-items:center; gap:16px; border-bottom:1px solid var(--border); position:sticky; top:0; z-index:50; }
.topbar-search { flex:1; max-width:480px; position:relative; }
.topbar-search input { width:100%; padding:9px 14px 9px 38px; border:1px solid var(--border); border-radius:8px; font-size:13px; background:var(--bg); }
.topbar-search svg { position:absolute; left:12px; top:50%; transform:translateY(-50%); width:16px; height:16px; color:var(--text-secondary); }
.topbar-actions { display:flex; align-items:center; gap:12px; margin-left:auto; }
.topbar-icon { width:36px; height:36px; border-radius:8px; border:1px solid var(--border); display:flex; align-items:center; justify-content:center; cursor:pointer; position:relative; background:#fff; }
.topbar-icon .dot { position:absolute; top:6px; right:6px; width:8px; height:8px; background:var(--danger); border-radius:50%; border:2px solid #fff; }
.topbar-profile { display:flex; align-items:center; gap:10px; cursor:pointer; padding:4px 8px; border-radius:8px; }
.topbar-profile:hover { background:var(--bg); }
.topbar-avatar { width:34px; height:34px; border-radius:50%; background:var(--green-600); display:flex; align-items:center; justify-content:center; color:#fff; font-weight:600; font-size:13px; }
.topbar-profile-info { font-size:13px; font-weight:600; }
.topbar-profile-info small { display:block; font-size:11px; color:var(--text-secondary); font-weight:400; }

.content { padding:28px; }
.page { display:none; }
.page.active { display:block; }
.page-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
.page-title { font-size:22px; font-weight:700; }
.page-subtitle { font-size:13px; color:var(--text-secondary); margin-top:2px; }
.btn { padding:9px 18px; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; border:none; display:inline-flex; align-items:center; gap:6px; transition:all 0.2s; }
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
.stat-card-icon { width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; }
.stat-card-icon svg { width:20px; height:20px; }
.stat-card-label { font-size:12px; color:var(--text-secondary); }
.stat-card-value { font-size:26px; font-weight:700; margin-top:4px; }
.stat-card-change { font-size:11px; margin-top:6px; }
.stat-card-change.up { color:var(--success); }
.stat-card-change.down { color:var(--danger); }

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
.status-active { background:#dcfce7; color:#166534; }
.status-pending { background:#fef3c7; color:#92400e; }
.status-completed { background:#dbeafe; color:#1e40af; }
.status-draft { background:#f3f4f6; color:#4b5563; }
.status-cancelled { background:#fee2e2; color:#991b1b; }
.status-expired { background:#f3f4f6; color:#6b7280; }
.status-approved { background:#dcfce7; color:#166534; }
.status-rejected { background:#fee2e2; color:#991b1b; }

.progress-bar { height:6px; background:var(--border); border-radius:3px; overflow:hidden; width:100%; }
.progress-fill { height:100%; background:var(--green-500); border-radius:3px; transition:width 0.3s; }

.form-group { margin-bottom:16px; }
.form-label { display:block; font-size:12px; font-weight:600; margin-bottom:6px; color:var(--text-secondary); }
.form-input, .form-select, .form-textarea { width:100%; padding:9px 12px; border:1px solid var(--border); border-radius:8px; font-size:13px; font-family:inherit; }
.form-input:focus, .form-select:focus, .form-textarea:focus { outline:none; border-color:var(--green-500); box-shadow:0 0 0 3px rgba(58,157,106,0.1); }
.form-textarea { resize:vertical; min-height:80px; }
.form-row { display:grid; grid-template-columns:1fr 1fr; gap:16px; }

.tabs { display:flex; gap:4px; border-bottom:1px solid var(--border); margin-bottom:20px; }
.tab { padding:10px 16px; font-size:13px; font-weight:500; cursor:pointer; border-bottom:2px solid transparent; color:var(--text-secondary); }
.tab.active { color:var(--green-700); border-bottom-color:var(--green-700); font-weight:600; }
.tab:hover { color:var(--text); }

.filter-bar { display:flex; gap:10px; margin-bottom:16px; flex-wrap:wrap; align-items:center; }
.filter-bar input, .filter-bar select { padding:8px 12px; border:1px solid var(--border); border-radius:8px; font-size:13px; }

.modal-overlay { display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:200; align-items:center; justify-content:center; }
.modal-overlay.active { display:flex; }
.modal { background:#fff; border-radius:12px; width:90%; max-width:560px; max-height:90vh; overflow-y:auto; }
.modal-header { padding:18px 22px; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; }
.modal-title { font-size:16px; font-weight:700; }
.modal-body { padding:22px; }
.modal-footer { padding:16px 22px; border-top:1px solid var(--border); display:flex; justify-content:flex-end; gap:10px; }

.grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
.grid-3 { display:grid; grid-template-columns:repeat(3,1fr); gap:20px; }

.empty-state { text-align:center; padding:60px 20px; color:var(--text-secondary); }
.empty-state svg { width:48px; height:48px; margin-bottom:12px; opacity:0.4; }

.avatar-sm { width:32px; height:32px; border-radius:50%; background:var(--green-100); color:var(--green-700); display:inline-flex; align-items:center; justify-content:center; font-weight:600; font-size:12px; }
.avatar-row { display:flex; align-items:center; gap:10px; }

.toast { position:fixed; bottom:24px; right:24px; background:var(--green-800); color:#fff; padding:12px 20px; border-radius:8px; font-size:13px; z-index:300; display:none; animation:slideIn 0.3s; }
@keyframes slideIn { from{transform:translateX(100%);opacity:0;} to{transform:translateX(0);opacity:1;} }

.chip { display:inline-flex; align-items:center; gap:4px; padding:3px 10px; background:var(--green-100); color:var(--green-700); border-radius:20px; font-size:11px; font-weight:500; }
.chip-close { cursor:pointer; }

@media(max-width:900px) {
  .sidebar { width:70px; }
  .sidebar-brand, .nav-section-title, .nav-item span, .sidebar-user, .sidebar-user small, .nav-item .badge { display:none; }
  .nav-item { justify-content:center; padding:12px; }
  .main { margin-left:70px; }
  .grid-2, .grid-3, .form-row { grid-template-columns:1fr; }
}
</style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sidebar-header">
    <div class="sidebar-logo">NC</div>
    <div class="sidebar-brand">NATCODEV<small>Academy Workspace</small></div>
  </div>

  <div class="nav-section">
    <div class="nav-section-title">Main</div>
    <div class="nav-item active" data-page="dashboard">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
      <span>Dashboard</span>
    </div>
    <div class="nav-item" data-page="programs">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg>
      <span>Programs</span>
      <span class="badge">12</span>
    </div>
    <div class="nav-item" data-page="courses">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z"/><path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z"/></svg>
      <span>Courses</span>
      <span class="badge">48</span>
    </div>
    <div class="nav-item" data-page="lessons">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
      <span>Lessons</span>
    </div>
    <div class="nav-item" data-page="assessments">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
      <span>Assessments</span>
    </div>
  </div>

  <div class="nav-section">
    <div class="nav-section-title">Operations</div>
    <div class="nav-item" data-page="cohorts">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
      <span>Cohorts</span>
    </div>
    <div class="nav-item" data-page="instructors">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      <span>Instructors</span>
    </div>
    <div class="nav-item" data-page="attendance">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      <span>Attendance</span>
    </div>
    <div class="nav-item" data-page="reminders">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
      <span>Reminders</span>
      <span class="badge">5</span>
    </div>
  </div>

  <div class="nav-section">
    <div class="nav-section-title">People</div>
    <div class="nav-item" data-page="learners">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
      <span>Learners</span>
    </div>
    <div class="nav-item" data-page="certificates">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="7"/><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/></svg>
      <span>Certificates</span>
    </div>
    <div class="nav-item" data-page="pathways">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
      <span>Certificate Pathways</span>
    </div>
  </div>

  <div class="nav-section">
    <div class="nav-section-title">Finance & Insights</div>
    <div class="nav-item" data-page="refunds">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 102.13-9.36L1 10"/></svg>
      <span>Refunds</span>
    </div>
    <div class="nav-item" data-page="feedback">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
      <span>Feedback</span>
    </div>
    <div class="nav-item" data-page="reports">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
      <span>Reports</span>
    </div>
  </div>

  <div class="sidebar-footer">
    <div class="sidebar-avatar">GD</div>
    <div class="sidebar-user">Grace Deh<small>Super Admin</small></div>
  </div>
</aside>

<!-- MAIN -->
<div class="main">
  <div class="topbar">
    <div class="topbar-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" placeholder="Search across all workspace pages..." id="globalSearch">
    </div>
    <div class="topbar-actions">
      <div class="topbar-icon" title="Notifications">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
        <span class="dot"></span>
      </div>
      <div class="topbar-icon" title="Help">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      </div>
      <div class="topbar-profile">
        <div class="topbar-avatar">GD</div>
        <div class="topbar-profile-info">Grace Deh<small>Super Admin</small></div>
      </div>
    </div>
  </div>

  <div class="content">

    <!-- DASHBOARD -->
    <div class="page active" id="page-dashboard">
      <div class="page-header">
        <div>
          <div class="page-title">Dashboard</div>
          <div class="page-subtitle">Overview of your academy performance</div>
        </div>
        <button class="btn btn-primary" onclick="showToast('Report exported')"> Export Report</button>
      </div>

      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-card-header">
            <div class="stat-card-label">Total Learners</div>
            <div class="stat-card-icon" style="background:#dbeafe;color:#1e40af"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div>
          </div>
          <div class="stat-card-value">3,624</div>
          <div class="stat-card-change up">↑ 12.5% from last month</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-header">
            <div class="stat-card-label">Active Enrollments</div>
            <div class="stat-card-icon" style="background:#dcfce7;color:#166534"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
          </div>
          <div class="stat-card-value">2,479</div>
          <div class="stat-card-change up">↑ 8.2% from last month</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-header">
            <div class="stat-card-label">Certificates Issued</div>
            <div class="stat-card-icon" style="background:#f3e8ff;color:#6b21a8"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="7"/><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/></svg></div>
          </div>
          <div class="stat-card-value">1,532</div>
          <div class="stat-card-change up">↑ 15.3% from last month</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-header">
            <div class="stat-card-label">Completion Rate</div>
            <div class="stat-card-icon" style="background:#fef3c7;color:#92400e"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></div>
          </div>
          <div class="stat-card-value">68.4%</div>
          <div class="stat-card-change down">↓ 2.1% from last month</div>
        </div>
      </div>

      <div class="grid-2">
        <div class="card">
          <div class="card-header">
            <div class="card-title">Recent Enrollments</div>
            <button class="btn btn-secondary btn-sm" onclick="navigateTo('learners')">View All</button>
          </div>
          <div class="card-body p0">
            <table>
              <thead><tr><th>Learner</th><th>Course</th><th>Date</th><th>Status</th></tr></thead>
              <tbody>
                <tr><td><div class="avatar-row"><div class="avatar-sm">AK</div>Aisha Koroma</div></td><td>Power BI Essentials</td><td>Jun 4, 2026</td><td><span class="status-badge status-active">Active</span></td></tr>
                <tr><td><div class="avatar-row"><div class="avatar-sm">TS</div>Tunde Salami</div></td><td>Python for Data Science</td><td>Jun 3, 2026</td><td><span class="status-badge status-active">Active</span></td></tr>
                <tr><td><div class="avatar-row"><div class="avatar-sm">MO</div>Miriam Osei</div></td><td>Agile Project Management</td><td>Jun 2, 2026</td><td><span class="status-badge status-pending">Pending</span></td></tr>
                <tr><td><div class="avatar-row"><div class="avatar-sm">FN</div>Fatima Ndiaye</div></td><td>UX/UI Design Fundamentals</td><td>Jun 1, 2026</td><td><span class="status-badge status-active">Active</span></td></tr>
              </tbody>
            </table>
          </div>
        </div>
        <div class="card">
          <div class="card-header">
            <div class="card-title">Upcoming Live Sessions</div>
            <button class="btn btn-secondary btn-sm" onclick="navigateTo('cohorts')">View Calendar</button>
          </div>
          <div class="card-body p0">
            <table>
              <thead><tr><th>Session</th><th>Instructor</th><th>Date</th><th>Seats</th></tr></thead>
              <tbody>
                <tr><td>Python Capstone Review</td><td>Dr. Adebayo</td><td>Jun 8, 10:00</td><td>12/30</td></tr>
                <tr><td>Power BI Dashboard Lab</td><td>Prof. Mensah</td><td>Jun 9, 14:00</td><td>8/25</td></tr>
                <tr><td>Agile Sprint Planning</td><td>Ms. Okonkwo</td><td>Jun 10, 09:00</td><td>20/25</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- PROGRAMS -->
    <div class="page" id="page-programs">
      <div class="page-header">
        <div><div class="page-title">Training Programs</div><div class="page-subtitle">Manage learning programs, cohorts, and certificate pathways</div></div>
        <button class="btn btn-primary" onclick="openModal('programModal')">+ New Program</button>
      </div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">Total Programs</div><div class="stat-card-value">12</div><div class="stat-card-change up">3 active cohorts</div></div>
        <div class="stat-card"><div class="stat-card-label">Total Learners</div><div class="stat-card-value">3,624</div></div>
        <div class="stat-card"><div class="stat-card-label">Completion Rate</div><div class="stat-card-value">68.4%</div></div>
        <div class="stat-card"><div class="stat-card-label">Certificates Issued</div><div class="stat-card-value">1,532</div></div>
      </div>
      <div class="card">
        <div class="card-header">
          <div class="card-title">All Programs</div>
          <div class="filter-bar" style="margin:0">
            <input type="text" placeholder="Search programs..." id="programSearch" oninput="filterTable('programsTable',this.value)">
            <select onchange="filterStatus('programsTable',this.value)">
              <option value="">All Status</option><option>Active</option><option>Draft</option><option>Completed</option>
            </select>
          </div>
        </div>
        <div class="card-body p0">
          <table id="programsTable">
            <thead><tr><th>Program Name</th><th>Courses</th><th>Learners</th><th>Duration</th><th>Completion</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <tr><td><strong>Career Onboarding Program</strong></td><td>5</td><td>1,245</td><td>12 weeks</td><td><div class="progress-bar" style="width:120px"><div class="progress-fill" style="width:85%"></div></div></td><td><span class="status-badge status-active">Active</span></td><td><button class="btn-icon">⋮</button></td></tr>
              <tr><td><strong>Pre-Service Accreditation</strong></td><td>8</td><td>845</td><td>16 weeks</td><td><div class="progress-bar" style="width:120px"><div class="progress-fill" style="width:62%"></div></div></td><td><span class="status-badge status-active">Active</span></td><td><button class="btn-icon">⋮</button></td></tr>
              <tr><td><strong>Field Staff Certification Program</strong></td><td>6</td><td>630</td><td>10 weeks</td><td><div class="progress-bar" style="width:120px"><div class="progress-fill" style="width:74%"></div></div></td><td><span class="status-badge status-active">Active</span></td><td><button class="btn-icon">⋮</button></td></tr>
              <tr><td><strong>State Coordinator Operations Program</strong></td><td>4</td><td>278</td><td>8 weeks</td><td><div class="progress-bar" style="width:120px"><div class="progress-fill" style="width:45%"></div></div></td><td><span class="status-badge status-pending">Draft</span></td><td><button class="btn-icon">⋮</button></td></tr>
              <tr><td><strong>Headquarters Skills Certification</strong></td><td>7</td><td>615</td><td>14 weeks</td><td><div class="progress-bar" style="width:120px"><div class="progress-fill" style="width:91%"></div></div></td><td><span class="status-badge status-completed">Completed</span></td><td><button class="btn-icon">⋮</button></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- COURSES -->
    <div class="page" id="page-courses">
      <div class="page-header">
        <div><div class="page-title">Courses</div><div class="page-subtitle">48 courses across all programs</div></div>
        <button class="btn btn-primary" onclick="openModal('courseModal')">+ New Course</button>
      </div>
      <div class="tabs">
        <div class="tab active" onclick="switchTab(this,'all-courses')">All Courses</div>
        <div class="tab" onclick="switchTab(this,'published-courses')">Published</div>
        <div class="tab" onclick="switchTab(this,'draft-courses')">Drafts</div>
        <div class="tab" onclick="switchTab(this,'archived-courses')">Archived</div>
      </div>
      <div class="card">
        <div class="card-header">
          <div class="card-title">Course Catalog</div>
          <div class="filter-bar" style="margin:0">
            <input type="text" placeholder="Search courses..." oninput="filterTable('coursesTable',this.value)">
            <select><option>All Programs</option><option>Career Onboarding</option><option>Pre-Service</option></select>
          </div>
        </div>
        <div class="card-body p0">
          <table id="coursesTable">
            <thead><tr><th>Course</th><th>Program</th><th>Lessons</th><th>Enrolled</th><th>Rating</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <tr><td><strong>Power BI Essentials</strong></td><td>Career Onboarding</td><td>12</td><td>485</td><td>⭐ 4.8</td><td><span class="status-badge status-active">Published</span></td><td><button class="btn-icon">⋮</button></td></tr>
              <tr><td><strong>Python for Data Science</strong></td><td>Career Onboarding</td><td>18</td><td>620</td><td>⭐ 4.9</td><td><span class="status-badge status-active">Published</span></td><td><button class="btn-icon">⋮</button></td></tr>
              <tr><td><strong>Agile Project Management</strong></td><td>Pre-Service Accreditation</td><td>10</td><td>312</td><td>⭐ 4.6</td><td><span class="status-badge status-active">Published</span></td><td><button class="btn-icon">⋮</button></td></tr>
              <tr><td><strong>UX/UI Design Fundamentals</strong></td><td>Pre-Service Accreditation</td><td>14</td><td>278</td><td>⭐ 4.7</td><td><span class="status-badge status-active">Published</span></td><td><button class="btn-icon"></button></td></tr>
              <tr><td><strong>Data Visualization with R</strong></td><td>Field Staff Certification</td><td>8</td><td>195</td><td>⭐ 4.5</td><td><span class="status-badge status-draft">Draft</span></td><td><button class="btn-icon"></button></td></tr>
              <tr><td><strong>Leadership in Public Health</strong></td><td>HQ Skills Certification</td><td>11</td><td>240</td><td>⭐ 4.8</td><td><span class="status-badge status-active">Published</span></td><td><button class="btn-icon">⋮</button></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- LESSONS -->
    <div class="page" id="page-lessons">
      <div class="page-header">
        <div><div class="page-title">Lessons</div><div class="page-subtitle">Manage individual lessons within courses</div></div>
        <button class="btn btn-primary" onclick="openModal('lessonModal')">+ New Lesson</button>
      </div>
      <div class="filter-bar">
        <select id="lessonCourseFilter" onchange="filterLessons()">
          <option value="">All Courses</option>
          <option>Power BI Essentials</option>
          <option>Python for Data Science</option>
          <option>Agile Project Management</option>
        </select>
        <select><option>All Types</option><option>Video</option><option>Reading</option><option>Quiz</option><option>Assignment</option></select>
      </div>
      <div class="card">
        <div class="card-body p0">
          <table id="lessonsTable">
            <thead><tr><th>Lesson Title</th><th>Course</th><th>Type</th><th>Duration</th><th>Completion</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <tr><td><strong>Introduction to Power BI</strong></td><td>Power BI Essentials</td><td><span class="chip"> Video</span></td><td>15 min</td><td>92%</td><td><span class="status-badge status-active">Published</span></td><td><button class="btn-icon">✏️</button></td></tr>
              <tr><td><strong>Connecting Data Sources</strong></td><td>Power BI Essentials</td><td><span class="chip">🎥 Video</span></td><td>22 min</td><td>88%</td><td><span class="status-badge status-active">Published</span></td><td><button class="btn-icon">️</button></td></tr>
              <tr><td><strong>Data Modeling Basics</strong></td><td>Power BI Essentials</td><td><span class="chip">📖 Reading</span></td><td>10 min</td><td>76%</td><td><span class="status-badge status-active">Published</span></td><td><button class="btn-icon">✏️</button></td></tr>
              <tr><td><strong>Creating Visualizations</strong></td><td>Power BI Essentials</td><td><span class="chip">📝 Assignment</span></td><td>45 min</td><td>65%</td><td><span class="status-badge status-active">Published</span></td><td><button class="btn-icon">✏️</button></td></tr>
              <tr><td><strong>Python Variables & Types</strong></td><td>Python for Data Science</td><td><span class="chip">🎥 Video</span></td><td>18 min</td><td>94%</td><td><span class="status-badge status-active">Published</span></td><td><button class="btn-icon">✏️</button></td></tr>
              <tr><td><strong>Pandas Fundamentals</strong></td><td>Python for Data Science</td><td><span class="chip">❓ Quiz</span></td><td>20 min</td><td>81%</td><td><span class="status-badge status-draft">Draft</span></td><td><button class="btn-icon">✏️</button></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ASSESSMENTS -->
    <div class="page" id="page-assessments">
      <div class="page-header">
        <div><div class="page-title">Assessments</div><div class="page-subtitle">Quizzes, exams, and assignments</div></div>
        <button class="btn btn-primary" onclick="openModal('assessmentModal')">+ New Assessment</button>
      </div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">Total Assessments</div><div class="stat-card-value">156</div></div>
        <div class="stat-card"><div class="stat-card-label">Avg Pass Rate</div><div class="stat-card-value">78.3%</div></div>
        <div class="stat-card"><div class="stat-card-label">Pending Reviews</div><div class="stat-card-value">24</div></div>
        <div class="stat-card"><div class="stat-card-label">Avg Score</div><div class="stat-card-value">72.5</div></div>
      </div>
      <div class="card">
        <div class="card-header"><div class="card-title">Assessment Library</div></div>
        <div class="card-body p0">
          <table>
            <thead><tr><th>Assessment</th><th>Course</th><th>Type</th><th>Questions</th><th>Duration</th><th>Pass Rate</th><th>Status</th></tr></thead>
            <tbody>
              <tr><td><strong>Power BI Fundamentals Quiz</strong></td><td>Power BI Essentials</td><td>Quiz</td><td>25</td><td>30 min</td><td>85%</td><td><span class="status-badge status-active">Active</span></td></tr>
              <tr><td><strong>Mid-Term Exam: Data Modeling</strong></td><td>Power BI Essentials</td><td>Exam</td><td>50</td><td>90 min</td><td>72%</td><td><span class="status-badge status-active">Active</span></td></tr>
              <tr><td><strong>Python Basics Assessment</strong></td><td>Python for Data Science</td><td>Quiz</td><td>30</td><td>40 min</td><td>88%</td><td><span class="status-badge status-active">Active</span></td></tr>
              <tr><td><strong>Capstone Project Submission</strong></td><td>Python for Data Science</td><td>Assignment</td><td>5</td><td>7 days</td><td>65%</td><td><span class="status-badge status-pending">Reviewing</span></td></tr>
              <tr><td><strong>Agile Principles Test</strong></td><td>Agile Project Management</td><td>Quiz</td><td>20</td><td>25 min</td><td>91%</td><td><span class="status-badge status-active">Active</span></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- COHORTS -->
    <div class="page" id="page-cohorts">
      <div class="page-header">
        <div><div class="page-title">Cohorts</div><div class="page-subtitle">Manage learner groups and sessions</div></div>
        <button class="btn btn-primary" onclick="openModal('cohortModal')">+ New Cohort</button>
      </div>
      <div class="card">
        <div class="card-header"><div class="card-title">Active Cohorts</div></div>
        <div class="card-body p0">
          <table>
            <thead><tr><th>Cohort</th><th>Program</th><th>Start Date</th><th>End Date</th><th>Learners</th><th>Progress</th><th>Status</th></tr></thead>
            <tbody>
              <tr><td><strong>Cohort 2026-A</strong></td><td>Career Onboarding</td><td>Jan 15, 2026</td><td>Apr 10, 2026</td><td>245</td><td><div class="progress-bar" style="width:100px"><div class="progress-fill" style="width:100%"></div></div></td><td><span class="status-badge status-completed">Completed</span></td></tr>
              <tr><td><strong>Cohort 2026-B</strong></td><td>Career Onboarding</td><td>May 1, 2026</td><td>Jul 25, 2026</td><td>312</td><td><div class="progress-bar" style="width:100px"><div class="progress-fill" style="width:42%"></div></div></td><td><span class="status-badge status-active">Active</span></td></tr>
              <tr><td><strong>Cohort 2026-C</strong></td><td>Pre-Service Accreditation</td><td>Mar 10, 2026</td><td>Jun 30, 2026</td><td>189</td><td><div class="progress-bar" style="width:100px"><div class="progress-fill" style="width:68%"></div></div></td><td><span class="status-badge status-active">Active</span></td></tr>
              <tr><td><strong>Cohort 2026-D</strong></td><td>Field Staff Certification</td><td>Jun 5, 2026</td><td>Aug 15, 2026</td><td>156</td><td><div class="progress-bar" style="width:100px"><div class="progress-fill" style="width:5%"></div></div></td><td><span class="status-badge status-active">Active</span></td></tr>
              <tr><td><strong>Cohort 2026-E</strong></td><td>HQ Skills Certification</td><td>Jul 1, 2026</td><td>Sep 30, 2026</td><td>98</td><td><div class="progress-bar" style="width:100px"><div class="progress-fill" style="width:0%"></div></div></td><td><span class="status-badge status-pending">Upcoming</span></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- INSTRUCTORS -->
    <div class="page" id="page-instructors">
      <div class="page-header">
        <div><div class="page-title">Instructors</div><div class="page-subtitle">Manage teaching staff and facilitators</div></div>
        <button class="btn btn-primary" onclick="openModal('instructorModal')">+ Add Instructor</button>
      </div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">Total Instructors</div><div class="stat-card-value">28</div></div>
        <div class="stat-card"><div class="stat-card-label">Active This Month</div><div class="stat-card-value">22</div></div>
        <div class="stat-card"><div class="stat-card-label">Avg Rating</div><div class="stat-card-value">4.6</div></div>
        <div class="stat-card"><div class="stat-card-label">Total Sessions</div><div class="stat-card-value">342</div></div>
      </div>
      <div class="card">
        <div class="card-body p0">
          <table>
            <thead><tr><th>Instructor</th><th>Email</th><th>Courses</th><th>Learners</th><th>Rating</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <tr><td><div class="avatar-row"><div class="avatar-sm">DA</div><div><strong>Dr. Adebayo</strong><br><small style="color:var(--text-secondary)">Data Science</small></div></div></td><td>adebayo@natcodev.org</td><td>4</td><td>620</td><td>⭐ 4.9</td><td><span class="status-badge status-active">Active</span></td><td><button class="btn-icon">⋮</button></td></tr>
              <tr><td><div class="avatar-row"><div class="avatar-sm">PM</div><div><strong>Prof. Mensah</strong><br><small style="color:var(--text-secondary)">Business Intelligence</small></div></div></td><td>mensah@natcodev.org</td><td>3</td><td>485</td><td>⭐ 4.8</td><td><span class="status-badge status-active">Active</span></td><td><button class="btn-icon">⋮</button></td></tr>
              <tr><td><div class="avatar-row"><div class="avatar-sm">MO</div><div><strong>Ms. Okonkwo</strong><br><small style="color:var(--text-secondary)">Project Management</small></div></div></td><td>okonkwo@natcodev.org</td><td>2</td><td>312</td><td>⭐ 4.7</td><td><span class="status-badge status-active">Active</span></td><td><button class="btn-icon">⋮</button></td></tr>
              <tr><td><div class="avatar-row"><div class="avatar-sm">JK</div><div><strong>Mr. Kamara</strong><br><small style="color:var(--text-secondary)">UX Design</small></div></div></td><td>kamara@natcodev.org</td><td>2</td><td>278</td><td>⭐ 4.6</td><td><span class="status-badge status-active">Active</span></td><td><button class="btn-icon">⋮</button></td></tr>
              <tr><td><div class="avatar-row"><div class="avatar-sm">SN</div><div><strong>Dr. Nkrumah</strong><br><small style="color:var(--text-secondary)">Public Health</small></div></div></td><td>nkrumah@natcodev.org</td><td>3</td><td>240</td><td>⭐ 4.8</td><td><span class="status-badge status-pending">On Leave</span></td><td><button class="btn-icon">⋮</button></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ATTENDANCE -->
    <div class="page" id="page-attendance">
      <div class="page-header">
        <div><div class="page-title">Attendance</div><div class="page-subtitle">Track session attendance across cohorts</div></div>
        <button class="btn btn-primary" onclick="showToast('Attendance report exported')">📥 Export</button>
      </div>
      <div class="filter-bar">
        <select><option>All Cohorts</option><option>Cohort 2026-B</option><option>Cohort 2026-C</option><option>Cohort 2026-D</option></select>
        <input type="date" value="2026-06-05">
        <button class="btn btn-secondary btn-sm">Apply Filter</button>
      </div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">Today's Sessions</div><div class="stat-card-value">8</div></div>
        <div class="stat-card"><div class="stat-card-label">Avg Attendance</div><div class="stat-card-value">84.2%</div></div>
        <div class="stat-card"><div class="stat-card-label">Absent Today</div><div class="stat-card-value">47</div></div>
        <div class="stat-card"><div class="stat-card-label">Late Arrivals</div><div class="stat-card-value">23</div></div>
      </div>
      <div class="card">
        <div class="card-header"><div class="card-title">Today's Attendance - June 5, 2026</div></div>
        <div class="card-body p0">
          <table>
            <thead><tr><th>Learner</th><th>Cohort</th><th>Session</th><th>Check-in</th><th>Duration</th><th>Status</th></tr></thead>
            <tbody>
              <tr><td><div class="avatar-row"><div class="avatar-sm">AK</div>Aisha Koroma</div></td><td>Cohort 2026-B</td><td>Power BI Lab</td><td>09:58 AM</td><td>1h 45m</td><td><span class="status-badge status-active">Present</span></td></tr>
              <tr><td><div class="avatar-row"><div class="avatar-sm">TS</div>Tunde Salami</div></td><td>Cohort 2026-B</td><td>Power BI Lab</td><td>10:12 AM</td><td>1h 30m</td><td><span class="status-badge status-pending">Late</span></td></tr>
              <tr><td><div class="avatar-row"><div class="avatar-sm">MO</div>Miriam Osei</div></td><td>Cohort 2026-C</td><td>Agile Workshop</td><td>—</td><td>—</td><td><span class="status-badge status-cancelled">Absent</span></td></tr>
              <tr><td><div class="avatar-row"><div class="avatar-sm">FN</div>Fatima Ndiaye</div></td><td>Cohort 2026-C</td><td>Agile Workshop</td><td>02:00 PM</td><td>2h 00m</td><td><span class="status-badge status-active">Present</span></td></tr>
              <tr><td><div class="avatar-row"><div class="avatar-sm">JB</div>James Boateng</div></td><td>Cohort 2026-D</td><td>Field Methods</td><td>08:55 AM</td><td>3h 15m</td><td><span class="status-badge status-active">Present</span></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- REMINDERS -->
    <div class="page" id="page-reminders">
      <div class="page-header">
        <div><div class="page-title">Reminders</div><div class="page-subtitle">Automated notifications and manual reminders</div></div>
        <button class="btn btn-primary" onclick="openModal('reminderModal')">+ New Reminder</button>
      </div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">Scheduled</div><div class="stat-card-value">18</div></div>
        <div class="stat-card"><div class="stat-card-label">Sent Today</div><div class="stat-card-value">42</div></div>
        <div class="stat-card"><div class="stat-card-label">Open Rate</div><div class="stat-card-value">76.4%</div></div>
        <div class="stat-card"><div class="stat-card-label">Pending</div><div class="stat-card-value">5</div></div>
      </div>
      <div class="card">
        <div class="card-header"><div class="card-title">Reminder Queue</div></div>
        <div class="card-body p0">
          <table>
            <thead><tr><th>Reminder</th><th>Target</th><th>Schedule</th><th>Channel</th><th>Recipients</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <tr><td><strong>Assignment Due Tomorrow</strong></td><td>Cohort 2026-B</td><td>Jun 5, 6:00 PM</td><td>📧 Email + SMS</td><td>312</td><td><span class="status-badge status-pending">Scheduled</span></td><td><button class="btn-icon">✏️</button></td></tr>
              <tr><td><strong>Live Session in 1 Hour</strong></td><td>Cohort 2026-C</td><td>Jun 5, 1:00 PM</td><td> Email</td><td>189</td><td><span class="status-badge status-pending">Scheduled</span></td><td><button class="btn-icon">✏️</button></td></tr>
              <tr><td><strong>Course Completion Reminder</strong></td><td>All Active Learners</td><td>Jun 6, 9:00 AM</td><td>📧 Email + Push</td><td>2,479</td><td><span class="status-badge status-pending">Scheduled</span></td><td><button class="btn-icon">️</button></td></tr>
              <tr><td><strong>Feedback Request</strong></td><td>Cohort 2026-A</td><td>Jun 4, 10:00 AM</td><td>📧 Email</td><td>245</td><td><span class="status-badge status-completed">Sent</span></td><td><button class="btn-icon">👁</button></td></tr>
              <tr><td><strong>Certificate Ready</strong></td><td>Completed Learners</td><td>Jun 3, 8:00 AM</td><td> Email</td><td>87</td><td><span class="status-badge status-completed">Sent</span></td><td><button class="btn-icon">👁</button></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- LEARNERS -->
    <div class="page" id="page-learners">
      <div class="page-header">
        <div><div class="page-title">Learners</div><div class="page-subtitle">3,624 registered learners</div></div>
        <button class="btn btn-primary" onclick="openModal('learnerModal')">+ Add Learner</button>
      </div>
      <div class="filter-bar">
        <input type="text" placeholder="Search by name or email..." oninput="filterTable('learnersTable',this.value)">
        <select><option>All Programs</option><option>Career Onboarding</option><option>Pre-Service</option></select>
        <select><option>All Status</option><option>Active</option><option>Completed</option><option>Dropped</option></select>
      </div>
      <div class="card">
        <div class="card-body p0">
          <table id="learnersTable">
            <thead><tr><th>Learner</th><th>Email</th><th>Program</th><th>Enrolled</th><th>Progress</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <tr><td><div class="avatar-row"><div class="avatar-sm">AK</div><div><strong>Aisha Koroma</strong><br><small style="color:var(--text-secondary)">Learner ID: NC-2026-001</small></div></div></td><td>aisha.k@natcodev.org</td><td>Career Onboarding</td><td>Jan 15, 2026</td><td><div class="progress-bar" style="width:100px"><div class="progress-fill" style="width:85%"></div></div> 85%</td><td><span class="status-badge status-active">Active</span></td><td><button class="btn-icon">⋮</button></td></tr>
              <tr><td><div class="avatar-row"><div class="avatar-sm">TS</div><div><strong>Tunde Salami</strong><br><small style="color:var(--text-secondary)">Learner ID: NC-2026-002</small></div></div></td><td>tunde.s@natcodev.org</td><td>Career Onboarding</td><td>Jan 15, 2026</td><td><div class="progress-bar" style="width:100px"><div class="progress-fill" style="width:92%"></div></div> 92%</td><td><span class="status-badge status-active">Active</span></td><td><button class="btn-icon"></button></td></tr>
              <tr><td><div class="avatar-row"><div class="avatar-sm">MO</div><div><strong>Miriam Osei</strong><br><small style="color:var(--text-secondary)">Learner ID: NC-2026-003</small></div></div></td><td>miriam.o@natcodev.org</td><td>Pre-Service Accreditation</td><td>Mar 10, 2026</td><td><div class="progress-bar" style="width:100px"><div class="progress-fill" style="width:68%"></div></div> 68%</td><td><span class="status-badge status-active">Active</span></td><td><button class="btn-icon">⋮</button></td></tr>
              <tr><td><div class="avatar-row"><div class="avatar-sm">FN</div><div><strong>Fatima Ndiaye</strong><br><small style="color:var(--text-secondary)">Learner ID: NC-2026-004</small></div></div></td><td>fatima.n@natcodev.org</td><td>Pre-Service Accreditation</td><td>Mar 10, 2026</td><td><div class="progress-bar" style="width:100px"><div class="progress-fill" style="width:100%"></div></div> 100%</td><td><span class="status-badge status-completed">Completed</span></td><td><button class="btn-icon">⋮</button></td></tr>
              <tr><td><div class="avatar-row"><div class="avatar-sm">JB</div><div><strong>James Boateng</strong><br><small style="color:var(--text-secondary)">Learner ID: NC-2026-005</small></div></div></td><td>james.b@natcodev.org</td><td>Field Staff Certification</td><td>Jun 5, 2026</td><td><div class="progress-bar" style="width:100px"><div class="progress-fill" style="width:12%"></div></div> 12%</td><td><span class="status-badge status-active">Active</span></td><td><button class="btn-icon"></button></td></tr>
              <tr><td><div class="avatar-row"><div class="avatar-sm">SK</div><div><strong>Sarah Koffi</strong><br><small style="color:var(--text-secondary)">Learner ID: NC-2026-006</small></div></div></td><td>sarah.k@natcodev.org</td><td>HQ Skills Certification</td><td>Jan 20, 2026</td><td><div class="progress-bar" style="width:100px"><div class="progress-fill" style="width:0%"></div></div> 0%</td><td><span class="status-badge status-cancelled">Dropped</span></td><td><button class="btn-icon">⋮</button></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- CERTIFICATES -->
    <div class="page" id="page-certificates">
      <div class="page-header">
        <div><div class="page-title">Certificates</div><div class="page-subtitle">1,532 certificates issued</div></div>
        <button class="btn btn-primary" onclick="openModal('certificateModal')">+ Issue Certificate</button>
      </div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">Total Issued</div><div class="stat-card-value">1,532</div></div>
        <div class="stat-card"><div class="stat-card-label">Pending Approval</div><div class="stat-card-value">24</div></div>
        <div class="stat-card"><div class="stat-card-label">Verified</div><div class="stat-card-value">1,489</div></div>
        <div class="stat-card"><div class="stat-card-label">Revoked</div><div class="stat-card-value">19</div></div>
      </div>
      <div class="card">
        <div class="card-header"><div class="card-title">Certificate Records</div></div>
        <div class="card-body p0">
          <table>
            <thead><tr><th>Certificate ID</th><th>Learner</th><th>Program</th><th>Issue Date</th><th>Grade</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <tr><td><strong>CERT-2026-0847</strong></td><td>Fatima Ndiaye</td><td>Pre-Service Accreditation</td><td>Jun 1, 2026</td><td>A (95%)</td><td><span class="status-badge status-approved">Verified</span></td><td><button class="btn-icon">👁</button></td></tr>
              <tr><td><strong>CERT-2026-0846</strong></td><td>Tunde Salami</td><td>Career Onboarding</td><td>May 28, 2026</td><td>A+ (98%)</td><td><span class="status-badge status-approved">Verified</span></td><td><button class="btn-icon">👁</button></td></tr>
              <tr><td><strong>CERT-2026-0845</strong></td><td>Aisha Koroma</td><td>Career Onboarding</td><td>May 25, 2026</td><td>B+ (87%)</td><td><span class="status-badge status-approved">Verified</span></td><td><button class="btn-icon">👁</button></td></tr>
              <tr><td><strong>CERT-2026-0844</strong></td><td>Miriam Osei</td><td>Pre-Service Accreditation</td><td>—</td><td>—</td><td><span class="status-badge status-pending">Pending</span></td><td><button class="btn-icon">✏️</button></td></tr>
              <tr><td><strong>CERT-2026-0843</strong></td><td>Sarah Koffi</td><td>HQ Skills Certification</td><td>Apr 15, 2026</td><td>C (72%)</td><td><span class="status-badge status-cancelled">Revoked</span></td><td><button class="btn-icon">👁</button></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- PATHWAYS -->
    <div class="page" id="page-pathways">
      <div class="page-header">
        <div><div class="page-title">Certificate Pathways</div><div class="page-subtitle">Define learning paths that lead to certification</div></div>
        <button class="btn btn-primary" onclick="openModal('pathwayModal')">+ New Pathway</button>
      </div>
      <div class="grid-3">
        <div class="card">
          <div class="card-body">
            <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:12px">
              <div>
                <div style="font-weight:700;font-size:15px">Data Analyst Pathway</div>
                <div style="font-size:12px;color:var(--text-secondary);margin-top:2px">4 courses • 6 months</div>
              </div>
              <span class="status-badge status-active">Active</span>
            </div>
            <div style="margin:16px 0">
              <div style="font-size:12px;color:var(--text-secondary);margin-bottom:6px">Progress</div>
              <div class="progress-bar"><div class="progress-fill" style="width:78%"></div></div>
              <div style="font-size:12px;margin-top:4px">78% of learners complete</div>
            </div>
            <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px">
              <span class="chip">Power BI</span><span class="chip">Python</span><span class="chip">SQL</span><span class="chip">Statistics</span>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--text-secondary);border-top:1px solid var(--border);padding-top:12px">
              <span>👥 620 enrolled</span><span>🏆 485 certified</span>
            </div>
          </div>
        </div>
        <div class="card">
          <div class="card-body">
            <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:12px">
              <div>
                <div style="font-weight:700;font-size:15px">Project Manager Pathway</div>
                <div style="font-size:12px;color:var(--text-secondary);margin-top:2px">3 courses • 4 months</div>
              </div>
              <span class="status-badge status-active">Active</span>
            </div>
            <div style="margin:16px 0">
              <div style="font-size:12px;color:var(--text-secondary);margin-bottom:6px">Progress</div>
              <div class="progress-bar"><div class="progress-fill" style="width:65%"></div></div>
              <div style="font-size:12px;margin-top:4px">65% of learners complete</div>
            </div>
            <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px">
              <span class="chip">Agile</span><span class="chip">Scrum</span><span class="chip">Leadership</span>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--text-secondary);border-top:1px solid var(--border);padding-top:12px">
              <span>👥 312 enrolled</span><span> 203 certified</span>
            </div>
          </div>
        </div>
        <div class="card">
          <div class="card-body">
            <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:12px">
              <div>
                <div style="font-weight:700;font-size:15px">UX Designer Pathway</div>
                <div style="font-size:12px;color:var(--text-secondary);margin-top:2px">5 courses • 8 months</div>
              </div>
              <span class="status-badge status-pending">Draft</span>
            </div>
            <div style="margin:16px 0">
              <div style="font-size:12px;color:var(--text-secondary);margin-bottom:6px">Progress</div>
              <div class="progress-bar"><div class="progress-fill" style="width:42%"></div></div>
              <div style="font-size:12px;margin-top:4px">42% of learners complete</div>
            </div>
            <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px">
              <span class="chip">Research</span><span class="chip">Wireframing</span><span class="chip">Prototyping</span>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--text-secondary);border-top:1px solid var(--border);padding-top:12px">
              <span>👥 278 enrolled</span><span>🏆 117 certified</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- REFUNDS -->
    <div class="page" id="page-refunds">
      <div class="page-header">
        <div><div class="page-title">Refunds</div><div class="page-subtitle">Manage refund requests and processing</div></div>
        <button class="btn btn-primary" onclick="openModal('refundModal')">+ New Refund Request</button>
      </div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">Total Requests</div><div class="stat-card-value">47</div></div>
        <div class="stat-card"><div class="stat-card-label">Pending</div><div class="stat-card-value">12</div></div>
        <div class="stat-card"><div class="stat-card-label">Approved</div><div class="stat-card-value">28</div></div>
        <div class="stat-card"><div class="stat-card-label">Total Amount</div><div class="stat-card-value">$18,450</div></div>
      </div>
      <div class="card">
        <div class="card-header"><div class="card-title">Refund Requests</div></div>
        <div class="card-body p0">
          <table>
            <thead><tr><th>Request ID</th><th>Learner</th><th>Course</th><th>Amount</th><th>Reason</th><th>Date</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <tr><td><strong>REF-2026-0047</strong></td><td>Sarah Koffi</td><td>HQ Skills Certification</td><td>$450</td><td>Course mismatch</td><td>Jun 4, 2026</td><td><span class="status-badge status-pending">Pending</span></td><td><button class="btn btn-sm btn-primary" onclick="showToast('Refund approved')">Approve</button></td></tr>
              <tr><td><strong>REF-2026-0046</strong></td><td>David Mensah</td><td>Python for Data Science</td><td>$380</td><td>Technical issues</td><td>Jun 3, 2026</td><td><span class="status-badge status-pending">Pending</span></td><td><button class="btn btn-sm btn-primary" onclick="showToast('Refund approved')">Approve</button></td></tr>
              <tr><td><strong>REF-2026-0045</strong></td><td>Amina Yusuf</td><td>Agile Project Management</td><td>$320</td><td>Personal reasons</td><td>Jun 2, 2026</td><td><span class="status-badge status-approved">Approved</span></td><td><button class="btn-icon"></button></td></tr>
              <tr><td><strong>REF-2026-0044</strong></td><td>Emeka Obi</td><td>Power BI Essentials</td><td>$290</td><td>Duplicate enrollment</td><td>Jun 1, 2026</td><td><span class="status-badge status-approved">Approved</span></td><td><button class="btn-icon">👁</button></td></tr>
              <tr><td><strong>REF-2026-0043</strong></td><td>Linda Asante</td><td>UX/UI Design</td><td>$410</td><td>Not as described</td><td>May 30, 2026</td><td><span class="status-badge status-rejected">Rejected</span></td><td><button class="btn-icon">👁</button></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- FEEDBACK -->
    <div class="page" id="page-feedback">
      <div class="page-header">
        <div><div class="page-title">Feedback</div><div class="page-subtitle">Learner reviews and course feedback</div></div>
        <button class="btn btn-primary" onclick="showToast('Feedback report exported')"> Export</button>
      </div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">Average Rating</div><div class="stat-card-value" style="color:var(--warning)">⭐ 4.6</div></div>
        <div class="stat-card"><div class="stat-card-label">Total Reviews</div><div class="stat-card-value">2,847</div></div>
        <div class="stat-card"><div class="stat-card-label">5-Star Reviews</div><div class="stat-card-value">71%</div></div>
        <div class="stat-card"><div class="stat-card-label">Response Rate</div><div class="stat-card-value">89%</div></div>
      </div>
      <div class="card">
        <div class="card-header"><div class="card-title">Recent Feedback</div></div>
        <div class="card-body">
          <div style="display:flex;flex-direction:column;gap:16px">
            <div style="padding:16px;background:var(--bg);border-radius:10px">
              <div style="display:flex;justify-content:space-between;margin-bottom:8px">
                <div><strong>Aisha Koroma</strong> <span style="color:var(--text-secondary);font-size:12px">• Power BI Essentials</span></div>
                <div style="color:var(--warning)">⭐⭐⭐⭐⭐</div>
              </div>
              <div style="font-size:13px;color:var(--text-secondary)">"Excellent course! The instructor was very knowledgeable and the hands-on labs were incredibly helpful. Highly recommend."</div>
              <div style="font-size:11px;color:var(--text-secondary);margin-top:8px">Jun 4, 2026 • <span style="color:var(--success)">✓ Responded</span></div>
            </div>
            <div style="padding:16px;background:var(--bg);border-radius:10px">
              <div style="display:flex;justify-content:space-between;margin-bottom:8px">
                <div><strong>Tunde Salami</strong> <span style="color:var(--text-secondary);font-size:12px">• Python for Data Science</span></div>
                <div style="color:var(--warning)">⭐⭐⭐⭐⭐</div>
              </div>
              <div style="font-size:13px;color:var(--text-secondary)">"The capstone project really helped solidify my learning. Would love more advanced content on machine learning."</div>
              <div style="font-size:11px;color:var(--text-secondary);margin-top:8px">Jun 3, 2026 • <span style="color:var(--success)">✓ Responded</span></div>
            </div>
            <div style="padding:16px;background:var(--bg);border-radius:10px">
              <div style="display:flex;justify-content:space-between;margin-bottom:8px">
                <div><strong>Miriam Osei</strong> <span style="color:var(--text-secondary);font-size:12px">• Agile Project Management</span></div>
                <div style="color:var(--warning)">⭐⭐⭐⭐</div>
              </div>
              <div style="font-size:13px;color:var(--text-secondary)">"Good content but the pacing was a bit fast. More practice exercises would be helpful."</div>
              <div style="font-size:11px;color:var(--text-secondary);margin-top:8px">Jun 2, 2026 • <span style="color:var(--warning)">⏳ Pending response</span></div>
            </div>
            <div style="padding:16px;background:var(--bg);border-radius:10px">
              <div style="display:flex;justify-content:space-between;margin-bottom:8px">
                <div><strong>James Boateng</strong> <span style="color:var(--text-secondary);font-size:12px">• Field Staff Certification</span></div>
                <div style="color:var(--warning)">⭐⭐⭐</div>
              </div>
              <div style="font-size:13px;color:var(--text-secondary)">"The content is relevant but the platform had some technical issues during live sessions."</div>
              <div style="font-size:11px;color:var(--text-secondary);margin-top:8px">Jun 1, 2026 • <span style="color:var(--warning)">⏳ Pending response</span></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- REPORTS -->
    <div class="page" id="page-reports">
      <div class="page-header">
        <div><div class="page-title">Reports</div><div class="page-subtitle">Analytics and insights across the academy</div></div>
        <button class="btn btn-primary" onclick="showToast('Generating report...')">📊 Generate Report</button>
      </div>
      <div class="grid-2">
        <div class="card">
          <div class="card-header"><div class="card-title">Enrollment Trends</div></div>
          <div class="card-body">
            <div style="display:flex;align-items:end;gap:8px;height:180px;padding:20px 0">
              <div style="flex:1;background:var(--green-100);border-radius:6px 6px 0 0;height:40%;position:relative"><div style="position:absolute;bottom:-20px;left:50%;transform:translateX(-50%);font-size:10px;color:var(--text-secondary)">Jan</div></div>
              <div style="flex:1;background:var(--green-100);border-radius:6px 6px 0 0;height:55%"><div style="position:absolute;bottom:-20px;left:50%;transform:translateX(-50%);font-size:10px;color:var(--text-secondary)">Feb</div></div>
              <div style="flex:1;background:var(--green-100);border-radius:6px 6px 0 0;height:48%"><div style="position:absolute;bottom:-20px;left:50%;transform:translateX(-50%);font-size:10px;color:var(--text-secondary)">Mar</div></div>
              <div style="flex:1;background:var(--green-100);border-radius:6px 6px 0 0;height:72%"><div style="position:absolute;bottom:-20px;left:50%;transform:translateX(-50%);font-size:10px;color:var(--text-secondary)">Apr</div></div>
              <div style="flex:1;background:var(--green-100);border-radius:6px 6px 0 0;height:65%"><div style="position:absolute;bottom:-20px;left:50%;transform:translateX(-50%);font-size:10px;color:var(--text-secondary)">May</div></div>
              <div style="flex:1;background:var(--green-500);border-radius:6px 6px 0 0;height:85%"><div style="position:absolute;bottom:-20px;left:50%;transform:translateX(-50%);font-size:10px;color:var(--text-secondary)">Jun</div></div>
            </div>
          </div>
        </div>
        <div class="card">
          <div class="card-header"><div class="card-title">Completion by Program</div></div>
          <div class="card-body">
            <div style="display:flex;flex-direction:column;gap:14px">
              <div><div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px"><span>Career Onboarding</span><span style="font-weight:600">85%</span></div><div class="progress-bar"><div class="progress-fill" style="width:85%"></div></div></div>
              <div><div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px"><span>Pre-Service Accreditation</span><span style="font-weight:600">62%</span></div><div class="progress-bar"><div class="progress-fill" style="width:62%"></div></div></div>
              <div><div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px"><span>Field Staff Certification</span><span style="font-weight:600">74%</span></div><div class="progress-bar"><div class="progress-fill" style="width:74%"></div></div></div>
              <div><div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px"><span>HQ Skills Certification</span><span style="font-weight:600">91%</span></div><div class="progress-bar"><div class="progress-fill" style="width:91%"></div></div></div>
              <div><div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px"><span>State Coordinator Ops</span><span style="font-weight:600">45%</span></div><div class="progress-bar"><div class="progress-fill" style="width:45%"></div></div></div>
            </div>
          </div>
        </div>
      </div>
      <div class="card">
        <div class="card-header"><div class="card-title">Available Reports</div></div>
        <div class="card-body p0">
          <table>
            <thead><tr><th>Report</th><th>Description</th><th>Last Generated</th><th>Actions</th></tr></thead>
            <tbody>
              <tr><td><strong>Learner Progress Report</strong></td><td>Individual and cohort progress metrics</td><td>Jun 4, 2026</td><td><button class="btn btn-sm btn-primary" onclick="showToast('Report downloaded')">⬇ Download</button></td></tr>
              <tr><td><strong>Financial Summary</strong></td><td>Revenue, refunds, and outstanding payments</td><td>Jun 1, 2026</td><td><button class="btn btn-sm btn-primary" onclick="showToast('Report downloaded')">⬇ Download</button></td></tr>
              <tr><td><strong>Instructor Performance</strong></td><td>Ratings, session counts, and learner feedback</td><td>May 28, 2026</td><td><button class="btn btn-sm btn-primary" onclick="showToast('Report downloaded')"> Download</button></td></tr>
              <tr><td><strong>Certificate Audit</strong></td><td>All issued certificates with verification status</td><td>May 25, 2026</td><td><button class="btn btn-sm btn-primary" onclick="showToast('Report downloaded')"> Download</button></td></tr>
              <tr><td><strong>Attendance Analytics</strong></td><td>Session attendance patterns and trends</td><td>May 20, 2026</td><td><button class="btn btn-sm btn-primary" onclick="showToast('Report downloaded')">⬇ Download</button></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div>
</div>

<!-- MODALS -->
<div class="modal-overlay" id="programModal">
  <div class="modal">
    <div class="modal-header"><div class="modal-title">Create New Program</div><button class="btn-icon" onclick="closeModal('programModal')">✕</button></div>
    <div class="modal-body">
      <div class="form-group"><label class="form-label">Program Name</label><input class="form-input" placeholder="e.g. Career Onboarding Program"></div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Duration</label><input class="form-input" placeholder="e.g. 12 weeks"></div>
        <div class="form-group"><label class="form-label">Max Learners</label><input class="form-input" type="number" placeholder="500"></div>
      </div>
      <div class="form-group"><label class="form-label">Description</label><textarea class="form-textarea" placeholder="Program description..."></textarea></div>
      <div class="form-group"><label class="form-label">Certificate Pathway</label><select class="form-select"><option>Select pathway...</option><option>Data Analyst</option><option>Project Manager</option><option>UX Designer</option></select></div>
    </div>
    <div class="modal-footer"><button class="btn btn-secondary" onclick="closeModal('programModal')">Cancel</button><button class="btn btn-primary" onclick="closeModal('programModal');showToast('Program created successfully')">Create Program</button></div>
  </div>
</div>

<div class="modal-overlay" id="courseModal">
  <div class="modal">
    <div class="modal-header"><div class="modal-title">Create New Course</div><button class="btn-icon" onclick="closeModal('courseModal')">✕</button></div>
    <div class="modal-body">
      <div class="form-group"><label class="form-label">Course Title</label><input class="form-input" placeholder="e.g. Power BI Essentials"></div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Program</label><select class="form-select"><option>Career Onboarding</option><option>Pre-Service Accreditation</option><option>Field Staff Certification</option></select></div>
        <div class="form-group"><label class="form-label">Instructor</label><select class="form-select"><option>Dr. Adebayo</option><option>Prof. Mensah</option><option>Ms. Okonkwo</option></select></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Duration (hours)</label><input class="form-input" type="number" placeholder="40"></div>
        <div class="form-group"><label class="form-label">Price ($)</label><input class="form-input" type="number" placeholder="299"></div>
      </div>
      <div class="form-group"><label class="form-label">Description</label><textarea class="form-textarea" placeholder="Course description..."></textarea></div>
    </div>
    <div class="modal-footer"><button class="btn btn-secondary" onclick="closeModal('courseModal')">Cancel</button><button class="btn btn-primary" onclick="closeModal('courseModal');showToast('Course created')">Create Course</button></div>
  </div>
</div>

<div class="modal-overlay" id="lessonModal">
  <div class="modal">
    <div class="modal-header"><div class="modal-title">Add New Lesson</div><button class="btn-icon" onclick="closeModal('lessonModal')">✕</button></div>
    <div class="modal-body">
      <div class="form-group"><label class="form-label">Lesson Title</label><input class="form-input" placeholder="e.g. Introduction to Power BI"></div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Course</label><select class="form-select"><option>Power BI Essentials</option><option>Python for Data Science</option></select></div>
        <div class="form-group"><label class="form-label">Type</label><select class="form-select"><option>Video</option><option>Reading</option><option>Quiz</option><option>Assignment</option></select></div>
      </div>
      <div class="form-group"><label class="form-label">Duration (minutes)</label><input class="form-input" type="number" placeholder="30"></div>
      <div class="form-group"><label class="form-label">Content / Notes</label><textarea class="form-textarea" placeholder="Lesson content..."></textarea></div>
    </div>
    <div class="modal-footer"><button class="btn btn-secondary" onclick="closeModal('lessonModal')">Cancel</button><button class="btn btn-primary" onclick="closeModal('lessonModal');showToast('Lesson added')">Add Lesson</button></div>
  </div>
</div>

<div class="modal-overlay" id="assessmentModal">
  <div class="modal">
    <div class="modal-header"><div class="modal-title">Create Assessment</div><button class="btn-icon" onclick="closeModal('assessmentModal')">✕</button></div>
    <div class="modal-body">
      <div class="form-group"><label class="form-label">Assessment Title</label><input class="form-input" placeholder="e.g. Mid-Term Exam"></div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Course</label><select class="form-select"><option>Power BI Essentials</option><option>Python for Data Science</option></select></div>
        <div class="form-group"><label class="form-label">Type</label><select class="form-select"><option>Quiz</option><option>Exam</option><option>Assignment</option><option>Project</option></select></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Number of Questions</label><input class="form-input" type="number" placeholder="25"></div>
        <div class="form-group"><label class="form-label">Duration (minutes)</label><input class="form-input" type="number" placeholder="60"></div>
      </div>
      <div class="form-group"><label class="form-label">Passing Score (%)</label><input class="form-input" type="number" placeholder="70"></div>
    </div>
    <div class="modal-footer"><button class="btn btn-secondary" onclick="closeModal('assessmentModal')">Cancel</button><button class="btn btn-primary" onclick="closeModal('assessmentModal');showToast('Assessment created')">Create</button></div>
  </div>
</div>

<div class="modal-overlay" id="cohortModal">
  <div class="modal">
    <div class="modal-header"><div class="modal-title">Create New Cohort</div><button class="btn-icon" onclick="closeModal('cohortModal')">✕</button></div>
    <div class="modal-body">
      <div class="form-group"><label class="form-label">Cohort Name</label><input class="form-input" placeholder="e.g. Cohort 2026-F"></div>
      <div class="form-group"><label class="form-label">Program</label><select class="form-select"><option>Career Onboarding</option><option>Pre-Service Accreditation</option><option>Field Staff Certification</option></select></div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Start Date</label><input class="form-input" type="date"></div>
        <div class="form-group"><label class="form-label">End Date</label><input class="form-input" type="date"></div>
      </div>
      <div class="form-group"><label class="form-label">Max Learners</label><input class="form-input" type="number" placeholder="300"></div>
    </div>
    <div class="modal-footer"><button class="btn btn-secondary" onclick="closeModal('cohortModal')">Cancel</button><button class="btn btn-primary" onclick="closeModal('cohortModal');showToast('Cohort created')">Create Cohort</button></div>
  </div>
</div>

<div class="modal-overlay" id="instructorModal">
  <div class="modal">
    <div class="modal-header"><div class="modal-title">Add Instructor</div><button class="btn-icon" onclick="closeModal('instructorModal')">✕</button></div>
    <div class="modal-body">
      <div class="form-row">
        <div class="form-group"><label class="form-label">First Name</label><input class="form-input"></div>
        <div class="form-group"><label class="form-label">Last Name</label><input class="form-input"></div>
      </div>
      <div class="form-group"><label class="form-label">Email</label><input class="form-input" type="email"></div>
      <div class="form-group"><label class="form-label">Specialization</label><input class="form-input" placeholder="e.g. Data Science"></div>
      <div class="form-group"><label class="form-label">Bio</label><textarea class="form-textarea"></textarea></div>
    </div>
    <div class="modal-footer"><button class="btn btn-secondary" onclick="closeModal('instructorModal')">Cancel</button><button class="btn btn-primary" onclick="closeModal('instructorModal');showToast('Instructor added')">Add Instructor</button></div>
  </div>
</div>

<div class="modal-overlay" id="reminderModal">
  <div class="modal">
    <div class="modal-header"><div class="modal-title">Create Reminder</div><button class="btn-icon" onclick="closeModal('reminderModal')">✕</button></div>
    <div class="modal-body">
      <div class="form-group"><label class="form-label">Reminder Title</label><input class="form-input" placeholder="e.g. Assignment Due Tomorrow"></div>
      <div class="form-group"><label class="form-label">Target Audience</label><select class="form-select"><option>All Learners</option><option>Specific Cohort</option><option>Specific Course</option></select></div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Schedule Date</label><input class="form-input" type="date"></div>
        <div class="form-group"><label class="form-label">Time</label><input class="form-input" type="time"></div>
      </div>
      <div class="form-group"><label class="form-label">Channel</label><select class="form-select"><option>Email</option><option>SMS</option><option>Push Notification</option><option>Email + SMS</option></select></div>
      <div class="form-group"><label class="form-label">Message</label><textarea class="form-textarea" placeholder="Reminder message..."></textarea></div>
    </div>
    <div class="modal-footer"><button class="btn btn-secondary" onclick="closeModal('reminderModal')">Cancel</button><button class="btn btn-primary" onclick="closeModal('reminderModal');showToast('Reminder scheduled')">Schedule</button></div>
  </div>
</div>

<div class="modal-overlay" id="learnerModal">
  <div class="modal">
    <div class="modal-header"><div class="modal-title">Add Learner</div><button class="btn-icon" onclick="closeModal('learnerModal')">✕</button></div>
    <div class="modal-body">
      <div class="form-row">
        <div class="form-group"><label class="form-label">First Name</label><input class="form-input"></div>
        <div class="form-group"><label class="form-label">Last Name</label><input class="form-input"></div>
      </div>
      <div class="form-group"><label class="form-label">Email</label><input class="form-input" type="email"></div>
      <div class="form-group"><label class="form-label">Program</label><select class="form-select"><option>Career Onboarding</option><option>Pre-Service Accreditation</option><option>Field Staff Certification</option></select></div>
      <div class="form-group"><label class="form-label">Cohort</label><select class="form-select"><option>Cohort 2026-B</option><option>Cohort 2026-C</option><option>Cohort 2026-D</option></select></div>
    </div>
    <div class="modal-footer"><button class="btn btn-secondary" onclick="closeModal('learnerModal')">Cancel</button><button class="btn btn-primary" onclick="closeModal('learnerModal');showToast('Learner enrolled')">Enroll Learner</button></div>
  </div>
</div>

<div class="modal-overlay" id="certificateModal">
  <div class="modal">
    <div class="modal-header"><div class="modal-title">Issue Certificate</div><button class="btn-icon" onclick="closeModal('certificateModal')">✕</button></div>
    <div class="modal-body">
      <div class="form-group"><label class="form-label">Learner</label><select class="form-select"><option>Fatima Ndiaye</option><option>Tunde Salami</option><option>Aisha Koroma</option></select></div>
      <div class="form-group"><label class="form-label">Program</label><select class="form-select"><option>Pre-Service Accreditation</option><option>Career Onboarding</option></select></div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Grade</label><input class="form-input" placeholder="e.g. A (95%)"></div>
        <div class="form-group"><label class="form-label">Issue Date</label><input class="form-input" type="date"></div>
      </div>
      <div class="form-group"><label class="form-label">Certificate ID</label><input class="form-input" value="CERT-2026-0848" readonly style="background:var(--bg)"></div>
    </div>
    <div class="modal-footer"><button class="btn btn-secondary" onclick="closeModal('certificateModal')">Cancel</button><button class="btn btn-primary" onclick="closeModal('certificateModal');showToast('Certificate issued')">Issue Certificate</button></div>
  </div>
</div>

<div class="modal-overlay" id="pathwayModal">
  <div class="modal">
    <div class="modal-header"><div class="modal-title">Create Certificate Pathway</div><button class="btn-icon" onclick="closeModal('pathwayModal')">✕</button></div>
    <div class="modal-body">
      <div class="form-group"><label class="form-label">Pathway Name</label><input class="form-input" placeholder="e.g. Data Analyst Pathway"></div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Duration</label><input class="form-input" placeholder="6 months"></div>
        <div class="form-group"><label class="form-label">Certificate Name</label><input class="form-input" placeholder="Certified Data Analyst"></div>
      </div>
      <div class="form-group"><label class="form-label">Required Courses</label><select class="form-select" multiple style="min-height:100px"><option>Power BI Essentials</option><option>Python for Data Science</option><option>SQL Fundamentals</option><option>Statistics 101</option></select></div>
      <div class="form-group"><label class="form-label">Description</label><textarea class="form-textarea"></textarea></div>
    </div>
    <div class="modal-footer"><button class="btn btn-secondary" onclick="closeModal('pathwayModal')">Cancel</button><button class="btn btn-primary" onclick="closeModal('pathwayModal');showToast('Pathway created')">Create Pathway</button></div>
  </div>
</div>

<div class="modal-overlay" id="refundModal">
  <div class="modal">
    <div class="modal-header"><div class="modal-title">New Refund Request</div><button class="btn-icon" onclick="closeModal('refundModal')">✕</button></div>
    <div class="modal-body">
      <div class="form-group"><label class="form-label">Learner</label><select class="form-select"><option>Sarah Koffi</option><option>David Mensah</option><option>Amina Yusuf</option></select></div>
      <div class="form-group"><label class="form-label">Course</label><select class="form-select"><option>HQ Skills Certification</option><option>Python for Data Science</option><option>Agile Project Management</option></select></div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Amount ($)</label><input class="form-input" type="number" placeholder="450"></div>
        <div class="form-group"><label class="form-label">Reason</label><select class="form-select"><option>Course mismatch</option><option>Technical issues</option><option>Personal reasons</option><option>Duplicate enrollment</option></select></div>
      </div>
      <div class="form-group"><label class="form-label">Notes</label><textarea class="form-textarea"></textarea></div>
    </div>
    <div class="modal-footer"><button class="btn btn-secondary" onclick="closeModal('refundModal')">Cancel</button><button class="btn btn-primary" onclick="closeModal('refundModal');showToast('Refund request submitted')">Submit Request</button></div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
function navigateTo(page) {
  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  const pageEl = document.getElementById('page-' + page);
  if (pageEl) pageEl.classList.add('active');
  const navEl = document.querySelector(`.nav-item[data-page="${page}"]`);
  if (navEl) navEl.classList.add('active');
  window.scrollTo(0, 0);
}

document.querySelectorAll('.nav-item').forEach(item => {
  item.addEventListener('click', () => {
    const page = item.getAttribute('data-page');
    if (page) navigateTo(page);
  });
});

function openModal(id) { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }

document.querySelectorAll('.modal-overlay').forEach(overlay => {
  overlay.addEventListener('click', e => {
    if (e.target === overlay) overlay.classList.remove('active');
  });
});

function showToast(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.style.display = 'block';
  setTimeout(() => t.style.display = 'none', 2500);
}

function filterTable(tableId, query) {
  const table = document.getElementById(tableId);
  if (!table) return;
  const rows = table.querySelectorAll('tbody tr');
  const q = query.toLowerCase();
  rows.forEach(row => {
    row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
}

function filterStatus(tableId, status) {
  const table = document.getElementById(tableId);
  if (!table) return;
  const rows = table.querySelectorAll('tbody tr');
  rows.forEach(row => {
    if (!status) { row.style.display = ''; return; }
    row.style.display = row.textContent.includes(status) ? '' : 'none';
  });
}

function switchTab(el, tabId) {
  el.parentElement.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
  el.classList.add('active');
}

function filterLessons() {
  const val = document.getElementById('lessonCourseFilter').value;
  const rows = document.querySelectorAll('#lessonsTable tbody tr');
  rows.forEach(row => {
    if (!val) { row.style.display = ''; return; }
    row.style.display = row.textContent.includes(val) ? '' : 'none';
  });
}

document.getElementById('globalSearch').addEventListener('input', function() {
  const q = this.value.toLowerCase();
  if (q.length < 2) return;
  const pages = ['programs','courses','lessons','assessments','cohorts','instructors','attendance','reminders','learners','certificates','pathways','refunds','feedback','reports'];
  for (const p of pages) {
    const el = document.getElementById('page-' + p);
    if (el && el.textContent.toLowerCase().includes(q)) {
      navigateTo(p);
      break;
    }
  }
});
</script>
</body>
</html>

