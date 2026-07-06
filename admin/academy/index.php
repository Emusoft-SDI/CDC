<?php
declare(strict_types=1);

require_once __DIR__ . '/../_auth.php';
require_once __DIR__ . '/../../lib/admin-layout.php';
require_once __DIR__ . '/../../lib/admin-operator-strip.php';
require_once __DIR__ . '/../../lib/academy.php';

$pdo = db();
admin_ensure_schema($pdo);
academy_ensure_schema($pdo);
admin_require($pdo, 'training');

defined('ACADEMY_ADMIN_ROUTE_BASE') || define('ACADEMY_ADMIN_ROUTE_BASE', 'index.php');

$message = '';
$error = '';
$tab = str_replace('-', '_', preg_replace('/[^a-z_-]/', '', (string) ($_GET['page'] ?? $_GET['tab'] ?? 'overview')) ?: 'overview');
$allowedTabs = ['overview', 'programs', 'courses', 'certificate_groups', 'lessons', 'assessments', 'calendar', 'instructors', 'attendance', 'reminders', 'feedback', 'enrollments', 'certificates', 'refunds', 'reports'];
if (!in_array($tab, $allowedTabs, true)) {
    $tab = 'overview';
}

function academy_admin_redirect(string $tab, string $key, string $message): void
{
    $pages = [
        'overview' => ACADEMY_ADMIN_ROUTE_BASE . '?page=overview',
        'programs' => ACADEMY_ADMIN_ROUTE_BASE . '?page=programs',
        'courses' => ACADEMY_ADMIN_ROUTE_BASE . '?page=courses',
        'certificate_groups' => ACADEMY_ADMIN_ROUTE_BASE . '?page=certificate-groups',
        'lessons' => ACADEMY_ADMIN_ROUTE_BASE . '?page=lessons',
        'assessments' => ACADEMY_ADMIN_ROUTE_BASE . '?page=assessments',
        'calendar' => ACADEMY_ADMIN_ROUTE_BASE . '?page=calendar',
        'instructors' => ACADEMY_ADMIN_ROUTE_BASE . '?page=instructors',
        'attendance' => ACADEMY_ADMIN_ROUTE_BASE . '?page=attendance',
        'reminders' => ACADEMY_ADMIN_ROUTE_BASE . '?page=reminders',
        'feedback' => ACADEMY_ADMIN_ROUTE_BASE . '?page=feedback',
        'enrollments' => ACADEMY_ADMIN_ROUTE_BASE . '?page=enrollments',
        'certificates' => ACADEMY_ADMIN_ROUTE_BASE . '?page=certificates',
        'refunds' => ACADEMY_ADMIN_ROUTE_BASE . '?page=refunds',
        'reports' => ACADEMY_ADMIN_ROUTE_BASE . '?page=reports',
    ];
    redirect_to(($pages[$tab] ?? (ACADEMY_ADMIN_ROUTE_BASE . '?page=overview')) . '&' . http_build_query([$key => $message]));
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
define('NATCODEV_ACADEMY_WORKSPACE_VIEW', true);
require __DIR__ . '/views/workspace.php';
