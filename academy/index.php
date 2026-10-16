<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/academy.php';
require_once __DIR__ . '/../lib/landing-templates.php';
require_once __DIR__ . '/../lib/monnify.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$pdo = db();

// Gate public-facing academy access by module state.
if (!admin_feature_is_allowed($pdo, 'training')) {
    http_response_code(403);
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>NATCODEV Academy</title></head>';
    echo '<body style="font-family:system-ui;padding:60px;text-align:center;color:#555">';
    echo '<h1>Academy Currently Unavailable</h1><p>This module is temporarily disabled.</p></body></html>';
    exit;
}

$academyTemplate = landing_template($pdo, 'academy');
academy_ensure_schema($pdo);
$user = current_user($pdo);
$courses = academy_courses($pdo, null, true);
$programs = $pdo->query("SELECT * FROM academy_programs WHERE status = 'active' ORDER BY sort_order ASC, title ASC")->fetchAll();
$homepage = academy_homepage_settings($pdo);
$courseId = (int) ($_GET['course_id'] ?? 0);
$selectedCourse = null;
foreach ($courses as $courseRow) {
    if ((int) $courseRow['id'] === $courseId) {
        $selectedCourse = $courseRow;
        break;
    }
}
$catalogPerPage = 6;
$catalogCategories = [];
foreach ($courses as $courseRow) {
    $cat = trim((string) ($courseRow['category'] ?? 'Professional Skills'));
    $catalogCategories[$cat === '' ? 'Professional Skills' : $cat] = true;
}
ksort($catalogCategories);
$catalogCategory = trim((string) ($_GET['category'] ?? ''));
$catalogPayment = trim((string) ($_GET['payment'] ?? ''));
$catalogCourses = array_values(array_filter($courses, static function (array $course) use ($catalogCategory): bool {
    return $catalogCategory === '' || strcasecmp((string) ($course['category'] ?? ''), $catalogCategory) === 0;
}));
$catalogCourses = array_values(array_filter($catalogCourses, static function (array $course) use ($catalogPayment): bool {
    if ($catalogPayment === '') {
        return true;
    }
    if ($catalogPayment === 'free') {
        return (int) ($course['is_free'] ?? 0) === 1;
    }
    if ($catalogPayment === 'paid') {
        return (int) ($course['is_free'] ?? 0) === 0;
    }
    return true;
}));
$catalogPage = max(1, (int) ($_GET['page'] ?? 1));
$catalogTotal = count($catalogCourses);
$catalogPages = max(1, (int) ceil($catalogTotal / $catalogPerPage));
$catalogPage = min($catalogPage, $catalogPages);
$pagedCourses = array_slice($catalogCourses, ($catalogPage - 1) * $catalogPerPage, $catalogPerPage);
$pagedCourseGroups = [];
foreach ($pagedCourses as $courseRow) {
    $cat = trim((string) ($courseRow['category'] ?? 'Professional Skills'));
    $pagedCourseGroups[$cat === '' ? 'Professional Skills' : $cat][] = $courseRow;
}
$certTracks = academy_certificate_groups($pdo, null, true);
$stats = [
    'courses' => count($courses),
    'programs' => count($programs),
    'certificates' => count($certTracks),
    'learners' => (int) $pdo->query("SELECT COUNT(DISTINCT user_id) FROM webinar_registrations")->fetchColumn(),
];
$featuredCourse = $selectedCourse ?: ($courses[0] ?? null);
if (!$selectedCourse && (int) $homepage['featured_course_id'] > 0) {
    foreach ($courses as $homepageCourse) {
        if ((int) $homepageCourse['id'] === (int) $homepage['featured_course_id']) {
            $featuredCourse = $homepageCourse;
            break;
        }
    }
}
$logo = app_primary_logo_url();
$hero = academy_homepage_image_url($homepage['hero_image']);
$pathways = $homepage['pathways'];
$courseImages = [
    'grower' => '../assets/academy/course-grower-foundation.svg',
    'marketplace' => '../assets/academy/course-market-readiness.svg',
    'provider' => '../assets/academy/course-provider-accreditation.svg',
    'field' => '../assets/academy/course-field-advisory.svg',
    'governance' => '../assets/academy/course-governance.svg',
    'certificate' => '../assets/academy/course-certificate.svg',
];
$learnerName = $user ? trim((string) ($user['name'] ?? '')) : '';
$userRegisteredIds = [];
if ($user) {
    try {
        $regStmt = $pdo->prepare("SELECT webinar_id FROM webinar_registrations WHERE user_id = ?");
        $regStmt->execute([(int) $user['id']]);
        $userRegisteredIds = array_map('intval', $regStmt->fetchAll(PDO::FETCH_COLUMN));
    } catch (Throwable $e) {
        $userRegisteredIds = [];
    }
}

function public_academy_money(float $amount): string
{
    return 'NGN ' . number_format($amount, 2);
}

function public_academy_course_url(array $course, ?array $user): string
{
    $id = (int) ($course['id'] ?? 0);
    if ($user) {
        return 'dashboard.php?screen=lesson&course_id=' . $id;
    }
    return 'register.php?course_id=' . $id;
}


function public_academy_course_image(array $course, array $images): string
{
    if (function_exists('academy_course_artwork_url')) {
        return academy_course_artwork_url($course, '../');
    }
    $haystack = strtolower((string) ($course['title'] ?? '') . ' ' . (string) ($course['description'] ?? '') . ' ' . (string) ($course['target_roles'] ?? '') . ' ' . (string) ($course['program_title'] ?? ''));
    if (str_contains($haystack, 'market') || str_contains($haystack, 'seller') || str_contains($haystack, 'buyer')) {
        return $images['marketplace'];
    }
    if (str_contains($haystack, 'provider') || str_contains($haystack, 'accreditation') || str_contains($haystack, 'service')) {
        return $images['provider'];
    }
    if (str_contains($haystack, 'field') || str_contains($haystack, 'advisory') || str_contains($haystack, 'agent') || str_contains($haystack, 'agronom')) {
        return $images['field'];
    }
    if (str_contains($haystack, 'coordinator') || str_contains($haystack, 'governance') || str_contains($haystack, 'admin')) {
        return $images['governance'];
    }
    if (str_contains($haystack, 'certificate') || str_contains($haystack, 'certification')) {
        return $images['certificate'];
    }
    return $images['grower'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>NATCODEV Academy — Coconut Value-Chain Learning Campus</title>
  <meta name="description" content="NATCODEV Academy is the public entry point for coconut value-chain learning, learner registration, certificates, and approved role pathways.">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    :root{
      --forest-950:#04170e;--forest-900:#062c18;--forest-800:#0a3a21;--forest-700:#0d5c33;--forest-600:#128048;--forest-500:#1a9d5c;
      --mint:#7fc8a9;--mint-soft:#dcefe4;
      --gold:#c99b22;--gold-deep:#a97e14;--gold-soft:#f6ead0;
      --cream:#f4f2e8;--paper:#fbfaf3;--paper-warm:#fffdf6;
      --ink:#10231a;--ink-soft:#33463b;--muted:#56675d;
      --line:#e0e6da;--line-soft:#ecefe4;
      --sky:#e8f2ff;--clay:#f7efe2;
      --radius:14px;
      --shadow-1:0 1px 2px rgba(10,35,22,.05),0 10px 28px rgba(10,35,22,.06);
      --shadow-2:0 2px 6px rgba(10,35,22,.07),0 22px 48px rgba(10,35,22,.13);
      --font-display:Georgia,'Times New Roman',serif;
      --font-ui:'Segoe UI',system-ui,-apple-system,'Helvetica Neue',Arial,sans-serif;
    }
    *{box-sizing:border-box}
    html{scroll-behavior:smooth}
    body{margin:0;background:var(--cream);color:var(--ink);font-family:var(--font-ui);font-size:1rem;line-height:1.65;-webkit-font-smoothing:antialiased}
    a{text-decoration:none;color:inherit}
    img{max-width:100%}
    :focus-visible{outline:3px solid var(--gold);outline-offset:2px;border-radius:4px}
    .skip-link{position:absolute;left:12px;top:-48px;z-index:200;background:var(--forest-900);color:#fff;padding:10px 16px;border-radius:8px;font-weight:700;transition:top .2s}
    .skip-link:focus{top:12px}

    .topbar{position:sticky;top:0;z-index:80;background:rgba(255,253,246,.94);backdrop-filter:blur(10px);border-bottom:1px solid var(--line);transition:box-shadow .25s}
    .topbar.scrolled{box-shadow:0 6px 24px rgba(10,35,22,.10)}
    .topbar-inner{max-width:1280px;margin:0 auto;padding:12px 22px;display:flex;align-items:center;gap:20px}
    .brand{display:flex;align-items:center;gap:12px;color:var(--forest-800);flex-shrink:0}
    .brand img{width:46px;height:46px;border-radius:50%;object-fit:contain;background:#fff;border:1px solid var(--line);padding:3px}
    .brand-text strong{display:block;font-size:1.12rem;letter-spacing:-.01em;line-height:1.15}
    .brand-text span{display:block;color:var(--muted);font-size:.72rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase}
    .mainnav{display:flex;align-items:center;gap:2px;margin-left:auto;overflow-x:auto;scrollbar-width:none}
    .mainnav::-webkit-scrollbar{display:none}
    .mainnav a{padding:9px 13px;border-radius:9px;color:var(--ink-soft);font-weight:650;font-size:.95rem;white-space:nowrap}
    .mainnav a:hover{background:var(--mint-soft);color:var(--forest-800)}
    .auth{display:flex;align-items:center;gap:10px;flex-shrink:0}
    .auth a.login{padding:9px 14px;border-radius:9px;font-weight:700;font-size:.95rem;color:var(--forest-800)}
    .auth a.login:hover{background:var(--mint-soft)}

    /* Topbar Profile Dropdown */
    .tb-user-dropdown, .dropdown{position:relative;display:inline-flex;align-items:center}
    button.tb-user, .tb-user{position:relative;height:42px;min-width:0;max-width:200px;padding:4px 12px 4px 5px;border-radius:999px;border:1px solid var(--line);background:#ffffff !important;color:var(--forest-950) !important;cursor:pointer;display:inline-flex;align-items:center;gap:8px;font-family:inherit;font-size:13px;font-weight:normal;text-align:left;line-height:normal;outline:none;box-shadow:0 1px 3px rgba(0,0,0,0.06);transition:all .18s ease;user-select:none;-webkit-user-select:none}
    button.tb-user:hover, .tb-user:hover{background:#f6faf6 !important;border-color:var(--forest-600)}
    button.tb-user:focus-visible, .tb-user:focus-visible{outline:2px solid var(--forest-600);outline-offset:2px}
    .dropdown.open button.tb-user, .dropdown.open .tb-user, .dropdown.active button.tb-user, .dropdown.active .tb-user{border-color:var(--forest-600);background:#ffffff !important;box-shadow:0 0 0 3px rgba(18,128,72,.18)}

    .tb-user .av{width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--forest-800),var(--forest-600));color:#fff;display:flex;align-items:center;justify-content:center;font-weight:900;font-size:13px;flex-shrink:0}
    .tb-user-details{display:flex;flex-direction:column;min-width:0;text-align:left;line-height:1.2}
    .tb-user-details .nm{max-width:96px;font-size:12px;font-weight:800;color:var(--forest-950);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .tb-user-details .st{font-size:10px;font-weight:700;color:var(--forest-600);line-height:1.1;white-space:nowrap;text-transform:capitalize}
    .tb-user-caret{font-size:10px;color:var(--muted);margin-left:2px;margin-right:2px;transition:transform .22s cubic-bezier(.16,1,.3,1),color .15s ease;flex-shrink:0}
    .dropdown.open .tb-user-caret, .dropdown.active .tb-user-caret{transform:rotate(180deg);color:var(--forest-700)}
    @media(hover:hover) and (pointer:fine){
      .dropdown:not(.closed):hover .tb-user-caret{transform:rotate(180deg);color:var(--forest-700)}
    }
    .dropdown.closed .tb-user-caret{transform:none !important;color:var(--muted) !important}

    .dropdown-menu{display:none;position:absolute;right:0;top:calc(100% + 8px);width:260px;max-width:calc(100vw - 20px);background:#fff;border:1px solid var(--line);border-radius:12px;box-shadow:0 18px 42px rgba(4,23,14,.18),0 4px 12px rgba(0,0,0,.06);padding:8px;z-index:200;box-sizing:border-box}
    .dropdown-menu::before{content:"";position:absolute;top:-12px;left:0;right:0;height:12px;background:transparent}
    .dropdown.open > .dropdown-menu, .dropdown.active > .dropdown-menu{display:flex !important;flex-direction:column;gap:2px;animation:indexDropdownFade .18s cubic-bezier(.16,1,.3,1)}
    @media(hover:hover) and (pointer:fine){
      .dropdown:not(.closed):hover > .dropdown-menu{display:flex !important;flex-direction:column;gap:2px;animation:indexDropdownFade .18s cubic-bezier(.16,1,.3,1)}
    }
    .dropdown.closed > .dropdown-menu{display:none !important}

    @keyframes indexDropdownFade{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}

    .dropdown-header{padding:10px 12px;border-radius:8px;background:linear-gradient(135deg,#f0fdf4,#e8f5e9);border:1px solid rgba(18,128,72,.15);margin-bottom:4px}
    .dropdown-header .dh-name{font-weight:850;font-size:13px;color:var(--forest-950);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;line-height:1.3}
    .dropdown-header .dh-email{font-size:11px;color:#475569;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:2px;font-weight:600}
    .dropdown-header .dh-tags{display:flex;gap:6px;margin-top:8px;flex-wrap:wrap;align-items:center}
    .badge-pill{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:20px;font-size:10px;font-weight:800}
    .bp-green{background:#D1FAE5;color:#065F46}
    .bp-blue{background:#DBEAFE;color:#1E40AF}

    .dropdown-divider{height:1px;background:var(--line);margin:4px 0}

    .dropdown-menu a, .dropdown-item{display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:8px;color:var(--forest-950) !important;font-size:13px;font-weight:700;text-decoration:none;transition:background-color .15s ease,color .15s ease;line-height:1.3}
    .dropdown-menu a i, .dropdown-item i{width:18px;text-align:center;font-size:13px;color:var(--muted);transition:color .15s ease;flex-shrink:0}
    .dropdown-menu a:hover, .dropdown-item:hover, .dropdown-menu a:focus-visible, .dropdown-item:focus-visible{background:var(--mint-soft) !important;color:var(--forest-700) !important;outline:2px solid var(--forest-600);outline-offset:-2px}
    .dropdown-menu a:hover i, .dropdown-item:hover i{color:var(--forest-700)}

    .dropdown-item-danger{color:#dc2626 !important}
    .dropdown-item-danger i{color:#dc2626 !important}
    .dropdown-item-danger:hover, .dropdown-item-danger:focus-visible{background:#fee2e2 !important;color:#991b1b !important;outline:2px solid #dc2626;outline-offset:-2px}
    .dropdown-item-danger:hover i, .dropdown-item-danger:focus-visible i{color:#991b1b !important}


    .btn{display:inline-flex;align-items:center;justify-content:center;gap:9px;border-radius:10px;padding:13px 22px;font-weight:750;font-size:.98rem;cursor:pointer;border:1px solid transparent;transition:transform .18s ease,box-shadow .18s ease,background .18s ease,border-color .18s ease;min-height:48px}
    .btn:hover{transform:translateY(-1px)}
    .btn:active{transform:translateY(0)}
    .btn-primary{background:var(--forest-700);color:#fff;border-color:var(--forest-700);box-shadow:0 8px 20px rgba(13,92,51,.28)}
    .btn-primary:hover{background:var(--forest-800);border-color:var(--forest-800)}
    .btn-gold{background:var(--gold);color:#241a02;border-color:var(--gold);box-shadow:0 8px 22px rgba(201,155,34,.32)}
    .btn-gold:hover{background:var(--gold-deep);border-color:var(--gold-deep)}
    .btn-ghost{background:#fff;color:var(--forest-800);border-color:var(--line)}
    .btn-ghost:hover{border-color:var(--forest-600);background:var(--paper-warm)}
    .btn-sm{padding:9px 16px;min-height:40px;font-size:.9rem}

    .hero{position:relative;background:var(--forest-950);color:#f2f7f0;overflow:hidden}
    .hero::before{content:"";position:absolute;inset:0;background:linear-gradient(105deg,rgba(4,23,14,.96) 8%,rgba(6,44,24,.86) 42%,rgba(10,58,33,.55) 68%,rgba(6,44,24,.35));z-index:1}
    .hero-bg{position:absolute;inset:0;z-index:0}
    .hero-bg img{width:100%;height:100%;object-fit:cover;opacity:.5}
    .hero-leaf{position:absolute;right:-70px;top:-90px;width:430px;height:430px;z-index:1;opacity:.16;color:var(--mint)}
    .hero-inner{position:relative;z-index:2;max-width:1280px;margin:0 auto;padding:46px 22px 48px;display:grid;grid-template-columns:minmax(0,1.15fr) minmax(0,.85fr);gap:40px;align-items:center}
    .eyebrow{display:inline-flex;align-items:center;gap:9px;color:var(--gold);font-weight:800;letter-spacing:.16em;text-transform:uppercase;font-size:.76rem}
    .eyebrow::before{content:"";width:26px;height:2px;background:var(--gold);border-radius:2px}
    .hero h1{font-family:var(--font-display);font-size:clamp(2.2rem,4.4vw,3.6rem);line-height:1.06;letter-spacing:-.015em;margin:12px 0 16px;max-width:14ch;font-weight:700}
    .hero h1 em{font-style:italic;color:var(--mint)}
    .hero .lede{font-size:1.05rem;line-height:1.6;color:#dcebe0;max-width:56ch;font-weight:500}
    .hero-actions{display:flex;gap:13px;flex-wrap:wrap;margin-top:24px}
    .hero-actions .btn-ghost{background:rgba(255,255,255,.10);color:#fff;border-color:rgba(255,255,255,.35)}
    .hero-actions .btn-ghost:hover{background:rgba(255,255,255,.18);border-color:rgba(255,255,255,.5)}
    .hero-trust{display:flex;gap:20px;flex-wrap:wrap;margin-top:24px;padding-top:18px;border-top:1px solid rgba(255,255,255,.14)}
    .hero-trust div{font-size:.86rem;color:#b9d2c1;display:flex;align-items:center;gap:8px}
    .hero-trust i{color:var(--gold);font-size:.8rem}

    .passport{position:relative}
    .passport-card{background:var(--paper-warm);color:var(--ink);border-radius:18px;box-shadow:0 30px 70px rgba(2,12,7,.5);overflow:hidden;border:1px solid rgba(255,255,255,.5)}
    .passport-media{position:relative;height:190px;overflow:hidden}
    .passport-media img{width:100%;height:100%;object-fit:cover;display:block}
    .passport-media::after{content:"";position:absolute;inset:0;background:linear-gradient(180deg,transparent 45%,rgba(4,23,14,.72))}
    .passport-code{position:absolute;left:16px;top:16px;z-index:2;background:rgba(4,23,14,.82);color:var(--gold);font-size:.72rem;font-weight:800;letter-spacing:.1em;padding:6px 11px;border-radius:999px}
    .passport-kind{position:absolute;left:16px;bottom:14px;z-index:2;color:#fff;font-weight:800;font-size:.95rem;text-shadow:0 2px 10px rgba(0,0,0,.5)}
    .passport-body{padding:24px 26px 26px}
    .passport-body h2{font-family:var(--font-display);font-size:1.5rem;line-height:1.22;margin:0 0 10px;color:var(--forest-900);font-weight:700}
    .passport-body p{margin:0 0 18px;color:var(--ink-soft);font-size:.96rem;line-height:1.6}
    .passport-meta{display:flex;flex-wrap:wrap;gap:8px 18px;color:var(--muted);font-size:.88rem;font-weight:600;margin-bottom:20px}
    .passport-meta span{display:inline-flex;align-items:center;gap:7px}
    .passport-meta i{color:var(--forest-600);font-size:.82rem;width:16px;text-align:center}
    .passport-foot{display:flex;align-items:center;justify-content:space-between;gap:14px;border-top:1px solid var(--line-soft);padding-top:18px}
    .passport-price{font-family:var(--font-display);font-size:1.45rem;font-weight:700;color:var(--forest-800)}
    .passport-price small{display:block;font-family:var(--font-ui);font-size:.72rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--muted)}
    .passport-badge{position:absolute;right:-14px;top:-14px;z-index:3;background:var(--gold);color:#241a02;font-size:.7rem;font-weight:900;letter-spacing:.12em;text-transform:uppercase;padding:8px 14px;border-radius:999px;box-shadow:0 10px 24px rgba(201,155,34,.4);transform:rotate(4deg)}

    .stats-band{position:relative;z-index:5}
    .stats-inner{max-width:1280px;margin:0 auto;padding:0 22px}
    .stats-card{background:var(--paper);border:1px solid var(--line);border-radius:18px;box-shadow:var(--shadow-2);display:grid;grid-template-columns:repeat(4,1fr)}
    .stat{padding:26px 24px;border-left:1px solid var(--line-soft)}
    .stat:first-child{border-left:0}
    .stat b{display:block;font-family:var(--font-display);font-size:2.15rem;font-weight:700;color:var(--forest-800);line-height:1.1}
    .stat span{display:block;margin-top:5px;color:var(--muted);font-size:.82rem;font-weight:700;letter-spacing:.07em;text-transform:uppercase}

    .quick-nav-bar{background:rgba(255,253,246,.96);backdrop-filter:blur(10px);border-bottom:1px solid var(--line);position:sticky;top:69px;z-index:70;box-shadow:0 2px 10px rgba(10,35,22,.05);transition:all .2s ease}
    .quick-nav-inner{max-width:1280px;margin:0 auto;padding:10px 22px;display:flex;align-items:center;gap:18px;overflow-x:auto;scrollbar-width:none}
    .quick-nav-inner::-webkit-scrollbar{display:none}
    .quick-nav-label{font-size:.78rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--forest-800);display:flex;align-items:center;gap:6px;white-space:nowrap;flex-shrink:0}
    .quick-nav-pills{display:flex;align-items:center;gap:8px;white-space:nowrap}
    .quick-pill{display:inline-flex;align-items:center;gap:7px;padding:7px 15px;border-radius:999px;font-size:.88rem;font-weight:700;color:var(--ink-soft);background:var(--paper);border:1px solid var(--line);transition:all .18s ease;text-decoration:none}
    .quick-pill:hover{background:var(--mint-soft);color:var(--forest-800);border-color:var(--mint)}
    .quick-pill.active{background:var(--forest-700);color:#fff;border-color:var(--forest-700);box-shadow:0 2px 8px rgba(13,92,51,.25)}

    .section{max-width:1280px;margin:0 auto;padding:72px 22px 0;scroll-margin-top:124px}
    .section-head{display:flex;justify-content:space-between;align-items:flex-end;gap:24px;margin-bottom:34px;flex-wrap:wrap}
    .section-head .kicker{display:inline-flex;align-items:center;gap:9px;color:var(--forest-600);font-weight:800;letter-spacing:.14em;text-transform:uppercase;font-size:.74rem;margin-bottom:12px}
    .section-head .kicker::before{content:"";width:22px;height:2px;background:var(--gold);border-radius:2px}
    .section-head h2{font-family:var(--font-display);font-size:clamp(1.75rem,3.1vw,2.5rem);line-height:1.12;margin:0;color:var(--forest-900);font-weight:700;letter-spacing:-.01em;max-width:22ch}
    .section-head .sub{margin:12px 0 0;color:var(--muted);line-height:1.6;max-width:62ch}
    .panel{background:var(--paper);border:1px solid var(--line);border-radius:var(--radius);box-shadow:var(--shadow-1)}

    .pathway-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:18px}
    .pathway{position:relative;background:var(--paper);border:1px solid var(--line);border-radius:var(--radius);padding:28px 24px 26px;box-shadow:var(--shadow-1);transition:transform .22s ease,box-shadow .22s ease;overflow:hidden}
    .pathway::before{content:attr(data-index);position:absolute;right:14px;top:6px;font-family:var(--font-display);font-size:3.4rem;font-weight:700;color:var(--mint-soft);line-height:1;z-index:0}
    .pathway:hover{transform:translateY(-4px);box-shadow:var(--shadow-2)}
    .pathway-icon{width:52px;height:52px;border-radius:14px;display:grid;place-items:center;background:var(--mint-soft);color:var(--forest-700);font-size:1.25rem;margin-bottom:20px;position:relative;z-index:1}
    .pathway h3{font-family:var(--font-display);font-size:1.22rem;margin:0 0 10px;color:var(--forest-900);font-weight:700;position:relative;z-index:1}
    .pathway p{margin:0;color:var(--ink-soft);font-size:.93rem;line-height:1.62;position:relative;z-index:1}

    .program-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}
    .program{display:flex;flex-direction:column;background:var(--paper);border:1px solid var(--line);border-radius:var(--radius);overflow:hidden;box-shadow:var(--shadow-1);transition:transform .22s ease,box-shadow .22s ease}
    .program:hover{transform:translateY(-4px);box-shadow:var(--shadow-2)}
    .program-media{position:relative;height:158px;background:linear-gradient(120deg,var(--forest-800),var(--forest-600));display:grid;place-items:center;overflow:hidden}
    .program-media img{width:100%;height:100%;object-fit:cover;display:block}
    .program-num{font-family:var(--font-display);font-size:2.6rem;color:rgba(255,255,255,.5);font-weight:700}
    .program-body{padding:22px 22px 24px;display:flex;flex-direction:column;flex:1}
    .program-body h3{font-family:var(--font-display);font-size:1.18rem;margin:0 0 9px;color:var(--forest-900);font-weight:700;line-height:1.3}
    .program-body p{margin:0 0 16px;color:var(--ink-soft);font-size:.92rem;line-height:1.6;flex:1}

    .badge{display:inline-flex;align-items:center;gap:6px;max-width:100%;padding:5px 11px;border-radius:999px;font-size:.74rem;font-weight:750;letter-spacing:.02em;background:var(--mint-soft);color:var(--forest-800);white-space:nowrap}
    .badge.gold{background:var(--gold-soft);color:#6d4e05}
    .badge.sky{background:var(--sky);color:#1c4e80}
    .badge.forest{background:var(--forest-700);color:#fff}

    .catalog-tools{display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end}
    .field{display:flex;flex-direction:column;gap:5px}
    .field label{font-size:.7rem;font-weight:800;letter-spacing:.09em;text-transform:uppercase;color:var(--muted)}
    .field select{appearance:none;-webkit-appearance:none;border:1px solid var(--line);background:var(--paper-warm) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' fill='none' stroke='%2356675d' stroke-width='2' stroke-linecap='round'/%3E%3C/svg%3E") no-repeat right 14px center;border-radius:10px;padding:11px 40px 11px 14px;font-family:var(--font-ui);font-size:.92rem;font-weight:600;color:var(--ink);cursor:pointer;min-height:44px}
    .field select:hover{border-color:var(--forest-600)}
    .catalog-count{color:var(--muted);font-size:.9rem;margin-top:10px}
    .category-head{display:flex;align-items:center;gap:14px;margin:34px 0 18px}
    .category-head h3{font-family:var(--font-display);font-size:1.3rem;margin:0;color:var(--forest-900);font-weight:700}
    .category-head .rule{flex:1;height:1px;background:var(--line)}
    .course-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}
    .course{display:flex;flex-direction:column;background:var(--paper);border:1px solid var(--line);border-radius:var(--radius);overflow:hidden;box-shadow:var(--shadow-1);transition:transform .22s ease,box-shadow .22s ease}
    .course:hover{transform:translateY(-4px);box-shadow:var(--shadow-2)}
    .course-thumb{position:relative;height:170px;overflow:hidden;background:var(--mint-soft)}
    .course-thumb img{width:100%;height:100%;object-fit:cover;display:block;transition:transform .35s ease}
    .course:hover .course-thumb img{transform:scale(1.04)}
    .course-thumb .code{position:absolute;left:14px;bottom:12px;background:rgba(4,23,14,.85);color:#fff;font-size:.7rem;font-weight:800;letter-spacing:.08em;padding:5px 10px;border-radius:999px}
    .course-body{padding:20px 20px 22px;display:flex;flex-direction:column;flex:1}
    .course-tags{display:flex;gap:7px;flex-wrap:wrap;margin-bottom:13px}
    .course-body h3{font-family:var(--font-display);font-size:1.16rem;margin:0 0 9px;color:var(--forest-900);font-weight:700;line-height:1.3}
    .course-body .desc{margin:0 0 14px;color:var(--ink-soft);font-size:.91rem;line-height:1.58;flex:1}
    .course-meta{display:flex;flex-wrap:wrap;gap:6px 16px;color:var(--muted);font-size:.84rem;font-weight:600;margin-bottom:18px}
    .course-meta span{display:inline-flex;align-items:center;gap:7px}
    .course-meta i{color:var(--forest-600);font-size:.8rem;width:15px;text-align:center}
    .course-foot{display:flex;align-items:center;justify-content:space-between;gap:12px;border-top:1px solid var(--line-soft);padding-top:16px}
    .price{font-family:var(--font-display);font-size:1.22rem;font-weight:700;color:var(--forest-800)}
    .price.free{color:var(--forest-600)}
    .pagination{display:flex;justify-content:center;gap:8px;flex-wrap:wrap;margin-top:44px}
    .pagination a{min-width:44px;height:44px;display:inline-grid;place-items:center;border-radius:11px;border:1px solid var(--line);background:var(--paper);color:var(--ink-soft);font-weight:700;font-size:.92rem;padding:0 14px;transition:background .18s,border-color .18s}
    .pagination a:hover{border-color:var(--forest-600);background:var(--mint-soft)}
    .pagination a.active{background:var(--forest-700);border-color:var(--forest-700);color:#fff}
    .empty-state{padding:44px 28px;text-align:center;color:var(--muted)}
    .empty-state i{display:block;font-size:1.7rem;color:var(--mint);margin-bottom:14px}

    .cert-band{background:linear-gradient(135deg,var(--forest-950) 0%,var(--forest-800) 55%,var(--forest-700) 130%);border-radius:22px;padding:52px 50px;color:#eef6f0;position:relative;overflow:hidden}
    .cert-band::before{content:"";position:absolute;left:-60px;bottom:-80px;width:340px;height:340px;border-radius:50%;background:radial-gradient(circle,rgba(127,200,169,.22),transparent 70%)}
    .cert-band .section-head h2{color:#fff}
    .cert-band .section-head .sub{color:#b9d2c1}
    .cert-band .section-head .kicker{color:var(--gold)}
    .cert-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:16px;position:relative;z-index:1}
    .cert-track{display:flex;gap:18px;align-items:flex-start;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.14);border-radius:var(--radius);padding:22px;transition:background .2s,border-color .2s}
    .cert-track:hover{background:rgba(255,255,255,.10);border-color:rgba(255,255,255,.26)}
    .cert-track .mark{width:46px;height:46px;flex-shrink:0;border-radius:12px;display:grid;place-items:center;background:rgba(201,155,34,.18);color:var(--gold);font-size:1.15rem;border:1px solid rgba(201,155,34,.35)}
    .cert-track h3{font-family:var(--font-display);font-size:1.1rem;margin:0 0 6px;color:#fff;font-weight:700;line-height:1.3}
    .cert-track p{margin:0 0 10px;color:#b9d2c1;font-size:.89rem;line-height:1.58}
    .cert-track .count{font-size:.74rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--gold)}
    .cert-empty{color:#b9d2c1;text-align:center}
    .cert-empty i{color:var(--gold)}

    .route-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}
    .route-step{position:relative;padding:34px 30px 30px;background:var(--paper);border:1px solid var(--line);border-radius:var(--radius);box-shadow:var(--shadow-1)}
    .route-num{width:44px;height:44px;border-radius:50%;display:grid;place-items:center;background:var(--forest-700);color:#fff;font-family:var(--font-display);font-weight:700;font-size:1.05rem;margin-bottom:18px;box-shadow:0 8px 18px rgba(13,92,51,.3)}
    .route-step h3{font-family:var(--font-display);font-size:1.18rem;margin:0 0 9px;color:var(--forest-900);font-weight:700}
    .route-step p{margin:0;color:var(--ink-soft);font-size:.92rem;line-height:1.62}

    .spotlight{display:grid;grid-template-columns:minmax(0,1fr) 360px;overflow:hidden;border:2px solid var(--gold);border-radius:18px;box-shadow:var(--shadow-2)}
    .spotlight-main{padding:34px 36px}
    .spotlight-main h2{font-family:var(--font-display);font-size:1.65rem;margin:8px 0 12px;color:var(--forest-900);font-weight:700}
    .spotlight-main p{color:var(--ink-soft);line-height:1.65;margin:0 0 16px}
    .spotlight-meta{display:flex;flex-wrap:wrap;gap:8px 20px;color:var(--muted);font-size:.9rem;font-weight:600}
    .spotlight-side{background:linear-gradient(150deg,var(--forest-800),var(--forest-900));color:#fff;padding:34px 30px;display:flex;flex-direction:column;justify-content:center;gap:14px}
    .spotlight-side .price{color:#fff;font-size:1.7rem}
    .spotlight-side .price small{display:block;font-family:var(--font-ui);font-size:.7rem;letter-spacing:.12em;text-transform:uppercase;color:var(--mint);font-weight:800}

    .cta-band{position:relative;margin-top:88px;background:linear-gradient(120deg,var(--forest-900),var(--forest-700) 120%);color:#fff;overflow:hidden}
    .cta-band::before{content:"";position:absolute;right:-110px;top:-130px;width:480px;height:480px;border-radius:50%;background:radial-gradient(circle,rgba(201,155,34,.20),transparent 68%)}
    .cta-inner{position:relative;z-index:1;max-width:1280px;margin:0 auto;padding:78px 22px;display:flex;align-items:center;justify-content:space-between;gap:40px;flex-wrap:wrap}
    .cta-inner h2{font-family:var(--font-display);font-size:clamp(1.7rem,3.2vw,2.5rem);margin:0 0 10px;font-weight:700;letter-spacing:-.01em;line-height:1.15;max-width:24ch}
    .cta-inner p{margin:0;color:#c4dbc9;max-width:52ch}
    .cta-actions{display:flex;gap:13px;flex-wrap:wrap}
    .cta-actions .btn-ghost{background:rgba(255,255,255,.10);color:#fff;border-color:rgba(255,255,255,.35)}
    .cta-actions .btn-ghost:hover{background:rgba(255,255,255,.18);border-color:rgba(255,255,255,.5)}

    .reveal{opacity:0;transform:translateY(20px);transition:opacity .65s ease,transform .65s cubic-bezier(.22,.61,.36,1);transition-delay:var(--d,0ms)}
    .reveal.in-view{opacity:1;transform:none}
    @media (prefers-reduced-motion: reduce){
      html{scroll-behavior:auto}
      .reveal{opacity:1;transform:none;transition:none}
      .pathway,.program,.course,.btn{transition:none}
      .course:hover .course-thumb img{transform:none}
    }

    @media (max-width:1080px){
      .hero-inner{grid-template-columns:1fr;padding:36px 22px 56px;gap:32px}
      .hero h1{max-width:none}
      .passport{max-width:560px}
      .pathway-grid{grid-template-columns:repeat(2,1fr)}
      .program-grid{grid-template-columns:repeat(2,1fr)}
      .course-grid{grid-template-columns:repeat(2,1fr)}
      .route-grid{grid-template-columns:1fr}
      .spotlight{grid-template-columns:1fr}
      .stats-card{grid-template-columns:repeat(2,1fr)}
      .stat:nth-child(3){border-left:0}
      .stat{border-top:1px solid var(--line-soft)}
      .stat:nth-child(-n+2){border-top:0}
    }
    @media (max-width:720px){
      .topbar-inner{flex-wrap:wrap;gap:10px;padding:11px 16px}
      .mainnav{order:3;width:100%;margin-left:0}
      .auth{margin-left:auto}
      .tb-user{max-width:none;padding:3px 8px 3px 3px}
      .tb-user-details{display:none}
      .dropdown-menu{width:244px;right:-4px}
      .quick-nav-bar{top:58px}
      .quick-nav-inner{padding:8px 16px;gap:10px}
      .quick-nav-label{display:none}
      .quick-pill{padding:6px 12px;font-size:.82rem}
      .section{padding:54px 16px 0;scroll-margin-top:104px}
      .hero-inner{padding:30px 16px 50px}
      .hero-actions .btn{width:100%}
      .pathway-grid,.program-grid,.course-grid{grid-template-columns:1fr}
      .cert-band{padding:40px 24px}
      .cert-grid{grid-template-columns:1fr}
      .cert-track{flex-direction:column}
      .stats-inner{padding:0 16px}
      .stat{padding:20px 16px}
      .stat b{font-size:1.7rem}
      .cta-inner{padding:56px 16px}
      .section-head{margin-bottom:26px}
      .spotlight-main{padding:26px 22px}
      .passport-badge{right:8px;top:8px}
    }
    @media (max-width:480px){
      .auth a.login{padding:8px 10px;font-size:.85rem}
      .auth a.login .login-label{display:none}
      .dropdown-menu{width:232px;right:-8px}
     }
  .academy-template-b .pathway-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
  .academy-template-b .program-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
  .academy-template-b .course-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
  .academy-template-b .route-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
  .academy-template-b .quiet-link{color:#b9d2c1;text-decoration:underline;font-weight:650}
  </style>
</head>
<body class="academy-template-<?= e($academyTemplate) ?>">
  <a class="skip-link" href="#catalog">Skip to course catalog</a>

  <header class="topbar" id="topbar">
    <div class="topbar-inner">
      <a class="brand" href="../index.php" aria-label="NATCODEV home">
        <img src="<?= e($logo) ?>" alt="NATCODEV logo">
        <span class="brand-text"><strong>NATCODEV Academy</strong><span>Learning campus</span></span>
      </a>
      <nav class="mainnav" aria-label="Academy navigation">
        <a href="#catalog">Catalog</a>
        <a href="#pathways">Pathways</a>
        <a href="program.php">Programs</a>
        <a href="#certificates">Certificates</a>
        <a href="#route">How it works</a>
        <a href="../verify-certificate.php">Verify Certificate</a>
      </nav>
      <div class="auth">
        <?php if ($user): 
          $initials = strtoupper(substr((string) ($user['name'] ?? 'U'), 0, 1));
          $role = academy_current_role($pdo, $user);
          require_once __DIR__ . '/../lib/wallet.php';
          $userWallet = wallet_get_or_create($pdo, (int) $user['id']);
          $walletBalance = (float) ($userWallet['balance'] ?? 0);
        ?>
          <a class="login" href="dashboard.php"><i class="fas fa-graduation-cap"></i> <span class="login-label">Learner Dashboard</span></a>
          <div class="tb-user-dropdown dropdown" id="landingProfileDropdown">
            <button class="tb-user" id="landingProfileDropdownBtn" type="button" aria-haspopup="true" aria-expanded="false" aria-label="Learner profile menu">
              <span class="av"><?= e($initials) ?></span>
              <span class="tb-user-details">
                <span class="nm"><?= e((string) ($user['name'] ?? 'User')) ?></span>
                <span class="st"><?= e(academy_role_label($role)) ?></span>
              </span>
              <i class="fas fa-chevron-down tb-user-caret" aria-hidden="true"></i>
            </button>
            <div class="dropdown-menu" id="landingProfileDropdownMenu" role="menu" aria-labelledby="landingProfileDropdownBtn">
              <div class="dropdown-header">
                <div class="dh-name"><?= e((string) ($user['name'] ?? 'User')) ?></div>
                <div class="dh-email"><?= e((string) ($user['email'] ?? '')) ?></div>
                <div class="dh-tags">
                  <span class="badge-pill bp-green"><?= e(academy_role_label($role)) ?></span>
                  <span class="badge-pill bp-blue"><i class="fas fa-wallet"></i> <?= e(public_academy_money($walletBalance)) ?></span>
                </div>
              </div>
              <div class="dropdown-divider"></div>
              <a href="dashboard.php?screen=catalog" class="dropdown-item" role="menuitem">
                <i class="fas fa-compass"></i>
                <span>Course Catalog</span>
              </a>
              <a href="dashboard.php?screen=learning" class="dropdown-item" role="menuitem">
                <i class="fas fa-graduation-cap"></i>
                <span>My Learning</span>
              </a>
              <a href="dashboard.php?screen=certificates" class="dropdown-item" role="menuitem">
                <i class="fas fa-award"></i>
                <span>Certificates</span>
              </a>
              <a href="dashboard.php?screen=transactions" class="dropdown-item" role="menuitem">
                <i class="fas fa-wallet"></i>
                <span>Wallet & Transactions</span>
              </a>
              <a href="dashboard.php?screen=messages" class="dropdown-item" role="menuitem">
                <i class="fas fa-bell"></i>
                <span>Messages & Alerts</span>
              </a>
              <a href="dashboard.php?screen=settings" class="dropdown-item" role="menuitem">
                <i class="fas fa-user-gear"></i>
                <span>Profile & Settings</span>
              </a>
              <a href="dashboard.php?screen=support" class="dropdown-item" role="menuitem">
                <i class="fas fa-circle-question"></i>
                <span>Help & Support</span>
              </a>
              <div class="dropdown-divider"></div>
              <a href="../index.php" class="dropdown-item" role="menuitem">
                <i class="fas fa-house"></i>
                <span>NATCODEV Home</span>
              </a>
              <div class="dropdown-divider"></div>
              <a href="logout.php?_csrf=<?= e(csrf_token()) ?>" class="dropdown-item dropdown-item-danger" role="menuitem">
                <i class="fas fa-right-from-bracket"></i>
                <span>Log Out</span>
              </a>
            </div>
          </div>
        <?php else: ?>
          <a class="login" href="../login.php?next=academy/dashboard.php">Login</a>
          <a class="btn btn-primary btn-sm" href="register.php">Register as Learner</a>
        <?php endif; ?>
      </div>
    </div>
  </header>

  <main>
    <?php require __DIR__ . "/../templates/academy-" . $academyTemplate . ".php"; ?>
  </main>

  <section class="cta-band">
    <div class="cta-inner">
      <div>
        <h2><?= $homepage['cta_heading'] ?></h2>
        <p><?= $homepage['cta_copy'] ?></p>
      </div>
      <div class="cta-actions">
        <?php if ($user): ?>
          <a class="btn btn-gold" href="dashboard.php?screen=catalog"><i class="fas fa-user-graduate"></i> Open My Learning</a>
        <?php else: ?>
          <a class="btn btn-gold" href="register.php"><i class="fas fa-user-graduate"></i> Register as Learner</a>
          <a class="btn btn-ghost" href="../login.php?next=academy/dashboard.php"><i class="fas fa-right-to-bracket"></i> Sign In</a>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <?= public_footer() ?>

  <script>
    (function () {
      var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      var topbar = document.getElementById('topbar');
      if (topbar) {
        var onScroll = function () {
          topbar.classList.toggle('scrolled', window.scrollY > 8);
        };
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
      }

      // Initialize topbar profile dropdowns
      var dropdowns = document.querySelectorAll('.dropdown, .tb-user-dropdown');
      dropdowns.forEach(function (dd) {
        var toggle = dd.querySelector('#landingProfileDropdownBtn, #profileDropdownBtn, .tb-user, .tb-user-toggle, [data-dropdown-toggle], button') || dd;
        var menu = dd.querySelector('.dropdown-menu');
        if (!toggle || !menu) return;

        if (toggle.dataset.dropdownReady) return;
        toggle.dataset.dropdownReady = 'true';

        function closeDropdown(focusToggle) {
          dd.classList.remove('open', 'active');
          dd.classList.add('closed');
          if (toggle.setAttribute) toggle.setAttribute('aria-expanded', 'false');
          if (focusToggle && typeof toggle.focus === 'function') {
            toggle.focus();
          }
        }

        function openDropdown() {
          document.querySelectorAll('.dropdown.open, .tb-user-dropdown.open').forEach(function (other) {
            if (other !== dd) {
              other.classList.remove('open', 'active', 'closed');
              var otherBtn = other.querySelector('#landingProfileDropdownBtn, #profileDropdownBtn, .tb-user, .tb-user-toggle, [data-dropdown-toggle], button') || other;
              if (otherBtn && otherBtn.setAttribute) otherBtn.setAttribute('aria-expanded', 'false');
            }
          });

          dd.classList.remove('closed');
          dd.classList.add('open', 'active');
          if (toggle.setAttribute) toggle.setAttribute('aria-expanded', 'true');
        }

        toggle.addEventListener('click', function (e) {
          if (e.target.closest('.dropdown-menu a')) return;
          e.preventDefault();
          e.stopPropagation();

          var isOpen = dd.classList.contains('open');
          if (isOpen) {
            closeDropdown(false);
          } else {
            openDropdown();
          }
        });

        // Pointer enter: open if device supports hover and not explicitly closed
        dd.addEventListener('mouseenter', function () {
          if (window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
            if (!dd.classList.contains('closed')) {
              openDropdown();
            }
          }
        });

        // Pointer leave: clear closed suppression and close on fine-pointer devices
        dd.addEventListener('mouseleave', function () {
          dd.classList.remove('closed');
          if (window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
            closeDropdown(false);
            dd.classList.remove('closed');
          } else if (!dd.classList.contains('open')) {
            if (toggle.setAttribute) toggle.setAttribute('aria-expanded', 'false');
          }
        });

        // Keyboard navigation support (Arrow keys, Home, End, Escape, Tab)
        dd.addEventListener('keydown', function (e) {
          var items = Array.prototype.slice.call(menu.querySelectorAll('a.dropdown-item, a[role="menuitem"], a'));
          if (e.key === 'Escape') {
            e.preventDefault();
            e.stopPropagation();
            closeDropdown(true);
            return;
          }

          if (e.key === 'Tab') {
            if (dd.classList.contains('open')) {
              closeDropdown(false);
              dd.classList.remove('closed');
            }
            return;
          }

          if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (!dd.classList.contains('open')) {
              openDropdown();
              if (items.length) items[0].focus();
            } else {
              var activeIdx = items.indexOf(document.activeElement);
              var nextIdx = (activeIdx + 1 < items.length) ? activeIdx + 1 : 0;
              if (items[nextIdx]) items[nextIdx].focus();
            }
          } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (!dd.classList.contains('open')) {
              openDropdown();
              if (items.length) items[items.length - 1].focus();
            } else {
              var activeIdx = items.indexOf(document.activeElement);
              if (activeIdx <= 0) {
                if (typeof toggle.focus === 'function') toggle.focus();
              } else {
                items[activeIdx - 1].focus();
              }
            }
          } else if (e.key === 'Home' && dd.classList.contains('open')) {
            e.preventDefault();
            if (items.length) items[0].focus();
          } else if (e.key === 'End' && dd.classList.contains('open')) {
            e.preventDefault();
            if (items.length) items[items.length - 1].focus();
          }
        });
      });

      // Close on click outside
      document.addEventListener('click', function (e) {
        if (!e.target.closest('.dropdown, .tb-user-dropdown')) {
          document.querySelectorAll('.dropdown.open, .tb-user-dropdown.open, .dropdown.closed, .tb-user-dropdown.closed').forEach(function (dd) {
            dd.classList.remove('open', 'active', 'closed');
            var btn = dd.querySelector('#landingProfileDropdownBtn, #profileDropdownBtn, .tb-user, [data-dropdown-toggle], button') || dd;
            if (btn && btn.setAttribute) btn.setAttribute('aria-expanded', 'false');
          });
        }
      });
      if (reduceMotion || !('IntersectionObserver' in window)) {
        document.querySelectorAll('.reveal').forEach(function (el) { el.classList.add('in-view'); });
        return;
      }
      var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            entry.target.classList.add('in-view');
            observer.unobserve(entry.target);
          }
        });
      }, { threshold: 0.12, rootMargin: '0px 0px -24px 0px' });
      document.querySelectorAll('.reveal').forEach(function (el) { observer.observe(el); });

      var navPills = document.querySelectorAll('.quick-pill');
      if (navPills.length > 0 && 'IntersectionObserver' in window) {
        var secObserver = new IntersectionObserver(function (entries) {
          entries.forEach(function (entry) {
            if (entry.isIntersecting) {
              var id = entry.target.getAttribute('id');
              if (id) {
                navPills.forEach(function (pill) {
                  var href = pill.getAttribute('href');
                  if (href === '#' + id) {
                    pill.classList.add('active');
                  } else {
                    pill.classList.remove('active');
                  }
                });
              }
            }
          });
        }, { rootMargin: '-15% 0px -70% 0px' });
        document.querySelectorAll('section[id]').forEach(function (sec) {
          secObserver.observe(sec);
        });
      }
    })();
  </script>
</body>
</html>
