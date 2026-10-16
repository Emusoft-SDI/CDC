<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/academy.php';

/** @var PDO $pdo */
$pdo = db();

academy_ensure_schema($pdo);

// Count questions and invariant metrics per course assessment
$rows = $pdo->query("
    SELECT
        w.id AS course_id,
        w.title,
        w.is_free,
        a.id AS assessment_id,
        COUNT(q.id) AS qcount,
        SUM(CASE WHEN q.option_c IS NULL OR TRIM(q.option_c) = '' OR q.option_d IS NULL OR TRIM(q.option_d) = '' THEN 1 ELSE 0 END) AS missing_options,
        SUM(CASE WHEN q.rationale IS NULL OR TRIM(q.rationale) = '' THEN 1 ELSE 0 END) AS missing_rationales,
        SUM(CASE WHEN q.points IS NULL OR q.points <= 0 THEN 1 ELSE 0 END) AS invalid_points,
        COUNT(DISTINCT q.correct_option) AS distinct_opts
    FROM webinars w
    LEFT JOIN academy_assessments a ON a.webinar_id = w.id AND a.status = 'active'
    LEFT JOIN academy_questions q ON q.assessment_id = a.id AND q.status = 'active'
    WHERE COALESCE(w.status, 'active') = 'active'
    GROUP BY w.id, w.title, w.is_free, a.id
    ORDER BY w.id ASC
")->fetchAll(PDO::FETCH_ASSOC);

echo "NATCODEV Academy Quiz / Assessment Question Invariant Report\n";
echo str_repeat("=", 120) . "\n";
echo sprintf("%-5s | %-42s | %-5s | %-6s | %-8s | %-7s | %-10s | %-7s | %-6s | %s\n",
    'ID', 'Course Title', 'Type', 'Count', 'Required', 'BadOpts', 'NoRationale', 'BadPts', 'Opts', 'Status');
echo str_repeat("-", 120) . "\n";

$totalCourses = count($rows);
$compliantCount = 0;
$totalQuestions = 0;

foreach ($rows as $r) {
    $courseId = (int) $r['course_id'];
    $title = (string) $r['title'];
    $isFree = (int) $r['is_free'] === 1;
    $typeStr = $isFree ? 'FREE' : 'PAID';
    $required = $isFree ? 10 : 25;
    $count = (int) ($r['qcount'] ?? 0);
    $badOpts = (int) ($r['missing_options'] ?? 0);
    $noRat = (int) ($r['missing_rationales'] ?? 0);
    $badPts = (int) ($r['invalid_points'] ?? 0);
    $distinctOpts = (int) ($r['distinct_opts'] ?? 0);

    $isPass = ($count >= $required) && ($badOpts === 0) && ($noRat === 0) && ($badPts === 0) && ($distinctOpts >= 4);
    if ($isPass) {
        $compliantCount++;
    }
    $totalQuestions += $count;

    $statusStr = $isPass ? '[PASS]' : '[FAIL]';

    echo sprintf("%-5d | %-42s | %-5s | %-6d | %-8d | %-7d | %-10d | %-7d | %-6d | %s\n",
        $courseId,
        substr($title, 0, 42),
        $typeStr,
        $count,
        $required,
        $badOpts,
        $noRat,
        $badPts,
        $distinctOpts,
        $statusStr
    );
}

echo str_repeat("=", 120) . "\n";
echo sprintf("Summary: %d / %d courses compliant. Total questions across active courses: %d.\n",
    $compliantCount, $totalCourses, $totalQuestions);
if ($compliantCount === $totalCourses) {
    echo "STATUS: ALL QUIZ QUESTION INVARIANTS MET (Free >= 10, Paid >= 25, 4 Options A-D, Points >= 1, Rationales, Balanced Answers).\n";
} else {
    echo "STATUS: NON-COMPLIANT COURSES DETECTED.\n";
}
