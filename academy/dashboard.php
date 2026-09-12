<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/admin-layout.php';
require_once __DIR__ . '/../lib/academy.php';
require_once __DIR__ . '/../lib/monnify.php';
require_once __DIR__ . '/../lib/support.php';
require_once __DIR__ . '/../lib/workspace-account.php';

session_start();
$pdo = db();
$user = current_user($pdo);
if (!$user) {
    $requestPath = '../academy/dashboard.php';
    $query = (string) ($_SERVER['QUERY_STRING'] ?? '');
    if ($query !== '') {
        $requestPath .= '?' . $query;
    }
    redirect_to('../login.php?next=' . urlencode(ltrim(str_replace('../', '', $requestPath), '/')));
}
if (app_user_needs_email_verification($user)) {
    unset($_SESSION['user_id']);
    redirect_to('../academy/login.php?email=' . urlencode((string) ($user['email'] ?? '')));
}

$academyRole = academy_current_role($pdo, $user);
if ($academyRole !== 'learner' && !admin_feature_is_allowed($pdo, 'training')) {
    http_response_code(403);
    exit('Forbidden: NATCODEV Academy is not enabled for your role.');
}

wallet_ensure_schema($pdo);
academy_ensure_schema($pdo);
support_ensure_schema($pdo);
$wallet = wallet_get_or_create($pdo, (int) $user['id']);
$pdo->exec("
    UPDATE webinar_registrations r
    JOIN webinars w ON w.id = r.webinar_id
    SET r.certificate_status = IF(
        r.completion_status = 'completed'
        AND NOT EXISTS (
            SELECT 1
            FROM academy_lessons l
            LEFT JOIN academy_progress ap
              ON ap.lesson_id = l.id
             AND ap.webinar_id = l.webinar_id
             AND ap.user_id = r.user_id
             AND ap.status = 'completed'
            WHERE l.webinar_id = r.webinar_id
              AND l.status = 'active'
              AND l.is_required = 1
              AND ap.id IS NULL
        )
        AND EXISTS (
            SELECT 1
            FROM academy_assessments aa
            JOIN academy_attempts at
              ON at.assessment_id = aa.id
             AND at.webinar_id = aa.webinar_id
             AND at.user_id = r.user_id
             AND at.passed = 1
            WHERE aa.webinar_id = r.webinar_id
              AND aa.status = 'active'
        ),
        'eligible',
        'not_started'
    )
    WHERE w.certification_required = 1
      AND r.certificate_status = 'not_required'
");
$pdo->exec("
    UPDATE webinar_registrations r
    JOIN academy_certificates c ON c.registration_id = r.id
    SET r.certificate_status = CASE
        WHEN c.status = 'issued' THEN 'issued'
        WHEN c.status = 'pending' THEN 'pending'
        ELSE r.certificate_status
    END
");
$pdo->exec("
    UPDATE academy_certificates c
    JOIN webinar_registrations r ON r.id = c.registration_id
    JOIN webinars w ON w.id = r.webinar_id
    SET c.status = 'rejected',
        c.issued_at = NULL
    WHERE c.status = 'issued'
      AND w.certification_required = 1
      AND (
        EXISTS (
            SELECT 1
            FROM academy_lessons l
            LEFT JOIN academy_progress ap
              ON ap.lesson_id = l.id
             AND ap.webinar_id = l.webinar_id
             AND ap.user_id = r.user_id
             AND ap.status = 'completed'
            WHERE l.webinar_id = r.webinar_id
              AND l.status = 'active'
              AND l.is_required = 1
              AND ap.id IS NULL
        )
        OR NOT EXISTS (
            SELECT 1
            FROM academy_assessments aa
            JOIN academy_attempts at
              ON at.assessment_id = aa.id
             AND at.webinar_id = aa.webinar_id
             AND at.user_id = r.user_id
             AND at.passed = 1
            WHERE aa.webinar_id = r.webinar_id
              AND aa.status = 'active'
        )
      )
");
$pdo->exec("
    UPDATE webinar_registrations r
    JOIN academy_certificates c ON c.registration_id = r.id
    SET r.certificate_status = 'not_started'
    WHERE c.status = 'rejected'
      AND r.certificate_status IN ('eligible', 'issued', 'pending')
");

require_once __DIR__ . '/../lib/academy/dashboard-helpers.php';

$screen = ac_screen((string) ($_GET['screen'] ?? ($_GET['tab'] ?? 'catalog')));
$tabMap = ['learn' => 'learning', 'calendar' => 'learning', 'catalog' => 'catalog', 'certificates' => 'certificates', 'payments' => 'transactions', 'feedback' => 'settings'];
if (isset($tabMap[$screen])) {
    $screen = $tabMap[$screen];
}
$message = (string) ($_GET['message'] ?? $_GET['registered'] ?? '');
$error = (string) ($_GET['error'] ?? '');
$role = $academyRole;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Security token expired. Refresh the Academy dashboard and try again.';
    } else {
        try {
            $action = (string) ($_POST['action'] ?? '');
            if ($action === 'account_profile') {
                workspace_account_update_profile($pdo, (int) $user['id'], $_POST);
                ac_redirect('settings', 'message', 'Account profile updated.');
            }
            if ($action === 'account_password') {
                workspace_account_change_password($pdo, (int) $user['id'], (string) ($_POST['current_password'] ?? ''), (string) ($_POST['new_password'] ?? ''), (string) ($_POST['confirm_password'] ?? ''));
                ac_redirect('settings', 'message', 'Password changed.');
            }

            if ($action === 'complete_lesson') {
                $lessonId = (int) ($_POST['lesson_id'] ?? 0);
                $stmt = $pdo->prepare("
                    SELECT l.*, r.id registration_id, w.certification_required
                    FROM academy_lessons l
                    JOIN webinar_registrations r ON r.webinar_id = l.webinar_id AND r.user_id = ?
                    JOIN webinars w ON w.id = l.webinar_id
                    WHERE l.id = ? AND l.status = 'active'
                    LIMIT 1
                ");
                $stmt->execute([(int) $user['id'], $lessonId]);
                $lesson = $stmt->fetch();
                if (!$lesson) {
                    throw new RuntimeException('Lesson was not found or you are not enrolled.');
                }
                $pdo->prepare("
                    INSERT INTO academy_progress (user_id, webinar_id, lesson_id, status, progress_percent, started_at, completed_at)
                    VALUES (?, ?, ?, 'completed', 100, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE status = 'completed', progress_percent = 100, completed_at = NOW()
                ")->execute([(int) $user['id'], (int) $lesson['webinar_id'], $lessonId]);

                $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM academy_lessons WHERE webinar_id = ? AND status = 'active'");
                $totalStmt->execute([(int) $lesson['webinar_id']]);
                $total = max(1, (int) $totalStmt->fetchColumn());
                $doneStmt = $pdo->prepare("SELECT COUNT(*) FROM academy_progress WHERE user_id = ? AND webinar_id = ? AND status = 'completed'");
                $doneStmt->execute([(int) $user['id'], (int) $lesson['webinar_id']]);
                $progress = min(100, (int) round(((int) $doneStmt->fetchColumn() / $total) * 100));
                $pdo->prepare("
                    UPDATE webinar_registrations
                    SET progress_percent = ?, completion_status = IF(? >= 100, 'completed', 'in_progress'),
                        started_at = COALESCE(started_at, NOW()), completed_at = IF(? >= 100, COALESCE(completed_at, NOW()), completed_at),
                        certificate_status = IF(? >= 100 AND ? = 1, 'not_started', certificate_status)
                    WHERE id = ?
                ")->execute([$progress, $progress, $progress, $progress, (int) $lesson['certification_required'], (int) $lesson['registration_id']]);
                $eligibility = ac_certificate_eligibility($pdo, (int) $user['id'], (int) $lesson['registration_id']);
                if ($eligibility['eligible']) {
                    $pdo->prepare("UPDATE webinar_registrations SET certificate_status = 'eligible' WHERE id = ?")->execute([(int) $lesson['registration_id']]);
                }
                ac_redirect('lesson', 'message', 'Lesson marked complete.', ['course_id' => (int) $lesson['webinar_id']]);
            }

            if ($action === 'submit_assessment') {
                $assessmentId = (int) ($_POST['assessment_id'] ?? 0);
                $assessmentStmt = $pdo->prepare("
                    SELECT a.*, r.id registration_id
                    FROM academy_assessments a
                    JOIN webinar_registrations r ON r.webinar_id = a.webinar_id AND r.user_id = ?
                    WHERE a.id = ? AND a.status = 'active'
                    LIMIT 1
                ");
                $assessmentStmt->execute([(int) $user['id'], $assessmentId]);
                $assessment = $assessmentStmt->fetch();
                if (!$assessment) {
                    throw new RuntimeException('Assessment was not found or you are not enrolled.');
                }
                $questionsStmt = $pdo->prepare("SELECT * FROM academy_questions WHERE assessment_id = ? AND status = 'active' ORDER BY sort_order ASC, id ASC");
                $questionsStmt->execute([$assessmentId]);
                $questions = $questionsStmt->fetchAll();
                if (!$questions) {
                    throw new RuntimeException('This assessment has no active questions yet.');
                }
                $answers = (array) ($_POST['answers'] ?? []);
                $earned = 0.0;
                $possible = 0.0;
                foreach ($questions as $question) {
                    $possible += (float) $question['points'];
                    $answer = strtoupper(trim((string) ($answers[(int) $question['id']] ?? '')));
                    if ($answer === strtoupper((string) $question['correct_option'])) {
                        $earned += (float) $question['points'];
                    }
                }
                $score = $possible > 0 ? round(($earned / $possible) * 100, 2) : 0;
                $passed = $score >= (float) $assessment['pass_score'] ? 1 : 0;
                $pdo->prepare("
                    INSERT INTO academy_attempts (assessment_id, webinar_id, user_id, score_percent, passed, answers, completed_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ")->execute([$assessmentId, (int) $assessment['webinar_id'], (int) $user['id'], $score, $passed, json_encode($answers, JSON_UNESCAPED_SLASHES)]);
                if ($passed) {
                    $eligibility = ac_certificate_eligibility($pdo, (int) $user['id'], (int) $assessment['registration_id']);
                    $pdo->prepare("
                        UPDATE webinar_registrations
                        SET certificate_status = ?, completion_status = IF(? = 1, 'completed', completion_status),
                            progress_percent = IF(? = 1, 100, progress_percent),
                            completed_at = IF(? = 1, COALESCE(completed_at, NOW()), completed_at)
                        WHERE id = ?
                    ")->execute([
                        $eligibility['eligible'] ? 'eligible' : 'not_started',
                        $eligibility['eligible'] ? 1 : 0,
                        $eligibility['eligible'] ? 1 : 0,
                        $eligibility['eligible'] ? 1 : 0,
                        (int) $assessment['registration_id'],
                    ]);
                }
                ac_redirect('quiz', $passed ? 'message' : 'error', $passed ? 'Assessment passed. Certificate eligibility will unlock after all required lessons are complete.' : 'Assessment submitted. Score: ' . $score . '%.', ['course_id' => (int) $assessment['webinar_id']]);
            }

            if ($action === 'request_certificate') {
                $registrationId = (int) ($_POST['registration_id'] ?? 0);
                $eligibility = ac_certificate_eligibility($pdo, (int) $user['id'], $registrationId);
                $registration = $eligibility['registration'];
                if (!$eligibility['eligible']) {
                    throw new RuntimeException(implode(' ', $eligibility['reasons']));
                }
                $status = (int) $registration['certificate_approval_required'] === 1 ? 'pending' : 'issued';
                $ref = academy_certificate_ref((int) $user['id'], (int) $registration['webinar_id']);
                $pdo->prepare("
                    INSERT INTO academy_certificates (user_id, webinar_id, registration_id, certificate_ref, status, issued_at)
                    VALUES (?, ?, ?, ?, ?, IF(? = 'issued', NOW(), NULL))
                    ON DUPLICATE KEY UPDATE
                        status = IF(status = 'rejected', VALUES(status), status),
                        issued_at = IF(status = 'rejected' AND VALUES(status) = 'issued', NOW(), issued_at),
                        requested_at = IF(status = 'rejected', CURRENT_TIMESTAMP, requested_at),
                        notes = IF(status = 'rejected', NULL, notes)
                ")->execute([(int) $user['id'], (int) $registration['webinar_id'], $registrationId, $ref, $status, $status]);
                $pdo->prepare("UPDATE webinar_registrations SET certificate_status = ? WHERE id = ?")->execute([$status === 'issued' ? 'issued' : 'pending', $registrationId]);
                ac_redirect('certificates', 'message', $status === 'issued' ? 'Certificate issued.' : 'Certificate request sent for approval.');
            }

            if ($action === 'resubmit_certificate') {
                $certificateId = (int) ($_POST['certificate_id'] ?? 0);
                $fixNote = trim((string) ($_POST['fix_note'] ?? ''));
                if ($fixNote === '') {
                    throw new RuntimeException('Tell the review team what you fixed before resubmitting.');
                }
                $certStmt = $pdo->prepare("SELECT c.*, r.id registration_id FROM academy_certificates c JOIN webinar_registrations r ON r.id = c.registration_id WHERE c.id = ? AND c.user_id = ? AND c.status = 'rejected' LIMIT 1");
                $certStmt->execute([$certificateId, (int) $user['id']]);
                $cert = $certStmt->fetch();
                if (!$cert) {
                    throw new RuntimeException('Rejected certificate request was not found.');
                }
                $eligibility = ac_certificate_eligibility($pdo, (int) $user['id'], (int) $cert['registration_id']);
                if (!$eligibility['eligible']) {
                    throw new RuntimeException(implode(' ', $eligibility['reasons']));
                }
                $pdo->prepare("UPDATE academy_certificates SET status = 'pending', issued_at = NULL, requested_at = CURRENT_TIMESTAMP, resubmission_notes = ?, resubmitted_at = NOW() WHERE id = ? AND user_id = ?")->execute([$fixNote, $certificateId, (int) $user['id']]);
                $pdo->prepare("UPDATE webinar_registrations SET certificate_status = 'pending' WHERE id = ? AND user_id = ?")->execute([(int) $cert['registration_id'], (int) $user['id']]);
                ac_redirect('certificates', 'message', 'Certificate correction submitted for review.', ['course_id' => (int) $cert['webinar_id']]);
            }
            if ($action === 'request_group_certificate') {
                $groupId = (int) ($_POST['group_id'] ?? 0);
                $groupStmt = $pdo->prepare("SELECT * FROM academy_certificate_groups WHERE id = ? AND status = 'active' LIMIT 1");
                $groupStmt->execute([$groupId]);
                $group = $groupStmt->fetch();
                if (!$group) {
                    throw new RuntimeException('Certificate pathway was not found.');
                }
                $eligibility = academy_group_eligibility($pdo, (int) $user['id'], $groupId);
                if (!$eligibility['eligible']) {
                    throw new RuntimeException('Complete all required courses before requesting this certificate pathway.');
                }
                $status = (int) $group['certificate_approval_required'] === 1 ? 'pending' : 'issued';
                $ref = academy_group_certificate_ref((int) $user['id'], $groupId);
                $pdo->prepare("
                    INSERT INTO academy_group_certificates (user_id, group_id, certificate_ref, status, issued_at)
                    VALUES (?, ?, ?, ?, IF(? = 'issued', NOW(), NULL))
                    ON DUPLICATE KEY UPDATE
                        status = IF(status = 'rejected', VALUES(status), status),
                        issued_at = IF(status = 'rejected' AND VALUES(status) = 'issued', NOW(), issued_at),
                        requested_at = IF(status = 'rejected', CURRENT_TIMESTAMP, requested_at),
                        notes = IF(status = 'rejected', NULL, notes)
                ")->execute([(int) $user['id'], $groupId, $ref, $status, $status]);
                ac_redirect('certificates', 'message', $status === 'issued' ? 'Certificate pathway issued.' : 'Certificate pathway request sent for approval.');
            }

            if ($action === 'resubmit_group_certificate') {
                $certificateId = (int) ($_POST['certificate_id'] ?? 0);
                $fixNote = trim((string) ($_POST['fix_note'] ?? ''));
                if ($fixNote === '') {
                    throw new RuntimeException('Tell the review team what you fixed before resubmitting.');
                }
                $certStmt = $pdo->prepare("SELECT * FROM academy_group_certificates WHERE id = ? AND user_id = ? AND status = 'rejected' LIMIT 1");
                $certStmt->execute([$certificateId, (int) $user['id']]);
                $cert = $certStmt->fetch();
                if (!$cert) {
                    throw new RuntimeException('Rejected certificate pathway request was not found.');
                }
                $eligibility = academy_group_eligibility($pdo, (int) $user['id'], (int) $cert['group_id']);
                if (!$eligibility['eligible']) {
                    throw new RuntimeException('Complete all required courses before resubmitting this certificate pathway.');
                }
                $pdo->prepare("UPDATE academy_group_certificates SET status = 'pending', issued_at = NULL, requested_at = CURRENT_TIMESTAMP, resubmission_notes = ?, resubmitted_at = NOW() WHERE id = ? AND user_id = ?")->execute([$fixNote, $certificateId, (int) $user['id']]);
                ac_redirect('certificates', 'message', 'Certificate pathway correction submitted for review.');
            }
            if ($action === 'academy_request_withdrawal') {
                $withdrawal = wallet_request_withdrawal($pdo, $user, [
                    'amount' => $_POST['withdraw_amount'] ?? 0,
                    'provider' => $_POST['withdraw_provider'] ?? 'monnify',
                    'bank_code' => $_POST['bank_code'] ?? '',
                    'bank_name' => $_POST['bank_name'] ?? '',
                    'account_number' => $_POST['account_number'] ?? '',
                    'account_name' => $_POST['account_name'] ?? '',
                    'note' => $_POST['withdraw_note'] ?? 'Academy wallet withdrawal',
                ]);
                if ($withdrawal['success']) {
                    ac_redirect('transactions', 'message', 'Withdrawal request submitted. Reference: ' . (string) $withdrawal['reference'] . '.');
                }
                throw new RuntimeException((string) ($withdrawal['error'] ?? 'Unable to submit withdrawal request.'));
            }
            if ($action === 'request_refund') {
                $transactionId = (int) ($_POST['transaction_id'] ?? 0);
                $stmt = $pdo->prepare("
                    SELECT wt.*
                    FROM wallet_transactions wt
                    JOIN wallets w ON w.id = wt.wallet_id
                    WHERE wt.id = ? AND w.user_id = ?
                    LIMIT 1
                ");
                $stmt->execute([$transactionId, (int) $user['id']]);
                $tx = $stmt->fetch();
                if (!$tx) {
                    throw new RuntimeException('Transaction was not found.');
                }
                $courseId = ac_course_id_from_tx($tx);
                if ($courseId <= 0) {
                    throw new RuntimeException('This is not an Academy training payment.');
                }
                $regStmt = $pdo->prepare("
                    SELECT r.completion_status, r.certificate_status,
                           (SELECT COUNT(*) FROM academy_certificates c WHERE c.registration_id = r.id AND c.status = 'issued') issued_certificates
                    FROM webinar_registrations r
                    WHERE r.user_id = ? AND r.webinar_id = ?
                    LIMIT 1
                ");
                $regStmt->execute([(int) $user['id'], $courseId]);
                $reg = $regStmt->fetch();
                if ($reg && ((string) $reg['completion_status'] === 'completed' || (string) $reg['certificate_status'] === 'issued' || (int) $reg['issued_certificates'] > 0)) {
                    throw new RuntimeException('Refund is not available after completion or certificate issuance.');
                }
                $existing = $pdo->prepare("SELECT id FROM academy_refund_requests WHERE user_id = ? AND transaction_id = ? AND status IN ('pending','approved','paid') LIMIT 1");
                $existing->execute([(int) $user['id'], $transactionId]);
                if ($existing->fetchColumn()) {
                    ac_redirect('transactions', 'message', 'A refund request already exists for this transaction.');
                }
                $pdo->prepare("
                    INSERT INTO academy_refund_requests (user_id, webinar_id, transaction_id, amount, reason)
                    VALUES (?, ?, ?, ?, ?)
                ")->execute([(int) $user['id'], $courseId, $transactionId, (float) $tx['amount'], trim((string) ($_POST['reason'] ?? 'Academy refund request.'))]);
                ac_redirect('transactions', 'message', 'Refund request submitted.');
            }

            if ($action === 'academy_support_create') {
                $category = (string) ($_POST['category'] ?? 'academy');
                $ref = support_create_ticket($pdo, [
                    'name' => $user['name'] ?? '',
                    'email' => $user['email'] ?? '',
                    'phone' => $user['phone'] ?? '',
                    'category' => $category,
                    'priority' => $_POST['priority'] ?? 'medium',
                    'subject' => $_POST['subject'] ?? '',
                    'description' => $_POST['description'] ?? '',
                    'linked_record_type' => $_POST['linked_record_type'] ?? 'course_enrollment',
                    'linked_record_ref' => $_POST['linked_record_ref'] ?? '',
                ], $user);
                $returnScreen = ac_screen((string) ($_POST['return_screen'] ?? 'support'));
                ac_redirect($returnScreen, 'message', 'Support ticket ' . $ref . ' has been opened.', ['ticket' => $ref]);
            }

            if ($action === 'academy_support_reply') {
                $ref = preg_replace('/[^A-Z0-9-]/i', '', (string) ($_POST['ticket_ref'] ?? ''));
                $ticket = support_ticket_by_ref($pdo, $ref);
                if (!$ticket || (int) ($ticket['user_id'] ?? 0) !== (int) $user['id']) {
                    throw new RuntimeException('This support ticket does not belong to your learner account.');
                }
                if (in_array((string) $ticket['status'], ['resolved', 'closed', 'rejected'], true)) {
                    throw new RuntimeException('This ticket is closed. Open a new help request if you still need support.');
                }
                support_add_message($pdo, (int) $ticket['id'], (string) ($_POST['reply'] ?? ''), $user, false, 'public', (string) ($user['name'] ?? 'Learner'), 'learner');
                $pdo->prepare("UPDATE support_tickets SET status = IF(status = 'waiting_on_user', 'open', status), last_activity_at = NOW() WHERE id = ?")->execute([(int) $ticket['id']]);
                $returnScreen = ac_screen((string) ($_POST['return_screen'] ?? 'support'));
                ac_redirect($returnScreen, 'message', 'Reply added to ticket ' . $ref . '.', ['ticket' => $ref]);
            }

            if ($action === 'submit_feedback') {
                $courseId = (int) ($_POST['webinar_id'] ?? 0);
                $rating = max(1, min(5, (int) ($_POST['rating'] ?? 5)));
                $enrolled = $pdo->prepare("SELECT id FROM webinar_registrations WHERE webinar_id = ? AND user_id = ? LIMIT 1");
                $enrolled->execute([$courseId, (int) $user['id']]);
                if (!$enrolled->fetchColumn()) {
                    throw new RuntimeException('You can only rate a course you enrolled for.');
                }
                $pdo->prepare("
                    INSERT INTO academy_feedback (webinar_id, user_id, rating, comment, status)
                    VALUES (?, ?, ?, ?, 'visible')
                    ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment), status = 'visible'
                ")->execute([$courseId, (int) $user['id'], $rating, trim((string) ($_POST['comment'] ?? '')) ?: null]);
                ac_redirect('settings', 'message', 'Course feedback saved.');
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$catalog = academy_courses($pdo, $role, true);
$catalogQuery = trim((string) ($_GET['q'] ?? ''));
$catalogCategories = [];
foreach ($catalog as $catalogCourse) {
    $cat = trim((string) ($catalogCourse['category'] ?? 'Professional Skills'));
    $catalogCategories[$cat === '' ? 'Professional Skills' : $cat] = true;
}
ksort($catalogCategories);
$catalogCategory = trim((string) ($_GET['category'] ?? ''));
$catalogFiltered = array_values(array_filter($catalog, static function (array $course) use ($catalogQuery, $catalogCategory): bool {
    if ($catalogCategory !== '' && strcasecmp((string) ($course['category'] ?? ''), $catalogCategory) !== 0) {
        return false;
    }
    if ($catalogQuery === '') {
        return true;
    }
    $haystack = (string) ($course['title'] ?? '') . ' ' . (string) ($course['description'] ?? '') . ' ' . (string) ($course['program_title'] ?? '') . ' ' . (string) ($course['category'] ?? '');
    return stripos($haystack, $catalogQuery) !== false;
}));
$catalogPerPage = 12;
$catalogPage = max(1, (int) ($_GET['page'] ?? 1));
$catalogTotal = count($catalogFiltered);
$catalogPages = max(1, (int) ceil($catalogTotal / $catalogPerPage));
$catalogPage = min($catalogPage, $catalogPages);
$catalogPaged = array_slice($catalogFiltered, ($catalogPage - 1) * $catalogPerPage, $catalogPerPage);
$registered = academy_registered_courses($pdo, (int) $user['id']);
$registeredIds = array_map(static fn(array $row): int => (int) $row['id'], $registered);
$requestedCourseId = (int) ($_GET['course_id'] ?? 0);
$course = ac_current_course($catalog, $registered, $requestedCourseId);
$courseId = $course ? (int) $course['id'] : 0;
$isCourseRegistered = $courseId > 0 && in_array($courseId, $registeredIds, true);
$requestedCourseAvailable = $screen === 'learning' && $requestedCourseId > 0 && $courseId === $requestedCourseId && !$isCourseRegistered;
$lessons = $courseId > 0 ? academy_lessons_for_course($pdo, $courseId) : [];
$completedLessonIds = [];
if ($courseId > 0) {
    $progressStmt = $pdo->prepare("SELECT lesson_id FROM academy_progress WHERE user_id = ? AND webinar_id = ? AND status = 'completed'");
    $progressStmt->execute([(int) $user['id'], $courseId]);
    $completedLessonIds = array_map('intval', $progressStmt->fetchAll(PDO::FETCH_COLUMN));
}
$selectedLessonId = (int) ($_GET['lesson_id'] ?? 0);
$selectedLesson = null;
foreach ($lessons as $lessonRow) {
    if ($selectedLessonId > 0 && (int) $lessonRow['id'] === $selectedLessonId) {
        $selectedLesson = $lessonRow;
        break;
    }
}
if (!$selectedLesson && $lessons) {
    foreach ($lessons as $lessonRow) {
        if (!in_array((int) $lessonRow['id'], $completedLessonIds, true)) {
            $selectedLesson = $lessonRow;
            break;
        }
    }
    $selectedLesson = $selectedLesson ?: $lessons[0];
}
$assessment = $courseId > 0 ? academy_assessment_for_course($pdo, $courseId) : null;
$questions = [];
if ($assessment) {
    $qStmt = $pdo->prepare("SELECT * FROM academy_questions WHERE assessment_id = ? AND status = 'active' ORDER BY sort_order ASC, id ASC");
    $qStmt->execute([(int) $assessment['id']]);
    $questions = $qStmt->fetchAll();
}
$currentRegistration = null;
foreach ($registered as $registrationRow) {
    if ((int) $registrationRow['id'] === $courseId) {
        $currentRegistration = $registrationRow;
        break;
    }
}
$currentAssessmentPassed = false;
if ($courseId > 0) {
    $passedStmt = $pdo->prepare("SELECT COUNT(*) FROM academy_attempts WHERE user_id = ? AND webinar_id = ? AND passed = 1");
    $passedStmt->execute([(int) $user['id'], $courseId]);
    $currentAssessmentPassed = (int) $passedStmt->fetchColumn() > 0;
}
$catalogGroupLabels = [];
foreach ($catalogFiltered as $catalogCourse) {
    $cat = trim((string) ($catalogCourse['category'] ?? 'Professional Skills'));
    $catalogGroupLabels[$cat === '' ? 'Professional Skills' : $cat] = true;
}
ksort($catalogGroupLabels);
$catalogPagedGroups = [];
foreach ($catalogPaged as $catalogCourse) {
    $cat = trim((string) ($catalogCourse['category'] ?? 'Professional Skills'));
    $catalogPagedGroups[$cat === '' ? 'Professional Skills' : $cat][] = $catalogCourse;
}
$journeySteps = ac_journey_steps(
    $course,
    $isCourseRegistered,
    count($completedLessonIds),
    count($lessons),
    $currentAssessmentPassed,
    $currentRegistration['payment_status'] ?? null,
    $currentRegistration['certificate_status'] ?? null
);
$programs = $pdo->query("SELECT * FROM academy_programs WHERE status = 'active' ORDER BY sort_order ASC, title ASC")->fetchAll();
$certificateGroups = academy_certificate_groups($pdo, $role, true);
$certStmt = $pdo->prepare("SELECT c.*, w.title course_title FROM academy_certificates c JOIN webinars w ON w.id = c.webinar_id WHERE c.user_id = ? ORDER BY c.requested_at DESC");
$certStmt->execute([(int) $user['id']]);
$certificates = $certStmt->fetchAll();
$currentCourseCertificate = null;
foreach ($certificates as $certificateRow) {
    if ($courseId > 0 && (int) $certificateRow['webinar_id'] === $courseId) {
        $currentCourseCertificate = $certificateRow;
        break;
    }
}
$currentCertificateEligibility = $currentRegistration ? ac_certificate_eligibility($pdo, (int) $user['id'], (int) $currentRegistration['registration_id']) : null;
$completedStmt = $pdo->prepare("
    SELECT r.id registration_id, r.webinar_id, r.completed_at, r.progress_percent, r.completion_status, r.certificate_status,
           w.title course_title,
           (SELECT MAX(at.score_percent) FROM academy_attempts at WHERE at.user_id = r.user_id AND at.webinar_id = r.webinar_id AND at.passed = 1) best_score,
           c.certificate_ref, c.status certificate_record_status
    FROM webinar_registrations r
    JOIN webinars w ON w.id = r.webinar_id
    LEFT JOIN academy_certificates c ON c.registration_id = r.id AND c.user_id = r.user_id
    WHERE r.user_id = ? AND r.completion_status = 'completed'
    ORDER BY COALESCE(r.completed_at, r.registered_at) DESC
    LIMIT 6
");
$completedStmt->execute([(int) $user['id']]);
$recentCompletedCourses = $completedStmt->fetchAll();
$groupCertStmt = $pdo->prepare("SELECT c.*, g.title group_title FROM academy_group_certificates c JOIN academy_certificate_groups g ON g.id = c.group_id WHERE c.user_id = ? ORDER BY c.requested_at DESC");
$groupCertStmt->execute([(int) $user['id']]);
$groupCertificates = $groupCertStmt->fetchAll();
$txStmt = $pdo->prepare("
    SELECT wt.*,
           (SELECT rr.status FROM academy_refund_requests rr WHERE rr.transaction_id = wt.id AND rr.user_id = ? ORDER BY rr.requested_at DESC LIMIT 1) refund_status
    FROM wallet_transactions wt
    JOIN wallets w ON w.id = wt.wallet_id
    WHERE w.user_id = ? AND (wt.reference LIKE 'NAT-TRAIN-%' OR wt.description LIKE '%training%')
    ORDER BY wt.created_at DESC
    LIMIT 80
");
$txStmt->execute([(int) $user['id'], (int) $user['id']]);
$transactions = $txStmt->fetchAll();
$refundCountStmt = $pdo->prepare("SELECT COUNT(*) FROM academy_refund_requests WHERE user_id = ?");
$refundCountStmt->execute([(int) $user['id']]);
$refundRequestCount = (int) $refundCountStmt->fetchColumn();
$walletStmt = $pdo->prepare("SELECT COALESCE(balance, 0) FROM wallets WHERE user_id = ? LIMIT 1");
$walletStmt->execute([(int) $user['id']]);
$walletBalance = (float) ($walletStmt->fetchColumn() ?: 0);
$academyWithdrawals = [];
if (!empty($wallet['id'])) {
    $withdrawalStmt = $pdo->prepare("SELECT * FROM wallet_withdrawals WHERE wallet_id = ? ORDER BY requested_at DESC LIMIT 10");
    $withdrawalStmt->execute([(int) $wallet['id']]);
    $academyWithdrawals = $withdrawalStmt->fetchAll();
}
$messages = [];
if (app_table_exists($pdo, 'messages')) {
    $msgStmt = $pdo->prepare("SELECT * FROM messages WHERE user_id = ? AND (ticket_id LIKE 'ACAD-%' OR message LIKE '%Academy%' OR message LIKE '%training%') ORDER BY created_at DESC LIMIT 20");
    $msgStmt->execute([(int) $user['id']]);
    $messages = $msgStmt->fetchAll();
}
$supportCategories = array_intersect_key(support_categories(), array_flip(['academy', 'payments', 'verification', 'account', 'technical', 'general']));
$supportPriorities = support_priorities();
$supportStatuses = support_statuses();
$learnerTickets = support_user_tickets($pdo, (int) $user['id']);
$selectedSupportRef = preg_replace('/[^A-Z0-9-]/i', '', (string) ($_GET['ticket'] ?? ''));
if ($selectedSupportRef === '' && $learnerTickets) {
    $selectedSupportRef = (string) $learnerTickets[0]['ticket_ref'];
}
$selectedSupportTicket = null;
$selectedSupportMessages = [];
if ($selectedSupportRef !== '') {
    $candidate = support_ticket_by_ref($pdo, $selectedSupportRef);
    if ($candidate && (int) ($candidate['user_id'] ?? 0) === (int) $user['id']) {
        $selectedSupportTicket = $candidate;
        $selectedSupportMessages = support_ticket_messages($pdo, (int) $candidate['id'], false);
    }
}
$supportStats = ['open' => 0, 'in_progress' => 0, 'waiting_on_user' => 0, 'resolved' => 0, 'all' => count($learnerTickets)];
foreach ($learnerTickets as $ticket) {
    $ticketStatus = (string) $ticket['status'];
    $supportStats[$ticketStatus] = ($supportStats[$ticketStatus] ?? 0) + 1;
}
$feedbackStmt = $pdo->prepare("SELECT f.*, w.title course_title FROM academy_feedback f JOIN webinars w ON w.id = f.webinar_id WHERE f.user_id = ? ORDER BY f.created_at DESC");
$feedbackStmt->execute([(int) $user['id']]);
$myFeedback = $feedbackStmt->fetchAll();
$activeLearning = array_values(array_filter($registered, static fn(array $row): bool => (string) ($row['completion_status'] ?? '') !== 'completed'));
$completedLearning = array_values(array_filter($registered, static fn(array $row): bool => (string) ($row['completion_status'] ?? '') === 'completed'));
$avgProgress = $registered ? (int) round(array_sum(array_map(static fn(array $row): int => (int) ($row['progress_percent'] ?? 0), $registered)) / count($registered)) : 0;
$logo = app_primary_logo_url();
$screenTitles = [
    'catalog' => ['Academy Catalog', 'Browse courses by role'],
    'course' => ['Course Detail', $course ? (string) $course['title'] : 'Course information'],
    'checkout' => ['Checkout / Register', 'Complete your enrollment'],
    'learning' => ['My Learning', 'Your enrolled courses'],
    'lesson' => ['Lesson Player', $course ? (string) $course['title'] : 'Course lessons'],
    'quiz' => ['Quiz / Exam', $assessment ? (string) $assessment['title'] : 'Assessment'],
    'certificates' => ['Certificates & Pathways', 'View, download and verify credentials'],
    'transactions' => ['Transactions & Refunds', 'Payments, receipts and refund requests'],
    'messages' => ['Academy Messages', 'Course notices and support updates'],
    'settings' => ['Settings & Feedback', 'Learning preferences and course ratings'],
    'support' => ['Academy Support', 'Get help with courses, payments and certificates'],
];
$titleSet = $screenTitles[$screen];
$initials = strtoupper(substr((string) ($user['name'] ?? 'U'), 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>NATCODEV Academy - <?= e($titleSet[0]) ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/academy-dashboard.css">
</head>
<body>
<div class="sidebar-overlay" id="overlay"></div>
<?php require __DIR__ . '/../lib/layout-components/academy-sidebar.php'; ?>
<?php require __DIR__ . '/../lib/layout-components/academy-topbar.php'; ?>
<main class="main">
  <?php if ($message): ?><div class="notice ok"><?= e($message) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="notice err"><?= e($error) ?></div><?php endif; ?>
  <div class="breadcrumb"><a href="dashboard.php?screen=catalog">Academy</a><i class="fas fa-chevron-right" style="font-size:10px"></i><span><?= e($titleSet[0]) ?></span></div>
  <div class="pg-head"><div><h2><?= e($titleSet[0]) ?></h2><div class="sub"><?= e($titleSet[1]) ?>. Practical knowledge, verified skills, real platform outcomes.</div></div><a class="btn btn-o" href="dashboard.php?screen=catalog"><i class="fas fa-home"></i> Academy Home</a></div>

<?php if ($screen === 'catalog'): require __DIR__ . '/modules/catalog.php'; endif; ?>


<?php if ($screen === 'course' && $course): require __DIR__ . '/modules/course.php'; endif; ?>


<?php if ($screen === 'checkout' && $course): require __DIR__ . '/modules/checkout.php'; endif; ?>

<?php if ($screen === 'learning'): require __DIR__ . '/modules/learning.php'; endif; ?>


<?php if ($screen === 'lesson' && $course): require __DIR__ . '/modules/lesson.php'; endif; ?>


<?php if ($screen === 'quiz' && $course): require __DIR__ . '/modules/quiz.php'; endif; ?>


<?php if ($screen === 'certificates'): require __DIR__ . '/modules/certificates.php'; endif; ?>


<?php if ($screen === 'transactions'): require __DIR__ . '/modules/transactions.php'; endif; ?>


<?php if ($screen === 'messages'): require __DIR__ . '/modules/messages.php'; endif; ?>

<?php if ($screen === 'settings'): require __DIR__ . '/modules/settings.php'; endif; ?>


<?php if ($screen === 'support'): require __DIR__ . '/modules/support.php'; endif; ?>


  <?php require __DIR__ . '/partials/learning-summary.php'; ?>
</main>
<script src="../assets/js/academy-dashboard.js"></script>
</body>
</html>
