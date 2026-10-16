<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/academy.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$pdo = db();

// Gate by module state.
if (!admin_feature_is_allowed($pdo, 'training')) {
    http_response_code(403);
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>NATCODEV Academy</title></head>';
    echo '<body style="font-family:system-ui;padding:60px;text-align:center;color:#555">';
    echo '<h1>Academy Currently Unavailable</h1><p>This module is temporarily disabled.</p></body></html>';
    exit;
}

academy_ensure_schema($pdo);
$user = current_user($pdo);

$programId = (int) ($_GET['program_id'] ?? $_GET['id'] ?? 0);
$program = null;
if ($programId > 0) {
    $programStmt = $pdo->prepare("SELECT * FROM academy_programs WHERE id = ? AND status = 'active' LIMIT 1");
    $programStmt->execute([$programId]);
    $program = $programStmt->fetch() ?: null;
}
$programs = $pdo->query("SELECT * FROM academy_programs WHERE status = 'active' ORDER BY sort_order ASC, title ASC")->fetchAll();
$courses = academy_courses($pdo, null, true);
$programCourses = array_values(array_filter($courses, static function (array $course) use ($programId): bool {
    return $programId > 0 && (int) ($course['program_id'] ?? 0) === $programId;
}));
$logo = app_primary_logo_url();
$applyUrl = $user
    ? 'dashboard.php?screen=catalog&program=' . $programId
    : 'register.php?program_id=' . $programId;

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $program ? e((string) $program['title']) . ' — ' : '' ?>Programs — NATCODEV Academy</title>
  <meta name="description" content="Structured NATCODEV Academy programs: details, prerequisites, and the courses inside each operating path.">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    :root{
      --forest-950:#04170e;--forest-900:#062c18;--forest-800:#0a3a21;--forest-700:#0d5c33;--forest-600:#128048;--forest-500:#1a9d5c;
      --mint:#7fc8a9;--mint-soft:#dcefe4;
      --gold:#c99b22;--gold-deep:#a97e14;--gold-soft:#f6ead0;
      --cream:#f4f2e8;--paper:#fbfaf3;--paper-warm:#fffdf6;
      --ink:#10231a;--ink-soft:#33463b;--muted:#56675d;
      --line:#e0e6da;--line-soft:#ecefe4;
      --sky:#e8f2ff;--radius:14px;
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

    .tb-user-dropdown,.dropdown{position:relative;display:inline-flex;align-items:center}
    button.tb-user,.tb-user{position:relative;height:42px;min-width:0;max-width:200px;padding:4px 12px 4px 5px;border-radius:999px;border:1px solid var(--line);background:#fff;color:var(--forest-950);cursor:pointer;display:inline-flex;align-items:center;gap:8px;font-family:inherit;font-size:13px;text-align:left;outline:none;box-shadow:0 1px 3px rgba(0,0,0,.06);transition:all .18s ease;user-select:none;-webkit-user-select:none}
    button.tb-user:hover,.tb-user:hover{background:#f6faf6;border-color:var(--forest-600)}
    .tb-user .av{width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--forest-800),var(--forest-600));color:#fff;display:flex;align-items:center;justify-content:center;font-weight:900;font-size:13px;flex-shrink:0}
    .tb-user-details{display:flex;flex-direction:column;min-width:0;text-align:left;line-height:1.2}
    .tb-user-details .nm{max-width:96px;font-size:12px;font-weight:800;color:var(--forest-950);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .tb-user-details .st{font-size:10px;font-weight:700;color:var(--forest-600);line-height:1.1;white-space:nowrap;text-transform:capitalize}
    .tb-user-caret{font-size:10px;color:var(--muted);margin-left:2px;margin-right:2px;transition:transform .22s cubic-bezier(.16,1,.3,1),color .15s ease;flex-shrink:0}
    .dropdown.open .tb-user-caret,.dropdown.active .tb-user-caret{transform:rotate(180deg);color:var(--forest-700)}
    .dropdown-menu{display:none;position:absolute;right:0;top:calc(100% + 8px);width:260px;max-width:calc(100vw - 20px);background:#fff;border:1px solid var(--line);border-radius:12px;box-shadow:0 18px 42px rgba(4,23,14,.18),0 4px 12px rgba(0,0,0,.06);padding:8px;z-index:200;box-sizing:border-box}
    .dropdown.open>.dropdown-menu,.dropdown.active>.dropdown-menu{display:flex;flex-direction:column;gap:2px}
    .dropdown.closed>.dropdown-menu{display:none}
    .dropdown-header{padding:10px 12px;border-radius:8px;background:linear-gradient(135deg,#f0fdf4,#e8f5e9);border:1px solid rgba(18,128,72,.15);margin-bottom:4px}
    .dropdown-header .dh-name{font-weight:850;font-size:13px;color:var(--forest-950);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;line-height:1.3}
    .dropdown-header .dh-email{font-size:11px;color:#475569;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:2px;font-weight:600}
    .badge-pill{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:20px;font-size:10px;font-weight:800}
    .bp-green{background:#D1FAE5;color:#065F46}
    .bp-blue{background:#DBEAFE;color:#1E40AF}
    .dropdown-divider{height:1px;background:var(--line);margin:4px 0}
    .dropdown-menu a,.dropdown-item{display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:8px;color:var(--forest-950);font-size:13px;font-weight:700;text-decoration:none;transition:background-color .15s ease,color .15s ease;line-height:1.3}
    .dropdown-menu a i,.dropdown-item i{width:18px;text-align:center;font-size:13px;color:var(--muted);flex-shrink:0}
    .dropdown-menu a:hover,.dropdown-item:hover{background:var(--mint-soft);color:var(--forest-700)}
    .dropdown-item-danger{color:#dc2626 !important}
    .dropdown-item-danger i{color:#dc2626 !important}
    .dropdown-item-danger:hover{background:#fee2e2 !important;color:#991b1b !important}

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

    .page-hero{position:relative;background:var(--forest-950);color:#f2f7f0;overflow:hidden}
    .page-hero::before{content:"";position:absolute;inset:0;background:linear-gradient(105deg,rgba(4,23,14,.96) 8%,rgba(6,44,24,.86) 42%,rgba(10,58,33,.55) 68%,rgba(6,44,24,.35));z-index:1}
    .page-hero-inner{position:relative;z-index:2;max-width:1280px;margin:0 auto;padding:72px 22px 84px}
    .eyebrow{display:inline-flex;align-items:center;gap:9px;color:var(--gold);font-weight:800;letter-spacing:.16em;text-transform:uppercase;font-size:.76rem}
    .eyebrow::before{content:"";width:26px;height:2px;background:var(--gold);border-radius:2px}
    .page-hero h1{font-family:var(--font-display);font-size:clamp(2rem,4.4vw,3.4rem);line-height:1.06;letter-spacing:-.015em;margin:16px 0 16px;max-width:20ch;font-weight:700}
    .page-hero .lede{font-size:1.08rem;line-height:1.66;color:#dcebe0;max-width:62ch;font-weight:500;margin:0 0 24px}
    .page-hero .hero-actions{display:flex;gap:13px;flex-wrap:wrap}
    .page-hero .hero-meta{display:flex;gap:22px;flex-wrap:wrap;margin-top:30px;padding-top:24px;border-top:1px solid rgba(255,255,255,.14)}
    .page-hero .hero-meta div{font-size:.88rem;color:#b9d2c1;display:flex;align-items:center;gap:8px}
    .page-hero .hero-meta i{color:var(--gold);font-size:.8rem}

    .section{max-width:1280px;margin:0 auto;padding:72px 22px 0;scroll-margin-top:84px}
    .section-head{display:flex;justify-content:space-between;align-items:flex-end;gap:24px;margin-bottom:34px;flex-wrap:wrap}
    .section-head .kicker{display:inline-flex;align-items:center;gap:9px;color:var(--forest-600);font-weight:800;letter-spacing:.14em;text-transform:uppercase;font-size:.74rem;margin-bottom:12px}
    .section-head .kicker::before{content:"";width:22px;height:2px;background:var(--gold);border-radius:2px}
    .section-head h2{font-family:var(--font-display);font-size:clamp(1.75rem,3.1vw,2.5rem);line-height:1.12;margin:0;color:var(--forest-900);font-weight:700;letter-spacing:-.01em;max-width:22ch}
    .section-head .sub{margin:12px 0 0;color:var(--muted);line-height:1.6;max-width:62ch}
    .panel{background:var(--paper);border:1px solid var(--line);border-radius:var(--radius);box-shadow:var(--shadow-1)}

    .program-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}
    .program{display:flex;flex-direction:column;background:var(--paper);border:1px solid var(--line);border-radius:var(--radius);overflow:hidden;box-shadow:var(--shadow-1);transition:transform .22s ease,box-shadow .22s ease}
    .program:hover{transform:translateY(-4px);box-shadow:var(--shadow-2)}
    .program-media{position:relative;height:158px;background:linear-gradient(120deg,var(--forest-800),var(--forest-600));display:grid;place-items:center;overflow:hidden}
    .program-media img{width:100%;height:100%;object-fit:cover;display:block}
    .program-num{font-family:var(--font-display);font-size:2.6rem;color:rgba(255,255,255,.5);font-weight:700}
    .program-body{padding:22px 22px 24px;display:flex;flex-direction:column;flex:1}
    .program-body h3{font-family:var(--font-display);font-size:1.18rem;margin:0 0 9px;color:var(--forest-900);font-weight:700;line-height:1.3}
    .program-body p{margin:0 0 14px;color:var(--ink-soft);font-size:.92rem;line-height:1.6;flex:1}
    .program-body .program-foot{display:flex;align-items:center;justify-content:space-between;gap:12px;border-top:1px solid var(--line-soft);padding-top:16px}

    .badge{display:inline-flex;align-items:center;gap:6px;max-width:100%;padding:5px 11px;border-radius:999px;font-size:.74rem;font-weight:750;letter-spacing:.02em;background:var(--mint-soft);color:var(--forest-800);white-space:nowrap}
    .badge.gold{background:var(--gold-soft);color:#6d4e05}
    .badge.sky{background:var(--sky);color:#1c4e80}
    .badge.forest{background:var(--forest-700);color:#fff}

    .prereq-panel{padding:30px 32px}
    .prereq-panel h3{font-family:var(--font-display);font-size:1.25rem;margin:0 0 14px;color:var(--forest-900);font-weight:700}
    .prereq-panel p{margin:0;color:var(--ink-soft);line-height:1.68;white-space:pre-line}

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

    .empty-state{padding:44px 28px;text-align:center;color:var(--muted)}
    .empty-state i{display:block;font-size:1.7rem;color:var(--mint);margin-bottom:14px}

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
      .program,.course,.btn{transition:none}
      .course:hover .course-thumb img{transform:none}
    }

    @media (max-width:1080px){
      .program-grid,.course-grid{grid-template-columns:repeat(2,1fr)}
    }
    @media (max-width:720px){
      .topbar-inner{flex-wrap:wrap;gap:10px;padding:11px 16px}
      .mainnav{order:3;width:100%;margin-left:0}
      .auth{margin-left:auto}
      .tb-user{max-width:none;padding:3px 8px 3px 3px}
      .tb-user-details{display:none}
      .dropdown-menu{width:244px;right:-4px}
      .section{padding:56px 16px 0}
      .page-hero-inner{padding:52px 16px 64px}
      .page-hero h1{max-width:none}
      .hero-actions .btn{width:100%}
      .program-grid,.course-grid{grid-template-columns:1fr}
      .cta-inner{padding:56px 16px}
      .section-head{margin-bottom:26px}
    }
    @media (max-width:480px){
      .auth a.login{padding:8px 10px;font-size:.85rem}
      .auth a.login .login-label{display:none}
      .dropdown-menu{width:232px;right:-8px}
    }
  </style>
</head>
<body>
  <a class="skip-link" href="#main">Skip to content</a>

  <header class="topbar" id="topbar">
    <div class="topbar-inner">
      <a class="brand" href="../index.php" aria-label="NATCODEV home">
        <img src="<?= e($logo) ?>" alt="NATCODEV logo">
        <span class="brand-text"><strong>NATCODEV Academy</strong><span>Learning campus</span></span>
      </a>
      <nav class="mainnav" aria-label="Academy navigation">
        <a href="index.php#pathways">Pathways</a>
        <a href="program.php">Programs</a>
        <a href="index.php#catalog">Catalog</a>
        <a href="index.php#certificates">Certificates</a>
        <a href="index.php#route">How it works</a>
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
          <div class="tb-user-dropdown dropdown" id="programProfileDropdown">
            <button class="tb-user" id="programProfileDropdownBtn" type="button" aria-haspopup="true" aria-expanded="false" aria-label="Learner profile menu">
              <span class="av"><?= e($initials) ?></span>
              <span class="tb-user-details">
                <span class="nm"><?= e((string) ($user['name'] ?? 'User')) ?></span>
                <span class="st"><?= e(academy_role_label($role)) ?></span>
              </span>
              <i class="fas fa-chevron-down tb-user-caret" aria-hidden="true"></i>
            </button>
            <div class="dropdown-menu" id="programProfileDropdownMenu" role="menu" aria-labelledby="programProfileDropdownBtn">
              <div class="dropdown-header">
                <div class="dh-name"><?= e((string) ($user['name'] ?? 'User')) ?></div>
                <div class="dh-email"><?= e((string) ($user['email'] ?? '')) ?></div>
                <div class="dh-tags" style="display:flex;gap:6px;margin-top:8px;flex-wrap:wrap;align-items:center">
                  <span class="badge-pill bp-green"><?= e(academy_role_label($role)) ?></span>
                  <span class="badge-pill bp-blue"><i class="fas fa-wallet"></i> <?= e(public_academy_money($walletBalance)) ?></span>
                </div>
              </div>
              <div class="dropdown-divider"></div>
              <a href="dashboard.php?screen=catalog" class="dropdown-item" role="menuitem"><i class="fas fa-compass"></i><span>Course Catalog</span></a>
              <a href="dashboard.php?screen=learning" class="dropdown-item" role="menuitem"><i class="fas fa-graduation-cap"></i><span>My Learning</span></a>
              <a href="dashboard.php?screen=certificates" class="dropdown-item" role="menuitem"><i class="fas fa-award"></i><span>Certificates</span></a>
              <a href="dashboard.php?screen=transactions" class="dropdown-item" role="menuitem"><i class="fas fa-wallet"></i><span>Wallet &amp; Transactions</span></a>
              <a href="dashboard.php?screen=support" class="dropdown-item" role="menuitem"><i class="fas fa-circle-question"></i><span>Help &amp; Support</span></a>
              <div class="dropdown-divider"></div>
              <a href="../index.php" class="dropdown-item" role="menuitem"><i class="fas fa-house"></i><span>NATCODEV Home</span></a>
              <div class="dropdown-divider"></div>
              <a href="logout.php?_csrf=<?= e(csrf_token()) ?>" class="dropdown-item dropdown-item-danger" role="menuitem"><i class="fas fa-right-from-bracket"></i><span>Log Out</span></a>
            </div>
          </div>
        <?php else: ?>
          <a class="login" href="../login.php?next=academy/dashboard.php">Login</a>
          <a class="btn btn-primary btn-sm" href="register.php">Register as Learner</a>
        <?php endif; ?>
      </div>
    </div>
  </header>

  <main id="main">
    <?php if ($program): ?>
    <section class="page-hero" aria-label="Program overview">
      <div class="page-hero-inner">
        <span class="eyebrow">Academy Program</span>
        <h1><?= e((string) $program['title']) ?></h1>
        <p class="lede"><?= e((string) ($program['description'] ?? '')) ?></p>
        <div class="hero-actions">
          <a class="btn btn-gold" href="<?= e($applyUrl) ?>"><i class="fas fa-user-graduate"></i> Apply Now</a>
          <a class="btn btn-ghost" style="background:rgba(255,255,255,.10);color:#fff;border-color:rgba(255,255,255,.35)" href="program.php"><i class="fas fa-grid-2"></i> All Programs</a>
        </div>
        <div class="hero-meta">
          <div><i class="fas fa-users"></i> <?= e(academy_role_labels((string) ($program['audience_roles'] ?? 'all'))) ?></div>
          <div><i class="fas fa-layer-group"></i> <?= count($programCourses) ?> course<?= count($programCourses) === 1 ? '' : 's' ?></div>
          <?php if (trim((string) ($program['prerequisites'] ?? '')) !== ''): ?>
            <div><i class="fas fa-circle-check"></i> Prerequisites listed</div>
          <?php endif; ?>
        </div>
      </div>
    </section>

    <?php if (trim((string) ($program['prerequisites'] ?? '')) !== ''): ?>
    <section class="section" aria-label="Program prerequisites">
      <div class="section-head reveal">
        <div>
          <span class="kicker">Before you apply</span>
          <h2>Program prerequisites</h2>
        </div>
      </div>
      <div class="panel prereq-panel reveal">
        <h3><i class="fas fa-clipboard-list" style="color:var(--gold);margin-right:8px"></i>What you need to start</h3>
        <p><?= e((string) $program['prerequisites']) ?></p>
      </div>
    </section>
    <?php endif; ?>

    <section class="section" id="program-courses" aria-label="Courses in this program">
      <div class="section-head reveal">
        <div>
          <span class="kicker">Inside this program</span>
          <h2>Program courses</h2>
          <p class="sub">Every course below belongs to <?= e((string) $program['title']) ?>. Enroll in any course to start building the evidence for this operating path.</p>
        </div>
        <a class="btn btn-ghost" href="<?= e($applyUrl) ?>"><i class="fas fa-user-plus"></i> Apply Now</a>
      </div>
      <div class="course-grid">
        <?php foreach ($programCourses as $courseIndex => $course): ?>
          <?php $courseThumb = function_exists('academy_course_artwork_url') ? academy_course_artwork_url($course) : (function_exists('academy_course_thumbnail_src') ? academy_course_thumbnail_src($course) : academy_program_thumbnail_src($course)); ?>
          <article class="course reveal" style="--d:<?= ($courseIndex % 3) * 60 ?>ms">
            <a href="<?= e(public_academy_course_url($course, $user)) ?>">
              <div class="course-thumb">
                <?php if ($courseThumb !== ''): ?>
                  <img src="<?= e($courseThumb) ?>" alt="<?= e((string) $course['title']) ?> course image" loading="lazy">
                <?php else: ?>
                  <i class="fas fa-graduation-cap" style="position:absolute;inset:0;display:grid;place-items:center;font-size:2.4rem;color:var(--forest-600)"></i>
                <?php endif; ?>
                <span class="code"><?= e((string) ($course['course_code'] ?? 'Course')) ?></span>
              </div>
            </a>
            <div class="course-body">
              <div class="course-tags">
                <span class="badge"><?= e((string) ($course['category'] ?? 'Training')) ?></span>
                <?php if ((int) ($course['is_free'] ?? 0) === 1): ?>
                  <span class="badge forest"><i class="fas fa-tag"></i> Free</span>
                <?php endif; ?>
              </div>
              <h3><?= e((string) $course['title']) ?></h3>
              <p class="desc"><?= e((string) mb_substr((string) ($course['description'] ?? 'Practical NATCODEV Academy course.'), 0, 150)) ?></p>
              <div class="course-meta">
                <span><i class="fas fa-layer-group"></i> <?= (int) ($course['lessons'] ?? 0) ?> lessons</span>
                <span><i class="fas fa-clipboard-check"></i> <?= (int) ($course['assessments'] ?? 0) ?> assessment<?= (int) ($course['assessments'] ?? 0) === 1 ? '' : 's' ?></span>
                <span><i class="fas fa-users"></i> <?= e(academy_role_labels((string) ($course['target_roles'] ?? 'all'))) ?></span>
              </div>
              <div class="course-foot">
                <div class="price<?= (int) $course['is_free'] === 1 ? ' free' : '' ?>"><?= (int) $course['is_free'] === 1 ? 'Free' : e(public_academy_money((float) $course['price'])) ?></div>
                <a class="btn btn-primary btn-sm" href="<?= e(public_academy_course_url($course, $user)) ?>"><?= e(academy_delivery_action((string) ($course['delivery_type'] ?? 'lms'))) ?><i class="fas fa-arrow-right"></i></a>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
      <?php if (!$programCourses): ?>
        <div class="panel empty-state"><i class="fas fa-seedling"></i>No courses are published in this program yet. Check back soon or apply now to be notified.</div>
      <?php endif; ?>
    </section>
    <?php else: ?>
    <section class="page-hero" aria-label="Academy programs">
      <div class="page-hero-inner">
        <span class="eyebrow">Academy Programs</span>
        <h1>Structured programs, real operating paths</h1>
        <p class="lede">Each program groups courses around a real NATCODEV operating path. Review the details and prerequisites, then apply to start learning.</p>
        <div class="hero-actions">
          <a class="btn btn-gold" href="register.php"><i class="fas fa-user-graduate"></i> Register as Learner</a>
          <a class="btn btn-ghost" style="background:rgba(255,255,255,.10);color:#fff;border-color:rgba(255,255,255,.35)" href="index.php#catalog"><i class="fas fa-book-open"></i> Browse the Catalog</a>
        </div>
      </div>
    </section>

    <section class="section" aria-label="All programs">
      <div class="program-grid" style="margin-top:48px">
        <?php foreach ($programs as $programIndex => $programRow): ?>
          <?php $programThumb = academy_program_thumbnail_src($programRow); ?>
          <article class="program reveal" style="--d:<?= ($programIndex % 3) * 70 ?>ms">
            <a href="program.php?program_id=<?= (int) $programRow['id'] ?>">
              <div class="program-media">
                <?php if ($programThumb !== ''): ?>
                  <img src="<?= e($programThumb) ?>" alt="<?= e((string) $programRow['title']) ?> thumbnail" loading="lazy">
                <?php else: ?>
                  <span class="program-num" aria-hidden="true"><?= $programIndex + 1 ?></span>
                <?php endif; ?>
              </div>
              <div class="program-body">
                <h3><?= e((string) $programRow['title']) ?></h3>
                <p><?= e((string) mb_substr((string) ($programRow['description'] ?? ''), 0, 160)) ?></p>
                <span class="badge"><?= e(academy_role_labels((string) ($programRow['audience_roles'] ?? 'all'))) ?></span>
                <div class="program-foot" style="margin-top:16px">
                  <span class="badge gold"><i class="fas fa-arrow-up-right-from-square"></i> View details</span>
                </div>
              </div>
            </a>
          </article>
        <?php endforeach; ?>
      </div>
      <?php if (!$programs): ?>
        <div class="panel empty-state"><i class="fas fa-seedling"></i>No active Academy program is available yet. Check back soon.</div>
      <?php endif; ?>
    </section>
    <?php endif; ?>
  </main>

  <section class="cta-band">
    <div class="cta-inner">
      <div>
        <h2>Ready to start your operating path?</h2>
        <p>Join the NATCODEV Academy, enroll in the program courses, and build verifiable credentials for your role.</p>
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
      var topbar = document.getElementById('topbar');
      if (topbar) {
        var onScroll = function () { topbar.classList.toggle('scrolled', window.scrollY > 8); };
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
      }

      var dd = document.getElementById('programProfileDropdown');
      if (dd) {
        var toggle = document.getElementById('programProfileDropdownBtn');
        var menu = document.getElementById('programProfileDropdownMenu');
        if (toggle && menu) {
          var closeMenu = function () { dd.classList.remove('open', 'active'); dd.classList.add('closed'); toggle.setAttribute('aria-expanded', 'false'); };
          var openMenu = function () { dd.classList.remove('closed'); dd.classList.add('open', 'active'); toggle.setAttribute('aria-expanded', 'true'); };
          toggle.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (dd.classList.contains('open')) { closeMenu(); } else { openMenu(); }
          });
          document.addEventListener('click', function (e) { if (!dd.contains(e.target)) { closeMenu(); } });
          document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { closeMenu(); toggle.focus(); } });
        }
      }

      if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        var revealEls = document.querySelectorAll('.reveal');
        if ('IntersectionObserver' in window && revealEls.length) {
          var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
              if (entry.isIntersecting) { entry.target.classList.add('in-view'); io.unobserve(entry.target); }
            });
          }, { threshold: 0.12, rootMargin: '0px 0px -24px 0px' });
          revealEls.forEach(function (el) { io.observe(el); });
        } else {
          revealEls.forEach(function (el) { el.classList.add('in-view'); });
        }
      } else {
        document.querySelectorAll('.reveal').forEach(function (el) { el.classList.add('in-view'); });
      }
    })();
  </script>
</body>
</html>
