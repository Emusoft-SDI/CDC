<?php
declare(strict_types=1);

if (!$isCourseRegistered && $courseId > 0 && !empty($user['id'])) {
    try {
        $certReq = (int) ($course['certification_required'] ?? 0) === 1 ? 'not_started' : 'not_required';
        $payStatus = (int) ($course['is_free'] ?? 0) === 1 ? 'free' : 'enrolled';
        $pdo->prepare("
            INSERT INTO webinar_registrations (webinar_id, user_id, payment_status, progress_percent, completion_status, certificate_status, registered_at)
            VALUES (?, ?, ?, 0, 'registered', ?, NOW())
            ON DUPLICATE KEY UPDATE id=id
        ")->execute([$courseId, (int) $user['id'], $payStatus, $certReq]);
        $registered = academy_registered_courses($pdo, (int) $user['id']);
        $registeredIds = array_map(static fn(array $row): int => (int) $row['id'], $registered);
        $isCourseRegistered = true;
    } catch (Throwable $e) {
    }
}
?>
<?php if (!$isCourseRegistered): ?>
  <section class="card">
    <div class="card-h">
      <h3>Enrollment Required</h3>
      <a class="link" href="dashboard.php?screen=course&course_id=<?= $courseId ?>">Course Detail</a>
    </div>
    <p class="muted">Register for this course before taking the assessment.</p>
    <a class="btn btn-p" href="dashboard.php?screen=course&course_id=<?= $courseId ?>">Register / Enroll</a>
  </section>
<?php else: ?>
  <section class="exam-shell">
    <article class="exam-hero">
      <div class="exam-hero-top">
        <div>
          <h3><?= $assessment ? e((string) $assessment['title']) : 'Assessment' ?></h3>
          <p class="muted"><?= e((string) ($course['title'] ?? 'Academy Course')) ?></p>
        </div>
        <div class="actions" style="margin-top:0">
          <a class="btn btn-o" href="dashboard.php?screen=lesson&course_id=<?= $courseId ?>"><i class="fa-solid fa-book-open"></i> Lessons</a>
          <?php if (!empty($currentAttempt) && empty($isRetake)): ?>
            <a class="btn btn-p" href="dashboard.php?screen=quiz&course_id=<?= $courseId ?>&retake=1"><i class="fa-solid fa-rotate-right"></i> Retake Assessment</a>
          <?php elseif (!empty($currentAttempt) && !empty($isRetake)): ?>
            <a class="btn btn-o" href="dashboard.php?screen=quiz&course_id=<?= $courseId ?>"><i class="fa-solid fa-clock-rotate-left"></i> View Previous Attempt</a>
          <?php endif; ?>
        </div>
      </div>
      <div class="exam-stats">
        <div class="exam-stat"><strong><?= count($questions) ?></strong><span>Questions</span></div>
        <div class="exam-stat"><strong><?= $assessment ? e((string) $assessment['pass_score']) : '--' ?>%</strong><span>Pass Mark</span></div>
        <div class="exam-stat"><strong><?= count($allAttempts) ?></strong><span>Attempts Recorded</span></div>
        <div class="exam-stat"><strong><?= count($completedLessonIds) ?>/<?= count($lessons) ?></strong><span>Lessons Complete</span></div>
      </div>
    </article>

    <?php if (!$allLessonsDone && $lessons): ?>
      <div class="notice">
        Finish all <?= count($lessons) ?> lessons to complete the course. The assessment is the final step &mdash; you have completed <?= count($completedLessonIds) ?> of <?= count($lessons) ?> lessons.
      </div>
      <div class="prog" style="max-width:420px;margin-bottom:16px">
        <div class="prog-f" style="width:<?= $totalLessons > 0 ? max(0, min(100, (int) round((count($completedLessonIds) / $totalLessons) * 100))) : 0 ?>%"></div>
      </div>
    <?php endif; ?>

    <?php if ($assessment && $questions): ?>
      <?php if (!empty($currentAttempt) && empty($isRetake)): ?>
        <?php
          // Parse user answers from attempt JSON
          $attemptAnswers = [];
          if (!empty($currentAttempt['answers'])) {
              $decoded = json_decode((string) $currentAttempt['answers'], true);
              if (is_array($decoded)) {
                  $attemptAnswers = $decoded;
              }
          }

          $attemptScore = (float) ($currentAttempt['score_percent'] ?? 0);
          $attemptPassed = (int) ($currentAttempt['passed'] ?? 0) === 1;
          $passMark = (float) ($assessment['pass_score'] ?? 75);

          $totalPointsEarned = 0.0;
          $totalPointsPossible = 0.0;
          $totalCorrect = 0;
          $totalMissed = 0;

          $questionReviewData = [];
          foreach ($questions as $index => $q) {
              $qId = (int) $q['id'];
              $pts = (float) ($q['points'] ?? 1);
              $totalPointsPossible += $pts;
              $learnerAnswer = strtoupper(trim((string) ($attemptAnswers[$qId] ?? $attemptAnswers[(string) $qId] ?? '')));
              if ($learnerAnswer === '') {
                  $idxKey = $index + 1;
                  $learnerAnswer = strtoupper(trim((string) ($attemptAnswers[$idxKey] ?? $attemptAnswers[(string) $idxKey] ?? '')));
              }
              if (str_starts_with($learnerAnswer, 'OPTION_')) {
                  $learnerAnswer = substr($learnerAnswer, -1);
              }
              if (strlen($learnerAnswer) > 1) {
                  $learnerAnswer = substr($learnerAnswer, 0, 1);
              }

              $correctAnswer = strtoupper(trim((string) ($q['correct_option'] ?? 'A')));
              if (str_starts_with($correctAnswer, 'OPTION_')) {
                  $correctAnswer = substr($correctAnswer, -1);
              }
              if (strlen($correctAnswer) > 1) {
                  $correctAnswer = substr($correctAnswer, 0, 1);
              }

              $isCorrect = ($learnerAnswer !== '' && $learnerAnswer === $correctAnswer);
              if ($isCorrect) {
                  $totalPointsEarned += $pts;
                  $totalCorrect++;
              } else {
                  $totalMissed++;
              }
              $questionReviewData[] = [
                  'question' => $q,
                  'user_answer' => $learnerAnswer,
                  'correct_answer' => $correctAnswer,
                  'is_correct' => $isCorrect,
                  'points' => $pts,
              ];
          }

          $isCertTrack = (int) ($course['certification_required'] ?? 0) === 1;
          $certIssued = (!empty($currentCourseCertificate) && ($currentCourseCertificate['status'] ?? '') === 'issued') || (($currentRegistration['certificate_status'] ?? '') === 'issued');
          $certPending = (!empty($currentCourseCertificate) && ($currentCourseCertificate['status'] ?? '') === 'pending') || (($currentRegistration['certificate_status'] ?? '') === 'pending');
          $certEligible = (!empty($currentCertificateEligibility['eligible']) || (($currentRegistration['certificate_status'] ?? '') === 'eligible')) && !$certIssued && !$certPending;
          $remainingLessonsCount = max(0, count($lessons) - count($completedLessonIds));
        ?>

        <?php if (count($allAttempts) > 1): ?>
          <div class="attempt-history-bar">
            <span class="attempt-history-title"><i class="fa-solid fa-clock-rotate-left"></i> Attempt History (<?= count($allAttempts) ?>):</span>
            <div class="attempt-pills" role="navigation" aria-label="Attempt History">
              <?php foreach ($allAttempts as $attIdx => $attRow): ?>
                <?php
                  $isThisCurrent = (int) $attRow['id'] === (int) $currentAttempt['id'];
                  $attPassed = (int) $attRow['passed'] === 1;
                  $attNum = count($allAttempts) - $attIdx;
                ?>
                <a href="dashboard.php?screen=quiz&course_id=<?= $courseId ?>&attempt_id=<?= (int) $attRow['id'] ?>"
                   class="attempt-pill <?= $isThisCurrent ? 'active' : '' ?> <?= $attPassed ? 'passed' : 'failed' ?>"
                   <?= $isThisCurrent ? 'aria-current="page"' : '' ?>>
                  <span>Attempt #<?= $attNum ?>:</span>
                  <strong><?= number_format((float) $attRow['score_percent'], 1) ?>%</strong>
                  <span class="pill-badge"><?= $attPassed ? 'Passed' : 'Missed' ?></span>
                </a>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <?php if ($attemptPassed): ?>
          <!-- Executive Celebratory Congratulations Card -->
          <article class="congrats-card">
            <div class="congrats-banner">
              <div class="congrats-trophy-badge">
                <i class="fa-solid fa-trophy"></i>
              </div>
              <div class="congrats-intro">
                <div class="congrats-status-row">
                  <span class="badge-pill bp-green"><i class="fa-solid fa-circle-check"></i> ASSESSMENT PASSED</span>
                  <span class="congrats-date">Completed <?= date('F j, Y, g:i A', strtotime((string) $currentAttempt['completed_at'])) ?></span>
                </div>
                <h2>Congratulations, <?= e((string) ($user['name'] ?? 'Learner')) ?>!</h2>
                <p class="congrats-lead">
                  Outstanding performance! You achieved a verified score of <strong><?= number_format($attemptScore, 1) ?>%</strong> (Pass threshold: <?= $passMark ?>%). You have demonstrated mastery of the core competencies for <strong><?= e((string) ($course['title'] ?? 'this course')) ?></strong>.
                </p>
              </div>
            </div>

            <div class="congrats-metrics">
              <div class="c-metric">
                <span class="c-label">Final Score</span>
                <strong class="c-value text-green"><?= number_format($attemptScore, 1) ?>%</strong>
                <span class="c-sub">Threshold: <?= $passMark ?>%</span>
              </div>
              <div class="c-metric">
                <span class="c-label">Points Earned</span>
                <strong class="c-value"><?= ($totalPointsEarned == (int)$totalPointsEarned ? (int)$totalPointsEarned : number_format($totalPointsEarned, 1)) ?> / <?= ($totalPointsPossible == (int)$totalPointsPossible ? (int)$totalPointsPossible : number_format($totalPointsPossible, 1)) ?></strong>
                <span class="c-sub">Total Weighted Points</span>
              </div>
              <div class="c-metric">
                <span class="c-label">Accuracy</span>
                <strong class="c-value"><?= $totalCorrect ?> / <?= count($questions) ?></strong>
                <span class="c-sub">Questions Passed</span>
              </div>
              <div class="c-metric">
                <span class="c-label">Lessons Status</span>
                <strong class="c-value"><?= count($completedLessonIds) ?> / <?= count($lessons) ?></strong>
                <span class="c-sub"><?= $remainingLessonsCount === 0 ? 'All Lessons Complete' : $remainingLessonsCount . ' Lesson(s) Remaining' ?></span>
              </div>
            </div>

            <div class="congrats-action-box">
              <h4 style="margin:0 0 10px;color:#0F3D1B;font-size:15px;font-weight:900"><i class="fa-solid fa-compass" style="color:var(--green-700)"></i> Next Steps &amp; Credentials</h4>
              <div class="congrats-actions-grid">
                <?php if ($certIssued): ?>
                  <div class="next-step-card highlight">
                    <div class="ns-icon"><i class="fa-solid fa-award"></i></div>
                    <div class="ns-content">
                      <strong>Official Verified Certificate Ready</strong>
                      <p>Your official credential has been issued. You can view, verify, and download your branded PDF certificate.</p>
                      <div class="actions">
                        <a class="btn btn-p" href="../dashboard/download-academy-certificate.php?ref=<?= urlencode((string) ($currentCourseCertificate['certificate_ref'] ?? '')) ?>"><i class="fa-solid fa-download"></i> Download Certificate</a>
                        <a class="btn btn-o" href="dashboard.php?screen=certificates"><i class="fa-solid fa-award"></i> View &amp; Download Certificate</a>
                      </div>
                    </div>
                  </div>
                <?php elseif ($certPending): ?>
                  <div class="next-step-card">
                    <div class="ns-icon"><i class="fa-solid fa-hourglass-half"></i></div>
                    <div class="ns-content">
                      <strong>Certificate Approval in Progress</strong>
                      <p>Your certificate application is currently pending academic board review. Check your certification dashboard for live status updates.</p>
                      <div class="actions">
                        <a class="btn btn-o" href="dashboard.php?screen=certificates"><i class="fa-solid fa-award"></i> Check Certificate Status</a>
                      </div>
                    </div>
                  </div>
                <?php elseif ($certEligible): ?>
                  <div class="next-step-card highlight">
                    <div class="ns-icon"><i class="fa-solid fa-certificate"></i></div>
                    <div class="ns-content">
                      <strong>Certificate Unlocked &amp; Eligible</strong>
                      <p>You have met both lesson progression and assessment criteria! Claim your verified certificate now.</p>
                      <div class="actions">
                        <a class="btn btn-p" href="dashboard.php?screen=certificates" style="background:var(--green-700);color:#fff"><i class="fa-solid fa-award"></i> Claim Your Certificate</a>
                      </div>
                    </div>
                  </div>
                <?php endif; ?>

                <?php if ($remainingLessonsCount > 0): ?>
                  <div class="next-step-card alert">
                    <div class="ns-icon"><i class="fa-solid fa-book-open-reader"></i></div>
                    <div class="ns-content">
                      <strong><?= $isCertTrack ? 'Finish Remaining Lessons to Unlock Certificate' : 'Complete Remaining Lessons in Course' ?></strong>
                      <p>You achieved a passing exam score! Complete the remaining <strong><?= $remainingLessonsCount ?></strong> lesson(s) <?= $isCertTrack ? 'to unlock certificate eligibility' : 'to finish this entire curriculum' ?>.</p>
                      <div class="actions">
                        <a class="btn btn-p" href="dashboard.php?screen=lesson&course_id=<?= $courseId ?>"><i class="fa-solid fa-play"></i> Resume Lessons (<?= $remainingLessonsCount ?> remaining)</a>
                      </div>
                    </div>
                  </div>
                <?php elseif (!$isCertTrack): ?>
                  <div class="next-step-card highlight">
                    <div class="ns-icon"><i class="fa-solid fa-graduation-cap"></i></div>
                    <div class="ns-content">
                      <strong>Course Curriculum Completed</strong>
                      <p>You have completed all lessons and passed the final assessment! Download your participation documents below.</p>
                    </div>
                  </div>
                <?php endif; ?>

                <div class="next-step-card">
                  <div class="ns-icon"><i class="fa-solid fa-file-pdf"></i></div>
                  <div class="ns-content">
                    <strong>Official Course Pack &amp; Participation Pack</strong>
                    <p>Download your official branded study pack, curriculum outline, references, and verified participation document.</p>
                    <div class="actions">
                      <a class="btn btn-o" href="download-course.php?course_id=<?= $courseId ?>" target="_blank"><i class="fa-solid fa-file-arrow-down"></i> Download Participation Pack</a>
                    </div>
                  </div>
                </div>

                <div class="next-step-card">
                  <div class="ns-icon"><i class="fa-solid fa-rotate-right"></i></div>
                  <div class="ns-content">
                    <strong>Optional Score Improvement / Practice Retake</strong>
                    <p>Learners may retake the assessment at any time to reinforce knowledge or target a 100% score.</p>
                    <div class="actions">
                      <a class="btn btn-o" href="dashboard.php?screen=quiz&course_id=<?= $courseId ?>&retake=1"><i class="fa-solid fa-rotate-right"></i> Retake Assessment</a>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </article>
        <?php else: ?>
          <!-- Clear Result Card for Missed / Below Pass Mark -->
          <article class="card result-card-failed">
            <div class="failed-banner">
              <div class="failed-icon-badge">
                <i class="fa-solid fa-circle-exclamation"></i>
              </div>
              <div class="failed-intro">
                <div class="failed-status-row">
                  <span class="badge-pill bp-red"><i class="fa-solid fa-xmark"></i> PASS THRESHOLD NOT REACHED</span>
                  <span class="failed-date">Attempt submitted <?= date('F j, Y, g:i A', strtotime((string) $currentAttempt['completed_at'])) ?></span>
                </div>
                <h2>Score: <?= number_format($attemptScore, 1) ?>% (Pass Mark: <?= $passMark ?>%)</h2>
                <p class="failed-lead">
                  You did not achieve the required pass mark of <?= $passMark ?>% on this attempt. Don't worry! Review the detailed answers and pedagogical rationales below to understand where you can improve, review the course lessons, and retake the assessment when you're ready.
                </p>
                <?php if (!empty($currentAssessmentPassed)): ?>
                  <div class="notice ok" style="margin-top:12px;background:#F0FDF4;border:1px solid #86EFAC;color:#166534">
                    <i class="fa-solid fa-circle-check"></i> <strong>Passed on another attempt:</strong> You achieved a passing score on a different attempt in your history (<?= count($allAttempts) ?> recorded). Your course completion and certification status remain secured.
                  </div>
                <?php endif; ?>
              </div>
            </div>

            <div class="failed-metrics">
              <div class="c-metric">
                <span class="c-label">Achieved Score</span>
                <strong class="c-value text-red"><?= number_format($attemptScore, 1) ?>%</strong>
                <span class="c-sub">Required: <?= $passMark ?>%</span>
              </div>
              <div class="c-metric">
                <span class="c-label">Points Earned</span>
                <strong class="c-value"><?= ($totalPointsEarned == (int)$totalPointsEarned ? (int)$totalPointsEarned : number_format($totalPointsEarned, 1)) ?> / <?= ($totalPointsPossible == (int)$totalPointsPossible ? (int)$totalPointsPossible : number_format($totalPointsPossible, 1)) ?></strong>
                <span class="c-sub">Points Recorded</span>
              </div>
              <div class="c-metric">
                <span class="c-label">Correct Answers</span>
                <strong class="c-value text-green"><?= $totalCorrect ?> / <?= count($questions) ?></strong>
                <span class="c-sub">Questions Passed</span>
              </div>
              <div class="c-metric">
                <span class="c-label">Missed Answers</span>
                <strong class="c-value text-red"><?= $totalMissed ?> / <?= count($questions) ?></strong>
                <span class="c-sub">Questions to Review</span>
              </div>
            </div>

            <div class="actions" style="margin-top:16px;gap:10px">
              <a class="btn btn-p" href="dashboard.php?screen=quiz&course_id=<?= $courseId ?>&retake=1"><i class="fa-solid fa-rotate-right"></i> Retake Assessment Now</a>
              <a class="btn btn-o" href="dashboard.php?screen=lesson&course_id=<?= $courseId ?>"><i class="fa-solid fa-book-open"></i> Review Course Lessons</a>
              <a class="btn btn-o" href="download-course.php?course_id=<?= $courseId ?>" target="_blank"><i class="fa-solid fa-file-pdf"></i> Download Course Pack</a>
            </div>
          </article>
        <?php endif; ?>

        <!-- Post-Submission Question-by-Question Detailed Review -->
        <section class="review-section">
          <div class="review-head">
            <div>
              <h3>Question-by-Question Review &amp; Pedagogical Feedback</h3>
              <p class="muted">Examine your submitted responses alongside correct answers and educational rationales.</p>
            </div>
            <div class="review-summary-pills">
              <span class="badge-pill bp-green"><i class="fa-solid fa-check"></i> <?= $totalCorrect ?> Correct</span>
              <?php if ($totalMissed > 0): ?>
                <span class="badge-pill bp-red"><i class="fa-solid fa-xmark"></i> <?= $totalMissed ?> Missed</span>
              <?php endif; ?>
            </div>
          </div>

          <div class="review-questions-list">
            <?php foreach ($questionReviewData as $index => $item): ?>
              <?php
                $q = $item['question'];
                $userAns = $item['user_answer'];
                $correctAns = $item['correct_answer'];
                $isCorrect = $item['is_correct'];
                $pts = $item['points'];
                $options = [
                    'A' => (string) ($q['option_a'] ?? ''),
                    'B' => (string) ($q['option_b'] ?? ''),
                    'C' => (string) ($q['option_c'] ?? ''),
                    'D' => (string) ($q['option_d'] ?? ''),
                ];
              ?>
              <article class="review-question-card <?= $isCorrect ? 'q-correct' : 'q-incorrect' ?>" id="q-<?= $index + 1 ?>">
                <div class="review-question-head">
                  <div class="q-badge-num <?= $isCorrect ? 'bg-green' : 'bg-red' ?>">
                    <?= $index + 1 ?>
                  </div>
                  <div class="q-meta">
                    <div class="q-meta-top">
                      <span class="q-step">Question <?= $index + 1 ?> of <?= count($questions) ?></span>
                      <?php if ($isCorrect): ?>
                        <span class="badge-pill bp-green"><i class="fa-solid fa-circle-check"></i> Correct (+<?= ($pts == (int)$pts ? (int)$pts : number_format($pts, 1)) ?> <?= $pts == 1.0 ? 'pt' : 'pts' ?>)</span>
                      <?php elseif ($userAns === ''): ?>
                        <span class="badge-pill bp-gray" style="background:#E2E8F0;color:#475569"><i class="fa-solid fa-minus"></i> Unanswered (0 / <?= ($pts == (int)$pts ? (int)$pts : number_format($pts, 1)) ?> pts)</span>
                      <?php else: ?>
                        <span class="badge-pill bp-red"><i class="fa-solid fa-circle-xmark"></i> Missed / Incorrect (0 / <?= ($pts == (int)$pts ? (int)$pts : number_format($pts, 1)) ?> pts)</span>
                      <?php endif; ?>
                    </div>
                    <h4 class="q-title"><?= e((string) $q['question_text']) ?></h4>
                  </div>
                </div>

                <?php if (!$isCorrect): ?>
                  <div class="missed-feedback-header">
                    <div class="mfh-alert">
                      <span class="mfh-tag"><i class="fa-solid fa-circle-xmark"></i> Your Submission:</span>
                      <span class="mfh-user-choice">
                        <?php if ($userAns !== '' && isset($options[$userAns])): ?>
                          Option <?= e($userAns) ?>: &ldquo;<?= e($options[$userAns]) ?>&rdquo; &mdash; <strong>Incorrect</strong>
                        <?php elseif ($userAns !== ''): ?>
                          Option <?= e($userAns) ?> &mdash; <strong>Incorrect</strong>
                        <?php else: ?>
                          <em>No answer submitted (Question skipped / unanswered)</em>
                        <?php endif; ?>
                      </span>
                    </div>
                    <div class="mfh-correct">
                      <span class="mfh-tag"><i class="fa-solid fa-circle-check"></i> Correct Answer:</span>
                      <strong class="mfh-correct-choice">
                        Option <?= e($correctAns) ?>: &ldquo;<?= e($options[$correctAns] ?? '') ?>&rdquo;
                      </strong>
                    </div>
                  </div>
                <?php endif; ?>

                <div class="review-options-list" role="list">
                  <?php foreach ($options as $letter => $text): ?>
                    <?php
                      if (trim($text) === '') {
                          continue;
                      }
                      $isUserChoice = ($userAns === $letter);
                      $isCorrectOption = ($correctAns === $letter);

                      $optionClass = 'review-opt-neutral';
                      $pill = null;

                      if ($isCorrectOption && $isUserChoice) {
                          $optionClass = 'review-opt-correct-chosen';
                          $pill = '<span class="opt-pill pill-correct"><i class="fa-solid fa-check"></i> Your Choice (Correct)</span>';
                      } elseif ($isCorrectOption && !$isUserChoice) {
                          $optionClass = 'review-opt-correct-missed';
                          $pill = '<span class="opt-pill pill-correct"><i class="fa-solid fa-check"></i> Correct Answer</span>';
                      } elseif (!$isCorrectOption && $isUserChoice) {
                          $optionClass = 'review-opt-incorrect-chosen';
                          $pill = '<span class="opt-pill pill-incorrect"><i class="fa-solid fa-xmark"></i> Your Choice (Incorrect)</span>';
                      }
                    ?>
                    <div class="review-option-row <?= $optionClass ?>" role="listitem">
                      <div class="opt-left">
                        <span class="opt-circle"><?= e($letter) ?></span>
                        <span class="opt-label-text"><?= e($text) ?></span>
                      </div>
                      <?php if ($pill): ?>
                        <div class="opt-right"><?= $pill ?></div>
                      <?php endif; ?>
                    </div>
                  <?php endforeach; ?>
                </div>

                <?php $rationale = trim((string) ($q['rationale'] ?? '')); ?>
                <div class="review-rationale-box <?= $isCorrect ? 'rationale-box-correct' : 'rationale-box-missed' ?>">
                  <div class="rationale-header">
                    <i class="fa-solid <?= $isCorrect ? 'fa-lightbulb' : 'fa-graduation-cap' ?>"></i>
                    <strong><?= $isCorrect ? 'Pedagogical Rationale &amp; Key Concept' : 'Pedagogical Rationale &amp; Why Option ' . e($correctAns) . ' is Correct' ?></strong>
                  </div>
                  <div class="rationale-content">
                    <?php if ($rationale !== ''): ?>
                      <?= nl2br(e($rationale)) ?>
                    <?php else: ?>
                      Option <strong><?= e($correctAns) ?></strong> &mdash; <em><?= e($options[$correctAns] ?? '') ?></em> is the verified curriculum standard and core competency requirement for this assessment.
                    <?php endif; ?>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>

          <div class="review-footer-bar">
            <div class="rf-left">
              <a class="btn btn-o" href="dashboard.php?screen=lesson&course_id=<?= $courseId ?>"><i class="fa-solid fa-book-open"></i> Course Lessons</a>
              <a class="btn btn-o" href="download-course.php?course_id=<?= $courseId ?>" target="_blank"><i class="fa-solid fa-file-pdf"></i> Download Course Pack</a>
            </div>
            <div class="rf-right">
              <a class="btn btn-p" href="dashboard.php?screen=quiz&course_id=<?= $courseId ?>&retake=1"><i class="fa-solid fa-rotate-right"></i> Retake Assessment</a>
              <?php if ($attemptPassed && ($certEligible || $certIssued || $certPending)): ?>
                <a class="btn btn-p" href="dashboard.php?screen=certificates" style="background:#0F3D1B;border:1px solid #FACC15"><i class="fa-solid fa-award"></i> Certificate Center</a>
              <?php endif; ?>
            </div>
          </div>
        </section>

      <?php else: ?>
        <!-- Assessment Form (Initial attempt or retake mode) -->
        <?php require __DIR__ . '/../partials/assessment-form.php'; ?>
      <?php endif; ?>
    <?php else: ?>
      <div class="empty exam-empty">No active assessment is available for this course yet.</div>
    <?php endif; ?>
  </section>
<?php endif; ?>
