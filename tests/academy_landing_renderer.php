<?php
declare(strict_types=1);

/**
 * Renders academy/index.php as a simulated web request and reports
 * structural checks as a single JSON line prefixed with LANDING_RESULT:.
 *
 * Usage: php academy_landing_renderer.php [query_string]
 * Example: php academy_landing_renderer.php "payment=free&page=2"
 */

ini_set('display_errors', '0');
error_reporting(E_ALL);

$base = dirname(__DIR__);

$_SERVER['SCRIPT_NAME'] = '/academy/index.php';
$_SERVER['SCRIPT_FILENAME'] = $base . '/academy/index.php';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/academy/index.php';
$_SERVER['HTTP_HOST'] = 'localhost';
$_GET = [];
$_POST = [];
$_COOKIE = [];
parse_str((string) ($argv[1] ?? ''), $_GET);

ob_start();
require $base . '/academy/index.php';
$html = ob_get_clean();

$checks = [];

$checks['rendered'] = strlen($html) > 20000;
$checks['no_php_errors'] = !preg_match('/Fatal error|Parse error|Warning:|Notice:|Deprecated:|Recoverable error/', $html);
$checks['lang_en'] = str_contains($html, '<html lang="en">');
$checks['skip_link'] = str_contains($html, 'skip-link');
$checks['brand'] = str_contains($html, 'NATCODEV Academy');
$checks['hero_headline'] = str_contains($html, 'tree of life');
$checks['eyebrow'] = str_contains($html, 'Coconut value-chain learning campus');
$checks['stats_band'] = str_contains($html, 'stats-band');
$checks['pathways'] = str_contains($html, 'id="pathways"');
$checks['programs'] = str_contains($html, 'id="programs"');
$checks['catalog'] = str_contains($html, 'id="catalog"');
$checks['certificates'] = str_contains($html, 'id="certificates"');
$checks['route'] = str_contains($html, 'id="route"');
$checks['cta_band'] = str_contains($html, 'cta-band');
$checks['course_grid'] = str_contains($html, 'course-grid');
$checks['single_h1'] = substr_count($html, '<h1') === 1;
$checks['nav_aria'] = str_contains($html, 'aria-label="Academy navigation"');
$checks['filter_aria'] = str_contains($html, 'aria-label="Course catalog filters"');
$checks['pagination_aria'] = str_contains($html, 'aria-label="Course catalog pages"') || !str_contains($html, 'pagination');
$checks['reduced_motion'] = str_contains($html, 'prefers-reduced-motion');
$checks['footer'] = str_contains($html, 'nc-footer');

$countLine = '';
if (preg_match('/Showing [^<]*\. Page (\d+) of (\d+)\./', $html, $m)) {
    $checks['count_line'] = true;
    $checks['page_number'] = (int) $m[1];
    $checks['page_total'] = (int) $m[2];
} else {
    $checks['count_line'] = false;
    $checks['page_number'] = 0;
    $checks['page_total'] = 0;
}

$checks['has_ngn'] = str_contains($html, 'NGN ');

$expects = json_decode((string) (getenv('LANDING_EXPECTS') ?: '[]'), true);
if (!is_array($expects)) {
    $expects = [];
}
$checks['expected_program_present'] = true;
$checks['expected_course_present'] = true;
if (isset($expects['program']) && $expects['program'] !== '') {
    $checks['expected_program_present'] = str_contains($html, htmlspecialchars((string) $expects['program'], ENT_QUOTES, 'UTF-8'));
}
if (isset($expects['course']) && $expects['course'] !== '') {
    $checks['expected_course_present'] = str_contains($html, htmlspecialchars((string) $expects['course'], ENT_QUOTES, 'UTF-8'));
}

$checks['free_filter_clean'] = true;
$checks['paid_filter_clean'] = true;
$payment = (string) ($_GET['payment'] ?? '');
if ($payment === 'free') {
    $checks['free_filter_clean'] = !$checks['has_ngn'] && str_contains($html, 'free tuition');
}
if ($payment === 'paid') {
    $checks['paid_filter_clean'] = str_contains($html, 'paid tuition');
}

$category = (string) ($_GET['category'] ?? '');
if ($category !== '') {
    $checks['category_filter_applied'] = str_contains($html, ' in ' . htmlspecialchars($category, ENT_QUOTES, 'UTF-8'));
} else {
    $checks['category_filter_applied'] = true;
}

$checks['empty_state_absent'] = !str_contains($html, 'No public Academy course is active in this category yet');
$checks['empty_state_present'] = str_contains($html, 'No public Academy course is active in this category yet');

$courseId = (int) ($_GET['course_id'] ?? 0);
if ($courseId > 0) {
    $checks['spotlight'] = str_contains($html, 'Selected course');
} else {
    $checks['spotlight'] = true;
}

echo 'LANDING_RESULT:' . json_encode($checks, JSON_THROW_ON_ERROR) . "\n";
