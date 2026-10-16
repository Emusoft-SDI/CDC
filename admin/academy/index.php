<?php
declare(strict_types=1);

require_once __DIR__ . '/../_auth.php';
require_once __DIR__ . '/../../lib/admin-layout.php';
require_once __DIR__ . '/../../lib/admin-operator-strip.php';
require_once __DIR__ . '/../../lib/academy.php';
require_once __DIR__ . '/../../lib/wallet.php';

$pdo = db();
admin_ensure_schema($pdo);
academy_ensure_schema($pdo);
admin_require($pdo, 'training');

defined('ACADEMY_ADMIN_ROUTE_BASE') || define('ACADEMY_ADMIN_ROUTE_BASE', 'index.php');

$message = '';
$error = '';
$tab = str_replace('-', '_', preg_replace('/[^a-z_-]/', '', (string) ($_GET['page'] ?? $_GET['tab'] ?? 'overview')) ?: 'overview');
$allowedTabs = ['overview', 'homepage', 'programs', 'courses', 'certificate_groups', 'lessons', 'assessments', 'calendar', 'instructors', 'attendance', 'reminders', 'feedback', 'enrollments', 'certificates', 'refunds', 'reports'];
if (!in_array($tab, $allowedTabs, true)) {
    $tab = 'overview';
}

function academy_admin_redirect(string $tab, string $key, string $message): void
{
    $pages = [
        'overview' => ACADEMY_ADMIN_ROUTE_BASE,
        'homepage' => ACADEMY_ADMIN_ROUTE_BASE . '?page=homepage',
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
                $prerequisites = trim((string) ($_POST['prerequisites'] ?? ''));
                if ($title === '') {
                    throw new RuntimeException('Program title is required.');
                }
                $thumbnail = academy_upload_homepage_image('thumbnail', 'thumbnails');
                if ($programId > 0) {
                    if ($thumbnail !== '') {
                        $stmt = $pdo->prepare("UPDATE academy_programs SET title = ?, description = ?, audience_roles = ?, status = ?, sort_order = ?, thumbnail = ?, prerequisites = ? WHERE id = ?");
                        $stmt->execute([$title, $description, $audienceRoles, $status, $sort, $thumbnail, $prerequisites, $programId]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE academy_programs SET title = ?, description = ?, audience_roles = ?, status = ?, sort_order = ?, prerequisites = ? WHERE id = ?");
                        $stmt->execute([$title, $description, $audienceRoles, $status, $sort, $prerequisites, $programId]);
                    }
                } else {
                    $stmt = $pdo->prepare("INSERT INTO academy_programs (title, description, audience_roles, status, sort_order, thumbnail, prerequisites) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$title, $description, $audienceRoles, $status, $sort, $thumbnail, $prerequisites]);
                }
                academy_admin_redirect('programs', 'message', 'Academy program saved.');
            }

            if ($action === 'save_program_thumbnail') {
                $programId = (int) ($_POST['program_id'] ?? 0);
                if ($programId <= 0) {
                    throw new RuntimeException('Select a program first.');
                }
                if (isset($_POST['remove_thumbnail'])) {
                    $pdo->prepare("UPDATE academy_programs SET thumbnail = NULL WHERE id = ?")->execute([$programId]);
                    academy_admin_redirect('programs', 'message', 'Program image removed.');
                }
                $programThumbnail = academy_upload_homepage_image('program_thumbnail', 'thumbnails');
                if ($programThumbnail === '') {
                    throw new RuntimeException('Choose a program image to upload.');
                }
                $pdo->prepare("UPDATE academy_programs SET thumbnail = ? WHERE id = ?")->execute([$programThumbnail, $programId]);
                academy_admin_redirect('programs', 'message', 'Program image saved.');
            }

            if ($action === 'delete_program') {
                $programId = (int) ($_POST['program_id'] ?? 0);
                if ($programId <= 0) {
                    throw new RuntimeException('Select a program to delete.');
                }
                $exists = $pdo->prepare("SELECT id FROM academy_programs WHERE id = ?");
                $exists->execute([$programId]);
                if (!$exists->fetchColumn()) {
                    throw new RuntimeException('Program not found.');
                }
                $pdo->beginTransaction();
                $pdo->prepare("UPDATE webinars SET program_id = NULL WHERE program_id = ?")->execute([$programId]);
                $pdo->prepare("DELETE FROM academy_programs WHERE id = ?")->execute([$programId]);
                $pdo->commit();
                academy_admin_redirect('programs', 'message', 'Program deleted. Its courses were moved back to the unassigned list.');
            }

            if ($action === 'save_homepage') {
                $currentHomepage = academy_homepage_settings($pdo);
                $heroPath = (string) $currentHomepage['hero_image'];
                if (isset($_POST['remove_hero'])) {
                    $heroPath = '';
                } else {
                    $uploadedHero = academy_upload_homepage_image('hero_image', 'hero');
                    if ($uploadedHero !== '') {
                        $heroPath = $uploadedHero;
                    }
                }
                $featuredCourseId = (int) ($_POST['featured_course_id'] ?? 0);
                if ($featuredCourseId > 0) {
                    $featuredCheck = $pdo->prepare("SELECT id FROM webinars WHERE id = ? AND status = 'active' LIMIT 1");
                    $featuredCheck->execute([$featuredCourseId]);
                    if (!$featuredCheck->fetchColumn()) {
                        throw new RuntimeException('Featured course must be an active Academy course.');
                    }
                }
                $trustBadges = [];
                for ($index = 0; $index < 3; $index++) {
                    $trustBadges[] = trim(strip_tags((string) ($_POST['trust_label'][$index] ?? '')));
                }
                $pathwayCards = [];
                for ($index = 0; $index < 4; $index++) {
                    $pathwayCards[] = [
                        'icon' => preg_replace('/[^a-z0-9-]/', '', (string) ($_POST['pathway_icon'][$index] ?? '')) ?? '',
                        'title' => trim(strip_tags((string) ($_POST['pathway_title'][$index] ?? ''))),
                        'text' => trim(strip_tags((string) ($_POST['pathway_text'][$index] ?? ''))),
                    ];
                }
                academy_homepage_save($pdo, [
                    'hero_image' => $heroPath,
                    'eyebrow' => trim(strip_tags((string) ($_POST['eyebrow'] ?? ''))),
                    'headline' => academy_homepage_html((string) ($_POST['headline'] ?? '')),
                    'lede' => academy_homepage_html((string) ($_POST['lede'] ?? '')),
                    'featured_course_id' => $featuredCourseId,
                    'trust' => $trustBadges,
                    'pathways' => $pathwayCards,
                    'cta_heading' => academy_homepage_html((string) ($_POST['cta_heading'] ?? '')),
                    'cta_copy' => academy_homepage_html((string) ($_POST['cta_copy'] ?? '')),
                ]);
                academy_admin_redirect('homepage', 'message', 'Homepage settings saved.');
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

            if ($action === 'course_status') {
                $courseId = (int) ($_POST['course_id'] ?? 0);
                $status = in_array((string) ($_POST['status'] ?? 'active'), ['active', 'draft', 'paused', 'archived'], true) ? (string) $_POST['status'] : 'active';
                if ($courseId <= 0) {
                    throw new RuntimeException('Select a course to update.');
                }
                $stmt = $pdo->prepare("UPDATE webinars SET status = ? WHERE id = ?");
                $stmt->execute([$status, $courseId]);
                $label = ['active' => 'live', 'draft' => 'draft', 'paused' => 'disabled', 'archived' => 'discontinued'][$status];
                academy_admin_redirect('courses', 'message', 'Course set to ' . $label . '.');
            }

            if ($action === 'delete_course') {
                $courseId = (int) ($_POST['course_id'] ?? 0);
                if ($courseId <= 0) {
                    throw new RuntimeException('Select a course to delete.');
                }
                $exists = $pdo->prepare("SELECT id FROM webinars WHERE id = ?");
                $exists->execute([$courseId]);
                if (!$exists->fetchColumn()) {
                    throw new RuntimeException('Course not found.');
                }
                $pdo->beginTransaction();
                $pdo->prepare("DELETE FROM academy_lessons WHERE webinar_id = ?")->execute([$courseId]);
                $pdo->prepare("DELETE q FROM academy_questions q JOIN academy_assessments a ON a.id = q.assessment_id WHERE a.webinar_id = ?")->execute([$courseId]);
                $pdo->prepare("DELETE FROM academy_assessments WHERE webinar_id = ?")->execute([$courseId]);
                $pdo->prepare("DELETE FROM academy_attempts WHERE webinar_id = ?")->execute([$courseId]);
                $pdo->prepare("DELETE FROM academy_feedback WHERE webinar_id = ?")->execute([$courseId]);
                $pdo->prepare("DELETE FROM academy_certificates WHERE webinar_id = ?")->execute([$courseId]);
                $pdo->prepare("DELETE FROM academy_certificate_group_courses WHERE webinar_id = ?")->execute([$courseId]);
                $pdo->prepare("DELETE FROM academy_cohorts WHERE webinar_id = ?")->execute([$courseId]);
                $pdo->prepare("DELETE FROM academy_reminders WHERE webinar_id = ?")->execute([$courseId]);
                $pdo->prepare("DELETE FROM webinar_registrations WHERE webinar_id = ?")->execute([$courseId]);
                $pdo->prepare("DELETE FROM webinars WHERE id = ?")->execute([$courseId]);
                $pdo->commit();
                academy_admin_redirect('courses', 'message', 'Course deleted.');
            }

            if ($action === 'batch_course_action') {
                $courseIds = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['course_ids'] ?? [])))));
                $batchAction = (string) ($_POST['batch_action'] ?? '');
                if (!$courseIds) {
                    throw new RuntimeException('Select at least one course.');
                }
                if (!in_array($batchAction, ['live', 'disable', 'discontinue', 'delete'], true)) {
                    throw new RuntimeException('Choose a batch action.');
                }
                $statusMap = ['live' => 'active', 'disable' => 'paused', 'discontinue' => 'archived'];
                if ($batchAction === 'delete') {
                    $pdo->beginTransaction();
                    try {
                        foreach ($courseIds as $cid) {
                            $pdo->prepare("DELETE FROM academy_lessons WHERE webinar_id = ?")->execute([$cid]);
                            $pdo->prepare("DELETE q FROM academy_questions q JOIN academy_assessments a ON a.id = q.assessment_id WHERE a.webinar_id = ?")->execute([$cid]);
                            $pdo->prepare("DELETE FROM academy_assessments WHERE webinar_id = ?")->execute([$cid]);
                            $pdo->prepare("DELETE FROM academy_attempts WHERE webinar_id = ?")->execute([$cid]);
                            $pdo->prepare("DELETE FROM academy_feedback WHERE webinar_id = ?")->execute([$cid]);
                            $pdo->prepare("DELETE FROM academy_certificates WHERE webinar_id = ?")->execute([$cid]);
                            $pdo->prepare("DELETE FROM academy_certificate_group_courses WHERE webinar_id = ?")->execute([$cid]);
                            $pdo->prepare("DELETE FROM academy_cohorts WHERE webinar_id = ?")->execute([$cid]);
                            $pdo->prepare("DELETE FROM academy_reminders WHERE webinar_id = ?")->execute([$cid]);
                            $pdo->prepare("DELETE FROM webinar_registrations WHERE webinar_id = ?")->execute([$cid]);
                            $pdo->prepare("DELETE FROM webinars WHERE id = ?")->execute([$cid]);
                        }
                        $pdo->commit();
                    } catch (Throwable $e) {
                        $pdo->rollBack();
                        throw $e;
                    }
                    academy_admin_redirect('courses', 'message', count($courseIds) . ' course(s) deleted.');
                }
                $status = $statusMap[$batchAction];
                $stmt = $pdo->prepare("UPDATE webinars SET status = ? WHERE id = ?");
                foreach ($courseIds as $cid) {
                    $stmt->execute([$status, $cid]);
                }
                $label = ['live' => 'live', 'disable' => 'disabled', 'discontinue' => 'discontinued'][$batchAction];
                academy_admin_redirect('courses', 'message', count($courseIds) . ' course(s) set to ' . $label . '.');
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
                $lessonId = (int) ($_POST['lesson_id'] ?? 0);
                $courseId = (int) ($_POST['webinar_id'] ?? 0);
                $title = trim((string) ($_POST['title'] ?? ''));
                if ($courseId <= 0 || $title === '') {
                    throw new RuntimeException('Course and lesson title are required.');
                }
                $videoUrl = trim((string) ($_POST['video_url'] ?? '')) ?: null;
                $audioUrl = trim((string) ($_POST['audio_url'] ?? '')) ?: null;
                $infographicUrl = trim((string) ($_POST['infographic_url'] ?? '')) ?: null;

                $params = [
                    $courseId,
                    $title,
                    trim((string) ($_POST['summary'] ?? '')),
                    trim((string) ($_POST['content'] ?? '')),
                    array_key_exists((string) ($_POST['delivery_type'] ?? ''), academy_delivery_types()) ? (string) $_POST['delivery_type'] : 'document',
                    trim((string) ($_POST['material_url'] ?? '')) ?: null,
                    $videoUrl,
                    $audioUrl,
                    $infographicUrl,
                    max(1, (int) ($_POST['duration_minutes'] ?? 20)),
                    (int) ($_POST['sort_order'] ?? 0),
                    isset($_POST['is_required']) ? 1 : 0,
                    in_array((string) ($_POST['status'] ?? 'active'), ['active', 'draft', 'paused', 'archived'], true) ? (string) $_POST['status'] : 'active',
                ];
                if ($lessonId > 0) {
                    $stmt = $pdo->prepare("
                        UPDATE academy_lessons
                        SET webinar_id = ?, title = ?, summary = ?, content = ?, delivery_type = ?, material_url = ?,
                            video_url = ?, audio_url = ?, infographic_url = ?,
                            duration_minutes = ?, sort_order = ?, is_required = ?, status = ?
                        WHERE id = ?
                    ");
                    $stmt->execute(array_merge($params, [$lessonId]));
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO academy_lessons
                            (webinar_id, title, summary, content, delivery_type, material_url, video_url, audio_url, infographic_url, duration_minutes, sort_order, is_required, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute($params);
                }
                academy_admin_redirect('lessons', 'message', 'Lesson/material saved.');
            }

            if ($action === 'lesson_status') {
                $lessonId = (int) ($_POST['lesson_id'] ?? 0);
                $status = in_array((string) ($_POST['status'] ?? 'active'), ['active', 'draft', 'paused', 'archived'], true) ? (string) $_POST['status'] : 'active';
                if ($lessonId <= 0) {
                    throw new RuntimeException('Select a lesson to update.');
                }
                $stmt = $pdo->prepare("UPDATE academy_lessons SET status = ? WHERE id = ?");
                $stmt->execute([$status, $lessonId]);
                $label = ['active' => 'live', 'draft' => 'draft', 'paused' => 'disabled', 'archived' => 'discontinued'][$status];
                academy_admin_redirect('lessons', 'message', 'Lesson set to ' . $label . '.');
            }

            if ($action === 'delete_lesson') {
                $lessonId = (int) ($_POST['lesson_id'] ?? 0);
                if ($lessonId <= 0) {
                    throw new RuntimeException('Select a lesson to delete.');
                }
                $pdo->prepare("DELETE FROM academy_progress WHERE lesson_id IN (SELECT id FROM academy_lessons WHERE id = ?)")->execute([$lessonId]);
                $pdo->prepare("DELETE FROM academy_lessons WHERE id = ?")->execute([$lessonId]);
                academy_admin_redirect('lessons', 'message', 'Lesson deleted.');
            }

            if ($action === 'batch_lesson_action') {
                $lessonIds = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['lesson_ids'] ?? [])))));
                $batchAction = (string) ($_POST['batch_action'] ?? '');
                if (!$lessonIds) {
                    throw new RuntimeException('Select at least one lesson.');
                }
                if (!in_array($batchAction, ['live', 'disable', 'discontinue', 'delete'], true)) {
                    throw new RuntimeException('Choose a batch action.');
                }
                $statusMap = ['live' => 'active', 'disable' => 'paused', 'discontinue' => 'archived'];
                if ($batchAction === 'delete') {
                    $pdo->beginTransaction();
                    try {
                        $progressStmt = $pdo->prepare("DELETE FROM academy_progress WHERE lesson_id IN (SELECT id FROM academy_lessons WHERE id = ?)");
                        $deleteStmt = $pdo->prepare("DELETE FROM academy_lessons WHERE id = ?");
                        foreach ($lessonIds as $lid) {
                            $progressStmt->execute([$lid]);
                            $deleteStmt->execute([$lid]);
                        }
                        $pdo->commit();
                    } catch (Throwable $e) {
                        $pdo->rollBack();
                        throw $e;
                    }
                    academy_admin_redirect('lessons', 'message', count($lessonIds) . ' lesson(s) deleted.');
                }
                $status = $statusMap[$batchAction];
                $stmt = $pdo->prepare("UPDATE academy_lessons SET status = ? WHERE id = ?");
                foreach ($lessonIds as $lid) {
                    $stmt->execute([$status, $lid]);
                }
                $label = ['live' => 'live', 'disable' => 'disabled', 'discontinue' => 'discontinued'][$batchAction];
                academy_admin_redirect('lessons', 'message', count($lessonIds) . ' lesson(s) set to ' . $label . '.');
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
                require_once dirname(__DIR__, 2) . '/lib/academy/assessments.php';
                academy_ensure_course_quiz_invariants($pdo, $courseId);
                academy_admin_redirect('assessments', 'message', 'Assessment saved and quiz invariants enforced.');
            }

            if ($action === 'save_question') {
                $assessmentId = (int) ($_POST['assessment_id'] ?? 0);
                $question = trim((string) ($_POST['question_text'] ?? ''));
                if ($assessmentId <= 0 || $question === '') {
                    throw new RuntimeException('Assessment and question are required.');
                }
                $optA = trim((string) ($_POST['option_a'] ?? ''));
                $optB = trim((string) ($_POST['option_b'] ?? ''));
                $optC = trim((string) ($_POST['option_c'] ?? ''));
                $optD = trim((string) ($_POST['option_d'] ?? ''));
                if ($optA === '' || $optB === '' || $optC === '' || $optD === '') {
                    throw new RuntimeException('All four options (A, B, C, D) are required for assessment questions.');
                }
                $stmt = $pdo->prepare("
                    INSERT INTO academy_questions
                        (assessment_id, question_text, option_a, option_b, option_c, option_d, correct_option, points, sort_order, rationale)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $assessmentId,
                    $question,
                    $optA,
                    $optB,
                    $optC,
                    $optD,
                    in_array((string) ($_POST['correct_option'] ?? 'A'), ['A', 'B', 'C', 'D'], true) ? (string) $_POST['correct_option'] : 'A',
                    max(1, (float) ($_POST['points'] ?? 1)),
                    (int) ($_POST['sort_order'] ?? 0),
                    trim((string) ($_POST['rationale'] ?? '')) ?: null,
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
                $status = in_array((string) ($_POST['status'] ?? 'pending'), ['pending', 'under_review', 'approved', 'paid', 'rejected', 'closed'], true) ? (string) $_POST['status'] : 'pending';
                $adminNotes = trim((string) ($_POST['admin_notes'] ?? ''));
                $result = admin_process_academy_refund($pdo, $refundId, $status, $adminNotes, (int) ($_SESSION['user_id'] ?? 0) ?: 0);
                if (empty($result['success'])) {
                    throw new RuntimeException((string) ($result['error'] ?? 'Refund review failed.'));
                }
                if (!empty($result['queued'])) {
                    academy_admin_redirect('refunds', 'message', 'Refund queued for Super Admin money approval (request #' . $result['queued'] . '). No funds moved yet.');
                }
                academy_admin_redirect('refunds', 'message', $status === 'rejected' ? 'Refund rejected.' : 'Refund review saved.');
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
$editingProgram = null;
$editProgramId = (int) ($_GET['edit'] ?? 0);
if ($editProgramId > 0) {
    $editProgramStmt = $pdo->prepare("SELECT * FROM academy_programs WHERE id = ?");
    $editProgramStmt->execute([$editProgramId]);
    $editingProgram = $editProgramStmt->fetch() ?: null;
}
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
    WHERE c.deleted_at IS NULL
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
    'certificates' => (int) $pdo->query("SELECT COUNT(*) FROM academy_certificates WHERE deleted_at IS NULL")->fetchColumn(),
    'cohorts' => (int) $pdo->query("SELECT COUNT(*) FROM academy_cohorts")->fetchColumn(),
    'attendance' => (int) $pdo->query("SELECT COUNT(*) FROM academy_attendance")->fetchColumn(),
    'feedback' => (int) $pdo->query("SELECT COUNT(*) FROM academy_feedback")->fetchColumn(),
];
$stats['active_courses'] = (int) $pdo->query("SELECT COUNT(*) FROM webinars WHERE status = 'active'")->fetchColumn();
$stats['pending_certificates'] = (int) $pdo->query("SELECT COUNT(*) FROM academy_certificates WHERE status = 'pending' AND deleted_at IS NULL")->fetchColumn();
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

// --- Course grid: search, filter, sort, paginate (courses tab) ---
$gridQ = trim((string) ($_GET['q'] ?? ''));
$gridStatus = (string) ($_GET['f_status'] ?? 'all');
$gridType = (string) ($_GET['f_type'] ?? 'all');
$gridPrice = (string) ($_GET['f_price'] ?? 'all');
$gridProgram = (int) ($_GET['f_program'] ?? 0);
$gridSort = (string) ($_GET['s'] ?? 'title');
$gridDir = strtolower((string) ($_GET['d'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
$gridPage = max(1, (int) ($_GET['p'] ?? 1));
$gridPer = 20;

$sortMap = [
    'title' => 'w.title',
    'program' => 'p.title',
    'type' => 'w.course_type',
    'status' => 'w.status',
    'price' => 'w.is_free',
    'start' => 'w.start_time',
    'enrollments' => 'enrollments',
    'lessons' => 'lesson_count',
];
$sortCol = $sortMap[$gridSort] ?? 'w.title';

$where = ['1 = 1'];
$args = [];
if ($gridQ !== '') {
    $where[] = "(w.title LIKE ? OR w.course_code LIKE ? OR w.description LIKE ?)";
    $like = '%' . $gridQ . '%';
    $args[] = $like;
    $args[] = $like;
    $args[] = $like;
}
if (in_array($gridStatus, ['active', 'draft', 'paused', 'archived'], true)) {
    $where[] = "COALESCE(w.status, 'active') = ?";
    $args[] = $gridStatus;
}
if (in_array($gridType, ['course', 'webinar', 'workshop', 'certification', 'orientation'], true)) {
    $where[] = "w.course_type = ?";
    $args[] = $gridType;
}
if ($gridPrice === 'free') {
    $where[] = 'w.is_free = 1';
} elseif ($gridPrice === 'paid') {
    $where[] = 'w.is_free = 0';
}
if ($gridProgram > 0) {
    $where[] = 'w.program_id = ?';
    $args[] = $gridProgram;
}
$whereSql = implode(' AND ', $where);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM webinars w LEFT JOIN academy_programs p ON p.id = w.program_id WHERE {$whereSql}");
$countStmt->execute($args);
$gridTotal = (int) $countStmt->fetchColumn();
$gridPages = max(1, (int) ceil($gridTotal / $gridPer));
$gridPage = min($gridPage, $gridPages);
$gridOffset = ($gridPage - 1) * $gridPer;

$gridStmt = $pdo->prepare("
    SELECT w.*, p.title AS program_title,
           (SELECT COUNT(DISTINCT r.id) FROM webinar_registrations r WHERE r.webinar_id = w.id) AS enrollments,
           (SELECT COUNT(DISTINCT l.id) FROM academy_lessons l WHERE l.webinar_id = w.id AND l.status = 'active') AS lesson_count,
           (SELECT COUNT(DISTINCT a.id) FROM academy_assessments a WHERE a.webinar_id = w.id AND a.status = 'active') AS assessment_count
    FROM webinars w
    LEFT JOIN academy_programs p ON p.id = w.program_id
    WHERE {$whereSql}
    ORDER BY {$sortCol} {$gridDir}, w.id ASC
    LIMIT {$gridPer} OFFSET {$gridOffset}
");
$gridStmt->execute($args);
$courseGrid = $gridStmt->fetchAll();

// Build a clean query string that preserves the current grid filters
// (search + filters only; sort and page are appended by the view).
$gridBase = http_build_query(array_filter([
    'q' => $gridQ,
    'f_status' => $gridStatus !== 'all' ? $gridStatus : null,
    'f_type' => $gridType !== 'all' ? $gridType : null,
    'f_price' => $gridPrice !== 'all' ? $gridPrice : null,
    'f_program' => $gridProgram > 0 ? $gridProgram : null,
], static fn ($v): bool => $v !== null && $v !== ''));

// Load the course being edited (when ?edit=<id> is set).
$editingCourse = null;
$editId = (int) ($_GET['edit'] ?? 0);
if ($editId > 0) {
    $editStmt = $pdo->prepare("SELECT w.*, p.title AS program_title FROM webinars w LEFT JOIN academy_programs p ON p.id = w.program_id WHERE w.id = ?");
    $editStmt->execute([$editId]);
    $editingCourse = $editStmt->fetch() ?: null;
}

// --- Lesson grid: search, filter, sort, paginate (lessons tab) ---
$lgQ = trim((string) ($_GET['q'] ?? ''));
$lgCourse = (int) ($_GET['f_course'] ?? 0);
$lgDelivery = (string) ($_GET['f_delivery'] ?? 'all');
$lgStatus = (string) ($_GET['f_status'] ?? 'all');
$lgSort = (string) ($_GET['s'] ?? 'course');
$lgDir = strtolower((string) ($_GET['d'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
$lgPage = max(1, (int) ($_GET['p'] ?? 1));
$lgPer = 20;

$lgSortMap = [
    'course' => 'w.title',
    'title' => 'l.title',
    'delivery' => 'l.delivery_type',
    'status' => 'l.status',
    'duration' => 'l.duration_minutes',
    'sort' => 'l.sort_order',
];
$lgSortCol = $lgSortMap[$lgSort] ?? 'w.title';

$lgWhere = ['1 = 1'];
$lgArgs = [];
if ($lgQ !== '') {
    $lgWhere[] = "(l.title LIKE ? OR l.summary LIKE ? OR l.content LIKE ?)";
    $lgLike = '%' . $lgQ . '%';
    $lgArgs[] = $lgLike;
    $lgArgs[] = $lgLike;
    $lgArgs[] = $lgLike;
}
if ($lgCourse > 0) {
    $lgWhere[] = 'l.webinar_id = ?';
    $lgArgs[] = $lgCourse;
}
if (array_key_exists($lgDelivery, academy_delivery_types())) {
    $lgWhere[] = 'l.delivery_type = ?';
    $lgArgs[] = $lgDelivery;
}
if (in_array($lgStatus, ['active', 'draft', 'paused', 'archived'], true)) {
    $lgWhere[] = "COALESCE(l.status, 'active') = ?";
    $lgArgs[] = $lgStatus;
}
$lgWhereSql = implode(' AND ', $lgWhere);

$lgCountStmt = $pdo->prepare("
    SELECT COUNT(*) FROM academy_lessons l
    JOIN webinars w ON w.id = l.webinar_id
    WHERE {$lgWhereSql}
");
$lgCountStmt->execute($lgArgs);
$lgTotal = (int) $lgCountStmt->fetchColumn();
$lgPages = max(1, (int) ceil($lgTotal / $lgPer));
$lgPage = min($lgPage, $lgPages);
$lgOffset = ($lgPage - 1) * $lgPer;

$lgStmt = $pdo->prepare("
    SELECT l.*, w.title AS course_title
    FROM academy_lessons l
    JOIN webinars w ON w.id = l.webinar_id
    WHERE {$lgWhereSql}
    ORDER BY {$lgSortCol} {$lgDir}, l.id ASC
    LIMIT {$lgPer} OFFSET {$lgOffset}
");
$lgStmt->execute($lgArgs);
$lessonGrid = $lgStmt->fetchAll();

$lgBase = http_build_query(array_filter([
    'q' => $lgQ,
    'f_course' => $lgCourse > 0 ? $lgCourse : null,
    'f_delivery' => $lgDelivery !== 'all' ? $lgDelivery : null,
    'f_status' => $lgStatus !== 'all' ? $lgStatus : null,
], static fn ($v): bool => $v !== null && $v !== ''));

// Load the lesson being edited (when ?edit_lesson=<id> is set).
$editingLesson = null;
$editLessonId = (int) ($_GET['edit_lesson'] ?? 0);
if ($editLessonId > 0) {
    $elStmt = $pdo->prepare("SELECT l.*, w.title AS course_title FROM academy_lessons l JOIN webinars w ON w.id = l.webinar_id WHERE l.id = ?");
    $elStmt->execute([$editLessonId]);
    $editingLesson = $elStmt->fetch() ?: null;
}

$homepage = academy_homepage_settings($pdo);
$activeCourses = array_values(array_filter($courses, static fn (array $row): bool => (string) ($row['status'] ?? '') === 'active'));

admin_page_start('Academy Workspace', [
    'active' => 'academy/?page=' . str_replace('_', '-', (string) $tab),
    'description' => 'Manage programs, courses, lessons, materials, assessments, enrollments, certificates, refunds, and learning reports.',
    'wide' => true,
    'topbar_only' => true,
    'breadcrumbs' => [
        ['label' => 'Learning & Training'],
        ['label' => 'Academy Workspace'],
    ],
    'css' => '.acad-brand{display:flex;gap:10px;align-items:center;border-bottom:1px solid rgba(255,255,255,.14);padding-bottom:14px;margin-bottom:14px}.acad-brand img{width:46px;height:46px;border-radius:50%;background:#fff;padding:4px}.acad-brand strong{display:block;font-size:1.05rem}.acad-brand small{display:block;color:#dff5e8;font-size:.72rem;line-height:1.25}.acad-label{font-size:.72rem;text-transform:uppercase;color:#aee4c4;font-weight:900;margin:14px 4px 8px}.acad-nav{display:grid;gap:5px}.acad-nav a{display:flex;align-items:center;justify-content:space-between;gap:10px;color:#fff;text-decoration:none;padding:10px 11px;border-radius:8px;font-weight:850}.acad-nav a:hover,.acad-nav a.active{background:rgba(46,204,113,.24)}.acad-nav span:first-child{display:inline-flex;align-items:center;gap:9px}.acad-count{background:#0ea765;color:#fff;border-radius:999px;min-width:24px;text-align:center;padding:2px 7px;font-size:.74rem}.acad-count.warn{background:#f79009}.acad-toolstrip{display:flex;gap:10px;align-items:center;flex-wrap:wrap}.acad-tool{border:1px solid var(--line);border-radius:8px;background:#fff;padding:9px 11px;font-weight:850;color:#102033}.acad-head{display:flex;justify-content:space-between;align-items:flex-end;gap:12px;flex-wrap:wrap;margin-bottom:14px}.acad-head h2{font-size:1.65rem;margin:0;color:#0b1f16}.acad-head p{margin:4px 0 0;color:var(--muted)}.acad-kpis{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:12px}.acad-kpi{border:1px solid var(--line);border-radius:8px;background:#fff;box-shadow:var(--shadow);padding:14px;display:flex;justify-content:space-between;gap:10px;min-height:112px}.acad-kpi small{display:block;text-transform:uppercase;font-size:.72rem;font-weight:900;color:#536171}.acad-kpi strong{display:block;font-size:1.45rem;color:#101828;margin-top:7px}.acad-kpi span{display:block;color:#079455;font-size:.78rem;font-weight:850;margin-top:5px}.acad-icon{width:48px;height:48px;border-radius:50%;display:grid;place-items:center;background:#e8f5ed;color:#087443;font-size:1.2rem}.acad-icon.blue{background:#e8f1ff;color:#175cd3}.acad-icon.orange{background:#fff1df;color:#c05600}.acad-icon.purple{background:#f1e9ff;color:#6941c6}.acad-icon.red{background:#fee4e2;color:#d92d20}.acad-overview-grid{display:grid;grid-template-columns:1.2fr 1.35fr .95fr;gap:14px;margin-top:14px}.acad-row{display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:14px;margin-top:14px}.acad-bottom{display:grid;grid-template-columns:1fr 1.15fr;gap:14px;margin-top:14px}.acad-panel{border:1px solid var(--line);border-radius:8px;background:#fff;box-shadow:var(--shadow);padding:14px}.acad-panel-head{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:10px}.acad-panel-head h3{margin:0;color:#102033;font-size:1rem}.acad-panel-head a{color:#0f6b3c;text-decoration:none;font-weight:900;font-size:.82rem}.acad-table{width:100%;border-collapse:collapse}.acad-table th,.acad-table td{padding:9px 8px;border-bottom:1px solid #edf1f4;text-align:left;font-size:.8rem;vertical-align:top}.acad-table th{font-size:.72rem;text-transform:uppercase;color:#667085}.acad-badge{display:inline-flex;border-radius:999px;padding:3px 7px;font-size:.7rem;font-weight:900}.acad-badge.ok{background:#dcfae6;color:#067647}.acad-badge.info{background:#dbeafe;color:#175cd3}.acad-badge.warn{background:#fef0c7;color:#b54708}.acad-badge.bad{background:#fee4e2;color:#b42318}.acad-chart{height:210px;display:flex;align-items:flex-end;gap:10px;border-bottom:1px solid #d8dee6;padding:12px 8px 0}.acad-bar{flex:1;border-radius:8px 8px 0 0;background:linear-gradient(180deg,#0f6b3c,#9bd6ae);min-height:28px}.acad-list{display:grid;gap:9px}.acad-list-row{display:flex;justify-content:space-between;gap:10px;border-bottom:1px solid #eef2f4;padding-bottom:9px;font-size:.83rem}.acad-list-row strong{color:#102033}.acad-list-row small{display:block;color:var(--muted);margin-top:2px}.acad-progress{height:8px;border-radius:999px;background:#eef2f4;overflow:hidden;min-width:86px}.acad-fill{height:100%;background:#0f6b3c}.acad-actions{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:14px}.acad-action{border:1px solid var(--line);border-radius:8px;background:#fff;padding:16px;display:flex;gap:12px;align-items:center;color:inherit;text-decoration:none}.acad-action i{width:38px;height:38px;border-radius:50%;display:grid;place-items:center;background:#e8f5ed;color:#0f6b3c}.acad-action strong{display:block;color:#102033}.acad-action small{color:var(--muted)}.academy-tabs{display:grid;gap:10px;margin:0 0 18px}.academy-tab-row{display:flex;align-items:flex-start;gap:10px;flex-wrap:wrap;padding:10px;border:1px solid var(--line);border-radius:8px;background:#fff}.academy-tab-label{min-width:92px;padding:9px 10px;color:var(--green-dark);font-weight:900}.academy-tab-row a{display:inline-flex;align-items:center;min-height:36px;padding:8px 11px;border:1px solid transparent;border-radius:7px;background:#f8fbf9;color:var(--ink);font-weight:800}.academy-tab-row a.active,.academy-tab-row a:hover{background:#e8f5ed;border-color:#cae4d4;color:var(--green-dark);text-decoration:none}.academy-split{display:grid;grid-template-columns:360px minmax(0,1fr);gap:18px;align-items:start}.academy-create{padding:0}.academy-create>summary{padding:16px 18px;font-weight:900;color:var(--green-dark);cursor:pointer;list-style:none}.academy-create>summary::-webkit-details-marker{display:none}.academy-create>form,.academy-create>.panel-inner{padding:0 18px 18px}.academy-pillbox{display:flex;gap:6px;flex-wrap:wrap}.academy-pillbox label{margin:0;border:1px solid var(--line);border-radius:999px;padding:6px 9px;background:#fbfdfb;font-size:.85rem}.academy-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.academy-card-list{display:grid;gap:12px}.academy-card-list article{border:1px solid var(--line);border-radius:8px;background:#fff;padding:14px}.academy-mini{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px}@media(max-width:1100px){.acad-kpis{grid-template-columns:repeat(3,minmax(0,1fr))}.acad-overview-grid,.acad-row,.acad-bottom{grid-template-columns:1fr}.acad-actions{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:900px){.academy-split,.academy-form-grid,.acad-kpis,.acad-actions{grid-template-columns:1fr}.academy-tab-label{width:100%;min-width:0}}',
]);
define('NATCODEV_ACADEMY_WORKSPACE_VIEW', true);
require __DIR__ . '/views/workspace.php';
admin_page_end();

