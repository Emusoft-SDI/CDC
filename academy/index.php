<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/academy.php';
require_once __DIR__ . '/../lib/monnify.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$pdo = db();
academy_ensure_schema($pdo);
$user = current_user($pdo);
$courses = academy_courses($pdo, null, true);
$programs = $pdo->query("SELECT * FROM academy_programs WHERE status = 'active' ORDER BY sort_order ASC, title ASC")->fetchAll();
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
$catalogCourses = array_values(array_filter($courses, static function (array $course) use ($catalogCategory): bool {
    return $catalogCategory === '' || strcasecmp((string) ($course['category'] ?? ''), $catalogCategory) === 0;
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
$logo = app_primary_logo_url();
$hero = '../assets/academy/natcodev-academy-public-hero.png';
$pathways = [
    ['icon' => 'fa-seedling', 'title' => 'Grower Foundation', 'text' => 'Farm records, profile readiness, field evidence, wallet basics, and certificate preparation.'],
    ['icon' => 'fa-store', 'title' => 'Market Readiness', 'text' => 'Product listing, fulfillment discipline, customer support, refunds, and seller trust standards.'],
    ['icon' => 'fa-handshake-angle', 'title' => 'Provider Accreditation', 'text' => 'Service capacity, compliance evidence, delivery quality, and provider onboarding.'],
    ['icon' => 'fa-map-location-dot', 'title' => 'Field & Advisory', 'text' => 'Verification visits, GPS evidence, safety routines, advisory reporting, and escalation.'],
];
$courseImages = [
    'grower' => '../assets/academy/course-grower-foundation.svg',
    'marketplace' => '../assets/academy/course-market-readiness.svg',
    'provider' => '../assets/academy/course-provider-accreditation.svg',
    'field' => '../assets/academy/course-field-advisory.svg',
    'governance' => '../assets/academy/course-governance.svg',
    'certificate' => '../assets/academy/course-certificate.svg',
];

function public_academy_money(float $amount): string
{
    return 'NGN ' . number_format($amount, 2);
}

function public_academy_course_url(array $course, ?array $user): string
{
    $id = (int) $course['id'];
    if ($user) {
        return 'dashboard.php?screen=course&course_id=' . $id;
    }
    return 'register.php?course_id=' . $id;
}

function public_academy_course_image(array $course, array $images): string
{
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
  <title>NATCODEV Academy - Public Learning Entry</title>
  <meta name="description" content="NATCODEV Academy is the public entry point for coconut value-chain learning, learner registration, certificates, and approved role pathways.">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    :root{--forest:#0b4f2a;--deep:#062c18;--leaf:#13834b;--gold:#c99b22;--sky:#e8f2ff;--clay:#f7efe2;--ink:#102017;--muted:#667085;--line:#dde7df;--bg:#f7faf5;--panel:#fff;--shadow:0 18px 42px rgba(16,24,40,.08)}
    *{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font-family:"Segoe UI",Arial,sans-serif}a{text-decoration:none;color:inherit}.top{position:sticky;top:0;z-index:40;background:rgba(255,255,255,.97);border-bottom:1px solid rgba(16,24,40,.08);box-shadow:0 10px 28px rgba(16,24,40,.05)}.bar{max-width:1320px;margin:auto;padding:13px 22px;display:flex;align-items:center;justify-content:space-between;gap:18px}.brand{display:flex;align-items:center;gap:12px;color:var(--forest)}.brand img{width:52px;height:52px;border-radius:50%;object-fit:contain;background:#fff;border:1px solid var(--line);padding:3px}.brand strong{font-size:1.28rem;display:block}.brand span span{display:block;color:var(--muted);font-size:.78rem;font-weight:800}.nav{display:flex;align-items:center;gap:8px;flex-wrap:wrap}.nav a{padding:9px 11px;border-radius:7px;color:#344054;font-weight:850}.nav a:hover{background:#edf7ef;color:var(--forest)}.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;border-radius:8px;padding:11px 15px;background:var(--forest);color:#fff;font-weight:950;border:1px solid var(--forest);cursor:pointer}.btn.secondary{background:#fff;color:var(--forest);border-color:var(--line)}.btn.gold{background:var(--gold);border-color:var(--gold);color:#1f1600}
    .hero{background:linear-gradient(110deg,rgba(6,44,24,.94),rgba(11,79,42,.72) 50%,rgba(11,79,42,.08)),url("<?= e($hero) ?>") center/cover no-repeat;color:#fff}.hero-inner{width:min(1320px,100%);margin:0 auto;padding:74px 22px 42px;min-height:630px;display:flex;align-items:flex-end}.hero-copy{max-width:860px}.eyebrow{color:#f5d77d;font-weight:950;letter-spacing:.12em;text-transform:uppercase;font-size:.8rem}.hero h1{font-size:clamp(2.65rem,6vw,5.7rem);line-height:.96;margin:12px 0 16px;letter-spacing:0;max-width:830px}.hero p{font-size:1.12rem;line-height:1.62;color:#eef8ef;max-width:780px;font-weight:650}.hero-actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:24px}.wrap{max-width:1320px;margin:auto;padding:28px 22px 54px}.section-head{display:flex;justify-content:space-between;gap:18px;align-items:end;margin:28px 0 14px}.section-head h2{font-size:1.75rem;margin:0;color:#10351e}.section-head p{margin:6px 0 0;color:var(--muted);line-height:1.45}.grid{display:grid;gap:16px}.g2{grid-template-columns:repeat(2,minmax(0,1fr))}.g3{grid-template-columns:repeat(3,minmax(0,1fr))}.g4{grid-template-columns:repeat(4,minmax(0,1fr))}.panel,.course,.path,.program,.track{background:#fff;border:1px solid rgba(16,24,40,.08);border-radius:8px;box-shadow:var(--shadow)}.panel{padding:20px}.intro-band{margin-top:-54px;position:relative;z-index:5}.split{display:grid;grid-template-columns:minmax(0,1fr) 390px;gap:18px;align-items:stretch}.curriculum{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.lesson-block{border:1px solid var(--line);border-radius:8px;padding:14px;background:#fbfdf9}.lesson-block b{display:inline-grid;place-items:center;width:34px;height:34px;border-radius:8px;background:var(--forest);color:#fff;margin-bottom:9px}.lesson-block strong{display:block}.meta{color:var(--muted);font-size:.92rem;line-height:1.48}.selected{border-left:5px solid var(--gold)}.badge{display:inline-flex;width:max-content;max-width:100%;gap:6px;align-items:center;border-radius:999px;padding:5px 9px;background:#eef8ef;color:var(--forest);font-size:.78rem;font-weight:900}.badge.gold{background:#fff5d6;color:#855f00}.badge.blue{background:var(--sky);color:#184c8f}.program{padding:16px;background:linear-gradient(180deg,#fff,#fbfdf9)}.program h3,.path h3,.track h3,.course h3{margin:0;color:#122d19;line-height:1.25}.program .num{width:42px;height:42px;border-radius:8px;background:var(--clay);color:#6f4a00;display:grid;place-items:center;font-weight:950;margin-bottom:12px}.course{overflow:hidden;display:flex;flex-direction:column}.thumb{height:180px;background:#eef8ef;position:relative;overflow:hidden}.thumb img{width:100%;height:100%;object-fit:cover;display:block}.thumb span{position:absolute;left:12px;bottom:12px;background:rgba(255,255,255,.92);border:1px solid rgba(255,255,255,.7);border-radius:999px;padding:6px 10px;font-weight:950;color:#16421f;font-size:.78rem}.course-body{padding:16px;display:grid;gap:10px;flex:1}.course-foot{display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap}.price{font-weight:950;color:var(--forest);font-size:1.04rem}.path{padding:18px}.path i,.track i{width:44px;height:44px;border-radius:8px;display:grid;place-items:center;background:#eef8ef;color:var(--forest);font-size:1.08rem;margin-bottom:10px}.track{padding:16px}.catalog-tools{display:flex;gap:10px;align-items:center;flex-wrap:wrap}.catalog-tools select{border:1px solid var(--line);background:#fff;border-radius:8px;padding:11px 12px;font-weight:850;color:#24402f}.category-head{display:flex;justify-content:space-between;align-items:center;gap:12px;margin:22px 0 12px}.category-head h3{margin:0;color:#10351e}.footer{background:#082f19;color:#fff;margin-top:28px}.footer-inner{max-width:1320px;margin:auto;padding:26px 22px;display:flex;justify-content:space-between;gap:20px;flex-wrap:wrap}.footer a{color:#f6df85;font-weight:900}.pagination{display:flex;gap:8px;flex-wrap:wrap;margin-top:18px}.pagination a{border:1px solid var(--line);background:#fff;color:var(--forest);border-radius:8px;padding:8px 12px;font-weight:950}.pagination a.active{background:var(--forest);border-color:var(--forest);color:#fff}
    @media(max-width:1050px){.split,.g3,.g4{grid-template-columns:1fr 1fr}.curriculum{grid-template-columns:1fr}.intro-band{margin-top:0}}@media(max-width:740px){.bar,.nav,.section-head,.footer-inner{align-items:flex-start;flex-direction:column}.hero-inner{min-height:620px;padding:48px 18px 28px}.wrap{padding:18px}.g2,.g3,.g4,.split{grid-template-columns:1fr}.hero h1{font-size:2.7rem}.nav{width:100%}.nav a,.btn{width:100%}}
  </style>
</head>
<body>
  <header class="top">
    <div class="bar">
      <a class="brand" href="../index.php"><img src="<?= e($logo) ?>" alt="NATCODEV"><span><strong>NATCODEV Academy</strong><span>Public learning entry point</span></span></a>
      <nav class="nav" aria-label="Academy navigation">
        <a href="#programs">Programs</a>
        <a href="#catalog">Catalog</a>
        <a href="#certificates">Certificates</a>
        <a href="../verify-certificate.php">Verify Certificate</a>
        <?php if ($user): ?><a href="dashboard.php">Learner Dashboard</a><a class="btn" href="request-role.php">Request Role</a><?php else: ?><a href="../login.php?next=academy/dashboard.php">Login</a><a class="btn" href="register.php">Register as Learner</a><?php endif; ?>
      </nav>
    </div>
  </header>

  <section class="hero">
    <div class="hero-inner">
      <div class="hero-copy">
        <div class="eyebrow">Coconut value-chain learning campus</div>
        <h1>Learn the work before you enter the work.</h1>
        <p>NATCODEV Academy is the education entry point for growers, learners, field teams, sellers, providers, and coordinators. Explore structured programs, enroll in practical courses, complete assessments, and earn certificates that prepare you for approved platform roles.</p>
        <div class="hero-actions">
          <a class="btn gold" href="<?= $user ? 'dashboard.php?screen=catalog' : 'register.php' ?>"><i class="fas fa-user-graduate"></i> <?= $user ? 'Open My Learning' : 'Start Learning' ?></a>
          <a class="btn secondary" href="#catalog"><i class="fas fa-book-open"></i> Browse Courses</a>
          <a class="btn secondary" href="#programs"><i class="fas fa-layer-group"></i> View Programs</a>
        </div>
      </div>
    </div>
  </section>

  <main class="wrap">
    <section class="split intro-band">
      <article class="panel">
        <div class="section-head" style="margin-top:0"><div><h2>Your Learning Route</h2><p>Clear progression from public discovery to verified skills.</p></div></div>
        <div class="curriculum">
          <div class="lesson-block"><b>1</b><strong>Discover</strong><span class="meta">Compare courses, programs, lessons, assessments, and certificate tracks before creating an account.</span></div>
          <div class="lesson-block"><b>2</b><strong>Enroll & Study</strong><span class="meta">Register as a learner, unlock free or paid courses, complete lessons, and submit assessments.</span></div>
          <div class="lesson-block"><b>3</b><strong>Certify & Apply</strong><span class="meta">Use completed training as readiness for certificates and role requests reviewed by admins.</span></div>
        </div>
      </article>
      <aside class="panel selected">
        <span class="badge blue"><i class="fas fa-book"></i> Featured learning</span>
        <h2><?= $featuredCourse ? e((string) $featuredCourse['title']) : 'Academy course catalog' ?></h2>
        <p class="meta"><?= $featuredCourse ? e((string) mb_substr((string) ($featuredCourse['description'] ?? 'Start with a practical Academy course.'), 0, 210)) : 'Active courses will appear here once the Academy catalog is published.' ?></p>
        <?php if ($featuredCourse): ?>
          <p><strong><?= (int) $featuredCourse['is_free'] === 1 ? 'Free course' : e(public_academy_money((float) $featuredCourse['price'])) ?></strong> / <?= (int) ($featuredCourse['lessons'] ?? 0) ?> lessons / <?= e(academy_delivery_label((string) ($featuredCourse['delivery_type'] ?? 'lms'))) ?></p>
          <a class="btn" href="<?= e(public_academy_course_url($featuredCourse, $user)) ?>">Open Featured Course</a>
        <?php else: ?>
          <a class="btn" href="<?= $user ? 'dashboard.php?screen=catalog' : 'register.php' ?>">Enter Academy</a>
        <?php endif; ?>
      </aside>
    </section>

    <section id="programs">
      <div class="section-head"><div><h2>Academic Programs</h2><p>Each program groups courses around a real NATCODEV operating path.</p></div><a class="btn secondary" href="<?= $user ? 'dashboard.php?screen=catalog' : 'register.php' ?>">Join as Learner</a></div>
      <div class="grid g3">
        <?php foreach (array_slice($programs, 0, 6) as $index => $program): ?>
          <article class="program">
            <div class="num"><?= $index + 1 ?></div>
            <h3><?= e((string) $program['title']) ?></h3>
            <p class="meta"><?= e((string) ($program['description'] ?? 'Structured NATCODEV Academy program.')) ?></p>
            <span class="badge"><?= e(academy_role_labels((string) ($program['audience_roles'] ?? 'all'))) ?></span>
          </article>
        <?php endforeach; ?>
        <?php if (!$programs): ?><div class="panel">No active Academy program is available yet.</div><?php endif; ?>
      </div>
    </section>

    <section id="catalog">
      <div class="section-head">
        <div><h2>Public Course Catalog</h2><p>Browse before login. Showing <?= number_format($catalogTotal) ?> active courses<?= $catalogCategory !== '' ? ' in ' . e($catalogCategory) : '' ?>, page <?= $catalogPage ?> of <?= $catalogPages ?>.</p></div>
        <form class="catalog-tools" method="get" action="index.php#catalog">
          <select name="category" onchange="this.form.submit()">
            <option value="">All Categories</option>
            <?php foreach (array_keys($catalogCategories) as $categoryOption): ?><option value="<?= e($categoryOption) ?>" <?= $catalogCategory === $categoryOption ? 'selected' : '' ?>><?= e($categoryOption) ?></option><?php endforeach; ?>
          </select>
          <a class="btn secondary" href="<?= $user ? 'dashboard.php?screen=catalog' : 'register.php' ?>">Enroll</a>
        </form>
      </div>
      <?php foreach ($pagedCourseGroups as $categoryLabel => $categoryCourses): ?>
        <div class="category-head"><h3><?= e($categoryLabel) ?></h3><span class="badge"><?= count($categoryCourses) ?> shown</span></div>
        <div class="grid g3">
          <?php foreach ($categoryCourses as $course): ?>
            <article class="course">
              <div class="thumb"><img src="<?= e(public_academy_course_image($course, $courseImages)) ?>" alt="<?= e((string) $course['title']) ?> course image"><span><?= e((string) ($course['course_code'] ?? 'Course')) ?></span></div>
              <div class="course-body">
                <div style="display:flex;gap:8px;flex-wrap:wrap"><span class="badge"><?= e((string) ($course['category'] ?? $categoryLabel)) ?></span><?php if (!empty($course['program_title'])): ?><span class="badge blue"><?= e((string) $course['program_title']) ?></span><?php endif; ?></div>
                <h3><?= e((string) $course['title']) ?></h3>
                <p class="meta"><?= e((string) mb_substr((string) ($course['description'] ?? 'Practical NATCODEV Academy course.'), 0, 150)) ?></p>
                <div class="meta"><?= e(academy_role_labels((string) ($course['target_roles'] ?? 'all'))) ?> / <?= (int) ($course['lessons'] ?? 0) ?> lessons / <?= (int) ($course['assessments'] ?? 0) ?> assessment</div>
                <div class="course-foot">
                  <div class="price"><?= (int) $course['is_free'] === 1 ? 'Free' : e(public_academy_money((float) $course['price'])) ?></div>
                  <a class="btn" href="<?= e(public_academy_course_url($course, $user)) ?>">View Syllabus</a>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
        <?php if (!$pagedCourses): ?><div class="panel">No public Academy course is active in this category yet.</div><?php endif; ?>
      <?php if ($catalogPages > 1): ?>
        <nav class="pagination" aria-label="Course catalog pages">
          <?php $catalogBase = []; if ($catalogCategory !== '') { $catalogBase['category'] = $catalogCategory; } ?>
          <?php if ($catalogPage > 1): ?><a href="index.php?<?= e(http_build_query($catalogBase + ['page' => $catalogPage - 1])) ?>#catalog">Previous</a><?php endif; ?>
          <?php for ($page = 1; $page <= $catalogPages; $page++): ?><a class="<?= $page === $catalogPage ? 'active' : '' ?>" href="index.php?<?= e(http_build_query($catalogBase + ['page' => $page])) ?>#catalog"><?= $page ?></a><?php endfor; ?>
          <?php if ($catalogPage < $catalogPages): ?><a href="index.php?<?= e(http_build_query($catalogBase + ['page' => $catalogPage + 1])) ?>#catalog">Next</a><?php endif; ?>
        </nav>
      <?php endif; ?>
    </section>

    <section id="pathways">
      <div class="section-head"><div><h2>Learning Pathways</h2><p>Choose a learning direction before requesting any operating role.</p></div></div>
      <div class="grid g4">
        <?php foreach ($pathways as $pathway): ?><article class="path"><i class="fas <?= e($pathway['icon']) ?>"></i><h3><?= e($pathway['title']) ?></h3><p class="meta"><?= e($pathway['text']) ?></p></article><?php endforeach; ?>
      </div>
    </section>

    <section id="certificates">
      <div class="section-head"><div><h2>Certificate Tracks</h2><p>Complete required courses, pass assessments, then request verifiable Academy credentials.</p></div><a class="btn secondary" href="../verify-certificate.php">Verify Certificate</a></div>
      <div class="grid g3">
        <?php foreach (array_slice($certTracks, 0, 6) as $track): ?>
          <article class="track">
            <i class="fas fa-certificate"></i>
            <h3><?= e((string) $track['title']) ?></h3>
            <p class="meta"><?= e((string) ($track['description'] ?? 'Complete the required Academy courses to become eligible.')) ?></p>
            <span class="badge gold"><?= (int) ($track['course_count'] ?? 0) ?> linked course(s)</span>
          </article>
        <?php endforeach; ?>
        <?php if (!$certTracks): ?><div class="panel">No active certificate track is available yet.</div><?php endif; ?>
      </div>
    </section>

    <?php if ($selectedCourse): ?>
      <section class="panel selected">
        <span class="badge gold">Selected course</span>
        <h2><?= e((string) $selectedCourse['title']) ?></h2>
        <p class="meta"><?= e((string) ($selectedCourse['description'] ?? '')) ?></p>
        <p><strong><?= (int) $selectedCourse['is_free'] === 1 ? 'Free' : e(public_academy_money((float) $selectedCourse['price'])) ?></strong> / <?= e(academy_delivery_label((string) ($selectedCourse['delivery_type'] ?? 'lms'))) ?></p>
        <a class="btn" href="<?= e(public_academy_course_url($selectedCourse, $user)) ?>">Continue to Enrollment</a>
      </section>
    <?php endif; ?>
  </main>

  <footer class="footer">
    <div class="footer-inner">
      <div><strong>NATCODEV Academy</strong><br><span>Learn first. Request operating access when ready.</span></div>
      <div><a href="dashboard.php">Learner Dashboard</a> / <a href="../verify-certificate.php">Verify Certificate</a> / <a href="<?= $user ? 'dashboard.php?screen=support' : '../support/index.php?category=academy' ?>">Support</a></div>
    </div>
  </footer>
</body>
</html>
