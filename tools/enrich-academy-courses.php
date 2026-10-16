<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/academy.php';
require_once __DIR__ . '/../lib/academy/courses.php';
require_once __DIR__ . '/../lib/academy/seeds/programs.php';

$pdo = db();
academy_ensure_schema($pdo);

echo "Starting Academy Course Enrichment...\n";

// 1. Course definitions and mappings for Courses 1-8
$courseConfigs = [
    1 => [
        'infographic' => 'grower-onboarding-workflow',
        'audio_stem' => 'grower-onboarding',
        'key_takeaway' => 'Your grower profile is the single authoritative identity used across cooperative disbursements, input supplies, and buyer traceability.',
        'warning' => 'Never upload dark, cropped, or blurred photos of identity documents. Image clarity failures account for over 80% of rejected verification requests.',
        'protip' => 'Never type State or LGA names in free text. Reports, farm input trucks, and field-agent assignments are aggregated strictly by system-standard LGA codes.',
    ],
    2 => [
        'infographic' => 'grower-onboarding-workflow',
        'audio_stem' => 'registry-data-quality',
        'key_takeaway' => 'The registry is the source of truth for payments, inputs, and certification. One person, one record, kept current is the fundamental standard.',
        'warning' => 'Do not create multiple accounts or guess LGA names. Duplicate accounts split payment records and delay field verification.',
        'protip' => 'Always verify your primary phone number and check that your State and LGA match your physical farm location before applying for inputs.',
    ],
    3 => [
        'infographic' => 'coconut-nursery-lifecycle',
        'audio_stem' => 'farmhand-safety',
        'key_takeaway' => 'Every field job starts with safety: confirm the task category, wear appropriate PPE, and use the buddy rule when working with sharp tools or chemicals.',
        'warning' => 'Never work at height during rain or high wind. Inspect all ladders, cutting poles, and safety belts prior to every harvest climb.',
        'protip' => 'Always lift heavy loads using leg muscles rather than the lower back. Ask a coworker to assist with bags or seed bins exceeding 25kg.',
    ],
    4 => [
        'infographic' => 'wallet-settlement-flow',
        'audio_stem' => 'provider-accreditation',
        'key_takeaway' => 'Accreditation confirms business legitimacy, physical operating presence, and verified product listings across declared LGAs.',
        'warning' => 'Listing counterfeit, diluted, or unregistered agricultural inputs results in permanent provider de-accreditation and forfeiture of wallet bond.',
        'protip' => 'State realistic delivery lead times and exact operating LGAs in your storefront to maintain high customer review scores and repeat orders.',
    ],
    5 => [
        'infographic' => 'wallet-settlement-flow',
        'audio_stem' => 'marketplace-clinic',
        'key_takeaway' => 'A reputable storefront combines clear product grades, transparent pricing, and fast response to inquiries within the platform.',
        'warning' => 'Never transact or accept payments outside the platform. Off-platform transactions strip buyer and seller dispute protections.',
        'protip' => 'Photograph actual produce in good natural daylight showing size, husking grade, and packaging before dispatching consignments.',
    ],
    6 => [
        'infographic' => 'coconut-nursery-lifecycle',
        'audio_stem' => 'farm-operations-mgr',
        'key_takeaway' => 'Effective farm management requires written annual plans, stand censuses, disciplined procurement, and structured daily task supervision.',
        'warning' => 'Never over-apply chemical fertilizers without soil testing. Excessive dosing wastes capital and damages soil biology.',
        'protip' => 'Plan rolling replanting of senile palms annually using certified seedlings so total plantation yield never experiences sudden drop-offs.',
    ],
    7 => [
        'infographic' => 'field-agent-gps-audit',
        'audio_stem' => 'field-agent-verification',
        'key_takeaway' => 'Field agents are the eyes of the cooperative. Every farm verification requires stable GPS coordinates, daylight photos, and grower identity confirmation.',
        'warning' => 'Fabricating GPS points or reusing old photographs is an automatic termination offense and triggers immediate audit escalation.',
        'protip' => 'Wait for open-sky GPS fix accuracy to drop below 5 meters before recording coordinates, and always photograph a permanent entrance landmark.',
    ],
    8 => [
        'infographic' => 'pest-disease-diagnostics',
        'audio_stem' => 'agronomy-advisory',
        'key_takeaway' => 'Follow the full advisory lifecycle: receive, diagnose root causes, recommend measured interventions, document in the system, and follow up.',
        'warning' => 'Never prescribe chemical treatments without verifying whether symptoms stem from water stress, waterlogging, or mineral deficiencies.',
        'protip' => 'Distinguish uniform block symptoms (soil/nutrient issues) from patchy symptoms (localized drainage, pests, or infection centers).',
    ],
];

// Helper to write rich 7-slide video brief
function enrich_video_brief(string $filePath, string $title, array $slides): void
{
    $slideJson = json_encode($slides, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $html = <<<HTML
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{$safeTitle} - One Minute Brief</title>
  <style>
    *{box-sizing:border-box}
    html,body{margin:0;padding:0;height:100%}
    body{font-family:Arial,Helvetica,sans-serif;background:#102516;color:#fff;display:flex}
    .player{flex:1 1 auto;display:flex;flex-direction:column;width:100%;min-height:100vh;background:linear-gradient(135deg,#163b24,#245c34)}
    .player.enlarged{position:fixed;top:0;left:0;right:0;bottom:0;z-index:2147483000;min-height:100vh;box-shadow:0 0 0 4px #d7b246}
    .stage{flex:1 1 auto;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:clamp(20px,5vw,64px);overflow:auto}
    .brand{color:#d7b246;font-weight:800;letter-spacing:3px;text-transform:uppercase;font-size:clamp(11px,1.4vw,14px)}
    h1{font-family:Georgia,'Times New Roman',serif;font-size:clamp(26px,4.2vw,48px);line-height:1.15;margin:16px 0 10px;max-width:920px}
    .slide{font-size:clamp(16px,2.1vw,22px);line-height:1.55;color:#edf7ee;max-width:840px;min-height:3em;margin:12px 0 0;opacity:0;transform:translateY(8px);transition:opacity .45s ease,transform .45s ease}
    .slide.show{opacity:1;transform:none}
    .progress{width:min(720px,86%);height:12px;background:rgba(255,255,255,.18);border-radius:99px;overflow:hidden;margin-top:26px}
    .progress-fill{display:block;height:100%;width:0;background:#d7b246;border-radius:99px;transition:width .25s linear}
    .time{margin-top:10px;color:#d8e8d8;font-size:clamp(12px,1.4vw,14px);font-variant-numeric:tabular-nums}
    .controls{display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:10px;padding:14px 16px;background:rgba(0,0,0,.28);border-top:1px solid rgba(255,255,255,.14)}
    .ctl{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-width:108px;padding:11px 18px;font-family:inherit;font-size:15px;font-weight:700;line-height:1.2;color:#102516;background:#d7b246;border:0;border-radius:8px;cursor:pointer}
    .ctl:hover{background:#e6c45c}
    .ctl:active{transform:translateY(1px)}
    .ctl:focus-visible{outline:3px solid #fff;outline-offset:2px}
    .ctl.secondary{background:#2f6b40;color:#fff}
    .ctl.secondary:hover{background:#3a8050}
    .status{color:#cfe4d2;font-size:13px;margin:0 4px}
    @media (max-width:600px){.ctl{min-width:92px;padding:9px 12px;font-size:14px}.status{width:100%;text-align:center;margin:2px 0}}
  </style>
</head>
<body>
  <div class="player" id="player">
    <div class="stage" id="stage">
      <div class="brand">NATCODEV Academy Video Brief</div>
      <h1 id="title">{$safeTitle}</h1>
      <p class="slide" id="slide"></p>
      <div class="progress" id="progress" role="progressbar" aria-label="One-minute brief progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><span class="progress-fill" id="progressFill"></span></div>
      <div class="time"><span id="time">00:00</span> / 01:00</div>
    </div>
    <div class="controls">
      <button type="button" class="ctl" id="playPause" aria-label="Pause the brief" title="Play / Pause the brief">Pause</button>
      <button type="button" class="ctl secondary" id="restart" aria-label="Restart the brief from the beginning" title="Restart / Replay from the start">Restart</button>
      <button type="button" class="ctl secondary" id="fullscreen" aria-label="Show the brief in fullscreen" title="Fullscreen / Enlarge">Fullscreen</button>
      <span class="status" id="slideInfo"></span>
      <span class="status" id="status" aria-live="polite"></span>
    </div>
  </div>
  <script>
    (function(){
      const slides = {$slideJson};
      const DURATION = 60;
      const TICK_MS = 250;
      const perSlide = Math.max(1, Math.ceil(DURATION / slides.length));
      const player = document.getElementById('player');
      const slideEl = document.getElementById('slide');
      const timeEl = document.getElementById('time');
      const fill = document.getElementById('progressFill');
      const progress = document.getElementById('progress');
      const slideInfo = document.getElementById('slideInfo');
      const statusEl = document.getElementById('status');
      const playBtn = document.getElementById('playPause');
      const restartBtn = document.getElementById('restart');
      const fsBtn = document.getElementById('fullscreen');
      let elapsed = 0;
      let playing = false;
      let ended = false;
      let timer = null;
      let currentIndex = -1;
      function fmt(seconds){
        const s = Math.min(DURATION, Math.floor(seconds));
        if (s >= DURATION){ return '01:00'; }
        return '00:' + String(s).padStart(2, '0');
      }
      function render(){
        const idx = Math.min(slides.length - 1, Math.floor(elapsed / perSlide));
        if (idx !== currentIndex){
          currentIndex = idx;
          slideEl.classList.remove('show');
          void slideEl.offsetWidth;
          slideEl.textContent = slides[idx];
          slideEl.classList.add('show');
          slideInfo.textContent = 'Slide ' + (idx + 1) + ' of ' + slides.length;
        }
        const pct = Math.min(100, (elapsed / DURATION) * 100);
        fill.style.width = pct.toFixed(2) + '%';
        progress.setAttribute('aria-valuenow', String(Math.round(pct)));
        timeEl.textContent = fmt(elapsed);
      }
      function tick(){
        elapsed = Math.min(DURATION, elapsed + (TICK_MS / 1000));
        render();
        if (elapsed >= DURATION){
          finish();
        }
      }
      function startTimer(){
        stopTimer();
        timer = setInterval(tick, TICK_MS);
      }
      function stopTimer(){
        if (timer !== null){
          clearInterval(timer);
          timer = null;
        }
      }
      function markPlaying(){
        playing = true;
        ended = false;
        playBtn.textContent = 'Pause';
        playBtn.setAttribute('aria-label', 'Pause the brief');
        statusEl.textContent = 'Playing';
      }
      function markPaused(){
        playing = false;
        playBtn.textContent = ended ? 'Replay' : 'Play';
        playBtn.setAttribute('aria-label', ended ? 'Replay the brief' : 'Play the brief');
        statusEl.textContent = ended ? 'Finished' : 'Paused';
      }
      function finish(){
        stopTimer();
        playing = false;
        ended = true;
        playBtn.textContent = 'Replay';
        playBtn.setAttribute('aria-label', 'Replay the brief');
        statusEl.textContent = 'Finished - press Replay or Restart to watch again.';
      }
      playBtn.addEventListener('click', function(){
        if (ended){
          elapsed = 0;
          currentIndex = -1;
          render();
          startTimer();
          markPlaying();
          return;
        }
        if (playing){
          stopTimer();
          markPaused();
        } else {
          startTimer();
          markPlaying();
        }
      });
      restartBtn.addEventListener('click', function(){
        elapsed = 0;
        currentIndex = -1;
        render();
        startTimer();
        markPlaying();
      });
      fsBtn.addEventListener('click', function(){
        if (document.fullscreenElement){
          document.exitFullscreen().catch(function(){});
          player.classList.remove('enlarged');
        } else if (player.requestFullscreen){
          player.requestFullscreen().catch(function(){
            player.classList.toggle('enlarged');
          });
        } else {
          player.classList.toggle('enlarged');
        }
      });
      render();
      startTimer();
      markPlaying();
    })();
  </script>
</body>
</html>
HTML;

    file_put_contents($filePath, $html, LOCK_EX);
}

// 2. Process Courses 1 to 8
foreach ($courseConfigs as $courseId => $cfg) {
    $courseStmt = $pdo->prepare("SELECT * FROM webinars WHERE id = ? LIMIT 1");
    $courseStmt->execute([$courseId]);
    $course = $courseStmt->fetch();
    if (!$course) {
        echo "Course {$courseId} not found in database, skipping.\n";
        continue;
    }

    $courseTitle = (string) $course['title'];
    $slug = academy_slug($courseTitle);
    echo "Processing Course #{$courseId}: {$courseTitle} (slug: {$slug})...\n";

    // Setup Video Brief
    $videoRelPath = "academy_uploads/videos/{$slug}-one-minute-brief.html";
    $videoDiskPath = dirname(__DIR__) . '/' . $videoRelPath;
    $handoutRelPath = "academy_uploads/materials/{$slug}-course-handout.pdf";
    $handoutDiskPath = dirname(__DIR__) . '/' . $handoutRelPath;

    // Get lessons for this course
    $lessonStmt = $pdo->prepare("SELECT * FROM academy_lessons WHERE webinar_id = ? AND status = 'active' ORDER BY sort_order ASC, id ASC");
    $lessonStmt->execute([$courseId]);
    $lessons = $lessonStmt->fetchAll();

    // Generate 6-7 slide video brief
    $slides = [
        "Welcome to {$courseTitle}",
        "Module Overview: " . mb_strimwidth((string) ($course['description'] ?? 'Practical NATCODEV professional training course.'), 0, 140, '...'),
    ];
    foreach (array_slice($lessons, 0, 3) as $l) {
        $slides[] = "Core Topic: " . (string) $l['title'] . " — " . mb_strimwidth((string) ($l['summary'] ?? 'Key field and operational standard.'), 0, 100, '...');
    }
    $slides[] = "Practical Standard: Study each lesson, inspect interactive vector infographics, and download your course reference pack.";
    $slides[] = "Certification Milestone: Complete all lessons, pass the verified assessment, and unlock your formal NATCODEV certificate.";

    enrich_video_brief($videoDiskPath, $courseTitle, $slides);
    echo "  -> Video brief written: {$videoRelPath} (" . count($slides) . " slides)\n";

    // Audio tracks config
    $stem = $cfg['audio_stem'];
    $audioTracksJson = json_encode([
        'en' => "../academy_uploads/audio/{$stem}-en.mp3",
        'pcm' => "../academy_uploads/audio/{$stem}-pcm.mp3",
        'ha' => "../academy_uploads/audio/{$stem}-ha.mp3",
        'yo' => "../academy_uploads/audio/{$stem}-yo.mp3",
        'ig' => "../academy_uploads/audio/{$stem}-ig.mp3",
    ], JSON_UNESCAPED_SLASHES);

    $videoUrl = '../' . $videoRelPath;
    $defaultMaterialUrl = is_file($handoutDiskPath) ? '../' . $handoutRelPath : "../academy/download-course.php?course_id={$courseId}";

    // Update lessons with multimodal elements and formatted callout tags
    $updateLessonStmt = $pdo->prepare("
        UPDATE academy_lessons
        SET video_url = ?,
            audio_url = ?,
            audio_tracks = ?,
            infographic_url = ?,
            content = ?
        WHERE id = ?
    ");

    foreach ($lessons as $lessonIdx => $lesson) {
        $lessonId = (int) $lesson['id'];
        $content = (string) $lesson['content'];

        // If content doesn't already contain TAKEAWAY or PROTIP tags, inject multimodal callouts
        if (!str_contains($content, '[TAKEAWAY]') && !str_contains($content, '[PROTIP]')) {
            $takeaway = "[TAKEAWAY]{$cfg['key_takeaway']}[/TAKEAWAY]\n\n";
            $protip = "\n\n[PROTIP]{$cfg['protip']}[/PROTIP]";
            $warning = ($lessonIdx === 1) ? "\n\n[WARNING]{$cfg['warning']}[/WARNING]" : "";
            
            // Format steps or checklist if applicable
            $extra = "";
            if ($lessonIdx === 0) {
                $extra = "\n\n[STEPS]1. Review Core Standard: Confirm requirements and objectives.\n2. Apply Field Discipline: Follow approved procedure step by step.\n3. Verify Documentation: Keep verified logs and audit evidence.\n4. Complete Assessment: Test understanding and claim certificate.[/STEPS]";
            } elseif ($lessonIdx === 2) {
                $extra = "\n\n[CHECKLIST]Verify identity and account records\nMaintain clean operational logs\nFollow verified safety and quality standards\nSubmit timely completion evidence[/CHECKLIST]";
            }

            $content = $takeaway . $content . $protip . $warning . $extra;
        }

        $infographic = ($lessonIdx === 0 || $lessonIdx === 1) ? $cfg['infographic'] : '';

        $updateLessonStmt->execute([
            $videoUrl,
            "../academy_uploads/audio/{$stem}-en.mp3",
            $audioTracksJson,
            $infographic ?: null,
            $content,
            $lessonId
        ]);
    }
    echo "  -> Enriched " . count($lessons) . " lessons with multimodal assets\n";

    // Update materials for this course with actionable URLs
    $matStmt = $pdo->prepare("SELECT * FROM academy_materials WHERE webinar_id = ?");
    $matStmt->execute([$courseId]);
    $materials = $matStmt->fetchAll();

    $updateMatStmt = $pdo->prepare("UPDATE academy_materials SET material_url = ? WHERE id = ?");
    foreach ($materials as $mat) {
        $matId = (int) $mat['id'];
        $curUrl = trim((string) ($mat['material_url'] ?? ''));
        if ($curUrl === '' || $curUrl === 'NULL') {
            $updateMatStmt->execute([$defaultMaterialUrl, $matId]);
        }
    }
    echo "  -> Ensured " . count($materials) . " course materials have valid actionable URLs\n";
}

echo "Academy Course Enrichment completed successfully!\n";
