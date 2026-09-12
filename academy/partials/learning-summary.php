<?php
declare(strict_types=1);
?>
  <section class="card" style="margin-top:24px">
    <div class="card-h"><h3>Recently Completed Courses</h3><a class="link" href="dashboard.php?screen=certificates">Certificates</a></div>
    <?php if ($recentCompletedCourses): ?>
      <div class="completed-course-list">
        <?php foreach ($recentCompletedCourses as $done): ?>
          <?php
            $score = $done['best_score'] !== null ? number_format((float) $done['best_score'], 0) . '%' : '--';
            $certStatus = (string) ($done['certificate_record_status'] ?: $done['certificate_status']);
            $certStatus = $certStatus !== '' ? $certStatus : 'not_required';
            $certIssued = (string) ($done['certificate_record_status'] ?? '') === 'issued' && !empty($done['certificate_ref']);
          ?>
          <article class="completed-course">
            <h3><?= e((string) $done['course_title']) ?></h3>
            <div class="completed-meta">
              <div><strong><?= e($score) ?></strong><span>Best Score</span></div>
              <div><strong><?= (int) ($done['progress_percent'] ?? 0) ?>%</strong><span>Progress</span></div>
              <div><strong><?= e(!empty($done['completed_at']) ? date('M j, Y', strtotime((string) $done['completed_at'])) : 'Completed') ?></strong><span>Completed</span></div>
              <div><strong><?= e(ac_status($certStatus)) ?></strong><span>Certificate</span></div>
            </div>
            <div class="completed-actions">
              <?= ac_badge('completed', 'Completed') ?>
              <?= ac_badge($certStatus, 'Certificate: ' . ac_status($certStatus)) ?>
              <?php if ($certIssued): ?>
                <a class="btn btn-o btn-s" href="../dashboard/download-academy-certificate.php?ref=<?= urlencode((string) $done['certificate_ref']) ?>">Download Certificate</a>
              <?php elseif ($certStatus === 'eligible' || $certStatus === 'pending'): ?>
                <a class="btn btn-o btn-s" href="dashboard.php?screen=certificates">Open Certificates</a>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="empty">Complete a course and pass its assessment to see scores and certificate downloads here.</div>
    <?php endif; ?>
  </section>

  <section class="journey">
    <div class="journey-head">My Learning Journey</div>
    <div class="journey-steps">
      <?php foreach ($journeySteps as $idx => $step): ?>
        <a class="j-step <?= $step['status'] === 'completed' ? 'done' : ($step['status'] === 'current' ? 'cur' : '') ?>" href="dashboard.php?screen=<?= e((string) $step['screen']) ?><?= $courseId ? '&course_id=' . $courseId : '' ?>">
          <div class="j-ic"><?= $idx + 1 ?></div>
          <h5><?= e((string) $step['label']) ?></h5>
          <p><?= e((string) $step['note']) ?></p>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
  <footer class="footer"><div><i class="fas fa-shield-alt"></i> Secure / Transparent / Traceable</div><div>Empowering Growers. Building Sustainable Coconut Communities.</div><div>NATCODEV Academy</div></footer>
