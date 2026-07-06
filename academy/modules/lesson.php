<?php if (!$isCourseRegistered): ?>
    <section class="card"><div class="card-h"><h3>Enrollment Required</h3><a class="link" href="dashboard.php?screen=course&course_id=<?= $courseId ?>">Course Detail</a></div><p class="muted">Register for this course before opening lessons, tracking progress, taking the assessment, or requesting a certificate.</p><a class="btn btn-p" href="dashboard.php?screen=course&course_id=<?= $courseId ?>">Register / Enroll</a></section>
  <?php else: ?>
    <section class="grid g2">
      <article class="card span2">
        <div class="card-h"><h3><?= e((string) $course['title']) ?></h3><a class="link" href="dashboard.php?screen=quiz&course_id=<?= $courseId ?>">Take Assessment</a></div>
        <?php if ($selectedLesson): ?>
          <h3><?= e((string) $selectedLesson['title']) ?></h3>
          <p class="muted"><?= e((string) ($selectedLesson['summary'] ?? '')) ?></p>
          <?php if (!empty($selectedLesson['material_url'])): ?><iframe class="lesson-frame" src="<?= e((string) $selectedLesson['material_url']) ?>" title="<?= e((string) $selectedLesson['title']) ?>"></iframe><?php endif; ?>
          <?php if (!empty($selectedLesson['content'])): ?><div class="empty" style="margin-top:12px"><?= nl2br(e((string) $selectedLesson['content'])) ?></div><?php endif; ?>
          <div class="actions">
            <?php if (!empty($selectedLesson['material_url'])): ?><a class="btn btn-o" href="<?= e((string) $selectedLesson['material_url']) ?>" target="_blank" rel="noopener"><?= e(academy_delivery_action((string) $selectedLesson['delivery_type'])) ?></a><?php endif; ?>
            <?php if (in_array((int) $selectedLesson['id'], $completedLessonIds, true)): ?><?= ac_badge('completed', 'Lesson Complete') ?><?php else: ?><form method="post"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="complete_lesson"><input type="hidden" name="lesson_id" value="<?= (int) $selectedLesson['id'] ?>"><button type="submit">Mark Lesson Complete</button></form><?php endif; ?>
          </div>
        <?php else: ?>
          <div class="empty">No lessons have been added yet. Use the course delivery link/instructions from Course Detail.</div>
        <?php endif; ?>
      </article>
      <article class="card span2"><div class="card-h"><h3>Course Lessons</h3><a class="link" href="dashboard.php?screen=learning&course_id=<?= $courseId ?>">My Learning</a></div><div class="lesson-list">
        <?php foreach ($lessons as $lesson): ?><div class="lesson-row <?= $selectedLesson && (int) $selectedLesson['id'] === (int) $lesson['id'] ? 'active' : '' ?>"><div><strong><?= e((string) $lesson['title']) ?></strong><br><span class="muted"><?= e(academy_delivery_label((string) $lesson['delivery_type'])) ?> / <?= (int) $lesson['duration_minutes'] ?> min</span></div><div class="actions"><a class="btn btn-o btn-s" href="dashboard.php?screen=lesson&course_id=<?= $courseId ?>&lesson_id=<?= (int) $lesson['id'] ?>">Open</a><?= in_array((int) $lesson['id'], $completedLessonIds, true) ? ac_badge('completed', 'Done') : ac_badge('not_started', 'Pending') ?></div></div><?php endforeach; ?>
      </div></article>
    </section>
  <?php endif; ?>