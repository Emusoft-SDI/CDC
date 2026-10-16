<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/academy.php';
require_once __DIR__ . '/../lib/otp-delivery.php';

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

app_ensure_core_schema($pdo);
academy_ensure_schema($pdo);
app_add_column_if_missing($pdo, 'users', 'platform_role', "VARCHAR(60) NULL");
app_ensure_user_verification_schema($pdo);

$courseId = (int) ($_GET['course_id'] ?? $_POST['course_id'] ?? 0);
$course = null;
if ($courseId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM webinars WHERE id = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$courseId]);
    $course = $stmt->fetch() ?: null;
}

$programId = (int) ($_GET['program_id'] ?? $_POST['program_id'] ?? 0);
$program = null;
if ($programId > 0) {
    $programStmt = $pdo->prepare("SELECT id, title FROM academy_programs WHERE id = ? AND status = 'active' LIMIT 1");
    $programStmt->execute([$programId]);
    $program = $programStmt->fetch() ?: null;
}

if (current_user($pdo)) {
    if ($programId > 0) {
        redirect_to('dashboard.php?screen=catalog&program=' . $programId);
    }
    redirect_to($course ? 'dashboard.php?screen=course&course_id=' . $courseId : 'dashboard.php');
}

$error = '';
$message = '';
if ($programId > 0) {
    $oauthNext = 'academy/dashboard.php?screen=catalog&program=' . $programId;
    $otpTarget = $oauthNext;
} else {
    $oauthNext = $course ? 'academy/dashboard.php?screen=course&course_id=' . $courseId : 'academy/dashboard.php?screen=catalog';
}
if (isset($_GET['social'])) {
    app_oauth_begin((string) $_GET['social'], 'learner', $oauthNext);
}
if (isset($_GET['oauth_callback'])) {
    try {
        $oauth = app_oauth_finish($pdo, (string) $_GET['oauth_callback'], 'learner');
        $stmt = $pdo->prepare('SELECT id, email FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([(int) $oauth['user_id']]);
        $oauthUser = $stmt->fetch() ?: ['id' => (int) $oauth['user_id'], 'email' => ''];
        unset($_SESSION['user_id']);
        if ($programId > 0) {
            $otpTarget = 'academy/dashboard.php?screen=catalog&program=' . $programId;
        } elseif ($course) {
            $otpTarget = 'academy/dashboard.php?screen=course&course_id=' . $courseId;
        } else {
            $otpTarget = 'academy/dashboard.php?screen=catalog';
        }
        $otpStart = otp_begin_email_login_challenge($pdo, $oauthUser, $otpTarget);
        if ($otpStart['ok']) {
            redirect_to('../verify-otp.php');
        }
        $error = (string) $otpStart['message'];
    } catch (Throwable $e) {
        error_log('Academy OAuth error: ' . $e->getMessage());
        $error = $e->getMessage();
    }
}
if (isset($_GET['oauth_error'])) {
    $error = app_oauth_missing_credentials_message((string) ($_GET['provider'] ?? 'google'));
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!app_check_rate_limit('learner_registration', 5, 3600)) {
        $error = 'Too many registration attempts. Please try again in an hour.';
    } elseif (!verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'Please refresh and try again.';
    } else {
        // Every public sign-up goes through the shared registration wizard:
        // the account is created as needs_confirmation and the learner
        // profile is completed in the wizard after the email OTP is verified.
        try {
            $result = registration_wizard_begin($pdo, 'learner', $_POST);
            if (!empty($result['ok'])) {
                redirect_to('../verify-otp.php');
            }
            $error = (string) $result['message'];
        } catch (Throwable $e) {
            error_log('Academy registration error: ' . $e->getMessage());
            $error = str_contains($e->getMessage(), 'Duplicate') ? 'That email already exists. Please login instead.' : 'Unable to create learner account now.';
        }
    }
}

$logo = app_primary_logo_url();
$loginNext = '../academy/dashboard.php' . ($program ? '?screen=catalog&program=' . $programId : ($course ? '?screen=course&course_id=' . $courseId : ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register as Learner - NATCODEV Academy</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    *{box-sizing:border-box}body{margin:0;font-family:"Segoe UI",Arial,sans-serif;background:#f4faf2;color:#101828}.auth{min-height:100vh;display:grid;grid-template-columns:minmax(0,1fr) 500px}.visual{background:linear-gradient(90deg,rgba(5,45,20,.76),rgba(5,45,20,.18)),url("../assets/academy/natcodev-academy-public-hero.png") center/cover;color:#fff;padding:50px;display:flex;align-items:flex-end}.visual h1{font-size:clamp(2.5rem,5vw,4.8rem);line-height:.96;margin:0 0 14px}.visual p{font-size:1.18rem;max-width:760px;line-height:1.55}.panel{background:#fff;padding:42px;display:grid;align-content:center}.brand{display:flex;gap:12px;align-items:center;margin-bottom:24px;color:#06451f;text-decoration:none}.brand img{width:58px;height:58px;border-radius:50%}.brand strong{font-size:1.55rem}label{display:block;font-weight:850;margin-top:12px}input{width:100%;border:1px solid #dfe8d8;border-radius:8px;padding:13px;margin-top:6px}.pass{position:relative}.pass input{padding-right:74px}.pass button{position:absolute;right:8px;bottom:7px;border:0;border-radius:7px;background:#eef8ef;color:#06451f;padding:8px 10px;font-weight:900;cursor:pointer}.social{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:12px 0}.social a{border:1px solid #dfe8d8;border-radius:8px;padding:12px;text-align:center;color:#06451f;font-weight:900;text-decoration:none}.btn{width:100%;border:0;border-radius:8px;background:#08753a;color:#fff;padding:14px;margin-top:16px;font-weight:950;font-size:1rem;cursor:pointer}.alert{padding:12px;border-radius:8px;margin:10px 0;font-weight:850}.err{background:#fff1f2;color:#b42318}.ok{background:#e8f6ec;color:#06451f}.course{border:1px solid #dfe8d8;border-radius:8px;background:#f8fcf7;padding:13px;margin:12px 0}.links{display:flex;justify-content:space-between;gap:12px;margin-top:16px}a{color:#06451f;font-weight:900;text-decoration:none}.fineprint{font-size:.88rem;color:#667085;line-height:1.45}@media(max-width:900px){.auth{grid-template-columns:1fr}.visual{min-height:360px}.panel{padding:24px}}
  </style>
</head>
<body>
<main class="auth">
  <section class="visual"><div><h1>Start as a learner.</h1><p>Academy access is open. Other platform roles are requested later and approved by NATCODEV platform admins.</p></div></section>
  <section class="panel">
    <a class="brand" href="index.php"><img src="<?= e($logo) ?>" alt="NATCODEV"><strong>NATCODEV Academy</strong></a>
    <h2>Create learner account</h2>
    <p>Use this account for courses, progress, payments, certificates, and role requests.</p>
    <?php if ($program): ?><div class="course"><strong>Selected program:</strong><br><?= e((string) $program['title']) ?></div><?php endif; ?>
    <?php if ($course): ?><div class="course"><strong>Selected course:</strong><br><?= e((string) $course['title']) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert err"><?= e($error) ?></div><?php endif; ?>
    <?php if ($message): ?><div class="alert ok"><?= e($message) ?></div><?php endif; ?>
    <?= app_social_buttons('learner', $oauthNext) ?>
    <form method="post" action="../register-wizard.php">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="board" value="learner">
      <input type="hidden" name="action" value="start">
      <input type="hidden" name="course_id" value="<?= $courseId ?>">
      <input type="hidden" name="program_id" value="<?= $programId ?>">
      <label>Name<input name="name" required></label>
      <label>Email<input type="email" name="email" required></label>
      <label>Phone<input name="phone" placeholder="+234..."></label>
      <label class="pass">Password<input id="academy-register-password" type="password" name="password" minlength="8" required><button type="button" data-toggle-password="academy-register-password">Show</button></label>
      <button class="btn">Register as Learner</button>
    </form>

    <div style="margin-top:16px;border-top:1px solid #e5e7eb;padding-top:16px">
      <strong>Already started?</strong>
      <p class="fineprint" style="margin:6px 0 10px">Enter the email you used and we will send a fresh code, then take you back to the step you stopped at.</p>
      <form method="post" action="../register-wizard.php">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="board" value="learner">
        <input type="hidden" name="action" value="resume">
        <label>Email you registered with<input type="email" name="email" required></label>
        <button class="btn" type="submit">Send me a new code</button>
      </form>
    </div>
    <p class="fineprint">Learner registration does not grant seller, provider, grower, field, coordinator, or admin privileges. Those are requested from inside your account and reviewed before approval.</p>
    <div class="links"><a href="login.php<?= $course ? '?course_id=' . (int) $courseId : '' ?>">Already registered?</a><a href="index.php">Back to Academy</a></div>
  </section>
</main>
<script>document.querySelectorAll('[data-toggle-password]').forEach(function(button){button.addEventListener('click',function(){var input=document.getElementById(button.getAttribute('data-toggle-password'));if(!input)return;var show=input.type==='password';input.type=show?'text':'password';button.textContent=show?'Hide':'Show';});});</script>
<?= public_footer() ?>
</body>
</html>


