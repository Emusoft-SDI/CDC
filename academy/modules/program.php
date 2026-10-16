<section class="grid g2">
    <article class="card">
      <?php $progThumb = function_exists('academy_program_thumbnail_src') ? academy_program_thumbnail_src($program) : ''; ?>
      <div style="display:flex;gap:14px;margin-bottom:14px">
        <?php if ($progThumb !== ''): ?>
          <img src="<?= e($progThumb) ?>" alt="<?= e((string) $program['title']) ?>" style="width:130px;height:90px;object-fit:cover;border-radius:8px;flex-shrink:0;box-shadow:0 2px 8px rgba(0,0,0,0.12)">
        <?php else: ?>
          <div style="width:120px;height:90px;background:linear-gradient(135deg,var(--green-700),var(--green-500));border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:38px;color:white;flex-shrink:0"><i class="fas fa-graduation-cap"></i></div>
        <?php endif; ?>
        <div><h3><?= e((string) $program['title']) ?></h3><p class="muted"><?= e((string) ($program['description'] ?? '')) ?></p><div class="actions"><?= ac_badge('active', ucfirst((string) ($program['status'] ?? 'active'))) ?><span class="badge-pill bp-blue"><?= e(academy_role_labels((string) ($program['audience_roles'] ?? 'all'))) ?></span></div></div>
      </div>
      <div class="grid g5" style="gap:8px;margin-bottom:14px"><div class="card"><strong>Audience</strong><br><span class="muted"><?= e(academy_role_labels((string) ($program['audience_roles'] ?? 'all'))) ?></span></div><div class="card"><strong>Courses</strong><br><?= count($programCourses) ?></div><div class="card"><strong>Free Courses</strong><br><?= count(array_filter($programCourses, static fn(array $c): bool => (int) ($c['is_free'] ?? 0) === 1)) ?></div><div class="card"><strong>Prerequisites</strong><br><?= trim((string) ($program['prerequisites'] ?? '')) !== '' ? 'Listed' : 'None listed' ?></div><div class="card"><strong>Path</strong><br><span class="muted">Sort order <?= (int) ($program['sort_order'] ?? 0) ?></span></div></div>

      <?php if (trim((string) ($program['prerequisites'] ?? '')) !== ''): ?>
        <div class="card-h" style="margin-top:18px"><h3>Prerequisites</h3></div>
        <div class="empty"><?= nl2br(e((string) $program['prerequisites'])) ?></div>
      <?php endif; ?>

      <div class="card-h" style="margin-top:18px"><h3>Program Courses</h3><span class="muted"><?= count($programCourses) ?> course<?= count($programCourses) === 1 ? '' : 's' ?></span></div>
      <?php if ($programCourses): ?>
        <div class="lesson-list">
          <?php foreach ($programCourses as $programCourse): ?>
            <div class="lesson-row">
              <div>
                <strong><?= e((string) $programCourse['title']) ?></strong><br>
                <span class="muted"><?= e((string) ($programCourse['category'] ?? 'Training')) ?> / <?= e(academy_delivery_label((string) ($programCourse['delivery_type'] ?? 'lms'))) ?> / <?= (int) ($programCourse['lessons'] ?? 0) ?> lessons</span>
              </div>
              <div class="actions">
                <?= (int) ($programCourse['is_free'] ?? 0) === 1 ? ac_badge('free', 'Free') : ac_badge('paid', ac_money((float) $programCourse['price'])) ?>
                <a class="btn btn-o btn-s" href="dashboard.php?screen=course&course_id=<?= (int) $programCourse['id'] ?>">Open</a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="empty">No courses are published in this program yet.</div>
      <?php endif; ?>
    </article>
    <article class="card">
      <div class="card-h"><h3>Apply to This Program</h3><a class="link" href="dashboard.php?screen=catalog">Catalog</a></div>
      <p class="muted">Enroll in the courses inside <?= e((string) $program['title']) ?> to start building the evidence for this operating path. Free courses unlock immediately.</p>
      <div class="actions" style="margin-top:14px">
        <a class="btn btn-p btn-full" href="dashboard.php?screen=catalog&program=<?= $programId ?>"><i class="fas fa-user-graduate"></i> Apply Now — Browse Courses</a>
        <a class="btn btn-o btn-full" href="../academy/program.php?program_id=<?= $programId ?>" target="_blank" rel="noopener"><i class="fas fa-arrow-up-right-from-square"></i> View Public Program Page</a>
      </div>
      <?php if ($registered): ?>
        <div class="card-h" style="margin-top:18px"><h3>Your Progress</h3><span class="muted"><?= count(array_filter($programCourses, static fn(array $c): bool => in_array((int) $c['id'], $registeredIds, true))) ?> enrolled</span></div>
        <div class="empty">You have enrolled in <?= count(array_filter($programCourses, static fn(array $c): bool => in_array((int) $c['id'], $registeredIds, true))) ?> of <?= count($programCourses) ?> course<?= count($programCourses) === 1 ? '' : 's' ?> in this program.</div>
      <?php endif; ?>
    </article>
  </section>
