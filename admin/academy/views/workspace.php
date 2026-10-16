<?php defined('NATCODEV_ACADEMY_WORKSPACE_VIEW') || exit; ?>
<style>
  .acad-content { min-width:0; }
  /* Course grid */
  .acad-grid-toolbar{display:flex;gap:10px;align-items:center;flex-wrap:wrap;background:#fff;border:1px solid var(--line);border-radius:10px;padding:12px;margin:14px 0}
  .acad-grid-toolbar input[type=search],.acad-grid-toolbar select{border:1px solid var(--line);border-radius:8px;padding:9px 11px;font:inherit;background:#fff;color:#102033}
  .acad-grid-toolbar input[type=search]{flex:1;min-width:220px}
  .acad-grid-toolbar .btn{border:0;border-radius:8px;padding:9px 14px;font-weight:800;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:6px;font-size:.88rem}
  .acad-grid-toolbar .btn-primary{background:#0ea765;color:#fff}
  .acad-grid-toolbar .btn-primary:hover{background:#0b8a54}
  .acad-grid-toolbar .btn-ghost{background:#fff;color:#102033;border:1px solid var(--line)}
  .acad-grid-count{margin-left:auto;font-weight:800;color:var(--muted);font-size:.85rem}
  .acad-grid-wrap{overflow-x:auto;border:1px solid var(--line);border-radius:10px;background:#fff;margin-bottom:16px}
  table.acad-table, table.acad-course-table, table.acad-lesson-table, .acad-grid-wrap > table {
    width: 100%;
    border-collapse: collapse;
    font-size: .9rem;
    min-width: 1050px;
    display: table !important;
  }
  .acad-grid-wrap th, .acad-grid-wrap td,
  table.acad-course-table th, table.acad-course-table td,
  table.acad-lesson-table th, table.acad-lesson-table td {
    padding: 11px 12px;
    text-align: left;
    border-bottom: 1px solid var(--line);
    vertical-align: middle;
  }
  .acad-grid-wrap thead th,
  table.acad-course-table thead th,
  table.acad-lesson-table thead th {
    background: #063f24;
    color: #fff;
    font-weight: 800;
    white-space: nowrap;
  }
  .acad-grid-wrap thead th a,
  table.acad-course-table thead th a,
  table.acad-lesson-table thead th a {
    color: #fff;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
  }
  .acad-grid-wrap thead th a:hover,
  table.acad-course-table thead th a:hover,
  table.acad-lesson-table thead th a:hover {
    color: #9ff0c6;
  }
  .acad-grid-wrap tbody tr:nth-child(even),
  table.acad-course-table tbody tr:nth-child(even),
  table.acad-lesson-table tbody tr:nth-child(even) {
    background: #f7fbf8;
  }
  .acad-grid-wrap tbody tr:hover,
  table.acad-course-table tbody tr:hover,
  table.acad-lesson-table tbody tr:hover {
    background: #eef8f1;
  }
  .acad-grid-actions{display:flex;gap:6px;flex-wrap:nowrap;white-space:nowrap;align-items:center}

  .acad-grid-status-form{display:inline}
  .acad-grid-status-form select{border:1px solid var(--line);border-radius:7px;padding:6px 8px;font-weight:800;font-size:.78rem;background:#fff;color:#102033;cursor:pointer}
  .acad-btn{display:inline-flex;align-items:center;gap:5px;border:1px solid var(--line);background:#fff;color:#102033;border-radius:7px;padding:6px 9px;font-weight:800;font-size:.78rem;cursor:pointer;text-decoration:none;white-space:nowrap}
  .acad-btn:hover{background:#f0f7f2}
  .acad-btn.view{color:#0b6bcb;border-color:#bcd6f5}
  .acad-btn.edit{color:#0ea765;border-color:#a9e3c6}
  .acad-btn.delete{color:#d92d20;border-color:#f3b4ae}
  .acad-btn.delete:hover{background:#fee4e2}
  .acad-status{display:inline-block;padding:3px 10px;border-radius:999px;font-size:.72rem;font-weight:900;text-transform:capitalize}
  .acad-status.ok{background:#def7ec;color:#067647}
  .acad-status.warn{background:#fef0c7;color:#b54708}
  .acad-status.bad{background:#fee4e2;color:#b42318}
  .acad-status.info{background:#e0eaff;color:#3538cd}
  .acad-price-free{display:inline-block;padding:2px 9px;border-radius:999px;background:#def7ec;color:#067647;font-weight:900;font-size:.74rem}
  .acad-grid-empty{text-align:center;padding:28px;color:var(--muted);font-weight:700}
  .acad-grid-pager{display:flex;gap:8px;align-items:center;justify-content:flex-end;margin-top:12px;flex-wrap:wrap}
  .acad-grid-pager .pg{display:inline-flex;align-items:center;border:1px solid var(--line);background:#fff;border-radius:8px;padding:7px 11px;font-weight:800;text-decoration:none;color:#102033;font-size:.85rem}
  .acad-grid-pager .pg:hover{background:#f0f7f2}
  .acad-grid-pager .pg.active{background:#063f24;color:#fff;border-color:#063f24}
  .acad-grid-pager .pg.disabled{opacity:.45;pointer-events:none}
  .acad-grid-pager .pg-info{margin-right:auto;font-weight:800;color:var(--muted);font-size:.85rem}
  .acad-grid-pager .pg-dots{color:var(--muted);font-weight:800}
  .course-view-data h3{margin:0 0 2px;font-size:1.25rem;color:#0b1f16}
  .course-view-data dl{display:grid;grid-template-columns:150px 1fr;gap:8px 14px;margin:14px 0}
  .course-view-data dt{font-weight:800;color:#063f24}
  .course-view-data dd{margin:0}
  .course-view-data .cv-block{margin-top:12px}
  .course-view-data .cv-block h4{margin:0 0 4px;font-size:.82rem;text-transform:uppercase;letter-spacing:.04em;color:#063f24}
  .course-view-data .cv-block p{margin:0;color:#334155;line-height:1.5}
  .course-modal-backdrop{position:fixed;inset:0;background:rgba(6,32,20,.55);display:none;align-items:flex-start;justify-content:center;padding:40px 16px;z-index:9999;overflow:auto}
  .course-modal-backdrop.open{display:flex}
  .course-modal{background:#fff;border-radius:12px;max-width:740px;width:100%;padding:24px;box-shadow:0 24px 60px rgba(0,0,0,.3)}
  .course-modal .close-row{display:flex;justify-content:flex-end;margin-top:18px;border-top:1px solid var(--line);padding-top:14px}
</style>
<?php $academyAdminUser = current_user($pdo) ?: []; $academyAdminName = (string) (($academyAdminUser['name'] ?? '') ?: 'Academy Admin'); $academyAdminRole = ucwords(str_replace('_', ' ', (string) (($academyAdminUser['platform_role'] ?? '') ?: ($academyAdminUser['role'] ?? 'admin')))); ?>

<div class="acad-workspace">
  <aside class="acad-rail" aria-label="Academy workspace navigation">
    <div class="acad-brand"><img src="<?= e(app_admin_logo_url()) ?>" alt="NATCODEV"><div><strong>NATCODEV</strong><small>Academy Workspace</small></div></div>
    <div class="acad-label">Workspace Hub</div>
    <nav class="acad-nav">
      <a href="../index.php"><span><i class="fa-solid fa-house"></i> Workspace Hub</span></a>
    </nav>
    <div class="acad-label">Academy Workspace</div>
    <nav class="acad-nav">
      <a class="<?= $tab === 'overview' ? 'active' : '' ?>" href="index.php?page=overview"><span><i class="fa-solid fa-table-columns"></i> Overview</span></a>
      <a class="<?= $tab === 'homepage' ? 'active' : '' ?>" href="index.php?page=homepage"><span><i class="fa-solid fa-palette"></i> Homepage</span></a>
      <a class="<?= $tab === 'programs' ? 'active' : '' ?>" href="index.php?page=programs"><span><i class="fa-solid fa-layer-group"></i> Programs</span><span class="acad-count"><?= (int) $stats['programs'] ?></span></a>
      <a class="<?= $tab === 'courses' ? 'active' : '' ?>" href="index.php?page=courses"><span><i class="fa-solid fa-book-open"></i> Courses</span><span class="acad-count"><?= (int) $stats['courses'] ?></span></a>
    </nav>
    <div class="acad-label">Content &amp; Assessment</div>
    <nav class="acad-nav">
      <a class="<?= $tab === 'lessons' ? 'active' : '' ?>" href="index.php?page=lessons"><span><i class="fa-solid fa-file-lines"></i> Lessons &amp; Materials</span><span class="acad-count"><?= (int) $stats['lessons'] ?></span></a>
      <a class="<?= $tab === 'assessments' ? 'active' : '' ?>" href="index.php?page=assessments"><span><i class="fa-solid fa-clipboard-question"></i> Assessments</span></a>
      <a class="<?= $tab === 'certificate_groups' ? 'active' : '' ?>" href="index.php?page=certificate-groups"><span><i class="fa-solid fa-route"></i> Pathways</span></a>
    </nav>
    <div class="acad-label">Delivery</div>
    <nav class="acad-nav">
      <a class="<?= $tab === 'calendar' ? 'active' : '' ?>" href="index.php?page=calendar"><span><i class="fa-regular fa-calendar"></i> Cohorts</span><span class="acad-count"><?= (int) $stats['cohorts'] ?></span></a>
      <a class="<?= $tab === 'instructors' ? 'active' : '' ?>" href="index.php?page=instructors"><span><i class="fa-solid fa-chalkboard-user"></i> Instructors</span></a>
      <a class="<?= $tab === 'attendance' ? 'active' : '' ?>" href="index.php?page=attendance"><span><i class="fa-solid fa-clipboard-check"></i> Attendance</span><span class="acad-count"><?= (int) $stats['attendance'] ?></span></a>
      <a class="<?= $tab === 'reminders' ? 'active' : '' ?>" href="index.php?page=reminders"><span><i class="fa-solid fa-bell"></i> Reminders</span></a>
    </nav>
    <div class="acad-label">Learners &amp; Outcomes</div>
    <nav class="acad-nav">
      <a class="<?= $tab === 'enrollments' ? 'active' : '' ?>" href="index.php?page=enrollments"><span><i class="fa-solid fa-users"></i> Learners</span><span class="acad-count"><?= (int) $stats['enrollments'] ?></span></a>
      <a class="<?= $tab === 'certificates' ? 'active' : '' ?>" href="index.php?page=certificates"><span><i class="fa-solid fa-certificate"></i> Certificates</span><span class="acad-count warn"><?= (int) $stats['pending_certificates'] ?></span></a>
      <a class="<?= $tab === 'refunds' ? 'active' : '' ?>" href="index.php?page=refunds"><span><i class="fa-solid fa-rotate-left"></i> Refunds</span><span class="acad-count warn"><?= (int) $stats['pending_refunds'] ?></span></a>
      <a class="<?= $tab === 'feedback' ? 'active' : '' ?>" href="index.php?page=feedback"><span><i class="fa-solid fa-star-half-stroke"></i> Feedback</span><span class="acad-count"><?= (int) $stats['feedback'] ?></span></a>
    </nav>
    <div class="acad-label">Reporting</div>
    <nav class="acad-nav">
      <a class="<?= $tab === 'reports' ? 'active' : '' ?>" href="index.php?page=reports"><span><i class="fa-solid fa-chart-line"></i> Reports</span></a>
    </nav>
    <div class="acad-label">Quick Links</div>
    <nav class="acad-nav">
      <a href="index.php?page=courses"><span><i class="fa-solid fa-plus"></i> Add Course</span></a>
      <a href="index.php?page=calendar"><span><i class="fa-solid fa-calendar-plus"></i> Schedule Cohort</span></a>
      <a href="index.php?page=certificates"><span><i class="fa-solid fa-award"></i> Review Certificates</span></a>
      <a href="../../academy/index.php" target="_blank" rel="noopener"><span><i class="fa-solid fa-arrow-up-right-from-square"></i> Public Academy</span></a>
    </nav>
  </aside>
  <div class="acad-content">
    <?php if (!empty($_GET['message'])): ?><div class="notice ok"><?= e((string) $_GET['message']) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>
    <div class="acad-head">
      <div>
        <h2>NATCODEV Academy</h2>
        <p>Manage learning programs, courses, enrollments, certificates, payments, cohorts, and learner outcomes.</p>
      </div>
      <div class="acad-toolstrip">
        <span class="acad-tool" title="Pending Certificates"><i class="fa-regular fa-bell"></i> <?= (int) $stats['pending_certificates'] ?> pending</span>
        <span class="acad-tool" title="Academy Collections"><i class="fa-solid fa-wallet"></i> <?= e(academy_admin_money($academyCollections)) ?></span>
        <span class="acad-tool" title="Current Week"><i class="fa-regular fa-calendar"></i> <?= e(date('M j')) ?> - <?= e(date('M j, Y', strtotime('+6 days'))) ?></span>
        <a class="acad-tool" href="../../academy/index.php" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square"></i> Public Academy</a>
      </div>
    </div>

<?php $academyNavGroups = [
    'Content Tools' => ['lessons' => 'Lessons & Materials', 'assessments' => 'Assessments', 'certificate_groups' => 'Certificate Groups'],
    'Delivery Tools' => ['calendar' => 'Calendar & Cohorts', 'instructors' => 'Instructors', 'attendance' => 'Attendance', 'reminders' => 'Reminders'],
    'Learners' => ['enrollments' => 'Enrollments', 'certificates' => 'Certificates', 'refunds' => 'Refunds', 'feedback' => 'Feedback'],
];
$academyPageMap = [
    'programs' => ACADEMY_ADMIN_ROUTE_BASE . '?page=programs',
    'courses' => ACADEMY_ADMIN_ROUTE_BASE . '?page=courses',
    'lessons' => ACADEMY_ADMIN_ROUTE_BASE . '?page=lessons',
    'assessments' => ACADEMY_ADMIN_ROUTE_BASE . '?page=assessments',
    'certificate_groups' => ACADEMY_ADMIN_ROUTE_BASE . '?page=certificate-groups',
    'calendar' => ACADEMY_ADMIN_ROUTE_BASE . '?page=calendar',
    'instructors' => ACADEMY_ADMIN_ROUTE_BASE . '?page=instructors',
    'attendance' => ACADEMY_ADMIN_ROUTE_BASE . '?page=attendance',
    'reminders' => ACADEMY_ADMIN_ROUTE_BASE . '?page=reminders',
    'enrollments' => ACADEMY_ADMIN_ROUTE_BASE . '?page=enrollments',
    'certificates' => ACADEMY_ADMIN_ROUTE_BASE . '?page=certificates',
    'refunds' => ACADEMY_ADMIN_ROUTE_BASE . '?page=refunds',
    'feedback' => ACADEMY_ADMIN_ROUTE_BASE . '?page=feedback',
    'reports' => ACADEMY_ADMIN_ROUTE_BASE . '?page=reports',
]; ?>
<details class="academy-tabs academy-create" aria-label="Academy advanced module navigation">
  <summary>Advanced Academy Tools</summary>
  <?php foreach ($academyNavGroups as $groupLabel => $items): ?>
    <div class="academy-tab-row">
      <div class="academy-tab-label"><?= e($groupLabel) ?></div>
      <?php foreach ($items as $key => $label): ?><a class="<?= $tab === $key ? 'active' : '' ?>" href="<?= e($academyPageMap[$key] ?? 'academy.php') ?>"><?= e($label) ?></a><?php endforeach; ?>
    </div>
  <?php endforeach; ?>
</details>
<script>
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.academy-split > form.panel, .academy-split > .panel').forEach(function (panel) {
    if (panel.closest('.academy-create')) return;
    var title = panel.querySelector('h2') ? panel.querySelector('h2').textContent.trim() : 'Tools';
    var details = document.createElement('details');
    details.className = 'panel academy-create';
    var summary = document.createElement('summary');
    summary.textContent = 'Open ' + title;
    panel.parentNode.insertBefore(details, panel);
    panel.classList.remove('panel');
    details.appendChild(summary);
    details.appendChild(panel);
  });
});
</script>

<?php if ($tab === 'overview'): ?>
  <section class="acad-kpis">
    <div class="acad-kpi"><div><small>Active Courses</small><strong><?= (int) $stats['active_courses'] ?></strong><span><?= (int) $stats['courses'] ?> total courses</span></div><div class="acad-icon"><i class="fa-solid fa-book-open"></i></div></div>
    <div class="acad-kpi"><div><small>Learner Enrollments</small><strong><?= (int) $stats['enrollments'] ?></strong><span><?= (int) $stats['completed'] ?> completed</span></div><div class="acad-icon blue"><i class="fa-solid fa-users"></i></div></div>
    <div class="acad-kpi"><div><small>Completion Rate</small><strong><?= number_format((float) $stats['completed_percent'], 1) ?>%</strong><span>Across Academy learners</span></div><div class="acad-icon"><i class="fa-solid fa-circle-check"></i></div></div>
    <div class="acad-kpi"><div><small>Certificates</small><strong><?= (int) $stats['certificates'] ?></strong><span><?= (int) $stats['pending_certificates'] ?> pending review</span></div><div class="acad-icon purple"><i class="fa-solid fa-certificate"></i></div></div>
    <div class="acad-kpi"><div><small>Collections</small><strong><?= e(academy_admin_money($academyCollections)) ?></strong><span><?= e(academy_admin_money($academyOutstanding)) ?> outstanding</span></div><div class="acad-icon"><i class="fa-solid fa-wallet"></i></div></div>
    <div class="acad-kpi"><div><small>Refund Requests</small><strong><?= (int) $stats['pending_refunds'] ?></strong><span>Academy payment support</span></div><div class="acad-icon red"><i class="fa-solid fa-rotate-left"></i></div></div>
  </section>

  <section class="acad-overview-grid">
    <div class="acad-panel">
      <div class="acad-panel-head"><h3>Enrollment & Completion Trend</h3><a href="index.php?page=reports">View Report</a></div>
      <div class="acad-chart"><?php foreach ([44, 58, 52, 70, 86, 78, 92] as $height): ?><div class="acad-bar" style="height:<?= $height ?>%"></div><?php endforeach; ?></div>
      <div class="acad-list-row"><span>Total Enrollments</span><strong><?= (int) $stats['enrollments'] ?></strong></div>
      <div class="acad-list-row"><span>Completed Learning Journeys</span><strong><?= (int) $stats['completed'] ?></strong></div>
    </div>
    <div class="acad-panel">
      <div class="acad-panel-head"><h3>Recent Enrollments</h3><a href="index.php?page=enrollments">View All</a></div>
      <table class="acad-table"><thead><tr><th>Learner</th><th>Course</th><th>Payment</th><th>Progress</th></tr></thead><tbody>
        <?php foreach ($recentEnrollments as $row): ?><tr><td><strong><?= e((string) $row['user_name']) ?></strong><br><small><?= e((string) $row['email']) ?></small></td><td><?= e((string) $row['course_title']) ?></td><td><span class="acad-badge <?= e(academy_admin_badge((string) $row['payment_status'])) ?>"><?= e((string) $row['payment_status']) ?></span></td><td><?= (int) $row['progress_percent'] ?>%</td></tr><?php endforeach; ?>
        <?php if (!$recentEnrollments): ?><tr><td colspan="4">No enrollments yet.</td></tr><?php endif; ?>
      </tbody></table>
    </div>
    <div class="acad-panel">
      <div class="acad-panel-head"><h3>Public Academy</h3><a href="../../academy/index.php" target="_blank">Open</a></div>
      <div class="acad-list-row"><span>Programs</span><strong><?= (int) $stats['programs'] ?></strong></div>
      <div class="acad-list-row"><span>Lessons & Materials</span><strong><?= (int) $stats['lessons'] ?></strong></div>
      <div class="acad-list-row"><span>Assessments</span><strong><?= count($assessments) ?></strong></div>
      <div class="acad-list-row"><span>Feedback</span><strong><?= (int) $stats['feedback'] ?></strong></div>
      <a class="button secondary" style="width:100%;margin-top:12px" href="../../academy/index.php" target="_blank">View Learner Entry Point</a>
    </div>
  </section>

  <section class="acad-row">
    <div class="acad-panel">
      <div class="acad-panel-head"><h3>Course Catalog Health</h3><a href="index.php?page=courses">View All</a></div>
      <?php foreach ($recentCourses as $course): ?><div class="acad-list-row"><div><strong><?= e((string) $course['title']) ?></strong><small><?= e((string) ($course['program_title'] ?? 'Unassigned')) ?> / <?= e(academy_delivery_label((string) ($course['delivery_type'] ?? 'lms'))) ?></small></div><span class="acad-badge <?= e(academy_admin_badge((string) $course['status'])) ?>"><?= e((string) $course['status']) ?></span></div><?php endforeach; ?>
      <?php if (!$recentCourses): ?><p class="empty">No Academy courses yet.</p><?php endif; ?>
    </div>
    <div class="acad-panel">
      <div class="acad-panel-head"><h3>Upcoming Cohorts</h3><a href="index.php?page=calendar">View Calendar</a></div>
      <?php foreach ($upcomingCohorts as $cohort): ?><div class="acad-list-row"><div><strong><?= e((string) $cohort['title']) ?></strong><small><?= e((string) $cohort['course_title']) ?> / <?= e(academy_admin_when((string) $cohort['start_at'])) ?></small></div><span><?= (int) $cohort['enrolled'] ?> enrolled</span></div><?php endforeach; ?>
      <?php if (!$upcomingCohorts): ?><p class="empty">No upcoming cohorts scheduled.</p><?php endif; ?>
    </div>
    <div class="acad-panel">
      <div class="acad-panel-head"><h3>Certificate Review Queue</h3><a href="index.php?page=certificates">View All</a></div>
      <?php foreach ($recentCertificates as $cert): ?><div class="acad-list-row"><div><strong><?= e((string) $cert['user_name']) ?></strong><small><?= e((string) $cert['course_title']) ?></small></div><span class="acad-badge <?= e(academy_admin_badge((string) $cert['status'])) ?>"><?= e((string) $cert['status']) ?></span></div><?php endforeach; ?>
      <?php if (!$recentCertificates): ?><p class="empty">No certificate requests yet.</p><?php endif; ?>
    </div>
    <div class="acad-panel">
      <div class="acad-panel-head"><h3>Refund & Payment Queue</h3><a href="index.php?page=refunds">View All</a></div>
      <?php foreach ($recentRefunds as $refund): ?><div class="acad-list-row"><div><strong><?= e((string) $refund['user_name']) ?></strong><small><?= e((string) $refund['course_title']) ?></small></div><span class="acad-badge <?= e(academy_admin_badge((string) $refund['status'])) ?>"><?= e('NGN ' . number_format((float) $refund['amount'], 2)) ?></span></div><?php endforeach; ?>
      <?php if (!$recentRefunds): ?><p class="empty">No Academy refund requests.</p><?php endif; ?>
    </div>
  </section>

  <section class="acad-bottom">
    <div class="acad-panel">
      <div class="acad-panel-head"><h3>Completion by Role</h3><a href="index.php?page=reports">Full Report</a></div>
      <table class="acad-table"><thead><tr><th>Role</th><th>Enrollments</th><th>Completed</th><th>Progress</th></tr></thead><tbody>
        <?php foreach (array_slice($completionByRole, 0, 6) as $row): $progress = max(0, min(100, (float) $row['avg_progress'])); ?><tr><td><?= e(academy_role_label((string) $row['user_role'])) ?></td><td><?= (int) $row['enrollments'] ?></td><td><?= (int) $row['completed'] ?></td><td><div class="acad-progress"><div class="acad-fill" style="width:<?= $progress ?>%"></div></div><?= number_format($progress, 1) ?>%</td></tr><?php endforeach; ?>
        <?php if (!$completionByRole): ?><tr><td colspan="4">No completion data yet.</td></tr><?php endif; ?>
      </tbody></table>
    </div>
    <div class="acad-panel">
      <div class="acad-panel-head"><h3>Quick Actions</h3></div>
      <div class="acad-actions">
        <a class="acad-action" href="index.php?page=courses"><i class="fa-solid fa-plus"></i><span><strong>Add Course</strong><small>Create a new Academy course</small></span></a>
        <a class="acad-action" href="index.php?page=lessons"><i class="fa-solid fa-file-lines"></i><span><strong>Add Lesson</strong><small>Build course content</small></span></a>
        <a class="acad-action" href="index.php?page=calendar"><i class="fa-regular fa-calendar-plus"></i><span><strong>Schedule Cohort</strong><small>Plan live delivery</small></span></a>
        <a class="acad-action" href="index.php?page=certificates"><i class="fa-solid fa-certificate"></i><span><strong>Review Certificate</strong><small>Approve learner awards</small></span></a>
        <a class="acad-action" href="index.php?page=reports"><i class="fa-solid fa-download"></i><span><strong>Export Report</strong><small>Academy intelligence</small></span></a>
      </div>
    </div>
  </section>
<?php endif; ?>

<?php if ($tab === 'homepage'): ?>
  <section class="academy-split">
    <form class="panel" method="post" enctype="multipart/form-data">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="save_homepage">
      <h2>Homepage Settings</h2>
      <p class="muted">Controls the public Academy campus: <a href="../../academy/index.php" target="_blank" rel="noopener">open the public homepage</a>.</p>
      <label>Hero Image<?php if ($homepage['hero_image'] !== ''): ?><small>Current: <?= e($homepage['hero_image']) ?></small><?php endif; ?><input type="file" name="hero_image" accept="image/jpeg,image/png,image/webp"></label>
      <label><input type="checkbox" name="remove_hero"<?= $homepage['hero_image'] === '' ? ' disabled' : '' ?>> Remove hero image and restore the default artwork</label>
      <label>Eyebrow<input name="eyebrow" value="<?= e($homepage['eyebrow']) ?>"></label>
      <label>Headline<small>Supports &lt;em&gt; and &lt;strong&gt; emphasis.</small><input name="headline" value="<?= e($homepage['headline']) ?>"></label>
      <label>Introduction<textarea name="lede" rows="3"><?= e($homepage['lede']) ?></textarea></label>
      <label>Featured Course<select name="featured_course_id">
        <option value="0">First active course (automatic)</option>
        <?php foreach ($activeCourses as $courseOption): ?><option value="<?= (int) $courseOption['id'] ?>"<?= (int) $homepage['featured_course_id'] === (int) $courseOption['id'] ? ' selected' : '' ?>><?= e((string) $courseOption['title']) ?></option><?php endforeach; ?>
      </select></label>
      <fieldset><legend>Trust Badges</legend>
        <?php for ($index = 0; $index < 3; $index++): ?><label>Badge <?= $index + 1 ?><input name="trust_label[<?= $index ?>]" value="<?= e($homepage['trust'][$index] ?? '') ?>"></label><?php endfor; ?>
      </fieldset>
      <fieldset><legend>Pathways</legend>
        <?php for ($index = 0; $index < 4; $index++): $pathway = $homepage['pathways'][$index] ?? ['icon' => '', 'title' => '', 'text' => '']; ?>
        <div class="academy-pathway-editor">
          <label>Icon Class<input name="pathway_icon[<?= $index ?>]" value="<?= e($pathway['icon']) ?>" placeholder="fa-seedling"></label>
          <label>Title<input name="pathway_title[<?= $index ?>]" value="<?= e($pathway['title']) ?>"></label>
          <label>Text<textarea name="pathway_text[<?= $index ?>]" rows="2"><?= e($pathway['text']) ?></textarea></label>
        </div>
        <?php endfor; ?>
      </fieldset>
      <label>CTA Heading<input name="cta_heading" value="<?= e($homepage['cta_heading']) ?>"></label>
      <label>CTA Copy<textarea name="cta_copy" rows="3"><?= e($homepage['cta_copy']) ?></textarea></label>
      <button type="submit">Save Homepage</button>
    </form>
    <div class="academy-card-list">
      <article>
        <h3>Current Homepage</h3>
        <?php if ($homepage['hero_image'] !== ''): ?>
          <img src="../../<?= e($homepage['hero_image']) ?>" alt="Homepage hero preview" style="width:100%;border-radius:10px;margin-bottom:10px">
        <?php else: ?>
          <p class="muted">Default hero artwork is in use.</p>
        <?php endif; ?>
        <p><strong><?= e(strip_tags($homepage['headline'])) ?></strong></p>
        <p class="muted"><?= e(mb_strimwidth(strip_tags($homepage['lede']), 0, 140, '…')) ?></p>
        <small><?= count($homepage['pathways']) ?> pathways · <?= count($homepage['trust']) ?> trust badges</small>
      </article>
    </div>
  </section>
<?php endif; ?>

<?php if ($tab === 'programs'): ?>
  <?php
  $ep = $editingProgram;
  $pval = static function (string $field, string $default = '') use ($ep): string {
      return $ep ? (string) ($ep[$field] ?? $default) : $default;
  };
  $psel = static function (string $field, string $value, string $default = '') use ($ep): string {
      $current = $ep ? (string) ($ep[$field] ?? $default) : $default;
      return $current === $value ? ' selected' : '';
  };
  $epRoles = $ep ? array_values(array_filter(array_map('trim', explode(',', (string) ($ep['audience_roles'] ?? ''))))) : [];
  ?>
  <section class="academy-split">
    <form class="panel" method="post" enctype="multipart/form-data">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="save_program">
      <input type="hidden" name="program_id" value="<?= (int) ($ep['id'] ?? 0) ?>">
      <h2><?= $ep ? 'Edit Program #' . (int) $ep['id'] : 'Create Program' ?></h2>
      <label>Title<input name="title" required value="<?= e($pval('title')) ?>"></label>
      <label>Thumbnail<input type="file" name="thumbnail" accept="image/jpeg,image/png,image/webp"></label>
      <label>Description<textarea name="description"><?= e($pval('description')) ?></textarea></label>
      <label>Prerequisites<textarea name="prerequisites" placeholder="What learners need before joining this program..."><?= e($pval('prerequisites')) ?></textarea></label>
      <label>Status<select name="status"><option value="active"<?= $psel('status', 'active', 'active') ?>>Active</option><option value="draft"<?= $psel('status', 'draft') ?>>Draft</option><option value="paused"<?= $psel('status', 'paused') ?>>Paused</option><option value="archived"<?= $psel('status', 'archived') ?>>Archived</option></select></label>
      <label>Sort Order<input type="number" name="sort_order" value="<?= (int) ($ep['sort_order'] ?? 0) ?>"></label>
      <fieldset><legend>Audience Roles</legend><div class="academy-pillbox"><?php foreach ($roles as $role): ?><label><input type="checkbox" name="audience_roles[]" value="<?= e($role) ?>"<?= in_array($role, $epRoles, true) ? ' checked' : '' ?>> <?= e(academy_role_label($role)) ?></label><?php endforeach; ?></div></fieldset>
      <button type="submit">Save Program</button>
      <?php if ($ep): ?><a class="acad-btn" href="index.php?page=programs">Cancel edit</a><?php endif; ?>
    </form>
    <div class="academy-card-list">
      <?php foreach ($programs as $program): ?>
        <?php $programThumb = academy_program_thumbnail_src($program); ?>
        <article>
          <h3><?= e((string) $program['title']) ?></h3>
          <p><?= e((string) $program['description']) ?></p>
          <?php if (trim((string) ($program['prerequisites'] ?? '')) !== ''): ?>
            <p><small><strong>Prerequisites:</strong><br><?= e((string) $program['prerequisites']) ?></small></p>
          <?php endif; ?>
          <small><?= e(academy_role_labels((string) $program['audience_roles'])) ?> / <?= e((string) $program['status']) ?></small>
          <?php if ($programThumb !== ''): ?>
            <img src="<?= e($programThumb) ?>" alt="<?= e((string) $program['title']) ?> thumbnail" style="width:100%;border-radius:8px;margin:8px 0">
          <?php endif; ?>
          <div style="display:flex;gap:8px;flex-wrap:wrap;margin:8px 0">
            <a class="acad-btn" href="index.php?page=programs&edit=<?= (int) $program['id'] ?>"><i class="fa-solid fa-pen"></i> Edit</a>
            <a class="acad-btn" href="../academy/program.php?program_id=<?= (int) $program['id'] ?>" target="_blank" rel="noopener"><i class="fa-solid fa-eye"></i> View</a>
            <form method="post" style="display:inline">
              <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="delete_program">
              <input type="hidden" name="program_id" value="<?= (int) $program['id'] ?>">
              <button class="acad-btn" type="submit" onclick="return confirm('Delete this program? Its courses will be moved back to the unassigned list.')"><i class="fa-solid fa-trash"></i> Delete</button>
            </form>
          </div>
          <form method="post" enctype="multipart/form-data" class="academy-thumb-form">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="save_program_thumbnail">
            <input type="hidden" name="program_id" value="<?= (int) $program['id'] ?>">
            <input type="file" name="program_thumbnail" accept="image/jpeg,image/png,image/webp">
            <button type="submit"><?= $programThumb !== '' ? 'Replace image' : 'Upload image' ?></button>
            <?php if ($programThumb !== ''): ?>
              <label class="academy-thumb-remove"><input type="checkbox" name="remove_thumbnail"> Remove image</label>
            <?php endif; ?>
          </form>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<?php if ($tab === 'courses'): ?>
  <?php
  $ec = $editingCourse;
  $val = static function (string $field, string $default = '') use ($ec): string {
      return $ec ? (string) ($ec[$field] ?? $default) : $default;
  };
  $sel = static function (string $field, string $value, string $default = '') use ($ec): string {
      $current = $ec ? (string) ($ec[$field] ?? $default) : $default;
      return $current === $value ? ' selected' : '';
  };
  $isChecked = static function (string $field, bool $default = false) use ($ec): string {
      if (!$ec) {
          return $default ? ' checked' : '';
      }
      return ((int) ($ec[$field] ?? 0)) === 1 ? ' checked' : '';
  };
  $ecRoles = $ec ? array_values(array_filter(array_map('trim', explode(',', (string) ($ec['target_roles'] ?? ''))))) : [];
  $sortLink = static function (string $col) use ($gridSort, $gridDir, $gridBase): string {
      $d = ($gridSort === $col && $gridDir === 'ASC') ? 'desc' : 'asc';
      return ACADEMY_ADMIN_ROUTE_BASE . '?page=courses' . ($gridBase !== '' ? '&' . $gridBase : '') . '&s=' . $col . '&d=' . $d;
  };
  $sortArrow = static function (string $col) use ($gridSort, $gridDir): string {
      if ($gridSort !== $col) {
          return '';
      }
      return $gridDir === 'ASC' ? ' <i class="fa-solid fa-sort-up"></i>' : ' <i class="fa-solid fa-sort-down"></i>';
  };
  $pageLink = static function (int $p) use ($gridBase, $gridSort, $gridDir): string {
      return ACADEMY_ADMIN_ROUTE_BASE . '?page=courses' . ($gridBase !== '' ? '&' . $gridBase : '') . '&s=' . $gridSort . '&d=' . strtolower($gridDir) . '&p=' . $p;
  };
  $statusClass = static function (string $st): string {
      return ['active' => 'ok', 'draft' => 'warn', 'paused' => 'warn', 'archived' => 'bad'][$st] ?? 'info';
  };
  ?>

  <details class="panel academy-create" id="course-form-panel"<?= $ec ? ' open' : '' ?>>
    <summary><?= $ec ? 'Edit Course #' . (int) $ec['id'] : 'Create New Course' ?></summary>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="save_course">
      <input type="hidden" name="course_id" value="<?= (int) ($ec['id'] ?? 0) ?>">
      <h2><?= $ec ? 'Edit Course' : 'Create Course' ?></h2>
      <div class="academy-form-grid">
        <label>Program<select name="program_id"><?php foreach ($programs as $program): ?><option value="<?= (int) $program['id'] ?>"<?= $ec && (int) ($ec['program_id'] ?? 0) === (int) $program['id'] ? ' selected' : '' ?>><?= e((string) $program['title']) ?></option><?php endforeach; ?></select></label>
        <label>Course Code<input name="course_code" placeholder="NAT-ACAD-001" value="<?= e($val('course_code')) ?>"></label>
        <label>Course Type<select name="course_type"><?php foreach (['course' => 'Course', 'webinar' => 'Webinar', 'workshop' => 'Workshop', 'certification' => 'Certification', 'orientation' => 'Orientation'] as $ctypeKey => $ctypeLabel): ?><option value="<?= e($ctypeKey) ?>"<?= $sel('course_type', $ctypeKey, 'course') ?>><?= e($ctypeLabel) ?></option><?php endforeach; ?></select></label>
        <label>Status<select name="status"><?php foreach (['active' => 'Active (Live)', 'draft' => 'Draft', 'paused' => 'Paused (Disabled)', 'archived' => 'Archived (Discontinued)'] as $statKey => $statLabel): ?><option value="<?= e($statKey) ?>"<?= $sel('status', $statKey, 'active') ?>><?= e($statLabel) ?></option><?php endforeach; ?></select></label>
        <label>Start Time<input type="datetime-local" name="start_time" value="<?= e($ec ? date('Y-m-d\TH:i', (int) strtotime((string) $ec['start_time'])) : date('Y-m-d\TH:i', (int) strtotime('+7 days 10:00'))) ?>"></label>
        <label>Duration Minutes<input type="number" name="duration_minutes" value="<?= (int) ($ec['duration_minutes'] ?? 90) ?>"></label>
        <label>Price NGN<input type="number" name="price" min="0" step="100" value="<?= e($ec ? number_format((float) $ec['price'], 2) : '0.00') ?>"></label>
        <label>Max Attendees<input type="number" name="max_attendees" value="<?= (int) ($ec['max_attendees'] ?? 250) ?>"></label>
        <label>Delivery Type<select name="delivery_type"><?php foreach (academy_delivery_types() as $key => $label): ?><option value="<?= e($key) ?>"<?= $sel('delivery_type', $key, 'lms') ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
        <label>Delivery URL<input name="delivery_url" placeholder="https://..." value="<?= e($val('delivery_url')) ?>"></label>
      </div>
      <label>Title<input name="title" required value="<?= e($val('title')) ?>"></label>
      <label>Description<textarea name="description" required><?= e($val('description')) ?></textarea></label>
      <label>Delivery Instructions<textarea name="delivery_instructions"><?= e($val('delivery_instructions')) ?></textarea></label>
      <label>Prerequisites<textarea name="prerequisites"><?= e($val('prerequisites')) ?></textarea></label>
      <div class="academy-form-grid">
        <label>Pass Score<input type="number" name="pass_score" min="0" max="100" value="<?= (int) ($ec['pass_score'] ?? 70) ?>"></label>
        <label>Instructor<input name="instructor_name" value="<?= e($val('instructor_name')) ?>"></label>
      </div>
      <label><input type="checkbox" name="is_free"<?= $ec ? $isChecked('is_free') : ' checked' ?>> Free course</label>
      <label><input type="checkbox" name="certification_required"<?= $isChecked('certification_required') ?>> Certification course</label>
      <label><input type="checkbox" name="certificate_approval_required"<?= $isChecked('certificate_approval_required') ?>> Certificate requires admin approval</label>
      <fieldset><legend>RBAC Audience</legend><div class="academy-pillbox"><?php foreach ($roles as $role): ?><label><input type="checkbox" name="target_roles[]" value="<?= e($role) ?>"<?= in_array($role, $ecRoles, true) ? ' checked' : '' ?>> <?= e(academy_role_label($role)) ?></label><?php endforeach; ?></div></fieldset>
      <button type="submit">Save Course</button>
      <?php if ($ec): ?><a class="acad-btn" href="index.php?page=courses">Cancel edit</a><?php endif; ?>
    </form>
  </details>

  <form class="acad-grid-toolbar" method="get" action="index.php">
    <input type="hidden" name="page" value="courses">
    <input type="search" name="q" value="<?= e($gridQ) ?>" placeholder="Search title, code, or description..." aria-label="Search courses">
    <select name="f_status" aria-label="Filter by status">
      <option value="all"<?= $gridStatus === 'all' ? ' selected' : '' ?>>All statuses</option>
      <option value="active"<?= $gridStatus === 'active' ? ' selected' : '' ?>>Live</option>
      <option value="draft"<?= $gridStatus === 'draft' ? ' selected' : '' ?>>Draft</option>
      <option value="paused"<?= $gridStatus === 'paused' ? ' selected' : '' ?>>Disabled</option>
      <option value="archived"<?= $gridStatus === 'archived' ? ' selected' : '' ?>>Discontinued</option>
    </select>
    <select name="f_type" aria-label="Filter by type">
      <option value="all"<?= $gridType === 'all' ? ' selected' : '' ?>>All types</option>
      <?php foreach (['course' => 'Course', 'webinar' => 'Webinar', 'workshop' => 'Workshop', 'certification' => 'Certification', 'orientation' => 'Orientation'] as $ftKey => $ftLabel): ?><option value="<?= e($ftKey) ?>"<?= $gridType === $ftKey ? ' selected' : '' ?>><?= e($ftLabel) ?></option><?php endforeach; ?>
    </select>
    <select name="f_price" aria-label="Filter by price">
      <option value="all"<?= $gridPrice === 'all' ? ' selected' : '' ?>>Free &amp; Paid</option>
      <option value="free"<?= $gridPrice === 'free' ? ' selected' : '' ?>>Free only</option>
      <option value="paid"<?= $gridPrice === 'paid' ? ' selected' : '' ?>>Paid only</option>
    </select>
    <select name="f_program" aria-label="Filter by program">
      <option value="0">All programs</option>
      <?php foreach ($programs as $program): ?><option value="<?= (int) $program['id'] ?>"<?= $gridProgram === (int) $program['id'] ? ' selected' : '' ?>><?= e((string) $program['title']) ?></option><?php endforeach; ?>
    </select>
    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-filter"></i> Apply</button>
    <a class="btn btn-ghost" href="index.php?page=courses"><i class="fa-solid fa-rotate-left"></i> Reset</a>
    <a class="btn btn-primary" href="javascript:void(0)" onclick="document.getElementById('course-form-panel').open=true;document.getElementById('course-form-panel').scrollIntoView()"><i class="fa-solid fa-plus"></i> New Course</a>
    <span class="acad-grid-count"><?= (int) $gridTotal ?> course<?= $gridTotal === 1 ? '' : 's' ?></span>
  </form>

  <div class="acad-grid-wrap">
    <table class="acad-table acad-course-table">
      <thead>
        <tr>
          <th><a href="<?= e($sortLink('title')) ?>">Title<?= $sortArrow('title') ?></a></th>
          <th><a href="<?= e($sortLink('program')) ?>">Program<?= $sortArrow('program') ?></a></th>
          <th><a href="<?= e($sortLink('type')) ?>">Type<?= $sortArrow('type') ?></a></th>
          <th><a href="<?= e($sortLink('status')) ?>">Status<?= $sortArrow('status') ?></a></th>
          <th><a href="<?= e($sortLink('price')) ?>">Price<?= $sortArrow('price') ?></a></th>
          <th>Delivery</th>
          <th><a href="<?= e($sortLink('start')) ?>">Start<?= $sortArrow('start') ?></a></th>
          <th><a href="<?= e($sortLink('enrollments')) ?>">Enrolled<?= $sortArrow('enrollments') ?></a></th>
          <th><a href="<?= e($sortLink('lessons')) ?>">Lessons<?= $sortArrow('lessons') ?></a></th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($courseGrid as $row): ?>
          <?php
          $rowStatus = (string) ($row['status'] ?: 'active');
          $rowPrice = (int) $row['is_free'] === 1 ? '<span class="acad-price-free">Free</span>' : 'NGN ' . e(number_format((float) $row['price'], 2));
          ?>
          <tr>
            <td><strong><?= e((string) $row['title']) ?></strong><?= ((string) ($row['course_code'] ?? '')) !== '' ? '<br><small class="muted">' . e((string) $row['course_code']) . '</small>' : '' ?></td>
            <td><?= e((string) ($row['program_title'] ?: 'Unassigned')) ?></td>
            <td><?= e(ucfirst((string) $row['course_type'])) ?></td>
            <td><span class="acad-status <?= e($statusClass($rowStatus)) ?>"><?= e(ucfirst($rowStatus)) ?></span></td>
            <td><?= $rowPrice ?></td>
            <td><?= e(academy_delivery_label((string) ($row['delivery_type'] ?: 'lms'))) ?></td>
            <td><?= e(academy_admin_when((string) $row['start_time'])) ?></td>
            <td><?= (int) $row['enrollments'] ?></td>
            <td><?= (int) $row['lesson_count'] ?></td>
            <td class="acad-grid-actions">
              <button class="acad-btn view" type="button" data-view-course="<?= (int) $row['id'] ?>" title="View course"><i class="fa-solid fa-eye"></i> View</button>
              <a class="acad-btn edit" href="index.php?page=courses&edit=<?= (int) $row['id'] ?>" title="Edit course"><i class="fa-solid fa-pen"></i> Edit</a>
              <form class="acad-grid-status-form" method="post">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="course_status">
                <input type="hidden" name="course_id" value="<?= (int) $row['id'] ?>">
                <select name="status" onchange="this.form.submit()" title="Set course status" aria-label="Set course status">
                  <option value="active"<?= $rowStatus === 'active' ? ' selected' : '' ?>>Live</option>
                  <option value="draft"<?= $rowStatus === 'draft' ? ' selected' : '' ?>>Draft</option>
                  <option value="paused"<?= $rowStatus === 'paused' ? ' selected' : '' ?>>Disable</option>
                  <option value="archived"<?= $rowStatus === 'archived' ? ' selected' : '' ?>>Discontinue</option>
                </select>
              </form>
              <form method="post" class="acad-delete-form" data-course-title="<?= e((string) $row['title']) ?>">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="delete_course">
                <input type="hidden" name="course_id" value="<?= (int) $row['id'] ?>">
                <button class="acad-btn delete" type="submit" title="Delete course"><i class="fa-solid fa-trash"></i> Delete</button>
              </form>
            </td>
          </tr>
          <tr class="course-view-row"><td colspan="10" style="padding:0;border:0">
            <div class="course-view-data" data-for="<?= (int) $row['id'] ?>" hidden>
              <h3><?= e((string) $row['title']) ?></h3>
              <p class="muted"><?= e((string) ($row['course_code'] ?: 'No course code')) ?> &middot; <?= e(ucfirst((string) $row['course_type'])) ?> &middot; <?= e(ucfirst($rowStatus)) ?></p>
              <dl>
                <dt>Program</dt><dd><?= e((string) ($row['program_title'] ?: 'Unassigned')) ?></dd>
                <dt>Status</dt><dd><?= e(ucfirst($rowStatus)) ?></dd>
                <dt>Price</dt><dd><?= (int) $row['is_free'] === 1 ? 'Free' : 'NGN ' . e(number_format((float) $row['price'], 2)) ?></dd>
                <dt>Delivery</dt><dd><?= e(academy_delivery_label((string) ($row['delivery_type'] ?: 'lms'))) ?></dd>
                <dt>Start</dt><dd><?= e(academy_admin_when((string) $row['start_time'])) ?></dd>
                <dt>Duration</dt><dd><?= (int) $row['duration_minutes'] ?> minutes</dd>
                <dt>Max attendees</dt><dd><?= (int) $row['max_attendees'] ?></dd>
                <dt>Enrollments</dt><dd><?= (int) $row['enrollments'] ?></dd>
                <dt>Lessons</dt><dd><?= (int) $row['lesson_count'] ?></dd>
                <dt>Assessments</dt><dd><?= (int) $row['assessment_count'] ?></dd>
                <dt>Audience</dt><dd><?= e(academy_role_labels((string) ($row['target_roles'] ?? ''))) ?></dd>
                <dt>Instructor</dt><dd><?= e((string) ($row['instructor_name'] ?: '—')) ?></dd>
                <dt>Pass score</dt><dd><?= (int) $row['pass_score'] ?>%</dd>
                <dt>Certification</dt><dd><?= (int) $row['certification_required'] === 1 ? 'Required' : 'Not required' ?><?= (int) $row['certificate_approval_required'] === 1 ? ' (admin approval)' : '' ?></dd>
              </dl>
              <div class="cv-block"><h4>Description</h4><p><?= e((string) $row['description']) ?></p></div>
              <div class="cv-block"><h4>Delivery instructions</h4><p><?= e((string) ($row['delivery_instructions'] ?: '—')) ?></p></div>
              <div class="cv-block"><h4>Prerequisites</h4><p><?= e((string) ($row['prerequisites'] ?: '—')) ?></p></div>
            </div>
          </td></tr>
        <?php endforeach; ?>
        <?php if (!$courseGrid): ?><tr><td colspan="10" class="acad-grid-empty">No courses match the current search and filters.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($gridPages > 1): ?>
    <div class="acad-grid-pager">
      <span class="pg-info">Showing <?= (int) ((($gridPage - 1) * $gridPer) + 1) ?>-<?= (int) min($gridPage * $gridPer, $gridTotal) ?> of <?= (int) $gridTotal ?></span>
      <a class="pg<?= $gridPage <= 1 ? ' disabled' : '' ?>" href="<?= e($pageLink(1)) ?>">First</a>
      <a class="pg<?= $gridPage <= 1 ? ' disabled' : '' ?>" href="<?= e($pageLink(max(1, $gridPage - 1))) ?>">Prev</a>
      <?php
      $winStart = max(1, min($gridPage - 2, $gridPages - 4));
      $winEnd = min($gridPages, max($gridPage + 2, 5));
      if ($winStart > 1): ?><span class="pg-dots">...</span><?php endif;
      for ($i = $winStart; $i <= $winEnd; $i++): ?><a class="pg<?= $i === $gridPage ? ' active' : '' ?>" href="<?= e($pageLink($i)) ?>"><?= (int) $i ?></a><?php endfor;
      if ($winEnd < $gridPages): ?><span class="pg-dots">...</span><?php endif;
      ?>
      <a class="pg<?= $gridPage >= $gridPages ? ' disabled' : '' ?>" href="<?= e($pageLink(min($gridPages, $gridPage + 1))) ?>">Next</a>
      <a class="pg<?= $gridPage >= $gridPages ? ' disabled' : '' ?>" href="<?= e($pageLink($gridPages)) ?>">Last</a>
    </div>
  <?php endif; ?>

  <div class="course-modal-backdrop" id="course-view-backdrop" role="dialog" aria-modal="true" aria-label="Course details">
    <div class="course-modal">
      <div id="course-view-body"></div>
      <div class="close-row"><button class="acad-btn" type="button" data-close-course><i class="fa-solid fa-xmark"></i> Close</button></div>
    </div>
  </div>

  <script>
  (function () {
    var backdrop = document.getElementById('course-view-backdrop');
    var body = document.getElementById('course-view-body');
    if (!backdrop || !body) return;
    function close() { backdrop.classList.remove('open'); }
    function open(id) {
      var data = document.querySelector('.course-view-data[data-for="' + id + '"]');
      if (!data) return;
      body.innerHTML = '';
      var clone = data.cloneNode(true);
      clone.removeAttribute('hidden');
      clone.removeAttribute('data-for');
      body.appendChild(clone);
      backdrop.classList.add('open');
    }
    document.querySelectorAll('[data-view-course]').forEach(function (btn) {
      btn.addEventListener('click', function () { open(btn.getAttribute('data-view-course')); });
    });
    document.querySelectorAll('.acad-delete-form').forEach(function (form) {
      form.addEventListener('submit', function (ev) {
        var title = form.getAttribute('data-course-title') || 'this course';
        if (!window.confirm('Delete "' + title + '" and all its lessons, assessments, and enrollments? This cannot be undone.')) {
          ev.preventDefault();
        }
      });
    });
    backdrop.addEventListener('click', function (ev) { if (ev.target === backdrop) close(); });
    var cb = backdrop.querySelector('[data-close-course]');
    if (cb) cb.addEventListener('click', close);
    document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape') close(); });
  })();
  </script>
<?php endif; ?>

<?php if ($tab === 'certificate_groups'): ?>
  <section class="academy-split">
    <form class="panel" method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="save_certificate_group">
      <h2>Create Certificate Group</h2>
      <p class="muted">Use this when one certificate should represent a pathway made from several completed courses.</p>
      <label>Certificate Title<input name="title" required placeholder="Input Provider Accreditation Certificate"></label>
      <label>Description<textarea name="description" required></textarea></label>
      <div class="academy-form-grid">
        <label>Status<select name="status"><option value="active">Active</option><option value="draft">Draft</option><option value="paused">Paused</option><option value="archived">Archived</option></select></label>
        <label>Sort Order<input type="number" name="sort_order" value="0"></label>
      </div>
      <label><input type="checkbox" name="certificate_approval_required"> Requires admin approval before issue</label>
      <fieldset><legend>RBAC Audience</legend><div class="academy-pillbox"><?php foreach ($roles as $role): ?><label><input type="checkbox" name="audience_roles[]" value="<?= e($role) ?>"> <?= e(academy_role_label($role)) ?></label><?php endforeach; ?></div></fieldset>
      <fieldset><legend>Required Courses</legend><div class="academy-pillbox"><?php foreach ($courses as $course): ?><label><input type="checkbox" name="course_ids[]" value="<?= (int) $course['id'] ?>"> <?= e((string) $course['title']) ?></label><?php endforeach; ?></div></fieldset>
      <button type="submit">Save Certificate Group</button>
    </form>
    <div class="academy-card-list">
      <?php foreach ($certificateGroups as $group): ?>
        <?php $groupCourses = academy_certificate_group_courses($pdo, (int) $group['id']); ?>
        <article>
          <h3><?= e((string) $group['title']) ?></h3>
          <p><?= e((string) $group['description']) ?></p>
          <p class="muted"><?= e(academy_role_labels((string) $group['audience_roles'])) ?> / <?= (int) $group['certificate_approval_required'] === 1 ? 'Approval required' : 'Auto issue' ?></p>
          <small><?= count($groupCourses) ?> required course<?= count($groupCourses) === 1 ? '' : 's' ?>: <?= e(implode(', ', array_map(static fn(array $row): string => (string) $row['title'], $groupCourses))) ?></small>
        </article>
      <?php endforeach; ?>
      <?php if (!$certificateGroups): ?><article>No grouped certificates have been created yet.</article><?php endif; ?>
    </div>
  </section>
<?php endif; ?>

<?php if ($tab === 'lessons'): ?>
  <?php
  $el = $editingLesson;
  $lval = static function (string $field, string $default = '') use ($el): string {
      return $el ? (string) ($el[$field] ?? $default) : $default;
  };
  $lsel = static function (string $field, string $value, string $default = '') use ($el): string {
      $current = $el ? (string) ($el[$field] ?? $default) : $default;
      return $current === $value ? ' selected' : '';
  };
  $lisChecked = static function (string $field, bool $default = false) use ($el): string {
      if (!$el) {
          return $default ? ' checked' : '';
      }
      return ((int) ($el[$field] ?? 0)) === 1 ? ' checked' : '';
  };
  $lgStatusClass = static function (string $st): string {
      return ['active' => 'ok', 'draft' => 'warn', 'paused' => 'warn', 'archived' => 'bad'][$st] ?? 'info';
  };
  $lgSortLink = static function (string $col) use ($lgSort, $lgDir, $lgBase): string {
      $d = ($lgSort === $col && $lgDir === 'ASC') ? 'desc' : 'asc';
      return ACADEMY_ADMIN_ROUTE_BASE . '?page=lessons' . ($lgBase !== '' ? '&' . $lgBase : '') . '&s=' . $col . '&d=' . $d;
  };
  $lgSortArrow = static function (string $col) use ($lgSort, $lgDir): string {
      if ($lgSort !== $col) {
          return '';
      }
      return $lgDir === 'ASC' ? ' <i class="fa-solid fa-sort-up"></i>' : ' <i class="fa-solid fa-sort-down"></i>';
  };
  $lgPageLink = static function (int $p) use ($lgBase, $lgSort, $lgDir): string {
      return ACADEMY_ADMIN_ROUTE_BASE . '?page=lessons' . ($lgBase !== '' ? '&' . $lgBase : '') . '&s=' . $lgSort . '&d=' . strtolower($lgDir) . '&p=' . $p;
  };
  ?>

  <details class="panel academy-create" id="lesson-form-panel"<?= $el ? ' open' : '' ?>>
    <summary><?= $el ? 'Edit Lesson #' . (int) $el['id'] : 'Add Lesson / Material' ?></summary>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="save_lesson">
      <input type="hidden" name="lesson_id" value="<?= (int) ($el['id'] ?? 0) ?>">
      <h2><?= $el ? 'Edit Lesson' : 'Add Lesson / Material' ?></h2>
      <label>Course<select name="webinar_id" required><?php foreach ($courses as $course): ?><option value="<?= (int) $course['id'] ?>"<?= $el && (int) ($el['webinar_id'] ?? 0) === (int) $course['id'] ? ' selected' : '' ?>><?= e((string) $course['title']) ?></option><?php endforeach; ?></select></label>
      <label>Title<input name="title" required value="<?= e($lval('title')) ?>"></label>
      <label>Summary<textarea name="summary"><?= e($lval('summary')) ?></textarea></label>
      <label>Lesson Content<textarea name="content"><?= e($lval('content')) ?></textarea></label>
      <div class="academy-form-grid">
        <label>Delivery Type<select name="delivery_type"><?php foreach (academy_delivery_types() as $key => $label): ?><option value="<?= e($key) ?>"<?= $lsel('delivery_type', $key, 'document') ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
        <label>Material URL<input name="material_url" value="<?= e($lval('material_url')) ?>"></label>
        <label>Video URL / Path<input name="video_url" placeholder="../academy_uploads/videos/... or https://..." value="<?= e($lval('video_url')) ?>"></label>
        <label>Audio URL / Path<input name="audio_url" placeholder="../academy_uploads/audio/... or https://..." value="<?= e($lval('audio_url')) ?>"></label>
        <label>Infographic Slug<input name="infographic_url" placeholder="e.g. grower-onboarding-workflow" value="<?= e($lval('infographic_url')) ?>"></label>
        <label>Duration (mins)<input type="number" name="duration_minutes" value="<?= (int) ($el['duration_minutes'] ?? 20) ?>"></label>
        <label>Sort Order<input type="number" name="sort_order" value="<?= (int) ($el['sort_order'] ?? 0) ?>"></label>
      </div>
      <label><input type="checkbox" name="is_required"<?= $el ? $lisChecked('is_required') : ' checked' ?>> Required lesson</label>
      <label>Status<select name="status"><?php foreach (['active' => 'Active (Live)', 'draft' => 'Draft', 'paused' => 'Paused (Disabled)', 'archived' => 'Archived (Discontinued)'] as $lstatKey => $lstatLabel): ?><option value="<?= e($lstatKey) ?>"<?= $lsel('status', $lstatKey, 'active') ?>><?= e($lstatLabel) ?></option><?php endforeach; ?></select></label>
      <button type="submit">Save Lesson</button>
      <?php if ($el): ?><a class="acad-btn" href="index.php?page=lessons">Cancel edit</a><?php endif; ?>
    </form>
  </details>

  <form class="acad-grid-toolbar" method="get" action="index.php">
    <input type="hidden" name="page" value="lessons">
    <input type="search" name="q" value="<?= e($lgQ) ?>" placeholder="Search lesson title, summary, or content..." aria-label="Search lessons">
    <select name="f_course" aria-label="Filter by course">
      <option value="0">All courses</option>
      <?php foreach ($courses as $course): ?><option value="<?= (int) $course['id'] ?>"<?= $lgCourse === (int) $course['id'] ? ' selected' : '' ?>><?= e((string) $course['title']) ?></option><?php endforeach; ?>
    </select>
    <select name="f_delivery" aria-label="Filter by delivery type">
      <option value="all"<?= $lgDelivery === 'all' ? ' selected' : '' ?>>All delivery types</option>
      <?php foreach (academy_delivery_types() as $key => $label): ?><option value="<?= e($key) ?>"<?= $lgDelivery === $key ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
    </select>
    <select name="f_status" aria-label="Filter by status">
      <option value="all"<?= $lgStatus === 'all' ? ' selected' : '' ?>>All statuses</option>
      <option value="active"<?= $lgStatus === 'active' ? ' selected' : '' ?>>Live</option>
      <option value="draft"<?= $lgStatus === 'draft' ? ' selected' : '' ?>>Draft</option>
      <option value="paused"<?= $lgStatus === 'paused' ? ' selected' : '' ?>>Disabled</option>
      <option value="archived"<?= $lgStatus === 'archived' ? ' selected' : '' ?>>Discontinued</option>
    </select>
    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-filter"></i> Apply</button>
    <a class="btn btn-ghost" href="index.php?page=lessons"><i class="fa-solid fa-rotate-left"></i> Reset</a>
    <a class="btn btn-primary" href="javascript:void(0)" onclick="document.getElementById('lesson-form-panel').open=true;document.getElementById('lesson-form-panel').scrollIntoView()"><i class="fa-solid fa-plus"></i> New Lesson</a>
    <span class="acad-grid-count"><?= (int) $lgTotal ?> lesson<?= $lgTotal === 1 ? '' : 's' ?></span>
  </form>

  <div class="acad-grid-wrap">
    <table class="acad-table acad-lesson-table">
      <thead>
        <tr>
          <th><a href="<?= e($lgSortLink('course')) ?>">Course<?= $lgSortArrow('course') ?></a></th>
          <th><a href="<?= e($lgSortLink('title')) ?>">Lesson<?= $lgSortArrow('title') ?></a></th>
          <th>Summary</th>
          <th><a href="<?= e($lgSortLink('delivery')) ?>">Delivery<?= $lgSortArrow('delivery') ?></a></th>
          <th><a href="<?= e($lgSortLink('duration')) ?>">Duration<?= $lgSortArrow('duration') ?></a></th>
          <th><a href="<?= e($lgSortLink('sort')) ?>">Sort<?= $lgSortArrow('sort') ?></a></th>
          <th><a href="<?= e($lgSortLink('status')) ?>">Status<?= $lgSortArrow('status') ?></a></th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($lessonGrid as $row): ?>
          <?php
          $rowStatus = (string) ($row['status'] ?: 'active');
          $reqBadge = (int) $row['is_required'] === 1 ? '<span class="acad-req">Required</span>' : '<span class="acad-optional">Optional</span>';
          ?>
          <tr>
            <td><?= e((string) ($row['course_title'] ?: '—')) ?></td>
            <td><strong><?= e((string) $row['title']) ?></strong><br><?= $reqBadge ?></td>
            <td><small class="muted"><?= e((string) ($row['summary'] ?: '—')) ?></small></td>
            <td><?= e(academy_delivery_label((string) ($row['delivery_type'] ?: 'document'))) ?></td>
            <td><?= (int) $row['duration_minutes'] ?> min</td>
            <td><?= (int) $row['sort_order'] ?></td>
            <td><span class="acad-status <?= e($lgStatusClass($rowStatus)) ?>"><?= e(ucfirst($rowStatus)) ?></span></td>
            <td class="acad-grid-actions">
              <button class="acad-btn view" type="button" data-view-lesson="<?= (int) $row['id'] ?>" title="View lesson"><i class="fa-solid fa-eye"></i> View</button>
              <a class="acad-btn edit" href="index.php?page=lessons&edit_lesson=<?= (int) $row['id'] ?>" title="Edit lesson"><i class="fa-solid fa-pen"></i> Edit</a>
              <form class="acad-grid-status-form" method="post">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="lesson_status">
                <input type="hidden" name="lesson_id" value="<?= (int) $row['id'] ?>">
                <select name="status" onchange="this.form.submit()" title="Set lesson status" aria-label="Set lesson status">
                  <option value="active"<?= $rowStatus === 'active' ? ' selected' : '' ?>>Live</option>
                  <option value="draft"<?= $rowStatus === 'draft' ? ' selected' : '' ?>>Draft</option>
                  <option value="paused"<?= $rowStatus === 'paused' ? ' selected' : '' ?>>Disable</option>
                  <option value="archived"<?= $rowStatus === 'archived' ? ' selected' : '' ?>>Discontinue</option>
                </select>
              </form>
              <form method="post" class="acad-delete-form" data-lesson-title="<?= e((string) $row['title']) ?>">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="delete_lesson">
                <input type="hidden" name="lesson_id" value="<?= (int) $row['id'] ?>">
                <button class="acad-btn delete" type="submit" title="Delete lesson"><i class="fa-solid fa-trash"></i> Delete</button>
              </form>
            </td>
          </tr>
          <tr class="course-view-row"><td colspan="8" style="padding:0;border:0">
            <div class="course-view-data" data-lesson-for="<?= (int) $row['id'] ?>" hidden>
              <h3><?= e((string) $row['title']) ?></h3>
              <p class="muted"><?= e((string) ($row['course_title'] ?: '—')) ?> &middot; <?= e(academy_delivery_label((string) ($row['delivery_type'] ?: 'document'))) ?> &middot; <?= e(ucfirst($rowStatus)) ?> &middot; <?= (int) $row['duration_minutes'] ?> min</p>
              <dl>
                <dt>Course</dt><dd><?= e((string) ($row['course_title'] ?: '—')) ?></dd>
                <dt>Status</dt><dd><?= e(ucfirst($rowStatus)) ?></dd>
                <dt>Delivery</dt><dd><?= e(academy_delivery_label((string) ($row['delivery_type'] ?: 'document'))) ?></dd>
                <dt>Duration</dt><dd><?= (int) $row['duration_minutes'] ?> minutes</dd>
                <dt>Sort order</dt><dd><?= (int) $row['sort_order'] ?></dd>
                <dt>Required</dt><dd><?= (int) $row['is_required'] === 1 ? 'Yes' : 'No' ?></dd>
                <dt>Material URL</dt><dd><?= e((string) ($row['material_url'] ?: '—')) ?></dd>
              </dl>
              <div class="cv-block"><h4>Summary</h4><p><?= e((string) ($row['summary'] ?: '—')) ?></p></div>
              <div class="cv-block"><h4>Content</h4><div class="cv-content"><?= e((string) $row['content']) ?></div></div>
            </div>
          </td></tr>
        <?php endforeach; ?>
        <?php if (!$lessonGrid): ?><tr><td colspan="8" class="acad-grid-empty">No lessons match the current search and filters.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($lgPages > 1): ?>
    <div class="acad-grid-pager">
      <span class="pg-info">Showing <?= (int) ((($lgPage - 1) * $lgPer) + 1) ?>-<?= (int) min($lgPage * $lgPer, $lgTotal) ?> of <?= (int) $lgTotal ?></span>
      <a class="pg<?= $lgPage <= 1 ? ' disabled' : '' ?>" href="<?= e($lgPageLink(1)) ?>">First</a>
      <a class="pg<?= $lgPage <= 1 ? ' disabled' : '' ?>" href="<?= e($lgPageLink(max(1, $lgPage - 1))) ?>">Prev</a>
      <?php
      $lgWinStart = max(1, min($lgPage - 2, $lgPages - 4));
      $lgWinEnd = min($lgPages, max($lgPage + 2, 5));
      if ($lgWinStart > 1): ?><span class="pg-dots">...</span><?php endif;
      for ($i = $lgWinStart; $i <= $lgWinEnd; $i++): ?><a class="pg<?= $i === $lgPage ? ' active' : '' ?>" href="<?= e($lgPageLink($i)) ?>"><?= (int) $i ?></a><?php endfor;
      if ($lgWinEnd < $lgPages): ?><span class="pg-dots">...</span><?php endif;
      ?>
      <a class="pg<?= $lgPage >= $lgPages ? ' disabled' : '' ?>" href="<?= e($lgPageLink(min($lgPages, $lgPage + 1))) ?>">Next</a>
      <a class="pg<?= $lgPage >= $lgPages ? ' disabled' : '' ?>" href="<?= e($lgPageLink($lgPages)) ?>">Last</a>
    </div>
  <?php endif; ?>

  <div class="course-modal-backdrop" id="lesson-view-backdrop" role="dialog" aria-modal="true" aria-label="Lesson details">
    <div class="course-modal">
      <div id="lesson-view-body"></div>
      <div class="close-row"><button class="acad-btn" type="button" data-close-lesson><i class="fa-solid fa-xmark"></i> Close</button></div>
    </div>
  </div>

  <script>
  (function () {
    var backdrop = document.getElementById('lesson-view-backdrop');
    var body = document.getElementById('lesson-view-body');
    if (!backdrop || !body) return;
    function close() { backdrop.classList.remove('open'); }
    function open(id) {
      var data = document.querySelector('.course-view-data[data-lesson-for="' + id + '"]');
      if (!data) return;
      body.innerHTML = '';
      var clone = data.cloneNode(true);
      clone.removeAttribute('hidden');
      clone.removeAttribute('data-lesson-for');
      body.appendChild(clone);
      backdrop.classList.add('open');
    }
    document.querySelectorAll('[data-view-lesson]').forEach(function (btn) {
      btn.addEventListener('click', function () { open(btn.getAttribute('data-view-lesson')); });
    });
    document.querySelectorAll('.acad-delete-form').forEach(function (form) {
      form.addEventListener('submit', function (ev) {
        var title = form.getAttribute('data-lesson-title') || form.getAttribute('data-course-title') || 'this lesson';
        if (!window.confirm('Delete "' + title + '"? This cannot be undone.')) {
          ev.preventDefault();
        }
      });
    });
    backdrop.addEventListener('click', function (ev) { if (ev.target === backdrop) close(); });
    var cb = backdrop.querySelector('[data-close-lesson]');
    if (cb) cb.addEventListener('click', close);
    document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape') close(); });
  })();
  </script>
<?php endif; ?>

<?php if ($tab === 'assessments'): ?>
  <section class="academy-split">
    <div class="panel">
      <form method="post">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save_assessment">
        <h2>Create Assessment</h2>
        <label>Course<select name="webinar_id"><?php foreach ($courses as $course): ?><option value="<?= (int) $course['id'] ?>"><?= e((string) $course['title']) ?></option><?php endforeach; ?></select></label>
        <label>Title<input name="title" required></label>
        <label>Instructions<textarea name="instructions"></textarea></label>
        <div class="academy-form-grid"><label>Pass Score<input type="number" name="pass_score" value="70"></label><label>Max Attempts<input type="number" name="max_attempts" value="3"></label></div>
        <button type="submit">Save Assessment</button>
      </form>
      <hr>
      <form method="post">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save_question">
        <h2>Add Question</h2>
        <label>Assessment<select name="assessment_id"><?php foreach ($assessments as $assessment): ?><option value="<?= (int) $assessment['id'] ?>"><?= e((string) $assessment['course_title']) ?> - <?= e((string) $assessment['title']) ?></option><?php endforeach; ?></select></label>
        <label>Question<textarea name="question_text" required></textarea></label>
        <div class="academy-form-grid"><label>A<input name="option_a" required></label><label>B<input name="option_b" required></label><label>C<input name="option_c" required></label><label>D<input name="option_d" required></label><label>Correct<select name="correct_option"><option>A</option><option>B</option><option>C</option><option>D</option></select></label><label>Points<input type="number" name="points" value="1"></label></div>
        <label>Educational Rationale<textarea name="rationale" placeholder="Explain the underlying domain reason why the correct answer is right and why others are wrong..."></textarea></label>
        <button type="submit">Save Question</button>
      </form>
    </div>
    <table><tr><th>Course</th><th>Assessment</th><th>Pass</th><th>Questions</th></tr><?php foreach ($assessments as $assessment): ?><tr><td><?= e((string) $assessment['course_title']) ?></td><td><?= e((string) $assessment['title']) ?></td><td><?= e((string) $assessment['pass_score']) ?>%</td><td><?= (int) $assessment['questions'] ?></td></tr><?php endforeach; ?><?php if (!$assessments): ?><tr><td colspan="4">No assessments yet.</td></tr><?php endif; ?></table>
  </section>
<?php endif; ?>

<?php if ($tab === 'calendar'): ?>
  <section class="academy-split">
    <form class="panel" method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="save_cohort">
      <h2>Create Calendar / Cohort Session</h2>
      <label>Course<select name="webinar_id" required><?php foreach ($courses as $course): ?><option value="<?= (int) $course['id'] ?>"><?= e((string) $course['title']) ?></option><?php endforeach; ?></select></label>
      <label>Instructor<select name="instructor_id"><option value="">Unassigned</option><?php foreach ($instructors as $instructor): ?><option value="<?= (int) $instructor['id'] ?>"><?= e((string) $instructor['name']) ?></option><?php endforeach; ?></select></label>
      <label>Session / Cohort Title<input name="title" required placeholder="June cohort live orientation"></label>
      <div class="academy-form-grid">
        <label>Start<input type="datetime-local" name="start_at" value="<?= e(date('Y-m-d\TH:i', strtotime('+7 days 10:00'))) ?>" required></label>
        <label>End<input type="datetime-local" name="end_at"></label>
        <label>Capacity<input type="number" name="capacity" value="100"></label>
        <label>Status<select name="status"><option value="scheduled">Scheduled</option><option value="open">Open</option><option value="completed">Completed</option><option value="cancelled">Cancelled</option></select></label>
      </div>
      <label>Venue<input name="venue" placeholder="Physical venue or state office"></label>
      <label>Meeting URL<input name="meeting_url" placeholder="Zoom/Meet/Teams/WhatsApp link"></label>
      <label>Notes<textarea name="notes"></textarea></label>
      <button type="submit">Save Cohort</button>
    </form>
    <table><tr><th>Date</th><th>Course</th><th>Cohort</th><th>Instructor</th><th>Attendance</th><th>Status</th></tr><?php foreach ($cohorts as $cohort): ?><tr><td><?= e((string) $cohort['start_at']) ?></td><td><?= e((string) $cohort['course_title']) ?></td><td><strong><?= e((string) $cohort['title']) ?></strong><br><small><?= e((string) ($cohort['venue'] ?: $cohort['meeting_url'] ?: 'No venue/link set')) ?></small></td><td><?= e((string) ($cohort['instructor_name'] ?? 'Unassigned')) ?></td><td><?= (int) $cohort['attendance_marked'] ?>/<?= (int) $cohort['enrolled'] ?></td><td><?= e((string) $cohort['status']) ?></td></tr><?php endforeach; ?><?php if (!$cohorts): ?><tr><td colspan="6">No calendar/cohort sessions yet.</td></tr><?php endif; ?></table>
  </section>
<?php endif; ?>

<?php if ($tab === 'instructors'): ?>
  <section class="academy-split">
    <form class="panel" method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="save_instructor">
      <h2>Add Instructor / Facilitator</h2>
      <label>Name<input name="name" required></label>
      <label>Email<input type="email" name="email"></label>
      <label>Phone<input name="phone"></label>
      <label>Specialty<input name="specialty" placeholder="Field verification, provider compliance..."></label>
      <label>Bio<textarea name="bio"></textarea></label>
      <label>Status<select name="status"><option value="active">Active</option><option value="paused">Paused</option><option value="archived">Archived</option></select></label>
      <button type="submit">Save Instructor</button>
    </form>
    <table><tr><th>Name</th><th>Specialty</th><th>Contact</th><th>Status</th></tr><?php foreach ($instructors as $instructor): ?><tr><td><strong><?= e((string) $instructor['name']) ?></strong><br><small><?= e((string) $instructor['bio']) ?></small></td><td><?= e((string) $instructor['specialty']) ?></td><td><?= e((string) $instructor['email']) ?><br><?= e((string) $instructor['phone']) ?></td><td><?= e((string) $instructor['status']) ?></td></tr><?php endforeach; ?><?php if (!$instructors): ?><tr><td colspan="4">No instructors yet.</td></tr><?php endif; ?></table>
  </section>
<?php endif; ?>

<?php if ($tab === 'attendance'): ?>
  <section class="academy-split">
    <form class="panel" method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="mark_attendance">
      <h2>Mark Attendance</h2>
      <label>Cohort<select name="cohort_id" required><?php foreach ($cohorts as $cohort): ?><option value="<?= (int) $cohort['id'] ?>"><?= e((string) $cohort['course_title']) ?> - <?= e((string) $cohort['title']) ?></option><?php endforeach; ?></select></label>
      <label>Enrolled User<select name="user_id" required><?php foreach ($enrollments as $row): ?><option value="<?= (int) $row['user_id'] ?>"><?= e((string) $row['user_name']) ?> - <?= e((string) $row['course_title']) ?></option><?php endforeach; ?></select></label>
      <label>Status<select name="status"><option value="present">Present</option><option value="late">Late</option><option value="absent">Absent</option><option value="excused">Excused</option></select></label>
      <label>Notes<textarea name="notes"></textarea></label>
      <button type="submit">Save Attendance</button>
    </form>
    <table><tr><th>Marked</th><th>User</th><th>Course/Cohort</th><th>Status</th><th>Notes</th></tr><?php foreach ($attendanceRows as $row): ?><tr><td><?= e((string) $row['marked_at']) ?></td><td><?= e((string) $row['user_name']) ?><br><small><?= e((string) $row['email']) ?></small></td><td><?= e((string) $row['course_title']) ?><br><small><?= e((string) $row['cohort_title']) ?></small></td><td><?= e((string) $row['status']) ?></td><td><?= e((string) $row['notes']) ?></td></tr><?php endforeach; ?><?php if (!$attendanceRows): ?><tr><td colspan="5">No attendance has been marked yet.</td></tr><?php endif; ?></table>
  </section>
<?php endif; ?>

<?php if ($tab === 'reminders'): ?>
  <section class="academy-split">
    <form class="panel" method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="save_reminder">
      <h2>Create Reminder</h2>
      <label>Course<select name="webinar_id"><option value="">Any course</option><?php foreach ($courses as $course): ?><option value="<?= (int) $course['id'] ?>"><?= e((string) $course['title']) ?></option><?php endforeach; ?></select></label>
      <label>Cohort<select name="cohort_id"><option value="">No cohort</option><?php foreach ($cohorts as $cohort): ?><option value="<?= (int) $cohort['id'] ?>"><?= e((string) $cohort['title']) ?></option><?php endforeach; ?></select></label>
      <label>Title<input name="title" required></label>
      <label>Message<textarea name="message" required></textarea></label>
      <div class="academy-form-grid">
        <label>Channel<select name="channel"><option value="dashboard">Dashboard</option><option value="email">Email</option><option value="sms">SMS</option><option value="whatsapp">WhatsApp</option></select></label>
        <label>Send At<input type="datetime-local" name="send_at"></label>
        <label>Status<select name="status"><option value="draft">Draft</option><option value="scheduled">Scheduled</option><option value="sent">Mark Sent</option><option value="cancelled">Cancelled</option></select></label>
      </div>
      <fieldset><legend>Audience Roles</legend><div class="academy-pillbox"><?php foreach ($roles as $role): ?><label><input type="checkbox" name="audience_roles[]" value="<?= e($role) ?>"> <?= e(academy_role_label($role)) ?></label><?php endforeach; ?></div></fieldset>
      <button type="submit">Save Reminder</button>
    </form>
    <table><tr><th>When</th><th>Reminder</th><th>Course/Cohort</th><th>Audience</th><th>Status</th></tr><?php foreach ($reminders as $reminder): ?><tr><td><?= e((string) ($reminder['send_at'] ?? $reminder['created_at'])) ?></td><td><strong><?= e((string) $reminder['title']) ?></strong><br><small><?= e((string) $reminder['message']) ?></small></td><td><?= e((string) ($reminder['course_title'] ?? 'Any course')) ?><br><small><?= e((string) ($reminder['cohort_title'] ?? '')) ?></small></td><td><?= e(academy_role_labels((string) $reminder['audience_roles'])) ?></td><td><?= e((string) $reminder['channel']) ?> / <?= e((string) $reminder['status']) ?></td></tr><?php endforeach; ?><?php if (!$reminders): ?><tr><td colspan="5">No reminders yet.</td></tr><?php endif; ?></table>
  </section>
<?php endif; ?>

<?php if ($tab === 'feedback'): ?>
  <table><tr><th>Date</th><th>User</th><th>Course</th><th>Rating</th><th>Comment</th></tr><?php foreach ($feedbackRows as $row): ?><tr><td><?= e((string) $row['created_at']) ?></td><td><?= e((string) $row['user_name']) ?><br><small><?= e((string) $row['email']) ?></small></td><td><?= e((string) $row['course_title']) ?></td><td><?= (int) $row['rating'] ?>/5</td><td><?= e((string) $row['comment']) ?></td></tr><?php endforeach; ?><?php if (!$feedbackRows): ?><tr><td colspan="5">No learner feedback yet.</td></tr><?php endif; ?></table>
<?php endif; ?>

<?php if ($tab === 'enrollments'): ?>
  <table><tr><th>User</th><th>Course</th><th>Payment</th><th>Progress</th><th>Registered</th></tr><?php foreach ($enrollments as $row): ?><tr><td><strong><?= e((string) $row['user_name']) ?></strong><br><small><?= e((string) $row['email']) ?></small></td><td><?= e((string) $row['course_title']) ?><br><small><?= e((string) ($row['program_title'] ?? '')) ?></small></td><td><?= e((string) $row['payment_status']) ?></td><td><?= (int) $row['progress_percent'] ?>% / <?= e((string) $row['completion_status']) ?></td><td><?= e((string) $row['registered_at']) ?></td></tr><?php endforeach; ?></table>
<?php endif; ?>

<?php if ($tab === 'certificates'): ?>
  <table><tr><th>User</th><th>Certificate</th><th>Reference</th><th>Status</th><th>Review</th></tr><?php foreach ($certificates as $cert): ?><tr><td><?= e((string) $cert['user_name']) ?><br><small><?= e((string) $cert['email']) ?></small></td><td><?= e((string) $cert['course_title']) ?><br><small><?= e(ucfirst((string) $cert['certificate_kind'])) ?> certificate</small></td><td><?= e((string) $cert['certificate_ref']) ?></td><td><?= e((string) $cert['status']) ?></td><td><?php if ((string) $cert['status'] === 'issued'): ?><span class="badge verified">Permanent</span><?php else: ?><form method="post" class="actions"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="review_certificate"><input type="hidden" name="certificate_kind" value="<?= e((string) $cert['certificate_kind']) ?>"><input type="hidden" name="certificate_id" value="<?= (int) $cert['id'] ?>"><select name="status"><option value="pending">Pending</option><option value="issued">Issued</option><option value="rejected">Rejected</option></select><input name="notes" placeholder="Notes"><button>Save</button></form><?php endif; ?></td></tr><?php endforeach; ?><?php if (!$certificates): ?><tr><td colspan="5">No certificate requests yet.</td></tr><?php endif; ?></table>
<?php endif; ?>

<?php if ($tab === 'refunds'): ?>
  <table><tr><th>User</th><th>Course</th><th>Amount</th><th>Reason</th><th>Status</th><th>Review</th></tr><?php foreach ($refunds as $refund): ?><tr><td><?= e((string) $refund['user_name']) ?><br><small><?= e((string) $refund['email']) ?></small></td><td><?= e((string) $refund['course_title']) ?></td><td>NGN <?= e(number_format((float) $refund['amount'], 2)) ?></td><td><?= e((string) $refund['reason']) ?></td><td><?= e((string) $refund['status']) ?></td><td><form method="post" class="actions"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="review_refund"><input type="hidden" name="refund_id" value="<?= (int) $refund['id'] ?>"><select name="status"><option value="pending">Pending</option><option value="approved">Approved</option><option value="rejected">Rejected</option><option value="paid">Paid</option><option value="closed">Closed</option></select><input name="admin_notes" placeholder="Notes"><button>Save</button></form></td></tr><?php endforeach; ?><?php if (!$refunds): ?><tr><td colspan="6">No Academy refund requests yet.</td></tr><?php endif; ?></table>
<?php endif; ?>

<?php if ($tab === 'reports'): ?>
  <section class="stats">
    <div class="stat"><span>Enrollments</span><div class="metric"><?= (int) $stats['enrollments'] ?></div></div>
    <div class="stat"><span>Completed</span><div class="metric"><?= (int) $stats['completed'] ?></div></div>
    <div class="stat"><span>Cohorts</span><div class="metric"><?= (int) $stats['cohorts'] ?></div></div>
    <div class="stat"><span>Attendance</span><div class="metric"><?= (int) $stats['attendance'] ?></div></div>
    <div class="stat"><span>Feedback</span><div class="metric"><?= (int) $stats['feedback'] ?></div></div>
  </section>
  <section class="grid">
    <?php foreach ($programs as $program): ?>
      <?php $programCourses = array_values(array_filter($courses, static fn(array $course): bool => (int) ($course['program_id'] ?? 0) === (int) $program['id'])); ?>
      <article class="card"><h2><?= e((string) $program['title']) ?></h2><p class="metric"><?= count($programCourses) ?></p><p class="muted">courses in this Academy program</p></article>
    <?php endforeach; ?>
  </section>
  <section class="card" style="margin-top:16px;">
    <h2>Completion By Role</h2>
    <table><tr><th>Role</th><th>Enrollments</th><th>Completed</th><th>Average Progress</th></tr><?php foreach ($completionByRole as $row): ?><tr><td><?= e(academy_role_label((string) $row['user_role'])) ?></td><td><?= (int) $row['enrollments'] ?></td><td><?= (int) $row['completed'] ?></td><td><?= e((string) $row['avg_progress']) ?>%</td></tr><?php endforeach; ?><?php if (!$completionByRole): ?><tr><td colspan="4">No enrollment report data yet.</td></tr><?php endif; ?></table>
  </section>
  <section class="card" style="margin-top:16px;">
    <h2>Course Intelligence</h2>
    <table><tr><th>Course</th><th>Enrollments</th><th>Paid</th><th>Completed</th><th>Attempts</th><th>Avg Score</th><th>Rating</th></tr><?php foreach ($courseReport as $row): ?><tr><td><?= e((string) $row['title']) ?></td><td><?= (int) $row['enrollments'] ?></td><td><?= (int) $row['paid_enrollments'] ?></td><td><?= (int) $row['completed'] ?></td><td><?= (int) $row['attempts'] ?></td><td><?= e((string) ($row['avg_score'] ?? '0')) ?>%</td><td><?= e((string) ($row['avg_rating'] ?? 'No rating')) ?></td></tr><?php endforeach; ?></table>
  </section>
<?php endif; ?>

  </div>
</div>
<?php admin_page_end(); ?>
