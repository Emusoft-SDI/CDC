<?php
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
    <?php
      // Determine lesson multimodal assets
      $lessonVideo = trim((string) ($selectedLesson['video_url'] ?? ''));
      if ($lessonVideo === '' && !empty($selectedLesson['material_url'])) {
          $matUrl = (string) $selectedLesson['material_url'];
          if (str_contains($matUrl, 'brief') || str_contains($matUrl, 'video') || str_ends_with($matUrl, '.mp4')) {
              $lessonVideo = $matUrl;
          }
      }

      $audioTracks = [];
      if (!empty($selectedLesson['audio_tracks'])) {
          $decoded = json_decode((string) $selectedLesson['audio_tracks'], true);
          if (is_array($decoded)) {
              $audioTracks = $decoded;
          }
      }
      if (!$audioTracks && !empty($selectedLesson['audio_url'])) {
          $audioTracks['en'] = (string) $selectedLesson['audio_url'];
      }

      // Check for associated infographic
      $infographicSlug = trim((string) ($selectedLesson['infographic_url'] ?? ''));
      if ($infographicSlug === '') {
          $courseTitleLower = strtolower((string) $course['title']);
          if (str_contains($courseTitleLower, 'grower') || str_contains($courseTitleLower, 'onboarding')) {
              $infographicSlug = 'grower-onboarding-workflow';
          } elseif (str_contains($courseTitleLower, 'nursery') || str_contains($courseTitleLower, 'seedling')) {
              $infographicSlug = 'coconut-nursery-lifecycle';
          } elseif (str_contains($courseTitleLower, 'field agent') || str_contains($courseTitleLower, 'gps')) {
              $infographicSlug = 'field-agent-gps-audit';
          } elseif (str_contains($courseTitleLower, 'pest') || str_contains($courseTitleLower, 'disease') || str_contains($courseTitleLower, 'health')) {
              $infographicSlug = 'pest-disease-diagnostics';
          }
      }
      $hasInfographic = ($infographicSlug !== '' && academy_lesson_infographic_svg($infographicSlug) !== null);
      $hasVideo = ($lessonVideo !== '');
      $hasAudio = true; // Audio is always available (either via MP3 or Web Speech synthesis)

      // Lesson navigation: resolve position, previous and next lessons in course order
      $lessonPosition = 0;
      $prevLesson = null;
      $nextLesson = null;
      foreach ($lessons as $lessonPos => $lessonRow) {
          if ($selectedLesson && (int) $lessonRow['id'] === (int) $selectedLesson['id']) {
              $lessonPosition = $lessonPos + 1;
              $prevLesson = $lessonPos > 0 ? $lessons[$lessonPos - 1] : null;
              $nextLesson = array_key_exists($lessonPos + 1, $lessons) ? $lessons[$lessonPos + 1] : null;
              break;
          }
      }
      $lessonUrl = static function (int $lessonId) use ($courseId): string {
          return 'dashboard.php?screen=lesson&course_id=' . $courseId . '&lesson_id=' . $lessonId;
      };
      $assessmentUrl = 'dashboard.php?screen=quiz&course_id=' . $courseId;
      $multitrackPayload = function_exists('academy_lesson_multitrack_payload') && $selectedLesson
          ? academy_lesson_multitrack_payload($selectedLesson, $course)
          : [];
      $courseThumb = function_exists('academy_course_artwork_url') 
          ? academy_course_artwork_url($course) 
          : (function_exists('academy_course_thumbnail_src') ? academy_course_thumbnail_src($course) : '../assets/academy/course-grower-foundation.svg');
    ?>
    <section class="grid g2">
      <article class="card span2">
        <div class="card-h">
          <h3><?= e((string) $course['title']) ?></h3>
          <div class="actions">
            <a class="btn btn-o btn-s" href="dashboard.php?screen=course&course_id=<?= $courseId ?>"><i class="fas fa-info-circle"></i> Course Detail</a>
            <a class="link" href="dashboard.php?screen=learning&course_id=<?= $courseId ?>">My Learning</a>
          </div>
        </div>

        <!-- 16:9 Course Artwork Banner -->
        <div class="lesson-banner-169" style="position:relative;width:100%;aspect-ratio:16/9;max-height:220px;border-radius:12px;overflow:hidden;margin-bottom:18px;box-shadow:0 4px 16px rgba(0,0,0,0.12);background:linear-gradient(135deg,var(--green-700),var(--green-500))">
          <img src="<?= e($courseThumb) ?>" alt="<?= e((string) $course['title']) ?>" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover">
          <div style="position:absolute;inset:0;background:linear-gradient(to top, rgba(16,37,22,0.92) 0%, rgba(16,37,22,0.35) 60%, transparent 100%);display:flex;align-items:flex-end;padding:16px 20px">
            <div style="color:#fff">
              <div style="display:flex;gap:8px;align-items:center;margin-bottom:6px;flex-wrap:wrap">
                <span class="badge" style="background:#d7b246;color:#102516;font-weight:700"><?= e(academy_delivery_label((string) ($course['delivery_type'] ?? 'lms'))) ?></span>
                <span class="badge" style="background:rgba(255,255,255,0.2);color:#fff"><?= e((string) ($course['category'] ?? 'Academy Course')) ?></span>
                <span class="badge" style="background:#27ae60;color:#fff"><i class="fas fa-check-circle"></i> Active Learning</span>
              </div>
              <h2 style="margin:0 0 2px;color:#fff;font-size:22px;text-shadow:0 2px 4px rgba(0,0,0,0.6)"><?= e((string) $course['title']) ?></h2>
              <div style="color:#d8e8d8;font-size:12px"><?= e((string) ($course['program_title'] ?? 'NATCODEV Academy')) ?> &bull; Lesson <?= $lessonPosition ?> of <?= count($lessons) ?></div>
            </div>
          </div>
        </div>

        <?php if ($selectedLesson): ?>
          <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px">
            <div>
              <?php if ($lessonPosition > 0): ?>
                <div class="muted" style="font-size:12px;font-weight:800;letter-spacing:.07em;text-transform:uppercase;margin-bottom:4px">Lesson <?= $lessonPosition ?> of <?= count($lessons) ?> &bull; <?= count($completedLessonIds) ?>/<?= count($lessons) ?> complete</div>
              <?php endif; ?>
              <h3 style="font-size:20px;margin-bottom:4px"><?= e((string) $selectedLesson['title']) ?></h3>
              <p class="muted" style="margin:0"><?= e((string) ($selectedLesson['summary'] ?? '')) ?></p>
            </div>
            <div class="actions" style="margin:0">
              <a class="btn btn-o btn-s" href="download-course.php?course_id=<?= $courseId ?>" target="_blank" rel="noopener"><i class="fas fa-file-pdf" style="color:#e74c3c"></i> Download Course PDF</a>
              <a class="link" href="download-course.php?course_id=<?= $courseId ?>&format=txt" target="_blank" rel="noopener" style="font-size:11px;align-self:center;margin-left:4px"><i class="fas fa-file-lines"></i> TXT</a>
              <span class="muted" style="align-self:center;font-size:12px"><?= academy_delivery_label((string) $selectedLesson['delivery_type']) ?> &bull; ~<?= (int) $selectedLesson['duration_minutes'] ?> min</span>
            </div>
          </div>

          <!-- Multimodal Format Tabs -->
          <script id="lesson-multitrack-json" type="application/json"><?= json_encode($multitrackPayload, JSON_UNESCAPED_UNICODE) ?></script>
          <div class="media-tabs" id="media-tabs">
            <button type="button" class="media-tab active" data-tab="reading">
              <i class="fas fa-book-open"></i> Read &amp; Study
            </button>
            <?php if ($hasVideo): ?>
              <button type="button" class="media-tab" data-tab="video">
                <i class="fas fa-circle-play"></i> Video Lesson <span class="badge-count">HD + Audio</span>
              </button>
            <?php endif; ?>
            <button type="button" class="media-tab" data-tab="audio">
              <i class="fas fa-headphones"></i> Audio Lecture <span class="badge-count">Multitrack</span>
            </button>
            <?php if ($hasInfographic): ?>
              <button type="button" class="media-tab" data-tab="infographic">
                <i class="fas fa-chart-pie"></i> Infographic &amp; Visuals <span class="badge-count">Vector</span>
              </button>
            <?php endif; ?>
          </div>

          <!-- PANEL 1: Reading Mode (With Rich Visual Formatting) -->
          <div class="media-panel active" id="panel-reading">
            <?php if (!empty($selectedLesson['content'])): ?>
              <div class="lesson-body" id="lesson-body-content" style="margin-top:12px">
                <?= academy_render_lesson_content((string) $selectedLesson['content'], $selectedLesson) ?>
              </div>
            <?php else: ?>
              <div class="empty">No written lesson text available. Please use the audio or video lesson tabs above.</div>
            <?php endif; ?>
          </div>

          <!-- PANEL 2: Video Player with Sound Controls -->
          <?php if ($hasVideo): ?>
            <div class="media-panel" id="panel-video">
              <div class="video-player-container">
                <div class="video-player-wrapper">
                  <?php if (str_ends_with(strtolower($lessonVideo), '.mp4') || str_ends_with(strtolower($lessonVideo), '.webm')): ?>
                    <video controls preload="metadata" poster="<?= e($courseThumb) ?>">
                      <source src="<?= e($lessonVideo) ?>" type="<?= str_ends_with(strtolower($lessonVideo), '.webm') ? 'video/webm' : 'video/mp4' ?>">
                      Your browser does not support HTML5 video.
                    </video>
                  <?php else: ?>
                    <iframe src="<?= e($lessonVideo) ?>" title="<?= e((string) $selectedLesson['title']) ?>" id="video-brief-iframe" allow="autoplay; fullscreen; microphone; camera; display-capture" allowfullscreen></iframe>
                  <?php endif; ?>
                </div>
                <div class="video-player-footer">
                  <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                    <div><i class="fas fa-film"></i> <?= e((string) $selectedLesson['title']) ?> (Video Brief)</div>
                    <button type="button" class="btn btn-p btn-s" id="video-narration-btn" title="Toggle audio narration for this video"><i class="fas fa-volume-high"></i> <span id="video-narration-label">Play Video Audio</span></button>
                    <div style="display:flex;align-items:center;gap:4px">
                      <label for="video-audio-track-select" style="font-size:12px;margin:0">Voice:</label>
                      <select id="video-audio-track-select" style="font-size:12px;padding:3px 8px;border-radius:4px;background:rgba(255,255,255,0.15);color:#fff;border:1px solid rgba(255,255,255,0.25)">
                        <option value="en" selected>English (Official)</option>
                        <option value="pcm">Nigerian Pidgin</option>
                        <option value="ha">Hausa</option>
                        <option value="yo">Yoruba</option>
                        <option value="ig">Igbo</option>
                      </select>
                    </div>
                  </div>
                  <div class="video-speed-selector">
                    <span style="align-self:center;margin-right:4px">Speed:</span>
                    <button type="button" class="video-speed-btn active" data-speed="1">1.0x</button>
                    <button type="button" class="video-speed-btn" data-speed="1.25">1.25x</button>
                    <button type="button" class="video-speed-btn" data-speed="1.5">1.5x</button>
                  </div>
                </div>
              </div>
            </div>
          <?php endif; ?>

          <!-- PANEL 3: Dedicated Multitrack Audio Player -->
          <div class="media-panel" id="panel-audio">
            <div class="audio-player-card">
              <div class="audio-player-header">
                <div class="audio-title-group">
                  <div class="audio-pulse-indicator"></div>
                  <div>
                    <strong style="color:#0F3D1B;display:block;font-size:14px"><?= e((string) $selectedLesson['title']) ?> (Audio Narration)</strong>
                    <small style="color:var(--muted)" id="audio-active-track-label">Listen in your preferred local language &bull; Multitrack active</small>
                  </div>
                </div>
                <div class="audio-track-selector">
                  <label for="audio-track-select" style="margin:0">Voice Track:</label>
                  <select id="audio-track-select">
                    <option value="en" data-url="<?= e($audioTracks['en'] ?? '') ?>" data-lang="en" selected>English (Official)</option>
                    <option value="pcm" data-url="<?= e($audioTracks['pcm'] ?? '') ?>" data-lang="pcm">Nigerian Pidgin (Naija)</option>
                    <option value="ha" data-url="<?= e($audioTracks['ha'] ?? '') ?>" data-lang="ha">Hausa (Arewa Track)</option>
                    <option value="yo" data-url="<?= e($audioTracks['yo'] ?? '') ?>" data-lang="yo">Yoruba (Southwest Track)</option>
                    <option value="ig" data-url="<?= e($audioTracks['ig'] ?? '') ?>" data-lang="ig">Igbo (Southeast Track)</option>
                  </select>
                </div>
              </div>

              <div class="audio-controls-row">
                <button type="button" class="audio-btn-skip" id="audio-skip-back" title="Rewind 15 seconds">
                  <i class="fas fa-rotate-left"></i>
                </button>
                <button type="button" class="audio-btn-circle" id="audio-play-btn" title="Play / Pause">
                  <i class="fas fa-play"></i>
                </button>
                <button type="button" class="audio-btn-skip" id="audio-skip-fwd" title="Skip 15 seconds">
                  <i class="fas fa-rotate-right"></i>
                </button>

                <div class="audio-progress-container">
                  <div class="audio-scrub-bar" id="audio-scrub-bar">
                    <div class="audio-scrub-fill" id="audio-scrub-fill"></div>
                  </div>
                  <div class="audio-time-row">
                    <span id="audio-cur-time">00:00</span>
                    <span id="audio-dur-time">~<?= (int) ($selectedLesson['audio_duration_seconds'] > 0 ? gmdate('i:s', (int) $selectedLesson['audio_duration_seconds']) : sprintf('%02d:00', max(1, (int) $selectedLesson['duration_minutes']))) ?></span>
                  </div>
                </div>

                <div class="audio-actions-right">
                  <button type="button" class="audio-speed-tag" id="audio-speed-btn">1.0x</button>
                  <a class="audio-download-link" id="audio-download-link" href="#" download="<?= e(academy_slug((string) $selectedLesson['title'])) ?>.mp3" style="display:none">
                    <i class="fas fa-download"></i> Save MP3
                  </a>
                </div>
              </div>
            </div>
          </div>

          <!-- PANEL 4: Interactive Infographics & Diagram Viewer -->
          <?php if ($hasInfographic): ?>
            <div class="media-panel" id="panel-infographic">
              <?= academy_render_infographic_block($infographicSlug, (string) $selectedLesson['title'] . ' - Visual Standard') ?>
            </div>
          <?php endif; ?>

          <div class="actions" style="margin-top:20px;border-top:1px solid var(--border);padding-top:16px">
            <?php if (in_array((int) $selectedLesson['id'], $completedLessonIds, true)): ?>
              <?= ac_badge('completed', 'Lesson Complete') ?>
            <?php else: ?>
              <form method="post">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="complete_lesson">
                <input type="hidden" name="lesson_id" value="<?= (int) $selectedLesson['id'] ?>">
                <button type="submit" class="btn btn-p"><i class="fas fa-check"></i> Mark Lesson Complete</button>
              </form>
            <?php endif; ?>
            <?php if ($prevLesson): ?>
              <a class="btn btn-o btn-s" href="<?= e($lessonUrl((int) $prevLesson['id'])) ?>"><i class="fas fa-arrow-left"></i> Previous Lesson</a>
            <?php endif; ?>
            <?php if ($nextLesson): ?>
              <a class="btn btn-p btn-s" href="<?= e($lessonUrl((int) $nextLesson['id'])) ?>">Next Lesson <i class="fas fa-arrow-right"></i></a>
            <?php elseif ($assessment): ?>
              <a class="btn btn-p btn-s" href="<?= e($assessmentUrl) ?>"><i class="fas fa-clipboard-check"></i> Take Assessment</a>
            <?php endif; ?>
            <?php if ($nextLesson && $assessment): ?>
              <a class="btn btn-o btn-s" href="<?= e($assessmentUrl) ?>"><i class="fas fa-clipboard-list"></i> Take Assessment</a>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <div class="empty">No lessons have been added yet. Use the course delivery link/instructions from Course Detail.</div>
        <?php endif; ?>
      </article>

      <article class="card span2"><div class="card-h"><h3>Course Lessons</h3><span class="muted"><?= count($completedLessonIds) ?>/<?= count($lessons) ?> complete</span></div><div class="lesson-list">
        <?php $lessonIndex = 0; foreach ($lessons as $lesson): $lessonIndex += 1; ?>
          <div class="lesson-row <?= $selectedLesson && (int) $selectedLesson['id'] === (int) $lesson['id'] ? 'active' : '' ?>">
            <div>
              <strong><?= $lessonIndex ?>. <?= e((string) $lesson['title']) ?></strong><br>
              <span class="muted"><?= academy_delivery_label((string) $lesson['delivery_type']) ?> &bull; ~<?= (int) $lesson['duration_minutes'] ?> min read</span>
            </div>
            <div class="actions">
              <a class="btn btn-o btn-s" href="dashboard.php?screen=lesson&course_id=<?= $courseId ?>&lesson_id=<?= (int) $lesson['id'] ?>">Open</a>
              <?= in_array((int) $lesson['id'], $completedLessonIds, true) ? ac_badge('completed', 'Done') : ac_badge('not_started', 'Pending') ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div></article>

      <?php if (!empty($lessonsMaterials)): ?>
        <article class="card span2">
          <div class="card-h"><h3>Course Learning Materials &amp; Downloads</h3><span class="muted"><?= count($lessonsMaterials) ?> resources</span></div>
          <div class="lesson-list">
            <?php foreach ($lessonsMaterials as $mat): ?>
              <div class="lesson-row">
                <div>
                  <strong><i class="fas <?= str_contains(strtolower((string) ($mat['material_type'] ?? '')), 'checklist') ? 'fa-list-check' : (str_contains(strtolower((string) ($mat['material_type'] ?? '')), 'template') ? 'fa-file-lines' : (str_contains(strtolower((string) ($mat['material_type'] ?? '')), 'guide') ? 'fa-book-bookmark' : 'fa-paperclip')) ?>"></i> <?= e((string) $mat['title']) ?></strong>
                  <span class="badge" style="margin-left:6px;font-size:11px"><?= e(academy_material_label((string) ($mat['material_type'] ?? 'link'))) ?></span>
                  <?php if (!empty($mat['notes'])): ?><br><span class="muted"><?= e((string) $mat['notes']) ?></span><?php endif; ?>
                </div>
                <div class="actions">
                  <?php if (!empty($mat['material_url'])): ?>
                    <a class="btn btn-o btn-s" href="<?= e((string) $mat['material_url']) ?>" target="_blank" rel="noopener"><i class="fas fa-arrow-up-right-from-square"></i> Open Resource</a>
                  <?php else: ?>
                    <a class="btn btn-o btn-s" href="download-course.php?course_id=<?= $courseId ?>" target="_blank" rel="noopener"><i class="fas fa-download"></i> Download Pack</a>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </article>
      <?php endif; ?>

      <?php if ($assessment && $questions): ?>
        <article class="card span2">
          <div class="card-h"><h3>Final Step: Course Assessment</h3><?= $allLessonsDone ? ac_badge('completed', 'All lessons complete') : ac_badge('not_started', count($completedLessonIds) . '/' . count($lessons) . ' lessons') ?></div>
          <?php if ($allLessonsDone): ?>
            <?php if (!empty($currentAttempt)): ?>
              <div class="exam-status-box" style="padding:16px;background:#FBFDFB;border:1px solid #D8EADF;border-radius:10px;margin-top:10px">
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:8px">
                  <h4 style="margin:0;color:#0F3D1B;font-size:16px"><i class="fa-solid fa-clipboard-check" style="color:var(--green-700)"></i> Assessment Status: <?= (int)$currentAttempt['passed'] === 1 ? 'Passed' : 'Submitted (Did Not Pass)' ?></h4>
                  <?= (int)$currentAttempt['passed'] === 1 ? ac_badge('completed', 'Score: ' . number_format((float)$currentAttempt['score_percent'], 1) . '%') : ac_badge('failed', 'Score: ' . number_format((float)$currentAttempt['score_percent'], 1) . '%') ?>
                </div>
                <p class="muted" style="margin:0 0 12px">
                  <?= (int)$currentAttempt['passed'] === 1 ? 'You have passed this assessment. Review your answers and rationale feedback or proceed to claim your credentials.' : 'You have attempted this assessment. Review incorrect answers, examine rationales, or retake the assessment to achieve the pass score.' ?>
                </p>
                <div class="actions">
                  <a class="btn btn-p" href="dashboard.php?screen=quiz&course_id=<?= $courseId ?>"><i class="fa-solid fa-list-check"></i> Review Answers & Detailed Feedback</a>
                  <a class="btn btn-o" href="dashboard.php?screen=quiz&course_id=<?= $courseId ?>&retake=1"><i class="fa-solid fa-rotate-right"></i> Retake Assessment</a>
                  <a class="btn btn-o" href="download-course.php?course_id=<?= $courseId ?>" target="_blank"><i class="fa-solid fa-file-pdf"></i> Download Course Pack</a>
                  <?php if ((int)$currentAttempt['passed'] === 1): ?>
                    <a class="btn btn-o" href="dashboard.php?screen=certificates"><i class="fa-solid fa-award"></i> Certificate Status</a>
                  <?php endif; ?>
                </div>
              </div>
            <?php else: ?>
              <p class="muted">You have completed every lesson in this course. Take the assessment below to finish the course and unlock your certificate.</p>
              <?php require __DIR__ . '/../partials/assessment-form.php'; ?>
            <?php endif; ?>
          <?php else: ?>
            <p class="muted">Complete all <?= count($lessons) ?> lessons to unlock the final assessment. You have completed <?= count($completedLessonIds) ?> of <?= count($lessons) ?>.</p>
            <div class="prog" style="max-width:420px"><div class="prog-f" style="width:<?= $totalLessons > 0 ? max(0, min(100, (int) round((count($completedLessonIds) / $totalLessons) * 100))) : 0 ?>%"></div></div>
            <div class="actions" style="margin-top:12px"><a class="btn btn-o" href="dashboard.php?screen=quiz&course_id=<?= $courseId ?>">Preview Assessment</a></div>
          <?php endif; ?>
        </article>
      <?php endif; ?>
    </section>


