<?php
declare(strict_types=1);

/**
 * NATCODEV Academy Public Landing Page Render Suite
 * Validates the rebuilt academy/index.php learner landing experience:
 * 1. Structural integrity (hero, pathways, programs, catalog, certificates, route, CTA)
 * 2. Real catalog data flowing into the page (programs, courses, certificate tracks)
 * 3. Catalog filters (category, tuition) and pagination behavior
 * 4. Selected-course spotlight and empty states
 * 5. Accessibility surface (single h1, skip link, ARIA labels, reduced-motion support)
 *
 * Each scenario renders the real page in an isolated PHP subprocess so the
 * page's top-level function definitions never collide between scenarios.
 */

require_once __DIR__ . '/TestHarness.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/academy.php';

function academy_landing_render(string $query = '', array $expects = []): array
{
    $cmd = PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/academy_landing_renderer.php');
    if ($query !== '') {
        $cmd .= ' ' . escapeshellarg($query);
    }
    $env = getenv();
    if ($expects !== []) {
        $env['LANDING_EXPECTS'] = json_encode($expects, JSON_THROW_ON_ERROR);
    }
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($cmd, $descriptors, $pipes, null, $env);
    if (!is_resource($process)) {
        return ['rendered' => false, 'error' => 'proc_open unavailable'];
    }
    fclose($pipes[0]);
    stream_set_timeout($pipes[1], 180);
    $output = (string) stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    if (preg_match('/LANDING_RESULT:(\{.*\})/s', $output, $matches)) {
        $decoded = json_decode($matches[1], true);
        if (is_array($decoded)) {
            $decoded['exit_code'] = $exitCode;
            return $decoded;
        }
    }

    return ['rendered' => false, 'exit_code' => $exitCode, 'error' => mb_substr($output, 0, 200)];
}

function run_academy_landing_tests(): void
{
    TestHarness::start("Scenario 15: Academy Public Landing Page Render");
    $pdo = TestHarness::createTestDb();
    academy_ensure_schema($pdo);

    $courses = academy_courses($pdo, null, true);
    $programs = $pdo->query("SELECT * FROM academy_programs WHERE status = 'active' ORDER BY sort_order ASC, title ASC")->fetchAll();
    $tracks = academy_certificate_groups($pdo, null, true);
    $freeCourses = array_values(array_filter($courses, static function (array $course): bool {
        return (int) ($course['is_free'] ?? 0) === 1;
    }));
    $paidCourses = array_values(array_filter($courses, static function (array $course): bool {
        return (int) ($course['is_free'] ?? 0) === 0;
    }));
    $categories = [];
    foreach ($courses as $course) {
        $cat = trim((string) ($course['category'] ?? ''));
        if ($cat !== '') {
            $categories[$cat] = true;
        }
    }
    $categories = array_keys($categories);
    sort($categories);

    TestHarness::assert(count($courses) > 0, "Landing Data: " . count($courses) . " active courses available for the catalog");
    TestHarness::assert(count($programs) > 0, "Landing Data: " . count($programs) . " active programs available");
    TestHarness::assert(count($tracks) > 0, "Landing Data: " . count($tracks) . " certificate tracks available");
    TestHarness::assert(count($freeCourses) > 0, "Landing Data: " . count($freeCourses) . " free courses for the tuition filter");

    $structuralChecks = [
        'rendered' => 'page renders with full layout',
        'no_php_errors' => 'page renders without PHP errors or warnings',
        'lang_en' => 'document declares lang="en"',
        'skip_link' => 'keyboard skip link is present',
        'brand' => 'NATCODEV Academy brand renders in the top bar',
        'hero_headline' => 'hero headline renders',
        'eyebrow' => 'coconut value-chain eyebrow renders',
        'stats_band' => 'live statistics band renders',
        'pathways' => 'learning pathways section renders',
        'programs' => 'academic programs section renders',
        'catalog' => 'course catalog section renders',
        'certificates' => 'certificate tracks section renders',
        'route' => 'learning route section renders',
        'cta_band' => 'final call-to-action band renders',
        'course_grid' => 'course cards render in the catalog',
        'single_h1' => 'page exposes exactly one h1',
        'nav_aria' => 'primary navigation has an accessible name',
        'filter_aria' => 'catalog filter form has an accessible name',
        'pagination_aria' => 'pagination has an accessible name when present',
        'reduced_motion' => 'prefers-reduced-motion handling is present',
        'footer' => 'site footer renders',
        'count_line' => 'catalog result count line renders',
    ];

    $home = academy_landing_render('');
    foreach ($structuralChecks as $key => $description) {
        $value = $home[$key] ?? false;
        TestHarness::assert($value === true, "Landing Structure: {$description}" . (isset($home['error']) && !$value ? " ({$home['error']})" : ''));
    }
    TestHarness::assert(($home['page_number'] ?? 0) === 1, "Landing Pagination: catalog opens on page 1");
    TestHarness::assert(($home['page_total'] ?? 0) >= 1, "Landing Pagination: catalog reports a valid page total");
    TestHarness::assert(($home['empty_state_absent'] ?? false), "Landing Catalog: default view shows courses, not the empty state");

    $firstProgram = (string) ($programs[0]['title'] ?? '');
    $firstCourse = $courses[0] ?? null;
    $firstCourseTitle = $firstCourse ? (string) $firstCourse['title'] : '';
    $contentRender = academy_landing_render('', ['program' => $firstProgram, 'course' => $firstCourseTitle]);
    if ($firstProgram !== '') {
        TestHarness::assert(($contentRender['expected_program_present'] ?? false), "Landing Content: program '{$firstProgram}' renders in the programs grid");
    }
    if ($firstCourseTitle !== '') {
        TestHarness::assert(($contentRender['expected_course_present'] ?? false), "Landing Content: course '{$firstCourseTitle}' renders in the catalog");
    }

    $freeRender = academy_landing_render('payment=free');
    TestHarness::assert(($freeRender['free_filter_clean'] ?? false), "Landing Filter: free-tuition filter shows only free courses (no NGN prices on the page)");
    TestHarness::assert(($freeRender['count_line'] ?? false), "Landing Filter: free-tuition filter keeps the result count line");

    if (count($paidCourses) > 0) {
        $paidRender = academy_landing_render('payment=paid');
        TestHarness::assert(($paidRender['paid_filter_clean'] ?? false), "Landing Filter: paid-tuition filter applies the paid state");
        TestHarness::assert(($paidRender['has_ngn'] ?? false), "Landing Filter: paid-tuition filter lists NGN prices for " . count($paidCourses) . " paid courses");
    } else {
        TestHarness::assert(true, "Landing Filter: paid-tuition filter skipped (no paid courses in catalog)");
    }

    if ($categories !== []) {
        $category = $categories[0];
        $categoryRender = academy_landing_render('category=' . rawurlencode($category));
        TestHarness::assert(($categoryRender['category_filter_applied'] ?? false), "Landing Filter: category filter applies '{$category}' to the result line");
        TestHarness::assert(($categoryRender['rendered'] ?? false), "Landing Filter: category filter still renders the full page");
    }

    if (($home['page_total'] ?? 0) > 1) {
        $pageTwo = academy_landing_render('page=2');
        TestHarness::assert(($pageTwo['rendered'] ?? false), "Landing Pagination: page 2 renders");
        TestHarness::assert(($pageTwo['page_number'] ?? 0) === 2, "Landing Pagination: page 2 reports itself as the active page");
    } else {
        TestHarness::assert(true, "Landing Pagination: page 2 skipped (single-page catalog)");
    }

    $firstCourseId = (int) ($courses[0]['id'] ?? 0);
    if ($firstCourseId > 0) {
        $spotlight = academy_landing_render('course_id=' . $firstCourseId);
        TestHarness::assert(($spotlight['spotlight'] ?? false), "Landing Spotlight: selected course panel renders for course #{$firstCourseId}");
        TestHarness::assert(($spotlight['rendered'] ?? false), "Landing Spotlight: page still renders around the spotlight panel");
    }

    $emptyRender = academy_landing_render('category=' . rawurlencode('ZZZ Nonexistent Category'));
    TestHarness::assert(($emptyRender['empty_state_present'] ?? false), "Landing Empty State: unknown category shows the guided empty state");
    TestHarness::assert(($emptyRender['rendered'] ?? false), "Landing Empty State: page still renders around the empty state");

    $badPage = academy_landing_render('page=9999');
    TestHarness::assert(($badPage['rendered'] ?? false), "Landing Edge: out-of-range page clamps gracefully and still renders");
}
