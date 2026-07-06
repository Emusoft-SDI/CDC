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

function ac_screen(string $value): string
{
    $value = preg_replace('/[^a-z-]/', '', $value) ?: 'catalog';
    return in_array($value, ['catalog', 'course', 'checkout', 'learning', 'lesson', 'quiz', 'certificates', 'transactions', 'messages', 'settings', 'support'], true) ? $value : 'catalog';
}

function ac_redirect(string $screen, string $key, string $message, array $extra = []): void
{
    redirect_to('dashboard.php?' . http_build_query(['screen' => $screen, $key => $message] + $extra));
}

function ac_money(float $amount): string
{
    return 'NGN ' . number_format($amount, 2);
}

function ac_status(string $value): string
{
    return ucwords(str_replace('_', ' ', $value));
}

function ac_badge(string $status, ?string $label = null): string
{
    $class = match ($status) {
        'completed', 'issued', 'paid', 'free', 'active', 'visible' => 'bp-green',
        'in_progress', 'registered', 'pending', 'scheduled', 'open' => 'bp-blue',
        'eligible', 'approved' => 'bp-teal',
        'failed', 'rejected', 'cancelled' => 'bp-red',
        'not_started', 'not_required', 'inactive' => 'bp-gray',
        default => 'bp-orange',
    };
    return '<span class="badge-pill ' . e($class) . '">' . e($label ?? ac_status($status)) . '</span>';
}

function ac_course_id_from_tx(array $tx): int
{
    $payload = json_decode((string) ($tx['provider_payload'] ?? ''), true);
    if (is_array($payload)) {
        $id = (int) ($payload['webinar_id'] ?? $payload['training_webinar_id'] ?? 0);
        if ($id > 0) {
            return $id;
        }
    }
    if (preg_match('/^NAT-TRAIN(?:-WALLET)?-\d+-(\d+)-/', (string) ($tx['reference'] ?? ''), $match)) {
        return (int) $match[1];
    }
    return 0;
}

function ac_current_course(array $courses, array $registered, int $courseId): ?array
{
    foreach ($registered as $course) {
        if ((int) $course['id'] === $courseId) {
            return $course;
        }
    }
    foreach ($courses as $course) {
        if ((int) $course['id'] === $courseId) {
            return $course;
        }
    }
    return $registered[0] ?? ($courses[0] ?? null);
}

function ac_certificate_eligibility(PDO $pdo, int $userId, int $registrationId): array
{
    $stmt = $pdo->prepare("
        SELECT r.*, w.title, w.certification_required, w.certificate_approval_required
        FROM webinar_registrations r
        JOIN webinars w ON w.id = r.webinar_id
        WHERE r.id = ? AND r.user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$registrationId, $userId]);
    $registration = $stmt->fetch();
    if (!$registration) {
        return ['eligible' => false, 'registration' => null, 'reasons' => ['Enrollment was not found.'], 'passed_score' => null];
    }

    $reasons = [];
    if ((int) $registration['certification_required'] !== 1) {
        $reasons[] = 'This course is not a certification track.';
    }

    $lessonStmt = $pdo->prepare("
        SELECT
            COUNT(*) required_lessons,
            COUNT(ap.id) completed_lessons
        FROM academy_lessons l
        LEFT JOIN academy_progress ap
          ON ap.lesson_id = l.id
         AND ap.webinar_id = l.webinar_id
         AND ap.user_id = ?
         AND ap.status = 'completed'
        WHERE l.webinar_id = ?
          AND l.status = 'active'
          AND l.is_required = 1
    ");
    $lessonStmt->execute([$userId, (int) $registration['webinar_id']]);
    $lessonStatus = $lessonStmt->fetch() ?: ['required_lessons' => 0, 'completed_lessons' => 0];
    if ((int) $lessonStatus['completed_lessons'] < (int) $lessonStatus['required_lessons']) {
        $reasons[] = 'Complete all required lessons before requesting a certificate.';
    }

    $assessmentStmt = $pdo->prepare("
        SELECT a.id, a.pass_score, MAX(CASE WHEN at.passed = 1 THEN at.score_percent ELSE NULL END) passed_score
        FROM academy_assessments a
        LEFT JOIN academy_attempts at
          ON at.assessment_id = a.id
         AND at.webinar_id = a.webinar_id
         AND at.user_id = ?
        WHERE a.webinar_id = ?
          AND a.status = 'active'
        GROUP BY a.id, a.pass_score
        ORDER BY a.id ASC
        LIMIT 1
    ");
    $assessmentStmt->execute([$userId, (int) $registration['webinar_id']]);
    $assessmentStatus = $assessmentStmt->fetch();
    if (!$assessmentStatus) {
        $reasons[] = 'A passed assessment is required before certificate issuance.';
    } elseif ($assessmentStatus['passed_score'] === null) {
        $reasons[] = 'Pass the course assessment before requesting a certificate.';
    }

    return [
        'eligible' => $reasons === [],
        'registration' => $registration,
        'reasons' => $reasons,
        'passed_score' => $assessmentStatus['passed_score'] ?? null,
        'required_lessons' => (int) $lessonStatus['required_lessons'],
        'completed_lessons' => (int) $lessonStatus['completed_lessons'],
    ];
}

function ac_journey_steps(?array $course, bool $isRegistered, int $completedLessons, int $totalLessons, bool $assessmentPassed, ?string $paymentStatus, ?string $certificateStatus): array
{
    $hasCourse = $course !== null;
    $lessonsDone = $isRegistered && $totalLessons > 0 && $completedLessons >= $totalLessons;
    return [
        ['screen' => 'catalog', 'label' => 'Browse', 'status' => $hasCourse ? 'completed' : 'current', 'note' => $hasCourse ? 'Course selected' : 'Choose a course'],
        ['screen' => 'course', 'label' => 'Course Detail', 'status' => $hasCourse ? 'completed' : 'pending', 'note' => $hasCourse ? (string) $course['title'] : 'No course selected'],
        ['screen' => 'checkout', 'label' => 'Register / Pay', 'status' => $isRegistered ? 'completed' : ($hasCourse ? 'current' : 'pending'), 'note' => $isRegistered ? ac_status((string) ($paymentStatus ?: 'registered')) : 'Not enrolled'],
        ['screen' => 'lesson', 'label' => 'Lessons', 'status' => $lessonsDone ? 'completed' : ($isRegistered ? 'current' : 'pending'), 'note' => $completedLessons . '/' . $totalLessons . ' completed'],
        ['screen' => 'quiz', 'label' => 'Assessment', 'status' => $assessmentPassed ? 'completed' : ($lessonsDone ? 'current' : 'pending'), 'note' => $assessmentPassed ? 'Passed' : 'Not passed'],
        ['screen' => 'certificates', 'label' => 'Certificate', 'status' => in_array((string) $certificateStatus, ['issued', 'eligible', 'pending'], true) ? 'completed' : ($assessmentPassed ? 'current' : 'pending'), 'note' => ac_status((string) ($certificateStatus ?: 'not_started'))],
    ];
}

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
<style>
:root{--green-900:#0d3310;--green-800:#14521a;--green-700:#1B5E20;--green-600:#2E7D32;--green-500:#43A047;--green-100:#E8F5E9;--green-50:#F1F8E9;--teal:#26A69A;--bg:#F5F7FA;--card:#fff;--text:#1A1A2E;--muted:#6B7280;--border:#E5E7EB;--sidebar-w:260px;--topbar-h:68px;--red:#EF4444;--orange:#F59E0B;--blue:#3B82F6;--purple:#8B5CF6}
*{margin:0;padding:0;box-sizing:border-box}body{font-family:Inter,"Segoe UI",Arial,sans-serif;background:var(--bg);color:var(--text);font-size:14px;line-height:1.5}a{text-decoration:none;color:inherit}.sidebar{position:fixed;left:0;top:0;bottom:0;width:var(--sidebar-w);background:linear-gradient(180deg,var(--green-700),var(--green-900));color:#fff;padding:16px 0;z-index:100;overflow-y:auto}.sb-brand{display:flex;align-items:center;gap:10px;padding:0 18px 16px;border-bottom:1px solid rgba(255,255,255,.12)}.sb-logo{width:40px;height:40px;background:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;overflow:hidden}.sb-logo img{width:100%;height:100%;object-fit:contain}.sb-brand h1{font-size:15px;font-weight:900}.sb-brand small{font-size:10px;color:rgba(255,255,255,.72);display:block}.sb-nav-label{font-size:10px;color:rgba(255,255,255,.55);padding:14px 18px 6px;text-transform:uppercase;letter-spacing:1px;font-weight:800}.sb-item{display:flex;align-items:center;gap:12px;padding:11px 18px;color:rgba(255,255,255,.86);font-size:13px;font-weight:700;border-left:3px solid transparent;transition:.2s}.sb-item:hover,.sb-item.active{background:rgba(255,255,255,.12);color:#fff;border-left-color:#4ade80}.sb-item i{width:18px;text-align:center}.sb-item .badge{margin-left:auto;background:var(--red);font-size:10px;padding:2px 7px;border-radius:10px}.sb-footer{margin:16px;padding:16px;background:rgba(0,0,0,.2);border-radius:10px}.sb-footer h4{font-size:13px}.sb-footer p{font-size:11px;color:rgba(255,255,255,.78);margin:6px 0 12px}.btn-sb{display:inline-block;padding:8px 12px;border:1px solid white;border-radius:6px;color:white;font-size:11px;font-weight:800;margin-top:6px}.topbar{position:fixed;top:0;left:var(--sidebar-w);right:0;height:var(--topbar-h);background:#fff;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;padding:0 24px;z-index:99}.tb-left,.tb-right{display:flex;align-items:center;gap:14px}.tb-title{font-size:14px;font-weight:900;color:var(--green-700)}.tb-sub{font-size:11px;color:var(--muted)}.tb-icon{width:38px;height:38px;border-radius:8px;display:flex;align-items:center;justify-content:center;background:#fff;border:1px solid var(--border);color:var(--muted)}.tb-user{display:flex;align-items:center;gap:10px;padding:4px 12px 4px 4px;border:1px solid var(--border);border-radius:22px}.av{width:32px;height:32px;border-radius:50%;background:var(--green-600);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:900}.nm{font-weight:900}.st{font-size:11px;color:var(--green-600)}.topbar-badges{display:flex;gap:6px;flex-wrap:wrap}.main{margin-left:var(--sidebar-w);margin-top:var(--topbar-h);padding:22px;min-height:calc(100vh - var(--topbar-h))}.breadcrumb{display:flex;align-items:center;gap:6px;font-size:12px;color:var(--muted);margin-bottom:14px}.breadcrumb a{color:var(--green-600);font-weight:800}.pg-head{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:22px;gap:16px;flex-wrap:wrap}.pg-head h2{font-size:24px;font-weight:950;letter-spacing:-.3px}.pg-head .sub{font-size:13px;color:var(--muted);margin-top:4px}.card{background:var(--card);border-radius:12px;padding:20px;border:1px solid var(--border);box-shadow:0 1px 3px rgba(0,0,0,.04);overflow:hidden}.card-h{display:flex;justify-content:space-between;align-items:center;margin:-20px -20px 16px;padding:14px 16px;background:linear-gradient(135deg,#FFFBEA,#F2FBEF);border-bottom:1px solid #D8EADF;gap:10px}.card-h h3{font-size:15px;font-weight:950;color:#0F3D1B}.link{color:var(--green-700);font-size:12px;font-weight:900}.card-h .link{background:#FACC15;color:#173B12;border:1px solid #EAB308;border-radius:999px;padding:5px 10px}.grid{display:grid;gap:18px}.g2{grid-template-columns:repeat(2,1fr)}.g3{grid-template-columns:repeat(3,1fr)}.g4{grid-template-columns:repeat(4,1fr)}.g5{grid-template-columns:repeat(5,1fr)}.span2{grid-column:span 2}.span3{grid-column:span 3}.badge-pill{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:800}.bp-green{background:#D1FAE5;color:#059669}.bp-blue{background:#DBEAFE;color:var(--blue)}.bp-orange{background:#FEF3C7;color:#D97706}.bp-red{background:#FEE2E2;color:var(--red)}.bp-gray{background:#F3F4F6;color:var(--muted)}.bp-purple{background:#EDE9FE;color:var(--purple)}.bp-teal{background:#CCFBF1;color:var(--teal)}.btn,button{padding:9px 16px;border-radius:8px;border:0;cursor:pointer;font-size:13px;font-weight:900;display:inline-flex;align-items:center;gap:6px;font-family:inherit}.btn-p,button{background:var(--green-700);color:#fff}.btn-o{background:#fff;color:var(--green-700);border:1px solid var(--green-700)}.btn-s{padding:6px 12px;font-size:12px}.btn-full{width:100%;justify-content:center}button:disabled{opacity:.45;cursor:not-allowed}input,select,textarea{padding:10px 12px;border:1px solid var(--border);border-radius:7px;font-family:inherit;width:100%}label{display:block;font-weight:800;margin:10px 0 5px}table{width:100%;border-collapse:collapse}th{text-align:left;padding:10px 12px;background:#F9FAFB;font-size:11px;font-weight:900;color:var(--muted);border-bottom:1px solid var(--border);text-transform:uppercase}td{padding:12px;border-bottom:1px solid var(--border);font-size:13px}.prog{width:100%;height:7px;background:#E5E7EB;border-radius:4px;overflow:hidden}.prog-f{height:100%;background:var(--green-600);border-radius:4px}.course-card{border:1px solid var(--border);border-radius:10px;overflow:hidden;transition:.2s;background:#fff}.course-card:hover{box-shadow:0 4px 12px rgba(0,0,0,.08);transform:translateY(-2px)}.course-thumb{height:96px;background:linear-gradient(135deg,var(--green-700),var(--green-500));display:flex;align-items:center;justify-content:center;color:white;font-size:32px;position:relative}.course-thumb.cat-2{background:linear-gradient(135deg,var(--orange),#F59E0B)}.course-thumb.cat-3{background:linear-gradient(135deg,var(--blue),#60A5FA)}.course-thumb.cat-4{background:linear-gradient(135deg,var(--purple),#A78BFA)}.new-tag{position:absolute;top:8px;left:8px;background:#FACC15;color:#000;font-size:9px;padding:2px 6px;border-radius:4px;font-weight:900}.course-body{padding:12px}.course-title{font-size:13px;font-weight:950;margin-bottom:5px;line-height:1.3;min-height:34px}.course-meta{font-size:11px;color:var(--muted);margin-bottom:8px}.course-footer{display:flex;justify-content:space-between;align-items:center;font-size:11px;gap:6px}.course-price{font-weight:950;color:var(--green-700)}.course-rating{color:var(--orange);font-weight:800}.nav-card{padding:14px;background:#F9FAFB;border-radius:10px;display:flex;align-items:center;gap:12px;border:1px solid var(--border);transition:.2s}.nav-card:hover{background:var(--green-50);border-color:var(--green-600);transform:translateY(-2px)}.nav-ic{width:40px;height:40px;border-radius:8px;background:var(--green-100);color:var(--green-700);display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}.nav-info{flex:1}.nav-title{font-size:13px;font-weight:950}.nav-desc{font-size:11px;color:var(--muted)}.lesson-player{aspect-ratio:16/9;background:linear-gradient(135deg,#1B5E20,#0d3310);border-radius:10px;position:relative;display:flex;align-items:center;justify-content:center;color:white;overflow:hidden}.play-btn{width:64px;height:64px;background:rgba(255,255,255,.95);color:var(--green-700);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:24px}.quiz-option{display:flex;align-items:center;gap:10px;padding:12px 14px;border:2px solid var(--border);border-radius:8px;margin-bottom:8px;font-size:13px}.quiz-option:hover{border-color:var(--green-600);background:#F9FAFB}.notice{padding:12px 14px;border-radius:9px;margin-bottom:16px;font-weight:800}.notice.ok{background:#D1FAE5;color:#065F46}.notice.err{background:#FEE2E2;color:#991B1B}.journey{background:#fff;border:1px solid var(--border);border-radius:12px;padding:22px;margin-top:24px}.journey-head{font-size:16px;font-weight:950;margin-bottom:18px;color:var(--green-700)}.journey-steps{display:flex;gap:12px;overflow-x:auto;padding-bottom:8px}.j-step{flex:1;min-width:120px;text-align:center}.j-ic{width:42px;height:42px;border-radius:50%;margin:0 auto 8px;display:flex;align-items:center;justify-content:center;font-weight:900;background:#fff;border:2px solid var(--border);color:var(--muted)}.j-step.done .j-ic{background:var(--green-600);border-color:var(--green-600);color:#fff}.j-step.cur .j-ic{border-color:var(--green-600);color:var(--green-600)}.j-step h5{font-size:11px;font-weight:900}.j-step p{font-size:10px;color:var(--muted)}.footer{margin-top:30px;padding:14px 20px;background:var(--green-900);color:#fff;border-radius:10px;display:flex;justify-content:space-between;align-items:center;font-size:12px;flex-wrap:wrap;gap:12px}.menu-btn{display:none}.sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:98}.muted{color:var(--muted)}.actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}.info-row{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border);font-size:13px}.info-row:last-child{border-bottom:0}.empty{border:1px dashed var(--border);border-radius:10px;padding:18px;background:#fff;color:var(--muted)}@media(max-width:1200px){.g4,.g5{grid-template-columns:repeat(2,1fr)}.topbar-badges{display:none}}@media(max-width:1024px){.sidebar{transform:translateX(-100%)}.sidebar.active{transform:translateX(0)}.sidebar-overlay.active{display:block}.topbar{left:0}.main{margin-left:0}.menu-btn{display:flex}.g3,.g4,.g5{grid-template-columns:repeat(2,1fr)}}@media(max-width:768px){.g2,.g3,.g4,.g5{grid-template-columns:1fr}.span2,.span3{grid-column:span 1}.main{padding:16px}.tb-user .nm,.tb-user .st{display:none}.footer{display:grid;text-align:center}}
.pagination{display:flex;gap:8px;flex-wrap:wrap;margin-top:16px}.pagination a{border:1px solid var(--border);background:#fff;color:var(--green-700);border-radius:8px;padding:7px 11px;font-weight:950;font-size:12px}.pagination a.active{background:var(--green-700);border-color:var(--green-700);color:#fff}
.lesson-frame{width:100%;min-height:420px;border:1px solid var(--border);border-radius:10px;background:#fff}.lesson-list{display:grid;gap:10px}.lesson-row{display:grid;grid-template-columns:1fr auto;gap:10px;align-items:center;border:1px solid var(--border);border-radius:8px;padding:12px;background:#fff}.lesson-row.active{border-color:var(--green-600);background:#F1FAF5}.learning-focus{margin-bottom:18px}.learning-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}
.course-accordion{display:grid;gap:12px}.course-progress-item{border:1px solid var(--border);border-radius:10px;background:#fff;overflow:hidden}.course-progress-item[open]{border-color:#A7DCC0;box-shadow:0 6px 18px rgba(15,61,27,.06)}.course-progress-summary{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:12px;align-items:center;padding:14px 16px;cursor:pointer;list-style:none}.course-progress-summary::-webkit-details-marker{display:none}.course-progress-summary h3{font-size:14px;margin:0;color:#0F3D1B}.course-progress-summary .muted{font-size:12px}.course-progress-meta{display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end}.course-progress-body{border-top:1px solid var(--border);padding:14px 16px;background:#FBFDFB}.progress-report{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:10px;margin:12px 0}.progress-report div{border:1px solid var(--border);border-radius:8px;background:#fff;padding:10px}.progress-report strong{display:block;color:var(--green-700);font-size:16px}.progress-report span{display:block;color:var(--muted);font-size:11px;font-weight:800;margin-top:2px}.progress-percent{font-weight:950;color:var(--green-700);font-size:18px}.collapse-caret{color:var(--green-700);font-size:12px;font-weight:950}@media(max-width:900px){.course-progress-summary{grid-template-columns:1fr}.course-progress-meta{justify-content:flex-start}.progress-report{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:520px){.progress-report{grid-template-columns:1fr}}
.completed-course-list{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.completed-course{border:1px solid var(--border);border-radius:10px;background:#fff;padding:14px}.completed-course h3{font-size:14px;margin:0 0 8px;color:#0F3D1B}.completed-meta{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;margin:10px 0}.completed-meta div{border:1px solid var(--border);border-radius:8px;background:#FBFDFB;padding:9px}.completed-meta strong{display:block;color:var(--green-700);font-size:15px}.completed-meta span{display:block;color:var(--muted);font-size:11px;font-weight:800}.completed-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}@media(max-width:1100px){.completed-course-list{grid-template-columns:1fr 1fr}}@media(max-width:720px){.completed-course-list{grid-template-columns:1fr}}
.exam-shell{display:grid;gap:18px;max-width:1120px}.exam-hero{background:#fff;border:1px solid var(--border);border-radius:14px;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,.04)}.exam-hero-top{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap}.exam-hero h3{font-size:24px;line-height:1.2;color:#0F3D1B;margin:0 0 6px}.exam-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-top:18px}.exam-stat{border:1px solid #D8EADF;border-radius:10px;background:#FBFDFB;padding:12px}.exam-stat strong{display:block;font-size:20px;color:var(--green-700);line-height:1}.exam-stat span{display:block;margin-top:5px;color:var(--muted);font-size:11px;font-weight:900;text-transform:uppercase}.exam-form{display:grid;gap:14px}.exam-instructions{border:1px solid #D8EADF;border-left:5px solid var(--green-700);border-radius:10px;background:#F6FFF7;padding:14px 16px;color:#334155;font-weight:700}.exam-question{background:#fff;border:1px solid var(--border);border-radius:14px;padding:18px;box-shadow:0 1px 3px rgba(0,0,0,.04)}.exam-question-head{display:grid;grid-template-columns:auto minmax(0,1fr);gap:14px;align-items:flex-start;margin-bottom:14px}.exam-number{width:42px;height:42px;border-radius:50%;background:var(--green-700);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:950;font-size:18px;flex:0 0 42px}.exam-question small{display:block;color:var(--muted);font-size:11px;font-weight:900;text-transform:uppercase;margin-bottom:3px}.exam-question h3{font-size:17px;line-height:1.45;color:#0F172A;margin:0}.exam-options{display:grid;gap:10px}.exam-options .quiz-option{margin:0;min-height:54px;cursor:pointer;align-items:flex-start;background:#fff}.exam-options .quiz-option:hover{background:#F6FFF7;box-shadow:0 8px 22px rgba(21,82,26,.08)}.quiz-option input[type="radio"]{width:18px;min-width:18px;height:18px;margin-top:2px;accent-color:var(--green-700)}.option-letter{width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;background:#E8F5E9;color:var(--green-700);font-weight:950;flex:0 0 28px}.option-text{font-weight:800;line-height:1.45;color:#1F2937}.exam-submit{display:flex;gap:10px;justify-content:flex-end;align-items:center;position:sticky;bottom:0;background:linear-gradient(180deg,rgba(245,247,250,.78),#F5F7FA);padding:14px 0 2px;z-index:5}.exam-submit button{min-height:44px}.exam-empty{max-width:760px}@media(max-width:900px){.exam-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.exam-submit{position:static;justify-content:flex-start;flex-wrap:wrap}.exam-hero h3{font-size:21px}}@media(max-width:560px){.exam-stats{grid-template-columns:1fr}.exam-question{padding:14px}.exam-question-head{grid-template-columns:1fr}.exam-number{width:36px;height:36px;font-size:16px}.exam-options .quiz-option{padding:11px}.option-letter{width:26px;height:26px;flex-basis:26px}}
.support-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-bottom:18px}.support-stat{border:1px solid var(--border);border-radius:10px;background:#fff;padding:14px}.support-stat strong{display:block;color:var(--green-700);font-size:24px}.support-stat span{display:block;color:var(--muted);font-size:11px;font-weight:900;text-transform:uppercase}.support-ticket-list{display:grid;gap:9px}.support-ticket{display:block;border:1px solid var(--border);border-radius:10px;background:#fff;padding:12px}.support-ticket.active{border-color:var(--green-600);background:var(--green-50)}.support-ticket strong{display:block;color:#0F3D1B}.support-ticket small{display:block;color:var(--muted);margin:3px 0 7px}.support-chat{display:grid;gap:10px;margin:12px 0}.support-message{border:1px solid var(--border);border-radius:10px;background:#FBFDFB;padding:12px}.support-message.agent,.support-message.support_agent,.support-message.admin{background:#EFF6FF}.support-message strong{display:block;color:#0F3D1B}.support-message small{display:block;color:var(--muted);margin-top:4px}.help-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}.help-card{border:1px solid var(--border);border-radius:10px;background:#fff;padding:13px}.help-card i{color:var(--green-700);font-size:18px;margin-bottom:8px}.help-card strong{display:block;color:#0F3D1B}.help-card span{display:block;color:var(--muted);font-size:12px;margin-top:4px}@media(max-width:900px){.support-stats,.help-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:620px){.support-stats,.help-grid{grid-template-columns:1fr}}
.topbar{height:64px;padding:0 18px}.tb-left{min-width:0;flex:1}.tb-left>div{min-width:0}.tb-title{font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:420px}.tb-sub{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:420px}.tb-right{gap:8px;flex-shrink:0}.topbar-badges{display:flex;gap:6px;flex-wrap:nowrap}.topbar-badges .badge-pill{height:28px;padding:3px 9px;white-space:nowrap}.tb-icon{width:34px;height:34px;flex:0 0 34px}.tb-user{position:relative;height:40px;min-width:0;max-width:178px;padding:4px 9px 4px 4px;border-radius:999px;cursor:pointer;background:#fff}.tb-user .nm{max-width:86px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.dropdown-menu{display:none;position:absolute;right:0;top:calc(100% + 8px);min-width:210px;background:#fff;border:1px solid var(--border);border-radius:10px;box-shadow:0 18px 42px rgba(16,24,40,.16);padding:8px;z-index:150}.dropdown:hover .dropdown-menu,.dropdown:focus-within .dropdown-menu{display:grid;gap:4px}.dropdown-menu a{display:block;padding:9px 10px;border-radius:8px;color:var(--text);font-weight:850}.dropdown-menu a:hover{background:var(--green-50);color:var(--green-700)}.dropdown-divider{height:1px;background:var(--border);margin:4px 0}@media(max-width:1180px){.topbar-badges .badge-pill:nth-child(2){display:none}.tb-title,.tb-sub{max-width:320px}}@media(max-width:860px){.topbar-badges{display:none}.tb-title,.tb-sub{max-width:calc(100vw - 270px)}.tb-user{max-width:116px}.tb-user .nm,.tb-user .st{display:none}}@media(max-width:520px){.topbar{padding:0 10px}.tb-icon{width:32px;height:32px;flex-basis:32px}.tb-title{max-width:150px}}
</style>
</head>
<body>
<div class="sidebar-overlay" id="overlay"></div>
<aside class="sidebar" id="sidebar">
  <a class="sb-brand" href="dashboard.php?screen=catalog">
    <div class="sb-logo"><img src="<?= e($logo) ?>" alt="NATCODEV"></div>
    <div><h1>NATCODEV</h1><small>Academy</small></div>
  </a>
  <div class="sb-nav-label">Academy</div>
  <nav>
    <?php foreach ([
      'catalog' => ['fas fa-compass', 'Catalog'],
      'learning' => ['fas fa-book-open', 'My Learning'],
      'lesson' => ['fas fa-play-circle', 'Lesson Player'],
      'quiz' => ['fas fa-question-circle', 'Quiz / Exam'],
      'certificates' => ['fas fa-award', 'Certificates'],
      'transactions' => ['fas fa-exchange-alt', 'Transactions'],
      'messages' => ['fas fa-envelope', 'Messages'],
      'settings' => ['fas fa-cog', 'Settings & Feedback'],
      'support' => ['fas fa-headset', 'Help & Support'],
    ] as $key => $item): ?>
      <a class="sb-item <?= $screen === $key ? 'active' : '' ?>" href="dashboard.php?screen=<?= e($key) ?>"><i class="<?= e($item[0]) ?>"></i><span><?= e($item[1]) ?></span><?= $key === 'messages' && count($messages) > 0 ? '<span class="badge">' . count($messages) . '</span>' : '' ?></a>
    <?php endforeach; ?>
    <a class="sb-item <?= $screen === 'transactions' ? 'active' : '' ?>" href="dashboard.php?screen=transactions"><i class="fas fa-wallet"></i><span>Fund Wallet</span></a>
    <a class="sb-item" href="../academy/index.php"><i class="fas fa-home"></i><span>Academy Site</span></a>
 
     <a class="sb-item" href="../academy/request-role.php"><i class="fas fa-user-shield"></i><span>Request Role</span></a>
    <a class="sb-item" href="logout.php"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
  </nav>
  <div class="sb-footer">
    <h4>Learn. Grow. Certify.</h4>
    <p>Practical skills. Real farm impact.</p>
    <a class="btn-sb" href="dashboard.php?screen=catalog">Browse Catalog <i class="fas fa-arrow-right"></i></a>
    <a class="btn-sb" href="logout.php">Logout</a>
  </div>
</aside>
<header class="topbar">
  <div class="tb-left">
    <button class="tb-icon menu-btn" id="menuBtn"><i class="fas fa-bars"></i></button>
    <div>
        <div class="tb-title">NATCODEV Academy - <?= e($titleSet[0]) ?></div>
        <div class="tb-sub"><?= e($titleSet[1]) ?></div>
    </div>
  </div>
  <div class="tb-right">
    <div class="topbar-badges"><span class="badge-pill bp-green"><i class="fas fa-book-open"></i> <?= count($registered) ?></span><span class="badge-pill bp-teal"><i class="fas fa-chart-line"></i> <?= $avgProgress ?>%</span><span class="badge-pill bp-blue"><i class="fas fa-wallet"></i> <?= e(ac_money($walletBalance)) ?></span></div>
    <a class="tb-icon" href="dashboard.php?screen=support" title="Help & Support"><i class="far fa-question-circle"></i></a>
    <a class="tb-icon" href="dashboard.php?screen=messages" title="Messages"><i class="far fa-bell"></i></a>
    <div class="tb-user dropdown">
        <div class="av"><?= e($initials) ?></div>
        <div>
            <div class="nm"><?= e((string) ($user['name'] ?? 'User')) ?></div>
            <div class="st"><?= e(academy_role_label($role)) ?></div>
        </div>
        <div class="dropdown-menu">
            <a href="dashboard.php?screen=settings">Profile & Settings</a>
            <a href="dashboard.php?screen=support">Help & Support</a>
            <div class="dropdown-divider"></div>
            <a href="logout.php" style="color:var(--red);">Logout</a>
        </div>
    </div>
  </div>
</header>
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


  <section class="card" style="margin-top:24px">
    <div class="card-h"><h3>Recently Completed Courses</h3><a class="link" href="dashboard.php?screen=certificates">Certificates</a></div>
    <?php if ($recentCompletedCourses): ?>
      <div class="completed-course-list">
        <?php foreach ($recentCompletedCourses as $done): ?>
          <?php
            $score = $done['best_score'] !== null ? number_format((float) $done['best_score'], 0) . '%' : '--';
            $certStatus = (string) ($done['certificate_record_status'] ?: $done['certificate_status']);
            $certStatus = $certStatus !== '' ? $certStatus : 'not_required';
            $certIssued = (string) ($done['certificate_record_status'] ?? '') === 'issued' && !empty($done['certificate_ref']);
          ?>
          <article class="completed-course">
            <h3><?= e((string) $done['course_title']) ?></h3>
            <div class="completed-meta">
              <div><strong><?= e($score) ?></strong><span>Best Score</span></div>
              <div><strong><?= (int) ($done['progress_percent'] ?? 0) ?>%</strong><span>Progress</span></div>
              <div><strong><?= e(!empty($done['completed_at']) ? date('M j, Y', strtotime((string) $done['completed_at'])) : 'Completed') ?></strong><span>Completed</span></div>
              <div><strong><?= e(ac_status($certStatus)) ?></strong><span>Certificate</span></div>
            </div>
            <div class="completed-actions">
              <?= ac_badge('completed', 'Completed') ?>
              <?= ac_badge($certStatus, 'Certificate: ' . ac_status($certStatus)) ?>
              <?php if ($certIssued): ?>
                <a class="btn btn-o btn-s" href="../dashboard/download-academy-certificate.php?ref=<?= urlencode((string) $done['certificate_ref']) ?>">Download Certificate</a>
              <?php elseif ($certStatus === 'eligible' || $certStatus === 'pending'): ?>
                <a class="btn btn-o btn-s" href="dashboard.php?screen=certificates">Open Certificates</a>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="empty">Complete a course and pass its assessment to see scores and certificate downloads here.</div>
    <?php endif; ?>
  </section>

  <section class="journey">
    <div class="journey-head">My Learning Journey</div>
    <div class="journey-steps">
      <?php foreach ($journeySteps as $idx => $step): ?>
        <a class="j-step <?= $step['status'] === 'completed' ? 'done' : ($step['status'] === 'current' ? 'cur' : '') ?>" href="dashboard.php?screen=<?= e((string) $step['screen']) ?><?= $courseId ? '&course_id=' . $courseId : '' ?>">
          <div class="j-ic"><?= $idx + 1 ?></div>
          <h5><?= e((string) $step['label']) ?></h5>
          <p><?= e((string) $step['note']) ?></p>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
  <footer class="footer"><div><i class="fas fa-shield-alt"></i> Secure / Transparent / Traceable</div><div>Empowering Growers. Building Sustainable Coconut Communities.</div><div>NATCODEV Academy</div></footer>
</main>
<script>
(function(){
  const menuBtn=document.getElementById('menuBtn'), sidebar=document.getElementById('sidebar'), overlay=document.getElementById('overlay');
  if(menuBtn){menuBtn.addEventListener('click',()=>{sidebar.classList.toggle('active');overlay.classList.toggle('active');});}
  if(overlay){overlay.addEventListener('click',()=>{sidebar.classList.remove('active');overlay.classList.remove('active');});}
  document.addEventListener('keydown',e=>{if(e.key==='Escape'){sidebar.classList.remove('active');overlay.classList.remove('active');}});
  const fundForm=document.getElementById('academy-fund-wallet'), fundResult=document.getElementById('academy-wallet-result');
  document.querySelectorAll('[data-withdrawal-form]').forEach(form=>{
    const provider=form.querySelector('[data-provider]'), bankSelect=form.querySelector('[data-bank-select]'), bankName=form.querySelector('[data-bank-name]'), bankCode=form.querySelector('[data-bank-code]'), accountNumber=form.querySelector('[data-account-number]'), accountName=form.querySelector('[data-account-name]'), status=form.querySelector('[data-resolve-status]'), submit=form.querySelector('[data-submit-withdrawal]');
    let timer=null;
    function setStatus(message,ok=true){status.className='notice '+(ok?'ok':'error'); status.textContent=message;}
    function reset(){accountName.value=''; submit.disabled=true; setStatus('Verify bank account before withdrawal.');}
    async function loadBanks(){reset(); bankSelect.innerHTML='<option value="">Loading banks...</option>'; try{const response=await fetch(form.dataset.bankUrl+'?provider='+encodeURIComponent(provider.value),{credentials:'same-origin'}); const payload=await response.json(); if(!response.ok||!payload.success) throw new Error(payload.error||'Unable to load banks.'); bankSelect.innerHTML='<option value="">Select receiving bank</option>'+payload.banks.map(bank=>'<option value="'+String(bank.code).replace(/"/g,'&quot;')+'" data-name="'+String(bank.name).replace(/"/g,'&quot;')+'">'+bank.name+'</option>').join('');}catch(error){bankSelect.innerHTML='<option value="">Bank lookup unavailable</option>'; setStatus(error.message||'Bank lookup unavailable.',false);}}
    async function resolve(){const option=bankSelect.options[bankSelect.selectedIndex]; bankCode.value=bankSelect.value||''; bankName.value=option?(option.dataset.name||''):''; accountName.value=''; submit.disabled=true; const digits=accountNumber.value.replace(/\D/g,'').slice(0,10); accountNumber.value=digits; if(!bankCode.value||digits.length!==10) return reset(); setStatus('Verifying account name...'); const data=new FormData(); data.append('_csrf',form.querySelector('[name="_csrf"]').value); data.append('provider',provider.value); data.append('bank_code',bankCode.value); data.append('account_number',digits); try{const response=await fetch(form.dataset.resolveUrl,{method:'POST',body:data,credentials:'same-origin'}); const payload=await response.json(); if(!response.ok||!payload.success||!payload.account_name) throw new Error(payload.error||'Account could not be verified.'); accountName.value=payload.account_name; submit.disabled=false; setStatus('Verified: '+payload.account_name);}catch(error){setStatus(error.message||'Account could not be verified.',false);}}
    provider.addEventListener('change',loadBanks); bankSelect.addEventListener('change',resolve); accountNumber.addEventListener('input',()=>{clearTimeout(timer); reset(); timer=setTimeout(resolve,450);}); form.addEventListener('submit',event=>{if(submit.disabled||!accountName.value){event.preventDefault(); setStatus('Verify the bank account before submitting withdrawal.',false);}}); loadBanks();
  });
  if(fundForm&&fundResult){
    fundForm.addEventListener('submit',async function(event){
      event.preventDefault();
      fundResult.style.display='block';
      fundResult.className='notice ok';
      fundResult.textContent='Initializing wallet funding...';
      try{
        const response=await fetch('../api/fund-wallet.php',{method:'POST',body:new FormData(fundForm),credentials:'same-origin'});
        const data=await response.json();
        if(!data.success){throw new Error(data.error||'Unable to initialize payment.');}
        const url=data.checkout_url||data.payment_url||data.authorization_url||'';
        if(url){
          fundResult.textContent='Funding initialized. Redirecting to payment...';
          window.location.href=url;
        }else{
          fundResult.textContent='Funding initialized. Follow the returned Monnify payment instructions.';
        }
      }catch(error){
        fundResult.className='notice err';
        fundResult.textContent=error.message||'Unable to initialize wallet funding.';
      }
    });
  }
})();
</script>
</body>
</html>
