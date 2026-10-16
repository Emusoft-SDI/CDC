<?php
declare(strict_types=1);

/**
 * Academy content seeder.
 *
 * Fills every Academy course (webinars row) with real lesson modules,
 * materials, and - for paid courses - assessments with questions.
 *
 * Data format (returned by the content batch files):
 *   courseId => [
 *     'overview'  => string   richer course description for webinars.description
 *     'duration'  => int      total course minutes
 *     'lessons'   => [ ['title','summary','duration','content'], ... ]
 *     'materials' => [ ['title','type','notes'], ... ]   (optional)
 *     'assessment'=> [ 'title','instructions','pass_score','questions'=>[
 *                        ['question','a','b','c','d','correct'], ... ] ] (optional)
 *   ]
 *
 * Lessons are upserted by (webinar_id, title) so learner progress rows
 * survive a re-run; generic stub lessons that are not part of the new
 * curriculum are removed. The seeder is idempotent.
 */

// The seeder links each course's one-minute video brief to its lessons,
// so it needs the slug/material/video-brief helpers from the program seeder.
require_once __DIR__ . '/seeds/programs.php';

/**
 * Honest study time for a plain-text lesson, derived from the
 * content itself (roughly 200 words per minute of focused reading)
 * so a short module is never labelled as a long one.
 */
function academy_reading_minutes(string $content): int
{
    $words = str_word_count(strip_tags($content));
    return max(1, (int) ceil($words / 200));
}

function academy_seed_course_content(PDO $pdo, array $content): void
{
    $stubTitles = [
        'Course PDF Handout',
        'One-Minute Video Brief',
        'Assessment Readiness',
    ];

    foreach ($content as $courseId => $course) {
        $courseId = (int) $courseId;
        $exists = $pdo->prepare("SELECT id FROM webinars WHERE id = ? AND status = 'active' LIMIT 1");
        $exists->execute([$courseId]);
        if (!$exists->fetchColumn()) {
            continue;
        }

        // Read the real course title so the video-brief slug matches the
        // one-minute brief that ships in academy_uploads/videos/.
        $titleStmt = $pdo->prepare("SELECT title FROM webinars WHERE id = ? LIMIT 1");
        $titleStmt->execute([$courseId]);
        $courseTitle = (string) ($titleStmt->fetchColumn() ?: '');
        if ($courseTitle === '') {
            continue;
        }

        // Every lesson carries the course's one-minute video brief, matching
        // the starter programs. The brief is generated from the course
        // objectives when it is missing from disk.
        $courseSlug = academy_slug($courseTitle);
        $videoRel = 'academy_uploads/videos/' . $courseSlug . '-one-minute-brief.html';
        if (!is_file(academy_material_path($videoRel))) {
            academy_write_video_brief($videoRel, [
                'title' => $courseTitle,
                'objectives' => (array) ($course['objectives'] ?? []),
            ]);
        }
        $courseVideoUrl = is_file(academy_material_path($videoRel)) ? '../' . $videoRel : '';

        $lessonTitles = array_map(static fn (array $l): string => (string) $l['title'], $course['lessons']);

        // Course-level overview and duration. The course read time is
        // derived from the total word count of the whole curriculum
        // (roughly 200 words per minute), so the headline figure matches
        // the real reading time instead of accumulating per-module
        // rounding on short modules.
        $totalWords = 0;
        foreach ($course['lessons'] as $lesson) {
            $totalWords += (int) str_word_count(strip_tags((string) $lesson['content']));
        }
        $totalMinutes = max(1, (int) round($totalWords / 200));
        $pdo->prepare("
            UPDATE webinars
            SET description = ?,
                duration_minutes = ?,
                delivery_instructions = ?
            WHERE id = ?
        ")->execute([
            (string) $course['overview'],
            $totalMinutes,
            'Self-paced. Work through every required lesson in order, then take the assessment to unlock your certificate.',
            $courseId,
        ]);

        // Lessons: upsert by (webinar_id, title). Duration is the
        // honest reading time of the module, and text modules are
        // labelled as self-paced reading (not a PDF that does not exist).
        $order = 0;
        foreach ($course['lessons'] as $lesson) {
            $order += 10;
            $title = (string) $lesson['title'];
            $summary = (string) ($lesson['summary'] ?? '');
            $body = (string) $lesson['content'];
            $minutes = academy_reading_minutes($body);
            $videoUrl = (string) ($lesson['video_url'] ?? '');
            if ($videoUrl === '') {
                $videoUrl = $courseVideoUrl;
            }
            $audioUrl = (string) ($lesson['audio_url'] ?? '');
            if ($audioUrl === '') {
                $audioUrl = "../academy_uploads/audio/{$courseSlug}-en.mp3";
            }
            if (!empty($lesson['audio_tracks'])) {
                $audioTracks = json_encode($lesson['audio_tracks'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            } else {
                $audioTracks = json_encode([
                    'en' => "../academy_uploads/audio/{$courseSlug}-en.mp3",
                    'pcm' => "../academy_uploads/audio/{$courseSlug}-pcm.mp3",
                    'ha' => "../academy_uploads/audio/{$courseSlug}-ha.mp3",
                    'yo' => "../academy_uploads/audio/{$courseSlug}-yo.mp3",
                    'ig' => "../academy_uploads/audio/{$courseSlug}-ig.mp3",
                ], JSON_UNESCAPED_SLASHES);
            }
            $infographicUrl = (string) ($lesson['infographic_url'] ?? '');
            if ($infographicUrl === '') {
                $titleLower = strtolower($courseTitle);
                if (str_contains($titleLower, 'grower') || str_contains($titleLower, 'registry')) {
                    $infographicUrl = 'grower-onboarding-workflow';
                } elseif (str_contains($titleLower, 'safety') || str_contains($titleLower, 'operations') || str_contains($titleLower, 'nursery')) {
                    $infographicUrl = 'coconut-nursery-lifecycle';
                } elseif (str_contains($titleLower, 'field agent') || str_contains($titleLower, 'gps')) {
                    $infographicUrl = 'field-agent-gps-audit';
                } elseif (str_contains($titleLower, 'agronomy') || str_contains($titleLower, 'pest')) {
                    $infographicUrl = 'pest-disease-diagnostics';
                } elseif (str_contains($titleLower, 'provider') || str_contains($titleLower, 'marketplace') || str_contains($titleLower, 'seller') || str_contains($titleLower, 'wallet')) {
                    $infographicUrl = 'wallet-settlement-flow';
                }
            }

            $pdo->prepare("
                INSERT INTO academy_lessons
                    (webinar_id, title, summary, content, delivery_type, video_url, audio_url, audio_tracks, infographic_url, duration_minutes, sort_order, is_required, status)
                VALUES (?, ?, ?, ?, 'reading', ?, ?, ?, ?, ?, ?, 1, 'active')
                ON DUPLICATE KEY UPDATE
                    summary = VALUES(summary),
                    content = VALUES(content),
                    delivery_type = VALUES(delivery_type),
                    video_url = VALUES(video_url),
                    audio_url = VALUES(audio_url),
                    audio_tracks = VALUES(audio_tracks),
                    infographic_url = VALUES(infographic_url),
                    duration_minutes = VALUES(duration_minutes),
                    sort_order = VALUES(sort_order),
                    is_required = 1,
                    status = 'active'
            ")->execute([$courseId, $title, $summary, $body, $videoUrl ?: null, $audioUrl ?: null, $audioTracks, $infographicUrl ?: null, $minutes, $order]);
        }

        // Remove the generic stubs (and anything not part of the new curriculum).
        if ($lessonTitles) {
            $placeholders = implode(',', array_fill(0, count($lessonTitles), '?'));
            $pdo->prepare("
                DELETE FROM academy_lessons
                WHERE webinar_id = ? AND title NOT IN ({$placeholders})
            ")->execute(array_merge([$courseId], $lessonTitles));
        } else {
            $pdo->prepare("DELETE FROM academy_lessons WHERE webinar_id = ?")->execute([$courseId]);
        }

        // Extra downloadable/reference materials. Upserted by title;
        // materials not in the new set (including any misassigned by
        // an earlier run) are removed so the course carries exactly
        // this set.
        $materialTitles = [];
        foreach (($course['materials'] ?? []) as $material) {
            $materialTitle = (string) $material['title'];
            $materialTitles[] = $materialTitle;
            $matUrl = (string) ($material['material_url'] ?? $material['url'] ?? '');
            if ($matUrl === '') {
                $matUrl = is_file(academy_material_path('academy_uploads/materials/' . $courseSlug . '-course-handout.pdf'))
                    ? '../academy_uploads/materials/' . $courseSlug . '-course-handout.pdf'
                    : "../academy/download-course.php?course_id={$courseId}";
            }
            $pdo->prepare("
                INSERT INTO academy_materials
                    (webinar_id, lesson_id, title, material_type, material_url, notes, sort_order, status)
                VALUES (?, NULL, ?, ?, ?, ?, ?, 'active')
                ON DUPLICATE KEY UPDATE material_type = VALUES(material_type), material_url = VALUES(material_url), notes = VALUES(notes), status = 'active'
            ")->execute([
                $courseId,
                $materialTitle,
                (string) ($material['type'] ?? 'link'),
                $matUrl,
                (string) ($material['notes'] ?? ''),
                (int) ($material['sort_order'] ?? 0),
            ]);
        }
        if ($materialTitles) {
            $materialPlaceholders = implode(',', array_fill(0, count($materialTitles), '?'));
            $pdo->prepare("DELETE FROM academy_materials WHERE webinar_id = ? AND title NOT IN ({$materialPlaceholders})")
                ->execute(array_merge([$courseId], $materialTitles));
        }

        // Assessment with questions for paid courses. Any existing
        // assessments (and their questions) are replaced so the
        // course carries exactly this assessment.
        $assessment = $course['assessment'] ?? null;
        if ($assessment !== null) {
            $oldStmt = $pdo->prepare("SELECT id FROM academy_assessments WHERE webinar_id = ?");
            $oldStmt->execute([$courseId]);
            foreach ($oldStmt->fetchAll(PDO::FETCH_COLUMN) as $oldId) {
                $pdo->prepare("DELETE FROM academy_questions WHERE assessment_id = ?")->execute([(int) $oldId]);
                $pdo->prepare("DELETE FROM academy_assessments WHERE id = ?")->execute([(int) $oldId]);
            }

            $pdo->prepare("
                INSERT INTO academy_assessments
                    (webinar_id, title, instructions, pass_score, max_attempts, status)
                VALUES (?, ?, ?, ?, 3, 'active')
                ON DUPLICATE KEY UPDATE
                    instructions = VALUES(instructions),
                    pass_score = VALUES(pass_score),
                    max_attempts = 3,
                    status = 'active'
            ")->execute([
                $courseId,
                (string) $assessment['title'],
                (string) ($assessment['instructions'] ?? 'Answer every question. A score of 70% or higher is required to pass.'),
                (float) ($assessment['pass_score'] ?? 70),
            ]);

            $assessmentId = (int) $pdo->lastInsertId();
            if ($assessmentId === 0) {
                $stmt = $pdo->prepare("SELECT id FROM academy_assessments WHERE webinar_id = ? AND title = ? LIMIT 1");
                $stmt->execute([$courseId, (string) $assessment['title']]);
                $assessmentId = (int) ($stmt->fetchColumn() ?: 0);
            }

            if ($assessmentId > 0) {
                $isFreeStmt = $pdo->prepare("SELECT is_free, title, category, target_roles FROM webinars WHERE id = ? LIMIT 1");
                $isFreeStmt->execute([$courseId]);
                $webinarRow = $isFreeStmt->fetch(PDO::FETCH_ASSOC) ?: [];
                $isFree = (int) ($webinarRow['is_free'] ?? 0) === 1;
                $minQuestions = $isFree ? 10 : 25;

                $questionsList = array_values((array) ($assessment['questions'] ?? []));
                if (count($questionsList) < $minQuestions) {
                    require_once __DIR__ . '/seeds/assignments.php';
                    $backfillQuestions = academy_assignment_pack_questions($webinarRow + ['id' => $courseId], $minQuestions + 15);
                    $existingTexts = array_map(static fn(array $q): string => mb_substr(mb_strtolower(trim((string) ($q['question'] ?? ''))), 0, 180), $questionsList);
                    foreach ($backfillQuestions as $bq) {
                        if (count($questionsList) >= $minQuestions) {
                            break;
                        }
                        $bText = mb_substr(mb_strtolower(trim((string) ($bq['question'] ?? ''))), 0, 180);
                        if (!in_array($bText, $existingTexts, true)) {
                            $existingTexts[] = $bText;
                            $questionsList[] = $bq;
                        }
                    }
                }

                $qOrder = 0;
                $qStmt = $pdo->prepare("
                    INSERT INTO academy_questions
                        (assessment_id, question_text, option_a, option_b, option_c, option_d, correct_option, points, rationale, sort_order, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
                    ON DUPLICATE KEY UPDATE
                        option_a = VALUES(option_a),
                        option_b = VALUES(option_b),
                        option_c = VALUES(option_c),
                        option_d = VALUES(option_d),
                        correct_option = VALUES(correct_option),
                        points = VALUES(points),
                        rationale = VALUES(rationale),
                        sort_order = VALUES(sort_order),
                        status = 'active'
                ");

                foreach ($questionsList as $question) {
                    $qOrder += 10;
                    $optC = (string) ($question['c'] ?? '');
                    if ($optC === '') {
                        $optC = 'Follow standard operational procedures with immediate supervisor notification';
                    }
                    $optD = (string) ($question['d'] ?? '');
                    if ($optD === '') {
                        $optD = 'Document discrepancy and escalate according to governance guidelines';
                    }
                    $correct = strtoupper((string) ($question['correct'] ?? 'A'));
                    $rat = (string) ($question['rationale'] ?? "Option {$correct} is correct according to the platform operational curriculum.");
                    $points = (float) ($question['points'] ?? 1.0);
                    if ($points <= 0) {
                        $points = 1.0;
                    }

                    $qStmt->execute([
                        $assessmentId,
                        (string) $question['question'],
                        (string) $question['a'],
                        (string) $question['b'],
                        $optC,
                        $optD,
                        $correct,
                        $points,
                        $rat,
                        $qOrder,
                    ]);
                }
            }
        }
    }
}
