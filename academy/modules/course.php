<section class="grid g2">
    <article class="card">
      <?php 
        $courseThumb = function_exists('academy_course_artwork_url') 
            ? academy_course_artwork_url($course) 
            : (function_exists('academy_course_thumbnail_src') ? academy_course_thumbnail_src($course) : '../assets/academy/course-grower-foundation.svg');
        $firstLessonId = !empty($lessons[0]['id']) ? (int) $lessons[0]['id'] : 0;
        $activeLessonUrl = 'dashboard.php?screen=lesson&course_id=' . $courseId . ($firstLessonId > 0 ? '&lesson_id=' . $firstLessonId : '');
      ?>
      <div class="card-h">
        <h3>Course Detail</h3>
        <a class="btn btn-p btn-sm" href="<?= e($activeLessonUrl) ?>"><i class="fas fa-play"></i> Open Training</a>
      </div>
      
      <!-- 16:9 Course Artwork Banner -->
      <div class="course-banner-169" style="position:relative;width:100%;aspect-ratio:16/9;max-height:260px;border-radius:12px;overflow:hidden;margin-bottom:18px;box-shadow:0 4px 16px rgba(0,0,0,0.12);background:linear-gradient(135deg,var(--green-700),var(--green-500))">
        <img src="<?= e($courseThumb) ?>" alt="<?= e((string) $course['title']) ?>" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover">
        <div style="position:absolute;inset:0;background:linear-gradient(to top, rgba(16,37,22,0.92) 0%, rgba(16,37,22,0.35) 60%, transparent 100%);display:flex;align-items:flex-end;padding:18px 22px">
          <div>
            <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px;flex-wrap:wrap">
              <?= ac_badge((int) $course['is_free'] === 1 ? 'free' : 'paid', (int) $course['is_free'] === 1 ? 'Free Course' : ac_money((float) $course['price'])) ?>
              <?= ac_badge('active', academy_delivery_label((string) ($course['delivery_type'] ?? 'lms'))) ?>
              <?php if ($isCourseRegistered): ?>
                <span class="badge" style="background:#27ae60;color:#fff"><i class="fas fa-check-circle"></i> Enrolled</span>
              <?php endif; ?>
            </div>
            <h2 style="margin:0 0 4px;color:#fff;font-size:22px;text-shadow:0 2px 4px rgba(0,0,0,0.6)"><?= e((string) $course['title']) ?></h2>
            <div style="color:#d8e8d8;font-size:13px"><?= e((string) ($course['program_title'] ?? 'NATCODEV Academy')) ?> &bull; <?= e(academy_role_labels((string) ($course['target_roles'] ?? 'all'))) ?></div>
          </div>
        </div>
      </div>
      <p class="muted" style="margin-bottom:16px"><?= e((string) ($course['description'] ?? '')) ?></p>
      
      <div class="grid g5" style="gap:8px;margin-bottom:14px"><div class="card"><strong>Audience</strong><br><span class="muted"><?= e(academy_role_labels((string) ($course['target_roles'] ?? 'all'))) ?></span></div><div class="card"><strong>Lessons</strong><br><?= (int) ($course['lessons'] ?? count($lessons)) ?></div><div class="card"><strong>Assessments</strong><br><?= (int) ($course['assessments'] ?? ($assessment ? 1 : 0)) ?></div><div class="card"><strong>Study Time</strong><br>~<?= (int) ($course['duration_minutes'] ?? 0) ?> min</div><div class="card"><strong>Certificate</strong><br><?= (int) ($course['certification_required'] ?? 0) === 1 ? 'Yes' : 'Learning only' ?></div></div>
      <?php if (!empty($course['delivery_instructions'])): ?><div class="empty"><?= nl2br(e((string) $course['delivery_instructions'])) ?></div><?php endif; ?>

      <div class="card-h" style="margin-top:18px"><h3>Course Outline</h3><span class="muted"><?= count($lessons) ?> lessons</span></div>
      <?php if ($lessons): ?>
        <div class="lesson-list">
          <?php $outlineIndex = 0; foreach ($lessons as $outlineLesson): $outlineIndex += 1; ?>
            <div class="lesson-row">
              <div>
                <strong><?= $outlineIndex ?>. <?= e((string) $outlineLesson['title']) ?></strong><br>
                <span class="muted"><?= academy_delivery_label((string) $outlineLesson['delivery_type']) ?> / ~<?= (int) $outlineLesson['duration_minutes'] ?> min read</span>
                <?php if ((string) ($outlineLesson['summary'] ?? '') !== ''): ?><br><span class="muted"><?= e((string) $outlineLesson['summary']) ?></span><?php endif; ?>
              </div>
              <div class="actions">
                <a class="btn btn-o btn-s" href="dashboard.php?screen=lesson&course_id=<?= $courseId ?>&lesson_id=<?= (int) $outlineLesson['id'] ?>"><i class="fas fa-play"></i> Start</a>
                <?php if ($isCourseRegistered && in_array((int) $outlineLesson['id'], $completedLessonIds, true)): ?>
                  <?= ac_badge('completed', 'Done') ?>
                <?php elseif ($isCourseRegistered): ?>
                  <?= ac_badge('not_started', 'Pending') ?>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="actions" style="margin-top:14px">
          <a class="btn btn-p" href="<?= e($activeLessonUrl) ?>"><i class="fas fa-play-circle"></i> Open Training / Start Learning</a>
          <a class="btn btn-o" href="download-course.php?course_id=<?= $courseId ?>" target="_blank" rel="noopener"><i class="fas fa-file-pdf" style="color:#e74c3c"></i> Download Course Pack (PDF)</a>
          <a class="link" href="download-course.php?course_id=<?= $courseId ?>&format=txt" target="_blank" rel="noopener" style="font-size:12px;align-self:center"><i class="fas fa-file-lines"></i> Plain Text</a>
          <?php if ($assessment && $questions && $allLessonsDone): ?>
            <a class="btn btn-p" href="dashboard.php?screen=lesson&course_id=<?= $courseId ?>"><i class="fas fa-check-double"></i> Finish Course &amp; Take Assessment</a>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div class="empty">No lessons have been added to this course yet.</div>
      <?php endif; ?>

      <?php if (!empty($lessonsMaterials)): ?>
        <div class="card-h" style="margin-top:22px"><h3>Course Materials &amp; Resources</h3><span class="muted"><?= count($lessonsMaterials) ?> resources</span></div>
        <div class="lesson-list">
          <?php foreach ($lessonsMaterials as $materialRow): ?>
            <div class="lesson-row">
              <div>
                <strong><i class="fas <?= str_contains(strtolower((string) ($materialRow['material_type'] ?? '')), 'checklist') ? 'fa-list-check' : (str_contains(strtolower((string) ($materialRow['material_type'] ?? '')), 'template') ? 'fa-file-lines' : (str_contains(strtolower((string) ($materialRow['material_type'] ?? '')), 'guide') ? 'fa-book-bookmark' : 'fa-paperclip')) ?>"></i> <?= e((string) $materialRow['title']) ?></strong>
                <span class="badge" style="margin-left:6px;font-size:11px"><?= e(academy_material_label((string) ($materialRow['material_type'] ?? 'link'))) ?></span>
                <?php if (!empty($materialRow['notes'])): ?><br><span class="muted"><?= e((string) $materialRow['notes']) ?></span><?php endif; ?>
              </div>
              <div class="actions">
                <?php if (!empty($materialRow['material_url']) && (!str_contains((string) $materialRow['material_url'], 'download-course.php') || $isCourseRegistered)): ?>
                  <a class="btn btn-o btn-s" href="<?= e((string) $materialRow['material_url']) ?>" target="_blank" rel="noopener"><i class="fas fa-arrow-up-right-from-square"></i> Open</a>
                <?php else: ?>
                  <a class="btn btn-o btn-s" href="download-course.php?course_id=<?= $courseId ?>" target="_blank" rel="noopener"><i class="fas fa-file-pdf" style="color:#e74c3c"></i> PDF</a>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </article>
    <article class="card">
      <div class="card-h"><h3>Enroll</h3><a class="link" href="dashboard.php?screen=catalog">Catalog</a></div>
      <p class="muted">Free courses unlock immediately. Paid courses can be paid from wallet or directly with Monnify. Once enrolled you can read every lesson, listen to it, and download the whole course pack.</p>
      <?php if (in_array($courseId, $registeredIds, true)): ?>
        <div class="notice ok">You are already registered for this course.</div>
        <div class="actions">
          <a class="btn btn-p btn-full" href="<?= e($activeLessonUrl) ?>"><i class="fas fa-play-circle"></i> Open Training / Start Learning</a>
          <a class="btn btn-o btn-full" href="download-course.php?course_id=<?= $courseId ?>" target="_blank" rel="noopener"><i class="fas fa-file-pdf" style="color:#e74c3c"></i> Download Course Pack (PDF)</a>
        </div>
      <?php elseif ((int) $course['is_free'] === 1): ?>
        <div class="actions" style="margin-bottom:10px">
          <a class="btn btn-p btn-full" href="<?= e($activeLessonUrl) ?>"><i class="fas fa-play-circle"></i> Open Training / Start Learning</a>
        </div>
        <form method="post" action="../api/register-webinar.php"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="return_to" value="../academy/<?= e($activeLessonUrl) ?>"><input type="hidden" name="webinar_id" value="<?= $courseId ?>"><button class="btn-full" type="submit">Enroll Free &amp; Start Learning</button></form>
      <?php else: ?>
        <div class="actions" style="margin-bottom:12px">
          <a class="btn btn-p btn-full" href="<?= e($activeLessonUrl) ?>"><i class="fas fa-play-circle"></i> Open Training / Start Learning</a>
        </div>
        <div class="info-row"><span>Course Price</span><strong><?= e(ac_money((float) $course['price'])) ?></strong></div><div class="info-row"><span>Wallet Balance</span><strong><?= e(ac_money($walletBalance)) ?></strong></div>
        <div class="actions"><form method="post" action="../api/register-webinar.php"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="return_to" value="../academy/<?= e($activeLessonUrl) ?>"><input type="hidden" name="webinar_id" value="<?= $courseId ?>"><input type="hidden" name="payment_method" value="wallet"><button type="submit" <?= $walletBalance >= (float) $course['price'] ? '' : 'disabled' ?>>Pay From Wallet</button></form><form method="post" action="../api/register-webinar.php"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="return_to" value="../academy/<?= e($activeLessonUrl) ?>"><input type="hidden" name="webinar_id" value="<?= $courseId ?>"><input type="hidden" name="payment_method" value="monnify_direct"><button type="submit">Pay Direct</button></form><a class="btn btn-o" href="dashboard.php?screen=transactions">Fund Wallet</a></div>
      <?php endif; ?>
    </article>
  </section>

