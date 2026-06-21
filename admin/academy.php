<?php
declare(strict_types=1);

if (!defined('NATCODEV_ACADEMY_LEGACY')) {
    $_GET['page'] = preg_replace('/[^a-z_]/', '', (string) ($_GET['page'] ?? 'dashboard')) ?: 'dashboard';
    require __DIR__ . '/acad/academy-design.php';
    return;
}

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../lib/admin-layout.php';
require_once __DIR__ . '/../lib/academy.php';

$pdo = db();
admin_ensure_schema($pdo);
academy_ensure_schema($pdo);
admin_require($pdo);

$message = '';
$error = '';
$tab = preg_replace('/[^a-z_]/', '', (string) ($_GET['tab'] ?? 'overview')) ?: 'overview';
$allowedTabs = ['overview', 'programs', 'courses', 'certificate_groups', 'lessons', 'assessments', 'calendar', 'instructors', 'attendance', 'reminders', 'feedback', 'enrollments', 'certificates', 'refunds', 'reports'];
if (!in_array($tab, $allowedTabs, true)) {
    $tab = 'overview';
}

function academy_admin_redirect(string $tab, string $key, string $message): void
{
    $pages = [
        'overview' => 'academy.php',
        'programs' => 'academy-programs.php',
        'courses' => 'academy-courses.php',
        'certificate_groups' => 'academy-certificate-pathways.php',
        'lessons' => 'academy-lessons.php',
        'assessments' => 'academy-assessments.php',
        'calendar' => 'academy-cohorts.php',
        'instructors' => 'academy-instructors.php',
        'attendance' => 'academy-attendance.php',
        'reminders' => 'academy-reminders.php',
        'feedback' => 'academy-feedback.php',
        'enrollments' => 'academy-learners.php',
        'certificates' => 'academy-certificates.php',
        'refunds' => 'academy-refunds.php',
        'reports' => 'academy-reports.php',
    ];
    redirect_to(($pages[$tab] ?? 'academy.php') . '?' . http_build_query([$key => $message]));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Invalid security token.';
    } else {
        try {
            $action = (string) ($_POST['action'] ?? '');
            if ($action === 'save_program') {
                $programId = (int) ($_POST['program_id'] ?? 0);
                $title = trim((string) ($_POST['title'] ?? ''));
                $description = trim((string) ($_POST['description'] ?? ''));
                $audienceRoles = implode(',', array_values(array_filter(array_map('trim', (array) ($_POST['audience_roles'] ?? [])))));
                $status = in_array((string) ($_POST['status'] ?? 'active'), ['active', 'draft', 'paused', 'archived'], true) ? (string) $_POST['status'] : 'active';
                $sort = (int) ($_POST['sort_order'] ?? 0);
                if ($title === '') {
                    throw new RuntimeException('Program title is required.');
                }
                if ($programId > 0) {
                    $stmt = $pdo->prepare("UPDATE academy_programs SET title = ?, description = ?, audience_roles = ?, status = ?, sort_order = ? WHERE id = ?");
                    $stmt->execute([$title, $description, $audienceRoles, $status, $sort, $programId]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO academy_programs (title, description, audience_roles, status, sort_order) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$title, $description, $audienceRoles, $status, $sort]);
                }
                academy_admin_redirect('programs', 'message', 'Academy program saved.');
            }

            if ($action === 'save_course') {
                $courseId = (int) ($_POST['course_id'] ?? 0);
                $title = trim((string) ($_POST['title'] ?? ''));
                $description = trim((string) ($_POST['description'] ?? ''));
                $programId = (int) ($_POST['program_id'] ?? 0) ?: null;
                $targetRoles = implode(',', array_values(array_filter(array_map('trim', (array) ($_POST['target_roles'] ?? [])))));
                $courseType = in_array((string) ($_POST['course_type'] ?? 'course'), ['course', 'webinar', 'workshop', 'certification', 'orientation'], true) ? (string) $_POST['course_type'] : 'course';
                $deliveryType = array_key_exists((string) ($_POST['delivery_type'] ?? ''), academy_delivery_types()) ? (string) $_POST['delivery_type'] : 'lms';
                $status = in_array((string) ($_POST['status'] ?? 'active'), ['active', 'draft', 'paused', 'archived'], true) ? (string) $_POST['status'] : 'active';
                $startTime = strtotime((string) ($_POST['start_time'] ?? ''));
                $duration = max(15, min(720, (int) ($_POST['duration_minutes'] ?? 60)));
                $isFree = isset($_POST['is_free']) ? 1 : 0;
                $price = $isFree ? 0 : max(0, (float) ($_POST['price'] ?? 0));
                $certificateRequired = isset($_POST['certification_required']) ? 1 : 0;
                if ($title === '' || $description === '') {
                    throw new RuntimeException('Course title and description are required.');
                }
                if ($startTime === false) {
                    throw new RuntimeException('Provide a valid start date/time.');
                }
                if (!$isFree && $price <= 0) {
                    throw new RuntimeException('Paid courses require a price.');
                }
                $params = [
                    $programId,
                    trim((string) ($_POST['course_code'] ?? '')),
                    $courseType,
                    $title,
                    $description,
                    date('Y-m-d H:i:s', $startTime),
                    $duration,
                    $isFree,
                    $price,
                    $deliveryType,
                    trim((string) ($_POST['delivery_url'] ?? '')) ?: null,
                    trim((string) ($_POST['delivery_instructions'] ?? '')) ?: null,
                    max(1, (int) ($_POST['max_attendees'] ?? 100)),
                    trim((string) ($_POST['category'] ?? 'Academy')),
                    $targetRoles,
                    $certificateRequired,
                    trim((string) ($_POST['prerequisites'] ?? '')) ?: null,
                    max(0, min(100, (float) ($_POST['pass_score'] ?? 70))),
                    isset($_POST['certificate_approval_required']) ? 1 : 0,
                    trim((string) ($_POST['instructor_name'] ?? '')) ?: null,
                    $status,
                ];
                if ($courseId > 0) {
                    $stmt = $pdo->prepare("
                        UPDATE webinars
                        SET program_id = ?, course_code = ?, course_type = ?, title = ?, description = ?, start_time = ?, duration_minutes = ?,
                            is_free = ?, price = ?, delivery_type = ?, delivery_url = ?, zoom_link = ?, delivery_instructions = ?,
                            max_attendees = ?, category = ?, target_roles = ?, certification_required = ?, prerequisites = ?,
                            pass_score = ?, certificate_approval_required = ?, instructor_name = ?, status = ?
                        WHERE id = ?
                    ");
                    $stmt->execute(array_merge(array_slice($params, 0, 11), [$params[10]], array_slice($params, 11), [$courseId]));
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO webinars
                            (program_id, course_code, course_type, title, description, start_time, duration_minutes, is_free, price,
                             delivery_type, delivery_url, zoom_link, delivery_instructions, max_attendees, category, target_roles,
                             certification_required, prerequisites, pass_score, certificate_approval_required, instructor_name, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute(array_merge(array_slice($params, 0, 11), [$params[10]], array_slice($params, 11)));
                }
                academy_admin_redirect('courses', 'message', 'Academy course saved.');
            }

            if ($action === 'save_certificate_group') {
                $title = trim((string) ($_POST['title'] ?? ''));
                $description = trim((string) ($_POST['description'] ?? ''));
                $audienceRoles = implode(',', array_values(array_filter(array_map('trim', (array) ($_POST['audience_roles'] ?? [])))));
                $courseIds = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['course_ids'] ?? [])))));
                $status = in_array((string) ($_POST['status'] ?? 'active'), ['active', 'draft', 'paused', 'archived'], true) ? (string) $_POST['status'] : 'active';
                if ($title === '' || $description === '') {
                    throw new RuntimeException('Certificate group title and description are required.');
                }
                if (!$courseIds) {
                    throw new RuntimeException('Select at least one course for this grouped certificate.');
                }
                $stmt = $pdo->prepare("
                    INSERT INTO academy_certificate_groups
                        (title, description, audience_roles, certificate_approval_required, status, sort_order)
                    VALUES (?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        description = VALUES(description),
                        audience_roles = VALUES(audience_roles),
                        certificate_approval_required = VALUES(certificate_approval_required),
                        status = VALUES(status),
                        sort_order = VALUES(sort_order)
                ");
                $stmt->execute([
                    $title,
                    $description,
                    $audienceRoles,
                    isset($_POST['certificate_approval_required']) ? 1 : 0,
                    $status,
                    (int) ($_POST['sort_order'] ?? 0),
                ]);
                $groupId = (int) $pdo->lastInsertId();
                if ($groupId <= 0) {
                    $lookup = $pdo->prepare("SELECT id FROM academy_certificate_groups WHERE title = ? LIMIT 1");
                    $lookup->execute([$title]);
                    $groupId = (int) $lookup->fetchColumn();
                }
                $pdo->prepare("DELETE FROM academy_certificate_group_courses WHERE group_id = ?")->execute([$groupId]);
                $insert = $pdo->prepare("INSERT INTO academy_certificate_group_courses (group_id, webinar_id, is_required, sort_order) VALUES (?, ?, 1, ?)");
                foreach ($courseIds as $index => $courseId) {
                    $insert->execute([$groupId, $courseId, ($index + 1) * 10]);
                }
                academy_admin_redirect('certificate_groups', 'message', 'Certificate group saved.');
            }

            if ($action === 'save_lesson') {
                $courseId = (int) ($_POST['webinar_id'] ?? 0);
                $title = trim((string) ($_POST['title'] ?? ''));
                if ($courseId <= 0 || $title === '') {
                    throw new RuntimeException('Course and lesson title are required.');
                }
                $stmt = $pdo->prepare("
                    INSERT INTO academy_lessons
                        (webinar_id, title, summary, content, delivery_type, material_url, duration_minutes, sort_order, is_required, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $courseId,
                    $title,
                    trim((string) ($_POST['summary'] ?? '')),
                    trim((string) ($_POST['content'] ?? '')),
                    array_key_exists((string) ($_POST['delivery_type'] ?? ''), academy_delivery_types()) ? (string) $_POST['delivery_type'] : 'document',
                    trim((string) ($_POST['material_url'] ?? '')) ?: null,
                    max(1, (int) ($_POST['duration_minutes'] ?? 20)),
                    (int) ($_POST['sort_order'] ?? 0),
                    isset($_POST['is_required']) ? 1 : 0,
                    in_array((string) ($_POST['status'] ?? 'active'), ['active', 'draft', 'paused', 'archived'], true) ? (string) $_POST['status'] : 'active',
                ]);
                academy_admin_redirect('lessons', 'message', 'Lesson/material saved.');
            }

            if ($action === 'save_assessment') {
                $courseId = (int) ($_POST['webinar_id'] ?? 0);
                $title = trim((string) ($_POST['title'] ?? ''));
                if ($courseId <= 0 || $title === '') {
                    throw new RuntimeException('Course and assessment title are required.');
                }
                $stmt = $pdo->prepare("INSERT INTO academy_assessments (webinar_id, title, instructions, pass_score, max_attempts, status) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $courseId,
                    $title,
                    trim((string) ($_POST['instructions'] ?? '')),
                    max(0, min(100, (float) ($_POST['pass_score'] ?? 70))),
                    max(1, (int) ($_POST['max_attempts'] ?? 3)),
                    in_array((string) ($_POST['status'] ?? 'active'), ['active', 'draft', 'paused', 'archived'], true) ? (string) $_POST['status'] : 'active',
                ]);
                academy_admin_redirect('assessments', 'message', 'Assessment saved.');
            }

            if ($action === 'save_question') {
                $assessmentId = (int) ($_POST['assessment_id'] ?? 0);
                $question = trim((string) ($_POST['question_text'] ?? ''));
                if ($assessmentId <= 0 || $question === '') {
                    throw new RuntimeException('Assessment and question are required.');
                }
                $stmt = $pdo->prepare("
                    INSERT INTO academy_questions
                        (assessment_id, question_text, option_a, option_b, option_c, option_d, correct_option, points, sort_order)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $assessmentId,
                    $question,
                    trim((string) ($_POST['option_a'] ?? '')),
                    trim((string) ($_POST['option_b'] ?? '')),
                    trim((string) ($_POST['option_c'] ?? '')),
                    trim((string) ($_POST['option_d'] ?? '')),
                    in_array((string) ($_POST['correct_option'] ?? 'A'), ['A', 'B', 'C', 'D'], true) ? (string) $_POST['correct_option'] : 'A',
                    max(1, (float) ($_POST['points'] ?? 1)),
                    (int) ($_POST['sort_order'] ?? 0),
                ]);
                academy_admin_redirect('assessments', 'message', 'Assessment question saved.');
            }

            if ($action === 'review_certificate') {
                $certificateId = (int) ($_POST['certificate_id'] ?? 0);
                $certificateKind = (string) ($_POST['certificate_kind'] ?? 'course');
                $status = in_array((string) ($_POST['status'] ?? 'pending'), ['pending', 'issued', 'rejected'], true) ? (string) $_POST['status'] : 'pending';
                $table = $certificateKind === 'group' ? 'academy_group_certificates' : 'academy_certificates';
                $currentStmt = $pdo->prepare("SELECT status FROM {$table} WHERE id = ? LIMIT 1");
                $currentStmt->execute([$certificateId]);
                $currentStatus = (string) ($currentStmt->fetchColumn() ?: '');
                if ($currentStatus === 'issued') {
                    throw new RuntimeException('Issued Academy certificates are permanent and cannot be changed.');
                }
                $stmt = $pdo->prepare("UPDATE {$table} SET status = ?, notes = ?, approved_by = ?, issued_at = IF(? = 'issued', COALESCE(issued_at, NOW()), issued_at) WHERE id = ?");
                $stmt->execute([$status, trim((string) ($_POST['notes'] ?? '')), (int) ($_SESSION['user_id'] ?? 0) ?: null, $status, $certificateId]);
                academy_admin_redirect('certificates', 'message', 'Certificate review saved.');
            }

            if ($action === 'review_refund') {
                $refundId = (int) ($_POST['refund_id'] ?? 0);
                $status = in_array((string) ($_POST['status'] ?? 'pending'), ['pending', 'approved', 'rejected', 'paid', 'closed'], true) ? (string) $_POST['status'] : 'pending';
                $stmt = $pdo->prepare("UPDATE academy_refund_requests SET status = ?, admin_notes = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
                $stmt->execute([$status, trim((string) ($_POST['admin_notes'] ?? '')), (int) ($_SESSION['user_id'] ?? 0) ?: null, $refundId]);
                academy_admin_redirect('refunds', 'message', 'Refund review saved.');
            }

            if ($action === 'save_instructor') {
                $name = trim((string) ($_POST['name'] ?? ''));
                if ($name === '') {
                    throw new RuntimeException('Instructor name is required.');
                }
                $stmt = $pdo->prepare("
                    INSERT INTO academy_instructors (name, email, phone, specialty, bio, status)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $name,
                    trim((string) ($_POST['email'] ?? '')) ?: null,
                    trim((string) ($_POST['phone'] ?? '')) ?: null,
                    trim((string) ($_POST['specialty'] ?? '')) ?: null,
                    trim((string) ($_POST['bio'] ?? '')) ?: null,
                    in_array((string) ($_POST['status'] ?? 'active'), ['active', 'paused', 'archived'], true) ? (string) $_POST['status'] : 'active',
                ]);
                academy_admin_redirect('instructors', 'message', 'Instructor saved.');
            }

            if ($action === 'save_cohort') {
                $courseId = (int) ($_POST['webinar_id'] ?? 0);
                $title = trim((string) ($_POST['title'] ?? ''));
                $start = strtotime((string) ($_POST['start_at'] ?? ''));
                if ($courseId <= 0 || $title === '' || $start === false) {
                    throw new RuntimeException('Course, cohort title, and start date are required.');
                }
                $endRaw = trim((string) ($_POST['end_at'] ?? ''));
                $end = $endRaw !== '' ? strtotime($endRaw) : false;
                $stmt = $pdo->prepare("
                    INSERT INTO academy_cohorts (webinar_id, instructor_id, title, start_at, end_at, venue, meeting_url, capacity, status, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $courseId,
                    (int) ($_POST['instructor_id'] ?? 0) ?: null,
                    $title,
                    date('Y-m-d H:i:s', $start),
                    $end !== false ? date('Y-m-d H:i:s', $end) : null,
                    trim((string) ($_POST['venue'] ?? '')) ?: null,
                    trim((string) ($_POST['meeting_url'] ?? '')) ?: null,
                    max(1, (int) ($_POST['capacity'] ?? 100)),
                    in_array((string) ($_POST['status'] ?? 'scheduled'), ['scheduled', 'open', 'completed', 'cancelled'], true) ? (string) $_POST['status'] : 'scheduled',
                    trim((string) ($_POST['notes'] ?? '')) ?: null,
                ]);
                academy_admin_redirect('calendar', 'message', 'Calendar/cohort session saved.');
            }

            if ($action === 'mark_attendance') {
                $cohortId = (int) ($_POST['cohort_id'] ?? 0);
                $userId = (int) ($_POST['user_id'] ?? 0);
                $status = in_array((string) ($_POST['status'] ?? 'present'), ['present', 'absent', 'late', 'excused'], true) ? (string) $_POST['status'] : 'present';
                $cohortStmt = $pdo->prepare("SELECT webinar_id FROM academy_cohorts WHERE id = ? LIMIT 1");
                $cohortStmt->execute([$cohortId]);
                $courseId = (int) $cohortStmt->fetchColumn();
                if ($cohortId <= 0 || $userId <= 0 || $courseId <= 0) {
                    throw new RuntimeException('Valid cohort and enrolled user are required.');
                }
                $stmt = $pdo->prepare("
                    INSERT INTO academy_attendance (cohort_id, webinar_id, user_id, status, marked_by, marked_at, notes)
                    VALUES (?, ?, ?, ?, ?, NOW(), ?)
                    ON DUPLICATE KEY UPDATE status = VALUES(status), marked_by = VALUES(marked_by), marked_at = NOW(), notes = VALUES(notes)
                ");
                $stmt->execute([$cohortId, $courseId, $userId, $status, (int) ($_SESSION['user_id'] ?? 0) ?: null, trim((string) ($_POST['notes'] ?? '')) ?: null]);
                academy_admin_redirect('attendance', 'message', 'Attendance saved.');
            }

            if ($action === 'save_reminder') {
                $title = trim((string) ($_POST['title'] ?? ''));
                $body = trim((string) ($_POST['message'] ?? ''));
                if ($title === '' || $body === '') {
                    throw new RuntimeException('Reminder title and message are required.');
                }
                $sendRaw = trim((string) ($_POST['send_at'] ?? ''));
                $sendAt = $sendRaw !== '' ? strtotime($sendRaw) : false;
                $status = in_array((string) ($_POST['status'] ?? 'draft'), ['draft', 'scheduled', 'sent', 'cancelled'], true) ? (string) $_POST['status'] : 'draft';
                $stmt = $pdo->prepare("
                    INSERT INTO academy_reminders (webinar_id, cohort_id, audience_roles, title, message, channel, send_at, status, created_by, sent_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, IF(? = 'sent', NOW(), NULL))
                ");
                $stmt->execute([
                    (int) ($_POST['webinar_id'] ?? 0) ?: null,
                    (int) ($_POST['cohort_id'] ?? 0) ?: null,
                    implode(',', array_values(array_filter(array_map('trim', (array) ($_POST['audience_roles'] ?? []))))),
                    $title,
                    $body,
                    in_array((string) ($_POST['channel'] ?? 'dashboard'), ['dashboard', 'email', 'sms', 'whatsapp'], true) ? (string) $_POST['channel'] : 'dashboard',
                    $sendAt !== false ? date('Y-m-d H:i:s', $sendAt) : null,
                    $status,
                    (int) ($_SESSION['user_id'] ?? 0) ?: null,
                    $status,
                ]);
                academy_admin_redirect('reminders', 'message', 'Reminder saved.');
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$programs = $pdo->query("SELECT * FROM academy_programs ORDER BY sort_order ASC, title ASC")->fetchAll();
$courses = academy_courses($pdo, null, false);
$certificateGroups = academy_certificate_groups($pdo, null, false);
$instructors = $pdo->query("SELECT * FROM academy_instructors ORDER BY status ASC, name ASC")->fetchAll();
$cohorts = $pdo->query("
    SELECT c.*, w.title course_title, i.name instructor_name,
           COUNT(DISTINCT r.id) enrolled,
           COUNT(DISTINCT a.id) attendance_marked
    FROM academy_cohorts c
    JOIN webinars w ON w.id = c.webinar_id
    LEFT JOIN academy_instructors i ON i.id = c.instructor_id
    LEFT JOIN webinar_registrations r ON r.webinar_id = c.webinar_id
    LEFT JOIN academy_attendance a ON a.cohort_id = c.id
    GROUP BY c.id
    ORDER BY c.start_at DESC
    LIMIT 160
")->fetchAll();
$lessons = $pdo->query("
    SELECT l.*, w.title course_title
    FROM academy_lessons l
    JOIN webinars w ON w.id = l.webinar_id
    ORDER BY w.title ASC, l.sort_order ASC, l.id ASC
    LIMIT 150
")->fetchAll();
$assessments = $pdo->query("
    SELECT a.*, w.title course_title, COUNT(q.id) questions
    FROM academy_assessments a
    JOIN webinars w ON w.id = a.webinar_id
    LEFT JOIN academy_questions q ON q.assessment_id = a.id
    GROUP BY a.id
    ORDER BY w.title ASC, a.id ASC
")->fetchAll();
$enrollments = $pdo->query("
    SELECT r.*, u.name user_name, u.email, w.title course_title, p.title program_title
    FROM webinar_registrations r
    JOIN users u ON u.id = r.user_id
    JOIN webinars w ON w.id = r.webinar_id
    LEFT JOIN academy_programs p ON p.id = w.program_id
    ORDER BY r.registered_at DESC
    LIMIT 120
")->fetchAll();
$certificates = $pdo->query("
    SELECT c.id, c.user_id, c.certificate_ref, c.status, c.requested_at, c.issued_at, c.notes,
           'course' certificate_kind, u.name user_name, u.email, w.title course_title
    FROM academy_certificates c
    JOIN users u ON u.id = c.user_id
    JOIN webinars w ON w.id = c.webinar_id
    UNION ALL
    SELECT gc.id, gc.user_id, gc.certificate_ref, gc.status, gc.requested_at, gc.issued_at, gc.notes,
           'group' certificate_kind, u.name user_name, u.email, g.title course_title
    FROM academy_group_certificates gc
    JOIN users u ON u.id = gc.user_id
    JOIN academy_certificate_groups g ON g.id = gc.group_id
    ORDER BY requested_at DESC
    LIMIT 120
")->fetchAll();
$refunds = $pdo->query("
    SELECT rr.*, u.name user_name, u.email, w.title course_title
    FROM academy_refund_requests rr
    JOIN users u ON u.id = rr.user_id
    JOIN webinars w ON w.id = rr.webinar_id
    ORDER BY rr.requested_at DESC
    LIMIT 120
")->fetchAll();
$attendanceRows = $pdo->query("
    SELECT a.*, c.title cohort_title, w.title course_title, u.name user_name, u.email
    FROM academy_attendance a
    JOIN academy_cohorts c ON c.id = a.cohort_id
    JOIN webinars w ON w.id = a.webinar_id
    JOIN users u ON u.id = a.user_id
    ORDER BY a.marked_at DESC
    LIMIT 160
")->fetchAll();
$reminders = $pdo->query("
    SELECT r.*, w.title course_title, c.title cohort_title
    FROM academy_reminders r
    LEFT JOIN webinars w ON w.id = r.webinar_id
    LEFT JOIN academy_cohorts c ON c.id = r.cohort_id
    ORDER BY COALESCE(r.send_at, r.created_at) DESC
    LIMIT 160
")->fetchAll();
$feedbackRows = $pdo->query("
    SELECT f.*, u.name user_name, u.email, w.title course_title
    FROM academy_feedback f
    JOIN users u ON u.id = f.user_id
    JOIN webinars w ON w.id = f.webinar_id
    ORDER BY f.created_at DESC
    LIMIT 160
")->fetchAll();
$completionByRole = $pdo->query("
    SELECT COALESCE(NULLIF(u.platform_role, ''), u.role, 'grower') user_role,
           COUNT(r.id) enrollments,
           SUM(r.completion_status = 'completed') completed,
           ROUND(AVG(r.progress_percent), 1) avg_progress
    FROM webinar_registrations r
    JOIN users u ON u.id = r.user_id
    GROUP BY COALESCE(NULLIF(u.platform_role, ''), u.role, 'grower')
    ORDER BY enrollments DESC
")->fetchAll();
$courseReport = $pdo->query("
    SELECT w.title,
           COUNT(DISTINCT r.id) enrollments,
           SUM(r.payment_status = 'paid') paid_enrollments,
           SUM(r.completion_status = 'completed') completed,
           COUNT(DISTINCT at.id) attempts,
           ROUND(AVG(at.score_percent), 1) avg_score,
           ROUND(AVG(f.rating), 1) avg_rating
    FROM webinars w
    LEFT JOIN webinar_registrations r ON r.webinar_id = w.id
    LEFT JOIN academy_attempts at ON at.webinar_id = w.id
    LEFT JOIN academy_feedback f ON f.webinar_id = w.id
    GROUP BY w.id
    ORDER BY enrollments DESC, w.title ASC
    LIMIT 120
")->fetchAll();
$stats = [
    'programs' => count($programs),
    'courses' => count($courses),
    'lessons' => (int) $pdo->query("SELECT COUNT(*) FROM academy_lessons")->fetchColumn(),
    'enrollments' => (int) $pdo->query("SELECT COUNT(*) FROM webinar_registrations")->fetchColumn(),
    'completed' => (int) $pdo->query("SELECT COUNT(*) FROM webinar_registrations WHERE completion_status = 'completed'")->fetchColumn(),
    'certificates' => (int) $pdo->query("SELECT COUNT(*) FROM academy_certificates")->fetchColumn(),
    'cohorts' => (int) $pdo->query("SELECT COUNT(*) FROM academy_cohorts")->fetchColumn(),
    'attendance' => (int) $pdo->query("SELECT COUNT(*) FROM academy_attendance")->fetchColumn(),
    'feedback' => (int) $pdo->query("SELECT COUNT(*) FROM academy_feedback")->fetchColumn(),
];
$stats['active_courses'] = (int) $pdo->query("SELECT COUNT(*) FROM webinars WHERE status = 'active'")->fetchColumn();
$stats['pending_certificates'] = (int) $pdo->query("SELECT COUNT(*) FROM academy_certificates WHERE status = 'pending'")->fetchColumn();
$stats['pending_refunds'] = (int) $pdo->query("SELECT COUNT(*) FROM academy_refund_requests WHERE status IN ('pending','under_review','approved')")->fetchColumn();
$stats['completed_percent'] = $stats['enrollments'] > 0 ? round(((int) $stats['completed'] / (int) $stats['enrollments']) * 100, 1) : 0.0;
$academyCollections = (float) $pdo->query("
    SELECT COALESCE(SUM(w.price), 0)
    FROM webinar_registrations r
    JOIN webinars w ON w.id = r.webinar_id
    WHERE r.payment_status IN ('paid','successful')
")->fetchColumn();
$academyOutstanding = (float) $pdo->query("
    SELECT COALESCE(SUM(w.price), 0)
    FROM webinar_registrations r
    JOIN webinars w ON w.id = r.webinar_id
    WHERE r.payment_status IN ('pending','processing')
")->fetchColumn();
$recentCourses = array_slice($courses, 0, 5);
$recentEnrollments = array_slice($enrollments, 0, 5);
$recentCertificates = array_slice($certificates, 0, 5);
$recentRefunds = array_slice($refunds, 0, 5);
$upcomingCohorts = array_values(array_filter($cohorts, static fn(array $row): bool => strtotime((string) $row['start_at']) >= strtotime('-1 day')));
$upcomingCohorts = array_slice($upcomingCohorts, 0, 5);

function academy_admin_money(float $amount): string
{
    return 'NGN ' . number_format($amount, 2);
}

function academy_admin_when(?string $date): string
{
    if (!$date) {
        return '-';
    }
    $time = strtotime($date);
    return $time ? date('M j, Y g:i A', $time) : '-';
}

function academy_admin_badge(string $status): string
{
    return match ($status) {
        'active', 'completed', 'issued', 'paid', 'successful', 'visible', 'present' => 'ok',
        'pending', 'registered', 'scheduled', 'draft', 'under_review', 'processing' => 'warn',
        'rejected', 'cancelled', 'failed', 'archived', 'absent' => 'bad',
        default => 'info',
    };
}
$roles = ['all', 'grower', 'farm_hand', 'provider', 'input_provider', 'service_provider', 'seller', 'field_agent', 'agronomist', 'agric_extensionist', 'state_coordinator', 'national_coordinator', 'investor', 'admin', 'super_admin'];

admin_page_start('Academy Workspace', [
    'active' => 'academy.php',
    'description' => 'Manage programs, courses, lessons, materials, assessments, enrollments, certificates, refunds, and learning reports.',
    'wide' => true,
    'chrome' => false,
    'css' => '.admin-header,.admin-footer,.page-title{display:none!important}.admin-main{max-width:none!important;padding:0!important}.admin-shell{background:#f6faf7}.notice{margin:14px 16px}.acad-workspace{display:grid;grid-template-columns:248px minmax(0,1fr);gap:18px;align-items:start}.acad-rail{position:sticky;top:0;min-height:100vh;border-radius:0;background:linear-gradient(180deg,#063f24,#005b32);color:#fff;padding:16px;box-shadow:0 18px 42px rgba(6,63,36,.22)}.acad-brand{display:flex;gap:10px;align-items:center;border-bottom:1px solid rgba(255,255,255,.14);padding-bottom:14px;margin-bottom:14px}.acad-brand img{width:46px;height:46px;border-radius:50%;background:#fff;padding:4px}.acad-brand strong{display:block;font-size:1.05rem}.acad-brand small{display:block;color:#dff5e8;font-size:.72rem;line-height:1.25}.acad-label{font-size:.72rem;text-transform:uppercase;color:#aee4c4;font-weight:900;margin:14px 4px 8px}.acad-nav{display:grid;gap:5px}.acad-nav a{display:flex;align-items:center;justify-content:space-between;gap:10px;color:#fff;text-decoration:none;padding:10px 11px;border-radius:8px;font-weight:850}.acad-nav a:hover,.acad-nav a.active{background:rgba(46,204,113,.24)}.acad-nav span:first-child{display:inline-flex;align-items:center;gap:9px}.acad-count{background:#0ea765;color:#fff;border-radius:999px;min-width:24px;text-align:center;padding:2px 7px;font-size:.74rem}.acad-count.warn{background:#f79009}.acad-content{min-width:0;padding:16px 16px 26px}.acad-top{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:12px}.acad-search{flex:1;min-width:280px;border:1px solid var(--line);border-radius:8px;background:#fff;display:flex;align-items:center;gap:10px;padding:9px 12px;color:var(--muted)}.acad-search input{border:0;box-shadow:none;padding:0}.acad-search input:focus{box-shadow:none}.acad-toolstrip{display:flex;gap:10px;align-items:center;flex-wrap:wrap}.acad-tool{border:1px solid var(--line);border-radius:8px;background:#fff;padding:9px 11px;font-weight:850;color:#102033}.acad-head{display:flex;justify-content:space-between;align-items:end;gap:12px;flex-wrap:wrap;margin-bottom:14px}.acad-head h2{font-size:1.65rem;margin:0;color:#0b1f16}.acad-head p{margin:4px 0 0;color:var(--muted)}.acad-kpis{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:12px}.acad-kpi{border:1px solid var(--line);border-radius:8px;background:#fff;box-shadow:var(--shadow);padding:14px;display:flex;justify-content:space-between;gap:10px;min-height:112px}.acad-kpi small{display:block;text-transform:uppercase;font-size:.72rem;font-weight:900;color:#536171}.acad-kpi strong{display:block;font-size:1.45rem;color:#101828;margin-top:7px}.acad-kpi span{display:block;color:#079455;font-size:.78rem;font-weight:850;margin-top:5px}.acad-icon{width:48px;height:48px;border-radius:50%;display:grid;place-items:center;background:#e8f5ed;color:#087443;font-size:1.2rem}.acad-icon.blue{background:#e8f1ff;color:#175cd3}.acad-icon.orange{background:#fff1df;color:#c05600}.acad-icon.purple{background:#f1e9ff;color:#6941c6}.acad-icon.red{background:#fee4e2;color:#d92d20}.acad-grid{display:grid;grid-template-columns:1.2fr 1.35fr .95fr;gap:14px;margin-top:14px}.acad-row{display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:14px;margin-top:14px}.acad-bottom{display:grid;grid-template-columns:1fr 1.15fr;gap:14px;margin-top:14px}.acad-panel{border:1px solid var(--line);border-radius:8px;background:#fff;box-shadow:var(--shadow);padding:14px}.acad-panel-head{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:10px}.acad-panel-head h3{margin:0;color:#102033;font-size:1rem}.acad-panel-head a{color:#0f6b3c;text-decoration:none;font-weight:900;font-size:.82rem}.acad-table{width:100%;border-collapse:collapse}.acad-table th,.acad-table td{padding:9px 8px;border-bottom:1px solid #edf1f4;text-align:left;font-size:.8rem;vertical-align:top}.acad-table th{font-size:.72rem;text-transform:uppercase;color:#667085}.acad-badge{display:inline-flex;border-radius:999px;padding:3px 7px;font-size:.7rem;font-weight:900}.acad-badge.ok{background:#dcfae6;color:#067647}.acad-badge.info{background:#dbeafe;color:#175cd3}.acad-badge.warn{background:#fef0c7;color:#b54708}.acad-badge.bad{background:#fee4e2;color:#b42318}.acad-chart{height:210px;display:flex;align-items:end;gap:10px;border-bottom:1px solid #d8dee6;padding:12px 8px 0}.acad-bar{flex:1;border-radius:8px 8px 0 0;background:linear-gradient(180deg,#0f6b3c,#9bd6ae);min-height:28px}.acad-list{display:grid;gap:9px}.acad-list-row{display:flex;justify-content:space-between;gap:10px;border-bottom:1px solid #eef2f4;padding-bottom:9px;font-size:.83rem}.acad-list-row strong{color:#102033}.acad-list-row small{display:block;color:var(--muted);margin-top:2px}.acad-progress{height:8px;border-radius:999px;background:#eef2f4;overflow:hidden;min-width:86px}.acad-fill{height:100%;background:#0f6b3c}.acad-actions{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:14px}.acad-action{border:1px solid var(--line);border-radius:8px;background:#fff;padding:16px;display:flex;gap:12px;align-items:center;color:inherit;text-decoration:none}.acad-action i{width:38px;height:38px;border-radius:50%;display:grid;place-items:center;background:#e8f5ed;color:#0f6b3c}.acad-action strong{display:block;color:#102033}.acad-action small{color:var(--muted)}.academy-tabs{display:grid;gap:10px;margin:0 0 18px}.academy-tab-row{display:flex;align-items:flex-start;gap:10px;flex-wrap:wrap;padding:10px;border:1px solid var(--line);border-radius:8px;background:#fff}.academy-tab-label{min-width:92px;padding:9px 10px;color:var(--green-dark);font-weight:900}.academy-tab-row a{display:inline-flex;align-items:center;min-height:36px;padding:8px 11px;border:1px solid transparent;border-radius:7px;background:#f8fbf9;color:var(--ink);font-weight:800}.academy-tab-row a.active,.academy-tab-row a:hover{background:#e8f5ed;border-color:#cae4d4;color:var(--green-dark);text-decoration:none}.academy-split{display:grid;grid-template-columns:360px minmax(0,1fr);gap:18px;align-items:start}.academy-create{padding:0}.academy-create>summary{padding:16px 18px;font-weight:900;color:var(--green-dark);cursor:pointer;list-style:none}.academy-create>summary::-webkit-details-marker{display:none}.academy-create>form,.academy-create>.panel-inner{padding:0 18px 18px}.academy-pillbox{display:flex;gap:6px;flex-wrap:wrap}.academy-pillbox label{margin:0;border:1px solid var(--line);border-radius:999px;padding:6px 9px;background:#fbfdfb;font-size:.85rem}.academy-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.academy-card-list{display:grid;gap:12px}.academy-card-list article{border:1px solid var(--line);border-radius:8px;background:#fff;padding:14px}.academy-mini{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px}@media(max-width:1400px){.acad-workspace{grid-template-columns:1fr}.acad-rail{position:relative;top:auto;min-height:auto}.acad-nav{grid-template-columns:repeat(3,minmax(0,1fr))}.acad-kpis{grid-template-columns:repeat(3,minmax(0,1fr))}.acad-grid,.acad-row,.acad-bottom{grid-template-columns:1fr}.acad-actions{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:900px){.academy-split,.academy-form-grid,.acad-nav,.acad-kpis,.acad-actions{grid-template-columns:1fr}.academy-tab-label{width:100%;min-width:0}}',
]);
?>
<style>
  html, body { margin:0 !important; padding:0 !important; overflow-x:hidden; }
  .admin-main { width:100vw !important; max-width:none !important; margin:0 !important; padding:0 !important; }
  .acad-workspace { width:100vw !important; min-height:100vh; margin:0 !important; gap:0 !important; grid-template-columns:220px minmax(0,1fr) !important; }
  .acad-rail { left:0; width:220px; min-height:100vh; max-height:100vh; overflow-y:auto; overflow-x:hidden; }
  .acad-nav { grid-template-columns:1fr !important; }
  .acad-nav a { min-width:0; width:100%; align-items:center; }
  .acad-nav a span:first-child { min-width:0; max-width:100%; overflow-wrap:anywhere; }
  .acad-count { flex:0 0 auto; }
  .acad-content { padding:12px 14px 28px !important; background:#f6faf7; min-height:100vh; }
  @media(max-width:1400px){ .acad-workspace{grid-template-columns:220px minmax(0,1fr) !important;} .acad-rail{position:sticky !important; top:0 !important;} .acad-nav{grid-template-columns:1fr !important;} }
</style>
<?php if (!empty($_GET['message'])): ?><div class="notice ok"><?= e((string) $_GET['message']) ?></div><?php endif; ?>
<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>

<div class="acad-workspace">
  <aside class="acad-rail" aria-label="Academy workspace navigation">
    <div class="acad-brand"><img src="<?= e(app_primary_logo_url()) ?>" alt="NATCODEV"><div><strong>NATCODEV</strong><small>Academy Workspace</small></div></div>
    <div class="acad-label">Workspace Hub</div>
    <nav class="acad-nav">
      <a href="index.php"><span><i class="fa-solid fa-house"></i> Workspace Hub</span></a>
      <a href="coordination.php"><span><i class="fa-solid fa-gauge-high"></i> Operations</span></a>
      <a href="registry.php"><span><i class="fa-solid fa-people-roof"></i> Registry</span></a>
      <a href="marketplace.php"><span><i class="fa-solid fa-cart-shopping"></i> Marketplace</span></a>
      <a href="wallet.php"><span><i class="fa-solid fa-wallet"></i> Wallet</span></a>
      <a href="support.php"><span><i class="fa-solid fa-headset"></i> Support Desk</span></a>
    </nav>
    <div class="acad-label">Academy Workspace</div>
    <nav class="acad-nav">
      <a class="<?= $tab === 'overview' ? 'active' : '' ?>" href="academy.php"><span><i class="fa-solid fa-table-columns"></i> Overview</span></a>
      <a class="<?= $tab === 'programs' ? 'active' : '' ?>" href="academy-programs.php"><span><i class="fa-solid fa-layer-group"></i> Programs</span><span class="acad-count"><?= (int) $stats['programs'] ?></span></a>
      <a class="<?= $tab === 'courses' ? 'active' : '' ?>" href="academy-courses.php"><span><i class="fa-solid fa-book-open"></i> Courses</span><span class="acad-count"><?= (int) $stats['courses'] ?></span></a>
    </nav>
    <div class="acad-label">Content & Assessment</div>
    <nav class="acad-nav">
      <a class="<?= $tab === 'lessons' ? 'active' : '' ?>" href="academy-lessons.php"><span><i class="fa-solid fa-file-lines"></i> Lessons & Materials</span><span class="acad-count"><?= (int) $stats['lessons'] ?></span></a>
      <a class="<?= $tab === 'assessments' ? 'active' : '' ?>" href="academy-assessments.php"><span><i class="fa-solid fa-clipboard-question"></i> Assessments</span></a>
      <a class="<?= $tab === 'certificate_groups' ? 'active' : '' ?>" href="academy-certificate-pathways.php"><span><i class="fa-solid fa-route"></i> Pathways</span></a>
    </nav>
    <div class="acad-label">Delivery Operations</div>
    <nav class="acad-nav">
      <a class="<?= $tab === 'calendar' ? 'active' : '' ?>" href="academy-cohorts.php"><span><i class="fa-regular fa-calendar"></i> Cohorts</span><span class="acad-count"><?= (int) $stats['cohorts'] ?></span></a>
      <a class="<?= $tab === 'instructors' ? 'active' : '' ?>" href="academy-instructors.php"><span><i class="fa-solid fa-chalkboard-user"></i> Instructors</span></a>
      <a class="<?= $tab === 'attendance' ? 'active' : '' ?>" href="academy-attendance.php"><span><i class="fa-solid fa-clipboard-check"></i> Attendance</span><span class="acad-count"><?= (int) $stats['attendance'] ?></span></a>
      <a class="<?= $tab === 'reminders' ? 'active' : '' ?>" href="academy-reminders.php"><span><i class="fa-solid fa-bell"></i> Reminders</span></a>
    </nav>
    <div class="acad-label">Learners & Outcomes</div>
    <nav class="acad-nav">
      <a class="<?= $tab === 'enrollments' ? 'active' : '' ?>" href="academy-learners.php"><span><i class="fa-solid fa-users"></i> Learners</span><span class="acad-count"><?= (int) $stats['enrollments'] ?></span></a>
      <a class="<?= $tab === 'certificates' ? 'active' : '' ?>" href="academy-certificates.php"><span><i class="fa-solid fa-certificate"></i> Certificates</span><span class="acad-count warn"><?= (int) $stats['pending_certificates'] ?></span></a>
      <a class="<?= $tab === 'refunds' ? 'active' : '' ?>" href="academy-refunds.php"><span><i class="fa-solid fa-rotate-left"></i> Refunds</span><span class="acad-count warn"><?= (int) $stats['pending_refunds'] ?></span></a>
      <a class="<?= $tab === 'feedback' ? 'active' : '' ?>" href="academy-feedback.php"><span><i class="fa-solid fa-star-half-stroke"></i> Feedback</span><span class="acad-count"><?= (int) $stats['feedback'] ?></span></a>
    </nav>
    <div class="acad-label">Reporting</div>
    <nav class="acad-nav">
      <a class="<?= $tab === 'reports' ? 'active' : '' ?>" href="academy-reports.php"><span><i class="fa-solid fa-chart-line"></i> Reports</span></a>
    </nav>
    <div class="acad-label">Quick Links</div>
    <nav class="acad-nav">
      <a href="academy-courses.php"><span><i class="fa-solid fa-plus"></i> Add Course</span></a>
      <a href="academy-cohorts.php"><span><i class="fa-solid fa-calendar-plus"></i> Schedule Cohort</span></a>
      <a href="academy-certificates.php"><span><i class="fa-solid fa-award"></i> Review Certificates</span></a>
      <a href="../academy/index.php" target="_blank"><span><i class="fa-solid fa-arrow-up-right-from-square"></i> Public Academy</span></a>
    </nav>
  </aside>
  <main class="acad-content">
    <div class="acad-top">
      <div class="acad-search"><i class="fa-solid fa-magnifying-glass"></i><input type="search" placeholder="Search courses, learners, certificates, cohorts..." aria-label="Search Academy workspace"></div>
      <div class="acad-toolstrip">
        <span class="acad-tool"><i class="fa-regular fa-bell"></i> <?= (int) $stats['pending_certificates'] ?></span>
        <span class="acad-tool"><i class="fa-solid fa-wallet"></i> <?= e(academy_admin_money($academyCollections)) ?></span>
        <a class="button secondary" href="../academy/index.php" target="_blank">View Public Academy</a>
      </div>
    </div>
    <div class="acad-head">
      <div><h2>NATCODEV Academy</h2><p>Manage learning programs, courses, enrollments, certificates, payments, cohorts, and learner outcomes.</p></div>
      <span class="acad-tool"><i class="fa-regular fa-calendar"></i> <?= e(date('M j')) ?> - <?= e(date('M j, Y', strtotime('+6 days'))) ?></span>
    </div>

<?php $academyNavGroups = [
    'Content Tools' => ['lessons' => 'Lessons & Materials', 'assessments' => 'Assessments', 'certificate_groups' => 'Certificate Groups'],
    'Delivery Tools' => ['calendar' => 'Calendar & Cohorts', 'instructors' => 'Instructors', 'attendance' => 'Attendance', 'reminders' => 'Reminders'],
    'Learner Operations' => ['enrollments' => 'Enrollments', 'certificates' => 'Certificates', 'refunds' => 'Refunds', 'feedback' => 'Feedback'],
];
$academyPageMap = [
    'programs' => 'academy-programs.php',
    'courses' => 'academy-courses.php',
    'lessons' => 'academy-lessons.php',
    'assessments' => 'academy-assessments.php',
    'certificate_groups' => 'academy-certificate-pathways.php',
    'calendar' => 'academy-cohorts.php',
    'instructors' => 'academy-instructors.php',
    'attendance' => 'academy-attendance.php',
    'reminders' => 'academy-reminders.php',
    'enrollments' => 'academy-learners.php',
    'certificates' => 'academy-certificates.php',
    'refunds' => 'academy-refunds.php',
    'feedback' => 'academy-feedback.php',
    'reports' => 'academy-reports.php',
]; ?>
<details class="academy-tabs academy-create" aria-label="Academy advanced module navigation">
  <summary>Advanced Academy Tools</summary>
  <?php foreach ($academyNavGroups as $groupLabel => $items): ?>
    <div class="academy-tab-row">
      <div class="academy-tab-label"><?= e($groupLabel) ?></div>
      <?php foreach ($items as $key => $label): ?><a class="<?= $tab === $key ? 'active' : '' ?>" href="<?= e($academyPageMap[$key] ?? 'academy.php') ?>"><?= e($label) ?></a><?php endforeach; ?>
    </div>
  <?php endforeach; ?>
</details>
<script>
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.academy-split > form.panel, .academy-split > .panel').forEach(function (panel) {
    if (panel.closest('.academy-create')) return;
    var title = panel.querySelector('h2') ? panel.querySelector('h2').textContent.trim() : 'Tools';
    var details = document.createElement('details');
    details.className = 'panel academy-create';
    var summary = document.createElement('summary');
    summary.textContent = 'Open ' + title;
    panel.parentNode.insertBefore(details, panel);
    panel.classList.remove('panel');
    details.appendChild(summary);
    details.appendChild(panel);
  });
});
</script>

<?php if ($tab === 'overview'): ?>
  <section class="acad-kpis">
    <div class="acad-kpi"><div><small>Active Courses</small><strong><?= (int) $stats['active_courses'] ?></strong><span><?= (int) $stats['courses'] ?> total courses</span></div><div class="acad-icon"><i class="fa-solid fa-book-open"></i></div></div>
    <div class="acad-kpi"><div><small>Learner Enrollments</small><strong><?= (int) $stats['enrollments'] ?></strong><span><?= (int) $stats['completed'] ?> completed</span></div><div class="acad-icon blue"><i class="fa-solid fa-users"></i></div></div>
    <div class="acad-kpi"><div><small>Completion Rate</small><strong><?= number_format((float) $stats['completed_percent'], 1) ?>%</strong><span>Across Academy learners</span></div><div class="acad-icon"><i class="fa-solid fa-circle-check"></i></div></div>
    <div class="acad-kpi"><div><small>Certificates</small><strong><?= (int) $stats['certificates'] ?></strong><span><?= (int) $stats['pending_certificates'] ?> pending review</span></div><div class="acad-icon purple"><i class="fa-solid fa-certificate"></i></div></div>
    <div class="acad-kpi"><div><small>Collections</small><strong><?= e(academy_admin_money($academyCollections)) ?></strong><span><?= e(academy_admin_money($academyOutstanding)) ?> outstanding</span></div><div class="acad-icon"><i class="fa-solid fa-wallet"></i></div></div>
    <div class="acad-kpi"><div><small>Refund Requests</small><strong><?= (int) $stats['pending_refunds'] ?></strong><span>Academy payment support</span></div><div class="acad-icon red"><i class="fa-solid fa-rotate-left"></i></div></div>
  </section>

  <section class="acad-grid">
    <div class="acad-panel">
      <div class="acad-panel-head"><h3>Enrollment & Completion Trend</h3><a href="academy-reports.php">View Report</a></div>
      <div class="acad-chart"><?php foreach ([44, 58, 52, 70, 86, 78, 92] as $height): ?><div class="acad-bar" style="height:<?= $height ?>%"></div><?php endforeach; ?></div>
      <div class="acad-list-row"><span>Total Enrollments</span><strong><?= (int) $stats['enrollments'] ?></strong></div>
      <div class="acad-list-row"><span>Completed Learning Journeys</span><strong><?= (int) $stats['completed'] ?></strong></div>
    </div>
    <div class="acad-panel">
      <div class="acad-panel-head"><h3>Recent Enrollments</h3><a href="academy-learners.php">View All</a></div>
      <table class="acad-table"><thead><tr><th>Learner</th><th>Course</th><th>Payment</th><th>Progress</th></tr></thead><tbody>
        <?php foreach ($recentEnrollments as $row): ?><tr><td><strong><?= e((string) $row['user_name']) ?></strong><br><small><?= e((string) $row['email']) ?></small></td><td><?= e((string) $row['course_title']) ?></td><td><span class="acad-badge <?= e(academy_admin_badge((string) $row['payment_status'])) ?>"><?= e((string) $row['payment_status']) ?></span></td><td><?= (int) $row['progress_percent'] ?>%</td></tr><?php endforeach; ?>
        <?php if (!$recentEnrollments): ?><tr><td colspan="4">No enrollments yet.</td></tr><?php endif; ?>
      </tbody></table>
    </div>
    <div class="acad-panel">
      <div class="acad-panel-head"><h3>Public Academy</h3><a href="../academy/index.php" target="_blank">Open</a></div>
      <div class="acad-list-row"><span>Programs</span><strong><?= (int) $stats['programs'] ?></strong></div>
      <div class="acad-list-row"><span>Lessons & Materials</span><strong><?= (int) $stats['lessons'] ?></strong></div>
      <div class="acad-list-row"><span>Assessments</span><strong><?= count($assessments) ?></strong></div>
      <div class="acad-list-row"><span>Feedback</span><strong><?= (int) $stats['feedback'] ?></strong></div>
      <a class="button secondary" style="width:100%;margin-top:12px" href="../academy/index.php" target="_blank">View Learner Entry Point</a>
    </div>
  </section>

  <section class="acad-row">
    <div class="acad-panel">
      <div class="acad-panel-head"><h3>Course Catalog Health</h3><a href="academy-courses.php">View All</a></div>
      <?php foreach ($recentCourses as $course): ?><div class="acad-list-row"><div><strong><?= e((string) $course['title']) ?></strong><small><?= e((string) ($course['program_title'] ?? 'Unassigned')) ?> / <?= e(academy_delivery_label((string) ($course['delivery_type'] ?? 'lms'))) ?></small></div><span class="acad-badge <?= e(academy_admin_badge((string) $course['status'])) ?>"><?= e((string) $course['status']) ?></span></div><?php endforeach; ?>
      <?php if (!$recentCourses): ?><p class="empty">No Academy courses yet.</p><?php endif; ?>
    </div>
    <div class="acad-panel">
      <div class="acad-panel-head"><h3>Upcoming Cohorts</h3><a href="academy-cohorts.php">View Calendar</a></div>
      <?php foreach ($upcomingCohorts as $cohort): ?><div class="acad-list-row"><div><strong><?= e((string) $cohort['title']) ?></strong><small><?= e((string) $cohort['course_title']) ?> / <?= e(academy_admin_when((string) $cohort['start_at'])) ?></small></div><span><?= (int) $cohort['enrolled'] ?> enrolled</span></div><?php endforeach; ?>
      <?php if (!$upcomingCohorts): ?><p class="empty">No upcoming cohorts scheduled.</p><?php endif; ?>
    </div>
    <div class="acad-panel">
      <div class="acad-panel-head"><h3>Certificate Review Queue</h3><a href="academy-certificates.php">View All</a></div>
      <?php foreach ($recentCertificates as $cert): ?><div class="acad-list-row"><div><strong><?= e((string) $cert['user_name']) ?></strong><small><?= e((string) $cert['course_title']) ?></small></div><span class="acad-badge <?= e(academy_admin_badge((string) $cert['status'])) ?>"><?= e((string) $cert['status']) ?></span></div><?php endforeach; ?>
      <?php if (!$recentCertificates): ?><p class="empty">No certificate requests yet.</p><?php endif; ?>
    </div>
    <div class="acad-panel">
      <div class="acad-panel-head"><h3>Refund & Payment Queue</h3><a href="academy-refunds.php">View All</a></div>
      <?php foreach ($recentRefunds as $refund): ?><div class="acad-list-row"><div><strong><?= e((string) $refund['user_name']) ?></strong><small><?= e((string) $refund['course_title']) ?></small></div><span class="acad-badge <?= e(academy_admin_badge((string) $refund['status'])) ?>"><?= e('NGN ' . number_format((float) $refund['amount'], 2)) ?></span></div><?php endforeach; ?>
      <?php if (!$recentRefunds): ?><p class="empty">No Academy refund requests.</p><?php endif; ?>
    </div>
  </section>

  <section class="acad-bottom">
    <div class="acad-panel">
      <div class="acad-panel-head"><h3>Completion by Role</h3><a href="academy-reports.php">Full Report</a></div>
      <table class="acad-table"><thead><tr><th>Role</th><th>Enrollments</th><th>Completed</th><th>Progress</th></tr></thead><tbody>
        <?php foreach (array_slice($completionByRole, 0, 6) as $row): $progress = max(0, min(100, (float) $row['avg_progress'])); ?><tr><td><?= e(academy_role_label((string) $row['user_role'])) ?></td><td><?= (int) $row['enrollments'] ?></td><td><?= (int) $row['completed'] ?></td><td><div class="acad-progress"><div class="acad-fill" style="width:<?= $progress ?>%"></div></div><?= number_format($progress, 1) ?>%</td></tr><?php endforeach; ?>
        <?php if (!$completionByRole): ?><tr><td colspan="4">No completion data yet.</td></tr><?php endif; ?>
      </tbody></table>
    </div>
    <div class="acad-panel">
      <div class="acad-panel-head"><h3>Quick Actions</h3></div>
      <div class="acad-actions">
        <a class="acad-action" href="academy-courses.php"><i class="fa-solid fa-plus"></i><span><strong>Add Course</strong><small>Create a new Academy course</small></span></a>
        <a class="acad-action" href="academy-lessons.php"><i class="fa-solid fa-file-lines"></i><span><strong>Add Lesson</strong><small>Build course content</small></span></a>
        <a class="acad-action" href="academy-cohorts.php"><i class="fa-regular fa-calendar-plus"></i><span><strong>Schedule Cohort</strong><small>Plan live delivery</small></span></a>
        <a class="acad-action" href="academy-certificates.php"><i class="fa-solid fa-certificate"></i><span><strong>Review Certificate</strong><small>Approve learner awards</small></span></a>
        <a class="acad-action" href="academy-reports.php"><i class="fa-solid fa-download"></i><span><strong>Export Report</strong><small>Academy intelligence</small></span></a>
      </div>
    </div>
  </section>
<?php endif; ?>

<?php if ($tab === 'programs'): ?>
  <section class="academy-split">
    <form class="panel" method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="save_program">
      <input type="hidden" name="program_id" value="0">
      <h2>Create Program</h2>
      <label>Title<input name="title" required></label>
      <label>Description<textarea name="description"></textarea></label>
      <label>Status<select name="status"><option value="active">Active</option><option value="draft">Draft</option><option value="paused">Paused</option><option value="archived">Archived</option></select></label>
      <label>Sort Order<input type="number" name="sort_order" value="0"></label>
      <fieldset><legend>Audience Roles</legend><div class="academy-pillbox"><?php foreach ($roles as $role): ?><label><input type="checkbox" name="audience_roles[]" value="<?= e($role) ?>"> <?= e(academy_role_label($role)) ?></label><?php endforeach; ?></div></fieldset>
      <button type="submit">Save Program</button>
    </form>
    <div class="academy-card-list">
      <?php foreach ($programs as $program): ?>
        <article>
          <h3><?= e((string) $program['title']) ?></h3>
          <p><?= e((string) $program['description']) ?></p>
          <small><?= e(academy_role_labels((string) $program['audience_roles'])) ?> / <?= e((string) $program['status']) ?></small>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<?php if ($tab === 'courses'): ?>
  <section class="academy-split">
    <form class="panel" method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="save_course">
      <input type="hidden" name="course_id" value="0">
      <h2>Create Course</h2>
      <div class="academy-form-grid">
        <label>Program<select name="program_id"><?php foreach ($programs as $program): ?><option value="<?= (int) $program['id'] ?>"><?= e((string) $program['title']) ?></option><?php endforeach; ?></select></label>
        <label>Course Code<input name="course_code" placeholder="NAT-ACAD-001"></label>
        <label>Course Type<select name="course_type"><option value="course">Course</option><option value="webinar">Webinar</option><option value="workshop">Workshop</option><option value="certification">Certification</option><option value="orientation">Orientation</option></select></label>
        <label>Status<select name="status"><option value="active">Active</option><option value="draft">Draft</option><option value="paused">Paused</option><option value="archived">Archived</option></select></label>
        <label>Start Time<input type="datetime-local" name="start_time" value="<?= e(date('Y-m-d\TH:i', strtotime('+7 days 10:00'))) ?>"></label>
        <label>Duration Minutes<input type="number" name="duration_minutes" value="90"></label>
        <label>Price NGN<input type="number" name="price" min="0" step="100" value="0"></label>
        <label>Max Attendees<input type="number" name="max_attendees" value="250"></label>
        <label>Delivery Type<select name="delivery_type"><?php foreach (academy_delivery_types() as $key => $label): ?><option value="<?= e($key) ?>"><?= e($label) ?></option><?php endforeach; ?></select></label>
        <label>Delivery URL<input name="delivery_url" placeholder="https://..."></label>
      </div>
      <label>Title<input name="title" required></label>
      <label>Description<textarea name="description" required></textarea></label>
      <label>Delivery Instructions<textarea name="delivery_instructions"></textarea></label>
      <label>Prerequisites<textarea name="prerequisites"></textarea></label>
      <div class="academy-form-grid">
        <label>Pass Score<input type="number" name="pass_score" min="0" max="100" value="70"></label>
        <label>Instructor<input name="instructor_name"></label>
      </div>
      <label><input type="checkbox" name="is_free" checked> Free course</label>
      <label><input type="checkbox" name="certification_required"> Certification course</label>
      <label><input type="checkbox" name="certificate_approval_required"> Certificate requires admin approval</label>
      <fieldset><legend>RBAC Audience</legend><div class="academy-pillbox"><?php foreach ($roles as $role): ?><label><input type="checkbox" name="target_roles[]" value="<?= e($role) ?>"> <?= e(academy_role_label($role)) ?></label><?php endforeach; ?></div></fieldset>
      <button type="submit">Save Course</button>
    </form>
    <div class="academy-card-list">
      <?php foreach ($courses as $course): ?>
        <article>
          <h3><?= e((string) $course['title']) ?></h3>
          <p><?= e((string) $course['description']) ?></p>
          <div class="academy-mini">
            <small><strong>Program</strong><br><?= e((string) ($course['program_title'] ?? 'Unassigned')) ?></small>
            <small><strong>Audience</strong><br><?= e(academy_role_labels((string) ($course['target_roles'] ?? ''))) ?></small>
            <small><strong>Price</strong><br><?= (int) $course['is_free'] === 1 ? 'Free' : 'NGN ' . e(number_format((float) $course['price'], 2)) ?></small>
            <small><strong>Delivery</strong><br><?= e(academy_delivery_label((string) ($course['delivery_type'] ?? 'lms'))) ?></small>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<?php if ($tab === 'certificate_groups'): ?>
  <section class="academy-split">
    <form class="panel" method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="save_certificate_group">
      <h2>Create Certificate Group</h2>
      <p class="muted">Use this when one certificate should represent a pathway made from several completed courses.</p>
      <label>Certificate Title<input name="title" required placeholder="Input Provider Accreditation Certificate"></label>
      <label>Description<textarea name="description" required></textarea></label>
      <div class="academy-form-grid">
        <label>Status<select name="status"><option value="active">Active</option><option value="draft">Draft</option><option value="paused">Paused</option><option value="archived">Archived</option></select></label>
        <label>Sort Order<input type="number" name="sort_order" value="0"></label>
      </div>
      <label><input type="checkbox" name="certificate_approval_required"> Requires admin approval before issue</label>
      <fieldset><legend>RBAC Audience</legend><div class="academy-pillbox"><?php foreach ($roles as $role): ?><label><input type="checkbox" name="audience_roles[]" value="<?= e($role) ?>"> <?= e(academy_role_label($role)) ?></label><?php endforeach; ?></div></fieldset>
      <fieldset><legend>Required Courses</legend><div class="academy-pillbox"><?php foreach ($courses as $course): ?><label><input type="checkbox" name="course_ids[]" value="<?= (int) $course['id'] ?>"> <?= e((string) $course['title']) ?></label><?php endforeach; ?></div></fieldset>
      <button type="submit">Save Certificate Group</button>
    </form>
    <div class="academy-card-list">
      <?php foreach ($certificateGroups as $group): ?>
        <?php $groupCourses = academy_certificate_group_courses($pdo, (int) $group['id']); ?>
        <article>
          <h3><?= e((string) $group['title']) ?></h3>
          <p><?= e((string) $group['description']) ?></p>
          <p class="muted"><?= e(academy_role_labels((string) $group['audience_roles'])) ?> / <?= (int) $group['certificate_approval_required'] === 1 ? 'Approval required' : 'Auto issue' ?></p>
          <small><?= count($groupCourses) ?> required course<?= count($groupCourses) === 1 ? '' : 's' ?>: <?= e(implode(', ', array_map(static fn(array $row): string => (string) $row['title'], $groupCourses))) ?></small>
        </article>
      <?php endforeach; ?>
      <?php if (!$certificateGroups): ?><article>No grouped certificates have been created yet.</article><?php endif; ?>
    </div>
  </section>
<?php endif; ?>

<?php if ($tab === 'lessons'): ?>
  <section class="academy-split">
    <form class="panel" method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="save_lesson">
      <h2>Add Lesson / Material</h2>
      <label>Course<select name="webinar_id" required><?php foreach ($courses as $course): ?><option value="<?= (int) $course['id'] ?>"><?= e((string) $course['title']) ?></option><?php endforeach; ?></select></label>
      <label>Title<input name="title" required></label>
      <label>Summary<textarea name="summary"></textarea></label>
      <label>Lesson Content<textarea name="content"></textarea></label>
      <div class="academy-form-grid">
        <label>Delivery Type<select name="delivery_type"><?php foreach (academy_delivery_types() as $key => $label): ?><option value="<?= e($key) ?>"><?= e($label) ?></option><?php endforeach; ?></select></label>
        <label>Material URL<input name="material_url"></label>
        <label>Duration<input type="number" name="duration_minutes" value="20"></label>
        <label>Sort<input type="number" name="sort_order" value="0"></label>
      </div>
      <label><input type="checkbox" name="is_required" checked> Required lesson</label>
      <label>Status<select name="status"><option value="active">Active</option><option value="draft">Draft</option><option value="paused">Paused</option><option value="archived">Archived</option></select></label>
      <button type="submit">Save Lesson</button>
    </form>
    <table><tr><th>Course</th><th>Lesson</th><th>Delivery</th><th>Status</th></tr><?php foreach ($lessons as $lesson): ?><tr><td><?= e((string) $lesson['course_title']) ?></td><td><strong><?= e((string) $lesson['title']) ?></strong><br><small><?= e((string) $lesson['summary']) ?></small></td><td><?= e(academy_delivery_label((string) $lesson['delivery_type'])) ?></td><td><?= e((string) $lesson['status']) ?></td></tr><?php endforeach; ?><?php if (!$lessons): ?><tr><td colspan="4">No lessons yet.</td></tr><?php endif; ?></table>
  </section>
<?php endif; ?>

<?php if ($tab === 'assessments'): ?>
  <section class="academy-split">
    <div class="panel">
      <form method="post">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save_assessment">
        <h2>Create Assessment</h2>
        <label>Course<select name="webinar_id"><?php foreach ($courses as $course): ?><option value="<?= (int) $course['id'] ?>"><?= e((string) $course['title']) ?></option><?php endforeach; ?></select></label>
        <label>Title<input name="title" required></label>
        <label>Instructions<textarea name="instructions"></textarea></label>
        <div class="academy-form-grid"><label>Pass Score<input type="number" name="pass_score" value="70"></label><label>Max Attempts<input type="number" name="max_attempts" value="3"></label></div>
        <button type="submit">Save Assessment</button>
      </form>
      <hr>
      <form method="post">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save_question">
        <h2>Add Question</h2>
        <label>Assessment<select name="assessment_id"><?php foreach ($assessments as $assessment): ?><option value="<?= (int) $assessment['id'] ?>"><?= e((string) $assessment['course_title']) ?> - <?= e((string) $assessment['title']) ?></option><?php endforeach; ?></select></label>
        <label>Question<textarea name="question_text" required></textarea></label>
        <div class="academy-form-grid"><label>A<input name="option_a" required></label><label>B<input name="option_b" required></label><label>C<input name="option_c"></label><label>D<input name="option_d"></label><label>Correct<select name="correct_option"><option>A</option><option>B</option><option>C</option><option>D</option></select></label><label>Points<input type="number" name="points" value="1"></label></div>
        <button type="submit">Save Question</button>
      </form>
    </div>
    <table><tr><th>Course</th><th>Assessment</th><th>Pass</th><th>Questions</th></tr><?php foreach ($assessments as $assessment): ?><tr><td><?= e((string) $assessment['course_title']) ?></td><td><?= e((string) $assessment['title']) ?></td><td><?= e((string) $assessment['pass_score']) ?>%</td><td><?= (int) $assessment['questions'] ?></td></tr><?php endforeach; ?><?php if (!$assessments): ?><tr><td colspan="4">No assessments yet.</td></tr><?php endif; ?></table>
  </section>
<?php endif; ?>

<?php if ($tab === 'calendar'): ?>
  <section class="academy-split">
    <form class="panel" method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="save_cohort">
      <h2>Create Calendar / Cohort Session</h2>
      <label>Course<select name="webinar_id" required><?php foreach ($courses as $course): ?><option value="<?= (int) $course['id'] ?>"><?= e((string) $course['title']) ?></option><?php endforeach; ?></select></label>
      <label>Instructor<select name="instructor_id"><option value="">Unassigned</option><?php foreach ($instructors as $instructor): ?><option value="<?= (int) $instructor['id'] ?>"><?= e((string) $instructor['name']) ?></option><?php endforeach; ?></select></label>
      <label>Session / Cohort Title<input name="title" required placeholder="June cohort live orientation"></label>
      <div class="academy-form-grid">
        <label>Start<input type="datetime-local" name="start_at" value="<?= e(date('Y-m-d\TH:i', strtotime('+7 days 10:00'))) ?>" required></label>
        <label>End<input type="datetime-local" name="end_at"></label>
        <label>Capacity<input type="number" name="capacity" value="100"></label>
        <label>Status<select name="status"><option value="scheduled">Scheduled</option><option value="open">Open</option><option value="completed">Completed</option><option value="cancelled">Cancelled</option></select></label>
      </div>
      <label>Venue<input name="venue" placeholder="Physical venue or state office"></label>
      <label>Meeting URL<input name="meeting_url" placeholder="Zoom/Meet/Teams/WhatsApp link"></label>
      <label>Notes<textarea name="notes"></textarea></label>
      <button type="submit">Save Cohort</button>
    </form>
    <table><tr><th>Date</th><th>Course</th><th>Cohort</th><th>Instructor</th><th>Attendance</th><th>Status</th></tr><?php foreach ($cohorts as $cohort): ?><tr><td><?= e((string) $cohort['start_at']) ?></td><td><?= e((string) $cohort['course_title']) ?></td><td><strong><?= e((string) $cohort['title']) ?></strong><br><small><?= e((string) ($cohort['venue'] ?: $cohort['meeting_url'] ?: 'No venue/link set')) ?></small></td><td><?= e((string) ($cohort['instructor_name'] ?? 'Unassigned')) ?></td><td><?= (int) $cohort['attendance_marked'] ?>/<?= (int) $cohort['enrolled'] ?></td><td><?= e((string) $cohort['status']) ?></td></tr><?php endforeach; ?><?php if (!$cohorts): ?><tr><td colspan="6">No calendar/cohort sessions yet.</td></tr><?php endif; ?></table>
  </section>
<?php endif; ?>

<?php if ($tab === 'instructors'): ?>
  <section class="academy-split">
    <form class="panel" method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="save_instructor">
      <h2>Add Instructor / Facilitator</h2>
      <label>Name<input name="name" required></label>
      <label>Email<input type="email" name="email"></label>
      <label>Phone<input name="phone"></label>
      <label>Specialty<input name="specialty" placeholder="Field verification, provider compliance..."></label>
      <label>Bio<textarea name="bio"></textarea></label>
      <label>Status<select name="status"><option value="active">Active</option><option value="paused">Paused</option><option value="archived">Archived</option></select></label>
      <button type="submit">Save Instructor</button>
    </form>
    <table><tr><th>Name</th><th>Specialty</th><th>Contact</th><th>Status</th></tr><?php foreach ($instructors as $instructor): ?><tr><td><strong><?= e((string) $instructor['name']) ?></strong><br><small><?= e((string) $instructor['bio']) ?></small></td><td><?= e((string) $instructor['specialty']) ?></td><td><?= e((string) $instructor['email']) ?><br><?= e((string) $instructor['phone']) ?></td><td><?= e((string) $instructor['status']) ?></td></tr><?php endforeach; ?><?php if (!$instructors): ?><tr><td colspan="4">No instructors yet.</td></tr><?php endif; ?></table>
  </section>
<?php endif; ?>

<?php if ($tab === 'attendance'): ?>
  <section class="academy-split">
    <form class="panel" method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="mark_attendance">
      <h2>Mark Attendance</h2>
      <label>Cohort<select name="cohort_id" required><?php foreach ($cohorts as $cohort): ?><option value="<?= (int) $cohort['id'] ?>"><?= e((string) $cohort['course_title']) ?> - <?= e((string) $cohort['title']) ?></option><?php endforeach; ?></select></label>
      <label>Enrolled User<select name="user_id" required><?php foreach ($enrollments as $row): ?><option value="<?= (int) $row['user_id'] ?>"><?= e((string) $row['user_name']) ?> - <?= e((string) $row['course_title']) ?></option><?php endforeach; ?></select></label>
      <label>Status<select name="status"><option value="present">Present</option><option value="late">Late</option><option value="absent">Absent</option><option value="excused">Excused</option></select></label>
      <label>Notes<textarea name="notes"></textarea></label>
      <button type="submit">Save Attendance</button>
    </form>
    <table><tr><th>Marked</th><th>User</th><th>Course/Cohort</th><th>Status</th><th>Notes</th></tr><?php foreach ($attendanceRows as $row): ?><tr><td><?= e((string) $row['marked_at']) ?></td><td><?= e((string) $row['user_name']) ?><br><small><?= e((string) $row['email']) ?></small></td><td><?= e((string) $row['course_title']) ?><br><small><?= e((string) $row['cohort_title']) ?></small></td><td><?= e((string) $row['status']) ?></td><td><?= e((string) $row['notes']) ?></td></tr><?php endforeach; ?><?php if (!$attendanceRows): ?><tr><td colspan="5">No attendance has been marked yet.</td></tr><?php endif; ?></table>
  </section>
<?php endif; ?>

<?php if ($tab === 'reminders'): ?>
  <section class="academy-split">
    <form class="panel" method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="save_reminder">
      <h2>Create Reminder</h2>
      <label>Course<select name="webinar_id"><option value="">Any course</option><?php foreach ($courses as $course): ?><option value="<?= (int) $course['id'] ?>"><?= e((string) $course['title']) ?></option><?php endforeach; ?></select></label>
      <label>Cohort<select name="cohort_id"><option value="">No cohort</option><?php foreach ($cohorts as $cohort): ?><option value="<?= (int) $cohort['id'] ?>"><?= e((string) $cohort['title']) ?></option><?php endforeach; ?></select></label>
      <label>Title<input name="title" required></label>
      <label>Message<textarea name="message" required></textarea></label>
      <div class="academy-form-grid">
        <label>Channel<select name="channel"><option value="dashboard">Dashboard</option><option value="email">Email</option><option value="sms">SMS</option><option value="whatsapp">WhatsApp</option></select></label>
        <label>Send At<input type="datetime-local" name="send_at"></label>
        <label>Status<select name="status"><option value="draft">Draft</option><option value="scheduled">Scheduled</option><option value="sent">Mark Sent</option><option value="cancelled">Cancelled</option></select></label>
      </div>
      <fieldset><legend>Audience Roles</legend><div class="academy-pillbox"><?php foreach ($roles as $role): ?><label><input type="checkbox" name="audience_roles[]" value="<?= e($role) ?>"> <?= e(academy_role_label($role)) ?></label><?php endforeach; ?></div></fieldset>
      <button type="submit">Save Reminder</button>
    </form>
    <table><tr><th>When</th><th>Reminder</th><th>Course/Cohort</th><th>Audience</th><th>Status</th></tr><?php foreach ($reminders as $reminder): ?><tr><td><?= e((string) ($reminder['send_at'] ?? $reminder['created_at'])) ?></td><td><strong><?= e((string) $reminder['title']) ?></strong><br><small><?= e((string) $reminder['message']) ?></small></td><td><?= e((string) ($reminder['course_title'] ?? 'Any course')) ?><br><small><?= e((string) ($reminder['cohort_title'] ?? '')) ?></small></td><td><?= e(academy_role_labels((string) $reminder['audience_roles'])) ?></td><td><?= e((string) $reminder['channel']) ?> / <?= e((string) $reminder['status']) ?></td></tr><?php endforeach; ?><?php if (!$reminders): ?><tr><td colspan="5">No reminders yet.</td></tr><?php endif; ?></table>
  </section>
<?php endif; ?>

<?php if ($tab === 'feedback'): ?>
  <table><tr><th>Date</th><th>User</th><th>Course</th><th>Rating</th><th>Comment</th></tr><?php foreach ($feedbackRows as $row): ?><tr><td><?= e((string) $row['created_at']) ?></td><td><?= e((string) $row['user_name']) ?><br><small><?= e((string) $row['email']) ?></small></td><td><?= e((string) $row['course_title']) ?></td><td><?= (int) $row['rating'] ?>/5</td><td><?= e((string) $row['comment']) ?></td></tr><?php endforeach; ?><?php if (!$feedbackRows): ?><tr><td colspan="5">No learner feedback yet.</td></tr><?php endif; ?></table>
<?php endif; ?>

<?php if ($tab === 'enrollments'): ?>
  <table><tr><th>User</th><th>Course</th><th>Payment</th><th>Progress</th><th>Registered</th></tr><?php foreach ($enrollments as $row): ?><tr><td><strong><?= e((string) $row['user_name']) ?></strong><br><small><?= e((string) $row['email']) ?></small></td><td><?= e((string) $row['course_title']) ?><br><small><?= e((string) ($row['program_title'] ?? '')) ?></small></td><td><?= e((string) $row['payment_status']) ?></td><td><?= (int) $row['progress_percent'] ?>% / <?= e((string) $row['completion_status']) ?></td><td><?= e((string) $row['registered_at']) ?></td></tr><?php endforeach; ?></table>
<?php endif; ?>

<?php if ($tab === 'certificates'): ?>
  <table><tr><th>User</th><th>Certificate</th><th>Reference</th><th>Status</th><th>Review</th></tr><?php foreach ($certificates as $cert): ?><tr><td><?= e((string) $cert['user_name']) ?><br><small><?= e((string) $cert['email']) ?></small></td><td><?= e((string) $cert['course_title']) ?><br><small><?= e(ucfirst((string) $cert['certificate_kind'])) ?> certificate</small></td><td><?= e((string) $cert['certificate_ref']) ?></td><td><?= e((string) $cert['status']) ?></td><td><?php if ((string) $cert['status'] === 'issued'): ?><span class="badge verified">Permanent</span><?php else: ?><form method="post" class="actions"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="review_certificate"><input type="hidden" name="certificate_kind" value="<?= e((string) $cert['certificate_kind']) ?>"><input type="hidden" name="certificate_id" value="<?= (int) $cert['id'] ?>"><select name="status"><option value="pending">Pending</option><option value="issued">Issued</option><option value="rejected">Rejected</option></select><input name="notes" placeholder="Notes"><button>Save</button></form><?php endif; ?></td></tr><?php endforeach; ?><?php if (!$certificates): ?><tr><td colspan="5">No certificate requests yet.</td></tr><?php endif; ?></table>
<?php endif; ?>

<?php if ($tab === 'refunds'): ?>
  <table><tr><th>User</th><th>Course</th><th>Amount</th><th>Reason</th><th>Status</th><th>Review</th></tr><?php foreach ($refunds as $refund): ?><tr><td><?= e((string) $refund['user_name']) ?><br><small><?= e((string) $refund['email']) ?></small></td><td><?= e((string) $refund['course_title']) ?></td><td>NGN <?= e(number_format((float) $refund['amount'], 2)) ?></td><td><?= e((string) $refund['reason']) ?></td><td><?= e((string) $refund['status']) ?></td><td><form method="post" class="actions"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="review_refund"><input type="hidden" name="refund_id" value="<?= (int) $refund['id'] ?>"><select name="status"><option value="pending">Pending</option><option value="approved">Approved</option><option value="rejected">Rejected</option><option value="paid">Paid</option><option value="closed">Closed</option></select><input name="admin_notes" placeholder="Notes"><button>Save</button></form></td></tr><?php endforeach; ?><?php if (!$refunds): ?><tr><td colspan="6">No Academy refund requests yet.</td></tr><?php endif; ?></table>
<?php endif; ?>

<?php if ($tab === 'reports'): ?>
  <section class="stats">
    <div class="stat"><span>Enrollments</span><div class="metric"><?= (int) $stats['enrollments'] ?></div></div>
    <div class="stat"><span>Completed</span><div class="metric"><?= (int) $stats['completed'] ?></div></div>
    <div class="stat"><span>Cohorts</span><div class="metric"><?= (int) $stats['cohorts'] ?></div></div>
    <div class="stat"><span>Attendance</span><div class="metric"><?= (int) $stats['attendance'] ?></div></div>
    <div class="stat"><span>Feedback</span><div class="metric"><?= (int) $stats['feedback'] ?></div></div>
  </section>
  <section class="grid">
    <?php foreach ($programs as $program): ?>
      <?php $programCourses = array_values(array_filter($courses, static fn(array $course): bool => (int) ($course['program_id'] ?? 0) === (int) $program['id'])); ?>
      <article class="card"><h2><?= e((string) $program['title']) ?></h2><p class="metric"><?= count($programCourses) ?></p><p class="muted">courses in this Academy program</p></article>
    <?php endforeach; ?>
  </section>
  <section class="card" style="margin-top:16px;">
    <h2>Completion By Role</h2>
    <table><tr><th>Role</th><th>Enrollments</th><th>Completed</th><th>Average Progress</th></tr><?php foreach ($completionByRole as $row): ?><tr><td><?= e(academy_role_label((string) $row['user_role'])) ?></td><td><?= (int) $row['enrollments'] ?></td><td><?= (int) $row['completed'] ?></td><td><?= e((string) $row['avg_progress']) ?>%</td></tr><?php endforeach; ?><?php if (!$completionByRole): ?><tr><td colspan="4">No enrollment report data yet.</td></tr><?php endif; ?></table>
  </section>
  <section class="card" style="margin-top:16px;">
    <h2>Course Intelligence</h2>
    <table><tr><th>Course</th><th>Enrollments</th><th>Paid</th><th>Completed</th><th>Attempts</th><th>Avg Score</th><th>Rating</th></tr><?php foreach ($courseReport as $row): ?><tr><td><?= e((string) $row['title']) ?></td><td><?= (int) $row['enrollments'] ?></td><td><?= (int) $row['paid_enrollments'] ?></td><td><?= (int) $row['completed'] ?></td><td><?= (int) $row['attempts'] ?></td><td><?= e((string) ($row['avg_score'] ?? '0')) ?>%</td><td><?= e((string) ($row['avg_rating'] ?? 'No rating')) ?></td></tr><?php endforeach; ?></table>
  </section>
<?php endif; ?>

  </main>
</div>
<?php admin_page_end(); ?>
