<?php declare(strict_types=1);
/**
 * Reusable final-assessment form. Rendered on the Quiz
 * screen and inline at the end of the lesson flow, so the
 * assessment is the natural final step of the course.
 *
 * Requires: $assessment, $questions, $courseId.
 */
?>
<form class="exam-form" method="post">
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="action" value="submit_assessment">
  <input type="hidden" name="assessment_id" value="<?= (int) $assessment['id'] ?>">
  <?php if (!empty($isRetake) && !empty($currentAttempt)): ?>
    <div class="notice" style="background:#FFFBEA;color:#92400E;border:1px solid #FDE68A;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
      <div>
        <i class="fa-solid fa-rotate-right"></i> <strong>Retake Mode:</strong> Submitting will record and evaluate a new official assessment attempt. (Previous score: <?= number_format((float) $currentAttempt['score_percent'], 1) ?>% &mdash; <?= (int) $currentAttempt['passed'] === 1 ? 'Passed' : 'Did Not Pass' ?>).
      </div>
      <a class="btn btn-o btn-s" href="dashboard.php?screen=quiz&course_id=<?= $courseId ?>"><i class="fa-solid fa-arrow-left"></i> Return to Previous Review</a>
    </div>
  <?php endif; ?>
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
    <?php if (!empty($currentAttempt)): ?>
      <a class="btn btn-o" href="dashboard.php?screen=quiz&course_id=<?= $courseId ?>"><i class="fa-solid fa-clock-rotate-left"></i> View Previous Attempt</a>
    <?php endif; ?>
    <button type="submit" class="btn btn-p"><i class="fa-solid fa-paper-plane"></i> Submit Assessment</button>
  </div>
</form>
