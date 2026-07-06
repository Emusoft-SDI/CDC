<?php defined('NATCODEV_ACADEMY_WORKSPACE_VIEW') || exit; ?>
<style>
  html, body { margin:0 !important; padding:0 !important; overflow-x:hidden; }
  .admin-main { width:100vw !important; max-width:none !important; margin:0 !important; padding:0 !important; }
  .acad-workspace { width:100vw !important; min-height:100vh; margin:0 !important; gap:0 !important; grid-template-columns:220px minmax(0,1fr) !important; }
  .acad-rail { left:0; width:220px; min-height:100vh; max-height:100vh; overflow-y:auto; overflow-x:hidden; }
  .acad-nav { grid-template-columns:1fr !important; }
  .acad-nav a { min-width:0; width:100%; align-items:center; }
  .acad-nav a span:first-child { min-width:0; max-width:100%; overflow-wrap:anywhere; }
  .acad-count { flex:0 0 auto; }
  .acad-content { padding:12px 14px 28px !important; background:#f6faf7; min-height:100vh; }
  .acad-profile-menu{position:relative}.acad-profile-trigger{border:1px solid var(--line);background:#fff;color:#102033;border-radius:8px;padding:9px 11px;font-weight:850;display:flex;align-items:center;gap:8px;cursor:pointer}.acad-profile-dropdown{display:none;position:absolute;right:0;top:calc(100% + 8px);z-index:50;width:230px;background:#fff;border:1px solid var(--line);border-radius:8px;box-shadow:0 18px 38px rgba(16,24,40,.16);padding:8px}.acad-profile-menu.open .acad-profile-dropdown{display:grid;gap:5px}.acad-profile-dropdown a,.acad-profile-dropdown button{width:100%;display:flex;align-items:center;gap:8px;justify-content:flex-start;border-radius:6px;padding:9px 10px;background:#fff;color:#102033;border:0;box-shadow:none;text-decoration:none;font-weight:800}.acad-profile-dropdown a:hover,.acad-profile-dropdown button:hover{background:#eef7f1;color:#075c34}.acad-profile-meta{padding:7px 10px;border-bottom:1px solid var(--line);margin-bottom:3px}.acad-profile-meta strong{display:block}.acad-profile-meta small{color:var(--muted)}
  @media(max-width:1400px){ .acad-workspace{grid-template-columns:220px minmax(0,1fr) !important;} .acad-rail{position:sticky !important; top:0 !important;} .acad-nav{grid-template-columns:1fr !important;} }
</style>
<?php if (!empty($_GET['message'])): ?><div class="notice ok"><?= e((string) $_GET['message']) ?></div><?php endif; ?>
<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>
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
      <a class="<?= $tab === 'programs' ? 'active' : '' ?>" href="index.php?page=programs"><span><i class="fa-solid fa-layer-group"></i> Programs</span><span class="acad-count"><?= (int) $stats['programs'] ?></span></a>
      <a class="<?= $tab === 'courses' ? 'active' : '' ?>" href="index.php?page=courses"><span><i class="fa-solid fa-book-open"></i> Courses</span><span class="acad-count"><?= (int) $stats['courses'] ?></span></a>
    </nav>
    <div class="acad-label">Content & Assessment</div>
    <nav class="acad-nav">
      <a class="<?= $tab === 'lessons' ? 'active' : '' ?>" href="index.php?page=lessons"><span><i class="fa-solid fa-file-lines"></i> Lessons & Materials</span><span class="acad-count"><?= (int) $stats['lessons'] ?></span></a>
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
    <div class="acad-label">Learners & Outcomes</div>
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
      <a href="../../academy/index.php" target="_blank"><span><i class="fa-solid fa-arrow-up-right-from-square"></i> Public Academy</span></a>
    </nav>
  </aside>
  <main class="acad-content">
    <?= admin_workspace_operator_strip($pdo, ['asset_prefix' => '../../', 'profile_href' => '../profile.php', 'password_href' => '../profile.php#password', 'logout_action' => '../admin.php', 'title' => 'Academy workspace', 'placeholder' => 'Search courses, learners, certificates...']) ?>
    <div class="acad-top">
      <div class="acad-search"><i class="fa-solid fa-magnifying-glass"></i><input type="search" placeholder="Search courses, learners, certificates, cohorts..." aria-label="Search Academy workspace"></div>
      <div class="acad-toolstrip">
        <span class="acad-tool"><i class="fa-regular fa-bell"></i> <?= (int) $stats['pending_certificates'] ?></span>
        <span class="acad-tool"><i class="fa-solid fa-wallet"></i> <?= e(academy_admin_money($academyCollections)) ?></span>
        <a class="acad-tool" href="../../academy/index.php" target="_blank" rel="noopener">View Public Academy</a>
        <div class="acad-profile-menu">
          <button class="acad-profile-trigger" type="button" data-acad-menu><i class="fa-solid fa-user-circle"></i><?= e($academyAdminName) ?><i class="fa-solid fa-chevron-down"></i></button>
          <div class="acad-profile-dropdown">
            <div class="acad-profile-meta"><strong><?= e($academyAdminName) ?></strong><small><?= e($academyAdminRole) ?></small></div>
            <a href="../profile.php"><i class="fa-solid fa-user"></i> My Profile</a>
            <a href="../settings.php"><i class="fa-solid fa-gear"></i> Account Settings</a>
            <form method="post" action="../admin.php"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="logout" value="1"><button type="submit"><i class="fa-solid fa-right-from-bracket"></i> Logout</button></form>
          </div>
        </div>
      </div>
    </div>
    <div class="acad-head">
      <div><h2>NATCODEV Academy</h2><p>Manage learning programs, courses, enrollments, certificates, payments, cohorts, and learner outcomes.</p></div>
      <span class="acad-tool"><i class="fa-regular fa-calendar"></i> <?= e(date('M j')) ?> - <?= e(date('M j, Y', strtotime('+6 days'))) ?></span>
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

  <section class="acad-grid">
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

<?php if ($tab === 'programs'): ?>
  <section class="academy-split">
    <form class="panel" method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="save_program">
      <input type="hidden" name="program_id" value="0">
      <h2>Create Program</h2>
      <label>Title<input name="title" required></label>
      <label>Description<textarea name="description"></textarea></label>
      <label>Status<select name="status"><option value="active">Active</option><option value="draft">Draft</option><option value="paused">Paused</option><option value="archived">Archived</option></select></label>
      <label>Sort Order<input type="number" name="sort_order" value="0"></label>
      <fieldset><legend>Audience Roles</legend><div class="academy-pillbox"><?php foreach ($roles as $role): ?><label><input type="checkbox" name="audience_roles[]" value="<?= e($role) ?>"> <?= e(academy_role_label($role)) ?></label><?php endforeach; ?></div></fieldset>
      <button type="submit">Save Program</button>
    </form>
    <div class="academy-card-list">
      <?php foreach ($programs as $program): ?>
        <article>
          <h3><?= e((string) $program['title']) ?></h3>
          <p><?= e((string) $program['description']) ?></p>
          <small><?= e(academy_role_labels((string) $program['audience_roles'])) ?> / <?= e((string) $program['status']) ?></small>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<?php if ($tab === 'courses'): ?>
  <section class="academy-split">
    <form class="panel" method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="save_course">
      <input type="hidden" name="course_id" value="0">
      <h2>Create Course</h2>
      <div class="academy-form-grid">
        <label>Program<select name="program_id"><?php foreach ($programs as $program): ?><option value="<?= (int) $program['id'] ?>"><?= e((string) $program['title']) ?></option><?php endforeach; ?></select></label>
        <label>Course Code<input name="course_code" placeholder="NAT-ACAD-001"></label>
        <label>Course Type<select name="course_type"><option value="course">Course</option><option value="webinar">Webinar</option><option value="workshop">Workshop</option><option value="certification">Certification</option><option value="orientation">Orientation</option></select></label>
        <label>Status<select name="status"><option value="active">Active</option><option value="draft">Draft</option><option value="paused">Paused</option><option value="archived">Archived</option></select></label>
        <label>Start Time<input type="datetime-local" name="start_time" value="<?= e(date('Y-m-d\TH:i', strtotime('+7 days 10:00'))) ?>"></label>
        <label>Duration Minutes<input type="number" name="duration_minutes" value="90"></label>
        <label>Price NGN<input type="number" name="price" min="0" step="100" value="0"></label>
        <label>Max Attendees<input type="number" name="max_attendees" value="250"></label>
        <label>Delivery Type<select name="delivery_type"><?php foreach (academy_delivery_types() as $key => $label): ?><option value="<?= e($key) ?>"><?= e($label) ?></option><?php endforeach; ?></select></label>
        <label>Delivery URL<input name="delivery_url" placeholder="https://..."></label>
      </div>
      <label>Title<input name="title" required></label>
      <label>Description<textarea name="description" required></textarea></label>
      <label>Delivery Instructions<textarea name="delivery_instructions"></textarea></label>
      <label>Prerequisites<textarea name="prerequisites"></textarea></label>
      <div class="academy-form-grid">
        <label>Pass Score<input type="number" name="pass_score" min="0" max="100" value="70"></label>
        <label>Instructor<input name="instructor_name"></label>
      </div>
      <label><input type="checkbox" name="is_free" checked> Free course</label>
      <label><input type="checkbox" name="certification_required"> Certification course</label>
      <label><input type="checkbox" name="certificate_approval_required"> Certificate requires admin approval</label>
      <fieldset><legend>RBAC Audience</legend><div class="academy-pillbox"><?php foreach ($roles as $role): ?><label><input type="checkbox" name="target_roles[]" value="<?= e($role) ?>"> <?= e(academy_role_label($role)) ?></label><?php endforeach; ?></div></fieldset>
      <button type="submit">Save Course</button>
    </form>
    <div class="academy-card-list">
      <?php foreach ($courses as $course): ?>
        <article>
          <h3><?= e((string) $course['title']) ?></h3>
          <p><?= e((string) $course['description']) ?></p>
          <div class="academy-mini">
            <small><strong>Program</strong><br><?= e((string) ($course['program_title'] ?? 'Unassigned')) ?></small>
            <small><strong>Audience</strong><br><?= e(academy_role_labels((string) ($course['target_roles'] ?? ''))) ?></small>
            <small><strong>Price</strong><br><?= (int) $course['is_free'] === 1 ? 'Free' : 'NGN ' . e(number_format((float) $course['price'], 2)) ?></small>
            <small><strong>Delivery</strong><br><?= e(academy_delivery_label((string) ($course['delivery_type'] ?? 'lms'))) ?></small>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
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
  <section class="academy-split">
    <form class="panel" method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="save_lesson">
      <h2>Add Lesson / Material</h2>
      <label>Course<select name="webinar_id" required><?php foreach ($courses as $course): ?><option value="<?= (int) $course['id'] ?>"><?= e((string) $course['title']) ?></option><?php endforeach; ?></select></label>
      <label>Title<input name="title" required></label>
      <label>Summary<textarea name="summary"></textarea></label>
      <label>Lesson Content<textarea name="content"></textarea></label>
      <div class="academy-form-grid">
        <label>Delivery Type<select name="delivery_type"><?php foreach (academy_delivery_types() as $key => $label): ?><option value="<?= e($key) ?>"><?= e($label) ?></option><?php endforeach; ?></select></label>
        <label>Material URL<input name="material_url"></label>
        <label>Duration<input type="number" name="duration_minutes" value="20"></label>
        <label>Sort<input type="number" name="sort_order" value="0"></label>
      </div>
      <label><input type="checkbox" name="is_required" checked> Required lesson</label>
      <label>Status<select name="status"><option value="active">Active</option><option value="draft">Draft</option><option value="paused">Paused</option><option value="archived">Archived</option></select></label>
      <button type="submit">Save Lesson</button>
    </form>
    <table><tr><th>Course</th><th>Lesson</th><th>Delivery</th><th>Status</th></tr><?php foreach ($lessons as $lesson): ?><tr><td><?= e((string) $lesson['course_title']) ?></td><td><strong><?= e((string) $lesson['title']) ?></strong><br><small><?= e((string) $lesson['summary']) ?></small></td><td><?= e(academy_delivery_label((string) $lesson['delivery_type'])) ?></td><td><?= e((string) $lesson['status']) ?></td></tr><?php endforeach; ?><?php if (!$lessons): ?><tr><td colspan="4">No lessons yet.</td></tr><?php endif; ?></table>
  </section>
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
        <div class="academy-form-grid"><label>A<input name="option_a" required></label><label>B<input name="option_b" required></label><label>C<input name="option_c"></label><label>D<input name="option_d"></label><label>Correct<select name="correct_option"><option>A</option><option>B</option><option>C</option><option>D</option></select></label><label>Points<input type="number" name="points" value="1"></label></div>
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

  </main>
</div>
<script>
document.querySelectorAll('[data-acad-menu]').forEach((button) => {
  button.addEventListener('click', (event) => {
    event.stopPropagation();
    const menu = button.closest('.acad-profile-menu');
    document.querySelectorAll('.acad-profile-menu.open').forEach((other) => { if (other !== menu) other.classList.remove('open'); });
    if (menu) menu.classList.toggle('open');
  });
});
document.addEventListener('click', () => document.querySelectorAll('.acad-profile-menu.open').forEach((menu) => menu.classList.remove('open')));
</script>
<?php admin_page_end(); ?>
