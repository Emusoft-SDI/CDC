<?php
declare(strict_types=1);

/**
 * Returns required minimum questions for a course:
 * Free courses: minimum 10 questions.
 * Paid courses: minimum 25 questions.
 */
function academy_required_question_count(bool $isFree): int
{
    return $isFree ? 10 : 25;
}

/**
 * Guarantees that a specific course assessment satisfies all invariants:
 * - Active assessment exists
 * - At least 10 questions for FREE courses, 25 for PAID courses
 * - All questions have complete options A-D, valid correct option, points >= 1.0, and pedagogical rationales
 */
function academy_ensure_course_quiz_invariants(PDO $pdo, int $courseId): bool
{
    if ($courseId <= 0 || !app_table_exists($pdo, 'webinars') || !app_table_exists($pdo, 'academy_assessments') || !app_table_exists($pdo, 'academy_questions')) {
        return false;
    }

    require_once __DIR__ . '/seeds/assignments.php';

    $courseStmt = $pdo->prepare("
        SELECT w.id, w.title, w.description, w.target_roles, w.category, w.pass_score, w.is_free, p.title AS program_title
        FROM webinars w
        LEFT JOIN academy_programs p ON p.id = w.program_id
        WHERE w.id = ? AND COALESCE(w.status, 'active') = 'active'
        LIMIT 1
    ");
    $courseStmt->execute([$courseId]);
    $course = $courseStmt->fetch(PDO::FETCH_ASSOC);
    if (!$course) {
        return false;
    }

    $isFree = (int) ($course['is_free'] ?? 1) === 1;
    $minRequired = academy_required_question_count($isFree);

    $assessmentStmt = $pdo->prepare("
        SELECT id, title, pass_score, max_attempts, instructions, status
        FROM academy_assessments
        WHERE webinar_id = ? AND status = 'active'
        ORDER BY id ASC
    ");
    $assessmentStmt->execute([$courseId]);
    $assessments = $assessmentStmt->fetchAll(PDO::FETCH_ASSOC);

    $updated = false;

    if (empty($assessments)) {
        $title = trim((string) ($course['title'] ?? 'Academy Course')) . ' Assessment';
        $passScore = max(70.0, (float) ($course['pass_score'] ?? 70.0));
        $pdo->prepare("
            INSERT INTO academy_assessments (webinar_id, title, instructions, pass_score, max_attempts, status)
            VALUES (?, ?, 'Answer every question carefully. Review all 4 options before submitting. A passing score is required for certificate eligibility.', ?, 3, 'active')
        ")->execute([$courseId, $title, $passScore]);
        $assessmentStmt->execute([$courseId]);
        $assessments = $assessmentStmt->fetchAll(PDO::FETCH_ASSOC);
        $updated = true;
    }

    if (empty($assessments)) {
        return false;
    }

    $questionStmt = $pdo->prepare("
        SELECT id, question_text, option_a, option_b, option_c, option_d, correct_option, points, rationale, sort_order
        FROM academy_questions
        WHERE assessment_id = ? AND status = 'active'
        ORDER BY sort_order ASC, id ASC
    ");

    $questionRepairStmt = $pdo->prepare("
        UPDATE academy_questions
        SET option_c = ?, option_d = ?, correct_option = ?, points = ?, rationale = ?
        WHERE id = ?
    ");

    $rotateStmt = $pdo->prepare("
        UPDATE academy_questions
        SET option_a = ?, option_b = ?, option_c = ?, option_d = ?, correct_option = ?, rationale = ?
        WHERE id = ?
    ");

    $letters = ['A', 'B', 'C', 'D'];

    foreach ($assessments as $assessment) {
        $assessmentId = (int) $assessment['id'];
        $questionStmt->execute([$assessmentId]);
        $existing = $questionStmt->fetchAll(PDO::FETCH_ASSOC);

        // 1. Repair missing options, points, or rationales on existing questions
        foreach ($existing as &$q) {
            $needsC = trim((string) ($q['option_c'] ?? '')) === '';
            $needsD = trim((string) ($q['option_d'] ?? '')) === '';
            $needsRat = trim((string) ($q['rationale'] ?? '')) === '';
            $needsPts = (float) ($q['points'] ?? 0) <= 0;
            $correct = strtoupper(trim((string) ($q['correct_option'] ?? 'A')));
            $needsCorrect = !in_array($correct, ['A', 'B', 'C', 'D'], true);

            if ($needsC || $needsD || $needsRat || $needsPts || $needsCorrect) {
                $c = $needsC ? 'Follow standard operational procedures with immediate supervisor notification' : (string) $q['option_c'];
                $d = $needsD ? 'Document the discrepancy and re-verify after corrective remediation' : (string) $q['option_d'];
                $cor = $needsCorrect ? 'A' : $correct;
                $pts = $needsPts ? 1.0 : (float) $q['points'];
                $rat = $needsRat ? "Option {$cor} is correct: platform standards mandate verifiable evidence, procedural compliance, and traceable accountability across all operational activities." : (string) $q['rationale'];

                $questionRepairStmt->execute([$c, $d, $cor, $pts, $rat, (int) $q['id']]);
                $q['option_c'] = $c;
                $q['option_d'] = $d;
                $q['correct_option'] = $cor;
                $q['points'] = $pts;
                $q['rationale'] = $rat;
                $updated = true;
            }
        }
        unset($q);

        // 1b. Rebalance answers across A, B, C, D if answers are concentrated on 'A'
        $countsByOpt = [];
        foreach ($existing as $exQ) {
            $cor = strtoupper(trim((string) ($exQ['correct_option'] ?? 'A')));
            $countsByOpt[$cor] = ($countsByOpt[$cor] ?? 0) + 1;
        }
        if (count($countsByOpt) < 4 && count($existing) >= 4 && (($countsByOpt['A'] ?? 0) >= (count($existing) * 0.75))) {
            foreach ($existing as $idx => &$q) {
                $targetLetter = $letters[$idx % 4];
                $currentCor = strtoupper(trim((string) ($q['correct_option'] ?? 'A')));
                if ($targetLetter !== $currentCor && $currentCor === 'A') {
                    $curKey = 'option_a';
                    $tgtKey = 'option_' . strtolower($targetLetter);
                    $origCorrectText = (string) $q[$curKey];
                    $origDistractorText = (string) $q[$tgtKey];

                    $q[$tgtKey] = $origCorrectText;
                    $q[$curKey] = $origDistractorText;
                    $q['correct_option'] = $targetLetter;
                    $cleanRat = preg_replace('/^Option [A-D] is correct:?\s*/i', '', trim((string) ($q['rationale'] ?? '')));
                    if ($cleanRat === '') {
                        $cleanRat = "platform standards mandate verifiable evidence, procedural compliance, and operational accuracy.";
                    }
                    $q['rationale'] = "Option {$targetLetter} is correct: {$cleanRat}";

                    $rotateStmt->execute([
                        $q['option_a'],
                        $q['option_b'],
                        $q['option_c'],
                        $q['option_d'],
                        $targetLetter,
                        $q['rationale'],
                        (int) $q['id'],
                    ]);
                    $updated = true;
                }
            }
            unset($q);
        }

        // 2. Backfill additional questions if below the minimum (10 for free, 25 for paid)
        $existingCount = count($existing);
        if ($existingCount < $minRequired) {
            $needed = $minRequired - $existingCount;
            $existingTexts = array_map(static fn(array $row): string => mb_substr(mb_strtolower(trim((string) $row['question_text'])), 0, 180), $existing);
            $packQuestions = academy_assignment_pack_questions($course, $minRequired + 25);

            $maxSort = 0;
            foreach ($existing as $exRow) {
                $maxSort = max($maxSort, (int) ($exRow['sort_order'] ?? 0));
            }
            if ($maxSort === 0) {
                $maxSort = $existingCount * 10;
            }

            $questionInsert = $pdo->prepare("
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

            $added = 0;
            foreach ($packQuestions as $pq) {
                if ($added >= $needed) {
                    break;
                }
                $qPrefix = mb_substr(mb_strtolower(trim((string) $pq['question'])), 0, 180);
                if (in_array($qPrefix, $existingTexts, true)) {
                    continue;
                }
                $existingTexts[] = $qPrefix;
                $maxSort += 10;
                $points = (float) ($pq['points'] ?? 1.0);
                if ($points <= 0) {
                    $points = 1.0;
                }
                $cor = strtoupper((string) ($pq['correct'] ?? 'A'));
                $rat = (string) ($pq['rationale'] ?? ("Option {$cor} is correct: adheres to standard operating procedures taught in this curriculum."));

                $questionInsert->execute([
                    $assessmentId,
                    (string) $pq['question'],
                    (string) $pq['a'],
                    (string) $pq['b'],
                    (string) ($pq['c'] ?? 'Document and escalate according to platform governance guidelines'),
                    (string) ($pq['d'] ?? 'Refer to comprehensive technical documentation and field SOP manual'),
                    $cor,
                    $points,
                    $rat,
                    $maxSort,
                ]);
                $added++;
            }
            $updated = true;
        }
    }

    return $updated;
}

/**
 * Master invariant enforcer:
 * Guarantees that every quiz/assessment for a FREE course has at least 10 realistic,
 * domain-relevant multiple-choice questions (options A-D, correct option, points, rationale),
 * and every quiz/assessment for a PAID course has at least 25 comprehensive, rigorous questions.
 */
function academy_ensure_quiz_question_invariants(PDO $pdo): array
{
    if (!app_table_exists($pdo, 'webinars') || !app_table_exists($pdo, 'academy_assessments') || !app_table_exists($pdo, 'academy_questions')) {
        return ['checked' => 0, 'updated' => 0];
    }

    $courseIds = $pdo->query("
        SELECT id FROM webinars
        WHERE COALESCE(status, 'active') = 'active'
        ORDER BY id ASC
    ")->fetchAll(PDO::FETCH_COLUMN);

    $checked = 0;
    $updated = 0;

    foreach ($courseIds as $courseId) {
        $checked++;
        if (academy_ensure_course_quiz_invariants($pdo, (int) $courseId)) {
            $updated++;
        }
    }

    return ['checked' => $checked, 'updated' => $updated];
}

/**
 * Retrieves the active assessment for a course, ensuring the 10/25 question invariant is met.
 */
function academy_assessment_for_course(PDO $pdo, int $courseId): ?array
{
    academy_ensure_schema($pdo);
    if ($courseId > 0) {
        academy_ensure_course_quiz_invariants($pdo, $courseId);
    }
    $stmt = $pdo->prepare("SELECT * FROM academy_assessments WHERE webinar_id = ? AND status = 'active' ORDER BY id ASC LIMIT 1");
    $stmt->execute([$courseId]);
    $assessment = $stmt->fetch();
    return $assessment ?: null;
}

