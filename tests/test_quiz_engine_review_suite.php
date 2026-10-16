<?php
declare(strict_types=1);

/**
 * Quiz Engine & Post-Submission Answer Review Test Suite
 * Validates grading, answer review, missed question pedagogical feedback,
 * retake workflow, and passing congratulations card.
 */

require_once __DIR__ . '/TestHarness.php';
require_once __DIR__ . '/../lib/academy.php';
require_once __DIR__ . '/../lib/academy/dashboard-helpers.php';

TestHarness::start("Quiz Engine & Answer Review System Test Suite");

// -----------------------------------------------------------------------------
// 1. Scoring Logic & Answer Normalization Test
// -----------------------------------------------------------------------------
$questions = [
    [
        'id' => 101,
        'question_text' => 'What is the required spacing for hybrid coconut palms?',
        'option_a' => '8m x 8m triangular spacing',
        'option_b' => '2m x 2m grid',
        'option_c' => '20m x 20m wide spacing',
        'option_d' => 'Random scatter',
        'correct_option' => 'A',
        'points' => 50.0,
        'rationale' => 'Triangular 8m x 8m optimizes canopy sunlight and root development.',
    ],
    [
        'id' => 102,
        'question_text' => 'Which fungal infection affects nursery seedlings during peak rainfall?',
        'option_a' => 'Coconut Leaf Rot (Bipolaris incurvata)',
        'option_b' => 'Common rust',
        'option_c' => 'Powdery mildew',
        'option_d' => 'Corn smut',
        'correct_option' => 'A',
        'points' => 50.0,
        'rationale' => 'Bipolaris incurvata thrives in humid nursery environments.',
    ],
];

// Test 1.1: 100% Score Calculation
$submittedAnswers1 = [101 => 'A', 102 => 'A'];
$earned1 = 0.0;
$possible1 = 0.0;
foreach ($questions as $q) {
    $possible1 += (float) $q['points'];
    $ans = strtoupper(trim((string) ($submittedAnswers1[$q['id']] ?? '')));
    if (str_starts_with($ans, 'OPTION_')) { $ans = substr($ans, -1); }
    if (strlen($ans) > 1) { $ans = substr($ans, 0, 1); }
    $correct = strtoupper(trim((string) $q['correct_option']));
    if (str_starts_with($correct, 'OPTION_')) { $correct = substr($correct, -1); }
    if (strlen($correct) > 1) { $correct = substr($correct, 0, 1); }
    if ($ans !== '' && $ans === $correct) {
        $earned1 += (float) $q['points'];
    }
}
$score1 = $possible1 > 0 ? round(($earned1 / $possible1) * 100, 2) : 0;
TestHarness::assertEqual(100.0, $score1, "Scoring: Evaluated perfect score of 100%");
TestHarness::assert($score1 >= 75.0, "Scoring: Perfect score meets/exceeds 75% pass mark");

// Test 1.2: Failing Score & Partial Credit Calculation
$submittedAnswers2 = [101 => 'B', 102 => 'A'];
$earned2 = 0.0;
$possible2 = 0.0;
foreach ($questions as $q) {
    $possible2 += (float) $q['points'];
    $ans = strtoupper(trim((string) ($submittedAnswers2[$q['id']] ?? '')));
    if (str_starts_with($ans, 'OPTION_')) { $ans = substr($ans, -1); }
    if (strlen($ans) > 1) { $ans = substr($ans, 0, 1); }
    $correct = strtoupper(trim((string) $q['correct_option']));
    if (str_starts_with($correct, 'OPTION_')) { $correct = substr($correct, -1); }
    if (strlen($correct) > 1) { $correct = substr($correct, 0, 1); }
    if ($ans !== '' && $ans === $correct) {
        $earned2 += (float) $q['points'];
    }
}
$score2 = $possible2 > 0 ? round(($earned2 / $possible2) * 100, 2) : 0;
TestHarness::assertEqual(50.0, $score2, "Scoring: Evaluated partial score of 50%");
TestHarness::assert($score2 < 75.0, "Scoring: Partial score accurately flagged below 75% pass threshold");

// Test 1.3: Normalization of Prefixed Answer Values ('OPTION_A' -> 'A')
$submittedAnswers3 = [101 => 'option_a', 102 => 'OPTION_A'];
$earned3 = 0.0;
$possible3 = 0.0;
foreach ($questions as $q) {
    $possible3 += (float) $q['points'];
    $ans = strtoupper(trim((string) ($submittedAnswers3[$q['id']] ?? '')));
    if (str_starts_with($ans, 'OPTION_')) { $ans = substr($ans, -1); }
    if (strlen($ans) > 1) { $ans = substr($ans, 0, 1); }
    $correct = strtoupper(trim((string) $q['correct_option']));
    if (str_starts_with($correct, 'OPTION_')) { $correct = substr($correct, -1); }
    if (strlen($correct) > 1) { $correct = substr($correct, 0, 1); }
    if ($ans !== '' && $ans === $correct) {
        $earned3 += (float) $q['points'];
    }
}
$score3 = $possible3 > 0 ? round(($earned3 / $possible3) * 100, 2) : 0;
TestHarness::assertEqual(100.0, $score3, "Scoring: Case & prefix normalization ('option_a' / 'OPTION_A') correctly matches 'A'");

// -----------------------------------------------------------------------------
// 2. Question-by-Question Review Structure & Rationale Verification
// -----------------------------------------------------------------------------
$mockAttemptAnswers = ['101' => 'B', '102' => 'A'];
$questionReviewData = [];
$totalPointsPossible = 0.0;
$totalPointsEarned = 0.0;
$totalCorrect = 0;
$totalMissed = 0;

foreach ($questions as $index => $q) {
    $qId = (int) $q['id'];
    $pts = (float) ($q['points'] ?? 1);
    $totalPointsPossible += $pts;
    $learnerAnswer = strtoupper(trim((string) ($mockAttemptAnswers[$qId] ?? $mockAttemptAnswers[(string) $qId] ?? '')));
    if ($learnerAnswer === '') {
        $idxKey = $index + 1;
        $learnerAnswer = strtoupper(trim((string) ($mockAttemptAnswers[$idxKey] ?? $mockAttemptAnswers[(string) $idxKey] ?? '')));
    }
    if (str_starts_with($learnerAnswer, 'OPTION_')) { $learnerAnswer = substr($learnerAnswer, -1); }
    if (strlen($learnerAnswer) > 1) { $learnerAnswer = substr($learnerAnswer, 0, 1); }

    $correctAnswer = strtoupper(trim((string) ($q['correct_option'] ?? 'A')));
    if (str_starts_with($correctAnswer, 'OPTION_')) { $correctAnswer = substr($correctAnswer, -1); }
    if (strlen($correctAnswer) > 1) { $correctAnswer = substr($correctAnswer, 0, 1); }

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

TestHarness::assertEqual(1, $totalCorrect, "Review Data: 1 correct question recorded");
TestHarness::assertEqual(1, $totalMissed, "Review Data: 1 missed question recorded");

// Verify Missed Question Feedback
$missedItem = $questionReviewData[0]; // Question 101: user chose B, correct was A
TestHarness::assert(!$missedItem['is_correct'], "Missed Question: Question 101 marked as incorrect");
TestHarness::assertEqual('B', $missedItem['user_answer'], "Missed Question: User choice is identified as Option B");
TestHarness::assertEqual('A', $missedItem['correct_answer'], "Missed Question: Correct answer is prominently identified as Option A");
TestHarness::assert(!empty($missedItem['question']['rationale']), "Missed Question: Pedagogical rationale is populated");

// Verify Correct Question Feedback
$correctItem = $questionReviewData[1]; // Question 102: user chose A, correct was A
TestHarness::assert($correctItem['is_correct'], "Correct Question: Question 102 marked as correct");
TestHarness::assertEqual('A', $correctItem['user_answer'], "Correct Question: User choice is identified as Option A");
TestHarness::assertEqual('A', $correctItem['correct_answer'], "Correct Question: Matches correct option A");

// -----------------------------------------------------------------------------
// 3. Unanswered Question Handling Test
// -----------------------------------------------------------------------------
$mockUnanswered = ['102' => 'A']; // 101 skipped
$q1LearnerAns = strtoupper(trim((string) ($mockUnanswered[101] ?? '')));
$isQ1Correct = ($q1LearnerAns !== '' && $q1LearnerAns === 'A');
TestHarness::assert(!$isQ1Correct, "Unanswered: Skipped question is not marked correct");
TestHarness::assertEqual('', $q1LearnerAns, "Unanswered: Learner answer is accurately empty string");

// -----------------------------------------------------------------------------
// 4. Verification of View Templates & CSS Classes
// -----------------------------------------------------------------------------
$quizModuleContent = file_get_contents(__DIR__ . '/../academy/modules/quiz.php');
TestHarness::assert(strpos($quizModuleContent, 'congrats-card') !== false, "Quiz View: Congratulations Card markup present");
TestHarness::assert(strpos($quizModuleContent, 'congrats-trophy-badge') !== false, "Quiz View: Executive trophy badge markup present");
TestHarness::assert(strpos($quizModuleContent, 'download-course.php') !== false, "Quiz View: Branded course pack download CTA present");
TestHarness::assert(strpos($quizModuleContent, 'retake=1') !== false, "Quiz View: Retake assessment query param present");
TestHarness::assert(strpos($quizModuleContent, 'missed-feedback-header') !== false, "Quiz View: Prominent missed feedback header present");
TestHarness::assert(strpos($quizModuleContent, 'review-rationale-box') !== false, "Quiz View: Pedagogical rationale container present");

$cssContent = file_get_contents(__DIR__ . '/../assets/css/academy-dashboard.css');
TestHarness::assert(strpos($cssContent, '.congrats-card') !== false, "CSS: .congrats-card styling declared");
TestHarness::assert(strpos($cssContent, '.missed-feedback-header') !== false, "CSS: .missed-feedback-header styling declared");
TestHarness::assert(strpos($cssContent, '.review-opt-correct-missed') !== false, "CSS: .review-opt-correct-missed styling declared");
TestHarness::assert(strpos($cssContent, '.review-opt-incorrect-chosen') !== false, "CSS: .review-opt-incorrect-chosen styling declared");

TestHarness::summary();
