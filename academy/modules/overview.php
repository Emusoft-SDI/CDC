<?php
/**
 * Academy Learner — Overview / Home Dashboard Screen
 *
 * Displays a personalised executive KPI summary, enrolled courses grid with 16:9 masterclass artwork,
 * quick actions, recent activity timeline, upcoming deadlines, and certification credentials.
 *
 * Expects (from dashboard.php): $pdo, $user, $screen, $registered,
 * $wallet, $walletBalance, $logo, $initials, $titleSet, $course,
 * $completedLearning, $activeLearning, $avgProgress, $certificates,
 * $groupCertificates, $courses, $error, $message variables available.
 */

if (!isset($pdo) || !$pdo) {
    exit('Overview module requires valid PDO connection.');
}

$userId = (int) ($user['id'] ?? 0);
if ($userId <= 0) {
    exit('User not authenticated.');
}

// ──────────────────────────────────────────────
// KPI Queries
// ──────────────────────────────────────────────

$coursesEnrolled    = 0;
$inProgress         = 0;
$completed          = 0;
$certificatesEarned = 0;
$quizzesTaken       = 0;
$totalHoursLearned  = 0.0;

try {
    // Courses enrolled
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM webinar_registrations WHERE user_id = ?");
    $stmt->execute([$userId]);
    $coursesEnrolled = (int) $stmt->fetchColumn();

    // In progress (progress < 100 and completion_status != 'completed')
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM webinar_registrations WHERE user_id = ? AND progress_percent < 100 AND completion_status != 'completed'");
    $stmt->execute([$userId]);
    $inProgress = (int) $stmt->fetchColumn();

    // Completed
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM webinar_registrations WHERE user_id = ? AND (progress_percent >= 100 OR completion_status = 'completed')");
    $stmt->execute([$userId]);
    $completed = (int) $stmt->fetchColumn();

    // Certificates earned (issued status)
    $acCertSoft = app_column_exists($pdo, 'academy_certificates', 'deleted_at') ? ' AND c.deleted_at IS NULL' : '';
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM academy_certificates c WHERE c.user_id = ? AND c.status = 'issued'{$acCertSoft}");
    $stmt->execute([$userId]);
    $certificatesEarned += (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM academy_group_certificates g WHERE g.user_id = ? AND g.status = 'issued'");
    $stmt->execute([$userId]);
    $certificatesEarned += (int) $stmt->fetchColumn();

    // Quizzes taken (assessment attempts)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM academy_attempts WHERE user_id = ?");
    $stmt->execute([$userId]);
    $quizzesTaken = (int) $stmt->fetchColumn();

    // Hours learned
    $timeSpentSec = 0;
    if (app_table_exists($pdo, 'lesson_completions')) {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(time_spent_seconds), 0) FROM lesson_completions WHERE enrollment_id IN (SELECT id FROM webinar_registrations WHERE user_id = ?)");
        $stmt->execute([$userId]);
        $timeSpentSec = (int) $stmt->fetchColumn();
    } elseif (app_table_exists($pdo, 'academy_progress') && app_column_exists($pdo, 'academy_progress', 'time_spent_seconds')) {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(time_spent_seconds), 0) FROM academy_progress WHERE user_id = ? AND time_spent_seconds IS NOT NULL");
        $stmt->execute([$userId]);
        $timeSpentSec = (int) $stmt->fetchColumn();
    } else {
        $estStmt = $pdo->prepare("
            SELECT COALESCE(SUM(l.duration_minutes), 0)
            FROM academy_progress ap
            JOIN academy_lessons l ON l.id = ap.lesson_id AND l.webinar_id = ap.webinar_id
            WHERE ap.user_id = ? AND ap.status = 'completed' AND ap.completed_at IS NOT NULL
        ");
        $estStmt->execute([$userId]);
        $timeSpentSec = (int) $estStmt->fetchColumn() * 60;
    }
    $totalHoursLearned = round(($timeSpentSec / 3600), 1);
} catch (Throwable $e) {
    error_log('Academy overview KPI error: ' . $e->getMessage());
}

// ──────────────────────────────────────────────
// Enrolled Courses (Prioritize In-Progress, Max 6)
// ──────────────────────────────────────────────

$enrolledCourses = [];
if (!empty($registered) && is_array($registered)) {
    $enrolledCourses = $registered;
    usort($enrolledCourses, static function(array $a, array $b): int {
        $aProg = (int) ($a['progress_percent'] ?? 0);
        $bProg = (int) ($b['progress_percent'] ?? 0);
        $aDone = ((string) ($a['completion_status'] ?? '') === 'completed' || $aProg >= 100) ? 1 : 0;
        $bDone = ((string) ($b['completion_status'] ?? '') === 'completed' || $bProg >= 100) ? 1 : 0;
        if ($aDone !== $bDone) {
            return $aDone <=> $bDone; // In-progress first
        }
        return (int) ($b['id'] ?? 0) <=> (int) ($a['id'] ?? 0);
    });
    $enrolledCourses = array_slice($enrolledCourses, 0, 6);
}

// Determine active course for Hero CTA
$activeCourse = null;
if (!empty($registered)) {
    foreach ($registered as $rc) {
        if ((string) ($rc['completion_status'] ?? '') !== 'completed' && (int) ($rc['progress_percent'] ?? 0) < 100) {
            $activeCourse = $rc;
            break;
        }
    }
}
$resumeUrl = $activeCourse
    ? 'dashboard.php?screen=lesson&course_id=' . (int) $activeCourse['id']
    : 'dashboard.php?screen=catalog';

// ──────────────────────────────────────────────
// Recent Activity Feed (Top 5)
// ──────────────────────────────────────────────

$recentActivities = [];
try {
    $activityRows = [];

    // Course started
    $startedStmt = $pdo->prepare("
        SELECT r.webinar_id, w.title, r.started_at, 'course_started' AS activity_type
        FROM webinar_registrations r
        JOIN webinars w ON w.id = r.webinar_id
        WHERE r.user_id = ? AND r.started_at IS NOT NULL
        ORDER BY r.started_at DESC
        LIMIT 3
    ");
    $startedStmt->execute([$userId]);
    foreach ($startedStmt->fetchAll() as $row) {
        $cId = (int) ($row['webinar_id'] ?? 0);
        $activityRows[] = [
            'type'    => 'course_started',
            'icon'    => 'fa-play-circle',
            'title'   => 'Started learning',
            'detail'  => (string) ($row['title'] ?? 'Course'),
            'time'    => (string) ($row['started_at'] ?? 'now'),
            'href'    => "dashboard.php?screen=course&course_id=" . $cId,
        ];
    }

    // Lesson completed
    $lessonDoneStmt = $pdo->prepare("
        SELECT ap.webinar_id, w.title, ap.completed_at, ap.lesson_id
        FROM academy_progress ap
        JOIN webinars w ON w.id = ap.webinar_id
        WHERE ap.user_id = ? AND ap.status = 'completed' AND ap.completed_at IS NOT NULL
        ORDER BY ap.completed_at DESC
        LIMIT 3
    ");
    $lessonDoneStmt->execute([$userId]);
    foreach ($lessonDoneStmt->fetchAll() as $row) {
        $cId = (int) ($row['webinar_id'] ?? 0);
        $lId = (int) ($row['lesson_id'] ?? 0);
        $activityRows[] = [
            'type'    => 'lesson_completed',
            'icon'    => 'fa-check-circle',
            'title'   => 'Lesson completed',
            'detail'  => (string) ($row['title'] ?? 'Course lesson'),
            'time'    => (string) ($row['completed_at'] ?? 'now'),
            'href'    => "dashboard.php?screen=lesson&course_id={$cId}&lesson_id={$lId}",
        ];
    }

    // Assessment passed
    $assessPassedStmt = $pdo->prepare("
        SELECT at.webinar_id, w.title, at.score_percent, at.passed, at.completed_at, at.assessment_id
        FROM academy_attempts at
        JOIN webinars w ON w.id = at.webinar_id
        WHERE at.user_id = ? AND at.passed = 1
        ORDER BY at.completed_at DESC
        LIMIT 2
    ");
    $assessPassedStmt->execute([$userId]);
    foreach ($assessPassedStmt->fetchAll() as $row) {
        $activityRows[] = [
            'type'    => 'quiz_passed',
            'icon'    => 'fa-trophy',
            'title'   => 'Assessment passed',
            'detail'  => (string) ($row['title'] ?? 'Assessment') . ' — Score: ' . round((float) ($row['score_percent'] ?? 0)) . '%',
            'time'    => (string) ($row['completed_at'] ?? 'now'),
            'href'    => "dashboard.php?screen=certificates",
        ];
    }

    // Certificate earned
    $certEarnedStmt = $pdo->prepare("
        SELECT c.certificate_ref, w.title, c.issued_at
        FROM academy_certificates c
        JOIN webinars w ON w.id = c.webinar_id
        WHERE c.user_id = ? AND c.status = 'issued'
        ORDER BY c.issued_at DESC
        LIMIT 2
    ");
    $certEarnedStmt->execute([$userId]);
    foreach ($certEarnedStmt->fetchAll() as $row) {
        $activityRows[] = [
            'type'    => 'certificate_earned',
            'icon'    => 'fa-award',
            'title'   => 'Certificate earned',
            'detail'  => (string) ($row['title'] ?? 'Certificate') . ' (' . (string) ($row['certificate_ref'] ?? '') . ')',
            'time'    => (string) ($row['issued_at'] ?? 'now'),
            'href'    => "dashboard.php?screen=certificates",
        ];
    }

    usort($activityRows, static fn(array $a, array $b): int => strtotime($b['time']) <=> strtotime($a['time']));
    $recentActivities = array_slice($activityRows, 0, 5);
} catch (Throwable $e) {
    error_log('Academy overview activity error: ' . $e->getMessage());
}

// ──────────────────────────────────────────────
// Upcoming Deadlines / Pending Assessments
// ──────────────────────────────────────────────

$upcomingDeadlines = [];
try {
    $deadlineStmt = $pdo->prepare("
        SELECT a.id assessment_id, a.title, a.pass_score, w.title course_title, w.id course_id
        FROM academy_assessments a
        JOIN webinars w ON w.id = a.webinar_id
        JOIN webinar_registrations r ON r.webinar_id = w.id AND r.user_id = ?
        LEFT JOIN academy_attempts at ON at.assessment_id = a.id AND at.user_id = ? AND at.passed = 1
        WHERE a.status = 'active' AND COALESCE(w.status, 'active') = 'active' AND at.id IS NULL
        ORDER BY a.id ASC
        LIMIT 4
    ");
    $deadlineStmt->execute([$userId, $userId]);
    $upcomingDeadlines = $deadlineStmt->fetchAll();
} catch (Throwable $e) {
    error_log('Academy overview deadlines error: ' . $e->getMessage());
}

// ──────────────────────────────────────────────
// Certificates Gallery (Top 4)
// ──────────────────────────────────────────────

$recentCerts = [];
try {
    $certGalleryStmt = $pdo->prepare("
        SELECT c.certificate_ref, w.title course_title, c.issued_at, c.status,
               c.certificate_pdf_path
        FROM academy_certificates c
        JOIN webinars w ON w.id = c.webinar_id
        WHERE c.user_id = ? AND c.status IN ('issued', 'pending', 'approved')
        ORDER BY c.issued_at DESC, c.requested_at DESC
        LIMIT 4
    ");
    $certGalleryStmt->execute([$userId]);
    $recentCerts = $certGalleryStmt->fetchAll();

    $grpCertStmt = $pdo->prepare("
        SELECT g.certificate_ref, gt.title group_title, g.issued_at, g.status,
               g.certificate_pdf_path
        FROM academy_group_certificates g
        JOIN academy_certificate_groups gt ON gt.id = g.group_id
        WHERE g.user_id = ? AND g.status IN ('issued', 'pending', 'approved')
        ORDER BY g.issued_at DESC, g.requested_at DESC
        LIMIT 4
    ");
    $grpCertStmt->execute([$userId]);
    foreach ($grpCertStmt->fetchAll() as $gc) {
        $gc['course_title'] = 'Pathway: ' . ($gc['group_title'] ?? 'Specialization');
        unset($gc['group_title']);
        $recentCerts[] = $gc;
    }

    usort($recentCerts, static fn(array $a, array $b): int =>
        (strtotime($b['issued_at'] ?: '2000-01-01') ?? 0) <=> (strtotime($a['issued_at'] ?: '2000-01-01') ?? 0)
    );
    $recentCerts = array_slice($recentCerts, 0, 4);
} catch (Throwable $e) {
    error_log('Academy overview certificates error: ' . $e->getMessage());
}

if (!function_exists('ac_relative_time')) {
    function ac_relative_time(string $datetime): string
    {
        $timestamp = strtotime($datetime);
        if (!$timestamp) return '-';
        $diff = time() - $timestamp;
        if ($diff < 0) return date('M j, Y', $timestamp);
        if ($diff < 60) return 'Just now';
        if ($diff < 3600) return floor($diff / 60) . 'm ago';
        if ($diff < 86400) return floor($diff / 3600) . 'h ago';
        if ($diff < 604800) return floor($diff / 86400) . 'd ago';
        return date('M j, Y', $timestamp);
    }
}

$userDisplayName = (string) ($user['name'] ?? 'Learner');
$userRoleLabel   = academy_role_label(academy_current_role($pdo, $user));
$userAccountRef  = (string) ($user['app_ref'] ?? strtoupper(substr((string) ($user['email'] ?? 'USR'), 0, 8)) . '***');
$userEmail       = (string) ($user['email'] ?? 'N/A');
$userJoined      = date('M j, Y', strtotime((string) ($user['created_at'] ?? 'now')));
?>

<style>
/* ── NATCODEV Executive Overview Styles ── */
.ov-hero{
  background:linear-gradient(135deg,#0d3310 0%,#15521a 50%,#1B5E20 100%);
  border-radius:16px;
  padding:24px 28px;
  color:#fff;
  display:flex;
  justify-content:space-between;
  align-items:center;
  gap:24px;
  margin-bottom:22px;
  box-shadow:0 10px 28px rgba(15,61,27,.12);
  border:1px solid rgba(250,204,21,.25);
  position:relative;
  overflow:hidden;
}
.ov-hero::after{
  content:'';
  position:absolute;
  right:-30px;
  bottom:-40px;
  width:220px;
  height:220px;
  background:radial-gradient(circle,rgba(250,204,21,.08) 0%,transparent 70%);
  border-radius:50%;
  pointer-events:none;
}
.ov-hero-left{display:flex;align-items:center;gap:18px;min-width:0}
.ov-hero-avatar{
  width:58px;
  height:58px;
  border-radius:50%;
  background:linear-gradient(135deg,#FACC15,#EAB308);
  color:#15521a;
  display:grid;
  place-items:center;
  font-size:22px;
  font-weight:950;
  flex-shrink:0;
  box-shadow:0 4px 12px rgba(0,0,0,.15);
  border:2px solid #ffffff;
}
.ov-hero-title h2{
  margin:0;
  font-size:clamp(1.35rem,2.2vw,1.8rem);
  font-weight:950;
  color:#ffffff;
  line-height:1.2;
  letter-spacing:-.2px;
}
.ov-hero-meta{
  display:flex;
  gap:8px;
  align-items:center;
  margin-top:6px;
  flex-wrap:wrap;
}
.ov-pill{
  display:inline-flex;
  align-items:center;
  gap:5px;
  padding:3px 10px;
  border-radius:999px;
  font-size:11px;
  font-weight:800;
  line-height:1;
}
.ov-pill-gold{background:rgba(250,204,21,.2);color:#FEF08A;border:1px solid rgba(250,204,21,.4)}
.ov-pill-glass{background:rgba(255,255,255,.14);color:#ffffff;border:1px solid rgba(255,255,255,.2)}
.ov-hero-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap;flex-shrink:0}
.btn-hero-primary{
  background:#FACC15;
  color:#0F3D1B;
  border:0;
  padding:10px 18px;
  border-radius:9px;
  font-size:13px;
  font-weight:950;
  display:inline-flex;
  align-items:center;
  gap:8px;
  box-shadow:0 4px 14px rgba(250,204,21,.3);
  transition:all .18s ease;
  text-decoration:none;
}
.btn-hero-primary:hover{background:#FDE047;transform:translateY(-1px);color:#0F3D1B}
.btn-hero-secondary{
  background:rgba(255,255,255,.12);
  color:#ffffff;
  border:1px solid rgba(255,255,255,.3);
  padding:10px 16px;
  border-radius:9px;
  font-size:13px;
  font-weight:850;
  display:inline-flex;
  align-items:center;
  gap:7px;
  transition:all .18s ease;
  text-decoration:none;
}
.btn-hero-secondary:hover{background:rgba(255,255,255,.22);color:#ffffff;transform:translateY(-1px)}

/* ── KPI Horizontal Strip ── */
.ov-kpis{
  display:grid;
  grid-template-columns:repeat(6,minmax(0,1fr));
  gap:14px;
  margin-bottom:24px;
}
@media(max-width:1200px){.ov-kpis{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media(max-width:640px){.ov-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}}
.ov-kpi-card{
  background:#ffffff;
  border:1px solid var(--border);
  border-radius:12px;
  padding:16px 14px;
  display:flex;
  align-items:center;
  gap:12px;
  box-shadow:0 2px 6px rgba(0,0,0,.03);
  transition:transform .18s ease,border-color .18s ease,box-shadow .18s ease;
}
.ov-kpi-card:hover{
  transform:translateY(-2px);
  border-color:var(--green-500);
  box-shadow:0 6px 18px rgba(15,61,27,.08);
}
.ov-kpi-icon{
  width:44px;
  height:44px;
  border-radius:11px;
  display:grid;
  place-items:center;
  font-size:19px;
  flex-shrink:0;
}
.ov-kpi-icon.enroll{background:#E8F5E9;color:#1B5E20}
.ov-kpi-icon.progress{background:#DBEAFE;color:#1D4ED8}
.ov-kpi-icon.complete{background:#D1FAE5;color:#065F46}
.ov-kpi-icon.cert{background:#FEF3C7;color:#92400E}
.ov-kpi-icon.quiz{background:#EDE9FE;color:#7C3AED}
.ov-kpi-icon.hours{background:#FFE4E6;color:#DC2626}
.ov-kpi-data{min-width:0}
.ov-kpi-num{
  display:block;
  font-size:1.55rem;
  font-weight:950;
  color:#0F3D1B;
  line-height:1.1;
}
.ov-kpi-label{
  display:block;
  font-size:.68rem;
  font-weight:800;
  color:var(--muted);
  text-transform:uppercase;
  letter-spacing:.05em;
  margin-top:2px;
  white-space:nowrap;
  overflow:hidden;
  text-overflow:ellipsis;
}

/* ── Main Layout: 8/4 Grid ── */
.ov-main-layout{
  display:grid;
  grid-template-columns:minmax(0,1fr) 320px;
  gap:22px;
  align-items:start;
}
@media(max-width:1040px){.ov-main-layout{grid-template-columns:1fr}}

/* ── Section Cards ── */
.ov-section-card{
  background:#ffffff;
  border:1px solid var(--border);
  border-radius:14px;
  overflow:hidden;
  box-shadow:0 2px 8px rgba(0,0,0,.03);
  margin-bottom:20px;
}
.ov-section-head{
  padding:16px 20px;
  background:linear-gradient(135deg,#F8FFF8 0%,#F1F8EE 100%);
  border-bottom:1px solid #D8EADF;
  display:flex;
  justify-content:space-between;
  align-items:center;
  gap:12px;
}
.ov-section-head h3{
  margin:0;
  font-size:1.02rem;
  font-weight:950;
  color:#0F3D1B;
  display:flex;
  align-items:center;
  gap:8px;
}
.ov-section-link{
  font-size:.76rem;
  font-weight:900;
  color:var(--green-700);
  background:#ffffff;
  border:1px solid #CBD5E1;
  padding:5px 11px;
  border-radius:999px;
  transition:all .15s ease;
  text-decoration:none;
  white-space:nowrap;
}
.ov-section-link:hover{
  background:var(--green-50);
  border-color:var(--green-600);
  color:var(--green-800);
}

/* ── Enrolled Courses Grid ── */
.ec-grid{
  display:grid;
  grid-template-columns:repeat(2,minmax(0,1fr));
  gap:16px;
  padding:18px;
}
@media(max-width:720px){.ec-grid{grid-template-columns:1fr}}
.ec-item{
  background:#ffffff;
  border:1px solid var(--border);
  border-radius:12px;
  overflow:hidden;
  display:flex;
  flex-direction:column;
  transition:transform .18s ease,border-color .18s ease,box-shadow .18s ease;
}
.ec-item:hover{
  transform:translateY(-2px);
  border-color:#86EFAC;
  box-shadow:0 8px 24px rgba(15,61,27,.08);
}
.ec-thumb{
  aspect-ratio:16/9;
  width:100%;
  background:linear-gradient(135deg,#1B5E20,#0F3D1B);
  position:relative;
  overflow:hidden;
  display:grid;
  place-items:center;
}
.ec-thumb img{
  width:100%;
  height:100%;
  object-fit:cover;
  transition:transform .3s ease;
}
.ec-item:hover .ec-thumb img{transform:scale(1.03)}
.ec-thumb-tag{
  position:absolute;
  top:9px;
  left:9px;
  background:rgba(15,61,27,.85);
  color:#FEF08A;
  backdrop-filter:blur(4px);
  font-size:10px;
  font-weight:900;
  padding:3px 8px;
  border-radius:6px;
  letter-spacing:.3px;
}
.ec-thumb-badge{
  position:absolute;
  top:9px;
  right:9px;
  font-size:10px;
  font-weight:900;
  padding:3px 8px;
  border-radius:6px;
  backdrop-filter:blur(4px);
}
.ec-thumb-badge.complete{background:rgba(6,95,70,.9);color:#ffffff}
.ec-thumb-badge.in-progress{background:rgba(29,78,216,.9);color:#ffffff}
.ec-thumb-badge.registered{background:rgba(217,119,6,.9);color:#ffffff}
.ec-body{
  padding:14px 16px;
  display:flex;
  flex-direction:column;
  flex:1;
}
.ec-title{
  font-size:.9rem;
  font-weight:900;
  color:#0F3D1B;
  line-height:1.35;
  margin:0 0 8px;
  min-height:38px;
}
.ec-progress-bar{
  width:100%;
  height:7px;
  background:#E2E8F0;
  border-radius:999px;
  overflow:hidden;
  margin-top:auto;
}
.ec-progress-fill{
  height:100%;
  border-radius:999px;
  transition:width .4s ease;
}
.ec-progress-meta{
  display:flex;
  justify-content:space-between;
  align-items:center;
  margin-top:5px;
  font-size:11px;
  font-weight:800;
  color:var(--muted);
}
.ec-actions{
  margin-top:12px;
  padding-top:12px;
  border-top:1px solid #F1F5F9;
  display:flex;
  justify-content:space-between;
  align-items:center;
  gap:8px;
}
.btn-ec-play{
  background:var(--green-700);
  color:#ffffff;
  border:0;
  padding:6px 12px;
  border-radius:7px;
  font-size:11px;
  font-weight:900;
  display:inline-flex;
  align-items:center;
  gap:5px;
  text-decoration:none;
  transition:background .15s ease;
}
.btn-ec-play:hover{background:var(--green-600);color:#ffffff}
.btn-ec-cert{
  background:#FEF3C7;
  color:#92400E;
  border:1px solid #FDE68A;
  padding:6px 12px;
  border-radius:7px;
  font-size:11px;
  font-weight:900;
  display:inline-flex;
  align-items:center;
  gap:5px;
  text-decoration:none;
}
.btn-ec-cert:hover{background:#FDE68A}
.ec-outline-link{
  font-size:11px;
  font-weight:800;
  color:var(--muted);
  text-decoration:none;
  display:inline-flex;
  align-items:center;
  gap:4px;
}
.ec-outline-link:hover{color:var(--green-700);text-decoration:underline}

/* ── Activity & Deadlines 2-Col Subgrid ── */
.ov-subgrid{
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:18px;
  margin-bottom:20px;
}
@media(max-width:720px){.ov-subgrid{grid-template-columns:1fr}}

.act-list{list-style:none;margin:0;padding:0}
.act-row{
  display:flex;
  align-items:flex-start;
  gap:12px;
  padding:12px 18px;
  border-bottom:1px solid var(--border-light);
  transition:background .15s ease;
}
.act-row:last-child{border-bottom:0}
.act-row:hover{background:#F8FAFC}
.act-icon-box{
  width:32px;
  height:32px;
  border-radius:8px;
  display:grid;
  place-items:center;
  font-size:14px;
  flex-shrink:0;
}
.act-icon-box.course_started{background:#DBEAFE;color:#1D4ED8}
.act-icon-box.lesson_completed{background:#D1FAE5;color:#065F46}
.act-icon-box.quiz_passed{background:#FEF3C7;color:#92400E}
.act-icon-box.certificate_earned{background:#EDE9FE;color:#7C3AED}
.act-info{flex:1;min-width:0}
.act-title{
  font-size:.82rem;
  font-weight:850;
  color:#0F3D1B;
  line-height:1.25;
  margin:0;
}
.act-detail{
  font-size:.74rem;
  color:var(--muted);
  font-weight:700;
  margin-top:2px;
  white-space:nowrap;
  overflow:hidden;
  text-overflow:ellipsis;
}
.act-time{
  font-size:.7rem;
  color:var(--muted);
  font-weight:800;
  white-space:nowrap;
  flex-shrink:0;
}

/* ── Deadlines Items ── */
.deadline-box{padding:14px 18px;display:grid;gap:10px}
.dl-row, .dl-row:visited, .dl-row:active{
  display:flex;
  align-items:center;
  gap:10px;
  padding:11px 13px;
  border-radius:9px;
  background:#FFFBEB;
  border:1px solid #FDE68A;
  text-decoration:none;
  color:inherit;
  transition:all .15s ease;
  cursor:pointer;
}
.dl-row:hover{
  background:#FEF3C7;
  border-color:#F59E0B;
  transform:translateX(3px);
  box-shadow:0 2px 6px rgba(180,83,9,0.10);
  text-decoration:none;
  color:inherit;
}
.dl-row:focus-visible{
  outline:2px solid #F59E0B;
  outline-offset:2px;
}
.dl-title-box{flex:1;min-width:0}
.dl-name{
  font-size:.8rem;
  font-weight:850;
  color:#92400E;
  line-height:1.2;
  white-space:nowrap;
  overflow:hidden;
  text-overflow:ellipsis;
}
.dl-course{
  font-size:.7rem;
  color:#B45309;
  font-weight:700;
  margin-top:1px;
}
.dl-date-badge{
  font-size:.68rem;
  font-weight:900;
  background:#FEF3C7;
  color:#92400E;
  padding:3px 8px;
  border-radius:6px;
  white-space:nowrap;
  flex-shrink:0;
}

/* ── Certificates Gallery ── */
.crt-grid{
  display:grid;
  grid-template-columns:repeat(auto-fill,minmax(200px,1fr));
  gap:14px;
  padding:18px;
}
.crt-box{
  background:#ffffff;
  border:1px solid var(--border);
  border-radius:12px;
  padding:16px 14px;
  text-align:center;
  box-shadow:0 1px 4px rgba(0,0,0,.03);
  transition:all .18s ease;
}
.crt-box:hover{
  border-color:#F59E0B;
  transform:translateY(-2px);
  box-shadow:0 6px 18px rgba(245,158,11,.12);
}
.crt-gold-icon{
  width:42px;
  height:42px;
  border-radius:10px;
  background:linear-gradient(135deg,#FEF3C7,#FDE68A);
  color:#92400E;
  display:grid;
  place-items:center;
  margin:0 auto 10px;
  font-size:20px;
}
.crt-name{
  font-size:.82rem;
  font-weight:900;
  color:#0F3D1B;
  line-height:1.25;
  margin:0 0 4px;
}
.crt-ref{
  font-size:.68rem;
  color:var(--muted);
  font-weight:800;
  margin-bottom:8px;
  font-family:monospace;
}

/* ── Sidebar Cards ── */
.sb-widget{
  background:#ffffff;
  border:1px solid var(--border);
  border-radius:14px;
  overflow:hidden;
  box-shadow:0 2px 8px rgba(0,0,0,.03);
  margin-bottom:18px;
}
.sb-widget-head{
  padding:14px 18px;
  background:linear-gradient(135deg,#F8FFF8 0%,#EFF6EE 100%);
  border-bottom:1px solid #D8EADF;
  font-size:.88rem;
  font-weight:950;
  color:#0F3D1B;
  display:flex;
  align-items:center;
  gap:8px;
}
.sb-widget-body{padding:16px 18px}
.sb-user-card{display:flex;align-items:center;gap:12px;margin-bottom:14px}
.sb-user-avatar{
  width:46px;
  height:46px;
  border-radius:50%;
  background:linear-gradient(135deg,var(--green-700),var(--green-600));
  color:#ffffff;
  display:grid;
  place-items:center;
  font-weight:950;
  font-size:17px;
  flex-shrink:0;
}
.sb-user-name{font-size:.9rem;font-weight:900;color:#0F3D1B}
.sb-user-role{font-size:.72rem;color:var(--muted);font-weight:700}
.sb-wallet-box{
  background:linear-gradient(135deg,#F0FDF4,#DCFCE7);
  border:1px solid #BBF7D0;
  border-radius:10px;
  padding:12px 14px;
  margin:12px 0;
  display:flex;
  justify-content:space-between;
  align-items:center;
}
.sb-wallet-bal{
  font-size:1.15rem;
  font-weight:950;
  color:#14532D;
  line-height:1.1;
}
.sb-wallet-lbl{
  font-size:.68rem;
  font-weight:800;
  color:#166534;
  text-transform:uppercase;
  letter-spacing:.04em;
  margin-top:2px;
}
.sb-meta-list{display:grid;gap:8px;border-top:1px solid #F1F5F9;padding-top:12px;font-size:.78rem}
.sb-meta-row{display:flex;justify-content:space-between;align-items:center}
.sb-meta-k{color:var(--muted);font-weight:800}
.sb-meta-v{color:#334155;font-weight:800;max-width:170px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}

/* Quick Nav Links */
.sb-nav-list{display:grid;gap:3px;padding:8px}
.sb-nav-item{
  display:flex;
  align-items:center;
  gap:10px;
  padding:9px 12px;
  border-radius:8px;
  color:inherit;
  text-decoration:none;
  transition:all .15s ease;
}
.sb-nav-item:hover{background:var(--green-50);color:var(--green-800)}
.sb-nav-icon{
  width:30px;
  height:30px;
  border-radius:7px;
  display:grid;
  place-items:center;
  font-size:13px;
  flex-shrink:0;
}
.sb-nav-text strong{display:block;font-size:.82rem;font-weight:850;color:#0F3D1B;line-height:1.2}
.sb-nav-text small{display:block;font-size:.66rem;color:var(--muted);font-weight:700}
.sb-nav-arrow{margin-left:auto;color:#94A3B8;font-size:10px}

/* Multimodal Tip */
.tip-box{
  background:linear-gradient(135deg,#EFF6FF,#DBEAFE);
  border:1px solid #BFDBFE;
  border-radius:12px;
  padding:14px 16px;
  margin-top:16px;
}
.tip-title{font-size:.8rem;font-weight:900;color:#1E40AF;display:flex;align-items:center;gap:6px;margin-bottom:4px}
.tip-text{font-size:.72rem;line-height:1.45;color:#1E3A8A;font-weight:700}
</style>

<!-- ════════════ Executive Hero Welcome ════════════ -->
<section class="ov-hero">
  <div class="ov-hero-left">
    <div class="ov-hero-avatar"><?= e($initials) ?></div>
    <div class="ov-hero-title">
      <h2>Welcome back, <?= e($userDisplayName) ?>! &#128075;</h2>
      <div class="ov-hero-meta">
        <span class="ov-pill ov-pill-gold"><i class="fas fa-shield-alt"></i> <?= e($userRoleLabel) ?></span>
        <span class="ov-pill ov-pill-glass"><i class="fas fa-id-badge"></i> Account <?= e($userAccountRef) ?></span>
        <span class="ov-pill ov-pill-glass"><i class="fas fa-chart-line"></i> <?= (int) $coursesEnrolled ?> Enrolled &bull; <?= (int) $avgProgress ?>% Avg Progress</span>
      </div>
    </div>
  </div>
  <div class="ov-hero-actions">
    <?php if ($activeCourse): ?>
      <a class="btn-hero-primary" href="<?= e($resumeUrl) ?>">
        <i class="fas fa-play"></i> Resume: <?= e(mb_strimwidth((string) $activeCourse['title'], 0, 24, '...')) ?>
      </a>
    <?php endif; ?>
    <a class="btn-hero-secondary" href="dashboard.php?screen=catalog">
      <i class="fas fa-compass"></i> Explore Catalog
    </a>
    <a class="btn-hero-secondary" href="dashboard.php?screen=transactions">
      <i class="fas fa-wallet"></i> <?= ac_money($walletBalance ?? 0) ?>
    </a>
  </div>
</section>

<!-- ════════════ KPI Metrics Row (6 Horizontal Columns) ════════════ -->
<section class="ov-kpis">
  <div class="ov-kpi-card">
    <div class="ov-kpi-icon enroll"><i class="fas fa-book-reader"></i></div>
    <div class="ov-kpi-data">
      <span class="ov-kpi-num"><?= number_format($coursesEnrolled) ?></span>
      <span class="ov-kpi-label">Courses Enrolled</span>
    </div>
  </div>
  <div class="ov-kpi-card">
    <div class="ov-kpi-icon progress"><i class="fas fa-spinner"></i></div>
    <div class="ov-kpi-data">
      <span class="ov-kpi-num"><?= number_format($inProgress) ?></span>
      <span class="ov-kpi-label">In Progress</span>
    </div>
  </div>
  <div class="ov-kpi-card">
    <div class="ov-kpi-icon complete"><i class="fas fa-check-double"></i></div>
    <div class="ov-kpi-data">
      <span class="ov-kpi-num"><?= number_format($completed) ?></span>
      <span class="ov-kpi-label">Completed</span>
    </div>
  </div>
  <div class="ov-kpi-card">
    <div class="ov-kpi-icon cert"><i class="fas fa-award"></i></div>
    <div class="ov-kpi-data">
      <span class="ov-kpi-num"><?= number_format($certificatesEarned) ?></span>
      <span class="ov-kpi-label">Certificates Earned</span>
    </div>
  </div>
  <div class="ov-kpi-card">
    <div class="ov-kpi-icon quiz"><i class="fas fa-question-circle"></i></div>
    <div class="ov-kpi-data">
      <span class="ov-kpi-num"><?= number_format($quizzesTaken) ?></span>
      <span class="ov-kpi-label">Quizzes Taken</span>
    </div>
  </div>
  <div class="ov-kpi-card">
    <div class="ov-kpi-icon hours"><i class="fas fa-clock"></i></div>
    <div class="ov-kpi-data">
      <span class="ov-kpi-num"><?= $totalHoursLearned ?>h</span>
      <span class="ov-kpi-label">Hours Learned</span>
    </div>
  </div>
</section>

<!-- ════════════ Main Layout: 8-Col Primary + 4-Col Sidebar ════════════ -->
<div class="ov-main-layout">

  <!-- ──── LEFT PRIMARY CONTENT AREA (8 Cols) ──── -->
  <div>

    <!-- ──── My Enrolled Courses ──── -->
    <section class="ov-section-card">
      <div class="ov-section-head">
        <h3><i class="fas fa-graduation-cap"></i> My Enrolled Courses</h3>
        <?php if ($coursesEnrolled > 4): ?>
          <a class="ov-section-link" href="dashboard.php?screen=learning">View All (<?= $coursesEnrolled ?>) &rarr;</a>
        <?php endif; ?>
      </div>

      <?php if (!empty($enrolledCourses)): ?>
        <div class="ec-grid">
          <?php foreach ($enrolledCourses as $cRow): ?>
            <?php
              $cId = (int) ($cRow['id'] ?? $cRow['webinar_id'] ?? 0);
              $progress = max(0, min(100, (int) ($cRow['progress_percent'] ?? 0)));
              $totalL = (int) ($cRow['lessons'] ?? $cRow['total_lessons'] ?? 0);
              $doneL  = (int) ($cRow['completed_lessons'] ?? 0);
              $cTitle = (string) ($cRow['title'] ?? 'Course');
              $cCat   = (string) ($cRow['category'] ?? 'Professional Skills');
              $status = (string) ($cRow['completion_status'] ?? 'registered');
              $isComplete = ($status === 'completed' || $progress >= 100);

              $thumbUrl = '';
              if (function_exists('academy_course_artwork_url')) {
                  $thumbUrl = academy_course_artwork_url($cRow, '../');
              }
              if ($thumbUrl === '') {
                  $thumbUrl = '../assets/academy/course-grower-foundation.svg';
              }
            ?>
            <article class="ec-item">
              <div class="ec-thumb">
                <img src="<?= e($thumbUrl) ?>" alt="<?= e($cTitle) ?>" loading="lazy">
                <span class="ec-thumb-tag"><?= e($cCat) ?></span>
                <span class="ec-thumb-badge <?= $isComplete ? 'complete' : ($progress > 0 ? 'in-progress' : 'registered') ?>">
                  <?= $isComplete ? 'Completed' : ($progress > 0 ? $progress . '% Done' : 'Enrolled') ?>
                </span>
              </div>
              <div class="ec-body">
                <h4 class="ec-title"><?= e($cTitle) ?></h4>
                <div class="ec-progress-bar">
                  <div class="ec-progress-fill" style="width:<?= $progress ?>%;background:<?= $isComplete ? '#059669' : ($progress > 40 ? '#1D4ED8' : '#F59E0B') ?>"></div>
                </div>
                <div class="ec-progress-meta">
                  <span><?= $doneL ?> of <?= $totalL ?> lessons</span>
                  <span><?= $progress ?>%</span>
                </div>
                <div class="ec-actions">
                  <?php if ($isComplete): ?>
                    <a class="btn-ec-cert" href="dashboard.php?screen=certificates">
                      <i class="fas fa-award"></i> Certificate
                    </a>
                  <?php else: ?>
                    <a class="btn-ec-play" href="dashboard.php?screen=lesson&course_id=<?= $cId ?>">
                      <i class="fas fa-play"></i> <?= $progress > 0 ? 'Resume' : 'Start' ?>
                    </a>
                  <?php endif; ?>
                  <a class="ec-outline-link" href="dashboard.php?screen=course&course_id=<?= $cId ?>">
                    <i class="fas fa-list-ul"></i> Outline
                  </a>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div style="text-align:center;padding:48px 24px;color:var(--muted)">
          <i class="fas fa-book-open" style="font-size:2.8rem;color:var(--green-500);margin-bottom:12px;display:block"></i>
          <h4 style="color:#0F3D1B;margin:0 0 6px;font-size:1.1rem;font-weight:900">No Enrolled Courses Yet</h4>
          <p style="margin:0 0 16px;font-size:.85rem">Explore our certified agribusiness pathways and start your learning journey.</p>
          <a href="dashboard.php?screen=catalog" class="btn btn-p"><i class="fas fa-search"></i> Browse Catalog</a>
        </div>
      <?php endif; ?>
    </section>

    <!-- ──── Activity Feed + Deadlines (2-Col Subgrid) ──── -->
    <div class="ov-subgrid">

      <!-- Recent Activity Feed -->
      <section class="ov-section-card" style="margin-bottom:0">
        <div class="ov-section-head">
          <h3><i class="fas fa-history"></i> Recent Activity</h3>
        </div>
        <div>
          <?php if (!empty($recentActivities)): ?>
            <ul class="act-list">
              <?php foreach ($recentActivities as $act): ?>
                <li class="act-row">
                  <div class="act-icon-box <?= e($act['type']) ?>">
                    <i class="fas <?= e($act['icon']) ?>"></i>
                  </div>
                  <div class="act-info">
                    <p class="act-title"><?= e($act['title']) ?></p>
                    <p class="act-detail"><?= e($act['detail']) ?></p>
                  </div>
                  <span class="act-time"><?= e(ac_relative_time($act['time'])) ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <p style="text-align:center;color:var(--muted);padding:32px 18px;font-weight:700;font-size:.82rem">
              No recent activity yet.<br><small>Your completed lessons and quiz scores will appear here.</small>
            </p>
          <?php endif; ?>
        </div>
      </section>

      <!-- Upcoming Quizzes & Deadlines -->
      <section class="ov-section-card" style="margin-bottom:0">
        <div class="ov-section-head">
          <h3><i class="fas fa-calendar-check"></i> Assessments & Quizzes</h3>
          <a class="ov-section-link" href="dashboard.php?screen=quiz<?= !empty($upcomingDeadlines[0]['course_id']) ? '&course_id=' . (int) $upcomingDeadlines[0]['course_id'] : '' ?>">Take Exam &rarr;</a>
        </div>
        <div>
          <?php if (!empty($upcomingDeadlines)): ?>
            <div class="deadline-box">
              <?php foreach ($upcomingDeadlines as $dl): ?>
                <a href="dashboard.php?screen=quiz&course_id=<?= (int) ($dl['course_id'] ?? 0) ?>" class="dl-row">
                  <i class="fas fa-question-circle" style="color:#B45309;font-size:16px;flex-shrink:0"></i>
                  <div class="dl-title-box">
                    <div class="dl-name"><?= e((string) $dl['title']) ?></div>
                    <div class="dl-course"><?= e((string) $dl['course_title']) ?></div>
                  </div>
                  <span class="dl-date-badge"><?= !empty($dl['pass_score']) ? (int) $dl['pass_score'] . '% Pass Mark' : 'Open Exam' ?></span>
                </a>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div style="text-align:center;padding:32px 18px">
              <i class="fas fa-check-circle" style="font-size:2rem;color:var(--green-500);margin-bottom:8px;display:block"></i>
              <strong style="color:#0F3D1B;font-size:.88rem">All Caught Up!</strong><br>
              <small style="color:var(--muted);font-weight:700">No pending assessments at this time.</small>
            </div>
          <?php endif; ?>
        </div>
      </section>

    </div>

    <!-- ──── Certificates & Credentials Showcase ──── -->
    <section class="ov-section-card">
      <div class="ov-section-head">
        <h3><i class="fas fa-certificate"></i> Certificates & Credentials</h3>
        <a class="ov-section-link" href="dashboard.php?screen=certificates">View All &rarr;</a>
      </div>
      <div>
        <?php if (!empty($recentCerts)): ?>
          <div class="crt-grid">
            <?php foreach ($recentCerts as $cert): ?>
              <?php
                $cRef = (string) ($cert['certificate_ref'] ?? '—');
                $pdfPath = trim((string) ($cert['certificate_pdf_path'] ?? ''));
              ?>
              <div class="crt-box">
                <div class="crt-gold-icon"><i class="fas fa-award"></i></div>
                <h5 class="crt-name"><?= e((string) ($cert['course_title'] ?? 'Certificate')) ?></h5>
                <div class="crt-ref"><?= e($cRef) ?></div>
                <span class="badge-pill bp-green">Issued</span>
                <?php if ($pdfPath !== ''): ?>
                  <div style="margin-top:8px">
                    <a class="btn btn-o btn-s" href="../<?= e($pdfPath) ?>" target="_blank"><i class="fas fa-download"></i> PDF</a>
                  </div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div style="text-align:center;padding:32px 20px">
            <i class="fas fa-medal" style="font-size:2rem;color:#F59E0B;margin-bottom:8px;display:block"></i>
            <strong style="color:#0F3D1B;font-size:.9rem">Your Certification Pathway</strong><br>
            <p style="color:var(--muted);font-size:.78rem;margin:4px 0 14px;max-width:440px;display:inline-block">
              Complete course modules and achieve 75%+ on final exams to earn official NATCODEV Verified Certificates with verifiable credentials.
            </p><br>
            <a class="btn btn-o btn-s" href="dashboard.php?screen=certificates"><i class="fas fa-compass"></i> View Pathways</a>
          </div>
        <?php endif; ?>
      </div>
    </section>

  </div>
  <!-- /LEFT PRIMARY AREA -->

  <!-- ──── RIGHT SIDEBAR (4 Cols / 320px) ──── -->
  <aside>

    <!-- Learner Profile & Wallet Widget -->
    <div class="sb-widget">
      <div class="sb-widget-head"><i class="fas fa-id-card"></i> Learner Profile</div>
      <div class="sb-widget-body">
        <div class="sb-user-card">
          <div class="sb-user-avatar"><?= e($initials) ?></div>
          <div>
            <div class="sb-user-name"><?= e($userDisplayName) ?></div>
            <div class="sb-user-role"><?= e($userRoleLabel) ?></div>
          </div>
        </div>

        <div class="sb-wallet-box">
          <div>
            <div class="sb-wallet-bal"><?= ac_money($walletBalance ?? 0) ?></div>
            <div class="sb-wallet-lbl">Wallet Balance</div>
          </div>
          <a class="btn btn-sm btn-p" href="dashboard.php?screen=transactions" style="padding:6px 11px;font-size:11px">
            <i class="fas fa-plus-circle"></i> Fund
          </a>
        </div>

        <div class="sb-meta-list">
          <div class="sb-meta-row">
            <span class="sb-meta-k">Account Ref</span>
            <span class="sb-meta-v"><?= e($userAccountRef) ?></span>
          </div>
          <div class="sb-meta-row">
            <span class="sb-meta-k">Email</span>
            <span class="sb-meta-v"><?= e($userEmail) ?></span>
          </div>
          <div class="sb-meta-row">
            <span class="sb-meta-k">Joined</span>
            <span class="sb-meta-v"><?= e($userJoined) ?></span>
          </div>
          <div class="sb-meta-row">
            <span class="sb-meta-k">Completion</span>
            <span class="sb-meta-v" style="color:var(--green-700)"><?= (int) $completed ?> / <?= (int) $coursesEnrolled ?> Courses</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Quick Navigation Hub -->
    <div class="sb-widget">
      <div class="sb-widget-head"><i class="fas fa-bolt"></i> Learning Shortcuts</div>
      <div class="sb-nav-list">
        <a class="sb-nav-item" href="dashboard.php?screen=catalog">
          <span class="sb-nav-icon" style="background:#E8F5E9;color:#1B5E20"><i class="fas fa-compass"></i></span>
          <span class="sb-nav-text"><strong>Browse Catalog</strong><small>Discover new courses</small></span>
          <i class="fas fa-chevron-right sb-nav-arrow"></i>
        </a>
        <a class="sb-nav-item" href="dashboard.php?screen=learning">
          <span class="sb-nav-icon" style="background:#DBEAFE;color:#1D4ED8"><i class="fas fa-book-open"></i></span>
          <span class="sb-nav-text"><strong>My Learning</strong><small>All enrolled courses</small></span>
          <i class="fas fa-chevron-right sb-nav-arrow"></i>
        </a>
        <a class="sb-nav-item" href="dashboard.php?screen=certificates">
          <span class="sb-nav-icon" style="background:#FEF3C7;color:#92400E"><i class="fas fa-award"></i></span>
          <span class="sb-nav-text"><strong>Certificates & Badges</strong><small>Download & verify</small></span>
          <i class="fas fa-chevron-right sb-nav-arrow"></i>
        </a>
        <a class="sb-nav-item" href="dashboard.php?screen=transactions">
          <span class="sb-nav-icon" style="background:#F3E8FF;color:#7C3AED"><i class="fas fa-wallet"></i></span>
          <span class="sb-nav-text"><strong>Wallet & Receipts</strong><small>Payment history</small></span>
          <i class="fas fa-chevron-right sb-nav-arrow"></i>
        </a>
        <a class="sb-nav-item" href="dashboard.php?screen=messages">
          <span class="sb-nav-icon" style="background:#E0F2FE;color:#0369A1"><i class="fas fa-envelope"></i></span>
          <span class="sb-nav-text"><strong>Course Notices</strong><small>Alerts and updates</small></span>
          <i class="fas fa-chevron-right sb-nav-arrow"></i>
        </a>
        <a class="sb-nav-item" href="dashboard.php?screen=support">
          <span class="sb-nav-icon" style="background:#FFE4E6;color:#DC2626"><i class="fas fa-headset"></i></span>
          <span class="sb-nav-text"><strong>Help & Support</strong><small>Open a learner ticket</small></span>
          <i class="fas fa-chevron-right sb-nav-arrow"></i>
        </a>
      </div>
    </div>

    <!-- Multimodal Feature Spotlight -->
    <div class="tip-box">
      <div class="tip-title"><i class="fas fa-headphones-alt"></i> Multitrack Audio</div>
      <div class="tip-text">
        Every academy lesson supports 5 audio tracks: <strong>English</strong>, <strong>Nigerian Pidgin</strong>, <strong>Hausa</strong>, <strong>Yoruba</strong>, and <strong>Igbo</strong>. Switch languages seamlessly in the lesson player.
      </div>
    </div>

  </aside>
  <!-- /RIGHT SIDEBAR -->

</div>
<!-- /MAIN LAYOUT -->
