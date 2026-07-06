<?php
declare(strict_types=1);

function academy_delivery_types(): array
{
    return [
        'live_zoom' => 'Zoom / Live Class',
        'video' => 'YouTube / Video',
        'document' => 'PDF / Document Material',
        'lms' => 'LMS / Self-paced Page',
        'chat_group' => 'WhatsApp / Telegram Class',
        'in_person' => 'In-person Venue',
        'mixed' => 'Mixed Delivery',
    ];
}
function academy_delivery_actions(): array
{
    return [
        'live_zoom' => 'Join Live Class',
        'video' => 'Watch Video',
        'document' => 'Open Material',
        'lms' => 'Open Course',
        'chat_group' => 'Join Class Group',
        'in_person' => 'View Venue',
        'mixed' => 'Open Training',
    ];
}
function academy_delivery_label(string $type): string
{
    return academy_delivery_types()[$type] ?? 'Training Material';
}
function academy_delivery_action(string $type): string
{
    return academy_delivery_actions()[$type] ?? 'Open Training';
}
function academy_role_label(string $role): string
{
    return [
        'super_admin' => 'Super Administrator',
        'national_coordinator' => 'National Coordinator',
        'state_coordinator' => 'State Coordinator',
        'investor' => 'Investor',
        'admin' => 'Administrator',
        'field_agent' => 'Field Agent',
        'agronomist' => 'Agronomist',
        'agric_extensionist' => 'Agric Extensionist',
        'provider' => 'Provider',
        'input_provider' => 'Input Provider',
        'service_provider' => 'Service Provider',
        'seller' => 'Marketplace Seller',
        'farm_hand' => 'Farm Hand',
        'learner' => 'Learner',
        'grower' => 'Grower',
        'all' => 'All Users',
    ][$role] ?? ucwords(str_replace('_', ' ', $role));
}
function academy_role_labels(string $roles): string
{
    $items = array_values(array_filter(array_map('trim', explode(',', $roles))));
    if (!$items) {
        return 'All Users';
    }
    return implode(', ', array_map('academy_role_label', $items));
}
function academy_current_role(PDO $pdo, array $user): string
{
    if (function_exists('admin_highest_assigned_platform_role')) {
        return admin_highest_assigned_platform_role($pdo, (int) $user['id']) ?? (string) ($user['platform_role'] ?? $user['role'] ?? 'grower');
    }
    return (string) ($user['platform_role'] ?? $user['role'] ?? 'grower');
}
function academy_course_visible_to_role(array $course, string $role): bool
{
    if ($role === 'learner') {
        return true;
    }
    $targets = array_values(array_filter(array_map('trim', explode(',', (string) ($course['target_roles'] ?? '')))));
    return !$targets || in_array('all', $targets, true) || in_array($role, $targets, true);
}
function academy_courses(PDO $pdo, ?string $role = null, bool $activeOnly = true): array
{
    academy_ensure_schema($pdo);
    $where = $activeOnly ? "WHERE COALESCE(w.status, 'active') = 'active'" : '';
    $rows = $pdo->query("
        SELECT w.*, p.title program_title, p.audience_roles program_roles,
               COUNT(DISTINCT r.id) registrations,
               COUNT(DISTINCT l.id) lessons,
               COUNT(DISTINCT a.id) assessments
        FROM webinars w
        LEFT JOIN academy_programs p ON p.id = w.program_id
        LEFT JOIN webinar_registrations r ON r.webinar_id = w.id
        LEFT JOIN academy_lessons l ON l.webinar_id = w.id AND l.status = 'active'
        LEFT JOIN academy_assessments a ON a.webinar_id = w.id AND a.status = 'active'
        {$where}
        GROUP BY w.id
        ORDER BY p.sort_order ASC, w.is_free DESC, w.start_time ASC, w.id ASC
    ")->fetchAll();
    if ($role === null) {
        return $rows;
    }
    return array_values(array_filter($rows, static fn(array $course): bool => academy_course_visible_to_role($course, $role)));
}
function academy_registered_courses(PDO $pdo, int $userId): array
{
    academy_ensure_schema($pdo);
    $stmt = $pdo->prepare("
        SELECT r.id registration_id, r.payment_status, r.registered_at, r.progress_percent, r.completion_status, r.started_at, r.completed_at, r.certificate_status,
               w.*, p.title program_title,
               COUNT(DISTINCT l.id) lessons,
               COUNT(DISTINCT CASE WHEN ap.status = 'completed' THEN ap.lesson_id END) completed_lessons,
               MAX(at.passed) assessment_passed
        FROM webinar_registrations r
        JOIN webinars w ON w.id = r.webinar_id
        LEFT JOIN academy_programs p ON p.id = w.program_id
        LEFT JOIN academy_lessons l ON l.webinar_id = w.id AND l.status = 'active'
        LEFT JOIN academy_progress ap ON ap.user_id = r.user_id AND ap.webinar_id = w.id AND ap.lesson_id = l.id
        LEFT JOIN academy_attempts at ON at.user_id = r.user_id AND at.webinar_id = w.id
        WHERE r.user_id = ?
        GROUP BY r.id
        ORDER BY r.registered_at DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}
function academy_lessons_for_course(PDO $pdo, int $courseId): array
{
    academy_ensure_schema($pdo);
    $stmt = $pdo->prepare("SELECT * FROM academy_lessons WHERE webinar_id = ? AND status = 'active' ORDER BY sort_order ASC, id ASC");
    $stmt->execute([$courseId]);
    return $stmt->fetchAll();
}
