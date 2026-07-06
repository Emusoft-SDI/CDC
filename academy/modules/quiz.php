<?php if (!$isCourseRegistered): ?>
    <section class="card"><div class="card-h"><h3>Enrollment Required</h3><a class="link" href="dashboard.php?screen=course&course_id=<?= $courseId ?>">Course Detail</a></div><p class="muted">Register for this course before taking the assessment.</p><a class="btn btn-p" href="dashboard.php?screen=course&course_id=<?= $courseId ?>">Register / Enroll</a></section>
  <?php else: ?>
    <section class="exam-shell">
      <article class="exam-hero">
        <div class="exam-hero-top">
          <div>
            <h3><?= $assessment ? e((string) $assessment['title']) : 'Assessment' ?></h3>
            <p class="muted"><?= e((string) ($course['title'] ?? 'Academy Course')) ?></p>
          </div>
          <a class="btn btn-o" href="dashboard.php?screen=lesson&course_id=<?= $courseId ?>"><i class="fa-solid fa-book-open"></i> Lessons</a>
        </div>
        <div class="exam-stats">
          <div class="exam-stat"><strong><?= count($questions) ?></strong><span>Questions</span></div>
          <div class="exam-stat"><strong><?= $assessment ? e((string) $assessment['pass_score']) : '--' ?>%</strong><span>Pass Mark</span></div>
          <div class="exam-stat"><strong><?= $assessment ? (int) $assessment['max_attempts'] : 0 ?></strong><span>Attempts</span></div>
          <div class="exam-stat"><strong><?= count($completedLessonIds) ?>/<?= count($lessons) ?></strong><span>Lessons Complete</span></div>
        </div>
      </article>

      <?php if ($assessment && $questions): ?>
        <form class="exam-form" method="post">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="submit_assessment">
          <input type="hidden" name="assessment_id" value="<?= (int) $assessment['id'] ?>">
          <div class="exam-instructions">
            <?= e((string) $assessment['instructions']) ?> Pass score: <?= e((string) $assessment['pass_score']) ?>%.
          </div>
          <?php foreach ($questions as $index => $question): ?>
            <article class="exam-question">
              <div class="exam-question-head">
                <span class="exam-number"><?= $index + 1 ?></span>
                <div>
                  <small>Question <?= $index + 1 ?> of <?= count($questions) ?></small>
                  <h3><?= e((string) $question['question_text']) ?></h3>
                </div>
              </div>
              <div class="exam-options">
                <?php foreach (['A' => 'option_a', 'B' => 'option_b', 'C' => 'option_c', 'D' => 'option_d'] as $letter => $field): ?>
                  <?php if (trim((string) $question[$field]) !== ''): ?>
                    <label class="quiz-option">
                      <input type="radio" name="answers[<?= (int) $question['id'] ?>]" value="<?= e($letter) ?>" required>
                      <span class="option-letter"><?= e($letter) ?></span>
                      <span class="option-text"><?= e((string) $question[$field]) ?></span>
                    </label>
                  <?php endif; ?>
                <?php endforeach; ?>
              </div>
            </article>
          <?php endforeach; ?>
          <div class="exam-submit">
            <a class="btn btn-o" href="dashboard.php?screen=lesson&course_id=<?= $courseId ?>">Review Lessons</a>
            <button type="submit"><i class="fa-solid fa-paper-plane"></i> Submit Assessment</button>
          </div>
        </form>
      <?php else: ?>
        <div class="empty exam-empty">No active assessment is available for this course yet.</div>
      <?php endif; ?>
    </section>
  <?php endif; ?>