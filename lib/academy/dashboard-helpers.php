<?php
declare(strict_types=1);

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
