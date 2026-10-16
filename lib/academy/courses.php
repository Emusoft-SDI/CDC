<?php
declare(strict_types=1);

function academy_delivery_types(): array
{
    return [
        'live_zoom' => 'Zoom / Live Class',
        'video' => 'Video Lesson',
        'audio' => 'Audio / Listen',
        'reading' => 'Reading / Self-paced Text',
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
        'audio' => 'Listen Now',
        'reading' => 'Read Lesson',
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
function academy_slug(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? $value;
    return trim($value, '-') ?: 'academy-course';
}
/**
 * Public URL for a program thumbnail stored as a web-root relative path
 * (e.g. academy_uploads/thumbnails/grower-onboarding-program.png).
 */
function academy_program_thumbnail_src(array $row): string
{
    $thumbnail = trim((string) ($row['program_thumbnail'] ?? $row['thumbnail'] ?? ''));
    if ($thumbnail === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $thumbnail)) {
        return $thumbnail;
    }
    return '../' . ltrim(str_replace('\\', '/', $thumbnail), '/');
}

/**
 * Public URL for a course thumbnail, checking course-specific bespoke images first,
 * then program thumbnails, then thematic 16:9 artwork matching category and topic.
 */
function academy_course_artwork_url(array $course, string $prefix = '../'): string
{
    $courseId = (int) ($course['id'] ?? 0);
    $slug = academy_slug((string) ($course['title'] ?? ''));
    $baseDir = dirname(__DIR__, 2);

    $candidates = [];
    if ($courseId > 0) {
        $candidates[] = 'assets/academy/thumbnails/course-' . $courseId . '.jpg';
        $candidates[] = 'academy_uploads/thumbnails/course-' . $courseId . '.jpg';
    }
    if ($slug !== '') {
        $candidates[] = 'assets/academy/thumbnails/' . $slug . '.jpg';
        $candidates[] = 'academy_uploads/thumbnails/' . $slug . '.jpg';
    }

    foreach ($candidates as $cand) {
        if (is_file($baseDir . '/' . $cand)) {
            return $prefix . $cand;
        }
    }

    $progThumb = academy_program_thumbnail_src($course);
    if ($progThumb !== '') {
        return $progThumb;
    }

    $haystack = strtolower((string) ($course['title'] ?? '') . ' ' . (string) ($course['description'] ?? '') . ' ' . (string) ($course['target_roles'] ?? '') . ' ' . (string) ($course['program_title'] ?? '') . ' ' . (string) ($course['category'] ?? ''));
    if (str_contains($haystack, 'market') || str_contains($haystack, 'seller') || str_contains($haystack, 'buyer') || str_contains($haystack, 'trade') || str_contains($haystack, 'store')) {
        return $prefix . 'assets/academy/course-market-readiness.svg';
    }
    if (str_contains($haystack, 'provider') || str_contains($haystack, 'accreditation') || str_contains($haystack, 'input') || str_contains($haystack, 'equipment')) {
        return $prefix . 'assets/academy/course-provider-accreditation.svg';
    }
    if (str_contains($haystack, 'field') || str_contains($haystack, 'advisory') || str_contains($haystack, 'agent') || str_contains($haystack, 'agronom') || str_contains($haystack, 'gps')) {
        return $prefix . 'assets/academy/course-field-advisory.svg';
    }
    if (str_contains($haystack, 'coordinator') || str_contains($haystack, 'governance') || str_contains($haystack, 'cooperative') || str_contains($haystack, 'admin') || str_contains($haystack, 'wallet')) {
        return $prefix . 'assets/academy/course-governance.svg';
    }
    if (str_contains($haystack, 'certificate') || str_contains($haystack, 'certification') || str_contains($haystack, 'exam')) {
        return $prefix . 'assets/academy/course-certificate.svg';
    }

    return $prefix . 'assets/academy/course-grower-foundation.svg';
}

function academy_course_thumbnail_src(array $course, string $prefix = '../'): string
{
    return academy_course_artwork_url($course, $prefix);
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
        SELECT w.*, p.title program_title, p.audience_roles program_roles, p.thumbnail program_thumbnail,
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

function academy_materials_for_course(PDO $pdo, int $courseId): array
{
    academy_ensure_schema($pdo);
    $stmt = $pdo->prepare("SELECT * FROM academy_materials WHERE webinar_id = ? AND status = 'active' ORDER BY sort_order ASC, id ASC");
    $stmt->execute([$courseId]);
    return $stmt->fetchAll();
}

function academy_material_label(string $type): string
{
    return [
        'guide' => 'Guide',
        'reference' => 'Reference',
        'template' => 'Template',
        'checklist' => 'Checklist',
        'worksheet' => 'Worksheet',
        'link' => 'Resource',
    ][strtolower($type)] ?? 'Resource';
}

/**
 * Generates structured, localized multitrack audio narration scripts
 * for English (en), Nigerian Pidgin (pcm), Hausa (ha), Yoruba (yo), and Igbo (ig).
 */
function academy_lesson_multitrack_payload(array $lesson, array $course): array
{
    $title = trim((string) ($lesson['title'] ?? 'Lesson'));
    $summary = trim((string) ($lesson['summary'] ?? ''));
    $courseTitle = trim((string) ($course['title'] ?? 'NATCODEV Academy Course'));
    $rawContent = (string) ($lesson['content'] ?? '');

    // Extract takeaway if available
    $takeaway = '';
    if (preg_match('/\[TAKEAWAY\](.*?)\[\/TAKEAWAY\]/is', $rawContent, $m)) {
        $takeaway = trim(preg_replace('/\s+/', ' ', $m[1]));
    }
    // Extract protip if available
    $protip = '';
    if (preg_match('/\[PROTIP\](.*?)\[\/PROTIP\]/is', $rawContent, $m)) {
        $protip = trim(preg_replace('/\s+/', ' ', $m[1]));
    }
    // Clean text of short paragraphs for voice script
    $cleanText = preg_replace('/\[[A-Z0-9_:| -]+\]/i', '', $rawContent);
    $cleanText = preg_replace('/\[\/[A-Z0-9_]+\]/i', '', $cleanText);
    $sentences = preg_split('/(?<=[.?!])\s+/', strip_tags($cleanText), 6);
    $introPoints = implode(' ', array_slice($sentences ?: [], 0, 3));

    // 1. English (Official)
    $enScript = "NATCODEV Academy Lecture on {$courseTitle}. Lesson topic: {$title}. ";
    if ($summary !== '') {
        $enScript .= "{$summary}. ";
    }
    if ($takeaway !== '') {
        $enScript .= "Key Takeaway: {$takeaway}. ";
    }
    if ($introPoints !== '') {
        $enScript .= "Core Concept: {$introPoints} ";
    }
    if ($protip !== '') {
        $enScript .= "Field Pro-Tip: {$protip}. ";
    }
    $enScript .= "Study this lesson thoroughly, take the assessment, and earn your verified certificate.";

    // 2. Nigerian Pidgin (Naija Track)
    $pcmScript = "Welcome to NATCODEV Academy lecture for Naija Pidgin. Lesson topic na: {$title}. ";
    if ($summary !== '') {
        $pcmScript .= "Wetyn dis lesson dey talk about: {$summary}. ";
    }
    if ($takeaway !== '') {
        $pcmScript .= "Di main takeaway wey you must hold tight: {$takeaway}. ";
    } else {
        $pcmScript .= "Make you pay serious attention to all di technical steps wey dey dis lesson. ";
    }
    if ($protip !== '') {
        $pcmScript .= "Sharp advice from di field experts: {$protip}. ";
    }
    $pcmScript .= "Make you read am finish, answer di quiz questions well well, and collect your verified certificate. We dey build solid coconut enterprise together.";

    // 3. Hausa (Arewa Track)
    $haScript = "Barka da zuwa darasin NATCODEV Academy a harshen Hausa. Taken darasi: {$title}. ";
    if ($summary !== '') {
        $haScript .= "Bayanin darasi: {$summary}. ";
    }
    if ($takeaway !== '') {
        $haScript .= "Muhimmin jigon da ya kamata ku kiyaye: {$takeaway}. ";
    }
    $haScript .= "Wannan darasi na shirin {$courseTitle} yana koyar da dabarun aikin gona na zamani da kuma inganta amfanin gona. ";
    if ($protip !== '') {
        $haScript .= "Shawarar kwararru: {$protip}. ";
    }
    $haScript .= "Ku kammala karatun wannan darasi, ku amsa tambayoyin jarrabawa domin samun takardar shaidar gwaninta daga NATCODEV.";

    // 4. Yoruba (Southwest Track)
    $yoScript = "E kaabo si eko NATCODEV Academy ni ede Yoruba. Akole eko yii ni: {$title}. ";
    if ($summary !== '') {
        $yoScript .= "Akopopo re ni pe: {$summary}. ";
    }
    if ($takeaway !== '') {
        $yoScript .= "Koko pataki ti o gbodo mo daadaa: {$takeaway}. ";
    }
    $yoScript .= "Eko yii ninu {$courseTitle} je ipile pataki fun idagbasoke oko agbon ati eto oro-aje to munadoko. ";
    if ($protip !== '') {
        $yoScript .= "Imoran pataki lati odo awon ojogbon: {$protip}. ";
    }
    $yoScript .= "E ka gbogbo eko yii tan, ki e se idanwo re lati gba iwe-eri ti a fowo si lori eto NATCODEV.";

    // 5. Igbo (Southeast Track)
    $igScript = "Nnoo na ihe omumu NATCODEV Academy n'asusu Igbo. Isiokwu ihe omumu nke taata bu: {$title}. ";
    if ($summary !== '') {
        $igScript .= "Nchikota ihe omumu a bu: {$summary}. ";
    }
    if ($takeaway !== '') {
        $igScript .= "Isi ihe di mkpa i kwesiri ijide n'aka bu: {$takeaway}. ";
    }
    $igScript .= "Ihe omumu a sitere na {$courseTitle} ga-enyere gi aka ima otu e si ewere oru ugbo aki bekee gaa n'ihu n'uzo ohuru. ";
    if ($protip !== '') {
        $igScript .= "Ndụmọdụ sitere n'aka ndi oka-ibe: {$protip}. ";
    }
    $igScript .= "Guo ihe omumu a nke oma, zaa ajuju nyocha ka i wee nweta asambodo kwesiri ntukwasi obi sitere na NATCODEV.";

    return [
        'en' => [
            'label' => 'English (Official)',
            'lang' => 'en-NG',
            'script' => $enScript,
            'summary' => $summary ?: $title,
        ],
        'pcm' => [
            'label' => 'Nigerian Pidgin (Naija)',
            'lang' => 'pcm',
            'script' => $pcmScript,
            'summary' => 'Naija Pidgin summary for ' . $title,
        ],
        'ha' => [
            'label' => 'Hausa (Arewa Track)',
            'lang' => 'ha-NG',
            'script' => $haScript,
            'summary' => 'Takaitaccen bayani da harshen Hausa',
        ],
        'yo' => [
            'label' => 'Yoruba (Southwest Track)',
            'lang' => 'yo-NG',
            'script' => $yoScript,
            'summary' => 'Akopopo eko ni ede Yoruba',
        ],
        'ig' => [
            'label' => 'Igbo (Southeast Track)',
            'lang' => 'ig-NG',
            'script' => $igScript,
            'summary' => 'Nchikota ihe omumu n\'asusu Igbo',
        ],
    ];
}

