<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/admin-layout.php';
require_once __DIR__ . '/../../lib/academy.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$pdo = db();
admin_ensure_schema($pdo);
academy_ensure_schema($pdo);
admin_require($pdo);

$user = current_user($pdo) ?: [];
$adminName = (string) ($user['name'] ?? 'Admin User');
$adminRole = ucwords(str_replace('_', ' ', admin_current_platform_role($pdo) ?? 'admin'));
$adminInitials = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $adminName) ?: 'AU', 0, 2));
$academyScriptDir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/admin/academy.php')));
$academyAdminBase = basename($academyScriptDir) === 'acad' ? dirname($academyScriptDir) : $academyScriptDir;
$academyAdminBase = rtrim($academyAdminBase, '/') ?: '/admin';
$academyPublicBase = preg_replace('#/admin$#', '', $academyAdminBase) ?: '';
$adminPicture = ltrim((string) ($user['profile_picture'] ?? ''), '/');
$adminPictureUrl = $adminPicture !== '' ? $academyPublicBase . '/' . $adminPicture : '';
$activePage = preg_replace('/[^a-z_]/', '', (string) ($_GET['page'] ?? 'dashboard')) ?: 'dashboard';
$validPages = ['dashboard','programs','courses','lessons','materials','assessments','questions','cohorts','instructors','attendance','reminders','learners','progress','attempts','certificates','pathways','refunds','feedback','reports'];
if (!in_array($activePage, $validPages, true)) {
    $activePage = 'dashboard';
}

function acad_count(PDO $pdo, string $sql): int
{
    try {
        return (int) $pdo->query($sql)->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function acad_money(float $amount): string
{
    return 'NGN ' . number_format($amount, 2);
}

function acad_initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    $initials = '';
    foreach ($parts ?: [] as $part) {
        $initials .= strtoupper(substr($part, 0, 1));
        if (strlen($initials) >= 2) {
            break;
        }
    }
    return $initials !== '' ? $initials : 'NA';
}

function acad_status_class(string $status): string
{
    return match ($status) {
        'active', 'paid', 'successful', 'issued', 'present', 'completed', 'approved' => 'status-active',
        'pending', 'registered', 'scheduled', 'processing', 'under_review', 'draft' => 'status-pending',
        'rejected', 'failed', 'cancelled', 'absent', 'archived' => 'status-cancelled',
        default => 'status-completed',
    };
}

function acad_post_string(string $key, string $default = ''): string
{
    return trim((string) ($_POST[$key] ?? $default));
}

function acad_post_int(string $key, int $default = 0): int
{
    return max(0, (int) ($_POST[$key] ?? $default));
}

function acad_admin_redirect(string $page, string $message, bool $error = false): void
{
    $target = (string) ($_SERVER['PHP_SELF'] ?? 'academy.php');
    $query = ['page' => $page, $error ? 'error' : 'message' => $message];
    redirect_to($target . '?' . http_build_query($query));
}

function acad_find_or_create_learner(PDO $pdo, string $name, string $email): int
{
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $existing = $stmt->fetchColumn();
    if ($existing !== false) {
        return (int) $existing;
    }

    $password = password_hash(bin2hex(random_bytes(12)), PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (email, password, name, role) VALUES (?, ?, ?, 'learner')");
    $stmt->execute([$email, $password, $name]);
    return (int) $pdo->lastInsertId();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = acad_post_string('action');
    $page = preg_replace('/[^a-z_]/', '', acad_post_string('page', $activePage)) ?: 'dashboard';

    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        acad_admin_redirect($page, 'Your session expired. Please retry the action.', true);
    }

    try {
        switch ($action) {
            case 'create_program':
                $title = acad_post_string('title');
                if ($title === '') {
                    throw new RuntimeException('Program name is required.');
                }
                $stmt = $pdo->prepare("
                    INSERT INTO academy_programs (title, description, audience_roles, status, sort_order)
                    VALUES (?, ?, ?, 'active', ?)
                    ON DUPLICATE KEY UPDATE description = VALUES(description), audience_roles = VALUES(audience_roles), status = 'active', sort_order = VALUES(sort_order)
                ");
                $stmt->execute([$title, acad_post_string('description'), acad_post_string('audience_roles', 'all'), acad_post_int('sort_order')]);
                acad_admin_redirect('programs', 'Program saved.');

            case 'create_course':
                $title = acad_post_string('title');
                if ($title === '') {
                    throw new RuntimeException('Course title is required.');
                }
                $durationMinutes = max(30, acad_post_int('duration_hours', 1) * 60);
                $startTime = acad_post_string('start_time') ?: date('Y-m-d H:i:s', strtotime('+7 days'));
                $startTime = str_replace('T', ' ', $startTime);
                if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $startTime)) {
                    $startTime .= ':00';
                }
                $stmt = $pdo->prepare("
                    INSERT INTO webinars
                        (program_id, title, description, start_time, duration_minutes, is_free, price, max_attendees, category, target_roles, certification_required, pass_score, instructor_name, delivery_type, delivery_url, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Academy', ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE program_id = VALUES(program_id), description = VALUES(description), duration_minutes = VALUES(duration_minutes), is_free = VALUES(is_free), price = VALUES(price), max_attendees = VALUES(max_attendees), target_roles = VALUES(target_roles), certification_required = VALUES(certification_required), pass_score = VALUES(pass_score), instructor_name = VALUES(instructor_name), delivery_type = VALUES(delivery_type), delivery_url = VALUES(delivery_url), status = VALUES(status)
                ");
                $price = (float) ($_POST['price'] ?? 0);
                $stmt->execute([
                    acad_post_int('program_id') ?: null,
                    $title,
                    acad_post_string('description'),
                    $startTime,
                    $durationMinutes,
                    $price <= 0 ? 1 : 0,
                    $price,
                    acad_post_int('max_attendees', 100) ?: 100,
                    acad_post_string('target_roles', 'all'),
                    isset($_POST['certification_required']) ? 1 : 0,
                    (float) ($_POST['pass_score'] ?? 70),
                    acad_post_string('instructor_name'),
                    acad_post_string('delivery_type', 'lms'),
                    acad_post_string('delivery_url'),
                    acad_post_string('status', 'active'),
                ]);
                acad_admin_redirect('courses', 'Course saved.');

            case 'create_lesson':
                $courseId = acad_post_int('webinar_id');
                $title = acad_post_string('title');
                if ($courseId <= 0 || $title === '') {
                    throw new RuntimeException('Course and lesson title are required.');
                }
                $stmt = $pdo->prepare("
                    INSERT INTO academy_lessons (webinar_id, title, summary, content, delivery_type, material_url, duration_minutes, sort_order, is_required, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 'active')
                    ON DUPLICATE KEY UPDATE summary = VALUES(summary), content = VALUES(content), delivery_type = VALUES(delivery_type), material_url = VALUES(material_url), duration_minutes = VALUES(duration_minutes), sort_order = VALUES(sort_order), status = 'active'
                ");
                $stmt->execute([$courseId, $title, acad_post_string('summary'), acad_post_string('content'), acad_post_string('delivery_type', 'document'), acad_post_string('material_url'), acad_post_int('duration_minutes', 20) ?: 20, acad_post_int('sort_order')]);
                acad_admin_redirect('lessons', 'Lesson saved.');

            case 'create_assessment':
                $courseId = acad_post_int('webinar_id');
                $title = acad_post_string('title');
                if ($courseId <= 0 || $title === '') {
                    throw new RuntimeException('Course and assessment title are required.');
                }
                $stmt = $pdo->prepare("
                    INSERT INTO academy_assessments (webinar_id, title, instructions, pass_score, max_attempts, status)
                    VALUES (?, ?, ?, ?, ?, 'active')
                    ON DUPLICATE KEY UPDATE instructions = VALUES(instructions), pass_score = VALUES(pass_score), max_attempts = VALUES(max_attempts), status = 'active'
                ");
                $stmt->execute([$courseId, $title, acad_post_string('instructions'), (float) ($_POST['pass_score'] ?? 70), acad_post_int('max_attempts', 3) ?: 3]);
                acad_admin_redirect('assessments', 'Assessment saved.');

            case 'create_material':
                $courseId = acad_post_int('webinar_id');
                $title = acad_post_string('title');
                if ($courseId <= 0 || $title === '') {
                    throw new RuntimeException('Course and material title are required.');
                }
                $stmt = $pdo->prepare("
                    INSERT INTO academy_materials (webinar_id, lesson_id, title, material_type, material_url, file_path, notes, sort_order, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')
                    ON DUPLICATE KEY UPDATE lesson_id = VALUES(lesson_id), material_type = VALUES(material_type), material_url = VALUES(material_url), file_path = VALUES(file_path), notes = VALUES(notes), sort_order = VALUES(sort_order), status = 'active'
                ");
                $stmt->execute([$courseId, acad_post_int('lesson_id') ?: null, $title, acad_post_string('material_type', 'link'), acad_post_string('material_url'), acad_post_string('file_path'), acad_post_string('notes'), acad_post_int('sort_order')]);
                acad_admin_redirect('materials', 'Material saved.');

            case 'create_question':
                $assessmentId = acad_post_int('assessment_id');
                $question = acad_post_string('question_text');
                if ($assessmentId <= 0 || $question === '') {
                    throw new RuntimeException('Assessment and question text are required.');
                }
                $stmt = $pdo->prepare("
                    INSERT INTO academy_questions (assessment_id, question_text, option_a, option_b, option_c, option_d, correct_option, points, sort_order, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
                    ON DUPLICATE KEY UPDATE option_a = VALUES(option_a), option_b = VALUES(option_b), option_c = VALUES(option_c), option_d = VALUES(option_d), correct_option = VALUES(correct_option), points = VALUES(points), sort_order = VALUES(sort_order), status = 'active'
                ");
                $stmt->execute([$assessmentId, $question, acad_post_string('option_a'), acad_post_string('option_b'), acad_post_string('option_c'), acad_post_string('option_d'), strtoupper(substr(acad_post_string('correct_option', 'A'), 0, 1)), (float) ($_POST['points'] ?? 1), acad_post_int('sort_order')]);
                acad_admin_redirect('questions', 'Question saved.');

            case 'create_cohort':
                $courseId = acad_post_int('webinar_id');
                $title = acad_post_string('title');
                $startDate = acad_post_string('start_date');
                if ($courseId <= 0 || $title === '' || $startDate === '') {
                    throw new RuntimeException('Course, cohort name, and start date are required.');
                }
                $startAt = $startDate . ' ' . (acad_post_string('start_time', '09:00') ?: '09:00') . ':00';
                $endAt = acad_post_string('end_date') !== '' ? acad_post_string('end_date') . ' 17:00:00' : null;
                $stmt = $pdo->prepare("
                    INSERT INTO academy_cohorts (webinar_id, instructor_id, title, start_at, end_at, venue, meeting_url, capacity, status, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'scheduled', ?)
                ");
                $stmt->execute([$courseId, acad_post_int('instructor_id') ?: null, $title, $startAt, $endAt, acad_post_string('venue'), acad_post_string('meeting_url'), acad_post_int('capacity', 100) ?: 100, acad_post_string('notes')]);
                acad_admin_redirect('cohorts', 'Cohort scheduled.');

            case 'create_instructor':
                $name = trim(acad_post_string('first_name') . ' ' . acad_post_string('last_name'));
                if ($name === '') {
                    $name = acad_post_string('name');
                }
                if ($name === '') {
                    throw new RuntimeException('Instructor name is required.');
                }
                $stmt = $pdo->prepare("INSERT INTO academy_instructors (name, email, phone, specialty, bio, status) VALUES (?, ?, ?, ?, ?, 'active')");
                $stmt->execute([$name, acad_post_string('email'), acad_post_string('phone'), acad_post_string('specialty'), acad_post_string('bio')]);
                acad_admin_redirect('instructors', 'Instructor added.');

            case 'create_reminder':
                $title = acad_post_string('title');
                $message = acad_post_string('message');
                if ($title === '' || $message === '') {
                    throw new RuntimeException('Reminder title and message are required.');
                }
                $sendAt = acad_post_string('send_date') !== '' ? acad_post_string('send_date') . ' ' . (acad_post_string('send_time', '09:00') ?: '09:00') . ':00' : null;
                $stmt = $pdo->prepare("INSERT INTO academy_reminders (webinar_id, cohort_id, audience_roles, title, message, channel, send_at, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, 'scheduled', ?)");
                $stmt->execute([acad_post_int('webinar_id') ?: null, acad_post_int('cohort_id') ?: null, acad_post_string('audience_roles', 'all'), $title, $message, acad_post_string('channel', 'dashboard'), $sendAt, (int) ($user['id'] ?? 0) ?: null]);
                acad_admin_redirect('reminders', 'Reminder scheduled.');

            case 'mark_attendance':
                $cohortId = acad_post_int('cohort_id');
                $learnerId = acad_post_int('user_id');
                if ($cohortId <= 0 || $learnerId <= 0) {
                    throw new RuntimeException('Cohort and learner are required.');
                }
                $cohortStmt = $pdo->prepare("SELECT webinar_id FROM academy_cohorts WHERE id = ? LIMIT 1");
                $cohortStmt->execute([$cohortId]);
                $courseId = (int) $cohortStmt->fetchColumn();
                if ($courseId <= 0) {
                    throw new RuntimeException('Cohort record was not found.');
                }
                $stmt = $pdo->prepare("
                    INSERT INTO academy_attendance (cohort_id, webinar_id, user_id, status, marked_by, marked_at, notes)
                    VALUES (?, ?, ?, ?, ?, NOW(), ?)
                    ON DUPLICATE KEY UPDATE status = VALUES(status), marked_by = VALUES(marked_by), marked_at = NOW(), notes = VALUES(notes)
                ");
                $stmt->execute([$cohortId, $courseId, $learnerId, acad_post_string('status', 'present'), (int) ($user['id'] ?? 0) ?: null, acad_post_string('notes')]);
                acad_admin_redirect('attendance', 'Attendance marked.');

            case 'enroll_learner':
                $name = trim(acad_post_string('first_name') . ' ' . acad_post_string('last_name'));
                $email = acad_post_string('email');
                $courseId = acad_post_int('webinar_id');
                if ($name === '' || $email === '' || $courseId <= 0) {
                    throw new RuntimeException('Learner name, email, and course are required.');
                }
                $learnerId = acad_find_or_create_learner($pdo, $name, $email);
                $stmt = $pdo->prepare("
                    INSERT INTO webinar_registrations (webinar_id, user_id, payment_status, progress_percent, completion_status, certificate_status)
                    VALUES (?, ?, 'admin_enrolled', 0, 'registered', 'not_required')
                    ON DUPLICATE KEY UPDATE payment_status = VALUES(payment_status)
                ");
                $stmt->execute([$courseId, $learnerId]);
                acad_admin_redirect('learners', 'Learner enrolled.');

            case 'issue_certificate':
                $userId = acad_post_int('user_id');
                $courseId = acad_post_int('webinar_id');
                if ($userId <= 0 || $courseId <= 0) {
                    throw new RuntimeException('Learner and course are required.');
                }
                $regStmt = $pdo->prepare("SELECT id FROM webinar_registrations WHERE user_id = ? AND webinar_id = ? LIMIT 1");
                $regStmt->execute([$userId, $courseId]);
                $registrationId = (int) $regStmt->fetchColumn();
                if ($registrationId <= 0) {
                    $pdo->prepare("INSERT IGNORE INTO webinar_registrations (webinar_id, user_id, payment_status, progress_percent, completion_status, certificate_status, completed_at) VALUES (?, ?, 'admin_enrolled', 100, 'completed', 'issued', NOW())")->execute([$courseId, $userId]);
                    $registrationId = (int) $pdo->lastInsertId();
                }
                $ref = acad_post_string('certificate_ref') ?: academy_certificate_ref($userId, $courseId);
                $issuedAt = acad_post_string('issued_at') !== '' ? acad_post_string('issued_at') . ' 00:00:00' : date('Y-m-d H:i:s');
                $stmt = $pdo->prepare("
                    INSERT INTO academy_certificates (user_id, webinar_id, registration_id, certificate_ref, status, issued_at, approved_by, notes)
                    VALUES (?, ?, ?, ?, 'issued', ?, ?, ?)
                    ON DUPLICATE KEY UPDATE status = 'issued', issued_at = VALUES(issued_at), approved_by = VALUES(approved_by), notes = VALUES(notes)
                ");
                $stmt->execute([$userId, $courseId, $registrationId ?: null, $ref, $issuedAt, (int) ($user['id'] ?? 0) ?: null, acad_post_string('notes')]);
                $pdo->prepare("UPDATE webinar_registrations SET progress_percent = 100, completion_status = 'completed', certificate_status = 'issued', completed_at = COALESCE(completed_at, NOW()) WHERE user_id = ? AND webinar_id = ?")->execute([$userId, $courseId]);
                acad_admin_redirect('certificates', 'Certificate issued.');

            case 'create_pathway':
                $title = acad_post_string('title');
                if ($title === '') {
                    throw new RuntimeException('Pathway name is required.');
                }
                $stmt = $pdo->prepare("
                    INSERT INTO academy_certificate_groups (title, description, audience_roles, certificate_approval_required, status, sort_order)
                    VALUES (?, ?, ?, ?, 'active', ?)
                    ON DUPLICATE KEY UPDATE description = VALUES(description), audience_roles = VALUES(audience_roles), certificate_approval_required = VALUES(certificate_approval_required), status = 'active', sort_order = VALUES(sort_order)
                ");
                $stmt->execute([$title, acad_post_string('description'), acad_post_string('audience_roles', 'all'), isset($_POST['certificate_approval_required']) ? 1 : 0, acad_post_int('sort_order')]);
                $groupId = (int) $pdo->lastInsertId();
                if ($groupId <= 0) {
                    $lookup = $pdo->prepare("SELECT id FROM academy_certificate_groups WHERE title = ? LIMIT 1");
                    $lookup->execute([$title]);
                    $groupId = (int) $lookup->fetchColumn();
                }
                $pdo->prepare("DELETE FROM academy_certificate_group_courses WHERE group_id = ?")->execute([$groupId]);
                $insertCourse = $pdo->prepare("INSERT INTO academy_certificate_group_courses (group_id, webinar_id, is_required, sort_order) VALUES (?, ?, 1, ?)");
                foreach ((array) ($_POST['webinar_ids'] ?? []) as $idx => $courseId) {
                    if ((int) $courseId > 0) {
                        $insertCourse->execute([$groupId, (int) $courseId, (int) $idx]);
                    }
                }
                acad_admin_redirect('pathways', 'Certificate pathway saved.');

            case 'create_refund':
                $userId = acad_post_int('user_id');
                $courseId = acad_post_int('webinar_id');
                if ($userId <= 0 || $courseId <= 0) {
                    throw new RuntimeException('Learner and course are required.');
                }
                $stmt = $pdo->prepare("INSERT INTO academy_refund_requests (user_id, webinar_id, amount, reason, status, admin_notes, reviewed_by) VALUES (?, ?, ?, ?, 'pending', ?, ?)");
                $stmt->execute([$userId, $courseId, (float) ($_POST['amount'] ?? 0), acad_post_string('reason'), acad_post_string('admin_notes'), (int) ($user['id'] ?? 0) ?: null]);
                acad_admin_redirect('refunds', 'Refund request submitted.');

            default:
                throw new RuntimeException('Unknown Academy action.');
        }
    } catch (Throwable $e) {
        acad_admin_redirect($page, $e->getMessage(), true);
    }
}

$programs = $pdo->query("SELECT * FROM academy_programs ORDER BY sort_order ASC, title ASC LIMIT 80")->fetchAll();
$courses = academy_courses($pdo, null, false);
$lessons = $pdo->query("SELECT l.*, w.title course_title FROM academy_lessons l JOIN webinars w ON w.id = l.webinar_id ORDER BY w.title ASC, l.sort_order ASC LIMIT 120")->fetchAll();
$materials = $pdo->query("SELECT m.*, w.title course_title, l.title lesson_title FROM academy_materials m JOIN webinars w ON w.id = m.webinar_id LEFT JOIN academy_lessons l ON l.id = m.lesson_id ORDER BY w.title ASC, m.sort_order ASC, m.id DESC LIMIT 160")->fetchAll();
$assessments = $pdo->query("SELECT a.*, w.title course_title, COUNT(q.id) questions FROM academy_assessments a JOIN webinars w ON w.id = a.webinar_id LEFT JOIN academy_questions q ON q.assessment_id = a.id GROUP BY a.id ORDER BY w.title ASC, a.id ASC LIMIT 120")->fetchAll();
$questions = $pdo->query("SELECT q.*, a.title assessment_title, w.title course_title FROM academy_questions q JOIN academy_assessments a ON a.id = q.assessment_id JOIN webinars w ON w.id = a.webinar_id ORDER BY w.title ASC, a.title ASC, q.sort_order ASC LIMIT 180")->fetchAll();
$cohorts = $pdo->query("SELECT c.*, w.title course_title, i.name instructor_name, COUNT(DISTINCT r.id) enrolled, COUNT(DISTINCT a.id) attendance_marked FROM academy_cohorts c JOIN webinars w ON w.id = c.webinar_id LEFT JOIN academy_instructors i ON i.id = c.instructor_id LEFT JOIN webinar_registrations r ON r.webinar_id = c.webinar_id LEFT JOIN academy_attendance a ON a.cohort_id = c.id GROUP BY c.id ORDER BY c.start_at DESC LIMIT 120")->fetchAll();
$instructors = $pdo->query("SELECT * FROM academy_instructors ORDER BY status ASC, name ASC LIMIT 120")->fetchAll();
$enrollments = $pdo->query("SELECT r.*, u.name user_name, u.email, w.title course_title, p.title program_title FROM webinar_registrations r JOIN users u ON u.id = r.user_id JOIN webinars w ON w.id = r.webinar_id LEFT JOIN academy_programs p ON p.id = w.program_id ORDER BY r.registered_at DESC LIMIT 120")->fetchAll();
$progressRows = $pdo->query("SELECT p.*, u.name user_name, u.email, w.title course_title, l.title lesson_title FROM academy_progress p JOIN users u ON u.id = p.user_id JOIN webinars w ON w.id = p.webinar_id LEFT JOIN academy_lessons l ON l.id = p.lesson_id ORDER BY p.updated_at DESC, p.created_at DESC LIMIT 160")->fetchAll();
$attemptRows = $pdo->query("SELECT at.*, u.name user_name, u.email, w.title course_title, a.title assessment_title FROM academy_attempts at JOIN users u ON u.id = at.user_id JOIN webinars w ON w.id = at.webinar_id JOIN academy_assessments a ON a.id = at.assessment_id ORDER BY at.completed_at DESC LIMIT 160")->fetchAll();
$certificates = $pdo->query("SELECT c.id, c.user_id, c.certificate_ref, c.status, c.requested_at, c.issued_at, c.notes, 'course' certificate_kind, u.name user_name, u.email, w.title course_title FROM academy_certificates c JOIN users u ON u.id = c.user_id JOIN webinars w ON w.id = c.webinar_id UNION ALL SELECT gc.id, gc.user_id, gc.certificate_ref, gc.status, gc.requested_at, gc.issued_at, gc.notes, 'group' certificate_kind, u.name user_name, u.email, g.title course_title FROM academy_group_certificates gc JOIN users u ON u.id = gc.user_id JOIN academy_certificate_groups g ON g.id = gc.group_id ORDER BY requested_at DESC LIMIT 120")->fetchAll();
$certificateGroups = academy_certificate_groups($pdo, null, false);
$refunds = $pdo->query("SELECT rr.*, u.name user_name, u.email, w.title course_title FROM academy_refund_requests rr JOIN users u ON u.id = rr.user_id JOIN webinars w ON w.id = rr.webinar_id ORDER BY rr.requested_at DESC LIMIT 120")->fetchAll();
$attendanceRows = $pdo->query("SELECT a.*, c.title cohort_title, w.title course_title, u.name user_name, u.email FROM academy_attendance a JOIN academy_cohorts c ON c.id = a.cohort_id JOIN webinars w ON w.id = a.webinar_id JOIN users u ON u.id = a.user_id ORDER BY a.marked_at DESC LIMIT 120")->fetchAll();
$reminders = $pdo->query("SELECT r.*, w.title course_title, c.title cohort_title FROM academy_reminders r LEFT JOIN webinars w ON w.id = r.webinar_id LEFT JOIN academy_cohorts c ON c.id = r.cohort_id ORDER BY COALESCE(r.send_at, r.created_at) DESC LIMIT 120")->fetchAll();
$feedbackRows = $pdo->query("SELECT f.*, u.name user_name, u.email, w.title course_title FROM academy_feedback f JOIN users u ON u.id = f.user_id JOIN webinars w ON w.id = f.webinar_id ORDER BY f.created_at DESC LIMIT 120")->fetchAll();
$courseReport = $pdo->query("SELECT w.title, COUNT(DISTINCT r.id) enrollments, SUM(r.payment_status = 'paid') paid_enrollments, SUM(r.completion_status = 'completed') completed, COUNT(DISTINCT at.id) attempts, ROUND(AVG(at.score_percent), 1) avg_score, ROUND(AVG(f.rating), 1) avg_rating FROM webinars w LEFT JOIN webinar_registrations r ON r.webinar_id = w.id LEFT JOIN academy_attempts at ON at.webinar_id = w.id LEFT JOIN academy_feedback f ON f.webinar_id = w.id GROUP BY w.id ORDER BY enrollments DESC, w.title ASC LIMIT 120")->fetchAll();
$academyLearners = $pdo->query("SELECT DISTINCT u.id, u.name, u.email FROM users u LEFT JOIN webinar_registrations r ON r.user_id = u.id WHERE u.role IN ('learner','grower','provider','seller','admin') OR r.id IS NOT NULL ORDER BY u.name ASC LIMIT 300")->fetchAll();
$flashMessage = trim((string) ($_GET['message'] ?? ''));
$flashError = trim((string) ($_GET['error'] ?? ''));
$csrf = csrf_token();

$stats = [
    'programs' => count($programs),
    'courses' => count($courses),
    'active_courses' => acad_count($pdo, "SELECT COUNT(*) FROM webinars WHERE status = 'active'"),
    'lessons' => acad_count($pdo, "SELECT COUNT(*) FROM academy_lessons"),
    'materials' => count($materials),
    'assessments' => count($assessments),
    'questions' => count($questions),
    'cohorts' => acad_count($pdo, "SELECT COUNT(*) FROM academy_cohorts"),
    'instructors' => count($instructors),
    'attendance' => acad_count($pdo, "SELECT COUNT(*) FROM academy_attendance"),
    'reminders' => count($reminders),
    'enrollments' => acad_count($pdo, "SELECT COUNT(*) FROM webinar_registrations"),
    'progress' => count($progressRows),
    'attempts' => count($attemptRows),
    'completed' => acad_count($pdo, "SELECT COUNT(*) FROM webinar_registrations WHERE completion_status = 'completed'"),
    'certificates' => count($certificates),
    'pending_certificates' => acad_count($pdo, "SELECT COUNT(*) FROM academy_certificates WHERE status = 'pending'"),
    'pathways' => count($certificateGroups),
    'refunds' => count($refunds),
    'pending_refunds' => acad_count($pdo, "SELECT COUNT(*) FROM academy_refund_requests WHERE status IN ('pending','under_review','approved')"),
    'feedback' => count($feedbackRows),
];
$stats['completed_percent'] = $stats['enrollments'] > 0 ? round(($stats['completed'] / $stats['enrollments']) * 100, 1) : 0.0;
$academyCollections = (float) $pdo->query("SELECT COALESCE(SUM(w.price), 0) FROM webinar_registrations r JOIN webinars w ON w.id = r.webinar_id WHERE r.payment_status IN ('paid','successful')")->fetchColumn();
$academyWorkspaceData = [
    'programs' => array_map(static function (array $program) use ($courses, $enrollments): array {
        $programCourses = array_values(array_filter($courses, static fn(array $course): bool => (int) ($course['program_id'] ?? 0) === (int) $program['id']));
        $courseIds = array_map(static fn(array $course): int => (int) $course['id'], $programCourses);
        $learners = array_values(array_filter($enrollments, static fn(array $row): bool => in_array((int) ($row['webinar_id'] ?? 0), $courseIds, true)));
        $completed = array_values(array_filter($learners, static fn(array $row): bool => ($row['completion_status'] ?? '') === 'completed'));
        $completion = count($learners) > 0 ? (int) round((count($completed) / count($learners)) * 100) : 0;
        return [
            'title' => (string) $program['title'],
            'courses' => count($programCourses),
            'learners' => count($learners),
            'duration' => (string) ($program['duration_label'] ?? 'Self-paced'),
            'completion' => $completion,
            'status' => ucfirst((string) ($program['status'] ?? 'active')),
            'statusClass' => acad_status_class((string) ($program['status'] ?? 'active')),
        ];
    }, $programs),
    'courses' => array_map(static function (array $course) use ($enrollments, $lessons): array {
        $courseEnrollments = array_values(array_filter($enrollments, static fn(array $row): bool => (int) ($row['webinar_id'] ?? 0) === (int) $course['id']));
        $lessonCount = count(array_filter($lessons, static fn(array $lesson): bool => (int) ($lesson['webinar_id'] ?? 0) === (int) $course['id']));
        return [
            'title' => (string) $course['title'],
            'program' => (string) ($course['program_title'] ?? 'Unassigned'),
            'lessons' => $lessonCount,
            'enrolled' => count($courseEnrollments),
            'rating' => number_format((float) ($course['rating'] ?? 0), 1),
            'status' => ucfirst((string) ($course['status'] ?? 'draft')),
            'statusClass' => acad_status_class((string) ($course['status'] ?? 'draft')),
        ];
    }, $courses),
    'lessons' => array_map(static fn(array $lesson): array => [
        'title' => (string) $lesson['title'],
        'course' => (string) $lesson['course_title'],
        'type' => ucfirst((string) ($lesson['lesson_type'] ?? 'lesson')),
        'duration' => ((int) ($lesson['duration_minutes'] ?? 0)) . ' min',
        'completion' => 'Live',
        'status' => ucfirst((string) ($lesson['status'] ?? 'published')),
        'statusClass' => acad_status_class((string) ($lesson['status'] ?? 'active')),
    ], $lessons),
    'learners' => array_map(static fn(array $row): array => [
        'name' => (string) $row['user_name'],
        'email' => (string) $row['email'],
        'initials' => acad_initials((string) $row['user_name']),
        'program' => (string) ($row['program_title'] ?? 'Academy'),
        'enrolled' => date('M j, Y', strtotime((string) ($row['registered_at'] ?? 'now'))),
        'progress' => (int) ($row['progress_percent'] ?? 0),
        'status' => ucwords(str_replace('_', ' ', (string) ($row['completion_status'] ?? 'registered'))),
        'statusClass' => acad_status_class((string) ($row['completion_status'] ?? 'registered')),
    ], $enrollments),
    'certificates' => array_map(static fn(array $row): array => [
        'ref' => (string) ($row['certificate_ref'] ?? ('CERT-' . $row['id'])),
        'learner' => (string) $row['user_name'],
        'course' => (string) $row['course_title'],
        'issued' => !empty($row['issued_at']) ? date('M j, Y', strtotime((string) $row['issued_at'])) : 'Not issued',
        'grade' => (string) ($row['notes'] ?: '-'),
        'status' => ucfirst((string) ($row['status'] ?? 'pending')),
        'statusClass' => acad_status_class((string) ($row['status'] ?? 'pending')),
    ], $certificates),
    'refunds' => array_map(static fn(array $row): array => [
        'ref' => 'REF-' . str_pad((string) $row['id'], 5, '0', STR_PAD_LEFT),
        'learner' => (string) $row['user_name'],
        'course' => (string) $row['course_title'],
        'amount' => acad_money((float) ($row['amount'] ?? 0)),
        'reason' => (string) ($row['reason'] ?? 'Refund request'),
        'date' => date('M j, Y', strtotime((string) ($row['requested_at'] ?? 'now'))),
        'status' => ucwords(str_replace('_', ' ', (string) ($row['status'] ?? 'pending'))),
        'statusClass' => acad_status_class((string) ($row['status'] ?? 'pending')),
    ], $refunds),
];

$academyExport = preg_replace('/[^a-z0-9_-]/', '', (string) ($_GET['export'] ?? ''));
if ($academyExport !== '') {
    $rows = match ($academyExport) {
        'programs' => $academyWorkspaceData['programs'],
        'courses', 'progress' => $courseReport,
        'lessons' => $academyWorkspaceData['lessons'],
        'learners' => $academyWorkspaceData['learners'],
        'certificates' => $academyWorkspaceData['certificates'],
        'refunds' => $academyWorkspaceData['refunds'],
        'feedback' => $feedbackRows,
        'attendance' => $attendanceRows,
        'attempts' => $attemptRows,
        default => $courseReport,
    };
    app_export_csv('natcodev-academy-' . $academyExport . '-' . date('Ymd') . '.csv', $rows ? array_keys($rows[0]) : [], $rows);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>NATCODEV Academy - Workspace</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/inter@5.0.0/index.min.css">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
:root {
  --green-900:#0f2e1f; --green-800:#1a4731; --green-700:#235c3f; --green-600:#2d7a52;
  --green-500:#3a9d6a; --green-400:#4fc48a; --green-100:#e8f5ee; --green-50:#f0faf4;
  --bg:#f5f7f5; --card:#fff; --text:#1a1a1a; --text-secondary:#6b7280;
  --border:#e5e7eb; --danger:#dc2626; --warning:#f59e0b; --info:#3b82f6;
  --success:#10b981; --purple:#8b5cf6;
}
body { font-family:'Inter',sans-serif; background:var(--bg); color:var(--text); display:flex; min-height:100vh; }
.sidebar { width:260px; background:var(--green-900); color:#fff; position:fixed; top:0; left:0; bottom:0; overflow-y:auto; z-index:100; }
.sidebar-header { padding:20px; display:flex; align-items:center; gap:12px; border-bottom:1px solid rgba(255,255,255,0.1); }
.sidebar-logo { width:40px; height:40px; background:var(--green-400); border-radius:10px; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:14px; color:var(--green-900); }
.sidebar-brand { font-size:15px; font-weight:700; }
.sidebar-brand small { display:block; font-size:10px; font-weight:400; opacity:0.7; margin-top:2px; }
.nav-section { padding:16px 0; }
.nav-section-title { padding:0 20px; font-size:10px; text-transform:uppercase; letter-spacing:1px; opacity:0.5; margin-bottom:8px; }
.nav-item { display:flex; align-items:center; gap:12px; padding:10px 20px; cursor:pointer; transition:all 0.2s; font-size:14px; color:rgba(255,255,255,0.75); border-left:3px solid transparent; text-decoration:none; }
.nav-item:hover { background:rgba(255,255,255,0.08); color:#fff; }
.nav-item.active { background:rgba(255,255,255,0.12); color:#fff; border-left-color:var(--green-400); }
.nav-item svg { width:18px; height:18px; flex-shrink:0; }
.nav-item .badge { margin-left:auto; background:var(--green-400); color:var(--green-900); font-size:10px; font-weight:700; padding:2px 7px; border-radius:10px; }
.sidebar-footer { padding:16px 20px; border-top:1px solid rgba(255,255,255,0.1); display:flex; align-items:center; gap:10px; }
.sidebar-avatar { width:36px; height:36px; border-radius:50%; background:var(--green-600); display:flex; align-items:center; justify-content:center; font-weight:600; font-size:13px; }
.sidebar-user { font-size:13px; font-weight:600; }
.sidebar-user small { display:block; font-size:11px; opacity:0.6; font-weight:400; }

.main { margin-left:260px; flex:1; min-height:100vh; }
.topbar { background:#fff; padding:14px 28px; display:flex; align-items:center; gap:16px; border-bottom:1px solid var(--border); position:sticky; top:0; z-index:50; }
.topbar-search { flex:1; max-width:480px; position:relative; }
.topbar-search input { width:100%; padding:9px 14px 9px 38px; border:1px solid var(--border); border-radius:8px; font-size:13px; background:var(--bg); }
.topbar-search svg { position:absolute; left:12px; top:50%; transform:translateY(-50%); width:16px; height:16px; color:var(--text-secondary); }
.topbar-actions { display:flex; align-items:center; gap:12px; margin-left:auto; }
.topbar-icon { width:36px; height:36px; border-radius:8px; border:1px solid var(--border); display:flex; align-items:center; justify-content:center; cursor:pointer; position:relative; background:#fff; }
.topbar-icon .dot { position:absolute; top:6px; right:6px; width:8px; height:8px; background:var(--danger); border-radius:50%; border:2px solid #fff; }
.topbar-profile { display:flex; align-items:center; gap:10px; min-width:0; max-width:260px; cursor:pointer; padding:4px 10px 4px 6px; border-radius:8px; }
.topbar-profile:hover { background:var(--bg); }
.topbar-avatar { width:34px; height:34px; border-radius:50%; background:var(--green-600); display:flex; align-items:center; justify-content:center; color:#fff; font-weight:600; font-size:13px; }
.topbar-profile-info { display:flex; min-width:0; max-width:160px; flex-direction:column; align-items:flex-start; font-size:13px; font-weight:700; line-height:1.15; text-align:left; }
.topbar-profile-info,.topbar-profile-info small { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.topbar-profile-info small { display:block; max-width:100%; margin-top:2px; font-size:11px; color:var(--text-secondary); font-weight:500; }
.topbar-avatar { flex:0 0 34px; }
.topbar-profile svg { flex:0 0 auto; }
.topbar-menu-wrap{position:relative}
.topbar-icon{color:var(--text);text-decoration:none}
.topbar-menu{display:none;position:absolute;right:0;top:48px;width:270px;background:#fff;border:1px solid var(--border);border-radius:10px;box-shadow:0 18px 40px rgba(0,0,0,.12);padding:8px;z-index:90}
.topbar-menu.active{display:block}
.topbar-menu a{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:9px 10px;border-radius:8px;color:var(--text);text-decoration:none;font-weight:650}
.topbar-menu a:hover{background:var(--bg)}
.topbar-menu small{display:block;color:var(--text-secondary);font-weight:500;margin-top:2px}
.topbar-menu-label{padding:6px 10px 8px;color:var(--text-secondary);font-size:11px;text-transform:uppercase;letter-spacing:.4px;font-weight:800}
.topbar-profile{background:none;border:0;color:var(--text);font:inherit}
.topbar-avatar{overflow:hidden}
.topbar-avatar img{width:100%;height:100%;object-fit:cover;display:block}

.content { padding:28px; }
.page { display:none; }
.page.active { display:block; }
.page-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
.page-title { font-size:22px; font-weight:700; }
.page-subtitle { font-size:13px; color:var(--text-secondary); margin-top:2px; }
.btn { padding:9px 18px; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; border:none; display:inline-flex; align-items:center; gap:6px; transition:all 0.2s; }
.btn-primary { background:var(--green-700); color:#fff; }
.btn-primary:hover { background:var(--green-800); }
.btn-secondary { background:#fff; color:var(--text); border:1px solid var(--border); }
.btn-secondary:hover { background:var(--bg); }
.btn-danger { background:var(--danger); color:#fff; }
.btn-sm { padding:6px 12px; font-size:12px; }
.btn-icon { padding:6px; background:none; border:1px solid var(--border); border-radius:6px; cursor:pointer; }

.stats-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:16px; margin-bottom:24px; }
.stat-card { background:#fff; padding:20px; border-radius:12px; border:1px solid var(--border); }
.stat-card-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:12px; }
.stat-card-icon { width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; }
.stat-card-icon svg { width:20px; height:20px; }
.stat-card-label { font-size:12px; color:var(--text-secondary); }
.stat-card-value { font-size:26px; font-weight:700; margin-top:4px; }
.stat-card-change { font-size:11px; margin-top:6px; }
.stat-card-change.up { color:var(--success); }
.stat-card-change.down { color:var(--danger); }

.card { background:#fff; border-radius:12px; border:1px solid var(--border); margin-bottom:20px; }
.card-header { padding:18px 22px; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; }
.card-title { font-size:15px; font-weight:700; }
.card-body { padding:22px; }
.card-body.p0 { padding:0; }

table { width:100%; border-collapse:collapse; }
th, td { padding:12px 22px; text-align:left; font-size:13px; }
th { background:var(--bg); font-weight:600; color:var(--text-secondary); font-size:11px; text-transform:uppercase; letter-spacing:0.5px; border-bottom:1px solid var(--border); }
td { border-bottom:1px solid var(--border); }
tr:last-child td { border-bottom:none; }
tr:hover td { background:var(--green-50); }

.status-badge { padding:4px 10px; border-radius:20px; font-size:11px; font-weight:600; display:inline-block; }
.status-active { background:#dcfce7; color:#166534; }
.status-pending { background:#fef3c7; color:#92400e; }
.status-completed { background:#dbeafe; color:#1e40af; }
.status-draft { background:#f3f4f6; color:#4b5563; }
.status-cancelled { background:#fee2e2; color:#991b1b; }
.status-expired { background:#f3f4f6; color:#6b7280; }
.status-approved { background:#dcfce7; color:#166534; }
.status-rejected { background:#fee2e2; color:#991b1b; }

.progress-bar { height:6px; background:var(--border); border-radius:3px; overflow:hidden; width:100%; }
.progress-fill { height:100%; background:var(--green-500); border-radius:3px; transition:width 0.3s; }

.form-group { margin-bottom:16px; }
.form-label { display:block; font-size:12px; font-weight:600; margin-bottom:6px; color:var(--text-secondary); }
.form-input, .form-select, .form-textarea { width:100%; padding:9px 12px; border:1px solid var(--border); border-radius:8px; font-size:13px; font-family:inherit; }
.form-input:focus, .form-select:focus, .form-textarea:focus { outline:none; border-color:var(--green-500); box-shadow:0 0 0 3px rgba(58,157,106,0.1); }
.form-textarea { resize:vertical; min-height:80px; }
.form-row { display:grid; grid-template-columns:1fr 1fr; gap:16px; }

.tabs { display:flex; gap:4px; border-bottom:1px solid var(--border); margin-bottom:20px; }
.tab { padding:10px 16px; font-size:13px; font-weight:500; cursor:pointer; border-bottom:2px solid transparent; color:var(--text-secondary); }
.tab.active { color:var(--green-700); border-bottom-color:var(--green-700); font-weight:600; }
.tab:hover { color:var(--text); }

.filter-bar { display:flex; gap:10px; margin-bottom:16px; flex-wrap:wrap; align-items:center; }
.filter-bar input, .filter-bar select { padding:8px 12px; border:1px solid var(--border); border-radius:8px; font-size:13px; }

.modal-overlay { display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:200; align-items:center; justify-content:center; }
.modal-overlay.active { display:flex; }
.modal { background:#fff; border-radius:12px; width:90%; max-width:560px; max-height:90vh; overflow-y:auto; }
.modal-header { padding:18px 22px; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; }
.modal-title { font-size:16px; font-weight:700; }
.modal-body { padding:22px; }
.modal-footer { padding:16px 22px; border-top:1px solid var(--border); display:flex; justify-content:flex-end; gap:10px; }

.grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
.grid-3 { display:grid; grid-template-columns:repeat(3,1fr); gap:20px; }

.empty-state { text-align:center; padding:60px 20px; color:var(--text-secondary); }
.empty-state svg { width:48px; height:48px; margin-bottom:12px; opacity:0.4; }

.avatar-sm { width:32px; height:32px; border-radius:50%; background:var(--green-100); color:var(--green-700); display:inline-flex; align-items:center; justify-content:center; font-weight:600; font-size:12px; }
.avatar-row { display:flex; align-items:center; gap:10px; }

.toast { position:fixed; bottom:24px; right:24px; background:var(--green-800); color:#fff; padding:12px 20px; border-radius:8px; font-size:13px; z-index:300; display:none; animation:slideIn 0.3s; }
.alert { margin-bottom:18px; padding:13px 16px; border-radius:10px; font-size:14px; font-weight:700; border:1px solid var(--border); }
.alert.success { background:#ecfdf3; color:#067647; border-color:#abefc6; }
.alert.error { background:#fff1f3; color:#b42318; border-color:#fecdd6; }
@keyframes slideIn { from{transform:translateX(100%);opacity:0;} to{transform:translateX(0);opacity:1;} }

.chip { display:inline-flex; align-items:center; gap:4px; padding:3px 10px; background:var(--green-100); color:var(--green-700); border-radius:20px; font-size:11px; font-weight:500; }
.chip-close { cursor:pointer; }

@media(max-width:900px) {
  .sidebar { width:70px; }
  .sidebar-brand, .nav-section-title, .nav-item span, .sidebar-user, .sidebar-user small, .nav-item .badge { display:none; }
  .nav-item { justify-content:center; padding:12px; }
  .main { margin-left:70px; }
  .grid-2, .grid-3, .form-row { grid-template-columns:1fr; }
}
</style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sidebar-header">
    <div class="sidebar-logo">NC</div>
    <div class="sidebar-brand">NATCODEV<small>Academy Workspace</small></div>
  </div>

  <div class="nav-section">
    <div class="nav-section-title">Workspace Hub</div>
    <a class="nav-item" href="<?= e($academyAdminBase) ?>/index.php">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12l9-9 9 9"/><path d="M9 21V9h6v12"/></svg>
      <span>Workspace Hub</span>
    </a>
  </div>

  <div class="nav-section">
    <div class="nav-section-title">Academy</div>
    <div class="nav-item active" data-page="dashboard">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
      <span>Dashboard</span>
    </div>
    <div class="nav-item" data-page="programs">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg>
      <span>Programs</span>
      <span class="badge"><?= (int) $stats['programs'] ?></span>
    </div>
    <div class="nav-item" data-page="courses">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z"/><path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z"/></svg>
      <span>Courses</span>
      <span class="badge"><?= (int) $stats['courses'] ?></span>
    </div>
    <div class="nav-item" data-page="lessons">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
      <span>Lessons</span>
    </div>
    <div class="nav-item" data-page="materials">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15V6a2 2 0 00-2-2H7l-4 4v10a2 2 0 002 2h9"/><path d="M16 18h6"/><path d="M19 15v6"/></svg>
      <span>Materials</span>
      <span class="badge"><?= (int) $stats['materials'] ?></span>
    </div>
    <div class="nav-item" data-page="assessments">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
      <span>Assessments</span>
    </div>
    <div class="nav-item" data-page="questions">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 115.82 1c0 2-3 2-3 4"/><path d="M12 17h.01"/></svg>
      <span>Question Bank</span>
      <span class="badge"><?= (int) $stats['questions'] ?></span>
    </div>
  </div>

  <div class="nav-section">
    <div class="nav-section-title">Operations</div>
    <div class="nav-item" data-page="cohorts">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
      <span>Cohorts</span>
    </div>
    <div class="nav-item" data-page="instructors">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      <span>Instructors</span>
    </div>
    <div class="nav-item" data-page="attendance">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      <span>Attendance</span>
    </div>
    <div class="nav-item" data-page="reminders">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
      <span>Reminders</span>
      <span class="badge"><?= (int) $stats['reminders'] ?></span>
    </div>
  </div>

  <div class="nav-section">
    <div class="nav-section-title">People</div>
    <div class="nav-item" data-page="learners">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
      <span>Learners</span>
    </div>
    <div class="nav-item" data-page="progress">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="M7 14l3-3 3 2 5-6"/></svg>
      <span>Progress</span>
    </div>
    <div class="nav-item" data-page="attempts">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 12l2 2 4-4"/><path d="M21 12a9 9 0 11-9-9"/></svg>
      <span>Attempts</span>
    </div>
    <div class="nav-item" data-page="certificates">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="7"/><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/></svg>
      <span>Certificates</span>
    </div>
    <div class="nav-item" data-page="pathways">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
      <span>Certificate Pathways</span>
    </div>
  </div>

  <div class="nav-section">
    <div class="nav-section-title">Finance & Insights</div>
    <div class="nav-item" data-page="refunds">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 102.13-9.36L1 10"/></svg>
      <span>Refunds</span>
    </div>
    <div class="nav-item" data-page="feedback">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
      <span>Feedback</span>
    </div>
    <div class="nav-item" data-page="reports">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
      <span>Reports</span>
    </div>
  </div>

  <div class="sidebar-footer">
    <div class="sidebar-avatar"><?= e($adminInitials) ?></div>
    <div class="sidebar-user"><?= e($adminName) ?><small><?= e($adminRole) ?></small></div>
  </div>
</aside>

<!-- MAIN -->
<div class="main">
  <div class="topbar">
    <div class="topbar-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" placeholder="Search across all workspace pages..." id="globalSearch">
    </div>
    <div class="topbar-actions">
      <a class="topbar-icon" href="<?= e($academyAdminBase) ?>/index.php" title="Workspace Hub">⌂</a>
      <a class="topbar-icon" href="<?= e($academyPublicBase) ?>/index.php" title="Public Homepage">↗</a>
      <a class="topbar-icon" href="<?= e($academyAdminBase) ?>/notifications.php" title="Notifications">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
      </a>
      <a class="topbar-icon" href="<?= e($academyAdminBase) ?>/support.php" title="Help">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      </a>
      <div class="topbar-menu-wrap">
        <button class="topbar-profile" type="button" data-topbar-menu="profileMenu" aria-haspopup="true" aria-expanded="false">
          <div class="topbar-avatar"><?php if ($adminPictureUrl !== ''): ?><img src="<?= e($adminPictureUrl) ?>" alt=""><?php else: ?><?= e($adminInitials) ?><?php endif; ?></div>
          <div class="topbar-profile-info"><?= e($adminName) ?><small><?= e($adminRole) ?></small></div>
        </button>
        <div class="topbar-menu" id="profileMenu">
          <div class="topbar-menu-label">Profile</div>
          <a href="<?= e($academyAdminBase) ?>/profile.php"><span>Edit Profile<small>Photo, name, contact</small></span></a>
          <a href="<?= e($academyAdminBase) ?>/index.php"><span>Workspace Hub</span></a>
          <a href="<?= e($academyPublicBase) ?>/index.php"><span>Public Homepage</span></a>
          <a href="<?= e($academyAdminBase) ?>/index.php?logout=1"><span>Logout from workspace</span></a>
          <a href="<?= e($academyAdminBase) ?>/admin.php?logout=1"><span>Logout via legacy admin</span></a>
          <a href="<?= e($academyAdminBase) ?>/login.php?logout=1"><span>Logout to login</span></a>
        </div>
      </div>
    </div>
  </div>

  <div class="content">
    <?php if ($flashMessage !== '' || $flashError !== ''): ?>
      <div class="<?= $flashError !== '' ? 'alert error' : 'alert success' ?>">
        <?= e($flashError !== '' ? $flashError : $flashMessage) ?>
      </div>
    <?php endif; ?>

    <!-- DASHBOARD -->
    <div class="page active" id="page-dashboard">
      <div class="page-header">
        <div>
          <div class="page-title">Dashboard</div>
          <div class="page-subtitle">Overview of your academy performance</div>
        </div>
        <button class="btn btn-primary" onclick="showToast('Report exported')"> Export Report</button>
      </div>

      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-card-header">
            <div class="stat-card-label">Total Learners</div>
            <div class="stat-card-icon" style="background:#dbeafe;color:#1e40af"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div>
          </div>
          <div class="stat-card-value"><?= number_format((int) $stats['enrollments']) ?></div>
          <div class="stat-card-change up">↑ 12.5% from last month</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-header">
            <div class="stat-card-label">Active Enrollments</div>
            <div class="stat-card-icon" style="background:#dcfce7;color:#166534"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
          </div>
          <div class="stat-card-value"><?= number_format((int) $stats['active_courses']) ?></div>
          <div class="stat-card-change up">↑ 8.2% from last month</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-header">
            <div class="stat-card-label">Certificates Issued</div>
            <div class="stat-card-icon" style="background:#f3e8ff;color:#6b21a8"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="7"/><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/></svg></div>
          </div>
          <div class="stat-card-value"><?= number_format((int) $stats['certificates']) ?></div>
          <div class="stat-card-change up">↑ 15.3% from last month</div>
        </div>
        <div class="stat-card">
          <div class="stat-card-header">
            <div class="stat-card-label">Completion Rate</div>
            <div class="stat-card-icon" style="background:#fef3c7;color:#92400e"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></div>
          </div>
          <div class="stat-card-value"><?= e((string) $stats['completed_percent']) ?>%</div>
          <div class="stat-card-change down">↓ 2.1% from last month</div>
        </div>
      </div>

      <div class="grid-2">
        <div class="card">
          <div class="card-header">
            <div class="card-title">Recent Enrollments</div>
            <button class="btn btn-secondary btn-sm" onclick="navigateTo('learners')">View All</button>
          </div>
          <div class="card-body p0">
            <table>
              <thead><tr><th>Learner</th><th>Course</th><th>Date</th><th>Status</th></tr></thead>
              <tbody>
                <?php foreach (array_slice($enrollments, 0, 6) as $enrollment): ?>
                  <?php $progress = (int) ($enrollment['progress_percent'] ?? 0); ?>
                  <tr>
                    <td><div class="avatar-row"><div class="avatar-sm"><?= e(acad_initials((string) $enrollment['user_name'])) ?></div><?= e((string) $enrollment['user_name']) ?></div></td>
                    <td><?= e((string) $enrollment['course_title']) ?></td>
                    <td><?= e(date('M j, Y', strtotime((string) ($enrollment['registered_at'] ?? 'now')))) ?></td>
                    <td><span class="status-badge <?= e(acad_status_class((string) ($enrollment['completion_status'] ?? 'registered'))) ?>"><?= e(ucwords(str_replace('_', ' ', (string) ($enrollment['completion_status'] ?? 'registered')))) ?></span></td>
                  </tr>
                <?php endforeach; ?>
                <?php if (!$enrollments): ?>
                  <tr><td colspan="4">No enrollments yet.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
        <div class="card">
          <div class="card-header">
            <div class="card-title">Upcoming Live Sessions</div>
            <button class="btn btn-secondary btn-sm" onclick="navigateTo('cohorts')">View Calendar</button>
          </div>
          <div class="card-body p0">
            <table>
              <thead><tr><th>Session</th><th>Instructor</th><th>Date</th><th>Seats</th></tr></thead>
              <tbody>
                <?php foreach (array_slice($cohorts, 0, 6) as $cohort): ?>
                  <tr>
                    <td><?= e((string) ($cohort['title'] ?? $cohort['course_title'])) ?></td>
                    <td><?= e((string) ($cohort['instructor_name'] ?? 'Unassigned')) ?></td>
                    <td><?= e($cohort['start_at'] ? date('M j, H:i', strtotime((string) $cohort['start_at'])) : 'Not scheduled') ?></td>
                    <td><?= number_format((int) ($cohort['enrolled'] ?? 0)) ?>/<?= number_format((int) ($cohort['capacity'] ?? 0)) ?></td>
                  </tr>
                <?php endforeach; ?>
                <?php if (!$cohorts): ?>
                  <tr><td colspan="4">No upcoming sessions yet.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- PROGRAMS -->
    <div class="page" id="page-programs">
      <div class="page-header">
        <div><div class="page-title">Training Programs</div><div class="page-subtitle">Manage learning programs, cohorts, and certificate pathways</div></div>
        <button class="btn btn-primary" onclick="openModal('programModal')">+ New Program</button>
      </div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">Total Programs</div><div class="stat-card-value"><?= number_format((int) $stats['programs']) ?></div><div class="stat-card-change up"><?= number_format((int) $stats['cohorts']) ?> cohorts</div></div>
        <div class="stat-card"><div class="stat-card-label">Total Learners</div><div class="stat-card-value"><?= number_format((int) $stats['enrollments']) ?></div></div>
        <div class="stat-card"><div class="stat-card-label">Completion Rate</div><div class="stat-card-value"><?= e((string) $stats['completed_percent']) ?>%</div></div>
        <div class="stat-card"><div class="stat-card-label">Certificates Issued</div><div class="stat-card-value"><?= number_format((int) $stats['certificates']) ?></div></div>
      </div>
      <div class="card">
        <div class="card-header">
          <div class="card-title">All Programs</div>
          <div class="filter-bar" style="margin:0">
            <input type="text" placeholder="Search programs..." id="programSearch" oninput="filterTable('programsTable',this.value)">
            <select onchange="filterStatus('programsTable',this.value)">
              <option value="">All Status</option><option>Active</option><option>Draft</option><option>Completed</option>
            </select>
          </div>
        </div>
        <div class="card-body p0">
          <table id="programsTable">
            <thead><tr><th>Program Name</th><th>Courses</th><th>Learners</th><th>Duration</th><th>Completion</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <tr><td><strong>Career Onboarding Program</strong></td><td>5</td><td>1,245</td><td>12 weeks</td><td><div class="progress-bar" style="width:120px"><div class="progress-fill" style="width:85%"></div></div></td><td><span class="status-badge status-active">Active</span></td><td><button class="btn-icon">⋮</button></td></tr>
              <tr><td><strong>Pre-Service Accreditation</strong></td><td>8</td><td>845</td><td>16 weeks</td><td><div class="progress-bar" style="width:120px"><div class="progress-fill" style="width:62%"></div></div></td><td><span class="status-badge status-active">Active</span></td><td><button class="btn-icon">⋮</button></td></tr>
              <tr><td><strong>Field Staff Certification Program</strong></td><td>6</td><td>630</td><td>10 weeks</td><td><div class="progress-bar" style="width:120px"><div class="progress-fill" style="width:74%"></div></div></td><td><span class="status-badge status-active">Active</span></td><td><button class="btn-icon">⋮</button></td></tr>
              <tr><td><strong>State Coordinator Operations Program</strong></td><td>4</td><td>278</td><td>8 weeks</td><td><div class="progress-bar" style="width:120px"><div class="progress-fill" style="width:45%"></div></div></td><td><span class="status-badge status-pending">Draft</span></td><td><button class="btn-icon">⋮</button></td></tr>
              <tr><td><strong>Headquarters Skills Certification</strong></td><td>7</td><td>615</td><td>14 weeks</td><td><div class="progress-bar" style="width:120px"><div class="progress-fill" style="width:91%"></div></div></td><td><span class="status-badge status-completed">Completed</span></td><td><button class="btn-icon">⋮</button></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- COURSES -->
    <div class="page" id="page-courses">
      <div class="page-header">
        <div><div class="page-title">Courses</div><div class="page-subtitle"><?= number_format((int) $stats['courses']) ?> courses across all programs</div></div>
        <button class="btn btn-primary" onclick="openModal('courseModal')">+ New Course</button>
      </div>
      <div class="tabs">
        <div class="tab active" onclick="switchTab(this,'all-courses')">All Courses</div>
        <div class="tab" onclick="switchTab(this,'published-courses')">Published</div>
        <div class="tab" onclick="switchTab(this,'draft-courses')">Drafts</div>
        <div class="tab" onclick="switchTab(this,'archived-courses')">Archived</div>
      </div>
      <div class="card">
        <div class="card-header">
          <div class="card-title">Course Catalog</div>
          <div class="filter-bar" style="margin:0">
            <input type="text" placeholder="Search courses..." oninput="filterTable('coursesTable',this.value)">
            <select><option>All Programs</option><option>Career Onboarding</option><option>Pre-Service</option></select>
          </div>
        </div>
        <div class="card-body p0">
          <table id="coursesTable">
            <thead><tr><th>Course</th><th>Program</th><th>Lessons</th><th>Enrolled</th><th>Rating</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <tr><td><strong>Power BI Essentials</strong></td><td>Career Onboarding</td><td>12</td><td>485</td><td>⭐ 4.8</td><td><span class="status-badge status-active">Published</span></td><td><button class="btn-icon">⋮</button></td></tr>
              <tr><td><strong>Python for Data Science</strong></td><td>Career Onboarding</td><td>18</td><td>620</td><td>⭐ 4.9</td><td><span class="status-badge status-active">Published</span></td><td><button class="btn-icon">⋮</button></td></tr>
              <tr><td><strong>Agile Project Management</strong></td><td>Pre-Service Accreditation</td><td>10</td><td>312</td><td>⭐ 4.6</td><td><span class="status-badge status-active">Published</span></td><td><button class="btn-icon">⋮</button></td></tr>
              <tr><td><strong>UX/UI Design Fundamentals</strong></td><td>Pre-Service Accreditation</td><td>14</td><td>278</td><td>⭐ 4.7</td><td><span class="status-badge status-active">Published</span></td><td><button class="btn-icon"></button></td></tr>
              <tr><td><strong>Data Visualization with R</strong></td><td>Field Staff Certification</td><td>8</td><td>195</td><td>⭐ 4.5</td><td><span class="status-badge status-draft">Draft</span></td><td><button class="btn-icon"></button></td></tr>
              <tr><td><strong>Leadership in Public Health</strong></td><td>HQ Skills Certification</td><td>11</td><td>240</td><td>⭐ 4.8</td><td><span class="status-badge status-active">Published</span></td><td><button class="btn-icon">⋮</button></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- LESSONS -->
    <div class="page" id="page-lessons">
      <div class="page-header">
        <div><div class="page-title">Lessons</div><div class="page-subtitle">Manage individual lessons within courses</div></div>
        <button class="btn btn-primary" onclick="openModal('lessonModal')">+ New Lesson</button>
      </div>
      <div class="filter-bar">
        <select id="lessonCourseFilter" onchange="filterLessons()">
          <option value="">All Courses</option>
          <option>Power BI Essentials</option>
          <option>Python for Data Science</option>
          <option>Agile Project Management</option>
        </select>
        <select><option>All Types</option><option>Video</option><option>Reading</option><option>Quiz</option><option>Assignment</option></select>
      </div>
      <div class="card">
        <div class="card-body p0">
          <table id="lessonsTable">
            <thead><tr><th>Lesson Title</th><th>Course</th><th>Type</th><th>Duration</th><th>Completion</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <tr><td><strong>Introduction to Power BI</strong></td><td>Power BI Essentials</td><td><span class="chip"> Video</span></td><td>15 min</td><td>92%</td><td><span class="status-badge status-active">Published</span></td><td><button class="btn-icon">✏️</button></td></tr>
              <tr><td><strong>Connecting Data Sources</strong></td><td>Power BI Essentials</td><td><span class="chip">🎥 Video</span></td><td>22 min</td><td>88%</td><td><span class="status-badge status-active">Published</span></td><td><button class="btn-icon">️</button></td></tr>
              <tr><td><strong>Data Modeling Basics</strong></td><td>Power BI Essentials</td><td><span class="chip">📖 Reading</span></td><td>10 min</td><td>76%</td><td><span class="status-badge status-active">Published</span></td><td><button class="btn-icon">✏️</button></td></tr>
              <tr><td><strong>Creating Visualizations</strong></td><td>Power BI Essentials</td><td><span class="chip">📝 Assignment</span></td><td>45 min</td><td>65%</td><td><span class="status-badge status-active">Published</span></td><td><button class="btn-icon">✏️</button></td></tr>
              <tr><td><strong>Python Variables & Types</strong></td><td>Python for Data Science</td><td><span class="chip">🎥 Video</span></td><td>18 min</td><td>94%</td><td><span class="status-badge status-active">Published</span></td><td><button class="btn-icon">✏️</button></td></tr>
              <tr><td><strong>Pandas Fundamentals</strong></td><td>Python for Data Science</td><td><span class="chip">❓ Quiz</span></td><td>20 min</td><td>81%</td><td><span class="status-badge status-draft">Draft</span></td><td><button class="btn-icon">✏️</button></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- MATERIALS -->
    <div class="page" id="page-materials">
      <div class="page-header">
        <div><div class="page-title">Materials</div><div class="page-subtitle">Manage links, files, readings, and delivery assets attached to courses and lessons</div></div>
        <button class="btn btn-primary" onclick="openModal('materialModal')">+ New Material</button>
      </div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">Total Materials</div><div class="stat-card-value"><?= number_format((int) $stats['materials']) ?></div></div>
        <div class="stat-card"><div class="stat-card-label">Courses</div><div class="stat-card-value"><?= number_format((int) $stats['courses']) ?></div></div>
        <div class="stat-card"><div class="stat-card-label">Lessons</div><div class="stat-card-value"><?= number_format((int) $stats['lessons']) ?></div></div>
        <div class="stat-card"><div class="stat-card-label">Active Assets</div><div class="stat-card-value"><?= number_format(acad_count($pdo, "SELECT COUNT(*) FROM academy_materials WHERE status = 'active'")) ?></div></div>
      </div>
      <div class="card">
        <div class="card-header"><div class="card-title">Material Library</div><div class="filter-bar" style="margin:0"><input type="text" placeholder="Search materials..." oninput="filterTable('materialsTable',this.value)"></div></div>
        <div class="card-body p0">
          <table id="materialsTable">
            <thead><tr><th>Material</th><th>Course</th><th>Lesson</th><th>Type</th><th>URL / File</th><th>Status</th></tr></thead>
            <tbody>
              <?php foreach ($materials as $material): ?>
                <tr><td><strong><?= e((string) $material['title']) ?></strong></td><td><?= e((string) $material['course_title']) ?></td><td><?= e((string) ($material['lesson_title'] ?? 'Course level')) ?></td><td><?= e(ucfirst((string) $material['material_type'])) ?></td><td><?= e((string) ($material['material_url'] ?: $material['file_path'] ?: '-')) ?></td><td><span class="status-badge <?= e(acad_status_class((string) $material['status'])) ?>"><?= e(ucfirst((string) $material['status'])) ?></span></td></tr>
              <?php endforeach; ?>
              <?php if (!$materials): ?><tr><td colspan="6">No materials yet.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ASSESSMENTS -->
    <div class="page" id="page-assessments">
      <div class="page-header">
        <div><div class="page-title">Assessments</div><div class="page-subtitle">Quizzes, exams, and assignments</div></div>
        <button class="btn btn-primary" onclick="openModal('assessmentModal')">+ New Assessment</button>
      </div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">Total Assessments</div><div class="stat-card-value">156</div></div>
        <div class="stat-card"><div class="stat-card-label">Avg Pass Rate</div><div class="stat-card-value">78.3%</div></div>
        <div class="stat-card"><div class="stat-card-label">Pending Reviews</div><div class="stat-card-value">24</div></div>
        <div class="stat-card"><div class="stat-card-label">Avg Score</div><div class="stat-card-value">72.5</div></div>
      </div>
      <div class="card">
        <div class="card-header"><div class="card-title">Assessment Library</div></div>
        <div class="card-body p0">
          <table>
            <thead><tr><th>Assessment</th><th>Course</th><th>Type</th><th>Questions</th><th>Duration</th><th>Pass Rate</th><th>Status</th></tr></thead>
            <tbody>
              <tr><td><strong>Power BI Fundamentals Quiz</strong></td><td>Power BI Essentials</td><td>Quiz</td><td>25</td><td>30 min</td><td>85%</td><td><span class="status-badge status-active">Active</span></td></tr>
              <tr><td><strong>Mid-Term Exam: Data Modeling</strong></td><td>Power BI Essentials</td><td>Exam</td><td>50</td><td>90 min</td><td>72%</td><td><span class="status-badge status-active">Active</span></td></tr>
              <tr><td><strong>Python Basics Assessment</strong></td><td>Python for Data Science</td><td>Quiz</td><td>30</td><td>40 min</td><td>88%</td><td><span class="status-badge status-active">Active</span></td></tr>
              <tr><td><strong>Capstone Project Submission</strong></td><td>Python for Data Science</td><td>Assignment</td><td>5</td><td>7 days</td><td>65%</td><td><span class="status-badge status-pending">Reviewing</span></td></tr>
              <tr><td><strong>Agile Principles Test</strong></td><td>Agile Project Management</td><td>Quiz</td><td>20</td><td>25 min</td><td>91%</td><td><span class="status-badge status-active">Active</span></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- QUESTION BANK -->
    <div class="page" id="page-questions">
      <div class="page-header">
        <div><div class="page-title">Question Bank</div><div class="page-subtitle">Manage assessment questions, answer options, correct answers, and scoring</div></div>
        <button class="btn btn-primary" onclick="openModal('questionModal')">+ New Question</button>
      </div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">Questions</div><div class="stat-card-value"><?= number_format((int) $stats['questions']) ?></div></div>
        <div class="stat-card"><div class="stat-card-label">Assessments</div><div class="stat-card-value"><?= number_format((int) $stats['assessments']) ?></div></div>
        <div class="stat-card"><div class="stat-card-label">Attempts</div><div class="stat-card-value"><?= number_format((int) $stats['attempts']) ?></div></div>
        <div class="stat-card"><div class="stat-card-label">Active Questions</div><div class="stat-card-value"><?= number_format(acad_count($pdo, "SELECT COUNT(*) FROM academy_questions WHERE status = 'active'")) ?></div></div>
      </div>
      <div class="card">
        <div class="card-header"><div class="card-title">Questions</div><div class="filter-bar" style="margin:0"><input type="text" placeholder="Search questions..." oninput="filterTable('questionsTable',this.value)"></div></div>
        <div class="card-body p0">
          <table id="questionsTable">
            <thead><tr><th>Question</th><th>Assessment</th><th>Course</th><th>Correct</th><th>Points</th><th>Status</th></tr></thead>
            <tbody>
              <?php foreach ($questions as $question): ?>
                <?php $questionPreview = strlen((string) $question['question_text']) > 90 ? substr((string) $question['question_text'], 0, 90) . '...' : (string) $question['question_text']; ?>
                <tr><td><strong><?= e($questionPreview) ?></strong></td><td><?= e((string) $question['assessment_title']) ?></td><td><?= e((string) $question['course_title']) ?></td><td><?= e((string) $question['correct_option']) ?></td><td><?= e((string) $question['points']) ?></td><td><span class="status-badge <?= e(acad_status_class((string) $question['status'])) ?>"><?= e(ucfirst((string) $question['status'])) ?></span></td></tr>
              <?php endforeach; ?>
              <?php if (!$questions): ?><tr><td colspan="6">No questions yet.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- COHORTS -->
    <div class="page" id="page-cohorts">
      <div class="page-header">
        <div><div class="page-title">Cohorts</div><div class="page-subtitle">Manage learner groups and sessions</div></div>
        <button class="btn btn-primary" onclick="openModal('cohortModal')">+ New Cohort</button>
      </div>
      <div class="card">
        <div class="card-header"><div class="card-title">Active Cohorts</div></div>
        <div class="card-body p0">
          <table>
            <thead><tr><th>Cohort</th><th>Program</th><th>Start Date</th><th>End Date</th><th>Learners</th><th>Progress</th><th>Status</th></tr></thead>
            <tbody>
              <tr><td><strong>Cohort 2026-A</strong></td><td>Career Onboarding</td><td>Jan 15, 2026</td><td>Apr 10, 2026</td><td>245</td><td><div class="progress-bar" style="width:100px"><div class="progress-fill" style="width:100%"></div></div></td><td><span class="status-badge status-completed">Completed</span></td></tr>
              <tr><td><strong>Cohort 2026-B</strong></td><td>Career Onboarding</td><td>May 1, 2026</td><td>Jul 25, 2026</td><td>312</td><td><div class="progress-bar" style="width:100px"><div class="progress-fill" style="width:42%"></div></div></td><td><span class="status-badge status-active">Active</span></td></tr>
              <tr><td><strong>Cohort 2026-C</strong></td><td>Pre-Service Accreditation</td><td>Mar 10, 2026</td><td>Jun 30, 2026</td><td>189</td><td><div class="progress-bar" style="width:100px"><div class="progress-fill" style="width:68%"></div></div></td><td><span class="status-badge status-active">Active</span></td></tr>
              <tr><td><strong>Cohort 2026-D</strong></td><td>Field Staff Certification</td><td>Jun 5, 2026</td><td>Aug 15, 2026</td><td>156</td><td><div class="progress-bar" style="width:100px"><div class="progress-fill" style="width:5%"></div></div></td><td><span class="status-badge status-active">Active</span></td></tr>
              <tr><td><strong>Cohort 2026-E</strong></td><td>HQ Skills Certification</td><td>Jul 1, 2026</td><td>Sep 30, 2026</td><td>98</td><td><div class="progress-bar" style="width:100px"><div class="progress-fill" style="width:0%"></div></div></td><td><span class="status-badge status-pending">Upcoming</span></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- INSTRUCTORS -->
    <div class="page" id="page-instructors">
      <div class="page-header">
        <div><div class="page-title">Instructors</div><div class="page-subtitle">Manage teaching staff and facilitators</div></div>
        <button class="btn btn-primary" onclick="openModal('instructorModal')">+ Add Instructor</button>
      </div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">Total Instructors</div><div class="stat-card-value">28</div></div>
        <div class="stat-card"><div class="stat-card-label">Active This Month</div><div class="stat-card-value">22</div></div>
        <div class="stat-card"><div class="stat-card-label">Avg Rating</div><div class="stat-card-value">4.6</div></div>
        <div class="stat-card"><div class="stat-card-label">Total Sessions</div><div class="stat-card-value">342</div></div>
      </div>
      <div class="card">
        <div class="card-body p0">
          <table>
            <thead><tr><th>Instructor</th><th>Email</th><th>Courses</th><th>Learners</th><th>Rating</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <tr><td><div class="avatar-row"><div class="avatar-sm">DA</div><div><strong>Dr. Adebayo</strong><br><small style="color:var(--text-secondary)">Data Science</small></div></div></td><td>adebayo@natcodev.org</td><td>4</td><td>620</td><td>⭐ 4.9</td><td><span class="status-badge status-active">Active</span></td><td><button class="btn-icon">⋮</button></td></tr>
              <tr><td><div class="avatar-row"><div class="avatar-sm">PM</div><div><strong>Prof. Mensah</strong><br><small style="color:var(--text-secondary)">Business Intelligence</small></div></div></td><td>mensah@natcodev.org</td><td>3</td><td>485</td><td>⭐ 4.8</td><td><span class="status-badge status-active">Active</span></td><td><button class="btn-icon">⋮</button></td></tr>
              <tr><td><div class="avatar-row"><div class="avatar-sm">MO</div><div><strong>Ms. Okonkwo</strong><br><small style="color:var(--text-secondary)">Project Management</small></div></div></td><td>okonkwo@natcodev.org</td><td>2</td><td>312</td><td>⭐ 4.7</td><td><span class="status-badge status-active">Active</span></td><td><button class="btn-icon">⋮</button></td></tr>
              <tr><td><div class="avatar-row"><div class="avatar-sm">JK</div><div><strong>Mr. Kamara</strong><br><small style="color:var(--text-secondary)">UX Design</small></div></div></td><td>kamara@natcodev.org</td><td>2</td><td>278</td><td>⭐ 4.6</td><td><span class="status-badge status-active">Active</span></td><td><button class="btn-icon">⋮</button></td></tr>
              <tr><td><div class="avatar-row"><div class="avatar-sm">SN</div><div><strong>Dr. Nkrumah</strong><br><small style="color:var(--text-secondary)">Public Health</small></div></div></td><td>nkrumah@natcodev.org</td><td>3</td><td>240</td><td>⭐ 4.8</td><td><span class="status-badge status-pending">On Leave</span></td><td><button class="btn-icon">⋮</button></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ATTENDANCE -->
    <div class="page" id="page-attendance">
      <div class="page-header">
        <div><div class="page-title">Attendance</div><div class="page-subtitle">Track session attendance across cohorts</div></div>
        <button class="btn btn-primary" onclick="openModal('attendanceModal')">+ Mark Attendance</button>
      </div>
      <div class="filter-bar">
        <select><option>All Cohorts</option><option>Cohort 2026-B</option><option>Cohort 2026-C</option><option>Cohort 2026-D</option></select>
        <input type="date" value="2026-06-05">
        <button class="btn btn-secondary btn-sm">Apply Filter</button>
      </div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">Today's Sessions</div><div class="stat-card-value">8</div></div>
        <div class="stat-card"><div class="stat-card-label">Avg Attendance</div><div class="stat-card-value">84.2%</div></div>
        <div class="stat-card"><div class="stat-card-label">Absent Today</div><div class="stat-card-value">47</div></div>
        <div class="stat-card"><div class="stat-card-label">Late Arrivals</div><div class="stat-card-value">23</div></div>
      </div>
      <div class="card">
        <div class="card-header"><div class="card-title">Today's Attendance - June 5, 2026</div></div>
        <div class="card-body p0">
          <table>
            <thead><tr><th>Learner</th><th>Cohort</th><th>Session</th><th>Check-in</th><th>Duration</th><th>Status</th></tr></thead>
            <tbody>
              <tr><td><div class="avatar-row"><div class="avatar-sm">AK</div>Aisha Koroma</div></td><td>Cohort 2026-B</td><td>Power BI Lab</td><td>09:58 AM</td><td>1h 45m</td><td><span class="status-badge status-active">Present</span></td></tr>
              <tr><td><div class="avatar-row"><div class="avatar-sm">TS</div>Tunde Salami</div></td><td>Cohort 2026-B</td><td>Power BI Lab</td><td>10:12 AM</td><td>1h 30m</td><td><span class="status-badge status-pending">Late</span></td></tr>
              <tr><td><div class="avatar-row"><div class="avatar-sm">MO</div>Miriam Osei</div></td><td>Cohort 2026-C</td><td>Agile Workshop</td><td>—</td><td>—</td><td><span class="status-badge status-cancelled">Absent</span></td></tr>
              <tr><td><div class="avatar-row"><div class="avatar-sm">FN</div>Fatima Ndiaye</div></td><td>Cohort 2026-C</td><td>Agile Workshop</td><td>02:00 PM</td><td>2h 00m</td><td><span class="status-badge status-active">Present</span></td></tr>
              <tr><td><div class="avatar-row"><div class="avatar-sm">JB</div>James Boateng</div></td><td>Cohort 2026-D</td><td>Field Methods</td><td>08:55 AM</td><td>3h 15m</td><td><span class="status-badge status-active">Present</span></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- REMINDERS -->
    <div class="page" id="page-reminders">
      <div class="page-header">
        <div><div class="page-title">Reminders</div><div class="page-subtitle">Automated notifications and manual reminders</div></div>
        <button class="btn btn-primary" onclick="openModal('reminderModal')">+ New Reminder</button>
      </div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">Scheduled</div><div class="stat-card-value">18</div></div>
        <div class="stat-card"><div class="stat-card-label">Sent Today</div><div class="stat-card-value">42</div></div>
        <div class="stat-card"><div class="stat-card-label">Open Rate</div><div class="stat-card-value">76.4%</div></div>
        <div class="stat-card"><div class="stat-card-label">Pending</div><div class="stat-card-value">5</div></div>
      </div>
      <div class="card">
        <div class="card-header"><div class="card-title">Reminder Queue</div></div>
        <div class="card-body p0">
          <table>
            <thead><tr><th>Reminder</th><th>Target</th><th>Schedule</th><th>Channel</th><th>Recipients</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <tr><td><strong>Assignment Due Tomorrow</strong></td><td>Cohort 2026-B</td><td>Jun 5, 6:00 PM</td><td>📧 Email + SMS</td><td>312</td><td><span class="status-badge status-pending">Scheduled</span></td><td><button class="btn-icon">✏️</button></td></tr>
              <tr><td><strong>Live Session in 1 Hour</strong></td><td>Cohort 2026-C</td><td>Jun 5, 1:00 PM</td><td> Email</td><td>189</td><td><span class="status-badge status-pending">Scheduled</span></td><td><button class="btn-icon">✏️</button></td></tr>
              <tr><td><strong>Course Completion Reminder</strong></td><td>All Active Learners</td><td>Jun 6, 9:00 AM</td><td>📧 Email + Push</td><td>2,479</td><td><span class="status-badge status-pending">Scheduled</span></td><td><button class="btn-icon">️</button></td></tr>
              <tr><td><strong>Feedback Request</strong></td><td>Cohort 2026-A</td><td>Jun 4, 10:00 AM</td><td>📧 Email</td><td>245</td><td><span class="status-badge status-completed">Sent</span></td><td><button class="btn-icon">👁</button></td></tr>
              <tr><td><strong>Certificate Ready</strong></td><td>Completed Learners</td><td>Jun 3, 8:00 AM</td><td> Email</td><td>87</td><td><span class="status-badge status-completed">Sent</span></td><td><button class="btn-icon">👁</button></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- LEARNERS -->
    <div class="page" id="page-learners">
      <div class="page-header">
        <div><div class="page-title">Learners</div><div class="page-subtitle"><?= number_format((int) $stats['enrollments']) ?> registered learners</div></div>
        <button class="btn btn-primary" onclick="openModal('learnerModal')">+ Add Learner</button>
      </div>
      <div class="filter-bar">
        <input type="text" placeholder="Search by name or email..." oninput="filterTable('learnersTable',this.value)">
        <select><option>All Programs</option><option>Career Onboarding</option><option>Pre-Service</option></select>
        <select><option>All Status</option><option>Active</option><option>Completed</option><option>Dropped</option></select>
      </div>
      <div class="card">
        <div class="card-body p0">
          <table id="learnersTable">
            <thead><tr><th>Learner</th><th>Email</th><th>Program</th><th>Enrolled</th><th>Progress</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <tr><td><div class="avatar-row"><div class="avatar-sm">AK</div><div><strong>Aisha Koroma</strong><br><small style="color:var(--text-secondary)">Learner ID: NC-2026-001</small></div></div></td><td>aisha.k@natcodev.org</td><td>Career Onboarding</td><td>Jan 15, 2026</td><td><div class="progress-bar" style="width:100px"><div class="progress-fill" style="width:85%"></div></div> 85%</td><td><span class="status-badge status-active">Active</span></td><td><button class="btn-icon">⋮</button></td></tr>
              <tr><td><div class="avatar-row"><div class="avatar-sm">TS</div><div><strong>Tunde Salami</strong><br><small style="color:var(--text-secondary)">Learner ID: NC-2026-002</small></div></div></td><td>tunde.s@natcodev.org</td><td>Career Onboarding</td><td>Jan 15, 2026</td><td><div class="progress-bar" style="width:100px"><div class="progress-fill" style="width:92%"></div></div> 92%</td><td><span class="status-badge status-active">Active</span></td><td><button class="btn-icon"></button></td></tr>
              <tr><td><div class="avatar-row"><div class="avatar-sm">MO</div><div><strong>Miriam Osei</strong><br><small style="color:var(--text-secondary)">Learner ID: NC-2026-003</small></div></div></td><td>miriam.o@natcodev.org</td><td>Pre-Service Accreditation</td><td>Mar 10, 2026</td><td><div class="progress-bar" style="width:100px"><div class="progress-fill" style="width:68%"></div></div> 68%</td><td><span class="status-badge status-active">Active</span></td><td><button class="btn-icon">⋮</button></td></tr>
              <tr><td><div class="avatar-row"><div class="avatar-sm">FN</div><div><strong>Fatima Ndiaye</strong><br><small style="color:var(--text-secondary)">Learner ID: NC-2026-004</small></div></div></td><td>fatima.n@natcodev.org</td><td>Pre-Service Accreditation</td><td>Mar 10, 2026</td><td><div class="progress-bar" style="width:100px"><div class="progress-fill" style="width:100%"></div></div> 100%</td><td><span class="status-badge status-completed">Completed</span></td><td><button class="btn-icon">⋮</button></td></tr>
              <tr><td><div class="avatar-row"><div class="avatar-sm">JB</div><div><strong>James Boateng</strong><br><small style="color:var(--text-secondary)">Learner ID: NC-2026-005</small></div></div></td><td>james.b@natcodev.org</td><td>Field Staff Certification</td><td>Jun 5, 2026</td><td><div class="progress-bar" style="width:100px"><div class="progress-fill" style="width:12%"></div></div> 12%</td><td><span class="status-badge status-active">Active</span></td><td><button class="btn-icon"></button></td></tr>
              <tr><td><div class="avatar-row"><div class="avatar-sm">SK</div><div><strong>Sarah Koffi</strong><br><small style="color:var(--text-secondary)">Learner ID: NC-2026-006</small></div></div></td><td>sarah.k@natcodev.org</td><td>HQ Skills Certification</td><td>Jan 20, 2026</td><td><div class="progress-bar" style="width:100px"><div class="progress-fill" style="width:0%"></div></div> 0%</td><td><span class="status-badge status-cancelled">Dropped</span></td><td><button class="btn-icon">⋮</button></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- PROGRESS -->
    <div class="page" id="page-progress">
      <div class="page-header">
        <div><div class="page-title">Learner Progress</div><div class="page-subtitle">Track lesson completion and course movement across enrolled learners</div></div>
        <button class="btn btn-primary" onclick="navigateTo('learners')">Enroll Learner</button>
      </div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">Progress Records</div><div class="stat-card-value"><?= number_format((int) $stats['progress']) ?></div></div>
        <div class="stat-card"><div class="stat-card-label">Completed Enrollments</div><div class="stat-card-value"><?= number_format((int) $stats['completed']) ?></div></div>
        <div class="stat-card"><div class="stat-card-label">Completion Rate</div><div class="stat-card-value"><?= e((string) $stats['completed_percent']) ?>%</div></div>
        <div class="stat-card"><div class="stat-card-label">Active Learners</div><div class="stat-card-value"><?= number_format((int) $stats['enrollments']) ?></div></div>
      </div>
      <div class="card">
        <div class="card-header"><div class="card-title">Lesson Progress</div><div class="filter-bar" style="margin:0"><input type="text" placeholder="Search progress..." oninput="filterTable('progressTable',this.value)"></div></div>
        <div class="card-body p0">
          <table id="progressTable">
            <thead><tr><th>Learner</th><th>Course</th><th>Lesson</th><th>Status</th><th>Progress</th><th>Updated</th></tr></thead>
            <tbody>
              <?php foreach ($progressRows as $row): ?>
                <tr><td><div class="avatar-row"><div class="avatar-sm"><?= e(acad_initials((string) $row['user_name'])) ?></div><?= e((string) $row['user_name']) ?></div></td><td><?= e((string) $row['course_title']) ?></td><td><?= e((string) ($row['lesson_title'] ?? 'Course level')) ?></td><td><span class="status-badge <?= e(acad_status_class((string) $row['status'])) ?>"><?= e(ucwords(str_replace('_', ' ', (string) $row['status']))) ?></span></td><td><div class="progress-bar" style="width:100px"><div class="progress-fill" style="width:<?= (int) $row['progress_percent'] ?>%"></div></div> <?= (int) $row['progress_percent'] ?>%</td><td><?= e($row['updated_at'] ? date('M j, Y H:i', strtotime((string) $row['updated_at'])) : date('M j, Y H:i', strtotime((string) $row['created_at']))) ?></td></tr>
              <?php endforeach; ?>
              <?php if (!$progressRows): ?><tr><td colspan="6">No learner progress recorded yet.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ATTEMPTS -->
    <div class="page" id="page-attempts">
      <div class="page-header">
        <div><div class="page-title">Assessment Attempts</div><div class="page-subtitle">Review submissions, scores, pass/fail status, and evidence of assessment completion</div></div>
        <button class="btn btn-primary" onclick="navigateTo('questions')">Manage Questions</button>
      </div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">Attempts</div><div class="stat-card-value"><?= number_format((int) $stats['attempts']) ?></div></div>
        <div class="stat-card"><div class="stat-card-label">Passed</div><div class="stat-card-value"><?= number_format(acad_count($pdo, "SELECT COUNT(*) FROM academy_attempts WHERE passed = 1")) ?></div></div>
        <div class="stat-card"><div class="stat-card-label">Submitted</div><div class="stat-card-value"><?= number_format(acad_count($pdo, "SELECT COUNT(*) FROM academy_attempts WHERE status = 'submitted'")) ?></div></div>
        <div class="stat-card"><div class="stat-card-label">Questions</div><div class="stat-card-value"><?= number_format((int) $stats['questions']) ?></div></div>
      </div>
      <div class="card">
        <div class="card-header"><div class="card-title">Recent Attempts</div><div class="filter-bar" style="margin:0"><input type="text" placeholder="Search attempts..." oninput="filterTable('attemptsTable',this.value)"></div></div>
        <div class="card-body p0">
          <table id="attemptsTable">
            <thead><tr><th>Learner</th><th>Course</th><th>Assessment</th><th>Score</th><th>Passed</th><th>Completed</th></tr></thead>
            <tbody>
              <?php foreach ($attemptRows as $attempt): ?>
                <tr><td><div class="avatar-row"><div class="avatar-sm"><?= e(acad_initials((string) $attempt['user_name'])) ?></div><?= e((string) $attempt['user_name']) ?></div></td><td><?= e((string) $attempt['course_title']) ?></td><td><?= e((string) $attempt['assessment_title']) ?></td><td><?= e((string) $attempt['score_percent']) ?>%</td><td><span class="status-badge <?= ((int) $attempt['passed'] === 1) ? 'status-active' : 'status-cancelled' ?>"><?= ((int) $attempt['passed'] === 1) ? 'Passed' : 'Not Passed' ?></span></td><td><?= e(date('M j, Y H:i', strtotime((string) $attempt['completed_at']))) ?></td></tr>
              <?php endforeach; ?>
              <?php if (!$attemptRows): ?><tr><td colspan="6">No assessment attempts yet.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- CERTIFICATES -->
    <div class="page" id="page-certificates">
      <div class="page-header">
        <div><div class="page-title">Certificates</div><div class="page-subtitle"><?= number_format((int) $stats['certificates']) ?> certificate records</div></div>
        <button class="btn btn-primary" onclick="openModal('certificateModal')">+ Issue Certificate</button>
      </div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">Total Records</div><div class="stat-card-value"><?= number_format((int) $stats['certificates']) ?></div></div>
        <div class="stat-card"><div class="stat-card-label">Pending Approval</div><div class="stat-card-value"><?= number_format((int) $stats['pending_certificates']) ?></div></div>
        <div class="stat-card"><div class="stat-card-label">Certificate Pathways</div><div class="stat-card-value"><?= number_format((int) $stats['pathways']) ?></div></div>
        <div class="stat-card"><div class="stat-card-label">Completed Learners</div><div class="stat-card-value"><?= number_format((int) $stats['completed']) ?></div></div>
      </div>
      <div class="card">
        <div class="card-header"><div class="card-title">Certificate Records</div></div>
        <div class="card-body p0">
          <table>
            <thead><tr><th>Certificate ID</th><th>Learner</th><th>Program</th><th>Issue Date</th><th>Grade</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <tr><td><strong>CERT-2026-0847</strong></td><td>Fatima Ndiaye</td><td>Pre-Service Accreditation</td><td>Jun 1, 2026</td><td>A (95%)</td><td><span class="status-badge status-approved">Verified</span></td><td><button class="btn-icon">👁</button></td></tr>
              <tr><td><strong>CERT-2026-0846</strong></td><td>Tunde Salami</td><td>Career Onboarding</td><td>May 28, 2026</td><td>A+ (98%)</td><td><span class="status-badge status-approved">Verified</span></td><td><button class="btn-icon">👁</button></td></tr>
              <tr><td><strong>CERT-2026-0845</strong></td><td>Aisha Koroma</td><td>Career Onboarding</td><td>May 25, 2026</td><td>B+ (87%)</td><td><span class="status-badge status-approved">Verified</span></td><td><button class="btn-icon">👁</button></td></tr>
              <tr><td><strong>CERT-2026-0844</strong></td><td>Miriam Osei</td><td>Pre-Service Accreditation</td><td>—</td><td>—</td><td><span class="status-badge status-pending">Pending</span></td><td><button class="btn-icon">✏️</button></td></tr>
              <tr><td><strong>CERT-2026-0843</strong></td><td>Sarah Koffi</td><td>HQ Skills Certification</td><td>Apr 15, 2026</td><td>C (72%)</td><td><span class="status-badge status-cancelled">Revoked</span></td><td><button class="btn-icon">👁</button></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- PATHWAYS -->
    <div class="page" id="page-pathways">
      <div class="page-header">
        <div><div class="page-title">Certificate Pathways</div><div class="page-subtitle">Define learning paths that lead to certification</div></div>
        <button class="btn btn-primary" onclick="openModal('pathwayModal')">+ New Pathway</button>
      </div>
      <div class="grid-3">
        <div class="card">
          <div class="card-body">
            <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:12px">
              <div>
                <div style="font-weight:700;font-size:15px">Data Analyst Pathway</div>
                <div style="font-size:12px;color:var(--text-secondary);margin-top:2px">4 courses • 6 months</div>
              </div>
              <span class="status-badge status-active">Active</span>
            </div>
            <div style="margin:16px 0">
              <div style="font-size:12px;color:var(--text-secondary);margin-bottom:6px">Progress</div>
              <div class="progress-bar"><div class="progress-fill" style="width:78%"></div></div>
              <div style="font-size:12px;margin-top:4px">78% of learners complete</div>
            </div>
            <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px">
              <span class="chip">Power BI</span><span class="chip">Python</span><span class="chip">SQL</span><span class="chip">Statistics</span>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--text-secondary);border-top:1px solid var(--border);padding-top:12px">
              <span>👥 620 enrolled</span><span>🏆 485 certified</span>
            </div>
          </div>
        </div>
        <div class="card">
          <div class="card-body">
            <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:12px">
              <div>
                <div style="font-weight:700;font-size:15px">Project Manager Pathway</div>
                <div style="font-size:12px;color:var(--text-secondary);margin-top:2px">3 courses • 4 months</div>
              </div>
              <span class="status-badge status-active">Active</span>
            </div>
            <div style="margin:16px 0">
              <div style="font-size:12px;color:var(--text-secondary);margin-bottom:6px">Progress</div>
              <div class="progress-bar"><div class="progress-fill" style="width:65%"></div></div>
              <div style="font-size:12px;margin-top:4px">65% of learners complete</div>
            </div>
            <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px">
              <span class="chip">Agile</span><span class="chip">Scrum</span><span class="chip">Leadership</span>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--text-secondary);border-top:1px solid var(--border);padding-top:12px">
              <span>👥 312 enrolled</span><span> 203 certified</span>
            </div>
          </div>
        </div>
        <div class="card">
          <div class="card-body">
            <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:12px">
              <div>
                <div style="font-weight:700;font-size:15px">UX Designer Pathway</div>
                <div style="font-size:12px;color:var(--text-secondary);margin-top:2px">5 courses • 8 months</div>
              </div>
              <span class="status-badge status-pending">Draft</span>
            </div>
            <div style="margin:16px 0">
              <div style="font-size:12px;color:var(--text-secondary);margin-bottom:6px">Progress</div>
              <div class="progress-bar"><div class="progress-fill" style="width:42%"></div></div>
              <div style="font-size:12px;margin-top:4px">42% of learners complete</div>
            </div>
            <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px">
              <span class="chip">Research</span><span class="chip">Wireframing</span><span class="chip">Prototyping</span>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--text-secondary);border-top:1px solid var(--border);padding-top:12px">
              <span>👥 278 enrolled</span><span>🏆 117 certified</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- REFUNDS -->
    <div class="page" id="page-refunds">
      <div class="page-header">
        <div><div class="page-title">Refunds</div><div class="page-subtitle">Manage refund requests and processing</div></div>
        <button class="btn btn-primary" onclick="openModal('refundModal')">+ New Refund Request</button>
      </div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">Total Requests</div><div class="stat-card-value"><?= number_format((int) $stats['refunds']) ?></div></div>
        <div class="stat-card"><div class="stat-card-label">Pending</div><div class="stat-card-value"><?= number_format((int) $stats['pending_refunds']) ?></div></div>
        <div class="stat-card"><div class="stat-card-label">Collections</div><div class="stat-card-value"><?= e(acad_money($academyCollections)) ?></div></div>
        <div class="stat-card"><div class="stat-card-label">Payment Support</div><div class="stat-card-value"><?= number_format((int) $stats['refunds']) ?></div></div>
      </div>
      <div class="card">
        <div class="card-header"><div class="card-title">Refund Requests</div></div>
        <div class="card-body p0">
          <table>
            <thead><tr><th>Request ID</th><th>Learner</th><th>Course</th><th>Amount</th><th>Reason</th><th>Date</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <tr><td><strong>REF-2026-0047</strong></td><td>Sarah Koffi</td><td>HQ Skills Certification</td><td>$450</td><td>Course mismatch</td><td>Jun 4, 2026</td><td><span class="status-badge status-pending">Pending</span></td><td><button class="btn btn-sm btn-primary" onclick="showToast('Refund approved')">Approve</button></td></tr>
              <tr><td><strong>REF-2026-0046</strong></td><td>David Mensah</td><td>Python for Data Science</td><td>$380</td><td>Technical issues</td><td>Jun 3, 2026</td><td><span class="status-badge status-pending">Pending</span></td><td><button class="btn btn-sm btn-primary" onclick="showToast('Refund approved')">Approve</button></td></tr>
              <tr><td><strong>REF-2026-0045</strong></td><td>Amina Yusuf</td><td>Agile Project Management</td><td>$320</td><td>Personal reasons</td><td>Jun 2, 2026</td><td><span class="status-badge status-approved">Approved</span></td><td><button class="btn-icon"></button></td></tr>
              <tr><td><strong>REF-2026-0044</strong></td><td>Emeka Obi</td><td>Power BI Essentials</td><td>$290</td><td>Duplicate enrollment</td><td>Jun 1, 2026</td><td><span class="status-badge status-approved">Approved</span></td><td><button class="btn-icon">👁</button></td></tr>
              <tr><td><strong>REF-2026-0043</strong></td><td>Linda Asante</td><td>UX/UI Design</td><td>$410</td><td>Not as described</td><td>May 30, 2026</td><td><span class="status-badge status-rejected">Rejected</span></td><td><button class="btn-icon">👁</button></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- FEEDBACK -->
    <div class="page" id="page-feedback">
      <div class="page-header">
        <div><div class="page-title">Feedback</div><div class="page-subtitle">Learner reviews and course feedback</div></div>
        <button class="btn btn-primary" onclick="showToast('Feedback report exported')"> Export</button>
      </div>
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">Average Rating</div><div class="stat-card-value" style="color:var(--warning)">⭐ 4.6</div></div>
        <div class="stat-card"><div class="stat-card-label">Total Reviews</div><div class="stat-card-value">2,847</div></div>
        <div class="stat-card"><div class="stat-card-label">5-Star Reviews</div><div class="stat-card-value">71%</div></div>
        <div class="stat-card"><div class="stat-card-label">Response Rate</div><div class="stat-card-value">89%</div></div>
      </div>
      <div class="card">
        <div class="card-header"><div class="card-title">Recent Feedback</div></div>
        <div class="card-body">
          <div style="display:flex;flex-direction:column;gap:16px">
            <div style="padding:16px;background:var(--bg);border-radius:10px">
              <div style="display:flex;justify-content:space-between;margin-bottom:8px">
                <div><strong>Aisha Koroma</strong> <span style="color:var(--text-secondary);font-size:12px">• Power BI Essentials</span></div>
                <div style="color:var(--warning)">⭐⭐⭐⭐⭐</div>
              </div>
              <div style="font-size:13px;color:var(--text-secondary)">"Excellent course! The instructor was very knowledgeable and the hands-on labs were incredibly helpful. Highly recommend."</div>
              <div style="font-size:11px;color:var(--text-secondary);margin-top:8px">Jun 4, 2026 • <span style="color:var(--success)">✓ Responded</span></div>
            </div>
            <div style="padding:16px;background:var(--bg);border-radius:10px">
              <div style="display:flex;justify-content:space-between;margin-bottom:8px">
                <div><strong>Tunde Salami</strong> <span style="color:var(--text-secondary);font-size:12px">• Python for Data Science</span></div>
                <div style="color:var(--warning)">⭐⭐⭐⭐⭐</div>
              </div>
              <div style="font-size:13px;color:var(--text-secondary)">"The capstone project really helped solidify my learning. Would love more advanced content on machine learning."</div>
              <div style="font-size:11px;color:var(--text-secondary);margin-top:8px">Jun 3, 2026 • <span style="color:var(--success)">✓ Responded</span></div>
            </div>
            <div style="padding:16px;background:var(--bg);border-radius:10px">
              <div style="display:flex;justify-content:space-between;margin-bottom:8px">
                <div><strong>Miriam Osei</strong> <span style="color:var(--text-secondary);font-size:12px">• Agile Project Management</span></div>
                <div style="color:var(--warning)">⭐⭐⭐⭐</div>
              </div>
              <div style="font-size:13px;color:var(--text-secondary)">"Good content but the pacing was a bit fast. More practice exercises would be helpful."</div>
              <div style="font-size:11px;color:var(--text-secondary);margin-top:8px">Jun 2, 2026 • <span style="color:var(--warning)">⏳ Pending response</span></div>
            </div>
            <div style="padding:16px;background:var(--bg);border-radius:10px">
              <div style="display:flex;justify-content:space-between;margin-bottom:8px">
                <div><strong>James Boateng</strong> <span style="color:var(--text-secondary);font-size:12px">• Field Staff Certification</span></div>
                <div style="color:var(--warning)">⭐⭐⭐</div>
              </div>
              <div style="font-size:13px;color:var(--text-secondary)">"The content is relevant but the platform had some technical issues during live sessions."</div>
              <div style="font-size:11px;color:var(--text-secondary);margin-top:8px">Jun 1, 2026 • <span style="color:var(--warning)">⏳ Pending response</span></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- REPORTS -->
    <div class="page" id="page-reports">
      <div class="page-header">
        <div><div class="page-title">Reports</div><div class="page-subtitle">Analytics and insights across the academy</div></div>
        <button class="btn btn-primary" onclick="showToast('Generating report...')">📊 Generate Report</button>
      </div>
      <div class="grid-2">
        <div class="card">
          <div class="card-header"><div class="card-title">Enrollment Trends</div></div>
          <div class="card-body">
            <div style="display:flex;align-items:end;gap:8px;height:180px;padding:20px 0">
              <div style="flex:1;background:var(--green-100);border-radius:6px 6px 0 0;height:40%;position:relative"><div style="position:absolute;bottom:-20px;left:50%;transform:translateX(-50%);font-size:10px;color:var(--text-secondary)">Jan</div></div>
              <div style="flex:1;background:var(--green-100);border-radius:6px 6px 0 0;height:55%"><div style="position:absolute;bottom:-20px;left:50%;transform:translateX(-50%);font-size:10px;color:var(--text-secondary)">Feb</div></div>
              <div style="flex:1;background:var(--green-100);border-radius:6px 6px 0 0;height:48%"><div style="position:absolute;bottom:-20px;left:50%;transform:translateX(-50%);font-size:10px;color:var(--text-secondary)">Mar</div></div>
              <div style="flex:1;background:var(--green-100);border-radius:6px 6px 0 0;height:72%"><div style="position:absolute;bottom:-20px;left:50%;transform:translateX(-50%);font-size:10px;color:var(--text-secondary)">Apr</div></div>
              <div style="flex:1;background:var(--green-100);border-radius:6px 6px 0 0;height:65%"><div style="position:absolute;bottom:-20px;left:50%;transform:translateX(-50%);font-size:10px;color:var(--text-secondary)">May</div></div>
              <div style="flex:1;background:var(--green-500);border-radius:6px 6px 0 0;height:85%"><div style="position:absolute;bottom:-20px;left:50%;transform:translateX(-50%);font-size:10px;color:var(--text-secondary)">Jun</div></div>
            </div>
          </div>
        </div>
        <div class="card">
          <div class="card-header"><div class="card-title">Completion by Program</div></div>
          <div class="card-body">
            <div style="display:flex;flex-direction:column;gap:14px">
              <div><div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px"><span>Career Onboarding</span><span style="font-weight:600">85%</span></div><div class="progress-bar"><div class="progress-fill" style="width:85%"></div></div></div>
              <div><div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px"><span>Pre-Service Accreditation</span><span style="font-weight:600">62%</span></div><div class="progress-bar"><div class="progress-fill" style="width:62%"></div></div></div>
              <div><div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px"><span>Field Staff Certification</span><span style="font-weight:600">74%</span></div><div class="progress-bar"><div class="progress-fill" style="width:74%"></div></div></div>
              <div><div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px"><span>HQ Skills Certification</span><span style="font-weight:600">91%</span></div><div class="progress-bar"><div class="progress-fill" style="width:91%"></div></div></div>
              <div><div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px"><span>State Coordinator Ops</span><span style="font-weight:600">45%</span></div><div class="progress-bar"><div class="progress-fill" style="width:45%"></div></div></div>
            </div>
          </div>
        </div>
      </div>
      <div class="card">
        <div class="card-header"><div class="card-title">Available Reports</div></div>
        <div class="card-body p0">
          <table>
            <thead><tr><th>Report</th><th>Description</th><th>Last Generated</th><th>Actions</th></tr></thead>
            <tbody>
              <tr><td><strong>Learner Progress Report</strong></td><td>Individual and cohort progress metrics</td><td>Jun 4, 2026</td><td><button class="btn btn-sm btn-primary" onclick="showToast('Report downloaded')">⬇ Download</button></td></tr>
              <tr><td><strong>Financial Summary</strong></td><td>Revenue, refunds, and outstanding payments</td><td>Jun 1, 2026</td><td><button class="btn btn-sm btn-primary" onclick="showToast('Report downloaded')">⬇ Download</button></td></tr>
              <tr><td><strong>Instructor Performance</strong></td><td>Ratings, session counts, and learner feedback</td><td>May 28, 2026</td><td><button class="btn btn-sm btn-primary" onclick="showToast('Report downloaded')"> Download</button></td></tr>
              <tr><td><strong>Certificate Audit</strong></td><td>All issued certificates with verification status</td><td>May 25, 2026</td><td><button class="btn btn-sm btn-primary" onclick="showToast('Report downloaded')"> Download</button></td></tr>
              <tr><td><strong>Attendance Analytics</strong></td><td>Session attendance patterns and trends</td><td>May 20, 2026</td><td><button class="btn btn-sm btn-primary" onclick="showToast('Report downloaded')">⬇ Download</button></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div>
</div>

<!-- MODALS -->
<?php
$courseOptions = static function (array $courses): void {
    foreach ($courses as $course) {
        echo '<option value="' . (int) $course['id'] . '">' . e((string) $course['title']) . '</option>';
    }
};
$programOptions = static function (array $programs): void {
    echo '<option value="">No program</option>';
    foreach ($programs as $program) {
        echo '<option value="' . (int) $program['id'] . '">' . e((string) $program['title']) . '</option>';
    }
};
$instructorOptions = static function (array $instructors): void {
    echo '<option value="">Unassigned</option>';
    foreach ($instructors as $instructor) {
        echo '<option value="' . (int) $instructor['id'] . '">' . e((string) $instructor['name']) . '</option>';
    }
};
$learnerOptions = static function (array $learners): void {
    foreach ($learners as $learner) {
        echo '<option value="' . (int) $learner['id'] . '">' . e((string) $learner['name']) . ' (' . e((string) $learner['email']) . ')</option>';
    }
};
$cohortOptions = static function (array $cohorts): void {
    echo '<option value="">No cohort</option>';
    foreach ($cohorts as $cohort) {
        echo '<option value="' . (int) $cohort['id'] . '">' . e((string) $cohort['title']) . '</option>';
    }
};
$lessonOptions = static function (array $lessons): void {
    echo '<option value="">Course level</option>';
    foreach ($lessons as $lesson) {
        echo '<option value="' . (int) $lesson['id'] . '">' . e((string) $lesson['course_title']) . ' - ' . e((string) $lesson['title']) . '</option>';
    }
};
$assessmentOptions = static function (array $assessments): void {
    foreach ($assessments as $assessment) {
        echo '<option value="' . (int) $assessment['id'] . '">' . e((string) $assessment['course_title']) . ' - ' . e((string) $assessment['title']) . '</option>';
    }
};
?>

<div class="modal-overlay" id="programModal"><div class="modal"><form method="post">
  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="create_program"><input type="hidden" name="page" value="programs">
  <div class="modal-header"><div class="modal-title">Create New Program</div><button type="button" class="btn-icon" onclick="closeModal('programModal')">x</button></div>
  <div class="modal-body">
    <div class="form-group"><label class="form-label">Program Name</label><input class="form-input" name="title" required placeholder="e.g. Career Onboarding Program"></div>
    <div class="form-row"><div class="form-group"><label class="form-label">Audience Roles</label><input class="form-input" name="audience_roles" value="all"></div><div class="form-group"><label class="form-label">Sort Order</label><input class="form-input" name="sort_order" type="number" value="0"></div></div>
    <div class="form-group"><label class="form-label">Description</label><textarea class="form-textarea" name="description" placeholder="Program description..."></textarea></div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('programModal')">Cancel</button><button class="btn btn-primary" type="submit">Create Program</button></div>
</form></div></div>

<div class="modal-overlay" id="courseModal"><div class="modal"><form method="post">
  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="create_course"><input type="hidden" name="page" value="courses">
  <div class="modal-header"><div class="modal-title">Create New Course</div><button type="button" class="btn-icon" onclick="closeModal('courseModal')">x</button></div>
  <div class="modal-body">
    <div class="form-group"><label class="form-label">Course Title</label><input class="form-input" name="title" required placeholder="e.g. Coconut Value Chain Essentials"></div>
    <div class="form-row"><div class="form-group"><label class="form-label">Program</label><select class="form-select" name="program_id"><?php $programOptions($programs); ?></select></div><div class="form-group"><label class="form-label">Instructor Name</label><input class="form-input" name="instructor_name" placeholder="Lead facilitator"></div></div>
    <div class="form-row"><div class="form-group"><label class="form-label">Duration (hours)</label><input class="form-input" name="duration_hours" type="number" value="2" min="1"></div><div class="form-group"><label class="form-label">Price (NGN)</label><input class="form-input" name="price" type="number" value="0" min="0" step="0.01"></div></div>
    <div class="form-row"><div class="form-group"><label class="form-label">Start Time</label><input class="form-input" name="start_time" type="datetime-local"></div><div class="form-group"><label class="form-label">Delivery Type</label><select class="form-select" name="delivery_type"><option value="lms">LMS</option><option value="live_zoom">Live Zoom</option><option value="document">Document</option><option value="video">Video</option></select></div></div>
    <div class="form-row"><div class="form-group"><label class="form-label">Audience Roles</label><input class="form-input" name="target_roles" value="all"></div><div class="form-group"><label class="form-label">Pass Score</label><input class="form-input" name="pass_score" type="number" value="70"></div></div>
    <div class="form-group"><label class="form-label"><input type="checkbox" name="certification_required" checked> Certificate required</label></div>
    <div class="form-group"><label class="form-label">Delivery URL</label><input class="form-input" name="delivery_url" placeholder="https://..."></div>
    <div class="form-group"><label class="form-label">Description</label><textarea class="form-textarea" name="description" placeholder="Course description..."></textarea></div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('courseModal')">Cancel</button><button class="btn btn-primary" type="submit">Create Course</button></div>
</form></div></div>

<div class="modal-overlay" id="lessonModal"><div class="modal"><form method="post">
  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="create_lesson"><input type="hidden" name="page" value="lessons">
  <div class="modal-header"><div class="modal-title">Add New Lesson</div><button type="button" class="btn-icon" onclick="closeModal('lessonModal')">x</button></div>
  <div class="modal-body">
    <div class="form-group"><label class="form-label">Lesson Title</label><input class="form-input" name="title" required></div>
    <div class="form-row"><div class="form-group"><label class="form-label">Course</label><select class="form-select" name="webinar_id" required><?php $courseOptions($courses); ?></select></div><div class="form-group"><label class="form-label">Type</label><select class="form-select" name="delivery_type"><option value="video">Video</option><option value="document">Reading</option><option value="assignment">Assignment</option><option value="live">Live Session</option></select></div></div>
    <div class="form-row"><div class="form-group"><label class="form-label">Duration (minutes)</label><input class="form-input" name="duration_minutes" type="number" value="20"></div><div class="form-group"><label class="form-label">Sort Order</label><input class="form-input" name="sort_order" type="number" value="0"></div></div>
    <div class="form-group"><label class="form-label">Material URL</label><input class="form-input" name="material_url"></div>
    <div class="form-group"><label class="form-label">Summary</label><textarea class="form-textarea" name="summary"></textarea></div>
    <div class="form-group"><label class="form-label">Content / Notes</label><textarea class="form-textarea" name="content"></textarea></div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('lessonModal')">Cancel</button><button class="btn btn-primary" type="submit">Add Lesson</button></div>
</form></div></div>

<div class="modal-overlay" id="materialModal"><div class="modal"><form method="post">
  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="create_material"><input type="hidden" name="page" value="materials">
  <div class="modal-header"><div class="modal-title">Add Course Material</div><button type="button" class="btn-icon" onclick="closeModal('materialModal')">x</button></div>
  <div class="modal-body">
    <div class="form-group"><label class="form-label">Material Title</label><input class="form-input" name="title" required placeholder="e.g. Module 1 reading pack"></div>
    <div class="form-row"><div class="form-group"><label class="form-label">Course</label><select class="form-select" name="webinar_id" required><?php $courseOptions($courses); ?></select></div><div class="form-group"><label class="form-label">Lesson</label><select class="form-select" name="lesson_id"><?php $lessonOptions($lessons); ?></select></div></div>
    <div class="form-row"><div class="form-group"><label class="form-label">Type</label><select class="form-select" name="material_type"><option value="link">Link</option><option value="pdf">PDF</option><option value="video">Video</option><option value="document">Document</option><option value="assignment">Assignment</option></select></div><div class="form-group"><label class="form-label">Sort Order</label><input class="form-input" name="sort_order" type="number" value="0"></div></div>
    <div class="form-group"><label class="form-label">Material URL</label><input class="form-input" name="material_url" placeholder="https://..."></div>
    <div class="form-group"><label class="form-label">File Path</label><input class="form-input" name="file_path" placeholder="academy_uploads/..."></div>
    <div class="form-group"><label class="form-label">Notes</label><textarea class="form-textarea" name="notes"></textarea></div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('materialModal')">Cancel</button><button class="btn btn-primary" type="submit">Save Material</button></div>
</form></div></div>

<div class="modal-overlay" id="assessmentModal"><div class="modal"><form method="post">
  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="create_assessment"><input type="hidden" name="page" value="assessments">
  <div class="modal-header"><div class="modal-title">Create Assessment</div><button type="button" class="btn-icon" onclick="closeModal('assessmentModal')">x</button></div>
  <div class="modal-body">
    <div class="form-group"><label class="form-label">Assessment Title</label><input class="form-input" name="title" required></div>
    <div class="form-row"><div class="form-group"><label class="form-label">Course</label><select class="form-select" name="webinar_id" required><?php $courseOptions($courses); ?></select></div><div class="form-group"><label class="form-label">Max Attempts</label><input class="form-input" name="max_attempts" type="number" value="3"></div></div>
    <div class="form-group"><label class="form-label">Passing Score (%)</label><input class="form-input" name="pass_score" type="number" value="70"></div>
    <div class="form-group"><label class="form-label">Instructions</label><textarea class="form-textarea" name="instructions"></textarea></div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('assessmentModal')">Cancel</button><button class="btn btn-primary" type="submit">Create Assessment</button></div>
</form></div></div>

<div class="modal-overlay" id="questionModal"><div class="modal"><form method="post">
  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="create_question"><input type="hidden" name="page" value="questions">
  <div class="modal-header"><div class="modal-title">Add Question</div><button type="button" class="btn-icon" onclick="closeModal('questionModal')">x</button></div>
  <div class="modal-body">
    <div class="form-group"><label class="form-label">Assessment</label><select class="form-select" name="assessment_id" required><?php $assessmentOptions($assessments); ?></select></div>
    <div class="form-group"><label class="form-label">Question Text</label><textarea class="form-textarea" name="question_text" required></textarea></div>
    <div class="form-row"><div class="form-group"><label class="form-label">Option A</label><input class="form-input" name="option_a" required></div><div class="form-group"><label class="form-label">Option B</label><input class="form-input" name="option_b" required></div></div>
    <div class="form-row"><div class="form-group"><label class="form-label">Option C</label><input class="form-input" name="option_c"></div><div class="form-group"><label class="form-label">Option D</label><input class="form-input" name="option_d"></div></div>
    <div class="form-row"><div class="form-group"><label class="form-label">Correct Option</label><select class="form-select" name="correct_option"><option>A</option><option>B</option><option>C</option><option>D</option></select></div><div class="form-group"><label class="form-label">Points</label><input class="form-input" name="points" type="number" step="0.5" value="1"></div></div>
    <div class="form-group"><label class="form-label">Sort Order</label><input class="form-input" name="sort_order" type="number" value="0"></div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('questionModal')">Cancel</button><button class="btn btn-primary" type="submit">Save Question</button></div>
</form></div></div>

<div class="modal-overlay" id="cohortModal"><div class="modal"><form method="post">
  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="create_cohort"><input type="hidden" name="page" value="cohorts">
  <div class="modal-header"><div class="modal-title">Create New Cohort</div><button type="button" class="btn-icon" onclick="closeModal('cohortModal')">x</button></div>
  <div class="modal-body">
    <div class="form-group"><label class="form-label">Cohort Name</label><input class="form-input" name="title" required></div>
    <div class="form-row"><div class="form-group"><label class="form-label">Course</label><select class="form-select" name="webinar_id" required><?php $courseOptions($courses); ?></select></div><div class="form-group"><label class="form-label">Instructor</label><select class="form-select" name="instructor_id"><?php $instructorOptions($instructors); ?></select></div></div>
    <div class="form-row"><div class="form-group"><label class="form-label">Start Date</label><input class="form-input" name="start_date" type="date" required></div><div class="form-group"><label class="form-label">Start Time</label><input class="form-input" name="start_time" type="time" value="09:00"></div></div>
    <div class="form-row"><div class="form-group"><label class="form-label">End Date</label><input class="form-input" name="end_date" type="date"></div><div class="form-group"><label class="form-label">Capacity</label><input class="form-input" name="capacity" type="number" value="100"></div></div>
    <div class="form-group"><label class="form-label">Venue</label><input class="form-input" name="venue"></div><div class="form-group"><label class="form-label">Meeting URL</label><input class="form-input" name="meeting_url"></div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('cohortModal')">Cancel</button><button class="btn btn-primary" type="submit">Create Cohort</button></div>
</form></div></div>

<div class="modal-overlay" id="attendanceModal"><div class="modal"><form method="post">
  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="mark_attendance"><input type="hidden" name="page" value="attendance">
  <div class="modal-header"><div class="modal-title">Mark Attendance</div><button type="button" class="btn-icon" onclick="closeModal('attendanceModal')">x</button></div>
  <div class="modal-body">
    <div class="form-group"><label class="form-label">Cohort</label><select class="form-select" name="cohort_id" required><?php foreach ($cohorts as $cohort) { echo '<option value="' . (int) $cohort['id'] . '">' . e((string) $cohort['title']) . '</option>'; } ?></select></div>
    <div class="form-group"><label class="form-label">Learner</label><select class="form-select" name="user_id" required><?php $learnerOptions($academyLearners); ?></select></div>
    <div class="form-group"><label class="form-label">Status</label><select class="form-select" name="status"><option value="present">Present</option><option value="late">Late</option><option value="absent">Absent</option><option value="excused">Excused</option></select></div>
    <div class="form-group"><label class="form-label">Notes</label><textarea class="form-textarea" name="notes"></textarea></div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('attendanceModal')">Cancel</button><button class="btn btn-primary" type="submit">Save Attendance</button></div>
</form></div></div>

<div class="modal-overlay" id="instructorModal"><div class="modal"><form method="post">
  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="create_instructor"><input type="hidden" name="page" value="instructors">
  <div class="modal-header"><div class="modal-title">Add Instructor</div><button type="button" class="btn-icon" onclick="closeModal('instructorModal')">x</button></div>
  <div class="modal-body"><div class="form-row"><div class="form-group"><label class="form-label">First Name</label><input class="form-input" name="first_name" required></div><div class="form-group"><label class="form-label">Last Name</label><input class="form-input" name="last_name"></div></div><div class="form-group"><label class="form-label">Email</label><input class="form-input" name="email" type="email"></div><div class="form-group"><label class="form-label">Phone</label><input class="form-input" name="phone"></div><div class="form-group"><label class="form-label">Specialization</label><input class="form-input" name="specialty"></div><div class="form-group"><label class="form-label">Bio</label><textarea class="form-textarea" name="bio"></textarea></div></div>
  <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('instructorModal')">Cancel</button><button class="btn btn-primary" type="submit">Add Instructor</button></div>
</form></div></div>

<div class="modal-overlay" id="reminderModal"><div class="modal"><form method="post">
  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="create_reminder"><input type="hidden" name="page" value="reminders">
  <div class="modal-header"><div class="modal-title">Create Reminder</div><button type="button" class="btn-icon" onclick="closeModal('reminderModal')">x</button></div>
  <div class="modal-body"><div class="form-group"><label class="form-label">Reminder Title</label><input class="form-input" name="title" required></div><div class="form-row"><div class="form-group"><label class="form-label">Course</label><select class="form-select" name="webinar_id"><option value="">Any course</option><?php $courseOptions($courses); ?></select></div><div class="form-group"><label class="form-label">Cohort</label><select class="form-select" name="cohort_id"><?php $cohortOptions($cohorts); ?></select></div></div><div class="form-row"><div class="form-group"><label class="form-label">Schedule Date</label><input class="form-input" name="send_date" type="date"></div><div class="form-group"><label class="form-label">Time</label><input class="form-input" name="send_time" type="time" value="09:00"></div></div><div class="form-row"><div class="form-group"><label class="form-label">Channel</label><select class="form-select" name="channel"><option value="dashboard">Dashboard</option><option value="email">Email</option><option value="sms">SMS</option><option value="whatsapp">WhatsApp</option></select></div><div class="form-group"><label class="form-label">Audience Roles</label><input class="form-input" name="audience_roles" value="all"></div></div><div class="form-group"><label class="form-label">Message</label><textarea class="form-textarea" name="message" required></textarea></div></div>
  <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('reminderModal')">Cancel</button><button class="btn btn-primary" type="submit">Schedule</button></div>
</form></div></div>

<div class="modal-overlay" id="learnerModal"><div class="modal"><form method="post">
  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="enroll_learner"><input type="hidden" name="page" value="learners">
  <div class="modal-header"><div class="modal-title">Add Learner</div><button type="button" class="btn-icon" onclick="closeModal('learnerModal')">x</button></div>
  <div class="modal-body"><div class="form-row"><div class="form-group"><label class="form-label">First Name</label><input class="form-input" name="first_name" required></div><div class="form-group"><label class="form-label">Last Name</label><input class="form-input" name="last_name"></div></div><div class="form-group"><label class="form-label">Email</label><input class="form-input" name="email" type="email" required></div><div class="form-group"><label class="form-label">Enroll In Course</label><select class="form-select" name="webinar_id" required><?php $courseOptions($courses); ?></select></div></div>
  <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('learnerModal')">Cancel</button><button class="btn btn-primary" type="submit">Enroll Learner</button></div>
</form></div></div>

<div class="modal-overlay" id="certificateModal"><div class="modal"><form method="post">
  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="issue_certificate"><input type="hidden" name="page" value="certificates">
  <div class="modal-header"><div class="modal-title">Issue Certificate</div><button type="button" class="btn-icon" onclick="closeModal('certificateModal')">x</button></div>
  <div class="modal-body"><div class="form-group"><label class="form-label">Learner</label><select class="form-select" name="user_id" required><?php $learnerOptions($academyLearners); ?></select></div><div class="form-group"><label class="form-label">Course</label><select class="form-select" name="webinar_id" required><?php $courseOptions($courses); ?></select></div><div class="form-row"><div class="form-group"><label class="form-label">Grade / Notes</label><input class="form-input" name="notes" placeholder="A (95%)"></div><div class="form-group"><label class="form-label">Issue Date</label><input class="form-input" name="issued_at" type="date"></div></div><div class="form-group"><label class="form-label">Certificate Ref (optional)</label><input class="form-input" name="certificate_ref" placeholder="Auto-generated if empty"></div></div>
  <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('certificateModal')">Cancel</button><button class="btn btn-primary" type="submit">Issue Certificate</button></div>
</form></div></div>

<div class="modal-overlay" id="pathwayModal"><div class="modal"><form method="post">
  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="create_pathway"><input type="hidden" name="page" value="pathways">
  <div class="modal-header"><div class="modal-title">Create Certificate Pathway</div><button type="button" class="btn-icon" onclick="closeModal('pathwayModal')">x</button></div>
  <div class="modal-body"><div class="form-group"><label class="form-label">Pathway Name</label><input class="form-input" name="title" required></div><div class="form-row"><div class="form-group"><label class="form-label">Audience Roles</label><input class="form-input" name="audience_roles" value="all"></div><div class="form-group"><label class="form-label">Sort Order</label><input class="form-input" name="sort_order" type="number" value="0"></div></div><div class="form-group"><label class="form-label">Required Courses</label><select class="form-select" name="webinar_ids[]" multiple style="min-height:120px"><?php $courseOptions($courses); ?></select></div><div class="form-group"><label class="form-label"><input type="checkbox" name="certificate_approval_required" checked> Require admin approval</label></div><div class="form-group"><label class="form-label">Description</label><textarea class="form-textarea" name="description"></textarea></div></div>
  <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('pathwayModal')">Cancel</button><button class="btn btn-primary" type="submit">Create Pathway</button></div>
</form></div></div>

<div class="modal-overlay" id="refundModal"><div class="modal"><form method="post">
  <input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="create_refund"><input type="hidden" name="page" value="refunds">
  <div class="modal-header"><div class="modal-title">New Refund Request</div><button type="button" class="btn-icon" onclick="closeModal('refundModal')">x</button></div>
  <div class="modal-body"><div class="form-group"><label class="form-label">Learner</label><select class="form-select" name="user_id" required><?php $learnerOptions($academyLearners); ?></select></div><div class="form-group"><label class="form-label">Course</label><select class="form-select" name="webinar_id" required><?php $courseOptions($courses); ?></select></div><div class="form-row"><div class="form-group"><label class="form-label">Amount (NGN)</label><input class="form-input" name="amount" type="number" step="0.01" min="0"></div><div class="form-group"><label class="form-label">Reason</label><select class="form-select" name="reason"><option>Course mismatch</option><option>Technical issues</option><option>Personal reasons</option><option>Duplicate enrollment</option><option>Payment access issue</option></select></div></div><div class="form-group"><label class="form-label">Admin Notes</label><textarea class="form-textarea" name="admin_notes"></textarea></div></div>
  <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('refundModal')">Cancel</button><button class="btn btn-primary" type="submit">Submit Request</button></div>
</form></div></div>

<div class="toast" id="toast"></div>

<script>
const academyWorkspaceData = <?= json_encode($academyWorkspaceData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
const initialAcademyPage = <?= json_encode($activePage) ?>;

function htmlEscape(value) {
  return String(value ?? '').replace(/[&<>"']/g, char => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;'
  }[char]));
}

function renderRows(selector, rows, emptyMessage, template) {
  const body = document.querySelector(selector);
  if (!body) return;
  if (!rows || rows.length === 0) {
    body.innerHTML = `<tr><td colspan="8">${htmlEscape(emptyMessage)}</td></tr>`;
    return;
  }
  body.innerHTML = rows.map(template).join('');
}

function hydrateAcademyWorkspace() {
  renderRows('#programsTable tbody', academyWorkspaceData.programs, 'No programs have been created.', row => `
    <tr>
      <td><strong>${htmlEscape(row.title)}</strong></td>
      <td>${htmlEscape(row.courses)}</td>
      <td>${htmlEscape(row.learners)}</td>
      <td>${htmlEscape(row.duration)}</td>
      <td><div class="progress-bar" style="width:120px"><div class="progress-fill" style="width:${Number(row.completion || 0)}%"></div></div> ${htmlEscape(row.completion)}%</td>
      <td><span class="status-badge ${htmlEscape(row.statusClass)}">${htmlEscape(row.status)}</span></td>
      <td><button class="btn-icon" title="Program actions">...</button></td>
    </tr>`);

  renderRows('#coursesTable tbody', academyWorkspaceData.courses, 'No courses have been created.', row => `
    <tr>
      <td><strong>${htmlEscape(row.title)}</strong></td>
      <td>${htmlEscape(row.program)}</td>
      <td>${htmlEscape(row.lessons)}</td>
      <td>${htmlEscape(row.enrolled)}</td>
      <td>${htmlEscape(row.rating)}</td>
      <td><span class="status-badge ${htmlEscape(row.statusClass)}">${htmlEscape(row.status)}</span></td>
      <td><button class="btn-icon" title="Course actions">...</button></td>
    </tr>`);

  renderRows('#lessonsTable tbody', academyWorkspaceData.lessons, 'No lessons have been created.', row => `
    <tr>
      <td><strong>${htmlEscape(row.title)}</strong></td>
      <td>${htmlEscape(row.course)}</td>
      <td><span class="chip">${htmlEscape(row.type)}</span></td>
      <td>${htmlEscape(row.duration)}</td>
      <td>${htmlEscape(row.completion)}</td>
      <td><span class="status-badge ${htmlEscape(row.statusClass)}">${htmlEscape(row.status)}</span></td>
      <td><button class="btn-icon" title="Lesson actions">...</button></td>
    </tr>`);

  renderRows('#learnersTable tbody', academyWorkspaceData.learners, 'No learners are enrolled yet.', row => `
    <tr>
      <td><div class="avatar-row"><div class="avatar-sm">${htmlEscape(row.initials)}</div><div><strong>${htmlEscape(row.name)}</strong><br><small style="color:var(--text-secondary)">Academy learner</small></div></div></td>
      <td>${htmlEscape(row.email)}</td>
      <td>${htmlEscape(row.program)}</td>
      <td>${htmlEscape(row.enrolled)}</td>
      <td><div class="progress-bar" style="width:100px"><div class="progress-fill" style="width:${Number(row.progress || 0)}%"></div></div> ${htmlEscape(row.progress)}%</td>
      <td><span class="status-badge ${htmlEscape(row.statusClass)}">${htmlEscape(row.status)}</span></td>
      <td><button class="btn-icon" title="Learner actions">...</button></td>
    </tr>`);

  renderRows('#page-certificates table tbody', academyWorkspaceData.certificates, 'No certificate records yet.', row => `
    <tr>
      <td><strong>${htmlEscape(row.ref)}</strong></td>
      <td>${htmlEscape(row.learner)}</td>
      <td>${htmlEscape(row.course)}</td>
      <td>${htmlEscape(row.issued)}</td>
      <td>${htmlEscape(row.grade)}</td>
      <td><span class="status-badge ${htmlEscape(row.statusClass)}">${htmlEscape(row.status)}</span></td>
      <td><button class="btn-icon" title="Certificate actions">...</button></td>
    </tr>`);

  renderRows('#page-refunds table tbody', academyWorkspaceData.refunds, 'No refund requests yet.', row => `
    <tr>
      <td><strong>${htmlEscape(row.ref)}</strong></td>
      <td>${htmlEscape(row.learner)}</td>
      <td>${htmlEscape(row.course)}</td>
      <td>${htmlEscape(row.amount)}</td>
      <td>${htmlEscape(row.reason)}</td>
      <td>${htmlEscape(row.date)}</td>
      <td><span class="status-badge ${htmlEscape(row.statusClass)}">${htmlEscape(row.status)}</span></td>
      <td><button class="btn-icon" title="Refund actions">...</button></td>
    </tr>`);
}

function navigateTo(page) {
  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  const pageEl = document.getElementById('page-' + page);
  if (pageEl) pageEl.classList.add('active');
  const navEl = document.querySelector(`.nav-item[data-page="${page}"]`);
  if (navEl) navEl.classList.add('active');
  window.scrollTo(0, 0);
}

document.querySelectorAll('.nav-item').forEach(item => {
  item.addEventListener('click', () => {
    const page = item.getAttribute('data-page');
    if (page) {
      navigateTo(page);
      const url = new URL(window.location.href);
      url.searchParams.set('page', page);
      history.replaceState(null, '', url);
    }
  });
});

hydrateAcademyWorkspace();
enhanceAcademyActions();
paginateAcademyTables();
navigateTo(initialAcademyPage);

function academyExportUrl(type) {
  const url = new URL(window.location.href);
  url.searchParams.set('export', type);
  url.searchParams.set('page', 'reports');
  return url.toString();
}

function enhanceAcademyActions() {
  const pageExports = {
    'page-dashboard': 'courses',
    'page-feedback': 'feedback',
    'page-reports': 'courses'
  };
  Object.entries(pageExports).forEach(([pageId, type]) => {
    document.querySelectorAll(`#${pageId} .page-header .btn-primary`).forEach(btn => {
      btn.onclick = () => { window.location.href = academyExportUrl(type); };
      if (/generate/i.test(btn.textContent)) btn.textContent = 'Generate CSV';
    });
  });
  const reportTypes = ['progress','courses','learners','certificates','attendance'];
  document.querySelectorAll('#page-reports tbody .btn-primary').forEach((btn, index) => {
    btn.onclick = () => { window.location.href = academyExportUrl(reportTypes[index] || 'courses'); };
  });
  document.querySelectorAll('#page-refunds tbody .btn-primary').forEach(btn => {
    btn.onclick = () => navigateTo('refunds');
  });
  document.querySelectorAll('.btn-icon').forEach(btn => {
    if (!btn.textContent.trim()) btn.textContent = '...';
  });
}

function paginateAcademyTables(pageSize = 25) {
  document.querySelectorAll('.page table').forEach(table => {
    const tbody = table.querySelector('tbody');
    if (!tbody || table.dataset.paginated === '1') return;
    const rows = Array.from(tbody.querySelectorAll('tr'));
    if (rows.length <= pageSize) return;
    table.dataset.paginated = '1';
    let page = 1;
    const totalPages = Math.ceil(rows.length / pageSize);
    const nav = document.createElement('div');
    nav.className = 'pagination';
    nav.style.cssText = 'display:flex;gap:8px;align-items:center;margin:12px 22px;flex-wrap:wrap';
    const render = () => {
      rows.forEach((row, index) => {
        row.style.display = index >= (page - 1) * pageSize && index < page * pageSize ? '' : 'none';
      });
      nav.innerHTML = `<button class="btn btn-sm btn-secondary" type="button" ${page === 1 ? 'disabled' : ''}>Previous</button><span class="chip">Page ${page} of ${totalPages}</span><button class="btn btn-sm btn-secondary" type="button" ${page === totalPages ? 'disabled' : ''}>Next</button>`;
      nav.querySelector('button:first-child')?.addEventListener('click', () => { page = Math.max(1, page - 1); render(); });
      nav.querySelector('button:last-child')?.addEventListener('click', () => { page = Math.min(totalPages, page + 1); render(); });
    };
    table.closest('.card-body')?.appendChild(nav);
    render();
  });
}

function openModal(id) { const modal = document.getElementById(id); if (modal) modal.classList.add('active'); }
function closeModal(id) { const modal = document.getElementById(id); if (modal) modal.classList.remove('active'); }

document.querySelectorAll('.modal-overlay').forEach(overlay => {
  overlay.addEventListener('click', e => {
    if (e.target === overlay) overlay.classList.remove('active');
  });
});

function showToast(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.style.display = 'block';
  setTimeout(() => t.style.display = 'none', 2500);
}

function filterTable(tableId, query) {
  const table = document.getElementById(tableId);
  if (!table) return;
  const rows = table.querySelectorAll('tbody tr');
  const q = query.toLowerCase();
  rows.forEach(row => {
    row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
}

function filterStatus(tableId, status) {
  const table = document.getElementById(tableId);
  if (!table) return;
  const rows = table.querySelectorAll('tbody tr');
  rows.forEach(row => {
    if (!status) { row.style.display = ''; return; }
    row.style.display = row.textContent.includes(status) ? '' : 'none';
  });
}

function switchTab(el, tabId) {
  el.parentElement.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
  el.classList.add('active');
}

function filterLessons() {
  const val = document.getElementById('lessonCourseFilter').value;
  const rows = document.querySelectorAll('#lessonsTable tbody tr');
  rows.forEach(row => {
    if (!val) { row.style.display = ''; return; }
    row.style.display = row.textContent.includes(val) ? '' : 'none';
  });
}

document.getElementById('globalSearch').addEventListener('input', function() {
  const q = this.value.toLowerCase();
  if (q.length < 2) return;
  const pages = ['programs','courses','lessons','assessments','cohorts','instructors','attendance','reminders','learners','certificates','pathways','refunds','feedback','reports'];
  for (const p of pages) {
    const el = document.getElementById('page-' + p);
    if (el && el.textContent.toLowerCase().includes(q)) {
      navigateTo(p);
      break;
    }
  }
});
document.querySelectorAll('[data-topbar-menu]').forEach(button => {
  button.addEventListener('click', event => {
    event.stopPropagation();
    const menu = document.getElementById(button.dataset.topbarMenu);
    document.querySelectorAll('.topbar-menu.active').forEach(open => { if (open !== menu) open.classList.remove('active'); });
    menu?.classList.toggle('active');
    button.setAttribute('aria-expanded', menu?.classList.contains('active') ? 'true' : 'false');
  });
});
document.addEventListener('click', event => {
  if (!event.target.closest('.topbar-menu-wrap')) {
    document.querySelectorAll('.topbar-menu.active').forEach(menu => menu.classList.remove('active'));
    document.querySelectorAll('[aria-expanded="true"]').forEach(button => button.setAttribute('aria-expanded', 'false'));
  }
});
</script>
</body>
</html>

