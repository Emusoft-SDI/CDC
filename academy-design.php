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
<?php require 'academy-design-partials/sidebar.php'; ?>

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
    <?php require 'academy-design-partials/dashboard.php'; ?>

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
    <?php require 'academy-design-partials/pathways.php'; ?>

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
    <?php require 'academy-design-partials/feedback.php'; ?>

    <!-- REPORTS -->
    <?php require 'academy-design-partials/reports.php'; ?>

  </div>
</div>

<!-- MODALS -->
<?php require 'academy-design-partials/modals.php'; ?>
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

